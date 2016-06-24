<?php

new CSST_WAD_Shortcode;

class CSST_WAD_Shortcode {

	public function __construct() {

		$this -> wp_site_url         = 'http://scottfennell.com/css-tricks-wp-api-control';
		$this -> wp_api_path         = '/wp-json/wp/v2';
		$this -> key                 = '3AcNVuX3C0cS';
		$this -> secret              = 'QlKmoHKR0gzRUXkCw1LlpmRRz0zaSAreCz626Ztp6ifQdcvR';	
		$this -> access_token        = 'HyHoB8Ln6PVPX85CG6npIIgy';
		$this -> access_token_secret = 'B83NPbuAfra9pkH8aNDmE98902CHYf7t5hAq1Fc5Npr7Admm';

		add_shortcode( 'csst_wad', array( $this, 'shortcode' ) );

	}

	public function shortcode( $atts ) {

		/*$a = shortcode_atts( array(
			'url'   => FALSE,
			'basic' => FALSE,
		), $atts );*/

		$out = '';

		$url = $this -> wp_site_url . $this -> wp_api_path . '/posts/5';

		$response = $this -> oauthRequest(
			$url,
			'GET'
		);

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

		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}

	function oauthRequest( $url, $method ) {
		
		// The keys need to be alphabetical.
		$params = array(
			'oauth_consumer_key'     => $this -> key,
			'oauth_nonce'            => wp_create_nonce( time() . rand() . $url . $method ),
			'oauth_signature_method' => "HMAC-SHA1",
			'oauth_timestamp'        => time(),
			'oauth_token'            => $this -> access_token,
			'oauth_version'          => "1.0",
		);
		
		// Convert params to string 
		foreach ($params as $k => $v) {    
			$pairs[] = $this->_urlencode_rfc3986($k).'='.$this->_urlencode_rfc3986($v);
		}
		$concatenatedParams = implode('&', $pairs);
		$concatenatedParams = str_replace('=', '%3D', $concatenatedParams);
		$concatenatedParams = str_replace('&', '%26', $concatenatedParams);
		
		// Form base string (first key)
		// echo '<h4>concatenated params</h4><pre>'.$concatenatedParams.'</pre>';
		// base string should never use the '?' even if it has one in a GET query
		// See : https://developers.google.com/accounts/docs/OAuth_ref#SigningOAuth
		$base_string = $method."&".urlencode($url)."&".$concatenatedParams;
	
		// Form secret (second key)
		$secret = urlencode($this->secret)."&".$this -> access_token_secret; // concatentate the oauth_token_secret (null when doing initial '1st leg' request token)
	
		// Make signature and append to params
		$params['oauth_signature'] = $this -> get_signature( $base_string, $secret );        
		
		// Build OAuth Authorization header from oauth_* parameters only.
		$headers = $this->get_headers( $params );
		
		$args = array(
			'method' => $method,
			'headers' => $headers,
		);
		$json_response = wp_remote_request( $url, $args );

		// Result JSON
		return $json_response;

	}
  
	function get_base_string( $method, $url, $params ) {

		$params_str = '';

		// Convert params to string 
		foreach ( $params as $k => $v ) {    
			$params_str .= $this -> _urlencode_rfc3986( $k ) . '%3D' . $this -> _urlencode_rfc3986( $v ) . '%26';
		}
		$params_str = rtrim( $params_str, '&' );

		$out = $method . '&' . urlencode( $url ) . '&' . $params_str;

		return $out;

	}
  
	function get_signature( $base_string, $secret ) {

		$out = hash_hmac( 'sha1', $base_string, $secret, TRUE );

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