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
    <h4><?= __('Create shipment Order ID:', 'dpdro'); ?> <b><?= $order->id; ?></b></h4>
</div>

<!-- Modal body. -->
<div class="d-modal-body">

    <!-- Payment. -->
    <?php if ($order->get_payment_method() != 'cod') : ?>
        <div class="d-modal-step d-modal-payment">
            <p><?php _e('We switch "Courier service payer" to "SENDER" because the order has a different payment method than "COD".', 'dpdro'); ?></p>
        </div>
    <?php endif; ?>

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
    $addressValidation = false;
    ?>

    <!-- Check if order has DPD RO shipping method. -->
    <?php $dataList = new DataLists($wpdb); ?>
    <?php if (!$dataList->getServiceById($orderShippingMethod) && $orderAddress && !empty($orderAddress)) : ?>
        <?php if ($orderShipping && !empty($orderShipping)) : ?>

            <!-- Change shipping method. -->
            <div class="d-modal-step d-modal-shipping-method">
                <h5>
                    <p>
                        <?php _e('Order shipping method:', 'dpdro'); ?>
                        <b><?= $order->get_shipping_to_display(); ?></b>
                    </p>
                    <p class="d-success">
                        <?php _e('DPD RO shipping method selected:', 'dpdro'); ?>
                        <b><?= $orderShipping->method_title; ?></b>
                    </p>
                </h5>
                <?php $ajaxNonceAddShippingMethod = wp_create_nonce('dpdro_add_shipping_method'); ?>
                <button data-nonce="<?= $ajaxNonceAddShippingMethod; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-add-shipping-method">
                    <?php _e('Change shipping method', 'dpdro'); ?>
                </button>
            </div>
        <?php else : ?>

            <!-- Select shipping method. -->
            <div class="d-modal-step d-modal-shipping-method">
                <h5>
                    <p>
                        <?php _e('Order shipping method:', 'dpdro'); ?>
                        <b><?= $order->get_shipping_to_display(); ?></b>
                    </p>
                    <p class="d-danger">
                        <?php _e('No DPD RO shipping method selected.', 'dpdro'); ?>
                    </p>
                </h5>
                <?php $ajaxNonceAddShippingMethod = wp_create_nonce('dpdro_add_shipping_method'); ?>
                <button data-nonce="<?= $ajaxNonceAddShippingMethod; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-add-shipping-method">
                    <?php _e('Add shipping method', 'dpdro'); ?>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Address. -->
    <div class="d-modal-step d-modal-address">
        <?php if ($checkCountry) : ?>
            <?php if ($orderAddress && !empty($orderAddress)) : ?>
                <?php if ($orderAddress->method != 'pickup') : ?>

                    <!-- Validation. -->
                    <div class="d-modal-step d-modal-validation">
                        <?php $ajaxNonceSkip = wp_create_nonce('dpdro_skip_validation_address'); ?>
                        <?php $ajaxNonceValidation = wp_create_nonce('dpdro_validation_address'); ?>
                        <?php if ($orderAddress->status == 'validated') : ?>
                            <p class="d-address success">
                                <?php _e('DPD RO order delivery address:', 'dpdro'); ?>
                                <b>
                                    <?= $orderAddress->address_city_name != '' ? $orderAddress->address_city_name : ''; ?>
                                    <?= $orderAddress->address_street_name != '' ? ', str. ' . $orderAddress->address_street_name : ''; ?>
                                    <?= $orderAddress->address_number != '' ? ', nr. ' . $orderAddress->address_number : ''; ?>
                                    <?= $orderAddress->address_block != '' ? ', bl. ' . $orderAddress->address_block : ''; ?>
                                    <?= $orderAddress->address_apartment != '' ? ', ap. ' . $orderAddress->address_apartment : ''; ?>
                                    <?= $order->get_shipping_postcode() != '' ? ', ' . $order->get_shipping_postcode() : ''; ?>
                                </b>
                            </p>
                            <button data-nonce="<?= $ajaxNonceValidation; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-validation-address">
                                <?php _e('Change address', 'dpdro'); ?>
                            </button>
                        <?php elseif ($orderAddress->status == 'normalize') : ?>
                            <p class="d-address">
                                <?php _e('DPD RO order delivery address:', 'dpdro'); ?>
                                <b><?= $orderAddress->address_city_name . ', ' . $orderAddress->address; ?></b>
                            </p>
                            <button data-nonce="<?= $ajaxNonceSkip; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button warning js-d-skip-validation-address">
                                <?php _e('Skip validation address', 'dpdro'); ?>
                            </button>
                            <button data-nonce="<?= $ajaxNonceValidation; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-validation-address">
                                <?php _e('Validate address', 'dpdro'); ?>
                            </button>
                        <?php elseif ($orderAddress->status == 'skip') : ?>
                            <p class="d-address">
                                <?php _e('DPD RO order delivery address:', 'dpdro'); ?>
                                <b><?= $orderAddress->address_city_name . ', ' . $orderAddress->address; ?></b>
                            </p>
                            <button data-nonce="<?= $ajaxNonceValidation; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-validation-address">
                                <?php _e('Validate address', 'dpdro'); ?>
                            </button>
                        <?php else : ?>
                            <p class="d-address">
                                <?php _e('DPD RO order delivery address:', 'dpdro'); ?>
                                <b><?= $orderAddress->address_city_name . ', ' . $orderAddress->address; ?></b>
                            </p>
                            <button data-nonce="<?= $ajaxNonceValidation; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-validation-address">
                                <?php _e('Validate address', 'dpdro'); ?>
                            </button>
                            <button data-nonce="<?= $ajaxNonceSkip; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button warning js-d-skip-validation-address">
                                <?php _e('Skip validation address', 'dpdro'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <h5 class="d-pickup">
                        <p>
                            <?php _e('DPD RO order pickup address:', 'dpdro'); ?>
                            <b><?= $orderAddress->office_name; ?></b>
                        </p>
                        <?php $ajaxNonceChangeOffice = wp_create_nonce('dpdro_change_office'); ?>
                        <button data-nonce="<?= $ajaxNonceChangeOffice; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-change-office">
                            <?php _e('Change office', 'dpdro'); ?>
                        </button>
                    </h5>
                <?php endif; ?>
            <?php else : ?>
                <h5 class="d-response d-error"><?php _e('No DPD RO address stored for this order.', 'dpdro'); ?></h5>
                <?php $ajaxNonceAddAddress = wp_create_nonce('dpdro_add_address'); ?>
                <button data-nonce="<?= $ajaxNonceAddAddress; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-add-address">
                    <?php _e('Add DPD RO address', 'dpdro'); ?>
                </button>
            <?php endif; ?>
        <?php else : ?>
            <p class="d-address">
                <?php _e('WooCommerce order address:', 'dpdro'); ?>
                <b><?= $order->get_shipping_city() . ', ' . $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(); ?></b>
            </p>
        <?php endif; ?>
    </div>

    <!-- Products. -->
    <div class="d-modal-step d-modal-products">
        <h5><?php _e('Group the products in your shipment into parcels', 'dpdro'); ?></h5>
        <p><?php _e('This module lets you organize your products into parcels using the table below. Select parcel number.', 'dpdro'); ?></p>
        <?php $products = $order->get_items(); ?>
        <?php if ($products && !empty($products)) : ?>
            <?php

            $count = 0;
            $parcelQuantity = 0;
            $parcelQuantityMax = (float) $settings['max_weight'];
            ?>
            <div class="d-modal-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'dpdro'); ?></th>
                            <th><?php _e('Product', 'dpdro'); ?></th>
                            <th><?php _e('Weight', 'dpdro'); ?></th>
                            <th><?php _e('Parcel', 'dpdro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $parcelNoPalet = 1; $shipmentNode = '';?>
                        <?php foreach ($products as $product) : ?>
                            <?php
                            if (($settings['packaging_method']  == 'all')) {
                                $count++;
                            } else {
                                $count = 1;
                            }
                            $productData = $product->get_data();
                            $productInfo = wc_get_product($productData['product_id']);
                            $productWeight = method_exists($productInfo, 'get_weight') ? $productInfo->get_weight() : 0;
                            $productWeight = $productWeight == '0' ? $settings['default_weight'] : $productWeight;
                            $productWeight = $productWeight == '0' ? '0.001' : $productWeight;
                            $productQuantity = $productData['quantity'];
                            $productWidth =  method_exists($productInfo, 'get_width') ? $productInfo->get_width() : 0;
                            $productDepth =  method_exists($productInfo, 'get_length') ? $productInfo->get_length() : 0;
                            $productHeight =  method_exists($productInfo, 'get_height') ? $productInfo->get_height() : 0;

                            $shipmentNode .=  $productInfo->get_sku() . ' ';
                            $incrementCount = false;
                            ?>
                            <?php for ($quantity = 0; $quantity < (int) $productQuantity; $quantity++) : ?>
                                <?php
                                    if ($incrementCount) {
                                        $count++;
                                    }
                                    $incrementCount = true;
                                ?>
                                <tr class="js-d-modal-table-product" data-index="<?= $productData['id']; ?>">
                                    <td>
                                        <input name="id" type="hidden" value="<?= $productData['product_id']; ?>" />
                                        <?= $productData['product_id']; ?>
                                    </td>
                                    <td>
                                        <input name="name" type="hidden" value="<?= $productData['name']; ?>" />
                                        <?= $productData['name']; ?>
                                    </td>
                                    <td>
                                        <div class="d-field">
                                            <?php if (!$dataList->getServiceById($orderShippingMethod)) : ?>
                                                <?php if ($orderShipping && !empty($orderShipping) && $orderShipping->method_code == '2412') : ?>
                                                    <input name="weight" type="text" value="<?= $productWeight; ?>" disabled />
                                                <?php else : ?>
                                                    <input name="weight" type="text" value="<?= $productWeight; ?>" />
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <?php if ($orderShippingMethod && $orderShippingMethod == '2412') : ?>
                                                    <input name="weight" type="text" value="<?= $productWeight; ?>" disabled />
                                                <?php else : ?>
                                                    <input name="weight" type="text" value="<?= $productWeight; ?>" />
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <input name="width" type="hidden" value="<?= $productWidth; ?>" />
                                            <input name="depth" type="hidden" value="<?= $productDepth; ?>" />
                                            <input name="height" type="hidden" value="<?= $productHeight; ?>" />
                                            <span><?php _e('kg', 'dpdro'); ?></span>
                                        </div>
                                    </td>
                                    <?php
                                    if (($parcelQuantity + (float) $productWeight) <= $parcelQuantityMax) {
                                        $parcelQuantity = $parcelQuantity + (float) $productWeight;
                                    } else {
                                        $parcelQuantity = (float) $productWeight;
                                    }
                                    ?>
                                    <td>
                                        <?php if (!$dataList->getServiceById($orderShippingMethod)) : ?>
                                            <?php if ($orderShipping && !empty($orderShipping) && $orderShipping->method_code == '2412') : ?>
                                                <div class="d-field">
                                                    <input name="parcel" type="text" value="<?= $parcelNoPalet; ?>" disabled />
                                                    <?php $parcelNoPalet = $parcelNoPalet + 1; ?>
                                                </div>
                                            <?php else : ?>
                                                <?php if ($settings['packaging_method']  == 'all') : ?>
                                                    <div class="d-field">
                                                        <select name="parcel">
                                                            <?php $parcelNo = 1; ?>
                                                            <?php foreach ($products as $parcel) : ?>
                                                                <?php $parcelData = $parcel->get_data(); ?>
                                                                <?php for ($parcelIndex = 0; $parcelIndex < (int) $parcel->get_data()['quantity']; $parcelIndex++) : ?>
                                                                    <?php if ($count == $parcelNo) : ?>
                                                                        <option selected value="<?= $parcelNo; ?>"><?= $parcelNo; ?></option>
                                                                    <?php else : ?>
                                                                        <option value="<?= $parcelNo; ?>"><?= $parcelNo; ?></option>
                                                                    <?php endif; ?>
                                                                    <?php $parcelNo = $parcelNo + 1; ?>
                                                                <?php endfor; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="d-field">
                                                        <input name="parcel" type="text" value="1" disabled />
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <?php if ($orderShippingMethod && $orderShippingMethod == '2412') : ?>
                                                <div class="d-field">
                                                    <input name="parcel" type="text" value="<?= $parcelNoPalet; ?>" disabled />
                                                    <?php $parcelNoPalet = $parcelNoPalet + 1; ?>
                                                </div>
                                            <?php else : ?>
                                                <?php if ($settings['packaging_method']  == 'all') : ?>
                                                    <div class="d-field">
                                                        <select name="parcel">
                                                            <?php $parcelNo = 1; ?>
                                                            <?php foreach ($products as $parcel) : ?>
                                                                <?php $parcelData = $parcel->get_data(); ?>
                                                                <?php for ($parcelIndex = 0; $parcelIndex < (int) $parcel->get_data()['quantity']; $parcelIndex++) : ?>
                                                                    <?php if ($count == $parcelNo) : ?>
                                                                        <option selected value="<?= $parcelNo; ?>"><?= $parcelNo; ?></option>
                                                                    <?php else : ?>
                                                                        <option value="<?= $parcelNo; ?>"><?= $parcelNo; ?></option>
                                                                    <?php endif; ?>
                                                                    <?php $parcelNo = $parcelNo + 1; ?>
                                                                <?php endfor; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="d-field">
                                                        <input name="parcel" type="text" value="1" disabled />
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <b><?php _e('NOTE:', 'dpdro'); ?></b>
                    <?php _e('The price may change depending on the distribution of the products in the parcels', 'dpdro'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Parcels. -->
    <div class="d-modal-step d-modal-parcels">
        <h5><?php _e('Enter description for each parcel', 'dpdro'); ?></h5>
        <p><?php _e('You can enter description of each parcel, for communication with courier services, in the fields below.', 'dpdro'); ?></p>
        <?php if ($products && !empty($products)) : ?>
            <div class="d-modal-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('Parcel', 'dpdro'); ?></th>
                            <th><?php _e('Description', 'dpdro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$dataList->getServiceById($orderShippingMethod)) : ?>
                            <?php if ($orderShipping && !empty($orderShipping) && $orderShipping->method_code == '2412') : ?>
                                <?php $parcelNo = 1; ?>
                                <?php foreach ($products as $product) : ?>
                                    <?php
                                    $productData = $product->get_data();
                                    $productQuantity = $productData['quantity']
                                    ?>
                                    <?php for ($quantity = 0; $quantity < (int) $productQuantity; $quantity++) : ?>
                                        <tr class="js-d-modal-table-parcel" data-index="<?= $parcelNo; ?>">
                                            <td>
                                                <input name="id" type="hidden" value="<?= $parcelNo; ?>" />
                                                <?= $parcelNo; ?>
                                            </td>
                                            <td>
                                                <div class="d-field">
                                                    <input name="description" type="text" placeholder="<?php _e('Description', 'dpdro'); ?>" value="<?= $productData['product_id']; ?>" />
                                                </div>
                                            </td>
                                        </tr>
                                        <?php $parcelNo = $parcelNo + 1; ?>
                                    <?php endfor; ?>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <?php if ($settings['packaging_method']  == 'all') : ?>
                                    <?php $parcelNo = 1; ?>
                                    <?php foreach ($products as $product) : ?>
                                        <?php
                                        $productData = $product->get_data();
                                        $productQuantity = $productData['quantity']
                                        ?>
                                        <?php for ($quantity = 0; $quantity < (int) $productQuantity; $quantity++) : ?>
                                            <tr class="js-d-modal-table-parcel" data-index="<?= $parcelNo; ?>">
                                                <td>
                                                    <input name="id" type="hidden" value="<?= $parcelNo; ?>" />
                                                    <?= $parcelNo; ?>
                                                </td>
                                                <td>
                                                    <div class="d-field">
                                                        <input name="description" type="text" placeholder="<?php _e('Description', 'dpdro'); ?>" value="<?= $productData['product_id']; ?>" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $parcelNo = $parcelNo + 1; ?>
                                        <?php endfor; ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <input name="id" type="hidden" value="1" />
                                        <td>1</td>
                                        <td>
                                            <div class="d-field">
                                                <input name="description" type="text" placeholder="<?php _e('Description', 'dpdro'); ?>" value="<?php _e('Description', 'dpdro'); ?>" />
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else : ?>
                            <?php if ($orderShippingMethod && $orderShippingMethod == '2412') : ?>
                                <?php $parcelNo = 1; ?>
                                <?php foreach ($products as $product) : ?>
                                    <?php
                                    $productData = $product->get_data();
                                    $productQuantity = $productData['quantity']
                                    ?>
                                    <?php for ($quantity = 0; $quantity < (int) $productQuantity; $quantity++) : ?>
                                        <tr class="js-d-modal-table-parcel" data-index="<?= $parcelNo; ?>">
                                            <td>
                                                <input name="id" type="hidden" value="<?= $parcelNo; ?>" />
                                                <?= $parcelNo; ?>
                                            </td>
                                            <td>
                                                <div class="d-field">
                                                    <input name="description" type="text" placeholder="<?php _e('Description', 'dpdro'); ?>" value="<?= $productData['product_id']; ?>" />
                                                </div>
                                            </td>
                                        </tr>
                                        <?php $parcelNo = $parcelNo + 1; ?>
                                    <?php endfor; ?>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <?php if ($settings['packaging_method']  == 'all') : ?>
                                    <?php $parcelNo = 1; ?>
                                    <?php foreach ($products as $product) : ?>
                                        <?php
                                        $productData = $product->get_data();
                                        $productQuantity = $productData['quantity']
                                        ?>
                                        <?php for ($quantity = 0; $quantity < (int) $productQuantity; $quantity++) : ?>
                                            <tr class="js-d-modal-table-parcel" data-index="<?= $parcelNo; ?>">
                                                <td>
                                                    <input name="id" type="hidden" value="<?= $parcelNo; ?>" />
                                                    <?= $parcelNo; ?>
                                                </td>
                                                <td>
                                                    <div class="d-field">
                                                        <input name="description" type="text" placeholder="<?php _e('Description', 'dpdro'); ?>" value="<?= $productData['product_id']; ?>" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $parcelNo = $parcelNo + 1; ?>
                                        <?php endfor; ?>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <input name="id" type="hidden" value="1" />
                                        <td>1</td>
                                        <td>
                                            <div class="d-field">
                                                <input name="description" type="text" placeholder="<?php _e('Description', 'dpdro'); ?>" value="<?php _e('Description', 'dpdro'); ?>" />
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Shipment -->
    <div class="d-modal-step d-modal-shipment">
        <div class="d-half">
            <div class="d-field d-checkbox">
                <input id="shipment-swap" type="checkbox" name="swap" value="1" />
                <label for="shipment-swap"><?php _e('This is a SWAP shipment', 'dpdro'); ?></label>
            </div>
            <div class="d-field d-checkbox">
                <input id="shipment-rod" type="checkbox" name="rod" value="1" />
                <label for="shipment-rod"><?php _e('This is a ROD shipment', 'dpdro'); ?></label>
            </div>
            <?php if (!$dataList->getServiceById($orderShippingMethod)) : ?>
                <?php if ($orderShipping && !empty($orderShipping) && $orderShipping->method_code == '2412') : ?>
                    <div class="d-field d-checkbox">
                        <input id="shipment-rop" type="checkbox" name="rop" value="1" />
                        <label for="shipment-rop"><?php _e('This is a ROP shipment', 'dpdro'); ?></label>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <?php if ($orderShippingMethod && $orderShippingMethod == '2412') : ?>
                    <div class="d-field d-checkbox">
                        <input id="shipment-rop" type="checkbox" name="rop" value="1" />
                        <label for="shipment-rop"><?php _e('This is a ROP shipment', 'dpdro'); ?></label>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="d-field d-checkbox">
                <input id="shipment-rsp" type="checkbox" name="rsp" value="1" />
                <label for="shipment-rsp"><?php _e('Return reusable packaging', 'dpdro'); ?></label>
            </div>
        </div>
        <div class="d-half">
            <div class="d-field d-checkbox d-checkbox-option">
                <input id="shipment-voucher" type="checkbox" name="voucher" value="1" />
                <label for="shipment-voucher"><?php _e('This shipment has a VOUCHER', 'dpdro'); ?></label>
                <select name="voucher_sender">
                    <option selected value="SENDER"><?php _e('SENDER', 'dpdro'); ?></option>
                    <option value="RECIPIENT"><?php _e('RECIPIENT', 'dpdro'); ?></option>
                </select>
            </div>
            <div class="d-field d-checkbox d-checkbox-option">
                <?php if ($order->get_shipping_company() && $order->get_shipping_company() != '') : ?>
                    <input id="shipment-private" type="checkbox" name="private" value="1" checked />
                    <label for="shipment-private"><?php _e('This shipment is on company', 'dpdro'); ?></label>
                    <input name="private_person" type="text" value="<?= $order->get_shipping_company(); ?>" />
                <?php else: ?>
                    <input id="shipment-private" type="checkbox" name="private" value="1" />
                    <label for="shipment-private"><?php _e('This shipment is on company', 'dpdro'); ?></label>
                    <input name="private_person" type="text" value="" />
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Packages & Contents -->
    <div class="d-modal-step d-modal-package">

        <?php
        /** 
         * Library nomenclature
         */
        $nomenclature = new LibraryNomenclature();
        ?>

        <!-- Packages -->
        <div class="d-half">
            <div class="d-field">
                <label for="packages"><?php _e('Package', 'dpdro'); ?></label>
                <input list="packages-list" id="packages" type="text" name="packages" value="<?= $settings['packages']; ?>" />
                <datalist id="packages-list">
                    <?php foreach ($nomenclature->OptionsPackages() as $package) : ?>
                        <option value="<?= $package; ?>"><?= $package; ?></option>
                    <?php endforeach ?>
                </datalist>
            </div>
        </div>

        <!-- Contents -->
        <div class="d-half">
            <div class="d-field">
                <label for="contents"><?php _e('Contents', 'dpdro'); ?></label>
                <input list="contents-list" id="contents" type="text" name="contents" value="<?= $settings['contents']; ?>" />
                <datalist id="contents-list">
                    <?php foreach ($nomenclature->OptionsContents() as $content) : ?>
                        <option value="<?= $content; ?>"><?= $content; ?></option>
                    <?php endforeach ?>
                </datalist>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="d-modal-step d-modal-info">

        <!-- Notes -->
        <div class="d-half">
            <div class="d-field">
                <label><?php _e('Shipment note', 'dpdro'); ?></label>
                <textarea name="notes" rows="2"><?= $shipmentNode; ?></textarea>
            </div>
        </div>

        <!-- Ref 2 -->
        <div class="d-half">
            <div class="d-field">
                <label><?php _e('Shipment ref 2', 'dpdro'); ?></label>
                <textarea name="ref_2" rows="2"></textarea>
            </div>
        </div>
    </div>
</div>

<!-- Modal footer. -->
<div class="d-modal-foot">
    <?php $ajaxNonce = wp_create_nonce('dpdro_save_shipment'); ?>
    <?php if ($dataList->getServiceById($orderShippingMethod)) : ?>
        <input name="id" type="hidden" value="<?= $order->id; ?>">
        <?php if ($checkCountry) : ?>
            <?php if ($orderAddress && !empty($orderAddress)) : ?>
                <?php if ($orderAddress->method != 'pickup') : ?>
                    <?php if ($orderAddress->status == 'skip' || $orderAddress->status == 'validated') : ?>
                        <button type="button" class="d-button secondary js-d-modal-close">
                            <?php _e('Cancel', 'dpdro'); ?>
                        </button>
                        <button data-nonce="<?= $ajaxNonce; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-save-shipment">
                            <?php _e('Save shipment', 'dpdro'); ?>
                        </button>
                    <?php else : ?>
                        <span class="d-alert d-error"><?php _e('You must validate or skip validation address!', 'dpdro'); ?></span>
                    <?php endif; ?>
                <?php else : ?>
                    <button type="button" class="d-button secondary js-d-modal-close">
                        <?php _e('Cancel', 'dpdro'); ?>
                    </button>
                    <button data-nonce="<?= $ajaxNonce; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-save-shipment">
                        <?php _e('Save shipment', 'dpdro'); ?>
                    </button>
                <?php endif; ?>
            <?php else : ?>
                <span class="d-alert d-error"><?php _e('You must add DPD RO address to the order.', 'dpdro'); ?></span>
            <?php endif; ?>
        <?php else : ?>
            <button type="button" class="d-button secondary js-d-modal-close">
                <?php _e('Cancel', 'dpdro'); ?>
            </button>
            <button data-nonce="<?= $ajaxNonce; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-save-shipment">
                <?php _e('Save shipment', 'dpdro'); ?>
            </button>
        <?php endif; ?>
    <?php else : ?>
        <?php if ($orderShipping && !empty($orderShipping)) : ?>
            <button type="button" class="d-button secondary js-d-modal-close">
                <?php _e('Cancel', 'dpdro'); ?>
            </button>
            <button data-nonce="<?= $ajaxNonce; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button primary js-d-save-shipment">
                <?php _e('Save shipment', 'dpdro'); ?>
            </button>
        <?php else : ?>
            <span class="d-alert d-error"><?php _e('You must add DPD RO shipping method to the order.', 'dpdro'); ?></span>
        <?php endif; ?>
    <?php endif; ?>
</div>