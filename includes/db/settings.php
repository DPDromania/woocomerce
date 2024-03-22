<?php

/**
 * Namespace: includes/db.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBSettings
{
    /** 
     * Table name.
     */
    private $name;

    /** 
     * @var wpdb 
     */
    protected $wpdb;

    /** 
     * Function init.
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
        $this->name = $this->wpdb->prefix . 'order_dpd_settings';
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` TEXT NOT NULL,
			`shipping_tax_rate` TEXT NOT NULL,
			`shipping_tax` TEXT NOT NULL,
			`courier_service` TEXT NOT NULL,
			`declared_value` TEXT NOT NULL,
			`include_shipping` TEXT NOT NULL,
			`date_added` datetime NOT NULL,
            PRIMARY KEY (`id`)
        )");
        if ($this->wpdb->last_error) {
            throw new Exception($this->wpdb->last_error);
        }
    }

    /**
     * Function is trigger when module is deactivated.
     */
    public function deactivate()
    {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->name};");
    }
}
