<?php
/**
 * SEO Plugins Integration (Yoast, WordPress XML Sitemap, etc.)
 *
 * @package    HidePostsPlugin
 */

namespace MartinCV\WHP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEO_Integration class.
 */
class SEO_Integration {
	use \MartinCV\WHP\Traits\Singleton;

	/**
	 * Enabled post types
	 *
	 * @var array
	 */
	private $enabled_post_types = array();

	/**
	 * Initialize class
	 *
	 * @return  void
	 */
	private function initialize() {
		$this->enabled_post_types = whp_plugin()->get_enabled_post_types();

		// WordPress core XML sitemap filters.
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'hide_from_wp_sitemap' ), 10, 2 );

		// Yoast SEO filters.
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this, 'hide_from_yoast_sitemap' ), 10, 1 );
		add_filter( 'wpseo_breadcrumb_links', array( $this, 'hide_from_yoast_breadcrumbs' ), 10, 1 );
		add_filter( 'wpseo_link_suggestions_indexables', array( $this, 'hide_from_yoast_link_suggestions' ), 10, 3 );
	}

	/**
	 * Hide posts from WordPress core XML sitemap
	 *
	 * @param array  $args      WP_Query arguments.
	 * @param string $post_type Post type name.
	 *
	 * @return array
	 */
	public function hide_from_wp_sitemap( $args, $post_type ) {
		if ( ! in_array( $post_type, $this->enabled_post_types, true ) ) {
			return $args;
		}

		$data_migrated = get_option( 'whp_data_migrated', false );
		$fallback      = ! $data_migrated;

		$hidden_ids = whp_plugin()->get_hidden_posts_ids( $post_type, 'xml_sitemap', $fallback );

		if ( ! empty( $hidden_ids ) ) {
			$args['post__not_in'] = ! empty( $args['post__not_in'] ) ? array_unique( array_merge( $hidden_ids, $args['post__not_in'] ) ) : $hidden_ids;
		}

		return $args;
	}

	/**
	 * Hide posts from Yoast SEO XML sitemap
	 *
	 * @param array $excluded_post_ids Post IDs already excluded from the sitemap.
	 *
	 * @return array
	 */
	public function hide_from_yoast_sitemap( $excluded_post_ids ) {
		if ( ! is_array( $excluded_post_ids ) ) {
			$excluded_post_ids = array();
		}

		$data_migrated = get_option( 'whp_data_migrated', false );
		$fallback      = ! $data_migrated;

		foreach ( $this->enabled_post_types as $post_type ) {
			$hidden_ids = whp_plugin()->get_hidden_posts_ids( $post_type, 'yoast_sitemap', $fallback );

			if ( ! empty( $hidden_ids ) ) {
				$excluded_post_ids = array_merge( $excluded_post_ids, array_map( 'intval', $hidden_ids ) );
			}
		}

		return array_unique( $excluded_post_ids );
	}

	/**
	 * Hide posts from Yoast SEO breadcrumbs
	 *
	 * @param array $links Breadcrumb links.
	 *
	 * @return array
	 */
	public function hide_from_yoast_breadcrumbs( $links ) {
		if ( empty( $links ) || ! is_array( $links ) ) {
			return $links;
		}

		$data_migrated = get_option( 'whp_data_migrated', false );
		$fallback      = ! $data_migrated;

		foreach ( $links as $key => $link ) {
			if ( isset( $link['id'] ) && $link['id'] > 0 ) {
				$post_type = get_post_type( $link['id'] );

				if ( in_array( $post_type, $this->enabled_post_types, true ) ) {
					$is_hidden = whp_plugin()->get_whp_meta( $link['id'], 'hide_on_yoast_breadcrumbs', $fallback );

					if ( $is_hidden ) {
						unset( $links[ $key ] );
					}
				}
			}
		}

		return $links;
	}

	/**
	 * Hide posts from Yoast SEO (Premium) internal link suggestions.
	 *
	 * @param array  $suggestions Indexable suggestion objects.
	 * @param int    $object_id   The object id for the current indexable.
	 * @param string $object_type The object type for the current indexable.
	 *
	 * @return array
	 */
	public function hide_from_yoast_link_suggestions( $suggestions, $object_id, $object_type ) {
		if ( 'post' !== $object_type || empty( $suggestions ) || ! is_array( $suggestions ) ) {
			return $suggestions;
		}

		$data_migrated = get_option( 'whp_data_migrated', false );
		$fallback      = ! $data_migrated;

		$hidden_ids = array();

		foreach ( $this->enabled_post_types as $post_type ) {
			$ids = whp_plugin()->get_hidden_posts_ids( $post_type, 'yoast_internal_links', $fallback );

			if ( ! empty( $ids ) ) {
				$hidden_ids = array_merge( $hidden_ids, $ids );
			}
		}

		if ( empty( $hidden_ids ) ) {
			return $suggestions;
		}

		$hidden_ids = array_map( 'intval', $hidden_ids );

		return array_values(
			array_filter(
				$suggestions,
				static function ( $suggestion ) use ( $hidden_ids ) {
					return ! in_array( (int) $suggestion->object_id, $hidden_ids, true );
				}
			)
		);
	}
}
