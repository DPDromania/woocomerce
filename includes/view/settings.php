<?php

/**
 * Global database.
 */
global $wpdb;


/** 
 * Data settings
 */
$settings = new DataSettings($wpdb);
$dataSettings = $settings->getSettings();

/** 
 * Library API
 */
$dpdApi = new LibraryApi($dataSettings['username'], $dataSettings['password']);
if ($dataSettings['authenticated'] != '') {
    $authenticated = $dataSettings['authenticated'] == '1' ? true : false;
} else {
    $authenticated = $dpdApi->check();
}
if ($authenticated) {

    /** 
     * Data Lists
     */
    $lists = new DataLists($wpdb);
    $listServices = $lists->getServices();
    $listClients = $lists->getClients();
    $listOffices = $lists->getOffices();
    $listOfficesGroups = $lists->getOfficesGroups();
    $listTaxRates = $lists->getTaxRates();
    $listTaxRatesOffices = $lists->getTaxRatesOffices();

    /** 
     * Data zones
     */
    $listZones = DataZones::zones();

    /** 
     * Library nomenclature
     */
    $nomenclature = new LibraryNomenclature();
    $nomenclaturePayerCourier = $nomenclature->PayerCourier();
    $nomenclaturePrintFormat = $nomenclature->PrintFormat();
    $nomenclaturePrintPaperSize = $nomenclature->PrintPaperSize();
    $nomenclatureOptionsDelivery = $nomenclature->OptionsDelivery();
    $nomenclatureOptionsDeliveryCourier = $nomenclature->OptionsDeliveryCourier();
    $nomenclatureOptionsPackages = $nomenclature->OptionsPackages();
    $nomenclatureOptionsContents = $nomenclature->OptionsContents();
}

/** 
 * Ajax nonce
 */
$ajaxNonceUpdateConnection = wp_create_nonce('dpdro_update_connection');
$ajaxNonceSaveConnection = wp_create_nonce('dpdro_save_connection');
$ajaxNonceSaveSettings = wp_create_nonce('dpdro_save_settings');
$ajaxNonceSaveTaxRate = wp_create_nonce('dpdro_save_tax_rate');
$ajaxNonceSaveTaxRatesOffices = wp_create_nonce('dpdro_save_tax_rates_offices');
$ajaxNonceAdvanceSettings = wp_create_nonce('dpdro_advance_settings');
$ajaxNoncePaymentSettings = wp_create_nonce('dpdro_payment_settings');
global $wp_version;

