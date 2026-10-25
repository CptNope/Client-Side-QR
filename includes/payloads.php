<?php
/**
 * What a fixed QR code holds, built on the server (the QR code block, page
 * QR cards and short links). Mirrors CSQR.payload in assets/qr-core.js; the
 * browser tests decode both and compare.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Escape a value for the WiFi QR format: \ ; , : " get a backslash.
 *
 * @param string $value Raw value.
 * @return string
 */
function csqr_escape_wifi( $value ) {
	return preg_replace( '/([\\\\;,:"])/', '\\\\$1', (string) $value );
}

/**
 * Escape a vCard 3.0 text value.
 *
 * @param string $value Raw value.
 * @return string
 */
function csqr_escape_vcard( $value ) {
	$value = str_replace( '\\', '\\\\', (string) $value );
	$value = preg_replace( "/\r?\n/", '\\n', $value );
	return preg_replace( '/([;,])/', '\\\\$1', $value );
}

/**
 * Add https:// to a bare address.
 *
 * @param string $url URL.
 * @return string
 */
function csqr_with_scheme( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	return preg_match( '/^[a-z][a-z0-9+.-]*:/i', $url ) ? $url : 'https://' . $url;
}

/**
 * The text a fixed code holds.
 *
 * @param string               $type   page, link, url, text, wifi, vcard, email, sms, review.
 * @param array<string, mixed> $fields Block attributes.
 * @param int                  $post_id Post being displayed (for "page").
 * @return string Empty when required fields are missing.
 */
function csqr_build_payload( $type, $fields, $post_id = 0 ) {
	$f = function ( $key ) use ( $fields ) {
		return isset( $fields[ $key ] ) && is_scalar( $fields[ $key ] ) ? trim( (string) $fields[ $key ] ) : '';
	};

	switch ( $type ) {
		case 'page':
			$post_id = $post_id ? $post_id : get_the_ID();
			$url     = $post_id ? get_permalink( $post_id ) : '';
			return $url ? (string) $url : home_url( '/' );

		case 'link':
			$link_id = isset( $fields['linkId'] ) ? absint( $fields['linkId'] ) : 0;
			return $link_id ? csqr_link_url( $link_id ) : '';

		case 'url':
		case 'review':
			return esc_url_raw( csqr_with_scheme( $f( 'url' ) ) );

		case 'text':
			return $f( 'text' );

		case 'wifi':
			$ssid = $f( 'ssid' );
			if ( '' === $ssid ) {
				return '';
			}
			$enc = in_array( $f( 'encryption' ), array( 'WEP', 'nopass' ), true ) ? $f( 'encryption' ) : 'WPA';
			$out = 'WIFI:T:' . $enc . ';S:' . csqr_escape_wifi( $ssid ) . ';';
			if ( 'nopass' !== $enc ) {
				// The password is not trimmed: spaces can be part of it.
				$out .= 'P:' . csqr_escape_wifi( isset( $fields['password'] ) ? (string) $fields['password'] : '' ) . ';';
			}
			if ( ! empty( $fields['hidden'] ) ) {
				$out .= 'H:true;';
			}
			return $out . ';';

		case 'vcard':
			$first = $f( 'firstName' );
			$last  = $f( 'lastName' );
			$org   = $f( 'company' );
			if ( '' === $first && '' === $last && '' === $org ) {
				return '';
			}
			$full  = trim( $first . ' ' . $last );
			$lines = array(
				'BEGIN:VCARD',
				'VERSION:3.0',
				'N:' . csqr_escape_vcard( $last ) . ';' . csqr_escape_vcard( $first ) . ';;;',
				'FN:' . csqr_escape_vcard( '' !== $full ? $full : $org ),
			);
			$map   = array(
				'company' => 'ORG',
				'title'   => 'TITLE',
				'phone'   => 'TEL;TYPE=CELL',
				'email'   => 'EMAIL',
			);
			foreach ( $map as $key => $prop ) {
				if ( '' !== $f( $key ) ) {
					$lines[] = $prop . ':' . csqr_escape_vcard( $f( $key ) );
				}
			}
			if ( '' !== $f( 'website' ) ) {
				$lines[] = 'URL:' . csqr_escape_vcard( csqr_with_scheme( $f( 'website' ) ) );
			}
			if ( '' !== $f( 'address' ) ) {
				$lines[] = 'ADR;TYPE=WORK:;;' . csqr_escape_vcard( $f( 'address' ) ) . ';;;;';
			}
			$lines[] = 'END:VCARD';
			return implode( "\r\n", $lines );

		case 'email':
			$to = sanitize_email( $f( 'email' ) );
			if ( '' === $to ) {
				return '';
			}
			$query = array();
			if ( '' !== $f( 'subject' ) ) {
				$query[] = 'subject=' . rawurlencode( $f( 'subject' ) );
			}
			if ( '' !== $f( 'body' ) ) {
				$query[] = 'body=' . rawurlencode( $f( 'body' ) );
			}
			return 'mailto:' . $to . ( $query ? '?' . implode( '&', $query ) : '' );

		case 'sms':
			$phone = preg_replace( '/[^\d+]/', '', $f( 'phone' ) );
			return '' === $phone ? '' : 'SMSTO:' . $phone . ':' . $f( 'message' );
	}

	return '';
}

