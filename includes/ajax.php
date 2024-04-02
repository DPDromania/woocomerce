<?php

/**
 * Namespace: includes.
 */

if (!defined('ABSPATH')) {
	exit;
}

class Ajax
{
	/**
	 * Global database.
	 */
	private $wpdb;

	/**
	 * Tables name.
	 */
	private $tableServices;
	private $tableClients;
	private $tableOffices;

    private $tableCities;
	private $tableAddresses;
	private $tableSettings;
	private $tableTaxRates;
	private $tableTaxRatesOffices;

	/**
	 * Constructor.
	 */
	public function __construct($wpdb)
	{
		$this->init();
		$this->wpdb = $wpdb;
		$this->tableServices = $this->wpdb->prefix . 'dpdro_services';
		$this->tableClients = $this->wpdb->prefix . 'dpdro_clients';
		$this->tableOffices = $this->wpdb->prefix . 'dpdro_offices';
		$this->tableCities = $this->wpdb->prefix . 'dpdro_cities';
		$this->tableAddresses = $this->wpdb->prefix . 'dpdro_addresses';
		$this->tableSettings = $this->wpdb->prefix . 'dpdro_settings';
		$this->tableTaxRates = $this->wpdb->prefix . 'order_dpd_tax_rates';
		$this->tableTaxRatesOffices = $this->wpdb->prefix . 'order_dpd_tax_rates_offices';
	}

	/**
	 * Init your settings.
	 */
	function init()
	{
		add_action('wp_ajax_saveConnection', array($this, 'saveConnection'));
		add_action('wp_ajax_nopriv_saveConnection', array($this, 'saveConnection'));
		add_action('wp_ajax_updateConnection', array($this, 'updateConnection'));
		add_action('wp_ajax_nopriv_updateConnection', array($this, 'updateConnection'));
		add_action('wp_ajax_saveSettings', array($this, 'saveSettings'));
		add_action('wp_ajax_nopriv_saveSettings', array($this, 'saveSettings'));
		add_action('wp_ajax_saveAdvanceSettings', array($this, 'saveAdvanceSettings'));
		add_action('wp_ajax_nopriv_saveAdvanceSettings', array($this, 'saveAdvanceSettings'));
		add_action('wp_ajax_savePaymentSettings', array($this, 'savePaymentSettings'));
		add_action('wp_ajax_nopriv_savePaymentSettings', array($this, 'savePaymentSettings'));
		add_action('wp_ajax_saveTaxRates', array($this, 'saveTaxRates'));
		add_action('wp_ajax_nopriv_saveTaxRates', array($this, 'saveTaxRates'));
		add_action('wp_ajax_saveTaxRatesOffices', array($this, 'saveTaxRatesOffices'));
		add_action('wp_ajax_nopriv_saveTaxRatesOffices', array($this, 'saveTaxRatesOffices'));
	}

