<?php
/**
 * WP-CLI commands for WEBP Support by thisismyurl.com.
 *
 * Registered via `WP_CLI::add_command( 'webp', 'TIMU_WEBP_Support_CLI' )`
 * inside TIMU_WEBP_Support::init() when WP-CLI is loaded.
 *
 * @package TIMU_WEBP_Support
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_CLI_Command' ) ) {
    return;
}

/**
 * Manage WebP conversion and restoration of Media Library attachments.
 */
class TIMU_WEBP_Support_CLI extends WP_CLI_Command {

    /**
     * Convert one or more attachments to WebP.
     *
     * ## OPTIONS
     *
     * [<id>...]
     * : One or more attachment IDs to convert. Mutually exclusive with --all.
     *
     * [--all]
     * : Convert every pending attachment in the Media Library.
     *
     * [--dry-run]
     * : Print what would be converted without doing the work.
     *
     * ## EXAMPLES
     *
     *     wp webp convert 42 43 44
     *     wp webp convert --all
     *     wp webp convert --all --dry-run
     *
     * @param array $args       Positional arguments (attachment IDs).
     * @param array $assoc_args Associative arguments.
     *
     * @return void
     */
    public function convert( $args, $assoc_args ) {
        $all     = ! empty( $assoc_args['all'] );
        $dry_run = ! empty( $assoc_args['dry-run'] );

        if ( $all && ! empty( $args ) ) {
            WP_CLI::error( '--all and explicit IDs are mutually exclusive.' );
        }

        if ( $all ) {
            $lists = TIMU_WEBP_Support::get_media_lists();
            $ids   = array_map(
                static function ( $post ) {
                    return (int) $post->ID;
                },
                $lists['pending']
            );
        } else {
            $ids = array_filter( array_map( 'absint', $args ) );
        }

        if ( empty( $ids ) ) {
            WP_CLI::warning( 'No attachments to convert.' );
            return;
        }

        WP_CLI::log( sprintf( 'Converting %d attachment(s)%s.', count( $ids ), $dry_run ? ' [dry-run]' : '' ) );

        $progress = \WP_CLI\Utils\make_progress_bar( 'Converting', count( $ids ) );
        $ok       = 0;
        $failed   = 0;

        foreach ( $ids as $id ) {
            if ( $dry_run ) {
                WP_CLI::log( sprintf( '  would convert #%d (%s)', $id, basename( (string) get_attached_file( $id ) ) ) );
                ++$ok;
                $progress->tick();
                continue;
            }

            $result = TIMU_WEBP_Support::convert_to_webp( $id );
            if ( true === $result ) {
                ++$ok;
            } else {
                ++$failed;
                $message = is_wp_error( $result ) ? $result->get_error_message() : 'unknown error';
                WP_CLI::warning( sprintf( '#%d: %s', $id, $message ) );
            }
            $progress->tick();
        }

        $progress->finish();

        if ( $failed > 0 ) {
            WP_CLI::error( sprintf( 'Converted %d, failed %d.', $ok, $failed ), false );
            WP_CLI::halt( 1 );
        }

        WP_CLI::success( sprintf( 'Converted %d attachment(s).', $ok ) );
    }

    /**
     * Restore one or more converted attachments from backup.
     *
     * ## OPTIONS
     *
     * [<id>...]
     * : One or more attachment IDs to restore. Mutually exclusive with --all.
     *
     * [--all]
     * : Restore every managed attachment that has a backup on disk.
     *
     * [--dry-run]
     * : Print what would be restored without doing the work.
     *
     * ## EXAMPLES
     *
     *     wp webp restore 42
     *     wp webp restore --all
     *     wp webp restore --all --dry-run
     *
     * @param array $args       Positional arguments (attachment IDs).
     * @param array $assoc_args Associative arguments.
     *
     * @return void
     */
    public function restore( $args, $assoc_args ) {
        $all     = ! empty( $assoc_args['all'] );
        $dry_run = ! empty( $assoc_args['dry-run'] );

        if ( $all && ! empty( $args ) ) {
            WP_CLI::error( '--all and explicit IDs are mutually exclusive.' );
        }

        if ( $all ) {
            $lists = TIMU_WEBP_Support::get_media_lists();
            $ids   = array();
            foreach ( $lists['media'] as $post ) {
                $orig = get_post_meta( $post->ID, TIMU_WEBP_Support::BACKUP_META_KEY, true );
                if ( $orig && 'external' !== $orig ) {
                    $ids[] = (int) $post->ID;
                }
            }
        } else {
            $ids = array_filter( array_map( 'absint', $args ) );
        }

        if ( empty( $ids ) ) {
            WP_CLI::warning( 'No attachments to restore.' );
            return;
        }

        WP_CLI::log( sprintf( 'Restoring %d attachment(s)%s.', count( $ids ), $dry_run ? ' [dry-run]' : '' ) );

        $progress = \WP_CLI\Utils\make_progress_bar( 'Restoring', count( $ids ) );
        $ok       = 0;
        $failed   = 0;

        foreach ( $ids as $id ) {
            if ( $dry_run ) {
                WP_CLI::log( sprintf( '  would restore #%d', $id ) );
                ++$ok;
                $progress->tick();
                continue;
            }

            if ( TIMU_WEBP_Support::restore_image( $id ) ) {
                ++$ok;
            } else {
                ++$failed;
                WP_CLI::warning( sprintf( '#%d: restore failed (no backup on disk?).', $id ) );
            }
            $progress->tick();
        }

        $progress->finish();

        if ( $failed > 0 ) {
            WP_CLI::error( sprintf( 'Restored %d, failed %d.', $ok, $failed ), false );
            WP_CLI::halt( 1 );
        }

        WP_CLI::success( sprintf( 'Restored %d attachment(s).', $ok ) );
    }

    /**
     * Report pending, managed, and restorable attachment counts.
     *
     * ## EXAMPLES
     *
     *     wp webp status
     *
     * @return void
     */
    public function status() {
        $lists      = TIMU_WEBP_Support::get_media_lists();
        $pending    = count( $lists['pending'] );
        $managed    = count( $lists['media'] );
        $restorable = 0;
        $missing    = 0;

        foreach ( $lists['media'] as $post ) {
            if ( isset( $post->timu_wsstatus ) && 'missing' === $post->timu_wsstatus ) {
                ++$missing;
                continue;
            }
            $orig = get_post_meta( $post->ID, TIMU_WEBP_Support::BACKUP_META_KEY, true );
            if ( $orig && 'external' !== $orig ) {
                ++$restorable;
            }
        }

        WP_CLI::log( sprintf( 'Pending:    %d', $pending ) );
        WP_CLI::log( sprintf( 'Managed:    %d', $managed ) );
        WP_CLI::log( sprintf( 'Restorable: %d', $restorable ) );
        WP_CLI::log( sprintf( 'Missing:    %d', $missing ) );
    }
}
