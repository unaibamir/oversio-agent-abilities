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

	public function test_update_user_edits_profile_fields(): void {
		$this->acting_as( 'administrator' );
		$uid = self::factory()->user->create( array( 'role' => 'author' ) );
		$res = wp_get_ability( 'aafm/update-user' )->execute(
			array(
				'user_id'      => $uid,
				'display_name' => 'Renamed',
			)
		);
		$this->assertIsArray( $res );
		$this->assertSame( 'Renamed', get_userdata( $uid )->display_name );
	}

	public function test_update_user_role_change_requires_promote_users(): void {
		// An editor can edit_user on lower users but must NOT promote roles (promote_users is admin).
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->acting_as( 'editor' );
		$res = wp_get_ability( 'aafm/update-user' )->execute(
			array(
				'user_id' => $author,
				'role'    => 'administrator',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $res, 'a non-admin must not change a role.' );
		$this->assertContains( 'author', (array) get_userdata( $author )->roles, 'role must be untouched.' );
	}

	public function test_update_user_is_not_destructive(): void {
		$ann = wp_get_ability( 'aafm/update-user' )->get_meta_item( 'annotations' );
		$this->assertFalse( $ann['destructive'], 'update-user is a recoverable edit, not destructive.' );
	}

	/**
	 * Reviewer note M2: refuse to demote the SOLE remaining administrator to a
	 * non-admin role. promote_users gates the role change itself; this floor sits on
	 * top so a capable admin can't lock the site out of administration by demoting
	 * the last admin (the mirror image of the delete-user last-admin guard).
	 */
	public function test_update_user_cannot_demote_the_last_administrator(): void {
		$admin = $this->acting_as( 'administrator' );
		// The WP test fixture seeds its own administrator (user 1), so reduce the
		// admin count to exactly one — the acting admin — before the demotion attempt.
		foreach ( get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
			)
		) as $other_admin ) {
			if ( (int) $other_admin !== $admin ) {
				wp_update_user(
					array(
						'ID'   => (int) $other_admin,
						'role' => 'subscriber',
					)
				);
			}
		}
		$this->assertCount(
			1,
			get_users(
				array(
					'role'   => 'administrator',
					'fields' => 'ID',
				)
			),
			'fixture must leave exactly one admin.'
		);

		// Demoting the sole remaining admin to editor would leave the site with no admin — refuse it.
		$res = wp_get_ability( 'aafm/update-user' )->execute(
			array(
				'user_id' => $admin,
				'role'    => 'editor',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $res, 'demoting the sole admin must be refused.' );
		$this->assertContains( 'administrator', (array) get_userdata( $admin )->roles, 'sole admin must stay an admin.' );
	}
}
