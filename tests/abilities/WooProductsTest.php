<?php
/**
 * WooCommerce product abilities (sub-slice W4-WC1a).
 *
 * The DDEV site ships no WooCommerce host plugin, so each test forces the integration active through
 * its per-slug filter and defines the minimal WooCommerce host surface via stub_woocommerce() (the
 * IntegrationStubs trait). The abilities list/read/create/update/delete through the WC CRUD layer
 * (wc_get_products / wc_get_product / WC_Product), all served by the WcStubStore.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;
use AAFM\Tests\IntegrationStubs;
use WP_Error;

final class WooProductsTest extends TestCase {

	use IntegrationStubs;

	public function set_up(): void {
		parent::set_up();
		aafm_install_activity_log();
		aafm_clear_activity_log();
		$this->force_integration( 'woocommerce' );
		$this->stub_woocommerce();
		aafm_registry_cache_should_flush( true );
		$this->register_wc_products();
	}

	public function tear_down(): void {
		$this->reset_integration_stubs();
		parent::tear_down();
	}

	/**
	 * Run a callback inside a simulated Abilities API init action.
	 *
	 * @param string   $action Action name to simulate.
	 * @param callable $cb     Callback to invoke while the action is "running".
	 */
	private function in_action( string $action, callable $cb ): void {
		global $wp_current_filter;
		$wp_current_filter[] = $action;
		$cb();
		array_pop( $wp_current_filter );
	}

	/**
	 * Enable + register the WooCommerce product set so the abilities can be invoked.
	 */
	private function register_wc_products(): void {
		$this->in_action( 'wp_abilities_api_categories_init', 'aafm_register_categories' );
		update_option(
			'aafm_enabled_abilities',
			array(
				'aafm/wc-list-products',
				'aafm/wc-get-product',
				'aafm/wc-create-product',
				'aafm/wc-update-product',
				'aafm/wc-delete-product',
			)
		);
		$this->in_action( 'wp_abilities_api_init', 'aafm_register_enabled_abilities' );
	}

	public function test_list_products_requires_manage_woocommerce(): void {
		$this->acting_as( 'editor' );
		$this->assertNotTrue( wp_get_ability( 'aafm/wc-list-products' )->check_permissions( array() ) );

		$this->acting_as( 'administrator' ); // admin has manage_woocommerce.
		$res = wp_get_ability( 'aafm/wc-list-products' )->execute( array() );
		$this->assertArrayHasKey( 'products', $res );
		$this->assertArrayHasKey( 'total', $res );
		$this->assertSame( 1, $res['total'] );
		$this->assertSame( 101, $res['products'][0]['id'] );
		$this->assertSame( 'Test Widget', $res['products'][0]['name'] );
		$this->assertSame( 'WIDGET-101', $res['products'][0]['sku'] );
		$this->assertArrayNotHasKey( 'description', $res['products'][0], 'list rows are lean.' );
	}

	public function test_list_products_denies_a_subscriber(): void {
		$this->acting_as( 'subscriber' );
		$this->assertNotTrue( wp_get_ability( 'aafm/wc-list-products' )->check_permissions( array() ) );
	}

	public function test_get_product_returns_full_shape(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-get-product' )->execute( array( 'product_id' => 101 ) );
		$this->assertNotInstanceOf( WP_Error::class, $res );
		$this->assertSame( 101, $res['id'] );
		$this->assertSame( 'Test Widget', $res['name'] );
		$this->assertArrayHasKey( 'description', $res );
		$this->assertArrayHasKey( 'attributes', $res );
		$this->assertArrayHasKey( 'images', $res );
		$this->assertArrayHasKey( 'variation_ids', $res );
		$this->assertArrayHasKey( 'categories', $res );
	}

	public function test_get_product_nonexistent_id_is_graceful_error(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-get-product' )->execute( array( 'product_id' => 999999 ) );
		$this->assertInstanceOf( WP_Error::class, $res );
	}

	public function test_get_product_denies_a_subscriber(): void {
		$this->acting_as( 'subscriber' );
		$this->assertNotTrue(
			wp_get_ability( 'aafm/wc-get-product' )->check_permissions( array( 'product_id' => 101 ) )
		);
	}

	public function test_get_product_empty_attributes_encodes_as_object_not_array(): void {
		// The attributes map is type:object; an empty one must JSON-encode to "{}" (object), never
		// "[]" (list), per the get-all-post-meta / ACF empty-map lesson.
		$this->acting_as( 'administrator' );
		$res  = wp_get_ability( 'aafm/wc-get-product' )->execute( array( 'product_id' => 101 ) );
		$json = (string) wp_json_encode( $res['attributes'] );
		$this->assertSame( '{}', $json, 'Empty product attributes must encode as {}.' );
	}

	public function test_wc_abilities_absent_when_host_inactive(): void {
		// HIGH-2: assert at the REGISTRY level (not via aafm_user_can_discover_ability, which leaks
		// through the process-wide raw-permission $store once any test registered the set). The
		// stub_woocommerce() helper defines class WooCommerce process-wide, so real detection still
		// reports WC active after removing the force filter — pin it off through aafm_woocommerce_active.
		$this->reset_integration_stubs();
		remove_all_filters( 'aafm_integration_active_woocommerce' );
		add_filter( 'aafm_woocommerce_active', '__return_false', 99 );
		$this->assertFalse( aafm_integration_active( 'woocommerce' ) );
		aafm_registry_cache_should_flush( true );
		$this->assertArrayNotHasKey( 'aafm/wc-list-products', aafm_get_abilities_registry() );
		remove_filter( 'aafm_woocommerce_active', '__return_false', 99 );
	}

	public function test_list_products_discovery_admin_yes_editor_no(): void {
		// The WC abilities gate on the object-independent manage_woocommerce cap, so they fall through
		// to their real permission_callback at discovery (no server.php case). An admin discovers them;
		// an editor (no manage_woocommerce) does not.
		$this->acting_as( 'administrator' );
		$this->assertTrue( aafm_user_can_discover_ability( 'aafm/wc-list-products' ) );

		$this->acting_as( 'editor' );
		$this->assertFalse( aafm_user_can_discover_ability( 'aafm/wc-list-products' ) );
	}
}
