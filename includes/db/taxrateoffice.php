<?php

/**
 * Namespace: includes/db.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBTaxRateOffice
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
        $this->name = $this->wpdb->prefix .'order_dpd_tax_rates_offices';
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
			`service_id` TEXT NOT NULL,
			`zone_id` TEXT NOT NULL,
			`based_on` TEXT NOT NULL,
			`apply_over` TEXT NOT NULL,
			`tax_rate` TEXT NOT NULL,
			`calculation_type` TEXT NOT NULL,
			`status` TEXT NOT NULL,
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
