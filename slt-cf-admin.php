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

	/*
	// jQuery UI autocomplete for Gmaps
	$gmap_fields = slt_cf_get_field_names( array(), array( 'gmap' ) );
	if ( ! empty( $gmap_fields ) ) { ?>
		<script type="text/javascript">
		if ( ! jQuery().autocomplete ) {
			document.write( '<?php echo '<div id="message" class="updated"><p>' . __( 'You have at least one <code>gmap</code> field defined through Developer\\\'s Custom Fields, but your WordPress installation doesn\\\'t include jQuery UI <code>autocomplete</code>. Until we switch to <code>suggest</code>, please use the <a href="http://wordpress.org/extend/plugins/use-google-libraries/">Use Google Libraries</a> plugin - Google\\\'s jQuery UI library includes <code>autocomplete</code>.', SLT_CF_TEXT_DOMAIN ) . '</p></div>'; ?>' );
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
	global $wpdb;
	$msg = null;

	// Confirmation checked?
	if ( array_key_exists( 'confirmation', $_POST ) ) {

		// Remove rows for old post and attachment fields
		$field_names = slt_cf_get_field_names( array( 'post', 'attachment' ) );
		if ( $field_names ) {
			$wpdb->query( slt_cf_delete_old_fields_query( $wpdb->postmeta, $field_names, array( slt_cf_prefix( 'post' ), slt_cf_prefix( 'attachment' ) ) ) );
		}

		// Remove rows for old user fields
		$field_names = slt_cf_get_field_names( array( 'user' ) );
		if ( $field_names ) {
			$wpdb->query( slt_cf_delete_old_fields_query( $wpdb->usermeta, $field_names, slt_cf_prefix( 'user' ) ) );
		}

		// Set message
		$msg = 'cleanedup';

	} else {

		// Need confirmation
		$msg = 'confirm';

	}

	// Redirect with message
	$redirect_url = admin_url( 'tools.php?page=slt_cf_data_tools' );
	if ( $msg ) {
		$redirect_url .= '&msg=' . $msg;
	}
	wp_redirect( $redirect_url );
	exit;

}

// Helper functions for building queries
function slt_cf_delete_old_fields_query( $table, $field_names, $prefixes ) {
	if ( ! is_array( $prefixes ) ) {
		$prefixes = (array) $prefixes;
	}
	$query = "	DELETE FROM	$table
				WHERE		meta_key 	NOT IN ( '" . implode( "', '", $field_names ) . "' ) ";
	$prefixes_used = array();
	$prefix_clauses = array();
	foreach ( $prefixes as $prefix ) {
		if ( ! in_array( $prefix, $prefixes_used ) ) {
			$prefix_clauses[] = " meta_key LIKE '" . str_replace( array( '%', '_' ), array( '\%', '\_' ), $prefix ) . "%' ";
		}
		$prefixes_used[] = $prefix;
	}
	$query .= " AND ( " . implode( ' OR ', $prefix_clauses ) . " ) ";
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
		wp_die( __( 'You do not have sufficient permissions to access this page.', SLT_CF_TEXT_DOMAIN ) );

	// Initialize
	global $slt_custom_fields;
	$msg = array_key_exists( 'msg', $_GET ) ? $_GET['msg'] : "default";

	?>

	<div class="wrap">

		<div id="icon-tools" class="icon32"><br /></div>
		<h2><?php echo SLT_CF_TITLE . ' ' . __( 'database tools', SLT_CF_TEXT_DOMAIN ); ?></h2>

		<?php
		switch ( $msg ) {
			case "cleanedup":
				echo '<div id="message" class="updated"><p>' . __( 'The meta tables have been successfully cleaned up.', SLT_CF_TEXT_DOMAIN ) . '</p></div>' . "\n";
				break;
			case "confirm":
				echo '<div id="message" class="error"><p>' . __( 'Please confirm your action by checking the checkbox!', SLT_CF_TEXT_DOMAIN ) . '</p></div>' . "\n";
				break;
			default:
				echo '<div id="message" class="error"><p><strong>' . __( 'WARNING!', SLT_CF_TEXT_DOMAIN ) . '</strong> ' . __( 'Please <em>back up your database</em> before using any of these tools!', SLT_CF_TEXT_DOMAIN ) . '</p></div>' . "\n";
				echo '<p><em>' . __( 'Note that these tools will only affect fields in the <code>postmeta</code> and <code>usermeta</code> tables that have been defined through the Developer\'s Custom Fields plugin, i.e. using the <code>slt_cf_register_box</code> function.', SLT_CF_TEXT_DOMAIN ) . '</em></p>' . "\n";
				break;
		}
		?>

		<!-- Clean up meta tables data -->
		<div class="tool-box">

			<h3 class="title"><?php _e( 'Clean up meta tables data', SLT_CF_TEXT_DOMAIN ) ?></h3>

			<p><?php _e( 'Use this tool to:', SLT_CF_TEXT_DOMAIN ); ?></p>

			<ul class="ul-disc">
				<li><?php _e( 'Remove meta table database rows for fields defined for the Developer\'s Custom Fields plugin in the past, but which are no longer in use', SLT_CF_TEXT_DOMAIN ); ?></li>
			</ul>

			<form action="" method="post">
				<?php wp_nonce_field( 'slt-cf-cleanup', '_slt_cf_nonce' ); ?>
				<input type="hidden" name="slt-cf-form" value="cleanup" />
				<p><label for="confirmation"><input type="checkbox" name="confirmation" id="confirmation" value="1" />&nbsp; <?php _e( 'Yes, I\'ve backed up my data!', SLT_CF_TEXT_DOMAIN ); ?></label></p>
				<p><input type="submit" name="cleanup-submit" id="cleanup-submit" class="button-primary" value="<?php _e( 'Clean up meta tables', SLT_CF_TEXT_DOMAIN ); ?>" /></p>
			</form>

		</div>

	</div>

	<?php

}

