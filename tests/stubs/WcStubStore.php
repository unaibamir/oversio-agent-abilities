<?php
/**
 * Process-wide backing store for the WooCommerce host stubs (Wave 4 integration tests).
 *
 * Lives in its own file so the IntegrationStubs trait file holds a single object structure (the
 * trait), satisfying Generic.Files.OneObjectStructurePerFile. Required directly from the test
 * bootstrap, never shipped.
 *
 * @package AgentAbilitiesForMCP
 */

declare( strict_types=1 );

namespace AAFM\Tests;

/**
 * Process-wide backing store for the WooCommerce host stubs.
 *
 * WooCommerce's wc_get_product/wc_get_products and the WC_Product object are defined once per
 * process; this static store holds the seeded products keyed by id and the data each stub WC_Product
 * reads/writes, so a value written through ->save() (create or update) is visible to a following
 * wc_get_product()/wc_get_products() inside one test, and a ->delete() removes it. stub_woocommerce()
 * reset()s + seeds it each test, and reset_integration_stubs() clears it.
 */
class WcStubStore {

	/**
	 * Products keyed by id: id => array of product data (the WC_Product getter source).
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public static array $products = array();

	/**
	 * The next id handed out to a created product.
	 *
	 * @var int
	 */
	public static int $next_id = 1000;

	/**
	 * Clear all state.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$products = array();
		self::$next_id  = 1000;
	}

	/**
	 * Seed one product's data under its id (the test fixture's setup path).
	 *
	 * @param int                 $id   Product id.
	 * @param array<string,mixed> $data Product data.
	 * @return void
	 */
	public static function seed( int $id, array $data ): void {
		$data['id']            = $id;
		self::$products[ $id ] = self::with_defaults( $data );
	}

	/**
	 * Whether a product id exists in the store.
	 *
	 * @param int $id Product id.
	 * @return bool
	 */
	public static function exists( int $id ): bool {
		return isset( self::$products[ $id ] );
	}

	/**
	 * The stored data for a product id, or null.
	 *
	 * @param int $id Product id.
	 * @return array<string,mixed>|null
	 */
	public static function get( int $id ): ?array {
		return self::$products[ $id ] ?? null;
	}

	/**
	 * Persist a product's data (create when id is 0/absent, else update), returning the id.
	 *
	 * @param array<string,mixed> $data Product data, including 'id' (0 to create).
	 * @return int The persisted id.
	 */
	public static function save( array $data ): int {
		$id = (int) ( $data['id'] ?? 0 );
		if ( $id <= 0 ) {
			$id = self::$next_id;
			++self::$next_id;
		}
		$data['id']            = $id;
		self::$products[ $id ] = self::with_defaults( $data );
		return $id;
	}

	/**
	 * Permanently remove a product id.
	 *
	 * @param int $id Product id.
	 * @return void
	 */
	public static function delete( int $id ): void {
		unset( self::$products[ $id ] );
	}

	/**
	 * Every stored product's data, in id order (the wc_get_products() source).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		$out = array_values( self::$products );
		usort(
			$out,
			static fn( array $a, array $b ): int => ( (int) $a['id'] ) <=> ( (int) $b['id'] )
		);
		return $out;
	}

	/**
	 * The wc_get_products() stub: returns WC_Product objects for the seeded products, honouring the
	 * status filter and limit/page paging. Mirrors WC's default return ('objects').
	 *
	 * @param array<string,mixed> $args Query args (status, limit, page).
	 * @return array<int,\WC_Product>
	 */
	public static function query( array $args = array() ): array {
		$status = $args['status'] ?? '';
		$rows   = self::all();

		if ( '' !== $status && 'any' !== $status ) {
			$wanted = (array) $status;
			$rows   = array_values(
				array_filter(
					$rows,
					static fn( array $row ): bool => in_array( (string) ( $row['status'] ?? '' ), $wanted, true )
				)
			);
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : -1;
		if ( $limit > 0 ) {
			$page   = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
			$offset = ( $page - 1 ) * $limit;
			$rows   = array_slice( $rows, $offset, $limit );
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = new \WC_Product( (int) $row['id'] );
		}
		return $out;
	}

	/**
	 * Fill a product data array with the defaults the WC_Product getters expect, so a partial seed
	 * or a partial create still reads back a complete, typed shape.
	 *
	 * @param array<string,mixed> $data Raw product data.
	 * @return array<string,mixed>
	 */
	private static function with_defaults( array $data ): array {
		return array_merge(
			array(
				'id'                => 0,
				'name'              => '',
				'type'              => 'simple',
				'status'            => 'publish',
				'sku'               => '',
				'description'       => '',
				'short_description' => '',
				'price'             => '',
				'regular_price'     => '',
				'sale_price'        => '',
				'stock_status'      => 'instock',
				'stock_quantity'    => null,
				'manage_stock'      => false,
				'featured'          => false,
				'category_ids'      => array(),
				'tag_ids'           => array(),
				'gallery_image_ids' => array(),
				'image_id'          => 0,
				'attributes'        => array(),
				'children'          => array(),
			),
			$data
		);
	}
}
