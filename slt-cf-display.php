<?php

/* Display
***************************************************************************************/

// Add meta boxes to post screen
function slt_cf_add_meta_boxes( $post_type ) {
	global $slt_custom_fields;
	foreach ( $slt_custom_fields['boxes'] as $box_key => $box ) {
		add_meta_box(
			slt_cf_prefix( 'post' ) . $box['id'],
			$box['title'],
			'slt_cf_display_box',
			$post_type,
			$box['context'],
			$box['priority'],
			array( 'box' => $box_key )
		);
	}
}

// Inline scripting for moving boxes above content
add_action( 'admin_head-post.php', 'slt_cf_move_metaboxes' );
add_action( 'admin_head-post-new.php', 'slt_cf_move_metaboxes' );
function slt_cf_move_metaboxes() {
	global $slt_custom_fields;
	$output = array();
	foreach ( $slt_custom_fields['boxes'] as $box_key => $box ) {
		if ( array_key_exists( 'above_content', $box ) && $box['above_content'] )
			$output[] = 'slt_cf_metaboxes_above_content.push( "' . slt_cf_prefix( 'post' ) . $box['id'] . '" );';
	}
	if ( ! empty( $output ) ) { ?>
		<script type="text/javascript">//<![CDATA[
			var slt_cf_metaboxes_above_content = [];
			<?php foreach ( $output as $output_line ) { echo $output_line; ?>
			<?php } ?>
			//]]></script>
	<?php }
}

// Output user profile sections
function slt_cf_add_user_profile_sections( $user ) {
	global $slt_custom_fields;
	foreach ( $slt_custom_fields['boxes'] as $box_key => $box )
		slt_cf_display_box( $user, $box_key, 'user' );
}

/**
 * Display a box's fields
 *
 * @since		0.1
 * @param		object	$object
 * @param		array	$custom_data
 * @param		string	$request_type	'post' | 'user'; defaults to 'post' for both 'post' and 'attachment' requests
 * @return		void
 */
