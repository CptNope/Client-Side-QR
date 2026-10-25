<?php
/**
 * The "QR code" block: a fixed code the site owner sets up (this page, a
 * short link, guest WiFi, a contact card, a review link…), shown to visitors
 * as a ready-to-scan card. The interactive generator stays in csqr/generator.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block attributes.
 *
 * @return array<string, array<string, mixed>>
 */
function csqr_code_block_attributes() {
	$string = array( 'type' => 'string' );
	$attrs  = array(
		'type'            => array(
			'type'    => 'string',
			'default' => 'page',
		),
		'caption'         => array(
			'type'    => 'string',
			'default' => '',
		),
		'showDownloads'   => array(
			'type'    => 'boolean',
			'default' => false,
		),
		'showWifiDetails' => array(
			'type'    => 'boolean',
			'default' => true,
		),
		'qrSize'          => array(
			'type'    => 'number',
			'default' => 200,
		),
		'cardAlign'       => array(
			'type'    => 'string',
			'default' => 'center',
		),
		'linkId'          => array( 'type' => 'number' ),
		'hidden'          => array( 'type' => 'boolean' ),
		'qrGradient'      => array( 'type' => 'boolean' ),
		'transparent'     => array( 'type' => 'boolean' ),
		'logoSize'        => array( 'type' => 'number' ),
		'logoId'          => array( 'type' => 'number' ),
	);
	foreach ( array( 'url', 'text', 'ssid', 'password', 'encryption', 'firstName', 'lastName', 'company', 'title', 'phone', 'email', 'website', 'address', 'subject', 'body', 'message', 'styleId', 'qrColorDark', 'qrColorDark2', 'qrColorLight', 'qrDotStyle', 'qrEyeStyle', 'qrEyeColor', 'qrCorrectLevel', 'logoUrl' ) as $key ) {
		$attrs[ $key ] = $string;
	}
	return $attrs;
}

/**
 * Register the block and its editor script.
 *
 * @return void
 */
function csqr_register_code_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	wp_register_script(
		'csqr-code-block',
		CSQR_URL . 'assets/qr-code-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-data', 'wp-api-fetch', 'csqr-editor' ),
		CSQR_VERSION,
		true
	);
	wp_set_script_translations( 'csqr-code-block', 'anderson-qr-code-generator' );

	register_block_type(
		'csqr/code',
		array(
			'api_version'     => 3,
			'editor_script'   => 'csqr-code-block',
			'editor_style'    => 'csqr-style',
			'style'           => 'csqr-style',
			'attributes'      => csqr_code_block_attributes(),
			'render_callback' => 'csqr_render_code_block',
			'supports'        => array(
				'html'   => false,
				'align'  => array( 'left', 'center', 'right' ),
				'anchor' => true,
			),
		)
	);
}
add_action( 'init', 'csqr_register_code_block' );

/**
 * Editor data shared by both blocks (defaults, styles, short links).
 *
 * @return void
 */
function csqr_editor_config() {
	$config = array(
		'defaults'    => csqr_card_design( array() ),
		'settings'    => csqr_get_settings(),
		'styles'      => csqr_styles_for_js(),
		'links'       => csqr_links_for_js(),
		'newLinkUrl'  => admin_url( 'post-new.php?post_type=' . CSQR_LINK_TYPE ),
		'stylesUrl'   => admin_url( 'admin.php?page=csqr-styles' ),
		'previewUrl'  => home_url( '/' ),
		'canManage'   => current_user_can( 'manage_options' ),
	);
	wp_add_inline_script( 'csqr-ui', 'window.csqrEditorConfig = ' . wp_json_encode( $config ) . ';', 'before' );
}
add_action( 'enqueue_block_editor_assets', 'csqr_editor_config' );

/**
 * Captions used when a block has none.
 *
 * @param string $type Block type setting.
 * @return string
 */
function csqr_default_caption( $type ) {
	$captions = array(
		'page'   => __( 'Scan to open this page on your phone', 'anderson-qr-code-generator' ),
		'wifi'   => __( 'Scan to join our WiFi', 'anderson-qr-code-generator' ),
		'vcard'  => __( 'Scan to save my contact details', 'anderson-qr-code-generator' ),
		'review' => __( 'Enjoyed your visit? Scan to leave us a review', 'anderson-qr-code-generator' ),
		'email'  => __( 'Scan to email us', 'anderson-qr-code-generator' ),
		'sms'    => __( 'Scan to text us', 'anderson-qr-code-generator' ),
	);
	return isset( $captions[ $type ] ) ? $captions[ $type ] : '';
}

