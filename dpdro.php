<?php
/** 
 * Plugin Name: DPD RO Shipping / Payment
 * Plugin URI: https://www.dpd.com/ro/ro/
 * Description: 
 * 		A powerful plugin DPD RO Woocommerce Shipping / Payment Gateway.
 * 		Requires PHP min: 7.0
 * 		Requires Wordpress min: 5.0
 * 		Requires Woocommerce min: 3.9		
 * Version: 3.2.27
 * Author: DPD-RO
 * Author URI: https://www.dpd.com/ro/ro/
 * Text Domain: dpdro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Requires Woocommerce: 3.9
 */

define('DPDRO_VERSION',  '3.2.27');

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

define('PLUGIN_DIR_DPDRO', dirname(__FILE__) . '/');
define('PLUGIN_LOGS_DIR_DPDRO', dirname(__FILE__) . '/../../dpdro');
define('WPMU_PLUGIN_DIR_DPDRO', dirname(__FILE__) . '/languages');

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * The code that runs during plugin activation.
 * The core plugin class that is used to check for updated
 */
if (!function_exists('dpdRoUpdateChecker')) {
	require plugin_dir_path(__FILE__) . 'plugin-update-checker/plugin-update-checker.php';
	function dpdRoUpdateChecker()
	{
		$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker('https://github.com/DPDromania/woocomerce', __FILE__, 'DPD RO Shipping / Payment');
		$myUpdateChecker->setBranch('master');
		//$myUpdateChecker->se8tAuthentication('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');
	}
	dpdRoUpdateChecker();
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.php
 */
if (!function_exists('dpdRoActivation')) {
	function dpdRoActivation()
	{
		require_once plugin_dir_path(__FILE__) . 'includes/wp/activator.php';
		require_once plugin_dir_path(__FILE__) . 'includes/database.php';
		WPActivator::activate();

		global $wpdb;
		$Database = new Database($wpdb);
		$Database->activate();
	}
	register_activation_hook(__FILE__, 'dpdRoActivation');
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.php
 */
if (!function_exists('dpdRoDeactivation')) {
	function dpdRoDeactivation()
	{
		require_once plugin_dir_path(__FILE__) . 'includes/wp/deactivator.php';
		require_once plugin_dir_path(__FILE__) . 'includes/database.php';
		WPDeactivator::deactivate();

		global $wpdb;
		$Database = new Database($wpdb);
		$Database->deactivate();
	}
	register_deactivation_hook(__FILE__, 'dpdRoDeactivation');
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
if (!function_exists('dpdRoRun')) {
	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */
	require plugin_dir_path(__FILE__) . 'includes/module.php';
	function dpdRoRun()
	{
		/**
		 * Global database.
		 */
		global $wpdb;
		
		new Module($wpdb);
	}
	dpdRoRun();
}
