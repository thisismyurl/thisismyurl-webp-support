<?php
/**
 * WP 7 Abilities API registration for WEBP Support by thisismyurl.com.
 *
 * Exposes the plugin's non-destructive batch conversion and one-click
 * restore operations as discoverable, REST/AI-invokable abilities. Both
 * wrap the same static methods the WP-CLI commands call
 * (TIMU_WEBP_Support::convert_to_webp() / ::restore_image()), so there is
 * one code path per operation regardless of the invoking surface.
 *
 * @package TIMU_WEBP_Support
 * @since   0.6149
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // Abilities API unavailable (WordPress < 6.9).
		}

		wp_register_ability(
			'thisismyurl-webp-support/convert',
			array(
				'label'               => __( 'Convert Media Library images to WebP/AVIF', 'thisismyurl-webp-support' ),
				'description'         => __( 'Batch-converts pending Media Library images to the format configured in the plugin settings (WebP, AVIF, or both). Originals are backed up before conversion, so the operation is reversible via the restore ability. Use this to shrink image payloads across the library.', 'thisismyurl-webp-support' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Optional: maximum number of pending attachments to convert in this batch. If omitted, every pending attachment is converted.', 'thisismyurl-webp-support' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'required'             => array( 'converted', 'skipped', 'errors', 'space_saved_bytes' ),
					'properties'           => array(
						'converted'         => array(
							'type'        => 'integer',
							'description' => __( 'Number of attachments successfully converted in this batch.', 'thisismyurl-webp-support' ),
						),
						'skipped'           => array(
							'type'        => 'integer',
							'description' => __( 'Number of pending attachments left unprocessed because the limit was reached.', 'thisismyurl-webp-support' ),
						),
						'errors'            => array(
							'type'        => 'integer',
							'description' => __( 'Number of attachments that failed to convert.', 'thisismyurl-webp-support' ),
						),
						'space_saved_bytes' => array(
							'type'        => 'integer',
							'description' => __( 'Total bytes saved across the attachments converted in this batch.', 'thisismyurl-webp-support' ),
						),
						'error_messages'    => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
							'description' => __( 'Human-readable failure messages, one per failed attachment.', 'thisismyurl-webp-support' ),
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input = array() ) {
					if ( ! class_exists( 'TIMU_WEBP_Support' ) ) {
						return new WP_Error( 'timu_webp_unavailable', __( 'WEBP Support plugin is not loaded.', 'thisismyurl-webp-support' ) );
					}

					$input = is_array( $input ) ? $input : array();
					$limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : 0;

					$lists = TIMU_WEBP_Support::get_media_lists();
					$ids   = array_map(
						static function ( $post ) {
							return (int) $post->ID;
						},
						$lists['pending']
					);

					$skipped = 0;
					if ( $limit > 0 && count( $ids ) > $limit ) {
						$skipped = count( $ids ) - $limit;
						$ids     = array_slice( $ids, 0, $limit );
					}

					$converted          = 0;
					$errors             = 0;
					$space_saved_bytes  = 0;
					$error_messages     = array();

					foreach ( $ids as $id ) {
						$result = TIMU_WEBP_Support::convert_to_webp( $id );

						if ( true === $result ) {
							++$converted;
							$space_saved_bytes += (int) get_post_meta( $id, TIMU_WEBP_Support::SAVINGS_META_KEY, true );
							continue;
						}

						++$errors;
						$error_messages[] = is_wp_error( $result )
							? sprintf( '#%d: %s', $id, $result->get_error_message() )
							: sprintf( '#%d: %s', $id, __( 'unknown error', 'thisismyurl-webp-support' ) );
					}

					return array(
						'converted'         => $converted,
						'skipped'           => $skipped,
						'errors'            => $errors,
						'space_saved_bytes' => $space_saved_bytes,
						'error_messages'    => $error_messages,
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false, // Writes new image files and post meta.
						'destructive' => false, // Originals are backed up before conversion; restore reverses it. Non-destructive by design.
						'idempotent'  => false, // Each call converts whatever is pending; repeating with new pending items does more work.
					),
					'show_in_rest' => true, // Cap-guarded and non-destructive (originals are preserved).
				),
			)
		);

		wp_register_ability(
			'thisismyurl-webp-support/restore',
			array(
				'label'               => __( 'Restore original images from backups', 'thisismyurl-webp-support' ),
				'description'         => __( 'Restores every managed Media Library attachment that has a backup on disk, replacing the converted WebP/AVIF file with the original. Use this to roll back a conversion across the library.', 'thisismyurl-webp-support' ),
				'category'            => 'site',
				'output_schema'       => array(
					'type'                 => 'object',
					'required'             => array( 'restored', 'errors' ),
					'properties'           => array(
						'restored' => array(
							'type'        => 'integer',
							'description' => __( 'Number of attachments successfully restored from backup.', 'thisismyurl-webp-support' ),
						),
						'errors'   => array(
							'type'        => 'integer',
							'description' => __( 'Number of attachments that could not be restored (for example, no backup on disk).', 'thisismyurl-webp-support' ),
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function () {
					if ( ! class_exists( 'TIMU_WEBP_Support' ) ) {
						return new WP_Error( 'timu_webp_unavailable', __( 'WEBP Support plugin is not loaded.', 'thisismyurl-webp-support' ) );
					}

					$lists    = TIMU_WEBP_Support::get_media_lists();
					$restored = 0;
					$errors   = 0;

					foreach ( $lists['media'] as $post ) {
						$orig = get_post_meta( $post->ID, TIMU_WEBP_Support::BACKUP_META_KEY, true );
						if ( ! $orig || 'external' === $orig ) {
							continue; // No restorable backup for this attachment.
						}

						if ( TIMU_WEBP_Support::restore_image( (int) $post->ID ) ) {
							++$restored;
						} else {
							++$errors;
						}
					}

					return array(
						'restored' => $restored,
						'errors'   => $errors,
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false, // Overwrites converted files with the originals and clears backup meta.
						'destructive' => true,  // Overwrites the current converted state of each attachment.
						'idempotent'  => true,  // After a restore the backup meta is cleared, so a second run finds nothing to restore: same end state.
					),
					'show_in_rest' => true, // Rule 7: fully guarded by a logged-in manage_options check, so REST exposure is acceptable.
				),
			)
		);
	}
);
