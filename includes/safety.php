<?php
/**
 * Safety option getters: filterable, bounded, default off/neutral.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Requests-per-minute rate limit. 0 (the default) means no limit.
 *
 * @return int Clamped to >= 0.
 */
function aafm_rate_limit_per_min(): int {
	/**
	 * Filters the requests-per-minute rate limit. 0 means no limit.
	 *
	 * @param int $limit Stored limit, clamped to >= 0.
	 */
	return (int) apply_filters( 'aafm_rate_limit_per_min', max( 0, (int) get_option( 'aafm_rate_limit_per_min', 0 ) ) );
}

/**
 * IP allowlist. Empty (the default) means no IP restriction.
 *
 * @return array<int, string> Trimmed, non-empty entries.
 */
function aafm_ip_allowlist(): array {
	$normalize = static fn( $entries ): array => array_values(
		array_filter(
			array_map( 'trim', array_filter( (array) $entries, 'is_string' ) )
		)
	);

	$stored = $normalize( get_option( 'aafm_ip_allowlist', array() ) );

	/**
	 * Filters the IP/CIDR allowlist for the MCP endpoint.
	 *
	 * @param array<int, string> $stored Trimmed, non-empty entries.
	 */
	return $normalize( apply_filters( 'aafm_ip_allowlist', $stored ) );
}

/**
 * Whether agent-created content is forced to draft. Off by default.
 *
 * @return bool
 */
function aafm_force_draft(): bool {
	/**
	 * Filters whether agent-created content is forced to draft.
	 *
	 * @param bool $force True to force draft status.
	 */
	return (bool) apply_filters( 'aafm_force_draft', (bool) get_option( 'aafm_force_draft', false ) );
}

/**
 * Maximum allowed title length. 0 (the default) means no cap.
 *
 * @return int Clamped to >= 0.
 */
function aafm_max_title_len(): int {
	/**
	 * Filters the maximum allowed title length. 0 means no cap.
	 *
	 * @param int $len Stored cap, clamped to >= 0.
	 */
	return (int) apply_filters( 'aafm_max_title_len', max( 0, (int) get_option( 'aafm_max_title_len', 0 ) ) );
}
