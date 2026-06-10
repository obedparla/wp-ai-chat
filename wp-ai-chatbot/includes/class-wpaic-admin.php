<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Admin {
	private WPAIC_Logs $logs;
	private WPAIC_License_Manager $license_manager;
	private WPAIC_Transcript_Renderer $transcript_renderer;

	public function __construct( ?WPAIC_License_Manager $license_manager = null ) {
		$this->logs                = new WPAIC_Logs();
		$this->license_manager     = $license_manager ?? new WPAIC_License_Manager();
		$this->transcript_renderer = new WPAIC_Transcript_Renderer( $this->logs );
	}

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_wpaic_get_conversation', array( $this, 'ajax_get_conversation' ) );
		add_action( 'wp_ajax_wpaic_delete_conversation', array( $this, 'ajax_delete_conversation' ) );
		add_action( 'wp_ajax_wpaic_update_search_indexes', array( $this, 'ajax_update_search_indexes' ) );
		add_action( 'wp_ajax_wpaic_update_support_status', array( $this, 'ajax_update_support_status' ) );
		add_action( 'wp_ajax_wpaic_get_support_transcript', array( $this, 'ajax_get_support_transcript' ) );
		add_action( 'wp_ajax_wpaic_upload_csv', array( $this, 'ajax_upload_csv' ) );
		add_action( 'wp_ajax_wpaic_delete_data_source', array( $this, 'ajax_delete_data_source' ) );
		add_action( 'wp_ajax_wpaic_save_faqs', array( $this, 'ajax_save_faqs' ) );
		add_action( 'wp_ajax_wpaic_update_onboarding', array( $this, 'ajax_update_onboarding' ) );
	}

	/**
	 * Redirect to the settings page once after a fresh plugin activation.
	 */
	public function maybe_redirect_after_activation(): void {
		if ( ! get_transient( 'wpaic_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'wpaic_activation_redirect' );

		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wp-ai-chatbot' ) );
		exit;
	}

	public function enqueue_admin_scripts( string $hook ): void {
		$allowed_hooks = array(
			'toplevel_page_wp-ai-chatbot',
			'ai-chatbot_page_wp-ai-chatbot-logs',
			'ai-chatbot_page_wp-ai-chatbot-support',
		);
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpaic-google-fonts',
			'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap',
			array(),
			WPAIC_VERSION
		);

		wp_enqueue_media();
		wp_add_inline_script(
			'jquery',
			'jQuery(document).ready(function($){' .
				'var frame;' .
				'$("#wpaic_logo_upload").on("click",function(e){' .
					'e.preventDefault();' .
					'if(frame){frame.open();return;}' .
					'frame=wp.media({title:"Select Chatbot Logo",button:{text:"Use as Logo"},multiple:false,library:{type:"image"}});' .
					'frame.on("select",function(){' .
						'var a=frame.state().get("selection").first().toJSON();' .
						'if(!a.type||a.type!=="image"){alert("Please select an image file (JPEG, PNG, GIF, WebP, or SVG).");return;}' .
						'$("#wpaic_chatbot_logo").val(a.url).trigger("input")[0].dispatchEvent(new Event("input",{bubbles:true}));' .
						'$("#wpaic_logo_preview").attr("src",a.url).show();' .
						'if($("#wpaic_logo_letter").length){$("#wpaic_logo_letter").hide();}' .
						'$("#wpaic_logo_remove").show();' .
					'});' .
					'frame.open();' .
				'});' .
				'$("#wpaic_logo_remove").on("click",function(e){' .
					'e.preventDefault();' .
					'$("#wpaic_chatbot_logo").val("")[0].dispatchEvent(new Event("input",{bubbles:true}));' .
					'$("#wpaic_logo_preview").hide();' .
					'if($("#wpaic_logo_letter").length){$("#wpaic_logo_letter").show();}' .
					'$(this).hide();' .
				'});' .
			'});'
		);

		wp_enqueue_script(
			'wpaic-admin',
			WPAIC_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			WPAIC_VERSION,
			true
		);
		wp_localize_script( 'wpaic-admin', 'wpaicAdmin', array(
			'adminNonce'                => wp_create_nonce( 'wpaic_admin' ),
			'onboardingNonce'           => wp_create_nonce( 'wpaic_onboarding' ),
			'deleteConversationConfirm' => __( 'Are you sure you want to delete this conversation?', 'wp-ai-chatbot' ),
		) );

		$manifest_path = WPAIC_PLUGIN_DIR . 'frontend/dist/.vite/manifest.json';
		if ( file_exists( $manifest_path ) ) {
			$manifest_contents = wpaic_file_get_contents( $manifest_path );
			if ( false !== $manifest_contents ) {
				/** @var array<string, mixed>|null $manifest */
				$manifest = json_decode( $manifest_contents, true );
				if ( is_array( $manifest ) && isset( $manifest['admin.html']['file'] ) ) {
					wp_enqueue_style(
						'wpaic-admin-tailwind',
						WPAIC_PLUGIN_URL . 'frontend/dist/' . $manifest['admin.html']['file'],
						array(),
						WPAIC_VERSION
					);
				}
				if ( is_array( $manifest ) && isset( $manifest['src/admin-preview.tsx'] ) ) {
					$preview_entry = $manifest['src/admin-preview.tsx'];
					$css_files     = array();
					if ( ! empty( $preview_entry['css'] ) ) {
						$css_files = array_merge( $css_files, (array) $preview_entry['css'] );
					}
					if ( ! empty( $preview_entry['imports'] ) ) {
						foreach ( (array) $preview_entry['imports'] as $import_key ) {
							if ( isset( $manifest[ $import_key ]['css'] ) ) {
								$css_files = array_merge( $css_files, (array) $manifest[ $import_key ]['css'] );
							}
						}
					}
					foreach ( $css_files as $index => $css_file ) {
						wp_enqueue_style(
							'wpaic-admin-preview-css-' . $index,
							WPAIC_PLUGIN_URL . 'frontend/dist/' . $css_file,
							array(),
							WPAIC_VERSION
						);
					}
					wp_enqueue_script(
						'wpaic-admin-preview',
						WPAIC_PLUGIN_URL . 'frontend/dist/' . $preview_entry['file'],
						array(),
						WPAIC_VERSION,
						true
					);
					add_filter( 'script_loader_tag', function ( string $tag, string $handle ) {
						if ( 'wpaic-admin-preview' === $handle ) {
							return str_replace( '<script ', '<script type="module" ', $tag );
						}
						return $tag;
					}, 10, 2 );
					$settings = get_option( 'wpaic_settings', array() );
					if ( ! is_array( $settings ) ) {
						$settings = array();
					}
					wp_localize_script( 'wpaic-admin-preview', 'wpaicAdminPreview', array(
						'greeting'         => $settings['greeting_message'] ?? 'Hello! How can I help you today?',
						'chatbotName'      => $settings['chatbot_name'] ?? '',
						'chatbotLogo'      => $settings['chatbot_logo'] ?? '',
						'chatbotRole'      => $settings['chatbot_role'] ?? '',
						'themeColor'       => $settings['theme_color'] ?? '#2545B8',
						'proactiveMessage' => $settings['proactive_message'] ?? '',
					) );
				}
			}
		}
	}

	public function add_admin_menu(): void {
		$new_support_request_count = $this->get_new_support_request_count();
		$support_badge             = '';
		if ( $new_support_request_count > 0 ) {
			$support_badge = sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
				$new_support_request_count,
				number_format_i18n( $new_support_request_count ),
				sprintf(
					/* translators: %s: number of new support requests */
					_n( '%s new support request', '%s new support requests', $new_support_request_count, 'wp-ai-chatbot' ),
					number_format_i18n( $new_support_request_count )
				)
			);
		}

		add_menu_page(
			__( 'AI Chatbot', 'wp-ai-chatbot' ),
			__( 'AI Chatbot', 'wp-ai-chatbot' ) . $support_badge,
			'manage_options',
			'wp-ai-chatbot',
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			80
		);

		add_submenu_page(
			'wp-ai-chatbot',
			__( 'Settings', 'wp-ai-chatbot' ),
			__( 'Settings', 'wp-ai-chatbot' ),
			'manage_options',
			'wp-ai-chatbot',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'wp-ai-chatbot',
			__( 'Chat Logs', 'wp-ai-chatbot' ),
			__( 'Chat Logs', 'wp-ai-chatbot' ),
			'manage_options',
			'wp-ai-chatbot-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'wp-ai-chatbot',
			__( 'Support Requests', 'wp-ai-chatbot' ),
			__( 'Support', 'wp-ai-chatbot' ) . $support_badge,
			'manage_options',
			'wp-ai-chatbot-support',
			array( $this, 'render_support_page' )
		);
	}

	/**
	 * Number of handoff requests still in the "new" status, shown as an
	 * awaiting-mod count bubble on the admin menu.
	 */
	private function get_new_support_request_count(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_support_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'new'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function register_settings(): void {
		register_setting(
			'wpaic_settings_group',
			'wpaic_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$existing  = get_option( 'wpaic_settings', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$tab_fields = array(
			'general'    => array( 'enabled', 'greeting_message', 'language', 'tone_of_voice', 'system_prompt', 'retention_days', 'anonymize_ip' ),
			'api'        => array( 'provider_url_override' ),
			'appearance' => array( 'chatbot_name', 'chatbot_logo', 'chatbot_role', 'theme_color' ),
			'engagement' => array( 'handoff_enabled', 'handoff_fields', 'promotions_enabled', 'proactive_enabled', 'proactive_delay', 'proactive_message', 'proactive_pages', 'conversation_starters' ),
			'search'     => array( 'product_index_enabled', 'content_index_post_types' ),
		);

		$active_tab = $input['active_tab'] ?? '';
		if ( $active_tab && isset( $tab_fields[ $active_tab ] ) ) {
			$merged = $existing;
			foreach ( $tab_fields[ $active_tab ] as $field ) {
				$merged[ $field ] = $input[ $field ] ?? '';
			}
		} else {
			$merged = array_merge( $existing, $input );
		}

		$sanitized                     = array();
		$sanitized['model']            = 'gpt-5-mini';
		$sanitized['greeting_message'] = sanitize_textarea_field( $merged['greeting_message'] ?? '' );
		$sanitized['enabled']          = ! empty( $merged['enabled'] );
		$sanitized['system_prompt']    = sanitize_textarea_field( $merged['system_prompt'] ?? '' );
		$theme_color                   = sanitize_hex_color( $merged['theme_color'] ?? '#2545B8' );
		$sanitized['theme_color']      = $theme_color ? $theme_color : '#2545B8';
		$sanitized['language']         = sanitize_text_field( $merged['language'] ?? 'auto' );
		$tone_of_voice                 = sanitize_key( $merged['tone_of_voice'] ?? 'neutral' );
		$valid_tones                   = array_keys( $this->get_tone_of_voice_options() );
		$sanitized['tone_of_voice']    = in_array( $tone_of_voice, $valid_tones, true ) ? $tone_of_voice : 'neutral';

		$sanitized['retention_days'] = isset( $merged['retention_days'] ) ? max( 0, (int) $merged['retention_days'] ) : 0;
		// Anonymization defaults to on when never saved.
		$sanitized['anonymize_ip'] = array_key_exists( 'anonymize_ip', $merged ) ? ! empty( $merged['anonymize_ip'] ) : true;

		$sanitized['proactive_enabled'] = ! empty( $merged['proactive_enabled'] );
		$sanitized['proactive_delay']   = max( 1, (int) ( $merged['proactive_delay'] ?? 10 ) );
		$sanitized['proactive_message'] = sanitize_textarea_field( $merged['proactive_message'] ?? '' );
		$sanitized['proactive_pages']   = sanitize_text_field( $merged['proactive_pages'] ?? 'all' );
		$sanitized['conversation_starters'] = $this->sanitize_conversation_starters( $merged['conversation_starters'] ?? array() );

		$sanitized['chatbot_name'] = sanitize_text_field( $merged['chatbot_name'] ?? '' );
		$sanitized['chatbot_logo'] = esc_url_raw( $merged['chatbot_logo'] ?? '' );
		$sanitized['chatbot_role'] = sanitize_text_field( $merged['chatbot_role'] ?? '' );

		$sanitized['handoff_enabled'] = ! empty( $merged['handoff_enabled'] );

		// Off by default: published coupons can include private codes (support
		// credits, partner codes) the merchant never meant to advertise.
		$sanitized['promotions_enabled'] = ! empty( $merged['promotions_enabled'] );

		$valid_handoff_fields         = array( 'phone_number', 'company', 'order_number', 'request_message' );
		$raw_handoff_fields           = $merged['handoff_fields'] ?? array();
		$sanitized['handoff_fields']  = is_array( $raw_handoff_fields )
			? array_values( array_intersect( $raw_handoff_fields, $valid_handoff_fields ) )
			: array();

		$sanitized['provider_url']          = esc_url_raw( $merged['provider_url'] ?? '' );
		$sanitized['provider_site_key']     = sanitize_text_field( $merged['provider_site_key'] ?? '' );
		$sanitized['provider_url_override'] = $this->license_manager->is_provider_url_override_allowed()
			? esc_url_raw( $merged['provider_url_override'] ?? '' )
			: '';

		$search_settings                         = $this->sanitize_search_index_settings( $merged, 'search' === $active_tab );
		$sanitized['product_index_enabled']      = $search_settings['product_index_enabled'];
		$sanitized['content_index_post_types']   = $search_settings['content_index_post_types'];

		return $sanitized;
	}

	/**
	 * @return array<string, string>
	 */
	private function get_tone_of_voice_options(): array {
		return array(
			'neutral'      => __( 'Neutral (Balanced, factual, straightforward, no strong tone)', 'wp-ai-chatbot' ),
			'friendly'     => __( 'Friendly (Warm, conversational, approachable, uses casual language)', 'wp-ai-chatbot' ),
			'professional' => __( 'Professional (Neutral, task-focused, clear and efficient, straight to the point)', 'wp-ai-chatbot' ),
			'enthusiastic' => __( 'Enthusiastic (Upbeat, energetic, positive, more expressive without being pushy)', 'wp-ai-chatbot' ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function get_available_content_post_types(): array {
		$post_type_labels    = array();
		$public_post_types   = get_post_types( array( 'public' => true ), 'objects' );
		$excluded_post_types = array( 'product', 'attachment' );

		foreach ( $public_post_types as $post_type ) {
			if ( is_object( $post_type ) && isset( $post_type->name ) ) {
				$name = sanitize_key( (string) $post_type->name );
				if ( in_array( $name, $excluded_post_types, true ) ) {
					continue;
				}

				$label = isset( $post_type->labels->name ) && is_string( $post_type->labels->name )
					? $post_type->labels->name
					: ucwords( str_replace( '_', ' ', $name ) );

				$post_type_labels[ $name ] = $label;
				continue;
			}

			if ( is_string( $post_type ) ) {
				$name = sanitize_key( $post_type );
				if ( in_array( $name, $excluded_post_types, true ) ) {
					continue;
				}

				$post_type_labels[ $name ] = ucwords( str_replace( '_', ' ', $name ) );
			}
		}

		return $post_type_labels;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array{product_index_enabled: bool, content_index_post_types: array<int, string>}
	 */
	private function sanitize_search_index_settings( array $input, bool $allow_empty_content_selection = false ): array {
		$available_post_types = array_keys( $this->get_available_content_post_types() );

		$product_index_enabled = true;
		if ( array_key_exists( 'product_index_enabled', $input ) ) {
			$product_index_enabled = ! empty( $input['product_index_enabled'] );
		}

		$raw_content_post_types = $input['content_index_post_types'] ?? null;
		if ( is_array( $raw_content_post_types ) ) {
			$content_index_post_types = array_values(
				array_intersect(
					array_map( 'sanitize_key', $raw_content_post_types ),
					$available_post_types
				)
			);
		} elseif ( ! $allow_empty_content_selection ) {
			$content_index_post_types = array( 'page', 'post' );
		} else {
			$content_index_post_types = array();
		}

		return array(
			'product_index_enabled'    => $product_index_enabled,
			'content_index_post_types' => $content_index_post_types,
		);
	}

	/**
	 * @param mixed $raw_starters
	 * @return array<int, string>
	 */
	private function sanitize_conversation_starters( mixed $raw_starters ): array {
		if ( ! is_array( $raw_starters ) ) {
			return array();
		}

		$starters = array();
		foreach ( $raw_starters as $starter ) {
			if ( ! is_scalar( $starter ) ) {
				continue;
			}

			$cleaned = sanitize_text_field( (string) $starter );
			if ( '' === $cleaned || in_array( $cleaned, $starters, true ) ) {
				continue;
			}

			$starters[] = $cleaned;
			if ( count( $starters ) >= 5 ) {
				break;
			}
		}

		return $starters;
	}

	private function format_index_updated_at( ?string $updated ): string {
		if ( empty( $updated ) ) {
			return (string) __( 'Unknown', 'wp-ai-chatbot' );
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			strtotime( $updated )
		);
	}

	/**
	 * Why the chat widget is hidden on the frontend even though it is enabled.
	 */
	private function get_chat_hidden_reason(): string {
		if ( ! $this->license_manager->has_valid_chat_license() ) {
			return (string) __( 'license required', 'wp-ai-chatbot' );
		}

		if ( ! $this->license_manager->is_provider_url_configured() ) {
			return (string) __( 'provider not configured', 'wp-ai-chatbot' );
		}

		return (string) __( 'billing connection unavailable', 'wp-ai-chatbot' );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings   = get_option( 'wpaic_settings', array() );
		$settings   = is_array( $settings ) ? $settings : array();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_enabled = ! empty( $settings['enabled'] );
		$is_live    = $is_enabled && $this->license_manager->can_render_chat();
		if ( $is_live ) {
			$pill_label = __( 'Live on site', 'wp-ai-chatbot' );
		} elseif ( $is_enabled ) {
			/* translators: %s: reason the chat widget is hidden on the frontend */
			$pill_label = sprintf( __( 'Hidden — %s', 'wp-ai-chatbot' ), $this->get_chat_hidden_reason() );
		} else {
			$pill_label = __( 'Paused', 'wp-ai-chatbot' );
		}
		$tabs       = array(
			'general'    => __( 'General', 'wp-ai-chatbot' ),
			'appearance' => __( 'Appearance', 'wp-ai-chatbot' ),
			'engagement' => __( 'Engagement', 'wp-ai-chatbot' ),
			'knowledge'  => __( 'Knowledge', 'wp-ai-chatbot' ),
			'api'        => __( 'Licensing', 'wp-ai-chatbot' ),
		);
		$tab_icons = array(
			'general'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
			'appearance' => '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.62-.38-1.01 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-4.97-4.48-9-10-9z"/>',
			'engagement' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
			'knowledge'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
			'api'        => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
		);
		?>
		<div class="wpaic-admin-wrap" style="margin-left: -20px;">
			<div class="bg-surface border-b border-line sticky top-[32px] z-10">
				<div class="max-w-[960px] mx-auto px-8 pt-5">
					<div class="flex items-center gap-3 mb-4">
						<h1 class="text-[22px] font-semibold tracking-tight text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'AI Chatbot', 'wp-ai-chatbot' ); ?></h1>
						<span class="wpaic-status-pill <?php echo $is_live ? 'wpaic-status-pill-live' : 'wpaic-status-pill-paused'; ?>">
							<span class="wpaic-status-dot <?php echo $is_live ? 'wpaic-status-dot-live' : 'wpaic-status-dot-paused'; ?>"></span>
							<?php echo esc_html( $pill_label ); ?>
						</span>
					</div>
					<nav class="flex gap-0.5 -mb-px">
						<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=wp-ai-chatbot' ) ) ); ?>"
								class="inline-flex items-center gap-2 px-3.5 py-2.5 text-[13.5px] font-medium border-b-2 transition-colors no-underline <?php echo $active_tab === $tab_id ? 'text-ink border-ink' : 'text-muted border-transparent hover:text-ink-2'; ?>">
								<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $tab_icons[ $tab_id ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG paths ?></svg>
								<?php echo esc_html( $tab_label ); ?>
							</a>
						<?php endforeach; ?>
					</nav>
				</div>
			</div>

			<?php if ( 'knowledge' === $active_tab ) : ?>
				<?php $this->render_knowledge_tab(); ?>
			<?php else : ?>
				<form action="options.php" method="post">
					<?php settings_fields( 'wpaic_settings_group' ); ?>
					<input type="hidden" name="wpaic_settings[active_tab]" value="<?php echo esc_attr( $active_tab ); ?>">

					<?php if ( 'general' === $active_tab ) : ?>
						<?php $this->render_general_tab( $settings ); ?>
					<?php elseif ( 'api' === $active_tab ) : ?>
						<?php $this->render_api_tab( $settings ); ?>
					<?php elseif ( 'appearance' === $active_tab ) : ?>
						<?php $this->render_appearance_tab( $settings ); ?>
					<?php elseif ( 'engagement' === $active_tab ) : ?>
						<?php $this->render_engagement_tab( $settings ); ?>
					<?php endif; ?>

					<div class="<?php echo 'appearance' === $active_tab ? 'max-w-[1200px]' : 'max-w-[960px]'; ?> mx-auto px-8 pb-8">
						<div class="pt-4">
							<button type="submit" class="wpaic-btn wpaic-btn-primary">
								<?php esc_html_e( 'Save changes', 'wp-ai-chatbot' ); ?><span id="wpaic-unsaved-indicator" class="hidden" aria-hidden="true"> &bull;</span>
							</button>
						</div>
					</div>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the dismissible first-run onboarding checklist on the General tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_onboarding_checklist( array $settings ): void {
		$onboarding = get_option( 'wpaic_onboarding', array() );
		$onboarding = is_array( $onboarding ) ? $onboarding : array();
		if ( ! empty( $onboarding['dismissed'] ) ) {
			return;
		}

		$completed_steps = isset( $onboarding['steps'] ) && is_array( $onboarding['steps'] ) ? $onboarding['steps'] : array();

		global $wpdb;
		$faqs_table    = $wpdb->prefix . 'wpaic_faqs';
		$sources_table = $wpdb->prefix . 'wpaic_data_sources';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$faq_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $faqs_table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$source_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $sources_table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$search_index   = new WPAIC_Search_Index();
		$content_index  = new WPAIC_Content_Index();
		$product_status = $search_index->get_index_status();
		$content_status = $content_index->get_index_status();
		$index_built    = ! empty( $product_status['exists'] ) || ! empty( $content_status['exists'] );

		$license_ready   = $this->license_manager->can_render_chat();
		$branded         = '' !== trim( (string) ( $settings['chatbot_name'] ?? '' ) );
		$knowledge_added = $faq_count > 0 || $source_count > 0;
		$store_tried     = in_array( 'try_it', $completed_steps, true );

		if ( $knowledge_added ) {
			$knowledge_parts = array();
			if ( $faq_count > 0 ) {
				/* translators: %d: number of FAQ pairs */
				$knowledge_parts[] = sprintf( _n( '%d FAQ pair', '%d FAQ pairs', $faq_count, 'wp-ai-chatbot' ), $faq_count );
			}
			if ( $source_count > 0 ) {
				/* translators: %d: number of uploaded CSV files */
				$knowledge_parts[] = sprintf( _n( '%d CSV file', '%d CSV files', $source_count, 'wp-ai-chatbot' ), $source_count );
			}
			$knowledge_status = implode( ' · ', $knowledge_parts );
		} elseif ( $index_built ) {
			$knowledge_status = __( 'Site content is indexed — add FAQ pairs or a CSV for better answers.', 'wp-ai-chatbot' );
		} else {
			$knowledge_status = __( 'Add FAQ pairs or upload a CSV so the bot knows your business.', 'wp-ai-chatbot' );
		}

		$license_url = $this->license_manager->get_activation_url();
		if ( '' === $license_url ) {
			$license_url = add_query_arg( 'tab', 'api', admin_url( 'admin.php?page=wp-ai-chatbot' ) );
		}

		$steps = array(
			array(
				'done'       => $license_ready,
				'title'      => __( 'Start your trial or activate your license', 'wp-ai-chatbot' ),
				'status'     => $license_ready
					? __( 'Chat is ready to show on your site.', 'wp-ai-chatbot' )
					: sprintf(
						/* translators: %s: reason the chat widget is hidden on the frontend */
						__( 'The chat stays hidden until this is done — %s.', 'wp-ai-chatbot' ),
						$this->get_chat_hidden_reason()
					),
				'link'       => $license_url,
				'link_label' => __( 'Open Licensing', 'wp-ai-chatbot' ),
				'link_id'    => '',
				'new_tab'    => false,
			),
			array(
				'done'       => $branded,
				'title'      => __( 'Name and brand your chatbot', 'wp-ai-chatbot' ),
				'status'     => $branded
					? sprintf(
						/* translators: %s: configured chatbot name */
						__( 'Named "%s" — tweak colors and logo anytime.', 'wp-ai-chatbot' ),
						(string) $settings['chatbot_name']
					)
					: __( 'Give it a name, logo, and your brand color.', 'wp-ai-chatbot' ),
				'link'       => add_query_arg( 'tab', 'appearance', admin_url( 'admin.php?page=wp-ai-chatbot' ) ),
				'link_label' => __( 'Open Appearance', 'wp-ai-chatbot' ),
				'link_id'    => '',
				'new_tab'    => false,
			),
			array(
				'done'       => $knowledge_added,
				'title'      => __( 'Add knowledge', 'wp-ai-chatbot' ),
				'status'     => $knowledge_status,
				'link'       => add_query_arg( 'tab', 'knowledge', admin_url( 'admin.php?page=wp-ai-chatbot' ) ),
				'link_label' => __( 'Open Knowledge', 'wp-ai-chatbot' ),
				'link_id'    => '',
				'new_tab'    => false,
			),
			array(
				'done'       => $store_tried,
				'title'      => __( 'Open your store and try it', 'wp-ai-chatbot' ),
				'status'     => __( 'Ask the chatbot a question the way a shopper would.', 'wp-ai-chatbot' ),
				'link'       => home_url( '/' ),
				'link_label' => __( 'Visit your store', 'wp-ai-chatbot' ),
				'link_id'    => 'wpaic-onboarding-try-it',
				'new_tab'    => true,
			),
		);
		?>
		<div id="wpaic-onboarding" class="wpaic-card mb-[18px]">
			<div class="wpaic-card-header">
				<div class="min-w-0 flex-1">
					<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Get set up', 'wp-ai-chatbot' ); ?></h3>
					<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Four quick steps to put the chatbot to work on your store.', 'wp-ai-chatbot' ); ?></p>
				</div>
				<button type="button" id="wpaic-onboarding-dismiss" class="wpaic-btn wpaic-btn-sm"><?php esc_html_e( 'Dismiss', 'wp-ai-chatbot' ); ?></button>
			</div>
			<div class="wpaic-card-body flex flex-col gap-2.5">
				<?php foreach ( $steps as $step_index => $step ) : ?>
					<div class="flex items-center gap-3.5 px-4 py-3 border border-line rounded-[10px] bg-surface" data-onboarding-step="<?php echo esc_attr( (string) ( $step_index + 1 ) ); ?>" data-onboarding-done="<?php echo $step['done'] ? '1' : '0'; ?>">
						<span class="w-[22px] h-[22px] rounded-full grid place-items-center shrink-0 text-[11.5px] font-semibold <?php echo $step['done'] ? 'bg-success text-white' : 'bg-canvas text-muted border border-line-2'; ?>">
							<?php if ( $step['done'] ) : ?>
								<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
							<?php else : ?>
								<?php echo esc_html( (string) ( $step_index + 1 ) ); ?>
							<?php endif; ?>
						</span>
						<span class="flex-1 min-w-0">
							<span class="font-medium text-[13.5px] block"><?php echo esc_html( $step['title'] ); ?></span>
							<span class="text-muted text-[12.5px] block"><?php echo esc_html( $step['status'] ); ?></span>
						</span>
						<a href="<?php echo esc_url( $step['link'] ); ?>" class="wpaic-btn wpaic-btn-sm no-underline shrink-0"
							<?php echo '' !== $step['link_id'] ? 'id="' . esc_attr( $step['link_id'] ) . '"' : ''; ?>
							<?php echo $step['new_tab'] ? 'target="_blank" rel="noopener"' : ''; ?>>
							<?php echo esc_html( $step['link_label'] ); ?>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Persist onboarding checklist dismissal and manual step completion.
	 */
	public function ajax_update_onboarding(): void {
		check_ajax_referer( 'wpaic_onboarding', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-ai-chatbot' ) ) );
		}

		$onboarding = get_option( 'wpaic_onboarding', array() );
		$onboarding = is_array( $onboarding ) ? $onboarding : array();

		if ( ! empty( $_POST['dismissed'] ) ) {
			$onboarding['dismissed'] = true;
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		if ( 'try_it' === $step ) {
			$completed_steps = isset( $onboarding['steps'] ) && is_array( $onboarding['steps'] ) ? $onboarding['steps'] : array();
			if ( ! in_array( $step, $completed_steps, true ) ) {
				$completed_steps[] = $step;
			}
			$onboarding['steps'] = $completed_steps;
		}

		update_option( 'wpaic_onboarding', $onboarding );
		wp_send_json_success( array( 'onboarding' => $onboarding ) );
	}

	/**
	 * Render the General settings tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_general_tab( array $settings ): void {
		$enabled        = ! empty( $settings['enabled'] );
		$greeting       = $settings['greeting_message'] ?? 'Hello! How can I help you today?';
		$language       = $settings['language'] ?? 'auto';
		$tone_of_voice  = $settings['tone_of_voice'] ?? 'neutral';
		$system_prompt  = $settings['system_prompt'] ?? '';
		$retention_days = max( 0, (int) ( $settings['retention_days'] ?? 0 ) );
		$anonymize_ip   = array_key_exists( 'anonymize_ip', $settings ) ? ! empty( $settings['anonymize_ip'] ) : true;
		$languages     = array(
			'auto' => __( 'Auto-detect (match user)', 'wp-ai-chatbot' ),
			'en'   => __( 'English', 'wp-ai-chatbot' ),
			'es'   => __( 'Spanish', 'wp-ai-chatbot' ),
			'fr'   => __( 'French', 'wp-ai-chatbot' ),
			'de'   => __( 'German', 'wp-ai-chatbot' ),
			'it'   => __( 'Italian', 'wp-ai-chatbot' ),
			'pt'   => __( 'Portuguese', 'wp-ai-chatbot' ),
			'nl'   => __( 'Dutch', 'wp-ai-chatbot' ),
			'ru'   => __( 'Russian', 'wp-ai-chatbot' ),
			'zh'   => __( 'Chinese', 'wp-ai-chatbot' ),
			'ja'   => __( 'Japanese', 'wp-ai-chatbot' ),
			'ko'   => __( 'Korean', 'wp-ai-chatbot' ),
			'ar'   => __( 'Arabic', 'wp-ai-chatbot' ),
		);
		$tone_options = $this->get_tone_of_voice_options();
		if ( ! isset( $tone_options[ $tone_of_voice ] ) ) {
			$tone_of_voice = 'neutral';
		}
		$tone_descriptions = array(
			'neutral'      => __( 'Balanced, factual, straightforward', 'wp-ai-chatbot' ),
			'friendly'     => __( 'Warm, conversational, approachable', 'wp-ai-chatbot' ),
			'professional' => __( 'Polite, concise, business-focused', 'wp-ai-chatbot' ),
			'enthusiastic' => __( 'Upbeat, energetic, expressive', 'wp-ai-chatbot' ),
		);
		$tone_labels = array(
			'neutral'      => __( 'Neutral', 'wp-ai-chatbot' ),
			'friendly'     => __( 'Friendly', 'wp-ai-chatbot' ),
			'professional' => __( 'Professional', 'wp-ai-chatbot' ),
			'enthusiastic' => __( 'Enthusiastic', 'wp-ai-chatbot' ),
		);
		?>
		<div class="max-w-[960px] mx-auto px-8 pt-7 pb-4">
			<div class="mb-6">
				<h2 class="text-[26px] font-semibold text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'General', 'wp-ai-chatbot' ); ?></h2>
				<p class="text-muted text-sm mt-1"><?php esc_html_e( 'Core chatbot behavior, voice, and language.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<?php $this->render_onboarding_checklist( $settings ); ?>

			<div class="flex flex-col gap-[18px]">
				<div class="wpaic-card">
					<div class="wpaic-card-row">
						<div class="max-w-[60%]">
							<h4 class="text-sm font-semibold mb-1"><?php esc_html_e( 'Enable chatbot', 'wp-ai-chatbot' ); ?></h4>
							<p class="text-muted m-0 text-[13px]"><?php esc_html_e( 'Show the chat widget on every page of your website. Turn off to pause without losing settings.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<label class="wpaic-toggle">
							<input type="hidden" name="wpaic_settings[enabled]" value="0">
							<input type="checkbox" name="wpaic_settings[enabled]" value="1" <?php checked( $enabled ); ?>>
							<span class="wpaic-toggle-track"><span class="wpaic-toggle-thumb"></span></span>
						</label>
					</div>
				</div>

				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div>
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Messaging', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'What users see when they open the chat.', 'wp-ai-chatbot' ); ?></p>
						</div>
					</div>
					<div class="wpaic-card-body flex flex-col gap-[18px]">
						<div>
							<label for="wpaic_greeting" class="flex items-center gap-2.5 font-medium text-[13px] mb-1.5 text-ink">
								<?php esc_html_e( 'Greeting message', 'wp-ai-chatbot' ); ?>
							</label>
							<textarea id="wpaic_greeting" name="wpaic_settings[greeting_message]" rows="3"
								class="wpaic-input" style="resize: vertical; min-height: 88px; line-height: 1.6;"
								placeholder="<?php esc_attr_e( 'Hi there! Ask me anything about our products.', 'wp-ai-chatbot' ); ?>"><?php echo esc_textarea( $greeting ); ?></textarea>
							<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'First message shown when users open the chat.', 'wp-ai-chatbot' ); ?></span>
						</div>
						<div>
							<label for="wpaic_language" class="flex items-center gap-2.5 font-medium text-[13px] mb-1.5 text-ink">
								<?php esc_html_e( 'Response language', 'wp-ai-chatbot' ); ?>
							</label>
							<select id="wpaic_language" name="wpaic_settings[language]" class="wpaic-input wpaic-select">
								<?php foreach ( $languages as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $language, $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'Auto-detect mirrors the user\'s language.', 'wp-ai-chatbot' ); ?></span>
						</div>
					</div>
				</div>

				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div>
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Personality', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Choose a preset tone, or write a custom system prompt for finer control.', 'wp-ai-chatbot' ); ?></p>
						</div>
					</div>
					<div class="wpaic-card-body flex flex-col gap-[18px]">
						<div>
							<span class="block font-medium text-[13px] mb-2.5"><?php esc_html_e( 'Tone of voice', 'wp-ai-chatbot' ); ?></span>
							<div class="grid grid-cols-4 gap-2.5">
								<?php foreach ( $tone_labels as $tone_key => $tone_label ) : ?>
									<label class="p-3.5 border rounded-[10px] flex gap-3 cursor-pointer transition-colors hover:border-ink-2 <?php echo $tone_of_voice === $tone_key ? 'border-ink bg-surface' : 'border-line'; ?>">
										<input type="radio" name="wpaic_settings[tone_of_voice]" value="<?php echo esc_attr( $tone_key ); ?>" <?php checked( $tone_of_voice, $tone_key ); ?> class="sr-only wpaic-tone-radio">
										<span class="w-[18px] h-[18px] rounded-[5px] border-[1.5px] grid place-items-center shrink-0 mt-0.5 <?php echo $tone_of_voice === $tone_key ? 'bg-ink border-ink text-white' : 'bg-white border-line-2'; ?>">
											<?php if ( $tone_of_voice === $tone_key ) : ?>
												<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
											<?php endif; ?>
										</span>
										<span>
											<span class="font-semibold text-[13.5px] block mb-0.5"><?php echo esc_html( $tone_label ); ?></span>
											<span class="text-muted text-[12.5px] block"><?php echo esc_html( $tone_descriptions[ $tone_key ] ?? '' ); ?></span>
										</span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div>
							<label for="wpaic_system_prompt" class="flex items-center justify-between gap-2.5 font-medium text-[13px] mb-1.5 text-ink">
								<span><?php esc_html_e( 'Custom system prompt', 'wp-ai-chatbot' ); ?></span>
								<span class="font-normal text-muted text-xs"><?php esc_html_e( 'advanced · overrides tone preset', 'wp-ai-chatbot' ); ?></span>
							</label>
							<textarea id="wpaic_system_prompt" name="wpaic_settings[system_prompt]" rows="5"
								class="wpaic-input font-mono !text-[12.5px]" style="resize: vertical; min-height: 120px; line-height: 1.6;"
								placeholder="<?php esc_attr_e( 'Leave empty to use the selected tone preset.', 'wp-ai-chatbot' ); ?>"><?php echo esc_textarea( $system_prompt ); ?></textarea>
							<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'Define the chatbot\'s personality and behavior.', 'wp-ai-chatbot' ); ?></span>
						</div>
					</div>
				</div>

				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div>
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Privacy', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'How long visitor conversations are kept and how IP addresses are stored.', 'wp-ai-chatbot' ); ?></p>
						</div>
					</div>
					<div class="wpaic-card-row">
						<div class="max-w-[60%]">
							<h4 class="text-sm font-semibold mb-1"><?php esc_html_e( 'Anonymize visitor IP addresses', 'wp-ai-chatbot' ); ?></h4>
							<p class="text-muted m-0 text-[13px]"><?php esc_html_e( 'Store only truncated IPs (e.g. 203.0.113.0) for new conversations. Recommended for GDPR compliance.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<label class="wpaic-toggle">
							<input type="hidden" name="wpaic_settings[anonymize_ip]" value="0">
							<input type="checkbox" name="wpaic_settings[anonymize_ip]" value="1" <?php checked( $anonymize_ip ); ?>>
							<span class="wpaic-toggle-track"><span class="wpaic-toggle-thumb"></span></span>
						</label>
					</div>
					<div class="wpaic-card-body">
						<label for="wpaic_retention_days" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Conversation retention', 'wp-ai-chatbot' ); ?></label>
						<div class="flex items-center gap-2">
							<input type="number" id="wpaic_retention_days" name="wpaic_settings[retention_days]" value="<?php echo esc_attr( (string) $retention_days ); ?>" min="0" max="3650" class="wpaic-input !w-24">
							<span class="text-[13px] text-muted"><?php esc_html_e( 'days', 'wp-ai-chatbot' ); ?></span>
						</div>
						<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'Conversations idle for longer than this are deleted daily, including their messages and events. Set to 0 to keep conversations forever.', 'wp-ai-chatbot' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.wpaic-tone-radio').on('change', function() {
				var $cards = $(this).closest('.grid').find('label');
				$cards.removeClass('border-ink bg-surface').addClass('border-line');
				$cards.find('.w-\\[18px\\]').removeClass('bg-ink border-ink text-white').addClass('bg-white border-line-2').html('');
				var $selected = $(this).closest('label');
				$selected.removeClass('border-line').addClass('border-ink bg-surface');
				$selected.find('.w-\\[18px\\]').removeClass('bg-white border-line-2').addClass('bg-ink border-ink text-white').html('<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>');
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the API settings tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_api_tab( array $settings ): void {
		$provider_url        = $this->license_manager->get_provider_url();
		$provider_configured = $this->license_manager->is_provider_url_configured();
		$license_status      = $this->license_manager->get_license_status_label();
		$license_ok          = $this->license_manager->has_valid_chat_license();
		$activation_url      = $this->license_manager->get_activation_url();
		$account_url         = $this->license_manager->get_account_url();
		$pricing_url         = $this->license_manager->get_pricing_url();
		$provider_override   = $settings['provider_url_override'] ?? '';
		$show_provider_field = $this->license_manager->is_provider_url_override_allowed();
		?>
		<div class="max-w-[960px] mx-auto px-8 pt-7 pb-4">
			<div class="mb-6">
				<h2 class="text-[26px] font-semibold text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'Licensing', 'wp-ai-chatbot' ); ?></h2>
				<p class="text-muted text-sm mt-1"><?php esc_html_e( 'License status, billing, and provider configuration.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div class="flex flex-col gap-[18px]">
				<div class="wpaic-banner-info">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
					<span><?php esc_html_e( 'Licensing, billing, trials, account management, and plugin updates are handled automatically. The chat stays hidden until the trial or an active license is available.', 'wp-ai-chatbot' ); ?></span>
				</div>

				<div class="wpaic-card">
					<div class="px-5 pt-5 pb-4 flex items-start justify-between gap-4 flex-wrap">
						<div class="min-w-0">
							<div class="text-[11px] uppercase tracking-[0.08em] font-semibold text-muted-2 mb-2"><?php esc_html_e( 'License', 'wp-ai-chatbot' ); ?></div>
							<div class="flex items-center gap-2.5 flex-wrap">
								<h3 class="text-[18px] font-semibold m-0 leading-none"><?php echo esc_html( $license_status ); ?></h3>
								<span class="wpaic-status-pill <?php echo $license_ok ? 'wpaic-status-pill-live' : 'wpaic-status-pill-paused'; ?>">
									<span class="wpaic-status-dot <?php echo $license_ok ? 'wpaic-status-dot-live' : 'wpaic-status-dot-paused'; ?>"></span>
									<?php echo $license_ok ? esc_html__( 'Active', 'wp-ai-chatbot' ) : esc_html__( 'Inactive', 'wp-ai-chatbot' ); ?>
								</span>
							</div>
						</div>
						<div class="flex flex-wrap gap-2 shrink-0">
							<?php if ( ! $license_ok && '' !== $activation_url ) : ?>
								<a href="<?php echo esc_url( $activation_url ); ?>" class="wpaic-btn wpaic-btn-primary no-underline"><?php esc_html_e( 'Activate License', 'wp-ai-chatbot' ); ?></a>
							<?php endif; ?>
							<?php if ( $license_ok && '' !== $account_url ) : ?>
								<a href="<?php echo esc_url( $account_url ); ?>" class="wpaic-btn no-underline"><?php esc_html_e( 'Manage Billing', 'wp-ai-chatbot' ); ?></a>
							<?php endif; ?>
							<?php if ( '' !== $pricing_url ) : ?>
								<a href="<?php echo esc_url( $pricing_url ); ?>" class="wpaic-btn no-underline"><?php esc_html_e( 'See Plans', 'wp-ai-chatbot' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
					<div class="border-t border-line-2 px-5 py-4 flex items-center justify-between gap-4 flex-wrap">
						<div class="min-w-0 flex-1">
							<div class="text-[11px] uppercase tracking-[0.08em] font-semibold text-muted-2 mb-1.5"><?php esc_html_e( 'Provider endpoint', 'wp-ai-chatbot' ); ?></div>
							<code class="font-mono text-[12.5px] text-ink break-all block bg-canvas px-2 py-1 rounded-[6px] border border-line-2"><?php echo esc_html( $provider_url ); ?></code>
						</div>
						<span class="wpaic-tag <?php echo $provider_configured ? 'wpaic-tag-ok' : 'wpaic-tag-warn'; ?> shrink-0">
							<?php echo $provider_configured ? esc_html__( 'Connected', 'wp-ai-chatbot' ) : esc_html__( 'Placeholder URL', 'wp-ai-chatbot' ); ?>
						</span>
					</div>
				</div>

				<?php if ( $show_provider_field ) : ?>
					<div class="wpaic-card">
						<div class="wpaic-card-header">
							<div>
								<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Staging override', 'wp-ai-chatbot' ); ?></h3>
								<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Only available when staging overrides are explicitly enabled by constant.', 'wp-ai-chatbot' ); ?></p>
							</div>
						</div>
						<div class="wpaic-card-body">
							<label for="wpaic_provider_url_override" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Provider URL override', 'wp-ai-chatbot' ); ?></label>
							<input type="url" id="wpaic_provider_url_override" name="wpaic_settings[provider_url_override]" value="<?php echo esc_attr( (string) $provider_override ); ?>" class="wpaic-input" placeholder="https://staging-provider.example.com/wp-json/wpaip/v1/chat">
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Appearance settings tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_appearance_tab( array $settings ): void {
		$theme_color   = $settings['theme_color'] ?? '#2545B8';
		$chatbot_name  = $settings['chatbot_name'] ?? '';
		$chatbot_logo  = $settings['chatbot_logo'] ?? '';
		$chatbot_role  = $settings['chatbot_role'] ?? '';
		$preset_colors = array( '#1e2b5e', '#0f172a', '#2563eb', '#7c3aed', '#db2777', '#dc2626', '#ea580c', '#059669', '#0d9488', '#475569' );
		?>
		<div class="max-w-[1200px] mx-auto px-8 pt-7 pb-4">
			<div class="mb-6">
				<h2 class="text-[26px] font-semibold text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'Appearance', 'wp-ai-chatbot' ); ?></h2>
				<p class="text-muted text-sm mt-1"><?php esc_html_e( 'How the chat widget looks on your site.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div class="grid grid-cols-[1fr_480px] gap-6">
				<div class="flex flex-col gap-[18px]">
					<div class="wpaic-card">
						<div class="wpaic-card-header">
							<div>
								<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Identity', 'wp-ai-chatbot' ); ?></h3>
								<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Name, role, and logo.', 'wp-ai-chatbot' ); ?></p>
							</div>
						</div>
						<div class="wpaic-card-body flex flex-col gap-4">
							<div>
								<label for="wpaic_chatbot_name" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Chatbot name', 'wp-ai-chatbot' ); ?></label>
								<input type="text" id="wpaic_chatbot_name" name="wpaic_settings[chatbot_name]" value="<?php echo esc_attr( $chatbot_name ); ?>" class="wpaic-input" placeholder="<?php esc_attr_e( 'e.g. ShopBot', 'wp-ai-chatbot' ); ?>">
								<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'Leave empty for "AI Assistant".', 'wp-ai-chatbot' ); ?></span>
							</div>
							<div>
								<label for="wpaic_chatbot_role" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Role / subtitle', 'wp-ai-chatbot' ); ?></label>
								<input type="text" id="wpaic_chatbot_role" name="wpaic_settings[chatbot_role]" value="<?php echo esc_attr( $chatbot_role ); ?>" class="wpaic-input" placeholder="<?php esc_attr_e( 'e.g. Sales rep, Support, Concierge', 'wp-ai-chatbot' ); ?>">
								<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'Appears under the chatbot name.', 'wp-ai-chatbot' ); ?></span>
							</div>
							<div>
								<span class="block font-medium text-[13px] mb-1.5"><?php esc_html_e( 'Logo', 'wp-ai-chatbot' ); ?></span>
								<input type="hidden" id="wpaic_chatbot_logo" name="wpaic_settings[chatbot_logo]" value="<?php echo esc_attr( $chatbot_logo ); ?>">
								<div class="flex items-center gap-3.5">
									<div class="w-12 h-12 rounded-[10px] bg-canvas border border-dashed border-line grid place-items-center text-muted font-semibold text-lg overflow-hidden" style="font-family: var(--font-display);">
										<?php if ( ! empty( $chatbot_logo ) ) : ?>
											<img id="wpaic_logo_preview" src="<?php echo esc_attr( $chatbot_logo ); ?>" alt="" class="w-full h-full object-contain">
										<?php else : ?>
											<span id="wpaic_logo_letter"><?php echo esc_html( mb_strtoupper( mb_substr( $chatbot_name ?: 'A', 0, 1 ) ) ); ?></span>
											<img id="wpaic_logo_preview" src="" alt="" class="w-full h-full object-contain" style="display:none;">
										<?php endif; ?>
									</div>
									<div class="flex gap-2">
										<button type="button" id="wpaic_logo_upload" class="wpaic-btn wpaic-btn-sm">
											<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
											<?php esc_html_e( 'Upload', 'wp-ai-chatbot' ); ?>
										</button>
										<button type="button" id="wpaic_logo_remove" class="wpaic-btn wpaic-btn-sm wpaic-btn-danger" style="<?php echo empty( $chatbot_logo ) ? 'display:none' : ''; ?>">
											<?php esc_html_e( 'Remove', 'wp-ai-chatbot' ); ?>
										</button>
									</div>
								</div>
								<span class="text-xs text-muted mt-2 block"><?php esc_html_e( 'PNG or SVG, max 32px height in widget.', 'wp-ai-chatbot' ); ?></span>
							</div>
						</div>
					</div>

					<div class="wpaic-card">
						<div class="wpaic-card-header">
							<div>
								<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Theme', 'wp-ai-chatbot' ); ?></h3>
								<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Colors and placement.', 'wp-ai-chatbot' ); ?></p>
							</div>
						</div>
						<div class="wpaic-card-body flex flex-col gap-4">
							<div>
								<span class="block font-medium text-[13px] mb-1.5"><?php esc_html_e( 'Primary color', 'wp-ai-chatbot' ); ?></span>
								<div class="flex gap-2 items-center flex-wrap">
									<?php foreach ( $preset_colors as $color ) : ?>
										<button type="button"
											class="wpaic-color-dot <?php echo $theme_color === $color ? 'wpaic-color-dot-active' : ''; ?>"
											style="background: <?php echo esc_attr( $color ); ?>;"
											data-color="<?php echo esc_attr( $color ); ?>"
											aria-label="<?php
											/* translators: %s: hex color code preset. */
											echo esc_attr( sprintf( __( 'Use theme color %s', 'wp-ai-chatbot' ), $color ) );
											?>"></button>
									<?php endforeach; ?>
									<input type="text" name="wpaic_settings[theme_color]" value="<?php echo esc_attr( $theme_color ); ?>" class="wpaic-input font-mono !text-[12.5px] !w-28 ml-1.5" id="wpaic_theme_color_input">
								</div>
								<span class="text-xs text-muted mt-2.5 block"><?php esc_html_e( 'Used for chat header, buttons, and accents.', 'wp-ai-chatbot' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<div class="sticky top-[140px] self-start">
					<div class="rounded-[14px] border border-line overflow-hidden p-[22px] relative" style="min-height: 440px; background: linear-gradient(180deg, #eeece5, #f7f6f3);">
						<div class="absolute top-3 left-3.5 text-[10.5px] tracking-widest uppercase font-semibold text-muted bg-surface px-2 py-1 rounded-md border border-line z-10"><?php esc_html_e( 'Live preview', 'wp-ai-chatbot' ); ?></div>
						<div class="mt-[30px]" id="wpaic-admin-preview"></div>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.wpaic-color-dot').on('click', function() {
				var color = $(this).data('color');
				// Native event so the live preview and the unsaved-changes guard both see it.
				$('#wpaic_theme_color_input').val(color)[0].dispatchEvent(new Event('input', {bubbles: true}));
				$('.wpaic-color-dot').removeClass('wpaic-color-dot-active');
				$(this).addClass('wpaic-color-dot-active');
			});
			$('#wpaic_theme_color_input').on('input', function() {
				var val = $(this).val();
				$('.wpaic-color-dot').removeClass('wpaic-color-dot-active');
				$('.wpaic-color-dot[data-color="' + val + '"]').addClass('wpaic-color-dot-active');
			});
		});
		</script>
		<?php
	}

	/**
	 * Render the Engagement settings tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_engagement_tab( array $settings ): void {
		$proactive_enabled = ! empty( $settings['proactive_enabled'] );
		$proactive_delay   = $settings['proactive_delay'] ?? 10;
		$proactive_message = $settings['proactive_message'] ?? '';
		$proactive_pages   = $settings['proactive_pages'] ?? 'all';
		$handoff_enabled   = ! empty( $settings['handoff_enabled'] );
		$promotions_enabled = ! empty( $settings['promotions_enabled'] );
		$handoff_fields    = $settings['handoff_fields'] ?? array();
		$conversation_starters = $settings['conversation_starters'] ?? array();
		if ( ! is_array( $handoff_fields ) ) {
			$handoff_fields = array();
		}
		if ( ! is_array( $conversation_starters ) ) {
			$conversation_starters = array();
		}
		$optional_fields   = array(
			'phone_number'    => __( 'Phone Number', 'wp-ai-chatbot' ),
			'company'         => __( 'Company', 'wp-ai-chatbot' ),
			'order_number'    => __( 'Order Number', 'wp-ai-chatbot' ),
			'request_message' => __( 'Request Message', 'wp-ai-chatbot' ),
		);
		$page_options      = array(
			'all'      => __( 'All pages', 'wp-ai-chatbot' ),
			'shop'     => __( 'Shop pages only', 'wp-ai-chatbot' ),
			'product'  => __( 'Product pages only', 'wp-ai-chatbot' ),
			'homepage' => __( 'Homepage only', 'wp-ai-chatbot' ),
		);
		$starter_placeholders = array(
			__( 'What are your shipping times?', 'wp-ai-chatbot' ),
			__( 'Tell me about your best sellers', 'wp-ai-chatbot' ),
			__( 'Do you ship internationally?', 'wp-ai-chatbot' ),
			__( "What's your return policy?", 'wp-ai-chatbot' ),
			__( 'How do I contact support?', 'wp-ai-chatbot' ),
		);
		?>
		<div class="max-w-[960px] mx-auto px-8 pt-7 pb-4">
			<div class="mb-6">
				<h2 class="text-[26px] font-semibold text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'Engagement', 'wp-ai-chatbot' ); ?></h2>
				<p class="text-muted text-sm mt-1"><?php esc_html_e( 'Conversation starters, handoff, and proactive features.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div class="flex flex-col gap-[18px]">
				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div class="min-w-0 flex-1">
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Conversation starters', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Shown as quick-tap chips in the empty chat state.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<span class="text-xs text-muted shrink-0"><?php echo esc_html( count( array_filter( $conversation_starters ) ) . '/5' ); ?></span>
					</div>
					<div class="wpaic-card-body">
						<div class="mb-3.5">
							<div class="wpaic-banner-info">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
								<span><?php esc_html_e( 'Leave all blank to auto-generate starters from your site content.', 'wp-ai-chatbot' ); ?></span>
							</div>
						</div>
						<div class="flex flex-col gap-2">
							<?php for ( $i = 0; $i < 5; $i++ ) : ?>
								<div class="flex items-center gap-2.5">
									<span class="w-[22px] h-[22px] rounded-md bg-canvas grid place-items-center text-[11px] text-muted font-semibold"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
									<input type="text" name="wpaic_settings[conversation_starters][]"
										value="<?php echo esc_attr( (string) ( $conversation_starters[ $i ] ?? '' ) ); ?>"
										class="wpaic-input"
										placeholder="<?php echo esc_attr( 'e.g. ' . $starter_placeholders[ $i ] ); ?>">
								</div>
							<?php endfor; ?>
						</div>
					</div>
				</div>

				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div>
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Human handoff', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Allow customers to request human support.', 'wp-ai-chatbot' ); ?></p>
						</div>
					</div>
					<div class="wpaic-card-row">
						<div class="max-w-[60%]">
							<h4 class="text-sm font-semibold mb-1"><?php esc_html_e( 'Enable handoff', 'wp-ai-chatbot' ); ?></h4>
							<p class="text-muted m-0 text-[13px]"><?php esc_html_e( 'Bot collects name/email and sends notification.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<label class="wpaic-toggle">
							<input type="hidden" name="wpaic_settings[handoff_enabled]" value="0">
							<input type="checkbox" name="wpaic_settings[handoff_enabled]" value="1" <?php checked( $handoff_enabled ); ?>>
							<span class="wpaic-toggle-track"><span class="wpaic-toggle-thumb"></span></span>
						</label>
					</div>
					<div class="wpaic-card-body">
						<span class="block font-medium text-[13px] mb-2"><?php esc_html_e( 'Collected fields', 'wp-ai-chatbot' ); ?></span>
						<p class="text-muted text-[12.5px] mb-3"><?php esc_html_e( 'Select which fields the bot collects before submitting a support request.', 'wp-ai-chatbot' ); ?></p>
						<div class="flex flex-wrap gap-2">
							<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[13px] font-medium bg-accent-soft text-accent-ink opacity-75 cursor-not-allowed">
								<?php esc_html_e( 'Name', 'wp-ai-chatbot' ); ?>
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
							</span>
							<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[13px] font-medium bg-accent-soft text-accent-ink opacity-75 cursor-not-allowed">
								<?php esc_html_e( 'Email', 'wp-ai-chatbot' ); ?>
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
							</span>
							<?php foreach ( $optional_fields as $field_key => $field_label ) : ?>
								<label class="inline-flex items-center px-3 py-1.5 rounded-full text-[13px] font-medium cursor-pointer transition-colors
									<?php echo in_array( $field_key, $handoff_fields, true ) ? 'bg-accent-soft text-accent-ink' : 'bg-canvas text-muted hover:bg-line-2'; ?>">
									<input type="checkbox" name="wpaic_settings[handoff_fields][]" value="<?php echo esc_attr( $field_key ); ?>"
										<?php checked( in_array( $field_key, $handoff_fields, true ) ); ?>
										class="sr-only wpaic-handoff-field-checkbox">
									<?php echo esc_html( $field_label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div>
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Coupons & promotions', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Let the assistant share your active coupon codes with shoppers.', 'wp-ai-chatbot' ); ?></p>
						</div>
					</div>
					<div class="wpaic-card-row">
						<div class="max-w-[60%]">
							<h4 class="text-sm font-semibold mb-1"><?php esc_html_e( 'Advertise coupons in chat', 'wp-ai-chatbot' ); ?></h4>
							<p class="text-muted m-0 text-[13px]"><?php esc_html_e( 'When enabled, the assistant can list every published, non-expired coupon when shoppers ask about discounts. Leave off if you have private codes (e.g. support credits or partner codes) — the assistant will say it has no promotion info instead.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<label class="wpaic-toggle">
							<input type="hidden" name="wpaic_settings[promotions_enabled]" value="0">
							<input type="checkbox" name="wpaic_settings[promotions_enabled]" value="1" <?php checked( $promotions_enabled ); ?>>
							<span class="wpaic-toggle-track"><span class="wpaic-toggle-thumb"></span></span>
						</label>
					</div>
				</div>

				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div>
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Proactive message', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Show a dismissible teaser bubble next to the launcher after a delay.', 'wp-ai-chatbot' ); ?></p>
						</div>
					</div>
					<div class="wpaic-card-row">
						<div class="max-w-[60%]">
							<h4 class="text-sm font-semibold mb-1"><?php esc_html_e( 'Enable proactive message', 'wp-ai-chatbot' ); ?></h4>
							<p class="text-muted m-0 text-[13px]"><?php esc_html_e( 'Show the teaser after the visitor is on a page for the specified time. Clicking it opens the chat.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<label class="wpaic-toggle">
							<input type="hidden" name="wpaic_settings[proactive_enabled]" value="0">
							<input type="checkbox" name="wpaic_settings[proactive_enabled]" value="1" <?php checked( $proactive_enabled ); ?>>
							<span class="wpaic-toggle-track"><span class="wpaic-toggle-thumb"></span></span>
						</label>
					</div>
					<div class="wpaic-card-body flex flex-col gap-4">
						<div class="grid grid-cols-2 gap-4">
							<div>
								<label for="wpaic_proactive_delay" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Trigger delay', 'wp-ai-chatbot' ); ?></label>
								<div class="flex items-center gap-2">
									<input type="number" id="wpaic_proactive_delay" name="wpaic_settings[proactive_delay]" value="<?php echo esc_attr( (string) $proactive_delay ); ?>" min="1" max="300" class="wpaic-input !w-24">
									<span class="text-[13px] text-muted"><?php esc_html_e( 'seconds', 'wp-ai-chatbot' ); ?></span>
								</div>
							</div>
							<div>
								<label for="wpaic_proactive_pages" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Show on pages', 'wp-ai-chatbot' ); ?></label>
								<select id="wpaic_proactive_pages" name="wpaic_settings[proactive_pages]" class="wpaic-input wpaic-select">
									<?php foreach ( $page_options as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $proactive_pages, $key ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
						<div>
							<label for="wpaic_proactive_message" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Proactive message', 'wp-ai-chatbot' ); ?></label>
							<textarea id="wpaic_proactive_message" name="wpaic_settings[proactive_message]" rows="2"
								class="wpaic-input" style="resize: vertical; min-height: 60px; line-height: 1.6;"
								placeholder="<?php esc_attr_e( 'Hi! Looking for something specific? I can help you find the perfect product.', 'wp-ai-chatbot' ); ?>"><?php echo esc_textarea( $proactive_message ); ?></textarea>
							<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'Leave empty to use the greeting message.', 'wp-ai-chatbot' ); ?></span>
						</div>
						<div>
							<span class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Live preview', 'wp-ai-chatbot' ); ?></span>
							<div class="rounded-[14px] border border-line overflow-hidden relative" style="background: linear-gradient(180deg, #eeece5, #f7f6f3);">
								<div id="wpaic-admin-preview" data-preview-variant="teaser"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the unified Knowledge tab (data sources + FAQs + search index).
	 */
	private function render_knowledge_tab(): void {
		$settings               = get_option( 'wpaic_settings', array() );
		$settings               = is_array( $settings ) ? $settings : array();
		$product_index_enabled  = ! array_key_exists( 'product_index_enabled', $settings ) || ! empty( $settings['product_index_enabled'] );
		$available_post_types   = $this->get_available_content_post_types();
		$selected_post_types    = array_key_exists( 'content_index_post_types', $settings ) && is_array( $settings['content_index_post_types'] )
			? array_values( array_intersect( array_map( 'sanitize_key', $settings['content_index_post_types'] ), array_keys( $available_post_types ) ) )
			: array( 'page', 'post' );
		$search_index           = new WPAIC_Search_Index();
		$product_status         = $search_index->get_index_status();
		$content_index          = new WPAIC_Content_Index();
		$content_status         = $content_index->get_index_status();
		$content_sources_active = ! empty( $selected_post_types );

		global $wpdb;
		$sources_table = $wpdb->prefix . 'wpaic_data_sources';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$data_sources = $wpdb->get_results( "SELECT * FROM $sources_table ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$faqs_table = $wpdb->prefix . 'wpaic_faqs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$faqs          = $wpdb->get_results( "SELECT * FROM $faqs_table ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$faq_count     = is_array( $faqs ) ? count( $faqs ) : 0;
		$max_faq_pairs = WPAIC_System_Prompt::MAX_FAQ_PAIRS;
		$faq_text      = '';
		if ( ! empty( $faqs ) ) {
			$pairs = array();
			foreach ( $faqs as $faq ) {
				$pairs[] = "Q: " . $faq->question . "\nA: " . $faq->answer;
			}
			$faq_text = implode( "\n\n", $pairs );
		}

		// "Add as FAQ" deep link from the Chat Logs missed-searches report.
		$prefill_faq_question = isset( $_GET['wpaic_faq_question'] ) ? sanitize_text_field( wp_unslash( $_GET['wpaic_faq_question'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $prefill_faq_question ) {
			$prefill_block = 'Q: ' . $prefill_faq_question . "\nA: ";
			$faq_text      = '' === $faq_text ? $prefill_block : $faq_text . "\n\n" . $prefill_block;
		}

		$total_indexed = 0;
		if ( $product_status['exists'] ) {
			$total_indexed += (int) $product_status['product_count'];
		}
		if ( $content_status['exists'] ) {
			$total_indexed += (int) $content_status['post_count'];
		}
		?>
		<div class="max-w-[960px] mx-auto px-8 pt-7 pb-16">
			<div class="mb-6">
				<h2 class="text-[26px] font-semibold text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'Knowledge', 'wp-ai-chatbot' ); ?></h2>
				<p class="text-muted text-sm mt-1"><?php esc_html_e( 'Everything the bot knows about your business — uploads, FAQs, and indexed site content.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div class="flex flex-col gap-[18px]">

				<!-- Uploaded data sources -->
				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div class="min-w-0 flex-1">
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Uploaded data', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'CSV files. Up to 5 MB each.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<button type="button" id="wpaic-add-source" class="wpaic-btn wpaic-btn-sm">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
							<?php esc_html_e( 'Upload file', 'wp-ai-chatbot' ); ?>
						</button>
					</div>
					<div class="wpaic-card-body">
						<?php if ( ! empty( $data_sources ) ) : ?>
							<?php foreach ( $data_sources as $source ) : ?>
								<?php $columns = json_decode( $source->columns, true ) ?: array(); ?>
								<div class="flex items-center gap-3.5 px-4 py-3.5 border border-line rounded-[10px] bg-surface mb-2 last:mb-0" data-source-id="<?php echo esc_attr( (string) $source->id ); ?>">
									<div class="w-[34px] h-[34px] rounded-lg bg-canvas text-muted grid place-items-center shrink-0">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
									</div>
									<div class="flex-1 min-w-0">
										<div class="font-medium text-[13.5px]"><?php echo esc_html( $source->label ); ?></div>
										<div class="text-xs text-muted"><?php echo esc_html( $source->name ); ?> · <?php echo esc_html( (string) $source->row_count ); ?> <?php esc_html_e( 'rows', 'wp-ai-chatbot' ); ?></div>
									</div>
									<span class="wpaic-tag wpaic-tag-ok">
										<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
										<?php esc_html_e( 'Trained', 'wp-ai-chatbot' ); ?>
									</span>
									<button type="button" class="wpaic-delete-source w-6 h-6 rounded-md grid place-items-center text-muted hover:bg-canvas hover:text-danger bg-transparent border-0 cursor-pointer transition-colors" data-id="<?php echo esc_attr( (string) $source->id ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
									</button>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

						<div class="mt-3 px-5 py-8 rounded-[10px] border-[1.5px] border-dashed border-line bg-surface-2 text-center">
							<div class="w-10 h-10 rounded-lg bg-canvas grid place-items-center mx-auto mb-2.5 text-muted">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
							</div>
							<div class="font-medium text-[13.5px]"><?php esc_html_e( 'Drop CSV files here', 'wp-ai-chatbot' ); ?></div>
							<div class="text-muted text-[12.5px] mt-0.5"><?php esc_html_e( 'or', 'wp-ai-chatbot' ); ?> <span class="text-accent-ink font-medium cursor-pointer wpaic-browse-files"><?php esc_html_e( 'browse files', 'wp-ai-chatbot' ); ?></span> · <?php esc_html_e( 'max 5 MB', 'wp-ai-chatbot' ); ?></div>
						</div>
					</div>
				</div>

				<!-- FAQ pairs -->
				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div class="min-w-0 flex-1">
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'FAQ pairs', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Hand-written Q&A pairs. The bot prefers these over generated answers.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<?php if ( $faq_count > 0 ) : ?>
							<span class="wpaic-tag wpaic-tag-neutral shrink-0">
								<?php
								/* translators: %d: number of saved FAQ pairs */
								echo esc_html( sprintf( _n( '%d pair', '%d pairs', $faq_count, 'wp-ai-chatbot' ), $faq_count ) );
								?>
							</span>
						<?php endif; ?>
					</div>
					<div class="wpaic-card-body">
						<textarea id="wpaic_faq_content" rows="10" class="wpaic-input font-mono !text-[12.5px]" style="resize: vertical; min-height: 140px; line-height: 1.6;"
							placeholder="Q: What is your return policy?
A: We offer 30-day returns on all items.

Q: Do you ship internationally?
A: Yes, we ship to over 50 countries."><?php echo esc_textarea( $faq_text ); ?></textarea>
						<span class="text-xs text-muted mt-1.5 block"><?php esc_html_e( 'Format: "Q: question" then "A: answer". Separate pairs with a blank line.', 'wp-ai-chatbot' ); ?></span>
						<?php if ( $faq_count > $max_faq_pairs ) : ?>
							<div class="mt-2.5 p-3 bg-warn-soft text-warn-ink rounded-[10px] text-[13px]">
								<?php
								/* translators: 1: number of FAQ pairs used in answers, 2: total number of saved FAQ pairs */
								echo esc_html( sprintf( __( 'Using %1$d of %2$d — only the first %1$d FAQ pairs are included in answers. Move your most important pairs to the top.', 'wp-ai-chatbot' ), $max_faq_pairs, $faq_count ) );
								?>
							</div>
						<?php endif; ?>
						<?php if ( '' !== $prefill_faq_question ) : ?>
							<script>
							jQuery(document).ready(function($) {
								var textarea = $('#wpaic_faq_content')[0];
								textarea.scrollIntoView({ block: 'center' });
								textarea.focus();
								textarea.setSelectionRange(textarea.value.length, textarea.value.length);
							});
							</script>
						<?php endif; ?>
						<div class="mt-4 flex items-center gap-4">
							<button type="button" id="wpaic-save-faqs" class="wpaic-btn wpaic-btn-primary">
								<?php esc_html_e( 'Save FAQs', 'wp-ai-chatbot' ); ?>
							</button>
							<span id="wpaic-faq-status" class="text-sm hidden"></span>
						</div>
					</div>
				</div>

				<!-- Indexed site content -->
				<div class="wpaic-card">
					<div class="wpaic-card-header">
						<div class="min-w-0 flex-1">
							<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Indexed site content', 'wp-ai-chatbot' ); ?></h3>
							<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Choose which content types the bot can search at answer time.', 'wp-ai-chatbot' ); ?></p>
						</div>
						<button type="button" id="wpaic-update-search-indexes" class="wpaic-btn wpaic-btn-sm">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
							<?php esc_html_e( 'Re-index all', 'wp-ai-chatbot' ); ?>
						</button>
					</div>
					<div class="wpaic-card-body">
						<div class="grid grid-cols-2 gap-2.5">
							<label class="p-3.5 border rounded-[10px] flex gap-3 cursor-pointer transition-colors hover:border-ink-2 <?php echo $product_index_enabled ? 'border-ink bg-surface' : 'border-line'; ?>">
								<input type="checkbox" id="wpaic_product_index_enabled" name="product_index_enabled" value="1" <?php checked( $product_index_enabled ); ?> class="sr-only wpaic-index-checkbox">
								<span class="w-[18px] h-[18px] rounded-[5px] border-[1.5px] grid place-items-center shrink-0 mt-0.5 <?php echo $product_index_enabled ? 'bg-ink border-ink text-white' : 'bg-white border-line-2'; ?>">
									<?php if ( $product_index_enabled ) : ?>
										<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
									<?php endif; ?>
								</span>
								<span class="flex-1">
									<span class="flex items-center justify-between">
										<span class="font-semibold text-[13.5px]"><?php esc_html_e( 'Products', 'wp-ai-chatbot' ); ?></span>
										<span class="wpaic-tag wpaic-tag-neutral" id="wpaic-product-count"><?php echo esc_html( (string) ( $product_status['exists'] ? $product_status['product_count'] : 0 ) ); ?></span>
									</span>
									<span class="text-muted text-[12.5px] block"><?php esc_html_e( 'WooCommerce product index', 'wp-ai-chatbot' ); ?></span>
								</span>
							</label>

							<?php foreach ( $available_post_types as $post_type => $label ) : ?>
								<?php $is_checked = in_array( $post_type, $selected_post_types, true ); ?>
								<label class="p-3.5 border rounded-[10px] flex gap-3 cursor-pointer transition-colors hover:border-ink-2 <?php echo $is_checked ? 'border-ink bg-surface' : 'border-line'; ?>">
									<input type="checkbox" name="content_index_post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( $is_checked ); ?> class="sr-only wpaic-index-checkbox wpaic-content-index-post-type">
									<span class="w-[18px] h-[18px] rounded-[5px] border-[1.5px] grid place-items-center shrink-0 mt-0.5 <?php echo $is_checked ? 'bg-ink border-ink text-white' : 'bg-white border-line-2'; ?>">
										<?php if ( $is_checked ) : ?>
											<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
										<?php endif; ?>
									</span>
									<span class="flex-1">
										<span class="font-semibold text-[13.5px]"><?php echo esc_html( $label ); ?></span>
										<span class="text-muted text-[12.5px] block"><?php esc_html_e( 'WordPress content', 'wp-ai-chatbot' ); ?></span>
									</span>
								</label>
							<?php endforeach; ?>
						</div>

						<div class="mt-[18px] px-4 py-3.5 bg-surface-2 rounded-[10px] flex items-center gap-3.5">
							<span id="wpaic-index-status-dot" class="w-2 h-2 rounded-full <?php echo ( $product_status['exists'] || $content_status['exists'] ) ? 'bg-success' : 'bg-muted-2'; ?>"></span>
							<span class="flex-1 text-[13px]" id="wpaic-index-status-text">
								<?php if ( $product_status['exists'] || $content_status['exists'] ) : ?>
									<span class="font-medium"><?php esc_html_e( 'Up to date.', 'wp-ai-chatbot' ); ?></span>
									<span class="text-muted">
										<?php echo esc_html( (string) $total_indexed ); ?> <?php esc_html_e( 'items', 'wp-ai-chatbot' ); ?>
										· <?php esc_html_e( 'content changes are indexed automatically', 'wp-ai-chatbot' ); ?>
										<?php
										$last_updated = $product_status['last_updated'] ?? $content_status['last_updated'] ?? null;
										if ( $last_updated ) :
											?>
											· <?php esc_html_e( 'last full rebuild', 'wp-ai-chatbot' ); ?> <?php echo esc_html( $this->format_index_updated_at( $last_updated ) ); ?>
										<?php endif; ?>
									</span>
									<?php if ( $product_index_enabled && ! $product_status['exists'] ) : ?>
										<span id="wpaic-products-unindexed-note" class="block text-warn-ink"><?php esc_html_e( 'Products are not indexed yet — activation only indexes site content. Click "Re-index all" to add your products.', 'wp-ai-chatbot' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="font-medium"><?php esc_html_e( 'No index built yet.', 'wp-ai-chatbot' ); ?></span>
									<span class="text-muted"><?php esc_html_e( 'Click "Re-index all" to build.', 'wp-ai-chatbot' ); ?></span>
								<?php endif; ?>
							</span>
							<span id="wpaic-update-search-indexes-status" class="text-sm"></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Upload modal -->
		<div id="wpaic-source-modal" style="display:none;">
			<div class="wpaic-modal-backdrop" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;"></div>
			<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:500px;max-width:90%;border-radius:14px;z-index:100001;overflow:hidden;">
				<div class="flex items-center justify-between px-5 py-4 border-b border-line">
					<h2 class="text-[16px] font-semibold m-0" style="font-family: var(--font-display);"><?php esc_html_e( 'Add Data Source', 'wp-ai-chatbot' ); ?></h2>
					<button type="button" class="wpaic-modal-close w-6 h-6 rounded-md grid place-items-center text-muted hover:bg-canvas bg-transparent border-0 cursor-pointer">&times;</button>
				</div>
				<div class="p-5">
					<form id="wpaic-source-form" enctype="multipart/form-data">
						<div class="flex flex-col gap-4">
							<div>
								<label for="wpaic_source_name" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Name (slug)', 'wp-ai-chatbot' ); ?></label>
								<input type="text" id="wpaic_source_name" name="source_name" required pattern="[a-z0-9_-]+" class="wpaic-input" placeholder="e.g. services">
								<span class="text-xs text-muted mt-1 block"><?php esc_html_e( 'Lowercase letters, numbers, underscores, hyphens only.', 'wp-ai-chatbot' ); ?></span>
							</div>
							<div>
								<label for="wpaic_source_label" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Label', 'wp-ai-chatbot' ); ?></label>
								<input type="text" id="wpaic_source_label" name="source_label" required class="wpaic-input" placeholder="e.g. Our Services">
							</div>
							<div>
								<label for="wpaic_source_desc" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'Description', 'wp-ai-chatbot' ); ?></label>
								<textarea id="wpaic_source_desc" name="source_description" rows="2" required class="wpaic-input" style="resize:vertical;min-height:60px;line-height:1.6;"
									placeholder="<?php esc_attr_e( 'What information does this data contain? The bot uses this to decide when to query it.', 'wp-ai-chatbot' ); ?>"></textarea>
							</div>
							<div>
								<label for="wpaic_source_file" class="block font-medium text-[13px] mb-1.5 text-ink"><?php esc_html_e( 'CSV File', 'wp-ai-chatbot' ); ?></label>
								<input type="file" id="wpaic_source_file" name="csv_file" accept=".csv" required class="wpaic-input !p-0 file:mr-3 file:py-2 file:px-4 file:border-0 file:text-sm file:font-medium file:bg-canvas file:text-ink file:cursor-pointer">
								<span class="text-xs text-muted mt-1 block"><?php esc_html_e( 'Max 5MB. First row must be column headers.', 'wp-ai-chatbot' ); ?></span>
							</div>
							<div id="wpaic-upload-status" class="hidden"><span class="text-muted text-sm"><?php esc_html_e( 'Uploading...', 'wp-ai-chatbot' ); ?></span></div>
							<div id="wpaic-upload-result" class="hidden"></div>
						</div>
						<div class="mt-6 flex justify-end gap-3">
							<button type="button" class="wpaic-modal-cancel wpaic-btn"><?php esc_html_e( 'Cancel', 'wp-ai-chatbot' ); ?></button>
							<button type="submit" id="wpaic-upload-btn" class="wpaic-btn wpaic-btn-primary"><?php esc_html_e( 'Upload', 'wp-ai-chatbot' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Index checkbox toggle styling
			$('.wpaic-index-checkbox').on('change', function() {
				var $label = $(this).closest('label');
				var $box = $label.find('.w-\\[18px\\]');
				if (this.checked) {
					$label.removeClass('border-line').addClass('border-ink bg-surface');
					$box.removeClass('bg-white border-line-2').addClass('bg-ink border-ink text-white')
						.html('<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>');
				} else {
					$label.removeClass('border-ink bg-surface').addClass('border-line');
					$box.removeClass('bg-ink border-ink text-white').addClass('bg-white border-line-2').html('');
				}
			});

			// Handoff field checkbox styling
			$('.wpaic-handoff-field-checkbox').on('change', function() {
				var $label = $(this).closest('label');
				if (this.checked) {
					$label.removeClass('bg-canvas text-muted hover:bg-line-2').addClass('bg-accent-soft text-accent-ink');
				} else {
					$label.removeClass('bg-accent-soft text-accent-ink').addClass('bg-canvas text-muted hover:bg-line-2');
				}
			});

			// Upload modal
			$('#wpaic-add-source, .wpaic-browse-files').on('click', function() {
				$('#wpaic-source-modal').show();
				$('#wpaic-source-form')[0].reset();
				$('#wpaic-upload-status, #wpaic-upload-result').addClass('hidden');
			});
			$('.wpaic-modal-close, .wpaic-modal-cancel, .wpaic-modal-backdrop').on('click', function() {
				$('#wpaic-source-modal').hide();
			});

			$('#wpaic-source-form').on('submit', function(e) {
				e.preventDefault();
				var formData = new FormData(this);
				formData.append('action', 'wpaic_upload_csv');
				formData.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'wpaic_upload_csv' ) ); ?>');
				$('#wpaic-upload-status').removeClass('hidden');
				$('#wpaic-upload-result').addClass('hidden');
				$('#wpaic-upload-btn').prop('disabled', true);
				$.ajax({
					url: ajaxurl, type: 'POST', data: formData, processData: false, contentType: false,
					success: function(response) {
						$('#wpaic-upload-status').addClass('hidden');
						$('#wpaic-upload-btn').prop('disabled', false);
						if (response.success) {
							$('#wpaic-upload-result').removeClass('hidden').html('<div class="p-3 bg-success-soft text-success-ink rounded-[10px] text-[13px]">' + response.data.message + '</div>');
							setTimeout(function() { location.reload(); }, 1500);
						} else {
							$('#wpaic-upload-result').removeClass('hidden').html('<div class="p-3 bg-warn-soft text-warn-ink rounded-[10px] text-[13px]">' + (response.data ? response.data.message : '<?php echo esc_js( __( 'Upload failed.', 'wp-ai-chatbot' ) ); ?>') + '</div>');
						}
					},
					error: function() {
						$('#wpaic-upload-status').addClass('hidden');
						$('#wpaic-upload-btn').prop('disabled', false);
						$('#wpaic-upload-result').removeClass('hidden').html('<div class="p-3 bg-warn-soft text-warn-ink rounded-[10px] text-[13px]"><?php echo esc_js( __( 'Request failed.', 'wp-ai-chatbot' ) ); ?></div>');
					}
				});
			});

			// Delete data source
			$('.wpaic-delete-source').on('click', function() {
				if (!confirm('<?php echo esc_js( __( 'Delete this data source? This cannot be undone.', 'wp-ai-chatbot' ) ); ?>')) return;
				var $btn = $(this);
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'wpaic_delete_data_source', source_id: $btn.data('id'), _wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_delete_source' ) ); ?>' },
					success: function(response) {
						if (response.success) $btn.closest('[data-source-id]').fadeOut(function() { $(this).remove(); });
						else alert(response.data ? response.data.message : '<?php echo esc_js( __( 'Delete failed.', 'wp-ai-chatbot' ) ); ?>');
					}
				});
			});

			// Save FAQs
			$('#wpaic-save-faqs').on('click', function() {
				var $btn = $(this), $status = $('#wpaic-faq-status');
				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'wp-ai-chatbot' ) ); ?>');
				$status.addClass('hidden');
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'wpaic_save_faqs', faq_content: $('#wpaic_faq_content').val(), _wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_save_faqs' ) ); ?>' },
					success: function(response) {
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save FAQs', 'wp-ai-chatbot' ) ); ?>');
						if (response.success) $status.removeClass('hidden').css('color', 'var(--color-success-ink)').text(response.data.message);
						else $status.removeClass('hidden').css('color', 'var(--color-danger)').text(response.data ? response.data.message : '<?php echo esc_js( __( 'Save failed.', 'wp-ai-chatbot' ) ); ?>');
					},
					error: function() {
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save FAQs', 'wp-ai-chatbot' ) ); ?>');
						$status.removeClass('hidden').css('color', 'var(--color-danger)').text('<?php echo esc_js( __( 'Request failed.', 'wp-ai-chatbot' ) ); ?>');
					}
				});
			});

			// Update search indexes
			$('#wpaic-update-search-indexes').on('click', function() {
				var $btn = $(this);
				var $status = $('#wpaic-update-search-indexes-status');
				var contentTypes = $('.wpaic-content-index-post-type:checked').map(function() { return $(this).val(); }).get();
				$btn.prop('disabled', true).css('opacity', '0.5');
				$status.html('<span class="text-muted"><?php echo esc_js( __( 'Updating...', 'wp-ai-chatbot' ) ); ?></span>');
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: {
						action: 'wpaic_update_search_indexes',
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_update_search_indexes' ) ); ?>',
						product_index_enabled: $('#wpaic_product_index_enabled').is(':checked') ? '1' : '',
						content_index_post_types: contentTypes
					},
					success: function(response) {
						$btn.prop('disabled', false).css('opacity', '1');
						if (response.success) {
							$status.html('<span style="color:var(--color-success-ink)">' + response.data.message + '</span>');
							var total = Number(response.data.product.count || 0) + Number(response.data.content.count || 0);
							$('#wpaic-product-count').text(response.data.product.count || 0);
							$('#wpaic-index-status-dot').removeClass('bg-muted-2').addClass('bg-success');
							var updated = response.data.product.last_updated_label || response.data.content.last_updated_label || '';
							$('#wpaic-index-status-text').html('<span class="font-medium"><?php echo esc_js( __( 'Up to date.', 'wp-ai-chatbot' ) ); ?></span> <span class="text-muted">' + total + ' <?php echo esc_js( __( 'items', 'wp-ai-chatbot' ) ); ?> · <?php echo esc_js( __( 'content changes are indexed automatically', 'wp-ai-chatbot' ) ); ?>' + (updated ? ' · <?php echo esc_js( __( 'last full rebuild', 'wp-ai-chatbot' ) ); ?> ' + updated : '') + '</span>');
						} else {
							$status.html('<span style="color:var(--color-danger)">' + (response.data ? response.data.message : '<?php echo esc_js( __( 'Error', 'wp-ai-chatbot' ) ); ?>') + '</span>');
						}
					},
					error: function() {
						$btn.prop('disabled', false).css('opacity', '1');
						$status.html('<span style="color:var(--color-danger)"><?php echo esc_js( __( 'Request failed.', 'wp-ai-chatbot' ) ); ?></span>');
					}
				});
			});
		});
		</script>
		<?php
	}


	public function render_logs_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$page      = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = '';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = '';
		}
		$filters = array(
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);

		$per_page      = 20;
		$offset        = ( $page - 1 ) * $per_page;
		$conversations = $this->logs->get_conversations( $per_page, $offset, $filters );
		$total         = $this->logs->get_conversation_count( $filters );
		$total_pages   = (int) ceil( $total / $per_page );
		$has_filters   = '' !== $search || '' !== $date_from || '' !== $date_to;

		// This week vs previous week, trailing 7-day windows in local (site) time.
		$now_timestamp       = (int) strtotime( current_time( 'mysql' ) );
		$now_exclusive       = gmdate( 'Y-m-d H:i:s', $now_timestamp + 1 );
		$week_start          = gmdate( 'Y-m-d H:i:s', $now_timestamp - 7 * DAY_IN_SECONDS );
		$previous_week_start = gmdate( 'Y-m-d H:i:s', $now_timestamp - 14 * DAY_IN_SECONDS );

		$summary_cards = array(
			array(
				'label'    => __( 'Conversations', 'wp-ai-chatbot' ),
				'current'  => $this->logs->count_conversations_between( $week_start, $now_exclusive ),
				'previous' => $this->logs->count_conversations_between( $previous_week_start, $week_start ),
			),
			array(
				'label'    => __( 'Added to cart', 'wp-ai-chatbot' ),
				'current'  => WPAIC_Events::count_between( WPAIC_Events::PRODUCT_ADDED_TO_CART, $week_start, $now_exclusive ),
				'previous' => WPAIC_Events::count_between( WPAIC_Events::PRODUCT_ADDED_TO_CART, $previous_week_start, $week_start ),
			),
			array(
				'label'    => __( 'Checkouts started', 'wp-ai-chatbot' ),
				'current'  => WPAIC_Events::count_between( WPAIC_Events::CHECKOUT_STARTED, $week_start, $now_exclusive ),
				'previous' => WPAIC_Events::count_between( WPAIC_Events::CHECKOUT_STARTED, $previous_week_start, $week_start ),
			),
			array(
				'label'    => __( 'Handoffs', 'wp-ai-chatbot' ),
				'current'  => WPAIC_Events::count_between( WPAIC_Events::HANDOFF_CREATED, $week_start, $now_exclusive ),
				'previous' => WPAIC_Events::count_between( WPAIC_Events::HANDOFF_CREATED, $previous_week_start, $week_start ),
			),
		);

		$zero_result_searches = WPAIC_Events::get_zero_result_searches( 10, 30 );
		?>
		<div class="wpaic-admin-wrap" style="margin-left: -20px;">
			<div class="bg-surface border-b border-line">
				<div class="max-w-[960px] mx-auto px-8 pt-5 pb-5">
					<h1 class="text-[22px] font-semibold tracking-tight text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'Chat Logs', 'wp-ai-chatbot' ); ?></h1>
					<p class="text-muted text-sm mt-1"><?php esc_html_e( 'Conversation history from all visitors.', 'wp-ai-chatbot' ); ?></p>
				</div>
			</div>

			<div class="max-w-[960px] mx-auto px-8 py-6">
				<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
					<?php foreach ( $summary_cards as $card ) : ?>
						<?php
						$delta = $card['current'] - $card['previous'];
						if ( $delta > 0 ) {
							$trend_class = 'text-success-ink';
							$trend_label = sprintf(
								/* translators: %d: increase vs previous week */
								__( '+%d vs previous week', 'wp-ai-chatbot' ),
								$delta
							);
						} elseif ( $delta < 0 ) {
							$trend_class = 'text-danger';
							$trend_label = sprintf(
								/* translators: %d: decrease vs previous week (negative number) */
								__( '%d vs previous week', 'wp-ai-chatbot' ),
								$delta
							);
						} else {
							$trend_class = 'text-muted';
							$trend_label = __( 'Same as previous week', 'wp-ai-chatbot' );
						}
						?>
						<div class="wpaic-card p-4">
							<div class="text-[12px] text-muted font-medium uppercase tracking-wider"><?php echo esc_html( $card['label'] ); ?></div>
							<div class="text-[24px] font-semibold text-ink mt-1" style="font-family: var(--font-display);"><?php echo esc_html( number_format_i18n( $card['current'] ) ); ?></div>
							<div class="text-[12px] mt-0.5 <?php echo esc_attr( $trend_class ); ?>"><?php echo esc_html( $trend_label ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( ! empty( $zero_result_searches ) ) : ?>
					<div class="wpaic-card mb-5">
						<div class="wpaic-card-header">
							<div class="min-w-0 flex-1">
								<h3 class="text-[15px] font-semibold"><?php esc_html_e( 'Missed searches', 'wp-ai-chatbot' ); ?></h3>
								<p class="text-muted text-[13px] mt-0.5"><?php esc_html_e( 'Shopper searches from the last 30 days that returned no results. Add an FAQ so the bot has an answer next time.', 'wp-ai-chatbot' ); ?></p>
							</div>
						</div>
						<div class="wpaic-card-body">
							<?php foreach ( $zero_result_searches as $missed_search ) : ?>
								<?php
								$add_faq_url = add_query_arg(
									array(
										'page' => 'wp-ai-chatbot',
										'tab'  => 'knowledge',
										'wpaic_faq_question' => $missed_search['query'],
									),
									admin_url( 'admin.php' )
								);
								?>
								<div class="flex items-center gap-3 py-2 border-b border-line-2 last:border-0">
									<span class="flex-1 min-w-0 text-[13px] text-ink truncate">&ldquo;<?php echo esc_html( $missed_search['query'] ); ?>&rdquo;</span>
									<span class="wpaic-tag wpaic-tag-neutral"><?php echo esc_html( sprintf( /* translators: %d: number of times searched */ _n( '%d search', '%d searches', $missed_search['count'], 'wp-ai-chatbot' ), $missed_search['count'] ) ); ?></span>
									<a href="<?php echo esc_url( $add_faq_url ); ?>" class="wpaic-btn wpaic-btn-sm no-underline"><?php esc_html_e( 'Add as FAQ', 'wp-ai-chatbot' ); ?></a>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<form method="get" class="mb-4 flex items-end gap-2 flex-wrap">
					<input type="hidden" name="page" value="wp-ai-chatbot-logs">
					<div class="flex flex-col gap-1">
						<label for="wpaic-logs-search" class="text-[12px] text-muted font-medium"><?php esc_html_e( 'Search messages', 'wp-ai-chatbot' ); ?></label>
						<input type="search" id="wpaic-logs-search" name="s" value="<?php echo esc_attr( $search ); ?>" class="wpaic-input !w-[220px]" placeholder="<?php esc_attr_e( 'e.g. refund', 'wp-ai-chatbot' ); ?>">
					</div>
					<div class="flex flex-col gap-1">
						<label for="wpaic-logs-date-from" class="text-[12px] text-muted font-medium"><?php esc_html_e( 'From', 'wp-ai-chatbot' ); ?></label>
						<input type="date" id="wpaic-logs-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" class="wpaic-input !w-auto">
					</div>
					<div class="flex flex-col gap-1">
						<label for="wpaic-logs-date-to" class="text-[12px] text-muted font-medium"><?php esc_html_e( 'To', 'wp-ai-chatbot' ); ?></label>
						<input type="date" id="wpaic-logs-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" class="wpaic-input !w-auto">
					</div>
					<button type="submit" class="wpaic-btn"><?php esc_html_e( 'Filter', 'wp-ai-chatbot' ); ?></button>
					<?php if ( $has_filters ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-ai-chatbot-logs' ) ); ?>" class="wpaic-btn no-underline"><?php esc_html_e( 'Reset', 'wp-ai-chatbot' ); ?></a>
					<?php endif; ?>
				</form>
				<?php if ( empty( $conversations ) ) : ?>
					<div class="wpaic-card p-8 text-center">
						<div class="w-12 h-12 rounded-lg bg-canvas grid place-items-center mx-auto mb-3 text-muted">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						</div>
						<p class="text-muted"><?php esc_html_e( 'No conversations found.', 'wp-ai-chatbot' ); ?></p>
					</div>
				<?php else : ?>
					<div class="wpaic-card overflow-hidden">
						<table class="min-w-full">
							<thead>
								<tr class="border-b border-line-2 bg-surface-2">
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Messages', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'First message', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Started', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Last Activity', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Actions', 'wp-ai-chatbot' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $conversations as $conv ) : ?>
									<tr class="border-b border-line-2 last:border-0 hover:bg-surface-2 transition-colors">
										<td class="px-5 py-3.5 text-[13px]">
											<span class="wpaic-tag wpaic-tag-neutral"><?php echo esc_html( (string) $conv->message_count ); ?></span>
										</td>
										<td class="px-5 py-3.5 text-[13px] text-ink max-w-[320px]">
											<?php
											$first_user_message = trim( (string) ( $conv->first_user_message ?? '' ) );
											if ( mb_strlen( $first_user_message ) > 80 ) {
												$first_user_message = mb_substr( $first_user_message, 0, 80 ) . '…';
											}
											echo esc_html( '' !== $first_user_message ? $first_user_message : '—' );
											?>
										</td>
										<td class="px-5 py-3.5 text-[13px] text-ink"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conv->created_at ) ) ); ?></td>
										<td class="px-5 py-3.5 text-[13px] text-muted"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conv->updated_at ) ) ); ?></td>
										<td class="px-5 py-3.5 text-sm">
											<div class="flex items-center gap-2">
												<button type="button" class="wpaic-btn wpaic-btn-sm wpaic-view-conversation" data-id="<?php echo esc_attr( (string) $conv->id ); ?>"><?php esc_html_e( 'View', 'wp-ai-chatbot' ); ?></button>
												<button type="button" class="wpaic-btn wpaic-btn-sm wpaic-btn-danger wpaic-delete-conversation" data-id="<?php echo esc_attr( (string) $conv->id ); ?>"><?php esc_html_e( 'Delete', 'wp-ai-chatbot' ); ?></button>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="mt-4 flex justify-center">
						<?php
						$pagination_args = array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $page,
							'total'     => $total_pages,
							'prev_text' => '&laquo; ' . __( 'Previous', 'wp-ai-chatbot' ),
							'next_text' => __( 'Next', 'wp-ai-chatbot' ) . ' &raquo;',
						);
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links output is safe
						echo paginate_links( $pagination_args );
						?>
					</div>
				<?php endif; ?>
			</div>

			<div id="wpaic-conversation-modal" style="display:none;">
				<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;" class="wpaic-modal-backdrop"></div>
				<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:600px;max-width:90%;max-height:80vh;border-radius:14px;z-index:100001;display:flex;flex-direction:column;overflow:hidden;">
					<div class="flex items-center justify-between px-5 py-4 border-b border-line">
						<h2 class="text-[16px] font-semibold m-0" style="font-family: var(--font-display);"><?php esc_html_e( 'Conversation', 'wp-ai-chatbot' ); ?></h2>
						<button type="button" class="wpaic-modal-close w-6 h-6 rounded-md grid place-items-center text-muted hover:bg-canvas bg-transparent border-0 cursor-pointer">&times;</button>
					</div>
					<div class="wpaic-modal-body p-5 overflow-y-auto flex-1"></div>
				</div>
			</div>

			<style>
				.wpaic-message { margin-bottom: 12px; padding: 10px 14px; border-radius: 10px; font-size: 13px; line-height: 1.5; }
				.wpaic-message-user { background: var(--color-ink); color: #fff; margin-left: 40px; }
				.wpaic-message-assistant { background: var(--color-canvas); color: var(--color-ink); margin-right: 40px; }
				.wpaic-message-role { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 4px; font-weight: 600; letter-spacing: 0.05em; }
				.wpaic-message-time { font-size: 11px; opacity: 0.5; margin-top: 5px; }
				.wpaic-message-content p { margin: 0 0 8px; }
				.wpaic-message-content p:last-child { margin-bottom: 0; }
				.wpaic-message-content ul, .wpaic-message-content ol { margin: 4px 0 8px; padding-left: 18px; }
				.wpaic-message-content ul { list-style: disc; }
				.wpaic-message-content ol { list-style: decimal; }
				.wpaic-message-content code { background: rgba(0,0,0,0.07); padding: 1px 4px; border-radius: 4px; font-size: 12px; }
				.wpaic-message-content a { color: inherit; text-decoration: underline; }
				.wpaic-event-chip { display: block; width: fit-content; margin: 0 auto 10px; padding: 3px 10px; border-radius: 999px; background: #f0f0f1; color: #646970; font-size: 11px; line-height: 1.4; }
			</style>
		</div>
		<?php
	}

	public function ajax_get_conversation(): void {
		check_ajax_referer( 'wpaic_admin', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		if ( 0 === $conversation_id ) {
			wp_send_json_error();
		}

		wp_send_json_success( $this->transcript_renderer->get_transcript_items( $conversation_id ) );
	}

	public function ajax_delete_conversation(): void {
		check_ajax_referer( 'wpaic_admin', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? (int) $_POST['conversation_id'] : 0;
		if ( 0 === $conversation_id ) {
			wp_send_json_error();
		}

		$result = $this->logs->delete_conversation( $conversation_id );
		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	public function ajax_update_search_indexes(): void {
		check_ajax_referer( 'wpaic_update_search_indexes', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-ai-chatbot' ) ) );
		}

		$existing_settings = get_option( 'wpaic_settings', array() );
		$existing_settings = is_array( $existing_settings ) ? $existing_settings : array();

		$search_input = $_POST;
		if ( ! array_key_exists( 'product_index_enabled', $search_input ) ) {
			$search_input['product_index_enabled'] = '';
		}

		$sanitized_search_settings = $this->sanitize_search_index_settings( $search_input, true );
		update_option( 'wpaic_settings', array_merge( $existing_settings, $sanitized_search_settings ) );

		$search_index          = new WPAIC_Search_Index();
		$content_index         = new WPAIC_Content_Index();
		$product_index_result  = $search_index->build_index();
		$content_index_result  = $content_index->build_index();

		if ( ! $product_index_result || ! $content_index_result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update search indexes. Check file permissions.', 'wp-ai-chatbot' ) ) );
		}

		$product_status = $search_index->get_index_status();
		$content_status = $content_index->get_index_status();

		wp_send_json_success(
			array(
				'message' => __( 'Search indexes updated successfully.', 'wp-ai-chatbot' ),
				'product' => array(
					'enabled'            => $search_index->is_enabled(),
					'exists'             => $product_status['exists'],
					'count'              => $product_status['product_count'],
					'last_updated'       => $product_status['last_updated'],
					'last_updated_label' => $this->format_index_updated_at( $product_status['last_updated'] ),
				),
				'content' => array(
					'enabled'            => ! empty( $content_status['indexed_post_types'] ),
					'exists'             => $content_status['exists'],
					'count'              => $content_status['post_count'],
					'last_updated'       => $content_status['last_updated'],
					'last_updated_label' => $this->format_index_updated_at( $content_status['last_updated'] ),
					'post_types'         => $content_status['indexed_post_types'],
				),
			)
		);
	}

	/**
	 * Render the Support Requests page.
	 */
	public function render_support_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'wpaic_support_requests';
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$requests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_pages = (int) ceil( $total / $per_page );

		$statuses = array(
			'new'       => __( 'New', 'wp-ai-chatbot' ),
			'contacted' => __( 'Contacted', 'wp-ai-chatbot' ),
			'resolved'  => __( 'Resolved', 'wp-ai-chatbot' ),
		);

		$status_colors = array(
			'new'       => 'bg-warn-soft text-warn-ink',
			'contacted' => 'bg-accent-soft text-accent-ink',
			'resolved'  => 'bg-success-soft text-success-ink',
		);
		?>
		<div class="wpaic-admin-wrap" style="margin-left: -20px;">
			<div class="bg-surface border-b border-line">
				<div class="max-w-[960px] mx-auto px-8 pt-5 pb-5">
					<h1 class="text-[22px] font-semibold tracking-tight text-ink" style="font-family: var(--font-display);"><?php esc_html_e( 'Support Requests', 'wp-ai-chatbot' ); ?></h1>
					<p class="text-muted text-sm mt-1"><?php esc_html_e( 'Customer requests to speak with a human agent.', 'wp-ai-chatbot' ); ?></p>
				</div>
			</div>

			<div class="max-w-[960px] mx-auto px-8 py-6">
				<?php if ( empty( $requests ) ) : ?>
					<div class="wpaic-card p-8 text-center">
						<div class="w-12 h-12 rounded-lg bg-canvas grid place-items-center mx-auto mb-3 text-muted">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						</div>
						<p class="text-muted"><?php esc_html_e( 'No support requests yet. Requests will appear here when customers use the handoff feature.', 'wp-ai-chatbot' ); ?></p>
					</div>
				<?php else : ?>
					<div class="wpaic-card overflow-hidden">
						<table class="min-w-full">
							<thead>
								<tr class="border-b border-line-2 bg-surface-2">
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Date', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Customer', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Email', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Status', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-5 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider"><?php esc_html_e( 'Actions', 'wp-ai-chatbot' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $requests as $req ) : ?>
									<tr class="border-b border-line-2 last:border-0 hover:bg-surface-2 transition-colors">
										<td class="px-5 py-3.5 whitespace-nowrap text-[13px] text-ink">
											<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $req->created_at ) ) ); ?>
										</td>
										<td class="px-5 py-3.5 whitespace-nowrap text-[13px] font-medium text-ink">
											<?php echo esc_html( $req->customer_name ); ?>
										</td>
										<td class="px-5 py-3.5 whitespace-nowrap text-[13px] text-muted">
											<?php echo esc_html( $req->customer_email ); ?>
										</td>
										<td class="px-5 py-3.5 whitespace-nowrap">
											<select class="wpaic-support-status-select wpaic-tag text-[11.5px] border-0 cursor-pointer <?php echo esc_attr( $status_colors[ $req->status ] ?? 'wpaic-tag-neutral' ); ?>"
													data-id="<?php echo esc_attr( (string) $req->id ); ?>">
												<?php foreach ( $statuses as $status_val => $status_label ) : ?>
													<option value="<?php echo esc_attr( $status_val ); ?>" <?php selected( $req->status, $status_val ); ?>><?php echo esc_html( $status_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td class="px-5 py-3.5 whitespace-nowrap text-sm">
											<div class="flex items-center gap-2">
												<button type="button" class="wpaic-view-transcript wpaic-btn wpaic-btn-sm" data-id="<?php echo esc_attr( (string) $req->id ); ?>">
													<?php esc_html_e( 'View', 'wp-ai-chatbot' ); ?>
												</button>
												<a href="mailto:<?php echo esc_attr( $req->customer_email ); ?>" class="wpaic-btn wpaic-btn-sm no-underline">
													<?php esc_html_e( 'Email', 'wp-ai-chatbot' ); ?>
												</a>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="mt-4 flex justify-center">
							<?php
							$pagination_args = array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $page,
								'total'     => $total_pages,
								'prev_text' => '&laquo; ' . __( 'Previous', 'wp-ai-chatbot' ),
								'next_text' => __( 'Next', 'wp-ai-chatbot' ) . ' &raquo;',
							);
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links output is safe
							echo paginate_links( $pagination_args );
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<div id="wpaic-transcript-modal" style="display:none;">
			<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;" class="wpaic-modal-backdrop"></div>
			<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;width:600px;max-width:90%;max-height:80vh;border-radius:14px;z-index:100001;display:flex;flex-direction:column;overflow:hidden;">
				<div class="flex items-center justify-between px-5 py-4 border-b border-line">
					<h2 class="text-[16px] font-semibold m-0" style="font-family: var(--font-display);"><?php esc_html_e( 'Conversation Transcript', 'wp-ai-chatbot' ); ?></h2>
					<button type="button" class="wpaic-modal-close w-6 h-6 rounded-md grid place-items-center text-muted hover:bg-canvas bg-transparent border-0 cursor-pointer">&times;</button>
				</div>
				<div class="wpaic-modal-body p-5 overflow-y-auto flex-1"></div>
			</div>
		</div>

		<style>
			.wpaic-transcript-message { margin-bottom: 12px; padding: 10px 14px; border-radius: 10px; font-size: 13px; line-height: 1.5; }
			.wpaic-transcript-user { background: var(--color-ink); color: #fff; margin-left: 40px; }
			.wpaic-transcript-assistant { background: var(--color-canvas); color: var(--color-ink); margin-right: 40px; }
			.wpaic-transcript-role { font-size: 11px; text-transform: uppercase; opacity: 0.6; margin-bottom: 4px; font-weight: 600; letter-spacing: 0.05em; }
			.wpaic-transcript-content p { margin: 0 0 8px; }
			.wpaic-transcript-content p:last-child { margin-bottom: 0; }
			.wpaic-transcript-content ul, .wpaic-transcript-content ol { margin: 4px 0 8px; padding-left: 18px; }
			.wpaic-transcript-content ul { list-style: disc; }
			.wpaic-transcript-content ol { list-style: decimal; }
			.wpaic-transcript-content code { background: rgba(0,0,0,0.07); padding: 1px 4px; border-radius: 4px; font-size: 12px; }
			.wpaic-transcript-content a { color: inherit; text-decoration: underline; }
			.wpaic-event-chip { display: block; width: fit-content; margin: 0 auto 10px; padding: 3px 10px; border-radius: 999px; background: #f0f0f1; color: #646970; font-size: 11px; line-height: 1.4; }
			.wpaic-support-status-select:focus { outline: none; }
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('.wpaic-view-transcript').on('click', function() {
				var id = $(this).data('id');
				$('#wpaic-transcript-modal').show();
				$('#wpaic-transcript-modal .wpaic-modal-body').html('<p class="text-muted"><?php echo esc_js( __( 'Loading...', 'wp-ai-chatbot' ) ); ?></p>');

				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'wpaic_get_support_transcript', request_id: id, _wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_support' ) ); ?>' },
					success: function(response) {
						if (response.success && (response.data.conversation || response.data.transcript)) {
							var html = '';
							if (response.data.extra_fields) {
								var fieldLabels = {phone_number:'Phone',company:'Company',order_number:'Order Number',request_message:'Message'};
								html += '<div class="mb-4 p-3 bg-accent-soft border border-line rounded-[10px]">';
								$.each(response.data.extra_fields, function(k,v) {
									html += '<div class="mb-1 text-[13px]"><strong>' + $('<span>').text(fieldLabels[k]||k).html() + ':</strong> ' + $('<span>').text(v).html() + '</div>';
								});
								html += '</div>';
							}
							if (response.data.conversation) {
								response.data.conversation.forEach(function(item) {
									if (item.type === 'event') {
										html += '<div class="wpaic-event-chip">' + $('<div>').text(item.label).html() + '</div>';
										return;
									}
									html += '<div class="wpaic-transcript-message wpaic-transcript-' + item.role + '">';
									html += '<div class="wpaic-transcript-role">' + item.role + '</div>';
									// content_html is server-rendered, escaped markdown (assistant replies).
									if (item.content_html) {
										html += '<div class="wpaic-transcript-content">' + item.content_html + '</div></div>';
									} else {
										html += '<div>' + $('<div>').text(item.content).html().replace(/\n/g, '<br>') + '</div></div>';
									}
								});
							} else {
								var lines = response.data.transcript.split('\n');
								var currentRole = '', currentContent = '';
								function flush() {
									if (!currentContent) return;
									html += '<div class="wpaic-transcript-message' + (currentRole ? ' wpaic-transcript-' + currentRole : '') + '">';
									// Summary-only/legacy requests have no role-prefixed lines; skip the empty chip.
									if (currentRole) {
										html += '<div class="wpaic-transcript-role">' + currentRole + '</div>';
									}
									html += '<div>' + $('<div>').text(currentContent.trim()).html().replace(/\n/g, '<br>') + '</div></div>';
								}
								lines.forEach(function(line) {
									if (line.startsWith('User: ')) { flush(); currentRole = 'user'; currentContent = line.substring(6); }
									else if (line.startsWith('Assistant: ')) { flush(); currentRole = 'assistant'; currentContent = line.substring(11); }
									else { currentContent += '\n' + line; }
								});
								flush();
							}
							$('#wpaic-transcript-modal .wpaic-modal-body').html(html || '<p class="text-muted"><?php echo esc_js( __( 'No transcript available.', 'wp-ai-chatbot' ) ); ?></p>');
						} else {
							$('#wpaic-transcript-modal .wpaic-modal-body').html('<p style="color:var(--color-danger)"><?php echo esc_js( __( 'Error loading transcript.', 'wp-ai-chatbot' ) ); ?></p>');
						}
					}
				});
			});

			$('.wpaic-support-status-select').on('change', function() {
				var $select = $(this), id = $select.data('id'), status = $select.val();
				var colorMap = { 'new': 'bg-warn-soft text-warn-ink', 'contacted': 'bg-accent-soft text-accent-ink', 'resolved': 'bg-success-soft text-success-ink' };
				$.ajax({
					url: ajaxurl, type: 'POST',
					data: { action: 'wpaic_update_support_status', request_id: id, status: status, _wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_support' ) ); ?>' },
					success: function(response) {
						if (response.success) {
							$select.removeClass('bg-warn-soft text-warn-ink bg-accent-soft text-accent-ink bg-success-soft text-success-ink');
							$select.addClass(colorMap[status] || 'wpaic-tag-neutral');
						}
					}
				});
			});

			$('.wpaic-modal-close, .wpaic-modal-backdrop').on('click', function() { $('#wpaic-transcript-modal').hide(); });
		});
		</script>
		<?php
	}

	public function ajax_update_support_status(): void {
		check_ajax_referer( 'wpaic_support', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$request_id = isset( $_POST['request_id'] ) ? (int) $_POST['request_id'] : 0;
		$status     = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( 0 === $request_id || ! in_array( $status, array( 'new', 'contacted', 'resolved' ), true ) ) {
			wp_send_json_error();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_support_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $request_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	public function ajax_get_support_transcript(): void {
		check_ajax_referer( 'wpaic_support', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$request_id = isset( $_POST['request_id'] ) ? (int) $_POST['request_id'] : 0;
		if ( 0 === $request_id ) {
			wp_send_json_error();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_support_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$request = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT transcript, extra_fields, conversation_id FROM $table WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$request_id
			)
		);

		if ( $request ) {
			$data = array( 'transcript' => $request->transcript );
			if ( ! empty( $request->extra_fields ) ) {
				$data['extra_fields'] = json_decode( $request->extra_fields, true );
			}
			if ( ! empty( $request->conversation_id ) ) {
				$conversation_items = $this->transcript_renderer->get_transcript_items( (int) $request->conversation_id );
				if ( ! empty( $conversation_items ) ) {
					$data['conversation'] = $conversation_items;
				}
			}
			wp_send_json_success( $data );
		} else {
			wp_send_json_error();
		}
	}


	public function ajax_upload_csv(): void {
		check_ajax_referer( 'wpaic_upload_csv', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-ai-chatbot' ) ) );
		}

		$name        = isset( $_POST['source_name'] ) ? sanitize_key( wp_unslash( $_POST['source_name'] ) ) : '';
		$label       = isset( $_POST['source_label'] ) ? sanitize_text_field( wp_unslash( $_POST['source_label'] ) ) : '';
		$description = isset( $_POST['source_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['source_description'] ) ) : '';

		if ( empty( $name ) || empty( $label ) || empty( $description ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'wp-ai-chatbot' ) ) );
		}

		if ( ! preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Name must contain only lowercase letters, numbers, underscores, and hyphens.', 'wp-ai-chatbot' ) ) );
		}

		if ( ! isset( $_FILES['csv_file'] ) || ! isset( $_FILES['csv_file']['tmp_name'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a CSV file to upload.', 'wp-ai-chatbot' ) ) );
		}

		if ( ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'wp-ai-chatbot' ) ) );
		}

		$file = $_FILES['csv_file'];

		if ( $file['size'] > 5 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'File too large. Maximum 5MB allowed.', 'wp-ai-chatbot' ) ) );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			wp_send_json_error( array( 'message' => __( 'Only CSV files are allowed.', 'wp-ai-chatbot' ) ) );
		}

		// Parse the entire file before touching the database so a bad upload cannot destroy existing data.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			wp_send_json_error( array( 'message' => __( 'Could not read CSV file.', 'wp-ai-chatbot' ) ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
		$headers = fgetcsv( $handle );
		if ( ! $headers || count( $headers ) < 1 ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_send_json_error( array( 'message' => __( 'CSV must have a header row.', 'wp-ai-chatbot' ) ) );
		}

		$headers = array_map( 'sanitize_text_field', $headers );

		$encoded_rows = array();
		$preview      = array();

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
			$row_data = array();
			foreach ( $headers as $i => $header ) {
				$row_data[ $header ] = isset( $row[ $i ] ) ? sanitize_text_field( $row[ $i ] ) : '';
			}

			$encoded_rows[] = wp_json_encode( $row_data );

			if ( count( $preview ) < 3 ) {
				$preview[] = $row_data;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$row_count = count( $encoded_rows );

		global $wpdb;
		$sources_table = $wpdb->prefix . 'wpaic_data_sources';
		$data_table    = $wpdb->prefix . 'wpaic_training_data';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $sources_table WHERE name = %s", $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $existing ) {
			// Stage new rows next to the old ones; old rows are removed only after staging succeeds.
			$source_id = (int) $existing;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$staging_boundary_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM $data_table WHERE source_id = %d", $source_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$sources_table,
				array(
					'name'        => $name,
					'label'       => $label,
					'description' => $description,
					'columns'     => wp_json_encode( $headers ),
					'row_count'   => 0,
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
			$source_id = (int) $wpdb->insert_id;

			if ( ! $source_id ) {
				wp_send_json_error( array( 'message' => __( 'Failed to create data source.', 'wp-ai-chatbot' ) ) );
			}

			$staging_boundary_id = 0;
		}

		if ( ! self::batch_insert_training_rows( $source_id, $encoded_rows ) ) {
			// Roll back the staged rows; pre-existing data is untouched.
			if ( $existing ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( $wpdb->prepare( "DELETE FROM $data_table WHERE source_id = %d AND id > %d", $source_id, $staging_boundary_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $data_table, array( 'source_id' => $source_id ), array( '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $sources_table, array( 'id' => $source_id ), array( '%d' ) );
			}
			wp_send_json_error( array( 'message' => __( 'Import failed while saving rows. Existing data was left unchanged.', 'wp-ai-chatbot' ) ) );
		}

		// Swap: the new rows are fully imported, so drop the old ones and update the source metadata.
		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "DELETE FROM $data_table WHERE source_id = %d AND id <= %d", $source_id, $staging_boundary_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$sources_table,
				array(
					'label'       => $label,
					'description' => $description,
					'columns'     => wp_json_encode( $headers ),
					'row_count'   => $row_count,
				),
				array( 'id' => $source_id ),
				array( '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $sources_table, array( 'row_count' => $row_count ), array( 'id' => $source_id ), array( '%d' ), array( '%d' ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of rows imported */
					__( '%d rows imported.', 'wp-ai-chatbot' ),
					$row_count
				),
				'preview' => $preview,
			)
		);
	}

	/**
	 * Insert training rows in batched multi-row INSERT statements.
	 *
	 * @param int                $source_id    Data source ID the rows belong to.
	 * @param array<int, string> $encoded_rows JSON-encoded row data.
	 * @return bool True when every batch succeeded, false on the first failure.
	 */
	private static function batch_insert_training_rows( int $source_id, array $encoded_rows ): bool {
		global $wpdb;
		$data_table = $wpdb->prefix . 'wpaic_training_data';

		foreach ( array_chunk( $encoded_rows, 100 ) as $batch ) {
			$placeholders = array();
			$values       = array();
			foreach ( $batch as $encoded_row ) {
				$placeholders[] = '(%d, %s)';
				$values[]       = $source_id;
				$values[]       = $encoded_row;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$wpdb->prepare( "INSERT INTO $data_table (source_id, row_data) VALUES " . implode( ', ', $placeholders ), ...$values )
			);

			if ( false === $result ) {
				return false;
			}
		}

		return true;
	}

	public function ajax_delete_data_source(): void {
		check_ajax_referer( 'wpaic_delete_source', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-ai-chatbot' ) ) );
		}

		$source_id = isset( $_POST['source_id'] ) ? (int) $_POST['source_id'] : 0;
		if ( 0 === $source_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid source ID.', 'wp-ai-chatbot' ) ) );
		}

		global $wpdb;
		$sources_table = $wpdb->prefix . 'wpaic_data_sources';
		$data_table    = $wpdb->prefix . 'wpaic_training_data';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $data_table, array( 'source_id' => $source_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $sources_table, array( 'id' => $source_id ), array( '%d' ) );

		if ( false !== $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Delete failed.', 'wp-ai-chatbot' ) ) );
		}
	}

	/**
	 * AJAX handler to save FAQ content.
	 */
	public function ajax_save_faqs(): void {
		check_ajax_referer( 'wpaic_save_faqs', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-ai-chatbot' ) ) );
		}

		$content = isset( $_POST['faq_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['faq_content'] ) ) : '';

		global $wpdb;
		$faqs_table = $wpdb->prefix . 'wpaic_faqs';

		// Ensure FAQs table exists (handles plugin updates without reactivation).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $faqs_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $table_exists && function_exists( 'wpaic_create_training_tables' ) ) {
			wpaic_create_training_tables();
		}

		if ( '' === trim( $content ) ) {
			// Clearing is intentional on an explicitly empty save.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "TRUNCATE TABLE $faqs_table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			wp_send_json_success( array( 'message' => __( 'FAQs cleared.', 'wp-ai-chatbot' ) ) );
		}

		// Parse before touching the database so a malformed paste cannot wipe existing FAQs.
		$parsed = self::parse_faq_content( $content );

		if ( empty( $parsed['pairs'] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No FAQs could be parsed, so the existing FAQs were left unchanged. Check the format: "Q: question" then "A: answer" on the next line, with a blank line between each pair.', 'wp-ai-chatbot' ) )
			);
		}

		// Replace existing FAQs only after parsing succeeded.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE $faqs_table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$count = 0;
		foreach ( $parsed['pairs'] as $pair ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$faqs_table,
				array(
					'question' => $pair['question'],
					'answer'   => $pair['answer'],
				),
				array( '%s', '%s' )
			);
			if ( false !== $result ) {
				++$count;
			}
		}

		$message = sprintf(
			/* translators: %d: number of FAQs saved */
			__( '%d FAQ(s) saved.', 'wp-ai-chatbot' ),
			$count
		);

		if ( ! empty( $parsed['failed'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: quoted previews of entries that could not be parsed */
				__( 'Skipped entries that could not be parsed: %s. Each pair needs "Q:" and "A:" with no blank line between them.', 'wp-ai-chatbot' ),
				'"' . implode( '", "', $parsed['failed'] ) . '"'
			);
		}

		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * Parse Q:/A: formatted FAQ text into pairs without touching the database.
	 *
	 * Blocks are separated by blank lines. Blocks that do not match the
	 * "Q: ... / A: ..." format (e.g. a blank line between question and answer)
	 * are reported in 'failed' instead of being dropped silently.
	 *
	 * @param string $content Raw FAQ textarea content.
	 * @return array{pairs: array<int, array{question: string, answer: string}>, failed: array<int, string>}
	 */
	public static function parse_faq_content( string $content ): array {
		$pairs  = array();
		$failed = array();

		$blocks = preg_split( '/\n\s*\n/', $content );
		if ( false === $blocks ) {
			$blocks = array();
		}

		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( '' === $block ) {
				continue;
			}

			if ( preg_match( '/^Q:\s*(.+?)\s*\nA:\s*(.+)$/si', $block, $matches ) ) {
				$question = trim( $matches[1] );
				$answer   = trim( $matches[2] );

				if ( '' !== $question && '' !== $answer ) {
					$pairs[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
					continue;
				}
			}

			$first_line = trim( strtok( $block, "\n" ) );
			if ( mb_strlen( $first_line ) > 60 ) {
				$first_line = mb_substr( $first_line, 0, 57 ) . '...';
			}
			$failed[] = $first_line;
		}

		return array(
			'pairs'  => $pairs,
			'failed' => $failed,
		);
	}
}
