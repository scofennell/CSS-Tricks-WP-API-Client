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

		// The url for our custom endpoint, which returns network settings.
		$this -> url = 'http://scottfennell.com/css-tricks-wp-api-control/wp-json/css_tricks_wp_api_control/v1/network_settings';
		
		// Later on, we'll provide a value for the meta_key for the network setting we want to grab.
		$this -> meta_key = FALSE;

		// You'd get these from /wp-admin/users.php?page=rest-oauth1-apps on the control install.
		$this -> consumer_key    = '3AcNVuX3C0cS';
		$this -> consumer_secret = 'QlKmoHKR0gzRUXkCw1LlpmRRz0zaSAreCz626Ztp6ifQdcvR';	
		
		// You'd get these from postman.
		$this -> access_token        = 'HyHoB8Ln6PVPX85CG6npIIgy';
		$this -> access_token_secret = 'B83NPbuAfra9pkH8aNDmE98902CHYf7t5hAq1Fc5Npr7Admm';
		
		// All we really care about is GET requests.
		$this -> method = 'GET';

		// Combine some semi-random, semi-unique stuff into a nonce for our request.
		$this -> set_nonce();
		
		// Since we already have the consumer_secret and the access_token_secret, we can set the signature_key.
		$this -> set_signature_key();

		// We can set some of the headers now, but we'll have to revisit them later to set one of them in particular.
		$this -> set_headers();

		// Now that we have the url, method, and some of the headers, we can set the base string.
		$this -> set_base_string();

		// Now that we have the base string and the signature key, we can set the signature.
		$this -> set_signature();

		// Now that we have the signature, we can revisit the headers and set the final one.
		$this -> set_headers();

		// Now that we have the headers, we can combine them into a string for passing along in our http requests to the control install.
		$this -> set_header_string();

		// I find it easiest to demonstrate stuff like this with shortcodes.
		add_shortcode( 'css_tricks_wp_api_client', array( $this, 'shortcode' ) );

	}

	/**
	 * Our shortcode, invoked via [css_tricks_wp_api_client meta_key='whatever'].
	 * 
	 * @param  array $atts An array of shortcode attributes.
	 * @return string      A glorified var_dump() of an oauth'd http call to the control install.
	 */
	public function shortcode( $atts ) {

		$out = '';

		$response = $this -> get_response();

		foreach( $response as $k => $v ) {
			$out .= $this -> stringify( $k, $v );
		}

		$out = "<div class='csst_wad_shortcode'>$out</div>";

		return $out;

	}
	
	public function stringify( $k, $v ) {

		$out = "<ul><li><strong>$k:&nbsp;</strong>";

		// If it's an object, make it into an array.
		if( is_object( $v ) ) {
			$json = json_encode( $v );
			$v  = json_decode( $json, TRUE );
		}

		if( $this -> is_json( $v ) ) {

			$v = json_decode( $v, TRUE );

		}

		// If it's scalar, great, time to just log it.
		if( is_scalar( $v ) ) {

			$out .= esc_html( $v ) . '</li>' . PHP_EOL;

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

	function is_json($string) {

		if( ! is_string( $string ) ) { return FALSE; }

		json_decode( $string );
		
		$json_last_error = json_last_error();
		
		if( $json_last_error == JSON_ERROR_NONE ) { return TRUE; }

		return FALSE;
	
	}

	function get_response() {
		
		$url            = $this -> url;
		if( ! empty( $this -> meta_key ) ) {
			$url = add_query_arg( array( 'meta_key' => $this -> meta_key ), $url );
		}

		// Build OAuth Authorization header from oauth_* parameters only.
		$args = array(
			'method'  => $this -> method,
			'headers' => array(
				'Authorization' => 'OAuth ' . $this -> header_string,
			),
		);
		$json_response = wp_remote_request( $url, $args );

		// Result JSON
		return $json_response;

	}
  
	function set_signature_key() {
		
		// urlencode() may not be necessary depending on your values, but is recommended.
		$this -> signature_key = urlencode( $this -> consumer_secret ) . '&' . urlencode( $this -> access_token_secret );

	}

	function set_nonce(){

		$this -> nonce = wp_create_nonce( rand() . $this -> url . $this -> method );
	
	}

	function set_headers() {

		if( ! isset( $this -> headers ) ) {
			
			$this -> headers = array(
				'oauth_consumer_key'     => $this -> consumer_key,
				'oauth_nonce'            => $this -> nonce,
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => time(),
				'oauth_token'            => $this -> access_token,
				'oauth_version'          => '1.0',
			);

		} elseif( isset( $this -> signature ) ) {
		
			// Make signature and append to params
			$this -> headers['oauth_signature'] = $this -> signature;   
		
		}

	}

	// GET&http%3A%2F%2Fscottfennell.com%2Fcss-tricks-wp-api-control%2Fwp-json%2Fcss_tricks_wp_api_control%2Fv1%2Fnetwork_settings&meta_key%3Dsite_name%26oauth_consumer_key%3D3AcNVuX3C0cS%26oauth_nonce%3Ddaeb926d24%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3D1466890729%26oauth_token%3DHyHoB8Ln6PVPX85CG6npIIgy%26oauth_version%3D1.0
	/*function set_base_string() {

		$headers                 = $this -> headers;
		if( ! empty( $this -> meta_key ) ) {
			$url_params              = array( 'meta_key' => $this -> meta_key );
			$headers = array_merge( $headers, $url_params );
		}

		ksort( $headers );

		$string_params = array();

		foreach( $headers as $key => $value ) {
			$string_params[] = "$key=$value";
		}


		$out = $this -> method. '&' . rawurlencode( $this -> url ) . '&' . rawurlencode( implode( '&', $string_params ) );

		wp_die( var_dump( $out ) );

		$this -> base_string = $out;

	}*/

	/**
	 * Combine the headers and the url var for 'meta_key' into the base_string.
	 */
	// GET&http%3A%2F%2Fscottfennell.com%2Fcss-tricks-wp-api-control%2Fwp-json%2Fcss_tricks_wp_api_control%2Fv1%2Fnetwork_settings&meta_key%3Dsite_name%26oauth_consumer_key%3D3AcNVuX3C0cS%26oauth_nonce%3D4f03989add%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3D1466890667%26oauth_token%3DHyHoB8Ln6PVPX85CG6npIIgy%26oauth_version%3D1.0%26
	function set_base_string() {

		// Start by grabbing the oauth headers.
		$headers = $this -> headers;

		// Grab the url parameters.
		$url_params = array(
			'meta_key' => $this -> meta_key
		);

		// Combine the two arrays.
		$headers_and_params = array_merge( $headers, $url_params );
	
		// They need to be alphabetical.
		ksort( $headers_and_params );

		// This will hold each key/value pair of the array, as a string.
		$headers_and_params_string = '';

		// For each header and url param...
		foreach( $headers_and_params as $key => $value ) {

			// Combine them into a string.
			$headers_and_params_string .= "$key=$value&";

		}

		// Remove the trailing ampersan.
		$headers_and_params_string = rtrim( $headers_and_params_string, '&' );

		$out = $this -> method. '&' . rawurlencode( $this -> url ) . '&' . rawurlencode( $headers_and_params_string );

		$this -> base_string = $out;

	}
  

	function set_signature() {

		$out = hash_hmac( 'sha1', $this -> base_string, $this -> signature_key, TRUE );

		$out = base64_encode( $out );

		$this -> signature = $out;

	}

	function set_header_string() {

		$out = '';     
		
		foreach( $this -> headers as $key => $value ) {

			// You might think these headers are just letters and numbers, but that's not always the case due to base64_encode().
			$value = rawurlencode( $value );

			$out .= $key . '=' . '"' . $value . '"' . ', ';
		
		}
		$out = rtrim( $out, ', ' );

		$this -> header_string = $out;
	
	}
	
}