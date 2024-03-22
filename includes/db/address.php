<?php

/**
 * Namespace: includes/db.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBAddress
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
        $this->name = $this->wpdb->prefix .'order_dpd_address';
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
			`order_id` TEXT NOT NULL,
			`address` TEXT NOT NULL,
			`address_city_id` TEXT,
			`address_city_name` TEXT,
			`address_street_id` TEXT,
			`address_street_type` TEXT,
			`address_street_name` TEXT,
			`address_number` TEXT,
			`address_block` TEXT,
			`address_apartment` TEXT,
			`method` TEXT NOT NULL,
			`office_id` TEXT,
			`office_name` TEXT,
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
