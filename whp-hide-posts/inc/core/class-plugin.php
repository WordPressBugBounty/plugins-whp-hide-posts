<?php
/**
 * Plugin class
 *
 * @since 1.0.0
 *
 * @package    HidePostsPlugin
 */

namespace MartinCV\WHP\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin class
 */
class Plugin {
	use \MartinCV\WHP\Traits\Singleton;

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return  bool
	 */
	public function is_woocommerce_active() {
		$plugin = 'woocommerce/woocommerce.php';

		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
	}

	/**
	 * Check if current post is of type product.
	 *
	 * @return  bool
	 */
	public function is_woocommerce_product() {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return 'product' === $post->post_type;
	}

	/**
	 * Check if Yoast SEO is active.
	 *
	 * @return  bool
	 */
	public function is_yoast_seo_active() {
		// Check for Yoast SEO or Yoast SEO Premium.
		$yoast_free    = 'wordpress-seo/wp-seo.php';
		$yoast_premium = 'wordpress-seo-premium/wp-seo-premium.php';

		$active_plugins = (array) get_option( 'active_plugins', array() );

		return in_array( $yoast_free, $active_plugins, true ) || in_array( $yoast_premium, $active_plugins, true );
	}

	/**
	 * Get all IDs for posts that are hidden
	 *
	 * @param   string $post_type  The post type to be filtered.
	 * @param   string $from Filter for the posts hidden on specific page.
	 * @param   boolean $fallback Should it fallback to meta table.
	 *
	 * @return  array
	 */
	public function get_hidden_posts_ids( $post_type = 'post', $from = 'all', $fallback = false ) {
		$cache_key = 'whp_' . $post_type . '_' . $from;

		$found        = false;
		$hidden_posts = wp_cache_get( $cache_key, 'whp', false, $found );

		if ( $found && is_array( $hidden_posts ) ) {
			return $hidden_posts;
		}

		$hidden_posts = get_transient( $cache_key );

		if ( is_array( $hidden_posts ) ) {
			return $hidden_posts;
		}

		$key = Constants::HIDDEN_POSTS_KEYS_LIST[ $from ] ?? false;

		if ( ! $key ) {
			return array();
		}

		$key = str_replace( '_whp_', '', $key );

		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		if ( 'all' === $key ) {
			$like_pattern = $wpdb->esc_like( 'hide_' ) . '%';
			$sql          = $wpdb->prepare( "SELECT DISTINCT post_id FROM {$table_name} WHERE `condition` LIKE %s AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s)", $like_pattern, $post_type );
		} else {
			$sql = $wpdb->prepare( "SELECT DISTINCT post_id FROM {$table_name} WHERE `condition` = %s AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s)", $key, $post_type );
		}

		$hidden_posts = $wpdb->get_col( $sql );

		if ( $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to get hidden posts: %s', $wpdb->last_error ) );
			return array();
		}

		if ( empty( $hidden_posts ) && $fallback ) {
			$key = '_whp_' . $key;

			if ( '_whp_all' === $key ) {
				$like_pattern = $wpdb->esc_like( '_whp_hide_' ) . '%';
				$sql          = $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s)", $like_pattern, $post_type );
			} else {
				$sql = $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s)", $key, $post_type );
			}

