<?php
/**
 * Uninstall routine for Client-Side QR Code Generator.
 *
 * @package ClientSideQR
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'csqr_settings' );
