<?php

/**
 * A class for calling the control install.
 *
 * @package WordPress
 * @subpackage CSS_Tricks_WP_API_Client
 * @since CSS_Tricks_WP_API_Client 1.0
 */

class CSS_Tricks_WP_API_Client_Remote {

	/**
	 * Set our class variables.
	 * 
	 * @param array $query_vars         An array of query variables to send to the control install.
	 * @param array $remote_settings The plugin settings that we want to get from the control blog.
	 */
	public function __construct( $settings_slug, $settings_array = array() ) {

		$this -> settings_slug  = $settings_slug;
		$this -> settings_array = $settings_array;

		// Set some args for making http requests.
		$this -> set_args();

		// Set the url to which we'll make http requests.
		$this -> set_url();

	}

	/**
	 * Set the url to which we'll make http requests.
	 */
	function set_url() {

		// Grab the url vars that the user wants to pass to the control blog.
		$query_vars = array( 'meta_key' => $this -> settings_slug );

		// Tack the query vars onto the url.
		$url = add_query_arg( $query_vars, CSS_TRICKS_WP_API_CLIENT_CONTROL_URL );

		$this -> url = $url;

	}

	/**
	 * Set some args for making wp_remote_* calls.
	 */
	public function set_args() {

		$oauth = new CSS_Tricks_WP_API_Client_OAuth( $this -> settings_slug );
		
		$header_string = $oauth -> header_string;

		$this -> args = array(
			
			// Relies on the WP  Oauth1 plugin.
			'headers' => array(

				'Authorization' => $header_string,

			),		

			// Whatever, if it needs to take a while that's fine, I have time.
			'timeout'     => 30,
		    'redirection' => 30,

		);

	}

	/**
	 * Call the control blog.
	 * 
	 * @return mixed A network setting from the control blog.
	 */
	function call() {

		// Args for wp_remote_get().
		$args = $this -> args;

		// The url we'll be calling.
		$url = $this -> url;

		// If we are not on the control blog, then we are doing transients.
		if( ! defined( 'CSS_TRICKS_WP_API_CONTROL' ) ) {
			
			// Have we already called the control blog for this data?
			$transient = get_site_transient( $url );

			// If so, just return the data.
			if( ! empty( $transient ) ) { return $transient; }
		
		}

		// Call the control blog.
		$get = wp_remote_get( $url, $args );

		// If the response is weird, bail.
		if( is_wp_error( $get ) ) { return $get; }
		if( ! is_array( $get ) ) { return $get; }

		// If the response was bad, bail.
		$response_code = $get['response']['code'];
		if( substr( $response_code, 0, 2 ) == '40' ) { return $get; }
		if( substr( $response_code, 0, 2 ) == '50' ) { return $get; }

		// We made it this far!  Dig into the body.
		$out = json_decode( $get['body'], TRUE );

		// If we are not on the control blog, store the result locally.
		if( ! defined( 'CSS_TRICKS_WP_API_CONTROL' ) ) {
			set_site_transient( $url, $out, HOUR_IN_SECONDS );
		}

		// Return the body.
		return $out;

	}

	/**
	 * Delete the site transient.
	 */
	function delete_transient() {

		delete_site_transient( $this -> url );

	}

	/**
	 * Loop through all the settings and determine which ones should get their values from the control blog.
	 * 
	 * @return array The subset of settings whose values come from the control blog.
	 */
	function get_remote_settings() {

		// Grab all the settings.
		$settings_array = $this -> settings_array;

		if( ! is_array( $settings_array ) ) { return FALSE; }

		// This will hold all of the settings that get their values from the control blog.
		$remote_settings = array();

		// For each section...
		foreach( $settings_array as $section_key => $section ) {

			$section_settings = $section['settings'];

			// For each setting in this section...
			foreach( $section_settings as $setting_k => $setting ) {

				// Is this setting remote?
				if( isset( $setting['is_remote'] ) ) {

					$remote_settings[ $section_key ][ $setting_k ] = TRUE;

				}

			}

		}

		return $remote_settings;

	}

	/**
	 * This is the main function that other classes would call.
	 * This gives you all your settings values, merged with the values from the control blog.
	 * 
	 * @return array All of the values for all of the settings for a given plugin, both local and remote.
	 */
	function merge() {

		// Out of all the settings in our plugin, which ones do we want to get from the control blog?
		$remote_settings = $this -> get_remote_settings();

		// Grab our local values.
		$local_values = get_site_option( $this -> settings_slug );

		// Grab the remote values from the control blog.
		$remote_values = $this -> call();

		// We'll start our output with the local values.
		$out = $local_values;

		// If the remote values are weird, bail.
		if( ! is_array( $remote_values ) ) { return $out; }

		// For each section of remote settings...
		foreach( $remote_values as $section_key => $section_settings ) {

			// If there are no settings in this section, bail.
			if( ! is_array( $section_settings ) ) { continue; }

			// For each setting in this section...
			foreach( $section_settings as $setting_k => $setting_v ) {

				// If this setting is not mergeable -- that is, if you don't want to get this setting from the control blog, skip it.
				if( ! isset( $remote_settings[ $section_key ][ $setting_k ] ) ) { continue; }

				// We made it!  Add this setting, from the control blog, to our output.
				$out[ $section_key ][ $setting_k ] = $setting_v;

			}

		}

		return $out;

	}

}