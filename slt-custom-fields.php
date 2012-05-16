<?php

/**
 * @package Developer's Custom Fields
 */

/*
Plugin Name: Developer's Custom Fields
Plugin URI: http://wordpress.org/extend/plugins/developers-custom-fields/
Description: Provides theme developers with tools for managing custom fields.
Author: Steve Taylor
Version: 0.7.2.3
Author URI: http://sltaylor.co.uk
License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
Inspired by
- http://wordpressapi.com/2010/11/22/file-upload-with-add_meta_box-or-custom_post_type-in-wordpress/ (file upload code)
- http://snipplr.com/view/6108/mini-textile-class/ (simple textile formatting)
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo "Hi there! I'm just a plugin, not much I can do when called directly.";
	exit;
}

/* Globals and constants
***************************************************************************************/
global $slt_custom_fields, $wp_version;
define( 'SLT_CF_TITLE', "Developer's Custom Fields" );
define( 'SLT_CF_NO_OPTIONS', __( 'No options to choose from', 'slt-custom-fields' ) );
define( 'SLT_CF_REQUEST_PROTOCOL', isset( $_SERVER[ 'HTTPS' ] ) ? 'https://' : 'http://' );
define( 'SLT_CF_VERSION', '0.7.2.2' );
define( 'SLT_CF_WP_IS_GTE_3_3', version_compare( round( $wp_version, 1 ), '3.3' ) >= 0 );
$slt_custom_fields = array();
$slt_custom_fields['prefix'] = '_slt_';
$slt_custom_fields['hide_default_custom_meta_box'] = true;
/*This should be changed along with the name and structure, so that all ui elements can be styled at one place */
$slt_custom_fields['ui_css_url'] = plugins_url( 'js/jquery-datepicker/smoothness/jquery-ui-1.8.16.custom.css', __FILE__ );
$slt_custom_fields['datepicker_default_format'] = 'dd/mm/yy';
/*Timepicker additions */
$slt_custom_fields['timepicker_default_format'] = 'hh:mm tt';
$slt_custom_fields['timepicker_default_ampm'] = true;
$slt_custom_fields['boxes'] = array();
if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
	$slt_custom_fields['css_url'] = plugins_url( 'css/slt-cf-admin.css', __FILE__ );
else
	$slt_custom_fields['css_url'] = plugins_url( 'css/slt-cf-admin.min.css', __FILE__ );

// Constants that can be overridden in wp-config.php
if ( ! defined( 'SLT_CF_USE_GMAPS' ) )
	define( 'SLT_CF_USE_GMAPS', true );
if ( ! defined( 'SLT_CF_USE_FILE_SELECT' ) )
	define( 'SLT_CF_USE_FILE_SELECT', true );

/* Options stored in database
***************************************************************************************/
$slt_custom_fields['options'] = slt_cf_init_options();

/**
 * Initializes plugin options stored in database
 *
 * @since	0.7
 * @return	array	The current option values
 */
function slt_cf_init_options() {
	// Defaults
	$defaults = array(
		'version'			=> SLT_CF_VERSION,
		'alert-07-cleanup'	=> 1
	);
	// Try to get options from database
	$options = get_option( 'slt_cf_options' );
	$update_options = false;
	if ( ! $options ) {
		// No options - new installation, or upgrade from < 0.7
		$options = $defaults;
		$update_options = true;
	} else if ( ! array_key_exists( 'version', $options ) || version_compare( $options['version'], SLT_CF_VERSION ) == -1 ) {
		// No stored version, or stored version is lower than current version - upgrade
		$options = array_merge( $defaults, $options );
		// Force stored version to new version
		$options['version'] = SLT_CF_VERSION;
		$update_options = true;
	}
	if ( $update_options )
		update_option( 'slt_cf_options', $options );
	return $options;
}

/* Initialize
***************************************************************************************/
add_action( 'init', 'slt_cf_init' );

