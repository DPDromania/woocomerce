<?php

/**
 * Namespace: includes.
 */

if (!defined('ABSPATH')) {
	exit;
}



/**
 * Global variables.
 */
global $pickup;
global $pickupName;
global $pickupType;
$pickup = false;
$pickupName = __('No office selected', 'dpdro');
$pickupName = false;

class Frontend
{
	/**
	 * Global database.
	 */
	private $wpdb;

	/**
	 * The version of this plugin.
	 */
	private $version;

    /** @var  */
    private $cities;


	/**
	 * Constructor.
	 */
	public function __construct($wpdb)
	{
		$this->wpdb = $wpdb;
		$this->zones = array();
		$this->zoneId = false;
		$this->apply = false;

		/**
		 * Plugin data.
		 */
		if (!function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		$pluginData = get_plugin_data(PLUGIN_DIR_DPDRO . 'dpdro.php');
		$this->version = $pluginData['Version'];

		/**
		 * Init.
		 */
		$this->init();
	}

	/**
	 * Init.
	 */
	function init()
	{
		/**
		 * Shipping.
		 */
		add_filter('woocommerce_form_field_text', array($this, 'checkoutFields'), 10, 2);
        add_filter('woocommerce_cart_shipping_packages', array($this, 'shippingPackages'));
		add_action('woocommerce_checkout_update_order_review', array($this, 'updateOrderReview'));
		add_action('woocommerce_checkout_update_order_meta', array($this, 'updateOrderMeta'));
		add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));

		/**
		 * Payment.
		 */
		add_filter('woocommerce_available_payment_gateways', array($this, 'applySettings'));
		add_action('woocommerce_cart_calculate_fees', array($this, 'checkoutTax'));
		add_action('woocommerce_cart_totals_after_shipping', array($this, 'onRefresh'));
		add_action('woocommerce_review_order_after_shipping', array($this, 'onRefresh'));

