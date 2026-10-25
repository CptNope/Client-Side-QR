<?php
/**
 * WooCommerce: a code beside each product's details, printable shelf
 * labels for many products at once, and thank-you cards for an order's
 * box that link to each product's reviews.
 *
 * Loaded only when WooCommerce is active.
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is WooCommerce running?
 *
 * @return bool
 */
function csqr_wc_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}

/**
 * Hook everything once plugins have loaded.
 *
 * @return void
 */
function csqr_wc_init() {
	if ( ! csqr_wc_active() ) {
		return;
	}
	add_filter( 'csqr_page_qr_in_content', 'csqr_wc_not_in_content', 10, 2 );
	add_action( 'woocommerce_product_meta_end', 'csqr_wc_product_code' );
	add_filter( 'bulk_actions-edit-product', 'csqr_wc_bulk_actions' );
	add_filter( 'handle_bulk_actions-edit-product', 'csqr_wc_bulk_print', 10, 3 );
	add_filter( 'csqr_print_items', 'csqr_wc_print_items', 10, 3 );
	add_action( 'add_meta_boxes', 'csqr_wc_order_box', 10, 2 );
}
add_action( 'plugins_loaded', 'csqr_wc_init', 20 );

/**
 * Products show their code with the product details, not after the
 * description.
 *
 * @param bool $show    Show in the content.
 * @param int  $post_id Post id.
 * @return bool
 */
function csqr_wc_not_in_content( $show, $post_id ) {
	// Block themes may not run the classic product-details hook; then the
	// code falls back to the end of the description.
	return 'product' === get_post_type( $post_id ) && ! empty( $GLOBALS['csqr_wc_shown'][ $post_id ] ) ? false : $show;
}

/**
 * The code under the product's SKU and categories, when the page QR is on
 * for products (QR Codes → Settings) or for this product.
 *
 * @return void
 */
function csqr_wc_product_code() {
	$post_id = get_the_ID();
	if ( ! $post_id || ! csqr_page_qr_enabled( $post_id ) ) {
		return;
	}
	$GLOBALS['csqr_wc_shown'][ $post_id ] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Plugin-prefixed flag.
	echo csqr_page_qr_card( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
}

/**
 * "Print QR labels" in the products list.
 *
 * @param array<string, string> $actions Actions.
 * @return array<string, string>
 */
function csqr_wc_bulk_actions( $actions ) {
	$actions['csqr_print'] = __( 'Print QR labels', 'anderson-qr-code-generator' );
	return $actions;
}

/**
 * Send chosen products to the print sheet.
 *
 * @param string          $redirect Redirect.
 * @param string          $action   Action.
 * @param array<int, int> $ids      Product ids.
 * @return string
 */
function csqr_wc_bulk_print( $redirect, $action, $ids ) {
	return 'csqr_print' === $action ? csqr_print_url( 'products', $ids ) : $redirect;
}

/**
 * Plain-text price for a label (sale prices show the sale price).
 *
 * @param WC_Product $product Product.
 * @return string
 */
function csqr_wc_price_text( $product ) {
	$price = $product->get_price();
	if ( '' === $price || null === $price ) {
		return '';
	}
	$text = wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) );
	if ( $product->is_type( 'variable' ) ) {
		/* translators: %s: lowest price */
		$text = sprintf( __( 'From %s', 'anderson-qr-code-generator' ), wp_strip_all_tags( wc_price( $product->get_variation_price( 'min', true ) ) ) );
	}
	return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Print items for products (shelf labels) and orders (thank-you cards).
 *
 * @param array<int, array<string, mixed>> $items  Items.
 * @param string                           $source Source.
 * @param array<int, int>                  $ids    Ids.
 * @return array<int, array<string, mixed>>
 */
function csqr_wc_print_items( $items, $source, $ids ) {
	if ( 'products' === $source ) {
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! current_user_can( 'edit_post', $id ) || 'publish' !== $product->get_status() ) {
				continue;
			}
			$lines = array( csqr_wc_price_text( $product ) );
			if ( $product->get_sku() ) {
				/* translators: %s: SKU */
				$lines[] = sprintf( __( 'SKU %s', 'anderson-qr-code-generator' ), $product->get_sku() );
			}
			$items[] = array(
				'text'  => (string) get_permalink( $id ),
				'title' => $product->get_name(),
				'lines' => array_values( array_filter( $lines ) ),
				'name'  => 'qr-' . $product->get_slug(),
			);
		}
	}
	if ( 'order' === $source && ! empty( $ids[0] ) ) {
		$order = wc_get_order( $ids[0] );
		if ( $order && current_user_can( 'edit_shop_orders' ) ) {
			$seen = array();
			foreach ( $order->get_items() as $line ) {
				$product_id = (int) $line->get_product_id();
				if ( ! $product_id || isset( $seen[ $product_id ] ) || 'publish' !== get_post_status( $product_id ) ) {
					continue;
				}
				$seen[ $product_id ] = true;
				$product             = wc_get_product( $product_id );
				$items[]             = array(
					'text'  => (string) get_permalink( $product_id ) . '#reviews',
					/* translators: %s: product name */
					'title' => sprintf( __( 'Enjoying your %s?', 'anderson-qr-code-generator' ), $product ? $product->get_name() : get_the_title( $product_id ) ),
					'lines' => array(
						/* translators: %s: customer's first name */
						$order->get_billing_first_name() ? sprintf( __( 'Thank you, %s!', 'anderson-qr-code-generator' ), $order->get_billing_first_name() ) : __( 'Thank you for your order!', 'anderson-qr-code-generator' ),
						__( 'Scan to leave a review', 'anderson-qr-code-generator' ),
					),
					'name'  => 'qr-review-' . ( $product ? $product->get_slug() : $product_id ),
				);
			}
		}
	}
	return $items;
}

/**
 * A box on the order screen (classic and HPOS) to print thank-you cards.
 *
 * @param string $screen_id Screen or post type.
 * @param mixed  $order     Post or order.
 * @return void
 */
function csqr_wc_order_box( $screen_id, $order ) {
	$screens = array( 'shop_order' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	if ( ! in_array( $screen_id, $screens, true ) ) {
		return;
	}
	add_meta_box( 'csqr-order-cards', __( 'QR thank-you cards', 'anderson-qr-code-generator' ), 'csqr_wc_order_box_html', $screen_id, 'side', 'low' );
}

/**
 * Order box contents.
 *
 * @param mixed $object Post or order.
 * @return void
 */
function csqr_wc_order_box_html( $object ) {
	$order_id = $object instanceof WP_Post ? $object->ID : ( is_object( $object ) && method_exists( $object, 'get_id' ) ? $object->get_id() : 0 );
	if ( ! $order_id ) {
		return;
	}
	echo '<p>' . esc_html__( 'Print a card for the box with a code to review each product in this order.', 'anderson-qr-code-generator' ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( add_query_arg( 'layout', 'badge', csqr_print_url( 'order', array( $order_id ) ) ) ) . '">' . esc_html__( 'Print thank-you cards', 'anderson-qr-code-generator' ) . '</a></p>';
}
