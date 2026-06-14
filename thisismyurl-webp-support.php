<?php
/**
 * Plugin Name:       WEBP Support by Christopher Ross
 * Plugin URI:        https://thisismyurl.com/thisismyurl-webp-support/
 * Description:       Non-destructive WebP/AVIF conversion with backups, bulk processing, and one-click restoration.
 * Version:           1.6165.0822
 * Author:            Christopher Ross
 * Author URI:        https://thisismyurl.com/
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       thisismyurl-webp-support
 *
 * @package TIMU_WEBP_Support
 */
 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'TIMU_WEBP_VERSION' ) ) {
    define( 'TIMU_WEBP_VERSION', '1.6165.0822' );
}
if ( ! defined( 'TIMU_WEBP_SUPPORT_DIR' ) ) {
    define( 'TIMU_WEBP_SUPPORT_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'TIMU_WEBP_SUPPORT_BASENAME' ) ) {
    define( 'TIMU_WEBP_SUPPORT_BASENAME', plugin_basename( __FILE__ ) );
}

class TIMU_WEBP_Support {

    const AJAX_NONCE_ACTION  = 'timu_wswebp_nonce';
    const BACKUP_META_KEY    = '_webp_original_path';
    const SAVINGS_META_KEY   = '_webp_savings';
    const COMPANION_META_KEY = '_webp_companion_path';
    const CONVERTED_AT_KEY   = '_webp_converted_at';
    const OPTION_KEY         = 'timu_webp_support_options';
    const SETTINGS_GROUP     = 'timu_webp_support_settings';
    const CRON_HOOK          = 'timu_webp_auto_optimize_event';
    const ENV_OPTION_KEY     = 'timu_webp_environment_status';
    const ADMIN_TICK_LOCK    = 'timu_webp_admin_tick_lock';
    const BATCH_BACKUP_LOCK  = 'timu_webp_batch_backup_lock';
    const LOCK_PREFIX        = 'timu_webp_lock_';
    const LOCK_TTL_SECONDS   = 300;

    /**
     * Initialize plugin hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_show_environment_notice' ) );
        add_action( 'init', array( __CLASS__, 'sync_auto_optimize_schedule' ), 25 );
        add_action( 'admin_init', array( __CLASS__, 'maybe_auto_optimize_on_admin_access' ), 40 );
        add_action( self::CRON_HOOK, array( __CLASS__, 'run_auto_optimize_cron' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedules' ) );
        add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'maybe_optimize_on_upload' ), 99, 2 );
        add_action( 'wp_ajax_timu_wsbulk_optimize', array( __CLASS__, 'ajax_bulk_optimize' ) );
        add_action( 'wp_ajax_timu_wsprocess_batch', array( __CLASS__, 'ajax_process_batch' ) );
        add_action( 'wp_ajax_timu_wsrestore_single', array( __CLASS__, 'ajax_restore_single' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_action_links' ) );
        add_filter( 'wp_get_attachment_image', array( __CLASS__, 'maybe_wrap_picture' ), 10, 5 );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'webp', 'TIMU_WEBP_Support_CLI' );
        }
    }

    /**
     * Load plugin translations.
     *
     * @return void
     */
    public static function load_textdomain() {
        load_plugin_textdomain(
            'thisismyurl-webp-support',
            false,
            dirname( TIMU_WEBP_SUPPORT_BASENAME ) . '/languages'
        );
    }

    /**
     * Register plugin settings.
     *
     * @return void
     */
    public static function register_settings() {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
                'default'           => self::get_default_options(),
            )
        );
    }

    /**
     * Enqueue admin assets for the tools page.
     *
     * @param string $hook_suffix Current admin page suffix.
     *
     * @return void
     */
    public static function enqueue_admin_assets( $hook_suffix ) {
        if ( 'tools_page_webp-optimizer' !== $hook_suffix ) {

            return;
        }

        wp_enqueue_script(
            'timu-webp-support-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
            array( 'jquery' ),
            TIMU_WEBP_VERSION,
            true
        );
    }

    /**
     * Register the Tools submenu page.
     *
     * @return void
     */
    public static function add_admin_menu() {
        add_management_page(
            __( 'WebP Support', 'thisismyurl-webp-support' ),
            __( 'WebP Support', 'thisismyurl-webp-support' ),
            'manage_options',
            'webp-optimizer',
            array( __CLASS__, 'render_admin_page' )
        );
    }

    /**
     * Add Settings and Sponsor links to plugin row actions.
     *
     * @param array $links Existing plugin row links.
     *
     * @return array
     */
    public static function add_plugin_action_links( $links ) {
        $settings_url = admin_url( 'tools.php?page=webp-optimizer&tab=settings' );

        $custom_links = array(
            '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'thisismyurl-webp-support' ) . '</a>',
            '<a href="' . esc_url( 'https://github.com/sponsors/thisismyurl' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sponsor', 'thisismyurl-webp-support' ) . '</a>',
        );

        return array_merge( $custom_links, $links );
    }

    /**
     * Return default plugin options.
     *
     * @return array
     */
    private static function get_default_options() {
        return array(
            'quality'                   => 80,
            'quality_preset'            => 'web',
            'output_format'             => 'webp',
            'batch_size'                => 10,
            'enabled_extensions'        => array( 'jpg', 'jpeg', 'png', 'gif' ),
            'optimize_on_upload'        => 1,
            'auto_optimize_enabled'     => 0,
            'auto_optimize_admin'       => 1,
            'auto_optimize_cron'        => 1,
            'auto_optimize_interval'    => 'hourly',
            'auto_optimize_batch'       => 3,
            'list_per_page'             => 25,
            'report_bandwidth_cost_gb'  => 0.08,
            'report_monthly_image_hits' => 50000,
            'track_outbound_utms'       => 1,
            'delete_backups_uninstall'  => 1,
        );
    }

    /**
     * Retrieve plugin options merged with defaults.
     *
     * @return array
     */
    private static function get_options() {
        $saved = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $saved ) ) {
            $saved = array();
        }

        return wp_parse_args( $saved, self::get_default_options() );
    }

    /**
     * Sanitize plugin options.
     *
     * @param array $input Unsanitized option values.
     *
     * @return array
     */
    public static function sanitize_options( $input ) {
        $defaults   = self::get_default_options();
        $input      = is_array( $input ) ? $input : array();
        $extensions = array_keys( self::get_extension_mime_map() );

        $quality = isset( $input['quality'] ) ? absint( $input['quality'] ) : $defaults['quality'];
        $quality = min( 100, max( 0, $quality ) );

        $allowed_presets = array( 'web', 'print', 'lossless', 'custom' );
        $quality_preset  = isset( $input['quality_preset'] ) ? sanitize_key( $input['quality_preset'] ) : $defaults['quality_preset'];
        if ( ! in_array( $quality_preset, $allowed_presets, true ) ) {
            $quality_preset = 'web';
        }

        $allowed_formats = array( 'webp', 'avif', 'both' );
        $output_format   = isset( $input['output_format'] ) ? sanitize_key( $input['output_format'] ) : $defaults['output_format'];
        if ( ! in_array( $output_format, $allowed_formats, true ) ) {
            $output_format = 'webp';
        }

        $batch_size = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : $defaults['batch_size'];
        $batch_size = min( 100, max( 1, $batch_size ) );

        $enabled_extensions = isset( $input['enabled_extensions'] ) ? (array) $input['enabled_extensions'] : $defaults['enabled_extensions'];
        $enabled_extensions = array_values( array_intersect( $extensions, array_map( 'sanitize_key', $enabled_extensions ) ) );

        if ( empty( $enabled_extensions ) ) {
            $enabled_extensions = array( 'jpg' );
        }

        $auto_batch = isset( $input['auto_optimize_batch'] ) ? absint( $input['auto_optimize_batch'] ) : $defaults['auto_optimize_batch'];
        $auto_batch = min( 25, max( 1, $auto_batch ) );

        $allowed_intervals = array( 'fifteen_minutes', 'hourly', 'twicedaily', 'daily' );
        $interval          = isset( $input['auto_optimize_interval'] ) ? sanitize_key( (string) $input['auto_optimize_interval'] ) : 'hourly';
        if ( ! in_array( $interval, $allowed_intervals, true ) ) {
            $interval = 'hourly';
        }

        $report_cost_gb = isset( $input['report_bandwidth_cost_gb'] ) ? (float) $input['report_bandwidth_cost_gb'] : (float) $defaults['report_bandwidth_cost_gb'];
        $report_cost_gb = min( 10, max( 0, $report_cost_gb ) );

        $report_hits = isset( $input['report_monthly_image_hits'] ) ? absint( $input['report_monthly_image_hits'] ) : (int) $defaults['report_monthly_image_hits'];
        $report_hits = min( 100000000, max( 0, $report_hits ) );

        return array(
            'quality'                   => $quality,
            'quality_preset'            => $quality_preset,
            'output_format'             => $output_format,
            'batch_size'                => $batch_size,
            'enabled_extensions'        => $enabled_extensions,
            'optimize_on_upload'        => isset( $input['optimize_on_upload'] ) ? 1 : 0,
            'auto_optimize_enabled'     => isset( $input['auto_optimize_enabled'] ) ? 1 : 0,
            'auto_optimize_admin'       => isset( $input['auto_optimize_admin'] ) ? 1 : 0,
            'auto_optimize_cron'        => isset( $input['auto_optimize_cron'] ) ? 1 : 0,
            'auto_optimize_interval'    => $interval,
            'auto_optimize_batch'       => $auto_batch,
            'list_per_page'             => min( 500, max( 5, isset( $input['list_per_page'] ) ? absint( $input['list_per_page'] ) : 25 ) ),
            'report_bandwidth_cost_gb'  => $report_cost_gb,
            'report_monthly_image_hits' => $report_hits,
            'track_outbound_utms'       => isset( $input['track_outbound_utms'] ) ? 1 : 0,
            'delete_backups_uninstall'  => isset( $input['delete_backups_uninstall'] ) ? 1 : 0,
        );
    }

    /**
     * Get active conversion quality.
     *
     * @return int
     */
    private static function get_quality_setting() {
        $options = self::get_options();
        $preset  = isset( $options['quality_preset'] ) ? $options['quality_preset'] : 'web';

        switch ( $preset ) {
            case 'print':
                return 95;
            case 'lossless':
                return 100;
            case 'custom':
                return (int) $options['quality'];
            case 'web':
            default:
                return 82;
        }
    }

    /**
     * Return the configured output format (webp|avif|both).
     *
     * @return string
     */
    private static function get_output_format() {
        $options = self::get_options();
        $format  = isset( $options['output_format'] ) ? $options['output_format'] : 'webp';

        if ( ! in_array( $format, array( 'webp', 'avif', 'both' ), true ) ) {
            $format = 'webp';
        }

        return $format;
    }

    /**
     * Detect whether the current image-editor stack can save AVIF.
     *
     * Imagick is the only WP_Image_Editor backend that emits AVIF reliably as of
     * WP 6.5+. We probe Imagick's compiled format list rather than trusting the
     * mime map, because an Imagick build without `libheif` will list `avif` in
     * mimes but fail at write time.
     *
     * @return bool
     */
    private static function supports_avif() {
        if ( ! class_exists( 'Imagick' ) ) {
            return false;
        }

        $formats = array_map( 'strtolower', Imagick::queryFormats( 'AVIF*' ) );
        return in_array( 'avif', $formats, true );
    }

    /**
     * Return whether at least one supported image engine is available.
     *
     * @return bool
     */
    private static function has_supported_image_engine() {
        return ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) || function_exists( 'gd_info' );
    }

    /**
     * Build a thisismyurl link with optional static, privacy-safe UTM tags.
     *
     * @param string $url      Destination URL.
     * @param string $campaign Campaign identifier.
     *
     * @return string
     */
    private static function get_thisismyurl_link( $url, $campaign ) {
        $options = self::get_options();
        if ( empty( $options['track_outbound_utms'] ) ) {
            return $url;
        }

        return add_query_arg(
            array(
                'utm_source'   => 'wp_plugin',
                'utm_medium'   => 'webp_support',
                'utm_campaign' => sanitize_key( $campaign ),
            ),
            $url
        );
    }

    /**
     * Activation callback. Records environment capability details for admins.
     *
     * @return void
     */
    public static function activate_plugin() {
        $status = array(
            'checked_at'  => time(),
            'has_imagick' => extension_loaded( 'imagick' ) && class_exists( 'Imagick' ),
            'has_gd'      => function_exists( 'gd_info' ),
            'php'         => PHP_VERSION,
            'wp_version'  => get_bloginfo( 'version' ),
        );

        update_option( self::ENV_OPTION_KEY, $status, false );
        set_transient( 'timu_webp_activation_status', $status, MINUTE_IN_SECONDS * 5 );
    }

    /**
     * Deactivation callback. Clears scheduled events and locks.
     *
     * @return void
     */
    public static function deactivate_plugin() {
        while ( false !== wp_next_scheduled( self::CRON_HOOK ) ) {
            $timestamp = wp_next_scheduled( self::CRON_HOOK );
            if ( false === $timestamp ) {
                break;
            }
            wp_unschedule_event( (int) $timestamp, self::CRON_HOOK );
        }
        delete_transient( self::ADMIN_TICK_LOCK );
    }

    /**
     * Register custom schedules used by background auto optimization.
     *
     * @param array $schedules Existing schedules.
     *
     * @return array
     */
    public static function register_cron_schedules( $schedules ) {
        if ( ! isset( $schedules['fifteen_minutes'] ) ) {
            $schedules['fifteen_minutes'] = array(
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every 15 Minutes', 'thisismyurl-webp-support' ),
            );
        }

        return $schedules;
    }

    /**
     * Show environment notice after activation or when no image engine exists.
     *
     * @return void
     */
    public static function maybe_show_environment_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = get_transient( 'timu_webp_activation_status' );
        if ( false !== $status ) {
            delete_transient( 'timu_webp_activation_status' );
        } else {
            $status = get_option( self::ENV_OPTION_KEY, array() );
        }

        if ( empty( $status ) || ! is_array( $status ) ) {
            return;
        }

        $has_imagick = ! empty( $status['has_imagick'] );
        $has_gd      = ! empty( $status['has_gd'] );

        if ( ! $has_imagick && ! $has_gd ) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'WebP Support requires GD or Imagick. Neither image engine was detected, so conversions cannot run until one is enabled.', 'thisismyurl-webp-support' );
            echo '</p></div>';
        }
    }

    /**
     * Keep auto-optimization cron scheduling aligned with plugin settings.
     *
     * @return void
     */
    public static function sync_auto_optimize_schedule() {
        $options         = self::get_options();
        $should_schedule = ! empty( $options['auto_optimize_enabled'] ) && ! empty( $options['auto_optimize_cron'] );

        if ( ! $should_schedule ) {
            while ( false !== wp_next_scheduled( self::CRON_HOOK ) ) {
                $timestamp = wp_next_scheduled( self::CRON_HOOK );
                if ( false === $timestamp ) {
                    break;
                }
                wp_unschedule_event( (int) $timestamp, self::CRON_HOOK );
            }
            return;
        }

        $interval = isset( $options['auto_optimize_interval'] ) ? $options['auto_optimize_interval'] : 'hourly';
        $event    = wp_get_scheduled_event( self::CRON_HOOK );

        if ( $event && isset( $event->schedule ) && $event->schedule !== $interval ) {
            wp_unschedule_event( (int) $event->timestamp, self::CRON_HOOK );
            $event = false;
        }

        if ( ! $event ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, self::CRON_HOOK );
        }
    }

    /**
     * Optimize new uploads right after metadata generation, when enabled.
     *
     * @param array $metadata      Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     *
     * @return array
     */
    public static function maybe_optimize_on_upload( $metadata, $attachment_id ) {
        $options = self::get_options();

        if ( empty( $options['optimize_on_upload'] ) ) {
            return $metadata;
        }

        if ( ! self::has_supported_image_engine() ) {
            return $metadata;
        }

        if ( get_post_meta( $attachment_id, self::BACKUP_META_KEY, true ) ) {
            return $metadata;
        }

        if ( ! in_array( get_post_mime_type( $attachment_id ), self::get_enabled_source_mimes(), true ) ) {
            return $metadata;
        }

        self::convert_image( (int) $attachment_id, self::get_quality_setting() );

        return $metadata;
    }

    /**
     * Process a small optimization batch when admin pages are accessed.
     *
     * @return void
     */
    public static function maybe_auto_optimize_on_admin_access() {
        if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        $options = self::get_options();
        if ( empty( $options['auto_optimize_enabled'] ) || empty( $options['auto_optimize_admin'] ) ) {
            return;
        }

        if ( get_transient( self::ADMIN_TICK_LOCK ) ) {
            return;
        }

        set_transient( self::ADMIN_TICK_LOCK, 1, MINUTE_IN_SECONDS * 5 );
        self::run_auto_optimize_batch( 'admin' );
    }

    /**
     * Cron callback for background auto optimization.
     *
     * @return void
     */
    public static function run_auto_optimize_cron() {
        self::run_auto_optimize_batch( 'cron' );
    }

    /**
     * Execute one small auto optimization batch.
     *
     * @param string $context Trigger context.
     *
     * @return void
     */
    private static function run_auto_optimize_batch( $context ) {
        if ( ! self::has_supported_image_engine() ) {
            return;
        }

        $enabled_mimes = self::get_enabled_source_mimes();
        if ( empty( $enabled_mimes ) ) {
            return;
        }

        $options = self::get_options();
        $limit   = isset( $options['auto_optimize_batch'] ) ? (int) $options['auto_optimize_batch'] : 3;
        $limit   = min( 25, max( 1, $limit ) );

        $query = new WP_Query(
            array(
                'post_type'              => 'attachment',
                'post_status'            => 'inherit',
                'posts_per_page'         => $limit,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'post_mime_type'         => $enabled_mimes,
                'meta_query'             => array(
                    array(
                        'key'     => self::BACKUP_META_KEY,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            )
        );

        if ( empty( $query->posts ) ) {
            return;
        }

        TIMU_WEBP_Backup_Adapter::snapshot( 'WebP auto optimize', array() );

        foreach ( $query->posts as $attachment_id ) {
            self::convert_image( (int) $attachment_id, self::get_quality_setting() );
        }
    }

    /**
     * Build reporting metrics for a selected date window.
     *
     * @param string $range_key Date range key.
     *
     * @return array
     */
    private static function get_report_metrics( $range_key ) {
        $now   = time();
        $start = 0;

        switch ( $range_key ) {
            case '30d':
                $start = $now - ( 30 * DAY_IN_SECONDS );
                break;
            case '90d':
                $start = $now - ( 90 * DAY_IN_SECONDS );
                break;
            case '365d':
                $start = $now - ( 365 * DAY_IN_SECONDS );
                break;
            case 'all':
            default:
                $start = 0;
                break;
        }

        $query = new WP_Query(
            array(
                'post_type'              => 'attachment',
                'post_status'            => 'inherit',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'post_mime_type'         => array( 'image/webp', 'image/avif' ),
                'meta_query'             => array(
                    array(
                        'key'     => self::BACKUP_META_KEY,
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        $converted_count = 0;
        $bytes_saved     = 0;

        if ( ! empty( $query->posts ) ) {
            foreach ( $query->posts as $attachment_id ) {
                $backup_ref = get_post_meta( $attachment_id, self::BACKUP_META_KEY, true );
                if ( ! $backup_ref || 'external' === $backup_ref ) {
                    continue;
                }

                $converted_at = (int) get_post_meta( $attachment_id, self::CONVERTED_AT_KEY, true );
                if ( $start > 0 && ( $converted_at <= 0 || $converted_at < $start ) ) {
                    continue;
                }

                ++$converted_count;
                $bytes_saved += (int) get_post_meta( $attachment_id, self::SAVINGS_META_KEY, true );
            }
        }

        $options         = self::get_options();
        $monthly_hits    = isset( $options['report_monthly_image_hits'] ) ? (int) $options['report_monthly_image_hits'] : 0;
        $cost_per_gb     = isset( $options['report_bandwidth_cost_gb'] ) ? (float) $options['report_bandwidth_cost_gb'] : 0.0;
        $avg_saved_bytes = $converted_count > 0 ? ( $bytes_saved / $converted_count ) : 0;
        $gb_per_month    = ( $avg_saved_bytes * $monthly_hits ) / ( 1024 * 1024 * 1024 );
        $monthly_roi     = $gb_per_month * $cost_per_gb;

        return array(
            'range'           => $range_key,
            'converted_count' => $converted_count,
            'bytes_saved'     => $bytes_saved,
            'gb_saved'        => $bytes_saved / ( 1024 * 1024 * 1024 ),
            'avg_saved_kb'    => $avg_saved_bytes / 1024,
            'monthly_hits'    => $monthly_hits,
            'cost_per_gb'     => $cost_per_gb,
            'monthly_roi'     => $monthly_roi,
            'annual_roi'      => $monthly_roi * 12,
        );
    }

    /**
     * Get active processing batch size.
     *
     * @return int
     */
    private static function get_batch_size_setting() {
        $options = self::get_options();
        return (int) $options['batch_size'];
    }

    /**
     * Return enabled source mime types.
     *
     * @return array
     */
    private static function get_enabled_source_mimes() {
        $options    = self::get_options();
        $enabled    = isset( $options['enabled_extensions'] ) ? (array) $options['enabled_extensions'] : array();
        $extension_map = self::get_extension_mime_map();
        $mimes      = array();

        foreach ( $enabled as $extension ) {
            if ( isset( $extension_map[ $extension ] ) ) {
                $mimes[] = $extension_map[ $extension ];
            }
        }

        return array_values( array_unique( $mimes ) );
    }

    /**
     * Initialize the WordPress Filesystem API.
     *
     * Falls back to a thin direct-PHP shim when WP_Filesystem refuses to
     * initialise (typically because the host wants FTP/SSH credentials and
     * we have no UI surface to prompt). Every entry point that calls this is
     * already gated by `current_user_can( 'manage_options' )`, so dropping
     * to direct file ops is acceptable in this code path.
     *
     * @return WP_Filesystem_Base|TIMU_WEBP_Direct_FS
     */
    private static function init_fs() {
        global $wp_filesystem;

        if ( ! empty( $wp_filesystem ) && is_object( $wp_filesystem ) ) {
            return $wp_filesystem;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();

        if ( ! empty( $wp_filesystem ) && is_object( $wp_filesystem ) ) {
            return $wp_filesystem;
        }

        return new TIMU_WEBP_Direct_FS();
    }

    /**
     * Return file extension to mime map for supported source formats.
     *
     * @return array
     */
    private static function get_extension_mime_map() {
        return array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
        );
    }

    /**
     * Regenerate image metadata after file replacement.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $absolute_path Absolute file path.
     *
     * @return void
     */
    private static function regenerate_metadata( $attachment_id, $absolute_path ) {
        if ( ! file_exists( $absolute_path ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_generate_attachment_metadata( $attachment_id, $absolute_path );
        if ( ! is_wp_error( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }
    }

    /**
     * Replace a file extension while preserving the path.
     *
     * @param string $path      File path.
     * @param string $extension New extension without dot.
     *
     * @return string
     */
    private static function swap_extension( $path, $extension ) {
        return preg_replace( '/\.[^.]+$/', '.' . ltrim( $extension, '.' ), $path );
    }

    /**
     * Build the backup directory path for an attachment.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return string
     */
    private static function get_backup_dir( $attachment_id ) {
        $upload_dir = wp_upload_dir();
        $rel_path   = get_post_meta( $attachment_id, '_wp_attached_file', true );
        $subdir     = dirname( $rel_path );

        if ( '.' === $subdir ) {
            $subdir = '';
        }

        return trailingslashit( $upload_dir['basedir'] . '/webp-backups/' . $subdir );
    }

    /**
     * Return lists of pending and managed media items.
     *
     * Walks the attachment table in pages of 200 IDs to keep memory bounded on
     * large libraries. The admin Tools view materialises full WP_Post objects
     * for each row it actually displays, so we only hydrate IDs here and let
     * `get_post()` warm the cache on demand inside the loop.
     *
     * @return array {
     *     @type WP_Post[] $pending Attachments awaiting WebP conversion.
     *     @type WP_Post[] $media   Attachments already managed (or with status flags).
     * }
     */
    public static function get_media_lists() {
        $source_mimes  = array_values( self::get_extension_mime_map() );
        $enabled_mimes = self::get_enabled_source_mimes();
        $mime_filter   = array_merge( $source_mimes, array( 'image/webp', 'image/avif' ) );

        $pending  = array();
        $media    = array();
        $page     = 1;
        $per_page = 200;

        do {
            $query = new WP_Query(
                array(
                    'post_type'              => 'attachment',
                    'post_status'            => 'inherit',
                    'posts_per_page'         => $per_page,
                    'paged'                  => $page,
                    'fields'                 => 'ids',
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'post_mime_type'         => $mime_filter,
                )
            );

            if ( empty( $query->posts ) ) {
                break;
            }

            foreach ( $query->posts as $attachment_id ) {
                $post = get_post( $attachment_id );
                if ( ! $post ) {
                    continue;
                }

                $file      = get_attached_file( $post->ID );
                $mime      = get_post_mime_type( $post->ID );
                $orig_path = get_post_meta( $post->ID, self::BACKUP_META_KEY, true );
                $is_converted = in_array( $mime, array( 'image/webp', 'image/avif' ), true );

                if ( $is_converted && ! $orig_path ) {
                    update_post_meta( $post->ID, self::BACKUP_META_KEY, 'external' );
                    $orig_path = 'external';
                }

                if ( ! $file || ! file_exists( $file ) ) {
                    $post->timu_wsstatus = 'missing';
                    $media[]             = $post;
                    continue;
                }

                if ( $orig_path || $is_converted ) {
                    $media[] = $post;
                } elseif ( in_array( $mime, $enabled_mimes, true ) ) {
                    $pending[] = $post;
                } else {
                    $post->timu_wsstatus = 'disabled_by_settings';
                    $media[]             = $post;
                }
            }

            $fetched = count( $query->posts );
            ++$page;
        } while ( $fetched === $per_page );

        return array(
            'pending' => $pending,
            'media'   => $media,
        );
    }

    /**
     * Resolve a stored backup-path meta value to an absolute filesystem path.
     *
     * New conversions (0.6123+) write the path relative to `uploads/basedir/`
     * so dev/prod database copies and host migrations don't orphan backups.
     * Legacy values written by earlier versions stored an absolute path; this
     * reader honours both shapes by leaf-checking for the absolute marker
     * before treating the value as relative.
     *
     * The sentinel string `external` (set on pre-existing WebP attachments
     * that were never converted by this plugin) is passed through unchanged.
     *
     * @param string $stored Raw meta value from `_webp_original_path`.
     *
     * @return string Absolute path, sentinel, or empty string.
     */
    private static function resolve_backup_path( $stored ) {
        if ( '' === $stored || 'external' === $stored ) {
            return (string) $stored;
        }

        // Legacy absolute path -- Unix `/foo`, Windows `C:\foo`, or UNC `\\server\foo`.
        if ( '/' === $stored[0] || '\\' === $stored[0] || ( isset( $stored[1] ) && ':' === $stored[1] ) ) {
            return $stored;
        }

        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] ) . ltrim( $stored, '/\\' );
    }

    /**
     * Convert an absolute backup path to the uploads-relative form we now
     * persist to postmeta. If the path sits outside uploads we fall back to
     * storing the absolute value so the data round-trips losslessly.
     *
     * @param string $absolute_path Absolute filesystem path.
     *
     * @return string
     */
    private static function relativize_backup_path( $absolute_path ) {
        $upload_dir = wp_upload_dir();
        $basedir    = trailingslashit( $upload_dir['basedir'] );

        if ( 0 === strpos( $absolute_path, $basedir ) ) {
            return substr( $absolute_path, strlen( $basedir ) );
        }

        return $absolute_path;
    }

    /**
     * Acquire a per-attachment lock to prevent concurrent conversion or
     * restoration of the same file.
     *
     * Backed by a transient so it survives across requests and processes. Two
     * browser tabs (or two operators) hitting Optimize on the same attachment
     * will see the second attempt short-circuit, rather than racing each other
     * through `wp_get_image_editor`, `move`, and `wp_update_post`.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return bool True if the lock was acquired, false if already held.
     */
    private static function acquire_lock( $attachment_id ) {
        $key = self::LOCK_PREFIX . (int) $attachment_id;
        if ( false !== get_transient( $key ) ) {
            return false;
        }

        return (bool) set_transient( $key, time(), self::LOCK_TTL_SECONDS );
    }

    /**
     * Release a per-attachment lock acquired via acquire_lock().
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return void
     */
    private static function release_lock( $attachment_id ) {
        delete_transient( self::LOCK_PREFIX . (int) $attachment_id );
    }

    /**
     * Convert an image to WebP and back up the original.
     *
     * Backward-compatible alias retained because external callers (and the
     * pre-0.6126 WP-CLI handler) reach for this name. New code should call
     * `convert_image()`.
     *
     * @param int $attachment_id Attachment ID.
     * @param int $quality       Encoder quality.
     *
     * @return true|WP_Error
     */
    public static function convert_to_webp( $attachment_id, $quality = null ) {
        return self::convert_image( $attachment_id, $quality );
    }

    /**
     * Convert an image to the configured output format(s) and back up the original.
     *
     * @param int      $attachment_id Attachment ID.
     * @param int|null $quality       Encoder quality, or null to use plugin settings.
     *
     * @return true|WP_Error
     */
    public static function convert_image( $attachment_id, $quality = null ) {
        $attachment_id = (int) $attachment_id;

        if ( ! self::acquire_lock( $attachment_id ) ) {
            return new WP_Error( 'locked', __( 'Another process is already converting this image.', 'thisismyurl-webp-support' ) );
        }

        try {
            return self::convert_image_locked( $attachment_id, $quality );
        } finally {
            self::release_lock( $attachment_id );
        }
    }

    /**
     * Inner conversion routine. Caller must hold the per-attachment lock.
     *
     * @param int      $attachment_id Attachment ID.
     * @param int|null $quality       Encoder quality, or null to use plugin settings.
     *
     * @return true|WP_Error
     */
    private static function convert_image_locked( $attachment_id, $quality = null ) {
        $fs        = self::init_fs();
        $full_path = get_attached_file( $attachment_id );

        if ( null === $quality ) {
            $quality = self::get_quality_setting();
        }

        if ( ! $fs || ! $full_path || ! $fs->exists( $full_path ) ) {
            return new WP_Error( 'missing', __( 'File does not exist.', 'thisismyurl-webp-support' ) );
        }

        $info = wp_getimagesize( $full_path );
        if ( empty( $info['mime'] ) ) {
            return new WP_Error( 'info', __( 'Invalid image data.', 'thisismyurl-webp-support' ) );
        }

        $mime = $info['mime'];
        if ( ! in_array( $mime, self::get_enabled_source_mimes(), true ) ) {
            return new WP_Error( 'mime', __( 'Unsupported format.', 'thisismyurl-webp-support' ) );
        }

        $output_format = self::get_output_format();
        $wants_avif    = ( 'avif' === $output_format || 'both' === $output_format );

        if ( $wants_avif && ! self::supports_avif() ) {
            if ( 'avif' === $output_format ) {
                return new WP_Error( 'avif_unsupported', __( 'AVIF output requested but Imagick is missing AVIF support on this server.', 'thisismyurl-webp-support' ) );
            }
            // Both -> fall back to WebP-only when AVIF isn't available.
            $output_format = 'webp';
            $wants_avif    = false;
        }

        $primary_ext  = $wants_avif ? 'avif' : 'webp';
        $primary_mime = $wants_avif ? 'image/avif' : 'image/webp';

        $original_size = filesize( $full_path );
        $new_path      = self::swap_extension( $full_path, $primary_ext );
        $rel_path      = get_post_meta( $attachment_id, '_wp_attached_file', true );
        $new_rel_path  = self::swap_extension( $rel_path, $primary_ext );

        $editor = wp_get_image_editor( $full_path );
        if ( is_wp_error( $editor ) ) {
            return new WP_Error( 'editor', __( 'WordPress image editor could not load this image.', 'thisismyurl-webp-support' ) );
        }

        if ( method_exists( $editor, 'set_quality' ) ) {
            $editor->set_quality( $quality );
        }

        $saved = $editor->save( $new_path, $primary_mime );
        if ( is_wp_error( $saved ) || ! $fs->exists( $new_path ) ) {
            return new WP_Error(
                'encode',
                $wants_avif
                    ? __( 'Failed to create AVIF file.', 'thisismyurl-webp-support' )
                    : __( 'Failed to create WebP file.', 'thisismyurl-webp-support' )
            );
        }

        // Extra safety net via the shared vault/shadow engine, layered on top of
        // this plugin's own per-file backup below. No-op when no engine is active.
        TIMU_WEBP_Backup_Adapter::snapshot( 'WebP optimize #' . $attachment_id, array( $full_path ) );

        $backup_dir = self::get_backup_dir( $attachment_id );
        if ( ! wp_mkdir_p( $backup_dir ) ) {
            $fs->delete( $new_path );
            return new WP_Error( 'mkdir', __( 'Unable to create backup directory.', 'thisismyurl-webp-support' ) );
        }

        $backup_path = $backup_dir . basename( $full_path );
        if ( ! $fs->move( $full_path, $backup_path, true ) ) {
            $fs->delete( $new_path );
            return new WP_Error( 'move', __( 'Failed to archive original file.', 'thisismyurl-webp-support' ) );
        }

        // Companion WebP for `both` mode -- created from the archived original so
        // we re-encode the source pixels, not the freshly-compressed AVIF.
        if ( 'both' === $output_format ) {
            $companion_path   = self::swap_extension( $new_path, 'webp' );
            $companion_editor = wp_get_image_editor( $backup_path );

            if ( ! is_wp_error( $companion_editor ) ) {
                if ( method_exists( $companion_editor, 'set_quality' ) ) {
                    $companion_editor->set_quality( $quality );
                }
                $companion_saved = $companion_editor->save( $companion_path, 'image/webp' );

                if ( ! is_wp_error( $companion_saved ) && $fs->exists( $companion_path ) ) {
                    update_post_meta(
                        $attachment_id,
                        self::COMPANION_META_KEY,
                        self::relativize_backup_path( $companion_path )
                    );
                }
            }
            // If companion creation fails we keep the AVIF and move on -- the
            // primary conversion already succeeded.
        }

        update_post_meta( $attachment_id, self::BACKUP_META_KEY, self::relativize_backup_path( $backup_path ) );
        update_post_meta( $attachment_id, self::SAVINGS_META_KEY, max( 0, (int) $original_size - (int) filesize( $new_path ) ) );
        update_post_meta( $attachment_id, self::CONVERTED_AT_KEY, time() );
        update_post_meta( $attachment_id, '_wp_attached_file', $new_rel_path );

        wp_update_post(
            array(
                'ID'             => $attachment_id,
                'post_mime_type' => $primary_mime,
            )
        );

        self::regenerate_metadata( $attachment_id, $new_path );

        return true;
    }

    /**
     * Restore an original image from backup.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return bool
     */
    public static function restore_image( $attachment_id ) {
        $attachment_id = (int) $attachment_id;

        if ( ! self::acquire_lock( $attachment_id ) ) {
            return false;
        }

        try {
            return self::restore_image_locked( $attachment_id );
        } finally {
            self::release_lock( $attachment_id );
        }
    }

    /**
     * Inner restoration routine. Caller must hold the per-attachment lock.
     *
     * @param int $attachment_id Attachment ID.
     *
     * @return bool
     */
    private static function restore_image_locked( $attachment_id ) {
        $fs          = self::init_fs();
        $backup_path = self::resolve_backup_path( get_post_meta( $attachment_id, self::BACKUP_META_KEY, true ) );

        if ( ! $fs || ! $backup_path || 'external' === $backup_path || ! $fs->exists( $backup_path ) ) {
            return false;
        }

        $current_webp = get_attached_file( $attachment_id );
        if ( ! $current_webp ) {
            return false;
        }

        $extension     = strtolower( pathinfo( $backup_path, PATHINFO_EXTENSION ) );
        $restored_path = self::swap_extension( $current_webp, $extension );

        // Extra safety net via the shared vault/shadow engine before we overwrite
        // the current file with the restored original. No-op when no engine is active.
        TIMU_WEBP_Backup_Adapter::snapshot( 'WebP restore #' . $attachment_id, array( $current_webp ) );

        if ( ! $fs->move( $backup_path, $restored_path, true ) ) {
            return false;
        }

        if ( $fs->exists( $current_webp ) ) {
            $fs->delete( $current_webp );
        }

        // Drop the companion file from `both` mode, if any.
        $companion_rel = get_post_meta( $attachment_id, self::COMPANION_META_KEY, true );
        if ( $companion_rel ) {
            $companion_abs = self::resolve_backup_path( $companion_rel );
            if ( $companion_abs && $fs->exists( $companion_abs ) ) {
                $fs->delete( $companion_abs );
            }
        }

        $rel_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
        $new_rel  = self::swap_extension( $rel_path, $extension );
        update_post_meta( $attachment_id, '_wp_attached_file', $new_rel );

        $mime_map = self::get_extension_mime_map();
        $mime     = isset( $mime_map[ $extension ] ) ? $mime_map[ $extension ] : 'image/jpeg';

        wp_update_post(
            array(
                'ID'             => $attachment_id,
                'post_mime_type' => $mime,
            )
        );

        self::regenerate_metadata( $attachment_id, $restored_path );

        delete_post_meta( $attachment_id, self::BACKUP_META_KEY );
        delete_post_meta( $attachment_id, self::SAVINGS_META_KEY );
        delete_post_meta( $attachment_id, self::COMPANION_META_KEY );
        delete_post_meta( $attachment_id, self::CONVERTED_AT_KEY );

        return true;
    }

    /**
     * Wrap an attachment image tag in a <picture> element when both AVIF and
     * a WebP companion exist on disk. Browsers without AVIF support fall back
     * to the WebP source; the bare <img> fallback covers everything else.
     *
     * @param string       $html          Original image markup.
     * @param int          $attachment_id Attachment ID.
     * @param string|array $size          Requested size.
     * @param bool         $icon          Whether the image is treated as an icon.
     * @param array|string $attr          Image attributes.
     *
     * @return string
     */
    public static function maybe_wrap_picture( $html, $attachment_id, $size, $icon, $attr ) {
        $companion = get_post_meta( $attachment_id, self::COMPANION_META_KEY, true );
        if ( ! $companion ) {
            return $html;
        }

        $companion_abs = self::resolve_backup_path( $companion );
        if ( ! $companion_abs || ! file_exists( $companion_abs ) ) {
            return $html;
        }

        $upload_dir    = wp_upload_dir();
        $companion_url = trailingslashit( $upload_dir['baseurl'] ) . ltrim( (string) $companion, '/\\' );
        $avif_url      = wp_get_attachment_url( $attachment_id );

        if ( ! $avif_url ) {
            return $html;
        }

        return '<picture>' .
            '<source srcset="' . esc_url( $avif_url ) . '" type="image/avif" />' .
            '<source srcset="' . esc_url( $companion_url ) . '" type="image/webp" />' .
            $html .
            '</picture>';
    }

    /**
     * AJAX callback: convert one image to WebP.
     *
     * @return void
     */
    public static function ajax_bulk_optimize() {
        check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized request.', 'thisismyurl-webp-support' ) );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        if ( ! $attachment_id ) {
            wp_send_json_error( __( 'Invalid attachment ID.', 'thisismyurl-webp-support' ) );
        }

        $result = self::convert_to_webp( $attachment_id, self::get_quality_setting() );

        if ( true === $result ) {
            wp_send_json_success(
                array(
                    'filename' => basename( (string) get_attached_file( $attachment_id ) ),
                    'thumb'    => wp_get_attachment_image( $attachment_id, array( 50, 50 ) ),
                )
            );
        }

        wp_send_json_error( is_wp_error( $result ) ? $result->get_error_message() : __( 'Unknown error.', 'thisismyurl-webp-support' ) );
    }

    /**
     * AJAX callback: process a chunk of attachments.
     *
     * @return void
     */
    public static function ajax_process_batch() {
        check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized request.', 'thisismyurl-webp-support' ) );
        }

        $batch_limit = self::get_batch_size_setting();
        $ids         = isset( $_POST['attachment_ids'] ) ? (array) wp_unslash( $_POST['attachment_ids'] ) : array();
        $ids         = array_slice( array_values( array_filter( array_map( 'absint', $ids ) ) ), 0, $batch_limit );

        if ( empty( $ids ) ) {
            wp_send_json_error( __( 'No attachments were provided for batch processing.', 'thisismyurl-webp-support' ) );
        }

        $processed_ids = array();
        $failed_ids    = array();
        $errors        = array();

        // Take one Vault/Shadow safety snapshot per short run window. Re-running a
        // full backup for every AJAX chunk can cause long delays or timeouts.
        $backup_lock_key = self::BATCH_BACKUP_LOCK . '_' . get_current_user_id();
        if ( ! get_transient( $backup_lock_key ) ) {
            TIMU_WEBP_Backup_Adapter::snapshot( 'WebP batch conversion', array() );
            set_transient( $backup_lock_key, 1, 15 * MINUTE_IN_SECONDS );
        }

        foreach ( $ids as $attachment_id ) {
            $result = self::convert_to_webp( $attachment_id, self::get_quality_setting() );
            if ( true === $result ) {
                $processed_ids[] = $attachment_id;
            } else {
                $failed_ids[] = $attachment_id;
                $errors[]     = is_wp_error( $result ) ? $result->get_error_message() : __( 'Unknown conversion error.', 'thisismyurl-webp-support' );
            }
        }

        wp_send_json_success(
            array(
                'processed_ids' => $processed_ids,
                'failed_ids'    => $failed_ids,
                'errors'        => array_values( array_unique( $errors ) ),
            )
        );
    }

    /**
     * AJAX callback: restore one optimized image.
     *
     * @return void
     */
    public static function ajax_restore_single() {
        check_ajax_referer( self::AJAX_NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized request.', 'thisismyurl-webp-support' ) );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        if ( ! $attachment_id ) {
            wp_send_json_error( __( 'Invalid attachment ID.', 'thisismyurl-webp-support' ) );
        }

        if ( self::restore_image( $attachment_id ) ) {
            wp_send_json_success();
        }

        wp_send_json_error( __( 'Image could not be restored.', 'thisismyurl-webp-support' ) );
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'thisismyurl-webp-support' ) );
        }

        $allowed_tabs = array( 'optimize', 'settings', 'report' );
        $active_tab   = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'optimize'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
            $active_tab = 'optimize';
        }

        $lists       = self::get_media_lists();
        $options     = self::get_options();
        $pending_ids = array_map(
            static function ( $post ) {
                return (int) $post->ID;
            },
            $lists['pending']
        );
        $restorable  = array();

        foreach ( $lists['media'] as $post ) {
            $orig = get_post_meta( $post->ID, self::BACKUP_META_KEY, true );
            if ( $orig && 'external' !== $orig ) {
                $restorable[] = (int) $post->ID;
            }
        }

        // Savings stats (issue #23).
        $pending_bytes = 0;
        foreach ( $lists['pending'] as $post ) {
            $f = get_attached_file( $post->ID );
            if ( $f && file_exists( $f ) ) {
                $pending_bytes += (int) filesize( $f );
            }
        }
        $managed_savings       = 0;
        $managed_savings_count = 0;
        foreach ( $lists['media'] as $post ) {
            $sv = (int) get_post_meta( $post->ID, self::SAVINGS_META_KEY, true );
            if ( $sv > 0 ) {
                $managed_savings += $sv;
                ++$managed_savings_count;
            }
        }

        wp_add_inline_script(
            'timu-webp-support-admin',
            'window.TIMUWebPSupportData = ' . wp_json_encode(
                array(
                    'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                    'nonce'      => wp_create_nonce( self::AJAX_NONCE_ACTION ),
                    'actions'    => array(
                        'batch'   => 'timu_wsprocess_batch',
                        'restore' => 'timu_wsrestore_single',
                    ),
                    'batchSize'  => self::get_batch_size_setting(),
                    'perPage'    => (int) $options['list_per_page'],
                    'pendingIds' => $pending_ids,
                    'strings'    => array(
                        'processing'        => __( 'Processing...', 'thisismyurl-webp-support' ),
                        'restoring'         => __( 'Restoring...', 'thisismyurl-webp-support' ),
                        'confirmRestoreAll' => __( 'Restore all images? This cannot be undone.', 'thisismyurl-webp-support' ),
                        'failedPrefix'      => __( 'Some images failed:', 'thisismyurl-webp-support' ),
                    ),
                )
            ) . ';',
            'before'
        );

        $base_url        = admin_url( 'tools.php?page=webp-optimizer' );
        $optimize_url    = $base_url . '&tab=optimize';
        $settings_url    = $base_url . '&tab=settings';
        $report_url      = $base_url . '&tab=report';
        $thisismyurl_url = self::get_thisismyurl_link( 'https://thisismyurl.com/', 'plugin_header' );
        $sponsor_url     = 'https://github.com/sponsors/thisismyurl';

        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'WebP Support', 'thisismyurl-webp-support' ); ?>
                <span style="font-size:0.5em;font-weight:normal;vertical-align:middle;margin-left:10px;color:#646970;">
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %s: link to thisismyurl.com */
                            __( 'by %s', 'thisismyurl-webp-support' ),
                            '<a href="' . esc_url( $thisismyurl_url ) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;color:inherit;">thisismyurl.com</a>'
                        )
                    );
                    ?>
                </span>
            </h1>

            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo esc_url( $optimize_url ); ?>" class="nav-tab<?php echo 'optimize' === $active_tab ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Optimize', 'thisismyurl-webp-support' ); ?>
                    <?php if ( ! empty( $pending_ids ) ) : ?>
                        <span class="awaiting-mod" style="margin-left:4px;"><?php echo esc_html( count( $pending_ids ) ); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url( $settings_url ); ?>" class="nav-tab<?php echo 'settings' === $active_tab ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'thisismyurl-webp-support' ); ?>
                </a>
                <a href="<?php echo esc_url( $report_url ); ?>" class="nav-tab<?php echo 'report' === $active_tab ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Report', 'thisismyurl-webp-support' ); ?>
                </a>
            </nav>

            <?php if ( 'optimize' === $active_tab ) : ?>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Optimization Dashboard', 'thisismyurl-webp-support' ); ?></span></h2>
                            <div class="inside">
                                <div class="welcome-panel-content" style="padding:10px 0;min-height:100px;">
                                    <div class="fwo-controls" style="display:flex;gap:10px;align-items:center;">
                                        <button id="btn-start" class="button button-primary button-large" <?php disabled( empty( $pending_ids ) ); ?>>
                                            <?php
                                            printf(
                                                /* translators: %d: number of pending images */
                                                esc_html__( 'Optimize All %d Images', 'thisismyurl-webp-support' ),
                                                count( $pending_ids )
                                            );
                                            ?>
                                        </button>
                                        <button id="btn-cancel" class="button button-secondary button-large" style="display:none;color:#d63638;">
                                            <?php esc_html_e( 'Cancel Batch', 'thisismyurl-webp-support' ); ?>
                                        </button>
                                    </div>

                                    <div id="fwo-progress-container"
                                        role="progressbar"
                                        aria-valuenow="0"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="<?php esc_attr_e( 'Batch optimization progress', 'thisismyurl-webp-support' ); ?>"
                                        style="display:none;margin-top:20px;background:#f0f0f1;height:30px;position:relative;border-radius:4px;overflow:hidden;border:1px solid #c3c4c7;">
                                        <div id="fwo-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.2s;"></div>
                                        <div id="fwo-progress-text" style="position:absolute;width:100%;text-align:center;top:0;line-height:30px;font-weight:bold;color:#fff;mix-blend-mode:difference;">0%</div>
                                    </div>
                                    <div id="fwo-status-region" role="status" aria-live="polite" class="screen-reader-text"></div>
                                    <?php if ( $pending_bytes > 0 || $managed_savings > 0 ) : ?>
                                    <p class="description" style="margin-top:14px;">
                                        <?php
                                        if ( $pending_bytes > 0 ) {
                                            printf(
                                                /* translators: 1: number of files, 2: total size formatted */
                                                esc_html__( 'Pending: %1$d file(s), %2$s total.', 'thisismyurl-webp-support' ),
                                                count( $lists['pending'] ),
                                                esc_html( size_format( $pending_bytes, 2 ) )
                                            );
                                        }
                                        if ( $managed_savings > 0 ) {
                                            if ( $pending_bytes > 0 ) {
                                                echo '&ensp;';
                                            }
                                            printf(
                                                /* translators: 1: savings size formatted, 2: image count */
                                                esc_html__( 'Saved: %1$s across %2$d image(s).', 'thisismyurl-webp-support' ),
                                                esc_html( size_format( $managed_savings, 2 ) ),
                                                $managed_savings_count
                                            );
                                        }
                                        ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Pending Optimizations', 'thisismyurl-webp-support' ); ?> (<span id="p-cnt"><?php echo esc_html( count( $pending_ids ) ); ?></span>)</span></h2>
                            <div class="inside">
                                <table class="widefat striped" id="fwo-pending-table" style="border:none;box-shadow:none;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Preview', 'thisismyurl-webp-support' ); ?></th>
                                            <th><?php esc_html_e( 'ID', 'thisismyurl-webp-support' ); ?></th>
                                            <th><?php esc_html_e( 'File Name', 'thisismyurl-webp-support' ); ?></th>
                                            <th><?php esc_html_e( 'Size', 'thisismyurl-webp-support' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ( ! empty( $lists['pending'] ) ) : ?>
                                            <?php foreach ( $lists['pending'] as $post ) : ?>
                                                <?php
                                                $pf  = get_attached_file( $post->ID );
                                                $psz = ( $pf && file_exists( $pf ) ) ? size_format( (int) filesize( $pf ), 1 ) : '&#8212;';
                                                ?>
                                                <tr id="fwo-row-<?php echo esc_attr( $post->ID ); ?>">
                                                    <td><?php echo wp_kses_post( wp_get_attachment_image( $post->ID, array( 50, 50 ) ) ); ?></td>
                                                    <td>#<?php echo esc_html( $post->ID ); ?></td>
                                                    <td><?php echo esc_html( basename( (string) $pf ) ); ?></td>
                                                    <td><?php echo esc_html( $psz ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <tr class="no-images"><td colspan="4"><?php esc_html_e( 'All images optimized!', 'thisismyurl-webp-support' ); ?></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Managed Media', 'thisismyurl-webp-support' ); ?> (<span id="m-cnt"><?php echo esc_html( count( $lists['media'] ) ); ?></span>)</span></h2>
                            <div class="inside">
                                <table class="widefat striped" id="fwo-media-table" style="border:none;box-shadow:none;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Preview', 'thisismyurl-webp-support' ); ?></th>
                                            <th><?php esc_html_e( 'ID', 'thisismyurl-webp-support' ); ?></th>
                                            <th><?php esc_html_e( 'File Name', 'thisismyurl-webp-support' ); ?></th>
                                            <th><?php esc_html_e( 'Saved', 'thisismyurl-webp-support' ); ?></th>
                                            <th><?php esc_html_e( 'Action', 'thisismyurl-webp-support' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $lists['media'] as $post ) : ?>
                                            <?php
                                            $orig     = get_post_meta( $post->ID, self::BACKUP_META_KEY, true );
                                            $status   = isset( $post->timu_wsstatus ) ? $post->timu_wsstatus : '';
                                            $savings  = (int) get_post_meta( $post->ID, self::SAVINGS_META_KEY, true );
                                            $saved_sz = $savings > 0 ? size_format( $savings, 1 ) : '&#8212;';
                                            ?>
                                            <tr id="fwo-media-row-<?php echo esc_attr( $post->ID ); ?>">
                                                <td><?php echo wp_kses_post( wp_get_attachment_image( $post->ID, array( 50, 50 ) ) ); ?></td>
                                                <td>#<?php echo esc_html( $post->ID ); ?></td>
                                                <td><?php echo esc_html( basename( (string) get_attached_file( $post->ID ) ) ); ?></td>
                                                <td><?php echo esc_html( $saved_sz ); ?></td>
                                                <td>
                                                    <?php if ( 'missing' === $status ) : ?>
                                                        <span style="color:#d63638;"><span aria-hidden="true">&#9888; </span><strong><?php esc_html_e( 'File Missing', 'thisismyurl-webp-support' ); ?></strong></span>
                                                    <?php elseif ( 'disabled_by_settings' === $status ) : ?>
                                                        <span class="description"><?php esc_html_e( 'Excluded by Settings', 'thisismyurl-webp-support' ); ?></span>
                                                    <?php elseif ( $orig && 'external' !== $orig ) : ?>
                                                        <button class="restore-btn button button-small" data-id="<?php echo esc_attr( $post->ID ); ?>">
                                                            <?php esc_html_e( 'Restore', 'thisismyurl-webp-support' ); ?>
                                                        </button>
                                                    <?php else : ?>
                                                        <span class="description"><?php esc_html_e( 'Optimized', 'thisismyurl-webp-support' ); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div><!-- #post-body-content -->

                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'About', 'thisismyurl-webp-support' ); ?></span></h2>
                            <div class="inside">
                                <p><?php esc_html_e( 'Converts JPEG, PNG, and GIF images to WebP or AVIF using the WordPress image editor (GD or Imagick). Originals are backed up and can be restored any time.', 'thisismyurl-webp-support' ); ?></p>
                                <?php if ( ! empty( $restorable ) ) : ?>
                                    <hr />
                                    <p><strong><?php esc_html_e( 'Bulk Actions', 'thisismyurl-webp-support' ); ?></strong></p>
                                    <button id="btn-restore-all" class="button button-secondary" style="width:100%;text-align:center;" data-ids="<?php echo esc_attr( wp_json_encode( $restorable ) ); ?>">
                                        <?php esc_html_e( 'Restore All Originals', 'thisismyurl-webp-support' ); ?>
                                    </button>
                                <?php endif; ?>
                                <hr />
                                <p>
                                    <?php
                                    echo wp_kses_post(
                                        sprintf(
                                            /* translators: %s: link to thisismyurl.com */
                                            __( 'Provided free by %s.', 'thisismyurl-webp-support' ),
                                            '<a href="' . esc_url( $thisismyurl_url ) . '" target="_blank" rel="noopener noreferrer">thisismyurl.com</a>'
                                        )
                                    );
                                    ?>
                                </p>
                                <p><a href="<?php echo esc_url( $sponsor_url ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer" style="width:100%;text-align:center;"><?php esc_html_e( 'Sponsor development', 'thisismyurl-webp-support' ); ?></a></p>
                            </div>
                        </div>
                    </div><!-- #postbox-container-1 -->

                </div><!-- #post-body -->
            </div><!-- #poststuff -->

            <?php elseif ( 'settings' === $active_tab ) : /* settings tab */ ?>

            <div id="poststuff" style="padding-top:10px;">
                <div id="post-body" class="metabox-holder columns-1">
                    <div id="post-body-content">

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Conversion Settings', 'thisismyurl-webp-support' ); ?></span></h2>
                            <div class="inside">
                                <form method="post" action="options.php">
                                    <?php settings_fields( self::SETTINGS_GROUP ); ?>
                                    <?php $avif_ok = self::supports_avif(); ?>
                                    <table class="form-table" role="presentation">
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Output format', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <fieldset>
                                                <legend class="screen-reader-text"><?php esc_html_e( 'Output format', 'thisismyurl-webp-support' ); ?></legend>
                                                <?php
                                                $output_presets = array(
                                                    'webp' => __( 'WebP (recommended)', 'thisismyurl-webp-support' ),
                                                    'avif' => __( 'AVIF (Imagick only)', 'thisismyurl-webp-support' ),
                                                    'both' => __( 'Both &#8212; AVIF primary + WebP companion via &lt;picture&gt;', 'thisismyurl-webp-support' ),
                                                );
                                                $cur_format = isset( $options['output_format'] ) ? $options['output_format'] : 'webp';
                                                foreach ( $output_presets as $val => $label ) :
                                                    $needs_avif = ( 'webp' !== $val );
                                                ?>
                                                    <label style="display:block;margin-bottom:4px;">
                                                        <input type="radio"
                                                            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[output_format]"
                                                            value="<?php echo esc_attr( $val ); ?>"
                                                            <?php checked( $cur_format, $val ); ?>
                                                            <?php disabled( $needs_avif && ! $avif_ok ); ?> />
                                                        <?php echo wp_kses( $label, array() ); ?>
                                                        <?php if ( $needs_avif && ! $avif_ok ) : ?>
                                                            <em style="color:#d63638;">&#8212; <?php esc_html_e( 'AVIF unavailable: Imagick not installed or missing libheif', 'thisismyurl-webp-support' ); ?></em>
                                                        <?php endif; ?>
                                                    </label>
                                                <?php endforeach; ?>
                                                </fieldset>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Quality preset', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <fieldset>
                                                <legend class="screen-reader-text"><?php esc_html_e( 'Quality preset', 'thisismyurl-webp-support' ); ?></legend>
                                                <?php
                                                $quality_presets = array(
                                                    'web'      => __( 'Web (82) &#8212; balanced size/quality', 'thisismyurl-webp-support' ),
                                                    'print'    => __( 'Print (95) &#8212; high fidelity', 'thisismyurl-webp-support' ),
                                                    'lossless' => __( 'Lossless (100)', 'thisismyurl-webp-support' ),
                                                    'custom'   => __( 'Custom', 'thisismyurl-webp-support' ),
                                                );
                                                $cur_preset = isset( $options['quality_preset'] ) ? $options['quality_preset'] : 'web';
                                                foreach ( $quality_presets as $val => $label ) :
                                                ?>
                                                    <label style="display:block;margin-bottom:4px;">
                                                        <input type="radio"
                                                            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quality_preset]"
                                                            value="<?php echo esc_attr( $val ); ?>"
                                                            <?php checked( $cur_preset, $val ); ?>
                                                            class="timu-preset-radio" />
                                                        <?php echo esc_html( $label ); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                                <?php $custom_hidden = ( 'custom' !== $cur_preset ); ?>
                                                <div id="timu-custom-quality" style="margin-top:8px;<?php echo $custom_hidden ? 'display:none;' : ''; ?>"<?php echo $custom_hidden ? ' aria-hidden="true"' : ''; ?>>
                                                    <label for="timu-quality"><?php esc_html_e( 'Custom quality (0&#8211;100):', 'thisismyurl-webp-support' ); ?></label>
                                                    <input id="timu-quality" type="number" min="0" max="100"
                                                        name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quality]"
                                                        value="<?php echo esc_attr( $options['quality'] ); ?>"
                                                        class="small-text" style="margin-left:6px;"
                                                        <?php disabled( $custom_hidden ); ?> />
                                                </div>
                                                </fieldset>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="timu-batch-size"><?php esc_html_e( 'Batch size', 'thisismyurl-webp-support' ); ?></label></th>
                                            <td>
                                                <input id="timu-batch-size" type="number" min="1" max="100" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[batch_size]" value="<?php echo esc_attr( $options['batch_size'] ); ?>" class="small-text" />
                                                <p class="description"><?php esc_html_e( 'Images processed per AJAX request. Lower this if you see timeouts. Default: 10.', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Enable conversion for', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <?php foreach ( self::get_extension_mime_map() as $extension => $mime ) : ?>
                                                    <label style="display:inline-block;min-width:100%;margin-right:12px;">
                                                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled_extensions][]" value="<?php echo esc_attr( $extension ); ?>" <?php checked( in_array( $extension, (array) $options['enabled_extensions'], true ) ); ?> />
                                                        <?php echo esc_html( strtoupper( $extension ) . ' (' . $mime . ')' ); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                                <p class="description"><?php esc_html_e( 'Only enabled formats appear in Pending Optimizations.', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Optimize on upload', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[optimize_on_upload]" value="1" <?php checked( ! empty( $options['optimize_on_upload'] ) ); ?> />
                                                    <?php esc_html_e( 'Automatically convert new uploads right after upload.', 'thisismyurl-webp-support' ); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Auto optimize', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <fieldset>
                                                    <label style="display:block;margin-bottom:6px;">
                                                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_optimize_enabled]" value="1" <?php checked( ! empty( $options['auto_optimize_enabled'] ) ); ?> />
                                                        <?php esc_html_e( 'Enable automatic background optimization for pending images.', 'thisismyurl-webp-support' ); ?>
                                                    </label>
                                                    <label style="display:block;margin-bottom:6px;">
                                                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_optimize_admin]" value="1" <?php checked( ! empty( $options['auto_optimize_admin'] ) ); ?> />
                                                        <?php esc_html_e( 'Run a small optimization batch during wp-admin page visits.', 'thisismyurl-webp-support' ); ?>
                                                    </label>
                                                    <label style="display:block;margin-bottom:10px;">
                                                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_optimize_cron]" value="1" <?php checked( ! empty( $options['auto_optimize_cron'] ) ); ?> />
                                                        <?php esc_html_e( 'Run optimization in WP-Cron.', 'thisismyurl-webp-support' ); ?>
                                                    </label>
                                                    <p>
                                                        <label for="timu-auto-batch" style="margin-right:8px;"><?php esc_html_e( 'Images per auto run:', 'thisismyurl-webp-support' ); ?></label>
                                                        <input id="timu-auto-batch" type="number" min="1" max="25" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_optimize_batch]" value="<?php echo esc_attr( $options['auto_optimize_batch'] ); ?>" class="small-text" />
                                                    </p>
                                                    <p>
                                                        <label for="timu-auto-interval" style="margin-right:8px;"><?php esc_html_e( 'WP-Cron interval:', 'thisismyurl-webp-support' ); ?></label>
                                                        <select id="timu-auto-interval" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[auto_optimize_interval]">
                                                            <option value="fifteen_minutes" <?php selected( 'fifteen_minutes', $options['auto_optimize_interval'] ); ?>><?php esc_html_e( 'Every 15 minutes', 'thisismyurl-webp-support' ); ?></option>
                                                            <option value="hourly" <?php selected( 'hourly', $options['auto_optimize_interval'] ); ?>><?php esc_html_e( 'Hourly', 'thisismyurl-webp-support' ); ?></option>
                                                            <option value="twicedaily" <?php selected( 'twicedaily', $options['auto_optimize_interval'] ); ?>><?php esc_html_e( 'Twice daily', 'thisismyurl-webp-support' ); ?></option>
                                                            <option value="daily" <?php selected( 'daily', $options['auto_optimize_interval'] ); ?>><?php esc_html_e( 'Daily', 'thisismyurl-webp-support' ); ?></option>
                                                        </select>
                                                    </p>
                                                    <p class="description"><?php esc_html_e( 'Enable one or both triggers: admin traffic, cron, or both.', 'thisismyurl-webp-support' ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="timu-per-page"><?php esc_html_e( 'Items per page', 'thisismyurl-webp-support' ); ?></label></th>
                                            <td>
                                                <input id="timu-per-page" type="number" min="5" max="500" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[list_per_page]" value="<?php echo esc_attr( $options['list_per_page'] ); ?>" class="small-text" />
                                                <p class="description"><?php esc_html_e( 'How many images to show per page in the Pending and Managed Media lists. Default: 25.', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Report assumptions', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <p>
                                                    <label for="timu-monthly-hits" style="display:inline-block;min-width:240px;"><?php esc_html_e( 'Estimated monthly image requests', 'thisismyurl-webp-support' ); ?></label>
                                                    <input id="timu-monthly-hits" type="number" min="0" max="100000000" step="1" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[report_monthly_image_hits]" value="<?php echo esc_attr( $options['report_monthly_image_hits'] ); ?>" class="regular-text" style="max-width:180px;" />
                                                </p>
                                                <p>
                                                    <label for="timu-cost-gb" style="display:inline-block;min-width:240px;"><?php esc_html_e( 'Bandwidth cost per GB (USD)', 'thisismyurl-webp-support' ); ?></label>
                                                    <input id="timu-cost-gb" type="number" min="0" max="10" step="0.01" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[report_bandwidth_cost_gb]" value="<?php echo esc_attr( $options['report_bandwidth_cost_gb'] ); ?>" class="regular-text" style="max-width:180px;" />
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Outbound UTM parameters', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[track_outbound_utms]" value="1" <?php checked( ! empty( $options['track_outbound_utms'] ) ); ?> />
                                                    <?php esc_html_e( 'Add privacy-safe UTM parameters to links to thisismyurl.com.', 'thisismyurl-webp-support' ); ?>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'These UTMs include no site IDs, account IDs, user IDs, visitor data, or domain names. They only identify this plugin as the traffic source.', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'On uninstall', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_backups_uninstall]" value="1" <?php checked( ! empty( $options['delete_backups_uninstall'] ) ); ?> />
                                                    <?php esc_html_e( 'Delete all backup files when the plugin is uninstalled.', 'thisismyurl-webp-support' ); ?>
                                                </label>
                                                <p class="description"><?php esc_html_e( 'Leave unchecked to keep originals in the backup directory even after removing the plugin.', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                    </table>

                                    <?php submit_button( __( 'Save Settings', 'thisismyurl-webp-support' ) ); ?>
                                </form>
                            </div>
                        </div>

                    </div><!-- #post-body-content -->
                </div><!-- #post-body -->
            </div><!-- #poststuff -->

            <?php else : /* report tab */ ?>

            <?php
            $report_range = isset( $_GET['range'] ) ? sanitize_key( (string) $_GET['range'] ) : '30d'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( ! in_array( $report_range, array( '30d', '90d', '365d', 'all' ), true ) ) {
                $report_range = '30d';
            }
            $report_data = self::get_report_metrics( $report_range );
            ?>

            <div id="poststuff" style="padding-top:10px;">
                <div id="post-body" class="metabox-holder columns-1">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Business ROI report', 'thisismyurl-webp-support' ); ?></span></h2>
                            <div class="inside">
                                <p class="description"><?php esc_html_e( 'Use these metrics to show the measurable value this plugin has provided over business-friendly time windows.', 'thisismyurl-webp-support' ); ?></p>
                                <p>
                                    <a class="button <?php echo '30d' === $report_range ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'report', 'range' => '30d' ), $base_url ) ); ?>"><?php esc_html_e( 'Last 30 days', 'thisismyurl-webp-support' ); ?></a>
                                    <a class="button <?php echo '90d' === $report_range ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'report', 'range' => '90d' ), $base_url ) ); ?>"><?php esc_html_e( 'Last 90 days', 'thisismyurl-webp-support' ); ?></a>
                                    <a class="button <?php echo '365d' === $report_range ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'report', 'range' => '365d' ), $base_url ) ); ?>"><?php esc_html_e( 'Last 12 months', 'thisismyurl-webp-support' ); ?></a>
                                    <a class="button <?php echo 'all' === $report_range ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'report', 'range' => 'all' ), $base_url ) ); ?>"><?php esc_html_e( 'All time', 'thisismyurl-webp-support' ); ?></a>
                                </p>

                                <table class="widefat striped" style="max-width:960px;">
                                    <tbody>
                                        <tr>
                                            <th style="width:340px;"><?php esc_html_e( 'Images optimized in period', 'thisismyurl-webp-support' ); ?></th>
                                            <td><?php echo esc_html( number_format_i18n( (int) $report_data['converted_count'] ) ); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Total bandwidth saved (if each image is requested once)', 'thisismyurl-webp-support' ); ?></th>
                                            <td><?php echo esc_html( size_format( (int) $report_data['bytes_saved'], 2 ) ); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Average savings per image', 'thisismyurl-webp-support' ); ?></th>
                                            <td><?php echo esc_html( number_format_i18n( (float) $report_data['avg_saved_kb'], 2 ) . ' KB' ); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Estimated monthly ROI', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <?php
                                                echo esc_html(
                                                    sprintf(
                                                        /* translators: 1: monthly savings, 2: annual savings */
                                                        __( '$%1$s / month (about $%2$s / year)', 'thisismyurl-webp-support' ),
                                                        number_format_i18n( (float) $report_data['monthly_roi'], 2 ),
                                                        number_format_i18n( (float) $report_data['annual_roi'], 2 )
                                                    )
                                                );
                                                ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="description" style="margin-top:10px;">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: 1: image hit count, 2: cost per GB */
                                            __( 'ROI estimate uses %1$s image requests/month and $%2$s bandwidth cost per GB from your settings.', 'thisismyurl-webp-support' ),
                                            number_format_i18n( (int) $report_data['monthly_hits'] ),
                                            number_format_i18n( (float) $report_data['cost_per_gb'], 2 )
                                        )
                                    );
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div><!-- .wrap -->
        <?php
    }
}

