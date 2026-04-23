<?php

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package    Smart_Multistep_Lead_Forms
 * @subpackage Smart_Multistep_Lead_Forms/includes
 */
class SMLF_i18n {

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'smart-multistep-lead-forms',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
