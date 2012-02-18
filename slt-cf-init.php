<?php

/* Initialize
***************************************************************************************/

// Global initialization
function slt_cf_init() {
	global $slt_custom_fields;
	// Register scripts and styles
	wp_register_style( 'slt-cf-styles', $slt_custom_fields['css_url'] );
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		$slt_js_admin = plugins_url( 'js/slt-cf-admin.js', __FILE__ );
		$slt_js_file_select = plugins_url( 'js/slt-cf-file-select.js', __FILE__ );
		$slt_js_gmaps = plugins_url( 'js/slt-cf-gmaps.js', __FILE__ );
	} else {
		$slt_js_admin = plugins_url( 'js/slt-cf-admin.min.js', __FILE__ );
		$slt_js_file_select = plugins_url( 'js/slt-cf-file-select.min.js', __FILE__ );
		$slt_js_gmaps = plugins_url( 'js/slt-cf-gmaps.min.js', __FILE__ );
	}
	wp_register_script( 'slt-cf-scripts', $slt_js_admin, array( 'jquery' ) );
	if ( ! SLT_CF_WP_IS_GTE_3_3 ) {
		// Register jQuery UI Datepicker for below WP 3.3
		wp_register_script( 'jquery-ui-datepicker', plugins_url( 'js/jquery-datepicker/jquery-ui-1.8.16.custom.min.js', __FILE__ ), array( 'jquery-ui-core' ), '1.8.16', true );
	}
	wp_register_style( 'jquery-datepicker-smoothness', $slt_custom_fields['datepicker_css_url'] );
	wp_register_script( 'slt-cf-file-select', $slt_js_file_select, array( 'jquery', 'media-upload', 'thickbox' ) );
	wp_register_script( 'google-maps-api', SLT_CF_REQUEST_PROTOCOL . 'maps.google.com/maps/api/js?sensor=false' );
	wp_register_script( 'slt-cf-gmaps', $slt_js_gmaps, array( 'jquery-ui-core' ) );
	// Google Maps for front and admin
	if ( SLT_CF_USE_GMAPS ) {
		wp_enqueue_script( 'google-maps-api' );
		wp_enqueue_script( 'slt-cf-gmaps' );
	}
	// Generic hook, mostly for dependent plugins to hook to
	// See: http://core.trac.wordpress.org/ticket/11308#comment:7
	do_action( 'slt_cf_init' );
}

