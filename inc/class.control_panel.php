<?php

/**
 * A class for drawing a network settings page.
 *
 * @package WordPress
 * @subpackage CSS_Tricks_WP_API_Client
 * @since CSS_Tricks_WP_API_Client 1.0
 */

class CSS_Tricks_WP_API_Client_Control_Panel {

	/**
	 * Set our class variables and add our actions.
	 * 
	 * @param string $settings_slug  The slug for our settings.
	 * @param array  $settings_array A multidimensional array of settings.
	 * @param string $label          A label for the plugin instantiating this class.
	 * @param string $parent_page    The parent page for this settings page.
	 * @param string $capability     The permissions required in order to use this settings page.
	 */
	function __construct( $settings_slug, $settings_array, $label, $parent_page = 'settings.php', $capability = 'update_core' ) {

		// Grab a bunch of helpful functions for CRUD'ing settings.
		$this -> settings = new CSS_Tricks_WP_API_Client_CRUD( $settings_slug, $settings_array );

		$this -> settings_slug  = $settings_slug;		
		$this -> settings_array = $settings_array;
		$this -> label          = $label;
		$this -> parent_page    = $parent_page;
		$this -> capability     = $capability;
		
		// Add a menu item for our settings page.
		add_action( 'network_admin_menu', array( $this, 'add_menu' ) );

		// Register our plugin settings.
		add_action( 'network_admin_menu', array( $this, 'add_settings' ) );

		// Add a form handler for saving our plugin options.
		add_action( 'network_admin_edit_' . $settings_slug,  array( $this, 'update_network_options' ) );
		
	}

