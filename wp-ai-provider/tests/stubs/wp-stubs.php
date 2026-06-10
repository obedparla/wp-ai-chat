<?php
/**
 * Minimal WordPress stubs for WP AI Provider unit tests.
 */

$GLOBALS['wp_options'] = array();
$GLOBALS['wp_actions'] = array();
$GLOBALS['wp_transients'] = array();
$GLOBALS['wp_filters'] = array();

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $option, mixed $value = '' ): bool {
		if ( ! isset( $GLOBALS['wp_options'][ $option ] ) ) {
			$GLOBALS['wp_options'][ $option ] = $value;
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		return $GLOBALS['wp_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value ): bool {
		$GLOBALS['wp_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		unset( $GLOBALS['wp_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['wp_actions'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['wp_filters'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		foreach ( $GLOBALS['wp_filters'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
		$GLOBALS['wp_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		return $GLOBALS['wp_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( bool $hard = true ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		$dir = basename( dirname( $file ) );
		return $dir . '/' . basename( $file );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): string|array|int|false|null {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $file, callable $callback ): void {
		$GLOBALS['wp_activation_hooks'][ $file ] = $callback;
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $file, callable $callback ): void {
		$GLOBALS['wp_deactivation_hooks'][ $file ] = $callback;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( string $namespace, string $route, array $args ): void {
		$GLOBALS['wp_rest_routes'][ $namespace ][ $route ] = $args;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, string $icon_url = '', int|float|null $position = null ): string {
		$GLOBALS['wp_admin_pages'][ $menu_slug ] = array(
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'callback'   => $callback,
			'icon_url'   => $icon_url,
			'position'   => $position,
		);
		return $menu_slug;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $option_group, string $option_name, array $args = array() ): void {
		$GLOBALS['wp_registered_settings'][ $option_name ] = array(
			'group' => $option_group,
			'args'  => $args,
		);
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( string $id, string $title, callable|string|null $callback, string $page ): void {
		$GLOBALS['wp_settings_sections'][ $page ][ $id ] = array(
			'title'    => $title,
			'callback' => $callback,
		);
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = array() ): void {
		$GLOBALS['wp_settings_fields'][ $page ][ $section ][ $id ] = array(
			'title'    => $title,
			'callback' => $callback,
			'args'     => $args,
		);
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
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

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return $GLOBALS['wp_current_user_can'] ?? true;
	}
}

if ( ! function_exists( 'get_admin_page_title' ) ) {
	function get_admin_page_title(): string {
		return 'AI Provider';
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $option_group ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( string $page ): void {
		// No-op in tests.
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button(): void {
		echo '<input type="submit" class="button button-primary" value="Save Changes" />';
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = array() ): mixed {
		if ( isset( $GLOBALS['wp_remote_get_handler'] ) && is_callable( $GLOBALS['wp_remote_get_handler'] ) ) {
			return call_user_func( $GLOBALS['wp_remote_get_handler'], $url, $args );
		}

		return new WP_Error( 'http_not_mocked', 'No wp_remote_get mock registered.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( mixed $response ): int {
		return is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( mixed $response ): string {
		return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value ): string|false {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return $GLOBALS['wp_is_admin'] ?? false;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		/** @var array<string, mixed> */
		private array $data;

		/**
		 * @param string $code
		 * @param string $message
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

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		public mixed $data;
		public int $status;

		public function __construct( mixed $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_status(): int {
			return $this->status;
		}

		/** @return mixed */
		public function get_data(): mixed {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'MockWpdb' ) ) {
	/**
	 * In-memory wpdb double for the wpaip_usage_daily table. Emulates the
	 * exact queries WPAIP_Usage_Tracker issues: an atomic
	 * INSERT ... ON DUPLICATE KEY UPDATE upsert, a per-(day, bucket) SELECT,
	 * and the retention DELETE.
	 */
	class MockWpdb {
		public string $prefix = 'wp_';

		/** @var array<int, string> Raw queries passed to query()/dbDelta(), for assertions. */
		public array $queries = array();

		/** @var array<string, array<string, array<string, mixed>>> Table => "day|bucket" => row. */
		private array $rows = array();

		public function reset(): void {
			$this->queries = array();
			$this->rows    = array();
		}

		public function get_charset_collate(): string {
			return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
		}

		public function prepare( string $query, mixed ...$args ): string {
			// Single pass so substituted values containing %s/%d are never re-matched.
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

		public function query( string $query ): int|bool {
			$this->queries[] = $query;
			$normalized      = trim( (string) preg_replace( '/\s+/', ' ', $query ) );

			if ( preg_match( '/^INSERT INTO (\S+) \(([^)]+)\) VALUES \(([^)]+)\) ON DUPLICATE KEY UPDATE /i', $normalized, $matches ) ) {
				$table   = $matches[1];
				$columns = array_map( 'trim', explode( ',', $matches[2] ) );
				$values  = array_map(
					static function ( string $value ): string {
						return trim( trim( $value ), "'" );
					},
					explode( ',', $matches[3] )
				);
				$data    = array_combine( $columns, $values );

				$key = (string) ( $data['usage_day'] ?? '' ) . '|' . (string) ( $data['usage_bucket'] ?? '' );
				$row = $this->rows[ $table ][ $key ] ?? array();
				foreach ( $data as $column => $value ) {
					if ( in_array( $column, array( 'usage_day', 'usage_bucket' ), true ) ) {
						$row[ $column ] = $value;
						continue;
					}
					// ON DUPLICATE KEY UPDATE adds the same amounts the VALUES
					// carry, so adding onto the existing (or zero) row emulates
					// both the insert and the duplicate-key path.
					$row[ $column ] = (int) ( $row[ $column ] ?? 0 ) + (int) $value;
				}
				$this->rows[ $table ][ $key ] = $row;
				return 1;
			}

			if ( preg_match( "/^DELETE FROM (\S+) WHERE usage_day < '([^']+)'$/i", $normalized, $matches ) ) {
				$table   = $matches[1];
				$cutoff  = $matches[2];
				$deleted = 0;
				foreach ( $this->rows[ $table ] ?? array() as $key => $row ) {
					if ( (string) ( $row['usage_day'] ?? '' ) < $cutoff ) {
						unset( $this->rows[ $table ][ $key ] );
						++$deleted;
					}
				}
				return $deleted;
			}

			return false;
		}

		/**
		 * @return array<string, mixed>|null
		 */
		public function get_row( string $query, string $output = ARRAY_A ): ?array {
			$normalized = trim( (string) preg_replace( '/\s+/', ' ', $query ) );

			if ( preg_match( "/FROM (\S+) WHERE usage_day = '([^']+)' AND usage_bucket = '([^']+)'$/i", $normalized, $matches ) ) {
				$row = $this->rows[ $matches[1] ][ $matches[2] . '|' . $matches[3] ] ?? null;
				return is_array( $row ) ? $row : null;
			}

			return null;
		}

		/**
		 * Seed helper mirroring wpdb::insert() for usage rows in tests.
		 *
		 * @param array<string, mixed> $data
		 */
		public function insert( string $table, array $data ): int|false {
			$key                          = (string) ( $data['usage_day'] ?? '' ) . '|' . (string) ( $data['usage_bucket'] ?? '' );
			$this->rows[ $table ][ $key ] = $data;
			return 1;
		}
	}

	$GLOBALS['wpdb'] = new MockWpdb();
}

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * @param string|array<int, string> $queries
	 * @return array<string, string>
	 */
	function dbDelta( string|array $queries, bool $execute = true ): array {
		foreach ( (array) $queries as $sql ) {
			$GLOBALS['wpdb']->queries[] = (string) $sql;
		}
		return array();
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * @template T of array<string, mixed>
	 */
	class WP_REST_Request {
		/** @var array<string, mixed> */
		private array $params = array();
		/** @var array<string, string> */
		private array $headers = array();
		private string $body = '';

		/**
		 * @param array<string, mixed> $params
		 */
		public function set_params( array $params ): void {
			$this->params = $params;
		}

		public function set_param( string $key, mixed $value ): void {
			$this->params[ $key ] = $value;
		}

		public function set_body( string $body ): void {
			$this->body = $body;
		}

		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}

		public function get_body(): string {
			return $this->body;
		}

		public function set_header( string $key, string $value ): void {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		public function get_header( string $key ): ?string {
			return $this->headers[ strtolower( $key ) ] ?? null;
		}
	}
}