// Don't run the bulk of stuff for AJAX requests
if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {

	// Admin initialization
	add_action( 'admin_init', 'slt_cf_admin_init' );
	add_action( 'admin_menu', 'slt_cf_admin_menus' );
	add_action( 'admin_notices', 'slt_cf_admin_notices' );

	/* Display hooks
	***************************************************************************************/
	add_action( 'add_meta_boxes', 'slt_cf_display_post', 10, 2 );
	add_action( 'do_meta_boxes', 'slt_cf_remove_default_meta_box', 1, 3 );
	add_filter( 'attachment_fields_to_edit', 'slt_cf_display_attachment', 10, 2 );
	add_action( 'show_user_profile', 'slt_cf_display_user', 12, 1 );
	add_action( 'edit_user_profile', 'slt_cf_display_user', 12, 1 );

	// Display for a post screen
	function slt_cf_display_post( $context, $object ) {
		global $slt_custom_fields;
		// Check for context based on object properties in case the are 'link' or 'comment' custom post types
		if ( is_object( $object ) && ! ( isset( $object->comment_ID ) || isset( $object->link_id ) ) ) {
			slt_cf_init_fields( 'post', $context, $object->ID );
			if ( count( $slt_custom_fields['boxes'] ) )
				slt_cf_add_meta_boxes( $context );
		}
	}

	// Display for an attachment screen
	function slt_cf_display_attachment( $form_fields, $post ) {
		global $slt_custom_fields;
		slt_cf_init_fields( 'attachment', $post->post_mime_type, $post->ID );
		if ( count( $slt_custom_fields['boxes'] ) )
			$form_fields = slt_cf_add_attachment_fields( $form_fields, $post );
		return $form_fields;
	}

	// Display for a user screen
	function slt_cf_display_user( $user ) {
		global $slt_custom_fields;
		$user_roles = $user->roles;
		$user_role = array_shift( $user_roles );
		slt_cf_init_fields( 'user', $user_role, $user->ID );
		if ( count( $slt_custom_fields['boxes'] ) )
			slt_cf_add_user_profile_sections( $user );
	}

	/* Save hooks
	***************************************************************************************/
	add_action( 'save_post', 'slt_cf_save_post', 1, 2 );
	add_filter( 'attachment_fields_to_save', 'slt_cf_save_attachment', 10, 2 );
	add_action( 'personal_options_update', 'slt_cf_save_user', 1, 1 );
	add_action( 'edit_user_profile_update', 'slt_cf_save_user', 1, 1 );

	// Save for a post screen
	function slt_cf_save_post( $post_id, $post ) {
		global $slt_custom_fields;
		// Don't bother with post revisions
		if ( $post->post_type == 'revision' )
			return;
		slt_cf_init_fields( 'post', $post->post_type, $post_id );
		if ( count( $slt_custom_fields['boxes'] ) )
			slt_cf_save( 'post', $post_id, $post );
	}

	// Save for an attachment screen
	function slt_cf_save_attachment( $post, $attachment ) {
		global $slt_custom_fields;
		slt_cf_init_fields( 'attachment', $post['post_mime_type'], $post['ID'] );
		if ( count( $slt_custom_fields['boxes'] ) )
			$post = slt_cf_save( 'attachment', $post['ID'], $post, $attachment );
		return $post;
	}

	// Save for a user screen
	function slt_cf_save_user( $user_id ) {
		global $slt_custom_fields;
		$user = new WP_User( $user_id );
		$user_role = null;
		if ( ! empty( $user->roles ) && is_array( $user->roles ) )
			$user_role = array_shift( $user->roles );
		slt_cf_init_fields( 'user', $user_role, $user_id );
		if ( count( $slt_custom_fields['boxes'] ) )
			slt_cf_save( 'user', $user_id, $user );
	}

	/* Includes
	***************************************************************************************/
	require_once( 'slt-cf-admin.php' );
	require_once( 'slt-cf-display.php' );
	require_once( 'slt-cf-save.php' );

}

// Leave these functions available for AJAX requests
require_once( 'slt-cf-init.php' );
require_once( 'slt-cf-lib.php' );

