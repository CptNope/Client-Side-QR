<?php
/**
 * Plugin Name:       Anderson QR Code Generator
 * Plugin URI:        https://github.com/CptNope/Client-Side-QR
 * Description:       Generate privacy-friendly QR codes in the browser with a Gutenberg block and shortcode for campaigns, contact sharing, payments, and QR-driven site workflows.
 * Version:           4.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jeremy Anderson
 * Author URI:        https://jeremyanderson.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       anderson-qr-code-generator
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSQR_VERSION', '4.3.0' );
define( 'CSQR_OPTION_NAME', 'csqr_settings' );
define( 'CSQR_RELEASE_TRANSIENT', 'csqr_github_release_data' );
define( 'CSQR_RELEASE_CRON_HOOK', 'csqr_check_github_release_event' );
define( 'CSQR_SLUG', 'anderson-qr-code-generator' );
define( 'CSQR_AUTHOR_URL', 'https://jeremyanderson.tech/' );
define( 'CSQR_DOCS_URL', 'https://github.com/CptNope/Client-Side-QR#readme' );
define( 'CSQR_SUPPORT_URL', 'https://wordpress.org/support/plugin/' . CSQR_SLUG . '/' );
define( 'CSQR_FILE', __FILE__ );
define( 'CSQR_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSQR_URL', plugin_dir_url( __FILE__ ) );

require_once CSQR_DIR . 'includes/payloads.php';
require_once CSQR_DIR . 'includes/styles.php';
require_once CSQR_DIR . 'includes/links.php';
require_once CSQR_DIR . 'includes/code-block.php';
require_once CSQR_DIR . 'includes/page-qr.php';
require_once CSQR_DIR . 'includes/admin.php';

/**
 * Get the plugin's default settings.
 *
 * @return array<string, mixed>
 */
function csqr_get_default_settings() {
	$defaults = array(
		'qrColorDark'       => '#111111',
		'qrColorDark2'      => '#111111',
		'qrColorLight'      => '#ffffff',
		'uiUseThemeColors'  => true,
		'uiUseThemeFont'    => true,
		'uiSurfaceColor'    => '',
		'uiTextColor'       => '',
		'uiAccentColor'     => '',
		'uiFontFamily'      => '',
		'qrSize'            => 256,
		'qrCorrectLevel'    => 'H',
		'qrDotStyle'        => 'square',
		'qrEyeStyle'        => 'square',
		'qrEyeColor'        => '',
		'qrGradient'        => false,
		'logoUrl'           => '',
		'allowUserColor'    => false,
		'allowUserSize'     => false,
		'allowUserCorrectLevel' => false,
		'logoSize'          => 0.24,
		'transparent'       => false,
		'printUnits'        => 'cm',
		'pageQrTypes'       => array(),
		'pageQrCaption'     => '',
		'pageQrDownloads'   => true,
		'linkPrefix'        => 'qr',
		'enableUrl'         => true,
		'enableWifi'        => true,
		'enableEmail'       => true,
		'enableSms'         => true,
		'enableVcard'       => true,
		'enableCrypto'      => true,
		'enablePaypal'      => true,
	);

	return apply_filters( 'csqr_default_settings', $defaults );
}

/**
 * Get registered payload keys.
 *
 * @return string[]
 */
function csqr_get_payload_keys() {
	return array(
		'enableUrl',
		'enableWifi',
		'enableEmail',
		'enableSms',
		'enableVcard',
		'enableCrypto',
		'enablePaypal',
	);
}

/**
 * Sanitize a hex color value.
 *
 * @param mixed $value Color input.
 * @param string $fallback Fallback color.
 * @param bool $allow_empty Whether an empty string is allowed.
 * @return string
 */
function csqr_sanitize_hex_color( $value, $fallback, $allow_empty = false ) {
	$value = is_string( $value ) ? sanitize_text_field( $value ) : '';

	if ( '' === $value && $allow_empty ) {
		return '';
	}

	$sanitized = sanitize_hex_color( $value );

	return $sanitized ? $sanitized : $fallback;
}

/**
 * Sanitize plugin settings and shortcode attributes.
 *
 * @param array<string, mixed> $settings Raw settings.
 * @return array<string, mixed>
 */
