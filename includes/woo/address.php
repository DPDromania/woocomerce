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
 * Order address.
 */
$wooOrder = new WooOrder($wpdb);
$orderAddress = $wooOrder->getOrderAddress($order->id);

/**
 * Data settings.
 */
$dataSettings = new DataSettings($wpdb);
$settings = $dataSettings->getSettings();
?>

<!-- Modal header. -->
<div class="d-modal-head">
    <h4><?= __('Add DPD RO address for Order ID', 'dpdro'); ?> <b><?= $order->id; ?></b></h4>
</div>

<!-- Modal body. -->
<div class="d-modal-body">
    <input name="id" type="hidden" value="<?= $order->id; ?>">

    <!-- Error. -->
    <?php if ($errorMessage && !empty($errorMessage)) : ?>
        <h5 class="d-response d-error"><?= $errorMessage; ?></h5>
    <?php endif; ?>

    <!-- Success. -->
    <?php if ($successMessage && !empty($successMessage)) : ?>
        <h5 class="d-response d-success"><?= $successMessage; ?></h5>
    <?php endif; ?>

    <!-- Validation. -->
    <div class="d-modal-step d-modal-validate js-d-modal-validate">
        <div class="d-modal-nav">
            <?php
            $modalDelivery = 'active';
            $modalPickup = '';
            ?>
            <?php if ($_POST['params'] && !empty($_POST['params']) && $_POST['method'] && !empty($_POST['method']) && $_POST['method'] == 'pickup') : ?>
                <?php
                $modalDelivery = '';
                $modalPickup = 'active';
                ?>
                <?php if ($settings['show_office_selection']) : ?>
                    <input id="method-delivery" class="js-d-modal-nav" type="radio" name="method" value="delivery" />
                    <label for="method-delivery"><?php _e('Delivery', 'dpdro'); ?></label>
                    <input id="method-pickup" class="js-d-modal-nav" type="radio" name="method" value="pickup" checked />
                    <label for="method-pickup"><?php _e('Pickup', 'dpdro'); ?></label>
                <?php else : ?>
                    <input id="method-delivery" class="js-d-modal-nav" type="radio" name="method" value="delivery" />
                    <label class="d-full" for="method-delivery"><?php _e('Delivery', 'dpdro'); ?></label>
                <?php endif; ?>
            <?php else : ?>
                <?php if ($settings['show_office_selection']) : ?>
                    <input id="method-delivery" class="js-d-modal-nav" type="radio" name="method" value="delivery" checked />
                    <label for="method-delivery"><?php _e('Delivery', 'dpdro'); ?></label>
                    <input id="method-pickup" class="js-d-modal-nav" type="radio" name="method" value="pickup" />
                    <label for="method-pickup"><?php _e('Pickup', 'dpdro'); ?></label>
                <?php else : ?>
                    <input id="method-delivery" class="js-d-modal-nav" type="radio" name="method" value="delivery" checked />
                    <label class="d-full" for="method-delivery"><?php _e('Delivery', 'dpdro'); ?></label>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div data-content="delivery" class="d-modal-nav-content js-d-modal-nav-content <?= $modalDelivery; ?>">
            <div class="d-full">
                <div class="d-half">
                    <div class="d-field">
                        <label><?php _e('Country', 'dpdro'); ?></label>
                        <input type="text" name="country" value="<?= $order->get_shipping_country(); ?>" disabled />
                    </div>
                </div>
                <div class="d-half">
                    <div class="d-field">
                        <label><?php _e('State', 'dpdro'); ?></label>
                        <input type="text" name="state" value="<?= WC()->countries->get_states($order->get_shipping_country())[$order->get_shipping_state()]; ?>" disabled />
                    </div>
                </div>
            </div>
            <div class="d-full">
                <div class="d-field">
                    <?php if ($_POST['params'] && !empty($_POST['params']) && $_POST['params']['cityName'] && !empty($_POST['params']['cityName'])) : ?>
                        <input type="hidden" name="address_city_id" value="<?= $_POST['params']['cityId']; ?>" />
                        <input type="hidden" name="address_city_name" value="<?= $_POST['params']['cityName']; ?>" />
                    <?php else : ?>
                        <input type="hidden" name="address_city_id" value="<?= $orderAddress->address_city_id; ?>" />
                        <input type="hidden" name="address_city_name" value="<?= $orderAddress->address_city_name; ?>" />
                    <?php endif; ?>
                    <label><?php _e('City', 'dpdro'); ?> - <?php _e('order city:', 'dpdro'); ?> <?= $order->get_shipping_city(); ?></label>
                    <?php $ajaxNonceSearchCity = wp_create_nonce('dpdro_search_city'); ?>
                    <select data-nonce="<?= $ajaxNonceSearchCity; ?>" class="js-d-search-city">
                        <?php if ($_POST['params'] && !empty($_POST['params']) && $_POST['params']['cityName'] && !empty($_POST['params']['cityName'])) : ?>
                            <option value="<?= $_POST['params']['cityId']; ?>" selected><?= $_POST['params']['cityName']; ?></option>
                        <?php else : ?>
                            <?php if ($orderAddress->address_city_name && !empty($orderAddress->address_city_name)) : ?>
                                <option value="<?= $orderAddress->address_city_id; ?>" selected><?= $orderAddress->address_city_name; ?></option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                    <script type="text/javascript">
                        // City search 
                        $ = jQuery;
                        $('.js-d-search-city').select2({
                            minimumInputLength: 3,
                            placeholder: dpdRoGeneral.placeholderSearch,
                            ajax: {
                                url: dpdRo.ajaxurl,
                                type: 'post',
                                dataType: 'json',
                                delay: 250,
                                data: function(params) {
                                    return {
                                        action: 'searchCity',
                                        nonce: $(this).attr('data-nonce'),
                                        country: $('.js-d-modal-validate').find('input[name="country"]').val(),
                                        state: $('.js-d-modal-validate').find('input[name="state"]').val(),
                                        postcode: $('.js-d-modal-validate').find('input[name="address_postcode"]').val(),
                                        search: params.term,
                                    };
                                },
                                processResults: function(response) {
                                    return {
                                        results: response
                                    };
                                },
                                cache: true
                            },
                            templateResult: function(params) {
                                if (!params.id) {
                                    return params.text;
                                }
                                var response = $("<span>" + params.name + "</span>");
                                return response;
                            },
                            templateSelection: function(params) {
                                if (!params.id) {
                                    return params.text;
                                }
                                $('.js-d-modal-validate').find('input[name="address_city_id"]').val(params.id);
                                if (params.name) {
                                    $('.js-d-modal-validate').find('input[name="address_city_name"]').val(params.name);
                                }
                                if (params.postcode && params.postcode != '') {
                                    $('.js-d-modal-validate').find('input[name="address_postcode"]').val(params.postcode);
                                }
                                if (params.siteId && params.siteId != '') {
                                    var officeMap = 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&siteID=' + params.siteId + '&selectOfficeButtonCaption=Select this office';
                                    if ($('#frameOfficeLocator').length) {
                                        $('#frameOfficeLocator').attr('src', officeMap);
                                    }
                                }
                                $('.js-d-field-street-search').removeClass('d-disabled');
                                return params.text;
                            },
                        });
                    </script>
                </div>
            </div>
            <div class="d-full">
                <div class="d-field d-disabled js-d-field-street-search">
                    <?php if ($_POST['params'] && !empty($_POST['params']) && $_POST['params']['streetName'] && !empty($_POST['params']['streetName'])) : ?>
                        <input type="hidden" name="address_street_id" value="<?= $_POST['params']['streetId']; ?>" />
                        <input type="hidden" name="address_street_type" value="<?= $_POST['params']['streetType']; ?>" />
                        <input type="hidden" name="address_street_name" value="<?= $_POST['params']['streetName']; ?>" />
                    <?php else : ?>
                        <input type="hidden" name="address_street_id" value="<?= $orderAddress->address_street_id; ?>" />
                        <input type="hidden" name="address_street_type" value="<?= $orderAddress->address_street_type; ?>" />
                        <input type="hidden" name="address_street_name" value="<?= $orderAddress->address_street_name; ?>" />
                    <?php endif; ?>
                    <label><?php _e('Street', 'dpdro'); ?></label>
                    <?php $ajaxNonceSearchStreet = wp_create_nonce('dpdro_search_street'); ?>
                    <select data-nonce="<?= $ajaxNonceSearchStreet; ?>" class="js-d-search-street">
                        <?php if ($_POST['params'] && !empty($_POST['params']) && $_POST['params']['streetName'] && !empty($_POST['params']['streetName'])) : ?>
                            <option value="<?= $_POST['params']['streetId']; ?>" selected><?= $_POST['params']['streetType'] . ' ' . $_POST['params']['streetName']; ?></option>
                        <?php else : ?>
                            <?php if ($orderAddress->address_street_name && !empty($orderAddress->address_street_name)) : ?>
                                <option value="<?= $orderAddress->address_street_id; ?>" selected><?= $orderAddress->address_street_type . ' ' . $orderAddress->address_street_name; ?></option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                    <script type="text/javascript">
                        // Street search 
                        $ = jQuery;
                        $('.js-d-search-street').select2({
                            minimumInputLength: 3,
                            placeholder: dpdRoGeneral.placeholderSearch,
                            ajax: {
                                url: dpdRo.ajaxurl,
                                type: 'post',
                                dataType: 'json',
                                delay: 250,
                                data: function(params) {
                                    return {
                                        action: 'searchStreet',
                                        nonce: $(this).attr('data-nonce'),
                                        country: $('.js-d-modal-validate').find('input[name="country"]').val(),
                                        cityId: $('.js-d-modal-validate').find('input[name="address_city_id"]').val(),
                                        search: params.term,
                                    };
                                },
                                processResults: function(response) {
                                    return {
                                        results: response
                                    };
                                },
                                cache: true
                            },
                            templateResult: function(params) {
                                if (!params.id) {
                                    return params.text;
                                }
                                var response = $("<span>" + params.type + " " + params.name + "</span>");
                                return response;
                            },
                            templateSelection: function(params) {
                                if (!params.id) {
                                    return params.text;
                                }
                                $('.js-d-modal-validate').find('input[name="address_street_id"]').val(params.id);
                                if (params.type) {
                                    $('.js-d-modal-validate').find('input[name="address_street_type"]').val(params.type);
                                }
                                if (params.name) {
                                    $('.js-d-modal-validate').find('input[name="address_street_name"]').val(params.name);
                                }
                                $('.js-d-save-address').attr('disabled', '').prop('disabled', false);
                                return params.text;
                            },
                        });
                    </script>
                </div>
            </div>
            <div class="d-full">
                <div class="d-fourth">
                    <div class="d-field">
                        <label><?php _e('Number', 'dpdro'); ?></label>
                        <input type="text" name="address_number" value="<?= $orderAddress->address_number; ?>" />
                    </div>
                </div>
                <div class="d-fourth">
                    <div class="d-field">
                        <label><?php _e('Block', 'dpdro'); ?></label>
                        <input type="text" name="address_block" value="<?= $orderAddress->address_block; ?>" />
                    </div>
                </div>
                <div class="d-fourth">
                    <div class="d-field">
                        <label><?php _e('Apartment', 'dpdro'); ?></label>
                        <input type="text" name="address_apartment" value="<?= $orderAddress->address_apartment; ?>" />
                    </div>
                </div>
                <div class="d-fourth">
                    <div class="d-field">
                        <label><?php _e('Postcode', 'dpdro'); ?></label>
                        <?php if ($_POST['params'] && !empty($_POST['params']) && $_POST['params']['postcode'] && !empty($_POST['params']['postcode'])) : ?>
                            <input type="text" name="address_postcode" value="<?= $_POST['params']['postcode']; ?>" />
                        <?php else : ?>
                            <input type="text" name="address_postcode" value="<?= $order->get_shipping_postcode(); ?>" />
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($settings['show_office_selection']) : ?>
            <div data-content="pickup" class="d-modal-nav-content js-d-modal-nav-content <?= $modalPickup; ?>">
                <div class="d-full">
                    <div class="d-field">
                        <label><?php _e('Pickup from office', 'dpdro'); ?></label>
                        <input type="hidden" name="office_id" value="<?= $orderAddress->office_id; ?>" />
                        <input type="hidden" name="office_type" value="" />
                        <input type="hidden" name="address" value="<?= $orderAddress->address; ?>" />
                        <input type="text" name="office_name" value="<?= $orderAddress->office_name; ?>" disabled />
                    </div>
                </div>
                <iframe id="frameOfficeLocator" name="frameOfficeLocator" src="https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&selectOfficeButtonCaption=Select this office" width="800px" height="300px"></iframe>
            </div>
        <?php else : ?>
            <input type="hidden" name="address" value="<?= $orderAddress->address; ?>" />
            <input type="hidden" name="office_id" value="<?= $orderAddress->office_id; ?>" />
            <input type="hidden" name="office_name" value="<?= $orderAddress->office_name; ?>" />
            <input type="hidden" name="office_type" value="" />
        <?php endif; ?>
    </div>
</div>

<!-- Modal footer. -->
<div class="d-modal-foot">
    <?php $ajaxNonceBoBack = wp_create_nonce('dpdro_create_shipment'); ?>
    <?php $ajaxNonceSaveAddress = wp_create_nonce('dpdro_save_address'); ?>
    <button data-nonce="<?= $ajaxNonceBoBack; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button secondary js-d-go-back">
        <?php _e('Go back to create shipment', 'dpdro'); ?>
    </button>
    <button data-nonce="<?= $ajaxNonceSaveAddress; ?>" data-order-id="<?= $order->id; ?>" type="button" disabled class="d-button primary js-d-save-address">
        <?php _e('Save address', 'dpdro'); ?>
    </button>
</div>