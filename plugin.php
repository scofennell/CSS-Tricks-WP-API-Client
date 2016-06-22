<?php

/**
 * @package CSS_Tricks_WAD_Client
 */

/*
Plugin Name: CSST WAD Client
Plugin URI: https://css-tricks.com
Description: CSST WAD Client.
Version: 1.0
Author: Scott Fennell
Author URI: http://scottfennell.org
License: GPLv2 or later
Text Domain: csst-nav
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

// Define a slug for our plugin to use in CSS classes and such.
define( 'CSST_WAD', 'csst_wad' );

/**
 * Define a version that's more easily accessible than the docblock one,
 * for cache-busting.
 */
define( 'CSST_WAD_VERSION', '1.0' );

// Define paths and urls for easy loading of files.
define( 'CSST_WAD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CSST_WAD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// For each php file in the inc/ folder, require it.
foreach( glob( CSST_WAD_PLUGIN_DIR . 'inc/*.php' ) as $filename ) {
    require_once( $filename );
}

require_once( CSST_WAD_PLUGIN_DIR . 'lib/OAuth/bootstrap.php' );