// Admin initialization
function slt_cf_admin_init() {
	global $slt_cf_admin_notices, $slt_custom_fields, $pagenow, $wp_version;
	$requested_file = basename( $_SERVER['SCRIPT_FILENAME'] );
	
	// Decide now which notices to output
	$slt_cf_admin_notices = array();
	if ( $slt_custom_fields['options']['alert-07-cleanup'] && ! ( $pagenow == 'tools.php' && array_key_exists( 'page', $_GET ) && $_GET['page'] == 'slt_cf_data_tools' ) )
		$slt_cf_admin_notices[] = 'alert-07-cleanup';

	// Scripts and styles
	wp_enqueue_style( 'slt-cf-styles' );
	wp_enqueue_script( 'slt-cf-scripts' );
	$script_vars = array( 'ajaxurl' => admin_url( 'admin-ajax.php', SLT_CF_REQUEST_PROTOCOL ) );
	if ( in_array( 'alert-07-cleanup', $slt_cf_admin_notices ) ) {
		$script_vars['update_option_nonce'] = wp_create_nonce( 'slt-cf-update-option' );
		$script_vars['update_option_fail'] = __( 'There was a problem updating the option.', 'slt-custom-fields' );
	}
	wp_localize_script( 'slt-cf-scripts', 'slt_custom_fields', $script_vars );
	if ( ! SLT_CF_WP_IS_GTE_3_3 ) {
		// WP versions below 3.3 need TinyMCE initializing; 3.3+ uses wp_editor()
		add_action( 'admin_print_footer_scripts', 'wp_tiny_mce', 25 );
	}
	// Datepicker
	wp_enqueue_script( 'jquery-ui-datepicker' );
	wp_enqueue_style( 'jquery-datepicker-smoothness' );
	// Make sure forms allow file uploads
	if ( in_array( $requested_file, array( 'post-new.php', 'post.php' ) ) )
		add_action( 'post_edit_form_tag' , 'slt_cf_file_upload_form' );
	if ( in_array( $requested_file, array( 'user-edit.php', 'profile.php' ) ) )
		add_action( 'user_edit_form_tag' , 'slt_cf_file_upload_form' );
	// Google Maps
	if ( SLT_CF_USE_GMAPS ) {
		if ( SLT_CF_WP_IS_GTE_3_3 )
			wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_localize_script( 'slt-cf-gmaps', 'slt_cf_gmaps', array(
			'geocoder_label'	=> esc_html__( 'Find an address', 'slt-custom-fields' )
		));
	}
	// File select
	if ( SLT_CF_USE_FILE_SELECT ) {
		wp_enqueue_script( 'slt-cf-file-select' );
		wp_enqueue_style( 'thickbox' );
		wp_localize_script( 'slt-cf-file-select', 'slt_cf_file_select', array(
			'ajaxurl'			=> admin_url( 'admin-ajax.php', SLT_CF_REQUEST_PROTOCOL ),
			'text_select_file'	=> esc_html__( 'Select', 'slt-custom-fields' )
		));
		if ( ! SLT_CF_WP_IS_GTE_3_3 && $pagenow == 'media-upload.php' && array_key_exists( 'slt_cf_fs_field', $_GET ) ) {
			// For WP versions below 3.3, disable the Flash uploader when the File select overlay is invoked
			// The JS for this doesn't work with the Flash uploader
			add_filter( 'flash_uploader', '__return_false', 5 );
		}
	}

	// Deal with any form submissions for admin screen
	if ( array_key_exists( 'slt-cf-form', $_POST ) && check_admin_referer( 'slt-cf-' . $_POST['slt-cf-form'], '_slt_cf_nonce' ) )
		call_user_func( 'slt_cf_' . $_POST['slt-cf-form'] . '_form_process' );
		
}

/**
 * Add any admin menus
 * 
 * @since	0.7
 * @return	void
 */
function slt_cf_admin_menus() {
	// Database tools
	add_submenu_page( 'tools.php', SLT_CF_TITLE . ' ' . __( 'database tools', 'slt-custom-fields' ), __( 'Custom Fields data', 'slt-custom-fields' ), 'update_core', 'slt_cf_data_tools', 'slt_cf_database_tools_screen' );
}

