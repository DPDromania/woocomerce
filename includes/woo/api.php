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
		return $totalWeight;
	}

	/** 
	 * Prepare parcels.
	 */
	public function prepareParcels($serviceId, $packagingMethod = 'one')
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
		if ($serviceId == '2412' || $packagingMethod = 'all') {
			$index = 0;
			$seqNo = 1;
			foreach ($productsShipping as $product) {
				if ($product['weight'] > 0) {
					$parcels[$index] = [
						'seqNo'  => (int) $seqNo,
						'weight' => (float) $product['weight'],
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
			sort($productsShipping);
			if ($productsShipping && is_array($productsShipping)  && !empty($productsShipping)) {
				$count = 0;
				foreach ($productsShipping as $productShipping) {
					if (!isset($groupsWeight[$count])) {
						$groupsWeight[$count] = 0;
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
							'weight' => (float) $weight,
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
			$zoneId = $office->office_site_id;
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
						FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices` 
						WHERE 
							`service_id` = " . (int) $serviceId . " AND
							`zone_id` = " . (int) $zoneId . " AND
							`apply_over` <= " . $applyOverPrice . " AND
							status = 1
						ORDER BY `apply_over` DESC
					) UNION (
						SELECT * 
						FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices`
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
			} else {
				$zoneId = '000000001';
				$query = $this->wpdb->get_results(
					$this->wpdb->prepare(
						"
						(
							SELECT * 
							FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices` 
							WHERE 
								`service_id` = " . (int) $serviceId . " AND
								`zone_id` = " . (int) $zoneId . " AND
								`apply_over` <= " . $applyOverPrice . " AND
								status = 1
							ORDER BY `apply_over` DESC
						) UNION (
							SELECT * 
							FROM `{$this->wpdb->prefix}order_dpd_tax_rates_offices`
							WHERE 
								`service_id` = " . (int) $serviceId . " AND
								`zone_id` = " . (int) $zoneId . " AND
								`apply_over` <= " . $applyOverWeight . " AND
								status = 1
							ORDER BY `apply_over` DESC
						)
						",
						array()
					)
				);
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
			}
			arsort($taxRates);
			if (!empty($taxRates)) {
				return reset($taxRates);
			}
		}
		return false;
	}
}
