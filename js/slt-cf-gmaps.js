// Google Maps script for Developer's Custom Fields
// Contributed by adriantoll
// Be careful of center (in this script) / centre (DCF form field IDs)
/*global google */
/*global slt_cf_gmaps */


// Set up an array for multiple maps

  var slt_cf_maps = [];


// Write out a map for input or output

  function slt_cf_gmap_init( container_id, mode, marker_available, marker_latlng, center_latlng, zoom, maptype, callback ) {

    // Declare global variables

      var center_latlng_split, myOptions, marker_latlng_split, geocoder, boundsSW, geocodeBoundsSW, boundsNE, geocodeBoundsNE;

    // Set the map center (note different spelling of center / center)

      center_latlng = center_latlng.replace(' ','');
      center_latlng_split = center_latlng.split(',');
      center_latlng = new google.maps.LatLng(center_latlng_split[0],center_latlng_split[1]);

    // Set the map type

      if (maptype === 'hybrid') { maptype = google.maps.MapTypeId.HYBRID; }
      else if (maptype === 'satellite') { maptype = google.maps.MapTypeId.SATELLITE; }
      else if (maptype === 'terrain') { maptype = google.maps.MapTypeId.TERRAIN; }
      else { maptype = google.maps.MapTypeId.ROADMAP; }

    // Put the options array together

      myOptions = {
        zoom: zoom,
        center: center_latlng,
        mapTypeId: maptype,
        scrollwheel: false
      };

    // Write the map

      slt_cf_maps[container_id] = [];
      slt_cf_maps[container_id].map = new google.maps.Map(document.getElementById( container_id ), myOptions);
      slt_cf_maps[container_id].map._slt_cf_mapname = container_id;

    // Extra settings for markers

      if (marker_available) {

        // If it's an input map, make the marker draggable

          var draggable = ( mode === 'input' ) ? true : false;

        // Drop the marker at the specified latlng

          marker_latlng_split = marker_latlng.split(',');
          marker_latlng = new google.maps.LatLng(marker_latlng_split[0],marker_latlng_split[1]);
          slt_cf_maps[container_id].marker = new google.maps.Marker({
            map: slt_cf_maps[container_id].map,
            position: marker_latlng,
            draggable: draggable,
            animation: google.maps.Animation.DROP
          });
          slt_cf_maps[container_id].marker._slt_cf_mapname = container_id;

        // Listen for the marker being dropped to set the new position

          google.maps.event.addListener( slt_cf_maps[container_id].marker, 'drag', function() {
            var newMarkerLatLng = this.getPosition().toString().slice(1,-1).replace(' ','');
            document.getElementById( this._slt_cf_mapname + '_marker_latlng' ).value = newMarkerLatLng;
          });

      }



    // Extra settings for input maps

      if (mode === 'input') {

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

              geocoder = new google.maps.Geocoder();

            // Set up the geocoder bounds

              boundsSW = jQuery( '#' + container_id + '_bounds_sw' ).val().split(',');
              boundsNE = jQuery( '#' + container_id + '_bounds_ne' ).val().split(',');
              geocodeBoundsSW = new google.maps.LatLng( boundsSW[0], boundsSW[1] );
              geocodeBoundsNE = new google.maps.LatLng( boundsNE[0], boundsNE[1] );
              slt_cf_maps[container_id].map.geocodeBounds = new google.maps.LatLngBounds(geocodeBoundsSW,geocodeBoundsNE);

            // Kick off the geocoder on page load

              jQuery( document ).ready( function( $ ) {

                // Write the autocomplete form

                  $( '#' + container_id ).after( '<p class="gmap-address"><label for="' + container_id + '_address">' + slt_cf_gmaps.geocoder_label + ':</label> <input type="text" id="' + container_id + '_address" name="' + container_id + '_address" value="" class="regular-text" /></p>' );

                // Activate the autocomplete functionality

                  $( '#' + container_id + '_address' ).autocomplete({

                    // Fetch the address values

                      source: function(request, response) {

                        // Geocode the responses
                        // 'bounds' biases the response to results within the existing map bounds
                        // https://developers.google.com/maps/documentation/geocoding/#geocoding

                          geocoder.geocode( {'address': request.term, 'bounds': slt_cf_maps[container_id].map.getBounds()  }, function(results, status) {

                            // If we get meaningful results

                              if (status == google.maps.GeocoderStatus.OK) {

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

                        // Set the marker and center latlng values for saving

                          if ( marker_available ){ $('#' + container_id + '_marker_latlng').val(ui.item.latitude + ',' + ui.item.longitude); }
                          $('#' + container_id + '_centre_latlng').val(ui.item.latitude + ',' + ui.item.longitude);

                        // Update the map with the marker and center values

                          var newLocation = new google.maps.LatLng(ui.item.latitude,ui.item.longitude);
                          if ( marker_available ){ slt_cf_maps[container_id].marker.setPosition(newLocation); }
                          slt_cf_maps[container_id].map.panTo(newLocation);

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
