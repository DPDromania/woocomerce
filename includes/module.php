<?php

/**
 * Namespace: includes.
 */

class Module
{
	/**
	 * Global database.
	 */
	private $wpdb;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 */
	protected $loader;

	/**
	 * The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 */
	public function __construct($wpdb)
	{
		$this->wpdb = $wpdb;
		add_action('plugins_loaded', array($this, 'loadDependencies'));
		add_filter('woocommerce_shipping_methods', array($this, 'loadServices'));
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	public function loadDependencies()
	{

		/**
		 * Wordpress data.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/wp/loader.php';
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/wp/i18n.php';

		/**
		 * Check if WooCommerce is activated
		 */
		if (class_exists('woocommerce')) {

			/**
			 * Settings data.
			 */
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/data/addresses.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/data/settings.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/data/lists.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/data/zones.php';

			/**
			 * WooCommerce api.
			 */
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/woo/api.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/woo/orders.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/woo/order.php';

			/**
			 * Include DPD RO library.
			 */
			require_once plugin_dir_path(dirname(__FILE__)) . 'library/api.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'library/lists.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'library/nomenclature.php';

			/** 
			 * Services directory
			 */
			$dir = dirname(__FILE__) . '/woo/services/';
			if (is_dir($dir)) {

				/** 
				 * Data Lists
				 */
				$lists = new DataLists($this->wpdb);
				$listServices = $lists->getActiveServices();
				foreach ($listServices as $service) {
					$file = $dir . $service->service_id . '.php';
					if (file_exists($file)) {
						require_once $file;
					}
				}
			}

			/**
			 * Module data.
			 */
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ajax.php';
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/frontend.php';
		}

		/**
		 * Module data.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/backend.php';

		$this->loader = new WPLoader();
		$this->setLocale();
		$this->defineHooks();
		$this->loader->run();
	}

	/**
	 * Load services for WooCommerce.
	 */
	public function loadServices($methods)
	{
		/** 
		 * Services directory
		 */
		$dir = dirname(__FILE__) . '/woo/services/';
		if (is_dir($dir)) {

			/** 
			 * Data Lists
			 */
			$lists = new DataLists($this->wpdb);
			$listServices = $lists->getActiveServices();
			foreach ($listServices as $service) {
				$file = $dir . $service->service_id . '.php';
				if (file_exists($file)) {
					$methods['dpdro_shipping_' . $service->service_id] = 'DPDRO_Service_Gateway_' . $service->service_id;
				}
			}
		}
		return $methods;
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function setLocale()
	{
		$i18n = new WPi18n();
		$this->loader->add_action('plugins_loaded', $i18n, 'load');
	}

	/**
	 * Register all of the hooks related to the public-facing functionality of the plugin.
	 */
	private function defineHooks()
	{
		$inits = array();

		/**
		 * Check if WooCommerce is activated
		 */
		if (class_exists('woocommerce')) {
			$inits = array(
				new Ajax($this->wpdb),
				new Frontend($this->wpdb),
				new WooOrders($this->wpdb),
				new WooOrder($this->wpdb),
			);
		}

		array_push($inits, new Backend($this->wpdb));
		foreach ($inits as $init) {
			$this->loader->add_action('init', $init, 'init');
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run()
	{
		$this->loader->run();
	}
}
