<?php

/**
 * Namespace: includes/data/settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DataSettings
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
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->name = $this->wpdb->prefix . 'dpdro_settings';
    }

    /**
     * Get setting by key.
     */
    public function getSetting($key)
    {
        $settings = $this->getSettings();
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        return false;
    }

    /**
     * Get all settings.
     */
    public function getSettings()
    {
        $settings = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->name}", array()));
        $response = [];
        foreach ($settings as $setting) {
            $response[$setting->key] = $setting->value;
        }
        return $response;
    }
}
