<?php

/* Admin */

/* Notices
***************************************************************************************/

/**
 * Outputs any general admin notices.
 *
 * @since	0.7
 * @return	void
 */
function slt_cf_admin_notices() {
	global $slt_cf_admin_notices;
	// 0.7 cleanup
	if ( in_array( 'alert-07-cleanup', $slt_cf_admin_notices ) )
		echo '<div id="message" class="error"><p>' . sprintf( __(  'Please note that recent versions of the Developer\'s Custom Fields plugin may have created minor issues with single checkbox values. For more information, and to tidy up the database, make sure you visit the <a href="%1$s">Custom Fields database tools</a> page and run the cleanup tool there.', 'slt-custom-fields' ), admin_url( 'tools.php?page=slt_cf_data_tools' )  ) . '</p><p>' . sprintf( __(  'If this is the first time you\'ve used this plugin, or you don\'t think you\'ll have a problem with this, <a id="%1$s" href="#" title="Dismiss this notice without running the database cleanup">dismiss this notice</a>.', 'slt-custom-fields' ), 'slt-cf-dismiss_alert-07-cleanup'  ) . '</p></div>' . "\n";
	/*
	// jQuery UI autocomplete for Gmaps
	$gmap_fields = slt_cf_get_field_names( array(), array( 'gmap' ) );
	if ( ! empty( $gmap_fields ) ) { ?>
		<script type="text/javascript">
		if ( ! jQuery().autocomplete ) {
			document.write( '<?php echo '<div id="message" class="updated"><p>' . __( 'You have at least one <code>gmap</code> field defined through Developer\\\'s Custom Fields, but your WordPress installation doesn\\\'t include jQuery UI <code>autocomplete</code>. Until we switch to <code>suggest</code>, please use the <a href="http://wordpress.org/extend/plugins/use-google-libraries/">Use Google Libraries</a> plugin - Google\\\'s jQuery UI library includes <code>autocomplete</code>.', 'slt-custom-fields' ) . '</p></div>'; ?>' );
		}
		</script>
	<?php }
	*/
}

/* Database tools
***************************************************************************************/

/**
 * Processes a submission from the database cleanup tool form
 *
 * @since	0.7
 * @return	void
 */
function slt_cf_cleanup_form_process() {
	global $slt_custom_fields, $wpdb;
	$msg = null;
	// Confirmation checked?
	if ( array_key_exists( 'confirmation', $_POST ) ) {
		// Convert single checkbox booleans
		$field_names = slt_cf_get_field_names( array( 'post', 'attachment' ), array( 'checkbox' ) );
		if ( $field_names ) {
			$wpdb->query( slt_cf_convert_boolean_query( $wpdb->postmeta, $field_names, false ) );
			$wpdb->query( slt_cf_convert_boolean_query( $wpdb->postmeta, $field_names, true ) );
		}
		$field_names = slt_cf_get_field_names( array( 'user' ), array( 'checkbox' ) );
		if ( $field_names ) {
			$wpdb->query( slt_cf_convert_boolean_query( $wpdb->usermeta, $field_names, false ) );
			$wpdb->query( slt_cf_convert_boolean_query( $wpdb->usermeta, $field_names, true ) );
		}
		// Remove empty value rows
		$field_names = slt_cf_get_field_names( array( 'post', 'attachment' ) );
		if ( $field_names )
			$wpdb->query( slt_cf_delete_empty_fields_query( $wpdb->postmeta, $field_names ) );
		$field_names = slt_cf_get_field_names( array( 'user' ) );
		if ( $field_names )
			$wpdb->query( slt_cf_delete_empty_fields_query( $wpdb->usermeta, $field_names ) );
		// Remove rows for old fields
		foreach ( array( 'post', 'attachment' ) as $object ) {
			$field_names = slt_cf_get_field_names( array( $object ) );
			if ( $field_names )
				$wpdb->query( slt_cf_delete_old_fields_query( $wpdb->postmeta, $field_names, slt_cf_prefix( $object ) ) );
		}
		$field_names = slt_cf_get_field_names( array( 'user' ) );
		if ( $field_names )
			$wpdb->query( slt_cf_delete_old_fields_query( $wpdb->usermeta, $field_names, slt_cf_prefix( 'user' ) ) );
		// Update database flag?
		if ( $slt_custom_fields['options']['alert-07-cleanup'] )
			slt_cf_update_option( 'alert-07-cleanup', 0 );
		// Set message
		$msg = 'cleanedup';
	} else {
		// Need confirmation
		$msg = 'confirm';
	}
	// Redirect with message
	$redirect_url = admin_url( 'tools.php?page=slt_cf_data_tools' );
	if ( $msg )
		$redirect_url .= '&msg=' . $msg;
	wp_redirect( $redirect_url );
	exit;
}