/**
 * Minimal direct-PHP filesystem shim used when WP_Filesystem refuses to
 * initialise (typically a host that wants FTP/SSH credentials and gives us
 * no UI to surface them). Implements the methods this plugin actually calls
 * -- `exists`, `delete`, `move` -- with the same return shapes WP_Filesystem
 * uses, so callers don't need to branch on which backend they got.
 *
 * Only acceptable here because every entry point that touches the filesystem
 * is already capability-gated to `manage_options`.
 */
class TIMU_WEBP_Direct_FS {

    /**
     * Whether a path exists.
     *
     * @param string $path Absolute filesystem path.
     *
     * @return bool
     */
    public function exists( $path ) {
        return file_exists( $path );
    }

    /**
     * Delete a file or directory.
     *
     * @param string $path      Absolute filesystem path.
     * @param bool   $recursive Recurse into directories.
     *
     * @return bool
     */
    public function delete( $path, $recursive = false ) {
        if ( ! file_exists( $path ) ) {
            return false;
        }

        if ( is_file( $path ) || is_link( $path ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- WP_Filesystem unavailable; see init_fs().
            return @unlink( $path );
        }

        if ( ! $recursive ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir -- WP_Filesystem unavailable; see init_fs().
            return @rmdir( $path );
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $child ) {
            if ( $child->isDir() ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir -- WP_Filesystem unavailable; see init_fs().
                @rmdir( $child->getPathname() );
            } else {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- WP_Filesystem unavailable; see init_fs().
                @unlink( $child->getPathname() );
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir -- WP_Filesystem unavailable; see init_fs().
        return @rmdir( $path );
    }

    /**
     * Move a file, optionally overwriting the destination.
     *
     * @param string $source      Absolute source path.
     * @param string $destination Absolute destination path.
     * @param bool   $overwrite   Overwrite if destination exists.
     *
     * @return bool
     */
    public function move( $source, $destination, $overwrite = false ) {
        if ( ! file_exists( $source ) ) {
            return false;
        }

        if ( file_exists( $destination ) ) {
            if ( ! $overwrite ) {
                return false;
            }
            $this->delete( $destination );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- WP_Filesystem unavailable; see init_fs().
        return @rename( $source, $destination );
    }
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-timu-webp-cli.php';
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-backup-adapter.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/abilities.php';

register_activation_hook( __FILE__, array( 'TIMU_WEBP_Support', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'TIMU_WEBP_Support', 'deactivate_plugin' ) );

TIMU_WEBP_Support::init();

require_once plugin_dir_path( __FILE__ ) . 'github-updater.php';

\ThisIsMyURL\WebP\GitHubReleaseUpdater::boot(
    array(
        'plugin_file' => __FILE__,
        'slug'        => 'thisismyurl-webp-support',
        'repo'        => 'thisismyurl/thisismyurl-webp-support',
    )
);
