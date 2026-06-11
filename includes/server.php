<?php
/**
 * MCP server registration and tool-name helpers.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Mirror the adapter's McpNameSanitizer for display purposes (connect wizard, diagnostics).
 *
 * CONFIRMED against the vendored 0.5.0 source (Phase 0.5.2): the adapter converts '/' -> '-'
 * and keeps hyphens, producing names in the charset ^[A-Za-z0-9_.-]+$. So `aafm/get-posts`
 * becomes `aafm-get-posts`. Removing the slash is the hard blocker we care about; the few
 * client surfaces that also dislike hyphens (some ChatGPT Apps) are a v1.x follow-up — Claude,
 * Cursor, and Windsurf (our v1 targets) accept hyphenated tool names.
 *
 * @param string $ability_name Ability name, e.g. "aafm/get-posts".
 * @return string Sanitized MCP tool name, e.g. "aafm-get-posts".
 */
function aafm_mcp_tool_name( string $ability_name ): string {
	return str_replace( '/', '-', trim( $ability_name ) );
}

/**
 * Build the $tools list for the server: enabled abilities the current user can call.
 *
 * The hard gate remains each ability's own permission_callback at execute time; this
 * list-time filter just avoids advertising tools the connection could never invoke.
 * Phase 0.5.2 confirmed the adapter does NOT permission-filter tools/list and that
 * create_server() runs once per REST request after auth resolves, so this filter is
 * the genuine per-connection guarantee.
 *
 * @param array<int,string> $enabled Enabled ability names.
 * @return list<string>
 */
function aafm_build_server_tools( array $enabled ): array {
	$tools = array();
	foreach ( $enabled as $name ) {
		$ability = wp_get_ability( $name );
		if ( ! $ability instanceof WP_Ability ) {
			continue;
		}
		// If a user is authenticated, only list abilities whose permission check passes.
		if ( is_user_logged_in() ) {
			$ok = $ability->check_permissions( array() );
			if ( true !== $ok ) {
				continue;
			}
		}
		$tools[] = $name;
	}
	return $tools;
}

/**
 * Transport-level gate: require an authenticated user. Per-ability caps do the real work.
 * Named (not inline) so it is unit-testable and PHPStan-visible.
 *
 * @param \WP_REST_Request<array<string,mixed>> $request Incoming request (unused; auth already resolved).
 * @return bool|WP_Error
 */
function aafm_transport_permission_callback( $request ) {
	unset( $request );
	return is_user_logged_in()
		? true
		: new WP_Error( 'aafm_unauthenticated', __( 'Authentication required.', 'agent-abilities-for-mcp' ), array( 'status' => 401 ) );
}

/**
 * Register the single governed MCP server inside mcp_adapter_init.
 *
 * Phase 0.5.1 confirmed the 13-argument create_server() signature and corrected the
 * transport + error-handler FQCNs against the vendored 0.5.0 source.
 *
 * @param \WP\MCP\Core\McpAdapter $adapter Adapter instance.
 * @return void
 */
function aafm_register_mcp_server( $adapter ): void {
	$tools = aafm_build_server_tools( aafm_get_enabled_abilities() );

	$adapter->create_server(
		'aafm-server',
		'agent-abilities-for-mcp',
		'mcp',
		__( 'Agent Abilities for MCP', 'agent-abilities-for-mcp' ),
		__( 'Curated, governed WordPress abilities for AI agents.', 'agent-abilities-for-mcp' ),
		AAFM_VERSION,
		array( \WP\MCP\Transport\HttpTransport::class ),
		\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
		\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
		$tools,
		array(),
		array(),
		'aafm_transport_permission_callback'
	);
}
