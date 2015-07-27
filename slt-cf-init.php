<?php

/* Initialize
***************************************************************************************/

// Global initialization
function slt_cf_init() {
	global $slt_custom_fields, $pagenow;

	// Globals still to initialize (ones that use core functions with filters that aren't exposed if run earlier)
	$slt_custom_fields['ui_css_url'] = plugins_url( 'js/jquery-ui/smoothness/jquery-ui-1.8.16.custom.css', __FILE__ );
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		// Unminified debug stuff
		$slt_custom_fields['css_url'] = plugins_url( 'css/slt-cf-admin.css', __FILE__ );
		$slt_custom_fields['registration_css_url'] = plugins_url( 'css/slt-cf-registration.css', __FILE__ );
		$slt_js_gmaps = plugins_url( 'js/slt-cf-gmaps.js', __FILE__ );
	} else {
		// Minified production stuff
		$slt_custom_fields['css_url'] = plugins_url( 'css/slt-cf-admin.min.css', __FILE__ );
		$slt_custom_fields['registration_css_url'] = plugins_url( 'css/slt-cf-registration.min.css', __FILE__ );
		$slt_js_gmaps = plugins_url( 'js/slt-cf-gmaps.min.js', __FILE__ );
	}

	// Register any styles needed at the front
	wp_register_style( 'slt-cf-registration-styles', $slt_custom_fields['registration_css_url'], array(), SLT_CF_VERSION );

	/*
	 * Google Maps stuff is registered to go in the footer, so it can be enqueued dynamically
	 * on the front-end, as the map is output
	 */
	$gmaps_api_url = SLT_CF_REQUEST_PROTOCOL . 'maps.google.com/maps/api/js';
	if ( defined( 'SLT_CF_GMAPS_API_KEY' ) && SLT_CF_GMAPS_API_KEY ) {
		$gmaps_api_url = esc_url( add_query_arg( 'key', SLT_CF_GMAPS_API_KEY, $gmaps_api_url ) );
	}
	wp_register_script( 'google-maps-api', $gmaps_api_url, array(), false, true );
	$gmaps_deps = array( 'jquery', 'jquery-ui-core', 'jquery-ui-autocomplete' );
	wp_register_script( 'slt-cf-gmaps', $slt_js_gmaps, $gmaps_deps, SLT_CF_VERSION, true );

	// Generic hook, mostly for dependent plugins to hook to
	// See: http://core.trac.wordpress.org/ticket/11308#comment:7
	do_action( 'slt_cf_init' );

}

// Admin initialization
function slt_cf_admin_init() {
	global $slt_cf_admin_notices, $slt_custom_fields, $pagenow;
	$options = get_option( 'slt_cf_options' );

	// Notices to output?
	$slt_cf_admin_notices = array();

	// Check which relevant version warnings haven't been seen yet
	if ( ( $version_warnings_json = file_get_contents( plugin_dir_path( __FILE__ ) . 'slt-cf-version-warnings.json' ) ) !== false ) {
		$version_warnings = json_decode( $version_warnings_json, true );
		if ( ! empty( $version_warnings ) ) {
			foreach ( $version_warnings as $version => $warning ) {
				$warning_label = "Developer's Custom Fields plugin version " . $version;
				// Make sure the notice hasn't already been dismissed
				// And that warning is for a version less than or equal to current version
				if (	( empty( $options['dismissed_notices'] ) || ! is_array( $options['dismissed_notices'] ) || ! in_array( sanitize_title( $warning_label ), $options['dismissed_notices'] ) ) &&
						version_compare( $version, SLT_CF_VERSION, '<=' )
				) {
					$slt_cf_admin_notices[] = array(
						'label'		=> $warning_label,
						'text'		=> $warning,
					);
				}
			}
		}
	}

	// Determine some paths
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		$slt_js_admin = plugins_url( 'js/slt-cf-admin.js', __FILE__ );
		$slt_js_media = plugins_url( 'js/slt-cf-media.js', __FILE__ );
	} else {
		$slt_js_admin = plugins_url( 'js/slt-cf-admin.min.js', __FILE__ );
		$slt_js_media = plugins_url( 'js/slt-cf-media.min.js', __FILE__ );
	}

	// Register scripts and styles
	wp_register_script( 'slt-cf-scripts', $slt_js_admin, array( 'jquery' ), SLT_CF_VERSION );
	wp_register_script( 'slt-cf-media', $slt_js_media, array( 'jquery' ), SLT_CF_VERSION );
	wp_register_script( 'slt-cf-colorpicker', plugins_url( 'js/colorpicker/js/colorpicker.js', __FILE__ ), array( 'jquery' ), SLT_CF_VERSION );
	wp_register_style( 'slt-cf-styles', $slt_custom_fields['css_url'], array(), SLT_CF_VERSION );
	wp_register_style( 'slt-cf-colorpicker', plugins_url( 'js/colorpicker/css/colorpicker.css', __FILE__ ), array(), SLT_CF_VERSION );

	// Register jQuery UI Addon Timepicker for date and time fields
	wp_register_script( 'jquery-ui-timepicker', plugins_url( 'js/jquery-ui/jquery-ui-timepicker-addon.min.js', __FILE__ ), array( 'jquery-ui-datepicker', 'jquery-ui-slider' ), '1.8.16', true );
	wp_register_style( 'jquery-ui-smoothness', $slt_custom_fields['ui_css_url'], array(), '1.8.16' );

	// Make sure forms allow file uploads
	if ( in_array( $pagenow, array( 'post-new.php', 'post.php' ) ) ) {
		add_action( 'post_edit_form_tag' , 'slt_cf_file_upload_form' );
	}
	if ( in_array( $pagenow, array( 'user-edit.php', 'profile.php' ) ) ) {
		add_action( 'user_edit_form_tag' , 'slt_cf_file_upload_form' );
	}

	// Deal with any form submissions for admin screen
	if ( array_key_exists( 'slt-cf-form', $_POST ) && check_admin_referer( 'slt-cf-' . $_POST['slt-cf-form'], '_slt_cf_nonce' ) ) {
		call_user_func( 'slt_cf_' . $_POST['slt-cf-form'] . '_form_process' );
	}

}

