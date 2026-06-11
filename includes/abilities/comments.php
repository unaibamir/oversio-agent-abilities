<?php
/**
 * Comment abilities (reads). The moderation write is appended in Phase 4.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_filter( 'aafm_abilities_registry', 'aafm_register_comments_definitions' );

/**
 * Contribute comment ability definitions to the registry (reads).
 *
 * @param array<string,array<string,mixed>> $registry Registry.
 * @return array<string,array<string,mixed>>
 */
function aafm_register_comments_definitions( array $registry ): array {
	$registry['aafm/get-comments']         = array(
		'label'        => __( 'Get comments', 'agent-abilities-for-mcp' ),
		'description'  => __( 'List approved comments for a post (email and IP are never returned).', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'args_builder' => 'aafm_args_get_comments',
	);
	$registry['aafm/get-pending-comments'] = array(
		'label'        => __( 'Get pending comments', 'agent-abilities-for-mcp' ),
		'description'  => __( 'List the moderation queue (requires moderate_comments).', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'args_builder' => 'aafm_args_get_pending_comments',
	);
	return $registry;
}

/**
 * Args for aafm/get-comments.
 *
 * @return array<string,mixed>
 */
function aafm_args_get_comments(): array {
	return array(
		'label'               => __( 'Get comments', 'agent-abilities-for-mcp' ),
		'description'         => __( 'List approved comments for a post (email and IP are never returned).', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'  => array(
					'type'    => 'integer',
					'minimum' => 1,
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
				'comments' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
		),
		'execute_callback'    => 'aafm_exec_get_comments',
		'permission_callback' => 'aafm_perm_read',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Execute aafm/get-comments.
 *
 * Returns APPROVED comments only. The status filter is pinned server-side so a
 * low-privilege caller can never reach a pending, spam, or trashed comment body
 * through this ability — unapproved bodies are only available via
 * aafm/get-pending-comments, which requires moderate_comments.
 *
 * @param array<string,mixed> $input Validated input.
 * @return array<string,mixed>
 */
function aafm_exec_get_comments( array $input ): array {
	$paging   = aafm_paginate_args( $input, 50 );
	$comments = get_comments(
		array(
			'post_id' => isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0,
			'status'  => 'approve',
			'number'  => $paging['per_page'],
			'paged'   => $paging['page'],
		)
	);

	return array( 'comments' => aafm_redact_comments( $comments ) );
}

/**
 * Redact a list of comment results to safe public shapes.
 *
 * A get_comments() result can be comment IDs or objects depending on the query;
 * keep only WP_Comment instances before redacting so no partial record leaks.
 *
 * @param mixed $comments Result of get_comments().
 * @return array<int,array<string,mixed>>
 */
function aafm_redact_comments( $comments ): array {
	$objects = array_filter(
		(array) $comments,
		static fn( $comment ): bool => $comment instanceof WP_Comment
	);

	return array_values( array_map( 'aafm_redact_comment', $objects ) );
}

/**
 * Args for aafm/get-pending-comments.
 *
 * @return array<string,mixed>
 */
function aafm_args_get_pending_comments(): array {
	return array(
		'label'               => __( 'Get pending comments', 'agent-abilities-for-mcp' ),
		'description'         => __( 'List the moderation queue (requires moderate_comments).', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
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
				'comments' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
		),
		'execute_callback'    => 'aafm_exec_get_pending_comments',
		'permission_callback' => 'aafm_perm_moderate_comments',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Permission for the moderation queue: moderate_comments.
 *
 * This is the only gate to unapproved comment bodies. A caller without
 * moderate_comments is denied (and the denial is audited by the registration
 * wrapper) before any pending/spam content is read.
 *
 * @return bool
 */
function aafm_perm_moderate_comments(): bool {
	return current_user_can( 'moderate_comments' );
}

/**
 * Execute aafm/get-pending-comments (moderation queue).
 *
 * @param array<string,mixed> $input Validated input.
 * @return array<string,mixed>
 */
function aafm_exec_get_pending_comments( array $input ): array {
	$paging   = aafm_paginate_args( $input, 50 );
	$comments = get_comments(
		array(
			'status' => 'hold',
			'number' => $paging['per_page'],
			'paged'  => $paging['page'],
		)
	);

	return array( 'comments' => aafm_redact_comments( $comments ) );
}
