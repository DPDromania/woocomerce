<?php

/**
 * Namespace: includes/woo.
 */

if (!defined('ABSPATH')) {
	exit;
}

class WooApi
{
	/**
	 * Global database.
	 */
	private $wpdb;

	/** 
	 * Package.
	 */
	private $package;

	/** 
	 * Package.
	 */
	private $options;

	/** 
	 * Function init.
	 */
	public function __construct($wpdb, $package, $options)
	{
		$this->wpdb = $wpdb;
		$this->package = $package;
		$this->options = $options;
	}

	/**
	 * Round a kg weight to the precision accepted by the DPD API (2 decimals,
	 * 0.00 - 999.99). Without this, repeated float additions emit values like
	 * 1.2000000000000002 which the API rejects (MSG533).
	 */
	private static function normalizeWeight($weight)
	{
		return round((float) $weight, 2);
	}

	/**
	 * Total weight.
	 */
	public function totalWeight()
	{
		/** 
		 * Products.
		 */
		$products = $this->package['contents'];
		$totalWeight = 0.0;
		if (!empty($products)) {
			foreach ($products as $item) {
				$product = $item['data'];
				$productWeight = $product->get_weight();
				if (empty((float)$productWeight) && $this->options['use_default_weight'] == '1') {
					$productWeight = $this->options['default_weight'];
				}
				$productQuantity = $item['quantity'];
				$productWeight = wc_get_weight($productWeight, 'kg', get_option('woocommerce_weight_unit'));
				for ($i = 0; $i < (int) $productQuantity; $i++) {
					$totalWeight = $totalWeight + $productWeight;
				}
			}
		}
		return self::normalizeWeight($totalWeight);
	}

	/** 
	 * Prepare parcels.
	 */
	public function prepareParcels($serviceId, $packagingMethod = 'one', $country = null)
	{
		$products = $this->package['contents'];
		$productsShipping = [];
		if ($products && is_array($products) && !empty($products)) {
			foreach ($products as $item) {
				$product = $item['data'];
				$productWeight = $product->get_weight();
				if (empty((float)$productWeight) && $this->options['use_default_weight'] == '1') {
					$productWeight = $this->options['default_weight'];
				}
				$productQuantity = $item['quantity'];
				$productWeight = wc_get_weight($productWeight, 'kg', get_option('woocommerce_weight_unit'));
				for ($i = 0; $i < (int) $productQuantity; $i++) {
					array_push($productsShipping, [
						'weight' => $productWeight,
						'width'  => $product->get_width(),
						'depth'  => $product->get_length(),
						'height' => $product->get_height()
					]);
				}
			}
		}
		$parcels = [];

		if ($serviceId != 2303  && (($serviceId == 2212 && ($country == 'HU' || $country == 'BG')) || $serviceId == '2412' || $packagingMethod == 'all')) {

			$index = 0;
			$seqNo = 1;
			foreach ($productsShipping as $product) {
				if ($product['weight'] > 0) {
					$parcels[$index] = [
						'seqNo'  => (int) $seqNo,
						'weight' => self::normalizeWeight($product['weight']),
						'size'   => [
							'width' => (float) $product['width'],
							'depth' => (float) $product['depth'],
							'height' => (float) $product['height'],
						],
					];
					$index++;
					$seqNo++;
				}
			}
		} else {
			$groupsWeight = [];
			$groupedDimensions = [];
			sort($productsShipping);
			if ($productsShipping && is_array($productsShipping)  && !empty($productsShipping)) {
				$count = 0;
				foreach ($productsShipping as $productShipping) {
					if (!isset($groupsWeight[$count])) {
						$groupsWeight[$count] = 0;
					}

					if (!isset($groupedDimensions[$count])) {
						$groupedDimensions[$count]['width'] = (float) $productShipping['width'];
						$groupedDimensions[$count]['depth'] = (float) $productShipping['depth'];
						$groupedDimensions[$count]['height'] = (float) $productShipping['height'];
					} else {
						if ( (float) $productShipping['width'] > $groupedDimensions[$count]['width'] ) {
							$groupedDimensions[$count]['width'] = (float) $productShipping['width'];
						}

						if ( (float) $productShipping['depth'] > $groupedDimensions[$count]['depth'] ) {
							$groupedDimensions[$count]['depth'] = (float) $productShipping['depth'];
						}

						if ( (float) $productShipping['height'] > $groupedDimensions[$count]['height'] ) {
							$groupedDimensions[$count]['height'] = (float) $productShipping['height'];
						}
					}

					if ($groupsWeight[$count] + (float) $productShipping['weight'] > (float) $this->options['max_weight']) {
						$count++;
						$groupsWeight[$count] = (float) $productShipping['weight'];
					} else {
						$groupsWeight[$count] += (float)$productShipping['weight'];
					}
				}
			}
			if ($groupsWeight && is_array($groupsWeight) && !empty($groupsWeight)) {
				$index = 0;
				$seqNo = 1;
				foreach ($groupsWeight as $weight) {
					if ($weight > 0) {
						$parcels[$index] = [
							'seqNo'  => (int) $seqNo,
							'weight' => self::normalizeWeight($weight),
							'size' => (isset($groupedDimensions[$index])) ? $groupedDimensions[$index] : null
						];
						$index++;
						$seqNo++;
					}
				}
			}
		}
		return $parcels;
	}

