// Google Maps script for Developer's Custom Fields
// Contributed by adriantoll

// set up an array for multiple maps
var slt_cf_maps = [];

// Write out a map for input or output
function slt_cf_gmap_init( container_id, mode, marker_available, marker_latlng, centre_latlng, zoom, maptype, callback ) {
	var center_latlng_split, center_latlng, myOptions, marker_latlng_split, marker_latlng, geocoder, boundsSW, geocodeBoundsSW, boundsNE, geocodeBoundsNE;

	// SET UP THE MAP

	// set the map center (note different spelling of centre / center)
	centre_latlng = centre_latlng.replace(' ','');
	center_latlng_split = centre_latlng.split(',');
	center_latlng = new google.maps.LatLng(center_latlng_split[0],center_latlng_split[1]);
	
	// set the map type
	if (maptype == 'hybrid') { maptype = google.maps.MapTypeId.HYBRID; }
	else if (maptype == 'satellite') { maptype = google.maps.MapTypeId.SATELLITE; }
	else if (maptype == 'terrain') { maptype = google.maps.MapTypeId.TERRAIN; }
	else { maptype = google.maps.MapTypeId.ROADMAP; }

	// put the options array together
	myOptions = {
		zoom: zoom,
		center: center_latlng,
		mapTypeId: maptype,
		scrollwheel: false
	};

	// write the map
	slt_cf_maps[container_id] = new Array();
	slt_cf_maps[container_id]['map'] = new google.maps.Map(document.getElementById( container_id ), myOptions);
	slt_cf_maps[container_id]['map']['_slt_cf_mapname'] = container_id;

	// IF THERE'S A MARKER
	if (marker_available) {

		// if it's an input map, make the marker draggable
		draggable = ( mode == 'input' ) ? true : false;

		// drop the marker at the specified latlng
		marker_latlng_split = marker_latlng.split(',');
		marker_latlng = new google.maps.LatLng(marker_latlng_split[0],marker_latlng_split[1]);
		slt_cf_maps[container_id]['marker'] = new google.maps.Marker({
			map: slt_cf_maps[container_id]['map'],
			position: marker_latlng,
			draggable: draggable,
			animation: google.maps.Animation.DROP
		});
		slt_cf_maps[container_id]['marker']['_slt_cf_mapname'] = container_id;
		
		// listen for the marker being dropped to set new position
		google.maps.event.addListener( slt_cf_maps[container_id]['marker'], 'drag', function() {
			newMarkerLatLng = this.getPosition().toString().slice(1,-1).replace(' ','');
			document.getElementById( this['_slt_cf_mapname'] + '_marker_latlng' ).value = newMarkerLatLng;
		});		

	}

	// IF IT'S AN INPUT MAP
	if (mode == 'input') {
	
		// listen for changes to the map zoom
		google.maps.event.addListener( slt_cf_maps[container_id]['map'], 'zoom_changed', function() {
			newZoom = this.getZoom();
			document.getElementById( this['_slt_cf_mapname'] + '_zoom' ).value = newZoom;
			document.getElementById( this['_slt_cf_mapname'] + '_bounds_sw').value = this.getBounds().getSouthWest().toString().slice(1,-1).replace(' ','');
			document.getElementById( this['_slt_cf_mapname'] + '_bounds_ne').value = this.getBounds().getNorthEast().toString().slice(1,-1).replace(' ','');
		});
		
		// listen for changes to the map center
		google.maps.event.addListener( slt_cf_maps[container_id]['map'], 'center_changed', function() {
			newLatLng = this.getCenter().toString().slice(1,-1).replace(' ','');
			document.getElementById( this['_slt_cf_mapname'] + '_centre_latlng' ).value = newLatLng;
			document.getElementById( this['_slt_cf_mapname'] + '_bounds_sw').value = this.getBounds().getSouthWest().toString().slice(1,-1).replace(' ','');
			document.getElementById( this['_slt_cf_mapname'] + '_bounds_ne').value = this.getBounds().getNorthEast().toString().slice(1,-1).replace(' ','');
		});
		
		// set up the geocoder?
		if ( jQuery().autocomplete ) {

			geocoder = new google.maps.Geocoder();

			// set up the geocoder bounds
			boundsSW = jQuery( '#' + container_id + '_bounds_sw' ).val().split(',');
			geocodeBoundsSW = new google.maps.LatLng( boundsSW[0], boundsSW[1] );
			boundsNE = jQuery( '#' + container_id + '_bounds_ne' ).val().split(',');
			geocodeBoundsNE = new google.maps.LatLng( boundsNE[0], boundsNE[1	] );
			slt_cf_maps[container_id]['map']['geocodeBounds'] = new google.maps.LatLngBounds(geocodeBoundsSW,geocodeBoundsNE);
			
			jQuery( document ).ready( function( $ ) {
				
				// Write the geocoder
				$( '#' + container_id ).before( '<p class="gmap-address"><label for="' + container_id + '_address">' + slt_cf_gmaps.geocoder_label + ':</label> <input type="text" id="' + container_id + '_address" name="' + container_id + '_address" value="" class="regular-text" /></p>' );
	
				// activate the autocomplete functionality
				$( '#' + container_id + '_address' ).autocomplete({
			
					// fetch the address values
					source: function(request, response) {
			
						// geocode
						geocoder.geocode( {'address': request.term }, function(results, status) {
			
							// deal with the response
							response($.map(results, function(item) {
			
								// if it's within a rough rectangle around the default geocoding bounds
								addressLatLng = new google.maps.LatLng(item.geometry.location.lat(),item.geometry.location.lng());
								if ( slt_cf_maps[container_id]['map']['geocodeBounds'].contains(addressLatLng) ) {
		
									// return the address values
									return {
			
										label:  item.formatted_address,
										value: item.formatted_address,
										latitude: item.geometry.location.lat(),
										longitude: item.geometry.location.lng()
			
									} // end return the address values
			
								} // end if it's within the UK
								
							})); // end geocode
			
						}); // end deal with the response
						
					}, // end fetch the address values
				
					// what to do when an address is selected
					select: function(event, ui) {
						var newLocation;
			
						// set the marker and center latlng
						if ( marker_available )
							$('#' + container_id + '_marker_latlng').val(ui.item.latitude + ',' + ui.item.longitude);
						$('#' + container_id + '_centre_latlng').val(ui.item.latitude + ',' + ui.item.longitude);
						newLocation = new google.maps.LatLng(ui.item.latitude,ui.item.longitude);
						if ( marker_available )
							slt_cf_maps[container_id]['marker'].setPosition(newLocation);
						slt_cf_maps[container_id]['map'].panTo(newLocation);
			
					}
				
				}); // end activate the autocomplete functionality
			
			});

		}
	
	}
	
	// If there's a callback, call it with a reference to the map
	if ( typeof callback != 'undefined' && window[callback] )
		window[callback]( slt_cf_maps[container_id]['map'] );

}

jQuery( document ).ready( function( $ ) {
	if ( $( 'div.gmap_input' ).length ) {
		$( 'div.gmap_input' ).each( function() {
			var id, mapCenter;
			id = $( this ).attr( 'id' );
			if ( $( "input.gmap_toggle_display" ).length ) {
				// Toggle map view
				$( "input.gmap_toggle_display" ).change( function() {
					if ( $( this ).hasClass( "yes" ) ) {
						$( "#" + id + "_wrapper" ).slideDown('fast', function() {
						       mapCenter = slt_cf_maps[ id ]["map"].getCenter();
						       google.maps.event.trigger( slt_cf_maps[ id ]["map"], "resize" );
						       slt_cf_maps[ id ]["map"].setCenter(mapCenter);
						});
					} else {
						$( "#" + id + "_wrapper" ).slideUp();
					}
				});
			}
		});
	}
});
