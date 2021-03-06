<?php

/**
 * A class for building an OAuth header for http requests.
 *
 * @package WordPress
 * @subpackage CSS_Tricks_WP_API_Client
 * @since CSS_Tricks_WP_API_Client 1.0
 */

class CSS_Tricks_WP_API_Client_OAuth {

	public function __construct( $meta_key = FALSE ) {

		// The network setting we want to grab.
		$this -> meta_key = sanitize_key( $meta_key );

		// The url for our custom endpoint, which returns network settings.
		$this -> url = esc_url( CSS_TRICKS_WP_API_CLIENT_CONTROL_URL );

		// You'd get these from /wp-admin/users.php?page=rest-oauth1-apps on the control install.
		$this -> consumer_key    = CSS_TRICKS_WP_API_CLIENT_CONSUMER_KEY;
		$this -> consumer_secret = CSS_TRICKS_WP_API_CLIENT_CONSUMER_SECRET;
		
		// You'd get these from postman.
		$this -> access_token        = CSS_TRICKS_WP_API_CLIENT_ACCESS_TOKEN;
		$this -> access_token_secret = CSS_TRICKS_WP_API_CLIENT_ACCESS_TOKEN_SECRET;
		
		// All we really care about here is GET requests.
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

	}

	/**
	 * Combine some semi-random, semi-unique stuff into a nonce.
	 */
	private function set_nonce() {

		$this -> nonce = wp_create_nonce( rand() . $this -> url . $this -> method );
	
	}

	/**
	 * Combine the consumer_secret and the access_token_secret into a signature key.
	 * 
	 * @see http://oauth1.wp-api.org/docs/basics/Signing.html#signature-key
	 */
	private function set_signature_key() {
		
		$this -> signature_key = urlencode( $this -> consumer_secret ) . '&' . urlencode( $this -> access_token_secret );

	}

	/**
	 * Combine the values from postman, the oauth1 plugin, and this class iteslf, into the headers array.
	 * 
	 * This function gets run twice.  First, it sets most of the headers.  Later, it sets the final header, the oauth_signature.
	 * You can't do them all at once because you need the base string in order to set the signature, and the base string needs the first few headers!
	 */
	private function set_headers() {

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

		// If we've already set some of the headers and we have the signature now, add the signature to the headers.
		} elseif( isset( $this -> signature ) ) {
	
			$this -> headers['oauth_signature'] = $this -> signature;   
		
		}

	}

	/**
	 * Combine the first few headers and any url vars into the base string.
	 * 
	 * @see http://oauth1.wp-api.org/docs/basics/Signing.html#base-string
	 */
	private function set_base_string() {

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
	private function set_signature() {

		$out = hash_hmac( 'sha1', $this -> base_string, $this -> signature_key, TRUE );

		$out = base64_encode( $out );

		$this -> signature = $out;

	}

	/**
	 * Combine the header array into a string.
	 */
	private function set_header_string() {

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
	 * Make an oauth'd http request.
	 * 
	 * @return object The result of an oauth'd http request.
	 */
	public function get_response() {
		
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