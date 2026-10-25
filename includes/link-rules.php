<?php
/**
 * Smarter short links: different destinations by time of day or day of the
 * week, an end date, and separate destinations for iPhone and Android.
 *
 * Order when a code is scanned: ended? → phone link → schedule → destination.
 * Times use the site's time zone (Settings → General).
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fields.
 *
 * @param array<string, array<string, mixed>> $schema Schema.
 * @return array<string, array<string, mixed>>
 */
function csqr_rules_schema( $schema ) {
	$schema['schedule']    = array(
		'meta'     => '_csqr_schedule',
		'type'     => 'array',
		'sanitize' => 'csqr_sanitize_schedule',
	);
	$schema['ends']        = array(
		'meta'     => '_csqr_ends',
		'type'     => 'string',
		'sanitize' => 'csqr_sanitize_local_datetime',
	);
	$schema['after_end']   = array(
		'meta' => '_csqr_after_end',
		'type' => 'url',
	);
	$schema['end_message'] = array(
		'meta' => '_csqr_end_message',
		'type' => 'string',
	);
	$schema['ios_url']     = array(
		'meta' => '_csqr_ios_url',
		'type' => 'url',
	);
	$schema['android_url'] = array(
		'meta' => '_csqr_android_url',
		'type' => 'url',
	);
	return $schema;
}
add_filter( 'csqr_link_schema', 'csqr_rules_schema' );

/**
 * "2026-12-31T23:59" or "2026-12-31 23:59" (site time) → "2026-12-31T23:59".
 *
 * @param mixed $value Raw.
 * @return string
 */
function csqr_sanitize_local_datetime( $value ) {
	$value = trim( str_replace( ' ', 'T', (string) $value ) );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
		$value .= 'T23:59';
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
		return '';
	}
	$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
	return $date ? $date->format( 'Y-m-d\TH:i' ) : '';
}

/**
 * "HH:MM" or ''.
 *
 * @param mixed $value Raw.
 * @return string
 */
function csqr_sanitize_clock( $value ) {
	$value = trim( (string) $value );
	if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $value, $m ) || (int) $m[1] > 23 || (int) $m[2] > 59 ) {
		return '';
	}
	return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
}

/**
 * Clean a schedule: up to 10 rules of { days[1-7], from, to, url }.
 * Days use ISO numbers: 1 Monday … 7 Sunday. No days means every day.
 *
 * @param mixed $value Raw.
 * @return array<int, array{days: array<int, int>, from: string, to: string, url: string}>
 */
function csqr_sanitize_schedule( $value ) {
	$out = array();
	foreach ( (array) $value as $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}
		$url = isset( $rule['url'] ) ? esc_url_raw( trim( (string) $rule['url'] ), array( 'http', 'https' ) ) : '';
		if ( '' === $url ) {
			continue;
		}
		$days = isset( $rule['days'] ) ? $rule['days'] : array();
		if ( is_string( $days ) ) {
			$days = preg_split( '/[\s,]+/', $days );
		}
		$days = array_values( array_unique( array_filter( array_map( 'intval', (array) $days ), function ( $d ) {
			return $d >= 1 && $d <= 7;
		} ) ) );
		sort( $days );
		$out[] = array(
			'days' => $days,
			'from' => csqr_sanitize_clock( isset( $rule['from'] ) ? $rule['from'] : '' ),
			'to'   => csqr_sanitize_clock( isset( $rule['to'] ) ? $rule['to'] : '' ),
			'url'  => $url,
		);
	}
	return array_slice( $out, 0, 10 );
}

/**
 * Does a schedule rule apply at this moment?
 *
 * @param array<string, mixed> $rule Rule.
 * @param DateTimeImmutable    $now  Now, in the site's time zone.
 * @return bool
 */
function csqr_rule_matches( $rule, $now ) {
	$day   = (int) $now->format( 'N' );
	$clock = $now->format( 'H:i' );
	$from  = $rule['from'];
	$to    = $rule['to'];
	// A window past midnight (22:00–02:00) belongs to the day it starts on.
	$wraps = '' !== $from && '' !== $to && $from > $to;
	if ( $wraps && $clock < $to ) {
		$day = 1 === $day ? 7 : $day - 1;
	}
	if ( $rule['days'] && ! in_array( $day, $rule['days'], true ) ) {
		return false;
	}
	if ( '' === $from && '' === $to ) {
		return true;
	}
	if ( $wraps ) {
		return $clock >= $from || $clock < $to;
	}
	return ( '' === $from || $clock >= $from ) && ( '' === $to || $clock < $to );
}

/**
 * Has the link's end date passed?
 *
 * @param int                    $post_id Short link id.
 * @param DateTimeImmutable|null $now     Now.
 * @return bool
 */
function csqr_link_has_ended( $post_id, $now = null ) {
	$ends = (string) get_post_meta( $post_id, '_csqr_ends', true );
	if ( '' === $ends ) {
		return false;
	}
	$end = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $ends, wp_timezone() );
	$now = $now ? $now : new DateTimeImmutable( 'now', wp_timezone() );
	return $end && $now >= $end;
}

