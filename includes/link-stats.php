<?php
/**
 * Scan history and placements for short links.
 *
 * Daily totals only: a date and a number, nothing about the person. A
 * placement is one short link printed in several spots (window, flyer,
 * table card); each spot gets its own code (/qr/menu/window) and its own
 * count, and all of them go to the same destination.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Days of daily history kept per link. */
define( 'CSQR_HISTORY_DAYS', 400 );

/**
 * Placement field: a list of { slug, name }.
 *
 * @param array<string, array<string, mixed>> $schema Schema.
 * @return array<string, array<string, mixed>>
 */
function csqr_stats_schema( $schema ) {
	$schema['placements'] = array(
		'meta'     => '_csqr_placements',
		'type'     => 'array',
		'sanitize' => 'csqr_sanitize_placements',
	);
	return $schema;
}
add_filter( 'csqr_link_schema', 'csqr_stats_schema' );

/**
 * Clean a placements list. Accepts [{name, slug}], ["Window", "Flyer"] or a
 * comma-separated string.
 *
 * @param mixed $value Raw list.
 * @return array<int, array{slug: string, name: string}>
 */
function csqr_sanitize_placements( $value ) {
	if ( is_string( $value ) ) {
		$value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
	}
	$out  = array();
	$seen = array();
	foreach ( (array) $value as $item ) {
		$name = is_array( $item ) ? ( isset( $item['name'] ) ? (string) $item['name'] : '' ) : (string) $item;
		$slug = is_array( $item ) && ! empty( $item['slug'] ) ? (string) $item['slug'] : $name;
		$name = sanitize_text_field( $name );
		$slug = substr( sanitize_title( $slug ), 0, 40 );
		if ( '' === $name || '' === $slug || isset( $seen[ $slug ] ) ) {
			continue;
		}
		$seen[ $slug ] = true;
		$out[]         = array(
			'slug' => $slug,
			'name' => $name,
		);
	}
	return array_slice( $out, 0, 50 );
}

/**
 * Placements of a link.
 *
 * @param int $post_id Short link id.
 * @return array<int, array{slug: string, name: string}>
 */
function csqr_link_placements( $post_id ) {
	return csqr_sanitize_placements( get_post_meta( $post_id, '_csqr_placements', true ) );
}

/**
 * Today's date in the site's time zone (Y-m-d).
 *
 * @param int|null $time Unix time, or now.
 * @return string
 */
function csqr_stats_day( $time = null ) {
	return wp_date( 'Y-m-d', null === $time ? time() : $time );
}

/**
 * Record a counted scan in the daily history and its placement.
 *
 * @param int    $post_id Short link id.
 * @param string $place   Placement slug.
 * @return void
 */
function csqr_stats_record( $post_id, $place ) {
	$day   = csqr_stats_day();
	$daily = get_post_meta( $post_id, '_csqr_daily', true );
	$daily = is_array( $daily ) ? $daily : array();
	$daily[ $day ] = ( isset( $daily[ $day ] ) ? (int) $daily[ $day ] : 0 ) + 1;
	if ( count( $daily ) > CSQR_HISTORY_DAYS ) {
		ksort( $daily );
		$daily = array_slice( $daily, -CSQR_HISTORY_DAYS, null, true );
	}
	update_post_meta( $post_id, '_csqr_daily', $daily );

	// Only placements the link defines are counted separately.
	$known = wp_list_pluck( csqr_link_placements( $post_id ), 'slug' );
	$key   = in_array( $place, $known, true ) ? $place : '';
	$by    = get_post_meta( $post_id, '_csqr_by_place', true );
	$by    = is_array( $by ) ? $by : array();
	$by[ $key ] = ( isset( $by[ $key ] ) ? (int) $by[ $key ] : 0 ) + 1;
	update_post_meta( $post_id, '_csqr_by_place', $by );
}
add_action( 'csqr_scan_counted', 'csqr_stats_record', 10, 2 );

/**
 * Scans per day for the last $days days, oldest first, with gaps filled.
 *
 * @param int $post_id Short link id.
 * @param int $days    Number of days.
 * @return array<string, int> Y-m-d => scans.
 */
function csqr_link_daily( $post_id, $days = 30 ) {
	$daily = get_post_meta( $post_id, '_csqr_daily', true );
	$daily = is_array( $daily ) ? $daily : array();
	$out   = array();
	$now   = time();
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$day         = csqr_stats_day( $now - $i * DAY_IN_SECONDS );
		$out[ $day ] = isset( $daily[ $day ] ) ? (int) $daily[ $day ] : 0;
	}
	return $out;
}

/**
 * Scans per placement, including scans of the plain link (key "").
 *
 * @param int $post_id Short link id.
 * @return array<int, array{slug: string, name: string, scans: int, url: string}>
 */
