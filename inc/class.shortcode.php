<?php

new CSST_WAD_Shortcode;

// Edit the config to your requirements.
$wp_site_url = 'http://scottfennell.com/csst-wad-server';
$wp_api_path = '/wp-json/wp/v2';
$oauth_config = array(
    'key'               => 'zvxTHgConCdp', 
    'secret'            => 'Y7FAs0y61jKAiMEAWAGKNeS4nGnU4MTB4nJvFPW9T7yHDnea',
    'wp_api_domain'     => $wp_site_url,
    'wp_api_path'       => $wp_api_path,
    'uri_request'       => $wp_site_url.'/oauth1/request',
    'uri_authorize'     => $wp_site_url.'/oauth1/authorize',
    'uri_access'        => $wp_site_url.'/oauth1/access',
    'uri_user'          => $wp_site_url.$wp_api_path.'/users/me?context=edit', // 'embed' context excludes roles and capabilities, so use 'edit' to determine if publishing and uploads are allowed.
    'oauth_callback'    => 'http://localhost/wp/csst-wad-client'  // The url where you will run this test. Point to THIS php file.
);





    function sjf_json_error($string) {

        if( ! is_string( $string ) ) { return FALSE; }

        json_decode($string);
        return (json_last_error());
    }








add_action( 'template_redirect', 'sjf_get_auth' );

function sjf_get_auth( $oauth_config ) {

    global $oauth_config;

    // Clear the cookies to log out. 
    if( isset( $_GET['logout'] ) ) {
        setcookie("access_token", 0, time() - 1, "/" );                
        setcookie("access_token_secret", 0, time() - 1, "/" ); 
        setcookie("user_object", 0,  time() - 1, "/" ); 
        setcookie("oauth_token_secret", "", time() - 1, "/" );
        header('Location: '.$_SERVER['PHP_SELF']);
    }

    // OK.. Here we go... 
    $auth = new OAuthWP($oauth_config);
    // Pick up url query params after the oauth_callback after request token generation. (Also added check to make sure we're coming back from the OAuth server host)
    if(isset( $_COOKIE['oauth_token_secret'] ) && isset( $_REQUEST['oauth_token'] ) && isset( $_REQUEST['oauth_verifier'] ) && parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) == parse_url($oauth_config['uri_request'], PHP_URL_HOST)  ){
        // Back from Authorisation. Now Generate Access Tokens for this user    
        // Generate access tokens param string
        // Add the required 'oauth_verifier' parameter
        $request_data = array(
            'oauth_verifier' => $_REQUEST['oauth_verifier']
        );
        $temp_secret = $_COOKIE['oauth_token_secret']; // from /request token leg 
        $access_token_string = $auth->oauthRequest($oauth_config['uri_access'],'POST', $_REQUEST['oauth_token'], $temp_secret, $request_data); // no token secret yet... 
        parse_str($access_token_string, $access_tokens);
        if(!isset($access_tokens['oauth_token'])){
            echo '<h3>ERROR: Failed to get access tokens</h3>';
            print_r($access_tokens);
            echo '<hr>';
            print_r($access_token_string);
            exit;
        }
        $access_token = $access_tokens['oauth_token'];
        $access_token_secret = $access_tokens['oauth_token_secret'];
        
        // We encode it because otherwise, the cookie process will add slashes to json.
        $user = base64_encode( $auth->oauthRequest($oauth_config['uri_user'],'GET', $access_token, $access_token_secret) );

        // Store information in a cookie for when the page is reloaded
        setcookie("access_token", $access_token, time() + (3600 * 72), "/" );                    // expire in 72 hours...
        setcookie("access_token_secret", $access_token_secret, time() + (3600 * 72), "/" );       // expire in 72 hours...
        setcookie("user", $user,  time() + (3600 * 72), "/" );         // expire in 72 hours...
        // Clear the temp cookie
        setcookie("oauth_token_secret", "", time() - 1, "/" );
        // Reload the page
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
    if(isset($_COOKIE['access_token']) && isset($_COOKIE['access_token_secret']) && isset($_COOKIE['user'])){

        $u = json_decode( base64_decode( $_COOKIE['user'] ) );

        $av = $u->avatar_urls;
        foreach ($av as $key => $value) {
            if($key == 48){ // medium thumbnail
                $av = $value;
            }
        }
        echo '<h3><img style="margin:6px;width:50px;height:50px;float:left;vertical-align:middle;" src="'.$av.'"> logged in as: '.$u->name.'</h3>';
        echo '<br clear="all"><h4><a href="?logout=1">CLICK HERE TO LOG OUT</a></h4>';
        echo '<hr>';
        
    } else {
        
        // Not logged in. 
        $request_token_string = $auth->oauthRequest($oauth_config['uri_request'],'POST', null, null);
        parse_str($request_token_string, $request_parts);
        // temporarily store the oauth_token_secret for the next step after the callback.
        setcookie("oauth_token_secret", $request_parts['oauth_token_secret'], time() + 60, "/" ); 
        // echo '<h4>request_token_string :'.$request_token_string.'</h4>';
        // Start OAuth authorisation by obtaining a request token and generating a link to the OAuth server, with a callback here ...
        echo '<h3><a href="'.$oauth_config['uri_authorize'].'?'.$request_token_string.'&oauth_callback='.urlencode($oauth_config['oauth_callback']).'">LOGIN USING YOUR '.$oauth_config['wp_api_domain'].' WORDPRESS ACCOUNT</a></h3>';
        echo 'Uses WP-API and OAuth 1.0a Server for WordPress via https://github.com/WP-API';
    }

}
    
















