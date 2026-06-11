<?php
/**
 * Media abilities (read). The writes (set-featured-image, upload-media) are
 * appended in Phase 4.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_filter( 'aafm_abilities_registry', 'aafm_register_media_definitions' );

/**
 * Contribute media ability definitions to the registry (reads).
 *
 * @param array<string,array<string,mixed>> $registry Registry.
 * @return array<string,array<string,mixed>>
 */
function aafm_register_media_definitions( array $registry ): array {
	$registry['aafm/get-media'] = array(
		'label'        => __( 'Get media', 'agent-abilities-for-mcp' ),
		'description'  => __( 'List media library items (URL, alt, mime, dimensions).', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'args_builder' => 'aafm_args_get_media',
	);
	return $registry;
}

/**
 * Args for aafm/get-media.
 *
 * @return array<string,mixed>
 */
function aafm_args_get_media(): array {
	return array(
		'label'               => __( 'Get media', 'agent-abilities-for-mcp' ),
		'description'         => __( 'List media library items (URL, alt, mime, dimensions).', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'search'   => array(
					'type' => 'string',
				),
				'per_page' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 50,
				),
				'page'     => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'media' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
		),
		'execute_callback'    => 'aafm_exec_get_media',
		'permission_callback' => 'aafm_perm_get_media',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Permission for the media inventory read: upload_files or edit_posts.
 *
 * A bare subscriber (no upload/edit capability) is denied, and the denial is
 * audited by the registration wrapper before any media metadata is read.
 *
 * @return bool
 */
function aafm_perm_get_media(): bool {
	return current_user_can( 'upload_files' ) || current_user_can( 'edit_posts' );
}

/**
 * Execute aafm/get-media.
 *
 * Lists attachments in the media library, redacted to a safe inventory shape:
 * id, title, mime type, the public URL, alt text, and dimensions. The absolute
 * server file path (get_attached_file / _wp_attached_file) and any uploader PII
 * are never returned.
 *
 * @param array<string,mixed> $input Validated input.
 * @return array<string,mixed>
 */
function aafm_exec_get_media( array $input ): array {
	$paging = aafm_paginate_args( $input, 50 );
	$query  = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			's'              => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
			'posts_per_page' => $paging['per_page'],
			'paged'          => $paging['page'],
		)
	);

	// WP_Query->posts is typed array<int,int|WP_Post>; keep only WP_Post before redacting.
	$attachments = array_filter(
		$query->posts,
		static fn( $post ): bool => $post instanceof WP_Post
	);

	return array( 'media' => array_values( array_map( 'aafm_redact_media', $attachments ) ) );
}
