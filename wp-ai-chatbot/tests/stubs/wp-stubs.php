<?php
/**
 * WordPress function stubs for unit testing.
 *
 * These provide minimal implementations that allow tests to run without WordPress.
 */

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_title = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public string $post_type = 'post';
		public string $post_status = 'publish';
		public string $post_password = '';

		/**
		 * @param array<string, mixed> $data
		 */
		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public int $term_id = 0;
		public string $name = '';
		public string $slug = '';
		public string $taxonomy = '';
		public int $count = 0;
		public int $parent = 0;

		/**
		 * @param array<string, mixed> $data
		 */
		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		/** @var array<int, WP_Post|int> */
		public array $posts = array();

		/** @var array<string, mixed> */
		public array $query_vars = array();

		/**
		 * @param array<string, mixed> $args
		 */
		public function __construct( array $args = array() ) {
			$this->query_vars = $args;
			$posts            = WPAICTestHelper::get_mock_query_posts( $args );

			// Handle 'fields' => 'ids' parameter
			if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
				$this->posts = array_map( fn( $p ) => $p->ID, $posts );
			} else {
				$this->posts = $posts;
			}
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		/** @var array<string, mixed> */
		private array $data;

		/**
		 * @param array<string, mixed> $data
		 */
		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * @return array<string, mixed>
		 */
		public function get_error_data(): array {
			return $this->data;
		}
	}
}

/**
 * @template T of array<string, mixed>
 */
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private array $params = array();
		/** @var array<string, string> */
		private array $headers = array();

		/**
		 * @param array<string, mixed> $params
		 */
		public function set_params( array $params ): void {
			$this->params = $params;
		}

		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}

		public function set_header( string $key, string $value ): void {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		public function get_header( string $key ): ?string {
			return $this->headers[ strtolower( $key ) ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private mixed $data;
		/** @var int */
		private int $status;

		public function __construct( mixed $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_data(): mixed {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}

		/** @var array<string, string> */
		private array $headers = array();

		public function header( string $name, string $value ): void {
			$this->headers[ $name ] = $value;
		}

		/** @return array<string, string> */
		public function get_headers(): array {
			return $this->headers;
		}
	}
}

if ( ! function_exists( 'wp_get_nocache_headers' ) ) {
	/** @return array<string, string> */
	function wp_get_nocache_headers(): array {
		return array(
			'Expires'       => 'Wed, 11 Jan 1984 05:00:00 GMT',
			'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
		);
	}
}

if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( mixed $response ): WP_REST_Response {
		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}
		return new WP_REST_Response( $response );
	}
}

/**
 * Test helper for mocking WordPress data.
 */
class WPAICTestHelper {
	/** @var array<int, WP_Post> */
	private static array $mock_posts = array();

	/** @var array<int, array<string, mixed>> */
	private static array $mock_post_meta = array();

	/** @var array<int, WP_Term> */
	private static array $mock_terms = array();

	/** @var array<string, mixed> */
	private static array $mock_options = array();

	/** @var array<string, mixed> */
	private static array $mock_transients = array();

	/** @var array<int, array<string, array<int, string>>> */
	private static array $mock_post_terms = array();

	/** @var array<string, MockWCOrder> */
	public static array $mock_orders = array();

	/** @var array<string, bool> */
	private static array $conditionals = array();

	private static WP_Post|WP_Term|null $queried_object = null;

	/** @var array<string, int> */
	private static array $wc_page_ids = array();

	/** @var array<string, mixed>|null */
	private static ?array $last_query_vars = null;

	/** @var array<string, array<int, callable>> */
	private static array $mock_filters = array();

	public static function reset(): void {
		self::$mock_posts      = array();
		self::$mock_post_meta  = array();
		self::$mock_terms      = array();
		self::$mock_options    = array();
		self::$mock_transients = array();
		self::$mock_post_terms = array();
		self::$mock_orders     = array();
		self::$conditionals    = array();
		self::$queried_object  = null;
		self::$wc_page_ids     = array();
		self::$last_query_vars = null;
		self::$mock_filters    = array();
		unset( $_SERVER['REQUEST_URI'] );

		if ( isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof MockWpdb ) {
			$GLOBALS['wpdb']->reset();
		}
	}

	/**
	 * Add a mock WooCommerce order.
	 *
	 * @param array<string, mixed> $data Order data.
	 * @return MockWCOrder The mock order.
	 */
	public static function add_mock_order( array $data ): MockWCOrder {
		$order                                       = new MockWCOrder( $data );
		self::$mock_orders[ $order->get_order_number() ] = $order;
		return $order;
	}

