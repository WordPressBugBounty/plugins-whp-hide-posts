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
		add_filter( 'wpseo_sitemap_exclude_post_type', array( $this, 'hide_from_yoast_sitemap' ), 10, 2 );
		add_filter( 'wpseo_breadcrumb_links', array( $this, 'hide_from_yoast_breadcrumbs' ), 10, 1 );
		add_filter( 'wpseo_link_count_post_types', array( $this, 'hide_from_yoast_internal_links' ), 10, 1 );
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
	 * @param bool   $excluded  Whether to exclude the post.
	 * @param string $post_type Post type name.
	 *
	 * @return bool
	 */
	public function hide_from_yoast_sitemap( $excluded, $post_type ) {
		if ( ! in_array( $post_type, $this->enabled_post_types, true ) ) {
			return $excluded;
		}

		global $post;

		if ( ! $post || ! isset( $post->ID ) ) {
			return $excluded;
		}

		$data_migrated = get_option( 'whp_data_migrated', false );
		$fallback      = ! $data_migrated;

		$is_hidden = whp_plugin()->get_whp_meta( $post->ID, 'hide_on_yoast_sitemap', $fallback );

		if ( $is_hidden ) {
			return true;
		}

		return $excluded;
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
	 * Hide posts from Yoast SEO internal link suggestions
	 *
	 * @param array $post_types Post types to consider for internal links.
	 *
	 * @return array
	 */
	public function hide_from_yoast_internal_links( $post_types ) {
		if ( empty( $post_types ) || ! is_array( $post_types ) ) {
			return $post_types;
		}

		// Add a filter to the WP_Query used by Yoast.
		add_filter(
			'posts_where',
			array( $this, 'yoast_internal_links_where_clause' ),
			10,
			2
		);

		return $post_types;
	}

	/**
	 * Modify WHERE clause for Yoast internal links query
	 *
	 * @param string    $where The WHERE clause.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return string
	 */
	public function yoast_internal_links_where_clause( $where, $query ) {
		global $wpdb;

		$data_migrated = get_option( 'whp_data_migrated', false );

		if ( ! $data_migrated ) {
			return $where;
		}

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		$hidden_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$table_name} WHERE `condition` = %s",
				'hide_on_yoast_internal_links'
			)
		);

		if ( $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to get hidden posts for Yoast internal links: %s', $wpdb->last_error ) );
			return $where;
		}

		if ( ! empty( $hidden_posts ) ) {
			$ids_placeholders = array_fill( 0, count( $hidden_posts ), '%d' );
			$ids_placeholders = implode( ', ', $ids_placeholders );

			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID NOT IN ( $ids_placeholders )", ...$hidden_posts );
		}

		// Remove this filter to avoid affecting other queries.
		remove_filter( 'posts_where', array( $this, 'yoast_internal_links_where_clause' ), 10 );

		return $where;
	}
}
