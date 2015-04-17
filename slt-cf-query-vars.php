<?php

/* Handle query vars
***************************************************************************************/


add_filter( 'query_vars', 'slt_cf_query_vars' );
/**
 * Add any query var fields
 *
 * @since	1.1
 */
function slt_cf_query_vars( $query_vars ) {
	global $slt_custom_fields;

	// Go through all fields and add query vars accordingly
	foreach ( $slt_custom_fields['boxes'] as $box_key => $box ) {
		foreach ( $box['fields'] as $field_key => $field ) {
			if ( ! empty( $field['make_query_var'] ) ) {
				if ( ! in_array( $field['name'], $query_vars ) ) {
					$query_vars[] = $field['name'];
					$slt_custom_fields['query_vars'][] = $field['name'];
				}
			}
		}
	}

	return $query_vars;
}


add_action( 'parse_query', 'slt_cf_manage_query_string' );
/**
 * Manage query string, to add query vars if requested by 'dcf_use_query_string' flag
 *
 * Query string vars are only recognised normally in the main loop query.
 *
 * @since	1.1
 */
function slt_cf_manage_query_string( $query ) {
	global $wp, $slt_custom_fields;

	// Front-end and flag set?
	if ( ! is_admin() && $query->get( 'dcf_use_query_string' ) ) {

		// Get custom taxonomies in case we need to deal with them
		$custom_taxonomies = get_taxonomies( array( '_builtin' => false ) );

		// Go through the query vars already parsed by the main request, and add in
		foreach ( $wp->query_vars as $key => $value ) {

			// Ignore vars without a value
			if ( ! empty( $value ) ) {

				// Check if it's a custom field query var
				if ( in_array( $key, $slt_custom_fields['query_vars'] ) ) {

					// Set up if this is the Simple Events date field
					$compare = '=';
					if ( defined( 'SLT_SE_EVENT_DATE_FIELD' ) && $key == SLT_SE_EVENT_DATE_FIELD && defined( 'SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT' ) && SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT ) {
						$compare = 'BETWEEN';
						switch ( SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT ) {
							case 'Y': {
								$from_date = $value . '/01/01';
								$to_date = $value . '/12/31';
								break;
							}
							case 'mY': {
								$month = ( strlen( $value ) > 1 ) ? substr( $value, 0, 2 ) : '01';
								$year = ( strlen( $value ) > 5 ) ? substr( $value, 2, 4 ) : date( 'Y' );
								$from_date = $year . '/' . $month . '/01';
								$to_date = $year . '/' . $month . '/' . str_pad( cal_days_in_month( CAL_GREGORIAN, $month, $year ), 2, '0', STR_PAD_LEFT );
								break;
							}
						}
						$value = array(
							$from_date . ' 00:00',
							$to_date . ' 23:59'
						);
					}

					// Add to meta_query
					$current_meta_query = is_array( $query->get( 'meta_query' ) ) ? $query->get( 'meta_query' ) : array();
					$query->set( 'meta_query', array_merge( $current_meta_query, array( array(
						'key'		=> slt_cf_field_key( $key ),
						'value'		=> $value,
						'compare'	=> $compare,
					))));

				// Handle taxonomies?
				} else if ( ! $query->get( 'dcf_custom_field_query_vars_only' ) ) {

					// Also deal with non-custom field query vars
					if ( in_array( $key, $custom_taxonomies ) ) {

						// Add to tax_query
						$query->set( 'tax_query', array_merge( $query->get( 'tax_query' ), array( array(
							'taxonomy'	=> $key,
							'terms'		=> $value
						))));

					}

				}

			}

		}

	}

}