	/**
	 * Add our plugin to the menu.
	 */
	function add_menu() {

		// Args for add_submenu_page().
		$parent_slug = $this -> parent_page;
		$page_title  = $this -> label;
		$menu_title  = $this -> label; 
		$capability  = $this -> capability;
		$menu_slug   = $this -> settings_slug;
		$function    = array( $this, 'the_page' );

		add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );

	}

	/**
	 * Add our settings to the page.
	 */
	function add_settings() {

		$page = $this -> settings_slug;

		// For each settings section...
		foreach( $this -> settings_array as $section_k => $section ) {

			// Add that section.
			add_settings_section( $section_k, $section['label'], false, $page );

			// For each setting in this section...
			foreach( $section['settings'] as $setting_k => $setting ) {

				// Build a unique id for this setting, within wordpress.
				$setting_id = $page . '-' . $section_k . '-' . $setting_k;

				// The label for this setting.
				$setting_label = esc_html( $setting['label'] );

				// The callback function to display the input for this setting.
				$callback = array( $this, 'the_field_callback' );

				// How to sanitize this setting.
				$sanitize = 'sanitize_text_field';
				if( isset( $setting['sanitize'] ) ) {
					$sanitize = $setting['sanitize'];
				}

				// An array of args to pass to the input callback.
				$callback_args = array(
					'section_key' => $section_k,
					'setting_key' => $setting_k,
					'setting'     => $setting
				);

				// Register the input.
				add_settings_field(
					$setting_id,
					$setting_label,
					$callback,
					$page,
					$section_k,
					$callback_args
				);

			// End the list of settings for this section.
			}

		// End the list of settings sections.
		}

	}

	/**
	 * A callback function from add_settings_field() for echoing the form input.
	 * 
	 * @param  array $args Info about this setting which we'll need when outputting the input for it.
	 */
	public function the_field_callback( $args ) {

		// Build the HTML form input based on the args.
		$out = $this -> get_field( $args );

		echo $out;

	}

	/**
	 * Get an HTML form input for a setting.
	 * 
	 * @param  array $args Args passed from add_settings_field().
	 * @return string An HTML form input.
	 */
	function get_field( $args ) {

		$prefix = __CLASS__ . '-' . __FUNCTION__;

		// Parse the args from add_settings_field();
		$section_k = $args['section_key'];
		$setting_k = $args['setting_key'];
		$setting_v = $args['setting'];

		// Is this setting disabled?
		$maybe_disabled = $this -> is_setting_disabled( $setting_v );

		// The type of input.
		$type = 'text';
		if( isset( $setting_v['type'] ) ) {
			$type = $setting_v['type'];
		}

		// The <label> text for this input.
		$label = '';
		if( isset( $setting_v['label'] ) ) {
			$label = $setting_v['label'];
		}
		
		// Some UI instructions.
		$notes = '';
		if( isset( $setting_v['notes'] ) ) {
			$notes = $setting_v['notes'];       
		}
		
		// Build the name into array syntax.
		$name = $section_k . '[' . $setting_k . ']';

		// Call the DB and get the current value for this setting.
		$current_value = esc_attr( $this -> settings -> get_value( $section_k, $setting_k ) );
	
		// If you're a checkbox, your value attr is, like, always non-empty.
		if( $type == 'checkbox' ) {

			$value_attr = $setting_v['value'];

		// Otherwise, your value attr is whatever is in the database at the moment.
		} else {
		
			$value_attr = $current_value;
		
		}

		// Handle select inputs.
		if ( $type == 'select' ) {

			// Will hold all the '<option>' elements.
			$options_str = '';

			// For each option...
			foreach( $setting_v['options'] as $option_k => $option_v ) {

				// Should it be selected?
				$maybe_selected = selected( $option_k, $value, FALSE );

				$options_str .= "<option value='$option_k' $maybe_selected>$option_v</option>";

			}

			$field = "<select id='$name' name='$name' $maybe_disabled>$options_str</select>";
			
		// Handle other input types.
		} else {

			// Should the setting be checked?
			$maybe_checked = '';
			if( $type == 'checkbox' ) {
				$maybe_checked = checked( $current_value, $value_attr, FALSE );
			}

			$field = "<input type='$type' id='$name' name='$name' value='$value_attr' $maybe_disabled $maybe_checked>";

		}

		$out = "
			<label for='$name'>
				$field
				<p><i>$notes</i></p>
			</label>
		";

		return $out;

	}

	/**
	 * If a setting should be disabled, get the 'disabled' attribute.
	 * 
	 * @param  array   $setting A setting for our plugin.
	 * @return string  Returns the disabled html attribute, or an empty string.
	 */
	function is_setting_disabled( $setting ) {

		$out = '';

		// If we are not on the control install...
		if( ! defined( 'CSS_TRICKS_WP_API_CONTROL' ) ) {
	
			// And this setting needs to come from the control install...
			if( isset( $setting['is_remote'] ) ) {

				// Then yeah, it's disabled!
				$out = 'disabled';
			
			}
		
		// Yet if we ARE on the control...
		} else {

			// And this setting needs to come from the client installs...
			if( ! isset( $setting['is_remote'] ) ) {

				// Then yeah, it's disabled!
				$out = 'disabled';
			
			}
			
		}

		return $out;

	}

	/**
	 * Our form handler, attached in the constructor.
	 */
	function update_network_options() {
	
		$current_filter = current_filter();

		// Update the options upon form submit, in multisite.
		if( $current_filter != 'network_admin_edit_' . $this -> settings_slug ) { return FALSE; }

		// Grab the current screen.
		$current_screen = get_current_screen();
		$base           = $current_screen -> base;
		
		// In network admin, this is where the form is submitted to.
		if( $base != 'edit-network' )        { return FALSE; }
		if( ! isset( $_GET['action'] ) )     { return FALSE; }
		if( $_GET['action'] != $this -> settings_slug ) { return FALSE; }

		// Check the nonce.
		check_admin_referer( $this -> settings_slug . '-options' );

		// Will hold the new setting values.
		$new_values = array();

		// For each settings section...
		foreach( $this -> settings_array as $section_k => $section ) {
		
			// For each setting in that section...
			foreach( $section['settings'] as $setting_k => $setting ) {

				// If it's read-only, bail.
				if( $setting['type'] == 'private' ) { continue; }

				// Build the name for this setting.
				$name = $section_k . '[' . $setting_k . ']';

				// Was this setting posted?
				if ( isset( $_POST[ $section_k ][ $setting_k ] ) ) {
		
					// If so, sanitize it.
					$new_value = call_user_func( $setting['sanitize'], $_POST[ $section_k ][ $setting_k ] );

				// Else, set it to FALSE.
				} else {

					$new_value = FALSE;
				
				}

				// Pass this into the array of new values.
				$new_values[ $section_k ][ $setting_k ] = $new_value;
	
			}

		}

		// Update the database.
		$this -> settings -> set_settings( $new_values );

		// We made it!  Redirect the page back to our settings page, since the form is handled by some weird settings API url.
		$this -> redirect();
	
	}

	/**
	 * Redirect the browser back to our settings page.
	 */
	function redirect() {

		$redir_to = $this -> get_redirect_url();

		wp_redirect( $redir_to );
		exit;

	}

	/**
	 * Get the url to which we'll redirect the browser upon submit.
	 */
	function get_redirect_url() {

		$out = add_query_arg(
			array(
				'page'    => $this -> settings_slug,
				'updated' => 'true'
			),
			network_admin_url( $this -> parent_page )
		);

		return $out;

	}

	/**
	 * Echo the settings page.
	 */
	function the_page() {
		$out = $this -> get_page();
		echo $out;
	}

	/**
	 * Build the HTML for the settings page.
	 */
	function get_page() {

		// A message to tell us about maybe having updated some settings.
		$settings_message = $this -> get_settings_admin_notice_maybe();

		// The page header.
		$label   = $this -> label;
		$title   = "<h2>$label</h2>";
		
		$content = sprintf( esc_html( 'Some network-level options for %s', 'CSS-Tricks-WP-API-Client' ), $label );

		// The submit button for our settings form.
		$submit_text = esc_attr__( 'Save local settings and refresh the cache for remote control settings.', 'css-tricks-wp-api-client' );
		if( defined( 'CSS_TRICKS_WP_API_CONTROL' ) ) {
			$submit_text = esc_attr__( 'Update remote control settings.', 'css-tricks-wp-api-client' );
		}
		$submit = get_submit_button( $submit_text );

		// We're going OB because the WP Settings API always echoes.
		ob_start();

		// Vomit into the OB.
		settings_fields( $this -> settings_slug );
		do_settings_sections( $this -> settings_slug );

		// Grab the stuff from the OB, clean the OB.
		$settings = ob_get_clean();

		// Our form handler href.
		$action = network_admin_url( 'edit.php?action=' . $this -> settings_slug );
		
		$out = "
			<div class='wrap'>
				
				$settings_message

				<h2>$label</h2>
				
				<p>$content</p>
				
				<form method='POST' action='$action'>
					$settings
					$submit 
				</form>
				
			</div>
		";

		return $out;

	}

	/**
	 * Maybe get an admin notice to explain what happened when we updated settings.
	 * 
	 * @return string A WordPress admin notice.
	 */
	function get_settings_admin_notice_maybe() {

		if ( ! isset( $_GET['updated'] ) ) { return FALSE; }

		$out = '';

		$saved = sprintf( esc_html__( 'Settings saved for %s.', 'css-tricks-wp-api-client' ), $this -> label );
		$out = "
			<div class='updated notice is-dismissible'>
				<p>$saved</p>
			</div>
		";

		return $out;

	}

}