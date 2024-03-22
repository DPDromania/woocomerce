<?php

/**
 * Namespace: includes/db.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBShipment
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
        $this->name = $this->wpdb->prefix .'order_dpd_shipment';
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` TEXT NOT NULL,
            `shipment_id` TEXT NOT NULL,
            `shipment_data` TEXT NOT NULL,
            `parcels` TEXT NOT NULL,
            `price` TEXT NOT NULL,
			`pickup` datetime NOT NULL,
			`deadline` datetime NOT NULL,
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
