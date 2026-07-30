<?php
/**
 * REST API endpoints for Gutenberg integration
 * Reads/writes data from/to custom table ONLY
 *
 * @package    HidePostsPlugin
 */

namespace MartinCV\WHP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST_API class.
 */
class REST_API {
	use \MartinCV\WHP\Traits\Singleton;

	/**
	 * Initialize class
	 *
	 * @return  void
	 */
	private function initialize() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register custom REST API routes for Gutenberg
	 * Data is stored in custom table, NOT in wp_postmeta
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET endpoint to read hide settings from custom table.
		register_rest_route(
			'whp/v1',
			'/hide-settings/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_hide_settings' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_post', $request['id'] );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// POST endpoint to save hide settings to custom table.
		register_rest_route(
			'whp/v1',
			'/hide-settings/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_hide_settings' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_post', $request['id'] );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * REST API callback to get hide settings from custom table
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_hide_settings( $request ) {
		$post_id = $request['id'];

		$settings = array(
			'hide_on_frontpage'            => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_frontpage', false ),
			'hide_on_categories'           => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_categories', false ),
			'hide_on_search'               => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_search', false ),
			'hide_on_tags'                 => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_tags', false ),
			'hide_on_authors'              => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_authors', false ),
			'hide_in_rss_feed'             => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_in_rss_feed', false ),
			'hide_on_blog_page'            => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_blog_page', false ),
			'hide_on_date'                 => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_date', false ),
			'hide_on_post_navigation'      => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_post_navigation', false ),
			'hide_on_recent_posts'         => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_recent_posts', false ),
			'hide_on_archive'              => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_archive', false ),
			'hide_on_cpt_archive'          => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_cpt_archive', false ),
			'hide_on_rest_api'             => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_rest_api', false ),
			'hide_on_single_post_page'     => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_single_post_page', false ),
			'hide_on_xml_sitemap'          => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_xml_sitemap', false ),
			'hide_on_yoast_sitemap'        => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_sitemap', false ),
			'hide_on_yoast_breadcrumbs'    => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_breadcrumbs', false ),
			'hide_on_yoast_internal_links' => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_internal_links', false ),
			'hide_on_store'                => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_store', false ),
			'hide_on_product_category'     => (bool) whp_plugin()->get_whp_meta( $post_id, 'hide_on_product_category', false ),
		);

		return rest_ensure_response( $settings );
	}

	/**
	 * REST API callback to save hide settings to custom table
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function save_hide_settings( $request ) {
		$post_id = $request['id'];
		$params  = $request->get_json_params();

		if ( empty( $params ) ) {
			return new \WP_Error( 'no_data', 'No data provided', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
		}

		$allowed_conditions = array(
			'hide_on_frontpage',
			'hide_on_categories',
			'hide_on_search',
			'hide_on_tags',
			'hide_on_authors',
			'hide_in_rss_feed',
			'hide_on_blog_page',
			'hide_on_date',
			'hide_on_post_navigation',
			'hide_on_recent_posts',
			'hide_on_archive',
			'hide_on_cpt_archive',
			'hide_on_rest_api',
			'hide_on_single_post_page',
			'hide_on_xml_sitemap',
			'hide_on_yoast_sitemap',
			'hide_on_yoast_breadcrumbs',
			'hide_on_yoast_internal_links',
			'hide_on_store',
			'hide_on_product_category',
		);

		// Save each setting directly to custom table (NOT postmeta).
		foreach ( $params as $key => $value ) {
			if ( ! in_array( $key, $allowed_conditions, true ) ) {
				continue;
			}

			$value = rest_sanitize_boolean( $value );

			if ( $value ) {
				whp_plugin()->add_whp_meta( $post_id, $key );
			} else {
				whp_plugin()->delete_whp_meta( $post_id, $key, false );
			}

			whp_plugin()->clear_hidden_posts_cache( $post->post_type, $key );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
