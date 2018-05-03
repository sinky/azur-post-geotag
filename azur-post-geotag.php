<?php
/*
Plugin Name: Azur Post Geotag
Plugin URI: http://my-azur.de
Version: 0.1
Author: Marco Krage
Author URI: http://my-azur.de
Description: Set latlng of Post
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

/**
 * Adds a box to the main column on the Post and Page edit screens.
*/

function azur_post_geotag_scripts() {
  wp_enqueue_style('leaflet', '//cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.1/leaflet.css');
  wp_enqueue_script('leaflet-js', '//cdnjs.cloudflare.com/ajax/libs/leaflet/1.3.1/leaflet.js');
}
add_action( 'admin_enqueue_scripts', 'azur_post_geotag_scripts' );


function azur_post_geotag_add_meta_box() {

	$screens = array( 'post', 'page' );

	foreach ( $screens as $screen ) {

		add_meta_box(
			'azur_post_geotag_sectionid',
			'Azur Post Geotag',
			'azur_post_geotag_meta_box_callback',
			$screen
		);
	}
}
add_action( 'add_meta_boxes', 'azur_post_geotag_add_meta_box' );

/**
 * Prints the box content.
 *
 * @param WP_Post $post The object for the current post/page.
 */
function azur_post_geotag_meta_box_callback( $post ) {

	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'azur_post_geotag_meta_box', 'azur_post_geotag_meta_box_nonce' );

	/*
	 * Use get_post_meta() to retrieve an existing value
	 * from the database and use the value for the form.
	 */
	$lat = get_post_meta( $post->ID, 'geo_latitude', true );
	$lng = get_post_meta( $post->ID, 'geo_longitude', true );

	echo '<p><label for="azur_post_geotag_field">';
	_e( 'Geolocation', 'azur_post_geotag_textdomain' );
	echo '</label> ';
	echo '<input type="text" id="azur_post_geotag_field_geo_latitude" name="azur_post_geotag_field_geo_latitude" value="' . esc_attr( $lat ) . '" placeholder="Latitude"/> ';
	echo '<input type="text" id="azur_post_geotag_field_geo_longitude" name="azur_post_geotag_field_geo_longitude" value="' . esc_attr( $lng ) . '" placeholder="Longitude" /> ';
	echo '<button id="azur_post_geotag_reset">Rückgänig</button></p>';
  ?>
<div id="azur_post_geotag_map" style="height: 300px; width: 100%;">Map</div>
<script>
var map;
var azur_post_geotag_reset = document.getElementById('azur_post_geotag_reset');
var azur_post_geotag_field_geo_latitude = document.getElementById('azur_post_geotag_field_geo_latitude');
var azur_post_geotag_field_geo_longitude = document.getElementById('azur_post_geotag_field_geo_longitude');

function azur_post_geotag_initialize() {
  var lat = '<?php echo $lat; ?>';
  var lng = '<?php echo $lng; ?>';

  var center, zoom;

  var localOptions = JSON.parse(localStorage.getItem('<?php echo array_pop(explode('/', get_bloginfo('wpurl'))); ?>.azurPostMap'));

  // Defaults
  center = L.latLng(51, 7);
  zoom = 6;

  // Last marker position stored local
  if(localOptions) {
    center = new L.latLng(localOptions.lat, localOptions.lng);
  }

  // we have Post Data
  if(lat && lng) {
    center = new L.latLng(lat, lng);
  }

  var mapOptions = {
    center: center,
    zoom: Number(zoom)
  };

  map = L.map('azur_post_geotag_map', mapOptions);

  var marker = L.marker(center, {
    draggable: true
  });
	
  var tile_osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // we have Post Data
  if(lat && lng) {
    marker.addTo(map);
  }

  map.on('click', function(e) {
    marker.setLatLng(e.latlng);
    marker.addTo(map);
    setLatLng(e.latlng);
    saveLocalOptions(e.latlng);
  });

  marker.on('dragend', function(e) {
		var latlng = this.getLatLng();
    setLatLng(latlng);
    saveLocalOptions(latlng);
  });

  azur_post_geotag_reset.addEventListener('click', function(e) {
    e.preventDefault();
    azur_post_geotag_field_geo_latitude.value = lat;
    azur_post_geotag_field_geo_longitude.value = lng;
    map.setView([lat, lng]);
    marker.setLatLng([lat, lng]);
  });

  // automatic split "lat, lng"
  var handle_latlng_split = function(event) {
    if(/^\s*-?\d+\.\d+,\s?-?\d+\.\d+\s*$/.test(this.value)) {
      var coords = this.value.split(',');

      coords[0] = coords[0].trim();
      coords[1] = coords[1].trim();

      azur_post_geotag_field_geo_latitude.value = coords[0];
      azur_post_geotag_field_geo_longitude.value = coords[1];

      map.setView([coords[0], coords[1]]);
      marker.setLatLng([coords[0], coords[1]]);
    }
  };

  azur_post_geotag_field_geo_latitude.addEventListener('change', handle_latlng_split);
  azur_post_geotag_field_geo_longitude.addEventListener('change', handle_latlng_split);

}

function setLatLng(latlng) {
  azur_post_geotag_field_geo_latitude.value = latlng.lat;
  azur_post_geotag_field_geo_longitude.value = latlng.lng;
}

function saveLocalOptions(latlng) {
  var storeLocalOptions = {
    lat: latlng.lat,
    lng: latlng.lng
  };
  localStorage.setItem('<?php echo array_pop(explode('/', get_bloginfo('wpurl'))); ?>.azurPostMap', JSON.stringify(storeLocalOptions));
}

window.addEventListener('load', azur_post_geotag_initialize);
</script>
<?php
}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id The ID of the post being saved.
 */
function azur_post_geotag_save_meta_box_data( $post_id ) {

	/*
	 * We need to verify this came from our screen and with proper authorization,
	 * because the save_post action can be triggered at other times.
	 */

	// Check if our nonce is set.
	if ( ! isset( $_POST['azur_post_geotag_meta_box_nonce'] ) ) {
		return;
	}

	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['azur_post_geotag_meta_box_nonce'], 'azur_post_geotag_meta_box' ) ) {
		return;
	}

	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

	} else {

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	/* OK, it's safe for us to save the data now. */

	// Make sure that it is set.
	if ( ! isset( $_POST['azur_post_geotag_field_geo_latitude'] ) && ! isset( $_POST['azur_post_geotag_field_geo_longitude'] ) ) {
		return;
	}

	// Sanitize user input.
	$lat = sanitize_text_field( $_POST['azur_post_geotag_field_geo_latitude'] );
	$lng = sanitize_text_field( $_POST['azur_post_geotag_field_geo_longitude'] );

  // Update the meta field in the database.
  if($lat == 0 && $lng == 0 || empty($lat) || empty($lng)) {
    delete_post_meta( $post_id, 'geo_public' );
    delete_post_meta( $post_id, 'geo_latitude' );
    delete_post_meta( $post_id, 'geo_longitude' );
  }else{
    update_post_meta( $post_id, 'geo_public', 1 );
    update_post_meta( $post_id, 'geo_latitude', $lat );
    update_post_meta( $post_id, 'geo_longitude', $lng );
  }

}
add_action( 'save_post', 'azur_post_geotag_save_meta_box_data' );
