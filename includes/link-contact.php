<?php
/**
 * Contact-card short links. A contact code that holds the whole card is
 * dense and hard to scan; this one holds only a short address, and the site
 * answers it with a contact file (.vcf) the phone offers to save. The card
 * can include a photo and be edited after printing.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact fields, keyed as in the visitor generator's vCard builder.
 *
 * @return array<string, string> key => label
 */
function csqr_contact_fields() {
	return array(
		'firstName' => __( 'First name', 'anderson-qr-code-generator' ),
		'lastName'  => __( 'Last name', 'anderson-qr-code-generator' ),
		'company'   => __( 'Company', 'anderson-qr-code-generator' ),
		'title'     => __( 'Job title', 'anderson-qr-code-generator' ),
		'phone'     => __( 'Phone', 'anderson-qr-code-generator' ),
		'email'     => __( 'Email', 'anderson-qr-code-generator' ),
		'website'   => __( 'Website', 'anderson-qr-code-generator' ),
		'address'   => __( 'Address', 'anderson-qr-code-generator' ),
	);
}

/**
 * Fields.
 *
 * @param array<string, array<string, mixed>> $schema Schema.
 * @return array<string, array<string, mixed>>
 */
function csqr_contact_schema( $schema ) {
	$schema['contact']       = array(
		'meta'     => '_csqr_contact',
		'type'     => 'array',
		'sanitize' => 'csqr_sanitize_contact',
	);
	$schema['contact_photo'] = array(
		'meta' => '_csqr_contact_photo',
		'type' => 'int',
	);
	return $schema;
}
add_filter( 'csqr_link_schema', 'csqr_contact_schema' );

/**
 * Clean contact details.
 *
 * @param mixed $value Raw.
 * @return array<string, string>
 */
function csqr_sanitize_contact( $value ) {
	$value = is_array( $value ) ? $value : array();
	$out   = array();
	foreach ( array_keys( csqr_contact_fields() ) as $key ) {
		$v = isset( $value[ $key ] ) ? (string) $value[ $key ] : '';
		if ( 'email' === $key ) {
			$v = sanitize_email( $v );
		} elseif ( 'address' === $key ) {
			$v = sanitize_textarea_field( $v );
		} else {
			$v = sanitize_text_field( $v );
		}
		if ( '' !== $v ) {
			$out[ $key ] = $v;
		}
	}
	return $out;
}

/**
 * Contact links answer with a card instead of a redirect.
 *
 * @param array<string, mixed> $target Target.
 * @param WP_Post              $post   Short link.
 * @return array<string, mixed>
 */
function csqr_contact_target( $target, $post ) {
	if ( 'contact' !== csqr_link_kind( $post->ID ) ) {
		return $target;
	}
	$card = csqr_contact_vcard( $post->ID );
	return '' === $card ? array( 'type' => 'missing' ) : array(
		'type' => 'vcard',
		'card' => $card,
	);
}
add_filter( 'csqr_link_target', 'csqr_contact_target', 5, 2 );

/**
 * The contact card (vCard 3.0) for a link, with its photo.
 *
 * @param int $post_id Short link id.
 * @return string
 */
function csqr_contact_vcard( $post_id ) {
	$contact = csqr_sanitize_contact( get_post_meta( $post_id, '_csqr_contact', true ) );
	$card    = csqr_build_payload( 'vcard', $contact );
	if ( '' === $card ) {
		return '';
	}
	$photo = csqr_contact_photo_line( (int) get_post_meta( $post_id, '_csqr_contact_photo', true ) );
	if ( '' !== $photo ) {
		$card = str_replace( "\r\nEND:VCARD", "\r\n" . $photo . "\r\nEND:VCARD", $card );
	}
	return implode( "\r\n", array_map( 'csqr_vcard_fold', explode( "\r\n", $card ) ) ) . "\r\n";
}

/**
 * A PHOTO line from an image in the Media Library (medium size, JPEG or PNG).
 *
 * @param int $attachment_id Attachment id.
 * @return string
 */
function csqr_contact_photo_line( $attachment_id ) {
	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return '';
	}
	$file = get_attached_file( $attachment_id );
	$meta = wp_get_attachment_metadata( $attachment_id );
	if ( $file && ! empty( $meta['sizes']['medium']['file'] ) ) {
		$sized = path_join( dirname( $file ), $meta['sizes']['medium']['file'] );
		if ( is_readable( $sized ) ) {
			$file = $sized;
		}
	}
	if ( ! $file || ! is_readable( $file ) || filesize( $file ) > 512000 ) {
		return '';
	}
	$type = wp_check_filetype( $file );
	$kind = 'image/png' === $type['type'] ? 'PNG' : ( 'image/jpeg' === $type['type'] ? 'JPEG' : '' );
	if ( '' === $kind ) {
		return '';
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local Media Library file.
	$data = file_get_contents( $file );
	return false === $data ? '' : 'PHOTO;ENCODING=b;TYPE=' . $kind . ':' . base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- vCard photos are base64 by specification.
}

