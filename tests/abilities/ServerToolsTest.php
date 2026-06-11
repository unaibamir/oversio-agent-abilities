<?php
/**
 * Server $tools builder: only enabled AND currently-callable abilities are listed.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;

final class ServerToolsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		// The audited registration wrapper logs to the custom table, so it must exist.
		aafm_install_activity_log();
		aafm_clear_activity_log();

		// Categories first, inside their gated init action (idempotent).
		$this->in_action( 'wp_abilities_api_categories_init', 'aafm_register_categories' );

		// Two fixtures: one any logged-in user can call, one admin-only. The Abilities
		// registry is a process-singleton, so guard against re-registration across tests.
		$this->in_action(
			'wp_abilities_api_init',
			function (): void {
				if ( ! wp_has_ability( 'aafm/pub-read' ) ) {
					aafm_register_ability_with_log(
						'aafm/pub-read',
						array(
							'label'               => 'Pub Read',
							'description'         => 'Anyone may read.',
							'category'            => 'aafm-reads',
							'input_schema'        => array(
								'type'       => 'object',
								'properties' => array(),
							),
							'output_schema'       => array( 'type' => 'object' ),
							'execute_callback'    => static fn() => array(),
							'permission_callback' => '__return_true',
						)
					);
				}
				if ( ! wp_has_ability( 'aafm/admin-write' ) ) {
					aafm_register_ability_with_log(
						'aafm/admin-write',
						array(
							'label'               => 'Admin Write',
							'description'         => 'Admin only.',
							'category'            => 'aafm-writes',
							'input_schema'        => array(
								'type'       => 'object',
								'properties' => array(),
							),
							'output_schema'       => array( 'type' => 'object' ),
							'execute_callback'    => static fn() => array(),
							'permission_callback' => static fn() => current_user_can( 'manage_options' ),
						)
					);
				}
			}
		);

		update_option( 'aafm_enabled_abilities', array( 'aafm/pub-read', 'aafm/admin-write' ) );
	}

	/**
	 * Run a callback as if the named Abilities API init action were firing.
	 *
	 * Core gates wp_register_ability()/wp_register_ability_category() on doing_action();
	 * pushing the action name onto $wp_current_filter is the idiom core's own test trait uses.
	 *
	 * @param string   $action   Init action name.
	 * @param callable $callback Registration callback.
	 */
	private function in_action( string $action, callable $callback ): void {
		global $wp_current_filter;
		$wp_current_filter[] = $action;
		$callback();
		array_pop( $wp_current_filter );
	}

	public function test_subscriber_sees_only_callable_tools(): void {
		$this->acting_as( 'subscriber' );
		$tools = aafm_build_server_tools( array( 'aafm/pub-read', 'aafm/admin-write' ) );
		$this->assertContains( 'aafm/pub-read', $tools );
		$this->assertNotContains( 'aafm/admin-write', $tools );
	}

	public function test_admin_sees_both_tools(): void {
		$this->acting_as( 'administrator' );
		$tools = aafm_build_server_tools( array( 'aafm/pub-read', 'aafm/admin-write' ) );
		$this->assertContains( 'aafm/pub-read', $tools );
		$this->assertContains( 'aafm/admin-write', $tools );
	}

	public function test_registering_the_server_does_not_error(): void {
		$this->acting_as( 'administrator' );
		$adapter = \WP\MCP\Core\McpAdapter::instance();
		// The adapter gates create_server() on the mcp_adapter_init action, so simulate it.
		$this->in_action(
			'mcp_adapter_init',
			static function () use ( $adapter ): void {
				aafm_register_mcp_server( $adapter );
			}
		);
		$this->assertTrue( true );
	}

	public function test_transport_gate_denies_anonymous(): void {
		wp_set_current_user( 0 );
		$result = aafm_transport_permission_callback( new \WP_REST_Request() );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_transport_gate_allows_authenticated(): void {
		$this->acting_as( 'subscriber' );
		$this->assertTrue( aafm_transport_permission_callback( new \WP_REST_Request() ) );
	}
}