/**
 * Load text domain
 *
 * @since	1.0.1
 * @return	void
 */
function slt_cf_load_text_domain() {
	$locale = apply_filters( 'plugin_locale', get_locale(), SLT_CF_TEXT_DOMAIN );
	load_textdomain( SLT_CF_TEXT_DOMAIN, WP_LANG_DIR . '/' . SLT_CF_TEXT_DOMAIN . '/' . SLT_CF_TEXT_DOMAIN . '-' . $locale . '.mo' );
	load_plugin_textdomain( SLT_CF_TEXT_DOMAIN, false, basename( dirname( __FILE__ ) ) . '/lang/' );
}

/**
 * Login / registration scripts and styles
 *
 * @since	0.9
 * @return	void
 */
function slt_cf_login_enqueue_scripts() {
	wp_enqueue_style( 'slt-cf-registration-styles' );
}

/**
 * Admin scripts and styles
 *
 * @since	0.9
 * @return	void
 */
function slt_cf_admin_enqueue_scripts( $hook ) {
	global $pagenow, $slt_cf_admin_notices;
	$screen = get_current_screen();
	$edit_screen = in_array( $screen->base, array( 'post', 'user-edit', 'profile' ) );
	//echo '<pre>'; print_r( $screen ); echo '</pre>'; exit;

	// Global stuff
	$script_vars = array( 'ajaxurl' => admin_url( 'admin-ajax.php', SLT_CF_REQUEST_PROTOCOL ) );
	// Nonces for dismissal of notices
	if ( $slt_cf_admin_notices ) {
		foreach ( $slt_cf_admin_notices as $notice ) {
			$script_vars[ 'notice_nonce_' . sanitize_title( $notice['label'] ) ] = wp_create_nonce( 'notice_dismiss_' . sanitize_title( $notice['label'] ) );
		}
	}
	wp_localize_script( 'slt-cf-scripts', 'slt_custom_fields', $script_vars );
	wp_enqueue_script( 'slt-cf-scripts' );
	wp_enqueue_style( 'slt-cf-styles' );

	// Check for an edit screen
	// Also, for now include media uploader scripts for all "Appearance" and "Settings" pages,
	// in case file select button is being used directly there
	if ( $edit_screen || in_array( $pagenow, array( 'themes.php', 'options-general.php' ) ) ) {

		if ( $edit_screen ) {

			// Colorpicker
			/*
			wp_localize_script( 'slt-cf-colorpicker', 'slt_cf_colorpicker', array(
					'options'	=> array(
						'color'		=> '#ffffff',
					)
			));
			*/
			wp_enqueue_script( 'slt-cf-colorpicker' );
			wp_enqueue_style( 'slt-cf-colorpicker' );

			// Datepicker / Timepicker
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'jquery-ui-timepicker' );
			wp_enqueue_style( 'jquery-ui-smoothness' );

			// Google Maps
			if ( SLT_CF_USE_GMAPS ) {
				wp_localize_script( 'slt-cf-gmaps', 'slt_cf_gmaps', array(
					'geocoder_label'	=> esc_html__( 'Find an address', SLT_CF_TEXT_DOMAIN )
				));
				// Script is enqueued by slt_cf_gmap() function
			}

			// Sortable
			wp_enqueue_script( 'jquery-ui-sortable' );

		}

		// Media upload / select
		// Currently only valid for posts and options screens
		if ( ! $edit_screen || $screen->base == 'post' ) {

			// Check for file upload fields
			$file_upload_fields = slt_cf_current_fields_of_type( 'file' );

			// If an edit screen, only bother if there are file upload fields
			if ( SLT_CF_USE_FILE_SELECT && ( ! $edit_screen || $file_upload_fields ) ) {
				slt_cf_file_select_button_enqueue( $file_upload_fields );
			}

		}

	}

}