function csqr_sanitize_settings( $settings ) {
	$defaults     = csqr_get_default_settings();
	$settings     = is_array( $settings ) ? $settings : array();
	$dot_styles   = array( 'square', 'dots', 'rounded', 'extra-rounded', 'classy', 'classy-rounded' );
	$eye_styles   = array( 'square', 'dot', 'extra-rounded' );
	$error_levels = array( 'L', 'M', 'Q', 'H' );

	$sanitized = array(
		'qrColorDark'           => csqr_sanitize_hex_color( $settings['qrColorDark'] ?? $defaults['qrColorDark'], $defaults['qrColorDark'] ),
		'qrColorDark2'          => csqr_sanitize_hex_color( $settings['qrColorDark2'] ?? $defaults['qrColorDark2'], $defaults['qrColorDark2'] ),
		'qrColorLight'          => csqr_sanitize_hex_color( $settings['qrColorLight'] ?? $defaults['qrColorLight'], $defaults['qrColorLight'] ),
		'uiUseThemeColors'      => rest_sanitize_boolean( $settings['uiUseThemeColors'] ?? $defaults['uiUseThemeColors'] ),
		'uiUseThemeFont'        => rest_sanitize_boolean( $settings['uiUseThemeFont'] ?? $defaults['uiUseThemeFont'] ),
		'uiSurfaceColor'        => csqr_sanitize_hex_color( $settings['uiSurfaceColor'] ?? $defaults['uiSurfaceColor'], '', true ),
		'uiTextColor'           => csqr_sanitize_hex_color( $settings['uiTextColor'] ?? $defaults['uiTextColor'], '', true ),
		'uiAccentColor'         => csqr_sanitize_hex_color( $settings['uiAccentColor'] ?? $defaults['uiAccentColor'], '', true ),
		'uiFontFamily'          => csqr_sanitize_font_family( $settings['uiFontFamily'] ?? $defaults['uiFontFamily'] ),
		'qrSize'                => min( 800, max( 100, absint( $settings['qrSize'] ?? $defaults['qrSize'] ) ) ),
		'qrCorrectLevel'        => in_array( $settings['qrCorrectLevel'] ?? '', $error_levels, true ) ? $settings['qrCorrectLevel'] : $defaults['qrCorrectLevel'],
		'qrDotStyle'            => in_array( $settings['qrDotStyle'] ?? '', $dot_styles, true ) ? $settings['qrDotStyle'] : $defaults['qrDotStyle'],
		'qrEyeStyle'            => in_array( $settings['qrEyeStyle'] ?? '', $eye_styles, true ) ? $settings['qrEyeStyle'] : $defaults['qrEyeStyle'],
		'qrEyeColor'            => csqr_sanitize_hex_color( $settings['qrEyeColor'] ?? $defaults['qrEyeColor'], $defaults['qrColorDark'], true ),
		'qrGradient'            => rest_sanitize_boolean( $settings['qrGradient'] ?? $defaults['qrGradient'] ),
		'logoUrl'               => esc_url_raw( $settings['logoUrl'] ?? $defaults['logoUrl'] ),
		'allowUserColor'        => rest_sanitize_boolean( $settings['allowUserColor'] ?? $defaults['allowUserColor'] ),
		'allowUserSize'         => rest_sanitize_boolean( $settings['allowUserSize'] ?? $defaults['allowUserSize'] ),
		'allowUserCorrectLevel' => rest_sanitize_boolean( $settings['allowUserCorrectLevel'] ?? $defaults['allowUserCorrectLevel'] ),
		'logoSize'              => min( 0.3, max( 0.1, (float) ( $settings['logoSize'] ?? $defaults['logoSize'] ) ) ),
		'transparent'           => rest_sanitize_boolean( $settings['transparent'] ?? $defaults['transparent'] ),
		'printUnits'            => 'in' === ( $settings['printUnits'] ?? '' ) ? 'in' : 'cm',
		'pageQrTypes'           => array_values( array_filter( array_map( 'sanitize_key', (array) ( $settings['pageQrTypes'] ?? $defaults['pageQrTypes'] ) ), 'post_type_exists' ) ),
		'pageQrCaption'         => sanitize_text_field( $settings['pageQrCaption'] ?? $defaults['pageQrCaption'] ),
		'pageQrDownloads'       => rest_sanitize_boolean( $settings['pageQrDownloads'] ?? $defaults['pageQrDownloads'] ),
		'linkPrefix'            => csqr_sanitize_link_prefix( $settings['linkPrefix'] ?? $defaults['linkPrefix'] ),
	);

	foreach ( csqr_get_payload_keys() as $payload_key ) {
		$sanitized[ $payload_key ] = rest_sanitize_boolean( $settings[ $payload_key ] ?? $defaults[ $payload_key ] );
	}

	if (
		! $sanitized['enableUrl'] &&
		! $sanitized['enableWifi'] &&
		! $sanitized['enableEmail'] &&
		! $sanitized['enableSms'] &&
		! $sanitized['enableVcard'] &&
		! $sanitized['enableCrypto'] &&
		! $sanitized['enablePaypal']
	) {
		$sanitized['enableUrl'] = true;
	}

	return apply_filters( 'csqr_sanitized_settings', $sanitized, $settings );
}

/**
 * Sanitize a font-family declaration used for optional UI overrides.
 *
 * @param mixed $value Raw font-family input.
 * @return string
 */
function csqr_sanitize_font_family( $value ) {
	$value = is_string( $value ) ? wp_strip_all_tags( $value ) : '';
	$value = preg_replace( '/[^A-Za-z0-9,\-"\' _]/', '', $value );

	return is_string( $value ) ? trim( $value ) : '';
}

/**
 * Get stored settings merged with defaults.
 *
 * @return array<string, mixed>
 */
function csqr_get_settings() {
	$saved = get_option( CSQR_OPTION_NAME, array() );

	return csqr_sanitize_settings( wp_parse_args( is_array( $saved ) ? $saved : array(), csqr_get_default_settings() ) );
}

/**
 * Get settings for a shortcode or block instance.
 *
 * @param array<string, mixed> $attributes Instance attributes.
 * @return array<string, mixed>
 */
function csqr_get_instance_settings( $attributes ) {
	$settings = csqr_sanitize_settings( wp_parse_args( (array) $attributes, csqr_get_settings() ) );

	return apply_filters( 'csqr_instance_settings', $settings, $attributes );
}

/**
 * Register plugin settings.
 *
 * @return void
 */
function csqr_register_settings() {
	register_setting(
		'csqr_settings_group',
		CSQR_OPTION_NAME,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'csqr_sanitize_option_settings',
			'default'           => csqr_get_default_settings(),
		)
	);
}
add_action( 'admin_init', 'csqr_register_settings' );

