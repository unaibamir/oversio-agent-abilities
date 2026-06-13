<?php
/**
 * Dashboard read-only data helpers: agent user candidates, ability counts,
 * activity total, and the MCP protocol version. No output, no state changes.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Users that hold at least one application password — the accounts an MCP agent
 * could authenticate as. Bounded to a sane page; exposes only id/login/roles and
 * an admin flag, never email, display name, or any password material.
 *
 * @return array<int,array{id:int,login:string,roles:array<int,string>,is_admin:bool}>
 */
function aafm_agent_user_candidates(): array {
	$users = get_users(
		array(
			'number'  => 50,
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'user_login' ),
		)
	);

	$candidates = array();
	foreach ( $users as $user ) {
		$user_id = (int) $user->ID;
		$app_pws = WP_Application_Passwords::get_user_application_passwords( $user_id );
		if ( empty( $app_pws ) ) {
			continue;
		}

		$wp_user = get_userdata( $user_id );
		$roles   = ( $wp_user instanceof WP_User ) ? array_values( $wp_user->roles ) : array();

		$candidates[] = array(
			'id'       => $user_id,
			'login'    => (string) $user->user_login,
			'roles'    => array_map( 'strval', $roles ),
			'is_admin' => user_can( $user_id, 'manage_options' ),
		);
	}

	return $candidates;
}

/**
 * Count of abilities the operator has enabled.
 *
 * @return int
 */
function aafm_enabled_ability_count(): int {
	return count( aafm_get_enabled_abilities() );
}

/**
 * Total abilities in the catalog (enabled or not).
 *
 * @return int
 */
function aafm_total_ability_count(): int {
	return count( aafm_get_abilities_registry() );
}

/**
 * The MCP protocol version this plugin speaks. Single source of truth so other
 * code (help tab, connection configs) can reference it rather than re-literal it.
 *
 * @return string
 */
function aafm_mcp_protocol_version(): string {
	return '2025-06-18';
}

/**
 * Total number of rows in the activity log.
 *
 * @return int Non-negative row count.
 */
function aafm_activity_count(): int {
	global $wpdb;
	// The table name is an internal constant ($wpdb->prefix . 'aafm_activity_log'),
	// never user input; esc_sql() makes that explicit for the static analyzers.
	$table = esc_sql( aafm_activity_log_table() );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

	return max( 0, (int) $count );
}
