<?php
/**
 * Transport-gate safety enforcement: the IP allowlist denies blocked addresses
 * (audited) while leaving the logged-in and unauthenticated paths intact.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;

final class SafetyEnforcementTest extends TestCase {

	/**
	 * Saved REMOTE_ADDR so each test restores the fixture's request environment.
	 *
	 * @var string|null
	 */
	private $original_remote_addr;

	public function set_up(): void {
		parent::set_up();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$this->original_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;

		// The transport denial path writes a 'denied' row to the custom log.
		aafm_install_activity_log();
		aafm_clear_activity_log();
		$this->ensure_categories();
	}

	public function tear_down(): void {
		if ( null === $this->original_remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}
		parent::tear_down();
	}

	public function test_transport_blocks_disallowed_ip_and_audits(): void {
		$uid = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $uid );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		update_option( 'aafm_ip_allowlist', array( '10.0.0.0/8' ) );

		$result = aafm_transport_permission_callback( null );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 403, $result->get_error_data()['status'] ?? 0 );

		$denied = aafm_query_activity(
			array(
				'status'   => 'denied',
				'per_page' => 1,
			)
		);
		$this->assertNotEmpty( $denied );
		$this->assertSame( 'denied', $denied[0]['status'] );
		$this->assertSame( '(transport)', $denied[0]['ability'] );
	}

	public function test_transport_allows_listed_ip(): void {
		$uid = self::factory()->user->create();
		wp_set_current_user( $uid );
		$_SERVER['REMOTE_ADDR'] = '10.1.2.3';
		update_option( 'aafm_ip_allowlist', array( '10.0.0.0/8' ) );
		$this->assertTrue( aafm_transport_permission_callback( null ) );
	}

	public function test_transport_empty_allowlist_allows_any_ip(): void {
		$uid = self::factory()->user->create();
		wp_set_current_user( $uid );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
		update_option( 'aafm_ip_allowlist', array() );
		$this->assertTrue( aafm_transport_permission_callback( null ) );
	}

	public function test_transport_unauthenticated_still_401_regardless_of_ip(): void {
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '10.1.2.3'; // Would be allowed if it mattered.
		update_option( 'aafm_ip_allowlist', array( '10.0.0.0/8' ) );
		$result = aafm_transport_permission_callback( null );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] ?? 0 );
	}

	/**
	 * Register the plugin categories inside a simulated categories-init action.
	 *
	 * The Abilities API only permits category registration while the
	 * 'wp_abilities_api_categories_init' action is running; aafm_register_categories()
	 * is idempotent, so this is safe to call before every test.
	 */
	private function ensure_categories(): void {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		aafm_register_categories();
		array_pop( $wp_current_filter );
	}

	/**
	 * Register an ability through the wrapper inside a simulated abilities-init action.
	 *
	 * @param string              $name Ability name.
	 * @param array<string,mixed> $args Ability args.
	 * @return mixed The wrapper return value (WP_Ability or null).
	 */
	private function register( string $name, array $args ) {
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_init';
		$result              = aafm_register_ability_with_log( $name, $args );
		array_pop( $wp_current_filter );
		return $result;
	}

	public function test_decorated_permission_rate_limits_and_audits(): void {
		$uid = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $uid );
		update_option( 'aafm_rate_limit_per_min', 1 );

		$this->register(
			'aafm/rl-probe',
			array(
				'label'               => 'RL Probe',
				'description'         => 'Throwaway ability for rate-limit testing.',
				'category'            => 'aafm-reads',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => '__return_empty_array',
				'permission_callback' => '__return_true',
			)
		);
		$ability = wp_get_ability( 'aafm/rl-probe' );

		$this->assertTrue( $ability->check_permissions( array() ) );  // 1st: under limit.
		$this->assertFalse( $ability->check_permissions( array() ) ); // 2nd: over limit -> false.

		$denied = aafm_query_activity(
			array(
				'status'   => 'denied',
				'per_page' => 1,
			)
		);
		$this->assertNotEmpty( $denied );
		$this->assertSame( 'denied', $denied[0]['status'] );
		$this->assertSame( 'aafm/rl-probe', $denied[0]['ability'] );
	}

	public function test_rate_limit_off_decorator_is_no_op(): void {
		$uid = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $uid );
		update_option( 'aafm_rate_limit_per_min', 0 ); // Zero is the default and disables the limit.

		$this->register(
			'aafm/rl-off-probe',
			array(
				'label'               => 'RL Off Probe',
				'description'         => 'Throwaway.',
				'category'            => 'aafm-reads',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => '__return_empty_array',
				'permission_callback' => '__return_true',
			)
		);
		$ability = wp_get_ability( 'aafm/rl-off-probe' );

		// Many calls, all allowed — off means no token consumption, identical to today.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( $ability->check_permissions( array() ) );
		}
	}

	public function test_discovery_does_not_consume_a_rate_token(): void {
		// The RAW permission (used by tools/list) must NOT consume a token, so a real
		// ability call afterwards still gets its full allowance.
		$uid = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $uid );
		update_option( 'aafm_rate_limit_per_min', 1 );

		$this->register(
			'aafm/rl-disc-probe',
			array(
				'label'               => 'RL Disc Probe',
				'description'         => 'Throwaway.',
				'category'            => 'aafm-reads',
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => '__return_empty_array',
				'permission_callback' => '__return_true',
			)
		);

		// Simulate discovery: call the RAW permission the way the tools/list filter does.
		$raw = aafm_remember_raw_permission( 'aafm/rl-disc-probe' );
		$this->assertTrue( (bool) $raw( array() ) ); // discovery visibility check — must NOT consume a token.
		$this->assertTrue( (bool) $raw( array() ) ); // again — still no token.

		// Now the FIRST real (decorated) call still has its full allowance of 1.
		$ability = wp_get_ability( 'aafm/rl-disc-probe' );
		$this->assertTrue( $ability->check_permissions( array() ) );  // 1st real call allowed.
		$this->assertFalse( $ability->check_permissions( array() ) ); // 2nd real call over limit.
	}
}
