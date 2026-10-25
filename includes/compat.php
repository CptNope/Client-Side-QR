<?php
/**
 * Switching from another QR plugin: when that plugin is deactivated, its
 * shortcodes keep working and are drawn by this one, so pages don't have to
 * be edited one by one.
 *
 * Supported: Kaya QR Code Generator ([kaya_qrcode], [kaya_qrcode_dynamic])
 * and Shortcodes Ultimate ([su_qrcode]). Each is registered only if nothing
 * else has claimed the tag.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes from other plugins this one can draw.
 *
 * @return array<string, array{plugin: string, callback: callable}>
 */
function csqr_compat_shortcodes() {
	return apply_filters(
		'csqr_compat_shortcodes',
		array(
			'kaya_qrcode'         => array(
				'plugin'   => 'Kaya QR Code Generator',
				'callback' => 'csqr_compat_kaya',
			),
			'kaya_qrcode_dynamic' => array(
				'plugin'   => 'Kaya QR Code Generator',
				'callback' => 'csqr_compat_kaya_dynamic',
			),
			'su_qrcode'           => array(
				'plugin'   => 'Shortcodes Ultimate',
				'callback' => 'csqr_compat_su',
			),
		)
	);
}

/**
 * Register the ones no active plugin provides. Runs late so the original
 * plugin, if active, keeps its own shortcode.
 *
 * @return void
 */
function csqr_compat_register() {
	if ( ! apply_filters( 'csqr_compat_enabled', true ) ) {
		return;
	}
	foreach ( csqr_compat_shortcodes() as $tag => $def ) {
		if ( ! shortcode_exists( $tag ) ) {
			add_shortcode( $tag, $def['callback'] );
		}
	}
}
add_action( 'init', 'csqr_compat_register', 999 );

/**
 * A hex color, or ''.
 *
 * @param mixed $value Raw.
 * @return string
 */
function csqr_compat_color( $value ) {
	$value = trim( (string) $value );
	if ( '' !== $value && '#' !== $value[0] && preg_match( '/^[0-9a-f]{3,6}$/i', $value ) ) {
		$value = '#' . $value;
	}
	$hex = sanitize_hex_color( $value );
	return $hex ? $hex : '';
}

/**
 * Draw a code card from the common options other plugins use.
 *
 * @param string               $content What the code holds ('' = this page).
 * @param array<string, mixed> $o       size, color, background, align, title.
 * @return string
 */
function csqr_compat_render( $content, $o ) {
	$atts = array(
		'type'    => '' === $content ? 'page' : ( preg_match( '#^https?://#i', $content ) ? 'url' : 'text' ),
		'url'     => $content,
		'text'    => $content,
		'caption' => isset( $o['title'] ) ? sanitize_text_field( (string) $o['title'] ) : '',
	);
	if ( ! empty( $o['size'] ) && (int) $o['size'] > 0 ) {
		$atts['qrSize'] = max( 96, min( 600, (int) $o['size'] ) );
	}
	if ( ! empty( $o['color'] ) && csqr_compat_color( $o['color'] ) ) {
		$atts['qrColorDark'] = csqr_compat_color( $o['color'] );
	}
	if ( ! empty( $o['background'] ) && csqr_compat_color( $o['background'] ) ) {
		$atts['qrColorLight'] = csqr_compat_color( $o['background'] );
	}
	if ( ! empty( $o['align'] ) && in_array( $o['align'], array( 'left', 'right', 'center' ), true ) ) {
		$atts['cardAlign'] = $o['align'];
	}
	if ( ! empty( $o['level'] ) && in_array( strtoupper( (string) $o['level'] ), array( 'L', 'M', 'Q', 'H' ), true ) ) {
		$atts['qrCorrectLevel'] = strtoupper( (string) $o['level'] );
	}
	return csqr_render_code_block( $atts );
}

/**
 * [kaya_qrcode content="…"]. Without content it encodes the current page.
 * Kaya's own builder writes several spellings for its options, so the
 * common ones are all read.
 *
 * @param mixed $atts Attributes.
 * @return string
 */
