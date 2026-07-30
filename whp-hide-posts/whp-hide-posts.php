<?php
/**
 * Plugin Name: Hide Posts
 * Description: Hides posts on home page, categories, search, tags page, authors page, RSS Feed, XML sitemaps, Yoast SEO as well as hiding Woocommerce products
 * Author:      MartinCV
 * Author URI:  https://www.martincv.com
 * Version:     2.1.2
 * Text Domain: whp-hide-posts
 *
 * Hide Posts is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Hide Posts is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WordPress Hide Posts. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    HidePostsPlugin
 * @author     MartinCV
 * @since      0.0.1
 * @license    GPL-3.0+
 * @copyright  Copyright (c) 2022, MartinCV
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class
 */
final class HidePostsPlugin {
	/**
	 * Instance of the plugin
	 *
	 * @var HidePostsPlugin
	 */
	private static $instance;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version = '2.1.2';

	/**
	 * Instance of this plugin
	 *
	 * @return  HidePostsPlugin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof HidePostsPlugin ) ) {
			self::$instance = new HidePostsPlugin();
			self::$instance->constants();
			self::$instance->includes();

			add_action( 'plugins_loaded', array( self::$instance, 'run' ) );
			add_action( 'init', array( self::$instance, 'load_textdomain' ) );
		}

		return self::$instance;
	}

	/**
	 * 3rd party includes
	 *
	 * @return  void
	 */
	private function includes() {
		require_once WHP_PLUGIN_DIR . 'inc/core/autoloader.php';
		require_once WHP_PLUGIN_DIR . 'inc/core/helpers.php';
	}

	/**
	 * Define plugin constants
	 *
	 * @return  void
	 */
	private function constants() {
		// Plugin version.
		if ( ! defined( 'WHP_VERSION' ) ) {
			define( 'WHP_VERSION', $this->version );
		}

		// Plugin Folder Path.
		if ( ! defined( 'WHP_PLUGIN_DIR' ) ) {
			define( 'WHP_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'WHP_PLUGIN_URL' ) ) {
			define( 'WHP_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
		}

		// Plugin Root File.
		if ( ! defined( 'WHP_PLUGIN_FILE' ) ) {
			define( 'WHP_PLUGIN_FILE', __FILE__ );
		}
	}

	/**
	 * Initialize classes / objects here
	 *
	 * @return  void
	 */
	public function run() {
		\MartinCV\WHP\Zeen_Theme::get_instance();
		\MartinCV\WHP\Core\Database::get_instance()->create_tables();

		// Initialize metabox (needed for both admin and REST API/Gutenberg).
		\MartinCV\WHP\Admin\Post_Hide_Metabox::get_instance();

		// Initialize REST API for Gutenberg (custom table, no postmeta).
		\MartinCV\WHP\REST_API::get_instance();

		// Init classes if is Admin/Dashboard.
		if ( is_admin() ) {
			\MartinCV\WHP\Admin\Dashboard::get_instance();
			\MartinCV\WHP\Yoast_Duplicate_Post::get_instance();
		} else {
			\MartinCV\WHP\Post_Hide::get_instance();
		}

		// Initialize SEO integrations (works on both frontend and admin).
		\MartinCV\WHP\SEO_Integration::get_instance();

		// Initialize cache manager.
		\MartinCV\WHP\Cache_Manager::get_instance();
	}

	/**
	 * Register textdomain
	 *
	 * Hooked on `init` — WordPress 6.7+ warns when translations load earlier.
	 *
	 * @return  void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'whp-hide-posts', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
}

HidePostsPlugin::instance();

if ( ! function_exists( 'whp_plugin' ) ) {
	/**
	 * Instance of the Plugin class which holds helper functions
	 *
	 * @return \MartinCV\WHP\Core\Plugin
	 */
	function whp_plugin() {
		return \MartinCV\WHP\Core\Plugin::get_instance();
	}
}
