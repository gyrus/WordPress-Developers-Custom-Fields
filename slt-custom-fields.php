<?php

/**
 * @package Developers_Custom_Fields
 */

/*
Plugin Name: Developer's Custom Fields
Plugin URI: http://wordpress.org/extend/plugins/developers-custom-fields/
Description: Provides theme developers with tools for managing custom fields.
Author: Steve Taylor
Version: 1.2.2
Author URI: http://sltaylor.co.uk
License: GPLv2
Text Domain: slt-custom-fields
Domain Path: /lang
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
- http://snipplr.com/view/6108/mini-textile-class/ (simple textile formatting)
- https://github.com/thomasgriffin/New-Media-Image-Uploader
*/

// Make sure we don't expose any info if called directly
if ( ! function_exists( 'add_action' ) ) {
	echo "Hi there! I'm just a plugin, not much I can do when called directly.";
	exit;
}

/* Globals and constants
***************************************************************************************/
global $slt_custom_fields, $wp_version;
define( 'SLT_CF_TEXT_DOMAIN', 'slt-custom-fields' );
define( 'SLT_CF_TITLE', "Developer's Custom Fields" );
define( 'SLT_CF_NO_OPTIONS', __( 'No options to choose from', SLT_CF_TEXT_DOMAIN ) );
define( 'SLT_CF_REQUEST_PROTOCOL', isset( $_SERVER[ 'HTTPS' ] ) ? 'https://' : 'http://' );
define( 'SLT_CF_VERSION', '1.2.2' );
define( 'SLT_CF_PRIMARY_FILE_PATH', plugin_basename( __FILE__ ) );
$slt_custom_fields = array();
$slt_custom_fields['prefix'] = '_slt_';
$slt_custom_fields['hide_default_custom_meta_box'] = true;
$slt_custom_fields['datepicker_default_format'] = 'dd/mm/yy';
$slt_custom_fields['timepicker_default_format'] = 'hh:mm';
$slt_custom_fields['timepicker_default_ampm'] = false;
$slt_custom_fields['boxes'] = array();
$slt_custom_fields['query_vars'] = array();

// Constants that can be overridden in wp-config.php
if ( ! defined( 'SLT_CF_USE_GMAPS' ) ) {
	define( 'SLT_CF_USE_GMAPS', true );
}
if ( ! defined( 'SLT_CF_USE_FILE_SELECT' ) ) {
	define( 'SLT_CF_USE_FILE_SELECT', true );
}
if ( ! defined( 'SLT_CF_HANDLE_TERM_SPLITTING' ) ) {
	define( 'SLT_CF_HANDLE_TERM_SPLITTING', false );
}


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
		'version'			=> SLT_CF_VERSION
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

	if ( $update_options ) {
		update_option( 'slt_cf_options', $options );
	}

	return $options;
}


/* Initialize
***************************************************************************************/
add_action( 'init', 'slt_cf_init' );
add_action( 'plugins_loaded', 'slt_cf_load_text_domain' );

