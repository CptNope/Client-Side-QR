<?php
/**
 * "QR code for this page": one switch in the editor (or a site-wide setting
 * per post type) adds a scan-to-open card to the end of a post, page or
 * product. The editor panel also downloads the page's code directly.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSQR_PAGE_QR_META', '_csqr_page_qr' );

/**
 * Post types the switch appears on.
 *
 * @return string[]
 */
function csqr_page_qr_post_types() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );
	return array_values( (array) apply_filters( 'csqr_page_qr_post_types', $types ) );
}

/**
 * Register the per-post setting: '' follows the site setting, 'on', 'off'.
 *
 * @return void
 */
function csqr_register_page_qr_meta() {
	foreach ( csqr_page_qr_post_types() as $type ) {
		register_post_meta(
			$type,
			CSQR_PAGE_QR_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, array( 'on', 'off' ), true ) ? $value : '';
				},
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'csqr_register_page_qr_meta' );

/**
 * Does this post show its QR card?
 *
 * @param int $post_id Post id.
 * @return bool
 */
function csqr_page_qr_enabled( $post_id ) {
	$choice = get_post_meta( $post_id, CSQR_PAGE_QR_META, true );
	if ( 'on' === $choice ) {
		return true;
	}
	if ( 'off' === $choice ) {
		return false;
	}
	return in_array( get_post_type( $post_id ), csqr_get_settings()['pageQrTypes'], true );
}

/**
 * Add the card after the content of a single post being viewed.
 *
 * @param string $content Post content.
 * @return string
 */
function csqr_append_page_qr( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() || is_feed() || post_password_required() ) {
		return $content;
	}
	$post_id = get_the_ID();
	if ( ! $post_id || ! csqr_page_qr_enabled( $post_id ) || has_block( 'csqr/code', $post_id ) ) {
		return $content;
	}
	// WooCommerce products show it beside the product details instead.
	if ( ! apply_filters( 'csqr_page_qr_in_content', true, $post_id ) ) {
		return $content;
	}
	return $content . csqr_page_qr_card( $post_id );
}

/**
 * The page's QR card.
 *
 * @param int $post_id Post id.
 * @return string HTML.
 */
function csqr_page_qr_card( $post_id ) {
	$settings = csqr_get_settings();
	$caption  = '' !== $settings['pageQrCaption'] ? $settings['pageQrCaption'] : csqr_default_caption( 'page' );
	$design   = wp_parse_args( array( 'qrSize' => 160 ), csqr_card_design( array() ) );
	$card     = csqr_render_card(
		(string) get_permalink( $post_id ),
		$design,
		array(
			'caption'   => esc_html( $caption ),
			'label'     => __( 'QR code linking to this page', 'anderson-qr-code-generator' ),
			'name'      => 'qr-' . get_post_field( 'post_name', $post_id ),
			'downloads' => ! empty( $settings['pageQrDownloads'] ),
			'class'     => 'csqr-card--page',
		)
	);
	return '<div class="csqr-page-qr">' . $card . '</div>';
}
add_filter( 'the_content', 'csqr_append_page_qr', 20 );

/**
 * Block editor panel.
 *
 * @return void
 */
function csqr_page_qr_editor_assets() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->post_type, csqr_page_qr_post_types(), true ) ) {
		return;
	}
	wp_enqueue_script(
		'csqr-page-qr-panel',
		CSQR_URL . 'assets/qr-editor-panel.js',
		array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-core-data', 'wp-editor', 'csqr-ui' ),
		CSQR_VERSION,
		true
	);
	wp_set_script_translations( 'csqr-page-qr-panel', 'anderson-qr-code-generator' );
	wp_add_inline_script(
		'csqr-page-qr-panel',
		'window.csqrPagePanel = ' . wp_json_encode(
			array(
				'siteDefault' => in_array( $screen->post_type, csqr_get_settings()['pageQrTypes'], true ),
				'design'      => csqr_card_design( array() ),
				'settingsUrl' => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=csqr-settings#csqr-page-qr' ) : '',
			)
		) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'csqr_page_qr_editor_assets' );

/**
 * Classic editor: a meta box with the same switch. Hidden in the block
 * editor, which uses the sidebar panel instead.
 *
 * @return void
 */
function csqr_page_qr_meta_box() {
	foreach ( csqr_page_qr_post_types() as $type ) {
		add_meta_box(
			'csqr-page-qr',
			__( 'QR code', 'anderson-qr-code-generator' ),
			'csqr_page_qr_meta_box_html',
			$type,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}
}
add_action( 'add_meta_boxes', 'csqr_page_qr_meta_box' );

/**
 * Meta box contents.
 *
 * @param WP_Post $post Post.
 * @return void
 */
function csqr_page_qr_meta_box_html( $post ) {
	wp_nonce_field( 'csqr_page_qr', 'csqr_page_qr_nonce' );
	$choice  = get_post_meta( $post->ID, CSQR_PAGE_QR_META, true );
	$default = in_array( $post->post_type, csqr_get_settings()['pageQrTypes'], true );
	?>
	<p>
		<label for="csqr-page-qr-choice"><?php esc_html_e( 'Show a QR code for this page', 'anderson-qr-code-generator' ); ?></label>
		<select id="csqr-page-qr-choice" name="csqr_page_qr" class="widefat">
			<option value="" <?php selected( $choice, '' ); ?>><?php echo esc_html( $default ? __( 'Site default (shown)', 'anderson-qr-code-generator' ) : __( 'Site default (hidden)', 'anderson-qr-code-generator' ) ); ?></option>
			<option value="on" <?php selected( $choice, 'on' ); ?>><?php esc_html_e( 'Show it', 'anderson-qr-code-generator' ); ?></option>
			<option value="off" <?php selected( $choice, 'off' ); ?>><?php esc_html_e( 'Hide it', 'anderson-qr-code-generator' ); ?></option>
		</select>
	</p>
	<?php if ( 'publish' === $post->post_status ) : ?>
		<?php
		echo csqr_render_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
			(string) get_permalink( $post ),
			wp_parse_args( array( 'qrSize' => 160 ), csqr_card_design( array() ) ),
			array(
				'label'     => __( 'QR code linking to this page', 'anderson-qr-code-generator' ),
				'name'      => 'qr-' . $post->post_name,
				'downloads' => true,
				'check'     => true,
			)
		);
		?>
	<?php endif; ?>
	<?php
}

/**
 * Save the classic meta box.
 *
 * @param int $post_id Post id.
 * @return void
 */
function csqr_page_qr_save( $post_id ) {
	if ( ! isset( $_POST['csqr_page_qr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csqr_page_qr_nonce'] ) ), 'csqr_page_qr' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$value = isset( $_POST['csqr_page_qr'] ) ? sanitize_key( wp_unslash( $_POST['csqr_page_qr'] ) ) : '';
	if ( in_array( $value, array( 'on', 'off' ), true ) ) {
		update_post_meta( $post_id, CSQR_PAGE_QR_META, $value );
	} else {
		delete_post_meta( $post_id, CSQR_PAGE_QR_META );
	}
}
add_action( 'save_post', 'csqr_page_qr_save' );