/**
 * Fold a vCard line to 75 characters (RFC 6350 §3.2).
 *
 * @param string $line Line.
 * @return string
 */
function csqr_vcard_fold( $line ) {
	if ( strlen( $line ) <= 75 ) {
		return $line;
	}
	$parts = array( substr( $line, 0, 75 ) );
	$rest  = substr( $line, 75 );
	while ( '' !== $rest ) {
		$parts[] = ' ' . substr( $rest, 0, 74 );
		$rest    = (string) substr( $rest, 74 );
	}
	return implode( "\r\n", $parts );
}

/**
 * Send the card.
 *
 * @param array<string, mixed> $target Target.
 * @param WP_Post              $post   Short link.
 * @return void
 */
function csqr_contact_serve( $target, $post ) {
	if ( 'vcard' !== $target['type'] ) {
		return;
	}
	$name = sanitize_file_name( ( $post->post_name ? $post->post_name : 'contact' ) . '.vcf' );
	header( 'Content-Type: text/vcard; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="' . $name . '"' );
	header( 'Content-Length: ' . strlen( $target['card'] ) );
	echo $target['card']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A vCard file, not HTML.
	exit;
}
add_action( 'csqr_serve_link', 'csqr_contact_serve', 10, 2 );

/**
 * Contact rows on the edit screen (shown when "Contact card" is picked).
 *
 * @param WP_Post $post Short link.
 * @return void
 */
function csqr_contact_rows( $post ) {
	$contact = csqr_sanitize_contact( get_post_meta( $post->ID, '_csqr_contact', true ) );
	$photo   = (int) get_post_meta( $post->ID, '_csqr_contact_photo', true );
	if ( ! $contact && 'auto-draft' === $post->post_status ) {
		// Start a new card with the details of the person making it.
		$user    = wp_get_current_user();
		$contact = array_filter(
			array(
				'firstName' => $user->first_name,
				'lastName'  => $user->last_name,
				'email'     => $user->user_email,
				'website'   => $user->user_url,
			)
		);
	}
	foreach ( csqr_contact_fields() as $key => $label ) :
		$id = 'csqr-contact-' . strtolower( $key );
		?>
		<tr data-csqr-for="contact">
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( 'address' === $key ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="csqr[contact][<?php echo esc_attr( $key ); ?>]" rows="2" class="large-text"><?php echo esc_textarea( isset( $contact[ $key ] ) ? $contact[ $key ] : '' ); ?></textarea>
				<?php else : ?>
					<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( 'email' === $key ? 'email' : ( 'phone' === $key ? 'tel' : 'text' ) ); ?>" name="csqr[contact][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( isset( $contact[ $key ] ) ? $contact[ $key ] : '' ); ?>" class="regular-text" />
				<?php endif; ?>
			</td>
		</tr>
		<?php
	endforeach;
	?>
	<tr data-csqr-for="contact">
		<th scope="row"><?php esc_html_e( 'Photo', 'anderson-qr-code-generator' ); ?></th>
		<td>
			<span class="csqr-photo" data-csqr-photo>
				<input type="hidden" name="csqr[contact_photo]" value="<?php echo (int) $photo; ?>" />
				<span class="csqr-photo__preview"><?php echo $photo ? wp_get_attachment_image( $photo, array( 64, 64 ) ) : ''; ?></span>
				<button type="button" class="button" data-csqr-photo-pick><?php esc_html_e( 'Choose from Media Library', 'anderson-qr-code-generator' ); ?></button>
				<button type="button" class="button-link" data-csqr-photo-clear<?php echo $photo ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'anderson-qr-code-generator' ); ?></button>
			</span>
			<p class="description"><?php esc_html_e( 'Shown in the phone’s contacts. A square JPEG or PNG works best.', 'anderson-qr-code-generator' ); ?></p>
			<p class="description"><?php esc_html_e( 'The code holds only the short address, so it stays small and easy to scan, and you can update the details after printing.', 'anderson-qr-code-generator' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'csqr_link_fields', 'csqr_contact_rows' );