class CSST_WAD_Shortcode {

	public function __construct() {

		add_filter( 'rest_query_vars', array( $this, 'my_allowed_post_status' ) );

		add_shortcode( 'csst_wad', array( $this, 'shortcode' ) );

		add_action( 'rest_api_init', array( $this, 'register_postmeta' ) );

	}

	public function shortcode( $atts ) {

		global $oauth_config;

		//return FALSE;

	    /*$a = shortcode_atts( array(
    	    'url'   => FALSE,
    	    'basic' => FALSE,
    	), $atts );*/

    	$out = '';

    	//$header = $this->authorization_header();
		/*
		'OAuth oauth_consumer_key="zvxTHgConCdp", oauth_nonce="1466542852", oauth_signature_method="HMAC-SHA1", oauth_token="XQue3mHbIWrCS3Ubi6feLxjG", oauth_timestamp="1466542852", oauth_signature="UWk9Yi5flURQ%2BPsDBrRfjEMEH10%3D"'
		 */

		$auth = new OAuthWP($oauth_config);

		$args = array(
			//'headers'   => array( 'Authorization' => $header ),
			'timeout'   => 45,
			'sslverify' => false,
			//'method' => 'DELETE',
		);

		$response = $auth->oauthRequest(
			$oauth_config['wp_api_domain'].$oauth_config['wp_api_path'].'/posts/5',
            'GET', 
            'IAh3xNKAxTuc6rDX2mtBBPtl', 
            'UrJpJyXgaUzIQ4PwTpBpU27l1P0rHf2czERQbyAIGxi9kcmM'
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

}


















































class OAuthWP {
    function __construct( $config ) {
        $this->key = $config['key'];
        $this->secret = $config['secret'];
        $this->uri_request = $config['uri_request'];
        $this->uri_authorize = $config['uri_authorize'];
        $this->uri_access = $config['uri_access'];     
        $this->uri_user = $config['uri_user'];           
    }

    function queryStringFromData($data, $queryParams = false, $prevKey = '') {
        if ($initial = (false === $queryParams)) {
            $queryParams = array();
        }
        foreach ($data as $key => $value) {
            if ($prevKey) {
                $key = $prevKey.'['.$key.']'; // Handle multi-dimensional array
            }
            $queryParams[] = $this->_urlencode_rfc3986($key.'='.$value); // join with equals sign
        }
        if ($initial) {
            return implode('%26', $queryParams); // join with ampersand
        }
        return $queryParams;
    }

    function oauthRequest($url, $method, $oauth_access_token, $oauth_access_token_secret, $post_params=null, $post_json=false) {
        
        $params = array(
            "oauth_version" => "1.0",
            "oauth_nonce" => md5(time().rand()),
            "oauth_timestamp" => time(),
            "oauth_consumer_key" => $this->key,
            "oauth_signature_method" => "HMAC-SHA1",
            "oauth_token" => $oauth_access_token
        );
        // Filter out empty params. 
        $params = array_filter($params);
        // ## BUILD OAUTH SIGNATURE
        // Add extra params if present and not JSON
        if($post_params!=null && $post_json === false ){
            foreach ($post_params as $k => $v){
                    if(is_array($v)){
                            $iii = 0;
                            foreach ($v as $kk => $vv){
                                $params[$k][$iii] = $vv;
                                $iii++;
                            }
                    } else {
                        $params[$k] = $v;
                    }
            }
            // Remove 'file' param from signature base string. Since the server will have nothing to compare it to. Also potentially exposes paths.
            unset($params['file']);
            ksort($params);
        }
        
        // Deal query with any query params in the request_uri
        $request_query = parse_url($url, PHP_URL_QUERY);
        $request_uri_parts = parse_url($url);
        $request_base_uri = $request_uri_parts['scheme'].'://'.$request_uri_parts['host'].$request_uri_parts['path'];
        
        $joiner = '?'; // used for final url concatenation down below
        if(!empty($request_query)){
            $joiner = '&';
            parse_str($request_query, $query_params);
            $params = array_merge($query_params, $params);
            ksort($params);
        }
        // Encode params keys, values, join and then sort.
        $keys = $this->_urlencode_rfc3986(array_keys($params));
        $values = $this->_urlencode_rfc3986(array_values($params));
        $params = array_combine($keys, $values);
        ksort($params);
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
        $baseString= $method."&".urlencode($request_base_uri)."&".$concatenatedParams;
        // Form secret (second key)
        $secret = urlencode($this->secret)."&".$oauth_access_token_secret; // concatentate the oauth_token_secret (null when doing initial '1st leg' request token)
        // Make signature and append to params
        $params['oauth_signature'] = rawurlencode(base64_encode(hash_hmac('sha1', $baseString, $secret, TRUE)));        
        // Re-sort params
        ksort($params);
        // Remove any added GET query parameters from the params to rebuild the string without duplication ..
        if(isset($query_params)){
            foreach ($query_params as $key => $value) {
                if(isset($params[$key])){
                    unset($params[$key]);
                }
            }
            ksort($params);
        }
        // Remove any POST params so they get sent as POST data and not in the query string. 
        if($post_params!=null && $post_json === false ){
            foreach ($post_params as $key => $value) {
                if(isset($params[$key])){
                    unset($params[$key]);
                }
            }
            ksort($params);
        }    
        // Build OAuth Authorization header from oauth_* parameters only.
        $post_headers = $this->buildAuthorizationHeader($params);
        // Convert params to string 
        foreach ($params as $k => $v) {
            $urlPairs[] = $k."=".$v;
        }
        $concatenatedUrlParams = implode('&', $urlPairs);
        // The final url can use the ? query params....
        $final_url = $url; // original url. OAuth data will be set in the Authorization Header of the request, regardless of _GET or _POST (or _FILE)
        // Request using cURL
        $json_response = $this->_http($final_url, $method, $post_params, $post_headers, $post_json); 
        // Result JSON
        return $json_response;
    }

    // Send Authorised Request Using Curl ///////////////////////////
    function _http($url, $method, $post_data = null, $oauth_headers = null, $post_json=false) {       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        if($method=='POST')
        {
            curl_setopt($ch, CURLOPT_POST, 1);     
            if(isset($post_data['file'])){
                // Media upload
                $header[] = 'Content-Type: multipart/form-data';
                if(isset($oauth_headers)){
                    array_push($header, $oauth_headers);
                }                
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            } else {
                if(isset($oauth_headers)){
                    if($post_json===true){
                        $header[] = 'Content-Type: application/json';
                        array_push($header, $oauth_headers);
                    } else {
                        $header[] = $oauth_headers;
                    }
                    
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                }
                
                if($post_json===true){
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data); // application/x-www-form-urlencoded
                }
            }
        } else {
            // Not being used yet. 
            if(isset($oauth_headers))
            {
                $header[] = $oauth_headers;
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }
        }
        $response = curl_exec($ch);
        $this->http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->last_api_call = $url;
        curl_close($ch);
        return $response;
    }
  
    function _urlencode_rfc3986($input) {
        if (is_array($input)) {
            return array_map(array('OAuthWP', '_urlencode_rfc3986'), $input);
        }
        else if (is_scalar($input)) {
            return str_replace('+',' ',str_replace('%7E', '~', rawurlencode($input)));
        }
        else{
            return '';
        }
    }
    
    private function buildAuthorizationHeader($oauth) {
            $r = 'Authorization: OAuth ';
            $values = array();
            foreach($oauth as $key => $value){
                    $values[] = $key . '="' . rawurlencode($value) . '"';
            }
            $r .= implode(', ', $values);
            return $r;
    }
    
}