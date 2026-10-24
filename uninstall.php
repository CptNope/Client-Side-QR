<?php
/**
 * Uninstall routine for Anderson QR Code Generator.
 *
 * @package ClientSideQR
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'csqr_settings' );

// Left by the GitHub release notices in builds before 4.2.0.
delete_site_transient( 'csqr_github_release_data' );
wp_clear_scheduled_hook( 'csqr_check_github_release_event' );
