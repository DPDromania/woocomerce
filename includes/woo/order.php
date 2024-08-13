<?php

/**
 * Namespace: includes/woo.
 */

if (!defined('ABSPATH')) {
    exit;
}


require plugin_dir_path(__FILE__) . '../../library/dpdutil.php';

class WooOrder
{
    /**
     * Global database.
     */
    private $wpdb;

    /**
     * Tables name.
     */
    private $tableOrderAddresses;
    private $tableOrderSettings;
    private $tableOrderShipment;
    private $tableRequesteCourier;
    private $tableTaxRates;
    private $tableTaxRatesOffices;
    private $tableOtherMethod;

    /** 
     * Constructor.
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->tableOrderAddresses = $this->wpdb->prefix . 'order_dpd_address';
        $this->tableOrderSettings = $this->wpdb->prefix . 'order_dpd_settings';
        $this->tableOrderShipment = $this->wpdb->prefix . 'order_dpd_shipment';
        $this->tableRequesteCourier = $this->wpdb->prefix . 'order_dpd_courier';
        $this->tableTaxRates = $this->wpdb->prefix . 'order_dpd_tax_rates';
        $this->tableTaxRatesOffices = $this->wpdb->prefix . 'order_dpd_tax_rates_offices';
        $this->tableOtherMethod = $this->wpdb->prefix . 'order_dpd_other_method';
        $this->init();
    }

    /**
     * Init your settings.
     */
    function init()
    {
        add_action('woocommerce_checkout_update_order_review', array($this, 'orderReview'), 10, 1);
        add_action('woocommerce_thankyou', array($this, 'orderComplete'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'orderCompleteAdmin'), 10, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'orderActionsAdmin'));
        add_action('woocommerce_email_order_meta', array($this, 'orderActionEmail'), 10, 3);
        add_action('woocommerce_process_shop_order_meta', array($this, 'orderActionAdminUpdate'), 10, 2);

        /**
         * Order ajax.
         */
        add_action('wp_ajax_createShipment', array($this, 'createShipment'));
        add_action('wp_ajax_nopriv_createShipment', array($this, 'createShipment'));
        add_action('wp_ajax_saveShipment', array($this, 'saveShipment'));
        add_action('wp_ajax_nopriv_saveShipment', array($this, 'saveShipment'));
        add_action('wp_ajax_deleteShipment', array($this, 'deleteShipment'));
        add_action('wp_ajax_nopriv_deleteShipment', array($this, 'deleteShipment'));
        add_action('wp_ajax_skipValidationAddress', array($this, 'skipValidationAddress'));
        add_action('wp_ajax_nopriv_skipValidationAddress', array($this, 'skipValidationAddress'));
        add_action('wp_ajax_validationAddress', array($this, 'validationAddress'));
        add_action('wp_ajax_nopriv_validationAddress', array($this, 'validationAddress'));
        add_action('wp_ajax_searchStreet', array($this, 'searchStreet'));
        add_action('wp_ajax_nopriv_searchStreet', array($this, 'searchStreet'));
        add_action('wp_ajax_searchCity', array($this, 'searchCity'));
        add_action('wp_ajax_nopriv_searchCity', array($this, 'searchCity'));
        add_action('wp_ajax_validateAddress', array($this, 'validateAddress'));
        add_action('wp_ajax_nopriv_validateAddress', array($this, 'validateAddress'));
        add_action('wp_ajax_requestCourier', array($this, 'requestCourier'));
        add_action('wp_ajax_nopriv_requestCourier', array($this, 'requestCourier'));
        add_action('wp_ajax_pickedUp', array($this, 'pickedUp'));
        add_action('wp_ajax_nopriv_pickedUp', array($this, 'pickedUp'));
        add_action('wp_ajax_addAddress', array($this, 'addAddress'));
        add_action('wp_ajax_nopriv_addAddress', array($this, 'addAddress'));
        add_action('wp_ajax_saveAddress', array($this, 'saveAddress'));
        add_action('wp_ajax_nopriv_saveAddress', array($this, 'saveAddress'));
        add_action('wp_ajax_changeOffice', array($this, 'changeOffice'));
        add_action('wp_ajax_nopriv_changeOffice', array($this, 'changeOffice'));
        add_action('wp_ajax_saveOffice', array($this, 'saveOffice'));
        add_action('wp_ajax_nopriv_saveOffice', array($this, 'saveOffice'));
        add_action('wp_ajax_addShippingMethod', array($this, 'addShippingMethod'));
        add_action('wp_ajax_nopriv_addShippingMethod', array($this, 'addShippingMethod'));
        add_action('wp_ajax_saveShippingMethod', array($this, 'saveShippingMethod'));
        add_action('wp_ajax_nopriv_saveShippingMethod', array($this, 'saveShippingMethod'));

