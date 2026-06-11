<?php
/**
 * Page abilities (reads). Writes are appended in Phase 4.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_filter( 'aafm_abilities_registry', 'aafm_register_pages_definitions' );

/**
 * Contribute page ability definitions to the registry.
 *
 * @param array<string,array<string,mixed>> $registry Registry.
 * @return array<string,array<string,mixed>>
 */
function aafm_register_pages_definitions( array $registry ): array {
	$registry['aafm/get-pages'] = array(
		'label'        => __( 'Get pages', 'agent-abilities-for-mcp' ),
		'description'  => __( 'List pages filtered by status and search term.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'args_builder' => 'aafm_args_get_pages',
	);
	$registry['aafm/get-page']  = array(
		'label'        => __( 'Get page', 'agent-abilities-for-mcp' ),
		'description'  => __( 'Retrieve a single page by ID.', 'agent-abilities-for-mcp' ),
		'group'        => 'reads',
		'risk'         => 'read',
		'args_builder' => 'aafm_args_get_page',
	);
	return $registry;
}

/**
 * Args for aafm/get-pages.
 *
 * @return array<string,mixed>
 */
function aafm_args_get_pages(): array {
	return array(
		'label'               => __( 'Get pages', 'agent-abilities-for-mcp' ),
		'description'         => __( 'List pages filtered by status and search term.', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'status'   => array(
					'type'    => 'string',
					'default' => 'publish',
				),
				'search'   => array( 'type' => 'string' ),
				'page'     => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'per_page' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 50,
				),
			),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array(
				'posts' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'total' => array( 'type' => 'integer' ),
			),
		),
		'execute_callback'    => 'aafm_exec_get_pages',
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
 * Execute aafm/get-pages — delegates to the post query with post_type forced to page.
 *
 * @param array<string,mixed> $input Validated input.
 * @return array<string,mixed>|WP_Error
 */
function aafm_exec_get_pages( array $input ) {
	$input['post_type'] = 'page';
	return aafm_exec_get_posts( $input );
}

/**
 * Args for aafm/get-page.
 *
 * @return array<string,mixed>
 */
function aafm_args_get_page(): array {
	return array(
		'label'               => __( 'Get page', 'agent-abilities-for-mcp' ),
		'description'         => __( 'Retrieve a single page by ID.', 'agent-abilities-for-mcp' ),
		'category'            => 'aafm-reads',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'page_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'required'             => array( 'page_id' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array(
			'type'       => 'object',
			'properties' => array( 'post' => array( 'type' => 'object' ) ),
		),
		'execute_callback'    => 'aafm_exec_get_page',
		'permission_callback' => 'aafm_perm_get_page',
		'meta'                => array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
			),
		),
	);
}

/**
 * Permission for aafm/get-page: read, plus per-object edit for non-public pages.
 *
 * @param array<string,mixed> $input Input.
 * @return bool
 */
function aafm_perm_get_page( array $input ): bool {
	if ( ! current_user_can( 'read' ) ) {
		return false;
	}
	$id   = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
	$post = $id ? get_post( $id ) : null;
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}
	$public_statuses = get_post_stati( array( 'public' => true ) );
	if ( in_array( $post->post_status, $public_statuses, true ) ) {
		return true;
	}
	return current_user_can( 'edit_page', $id );
}

/**
 * Execute aafm/get-page.
 *
 * @param array<string,mixed> $input Input.
 * @return array<string,mixed>|WP_Error
 */
function aafm_exec_get_page( array $input ) {
	$id   = absint( $input['page_id'] );
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return aafm_generic_error();
	}
	return array( 'post' => aafm_redact_post( $post ) );
}
