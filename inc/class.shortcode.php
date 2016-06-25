<?php

new CSST_WAD_Shortcode;

class CSST_WAD_Shortcode {

	public function __construct() {

		$this -> url          = 'http://scottfennell.com/css-tricks-wp-api-control/wp-json/wp/v2';
		$this -> consumer_key                 = '3AcNVuX3C0cS';
		$this -> consumer_secret              = 'QlKmoHKR0gzRUXkCw1LlpmRRz0zaSAreCz626Ztp6ifQdcvR';	
		$this -> access_token        = 'HyHoB8Ln6PVPX85CG6npIIgy';
		$this -> access_token_secret = 'B83NPbuAfra9pkH8aNDmE98902CHYf7t5hAq1Fc5Npr7Admm';

		$this -> method = 'GET';
		
		add_shortcode( 'csst_wad', array( $this, 'shortcode' ) );

	}

	public function shortcode( $atts ) {

		/*$a = shortcode_atts( array(
			'url'   => FALSE,
			'basic' => FALSE,
		), $atts );*/

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

		if( $this -> isJson( $v ) ) {

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



	function isJson($string) {

		if( ! is_string( $string ) ) { return FALSE; }

		json_decode( $string );
		
		$json_last_error = json_last_error();
		
		if( $json_last_error == JSON_ERROR_NONE ) { return TRUE; }

		return FALSE;
	
	}

	function get_response() {
		
		// The keys need to be alphabetical.
		$params = array(
			'oauth_consumer_key'     => $this -> consumer_key,
			'oauth_nonce'            => wp_create_nonce( time() . rand() . $this -> url . $this -> url ),
			'oauth_signature_method' => "HMAC-SHA1",
			'oauth_timestamp'        => time(),
			'oauth_token'            => $this -> access_token,
			'oauth_version'          => "1.0",
		);

		// Make signature and append to params
		$params['oauth_signature'] = $this -> get_signature( $params );        
		
		// Build OAuth Authorization header from oauth_* parameters only.
		$headers = $this->get_headers( $params );
		
		$args = array(
			'method' => $this -> method,
			'headers' => $headers,
		);
		$json_response = wp_remote_request( $this -> url, $args );

		// Result JSON
		return $json_response;

	}
  
	function get_signature_key() {
		
		$out = urlencode( $this -> consumer_secret ) . "&" . $this -> access_token_secret;

		return $out;

	}

	function get_base_string( $params ) {

		$params_str = '';

		// Convert params to string 
		foreach ( $params as $k => $v ) {    
			$params_str .= $this -> _urlencode_rfc3986( $k ) . '%3D' . $this -> _urlencode_rfc3986( $v ) . '%26';
		}
		$params_str = rtrim( $params_str, '%26' );

		$out = $this -> method . '&' . urlencode( $this -> url ) . '&' . $params_str;

		return $out;

	}
  
	function get_signature( $params ) {

		$base_string = $this -> get_base_string( $params );

		// Form secret (second key)
		$signature_key = $this -> get_signature_key();

		$out = hash_hmac( 'sha1', $base_string, $signature_key, TRUE );

		$out = base64_encode( $out );

		$out = rawurlencode( $out );

		return $out;

	}

	function _urlencode_rfc3986( $input ) {
		
		$out = rawurlencode( $input );

		$out = str_replace( '%7E', '~', $out );

		$out = str_replace( '+', ' ', $out );
	
		return $out;

	}

	private function get_headers( $oauth ) {

		$r = 'Authorization: OAuth ';
		foreach($oauth as $key => $value ) {

			$value = rawurlencode( $value );

			$r .= $key . '=' . '"' . $value . '"' . ', ';
		
		}
		$r = rtrim( $r, ', ' );
		return $r;
	
	}
	
}