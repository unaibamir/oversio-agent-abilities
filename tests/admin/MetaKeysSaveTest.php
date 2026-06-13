<?php
/**
 * Exposed-meta-keys sanitizer coverage.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Admin;

use AAFM\Tests\TestCase;

final class MetaKeysSaveTest extends TestCase {

	public function test_sanitize_parses_multiline_trims_dedupes_drops_blocked(): void {
		$out = aafm_sanitize_allowed_meta_keys_input(
			array( 'aafm_meta_keys' => "subtitle\n subtitle \n\n_edit_lock\nwp_capabilities\nfeatured_color" )
		);
		$this->assertSame( array( 'subtitle', 'featured_color' ), $out );
	}

	public function test_detected_keys_scopes_and_excludes_blocked(): void {
		delete_transient( 'aafm_detected_meta_keys' );
		$id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		update_post_meta( $id, 'subtitle', 'x' );
		update_post_meta( $id, '_edit_lock', '123' );
		$keys = aafm_detected_meta_keys();
		$this->assertContains( 'subtitle', $keys );
		$this->assertNotContains( '_edit_lock', $keys );
	}
}