	/**
	 * Ajax save connection.
	 */
	public function saveConnection()
	{
		check_ajax_referer('dpdro_save_connection', 'nonce');

		$json = [
			'error' => true
		];
		if (isset($_POST['action'])) {
			$params = $_POST['params'];

			/**
			 * Library API.
			 */
			$libraryApi = new LibraryApi($params['username'], $params['password']);
			if ($libraryApi->check()) {

				/**
				 * Library Lists.
				 */
				$libraryLists = new LibraryLists($libraryApi);

				$this->insertServices($libraryLists);
				$this->createServicesFile($libraryLists);
				$this->insertClients($libraryLists);
				$this->insertOffices($libraryLists);
				$this->insertAddresses($libraryLists);
                $this->insertCities($libraryLists);

				$params['payment_tax'] = $libraryApi->getPaymentTax();
				$params['authenticated'] = 1;
				foreach ($params as $key => $value) {
					$checkSetting = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableSettings} WHERE `key` = %s", $key));
					if ($checkSetting && !empty($checkSetting)) {
						$this->wpdb->update(
							$this->tableSettings,
							array(
								'value'      => $value,
								'updated_at' => date('Y-m-d H:i:s', strtotime('now')),
							),
							array(
								'key' => $key,
							),
							array('%s', '%s'),
							array('%s')
						);
					} else {
						$this->wpdb->insert(
							$this->tableSettings,
							array(
								'key'   => $key,
								'value' => $value,
							),
							array('%s', '%s')
						);
					}
				}
				$json['error'] = false;
				$json['message'] = __('Authenticate successfully.', 'dpdro');
				$json['redirect'] = admin_url('admin.php?page=dpdro-settings');
			} else {
				$json['message'] = __('Username or password is incorrect.', 'dpdro');
			}
		}
		echo wp_send_json($json);
		wp_die();
	}

	/**
	 * Ajax updat connection.
	 */
	public function updateConnection()
	{
		check_ajax_referer('dpdro_update_connection', 'nonce');

		$json = [
			'error' => true
		];
		if (isset($_POST['action'])) {
			$params = $_POST['params'];

			/**
			 * Library API.
			 */
			$libraryApi = new LibraryApi($params['username'], $params['password']);
			if ($libraryApi->check()) {

				/**
				 * Library Lists.
				 */
				$libraryLists = new LibraryLists($libraryApi);

				$this->insertServices($libraryLists);
				$this->createServicesFile($libraryLists);
				$this->insertClients($libraryLists);
				$this->insertOffices($libraryLists);
				$this->insertAddresses($libraryLists);
                $this->insertCities($libraryLists);

				$params['payment_tax'] = $libraryApi->getPaymentTax();
				$params['authenticated'] = 1;
				foreach ($params as $key => $value) {
					$checkSetting = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableSettings} WHERE `key` = %s", $key));
					if ($checkSetting && !empty($checkSetting)) {
						$this->wpdb->update(
							$this->tableSettings,
							array(
								'value'      => $value,
								'updated_at' => date('Y-m-d H:i:s', strtotime('now')),
							),
							array(
								'key' => $key,
							),
							array('%s', '%s'),
							array('%s')
						);
					} else {
						$this->wpdb->insert(
							$this->tableSettings,
							array(
								'key'   => $key,
								'value' => $value,
							),
							array('%s', '%s')
						);
					}
				}
				$json['error'] = false;
				$json['message'] = __('Updated successfully.', 'dpdro');
			} else {
				$json['message'] = __('Username or password is incorrect.', 'dpdro');
			}
		}
		echo wp_send_json($json);
		wp_die();
	}

	/**
	 * Insert services to database.
	 */
	public function insertServices($libraryLists)
	{
		/**
		 * Empty table.
		 */
		$this->wpdb->query("TRUNCATE TABLE `{$this->tableServices}`");

		/**
		 * Insert new records.
		 */
		$services = $libraryLists->Services();
		foreach ($services as $service) {
			$this->wpdb->insert(
				$this->tableServices,
				array(
					'service_id'   => $service['service_id'],
					'service_name' => $service['service_name'],
				),
				array('%s', '%s')
			);
		}
	}

    /**
     * Insert cities to database.
     */
    public function insertCities($libraryLists)
    {
        /**
         * Empty table.
         */
        $this->wpdb->query("TRUNCATE TABLE IF EXISTS `{$this->tableCities}`");

        /**
         * Insert new records.
         */
        $cities = $libraryLists->Cities(642);
        foreach ($cities as $city) {
            $this->wpdb->insert(
                $this->tableCities,
                array(
                    'city_id'   => $city['city_id'],
                    'country_id'   => $city['country_id'],
                    'name'   => $city['name'],
                    'municipality' => $city['municipality'],
                    'region' => $city['region'],
                    'postal_code' => $city['postal_code']
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

	/**
	 * Create services file.
	 */
	public function createServicesFile($libraryLists)
	{
		/**
		 * Services files directory.
		 */
		$dir = dirname(__FILE__) . '/woo/services/';
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		if (is_dir($dir)) {

			/**
			 * Model file.
			 */
			$model = dirname(__FILE__) . '/woo/model.php';
			$modelContent = file_get_contents($model);

			/**
			 * Services.
			 */
			$services = $libraryLists->Services();
			foreach ($services as $service) {

				/**
				 * Service file.
				 */
				$file = $dir . DIRECTORY_SEPARATOR . $service['service_id'] . '.php';
				$fileModel = $modelContent;
				$fileContent = str_replace("ModelServiceID", $service['service_id'], $fileModel);
				$fileContent = str_replace("ModelServiceName", $service['service_name'], $fileContent);
				file_put_contents($file, $fileContent);
			}
		}
	}

	/**
	 * Insert clients to database.
	 */
	public function insertClients($libraryLists)
	{
		/**
		 * Empty table.
		 */
		$this->wpdb->query("TRUNCATE TABLE `{$this->tableClients}`");

		/**
		 * Insert new records.
		 */
		$clients = $libraryLists->ClientContracts();
		foreach ($clients as $client) {
			$this->wpdb->insert(
				$this->tableClients,
				array(
					'client_id'      => $client['client_id'],
					'client_name'    => $client['client_name'],
					'client_address' => $client['client_address'],
				),
				array('%s', '%s', '%s')
			);
		}
	}

	/**
	 * Insert offices to database.
	 */
	public function insertOffices($libraryLists)
	{
		/**
		 * Empty table.
		 */
		$this->wpdb->query("TRUNCATE TABLE `{$this->tableOffices}`");

		/**
		 * Insert new records.
		 */
		$offices = $libraryLists->OfficeLocations();
		foreach ($offices as $office) {
			$this->wpdb->insert(
				$this->tableOffices,
				array(
					'office_id'        => $office['office_id'],
					'office_name'      => $office['office_name'],
					'office_address'   => $office['office_address'],
					'office_site_id'   => $office['office_site_id'],
					'office_site_type' => $office['office_site_type'],
					'office_site_name' => $office['office_site_name'],
				),
				array('%s', '%s', '%s', '%s', '%s', '%s')
			);
		}
	}

	/**
	 * Insert addresses to database.
	 */
	public function insertAddresses($libraryLists)
	{
		/**
		 * Empty table.
		 */
		$this->wpdb->query("TRUNCATE TABLE `{$this->tableAddresses}`");

		/**
		 * Insert new records BG.
		 */
		$addressesBG = $libraryLists->Addresses(100);
		foreach ($addressesBG as $address) {
			$this->wpdb->insert(
				$this->tableAddresses,
				array(
					'site_id'               => trim($address['id']),
					'country_id'            => trim($address['countryId']),
					'main_site_id'          => trim($address['mainSiteId']),
					'type'                  => trim($address['type']),
					'type_en'               => trim($address['typeEn']),
					'name'                  => trim($address['name']),
					'name_en'               => trim($address['nameEn']),
					'municipality'          => trim($address['municipality']),
					'municipality_en'       => trim($address['municipalityEn']),
					'region'                => trim($address['region']),
					'region_en'             => trim($address['regionEn']),
					'post_code'             => trim($address['postCode']),
					'address_nomenclature'  => trim($address['addressNomenclature']),
					'x'                     => trim($address['x']),
					'y'                     => trim($address['y']),
					'serving_days'          => trim($address['servingDays']),
					'serving_office_id'     => trim($address['servingOfficeId']),
					'serving_hub_office_id' => trim($address['servingHubOfficeId'])

				),
				array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
			);
		}

		/**
		 * Insert new records RO.
		 */
		$addressesRO = $libraryLists->Addresses(642);
		foreach ($addressesRO as $address) {
			$this->wpdb->insert(
				$this->tableAddresses,
				array(
					'site_id'               => trim($address['id']),
					'country_id'            => trim($address['countryId']),
					'main_site_id'          => trim($address['mainSiteId']),
					'type'                  => trim($address['type']),
					'type_en'               => trim($address['typeEn']),
					'name'                  => trim($address['name']),
					'name_en'               => trim($address['nameEn']),
					'municipality'          => trim($address['municipality']),
					'municipality_en'       => trim($address['municipalityEn']),
					'region'                => trim($address['region']),
					'region_en'             => trim($address['regionEn']),
					'post_code'             => trim(strlen($address['postCode']) < 6 ? '0' . $address['postCode'] : $address['postCode']),
					'address_nomenclature'  => trim($address['addressNomenclature']),
					'x'                     => trim($address['x']),
					'y'                     => trim($address['y']),
					'serving_days'          => trim($address['servingDays']),
					'serving_office_id'     => trim($address['servingOfficeId']),
					'serving_hub_office_id' => trim($address['servingHubOfficeId'])

				),
				array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
			);
		}
	}

	/**
	 * Ajax save settings.
	 */
	public function saveSettings()
	{
		check_ajax_referer('dpdro_save_settings', 'nonce');

		$json = [
			'error' => true
		];
		if (isset($_POST['action'])) {
			$params = $_POST['params'];
			foreach ($params as $key => $value) {
				$checkSetting = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableSettings} WHERE `key` = %s", $key));
				if ($checkSetting && !empty($checkSetting)) {
					$this->wpdb->update(
						$this->tableSettings,
						array(
							'value'      => $value,
							'updated_at' => date('Y-m-d H:i:s', strtotime('now')),
						),
						array(
							'key' => $key,
						),
						array('%s', '%s'),
						array('%s')
					);
				} else {
					$this->wpdb->insert(
						$this->tableSettings,
						array(
							'key'   => $key,
							'value' => $value,
						),
						array('%s', '%s')
					);
				}
			}
			$json['error'] = false;
			$json['message'] = __('Settings saved successfully.', 'dpdro');
		} else {
			$json['message'] = __('Something went wrong.', 'dpdro');
		}
		echo wp_send_json($json);
		wp_die();
	}

	/**
	 * Ajax save advance settings.
	 */
	public function saveAdvanceSettings()
	{
		check_ajax_referer('dpdro_advance_settings', 'nonce');

		$json = [
			'error' => true
		];
		if (isset($_POST['action'])) {
			$params = $_POST['params'];
			foreach ($params as $key => $value) {
				$checkSetting = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableSettings} WHERE `key` = %s", $key));
				if ($checkSetting && !empty($checkSetting)) {
					$this->wpdb->update(
						$this->tableSettings,
						array(
							'value'      => $value,
							'updated_at' => date('Y-m-d H:i:s', strtotime('now')),
						),
						array(
							'key' => $key,
						),
						array('%s', '%s'),
						array('%s')
					);
				} else {
					$this->wpdb->insert(
						$this->tableSettings,
						array(
							'key'   => $key,
							'value' => $value,
						),
						array('%s', '%s')
					);
				}
			}
			$json['error'] = false;
			$json['message'] = __('Advance settings saved successfully.', 'dpdro');
		} else {
			$json['message'] = __('Something went wrong.', 'dpdro');
		}
		echo wp_send_json($json);
		wp_die();
	}

	/**
	 * Ajax save payment settings.
	 */
	public function savePaymentSettings()
	{
		check_ajax_referer('dpdro_payment_settings', 'nonce');

		$json = [
			'error' => true
		];
		if (isset($_POST['action'])) {
			$params = $_POST['params'];
			foreach ($params as $key => $value) {
				$checkSetting = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->tableSettings} WHERE `key` = %s", $key));
				if ($checkSetting && !empty($checkSetting)) {
					$this->wpdb->update(
						$this->tableSettings,
						array(
							'value'      => $value,
							'updated_at' => date('Y-m-d H:i:s', strtotime('now')),
						),
						array(
							'key' => $key,
						),
						array('%s', '%s'),
						array('%s')
					);
				} else {
					$this->wpdb->insert(
						$this->tableSettings,
						array(
							'key'   => $key,
							'value' => $value,
						),
						array('%s', '%s')
					);
				}
			}
			$json['error'] = false;
			$json['message'] = __('Payment settings saved successfully.', 'dpdro');
		} else {
			$json['message'] = __('Something went wrong.', 'dpdro');
		}
		echo wp_send_json($json);
		wp_die();
	}

	/**
	 * Ajax save tax rate.
	 */
	public function saveTaxRates()
	{
		check_ajax_referer('dpdro_save_tax_rate', 'nonce');

		$json = [
			'error' => true
		];
		if (isset($_POST['action'])) {
			$taxes = $_POST['params'];
			$taxes = json_decode(str_replace("\\", "", $taxes));

			/**
			 * Check for duplicates.
			 */
			if ($this->checkTaxesDuplicate($taxes)) {
				$json['message'] = __('There are taxes with the same values.', 'dpdro');
			} else {

				/**
				 * Empty table.
				 */
				$this->wpdb->query("TRUNCATE TABLE `{$this->tableTaxRates}`");

				/**
				 * Insert new records.
				 */
				foreach ($taxes as $tax) {
					$this->wpdb->insert(
						$this->tableTaxRates,
						array(
							'service_id'       => $tax->service_id,
							'zone_id'          => $tax->zone_id,
							'based_on'         => $tax->based_on,
							'apply_over'       => $tax->apply_over,
							'tax_rate'         => $tax->tax_rate,
							'calculation_type' => $tax->calculation_type,
							'status'           => $tax->status,
						),
						array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
					);
				}
				$json['error'] = false;
				$json['message'] = __('Tax rates settings saved successfully.', 'dpdro');
			}
		} else {
			$json['message'] = __('Something went wrong.', 'dpdro');
		}
		echo wp_send_json($json);
		wp_die();
	}

	/**
	 * Ajax save tax rate offices.
	 */
	public function saveTaxRatesOffices()
	{
		check_ajax_referer('dpdro_save_tax_rates_offices', 'nonce');

		$json = [
			'error' => true
		];
		if (isset($_POST['action'])) {
			$taxes = $_POST['params'];
			$taxes = json_decode(str_replace("\\", "", $taxes));

			/**
			 * Check for duplicates.
			 */
			if ($this->checkTaxesDuplicate($taxes)) {
				$json['message'] = __('There are taxes with the same values.', 'dpdro');
			} else {
				/**
				 * Empty table.
				 */
				$this->wpdb->query("TRUNCATE TABLE `{$this->tableTaxRatesOffices}`");

				/**
				 * Insert new records.
				 */
				foreach ($taxes as $tax) {
					$this->wpdb->insert(
						$this->tableTaxRatesOffices,
						array(
							'service_id'       => $tax->service_id,
							'zone_id'          => $tax->zone_id,
							'based_on'         => $tax->based_on,
							'apply_over'       => $tax->apply_over,
							'tax_rate'         => $tax->tax_rate,
							'calculation_type' => $tax->calculation_type,
							'status'           => $tax->status,
						),
						array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
					);
				}
				$json['error'] = false;
				$json['message'] = __('Tax rates DPDBox settings saved successfully.', 'dpdro');
			}
		} else {
			$json['message'] = __('Something went wrong.', 'dpdro');
		}
		echo wp_send_json($json);
		wp_die();
	}

	/**
	 * Check tax rate duplicate.
	 */
	public function checkTaxesDuplicate($taxes)
	{
		$duplicates = array();
		foreach ($taxes as $tax) {
			if (!empty($duplicates)) {
				foreach ($duplicates as $duplicate) {
					if (
						($duplicate->service_id == $tax->service_id) &&
						($duplicate->zone_id == $tax->zone_id) &&
						($duplicate->based_on == $tax->based_on) &&
						($duplicate->apply_over == $tax->apply_over) &&
						($duplicate->tax_rate == $tax->tax_rate) &&
						($duplicate->status == $tax->status)
					) {
						return true;
					} else {
						array_push($duplicates, $tax);
					}
				}
			} else {
				array_push($duplicates, $tax);
			}
		}
		return false;
	}
}
