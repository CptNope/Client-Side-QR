<?php
/**
 * Saved styles: a named set of QR design settings (colors, dots, corners,
 * logo) that blocks and shortcodes can use by name. Changing a style updates
 * every code that uses it.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSQR_STYLES_OPTION', 'csqr_styles' );

/**
 * Settings keys that belong to a code's design (what a style stores).
 *
 * @return string[]
 */
function csqr_design_keys() {
	return array( 'qrColorDark', 'qrColorDark2', 'qrColorLight', 'qrGradient', 'qrDotStyle', 'qrEyeStyle', 'qrEyeColor', 'qrCorrectLevel', 'logoUrl', 'logoSize', 'transparent' );
}

/**
 * All saved styles, keyed by id.
 *
 * @return array<string, array{name: string, design: array<string, mixed>}>
 */
function csqr_get_styles() {
	$styles = get_option( CSQR_STYLES_OPTION, array() );
	return is_array( $styles ) ? $styles : array();
}

/**
 * One saved style's design, or null.
 *
 * @param string $id Style id.
 * @return array<string, mixed>|null
 */
function csqr_get_style( $id ) {
	$styles = csqr_get_styles();
	$id     = sanitize_key( (string) $id );
	return isset( $styles[ $id ]['design'] ) && is_array( $styles[ $id ]['design'] ) ? $styles[ $id ]['design'] : null;
}

/**
 * Keep only design keys, sanitized.
 *
 * @param array<string, mixed> $raw Raw design.
 * @return array<string, mixed>
 */
function csqr_sanitize_design( $raw ) {
	$clean = csqr_sanitize_settings( wp_parse_args( (array) $raw, csqr_get_settings() ) );
	return array_intersect_key( $clean, array_flip( csqr_design_keys() ) );
}

/**
 * A style named by an instance (styleId / style="…") wins over its own design
 * attributes: the editor hides those controls while a style is in use.
 *
 * @param array<string, mixed> $settings Merged settings.
 * @param array<string, mixed> $attributes Instance attributes.
 * @return array<string, mixed>
 */
function csqr_apply_style( $settings, $attributes ) {
	$id = is_array( $attributes ) && ! empty( $attributes['styleId'] ) ? $attributes['styleId'] : '';
	if ( $id ) {
		$style = csqr_get_style( $id );
		if ( $style ) {
			$settings = array_merge( $settings, $style );
		}
	}
	return $settings;
}
add_filter( 'csqr_instance_settings', 'csqr_apply_style', 5, 2 );

/**
 * Styles as {id, name, design} for scripts.
 *
 * @return array<int, array<string, mixed>>
 */
function csqr_styles_for_js() {
	$out = array();
	foreach ( csqr_get_styles() as $id => $style ) {
		$out[] = array(
			'id'     => (string) $id,
			'name'   => (string) $style['name'],
			'design' => $style['design'],
		);
	}
	return $out;
}

/**
 * Save or delete a style (admin-post.php handlers).
 *
 * @return void
 */