/**
 * Design settings a QR card is drawn with, from defaults, a saved style and
 * per-instance attributes (in that order).
 *
 * @param array<string, mixed> $attributes Block or shortcode attributes.
 * @return array<string, mixed>
 */
function csqr_card_design( $attributes ) {
	$settings = csqr_get_instance_settings( $attributes );
	$keys     = array( 'qrColorDark', 'qrColorDark2', 'qrColorLight', 'qrGradient', 'qrDotStyle', 'qrEyeStyle', 'qrEyeColor', 'qrCorrectLevel', 'logoUrl', 'logoSize', 'qrSize', 'transparent' );
	$design   = array();
	foreach ( $keys as $key ) {
		if ( array_key_exists( $key, $settings ) ) {
			$design[ $key ] = $settings[ $key ];
		}
	}
	return $design;
}

/**
 * A read-only QR card: the code on its "sheet", a caption, and optionally the
 * print/download panel. Drawn in the browser by assets/qr-ui.js.
 *
 * @param string               $text QR content.
 * @param array<string, mixed> $design Design settings (see csqr_card_design()).
 * @param array<string, mixed> $args { caption, label (accessible name), name (file name), downloads (bool), check (bool), class, align }.
 * @return string HTML.
 */
function csqr_render_card( $text, $design, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'caption'   => '',
			'label'     => __( 'QR code', 'anderson-qr-code-generator' ),
			'name'      => 'qr-code',
			'downloads' => false,
			'check'     => false,
			'class'     => '',
			'align'     => 'center',
		)
	);

	csqr_enqueue_frontend();

	if ( '' === $text ) {
		return '';
	}

	$classes = array( 'csqr', 'csqr-card', 'csqr-card--' . ( in_array( $args['align'], array( 'left', 'right', 'center' ), true ) ? $args['align'] : 'center' ) );
	if ( $args['class'] ) {
		$classes[] = $args['class'];
	}
	$check_mode = '';
	if ( $args['check'] ) {
		$check_mode = 'full';
	} elseif ( ! is_admin() ) {
		$check_mode = current_user_can( 'edit_posts' ) ? 'editor' : 'quiet';
	}
	if ( 'QR code' === $args['label'] || __( 'QR code', 'anderson-qr-code-generator' ) === $args['label'] ) {
		if ( preg_match( '#^https?://#i', $text ) ) {
			/* translators: %s: the web address the QR code opens */
			$args['label'] = sprintf( __( 'QR code for %s', 'anderson-qr-code-generator' ), $text );
		}
	}
	$size = isset( $design['qrSize'] ) ? max( 96, min( 600, (int) $design['qrSize'] ) ) : 200;

	ob_start();
	?>
	<figure class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-csqr-text="<?php echo esc_attr( $text ); ?>" data-csqr-design="<?php echo esc_attr( wp_json_encode( $design ) ); ?>" data-csqr-name="<?php echo esc_attr( sanitize_title( $args['name'] ) ); ?>" style="--csqr-code-size:<?php echo esc_attr( (string) $size ); ?>px">
		<div class="csqr-sheet">
			<div class="csqr-sheet__code" role="img" aria-label="<?php echo esc_attr( $args['label'] ); ?>"></div>
		</div>
		<?php if ( '' !== trim( (string) $args['caption'] ) ) : ?>
			<figcaption class="csqr-card__caption"><?php echo wp_kses( $args['caption'], array( 'strong' => array(), 'em' => array(), 'br' => array(), 'a' => array( 'href' => array() ), 'span' => array( 'class' => array() ), 'code' => array( 'class' => array() ) ) ); ?></figcaption>
		<?php endif; ?>
		<?php if ( 'full' === $check_mode ) : ?>
			<div class="csqr-check"></div>
		<?php elseif ( '' !== $check_mode ) : ?>
			<div class="csqr-check" data-csqr-check="<?php echo esc_attr( $check_mode ); ?>" hidden></div>
		<?php endif; ?>
		<?php if ( $args['downloads'] ) : ?>
			<div class="csqr-export"></div>
		<?php endif; ?>
		<noscript><p class="csqr-card__noscript"><?php esc_html_e( 'Turn on JavaScript to see this QR code.', 'anderson-qr-code-generator' ); ?></p></noscript>
	</figure>
	<?php
	return (string) ob_get_clean();
}