        /**
         * Order notice.
         */
        add_action('admin_notices', array($this, 'notice'));
    }

    /**
     * Order review.
     */
    public function orderReview($packages)
    {
        parse_str($packages, $output);
        if (isset($output['payment_method'])) {
            WC()->session->set('chosen_payment_method', $output['payment_method']);
        }
        $shippingPackages = WC()->cart->get_shipping_packages();
        foreach ($shippingPackages as $key => $package) {
            WC()->session->__unset('shipping_for_package_' . $key);
        }
        WC()->cart->calculate_shipping();
    }

    /**
     * Order complete.
     */
    public function orderComplete($orderId)
    {
        if (!$orderId) {
            return;
        }

        /**
         * Allow code execution only once 
         */
        if (!get_post_meta($orderId, 'dpdro_order_complete', true)) {

            /**
             * Get an instance of the WC_Order object. 
             */
            $order = wc_get_order($orderId);

            /**
             * Order shipping method.
             */
            $orderShippingMethod = false;
            $orderShippingMethods = $order->get_shipping_methods();
            foreach ($orderShippingMethods as $method) {
                $orderShippingMethod = str_replace('shipping_dpd_', '', $method->get_method_id());
                $orderShippingMethod = str_replace('dpdro_shipping_', '', $orderShippingMethod);
            }
            if ($orderShippingMethod) {

                /** 
                 * Data Lists.
                 */
                $lists = new DataLists($this->wpdb);
                $service = $lists->getServiceById($orderShippingMethod);
                if ($service && !empty($service)) {
                    if ($this->checkCountry($order->get_shipping_country(), true)) {

                        /** 
                         * Order data.
                         */
                        $shippingMethod = 'delivery';
                        $countryId = $order->get_shipping_country();
                        $stateName = $order->get_shipping_state();
                        $cityId = '';
                        $cityName = $order->get_shipping_city();
                        $postcode = $order->get_shipping_postcode();

                        /** 
                         * Data Addresses.
                         */
                        $addresses = new DataAddresses($this->wpdb);
                        $cityData = $addresses->getAddress($countryId, $stateName, $cityName);
                        if ($cityData && !empty($cityData)) {
                            $cityId = $cityData->site_id;
                            $cityName = $cityData->name;
                        } else {
                            $cityData = $addresses->getAddressByPostcode($postcode);
                            if ($cityData && !empty($cityData)) {
                                $cityId = $cityData->site_id;
                                $cityName = $cityData->name;
                            }
                        }

                        /** 
                         * Data Office.
                         */
                        $officeId = '';
                        $officeName = '';
                        $officePickup = get_post_meta($orderId, 'dpdro_pickup', true);
                        $officePickupName = get_post_meta($orderId, 'dpdro_pickup_name', true);
                        $officePickupType = get_post_meta($orderId, 'dpdro_pickup_type', true);
                        if ($officePickup && !empty($officePickup)) {
                            $shippingMethod = 'pickup';
                            $officeData = $lists->getOfficeById($officePickup);
                            if ($officeData && !empty($officeData)) {
                                $officeId = $officeData->office_id;
                                $officeName = $officeData->office_name;
                            } else {
	                            $officeId = $officePickup;
                            }
                        }

                        /** 
                         * Order data to insert in database.
                         */
                        $orderData = [
                            'order_id'            => $orderId,
                            'address'             => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
                            'address_city_id'     => (string) $cityId,
                            'address_city_name'   => (string) $cityName,
                            'address_street_id'   => '',
                            'address_street_type' => '',
                            'address_street_name' => '',
                            'address_number'      => '',
                            'address_block'       => '',
                            'address_apartment'   => '',
                            'method'              => (string) $shippingMethod,
                            'office_id'           => (string) $officeId,
                            'office_name'         => (string) $officeName,
                            'status'              => 'unset',
                        ];

                        /** 
                         * Insert order data to database.
                         */
	                    if (in_array($order->get_shipping_country(), DPDUtil::getAllowedCountryCodes(), true)) {
                            $this->insertOrderAddress($orderData);
                        }

                        /** 
                         * Insert order settings to database.
                         */
                        $orderSettings = [
                            'order_id'          => $orderData['order_id'],
                            'shipping_tax'      => WC()->session->get('dpdro_shipping_tax_' . $orderShippingMethod),
                            'shipping_tax_rate' => WC()->session->get('dpdro_shipping_tax_rate_' . $orderShippingMethod)
                        ];
                        $this->insertOrderSettings($orderSettings);

                        /** 
                         * Unset order data from session.
                         */
                        WC()->session->__unset('dpdro_office_id');
                        WC()->session->__unset('dpdro_office_name');
                        WC()->session->__unset('dpdro_shipping_tax_' . $orderShippingMethod);
                        WC()->session->__unset('dpdro_shipping_tax_rate_' . $orderShippingMethod);
                    }
                }
            }

            /**
             * Flag the action as done (to avoid repetitions on reload for example). 
             */
            $order->update_meta_data('dpdro_order_complete', true);
            $order->save();
        }
    }

    /**
     * Order complete admin.
     */
    public function orderCompleteAdmin($postId, $post)
    {
        /**
         * Get an instance of the WC_Order object
         */
        $order = wc_get_order($postId);

        /**
         * Order shipping method.
         */
        $orderShippingMethod = false;
        $orderShippingMethods = $order->get_shipping_methods();
        foreach ($orderShippingMethods as $method) {
            $orderShippingMethod = str_replace('shipping_dpd_', '', $method->get_method_id());
            $orderShippingMethod = str_replace('dpdro_shipping_', '', $orderShippingMethod);
        }
        if ($orderShippingMethod) {

            /** 
             * Data Lists.
             */
            $lists = new DataLists($this->wpdb);
            $service = $lists->getServiceById($orderShippingMethod);
            if ($service && !empty($service)) {
                $countryId = $order->get_shipping_country();
                if (!$countryId || empty($countryId)) {
                    $countryId = $_POST['_shipping_country'];
                }
                if ($this->checkCountry($countryId, true)) {

                    /** 
                     * Add only once.
                     * Here we check if this address and settings for this order have allready been stored.
                     */
                    if ($orderAddress = $this->getOrderAddress($order->get_id())) {
                        if ($orderAddress->address && !empty($orderAddress->address)) {
                            $this->deleteOrderAddress($order->get_id());
                            $this->deleteOrderSettings($order->get_id());
                        }
                    }

                    /** 
                     * Order data.
                     */
                    $shippingMethod = 'delivery';
                    $cityId = '';
                    $cityName = $order->get_shipping_city();
                    $stateName = $order->get_shipping_state();
                    $postcode = $order->get_shipping_postcode();

                    /** 
                     * Data Addresses.
                     */
                    $addresses = new DataAddresses($this->wpdb);
                    $cityData = $addresses->getAddress($countryId, $stateName, $cityName);
                    if ($cityData && !empty($cityData)) {
                        $cityId = $cityData->site_id;
                        $cityName = $cityData->name;
                    } else {
                        $cityData = $addresses->getAddressByPostcode($postcode);
                        if ($cityData && !empty($cityData)) {
                            $cityId = $cityData->site_id;
                            $cityName = $cityData->name;
                        }
                    }

                    /** 
                     * Data Office.
                     */
                    $officeId = '';
                    $officeName = '';
                    $officePickup = get_post_meta($postId, 'dpdro_pickup', true);
                    $officePickupName = get_post_meta($postId, 'dpdro_pickup_name', true);
                    $officePickupType = get_post_meta($postId, 'dpdro_pickup_type', true);
                    if ($officePickup && !empty($officePickup)) {
                        if ($orderAddress->method && $orderAddress->method == 'pickup') {
                            $shippingMethod = 'pickup';
                        }
                        $officeData = $lists->getOfficeById($officePickup);
                        if ($officeData && !empty($officeData)) {
                            $officeId = $officeData->office_id;
                            $officeName = $officeData->office_name;
                        }
                    }

                    /** 
                     * Order data to insert in database.
                     */
                    $orderData = [
                        'order_id'            => $postId,
                        'address'             => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
                        'address_city_id'     => (string) $cityId,
                        'address_city_name'   => (string) $cityName,
                        'address_street_id'   => '',
                        'address_street_type' => '',
                        'address_street_name' => '',
                        'address_number'      => '',
                        'address_block'       => '',
                        'address_apartment'   => '',
                        'method'              => (string) $shippingMethod,
                        'office_id'           => (string) $officeId,
                        'office_name'         => (string) $officeName,
                        'status'              => 'unset',
                    ];

                    /** 
                     * Insert order data to database.
                     */
	                if (in_array($order->get_shipping_country(), DPDUtil::getAllowedCountryCodes(), true)) {
                        $this->insertOrderAddress($orderData);
                    }

                    /** 
                     * Insert order settings to database.
                     */
                    $orderSettings = [
                        'order_id'          => $orderData['order_id'],
                        'shipping_tax'      => $order->get_shipping_total(),
                        'shipping_tax_rate' => 'yes'
                    ];
                    $this->insertOrderSettings($orderSettings);
                }
            }
        }
    }

    /**
     * Order DPD RO actions admin.
     */
    public function orderActionsAdmin($order)
    {
        /**
         * Order data.
         */
        $orderShippingMethod = false;
        $orderShippingMethods = $order->get_shipping_methods();
        foreach ($orderShippingMethods as $method) {
            $orderShippingMethod = str_replace('dpdro_shipping_', '', $method->get_method_id());
            $orderShippingMethod = str_replace('shipping_dpd_', '', $orderShippingMethod);
        }

        $orderShipment = $this->getOrderShipment($order->id);
        $orderShipping = $this->getOrderShipping($order->id);
        echo '
            <div class="dpdro js-dpdro">
                <div class="d-box">
                    <h3 class="d-title">' . __('DPD RO', 'dpdro') . '</h3>
                    <div class="d-content">
        ';

        /** 
         * Data Lists
         */
        $dataLists = new DataLists($this->wpdb);
        $orderShippingMethod = $dataLists->getServiceById($orderShippingMethod);
        if ($order->get_status() == 'completed') {
            if ($orderShipment && !empty($orderShipment)) {
                $orderRequestCourier = $this->getRequestCourier($orderShipment->shipment_id);
                if ($orderShippingMethod) {
                    if ($orderRequestCourier && !empty($orderRequestCourier)) {
                        $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                        echo '
                            <a href="' . $orderShipmentLabels . '" title="' . __('Print labels', 'dpdro') . '" type="button" class="d-button d-opacity icon d-mb warning">
                                <i class="dashicons dashicons-printer"></i>
                            </a>
                        ';
                        $orderShipmentReturns = json_decode($orderShipment->shipment_data);
                        if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                            $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher', 'dpdro') . '" class="d-button d-opacity icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                        }
                        echo '
                            <button style="cursor: help;" title="' . __('Order has been picked up', 'dpdro') . '" type="button" class="d-button d-opacity icon success">
                                <i class="dashicons dashicons-yes-alt"></i>
                            </button>
                        ';
                        echo '
                            <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $orderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button d-opacity icon info js-d-track-order">
                                <i class="dashicons dashicons-location"></i>
                            </a>
                        ';
                    } else {
                        $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                        echo '
                            <a href="' . $orderShipmentLabels . '" title="' . __('Print labels', 'dpdro') . '" type="button" class="d-button d-opacity icon d-mb warning">
                                <i class="dashicons dashicons-printer"></i>
                            </a>
                        ';
                        $orderShipmentReturns = json_decode($orderShipment->shipment_data);
                        if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                            $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher', 'dpdro') . '" class="d-button d-opacity icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                        }
                        echo '
                            <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $orderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button d-opacity icon info js-d-track-order">
                                <i class="dashicons dashicons-location"></i>
                            </a>
                        ';
                        echo '
                            <button style="cursor: help;" title="' . __('No pickup order', 'dpdro') . '" type="button" class="d-button d-opacity icon danger">
                                <i class="dashicons dashicons-warning"></i>
                            </button>
                        ';
                    }
                } else {
                    if ($orderShipment && !empty($orderShipment) && $orderShipping && !empty($orderShipping)) {
                        if ($orderRequestCourier && !empty($orderRequestCourier)) {
                            $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentLabels . '" title="' . __('Print labels for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                            $orderShipmentReturns = json_decode($orderShipment->shipment_data);
                            if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                                $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                                echo '
                                    <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon d-mb warning">
                                        <i class="dashicons dashicons-printer"></i>
                                    </a>
                                ';
                            }
                            echo '
                                <button style="cursor: help;" title="' . __('Order without DPD RO shipping method has been picked up', 'dpdro') . '" type="button" class="d-button icon success">
                                    <i class="dashicons dashicons-yes-alt"></i>
                                </button>
                            ';
                            echo '
                                <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $orderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button icon info js-d-track-order">
                                    <i class="dashicons dashicons-location"></i>
                                </a>
                            ';
                        } else {
                            $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                            echo '
                                <a href="' . $orderShipmentLabels . '" title="' . __('Print labels for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon d-mb warning">
                                    <i class="dashicons dashicons-printer"></i>
                                </a>
                            ';
                            $orderShipmentReturns = json_decode($orderShipment->shipment_data);
                            if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                                $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                                echo '
                                    <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon d-mb warning">
                                        <i class="dashicons dashicons-printer"></i>
                                    </a>
                                ';
                            }
                            echo '
                                <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $orderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button icon info js-d-track-order">
                                    <i class="dashicons dashicons-location"></i>
                                </a>
                            ';
                            echo '
                                <button style="cursor: help;" title="' . __('No pickup order', 'dpdro') . '" type="button" class="d-button icon danger">
                                    <i class="dashicons dashicons-warning"></i>
                                </button>
                            ';
                        }
                    }
                }
            } else {
                echo '
                    <button style="cursor: help;" title="' . __('No shipping carrier', 'dpdro') . '" type="button" class="d-button d-opacity icon danger">
                        <i class="dashicons dashicons-warning"></i>
                    </button>
                ';
            }
        } else {
            if ($orderShippingMethod) {
                if ($orderShipment && !empty($orderShipment)) {
                    $ajaxNonceDeleteShipment = wp_create_nonce('dpdro_delete_shipment');
                    $ajaxNonceRequestCourier = wp_create_nonce('dpdro_request_courier');
                    $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                    echo '
                        <button data-nonce="' . $ajaxNonceDeleteShipment . '" title="' . __('Delete shipment', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button d-opacity icon danger js-d-delete-shipment">
                            <i class="dashicons dashicons-trash"></i>
                        </button>
                        <a href="' . $orderShipmentLabels . '" title="' . __('Print labels', 'dpdro') . '" type="button" class="d-button d-opacity icon warning">
                            <i class="dashicons dashicons-printer"></i>
                        </a>
                    ';
                    $orderShipmentReturns = json_decode($orderShipment->shipment_data);
                    if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                        $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                        echo '
                            <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher', 'dpdro') . '" class="d-button d-opacity icon warning">
                                <i class="dashicons dashicons-printer"></i>
                            </a>
                        ';
                    }
                    $orderRequestCourier = $this->getRequestCourier($orderShipment->shipment_id);
                    if (!$orderRequestCourier || empty($orderRequestCourier)) {
                        echo '
                            <button data-nonce="' . $ajaxNonceRequestCourier . '" title="' . __('Request courier', 'dpdro') . '" data-shipment-id="' . $orderShipment->shipment_id  . '" type="button" class="d-button d-opacity icon primary js-d-request-courier">
                                <i class="dashicons dashicons-car"></i>
                            </button>
                        ';
                    }
                    echo '
                        <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $orderShipment->shipment_id . '" title="' . __('Track', 'dpdro') . '" class="d-button d-opacity icon info js-d-track-order">
                            <i class="dashicons dashicons-location"></i>
                        </a>
                    ';
                    if ($orderRequestCourier && !empty($orderRequestCourier)) {
                        echo '
                            <button style="cursor: help;" title="' . __('A courier has been requested for order', 'dpdro') . '" type="button" class="d-button d-opacity d-opacity icon success">
                                <i class="dashicons dashicons-info-outline"></i>
                            </button>
                        ';
                    }
                } else {
                    $ajaxNonceCreateShipment = wp_create_nonce('dpdro_create_shipment');
                    echo '
                        <button data-nonce="' . $ajaxNonceCreateShipment . '" title="' . __('Create shipment', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button d-opacity icon primary js-d-create-shipment">
                            <i class="dashicons dashicons-welcome-add-page"></i>
                        </button>
                    ';
                }
            } else {
                if ($orderShipment && !empty($orderShipment) && $orderShipping && !empty($orderShipping)) {
                    $ajaxNonceDeleteShipment = wp_create_nonce('dpdro_delete_shipment');
                    $ajaxNonceRequestCourier = wp_create_nonce('dpdro_request_courier');
                    $orderShipmentLabels = admin_url('admin.php/dpdro_print?print=labels&id=' . absint($order->id));
                    echo '
                        <button data-nonce="' . $ajaxNonceDeleteShipment . '" title="' . __('Delete shipment for order without DPD RO shipping method', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button icon danger js-d-delete-shipment">
                            <i class="dashicons dashicons-trash"></i>
                        </button>
                        <a href="' . $orderShipmentLabels . '" title="' . __('Print labels for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon warning">
                            <i class="dashicons dashicons-printer"></i>
                        </a>
                    ';
                    $orderShipmentReturns = json_decode($orderShipment->shipment_data);
                    if ($orderShipmentReturns && $orderShipmentReturns->shipment_has_voucher === 'true') {
                        $orderShipmentVoucher = admin_url('admin.php/dpdro_print?print=voucher&id=' . absint($order->id));
                        echo '
                            <a href="' . $orderShipmentVoucher . '" title="' . __('Print voucher for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon warning">
                                <i class="dashicons dashicons-printer"></i>
                            </a>
                        ';
                    }
                    $orderRequestCourier = $this->getRequestCourier($orderShipment->shipment_id);
                    if (!$orderRequestCourier || empty($orderRequestCourier)) {
                        echo '
                            <button data-nonce="' . $ajaxNonceRequestCourier . '" title="' . __('Request courier for order without DPD RO shipping method', 'dpdro') . '" data-shipment-id="' . $orderShipment->shipment_id  . '" type="button" class="d-button icon primary js-d-request-courier">
                                <i class="dashicons dashicons-car"></i>
                            </button>
                        ';
                    }
                    echo '
                        <a target="_blank" href="https://tracking.dpd.ro/?shipmentNumber=' . $orderShipment->shipment_id . '" title="' . __('Track for order without DPD RO shipping method', 'dpdro') . '" class="d-button icon info js-d-track-order">
                            <i class="dashicons dashicons-location"></i>
                        </a>
                    ';
                    if ($orderRequestCourier && !empty($orderRequestCourier)) {
                        echo '
                            <button style="cursor: help;" title="' . __('A courier has been requested for order without DPD RO shipping method', 'dpdro') . '" type="button" class="d-button icon success">
                                <i class="dashicons dashicons-info-outline"></i>
                            </button>
                        ';
                    }
                } else {
                    $ajaxNonceCreateShipment = wp_create_nonce('dpdro_create_shipment');
                    echo '
                        <button data-nonce="' . $ajaxNonceCreateShipment . '" title="' . __('Create shipment for order without DPD RO shipping method', 'dpdro') . '" data-order-id="' . $order->id  . '" type="button" class="d-button icon primary js-d-create-shipment">
                            <i class="dashicons dashicons-welcome-add-page"></i>
                        </button>
                    ';
                }
            }
        }
        echo '
                    </div>						
                </div>
                <div data-order-id="' . $order->id  . '" class="d-modal js-d-modal">
                    <div class="d-modal-box js-d-modal-box">
                        <button type="button" class="d-modal-close js-d-modal-close">
                            <i class="fa fa-times" aria-hidden="true"></i>
                        </button>  
                        <div class="d-modal-content js-d-modal-content"></div>
                    </div>
                </div>
            </div>
        ';
    }

    /**
     * Update dpd order address when order admin address updated
     */
    public function orderActionAdminUpdate($postId, $post)
    {
        $post = get_post($postId);
        if ($post->post_type == 'shop_order') {
            $address = $this->getOrderAddress($postId);
            if ($address && !empty($address)) {
                $this->updateOrderAddress($address->id, $_POST);
            }
        }
    }

    /**
     * Order DPD RO actions email.
     */
    public function orderActionEmail($order, $sent, $mail)
    {
        $shipment = $this->getOrderShipment($order->get_order_number());
        if ($shipment && isset($shipment->shipment_id) && !empty($shipment->shipment_id)) {
            if ($mail === false) {
                echo '
        			<h2>
        				DPD RO - <a href="https://tracking.dpd.ro/?shipmentNumber=' . $shipment->shipment_id . '" target="_blank">' . __('Tracking url', 'dpdro') . '</a>
        			</h2>
        		';
            } else {
                echo "DPD RO Tracking\n https://tracking.dpd.ro/?shipmentNumber=" . $shipment->shipment_id;
            }
        }
    }

    /** 
     * Check country.
     */
    public function checkCountry($code = false, $all = false)
    {
        if ($all) {
            if ($code) {

                if (in_array($code, DPDUtil::getAllowedCountryCodes(), true)) {
				    return true;
                } else {
                    return false;
                }
            }
        } else {
            if ($code) {
	            if (in_array($code, DPDUtil::getAllowedCountryCodes(), true)) {
		            return true;
	            } else {
		            return false;
	            }
            }
        }
        return false;
    }

    /**
     * Insert order address to database.
     */
    public function insertOrderAddress($orderData)
    {
        /**
         * Insert new records.
         */
        $query = $this->wpdb->insert(
            $this->tableOrderAddresses,
            array(
                'order_id'            => $orderData['order_id'],
                'address'             => $orderData['address'],
                'address_city_id'     => $orderData['address_city_id'],
                'address_city_name'   => $orderData['address_city_name'],
                'address_street_id'   => $orderData['address_street_id'],
                'address_street_type' => $orderData['address_street_type'],
                'address_street_name' => $orderData['address_street_name'],
                'address_number'      => $orderData['address_number'],
                'address_block'       => $orderData['address_block'],
                'address_apartment'   => $orderData['address_apartment'],
                'method'              => $orderData['method'],
                'office_id'           => $orderData['office_id'],
                'office_name'         => $orderData['office_name'],
                'status'              => $orderData['status'],
                'date_added'          => date('Y-m-d H:i:s'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        /**
         * Response.
         */
        if ($query) {
            return true;
        }
        return false;
    }

    /** 
     * Get order address stored by order id.
     */
    public function getOrderAddress($orderId)
    {
        $query = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableOrderAddresses} WHERE `order_id` = %s", $orderId));
        if (!empty($query)) {
            return $query;
        }
        return false;
    }

    /** 
     * Update order address stored by order id.
     */
    public function updateOrderAddress($addressId, $data)
    {
        $address = $data['_shipping_address_1'];
        $city = $data['_shipping_city'];
        $query = $this->wpdb->query($this->wpdb->prepare(
            "
            UPDATE {$this->tableOrderAddresses} 
            SET 
                address = '{$address}',
                address_city_id = '',
                address_city_name = '{$city}',
                status = 'skip'
            WHERE `id` = %s",
            $addressId
        ));
        if (!empty($query)) {
            return $query;
        }
        return false;
    }

    /** 
     * Update order address stored by order id.
     */
    public function updateOrderOffice($officeData)
    {
        $officeId = $officeData['office_id'];
        $officeName = $officeData['office_name'];
        $query = $this->wpdb->query($this->wpdb->prepare(
            "
            UPDATE {$this->tableOrderAddresses} 
            SET 
                office_id = '{$officeId}',
                office_name = '{$officeName}',
                status = 'validated'
            WHERE `order_id` = %s",
            $officeData['order_id']
        ));
        if (!empty($query)) {
            return $query;
        }
        return false;
    }

    /** 
     * Update order address stored by order id after validation.
     */
    public function updateOrderAddressValidated($data)
    {
        $orderId = $data['orderId'];
        $streetId = $data['streetId'];
        $streetType = $data['streetType'];
        $streetName = $data['streetName'];
        $streetNumber = $data['number'];
        $streetBlock = $data['block'];
        $streetApartment = $data['apartment'];
        $this->wpdb->query($this->wpdb->prepare(
            "
            UPDATE {$this->tableOrderAddresses} 
            SET 
                address_street_id = '{$streetId}',
                address_street_type = '{$streetType}',
                address_street_name = '{$streetName}',
                address_number = '{$streetNumber}',
                address_block = '{$streetBlock}',
                address_apartment = '{$streetApartment}',
                method = 'delivery',
                status = 'validated'
            WHERE `order_id` = %s",
            $orderId
        ));
    }

    /** 
     * Delete order address stored by order id.
     */
    public function deleteOrderAddress($orderId)
    {
        $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->tableOrderAddresses} WHERE `order_id` = '{$orderId}'", array()));
    }

    /** 
     * Insert order settings to database.
     */
    public function insertOrderSettings($orderData)
    {
        /**
         * Data settings.
         */
        $dataSettings = new DataSettings($this->wpdb);
        $settings = $dataSettings->getSettings();

        /**
         * Insert new records.
         */
        $query = $this->wpdb->insert(
            $this->tableOrderSettings,
            array(
                'order_id'          => $orderData['order_id'],
                'shipping_tax_rate' => $orderData['shipping_tax_rate'],
                'shipping_tax'      => $orderData['shipping_tax'],
                'courier_service'   => $settings['courier_service_payer'],
                'declared_value'    => $settings['sender_payer_insurance'],
                'include_shipping'  => $settings['include_shipping_price'],
                'date_added'        => date('Y-m-d H:i:s'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        /**
         * Response.
         */
        if ($query) {
            return true;
        }
        return false;
    }

    /** 
     * Update order address stored by order id.
     */
    public function updateOrderSettings($data)
    {
        /**
         * Data settings.
         */
        $dataSettings = new DataSettings($this->wpdb);
        $settings = $dataSettings->getSettings();

        $shippingTax = $data['shipping_tax'];
        $shippingTaxRate = $data['shipping_tax_rate'];
        $courierServicePayer = $settings['courier_service_payer'];
        $senderPayerInsurance = $settings['sender_payer_insurance'];
        $includeShippingPrice = $settings['include_shipping_price'];

        $orderSettings = $this->getOrderSettings($data['order_id']);
        if (!$orderSettings || empty($orderSettings)) {
            $query = $this->wpdb->query($this->wpdb->prepare(
                "
                UPDATE {$this->tableOrderSettings} 
                SET 
                    shipping_tax_rate = '{$shippingTax}',
                    shipping_tax = '{$shippingTaxRate}',
                    courier_service = '{$courierServicePayer}',
                    declared_value = '{$senderPayerInsurance}',
                    include_shipping = '{$includeShippingPrice}'
                WHERE `order_id` = %s",
                $data['order_id']
            ));
            if (!empty($query)) {
                return $query;
            }
        } else {
            $this->insertOrderSettings($data);
        }
        return false;
    }

    /** 
     * Get order settings stored by order id.
     */
    public function getOrderSettings($orderId)
    {
        $query = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableOrderSettings} WHERE `order_id` = %s", $orderId));
        if ($query && !empty($query)) {
            return $query;
        }
        return false;
    }

    /** 
     * Delete order settings stored by order id.
     */
    public function deleteOrderSettings($orderId)
    {
        $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->tableOrderSettings} WHERE `order_id` = '{$orderId}'", array()));
    }

    /** 
     * Get order shipment stored by order id.
     */
    public function getOrderShipment($orderId)
    {
        $query = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableOrderShipment} WHERE `order_id` = %s", $orderId));
        if ($query && !empty($query)) {
            return $query;
        }
        return false;
    }

    /** 
     * Insert order shipment stored by order id.
     */
    public function insertOrderShipment($shipment)
    {
        /**
         * Insert new records.
         */
        $query = $this->wpdb->insert(
            $this->tableOrderShipment,
            array(
                'order_id'      => $shipment['order_id'],
                'shipment_id'   => $shipment['shipment_id'],
                'shipment_data' => $shipment['shipment_data'],
                'parcels'       => $shipment['parcels'],
                'price'         => $shipment['price'],
                'pickup'        => $shipment['pickup'],
                'deadline'      => $shipment['deadline'],
                'date_added'    => date('Y-m-d H:i:s'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        /**
         * Response.
         */
        if ($query) {
            return true;
        }
        return false;
    }

    /** 
     * Delete order shipment stored by order id.
     */
    public function deleteOrderShipment($orderId)
    {
        $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->tableOrderShipment} WHERE `order_id` = '{$orderId}'", array()));
    }

    /** 
     * Get request courier by shipment id.
     */
    public function getRequestCourier($shipmentId)
    {
        $query = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableRequesteCourier} WHERE `orders_ids` LIKE %s", '%' . $shipmentId . '%'));
        if ($query && !empty($query)) {
            return $query;
        }
        return false;
    }

    /** 
     * Insert request courier.
     */
    public function insertRequestCourier($request)
    {
        /**
         * Insert new records.
         */
        $query = $this->wpdb->insert(
            $this->tableRequesteCourier,
            array(
                'orders_ids'  => $request['orders_ids'],
                'request_id'  => $request['request_id'],
                'pickup_from' => $request['pickup_from'],
                'pickup_to'   => $request['pickup_to'],
                'date_added'  => date('Y-m-d H:i:s'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        /**
         * Response.
         */
        if ($query) {
            return true;
        }
        return false;
    }

    /** 
     * Insert order shipping to database.
     */
    public function insertOrderShipping($orderData)
    {
        /**
         * Insert new records.
         */
        $query = $this->wpdb->insert(
            $this->tableOtherMethod,
            array(
                'order_id'     => $orderData['order_id'],
                'method_id'    => $orderData['method_id'],
                'method_code'  => $orderData['method_code'],
                'method_title' => $orderData['method_title'],
                'date_added'   => date('Y-m-d H:i:s'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        /**
         * Response.
         */
        if ($query) {
            return true;
        }
        return false;
    }

    /** 
     * Get last order shipping stored by order id.
     */
    public function getOrderShipping($orderId)
    {
        $query = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableOtherMethod} WHERE `order_id` = %s ORDER BY `id` DESC", $orderId));
        if ($query && !empty($query)) {
            return $query;
        }
        return false;
    }

    /** 
     * Get last order shipping history stored by order id.
     */
    public function getOrderShippingHistory($orderId)
    {
        $query = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->tableOtherMethod} WHERE `order_id` = %s ORDER BY `id` DESC", $orderId));
        if ($query && !empty($query)) {
            return $query;
        }
        return false;
    }

    /**
     * Ajax create shipment.
     */
    public function createShipment()
    {
        check_ajax_referer('dpdro_create_shipment', 'nonce');

        /**
         * Response.
         */
        $json = [
            'error' => true
        ];

        if (isset($_POST['action'])) {

            /**
             * Response HTML.
             */
            ob_start();
            include_once PLUGIN_DIR_DPDRO . 'includes/woo/shipment.php';
            $json['html'] = ob_get_clean();
            $json['error'] = false;
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax save shipment.
     */
    public function saveShipment()
    {
        check_ajax_referer('dpdro_save_shipment', 'nonce');

        $json = [
            'error' => true
        ];
        if (isset($_POST['action'])) {

            /**
             * Data settings.
             */
            $dataSettings = new DataSettings($this->wpdb);
            $settings = $dataSettings->getSettings();

            /**
             * Library API.
             */
            $libraryApi = new LibraryApi($settings['username'], $settings['password']);

            /**
             * Data lists.
             */
            $dataList = new DataLists($this->wpdb);

            /**
             * Request params.
             */
            $params = $_POST['params'];

            /**
             * Get an instance of the WC_Order object. 
             */
            $order = wc_get_order($params['orderId']);

            /**
             * Order settings.
             */
            $orderSettings = $this->getOrderSettings($params['orderId']);
            if (!$orderSettings || empty($orderSettings)) {
                $orderSettings = (object) [
                    'shipping_tax'      => '',
                    'shipping_tax_rate' => '',
                    'courier_service'   => $settings['courier_service_payer'],
                    'declared_value'    => $settings['sender_payer_insurance'],
                    'include_shipping'  => $settings['include_shipping_price'],
                ];
            }

            /**
             * Request data.
             */
            $requestData = [
                'service'      => [
                    'autoAdjustPickupDate' => true,
                    'serviceId'            => ''
                ],
                'content'      => [
                    'package'  => $params['packages'],
                    'contents' => $params['contents'],
                    'parcels'  => array()
                ],
                'payment'      => [
                    'courierServicePayer' => $orderSettings->courier_service
                ],
                'recipient'    => [],
                'shipmentNote' => $params['notes'],
                'ref1'         => __('Woo v3.0 ID: ', 'dpdro') . $params['orderId'],
                'ref2'         => $params['ref2'],
            ];

            /**
             * Clients / Offices
             */
            if ((!empty($settings['office_locations']) && $settings['office_locations'] !== '0') || (!empty($settings['client_contracts']) && $settings['client_contracts'] !== '0')) {
                if (!empty($settings['office_locations']) && $settings['office_locations'] !== '0') {
                    $requestData['sender'] = [
                        'dropoffOfficeId' => (int) $settings['office_locations']
                    ];
                } else if (!empty($settings['client_contracts']) && $settings['client_contracts'] !== '0') {
                    $requestData['sender'] = [
                        'clientId' => $settings['client_contracts'],
                    ];
                }
            }

            /**
             * Third party
             */
            if ($orderSettings->courier_service === 'THIRD_PARTY') {
                $requestData['payment']['thirdPartyClientId'] = $settings['id_payer_contract'];
            }

            /**
             * Shipping
             */
            $orderShippingMethod = false;
            $orderShippingMethods = $order->get_shipping_methods();
            foreach ($orderShippingMethods as $method) {
                $orderShippingMethod = str_replace('dpdro_shipping_', '', $method->get_method_id());
                $orderShippingMethod = str_replace('shipping_dpd_', '', $orderShippingMethod);
            }
            if (!$dataList->getServiceById($orderShippingMethod)) {
                $orderShipping = $this->getOrderShipping($order->id);
                $orderShippingMethod = $orderShipping->method_code;
            }
            $requestData['service']['serviceId'] = (int) $orderShippingMethod;

            /**
             * Products / Parcels
             */
            if ($params['products']) {
                $parcels = [];
                if ($orderShippingMethod == '2412' || $settings['packaging_method'] == 'all') {
	                $params['products'] = $this->arrangeProducts($params['products']);
                    $index = 0;
                    $seqNo = 1;
                    foreach ($params['products'] as $product) {
                        $requestData['content']['parcels'][] = [
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
                } else {
                    foreach ($params['products'] as $product) {
                        if (is_array($parcels) && !empty($parcels) && array_key_exists($product['parcel'], $parcels)) {
                            $parcelWeight = floatval($parcels[$product['parcel']]);
                        } else {
                            $parcelWeight = 0;
                        }
                        $parcels[$product['parcel']] = $parcelWeight + floatval($product['weight']);
                    }
                    foreach ($parcels as $key => $parcel) {
                        $productParcel = [
                            'seqNo' => (int) $key,
                            'weight' => floatval($parcel)
                        ];
                        array_push($requestData['content']['parcels'], $productParcel);
                    }
                }
            }
            if ($settings['use_default_weight']) {
                $defaultWeight = (float) $settings['default_weight'];
                if ($defaultWeight != 0) {
                    $parcelsTotalWeight = 0.0;
                    foreach ($requestData['content']['parcels'] as $key => $parcel) {
                        $parcel_weight = (float) $parcel['weight'];
                        if ($parcel_weight == 0) {
                            $requestData['content']['parcels'][$key]['weight'] = $defaultWeight;
                        }
                        $parcelsTotalWeight = $parcelsTotalWeight + $requestData['content']['parcels'][$key]['weight'];
                    }
                    $requestData['content']['totalWeight'] = $parcelsTotalWeight;
                }
            }

            /**
             * Extra options
             */
            if ($params['swap'] && $params['swap'] !== 'false') {
                $requestData['service']['additionalServices']['returns']['swap'] = [
                    'serviceId'    => $orderShippingMethod,
                    'parcelsCount' => count($requestData['content']['parcels'])
                ];
            }
            if ($params['rsp'] && $params['rsp'] !== 'false') {
                $requestData['service']['additionalServices']['returns']['swap'] = [
                    'serviceId'    => 2007,
                    'parcelsCount' => count($requestData['content']['parcels'])
                ];
            }
            if ($params['rod'] && $params['rod'] !== 'false') {
                $requestData['service']['additionalServices']['returns']['rod'] = [
                    'enabled' => true
                ];
            }
            if ($orderShippingMethod && $orderShippingMethod == '2412') {
                if ($params['rop'] && $params['rop'] !== 'false') {
                    $requestData['service']['additionalServices']['returns']['rop'] = [
                        'pallets' => array(
                            [
                                'serviceId'    => $orderShippingMethod,
                                'parcelsCount' => count($requestData['content']['parcels']),
                            ]
                        )
                    ];
                }
            }
            if ($params['voucher'] && $params['voucher'] !== 'false') {
                $requestData['service']['additionalServices']['returns']['returnVoucher'] = [
                    'serviceId' => (int) $orderShippingMethod,
                    'payer'     =>  $params['voucherSender']
                ];
            }
            $optionPickupType = $order->get_meta('dpdro_pickup_type');
            if (!$optionPickupType || ($optionPickupType && !empty($optionPickupType) && $optionPickupType === 'OFFICE')) {
                if ($settings['test_or_open'] && !empty($settings['test_or_open'])) {
                    if ($orderShippingMethod == '2505' || $orderShippingMethod == '2002' || $orderShippingMethod == '2113' || $orderShippingMethod == '2005') {
                        $requestData['service']['additionalServices']['obpd'] = [
                            'option'                  => $settings['test_or_open'],
                            'returnShipmentServiceId' => (int) $orderShippingMethod,
                            'returnShipmentPayer'     => $settings['test_or_open_courier']
                        ];
                    }
                }
            }

            /**
             * Payment
             */
            if ($order->get_payment_method() && $order->get_payment_method() === 'cod') {
                if ($this->checkCountry($order->get_shipping_country(), true)) {
                    $packageAddress = array(
                        'destination' => array(
                            'country'  => $order->get_shipping_country(),
                            'state'    => $order->get_shipping_state(),
                            'postcode' => $order->get_shipping_postcode()
                        )
                    );
                    if (DataZones::checkCustomPayment($packageAddress, $dataSettings)) {
                        //$totalCod = number_format((float) $order->get_total() - (float) $order->get_shipping_total(), 2, '.', '') - (float) $settings['payment_tax'];
                        $totalCod = number_format((float) $order->get_total(), 2, '.', '');
                    } else {
                        $orderCodFee = 0;
                        foreach ($order->get_fees() as $orderFee) {
                            if ($orderFee->get_name() == 'Cash on delivery DPD RO' || $orderFee->get_name() == 'Comision ramburs DPD RO') {
                                $orderCodFee = (float) $orderFee->get_total();
                            }
                        }
                        //$totalCod = number_format((float) $order->get_total() - (float) $order->get_shipping_total(), 2, '.', '') - (float) $orderCodFee;
                        $totalCod = number_format((float) $order->get_total(), 2, '.', '');
                    }
                    $requestData['service']['additionalServices']['cod'] = [
                        'currencyCode'   => $order->get_currency(),
                        'processingType' => 'CASH'
                    ];
                    if ($orderSettings->shipping_tax_rate === 'yes') {
                        if ($orderSettings->courier_service === 'RECIPIENT') {
                            /** 
                             * Recipient pay the tax
                             */
                        } else {
                            $totalCod = number_format((float) $order->get_total(), 2, '.', '');
                        }
                    } else {
                        if ($orderSettings->courier_service === 'RECIPIENT' || $orderSettings->include_shipping === 'no' || !$orderSettings->include_shipping) {
                            /** 
                             * Recipient pay the tax
                             */
                        } else {
                            //$requestData['service']['additionalServices']['cod']['includeShippingPrice'] = true;
                        }
                    }
                    $requestData['service']['additionalServices']['cod']['amount'] = number_format((float) $totalCod, 2, '.', '');
                }
            } else {
                $requestData['payment']['courierServicePayer'] = 'SENDER';
            }
            if ($orderSettings->declared_value == '1' || $orderSettings->declared_value === 'yes') {
                $requestData['service']['additionalServices']['declaredValue']['amount'] = number_format($order->get_subtotal(), 2, '.', '');
            }

            /**
             * Customer data
             */
            $client = ($order->get_shipping_last_name() && $order->get_shipping_last_name() !== '') ? ' - ' . $order->get_shipping_last_name() : '';
            if ($params['private'] == 'true') {
                $requestData['recipient'] = [
                    'phone1'        => [
                        'number' => (string) $order->get_billing_phone()
                    ],
                    'email'         => (string) $order->get_billing_email(),
                    'clientName'    => $params['privatePerson'],
                    'contactName'   => $order->get_shipping_first_name() . $client,
                    'privatePerson' => false
                ];
            } else {
                $requestData['recipient'] = [
                    'phone1'        => [
                        'number' => (string) $order->get_billing_phone()
                    ],
                    'email'         => (string) $order->get_billing_email(),
                    'clientName'    => $order->get_shipping_first_name() . $client,
                    'privatePerson' => true
                ];
            }

            /**
             * Address
             */
            $addressData = $this->getOrderAddress($params['orderId']);
            if (
				$addressData &&
				$this->checkCountry($order->get_shipping_country()) &&
				(
					$addressData->status &&
					!empty($addressData->status) &&
					$addressData->status == 'skip' &&
					(
						$order->get_shipping_country() == 'RO' ||
						$order->get_shipping_country() == 'BG'
					)
				)
            ) {
                if ($addressData->method && $addressData->method === 'pickup') {

                    /**
                     * Address pickup
                     */
                    $requestData['recipient']['pickupOfficeId'] = (int) $addressData->office_id;
                } else {

                    /**
                     * Address delivery
                     */
                    $countryData = $libraryApi->countryByID($order->get_shipping_country());
                    if ($countryData) {
                        $requestData['recipient']['address']['countryId'] = $countryData['id'];
                        if (array_key_exists('postCodeFormats', $countryData) && !empty($countryData['postCodeFormats']) && is_array($countryData['postCodeFormats'])) {
                            if ($order->get_shipping_postcode() && !empty($order->get_shipping_postcode())) {
                                $requestData['recipient']['address']['postCode'] = trim($order->get_shipping_postcode());
                            }
                        }
                    }
                    if (isset($addressData->address_city_id) &&  !empty($addressData->address_city_id)) {
                        $requestData['recipient']['address']['siteId'] = $addressData->address_city_id;
                    } else {
                        if (isset($addressData->address_city_name) &&  !empty($addressData->address_city_name)) {
                            $requestData['recipient']['address']['siteName'] = $this->removeDiactritics($addressData->address_city_name);
                        }
                    }
                    if ($addressData->status && !empty($addressData->status) && $addressData->status == 'skip') {
                        $requestData['recipient']['address']['addressNote'] = $this->removeDiactritics($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2());
                    } else {
                        if (isset($addressData->address_street_id) &&  !empty($addressData->address_street_id)) {
                            $requestData['recipient']['address']['streetId'] = $addressData->address_street_id;
                        } else {
                            if (isset($addressData->address_street_type) &&  !empty($addressData->address_street_type)) {
                                $requestData['recipient']['address']['streetType'] = $addressData->address_street_type;
                            }
                        }
                        $requestData['recipient']['address']['streetNo'] = 0;
                        if (isset($addressData->address_number) &&  !empty($addressData->address_number)) {
                            $requestData['recipient']['address']['streetNo'] = (int) $addressData->address_number;
                        }
                        if (isset($addressData->address_block) &&  !empty($addressData->address_block)) {
                            $requestData['recipient']['address']['blockNo'] = $addressData->address_block;
                        }
                        if (isset($addressData->address_apartment) &&  !empty($addressData->address_apartment)) {
                            $requestData['recipient']['address']['apartmentNo'] = $addressData->address_apartment;
                        }
                    }
                }
            } else {
                $countryData = $libraryApi->countryByID($order->get_shipping_country());
                if ($countryData) {
                    $requestData['recipient']['address']['countryId'] = $countryData['id'];
                }
                if ($order->get_shipping_city()) {
                    $requestData['recipient']['address']['siteName'] = $this->removeDiactritics($order->get_shipping_city());
                }
                if (strlen($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2()) > 34) {
                    $orderAddressLength = strlen($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2());
                    $addressLine1 = substr($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(), 0, 34);
                    $addressLine2 = substr($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(), 34, $orderAddressLength);
                    $requestData['recipient']['address']['addressLine1'] = $this->removeDiactritics($addressLine1);
                    $requestData['recipient']['address']['addressLine2'] = $this->removeDiactritics($addressLine2);
                } else {
                    $requestData['recipient']['address']['addressLine1'] = $this->removeDiactritics($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2());
                }
                if ($order->get_shipping_postcode() && !empty($order->get_shipping_postcode())) {
                    $requestData['recipient']['address']['postCode'] = trim($order->get_shipping_postcode());
                }
            }

            /**
             * Shipment
             */
            $shipment = $libraryApi->createShipment($requestData);
            if (!is_array($shipment) || empty($shipment) || array_key_exists('error', $shipment)) {
                $errorMessage = $shipment['error']['message'];
                $errorContext = $shipment['error']['context'];
            } else {
                $deadline = (isset($shipment['deliveryDeadline']) && !empty($shipment['deliveryDeadline'])) ? $shipment['deliveryDeadline'] : '0000-00-00 00:00:00';
                $shipmentData = [
                    'order_id'      => $params['orderId'],
                    'shipment_id'   => $shipment['id'],
                    'shipment_data' => json_encode([
                        'shipment_swap'        => $params['swap'],
                        'shipment_rod'         => $params['rod'],
                        'shipment_has_voucher' => $params['voucher'],
                    ]),
                    'parcels'       => json_encode($shipment['parcels']),
                    'price'         => json_encode($shipment['price']),
                    'pickup'        => $shipment['pickupDate'],
                    'deadline'      => $deadline
                ];
                $orderShipment = $this->insertOrderShipment($shipmentData);
                if ($orderShipment && !empty($orderShipment)) {
                    $json['success'] = true;
                    $successMessage = __('Shipment created successfully.', 'dpdro');
                    $notice = sprintf(__('Shipment created successfully for %s.', 'dpdro'), '<b>Order ID: ' . $params['orderId'] . '</b>');
                    set_transient('dpdro_notice_success', $notice, 60 * 5);
                } else {
                    $errorMessage = __('Something went wrong.', 'dpdro');
                }
            }
        } else {
            $errorMessage = __('Something went wrong.', 'dpdro');
        }

        /**
         * Response HTML.
         */
        ob_start();
        include_once PLUGIN_DIR_DPDRO . 'includes/woo/shipment.php';
        $json['html'] = ob_get_clean();

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /** 
     * Ajax delete shipment.
     */
    public function deleteShipment()
    {
        check_ajax_referer('dpdro_delete_shipment', 'nonce');

        $json = [
            'error' => true
        ];
        if (isset($_POST['action'])) {
            $json['error'] = false;

            /**
             * Request params.
             */
            $params = $_POST['params'];
            $shipment = $this->getOrderShipment($params['orderId']);
            $this->deleteOrderShipment($params['orderId']);

            /**
             * Data settings.
             */
            $dataSettings = new DataSettings($this->wpdb);
            $settings = $dataSettings->getSettings();

            /**
             * Library API.
             */
            $libraryApi = new LibraryApi($settings['username'], $settings['password']);
            $libraryApiShipment = $libraryApi->deleteShipment($shipment->shipment_id);
            $notice = sprintf(__('Shipment with %s deleted successfully for %s.', 'dpdro'), '<b>ID: ' . $shipment->shipment_id . '</b>', '<b>Order ID: ' . $params['orderId'] . '</b>');
            set_transient('dpdro_notice_success', $notice, 60 * 5);
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
            set_transient('dpdro_notice_error', $json['message'], 60 * 5);
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax skip validation address.
     */
    public function skipValidationAddress()
    {
        check_ajax_referer('dpdro_skip_validation_address', 'nonce');

        /**
         * Response.
         */
        $json = [
            'error' => true
        ];

        if (isset($_POST['action'])) {

            /**
             * Response HTML.
             */
            $address = $this->getOrderAddress($_POST['params']['orderId']);
            if ($address && !empty($address)) {
                $this->wpdb->query($this->wpdb->prepare("UPDATE {$this->tableOrderAddresses} SET status = 'skip' WHERE `id` = %s", $address->id));
            }

            /**
             * Response HTML.
             */
            ob_start();
            include_once PLUGIN_DIR_DPDRO . 'includes/woo/shipment.php';
            $json['html'] = ob_get_clean();
            $json['error'] = false;
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax validation address.
     */
    public function validationAddress()
    {
        check_ajax_referer('dpdro_validation_address', 'nonce');

        /**
         * Response.
         */
        $json = [
            'error' => true
        ];

        if (isset($_POST['action'])) {

            /**
             * Response HTML.
             */
            ob_start();
            include_once PLUGIN_DIR_DPDRO . 'includes/woo/validation.php';
            $json['html'] = ob_get_clean();
            $json['error'] = false;
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax search street.
     */
    public function searchStreet()
    {
        check_ajax_referer('dpdro_search_street', 'nonce');

        /**
         * Response.
         */
        $json = [
            array(
                'id'       => 1,
                'text'     => $this->removeDiactritics($_POST['search']),
                'actualId' => 0,
                'siteId'   => 1,
                'type'     => '',
                'typeEn'   => '',
                'name'     => $this->removeDiactritics($_POST['search']),
                'nameEn'   => $this->removeDiactritics($_POST['search'])
            )
        ];

        if (isset($_POST['action'])) {

            /**
             * Data settings.
             */
            $dataSettings = new DataSettings($this->wpdb);
            $settings = $dataSettings->getSettings();

            /**
             * Library API.
             */
            $libraryApi = new LibraryApi($settings['username'], $settings['password']);
            $streets = $libraryApi->searchStreet($_POST['country'], $_POST['cityId'], $_POST['search']);
            if ($streets && !empty($streets)) {
                $json = $streets;
            }
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax search city.
     */
    public function searchCity()
    {
        check_ajax_referer('dpdro_search_city', 'nonce');

        /**
         * Response.
         */
        $json = [
            array(
                'id'       => 0,
                'text'     => __('No city found', 'dpdro'),
                'actualId' => 0,
                'siteId'   => 0,
                'name'     => __('No city found', 'dpdro'),
                'nameEn'   => __('No city found', 'dpdro'),
                'postcode' => '',
            )
        ];

        if (isset($_POST['action'])) {

            /**
             * Data address.
             */
            $libraryApi = new DataAddresses($this->wpdb);
            $cities = $libraryApi->getAddressSearch($_POST['country'], $_POST['state'], $_POST['search']);
            $citiesList = [];
            if ($cities && !empty($cities)) {
                foreach ($cities as $city) {
                    $citiesList[] = [
                        'id'       => $city->site_id,
                        'text'     => $city->name,
                        'actualId' => $city->site_id,
                        'siteId'   => $city->site_id,
                        'name'     => $city->name,
                        'nameEn'   => $city->name_en,
                        'postcode' => $city->post_code,
                    ];
                }
            } else {
                $cities = $libraryApi->getAddressByPostcode($_POST['postcode']);
                if ($cities && !empty($cities)) {
                    foreach ($cities as $city) {
                        $citiesList[] = [
                            'id'       => $city->site_id,
                            'text'     => $city->name,
                            'actualId' => $city->site_id,
                            'siteId'   => $city->site_id,
                            'name'     => $city->name,
                            'nameEn'   => $city->name_en,
                            'postcode' => $city->post_code,
                        ];
                    }
                }
            }
            if ($citiesList && !empty($citiesList)) {
                $json = $citiesList;
            }
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax validate address.
     */
    public function validateAddress()
    {
        check_ajax_referer('dpdro_validate_address', 'nonce');

        /**
         * Response.
         */
        $json = [];

        if (isset($_POST['action'])) {
            if ($_POST['params']['streetId'] != '' && $_POST['params']['streetId'] != '0') {

                /**
                 * Data settings.
                 */
                $dataSettings = new DataSettings($this->wpdb);
                $settings = $dataSettings->getSettings();

                /**
                 * Library API.
                 */
                $libraryApi = new LibraryApi($settings['username'], $settings['password']);
                $libraryApiAddress = [
                    'country'  => $_POST['params']['country'],
                    'cityId'   => $_POST['params']['cityId'],
                    'cityName' => $_POST['params']['city'],
                    'streetId' => $_POST['params']['streetId'],
                    'number'   => $_POST['params']['number'],
                    'postcode' => $_POST['params']['postcode'],
                ];
                $addressValidate = $libraryApi->addressValidation($libraryApiAddress);
                if ($addressValidate['valid']) {

                    /**
                     * Update order address.
                     */
                    $orderAddress1 = '';
                    if (!empty($_POST['params']['streetType'])) {
                        $orderAddress1 = $_POST['params']['streetType'];
                    }
                    $orderAddress1 .= $_POST['params']['streetName'] != '' ? ' ' . $_POST['params']['streetName'] : '';
                    $orderAddress1 .= $_POST['params']['number'] != '' ? ', nr. ' . $_POST['params']['number'] : '';
                    $orderAddress1 .= $_POST['params']['block'] != '' ? ', bl. ' . $_POST['params']['block'] : '';
                    $orderAddress1 .= $_POST['params']['apartment'] != '' ? ', ap. ' . $_POST['params']['apartment'] : '';
                    update_post_meta($_POST['params']['orderId'], '_shipping_city', $_POST['params']['city']);
                    update_post_meta($_POST['params']['orderId'], '_shipping_postcode', $_POST['params']['postcode']);
                    if (!empty($orderAddress1)) {
                        update_post_meta($_POST['params']['orderId'], '_shipping_address_1', $orderAddress1);
                    }

                    /**
                     * Update address.
                     */
                    $this->updateOrderAddressValidated($_POST['params']);

                    /**
                     * Success message.
                     */
                    $successMessage = __('Address has been validated successfully.', 'dpdro');
                } else {

                    /**
                     * Error message.
                     */
                    $errorMessage = $addressValidate['error']['message'];
                }
            } else {

                /**
                 * Error message.
                 */
                $errorMessage = __('Something went wrong.', 'dpdro');
            }
        } else {

            /**
             * Error message.
             */
            $errorMessage = __('Something went wrong.', 'dpdro');
        }

        /**
         * Response HTML.
         */
        ob_start();
        include_once PLUGIN_DIR_DPDRO . 'includes/woo/validation.php';
        $json['html'] = ob_get_clean();

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax request courier.
     */
    public function requestCourier()
    {
        check_ajax_referer('dpdro_request_courier', 'nonce');

        /**
         * Response.
         */
        $json = [
            'error' => true
        ];

        if (isset($_POST['action'])) {
            /**
             * Request params.
             */
            $params = $_POST['params'];

            /**
             * Data settings.
             */
            $dataSettings = new DataSettings($this->wpdb);
            $settings = $dataSettings->getSettings();

            /**
             * Library API.
             */
            $libraryApi = new LibraryApi($settings['username'], $settings['password']);
            $courier = $libraryApi->requestCourier($params['shipments']);
            if (!is_array($courier) || empty($courier) || array_key_exists('error', $courier)) {
                set_transient('dpdro_notice_error', $courier['error']['message'], 60 * 5);
            } else {
                $courierData = [
                    'orders_ids'  => json_encode($params['shipments']),
                    'request_id'  => $courier['orders'][0]['id'],
                    'pickup_from' => $courier['orders'][0]['pickupPeriodFrom'],
                    'pickup_to'   => $courier['orders'][0]['pickupPeriodTo']
                ];
                $this->insertRequestCourier($courierData);
                $notice = __('Requested courier successfully.', 'dpdro');
                set_transient('dpdro_notice_success', $notice, 60 * 5);
            }
            $json['error'] = false;
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
            set_transient('dpdro_notice_error', $json['message'], 60 * 5);
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax add address.
     */
    public function addAddress()
    {
        check_ajax_referer('dpdro_add_address', 'nonce');

        /**
         * Response.
         */
        $json = [
            'error' => true
        ];

        if (isset($_POST['action'])) {

            /**
             * Response HTML.
             */
            ob_start();
            include_once PLUGIN_DIR_DPDRO . 'includes/woo/address.php';
            $json['html'] = ob_get_clean();
            $json['error'] = false;
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax save address.
     */
    public function saveAddress()
    {
        check_ajax_referer('dpdro_save_address', 'nonce');

        /**
         * Response.
         */
        $json = [];

        if (isset($_POST['action'])) {
            $params = $_POST['params'];

            /**
             * Update order address.
             */
            $orderAddress1 = '';
            if (!empty($params['streetType'])) {
                $orderAddress1 = $params['streetType'];
            }
            $orderAddress1 .= $params['streetName'] != '' ? ' ' . $params['streetName'] : '';
            $orderAddress1 .= $params['number'] != '' ? ', nr. ' . $params['number'] : '';
            $orderAddress1 .= $params['block'] != '' ? ', bl. ' . $params['block'] : '';
            $orderAddress1 .= $params['apartment'] != '' ? ', ap. ' . $params['apartment'] : '';
            update_post_meta($params['orderId'], '_shipping_city', $params['city']);
            update_post_meta($params['orderId'], '_shipping_postcode', $params['postcode']);
            if (!empty($orderAddress1)) {
                update_post_meta($params['orderId'], '_shipping_address_1', $orderAddress1);
            }
            if ($params['method'] == 'pickup') {
                update_post_meta($params['orderId'], 'dpdro_pickup', $params['officeId']);
                update_post_meta($params['orderId'], 'dpdro_pickup_name', $params['officeName']);
                update_post_meta($params['orderId'], 'dpdro_pickup_type', $params['officeType']);
            }

            /** 
             * Order data to insert in database.
             * Insert order data to database.
             */
            $orderData = [
                'order_id'            => $params['orderId'],
                'address'             => $params['address'],
                'address_city_id'     => $params['cityId'],
                'address_city_name'   => $params['cityName'],
                'address_street_id'   => $params['streetId'],
                'address_street_type' => $params['streetType'],
                'address_street_name' => $params['streetName'],
                'address_number'      => $params['number'],
                'address_block'       => $params['block'],
                'address_apartment'   => $params['apartment'],
                'method'              => $params['method'],
                'office_id'           => $params['officeId'],
                'office_name'         => $params['officeName'],
                'status'              => 'validated',
            ];
            if ($params['method'] == 'delivery') {
                $address = '';
                $address .= $params['cityName'] != '' ? $params['cityName'] : '';
                $address .= $params['streetName'] != '' ? ', str. ' . $params['streetName'] : '';
                $address .= $params['number'] != '' ? ', nr. ' . $params['number'] : '';
                $address .= $params['block'] != '' ? ', bl. ' . $params['block'] : '';
                $address .= $params['apartment'] != '' ? ', ap. ' . $params['apartment'] : '';
                $orderData['address'] = $address;
            }
	        if (in_array($order->get_shipping_country(), DPDUtil::getAllowedCountryCodes(), true)) {
                $this->insertOrderAddress($orderData);
            }

            /** 
             * Get an instance of the WC_Order object. 
             * Insert order settings to database.
             */
            $orderSettings = $this->getOrderSettings($params['orderId']);
            if (!$orderSettings && empty($orderSettings)) {
                $order = wc_get_order($params['orderId']);
                $orderSettings = [
                    'order_id'          => $orderData['order_id'],
                    'shipping_tax'      => 'no',
                    'shipping_tax_rate' => $order->get_shipping_total()
                ];
                $this->insertOrderSettings($orderSettings);
            }

            /**
             * Success message.
             */
            $successMessage = __('DPD RO order address and settings has been added successfully.', 'dpdro');
        } else {

            /**
             * Error message.
             */
            $errorMessage = __('Something went wrong.', 'dpdro');
        }

        /**
         * Response HTML.
         */
        ob_start();
        include_once PLUGIN_DIR_DPDRO . 'includes/woo/shipment.php';
        $json['html'] = ob_get_clean();

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax change office.
     */
    public function changeOffice()
    {
        check_ajax_referer('dpdro_change_office', 'nonce');

        /**
         * Response.
         */
        $json = [
            'error' => true
        ];

        if (isset($_POST['action'])) {

            /**
             * Response HTML.
             */
            ob_start();
            include_once PLUGIN_DIR_DPDRO . 'includes/woo/office.php';
            $json['html'] = ob_get_clean();
            $json['error'] = false;
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax save office.
     */
    public function saveOffice()
    {
        check_ajax_referer('dpdro_save_office', 'nonce');

        /**
         * Response.
         */
        $json = [];

        if (isset($_POST['action'])) {
            $params = $_POST['params'];

            /**
             * Update order office.
             */
            update_post_meta($params['orderId'], 'dpdro_pickup', $params['officeId']);
            update_post_meta($params['orderId'], 'dpdro_pickup_name', $params['officeName']);
            update_post_meta($params['orderId'], 'dpdro_pickup_type', $params['officeType']);

            /** 
             * Order data to insert in database.
             * Insert order data to database.
             */
            $orderData = [
                'order_id'    => $params['orderId'],
                'office_id'   => $params['officeId'],
                'office_name' => $params['officeName'],
            ];
            $this->updateOrderOffice($orderData);

            /**
             * Success message.
             */
            $successMessage = __('DPD RO order office has been updated successfully.', 'dpdro');
        } else {

            /**
             * Error message.
             */
            $errorMessage = __('Something went wrong.', 'dpdro');
        }

        /**
         * Response HTML.
         */
        ob_start();
        include_once PLUGIN_DIR_DPDRO . 'includes/woo/office.php';
        $json['html'] = ob_get_clean();

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax add shipping method.
     */
    public function addShippingMethod()
    {
        check_ajax_referer('dpdro_add_shipping_method', 'nonce');

        /**
         * Response.
         */
        $json = [
            'error' => true
        ];

        if (isset($_POST['action'])) {

            /**
             * Response HTML.
             */
            ob_start();
            include_once PLUGIN_DIR_DPDRO . 'includes/woo/shipping.php';
            $json['html'] = ob_get_clean();
            $json['error'] = false;
        } else {
            $json['message'] = __('Something went wrong.', 'dpdro');
        }

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /**
     * Ajax save shipping method.
     */
    public function saveShippingMethod()
    {
        check_ajax_referer('dpdro_save_shipping_method', 'nonce');

        /**
         * Response.
         */
        $json = [];

        if (isset($_POST['action'])) {
            $params = $_POST['params'];

            /**
             * Update order shipping method.
             * Get the the WC_Order Object from an order ID (optional)
             */
            // $order = wc_get_order($params['orderId']);

            // /**
            //  * Array for tax calculations
            //  */
            // $calculateTaxFor = array(
            //     'country'  => $order->get_shipping_country(),
            //     'state'    => $order->get_shipping_state(), // (optional value)
            //     'postcode' => $order->get_shipping_postcode(), // (optional value)
            //     'city'     => $order->get_shipping_city(), // (optional value)
            // );

            // /**
            //  * Initializing
            //  */
            // $changeMethod = false;

            // /**
            //  * Loop through order shipping items
            //  */
            // foreach ($order->get_items('shipping') as $item_id => $item) {

            //     /**
            //      * Retrieve the customer shipping zone
            //      * Get an array of available shipping methods for the current shipping zone
            //      */
            //     $shippingZone = WC_Shipping_Zones::get_zone_by('instance_id', $item->get_instance_id());
            //     $shippingMethods = $shippingZone->get_shipping_methods();

            //     /**
            //      * Loop through available shipping methods
            //      * Targeting specific shipping method
            //      * Set an existing shipping method for customer zone
            //      * Set an existing Shipping method rate ID
            //      * Stop the loop
            //      */
            //     foreach ($shippingMethods as $instanceId => $shippingMethod) {
            //         if ($shippingMethod->is_enabled() && $shippingMethod->id === $params['serviceId']) {
            //             $item->set_method_title($params['serviceName']);
            //             $item->set_method_id($params['serviceId']); // 
            //             $item->set_total($params['serviceTax']);
            //             $item->calculate_taxes($calculateTaxFor);
            //             $item->save();
            //             $changeMethod = true;
            //             break;
            //         }
            //     }
            // }

            // /**
            //  * Calculate totals and save
            //  * The save() method is included
            //  */
            // if ($changeMethod) {
            //     $order->calculate_totals();
            // }

            /** 
             * Insert order shipping to database.
             */
            $orderShipping = [
                'order_id'     => $params['orderId'],
                'method_id'    => $params['serviceId'],
                'method_code'  => $params['serviceCode'],
                'method_title' => $params['serviceName']
            ];
            $this->insertOrderShipping($orderShipping);

            /** 
             * Insert order settings to database.
             */
            $orderSettings = [
                'order_id'          => $params['orderId'],
                'shipping_tax'      => $params['serviceTax'],
                'shipping_tax_rate' => $params['serviceTaxRate']
            ];
            $this->updateOrderSettings($orderSettings);

            /**
             * Success message.
             */
            $successMessage = __('DPD RO order shipping method has been added successfully.', 'dpdro');
        } else {

            /**
             * Error message.
             */
            $errorMessage = __('Something went wrong.', 'dpdro');
        }

        /**
         * Response HTML.
         */
        ob_start();
        include_once PLUGIN_DIR_DPDRO . 'includes/woo/shipping.php';
        $json['html'] = ob_get_clean();

        /**
         * Return response.
         */
        echo wp_send_json($json);
        wp_die();
    }

    /** 
     * Remove diacritics.
     */
    public function removeDiactritics($string)
    {
        if ($string) {
            $keywords = [
                'Ă' => 'A', 'ă' => 'a', 'Â' => 'A', 'â' => 'a', 'Î' => 'I', 'î' => 'i', 'Ș' => 'S', 'ș' => 's', 'Ț' => 'T', 'ț' => 't',
                'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
                'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
                'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
                'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y'
            ];
            $string = strtr($string, $keywords);
            $string = trim($string);
        }
        return $string;
    }

    /** 
     * Notice.
     */
    public function notice()
    {
        $notice = get_transient('dpdro_notice_success');
        if ($notice && !empty($notice)) {
            echo '
                <div class="notice notice-success notice-alt">
                    <p>' . $notice . '</p>
                </div>   
            ';
            delete_transient('dpdro_notice_success');
        }
        $notice = get_transient('dpdro_notice_error');
        if ($notice && !empty($notice)) {
            echo '
                <div class="notice notice-error notice-alt">
                    <p>' . $notice . '</p>
                </div>   
            ';
            delete_transient('dpdro_notice_error');
        }
    }

    /** 
     * Get tax rate by service id.
     */
    public function taxRate($serviceId, $zoneId, $applyOverWeight = 0, $applyOverPrice = 0)
    {
        $query = $this->wpdb->get_results(
            $this->wpdb->prepare("
                (
                    SELECT * 
                    FROM `{$this->wpdb->prefix}order_dpd_tax_rates` 
                    WHERE 
                        `service_id` = " . (int) $serviceId . " AND
                        `zone_id` = " . (int) $zoneId . " AND
                        `apply_over` <= " . $applyOverPrice . " AND
                        status = 1
                    ORDER BY `apply_over` DESC
                ) UNION (
                    SELECT * 
                    FROM `{$this->wpdb->prefix}order_dpd_tax_rates`
                    WHERE 
                        `service_id` = " . (int) $serviceId . " AND
                        `zone_id` = " . (int) $zoneId . " AND
                        `apply_over` <= " . $applyOverWeight . " AND
                        status = 1
                    ORDER BY `apply_over` DESC
                )
            ", array())
        );
        $taxRates = array();
        if (!empty($query)) {
            foreach ($query as $tax) {
                if ($tax->based_on) {
                    if ((float) $tax->apply_over <= (float) $applyOverPrice) {
                        $taxRates[$tax->apply_over] = $tax;
                    }
                } else {
                    if ((float) $tax->apply_over <= (float) $applyOverWeight) {
                        $taxRates[$tax->apply_over] = $tax;
                    }
                }
            }
        }
        arsort($taxRates);
        if (!empty($taxRates)) {
            return reset($taxRates);
        }
        return false;
    }

    /** 
     * Get tax rate office by service id.
     */
    public function taxRateOffice($serviceId, $zoneId, $pickup, $applyOverWeight = 0, $applyOverPrice = 0)
    {
        /** 
         * Data Lists
         */
        $dataLists = new DataLists($this->wpdb);

        /** 
         * Office
         */
        $office = $dataLists->getOfficeById($pickup);
        if ($office && !empty($office->office_site_id)) {
            $query = $this->wpdb->get_results(
                $this->wpdb->prepare("
					(
						SELECT * 
						FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices` 
						WHERE 
							`service_id` = " . (int) $serviceId . " AND
							`zone_id` = " . (int) $zoneId . " AND
							`apply_over` <= " . $applyOverPrice . " AND
							status = 1
						ORDER BY `apply_over` DESC
					) UNION (
						SELECT * 
						FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices`
						WHERE 
							`service_id` = " . (int) $serviceId . " AND
							`zone_id` = " . (int) $zoneId . " AND
							`apply_over` <= " . $applyOverWeight . " AND
							status = 1
						ORDER BY `apply_over` DESC
					)
				", array())
            );
            $taxRates = array();
            if (!empty($query)) {
                foreach ($query as $tax) {
                    if ($tax->based_on) {
                        if ((float) $tax->apply_over <= (float) $applyOverPrice) {
                            $taxRates[$tax->apply_over] = $tax;
                        }
                    } else {
                        if ((float) $tax->apply_over <= (float) $applyOverWeight) {
                            $taxRates[$tax->apply_over] = $tax;
                        }
                    }
                }
            } else {
                $zoneId = '000000001';
                $query = $this->wpdb->get_results(
                    $this->wpdb->prepare(
                        "
						(
							SELECT * 
							FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices` 
							WHERE 
								`service_id` = " . (int) $serviceId . " AND
								`zone_id` = " . (int) $zoneId . " AND
								`apply_over` <= " . $applyOverPrice . " AND
								status = 1
							ORDER BY `apply_over` DESC
						) UNION (
							SELECT * 
							FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices`
							WHERE 
								`service_id` = " . (int) $serviceId . " AND
								`zone_id` = " . (int) $zoneId . " AND
								`apply_over` <= " . $applyOverWeight . " AND
								status = 1
							ORDER BY `apply_over` DESC
						)
						",
                        array()
                    )
                );
                if (!empty($query)) {
                    foreach ($query as $tax) {
                        if ($tax->based_on) {
                            if ((float) $tax->apply_over <= (float) $applyOverPrice) {
                                $taxRates[$tax->apply_over] = $tax;
                            }
                        } else {
                            if ((float) $tax->apply_over <= (float) $applyOverWeight) {
                                $taxRates[$tax->apply_over] = $tax;
                            }
                        }
                    }
                }
            }
            arsort($taxRates);
            if (!empty($taxRates)) {
                return reset($taxRates);
            }
        }
        return false;
    }

	private function arrangeProducts($products)
	{
		$return = [];
		$lastParcel = 0;
		usort($products, fn($a, $b) => $a['parcel'] <=> $b['parcel']);
		$index = 0;
		foreach ($products as $in => $product) {
			if ($in == 0) {
				$lastParcel = $product['parcel'];
				$return[$index] = $product;
				$index ++;
				continue;
			}

			if ($lastParcel == $product['parcel']) {
				$return[$index - 1]['weight'] += $product['weight'];
				continue;
			}

			$return[$index] = $product;
			$lastParcel = $product['parcel'];
			$index ++;
		}
		return $return;
	}
}