/**
 * Helper function to enqueue script for media select button
 *
 * If using the media select button outside this plugin, call this in your
 * admin_enqueue_scripts hook function
 *
 * @since	1.1
 * @param	array	$file_upload_fields
 * @return	void
 */
function slt_cf_file_select_button_enqueue( $file_upload_fields = array() ) {

	// Enqueue core API
	wp_enqueue_media();

	// Localization / custom JS vars
	$media_localization = array(
		'ajaxurl'			=> admin_url( 'admin-ajax.php', SLT_CF_REQUEST_PROTOCOL ),
		'button_text'		=> __( 'Select', SLT_CF_TEXT_DOMAIN ),
	);
	if ( $file_upload_fields ) {

		// Pass through values for all registered buttons
		foreach ( $file_upload_fields as $file_upload_field ) {
			$field_name = slt_cf_prefix( 'post' ) . $file_upload_field['name'];
			$media_localization['dialog_title__' . $field_name ] = $file_upload_field['file_dialog_title'];
			$media_localization['restrict_to_type__' . $field_name ] = $file_upload_field['file_restrict_to_type'];
			$media_localization['attach_to_post__' . $field_name ] = $file_upload_field['file_attach_to_post'] ? 'yes' : 'no';
		}

	}
	wp_localize_script( 'slt-cf-media', 'slt_cf_media', $media_localization );

	// Enqueue media script
	wp_enqueue_script( 'slt-cf-media' );

}

/**
 * Add any admin menus
 *
 * @since	0.7
 * @return	void
 */
function slt_cf_admin_menus() {

	// Database tools
	add_submenu_page( 'tools.php', SLT_CF_TITLE . ' ' . __( 'database tools', SLT_CF_TEXT_DOMAIN ), __( 'Custom Fields data', SLT_CF_TEXT_DOMAIN ), 'update_core', 'slt_cf_data_tools', 'slt_cf_database_tools_screen' );

}

/* Initialize fields
***************************************************************************************/

/**
 * Initialize the registered fields for the given edit request
 *
 * @since	0.1
 * @param	string	$request_type	'post' | 'attachment' | 'user' (corresponds to $type in slt_cf_register_box)
 * @param	string	$scope			For 'post', post_type; for 'attachment', post_mime_type; for 'user', role ('registration' if registering)
 * @param	mixed	$object_id		ID of object being edited (null for user registration)
 * @return	void
 */
