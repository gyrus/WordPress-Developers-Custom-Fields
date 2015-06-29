<?php

/* Update a setting
***************************************************************************************/
function slt_cf_setting( $key, $value ) {
	global $slt_custom_fields;

	// Pass renamed settings
	if ( $key == 'datepicker_css_url' )
		$key = 'ui_css_url';

	// Set setting
	if ( is_string( $key ) )
		$slt_custom_fields[ $key ] = $value;
}

/* Updating database options
***************************************************************************************/

// Update option
function slt_cf_update_option( $key, $value ) {
	global $slt_custom_fields;
	$options = get_option( 'slt_cf_options' );
	$options[ $key ] = $value;
	update_option( 'slt_cf_options', $options );
	$slt_custom_fields['options'] = $options;
}

// Update via AJAX
add_action( 'wp_ajax_slt_cf_update_option', 'slt_cf_update_option_ajax' );
function slt_cf_update_option_ajax() {
	if ( array_key_exists( 'key', $_REQUEST ) && $_REQUEST['key'] && array_key_exists( 'value', $_REQUEST ) && wp_verify_nonce( $_REQUEST['update-option-nonce'], 'slt-cf-update-option' ) ) {
		slt_cf_update_option( $_REQUEST['key'], $_REQUEST['value'] );
		echo 'updated';
	}
	exit( 0 );
}

/* Register a box
***************************************************************************************/
function slt_cf_register_box( $box_data = array() ) {
	global $slt_custom_fields;
	$slt_custom_fields['boxes'][] = $box_data;
}

/* Return field key (with prefix)
***************************************************************************************/
function slt_cf_field_key( $key, $object_type = 'post' ) {
	global $slt_custom_fields;
	return slt_cf_prefix( $object_type ) . $key;
}

/* Return the right prefix
***************************************************************************************/
function slt_cf_prefix( $object_type = 'post' ) {
	global $slt_custom_fields;
	$prefix = $slt_custom_fields['prefix'];
	return $prefix;
}

/* Strip the prefix
***************************************************************************************/
function slt_cf_strip_prefix( $key ) {
	global $slt_custom_fields;
	$prefix = $slt_custom_fields['prefix'];
	if ( strlen( $key ) >= strlen( $prefix ) && substr( $key, 0, strlen( $prefix ) ) == $prefix )
		$key = substr( $key, strlen( $prefix ) );
	return $key;
}

/* Get / display custom field value
***************************************************************************************/
function slt_cf_field_value( $key, $type = 'post', $id = 0, $before = '', $after = '', $echo = false, $single = true ) {
	$key_no_prefix = $key;
	// Allow hooks to take over
	$value = apply_filters( 'slt_cf_field_value', null, $key, $type, $id, $before, $after, $echo, $single );
	if ( $value === null ) {
		// Do normal check
		$key = slt_cf_field_key( $key, $type );
		$id = slt_cf_default_id( $type, $id );
		if ( $type == 'attachment' )
			$metadata_type = 'post';
		else
			$metadata_type = $type;
		$value = get_metadata( $metadata_type, $id, $key, $single );
		// Sortable?
		if ( ! $single ) {
			$value = slt_cf_maybe_sort( $value, $key_no_prefix, $metadata_type, $id );
		}
	}
	if ( $value && is_string( $value ) ) {
		$value = $before . $value . $after;
		if ( $echo ) {
			echo $value;
		} else {
			return $value;
		}
	} else if ( ! $echo ) {
		return $value;
	}
}

/* Get all custom field values set by this plugin
***************************************************************************************/
function slt_cf_all_field_values( $type = 'post', $id = 0, $multiple_fields = array() ) {
	global $slt_custom_fields;
	$prefix = slt_cf_prefix( $type );
	$values = array();
	$all_values = array();
	$id = slt_cf_default_id( $type, $id );

	switch ( $type ) {
		case 'post':
		case 'attachment': {
			$values = get_post_custom( $id );
			break;
		}
		case 'user': {
			// Using get_user_metavalues because get_userdata returns an object,
			// and if keys have dashes in, they get lost in the creation of the object properties
			$user_values = get_user_metavalues( array( $id ) );
			$values = array();
			foreach ( $user_values[ $id ] as $user_value )
				$values[ $user_value->meta_key ] = $user_value->meta_value;
			break;
		}
	}

	if ( is_array( $values ) ) {
		foreach ( $values as $key => $value ) {
			if ( strlen( $key ) > strlen( $prefix ) && substr( $key, 0, strlen( $prefix ) ) == $prefix ) {
				$key_no_prefix = preg_replace( '#' . $prefix . '#', '', $key, 1 );
				// Only keep specified fields as arrays
				if ( is_array( $value ) && ! in_array( $key_no_prefix, $multiple_fields ) )
					$value = $value[0];
				$all_values[ $key_no_prefix ] = $value;
			}
		}
	}

	// Unserialize
	$all_values = array_map( 'maybe_unserialize', $all_values );

	// Sortable fields?
	if ( ! empty( $multiple_fields ) ) {
		foreach ( $multiple_fields as $multiple_field ) {
			if ( array_key_exists( $multiple_field, $all_values ) ) {
				$all_values[ $multiple_field ] = slt_cf_maybe_sort( $all_values[ $multiple_field ], $multiple_field, $type, $id );
			}
		}
	}

	return $all_values;
}

