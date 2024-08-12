<?php

	// Require API wrapper class in classes folder
	require_once PMPRO_KEAP_DIR . '/classes/class-pmpro-keap-api-wrapper.php';

	/**
	 * Add the options page
	 */
function pmpro_keap_admin_add_page() {
	add_submenu_page( 'pmpro-dashboard', 'Keap', 'Keap', 'manage_options', 'pmpro-keap', 'pmpro_keap_options_page' );
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

	add_settings_section( 'pmpro_keap_section_general', __( 'Add one or more Keap tags when users sign up for your site.', 'pmpro-keap' ), 'pmpro_keap_section_general', 'pmpro_keap_options' );
	add_settings_field( 'pmpro_keap_keap_authorized', __( 'Keap Authorized', 'pmpro-keap' ), 'pmpro_keap_keap_authorized', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_field( 'pmpro_keap_api_key', __( 'Keap API Key', 'pmpro-keap' ), 'pmpro_keap_api_key', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_field( 'pmpro_keap_api_secret', __( 'Keap Secret Key', 'pmpro-keap' ), 'pmpro_keap_secret_key', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_field( 'pmpro_keap_users_tags', __( 'All Users Tags', 'pmpro-keap' ), 'pmpro_keap_users_tags', 'pmpro_keap_options', 'pmpro_keap_section_general' );
	add_settings_section( 'pmpro_keap_section_levels', __( 'Membership Level Tags', 'pmpro-keap' ), 'pmpro_keap_section_levels', 'pmpro_keap_options' );

	if ( isset( $_GET['action'] ) && $_GET['action'] == 'authorize_keap' && wp_verify_nonce( $_GET['pmpro_keap_authorize_nonce'], 'pmpro_keap_authorize_nonce' ) ) {
		$keap    = PMPro_Keap_Api_Wrapper::get_instance();
		$authUrl = $keap->pmpro_keap_get_authorization_url();
		header( "Location: $authUrl" );
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
			update_option( 'pmpro_keap_refresh_token', sanitize_text_field( $tokenResponse['refresh_token'] ) );

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
function pmpro_keap_section_general() {
	// Nothing is needed right now.
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
		<?php
}

	/**
	 * Add User Tags for "All Users" Settings section for the PMPro Keap options page
	 *
	 * @return void
	 */
function pmpro_keap_users_tags() {
	$options  = get_option( 'pmpro_keap_options' );
	$all_tags = pmpro_keap_get_tags();

	// Figure out if array, single value or null.
	if ( isset( $options['users_tags'] ) && is_array( $options['users_tags'] ) ) {
		$selected_tags = $options['users_tags'];
	} elseif ( isset( $options['users_tags'] ) ) {
		// probably saved as comma separated string
		$selected_tags = str_replace( ' ', '', $options['users_tags'] );
		$selected_tags = explode( ',', $selected_tags );
	} else {
		$selected_tags = array();
	}

	// Get tags.
	if ( empty( $all_tags ) ) {
		esc_html_e( 'No tags found.', 'pmpro-keap' );
	} else {
		?>
			<select multiple='yes' name='pmpro_keap_options[users_tags][]'>
		<?php foreach ( $all_tags as $tag_id => $tag ) : ?>
				<option value='<?php echo esc_attr( $tag['id'] ); ?>' 
										  <?php
											if ( in_array( $tag['id'], $selected_tags ) ) :
												?>
					selected='selected'<?php endif; ?>>
					<?php echo esc_html( $tag['name'] ); ?>
				</option>
			<?php endforeach; ?>
			</select>
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
	?>
		<table class="<?php echo esc_attr( 'form-table' ); ?>">
		<?php
		$levels   = pmpro_getAllLevels( true, true );
		$all_tags = pmpro_keap_get_tags();
		if ( empty( $levels ) ) {
			?>
			<p><?php esc_html_e( 'No membership levels found.', 'pmpro-keap' ); ?></p>
			<?php
			return;
		} else {
			?>
			<p><?php esc_html_e( 'For each level below, choose the tags which should be added to the contact when a new user registers or switches levels.', 'pmpro-keap' ); ?></p>
			<?php
		}

		foreach ( $levels as $level ) {
			$tags = pmpro_keap_get_tags_for_level( $level->id );

			if ( empty( $tags ) ) {
				$tags = array();
			}
			?>
					<tr>
						<th>
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
							<select name="pmpro_keap_options[levels][<?php echo esc_attr( $level->id ); ?>][]" multiple="yes">
							<?php
							foreach ( $all_tags as $tag ) {
								?>
										<option value="<?php echo esc_attr( $tag['id'] ); ?>" 
									<?php if ( in_array( $tag['id'], $tags ) ) { ?>
													selected="selected"
												<?php } ?>>
									<?php echo esc_html( $tag ['name'] ); ?>
										</option>
									<?php
							}
							?>
							</select>
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
				<span class="<?php echo esc_attr( 'pmpro_tag pmpro_tag-has_icon pmpro_tag-active pmpro-keap-tag' ); ?>">
			<?php esc_html_e( 'Authorized', 'pmpro-keap' ); ?>
				</span>
			<?php
			return;
	}
	?>
			<span class="<?php echo esc_attr( 'pmpro_tag pmpro_tag-has_icon pmpro_tag-inactive pmpro-keap-tag' ); ?>">
		<?php esc_html_e( 'Not Authorized', 'pmpro-keap' ); ?>
			</span>
		<?php
		$options = get_option( 'pmpro_keap_options' );
		if ( ! empty( $options['api_key'] ) && ! empty( $options['api_secret'] ) ) {
			?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pmpro-keap&action=authorize_keap&pmpro_keap_authorize_nonce=' . wp_create_nonce( 'pmpro_keap_authorize_nonce' ) ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Authorize with Keap', 'pmpro-keap' ); ?>
				<?php
		} else {
			?>
					<div class="notice notice-warning is-dismissible">
						<p><?php esc_html_e( 'Please enter your Keap API key and API secret to continue.', 'pmpro-keap' ); ?></p>
					</div>
				<?php
		}
}
