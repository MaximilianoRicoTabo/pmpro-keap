<?php

	// Require API wrapper class in classes folder
	require_once PMPRO_KEAP_DIR . '/classes/class-pmpro-keap-api-wrapper.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0
 */
function pmpro_keap_admin_add_page() {
	add_submenu_page( 'pmpro-dashboard', __( 'Keap Integration', 'pmpro-keap' ), __( 'Keap', 'pmpro-keap' ), 'manage_options', 'pmpro-keap', 'pmpro_keap_options_page' );
}
add_action( 'admin_menu', 'pmpro_keap_admin_add_page' );

/**
 * Get settings options for PMPro Keap and and render the markup to save the options
 *
 * @return array $options
 */
function pmpro_keap_options_page() {
	require_once PMPRO_KEAP_DIR . '/adminpages/settings.php';
}

/**
 * Register setting page for PMPro Keap
 *
 * @return void
 * @since 1.0
 */
function pmpro_keap_admin_init() {

	register_setting( 'pmpro_keap_options', 'pmpro_keap_options', 'pmpro_keap_options_validate' );

	add_settings_section( 'pmpro_keap_section_general', '', 'pmpro_keap_section_general', 'pmpro_keap_options', array( 'before_section' => '<div class="pmpro_section">', 'after_section' => '</div></div>' ) );
	add_settings_field( 'pmpro_keap_keap_authorized', __( 'Keap Integration Status', 'pmpro-keap' ), 'pmpro_keap_keap_authorized', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_field( 'pmpro_keap_api_key', __( 'Keap API Key', 'pmpro-keap' ), 'pmpro_keap_api_key', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_field( 'pmpro_keap_api_secret', __( 'Keap Secret Key', 'pmpro-keap' ), 'pmpro_keap_secret_key', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_field( 'pmpro_keap_users_tags', __( 'All Users Tags', 'pmpro-keap' ), 'pmpro_keap_users_tags', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_section( 'pmpro_keap_section_levels', '', 'pmpro_keap_section_levels', 'pmpro_keap_options' );

	if ( isset( $_GET['action'] ) && $_GET['action'] == 'authorize_keap' &&
		wp_verify_nonce( sanitize_key( $_GET['pmpro_keap_authorize_nonce'] ), 'pmpro_keap_authorize_nonce' ) ) {
		$keap    = PMPro_Keap_Api_Wrapper::get_instance();
		wp_redirect( $keap->pmpro_keap_get_authorization_url() );
		exit;
	}

	// Handle the OAuth callback, the 'code' parameter is used as a nonce response from Keap and will be used to request the access token.
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'pmpro-keap' && isset( $_GET['code'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$keap               = PMPro_Keap_Api_Wrapper::get_instance();
		$authorization_code = sanitize_text_field( $_GET['code'] );
		$token_response     = $keap->pmpro_keap_request_token( $authorization_code );

		if ( isset( $token_response['access_token'] ) ) {
			// sanitize data and save options
			update_option( 'pmpro_keap_access_token', sanitize_text_field( $token_response['access_token'] ) );
			update_option( 'pmpro_keap_refresh_token', sanitize_text_field( $token_response['refresh_token'] ) );

		} else {
			// Handle token request error
			echo '<div class="error"><p>' . sprintf( esc_html__( 'Error requesting access token: %s', 'pmpro-keap' ), $token_response['error_description'] ) . '</p></div>';
			return;
		}

		// Redirect to the settings page after processing
		wp_redirect( admin_url( 'admin.php?page=pmpro-keap' ) );
		exit;
	}
}
add_action( 'admin_init', 'pmpro_keap_admin_init' );

/**
 * Add the settings title section for the PMPro Keap options page
 *
 * @since 1.0
 */
function pmpro_keap_section_general() { ?>
	<div id="pmpro-keap-general-settings" class="pmpro_section_toggle" data-visibility="shown" data-activated="true">
		<button class="pmpro_section-toggle-button" type="button" aria-expanded="false">
			<span class="dashicons dashicons-arrow-up-alt2"></span>
			<?php esc_html_e( 'General Settings', 'pmpro-keap' ); ?>
		</button>
	</div>
	<div class="pmpro_section_inside">
		<p><?php esc_html_e( 'Add your API keys below to connect your Keap account to this membership site.', 'pmpro-keap' ); ?></p>
	<?php
}

/**
 * Add the API Key settings section for the PMPro Keap options page
 *
 * @return void
 * @since 1.0
 */
function pmpro_keap_api_key() {
	$options = get_option( 'pmpro_keap_options' );
	$api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';
	?>
	<input id='pmpro_keap_api_key' name='pmpro_keap_options[api_key]' size='80' type='password' autocomplete='off' value='<?php echo esc_attr( $api_key ); ?>' />
	<?php
}

/**
 * Add the Secret Key settings section for the PMPro Keap options page
 *
 * @return void
 * @since 1.0
 */
function pmpro_keap_secret_key() {
	$options    = get_option( 'pmpro_keap_options' );
	$api_secret = ! empty( $options['api_secret'] ) ? $options['api_secret'] : '';
	?>
	<input id='pmpro_keap_api_secret' name='pmpro_keap_options[api_secret]' size='80' type='password' value='<?php echo esc_attr( $api_secret ); ?>' />
	<p class="description">
		<?php
			printf( esc_html__( 'You may obtain your Keap API and Secret key from the %s.', 'pmpro-keap' ), '<a href="' . esc_url( 'https://keys.developer.keap.com/' ) .  '" target="_blank">' . esc_html__( 'Keap Developer Portal', 'pmpro-keap' ) . '</a>' );
		?>
	</p>
	<?php
}


/**
 * Add the Users Tags settings section for the PMPro Keap options page
 *
 * @return void
 * @since 1.0
 */
function pmpro_keap_users_tags() {
	$options  = get_option( 'pmpro_keap_options' );
	$all_tags = pmpro_keap_get_tags();

	// Determine the selected tags.
	if ( isset( $options['users_tags'] ) && is_array( $options['users_tags'] ) ) {
		$selected_tags = $options['users_tags'];
	}

	// If no tags are selected, make it an array.
	if ( empty( $selected_tags ) ) {
		$selected_tags = array();
	}

	// Display checkboxes.
	if ( empty( $all_tags ) ) {
		esc_html_e( 'No tags found.', 'pmpro-keap' );
	} else {
		// Build the selectors for the checkbox list based on number of levels.
		$classes = array();
		$classes[] = "pmpro_checkbox_box";
		if ( count( $all_tags ) > 5 ) {
			$classes[] = "pmpro_scrollable";
		}
		$class = implode( ' ', array_unique( $classes ) );
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
		<?php
		foreach ( $all_tags as $tag_id => $tag ) {
			$checked = in_array( $tag['id'], $selected_tags ) ? 'checked' : '';
			?>
			<div class="pmpro_clickable">
				<input type="checkbox" id="pmpro_keap_options_users_tags_<?php echo esc_attr( $tag['id'] ); ?>" name="pmpro_keap_options[users_tags][]" value="<?php echo esc_attr( $tag['id'] ); ?>" <?php echo $checked; ?> />
				<label for="pmpro_keap_options_users_tags_<?php echo esc_attr( $tag['id'] ); ?>"><?php echo esc_html( $tag['name'] ); ?></label>
			</div>
			<?php
			}
		?>
		</div>
		<p class="description"><?php esc_html_e( 'Select the tags that should be added to all users.', 'pmpro-keap' ); ?></p>
		<?php
	}
}

/**
 * Add the Users Tags settings section for the PMPro Keap options page
 *
 * @return void
 * @since 1.0
 */
function pmpro_keap_section_levels() {
	$accessToken = get_option( 'pmpro_keap_access_token' );
	if ( $accessToken ) {
		$section_visibility = 'shown';
		$section_activated = 'true';
	} else {
		$section_visibility = 'hidden';
		$section_activated = 'false';
	}
	?>
	<div class="pmpro_section">
		<div id="pmpro-keap-level-settings" class="pmpro_section_toggle" data-visibility="<?php echo esc_attr( $section_visibility ); ?>" data-activated="<?php echo esc_attr( $section_activated ); ?>">
			<button class="pmpro_section-toggle-button" type="button" aria-expanded="false">
				<span class="dashicons dashicons-arrow-up-alt2"></span>
				<?php esc_html_e( 'Membership Level Tags', 'pmpro-keap' ); ?>
			</button>
		</div>
		<div class="pmpro_section_inside"<?php echo $section_visibility === 'hidden' ? 'style="display: none"' : ''; ?>>
		<?php
		$levels   = pmpro_getAllLevels( true, true );
		$all_tags = pmpro_keap_get_tags();

		if ( empty( $levels ) ) {
			?>
			<p><?php esc_html_e( 'No membership levels found.', 'pmpro-keap' ); ?></p>
			<?php
			return;
		} ?>

		<p><?php esc_html_e( 'For each level below, choose the tags which should be added to the contact when a new user registers or switches levels.', 'pmpro-keap' ); ?></p>
		<table class="form-table">
			<tbody>
			<?php
				// Build the selectors for the checkbox list based on number of levels.
				$classes = array();
				$classes[] = "pmpro_checkbox_box";
				if ( count( $all_tags ) > 5 ) {
					$classes[] = "pmpro_scrollable";
				}
				$class = implode( ' ', array_unique( $classes ) );

				// Loop through each level and display the tags.
				foreach ( $levels as $level ) {
					$tags = pmpro_keap_get_tags_for_level( $level->id );
					if ( empty( $tags ) ) {
						$tags = array();
					}
					?>
					<tr>
						<th scope="row">
							<?php echo esc_html( $level->name ); ?>
						</th>
						<td>
							<?php
							if ( empty( $all_tags ) ) {
								?>
								<p><?php esc_html_e( 'No tags found.', 'pmpro-keap' ); ?></p>
								<?php
							} else {
								?>
								<div class="<?php echo esc_attr( $class ); ?>">
								<?php
									foreach ( $all_tags as $tag ) {
										$checked = in_array( $tag['id'], $tags ) ? 'checked' : '';
										?>
										<div class="pmpro_clickable">
											<input type="checkbox" id="pmpro_keap_options_levels_<?php echo esc_attr( $level->id ); ?>_<?php echo esc_attr( $tag['id'] ); ?>" name="pmpro_keap_options[levels][<?php echo esc_attr( $level->id ); ?>][]" value="<?php echo esc_attr( $tag['id'] ); ?>" <?php echo $checked; ?> />
											<label for="pmpro_keap_options_levels_<?php echo esc_attr( $level->id ); ?>_<?php echo esc_attr( $tag['id'] ); ?>"><?php echo esc_html( $tag['name'] ); ?></label>
										</div>
										<?php
									}
								?>
								</div>
								<?php
							}
							?>
						</td>
					</tr>
					<?php
				}
			?>
			</tbody>
		</table>
		</div>
	</div>
	<?php
}


/**
 * Get the tags for a specific level
 *
 * @param int $level_id The level ID
 * @return array The tags for the level
 * @since 1.0
 */
function pmpro_keap_get_tags_for_level( $level_id ) {
	$options = get_option( 'pmpro_keap_options' );
	return $options['levels'][ $level_id ] ?? array();
}

/**
 * Get all Keap tags
 *
 * @return array The tags.
 * @since 1.0
 */
function pmpro_keap_get_tags() {
	$keap = PMPro_Keap_Api_Wrapper::get_instance();
	$tags = $keap->pmpro_keap_get_tags();
	return ! empty( $tags['tags'] ) ? $tags['tags'] : array();
}

/**
 * Show either or not if Keap is authorized or not.
 *
 * @since 1.0
 */
function pmpro_keap_keap_authorized() {
	$accessToken = get_option( 'pmpro_keap_access_token' );
	if ( $accessToken ) {
		?>
		<span class="pmpro_tag pmpro_tag-has_icon pmpro_tag-active">
			<?php esc_html_e( 'Authorized', 'pmpro-keap' ); ?>
		</span>
		<?php
		return;
	}
	?>
	<span class="pmpro_tag pmpro_tag-has_icon pmpro_tag-inactive">
		<?php esc_html_e( 'Not Authorized', 'pmpro-keap' ); ?>
	</span>
	<?php
	$options = get_option( 'pmpro_keap_options' );
	if ( ! empty( $options['api_key'] ) && ! empty( $options['api_secret'] ) ) {
		?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=pmpro-keap&action=authorize_keap&pmpro_keap_authorize_nonce=' . wp_create_nonce( 'pmpro_keap_authorize_nonce' ) ) ); ?>" class="button button-action"><?php esc_html_e( 'Authorize with Keap', 'pmpro-keap' ); ?></a>
		<?php
	} else {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Please enter your Keap API Key and Secret Key to continue.', 'pmpro-keap' ); ?></p>
		</div>
		<?php
	}
}