			$hidden_posts = $wpdb->get_col( $sql );
		}

		wp_cache_set( $cache_key, $hidden_posts, 'whp' );

		set_transient( $cache_key, $hidden_posts, WEEK_IN_SECONDS );

		return $hidden_posts;
	}

	/**
	 * Fetch enabled posts types for this plugin
	 *
	 * @return  array
	 */
	public function get_enabled_post_types() {
		$key = 'whp_pt';

		$post_types = wp_cache_get( $key, 'whp' );

		if ( $post_types ) {
			return $post_types;
		}

		$post_types         = array( 'post' );
		$enabled_post_types = get_option( 'whp_enabled_post_types', array() );

		if ( is_array( $enabled_post_types ) ) {
			$post_types = array_merge( $post_types, $enabled_post_types );
		}

		wp_cache_set( $key, $post_types, 'whp' );

		return $post_types;
	}

	/**
	 * Check if post type for post is a CPT.
	 *
	 * @param   \WP_Post $post  WordPress Post Object.
	 *
	 * @return  bool
	 */
	public function is_custom_post_type( $post = null ) {
		$all_custom_post_types = get_post_types( array( '_builtin' => false ) );

		if ( empty( $all_custom_post_types ) ) {
			return false;
		}

		$custom_types      = array_keys( $all_custom_post_types );
		$current_post_type = get_post_type( $post );

		if ( ! $current_post_type ) {
			return false;
		}

		return in_array( $current_post_type, $custom_types, true );
	}

	/**
	 * Check if we should use the custom table (true) or need to fall back to post meta (false)
	 * Returns true if:
	 * - Data has been migrated (whp_data_migrated = true), OR
	 * - Fresh installation (no legacy postmeta exists)
	 *
	 * @return boolean
	 */
	public function should_use_custom_table() {
		$data_migrated = get_option( 'whp_data_migrated', false );

		// If explicitly migrated, use custom table.
		if ( $data_migrated ) {
			return true;
		}

		// Check if there's any legacy postmeta - if not, it's a fresh install.
		global $wpdb;
		$has_legacy_data = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_whp_hide%' LIMIT 1"
		);

		// If no legacy data exists, it's a fresh install - use custom table.
		// If legacy data exists but not migrated, use fallback.
		return ! $has_legacy_data;
	}

	/**
	 * Check if post is hidden in the custom table
	 *
	 * @param  int  $post_id  The post id.
	 * @param  string  $key      The key name.
	 * @param  boolean $fallback Should it check in the post meta table.
	 *
	 * @return boolean
	 */
	public function get_whp_meta( $post_id, $key, $fallback = false ) {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		$hidden_post = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND `condition` = %s",
				$post_id,
				$key
			)
		);

		if ( $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to check post meta for post %d: %s', $post_id, $wpdb->last_error ) );
			return false;
		}

		if ( $hidden_post ) {
			return true;
		}

		if ( $fallback ) {
			return get_post_meta( $post_id, '_whp_' . $key, true );
		}

		return false;
	}

	/**
	 * Set post for hiding
	 *
	 * @param  int    $post_id  The post id.
	 * @param  string $key      The key name.
	 *
	 * @return boolean
	 */
	public function add_whp_meta( $post_id, $key ) {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		// Check if it already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND `condition` = %s",
				$post_id,
				$key
			)
		);

		if ( $exists ) {
			// Already exists, no need to insert.
			return true;
		}

		$result = $wpdb->insert(
			$table_name,
			array(
				'post_id'   => $post_id,
				'condition' => $key,
			),
			array(
				'%d',
				'%s',
			)
		);

		if ( false === $result && $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to add meta for post %d: %s', $post_id, $wpdb->last_error ) );
			return false;
		}

		return true;
	}

	/**
	 * Remove post from hiding
	 *
	 * @param  int     $post_id         The post id.
	 * @param  string  $key             The key name.
	 * @param  boolean $delete_postmeta Whether to also delete legacy postmeta.
	 *
	 * @return boolean
	 */
	public function delete_whp_meta( $post_id, $key, $delete_postmeta = false ) {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		$result = $wpdb->delete(
			$table_name,
			array(
				'post_id'   => $post_id,
				'condition' => $key,
			),
			array(
				'%d',
				'%s',
			)
		);

		if ( false === $result && $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to delete meta for post %d: %s', $post_id, $wpdb->last_error ) );
			return false;
		}

		if ( $delete_postmeta ) {
			delete_post_meta( $post_id, '_whp_' . $key );
		}

		return true;
	}

	/**
	 * Clear all cache entries for a post type + condition combination.
	 *
	 * Read-side caches are keyed by the short "from" key (e.g. `whp_post_yoast_internal_links`),
	 * so both the raw condition key and its mapped short key are cleared, plus the `_all` key.
	 *
	 * @param  string $post_type The post type.
	 * @param  string $condition The condition name (e.g. `hide_on_frontpage`).
	 *
	 * @return void
	 */
	public function clear_hidden_posts_cache( $post_type, $condition ) {
		$keys = array(
			'whp_' . $post_type . '_' . $condition,
			'whp_' . $post_type . '_all',
		);

		$from = array_search( '_whp_' . $condition, Constants::HIDDEN_POSTS_KEYS_LIST, true );

		if ( $from ) {
			$keys[] = 'whp_' . $post_type . '_' . $from;
		}

		foreach ( array_unique( $keys ) as $key ) {
			wp_cache_delete( $key, 'whp' );
			delete_transient( $key );
		}
	}
}