function csqr_link_place_counts( $post_id ) {
	$by  = get_post_meta( $post_id, '_csqr_by_place', true );
	$by  = is_array( $by ) ? $by : array();
	$out = array(
		array(
			'slug'  => '',
			'name'  => __( 'Main code', 'anderson-qr-code-generator' ),
			'scans' => isset( $by[''] ) ? (int) $by[''] : 0,
			'url'   => csqr_link_url( $post_id ),
		),
	);
	foreach ( csqr_link_placements( $post_id ) as $p ) {
		$out[] = array(
			'slug'  => $p['slug'],
			'name'  => $p['name'],
			'scans' => isset( $by[ $p['slug'] ] ) ? (int) $by[ $p['slug'] ] : 0,
			'url'   => csqr_link_url( $post_id, $p['slug'] ),
		);
	}
	return $out;
}

/**
 * Add the stats to csqr_get_link() (REST, CLI, exports).
 *
 * @param array<string, mixed> $link Link data.
 * @param WP_Post              $post Post.
 * @return array<string, mixed>
 */
function csqr_stats_in_link( $link, $post ) {
	$link['daily']       = csqr_link_daily( $post->ID, 30 );
	$link['by_place']    = csqr_link_place_counts( $post->ID );
	return $link;
}
add_filter( 'csqr_get_link', 'csqr_stats_in_link', 10, 2 );

/* -------------------------------------------------------------------- */
/* Edit screen                                                           */
/* -------------------------------------------------------------------- */

/**
 * Boxes: scan history, and placements.
 *
 * @return void
 */
function csqr_stats_meta_boxes() {
	add_meta_box( 'csqr-link-history', __( 'Scans over the last 30 days', 'anderson-qr-code-generator' ), 'csqr_stats_history_box', CSQR_LINK_TYPE, 'normal', 'default' );
	add_meta_box( 'csqr-link-places', __( 'Where it’s printed', 'anderson-qr-code-generator' ), 'csqr_stats_places_box', CSQR_LINK_TYPE, 'normal', 'default' );
}
add_action( 'add_meta_boxes_' . CSQR_LINK_TYPE, 'csqr_stats_meta_boxes', 20 );

/**
 * Scan history: a bar per day, a hover label on each, and a table view.
 *
 * @param WP_Post $post Short link.
 * @return void
 */
