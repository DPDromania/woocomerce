<?php

/**
 * The DPD RO functionality of the module.
 */

if (!defined('ABSPATH')) {
	exit;
}

class LibraryNomenclature
{
	/** 
	 * Function init.
	 */
	public function __construct()
	{
	}

	/**
	 * List of payer couriers.
	 */
	public function PayerCourier()
	{
		$list = array(
			'SENDER'      => 'Sender',
			'RECIPIENT'   => 'Recipient',
			'THIRD_PARTY' => 'Third party'
		);
		return $list;
	}

	/**
	 * List of print formats.
	 */
	public function PrintFormat()
	{
		$list = array(
			'pdf'  => 'pdf',
			'zpl'  => 'zpl',
			'html' => 'html'
		);
		return $list;
	}

	/**
	 * List of print paper sizes.
	 */
	public function PrintPaperSize()
	{
		$list = array(
			'A4_4xA6' => 'A4 4xA6',
			'A4'      => 'A4',
			'A6'      => 'A6',
		);
		return $list;
	}

	/**
	 * List of options delivery.
	 */
	public function OptionsDelivery()
	{
		$list = array(
			'OPEN' => __('Open On Payment/Delivery', 'dpdro'),
			'TEST' => __('Test On Payment/Delivery', 'dpdro'),
		);
		return $list;
	}

	/**
	 * List of options delivery courier.
	 */
	public function OptionsDeliveryCourier()
	{
		$list = array(
			'SENDER'      => 'Sender',
			'RECIPIENT'   => 'Recipient',
		);
		return $list;
	}

	/** 
	 * List of options package.
	 */
	public function OptionsPackages()
	{
		$list = array(
			'CUTIE DE CARTON',
			'PALET',
			'PLIC',
			'SAC',
			'CUTIE',
			'FOLIE',
		);
		return $list;
	}

	/** 
	 * List of options contents.
	 */
	public function OptionsContents()
	{
		$list = array(
			'PIESE AUTO',
			'DOCUMENTE',
			'BIROTICA SI PAPETARIE',
			'COSMETICE',
			'MEDICAMENTE',
			'MOBILIER',
			'TEXTILE',
			'ELECTRONICE',
			'ARTICOLE OPTICA',
			'DETERGENTI',
		);
		return $list;
	}
}
