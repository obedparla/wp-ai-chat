<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Loader {
	public function init(): void {
		$this->load_dependencies();
		$this->init_admin();
		$this->init_frontend();
		$this->init_api();
		$this->init_cart();
		$this->init_search_index();
	}

	private function load_dependencies(): void {
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-admin.php';
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-frontend.php';
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-api.php';
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-chat.php';
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-tools.php';
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-logs.php';
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-cart.php';
		require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-search-index.php';
	}

	private function init_admin(): void {
		if ( is_admin() ) {
			$admin = new WPAIC_Admin();
			$admin->init();
		}
	}

	private function init_frontend(): void {
		$frontend = new WPAIC_Frontend();
		$frontend->init();
	}

	private function init_api(): void {
		$api = new WPAIC_API();
		$api->init();
	}

	private function init_cart(): void {
		$cart = new WPAIC_Cart();
		$cart->init();
	}

	private function init_search_index(): void {
		$search_index = new WPAIC_Search_Index();

		add_action(
			'woocommerce_new_product',
			function ( int $product_id ) use ( $search_index ): void {
				$search_index->index_product( $product_id );
			}
		);
		add_action(
			'woocommerce_update_product',
			function ( int $product_id ) use ( $search_index ): void {
				$search_index->index_product( $product_id );
			}
		);
		add_action(
			'woocommerce_delete_product',
			function ( int $product_id ) use ( $search_index ): void {
				$search_index->remove_product( $product_id );
			}
		);
		add_action(
			'wp_trash_post',
			function ( int $post_id ) use ( $search_index ): void {
				if ( 'product' === get_post_type( $post_id ) ) {
					$search_index->remove_product( $post_id );
				}
			}
		);
		add_action(
			'woocommerce_save_product_variation',
			function ( int $variation_id ) use ( $search_index ): void {
				$parent_id = wp_get_post_parent_id( $variation_id );
				if ( $parent_id ) {
					$search_index->index_product( $parent_id );
				}
			}
		);
	}
}
