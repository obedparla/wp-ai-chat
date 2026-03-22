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

		wp_localize_script(
			'wpaic-chatbot',
			'wpaicConfig',
			$this->build_frontend_config( $settings )
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	public function build_frontend_config( array $settings ): array {
		$proactive_config = $this->get_proactive_config( $settings );

		return array(
			'apiUrl'               => rest_url( 'wpaic/v1' ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'greeting'             => $settings['greeting_message'] ?? 'Hello! How can I help you today?',
			'themeColor'           => $settings['theme_color'] ?? '#0073aa',
			'wcAjaxUrl'            => admin_url( 'admin-ajax.php' ),
			'cartUrl'              => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'pageContext'          => ( new WPAIC_Page_Context() )->build(),
			'proactiveEnabled'     => $proactive_config['enabled'],
			'proactiveDelay'       => $proactive_config['delay'],
			'proactiveMessage'     => $proactive_config['message'],
			'chatbotName'          => $settings['chatbot_name'] ?? '',
			'chatbotLogo'          => $settings['chatbot_logo'] ?? '',
			'conversationStarters' => $this->resolve_conversation_starters( $settings ),
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

	/**
	 * @param array<string, mixed> $settings
	 * @return array<int, string>
	 */
	private function resolve_conversation_starters( array $settings ): array {
		$manual_starters = $settings['conversation_starters'] ?? array();
		if ( is_array( $manual_starters ) ) {
			$manual_starters = array_values(
				array_filter(
					array_map(
						static function ( mixed $starter ): string {
							return is_scalar( $starter ) ? trim( (string) $starter ) : '';
						},
						$manual_starters
					)
				)
			);
		} else {
			$manual_starters = array();
		}

		if ( ! empty( $manual_starters ) ) {
			return array_slice( array_values( array_unique( $manual_starters ) ), 0, 5 );
		}

		return $this->build_default_conversation_starters( $settings );
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<int, string>
	 */
	private function build_default_conversation_starters( array $settings ): array {
		$starters               = array();
		$woocommerce_available  = wpaic_is_woocommerce_active();
		$content_index_available = false;

		if ( class_exists( 'WPAIC_Content_Index' ) ) {
			$content_index = new WPAIC_Content_Index();
			$status        = $content_index->get_index_status();
			$content_index_available = ! empty( $status['exists'] );
		}

		if ( $woocommerce_available ) {
			$starters[] = 'Help me find a product';
			$starters[] = 'Track my order';
		}

		if ( $woocommerce_available && $content_index_available ) {
			$starters[] = 'What are your shipping and return policies?';
		}

		if ( $woocommerce_available ) {
			$starters[] = 'Show me product categories';
		}

		$fallbacks = $woocommerce_available
			? array(
				'What can you help me with?',
				'Do you have any recommendations?',
			)
			: array(
				'What can you help me with?',
				'Tell me about your services',
			);

		foreach ( $fallbacks as $fallback ) {
			if ( count( $starters ) >= 4 ) {
				break;
			}

			if ( ! in_array( $fallback, $starters, true ) ) {
				$starters[] = $fallback;
			}
		}

		return array_slice( $starters, 0, 5 );
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