function csqr_handle_style_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You don’t have permission to change QR styles.', 'anderson-qr-code-generator' ), 403 );
	}
	check_admin_referer( 'csqr_style_save' );

	$styles = csqr_get_styles();
	$name   = isset( $_POST['style_name'] ) ? sanitize_text_field( wp_unslash( $_POST['style_name'] ) ) : '';
	$id     = isset( $_POST['style_id'] ) ? sanitize_key( wp_unslash( $_POST['style_id'] ) ) : '';
	$raw    = isset( $_POST['design'] ) && is_array( $_POST['design'] ) ? map_deep( wp_unslash( $_POST['design'] ), 'sanitize_text_field' ) : array();

	foreach ( array( 'qrGradient', 'transparent' ) as $flag ) {
		$raw[ $flag ] = ! empty( $raw[ $flag ] );
	}
	if ( isset( $raw['logoUrl'] ) ) {
		$raw['logoUrl'] = esc_url_raw( $raw['logoUrl'] );
	}

	if ( '' === $name ) {
		$name = __( 'Untitled style', 'anderson-qr-code-generator' );
	}
	if ( '' === $id || ! isset( $styles[ $id ] ) ) {
		$base = sanitize_title( $name );
		$base = '' !== $base ? $base : 'style';
		$id   = $base;
		$n    = 2;
		while ( isset( $styles[ $id ] ) ) {
			$id = $base . '-' . $n++;
		}
	}

	$styles[ $id ] = array(
		'name'   => $name,
		'design' => csqr_sanitize_design( $raw ),
	);
	update_option( CSQR_STYLES_OPTION, $styles, false );

	wp_safe_redirect( add_query_arg( array( 'page' => 'csqr-styles', 'saved' => rawurlencode( $id ) ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_csqr_style_save', 'csqr_handle_style_save' );

/**
 * Delete a style.
 *
 * @return void
 */
function csqr_handle_style_delete() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You don’t have permission to change QR styles.', 'anderson-qr-code-generator' ), 403 );
	}
	$id = isset( $_GET['style_id'] ) ? sanitize_key( wp_unslash( $_GET['style_id'] ) ) : '';
	check_admin_referer( 'csqr_style_delete_' . $id );
	$styles = csqr_get_styles();
	unset( $styles[ $id ] );
	update_option( CSQR_STYLES_OPTION, $styles, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'csqr-styles', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_csqr_style_delete', 'csqr_handle_style_delete' );

/**
 * One design form row.
 *
 * @param string $label Label.
 * @param string $html  Control HTML.
 * @param string $for   Control id.
 * @param string $help  Help text.
 * @return void
 */
function csqr_design_row( $label, $html, $for = '', $help = '' ) {
	?>
	<tr>
		<th scope="row"><?php if ( $for ) : ?><label for="<?php echo esc_attr( $for ); ?>"><?php echo esc_html( $label ); ?></label><?php else : ?><?php echo esc_html( $label ); ?><?php endif; ?></th>
		<td>
			<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by the caller. ?>
			<?php if ( $help ) : ?>
				<p class="description"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * The design controls shared by the settings page and the style editor.
 * Field names are "{$name}[key]"; the admin script redraws the preview.
 *
 * @param string               $name   Form array name.
 * @param array<string, mixed> $design Current values.
 * @return void
 */
function csqr_render_design_fields( $name, $design ) {
	$id   = function ( $key ) use ( $name ) {
		return sanitize_html_class( $name . '-' . $key );
	};
	$n    = function ( $key ) use ( $name ) {
		return esc_attr( $name . '[' . $key . ']' );
	};
	$dots = array(
		'square'         => __( 'Square', 'anderson-qr-code-generator' ),
		'rounded'        => __( 'Rounded', 'anderson-qr-code-generator' ),
		'extra-rounded'  => __( 'Extra rounded', 'anderson-qr-code-generator' ),
		'dots'           => __( 'Dots', 'anderson-qr-code-generator' ),
		'classy'         => __( 'Classy', 'anderson-qr-code-generator' ),
		'classy-rounded' => __( 'Classy rounded', 'anderson-qr-code-generator' ),
	);
	$eyes = array(
		'square'        => __( 'Square', 'anderson-qr-code-generator' ),
		'extra-rounded' => __( 'Rounded', 'anderson-qr-code-generator' ),
		'dot'           => __( 'Circle', 'anderson-qr-code-generator' ),
	);
	$opt  = function ( $choices, $current ) {
		$html = '';
		foreach ( $choices as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html;
	};

	csqr_design_row( __( 'Code color', 'anderson-qr-code-generator' ), '<input type="color" id="' . $id( 'qrColorDark' ) . '" name="' . $n( 'qrColorDark' ) . '" value="' . esc_attr( $design['qrColorDark'] ) . '" data-csqr-design="qrColorDark" />', $id( 'qrColorDark' ) );
	csqr_design_row(
		__( 'Gradient', 'anderson-qr-code-generator' ),
		'<label><input type="checkbox" name="' . $n( 'qrGradient' ) . '" value="1" ' . checked( ! empty( $design['qrGradient'] ), true, false ) . ' data-csqr-design="qrGradient" /> ' . esc_html__( 'Blend into a second color', 'anderson-qr-code-generator' ) . '</label> <input type="color" aria-label="' . esc_attr__( 'Second color', 'anderson-qr-code-generator' ) . '" name="' . $n( 'qrColorDark2' ) . '" value="' . esc_attr( $design['qrColorDark2'] ) . '" data-csqr-design="qrColorDark2" />'
	);
	csqr_design_row(
		__( 'Background', 'anderson-qr-code-generator' ),
		'<input type="color" id="' . $id( 'qrColorLight' ) . '" name="' . $n( 'qrColorLight' ) . '" value="' . esc_attr( $design['qrColorLight'] ) . '" data-csqr-design="qrColorLight" /> <label><input type="checkbox" name="' . $n( 'transparent' ) . '" value="1" ' . checked( ! empty( $design['transparent'] ), true, false ) . ' data-csqr-design="transparent" /> ' . esc_html__( 'Transparent', 'anderson-qr-code-generator' ) . '</label>',
		$id( 'qrColorLight' )
	);
	csqr_design_row( __( 'Dots', 'anderson-qr-code-generator' ), '<select id="' . $id( 'qrDotStyle' ) . '" name="' . $n( 'qrDotStyle' ) . '" data-csqr-design="qrDotStyle">' . $opt( $dots, $design['qrDotStyle'] ) . '</select>', $id( 'qrDotStyle' ) );
	csqr_design_row(
		__( 'Corners', 'anderson-qr-code-generator' ),
		'<select id="' . $id( 'qrEyeStyle' ) . '" name="' . $n( 'qrEyeStyle' ) . '" data-csqr-design="qrEyeStyle">' . $opt( $eyes, $design['qrEyeStyle'] ) . '</select> <input type="color" aria-label="' . esc_attr__( 'Corner color', 'anderson-qr-code-generator' ) . '" name="' . $n( 'qrEyeColor' ) . '" value="' . esc_attr( $design['qrEyeColor'] ? $design['qrEyeColor'] : $design['qrColorDark'] ) . '" data-csqr-design="qrEyeColor" />',
		$id( 'qrEyeStyle' )
	);
	csqr_design_row(
		__( 'Logo', 'anderson-qr-code-generator' ),
		'<span class="csqr-media" data-csqr-media><input type="url" class="regular-text" id="' . $id( 'logoUrl' ) . '" name="' . $n( 'logoUrl' ) . '" value="' . esc_attr( $design['logoUrl'] ) . '" data-csqr-design="logoUrl" placeholder="' . esc_attr__( 'No logo', 'anderson-qr-code-generator' ) . '" /> <button type="button" class="button" data-csqr-media-pick>' . esc_html__( 'Choose from Media Library', 'anderson-qr-code-generator' ) . '</button> <button type="button" class="button-link" data-csqr-media-clear>' . esc_html__( 'Remove', 'anderson-qr-code-generator' ) . '</button></span>'
		. '<p><label>' . esc_html__( 'Logo size', 'anderson-qr-code-generator' ) . ' <input type="range" min="0.1" max="0.3" step="0.02" name="' . $n( 'logoSize' ) . '" value="' . esc_attr( (string) $design['logoSize'] ) . '" data-csqr-design="logoSize" /></label></p>',
		$id( 'logoUrl' ),
		__( 'With a logo, codes use the strongest error correction automatically, and the logo is kept small enough to scan.', 'anderson-qr-code-generator' )
	);
	csqr_design_row(
		__( 'Error correction', 'anderson-qr-code-generator' ),
		'<select id="' . $id( 'qrCorrectLevel' ) . '" name="' . $n( 'qrCorrectLevel' ) . '" data-csqr-design="qrCorrectLevel">' . $opt(
			array(
				'L' => __( 'Low: fewest dots', 'anderson-qr-code-generator' ),
				'M' => __( 'Medium (recommended)', 'anderson-qr-code-generator' ),
				'Q' => __( 'High', 'anderson-qr-code-generator' ),
				'H' => __( 'Highest: survives damage and dirt', 'anderson-qr-code-generator' ),
			),
			$design['qrCorrectLevel']
		) . '</select>',
		$id( 'qrCorrectLevel' ),
		__( 'Higher levels survive scratches and smudges, but need more, smaller dots.', 'anderson-qr-code-generator' )
	);
}

/**
 * The live preview beside a design form: a code plus its scan check.
 *
 * @param string $sample Text to encode.
 * @return void
 */
function csqr_render_design_preview( $sample ) {
	?>
	<div class="csqr csqr-admin-preview" data-csqr-preview data-csqr-sample="<?php echo esc_attr( $sample ); ?>">
		<div class="csqr-sheet"><div class="csqr-sheet__code" role="img" aria-label="<?php esc_attr_e( 'Preview of the QR code', 'anderson-qr-code-generator' ); ?>"></div></div>
		<div class="csqr-check"></div>
	</div>
	<?php
}

/**
 * Saved styles admin page.
 *
 * @return void
 */
function csqr_render_styles_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$styles  = csqr_get_styles();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only choice of which style to edit.
	$edit_id = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
	$editing = $edit_id && isset( $styles[ $edit_id ] ) ? $styles[ $edit_id ] : null;
	$design  = $editing ? wp_parse_args( $editing['design'], csqr_card_design( array() ) ) : csqr_card_design( array() );
	?>
	<div class="wrap csqr-admin">
		<h1><?php esc_html_e( 'Saved styles', 'anderson-qr-code-generator' ); ?></h1>
		<p class="csqr-admin__lede"><?php esc_html_e( 'Save your brand’s colors, dots and logo once, then pick the style in any QR block or shortcode. Editing a style updates every code that uses it.', 'anderson-qr-code-generator' ); ?></p>

		<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Notices only. ?>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Style saved.', 'anderson-qr-code-generator' ); ?></p></div>
		<?php elseif ( isset( $_GET['deleted'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Style deleted. Codes that used it went back to your default design.', 'anderson-qr-code-generator' ); ?></p></div>
		<?php endif; ?>
		<?php // phpcs:enable ?>

		<?php if ( $styles ) : ?>
			<ul class="csqr-style-list">
				<?php foreach ( $styles as $id => $style ) : ?>
					<li class="csqr-style-list__item">
						<?php echo csqr_render_card( home_url( '/' ), wp_parse_args( $style['design'], array( 'qrSize' => 96 ) ), array( 'label' => $style['name'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div class="csqr-style-list__meta">
							<strong><?php echo esc_html( $style['name'] ); ?></strong>
							<span class="description"><?php esc_html_e( 'In shortcodes:', 'anderson-qr-code-generator' ); ?> <code>style="<?php echo esc_html( $id ); ?>"</code></span>
							<span class="csqr-style-list__actions">
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'csqr-styles', 'edit' => $id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'anderson-qr-code-generator' ); ?></a>
								<a class="csqr-danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'csqr_style_delete', 'style_id' => $id ), admin_url( 'admin-post.php' ) ), 'csqr_style_delete_' . $id ) ); ?>"><?php esc_html_e( 'Delete', 'anderson-qr-code-generator' ); ?></a>
							</span>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h2><?php echo $editing ? esc_html( sprintf( /* translators: %s: style name */ __( 'Edit “%s”', 'anderson-qr-code-generator' ), $editing['name'] ) ) : esc_html__( 'New style', 'anderson-qr-code-generator' ); ?></h2>
		<div class="csqr-admin__split">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-csqr-design-form>
				<input type="hidden" name="action" value="csqr_style_save" />
				<input type="hidden" name="style_id" value="<?php echo esc_attr( $editing ? $edit_id : '' ); ?>" />
				<?php wp_nonce_field( 'csqr_style_save' ); ?>
				<table class="form-table" role="presentation">
					<?php csqr_design_row( __( 'Name', 'anderson-qr-code-generator' ), '<input type="text" class="regular-text" id="csqr-style-name" name="style_name" required value="' . esc_attr( $editing ? $editing['name'] : '' ) . '" placeholder="' . esc_attr__( 'For example: Brand colors', 'anderson-qr-code-generator' ) . '" />', 'csqr-style-name' ); ?>
					<?php csqr_render_design_fields( 'design', $design ); ?>
				</table>
				<?php submit_button( $editing ? __( 'Save style', 'anderson-qr-code-generator' ) : __( 'Add style', 'anderson-qr-code-generator' ) ); ?>
			</form>
			<?php csqr_render_design_preview( home_url( '/' ) ); ?>
		</div>
		<?php csqr_render_about(); ?>
	</div>
	<?php
}