function csqr_stats_history_box( $post ) {
	$daily = csqr_link_daily( $post->ID, 30 );
	$total = array_sum( $daily );
	if ( ! $total ) {
		echo '<p class="csqr-history__empty">' . esc_html__( 'No scans in the last 30 days yet. Each scan of the code shows up here the same day.', 'anderson-qr-code-generator' ) . '</p>';
		return;
	}
	echo csqr_stats_chart( $daily ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
}

/**
 * Bar chart of scans per day (inline SVG, no script).
 *
 * @param array<string, int> $daily Y-m-d => scans, oldest first.
 * @return string HTML.
 */
function csqr_stats_chart( $daily ) {
	$total = array_sum( $daily );
	$max   = max( 1, max( $daily ) );
	$best  = array_search( max( $daily ), $daily, true );
	// A round top value keeps the one gridline honest and readable.
	$steps = array( 1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000, 10000 );
	$top   = $max;
	foreach ( $steps as $step ) {
		if ( $step >= $max ) {
			$top = $step;
			break;
		}
	}
	$n     = count( $daily );
	$w     = 600;
	$h     = 140;
	$pad_l = 32;
	$pad_b = 22;
	$slot  = ( $w - $pad_l ) / $n;
	$bar   = max( 4, $slot - 2 );
	$i     = 0;
	$bars  = '';
	$rows  = '';
	foreach ( $daily as $day => $count ) {
		$x     = $pad_l + $i * $slot + ( $slot - $bar ) / 2;
		$bh    = $count ? max( 2, ( $h - $pad_b - 8 ) * $count / $top ) : 0;
		$y     = $h - $pad_b - $bh;
		$label = sprintf(
			/* translators: 1: date, 2: number of scans */
			_n( '%1$s: %2$s scan', '%1$s: %2$s scans', $count, 'anderson-qr-code-generator' ),
			wp_date( get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ) ),
			number_format_i18n( $count )
		);
		// The hit target is the whole day's column, taller than the bar.
		$bars .= '<g class="csqr-history__day"><title>' . esc_html( $label ) . '</title>';
		$bars .= '<rect class="csqr-history__hit" x="' . esc_attr( (string) round( $pad_l + $i * $slot, 2 ) ) . '" y="0" width="' . esc_attr( (string) round( $slot, 2 ) ) . '" height="' . esc_attr( (string) ( $h - $pad_b ) ) . '" />';
		if ( $count ) {
			$bars .= '<path class="csqr-history__bar" d="' . esc_attr( csqr_stats_bar_path( $x, $y, $bar, $bh ) ) . '" />';
		}
		$bars .= '</g>';
		$rows .= '<tr><td>' . esc_html( wp_date( get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ) ) ) . '</td><td>' . esc_html( number_format_i18n( $count ) ) . '</td></tr>';
		++$i;
	}
	$first = array_key_first( $daily );
	$base  = $h - $pad_b;
	$grid  = $h - $pad_b - ( $h - $pad_b - 8 );

	$svg  = '<svg class="csqr-history__chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr(
		sprintf(
			/* translators: %s: number of scans */
			_n( '%s scan in the last 30 days', '%s scans in the last 30 days', $total, 'anderson-qr-code-generator' ),
			number_format_i18n( $total )
		)
	) . '">';
	$svg .= '<line class="csqr-history__grid" x1="' . $pad_l . '" x2="' . $w . '" y1="' . $grid . '" y2="' . $grid . '" />';
	$svg .= '<text class="csqr-history__tick" x="' . ( $pad_l - 6 ) . '" y="' . ( $grid + 4 ) . '" text-anchor="end">' . esc_html( number_format_i18n( $top ) ) . '</text>';
	$svg .= '<line class="csqr-history__axis" x1="' . $pad_l . '" x2="' . $w . '" y1="' . $base . '" y2="' . $base . '" />';
	$svg .= '<text class="csqr-history__tick" x="' . ( $pad_l - 6 ) . '" y="' . ( $base + 4 ) . '" text-anchor="end">0</text>';
	$svg .= $bars;
	$svg .= '<text class="csqr-history__tick" x="' . $pad_l . '" y="' . ( $h - 4 ) . '">' . esc_html( wp_date( 'M j', strtotime( $first . ' 12:00:00' ) ) ) . '</text>';
	$svg .= '<text class="csqr-history__tick" x="' . $w . '" y="' . ( $h - 4 ) . '" text-anchor="end">' . esc_html__( 'Today', 'anderson-qr-code-generator' ) . '</text>';
	$svg .= '</svg>';

	$out  = '<div class="csqr-history">';
	$out .= '<p class="csqr-history__total"><strong>' . esc_html( number_format_i18n( $total ) ) . '</strong> ' . esc_html( _n( 'scan', 'scans', $total, 'anderson-qr-code-generator' ) );
	if ( $best && max( $daily ) > 0 ) {
		$out .= '<span class="csqr-history__best">' . esc_html(
			sprintf(
				/* translators: 1: date, 2: number of scans */
				__( 'Busiest day: %1$s (%2$s)', 'anderson-qr-code-generator' ),
				wp_date( get_option( 'date_format' ), strtotime( $best . ' 12:00:00' ) ),
				number_format_i18n( max( $daily ) )
			)
		) . '</span>';
	}
	$out .= '</p>' . $svg;
	$out .= '<details class="csqr-history__table"><summary>' . esc_html__( 'Show as a table', 'anderson-qr-code-generator' ) . '</summary><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Day', 'anderson-qr-code-generator' ) . '</th><th scope="col">' . esc_html__( 'Scans', 'anderson-qr-code-generator' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></details>';
	$out .= '</div>';
	return $out;
}

/**
 * A bar with 4px rounded top corners, square at the baseline.
 *
 * @param float $x X.
 * @param float $y Top.
 * @param float $w Width.
 * @param float $h Height.
 * @return string SVG path data.
 */
function csqr_stats_bar_path( $x, $y, $w, $h ) {
	$r = min( 4, $w / 2, $h );
	$b = $y + $h;
	return sprintf(
		'M%1$.2f %2$.2f V%3$.2f Q%1$.2f %4$.2f %5$.2f %4$.2f H%6$.2f Q%7$.2f %4$.2f %7$.2f %3$.2f V%2$.2f Z',
		$x,
		$b,
		$y + $r,
		$y,
		$x + $r,
		$x + $w - $r,
		$x + $w
	);
}

/**
 * Placements: name each spot, get a code per spot, see which works.
 *
 * @param WP_Post $post Short link.
 * @return void
 */
