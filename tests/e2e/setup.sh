#!/usr/bin/env bash
# Create the pages the browser tests visit, and write their addresses to a
# fixtures file. Run on a throwaway site with the plugin active.
#
#   WP="wp --path=/path/to/site" bash tests/e2e/setup.sh /tmp/csqr-fixtures.json
set -euo pipefail
OUT="${1:-/tmp/csqr-fixtures.json}"
WP="${WP:-wp}"

$WP rewrite structure '/%postname%/' >/dev/null 2>&1 || true
$WP eval '
$make = function ( $title, $blocks, $type = "page" ) {
	$content = "";
	foreach ( $blocks as $b ) {
		$content .= serialize_block( array( "blockName" => $b[0], "attrs" => $b[1], "innerBlocks" => array(), "innerHTML" => "", "innerContent" => array() ) );
	}
	return wp_insert_post( array( "post_type" => $type, "post_status" => "publish", "post_title" => $title, "post_content" => wp_slash( $content ) ) );
};
$gen  = $make( "Make a QR code", array( array( "csqr/generator", array() ) ) );
$wifi = $make( "Guest WiFi", array( array( "csqr/code", array( "type" => "wifi", "ssid" => "Cafe; \"Guest\"", "password" => "p@ss:wo,rd", "showDownloads" => true ) ) ) );
$low  = $make( "Low contrast", array( array( "csqr/code", array( "type" => "url", "url" => "https://example.com/", "qrColorDark" => "#bbbbbb" ) ) ) );
$post = wp_insert_post( array( "post_type" => "post", "post_status" => "publish", "post_title" => "Spring menu", "post_content" => "<p>Our new menu.</p>" ) );
update_post_meta( $post, "_csqr_page_qr", "on" );
$link = wp_insert_post( array( "post_type" => "csqr_link", "post_status" => "publish", "post_title" => "Dinner menu", "post_name" => "menu", "meta_input" => array( "_csqr_destination" => "https://example.com/menu?x=1" ) ) );
// A logo in the Media Library (a plain square image made with GD).
$up  = wp_upload_dir();
$img = imagecreatetruecolor( 120, 120 );
imagefill( $img, 0, 0, imagecolorallocate( $img, 29, 58, 138 ) );
imagefilledellipse( $img, 60, 60, 70, 70, imagecolorallocate( $img, 255, 255, 255 ) );
$file = $up["path"] . "/csqr-test-logo.png";
imagepng( $img, $file );
$att = wp_insert_attachment( array( "post_mime_type" => "image/png", "post_title" => "Logo", "post_status" => "inherit" ), $file );
$logo = wp_get_attachment_url( $att );
update_option( "csqr_styles", array( "brand" => array( "name" => "Brand", "design" => csqr_sanitize_design( array( "qrColorDark" => "#1d3a8a", "logoUrl" => $logo, "logoSize" => 0.22 ) ) ) ) );
$styled = $make( "Styled", array( array( "csqr/code", array( "type" => "url", "url" => "https://example.com/brand", "styleId" => "brand" ) ) ) );
$shorts = wp_insert_post( array( "post_type" => "page", "post_status" => "publish", "post_title" => "Shortcodes", "post_content" => "[qr_code url=\"https://example.com/short\" caption=\"From a shortcode\"]\n\n[qr_code link=\"menu\"]\n\n[client_side_qr qrColorDark=\"#123456\" enableWifi=\"false\"]" ) );
flush_rewrite_rules( false );
echo wp_json_encode( array(
	"generator"   => get_permalink( $gen ),
	"wifi"        => get_permalink( $wifi ),
	"wifiPayload" => csqr_build_payload( "wifi", array( "ssid" => "Cafe; \"Guest\"", "password" => "p@ss:wo,rd" ) ),
	"lowContrast" => get_permalink( $low ),
	"pageQr"      => get_permalink( $post ),
	"pageQrId"    => $post,
	"link"        => csqr_link_url( $link ),
	"linkId"      => $link,
	"linkDest"    => "https://example.com/menu?x=1",
	"styled"      => get_permalink( $styled ),
	"logo"        => $logo,
	"shortcodes"  => get_permalink( $shorts ),
) );
' > "$OUT"
echo "Fixtures: $(cat "$OUT")"
