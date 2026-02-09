<?php
/**
 * Minimal WordPress stubs for WP AI Provider unit tests.
 */

$GLOBALS['wp_options'] = array();
$GLOBALS['wp_actions'] = array();

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

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * @template T of array<string, mixed>
	 */
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

		public function set_param( string $key, mixed $value ): void {
			$this->params[ $key ] = $value;
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
