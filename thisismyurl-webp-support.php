<?php
/**
 * Plugin Name:       WEBP Support by thisismyurl.com
 * Plugin URI:        https://thisismyurl.com/thisismyurl-webp-support/
 * Description:       Non-destructive WebP conversion with backups, bulk processing, and one-click restoration.
 * Version:           0.6123
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

class TIMU_WEBP_Support {

    const AJAX_NONCE_ACTION = 'timu_wswebp_nonce';
    const BACKUP_META_KEY   = '_webp_original_path';
    const SAVINGS_META_KEY  = '_webp_savings';
    const OPTION_KEY        = 'timu_webp_support_options';
    const SETTINGS_GROUP    = 'timu_webp_support_settings';
    const LOCK_PREFIX       = 'timu_webp_lock_';
    const LOCK_TTL_SECONDS  = 300;

    /**
     * Initialize plugin hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_timu_wsbulk_optimize', array( __CLASS__, 'ajax_bulk_optimize' ) );
        add_action( 'wp_ajax_timu_wsprocess_batch', array( __CLASS__, 'ajax_process_batch' ) );
        add_action( 'wp_ajax_timu_wsrestore_single', array( __CLASS__, 'ajax_restore_single' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_action_links' ) );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'webp', 'TIMU_WEBP_Support_CLI' );
        }
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
            '1.26112',
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
     * Add Settings and Donate links to plugin row actions.
     *
     * @param array $links Existing plugin row links.
     *
     * @return array
     */
    public static function add_plugin_action_links( $links ) {
        $custom_links = array(
            '<a href="' . esc_url( admin_url( 'tools.php?page=webp-optimizer' ) ) . '">' . esc_html__( 'Settings', 'thisismyurl-webp-support' ) . '</a>',
            '<a href="' . esc_url( 'https://thisismyurl.com/donate/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'thisismyurl-webp-support' ) . '</a>',
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
            'batch_size'                => 10,
            'enabled_extensions'        => array( 'jpg', 'jpeg', 'png', 'gif', 'bmp' ),
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

        $batch_size = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : $defaults['batch_size'];
        $batch_size = min( 100, max( 1, $batch_size ) );

        $enabled_extensions = isset( $input['enabled_extensions'] ) ? (array) $input['enabled_extensions'] : $defaults['enabled_extensions'];
        $enabled_extensions = array_values( array_intersect( $extensions, array_map( 'sanitize_key', $enabled_extensions ) ) );

        if ( empty( $enabled_extensions ) ) {
            $enabled_extensions = array( 'jpg' );
        }

        return array(
            'quality'                  => $quality,
            'batch_size'               => $batch_size,
            'enabled_extensions'       => $enabled_extensions,
            'delete_backups_uninstall' => isset( $input['delete_backups_uninstall'] ) ? 1 : 0,
        );
    }

    /**
     * Get active conversion quality.
     *
     * @return int
     */
    private static function get_quality_setting() {
        $options = self::get_options();
        return (int) $options['quality'];
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
            'bmp'  => 'image/bmp',
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
        $mime_filter   = array_merge( $source_mimes, array( 'image/webp' ) );

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
                $is_webp   = ( 'image/webp' === $mime );

                if ( $is_webp && ! $orig_path ) {
                    update_post_meta( $post->ID, self::BACKUP_META_KEY, 'external' );
                    $orig_path = 'external';
                }

                if ( ! $file || ! file_exists( $file ) ) {
                    $post->timu_wsstatus = 'missing';
                    $media[]             = $post;
                    continue;
                }

                if ( $orig_path || $is_webp ) {
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
     * so dev↔prod database copies and host migrations don't orphan backups.
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

        // Legacy absolute path — Unix `/foo`, Windows `C:\foo`, or UNC `\\server\foo`.
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
     * @param int $attachment_id Attachment ID.
     * @param int $quality       WebP quality.
     *
     * @return true|WP_Error
     */
    public static function convert_to_webp( $attachment_id, $quality = null ) {
        $attachment_id = (int) $attachment_id;

        if ( ! self::acquire_lock( $attachment_id ) ) {
            return new WP_Error( 'locked', __( 'Another process is already converting this image.', 'thisismyurl-webp-support' ) );
        }

        try {
            return self::convert_to_webp_locked( $attachment_id, $quality );
        } finally {
            self::release_lock( $attachment_id );
        }
    }

    /**
     * Inner conversion routine. Caller must hold the per-attachment lock.
     *
     * @param int      $attachment_id Attachment ID.
     * @param int|null $quality       WebP quality, or null to use plugin settings.
     *
     * @return true|WP_Error
     */
    private static function convert_to_webp_locked( $attachment_id, $quality = null ) {
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

        $original_size = filesize( $full_path );
        $new_path      = self::swap_extension( $full_path, 'webp' );
        $rel_path      = get_post_meta( $attachment_id, '_wp_attached_file', true );
        $new_rel_path  = self::swap_extension( $rel_path, 'webp' );

        $editor = wp_get_image_editor( $full_path );
        if ( is_wp_error( $editor ) ) {
            return new WP_Error( 'editor', __( 'WordPress image editor could not load this image.', 'thisismyurl-webp-support' ) );
        }

        if ( method_exists( $editor, 'set_quality' ) ) {
            $editor->set_quality( $quality );
        }

        $saved = $editor->save( $new_path, 'image/webp' );
        if ( is_wp_error( $saved ) || ! $fs->exists( $new_path ) ) {
            return new WP_Error( 'webp', __( 'Failed to create WebP file.', 'thisismyurl-webp-support' ) );
        }

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

        update_post_meta( $attachment_id, self::BACKUP_META_KEY, self::relativize_backup_path( $backup_path ) );
        update_post_meta( $attachment_id, self::SAVINGS_META_KEY, max( 0, (int) $original_size - (int) filesize( $new_path ) ) );
        update_post_meta( $attachment_id, '_wp_attached_file', $new_rel_path );

        wp_update_post(
            array(
                'ID'           => $attachment_id,
                'post_mime_type' => 'image/webp',
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

        if ( ! $fs->move( $backup_path, $restored_path, true ) ) {
            return false;
        }

        if ( $fs->exists( $current_webp ) ) {
            $fs->delete( $current_webp );
        }

        $rel_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
        $new_rel  = self::swap_extension( $rel_path, $extension );
        update_post_meta( $attachment_id, '_wp_attached_file', $new_rel );

        $mime_map = self::get_extension_mime_map();
        $mime     = isset( $mime_map[ $extension ] ) ? $mime_map[ $extension ] : 'image/jpeg';

        wp_update_post(
            array(
                'ID'           => $attachment_id,
                'post_mime_type' => $mime,
            )
        );

        self::regenerate_metadata( $attachment_id, $restored_path );

        delete_post_meta( $attachment_id, self::BACKUP_META_KEY );
        delete_post_meta( $attachment_id, self::SAVINGS_META_KEY );

        return true;
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

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
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
        $ids         = isset( $_POST['attachment_ids'] ) ? (array) $_POST['attachment_ids'] : array();
        $ids         = array_slice( array_values( array_filter( array_map( 'absint', $ids ) ) ), 0, $batch_limit );

        if ( empty( $ids ) ) {
            wp_send_json_error( __( 'No attachments were provided for batch processing.', 'thisismyurl-webp-support' ) );
        }

        $processed_ids = array();
        $failed_ids    = array();
        $errors        = array();

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

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
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
                    'pendingIds' => $pending_ids,
                    'strings'    => array(
                        'processing'       => __( 'Processing...', 'thisismyurl-webp-support' ),
                        'restoring'        => __( 'Restoring...', 'thisismyurl-webp-support' ),
                        'confirmRestoreAll'=> __( 'Restore all images? This cannot be undone.', 'thisismyurl-webp-support' ),
                        'failedPrefix'     => __( 'Some images failed:', 'thisismyurl-webp-support' ),
                    ),
                )
            ) . ';',
            'before'
        );

        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'WebP Support', 'thisismyurl-webp-support' ); ?>
                <span style="font-size:0.5em;font-weight:normal;vertical-align:middle;margin-left:10px;color:#646970;">
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            __( 'by %s', 'thisismyurl-webp-support' ),
                            '<a href="https://thisismyurl.com/" target="_blank" rel="noopener noreferrer" style="text-decoration:none;color:inherit;">thisismyurl.com</a>'
                        )
                    );
                    ?>
                </span>
            </h1>
            <p><?php esc_html_e( 'Non-destructive WebP conversion with backups and one-click restoration.', 'thisismyurl-webp-support' ); ?></p>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Optimization Dashboard', 'thisismyurl-webp-support' ); ?></span></h2>
                            <div class="inside">
                                <div class="welcome-panel-content" style="padding:10px 0;min-height:100px;">
                                    <div class="fwo-controls" style="display:flex;gap:10px;align-items:center;">
                                        <button id="btn-start" class="button button-primary button-large" <?php disabled( empty( $pending_ids ) ); ?>>
                                            <?php printf( esc_html__( 'Optimize All %d Images', 'thisismyurl-webp-support' ), count( $pending_ids ) ); ?>
                                        </button>
                                        <button id="btn-cancel" class="button button-secondary button-large" style="display:none;color:#d63638;">
                                            <?php esc_html_e( 'Cancel Batch', 'thisismyurl-webp-support' ); ?>
                                        </button>
                                    </div>

                                    <div id="fwo-progress-container" style="display:none;margin-top:20px;background:#f0f0f1;height:30px;position:relative;border-radius:4px;overflow:hidden;border:1px solid #c3c4c7;">
                                        <div id="fwo-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.2s;"></div>
                                        <div id="fwo-progress-text" style="position:absolute;width:100%;text-align:center;top:0;line-height:30px;font-weight:bold;color:#fff;mix-blend-mode:difference;">0%</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Conversion Settings', 'thisismyurl-webp-support' ); ?></span></h2>
                            <div class="inside">
                                <form method="post" action="options.php">
                                    <?php settings_fields( self::SETTINGS_GROUP ); ?>
                                    <table class="form-table" role="presentation">
                                        <tr>
                                            <th scope="row"><label for="timu-quality"><?php esc_html_e( 'WebP Quality', 'thisismyurl-webp-support' ); ?></label></th>
                                            <td>
                                                <input id="timu-quality" type="number" min="0" max="100" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[quality]" value="<?php echo esc_attr( $options['quality'] ); ?>" class="small-text" />
                                                <p class="description"><?php esc_html_e( 'Controls compression quality from 0 (smallest) to 100 (highest quality).', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="timu-batch-size"><?php esc_html_e( 'Batch Size', 'thisismyurl-webp-support' ); ?></label></th>
                                            <td>
                                                <input id="timu-batch-size" type="number" min="1" max="100" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[batch_size]" value="<?php echo esc_attr( $options['batch_size'] ); ?>" class="small-text" />
                                                <p class="description"><?php esc_html_e( 'Number of images processed per AJAX request.', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Enable Conversion For', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <?php foreach ( self::get_extension_mime_map() as $extension => $mime ) : ?>
                                                    <label style="display:inline-block;min-width:120px;margin-right:12px;">
                                                        <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled_extensions][]" value="<?php echo esc_attr( $extension ); ?>" <?php checked( in_array( $extension, (array) $options['enabled_extensions'], true ) ); ?> />
                                                        <?php echo esc_html( strtoupper( $extension ) . ' (' . $mime . ')' ); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                                <p class="description"><?php esc_html_e( 'Only enabled formats will appear in Pending Optimizations.', 'thisismyurl-webp-support' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Uninstall Behavior', 'thisismyurl-webp-support' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_backups_uninstall]" value="1" <?php checked( ! empty( $options['delete_backups_uninstall'] ) ); ?> />
                                                    <?php esc_html_e( 'Delete backup files when plugin is uninstalled.', 'thisismyurl-webp-support' ); ?>
                                                </label>
                                            </td>
                                        </tr>
                                    </table>

                                    <?php submit_button( __( 'Save Settings', 'thisismyurl-webp-support' ) ); ?>
                                </form>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ( ! empty( $lists['pending'] ) ) : ?>
                                            <?php foreach ( $lists['pending'] as $post ) : ?>
                                                <tr id="fwo-row-<?php echo esc_attr( $post->ID ); ?>">
                                                    <td><?php echo wp_kses_post( wp_get_attachment_image( $post->ID, array( 50, 50 ) ) ); ?></td>
                                                    <td>#<?php echo esc_html( $post->ID ); ?></td>
                                                    <td><?php echo esc_html( basename( (string) get_attached_file( $post->ID ) ) ); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <tr class="no-images"><td colspan="3"><?php esc_html_e( 'All images optimized!', 'thisismyurl-webp-support' ); ?></td></tr>
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
                                            <th><?php esc_html_e( 'Action', 'thisismyurl-webp-support' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $lists['media'] as $post ) : ?>
                                            <?php
                                            $orig   = get_post_meta( $post->ID, self::BACKUP_META_KEY, true );
                                            $status = isset( $post->timu_wsstatus ) ? $post->timu_wsstatus : '';
                                            ?>
                                            <tr id="fwo-media-row-<?php echo esc_attr( $post->ID ); ?>">
                                                <td><?php echo wp_kses_post( wp_get_attachment_image( $post->ID, array( 50, 50 ) ) ); ?></td>
                                                <td>#<?php echo esc_html( $post->ID ); ?></td>
                                                <td><?php echo esc_html( basename( (string) get_attached_file( $post->ID ) ) ); ?></td>
                                                <td>
                                                    <?php if ( 'missing' === $status ) : ?>
                                                        <span style="color:#d63638;"><?php esc_html_e( 'File Missing', 'thisismyurl-webp-support' ); ?></span>
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

                    </div>

                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Documentation', 'thisismyurl-webp-support' ); ?></span></h2>
                            <div class="inside">
                                <p><?php esc_html_e( 'This plugin converts JPEG, PNG, GIF, and BMP images into WebP using the WordPress image editor stack (GD or Imagick). Originals are moved to a backup directory and can be restored any time.', 'thisismyurl-webp-support' ); ?></p>
                                <hr />
                                <?php if ( ! empty( $restorable ) ) : ?>
                                    <p><strong><?php esc_html_e( 'Bulk Actions', 'thisismyurl-webp-support' ); ?></strong></p>
                                    <button id="btn-restore-all" class="button button-secondary" style="width:100%;text-align:center;" data-ids="<?php echo esc_attr( wp_json_encode( $restorable ) ); ?>">
                                        <?php esc_html_e( 'Restore All Originals', 'thisismyurl-webp-support' ); ?>
                                    </button>
                                    <hr />
                                <?php endif; ?>
                                <p>
                                    <?php
                                    echo wp_kses_post(
                                        sprintf(
                                            __( 'Provided free by %s.', 'thisismyurl-webp-support' ),
                                            '<a href="https://thisismyurl.com/" target="_blank" rel="noopener noreferrer">thisismyurl.com</a>'
                                        )
                                    );
                                    ?>
                                </p>
                                <p><a href="<?php echo esc_url( 'https://thisismyurl.com/donate/' ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer" style="width:100%;text-align:center;"><?php esc_html_e( 'Donate to Development', 'thisismyurl-webp-support' ); ?></a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

/**
 * Minimal direct-PHP filesystem shim used when WP_Filesystem refuses to
 * initialise (typically a host that wants FTP/SSH credentials and gives us
 * no UI to surface them). Implements the methods this plugin actually calls
 * — `exists`, `delete`, `move` — with the same return shapes WP_Filesystem
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

TIMU_WEBP_Support::init();

require_once plugin_dir_path( __FILE__ ) . 'github-updater.php';

timu_boot_github_release_updater(
    array(
        'plugin_file' => __FILE__,
        'slug'        => 'thisismyurl-webp-support',
        'repo'        => 'thisismyurl/thisismyurl-webp-support',
    )
);