// Helper functions for building queries
function slt_cf_delete_empty_fields_query( $table, $field_names ) {
	$query = "	DELETE FROM	$table
				WHERE		meta_key 	IN ( '" . implode( "', '", $field_names ) . "' )
				AND			( meta_value = '' OR meta_value IS NULL ) ";
	return $query;
}
function slt_cf_convert_boolean_query( $table, $field_names, $convert_value ) {
	$correct_value = $convert_value ? 1 : 0;
	$query = "	UPDATE	$table
				SET		meta_value			= '$correct_value'
				WHERE	meta_key 			IN ( '" . implode( "', '", $field_names ) . "' ) ";
	if ( $convert_value )
		$query .= " AND LOWER( meta_value ) = 'yes' ";
	else
		$query .= " AND ( LOWER( meta_value ) = 'no' OR meta_value = '' OR meta_value IS NULL ) ";
	return $query;
}
function slt_cf_delete_old_fields_query( $table, $field_names, $prefix ) {
	$query = "	DELETE FROM	$table
				WHERE		meta_key 	NOT IN ( '" . implode( "', '", $field_names ) . "' )
				AND			meta_key	LIKE '" . str_replace( array( '%', '_' ), array( '\%', '\_' ), $prefix ) . "%' ";
	return $query;
}


/**
 * Output the plugin's database tools screen
 *
 * @since	0.7
 * @return	void
 */
function slt_cf_database_tools_screen() {

	// Capability check
	if ( ! current_user_can( 'update_core' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'slt-custom-fields' ) );

	// Initialize
	global $slt_custom_fields;
	$msg = array_key_exists( 'msg', $_GET ) ? $_GET['msg'] : "default";

	?>

	<div class="wrap">

		<div id="icon-tools" class="icon32"><br /></div>
		<h2><?php echo SLT_CF_TITLE . ' ' . __( 'database tools', 'slt-custom-fields' ); ?></h2>

		<?php
		switch ( $msg ) {
			case "cleanedup":
				echo '<div id="message" class="updated"><p>' . __( 'The meta tables have been successfully cleaned up.', 'slt-custom-fields' ) . '</p></div>' . "\n";
				break;
			case "confirm":
				echo '<div id="message" class="error"><p>' . __( 'Please confirm your action by checking the checkbox!', 'slt-custom-fields' ) . '</p></div>' . "\n";
				break;
			default:
				echo '<div id="message" class="error"><p><strong>' . __( 'WARNING!', 'slt-custom-fields' ) . '</strong> ' . __( 'Please <em>back up your database</em> before using any of these tools!', 'slt-custom-fields' ) . '</p></div>' . "\n";
				echo '<p><em>' . __( 'Note that these tools will only affect fields in the <code>postmeta</code> and <code>usermeta</code> tables that have been defined through the Developer\'s Custom Fields plugin, i.e. using the <code>slt_cf_register_box</code> function.', 'slt-custom-fields' ) . '</em></p>' . "\n";
				break;
		}
		?>

		<!-- Clean up meta tables data -->
		<div class="tool-box">
			<h3 class="title"><?php _e( 'Clean up meta tables data', 'slt-custom-fields' ) ?></h3>
			<?php if ( $slt_custom_fields['options']['alert-07-cleanup'] ) { ?>
				<div class="slt-cf-warning"><p><?php _e( 'Note that some of your code may need updating after you use this tool. If your code includes tests for the value of single checkbox fields such as <code>if ( slt_cf_field_value( \'checkbox-field\' ) == \'yes\' ) [...]</code>, please update them. With the new boolean values, such code can now simply be written: <code>if ( slt_cf_field_value( \'checkbox-field\' ) ) [...]</code>. Please read <a href="http://sltaylor.co.uk/blog/developers-custom-fields-0-7-an-important-upgrade" target="_blank">this post</a> for full details.', 'slt-custom-fields' ); ?></p></div>
			<?php } ?>
			<p><?php _e( 'Use this tool to:', 'slt-custom-fields' ); ?></p>
			<ul class="ul-disc">
				<li><?php _e( 'Convert old &quot;yes&quot; / &quot;no&quot; single checkbox boolean values to &quot;1&quot; and &quot;0&quot;', 'slt-custom-fields' ); ?></li>
				<li><?php _e( 'Remove redundant meta table database rows with empty or null values', 'slt-custom-fields' ); ?></li>
				<li><?php _e( 'Remove meta table database rows for fields defined for the Developer\'s Custom Fields plugin in the past, but which are no longer in use', 'slt-custom-fields' ); ?></li>
			</ul>
			<form action="" method="post">
				<?php wp_nonce_field( 'slt-cf-cleanup', '_slt_cf_nonce' ); ?>
				<input type="hidden" name="slt-cf-form" value="cleanup" />
				<p><label for="confirmation"><?php _e( 'I have backed up my data!', 'slt-custom-fields' ); ?> <input type="checkbox" name="confirmation" id="confirmation" value="1" /></label></p>
				<p><input type="submit" name="cleanup-submit" id="cleanup-submit" class="button-primary" value="<?php _e( 'Clean up meta tables', 'slt-custom-fields' ); ?>" /></p>
			</form>
		</div>

	</div>

	<?php

}

