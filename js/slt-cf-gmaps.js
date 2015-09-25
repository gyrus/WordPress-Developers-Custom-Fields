// Google Maps script for Developer's Custom Fields
// Contributed by adriantoll
// Be careful of center (in this script) / centre (DCF form field IDs)
/* global google, slt_cf_gmaps, alert */
/* exported slt_cf_gmap_init */

  // Set up array for multiple maps and markers
  var slt_cf_maps = [];


  // Function to write a marker array to hidden input with a pipe as delimiter
  function write_markers(container_id) {

    // Start with an empty string
    var markers_string = '';

    // Loop through the markers
    for (var key in slt_cf_maps[container_id].markers) {

      // Get the marker latlng as a string
      var marker_latlng = slt_cf_maps[container_id].markers[key].getPosition().toString();

      // Add this marker to the HTML string
      markers_string += marker_latlng;

    }

    // Replace ")(" with "|", then remove all other brackets and spaces
    markers_string = markers_string.replace(/\)\(/g,'|').replace(/\(/g,'').replace(/\)/g,'').replace(/ /g,'');

    // Write to the input
    document.getElementById(container_id + '_map_markers').value = markers_string;

  }


  // Function to add a marker to a map
  function add_marker(container_id,new_marker_latlng) {

    // Set the marker var here to avoid duplicate
    // definition errors in jshint
    var marker;

    // If it's not an input map
    if (slt_cf_maps[container_id].settings.input_map === false) {

      // Add a simple marker to the map
      marker = new google.maps.Marker({
        map: slt_cf_maps[container_id].map,
        position: new_marker_latlng,
      });

    }

    // Otherwise it's an input map
    else {

      // Only add a marker if we haven't reached the maximum number of markers
      // or if this is a map being initialised
      if ( slt_cf_maps[container_id].markers.length < slt_cf_maps[container_id].settings.markers_max || slt_cf_maps[container_id].settings.init === true ) {

        // Add an interactive marker to the map
        // by pushing it to the markers object
        slt_cf_maps[container_id].markers.push(
          new google.maps.Marker({
            draggable: true,
            map: slt_cf_maps[container_id].map,
            position: new_marker_latlng,
            title: 'Drag to move, click to delete',
          })
        );

        // Update the <input> array
        write_markers(container_id);

        // Get the marker's array key
        var marker_key = slt_cf_maps[container_id].markers.length - 1;

        // Set an event listener for a click on the marker
        google.maps.event.addListener(slt_cf_maps[container_id].markers[marker_key], 'click', function() {

          // Remove the marker from the array
          slt_cf_maps[container_id].markers.splice(marker_key,1);

          // Remove the marker from the map
          this.setMap(null);

          // Remove a "max markers" error highlight
          if (slt_cf_maps[container_id].settings.markers_max > 0) { document.getElementById('markers_max_message').className = ''; }

          // Update the <input> array
          write_markers(container_id);

        });

        // Set an event listener for the end of a marker being dragged
        google.maps.event.addListener(slt_cf_maps[container_id].markers[marker_key], 'dragend', function(e) {

          // Update the <input> array
          write_markers(container_id);

        });

      // Otherwise highlight the max markers message
      } else if (slt_cf_maps[container_id].settings.markers_max > 1) {

        document.getElementById('markers_max_message').className = 'markers_max_message_error';

      }

    }

  }


  // Write out a map for input or output
  function slt_cf_gmap_init( container_id, map_mode, markers_max, map_markers, map_center_latlng, map_zoom, gmap_type, callback ) {

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

    // Add objects to the container for markers and settings
    slt_cf_maps[container_id].markers = [];
    slt_cf_maps[container_id].settings = [];

    // Add an init variable so we don't crash when reducing max markers
    slt_cf_maps[container_id].settings.init = true;

    // Set the container_id as a variable in the map object
    // to write changes out to the container HTML
    slt_cf_maps[container_id].map.parent_container_id = container_id;

    // Set the maximum number of markers
    slt_cf_maps[container_id].settings.markers_max = markers_max;

    // Store whether the map is an input map or not
    if (map_mode === 'input') { slt_cf_maps[container_id].settings.input_map = true; }
    else                      { slt_cf_maps[container_id].settings.input_map = false; }

    // Extra settings for markers
    if (markers_max) {

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

      // Only do this on input maps
      if (map_mode === 'input') {

        // Set a variable to store double click status
        slt_cf_maps[container_id].settings.doubleClick = false;

        // Function to deal with double clicks
        google.maps.event.addListener( slt_cf_maps[container_id].map, 'dblclick', function() {

          // Set the double click status to true so single click functions doesn't get triggered
          slt_cf_maps[container_id].settings.doubleClick = true;

          // Reset double click status once the delay for a potential single click has passed
          window.setTimeout(function(){ slt_cf_maps[container_id].settings.doubleClick = false; }, 250);

        });

        // Listen for a click on the map
        google.maps.event.addListener(slt_cf_maps[container_id].map, 'click', function(e) {

          // Wait 250ms to see if there's a double click
          window.setTimeout(function(){ if (!slt_cf_maps[container_id].settings.doubleClick) {

            // Add the marker to the map
            add_marker(container_id, e.latLng);

          } }, 250);

        });

      }

    }



    // Extra settings for input maps
    if (map_mode === 'input') {

      // Listen for changes to the map bounds
      google.maps.event.addListener( slt_cf_maps[container_id].map, 'bounds_changed', function() {

        // Write the new zoom, center and bounds for saving with the post
        document.getElementById( this.parent_container_id + '_zoom' ).value = this.getZoom();
        document.getElementById( this.parent_container_id + '_centre_latlng' ).value = this.getCenter().toString().slice(1,-1).replace(' ','');
        document.getElementById( this.parent_container_id + '_bounds_sw').value = this.getBounds().getSouthWest().toString().slice(1,-1).replace(' ','');
        document.getElementById( this.parent_container_id + '_bounds_ne').value = this.getBounds().getNorthEast().toString().slice(1,-1).replace(' ','');

      });

      // Geocoder
      if ( jQuery().autocomplete ) {

        // Initialise the geocoder
        var geocoder = new google.maps.Geocoder();

        // Kick off the geocoder on page load
        jQuery( document ).ready( function( $ ) {

          // Check if we can use the placeholder instead of a label
          var gmap_geocoder_label_class = '';
          if (document.createElement('input').placeholder !== 'undefined') {
            gmap_geocoder_label_class = 'screen-reader-text';
          }

          // Make the marker instructions conditional
          var marker_instructions = '';
          if (markers_max) {

            marker_instructions =  '';

              if ( slt_cf_maps[container_id].settings.markers_max > 1 ) {

                marker_instructions += '<p>Click on the map or find an address to add a marker. Click a marker to remove it. Drag and drop a marker to change its location.</p>';
                marker_instructions += '<p id="markers_max_message">Maximum number of markers: ' + slt_cf_maps[container_id].settings.markers_max + '</p>';

              }

              else {

                marker_instructions += '<p> Drag and drop the marker or find an address below.</p>';

              }


          }

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

              // If there are multiple markers
              if ( slt_cf_maps[container_id].settings.markers_max > 1 ){

                // Try to add a new marker
                add_marker(container_id,newLocation);

              // Or if there's one marker
              } else if ( slt_cf_maps[container_id].settings.markers_max === 1 ) {

                // Move the marker
                slt_cf_maps[container_id].markers[0].setPosition(newLocation);

                // And update the <input> array
                write_markers(container_id);

              }

            }

          });

        });

      }

    }

    // If there's a callback, call it with a reference to the map
    if ( typeof callback !== 'undefined' && window[callback] ) { window[callback]( slt_cf_maps[container_id].map ); }

    // We've finished, so init is no longer true
    slt_cf_maps[container_id].settings.init = false;

  }



// HIDE AND SHOW MAPS

  // Wait for the document to load
  jQuery( document ).ready( function( $ ) {

    // Listen for a change to the display radio buttons
    $('input.gmap_toggle_display').change( function() {

      // Set the map container id and wrapper selector
      var map_container_id = $(this).attr('data-map-id');
      var map_wrapper_selector = '#' + map_container_id + '_wrapper';

      // If it's the "yes" button
      if ($(this).hasClass('yes')) {

        // Slide the map wrapper down
        $(map_wrapper_selector).slideDown('fast', function() {

          // When the transition has finished:

          // Get the map's intended center
          var mapCenter = slt_cf_maps[map_container_id].map.getCenter();

          // Trigger the map resize event to fill the wrapper
          google.maps.event.trigger( slt_cf_maps[map_container_id].map, "resize" );

          // Reset the map center with the new bounds
          slt_cf_maps[map_container_id].map.setCenter(mapCenter);

        });

      }

      // Otherwise, default to closing the map
      else {

        $(map_wrapper_selector).slideUp();

      }

    });

  });