/**
 * Which phone is scanning, from the browser's description: ios, android or ''.
 *
 * @return string
 */
function csqr_scanner_platform() {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	if ( preg_match( '/iPhone|iPad|iPod/i', $agent ) ) {
		return 'ios';
	}
	if ( false !== stripos( $agent, 'Android' ) ) {
		return 'android';
	}
	return '';
}

/**
 * Apply phone links and the schedule to web-address links.
 *
 * @param array<string, mixed> $target Target so far.
 * @param WP_Post              $post   Short link.
 * @return array<string, mixed>
 */
function csqr_rules_target( $target, $post ) {
	if ( 'url' !== csqr_link_kind( $post->ID ) ) {
		return $target;
	}
	$platform = csqr_scanner_platform();
	if ( $platform ) {
		$phone = (string) get_post_meta( $post->ID, '_csqr_' . $platform . '_url', true );
		if ( '' !== $phone ) {
			return array(
				'type' => 'redirect',
				'url'  => $phone,
				'rule' => $platform,
			);
		}
	}
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	foreach ( csqr_sanitize_schedule( get_post_meta( $post->ID, '_csqr_schedule', true ) ) as $i => $rule ) {
		if ( csqr_rule_matches( $rule, $now ) ) {
			return array(
				'type' => 'redirect',
				'url'  => $rule['url'],
				'rule' => 'schedule-' . $i,
			);
		}
	}
	return $target;
}
add_filter( 'csqr_link_target', 'csqr_rules_target', 10, 2 );

/**
 * After the end date: go to the "after" address, or show the message.
 * Runs last, so it applies to every kind of link.
 *
 * @param array<string, mixed> $target Target so far.
 * @param WP_Post              $post   Short link.
 * @return array<string, mixed>
 */
function csqr_expiry_target( $target, $post ) {
	if ( 'missing' === $target['type'] || ! csqr_link_has_ended( $post->ID ) ) {
		return $target;
	}
	$after = (string) get_post_meta( $post->ID, '_csqr_after_end', true );
	if ( '' !== $after ) {
		return array(
			'type' => 'redirect',
			'url'  => $after,
			'rule' => 'ended',
		);
	}
	$message = (string) get_post_meta( $post->ID, '_csqr_end_message', true );
	return array(
		'type'    => 'ended',
		'message' => '' !== $message ? $message : __( 'This offer has ended. Thanks for scanning!', 'anderson-qr-code-generator' ),
		'status'  => 410,
	);
}
add_filter( 'csqr_link_target', 'csqr_expiry_target', 50, 2 );

/* -------------------------------------------------------------------- */
/* Edit screen                                                           */
/* -------------------------------------------------------------------- */

/**
 * Box for the rules.
 *
 * @return void
 */
function csqr_rules_meta_box() {
	add_meta_box( 'csqr-link-rules', __( 'Change where it goes automatically', 'anderson-qr-code-generator' ), 'csqr_rules_box', CSQR_LINK_TYPE, 'normal', 'default' );
}
add_action( 'add_meta_boxes_' . CSQR_LINK_TYPE, 'csqr_rules_meta_box', 15 );

/**
 * Rules box: schedule, phones, end date.
 *
 * @param WP_Post $post Short link.
 * @return void
 */
