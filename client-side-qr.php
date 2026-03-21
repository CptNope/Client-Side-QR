<?php
/**
 * Plugin Name:       Client-Side QR Code Generator
 * Description:       Generate privacy-friendly QR codes in the browser with a Gutenberg block and shortcode for campaigns, contact sharing, payments, and QR-driven site workflows.
 * Version:           4.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jeremy Anderson
 * Author URI:        https://jeremyanderson.tech
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       csqr
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSQR_VERSION', '4.1.0' );
define( 'CSQR_OPTION_NAME', 'csqr_settings' );

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

	return csqr_sanitize_settings( $settings );
}

/**
 * Add the settings page.
 *
 * @return void
 */
function csqr_add_settings_page() {
	add_options_page(
		__( 'Client-Side QR', 'csqr' ),
		__( 'Client-Side QR', 'csqr' ),
		'manage_options',
		'csqr-settings',
		'csqr_render_settings_page'
	);
}
add_action( 'admin_menu', 'csqr_add_settings_page' );

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
		<h1><?php esc_html_e( 'Client-Side QR Code Generator', 'csqr' ); ?></h1>
		<p><?php esc_html_e( 'Set lightweight defaults for new QR block and shortcode instances. Existing content can still override these values per instance.', 'csqr' ); ?></p>

		<form action="options.php" method="post">
			<?php settings_fields( 'csqr_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="csqr-qr-size"><?php esc_html_e( 'Default QR size', 'csqr' ); ?></label></th>
						<td>
							<input id="csqr-qr-size" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrSize]" type="number" min="100" max="800" step="10" value="<?php echo esc_attr( $settings['qrSize'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Default output size in pixels for new instances.', 'csqr' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-qr-color-dark"><?php esc_html_e( 'Default foreground color', 'csqr' ); ?></label></th>
						<td><input id="csqr-qr-color-dark" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrColorDark]" type="text" value="<?php echo esc_attr( $settings['qrColorDark'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-qr-color-light"><?php esc_html_e( 'Default background color', 'csqr' ); ?></label></th>
						<td><input id="csqr-qr-color-light" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrColorLight]" type="text" value="<?php echo esc_attr( $settings['qrColorLight'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="csqr-qr-correct-level"><?php esc_html_e( 'Default error correction', 'csqr' ); ?></label></th>
						<td>
							<select id="csqr-qr-correct-level" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[qrCorrectLevel]">
								<option value="L" <?php selected( $settings['qrCorrectLevel'], 'L' ); ?>><?php esc_html_e( 'Low (7%)', 'csqr' ); ?></option>
								<option value="M" <?php selected( $settings['qrCorrectLevel'], 'M' ); ?>><?php esc_html_e( 'Medium (15%)', 'csqr' ); ?></option>
								<option value="Q" <?php selected( $settings['qrCorrectLevel'], 'Q' ); ?>><?php esc_html_e( 'Quartile (25%)', 'csqr' ); ?></option>
								<option value="H" <?php selected( $settings['qrCorrectLevel'], 'H' ); ?>><?php esc_html_e( 'High (30%)', 'csqr' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Payload types enabled by default', 'csqr' ); ?></th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableUrl]" value="1" <?php checked( $settings['enableUrl'] ); ?> /> <?php esc_html_e( 'URL / Text', 'csqr' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableWifi]" value="1" <?php checked( $settings['enableWifi'] ); ?> /> <?php esc_html_e( 'WiFi', 'csqr' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableVcard]" value="1" <?php checked( $settings['enableVcard'] ); ?> /> <?php esc_html_e( 'vCard', 'csqr' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableEmail]" value="1" <?php checked( $settings['enableEmail'] ); ?> /> <?php esc_html_e( 'Email', 'csqr' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableSms]" value="1" <?php checked( $settings['enableSms'] ); ?> /> <?php esc_html_e( 'SMS', 'csqr' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enableCrypto]" value="1" <?php checked( $settings['enableCrypto'] ); ?> /> <?php esc_html_e( 'Crypto', 'csqr' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( CSQR_OPTION_NAME ); ?>[enablePaypal]" value="1" <?php checked( $settings['enablePaypal'] ); ?> /> <?php esc_html_e( 'PayPal', 'csqr' ); ?></label>
							</fieldset>
							<p class="description"><?php esc_html_e( 'If every payload type is disabled, URL / Text will be restored automatically so new instances always stay usable.', 'csqr' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
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
				'copySuccess'      => __( 'QR code image copied to your clipboard.', 'csqr' ),
				'copyUnavailable'  => __( 'Clipboard image copy is not supported in this browser.', 'csqr' ),
				'copyError'        => __( 'The QR code could not be copied right now.', 'csqr' ),
				'downloadReady'    => __( 'QR code ready for download.', 'csqr' ),
				'incompleteFields' => __( 'Complete the active fields to generate a QR code.', 'csqr' ),
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
			'url'    => __( 'URL', 'csqr' ),
			'wifi'   => __( 'WiFi', 'csqr' ),
			'vcard'  => __( 'vCard', 'csqr' ),
			'email'  => __( 'Email', 'csqr' ),
			'sms'    => __( 'SMS', 'csqr' ),
			'crypto' => __( 'Crypto', 'csqr' ),
			'paypal' => __( 'PayPal', 'csqr' ),
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
	>
		<div class="csqr-header">
			<h3 id="<?php echo esc_attr( $title_id ); ?>" class="csqr-title"><?php esc_html_e( 'Generate QR Code', 'csqr' ); ?></h3>
			<p id="<?php echo esc_attr( $description_id ); ?>" class="csqr-description"><?php esc_html_e( 'Build QR codes in the browser for links, campaigns, contact sharing, WiFi access, and payment flows.', 'csqr' ); ?></p>
		</div>

		<div class="csqr-input-group" aria-labelledby="<?php echo esc_attr( $title_id ); ?>" aria-describedby="<?php echo esc_attr( $description_id ); ?>">
			<div class="csqr-data-type-tabs" role="tablist" aria-label="<?php esc_attr_e( 'QR content type', 'csqr' ); ?>">
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
						__( 'URL or text', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_url' ) . '" type="url" class="csqr-input csqr-url-input" placeholder="' . esc_attr__( 'Enter a URL or plain text', 'csqr' ) . '" />'
					);
					?>
					<details class="csqr-utm-builder">
						<summary><?php esc_html_e( 'Optional campaign parameters', 'csqr' ); ?></summary>
						<div class="csqr-utm-grid">
							<?php
							csqr_render_field(
								$uid . '_utm_source',
								__( 'UTM source', 'csqr' ),
								'<input id="' . esc_attr( $uid . '_utm_source' ) . '" type="text" class="csqr-input csqr-utm-source" placeholder="' . esc_attr__( 'For example: print', 'csqr' ) . '" />'
							);
							csqr_render_field(
								$uid . '_utm_medium',
								__( 'UTM medium', 'csqr' ),
								'<input id="' . esc_attr( $uid . '_utm_medium' ) . '" type="text" class="csqr-input csqr-utm-medium" placeholder="' . esc_attr__( 'For example: poster', 'csqr' ) . '" />'
							);
							csqr_render_field(
								$uid . '_utm_campaign',
								__( 'UTM campaign', 'csqr' ),
								'<input id="' . esc_attr( $uid . '_utm_campaign' ) . '" type="text" class="csqr-input csqr-utm-campaign" placeholder="' . esc_attr__( 'Campaign name', 'csqr' ) . '" />'
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
						__( 'Network name (SSID)', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_wifi_ssid' ) . '" type="text" class="csqr-input csqr-wifi-ssid" autocomplete="off" />'
					);
					csqr_render_field(
						$uid . '_wifi_pass',
						__( 'Password', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_wifi_pass' ) . '" type="password" class="csqr-input csqr-wifi-pass" autocomplete="off" />'
					);
					?>
					<div class="csqr-row csqr-half-row">
						<?php
						csqr_render_field(
							$uid . '_wifi_enc',
							__( 'Security type', 'csqr' ),
							'<select id="' . esc_attr( $uid . '_wifi_enc' ) . '" class="csqr-input csqr-wifi-enc"><option value="WPA">' . esc_html__( 'WPA/WPA2', 'csqr' ) . '</option><option value="WEP">' . esc_html__( 'WEP', 'csqr' ) . '</option><option value="nopass">' . esc_html__( 'No password', 'csqr' ) . '</option></select>'
						);
						?>
						<label class="csqr-checkbox" for="<?php echo esc_attr( $uid . '_wifi_hidden' ); ?>">
							<input id="<?php echo esc_attr( $uid . '_wifi_hidden' ); ?>" type="checkbox" class="csqr-wifi-hidden" />
							<span><?php esc_html_e( 'Hidden network', 'csqr' ); ?></span>
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
							__( 'First name', 'csqr' ),
							'<input id="' . esc_attr( $uid . '_vcard_fname' ) . '" type="text" class="csqr-input csqr-vcard-fname" />'
						);
						csqr_render_field(
							$uid . '_vcard_lname',
							__( 'Last name', 'csqr' ),
							'<input id="' . esc_attr( $uid . '_vcard_lname' ) . '" type="text" class="csqr-input csqr-vcard-lname" />'
						);
						?>
					</div>
					<div class="csqr-row csqr-half-row">
						<?php
						csqr_render_field(
							$uid . '_vcard_phone',
							__( 'Phone number', 'csqr' ),
							'<input id="' . esc_attr( $uid . '_vcard_phone' ) . '" type="tel" class="csqr-input csqr-vcard-phone" />'
						);
						csqr_render_field(
							$uid . '_vcard_email',
							__( 'Email address', 'csqr' ),
							'<input id="' . esc_attr( $uid . '_vcard_email' ) . '" type="email" class="csqr-input csqr-vcard-email" />'
						);
						?>
					</div>
					<?php
					csqr_render_field(
						$uid . '_vcard_company',
						__( 'Company', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_vcard_company' ) . '" type="text" class="csqr-input csqr-vcard-company" />'
					);
					csqr_render_field(
						$uid . '_vcard_title',
						__( 'Job title', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_vcard_title' ) . '" type="text" class="csqr-input csqr-vcard-title" />'
					);
					csqr_render_field(
						$uid . '_vcard_url',
						__( 'Website', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_vcard_url' ) . '" type="url" class="csqr-input csqr-vcard-url" />'
					);
					csqr_render_field(
						$uid . '_vcard_address',
						__( 'Address', 'csqr' ),
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
						__( 'Email address', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_email_address' ) . '" type="email" class="csqr-input csqr-email-address" />'
					);
					csqr_render_field(
						$uid . '_email_subject',
						__( 'Subject', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_email_subject' ) . '" type="text" class="csqr-input csqr-email-subject" />'
					);
					csqr_render_field(
						$uid . '_email_body',
						__( 'Message body', 'csqr' ),
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
						__( 'Phone number', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_sms_phone' ) . '" type="tel" class="csqr-input csqr-sms-phone" />'
					);
					csqr_render_field(
						$uid . '_sms_message',
						__( 'Message', 'csqr' ),
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
						__( 'Currency', 'csqr' ),
						'<select id="' . esc_attr( $uid . '_crypto_currency' ) . '" class="csqr-input csqr-crypto-currency"><option value="bitcoin">' . esc_html__( 'Bitcoin (BTC)', 'csqr' ) . '</option><option value="ethereum">' . esc_html__( 'Ethereum (ETH)', 'csqr' ) . '</option><option value="litecoin">' . esc_html__( 'Litecoin (LTC)', 'csqr' ) . '</option></select>'
					);
					csqr_render_field(
						$uid . '_crypto_address',
						__( 'Wallet address', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_crypto_address' ) . '" type="text" class="csqr-input csqr-crypto-address" />'
					);
					csqr_render_field(
						$uid . '_crypto_amount',
						__( 'Amount', 'csqr' ),
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
						__( 'PayPal.me username', 'csqr' ),
						'<input id="' . esc_attr( $uid . '_paypal_username' ) . '" type="text" class="csqr-input csqr-paypal-username" />'
					);
					?>
					<div class="csqr-row csqr-half-row">
						<?php
						csqr_render_field(
							$uid . '_paypal_amount',
							__( 'Amount', 'csqr' ),
							'<input id="' . esc_attr( $uid . '_paypal_amount' ) . '" type="number" step="any" class="csqr-input csqr-paypal-amount" />'
						);
						csqr_render_field(
							$uid . '_paypal_currency',
							__( 'Currency', 'csqr' ),
							'<select id="' . esc_attr( $uid . '_paypal_currency' ) . '" class="csqr-input csqr-paypal-currency"><option value="USD">USD</option><option value="EUR">EUR</option><option value="GBP">GBP</option><option value="CAD">CAD</option><option value="AUD">AUD</option></select>'
						);
						?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $settings['allowUserColor'] || $settings['allowUserSize'] || $settings['allowUserCorrectLevel'] ) : ?>
				<div class="csqr-user-settings" aria-label="<?php esc_attr_e( 'QR design controls', 'csqr' ); ?>">
					<h4 class="csqr-section-title"><?php esc_html_e( 'Design options', 'csqr' ); ?></h4>

					<?php if ( $settings['allowUserColor'] ) : ?>
						<div class="csqr-setting-row">
							<label class="csqr-checkbox" for="<?php echo esc_attr( $uid . '_transparent_bg' ); ?>">
								<input id="<?php echo esc_attr( $uid . '_transparent_bg' ); ?>" type="checkbox" class="csqr-transparent-bg" />
								<span><?php esc_html_e( 'Transparent background', 'csqr' ); ?></span>
							</label>
						</div>
						<div class="csqr-setting-row csqr-setting-grid">
							<?php
							csqr_render_field(
								$uid . '_color_dark',
								__( 'Foreground color 1', 'csqr' ),
								'<input id="' . esc_attr( $uid . '_color_dark' ) . '" type="color" class="csqr-color-dark" value="' . esc_attr( $settings['qrColorDark'] ) . '" />'
							);
							if ( $settings['qrGradient'] ) {
								csqr_render_field(
									$uid . '_color_dark2',
									__( 'Foreground color 2', 'csqr' ),
									'<input id="' . esc_attr( $uid . '_color_dark2' ) . '" type="color" class="csqr-color-dark2" value="' . esc_attr( $settings['qrColorDark2'] ) . '" />'
								);
							}
							csqr_render_field(
								$uid . '_color_light',
								__( 'Background color', 'csqr' ),
								'<input id="' . esc_attr( $uid . '_color_light' ) . '" type="color" class="csqr-color-light" value="' . esc_attr( $settings['qrColorLight'] ) . '" />'
							);
							?>
						</div>
					<?php endif; ?>

					<?php if ( $settings['allowUserSize'] ) : ?>
						<div class="csqr-setting-row">
							<label class="csqr-range-label" for="<?php echo esc_attr( $size_input_id ); ?>">
								<span><?php esc_html_e( 'Size', 'csqr' ); ?></span>
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
								__( 'Error correction', 'csqr' ),
								'<select id="' . esc_attr( $level_input_id ) . '" class="csqr-input csqr-correct-level"><option value="L"' . selected( $settings['qrCorrectLevel'], 'L', false ) . '>' . esc_html__( 'Low (7%)', 'csqr' ) . '</option><option value="M"' . selected( $settings['qrCorrectLevel'], 'M', false ) . '>' . esc_html__( 'Medium (15%)', 'csqr' ) . '</option><option value="Q"' . selected( $settings['qrCorrectLevel'], 'Q', false ) . '>' . esc_html__( 'Quartile (25%)', 'csqr' ) . '</option><option value="H"' . selected( $settings['qrCorrectLevel'], 'H', false ) . '>' . esc_html__( 'High (30%)', 'csqr' ) . '</option></select>'
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="csqr-output-wrapper" hidden>
			<p id="<?php echo esc_attr( $output_label_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Generated QR code preview', 'csqr' ); ?></p>
			<div class="csqr-qrcode" role="img" aria-labelledby="<?php echo esc_attr( $output_label_id ); ?>"></div>
			<p id="<?php echo esc_attr( $status_id ); ?>" class="csqr-status" role="status" aria-live="polite"><?php esc_html_e( 'Complete the active fields to generate a QR code.', 'csqr' ); ?></p>
			<div class="csqr-downloads" hidden>
				<button type="button" class="csqr-download-png-btn"><?php esc_html_e( 'Download PNG', 'csqr' ); ?></button>
				<button type="button" class="csqr-download-svg-btn"><?php esc_html_e( 'Download SVG', 'csqr' ); ?></button>
				<button type="button" class="csqr-copy-btn"><?php esc_html_e( 'Copy image', 'csqr' ); ?></button>
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
