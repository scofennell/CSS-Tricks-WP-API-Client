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
		$this -> access_token        = 'HyHoB8Ln6PVPX85CG6npIIgy';
		$this -> access_token_secret = 'B83NPbuAfra9pkH8aNDmE98902CHYf7t5hAq1Fc5Npr7Admm';
		
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

		if( empty( $atts['meta_key'] ) ) {
			return new WP_Error( 'missing_meta_key', esc_html__( 'Missing meta key.', 'css-tricks-wp-api-client' ) );
		}

		// Now that we've dug into the shortcode, we can set the meta key.
		$this -> meta_key = sanitize_key( $atts['meta_key'] );

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

		// Make the http request.
		$response = $this -> get_response();

		// Dig into the response and present it as a list.
		foreach( $response as $k => $v ) {
			$out .= $this -> stringify( $k, $v );
		}

		$class = sanitize_html_class( __CLASS__ . '-' . __FUNCTION__ );

		$out = "<div class='$class'>$out</div>";

		return $out;

	}

	/**
	 * Combine the consumer_secret and the access_token_secret into a signature key.
	 * 
	 * @see http://oauth1.wp-api.org/docs/basics/Signing.html#signature-key
	 */
	function set_signature_key() {
		
		$this -> signature_key = urlencode( $this -> consumer_secret ) . '&' . urlencode( $this -> access_token_secret );

	}

	/**
	 * Combine some semi-random, semi-unique stuff into a nonce.
	 */
	function set_nonce(){

		$this -> nonce = wp_create_nonce( rand() . $this -> url . $this -> method );
	
	}

	/**
	 * Combine the values from postman, the oauth1 plugin, and this class iteslf, into the headers array.
	 * 
	 * This function gets run twice.  First, it sets most of the headers.  Later, it sets the final header, the oauth_signature.
	 * You can't do them all at once because you need the base string in order to set the signature, and the base string needs the first few headers!
	 */
	function set_headers() {

		// If we've not yet the headers, set the first few ones, which are easy.
		if( ! isset( $this -> headers ) ) {
			
			// These need to be in alphabetical order, although we will sort them later, automatically.
			$this -> headers = array(
				'oauth_consumer_key'     => $this -> consumer_key,
				'oauth_nonce'            => $this -> nonce,
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => time(),
				'oauth_token'            => $this -> access_token,
				'oauth_version'          => '1.0',
			);

		// If we've already set some of the headers and we have the signaute now, add the signature to the headers.
		} elseif( isset( $this -> signature ) ) {
	
			$this -> headers['oauth_signature'] = $this -> signature;   
		
		}

	}

	/**
	 * Combine the first few headers and any url vars into the base string.
	 * 
	 * @see http://oauth1.wp-api.org/docs/basics/Signing.html#base-string
	 */
	function set_base_string() {

		// Start by grabbing the oauth headers.
		$headers = $this -> headers;

		// Grab the url parameters.
		$url_params = array(
			'meta_key' => $this -> meta_key,
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

		// Remove the trailing ampersand.
		$headers_and_params_string = rtrim( $headers_and_params_string, '&' );

		$out = $this -> method . '&' . rawurlencode( $this -> url ) . '&' . rawurlencode( $headers_and_params_string );

		$this -> base_string = $out;

	}
  
	/**
	 * Combine the base_string and the signature_key into the signature.
	 * 
	 * @see http://oauth1.wp-api.org/docs/basics/Signing.html#signature
	 */
	function set_signature() {

		$out = hash_hmac( 'sha1', $this -> base_string, $this -> signature_key, TRUE );

		$out = base64_encode( $out );

		$this -> signature = $out;

	}

	/**
	 * Combine the header array into a string.
	 */
	function set_header_string() {

		$out = '';     
		
		// For each of the headers, which at this point does now include the signature...
		foreach( $this -> headers as $key => $value ) {

			$value = rawurlencode( $value );

			// Yes, it's mandatory to do double quotes aroung each value.
			$out .= $key . '=' . '"' . $value . '"' . ', ';
		
		}

		// Trim off the trailing comma/space.
		$out = rtrim( $out, ', ' );

		// Kind of similar to basic auth, you have to prepend this little string to declare what sort of auth you're sending.
		$out = 'OAuth ' . $out;

		$this -> header_string = $out;
	
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

	/**
	 * Make an oauth'd http request.
	 * 
	 * @return object The result of an oauth'd http request.
	 */
	function get_response() {
		
		// Grab the url to which we'll be making the request.
		$url = $this -> url;

		// If there is a meta_key, add that as a url var.
		if( ! empty( $this -> meta_key ) ) {
			$url = add_query_arg( array( 'meta_key' => $this -> meta_key ), $url );
		}

		// Args for wp_remote_*().
		$args = array(
			'method'  => $this -> method,
			'headers' => array(
				'Authorization' => $this -> header_string,
			),
		);

		$out = wp_remote_request( $url, $args );

		return $out;

	}

}