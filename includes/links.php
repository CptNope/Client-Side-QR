<?php
/**
 * Short links: codes you can change after printing. A code points at
 * yoursite.com/qr/{name}, which redirects to wherever you choose and can be
 * repointed any time. Scans are counted on your own site: a number and a
 * date, nothing about who scanned.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSQR_LINK_TYPE', 'csqr_link' );

/**
 * Clean the address prefix ("qr" in /qr/menu).
 *
 * @param mixed $value Raw prefix.
 * @return string
 */
function csqr_sanitize_link_prefix( $value ) {
	$value = trim( strtolower( (string) $value ), '/ ' );
	$value = preg_replace( '/[^a-z0-9-]/', '', $value );
	$taken = array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'page', 'category', 'tag', 'author', 'search', 'comments' );
	return ( '' === $value || in_array( $value, $taken, true ) ) ? 'qr' : substr( $value, 0, 32 );
}

/**
 * Register the short link type and its address rule.
 *
 * @return void
 */
function csqr_register_links() {
	register_post_type(
		CSQR_LINK_TYPE,
		array(
			'labels'              => array(
				'name'               => __( 'Short links', 'anderson-qr-code-generator' ),
				'singular_name'      => __( 'Short link', 'anderson-qr-code-generator' ),
				'add_new'            => __( 'Add short link', 'anderson-qr-code-generator' ),
				'add_new_item'       => __( 'Add short link', 'anderson-qr-code-generator' ),
				'edit_item'          => __( 'Edit short link', 'anderson-qr-code-generator' ),
				'new_item'           => __( 'New short link', 'anderson-qr-code-generator' ),
				'search_items'       => __( 'Search short links', 'anderson-qr-code-generator' ),
				'not_found'          => __( 'No short links yet.', 'anderson-qr-code-generator' ),
				'not_found_in_trash' => __( 'No short links in the trash.', 'anderson-qr-code-generator' ),
				'all_items'          => __( 'Short links', 'anderson-qr-code-generator' ),
				'item_published'     => __( 'Short link saved.', 'anderson-qr-code-generator' ),
				'item_updated'       => __( 'Short link updated.', 'anderson-qr-code-generator' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // Added under QR Codes in csqr_add_settings_page().
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'capabilities'        => array(
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'delete_posts'       => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
				'create_posts'       => 'manage_options',
			),
			'map_meta_cap'        => false,
		)
	);

	$prefix = preg_quote( csqr_get_settings()['linkPrefix'], '/' );
	add_rewrite_tag( '%csqr_link%', '([^&/]+)' );
	add_rewrite_tag( '%csqr_place%', '([^&/]+)' );
	add_rewrite_rule( '^' . $prefix . '/([^/]+)/([^/]+)/?$', 'index.php?csqr_link=$matches[1]&csqr_place=$matches[2]', 'top' );
	add_rewrite_rule( '^' . $prefix . '/([^/]+)/?$', 'index.php?csqr_link=$matches[1]', 'top' );
}
add_action( 'init', 'csqr_register_links' );

/**
 * Flush address rules when the prefix changes.
 *
 * @param mixed $old Old settings.
 * @param mixed $new New settings.
 * @return void
 */
function csqr_maybe_flush_links( $old, $new ) {
	$a = is_array( $old ) && isset( $old['linkPrefix'] ) ? $old['linkPrefix'] : 'qr';
	$b = is_array( $new ) && isset( $new['linkPrefix'] ) ? $new['linkPrefix'] : 'qr';
	if ( $a !== $b ) {
		update_option( 'csqr_flush_rewrite', 1 );
	}
}
add_action( 'update_option_csqr_settings', 'csqr_maybe_flush_links', 10, 2 );

/**
 * Flush on the request after activation or a prefix change (rules must be
 * registered first).
 *
 * @return void
 */
function csqr_flush_links_if_needed() {
	if ( get_option( 'csqr_flush_rewrite' ) ) {
		delete_option( 'csqr_flush_rewrite' );
		flush_rewrite_rules( false );
	}
}
add_action( 'init', 'csqr_flush_links_if_needed', 99 );

/**
 * The public address of a short link.
 *
 * @param int $post_id Short link id.
 * @return string
 */
function csqr_link_url( $post_id, $place = '' ) {
	$post = get_post( $post_id );
	if ( ! $post || CSQR_LINK_TYPE !== $post->post_type || '' === $post->post_name ) {
		return '';
	}
	$place = sanitize_title( (string) $place );
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( user_trailingslashit( csqr_get_settings()['linkPrefix'] . '/' . $post->post_name . ( '' !== $place ? '/' . $place : '' ) ) );
	}
	$args = array( 'csqr_link' => $post->post_name );
	if ( '' !== $place ) {
		$args['csqr_place'] = $place;
	}
	return add_query_arg( $args, home_url( '/' ) );
}

/**
 * What kind of short link this is: "url" (redirects) or "contact" (serves a
 * contact card).
 *
 * @param int $post_id Short link id.
 * @return string
 */
function csqr_link_kind( $post_id ) {
	$kind = (string) get_post_meta( $post_id, '_csqr_kind', true );
	return in_array( $kind, array_keys( csqr_link_kinds() ), true ) ? $kind : 'url';
}

/**
 * Kinds of short link.
 *
 * @return array<string, string>
 */
function csqr_link_kinds() {
	return apply_filters(
		'csqr_link_kinds',
		array(
			'url'     => __( 'Web address', 'anderson-qr-code-generator' ),
			'contact' => __( 'Contact card', 'anderson-qr-code-generator' ),
		)
	);
}

/**
 * Decide what a scan of this link gets. Other features (schedules, phone
 * links, contact cards) hook into csqr_link_target.
 *
 * @param WP_Post $post  Short link.
 * @param string  $place Placement slug, if any.
 * @return array{type: string, url?: string, message?: string}
 */
function csqr_link_target( $post, $place = '' ) {
	$dest   = csqr_link_destination( $post->ID );
	$target = '' !== $dest ? array( 'type' => 'redirect', 'url' => $dest ) : array( 'type' => 'missing' );
	return apply_filters( 'csqr_link_target', $target, $post, $place );
}

/**
 * Where a short link goes.
 *
 * @param int $post_id Short link id.
 * @return string
 */
function csqr_link_destination( $post_id ) {
	return (string) get_post_meta( $post_id, '_csqr_destination', true );
}

/**
 * Follow a short link: count the scan and redirect.
 *
 * @return void
 */
function csqr_follow_link() {
	$name = get_query_var( 'csqr_link' );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public redirect, no state change beyond a counter.
	if ( '' === $name || null === $name ) {
		$name = isset( $_GET['csqr_link'] ) ? sanitize_title( wp_unslash( $_GET['csqr_link'] ) ) : '';
	}
	if ( '' === $name ) {
		return;
	}
	$place = get_query_var( 'csqr_place' );
	if ( '' === $place || null === $place ) {
		$place = isset( $_GET['csqr_place'] ) ? sanitize_title( wp_unslash( $_GET['csqr_place'] ) ) : '';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$post   = get_page_by_path( sanitize_title( $name ), OBJECT, CSQR_LINK_TYPE );
	$target = $post && 'publish' === $post->post_status ? csqr_link_target( $post, sanitize_title( (string) $place ) ) : array( 'type' => 'missing' );

	if ( 'missing' === $target['type'] ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}

	if ( csqr_is_real_scan() ) {
		csqr_count_scan( $post->ID, sanitize_title( (string) $place ) );
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	do_action( 'csqr_serve_link', $target, $post );

	if ( 'redirect' === $target['type'] && ! empty( $target['url'] ) ) {
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- An admin chose this destination; short links point off-site by design.
		wp_redirect( $target['url'], 302, 'Anderson QR Code Generator' );
		exit;
	}
	csqr_link_message_page( isset( $target['message'] ) ? $target['message'] : __( 'This code isn’t active right now.', 'anderson-qr-code-generator' ), isset( $target['status'] ) ? (int) $target['status'] : 410 );
}

/**
 * A plain page for a code that has nowhere to go (for example an offer that
 * has ended).
 *
 * @param string $message Message for the person who scanned.
 * @param int    $status  HTTP status.
 * @return void
 */
function csqr_link_message_page( $message, $status = 410 ) {
	wp_die(
		'<p style="font-size:1.25em;line-height:1.5">' . esc_html( $message ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></p>',
		esc_html( get_bloginfo( 'name' ) ),
		array( 'response' => (int) $status )
	);
}

/**
 * Count one scan: the total, the time of the last scan, and (in
 * link-stats.php) daily and per-placement counts.
 *
 * @param int    $post_id Short link id.
 * @param string $place   Placement slug.
 * @return void
 */
function csqr_count_scan( $post_id, $place = '' ) {
	// Count only: no IP, browser or anything else about the person is stored.
	$count = (int) get_post_meta( $post_id, '_csqr_scans', true );
	update_post_meta( $post_id, '_csqr_scans', $count + 1 );
	update_post_meta( $post_id, '_csqr_last_scan', time() );
	do_action( 'csqr_scan_counted', $post_id, $place );
}
add_action( 'template_redirect', 'csqr_follow_link', 1 );

/**
 * Skip prefetches, link previews and obvious bots when counting.
 *
 * @return bool
 */
function csqr_is_real_scan() {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( 'GET' !== $method ) {
		return false;
	}
	foreach ( array( 'HTTP_PURPOSE', 'HTTP_SEC_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ' ) as $header ) {
		if ( isset( $_SERVER[ $header ] ) && false !== stripos( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ), 'prefetch' ) ) {
			return false;
		}
	}
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$bot   = (bool) preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|whatsapp|telegram|discord|slack|curl|wget|python|monitor/i', $agent );
	return (bool) apply_filters( 'csqr_count_scan', ! $bot && '' !== $agent );
}

/**
 * Edit screen: where it goes, its address, and its code.
 *
 * @return void
 */
function csqr_link_meta_boxes() {
	add_meta_box( 'csqr-link-destination', __( 'Where it goes', 'anderson-qr-code-generator' ), 'csqr_link_destination_box', CSQR_LINK_TYPE, 'normal', 'high' );
	add_meta_box( 'csqr-link-code', __( 'QR code', 'anderson-qr-code-generator' ), 'csqr_link_code_box', CSQR_LINK_TYPE, 'side', 'high' );
	remove_meta_box( 'slugdiv', CSQR_LINK_TYPE, 'normal' );
}
add_action( 'add_meta_boxes_' . CSQR_LINK_TYPE, 'csqr_link_meta_boxes' );

/**
 * Fields a short link stores, keyed by the name used in forms, the REST API,
 * WP-CLI and CSV imports. Features add theirs with the csqr_link_schema filter.
 *
 * Each field: meta (post meta key), type (string|url|int|array|bool), and
 * optional sanitize (callable).
 *
 * @return array<string, array<string, mixed>>
 */
function csqr_link_schema() {
	static $schema = null;
	if ( null === $schema ) {
		$schema = apply_filters(
			'csqr_link_schema',
			array(
				'destination' => array(
					'meta' => '_csqr_destination',
					'type' => 'url',
				),
				'kind'        => array(
					'meta'     => '_csqr_kind',
					'type'     => 'string',
					'sanitize' => function ( $v ) {
						return array_key_exists( (string) $v, csqr_link_kinds() ) ? (string) $v : 'url';
					},
				),
			)
		);
	}
	return $schema;
}

/**
 * Sanitize one field value by its schema type.
 *
 * @param array<string, mixed> $field Schema entry.
 * @param mixed                $value Raw value.
 * @return mixed
 */
function csqr_link_sanitize_field( $field, $value ) {
	if ( isset( $field['sanitize'] ) && is_callable( $field['sanitize'] ) ) {
		return call_user_func( $field['sanitize'], $value );
	}
	switch ( $field['type'] ) {
		case 'url':
			return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		case 'int':
			return (int) $value;
		case 'bool':
			return in_array( $value, array( true, 1, '1', 'true', 'yes', 'on' ), true );
		case 'array':
			return is_array( $value ) ? $value : array();
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Everything about a short link, as one array (for the API, CLI and exports).
 *
 * @param int $post_id Short link id.
 * @return array<string, mixed>|null
 */
function csqr_get_link( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || CSQR_LINK_TYPE !== $post->post_type ) {
		return null;
	}
	$out = array(
		'id'        => $post->ID,
		'name'      => $post->post_title,
		'slug'      => $post->post_name,
		'status'    => $post->post_status,
		'url'       => csqr_link_url( $post->ID ),
		'scans'     => (int) get_post_meta( $post->ID, '_csqr_scans', true ),
		'last_scan' => (int) get_post_meta( $post->ID, '_csqr_last_scan', true ),
	);
	foreach ( csqr_link_schema() as $key => $field ) {
		$value       = get_post_meta( $post->ID, $field['meta'], true );
		$out[ $key ] = '' === $value && 'array' === $field['type'] ? array() : $value;
	}
	$out['kind'] = csqr_link_kind( $post->ID );
	return apply_filters( 'csqr_get_link', $out, $post );
}

/**
 * Create or update a short link from an array. Unknown keys are ignored.
 *
 * @param array<string, mixed> $args { name, slug, status, plus any schema field }.
 * @param int                  $post_id Existing link to update, or 0.
 * @return int|WP_Error Link id.
 */
function csqr_save_link( $args, $post_id = 0 ) {
	$args   = is_array( $args ) ? $args : array();
	$schema = csqr_link_schema();
	$meta   = array();
	foreach ( $schema as $key => $field ) {
		if ( array_key_exists( $key, $args ) ) {
			$meta[ $field['meta'] ] = csqr_link_sanitize_field( $field, $args[ $key ] );
		}
	}
	$kind = isset( $meta['_csqr_kind'] ) ? $meta['_csqr_kind'] : ( $post_id ? csqr_link_kind( $post_id ) : 'url' );
	$dest = isset( $meta['_csqr_destination'] ) ? $meta['_csqr_destination'] : ( $post_id ? csqr_link_destination( $post_id ) : '' );
	if ( 'url' === $kind && '' === $dest ) {
		return new WP_Error( 'csqr_no_destination', __( 'A short link needs a destination (an http or https address).', 'anderson-qr-code-generator' ) );
	}

	$post = array(
		'post_type'  => CSQR_LINK_TYPE,
		'meta_input' => $meta,
	);
	if ( $post_id ) {
		$post['ID'] = $post_id;
	} else {
		$post['post_status'] = 'publish';
	}
	if ( isset( $args['name'] ) ) {
		$post['post_title'] = sanitize_text_field( (string) $args['name'] );
	}
	if ( isset( $args['status'] ) && in_array( $args['status'], array( 'publish', 'draft' ), true ) ) {
		$post['post_status'] = $args['status'];
	}
	if ( ! empty( $args['slug'] ) ) {
		$post['post_name'] = sanitize_title( (string) $args['slug'] );
	} elseif ( ! $post_id && ! empty( $post['post_title'] ) ) {
		$post['post_name'] = sanitize_title( $post['post_title'] );
	}
	$id = $post_id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
	if ( ! is_wp_error( $id ) ) {
		do_action( 'csqr_link_saved', (int) $id, $args );
	}
	return $id;
}

/**
 * Find a short link by its address name.
 *
 * @param string $slug Slug.
 * @return int Link id or 0.
 */
function csqr_find_link( $slug ) {
	$post = get_page_by_path( sanitize_title( $slug ), OBJECT, CSQR_LINK_TYPE );
	return $post ? (int) $post->ID : 0;
}

/**
 * Destination box: kind, where it goes, address, scans. Features add rows
 * with the csqr_link_fields action.
 *
 * @param WP_Post $post Short link.
 * @return void
 */
function csqr_link_destination_box( $post ) {
	wp_nonce_field( 'csqr_link_save', 'csqr_link_nonce' );
	$dest  = csqr_link_destination( $post->ID );
	$kind  = csqr_link_kind( $post->ID );
	$url   = csqr_link_url( $post->ID );
	$scans = (int) get_post_meta( $post->ID, '_csqr_scans', true );
	$last  = (int) get_post_meta( $post->ID, '_csqr_last_scan', true );
	$base  = get_option( 'permalink_structure' ) ? home_url( '/' . csqr_get_settings()['linkPrefix'] . '/' ) : home_url( '/?csqr_link=' );
	$kinds = csqr_link_kinds();
	?>
	<table class="form-table csqr-link-form" role="presentation" data-csqr-kind="<?php echo esc_attr( $kind ); ?>">
		<?php if ( count( $kinds ) > 1 ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Opens', 'anderson-qr-code-generator' ); ?></th>
				<td>
					<fieldset class="csqr-kind-choice">
						<legend class="screen-reader-text"><?php esc_html_e( 'What the code opens', 'anderson-qr-code-generator' ); ?></legend>
						<?php foreach ( $kinds as $value => $label ) : ?>
							<label><input type="radio" name="csqr[kind]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $kind, $value ); ?> /> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		<?php endif; ?>
		<tr data-csqr-for="url">
			<th scope="row"><label for="csqr-destination"><?php esc_html_e( 'Destination', 'anderson-qr-code-generator' ); ?></label></th>
			<td>
				<input type="url" id="csqr-destination" name="csqr_destination" class="large-text" value="<?php echo esc_attr( $dest ); ?>" placeholder="https://" />
				<p class="description"><?php esc_html_e( 'Change this any time. Printed codes follow it automatically.', 'anderson-qr-code-generator' ); ?></p>
			</td>
		</tr>
		<?php do_action( 'csqr_link_fields', $post ); ?>
		<tr>
			<th scope="row"><label for="csqr-slug"><?php esc_html_e( 'Short address', 'anderson-qr-code-generator' ); ?></label></th>
			<td>
				<span class="csqr-slug"><span class="csqr-slug__base"><?php echo esc_html( $base ); ?></span><input type="text" id="csqr-slug" name="csqr_slug" value="<?php echo esc_attr( $post->post_name ); ?>" placeholder="<?php esc_attr_e( 'menu', 'anderson-qr-code-generator' ); ?>" pattern="[a-z0-9\-]+" /></span>
				<p class="description"><?php esc_html_e( 'Lowercase letters, numbers and hyphens. Changing it breaks codes already printed with the old address.', 'anderson-qr-code-generator' ); ?></p>
			</td>
		</tr>
		<?php if ( $url ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Scans', 'anderson-qr-code-generator' ); ?></th>
				<td>
					<strong class="csqr-scans"><?php echo esc_html( number_format_i18n( $scans ) ); ?></strong>
					<?php if ( $last ) : ?>
						<span class="description"><?php echo esc_html( sprintf( /* translators: %s: time ago, e.g. "3 hours" */ __( 'last one %s ago', 'anderson-qr-code-generator' ), human_time_diff( $last ) ) ); ?></span>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Counted on your own site. Nothing about the person scanning is stored.', 'anderson-qr-code-generator' ); ?></p>
				</td>
			</tr>
		<?php endif; ?>
	</table>
	<?php
}

/**
 * Code box: the printable code for this short link.
 *
 * @param WP_Post $post Short link.
 * @return void
 */
function csqr_link_code_box( $post ) {
	$url = csqr_link_url( $post->ID );
	if ( ! $url || 'publish' !== $post->post_status ) {
		echo '<p>' . esc_html__( 'Publish the short link to get its QR code.', 'anderson-qr-code-generator' ) . '</p>';
		return;
	}
	echo csqr_render_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
		$url,
		wp_parse_args( array( 'qrSize' => 200 ), csqr_card_design( array() ) ),
		array(
			'label'     => sprintf( /* translators: %s: address */ __( 'QR code for %s', 'anderson-qr-code-generator' ), $url ),
			'caption'   => '<a href="' . esc_url( $url ) . '">' . esc_html( preg_replace( '#^https?://#', '', $url ) ) . '</a>',
			'name'      => 'qr-' . $post->post_name,
			'downloads' => true,
			'check'     => true,
		)
	);
}

/**
 * Save the edit screen. The form posts csqr_destination, csqr_slug and
 * csqr[field] for every other schema field.
 *
 * @param int     $post_id Post id.
 * @param WP_Post $post    Post.
 * @return void
 */
function csqr_link_save( $post_id, $post ) {
	if ( ! isset( $_POST['csqr_link_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csqr_link_nonce'] ) ), 'csqr_link_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$dest = isset( $_POST['csqr_destination'] ) ? esc_url_raw( wp_unslash( $_POST['csqr_destination'] ), array( 'http', 'https' ) ) : '';
	update_post_meta( $post_id, '_csqr_destination', $dest );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitized by its schema below.
	$posted = isset( $_POST['csqr'] ) && is_array( $_POST['csqr'] ) ? wp_unslash( $_POST['csqr'] ) : array();
	foreach ( csqr_link_schema() as $key => $field ) {
		if ( 'destination' === $key ) {
			continue;
		}
		if ( array_key_exists( $key, $posted ) ) {
			update_post_meta( $post_id, $field['meta'], csqr_link_sanitize_field( $field, $posted[ $key ] ) );
		} elseif ( 'bool' === $field['type'] || 'array' === $field['type'] ) {
			// Unchecked boxes and emptied lists aren't posted.
			update_post_meta( $post_id, $field['meta'], 'bool' === $field['type'] ? false : array() );
		}
	}

	$slug = isset( $_POST['csqr_slug'] ) ? sanitize_title( wp_unslash( $_POST['csqr_slug'] ) ) : '';
	if ( '' === $slug ) {
		$slug = sanitize_title( $post->post_title );
	}
	if ( '' !== $slug && $slug !== $post->post_name ) {
		remove_action( 'save_post_' . CSQR_LINK_TYPE, 'csqr_link_save', 10 );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => wp_unique_post_slug( $slug, $post_id, $post->post_status, CSQR_LINK_TYPE, 0 ),
			)
		);
		add_action( 'save_post_' . CSQR_LINK_TYPE, 'csqr_link_save', 10, 2 );
	}
	do_action( 'csqr_link_saved', $post_id, $posted );
}
add_action( 'save_post_' . CSQR_LINK_TYPE, 'csqr_link_save', 10, 2 );

/**
 * Web-address short links can't be published without a destination: keep
 * them drafts. Contact cards don't need one.
 *
 * @param array<string, mixed> $data    Post data.
 * @param array<string, mixed> $postarr Raw post data.
 * @return array<string, mixed>
 */
function csqr_link_require_destination( $data, $postarr ) {
	if ( CSQR_LINK_TYPE !== $data['post_type'] || 'publish' !== $data['post_status'] ) {
		return $data;
	}
	$from_form = isset( $_POST['csqr_destination'], $_POST['csqr_link_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csqr_link_nonce'] ) ), 'csqr_link_save' );
	$id        = ! empty( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
	if ( $from_form ) {
		$dest = esc_url_raw( wp_unslash( $_POST['csqr_destination'] ), array( 'http', 'https' ) );
		$kind = isset( $_POST['csqr']['kind'] ) ? sanitize_key( wp_unslash( $_POST['csqr']['kind'] ) ) : ( $id ? csqr_link_kind( $id ) : 'url' );
	} else {
		$dest = ! empty( $postarr['meta_input']['_csqr_destination'] ) ? esc_url_raw( $postarr['meta_input']['_csqr_destination'], array( 'http', 'https' ) ) : ( $id ? csqr_link_destination( $id ) : '' );
		$kind = ! empty( $postarr['meta_input']['_csqr_kind'] ) ? (string) $postarr['meta_input']['_csqr_kind'] : ( $id ? csqr_link_kind( $id ) : 'url' );
	}
	if ( '' === $dest && 'url' === $kind ) {
		$data['post_status'] = 'draft';
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'csqr_link_require_destination', 10, 2 );

/**
 * List table columns.
 *
 * @param array<string, string> $columns Columns.
 * @return array<string, string>
 */
function csqr_link_columns( $columns ) {
	return array(
		'cb'          => $columns['cb'],
		'title'       => __( 'Name', 'anderson-qr-code-generator' ),
		'csqr_url'    => __( 'Short address', 'anderson-qr-code-generator' ),
		'csqr_dest'   => __( 'Goes to', 'anderson-qr-code-generator' ),
		'csqr_scans'  => __( 'Scans', 'anderson-qr-code-generator' ),
		'csqr_last'   => __( 'Last scan', 'anderson-qr-code-generator' ),
	);
}
add_filter( 'manage_' . CSQR_LINK_TYPE . '_posts_columns', 'csqr_link_columns' );

/**
 * List table cells.
 *
 * @param string $column  Column.
 * @param int    $post_id Post id.
 * @return void
 */
function csqr_link_column( $column, $post_id ) {
	switch ( $column ) {
		case 'csqr_url':
			$url = csqr_link_url( $post_id );
			if ( $url ) {
				echo '<code class="csqr-nowrap">' . esc_html( preg_replace( '#^https?://#', '', $url ) ) . '</code>';
				echo '<div class="row-actions"><a href="' . esc_url( csqr_print_url( 'links', array( $post_id ), 'contact' === csqr_link_kind( $post_id ) ? 'badge' : '' ) ) . '">' . esc_html__( 'Print', 'anderson-qr-code-generator' ) . '</a></div>';
			}
			break;
		case 'csqr_dest':
			if ( 'contact' === csqr_link_kind( $post_id ) ) {
				echo esc_html__( 'Contact card', 'anderson-qr-code-generator' );
				break;
			}
			$dest = csqr_link_destination( $post_id );
			echo $dest ? '<a href="' . esc_url( $dest ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', $dest ) ) . '</a>' : '<em>' . esc_html__( 'Not set', 'anderson-qr-code-generator' ) . '</em>';
			break;
		case 'csqr_scans':
			echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_csqr_scans', true ) ) );
			break;
		case 'csqr_last':
			$last = (int) get_post_meta( $post_id, '_csqr_last_scan', true );
			echo $last ? esc_html( sprintf( /* translators: %s: time ago */ __( '%s ago', 'anderson-qr-code-generator' ), human_time_diff( $last ) ) ) : '—';
			break;
	}
}
add_action( 'manage_' . CSQR_LINK_TYPE . '_posts_custom_column', 'csqr_link_column', 10, 2 );

/**
 * Title placeholder.
 *
 * @param string  $text Placeholder.
 * @param WP_Post $post Post.
 * @return string
 */
function csqr_link_title_placeholder( $text, $post ) {
	return CSQR_LINK_TYPE === $post->post_type ? __( 'Name, for example: Dinner menu', 'anderson-qr-code-generator' ) : $text;
}
add_filter( 'enter_title_here', 'csqr_link_title_placeholder', 10, 2 );

/**
 * Short links for the block editor's picker.
 *
 * @return array<int, array{id: int, title: string, url: string}>
 */
function csqr_links_for_js() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return array();
	}
	$out   = array();
	$posts = get_posts(
		array(
			'post_type'      => CSQR_LINK_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	foreach ( $posts as $p ) {
		$out[] = array(
			'id'    => $p->ID,
			'title' => $p->post_title,
			'url'   => csqr_link_url( $p->ID ),
		);
	}
	return $out;
}
