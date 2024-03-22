<?php

/**
 * Namespace: includes.
 */

if (!defined('ABSPATH')) {
	exit;
}

class Backend
{
	/**
	 * Global database.
	 */
	private $wpdb;

	/**
	 * The version of this plugin.
	 */
	private $version;

	/**
	 * The unique identifier of this plugin.
	 */
	protected $pluginUrl;
	protected $pluginName;

	/**
	 * Constructor.
	 */
	public function __construct($wpdb)
	{
		if (!function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		$pluginData = get_plugin_data(PLUGIN_DIR_DPDRO . 'dpdro.php');
		$this->version = $pluginData['Version'];
		$this->pluginUrl = 'admin.php?page=dpdro-settings';
		$this->pluginName = 'dpdro';
		$this->wpdb = $wpdb;
		$this->init();
	}

	/**
	 * Init your settings.
	 */
	function init()
	{
		add_action('admin_notices', array($this, 'activateNotice'));
		add_action('admin_bar_menu', array($this, 'navMenu'), 100);
		add_action('admin_menu', array($this, 'addMenu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));

		/**
		 * Print labels and vouchers.
		 */
		add_action('init', array($this, 'printTemplate'));
		add_action('wp_loaded', array($this, 'wpLoaded'));
		add_filter('query_vars', array($this, 'queryVars'));
		add_filter('rewrite_rules_array', array($this, 'rewriteRules'));
	}

	/**
	 * Show activate notice.
	 */
	public function activateNotice()
	{
		if (get_transient('dpdro-activated')) {
			echo '<div class="updated notice is-dismissible"><p>Thank you for using DPD Woocommerce Shipping / Payment Gateway <a href="' . admin_url($this->pluginUrl) . '">Settings</a>.</p></div>';
			delete_transient('dpdro-activated');
		}
	}

	/**
	 * Link to settings from wordpress admin menu.
	 */
	public function navMenu()
	{
		global $wp_admin_bar;
		$menuId = 'dpdro';
		$wp_admin_bar->add_menu(
			array(
				'id' => $menuId,
				'title' => __('DPD RO', 'dpdro'),
				'href' => admin_url($this->pluginUrl)
			)
		);
	}

	/**
	 * Admin page.
	 */
	public function addMenu()
	{
		$dpdLogo = base64_encode('
			<svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" preserveAspectRatio="xMidYMid meet">
				<path fill="#f0f0f1" stroke="none" d="M124.9,162.9l142.5,80.6l1.9,127.9c0,0,1,10.7-11.6,10.7c-12.6,0-13.1-8.7-13.1-8.7V265.2l-130.5-77.1  l1,150.8l141.6,84.4l141.1-83.4V189.1l-81,48.5c0,0-7.3,6.3-17-3.4c-9.7-9.7,1.5-17.9,1.5-17.9l90.7-53.4L256.7,88.7L124.9,162.9z" />
			</svg>
		');
		add_menu_page(
			__('Settings', 'dpdro'),
			__('DPD RO', 'dpdro'),
			'manage_options',
			'dpdro-settings',
			array($this, 'render'),
			'data:image/svg+xml;base64,' . $dpdLogo,
			35
		);
	}

	/**
	 * Render admin page settings.
	 * Check if WooCommerce is activated
	 */
	public function render()
	{
		if (class_exists('woocommerce')) {
			include_once(PLUGIN_DIR_DPDRO . 'includes/view/settings.php');
		} else {
			include_once(PLUGIN_DIR_DPDRO . 'includes/view/message.php');
		}
	}

	/**
	 * Register the JavaScript for the admin-facing side of the site.
	 * Check if WooCommerce is activated
	 */
	public function enqueueScripts()
	{
		if (is_admin()) {
			$screen = get_current_screen();
			if (class_exists('woocommerce')) {
				if (
					isset($screen->base) &&
					isset($screen->id) &&
					(
						($screen->base == 'edit' &&  $screen->id == 'edit-shop_order') ||
						($screen->base == 'post' && $screen->id == 'shop_order') ||
						($screen->base == 'woocommerce_page_wc-orders' && $screen->id == 'woocommerce_page_wc-orders')
					)
				) {
					wp_enqueue_style('dpdro-font-awesome', plugin_dir_url(__FILE__) . '../assets/admin/css/font-awesome.min.css', array(), $this->version, 'all');
					wp_enqueue_style('dpdro-order', plugin_dir_url(__FILE__) . '../assets/admin/css/order.css', array(), $this->version, 'all');
					wp_enqueue_script('dpdro-order', plugin_dir_url(__FILE__) . '../assets/admin/js/order.js', array('jquery'), $this->version, true);
					wp_localize_script('dpdro-order', 'dpdRo', array('ajaxurl' => admin_url('admin-ajax.php')));

					$data = array(
						'errorMessage'       => __('Something went wrong.', 'dpdro'),
						'placeholderSearch'  => __('Search', 'dpdro'),
					);
					wp_localize_script('dpdro-order', 'dpdRoGeneral', $data);
				}
			}
			if (isset($screen->base) && $screen->base == 'toplevel_page_dpdro-settings') {
				wp_enqueue_style('dpdro-style', plugin_dir_url(__FILE__) . '../assets/admin/css/style.css', array(), $this->version, 'all');
				if (class_exists('woocommerce')) {
					wp_enqueue_script('dpdro-script', plugin_dir_url(__FILE__) . '../assets/admin/js/custom.js', array('jquery'), $this->version, true);
					wp_localize_script('dpdro-script', 'dpdRo', array('ajaxurl' => admin_url('admin-ajax.php')));

					$dataLists = new DataLists($this->wpdb);
					$data = array(
						'services'     => $dataLists->getServices(),
						'zones'        => DataZones::zones(),
						'zonesOffices' => $dataLists->getOfficesGroups(),
						'errorMessage' => __('Something went wrong.', 'dpdro'),
						'successLast'  => __('There are no tax settings.', 'dpdro'),
						'infoMessage'  => __('You must save tax rates modification. If you not save the modification will not be done.', 'dpdro'),
						'translate'    => [
							'price'      => __('Based on price', 'dpdro'),
							'weight'     => __('Based on weight', 'dpdro'),
							'fixed'      => __('Fixed', 'dpdro'),
							'percentage' => __('Percentage', 'dpdro'),
							'enabled'    => __('Enabled', 'dpdro'),
							'disabled'   => __('Disabled', 'dpdro'),
							'remove'     => __('Remove', 'dpdro'),
							'select'     => __(' --- Please Select --- ', 'dpdro'),
							'no_tax'     => __('No tax rate found.', 'dpdro'),
						],
					);
					wp_localize_script('dpdro-script', 'dpdRoGeneral', $data);
				}
			}
		}
	}

	/**
	 * flush_rules() if our rules are not yet included.
	 */
	static public function wpLoaded()
	{
		$rules = get_option('rewrite_rules');
		if (!isset($rules['api/(.*?)/(.+?)'])) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}

	/**
	 * Adding a new rule.
	 */
	static public function rewriteRules($rules)
	{
		$newrules = array();
		$newrules['api/(.*?)/(.+?)'] = 'admin.php/dpdro_print?print=$matches[1]';
		return $newrules + $rules;
	}

	/**
	 * Adding the id var so that WP recognizes it
	 */
	static public function queryVars($vars)
	{
		array_push($vars, 'dpdro_print', 'print');
		return $vars;
	}

	/**
	 * Print labels.
	 */
	public function printLabels($orderId)
	{

		/**
		 * Data settings.
		 */
		$dataSettings = new DataSettings($this->wpdb);
		$settings = $dataSettings->getSettings();
		$parameters = [
			'format'    => $settings['print_format'],
			'paperSize' => $settings['print_paper_size'],
			'parcels'   => array()
		];

		/** 
		 * Woo order
		 */
		$wooOrder = new WooOrder($this->wpdb);
		$orderShipment = $wooOrder->getOrderShipment($orderId);
		$orderShipmentParcels = json_decode($orderShipment->parcels);
		if ($orderShipmentParcels) {
			foreach ($orderShipmentParcels as $parcel) {
				$parcelData = [
					'parcel' => [
						'id' => $parcel->id
					]
				];
				array_push($parameters['parcels'], $parcelData);
			}
		}

		/**
		 * Library API.
		 */
		$libraryApi = new LibraryApi($settings['username'], $settings['password']);
		$response = $libraryApi->printLabels($parameters);

		if ($settings['print_format'] == 'html') {
			header('Content-type: text/html; charset=utf-8');
			header('Content-Disposition: attachment; filename="dpdro_shipment_labels_' . $orderId . '.html"');
		} else if ($settings['print_format'] == 'zpl') {
			header('Content-type: x-application/zpl');
			header('Content-Disposition: attachment; filename="dpdro_shipment_labels_' . $orderId . '.zpl"');
		} else {
			header('Content-type: application/pdf');
			header('Content-Disposition: attachment; filename="dpdro_shipment_labels_' . $orderId . '.pdf"');
		}
		echo $response;
	}

	/**
	 * Print voucher.
	 */
	public function printVoucher($orderId)
	{

		/**
		 * Data settings.
		 */
		$dataSettings = new DataSettings($this->wpdb);
		$settings = $dataSettings->getSettings();

		/** 
		 * Woo order
		 */
		$wooOrder = new WooOrder($this->wpdb);
		$orderShipment = $wooOrder->getOrderShipment($orderId);
		$orderShipmentParcels = json_decode($orderShipment->parcels);

		/**
		 * Library API.
		 */
		$libraryApi = new LibraryApi($settings['username'], $settings['password']);
		$response = $libraryApi->printVoucher($orderShipmentParcels[0]->id);

		header('Content-type: application/pdf');
		header('Content-Disposition: attachment; filename="dpdro_shipment_voucher_' . $orderId . '.pdf"');
		echo $response;
	}

	/**
	 * Printing template.
	 */
	public function printTemplate()
	{
		$url_path = trim(parse_url(add_query_arg(array()), PHP_URL_PATH), '/');
		if (strpos($url_path, '/dpdro_print') !== false || $url_path === 'dpdro_print') {
			if (isset($_GET['print']) && !empty($_GET['print'])) {
				if (isset($_GET['id']) && !empty($_GET['id'])) {
					if ($_GET['print'] === 'labels') {
						$this->printLabels($_GET['id']);
					} else if ($_GET['print'] === 'voucher') {
						$this->printVoucher($_GET['id']);
					}
				} else {
					echo __('You don\'t have permissions to view this page.', 'dpdro');
				}
			} else {
				echo __('You don\'t have permissions to view this page.', 'dpdro');
			}
			exit();
		}
	}
}
