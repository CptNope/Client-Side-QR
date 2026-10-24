<?php
/**
 * Admin screens: the "QR Codes" menu, settings, and the shortcode builder.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The QR Codes menu: Settings, Short links, Saved styles, Shortcode builder.
 *
 * @return void
 */
function csqr_add_settings_page() {
	$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Menu icon.
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M2 2h7v7H2zm2 2v3h3V4zm7-2h7v7h-7zm2 2v3h3V4zM2 11h7v7H2zm2 2v3h3v-3zm7-2h2v2h-2zm2 2h2v2h-2zm2-2h3v2h-3zm-4 4h2v3h-2zm4 0h3v3h-3z"/></svg>'
	);

	add_menu_page(
		__( 'QR Codes', 'anderson-qr-code-generator' ),
		__( 'QR Codes', 'anderson-qr-code-generator' ),
		'manage_options',
		'csqr-settings',
		'csqr_render_settings_page',
		$icon,
		58
	);
	add_submenu_page( 'csqr-settings', __( 'QR code settings', 'anderson-qr-code-generator' ), __( 'Settings', 'anderson-qr-code-generator' ), 'manage_options', 'csqr-settings', 'csqr_render_settings_page' );
	add_submenu_page( 'csqr-settings', __( 'Short links', 'anderson-qr-code-generator' ), __( 'Short links', 'anderson-qr-code-generator' ), 'manage_options', 'edit.php?post_type=' . CSQR_LINK_TYPE, '', 10 );
	add_submenu_page( 'csqr-settings', __( 'Saved styles', 'anderson-qr-code-generator' ), __( 'Saved styles', 'anderson-qr-code-generator' ), 'manage_options', 'csqr-styles', 'csqr_render_styles_page', 20 );
	add_submenu_page( 'csqr-settings', __( 'Shortcode builder', 'anderson-qr-code-generator' ), __( 'Shortcode builder', 'anderson-qr-code-generator' ), 'manage_options', 'csqr-shortcode-builder', 'csqr_render_shortcode_builder_page', 30 );
}
add_action( 'admin_menu', 'csqr_add_settings_page' );

/**
 * Keep the QR Codes menu open on short link screens.
 *
 * @param string $parent_file Parent menu.
 * @return string
 */
function csqr_menu_parent( $parent_file ) {
	global $submenu_file;
	$screen = get_current_screen();
	if ( $screen && CSQR_LINK_TYPE === $screen->post_type ) {
		$submenu_file = 'edit.php?post_type=' . CSQR_LINK_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Highlights the right submenu item.
		return 'csqr-settings';
	}
	return $parent_file;
}
add_filter( 'parent_file', 'csqr_menu_parent' );

/**
 * Old Settings → QR Code Generator bookmarks land on the new page.
 *
 * @return void
 */
function csqr_redirect_old_settings_urls() {
	global $pagenow;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'options-general.php' === $pagenow && in_array( $page, array( 'csqr-settings', 'csqr-shortcode-builder' ), true ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}
}
add_action( 'admin_init', 'csqr_redirect_old_settings_urls' );

/**
 * Is this one of the plugin's admin screens?
 *
 * @return bool
 */
function csqr_is_admin_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return false;
	}
	return false !== strpos( $screen->id, 'csqr' ) || CSQR_LINK_TYPE === $screen->post_type || in_array( $screen->post_type, csqr_page_qr_post_types(), true ) && 'post' === $screen->base;
}

/**
 * Admin assets.
 *
 * @return void
 */