		/**
		 * Change position of city field.
		 */
		add_filter('woocommerce_default_address_fields', array($this, 'changeCityFieldPosition'));

    }

	/**
	 * Get DPD RO settings.
	 */
	private function getSettings()
	{
		/** 
		 * Data settings
		 */
		$settings = new DataSettings($this->wpdb);
		return $settings->getSettings();
	}

	/**
	 * DPD RO offices map.
	 */
	public function checkoutFields($field, $key)
	{
		$settings = $this->getSettings();
		if ($settings['show_office_selection']) {
			if (is_checkout() && $key == 'billing_postcode') {
				global $pickup;
				global $pickupName;
				global $pickupType;
				$field .= '
					<p class="form-row address-field form-row-wide dpdro-offices-map js-dpdro-offices-map" id="billing_pickup_field" data-priority="70">
						<label for="billing_pickup">' . __('DPD RO offices map', 'dpdro') . '</label>
						<input type="hidden" name="billing_pickup" id="billing_pickup" value="' . $pickup . '" />
						<input type="hidden" name="shipping_pickup" id="shipping_pickup" value="' . $pickup . '" />
						<span class="woocommerce-input-wrapper">
							<input type="hidden" class="js-dpdro-offices-type" name="billing_pickup_type" id="billing_pickup_type" value="' . $pickupType  . '" />
							<input type="text" class="input-text js-dpdro-offices-name" name="billing_pickup_name" id="" placeholder="' . __('No office selected') . '" value="' . $pickupName  . '" disabled />
						</span>
						<iframe style="margin-top: 10px;" id="frameOfficeLocator" name="frameOfficeLocator" src="https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&selectOfficeButtonCaption=Select this office" width="800px" height="300px" ></iframe>
					</p>
				';
			}
		}
		return $field;
	}

	/**
	 * Shipping packages.
	 */
	public function shippingPackages($packages)
	{
		global $pickup;
		global $pickupName;
		global $pickupType;
		if ($pickup && !empty($pickup)) {
			$packages[0]['dpdro_pickup'] = $pickup;
		}
		if ($pickupName && !empty($pickupName)) {
			$packages[0]['dpdro_pickup_name'] = $pickupName;
		}
		if ($pickupType && !empty($pickupType)) {
			$packages[0]['dpdro_pickup_type'] = $pickupType;
		}
		return $packages;
	}

	/**
	 * Update order review.
	 */
	public function updateOrderReview($orderData)
	{
		global $pickup;
		global $pickupName;
		global $pickupType;
		$parsedUrl = array();
		parse_str(html_entity_decode($orderData), $parsedUrl);
		if (isset($parsedUrl['ship_to_different_address'])) {
			if (isset($parsedUrl['shipping_pickup'])) {
				$pickup = $parsedUrl['shipping_pickup'];
			}
		} else {
			if (isset($parsedUrl['billing_pickup'])) {
				$pickup = $parsedUrl['billing_pickup'];
			}
		}
		if (isset($parsedUrl['billing_pickup_name'])) {
			$pickupName = $parsedUrl['billing_pickup_name'];
		}
		if (isset($parsedUrl['billing_pickup_type'])) {
			$pickupType = $parsedUrl['billing_pickup_type'];
		}
		WC()->session->set('dpdro_office_id', $pickup);
		WC()->session->set('dpdro_office_name', $pickupName);
		WC()->session->set('dpdro_office_type', $pickupType);
	}

	/**
	 * Update order meta.
	 */
	public function updateOrderMeta($order_id)
	{
		$pickup = false;
		$pickupName = __('No office selected', 'dpdro');
		$pickupType = false;
		if (isset($_POST['ship_to_different_address']) && !empty($_POST['ship_to_different_address'])) {
			if (isset($_POST['shipping_pickup']) && !empty($_POST['shipping_pickup'])) {
				$pickup = $_POST['shipping_pickup'];
			}
		} else {
			if (isset($_POST['billing_pickup']) && !empty($_POST['billing_pickup'])) {
				$pickup = $_POST['billing_pickup'];
			}
		}
		if (isset($_POST['billing_pickup_name']) && !empty($_POST['billing_pickup_name'])) {
			$pickupName = $_POST['billing_pickup_name'];
		}
		if (isset($_POST['billing_pickup_type']) && !empty($_POST['billing_pickup_type'])) {
			$pickupType = $_POST['billing_pickup_type'];
		}
		update_post_meta($order_id, 'dpdro_pickup', $pickup);
		update_post_meta($order_id, 'dpdro_pickup_name', $pickupName);
		update_post_meta($order_id, 'dpdro_pickup_type', $pickupType);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 */
	public function enqueueScripts()
	{
		if (function_exists('is_checkout') && is_checkout()) {

			/**
			 * Check if WooCommerce is activated
			 */
			if (class_exists('woocommerce')) {
				wp_enqueue_script('dpdro-script', plugin_dir_url(__FILE__) . '../assets/public/js/custom.js', array('jquery'), $this->version, true);
				wp_localize_script('dpdro-script', 'dpdRo', array('ajaxurl' => admin_url('admin-ajax.php')));

				/** 
				 * Data
				 */
				$data = array(
					'textNoOfficeSelected' => __('No office selected', 'dpdro'),
					'noneSearchCity'       => wp_create_nonce('dpdro_search_city'),
				);
				wp_localize_script('dpdro-script', 'dpdRoGeneral', $data);
			}
		}

        $settings = $this->getSettings();
        if ($settings['city_dropdown']&& (is_cart() || is_checkout() || is_wc_endpoint_url('edit-address'))) {
            wp_enqueue_script('dpd-city-select', plugin_dir_url(__FILE__)  . '../assets/public/js/city-select.js', ['jquery', 'woocommerce'], $this->version, true);

            wp_localize_script('dpd-city-select', 'dpd_wc_city_select_params', [
                'cities' => $this->getCities(),
                'i18n_select_city_text' => esc_attr__('Select an option&hellip;', 'woocommerce'),
            ]);
        }
	}

	/**
	 * Get DPD RO zones.
	 */
	private function getZones()
	{
		if (!empty($this->zones)) {
			return $this->zones;
		}
		$settings = $this->getSettings();
		$zones = [];
		if (isset($settings['payment_zones']) && !empty($settings['payment_zones'])) {
			$zones = json_decode(str_replace("\\", "", $settings['payment_zones']));
		}
		return $this->zones = $zones;
	}

	/**
	 * Get the customer shipping zone id.
	 */
	private function getZoneId()
	{
		$package = array(
			'destination' => array(
				'country'  => WC()->customer->get_shipping_country(),
				'state'    => WC()->customer->get_shipping_state(),
				'postcode' => WC()->customer->get_shipping_postcode()
			)
		);
		$zone = WC_Shipping_Zones::get_zone_matching_package($package);
		$zoneId = $zone->get_zone_id();
		if (!isset($zoneId) || (empty($zoneId) && $zoneId !== 0)) {
			return $this->zoneId;
		}
		return $this->zoneId = $zoneId;
	}

	/**
	 * Get the customer shipping zone name.
	 */
	private function getTaxByZone()
	{
		if ($this->checkApply()) {
			$paymentZones = self::getZones();
			$zoneId = self::getZoneId();
			foreach ($paymentZones as $paymentZone) {
				if ($paymentZone->id == $zoneId && $paymentZone->status && $paymentZone->status == '1') {
					return $paymentZone;
				}
			}
		}
		return false;
	}

	/**
	 * Check if DPD RO payment tax available.
	 */
	private function checkApply()
	{
		if (!empty($this->apply)) {
			return $this->apply;
		}
		if ($this->checkCountry(WC()->customer->get_shipping_country())) {
			$chosenGateway = WC()->session->get('chosen_shipping_methods');
			if (strpos($chosenGateway[0], 'shipping_dpd') !== false || strpos($chosenGateway[0], 'dpdro_shipping') !== false) {
				$zoneId = $this->getZoneId();
				if ($zoneId || $zoneId == 0) {
					$paymentZones = $this->getZones();
					if ($paymentZones || !empty($paymentZones)) {
						foreach ($paymentZones as $paymentZone) {
							if ($paymentZone->id == $zoneId && $paymentZone->status && $paymentZone->status == '1') {
								return $this->apply = true;
							}
						}
					}
				}
			}
		}
		return $this->apply = false;
	}

	/**
	 * Refresh payment tax when address is changed.
	 */
	public function onRefresh()
	{
		if ($this->checkApply()) {
			$chosenGateway = WC()->session->get('chosen_payment_method');
			if ($chosenGateway == 'cod') {
				$taxName = __('Cash on delivery DPD RO', 'dpdro');
				$taxRate = $this->getTaxByZone();
				if ($taxRate && $taxRate->status) {
					if ($taxRate->type == 'custom') {
						$tax = (float) $taxRate->tax_rate;
						$vat = (float) $taxRate->vat_rate;
						$fullTax = 0;
						if ($tax > 0) {
							$fullTax = $fullTax + floatval($tax);
						}
						if ($tax > 0) {
							$fullTax = $fullTax + ($fullTax * floatval($vat) / 100);
						}
						if ($fullTax > 0) {
							WC()->cart->add_fee($taxName, $fullTax);
						}
					} else {
						$settings = $this->getSettings();
						$taxFee = $settings['payment_tax'];
						if ($taxFee > 0) {
							WC()->cart->add_fee($taxName, $taxFee);
						}
					}
				}
			}
		}
	}

	/**
	 * Add or removal tax payment gateway.
	 */
	public function checkoutTax()
	{
		if ($this->checkApply()) {
			$chosenGateway = WC()->session->get('chosen_payment_method');
			if ($chosenGateway == 'cod') {
				$taxName = __('Cash on delivery DPD RO', 'dpdro');
				$taxRate = $this->getTaxByZone();
				if ($taxRate && $taxRate->status) {
					if ($taxRate->type == 'custom') {
						$tax = (float) $taxRate->tax_rate;
						$vat = (float) $taxRate->vat_rate;
						$fullTax = 0;
						if ($tax > 0) {
							$fullTax = $fullTax + floatval($tax);
						}
						if ($tax > 0) {
							$fullTax = $fullTax + ($fullTax * floatval($vat) / 100);
						}
						if ($fullTax > 0) {
							WC()->cart->add_fee($taxName, $fullTax);
						}
					} else {
						$settings = $this->getSettings();
						$taxFee = $settings['payment_tax'];
						if ($taxFee > 0) {
							WC()->cart->add_fee($taxName, $taxFee);
						}
					}
				}
			}
		}
	}

	/**
	 * Apply DPD RO payment settings.
	 */
	public function applySettings($availableGateways)
	{
		if (!function_exists('is_checkout') || !is_checkout() && !is_wc_endpoint_url('order-pay')) {
			return $availableGateways;
		}
		$this->checkoutTax();
		return $availableGateways;
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

	/** 
	 * Change city field position.
	 */
	public function changeCityFieldPosition($fields)
	{
		$settings = $this->getSettings();
		if ($settings['county_before_city']) {
			$fields['state']['priority'] = 61;
		}
        return $fields;
	}

    function getCities($cc = null)
    {
        global $wpdb;

        switch (get_option('woocommerce_currency')) {
            case 'RON';
                $countryId = 642;
                $countryCode = 'RO';
                break;
            case 'лв.':
                $countryId = 100;
                $countryCode = 'BG';
                break;
            case '€':
                $countryId = 300;
                $countryCode = 'GR';
                break;
            default:
                $countryId = 642;
                $countryCode = 'RO';
        }


        if (empty($this->cities)) {
            $sql = "select * from ".$wpdb->prefix . "dpdro_cities where country_id = $countryId ";

            $cities_ro = [];
            $result =   $wpdb->get_results($sql );
            foreach ($result as $item) {
                $cities_ro[$item->postal_code] = $item;
            }
            $cities = [];
            $allowed = array_merge(WC()->countries->get_allowed_countries(), WC()->countries->get_shipping_countries());
            if ($allowed) {
                foreach ($allowed as $code => $country) {
                    if (file_exists(PLUGIN_DIR_DPDRO  . '/library/cities/' . $code . '.php')) {
                        if ($code !== 'RO') {
                            $cities = array_merge($cities, include(PLUGIN_DIR_DPDRO . '/library/cities/' . $code . '.php'));
                        } else {
                            $included_city = include(PLUGIN_DIR_DPDRO . '/library/cities/' . $code . '.php');
                            $cleaned_ro_cities = [];
                            foreach ($included_city['RO'] as $abbr => $_cities) {
                                foreach ($_cities as $_city) {
                                   if (isset($cities_ro[$_city[1]])) {
                                       $cleaned_ro_cities['RO'][$abbr][] = [
                                           $cities_ro[$_city[1]]->name, $_city[1]
                                       ];
                                       unset($cities_ro[$_city[1]]);
                                   } else {
                                       $cleaned_ro_cities['RO'][$abbr][] = $_city;
                                   }
                                }
                            }
                            if (count($cities_ro) > 0) {
                                $states = array_flip(WC()->countries->get_states( 'RO' ));
                                $address = new DataAddresses($wpdb);
                                $newStates = [];
                                foreach ($states as $name => $abbr) {
                                    $name = strtoupper($address->removeDiactritics($name));
                                    $newStates[$name] = $abbr;
                                }

                                foreach ($cities_ro as $code => $city) {
                                    if (isset($newStates[$city->region])) {
                                        $cities['RO'][$newStates[$city->region]][] = [$city->name, $code];
                                    }
                                }
                            }
                            $cities = array_merge($cities, $cleaned_ro_cities);
                        }
                    }
                }
            }
            $this->cities = apply_filters('dpd_wc_city_select_cities', $cities);
        }

        if (!is_null($cc)) {
            return isset($this->cities[$cc]) ? $this->cities[$cc] : false;
        } else {
            return $this->cities;
        }
    }


}
