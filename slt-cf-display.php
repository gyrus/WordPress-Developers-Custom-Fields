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

/* Add fields to an attachment screen
* No boxes / sections, just loop through all fields
***************************************************************************/
function slt_cf_add_attachment_fields( $form_fields, $post ) {
	global $slt_custom_fields;
	foreach ( $slt_custom_fields['boxes'] as $box_key => $box ) {
		foreach ( $box['fields'] as $field ) {
			// Only certain fields types allowed for now
			if ( ! in_array( $field['type'], array( 'text', 'select' ) ) )
				continue;
			// Add into form fields array
			$field_name = slt_cf_prefix( 'attachment' ) . $field['name'];
			$form_fields[ $field_name ] = array();
			$form_fields[ $field_name ]['label'] = $field['label'];
			$form_fields[ $field_name ]['value'] = slt_cf_field_value( $field['name'], 'attachment', $post->ID, '', '', false, $field['single'] );
			$form_fields[ $field_name ]['input'] = 'html';
			$input_field_name = "attachments[{$post->ID}][{$field_name}]";
			switch ( $field['type'] ) {
				case 'select': {
					$form_fields[ $field_name ]['html'] = slt_cf_input_select( $input_field_name, $form_fields[ $field_name ]['value'], $field['input_prefix'], $field['input_suffix'], array(), array(), false, $field['options'], $field['multiple'], ( $field['options_type'] != 'static' && ! $field['required'] ), $field['empty_option_text'], $field['no_options'] );
					break;
				}
				case 'text': {
					$form_fields[ $field_name ]['html'] = slt_cf_input_text( $input_field_name, $form_fields[ $field_name ]['value'], $field['prefix'], $field['suffix'], array(), array(), false );
					break;
				}
			}
			if ( $field['description'] )
				$form_fields[ $field_name ]['helps'] = $field['description'];
		}
	}
	return $form_fields;
}

/* Display a box's fields
***************************************************************************/
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

		// Skip fields not allowed in this scope
		if ( $field['type'] == 'file' && $request_type == 'user' )
			continue;

		if ( ( $request_type == 'post' && $object->post_status == 'auto-draft' ) || ! slt_cf_field_exists( $field['name'], $request_type, $object->ID ) ) {
			// Field doesn't exist yet, use a default if set
			$field_value = $field['default'];
		} else {
			// Get field value
			$field_value = slt_cf_field_value( $field['name'], $request_type, $object->ID, '', '', false, $field['single'] );
		}

		// Reverse autop?
		if ( $field['autop'] )
			$field_value = slt_cf_reverse_wpautop( $field_value );

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
						echo '<input type="checkbox" name="' . $field_name . '_' . $value . '" id="' . $field_name . '_' . $value . '" value="yes"';
						if ( is_array( $field_value ) && in_array( $value, $field_value )  )
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
				// Make sure textarea isn't output for WYSIWYG for 3.3 and above, wp_editor handles that
				if ( $field['type'] != 'wysiwyg' || ! SLT_CF_WP_IS_GTE_3_3 ) {
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
					if ( SLT_CF_WP_IS_GTE_3_3 ) {
						// For 3.3 and above - simple :)
						wp_editor( $field_value, $field_name, $field['wysiwyg_settings'] );
					} else {
						// For versions below 3.3
						?>
						<script type="text/javascript">
							jQuery( document ).ready( function() {
								jQuery( "<?php echo $field_name; ?>" ).addClass( 'mceEditor' );
								if ( typeof( tinyMCE ) == 'object' && typeof( tinyMCE.execCommand ) == 'function' ) {
									tinyMCE.execCommand( 'mceAddControl', false, '<?php echo $field_name; ?>' );
								}
							});
						</script>
						<?php
					}
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

			default: {
				/* Plain text field
				*****************************************************************/
				// Label
				echo $before_label . '<label for="' . $field_name .'" class="' . implode( ' ', $label_classes ) . '">' . $field['label'] . '</label>' . $after_label;
				// Input
				$input_classes[] = 'regular-text';
				echo $before_input;
				slt_cf_input_text( $field_name, $field_value, $field['input_prefix'], $field['input_suffix'], $input_styles, $input_classes );
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
function slt_cf_input_text( $field_name, $field_value = '', $prefix = '', $suffix = '', $input_styles = array(), $input_classes = array(), $echo = true ) {
	$output = '';
	if ( $prefix )
		$output .= $prefix . ' ';
	$output .= '<input type="text" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_name ) . '" value="' . esc_html( $field_value ) . '" style="' . esc_attr( implode( ';', $input_styles ) ) . '" class="' . esc_attr( implode( ' ', $input_classes ) ) . '" />';
	if ( $suffix )
		$output .= ' ' . $suffix;
	if ( $echo )
		echo $output;
	else
		return $output;
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


