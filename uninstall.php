<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}
/** 
 * Making WPDB as global
 * to access database information.
 */
global $wpdb;


/** 
 * @var $table_name 
 * name of table to be dropped
 * prefixed with $wpdb->prefix from the database
 */

// drop the table from the database.
$wpdb->query( "DROP TABLE IF EXISTS `dpdro_settings`" );
