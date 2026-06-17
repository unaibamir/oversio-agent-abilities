<?php
/**
 * Reusable-block (wp_block) read + write abilities.
 *
 * The wp_block post type is behind the Reusable Blocks / synced patterns. It is NOT in the
 * content post-type allowlist, so the shared content-write helpers (which gate on that
 * allowlist) refuse it. These abilities gate per-object directly on the wp_block edit/delete
 * meta caps (edit_block/delete_block) instead, with edit_posts/delete_posts as the
 * object-independent discovery floor. Block markup is hardened with wp_kses_post() before
 * insert — verified to PRESERVE Gutenberg block delimiters and their JSON attributes.
 *
 * delete-block uses the Trash (wp_trash_post), guarded against trash-disabled sites, so no
 * force-delete primitive is added.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_filter( 'aafm_abilities_registry', 'aafm_register_blocks_definitions' );

/**
 * Register the reusable-block ability definitions.
 *
 * @param array<string,array<string,mixed>> $registry Registry.
 * @return array<string,array<string,mixed>>
 */
function aafm_register_blocks_definitions( array $registry ): array {
	$registry['aafm/list-blocks'] = array(
		'label'        => __( 'List blocks', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Lists reusable blocks (synced patterns) by id, title, slug, status, and modified date. No block markup in the list. Requires the edit-posts capability.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_list_blocks',
	);
	$registry['aafm/get-block']   = array(
		'label'        => __( 'Get block', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Reads one reusable block by id, including its raw block markup. Requires edit access to that block.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'subject'      => 'site',
		'args_builder' => 'aafm_args_get_block',
	);
	// create-block, update-block, and delete-block are registered in their own tasks
	// (B3/B4) so each lands with its execute callback and tests in lockstep.
	return $registry;
}

/**
 * Object-independent floor for block reads/creates: the Block editor authoring cap.
 *
 * @return bool
 */
function aafm_perm_blocks_floor(): bool {
	return current_user_can( 'edit_posts' );
}

/**
 * Per-object permission: the caller may edit THIS wp_block (edit_block meta cap).
 *
 * @param array<string,mixed> $input Input carrying block_id.
 * @return bool
 */
function aafm_perm_block_object( array $input ): bool {
	$id    = absint( $input['block_id'] ?? 0 );
	$block = aafm_get_block_object( $id );
	return null !== $block && current_user_can( 'edit_post', $id );
}

/**
 * Args for list-blocks.
 *
 * @return array<string,mixed>
 */
function aafm_args_list_blocks(): array {
	return array(
		'label'               => __( 'List blocks', 'agent-abilities-for-mcp' ),
		'description'         => __( 'Lists reusable blocks. No markup in the list. Requires edit-posts.', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'page'     => array( 'type' => 'integer' ),
				'per_page' => array( 'type' => 'integer' ),
				'search'   => array( 'type' => 'string' ),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'blocks' => array( 'type' => 'array' ),
				'total'  => array( 'type' => 'integer' ),
			),
		),
		'execute_callback'    => 'aafm_exec_list_blocks',
		'permission_callback' => 'aafm_perm_blocks_floor',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Execute list-blocks: lean rows, paginated, no markup.
 *
 * @param array<string,mixed> $input Input.
 * @return array<string,mixed>
 */
function aafm_exec_list_blocks( array $input ): array {
	$per_page = isset( $input['per_page'] ) ? min( 100, max( 1, (int) $input['per_page'] ) ) : 20;
	$query    = new WP_Query(
		array(
			'post_type'      => 'wp_block',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => $per_page,
			'paged'          => isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1,
			's'              => isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '',
		)
	);
	$blocks   = array();
	foreach ( $query->posts as $block ) {
		if ( $block instanceof WP_Post ) {
			$blocks[] = aafm_redact_block( $block );
		}
	}
	return array(
		'blocks' => $blocks,
		'total'  => (int) $query->found_posts,
	);
}

/**
 * Args for get-block.
 *
 * @return array<string,mixed>
 */
function aafm_args_get_block(): array {
	return array(
		'label'               => __( 'Get block', 'agent-abilities-for-mcp' ),
		'description'         => __( 'Reads one reusable block by id, including its raw markup. Requires edit access to that block.', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array( 'block_id' => array( 'type' => 'integer' ) ),
			'required'             => array( 'block_id' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'id'      => array( 'type' => 'integer' ),
				'content' => array( 'type' => 'string' ),
			),
		),
		'execute_callback'    => 'aafm_exec_get_block',
		'permission_callback' => 'aafm_perm_block_object',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Execute get-block: the rich single-block shape (markup included).
 *
 * @param array<string,mixed> $input Input.
 * @return array<string,mixed>|WP_Error
 */
function aafm_exec_get_block( array $input ) {
	$block = aafm_get_block_object( absint( $input['block_id'] ?? 0 ) );
	if ( null === $block ) {
		return aafm_generic_error();
	}
	return aafm_rich_block( $block );
}
