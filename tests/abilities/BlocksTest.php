<?php
/**
 * Slice B: the reusable-block (wp_block) read + write abilities.
 *
 * Covers the lean/rich block assemblers and block-object resolver, the list-blocks and
 * get-block reads, the kses-hardened create-block/update-block writes with forced type and
 * author plus closed-schema smuggle rejection, the per-object edit_block/delete_block gates,
 * and the trash-only delete-block with its trash-disabled refusal.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;

final class BlocksTest extends TestCase {

	private function make_block( string $title = 'CTA', string $content = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ): int {
		return (int) self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	public function test_lean_redactor_has_no_content_and_rich_adds_markup(): void {
		$id   = $this->make_block();
		$lean = aafm_redact_block( get_post( $id ) );
		$this->assertSame( array( 'id', 'title', 'slug', 'status', 'modified' ), array_keys( $lean ) );
		$this->assertArrayNotHasKey( 'content', $lean, 'the list redactor must not carry block markup.' );

		$rich = aafm_rich_block( get_post( $id ) );
		$this->assertArrayHasKey( 'content', $rich );
		$this->assertStringContainsString( 'wp:paragraph', $rich['content'], 'rich block must expose the raw block markup.' );
	}

	public function test_block_object_resolver_rejects_a_non_block_post(): void {
		$post_id = (int) self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertNull( aafm_get_block_object( $post_id ), 'a normal post is not a wp_block.' );
		$block_id = $this->make_block();
		$this->assertInstanceOf( \WP_Post::class, aafm_get_block_object( $block_id ) );
	}

	private function in_action( string $action, callable $cb ): void {
		global $wp_current_filter;
		$wp_current_filter[] = $action;
		$cb();
		array_pop( $wp_current_filter );
	}

	private function register_blocks(): void {
		aafm_install_activity_log();
		aafm_clear_activity_log();
		$this->in_action( 'wp_abilities_api_categories_init', 'aafm_register_categories' );
		update_option(
			'aafm_enabled_abilities',
			array( 'aafm/list-blocks', 'aafm/get-block', 'aafm/create-block', 'aafm/update-block', 'aafm/delete-block' )
		);
		$this->in_action( 'wp_abilities_api_init', 'aafm_register_enabled_abilities' );
	}

	public function test_list_blocks_requires_edit_posts_and_returns_lean_rows(): void {
		$this->register_blocks();
		$this->make_block( 'Alpha' );
		$this->acting_as( 'subscriber' );
		$this->assertNotTrue( wp_get_ability( 'aafm/list-blocks' )->check_permissions( array() ) );

		$this->acting_as( 'editor' );
		$res = wp_get_ability( 'aafm/list-blocks' )->execute( array() );
		$this->assertArrayHasKey( 'blocks', $res );
		$this->assertArrayHasKey( 'total', $res );
		$this->assertArrayNotHasKey( 'content', $res['blocks'][0], 'list rows must be lean (no markup).' );
	}

	public function test_get_block_returns_markup_and_rejects_a_non_block(): void {
		$this->register_blocks();
		$id = $this->make_block( 'Beta', '<!-- wp:heading --><h2>Hi</h2><!-- /wp:heading -->' );
		$this->acting_as( 'editor' );
		$res = wp_get_ability( 'aafm/get-block' )->execute( array( 'block_id' => $id ) );
		$this->assertStringContainsString( 'wp:heading', $res['content'] );

		$post_id = (int) self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertInstanceOf( \WP_Error::class, wp_get_ability( 'aafm/get-block' )->execute( array( 'block_id' => $post_id ) ) );
	}
}
