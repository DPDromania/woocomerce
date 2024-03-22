<?php

/**
 * Namespace: includes/wp.
 */

class WPActivator
{
	public static function activate()
	{
		set_transient('dpdro-activated', true, 30);
	}
}
