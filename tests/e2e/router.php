<?php
/**
 * Router for PHP's built-in server, so WordPress pretty links work in tests:
 *
 *   php -S 127.0.0.1:8080 -t /path/to/site tests/e2e/router.php
 *
 * @package ClientSideQR
 */

$csqr_root = rtrim( $_SERVER['DOCUMENT_ROOT'], '/' );
$csqr_path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$csqr_file = $csqr_root . $csqr_path;

if ( '/' !== $csqr_path && is_file( $csqr_file ) ) {
	return false;
}
if ( '/' !== $csqr_path && is_dir( $csqr_file ) && is_file( rtrim( $csqr_file, '/' ) . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $csqr_path, '/' ) . '/index.php';
	chdir( rtrim( $csqr_file, '/' ) );
	require rtrim( $csqr_file, '/' ) . '/index.php';
	return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir( $csqr_root );
require $csqr_root . '/index.php';