/* Initialize fields
***************************************************************************************/
function slt_cf_init_fields( $request_type, $scope, $object_id ) {
	global $slt_custom_fields, $wp_roles, $post;

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
			if ( $field['type'] == 'file' && function_exists( 'slt_fs_button' )  )
				trigger_error( '<b>' . SLT_CF_TITLE . ':</b> File upload fields no longer needs the SLT File Select plugin - you can remove it if you want! If you use that plugin\'s functionality elsewhere, you can now just call the functions provided by this Custom Fields plugin.', E_USER_NOTICE );
					
			// Set defaults
			$field_defaults = array(
				'cloning'					=> false,
				'label'						=> '',
				'scope'						=> array(),
				'label_layout'				=> 'block',
				'hide_label'				=> false,
				'file_button_label'			=> __( "Select file", "slt-custom-fields" ),
				'file_removeable'			=> true,
				'file_attach_to_post'		=> true,
				'input_prefix'				=> '',
				'input_suffix'				=> '',
				'description'				=> '',
				'default'					=> null,
				'multiple'					=> false,
				'options_type'				=> 'static',
				'options'					=> array(),
				'options_query'				=> array(),
				'group_options'				=> false,
				'no_options'				=> SLT_CF_NO_OPTIONS,
				'exclude_current'			=> true,
				'single'					=> true,
				'required'					=> false,
				'empty_option_text'			=> '[' . __( "None", "slt-custom-fields" ) . ']',
				'width'						=> 0,
				'height'					=> 0,
				'charcounter'				=> false,
				'allowtags'					=> array(), /* Deprecated */
				'allowed_html'				=> array(),
				'autop'						=> false,
				'wysiwyg_settings'			=> array(), /* Defaults are dealt with below */
				'preview_size'				=> 'medium',
				'group_options'				=> false,
				'datepicker_format'			=> $slt_custom_fields['datepicker_default_format'],
				'location_marker'			=> true,
				'gmap_type'					=> 'roadmap',
				'edit_on_profile'			=> false
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
							"marker_latlng"	=> "52.24386921477694,-0.9203125000000227",
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
			}

			// Check if parameters are the right types
			if (
				! slt_cf_params_type( array( 'name', 'label', 'type', 'label_layout', 'file_button_label', 'input_prefix', 'input_suffix', 'description', 'options_type', 'no_options', 'empty_option_text', 'preview_size', 'datepicker_format' ), 'string', 'field', $field ) ||
				! slt_cf_params_type( array( 'hide_label', 'file_removeable', 'multiple', 'exclude_current', 'required', 'group_options', 'autop', 'edit_on_profile' ), 'boolean', 'field', $field ) ||
				! slt_cf_params_type( array( 'scope', 'options', 'allowtags', 'options_query', 'capabilities' ), 'array', 'field', $field ) ||
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

				// Check for any placeholder values in the query
				if ( array_search( '[OBJECT_ID]', $field['options_query'] ) ) {

					// Object ID
					switch ( $field['options_type'] ) {
						case 'posts': {
							global $post;
							$object_id = $post->ID;
							break;
						}
						case 'users': {
							global $user_id;
							$object_id = $user_id;
							break;
						}
					}
					$field['options_query'] = str_replace(
						array( '[OBJECT_ID]' ),
						array( $object_id ),
						$field['options_query']
					);

				} else if ( array_key_exists( 'tax_query', $field['options_query'] ) && $request_type == 'post' ) {

					// Taxonomy term IDs
					global $post;
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
							global $post;
							$field['options_query']['post__not_in'][] = $post->ID;
						}
						$posts_query = new WP_Query( $field['options_query'] );
						$posts = $posts_query->posts;
						$field['options'] = array();
						/** TODO
						// Hierarchical?
						if ( $field['hierarchical_options'] && is_string( $field['options_query']['post_type'] ) && is_post_type_hierarchical( $field['options_query']['post_type'] ) ) {
							
						}
						*/
						$current_category = array();
						foreach ( $posts as $post_data ) {
							if ( $field[ 'group_options' ] ) {
								$this_category = get_the_category( $post_data->ID );
								if ( empty( $current_category ) || $this_category[0]->cat_ID != $current_category[0]->cat_ID ) {
									// New category, initiate an option group
									$field['options'][ $this_category[0]->cat_name ] = '[optgroup]';
									$current_category = $this_category;
								}
							}
							$field['options'][ slt_cf_abbreviate( $post_data->post_title ) ] = $post_data->ID;
						}
						break;
					}

					case 'users': {
						// Get users
						$field['options'] = array();
						$get_users_args = array();
						// Exclude current user?
						if ( $field[ 'exclude_current' ] ) {
							global $user_id;
							$get_users_args['exclude'] = $user_id;
						}
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
								foreach ( $users as $user_data )
									$field['options'][ $user_data->display_name ] = $user_data->ID;
							}
						}
						break;
					}

					case 'terms': {
						// Get terms
						$args = $field['options_query'];
						$taxonomies = $args['taxonomies'];
 						$field['options'] = array();
						/** TODO
					 	if ( $field['hierarchical_options'] ) {
							$field['options_query']['hierarchical'] = true;
							slt_cf_hierarchical_terms( $field, '&nbsp;&nbsp;&nbsp;', @intval( $args['child_of'] ) );
						} else
						*/
						if ( ! is_wp_error( $option_terms = get_terms( $taxonomies, $args ) ) ) {
							foreach ( $option_terms as $option_term )
								$field['options'][$option_term->name] = $option_term->term_id;
						}
 						break;
					}

					default: {
						// Run filter for custom option types
						$field['options'] = apply_filters( 'slt_cf_populate_options', $field['options'], $request_type, $scope, $object_id, $field );
						break;
					}

				}
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
