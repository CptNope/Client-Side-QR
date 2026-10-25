<?php
/**
 * WP-CLI commands for short links.
 *
 *   wp qr link list [--format=table|csv|json]
 *   wp qr link create <name> <destination> [--slug=<slug>] [--placements=<a,b>] [--ends=<date>]
 *   wp qr link update <id|slug> [--destination=<url>] [--name=<name>] [--slug=<slug>] [--ends=<date>] …
 *   wp qr link get <id|slug> [--format=json]
 *   wp qr link stats <id|slug> [--days=30]
 *   wp qr link delete <id|slug>... [--force]
 *   wp qr import <file.csv> [--update]
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Manage short links.
 */
class CSQR_CLI_Link {

	/**
	 * Find a link by id or short address.
	 *
	 * @param string $ref Id or slug.
	 * @return int
	 */
	private function resolve( $ref ) {
		$id = ctype_digit( (string) $ref ) ? (int) $ref : csqr_find_link( $ref );
		if ( ! $id || ! csqr_get_link( $id ) ) {
			WP_CLI::error( sprintf( 'No short link "%s".', $ref ) );
		}
		return $id;
	}

	/**
	 * Fields from --options.
	 *
	 * @param array<string, mixed> $assoc Options.
	 * @return array<string, mixed>
	 */
	private function fields( $assoc ) {
		$allowed = array_merge( array( 'name', 'slug', 'status' ), array_keys( csqr_link_schema() ) );
		$out     = array();
		foreach ( $assoc as $key => $value ) {
			$key = str_replace( '-', '_', $key );
			if ( in_array( $key, $allowed, true ) ) {
				$decoded     = is_string( $value ) && preg_match( '/^[\[{]/', $value ) ? json_decode( $value, true ) : null;
				$out[ $key ] = null !== $decoded ? $decoded : $value;
			}
		}
		return $out;
	}

