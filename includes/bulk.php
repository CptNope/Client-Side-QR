<?php
/**
 * Many codes at once: create short links from a spreadsheet, export them,
 * and print sheets of codes (cut-out grids, table tents, name badges).
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu: Bulk create, plus the print sheet (reached from other screens).
 *
 * @return void
 */
function csqr_bulk_menu() {
	add_submenu_page( 'csqr-settings', __( 'Create many short links', 'anderson-qr-code-generator' ), __( 'Bulk create', 'anderson-qr-code-generator' ), 'manage_options', 'csqr-bulk', 'csqr_render_bulk_page', 15 );
	add_submenu_page( 'csqr-settings', __( 'Print QR codes', 'anderson-qr-code-generator' ), __( 'Print sheet', 'anderson-qr-code-generator' ), 'edit_posts', 'csqr-print', 'csqr_render_print_page', 16 );
}
add_action( 'admin_menu', 'csqr_bulk_menu', 20 );

/**
 * The print sheet is opened from other screens, so hide its menu item.
 *
 * @return void
 */
function csqr_bulk_hide_print_item() {
	remove_submenu_page( 'csqr-settings', 'csqr-print' );
}
add_action( 'admin_head', 'csqr_bulk_hide_print_item' );

/* -------------------------------------------------------------------- */
/* Import                                                                */
/* -------------------------------------------------------------------- */

/**
 * Columns a spreadsheet can use, with what they mean.
 *
 * @return array<string, string>
 */
function csqr_bulk_columns() {
	return apply_filters(
		'csqr_bulk_columns',
		array(
			'name'        => __( 'Name (required)', 'anderson-qr-code-generator' ),
			'destination' => __( 'Where it goes (required for web addresses)', 'anderson-qr-code-generator' ),
			'slug'        => __( 'Short address, e.g. menu (made from the name if empty)', 'anderson-qr-code-generator' ),
			'placements'  => __( 'Spots it’s printed, separated by commas', 'anderson-qr-code-generator' ),
			'ends'        => __( 'End date, e.g. 2026-12-31 23:59', 'anderson-qr-code-generator' ),
			'after_end'   => __( 'Where it goes after the end date', 'anderson-qr-code-generator' ),
			'ios_url'     => __( 'iPhone destination', 'anderson-qr-code-generator' ),
			'android_url' => __( 'Android destination', 'anderson-qr-code-generator' ),
		)
	);
}

/**
 * Parse CSV text into rows keyed by lowercased header. Accepts commas,
 * semicolons or tabs (whatever the header row uses).
 *
 * @param string $text CSV.
 * @return array<int, array<string, string>>
 */