function csqr_rules_box( $post ) {
	$schedule = csqr_sanitize_schedule( get_post_meta( $post->ID, '_csqr_schedule', true ) );
	$ends     = (string) get_post_meta( $post->ID, '_csqr_ends', true );
	$after    = (string) get_post_meta( $post->ID, '_csqr_after_end', true );
	$message  = (string) get_post_meta( $post->ID, '_csqr_end_message', true );
	$ios      = (string) get_post_meta( $post->ID, '_csqr_ios_url', true );
	$android  = (string) get_post_meta( $post->ID, '_csqr_android_url', true );
	$now      = new DateTimeImmutable( 'now', wp_timezone() );
	$active   = csqr_link_target( $post );
	$days     = array(
		1 => __( 'Mon', 'anderson-qr-code-generator' ),
		2 => __( 'Tue', 'anderson-qr-code-generator' ),
		3 => __( 'Wed', 'anderson-qr-code-generator' ),
		4 => __( 'Thu', 'anderson-qr-code-generator' ),
		5 => __( 'Fri', 'anderson-qr-code-generator' ),
		6 => __( 'Sat', 'anderson-qr-code-generator' ),
		7 => __( 'Sun', 'anderson-qr-code-generator' ),
	);
	// Always offer one empty row to add a rule.
	$rows   = $schedule;
	$rows[] = array(
		'days' => array(),
		'from' => '',
		'to'   => '',
		'url'  => '',
	);
	?>
	<div class="csqr-rules" data-csqr-for="url">
		<h3 class="csqr-rules__heading"><?php esc_html_e( 'By time of day', 'anderson-qr-code-generator' ); ?></h3>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: time zone name, 2: current time */
					__( 'The first matching rule wins; otherwise the code uses the destination above. Times are in %1$s (now %2$s).', 'anderson-qr-code-generator' ),
					wp_timezone_string(),
					$now->format( get_option( 'time_format' ) )
				)
			);
			?>
		</p>
		<div class="csqr-rules__list">
			<?php foreach ( $rows as $i => $rule ) : ?>
				<fieldset class="csqr-rule">
					<legend class="screen-reader-text">
						<?php
						/* translators: %d: rule number */
						echo esc_html( sprintf( __( 'Rule %d', 'anderson-qr-code-generator' ), $i + 1 ) );
						?>
					</legend>
					<span class="csqr-rule__days">
						<?php foreach ( $days as $n => $label ) : ?>
							<label><input type="checkbox" name="csqr[schedule][<?php echo (int) $i; ?>][days][]" value="<?php echo (int) $n; ?>" <?php checked( in_array( $n, $rule['days'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</span>
					<span class="csqr-rule__time">
						<label><?php esc_html_e( 'From', 'anderson-qr-code-generator' ); ?> <input type="time" name="csqr[schedule][<?php echo (int) $i; ?>][from]" value="<?php echo esc_attr( $rule['from'] ); ?>" /></label>
						<label><?php esc_html_e( 'until', 'anderson-qr-code-generator' ); ?> <input type="time" name="csqr[schedule][<?php echo (int) $i; ?>][to]" value="<?php echo esc_attr( $rule['to'] ); ?>" /></label>
					</span>
					<label class="csqr-rule__url"><?php esc_html_e( 'go to', 'anderson-qr-code-generator' ); ?> <input type="url" class="regular-text" name="csqr[schedule][<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $rule['url'] ); ?>" placeholder="https://" /></label>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e( 'No days ticked means every day. To remove a rule, clear its address.', 'anderson-qr-code-generator' ); ?></p>

		<h3 class="csqr-rules__heading"><?php esc_html_e( 'By phone', 'anderson-qr-code-generator' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="csqr-ios"><?php esc_html_e( 'iPhone and iPad', 'anderson-qr-code-generator' ); ?></label></th>
				<td><input type="url" id="csqr-ios" class="large-text" name="csqr[ios_url]" value="<?php echo esc_attr( $ios ); ?>" placeholder="https://apps.apple.com/…" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="csqr-android"><?php esc_html_e( 'Android', 'anderson-qr-code-generator' ); ?></label></th>
				<td>
					<input type="url" id="csqr-android" class="large-text" name="csqr[android_url]" value="<?php echo esc_attr( $android ); ?>" placeholder="https://play.google.com/…" />
					<p class="description"><?php esc_html_e( 'Handy for app links. Leave empty to use the destination above. Phone links win over the time rules.', 'anderson-qr-code-generator' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<h3 class="csqr-rules__heading"><?php esc_html_e( 'End date', 'anderson-qr-code-generator' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="csqr-ends"><?php esc_html_e( 'Stop on', 'anderson-qr-code-generator' ); ?></label></th>
			<td><input type="datetime-local" id="csqr-ends" name="csqr[ends]" value="<?php echo esc_attr( $ends ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="csqr-after-end"><?php esc_html_e( 'Then go to', 'anderson-qr-code-generator' ); ?></label></th>
			<td><input type="url" id="csqr-after-end" class="large-text" name="csqr[after_end]" value="<?php echo esc_attr( $after ); ?>" placeholder="https://" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="csqr-end-message"><?php esc_html_e( 'Or show', 'anderson-qr-code-generator' ); ?></label></th>
			<td>
				<input type="text" id="csqr-end-message" class="large-text" name="csqr[end_message]" value="<?php echo esc_attr( $message ); ?>" placeholder="<?php esc_attr_e( 'This offer has ended. Thanks for scanning!', 'anderson-qr-code-generator' ); ?>" />
				<p class="description"><?php esc_html_e( 'For limited-time offers and events. Without an address, people who scan see this message.', 'anderson-qr-code-generator' ); ?></p>
			</td>
		</tr>
	</table>
	<?php if ( 'publish' === $post->post_status ) : ?>
		<p class="csqr-rules__now">
			<strong><?php esc_html_e( 'Right now a scan goes to:', 'anderson-qr-code-generator' ); ?></strong>
			<?php if ( 'redirect' === $active['type'] ) : ?>
				<a href="<?php echo esc_url( $active['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $active['url'] ); ?></a>
			<?php elseif ( 'missing' === $active['type'] ) : ?>
				<em><?php esc_html_e( 'nowhere yet: add a destination', 'anderson-qr-code-generator' ); ?></em>
			<?php else : ?>
				<?php echo esc_html( isset( $active['message'] ) ? $active['message'] : csqr_link_kinds()[ csqr_link_kind( $post->ID ) ] ); ?>
			<?php endif; ?>
			<span class="description"><?php esc_html_e( '(on a computer; phone links can differ)', 'anderson-qr-code-generator' ); ?></span>
		</p>
	<?php endif; ?>
	<?php
}
