<?php

/**
 * Global database.
 */
global $wpdb;

/**
 * Get an instance of the WC_Order object.
 */
$order = wc_get_order($_POST['params']['orderId']);

/**
 * Shipping method selected.
 */
$orderShippingMethod = false;
$orderShippingMethods = $order->get_shipping_methods();
foreach ($orderShippingMethods as $method) {
    $orderShippingMethod = str_replace('dpdro_shipping_', '', $method->get_method_id());
    $orderShippingMethod = str_replace('shipping_dpd_', '', $orderShippingMethod);
}

/**
 * Data settings.
 */
$dataSettings = new DataSettings($wpdb);
$settings = $dataSettings->getSettings();
?>

<!-- Modal header. -->
<div class="d-modal-head">
    <h4><?= __('Select DPD RO shipping method Order ID:', 'dpdro'); ?> <b><?= $order->id; ?></b></h4>
</div>

<!-- Modal body. -->
<div class="d-modal-body">

    <!-- Error. -->
    <?php if ($errorMessage && !empty($errorMessage)) : ?>
        <h5 class="d-response d-error"><?= $errorMessage; ?></h5>
    <?php endif; ?>

    <!-- Success. -->
    <?php if ($successMessage && !empty($successMessage)) : ?>
        <h5 class="d-response d-success"><?= $successMessage; ?></h5>
    <?php endif; ?>

    <!-- Validation. -->
    <?php
    $wooOrder = new WooOrder($wpdb);
    $checkCountry = $wooOrder->checkCountry($order->get_shipping_country());
    $orderAddress = $wooOrder->getOrderAddress($order->id);
    $orderShipping = $wooOrder->getOrderShipping($order->id);
    $orderShippingHistory = $wooOrder->getOrderShippingHistory($order->id);
    ?>

    <!-- Check if order has DPD RO shipping methos. -->
    <?php $dataList = new DataLists($wpdb); ?>
    <?php if (!$dataList->getServiceById($orderShippingMethod) && $orderAddress && !empty($orderAddress)) : ?>

        <!-- Select shipping method. -->
        <div class="d-modal-step">
            <?php

            /**
             * Library API.
             */
            $libraryApi = new LibraryApi($settings['username'], $settings['password']);

            /** 
             * Data settings Calculate.
             */
            $settings['dpdro_pickup'] = !empty($orderAddress->office_id) ? $orderAddress->office_id : false;
            $settings['dpdro_pickup_name'] = '';
            $settings['dpdro_pickup_type'] = '';
            $settings['chosen_payment'] = $order->get_payment_method();
            $settings['contents_cost'] = $order->get_subtotal();
            $settings['package'] = array(
                'country'  => $order->get_shipping_country(),
                'state'    => $order->get_shipping_state(),
                'city'     => $order->get_shipping_city(),
                'postcode' => $order->get_shipping_postcode(),
            );
            $settingsCOD = array(
                'destination' => array(
                    'country'  => $order->get_shipping_country(),
                    'state'    => $order->get_shipping_state(),
                    'city'     => $order->get_shipping_city(),
                    'postcode' => $order->get_shipping_postcode()
                )
            );

            /** 
             * User.
             */
            $settings['customer_phone'] = $order->get_billing_phone();
            $settings['customer_email'] = $order->get_billing_email();

            /** 
             * Products.
             */
            $products = $order->get_items();
            $productsData = array();
            $settings['total_weight'] = 0.0;
            if (!empty($products)) {
                foreach ($products as $product) {
                    $productData = $product->get_data();
                    $productInfo = wc_get_product($productData['product_id']);
                    $productWeight = method_exists($productInfo, 'get_weight') ? $productInfo->get_weight() : 0;
                    $productWeight = wc_get_weight($productWeight, 'kg', get_option('woocommerce_weight_unit'));
                    $productWeight = $productWeight == '0' ? $settings['default_weight'] : $productWeight;
                    $productWeight = $productWeight == '0' ? '0.001' : $productWeight;
                    $productQuantity = $productData['quantity'];
                    for ($i = 0; $i < (int) $productQuantity; $i++) {
                        $settings['total_weight'] = $settings['total_weight'] + $productWeight;
                        array_push($productsData, [
                            'weight' => $productWeight,
                            'width'  => method_exists($productInfo, 'get_width') ? $productInfo->get_width() : 0,
                            'depth'  => method_exists($productInfo, 'get_length') ? $productInfo->get_length() : 0,
                            'height' => method_exists($productInfo, 'get_height') ? $productInfo->get_height() : 0
                        ]);
                    }
                }
            }

            /** 
             * Payment.
             */
            $settings['cod'] = false;
            if (isset($settings['payment_status']) && $settings['chosen_payment'] === 'cod') {

                /** 
                 * Data zones.
                 */
                $settings['cod'] = DataZones::zoneMatchingPackage($settingsCOD, $dataSettings);
            }

            /** 
             * Data settings.
             */
            $addresses = new DataAddresses($wpdb);

            /**
             * All shipping method available.
             */
            $shippingMethods = WC()->shipping->load_shipping_methods();
            $shippingMethodsActive = array();
            foreach ($shippingMethods as $id => $shippingMethod) {
                $shippingMethodCode = str_replace('dpdro_shipping_', '', $shippingMethod->id);
                $shippingMethodCode = str_replace('shipping_dpd_', '', $shippingMethodCode);
                if ($dataList->getServiceById($shippingMethodCode)) {

                    /** 
                     * Parcels.
                     */
                    $settings['parcels'] = [];
                    if ($shippingMethodCode == '2412') {
                        $index = 0;
                        $seqNo = 1;
                        foreach ($productsData as $product) {
                            if ($product['weight'] > 0) {
                                $settings['parcels'][$index] = [
                                    'seqNo'  => (int) $seqNo,
                                    'weight' => (float) $product['weight'],
                                    'size'   => [
                                        'width' => (float) $product['width'],
                                        'depth' => (float) $product['depth'],
                                        'height' => (float) $product['height'],
                                    ],
                                ];
                                $index++;
                                $seqNo++;
                            }
                        }
                    } else {
                        $groupsWeight = [];
                        sort($productsData);
                        if ($productsData && is_array($productsData)  && !empty($productsData)) {
                            $count = 0;
                            foreach ($productsData as $productShipping) {
                                if (!isset($groupsWeight[$count])) {
                                    $groupsWeight[$count] = 0;
                                }
                                if ($groupsWeight[$count] + (float) $productShipping['weight'] > (float) $this->options['max_weight']) {
                                    $count++;
                                    $groupsWeight[$count] = (float) $productShipping['weight'];
                                } else {
                                    $groupsWeight[$count] += (float)$productShipping['weight'];
                                }
                            }
                        }
                        if ($groupsWeight && is_array($groupsWeight) && !empty($groupsWeight)) {
                            $index = 0;
                            $seqNo = 1;
                            foreach ($groupsWeight as $weight) {
                                if ($weight > 0) {
                                    $settings['parcels'][$index] = [
                                        'seqNo'  => (int) $seqNo,
                                        'weight' => (float) $weight,
                                    ];
                                    $index++;
                                    $seqNo++;
                                }
                            }
                        }
                    }

                    /** 
                     * Calculate.
                     */
                    $serviceTax = $libraryApi->calculate($shippingMethodCode, $settings, $addresses);
                    if ($serviceTax && !isset($serviceTax['error'])) {
                        $taxService = (float) $serviceTax['price']['total'];
                        if ($checkCountry) {
                            if ($settings['cod'] && DataZones::checkCustomPayment($settingsCOD, $dataSettings)) {
                                $taxService = $taxService - (float) $settings['payment_tax'];
                            }
                        }
                        $taxServiceRate = 'no';
                        if ($settings['courier_service_payer'] == 'RECIPIENT') {

                            /** 
                             * Recipient pay the tax.
                             */
                        } else {
                            if (!empty($shippingMethod->cost)) {
                                $taxService = (float) $shippingMethod->cost;
                                $taxServiceRate = 'yes';
                            } else {
                                $dataZoneId = DataZones::getZoneId($settingsCOD);
                                $taxRate = $wooOrder->taxRate($shippingMethodCode, $dataZoneId, $settings['total_weight'], $order->get_subtotal());
                                if (isset($settings['dpdro_pickup']) && !empty($settings['dpdro_pickup'])) {
                                    $taxRate = $wooOrder->taxRateOffice($shippingMethodCode, $dataZoneId, $settings['dpdro_pickup'], $settings['total_weight'], $order->get_subtotal());
                                }
                                if ($taxRate) {
                                    if ($taxRate->calculation_type) {
                                        $taxService = (float) $taxRate->tax_rate;
                                    } else {
                                        $taxService = (float) $taxService + ($taxService * ($taxRate->tax_rate / 100));
                                    }
                                    $taxServiceRate = 'yes';
                                }
                            }
                        }
                        $shippingMethodsActive[] = [
                            'id'       => $shippingMethod->id,
                            'title'    => $shippingMethod->method_title,
                            'tax'      => $taxService,
                            'tax_rate' => $taxServiceRate,
                            'service'  => $shippingMethodCode,
                        ];
                    }
                }
            }
            ?>
            <div class="d-field">
                <div class="d-field-list">
                    <ul>
                        <?php foreach ($shippingMethodsActive as $key => $method) : ?>
                            <li>
                                <?php if ($orderShipping && !empty($orderShipping)) : ?>
                                    <?php if ($orderShipping->method_code == $method['service']) : ?>
                                        <input id="d-setting-set-service-<?= $method['service']; ?>" type="radio" name="service_id" value="<?= $method['id']; ?>" checked />
                                    <?php else : ?>
                                        <input id="d-setting-set-service-<?= $method['service']; ?>" type="radio" name="service_id" value="<?= $method['id']; ?>" />
                                    <?php endif; ?>
                                <?php else : ?>
                                    <?php if ($_POST['params'] && !empty($_POST['params']) && $_POST['params']['serviceCode'] && !empty($_POST['params']['serviceCode'])) : ?>
                                        <?php if ($_POST['params']['serviceCode'] == $method['service']) : ?>
                                            <input id="d-setting-set-service-<?= $method['service']; ?>" type="radio" name="service_id" value="<?= $method['id']; ?>" checked />
                                        <?php else : ?>
                                            <input id="d-setting-set-service-<?= $method['service']; ?>" type="radio" name="service_id" value="<?= $method['id']; ?>" />
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <?php if ($key == 0) : ?>
                                            <input id="d-setting-set-service-<?= $method['service']; ?>" type="radio" name="service_id" value="<?= $method['id']; ?>" checked />
                                        <?php else : ?>
                                            <input id="d-setting-set-service-<?= $method['service']; ?>" type="radio" name="service_id" value="<?= $method['id']; ?>" />
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <label for="d-setting-set-service-<?= $method['service']; ?>"><?= $method['tax']; ?> - <?= $method['title']; ?></label>
                                <input type="hidden" name="service_code" value="<?= $method['service']; ?>" />
                                <input type="hidden" name="service_name" value="<?= $method['title']; ?>" />
                                <input type="hidden" name="service_tax" value="<?= $method['tax']; ?>" />
                                <input type="hidden" name="service_tax_rate" value="<?= $method['tax_rate']; ?>" />
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php if ($orderShippingHistory && !empty($orderShippingHistory)) : ?>
                <hr>
                <div class="d-history">
                    <h5><?= __('Shipping method changes history:', 'dpdro'); ?></h5>
                    <table class="d-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php _e('Method', 'dpdro'); ?></th>
                                <th><?php _e('Date', 'dpdro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderShippingHistory as $key => $history) : ?>
                                <tr>
                                    <td><?= $key + 1; ?></td>
                                    <td><?= $history->method_title; ?></td>
                                    <td><?= $history->date_added; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal footer. -->
<div class="d-modal-foot">
    <?php $ajaxNonceBoBack = wp_create_nonce('dpdro_create_shipment'); ?>
    <?php $ajaxNonce = wp_create_nonce('dpdro_save_shipping_method'); ?>
    <button data-nonce="<?= $ajaxNonceBoBack; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button secondary js-d-go-back">
        <?php _e('Go back to create shipment', 'dpdro'); ?>
    </button>
    <button data-nonce="<?= $ajaxNonce; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-save-shipping-method">
        <?php _e('Save shipping method', 'dpdro'); ?>
    </button>
</div>