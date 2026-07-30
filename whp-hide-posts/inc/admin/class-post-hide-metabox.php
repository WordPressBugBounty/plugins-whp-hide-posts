<?php
/**
 * Hide Posts Metabox class
 *
 * @package    HidePostsPlugin
 */

namespace MartinCV\WHP\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post_Hide_Metabox class.
 */
class Post_Hide_Metabox {
	use \MartinCV\WHP\Traits\Singleton;

	/**
	 * Initialize class
	 *
	 * @return  void
	 */
	private function initialize() {
		$disable_hidden_on_column = get_option( 'whp_disable_hidden_on_column' );

		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );

		// Register save hooks for all enabled post types.
		$enabled_post_types = whp_plugin()->get_enabled_post_types();
		foreach ( $enabled_post_types as $post_type ) {
			add_action( "save_post_{$post_type}", array( $this, 'save_post_metabox' ), 10, 2 );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

		// Add bulk edit support.
		add_action( 'bulk_edit_custom_box', array( $this, 'bulk_edit_custom_box' ), 10, 2 );
		add_action( 'wp_ajax_whp_bulk_edit_save', array( $this, 'save_bulk_edit' ) );

		// Add quick edit support.
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );
		add_action( 'wp_ajax_whp_quick_edit_save', array( $this, 'save_quick_edit' ) );

