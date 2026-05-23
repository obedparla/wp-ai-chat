<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP-CLI commands for the WP AI Chatbot plugin.
 */
class WPAIC_CLI {

	private const DUMMYJSON_ENDPOINT = 'https://dummyjson.com/products';
	private const IMPORT_META_KEY    = '_wpaic_dummyjson_id';

	/**
	 * Import realistic WooCommerce products from dummyjson.com.
	 *
	 * Each DummyJSON product is mapped to a WooCommerce simple product with real
	 * thumbnail + gallery images, category, tags, brand attribute, SKU, weight,
	 * dimensions, regular/sale price, and stock. Re-running skips products
	 * already imported (matched via DummyJSON id stored in post meta).
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Maximum number of products to import. Default: all available (~194).
	 *
	 * [--skip=<n>]
	 * : Skip the first N products. Default: 0.
	 *
	 * [--purge]
	 * : Delete previously imported DummyJSON products (and their attachments) before importing.
	 *
	 * [--dry-run]
	 * : Print what would be imported without writing to the database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpaic import-dummyjson
	 *     wp wpaic import-dummyjson --limit=50
	 *     wp wpaic import-dummyjson --purge
	 *
	 * @param array<int, string>           $args       Positional arguments.
	 * @param array<string, string|bool>   $assoc_args Associative arguments.
	 */
	public function import_dummyjson( array $args, array $assoc_args ): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
		$skip     = isset( $assoc_args['skip'] ) ? (int) $assoc_args['skip'] : 0;
		$purge    = ! empty( $assoc_args['purge'] );
		$dry_run  = ! empty( $assoc_args['dry-run'] );

		if ( $purge && ! $dry_run ) {
			$this->purge_imported_products();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$products = $this->fetch_dummyjson_products( $limit, $skip );
		if ( empty( $products ) ) {
			WP_CLI::warning( 'No products fetched from DummyJSON.' );
			return;
		}

		$total    = count( $products );
		$progress = WP_CLI\Utils\make_progress_bar( "Importing {$total} products", $total );
		$created  = 0;
		$skipped  = 0;
		$failed   = 0;

		foreach ( $products as $product_data ) {
			$dummy_id = (int) ( $product_data['id'] ?? 0 );
			$title    = (string) ( $product_data['title'] ?? '' );

			if ( $dummy_id <= 0 || '' === $title ) {
				$failed++;
				$progress->tick();
				continue;
			}

			if ( $this->find_existing_product_id( $dummy_id ) > 0 ) {
				$skipped++;
				$progress->tick();
				continue;
			}

			if ( $dry_run ) {
				WP_CLI::log( sprintf( '[dry-run] would import #%d %s', $dummy_id, $title ) );
				$created++;
				$progress->tick();
				continue;
			}

			try {
				$this->create_product( $product_data );
				$created++;
			} catch ( \Throwable $e ) {
				$failed++;
				WP_CLI::warning( sprintf( 'Failed #%d %s: %s', $dummy_id, $title, $e->getMessage() ) );
			}

			$progress->tick();
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Imported %d, skipped %d, failed %d.', $created, $skipped, $failed ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_dummyjson_products( int $limit, int $skip ): array {
		$page_size = 100;
		$collected = array();
		$remaining = $limit > 0 ? $limit : PHP_INT_MAX;
		$offset    = $skip;

		while ( $remaining > 0 ) {
			$batch_limit = min( $page_size, $remaining );
			$url         = sprintf( '%s?limit=%d&skip=%d', self::DUMMYJSON_ENDPOINT, $batch_limit, $offset );
			$response    = wp_remote_get( $url, array( 'timeout' => 30 ) );

			if ( is_wp_error( $response ) ) {
				WP_CLI::warning( 'Fetch error: ' . $response->get_error_message() );
				break;
			}

			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body['products'] ) || ! is_array( $body['products'] ) ) {
				break;
			}

			foreach ( $body['products'] as $product ) {
				$collected[] = $product;
			}

			$fetched = count( $body['products'] );
			$total   = (int) ( $body['total'] ?? 0 );
			$offset += $fetched;
			$remaining -= $fetched;

			if ( $fetched < $batch_limit || $offset >= $total ) {
				break;
			}
		}

