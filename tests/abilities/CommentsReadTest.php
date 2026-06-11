<?php
/**
 * Comment read abilities: PII redaction + moderation gating of unapproved bodies.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;

final class CommentsReadTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		// The audited registration wrapper logs every permission check and execute to the
		// custom table, so it must exist before any ability is invoked.
		aafm_install_activity_log();
		aafm_clear_activity_log();

		// Register categories + enabled abilities inside their gated init actions, simulated
		// by pushing the action name onto $wp_current_filter — the idiom WP core's own
		// ability test trait uses. wp_register_ability() refuses to run otherwise.
		$this->in_action( 'wp_abilities_api_categories_init', 'aafm_register_categories' );
		update_option(
			'aafm_enabled_abilities',
			array( 'aafm/get-comments', 'aafm/get-pending-comments' )
		);
		$this->in_action( 'wp_abilities_api_init', 'aafm_register_enabled_abilities' );
	}

	/**
	 * Run a callback inside a simulated Abilities API init action.
	 *
	 * @param string   $action   Action name to simulate.
	 * @param callable $callback Callback to invoke while the action is "running".
	 */
	private function in_action( string $action, callable $callback ): void {
		global $wp_current_filter;
		$wp_current_filter[] = $action;
		$callback();
		array_pop( $wp_current_filter );
	}

	public function test_comment_reads_are_in_registry(): void {
		$registry = aafm_get_abilities_registry();
		foreach ( array( 'aafm/get-comments', 'aafm/get-pending-comments' ) as $name ) {
			$this->assertArrayHasKey( $name, $registry );
			$this->assertSame( 'reads', $registry[ $name ]['group'] );
			$this->assertSame( 'read', $registry[ $name ]['risk'] );
		}
	}

	public function test_get_comments_redacts_email_and_ip(): void {
		$this->acting_as( 'subscriber' );
		$post = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post,
				'comment_approved'     => '1',
				'comment_author'       => 'Jane',
				'comment_author_email' => 'jane@example.com',
				'comment_author_IP'    => '203.0.113.9',
				'comment_author_url'   => 'https://jane.example.com',
				'comment_agent'        => 'EvilBot/1.0',
			)
		);
		$out  = wp_get_ability( 'aafm/get-comments' )->execute( array( 'post_id' => $post ) );
		$json = wp_json_encode( $out );

		// The approved comment is returned...
		$this->assertCount( 1, $out['comments'] );
		$this->assertSame( 'Jane', $out['comments'][0]['author_name'] );

		// ...but never the email, IP, author URL, or user agent.
		$this->assertStringNotContainsString( 'jane@example.com', (string) $json );
		$this->assertStringNotContainsString( '203.0.113.9', (string) $json );
		$this->assertStringNotContainsString( 'jane.example.com', (string) $json );
		$this->assertStringNotContainsString( 'EvilBot/1.0', (string) $json );
		$this->assertArrayNotHasKey( 'author_email', $out['comments'][0] );
		$this->assertArrayNotHasKey( 'author_ip', $out['comments'][0] );
		$this->assertArrayNotHasKey( 'author_url', $out['comments'][0] );
		$this->assertArrayNotHasKey( 'agent', $out['comments'][0] );
	}

	/**
	 * The Phase 1 carried nit: a low-privilege caller must never receive an
	 * unapproved comment's body through get-comments.
	 */
	public function test_get_comments_never_returns_pending_or_spam_bodies(): void {
		$this->acting_as( 'subscriber' );
		$post = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post,
				'comment_approved' => '1',
				'comment_content'  => 'APPROVED_VISIBLE_BODY',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post,
				'comment_approved' => '0',
				'comment_content'  => 'PENDING_SECRET_BODY',
			)
		);
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post,
				'comment_approved' => 'spam',
				'comment_content'  => 'SPAM_SECRET_BODY',
			)
		);

		$out  = wp_get_ability( 'aafm/get-comments' )->execute( array( 'post_id' => $post ) );
		$json = wp_json_encode( $out );

		$this->assertCount( 1, $out['comments'] );
		$this->assertStringContainsString( 'APPROVED_VISIBLE_BODY', (string) $json );
		$this->assertStringNotContainsString( 'PENDING_SECRET_BODY', (string) $json );
		$this->assertStringNotContainsString( 'SPAM_SECRET_BODY', (string) $json );
	}

	/**
	 * The moderation queue is the only path to unapproved bodies, and it must be
	 * gated by moderate_comments. A subscriber is denied; a moderator is allowed
	 * AND actually sees the pending body.
	 */
	public function test_pending_comments_are_gated_by_moderate_comments(): void {
		$post = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post,
				'comment_approved' => '0',
				'comment_content'  => 'PENDING_SECRET_BODY',
			)
		);

		// Subscriber lacks moderate_comments → denied, never reaches the body.
		$this->acting_as( 'subscriber' );
		$this->assertFalse(
			wp_get_ability( 'aafm/get-pending-comments' )->check_permissions( array() )
		);

		// Editor has moderate_comments → allowed, and sees the pending body.
		$this->acting_as( 'editor' );
		$this->assertTrue(
			wp_get_ability( 'aafm/get-pending-comments' )->check_permissions( array() )
		);

		$out  = wp_get_ability( 'aafm/get-pending-comments' )->execute( array() );
		$json = wp_json_encode( $out );
		$this->assertGreaterThanOrEqual( 1, count( $out['comments'] ) );
		$this->assertStringContainsString( 'PENDING_SECRET_BODY', (string) $json );
	}
}
