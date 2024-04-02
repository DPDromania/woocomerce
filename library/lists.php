<?php

/**
 * The DPD RO functionality of the module.
 */

if (!defined('ABSPATH')) {
	exit;
}

class LibraryLists
{
	/** 
	 * DPD RO api.
	 */
	private $api;

	/** 
	 * Function init.
	 */
	public function __construct($api)
	{
		$this->api = $api;
	}

	/**
	 * List of services.
	 */
	public function Services()
	{
		$parameters = [
			'api'    => 'services',
			'method' => 'POST'
		];
		$request = $this->api->request($parameters);
		$response = array();
		if (is_array($request) && !empty($request) && array_key_exists('services', $request) && !empty($request['services'])) {
			foreach ($request['services'] as $service) {
				array_push($response, [
					'service_id'   => $service['id'],
					'service_name' => $service['name'],
				]);
			}
		}
		return $response;
	}

	/**
	 * List of clients contracts.
	 */
	public function ClientContracts()
	{
		$parameters = [
			'api'    => 'client/contract',
			'method' => 'POST'
		];
		$response = array();
		$request = $this->api->request($parameters);
		if (is_array($request) && !empty($request) && array_key_exists('clients', $request) && !empty($request['clients'])) {
			foreach ($request['clients'] as $client) {
				array_push($response, [
					'client_id'      => $client['clientId'],
					'client_name'    => $client['clientName'],
					'client_address' => $client['address']['fullAddressString'],
				]);
			}
		}
		return $response;
	}

	/**
	 * List of office locations.
	 */
	public function OfficeLocations()
	{
		$parameters = [
			'api'    => 'location/office',
			'method' => 'POST'
		];
		$response = array();
		$request = $this->api->request($parameters);
		if (is_array($request) && !empty($request) && array_key_exists('offices', $request) && !empty($request['offices'])) {
			foreach ($request['offices'] as $office) {
				array_push($response, [
					'office_id'        => $office['id'],
					'office_name'      => $office['name'],
					'office_address'   => $office['address']['fullAddressString'],
					'office_site_id'   => $office['siteId'],
					'office_site_type' => $office['address']['siteType'],
					'office_site_name' => $office['address']['siteName'],
				]);
			}
		}
		return $response;
	}

    public function Cities($countryId)
    {
        $parameters = [
            'api'    => 'location/site/csv/' . $countryId,
            'method' => 'POST'
        ];
        $request = $this->api->request($parameters, 'csv');
        $lines = explode(PHP_EOL, $request);
        $cities = [];
        if (is_array($lines) && !empty($lines) ) {
            foreach ($lines as $index => $line) {
                if ($index == 0) {
                    continue;
                }
                $data = explode(',', $line);
                array_push($cities, [
                    'city_id'       => $data[0],
                    'country_id'    => $data[1],
                    'name'          => $data[6],
                    'municipality'  => $data[8],
                    'region'        => $data[10],
                    'postal_code'   => $data[11]
                ]);
            }
        }
        return $cities;
    }

	/** 
	 * List addresses from csv.
	 */
	public function Addresses($country)
	{
		$response = array();
		$fileRO = dirname(__FILE__) . '/sites/' . $country . '.csv';
        $fileROContent = array_map("str_getcsv", file($fileRO, FILE_SKIP_EMPTY_LINES));
        $fileROHeader = array_shift($fileROContent);
        foreach ($fileROContent as $i => $row) {
            $response[$i] = array_combine($fileROHeader, $row);
        }
        return $response;
	}
}