		return $collected;
	}

	private function find_existing_product_id( int $dummy_id ): int {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::IMPORT_META_KEY,
						'value' => (string) $dummy_id,
					),
				),
				'no_found_rows'  => true,
			)
		);
		$ids = $query->posts;
		return is_array( $ids ) && ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function create_product( array $data ): int {
		$product = new WC_Product_Simple();
		$product->set_name( (string) $data['title'] );
		$product->set_description( (string) ( $data['description'] ?? '' ) );

		$short = $data['warrantyInformation'] ?? '';
		if ( ! empty( $data['shippingInformation'] ) ) {
			$short = trim( $short . ' · ' . $data['shippingInformation'], ' ·' );
		}
		$product->set_short_description( (string) $short );

		$price    = isset( $data['price'] ) ? (float) $data['price'] : 0.0;
		$discount = isset( $data['discountPercentage'] ) ? (float) $data['discountPercentage'] : 0.0;
		$product->set_regular_price( (string) $price );
		if ( $discount > 0 && $price > 0 ) {
			$sale = round( $price * ( 1 - ( $discount / 100 ) ), 2 );
			$product->set_sale_price( (string) $sale );
		}

		if ( ! empty( $data['sku'] ) ) {
			$sku = (string) $data['sku'];
			if ( ! wc_get_product_id_by_sku( $sku ) ) {
				$product->set_sku( $sku );
			}
		}

		$stock = isset( $data['stock'] ) ? (int) $data['stock'] : 0;
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $stock );
		$product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );

		if ( ! empty( $data['weight'] ) ) {
			$product->set_weight( (string) $data['weight'] );
		}
		if ( ! empty( $data['dimensions']['width'] ) ) {
			$product->set_width( (string) $data['dimensions']['width'] );
		}
		if ( ! empty( $data['dimensions']['height'] ) ) {
			$product->set_height( (string) $data['dimensions']['height'] );
		}
		if ( ! empty( $data['dimensions']['depth'] ) ) {
			$product->set_length( (string) $data['dimensions']['depth'] );
		}

		if ( ! empty( $data['rating'] ) ) {
			$product->set_average_rating( (string) $data['rating'] );
		}
		if ( ! empty( $data['reviews'] ) && is_array( $data['reviews'] ) ) {
			$product->set_review_count( count( $data['reviews'] ) );
		}

		$category_id = $this->ensure_term( (string) ( $data['category'] ?? '' ), 'product_cat' );
		if ( $category_id > 0 ) {
			$product->set_category_ids( array( $category_id ) );
		}

		if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			$tag_ids = array();
			foreach ( $data['tags'] as $tag ) {
				$id = $this->ensure_term( (string) $tag, 'product_tag' );
				if ( $id > 0 ) {
					$tag_ids[] = $id;
				}
			}
			if ( ! empty( $tag_ids ) ) {
				$product->set_tag_ids( $tag_ids );
			}
		}

		if ( ! empty( $data['brand'] ) ) {
			$brand = $this->build_attribute( 'Brand', array( (string) $data['brand'] ) );
			$product->set_attributes( array( $brand ) );
		}

		$product->update_meta_data( self::IMPORT_META_KEY, (string) $data['id'] );
		if ( ! empty( $data['meta']['barcode'] ) ) {
			$product->update_meta_data( '_wpaic_barcode', (string) $data['meta']['barcode'] );
		}

		$product_id = $product->save();
		if ( $product_id <= 0 ) {
			throw new \RuntimeException( 'WC_Product::save() returned 0' );
		}

		$this->attach_images( $product_id, $data );

		if ( ! empty( $data['reviews'] ) && is_array( $data['reviews'] ) ) {
			$this->import_reviews( $product_id, $data['reviews'] );
		}

		return $product_id;
	}

	private function ensure_term( string $name, string $taxonomy ): int {
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term instanceof WP_Term ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			return 0;
		}
		return (int) $created['term_id'];
	}

	/**
	 * @param array<int, string> $values
	 * @return WC_Product_Attribute
	 */
	private function build_attribute( string $label, array $values ): WC_Product_Attribute {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( $label );
		$attribute->set_options( $values );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		return $attribute;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function attach_images( int $product_id, array $data ): void {
		$thumbnail_url = (string) ( $data['thumbnail'] ?? '' );
		$image_urls    = isset( $data['images'] ) && is_array( $data['images'] ) ? array_map( 'strval', $data['images'] ) : array();

		$thumbnail_id = 0;
		if ( '' !== $thumbnail_url ) {
			$thumbnail_id = $this->sideload_image( $thumbnail_url, $product_id );
			if ( $thumbnail_id > 0 ) {
				set_post_thumbnail( $product_id, $thumbnail_id );
			}
		}

		$gallery_ids = array();
		foreach ( $image_urls as $url ) {
			if ( '' === $url ) {
				continue;
			}
			$attachment_id = $this->sideload_image( $url, $product_id );
			if ( $attachment_id > 0 && $attachment_id !== $thumbnail_id ) {
				$gallery_ids[] = $attachment_id;
			}
		}

		if ( ! empty( $gallery_ids ) ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
		}
	}

	private function sideload_image( string $url, int $product_id ): int {
		$attachment_id = media_sideload_image( $url, $product_id, null, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			WP_CLI::warning( sprintf( 'Image sideload failed (%s): %s', $url, $attachment_id->get_error_message() ) );
			return 0;
		}
		return (int) $attachment_id;
	}

	/**
	 * @param array<int, array<string, mixed>> $reviews
	 */
	private function import_reviews( int $product_id, array $reviews ): void {
		foreach ( $reviews as $review ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID'      => $product_id,
					'comment_author'       => (string) ( $review['reviewerName'] ?? 'Anonymous' ),
					'comment_author_email' => (string) ( $review['reviewerEmail'] ?? '' ),
					'comment_content'      => (string) ( $review['comment'] ?? '' ),
					'comment_type'         => 'review',
					'comment_approved'     => 1,
					'comment_date'         => isset( $review['date'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $review['date'] ) ) : current_time( 'mysql' ),
				)
			);
			if ( $comment_id ) {
				add_comment_meta( $comment_id, 'rating', (int) ( $review['rating'] ?? 0 ) );
			}
		}
	}

	private function purge_imported_products(): void {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => self::IMPORT_META_KEY,
						'compare' => 'EXISTS',
					),
				),
				'no_found_rows'  => true,
			)
		);

		$ids = is_array( $query->posts ) ? array_map( 'intval', $query->posts ) : array();
		if ( empty( $ids ) ) {
			WP_CLI::log( 'No previously imported products found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Purging %d imported products...', count( $ids ) ) );
		foreach ( $ids as $id ) {
			$thumbnail_id = (int) get_post_thumbnail_id( $id );
			if ( $thumbnail_id > 0 ) {
				wp_delete_attachment( $thumbnail_id, true );
			}
			$gallery = (string) get_post_meta( $id, '_product_image_gallery', true );
			if ( '' !== $gallery ) {
				foreach ( array_filter( array_map( 'intval', explode( ',', $gallery ) ) ) as $attachment_id ) {
					wp_delete_attachment( $attachment_id, true );
				}
			}
			wp_delete_post( $id, true );
		}
	}
}

WP_CLI::add_command( 'wpaic import-dummyjson', array( new WPAIC_CLI(), 'import_dummyjson' ) );