function csqr_stats_places_box( $post ) {
	$places = csqr_link_placements( $post->ID );
	$counts = csqr_link_place_counts( $post->ID );
	$max    = max( 1, max( wp_list_pluck( $counts, 'scans' ) ) );
	$live   = 'publish' === $post->post_status && csqr_link_url( $post->ID );
	?>
	<p class="description"><?php esc_html_e( 'Printing this code in more than one spot? Name each spot to get a separate code for it. They all go to the same place, and you can see which spot gets scanned.', 'anderson-qr-code-generator' ); ?></p>
	<div class="csqr-places" data-csqr-places>
		<ul class="csqr-places__list">
			<?php foreach ( $places as $i => $p ) : ?>
				<li class="csqr-places__item">
					<input type="text" name="csqr[placements][<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $p['name'] ); ?>" aria-label="<?php /* translators: %s: spot name */ echo esc_attr( sprintf( __( 'Name of the spot “%s”', 'anderson-qr-code-generator' ), $p['name'] ) ); ?>" />
					<input type="hidden" name="csqr[placements][<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $p['slug'] ); ?>" />
					<button type="button" class="button-link csqr-places__remove" data-csqr-place-remove aria-label="<?php /* translators: %s: spot name */ echo esc_attr( sprintf( __( 'Remove the spot “%s”', 'anderson-qr-code-generator' ), $p['name'] ) ); ?>"><?php esc_html_e( 'Remove', 'anderson-qr-code-generator' ); ?></button>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="csqr-places__add">
			<label for="csqr-new-place" class="screen-reader-text"><?php esc_html_e( 'New spot', 'anderson-qr-code-generator' ); ?></label>
			<input type="text" id="csqr-new-place" name="csqr[placements][new][name]" placeholder="<?php esc_attr_e( 'For example: Front window', 'anderson-qr-code-generator' ); ?>" />
			<span class="description"><?php esc_html_e( 'Saved when you update the link. Removing a spot stops counting it; its printed code then counts as the main code.', 'anderson-qr-code-generator' ); ?></span>
		</p>
	</div>
	<?php if ( $live && ( $places || $counts[0]['scans'] ) ) : ?>
		<?php if ( array_sum( wp_list_pluck( $counts, 'scans' ) ) < (int) get_post_meta( $post->ID, '_csqr_scans', true ) ) : ?>
			<p class="description"><?php esc_html_e( 'Counts per spot (and per day) started with version 4.4, so they can add up to less than the total.', 'anderson-qr-code-generator' ); ?></p>
		<?php endif; ?>
		<table class="widefat csqr-places__table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Spot', 'anderson-qr-code-generator' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Scans', 'anderson-qr-code-generator' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Its code', 'anderson-qr-code-generator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $counts as $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $row['name'] ); ?><br /><code><?php echo esc_html( preg_replace( '#^https?://#', '', $row['url'] ) ); ?></code></th>
						<td class="csqr-places__count">
							<span class="csqr-places__bar" style="--csqr-share:<?php echo esc_attr( (string) round( 100 * $row['scans'] / $max ) ); ?>%" aria-hidden="true"></span>
							<span class="csqr-places__n"><?php echo esc_html( number_format_i18n( $row['scans'] ) ); ?></span>
						</td>
						<td>
							<?php
							echo csqr_render_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
								$row['url'],
								wp_parse_args( array( 'qrSize' => 96 ), csqr_card_design( array() ) ),
								array(
									'name'  => 'qr-' . $post->post_name . ( $row['slug'] ? '-' . $row['slug'] : '' ),
									'class' => 'csqr-card--mini',
								)
							);
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php if ( $live ) : ?>
		<p class="csqr-places__print">
			<a class="button" href="<?php echo esc_url( csqr_print_url( 'links', array( $post->ID ), 'contact' === csqr_link_kind( $post->ID ) ? 'badge' : '' ) ); ?>"><?php echo $places ? esc_html__( 'Print or download every spot’s code', 'anderson-qr-code-generator' ) : esc_html__( 'Print or download this code', 'anderson-qr-code-generator' ); ?></a>
		</p>
	<?php endif; ?>
	<?php
}

/**
 * Keep the "new spot" input out of the stored list's index order: merge it in.
 *
 * @param mixed $value Raw placements from the form.
 * @return mixed
 */
function csqr_stats_merge_new_place( $value ) {
	if ( is_array( $value ) && isset( $value['new'] ) ) {
		$new = $value['new'];
		unset( $value['new'] );
		$value   = array_values( $value );
		$value[] = $new;
	}
	return $value;
}
add_filter(
	'csqr_link_schema',
	function ( $schema ) {
		$schema['placements']['sanitize'] = function ( $v ) {
			return csqr_sanitize_placements( csqr_stats_merge_new_place( $v ) );
		};
		return $schema;
	},
	20
);

/**
 * Scans column: a small trend note in the list table.
 *
 * @param string $column  Column.
 * @param int    $post_id Post id.
 * @return void
 */
function csqr_stats_column( $column, $post_id ) {
	if ( 'csqr_scans' !== $column ) {
		return;
	}
	$week = array_sum( array_slice( csqr_link_daily( $post_id, 7 ), 0, 7 ) );
	if ( $week ) {
		echo '<br /><span class="description">' . esc_html(
			sprintf(
				/* translators: %s: number of scans */
				__( '%s this week', 'anderson-qr-code-generator' ),
				number_format_i18n( $week )
			)
		) . '</span>';
	}
}
add_action( 'manage_' . CSQR_LINK_TYPE . '_posts_custom_column', 'csqr_stats_column', 20, 2 );
