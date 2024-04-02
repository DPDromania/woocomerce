<?php

/**
 * Namespace: includes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Database
{
	/**
	 * Global database.
	 */
	private $wpdb;

    /** 
     * Function init.
     */
    public function __construct($wpdb)
    {
		$this->wpdb = $wpdb;
    }

    /**
     * Function is trigger when module is activated.
     */
    public function activate()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/general.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/address.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/courier.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/shipment.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/taxrate.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/taxrateoffice.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/settings.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/other-method.php';
        
        $dbGeneral = new DBGeneral($this->wpdb);
        $dbGeneral->activate();

        $dbAddress = new DBAddress($this->wpdb);
        $dbAddress->activate();
        
        $dbCourier = new DBCourier($this->wpdb);
        $dbCourier->activate();
        
        $dbShipment = new DBShipment($this->wpdb);
        $dbShipment->activate();
        
        $dbTaxRate = new DBTaxRate($this->wpdb);
        $dbTaxRate->activate();
        
        $dbTaxRateOffice = new DBTaxRateOffice($this->wpdb);
        $dbTaxRateOffice->activate();
        
        $dbSettings = new DBSettings($this->wpdb);
        $dbSettings->activate();

        $dbOtherMethod = new DBOtherMethod($this->wpdb);
        $dbOtherMethod->activate();
        
        /**
         * DPD RO lists.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/services.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/clients.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/offices.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/addresses.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/cities.php';

        $dbDPDServices = new DBDPDServices($this->wpdb);
        $dbDPDServices->activate();
       
        $dbDPDClients = new DBDPDClients($this->wpdb);
        $dbDPDClients->activate();
        
        $dbDPDOffices = new DBDPDOffices($this->wpdb);
        $dbDPDOffices->activate();

        $dbDPDAddresses = new DBDPDAddresses($this->wpdb);
        $dbDPDAddresses->activate();

        $dbDPDCities = new DBDPDOCities($this->wpdb);
        $dbDPDCities->activate();
        
    }

    /**
     * Function is trigger when module is deactivated.
     */
    public function deactivate()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/general.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/address.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/courier.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/shipment.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/taxrate.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/taxrateoffice.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/settings.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/other-method.php';

        $dbGeneral = new DBGeneral($this->wpdb);
        $dbGeneral->deactivate();

        $dbAddress = new DBAddress($this->wpdb);
        $dbAddress->deactivate();
        
        $dbCourier = new DBCourier($this->wpdb);
        $dbCourier->deactivate();
        
        $dbShipment = new DBShipment($this->wpdb);
        $dbShipment->deactivate();
        
        $dbTaxRate = new DBTaxRate($this->wpdb);
        $dbTaxRate->deactivate();
        
        $dbTaxRateOffice = new DBTaxRateOffice($this->wpdb);
        $dbTaxRateOffice->deactivate();
        
        $dbSettings = new DBSettings($this->wpdb);
        $dbSettings->deactivate();

        $dbOtherMethod = new DBOtherMethod($this->wpdb);
        $dbOtherMethod->deactivate();


        /**
         * DPD RO lists.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/services.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/clients.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/offices.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/addresses.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/db/dpd/cities.php';

        $dbDPDServices = new DBDPDServices($this->wpdb);
        $dbDPDServices->deactivate();
       
        $dbDPDClients = new DBDPDClients($this->wpdb);
        $dbDPDClients->deactivate();
        
        $dbDPDOffices = new DBDPDOffices($this->wpdb);
        $dbDPDOffices->deactivate();

        $dbDPDAddresses = new DBDPDAddresses($this->wpdb);
        $dbDPDAddresses->deactivate();

        $dbDPDCities = new DBDPDOCities($this->wpdb);
        $dbDPDCities->deactivate();

    }
}
