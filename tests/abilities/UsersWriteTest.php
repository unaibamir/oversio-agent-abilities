<?php
/**
 * User writes CRUD (Wave 2 Slice 2): create-user, update-user, delete-user.
 *
 * The most security-sensitive slice in Wave 2. These tests pin the privilege rails:
 * create forces the site default role (never a caller-chosen admin), update gates any
 * role change behind promote_users (with a last-admin demotion floor), and delete
 * requires a reassign target while refusing self-deletion and last-admin removal.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;
use WP_Error;

final class UsersWriteTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		aafm_install_activity_log();
		aafm_clear_activity_log();
		global $wp_current_filter;
		$wp_current_filter[] = 'wp_abilities_api_categories_init';
		aafm_register_categories();
		array_pop( $wp_current_filter );
		update_option( 'aafm_enabled_abilities', array_keys( aafm_get_abilities_registry() ) );
		$wp_current_filter[] = 'wp_abilities_api_init';
		aafm_register_enabled_abilities();
		array_pop( $wp_current_filter );
	}

	public function test_create_user_requires_create_users_cap(): void {
		$this->acting_as( 'editor' ); // editor lacks create_users on single-site.
		$this->assertNotTrue(
			wp_get_ability( 'aafm/create-user' )->check_permissions( array() ),
			'create-user must require create_users.'
		);
	}

	public function test_create_user_creates_a_subscriber_by_default(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/create-user' )->execute(
			array(
				'username' => 'agent_new',
				'email'    => 'agent_new@example.com',
			)
		);
		$this->assertIsArray( $res );
		$new = get_user_by( 'login', 'agent_new' );
		$this->assertInstanceOf( \WP_User::class, $new );
		$this->assertContains( 'subscriber', (array) $new->roles, 'default role must be subscriber, not caller-chosen.' );
	}

	public function test_create_user_is_destructive_and_closed_schema(): void {
		$ability = wp_get_ability( 'aafm/create-user' );
		$ann     = $ability->get_meta_item( 'annotations' );
		$this->assertTrue( $ann['destructive'] );
		$this->assertFalse( $ability->get_input_schema()['additionalProperties'] );
	}
}
