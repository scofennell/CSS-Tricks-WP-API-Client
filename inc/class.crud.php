<?php

/**
 * A class for getting and setting options.
 *
 * @package WordPress
 * @subpackage CSS_Tricks_WP_API_Client
 * @since CSS_Tricks_WP_API_Client 1.0
 */

class CSS_Tricks_WP_API_Client_CRUD {

	/**
	 * Set our class variables.
	 * 
	 * @param string $settings_slug  The slug for these settings.
	 * @param array  $settings_array The multidimensional array that defines the settings.
	 */
	public function __construct( $settings_slug, $settings_array ) {

		$this -> settings_slug   = $settings_slug;

		$this -> settings_array  = $settings_array;

		// Grab our class which handles settings from the control install.
		$this -> remote_settings = new CSS_Tricks_WP_API_Client_Remote( $this -> settings_slug, $this -> settings_array );

		$this -> values = $this -> get_values();

	}

	/**
	 * Set an array of settings.
	 * 
	 * @param array $new_values An array of k/v pairs for setting settings.
	 */
	public function set_settings( $new_values ) {
		
		// Saving settings?  Better dump the transient that stores remote settings.
		$this -> remote_settings -> delete_transient();

		// Grab the current values.
		$old_values = $this -> get_values();

		// Rename the variable for easier readability.
		$updated_values = $old_values;

		// For each of the new values...
		foreach( $new_values as $k => $v ) {

			// Merge it with the current values.
			$updated_values[ $k ] = $v;

		}

		// Pass the whole thing to the DB.
		update_site_option( $this -> settings_slug, $updated_values );

	}

	/**
	 * Get our plugin settings values.
	 * 
	 * @return array An array of setting values.
	 */
	public function get_values() {

		// We'll use the remote settings class to merge our local values with the remote values.
		return $this -> remote_settings -> merge();

	}

	/**
	 * Get the current DB value for a setting.
	 * 
	 * @param  string $section_k The key for the section where this setting lives.
	 * @param  string $setting_k The slug for this setting.
	 * @return mixed             The current DB value for this setting.
	 */
	public function get_value( $section_k, $setting_k ) {

		$values = $this -> values;

		if( ! isset( $values[ $section_k ][ $setting_k ] ) ) {
			return FALSE;
		}

		return $values[ $section_k ][ $setting_k ];

	}	

	/**
	 * Update the value of a setting in the database.
	 * 
	 * @param  string $section_k The key for the section where this setting lives.
	 * @param  string $setting_k The slug for this setting.
	 * @param  mixed  The new value for this setting.
	 */
	public function set_setting( $section_k, $setting_k, $value ) {
		
		// Grab the whole array of plugin settings.
		$old_values = $this -> get_values();

		// Rename the var for readability.
		$updated_values = $old_values;

		// Update the particular setting in question.
		$updated_values[ $section_k ][ $setting_k ] = $value;

		// Update the setting in the DB.
		update_site_option( $this -> settings_slug, $updated_values );

	}

	/**
	 * Delete the value of a setting in the database.
	 * 
	 * @param  string $section_k The key for the section where this setting lives.
	 * @param  string $setting_k The slug for this setting.
	 */
	public function delete_setting( $section_k, $setting_k ) {
		
		$old_values = $this -> get_values();
		unset( $old_values[ $section_k ][ $setting_k ] );
		$updated_values = $old_values;

		update_site_option( $this -> settings_slug, $updated_values );

	}

}

?>