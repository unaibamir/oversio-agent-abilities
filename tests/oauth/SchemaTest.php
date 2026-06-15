<?php
/**
 * Tests for the OAuth storage schema installer.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\OAuth;

use AAFM\Tests\TestCase;

/**
 * Verifies the four OAuth tables install idempotently and record their schema version.
 */
class SchemaTest extends TestCase {

	/**
	 * Whether a plugin table exists for the current blog.
	 *
	 * The WordPress test suite rewrites every plugin `CREATE TABLE` / `DROP TABLE`
	 * to its `TEMPORARY` form so each test gets an isolated, rolled-back table.
	 * `SHOW TABLES` does not list temporary tables, so existence is probed with a
	 * trivial select instead, which sees the temporary table the same way the
	 * plugin's own queries do.
	 *
	 * @param string $suffix Unprefixed table suffix (e.g. 'aafm_oauth_clients').
	 * @return bool
	 */
	private function table_exists( string $suffix ): bool {
		global $wpdb;
		$table      = $wpdb->prefix . $suffix;
		$suppressed = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "SELECT 1 FROM {$table} LIMIT 0" );
		$error = $wpdb->last_error;
		$wpdb->suppress_errors( $suppressed );
		return '' === $error;
	}

	/**
	 * Installing creates all four OAuth tables and records the schema version.
	 */
	public function test_install_creates_all_four_tables(): void {
		aafm_install_oauth_tables();

		$this->assertTrue( $this->table_exists( 'aafm_oauth_clients' ) );
		$this->assertTrue( $this->table_exists( 'aafm_oauth_codes' ) );
		$this->assertTrue( $this->table_exists( 'aafm_oauth_access_tokens' ) );
		$this->assertTrue( $this->table_exists( 'aafm_oauth_consents' ) );

		$this->assertNotEmpty( get_option( 'aafm_oauth_schema_version' ) );
	}

	/**
	 * Installing twice is a no-op the second time (no error, tables still present).
	 */
	public function test_install_is_idempotent(): void {
		aafm_install_oauth_tables();
		aafm_install_oauth_tables();

		$this->assertTrue( $this->table_exists( 'aafm_oauth_clients' ) );
		$this->assertTrue( $this->table_exists( 'aafm_oauth_codes' ) );
		$this->assertTrue( $this->table_exists( 'aafm_oauth_access_tokens' ) );
		$this->assertTrue( $this->table_exists( 'aafm_oauth_consents' ) );
	}
}
