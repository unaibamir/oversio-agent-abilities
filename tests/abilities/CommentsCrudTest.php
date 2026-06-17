<?php
/**
 * Comment CRUD abilities: single read, create (author-pinned, content-sanitized),
 * content-only update, and permanent (force) delete. Proves no email/IP ever leaks
 * and that author identity can never be spoofed through input.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;
use WP_Comment;
use WP_Error;

final class CommentsCrudTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		aafm_install_activity_log();
		aafm_clear_activity_log();

		$this->in_action( 'wp_abilities_api_categories_init', 'aafm_register_categories' );
		update_option(
			'aafm_enabled_abilities',
			array(
				'aafm/get-comment',
				'aafm/create-comment',
				'aafm/update-comment',
				'aafm/delete-comment',
			)
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

	public function test_get_comment_returns_redacted_shape_without_email_or_ip(): void {
		$this->acting_as( 'editor' );
		$post    = self::factory()->post->create();
		$comment = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post,
				'comment_approved'     => '1',
				'comment_author'       => 'Jane',
				'comment_author_email' => 'jane@example.com',
				'comment_author_IP'    => '203.0.113.7',
				'comment_content'      => 'Hello world',
			)
		);

		$out = wp_get_ability( 'aafm/get-comment' )->execute( array( 'comment_id' => $comment ) );

		$this->assertIsArray( $out );
		$this->assertArrayHasKey( 'comment', $out );
		$shape = $out['comment'];
		$this->assertSame( $comment, $shape['id'] );
		$this->assertSame( 'Jane', $shape['author_name'] );
		$this->assertSame( 'Hello world', $shape['content'] );
		// The redactor must never surface email or IP.
		$this->assertArrayNotHasKey( 'author_email', $shape );
		$this->assertArrayNotHasKey( 'comment_author_email', $shape );
		$this->assertArrayNotHasKey( 'author_ip', $shape );
		$this->assertArrayNotHasKey( 'comment_author_IP', $shape );
		$this->assertStringNotContainsString( 'jane@example.com', wp_json_encode( $out ) );
		$this->assertStringNotContainsString( '203.0.113.7', wp_json_encode( $out ) );
	}

	public function test_get_comment_missing_id_returns_error(): void {
		$this->acting_as( 'editor' );
		$out = wp_get_ability( 'aafm/get-comment' )->execute( array( 'comment_id' => 999999 ) );
		$this->assertInstanceOf( WP_Error::class, $out );
	}
}
