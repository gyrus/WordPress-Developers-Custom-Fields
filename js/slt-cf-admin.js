/* SLT Custom Fields JavaScript
*********************************************************************/

jQuery( document ).ready( function($) {
	var	cp = $( '.slt-cf-color-preview' ),
		cpick = $( 'input.slt-cf-colorpicker' ),
		s = $( '.slt-cf-sortable' ),
		n = $( '.slt-cf-notice' ),
		i, box;

	/* Move meta boxes above content editor
	*****************************************************************/
	if ( typeof slt_cf_metaboxes_above_content != "undefined" ) {
		for ( i in slt_cf_metaboxes_above_content ) {
			box = $( 'div#' + slt_cf_metaboxes_above_content[ i ] ).detach();
			box.css( 'margin', '20px 0 0' ).insertAfter( '#titlediv' );
		}
	}

	/* Color preview
	 *****************************************************************/
	if ( cp.length ) {
		cp.each( function() {
			var $this = $( this );
			var name = $this.attr( 'id' ).replace( 'slt-cf-color-preview_', '' );
			slt_cf_color_preview_update( name );
			$( '[name=' + name + ']' ).on( 'change', function() {
				slt_cf_color_preview_update( $( this ).attr( 'name' ) );
			});
		});
	}

	/* Color picker
	 *****************************************************************/
	if ( cpick.length && $().ColorPicker ) {
		//console.log( slt_cf_colorpicker.options );
		cpick.ColorPicker({
			onSubmit: function( hsb, hex, rgb, el ) {
				$( el ).val( hex );
				$( el ).ColorPickerHide();
			},
			onBeforeShow: function () {
				$( this ).ColorPickerSetColor( this.value );
			}
		}).bind( 'keyup', function(){
			$( this ).ColorPickerSetColor( this.value );
		});
	}

	/* Sortable
	 *****************************************************************/
	if ( s.length ) {
		// Store initial order
		s.each( function() {
			slt_cf_store_sortable_order( $( this ) );
		});
		// Initialize sortability
		s.sortable({
			stop: function( e, ui ) {
				// When sorting stops, store order
				slt_cf_store_sortable_order( ui.item.parents( '.slt-cf-sortable' ) );
			}
		});
	}

	/* Handling notices
	*****************************************************************/
	if ( n.length ) {
		n.on( 'click', '.slt-cf-dismiss', function( e ) {
			e.preventDefault();
			var el = $( this );
			var notice = el.attr( 'href' ).replace( '?slt-cf-dismiss=', '' );
			$.post(
				slt_custom_fields.ajaxurl,
				{
					'action': 'slt_cf_notice_dismiss',
					'notice': notice,
					'notice-dismiss-nonce': slt_custom_fields[ 'notice_nonce_' + notice ]
				},
				function( data ) {
					if ( data == 'updated' ) {
						$( 'a#slt-cf-dismiss_alert-07-cleanup' ).parents( 'div#message' ).fadeOut( 600, function() { $( this ).remove(); } );
					} else {
						alert( slt_custom_fields.update_option_fail );
					}
				}
			);
		});
	}


	/* Cloning
	****************************************************************
	if ( $( "p.slt-cf-clone-field a" ).length ) {
		$( 'p.slt-cf-clone-field a' ).click( function() {

			// Initialize
			var masterFieldDiv, fieldName, fieldNameParts, fieldClassPrefix, lastCloneFieldName, newField, newFieldNumber, newFieldName;
			fieldClassPrefix = 'slt-cf-field_';
			masterFieldDiv = $( this ).parents( '.slt-cf' );
			fieldName = getValueFromClass( masterFieldDiv, fieldClassPrefix );

			// Store the field name for what is currently the last clone
			lastCloneFieldName = getValueFromClass( $( '.' + fieldClassPrefix + fieldName ).last(), fieldClassPrefix );

			// Clone the master field, placing the clone at the end of all clones
			$( '.' + fieldClassPrefix + fieldName ).last().after( masterFieldDiv.clone( true ) );

			// Get a handle for it and hide it straight away
			newField = $( '.' + fieldClassPrefix + fieldName ).last();
			newField.hide();

			// Determine new number / ID
			fieldNameParts = lastCloneFieldName.split( '_' );
			newFieldNumber = ( fieldNameParts.length > 1 ) ? ( fieldNameParts[1] + 1 ) : 2;
			newFieldName = fieldNameParts[0] + '_' + newFieldNumber;

			// Remove / add classes
			newField.removeClass( fieldClassPrefix + fieldName ).addClass( 'clone ' + fieldClassPrefix + newFieldName );

			// Adjust label and input ????
			newField.children( 'label' ).attr( 'for', newFieldName ).append( ' #' + newFieldNumber );

			// Remove unnecessary elements
			newField.children( '.description, p.slt-cf-clone-field' ).remove();

			// Reveal new field
			newField.slideDown();

			return false;
		});
	}

	// Get a value from a class
	function getValueFromClass( obj, prefix ) {
		var classes, value, i;
		classes = value = "";
		classes = obj.attr( "class" );
		classes = classes.split( " " );
		for ( i = 0; i < classes.length; i++ ) {
			if ( classes[i].length > prefix.length && classes[i].substr( 0, prefix.length ) == prefix ) {
				value = classes[i].substr( prefix.length );
				break;
			}
		}
		return value;
	}*/

});


/**
 * Store the order of sortable checkboxes
 *
 * @since	0.8.4
 * @param	object	el	The parent sortable element
 * @return	void
 */
function slt_cf_store_sortable_order( el ) {
	var o = [];
	el.children( '.slt-cf-multifield' ).each( function() {
		var n = jQuery( this ).find( 'input' ).attr( 'name' ).split( '_' );
		o.push( n.pop() );
	});
	el.siblings( 'input.slt-cf-sortable-order' ).val( o.join( ',' ) );
}


/**
 * Update a color preview
 *
 * @since	0.8.3
 * @param	object	n	The name of the field with the preview
 * @return	void
 */
function slt_cf_color_preview_update( n ) { jQuery( function($) {
	var val;
	var f = $( '[name=' + n + ']' );
	var cp = $( '#slt-cf-color-preview_' + n );
	if ( f.is( 'select' ) ) {
		// Select drop-down
		val = $( 'option:selected', f ).val();
		if ( ! val ) {
			val = $( 'option:first', f ).val();
		}
	} else if ( f.length > 1 && $( f[0] ).is( 'input' ) && $( f[0] ).attr( 'type' ) == 'radio' ) {
		// Radio
		val = $( '[name=' + n + ']:checked' ).val();
	} else {
		// Default
		val = f.val();
	}
	if ( typeof val != 'undefined' && /^[0-9a-f]{3,6}$/i.test( val ) ) {
		cp.css( 'background-color', '#' + val ).html( '&nbsp' );
	} else {
		cp.css( 'background-color', 'none' ).html( '?' );
	}
}); }