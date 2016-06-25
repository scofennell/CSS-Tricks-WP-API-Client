<?php

function CSST_WAD_Shortcode_init() {
	new CSST_WAD_Shortcode;
}
add_action( 'plugins_loaded', 'CSST_WAD_Shortcode_init' );

class CSST_WAD_Shortcode {

	public function __construct() {

		$this -> url                 = 'http://scottfennell.com/css-tricks-wp-api-control/wp-json/wp/v2/posts/5';
		//$this -> url                 = 'http://scottfennell.com/css-tricks-wp-api-control/wp-json/css_tricks_wp_api_control/v1/options/';
		$this -> meta_key            = FALSE;
		$this -> consumer_key        = '3AcNVuX3C0cS';
		$this -> consumer_secret     = 'QlKmoHKR0gzRUXkCw1LlpmRRz0zaSAreCz626Ztp6ifQdcvR';	
		$this -> access_token        = 'HyHoB8Ln6PVPX85CG6npIIgy';
		$this -> access_token_secret = 'B83NPbuAfra9pkH8aNDmE98902CHYf7t5hAq1Fc5Npr7Admm';
		$this -> method              = 'GET';

		$this -> set_signature_key();
		$this -> set_nonce();
		$this -> set_headers();
		$this -> set_base_string();
		$this -> set_signature();
		$this -> set_headers();
		$this -> set_header_string();

		add_shortcode( 'csst_wad', array( $this, 'shortcode' ) );

	}

	public function shortcode( $atts ) {

		$a = shortcode_atts( array(
			'meta_key'   => FALSE,
		), $atts );

		if( ! empty( $a['meta_key'] ) ) {
			$this -> meta_key = $a['meta_key'];
		}

		$out = '';

		$response = $this -> get_response();

		wp_die( var_dump( $response ) );

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
		
		$url = $this -> url;
		if( ! empty( $this -> meta_key ) ) {
			$url = add_query_arg( array( 'meta_key' => $this -> meta_key ), $url );
		}

		// Build OAuth Authorization header from oauth_* parameters only.
		$args = array(
			'method'  => $this -> method,
			'headers' => array(
				'Authorization' => 'OAuth ' . $this -> header_string,
				'timeout' => 45,
				'sslverify' => FALSE,
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

	function set_base_string() {

		$params_str = '';

		// Convert params to string 
		foreach ( $this -> headers as $k => $v ) {    
			$params_str .= $k . '=' . $v . '&';
		}
		$params_str = rtrim( $params_str, '&' );

		$out = $this -> method . '&' . urlencode( $this -> url ) . '&' . urlencode( $params_str );

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

			$out .= $key . '=' . '"' . $value . '"' . ', ';
		
		}
		$out = rtrim( $out, ', ' );

		$this -> header_string = $out;
	
	}
	
}