<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Page_Context {
	private const ALLOWED_PAGE_TYPES = array(
		'product',
		'cart',
		'checkout',
		'shop',
		'product_category',
		'product_tag',
		'singular',
		'other',
	);

	/**
	 * @return array<string, mixed>
	 */
	public function build(): array {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$post = $this->get_current_post();
			if ( $post instanceof WP_Post ) {
				return array(
					'page_type'  => 'product',
					'title'      => $post->post_title,
					'url'        => get_permalink( $post->ID ),
					'post_id'    => $post->ID,
					'post_type'  => $post->post_type,
					'product_id' => $post->ID,
				);
			}
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return $this->build_woocommerce_page_context( 'cart', 'Cart', function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $this->get_request_url() );
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $this->get_request_url();
			return $this->build_woocommerce_page_context( 'checkout', 'Checkout', $checkout_url );
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$shop_url = function_exists( 'get_post_type_archive_link' ) ? get_post_type_archive_link( 'product' ) : $this->get_request_url();
			return $this->build_woocommerce_page_context( 'shop', 'Shop', is_string( $shop_url ) ? $shop_url : $this->get_request_url() );
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term = $this->get_current_term();
			if ( $term instanceof WP_Term ) {
				$url = function_exists( 'get_term_link' ) ? get_term_link( $term ) : $this->get_request_url();
				return array(
					'page_type' => 'product_category',
					'title'     => $term->name,
					'url'       => is_string( $url ) ? $url : $this->get_request_url(),
					'term_id'   => $term->term_id,
					'taxonomy'  => $term->taxonomy,
					'term_slug' => $term->slug,
					'term_name' => $term->name,
				);
			}
		}

		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			$term = $this->get_current_term();
			if ( $term instanceof WP_Term ) {
				$url = function_exists( 'get_term_link' ) ? get_term_link( $term ) : $this->get_request_url();
				return array(
					'page_type' => 'product_tag',
					'title'     => $term->name,
					'url'       => is_string( $url ) ? $url : $this->get_request_url(),
					'term_id'   => $term->term_id,
					'taxonomy'  => $term->taxonomy,
					'term_slug' => $term->slug,
					'term_name' => $term->name,
				);
			}
		}

		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$post = $this->get_current_post();
			if ( $post instanceof WP_Post ) {
				return array(
					'page_type' => 'singular',
					'title'     => $post->post_title,
					'url'       => get_permalink( $post->ID ),
					'post_id'   => $post->ID,
					'post_type' => $post->post_type,
				);
			}
		}

		return array(
			'page_type' => 'other',
			'title'     => $this->get_fallback_title(),
			'url'       => $this->get_request_url(),
		);
	}

	/**
	 * @param mixed $page_context
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $page_context ): array {
		if ( ! is_array( $page_context ) ) {
			return array();
		}

		$page_type = isset( $page_context['page_type'] ) && is_string( $page_context['page_type'] ) ? sanitize_key( $page_context['page_type'] ) : '';
		$title     = isset( $page_context['title'] ) && is_scalar( $page_context['title'] ) ? sanitize_text_field( (string) $page_context['title'] ) : '';
		$url       = isset( $page_context['url'] ) && is_scalar( $page_context['url'] ) ? esc_url_raw( (string) $page_context['url'] ) : '';

		if ( '' === $page_type || ! in_array( $page_type, self::ALLOWED_PAGE_TYPES, true ) || '' === $title || '' === $url ) {
			return array();
		}

		$sanitized = array(
			'page_type' => $page_type,
			'title'     => $title,
			'url'       => $this->strip_query_args( $url ),
		);

		foreach ( array( 'post_id', 'product_id', 'term_id' ) as $field ) {
			if ( isset( $page_context[ $field ] ) && is_numeric( $page_context[ $field ] ) ) {
				$sanitized[ $field ] = absint( $page_context[ $field ] );
			}
		}

		foreach ( array( 'post_type', 'taxonomy', 'term_slug' ) as $field ) {
			if ( isset( $page_context[ $field ] ) && is_scalar( $page_context[ $field ] ) ) {
				$value = sanitize_key( (string) $page_context[ $field ] );
				if ( '' !== $value ) {
					$sanitized[ $field ] = $value;
				}
			}
		}

		if ( isset( $page_context['term_name'] ) && is_scalar( $page_context['term_name'] ) ) {
			$term_name = sanitize_text_field( (string) $page_context['term_name'] );
			if ( '' !== $term_name ) {
				$sanitized['term_name'] = $term_name;
			}
		}

		return $sanitized;
	}

	/**
	 * @param array<string, mixed> $page_context
	 */
	public function to_prompt_summary( array $page_context ): string {
		$sanitized = $this->sanitize( $page_context );
		if ( empty( $sanitized ) ) {
			return '';
		}

		$json = wp_json_encode( $sanitized );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		return " Current page context: {$json}. Mention this only when it materially helps answer the user.";
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_woocommerce_page_context( string $page_type, string $fallback_title, string $url ): array {
		$page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( $page_type ) : 0;
		$post    = $page_id > 0 ? get_post( $page_id ) : null;

		$context = array(
			'page_type' => $page_type,
			'title'     => $post instanceof WP_Post && '' !== $post->post_title ? $post->post_title : $fallback_title,
			'url'       => $url,
		);

		if ( $post instanceof WP_Post ) {
			$context['post_id']   = $post->ID;
			$context['post_type'] = $post->post_type;
		}

		return $context;
	}

	private function get_current_post(): ?WP_Post {
		if ( function_exists( 'get_queried_object' ) ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post ) {
				return $queried;
			}
		}

		if ( function_exists( 'get_queried_object_id' ) ) {
			$post_id = get_queried_object_id();
			if ( $post_id > 0 ) {
				return get_post( $post_id );
			}
		}

		return null;
	}

	private function get_current_term(): ?WP_Term {
		if ( function_exists( 'get_queried_object' ) ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Term ) {
				return $queried;
			}
		}

		return null;
	}

	private function get_fallback_title(): string {
		if ( function_exists( 'get_queried_object' ) ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post && '' !== $queried->post_title ) {
				return $queried->post_title;
			}
			if ( $queried instanceof WP_Term && '' !== $queried->name ) {
				return $queried->name;
			}
		}

		if ( function_exists( 'wp_get_document_title' ) ) {
			$title = wp_get_document_title();
			if ( is_string( $title ) && '' !== trim( $title ) ) {
				return $title;
			}
		}

		return 'Current page';
	}

	private function get_request_url(): string {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
		if ( ! is_string( $request_uri ) || '' === $request_uri ) {
			return home_url( '/' );
		}

		$path = parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/';
		}

		if ( ! str_starts_with( $path, '/' ) ) {
			$path = '/' . $path;
		}

		return home_url( $path );
	}

	private function strip_query_args( string $url ): string {
		$without_fragment = preg_replace( '/#.*$/', '', $url );
		$without_query    = preg_replace( '/\?.*$/', '', is_string( $without_fragment ) ? $without_fragment : $url );
		return is_string( $without_query ) ? $without_query : $url;
	}
}
