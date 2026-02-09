<?php
/**
 * WordPress function stubs for unit testing.
 *
 * These provide minimal implementations that allow tests to run without WordPress.
 */

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_title = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public string $post_type = 'post';
		public string $post_status = 'publish';

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
		public int $count = 0;

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

	/** @var array<int, array<string, array<int, string>>> */
	private static array $mock_post_terms = array();

	/** @var array<string, MockWCOrder> */
	public static array $mock_orders = array();

	public static function reset(): void {
		self::$mock_posts      = array();
		self::$mock_post_meta  = array();
		self::$mock_terms      = array();
		self::$mock_options    = array();
		self::$mock_post_terms = array();
		self::$mock_orders     = array();
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
		$posts = array_values( self::$mock_posts );

		if ( ! empty( $query_args['post_type'] ) ) {
			$posts = array_filter(
				$posts,
				fn( $p ) => $p->post_type === $query_args['post_type']
			);
		}

		if ( ! empty( $query_args['s'] ) ) {
			$search = strtolower( $query_args['s'] );
			$posts  = array_filter(
				$posts,
				fn( $p ) => str_contains( strtolower( $p->post_title ), $search ) ||
							str_contains( strtolower( $p->post_content ), $search )
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
			foreach ( $query_args['meta_query'] as $meta_query ) {
				if ( ! is_array( $meta_query ) || ! isset( $meta_query['key'] ) ) {
					continue;
				}
				$key     = $meta_query['key'];
				$value   = $meta_query['value'] ?? null;
				$compare = $meta_query['compare'] ?? '=';

				$posts = array_filter(
					$posts,
					function ( $p ) use ( $key, $value, $compare ) {
						$meta_value = self::get_post_meta( $p->ID, $key, true );
						$meta_float = is_numeric( $meta_value ) ? (float) $meta_value : 0;
						$cmp_float  = is_numeric( $value ) ? (float) $value : 0;

						return match ( $compare ) {
							'>='    => $meta_float >= $cmp_float,
							'<='    => $meta_float <= $cmp_float,
							'>'     => $meta_float > $cmp_float,
							'<'     => $meta_float < $cmp_float,
							'='     => $meta_value == $value,
							'!='    => $meta_value != $value,
							default => true,
						};
					}
				);
			}
		}

		$limit = $query_args['posts_per_page'] ?? 10;
		return array_slice( array_values( $posts ), 0, $limit );
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

class MockWpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 0;

	/** @var array<string, array<int, array<string, mixed>>> */
	private array $tables = array();
	private int $auto_increment = 1;

	public function __construct() {
		$this->tables = array(
			'wp_wpaic_conversations' => array(),
			'wp_wpaic_messages'      => array(),
			'wp_wpaic_faqs'          => array(),
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
		if ( preg_match( '/TRUNCATE\s+TABLE\s+(\S+)/i', $query, $matches ) ) {
			$table = $matches[1];
			if ( isset( $this->tables[ $table ] ) ) {
				$this->tables[ $table ] = array();
			}
			return true;
		}
		return false;
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
		$prepared = $query;
		foreach ( $args as $arg ) {
			$prepared = preg_replace( '/%[sd]/', is_string( $arg ) ? "'$arg'" : (string) $arg, $prepared, 1 );
		}
		return $prepared;
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

		if ( preg_match( '/SELECT\s+COUNT\(\*\)\s+FROM\s+\S+wpaic_conversations/i', $query ) ) {
			return (string) count( $this->tables['wp_wpaic_conversations'] );
		}

		return null;
	}

	/**
	 * @return array<int, object>|null
	 */
	public function get_results( string $query ): ?array {
		if ( preg_match( '/FROM\s+\S+wpaic_conversations/i', $query ) ) {
			$results = array();
			foreach ( $this->tables['wp_wpaic_conversations'] as $row ) {
				$obj = (object) $row;

				$message_count = 0;
				foreach ( $this->tables['wp_wpaic_messages'] as $msg ) {
					if ( $msg['conversation_id'] == $row['id'] ) {
						++$message_count;
					}
				}
				$obj->message_count = $message_count;
				$results[]          = $obj;
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
			return $results;
		}

		return array();
	}

	public function reset(): void {
		$this->tables = array(
			'wp_wpaic_conversations' => array(),
			'wp_wpaic_messages'      => array(),
			'wp_wpaic_faqs'          => array(),
		);
		$this->auto_increment = 1;
		$this->insert_id      = 0;
	}
}

$GLOBALS['wpdb'] = new MockWpdb();

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( bool $hard = true ): void {
		// No-op for testing.
	}
}

/**
 * Plugin activation function.
 */
function wpaic_activate(): void {
	add_option(
		'wpaic_settings',
		array(
			'openai_api_key'   => '',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello! How can I help you today?',
			'enabled'          => true,
			'system_prompt'    => '',
		)
	);

	wpaic_create_tables();
	flush_rewrite_rules();
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

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
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

class WPAICJsonResponseException extends Exception {
	public mixed $data;
	public bool $success;

	public function __construct( mixed $data, bool $success ) {
		$this->data    = $data;
		$this->success = $success;
		parent::__construct( $success ? 'JSON Success' : 'JSON Error' );
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
		return $value;
	}
}

if ( ! class_exists( 'MockWCProduct' ) ) {
	class MockWCProduct {
		private int $id;
		private bool $purchasable;
		private bool $in_stock;
		private string $type = 'simple';

		public function __construct( int $id, bool $purchasable = true, bool $in_stock = true ) {
			$this->id          = $id;
			$this->purchasable = $purchasable;
			$this->in_stock    = $in_stock;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function is_purchasable(): bool {
			return $this->purchasable;
		}

		public function is_in_stock(): bool {
			return $this->in_stock;
		}

		public function get_type(): string {
			return $this->type;
		}

		public function get_name(): string {
			$post = WPAICTestHelper::get_mock_post( $this->id );
			return $post ? $post->post_title : '';
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
	}
}

if ( ! class_exists( 'MockWCCart' ) ) {
	class MockWCCart {
		/** @var array<string, array<string, mixed>> */
		private array $cart = array();

		/**
		 * @param int $product_id
		 * @param int $quantity
		 * @return string|false
		 */
		public function add_to_cart( int $product_id, int $quantity = 1 ): string|false {
			$key                = 'cart_item_' . $product_id;
			$this->cart[ $key ] = array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
			);
			return $key;
		}

		public function get_cart_contents_count(): int {
			$count = 0;
			foreach ( $this->cart as $item ) {
				$count += $item['quantity'];
			}
			return $count;
		}

		public function get_cart_total(): string {
			return '$99.00';
		}

		public function clear(): void {
			$this->cart = array();
		}
	}
}

class MockWooCommerce {
	public MockWCCart $cart;

	public function __construct() {
		$this->cart = new MockWCCart();
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
