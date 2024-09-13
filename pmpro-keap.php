<?php
/**
 * Plugin Name: Paid Memberships Pro - Keap Integration
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/keap-integration/
 * Description: Create and tag leads and customers in Keap based on membership level.
 * Version: 1.0.2
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com/
 * License: GPL-2.0+
 * Text Domain: pmpro-keap
 * Domain Path: /languages
 */

define( 'PMPRO_KEAP_DIR', dirname( __FILE__ ) );
define( 'PMPRO_KEAP_VERSION', '1.0.2' );

require_once PMPRO_KEAP_DIR . '/includes/settings.php';
require_once PMPRO_KEAP_DIR . '/classes/class-pmpro-keap-api-wrapper.php';

/**
 * Create or Update a Contact in keap given an email address. May include tags and additional fields.
 *
 * @param int $user_id The WP user id.
 * @return int The contact ID in keap.
 * @since 1.0
 */
function pmpro_keap_update_keap_contact( $user_id ) {
	// Bail if pmpro_getMembershipLevelsForUser doesn't exist
	if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
		return;
	}

	$user = get_userdata( $user_id );
	// bail if the user id doesn't bring back a user
	if ( empty( $user ) ) {
		return;
	}

	// Default values.
	$contact_id       = null;
	$new_tags_id      = array();
	$existing_tags_id = array();

	// Get the current levels for the user.
	$current_levels = pmpro_getMembershipLevelsForUser( $user_id );

	// Connect to Keap.
	$keap     = PMPro_Keap_Api_Wrapper::get_instance();
	$response = $keap->pmpro_keap_get_contact_by_email( $user->user_email );

	// We probably need to make sure we can connect and not fatal error.

	// Add the customer to Keap if they don't exist, otherwise update their contact if they do exist.
	if ( $response['count'] == 0 ) {
		$response   = $keap->pmpro_keap_add_contact( $user );
		$contact_id = $response['id'];
	} else {
		$contact_id = $response['contacts'][0]['id'];
		$keap->pmpro_keap_update_contact( $contact_id, $user );
	}

	// Get tags from the options page so we can start applying it to the member.
	$options = get_option( 'pmpro_keap_options' );

	// Collect tags associated with current levels for comparison.
	foreach ( $current_levels as $level ) {
		if ( ! empty( $options['levels'][ $level->id ] ) ) {
			$new_tags_id = array_merge( $new_tags_id, $options['levels'][ $level->id ] );
		}
	}

	$new_tags_id = array_values( array_unique( array_merge( $new_tags_id, $options['users_tags'] ) ) );

	// Fetch current tags from Keap for the contact (if needed, depending on the API design).
	$existing_tags_id = $keap->pmpro_keap_get_tags_id_for_contact( $contact_id );

	// Collect all tags associated with all membership levels.
	$level_related_tags = array();
	foreach ( $options['levels'] as $level_tags ) {
		$level_related_tags = array_merge( $level_related_tags, $level_tags );
	}

	$level_related_tags = array_unique( $level_related_tags );


	// Determine which tags are not related to any level.
	$non_level_tags = array_diff( $existing_tags_id, $level_related_tags );

	// Determine which tags should be removed (tags not in new tags but exist in current tags).
	$tags_to_remove = array_diff( $existing_tags_id, $new_tags_id, $non_level_tags );


	// Remove the old tags from the contact
	if ( ! empty( $tags_to_remove ) ) {
		$keap->pmpro_keap_remove_tags_from_contact( $contact_id, $tags_to_remove );
	}

	// Add the new tags to the contact
	if ( ! empty( $new_tags_id ) ) {
		$keap->pmpro_keap_assign_tags_to_contact( $contact_id, $new_tags_id );
	}

	return $contact_id;
}

/**
 * Add a user to Keap when they register.
 *
 * @param int $user_id The ID of the user that just registered.
 * @return void
 * @since 1.0
 */
function pmpro_keap_user_register( $user_id ) {
	pmpro_keap_update_keap_contact( $user_id );
}
add_action( 'user_register', 'pmpro_keap_user_register', 10, 1 );

/**
 * Subscribe new members (PMPro) when they register
 *
 * @param int $level_id The ID of the level the user is changing to.
 * @param int $user_id The ID of the user that is changing levels.
 * @return void
 * @since 1.0
 */
function pmpro_keap_pmpro_after_change_membership_level( $old_user_levels ) {
	// Get unique user IDs from the old user levels.
	$user_ids = array_unique( array_keys( $old_user_levels ) );

	// Update Keap contact for each user ID.
	foreach ( $user_ids as $user_id ) {
		pmpro_keap_update_keap_contact( $user_id );
	}
}
add_action( 'pmpro_after_all_membership_level_changes', 'pmpro_keap_pmpro_after_change_membership_level', 10, 1 );

/**
 * Update a contact in Keap when a user updates their profile.
 *
 * @since 1.0
 */
function pmpro_keap_profile_update( $user_id, $old_user_data ) {
	pmpro_keap_update_keap_contact( $user_id );
}
add_action( 'profile_update', 'pmpro_keap_profile_update', 10, 2 );

/**
 * Function to add links to the plugin row meta.
 *
 * @since 1.0
 */
function pmpro_keap_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-keap.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/keap-integration/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-keap' ) . '">' . esc_html__( 'Docs', 'pmpro-keap' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-keap' ) . '">' . esc_html__( 'Support', 'pmpro-keap' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpro_keap_plugin_row_meta', 10, 2 );

/**
 * Filter links to add  plugin settings page link.
 *
 * @param array $links The existing links array to filter.
 * @return array The filtered links.
 * @since 1.0
 */
function pmpro_keap_add_action_links( $links ) {

	$new_links = array(
		'<a href="' . admin_url( 'admin.php?page=pmpro-keap' ) . '">' . __( 'Settings', 'pmpro-keap' ) . '</a>',
	);
	return array_merge( $new_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pmpro_keap_add_action_links' );