?>
<div class="dpdro js-dpdro">
    <div class="d-box-nav">
        <?php if ($authenticated) : ?>
            <button type="button" class="active d-button dpd js-d-box-nav" data-content="settings"><?php _e('Settings', 'dpdro'); ?></button>
            <button type="button" class="d-button dpd js-d-box-nav" data-content="address"><?php _e('Tax rates address', 'dpdro'); ?></button>
            <button type="button" class="d-button dpd js-d-box-nav" data-content="dpdBox"><?php _e('Tax rates DPD RO box', 'dpdro'); ?></button>
            <button type="button" class="d-button dpd js-d-box-nav" data-content="advance"><?php _e('Advanced settings', 'dpdro'); ?></button>
            <button type="button" class="d-button dpd js-d-box-nav" data-content="payment"><?php _e('Payment settings', 'dpdro'); ?></button>
        <?php endif; ?>
    </div>
    <div class="dpd_version">Plugin version: <?= DPDRO_VERSION; ?>  &nbsp; Wordpress version: <?= $wp_version; ?> &nbsp; Woocomerce version: <?= WC_VERSION; ?>  &nbsp; PHP version: <?= phpversion(); ?></div>
    <div class="d-message js-d-message"></div>
    <div data-content="settings" class="active d-box js-d-box-content">
        <h2 id="settings-auth" class="d-title"><?php _e('Authentication', 'dpdro'); ?></h2>
        <div class="d-content js-d-content">
            <div class="d-contentbox">
                <div class="d-settings">
                    <div class="d-field">
                        <label for="d-setting-username"><?php _e('Username', 'dpdro'); ?>:</label>
                        <input class="js-d-setting" id="d-setting-username" type="text" name="username" value="<?= $dataSettings['username']; ?>" />
                    </div>
                    <div class="d-field">
                        <label for="d-setting-password"><?php _e('Password', 'dpdro'); ?>:</label>
                        <input class="js-d-setting" id="d-setting-password" type="text" name="password" value="<?= $dataSettings['password']; ?>" />
                    </div>
                </div>
                <hr>
                <?php if ($authenticated) : ?>
                    <button data-nonce="<?= $ajaxNonceUpdateConnection; ?>" class="d-button primary alignment js-d-update-connection" type="button">
                        <?php _e('Update connection', 'dpdro'); ?>
                        <span class="dashicons dashicons-update"></span>
                    </button>
                <?php else : ?>
                    <button data-nonce="<?= $ajaxNonceSaveConnection; ?>" class="d-button primary alignment js-d-save-connection" type="button">
                        <?php _e('Connect', 'dpdro'); ?>
                        <span class="dashicons dashicons-admin-plugins"></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($authenticated) : ?>
            <h2 id="settings-packaging-method" class="d-title"><?php _e('Packaging Method', 'dpdro'); ?></h2>
            <div class="d-content js-d-content">
                <div class="d-contentbox">
                    <div class="d-settings">
                        <div class="d-field">
                            <label for="d-setting-packages"><?php _e('Package', 'dpdro'); ?>:</label>
                            <input list="d-setting-packages-list" class="js-d-setting" id="d-setting-packages" type="text" name="packages" value="<?= $dataSettings['packages']; ?>" />
                            <datalist id="d-setting-packages-list">
                                <?php foreach ($nomenclatureOptionsPackages as $packages) : ?>
                                    <option value="<?= $packages; ?>"><?= $packages; ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="d-field">
                            <label for="d-setting-contents"><?php _e('Content', 'dpdro'); ?>:</label>
                            <input list="d-setting-contents-list" class="js-d-setting" id="d-setting-contents" type="text" name="contents" value="<?= $dataSettings['contents']; ?>" />
                            <datalist id="d-setting-contents-list">
                                <?php foreach ($nomenclatureOptionsContents as $contents) : ?>
                                    <option value="<?= $contents; ?>"><?= $contents; ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-packaging-method"><?php _e('Packaging method', 'dpdro'); ?>:</label>
                            <div class="d-field-list">
                                <ul>
                                    <?php if ($dataSettings['packaging_method'] == 'one') : ?>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-packaging-method-one" type="radio" name="packaging_method" value="one" checked />
                                            <label for="d-setting-packaging-method-one"><?php _e('One parcel for all product', 'dpdro'); ?></label>
                                        </li>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-packaging-method-all" type="radio" name="packaging_method" value="all" />
                                            <label for="d-setting-packaging-method-all"><?php _e('One parcel for one product', 'dpdro'); ?></label>
                                        </li>
                                    <?php else : ?>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-packaging-method-one" type="radio" name="packaging_method" value="one" />
                                            <label for="d-setting-packaging-method-one"><?php _e('One parcel for all product', 'dpdro'); ?></label>
                                        </li>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-packaging-method-all" type="radio" name="packaging_method" value="all" checked />
                                            <label for="d-setting-packaging-method-all"><?php _e('One parcel for one product', 'dpdro'); ?></label>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <button data-nonce="<?= $ajaxNonceSaveSettings; ?>" class="d-button success alignment js-d-save-settings" type="button">
                        <?php _e('Save settings', 'dpdro'); ?>
                        <span class="dashicons dashicons-saved"></span>
                    </button>
                </div>
            </div>
            <h2 id="settings-services" class="d-title"><?php _e('Services', 'dpdro'); ?></h2>
            <div class="d-content js-d-content">
                <div class="d-contentbox">
                    <div class="d-settings">
                        <div class="d-field">
                            <label for="d-setting-services"><?php _e('Services', 'dpdro'); ?>:</label>
                            <div class="d-field-list">
                                <ul>
                                    <?php $services = json_decode(str_replace("\\", "", $dataSettings['services'])); ?>
                                    <?php foreach ($listServices as $service) : ?>
                                        <li class="d-checkbox">
                                            <?php if (is_array($services) && in_array($service->service_id, $services)) : ?>
                                                <input class="js-d-setting js-d-setting-services" id="d-setting-service-<?= $service->service_id; ?>" type="checkbox" name="service_<?= $service->service_id; ?>" value="<?= $service->service_id; ?>" checked />
                                            <?php else : ?>
                                                <input class="js-d-setting js-d-setting-services" id="d-setting-service-<?= $service->service_id; ?>" type="checkbox" name="service_<?= $service->service_id; ?>" value="<?= $service->service_id; ?>" />
                                            <?php endif; ?>
                                            <label for="d-setting-service-<?= $service->service_id; ?>"><?= $service->service_name; ?></label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <button data-nonce="<?= $ajaxNonceSaveSettings; ?>" class="d-button success alignment js-d-save-settings" type="button">
                        <?php _e('Save settings', 'dpdro'); ?>
                        <span class="dashicons dashicons-saved"></span>
                    </button>
                </div>
            </div>
            <h2 id="settings-sender-payer" class="d-title"><?php _e('Sender & Payer', 'dpdro'); ?></h2>
            <div class="d-content js-d-content">
                <div class="d-contentbox">
                    <div class="d-settings">
                        <div class="d-field">
                            <label for="d-setting-client-contracts"><?php _e('Pickup / shipping address', 'dpdro'); ?>:</label>
                            <select class="js-d-setting" id="d-setting-client-contracts" name="client_contracts">
                                <option selected value="0"><?php _e(' --- Please Select --- ', 'dpdro'); ?></option>
                                <?php foreach ($listClients as $client) : ?>
                                    <?php if ($client->client_id == $dataSettings['client_contracts']) : ?>
                                        <option selected value="<?= $client->client_id; ?>"><?= $client->client_id . ' - ' . $client->client_name . ' - ' . $client->client_address; ?></option>
                                    <?php else : ?>
                                        <option value="<?= $client->client_id; ?>"><?= $client->client_id . ' - ' . $client->client_name . ' - ' . $client->client_address; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-field">
                            <label for="d-setting-office-locations"><?php _e('Personal delivery DPD RO Pickup', 'dpdro'); ?>:</label>
                            <select class="js-d-setting" id="d-setting-office-locations" name="office_locations">
                                <option selected value="0"><?php _e(' --- Please Select --- ', 'dpdro'); ?></option>
                                <?php foreach ($listOffices as $office) : ?>
                                    <?php if ($office->office_id == $dataSettings['office_locations']) : ?>
                                        <option selected value="<?= $office->office_id; ?>"><?= $office->office_id . ' - ' . $office->office_name . ' - ' . $office->office_address; ?></option>
                                    <?php else : ?>
                                        <option value="<?= $office->office_id; ?>"><?= $office->office_id . ' - ' . $office->office_name . ' - ' . $office->office_address; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-sender-payer-insurance"><?php _e('Send insurance value', 'dpdro'); ?>:</label>
                            <div class="d-field-list">
                                <ul>
                                    <?php if ($dataSettings['sender_payer_insurance'] == '1') : ?>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-sender-payer-insurance-yes" type="radio" name="sender_payer_insurance" value="1" checked />
                                            <label for="d-setting-sender-payer-insurance-yes"><?php _e('Yes', 'dpdro'); ?></label>
                                        </li>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-sender-payer-insurance-no" type="radio" name="sender_payer_insurance" value="0" />
                                            <label for="d-setting-sender-payer-insurance-no"><?php _e('No', 'dpdro'); ?></label>
                                        </li>
                                    <?php else : ?>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-sender-payer-insurance-yes" type="radio" name="sender_payer_insurance" value="1" />
                                            <label for="d-setting-sender-payer-insurance-yes"><?php _e('Yes', 'dpdro'); ?></label>
                                        </li>
                                        <li>
                                            <input class="js-d-setting" id="d-setting-sender-payer-insurance-no" type="radio" name="sender_payer_insurance" value="0" checked />
                                            <label for="d-setting-sender-payer-insurance-no"><?php _e('No', 'dpdro'); ?></label>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                <small><?php _e('Select <b>"Yes"</b> if you want to send the insurance value when creating the shipment', 'dpdro'); ?></small>
                            </div>
                        </div>
                        <hr>
                        <input class="js-d-setting" type="hidden" name="include_shipping_price" value="0">
                        <div class="d-field">
                            <label for="d-setting-courier-service-payer"><?php _e('Courier service payer', 'dpdro'); ?>:</label>
                            <select class="js-d-setting" id="d-setting-courier-service-payer" name="courier_service_payer">
                                <?php foreach ($nomenclaturePayerCourier as $value => $name) : ?>
                                    <?php if ($value == $dataSettings['courier_service_payer']) : ?>
                                        <option selected value="<?= $value; ?>"><?= $name; ?></option>
                                    <?php else : ?>
                                        <option value="<?= $value; ?>"><?= $name; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-field js-d-field-id-payer-contract <?= $dataSettings['courier_service_payer'] == 'THIRD_PARTY' ? '' : 'hidden' ?>">
                            <label for="d-setting-id-payer-contract"><?php _e('ID payer contract', 'dpdro'); ?>:</label>
                            <input class="js-d-setting" id="d-setting-id-payer-contract" type="text" name="id_payer_contract" value="<?= $dataSettings['id_payer_contract']; ?>" />
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-print-format"><?php _e('Print format', 'dpdro'); ?>:</label>
                            <select class="js-d-setting" id="d-setting-print-format" name="print_format">
                                <?php foreach ($nomenclaturePrintFormat as $value => $name) : ?>
                                    <?php if ($value == $dataSettings['print_format']) : ?>
                                        <option selected value="<?= $value; ?>"><?= $name; ?></option>
                                    <?php else : ?>
                                        <option value="<?= $value; ?>"><?= $name; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-field">
                            <label for="d-setting-print-paper-size"><?php _e('Print paper size', 'dpdro'); ?>:</label>
                            <select class="js-d-setting" id="d-setting-print-paper-size" name="print_paper_size">
                                <?php foreach ($nomenclaturePrintPaperSize as $value => $name) : ?>
                                    <?php if ($value == $dataSettings['print_paper_size']) : ?>
                                        <option selected value="<?= $value; ?>"><?= $name; ?></option>
                                    <?php else : ?>
                                        <option value="<?= $value; ?>"><?= $name; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-test-or-open"><?php _e('Options on Payment / Delivery', 'dpdro'); ?>:</label>
                            <select class="js-d-setting" id="d-setting-test-or-open" name="test_or_open">
                                <option selected value="0"><?php _e(' --- Please Select --- ', 'dpdro'); ?></option>
                                <?php foreach ($nomenclatureOptionsDelivery as $optionKey => $optionValue) : ?>
                                    <?php if ($optionKey == $dataSettings['test_or_open']) : ?>
                                        <option selected value="<?= $optionKey; ?>"><?= $optionValue; ?></option>
                                    <?php else : ?>
                                        <option value="<?= $optionKey; ?>"><?= $optionValue; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-field">
                            <label for="d-setting-test-or-open-courier"><?php _e('Return shipment payer', 'dpdro'); ?>:</label>
                            <select class="js-d-setting" id="d-setting-test-or-open-courier" name="test_or_open_courier">
                                <?php foreach ($nomenclatureOptionsDeliveryCourier as $optionKeyCourier => $optionValueCourier) : ?>
                                    <?php if ($optionKeyCourier == $dataSettings['test_or_open_courier']) : ?>
                                        <option selected value="<?= $optionKeyCourier; ?>"><?= $optionValueCourier; ?></option>
                                    <?php else : ?>
                                        <option value="<?= $optionKeyCourier; ?>"><?= $optionValueCourier; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <button data-nonce="<?= $ajaxNonceSaveSettings; ?>" class="d-button success alignment js-d-save-settings" type="button">
                        <?php _e('Save settings', 'dpdro'); ?>
                        <span class="dashicons dashicons-saved"></span>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($authenticated) : ?>
        <div data-content="address" class="d-box js-d-box-content">
            <h2 class="d-title"><?php _e('Tax rates address', 'dpdro'); ?></h2>
            <div class="d-content js-d-content">
                <div class="d-contentbox">
                    <div class="d-settings">
                        <table class="d-table js-d-taxrates">
                            <thead>
                                <tr>
                                    <th><?php _e('Service', 'dpdro'); ?></th>
                                    <th class="w-210"><?php _e('Zone', 'dpdro'); ?></th>
                                    <th class="w-210"><?php _e('Condition', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('From', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('Price / Quantity', 'dpdro'); ?></th>
                                    <th class="w-145"><?php _e('Calculation', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('Status', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('Action', 'dpdro'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="js-d-taxrates-list">
                                <?php if ($listTaxRates && !empty($listTaxRates)) : ?>
                                    <?php foreach ($listTaxRates as $taxRate) : ?>
                                        <tr class="js-d-taxrate">
                                            <td>
                                                <div class="d-field full">
                                                    <select name="service_id">
                                                        <?php foreach ($listServices as $key => $service) : ?>
                                                            <?php if ($service->service_id == $taxRate->service_id) : ?>
                                                                <option selected value="<?= $service->service_id; ?>"><?= $service->service_name; ?></option>
                                                            <?php else : ?>
                                                                <option value="<?= $service->service_id; ?>"><?= $service->service_name; ?></option>
                                                            <?php endif ?>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-210">
                                                <div class="d-field full">
                                                    <select name="zone_id">
                                                        <?php foreach ($listZones as $key => $zone) : ?>
                                                            <?php if ($key == $taxRate->zone_id) : ?>
                                                                <option selected value="<?= $key; ?>"><?= $zone; ?></option>
                                                            <?php else : ?>
                                                                <option value="<?= $key; ?>"><?= $zone; ?></option>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-210">
                                                <div class="d-field full">
                                                    <select name="based_on">
                                                        <?php if ($taxRate->based_on == '1') : ?>
                                                            <option selected value="1"><?php _e('Based on price', 'dpdro'); ?></option>
                                                            <option value="0"><?php _e('Based on weight', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="1"><?php _e('Based on price', 'dpdro'); ?></option>
                                                            <option selected value="0"><?php _e('Based on weight', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <div class="d-field full">
                                                    <input class="js-d-setting-float" type="text" name="apply_over" value="<?= $taxRate->apply_over; ?>" />
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <div class="d-field full">
                                                    <input class="js-d-setting-float" type="text" name="tax_rate" value="<?= $taxRate->tax_rate; ?>" />
                                                </div>
                                            </td>
                                            <td class="w-145">
                                                <div class="d-field full">
                                                    <select name="calculation_type">
                                                        <?php if ($taxRate->calculation_type == '1') : ?>
                                                            <option selected value="1"><?php _e('Fixed', 'dpdro'); ?></option>
                                                            <option value="0"><?php _e('Percentage', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="1"><?php _e('Fixed', 'dpdro'); ?></option>
                                                            <option selected value="0"><?php _e('Percentage', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <div class="d-field full">
                                                    <select name="status">
                                                        <?php if ($taxRate->status == '1') : ?>
                                                            <option selected value="1"><?php _e('Enabled', 'dpdro'); ?></option>
                                                            <option value="0"><?php _e('Disabled', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="1"><?php _e('Enabled', 'dpdro'); ?></option>
                                                            <option selected value="0"><?php _e('Disabled', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <button type="button" class="d-button danger js-d-taxrate-remove">
                                                    <?php _e('Remove', 'dpdro'); ?>
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr class="js-d-taxrate-empty">
                                        <td colspan="8">
                                            <p class="d-table-empty"><?php _e('No tax rate found.', 'dpdro'); ?></p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="8">
                                        <button type="button" class="d-button primary js-d-taxrate-add">
                                            <?php _e('Add new tax rate', 'dpdro'); ?>
                                            <span class="dashicons dashicons-plus-alt"></span>
                                        </button>
                                        <button data-nonce="<?= $ajaxNonceSaveTaxRate; ?>" type="button" class="d-button success js-d-taxrate-save">
                                            <?php _e('Save tax rates', 'dpdro'); ?>
                                            <span class="dashicons dashicons-saved"></span>
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div data-content="dpdBox" class="d-box js-d-box-content">
            <h2 class="d-title"><?php _e('Tax rates DPD RO box', 'dpdro'); ?></h2>
            <div class="d-content js-d-content">
                <div class="d-contentbox">
                    <div class="d-settings">
                        <table class="d-table js-d-taxrates-offices">
                            <thead>
                                <tr>
                                    <th><?php _e('Service', 'dpdro'); ?></th>
                                    <th class="w-210"><?php _e('Offices Zone', 'dpdro'); ?></th>
                                    <th class="w-210"><?php _e('Condition', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('From', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('Price / Quantity', 'dpdro'); ?></th>
                                    <th class="w-145"><?php _e('Calculation', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('Status', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('Action', 'dpdro'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="js-d-taxrates-offices-list">
                                <?php if ($listTaxRatesOffices && !empty($listTaxRatesOffices)) : ?>
                                    <?php foreach ($listTaxRatesOffices as $taxRateOffices) : ?>
                                        <tr class="js-d-taxrate-offices">
                                            <td>
                                                <div class="d-field full">
                                                    <select name="service_id">
                                                        <?php foreach ($listServices as $key => $service) : ?>
                                                            <?php if ($service->service_id == $taxRateOffices->service_id) : ?>
                                                                <option selected value="<?= $service->service_id; ?>"><?= $service->service_name; ?></option>
                                                            <?php else : ?>
                                                                <option value="<?= $service->service_id; ?>"><?= $service->service_name; ?></option>
                                                            <?php endif ?>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-210">
                                                <div class="d-field full">
                                                    <select name="zone_id">
                                                        <?php foreach ($listOfficesGroups as $key => $zone) : ?>
                                                            <?php if ($key == $taxRateOffices->zone_id) : ?>
                                                                <option selected value="<?= $zone->office_site_id; ?>"><?= $zone->office_site_type != '' ? $zone->office_site_type . ' ' . $zone->office_site_name : $zone->office_site_name; ?></option>
                                                            <?php else : ?>
                                                                <option value="<?= $zone->office_site_id; ?>"><?= $zone->office_site_type != '' ? $zone->office_site_type . ' ' . $zone->office_site_name : $zone->office_site_name; ?></option>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-210">
                                                <div class="d-field full">
                                                    <select name="based_on">
                                                        <?php if ($taxRateOffices->based_on == '1') : ?>
                                                            <option selected value="1"><?php _e('Based on price', 'dpdro'); ?></option>
                                                            <option value="0"><?php _e('Based on weight', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="1"><?php _e('Based on price', 'dpdro'); ?></option>
                                                            <option selected value="0"><?php _e('Based on weight', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <div class="d-field full">
                                                    <input class="js-d-setting-float" type="text" name="apply_over" value="<?= $taxRateOffices->apply_over; ?>" />
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <div class="d-field full">
                                                    <input class="js-d-setting-float" type="text" name="tax_rate" value="<?= $taxRateOffices->tax_rate; ?>" />
                                                </div>
                                            </td>
                                            <td class="w-145">
                                                <div class="d-field full">
                                                    <select name="calculation_type">
                                                        <?php if ($taxRateOffices->calculation_type == '1') : ?>
                                                            <option selected value="1"><?php _e('Fixed', 'dpdro'); ?></option>
                                                            <option value="0"><?php _e('Percentage', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="1"><?php _e('Fixed', 'dpdro'); ?></option>
                                                            <option selected value="0"><?php _e('Percentage', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <div class="d-field full">
                                                    <select name="status">
                                                        <?php if ($taxRateOffices->status == '1') : ?>
                                                            <option selected value="1"><?php _e('Enabled', 'dpdro'); ?></option>
                                                            <option value="0"><?php _e('Disabled', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="1"><?php _e('Enabled', 'dpdro'); ?></option>
                                                            <option selected value="0"><?php _e('Disabled', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-130">
                                                <button type="button" class="d-button danger js-d-taxrate-offices-remove">
                                                    <?php _e('Remove', 'dpdro'); ?>
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr class="js-d-taxrate-offices-empty">
                                        <td colspan="8">
                                            <p class="d-table-empty"><?php _e('No tax rate found.', 'dpdro'); ?></p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="8">
                                        <button type="button" class="d-button primary js-d-taxrate-offices-add">
                                            <?php _e('Add new tax rate', 'dpdro'); ?>
                                            <span class="dashicons dashicons-plus-alt"></span>
                                        </button>
                                        <button data-nonce="<?= $ajaxNonceSaveTaxRatesOffices; ?>" type="button" class="d-button success js-d-taxrate-offices-save">
                                            <?php _e('Save tax rates', 'dpdro'); ?>
                                            <span class="dashicons dashicons-saved"></span>
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div data-content="advance" class="d-box js-d-box-content">
            <h2 class="d-title"><?php _e('Advance settings', 'dpdro'); ?></h2>
            <div class="d-content js-d-content">
                <div class="d-contentbox">
                    <div class="d-settings">
                        <div class="d-field">
                            <label for="d-setting-advanced-max-weight"><?php _e('Max parcel weight (kg)', 'dpdro'); ?>:</label>
                            <input class="js-d-setting js-d-setting-float" id="d-setting-advanced-max-weight" type="text" name="max_weight" value="<?= $dataSettings['max_weight']; ?>" />
                            <small><?php _e('Default value is <b>"31.5"</b> - copy in the field above if use default.', 'dpdro'); ?></small>
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-advanced-max-weight-automat"><?php _e('Max parcel weight automat (kg)', 'dpdro'); ?>:</label>
                            <input class="js-d-setting js-d-setting-float" id="d-setting-advanced-max-weight-automat" type="text" name="max_weight_automat" value="<?= $dataSettings['max_weight_automat']; ?>" />
                            <small><?php _e('Default value is <b>"20"</b> - copy in the field above if use default.', 'dpdro'); ?></small>
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-advanced-show-office-selection"><?php _e('Show DPD OOH network selection', 'dpdro'); ?>:</label>
                            <div class="d-field-list">
                                <ul>
                                    <li class="d-checkbox">
                                        <?php if ($dataSettings['show_office_selection'] == '1') : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-show-office-selection" type="checkbox" name="show_office_selection" value="1" checked />
                                        <?php else : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-show-office-selection" type="checkbox" name="show_office_selection" value="1" />
                                        <?php endif ?>
                                        <label for="d-setting-advanced-show-office-selection"><?php _e('If it is checked, customer can select "Pickup / shipping address".', 'dpdro') ?></label>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-advanced-use-default-weight"><?php _e('Use default weight', 'dpdro'); ?>:</label>
                            <div class="d-field-list">
                                <ul>
                                    <li class="d-checkbox">
                                        <?php if ($dataSettings['use_default_weight'] == '1') : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-use-default-weight" type="checkbox" name="use_default_weight" value="1" checked />
                                        <?php else : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-use-default-weight" type="checkbox" name="use_default_weight" value="1" />
                                        <?php endif ?>
                                        <label for="d-setting-advanced-use-default-weight"><?php _e('If it checked, will use default weight if product doesn\'t have weight.', 'dpdro') ?></label>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="d-field">
                            <label for="d-setting-advanced-default-weight"><?php _e('Default weight (kg)', 'dpdro'); ?>:</label>
                            <input class="js-d-setting js-d-setting-float" id="d-setting-advanced-default-weight" type="text" name="default_weight" value="<?= $dataSettings['default_weight']; ?>" />
                            <small><?php _e('Default value is <b>"1"</b> - copy in the field above if use default.', 'dpdro'); ?></small>
                        </div>
                        <hr>
                        <div class="d-field">
                            <label for="d-setting-advanced-county-before-city"><?php _e('Change Woocommerce field county position', 'dpdro'); ?>:</label>
                            <div class="d-field-list">
                                <ul>
                                    <li class="d-checkbox">
                                        <?php if ($dataSettings['county_before_city'] == '1') : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-county-before-city" type="checkbox" name="county_before_city" value="1" checked />
                                        <?php else : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-county-before-city" type="checkbox" name="county_before_city" value="1" />
                                        <?php endif ?>
                                        <label for="d-setting-advanced-county-before-city"><?php _e('If it is checked, the county field will be before city field.', 'dpdro') ?></label>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="d-field">
                            <label for="d-setting-advanced-city-select"><?php _e('Use city dropdown', 'dpdro'); ?>:</label>
                            <div class="d-field-list">
                                <ul>
                                    <li class="d-checkbox">
                                        <?php if ($dataSettings['city_dropdown'] == '1') : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-city-select" type="checkbox" name="city_dropdown" value="1" checked />
                                        <?php else : ?>
                                            <input class="js-d-setting" id="d-setting-advanced-city-select" type="checkbox" name="city_dropdown" value="1" />
                                        <?php endif ?>
                                        <label for="d-setting-advanced-city-select"><?php _e('If it is checked, a dropdown will be shown for city instead of an input type text.', 'dpdro') ?></label>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <button data-nonce="<?= $ajaxNonceAdvanceSettings; ?>" class="d-button success alignment js-d-save-advance-settings" type="button">
                        <?php _e('Save advanced settings', 'dpdro'); ?>
                        <span class="dashicons dashicons-saved"></span>
                    </button>
                </div>
            </div>
        </div>
        <div data-content="payment" class="d-box js-d-box-content">
            <h2 class="d-title"><?php _e('Payment settings', 'dpdro'); ?></h2>
            <div class="d-content js-d-content">
                <div class="d-contentbox">
                    <div class="d-settings">
                        <div class="d-field">
                            <label for="d-setting-advanced-payment-tax"><?php _e('DPD RO contract payment tax', 'dpdro'); ?>:</label>
                            <input disabled class="js-d-setting js-d-setting-float" id="d-setting-advanced-payment-tax" type="text" name="payment_tax" value="<?= $dataSettings['payment_tax']; ?>" />
                            <small>
                                <?php _e('This tax comes from your DPD RO contract.', 'dpdro'); ?>
                                <b><?php _e('If you are not agree with this tax you can add your own tax below.', 'dpdro'); ?></b>
                            </small>
                        </div>
                        <hr>
                        <table class="d-table d-table-field">
                            <thead>
                                <tr>
                                    <th><?php _e('Geo zone', 'dpdro'); ?></th>
                                    <th class="w-130"><?php _e('Tax type', 'dpdro'); ?></th>
                                    <th class="w-190"><?php _e('Tax rate', 'dpdro'); ?></th>
                                    <th class="w-190"><?php _e('VAT rate in (%) like 20', 'dpdro'); ?></th>
                                    <th class="w-190"><?php _e('Action', 'dpdro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($listZones && !empty($listZones)) : ?>
                                    <?php foreach ($listZones as $key => $zone) : ?>
                                        <?php
                                        $paymentZones = json_decode(str_replace("\\", "", $dataSettings['payment_zones']));
                                        $paymentZoneData = (object) array(
                                            'id'       => $key,
                                            'name'     => $zone,
                                            'type'     => 'dpdro',
                                            'tax_rate' => '0.00',
                                            'vat_rate' => '0.00',
                                            'status'   => '0',
                                        );
                                        if ($paymentZones && !empty($paymentZones) && is_array($paymentZones)) {
                                            foreach ($paymentZones as $paymentZone) {
                                                if ($paymentZone->id == $key) {
                                                    $paymentZoneData = $paymentZone;
                                                }
                                            }
                                        }
                                        ?>
                                        <tr class="js-payment-zone">
                                            <td>
                                                <input class="js-d-setting" type="hidden" name="payment_id" value="<?= $paymentZoneData->id; ?>">
                                                <input class="js-d-setting" type="hidden" name="payment_name" value="<?= $paymentZoneData->name; ?>">
                                                <p><?= $paymentZoneData->name; ?></p>
                                            </td>
                                            <td class="w-190">
                                                <div class="d-field full">
                                                    <select class="js-d-setting" name="payment_type">
                                                        <?php if ($paymentZoneData->type == 'custom') : ?>
                                                            <option selected value="custom"><?php _e('Custom', 'dpdro'); ?></option>
                                                            <option value="dpdro"><?php _e('DPD RO contract', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="custom"><?php _e('Custom', 'dpdro'); ?></option>
                                                            <option selected value="dpdro"><?php _e('DPD RO contract', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="w-190">
                                                <div class="d-field full">
                                                    <input class="js-d-setting js-d-setting-float" type="text" name="payment_tax_rate" value="<?= $paymentZoneData->tax_rate; ?>">
                                                </div>
                                            </td>
                                            <td class="w-190">
                                                <div class="d-field full">
                                                    <input class="js-d-setting js-d-setting-float" type="text" name="payment_vat_rate" value="<?= $paymentZoneData->vat_rate; ?>">
                                                </div>
                                            </td>
                                            <td class="w-190">
                                                <div class="d-field full">
                                                    <select class="js-d-setting" name="payment_status">
                                                        <?php if ($paymentZoneData->status == '1') : ?>
                                                            <option selected value="1"><?php _e('Enabled', 'dpdro'); ?></option>
                                                            <option value="0"><?php _e('Disabled', 'dpdro'); ?></option>
                                                        <?php else : ?>
                                                            <option value="1"><?php _e('Enabled', 'dpdro'); ?></option>
                                                            <option selected value="0"><?php _e('Disabled', 'dpdro'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5">
                                            <p class="d-table-empty"><?php _e('No payments found', 'dpdro'); ?></p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <hr>
                        <button data-nonce="<?= $ajaxNoncePaymentSettings; ?>" class="d-button success js-d-save-payment-settings" type="button">
                            <?php _e('Save payment settings', 'dpdro'); ?>
                            <span class="dashicons dashicons-saved"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>