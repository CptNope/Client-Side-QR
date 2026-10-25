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

// Added in 4.3.0: saved styles, short links and the per-page QR switch.
delete_option( 'csqr_styles' );
delete_option( 'csqr_flush_rewrite' );

$csqr_links = get_posts(
	array(
		'post_type'      => 'csqr_link',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);
foreach ( $csqr_links as $csqr_link_id ) {
	wp_delete_post( $csqr_link_id, true );
}

delete_post_meta_by_key( '_csqr_page_qr' );
