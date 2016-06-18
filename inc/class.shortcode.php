<?php

new CSST_WAD_Shortcode;

class CSST_WAD_Shortcode {

	public function __construct() {

		add_filter( 'rest_query_vars', array( $this, 'my_allowed_post_status' ) );

		add_shortcode( 'csst_wad', array( $this, 'shortcode' ) );

		

	}

	public function auth() {
		$args['headers']['Authorization'] = 'Basic ' . base64_encode( "$username:$password" );
	}

	public function shortcode( $atts ) {

	    $a = shortcode_atts( array(
    	    'url'   => FALSE,
    	    'basic' => FALSE,
    	), $atts );

		$out = '';
   
		$response = wp_remote_get( $a['url'] );

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
			$v = json_decode( $v );
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



}