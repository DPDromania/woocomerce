<?php

/**
 * Namespace: includes/data/settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DataAddresses
{
    /** 
     * Table name.
     */
    private $name;

    /** 
     * @var wpdb 
     */
    protected $wpdb;

    /** 
     * Function init.
     */
    public function __construct($wpdb)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->name = $this->wpdb->prefix . 'dpdro_addresses';
    }

    /**
     * Get address by country, state and city.
     */
    public function getAddress($country, $state, $city)
    {
        switch ($country) {
            case 642:
                $countryID = 'RO';
                break;
            case 100:
                $countryID = 'BG';
                break;
            default:
                $countryID = $country;
        }
        switch ($country) {
            case 'RO':
                $countryAddress = '642';
                break;
            case 'BG':
                $countryAddress = '100';
                break;
            default:
                $countryAddress = $country;
        }
        $city = strtoupper($this->removeDiactritics($city));
		$states = WC()->countries->get_states($countryID);
		$region = '';
		if (is_array($states) && isset($states[$state])) {
			$region = strtoupper ($this->removeDiactritics($states[$state]));
		}

        $response = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->name} WHERE `country_id` = %s AND `region` = %s AND `name` = %s", $countryAddress, $region, $city));
        return $response;
    }

    /**
     * Get address by country, state and search.
     */
    public function getAddressSearch($country, $state, $search)
    {
        switch ($country) {
            case 'RO':
                $countryID = '642';
                break;
            case 'BG':
                $countryID = '100';
                break;
            default:
                $countryID = $country;
        }
        $region = strtoupper($this->removeDiactritics($state));
        $search = strtoupper($this->removeDiactritics($search));
        $responseExactName = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->name} WHERE `country_id` = %s AND `region` = %s AND `name` = %s ORDER BY `name` DESC", $countryID, $region, $search));
        if ($responseExactName && !empty($responseExactName)) {
            return $responseExactName;
        }
        $responseLikeName = $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->name} WHERE `country_id` = %s AND `region` = %s AND `name` LIKE %s ORDER BY `name` DESC", $countryID, $region, '%' . $search . '%'));
        return $responseLikeName;
    }

    /**
     * Get address by postcode.
     */
    public function getAddressByPostcode($postcode)
    {
        $response = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->name} WHERE `post_code` = %s", $postcode));
        return $response;
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
}