// Don't run the bulk of stuff for AJAX requests
if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {

	// Admin initialization
	add_action( 'admin_init', 'slt_cf_admin_init' );
	add_action( 'admin_enqueue_scripts', 'slt_cf_admin_enqueue_scripts' ); // Scripts and styles
	add_action( 'admin_menu', 'slt_cf_admin_menus' );
	add_action( 'admin_notices', 'slt_cf_admin_notices' );
	if ( defined( 'SLT_CF_HANDLE_TERM_SPLITTING' ) && SLT_CF_HANDLE_TERM_SPLITTING ) {
		add_action( 'split_shared_term', 'slt_cf_split_shared_term', 10, 4 );
	}

	// Login / register styles
	add_action( 'login_enqueue_scripts', 'slt_cf_login_enqueue_scripts', 10 );

	/* Display hooks
	***************************************************************************************/
	add_action( 'add_meta_boxes', 'slt_cf_display_post_attachment', 10, 2 );
	add_action( 'do_meta_boxes', 'slt_cf_remove_default_meta_box', 1, 3 );
	add_action( 'show_user_profile', 'slt_cf_display_user', 12, 1 );
	add_action( 'edit_user_profile', 'slt_cf_display_user', 12, 1 );
	add_action( 'register_form', 'slt_cf_display_user', 12 );

	// Display for a post / attachment screen
	function slt_cf_display_post_attachment( $context, $object ) {
		global $slt_custom_fields;
		// Check for context based on object properties in case the are 'link' or 'comment' core custom post types
		if ( is_object( $object ) && ! ( isset( $object->comment_ID ) || isset( $object->link_id ) ) ) {
			$request_type = 'post';
			$scope = $context;
			if ( $context == 'attachment' ) {
				$request_type = 'attachment';
				$scope = $object->post_mime_type;
			}
			slt_cf_init_fields( $request_type, $scope, $object->ID );
			if ( count( $slt_custom_fields['boxes'] ) )
				slt_cf_add_meta_boxes( $context );

			// Post meta output for admins
			if ( current_user_can( 'update_core' ) )
				add_meta_box( 'slt_cf_postmeta_output', __( 'All post meta', SLT_CF_TEXT_DOMAIN ), 'slt_cf_postmeta_output', null, 'advanced', 'low' );
		}
	}

	// Display for a user screen / registration form
	function slt_cf_display_user( $user = null ) {
		global $slt_custom_fields;
		$user_role = 'registration';
		$user_id = null;
		if ( ! empty( $user ) ) {
			// Editing a profile, not registering a new user
			$user_roles = $user->roles;
			$user_role = array_shift( $user_roles );
			$user_id = $user->ID;
		}
		slt_cf_init_fields( 'user', $user_role, $user_id );
		if ( count( $slt_custom_fields['boxes'] ) ) {
			slt_cf_add_user_profile_sections( $user );
		}
	}

	/* Save hooks
	***************************************************************************************/
	add_action( 'save_post', 'slt_cf_save_post', 1, 2 );
	add_action( 'personal_options_update', 'slt_cf_save_user', 1, 1 );
	add_action( 'edit_user_profile_update', 'slt_cf_save_user', 1, 1 );
	add_action( 'user_register', 'slt_cf_save_user', 1, 1 );
	add_action( 'add_attachment', 'slt_cf_save_attachment', 1 );
	add_action( 'edit_attachment', 'slt_cf_save_attachment', 1 );

	// Save for a post screen
	function slt_cf_save_post( $post_id, $post ) {
		global $slt_custom_fields;
		// Don't bother with post revisions
		if ( $post->post_type == 'revision' ) {
			return;
		}
		slt_cf_init_fields( 'post', $post->post_type, $post_id );
		if ( count( $slt_custom_fields['boxes'] ) ) {
			slt_cf_save( 'post', $post_id, $post );
		}
	}

	// Save for an attachment screen
	function slt_cf_save_attachment( $post_id ) {
		global $slt_custom_fields;
		$post = get_post( $post_id );
		// Don't bother with post revisions
		if ( $post->post_type == 'revision' ) {
			return;
		}
		slt_cf_init_fields( 'attachment', $post->post_mime_type, $post_id );
		if ( count( $slt_custom_fields['boxes'] ) ) {
			slt_cf_save( 'attachment', $post_id, $post );
		}
	}

	// Save for a user screen
	function slt_cf_save_user( $user_id ) {
		global $slt_custom_fields;
		$user = new WP_User( $user_id );
		$user_role = null;
		if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
			$user_role = array_shift( $user->roles );
		}
		slt_cf_init_fields( 'user', $user_role, $user_id );
		if ( count( $slt_custom_fields['boxes'] ) ) {
			slt_cf_save( 'user', $user_id, $user );
		}
	}

	/* Includes
	***************************************************************************************/
	require_once( 'slt-cf-display.php' );
	require_once( 'slt-cf-save.php' );

}

// Leave these functions available for AJAX requests
require_once( 'slt-cf-init.php' );
require_once( 'slt-cf-admin.php' );
require_once( 'slt-cf-lib.php' );
require_once( 'slt-cf-query-vars.php' );
