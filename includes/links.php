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

	add_rewrite_tag( '%csqr_link%', '([^&/]+)' );
	add_rewrite_rule( '^' . preg_quote( csqr_get_settings()['linkPrefix'], '/' ) . '/([^/]+)/?$', 'index.php?csqr_link=$matches[1]', 'top' );
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
function csqr_link_url( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || CSQR_LINK_TYPE !== $post->post_type || '' === $post->post_name ) {
		return '';
	}
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( user_trailingslashit( csqr_get_settings()['linkPrefix'] . '/' . $post->post_name ) );
	}
	return add_query_arg( 'csqr_link', $post->post_name, home_url( '/' ) );
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
	if ( '' === $name || null === $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public redirect, no state change beyond a counter.
		$name = isset( $_GET['csqr_link'] ) ? sanitize_title( wp_unslash( $_GET['csqr_link'] ) ) : '';
	}
	if ( '' === $name ) {
		return;
	}
	$post = get_page_by_path( sanitize_title( $name ), OBJECT, CSQR_LINK_TYPE );
	$dest = $post && 'publish' === $post->post_status ? csqr_link_destination( $post->ID ) : '';

	if ( '' === $dest ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}

	if ( csqr_is_real_scan() ) {
		// Count only: no IP, browser or anything else about the person is stored.
		$count = (int) get_post_meta( $post->ID, '_csqr_scans', true );
		update_post_meta( $post->ID, '_csqr_scans', $count + 1 );
		update_post_meta( $post->ID, '_csqr_last_scan', time() );
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- An admin chose this destination; short links point off-site by design.
	wp_redirect( $dest, 302, 'Anderson QR Code Generator' );
	exit;
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
 * Destination box.
 *
 * @param WP_Post $post Short link.
 * @return void
 */
function csqr_link_destination_box( $post ) {
	wp_nonce_field( 'csqr_link_save', 'csqr_link_nonce' );
	$dest  = csqr_link_destination( $post->ID );
	$url   = csqr_link_url( $post->ID );
	$scans = (int) get_post_meta( $post->ID, '_csqr_scans', true );
	$last  = (int) get_post_meta( $post->ID, '_csqr_last_scan', true );
	$base  = get_option( 'permalink_structure' ) ? home_url( '/' . csqr_get_settings()['linkPrefix'] . '/' ) : home_url( '/?csqr_link=' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="csqr-destination"><?php esc_html_e( 'Destination', 'anderson-qr-code-generator' ); ?></label></th>
			<td>
				<input type="url" id="csqr-destination" name="csqr_destination" class="large-text" required value="<?php echo esc_attr( $dest ); ?>" placeholder="https://" />
				<p class="description"><?php esc_html_e( 'Change this any time. Printed codes follow it automatically.', 'anderson-qr-code-generator' ); ?></p>
			</td>
		</tr>
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
 * Save the destination and address.
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
}
add_action( 'save_post_' . CSQR_LINK_TYPE, 'csqr_link_save', 10, 2 );

/**
 * Short links can't be saved without a destination: keep them drafts.
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
	if ( $from_form ) {
		$dest = esc_url_raw( wp_unslash( $_POST['csqr_destination'] ), array( 'http', 'https' ) );
	} elseif ( ! empty( $postarr['meta_input']['_csqr_destination'] ) ) {
		$dest = esc_url_raw( $postarr['meta_input']['_csqr_destination'], array( 'http', 'https' ) );
	} else {
		$dest = ! empty( $postarr['ID'] ) ? csqr_link_destination( (int) $postarr['ID'] ) : '';
	}
	if ( '' === $dest ) {
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
				echo '<code>' . esc_html( preg_replace( '#^https?://#', '', $url ) ) . '</code>';
			}
			break;
		case 'csqr_dest':
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
