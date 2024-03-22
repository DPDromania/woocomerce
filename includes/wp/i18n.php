<?php

/**
 * Namespace: includes/wp.
 */

class WPi18n
{
	/**
	 * Load the plugin text domain for translation.
	 */
	public function load()
	{
		load_plugin_textdomain('dpdro', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/');
	}
}
