<?php

class DPDUtil {
	public static function getCountryId($code)
	{
		switch ($code) {
			case 'RO':
				return 642;
			case 'BG':
				return 100;
			case 'GR':
				return 300;
			case 'HU':
				return 348;
			case 'PL':
				return 616;
			case 'SL':
				return 703;
			case 'SK':
				return 705;
			case 'CZ':
				return 203;
			case 'HR':
				return 191;
			case 'AT':
				return 40;
			case 'IT':
				return 380;
			case 'DE':
				return 276;
			case 'ES':
				return 724;
			case 'FR':
				return 250;
			case 'NL':
				return 528;
			case 'BE':
				return 56;
			case 'EE':
				return 233;
			case 'DK':
				return 208;
			case 'LU':
				return 442;
			case 'LV':
				return 428;
			case 'LT':
				return 440;
			case 'FI':
				return 246;
			case 'PT':
				return 620;
			case 'SE':
				return 752;
			default:
				return 642;
		}
	}

	public static function getAllowedCountryCodes()
	{
		return ['RO', 'BG', 'GR', 'HU', 'PL', 'SL', 'SK', 'CZ', 'HR', 'AT', 'IT', 'DE', 'ES', 'FR','NL', 'BE', 'EE', 'DK', 'LU', 'LV', 'LT', 'FI', 'PT', 'SE'];
	}
}