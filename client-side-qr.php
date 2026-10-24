<?php
/**
 * Plugin Name:       Anderson QR Code Generator
 * Plugin URI:        https://github.com/CptNope/Client-Side-QR
 * Description:       Generate privacy-friendly QR codes in the browser with a Gutenberg block and shortcode for campaigns, contact sharing, payments, and QR-driven site workflows.
 * Version:           4.2.0
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

define( 'CSQR_VERSION', '4.2.0' );
define( 'CSQR_OPTION_NAME', 'csqr_settings' );
define( 'CSQR_RELEASE_TRANSIENT', 'csqr_github_release_data' );
define( 'CSQR_RELEASE_CRON_HOOK', 'csqr_check_github_release_event' );
define( 'CSQR_SLUG', 'anderson-qr-code-generator' );
define( 'CSQR_AUTHOR_URL', 'https://jeremyanderson.tech/' );
define( 'CSQR_DOCS_URL', 'https://github.com/CptNope/Client-Side-QR#readme' );
define( 'CSQR_SUPPORT_URL', 'https://wordpress.org/support/plugin/' . CSQR_SLUG . '/' );

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
 * Sanitize persisted option settings from the admin screen.
 *
 * @param array<string, mixed> $settings Raw option values.
 * @return array<string, mixed>
 */
function csqr_sanitize_option_settings( $settings ) {
	$settings = is_array( $settings ) ? $settings : array();

	foreach ( csqr_get_payload_keys() as $payload_key ) {
		if ( ! array_key_exists( $payload_key, $settings ) ) {
			$settings[ $payload_key ] = false;
		}
	}

	foreach ( array( 'uiUseThemeColors', 'uiUseThemeFont' ) as $boolean_key ) {
		if ( ! array_key_exists( $boolean_key, $settings ) ) {
			$settings[ $boolean_key ] = false;
		}
	}

	return csqr_sanitize_settings( $settings );
}

/**
 * Add the settings page.
 *
 * @return void
 */
function csqr_add_settings_page() {
	add_options_page(
		__( 'Anderson QR Code Generator', 'anderson-qr-code-generator' ),
		__( 'QR Code Generator', 'anderson-qr-code-generator' ),
		'manage_options',
		'csqr-settings',
		'csqr_render_settings_page'
	);

	add_submenu_page(
		'options-general.php',
		__( 'QR Shortcode Builder', 'anderson-qr-code-generator' ),
		__( 'QR Shortcode Builder', 'anderson-qr-code-generator' ),
		'manage_options',
		'csqr-shortcode-builder',
		'csqr_render_shortcode_builder_page'
	);
}
add_action( 'admin_menu', 'csqr_add_settings_page' );

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
 * Enqueue assets needed on plugin admin pages.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function csqr_admin_enqueue_assets( $hook_suffix ) {
	$allowed_hooks = array( 'settings_page_csqr-settings', 'settings_page_csqr-shortcode-builder' );

	if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
		return;
	}

	wp_enqueue_style( 'csqr-style' );
	wp_enqueue_script( 'csqr-script' );
}
add_action( 'admin_enqueue_scripts', 'csqr_admin_enqueue_assets' );

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
 * Render a shortcode builder field row.
 *
 * @param string $name Field name.
 * @param string $label Field label.
 * @param string $field_html Field HTML.
 * @return void
 */
function csqr_render_builder_row( $name, $label, $field_html ) {
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td><?php echo $field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
	</tr>
	<?php
}

/**
 * Render the classic editor shortcode builder page.
 *
 * @return void
 */
