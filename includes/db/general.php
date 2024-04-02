<?php

/**
 * Namespace: includes/db.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DBGeneral
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
        $this->name = $this->wpdb->prefix . 'dpdro_settings';
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->name} (
            `id` int(11) NOT NULL AUTO_INCREMENT,
			`key` TEXT NOT NULL,
			`value` TEXT NOT NULL,
			`created_at` datetime NOT NULL DEFAULT NOW(),
			`updated_at` datetime NOT NULL DEFAULT NOW(),
            PRIMARY KEY (`id`)
        )");
        if ($this->wpdb->last_error) {
            throw new Exception($this->wpdb->last_error);
        }

        /**
         * Insert default data.
         */
        $this->default();
    }

    /**
     * Function is trigger when module is deactivated.
     */
    public function deactivate()
    {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->name};");
    }

    /**
     * Default settings data.
     */
    public function default()
    {
        $default = [
            /**
             * Authentication.
             */
            'authenticated'          => '0',
            'username'               => '',
            'password'               => '',

            /**
             * Settings.
             */
            'packages'               => 'DPD RO',
            'contents'               => 'DPD RO',
            'packaging_method'       => 'one',
            'services'               => '',
            'client_contracts'       => '0',
            'office_locations'       => '0',
            'sender_payer_insurance' => '1',
            'include_shipping_price' => '0',
            'payment_tax'            => '0',
            'courier_service_payer'  => 'SENDER',
            'id_payer_contract'      => '',
            'print_format'           => 'pdf',
            'print_paper_size'       => 'A4_4xA6',
            'test_or_open'           => '',
            'test_or_open_courier'   => 'SENDER',

            /**
             * Advance settings.
             */
            'max_weight'             => '31.5',
            'max_weight_automat'     => '20',
            'show_office_selection'  => '0',
            'use_default_weight'     => '1',
            'default_weight'         => '1',
            'county_before_city'     => '0',
            'city_dropdown'         => '0',
            
            /**
             * Payment settings.
             */
            'payment_zones'          => '',
        ];
        foreach ($default as $key => $value) {
            $check = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->name} WHERE 'key' = `{$key}` ", array()));
            if (!$check) {
                $this->wpdb->insert(
                    $this->name,
                    array(
                        'key'   => $key,
                        'value' => $value,
                    ),
                    array('%s', '%s')
                );
            }
        }
    }
}