	/**
	 * List short links.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json, yaml or ids.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array<int, string>   $args  Arguments.
	 * @param array<string, mixed> $assoc Options.
	 * @return void
	 */
	public function list_( $args, $assoc ) {
		$ids = get_posts(
			array(
				'post_type'      => CSQR_LINK_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', $ids ) );
			return;
		}
		$rows = array_map(
			function ( $id ) {
				$l = csqr_get_link( $id );
				return array(
					'id'          => $l['id'],
					'name'        => $l['name'],
					'slug'        => $l['slug'],
					'status'      => $l['status'],
					'kind'        => $l['kind'],
					'destination' => $l['destination'],
					'url'         => $l['url'],
					'scans'       => $l['scans'],
				);
			},
			$ids
		);
		WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'name', 'slug', 'status', 'kind', 'destination', 'url', 'scans' ) );
	}

	/**
	 * Create a short link.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : What to call it.
	 *
	 * [<destination>]
	 * : Where it goes (not needed with --kind=contact).
	 *
	 * [--slug=<slug>]
	 * : Short address (made from the name if left out).
	 *
	 * [--<field>=<value>]
	 * : Any other field: placements, ends, after_end, end_message, ios_url,
	 * android_url, kind, contact (JSON), schedule (JSON).
	 *
	 * [--porcelain]
	 * : Print only the new id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp qr link create "Dinner menu" https://example.com/dinner --placements="Window, Table cards"
	 *
	 * @param array<int, string>   $args  Arguments.
	 * @param array<string, mixed> $assoc Options.
	 * @return void
	 */
	public function create( $args, $assoc ) {
		$fields         = $this->fields( $assoc );
		$fields['name'] = $args[0];
		if ( isset( $args[1] ) ) {
			$fields['destination'] = $args[1];
		}
		$slug = ! empty( $fields['slug'] ) ? $fields['slug'] : sanitize_title( $fields['name'] );
		if ( csqr_find_link( $slug ) ) {
			WP_CLI::error( sprintf( 'The short address "%s" is already used.', $slug ) );
		}
		$id = csqr_save_link( $fields );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		if ( ! empty( $assoc['porcelain'] ) ) {
			WP_CLI::line( (string) $id );
			return;
		}
		WP_CLI::success( sprintf( 'Created %s → %s', csqr_link_url( $id ), csqr_link_destination( $id ) ) );
	}

	/**
	 * Change a short link. Only the options given are changed.
	 *
	 * ## OPTIONS
	 *
	 * <link>
	 * : Id or short address.
	 *
	 * [--<field>=<value>]
	 * : name, slug, destination, placements, ends, after_end, ios_url, …
	 *
	 * ## EXAMPLES
	 *
	 *     wp qr link update menu --destination=https://example.com/winter-menu
	 *
	 * @param array<int, string>   $args  Arguments.
	 * @param array<string, mixed> $assoc Options.
	 * @return void
	 */
	public function update( $args, $assoc ) {
		$id     = $this->resolve( $args[0] );
		$fields = $this->fields( $assoc );
		if ( ! $fields ) {
			WP_CLI::error( 'Nothing to change. Give at least one --field=value.' );
		}
		$result = csqr_save_link( $fields, $id );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Updated %s → %s', csqr_link_url( $id ), csqr_link_destination( $id ) ) );
	}

	/**
	 * Show everything about a short link.
	 *
	 * ## OPTIONS
	 *
	 * <link>
	 * : Id or short address.
	 *
	 * [--format=<format>]
	 * : json or yaml.
	 * ---
	 * default: json
	 * ---
	 *
	 * @param array<int, string>   $args  Arguments.
	 * @param array<string, mixed> $assoc Options.
	 * @return void
	 */
	public function get( $args, $assoc ) {
		$link = csqr_get_link( $this->resolve( $args[0] ) );
		if ( isset( $assoc['format'] ) && 'yaml' === $assoc['format'] ) {
			WP_CLI\Utils\format_items( 'yaml', array( $link ), array_keys( $link ) );
			return;
		}
		WP_CLI::line( (string) wp_json_encode( $link, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Scans per day and per spot.
	 *
	 * ## OPTIONS
	 *
	 * <link>
	 * : Id or short address.
	 *
	 * [--days=<days>]
	 * : How many days back.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--format=<format>]
	 * : table, csv or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int, string>   $args  Arguments.
	 * @param array<string, mixed> $assoc Options.
	 * @return void
	 */
	public function stats( $args, $assoc ) {
		$id    = $this->resolve( $args[0] );
		$days  = max( 1, min( CSQR_HISTORY_DAYS, (int) $assoc['days'] ) );
		$rows  = array();
		$daily = csqr_link_daily( $id, $days );
		foreach ( $daily as $day => $count ) {
			$rows[] = array(
				'day'   => $day,
				'scans' => $count,
			);
		}
		WP_CLI\Utils\format_items( $assoc['format'], $rows, array( 'day', 'scans' ) );
		if ( 'table' === $assoc['format'] ) {
			WP_CLI::line( sprintf( '%d scans in %d days; %d in total.', array_sum( $daily ), $days, (int) get_post_meta( $id, '_csqr_scans', true ) ) );
			foreach ( csqr_link_place_counts( $id ) as $place ) {
				WP_CLI::line( sprintf( '  %s: %d', $place['name'], $place['scans'] ) );
			}
		}
	}

	/**
	 * Remove short links (to the trash, or for good with --force).
	 *
	 * ## OPTIONS
	 *
	 * <link>...
	 * : Ids or short addresses.
	 *
	 * [--force]
	 * : Delete instead of moving to the trash.
	 *
	 * @param array<int, string>   $args  Arguments.
	 * @param array<string, mixed> $assoc Options.
	 * @return void
	 */
	public function delete( $args, $assoc ) {
		foreach ( $args as $ref ) {
			$id = $this->resolve( $ref );
			$ok = ! empty( $assoc['force'] ) ? wp_delete_post( $id, true ) : wp_trash_post( $id );
			if ( $ok ) {
				WP_CLI::success( sprintf( '%s "%s".', ! empty( $assoc['force'] ) ? 'Deleted' : 'Trashed', $ref ) );
			} else {
				WP_CLI::warning( sprintf( 'Couldn’t remove "%s".', $ref ) );
			}
		}
	}
}
WP_CLI::add_command( 'qr link', 'CSQR_CLI_Link' );

/**
 * Create short links from a spreadsheet (same columns as QR Codes → Bulk create).
 *
 * ## OPTIONS
 *
 * <file>
 * : CSV file. The first row names the columns.
 *
 * [--update]
 * : Update links whose short address already exists, instead of skipping them.
 *
 * ## EXAMPLES
 *
 *     wp qr import links.csv --update
 *
 * @param array<int, string>   $args  Arguments.
 * @param array<string, mixed> $assoc Options.
 * @return void
 */
function csqr_cli_import( $args, $assoc ) {
	if ( ! is_readable( $args[0] ) ) {
		WP_CLI::error( sprintf( 'Can’t read %s.', $args[0] ) );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file given on the command line.
	$results = csqr_bulk_import( csqr_bulk_parse_csv( (string) file_get_contents( $args[0] ) ), ! empty( $assoc['update'] ) ? 'update' : 'skip' );
	$count   = array_count_values( wp_list_pluck( $results, 'status' ) );
	foreach ( $results as $r ) {
		if ( ! in_array( $r['status'], array( 'created', 'updated' ), true ) ) {
			WP_CLI::warning( sprintf( 'Row %d (%s): %s', $r['row'], $r['name'], $r['message'] ) );
		}
	}
	WP_CLI::success(
		sprintf(
			'%d created, %d updated, %d skipped, %d with problems.',
			isset( $count['created'] ) ? $count['created'] : 0,
			isset( $count['updated'] ) ? $count['updated'] : 0,
			isset( $count['skipped'] ) ? $count['skipped'] : 0,
			isset( $count['error'] ) ? $count['error'] : 0
		)
	);
}
WP_CLI::add_command( 'qr import', 'csqr_cli_import' );
