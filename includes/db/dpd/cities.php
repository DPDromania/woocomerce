<?php

/**
 * Namespace: includes/db/dpd.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBDPDOCities
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
        $this->name = $this->wpdb->prefix .'dpdro_cities';
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
			`city_id` int(11) NOT NULL,
			`country_id`  int(11) NOT NULL,
			`name` varchar(255) NOT NULL,
			`municipality` varchar(255) NOT NULL,
			`region` varchar(255) NOT NULL,
			`postal_code` varchar(255) NOT NULL,
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
