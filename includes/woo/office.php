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
            <input id="method-pickup" class="js-d-modal-nav" type="radio" name="method" value="pickup" checked />
            <label class="d-full" for="method-pickup"><?php _e('Pickup', 'dpdro'); ?></label>
        </div>
        <div data-content="pickup" class="d-modal-nav-content js-d-modal-nav-content active">
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
    </div>
</div>

<!-- Modal footer. -->
<div class="d-modal-foot">
    <?php $ajaxNonceBoBack = wp_create_nonce('dpdro_create_shipment'); ?>
    <?php $ajaxNonceSaveOffice = wp_create_nonce('dpdro_save_office'); ?>
    <button data-nonce="<?= $ajaxNonceBoBack; ?>" data-order-id="<?= $order->id; ?>" type="button" class="d-button secondary js-d-go-back">
        <?php _e('Go back to create shipment', 'dpdro'); ?>
    </button>
    <button data-nonce="<?= $ajaxNonceSaveOffice; ?>" data-order-id="<?= $order->id; ?>" type="button" disabled class="d-button primary js-d-save-office">
        <?php _e('Save office', 'dpdro'); ?>
    </button>
</div>