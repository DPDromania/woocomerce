<?php

/**
 * Namespace: includes/woo.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPDRO_Service_Gateway_ModelServiceID extends WC_Shipping_Method
{
    /**
     * Service data.
     */
    private $serviceId;
    private $serviceName;

    /**
     * Constructor.
     */
    function __construct($instance_id = 0)
    {
        $this->serviceId   = 'ModelServiceID';
        $this->serviceName = 'ModelServiceName';

        $this->id                 = 'dpdro_shipping_' . $this->serviceId;
        $this->instance_id        = absint($instance_id);
        $this->method_title       = $this->serviceName;
        $this->method_description = __('Allows shipping with DPD RO.', 'dpdro');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        /**
         * Init.
         */
        $this->init();
    }

    /**
     * Init user set variables.
     */
    function init()
    {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __('Method title', 'dpdro'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'dpdro'),
                'desc_tip'    => true,
                'default'     => $this->serviceName,
                'placeholder' => $this->serviceName,
            ),
            'tax_status' => array(
                'title'   => __('Tax status', 'dpdro'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __('Taxable', 'dpdro'),
                    'none'    => _x('None', 'Tax status', 'dpdro')
                )
            ),
            'cost' => array(
                'title'       => __('Cost', 'dpdro'),
                'type'        => 'text',
                'placeholder' => 'auto',
                'desc_tip'    => __('Enter a cost (excl. tax) or sum, e.g. 10.00 or let 0 to take DPD RO service price.', 'dpdro')
            )
        );
        $this->title                = $this->get_option('title') != '' ? $this->get_option('title') : $this->serviceName;
        $this->tax_status           = $this->get_option('tax_status');
        $this->cost                 = $this->get_option('cost');
        $this->enabled              = $this->get_option('enabled');
        $this->init_settings();
    }

    /**
     * Calculate_shipping function.
     * @param array $package (default: array())
     */
    public function calculate_shipping($package = array())
    {
        if ($this->enabled == 'yes') {
            /**
             * Global database.
             */
            global $wpdb;

            /** 
             * Data settings.
             */
            $settings = new DataSettings($wpdb);
            $dataSettings = $settings->getSettings();
            $dataSettings['dpdro_pickup'] = isset($package['dpdro_pickup']) && !empty($package['dpdro_pickup']) ? $package['dpdro_pickup'] : false;
            $dataSettings['dpdro_pickup_name'] = isset($package['dpdro_pickup_name']) && !empty($package['dpdro_pickup_name']) ? $package['dpdro_pickup_name'] : false;
            $dataSettings['dpdro_pickup_type'] = isset($package['dpdro_pickup_type']) && !empty($package['dpdro_pickup_type']) ? $package['dpdro_pickup_type'] : false;
            if (!$dataSettings['dpdro_pickup']) {
                $pickup = WC()->session->get('dpdro_office_id');
                if (isset($pickup) && !empty($pickup)) {
                    $dataSettings['dpdro_pickup'] = $pickup;
                }
            }
            if (!$dataSettings['dpdro_pickup_name']) {
                $pickupName = WC()->session->get('dpdro_office_name');
                if (isset($pickupName) && !empty($pickupName)) {
                    $dataSettings['dpdro_pickup_name'] = $pickupName;
                }
            }
            if (!$dataSettings['dpdro_pickup_type']) {
                $pickupType = WC()->session->get('dpdro_office_type');
                if (isset($pickupType) && !empty($pickupType)) {
                    $dataSettings['dpdro_pickup_type'] = $pickupType;
                }
            }
            $dataSettings['chosen_payment'] = WC()->session->get('chosen_payment_method');
            $dataSettings['contents_cost'] = $package['contents_cost'];
            $dataSettings['package'] = [
                'country'  => $package['destination']['country'],
                'state'    => $package['destination']['state'],
                'city'     => $package['destination']['city'],
                'postcode' => $package['destination']['postcode'],
            ];

            /** 
             * User.
             */
            $dataSettings['customer_phone'] = false;
            $dataSettings['customer_email'] = false;
            if (isset($package['user']['ID']) && $package['user']['ID'] !== 0) {
                $dataSettings['customer_phone'] = get_user_meta($package['user']['ID'], 'billing_phone', true);
                $dataSettings['customer_email'] = get_user_meta($package['user']['ID'], 'billing_email', true);
            }

            /** 
             * WooCommerce api.
             */
            $wooApi = new WooApi($wpdb, $package, $dataSettings);
            $dataSettings['total_weight'] = $wooApi->totalWeight();
            $dataSettings['parcels'] = $wooApi->prepareParcels($this->serviceId);

            /** 
             * Payment.
             */
            $dataSettings['cod'] = false;
            if (isset($dataSettings['chosen_payment']) && $dataSettings['chosen_payment'] === 'cod') {

                /** 
                 * Data zones.
                 */
                $dataSettings['cod'] = DataZones::zoneMatchingPackage($package, $settings);
            }

            /** 
             * Data settings.
             */
            $addresses = new DataAddresses($wpdb);

            /** 
             * Library api.
             */
            $dpdApi = new LibraryApi($dataSettings['username'], $dataSettings['password']);
            $serviceTax = $dpdApi->calculate($this->serviceId, $dataSettings, $addresses);
            if ($serviceTax && !isset($serviceTax['error'])) {
                $taxService = (float) $serviceTax['price']['total'];
                if ($this->checkCountry($package['destination']['country'])) {
                    if ($dataSettings['cod'] && DataZones::checkCustomPayment($package, $settings)) {
                        $taxService = $taxService - (float) $dataSettings['payment_tax'];
                    }
                }
                $taxServiceRate = 'no';
                if ($dataSettings['courier_service_payer'] == 'RECIPIENT') {

                    /** 
                     * Recipient pay the tax.
                     */
                } else {
                    if (!empty($this->cost)) {
                        $taxService = (float) $this->cost;
                        $taxServiceRate = 'yes';
                    } else {
                        $taxRate = $wooApi->taxRate($this->serviceId, $package);
                        if (isset($package['dpdro_pickup']) && !empty($package['dpdro_pickup'])) {
                            $taxRate = $wooApi->taxRateOffice($this->serviceId, $package, $package['dpdro_pickup']);
                        } else {
                            $pickup = WC()->session->get('dpdro_office_id');
                            if (isset($pickup) && !empty($pickup)) {
                                $taxRate = $wooApi->taxRateOffice($this->serviceId, $package, $pickup);
                            }
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

                /** 
                 * Set values tax and tax rate on session.
                 */
                WC()->session->set('dpdro_shipping_tax_' . $this->serviceId, $taxService);
                WC()->session->set('dpdro_shipping_tax_rate_' . $this->serviceId, $taxServiceRate);

                /** 
                 * Add tax to serrvice.
                 */
                $this->add_rate(array(
                    'id'    => $this->id . $this->instance_id,
                    'label' => $this->get_option('title') != '' ? $this->get_option('title') : $this->serviceName,
                    'cost'  => $taxService
                ));
            }
        }
    }

    /** 
     * Check country.
     */
    public function checkCountry($code = false)
    {
        if ($code) {
            if (
                $code === 'RO' || // Romania  -> ID WOO
                $code === 'BG' || // Bulgaria -> ID WOO
                $code === 'GR' || // Grecia   -> ID WOO
                $code === 'HU' || // Ungaria  -> ID WOO
                $code === 'SK' || // Slovakia -> ID WOO
                $code === 'PL'    // Polonia  -> ID WOO
            ) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
}