	/** 
	 * Get tax rate by service id.
	 */
	public function taxRate($serviceId, $package)
	{
		$shippingZone = WC_Shipping_Zones::get_zone_matching_package($package);
		if ($shippingZone) {
			$zoneId = $shippingZone->get_zone_id();
			$applyOverWeight = 0;
			$applyOverPrice = (float) WC()->cart->cart_contents_total;
			$weightUnit = get_option('woocommerce_weight_unit');
			if ($package['contents'] && is_array($package['contents']) && !empty($package['contents'])) {
				foreach ($package['contents'] as $item) {
					$product = $item['data'];
					$productWeight = $product->get_weight();
					$productQuantity = $item['quantity'];
					$productWeight = wc_get_weight($productWeight, 'kg', $weightUnit);
					for ($i = 0; $i < (int) $productQuantity; $i++) {
						$applyOverWeight = (float) $applyOverWeight + (float) $productWeight;
					}
				}
			}
			$query = $this->wpdb->get_results(
				$this->wpdb->prepare("
					(
						SELECT * 
						FROM `{$this->wpdb->prefix}order_dpd_tax_rates` 
						WHERE 
							`service_id` = " . (int) $serviceId . " AND
							`zone_id` = " . (int) $zoneId . " AND
							`apply_over` <= " . $applyOverPrice . " AND
							status = 1
						ORDER BY `apply_over` DESC
					) UNION (
						SELECT * 
						FROM `{$this->wpdb->prefix}order_dpd_tax_rates`
						WHERE 
							`service_id` = " . (int) $serviceId . " AND
							`zone_id` = " . (int) $zoneId . " AND
							`apply_over` <= " . $applyOverWeight . " AND
							status = 1
						ORDER BY `apply_over` DESC
					)
				", array())
			);
			$taxRates = array();
			if (!empty($query)) {
				foreach ($query as $tax) {
					if ($tax->based_on) {
						if ((float) $tax->apply_over <= (float) $applyOverPrice) {
							$taxRates[$tax->apply_over] = $tax;
						}
					} else {
						if ((float) $tax->apply_over <= (float) $applyOverWeight) {
							$taxRates[$tax->apply_over] = $tax;
						}
					}
				}
			}
			arsort($taxRates);
			if (!empty($taxRates)) {
				return reset($taxRates);
			}
		}
		return false;
	}

	/**
	 * Get tax rate office by service id.
	 */
	public function taxRateOffice($serviceId, $package, $pickup)
	{
		/**
		 * Data Lists
		 */
		$dataLists = new DataLists($this->wpdb);

		/**
		 * Office
		 */
		$office = $dataLists->getOfficeById($pickup);
		if ($office && !empty($office->office_site_id)) {
			$hadRows = false;
			$rate = $this->officeRateForZone($serviceId, $office->office_site_id, $package, $hadRows);

			/**
			 * Preserve original behavior: only fall back to the default zone when the
			 * office-specific zone has no configured rows at all.
			 */
			if (!$hadRows) {
				$rate = $this->officeRateForZone($serviceId, '000000001', $package);
			}
			return $rate ? $rate : false;
		}

		/**
		 * Service 2212 (DPD REGIONAL CEE) is a cross-border service: the selected
		 * pickup point is a locker / parcelshop in the destination country and is
		 * not stored in the offices table, so it cannot be resolved above. Fall
		 * back to the default "All / Rest DPDBox" zone (000000001) so the admin
		 * rate still applies, for every destination country. The row's own
		 * calculation_type (Fixed / Percentage) is applied by the caller.
		 */
		if (!empty($pickup) && (string) $serviceId === '2212') {
			return $this->officeRateForZone($serviceId, '000000001', $package);
		}

		return false;
	}

	/**
	 * Best-matching enabled office tax-rate row for a service + zone.
	 *
	 * Applies the price/weight tier rules (based_on / apply_over) against the
	 * current cart and returns the winning row object, or false when none
	 * qualifies. $hadRows is set (by reference) to whether the zone had ANY
	 * enabled rows before tier filtering, so the caller can decide whether to
	 * fall back to the default zone.
	 */
	private function officeRateForZone($serviceId, $zoneId, $package, &$hadRows = null)
	{
		$applyOverWeight = 0;
		$applyOverPrice = (float) WC()->cart->cart_contents_total;
		$weightUnit = get_option('woocommerce_weight_unit');
		if ($package['contents'] && is_array($package['contents']) && !empty($package['contents'])) {
			foreach ($package['contents'] as $item) {
				$product = $item['data'];
				$productWeight = $product->get_weight();
				$productQuantity = $item['quantity'];
				$productWeight = wc_get_weight($productWeight, 'kg', $weightUnit);
				for ($i = 0; $i < (int) $productQuantity; $i++) {
					$applyOverWeight = (float) $applyOverWeight + (float) $productWeight;
				}
			}
		}

		$table = $this->wpdb->prefix . 'order_dpd_tax_rates_offices';
		$rows = $this->wpdb->get_results(
			"(
				SELECT * FROM `{$table}`
				WHERE
					`service_id` = " . (int) $serviceId . " AND
					`zone_id` = " . (int) $zoneId . " AND
					`apply_over` <= " . $applyOverPrice . " AND
					status = 1
				ORDER BY `apply_over` DESC
			) UNION (
				SELECT * FROM `{$table}`
				WHERE
					`service_id` = " . (int) $serviceId . " AND
					`zone_id` = " . (int) $zoneId . " AND
					`apply_over` <= " . $applyOverWeight . " AND
					status = 1
				ORDER BY `apply_over` DESC
			)"
		);
		$hadRows = !empty($rows);

		$taxRates = array();
		if (!empty($rows)) {
			foreach ($rows as $tax) {
				if ($tax->based_on) {
					if ((float) $tax->apply_over <= (float) $applyOverPrice) {
						$taxRates[$tax->apply_over] = $tax;
					}
				} else {
					if ((float) $tax->apply_over <= (float) $applyOverWeight) {
						$taxRates[$tax->apply_over] = $tax;
					}
				}
			}
		}
		arsort($taxRates);
		if (!empty($taxRates)) {
			return reset($taxRates);
		}
		return false;
	}
}
