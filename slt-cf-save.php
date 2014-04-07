<?php

/* Save custom field values
***************************************************************************************/

/**
 * Save custom field values
 *
 * @since	0.1
 * @param	string	$request_type	'post' | 'attachment' | 'user' (corresponds to $type in slt_cf_register_box)
 * @param	integer	$object_id		ID of object being edited
 * @param	object	$object			The object being edited
 * @param	array	$extras			Any extras, e.g. $attachment passed by attachment_fields_to_save filter
 * @return	mixed
 */
function slt_cf_save( $request_type, $object_id, $object, $extras = array() ) {
	global $slt_custom_fields, $wpdb;

	// Metadata type
	if ( $request_type == 'attachment' )
		$metadata_type = 'post';
	else
		$metadata_type = $request_type;

	// Loop through boxes
	foreach ( $slt_custom_fields['boxes'] as $box ) {

		// Check meta box nonce
		$nonce_prefix = '';
		if ( $request_type == 'post' || ( SLT_CF_WP_IS_GTE_3_5 && $request_type == 'attachment' ) )
			$nonce_prefix = slt_cf_prefix( $request_type ) . $box['id'];

		if ( ! $nonce_prefix || ( isset( $_POST[ $nonce_prefix . '_wpnonce' ] ) && wp_verify_nonce( $_POST[ $nonce_prefix . '_wpnonce' ], $nonce_prefix . '_save' ) ) ) {

			// Loop through fields
			foreach ( $box['fields'] as $field ) {
				// Skip notices
				if ( $field['type'] == 'notice' )
					continue;

				// Initialize
				$field_name = slt_cf_prefix( $request_type ) . $field['name'];

				// Process the submitted value
				$value = null;
				$update = true;

				if ( $field['type'] == 'attachments_list' ) {

					/* Attachments list - no actual custom field, just unattach if necessary
					*************************************************************/
					if ( $field['attachments_list_options']['unattach_checkboxes'] ) {

						// Gather items to unattach
						$unattach_items = array();
						foreach ( $_POST as $post_field => $post_value ) {
							$post_field_name = explode( '_', $post_field );
							$item_id = array_pop( $post_field_name );
							if ( implode( '_', $post_field_name ) == $field_name ) {
								$unattach_items[] = $item_id;
							}
						}

						// Unattach any passed
						if ( $unattach_items ) {
							$wpdb->query( $wpdb->prepare("
								UPDATE	$wpdb->posts
								SET		post_parent		= 0
								WHERE	ID				IN ( " . implode( ',', $unattach_items ) . " )
							"));
						}

					}

					continue;

				} else if ( $field['type'] == 'checkboxes' ) {

					/* Multiple checkboxes - gather values into array
					*************************************************************/
					$value = array();
					foreach ( $field['options'] as $opt_key => $opt_value ) {
						if ( isset( $_POST[ $field_name . '_' . $opt_value ] ) )
							$value[] = $opt_value;
					}

					// Store order for sortable
					if ( $field['sortable'] && isset( $_POST[ $field_name . '_order' ] ) ) {
						update_metadata( $metadata_type, $object_id, $field_name . '_order', $_POST[ $field_name . '_order' ] );
					}

				} else if ( $field['type'] == 'checkbox' ) {

					/* Single checkbox - set value to 1 or 0
					*************************************************************/
					$value = ( isset( $_POST[ $field_name ] ) ? '1' : '0' );

				} else if ( isset( $_POST[ $field_name ] ) || ( $request_type == 'attachment' && isset( $_POST['attachments'][$object_id][$field_name] ) ) ) {

					/* Other field types
					*************************************************************/
					if ( isset( $_POST['attachments'][$object_id][$field_name] ) )
						$value =  $_POST['attachments'][$object_id][$field_name];
					else
						$value = $_POST[ $field_name ];

					// Deal with string inputs
					if ( in_array( $field['type'], array( 'text', 'textarea', 'textile', 'wysiwyg' ) ) ) {

						// Basic trim
						$value = trim( $value );

						if ( $field['type'] == "textile" ) {

							// Textile: strip all tags, then format
							$value = wp_kses( $value, array() );
							$value = slt_cf_simple_formatting( $value, 'html', $field['autop'] );

						} else if ( ! current_user_can( 'unfiltered_html' ) ) {

							// For users that can't submit unfiltered HTML...

							// Are there any tags defined as being allowed?
							if ( count( $field['allowed_html'] ) ) {
								// Strip all tags except those allowed
								$value = wp_kses( $value, $field['allowed_html'] );
							} else if ( count( $field['allowtags'] ) ) {
								// Deprecated
								$value = strip_tags( $value, '<' . implode( '><', $field['allowtags'] ) . '>' );
							} else if ( in_array( $field['type'], array( 'text', 'textarea' ) ) ) {
								// If no tags are allowed, for text and textarea, strip all HTML
								$value = wp_kses( $value, array() );
							} else if ( $field['type'] == 'wysiwyg' ) {
								// WYSIWYG: default to allow standard post tags
								$value = wp_kses_post( $value );
							}

						}

						// Auto-paragraphs for WYSIWYG and other fields with autop set
						if ( $field['type'] == 'wysiwyg' || $field['autop'] )
							$value = wpautop( $value );

					}

				} // Field type if

				// Save meta entry
				if ( $update ) {

					// Apply filters to value first
					$value = apply_filters( 'slt_cf_pre_save_value', $value, $request_type, $object_id, $object, $field );

					// Run save actions
					do_action( 'slt_cf_pre_save', $value, $request_type, $object_id, $object, $field );

					// Separate fields?
					if ( ! $field['single'] && ( $field['type'] == 'checkboxes' || ( $field['type'] == 'select' && $field['multiple'] ) ) ) {
						// Remove all old values
						delete_metadata( $request_type, $object_id, $field_name );
						// Add each new value separately, if there are values
						if ( $value ) {
							foreach ( $value as $value_item )
								add_metadata( $metadata_type, $object_id, $field_name, $value_item, false );
						}
					} else if ( $value === '' ) {
						// Delete field if it exists (and don't create it if it doesn't!)
						delete_metadata( $metadata_type, $object_id, $field_name );
					} else {
						// Update single field
						update_metadata( $metadata_type, $object_id, $field_name, $value );
					}

				}

			} // Fields foreach

		} // Nonce check if

	} // Boxes foreach

	// Return $post for attachments pre-3.5 (it's a filter, not an action!)
	if ( $request_type == 'attachment' && ! SLT_CF_WP_IS_GTE_3_5 )
		return $object;

}