function slt_cf_init_fields( $request_type, $scope, $object_id ) {
	global $slt_custom_fields, $slt_custom_fields_all_boxes, $wp_roles, $post, $user_id;

	// Store a copy of all boxes before paring down the main boxes var for this request
	// This is mainly so 4.2+ shared term splitting can be managed
	// Only done if requested
	$slt_custom_fields_all_boxes = null;
	if ( defined( 'SLT_CF_HANDLE_TERM_SPLITTING' ) && SLT_CF_HANDLE_TERM_SPLITTING ) {
		$slt_custom_fields_all_boxes = $slt_custom_fields['boxes'];
	}

	// Only run once per request
	static $init_run = false;
	if ( $init_run )
		return;
	$init_run = true;

	// Loop through boxes
	$unset_boxes = array();
	$field_names = array();
	foreach ( $slt_custom_fields['boxes'] as $box_key => &$box ) {

		// Initialize
		if ( ! is_array( $box['type'] ) )
			$box['type'] = array( $box['type'] );

		// Delete this box if it's not relevant to the current request
		if ( ! in_array( $request_type, $box['type'] ) ) {
			$unset_boxes[] = $box_key;
			continue;
		}

		// Check if required parameters are present
		if ( ! slt_cf_required_params( array( 'type', 'id', 'title' ), 'box', $box ) ) {
			$unset_boxes[] = $box_key;
			continue;
		}

		// Set defaults
		$box_defaults = array(
			'cloning'		=> false,
			'context'		=> 'advanced',
			'priority'		=> 'default',
			'description'	=> '',
			'fields'		=> array()
		);
		$box = array_merge( $box_defaults, $box );

		// Check if parameters are the right types
		if (
			! slt_cf_params_type( array( 'id', 'title', 'context', 'priority', 'description' ), 'string', 'box', $box ) ||
			! slt_cf_params_type( array( 'cloning' ), 'boolean', 'box', $box ) ||
			! slt_cf_params_type( array( 'fields', 'type' ), 'array', 'box', $box )
		) {
			$unset_boxes[] = $box_key;
			continue;
		}

		// Special context settings
		if ( $box['context'] == 'above-content' ) {
			$box['context'] = 'normal';
			$box['priority'] = 'high';
			$box['above_content'] = true;
		}

		// Loop through fields
		$unset_fields = array();
		foreach ( $box['fields'] as $field_key => &$field ) {

			// Any defaults that need setting early
			if ( ! array_key_exists( 'type', $field ) )
				$field['type'] = 'text';

			// Check if required parameters are present
			$required_params = array( 'name' );
			if ( $field['type'] != 'notice' )
				$required_params[] = 'label';
			if ( array_key_exists( 'options_type', $field ) && $field['options_type'] == 'terms' )
				$required_params[] = 'options_query';
			if ( ! slt_cf_required_params( $required_params, 'field', $field ) ) {
				$unset_fields[] = $field_key;
				continue;
			}

			// If the name is the same as the box id, this can cause problems
			if ( $field['name'] == $box['id'] ) {
				trigger_error( '<b>' . SLT_CF_TITLE . ':</b> Box <b>' . $box['id'] . '</b> has a field with the same name as the box id, which can cause problems.', E_USER_WARNING );
				$unset_fields[] = $field_key;
				continue;
			}

			// Using Google Maps?
			if ( $field['type'] == 'gmap' && ! SLT_CF_USE_GMAPS ) {
				trigger_error( '<b>' . SLT_CF_TITLE . ':</b> The field <b>' . $field['name'] . '</b> is a <code>gmap</code> type field, but <code>SLT_CF_USE_GMAPS</code> is set to disable Google Maps.', E_USER_WARNING );
				$unset_fields[] = $field_key;
				continue;
			}

			// Using File Select?
			if ( $field['type'] == 'file' && ! SLT_CF_USE_FILE_SELECT ) {
				trigger_error( '<b>' . SLT_CF_TITLE . ':</b> The field <b>' . $field['name'] . '</b> is a <code>file</code> type field, but <code>SLT_CF_USE_FILE_SELECT</code> is set to disable the file select functionality.', E_USER_WARNING );
				$unset_fields[] = $field_key;
				continue;
			}

			// File field type no longer needs the File Select plugin
			if ( $field['type'] == 'file' && function_exists( 'slt_fs_button' )  ) {
				trigger_error( '<b>' . SLT_CF_TITLE . ':</b> File upload fields no longer needs the SLT File Select plugin - you can remove it if you want! If you use that plugin\'s functionality elsewhere, you can now just call the functions provided by this Custom Fields plugin.', E_USER_NOTICE );
			}

			// File + Attachments List field types not allowed for file attachments or user profiles
			if ( in_array( $field['type'], array( 'file', 'attachments_list' ) ) && in_array( $request_type, array( 'attachment', 'user' ) ) ) {
				trigger_error( '<b>' . SLT_CF_TITLE . ':</b> The field <b>' . $field['name'] . '</b> is a <code>' . $field['type'] . '</code> type field, which is not allowed for attachments or user profiles.', E_USER_WARNING );
				$unset_fields[] = $field_key;
				continue;
			}

			// Set defaults
			$field_defaults = array(
				'cloning'					=> false,
				'label'						=> '',
				'scope'						=> array(),
				'label_layout'				=> 'block',
				'hide_label'				=> false,
				'file_button_label'			=> __( "Select file", SLT_CF_TEXT_DOMAIN ),
				'file_removeable'			=> true,
				'file_attach_to_post'		=> true,
				'file_dialog_title'			=> __( "Select file", SLT_CF_TEXT_DOMAIN ),
				'file_restrict_to_type'		=> '',
				'input_prefix'				=> '',
				'input_suffix'				=> '',
				'tabindex'					=> null,
				'description'				=> '',
				'default'					=> null,
				'multiple'					=> false,
				'options_type'				=> 'static',
				'options'					=> array(),
				'options_query'				=> array(),
				'group_options'				=> false,
				'group_by_post_type'		=> false,
				'no_options'				=> SLT_CF_NO_OPTIONS,
				'exclude_current'			=> true,
				'single'					=> true,
				'required'					=> false,
				'sortable'					=> false,
				'checkboxes_thumbnail'		=> false,
				'empty_option_text'			=> '[' . __( "None", SLT_CF_TEXT_DOMAIN ) . ']',
				'abbreviate_option_labels'	=> true,
				'width'						=> 0,
				'height'					=> 0,
				'charcounter'				=> false,
				'color_preview'				=> false,
				'allowtags'					=> array(), /* Deprecated */
				'allowed_html'				=> array(),
				'autop'						=> false,
				'wysiwyg_settings'			=> array(), /* Defaults are dealt with below */
				'preview_size'				=> 'medium',
				'datepicker_format'			=> $slt_custom_fields['datepicker_default_format'],
				'timepicker_format'			=> $slt_custom_fields['timepicker_default_format'],
				'timepicker_ampm'			=> $slt_custom_fields['timepicker_default_ampm'],
				'location_marker'			=> 1,
				'gmap_type'					=> 'roadmap',
				'edit_on_profile'			=> false,
				'attachments_list_options'	=> array(),
				'make_query_var'			=> false,
			);
			// Defaults dependent on request type
			switch ( $request_type ) {
				case 'post': {
					$field_defaults['capabilities'] = array( 'edit_posts' );
					if ( empty( $field_defaults['scope'] ) ) {
						// All public custom post types, plus posts and pages
						$field_defaults['scope'] = get_post_types( array( 'public' => true, '_builtin' => false ), 'names', 'and' );
						$field_defaults['scope'][] = 'post';
						$field_defaults['scope'][] = 'page';
					}
					break;
				}
				case 'attachment': {
					$field_defaults['capabilities'] = array( 'edit_posts' );
					break;
				}
				case 'user': {
					$field_defaults['capabilities'] = array( 'edit_users' );
					break;
				}
			}
			// Merge passed values with defaults
			$field = array_merge( $field_defaults, $field );
			// Defaults dependent on options type
			switch ( $field['options_type'] ) {
				case 'posts': {
					if ( ! array_key_exists( 'options_query', $field ) )
						$field['options_query'] = array( 'posts_per_page' => -1 );
					else
						$field['options_query'] = array_merge( array( 'posts_per_page' => -1 ), $field['options_query'] );
					break;
				}
				case 'users': {
					if ( empty( $field['options_query'] ) )
						$field['options_query'] = array_keys( $wp_roles->role_names );
					break;
				}
			}
			// Defaults dependent on field type
			switch ( $field['type'] ) {
				case 'gmap': {
					if ( ! $field['width'] )
						$field['width'] = 500;
					if ( ! $field['height'] )
						$field['height'] = 300;
					if ( ! $field['default'] || ! is_array( $field['default'] ) )
						$field['default'] = array(
							"centre_latlng"	=> "52.337946593485135,-1.667382812500029",
							"zoom"			=> "6",
							"map_markers"	=> array( "52.24386921477694,-0.9203125000000227" ),
							"bounds_sw"		=> "50.27802587971423,-7.160546875000023",
							"bounds_ne"		=> "54.306194393010095,3.8257812499999773"
						);
					break;
				}
				case 'wysiwyg': {
					if ( ! array_key_exists( 'teeny', $field['wysiwyg_settings'] ) )
						$field['wysiwyg_settings']['teeny'] = true;
					if ( ! array_key_exists( 'media_buttons', $field['wysiwyg_settings'] ) )
						$field['wysiwyg_settings']['media_buttons'] = false;
					break;
				}
				case 'attachments_list': {
					$attached_images_options_defaults = array(
						'post_mime_type'			=> 'image',
						'image_display_size'		=> 'thumbnail',
						'unattach_checkboxes'		=> true,
					);
					$field['attachments_list_options'] = array_merge( $attached_images_options_defaults, $field['attachments_list_options'] );
					break;
				}
			}

			// Check if parameters are the right types
			if (
				! slt_cf_params_type( array( 'name', 'label', 'type', 'label_layout', 'file_button_label', 'file_dialog_title', 'file_restrict_to_type', 'input_prefix', 'input_suffix', 'description', 'options_type', 'no_options', 'empty_option_text', 'preview_size', 'datepicker_format', 'timepicker_format' ), 'string', 'field', $field ) ||
				! slt_cf_params_type( array( 'hide_label', 'file_removeable', 'multiple', 'exclude_current', 'required', 'group_options', 'group_by_post_type', 'autop', 'edit_on_profile', 'timepicker_ampm', 'color_preview' ), 'boolean', 'field', $field ) ||
				! slt_cf_params_type( array( 'scope', 'options', 'allowtags', 'options_query', 'capabilities', 'attachments_list_options' ), 'array', 'field', $field ) ||
				! slt_cf_params_type( array( 'width', 'height' ), 'integer', 'field', $field )
			) {
				$unset_fields[] = $field_key;
				continue;
			}

			// Check scope
			if ( ! slt_cf_check_scope( $field, $request_type, $scope, $object_id ) ) {
				$unset_fields[] = $field_key;
				continue;
			}

			// Check capability if in admin
			if ( is_admin() ) {
				// If object-specific capability check fails
				// OR general capability check fails
				// AND it's not a user request, a user box, the user editing their own profile, with the edit_on_profile flag set
				if (
					(	( in_array( $request_type, array( 'post', 'attachment' ) ) && ! slt_cf_capability_check( $field['type'], $field['capabilities'], $object_id ) ) ||
						! slt_cf_capability_check( $field['type'], $field['capabilities'] )
					) &&
					! ( $request_type == 'user' && in_array( 'user', $box['type'] ) && IS_PROFILE_PAGE && $field['edit_on_profile'] )
				) {
					// Remove this field
					$unset_fields[] = $field_key;
					continue;
				}
			}

			// Check uniqueness of field name in scope
			// ???? Code could be streamlined!
			$field_name_used = false;
			foreach ( $field['scope'] as $scope_key => $scope_value ) {
				if ( is_string( $scope_key ) ) {
					// Taxonomic scope
					if ( ! array_key_exists( $scope_key, $field_names ) )
						$field_names[ $scope_key ] = array();
					foreach ( $scope_value as $scope_term_name ) {
						if ( ! array_key_exists( $scope_term_name, $field_names[ $scope_key ] ) ) {
							// Register scope
							$field_names[ $scope_key ][ $scope_term_name ] = array();
						} else if ( in_array( $field['name'], $field_names[ $scope_key ][ $scope_term_name ] ) ) {
							// Field name already used in this scope
							trigger_error( '<b>' . SLT_CF_TITLE . ':</b> Field name <b>' . $field['name'] . '</b> has already been used within this scope', E_USER_WARNING );
							$field_name_used = true;
							$unset_fields[] = $field_key;
							break;
						}
						if ( ! $field_name_used ) {
							// Register field name in this scope
							$field_names[ $scope_key ][ $scope_term_name ][] = $field['name'];
						}
					}
				} else {
					// Normal scope, i.e. post type or user role
					if ( ! array_key_exists( $scope_value, $field_names ) ) {
						// Register scope
						$field_names[ $scope_value ] = array();
					} else if ( in_array( $field['name'], $field_names[ $scope_value ] ) ) {
						// Field name already used in this scope
						trigger_error( '<b>' . SLT_CF_TITLE . ':</b> Field name <b>' . $field['name'] . '</b> has already been used within this scope', E_USER_WARNING );
						$field_name_used = true;
						$unset_fields[] = $field_key;
						break;
					}
					// Register field name in this scope
					$field_names[ $scope_value ][] = $field['name'];
				}
			}
			if ( $field_name_used )
				continue;

			/****************************************************************************
			From this point on, this field is considered as valid for the current request
			 ****************************************************************************/

			// Gather dynamic options data?
			if ( $field['options_type'] != 'static' ) {

				// IDs for placeholders
				$placeholder_ids = array(
					'object'	=> 0,
					'parent'	=> 0
				);

				// Placeholders appropriate when editing posts and attachments
				if ( in_array( $request_type, array( 'post', 'attachment' ) ) && isset( $post ) && is_object( $post ) ) {
					// Object ID
					if ( property_exists( $post, 'ID' ) )
						$placeholder_ids['object'] = $post->ID;
					// Parent ID - for hierarchical post types only
					if ( is_post_type_hierarchical( get_post_type( $post ) ) && property_exists( $post, 'post_parent' ) )
						$placeholder_ids['parent'] = $post->post_parent;
				}

				// Placeholders appropriate when editing users
				if ( $request_type == 'user' )
					$placeholder_ids['object'] = $user_id;

				// Check for any placeholder values in the options query
				// Need to check query manually in case value is inside an array
				foreach ( $field['options_query'] as $oq_key => &$oq_value ) {
					foreach ( $placeholder_ids as $ph_key => $ph_value ) {
						$placeholder = '[' . strtoupper( $ph_key ) . '_ID]';
						if ( $oq_value === $placeholder ) {
							$oq_value = $ph_value;
						} else if ( is_array( $oq_value ) && in_array( $placeholder, $oq_value ) ) {
							$oq_value = str_replace( $placeholder, $ph_value, $oq_value );
						}
					}
				}

				// Taxonomy term IDs
				if ( in_array( $request_type, array( 'post', 'attachment' ) ) && array_key_exists( 'tax_query', $field['options_query'] ) ) {

					foreach ( $field['options_query']['tax_query'] as &$tax_query ) {
						if ( is_array( $tax_query ) && array_key_exists( 'terms', $tax_query ) && $tax_query['terms'] == '[TERM_IDS]' && array_key_exists( 'taxonomy', $tax_query ) && taxonomy_exists( $tax_query['taxonomy'] ) ) {
							$related_terms = get_the_terms( $post->ID, $tax_query['taxonomy'] );
							$related_term_ids = array();
							if ( $related_terms ) {
								foreach ( $related_terms as $related_term )
									$related_term_ids[] = $related_term->term_id;
								$tax_query['terms'] = $related_term_ids;
							} else {
								// No related terms - return no results
								$field['options_query'] = array( 'post__in' => array( 0 ) );
							}
						}
					}

				}

				switch ( $field['options_type'] ) {

					case 'posts': {
						// Get posts
						if ( ! array_key_exists( 'post_type', $field['options_query'] ) )
							$field['options_query']['post_type'] = 'post';
						// For now, grouping only works for categories
						if ( $field[ 'group_options' ] && in_array( 'category', get_object_taxonomies( $field['options_query']['post_type'] ) ) )
							$field['options_query']['orderby'] = 'category';
						// Exclude current post?
						if ( $field[ 'exclude_current' ] ) {
							if ( is_object( $post ) && property_exists( $post, 'ID' ) )
								$field['options_query']['post__not_in'][] = $post->ID;
						}
						// Add sorting by post type if multiple post types, in order to group in output
						$multiple_post_types = ( isset( $field['options_query'] ) && is_array( $field['options_query'] ) && isset( $field['options_query']['post_type'] ) && is_array( $field['options_query']['post_type'] ) );
						if ( $multiple_post_types ) {
							add_action( 'pre_get_posts', 'slt_cf_sort_queries_by_post_type' );
						}
						// Do query
						$posts_query = new WP_Query( $field['options_query'] );
						// Remove sorting by post type
						if ( $multiple_post_types ) {
							remove_action( 'pre_get_posts', 'slt_cf_sort_queries_by_post_type' );
						}
						$posts = $posts_query->posts;
						$field['options'] = array();
						/** @todo Heirarchical post selection
						if ( $field['hierarchical_options'] && is_string( $field['options_query']['post_type'] ) && is_post_type_hierarchical( $field['options_query']['post_type'] ) ) {

						}
						 */
						$current_grouping = null;
						foreach ( $posts as $post_data ) {
							if ( $multiple_post_types && $field[ 'group_by_post_type' ] ) {
								// This takes precedence over group_options
								if ( is_null( $current_grouping ) || $post_data->post_type != $current_grouping ) {
									// New post type, initiate an option group
									$post_type_object = get_post_type_object( $post_data->post_type );
									// Adding prefix is necessary to avoid clashes with pages named after post types
									$field['options'][ __( 'Post type:', SLT_CF_TEXT_DOMAIN ) . ' ' . $post_type_object->label ] = '[optgroup]';
									$current_grouping = $post_data->post_type;
								}
							} else if ( $field[ 'group_options' ] ) {
								$this_category = get_the_category( $post_data->ID );
								if ( is_null( $current_grouping ) || $this_category[0]->cat_ID != $current_grouping[0]->cat_ID ) {
									// New category, initiate an option group
									$field['options'][ $this_category[0]->cat_name ] = '[optgroup]';
									$current_grouping = $this_category;
								}
							}
							$option_text = $field['abbreviate_option_labels'] ? slt_cf_abbreviate( $post_data->post_title ) : $post_data->post_title;
							$field['options'][ $option_text ] = $post_data->ID;
						}
						//echo '<pre>'; print_r( $field['options'] ); echo '</pre>'; exit;
						break;
					}

					case 'users': {
						// Get users
						$field['options'] = array();
						$get_users_args = array();
						// Exclude current user?
						if ( $field[ 'exclude_current' ] )
							$get_users_args['exclude'] = $user_id;
						// Loop through roles
						foreach ( $field['options_query'] as $role ) {
							// Get users for this role
							$get_role_args = array_merge( $get_users_args, array( 'role' => $role ) );
							$users = get_users( $get_role_args );
							if ( count( $users ) ) {
								// Grouping?
								if ( $field[ 'group_options' ] )
									$field['options'][ str_replace( '_', ' ', strtoupper( $role ) ) ] = '[optgroup]';
								// Add users to options
								foreach ( $users as $user_data ) {
									$option_text = $field['abbreviate_option_labels'] ? slt_cf_abbreviate( $user_data->display_name ) : $user_data->display_name;
									$field['options'][ $option_text ] = $user_data->ID;
								}
							}
						}
						break;
					}

					case 'terms': {
						// Get terms
						$args = $field['options_query'];
						$taxonomies = $args['taxonomies'];
						$field['options'] = array();
						/** @todo Heirarchical post selection
						if ( $field['hierarchical_options'] ) {
						$field['options_query']['hierarchical'] = true;
						slt_cf_hierarchical_terms( $field, '&nbsp;&nbsp;&nbsp;', @intval( $args['child_of'] ) );
						} else
						 */
						if ( ! is_wp_error( $option_terms = get_terms( $taxonomies, $args ) ) ) {
							foreach ( $option_terms as $option_term ) {
								$option_text = $field['abbreviate_option_labels'] ? slt_cf_abbreviate( $option_term->name ) : $option_term->name;
								$field['options'][ $option_text ] = $option_term->term_id;
							}
						}
						break;
					}

					case 'countries': {
						$field['options'] = slt_cf_options_preset( 'countries' );
						break;
					}

					default: {
					// Run filter for custom option types
					$field['options'] = apply_filters( 'slt_cf_populate_options', $field['options'], $request_type, $scope, $object_id, $field );
					break;
					}

				}
			}

			// Gather attachments to list?
			if ( $field['type'] == 'attachments_list' && is_admin() && $request_type == 'post' && isset( $post ) && is_object( $post ) ) {

				// get_children() arguments
				$get_children_args = array(
					'post_type'			=> 'attachment',
					'post_parent'		=> $post->ID,
					'post_mime_type'	=> $field['attachments_list_options']['post_mime_type']
				);
				$get_children_args = apply_filters( 'slt_cf_attachments_list_query', $get_children_args, $object_id, $post, $field );

				// Get attachments
				$field['attachments_list'] = get_children( $get_children_args );

			}

		} // Fields foreach

		// Unset any invalid fields
		foreach ( array_unique( $unset_fields ) as $field_key )
			unset( $slt_custom_fields['boxes'][ $box_key ]['fields'][ $field_key ] );

	} // Boxes foreach

	// Unset any invalid boxes
	$num_boxes = count( $slt_custom_fields['boxes'] );
	for ( $box_key = 0; $box_key < $num_boxes; $box_key++ ) {
		if ( count( $slt_custom_fields['boxes'][ $box_key ]['fields'] ) == 0 || in_array( $box_key, $unset_boxes ) )
			unset( $slt_custom_fields['boxes'][ $box_key ] );
	}

	// Post-processing of boxes
	$slt_custom_fields['boxes'] = apply_filters( 'slt_cf_init_boxes', $slt_custom_fields['boxes'] );

}
