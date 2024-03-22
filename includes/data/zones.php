<?php

/**
 * Namespace: includes/data.
 */

class DataZones
{
	/**
	 * Get WooCommerce zones.
	 */
	public static function zones()
	{
		$zones = WC_Shipping_Zones::get_zones();
		$zonesList = array(
			__('Rest of the World', 'dpdro')
		);
		foreach ($zones as $zone) {
			$zoneKey = array_key_exists('id', $zone) ? 'id' : 'zone_id';
			$zonesList[$zone[$zoneKey]] = $zone['zone_name'];
		}
		return $zonesList;
	}

	/**
	 * Get WooCommerce zones.
	 */
	public static function zoneMatchingPackage($package, $settings)
	{
		$codPackage = array(
			'destination' => array(
				'country'  => $package['destination']['country'],
				'state'    => $package['destination']['state'],
				'postcode' => $package['destination']['postcode']
			)
		);
		$zone = WC_Shipping_Zones::get_zone_matching_package($codPackage);
		$zoneId = $zone->get_zone_id();

		/* 
		 * Data settings.
		 */
		$dataSettings = $settings->getSettings();

		/* 
		 * Zones.
		 */
		$paymentZones = json_decode(str_replace("\\", "", $dataSettings['payment_zones']));

		/* 
		 * Check payment tax.
		 */
		$countryAllowed = array(
			'RO', // Romania  -> ID WOO
			'BG', // Bulgaria -> ID WOO
			'GR', // Grecia   -> ID WOO
			'HU', // Ungaria  -> ID WOO
			'SK', // Slovakia -> ID WOO
			'PL'  // Polonia  -> ID WOO
		);
		if (in_array($package['destination']['country'], $countryAllowed) && is_array($paymentZones)) {
			foreach ($paymentZones as $paymentZone) {
				if ($paymentZone->id == $zoneId && $paymentZone->status && $paymentZone->status == '1') {
					return [
						'currencyCode'   => get_woocommerce_currency(),
						'processingType' => 'CASH',
						'amount'         => WC()->cart->subtotal
					];
				}
			}
		}
		return false;
	}

	/**
	 * Check custom payment tax.
	 */
	public static function checkCustomPayment($package, $settings)
	{
		$codPackage = array(
			'destination' => array(
				'country'  => $package['destination']['country'],
				'state'    => $package['destination']['state'],
				'postcode' => $package['destination']['postcode']
			)
		);
		$zone = WC_Shipping_Zones::get_zone_matching_package($codPackage);
		$zoneId = $zone->get_zone_id();

		/* 
		 * Data settings.
		 */
		$dataSettings = $settings->getSettings();

		/* 
		 * Zones.
		 */
		$paymentZones = json_decode(str_replace("\\", "", $dataSettings['payment_zones']));

		/* 
		 * Check payment tax.
		 */
		$countryAllowed = array(
			'RO', // Romania  -> ID WOO
			'BG', // Bulgaria -> ID WOO
			'GR', // Grecia   -> ID WOO
			'HU', // Ungaria  -> ID WOO
			'SK', // Slovakia -> ID WOO
			'PL'  // Polonia  -> ID WOO
		);
		if (in_array($package['destination']['country'], $countryAllowed) && is_array($paymentZones)) {
			foreach ($paymentZones as $paymentZone) {
				if ($paymentZone->id == $zoneId && $paymentZone->status && $paymentZone->status == '1') {
					if ($paymentZone->type == 'dpdro') {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Get zone id.
	 */
	public static function getZoneId($package)
	{
		$codPackage = array(
			'destination' => array(
				'country'  => $package['destination']['country'],
				'state'    => $package['destination']['state'],
				'postcode' => $package['destination']['postcode']
			)
		);
		$zone = WC_Shipping_Zones::get_zone_matching_package($codPackage);
		$zoneId = $zone->get_zone_id();
		return $zoneId;
	}
}
