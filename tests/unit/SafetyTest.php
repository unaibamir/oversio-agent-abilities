<?php
/**
 * Safety option getters: filterable, bounded, default off/neutral.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Unit;

use AAFM\Tests\TestCase;

final class SafetyTest extends TestCase {

	public function test_safety_getters_default_off(): void {
		delete_option( 'aafm_rate_limit_per_min' );
		delete_option( 'aafm_ip_allowlist' );
		delete_option( 'aafm_force_draft' );
		delete_option( 'aafm_max_title_len' );
		$this->assertSame( 0, aafm_rate_limit_per_min() );
		$this->assertSame( array(), aafm_ip_allowlist() );
		$this->assertFalse( aafm_force_draft() );
		$this->assertSame( 0, aafm_max_title_len() );
	}

	public function test_safety_getters_read_and_bound(): void {
		update_option( 'aafm_rate_limit_per_min', '-5' );      // Clamp to >= 0.
		$this->assertSame( 0, aafm_rate_limit_per_min() );
		update_option( 'aafm_rate_limit_per_min', '30' );
		$this->assertSame( 30, aafm_rate_limit_per_min() );
		update_option( 'aafm_force_draft', '1' );
		$this->assertTrue( aafm_force_draft() );
		update_option( 'aafm_max_title_len', '120' );
		$this->assertSame( 120, aafm_max_title_len() );
		update_option( 'aafm_ip_allowlist', array( '10.0.0.1', '192.168.0.0/24' ) );
		$this->assertSame( array( '10.0.0.1', '192.168.0.0/24' ), aafm_ip_allowlist() );
	}

	public function test_ip_allowlist_normalizes_stored_and_filtered(): void {
		update_option( 'aafm_ip_allowlist', array( '  10.0.0.1  ', '', 0 ) );
		$this->assertSame( array( '10.0.0.1' ), aafm_ip_allowlist() ); // Trimmed, empties dropped.

		$inject = static fn() => array( '  192.168.0.5  ', 7, '' );
		add_filter( 'aafm_ip_allowlist', $inject );
		$this->assertSame( array( '192.168.0.5' ), aafm_ip_allowlist() ); // Filter output re-floored.
		remove_filter( 'aafm_ip_allowlist', $inject );
	}
}