function csqr_bulk_parse_csv( $text ) {
	$text = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text ); // Excel's byte-order mark.
	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
	$head = strtok( $text, "\n" );
	if ( false === $head ) {
		return array();
	}
	$counts = array(
		','  => substr_count( $head, ',' ),
		';'  => substr_count( $head, ';' ),
		"\t" => substr_count( $head, "\t" ),
	);
	arsort( $counts );
	$sep = (string) array_key_first( $counts );

	$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream for str_getcsv-style parsing with quoted newlines.
	fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	rewind( $handle );
	$header = fgetcsv( $handle, 0, $sep, '"', '\\' );
	if ( ! $header ) {
		return array();
	}
	$header = array_map(
		function ( $h ) {
			$h = strtolower( trim( (string) $h ) );
			$h = preg_replace( '/[^a-z0-9_]+/', '_', $h );
			$aliases = array(
				'title'   => 'name',
				'url'     => 'destination',
				'link'    => 'destination',
				'address' => 'slug',
				'expires' => 'ends',
				'iphone'  => 'ios_url',
				'android' => 'android_url',
			);
			return isset( $aliases[ $h ] ) ? $aliases[ $h ] : $h;
		},
		$header
	);
	$rows = array();
	while ( ( $cells = fgetcsv( $handle, 0, $sep, '"', '\\' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
			continue;
		}
		$row = array();
		foreach ( $header as $i => $key ) {
			if ( '' !== $key ) {
				$row[ $key ] = isset( $cells[ $i ] ) ? trim( (string) $cells[ $i ] ) : '';
			}
		}
		$rows[] = $row;
		if ( count( $rows ) >= 2000 ) {
			break;
		}
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	return $rows;
}

/**
 * Create or update short links from parsed rows.
 *
 * @param array<int, array<string, string>> $rows     Rows.
 * @param string                            $existing "skip" or "update" when the short address is taken.
 * @return array<int, array{row: int, status: string, id?: int, name: string, message?: string}>
 */
function csqr_bulk_import( $rows, $existing = 'skip' ) {
	$allowed = array_merge( array( 'name', 'slug' ), array_keys( csqr_link_schema() ) );
	$out     = array();
	foreach ( $rows as $i => $row ) {
		$args = array_intersect_key( $row, array_flip( $allowed ) );
		$args = array_filter(
			$args,
			function ( $v ) {
				return '' !== $v;
			}
		);
		$name = isset( $args['name'] ) ? $args['name'] : '';
		if ( '' === $name ) {
			$out[] = array(
				'row'     => $i + 2,
				'status'  => 'error',
				'name'    => '',
				'message' => __( 'No name.', 'anderson-qr-code-generator' ),
			);
			continue;
		}
		$slug  = isset( $args['slug'] ) ? sanitize_title( $args['slug'] ) : sanitize_title( $name );
		$found = $slug ? csqr_find_link( $slug ) : 0;
		if ( $found && 'update' !== $existing ) {
			$out[] = array(
				'row'     => $i + 2,
				'status'  => 'skipped',
				'id'      => $found,
				'name'    => $name,
				'message' => __( 'That short address is already used.', 'anderson-qr-code-generator' ),
			);
			continue;
		}
		$args['slug'] = $slug;
		$id           = csqr_save_link( $args, $found );
		if ( is_wp_error( $id ) ) {
			$out[] = array(
				'row'     => $i + 2,
				'status'  => 'error',
				'name'    => $name,
				'message' => $id->get_error_message(),
			);
			continue;
		}
		$out[] = array(
			'row'    => $i + 2,
			'status' => $found ? 'updated' : 'created',
			'id'     => (int) $id,
			'name'   => $name,
		);
	}
	return $out;
}

/**
 * Handle the bulk form: import or export.
 *
 * @return void
 */
function csqr_bulk_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You can’t do that.', 'anderson-qr-code-generator' ), 403 );
	}
	check_admin_referer( 'csqr_bulk' );

	$text = '';
	if ( ! empty( $_FILES['csqr_csv']['tmp_name'] ) && is_uploaded_file( sanitize_text_field( $_FILES['csqr_csv']['tmp_name'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The uploaded spreadsheet.
		$text = (string) file_get_contents( sanitize_text_field( $_FILES['csqr_csv']['tmp_name'] ) );
	} elseif ( isset( $_POST['csqr_rows'] ) ) {
		$text = sanitize_textarea_field( wp_unslash( $_POST['csqr_rows'] ) );
	}
	$existing = isset( $_POST['csqr_existing'] ) && 'update' === $_POST['csqr_existing'] ? 'update' : 'skip';
	$results  = csqr_bulk_import( csqr_bulk_parse_csv( $text ), $existing );

	set_transient( 'csqr_bulk_results_' . get_current_user_id(), $results, HOUR_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=csqr-bulk&imported=1' ) );
	exit;
}
add_action( 'admin_post_csqr_bulk_import', 'csqr_bulk_handle' );

/**
 * Download every short link as a spreadsheet (the same columns as import).
 *
 * @return void
 */
function csqr_bulk_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You can’t do that.', 'anderson-qr-code-generator' ), 403 );
	}
	check_admin_referer( 'csqr_bulk_export' );
	$ids  = get_posts(
		array(
			'post_type'      => CSQR_LINK_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$cols = array( 'name', 'slug', 'url', 'destination', 'placements', 'ends', 'after_end', 'ios_url', 'android_url', 'scans', 'last_scan' );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="short-links-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fputcsv( $out, $cols, ',', '"', '\\' );
	foreach ( $ids as $id ) {
		$link = csqr_get_link( $id );
		$row  = array();
		foreach ( $cols as $col ) {
			$v = isset( $link[ $col ] ) ? $link[ $col ] : '';
			if ( 'placements' === $col && is_array( $v ) ) {
				$v = implode( ', ', wp_list_pluck( $v, 'name' ) );
			} elseif ( 'last_scan' === $col ) {
				$v = $v ? wp_date( 'Y-m-d H:i', (int) $v ) : '';
			} elseif ( is_array( $v ) ) {
				$v = wp_json_encode( $v );
			}
			// Keep spreadsheet apps from running formulas found in data.
			$v     = (string) $v;
			$row[] = preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
		}
		fputcsv( $out, $row, ',', '"', '\\' );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_csqr_bulk_export', 'csqr_bulk_export' );

/**
 * Bulk create page.
 *
 * @return void
 */
function csqr_render_bulk_page() {
	$results = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
	if ( isset( $_GET['imported'] ) ) {
		$results = get_transient( 'csqr_bulk_results_' . get_current_user_id() );
		$results = is_array( $results ) ? $results : array();
		delete_transient( 'csqr_bulk_results_' . get_current_user_id() );
	}
	$example = "name,destination,placements\nDinner menu,https://example.com/dinner,\"Window, Table cards\"\nWine list,https://example.com/wine,";
	?>
	<div class="wrap csqr-admin csqr-bulk">
		<h1><?php esc_html_e( 'Create many short links', 'anderson-qr-code-generator' ); ?></h1>
		<?php if ( $results ) : ?>
			<?php csqr_bulk_results( $results ); ?>
		<?php endif; ?>
		<p><?php esc_html_e( 'Make a short link for every row of a spreadsheet, then print all their codes on one sheet.', 'anderson-qr-code-generator' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="csqr-bulk__form">
			<?php wp_nonce_field( 'csqr_bulk' ); ?>
			<input type="hidden" name="action" value="csqr_bulk_import" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="csqr-csv"><?php esc_html_e( 'Spreadsheet', 'anderson-qr-code-generator' ); ?></label></th>
					<td>
						<input type="file" id="csqr-csv" name="csqr_csv" accept=".csv,text/csv,text/tab-separated-values" />
						<p class="description"><?php esc_html_e( 'Save it as CSV from Excel, Google Sheets or Numbers.', 'anderson-qr-code-generator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="csqr-rows"><?php esc_html_e( 'Or paste rows', 'anderson-qr-code-generator' ); ?></label></th>
					<td><textarea id="csqr-rows" name="csqr_rows" rows="6" class="large-text code" placeholder="<?php echo esc_attr( $example ); ?>"></textarea></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'If a short address is taken', 'anderson-qr-code-generator' ); ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="csqr_existing" value="skip" checked /> <?php esc_html_e( 'Leave the existing link alone', 'anderson-qr-code-generator' ); ?></label><br />
							<label><input type="radio" name="csqr_existing" value="update" /> <?php esc_html_e( 'Update it with the row', 'anderson-qr-code-generator' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Create short links', 'anderson-qr-code-generator' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Columns', 'anderson-qr-code-generator' ); ?></h2>
		<p><?php esc_html_e( 'The first row names the columns. Only name is required; leave out any you don’t need.', 'anderson-qr-code-generator' ); ?></p>
		<table class="widefat striped csqr-bulk__columns">
			<tbody>
				<?php foreach ( csqr_bulk_columns() as $col => $desc ) : ?>
					<tr><th scope="row"><code><?php echo esc_html( $col ); ?></code></th><td><?php echo esc_html( $desc ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Export', 'anderson-qr-code-generator' ); ?></h2>
		<p><?php esc_html_e( 'Download every short link with its scan count, in the same columns. Edit it and import it again to update many links at once.', 'anderson-qr-code-generator' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=csqr_bulk_export' ), 'csqr_bulk_export' ) ); ?>"><?php esc_html_e( 'Download spreadsheet', 'anderson-qr-code-generator' ); ?></a></p>
		<?php csqr_render_about(); ?>
	</div>
	<?php
}

/**
 * The results of an import, with a link to print the new codes.
 *
 * @param array<int, array<string, mixed>> $results Results.
 * @return void
 */
function csqr_bulk_results( $results ) {
	$done  = array_filter(
		$results,
		function ( $r ) {
			return in_array( $r['status'], array( 'created', 'updated' ), true );
		}
	);
	$count = array_count_values( wp_list_pluck( $results, 'status' ) );
	$ids   = array_map( 'intval', wp_list_pluck( $done, 'id' ) );
	$parts = array();
	foreach ( array(
		/* translators: %s: number */
		'created' => _n_noop( '%s created', '%s created', 'anderson-qr-code-generator' ),
		/* translators: %s: number */
		'updated' => _n_noop( '%s updated', '%s updated', 'anderson-qr-code-generator' ),
		/* translators: %s: number */
		'skipped' => _n_noop( '%s skipped', '%s skipped', 'anderson-qr-code-generator' ),
		/* translators: %s: number */
		'error'   => _n_noop( '%s with a problem', '%s with a problem', 'anderson-qr-code-generator' ),
	) as $status => $noop ) {
		if ( ! empty( $count[ $status ] ) ) {
			$parts[] = sprintf( translate_nooped_plural( $noop, $count[ $status ], 'anderson-qr-code-generator' ), number_format_i18n( $count[ $status ] ) );
		}
	}
	?>
	<div class="notice <?php echo empty( $count['error'] ) ? 'notice-success' : 'notice-warning'; ?> csqr-bulk__results">
		<p><strong><?php echo esc_html( implode( ', ', $parts ) ); ?>.</strong>
			<?php if ( $ids ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( csqr_print_url( 'links', $ids ) ); ?>"><?php esc_html_e( 'Print these codes', 'anderson-qr-code-generator' ); ?></a>
			<?php endif; ?>
		</p>
		<?php if ( count( $results ) !== count( $done ) ) : ?>
			<ul>
				<?php foreach ( $results as $r ) : ?>
					<?php if ( ! in_array( $r['status'], array( 'created', 'updated' ), true ) ) : ?>
						<li>
							<?php
							/* translators: 1: row number, 2: name, 3: problem */
							echo esc_html( sprintf( __( 'Row %1$d (%2$s): %3$s', 'anderson-qr-code-generator' ), $r['row'], '' !== $r['name'] ? $r['name'] : '—', $r['message'] ) );
							?>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------- */
/* Print sheet                                                           */
/* -------------------------------------------------------------------- */

/**
 * Address of the print sheet for some items.
 *
 * @param string          $source "links", "products" or another source.
 * @param array<int, int> $ids    Item ids.
 * @return string
 */
function csqr_print_url( $source, $ids ) {
	return add_query_arg(
		array(
			'page'   => 'csqr-print',
			'source' => $source,
			'ids'    => implode( ',', array_map( 'intval', $ids ) ),
		),
		admin_url( 'admin.php' )
	);
}

/**
 * Items for the print sheet. Each: text (what the code holds), title,
 * lines (smaller text under it), name (file name).
 *
 * @param string          $source Source.
 * @param array<int, int> $ids    Ids.
 * @return array<int, array{text: string, title: string, lines: array<int, string>, name: string}>
 */
function csqr_print_items( $source, $ids ) {
	$items = array();
	if ( 'links' === $source && current_user_can( 'manage_options' ) ) {
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || CSQR_LINK_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			$items[] = array(
				'text'  => csqr_link_url( $id ),
				'title' => $post->post_title,
				'lines' => array( preg_replace( '#^https?://#', '', csqr_link_url( $id ) ) ),
				'name'  => 'qr-' . $post->post_name,
			);
			foreach ( csqr_link_placements( $id ) as $p ) {
				$items[] = array(
					'text'  => csqr_link_url( $id, $p['slug'] ),
					'title' => $post->post_title,
					'lines' => array( $p['name'] ),
					'name'  => 'qr-' . $post->post_name . '-' . $p['slug'],
				);
			}
		}
	}
	return apply_filters( 'csqr_print_items', $items, $source, $ids );
}

/**
 * Print sheet layouts.
 *
 * @return array<string, string>
 */
function csqr_print_layouts() {
	return array(
		'grid'  => __( 'Grid to cut out', 'anderson-qr-code-generator' ),
		'tent'  => __( 'Table tents (one per page, fold in half)', 'anderson-qr-code-generator' ),
		'badge' => __( 'Name badges (four per page)', 'anderson-qr-code-generator' ),
	);
}

/**
 * Print sheet page. The codes are drawn in the browser as SVG, so they print
 * sharp at any size.
 *
 * @return void
 */
function csqr_render_print_page() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page; items are filtered by capability.
	$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : 'links';
	$ids    = isset( $_GET['ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ) ) ) : array();
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$items = csqr_print_items( $source, array_slice( $ids, 0, 500 ) );
	$back  = 'products' === $source ? admin_url( 'edit.php?post_type=product' ) : admin_url( 'edit.php?post_type=' . CSQR_LINK_TYPE );
	wp_enqueue_script( 'csqr-print', CSQR_URL . 'assets/qr-print.js', array( 'csqr-ui' ), CSQR_VERSION, true );
	wp_enqueue_style( 'csqr-print', CSQR_URL . 'assets/qr-print.css', array( 'csqr-admin' ), CSQR_VERSION );
	?>
	<div class="wrap csqr-admin csqr-print" data-csqr-print data-csqr-design="<?php echo esc_attr( wp_json_encode( csqr_card_design( array() ) ) ); ?>">
		<h1 class="csqr-print__heading"><?php esc_html_e( 'Print QR codes', 'anderson-qr-code-generator' ); ?></h1>
		<?php if ( ! $items ) : ?>
			<p><?php esc_html_e( 'Nothing to print yet. Pick short links (or products) and choose “Print QR codes” from the Bulk actions menu.', 'anderson-qr-code-generator' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Go to short links', 'anderson-qr-code-generator' ); ?></a></p>
		<?php else : ?>
			<form class="csqr-print__controls" data-csqr-print-controls onsubmit="return false">
				<label><?php esc_html_e( 'Layout', 'anderson-qr-code-generator' ); ?>
					<select data-csqr-print-layout>
						<?php foreach ( csqr_print_layouts() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Paper', 'anderson-qr-code-generator' ); ?>
					<select data-csqr-print-paper>
						<option value="letter" <?php selected( 'in' === csqr_get_settings()['printUnits'] ); ?>><?php esc_html_e( 'US Letter', 'anderson-qr-code-generator' ); ?></option>
						<option value="a4" <?php selected( 'in' !== csqr_get_settings()['printUnits'] ); ?>><?php esc_html_e( 'A4', 'anderson-qr-code-generator' ); ?></option>
					</select>
				</label>
				<label data-csqr-print-for="grid"><?php esc_html_e( 'Code size', 'anderson-qr-code-generator' ); ?>
					<select data-csqr-print-size>
						<option value="3">3 cm (1.2 in)</option>
						<option value="4" selected>4 cm (1.6 in)</option>
						<option value="5">5 cm (2 in)</option>
						<option value="7">7 cm (2.8 in)</option>
					</select>
				</label>
				<label class="csqr-print__line"><?php esc_html_e( 'Extra line', 'anderson-qr-code-generator' ); ?>
					<input type="text" data-csqr-print-extra placeholder="<?php esc_attr_e( 'For example: Scan for the menu', 'anderson-qr-code-generator' ); ?>" />
				</label>
				<span class="csqr-print__actions">
					<button type="button" class="button button-primary" data-csqr-print-go><?php esc_html_e( 'Print or save as PDF', 'anderson-qr-code-generator' ); ?></button>
					<button type="button" class="button" data-csqr-print-zip><?php esc_html_e( 'Download all (zip)', 'anderson-qr-code-generator' ); ?></button>
				</span>
				<p class="description csqr-print__count">
					<?php
					/* translators: %s: number of codes */
					echo esc_html( sprintf( _n( '%s code. Print at 100%% (actual size) so the codes come out the size shown.', '%s codes. Print at 100%% (actual size) so the codes come out the size shown.', count( $items ), 'anderson-qr-code-generator' ), number_format_i18n( count( $items ) ) ) );
					?>
				</p>
				<p class="csqr-print__status" role="status" aria-live="polite" data-csqr-print-status></p>
			</form>
			<script type="application/json" data-csqr-print-items><?php echo wp_json_encode( $items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ); ?></script>
			<div class="csqr-print__pages" data-csqr-print-pages></div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * "Print QR codes" in the short links list's Bulk actions.
 *
 * @param array<string, string> $actions Actions.
 * @return array<string, string>
 */
function csqr_bulk_link_actions( $actions ) {
	$actions['csqr_print'] = __( 'Print QR codes', 'anderson-qr-code-generator' );
	return $actions;
}
add_filter( 'bulk_actions-edit-' . CSQR_LINK_TYPE, 'csqr_bulk_link_actions' );

/**
 * Send the chosen links to the print sheet.
 *
 * @param string          $redirect Redirect.
 * @param string          $action   Action.
 * @param array<int, int> $ids      Ids.
 * @return string
 */
function csqr_bulk_link_print( $redirect, $action, $ids ) {
	return 'csqr_print' === $action ? csqr_print_url( 'links', $ids ) : $redirect;
}
add_filter( 'handle_bulk_actions-edit-' . CSQR_LINK_TYPE, 'csqr_bulk_link_print', 10, 3 );
