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

	if ( ! empty( $slt_cf_admin_notices ) ) {
		foreach ( $slt_cf_admin_notices as $notice_slug => $notice_content ) {
			?>
			<div class="notice updated slt-cf-notice"><p><?php echo $notice_content; ?></p><p><a class="slt-cf-dismiss dashicons-before dashicons-dismiss" href="?slt-cf-dismiss=<?php echo sanitize_title( $notice_slug ); ?>"><?php _e( 'Dismiss this notice', SLT_CF_TEXT_DOMAIN ); ?></a></p></div>
			<?php
		}
	}

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

add_action( 'in_plugin_update_message-' . SLT_CF_PRIMARY_FILE_PATH, 'slt_cf_upgrade_warnings' );
/**
 * Check for any plugin update warning notices
 *
 * @since	1.1
 */
function slt_cf_upgrade_warnings() {

	// Get the warnings JSON file and the readme from SVN trunk
	$svn_root_url = 'http://plugins.svn.wordpress.org/developers-custom-fields/trunk/';
	$version_warnings_json = file_get_contents( $svn_root_url . 'slt-cf-version-warnings.json' );
	$readme = file_get_contents( $svn_root_url . 'readme.txt' );
	if ( $version_warnings_json && $readme ) {

		// Parse the current stable tag from readme
		if ( preg_match( '/^Stable tag: ([0-9\.]+)$/m', $readme, $readme_matches ) === 1 ) {
			$stable_version = $readme_matches[1];

			// Loop through the warnings
			$current_warnings = array();
			foreach ( json_decode( $version_warnings_json ) as $warning_version => $warning ) {

				// Add warning if it's for higher than current version, but lower than or equal to stable release version
				if ( version_compare( $warning_version, SLT_CF_VERSION, '>' ) && version_compare( $warning_version, $stable_version, '<=' ) ) {

					// Add the warning
					$current_warnings[] = '<dt style="color:#d54e21;font-size:1.1em;font-weight:bold">Version '. $warning_version .'</dt><dd style="margin-left: 0;">' . $warning . '</dd>';

				}

			}

			// Warnings to output?
			if ( $current_warnings ) {
				echo '<dl>' . implode( "\n", $current_warnings ) . '</dl>';
			}

		}

	}

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


/**
 * Manage 4.2+ term splitting
 *
 * @since	1.1
 */
function slt_cf_split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
	global $slt_custom_fields, $slt_custom_fields_all_boxes, $wpdb;

	// Has this request initialised the fields already?
	// If so, we need to grab the copy made of all the box data
	if ( isset( $slt_custom_fields_all_boxes ) && is_array( $slt_custom_fields_all_boxes ) && count( $slt_custom_fields_all_boxes ) ) {
		$boxes = $slt_custom_fields_all_boxes;
	} else {
		$boxes = $slt_custom_fields['boxes'];
	}

	// Find fields that use options_type 'terms'
	foreach ( $boxes as $box_key => $box ) {
		foreach ( $box['fields'] as $field_key => $field ) {
			// For multiple checkboxes and select fields which have options_type 'terms', using the same taxonomy
			if ( ( $field['type'] == 'checkboxes' || ( $field['type'] == 'select' && $field['multiple'] ) ) && $field['options_type'] == 'terms' && ( $taxonomy == $field['options_query']['taxonomies'] || ( is_array( $field['options_query']['taxonomies'] ) && in_array( $taxonomy, $field['options_query']['taxonomies'] ) ) ) ) {

				// Which meta table to look in?
				$meta_table = $box['type'] == 'user' ? 'usermeta' : 'postmeta';
				$meta_type = $box['type'] == 'user' ? 'user' : 'post';
				$meta_key = slt_cf_field_key( $field['name'], $box['type'] );
				$object_id_column = $meta_type . '_id';

				// Get all records for this field
				$field_records = $wpdb->get_results("
					SELECT		*
					FROM		" . $wpdb->prefix . $meta_table . "
					WHERE		meta_key	= '" . $meta_key . "'
				");

				// Check if any contain the old term ID
				foreach ( $field_records as $field_record ) {
					$new_field_value = null;

					// Check for a serialise single entry
					if ( $field['single'] ) {

						// Cater for serialized arrays
						$field_values = maybe_unserialize( $field_record->meta_value );
						if ( ! is_array( $field_values ) ) {
							// Just in case
							$field_values = (array) $field_values;
						}

						// If there are instance of the old term ID...
						if ( in_array( $old_term_id, $field_values ) ) {

							// Update them
							foreach ( $field_values as &$field_value ) {
								if ( $field_value == $old_term_id ) {
									$field_value = $new_term_id;
								}
							}

							// Pass them through to update the DB
							$new_field_value = $field_values;

						}

						if ( ! is_null( $new_field_value ) ) {
							update_metadata( $meta_type, $field_record->{$object_id_column}, $meta_key, $new_field_value );
						}

					} else {

						// Just a single value record, pass through to update the DB
						if ( $field_record->meta_value == $old_term_id ) {
							$new_field_value = $new_term_id;
						}

						if ( ! is_null( $new_field_value ) ) {
							// Multiple fields, need to pass old value to make sure the right entry is replaced
							update_metadata( $meta_type, $field_record->{$object_id_column}, $meta_key, $new_field_value, $old_term_id );
						}

					}

				}

			}
		}
	}

}