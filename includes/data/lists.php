<?php

/**
 * Namespace: includes/data.
 */

if (!defined('ABSPATH')) {
	exit;
}


class DataLists
{

    public const DPD_DISCARDED_SERVICES =  ['PALLET ONE RO', 'PALLET ONE BG'];
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
	}

	/**
	 * Get DPD RO services list.
	 */
	public function getServices()
	{
		$servicesTable = $this->wpdb->prefix . 'dpdro_services';
		$response = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$servicesTable}", array()));
		return $this->removeDiscardedServices($response);
	}

    private function removeDiscardedServices($services)
    {
        $remaining = [];
        foreach ($services as $service) {
            if (!in_array($service->service_name, self::DPD_DISCARDED_SERVICES, true)) {
                $remaining[] = $service;
            }
        }

        return $remaining;
    }

	/**
	 * Get DPD RO services list.
	 */
	public function getActiveServices()
	{
		$settings = new DataSettings($this->wpdb);
		$settingsServices = $settings->getSetting('services');
		$settingsServices = json_decode(str_replace("\\", "", $settingsServices));
		$servicesTable = $this->wpdb->prefix . 'dpdro_services';
		$services = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$servicesTable}", array()));
		if (is_array($settingsServices) && $services && !empty($services)) {
			foreach ($services as $key => $service) {
				if (!in_array($service->service_id, $settingsServices)) {
					unset($services[$key]);
				}
			}
		}
		return $services;
	}

	/**
	 * Get service by service id.
	 */
	public function getServiceById($serviceId)
	{
		$services = $this->getServices();
		foreach ($services as $service) {
			if ($service->service_id === $serviceId) {
				return $service;
			}
		}
		return false;
	}

	/**
	 * Get DPD RO clients list.
	 */
	public function getClients()
	{
		$servicesTable = $this->wpdb->prefix . 'dpdro_clients';
		$response = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$servicesTable}", array()));
		return $response;
	}

	/**
	 * Get DPD RO offices list.
	 */
	public function getOffices()
	{
		$servicesTable = $this->wpdb->prefix . 'dpdro_offices';
		$response = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$servicesTable}", array()));
		return $response;
	}

	/**
	 * Get DPD RO office by id.
	 */
	public function getOfficeById($pickup)
	{
		$servicesTable = $this->wpdb->prefix . 'dpdro_offices';
		$response = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$servicesTable} WHERE `office_id` = %s", $pickup));
		return $response;
	}

	/**
	 * Get DPD RO offices groups list.
	 */
	public function getOfficesGroups()
	{
		$offices = $this->getOffices();
		$response = (object) array();
		if ($offices && !empty($offices)) {
			$response->all = (object) array();
			$response->all->office_site_id = '000000001';
			$response->all->office_site_type = '';
			$response->all->office_site_name = __('All / Rest DPDBox', 'dpdro');
			foreach ($offices as $office) {
				$key = $office->office_site_id;
				$response->$key = $office;
			}
		}
		return $response;
	}

	/**
	 * Get tax rates list.
	 */
	public function getTaxRates()
	{
		$servicesTable = $this->wpdb->prefix . 'order_dpd_tax_rates';
		$response = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$servicesTable}", array()));
		return $response;
	}

	/**
	 * Get tax rates offices list.
	 */
	public function getTaxRatesOffices()
	{
		$servicesTable = $this->wpdb->prefix . 'order_dpd_tax_rates_offices';
		$response = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$servicesTable}", array()));
		return $response;
	}
}