/* Get the data on the ordering of a sortable field
***************************************************************************************/
function slt_cf_field_values_order( $key, $type = 'post', $id = 0 ) {
	$key = slt_cf_field_key( $key, $type ) . '_order';
	$id = slt_cf_default_id( $type, $id );
	if ( $type == 'attachment' )
		$metadata_type = 'post';
	else
		$metadata_type = $type;
	return get_metadata( $metadata_type, $id, $key, true );
}

/* Try to sort passed values by stored ordering
***************************************************************************************/
function slt_cf_maybe_sort( $values, $key, $type, $id ) {
	if ( $order = slt_cf_field_values_order( $key, $type, $id ) ) {
		$new_values = array();
		foreach ( explode( ',', $order ) as $key => $val ) {
			if ( in_array( $val, $values ) ) {
				$new_values[ $key ] = $val;
			}
		}
		if ( $new_values ) {
			$values = $new_values;
		}
	}
	return $values;
}

/* Test to see if custom field has been set for an object
***************************************************************************************/
function slt_cf_field_exists( $key, $type = 'post', $id = 0 ) {
	// Allow hooks to take over
	$check = apply_filters( 'slt_cf_field_exists', null, $key, $type, $id );
	if ( $check !== null )
		return $check;
	// Do normal check
	global $wpdb;
	$field_exists = false;
	$key = slt_cf_field_key( $key, $type );
	$id = slt_cf_default_id( $type, $id );
	switch ( $type ) {
		case 'post':
		case 'attachment'; {
			$table = $wpdb->postmeta;
			break;
		}
		case 'user': {
			$table = $wpdb->usermeta;
			break;
		}
	}
	$id_field = $type . '_id';
	$field = $wpdb->get_results("
		SELECT	meta_value
		FROM	$table
		WHERE	meta_key	= '$key'
		AND		$id_field	= $id
		LIMIT	0, 1
	");
	if ( count( $field ) )
		$field_exists = true;
	return $field_exists;
}

/**
 * Return an array of all fields for the current request of a certain type
 *
 * @since	0.1
 * @param	string	$type
 * @return	array
 */
function slt_cf_current_fields_of_type( $type = '' ) {
	global $slt_custom_fields;
	$fields = array();

	foreach ( $slt_custom_fields['boxes'] as $box ) {
		foreach ( $box['fields'] as $field ) {
			if ( $field['type'] == $type ) {
				$fields[] = $field;
			}
		}
	}

	return $fields;
}

/* Get all fields applied to an object
***************************************************************************************/
function slt_cf_get_current_fields( $type = 'post', $id = 0 ) {
	global $slt_custom_fields;
	$fields = array();
	$id = slt_cf_default_id( $type, $id );
	switch ( $type ) {
		case 'post': {
			$scope = get_post_type( $id );
			break;
		}
		case 'attachment': {
			$scope = get_post_mime_type( $id );
			break;
		}
		case 'user': {
			$user = new WP_User( $id );
			$user_roles = $user->roles;
			$scope = array_shift( $user_roles );
			break;
		}
	}
	foreach ( $slt_custom_fields['boxes'] as $box ) {
		foreach ( $box['fields'] as $field ) {
			if ( slt_cf_check_scope( $field, $type, $scope, $id ) )
				$fields[] = $field;
		}
	}
	return $fields;
}

/* Gather names of all fields
***************************************************************************************/
function slt_cf_get_field_names( $objects = array(), $types = array(), $add_prefix = true ) {
	global $slt_custom_fields;
	$names = array();
	foreach ( $slt_custom_fields['boxes'] as $box ) {
		$common_types = array_intersect( $objects, (array) $box['type'] );
		if ( empty( $objects ) || ! empty( $common_types ) ) {
			foreach ( $box['fields'] as $field ) {
				if ( empty( $types ) || in_array( $field['type'], $types ) ) {
					if ( $add_prefix )
						$names[] = slt_cf_field_key( $field['name'], $box['type'] ) ;
					else
						$names[] = $field['name'];
				}
			}
		}
	}
	return $names;
}

/* Check field scope
***************************************************************************************/
function slt_cf_check_scope( $field, $request_type, $request_scope, $object_id ) {
	global $wp_roles;
	$scope_match = false;

	if ( ( in_array( $request_type, array( 'attachment', 'user' ) ) ) && empty( $field['scope'] ) ) {

		// Match all attachments and users if there's an empty scope
		$scope_match = true;

	} else {

		// Test for an explicit match
		foreach ( $field['scope'] as $scope_key => $scope_value ) {

			if ( is_string( $scope_key ) && $request_type == 'post' && $scope_key == 'template' ) {

				// Page template matching
				$custom_fields = get_post_custom_values( '_wp_page_template', $object_id );
				$page_template = $custom_fields[0];
				foreach ( (array) $scope_value as $scope_template ) {
					if ( $scope_template == $page_template ) {
						$scope_match = true;
						break;
					}
				}
				if ( $scope_match )
					break;

			} else if ( is_string( $scope_key ) && $request_type == 'post' && $scope_key == 'post_format' && get_post_type( $object_id ) == 'post' ) {

				// Post format matching matching
				foreach ( (array) $scope_value as $scope_format_name ) {
					if ( has_post_format( $scope_format_name, $object_id ) ) {
						$scope_match = true;
						break;
					}
				}
				if ( $scope_match )
					break;

			} else if ( is_string( $scope_key ) && $request_type == 'post' && in_array( $scope_key, get_object_taxonomies( $request_scope ) ) ) {

				// Taxonomic matching
				$object_terms = wp_get_object_terms( $object_id, $scope_key, array( 'fields' => 'names' ) );
				foreach ( (array) $scope_value as $scope_term_name ) {
					if ( in_array( $scope_term_name, $object_terms ) ) {
						$scope_match = true;
						break;
					}
				}
				if ( $scope_match )
					break;

			} else if ( $scope_value == 'registration' && $request_type == 'user' && $request_scope == 'registration' && in_array( get_option( 'default_role' ), $field['scope'] ) ) {

				// Registration
				$scope_match = true;
				break;

			} else if ( is_string( $scope_value ) && (
				( $request_type == 'post' && in_array( $scope_value, get_post_types() ) ) ||
				( $request_type == 'user' && array_key_exists( $scope_value, $wp_roles->role_names ) ) ||
				( $request_type == 'attachment' && array_search( $scope_value, get_allowed_mime_types() ) )
			)) {

				// Basic scope match, against post type, user role, or MIME type
				if ( $request_scope == $scope_value ) {
					$scope_match = true;
					break;
				}

			} else if ( in_array( $scope_key, array( 'users', 'posts', 'attachments' ) ) && is_array( $scope_value ) ) {

				// Match particular object IDs
				if ( $scope_key == $request_type . 's' && in_array( $object_id, $scope_value ) ) {
					$scope_match = true;
					break;
				}

			} else {

				// See if there are any matching custom scope checks
				$scope_match = apply_filters( 'slt_cf_check_scope', $scope_match, $request_type, $request_scope, $object_id, $scope_key, $scope_value, $field );
				if ( $scope_match )
					break;

			}
		}

	}

	// Any post exceptions
	if ( in_array( $request_type, array( 'post', 'attachment' ) ) && array_key_exists( 'except_posts', $field['scope'] ) && $scope_match && in_array( $object_id, $field['scope']['except_posts'] ) ) {
		$scope_match = false;
	}

	// Any user exceptions
	if ( $request_type == 'user' && array_key_exists( 'except_users', $field['scope'] ) && $scope_match && in_array( $object_id, $field['scope']['except_users'] ) ) {
		$scope_match = false;
	}

	return $scope_match;
}

/**
 * Get posts with ordering first by custom field
 *
 * @todo Make it work! Currently the second ordering wipes the first. Need to get the order parameters into the SQL.
 *
 * @param string $key The key of the custom field to order by first
 * @param array $query The WP_Query arguments, including secondary order parameters if desired
 * @param string $custom_order DESC | ASC
 * @param bool $numeric Is the field being ordered by numberic?
 * @param string $object_type post | user
 * @return array An array of post objects
 */
function slt_cf_get_posts_by_custom_first( $key, $query = array(), $custom_order = 'DESC', $numeric = false, $object_type = 'post' ) {
	// First store the second ordering values, following WP defaults
	global $slt_cf_second_orderby, $slt_cf_second_order;
	$slt_cf_second_orderby = array_key_exists( 'orderby', $query ) ? $query[ 'orderby' ] : 'post_date';
	$slt_cf_second_order = array_key_exists( 'order', $query ) ? $query[ 'order' ] : 'DESC';
	// Set query up
	$query[ 'meta_key' ] = slt_cf_field_key( $key, $object_type );
	$query[ 'orderby' ] = $numeric ? 'meta_value_num' : 'meta_value';
	$query[ 'order' ] = $custom_order;
	// Get posts ordered by custom field
	$result = new WP_Query( $query );
	// Now sort by second parameters
	usort( $result->posts, 'slt_cf_order_posts' );
	return $result->posts;
}

// Comparison function to order posts query
function slt_cf_order_posts( $a, $b ) {
	global $slt_cf_second_orderby, $slt_cf_second_order;
	if ( $a->$slt_cf_second_orderby == $b->$slt_cf_second_orderby )
		return 0;
	if ( strtolower( $slt_cf_second_order ) == 'DESC' ) {
		return ( $a->$slt_cf_second_orderby > $b->$slt_cf_second_orderby ) ? -1 : 1;
	} else {
		return ( $a->$slt_cf_second_orderby < $b->$slt_cf_second_orderby ) ? -1 : 1;
	}
}

/* Manage default object IDs
***************************************************************************************/
function slt_cf_default_id( $type = 'post', $id = 0 ) {
	global $post, $profileuser;
	if ( ! $id ) {
		switch ( $type ) {
			case 'post':
			case 'attachment': {
				// Post ID
				if ( is_object( $post ) && property_exists( $post, 'ID' ) )
					$id = $post->ID;
				break;
			}
			case 'user': {
				if ( is_admin() ) {
					// ID of user being edited in admin
					$id = $profileuser->ID;
				} else {
					// Front-end
					if ( is_author() ) {
						// Author archive page
						$user = null;
						if ( get_query_var( 'author_name' ) )
							$user = get_user_by( 'slug', get_query_var( 'author_name' ) );
						else if ( get_query_var( 'author' ) )
							$user = get_userdata( get_query_var( 'author' ) );
						if ( is_object( $user ) && property_exists( $user, 'ID' ) )
							$id = $user->ID;
					} else {
						// Try to get author of current post
						$id = $post->post_author;
					}
				}
				break;
			}
		}
	}
	return $id;
}

/* Remove default custom fields meta box from post-type screens
***************************************************************************************/
function slt_cf_remove_default_meta_box( $post_type, $context, $post ) {
	global $slt_custom_fields;
	if ( $slt_custom_fields['hide_default_custom_meta_box'] )
		remove_meta_box( 'postcustom', $post_type, $context );
}

/* Make a form allow file uploads
***************************************************************************************/
function slt_cf_file_upload_form() {
	echo ' enctype="multipart/form-data"';
}

/* Capability check
***************************************************************************************/
function slt_cf_capability_check( $field_type, $caps, $post_id = 0 ) {
	// Force check with 'upload_files' for file upload fields
	if ( $field_type == 'file' && ! current_user_can( 'upload_files' ) )
		return false;
	// Check against each capability
	foreach ( $caps as $cap ) {
		if ( ( $post_id && current_user_can( $cap, $post_id ) ) || current_user_can( $cap ) )
			return true;
	}
	return false;
}

/* Check for required parameters
***************************************************************************************/
function slt_cf_required_params( $required_params, $item_type, $item_data ) {
	$keep = true;
	foreach ( $required_params as $param ) {
		if ( ! array_key_exists( $param, $item_data ) ) {
			$keep = false;
			trigger_error( '<b>' . SLT_CF_TITLE . ':</b> A ' . ucfirst( $item_type ) . ' is missing its <b>' . $param . '</b>!', E_USER_WARNING );
		}
	}
	return $keep;
}

/* Check for parameter type
***************************************************************************************/
function slt_cf_params_type( $params, $type, $item_type, $item_data ) {
	$valid = false;
	foreach ( $params as $param ) {
		if (
			( $type == 'array' && is_array( $item_data[$param] ) ) ||
			( $type == 'boolean' && is_bool( $item_data[$param] ) ) ||
			( $type == 'string' && is_string( $item_data[$param] ) ) ||
			( $type == 'integer' && ( is_int( $item_data[$param] ) || ctype_digit( $item_data[$param] ) ) )
		) {
			$valid = true;
		} else {
			trigger_error( '<b>' . SLT_CF_TITLE . ':</b> The <b>\'' . ( array_key_exists( 'title', $item_data ) ? $item_data['title'] : $item_data['name'] ) . '\'</b> ' . ucfirst( $item_type ) . ' has a <b>' . $param . '</b> parameter that needs to be of the type: <b>' . $type . '</b>', E_USER_WARNING );
		}
	}
	return $valid;
}

/* Simple textile-style formatting codes
***************************************************************************************/
function slt_cf_simple_formatting( $content, $output = "html", $autop = true ) {
	if ( $output == "html" ) {
		$regexes = array(
			'%\*\*([^\*]+)\*\*%',
			'%\_\_([^\_]+)\_\_%',
			'%(")(.*?)(").*?((?:http|https)(?::\/{2}[\\w]+)(?:[\/|\\.]?)(?:[^\\s"]*))%'
		);
		$replacements = array(
			'<strong>$1</strong>',
			'<em>$1</em>',
			'<a href="$4">$2</a>'
		);
		$content = strip_tags( $content );
		$content = preg_replace( $regexes, $replacements, $content );
		if ( $autop )
			$content = wpautop( $content );
	} else {
		$regexes = array(
			'%<(/?)strong>%',
			'%<(/?)em>%',
			'%<a href="([^"]*)">([^<]*)</a>%'
		);
		$replacements = array(
			'**',
			'__',
			'"$2":$1'
		);
		$content = preg_replace( $regexes, $replacements, $content );
		if ( $autop )
			$content = slt_cf_reverse_wpautop( $content );
		$content = strip_tags( $content );
	}
	return $content;
}

/* A simple "reverse wpautop"
***************************************************************************************/
function slt_cf_reverse_wpautop( $content ) {
	return str_replace( array( '<p>', '</p>', '<br />' ), array( '', "\r\n", "\r" ), $content );
}

/* Abbreviate a string
***************************************************************************************/
function slt_cf_abbreviate( $string, $max_length = 50 ) {
	if ( strlen( $string ) > $max_length )
		$string = substr_replace( $string, "&hellip;", $max_length );
	return $string;
}

/* Reverse a date string
***************************************************************************************/
function slt_cf_reverse_date( $date_string, $sep = '/', $to_timestamp = false ) {
	$date_parts = explode( $sep, $date_string );
	// Using this function, it's assumed input is YYYY/MM/DD
	if ( $to_timestamp )
		$date = mktime( 12, 0, 0, $date_parts[1], $date_parts[2], $date_parts[0] );
	else
		$date = implode( $sep, array_reverse( $date_parts ) );
	return $date;
}

/* Google Map functions
***************************************************************************************/

if ( SLT_CF_USE_GMAPS ) :

// Output a map (for display or input)
function slt_cf_gmap( $type = 'output', $name = '', $values = 'stored_data', $width = 0, $height = 0, $location_markers = null, $map_type_id = '', $echo = true, $js_callback = '', $required = true, $object_type = 'post' ) {
	$output = '';
	$using_default_name = false;
	static $map_count = 1;

	// Enqueue scripts
	wp_enqueue_script( 'google-maps-api' );
	wp_enqueue_script( 'slt-cf-gmaps' );

	// Defaults
	if ( empty( $name ) ) {
		$name = 'gmap_' . $map_count;
		$using_default_name = true;
	} else if ( in_array( $object_type, array( 'post', 'user' ) ) && ( strlen( $name ) < slt_cf_prefix( $object_type ) || substr( $name, 0, strlen( slt_cf_prefix( $object_type ) ) ) != slt_cf_prefix( $object_type ) ) ) {
		$name = slt_cf_field_key( $name, $object_type );
	}
	$location_markers = (int) $location_markers; // This should handle pre-1.3 booleans
	if ( empty( $map_type_id ) ) {
		$map_type_id = 'roadmap';
	}

	// Values
	if ( $type == 'output' && $object_type != 'custom' && ( empty( $values ) || $values == 'stored_data' ) ) {
		// Try to initalize values from current meta
		if ( is_author() ) {
			global $author, $wpdb;
			$request_type = 'user';
			$user = (array) get_userdata( intval( $author ) );
			$roles = array_keys( $user[ $wpdb->prefix . 'capabilities' ] );
			$scope = array_shift( $roles );
			$object_id = $author;
		} else {
			global $post;
			$request_type = 'post';
			$scope = get_post_type();
			$object_id = get_the_ID();
		}
		// Initialize fields - this function checks to make sure it's only called once per request
		slt_cf_init_fields( $request_type, $scope, $object_id );
		// Get fields for current item
		$cf_item_fields = slt_cf_get_current_fields();
		// Make sure there's a map and decide which one to use
		$map_field = null;
		foreach ( $cf_item_fields as $cf_item_field ) {
			if ( $cf_item_field['type'] == 'gmap' && ( $using_default_name || slt_cf_field_key( $cf_item_field['name'] ) == $name ) ) {
				$map_field = $cf_item_field;
				break;
			}
		}
		// No map field?
		if ( ! $map_field ) {
			return;
		}
		// Pass any width and height
		if ( ! $width ) {
			$width = $map_field['width'];
		}
		if ( ! $height ) {
			$height = $map_field['height'];
		}
		// Use the found name, get values
		$name = slt_cf_field_key( $map_field['name'] );
		if ( ! $values = slt_cf_field_value( $map_field['name'] ) ) {
			$values = array();
		}

	} else {

		// If an input and there's no values, this must be a newly created item
		// Make sure $values is an empty array
		if ( $type == 'input' && ! is_array( $values ) ) {
			$values = array();
		}
		// Defaults if there's no field
		if ( empty( $width ) ) {
			$width = 500;
		}
		if ( empty( $height ) ) {
			$height = 300;
		}

	}

	// Check values is an array
	if ( ! is_array( $values ) ) {
		return false;
	}

	// Check if optional map is flagged to not display for output
	if ( $type == 'output' && array_key_exists( 'display', $values ) && ! $values["display"] ) {
		return false;
	}

	// Set values defaults
	$values_defaults = array(
		'centre_latlng'		=> '55.877704802038835,-4.523828125000029',
		'zoom'				=> 5,
	);
	if ( $type == 'input' ) {
		$values_defaults['bounds_sw'] = '49.78401952556854,-11.335351562500023';
		$values_defaults['bounds_ne'] = '61.14529476347399,2.2876953124999773';
	}
	$values = wp_parse_args( $values, $values_defaults );

	// Need to deal with markers separately to cater for 1.1 change to multiple markers
	if ( empty( $values['map_markers'] ) ) {
		if ( ! empty( $values['marker_latlng'] ) ) {
			// Old single value passed through
			$values[ 'map_markers' ] = array( $values[ 'marker_latlng' ] );
		} else {
			// New multiple values default
			$values[ 'map_markers' ] = array( '55.877704802038835,-4.523828125000029' );
		}
	}

	// Sanitize
	foreach ( $values as $key => $value ) {
		$values[ $key ] = str_replace( ' ', '', $value );
	}

	// Name might contain square brackets for PHP $_POST arrays - set the ID right
	$id = str_replace( array( '[', ']' ), array( '_', '' ), $name ) . '_map_container';

	// Stuff for inputs
	if ( $type == 'input' ) {

		// Required?
		if ( ! $required ) {
			$initial_display_value = isset( $values["display"] ) ? $values["display"] : '0';

			$output .= '<p>' . __( 'Use location map?', SLT_CF_TEXT_DOMAIN ) . ' <input class="gmap_toggle_display yes" type="radio" name="' . $name . '[display]" id="' . $id . '_display_yes" value="1"' . checked( $initial_display_value, '1', false ) . ' /> <label for="' . $id . '_toggle_display_yes">' . __( 'Yes', SLT_CF_TEXT_DOMAIN ) . '</label> <input class="gmap_toggle_display no" type="radio" name="' . $name . '[display]" id="' . $id . '_display_no" value="0"' . checked( $initial_display_value, '0', false ) . ' /> <label for="' . $id . '_display_no">' . __( 'No', SLT_CF_TEXT_DOMAIN ) . '</label></p>';

			// Wrapper
			$output .= '<div id="' . $id . '_wrapper" style="width:' . esc_attr( $width ) . 'px;';
			if ( ! $initial_display_value ) {
				$output .= 'display:none;';
			}
			$output .= '">' . "\n";

		}

		// Geocoder and clicking instructions
		// Currently included via JS
		//$output .= '<p class="gmap-address"><small>Click on the map to add a marker. Click a marker to remove it. Click and drag a marker to change its location.<br><br></small><label for="' . $id . '_address">' . __( 'Find an address', SLT_CF_TEXT_DOMAIN ) . ':</label> <input type="text" id="' . $id . '_address" name="' . $id . '_address" value="" class="regular-text" /></p>';

	}

	// Map container
	$output .= '<style type="text/css">.gmap_output img {max-width:none;}</style><div id="' . $id . '" class="gmap_' . $type . '" style="width:' . esc_attr( $width ) . 'px;height:' . esc_attr( $height ) . 'px;"></div>' . "\n";

	// Hidden fields?
	if ( $type == 'input' ) {
		foreach ( $values as $key => $value ) {
			if ( $key != 'display' ) {
				$output .= '<input type="hidden" id="' . esc_attr( $id . "_" . $key ) .'" name="' . esc_attr( $name . "[" . $key . "]" ) .'" value="';
				if ( $key == 'map_markers' ) {
					// Multiple map markers output as pipe-delimited string here
					$output .= esc_attr( implode( "|", $value ) );
				} else {
					$output .= esc_attr( $value );
				}
				$output .= '" />' . "\n";
			}
		}
	}

	// JavaScript
	$inline_script = "jQuery( document ).ready( function($) {\n";
	// Output multiple markers array as JS array
	$inline_script .= "slt_cf_gmap_init( '{$id}', '{$type}', {$location_markers}, [ '" . implode( "','", $values['map_markers'] ) . "' ], '{$values['centre_latlng']}', {$values['zoom']}, '{$map_type_id}'";
	// Callback?
	if ( $js_callback ) {
		$inline_script .= ", '{$js_callback}'";
	}
	$inline_script .= " );\n";
	$inline_script .= "});\n";
	slt_cf_collect_dynamic_inline_footer_scripts( $inline_script );

	// Close wrapper?
	if ( $type == 'input' && ! $required ) {
		$output .= '</div>' . "\n";
	}

	$map_count++;
	// Output?
	if ( $echo ) {
		echo $output;
	} else {
		return $output;
	}
}

/**
 * Helper function to collect Gmaps init scripts for output in footer,
 * in case jQuery is in footer
 *
 * @since	0.8
 *
 * @param	string	$script									The inline script
 * @global	array	$slt_cf_dynamic_inline_footer_scripts
 * @return	void
 */
function slt_cf_collect_dynamic_inline_footer_scripts( $script ) {
	global $slt_cf_dynamic_inline_footer_scripts;
	if ( ! is_array( $slt_cf_dynamic_inline_footer_scripts ) )
		$slt_cf_dynamic_inline_footer_scripts = array();
	$slt_cf_dynamic_inline_footer_scripts[] = $script;
}

/**
 * Helper function to output Gmaps init scripts in footer
 *
 * @since	0.8
 *
 * @global	array	$slt_cf_dynamic_inline_footer_scripts
 * @return	void
 */
if ( is_admin() )
	add_action( 'admin_print_footer_scripts', 'slt_cf_output_dynamic_inline_footer_scripts', 99999, 1 );
else
	add_action( 'wp_footer', 'slt_cf_output_dynamic_inline_footer_scripts', 99999, 1 );
function slt_cf_output_dynamic_inline_footer_scripts( $script ) {
	global $slt_cf_dynamic_inline_footer_scripts;
	if ( is_array( $slt_cf_dynamic_inline_footer_scripts ) && ! empty( $slt_cf_dynamic_inline_footer_scripts ) ) {
		echo '<script type="text/javascript">' . "\n";
		foreach ( $slt_cf_dynamic_inline_footer_scripts as $script ) {
			echo $script;
		}
		echo '</script>' . "\n";
	}
}

endif;

// Map shortcode
add_shortcode( 'slt-cf-gmap', 'slt_cf_gmap_shortcode' );
function slt_cf_gmap_shortcode( $atts ) {
	if ( ! SLT_CF_USE_GMAPS )
		return '';
	// Initialize from attributes
	extract( shortcode_atts( array(
		'width'				=> 0,
		'height'			=> 0,
		'name'				=> ''
	), $atts ));
	// Return a map
	return slt_cf_gmap( 'output', $name, 'stored_data', $width, $height, null, '', false );
}


/* File Select button functions
***************************************************************************************/

if ( SLT_CF_USE_FILE_SELECT ) :

/**
 * Output file select button
 *
 * @since 0.6
 *
 * @param string	$name			A name for the input tag.
 * @param int		$value			The current value for the field (a media attachment ID).
 * @param string	$label			A label for the field.
 * @param string	$preview_size	The size (from available WordPress image sizes) for the preview, if an image.
 * @param bool		$removable		Should it be possible to remove the file?
 * @param bool		$attach_to_post	When uploaded, should the file be attached to the current post (if this button is on a post edit screen)?
 * @return void
 */
function slt_cf_file_select_button( $name, $value = 0, $label = 'Select file', $preview_size = 'thumbnail', $removable = true, $attach_to_post = true ) { ?>
	<div>
		<input type="button" id="<?php echo esc_attr( $name ); ?>_button" class="button-secondary slt-cf-fs-button" value="<?php echo esc_attr( $label ); ?>" />
		<?php if ( $value && $removable ) { ?>
			&nbsp;&nbsp;<input type="checkbox" name="<?php echo esc_attr( $name ); ?>_remove" value="1" class="slt-cf-fs-remove" /> <label for="<?php echo esc_attr( $name ); ?>_remove"><?php _e( 'Remove', SLT_CF_TEXT_DOMAIN ); ?></label>
		<?php } ?>
		<input type="hidden" value="<?php echo $attach_to_post ? '1' : '0'; ?>" name="<?php echo esc_attr( $name ); ?>_attach_to_post" id="<?php echo esc_attr( $name ); ?>_attach_to_post" class="slt-cf-fs-attach-to-post" />
		<input type="hidden" value="<?php echo esc_attr( $value ); ?>" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" class="slt-cf-fs-value" />
		<input type="hidden" value="<?php echo esc_attr( $preview_size ); ?>" name="<?php echo esc_attr( $name ); ?>_preview-size" id="<?php echo esc_attr( $name ); ?>_preview-size" class="slt-fs-preview-size" />
	</div>
	<div class="slt-fs-preview" id="<?php echo esc_attr( $name ); ?>_preview" style="margin-top:7px"><?php
		if ( $value ) {
			if ( wp_attachment_is_image( $value ) ) {
				// Show image preview
				echo wp_get_attachment_image( $value, $preview_size );
			} else {
				// File link
				echo slt_cf_file_select_link( $value );
			}
		}
	?></div>
<?php }

// Generate markup for file link
function slt_cf_file_select_link( $id ) {
	$attachment_url = wp_get_attachment_url( $id );
	$filetype_check = wp_check_filetype( $attachment_url );
	$filetype_parts = explode( '/', $filetype_check['type'] );
	$icon_files = glob( plugin_dir_path( __FILE__ ) . 'img/icon-*.png' );
	$filetype = 'unknown';
	foreach ( $icon_files as $icon_file ) {
		if ( basename( $icon_file ) == 'icon-' . $filetype_parts[1] . '.png' ) {
			$filetype = $filetype_parts[1];
			break;
		}
	}
	return '<a href="' . wp_get_attachment_url( $id ) . '" style="display: block; min-height:32px; padding: 10px 0 0 38px; background: url(' . plugins_url( "img/icon-" . $filetype . ".png", __FILE__ ) . ') no-repeat; font-size: 13px; font-weight: bold;">' . basename( $attachment_url ) . '</a>';
}

// AJAX wrapper to get image HTML
add_action( 'wp_ajax_slt_cf_fs_get_file', 'slt_cf_file_select_get_file_ajax' );
function slt_cf_file_select_get_file_ajax() {
	if ( wp_attachment_is_image( $_REQUEST['id'] ) ) {
		echo wp_get_attachment_image( $_REQUEST['id'], $_REQUEST['size'] );
	} else {
		echo slt_cf_file_select_link( $_REQUEST['id'] );
	}
	die();
}

endif;

/* Options pre-sets
 ***************************************************************************************/
function slt_cf_options_preset( $preset ) {
	$options = array();

	switch ( $preset ) {

		case 'countries': {
			$countries = array(
				'Abu Dhabi',
				'Afghanistan',
				'Ajman',
				'Albania',
				'Algeria',
				'Andorra',
				'Angola',
				'Anguilla',
				'Antigua and Barbuda',
				'Argentina',
				'Armenia',
				'Aruba',
				'Ascension',
				'Australia',
				'Austria',
				'Azerbaijan',
				'Azores',
				'Bahamas',
				'Bahrain',
				'Balearic Islands',
				'Bangladesh',
				'Barbados',
				'Belarus',
				'Belgium',
				'Belize',
				'Benin',
				'Bermuda',
				'Bhutan',
				'Bolivia',
				'Bosnia-Herzegovina',
				'Botswana',
				'Brazil',
				'British Indian Ocean',
				'Brunei Darussalam',
				'Bulgaria',
				'Burkina Faso',
				'Burma',
				'Burundi',
				'Cambodia',
				'Cameroon',
				'Canada',
				'Canary Islands',
				'Cape Verde',
				'Cayman Islands',
				'Central African Republic',
				'Chad',
				'Chile',
				'China',
				'Christmas Island',
				'Cocos (Keeling) Island',
				'Colombia',
				'Comoros',
				'Congo',
				'Corsica',
				'Costa Rica',
				'Cote d\'Ivoire',
				'Croatia',
				'Cuba',
				'Curacao',
				'Cyprus',
				'Czech Republic',
				'Denmark',
				'Djibouti',
				'Dominica',
				'Dominican Republic',
				'Dubai',
				'East Timor',
				'Ecuador',
				'Egypt',
				'El Salvador',
				'Equatorial Guinea',
				'Eritrea',
				'Estonia',
				'Ethiopia',
				'Falkland Islands',
				'Faroe Islands',
				'Fiji',
				'Finland',
				'France',
				'French Guiana',
				'French Polynesia',
				'French Southern and Antarctic Territories',
				'French West Indies',
				'Fujairah',
				'Gabon',
				'Gambia',
				'Gaza and KhanYunis',
				'Georgia',
				'Germany',
				'Ghana',
				'Gibraltar',
				'Greece',
				'Greenland',
				'Grenada',
				'Guam',
				'Guatemala',
				'Guinea',
				'Guinea-Bissau',
				'Guyana',
				'Haiti',
				'Honduras',
				'Hong Kong',
				'Hungary',
				'Iceland',
				'India',
				'Indonesia',
				'Iran',
				'Iraq',
				'Ireland',
				'Israel',
				'Italy',
				'Jamaica',
				'Japan',
				'Jordan',
				'Kazakhstan',
				'Kenya',
				'Kirghizstan',
				'Kiribati',
				'North Korea',
				'South Korea',
				'Kuwait',
				'Laos',
				'Latvia',
				'Lebanon',
				'Lesotho',
				'Liberia',
				'Libya',
				'Liechtenstein',
				'Lithuania',
				'Luxembourg',
				'Macao',
				'Macedonia',
				'Madagascar',
				'Madeira',
				'Malawi',
				'Malaysia',
				'Maldives',
				'Mali',
				'Malta',
				'Marshall Islands',
				'Mauritania',
				'Mauritius',
				'Mexico',
				'Micronesia',
				'Moldova',
				'Monaco',
				'Mongolia',
				'Montenegro',
				'Montserrat',
				'Morocco',
				'Mozambique',
				'Namibia',
				'Nauru Island',
				'Nepal',
				'Netherland Antilles',
				'Netherlands',
				'New Caledonia',
				'New Zealand',
				'Nicaragua',
				'Niger Republic',
				'Nigeria',
				'Norfolk Island',
				'Northern Mariana Island',
				'Norway',
				'Oman',
				'Pakistan',
				'Palau',
				'Panama',
				'Papua New Guinea',
				'Paraguay',
				'Peru',
				'Philippines',
				'Pitcairn Island',
				'Poland',
				'Portugal',
				'Puerto Rico',
				'Qatar',
				'Ras al Khaimah',
				'Reunion',
				'Romania',
				'Russia',
				'Rwanda',
				'Sabah',
				'American Samoa',
				'Western Samoa',
				'San Marino',
				'Sao Tome and Principe',
				'Sarawak',
				'Saudi Arabia',
				'Senegal',
				'Serbia',
				'Seychelles',
				'Sharjah',
				'Sierra Leone',
				'Singapore',
				'Slovakia',
				'Slovenia',
				'Solomon Islands',
				'Somalia',
				'South Africa',
				'South Georgia',
				'South Sandwich Islands',
				'Spain',
				'Spanish North African Territories',
				'Spitzbergen',
				'Sri Lanka',
				'Saint Kitts and Nevis',
				'St Helena',
				'St Lucia',
				'St Pierre and Miquelon',
				'St Vincent and Grenadines',
				'Sudan',
				'Suriname',
				'Swaziland',
				'Sweden',
				'Switzerland',
				'Syria',
				'Taiwan',
				'Tajikistan',
				'Tanzania',
				'Thailand',
				'Tibet',
				'Togo',
				'Tonga',
				'Trinidad and Tobago',
				'Tristan da Cunha',
				'Tunisia',
				'Turkey',
				'Turkmenistan',
				'Turks and Caicos Islands',
				'Tuvalu',
				'Uganda',
				'Ukraine',
				'Umm al Qaiwan',
				'United Arab Emirates',
				'United Kingdom',
				'Uruguay',
				'USA',
				'Uzbekistan',
				'Vanuatu',
				'Vatican City',
				'Venezuela',
				'Vietnam',
				'British Virgin Islands',
				'United States Virgin Islands',
				'Wake Island',
				'Wallis and Futuna Islands',
				'Yemen',
				'Zambia',
				'Zimbabwe'
			);
			// Countries as both keys and values
			$options = array_combine( $countries, $countries );
			break;
		}

	}

	return $options;
}


/* Sort queries by post type
Used when multiple post types are being queried and they need to be grouped in the output
To be hooked to pre_get_posts
 ***************************************************************************************/

function slt_cf_sort_queries_by_post_type( $query ) {
	if ( strpos( $query->query_vars['orderby'], 'post_type' ) === false ) {
		$query->set( 'orderby', 'post_type ' . $query->query_vars['orderby'] );
	}
}