	/**
	 * Get a mock WooCommerce order.
	 *
	 * @param int|string $order_id Order ID or number.
	 * @return MockWCOrder|false Order or false if not found.
	 */
	public static function get_mock_order( $order_id ): MockWCOrder|false {
		$order_id = (string) $order_id;
		return self::$mock_orders[ $order_id ] ?? false;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function add_mock_post( array $data ): WP_Post {
		$post                        = new WP_Post( $data );
		self::$mock_posts[ $post->ID ] = $post;
		return $post;
	}

	public static function get_mock_post( int $id ): ?WP_Post {
		return self::$mock_posts[ $id ] ?? null;
	}

	/**
	 * @param array<string, mixed> $query_args
	 * @return array<int, WP_Post>
	 */
	public static function get_mock_query_posts( array $query_args ): array {
		self::$last_query_vars = $query_args;
		$posts                 = array_values( self::$mock_posts );

		if ( ! empty( $query_args['post_type'] ) ) {
			$type = $query_args['post_type'];
			if ( is_array( $type ) ) {
				$posts = array_filter(
					$posts,
					fn( $p ) => in_array( $p->post_type, $type, true )
				);
			} else {
				$posts = array_filter(
					$posts,
					fn( $p ) => $p->post_type === $type
				);
			}
		}

		if ( ! empty( $query_args['post_status'] ) ) {
			$status = $query_args['post_status'];
			$posts  = array_filter(
				$posts,
				fn( $p ) => $p->post_status === $status
			);
		}

		if ( isset( $query_args['has_password'] ) && false === $query_args['has_password'] ) {
			$posts = array_filter(
				$posts,
				fn( $p ) => '' === $p->post_password
			);
		}

		if ( ! empty( $query_args['post__in'] ) && is_array( $query_args['post__in'] ) ) {
			$include_ids = array_map( 'intval', $query_args['post__in'] );
			$posts       = array_filter(
				$posts,
				fn( $p ) => in_array( $p->ID, $include_ids, true )
			);
		}

		if ( ! empty( $query_args['s'] ) ) {
			// Mirror WP_Query: split the search into whitespace-separated terms,
			// every term must appear (LIKE %term%) in the title or content.
			$search_terms = preg_split( '/\s+/', strtolower( trim( $query_args['s'] ) ) ) ?: array();
			$posts        = array_filter(
				$posts,
				function ( $p ) use ( $search_terms ) {
					$haystack = strtolower( $p->post_title . ' ' . $p->post_content );
					foreach ( $search_terms as $term ) {
						if ( '' !== $term && ! str_contains( $haystack, $term ) ) {
							return false;
						}
					}
					return true;
				}
			);
		}

		if ( ! empty( $query_args['tax_query'] ) && is_array( $query_args['tax_query'] ) ) {
			foreach ( $query_args['tax_query'] as $tax_query ) {
				if ( ! is_array( $tax_query ) || ! isset( $tax_query['taxonomy'], $tax_query['terms'] ) ) {
					continue;
				}
				$taxonomy = $tax_query['taxonomy'];
				$terms    = (array) $tax_query['terms'];
				$field    = $tax_query['field'] ?? 'term_id';

				$posts = array_filter(
					$posts,
					function ( $p ) use ( $taxonomy, $terms, $field ) {
						$post_terms = self::get_post_term_slugs( $p->ID, $taxonomy );
						if ( 'slug' === $field ) {
							return count( array_intersect( $post_terms, $terms ) ) > 0;
						}
						return false;
					}
				);
			}
		}

		if ( ! empty( $query_args['meta_query'] ) && is_array( $query_args['meta_query'] ) ) {
			$relation = strtoupper( (string) ( $query_args['meta_query']['relation'] ?? 'AND' ) );
			$clauses  = array_values(
				array_filter(
					$query_args['meta_query'],
					fn( $clause ) => is_array( $clause ) && isset( $clause['key'] )
				)
			);

			if ( 'OR' === $relation && count( $clauses ) > 0 ) {
				$posts = array_filter(
					$posts,
					function ( $p ) use ( $clauses ) {
						foreach ( $clauses as $clause ) {
							if ( self::meta_clause_matches( $p, $clause ) ) {
								return true;
							}
						}
						return false;
					}
				);
			} else {
				foreach ( $clauses as $clause ) {
					$posts = array_filter(
						$posts,
						fn( $p ) => self::meta_clause_matches( $p, $clause )
					);
				}
			}
		}

		$posts = array_values( $posts );

		if ( isset( $query_args['orderby'] ) ) {
			$posts = self::apply_orderby( $posts, $query_args );
		}

		$limit = $query_args['posts_per_page'] ?? 10;
		if ( -1 === $limit ) {
			return $posts;
		}
		return array_slice( $posts, 0, $limit );
	}

	/**
	 * Evaluate one meta_query clause against a post, mirroring the WP_Query
	 * compare operators the plugin uses (numeric comparisons, equality, EXISTS).
	 *
	 * @param array<string, mixed> $clause
	 */
	private static function meta_clause_matches( WP_Post $post, array $clause ): bool {
		$key     = $clause['key'];
		$value   = $clause['value'] ?? null;
		$compare = strtoupper( (string) ( $clause['compare'] ?? '=' ) );

		$meta_exists = isset( self::$mock_post_meta[ $post->ID ][ $key ] );
		$meta_value  = self::get_post_meta( $post->ID, $key, true );
		$meta_float  = is_numeric( $meta_value ) ? (float) $meta_value : 0;
		$cmp_float   = is_numeric( $value ) ? (float) $value : 0;

		return match ( $compare ) {
			'>='         => $meta_float >= $cmp_float,
			'<='         => $meta_float <= $cmp_float,
			'>'          => $meta_float > $cmp_float,
			'<'          => $meta_float < $cmp_float,
			'='          => $meta_value == $value,
			'!='         => $meta_value != $value,
			'EXISTS'     => $meta_exists,
			'NOT EXISTS' => ! $meta_exists,
			default      => true,
		};
	}

	/**
	 * Honor a recognized `orderby` on already-filtered posts. Only the forms the
	 * plugin actually uses are supported; anything else preserves insertion order.
	 *
	 * Supported:
	 *  - 'post__in'                          → preserve the `post__in` array order
	 *  - array( '<clause>' => 'ASC'|'DESC' ) → sort by that meta_query clause's numeric meta value
	 *  - 'date'                              → sort by post_date
	 *
	 * @param array<int, WP_Post> $posts
	 * @param array<string, mixed> $query_args
	 * @return array<int, WP_Post>
	 */
	private static function apply_orderby( array $posts, array $query_args ): array {
		$orderby = $query_args['orderby'];

		if ( 'post__in' === $orderby ) {
			$order = array();
			foreach ( (array) ( $query_args['post__in'] ?? array() ) as $position => $id ) {
				$order[ (int) $id ] = $position;
			}
			usort(
				$posts,
				fn( $a, $b ) => ( $order[ $a->ID ] ?? PHP_INT_MAX ) <=> ( $order[ $b->ID ] ?? PHP_INT_MAX )
			);
			return $posts;
		}

		if ( is_array( $orderby ) ) {
			$clause     = array_key_first( $orderby );
			$direction  = strtoupper( (string) $orderby[ $clause ] );
			$meta_query = $query_args['meta_query'] ?? array();
			$meta_key   = is_array( $meta_query ) && isset( $meta_query[ $clause ]['key'] ) ? $meta_query[ $clause ]['key'] : null;
			if ( null === $meta_key ) {
				return $posts;
			}
			usort(
				$posts,
				function ( $a, $b ) use ( $meta_key, $direction ) {
					$av = (float) self::get_post_meta( $a->ID, $meta_key, true );
					$bv = (float) self::get_post_meta( $b->ID, $meta_key, true );
					return 'ASC' === $direction ? $av <=> $bv : $bv <=> $av;
				}
			);
			return $posts;
		}

		if ( 'date' === $orderby ) {
			$direction = strtoupper( (string) ( $query_args['order'] ?? 'DESC' ) );
			usort(
				$posts,
				function ( $a, $b ) use ( $direction ) {
					$av = $a->post_date ?? '';
					$bv = $b->post_date ?? '';
					return 'ASC' === $direction ? $av <=> $bv : $bv <=> $av;
				}
			);
			return $posts;
		}

		return $posts;
	}

	/**
	 * Returns the query vars from the most recent WP_Query / get_mock_query_posts() call.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_last_query_vars(): ?array {
		return self::$last_query_vars;
	}

	public static function set_post_meta( int $post_id, string $key, mixed $value ): void {
		if ( ! isset( self::$mock_post_meta[ $post_id ] ) ) {
			self::$mock_post_meta[ $post_id ] = array();
		}
		self::$mock_post_meta[ $post_id ][ $key ] = $value;
	}

	public static function get_post_meta( int $post_id, string $key, bool $single = true ): mixed {
		$meta = self::$mock_post_meta[ $post_id ][ $key ] ?? '';
		return $single ? $meta : array( $meta );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function add_mock_term( array $data ): WP_Term {
		$term                            = new WP_Term( $data );
		self::$mock_terms[ $term->term_id ] = $term;
		return $term;
	}

	/**
	 * @return array<int, WP_Term>
	 */
	public static function get_mock_terms(): array {
		return array_values( self::$mock_terms );
	}

	/**
	 * Associates a post with terms in a taxonomy.
	 *
	 * @param int $post_id
	 * @param string $taxonomy
	 * @param array<int, string> $term_slugs
	 */
	public static function set_post_terms( int $post_id, string $taxonomy, array $term_slugs ): void {
		if ( ! isset( self::$mock_post_terms[ $post_id ] ) ) {
			self::$mock_post_terms[ $post_id ] = array();
		}
		self::$mock_post_terms[ $post_id ][ $taxonomy ] = $term_slugs;
	}

	/**
	 * Gets term slugs for a post in a taxonomy.
	 *
	 * @param int $post_id
	 * @param string $taxonomy
	 * @return array<int, string>
	 */
	public static function get_post_term_slugs( int $post_id, string $taxonomy ): array {
		return self::$mock_post_terms[ $post_id ][ $taxonomy ] ?? array();
	}

	public static function set_option( string $name, mixed $value ): void {
		self::$mock_options[ $name ] = $value;
	}

	public static function get_option( string $name, mixed $default = false ): mixed {
		return self::$mock_options[ $name ] ?? $default;
	}

	public static function delete_option( string $name ): void {
		unset( self::$mock_options[ $name ] );
	}

	public static function set_transient( string $name, mixed $value ): void {
		self::$mock_transients[ $name ] = $value;
	}

	public static function get_transient( string $name, mixed $default = false ): mixed {
		return self::$mock_transients[ $name ] ?? $default;
	}

	public static function delete_transient( string $name ): void {
		unset( self::$mock_transients[ $name ] );
	}

	public static function add_filter( string $hook_name, callable $callback ): void {
		self::$mock_filters[ $hook_name ][] = $callback;
	}

	/**
	 * @param mixed ...$args
	 */
	public static function apply_filters( string $hook_name, mixed $value, ...$args ): mixed {
		foreach ( self::$mock_filters[ $hook_name ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}

	public static function set_conditional( string $name, bool $value ): void {
		self::$conditionals[ $name ] = $value;
	}

	public static function get_conditional( string $name ): bool {
		return self::$conditionals[ $name ] ?? false;
	}

	public static function set_queried_object( WP_Post|WP_Term|null $object ): void {
		self::$queried_object = $object;
	}

	public static function get_queried_object(): WP_Post|WP_Term|null {
		return self::$queried_object;
	}

	public static function set_wc_page_id( string $page, int $post_id ): void {
		self::$wc_page_ids[ $page ] = $post_id;
	}

	public static function get_wc_page_id( string $page ): int {
		return self::$wc_page_ids[ $page ] ?? 0;
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/**
	 * @param string|null $time
	 * @param bool $create_dir
	 * @param bool $refresh_cache
	 * @return array<string, mixed>
	 */
	function wp_upload_dir( ?string $time = null, bool $create_dir = true, bool $refresh_cache = false ): array {
		$base = sys_get_temp_dir() . '/wpaic-test-uploads';
		return array(
			'path'    => $base,
			'url'     => 'http://example.com/wp-content/uploads',
			'subdir'  => '',
			'basedir' => $base,
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( string $target ): bool {
		if ( file_exists( $target ) ) {
			return is_dir( $target );
		}
		return @mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( string $file ): void {
		if ( file_exists( $file ) ) {
			@unlink( $file );
		}
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'strip_shortcodes' ) ) {
	function strip_shortcodes( string $content ): string {
		return preg_replace( '/\[.*?\]/', '', $content );
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args
	 * @return array<int, WP_Post>
	 */
	function get_posts( array $args = array() ): array {
		return WPAICTestHelper::get_mock_query_posts( $args );
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	/**
	 * @param array<string, mixed> $args
	 * @param string $output
	 * @return array<int, string>
	 */
	function get_post_types( array $args = array(), string $output = 'names' ): array {
		return array( 'post', 'page' );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $post_id ): ?WP_Post {
		return WPAICTestHelper::get_mock_post( $post_id );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
		return WPAICTestHelper::get_post_meta( $post_id, $key, $single );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int $post_id ): string {
		return "http://example.com/?p={$post_id}";
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( int $post_id ): int|false {
		$meta = get_post_meta( $post_id, '_thumbnail_id', true );
		return is_numeric( $meta ) ? (int) $meta : false;
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( int $attachment_id ): string|false {
		return $attachment_id > 0 ? "http://example.com/uploads/{$attachment_id}.jpg" : false;
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	/**
	 * @param array<string, mixed> $args
	 * @return array<int, string>|array<int, WP_Term>
	 */
	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = array() ): array {
		$terms = WPAICTestHelper::get_mock_terms();
		if ( isset( $args['fields'] ) && 'names' === $args['fields'] ) {
			return array_map( fn( $t ) => $t->name, $terms );
		}
		return $terms;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	/**
	 * @param array<string, mixed> $args
	 * @return array<int, WP_Term>
	 */
	function get_terms( array $args ): array {
		return WPAICTestHelper::get_mock_terms();
	}
}

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ): bool {
		// WooCommerce registers each global product attribute as a pa_{slug}
		// taxonomy; custom per-product attributes are not taxonomies.
		return str_starts_with( $taxonomy, 'pa_' ) || 'product_cat' === $taxonomy;
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( string $field, string|int $value, string $taxonomy = '' ): WP_Term|false {
		foreach ( WPAICTestHelper::get_mock_terms() as $term ) {
			if ( '' !== $taxonomy && $term->taxonomy !== $taxonomy ) {
				continue;
			}
			$matches = match ( $field ) {
				'slug' => $term->slug === $value,
				'name' => $term->name === $value,
				'id', 'term_id' => $term->term_id === (int) $value,
				default => false,
			};
			if ( $matches ) {
				return $term;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( int $term_id, string $taxonomy = '' ): ?WP_Term {
		foreach ( WPAICTestHelper::get_mock_terms() as $term ) {
			if ( $term->term_id === $term_id ) {
				return $term;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, mixed $default = false ): mixed {
		return WPAICTestHelper::get_option( $name, $default );
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $name, mixed $value ): bool {
		WPAICTestHelper::set_option( $name, $value );
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $name, mixed $value = '' ): bool {
		if ( WPAICTestHelper::get_option( $name, null ) === null ) {
			WPAICTestHelper::set_option( $name, $value );
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $name ): bool {
		WPAICTestHelper::delete_option( $name );
		return true;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show ): string {
		return match ( $show ) {
			'name' => 'Test Site',
			'url' => 'http://example.com',
			default => '',
		};
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	function get_locale(): string {
		return (string) WPAICTestHelper::get_option( 'test_locale', 'en_US' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'http://example.com' . $path;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
		WPAICTestHelper::set_transient( $transient, $value );
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		return WPAICTestHelper::get_transient( $transient, false );
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		WPAICTestHelper::delete_transient( $transient );
		return true;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax(): bool {
		return (bool) WPAICTestHelper::get_option( 'test_doing_ajax', false );
	}
}

if ( ! function_exists( 'is_network_admin' ) ) {
	function is_network_admin(): bool {
		return (bool) WPAICTestHelper::get_option( 'test_is_network_admin', false );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): string|array|int|false|null {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wpaic_is_freemius_configured' ) ) {
	function wpaic_is_freemius_configured(): bool {
		return (bool) WPAICTestHelper::get_option( 'test_freemius_configured', false );
	}
}

if ( ! function_exists( 'wpaic_fs' ) ) {
	function wpaic_fs(): mixed {
		return WPAICTestHelper::get_option( 'test_freemius_instance', null );
	}
}

if ( ! function_exists( 'is_product' ) ) {
	function is_product(): bool {
		return WPAICTestHelper::get_conditional( 'is_product' );
	}
}

if ( ! function_exists( 'is_cart' ) ) {
	function is_cart(): bool {
		return WPAICTestHelper::get_conditional( 'is_cart' );
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	function is_checkout(): bool {
		return WPAICTestHelper::get_conditional( 'is_checkout' );
	}
}

if ( ! function_exists( 'is_shop' ) ) {
	function is_shop(): bool {
		return WPAICTestHelper::get_conditional( 'is_shop' );
	}
}

if ( ! function_exists( 'is_product_category' ) ) {
	function is_product_category(): bool {
		return WPAICTestHelper::get_conditional( 'is_product_category' );
	}
}

if ( ! function_exists( 'is_product_tag' ) ) {
	function is_product_tag(): bool {
		return WPAICTestHelper::get_conditional( 'is_product_tag' );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular(): bool {
		return WPAICTestHelper::get_conditional( 'is_singular' );
	}
}

if ( ! function_exists( 'get_queried_object' ) ) {
	function get_queried_object(): WP_Post|WP_Term|null {
		return WPAICTestHelper::get_queried_object();
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id(): int {
		$queried = WPAICTestHelper::get_queried_object();
		if ( $queried instanceof WP_Post ) {
			return $queried->ID;
		}
		if ( $queried instanceof WP_Term ) {
			return $queried->term_id;
		}
		return 0;
	}
}

if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( WP_Term $term ): string {
		if ( 'product_cat' === $term->taxonomy ) {
			return 'http://example.com/product-category/' . $term->slug . '/';
		}
		if ( 'product_tag' === $term->taxonomy ) {
			return 'http://example.com/product-tag/' . $term->slug . '/';
		}
		return 'http://example.com/term/' . $term->slug . '/';
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( string $post_type ): string|false {
		if ( 'product' === $post_type ) {
			$shop_page_id = WPAICTestHelper::get_wc_page_id( 'shop' );
			if ( $shop_page_id > 0 ) {
				return get_permalink( $shop_page_id );
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wc_get_checkout_url' ) ) {
	function wc_get_checkout_url(): string {
		return 'http://example.com/checkout/';
	}
}

if ( ! function_exists( 'wc_get_page_id' ) ) {
	function wc_get_page_id( string $page ): int {
		return WPAICTestHelper::get_wc_page_id( $page );
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( int $post_id = 0 ): string {
		$post = get_post( $post_id );
		return $post instanceof WP_Post ? $post->post_title : '';
	}
}

if ( ! function_exists( 'wp_get_document_title' ) ) {
	function wp_get_document_title(): string {
		$queried = WPAICTestHelper::get_queried_object();
		if ( $queried instanceof WP_Post ) {
			return $queried->post_title;
		}
		if ( $queried instanceof WP_Term ) {
			return $queried->name;
		}
		return 'Current page';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $data ): string|false {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( $str );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		return trim( preg_replace( '/[^a-z0-9_]+/', '-', strtolower( $title ) ), '-' );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( string $color ): ?string {
		if ( '' === $color ) {
			return '';
		}
		if ( preg_match( '/^#[a-fA-F0-9]{6}$/', $color ) ) {
			return $color;
		}
		if ( preg_match( '/^#[a-fA-F0-9]{3}$/', $color ) ) {
			return $color;
		}
		return null;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return WPAICTestHelper::get_option( 'test_current_user_id', 0 );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type ): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( string $format, int|false $timestamp = false ): string {
		return gmdate( $format, false === $timestamp ? time() : $timestamp );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
	}
}

if ( ! function_exists( 'wp_is_uuid' ) ) {
	function wp_is_uuid( mixed $uuid, ?int $version = null ): bool {
		if ( ! is_string( $uuid ) ) {
			return false;
		}

		if ( is_numeric( $version ) ) {
			if ( 4 !== (int) $version ) {
				return false;
			}
			$regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
		} else {
			$regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
		}

		return (bool) preg_match( $regex, $uuid );
	}
}

class MockWpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 0;

	/** @var array<int, string> Raw queries passed to query(), for assertions. */
	public array $queries = array();

	/** @var array<string, array<int, array<string, mixed>>> */
	private array $tables = array();
	private int $auto_increment = 1;

	public function __construct() {
		$this->tables = array(
			'wp_wpaic_conversations'   => array(),
			'wp_wpaic_messages'        => array(),
			'wp_wpaic_faqs'            => array(),
			'wp_wpaic_support_requests' => array(),
			'wp_wpaic_events'          => array(),
		);
	}

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/**
	 * @param string $query
	 * @return int|bool
	 */
	public function query( string $query ): int|bool {
		$this->queries[] = $query;

		if ( preg_match( '/TRUNCATE\s+TABLE\s+(\S+)/i', $query, $matches ) ) {
			$table = $matches[1];
			if ( isset( $this->tables[ $table ] ) ) {
				$this->tables[ $table ] = array();
			}
			return true;
		}

		if ( preg_match( '/DROP\s+TABLE\s+IF\s+EXISTS\s+(\S+)/i', $query, $matches ) ) {
			unset( $this->tables[ $matches[1] ] );
			return true;
		}

		if ( preg_match( '/DELETE\s+FROM\s+(\S+)\s+WHERE\s+(\w+)\s+IN\s*\(([^)]*)\)/i', $query, $matches ) ) {
			$table  = $matches[1];
			$column = $matches[2];
			$values = array_map( 'trim', explode( ',', $matches[3] ) );
			if ( ! isset( $this->tables[ $table ] ) ) {
				return false;
			}
			$deleted = 0;
			foreach ( $this->tables[ $table ] as $id => $row ) {
				if ( in_array( (string) ( $row[ $column ] ?? '' ), $values, true ) ) {
					unset( $this->tables[ $table ][ $id ] );
					++$deleted;
				}
			}
			return $deleted;
		}

		return false;
	}

	/**
	 * @return array<int, string>
	 */
	public function get_col( string $query ): array {
		if ( preg_match( "/SELECT\s+id\s+FROM\s+\S+wpaic_conversations\s+WHERE\s+updated_at\s*<\s*'([^']+)'/i", $query, $matches ) ) {
			$cutoff = $matches[1];
			$ids    = array();
			foreach ( $this->tables['wp_wpaic_conversations'] as $row ) {
				if ( strcmp( (string) ( $row['updated_at'] ?? '' ), $cutoff ) < 0 ) {
					$ids[] = (string) $row['id'];
				}
			}
			return $ids;
		}
		return array();
	}

	/**
	 * @param string $table
	 * @param array<string, mixed> $data
	 * @param array<int, string>|null $format
	 * @return int|false
	 */
	public function insert( string $table, array $data, ?array $format = null ): int|false {
		$id               = $this->auto_increment++;
		$data['id']       = $id;
		$this->insert_id  = $id;

		// Mimic the schema's DEFAULT CURRENT_TIMESTAMP.
		if ( ! isset( $data['created_at'] ) ) {
			$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		}
		if ( ! isset( $data['updated_at'] ) && in_array( $table, array( 'wp_wpaic_conversations', 'wp_wpaic_support_requests' ), true ) ) {
			$data['updated_at'] = $data['created_at'];
		}

		if ( ! isset( $this->tables[ $table ] ) ) {
			$this->tables[ $table ] = array();
		}
		$this->tables[ $table ][ $id ] = $data;
		return 1;
	}

	/**
	 * @param string $table
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $where
	 * @param array<int, string>|null $format
	 * @param array<int, string>|null $where_format
	 * @return int|false
	 */
	public function update( string $table, array $data, array $where, ?array $format = null, ?array $where_format = null ): int|false {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return false;
		}

		$updated = 0;
		foreach ( $this->tables[ $table ] as $id => &$row ) {
			$match = true;
			foreach ( $where as $key => $value ) {
				if ( ! isset( $row[ $key ] ) || $row[ $key ] != $value ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				foreach ( $data as $key => $value ) {
					$row[ $key ] = $value;
				}
				++$updated;
			}
		}
		return $updated;
	}

	/**
	 * @param string $table
	 * @param array<string, mixed> $where
	 * @param array<int, string>|null $where_format
	 * @return int|false
	 */
	public function delete( string $table, array $where, ?array $where_format = null ): int|false {
		if ( ! isset( $this->tables[ $table ] ) ) {
			return false;
		}

		$deleted = 0;
		foreach ( $this->tables[ $table ] as $id => $row ) {
			$match = true;
			foreach ( $where as $key => $value ) {
				if ( ! isset( $row[ $key ] ) || $row[ $key ] != $value ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				unset( $this->tables[ $table ][ $id ] );
				++$deleted;
			}
		}
		return $deleted;
	}

	public function prepare( string $query, mixed ...$args ): string {
		// Single pass so substituted values containing %s/%d (e.g. LIKE '%shoes%') are never re-matched.
		$index = 0;
		return (string) preg_replace_callback(
			'/%[sd]/',
			function ( array $match ) use ( $args, &$index ) {
				$arg = $args[ $index++ ] ?? '';
				return is_string( $arg ) ? "'" . $arg . "'" : (string) $arg;
			},
			$query
		);
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	public function get_var( string $query ): mixed {
		if ( preg_match( '/SELECT\s+id\s+FROM\s+\S+wpaic_conversations\s+WHERE\s+session_id\s*=\s*\'([^\']+)\'/i', $query, $matches ) ) {
			$session_id = $matches[1];
			foreach ( $this->tables['wp_wpaic_conversations'] as $row ) {
				if ( $row['session_id'] === $session_id ) {
					return (string) $row['id'];
				}
			}
			return null;
		}

		// Distinct conversations with an event of a type in range (funnel stages).
		if ( preg_match( "/SELECT\s+COUNT\(DISTINCT\s+conversation_id\)\s+FROM\s+\S+wpaic_events\s+WHERE\s+event_type\s*=\s*'([^']+)'/i", $query, $matches ) ) {
			$event_type = $matches[1];
			$ids        = array();
			foreach ( $this->tables['wp_wpaic_events'] as $row ) {
				if ( ( $row['event_type'] ?? '' ) === $event_type && $this->row_matches_created_at_bounds( $row, $query ) ) {
					$ids[ (int) ( $row['conversation_id'] ?? 0 ) ] = true;
				}
			}
			return (string) count( $ids );
		}

		// Messages joined to conversations created in range (avg messages).
		if ( preg_match( '/COUNT\(\*\)\s+FROM\s+\S+wpaic_messages\s+m\s+INNER\s+JOIN\s+\S+wpaic_conversations\s+c/i', $query ) ) {
			$count = 0;
			foreach ( $this->tables['wp_wpaic_messages'] as $msg ) {
				$conv = $this->tables['wp_wpaic_conversations'][ (int) ( $msg['conversation_id'] ?? 0 ) ] ?? null;
				if ( null !== $conv && $this->row_matches_created_at_bounds( $conv, $query ) ) {
					++$count;
				}
			}
			return (string) $count;
		}

		// Earliest activity timestamp across conversations + events ("all time").
		if ( preg_match( '/MIN\(created_at\)\s+FROM\s*\(/i', $query ) ) {
			$min = null;
			foreach ( array( 'wp_wpaic_conversations', 'wp_wpaic_events' ) as $table ) {
				foreach ( $this->tables[ $table ] as $row ) {
					$created_at = (string) ( $row['created_at'] ?? '' );
					if ( '' !== $created_at && ( null === $min || strcmp( $created_at, $min ) < 0 ) ) {
						$min = $created_at;
					}
				}
			}
			return $min;
		}

		if ( preg_match( '/SELECT\s+COUNT\(\*\)\s+FROM\s+\S+wpaic_conversations/i', $query ) ) {
			return (string) count( $this->filter_conversation_rows( $query ) );
		}

		if ( preg_match( "/SELECT\s+COUNT\(\*\)\s+FROM\s+\S+wpaic_events\s+WHERE\s+event_type\s*=\s*'([^']+)'/i", $query, $matches ) ) {
			$event_type = $matches[1];
			$count      = 0;
			foreach ( $this->tables['wp_wpaic_events'] as $row ) {
				if ( ( $row['event_type'] ?? '' ) === $event_type && $this->row_matches_created_at_bounds( $row, $query ) ) {
					++$count;
				}
			}
			return (string) $count;
		}

		if ( preg_match( "/SELECT\s+COUNT\(\*\)\s+FROM\s+\S+wpaic_support_requests(?:\s+WHERE\s+status\s*=\s*'([^']+)')?/i", $query, $matches ) ) {
			$status = $matches[1] ?? null;
			$count  = 0;
			foreach ( $this->tables['wp_wpaic_support_requests'] as $row ) {
				if ( null === $status || ( $row['status'] ?? '' ) === $status ) {
					++$count;
				}
			}
			return (string) $count;
		}

		// Generic unfiltered COUNT(*) on any known table (e.g. faqs, data sources).
		if ( preg_match( '/SELECT\s+COUNT\(\*\)\s+FROM\s+(\S+)/i', $query, $matches ) && isset( $this->tables[ $matches[1] ] ) ) {
			return (string) count( $this->tables[ $matches[1] ] );
		}

		return null;
	}

	/**
	 * Apply optional created_at bounds (>=, < or <=) parsed from a prepared
	 * query to a row. No bounds in the query means the row matches.
	 *
	 * @param array<string, mixed> $row
	 */
	private function row_matches_created_at_bounds( array $row, string $query ): bool {
		$created_at = (string) ( $row['created_at'] ?? '' );

		if ( preg_match( "/(?:c\.)?created_at\s*>=\s*'([^']+)'/i", $query, $matches ) && strcmp( $created_at, $matches[1] ) < 0 ) {
			return false;
		}

		if ( preg_match( "/(?:c\.)?created_at\s*(<=?)\s*'([^']+)'/i", $query, $matches ) ) {
			$comparison = strcmp( $created_at, $matches[2] );
			if ( '<' === $matches[1] ? $comparison >= 0 : $comparison > 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Conversation rows matching optional created_at bounds and a message
	 * content LIKE filter parsed from a prepared query. No filters means all
	 * rows match, preserving the original mock behavior.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_conversation_rows( string $query ): array {
		$search = null;
		if ( preg_match( "/ms\.content\s+LIKE\s+'%(.*)%'/i", $query, $matches ) ) {
			$search = stripcslashes( $matches[1] );
		}

		$rows = array();
		foreach ( $this->tables['wp_wpaic_conversations'] as $row ) {
			if ( ! $this->row_matches_created_at_bounds( $row, $query ) ) {
				continue;
			}
			if ( null !== $search ) {
				$found = false;
				foreach ( $this->tables['wp_wpaic_messages'] as $msg ) {
					if ( $msg['conversation_id'] == $row['id'] && false !== stripos( (string) ( $msg['content'] ?? '' ), $search ) ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					continue;
				}
			}
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * @return array<int, object>|null
	 */
	public function get_results( string $query ): ?array {
		// Busiest-times heatmap: conversations grouped by day-of-week + hour.
		if ( preg_match( '/FROM\s+\S+wpaic_conversations/i', $query ) && false !== stripos( $query, 'DAYOFWEEK' ) ) {
			$grid = array();
			foreach ( $this->tables['wp_wpaic_conversations'] as $row ) {
				if ( ! $this->row_matches_created_at_bounds( $row, $query ) ) {
					continue;
				}
				$ts = strtotime( (string) ( $row['created_at'] ?? '' ) );
				if ( false === $ts ) {
					continue;
				}
				$dow = ( (int) gmdate( 'N', $ts ) ) - 1; // Monday=0 .. Sunday=6.
				$h   = (int) gmdate( 'G', $ts );
				$key = $dow . '-' . $h;
				if ( ! isset( $grid[ $key ] ) ) {
					$grid[ $key ] = array(
						'dow' => $dow,
						'h'   => $h,
						'c'   => 0,
					);
				}
				++$grid[ $key ]['c'];
			}
			return array_map( static fn( $g ) => (object) $g, array_values( $grid ) );
		}

		// Conversations grouped by calendar day (revenue/conversation series).
		if ( preg_match( '/FROM\s+\S+wpaic_conversations/i', $query ) && false !== stripos( $query, 'GROUP BY DATE' ) ) {
			$by_day = array();
			foreach ( $this->tables['wp_wpaic_conversations'] as $row ) {
				if ( ! $this->row_matches_created_at_bounds( $row, $query ) ) {
					continue;
				}
				$day = substr( (string) ( $row['created_at'] ?? '' ), 0, 10 );
				if ( '' === $day ) {
					continue;
				}
				$by_day[ $day ] = ( $by_day[ $day ] ?? 0 ) + 1;
			}
			$results = array();
			foreach ( $by_day as $day => $count ) {
				$results[] = (object) array(
					'd' => $day,
					'c' => $count,
				);
			}
			return $results;
		}

		if ( preg_match( '/FROM\s+\S+wpaic_conversations/i', $query ) ) {
			$results = array();
			foreach ( $this->filter_conversation_rows( $query ) as $row ) {
				$obj = (object) $row;

				$message_count      = 0;
				$first_user_message = null;
				foreach ( $this->tables['wp_wpaic_messages'] as $msg ) {
					if ( $msg['conversation_id'] == $row['id'] ) {
						++$message_count;
						if ( null === $first_user_message && 'user' === ( $msg['role'] ?? '' ) ) {
							$first_user_message = (string) ( $msg['content'] ?? '' );
						}
					}
				}
				$obj->message_count      = $message_count;
				$obj->first_user_message = $first_user_message;
				$results[]               = $obj;
			}
			if ( preg_match( '/LIMIT\s+(\d+)\s+OFFSET\s+(\d+)/i', $query, $limit_match ) ) {
				$results = array_slice( $results, (int) $limit_match[2], (int) $limit_match[1] );
			}
			return $results;
		}

		if ( preg_match( '/FROM\s+\S+wpaic_messages\s+WHERE\s+conversation_id\s*=\s*(\d+)/i', $query, $matches ) ) {
			$conv_id = (int) $matches[1];
			$results = array();
			foreach ( $this->tables['wp_wpaic_messages'] as $row ) {
				if ( $row['conversation_id'] == $conv_id ) {
					$results[] = (object) $row;
				}
			}
			return $results;
		}

		if ( preg_match( '/FROM\s+\S+wpaic_faqs/i', $query ) ) {
			$results = array();
			foreach ( $this->tables['wp_wpaic_faqs'] as $row ) {
				$results[] = (object) $row;
			}
			if ( preg_match( '/LIMIT\s+(\d+)/i', $query, $limit_match ) ) {
				$results = array_slice( $results, 0, (int) $limit_match[1] );
			}
			return $results;
		}

		if ( preg_match( '/FROM\s+\S+wpaic_support_requests/i', $query ) ) {
			$results = array();
			foreach ( $this->tables['wp_wpaic_support_requests'] as $row ) {
				$results[] = (object) $row;
			}
			return $results;
		}

		if ( preg_match( '/FROM\s+\S+wpaic_events\s+WHERE\s+conversation_id\s*=\s*(\d+)/i', $query, $matches ) ) {
			$conv_id = (int) $matches[1];
			$results = array();
			foreach ( $this->tables['wp_wpaic_events'] as $row ) {
				if ( $row['conversation_id'] == $conv_id ) {
					$results[] = (object) $row;
				}
			}
			return $results;
		}

		if ( preg_match( "/FROM\s+\S+wpaic_events\s+WHERE\s+event_type\s*=\s*'([^']+)'/i", $query, $matches ) ) {
			$event_type = $matches[1];
			$results    = array();
			foreach ( $this->tables['wp_wpaic_events'] as $row ) {
				if ( ( $row['event_type'] ?? '' ) === $event_type && $this->row_matches_created_at_bounds( $row, $query ) ) {
					$results[] = (object) $row;
				}
			}
			return $results;
		}

		return array();
	}

	public function get_row( string $query ): ?object {
		if ( preg_match( '/FROM\s+\S+wpaic_support_requests\s+WHERE\s+id\s*=\s*(\d+)/i', $query, $matches ) ) {
			$id = (int) $matches[1];
			return isset( $this->tables['wp_wpaic_support_requests'][ $id ] )
				? (object) $this->tables['wp_wpaic_support_requests'][ $id ]
				: null;
		}
		return null;
	}

	public function reset(): void {
		$this->tables = array(
			'wp_wpaic_conversations'   => array(),
			'wp_wpaic_messages'        => array(),
			'wp_wpaic_faqs'            => array(),
			'wp_wpaic_support_requests' => array(),
			'wp_wpaic_events'          => array(),
		);
		$this->auto_increment = 1;
		$this->insert_id      = 0;
		$this->queries        = array();
	}
}

$GLOBALS['wpdb'] = new MockWpdb();

if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media(): void {
		// No-op for unit tests.
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), string|bool|null $ver = false, array|bool $args = array() ): void {
		$GLOBALS['wpaic_test_enqueued_scripts'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
			'args' => $args,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), string|bool|null $ver = false, string $media = 'all' ): void {
		$GLOBALS['wpaic_test_enqueued_styles'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * @param array<string, mixed> $l10n
	 */
	function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
		$GLOBALS['wpaic_test_localized_scripts'][ $handle ][ $object_name ] = $l10n;
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): bool {
		$GLOBALS['wpaic_test_inline_scripts'][ $handle ][] = $data;
		return true;
	}
}

if ( ! function_exists( 'wpaic_file_get_contents' ) ) {
	function wpaic_file_get_contents( string $path ): string|false {
		return file_exists( $path ) ? file_get_contents( $path ) : false;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( bool $hard = true ): void {
		// No-op for testing.
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( string $hook, array $args = array() ): int|false {
		$scheduled = WPAICTestHelper::get_option( 'test_scheduled_events', array() );
		return $scheduled[ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = array() ): bool {
		$scheduled          = WPAICTestHelper::get_option( 'test_scheduled_events', array() );
		$scheduled[ $hook ] = $timestamp;
		WPAICTestHelper::set_option( 'test_scheduled_events', $scheduled );
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = array() ): int {
		$scheduled = WPAICTestHelper::get_option( 'test_scheduled_events', array() );
		unset( $scheduled[ $hook ] );
		WPAICTestHelper::set_option( 'test_scheduled_events', $scheduled );
		return 0;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = array() ): bool {
		$scheduled          = WPAICTestHelper::get_option( 'test_scheduled_events', array() );
		$scheduled[ $hook ] = $timestamp;
		WPAICTestHelper::set_option( 'test_scheduled_events', $scheduled );
		return true;
	}
}

if ( ! function_exists( 'wp_privacy_anonymize_ip' ) ) {
	/**
	 * Simplified version of core: zero the host portion of the address.
	 */
	function wp_privacy_anonymize_ip( string $ip_addr, bool $ipv6_fallback = false ): string {
		if ( str_contains( $ip_addr, ':' ) ) {
			$parts = explode( ':', $ip_addr );
			return implode( ':', array_slice( $parts, 0, 4 ) ) . '::';
		}
		$parts = explode( '.', $ip_addr );
		if ( 4 === count( $parts ) ) {
			$parts[3] = '0';
		}
		return implode( '.', $parts );
	}
}

/**
 * Plugin activation function.
 */
function wpaic_activate(): void {
	add_option(
		'wpaic_settings',
		array(
			'model'                 => 'gpt-5-mini',
			'greeting_message'      => 'Hello! How can I help you today?',
			'enabled'               => true,
			'system_prompt'         => '',
			'theme_color'           => '#2545B8',
			'conversation_starters' => array(),
			'provider_url_override' => '',
			'retention_days'        => 0,
			'anonymize_ip'          => true,
		)
	);

	wpaic_create_tables();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( 'wpaic_daily_retention' ) ) {
		wp_schedule_event( time(), 'daily', 'wpaic_daily_retention' );
	}

	// Mirrors the real activation hook: one-time redirect to the settings page.
	set_transient( 'wpaic_activation_redirect', true, 60 );
}

/**
 * Create plugin database tables.
 */
function wpaic_create_tables(): void {
	// No-op for unit tests - tables handled by MockWpdb.
}

if ( ! function_exists( 'register_setting' ) ) {
	/**
	 * @param string $option_group
	 * @param string $option_name
	 * @param array<string, mixed> $args
	 */
	function register_setting( string $option_group, string $option_name, array $args = array() ): void {
		// No-op for unit tests.
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	/**
	 * @param string $id
	 * @param string $title
	 * @param callable|string $callback
	 * @param string $page
	 */
	function add_settings_section( string $id, string $title, $callback, string $page ): void {
		// No-op for unit tests.
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	/**
	 * @param string $id
	 * @param string $title
	 * @param callable $callback
	 * @param string $page
	 * @param string $section
	 * @param array<string, mixed> $args
	 */
	function add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = array() ): void {
		// No-op for unit tests.
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * @param string $page_title
	 * @param string $menu_title
	 * @param string $capability
	 * @param string $menu_slug
	 * @param callable|null $callback
	 * @param string $icon_url
	 * @param int|float|null $position
	 * @return string
	 */
	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, string $icon_url = '', int|float|null $position = null ): string {
		$GLOBALS['wpaic_test_menu_pages'][] = array(
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'menu_slug'  => $menu_slug,
		);
		return $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * @param string $parent_slug
	 * @param string $page_title
	 * @param string $menu_title
	 * @param string $capability
	 * @param string $menu_slug
	 * @param callable|null $callback
	 * @param int|float|null $position
	 * @return string|false
	 */
	function add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, int|float|null $position = null ): string|false {
		$GLOBALS['wpaic_test_submenu_pages'][] = array(
			'parent_slug' => $parent_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'menu_slug'   => $menu_slug,
		);
		return $menu_slug;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string $hook_name
	 * @param callable $callback
	 * @param int $priority
	 * @param int $accepted_args
	 * @return true
	 */
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return WPAICTestHelper::get_option( 'test_user_can_' . $capability, false );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( mixed $selected, mixed $current = true, bool $display = true ): string {
		$result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $text, string $domain = 'default' ): void {
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( string $text ): string {
		return addslashes( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url, ?array $protocols = null, string $_context = 'display' ): string {
		return $url;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url, ?array $protocols = null ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email, bool $deprecated = false ): string|false {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ?: false;
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	/**
	 * @param string|string[] $to
	 * @param string $subject
	 * @param string $message
	 * @param string|string[] $headers
	 * @param string|string[] $attachments
	 * @return bool
	 */
	function wp_mail( $to, string $subject, string $message, $headers = '', $attachments = array() ): bool {
		WPAICTestHelper::set_option( 'test_last_mail', array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
		) );
		return true;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param string|array<string, mixed> $key
	 * @param string|int|null             $value
	 * @param string|null                 $url
	 * @return string
	 */
	function add_query_arg( $key, $value = null, $url = null ): string {
		if ( is_array( $key ) ) {
			$base_url = $value ?? '';
			$args     = $key;
		} else {
			$base_url = $url ?? '';
			$args     = array( $key => $value );
		}
		$query = http_build_query( $args );
		return $base_url . ( strpos( $base_url, '?' ) !== false ? '&' : '?' ) . $query;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '', string $scheme = 'admin' ): string {
		return 'https://example.com/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '', ?string $scheme = null ): string {
		return 'https://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals );
	}
}

if ( ! function_exists( 'get_admin_page_title' ) ) {
	function get_admin_page_title(): string {
		return 'AI Chatbot Settings';
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $option_group ): void {
		// No-op for unit tests.
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';

		if ( $display ) {
			echo $field;
		}

		return $field;
	}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( string $page ): void {
		// No-op for unit tests.
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = 'Save Changes', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null ): void {
		echo '<input type="submit" name="' . esc_attr( $name ) . '" class="button button-' . esc_attr( $type ) . '" value="' . esc_attr( $text ) . '" />';
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action ): string {
		return 'test_nonce_' . $action;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( string $nonce, string $action = '' ): int|false {
		$expected = 'test_nonce_' . $action;
		return $nonce === $expected ? 1 : false;
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( string $action, string|false $query_arg = false, bool $stop = true ): int|false {
		return 1;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): int|false {
		return 1;
	}
}

class WPAICJsonResponseException extends Exception {
	public mixed $data;
	public bool $success;

	public function __construct( mixed $data, bool $success ) {
		$this->data    = $data;
		$this->success = $success;
		parent::__construct( $success ? 'JSON Success' : 'JSON Error' );
	}
}

class WPAICRedirectException extends Exception {
	public string $location;

	public function __construct( string $location ) {
		$this->location = $location;
		parent::__construct( 'Redirect: ' . $location );
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	/**
	 * @param mixed $data
	 * @param int|null $status_code
	 * @param int $flags
	 * @throws WPAICJsonResponseException
	 */
	function wp_send_json_success( $data = null, ?int $status_code = null, int $flags = 0 ): never {
		throw new WPAICJsonResponseException( $data, true );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	/**
	 * @param mixed $data
	 * @param int|null $status_code
	 * @param int $flags
	 * @throws WPAICJsonResponseException
	 */
	function wp_send_json_error( $data = null, ?int $status_code = null, int $flags = 0 ): never {
		throw new WPAICJsonResponseException( $data, false );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location, int $status = 302, string $x_redirect_by = 'WordPress' ): never {
		throw new WPAICRedirectException( $location );
	}
}

if ( ! function_exists( 'get_user_by' ) ) {
	/**
	 * @param string $field
	 * @param int|string $value
	 * @return object|false
	 */
	function get_user_by( string $field, $value ): object|false {
		return false;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null ): string|false {
		return date( $format, $timestamp ?? time() );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param array<string, mixed>|string $key
	 * @param mixed $value
	 * @param string $url
	 * @return string
	 */
	function add_query_arg( $key, $value = null, string $url = '' ): string {
		if ( is_array( $key ) ) {
			return $url . '?' . http_build_query( $key );
		}
		return $url . '?' . $key . '=' . $value;
	}
}

if ( ! function_exists( 'paginate_links' ) ) {
	/**
	 * @param array<string, mixed> $args
	 * @return string|string[]|void
	 */
	function paginate_links( array $args = array() ) {
		return '<span class="pagination">1 2 3</span>';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param string|array<mixed> $value
	 * @return string|array<mixed>
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
	}
}

if ( ! function_exists( 'stripslashes_deep' ) ) {
	/**
	 * @param mixed $value
	 * @return mixed
	 */
	function stripslashes_deep( $value ) {
		return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
}

if ( ! function_exists( 'wpaic_is_woocommerce_active' ) ) {
	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool True if WooCommerce is active.
	 */
	function wpaic_is_woocommerce_active(): bool {
		return WPAICTestHelper::get_option( 'test_woocommerce_active', true );
	}
}

if ( ! function_exists( 'wc_get_cart_url' ) ) {
	/**
	 * Get cart URL for WooCommerce.
	 *
	 * @return string Cart URL.
	 */
	function wc_get_cart_url(): string {
		return 'http://example.com/cart/';
	}
}

if ( ! function_exists( 'wc_attribute_label' ) ) {
	/**
	 * @param string $name
	 * @param mixed $product
	 */
	function wc_attribute_label( string $name, $product = null ): string {
		return ucfirst( preg_replace( '/^pa_/', '', $name ) );
	}
}

if ( ! class_exists( 'WC_DateTime' ) ) {
	class WC_DateTime extends DateTime {
		public function date( string $format ): string {
			return $this->format( $format );
		}
	}
}

if ( ! class_exists( 'WC_Order_Item' ) ) {
	class WC_Order_Item {
		/** @var array<string, mixed> */
		private array $data;

		/**
		 * @param array<string, mixed> $data
		 */
		public function __construct( array $data = array() ) {
			$this->data = $data;
		}

		public function get_name(): string {
			return $this->data['name'] ?? '';
		}

		public function get_quantity(): int {
			return (int) ( $this->data['quantity'] ?? 1 );
		}
	}
}

if ( ! class_exists( 'MockWCOrder' ) ) {
	class MockWCOrder {
		/** @var array<string, mixed> */
		private array $data;
		/** @var array<string, mixed> */
		private array $meta = array();
		/** @var array<int, WC_Order_Item> */
		private array $items = array();

		/**
		 * @param array<string, mixed> $data
		 */
		public function __construct( array $data = array() ) {
			$this->data  = $data;
			$this->items = $data['items'] ?? array();
			$this->meta  = $data['meta'] ?? array();
		}

		public function get_order_number(): string {
			return (string) ( $this->data['order_number'] ?? $this->data['id'] ?? '' );
		}

		public function get_status(): string {
			return $this->data['status'] ?? 'pending';
		}

		public function get_billing_email(): string {
			return $this->data['billing_email'] ?? '';
		}

		public function get_date_created(): ?WC_DateTime {
			if ( isset( $this->data['date_created'] ) ) {
				return new WC_DateTime( $this->data['date_created'] );
			}
			return null;
		}

		public function get_total(): float {
			return (float) ( $this->data['total'] ?? 0 );
		}

		public function get_formatted_order_total(): string {
			return '$' . number_format( $this->get_total(), 2 );
		}

		/**
		 * @return array<int, WC_Order_Item>
		 */
		public function get_items(): array {
			return $this->items;
		}

		public function get_formatted_line_subtotal( WC_Order_Item $item ): string {
			return '$' . number_format( 10.00, 2 );
		}

		public function get_shipping_method(): string {
			return $this->data['shipping_method'] ?? '';
		}

		public function get_meta( string $key ): mixed {
			return $this->meta[ $key ] ?? '';
		}

		public function update_meta_data( string $key, mixed $value ): void {
			$this->meta[ $key ] = $value;
		}

		public function save(): int {
			return (int) ( $this->data['id'] ?? 0 );
		}

		public function get_currency(): string {
			return (string) ( $this->data['currency'] ?? 'USD' );
		}
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * Get WooCommerce order by ID or number.
	 *
	 * @param int|string $order_id Order ID or number.
	 * @return MockWCOrder|false Order object or false.
	 */
	function wc_get_order( $order_id ): MockWCOrder|false {
		return WPAICTestHelper::get_mock_order( $order_id );
	}
}

if ( ! function_exists( 'wc_get_is_paid_statuses' ) ) {
	/**
	 * @return array<int, string>
	 */
	function wc_get_is_paid_statuses(): array {
		return array( 'processing', 'completed' );
	}
}

if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
	function get_woocommerce_currency_symbol( string $currency = '' ): string {
		return WPAICTestHelper::get_option( 'woocommerce_currency_symbol', '$' );
	}
}

if ( ! function_exists( 'wc_get_orders' ) ) {
	/**
	 * Minimal mock honoring 'status' (array) and a 'date_created' => 'after...before'
	 * unix-timestamp range against seeded MockWCOrders.
	 *
	 * @param array<string, mixed> $args
	 * @return array<int, MockWCOrder|int>
	 */
	function wc_get_orders( array $args = array() ): array {
		$statuses = isset( $args['status'] ) ? (array) $args['status'] : array();
		$after    = null;
		$before   = null;
		if ( isset( $args['date_created'] ) && is_string( $args['date_created'] ) && str_contains( $args['date_created'], '...' ) ) {
			list( $after, $before ) = array_map( 'intval', explode( '...', $args['date_created'] ) );
		}
		$return  = $args['return'] ?? 'objects';
		$results = array();
		foreach ( WPAICTestHelper::$mock_orders as $order ) {
			if ( ! empty( $statuses ) && ! in_array( $order->get_status(), $statuses, true ) ) {
				continue;
			}
			$created = $order->get_date_created();
			$ts      = $created instanceof DateTimeInterface ? $created->getTimestamp() : null;
			if ( null !== $ts && null !== $after && $ts < $after ) {
				continue;
			}
			if ( null !== $ts && null !== $before && $ts > $before ) {
				continue;
			}
			$results[] = 'ids' === $return ? (int) $order->get_order_number() : $order;
		}
		return $results;
	}
}

if ( ! function_exists( 'wc_get_order_status_name' ) ) {
	/**
	 * Get readable order status name.
	 *
	 * @param string $status Status slug.
	 * @return string Readable status name.
	 */
	function wc_get_order_status_name( string $status ): string {
		$statuses = array(
			'pending'    => 'Pending payment',
			'processing' => 'Processing',
			'on-hold'    => 'On hold',
			'completed'  => 'Completed',
			'cancelled'  => 'Cancelled',
			'refunded'   => 'Refunded',
			'failed'     => 'Failed',
		);
		return $statuses[ $status ] ?? ucfirst( $status );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string {
		return filter_var( $email, FILTER_SANITIZE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( mixed $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook_name
	 * @param mixed ...$args
	 */
	function do_action( string $hook_name, ...$args ): void {
		// No-op for unit tests.
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook_name
	 * @param mixed $value
	 * @param mixed ...$args
	 * @return mixed
	 */
	function apply_filters( string $hook_name, mixed $value, ...$args ): mixed {
		return WPAICTestHelper::apply_filters( $hook_name, $value, ...$args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string $hook_name
	 * @param callable $callback
	 * @param int $priority
	 * @param int $accepted_args
	 * @return true
	 */
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		WPAICTestHelper::add_filter( $hook_name, $callback );
		return true;
	}
}

if ( ! class_exists( 'MockWCProduct' ) ) {
	class MockWCProduct {
		private int $id;
		private bool $purchasable;
		private bool $in_stock;
		private string $type      = 'simple';
		private string $external_url = '';
		private string $button_text  = '';
		private int $parent_id    = 0;

		public function __construct( int $id, bool $purchasable = true, bool $in_stock = true, string $type = 'simple', int $parent_id = 0 ) {
			$this->id          = $id;
			$this->purchasable = $purchasable;
			$this->in_stock    = $in_stock;
			$this->type        = $type;
			$this->parent_id   = $parent_id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_parent_id(): int {
			return $this->parent_id;
		}

		public function is_purchasable(): bool {
			return $this->purchasable;
		}

		public function is_in_stock(): bool {
			return $this->in_stock;
		}

		public function get_stock_status(): string {
			return $this->in_stock ? 'instock' : 'outofstock';
		}

		public function get_type(): string {
			return $this->type;
		}

		public function set_external( string $url, string $button_text ): void {
			$this->external_url = $url;
			$this->button_text  = $button_text;
		}

		public function get_product_url(): string {
			return $this->external_url;
		}

		public function get_button_text(): string {
			return $this->button_text;
		}

		public function get_name(): string {
			$post = WPAICTestHelper::get_mock_post( $this->id );
			return $post ? $post->post_title : '';
		}

		public function get_price(): string {
			return (string) ( WPAICTestHelper::get_post_meta( $this->id, '_price', true ) ?: '' );
		}

		public function get_sku(): string {
			return WPAICTestHelper::get_post_meta( $this->id, '_sku', true ) ?: '';
		}

		public function get_description(): string {
			$post = WPAICTestHelper::get_mock_post( $this->id );
			return $post ? $post->post_content : '';
		}

		public function get_short_description(): string {
			$post = WPAICTestHelper::get_mock_post( $this->id );
			return $post ? $post->post_excerpt : '';
		}

		public function is_type( string $type ): bool {
			return $this->type === $type;
		}

		/** @var array<string, string> */
		private array $attribute_values = array();
		private string $weight = '';
		/** @var array<string, string> */
		private array $dimensions = array(
			'length' => '',
			'width'  => '',
			'height' => '',
		);
		/** @var array<int, int> */
		private array $cross_sell_ids = array();
		/** @var array<int, int> */
		private array $upsell_ids = array();

		/**
		 * @param array<int, int> $cross_sell_ids
		 */
		public function set_cross_sell_ids( array $cross_sell_ids ): void {
			$this->cross_sell_ids = $cross_sell_ids;
		}

		/**
		 * @return array<int, int>
		 */
		public function get_cross_sell_ids(): array {
			return $this->cross_sell_ids;
		}

		/**
		 * @param array<int, int> $upsell_ids
		 */
		public function set_upsell_ids( array $upsell_ids ): void {
			$this->upsell_ids = $upsell_ids;
		}

		/**
		 * @return array<int, int>
		 */
		public function get_upsell_ids(): array {
			return $this->upsell_ids;
		}

		/**
		 * @param array<string, string> $attribute_values Attribute name (e.g. pa_color) => value string.
		 */
		public function set_attribute_values( array $attribute_values ): void {
			$this->attribute_values = $attribute_values;
		}

		/**
		 * Real WC_Product::get_attributes() returns WC_Product_Attribute objects
		 * keyed by attribute name; production code only reads the keys (via
		 * get_name() or the array key), so string values suffice here.
		 *
		 * @return array<string, string>
		 */
		public function get_attributes(): array {
			return $this->attribute_values;
		}

		public function get_attribute( string $name ): string {
			return $this->attribute_values[ $name ] ?? '';
		}

		public function set_weight( string $weight ): void {
			$this->weight = $weight;
		}

		public function get_weight(): string {
			return $this->weight;
		}

		/**
		 * @param array<string, string> $dimensions length/width/height map.
		 */
		public function set_dimensions( array $dimensions ): void {
			$this->dimensions = $dimensions;
		}

		/**
		 * @return array<string, string>
		 */
		public function get_dimensions( bool $formatted = true ): array {
			return $this->dimensions;
		}
	}
}

if ( ! class_exists( 'WC_Product_Variable' ) ) {
	class WC_Product_Variable extends MockWCProduct {
		/** @var array<string, array<int, string>> */
		private array $variation_attributes = array();

		/** @var array<int, array<string, mixed>> */
		private array $available_variations = array();

		public function __construct( int $id, bool $purchasable = true, bool $in_stock = true ) {
			parent::__construct( $id, $purchasable, $in_stock, 'variable' );
		}

		/**
		 * @param array<string, array<int, string>> $variation_attributes Attribute name => options.
		 */
		public function set_variation_attributes( array $variation_attributes ): void {
			$this->variation_attributes = $variation_attributes;
		}

		/**
		 * @return array<string, array<int, string>>
		 */
		public function get_variation_attributes(): array {
			return $this->variation_attributes;
		}

		/**
		 * @param array<int, array<string, mixed>> $available_variations
		 */
		public function set_available_variations( array $available_variations ): void {
			$this->available_variations = $available_variations;
		}

		/**
		 * @return array<int, array<string, mixed>>
		 */
		public function get_available_variations(): array {
			return $this->available_variations;
		}
	}
}

if ( ! class_exists( 'MockWCCart' ) ) {
	class MockWCCart {
		/** @var array<string, array<string, mixed>> */
		private array $cart = array();
		private ?string $cart_total_override = null;
		private ?string $cart_subtotal_override = null;
		private bool $return_html_totals = false;

		/**
		 * @param int $product_id
		 * @param int $quantity
		 * @param int $variation_id
		 * @param array<string, string> $variation
		 * @return string|false
		 */
		public function add_to_cart( int $product_id, int $quantity = 1, int $variation_id = 0, array $variation = array() ): string|false {
			$key                = 'cart_item_' . $product_id . ( $variation_id > 0 ? '_' . $variation_id : '' );
			$this->cart[ $key ] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'variation'    => $variation,
				'quantity'     => $quantity,
				'data'         => wc_get_product( $variation_id > 0 ? $variation_id : $product_id ),
			);
			return $key;
		}

		/**
		 * @return array<string, array<string, mixed>>
		 */
		public function get_cart(): array {
			return $this->cart;
		}

		public function get_cart_contents_count(): int {
			$count = 0;
			foreach ( $this->cart as $item ) {
				$count += $item['quantity'];
			}
			return $count;
		}

		public function remove_cart_item( string $cart_item_key ): bool {
			if ( ! isset( $this->cart[ $cart_item_key ] ) ) {
				return false;
			}
			unset( $this->cart[ $cart_item_key ] );
			return true;
		}

		public function set_quantity( string $cart_item_key, int $quantity = 1 ): bool {
			if ( ! isset( $this->cart[ $cart_item_key ] ) ) {
				return false;
			}
			if ( $quantity <= 0 ) {
				unset( $this->cart[ $cart_item_key ] );
				return true;
			}
			$this->cart[ $cart_item_key ]['quantity'] = $quantity;
			return true;
		}

		public function empty_cart(): void {
			$this->cart = array();
		}

		public function get_cart_total(): string {
			if ( null !== $this->cart_total_override ) {
				return $this->cart_total_override;
			}

			return $this->format_price( $this->get_numeric_total() );
		}

		public function get_cart_hash(): string {
			return md5( serialize( $this->cart ) );
		}

		public function get_cart_subtotal(): string {
			if ( null !== $this->cart_subtotal_override ) {
				return $this->cart_subtotal_override;
			}

			return $this->format_price( $this->get_numeric_total() );
		}

		/**
		 * @param MockWCProduct $product
		 */
		public function get_product_subtotal( MockWCProduct $product, int $quantity ): string {
			return $this->format_price( $this->get_product_price( $product ) * $quantity );
		}

		public function set_totals( string $subtotal, string $total ): void {
			$this->cart_subtotal_override = $subtotal;
			$this->cart_total_override    = $total;
		}

		public function set_return_html_totals( bool $return_html_totals ): void {
			$this->return_html_totals = $return_html_totals;
		}

		public function clear(): void {
			$this->cart                  = array();
			$this->cart_total_override   = null;
			$this->cart_subtotal_override = null;
			$this->return_html_totals    = false;
		}

		private function get_numeric_total(): float {
			$total = 0.0;
			foreach ( $this->cart as $item ) {
				$product = isset( $item['data'] ) && $item['data'] instanceof MockWCProduct ? $item['data'] : null;
				if ( null === $product && isset( $item['product_id'] ) ) {
					$loaded_product = wc_get_product( (int) $item['product_id'] );
					$product        = $loaded_product instanceof MockWCProduct ? $loaded_product : null;
				}

				if ( null === $product ) {
					continue;
				}

				$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
				$total   += $this->get_product_price( $product ) * $quantity;
			}

			return $total;
		}

		private function get_product_price( MockWCProduct $product ): float {
			return (float) WPAICTestHelper::get_post_meta( $product->get_id(), '_price', true );
		}

		private function format_price( float $amount ): string {
			$formatted = '$' . number_format( $amount, 2 );
			if ( ! $this->return_html_totals ) {
				return $formatted;
			}

			return '<span class="woocommerce-Price-amount amount">' . $formatted . '</span>';
		}
	}
}

class MockWCSession {
	/** @var array<string, mixed> */
	private array $data = array();

	public function get( string $key, mixed $default = null ): mixed {
		return $this->data[ $key ] ?? $default;
	}

	public function set( string $key, mixed $value ): void {
		$this->data[ $key ] = $value;
	}
}

class MockWooCommerce {
	public ?MockWCCart $cart;
	public MockWCSession $session;
	private MockWCCart $persisted_cart;
	private bool $can_initialize_cart;

	public function __construct( bool $autoload_cart = true, bool $can_initialize_cart = true ) {
		$this->persisted_cart      = new MockWCCart();
		$this->cart                = $autoload_cart ? $this->persisted_cart : null;
		$this->can_initialize_cart = $can_initialize_cart;
		$this->session             = new MockWCSession();
	}

	public function initialize_session(): void {}

	public function initialize_cart(): void {
		if ( $this->can_initialize_cart ) {
			$this->cart = $this->persisted_cart;
		}
	}

	public function get_persisted_cart(): MockWCCart {
		return $this->persisted_cart;
	}
}

/** @var MockWooCommerce|null $mock_wc */
$mock_wc = null;

if ( ! function_exists( 'WC' ) ) {
	function WC(): MockWooCommerce {
		global $mock_wc;
		if ( null === $mock_wc ) {
			$mock_wc = new MockWooCommerce();
		}
		return $mock_wc;
	}
}

if ( ! function_exists( 'wc_load_cart' ) ) {
	function wc_load_cart(): void {
		$woocommerce = WC();
		$woocommerce->initialize_session();
		$woocommerce->initialize_cart();
	}
}

/** @var array<int, MockWCProduct|false> */
$mock_wc_products = array();

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * @param int $product_id
	 * @return MockWCProduct|false
	 */
	function wc_get_product( int $product_id ): MockWCProduct|false {
		global $mock_wc_products;
		if ( isset( $mock_wc_products[ $product_id ] ) ) {
			return $mock_wc_products[ $product_id ];
		}
		// Auto-create mock product for any existing product post
		$post = WPAICTestHelper::get_mock_post( $product_id );
		if ( $post && 'product' === $post->post_type ) {
			return new MockWCProduct( $product_id );
		}
		return false;
	}
}

if ( ! function_exists( 'woocommerce_mini_cart' ) ) {
	function woocommerce_mini_cart(): void {
		echo '<div class="mini-cart-content">Mini Cart</div>';
	}
}

if ( ! function_exists( 'get_woocommerce_currency' ) ) {
	function get_woocommerce_currency(): string {
		return WPAICTestHelper::get_option( 'woocommerce_currency', 'USD' );
	}
}

if ( ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
	/**
	 * @return array<int>
	 */
	function wc_get_product_ids_on_sale(): array {
		$ids = WPAICTestHelper::get_option( 'test_product_ids_on_sale', array() );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}
}

if ( ! class_exists( 'MockShippingMethod' ) ) {
	class MockShippingMethod {
		public string $id;
		public string $title;
		public string $enabled;
		public string $cost;
		public string $min_amount;
		public string $requires;

		/**
		 * @param array<string, mixed> $data
		 */
		public function __construct( array $data = array() ) {
			$this->id         = (string) ( $data['id'] ?? '' );
			$this->title      = (string) ( $data['title'] ?? '' );
			$this->enabled    = (string) ( $data['enabled'] ?? 'yes' );
			$this->cost       = (string) ( $data['cost'] ?? '' );
			$this->min_amount = (string) ( $data['min_amount'] ?? '' );
			$this->requires   = (string) ( $data['requires'] ?? '' );
		}

		public function get_method_title(): string {
			return $this->title;
		}
	}
}

if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
	class WC_Shipping_Zones {
		/**
		 * @return array<int, array<string, mixed>>
		 */
		public static function get_zones( string $context = 'admin' ): array {
			$zones = WPAICTestHelper::get_option( 'test_shipping_zones', array() );
			return is_array( $zones ) ? $zones : array();
		}

		/**
		 * @return MockShippingZone|false
		 */
		public static function get_zone( int $zone_id ): MockShippingZone|false {
			if ( 0 === $zone_id ) {
				$rest_methods = WPAICTestHelper::get_option( 'test_shipping_rest_of_world_methods', array() );
				return new MockShippingZone( 0, is_array( $rest_methods ) ? $rest_methods : array() );
			}
			return false;
		}
	}
}

if ( ! class_exists( 'MockShippingZone' ) ) {
	class MockShippingZone {
		private int $id;
		/** @var array<int, MockShippingMethod> */
		private array $methods;

		/**
		 * @param array<int, MockShippingMethod> $methods
		 */
		public function __construct( int $id, array $methods ) {
			$this->id      = $id;
			$this->methods = $methods;
		}

		/**
		 * @return array<int, MockShippingMethod>
		 */
		public function get_shipping_methods( bool $enabled_only = false, string $context = 'admin' ): array {
			return $this->methods;
		}

		public function get_id(): int {
			return $this->id;
		}
	}
}
