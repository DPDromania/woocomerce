<?php

/**
 * Namespace: includes.
 */

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

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

	private $zones;


	private $zoneId;

	private $apply;

	private $initialized = false;

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
		if ($this->initialized) {
			return;
		}


		/**
		 * Shipping.
		 */
		add_filter('woocommerce_form_field_text', array($this, 'checkoutFields'), 10, 2);
        add_filter('woocommerce_cart_shipping_packages', array($this, 'shippingPackages'));

        add_action('woocommerce_checkout_update_order_review', array($this, 'updateOrderReview'));
		add_action('woocommerce_checkout_update_order_meta', array($this, 'updateOrderMeta'));

        add_action('woocommerce_checkout_update_order_review', function($posted_data) {
            parse_str($posted_data, $data);
            if (!empty($data['payment_method'])) {
                WC()->session->set('chosen_payment_method', wc_clean($data['payment_method']));
            }
        });

		add_action( 'wp_enqueue_scripts', function () {
			if ( ! is_checkout() ) return;

			// Only enqueue classic assets if NOT block checkout.
			if ( $this->my_is_block_checkout_default() || $this->my_is_block_checkout_by_page_content() ) {
				return;
			}

			$this->enqueueScripts();
		}, 20 );



		//blocks
		add_action( 'enqueue_block_assets', function () {
			// Only enqueue block assets if block checkout is active.
			if ( ! ( $this->my_is_block_checkout_default() || $this->my_is_block_checkout_by_page_content() ) ) {
				return;
			}

            $settings = $this->getSettings();

            // Hide DPD pickup fields in blocks checkout
            $hide_css = '
                .wc-block-components-address-form__dpdro-pickup_id,
                .wc-block-components-address-form__dpdro-pickup_type,
                .wc-block-components-address-form__dpdro-pickup_name {
                    display: none !important;
                }
                .dpdro-offices-map {
                    grid-column: 1 / -1 !important;
                    width: 100% !important;
                }
                .dpdro-offices-map iframe {
                    width: 100% !important;
                }
            ';

            // Also hide postcode when city dropdown is enabled (postcode auto-fills)
			/*
            if ($settings['city_dropdown'] === '1') {
                $hide_css .= '
                    .wc-block-components-address-form__postcode {
                        display: none !important;
                    }
                ';
            }

            wp_add_inline_style( 'wp-block-library', $hide_css );
			*/

			$this->enqueueScripts();
		}, 20 );
		add_action( 'woocommerce_init', function () {
			if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
				return;
			}

			// Saved for both shipping & billing (address location).
			woocommerce_register_additional_checkout_field( [
				'id'         => 'dpdro/pickup_id',
				'label'      => __( 'DPD RO office ID', 'dpdro' ),
				'location'   => 'address',
				'type'       => 'text',
				'required'   => false,
				'attributes' => [ 'data-dpdro' => 'pickup-id', 'style' => 'display:none' ],
			] );


			woocommerce_register_additional_checkout_field( [
				'id'         => 'dpdro/pickup_type',
				'label'      => __( 'DPD RO office type', 'dpdro' ),
				'location'   => 'address',
				'type'       => 'text',
				'required'   => false,
				'attributes' => [ 'data-dpdro' => 'pickup-type', 'style' => 'display:none' ],
			] );

			woocommerce_register_additional_checkout_field( [
				'id'         => 'dpdro/pickup_name',
				'label'      => __( 'DPD RO offices map', 'dpdro' ),
				'location'   => 'address',
				'type'       => 'text',
				'required'   => false,
				'attributes' => [
					'data-dpdro'  => 'pickup-name',
					'readonly'    => 'readonly',
					'placeholder' => __( 'No office selected', 'dpdro' ),
					'class'       => 'input-text js-dpdro-offices-name',
				],
			] );
		} );

        add_action('woocommerce_blocks_loaded', function () {
            woocommerce_store_api_register_update_callback([
                'namespace' => 'dpdro',
                'callback' => function ($data) {
                    // Invalidate cached shipping rates so calculate_shipping() is called on each method
                    $packages = WC()->cart->get_shipping_packages();
                    foreach ($packages as $package_key => $package) {
                        $session_key = 'shipping_for_package_' . $package_key;
                        WC()->session->set($session_key, false);
                    }
                    WC()->cart->calculate_shipping();
                    WC()->cart->calculate_totals();
                },
            ]);
        });
		//equivalent for updateOrderReview when using blocks
        $update_customer_callback = function( $customer, WP_REST_Request $request ) {
			$p = (array) $request->get_params();

			// Normalize address fields from either shipping_address or shippingAddress payload shapes.
			$shipping_country  = $this->dpdro_extract_country_from_store_api( $p, 'shipping' );
			$billing_country   = $this->dpdro_extract_country_from_store_api( $p, 'billing' );
			$shipping_postcode = $this->dpdro_extract_postcode_from_store_api( $p, 'shipping' );
			$billing_postcode  = $this->dpdro_extract_postcode_from_store_api( $p, 'billing' );
			$shipping_state    = $this->dpdro_extract_state_from_store_api( $p, 'shipping' );
            $shipping_city     = $this->dpdro_extract_city_from_store_api( $p, 'shipping' );
            $billing_city      = $this->dpdro_extract_city_from_store_api( $p, 'billing' );
			$billing_state     = $this->dpdro_extract_state_from_store_api( $p, 'billing' );
            $shipping_address  = $this->dpdro_extract_address_1_from_store_api( $p, 'shipping' );
            $billing_address   = $this->dpdro_extract_address_1_from_store_api( $p, 'billing' );


            if ( $billing_postcode === '' && $shipping_postcode !== '' ) {
				$billing_postcode = $shipping_postcode;
			}
			if ( $billing_state === '' && $shipping_state !== '' ) {
				$billing_state = $shipping_state;
			}

            if ( $billing_city === '' && $shipping_city !== '' ) {
                $billing_city = $shipping_city;
            }
            if ( $billing_address === '' && $shipping_address !== '' ) {
                $billing_address = $shipping_address;
            }
			if ( $shipping_country !== '' ) {
				if ( is_object( $customer ) && method_exists( $customer, 'set_shipping_country' ) ) {
					$customer->set_shipping_country( $shipping_country );
				}
				if ( WC()->customer && method_exists( WC()->customer, 'set_shipping_country' ) ) {
					WC()->customer->set_shipping_country( $shipping_country );
				}
			}

			if ( $billing_country !== '' ) {
				if ( is_object( $customer ) && method_exists( $customer, 'set_billing_country' ) ) {
					$customer->set_billing_country( $billing_country );
				}
				if ( WC()->customer && method_exists( WC()->customer, 'set_billing_country' ) ) {
					WC()->customer->set_billing_country( $billing_country );
				}
			}

			if ( $shipping_postcode !== '' ) {
				if ( is_object( $customer ) && method_exists( $customer, 'set_shipping_postcode' ) ) {
					$customer->set_shipping_postcode( $shipping_postcode );
				}
				if ( WC()->customer && method_exists( WC()->customer, 'set_shipping_postcode' ) ) {
					WC()->customer->set_shipping_postcode( $shipping_postcode );
				}
			}

			if ( $billing_postcode !== '' ) {
				if ( is_object( $customer ) && method_exists( $customer, 'set_billing_postcode' ) ) {
					$customer->set_billing_postcode( $billing_postcode );
				}
				if ( WC()->customer && method_exists( WC()->customer, 'set_billing_postcode' ) ) {
					WC()->customer->set_billing_postcode( $billing_postcode );
				}
			}

			if ( $shipping_state !== '' ) {
				if ( is_object( $customer ) && method_exists( $customer, 'set_shipping_state' ) ) {
					$customer->set_shipping_state( $shipping_state );
				}
				if ( WC()->customer && method_exists( WC()->customer, 'set_shipping_state' ) ) {
					WC()->customer->set_shipping_state( $shipping_state );
				}
			}

            if ( $billing_state !== '' ) {
                if ( is_object( $customer ) && method_exists( $customer, 'set_billing_state' ) ) {
                    $customer->set_billing_state( $billing_state );
                }
                if ( WC()->customer && method_exists( WC()->customer, 'set_billing_state' ) ) {
                    WC()->customer->set_billing_state( $billing_state );
                }
            }

			if ( $billing_city !== '' ) {
				if ( is_object( $customer ) && method_exists( $customer, 'set_billing_city' ) ) {
					$customer->set_billing_city( $billing_city );
				}
				if ( WC()->customer && method_exists( WC()->customer, 'set_billing_city' ) ) {
					WC()->customer->set_billing_city( $billing_city );
				}
			}

            if ( $shipping_city !== '' ) {
                if ( is_object( $customer ) && method_exists( $customer, 'set_shipping_city' ) ) {
                    $customer->set_shipping_city( $shipping_city );
                }
                if ( WC()->customer && method_exists( WC()->customer, 'set_shipping_city' ) ) {
                    WC()->customer->set_shipping_city( $shipping_city );
                }
            }

            if ( $shipping_address !== '' ) {
                if ( is_object( $customer ) && method_exists( $customer, 'set_shipping_address_1' ) ) {
                    $customer->set_shipping_address_1( $shipping_address );
                }
                if ( WC()->customer && method_exists( WC()->customer, 'set_shipping_address_1' ) ) {
                    WC()->customer->set_shipping_address_1( $shipping_address );
                }
            }

            if ( $billing_address !== '' ) {
                if ( is_object( $customer ) && method_exists( $customer, 'set_billing_address_1' ) ) {
                    $customer->set_billing_address_1( $billing_address );
                }
                if ( WC()->customer && method_exists( WC()->customer, 'set_billing_address_1' ) ) {
                    WC()->customer->set_billing_address_1( $billing_address );
                }
            }


			// Keep session customer array aligned, as several Woo internals still read it directly.
			if ( WC()->session ) {
				$session_customer = WC()->session->get( 'customer' );
				if ( ! is_array( $session_customer ) ) {
					$session_customer = [];
				}
				if ( $shipping_country !== '' ) {
					$session_customer['shipping_country'] = $shipping_country;
				}
				if ( $billing_country !== '' ) {
					$session_customer['billing_country'] = $billing_country;
					$session_customer['country'] = $billing_country;
				}
				if ( $shipping_postcode !== '' ) {
					$session_customer['shipping_postcode'] = $shipping_postcode;
				}
				if ( $billing_postcode !== '' ) {
					$session_customer['billing_postcode'] = $billing_postcode;
					$session_customer['postcode'] = $billing_postcode;
				}
				if ( $shipping_state !== '' ) {
					$session_customer['shipping_state'] = $shipping_state;
				}
				if ( $billing_state !== '' ) {
					$session_customer['billing_state'] = $billing_state;
					$session_customer['state'] = $billing_state;
				}
                if ( $shipping_address !== '' ) {
                    $session_customer['shipping_address_1'] = $shipping_address;
                }
                if ( $billing_address !== '' ) {
                    $session_customer['billing_address_1'] = $billing_address;
                    $session_customer['address_1'] = $billing_address;
                }
				WC()->session->set( 'customer', $session_customer );
			}
		};

		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', $update_customer_callback, 10, 2 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', $update_customer_callback, 10, 2 );


		// AJAX handler to update DPD session data (since Store API strips custom fields)
		add_action( 'wp_ajax_dpdro_update_session', array( $this, 'ajax_update_session' ) );
		add_action( 'wp_ajax_nopriv_dpdro_update_session', array( $this, 'ajax_update_session' ) );


		/**
		 * Payment.
		 */
		add_filter('woocommerce_available_payment_gateways', array($this, 'applySettings'));
		add_action('woocommerce_cart_calculate_fees', array($this, 'checkoutTax'));
		add_action('woocommerce_cart_totals_after_shipping', array($this, 'onRefresh'));
		add_action('woocommerce_review_order_after_shipping', array($this, 'onRefresh'));

		/**
		 * Cash on delivery is not allowed for DPD office/locker delivery.
		 * Block the order at placement with a clear error (classic + Block checkout).
		 */
		add_action('woocommerce_checkout_process', array($this, 'validateCodPickup'));
		add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'validateCodPickupStoreApi'), 10, 2);

		/**
		 * Change position of city field.
		 */
		add_filter('woocommerce_default_address_fields', array($this, 'changeCityFieldPosition'));

		$this->initialized = true;

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
							<input type="text" class="input-text js-dpdro-offices-name" name="billing_pickup_name" id="" placeholder="' . __('No office selected') . '" value="' . $pickupName  . '" readonly />
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
                $settings = $this->getSettings();



                if ($settings['show_office_selection']  == "1") {
                    wp_enqueue_script( 'dpdro-script', plugin_dir_url( __FILE__ ) . '../assets/public/js/custom.js', array( 'jquery' ), $this->version, true );
                    wp_enqueue_script('dpdro-checkout-blocks', plugin_dir_url(__FILE__) . '../assets/public/js/dpdro-checkout-address.js', array('jquery'), $this->version, true);
                    wp_localize_script('dpdro-checkout-blocks', 'dpdroData', [
                        'pickup' => $GLOBALS['pickup'] ?? '',
                        'pickupName' => $GLOBALS['pickupName'] ?? '',
                        'pickupType' => $GLOBALS['pickupType'] ?? '',
                        'iframeSrc' => 'https://services.dpd.ro/office_locator_widget_v3/office_locator.php?lang=en&showAddressForm=0&showOfficesList=0&selectOfficeButtonCaption=Select this office',
                        'label' => __('DPD RO offices map', 'dpdro'),
                        'noOffice' => __('No office selected', 'dpdro'),
                    ]);
                } else {
                    wp_enqueue_script( 'dpdro-script', plugin_dir_url( __FILE__ ) . '../assets/public/js/dpd.js', array( 'jquery' ), $this->version, true );
                }
				/** 
				 * Data
				 */
                $data = array(
                    'textNoOfficeSelected' => __('No office selected', 'dpdro'),
                    'noneSearchCity'       => wp_create_nonce('dpdro_search_city'),
                    'mapEnabled' => $settings['show_office_selection']
                );
                wp_localize_script('dpdro-script', 'dpdRoGeneral', $data);
                wp_localize_script( 'dpdro-script', 'dpdRo', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );

				if ($settings['city_dropdown']&& (is_cart() || is_checkout() || is_wc_endpoint_url('edit-address'))) {
					wp_enqueue_script('dpd-city-select', plugin_dir_url(__FILE__)  . '../assets/public/js/city-select.js', ['jquery', 'woocommerce'], $this->version, true);

					wp_localize_script('dpd-city-select', 'dpd_wc_city_select_params', [
						'cities' => $this->getCities(),
						'i18n_select_city_text' => esc_attr__('Select an option&hellip;', 'woocommerce'),
					]);
				}
			}
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

	function applyTax($value)
	{
		if ( get_option( 'woocommerce_prices_include_tax') != 'no') {
			return $value;
		}

		$taxes = $this->getTaxByZone();
	}

	/**
	 * Check if DPD RO payment tax available.
	 */
	private function checkApply()
	{
		if (!empty($this->apply)) {
			return $this->apply;
		}

		$country = "";
		if ( function_exists('WC') && WC() ) {
			if ( WC()->customer ) {
				$country = WC()->customer->get_shipping_country();
			} elseif ( WC()->session ) {
				// Try reading from the session customer array (works often in blocks)
				$customer = WC()->session->get( 'customer' );
				if ( is_array( $customer ) ) {
					$country = $customer['shipping_country'] ?? ( $customer['country'] ?? '' );
				}
			}
		}

		if ($this->checkCountry($country)) {
			$chosenGateway = WC()->session->get('chosen_shipping_methods');
			if (
				isset($chosenGateway[0]) &&
				(
					strpos($chosenGateway[0], 'shipping_dpd') !== false ||
					strpos($chosenGateway[0], 'dpdro_shipping') !== false)
			) {
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
	 * Whether COD must be blocked for the given pickup point name.
	 * Only PAYPOINT pickup points (offices/lockers whose name contains "PAYPOINT")
	 * do not accept cash on delivery.
	 */
	private function isCodBlockedPickup($pickupName)
	{
		return is_string($pickupName) && stripos($pickupName, 'PAYPOINT') !== false;
	}

	/**
	 * Resolve the pickup point name. Prefer the supplied name; if it is missing
	 * (e.g. classic checkout where the disabled field is not submitted), look it
	 * up from the DPD offices table by its id.
	 */
	private function resolvePickupName($pickupId, $pickupName = '')
	{
		$pickupName = is_string($pickupName) ? trim($pickupName) : '';
		$noOffice = __('No office selected', 'dpdro');
		if ($pickupName !== '' && strcasecmp($pickupName, $noOffice) !== 0) {
			return $pickupName;
		}
		if (!empty($pickupId) && class_exists('DataLists')) {
			$office = (new DataLists($this->wpdb))->getOfficeById($pickupId);
			if ($office && !empty($office->office_name)) {
				return (string) $office->office_name;
			}
		}
		return $pickupName;
	}

	/**
	 * Block COD + DPD PAYPOINT pickup on classic checkout.
	 * Adds an error notice so WooCommerce does not create the order.
	 */
	public function validateCodPickup()
	{
		$paymentMethod = isset($_POST['payment_method']) ? wc_clean(wp_unslash($_POST['payment_method'])) : '';
		if ($paymentMethod !== 'cod') {
			return;
		}

		$shipToDifferent = isset($_POST['ship_to_different_address']) && !empty($_POST['ship_to_different_address']);
		$pickupId = '';
		if ($shipToDifferent && !empty($_POST['shipping_pickup'])) {
			$pickupId = wc_clean(wp_unslash($_POST['shipping_pickup']));
		} elseif (!empty($_POST['billing_pickup'])) {
			$pickupId = wc_clean(wp_unslash($_POST['billing_pickup']));
		}

		$pickupName = '';
		if ($shipToDifferent && !empty($_POST['shipping_pickup_name'])) {
			$pickupName = wc_clean(wp_unslash($_POST['shipping_pickup_name']));
		} elseif (!empty($_POST['billing_pickup_name'])) {
			$pickupName = wc_clean(wp_unslash($_POST['billing_pickup_name']));
		}

		if ($this->isCodBlockedPickup($this->resolvePickupName($pickupId, $pickupName))) {
			wc_add_notice(
				__('Cash on delivery is not available for the selected PAYPOINT pickup point. Please choose another payment method.', 'dpdro'),
				'error'
			);
		}
	}

	/**
	 * Block COD + DPD PAYPOINT pickup on Block / Store API checkout.
	 * Throws a RouteException so the order is not placed.
	 */
	public function validateCodPickupStoreApi($order, $request)
	{
		$paymentMethod = '';
		if (is_object($request) && method_exists($request, 'get_param')) {
			$paymentMethod = (string) $request->get_param('payment_method');
		}
		if ($paymentMethod === '' && is_object($order) && method_exists($order, 'get_payment_method')) {
			$paymentMethod = (string) $order->get_payment_method();
		}
		if ($paymentMethod !== 'cod') {
			return;
		}

		$pickupId = '';
		$pickupName = '';
		if (is_object($order) && method_exists($order, 'get_meta')) {
			$pickupId = $order->get_meta('_wc_shipping/dpdro/pickup_id');
			if (empty($pickupId)) {
				$pickupId = $order->get_meta('_wc_billing/dpdro/pickup_id');
			}
			$pickupName = $order->get_meta('_wc_shipping/dpdro/pickup_name');
			if (empty($pickupName)) {
				$pickupName = $order->get_meta('_wc_billing/dpdro/pickup_name');
			}
		}

		if ($this->isCodBlockedPickup($this->resolvePickupName($pickupId, $pickupName)) && class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'dpdro_cod_not_allowed_pickup',
				__('Cash on delivery is not available for the selected PAYPOINT pickup point. Please choose another payment method.', 'dpdro'),
				400
			);
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
				if ($item->postal_code == '510002' && $item->name == 'ALBA IULIA') {
					$item->postal_code = '510150';
				}
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
                                           $cities_ro[$_city[1]]->name . " (".  $cities_ro[$_city[1]]->municipality .")", $_city[1]
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

                            // Normalize RO postcodes to 6-digit strings.
                            // Why: library/cities/RO.php stores postcodes as PHP int literals (e.g. 77005),
                            // which silently drops the leading "0" required by Ilfov (IF) and other RO regions.
                            // Without padding, data-postcode in the city dropdown, the WC postcode field, and
                            // downstream shipping/AWB lookups all see "77005" instead of "077005".
                            if (!empty($cities['RO']) && is_array($cities['RO'])) {
                                foreach ($cities['RO'] as $abbr => &$_cities) {
                                    foreach ($_cities as &$_city) {
                                        if (is_array($_city) && isset($_city[1])) {
                                            $_city[1] = str_pad((string) $_city[1], 6, '0', STR_PAD_LEFT);
                                        }
                                    }
                                    unset($_city);
                                }
                                unset($_cities);
                            }
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

	/**
	 * Extract DPDRO fields from the Store API payload for an address group.
	 * Primary (your site): $p['shipping_address']['dpdro/pickup_id|...']
	 * Fallbacks: extensions => dpdro => { pickup_* } or extensions['dpdro/pickup_*']
	 */
	private function dpdro_get_store_api_address_payload( array $p, string $group = 'shipping' ) : array {
		$snake = "{$group}_address";
		$camel = "{$group}Address";

		$addr = $p[ $snake ] ?? $p[ $camel ] ?? [];
		return is_array( $addr ) ? $addr : [];
	}

	private function dpdro_extract_postcode_from_store_api( array $p, string $group = 'shipping' ) : string {
		$addr = $this->dpdro_get_store_api_address_payload( $p, $group );
		if ( isset( $addr['postcode'] ) && $addr['postcode'] !== '' ) {
			return sanitize_text_field( (string) $addr['postcode'] );
		}
		return '';
	}
	private function dpdro_extract_country_from_store_api( array $p, string $group = 'shipping' ) : string {
		$addr = $this->dpdro_get_store_api_address_payload( $p, $group );
		if ( isset( $addr['country'] ) && $addr['country'] !== '' ) {
			return sanitize_text_field( (string) $addr['country'] );
		}
		return '';
	}

	private function dpdro_extract_state_from_store_api( array $p, string $group = 'shipping' ) : string {
		$addr = $this->dpdro_get_store_api_address_payload( $p, $group );
		if ( isset( $addr['state'] ) && $addr['state'] !== '' ) {
			return sanitize_text_field( (string) $addr['state'] );
		}
		return '';
	}

    private function dpdro_extract_city_from_store_api( array $p, string $group = 'shipping' ) : string {
        $addr = $this->dpdro_get_store_api_address_payload( $p, $group );
        if ( isset( $addr['city'] ) && $addr['city'] !== '' ) {
            return sanitize_text_field( (string) $addr['city'] );
        }
        return '';
    }

    private function dpdro_extract_address_1_from_store_api( array $p, string $group = 'shipping' ) : string {
        $addr = $this->dpdro_get_store_api_address_payload( $p, $group );
        if ( isset( $addr['address_1'] ) && $addr['address_1'] !== '' ) {
            return sanitize_text_field( (string) $addr['address_1'] );
        }
        return '';
    }


	function dpdro_extract_from_store_api( array $p, string $group = 'shipping' ) : array {
		$out  = [ 'pickup_id' => '', 'pickup_name' => '', 'pickup_type' => '' ];
		$addr = $this->dpdro_get_store_api_address_payload( $p, $group );
		if ( ! is_array( $addr ) ) {
			return $out;
		}

		// 1) Preferred on your install: fully-qualified keys on the address.
		foreach ( [ 'pickup_id', 'pickup_name', 'pickup_type' ] as $k ) {
			$fq = "dpdro/{$k}";
			if ( isset( $addr[ $fq ] ) && $addr[ $fq ] !== '' ) {
				$out[ $k ] = $addr[ $fq ];
			}
		}

		// 2) Fallback: address.extensions.dpdro.{pickup_*}
		if ( isset( $addr['extensions']['dpdro'] ) && is_array( $addr['extensions']['dpdro'] ) ) {
			foreach ( [ 'pickup_id', 'pickup_name', 'pickup_type' ] as $k ) {
				if ( $out[ $k ] === '' && isset( $addr['extensions']['dpdro'][ $k ] ) ) {
					$out[ $k ] = $addr['extensions']['dpdro'][ $k ];
				}
			}
		}

		// 3) Fallback: address.extensions['dpdro/pickup_*']
		if ( isset( $addr['extensions'] ) && is_array( $addr['extensions'] ) ) {
			foreach ( [ 'pickup_id', 'pickup_name', 'pickup_type' ] as $k ) {
				$fq = "dpdro/{$k}";
				if ( $out[ $k ] === '' && isset( $addr['extensions'][ $fq ] ) ) {
					$out[ $k ] = $addr['extensions'][ $fq ];
				}
			}
		}

		return array_map( 'sanitize_text_field', $out );
	}

	function my_is_block_checkout_default(): bool {
		return class_exists( CartCheckoutUtils::class )
		       && CartCheckoutUtils::is_checkout_block_default();
	}

	function my_is_block_checkout_by_page_content(): bool {
		if ( ! function_exists( 'wc_get_page_id' ) ) return false;

		$checkout_id = wc_get_page_id( 'checkout' );
		if ( ! $checkout_id || $checkout_id <= 0 ) return false;

		// WC_Blocks_Utils::has_block_in_page() exists in some versions.
		if ( class_exists( 'WC_Blocks_Utils' ) && method_exists( 'WC_Blocks_Utils', 'has_block_in_page' ) ) {
			return WC_Blocks_Utils::has_block_in_page( $checkout_id, 'woocommerce/checkout' );
		}

		// Generic WP fallback:
		$content = get_post_field( 'post_content', $checkout_id );
		return function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $content );
	}

	/**
	 * AJAX handler to update DPD session data for blocks checkout
	 */
	public function ajax_update_session() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'dpdro_search_city' ) ) {
			error_log('[DPD] AJAX update session - nonce verification failed');
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		$pickup_id = isset( $_POST['pickup_id'] ) ? sanitize_text_field( $_POST['pickup_id'] ) : '';
		$pickup_name = isset( $_POST['pickup_name'] ) ? sanitize_text_field( $_POST['pickup_name'] ) : '';
		$pickup_type = isset( $_POST['pickup_type'] ) ? sanitize_text_field( $_POST['pickup_type'] ) : '';

        $address = isset( $_POST['address'] ) ? sanitize_text_field( $_POST['address'] ) : '';
		$mirrorToBilling = isset( $_POST['mirror_to_billing'] ) ? sanitize_text_field( $_POST['mirror_to_billing'] ) : 0;
		$change = false;
        if ( WC()->customer && method_exists( WC()->customer, 'set_shipping_address_1' ) && $address !== '' ) {
            WC()->customer->set_shipping_address_1($address);
			$change = true;
        }

		 if ($mirrorToBilling && WC()->customer && method_exists( WC()->customer, 'set_billing_address_1' ) && $address !== '' ) {
            WC()->customer->set_billing_address_1($address);
			$change = true;
        }

		if ($change) {
			 WC()->customer->save();
		}

		error_log('[DPD] AJAX update session - pickup_id: ' . $pickup_id . ', pickup_name: ' . $pickup_name . ', pickup_type: ' . $address .  "address");

		// Set session variables
		WC()->session->set( 'dpdro_office_id', $pickup_id );
		WC()->session->set( 'dpdro_office_name', $pickup_name );
		WC()->session->set( 'dpdro_office_type', $pickup_type );

		// Flag when pickup was explicitly cleared, so update_customer_callback
		// doesn't overwrite with stale Blocks store values
		if ( $pickup_id === '' ) {
			WC()->session->set( 'dpdro_pickup_cleared', true );
		} else {
			WC()->session->set( 'dpdro_pickup_cleared', false );
		}

        $order_id = 0;
		if ( WC()->session ) {
			$order_id = isset( WC()->session->order_awaiting_payment )
				? absint( WC()->session->order_awaiting_payment )
				: absint( WC()->session->get( 'store_api_draft_order', 0 ) ); // blocks draft order
		}

		if ( $order_id > 0 ) {
			update_post_meta( $order_id, 'dpdro_pickup', $pickup_id );
			update_post_meta( $order_id, 'dpdro_pickup_name', $pickup_name );
			update_post_meta( $order_id, 'dpdro_pickup_type', $pickup_type );
		}

		wp_send_json_success( array(
			'message' => 'Session updated',
			'pickup_id' => $pickup_id,
			'pickup_name' => $pickup_name,
			'pickup_type' => $pickup_type
		) );
	}

}
