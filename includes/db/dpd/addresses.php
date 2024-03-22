<?php

/**
 * Namespace: includes/db/dpd.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBDPDAddresses
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
        $this->name = $this->wpdb->prefix . 'dpdro_addresses';
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `site_id` TEXT NOT NULL,
            `country_id` TEXT NOT NULL,
            `main_site_id` TEXT NOT NULL,
            `type` TEXT NOT NULL,
            `type_en` TEXT NOT NULL,
            `name` TEXT NOT NULL,
            `name_en` TEXT NOT NULL,
            `municipality` TEXT NOT NULL,
            `municipality_en` TEXT NOT NULL,
            `region` TEXT NOT NULL,
            `region_en` TEXT NOT NULL,
            `post_code` TEXT NOT NULL,
            `address_nomenclature` TEXT NOT NULL,
            `x` TEXT NOT NULL,
            `y` TEXT NOT NULL,
            `serving_days` TEXT NOT NULL,
            `serving_office_id` TEXT NOT NULL,
            `serving_hub_office_id` TEXT NOT NULL,
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