function csqr_render_shortcode_builder_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$defaults  = csqr_get_settings();
	$values    = $defaults;
	$shortcode = csqr_build_shortcode_from_settings( $defaults );
	$preview   = csqr_render_qr_generator( $defaults );

	if ( isset( $_POST['csqr_shortcode_builder_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csqr_shortcode_builder_nonce'] ) ), 'csqr_shortcode_builder' ) ) {
		$raw_values = array();

		foreach ( array_keys( csqr_get_default_settings() ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				// Every key is sanitized again, by type, in csqr_sanitize_settings() below.
				$raw_values[ $key ] = 'logoUrl' === $key ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		foreach ( array( 'uiUseThemeColors', 'uiUseThemeFont', 'qrGradient', 'allowUserColor', 'allowUserSize', 'allowUserCorrectLevel', 'enableUrl', 'enableWifi', 'enableEmail', 'enableSms', 'enableVcard', 'enableCrypto', 'enablePaypal' ) as $boolean_key ) {
			$raw_values[ $boolean_key ] = isset( $_POST[ $boolean_key ] );
		}

		$values    = csqr_sanitize_settings( wp_parse_args( $raw_values, $defaults ) );
		$shortcode = csqr_build_shortcode_from_settings( $values );
		$preview   = csqr_render_qr_generator( $values );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'QR Shortcode Builder', 'anderson-qr-code-generator' ); ?></h1>
		<p><?php esc_html_e( 'Use this screen to configure frontend output for classic-editor or shortcode-based workflows, then copy the generated shortcode into a post, page, widget, or template area.', 'anderson-qr-code-generator' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'csqr_shortcode_builder', 'csqr_shortcode_builder_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<?php
					csqr_render_builder_row( 'uiUseThemeColors', __( 'Inherit theme colors', 'anderson-qr-code-generator' ), '<label><input id="uiUseThemeColors" name="uiUseThemeColors" type="checkbox" value="1"' . checked( $values['uiUseThemeColors'], true, false ) . ' /> ' . esc_html__( 'Use the active theme colors for the interface shell', 'anderson-qr-code-generator' ) . '</label>' );
					csqr_render_builder_row( 'uiUseThemeFont', __( 'Inherit theme font', 'anderson-qr-code-generator' ), '<label><input id="uiUseThemeFont" name="uiUseThemeFont" type="checkbox" value="1"' . checked( $values['uiUseThemeFont'], true, false ) . ' /> ' . esc_html__( 'Use the active theme font for the interface shell', 'anderson-qr-code-generator' ) . '</label>' );
					csqr_render_builder_row( 'uiSurfaceColor', __( 'Shell background override', 'anderson-qr-code-generator' ), '<input id="uiSurfaceColor" name="uiSurfaceColor" type="text" class="regular-text" value="' . esc_attr( $values['uiSurfaceColor'] ) . '" />' );
					csqr_render_builder_row( 'uiTextColor', __( 'Shell text override', 'anderson-qr-code-generator' ), '<input id="uiTextColor" name="uiTextColor" type="text" class="regular-text" value="' . esc_attr( $values['uiTextColor'] ) . '" />' );
					csqr_render_builder_row( 'uiAccentColor', __( 'Shell accent override', 'anderson-qr-code-generator' ), '<input id="uiAccentColor" name="uiAccentColor" type="text" class="regular-text" value="' . esc_attr( $values['uiAccentColor'] ) . '" />' );
					csqr_render_builder_row( 'uiFontFamily', __( 'Shell font override', 'anderson-qr-code-generator' ), '<input id="uiFontFamily" name="uiFontFamily" type="text" class="regular-text code" value="' . esc_attr( $values['uiFontFamily'] ) . '" />' );
					csqr_render_builder_row( 'qrColorDark', __( 'Foreground color 1', 'anderson-qr-code-generator' ), '<input id="qrColorDark" name="qrColorDark" type="text" class="regular-text" value="' . esc_attr( $values['qrColorDark'] ) . '" />' );
					csqr_render_builder_row( 'qrColorDark2', __( 'Foreground color 2', 'anderson-qr-code-generator' ), '<input id="qrColorDark2" name="qrColorDark2" type="text" class="regular-text" value="' . esc_attr( $values['qrColorDark2'] ) . '" />' );
					csqr_render_builder_row( 'qrColorLight', __( 'Background color', 'anderson-qr-code-generator' ), '<input id="qrColorLight" name="qrColorLight" type="text" class="regular-text" value="' . esc_attr( $values['qrColorLight'] ) . '" />' );
					csqr_render_builder_row( 'qrSize', __( 'Size', 'anderson-qr-code-generator' ), '<input id="qrSize" name="qrSize" type="number" min="100" max="800" step="10" class="small-text" value="' . esc_attr( (string) $values['qrSize'] ) . '" />' );
					csqr_render_builder_row( 'qrCorrectLevel', __( 'Error correction', 'anderson-qr-code-generator' ), '<select id="qrCorrectLevel" name="qrCorrectLevel"><option value="L"' . selected( $values['qrCorrectLevel'], 'L', false ) . '>' . esc_html__( 'Low (7%)', 'anderson-qr-code-generator' ) . '</option><option value="M"' . selected( $values['qrCorrectLevel'], 'M', false ) . '>' . esc_html__( 'Medium (15%)', 'anderson-qr-code-generator' ) . '</option><option value="Q"' . selected( $values['qrCorrectLevel'], 'Q', false ) . '>' . esc_html__( 'Quartile (25%)', 'anderson-qr-code-generator' ) . '</option><option value="H"' . selected( $values['qrCorrectLevel'], 'H', false ) . '>' . esc_html__( 'High (30%)', 'anderson-qr-code-generator' ) . '</option></select>' );
					csqr_render_builder_row( 'qrDotStyle', __( 'Dot style', 'anderson-qr-code-generator' ), '<select id="qrDotStyle" name="qrDotStyle"><option value="square"' . selected( $values['qrDotStyle'], 'square', false ) . '>' . esc_html__( 'Square', 'anderson-qr-code-generator' ) . '</option><option value="dots"' . selected( $values['qrDotStyle'], 'dots', false ) . '>' . esc_html__( 'Dots', 'anderson-qr-code-generator' ) . '</option><option value="rounded"' . selected( $values['qrDotStyle'], 'rounded', false ) . '>' . esc_html__( 'Rounded', 'anderson-qr-code-generator' ) . '</option><option value="extra-rounded"' . selected( $values['qrDotStyle'], 'extra-rounded', false ) . '>' . esc_html__( 'Extra Rounded', 'anderson-qr-code-generator' ) . '</option><option value="classy"' . selected( $values['qrDotStyle'], 'classy', false ) . '>' . esc_html__( 'Classy', 'anderson-qr-code-generator' ) . '</option><option value="classy-rounded"' . selected( $values['qrDotStyle'], 'classy-rounded', false ) . '>' . esc_html__( 'Classy Rounded', 'anderson-qr-code-generator' ) . '</option></select>' );
					csqr_render_builder_row( 'qrEyeStyle', __( 'Corner eye style', 'anderson-qr-code-generator' ), '<select id="qrEyeStyle" name="qrEyeStyle"><option value="square"' . selected( $values['qrEyeStyle'], 'square', false ) . '>' . esc_html__( 'Square', 'anderson-qr-code-generator' ) . '</option><option value="dot"' . selected( $values['qrEyeStyle'], 'dot', false ) . '>' . esc_html__( 'Dot', 'anderson-qr-code-generator' ) . '</option><option value="extra-rounded"' . selected( $values['qrEyeStyle'], 'extra-rounded', false ) . '>' . esc_html__( 'Extra Rounded', 'anderson-qr-code-generator' ) . '</option></select>' );
					csqr_render_builder_row( 'qrEyeColor', __( 'Corner eye color', 'anderson-qr-code-generator' ), '<input id="qrEyeColor" name="qrEyeColor" type="text" class="regular-text" value="' . esc_attr( $values['qrEyeColor'] ) . '" />' );
					csqr_render_builder_row( 'logoUrl', __( 'Logo URL', 'anderson-qr-code-generator' ), '<input id="logoUrl" name="logoUrl" type="url" class="regular-text code" value="' . esc_attr( $values['logoUrl'] ) . '" />' );
					csqr_render_builder_row( 'qrGradient', __( 'Use gradient', 'anderson-qr-code-generator' ), '<label><input id="qrGradient" name="qrGradient" type="checkbox" value="1"' . checked( $values['qrGradient'], true, false ) . ' /> ' . esc_html__( 'Enable foreground gradient', 'anderson-qr-code-generator' ) . '</label>' );
					csqr_render_builder_row( 'allowUserColor', __( 'Allow visitor color changes', 'anderson-qr-code-generator' ), '<label><input id="allowUserColor" name="allowUserColor" type="checkbox" value="1"' . checked( $values['allowUserColor'], true, false ) . ' /> ' . esc_html__( 'Allow users to change colors', 'anderson-qr-code-generator' ) . '</label>' );
					csqr_render_builder_row( 'allowUserSize', __( 'Allow visitor size changes', 'anderson-qr-code-generator' ), '<label><input id="allowUserSize" name="allowUserSize" type="checkbox" value="1"' . checked( $values['allowUserSize'], true, false ) . ' /> ' . esc_html__( 'Allow users to change size', 'anderson-qr-code-generator' ) . '</label>' );
					csqr_render_builder_row( 'allowUserCorrectLevel', __( 'Allow visitor error correction changes', 'anderson-qr-code-generator' ), '<label><input id="allowUserCorrectLevel" name="allowUserCorrectLevel" type="checkbox" value="1"' . checked( $values['allowUserCorrectLevel'], true, false ) . ' /> ' . esc_html__( 'Allow users to change error correction', 'anderson-qr-code-generator' ) . '</label>' );
					csqr_render_builder_row( 'payloads', __( 'Enabled payload types', 'anderson-qr-code-generator' ), '<fieldset><label><input name="enableUrl" type="checkbox" value="1"' . checked( $values['enableUrl'], true, false ) . ' /> ' . esc_html__( 'URL / Text', 'anderson-qr-code-generator' ) . '</label><br /><label><input name="enableWifi" type="checkbox" value="1"' . checked( $values['enableWifi'], true, false ) . ' /> ' . esc_html__( 'WiFi', 'anderson-qr-code-generator' ) . '</label><br /><label><input name="enableVcard" type="checkbox" value="1"' . checked( $values['enableVcard'], true, false ) . ' /> ' . esc_html__( 'vCard', 'anderson-qr-code-generator' ) . '</label><br /><label><input name="enableEmail" type="checkbox" value="1"' . checked( $values['enableEmail'], true, false ) . ' /> ' . esc_html__( 'Email', 'anderson-qr-code-generator' ) . '</label><br /><label><input name="enableSms" type="checkbox" value="1"' . checked( $values['enableSms'], true, false ) . ' /> ' . esc_html__( 'SMS', 'anderson-qr-code-generator' ) . '</label><br /><label><input name="enableCrypto" type="checkbox" value="1"' . checked( $values['enableCrypto'], true, false ) . ' /> ' . esc_html__( 'Crypto', 'anderson-qr-code-generator' ) . '</label><br /><label><input name="enablePaypal" type="checkbox" value="1"' . checked( $values['enablePaypal'], true, false ) . ' /> ' . esc_html__( 'PayPal', 'anderson-qr-code-generator' ) . '</label></fieldset>' );
					?>
				</tbody>
			</table>
			<?php submit_button( __( 'Generate Shortcode', 'anderson-qr-code-generator' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Generated shortcode', 'anderson-qr-code-generator' ); ?></h2>
		<p><?php esc_html_e( 'Copy this shortcode into the classic editor, a widget, or any shortcode-enabled area.', 'anderson-qr-code-generator' ); ?></p>
		<textarea class="large-text code" rows="3" readonly onclick="this.select();"><?php echo esc_textarea( $shortcode ); ?></textarea>

		<h2><?php esc_html_e( 'Preview', 'anderson-qr-code-generator' ); ?></h2>
		<p><?php esc_html_e( 'This preview uses the same frontend renderer as the shortcode and block output.', 'anderson-qr-code-generator' ); ?></p>
		<div style="max-width: 520px;">
			<?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php csqr_render_about(); ?>
	</div>
	<?php
}

/**
 * Render the settings page.
 *
 * @return void
 */
function csqr_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = csqr_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Anderson QR Code Generator', 'anderson-qr-code-generator' ); ?></h1>
		<p><?php esc_html_e( 'Set lightweight defaults for new QR block and shortcode instances. These defaults apply to the frontend output for both Gutenberg blocks and classic-editor shortcode usage, while existing content can still override them per instance.', 'anderson-qr-code-generator' ); ?></p>

		<form action="options.php" method="post">
			<?php settings_fields( 'csqr_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Frontend shell defaults', 'anderson-qr-code-generator' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[uiUseThemeColors]" value="1" <?php checked( $settings['uiUseThemeColors'] ); ?> />
									<?php esc_html_e( 'Inherit theme colors by default', 'anderson-qr-code-generator' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[uiUseThemeFont]" value="1" <?php checked( $settings['uiUseThemeFont'] ); ?> />
									<?php esc_html_e( 'Inherit theme font by default', 'anderson-qr-code-generator' ); ?>
								</label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'These controls affect the surrounding generator interface for both the block and shortcode output. QR code colors stay separate below.', 'anderson-qr-code-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-ui-surface-color"><?php esc_html_e( 'Shell background override', 'anderson-qr-code-generator' ); ?></label></th>
						<td>
							<input id="csqr-ui-surface-color" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[uiSurfaceColor]" type="text" value="<?php echo esc_attr( $settings['uiSurfaceColor'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Optional. Used when theme color inheritance is disabled for an instance or as the saved fallback default.', 'anderson-qr-code-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-ui-text-color"><?php esc_html_e( 'Shell text override', 'anderson-qr-code-generator' ); ?></label></th>
						<td><input id="csqr-ui-text-color" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[uiTextColor]" type="text" value="<?php echo esc_attr( $settings['uiTextColor'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-ui-accent-color"><?php esc_html_e( 'Shell accent override', 'anderson-qr-code-generator' ); ?></label></th>
						<td><input id="csqr-ui-accent-color" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[uiAccentColor]" type="text" value="<?php echo esc_attr( $settings['uiAccentColor'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-ui-font-family"><?php esc_html_e( 'Shell font override', 'anderson-qr-code-generator' ); ?></label></th>
						<td>
							<input id="csqr-ui-font-family" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[uiFontFamily]" type="text" value="<?php echo esc_attr( $settings['uiFontFamily'] ); ?>" class="regular-text code" />
							<p class="description"><?php esc_html_e( 'Optional CSS font-family value for the interface when theme font inheritance is disabled.', 'anderson-qr-code-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-qr-size"><?php esc_html_e( 'Default QR size', 'anderson-qr-code-generator' ); ?></label></th>
						<td>
							<input id="csqr-qr-size" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrSize]" type="number" min="100" max="800" step="10" value="<?php echo esc_attr( $settings['qrSize'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Default output size in pixels for new instances.', 'anderson-qr-code-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-qr-color-dark"><?php esc_html_e( 'Default foreground color', 'anderson-qr-code-generator' ); ?></label></th>
						<td><input id="csqr-qr-color-dark" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrColorDark]" type="text" value="<?php echo esc_attr( $settings['qrColorDark'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-qr-color-light"><?php esc_html_e( 'Default background color', 'anderson-qr-code-generator' ); ?></label></th>
						<td><input id="csqr-qr-color-light" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrColorLight]" type="text" value="<?php echo esc_attr( $settings['qrColorLight'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-qr-correct-level"><?php esc_html_e( 'Default error correction', 'anderson-qr-code-generator' ); ?></label></th>
						<td>
							<select id="csqr-qr-correct-level" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrCorrectLevel]">
								<option value="L" <?php selected( $settings['qrCorrectLevel'], 'L' ); ?>><?php esc_html_e( 'Low (7%)', 'anderson-qr-code-generator' ); ?></option>
								<option value="M" <?php selected( $settings['qrCorrectLevel'], 'M' ); ?>><?php esc_html_e( 'Medium (15%)', 'anderson-qr-code-generator' ); ?></option>
								<option value="Q" <?php selected( $settings['qrCorrectLevel'], 'Q' ); ?>><?php esc_html_e( 'Quartile (25%)', 'anderson-qr-code-generator' ); ?></option>
								<option value="H" <?php selected( $settings['qrCorrectLevel'], 'H' ); ?>><?php esc_html_e( 'High (30%)', 'anderson-qr-code-generator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Payload types enabled by default', 'anderson-qr-code-generator' ); ?></th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableUrl]" value="1" <?php checked( $settings['enableUrl'] ); ?> /> <?php esc_html_e( 'URL / Text', 'anderson-qr-code-generator' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableWifi]" value="1" <?php checked( $settings['enableWifi'] ); ?> /> <?php esc_html_e( 'WiFi', 'anderson-qr-code-generator' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableVcard]" value="1" <?php checked( $settings['enableVcard'] ); ?> /> <?php esc_html_e( 'vCard', 'anderson-qr-code-generator' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableEmail]" value="1" <?php checked( $settings['enableEmail'] ); ?> /> <?php esc_html_e( 'Email', 'anderson-qr-code-generator' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableSms]" value="1" <?php checked( $settings['enableSms'] ); ?> /> <?php esc_html_e( 'SMS', 'anderson-qr-code-generator' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableCrypto]" value="1" <?php checked( $settings['enableCrypto'] ); ?> /> <?php esc_html_e( 'Crypto', 'anderson-qr-code-generator' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enablePaypal]" value="1" <?php checked( $settings['enablePaypal'] ); ?> /> <?php esc_html_e( 'PayPal', 'anderson-qr-code-generator' ); ?></label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'If every payload type is disabled, URL / Text will be restored automatically so new instances always stay usable.', 'anderson-qr-code-generator' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'options-general.php?page=csqr-shortcode-builder' ) ); ?>"><?php esc_html_e( 'Open QR Shortcode Builder', 'anderson-qr-code-generator' ); ?></a></p>
		<?php csqr_render_about(); ?>
	</div>
	<?php
}

/**
 * Register frontend and editor assets.
 *
 * @return void
 */
function csqr_register_assets() {
	$asset_url = plugin_dir_url( __FILE__ ) . 'assets/';

	wp_register_script(
		'csqr-qrcode-styling',
		$asset_url . 'vendor/qr-code-styling.js',
		array(),
		'1.5.0',
		true
	);

	wp_register_script(
		'csqr-script',
		$asset_url . 'qr-script.js',
		array( 'csqr-qrcode-styling' ),
		CSQR_VERSION,
		true
	);

	wp_localize_script(
		'csqr-script',
		'csqrFrontendConfig',
		array(
			'i18n' => array(
				'copySuccess'      => __( 'QR code image copied to your clipboard.', 'anderson-qr-code-generator' ),
				'copyUnavailable'  => __( 'Clipboard image copy is not supported in this browser.', 'anderson-qr-code-generator' ),
				'copyError'        => __( 'The QR code could not be copied right now.', 'anderson-qr-code-generator' ),
				'downloadReady'    => __( 'QR code ready for download.', 'anderson-qr-code-generator' ),
				'incompleteFields' => __( 'Complete the active fields to generate a QR code.', 'anderson-qr-code-generator' ),
			),
		)
	);

	wp_register_style(
		'csqr-style',
		$asset_url . 'qr-style.css',
		array(),
		CSQR_VERSION
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

	wp_register_script(
		'csqr-block-script',
		plugin_dir_url( __FILE__ ) . 'assets/qr-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'csqr-qrcode-styling' ),
		CSQR_VERSION,
		true
	);

	wp_localize_script(
		'csqr-block-script',
		'csqrBlockConfig',
		array(
			'defaults' => csqr_get_settings(),
			'i18n'     => array(
				'previewUrl' => home_url( '/' ),
			),
		)
	);

	wp_set_script_translations( 'csqr-block-script', 'anderson-qr-code-generator' );

	register_block_type(
		'csqr/generator',
		array(
			'api_version'     => 2,
			'editor_script'   => 'csqr-block-script',
			'style'           => 'csqr-style',
			'render_callback' => 'csqr_render_qr_generator',
		)
	);
}
add_action( 'init', 'csqr_register_block' );

/**
 * Render a field label/input pair.
 *
 * @param string $for Input ID.
 * @param string $label Visible label.
 * @param string $field_html Field markup.
 * @return void
 */
function csqr_render_field( $for, $label, $field_html ) {
	?>
	<label class="csqr-field" for="<?php echo esc_attr( $for ); ?>">
		<span class="csqr-field-label"><?php echo esc_html( $label ); ?></span>
		<?php echo $field_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</label>
	<?php
}

/**
 * Render the QR generator markup.
 *
 * @param array<string, mixed> $attributes Block or shortcode attributes.
 * @return string
 */
function csqr_render_qr_generator( $attributes = array() ) {
	$settings = csqr_get_instance_settings( $attributes );
	$uid      = wp_unique_id( 'csqr_' );

	$payload_labels = apply_filters(
		'csqr_payload_labels',
		array(
			'url'    => __( 'URL', 'anderson-qr-code-generator' ),
			'wifi'   => __( 'WiFi', 'anderson-qr-code-generator' ),
			'vcard'  => __( 'vCard', 'anderson-qr-code-generator' ),
			'email'  => __( 'Email', 'anderson-qr-code-generator' ),
			'sms'    => __( 'SMS', 'anderson-qr-code-generator' ),
			'crypto' => __( 'Crypto', 'anderson-qr-code-generator' ),
			'paypal' => __( 'PayPal', 'anderson-qr-code-generator' ),
		)
	);

	$enabled_payloads = array();
	foreach ( $payload_labels as $payload => $label ) {
		$key = 'enable' . ucfirst( $payload );
		if ( ! empty( $settings[ $key ] ) ) {
			$enabled_payloads[ $payload ] = $label;
		}
	}

	if ( empty( $enabled_payloads ) ) {
		$enabled_payloads['url'] = $payload_labels['url'];
	}

	$active_payload   = array_key_first( $enabled_payloads );
	$title_id         = $uid . '_title';
	$description_id   = $uid . '_description';
	$status_id        = $uid . '_status';
	$output_label_id  = $uid . '_output_label';
	$size_input_id    = $uid . '_size';
	$level_input_id   = $uid . '_error_level';
	$container_style  = csqr_get_container_style_attribute( $settings );

	wp_enqueue_script( 'csqr-script' );
	wp_enqueue_style( 'csqr-style' );

	ob_start();
	?>
	<div
		class="csqr-container"
		data-color-dark="<?php echo esc_attr( $settings['qrColorDark'] ); ?>"
		data-color-dark2="<?php echo esc_attr( $settings['qrColorDark2'] ); ?>"
		data-color-light="<?php echo esc_attr( $settings['qrColorLight'] ); ?>"
		data-size="<?php echo esc_attr( $settings['qrSize'] ); ?>"
		data-correct-level="<?php echo esc_attr( $settings['qrCorrectLevel'] ); ?>"
		data-dot-style="<?php echo esc_attr( $settings['qrDotStyle'] ); ?>"
		data-eye-style="<?php echo esc_attr( $settings['qrEyeStyle'] ); ?>"
		data-eye-color="<?php echo esc_attr( $settings['qrEyeColor'] ); ?>"
		data-gradient="<?php echo $settings['qrGradient'] ? 'true' : 'false'; ?>"
		data-logo-url="<?php echo esc_url( $settings['logoUrl'] ); ?>"
		<?php if ( '' !== $container_style ) : ?>
			style="<?php echo esc_attr( $container_style ); ?>"
		<?php endif; ?>
	>
		<div class="csqr-header">
			<h3 id="<?php echo esc_attr( $title_id ); ?>" class="csqr-title"><?php esc_html_e( 'Generate QR Code', 'anderson-qr-code-generator' ); ?></h3>
			<p id="<?php echo esc_attr( $description_id ); ?>" class="csqr-description"><?php esc_html_e( 'Build QR codes in the browser for links, campaigns, contact sharing, WiFi access, and payment flows.', 'anderson-qr-code-generator' ); ?></p>
		</div>

		<div class="csqr-input-group" aria-labelledby="<?php echo esc_attr( $title_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>">
			<div class="csqr-data-type-tabs" role="tablist" aria-label="<?php esc_attr_e( 'QR content type', 'anderson-qr-code-generator' ); ?>">
				<?php foreach ( $enabled_payloads as $payload => $label ) : ?>
					<?php
					$is_active = $active_payload === $payload;
					$tab_id    = $uid . '_tab_' . $payload;
					$panel_id  = $uid . '_panel_' . $payload;
					?>
					<button
						type="button"
						id="<?php echo esc_attr( $tab_id ); ?>"
						class="csqr-tab <?php echo $is_active ? 'active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
						aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
						data-type="<?php echo esc_attr( $payload ); ?>"
					>
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php if ( ! empty( $settings['enableUrl'] ) ) : ?>
				<section class="csqr-fields-container csqr-url-fields" id="<?php echo esc_attr( $uid . '_panel_url' ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '_tab_url' ); ?>" <?php echo 'url' === $active_payload ? '' : 'hidden'; ?>>
					<?php
					csqr_render_field(
						$uid . '_url',
						__( 'URL or text', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_url' ) . '" type="url" class="csqr-input csqr-url-input" placeholder="' . esc_attr__( 'Enter a URL or plain text', 'anderson-qr-code-generator' ) . '" />'
					);
					?>
					<details class="csqr-utm-builder">
						<summary><?php esc_html_e( 'Optional campaign parameters', 'anderson-qr-code-generator' ); ?></summary>
						<div class="csqr-utm-grid">
							<?php
							csqr_render_field(
								$uid . '_utm_source',
								__( 'UTM source', 'anderson-qr-code-generator' ),
								'<input id="' . esc_attr( $uid . '_utm_source' ) . '" type="text" class="csqr-input csqr-utm-source" placeholder="' . esc_attr__( 'For example: print', 'anderson-qr-code-generator' ) . '" />'
							);
							csqr_render_field(
								$uid . '_utm_medium',
								__( 'UTM medium', 'anderson-qr-code-generator' ),
								'<input id="' . esc_attr( $uid . '_utm_medium' ) . '" type="text" class="csqr-input csqr-utm-medium" placeholder="' . esc_attr__( 'For example: poster', 'anderson-qr-code-generator' ) . '" />'
							);
							csqr_render_field(
								$uid . '_utm_campaign',
								__( 'UTM campaign', 'anderson-qr-code-generator' ),
								'<input id="' . esc_attr( $uid . '_utm_campaign' ) . '" type="text" class="csqr-input csqr-utm-campaign" placeholder="' . esc_attr__( 'Campaign name', 'anderson-qr-code-generator' ) . '" />'
							);
							?>
						</div>
					</details>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $settings['enableWifi'] ) ) : ?>
				<section class="csqr-fields-container csqr-wifi-fields" id="<?php echo esc_attr( $uid . '_panel_wifi' ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '_tab_wifi' ); ?>" <?php echo 'wifi' === $active_payload ? '' : 'hidden'; ?>>
					<?php
					csqr_render_field(
						$uid . '_wifi_ssid',
						__( 'Network name (SSID)', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_wifi_ssid' ) . '" type="text" class="csqr-input csqr-wifi-ssid" autocomplete="off" />'
					);
					csqr_render_field(
						$uid . '_wifi_pass',
						__( 'Password', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_wifi_pass' ) . '" type="password" class="csqr-input csqr-wifi-pass" autocomplete="off" />'
					);
					?>
					<div class="csqr-row csqr-half-row">
						<?php
						csqr_render_field(
							$uid . '_wifi_enc',
							__( 'Security type', 'anderson-qr-code-generator' ),
							'<select id="' . esc_attr( $uid . '_wifi_enc' ) . '" class="csqr-input csqr-wifi-enc"><option value="WPA">' . esc_html__( 'WPA/WPA2', 'anderson-qr-code-generator' ) . '</option><option value="WEP">' . esc_html__( 'WEP', 'anderson-qr-code-generator' ) . '</option><option value="nopass">' . esc_html__( 'No password', 'anderson-qr-code-generator' ) . '</option></select>'
						);
						?>
						<label class="csqr-checkbox" for="<?php echo esc_attr( $uid . '_wifi_hidden' ); ?>">
							<input id="<?php echo esc_attr( $uid . '_wifi_hidden' ); ?>" type="checkbox" class="csqr-wifi-hidden" />
							<span><?php esc_html_e( 'Hidden network', 'anderson-qr-code-generator' ); ?></span>
						</label>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $settings['enableVcard'] ) ) : ?>
				<section class="csqr-fields-container csqr-vcard-fields" id="<?php echo esc_attr( $uid . '_panel_vcard' ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '_tab_vcard' ); ?>" <?php echo 'vcard' === $active_payload ? '' : 'hidden'; ?>>
					<div class="csqr-row csqr-half-row">
						<?php
						csqr_render_field(
							$uid . '_vcard_fname',
							__( 'First name', 'anderson-qr-code-generator' ),
							'<input id="' . esc_attr( $uid . '_vcard_fname' ) . '" type="text" class="csqr-input csqr-vcard-fname" />'
						);
						csqr_render_field(
							$uid . '_vcard_lname',
							__( 'Last name', 'anderson-qr-code-generator' ),
							'<input id="' . esc_attr( $uid . '_vcard_lname' ) . '" type="text" class="csqr-input csqr-vcard-lname" />'
						);
						?>
					</div>
					<div class="csqr-row csqr-half-row">
						<?php
						csqr_render_field(
							$uid . '_vcard_phone',
							__( 'Phone number', 'anderson-qr-code-generator' ),
							'<input id="' . esc_attr( $uid . '_vcard_phone' ) . '" type="tel" class="csqr-input csqr-vcard-phone" />'
						);
						csqr_render_field(
							$uid . '_vcard_email',
							__( 'Email address', 'anderson-qr-code-generator' ),
							'<input id="' . esc_attr( $uid . '_vcard_email' ) . '" type="email" class="csqr-input csqr-vcard-email" />'
						);
						?>
					</div>
					<?php
					csqr_render_field(
						$uid . '_vcard_company',
						__( 'Company', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_vcard_company' ) . '" type="text" class="csqr-input csqr-vcard-company" />'
					);
					csqr_render_field(
						$uid . '_vcard_title',
						__( 'Job title', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_vcard_title' ) . '" type="text" class="csqr-input csqr-vcard-title" />'
					);
					csqr_render_field(
						$uid . '_vcard_url',
						__( 'Website', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_vcard_url' ) . '" type="url" class="csqr-input csqr-vcard-url" />'
					);
					csqr_render_field(
						$uid . '_vcard_address',
						__( 'Address', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_vcard_address' ) . '" type="text" class="csqr-input csqr-vcard-address" />'
					);
					?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $settings['enableEmail'] ) ) : ?>
				<section class="csqr-fields-container csqr-email-fields" id="<?php echo esc_attr( $uid . '_panel_email' ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '_tab_email' ); ?>" <?php echo 'email' === $active_payload ? '' : 'hidden'; ?>>
					<?php
					csqr_render_field(
						$uid . '_email_address',
						__( 'Email address', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_email_address' ) . '" type="email" class="csqr-input csqr-email-address" />'
					);
					csqr_render_field(
						$uid . '_email_subject',
						__( 'Subject', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_email_subject' ) . '" type="text" class="csqr-input csqr-email-subject" />'
					);
					csqr_render_field(
						$uid . '_email_body',
						__( 'Message body', 'anderson-qr-code-generator' ),
						'<textarea id="' . esc_attr( $uid . '_email_body' ) . '" class="csqr-input csqr-email-body"></textarea>'
					);
					?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $settings['enableSms'] ) ) : ?>
				<section class="csqr-fields-container csqr-sms-fields" id="<?php echo esc_attr( $uid . '_panel_sms' ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '_tab_sms' ); ?>" <?php echo 'sms' === $active_payload ? '' : 'hidden'; ?>>
					<?php
					csqr_render_field(
						$uid . '_sms_phone',
						__( 'Phone number', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_sms_phone' ) . '" type="tel" class="csqr-input csqr-sms-phone" />'
					);
					csqr_render_field(
						$uid . '_sms_message',
						__( 'Message', 'anderson-qr-code-generator' ),
						'<textarea id="' . esc_attr( $uid . '_sms_message' ) . '" class="csqr-input csqr-sms-message"></textarea>'
					);
					?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $settings['enableCrypto'] ) ) : ?>
				<section class="csqr-fields-container csqr-crypto-fields" id="<?php echo esc_attr( $uid . '_panel_crypto' ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '_tab_crypto' ); ?>" <?php echo 'crypto' === $active_payload ? '' : 'hidden'; ?>>
					<?php
					csqr_render_field(
						$uid . '_crypto_currency',
						__( 'Currency', 'anderson-qr-code-generator' ),
						'<select id="' . esc_attr( $uid . '_crypto_currency' ) . '" class="csqr-input csqr-crypto-currency"><option value="bitcoin">' . esc_html__( 'Bitcoin (BTC)', 'anderson-qr-code-generator' ) . '</option><option value="ethereum">' . esc_html__( 'Ethereum (ETH)', 'anderson-qr-code-generator' ) . '</option><option value="litecoin">' . esc_html__( 'Litecoin (LTC)', 'anderson-qr-code-generator' ) . '</option></select>'
					);
					csqr_render_field(
						$uid . '_crypto_address',
						__( 'Wallet address', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_crypto_address' ) . '" type="text" class="csqr-input csqr-crypto-address" />'
					);
					csqr_render_field(
						$uid . '_crypto_amount',
						__( 'Amount', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_crypto_amount' ) . '" type="number" step="any" class="csqr-input csqr-crypto-amount" />'
					);
					?>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $settings['enablePaypal'] ) ) : ?>
				<section class="csqr-fields-container csqr-paypal-fields" id="<?php echo esc_attr( $uid . '_panel_paypal' ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $uid . '_tab_paypal' ); ?>" <?php echo 'paypal' === $active_payload ? '' : 'hidden'; ?>>
					<?php
					csqr_render_field(
						$uid . '_paypal_username',
						__( 'PayPal.me username', 'anderson-qr-code-generator' ),
						'<input id="' . esc_attr( $uid . '_paypal_username' ) . '" type="text" class="csqr-input csqr-paypal-username" />'
					);
					?>
					<div class="csqr-row csqr-half-row">
						<?php
						csqr_render_field(
							$uid . '_paypal_amount',
							__( 'Amount', 'anderson-qr-code-generator' ),
							'<input id="' . esc_attr( $uid . '_paypal_amount' ) . '" type="number" step="any" class="csqr-input csqr-paypal-amount" />'
						);
						csqr_render_field(
							$uid . '_paypal_currency',
							__( 'Currency', 'anderson-qr-code-generator' ),
							'<select id="' . esc_attr( $uid . '_paypal_currency' ) . '" class="csqr-input csqr-paypal-currency"><option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option><option value="CAD">CAD</option><option value="AUD">AUD</option></select>'
						);
						?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $settings['allowUserColor'] || $settings['allowUserSize'] || $settings['allowUserCorrectLevel'] ) : ?>
				<div class="csqr-user-settings" aria-label="<?php esc_attr_e( 'QR design controls', 'anderson-qr-code-generator' ); ?>">
					<h4 class="csqr-section-title"><?php esc_html_e( 'Design options', 'anderson-qr-code-generator' ); ?></h4>

					<?php if ( $settings['allowUserColor'] ) : ?>
						<div class="csqr-setting-row">
							<label class="csqr-checkbox" for="<?php echo esc_attr( $uid . '_transparent_bg' ); ?>">
								<input id="<?php echo esc_attr( $uid . '_transparent_bg' ); ?>" type="checkbox" class="csqr-transparent-bg" />
								<span><?php esc_html_e( 'Transparent background', 'anderson-qr-code-generator' ); ?></span>
							</label>
						</div>
						<div class="csqr-setting-row csqr-setting-grid">
							<?php
							csqr_render_field(
								$uid . '_color_dark',
								__( 'Foreground color 1', 'anderson-qr-code-generator' ),
								'<input id="' . esc_attr( $uid . '_color_dark' ) . '" type="color" class="csqr-color-dark" value="' . esc_attr( $settings['qrColorDark'] ) . '" />'
							);
							if ( $settings['qrGradient'] ) {
								csqr_render_field(
									$uid . '_color_dark2',
									__( 'Foreground color 2', 'anderson-qr-code-generator' ),
									'<input id="' . esc_attr( $uid . '_color_dark2' ) . '" type="color" class="csqr-color-dark2" value="' . esc_attr( $settings['qrColorDark2'] ) . '" />'
								);
							}
							csqr_render_field(
								$uid . '_color_light',
								__( 'Background color', 'anderson-qr-code-generator' ),
								'<input id="' . esc_attr( $uid . '_color_light' ) . '" type="color" class="csqr-color-light" value="' . esc_attr( $settings['qrColorLight'] ) . '" />'
							);
							?>
						</div>
					<?php endif; ?>

					<?php if ( $settings['allowUserSize'] ) : ?>
						<div class="csqr-setting-row">
							<label class="csqr-range-label" for="<?php echo esc_attr( $size_input_id ); ?>">
								<span><?php esc_html_e( 'Size', 'anderson-qr-code-generator' ); ?></span>
								<output class="csqr-size-val" for="<?php echo esc_attr( $size_input_id ); ?>"><?php echo esc_html( (string) $settings['qrSize'] ); ?></output>
							</label>
							<input id="<?php echo esc_attr( $size_input_id ); ?>" type="range" class="csqr-size-input" min="100" max="600" step="10" value="<?php echo esc_attr( $settings['qrSize'] ); ?>" />
						</div>
					<?php endif; ?>

					<?php if ( $settings['allowUserCorrectLevel'] ) : ?>
						<div class="csqr-setting-row">
							<?php
							csqr_render_field(
								$level_input_id,
								__( 'Error correction', 'anderson-qr-code-generator' ),
								'<select id="' . esc_attr( $level_input_id ) . '" class="csqr-input csqr-correct-level"><option value="L"' . selected( $settings['qrCorrectLevel'], 'L', false ) . '>' . esc_html__( 'Low (7%)', 'anderson-qr-code-generator' ) . '</option><option value="M"' . selected( $settings['qrCorrectLevel'], 'M', false ) . '>' . esc_html__( 'Medium (15%)', 'anderson-qr-code-generator' ) . '</option><option value="Q"' . selected( $settings['qrCorrectLevel'], 'Q', false ) . '>' . esc_html__( 'Quartile (25%)', 'anderson-qr-code-generator' ) . '</option><option value="H"' . selected( $settings['qrCorrectLevel'], 'H', false ) . '>' . esc_html__( 'High (30%)', 'anderson-qr-code-generator' ) . '</option></select>'
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="csqr-output-wrapper" hidden>
			<p id="<?php echo esc_attr( $output_label_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Generated QR code preview', 'anderson-qr-code-generator' ); ?></p>
			<div class="csqr-qrcode" role="img" aria-labelledby="<?php echo esc_attr( $output_label_id ); ?>"></div>
			<p id="<?php echo esc_attr( $status_id ); ?>" class="csqr-status" role="status" aria-live="polite"><?php esc_html_e( 'Complete the active fields to generate a QR code.', 'anderson-qr-code-generator' ); ?></p>
			<div class="csqr-downloads" hidden>
				<button type="button" class="csqr-download-png-btn"><?php esc_html_e( 'Download PNG', 'anderson-qr-code-generator' ); ?></button>
				<button type="button" class="csqr-download-svg-btn"><?php esc_html_e( 'Download SVG', 'anderson-qr-code-generator' ); ?></button>
				<button type="button" class="csqr-copy-btn"><?php esc_html_e( 'Copy image', 'anderson-qr-code-generator' ); ?></button>
			</div>
		</div>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Shortcode wrapper.
 *
 * @param array<string, mixed> $attributes Shortcode attributes.
 * @return string
 */
function csqr_shortcode( $attributes = array() ) {
	return csqr_render_qr_generator( $attributes );
}
add_shortcode( 'client_side_qr', 'csqr_shortcode' );
