<?php

/**
 * A class for demonstrating how to make oauth requests to the WP API.
 *
 * @package WordPress
 * @subpackage CSS_Tricks_WP_API_Client
 * @since CSS_Tricks_WP_API_Client 1.0
 */

function CSS_Tricks_WP_API_Client_Demo_init() {
	new CSS_Tricks_WP_API_Client_Demo;
}
add_action( 'plugins_loaded', 'CSS_Tricks_WP_API_Client_Demo_init' );

class CSS_Tricks_WP_API_Client_Demo {

	public function __construct() {

		// I find it easiest to demonstrate stuff like this with shortcodes.
		add_shortcode( CSS_TRICKS_WP_API_CLIENT, array( $this, 'shortcode' ) );

	}

	/**
	 * Our shortcode, invoked via [css_tricks_wp_api_client meta_key='whatever'].
	 * 
	 * @param  array $atts An array of shortcode attributes.
	 * @return string      A glorified var_dump() of an oauth'd http call to the control install.
	 */
	public function shortcode( $atts ) {

		$out = '';

		// Grab the values that the user supplied to the shortcode.
		$a = shortcode_atts(
			
			// There's really only one, meta_key, which is the name of the value we want to grab from the control blog.
			array(
				'meta_key' => FALSE,
			),
			$atts,
			CSS_TRICKS_WP_API_CLIENT
		);

		// Sanitize the meta_key.
		$meta_key = sanitize_key( $a['meta_key'] );

		// Instantiate our Oauth class, which takes one arg, the meta_key.
		$oauth = new CSS_Tricks_WP_API_Client_OAuth( $meta_key );

		// Make the http request.
		$response = $oauth -> get_response();

		// Dig into the response and present it as a list.
		foreach( $response as $k => $v ) {
			$out .= $this -> stringify( $k, $v );
		}

		// I like to make CSS classes in this manner.
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

		// Handle some special values that would not otherwise display well.
		if( $v === FALSE ) {
			$v = '(false)';
		} elseif( $v === TRUE ) {
			$v = '(true)';
		} elseif ( $v === '' ) {
			$v = '(empty string)';
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