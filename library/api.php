<?php

/**
 * The DPD functionality of the module.
 */

if (!defined('ABSPATH')) {
	exit;
}

class LibraryApi
{
	/** 
	 * Credentials.
	 */
	private $username;
	private $password;

	/** 
	 * Apiurl.
	 */
	private $apiUrl;

	/** 
	 * Function init.
	 */
	public function __construct($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
		$this->apiUrl = 'https://api.dpd.ro/v1/';
	}

	/**
	 * DPD RO api request.
	 */
	public function request($parameters, $json = true)
	{
		$parameters['data']['userName'] = $this->username;
		$parameters['data']['password'] = $this->password;
		$data = json_encode($parameters['data']);

		/**
		 * Initialize cache.
		 */
		$key = 'dpd_';
		$time = floor(floatval(date('i')) / 10);
		$key =  $key . 'cache__' . date('Y_m_d_H_') . $time . '_00' . '__' . sha1($this->apiUrl . $parameters['api'] . '/' . $data);
		$cache = $this->getCache($key);
		if ($cache) {
			return $cache;
		}

		$connection = curl_init();
		curl_setopt_array($connection, array(
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_URL => $this->apiUrl . $parameters['api'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $parameters['method'],
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_HTTPHEADER => array(
				"cache-control: no-cache",
				"content-type: application/json"
			),
		));
		$success = curl_exec($connection);
		$error = curl_error($connection);
		curl_close($connection);

		/**
		 * Initialize logs.
		 */
		$logs = [
			'request' => $parameters['data'],
			'timestamp' => date('Y-m-d h:i:s')
		];
		if ($error) {
			$logs['response'] = $error;
		} else {
			$logs['response'] = 'Success!';
		}
		$jsonLogs = json_encode($logs);
		file_put_contents(PLUGIN_LOGS_DIR_DPDRO . '/logs.json', print_r($jsonLogs, true) . "\n", FILE_APPEND);

		/**
		 * Return response.
		 */
		if ($error) {
			return $error;
		} else {
			$cacheDecode = json_decode($success, true);
			if (json_last_error() == JSON_ERROR_NONE) {
                $this->setCache($key, $cacheDecode);
            } else if ($json === 'csv') {
                $this->setCache($key, $success);
            } else {
                $json = false;
            }
			if ($json) {
				return $this->getCache($key);
			} else {
				return $success;
			}
		}
	}

	/**
	 * Check connection.
	 */
	public function check()
	{
		$parameters = [
			'api' => 'location/country',
			'method' => 'POST',
			'data' => [
				'name' => 'ROMA'
			]
		];
		$request = $this->request($parameters);
		if (is_array($request) && !empty($request) && array_key_exists('error', $request) && !empty($request['error'])) {
			return false;
		}
		return true;
	}

	/**
	 * Set cache.
	 */
	private function setCache($key, $data)
	{
		if (!is_dir(PLUGIN_LOGS_DIR_DPDRO)) {
			mkdir(PLUGIN_LOGS_DIR_DPDRO);
		}
		$dir = PLUGIN_LOGS_DIR_DPDRO . '/' . DIRECTORY_SEPARATOR . date('Y-m-d');
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		if (is_dir($dir)) {
			$file = $dir . DIRECTORY_SEPARATOR . $key;
			$log = $dir . DIRECTORY_SEPARATOR . '000_cache_log';
			file_put_contents($file, serialize($data));
			file_put_contents($log, date('Y-m-d H:i:s') . "\t" . 'saving: ' . "\t" . $key . "\n", FILE_APPEND);
		}
	}

	/**
	 * Get cache.
	 */
	private function getCache($key)
	{
		if (!is_dir(PLUGIN_LOGS_DIR_DPDRO)) {
			mkdir(PLUGIN_LOGS_DIR_DPDRO);
		}
		$dir = PLUGIN_LOGS_DIR_DPDRO . '/' . DIRECTORY_SEPARATOR . date('Y-m-d');
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		$log = $dir . DIRECTORY_SEPARATOR . '000_cache_log';
		if (is_dir($dir)) {
			$file = $dir . DIRECTORY_SEPARATOR . $key;
			if (is_file($file)) {
				file_put_contents($log, date('Y-m-d H:i:s') . "\t" .  'getting: ' .  "\t" .  $key . "\n", FILE_APPEND);
				$content = file_get_contents($file);
				return unserialize($content);
			}
		}
		file_put_contents($log, date('Y-m-d H:i:s') . "\t" . 'missed: ' . "\t" .  $key . "\n", FILE_APPEND);
		return false;
	}

	/** 
	 * Calculate service tax by service id.
	 */
	public function calculate($serviceId, $options, $addresses)
	{
		/** 
		 * Parameters.
		 */
		$parameters = [
			'api'    => 'calculate',
			'method' => 'POST',
			'data'   => [
					'service' => [
					'serviceIds'           => array($serviceId),
					'autoAdjustPickupDate' => true,
				],
				'content' => [
					'package'      => $options['packages'],
					'contents'     => $options['contents'],
					'parcelsCount' => 1,
					'totalWeight'  => $options['total_weight'],
					'parcels'      => array(
						0 => [
							'seqNo'  => 1,
							'weight' => $options['total_weight'],
						]
					),
				],
				'payment' => [
					'courierServicePayer' => $options['courier_service_payer']
				],
				'recipient'    => [],
				'shipmentNote' => '',
			]
		];
		if ($options['courier_service_payer'] === 'THIRD_PARTY') {
			$parameters['data']['payment']['thirdPartyClientId'] = $options['id_payer_contract'];
		}

		/** 
		 * Parcels.
		 */
		if (((float) $options['total_weight'] > (float) $options['max_weight']) || $serviceId == '2412' ) {
			$parameters['data']['content']['parcelsCount'] = (int) $options['parcels'];
			$parameters['data']['content']['parcels'] = $options['parcels'];
		}

        if (count($options['parcels']) > 1 && $options['packaging_method'] == 'all') {
            $parameters['data']['content']['parcelsCount'] = count($options['parcels']);
            $parameters['data']['content']['parcels'] = $options['parcels'];
        }

		/** 
		 * Address.
		 */
		$allowedCountryIds = [100, 300, 348, 616, 703, 705, 203, 191, 40, 380, 276, 724, 250, 528, 56, 208, 233, 442, 428, 440, 246, 620, 752, 642];
		$country = $this->countryByID($options['package']['country']);
		if (isset($country) && !empty($country) && in_array((int)$country['id'], $allowedCountryIds, true)) {
			if ($options['client_contracts'] != '' && $options['client_contracts'] != '0') {
				$parameters['data']['sender']['clientId'] = $options['client_contracts'];
			}
			if ($options['office_locations'] != '' && $options['office_locations'] != '0') {
				$parameters['data']['sender']['dropoffOfficeId'] = $options['office_locations'];
			}
			if ($options['dpdro_pickup'] && !empty($options['dpdro_pickup'])) {
				$parameters['data']['recipient']['privatePerson'] = false;
				$parameters['data']['recipient']['pickupOfficeId'] = $options['dpdro_pickup'];
			} else {
				$parameters['data']['recipient'] = [
					'addressLocation' => [
						'countryId' => (int) $country['id']
					],
					'privatePerson' => false
				];
				$city = $addresses->getAddress($country['id'], $options['package']['state'], $options['package']['city']);
				if ($city && isset($city->site_id) && !empty($city->site_id)) {
					$parameters['data']['recipient']['addressLocation']['siteId'] = (int) $city->site_id;
				} else {
					$city = $addresses->getAddressByPostcode($options['package']['postcode']);
					if ($city && !empty($city)) {
						$parameters['data']['recipient']['addressLocation']['siteId'] = $city->site_id;
					} else {
						$parameters['data']['recipient']['addressLocation']['siteName'] = (string) $options['package']['city'];
						if (array_key_exists('postCodeFormats', $country) && !empty($country['postCodeFormats']) && is_array($country['postCodeFormats'])) {
							$parameters['data']['recipient']['addressLocation']['postCode'] = (string) $options['package']['postcode'];
						}
					}
				}
			}
		} else {
			$parameters['data']['recipient'] = [
				'addressLocation' => [
					'countryId' => (int) $country['id'],
					'siteName'  => (string) $options['package']['city']
				],
				'privatePerson' => true
			];
			if (array_key_exists('postCodeFormats', $country) && !empty($country['postCodeFormats']) && is_array($country['postCodeFormats'])) {
				$parameters['data']['recipient']['addressLocation']['postCode'] = (string) $options['package']['postcode'];
			}
		}

		/** 
		 * User.
		 */
		if ($options['customer_phone'] && !empty($options['customer_phone'])) {
			$parameters['data']['recipient']['phone1'] = array(
				'number' => (string) $options['customer_phone']
			);
		}
		if ($options['customer_email'] && !empty($options['customer_email'])) {
			$parameters['data']['recipient']['email'] = (string) $options['customer_email'];
		}

		/** 
		 * Payment.
		 */
		if ($options['chosen_payment'] && $options['chosen_payment'] == 'cod' && $options['cod']) {
			$parameters['data']['service']['additionalServices']['cod'] = $options['cod'];
			if ($options['courier_service_payer'] == 'RECIPIENT' || !$options['include_shipping_price']) {

				/** 
				 * Recipient pay the tax.
				 */
			} else {
				//$parameters['data']['service']['additionalServices']['cod']['includeShippingPrice'] = true;
			}
			$parameters['data']['service']['additionalServices']['cod']['currencyCode'] = get_woocommerce_currency();
			$parameters['data']['service']['additionalServices']['cod']['processingType'] = 'CASH';
		}
		if (!$options['dpdro_pickup_type'] || ($options['dpdro_pickup_type'] && !empty($options['dpdro_pickup_type']) && $options['dpdro_pickup_type'] === 'OFFICE')) {
			if ($options['test_or_open'] && !empty($options['test_or_open'])) {
				if ($serviceId == '2505' || $serviceId == '2002' || $serviceId == '2113' || $serviceId == '2005') {
					$parameters['data']['service']['additionalServices']['obpd'] = [
						'option'                  => $options['test_or_open'],
						'returnShipmentServiceId' => (int) $serviceId,
						'returnShipmentPayer'     => $options['test_or_open_courier']
					];
				}
			}
		}

		/** 
		 * Sender payer insurance.
		 */
		if ($options['sender_payer_insurance'] === 'yes' || $options['sender_payer_insurance'] === '1') {
			$parameters['data']['service']['additionalServices']['declaredValue']['amount'] = number_format((float) $options['contents_cost'], 2, '.', '');
		}

		/** 
		 * Request.
		 */
		$results = $this->request($parameters);
		if (is_array($results) && array_key_exists('calculations', $results) && !empty($results['calculations'])) {
			return $results['calculations'][0];
		}
		return false;
	}

	/**
	 * Get DPD RO contract payment tax.
	 */
	public function getPaymentTax()
	{
		$paymentTax = '0';
		$parameters = [
			'api'    => 'calculate',
			'method' => 'POST',
			'data'   => array(
				'service'  => array(
					'serviceIds' => array('2505'),
					'autoAdjustPickupDate' => true,
					'additionalServices'   => array(
						'cod'           => array(
							'currencyCode'         => 'RON',
							'processingType'       => 'CASH',
							'amount'               => 1,
							'includeShippingPrice' => true,
						),
						'declaredValue' => array(
							'amount'               => 1,
						),
					)
				),
				'content'  => array(
					'package'      => 'DPD RO',
					'contents'     => 'DPD RO',
					'parcelsCount' => 1,
					'totalWeight'  => 1,
					'parcels'      => array(
						[
							'seqNo'  => 1,
							'weight' => 1,
						]
					),
				),
				'payment'  => array(
					'courierServicePayer' => 'SENDER',
				),
				'recipient' => array(
					'addressLocation' => array(
						'countryId' => 642,
						'siteId'    => 642279132,
					),
					'privatePerson'   => false,
					'phone1'          => array(
						'number'    => '0700000000',
					),
					'email'           => 'woo@dpd.ro',
				),
				'shipmentNote' => '',
			),
		];
		$results = $this->request($parameters);
		if (is_array($results) && array_key_exists('calculations', $results) && !empty($results['calculations'])) {
			$paymentPrice = $results['calculations'][0]['price']['details']['codPremium']['amount'];
			$paymentVat = $results['calculations'][0]['price']['details']['codPremium']['vatPercent'];
			$paymentTax = number_format($paymentPrice + ($paymentPrice * $paymentVat), 2, '.', '');
		}
		return $paymentTax;
	}

	/** 
	 * Get country data by id.
	 */
	public function countryByID($country)
	{
		/** 
		 * Response.
		 */
		$response = [
			'error' => true
		];

		if ($country && !empty($country)) {
			$parameters = [
				'api' => 'location/country',
				'method' => 'POST',
				'data' => [
					'isoAlpha2' => $country
				]
			];
			$request = $this->request($parameters);
			if (!is_array($request) || array_key_exists('error', $request)) {
				return $request;
			} else {
				$response = $request['countries'][0];
			}
		}
		return $response;
	}

	/** 
	 * Order address validation.
	 */
	public function addressValidation($address)
	{
		/** 
		 * Parameters.
		 */
		$parameters = [
			'api' => 'validation/address',
			'method' => 'POST',
			'data' => [
				'address' => []
			]
		];

		/** 
		 * Country data.
		 */
		$countryData = $this->countryByID($address['country']);
		if ($countryData && !empty($countryData)) {
			$parameters['data']['address']['countryId'] = $countryData['id'];
			if (array_key_exists('postCodeFormats', $countryData) && !empty($countryData['postCodeFormats']) && is_array($countryData['postCodeFormats'])) {
				if ($address['postcode'] && !empty($address['postcode'])) {
					$parameters['data']['address']['postCode'] = trim($address['postcode']);
				}
			}
		}

		/** 
		 * Address.
		 */
		if (isset($address['cityId']) && !empty($address['cityId'])) {
			$parameters['data']['address']['siteId'] = $address['cityId'];
		} else {
			$parameters['data']['address']['siteName'] = $this->removeDiactritics($address['cityName']);
		}
		$parameters['data']['address']['streetNo'] = 0;
		$parameters['data']['address']['streetId'] = $address['streetId'];
		if (isset($address['number']) &&  !empty($address['number'])) {
			$parameters['data']['address']['streetNo'] = (int) $address['number'];
		}

		/** 
		 * Response.
		 */
		$response = $this->request($parameters);
		return $response;
	}

	/**
	 * Create shipment
	 */
	public function createShipment($data)
	{
		/** 
		 * Add client system id.
		 */
		$data['clientSystemId'] = '221220070700';

		/** 
		 * Parameters.
		 */
		$parameters = [
			'api' => 'shipment',
			'method' => 'POST',
			'data' => $data
		];

		/** 
		 * Response.
		 */
		$response = $this->request($parameters);
		return $response;
	}

	/**
	 * Delete shipment
	 */
	public function deleteShipment($shipmentId)
	{
		/** 
		 * Parameters.
		 */
		$parameters = [
			'api' => 'shipment',
			'method' => 'DELETE',
			'data' => [
				'shipmentId' => $shipmentId,
				'comment' => 'Delete by woocommerce sender',
			]
		];

		/** 
		 * Response.
		 */
		$response = $this->request($parameters);
		return $response;
	}

	/**
	 * Request courier
	 */
	public function requestCourier($orders)
	{
		/** 
		 * Parameters.
		 */
		$parameters = [
			'api'    => 'pickup',
			'method' => 'POST',
			'data'   => [
				'explicitShipmentIdList' => $orders,
				'visitEndTime'           => '19:00',
				'autoAdjustPickupDate'   => true
			]
		];

		/** 
		 * Response.
		 */
		$response = $this->request($parameters);
		return $response;
	}

	/**
	 * Search street
	 */
	public function searchStreet($country, $city, $search)
	{
		/** 
		 * Parameters.
		 */

		$countryId = DPDUtil::getCountryId($country);
		$parameters = [
			'api' => 'location/street',
			'method' => 'POST',
			'data' => [
				'countryId' => $countryId,
				'siteId'    => $city,
				'name'      => $this->removeDiactritics($search),
			]
		];

		/** 
		 * Request.
		 */
		$request = $this->request($parameters);
		if (isset($request) && $request !== '') {
			if (isset($request['streets']) && !empty($request['streets'])) {
				foreach ($request['streets'] as $key => $street) {
					$request['streets'][$key]['text'] = $street['type'] . ' ' . $street['name'];
				}
				return $request['streets'];
			}
		}
		return false;
	}

	/** 
	 * Remove diacritics.
	 */
	public function removeDiactritics($string)
	{
		if ($string) {
			$keywords = [
				'Ă' => 'A', 'ă' => 'a', 'Â' => 'A', 'â' => 'a', 'Î' => 'I', 'î' => 'i', 'Ș' => 'S', 'ș' => 's', 'Ț' => 'T', 'ț' => 't',
				'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
				'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
				'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
				'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
				'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y'
			];
			$string = strtr($string, $keywords);
			$string = trim($string);
		}
		return $string;
	}

	/**
	 * Print labels.
	 */
	public function printLabels($data)
	{
		$parameters = [
			'api'    => 'print',
			'method' => 'POST',
			'data'   => $data
		];
		$response = $this->request($parameters, false);
		return $response;
	}

	/**
	 * Print voucher.
	 */
	public function printVoucher($shipmentIds)
	{
		$parameters = [
			'api'    => 'print/voucher',
			'method' => 'POST',
			'data'   => [
				'shipmentIds' => [$shipmentIds],
			]
		];
		$response = $this->request($parameters, false);
		return $response;
	}


}
