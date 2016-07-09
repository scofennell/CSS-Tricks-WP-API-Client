<?php

/**
 * A class for demonstrating how to make oauth requests to the WP API.
 *
 * @package WordPress
 * @subpackage CSS_Tricks_WP_API_Client
 * @since CSS_Tricks_WP_API_Client 1.0
 */

function CSS_Tricks_WP_API_Client_Shortcode_init() {
	new CSS_Tricks_WP_API_Client_Shortcode;
}
add_action( 'plugins_loaded', 'CSS_Tricks_WP_API_Client_Shortcode_init' );

class CSS_Tricks_WP_API_Client_Shortcode {

	public function __construct() {

		// I find it easiest to demonstrate stuff like this with shortcodes.
		add_shortcode( CSS_TRICKS_WP_API_CLIENT, array( $this, 'shortcode' ) );

		// The url for our custom endpoint, which returns network settings.
		$this -> url = 'http://scottfennell.com/css-tricks-wp-api-control/wp-json/css_tricks_wp_api_control/v1/network_settings';
		
		// Later on, we'll provide a value for the meta_key for the network setting we want to grab.
		$this -> meta_key = FALSE;

		// You'd get these from /wp-admin/users.php?page=rest-oauth1-apps on the control install.
		$this -> consumer_key    = '3AcNVuX3C0cS';
		$this -> consumer_secret = 'QlKmoHKR0gzRUXkCw1LlpmRRz0zaSAreCz626Ztp6ifQdcvR';
		
		// You'd get these from postman.
		$this -> access_token        = '0u3umhf9DFtn8PGXFwxgw5GN';
		$this -> access_token_secret = 'w4XtFmQrgtEajv5mNHu9jtfBBSsrSexcTHuDj3OAjccBArE4';
		
		// All we really care about here is GET requests.
		$this -> method = 'GET';

	}

	/**
	 * Our shortcode, invoked via [css_tricks_wp_api_client meta_key='whatever'].
	 * 
	 * @param  array $atts An array of shortcode attributes.
	 * @return string      A glorified var_dump() of an oauth'd http call to the control install.
	 */
	public function shortcode( $atts ) {

		$out = '';

		$atts = shortcode_atts(
			array(
				'meta_key' => FALSE,
			),
			$atts,
			CSS_TRICKS_WP_API_CLIENT
		);

		// Make the http request.
		$response = $this -> oauth -> get_response();

		// Dig into the response and present it as a list.
		foreach( $response as $k => $v ) {
			$out .= $this -> stringify( $k, $v );
		}

		$class = sanitize_html_class( __CLASS__ . '-' . __FUNCTION__ );

		$out = "<div class='$class'>$out</div>";

		return $out;

	}
	
	/**
	 * Just a cool function I wrote for outputting the result of an API call.
	 * 
	 * @param  string $k A key.
	 * @param  mixed  $v The value for $k.
	 * @return string A nested list, depicting the value of $v.
	 */
	public function stringify( $k, $v ) {

		// Open a list, starting with $k.
		$out = "<ul><li><strong>$k:&nbsp;</strong>";

		// If it's an object, make it into an array.
		if( is_object( $v ) ) {
			$json = json_encode( $v );
			$v    = json_decode( $json, TRUE );
		}

		// If it's json, turn it into an array.
		if( $this -> is_json( $v ) ) {
			$v = json_decode( $v, TRUE );
		}

		// If it's scalar, great, time to just add it.
		if( is_scalar( $v ) ) {

			$out .= esc_html( $v ) . '</li>' . PHP_EOL;

		// If it's null, say so.
		} elseif( is_null( $v ) ) {

			$out .= '(null)' . '</li>' . PHP_EOL;

		// If it's an array...
		} elseif( is_array( $v ) ) {

			// For each array member...
			foreach( $v as $kk => $vv ) {

				// Recurse it.
				$out .= $this -> stringify( $kk, $vv );

			}

			$out .= '</li>' . PHP_EOL;

		} 

		$out = "$out</ul>" . PHP_EOL;

		return $out;

	}

	/**
	 * Determine if a string is JSON.
	 * 
	 * @param  string  $string Any string.
	 * @return boolean Return TRUE if $string is json, else FALSE.
	 */
	function is_json( $string ) {

		// If it's not even a string, bail.
		if( ! is_string( $string ) ) { return FALSE; }

		// Attempt to decode it.
		json_decode( $string );
		
		// Was there an error in decoding it?
		$json_last_error = json_last_error();
		
		// If not, great, it's json.
		if( $json_last_error == JSON_ERROR_NONE ) { return TRUE; }

		// If so, then it's not json.
		return FALSE;
	
	}

}