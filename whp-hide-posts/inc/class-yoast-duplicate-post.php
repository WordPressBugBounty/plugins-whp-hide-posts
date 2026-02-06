<?php
/**
 * Yoast duplicate post support
 *
 * @link https://en-gb.wordpress.org/plugins/duplicate-post/
 *
 * @package    HidePostsPlugin
 */

namespace MartinCV\WHP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zeen Theme class.
 */
class Yoast_Duplicate_Post {
	use \MartinCV\WHP\Traits\Singleton;

	/**
	 * Initialize class
	 *
	 * @return  void
	 */
	private function initialize() {
		add_action( 'dp_duplicate_post', array( $this, 'duplicate_post_copy_whp_flags' ), 10, 2 );
		add_action( 'dp_duplicate_page', array( $this, 'duplicate_post_copy_whp_flags' ), 10, 2 );
	}

	/**
	 * Copy whp flags after post is copied
	 *
	 * @param int     $new_id The new post ID.
     * @param WP_Post $post   The original post object.
	 *
	 * @return array
	 */
	public function duplicate_post_copy_whp_flags( $new_id, $post ) {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		$conditions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT `condition` FROM {$table_name} WHERE `post_id` = %d",
				$post->ID
			)
		);

		if ( $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to get conditions for post %d: %s', $post->ID, $wpdb->last_error ) );
			return;
		}

		if ( ! empty( $conditions ) ) {
			foreach ( $conditions as $condition ) {
				$result = $wpdb->insert(
					$table_name,
					array(
						'post_id'   => $new_id,
						'condition' => $condition,
					),
					array(
						'%d',
						'%s',
					)
				);

				if ( false === $result && $wpdb->last_error ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( sprintf( 'WHP: Failed to copy condition %s to post %d: %s', $condition, $new_id, $wpdb->last_error ) );
				}
			}
		}
	}
}
