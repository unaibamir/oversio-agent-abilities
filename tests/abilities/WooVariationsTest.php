<?php
/**
 * WooCommerce product-variation abilities (sub-slice W4-WC1b).
 *
 * The DDEV site ships no WooCommerce host plugin, so each test forces the integration active through
 * its per-slug filter and defines the minimal WooCommerce host surface via stub_woocommerce() (the
 * IntegrationStubs trait). A variation is a product row carrying type='variation' and a parent_id;
 * the abilities list/read/create/update/delete through the WC CRUD layer (wc_get_product /
 * WC_Product_Variation), all served by the WcStubStore with parent/children linkage.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests\Abilities;

use AAFM\Tests\TestCase;
use AAFM\Tests\IntegrationStubs;
use AAFM\Tests\WcStubStore;
use WP_Error;

final class WooVariationsTest extends TestCase {

	use IntegrationStubs;

	public function set_up(): void {
		parent::set_up();
		aafm_install_activity_log();
		aafm_clear_activity_log();
		$this->force_integration( 'woocommerce' );
		$this->seed_variable_parent_with_variations();
		aafm_registry_cache_should_flush( true );
		$this->register_wc_variations();
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
	 * Seed a variable parent (id 500) owning two variations (601, 602). The parent is seeded first so
	 * the store's children linkage attaches each variation to it.
	 *
	 * @param int $variation_count How many variations to attach to the parent.
	 */
	private function seed_variable_parent_with_variations( int $variation_count = 2 ): void {
		$products = array(
			array(
				'id'     => 500,
				'name'   => 'Variable Parent',
				'type'   => 'variable',
				'status' => 'publish',
			),
		);
		for ( $i = 1; $i <= $variation_count; $i++ ) {
			$products[] = array(
				'id'            => 600 + $i,
				'parent_id'     => 500,
				'type'          => 'variation',
				'sku'           => 'VAR-' . ( 600 + $i ),
				'regular_price' => '5.0' . $i,
				'price'         => '5.0' . $i,
				'status'        => 'publish',
				'stock_status'  => 'instock',
				'description'   => 'Variation ' . $i,
				'attributes'    => array( 'pa_color' => 'red' ),
			);
		}
		$this->stub_woocommerce( $products );
	}

	/**
	 * Enable + register the WooCommerce variation set so the abilities can be invoked.
	 */
	private function register_wc_variations(): void {
		$this->in_action( 'wp_abilities_api_categories_init', 'aafm_register_categories' );
		update_option(
			'aafm_enabled_abilities',
			array(
				'aafm/wc-list-product-variations',
				'aafm/wc-get-product-variation',
				'aafm/wc-create-product-variation',
				'aafm/wc-update-product-variation',
				'aafm/wc-delete-product-variation',
			)
		);
		$this->in_action( 'wp_abilities_api_init', 'aafm_register_enabled_abilities' );
	}

	/**
	 * WC1b reads: list + get.
	 */
	public function test_list_variations_requires_manage_woocommerce(): void {
		$this->acting_as( 'editor' );
		$this->assertNotTrue(
			wp_get_ability( 'aafm/wc-list-product-variations' )->check_permissions( array( 'product_id' => 500 ) )
		);

		$this->acting_as( 'administrator' ); // admin has manage_woocommerce.
		$res = wp_get_ability( 'aafm/wc-list-product-variations' )->execute( array( 'product_id' => 500 ) );
		$this->assertArrayHasKey( 'variations', $res );
		$this->assertArrayHasKey( 'total', $res );
		$this->assertSame( 2, $res['total'] );
		$ids = wp_list_pluck( $res['variations'], 'id' );
		sort( $ids );
		$this->assertSame( array( 601, 602 ), $ids );
	}

	public function test_list_variation_rows_are_lean(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-list-product-variations' )->execute( array( 'product_id' => 500 ) );
		$row = $res['variations'][0];
		$this->assertArrayHasKey( 'id', $row );
		$this->assertArrayHasKey( 'parent_id', $row );
		$this->assertArrayHasKey( 'sku', $row );
		$this->assertArrayHasKey( 'price', $row );
		$this->assertArrayHasKey( 'stock_status', $row );
		$this->assertArrayHasKey( 'status', $row );
		$this->assertArrayNotHasKey( 'description', $row, 'list rows are lean (no description).' );
		$this->assertArrayNotHasKey( 'attributes', $row, 'list rows are lean (no attributes).' );
	}

	public function test_list_variations_denies_a_subscriber(): void {
		$this->acting_as( 'subscriber' );
		$this->assertNotTrue(
			wp_get_ability( 'aafm/wc-list-product-variations' )->check_permissions( array( 'product_id' => 500 ) )
		);
	}

	public function test_list_variations_requires_product_id(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-list-product-variations' )->execute( array() );
		$this->assertInstanceOf( WP_Error::class, $res, 'product_id (the parent) is required.' );
	}

	public function test_list_variations_unknown_parent_is_graceful_error(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-list-product-variations' )->execute( array( 'product_id' => 999999 ) );
		$this->assertInstanceOf( WP_Error::class, $res );
	}

	public function test_get_variation_returns_rich_shape(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-get-product-variation' )->execute( array( 'variation_id' => 601 ) );
		$this->assertNotInstanceOf( WP_Error::class, $res );
		$this->assertSame( 601, $res['id'] );
		$this->assertSame( 500, $res['parent_id'], 'The variation reports its parent.' );
		$this->assertSame( 'VAR-601', $res['sku'] );
		$this->assertArrayHasKey( 'description', $res );
		$this->assertArrayHasKey( 'attributes', $res );
		$this->assertArrayHasKey( 'regular_price', $res );
		$this->assertArrayHasKey( 'stock_quantity', $res );
		// The flat name=>value attribute map round-trips.
		$attributes = (array) $res['attributes'];
		$this->assertSame( 'red', $attributes['pa_color'] ?? null );
	}

	public function test_get_variation_nonexistent_id_is_graceful_error(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-get-product-variation' )->execute( array( 'variation_id' => 999999 ) );
		$this->assertInstanceOf( WP_Error::class, $res );
	}

	public function test_get_variation_denies_a_subscriber(): void {
		$this->acting_as( 'subscriber' );
		$this->assertNotTrue(
			wp_get_ability( 'aafm/wc-get-product-variation' )->check_permissions( array( 'variation_id' => 601 ) )
		);
	}

	public function test_get_variation_empty_attributes_encodes_as_object_not_array(): void {
		// A variation with no chosen attributes: the map must JSON-encode to "{}" (object), never "[]".
		WcStubStore::seed(
			700,
			array(
				'id'         => 700,
				'parent_id'  => 500,
				'type'       => 'variation',
				'attributes' => array(),
			)
		);
		$this->acting_as( 'administrator' );
		$res  = wp_get_ability( 'aafm/wc-get-product-variation' )->execute( array( 'variation_id' => 700 ) );
		$json = (string) wp_json_encode( $res['attributes'] );
		$this->assertSame( '{}', $json, 'Empty variation attributes must encode as {}.' );
	}

	public function test_get_variation_output_schema_declares_every_emitted_field(): void {
		$this->acting_as( 'administrator' );
		$res = wp_get_ability( 'aafm/wc-get-product-variation' )->execute( array( 'variation_id' => 601 ) );
		$this->assertNotInstanceOf( WP_Error::class, $res );

		$schema   = aafm_args_wc_get_product_variation()['output_schema'];
		$declared = array_keys( $schema['properties'] );

		foreach ( array_keys( $res ) as $emitted_key ) {
			$this->assertContains(
				$emitted_key,
				$declared,
				sprintf( 'Emitted field "%s" must be declared in the get-variation output_schema.', $emitted_key )
			);
		}
	}

	public function test_wc_variation_abilities_absent_when_host_inactive(): void {
		// Mirror WooProductsTest: assert at the REGISTRY level. stub_woocommerce() defines class
		// WooCommerce process-wide, so real detection still reports WC active after removing the force
		// filter — pin it off through the aafm_woocommerce_active seam.
		$this->reset_integration_stubs();
		remove_all_filters( 'aafm_integration_active_woocommerce' );
		add_filter( 'aafm_woocommerce_active', '__return_false', 99 );
		$this->assertFalse( aafm_integration_active( 'woocommerce' ) );
		aafm_registry_cache_should_flush( true );
		$this->assertArrayNotHasKey( 'aafm/wc-list-product-variations', aafm_get_abilities_registry() );
		remove_filter( 'aafm_woocommerce_active', '__return_false', 99 );
	}

	public function test_list_variations_discovery_admin_yes_editor_no(): void {
		$this->acting_as( 'administrator' );
		$this->assertTrue( aafm_user_can_discover_ability( 'aafm/wc-list-product-variations' ) );

		$this->acting_as( 'editor' );
		$this->assertFalse( aafm_user_can_discover_ability( 'aafm/wc-list-product-variations' ) );
	}
}