function slt_cf_display_box( $object, $custom_data, $request_type = 'post' ) {
	global $slt_custom_fields;
	static $date_output = false;
	static $time_output = false;
	static $datetime_output = false;

	// Initialize
	switch ( $request_type ) {
		case 'post': {
			// Get the key of the box we're in from the args
			$box_key = $custom_data['args']['box'];
			// Nonce for security
			wp_nonce_field( slt_cf_prefix( 'post' ) . $slt_custom_fields['boxes'][$box_key]['id'] . '_save', $slt_custom_fields['prefix'] . $slt_custom_fields['boxes'][$box_key]['id'] . '_wpnonce', false, true );
			// Description
			if ( $slt_custom_fields['boxes'][ $box_key ][ 'description' ] )
				echo '<p>' . $slt_custom_fields['boxes'][ $box_key ][ 'description' ] . '</p>';
			break;
		}
		case 'user': {
			// Get box key and output initial markup
			$box_key = $custom_data;
			echo '<h3>' . $slt_custom_fields['boxes'][ $box_key ]['title']. '</h3>';
			// Description
			if ( $slt_custom_fields['boxes'][ $box_key ][ 'description' ] )
				echo '<p>' . $slt_custom_fields['boxes'][ $box_key ][ 'description' ] . '</p>';
			echo '<table class="form-table">';
			break;
		}
	}

	// Loop through fields for this box
	foreach ( $slt_custom_fields['boxes'][ $box_key ]['fields'] as $field ) {
		$field_name = slt_cf_prefix( $slt_custom_fields['boxes'][ $box_key ]['type'] ) . $field['name'];

		if ( isset( $_POST[ $field_name ] ) ) {

			// Pass through from submitted form (with errors)
			$field_value = $_POST[ $field_name ];

		} else if (	( $request_type == 'post' && $object->post_status == 'auto-draft' ) ||
			( $request_type == 'user' && ! is_object( $object ) ) ||
			( is_object( $object ) && ! slt_cf_field_exists( $field['name'], $request_type, $object->ID ) )
		) {

			// Field doesn't exist yet, use a default if set
			$object_id = is_object( $object ) ? $object->ID : null;
			$field_value = apply_filters( 'slt_cf_default_value', $field['default'], $request_type, $object_id, $object, $field );

		} else {

			// Get field value
			$field_value = slt_cf_field_value( $field['name'], $request_type, $object->ID, '', '', false, $field['single'] );

		}

		// Reverse autop?
		if ( $field['autop'] ) {
			$field_value = slt_cf_reverse_wpautop( $field_value );
		}

		// Set defaults for styles and classes
		$field_classes = array( 'slt-cf', 'slt-cf-' . $field['type'], 'slt-cf-field_' . $field['name'] );
		$label_classes = array( 'slt-cf-label' );
		$input_classes = array( 'slt-cf-input' );
		$input_styles = array();
		$legend_classes = array();
		if ( in_array( $field['type'], array( 'checkboxes', 'radio' ) ) ) {
			$multi_field_classes = array( 'slt-cf-multifield' );
			$multi_field_styles = array();
			if ( $field['width'] > 0 ) {
				$multi_field_classes[] = 'slt-cf-fixed-width';
				$multi_field_styles[] = 'width:' . $field['width'] . 'em';
			}
			if ( $field['sortable'] ) {
				$multi_field_classes[] = 'ui-state-default';
			}
			if ( $field['checkboxes_thumbnail'] ) {
				$field_classes[] = 'thumbnails';
			}
		} else {
			if ( $field['width'] && $field['type'] != 'wysiwyg' )
				$input_styles[] = 'width:' . $field['width'] . 'em';
			if ( $field['label_layout'] == 'block' )
				$field_classes[] = 'label-block';
		}
		if ( $field['height'] && $field['type'] != 'wysiwyg' )
			$input_styles[] = 'height:' . $field['height'] . 'em';

		// This will hide the label / legend
		if ( $request_type == 'post' && $field['hide_label'] ) {
			$label_classes[] = 'screen-reader-text';
			$legend_classes[] = 'screen-reader-text';
		}

		// Markup to wrap field
		switch ( $request_type ) {
			case 'post': {
				echo '<div class="' . implode( " ", $field_classes ) . '">';
				$before_label = '';
				$after_label = '';
				$before_input = '';
				$after_input = '';
				break;
			}
			case 'user': {
				echo '<tr class="' . implode( " ", $field_classes ) . '">';
				$before_label = '<th>';
				$after_label = '</th>';
				$before_input = '<td>';
				$after_input = '</td>';
				break;
			}
		}

		// Color preview allowed in certain field types
		if ( $field['color_preview'] && in_array( $field['type'], array( 'text', 'select', 'radio' ) ) && ! ( $field['type'] == 'select' && $field['multiple'] ) ) {
			$after_input = '<div class="slt-cf-color-preview" id="slt-cf-color-preview_' . $field_name . '"></div>' . $after_input;
		}

		// Cloning
		// [under development]
		/*if ( $field['cloning'] ) {
			$after_input = '<p class="slt-cf-clone-field"><a href="#" class="button">' . __( "Clone field", 'slt-custom-fields' ) . '</a></p>' . $after_input;
		}*/

		// Description
		$field_description = '';
		if ( $field['type'] == 'textile' ) {
			$field_description .= '<p class="description textile">' . __( 'You can apply the following simple formatting codes: <b>**bold**</b></span>&nbsp;&nbsp;<i>__italic__</i>&nbsp;&nbsp;&quot;Link text&quot;:http://domain.com', 'slt-custom-fields' ) . '</p>';
		} else if ( ! current_user_can( 'unfiltered_html' ) ) {
			// If user can't submit any HTML, display any that is allowed
			if ( isset( $field['allowed_html'] ) && is_array( $field['allowed_html'] ) && count( $field['allowed_html'] ) ) {
				$field_description .= '<p class="description html">' . __( "You can use the following HTML tags:", 'slt-custom-fields' );
				// Switcheroo to use the WP allowed_tags function
				global $allowedtags;
				$temp = $allowedtags;
				$allowedtags = $field['allowed_html'];
				$field_description .= '<code>' . allowed_tags() . '</code></p>';
				$allowedtags = $temp;
			} else if ( isset( $field['allowtags'] ) && is_array( $field['allowtags'] ) && count( $field['allowtags'] ) ) {
				// Deprecated
				$field_description .= '<p class="description html">' . __( "You can use the following HTML tags:", 'slt-custom-fields' );
				foreach ( $field['allowtags'] as $tag )
					$field_description .= '<code>&lt;' . $tag . '&gt;</code> ';
				$field_description .= '</p>';
			}
		}
		if ( isset( $field['autop'] ) && $field['autop'] )
			$field_description .= '<p class="description autop">' . __( "Line and paragraph breaks will be maintained.", 'slt-custom-fields' ) . '</p>';
		if ( $field['description'] )
			$field_description .= '<p class="description"><i>' . $field['description'] . '</i></p>';

		// Tab index
		$tabindex = null;
		if ( is_numeric( $field['tabindex'] ) || $field['tabindex'] == '-1' ) {
			$tabindex = $field['tabindex'];
		} else if ( is_string( $field['tabindex'] ) && function_exists( $field['tabindex'] ) ) {
			$tabindex = call_user_func( $field['tabindex'] );
		}

		// Which type of field?
		switch ( $field['type'] ) {

			case "checkbox": {
				/* Single checkbox
				*****************************************************************/
				// Input
				$input = $before_input . '<input type="checkbox" name="' . $field_name . '" id="' . $field_name . '" value="1"';
				if ( $field_value )
					$input .= ' checked="checked"';
				$input .=  ' />';
				if ( $request_type == 'user' )
					$input .= $field_description;
				$input .= $after_input;
				// Label
				$label = $before_label . ' <label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				if ( $request_type == 'post' ) {
					echo $input;
					echo $label;
					echo $field_description;
				} else if ( $request_type == 'user' ) {
					echo $label;
					echo $input;
				}
				break;
			}

			case "checkboxes": {
				/* Multiple checkboxes
				*****************************************************************/
				if ( $request_type == 'post' ) {
					echo $before_input;
					echo '<fieldset>';
					echo '<legend class="' . implode( ' ', $legend_classes ) . '">' . $field['label'] . '</legend>';
				} else {
					echo $before_label . $field['label'] . $after_label;
					echo $before_input;
				}
				// Loop through options
				// No options?
				if ( empty( $field['options'] ) ) {
					echo '<p><em>' . $field['no_options'] . '</em></p>';
					echo '<input type="hidden" name="' . $field_name . '" value="" />';
				} else {
					// Special management of options here for sortable
					// The $field['options'] keys are text, so need to apply ordering "manually"
					$cb_tag = 'div';
					$sortable_options = array();
					if ( $field['sortable'] && $current_order = slt_cf_field_values_order( $field['name'], $request_type ) ) {
						// Add already ordered items in order
						$current_order_values = explode( ',', $current_order );
						foreach ( $current_order_values as $value ) {
							if ( ( $target_key = array_search( $value, $field['options'] ) ) !== false ) {
								$sortable_options[] = array( $target_key, $value );
							}
						}
						//echo '<pre>'; print_r( $sortable_options ); echo '</pre>';
						//echo '<pre>'; print_r( $field['options'] ); echo '</pre>'; exit;
						// Append any new unordered items
						foreach ( $field['options'] as $key => $value ) {
							if ( ! in_array( $value, $current_order_values ) ) {
								$sortable_options[] = array( $key, $value );
							}
						}
					} else {
						// Just copy default order through into sortable-friendly format
						foreach ( $field['options'] as $key => $value ) {
							$sortable_options[] = array( $key, $value );
						}
					}
					if ( $field['sortable'] ) {
						echo '<p><em>' . __( 'Drag-and-drop to sort the order of these items.', 'slt-custom-fields' ) . '</em></p>';
						echo '<ul class="slt-cf-sortable">';
						$cb_tag = 'li';
					}
					foreach ( $sortable_options as $option ) {
						$key = $option[0];
						$value = $option[1];
						echo '<' . $cb_tag . ' class="' . implode( ' ', $multi_field_classes ) . '" style="' . implode( ';', $multi_field_styles ) . '">';
						if ( $field['sortable'] ) {
							echo '<span class="ui-icon ui-icon-arrowthick-2-n-s"></span>';
						}
						// Thumbnail
						if ( $field['checkboxes_thumbnail'] && $field['options_query']['post_type'] == 'attachment' && $field['options_query']['post_mime_type'] == 'image' ) {
							$checkbox_thumbnail =  wp_get_attachment_image_src( $value, apply_filters( 'slt_cf_checkboxes_thumbnail_size', 'thumbnail', $field ) );
							echo '<img src="' . $checkbox_thumbnail[0] . '" alt="' . get_the_title( $value ) . ' thumbnail"> ';
						}
						// Input
						echo '<input type="checkbox" name="' . $field_name . '_' . $value . '" id="' . $field_name . '_' . $value . '" value="yes"';
						if ( ( is_array( $field_value ) && in_array( $value, $field_value ) ) || ( $field['sortable'] && $field['default'] === 'force-all' ) ) {
							echo ' checked="checked"';
							if ( $field['sortable'] && $field['default'] === 'force-all' ) {
								echo ' style="visibility:hidden;"';
							}
						}
						echo ' />';
						// Label
						echo ' <label for="' . $field_name .'_' . $value . '">' . $key . '</label>';

						echo '</' . $cb_tag . '>';
					}
					if ( $field['sortable'] ) {
						echo '</ul>';
						// This hidden field stores the order automatically
						echo '<input class="slt-cf-sortable-order" type="hidden" name="' . $field_name . '_order" id="' . $field_name . '_order" value="" />';
					}
				}
				if ( $request_type == 'post' )
					echo '</fieldset>';
				echo $field_description;
				echo $after_input;
				break;
			}

			case "radio": {
				/* Radio buttons
				*****************************************************************/
				if ( $request_type == 'post' ) {
					echo '<fieldset>';
					echo '<legend class="' . implode( ' ', $legend_classes ) . '">' . $field['label'] . '</legend>';
				} else {
					echo $before_label . $field['label'] . $after_label;
				}
				// Loop through options
				echo $before_input;
				// No options?
				if ( empty( $field['options'] ) ) {
					echo '<p><em>' . $field['no_options'] . '</em></p>';
					echo '<input type="hidden" name="' . $field_name . '" value="" />';
				} else {
					foreach ( $field['options'] as $key => $value ) {
						echo '<div class="' . implode( ' ', $multi_field_classes ) . '" style="' . implode( ';', $multi_field_styles ) . '">';
						// Input
						echo '<input type="radio" name="' . $field_name . '" id="' . $field_name . '_' . $value . '" value="' . $value . '"';
						if ( $field_value == $value )
							echo ' checked="checked"';
						echo ' />';
						// Label
						echo ' <label for="' . $field_name .'_' . $value . '">' . $key . '</label>';
						echo '</div>';
					}
				}
				if ( $request_type == 'post' )
					echo '</fieldset>';
				echo $field_description;
				echo $after_input;
				break;
			}

			case "textarea":
			case "textile":
			case "wysiwyg": {
				/* Text area / textile / WYSIWYG
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				echo $before_input;
				if ( $field['type'] != 'wysiwyg' ) {
					if ( $request_type == 'user' && ! is_object( $object ) ) {
						// Proper styling for registration form
						$input_classes[] = 'input';
					}
					echo '<textarea name="' . $field_name . '" id="' . $field_name . '" columns="50" rows="5" style="' . implode( ';', $input_styles ) . '" class="' . implode( ' ', $input_classes ) . '"';
					// Character counter JS
					if ( $field['type'] != "wysiwyg" && isset( $field['charcounter'] ) && $field['charcounter'] ) echo ' onkeyup="document.getElementById(\'' . $field_name . '-charcounter\').value=this.value.length;"';
					// Value
					if ( $field['type'] == "textile" )
						$field_value = slt_cf_simple_formatting( $field_value, "textile", $field['autop'] );
					echo '>' . htmlspecialchars( $field_value ) . '</textarea>';
					// Character counter
					if ( $field['type'] != "wysiwyg" && isset( $field['charcounter'] ) && $field['charcounter'] )
						echo '<p>' . __( "Characters so far:", 'slt-custom-fields' ) . ' <input type="text" id="' . $field_name . '-charcounter" disabled="disabled" style="width:4em;color:#000;" value="' . strlen( $field_value ) . '" /></p>';
				}
				// WYSIWYG
				if ( $field['type'] == 'wysiwyg' ) {
					wp_editor( $field_value, $field_name, $field['wysiwyg_settings'] );
				}
				echo $field_description;
				echo $after_input;
				break;
			}

			case "select": {
				/* Select dropdown
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				echo $before_input;
				slt_cf_input_select( $field_name, $field_value, $field['input_prefix'], $field['input_suffix'], $input_styles, $input_classes, true, $field['options'], $field['multiple'], ( $field['options_type'] != 'static' && ! $field['required'] ), $field['empty_option_text'], $field['no_options'] );
				echo $field_description;
				echo $after_input;
				break;
			}

			case 'file': {
				/* File upload field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				echo $before_input;
				slt_cf_file_select_button( $field_name, $field_value, $field['file_button_label'], $field['preview_size'], $field['file_removeable'], $field['file_attach_to_post'] );
				echo $field_description;
				echo $after_input;
				break;
			}

			case 'gmap': {
				/* Google Map field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				echo $before_input;
				slt_cf_gmap( 'input', $field_name, $field_value, $field['width'], $field['height'], $field['location_marker'], $field['gmap_type'], true, '', $field['required'] );
				echo $field_description;
				echo $after_input;
				break;
			}

			case 'date': {
				/* Date field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				$input_classes[] = 'slt-cf-date';
				echo $before_input;
				echo '<input type="text" name="' . $field_name . '" id="' . $field_name . '" value="' . htmlspecialchars( $field_value ) . '" style="' . implode( ';', $input_styles ) . '" class="' . implode( ' ', $input_classes ) . '" />';
				echo ' <i>' . str_replace( "y", "yy", $field['datepicker_format'] ) . '</i>';
				if ( ! $date_output ) {
					?>
					<script type="text/javascript">
						jQuery( document ).ready( function($) {
							$( 'input.slt-cf-date' ).datepicker({
								dateFormat: '<?php echo $field['datepicker_format']; ?>'
							});
						});
					</script>
					<?php
					$date_output = true;
				}
				echo $field_description;
				echo $after_input;
				break;
			}

			case 'time': {
				/* Time field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				$input_classes[] = 'slt-cf-time';
				echo $before_input;
				echo '<input type="text" name="' . $field_name . '" id="' . $field_name . '" value="' . htmlspecialchars( $field_value ) . '" style="' . implode( ';', $input_styles ) . '" class="' . implode( ' ', $input_classes ) . '" />';
				echo ' <i>' . $field['timepicker_format'] . '</i>';
				if ( ! $time_output ) {
					?>
					<script type="text/javascript">
						jQuery( document ).ready( function($) {
							$( 'input.slt-cf-time' ).timepicker({
								timeFormat: '<?php echo $field['timepicker_format']; ?>',
								ampm: '<?php echo $field['timepicker_ampm']; ?>'
							});
						});
					</script>
					<?php
					$time_output = true;
				}
				echo $field_description;
				echo $after_input;
				break;
			}

			case 'datetime': {
				/* Date and time field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				$input_classes[] = 'slt-cf-datetime';
				echo $before_input;
				echo '<input type="text" name="' . $field_name . '" id="' . $field_name . '" value="' . htmlspecialchars( $field_value ) . '" style="' . implode( ';', $input_styles ) . '" class="' . implode( ' ', $input_classes ) . '" />';
				echo ' <i>' . str_replace( "y", "yy", $field['datepicker_format'] ) . ' ' . $field['timepicker_format'] . '</i>';
				if ( ! $datetime_output ) {
					?>
					<script type="text/javascript">
						jQuery( document ).ready( function($) {
							$( 'input.slt-cf-datetime' ).datetimepicker({
								dateFormat: '<?php echo $field['datepicker_format']; ?>',
								timeFormat: '<?php echo $field['timepicker_format']; ?>',
								ampm: '<?php echo $field['timepicker_ampm']; ?>'
							});
						});
					</script>
					<?php
					$datetime_output = true;
				}
				echo $field_description;
				echo $after_input;
				break;
			}

			case 'notice': {
				/* Notice - no form field
				*****************************************************************/
				echo $before_label . '<h4 class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</h4>' . $after_label;
				echo $before_input . $field_description . $after_input;
				break;
			}

			case 'colorpicker': {
				/* Color picker field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				$input_classes[] = 'slt-cf-colorpicker';
				echo $before_input;
				slt_cf_input_text( $field_name, $field_value, $field['input_prefix'] . ' #', $field['input_suffix'], $input_styles, $input_classes );
				echo $field_description;
				echo $after_input;
				break;
			}

			case 'attachments_list': {
				/* Attachments list field
				*****************************************************************/
				global $_wp_additional_image_sizes;
				echo $before_input;
				echo '<fieldset>';
				echo '<legend class="' . implode( ' ', $legend_classes ) . '">' . $field['label'] . '</legend>';
				// List
				if ( $field['attachments_list'] ) {
					echo '<ul class="slt-cf-attachments-list slt-cf-cf">';
					foreach ( $field['attachments_list'] as $attachment ) {
						$mime_type = explode( '/', $attachment->post_mime_type );
						$mime_class = ( $mime_type[0] == 'image' ) ? 'image' : 'file';
						echo '<li class="slt-cf-' . $mime_class . '">';
						if ( $field['attachments_list_options']['unattach_checkboxes'] ) {
							echo '<label for="' . $field_name . '_' . $attachment->ID . '">';
						}
						if ( $mime_class == 'image' ) {
							// An image
							$image_infos = wp_get_attachment_image_src( $attachment->ID, $field['attachments_list_options']['image_display_size'] );
							echo '<img class="slt-cf-attachment" src="' . $image_infos[0] . '" alt="">';
						} else {
							// A file
							if ( in_array( $field['attachments_list_options']['image_display_size'], $_wp_additional_image_sizes ) ) {
								// An intermediate size
								$attachment_width = $_wp_additional_image_sizes[ $field['attachments_list_options']['image_display_size'] ]['width'];
								$attachment_height = $_wp_additional_image_sizes[ $field['attachments_list_options']['image_display_size'] ]['height'];
							} else {
								// A standard size
								$attachment_width = get_option( $field['attachments_list_options']['image_display_size'] . '_size_w' );
								$attachment_height = get_option( $field['attachments_list_options']['image_display_size'] . '_size_h' );
							}
							// Decide on icon
							$icon_class = "unknown";
							switch ( $mime_type[0] ) {
								case 'text': {
									$icon_class = "txt";
									break;
								}
								case 'application': {
									switch ( $mime_type[1] ) {
										case 'pdf': {
											$icon_class = "pdf";
											break;
										}
										case 'msword':
										case 'vnd.openxmlformats-officedocument.wordprocessingml.document':
										case 'vnd.oasis.opendocument.text': {
											$icon_class = "doc";
											break;
										}
									}
									break;
								}
							}
							// Output
							echo '<div class="slt-cf-attachment file" style="background: #fff url(' . plugins_url( "img/icon-" . $icon_class . ".png", __FILE__ ) . ') no-repeat center 15px;';
							if ( $attachment_width ) {
								echo 'width:' . $attachment_width . 'px;';
							}
							if ( $attachment_height ) {
								echo 'height:' . $attachment_height . 'px;';
							}
							echo '">';
							echo '<p>' . apply_filters( 'the_title', $attachment->post_title ) . '</p>';
							echo '</div>';
						}
						if ( $field['attachments_list_options']['unattach_checkboxes'] ) {
							echo '<input type="checkbox" name="' . $field_name . '_' . $attachment->ID . '" id="' . $field_name . '_' . $attachment->ID . '" value="yes" /> Check to unattach</label>';
						}
						echo '</li>';
					}
					echo '</ul>';
				} else {
					echo '<p><em>No attachments to list.</em></p>';
				}
				echo '</fieldset>';
				echo $field_description;
				echo $after_input;
				break;
			}

			default: {
				/* Plain text field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				$input_classes[] = 'regular-text';
				echo $before_input;
				slt_cf_input_text( $field_name, $field_value, $field['input_prefix'], $field['input_suffix'], $input_styles, $input_classes, true, $tabindex );
				echo $field_description;
				echo $after_input;
				break;
			}

		} // End switch

		// Markup to wrap field
		switch ( $request_type ) {
			case 'post': {
				echo '</div>';
				break;
			}
			case 'user': {
				echo '</tr>';
				break;
			}
		}

	} // Fields foreach

	// Round off any markup
	if ( $request_type == 'user')
		echo '</table>';

}


/* Field output functions
***************************************************************************/

// Plain text
function slt_cf_input_text( $field_name, $field_value = '', $prefix = '', $suffix = '', $input_styles = array(), $input_classes = array(), $echo = true, $tabindex = null ) {
	$output = '';
	if ( $prefix ) {
		$output .= $prefix . ' ';
	}
	$output .= '<input type="text" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" value="' . esc_html( $field_value ) . '" style="' . esc_attr( implode( ';', $input_styles ) ) . '" class="' . esc_attr( implode( ' ', $input_classes ) ) . '"';
	if ( is_numeric( $tabindex ) || $tabindex == '-1' ) {
		$output .= ' tabindex="' . $tabindex . '"';
	}
	$output .= ' />';
	if ( $suffix ) {
		$output .= ' ' . $suffix;
	}
	if ( $echo ) {
		echo $output;
	} else {
		return $output;
	}
}

// Select
function slt_cf_input_select( $field_name, $field_value, $prefix = '', $suffix = '', $input_styles = array(), $input_classes = array(), $echo = true, $options, $multiple = false, $empty_option = false, $empty_option_text = '', $no_options = '' ) {
	$output = '';

	// No options?
	if ( empty( $options ) ) {
		if ( ! $no_options )
			$no_options = SLT_CF_NO_OPTIONS;
		$output .= '<p><em>' . $no_options . '</em></p>';
		$output .= '<input type="hidden" name="' . esc_attr( $field_name ) . '" value="" />';
	} else {
		if ( $multiple ) {
			$input_styles[] = 'height:auto';
		} else if ( $prefix ) {
			$output .= $prefix . ' ';
		}
		$output .= '<select name="' . esc_attr( $field_name );
		if ( $multiple )
			$output .= '[]';
		$output .= '" id="' . esc_attr( $field_name ) . '" style="' . esc_attr( implode( ';', $input_styles ) ) . '" class="' . esc_attr( implode( ' ', $input_classes ) ) . '"';
		if ( $multiple ) {
			$size = ( count( $options ) < 15 ) ? count( $options ) : 15;
			$output .= ' multiple="multiple" size="' . esc_attr( $size ) . '"';
		}
		$output .= '>';
		// Handle option groups
		$opt_groups = false;
		// Empty option?
		if ( $empty_option && ! $multiple ) {
			if ( ! $empty_option_text )
				$empty_option_text = '[' . __( "None", "slt-custom-fields" ) . ']';
			$output .= '<option value=""';
			if ( $field_value === null || $field_value === '' )
				$output .= ' selected="selected"';
			$output .= '>' . esc_html( $empty_option_text ) . '</option>';
		}
		// Loop through options
		foreach ( $options as $key => $value ) {
			if ( $value === '[optgroup]' ) {
				if ( $opt_groups )
					$output .= '</optgroup>';
				$output .= '<optgroup label="' . esc_attr( $key ) . '">';
				$opt_groups = true;
			} else {
				$output .= '<option value="' . esc_attr( $value ) . '"';
				if ( ( is_array( $field_value ) && in_array( $value, $field_value ) ) || $field_value == $value )
					$output .= ' selected="selected"';
				$output .= '>' . esc_html( $key ) . '</option>';
			}
		}
		if ( $opt_groups )
			$output .= '</optgroup>';
		$output .= '</select>';
		if ( ! $multiple && $suffix )
			$output .= ' ' . $suffix;
	}
	if ( $echo )
		echo $output;
	else
		return $output;
}


/* Post meta output
***************************************************************************/

/**
 * Display all post meta
 *
 * @since		0.8.2
 * @return		void
 */
function slt_cf_postmeta_output() {

	// Get meta
	$pm = get_post_custom();
	ksort( $pm );
	//echo '<pre>'; print_r( $pm ); echo '</pre>'; return;

	// Output
	echo '<table border="0" cellspacing="0" cellpadding="5">';
	echo '<tr><th scope="col" align="left">Key</th><th scope="col" align="left">Value</th></tr>';
	foreach ( $pm as $key => $values ) {
		echo '<tr>';
		echo '<th scope="row" align="left" valign="top">' . $key . '</th>';
		echo '<td align="left" valign="top">';
		foreach ( $values as $value ) {
			echo '<div>';
			if ( strlen( $value ) ) {
				if ( is_serialized( $value ) ) {
					echo '<pre>'; print_r( unserialize( $value ) ); echo '</pre>';
				} else {
					echo esc_html( maybe_unserialize( $value ) );
				}
			} else {
				echo '<i style="color:#999">[null]</i>';
			}
			echo '</div>';
		}
		echo '</td>';
		echo '</tr>';
	}
	echo '</table>';

}