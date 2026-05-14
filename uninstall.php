<?php
/**
 * Uninstaller for WEBP Support by thisismyurl.com.
 *
 * @package TIMU_WEBP_Support
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wp_filesystem;
if ( empty( $wp_filesystem ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
}

$upload_dir = wp_upload_dir();
$backup_dir = $upload_dir['basedir'] . '/webp-backups/';
$options    = get_option( 'timu_webp_support_options', array() );
if ( ! empty( $options['delete_backups_uninstall'] ) && $wp_filesystem && $wp_filesystem->exists( $backup_dir ) ) {
	$wp_filesystem->delete( $backup_dir, true );
}

delete_metadata( 'post', 0, '_webp_original_path', '', true );
delete_metadata( 'post', 0, '_webp_savings', '', true );
delete_option( 'timu_webp_support_options' );
wp_cache_flush();