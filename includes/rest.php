<?php
/**
 * REST API for short links, for agencies and scripts managing many sites.
 *
 *   GET    /wp-json/anderson-qr/v1/links            list (search, page, per_page)
 *   POST   /wp-json/anderson-qr/v1/links            create
 *   GET    /wp-json/anderson-qr/v1/links/{id}       one link
 *   PATCH  /wp-json/anderson-qr/v1/links/{id}       update (only the fields sent)
 *   DELETE /wp-json/anderson-qr/v1/links/{id}       move to the trash (?force=true deletes)
 *   GET    /wp-json/anderson-qr/v1/links/{id}/stats daily scans (?days=30) and spots
 *
 * Needs a user who can manage options; from outside the site, use an
 * Application Password (Users → Profile).
 *
 * @package AndersonQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CSQR_REST_NAMESPACE', 'anderson-qr/v1' );

/**
 * Routes.
 *
 * @return void
 */
function csqr_rest_routes() {
	$can = function () {
		return current_user_can( 'manage_options' );
	};
	$id  = array(
		'id' => array(
			'type'     => 'integer',
			'required' => true,
		),
	);
	register_rest_route(
		CSQR_REST_NAMESPACE,
		'/links',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'csqr_rest_list',
				'permission_callback' => $can,
				'args'                => array(
					'search'   => array( 'type' => 'string' ),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 200,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'csqr_rest_create',
				'permission_callback' => $can,
			),
		)
	);
	register_rest_route(
		CSQR_REST_NAMESPACE,
		'/links/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'csqr_rest_get',
				'permission_callback' => $can,
				'args'                => $id,
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'csqr_rest_update',
				'permission_callback' => $can,
				'args'                => $id,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'csqr_rest_delete',
				'permission_callback' => $can,
				'args'                => $id + array(
					'force' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
		)
	);
	register_rest_route(
		CSQR_REST_NAMESPACE,
		'/links/(?P<id>\d+)/stats',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'csqr_rest_stats',
			'permission_callback' => $can,
			'args'                => $id + array(
				'days' => array(
					'type'    => 'integer',
					'default' => 30,
					'minimum' => 1,
					'maximum' => CSQR_HISTORY_DAYS,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'csqr_rest_routes' );

/**
 * The fields a request may set.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string, mixed>
 */
function csqr_rest_fields( $request ) {
	$body    = $request->get_json_params();
	$body    = is_array( $body ) && $body ? $body : $request->get_body_params();
	$allowed = array_merge( array( 'name', 'slug', 'status' ), array_keys( csqr_link_schema() ) );
	return array_intersect_key( (array) $body, array_flip( $allowed ) );
}

/**
 * A link, or a 404 error.
 *
 * @param int $id Link id.
 * @return array<string, mixed>|WP_Error
 */
function csqr_rest_link_or_error( $id ) {
	$link = csqr_get_link( (int) $id );
	if ( ! $link || 'trash' === $link['status'] ) {
		return new WP_Error( 'csqr_not_found', __( 'No short link with that id.', 'anderson-qr-code-generator' ), array( 'status' => 404 ) );
	}
	return $link;
}

/**
 * GET /links.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function csqr_rest_list( $request ) {
	$query = new WP_Query(
		array(
			'post_type'      => CSQR_LINK_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => (int) $request['per_page'],
			'paged'          => (int) $request['page'],
			's'              => (string) $request['search'],
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);
	$response = rest_ensure_response( array_map( 'csqr_get_link', $query->posts ) );
	$response->header( 'X-WP-Total', (string) $query->found_posts );
	$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
	return $response;
}

/**
 * POST /links.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function csqr_rest_create( $request ) {
	$fields = csqr_rest_fields( $request );
	if ( empty( $fields['name'] ) ) {
		return new WP_Error( 'csqr_no_name', __( 'A short link needs a name.', 'anderson-qr-code-generator' ), array( 'status' => 400 ) );
	}
	$slug = ! empty( $fields['slug'] ) ? $fields['slug'] : sanitize_title( $fields['name'] );
	if ( csqr_find_link( $slug ) ) {
		return new WP_Error( 'csqr_slug_taken', __( 'That short address is already used.', 'anderson-qr-code-generator' ), array( 'status' => 409 ) );
	}
	$id = csqr_save_link( $fields );
	if ( is_wp_error( $id ) ) {
		$id->add_data( array( 'status' => 400 ) );
		return $id;
	}
	$response = rest_ensure_response( csqr_get_link( $id ) );
	$response->set_status( 201 );
	return $response;
}

/**
 * GET /links/{id}.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string, mixed>|WP_Error
 */
function csqr_rest_get( $request ) {
	return csqr_rest_link_or_error( $request['id'] );
}

/**
 * PATCH /links/{id}.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string, mixed>|WP_Error
 */
function csqr_rest_update( $request ) {
	$link = csqr_rest_link_or_error( $request['id'] );
	if ( is_wp_error( $link ) ) {
		return $link;
	}
	$fields = csqr_rest_fields( $request );
	if ( ! empty( $fields['slug'] ) ) {
		$other = csqr_find_link( $fields['slug'] );
		if ( $other && $other !== (int) $link['id'] ) {
			return new WP_Error( 'csqr_slug_taken', __( 'That short address is already used.', 'anderson-qr-code-generator' ), array( 'status' => 409 ) );
		}
	}
	$id = csqr_save_link( $fields, (int) $link['id'] );
	if ( is_wp_error( $id ) ) {
		$id->add_data( array( 'status' => 400 ) );
		return $id;
	}
	return csqr_get_link( $id );
}

/**
 * DELETE /links/{id}.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string, mixed>|WP_Error
 */
function csqr_rest_delete( $request ) {
	$link = csqr_rest_link_or_error( $request['id'] );
	if ( is_wp_error( $link ) ) {
		return $link;
	}
	$force  = (bool) $request['force'];
	$result = $force ? wp_delete_post( (int) $link['id'], true ) : wp_trash_post( (int) $link['id'] );
	if ( ! $result ) {
		return new WP_Error( 'csqr_delete_failed', __( 'The short link couldn’t be removed.', 'anderson-qr-code-generator' ), array( 'status' => 500 ) );
	}
	return array(
		'deleted'  => true,
		'trashed'  => ! $force,
		'previous' => $link,
	);
}

/**
 * GET /links/{id}/stats.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string, mixed>|WP_Error
 */
function csqr_rest_stats( $request ) {
	$link = csqr_rest_link_or_error( $request['id'] );
	if ( is_wp_error( $link ) ) {
		return $link;
	}
	$daily = csqr_link_daily( (int) $link['id'], (int) $request['days'] );
	return array(
		'id'        => $link['id'],
		'total'     => $link['scans'],
		'last_scan' => $link['last_scan'] ? wp_date( 'c', $link['last_scan'] ) : null,
		'days'      => $daily,
		'in_period' => array_sum( $daily ),
		'by_place'  => csqr_link_place_counts( (int) $link['id'] ),
	);
}
