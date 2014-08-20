/* File Select script for SLT Custom Fields */
var select_button, parent_src_vars;
select_button = '<a href="#" class="slt-cf-fs-insert button-secondary">' + slt_cf_file_select.text_select_file + '</a>';

jQuery( document ).ready( function( $ ) {
	var fsb = $( '.slt-cf-fs-button' );

	// Actions for screens with the file select button

	// Invoke Media Library interface on button click
	fsb.on( 'click', function( e ) {
		e.preventDefault();
		var el = $( this );
		var upload_url;
		$( 'html' ).addClass( 'File' );
		upload_url = 'media-upload.php?slt_cf_fs_field=' + el.siblings( 'input.slt-cf-fs-value' ).attr( 'id' ).trim() + '&type=file';
		if ( el.siblings( 'input.slt-cf-fs-attach-to-post' ).attr( 'value' ) == '1' && el.parents( 'form' ).find( 'input[name=post_ID]' ).length ) {
			upload_url += '&post_id=' + el.parents( 'form' ).find( 'input[name=post_ID]' ).attr( 'value' );
		}
		upload_url += '&TB_iframe=true';
		tb_show( '', upload_url );
	});

	// Wipe form values when remove checkboxes are checked
	fsb.filter( ':first' ).parents( 'form' ).on( 'submit', function() {
		$( '.slt-cf-fs-remove:checked' ).each( function() {
			$( this ).siblings( 'input.slt-cf-fs-value' ).val( '' );
		});
	});

	// Actions for the Media Library overlay
	if ( slt_fs_media_overlay() ) {
		var ulsm = $( 'ul#sidemenu' );
		var current_tab = ulsm.find( 'a.current' ).parent( 'li' ).attr( 'id' );

		// Remove URL tab
		$( 'li#tab-type_url', ulsm ).remove();
		// Remove 'Save all changes' button
		$( 'p.ml-submit' ).remove();
		switch ( current_tab ) {
			case 'tab-type': {
				// File upload - works for non-dynamic upload interfaces
				slt_fs_media_item_interface();
				break;
			}
			case 'tab-gallery':
			case 'tab-library': {
				// Gallery / Media Library
				$( '#sort-buttons > span,th.order-head,#media-items .media-item div.menu_order,#media-items .media-item a.toggle, #gallery-settings' ).remove();
				$( '.media-item', '#media-items' ).each( function() {
					$( this ).prepend( select_button );
				});
				$( 'a.slt-cf-fs-insert' ).css({
					'display':	'block',
					'float':	'right',
					'margin':	'7px 20px 0 0'
				});
				break;
			}
		}

		// Select functionality
		$( 'body' ).on( 'click', 'a.slt-cf-fs-insert', function( e ) {
			e.preventDefault();
			var el = $( this );
			var item_id;
			if ( el.parent().attr( 'class' ) == 'savesend' ) {
				// For a freshly uploaded media item
				item_id = el.siblings( '.del-attachment' ).attr( 'id' );
				item_id = item_id.match( /del_attachment_([0-9]+)/ );
				item_id = item_id[1];
			} else {
				// For media items in a list
				item_id = el.parent().attr( 'id' );
				item_id = item_id.match( /media\-item\-([0-9]+)/ );
				item_id = item_id[1];
			}
			// Pass item ID to function to pass it into hidden field
			parent.slt_cf_fs_select_item( item_id, parent_src_vars['slt_cf_fs_field'] );
		});

		// Bind to AJAX completion to handle dynamic upload interfaces
		$( document ).ajaxComplete( function() {
			slt_fs_media_item_interface();
		});

	}

});

// Function to switch interface elements for a media item
function slt_fs_media_item_interface() {
	var td = jQuery( 'table.describe tr.submit td.savesend' );
	var ss = jQuery( 'tr.submit td.savesend', td );
	var ssi = ss.find( 'input' );
	// Remove all table rows except the buttons
	jQuery( 'tbody tr:not(.submit), a.wp-post-thumbnail', td ).remove();
	// Put the Select button in
	if ( ssi.length ) {
		ssi.replaceWith( select_button );
	} else {
		ss.prepend( select_button );
	}
	// Remove Edit Image button
	jQuery( 'input[value="Edit Image"]', td ).parent( 'p' ).remove();
}

// Check we're in a media overlay called by this plugin's File Select
function slt_fs_media_overlay() {
	var is_our_overlay = false;
	if ( jQuery( "body" ).attr( 'id' ) == 'media-upload' ) {
		// Loop through iframes in parent until we find the one we're in, then test the ID
		parent.jQuery( 'iframe' ).each( function( i, el ) {
			if ( el.contentWindow === window && jQuery( el ).attr( 'id' ) == 'TB_iframeContent' ) {
				parent_src_vars = slt_fs_get_url_vars( parent.document.getElementById( 'TB_iframeContent' ).src );
				//console.log( parent_src_vars );
				if ( 'slt_cf_fs_field' in parent_src_vars ) {
					is_our_overlay = true;
				}
			}
		});
	}
	return is_our_overlay;
}


// Parse URL variables
function slt_fs_get_url_vars( s ) {
	var vars = {};
	var parts = s.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
	 	vars[key] = value;
	});
	return vars;
}

// Place select button for new media uploads
function slt_fs_new_upload_button( count ) {
	var mit, ss, ssi;
	if ( slt_fs_media_overlay() ) {
		mit = jQuery( '#slt-cf-new-upload-button-' + count ).parents( 'table' );
		ss = mit.find( 'tr.submit td.savesend' );
		ssi = ss.find( 'input' );
		mit.find( 'tbody tr:not(.submit), a.wp-post-thumbnail' ).remove();
		mit.find( 'input[type=button][value="Edit Image"]' ).remove();
		if ( ssi.length ) {
			ssi.replaceWith( select_button );
		} else {
			ss.prepend( select_button );
		}
	}
}

// Select button functionality
function slt_cf_fs_select_item( item_id, field_id ) {
	var field, preview_div, preview_size;
	field = jQuery( '#' + field_id );
	preview_div = jQuery( '#' + field_id + '_preview' );
	preview_size = jQuery( '#' + field_id + '_preview-size' ).val();
	// Load preview image
	preview_div.html( '' ).load( slt_cf_file_select.ajaxurl, {
		id: 	item_id,
		size:	preview_size,
		action:	'slt_cf_fs_get_file'
	});
	// Pass ID to form field
	field.val( item_id );
	// Close interface down
	tb_remove();
	jQuery( 'html' ).removeClass( 'File' );
}