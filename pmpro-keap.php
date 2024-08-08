<?php
/**
 * Plugin Name: Paid Memberships Pro - Keap Add On
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-keap-integration/
 * Description: Sync your WordPress users and members with Keap contacts.
 * Version: 1.0
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com/
 * License: GPL-2.0+
 * Text Domain: pmpro-keap
 * Domain Path: /languages
 */

define( 'PMPRO_KEAP_DIR', dirname( __FILE__ ) );
define( 'PMPRO_KEAP_VERSION', '1.0' );

require_once PMPRO_KEAP_DIR . '/includes/settings.php';
require_once PMPRO_KEAP_DIR . '/classes/class-pmpro-keap-api-wrapper.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0
 */
function pmpro_keap_init() {
	add_action( 'user_register', 'pmpro_keap_user_register', 10, 1 );
	add_action( 'pmpro_after_change_membership_level', 'pmpro_keap_pmpro_after_change_membership_level', 10, 2 );
	add_action( 'profile_update', 'pmpro_keap_profile_update', 10, 2 );
}
add_action( 'init', 'pmpro_keap_init' );

/**
 * Enqueue the CSS assets for the PMPro Keap settings page.
 *
 * @since 1.0
 */
function pmpro_keap_enqueue_css_assets( $hook ) {
	// Only include on the PMPro Keap settings page
	if ( ! empty( $_REQUEST['page'] ) && $_REQUEST['page'] == 'pmpro-keap' ) {
		wp_enqueue_style( 'pmpro_keap', plugins_url( 'css/admin.css', __FILE__ ), '', PMPRO_KEAP_VERSION, 'screen' );
	}
}
add_action( 'admin_enqueue_scripts', 'pmpro_keap_enqueue_css_assets' );

/**
 * Create or Update a Contact in keap given an email address. May include tags and additional fields.
 *
 * @param WP_User $user The user object to add or update in keap.
 * @param int $level_id The ID of the level the user is changing to.
 * @param bool $skip_level_check If true, the function will not check for membership levels.
 * @return int The contact ID in keap.
 * @since TBD
 */
function pmpro_keap_update_keap_contact( $user, $level_id = NULL, $skip_level_check = false ) {
	// Bail if pmpro_getMembershipLevelsForUser doesn't exist
	if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
		return;
	}

	// Default values.
	$contact_id = NULL;
	$new_tags_id = array();
	$old_tags_id = array();

	// Get the current levels for the user.
	$current_levels = pmpro_getMembershipLevelsForUser( $user->ID );

	// If $level_id is specified, we're only dealing with that level.
	// Otherwise, we get all levels.
	if ( $level_id != NULL ) {
		$levels = array( pmpro_getLevel( $level_id ) );
	} else {
		$levels = $current_levels;
	}


	// Connect to Keap. 
	$keap = PMPro_Keap_Api_Wrapper::get_instance();
    $response = $keap->pmpro_keap_get_contact_by_email( $user->user_email );

	/// We probably need to make sure we can connect and not fatal error.

	// Add the customer to Keap if they don't exist, otherwise update their contact if they do exist.
	if (  $response[ 'count' ] == 0 ) {
		$response = $keap->pmpro_keap_add_contact( $user );
		$contact_id = $response[ 'id' ];
	} else {
		$contact_id = $response[ 'contacts' ][ 0 ][ 'id'];
		$keap->pmpro_keap_update_contact( $contact_id, $user );
	}

	// Get tags from the options page so we can start applying it to the member.
	$options = get_option( 'pmpro_keap_options' );

	// Collect tags associated with current levels for comparison.
	foreach ( $current_levels as $level ) {
		if ( !empty( $options[ 'levels' ][ $level->id ] ) ) {
			$old_tags_id = array_merge( $old_tags_id, $options[ 'levels' ][ $level->id ] );
		}
	}

	// The $skip_level_check will always run when hooking into PMPro actions.
	if ( ! $skip_level_check ) {
		foreach ( $levels as $level ) {
			if ( !empty( $options[ 'levels' ][ $level->id ] ) ) {
				// Append to the new_tags_id array
				$new_tags_id = array_merge( $new_tags_id, $options[ 'levels' ][ $level->id ] );
			}
		}
	}


	// Find tags that need to be removed (present in old but not in new)
	$tags_to_remove = array_diff( $old_tags_id, $new_tags_id );

	// Remove the old tags from the contact
	if ( !empty( $tags_to_remove ) ) {
		$keap->pmpro_keap_remove_tags_from_contact( $contact_id, $tags_to_remove );
	}

	// Merge in the user tags if they exist and get unique values.
	$new_tags_id = array_unique( array_merge( $new_tags_id, $options[ 'users_tags' ] ) );
	if ( !empty( $new_tags_id ) ) {
		$keap->pmpro_keap_assign_tags_to_contact( $contact_id, $new_tags_id );
	}

	// If no levels are present, remove all tags associated with levels.
	if ( empty( $levels ) ) {
		$all_level_tags = array();
		foreach ( $options[ 'levels' ] as $tags ) {
			$all_level_tags = array_merge( $all_level_tags, $tags );
		}
		if ( !empty( $all_level_tags ) ) {
			$keap->pmpro_keap_remove_tags_from_contact( $contact_id, array_unique( $all_level_tags ) );
		}
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
	$user = get_userdata( $user_id );
	pmpro_keap_update_keap_contact( $user, null, true );
}

/**
 * Subscribe new members (PMPro) when they register
 *
 * @param int $level_id The ID of the level the user is changing to.
 * @param int $user_id The ID of the user that is changing levels.
 * @return void
 * @since 1.0
 */
function pmpro_keap_pmpro_after_change_membership_level( $level_id, $user_id ) {
	$user = get_userdata( $user_id );
	pmpro_keap_update_keap_contact( $user, $level_id );
}

/**
 * Update a contact in Keap when a user updates their profile.
 * @since 1.0
 */
function pmpro_keap_profile_update( $user_id, $old_user_data ) {
	$user = get_userdata( $user_id );
	pmpro_keap_update_keap_contact( $user, null, true );
}

/**
 * Function to add links to the plugin row meta.
 * @since 1.0
 */
function pmpro_keap_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-keap.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/pmpro-keap-integration/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-keap' ) . '">' . esc_html__( 'Docs', 'pmpro-keap' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-keap' ) . '">' . esc_html__( 'Support', 'pmpro-keap' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpro_keap_plugin_row_meta', 10, 2 );
