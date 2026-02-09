<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Frontend {
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_chatbot_container' ) );
	}

	public function enqueue_assets(): void {
		$settings = get_option( 'wpaic_settings', array() );

		if ( ! is_array( $settings ) || empty( $settings['enabled'] ) ) {
			return;
		}

		$manifest_path = WPAIC_PLUGIN_DIR . 'frontend/dist/.vite/manifest.json';

		if ( file_exists( $manifest_path ) ) {
			$manifest_contents = wpaic_file_get_contents( $manifest_path );
			if ( false === $manifest_contents ) {
				return;
			}
			/** @var array<string, mixed>|null $manifest */
			$manifest = json_decode( $manifest_contents, true );
			if ( ! is_array( $manifest ) ) {
				return;
			}
			/** @var array{file?: string, css?: array<string>}|null $entry */
			$entry = $manifest['index.html'] ?? null;

			if ( is_array( $entry ) && isset( $entry['file'] ) ) {
				if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
					foreach ( $entry['css'] as $css_file ) {
						wp_enqueue_style(
							'wpaic-chatbot',
							WPAIC_PLUGIN_URL . 'frontend/dist/' . $css_file,
							array(),
							WPAIC_VERSION
						);
					}
				}

				wp_enqueue_script(
					'wpaic-chatbot',
					WPAIC_PLUGIN_URL . 'frontend/dist/' . $entry['file'],
					array(),
					WPAIC_VERSION,
					true
				);
			}
		}

		$proactive_config = $this->get_proactive_config( $settings );

		wp_localize_script(
			'wpaic-chatbot',
			'wpaicConfig',
			array(
				'apiUrl'           => rest_url( 'wpaic/v1' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'greeting'         => $settings['greeting_message'] ?? 'Hello! How can I help you today?',
				'themeColor'       => $settings['theme_color'] ?? '#0073aa',
				'wcAjaxUrl'        => admin_url( 'admin-ajax.php' ),
				'cartUrl'          => wc_get_cart_url(),
				'proactiveEnabled' => $proactive_config['enabled'],
				'proactiveDelay'   => $proactive_config['delay'],
				'proactiveMessage' => $proactive_config['message'],
				'chatbotName'      => $settings['chatbot_name'] ?? '',
				'chatbotLogo'      => $settings['chatbot_logo'] ?? '',
			)
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array{enabled: bool, delay: int, message: string}
	 */
	private function get_proactive_config( array $settings ): array {
		$enabled = ! empty( $settings['proactive_enabled'] );

		if ( ! $enabled ) {
			return array(
				'enabled' => false,
				'delay'   => 0,
				'message' => '',
			);
		}

		$pages_setting = $settings['proactive_pages'] ?? 'all';
		$page_matches  = $this->current_page_matches( $pages_setting );

		if ( ! $page_matches ) {
			return array(
				'enabled' => false,
				'delay'   => 0,
				'message' => '',
			);
		}

		$message = $settings['proactive_message'] ?? '';
		if ( empty( $message ) ) {
			$message = $settings['greeting_message'] ?? 'Hello! How can I help you today?';
		}

		return array(
			'enabled' => true,
			'delay'   => (int) ( $settings['proactive_delay'] ?? 10 ),
			'message' => $message,
		);
	}

	private function current_page_matches( string $pages_setting ): bool {
		switch ( $pages_setting ) {
			case 'shop':
				if ( ! function_exists( 'is_shop' ) ) {
					return false;
				}
				return is_shop()
					|| ( function_exists( 'is_product_category' ) && is_product_category() )
					|| ( function_exists( 'is_product_tag' ) && is_product_tag() );
			case 'product':
				return function_exists( 'is_product' ) && is_product();
			case 'homepage':
				return is_front_page() || is_home();
			case 'all':
			default:
				return true;
		}
	}

	public function render_chatbot_container(): void {
		$settings = get_option( 'wpaic_settings', array() );

		if ( ! is_array( $settings ) || empty( $settings['enabled'] ) ) {
			return;
		}

		echo '<div id="wpaic-chatbot-root"></div>';
	}
}
