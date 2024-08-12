<?php
global $msg, $msgt;

// Only admins can access this page.
if ( ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_options' ) ) ) {
	die( esc_html__( 'You do not have permissions to perform this action.', 'p' ) );
}

// Bail if nonce field isn't set.
if ( ! empty( $_REQUEST['savesettings'] ) && ( empty( $_REQUEST['pmpro_keap_nonce'] )
|| ! check_admin_referer( 'savesettings', 'pmpro_keap_nonce' ) ) ) {
	$msg  = -1;
	$msgt = __( 'Are you sure you want to do that? Try again.', 'pmpro-keap' );
	unset( $_REQUEST['savesettings'] );
}

// Save settings.
if ( ! empty( $_REQUEST['savesettings'] ) ) {
	// save options
	$options               = get_option( 'pmpro_keap_options' );
	$options['api_key']    = sanitize_text_field( $_REQUEST['pmpro_keap_options']['api_key'] );
	$options['api_secret'] = sanitize_text_field( $_REQUEST['pmpro_keap_options']['api_secret'] );

	// Reset authentication if the API key or secret are missing.
	if ( empty( $options['api_key'] ) || empty( $options['api_secret'] ) ) {
		delete_option( 'pmpro_keap_access_token' );
		delete_option( 'pmpro_keap_refresh_token' );
	}

	// Save level tags and clear them if unselected or missing.
	if ( ! empty( $_REQUEST['pmpro_keap_options']['levels'] ) ) {
		$submitted_levels = $_REQUEST['pmpro_keap_options']['levels'];

		// Iterate over existing levels to check if they should be updated or deleted
		foreach ( $options['levels'] as $level_id => $level_tags ) {
			if ( isset( $submitted_levels[ $level_id ] ) ) {
				// Update existing levels with new tags
				$temp_level_tags = array();
				foreach ( $submitted_levels[ $level_id ] as $tag ) {
					$temp_level_tags[] = sanitize_text_field( $tag );
				}
				$options['levels'][ $level_id ] = $temp_level_tags;
			} else {
				// If a level is not in the request, it means all items were deleted, so remove the level
				unset( $options['levels'][ $level_id ] );
			}
		}

		// Add any new levels that were submitted
		foreach ( $submitted_levels as $level_id => $level_tags ) {
			if ( ! isset( $options['levels'][ $level_id ] ) ) {
				$temp_level_tags = array();
				foreach ( $level_tags as $tag ) {
					$temp_level_tags[] = sanitize_text_field( $tag );
				}
				$options['levels'][ $level_id ] = $temp_level_tags;
			}
		}
	} else {
		// If no levels are submitted, clear all levels
		$options['levels'] = array();
	}

	// Save the User Tags and delete them if they are missing.
	if ( ! empty( $_REQUEST['pmpro_keap_options']['users_tags'] ) ) {
		$options['users_tags'] = array_map( 'sanitize_text_field', $_REQUEST['pmpro_keap_options']['users_tags'] );
	} else {
		$options['users_tags'] = array();
	}

	// Sav the options.
	update_option( 'pmpro_keap_options', $options );

	$msg  = true;
	$msgt = __( 'Settings saved successfully.', 'pmpro-keap' );

}
	// Include admin header
	require_once PMPRO_DIR . '/adminpages/admin_header.php';
	$options = get_option( 'pmpro_keap_options' );

if ( ! empty( $options['api_key'] ) ) {
	$api_key = $options['api_key'];
}

if ( ! empty( $options['api_secret'] ) ) {
	$api_secret = $options['api_secret'];
}

		// Retrieve stored access token
		$accessToken = get_option( 'pmpro_keap_access_token' );

?>
		 <div class="wrap">
			 <div id="icon-options-general" class="icon32"><br></div>
			<h1><?php esc_html_e( 'Keap Integration Options and Settings', 'pmpro-keap' ); ?></h1>

		<form action="" method="post" enctype="multipart/form-data" >
		<?php
				wp_nonce_field( 'savesettings', 'pmpro_keap_nonce' );
				do_settings_sections( 'pmpro_keap_options' );
		?>
			<p class="submit topborder">
					 <input name="savesettings" type="submit" class="button-primary" value="<?php esc_html_e( 'Save Settings', 'pmpro-keap' ); ?>" />
				 </p>
			 </form>
		 </div>

		<?php
		require_once PMPRO_DIR . '/adminpages/admin_footer.php';
