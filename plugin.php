<?php

/**
 * @package CSS_Tricks_WP_API_Client
 */

/**
 * Plugin Name: CSS-Tricks WP API Client
 * Plugin URI: https://css-tricks.com
 * Description: A sample plugin for making oauth requests to the WP API.
 * Version: 1.0
 * Author: Scott Fennell
 * Author URI: http://scottfennell.org
 * License: GPLv2 or later
 * Text Domain: css-tricks-wp-api-client
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo 'Hi!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

/**
 * Start editing here!
 */

// The url where your custom endpoint is, on the control blog. Similar to, http://example.com/wp-json/css_tricks_wp_api_control/v1/network_settings
define( 'CSS_TRICKS_WP_API_CLIENT_CONTROL_URL', 'http://scottfennell.com/css-tricks-wp-api-control/wp-json/css_tricks_wp_api_control/v1/network_settings' );

// You'd get these from /wp-admin/users.php?page=rest-oauth1-apps on the control install.
define( 'CSS_TRICKS_WP_API_CLIENT_CONSUMER_KEY', '3AcNVuX3C0cS' );
define( 'CSS_TRICKS_WP_API_CLIENT_CONSUMER_SECRET', 'QlKmoHKR0gzRUXkCw1LlpmRRz0zaSAreCz626Ztp6ifQdcvR' );

// You'd get these from postman.
define( 'CSS_TRICKS_WP_API_CLIENT_ACCESS_TOKEN', '0u3umhf9DFtn8PGXFwxgw5GN' );
define( 'CSS_TRICKS_WP_API_CLIENT_ACCESS_TOKEN_SECRET', 'w4XtFmQrgtEajv5mNHu9jtfBBSsrSexcTHuDj3OAjccBArE4' );

/**
 * Stop editing here!
 */

// Define a slug for our plugin to use in CSS classes and such.
define( 'CSS_TRICKS_WP_API_CLIENT', 'css_tricks_wp_api_client' );

/**
 * Define a version that's more easily accessible than the docblock one,
 * for cache-busting.
 */
define( 'CSS_TRICKS_WP_API_CLIENT_VERSION', '1.0' );

// Define paths and urls for easy loading of files.
define( 'CSS_TRICKS_WP_API_CLIENT_URL', plugin_dir_url( __FILE__ ) );
define( 'CSS_TRICKS_WP_API_CLIENT_DIR', plugin_dir_path( __FILE__ ) );

// For each php file in the inc/ folder, require it.
foreach( glob( CSS_TRICKS_WP_API_CLIENT_DIR . 'inc/*.php' ) as $filename ) {

    require_once( $filename );

}