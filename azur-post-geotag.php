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
  wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?v=3.9');
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

	echo '<p><label for="azur_post_geotag_new_field">';
	_e( 'Geolocation', 'azur_post_geotag_textdomain' );
	echo '</label> ';
	echo '<input type="text" id="azur_post_geotag_new_field_geo_latitude" name="azur_post_geotag_new_field_geo_latitude" value="' . esc_attr( $lat ) . '" /> ';
	echo '<input type="text" id="azur_post_geotag_new_field_geo_longitude" name="azur_post_geotag_new_field_geo_longitude" value="' . esc_attr( $lng ) . '" /> ';
	echo '<button id="azur_post_geotag_reset">Zur&uuml;cksetzen</button></p>';
  ?>
<div id="azur_post_geotag_map" style="height: 300px; width: 100%;">Map</div>
<script>
var map;
function initialize() {
  var lat = '<?php echo $lat; ?>';
  var lng = '<?php echo $lng; ?>';

  var center, zoom;

  var localOptions = JSON.parse(localStorage.getItem('reisen.azurPostMap'));

  console.log('localOptions', localOptions);

  // Defaults
  center = new google.maps.LatLng(51, 8);
  zoom = 6;

  // Last marker position stored local
  if(localOptions) {
    center = new google.maps.LatLng(localOptions.lat, localOptions.lng);
  }
  
  // Post Data
  if(lat && lng) {
    center = new google.maps.LatLng(lat, lng);
  }

  var mapOptions = {
    center: center,
    zoom: Number(zoom)
  };

  map = new google.maps.Map(document.getElementById('azur_post_geotag_map'), mapOptions);

  var marker = new google.maps.Marker({
    position: center,
    map: map,
    draggable: true
  });

  google.maps.event.addListener(map, 'click', function(e) {
    marker.setPosition(e.latLng);
    setLatLng(e);
    saveLocalOptions(e);
  });

  google.maps.event.addListener(marker, 'dragend', function(e) {
    setLatLng(e);
    saveLocalOptions(e);
  });

  google.maps.event.addDomListener(document.getElementById('azur_post_geotag_reset'), 'click', function(e) {
    e.preventDefault();
    document.getElementById('azur_post_geotag_new_field_geo_latitude').value = lat;
    document.getElementById('azur_post_geotag_new_field_geo_longitude').value = lng;
    marker.setPosition(new google.maps.LatLng(lat,lng));
  });
}

function setLatLng(e) {
  document.getElementById('azur_post_geotag_new_field_geo_latitude').value = e.latLng.lat();
  document.getElementById('azur_post_geotag_new_field_geo_longitude').value = e.latLng.lng();
}

function saveLocalOptions(e) {
  var storeLocalOptions = {
    lat: e.latLng.lat(),
    lng: e.latLng.lng()
  };
  localStorage.setItem('reisen.azurPostMap', JSON.stringify(storeLocalOptions));
}

google.maps.event.addDomListener(window, 'load', initialize);
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
	if ( ! isset( $_POST['azur_post_geotag_new_field_geo_latitude'] ) && ! isset( $_POST['azur_post_geotag_new_field_geo_longitude'] ) ) {
		return;
	}

	// Sanitize user input.
	$lat = sanitize_text_field( $_POST['azur_post_geotag_new_field_geo_latitude'] );
	$lng = sanitize_text_field( $_POST['azur_post_geotag_new_field_geo_longitude'] );

	// Update the meta field in the database.
	update_post_meta( $post_id, 'geo_latitude', $lat );
	update_post_meta( $post_id, 'geo_longitude', $lng );
}
add_action( 'save_post', 'azur_post_geotag_save_meta_box_data' );