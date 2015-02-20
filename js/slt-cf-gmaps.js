// Google Maps script for Developer's Custom Fields
// Contributed by adriantoll
// Be careful of center (in this script) / centre (DCF form field IDs)
/* global google, slt_cf_gmaps */
/* exported slt_cf_gmap_init */

  // Set up an array for multiple maps and markers
  var slt_cf_maps = [];


  // Function to write a marker array to hidden input with a pipe as delimiter
  function write_markers(container_id) {

    // Start with an empty string
    var markers_string = '';

    // Loop through the markers
    for (var key in slt_cf_maps[container_id].markers) {

      // Filter unwanted properties from the prototype
      if (slt_cf_maps[container_id].markers.hasOwnProperty(key)) {

        // Add this marker to the HTML string
        markers_string += slt_cf_maps[container_id].markers[key];

      }

    }

    // Replace ")(" with "|", then remove all other brackets and spaces
    markers_string = markers_string.replace(/\)\(/g,'|').replace(/\(/g,'').replace(/\)/g,'').replace(/ /g,'');

    // Write to the input
    document.getElementById(container_id + '_map_markers').value = markers_string;
    //console.log(markers_string);

  }


  // Function to add a marker to a map
  function add_marker(container_id,new_marker_latlng) {

    // Set the marker var here to avoid duplicate
    // definition errors in jshint
    var marker;

    // If it's not an input map
    if (slt_cf_maps[container_id].map._slt_cf_input_map === false) {

      // Add a simple marker to the map
      marker = new google.maps.Marker({
        map: slt_cf_maps[container_id].map,
        position: new_marker_latlng,
      });

    }

    // Otherwise it's an input map
    else {

      // Add an interactive marker to the map
      marker = new google.maps.Marker({
        draggable: true,
        map: slt_cf_maps[container_id].map,
        position: new_marker_latlng,
        title: 'Drag to move, click to delete',
      });

      // Increment the number of markers
      slt_cf_maps[container_id].marker_total++;

      // Set a variable for ease of use
      marker.id = slt_cf_maps[container_id].marker_total;

      // Add the marker latlng to the markers array
      slt_cf_maps[container_id].markers[marker.id] = marker.position.toString();

      // Update the <input> array
      write_markers(container_id);

      // Set an event listener for a click on the marker
      google.maps.event.addListener(marker, 'click', function() {

        // Remove the marker from the array
        delete slt_cf_maps[container_id].markers[marker.id];

        // Remove the marker from the map
        this.setMap(null);

        // Update the <input> array
        write_markers(container_id);

      });

      // Set an event listener for the end of a marker being dragged
      google.maps.event.addListener(marker, 'dragend', function(e) {

        // Set the new position in an array
        slt_cf_maps[container_id].markers[marker.id] = e.latLng.toString();

        // Update the <input> array
        write_markers(container_id);

      });

    }

  }


  // Write out a map for input or output
  function slt_cf_gmap_init( container_id, map_mode, markers_available, map_markers, map_center_latlng, map_zoom, gmap_type, callback ) {

    // Set the map type
    if      (gmap_type === 'hybrid')    { gmap_type = google.maps.MapTypeId.HYBRID; }
    else if (gmap_type === 'satellite') { gmap_type = google.maps.MapTypeId.SATELLITE; }
    else if (gmap_type === 'terrain')   { gmap_type = google.maps.MapTypeId.TERRAIN; }
    else                                { gmap_type = google.maps.MapTypeId.ROADMAP; }

    // Set the map center
    map_center_latlng = map_center_latlng.replace(' ','');
    map_center_latlng = map_center_latlng.split(',');
    map_center_latlng = new google.maps.LatLng(map_center_latlng[0],map_center_latlng[1]);

    // Put the options array together
    var map_options = {
      center: map_center_latlng,
      mapTypeId: gmap_type,
      scrollwheel: false,
      zoom: map_zoom,
    };

    // Write the map
    slt_cf_maps[container_id] = [];
    slt_cf_maps[container_id].map = new google.maps.Map(document.getElementById( container_id ), map_options);

    // Set the container_id as a variable in the map object
    slt_cf_maps[container_id].map._slt_cf_mapname = container_id;

    // Store whether the map is an input map or not
    if (map_mode === 'input') { slt_cf_maps[container_id].map._slt_cf_input_map = true; }
    else { slt_cf_maps[container_id].map._slt_cf_input_map = false; }

    // Extra settings for markers
    if (markers_available) {

      // Add a markers array to the map container
      slt_cf_maps[container_id].markers = [];

      // Set the current number of markers
      slt_cf_maps[container_id].marker_total = 0;

      // Catch old single marker fields - possibly not necessary but just in case
      if ( ! map_markers instanceof Array) { map_markers = [ map_markers ]; }

      // If there are existing markers
      if (map_markers.length > 0) {

        // Loop through the markers
        for (var key in map_markers) {

          // Filter unwanted properties from the prototype
          if (map_markers.hasOwnProperty(key)) {

            // Split the latlng
            var existing_marker_latlng = map_markers[key].split(',');

            // Create a new latlng object
            existing_marker_latlng = new google.maps.LatLng( existing_marker_latlng[0], existing_marker_latlng[1] );

            // Add the marker
            add_marker(container_id, existing_marker_latlng);

          }

        }

      }

      // Set a variable to store double click status
      slt_cf_maps[container_id].doubleClick = false;

      // Function to deal with double clicks
      google.maps.event.addListener( slt_cf_maps[container_id].map, 'dblclick', function() {

        // Set the double click status to true so single click functions doesn't get triggered
        slt_cf_maps[container_id].doubleClick = true;

        // Reset double click status once the delay for a potential single click has passed
        window.setTimeout(function(){ slt_cf_maps[container_id].doubleClick = false; }, 250);

      });

      // Listen for a click on the map
      google.maps.event.addListener(slt_cf_maps[container_id].map, 'click', function(e) {

        // Wait 250ms to see if there's a double click
        window.setTimeout(function(){ if (!slt_cf_maps[container_id].doubleClick) {

          // Add the marker to the map
          add_marker(container_id, e.latLng);

        } }, 250);

      });

    }



    // Extra settings for input maps
    if (map_mode === 'input') {

      // Listen for changes to the map bounds
      google.maps.event.addListener( slt_cf_maps[container_id].map, 'bounds_changed', function() {

        // Write the new zoom, center and bounds for saving with the post
        document.getElementById( this._slt_cf_mapname + '_zoom' ).value = this.getZoom();
        document.getElementById( this._slt_cf_mapname + '_centre_latlng' ).value = this.getCenter().toString().slice(1,-1).replace(' ','');
        document.getElementById( this._slt_cf_mapname + '_bounds_sw').value = this.getBounds().getSouthWest().toString().slice(1,-1).replace(' ','');
        document.getElementById( this._slt_cf_mapname + '_bounds_ne').value = this.getBounds().getNorthEast().toString().slice(1,-1).replace(' ','');

      });

      // Geocoder
      if ( jQuery().autocomplete ) {

        // Initialise the geocoder
        var geocoder = new google.maps.Geocoder();

        // Set up the geocoder bounds
        var boundsSW = jQuery( '#' + container_id + '_bounds_sw' ).val().split(',');
        var boundsNE = jQuery( '#' + container_id + '_bounds_ne' ).val().split(',');
        var geocodeBoundsSW = new google.maps.LatLng( boundsSW[0], boundsSW[1] );
        var geocodeBoundsNE = new google.maps.LatLng( boundsNE[0], boundsNE[1] );
        slt_cf_maps[container_id].map.geocodeBounds = new google.maps.LatLngBounds(geocodeBoundsSW,geocodeBoundsNE);

        // Kick off the geocoder on page load
        jQuery( document ).ready( function( $ ) {

          // Check if we can use the placeholder instead of a label
          var gmap_geocoder_label_class = '';
          if (document.createElement('input').placeholder !== 'undefined') {
            gmap_geocoder_label_class = 'screen-reader-text';
          }

          // Make the marker instructions conditional
          var marker_instructions = '';
          if (markers_available) { marker_instructions = '<small>Click on the map to add a marker. Click a marker to remove it. Click and drag a marker to change its location.<br><br></small>'; }

          // Write the autocomplete form
          $( '#' + container_id ).after( '<p class="gmap-address">' + marker_instructions + '<label for="' + container_id + '_address" class="' + gmap_geocoder_label_class + '">' + slt_cf_gmaps.geocoder_label + ':</label><input type="text" id="' + container_id + '_address" name="' + container_id + '_address" value="" class="regular-text" style="width:100%;" placeholder="Find an address" /></p>' );

          // Activate the autocomplete functionality
          $( '#' + container_id + '_address' ).autocomplete({

            // Fetch the address values
            source: function(request, response) {

              // Geocode the responses
              // 'bounds' biases the response to results within the existing map bounds
              // https://developers.google.com/maps/documentation/geocoding/#geocoding
              geocoder.geocode( {'address': request.term, 'bounds': slt_cf_maps[container_id].map.getBounds()  }, function(results, status) {

                // If we get meaningful results
                if (status === google.maps.GeocoderStatus.OK) {

                  // Go through each response
                  response($.map(results, function(item) {

                    // Return the address values
                    return {

                      label:     item.formatted_address,
                      value:     item.formatted_address,
                      latitude:  item.geometry.location.lat(),
                      longitude: item.geometry.location.lng()

                    };

                  }));

                }

                // Otherwise there's a problem
                else { console.log('Geocode was not successful for the following reason: ' + status); }

              });

            },

            // When an address is selected
            select: function(event, ui) {

              // Get the latlng of the geocode result
              var newLocation = new google.maps.LatLng(ui.item.latitude,ui.item.longitude);

              // Recenter the map
              slt_cf_maps[container_id].map.panTo(newLocation);

              // Add a marker if appropriate
              if ( markers_available ){ add_marker(container_id,newLocation); }

            }

          });

        });

      }

    }

    // If there's a callback, call it with a reference to the map
    if ( typeof callback !== 'undefined' && window[callback] ) { window[callback]( slt_cf_maps[container_id].map ); }

  }


  // Hide and show the input map
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
                     mapCenter = slt_cf_maps[ id ].map.getCenter();
                     google.maps.event.trigger( slt_cf_maps[ id ].map, "resize" );
                     slt_cf_maps[ id ].map.setCenter(mapCenter);
              });
            } else {
              $( "#" + id + "_wrapper" ).slideUp();
            }
          });
        }
      });
    }

  });
