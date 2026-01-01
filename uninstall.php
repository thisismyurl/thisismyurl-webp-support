<?php
/**
 * TIMU Plugin Uninstaller
 * This script runs automatically when a user clicks 'Delete' in the WordPress Plugins menu.
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Determine the plugin slug based on the directory being uninstalled.
 * This allows you to use the same file across your entire suite.
 */
$plugin_slug = dirname( WP_UNINSTALL_PLUGIN );

// 1. Delete the primary plugin options
delete_option( $plugin_slug . '_options' );

// 2. Delete licensing and status transients
delete_transient( $plugin_slug . '_license_status' );
delete_transient( $plugin_slug . '_license_msg' );

// 3. Clear the shared tools cache to ensure fresh data for remaining TIMU plugins
delete_transient( 'timu_tools_cache' );

// 4. Plugin-Specific Cleanup
global $wpdb;

if ( 'thisismyurl-webp-support' === $plugin_slug ) {
    // Delete WebP specific post metadata for all attachments
    $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_webp_original_path', '_webp_savings')" );
}

if ( 'thisismyurl-heic-support' === $plugin_slug ) {
    // Reserved for any HEIC-specific metadata if added in the future.
}

/**
 * Note: Files stored in /uploads/webp-backups/ or similar are intentionally 
 * preserved to prevent data loss. If a user reinstalls the plugin, their 
 * originals will still be available for restoration.
 */