function csqr_compat_kaya( $atts ) {
	$a = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
	$pick = function ( $keys ) use ( $a ) {
		foreach ( $keys as $k ) {
			if ( isset( $a[ $k ] ) && '' !== $a[ $k ] ) {
				return $a[ $k ];
			}
		}
		return '';
	};
	return csqr_compat_render(
		trim( (string) $pick( array( 'content', 'data', 'url' ) ) ),
		array(
			'size'       => $pick( array( 'size', 'qrsize' ) ),
			'color'      => $pick( array( 'color', 'qrcolor', 'fgcolor', 'foreground' ) ),
			'background' => $pick( array( 'bgcolor', 'background', 'bg_color', 'qrbgcolor' ) ),
			'align'      => $pick( array( 'align', 'alignment' ) ),
			'title'      => $pick( array( 'title' ) ),
			'level'      => $pick( array( 'ecclevel', 'ecc_level', 'level' ) ),
		)
	);
}

/**
 * [kaya_qrcode_dynamic]…[/kaya_qrcode_dynamic]: the enclosed shortcode's
 * output is what the code holds.
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Enclosed content.
 * @return string
 */
function csqr_compat_kaya_dynamic( $atts, $content = '' ) {
	$a            = is_array( $atts ) ? $atts : array();
	$a['content'] = trim( wp_strip_all_tags( do_shortcode( (string) $content ) ) );
	return csqr_compat_kaya( $a );
}

/**
 * [su_qrcode data="…" title="…" size="200" color="#000000" background="#ffffff" align="none" link="…"].
 *
 * @param mixed $atts Attributes.
 * @return string
 */
function csqr_compat_su( $atts ) {
	$a = shortcode_atts(
		array(
			'data'       => '',
			'title'      => '',
			'size'       => 200,
			'margin'     => 0,
			'align'      => 'none',
			'link'       => '',
			'target'     => 'blank',
			'color'      => '#000000',
			'background' => '#ffffff',
			'class'      => '',
		),
		$atts,
		'su_qrcode'
	);
	return csqr_compat_render( trim( (string) $a['data'] ), $a );
}

/**
 * Posts that still use another plugin's QR shortcodes (for the settings
 * screen).
 *
 * @return array<string, int> tag => number of posts
 */
function csqr_compat_usage() {
	global $wpdb;
	$out = array();
	foreach ( array_keys( csqr_compat_shortcodes() ) as $tag ) {
		$like = '%' . $wpdb->esc_like( '[' . $tag ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off count on an admin screen.
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private','future') AND post_content LIKE %s", $like ) );
		if ( $count ) {
			$out[ $tag ] = $count;
		}
	}
	return $out;
}

/**
 * Settings screen: say which old shortcodes are being drawn by this plugin.
 *
 * @return void
 */
function csqr_compat_settings_notice() {
	$usage = csqr_compat_usage();
	if ( ! $usage ) {
		return;
	}
	$defs = csqr_compat_shortcodes();
	?>
	<h2 id="csqr-compat"><?php esc_html_e( 'Codes from your old QR plugin', 'anderson-qr-code-generator' ); ?></h2>
	<p><?php esc_html_e( 'These shortcodes from another plugin are on your site. While that plugin is switched off, this plugin draws them with your default design, so you don’t have to edit those pages.', 'anderson-qr-code-generator' ); ?></p>
	<ul class="csqr-compat">
		<?php foreach ( $usage as $tag => $count ) : ?>
			<li>
				<code>[<?php echo esc_html( $tag ); ?>]</code>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: plugin name, 2: number of posts */
						_n( '(%1$s) in %2$s post or page', '(%1$s) in %2$s posts and pages', $count, 'anderson-qr-code-generator' ),
						$defs[ $tag ]['plugin'],
						number_format_i18n( $count )
					)
				);
				$ours = isset( $GLOBALS['shortcode_tags'][ $tag ] ) && $GLOBALS['shortcode_tags'][ $tag ] === $defs[ $tag ]['callback'];
				echo ': ' . ( $ours ? esc_html__( 'drawn by this plugin', 'anderson-qr-code-generator' ) : esc_html__( 'still handled by the other plugin while it’s active', 'anderson-qr-code-generator' ) );
				?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}
add_action( 'csqr_settings_after', 'csqr_compat_settings_notice' );
