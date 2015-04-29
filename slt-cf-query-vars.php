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

					// Get current meta query
					$current_meta_query = is_array( $query->get( 'meta_query' ) ) ? $query->get( 'meta_query' ) : array();
					$new_clauses = array();

					// Set up if this is the Simple Events date field
					if ( defined( 'SLT_SE_EVENT_DATE_FIELD' ) && $key == SLT_SE_EVENT_DATE_FIELD && defined( 'SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT' ) && SLT_SE_EVENT_DATE_QUERY_VAR_FORMAT ) {

						// Decide on the from / to boundaries of the range being filtered for
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

						// If Simple Events version doesn't have end date, or we don't have WP 4.1+, simple test
						if ( ! defined( 'SLT_SE_EVENT_END_DATE_FIELD' ) || version_compare( get_bloginfo( 'version' ), '4.1', '<' ) ) {

							$new_clauses[] = array(
								'key'		=> slt_cf_field_key( $key ),
								'value'		=> array(
									$from_date . ' 00:00',
									$to_date . ' 23:59'
								),
								'compare'	=> 'BETWEEN',
							);

						// With end date involved, more complex tests
						} else {

							// Start date is in range
							// OR end date is in range
							// OR start date is before range AND end date is after range
							$new_clauses[] = array(
								'relation'		=> 'OR',
								array(
									'key'		=> slt_cf_field_key( SLT_SE_EVENT_DATE_FIELD ),
									'value'		=> array(
										$from_date . ' 00:00',
										$to_date . ' 23:59'
									),
									'compare'	=> 'BETWEEN',
								),
								array(
									'key'		=> slt_cf_field_key( SLT_SE_EVENT_END_DATE_FIELD ),
									'value'		=> array(
										$from_date . ' 00:00',
										$to_date . ' 23:59'
									),
									'compare'	=> 'BETWEEN',
								),
								array(
									'relation'		=> 'AND',
									array(
										'key'		=> slt_cf_field_key( SLT_SE_EVENT_DATE_FIELD ),
										'value'		=> $from_date . ' 00:00',
										'compare'	=> '<',
									),
									array(
										'key'		=> slt_cf_field_key( SLT_SE_EVENT_END_DATE_FIELD ),
										'value'		=> $to_date . ' 23:59',
										'compare'	=> '>',
									),
								),
							);

						}


					} else {

						// Simple pass-through
						$new_clauses[] = array(
							'key'		=> slt_cf_field_key( $key ),
							'value'		=> $value,
							'compare'	=> is_array( $value ) ? 'IN' : '=',
						);

					}

					// Add clauses to meta_query
					$query->set( 'meta_query', array_merge( $current_meta_query, $new_clauses ) );

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