		if ( ! $disable_hidden_on_column ) {
			foreach ( $enabled_post_types as $pt ) {
				add_action( 'manage_' . $pt . '_posts_custom_column', array( $this, 'render_post_columns' ), 10, 2 );
				add_filter( 'manage_' . $pt . '_posts_columns', array( $this, 'add_post_columns' ) );
			}
		}
	}

	/**
	 * Load admin assets
	 *
	 * @return  void
	 */
	public function load_admin_assets() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, whp_plugin()->get_enabled_post_types(), true ) ) {
			return;
		}

		// For Gutenberg, we need to check differently.
		if ( ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}

		// Classic Editor JS.
		wp_enqueue_script(
			'whp-admin-post-script',
			WHP_PLUGIN_URL . 'assets/admin/js/whp-script.js',
			array( 'jquery' ),
			WHP_VERSION,
			true
		);

		wp_localize_script(
			'whp-admin-post-script',
			'whpPlugin',
			array(
				'selectTaxonomyLabel' => __( 'Select Taxonomy', 'whp-hide-posts' ),
				'bulk_edit_nonce'     => wp_create_nonce( 'whp_bulk_edit_nonce' ),
				'quick_edit_nonce'    => wp_create_nonce( 'whp_quick_edit_nonce' ),
			)
		);

		// Gutenberg/Block Editor JS - check if block editor is being used.
		if ( $screen->is_block_editor ) {
			wp_enqueue_script(
				'whp-gutenberg-script',
				WHP_PLUGIN_URL . 'assets/admin/js/whp-gutenberg.js',
				array(
					'wp-plugins',
					'wp-edit-post',
					'wp-editor',
					'wp-element',
					'wp-components',
					'wp-data',
					'wp-i18n',
					'wp-api-fetch',
				),
				WHP_VERSION,
				true
			);

			// Get post object for context flags.
			global $post;
			$post_object = $post ? $post : get_post( get_the_ID() );

			wp_localize_script(
				'whp-gutenberg-script',
				'whpGutenberg',
				array(
					'isCustomPostType'     => $post_object ? whp_plugin()->is_custom_post_type( $post_object ) : false,
					'isWooCommerceProduct' => $post_object && whp_plugin()->is_woocommerce_active() && 'product' === $post_object->post_type,
					'isYoastActive'        => whp_plugin()->is_yoast_seo_active(),
				)
			);
		}

		wp_enqueue_style(
			'whp-admin-post-style',
			WHP_PLUGIN_URL . 'assets/admin/css/whp-style.css',
			array(),
			WHP_VERSION
		);
	}

	/**
	 * Add Post Hide metabox in sidebar top
	 * Only for Classic Editor - Gutenberg uses the sidebar panel instead
	 *
	 * @return void
	 */
	public function add_metabox() {
		$screen = get_current_screen();

		// Don't show classic metabox in Gutenberg - use the sidebar panel instead.
		if ( $screen && $screen->is_block_editor ) {
			return;
		}

		$post_types = whp_plugin()->get_enabled_post_types();

		add_meta_box(
			'hide_posts',
			__( 'Hide Posts', 'whp-hide-posts' ),
			array( $this, 'metabox_callback' ),
			$post_types,
			'side',
			'high'
		);
	}

	/**
	 * Add custom columns in posts list table
	 *
	 * @param   array $columns Columns shown on the posts list.
	 *
	 * @return  array
	 */
	public function add_post_columns( $columns ) {
		global $post;

		$columns['hidden_on'] = __( 'Hidden On', 'whp-hide-posts' );

		return $columns;
	}

	/**
	 * Show on which pages the post is hidden
	 *
	 * @param   string $column_name The column name shown in the posts list table.
	 * @param   int    $post_id The current post id.
	 *
	 * @return  void
	 */
	public function render_post_columns( $column_name, $post_id ) {
		if ( 'hidden_on' !== $column_name ) {
			return;
		}

		$data_migrated = get_option( 'whp_data_migrated', false );

		$fallback = ! $data_migrated;

		$whp_hide_on_frontpage        = whp_plugin()->get_whp_meta( $post_id, 'hide_on_frontpage', $fallback  );
		$whp_hide_on_categories       = whp_plugin()->get_whp_meta( $post_id, 'hide_on_categories', $fallback  );
		$whp_hide_on_search           = whp_plugin()->get_whp_meta( $post_id, 'hide_on_search', $fallback  );
		$whp_hide_on_tags             = whp_plugin()->get_whp_meta( $post_id, 'hide_on_tags', $fallback  );
		$whp_hide_on_authors          = whp_plugin()->get_whp_meta( $post_id, 'hide_on_authors', $fallback  );
		$whp_hide_in_rss_feed         = whp_plugin()->get_whp_meta( $post_id, 'hide_in_rss_feed', $fallback  );
		$whp_hide_on_blog_page        = whp_plugin()->get_whp_meta( $post_id, 'hide_on_blog_page', $fallback  );
		$whp_hide_on_date             = whp_plugin()->get_whp_meta( $post_id, 'hide_on_date', $fallback  );
		$whp_hide_on_post_navigation  = whp_plugin()->get_whp_meta( $post_id, 'hide_on_post_navigation', $fallback  );
		$whp_hide_on_recent_posts     = whp_plugin()->get_whp_meta( $post_id, 'hide_on_recent_posts', $fallback  );
		$whp_hide_on_cpt_archive          = whp_plugin()->get_whp_meta( $post_id, 'hide_on_cpt_archive', $fallback  );
		$whp_hide_on_archive              = whp_plugin()->get_whp_meta( $post_id, 'hide_on_archive', $fallback  );
		$whp_hide_on_rest_api             = whp_plugin()->get_whp_meta( $post_id, 'hide_on_rest_api', $fallback  );
		$whp_hide_on_single_post_page     = whp_plugin()->get_whp_meta( $post_id, 'hide_on_single_post_page', $fallback );
		$whp_hide_on_xml_sitemap          = whp_plugin()->get_whp_meta( $post_id, 'hide_on_xml_sitemap', $fallback );
		$whp_hide_on_yoast_sitemap        = whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_sitemap', $fallback );
		$whp_hide_on_yoast_breadcrumbs    = whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_breadcrumbs', $fallback );
		$whp_hide_on_yoast_internal_links = whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_internal_links', $fallback );

		if ( whp_plugin()->is_woocommerce_active() && whp_plugin()->is_woocommerce_product() ) {
			$whp_hide_on_store            = whp_plugin()->get_whp_meta( $post_id, 'hide_on_store', $fallback );
			$whp_hide_on_product_category = whp_plugin()->get_whp_meta( $post_id, 'hide_on_product_category', $fallback );
		}

		$whp_hide_on = '';

		if ( $whp_hide_on_frontpage ) {
			$whp_hide_on .= __( 'Front / Home page', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_archive ) {
			$whp_hide_on .= __( 'Archives', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_categories ) {
			$whp_hide_on .= __( 'Categories archive', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_search ) {
			$whp_hide_on .= __( 'Search page', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_tags ) {
			$whp_hide_on .= __( 'Tags archive', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_authors ) {
			$whp_hide_on .= __( 'Authors archive', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_in_rss_feed ) {
			$whp_hide_on .= __( 'RSS feed', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_blog_page ) {
			$whp_hide_on .= __( 'Blog page', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_date ) {
			$whp_hide_on .= __( 'Date archive', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_post_navigation ) {
			$whp_hide_on .= __( 'Single post navigation', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_recent_posts ) {
			$whp_hide_on .= __( 'Recent Posts Widget', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_cpt_archive ) {
			$whp_hide_on .= __( 'CPT Archive page', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_rest_api ) {
			$whp_hide_on .= __( 'REST API', 'whp-hide-posts' ) . ', ';
		}

		if ( isset( $whp_hide_on_store ) && $whp_hide_on_store ) {
			$whp_hide_on .= __( 'Store page', 'whp-hide-posts' ) . ', ';
		}

		if ( isset( $whp_hide_on_product_category ) && $whp_hide_on_product_category ) {
			$whp_hide_on .= __( 'Product category page', 'whp-hide-posts' ) . ', ';
		}

		if ( $whp_hide_on_single_post_page ) {
			$whp_hide_on .= __( 'Single Post Page', 'whp-hide-posts' ) . ', ';
		}

		if ( '' !== $whp_hide_on ) {
			$whp_hide_on = rtrim( $whp_hide_on, ', ' );

			echo esc_html( $whp_hide_on );
		}
	}

	/**
	 * Show the metabox template in sidebar top
	 *
	 * @param  WP_Post $post Current post object.
	 *
	 * @return void
	 */
	public function metabox_callback( $post ) {
		wp_nonce_field( 'wp_metabox_nonce', 'wp_metabox_nonce_value' );

		$post_id = $post->ID;

		$data_migrated = get_option( 'whp_data_migrated', false );

		$fallback = ! $data_migrated;

		// Read values using proper fallback logic based on migration status.
		// The fallback parameter ensures we check post meta only if data hasn't been migrated yet.
		$whp_hide_on_frontpage        = whp_plugin()->get_whp_meta( $post_id, 'hide_on_frontpage', $fallback );
		$whp_hide_on_categories       = whp_plugin()->get_whp_meta( $post_id, 'hide_on_categories', $fallback );
		$whp_hide_on_search           = whp_plugin()->get_whp_meta( $post_id, 'hide_on_search', $fallback );
		$whp_hide_on_tags             = whp_plugin()->get_whp_meta( $post_id, 'hide_on_tags', $fallback );
		$whp_hide_on_authors          = whp_plugin()->get_whp_meta( $post_id, 'hide_on_authors', $fallback );
		$whp_hide_in_rss_feed         = whp_plugin()->get_whp_meta( $post_id, 'hide_in_rss_feed', $fallback );
		$whp_hide_on_blog_page        = whp_plugin()->get_whp_meta( $post_id, 'hide_on_blog_page', $fallback );
		$whp_hide_on_date             = whp_plugin()->get_whp_meta( $post_id, 'hide_on_date', $fallback );
		$whp_hide_on_post_navigation  = whp_plugin()->get_whp_meta( $post_id, 'hide_on_post_navigation', $fallback );
		$whp_hide_on_recent_posts     = whp_plugin()->get_whp_meta( $post_id, 'hide_on_recent_posts', $fallback );
		$whp_hide_on_cpt_archive      = whp_plugin()->get_whp_meta( $post_id, 'hide_on_cpt_archive', $fallback );
		$whp_hide_on_archive          = whp_plugin()->get_whp_meta( $post_id, 'hide_on_archive', $fallback );
		$whp_hide_on_rest_api         = whp_plugin()->get_whp_meta( $post_id, 'hide_on_rest_api', $fallback );
		$whp_hide_on_single_post_page = whp_plugin()->get_whp_meta( $post_id, 'hide_on_single_post_page', $fallback );
		$whp_hide_on_xml_sitemap      = whp_plugin()->get_whp_meta( $post_id, 'hide_on_xml_sitemap', $fallback );
		$whp_hide_on_yoast_sitemap    = whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_sitemap', $fallback );
		$whp_hide_on_yoast_breadcrumbs = whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_breadcrumbs', $fallback );
		$whp_hide_on_yoast_internal_links = whp_plugin()->get_whp_meta( $post_id, 'hide_on_yoast_internal_links', $fallback );

		if ( whp_plugin()->is_woocommerce_active() && whp_plugin()->is_woocommerce_product() ) {
			$whp_hide_on_store            = whp_plugin()->get_whp_meta( $post_id, 'hide_on_store', $fallback );
			$whp_hide_on_product_category = whp_plugin()->get_whp_meta( $post_id, 'hide_on_product_category', $fallback );
		}

		$enabled_post_types = whp_plugin()->get_enabled_post_types();

		require_once WHP_PLUGIN_DIR . 'views/admin/template-admin-post-metabox.php';
	}

	/**
	 * Save post hide fields on post save/update (Classic Editor only)
	 * Gutenberg saves are handled by rest_api_save_handler()
	 *
	 * @param  int      $post_id Curretn post id.
	 * @param  \WP_POST $post    Current post object.
	 *
	 * @return mixed          Returns post id or void
	 */
	public function save_post_metabox( $post_id, $post ) {
		// If autosave, skip.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// If revision, skip.
		if ( 'revision' === $post->post_type ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		$enabled_post_types = whp_plugin()->get_enabled_post_types();

		if ( ! in_array( $post->post_type, $enabled_post_types, true ) ) {
			return $post_id;
		}

		// Verify nonce (Classic Editor only - Gutenberg uses REST API).
		if ( ! isset( $_POST['wp_metabox_nonce_value'] ) ) {
			return $post_id;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_metabox_nonce_value'] ) ), 'wp_metabox_nonce' ) ) {
			return $post_id;
		}

		// Data to be stored in the database, built from a whitelist of known conditions.
		$conditions = array(
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
		);

		if ( whp_plugin()->is_woocommerce_active() && whp_plugin()->is_woocommerce_product() ) {
			$conditions[] = 'hide_on_store';
			$conditions[] = 'hide_on_product_category';
		}

		$data = array();

		foreach ( $conditions as $condition ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
			$data[ $condition ] = ! empty( $_POST[ 'whp_' . $condition ] );
		}

		// Save meta and get changed conditions.
		$changed_conditions = $this->save_meta_data( $data, $post_id );

		// Only clear cache for conditions that actually changed (optimized).
		foreach ( $changed_conditions as $condition ) {
			whp_plugin()->clear_hidden_posts_cache( $post->post_type, $condition );
		}
	}

	/**
	 * Save post meta data
	 *
	 * @param  array $meta_data The meta data array.
	 * @param  int   $post_id   Current post id.
	 *
	 * @return array Array of conditions that were changed.
	 */
	private function save_meta_data( $meta_data, $post_id ) {
		$changed = array();

		foreach ( $meta_data as $key => $value ) {
			// Check current state in custom table.
			$exist = whp_plugin()->get_whp_meta( $post_id, $key, false );

			// Track if the value changed.
			if ( ( (bool) $exist ) !== ( (bool) $value ) ) {
				$changed[] = $key;
			}

			// Save ONLY to custom table (primary source of truth).
			if ( $value ) {
				whp_plugin()->add_whp_meta( $post_id, $key );
			} else {
				whp_plugin()->delete_whp_meta( $post_id, $key, false );
			}
		}

		return $changed;
	}

	/**
	 * Add custom box to bulk edit
	 *
	 * @param string $column_name Column name.
	 * @param string $post_type   Post type.
	 *
	 * @return void
	 */
	public function bulk_edit_custom_box( $column_name, $post_type ) {
		$enabled_post_types = whp_plugin()->get_enabled_post_types();

		if ( ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}

		if ( 'hidden_on' !== $column_name ) {
			return;
		}

		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<span class="title"><?php esc_html_e( 'Hide Posts', 'whp-hide-posts' ); ?></span>
					<select name="whp_bulk_hide_action">
						<option value=""><?php esc_html_e( '— No Change —', 'whp-hide-posts' ); ?></option>
						<option value="hide_on_frontpage"><?php esc_html_e( 'Hide on frontpage', 'whp-hide-posts' ); ?></option>
						<option value="hide_on_categories"><?php esc_html_e( 'Hide on categories', 'whp-hide-posts' ); ?></option>
						<option value="hide_on_search"><?php esc_html_e( 'Hide on search', 'whp-hide-posts' ); ?></option>
						<option value="remove_all_hiding"><?php esc_html_e( 'Remove all hiding', 'whp-hide-posts' ); ?></option>
					</select>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Add custom box to quick edit
	 *
	 * @param string $column_name Column name.
	 * @param string $post_type   Post type.
	 *
	 * @return void
	 */
	public function quick_edit_custom_box( $column_name, $post_type ) {
		$enabled_post_types = whp_plugin()->get_enabled_post_types();

		if ( ! in_array( $post_type, $enabled_post_types, true ) ) {
			return;
		}

		if ( 'hidden_on' !== $column_name ) {
			return;
		}

		?>
		<fieldset class="inline-edit-col-right inline-edit-whp">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Hide Options', 'whp-hide-posts' ); ?></span>
					<label>
						<input type="checkbox" name="whp_hide_on_frontpage" value="1" />
						<?php esc_html_e( 'Hide on frontpage', 'whp-hide-posts' ); ?>
					</label>
					<label>
						<input type="checkbox" name="whp_hide_on_categories" value="1" />
						<?php esc_html_e( 'Hide on categories', 'whp-hide-posts' ); ?>
					</label>
					<label>
						<input type="checkbox" name="whp_hide_on_search" value="1" />
						<?php esc_html_e( 'Hide on search', 'whp-hide-posts' ); ?>
					</label>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save bulk edit changes via AJAX
	 *
	 * @return void
	 */
	public function save_bulk_edit() {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'whp_bulk_edit_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'whp-hide-posts' ) ) );
		}

		// Check capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'whp-hide-posts' ) ) );
		}

		// Get post IDs and action.
		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'intval', (array) $_POST['post_ids'] ) : array();
		$action   = isset( $_POST['hide_action'] ) ? sanitize_text_field( wp_unslash( $_POST['hide_action'] ) ) : '';

		if ( empty( $post_ids ) || empty( $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'whp-hide-posts' ) ) );
		}

		// Validate action against whitelist.
		$allowed_actions = array(
			'hide_on_frontpage',
			'hide_on_categories',
			'hide_on_search',
			'remove_all_hiding',
		);

		if ( ! in_array( $action, $allowed_actions, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid action', 'whp-hide-posts' ) ) );
		}

		// Process each post.
		$updated = 0;
		foreach ( $post_ids as $post_id ) {
			// Verify user can edit this post.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			if ( 'remove_all_hiding' === $action ) {
				// Remove all hide flags for this post.
				$this->remove_all_hide_flags( $post_id );
			} else {
				// Add specific hide flag.
				$hide_key = str_replace( 'whp_', '', $action );
				whp_plugin()->add_whp_meta( $post_id, $hide_key );
			}

			$updated++;
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of posts updated */
					_n( '%d post updated', '%d posts updated', $updated, 'whp-hide-posts' ),
					$updated
				),
			)
		);
	}

	/**
	 * Save quick edit changes via AJAX
	 *
	 * @return void
	 */
	public function save_quick_edit() {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'whp_quick_edit_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'whp-hide-posts' ) ) );
		}

		// Get post ID.
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'whp-hide-posts' ) ) );
		}

		// Check capability.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'whp-hide-posts' ) ) );
		}

		// Get hide options.
		$hide_options = array(
			'hide_on_frontpage'  => ! empty( $_POST['whp_hide_on_frontpage'] ),
			'hide_on_categories' => ! empty( $_POST['whp_hide_on_categories'] ),
			'hide_on_search'     => ! empty( $_POST['whp_hide_on_search'] ),
		);

		$changed_conditions = array();

		// Update each hide option and track changes.
		foreach ( $hide_options as $key => $value ) {
			$exist = whp_plugin()->get_whp_meta( $post_id, $key, false );

			if ( $exist && ! $value ) {
				// Remove hide flag.
				whp_plugin()->delete_whp_meta( $post_id, $key, true );
				$changed_conditions[] = $key;
			} elseif ( ! $exist && $value ) {
				// Add hide flag.
				whp_plugin()->add_whp_meta( $post_id, $key );
				$changed_conditions[] = $key;
			}
		}

		// Clear cache only for changed conditions (optimized).
		$post      = get_post( $post_id );
		$post_type = $post ? $post->post_type : 'post';

		foreach ( $changed_conditions as $condition ) {
			whp_plugin()->clear_hidden_posts_cache( $post_type, $condition );
		}

		wp_send_json_success( array( 'message' => __( 'Post updated successfully', 'whp-hide-posts' ) ) );
	}

	/**
	 * Remove all hide flags from a post
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	private function remove_all_hide_flags( $post_id ) {
		global $wpdb;

		$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );

		// Delete all hide flags for this post.
		$result = $wpdb->delete(
			$table_name,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		if ( false === $result && $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to remove hide flags for post %d: %s', $post_id, $wpdb->last_error ) );
		}

		// Also remove from postmeta (legacy).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$post_id,
				$wpdb->esc_like( '_whp_hide_' ) . '%'
			)
		);

		if ( $wpdb->last_error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'WHP: Failed to remove legacy meta for post %d: %s', $post_id, $wpdb->last_error ) );
		}

		// Clear all caches for this post type since we're removing ALL conditions.
		$post      = get_post( $post_id );
		$post_type = $post ? $post->post_type : 'post';

		// When removing all, we must clear all condition caches.
		$hide_types = array_keys( \MartinCV\WHP\Core\Constants::HIDDEN_POSTS_KEYS_LIST );
		foreach ( $hide_types as $hide_type ) {
			$cache_key = 'whp_' . $post_type . '_' . $hide_type;
			wp_cache_delete( $cache_key, 'whp' );
			delete_transient( $cache_key );
		}
	}
}