/**
 * Render the QR code block.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string
 */
function csqr_render_code_block( $attributes ) {
	$type = isset( $attributes['type'] ) ? sanitize_key( $attributes['type'] ) : 'page';
	$text = csqr_build_payload( $type, $attributes, get_the_ID() );
	if ( '' === $text ) {
		return '';
	}

	$design           = csqr_card_design( $attributes );
	$design['qrSize'] = isset( $attributes['qrSize'] ) ? max( 96, min( 600, (int) $attributes['qrSize'] ) ) : 200;

	$caption = isset( $attributes['caption'] ) && '' !== trim( (string) $attributes['caption'] ) ? esc_html( $attributes['caption'] ) : esc_html( csqr_default_caption( $type ) );
	// The network name and password sit in <code> so WordPress doesn't turn
	// their straight quotes curly: people need to type them exactly.
	if ( 'wifi' === $type && ! empty( $attributes['showWifiDetails'] ) ) {
		$details = '<span class="csqr-card__detail">' . esc_html__( 'Network', 'anderson-qr-code-generator' ) . ' <code class="csqr-card__value">' . esc_html( (string) $attributes['ssid'] ) . '</code></span>';
		if ( ! empty( $attributes['password'] ) && 'nopass' !== ( $attributes['encryption'] ?? '' ) ) {
			$details .= '<span class="csqr-card__detail">' . esc_html__( 'Password', 'anderson-qr-code-generator' ) . ' <code class="csqr-card__value">' . esc_html( (string) $attributes['password'] ) . '</code></span>';
		}
		$caption .= ( '' !== $caption ? '<br />' : '' ) . $details;
	}

	$labels = array(
		'page'   => __( 'QR code linking to this page', 'anderson-qr-code-generator' ),
		'link'   => __( 'QR code', 'anderson-qr-code-generator' ),
		'wifi'   => __( 'QR code to join the WiFi network', 'anderson-qr-code-generator' ),
		'vcard'  => __( 'QR code with contact details', 'anderson-qr-code-generator' ),
		'review' => __( 'QR code linking to our review page', 'anderson-qr-code-generator' ),
	);

	$align = $attributes['align'] ?? ( $attributes['cardAlign'] ?? 'center' );
	$html  = csqr_render_card(
		$text,
		$design,
		array(
			'caption'   => $caption,
			'label'     => isset( $labels[ $type ] ) ? $labels[ $type ] : __( 'QR code', 'anderson-qr-code-generator' ),
			'name'      => 'qr-' . $type,
			'downloads' => ! empty( $attributes['showDownloads'] ),
			'align'     => $align,
		)
	);

	$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes( array( 'class' => 'csqr-code-block' ) ) : 'class="csqr-code-block"';
	return '<div ' . $wrapper . '>' . $html . '</div>';
}

/**
 * Shortcode for a fixed code: [qr_code type="page"], [qr_code link="menu"],
 * [qr_code url="https://…" caption="…" style="brand"].
 *
 * @param array<string, mixed>|string $atts Attributes.
 * @return string
 */
function csqr_code_shortcode( $atts ) {
	$atts = csqr_shortcode_attributes( $atts );
	$map  = array(
		'downloads'   => 'showDownloads',
		'size'        => 'qrSize',
		'firstname'   => 'firstName',
		'lastname'    => 'lastName',
		'wifidetails' => 'showWifiDetails',
	);
	foreach ( $map as $from => $to ) {
		if ( isset( $atts[ $from ] ) ) {
			$atts[ $to ] = $atts[ $from ];
		}
	}
	if ( ! empty( $atts['link'] ) ) {
		$post = get_page_by_path( sanitize_title( $atts['link'] ), OBJECT, CSQR_LINK_TYPE );
		if ( $post ) {
			$atts['type']   = 'link';
			$atts['linkId'] = $post->ID;
		}
	}
	if ( empty( $atts['type'] ) ) {
		$atts['type'] = ! empty( $atts['url'] ) ? 'url' : ( ! empty( $atts['text'] ) ? 'text' : 'page' );
	}
	foreach ( array( 'showDownloads', 'showWifiDetails', 'hidden' ) as $flag ) {
		if ( isset( $atts[ $flag ] ) ) {
			$atts[ $flag ] = rest_sanitize_boolean( $atts[ $flag ] );
		}
	}
	return csqr_render_code_block( $atts );
}
add_shortcode( 'qr_code', 'csqr_code_shortcode' );