function csqr_admin_enqueue_assets() {
	if ( ! csqr_is_admin_screen() ) {
		return;
	}
	wp_enqueue_style( 'csqr-style' );
	wp_enqueue_style( 'csqr-admin', CSQR_URL . 'assets/qr-admin.css', array( 'csqr-style' ), CSQR_VERSION );
	wp_enqueue_media();
	wp_enqueue_script( 'csqr-admin', CSQR_URL . 'assets/qr-admin.js', array( 'csqr-ui' ), CSQR_VERSION, true );
	wp_localize_script(
		'csqr-admin',
		'csqrAdminConfig',
		array(
			'i18n' => array(
				'chooseLogo' => __( 'Choose a logo', 'anderson-qr-code-generator' ),
				'useLogo'    => __( 'Use this image', 'anderson-qr-code-generator' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'csqr_admin_enqueue_assets' );

/**
 * Sanitize the settings form. Unticked checkboxes aren't sent, so they're
 * filled in as false here.
 *
 * @param mixed $settings Submitted settings.
 * @return array<string, mixed>
 */
function csqr_sanitize_option_settings( $settings ) {
	$settings = is_array( $settings ) ? $settings : array();
	$flags    = array_merge( csqr_get_payload_keys(), array( 'uiUseThemeColors', 'uiUseThemeFont', 'qrGradient', 'transparent', 'allowUserColor', 'allowUserSize', 'allowUserCorrectLevel', 'pageQrDownloads' ) );
	foreach ( $flags as $flag ) {
		$settings[ $flag ] = ! empty( $settings[ $flag ] );
	}
	if ( ! isset( $settings['pageQrTypes'] ) ) {
		$settings['pageQrTypes'] = array();
	}
	return csqr_sanitize_settings( $settings );
}

/**
 * Checkbox helper.
 *
 * @param string $key     Setting key.
 * @param bool   $checked State.
 * @param string $label   Label.
 * @return string
 */
function csqr_setting_checkbox( $key, $checked, $label ) {
	return '<label><input type="checkbox" name="' . esc_attr( CSQR_OPTION_NAME . '[' . $key . ']' ) . '" value="1" ' . checked( $checked, true, false ) . ' /> ' . esc_html( $label ) . '</label>';
}

/**
 * Settings page.
 *
 * @return void
 */
function csqr_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s     = csqr_get_settings();
	$o     = CSQR_OPTION_NAME;
	$types = array();
	foreach ( csqr_page_qr_post_types() as $type ) {
		$obj = get_post_type_object( $type );
		if ( $obj ) {
			$types[ $type ] = $obj->labels->name;
		}
	}
	$payloads = array(
		'enableUrl'    => __( 'Link or text', 'anderson-qr-code-generator' ),
		'enableWifi'   => __( 'WiFi', 'anderson-qr-code-generator' ),
		'enableVcard'  => __( 'Contact card', 'anderson-qr-code-generator' ),
		'enableEmail'  => __( 'Email', 'anderson-qr-code-generator' ),
		'enableSms'    => __( 'Text message', 'anderson-qr-code-generator' ),
		'enableCrypto' => __( 'Crypto', 'anderson-qr-code-generator' ),
		'enablePaypal' => __( 'PayPal', 'anderson-qr-code-generator' ),
	);
	?>
	<div class="wrap csqr-admin">
		<h1><?php esc_html_e( 'QR code settings', 'anderson-qr-code-generator' ); ?></h1>
		<p class="csqr-admin__lede"><?php esc_html_e( 'Defaults for every QR code on the site. Blocks can override them, or use a saved style.', 'anderson-qr-code-generator' ); ?></p>
		<?php settings_errors(); ?>

		<form action="options.php" method="post">
			<?php settings_fields( 'csqr_settings_group' ); ?>

			<h2 id="csqr-design"><?php esc_html_e( 'Default design', 'anderson-qr-code-generator' ); ?></h2>
			<div class="csqr-admin__split" data-csqr-design-form>
				<table class="form-table" role="presentation">
					<?php csqr_render_design_fields( $o, $s ); ?>
				</table>
				<?php csqr_render_design_preview( home_url( '/' ) ); ?>
			</div>

			<h2 id="csqr-page-qr"><?php esc_html_e( 'QR code for each page', 'anderson-qr-code-generator' ); ?></h2>
			<p><?php esc_html_e( 'Adds a “scan to open this page” code below the content. You can also switch it on or off for a single page in the editor sidebar.', 'anderson-qr-code-generator' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				$boxes = '';
				foreach ( $types as $type => $label ) {
					$boxes .= '<label><input type="checkbox" name="' . esc_attr( $o . '[pageQrTypes][]' ) . '" value="' . esc_attr( $type ) . '" ' . checked( in_array( $type, $s['pageQrTypes'], true ), true, false ) . ' /> ' . esc_html( $label ) . '</label><br />';
				}
				csqr_design_row( __( 'Show on every', 'anderson-qr-code-generator' ), '<fieldset>' . $boxes . '</fieldset>' );
				csqr_design_row( __( 'Caption', 'anderson-qr-code-generator' ), '<input type="text" class="regular-text" id="csqr-page-caption" name="' . esc_attr( $o . '[pageQrCaption]' ) . '" value="' . esc_attr( $s['pageQrCaption'] ) . '" placeholder="' . esc_attr( csqr_default_caption( 'page' ) ) . '" />', 'csqr-page-caption' );
				csqr_design_row( __( 'Downloads', 'anderson-qr-code-generator' ), csqr_setting_checkbox( 'pageQrDownloads', $s['pageQrDownloads'], __( 'Let visitors download the code to print', 'anderson-qr-code-generator' ) ) );
				?>
			</table>

			<h2 id="csqr-links"><?php esc_html_e( 'Short links', 'anderson-qr-code-generator' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				csqr_design_row(
					__( 'Address', 'anderson-qr-code-generator' ),
					'<code>' . esc_html( home_url( '/' ) ) . '</code><input type="text" class="small-text" id="csqr-link-prefix" name="' . esc_attr( $o . '[linkPrefix]' ) . '" value="' . esc_attr( $s['linkPrefix'] ) . '" /><code>/menu</code>',
					'csqr-link-prefix',
					__( 'Changing this breaks codes already printed with the old address.', 'anderson-qr-code-generator' )
				);
				?>
			</table>

			<h2 id="csqr-printing"><?php esc_html_e( 'Printing', 'anderson-qr-code-generator' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				csqr_design_row(
					__( 'Units', 'anderson-qr-code-generator' ),
					'<select id="csqr-units" name="' . esc_attr( $o . '[printUnits]' ) . '"><option value="cm" ' . selected( $s['printUnits'], 'cm', false ) . '>' . esc_html__( 'Centimetres', 'anderson-qr-code-generator' ) . '</option><option value="in" ' . selected( $s['printUnits'], 'in', false ) . '>' . esc_html__( 'Inches', 'anderson-qr-code-generator' ) . '</option></select>',
					'csqr-units'
				);
				?>
			</table>

			<h2 id="csqr-generator"><?php esc_html_e( 'Visitor QR generator', 'anderson-qr-code-generator' ); ?></h2>
			<p><?php esc_html_e( 'The QR generator block and [client_side_qr] shortcode let visitors make their own codes.', 'anderson-qr-code-generator' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				$boxes = '';
				foreach ( $payloads as $key => $label ) {
					$boxes .= csqr_setting_checkbox( $key, $s[ $key ], $label ) . '<br />';
				}
				csqr_design_row( __( 'Kinds of code', 'anderson-qr-code-generator' ), '<fieldset>' . $boxes . '</fieldset>' );
				csqr_design_row(
					__( 'Visitors can change', 'anderson-qr-code-generator' ),
					'<fieldset>' . csqr_setting_checkbox( 'allowUserColor', $s['allowUserColor'], __( 'Colors', 'anderson-qr-code-generator' ) ) . '<br />' . csqr_setting_checkbox( 'allowUserSize', $s['allowUserSize'], __( 'Size on screen', 'anderson-qr-code-generator' ) ) . '<br />' . csqr_setting_checkbox( 'allowUserCorrectLevel', $s['allowUserCorrectLevel'], __( 'Error correction', 'anderson-qr-code-generator' ) ) . '</fieldset>'
				);
				csqr_design_row(
					__( 'Look', 'anderson-qr-code-generator' ),
					'<fieldset>' . csqr_setting_checkbox( 'uiUseThemeColors', $s['uiUseThemeColors'], __( 'Use the theme’s colors', 'anderson-qr-code-generator' ) ) . '<br />' . csqr_setting_checkbox( 'uiUseThemeFont', $s['uiUseThemeFont'], __( 'Use the theme’s font', 'anderson-qr-code-generator' ) ) . '</fieldset>'
				);
				?>
			</table>
			<details class="csqr-admin__more">
				<summary><?php esc_html_e( 'Colors and font when not using the theme’s', 'anderson-qr-code-generator' ); ?></summary>
				<table class="form-table" role="presentation">
					<?php
					foreach ( array(
						'uiSurfaceColor' => __( 'Background', 'anderson-qr-code-generator' ),
						'uiTextColor'    => __( 'Text', 'anderson-qr-code-generator' ),
						'uiAccentColor'  => __( 'Accent', 'anderson-qr-code-generator' ),
					) as $key => $label ) {
						csqr_design_row( $label, '<input type="text" class="regular-text" id="csqr-' . esc_attr( $key ) . '" name="' . esc_attr( $o . '[' . $key . ']' ) . '" value="' . esc_attr( $s[ $key ] ) . '" placeholder="#rrggbb" />', 'csqr-' . $key );
					}
					csqr_design_row( __( 'Font', 'anderson-qr-code-generator' ), '<input type="text" class="regular-text code" id="csqr-uiFontFamily" name="' . esc_attr( $o . '[uiFontFamily]' ) . '" value="' . esc_attr( $s['uiFontFamily'] ) . '" placeholder="Georgia, serif" />', 'csqr-uiFontFamily' );
					?>
				</table>
			</details>
			<input type="hidden" name="<?php echo esc_attr( $o . '[qrSize]' ); ?>" value="<?php echo esc_attr( (string) $s['qrSize'] ); ?>" />
			<?php submit_button(); ?>
		</form>
		<?php csqr_render_about(); ?>
	</div>
	<?php
}

/**
 * Shortcode builder: pick options, copy a shortcode. For the classic editor,
 * widgets and page builders.
 *
 * @return void
 */
function csqr_render_shortcode_builder_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$styles = csqr_get_styles();
	?>
	<div class="wrap csqr-admin">
		<h1><?php esc_html_e( 'Shortcode builder', 'anderson-qr-code-generator' ); ?></h1>
		<p class="csqr-admin__lede"><?php esc_html_e( 'For the classic editor, widgets and page builders. Choose what the code holds and copy the shortcode. In the block editor, use the QR code block instead.', 'anderson-qr-code-generator' ); ?></p>

		<div class="csqr-admin__split" data-csqr-builder>
			<table class="form-table" role="presentation">
				<?php
				csqr_design_row(
					__( 'Kind', 'anderson-qr-code-generator' ),
					'<select id="csqr-b-kind" data-b="kind">'
					. '<option value="page">' . esc_html__( 'This page (each page gets its own code)', 'anderson-qr-code-generator' ) . '</option>'
					. '<option value="url">' . esc_html__( 'A link', 'anderson-qr-code-generator' ) . '</option>'
					. '<option value="link">' . esc_html__( 'A short link you can change later', 'anderson-qr-code-generator' ) . '</option>'
					. '<option value="generator">' . esc_html__( 'A generator for visitors', 'anderson-qr-code-generator' ) . '</option>'
					. '</select>',
					'csqr-b-kind'
				);
				csqr_design_row( __( 'Link', 'anderson-qr-code-generator' ), '<input type="url" class="regular-text" id="csqr-b-url" data-b="url" placeholder="https://" />', 'csqr-b-url' );
				$links = '';
				foreach ( csqr_links_for_js() as $link ) {
					$links .= '<option value="' . esc_attr( (string) get_post_field( 'post_name', $link['id'] ) ) . '">' . esc_html( $link['title'] ) . '</option>';
				}
				csqr_design_row(
					__( 'Short link', 'anderson-qr-code-generator' ),
					$links ? '<select id="csqr-b-link" data-b="link">' . $links . '</select>' : '<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . CSQR_LINK_TYPE ) ) . '">' . esc_html__( 'Add your first short link', 'anderson-qr-code-generator' ) . '</a>',
					'csqr-b-link'
				);
				csqr_design_row( __( 'Caption', 'anderson-qr-code-generator' ), '<input type="text" class="regular-text" id="csqr-b-caption" data-b="caption" />', 'csqr-b-caption' );
				$opts = '<option value="">' . esc_html__( 'Site default design', 'anderson-qr-code-generator' ) . '</option>';
				foreach ( $styles as $id => $style ) {
					$opts .= '<option value="' . esc_attr( $id ) . '">' . esc_html( $style['name'] ) . '</option>';
				}
				csqr_design_row( __( 'Style', 'anderson-qr-code-generator' ), '<select id="csqr-b-style" data-b="style">' . $opts . '</select>', 'csqr-b-style' );
				csqr_design_row( __( 'Downloads', 'anderson-qr-code-generator' ), '<label><input type="checkbox" id="csqr-b-downloads" data-b="downloads" /> ' . esc_html__( 'Show print and download buttons', 'anderson-qr-code-generator' ) . '</label>' );
				?>
			</table>
			<div>
				<label for="csqr-b-output" class="csqr-admin__label"><?php esc_html_e( 'Your shortcode', 'anderson-qr-code-generator' ); ?></label>
				<textarea id="csqr-b-output" class="large-text code" rows="3" readonly data-b-output></textarea>
				<p><button type="button" class="button button-primary" data-b-copy><?php esc_html_e( 'Copy shortcode', 'anderson-qr-code-generator' ); ?></button> <span class="csqr-admin__copied" data-b-copied hidden><?php esc_html_e( 'Copied', 'anderson-qr-code-generator' ); ?></span></p>
			</div>
		</div>
		<?php csqr_render_about(); ?>
	</div>
	<?php
}
