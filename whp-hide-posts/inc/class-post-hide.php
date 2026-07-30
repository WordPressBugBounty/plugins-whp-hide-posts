<?php
/**
 * Logic for hiding posts happens here.
 *
 * @package    HidePostsPlugin
 */

namespace MartinCV\WHP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post_Hide class.
 */
class Post_Hide {
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

		add_action( 'pre_get_posts', array( $this, 'exclude_posts' ), 99 );
		add_action( 'parse_query', array( $this, 'parse_query' ) );
		add_filter( 'get_next_post_where', array( $this, 'hide_from_post_navigation' ), 10, 1 );
		add_filter( 'get_previous_post_where', array( $this, 'hide_from_post_navigation' ), 10, 1 );
		add_filter( 'widget_posts_args', array( $this, 'hide_from_recent_post_widget' ), 10, 1 );
		add_filter( 'query_loop_block_query_vars', array( $this, 'hide_from_query_block' ), 10, 2 );
		add_filter( 'render_block_core/latest-posts', array( $this, 'hide_from_latest_posts_block' ), 10, 2 );

		foreach ( $this->enabled_post_types as $pt ) {
			if ( 'product' !== $pt ) {
				add_filter( "rest_{$pt}_query", array( $this, 'hide_from_rest_api' ), 10, 2 );
			} else {
				add_filter( 'woocommerce_rest_product_object_query', array( $this, 'hide_from_rest_api' ), 10, 2 );
				add_filter( 'woocommerce_rest_product_query', array( $this, 'hide_from_rest_api' ), 10, 2 );
			}
		}
	}

	/**
	 * Hide from rest api
	 *
	 * @param  array           $args    The query args.
	 * @param  WP_REST_Request $request The request.
	 *
	 * @return array
	 */
	public function hide_from_rest_api( $args, $request ) {
		// Don't hide posts in admin context (edit mode).
		if ( 'edit' === $request->get_param( 'context' ) ) {
			return $args;
		}

		if ( ! in_array( $args['post_type'], $this->enabled_post_types, true ) ) {
			return $args;
		}

		$data_migrated = get_option( 'whp_data_migrated', false );

		$fallback = ! $data_migrated;

		$hidden_ids = whp_plugin()->get_hidden_posts_ids( $args['post_type'], 'rest_api', $fallback );

		if ( ! empty( $hidden_ids ) ) {
			$args['post__not_in'] = ! empty( $args['post__not_in'] ) ? array_unique( array_merge( $hidden_ids, $args['post__not_in'] ) ) : $hidden_ids;
		}

		return $args;
	}

	/**
	 * A workaround for the is_front_page() check inside pre_get_posts and later hooks.
	 *
	 * Based on the patch from @mattonomics in #27015
	 *
	 * @param \WP_Query $query The WordPress query object.
	 *
	 * @see http://wordpress.stackexchange.com/a/188320/26350
	 */
	public function parse_query( $query ) {
		if ( is_null( $query->queried_object ) && $query->get( 'page_id' ) ) {
			$query->queried_object    = get_post( $query->get( 'page_id' ) );
			$query->queried_object_id = (int) $query->get( 'page_id' );
		}
	}

	/**
	 * Exclude posts with enabled hide options
	 *
	 * @param  WP_Query $query Current query object.
	 *
	 * @return void
	 */
	public function exclude_posts( $query ) {
		global $wpdb;

		$q_post_type = $query->get( 'post_type' );

		if ( ( ! is_admin() || ( is_admin() && wp_doing_ajax() ) ) &&
			(
				empty( $query->get( 'post_type' ) ) ||
				( ! is_array( $q_post_type ) && in_array( $q_post_type, $this->enabled_post_types, true ) ) ||
				( is_array( $q_post_type ) && ! empty( array_intersect( $q_post_type, $this->enabled_post_types ) ) )
			)
		) {
			$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );
			// Handle single post pages
			if ( is_singular( $q_post_type ) && ! $query->is_main_query() ) {
				$hidden_posts = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT post_id FROM {$table_name} WHERE `condition` = %s",
						'hide_on_single_post_page'
					)
				);

				if ( $wpdb->last_error ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'WHP: Failed to get hidden posts on single page: %s', $wpdb->last_error ) );
					return;
				}

				if ( ! empty( $hidden_posts ) ) {
					$existing_posts = $query->get( 'post__not_in' );
					if ( ! is_array( $existing_posts ) ) {
						$existing_posts = array();
					}
					$query->set( 'post__not_in', array_unique( array_merge( $existing_posts, $hidden_posts ) ) );
				} else {
					// Fallback to meta.
					$existing_meta_query = $query->get( 'meta_query', array() );
					$existing_meta_query[] = array(
						'key'     => '_whp_hide_on_single_post_page',
						'compare' => 'NOT EXISTS',
					);
					$query->set( 'meta_query', $existing_meta_query );
				}
			}

			if ( ( is_front_page() && is_home() ) || is_front_page() ) {
				$this->exclude_by_condition( $query, 'hide_on_frontpage', '_whp_hide_on_frontpage' );
			} elseif ( is_home() ) {
				$this->exclude_by_condition( $query, 'hide_on_blog_page', '_whp_hide_on_blog_page' );
			}

			if ( is_post_type_archive( $q_post_type ) ) {
				$this->exclude_by_condition( $query, 'hide_on_cpt_archive', '_whp_hide_on_cpt_archive' );
			} elseif ( is_category() ) {
				$this->exclude_by_condition( $query, 'hide_on_categories', '_whp_hide_on_categories' );
			} elseif ( is_tag() ) {
				$this->exclude_by_condition( $query, 'hide_on_tags', '_whp_hide_on_tags' );
			} elseif ( is_author() ) {
				$this->exclude_by_condition( $query, 'hide_on_authors', '_whp_hide_on_authors' );
			} elseif ( is_date() ) {
				$this->exclude_by_condition( $query, 'hide_on_date', '_whp_hide_on_date' );
			} elseif ( is_search() ) {
				$this->exclude_by_condition( $query, 'hide_on_search', '_whp_hide_on_search' );
			} elseif ( is_feed() ) {
				$this->exclude_by_condition( $query, 'hide_in_rss_feed', '_whp_hide_in_rss_feed' );
			} elseif ( whp_plugin()->is_woocommerce_active() && is_shop() ) {
				$this->exclude_by_condition( $query, 'hide_on_store', '_whp_hide_on_store' );
			} elseif ( whp_plugin()->is_woocommerce_active() && is_product_category() ) {
				$this->exclude_by_condition( $query, 'hide_on_product_category', '_whp_hide_on_product_category' );
			} elseif ( is_archive() ) {
				$this->exclude_by_condition( $query, 'hide_on_archive', '_whp_hide_on_archive' );
			}
		}
	}

	/**
	 * Exclude posts by a condition using the custom table, with a fallback to post meta.
	 *
	 * @param WP_Query $query The query object.
	 * @param string   $condition The condition to check in the custom table.
	 * @param string   $meta_key The meta key to check for fallback.
	 */
	private function exclude_by_condition( &$query, $condition, $meta_key ) {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		$hidden_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$table_name} WHERE `condition` = %s",
				$condition
			)
		);

		if ( $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to exclude by condition %s: %s', $condition, $wpdb->last_error ) );
			return;
		}

		if ( ! empty( $hidden_posts ) ) {
			$existing_posts = $query->get( 'post__not_in' );
			if ( ! is_array( $existing_posts ) ) {
				$existing_posts = array();
			}
			$query->set( 'post__not_in', array_unique( array_merge( $existing_posts, $hidden_posts ) ) );
		} else {
			$data_migrated = get_option( 'whp_data_migrated', false );

			if ( ! $data_migrated ) {
				// Fallback to meta.
				$existing_meta_query   = $query->get( 'meta_query', array() );
				$existing_meta_query[] = array(
					'key'     => $meta_key,
					'compare' => 'NOT EXISTS',
				);
				$query->set( 'meta_query', $existing_meta_query );
			}
		}
	}

	/**
	 * Hide post from post navigation
	 *
	 * @param   string $where The WHERE part of the query.
	 *
	 * @return  string
	 */
	public function hide_from_post_navigation( $where ) {
		$data_migrated = get_option( 'whp_data_migrated', false );

		$fallback = ! $data_migrated;

		$hidden_on_post_navigation = whp_plugin()->get_hidden_posts_ids( 'post', 'post_navigation', $fallback );

		if ( empty( $hidden_on_post_navigation ) ) {
			return $where;
		}

		$ids_placeholders = array_fill( 0, count( $hidden_on_post_navigation ), '%d' );
		$ids_placeholders = implode( ', ', $ids_placeholders );

		global $wpdb;

		$where .= $wpdb->prepare( " AND ID NOT IN ( $ids_placeholders )", ...$hidden_on_post_navigation );

		return $where;
	}

	/**
	 * Hide posts from default WordPress recent post widget
	 *
	 * @param   array $query_args WP_Query arguments.
	 *
	 * @return  array
	 */
	public function hide_from_recent_post_widget( $query_args ) {
		$data_migrated = get_option( 'whp_data_migrated', false );

		$fallback = ! $data_migrated;

		$hidden_on_recent_posts = whp_plugin()->get_hidden_posts_ids( 'post', 'recent_posts', $fallback );

		if ( empty( $hidden_on_recent_posts ) ) {
			return $query_args;
		}

		$query_args['post__not_in'] = $hidden_on_recent_posts;

		return $query_args;
	}

	/**
	 * Hide posts from Gutenberg Query Loop block
	 *
	 * @param   array $query Query arguments.
	 * @param   array $block Block instance.
	 *
	 * @return  array
	 */
	public function hide_from_query_block( $query, $block ) {
		if ( ! isset( $query['post_type'] ) ) {
			return $query;
		}

		$post_type = is_array( $query['post_type'] ) ? $query['post_type'][0] : $query['post_type'];

		if ( ! in_array( $post_type, $this->enabled_post_types, true ) ) {
			return $query;
		}

		$data_migrated = get_option( 'whp_data_migrated', false );
		$fallback      = ! $data_migrated;

		// Determine which hide context to use based on current page.
		$hide_context = 'all';
		if ( is_front_page() ) {
			$hide_context = 'front_page';
		} elseif ( is_home() ) {
			$hide_context = 'blog_page';
		} elseif ( is_category() ) {
			$hide_context = 'categories';
		} elseif ( is_tag() ) {
			$hide_context = 'tags';
		} elseif ( is_author() ) {
			$hide_context = 'authors';
		} elseif ( is_search() ) {
			$hide_context = 'search';
		} elseif ( is_date() ) {
			$hide_context = 'date';
		} elseif ( is_archive() ) {
			$hide_context = 'cpt_archive';
		}

		$hidden_ids = whp_plugin()->get_hidden_posts_ids( $post_type, $hide_context, $fallback );

		if ( ! empty( $hidden_ids ) ) {
			$query['post__not_in'] = ! empty( $query['post__not_in'] ) ? array_unique( array_merge( $hidden_ids, $query['post__not_in'] ) ) : $hidden_ids;
		}

		return $query;
	}

	/**
	 * Hide posts from Gutenberg Latest Posts block
	 * This block is server-side rendered, so we can't filter via REST API
	 * We need to use the pre_render_block filter instead
	 *
	 * @param   string $block_content Block HTML content.
	 * @param   array  $block         Block instance.
	 *
	 * @return  string
	 */
	public function hide_from_latest_posts_block( $block_content, $block ) {
		// The Latest Posts block uses WP_Query internally during server-side rendering.
		// Our pre_get_posts filter at priority 99 should catch it automatically.
		// However, if block is rendered via REST, we need the rest_post_query filter.
		// Since we already hook rest_{post_type}_query, hidden posts are filtered.
		// So we just return the block content as-is - filtering happens upstream.
		return $block_content;
	}
}