/**
 * Links under the plugin's description on the Plugins screen.
 *
 * @param string[] $links Existing links.
 * @param string   $file  Plugin basename.
 * @return string[]
 */
function csqr_plugin_row_meta( $links, $file ) {
	if ( plugin_basename( __FILE__ ) !== $file ) {
		return $links;
	}
	$extra = array(
		CSQR_DOCS_URL                    => __( 'Documentation', 'anderson-qr-code-generator' ),
		CSQR_SUPPORT_URL                 => __( 'Support', 'anderson-qr-code-generator' ),
		CSQR_AUTHOR_URL . 'book-a-call/' => __( 'Hire the author', 'anderson-qr-code-generator' ),
	);
	foreach ( $extra as $url => $label ) {
		$links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'csqr_plugin_row_meta', 10, 2 );

/**
 * A short "about" footer, shown only on this plugin's own admin pages.
 *
 * @return void
 */
function csqr_render_about() {
	?>
	<aside class="csqr-about" aria-label="<?php esc_attr_e( 'About this plugin', 'anderson-qr-code-generator' ); ?>">
		<p>
			<strong><?php esc_html_e( 'Free and open source, by Jeremy Anderson.', 'anderson-qr-code-generator' ); ?></strong>
			<?php esc_html_e( 'Need a custom QR workflow, a WordPress site, or a tool built for your business? I build and look after WordPress sites.', 'anderson-qr-code-generator' ); ?>
		</p>
		<p class="csqr-about-links">
			<a class="button" href="<?php echo esc_url( CSQR_AUTHOR_URL . 'book-a-call/' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Book a call', 'anderson-qr-code-generator' ); ?></a>
			<a href="<?php echo esc_url( CSQR_AUTHOR_URL . 'services/' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Services', 'anderson-qr-code-generator' ); ?></a>
			<a href="<?php echo esc_url( CSQR_DOCS_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Documentation', 'anderson-qr-code-generator' ); ?></a>
			<a href="<?php echo esc_url( CSQR_SUPPORT_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Support', 'anderson-qr-code-generator' ); ?></a>
		</p>
	</aside>
	<?php
}

/**
 * Remove the scheduled check and cache left by the opt-in GitHub release
 * notices in earlier GitHub builds (removed in 4.2.0: WordPress.org plugins
 * update through WordPress.org).
 *
 * @return void
 */
function csqr_remove_release_check() {
	if ( wp_next_scheduled( CSQR_RELEASE_CRON_HOOK ) ) {
		wp_clear_scheduled_hook( CSQR_RELEASE_CRON_HOOK );
	}
	if ( false !== get_site_transient( CSQR_RELEASE_TRANSIENT ) ) {
		delete_site_transient( CSQR_RELEASE_TRANSIENT );
	}
}
add_action( 'admin_init', 'csqr_remove_release_check' );

/**
 * Build a shortcode string from settings.
 *
 * @param array<string, mixed> $settings Shortcode settings.
 * @return string
 */
function csqr_build_shortcode_from_settings( $settings ) {
	$defaults   = csqr_get_default_settings();
	$attributes = array();

	foreach ( $settings as $key => $value ) {
		if ( ! array_key_exists( $key, $defaults ) ) {
			continue;
		}

		$default = $defaults[ $key ];

		if ( is_bool( $default ) ) {
			if ( (bool) $value !== (bool) $default ) {
				$attributes[] = $key . '="' . ( $value ? 'true' : 'false' ) . '"';
			}
			continue;
		}

		if ( (string) $value !== (string) $default && '' !== (string) $value ) {
			$attributes[] = $key . '="' . esc_attr( (string) $value ) . '"';
		}
	}

	return '[client_side_qr' . ( $attributes ? ' ' . implode( ' ', $attributes ) : '' ) . ']';
}

/**
 * Build inline CSS variables for the frontend container shell.
 *
 * @param array<string, mixed> $settings Instance settings.
 * @return string
 */
function csqr_get_container_style_attribute( $settings ) {
	$styles = array();

	if ( empty( $settings['uiUseThemeColors'] ) ) {
		if ( ! empty( $settings['uiSurfaceColor'] ) ) {
			$styles[] = '--csqr-ui-surface:' . $settings['uiSurfaceColor'];
		}

		if ( ! empty( $settings['uiTextColor'] ) ) {
			$styles[] = '--csqr-ui-text:' . $settings['uiTextColor'];
		}

		if ( ! empty( $settings['uiAccentColor'] ) ) {
			$styles[] = '--csqr-ui-accent:' . $settings['uiAccentColor'];
		}
	}

	if ( empty( $settings['uiUseThemeFont'] ) && ! empty( $settings['uiFontFamily'] ) ) {
		$styles[] = '--csqr-ui-font:' . $settings['uiFontFamily'];
	}

	return implode( ';', $styles );
}

/**
 * Register frontend and editor assets.
 *
 * @return void
 */
function csqr_register_assets() {
	$asset_url = CSQR_URL . 'assets/';
	$settings  = csqr_get_settings();

	wp_register_script( 'csqr-qrcode-styling', $asset_url . 'vendor/qr-code-styling.js', array(), '1.5.0', true );
	wp_register_script( 'csqr-core', $asset_url . 'qr-core.js', array( 'csqr-qrcode-styling' ), CSQR_VERSION, true );
	wp_localize_script(
		'csqr-core',
		'csqrCoreConfig',
		array(
			'jsqrUrl' => $asset_url . 'vendor/jsQR.js?ver=1.4.0',
			'units'   => $settings['printUnits'],
			'i18n'    => csqr_core_strings(),
		)
	);
	wp_register_script( 'csqr-ui', $asset_url . 'qr-ui.js', array( 'csqr-core' ), CSQR_VERSION, true );
	wp_register_script( 'csqr-script', $asset_url . 'qr-script.js', array( 'csqr-ui' ), CSQR_VERSION, true );
	wp_localize_script(
		'csqr-script',
		'csqrFrontendConfig',
		array(
			'i18n' => array(
				'empty' => __( 'Your code appears here as you type.', 'anderson-qr-code-generator' ),
			),
		)
	);
	wp_register_style( 'csqr-style', $asset_url . 'qr-style.css', array(), CSQR_VERSION );
}

/**
 * Load what a QR card or the generator needs on the front end.
 *
 * @param bool $generator Also load the visitor generator.
 * @return void
 */
function csqr_enqueue_frontend( $generator = false ) {
	wp_enqueue_style( 'csqr-style' );
	wp_enqueue_script( $generator ? 'csqr-script' : 'csqr-ui' );
}

/**
 * Strings for the shared QR engine and its UI (assets/qr-core.js, qr-ui.js).
 *
 * @return array<string, string>
 */
function csqr_core_strings() {
	return array(
		'checking'          => __( 'Checking it scans…', 'anderson-qr-code-generator' ),
		'checkOk'           => __( 'Scans correctly', 'anderson-qr-code-generator' ),
		'checkWarn'         => __( 'Scans, with a caveat', 'anderson-qr-code-generator' ),
		'checkFail'         => __( 'Won’t scan reliably', 'anderson-qr-code-generator' ),
		'checkUnknown'      => __( 'Not checked', 'anderson-qr-code-generator' ),
		/* translators: %1$s: contrast ratio, e.g. 2.1 */
		'checkContrastLow'  => __( 'The colors are too close together (contrast %1$s:1). Use a darker code or a lighter background.', 'anderson-qr-code-generator' ),
		/* translators: %1$s: contrast ratio, e.g. 3.8 */
		'checkContrastMid'  => __( 'Contrast is on the low side (%1$s:1). It scans on screen, but print and dim light make it harder.', 'anderson-qr-code-generator' ),
		'checkInverted'     => __( 'The code is lighter than its background. Most phone cameras can’t read light-on-dark codes.', 'anderson-qr-code-generator' ),
		'checkTransparent'  => __( 'The background is transparent, so it only scans on a light surface.', 'anderson-qr-code-generator' ),
		'checkUnreadable'   => __( 'A phone camera can’t read this code. The styling hides too much of it.', 'anderson-qr-code-generator' ),
		'checkMismatch'     => __( 'This code reads back as different text. Simplify the styling.', 'anderson-qr-code-generator' ),
		'checkSmall'        => __( 'It scans close up, but may fail when printed small or seen from a distance.', 'anderson-qr-code-generator' ),
		'checkLogoBlocked'  => __( 'Couldn’t test this code: its logo is on another site that doesn’t allow it. Choose the logo from your Media Library.', 'anderson-qr-code-generator' ),
		'checkFailed'       => __( 'Couldn’t test this code in this browser.', 'anderson-qr-code-generator' ),
		'fixInverted'       => __( 'Swap the colors', 'anderson-qr-code-generator' ),
		'fixContrast'       => __( 'Use black on white', 'anderson-qr-code-generator' ),
		'fixContrastDarken' => __( 'Darken the code', 'anderson-qr-code-generator' ),
		'fixLogoSmaller'    => __( 'Make the logo smaller', 'anderson-qr-code-generator' ),
		'fixLogoRemove'     => __( 'Remove the logo', 'anderson-qr-code-generator' ),
		'fixSimplify'       => __( 'Use plain square dots', 'anderson-qr-code-generator' ),
		'fixLowerLevel'     => __( 'Use fewer, larger dots', 'anderson-qr-code-generator' ),
		'sizeCard'          => 'in' === csqr_get_settings()['printUnits'] ? __( 'Business card (1 in)', 'anderson-qr-code-generator' ) : __( 'Business card (2.5 cm)', 'anderson-qr-code-generator' ),
		'sizeFlyer'         => 'in' === csqr_get_settings()['printUnits'] ? __( 'Flyer or table card (2 in)', 'anderson-qr-code-generator' ) : __( 'Flyer or table card (5 cm)', 'anderson-qr-code-generator' ),
		'sizePoster'        => 'in' === csqr_get_settings()['printUnits'] ? __( 'Poster (4 in)', 'anderson-qr-code-generator' ) : __( 'Poster (10 cm)', 'anderson-qr-code-generator' ),
		'sizeSign'          => 'in' === csqr_get_settings()['printUnits'] ? __( 'Sign or window (8 in)', 'anderson-qr-code-generator' ) : __( 'Sign or window (20 cm)', 'anderson-qr-code-generator' ),
		'sizeCustom'        => __( 'Custom width (cm)…', 'anderson-qr-code-generator' ),
		'customWidth'       => __( 'Width in centimetres', 'anderson-qr-code-generator' ),
		'printSize'         => __( 'Print size', 'anderson-qr-code-generator' ),
		/* translators: 1: printed width, e.g. "5 cm"; 2: distance, e.g. "about 50 cm" */
		'scanTip'           => __( 'Prints %1$s wide and scans from %2$s away.', 'anderson-qr-code-generator' ),
		'tooSmall'          => __( 'Below 2 cm, many phones struggle.', 'anderson-qr-code-generator' ),
		/* translators: %1$s: number */
		'distCm'            => __( 'about %1$s cm', 'anderson-qr-code-generator' ),
		/* translators: %1$s: number */
		'distMetres'        => __( 'about %1$s m', 'anderson-qr-code-generator' ),
		/* translators: %1$s: number */
		'distInches'        => __( 'about %1$s in', 'anderson-qr-code-generator' ),
		/* translators: %1$s: number */
		'distFeet'          => __( 'about %1$s ft', 'anderson-qr-code-generator' ),
		/* translators: %1$s: number */
		'sizeCm'            => __( '%1$s cm', 'anderson-qr-code-generator' ),
		/* translators: %1$s: number */
		'sizeInches'        => __( '%1$s in', 'anderson-qr-code-generator' ),
		'downloadPng'       => __( 'Download PNG', 'anderson-qr-code-generator' ),
		'downloadSvg'       => __( 'SVG', 'anderson-qr-code-generator' ),
		'downloadPdf'       => __( 'PDF', 'anderson-qr-code-generator' ),
		'copyImage'         => __( 'Copy', 'anderson-qr-code-generator' ),
		'fillFirst'         => __( 'Fill in the form to download your code.', 'anderson-qr-code-generator' ),
		'editorOnly'        => __( 'Only people who can edit the site see this check.', 'anderson-qr-code-generator' ),
		/* translators: %1$s: the web address the QR code opens */
		'codeFor'           => __( 'QR code for %1$s', 'anderson-qr-code-generator' ),
		'codeReady'         => __( 'Your QR code', 'anderson-qr-code-generator' ),
		'copied'            => __( 'Copied. Paste it into your document or message.', 'anderson-qr-code-generator' ),
		'copyError'         => __( 'Your browser didn’t allow copying. Download it instead.', 'anderson-qr-code-generator' ),
		'downloadError'     => __( 'That download didn’t work in this browser.', 'anderson-qr-code-generator' ),
	);
}
add_action( 'init', 'csqr_register_assets' );

/**
 * Register the block.
 *
 * @return void
 */
function csqr_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	wp_register_script( 'csqr-editor', CSQR_URL . 'assets/qr-editor.js', array( 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'csqr-ui' ), CSQR_VERSION, true );
	wp_set_script_translations( 'csqr-editor', 'anderson-qr-code-generator' );
	wp_register_script( 'csqr-block-script', CSQR_URL . 'assets/qr-block.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'csqr-editor' ), CSQR_VERSION, true );
	wp_set_script_translations( 'csqr-block-script', 'anderson-qr-code-generator' );

	register_block_type(
		'csqr/generator',
		array(
			'api_version'     => 3,
			'editor_script'   => 'csqr-block-script',
			'editor_style'    => 'csqr-style',
			'style'           => 'csqr-style',
			'render_callback' => 'csqr_render_qr_generator',
		)
	);
}
add_action( 'init', 'csqr_register_block' );

/**
 * Fields of the visitor generator, per kind of code.
 *
 * @return array<string, array{label: string, fields: array<int, array<string, mixed>>}>
 */
function csqr_generator_fields() {
	$fields = array(
		'url'    => array(
			'label'  => __( 'Link', 'anderson-qr-code-generator' ),
			'fields' => array(
				array( 'key' => 'url', 'label' => __( 'Link or text', 'anderson-qr-code-generator' ), 'type' => 'text', 'inputmode' => 'url', 'placeholder' => 'https://', 'required' => true ),
				array( 'key' => 'utmSource', 'label' => __( 'Where it’s placed', 'anderson-qr-code-generator' ), 'type' => 'text', 'placeholder' => __( 'For example: flyer', 'anderson-qr-code-generator' ), 'group' => 'utm' ),
				array( 'key' => 'utmMedium', 'label' => __( 'Kind of placement', 'anderson-qr-code-generator' ), 'type' => 'text', 'placeholder' => __( 'For example: print', 'anderson-qr-code-generator' ), 'group' => 'utm' ),
				array( 'key' => 'utmCampaign', 'label' => __( 'Campaign name', 'anderson-qr-code-generator' ), 'type' => 'text', 'placeholder' => __( 'For example: spring-sale', 'anderson-qr-code-generator' ), 'group' => 'utm' ),
			),
		),
		'wifi'   => array(
			'label'  => __( 'WiFi', 'anderson-qr-code-generator' ),
			'fields' => array(
				array( 'key' => 'ssid', 'label' => __( 'Network name', 'anderson-qr-code-generator' ), 'type' => 'text', 'autocomplete' => 'off', 'required' => true ),
				array( 'key' => 'password', 'label' => __( 'Password', 'anderson-qr-code-generator' ), 'type' => 'text', 'autocomplete' => 'off', 'spellcheck' => 'false' ),
				array(
					'key'     => 'encryption',
					'label'   => __( 'Security', 'anderson-qr-code-generator' ),
					'type'    => 'select',
					'options' => array(
						'WPA'    => __( 'WPA, WPA2 or WPA3 (most networks)', 'anderson-qr-code-generator' ),
						'WEP'    => __( 'WEP (older networks)', 'anderson-qr-code-generator' ),
						'nopass' => __( 'No password', 'anderson-qr-code-generator' ),
					),
				),
				array( 'key' => 'hidden', 'label' => __( 'The network is hidden', 'anderson-qr-code-generator' ), 'type' => 'checkbox' ),
			),
		),
		'vcard'  => array(
			'label'  => __( 'Contact', 'anderson-qr-code-generator' ),
			'fields' => array(
				array( 'key' => 'firstName', 'label' => __( 'First name', 'anderson-qr-code-generator' ), 'type' => 'text', 'autocomplete' => 'given-name', 'half' => true ),
				array( 'key' => 'lastName', 'label' => __( 'Last name', 'anderson-qr-code-generator' ), 'type' => 'text', 'autocomplete' => 'family-name', 'half' => true ),
				array( 'key' => 'phone', 'label' => __( 'Phone', 'anderson-qr-code-generator' ), 'type' => 'tel', 'autocomplete' => 'tel', 'half' => true ),
				array( 'key' => 'email', 'label' => __( 'Email', 'anderson-qr-code-generator' ), 'type' => 'email', 'autocomplete' => 'email', 'half' => true ),
				array( 'key' => 'company', 'label' => __( 'Company', 'anderson-qr-code-generator' ), 'type' => 'text', 'autocomplete' => 'organization', 'half' => true ),
				array( 'key' => 'title', 'label' => __( 'Job title', 'anderson-qr-code-generator' ), 'type' => 'text', 'autocomplete' => 'organization-title', 'half' => true ),
				array( 'key' => 'website', 'label' => __( 'Website', 'anderson-qr-code-generator' ), 'type' => 'text', 'inputmode' => 'url', 'autocomplete' => 'url' ),
				array( 'key' => 'address', 'label' => __( 'Address', 'anderson-qr-code-generator' ), 'type' => 'text', 'autocomplete' => 'street-address' ),
			),
		),
		'email'  => array(
			'label'  => __( 'Email', 'anderson-qr-code-generator' ),
			'fields' => array(
				array( 'key' => 'email', 'label' => __( 'Send to', 'anderson-qr-code-generator' ), 'type' => 'email', 'required' => true ),
				array( 'key' => 'subject', 'label' => __( 'Subject', 'anderson-qr-code-generator' ), 'type' => 'text' ),
				array( 'key' => 'body', 'label' => __( 'Message', 'anderson-qr-code-generator' ), 'type' => 'textarea' ),
			),
		),
		'sms'    => array(
			'label'  => __( 'Text message', 'anderson-qr-code-generator' ),
			'fields' => array(
				array( 'key' => 'phone', 'label' => __( 'Phone number', 'anderson-qr-code-generator' ), 'type' => 'tel', 'required' => true ),
				array( 'key' => 'message', 'label' => __( 'Message', 'anderson-qr-code-generator' ), 'type' => 'textarea' ),
			),
		),
		'crypto' => array(
			'label'  => __( 'Crypto', 'anderson-qr-code-generator' ),
			'fields' => array(
				array(
					'key'     => 'currency',
					'label'   => __( 'Currency', 'anderson-qr-code-generator' ),
					'type'    => 'select',
					'options' => array(
						'bitcoin'  => __( 'Bitcoin (BTC)', 'anderson-qr-code-generator' ),
						'ethereum' => __( 'Ethereum (ETH)', 'anderson-qr-code-generator' ),
						'litecoin' => __( 'Litecoin (LTC)', 'anderson-qr-code-generator' ),
					),
				),
				array( 'key' => 'address', 'label' => __( 'Wallet address', 'anderson-qr-code-generator' ), 'type' => 'text', 'spellcheck' => 'false', 'required' => true ),
				array( 'key' => 'amount', 'label' => __( 'Amount (optional)', 'anderson-qr-code-generator' ), 'type' => 'text', 'inputmode' => 'decimal' ),
			),
		),
		'paypal' => array(
			'label'  => __( 'PayPal', 'anderson-qr-code-generator' ),
			'fields' => array(
				array( 'key' => 'username', 'label' => __( 'PayPal.me name', 'anderson-qr-code-generator' ), 'type' => 'text', 'placeholder' => 'yourname', 'required' => true ),
				array( 'key' => 'amount', 'label' => __( 'Amount (optional)', 'anderson-qr-code-generator' ), 'type' => 'text', 'inputmode' => 'decimal', 'half' => true ),
				array(
					'key'     => 'currency',
					'label'   => __( 'Currency', 'anderson-qr-code-generator' ),
					'type'    => 'select',
					'half'    => true,
					'options' => array(
						'USD' => 'USD',
						'EUR' => 'EUR',
						'GBP' => 'GBP',
						'CAD' => 'CAD',
						'AUD' => 'AUD',
					),
				),
			),
		),
	);

	// Older filter: relabel the kinds of code.
	$labels = apply_filters( 'csqr_payload_labels', wp_list_pluck( $fields, 'label' ) );
	foreach ( $labels as $type => $label ) {
		if ( isset( $fields[ $type ] ) ) {
			$fields[ $type ]['label'] = $label;
		}
	}
	return apply_filters( 'csqr_generator_fields', $fields );
}

/**
 * One generator field.
 *
 * @param string               $uid   Instance id.
 * @param array<string, mixed> $field Field definition.
 * @return string
 */
function csqr_render_generator_field( $uid, $field ) {
	$id    = $uid . '-' . $field['key'];
	$attrs = ' id="' . esc_attr( $id ) . '" data-field="' . esc_attr( $field['key'] ) . '"';
	foreach ( array( 'placeholder', 'autocomplete', 'inputmode', 'spellcheck' ) as $attr ) {
		if ( ! empty( $field[ $attr ] ) ) {
			$attrs .= ' ' . $attr . '="' . esc_attr( $field[ $attr ] ) . '"';
		}
	}
	if ( ! empty( $field['required'] ) ) {
		$attrs .= ' aria-required="true"';
	}
	$class = 'csqr-field' . ( ! empty( $field['half'] ) ? ' csqr-field--half' : '' );

	if ( 'checkbox' === $field['type'] ) {
		return '<label class="csqr-field csqr-field--check" for="' . esc_attr( $id ) . '"><input type="checkbox"' . $attrs . ' /> <span>' . esc_html( $field['label'] ) . '</span></label>';
	}
	if ( 'select' === $field['type'] ) {
		$control = '<select class="csqr-input"' . $attrs . '>';
		foreach ( $field['options'] as $value => $label ) {
			$control .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		$control .= '</select>';
	} elseif ( 'textarea' === $field['type'] ) {
		$control = '<textarea class="csqr-input" rows="3"' . $attrs . '></textarea>';
	} else {
		$control = '<input type="' . esc_attr( $field['type'] ) . '" class="csqr-input"' . $attrs . ' />';
	}
	return '<div class="' . esc_attr( $class ) . '"><label class="csqr-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>' . $control . '</div>';
}

/**
 * The visitor QR generator (block csqr/generator and [client_side_qr]).
 *
 * @param array<string, mixed> $attributes Block or shortcode attributes.
 * @return string
 */
function csqr_render_qr_generator( $attributes = array() ) {
	$settings = csqr_get_instance_settings( $attributes );
	$uid      = wp_unique_id( 'csqr-' );
	$all      = csqr_generator_fields();
	$kinds    = array();
	foreach ( $all as $type => $def ) {
		if ( ! empty( $settings[ 'enable' . ucfirst( $type ) ] ) ) {
			$kinds[ $type ] = $def;
		}
	}
	if ( ! $kinds ) {
		$kinds['url'] = $all['url'];
	}
	$first  = array_key_first( $kinds );
	$design = array_intersect_key( $settings, array_flip( array_merge( csqr_design_keys(), array( 'qrSize' ) ) ) );
	$style  = csqr_get_container_style_attribute( $settings );
	$title  = isset( $attributes['title'] ) && is_string( $attributes['title'] ) && '' !== trim( $attributes['title'] ) ? $attributes['title'] : __( 'Make a QR code', 'anderson-qr-code-generator' );

	$show_title = ! isset( $attributes['showTitle'] ) || ! in_array( $attributes['showTitle'], array( false, 0, '0', 'false', 'no', 'off' ), true );

	csqr_enqueue_frontend( true );

	ob_start();
	?>
	<div class="csqr csqr-container csqr-generator" id="<?php echo esc_attr( $uid ); ?>" data-csqr-design="<?php echo esc_attr( wp_json_encode( $design ) ); ?>"<?php echo '' !== $style ? ' style="' . esc_attr( $style ) . '"' : ''; ?>>
		<div class="csqr-generator__form">
			<h2 class="csqr-generator__title<?php echo $show_title ? '' : ' csqr-visually-hidden'; ?>" id="<?php echo esc_attr( $uid . '-title' ); ?>"><?php echo esc_html( $title ); ?></h2>

			<?php if ( count( $kinds ) > 1 ) : ?>
				<div class="csqr-kinds" role="tablist" aria-labelledby="<?php echo esc_attr( $uid . '-title' ); ?>">
					<?php foreach ( $kinds as $type => $def ) : ?>
						<button type="button" role="tab" class="csqr-kind" id="<?php echo esc_attr( $uid . '-tab-' . $type ); ?>" aria-controls="<?php echo esc_attr( $uid . '-panel-' . $type ); ?>" aria-selected="<?php echo $type === $first ? 'true' : 'false'; ?>" tabindex="<?php echo $type === $first ? '0' : '-1'; ?>" data-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $def['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $kinds as $type => $def ) : ?>
				<div class="csqr-panel" role="<?php echo count( $kinds ) > 1 ? 'tabpanel' : 'group'; ?>" id="<?php echo esc_attr( $uid . '-panel-' . $type ); ?>" data-type="<?php echo esc_attr( $type ); ?>" <?php echo count( $kinds ) > 1 ? 'aria-labelledby="' . esc_attr( $uid . '-tab-' . $type ) . '"' : 'aria-labelledby="' . esc_attr( $uid . '-title' ) . '"'; ?> <?php echo $type === $first ? '' : 'hidden'; ?>>
					<?php
					$main  = '';
					$extra = '';
					foreach ( $def['fields'] as $field ) {
						$html = csqr_render_generator_field( $uid . '-' . $type, $field );
						if ( isset( $field['group'] ) && 'utm' === $field['group'] ) {
							$extra .= $html;
						} else {
							$main .= $html;
						}
					}
					echo '<div class="csqr-fields">' . $main . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
					if ( '' !== $extra ) {
						echo '<details class="csqr-more"><summary>' . esc_html__( 'Track where scans come from', 'anderson-qr-code-generator' ) . '</summary><p class="csqr-more__help">' . esc_html__( 'Adds campaign tags to the link, so your analytics can tell this code’s visitors apart.', 'anderson-qr-code-generator' ) . '</p><div class="csqr-fields">' . $extra . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
					}
					?>
				</div>
			<?php endforeach; ?>

			<?php if ( $settings['allowUserColor'] || $settings['allowUserSize'] || $settings['allowUserCorrectLevel'] ) : ?>
				<details class="csqr-more csqr-more--design">
					<summary><?php esc_html_e( 'Colors and size', 'anderson-qr-code-generator' ); ?></summary>
					<div class="csqr-fields">
						<?php if ( $settings['allowUserColor'] ) : ?>
							<div class="csqr-field csqr-field--half"><label class="csqr-field__label" for="<?php echo esc_attr( $uid . '-dark' ); ?>"><?php esc_html_e( 'Code color', 'anderson-qr-code-generator' ); ?></label><input type="color" class="csqr-input csqr-input--color" id="<?php echo esc_attr( $uid . '-dark' ); ?>" data-design="qrColorDark" value="<?php echo esc_attr( $settings['qrColorDark'] ); ?>" /></div>
							<div class="csqr-field csqr-field--half"><label class="csqr-field__label" for="<?php echo esc_attr( $uid . '-light' ); ?>"><?php esc_html_e( 'Background', 'anderson-qr-code-generator' ); ?></label><input type="color" class="csqr-input csqr-input--color" id="<?php echo esc_attr( $uid . '-light' ); ?>" data-design="qrColorLight" value="<?php echo esc_attr( $settings['qrColorLight'] ); ?>" /></div>
							<label class="csqr-field csqr-field--check" for="<?php echo esc_attr( $uid . '-transparent' ); ?>"><input type="checkbox" id="<?php echo esc_attr( $uid . '-transparent' ); ?>" data-design="transparent" <?php checked( $settings['transparent'] ); ?> /> <span><?php esc_html_e( 'Transparent background', 'anderson-qr-code-generator' ); ?></span></label>
						<?php endif; ?>
						<?php if ( $settings['allowUserSize'] ) : ?>
							<div class="csqr-field"><label class="csqr-field__label" for="<?php echo esc_attr( $uid . '-size' ); ?>"><?php esc_html_e( 'Size on screen', 'anderson-qr-code-generator' ); ?> <output class="csqr-field__value" for="<?php echo esc_attr( $uid . '-size' ); ?>"><?php echo esc_html( (string) $settings['qrSize'] ); ?> px</output></label><input type="range" class="csqr-input csqr-input--range" id="<?php echo esc_attr( $uid . '-size' ); ?>" data-design="qrSize" min="120" max="480" step="8" value="<?php echo esc_attr( (string) min( 480, $settings['qrSize'] ) ); ?>" /></div>
						<?php endif; ?>
						<?php if ( $settings['allowUserCorrectLevel'] ) : ?>
							<div class="csqr-field"><label class="csqr-field__label" for="<?php echo esc_attr( $uid . '-level' ); ?>"><?php esc_html_e( 'Error correction', 'anderson-qr-code-generator' ); ?></label>
								<select class="csqr-input" id="<?php echo esc_attr( $uid . '-level' ); ?>" data-design="qrCorrectLevel">
									<?php
									foreach ( array(
										'L' => __( 'Low: fewest dots', 'anderson-qr-code-generator' ),
										'M' => __( 'Medium (recommended)', 'anderson-qr-code-generator' ),
										'Q' => __( 'High', 'anderson-qr-code-generator' ),
										'H' => __( 'Highest: survives damage', 'anderson-qr-code-generator' ),
									) as $level => $label ) {
										echo '<option value="' . esc_attr( $level ) . '"' . selected( $settings['qrCorrectLevel'], $level, false ) . '>' . esc_html( $label ) . '</option>';
									}
									?>
								</select>
							</div>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>

		<figure class="csqr-generator__result">
			<div class="csqr-sheet is-empty">
				<div class="csqr-sheet__code" role="img" aria-label="<?php esc_attr_e( 'Your QR code', 'anderson-qr-code-generator' ); ?>"></div>
				<p class="csqr-sheet__empty"><?php esc_html_e( 'Your code appears here as you type.', 'anderson-qr-code-generator' ); ?></p>
			</div>
			<figcaption class="csqr-check" data-level="empty"></figcaption>
			<div class="csqr-export"></div>
		</figure>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Shortcode wrapper.
 *
 * @param array<string, mixed> $attributes Shortcode attributes.
 * @return string
 */
function csqr_shortcode( $attributes = array() ) {
	return csqr_render_qr_generator( csqr_shortcode_attributes( $attributes ) );
}

/**
 * WordPress lowercases shortcode attribute names (qrColorDark arrives as
 * qrcolordark). Map them back to the setting keys.
 *
 * @param array<string, mixed>|string $attributes Shortcode attributes.
 * @return array<string, mixed>
 */
function csqr_shortcode_attributes( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$keys       = array_merge( array_keys( csqr_get_default_settings() ), array( 'style', 'showTitle' ) );
	$map        = array_combine( array_map( 'strtolower', $keys ), $keys );
	$out        = array();
	foreach ( $attributes as $key => $value ) {
		$name = isset( $map[ strtolower( (string) $key ) ] ) ? $map[ strtolower( (string) $key ) ] : $key;
		if ( 'style' === $name ) {
			$name = 'styleId';
		}
		$out[ $name ] = in_array( $value, array( 'true', 'false' ), true ) ? 'true' === $value : $value;
	}
	return $out;
}
add_shortcode( 'client_side_qr', 'csqr_shortcode' );

/**
 * Activation: address rules for short links are flushed on the next request.
 *
 * @return void
 */
function csqr_activate() {
	update_option( 'csqr_flush_rewrite', 1 );
}
register_activation_hook( __FILE__, 'csqr_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
