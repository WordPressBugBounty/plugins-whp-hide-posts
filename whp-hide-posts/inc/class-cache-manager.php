<?php
/**
 * Cache Manager for hiding posts cache invalidation
 *
 * @package    HidePostsPlugin
 */

namespace MartinCV\WHP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache_Manager class.
 */
class Cache_Manager {
	use \MartinCV\WHP\Traits\Singleton;

	/**
	 * Initialize class
	 *
	 * @return  void
	 */
	private function initialize() {
		// Clear cache on post status changes.
		add_action( 'delete_post', array( $this, 'clear_post_cache' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'clear_post_cache_by_id' ), 10, 1 );
		add_action( 'untrash_post', array( $this, 'clear_post_cache_by_id' ), 10, 1 );
		add_action( 'transition_post_status', array( $this, 'clear_post_cache_on_status_change' ), 10, 3 );
	}

	/**
	 * Clear cache when post is deleted
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function clear_post_cache( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->clear_cache_for_post_type( $post->post_type );
	}

	/**
	 * Clear cache when post is trashed or untrashed
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function clear_post_cache_by_id( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$this->clear_cache_for_post_type( $post->post_type );
	}

	/**
	 * Clear cache when post status changes
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 *
	 * @return void
	 */
	public function clear_post_cache_on_status_change( $new_status, $old_status, $post ) {
		// Only clear cache if status is changing to/from publish.
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			$this->clear_cache_for_post_type( $post->post_type );
		}
	}

	/**
	 * Clear all cache for a specific post type
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	private function clear_cache_for_post_type( $post_type ) {
		$enabled_post_types = whp_plugin()->get_enabled_post_types();

		if ( ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}

		$hide_types = array_keys( \MartinCV\WHP\Core\Constants::HIDDEN_POSTS_KEYS_LIST );

		foreach ( $hide_types as $hide_type ) {
			$key = 'whp_' . $post_type . '_' . $hide_type;

			wp_cache_delete( $key, 'whp' );
			delete_transient( $key );
		}
	}
}
