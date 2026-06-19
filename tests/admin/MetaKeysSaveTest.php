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

	public function test_meta_selector_is_nested_form_safe(): void {
		$id = self::factory()->post->create();
		update_post_meta( $id, 'subtitle', 'x' );
		ob_start();
		aafm_render_abilities_tab();
		$html = ob_get_clean();
		$this->assertSame( 1, substr_count( $html, '<form' ) );
		$this->assertStringContainsString( 'id="aafm-meta-keys-form"', $html );
		$this->assertStringContainsString( 'name="aafm_meta_keys"', $html );
		$this->assertStringContainsString( 'id="aafm-meta-keys-save"', $html );
		$this->assertStringContainsString( 'aafm-meta-chip', $html );
		// Direction A presentation: the meta-keys selector lives in a card.
		$this->assertStringContainsString( 'aafm-card', $html );

		// The privacy warning now renders through the shared notice component (a <div> with an
		// inline SVG icon), not the old ad-hoc <p class="aafm-notice">. The <div> never opens a
		// form, so the one-form invariant above still holds.
		$this->assertStringContainsString( 'aafm-notice aafm-notice-warning', $html );
		$this->assertStringContainsString( 'aafm-notice-ic', $html );
		$this->assertStringContainsString( '<svg class="aafm-icon"', $html );
	}

	/**
	 * The Content meta selector renders BOTH the Exposed textarea (existing) and the new Deny
	 * textarea, with Exposed above Deny and the `*` hint on each. The detected-keys chips
	 * attach to Exposed only, and the selector never opens a nested form.
	 */
	public function test_content_selector_renders_exposed_and_deny_textareas(): void {
		ob_start();
		aafm_render_meta_keys_selector();
		$html = ob_get_clean();

		$exposed_pos = strpos( $html, 'name="aafm_meta_keys"' );
		$deny_pos    = strpos( $html, 'name="aafm_deny_meta_keys"' );

		$this->assertNotFalse( $exposed_pos, 'Exposed textarea must render.' );
		$this->assertNotFalse( $deny_pos, 'Deny textarea must render.' );
		$this->assertLessThan( $deny_pos, $exposed_pos, 'Exposed must render above Deny.' );

		// The `*` wildcard is documented on both controls.
		$this->assertGreaterThanOrEqual( 2, substr_count( $html, '*' ) );

		// The Save button keeps the primary style; the selector is not a nested form.
		$this->assertStringContainsString( 'aafm-btn aafm-btn-primary', $html );
		$this->assertSame( 0, substr_count( $html, '<form' ) );
	}

	/**
	 * The Users sub-tab renders its own exposed + denied user-meta textareas, mirroring the
	 * Content pair, and never opens a nested form.
	 */
	public function test_users_selector_renders_exposed_and_deny_textareas(): void {
		ob_start();
		aafm_render_user_meta_keys_selector();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="aafm_exposed_user_meta_keys"', $html );
		$this->assertStringContainsString( 'name="aafm_denied_user_meta_keys"', $html );
		$this->assertStringContainsString( 'aafm-btn', $html );
		$this->assertSame( 0, substr_count( $html, '<form' ) );
	}

	/**
	 * The full abilities tab wires the Users selector into the users panel, still inside the
	 * single outer form (no second form opened by either meta selector).
	 */
	public function test_abilities_tab_wires_users_selector_in_single_form(): void {
		ob_start();
		aafm_render_abilities_tab();
		$html = ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '<form' ) );
		$this->assertStringContainsString( 'name="aafm_exposed_user_meta_keys"', $html );
		$this->assertStringContainsString( 'name="aafm_denied_user_meta_keys"', $html );
		$this->assertStringContainsString( 'name="aafm_deny_meta_keys"', $html );
	}
}
