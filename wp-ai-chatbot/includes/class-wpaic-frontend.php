<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Frontend {
	private WPAIC_License_Manager $license_manager;

	public function __construct( ?WPAIC_License_Manager $license_manager = null ) {
		$this->license_manager = $license_manager ?? new WPAIC_License_Manager();
	}

	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_chatbot_container' ) );
	}

	public function enqueue_assets(): void {
		$settings = get_option( 'wpaic_settings', array() );

		if ( ! is_array( $settings ) || empty( $settings['enabled'] ) || ! $this->license_manager->can_render_chat() ) {
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
			// Tiny loader stub (launcher + teaser); it dynamic-imports the full
			// React bundle (and its CSS) on first interaction or at idle when a
			// stored conversation exists, instead of on every pageview.
			/** @var array{file?: string, css?: array<string>}|null $entry */
			$entry = $manifest['src/loader.ts'] ?? null;

			if ( is_array( $entry ) && isset( $entry['file'] ) ) {
				$css_files = array();
				if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
					$css_files = array_merge( $css_files, (array) $entry['css'] );
				}
				if ( ! empty( $entry['imports'] ) && is_array( $entry['imports'] ) ) {
					foreach ( $entry['imports'] as $import_key ) {
						if ( isset( $manifest[ $import_key ]['css'] ) ) {
							$css_files = array_merge( $css_files, (array) $manifest[ $import_key ]['css'] );
						}
					}
				}
				foreach ( $css_files as $index => $css_file ) {
					wp_enqueue_style(
						'wpaic-chatbot-' . $index,
						WPAIC_PLUGIN_URL . 'frontend/dist/' . $css_file,
						array(),
						WPAIC_VERSION
					);
				}

				wp_enqueue_script(
					'wpaic-chatbot',
					WPAIC_PLUGIN_URL . 'frontend/dist/' . $entry['file'],
					array(),
					WPAIC_VERSION,
					true
				);
				add_filter( 'script_loader_tag', function ( string $tag, string $handle ) {
					if ( 'wpaic-chatbot' === $handle ) {
						return str_replace( '<script ', '<script type="module" ', $tag );
					}
					return $tag;
				}, 10, 2 );
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
			'themeColor'           => $settings['theme_color'] ?? '#2545B8',
			'wcAjaxUrl'            => admin_url( 'admin-ajax.php' ),
			'cartUrl'              => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'pageContext'          => ( new WPAIC_Page_Context() )->build(),
			'proactiveEnabled'     => $proactive_config['enabled'],
			'proactiveDelay'       => $proactive_config['delay'],
			'proactiveMessage'     => $proactive_config['message'],
			'chatbotName'          => $settings['chatbot_name'] ?? '',
			'chatbotLogo'          => $settings['chatbot_logo'] ?? '',
			'chatbotRole'          => $settings['chatbot_role'] ?? '',
			'currency'             => $this->get_currency_config(),
			'conversationStarters' => $this->resolve_conversation_starters( $settings ),
		);
	}

	/**
	 * @return array{symbol: string, decimals: int, decimalSeparator: string, thousandSeparator: string, position: string}
	 */
	private function get_currency_config(): array {
		$symbol             = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
		$decimals           = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		$decimal_separator  = function_exists( 'wc_get_price_decimal_separator' ) ? (string) wc_get_price_decimal_separator() : '.';
		$thousand_separator = function_exists( 'wc_get_price_thousand_separator' ) ? (string) wc_get_price_thousand_separator() : ',';
		$position           = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_currency_pos', 'left' ) : 'left';

		return array(
			'symbol'            => $symbol,
			'decimals'          => $decimals,
			'decimalSeparator'  => $decimal_separator,
			'thousandSeparator' => $thousand_separator,
			'position'          => $position,
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

		if ( ! is_array( $settings ) || empty( $settings['enabled'] ) || ! $this->license_manager->can_render_chat() ) {
			return;
		}

		echo '<div id="wpaic-chatbot-root"></div>';
	}
}
