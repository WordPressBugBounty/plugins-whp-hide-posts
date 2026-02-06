<?php
/**
 * Run on pluigin uninstall
 *
 * @package    HidePostsPlugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'whp_enabled_post_types' );
delete_option( 'whp_db_version' );
delete_option( 'whp_data_migrated' );
delete_option( 'whp_data_migrated_notice_closed' );
delete_option( 'whp_disable_hidden_on_column' );

global $wpdb;
$table_name = esc_sql( $wpdb->prefix . 'whp_posts_visibility' );
$wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE '_whp_hide_on_%'" );
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
