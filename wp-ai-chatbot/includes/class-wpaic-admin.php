<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Admin {
	private WPAIC_Logs $logs;

	public function __construct() {
		$this->logs = new WPAIC_Logs();
	}

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_wpaic_get_conversation', array( $this, 'ajax_get_conversation' ) );
		add_action( 'wp_ajax_wpaic_delete_conversation', array( $this, 'ajax_delete_conversation' ) );
		add_action( 'wp_ajax_wpaic_rebuild_index', array( $this, 'ajax_rebuild_index' ) );
		add_action( 'wp_ajax_wpaic_update_support_status', array( $this, 'ajax_update_support_status' ) );
		add_action( 'wp_ajax_wpaic_get_support_transcript', array( $this, 'ajax_get_support_transcript' ) );
		add_action( 'wp_ajax_wpaic_upload_csv', array( $this, 'ajax_upload_csv' ) );
		add_action( 'wp_ajax_wpaic_delete_data_source', array( $this, 'ajax_delete_data_source' ) );
		add_action( 'wp_ajax_wpaic_save_faqs', array( $this, 'ajax_save_faqs' ) );
	}

	/**
	 * Get available OpenAI models.
	 *
	 * @return array<string, string> Model IDs and labels.
	 */
	public function get_available_models(): array {
		return array(
			'gpt-4o-mini' => __( 'GPT-4o Mini (Recommended - Fast & Cheap)', 'wp-ai-chatbot' ),
			'gpt-4o'      => __( 'GPT-4o (Balanced)', 'wp-ai-chatbot' ),
			'gpt-5'       => __( 'GPT-5 (Best - Expensive)', 'wp-ai-chatbot' ),
		);
	}

	public function enqueue_admin_scripts( string $hook ): void {
		$allowed_hooks = array(
			'toplevel_page_wp-ai-chatbot',
			'ai-chatbot_page_wp-ai-chatbot-support',
		);
		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'jQuery(document).ready(function($){$(".wpaic-color-picker").wpColorPicker();});'
		);

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
			}
		}
	}

	public function add_admin_menu(): void {
		add_menu_page(
			__( 'AI Chatbot', 'wp-ai-chatbot' ),
			__( 'AI Chatbot', 'wp-ai-chatbot' ),
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
			__( 'Support', 'wp-ai-chatbot' ),
			'manage_options',
			'wp-ai-chatbot-support',
			array( $this, 'render_support_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'wpaic_settings_group',
			'wpaic_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'wpaic_main_section',
			__( 'Chatbot Settings', 'wp-ai-chatbot' ),
			'__return_null',
			'wp-ai-chatbot'
		);

		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'wp-ai-chatbot' ),
			array( $this, 'render_api_key_field' ),
			'wp-ai-chatbot',
			'wpaic_main_section'
		);

		add_settings_field(
			'model',
			__( 'Model', 'wp-ai-chatbot' ),
			array( $this, 'render_model_field' ),
			'wp-ai-chatbot',
			'wpaic_main_section'
		);

		add_settings_field(
			'greeting_message',
			__( 'Greeting Message', 'wp-ai-chatbot' ),
			array( $this, 'render_greeting_field' ),
			'wp-ai-chatbot',
			'wpaic_main_section'
		);

		add_settings_field(
			'enabled',
			__( 'Enable Chatbot', 'wp-ai-chatbot' ),
			array( $this, 'render_enabled_field' ),
			'wp-ai-chatbot',
			'wpaic_main_section'
		);

		add_settings_field(
			'system_prompt',
			__( 'System Prompt', 'wp-ai-chatbot' ),
			array( $this, 'render_system_prompt_field' ),
			'wp-ai-chatbot',
			'wpaic_main_section'
		);

		add_settings_field(
			'theme_color',
			__( 'Theme Color', 'wp-ai-chatbot' ),
			array( $this, 'render_theme_color_field' ),
			'wp-ai-chatbot',
			'wpaic_main_section'
		);

		add_settings_field(
			'language',
			__( 'Language', 'wp-ai-chatbot' ),
			array( $this, 'render_language_field' ),
			'wp-ai-chatbot',
			'wpaic_main_section'
		);

		add_settings_section(
			'wpaic_proactive_section',
			__( 'Proactive Engagement', 'wp-ai-chatbot' ),
			'__return_null',
			'wp-ai-chatbot'
		);

		add_settings_field(
			'proactive_enabled',
			__( 'Enable Proactive Popup', 'wp-ai-chatbot' ),
			array( $this, 'render_proactive_enabled_field' ),
			'wp-ai-chatbot',
			'wpaic_proactive_section'
		);

		add_settings_field(
			'proactive_delay',
			__( 'Trigger Delay (seconds)', 'wp-ai-chatbot' ),
			array( $this, 'render_proactive_delay_field' ),
			'wp-ai-chatbot',
			'wpaic_proactive_section'
		);

		add_settings_field(
			'proactive_message',
			__( 'Proactive Message', 'wp-ai-chatbot' ),
			array( $this, 'render_proactive_message_field' ),
			'wp-ai-chatbot',
			'wpaic_proactive_section'
		);

		add_settings_field(
			'proactive_pages',
			__( 'Show On Pages', 'wp-ai-chatbot' ),
			array( $this, 'render_proactive_pages_field' ),
			'wp-ai-chatbot',
			'wpaic_proactive_section'
		);

		add_settings_section(
			'wpaic_search_section',
			__( 'Search Index', 'wp-ai-chatbot' ),
			array( $this, 'render_search_section_description' ),
			'wp-ai-chatbot'
		);

		add_settings_field(
			'search_index_status',
			__( 'Index Status', 'wp-ai-chatbot' ),
			array( $this, 'render_search_index_status_field' ),
			'wp-ai-chatbot',
			'wpaic_search_section'
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
			'general'    => array( 'enabled', 'greeting_message', 'language' ),
			'api'        => array( 'openai_api_key', 'model' ),
			'appearance' => array( 'chatbot_name', 'chatbot_logo', 'theme_color', 'system_prompt' ),
			'engagement' => array( 'handoff_enabled', 'proactive_enabled', 'proactive_delay', 'proactive_message', 'proactive_pages' ),
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
		$sanitized['openai_api_key']   = sanitize_text_field( $merged['openai_api_key'] ?? '' );
		$sanitized['model']            = sanitize_text_field( $merged['model'] ?? 'gpt-4o-mini' );
		$sanitized['greeting_message'] = sanitize_textarea_field( $merged['greeting_message'] ?? '' );
		$sanitized['enabled']          = ! empty( $merged['enabled'] );
		$sanitized['system_prompt']    = sanitize_textarea_field( $merged['system_prompt'] ?? '' );
		$theme_color                   = sanitize_hex_color( $merged['theme_color'] ?? '#0073aa' );
		$sanitized['theme_color']      = $theme_color ? $theme_color : '#0073aa';
		$sanitized['language']         = sanitize_text_field( $merged['language'] ?? 'auto' );

		$sanitized['proactive_enabled'] = ! empty( $merged['proactive_enabled'] );
		$sanitized['proactive_delay']   = max( 1, (int) ( $merged['proactive_delay'] ?? 10 ) );
		$sanitized['proactive_message'] = sanitize_textarea_field( $merged['proactive_message'] ?? '' );
		$sanitized['proactive_pages']   = sanitize_text_field( $merged['proactive_pages'] ?? 'all' );

		$sanitized['chatbot_name'] = sanitize_text_field( $merged['chatbot_name'] ?? '' );
		$sanitized['chatbot_logo'] = esc_url_raw( $merged['chatbot_logo'] ?? '' );

		$sanitized['handoff_enabled'] = ! empty( $merged['handoff_enabled'] );

		return $sanitized;
	}

	public function render_api_key_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['openai_api_key'] ?? '' ) : '';
		echo '<input type="password" name="wpaic_settings[openai_api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	public function render_model_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['model'] ?? 'gpt-4o-mini' ) : 'gpt-4o-mini';
		$models   = $this->get_available_models();
		echo '<select name="wpaic_settings[model]">';
		foreach ( $models as $model_id => $label ) {
			echo '<option value="' . esc_attr( $model_id ) . '" ' . selected( $value, $model_id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the AI model. GPT-4o Mini offers the best balance of speed, cost, and quality.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_greeting_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['greeting_message'] ?? 'Hello! How can I help you today?' ) : 'Hello! How can I help you today?';
		echo '<textarea name="wpaic_settings[greeting_message]" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
	}

	public function render_enabled_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$checked  = is_array( $settings ) && ! empty( $settings['enabled'] );
		echo '<input type="checkbox" name="wpaic_settings[enabled]" value="1" ' . checked( $checked, true, false ) . ' />';
	}

	public function render_system_prompt_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['system_prompt'] ?? '' ) : '';
		echo '<textarea name="wpaic_settings[system_prompt]" rows="5" class="large-text" placeholder="' . esc_attr__( 'Leave empty for default prompt', 'wp-ai-chatbot' ) . '">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Customize the system prompt to define the chatbot\'s personality and behavior. Leave empty to use the default.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_theme_color_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['theme_color'] ?? '#0073aa' ) : '#0073aa';
		echo '<input type="text" name="wpaic_settings[theme_color]" value="' . esc_attr( $value ) . '" class="wpaic-color-picker" data-default-color="#0073aa" />';
		echo '<p class="description">' . esc_html__( 'Choose the primary color for the chatbot header, buttons, and accents.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_language_field(): void {
		$settings  = get_option( 'wpaic_settings', array() );
		$value     = is_array( $settings ) ? ( $settings['language'] ?? 'auto' ) : 'auto';
		$languages = array(
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
		echo '<select name="wpaic_settings[language]">';
		foreach ( $languages as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '" ' . selected( $value, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Language for chatbot responses. Auto-detect will respond in the language the user writes in.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_proactive_enabled_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$checked  = is_array( $settings ) && ! empty( $settings['proactive_enabled'] );
		echo '<input type="checkbox" name="wpaic_settings[proactive_enabled]" value="1" ' . checked( $checked, true, false ) . ' />';
		echo '<p class="description">' . esc_html__( 'Auto-open chat widget after visitor is on page for the specified delay.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_proactive_delay_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['proactive_delay'] ?? 10 ) : 10;
		echo '<input type="number" name="wpaic_settings[proactive_delay]" value="' . esc_attr( (string) $value ) . '" min="1" max="300" class="small-text" /> ';
		echo '<span class="description">' . esc_html__( 'seconds', 'wp-ai-chatbot' ) . '</span>';
	}

	public function render_proactive_message_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['proactive_message'] ?? '' ) : '';
		echo '<textarea name="wpaic_settings[proactive_message]" rows="2" class="large-text" placeholder="' . esc_attr__( 'Hi! Looking for something specific? I can help you find the perfect product.', 'wp-ai-chatbot' ) . '">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Message shown when chat opens proactively. Leave empty to use the greeting message.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_proactive_pages_field(): void {
		$settings = get_option( 'wpaic_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['proactive_pages'] ?? 'all' ) : 'all';
		$options  = array(
			'all'      => __( 'All pages', 'wp-ai-chatbot' ),
			'shop'     => __( 'Shop pages only', 'wp-ai-chatbot' ),
			'product'  => __( 'Product pages only', 'wp-ai-chatbot' ),
			'homepage' => __( 'Homepage only', 'wp-ai-chatbot' ),
		);
		echo '<select name="wpaic_settings[proactive_pages]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Which pages should trigger the proactive popup.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_search_section_description(): void {
		echo '<p>' . esc_html__( 'The search index enables fast fuzzy search across product titles, descriptions, SKUs, categories, and attributes.', 'wp-ai-chatbot' ) . '</p>';
	}

	public function render_search_index_status_field(): void {
		$search_index = new WPAIC_Search_Index();
		$status       = $search_index->get_index_status();
		$exists       = $status['exists'];
		$count        = $status['product_count'];
		$updated      = $status['last_updated'];

		if ( $exists ) {
			echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
			printf(
				/* translators: 1: product count, 2: last updated date */
				esc_html__( '%1$d products indexed. Last updated: %2$s', 'wp-ai-chatbot' ),
				esc_html( (string) $count ),
				esc_html( $updated ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $updated ) ) : __( 'Unknown', 'wp-ai-chatbot' ) )
			);
		} else {
			echo '<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ';
			echo esc_html__( 'Index missing. Click Rebuild Index to create it.', 'wp-ai-chatbot' );
		}

		echo '<br><br>';
		echo '<button type="button" id="wpaic-rebuild-index" class="button button-secondary">';
		echo esc_html__( 'Rebuild Index', 'wp-ai-chatbot' );
		echo '</button>';
		echo '<span id="wpaic-rebuild-status" style="margin-left: 10px;"></span>';

		?>
		<script>
		jQuery(document).ready(function($) {
			$('#wpaic-rebuild-index').on('click', function() {
				var $btn = $(this);
				var $status = $('#wpaic-rebuild-status');
				$btn.prop('disabled', true);
				$status.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php echo esc_js( __( 'Rebuilding...', 'wp-ai-chatbot' ) ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpaic_rebuild_index',
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_rebuild_index' ) ); ?>'
					},
					success: function(response) {
						$btn.prop('disabled', false);
						if (response.success) {
							$status.html('<span style="color: #46b450;">&#10003; ' + response.data.message + '</span>');
							setTimeout(function() { location.reload(); }, 1500);
						} else {
							$status.html('<span style="color: #dc3232;">&#10007; ' + (response.data ? response.data.message : '<?php echo esc_js( __( 'Error rebuilding index.', 'wp-ai-chatbot' ) ); ?>') + '</span>');
						}
					},
					error: function() {
						$btn.prop('disabled', false);
						$status.html('<span style="color: #dc3232;">&#10007; <?php echo esc_js( __( 'Request failed.', 'wp-ai-chatbot' ) ); ?></span>');
					}
				});
			});
		});
		</script>
		<?php
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings   = get_option( 'wpaic_settings', array() );
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs       = array(
			'general'    => __( 'General', 'wp-ai-chatbot' ),
			'api'        => __( 'API', 'wp-ai-chatbot' ),
			'appearance' => __( 'Appearance', 'wp-ai-chatbot' ),
			'engagement' => __( 'Engagement', 'wp-ai-chatbot' ),
			'train'      => __( 'Train Bot', 'wp-ai-chatbot' ),
			'search'     => __( 'Search Index', 'wp-ai-chatbot' ),
		);
		?>
		<div class="wpaic-admin-wrap" style="margin-left: -20px;">
			<div class="bg-white shadow-sm border-b border-gray-200">
				<div class="max-w-5xl mx-auto px-6 py-6">
					<div class="flex items-center gap-3">
						<span class="dashicons dashicons-format-chat text-blue-600 text-2xl"></span>
						<h1 class="text-2xl font-semibold text-gray-900"><?php esc_html_e( 'AI Chatbot Settings', 'wp-ai-chatbot' ); ?></h1>
					</div>
				</div>
			</div>

			<div class="max-w-5xl mx-auto px-6 py-4">
				<div class="bg-white rounded-lg border border-gray-200">
					<nav class="flex border-b border-gray-200 rounded-t-lg overflow-hidden">
						<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, admin_url( 'admin.php?page=wp-ai-chatbot' ) ) ); ?>"
								class="px-5 py-3 text-sm font-medium transition-colors <?php echo $active_tab === $tab_id ? 'text-blue-600 border-b-2 border-blue-600 -mb-px bg-blue-50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'; ?>">
								<?php echo esc_html( $tab_label ); ?>
							</a>
						<?php endforeach; ?>
					</nav>

					<form action="options.php" method="post" class="p-6">
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
						<?php elseif ( 'train' === $active_tab ) : ?>
							<?php $this->render_train_tab(); ?>
						<?php elseif ( 'search' === $active_tab ) : ?>
							<?php $this->render_search_tab(); ?>
						<?php endif; ?>

						<?php if ( 'search' !== $active_tab && 'train' !== $active_tab ) : ?>
							<div class="pt-4 mt-4 border-t border-gray-200">
								<button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
									<?php esc_html_e( 'Save Settings', 'wp-ai-chatbot' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the General settings tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_general_tab( array $settings ): void {
		$enabled   = ! empty( $settings['enabled'] );
		$greeting  = $settings['greeting_message'] ?? 'Hello! How can I help you today?';
		$language  = $settings['language'] ?? 'auto';
		$languages = array(
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
		?>
		<div class="space-y-4">
			<div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
				<div>
					<h3 class="text-sm font-medium text-gray-900"><?php esc_html_e( 'Enable Chatbot', 'wp-ai-chatbot' ); ?></h3>
					<p class="text-sm text-gray-500"><?php esc_html_e( 'Show the chat widget on your website', 'wp-ai-chatbot' ); ?></p>
				</div>
				<label class="relative inline-flex items-center cursor-pointer">
					<input type="checkbox" name="wpaic_settings[enabled]" value="1" <?php checked( $enabled ); ?> class="sr-only peer">
					<div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
				</label>
			</div>

			<div>
				<label for="wpaic_greeting" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'Greeting Message', 'wp-ai-chatbot' ); ?>
				</label>
				<textarea id="wpaic_greeting" name="wpaic_settings[greeting_message]" rows="3"
							class="max-w-md w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"><?php echo esc_textarea( $greeting ); ?></textarea>
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'First message shown when users open the chat.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div>
				<label for="wpaic_language" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'Response Language', 'wp-ai-chatbot' ); ?>
				</label>
				<select id="wpaic_language" name="wpaic_settings[language]"
						class="max-w-md w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
					<?php foreach ( $languages as $code => $label ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $language, $code ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'Language for chatbot responses. Auto-detect responds in the user\'s language.', 'wp-ai-chatbot' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the API settings tab.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_api_tab( array $settings ): void {
		$api_key = $settings['openai_api_key'] ?? '';
		$model   = $settings['model'] ?? 'gpt-4o-mini';
		$models  = $this->get_available_models();
		?>
		<div class="space-y-4">
			<div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
				<div class="flex">
					<span class="dashicons dashicons-info text-blue-500 mr-2"></span>
					<p class="text-sm text-blue-700">
						<?php
						printf(
							/* translators: %s: OpenAI platform URL */
							esc_html__( 'Get your API key from the %s.', 'wp-ai-chatbot' ),
							'<a href="https://platform.openai.com/api-keys" target="_blank" class="underline font-medium">OpenAI Platform</a>'
						);
						?>
					</p>
				</div>
			</div>

			<div>
				<label for="wpaic_api_key" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'OpenAI API Key', 'wp-ai-chatbot' ); ?>
				</label>
				<input type="password" id="wpaic_api_key" name="wpaic_settings[openai_api_key]" value="<?php echo esc_attr( $api_key ); ?>"
						class="max-w-md w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
						placeholder="sk-...">
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'Your API key is stored securely and never exposed to visitors.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div>
				<label for="wpaic_model" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'AI Model', 'wp-ai-chatbot' ); ?>
				</label>
				<select id="wpaic_model" name="wpaic_settings[model]"
						class="max-w-md w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
					<?php foreach ( $models as $model_id => $label ) : ?>
						<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $model, $model_id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'GPT-4o Mini is recommended for most use cases - best balance of speed, cost, and quality.', 'wp-ai-chatbot' ); ?></p>
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
		$theme_color   = $settings['theme_color'] ?? '#0073aa';
		$system_prompt = $settings['system_prompt'] ?? '';
		$chatbot_name  = $settings['chatbot_name'] ?? '';
		$chatbot_logo  = $settings['chatbot_logo'] ?? '';
		?>
		<div class="space-y-4">
			<div>
				<label for="wpaic_chatbot_name" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'Chatbot Name', 'wp-ai-chatbot' ); ?>
				</label>
				<input type="text" id="wpaic_chatbot_name" name="wpaic_settings[chatbot_name]" value="<?php echo esc_attr( $chatbot_name ); ?>"
						class="max-w-md w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
						placeholder="<?php esc_attr_e( 'e.g. ShopBot', 'wp-ai-chatbot' ); ?>">
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'Display name for the chatbot. Leave empty for "AI Assistant".', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div>
				<label for="wpaic_chatbot_logo" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'Chatbot Logo URL', 'wp-ai-chatbot' ); ?>
				</label>
				<input type="url" id="wpaic_chatbot_logo" name="wpaic_settings[chatbot_logo]" value="<?php echo esc_attr( $chatbot_logo ); ?>"
						class="max-w-md w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
						placeholder="<?php esc_attr_e( 'https://example.com/logo.png', 'wp-ai-chatbot' ); ?>">
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'URL to a logo image (max 32px height). Leave empty for no logo.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div>
				<label class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'Theme Color', 'wp-ai-chatbot' ); ?>
				</label>
				<input type="text" name="wpaic_settings[theme_color]" value="<?php echo esc_attr( $theme_color ); ?>"
						class="wpaic-color-picker" data-default-color="#0073aa">
				<p class="mt-2 text-sm text-gray-500"><?php esc_html_e( 'Primary color for the chat header, buttons, and accents.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div>
				<label for="wpaic_system_prompt" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'Custom System Prompt', 'wp-ai-chatbot' ); ?>
				</label>
				<textarea id="wpaic_system_prompt" name="wpaic_settings[system_prompt]" rows="6"
							class="max-w-lg w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm font-mono"
							placeholder="<?php esc_attr_e( 'Leave empty for default prompt', 'wp-ai-chatbot' ); ?>"><?php echo esc_textarea( $system_prompt ); ?></textarea>
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'Define the chatbot\'s personality and behavior. Leave empty for the default helpful assistant prompt.', 'wp-ai-chatbot' ); ?></p>
			</div>
		</div>
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
		$page_options      = array(
			'all'      => __( 'All pages', 'wp-ai-chatbot' ),
			'shop'     => __( 'Shop pages only', 'wp-ai-chatbot' ),
			'product'  => __( 'Product pages only', 'wp-ai-chatbot' ),
			'homepage' => __( 'Homepage only', 'wp-ai-chatbot' ),
		);
		?>
		<div class="space-y-4">
			<h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2"><?php esc_html_e( 'Human Handoff', 'wp-ai-chatbot' ); ?></h3>

			<div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
				<div>
					<h3 class="text-sm font-medium text-gray-900"><?php esc_html_e( 'Enable Handoff to Human', 'wp-ai-chatbot' ); ?></h3>
					<p class="text-sm text-gray-500"><?php esc_html_e( 'Allow customers to request human support. Bot collects name/email and sends notification.', 'wp-ai-chatbot' ); ?></p>
				</div>
				<label class="relative inline-flex items-center cursor-pointer">
					<input type="checkbox" name="wpaic_settings[handoff_enabled]" value="1" <?php checked( $handoff_enabled ); ?> class="sr-only peer">
					<div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
				</label>
			</div>

			<h3 class="text-lg font-medium text-gray-900 border-b border-gray-200 pb-2 mt-6"><?php esc_html_e( 'Proactive Engagement', 'wp-ai-chatbot' ); ?></h3>

			<div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
				<div class="flex">
					<span class="dashicons dashicons-lightbulb text-yellow-500 mr-2"></span>
					<p class="text-sm text-yellow-700"><?php esc_html_e( 'Proactive engagement automatically opens the chat widget after a delay to encourage visitor interaction.', 'wp-ai-chatbot' ); ?></p>
				</div>
			</div>

			<div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
				<div>
					<h3 class="text-sm font-medium text-gray-900"><?php esc_html_e( 'Enable Proactive Popup', 'wp-ai-chatbot' ); ?></h3>
					<p class="text-sm text-gray-500"><?php esc_html_e( 'Auto-open chat widget after visitor is on page', 'wp-ai-chatbot' ); ?></p>
				</div>
				<label class="relative inline-flex items-center cursor-pointer">
					<input type="checkbox" name="wpaic_settings[proactive_enabled]" value="1" <?php checked( $proactive_enabled ); ?> class="sr-only peer">
					<div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
				</label>
			</div>

			<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
				<div>
					<label for="wpaic_proactive_delay" class="block text-sm font-medium text-gray-700 mb-2">
						<?php esc_html_e( 'Trigger Delay', 'wp-ai-chatbot' ); ?>
					</label>
					<div class="flex items-center gap-2">
						<input type="number" id="wpaic_proactive_delay" name="wpaic_settings[proactive_delay]" value="<?php echo esc_attr( (string) $proactive_delay ); ?>"
								min="1" max="300"
								class="w-24 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
						<span class="text-sm text-gray-500"><?php esc_html_e( 'seconds', 'wp-ai-chatbot' ); ?></span>
					</div>
				</div>

				<div>
					<label for="wpaic_proactive_pages" class="block text-sm font-medium text-gray-700 mb-2">
						<?php esc_html_e( 'Show On Pages', 'wp-ai-chatbot' ); ?>
					</label>
					<select id="wpaic_proactive_pages" name="wpaic_settings[proactive_pages]"
							class="max-w-xs w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
						<?php foreach ( $page_options as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $proactive_pages, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div>
				<label for="wpaic_proactive_message" class="block text-sm font-medium text-gray-700 mb-2">
					<?php esc_html_e( 'Proactive Message', 'wp-ai-chatbot' ); ?>
				</label>
				<textarea id="wpaic_proactive_message" name="wpaic_settings[proactive_message]" rows="2"
							class="max-w-md w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
							placeholder="<?php esc_attr_e( 'Hi! Looking for something specific? I can help you find the perfect product.', 'wp-ai-chatbot' ); ?>"><?php echo esc_textarea( $proactive_message ); ?></textarea>
				<p class="mt-1 text-sm text-gray-500"><?php esc_html_e( 'Custom message when chat opens proactively. Leave empty to use the greeting message.', 'wp-ai-chatbot' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Search Index settings tab.
	 */
	private function render_search_tab(): void {
		$search_index = new WPAIC_Search_Index();
		$status       = $search_index->get_index_status();
		$exists       = $status['exists'];
		$count        = $status['product_count'];
		$updated      = $status['last_updated'];
		?>
		<div class="space-y-4">
			<div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
				<p class="text-sm text-gray-600"><?php esc_html_e( 'The search index enables fast fuzzy search across product titles, descriptions, SKUs, categories, and attributes.', 'wp-ai-chatbot' ); ?></p>
			</div>

			<div class="p-6 border border-gray-200 rounded-lg">
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-3">
						<?php if ( $exists ) : ?>
							<span class="flex items-center justify-center w-10 h-10 bg-green-100 rounded-full">
								<span class="dashicons dashicons-yes-alt text-green-600"></span>
							</span>
							<div>
								<h3 class="text-sm font-medium text-gray-900"><?php esc_html_e( 'Index Active', 'wp-ai-chatbot' ); ?></h3>
								<p class="text-sm text-gray-500">
									<?php
									printf(
										/* translators: 1: product count, 2: last updated date */
										esc_html__( '%1$d products indexed. Last updated: %2$s', 'wp-ai-chatbot' ),
										esc_html( (string) $count ),
										esc_html( $updated ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $updated ) ) : __( 'Unknown', 'wp-ai-chatbot' ) )
									);
									?>
								</p>
							</div>
						<?php else : ?>
							<span class="flex items-center justify-center w-10 h-10 bg-red-100 rounded-full">
								<span class="dashicons dashicons-warning text-red-600"></span>
							</span>
							<div>
								<h3 class="text-sm font-medium text-gray-900"><?php esc_html_e( 'Index Missing', 'wp-ai-chatbot' ); ?></h3>
								<p class="text-sm text-gray-500"><?php esc_html_e( 'Click Rebuild Index to create it.', 'wp-ai-chatbot' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<div class="flex items-center gap-3">
						<button type="button" id="wpaic-rebuild-index"
								class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-medium border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
							<span class="dashicons dashicons-update mr-2" style="font-size: 16px; width: 16px; height: 16px;"></span>
							<?php esc_html_e( 'Rebuild Index', 'wp-ai-chatbot' ); ?>
						</button>
						<span id="wpaic-rebuild-status" class="text-sm"></span>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#wpaic-rebuild-index').on('click', function() {
				var $btn = $(this);
				var $status = $('#wpaic-rebuild-status');
				$btn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
				$btn.find('.dashicons').addClass('animate-spin');
				$status.html('<span class="text-gray-500"><?php echo esc_js( __( 'Rebuilding...', 'wp-ai-chatbot' ) ); ?></span>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpaic_rebuild_index',
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_rebuild_index' ) ); ?>'
					},
					success: function(response) {
						$btn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
						$btn.find('.dashicons').removeClass('animate-spin');
						if (response.success) {
							$status.html('<span class="text-green-600">' + response.data.message + '</span>');
							setTimeout(function() { location.reload(); }, 1500);
						} else {
							$status.html('<span class="text-red-600">' + (response.data ? response.data.message : '<?php echo esc_js( __( 'Error rebuilding index.', 'wp-ai-chatbot' ) ); ?>') + '</span>');
						}
					},
					error: function() {
						$btn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
						$btn.find('.dashicons').removeClass('animate-spin');
						$status.html('<span class="text-red-600"><?php echo esc_js( __( 'Request failed.', 'wp-ai-chatbot' ) ); ?></span>');
					}
				});
			});
		});
		</script>
		<style>
			@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
			.animate-spin { animation: spin 1s linear infinite; }
		</style>
		<?php
	}

	public function render_logs_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page      = 20;
		$offset        = ( $page - 1 ) * $per_page;
		$conversations = $this->logs->get_conversations( $per_page, $offset );
		$total         = $this->logs->get_conversation_count();
		$total_pages   = (int) ceil( $total / $per_page );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chat Logs', 'wp-ai-chatbot' ); ?></h1>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'wp-ai-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Session', 'wp-ai-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'User', 'wp-ai-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Messages', 'wp-ai-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Started', 'wp-ai-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Activity', 'wp-ai-chatbot' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'wp-ai-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $conversations ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No conversations found.', 'wp-ai-chatbot' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $conversations as $conv ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $conv->id ); ?></td>
								<td><code><?php echo esc_html( substr( $conv->session_id, 0, 8 ) . '...' ); ?></code></td>
								<td>
									<?php
									if ( $conv->user_id ) {
										$user = get_user_by( 'id', $conv->user_id );
										echo esc_html( $user ? $user->display_name : 'User #' . $conv->user_id );
									} else {
										echo esc_html( $conv->user_ip ?? __( 'Anonymous', 'wp-ai-chatbot' ) );
									}
									?>
								</td>
								<td><?php echo esc_html( (string) $conv->message_count ); ?></td>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conv->created_at ) ) ); ?></td>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $conv->updated_at ) ) ); ?></td>
								<td>
									<button type="button" class="button button-small wpaic-view-conversation" data-id="<?php echo esc_attr( (string) $conv->id ); ?>">
										<?php esc_html_e( 'View', 'wp-ai-chatbot' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete wpaic-delete-conversation" data-id="<?php echo esc_attr( (string) $conv->id ); ?>">
										<?php esc_html_e( 'Delete', 'wp-ai-chatbot' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						$pagination_args = array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $page,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						);
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links output is safe
						echo paginate_links( $pagination_args );
						?>
					</div>
				</div>
			<?php endif; ?>

			<div id="wpaic-conversation-modal" style="display:none;">
				<div class="wpaic-modal-backdrop"></div>
				<div class="wpaic-modal-content">
					<div class="wpaic-modal-header">
						<h2><?php esc_html_e( 'Conversation', 'wp-ai-chatbot' ); ?></h2>
						<button type="button" class="wpaic-modal-close">&times;</button>
					</div>
					<div class="wpaic-modal-body"></div>
				</div>
			</div>

			<style>
				#wpaic-conversation-modal .wpaic-modal-backdrop {
					position: fixed;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					background: rgba(0,0,0,0.5);
					z-index: 100000;
				}
				#wpaic-conversation-modal .wpaic-modal-content {
					position: fixed;
					top: 50%;
					left: 50%;
					transform: translate(-50%, -50%);
					background: #fff;
					width: 600px;
					max-width: 90%;
					max-height: 80vh;
					border-radius: 4px;
					z-index: 100001;
					display: flex;
					flex-direction: column;
				}
				#wpaic-conversation-modal .wpaic-modal-header {
					padding: 15px 20px;
					border-bottom: 1px solid #ddd;
					display: flex;
					justify-content: space-between;
					align-items: center;
				}
				#wpaic-conversation-modal .wpaic-modal-header h2 {
					margin: 0;
				}
				#wpaic-conversation-modal .wpaic-modal-close {
					background: none;
					border: none;
					font-size: 24px;
					cursor: pointer;
					padding: 0;
					line-height: 1;
				}
				#wpaic-conversation-modal .wpaic-modal-body {
					padding: 20px;
					overflow-y: auto;
					flex: 1;
				}
				.wpaic-message {
					margin-bottom: 15px;
					padding: 10px 15px;
					border-radius: 8px;
				}
				.wpaic-message-user {
					background: #007cba;
					color: #fff;
					margin-left: 40px;
				}
				.wpaic-message-assistant {
					background: #f0f0f0;
					margin-right: 40px;
				}
				.wpaic-message-role {
					font-size: 11px;
					text-transform: uppercase;
					opacity: 0.7;
					margin-bottom: 5px;
				}
				.wpaic-message-time {
					font-size: 11px;
					opacity: 0.7;
					margin-top: 5px;
				}
			</style>

			<script>
			jQuery(document).ready(function($) {
				$('.wpaic-view-conversation').on('click', function() {
					var id = $(this).data('id');
					$('#wpaic-conversation-modal').show();
					$('#wpaic-conversation-modal .wpaic-modal-body').html('<p>Loading...</p>');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'wpaic_get_conversation',
							conversation_id: id,
							_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_admin' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								var html = '';
								response.data.forEach(function(msg) {
									html += '<div class="wpaic-message wpaic-message-' + msg.role + '">';
									html += '<div class="wpaic-message-role">' + msg.role + '</div>';
									html += '<div class="wpaic-message-content">' + $('<div>').text(msg.content).html().replace(/\n/g, '<br>') + '</div>';
									html += '<div class="wpaic-message-time">' + msg.created_at + '</div>';
									html += '</div>';
								});
								$('#wpaic-conversation-modal .wpaic-modal-body').html(html || '<p>No messages.</p>');
							} else {
								$('#wpaic-conversation-modal .wpaic-modal-body').html('<p>Error loading conversation.</p>');
							}
						}
					});
				});

				$('.wpaic-delete-conversation').on('click', function() {
					if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this conversation?', 'wp-ai-chatbot' ) ); ?>')) {
						return;
					}

					var $btn = $(this);
					var id = $btn.data('id');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'wpaic_delete_conversation',
							conversation_id: id,
							_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_admin' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								$btn.closest('tr').fadeOut(function() { $(this).remove(); });
							} else {
								alert('Error deleting conversation.');
							}
						}
					});
				});

				$('.wpaic-modal-close, .wpaic-modal-backdrop').on('click', function() {
					$('#wpaic-conversation-modal').hide();
				});
			});
			</script>
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

		$messages = $this->logs->get_conversation_messages( $conversation_id );
		$data     = array();

		foreach ( $messages as $msg ) {
			$data[] = array(
				'role'       => $msg->role,
				'content'    => $msg->content,
				'created_at' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $msg->created_at ) ),
			);
		}

		wp_send_json_success( $data );
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

	public function ajax_rebuild_index(): void {
		check_ajax_referer( 'wpaic_rebuild_index', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-ai-chatbot' ) ) );
		}

		$search_index = new WPAIC_Search_Index();
		$result       = $search_index->build_index();

		if ( $result ) {
			$status = $search_index->get_index_status();
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of products indexed */
						__( 'Index rebuilt successfully. %d products indexed.', 'wp-ai-chatbot' ),
						$status['product_count']
					),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to rebuild index. Check file permissions.', 'wp-ai-chatbot' ) ) );
		}
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
			'new'       => 'bg-yellow-100 text-yellow-800',
			'contacted' => 'bg-blue-100 text-blue-800',
			'resolved'  => 'bg-green-100 text-green-800',
		);
		?>
		<div class="wpaic-admin-wrap" style="margin-left: -20px;">
			<div class="bg-white shadow-sm border-b border-gray-200">
				<div class="max-w-5xl mx-auto px-6 py-6">
					<div class="flex items-center gap-3">
						<span class="dashicons dashicons-groups text-blue-600 text-2xl"></span>
						<h1 class="text-2xl font-semibold text-gray-900"><?php esc_html_e( 'Support Requests', 'wp-ai-chatbot' ); ?></h1>
					</div>
					<p class="mt-2 text-sm text-gray-600"><?php esc_html_e( 'Customer requests to speak with a human agent.', 'wp-ai-chatbot' ); ?></p>
				</div>
			</div>

			<div class="max-w-5xl mx-auto px-6 py-4">
				<?php if ( empty( $requests ) ) : ?>
					<div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
						<span class="dashicons dashicons-format-chat text-gray-400 text-4xl"></span>
						<p class="mt-4 text-gray-600"><?php esc_html_e( 'No support requests yet. Requests will appear here when customers use the handoff feature.', 'wp-ai-chatbot' ); ?></p>
					</div>
				<?php else : ?>
					<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
						<table class="min-w-full divide-y divide-gray-200">
							<thead class="bg-gray-50">
								<tr>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e( 'Date', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e( 'Customer', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e( 'Email', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e( 'Status', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e( 'Actions', 'wp-ai-chatbot' ); ?></th>
								</tr>
							</thead>
							<tbody class="bg-white divide-y divide-gray-200">
								<?php foreach ( $requests as $req ) : ?>
									<tr class="hover:bg-gray-50">
										<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
											<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $req->created_at ) ) ); ?>
										</td>
										<td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
											<?php echo esc_html( $req->customer_name ); ?>
										</td>
										<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
											<?php echo esc_html( $req->customer_email ); ?>
										</td>
										<td class="px-4 py-3 whitespace-nowrap">
											<select class="wpaic-status-select text-xs px-2 py-1 rounded-full border-0 <?php echo esc_attr( $status_colors[ $req->status ] ?? 'bg-gray-100 text-gray-800' ); ?>"
													data-id="<?php echo esc_attr( (string) $req->id ); ?>">
												<?php foreach ( $statuses as $status_val => $status_label ) : ?>
													<option value="<?php echo esc_attr( $status_val ); ?>" <?php selected( $req->status, $status_val ); ?>><?php echo esc_html( $status_label ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td class="px-4 py-3 whitespace-nowrap text-sm">
											<div class="flex items-center gap-2">
												<button type="button" class="wpaic-view-transcript inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
														data-id="<?php echo esc_attr( (string) $req->id ); ?>">
													<span class="dashicons dashicons-visibility" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
													<?php esc_html_e( 'View', 'wp-ai-chatbot' ); ?>
												</button>
												<a href="mailto:<?php echo esc_attr( $req->customer_email ); ?>" class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
													<span class="dashicons dashicons-email" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
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
			<div class="wpaic-modal-backdrop"></div>
			<div class="wpaic-modal-content">
				<div class="wpaic-modal-header">
					<h2><?php esc_html_e( 'Conversation Transcript', 'wp-ai-chatbot' ); ?></h2>
					<button type="button" class="wpaic-modal-close">&times;</button>
				</div>
				<div class="wpaic-modal-body"></div>
			</div>
		</div>

		<style>
			#wpaic-transcript-modal .wpaic-modal-backdrop {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0,0,0,0.5);
				z-index: 100000;
			}
			#wpaic-transcript-modal .wpaic-modal-content {
				position: fixed;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				background: #fff;
				width: 600px;
				max-width: 90%;
				max-height: 80vh;
				border-radius: 8px;
				z-index: 100001;
				display: flex;
				flex-direction: column;
			}
			#wpaic-transcript-modal .wpaic-modal-header {
				padding: 16px 20px;
				border-bottom: 1px solid #e5e7eb;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			#wpaic-transcript-modal .wpaic-modal-header h2 {
				margin: 0;
				font-size: 18px;
			}
			#wpaic-transcript-modal .wpaic-modal-close {
				background: none;
				border: none;
				font-size: 24px;
				cursor: pointer;
				padding: 0;
				line-height: 1;
				color: #6b7280;
			}
			#wpaic-transcript-modal .wpaic-modal-body {
				padding: 20px;
				overflow-y: auto;
				flex: 1;
			}
			.wpaic-transcript-message {
				margin-bottom: 12px;
				padding: 10px 14px;
				border-radius: 8px;
				font-size: 14px;
			}
			.wpaic-transcript-user {
				background: #3b82f6;
				color: #fff;
				margin-left: 40px;
			}
			.wpaic-transcript-assistant {
				background: #f3f4f6;
				margin-right: 40px;
			}
			.wpaic-transcript-role {
				font-size: 11px;
				text-transform: uppercase;
				opacity: 0.7;
				margin-bottom: 4px;
			}
			.wpaic-status-select {
				cursor: pointer;
				font-weight: 500;
			}
			.wpaic-status-select:focus {
				outline: none;
				ring: 2px;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('.wpaic-view-transcript').on('click', function() {
				var id = $(this).data('id');
				$('#wpaic-transcript-modal').show();
				$('#wpaic-transcript-modal .wpaic-modal-body').html('<p class="text-gray-500"><?php echo esc_js( __( 'Loading...', 'wp-ai-chatbot' ) ); ?></p>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpaic_get_support_transcript',
						request_id: id,
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_support' ) ); ?>'
					},
					success: function(response) {
						if (response.success && response.data.transcript) {
							var lines = response.data.transcript.split('\n');
							var html = '';
							var currentRole = '';
							var currentContent = '';

							lines.forEach(function(line) {
								if (line.startsWith('User: ')) {
									if (currentContent) {
										html += '<div class="wpaic-transcript-message wpaic-transcript-' + currentRole + '">';
										html += '<div class="wpaic-transcript-role">' + currentRole + '</div>';
										html += '<div>' + $('<div>').text(currentContent.trim()).html().replace(/\n/g, '<br>') + '</div></div>';
									}
									currentRole = 'user';
									currentContent = line.substring(6);
								} else if (line.startsWith('Assistant: ')) {
									if (currentContent) {
										html += '<div class="wpaic-transcript-message wpaic-transcript-' + currentRole + '">';
										html += '<div class="wpaic-transcript-role">' + currentRole + '</div>';
										html += '<div>' + $('<div>').text(currentContent.trim()).html().replace(/\n/g, '<br>') + '</div></div>';
									}
									currentRole = 'assistant';
									currentContent = line.substring(11);
								} else {
									currentContent += '\n' + line;
								}
							});

							if (currentContent) {
								html += '<div class="wpaic-transcript-message wpaic-transcript-' + currentRole + '">';
								html += '<div class="wpaic-transcript-role">' + currentRole + '</div>';
								html += '<div>' + $('<div>').text(currentContent.trim()).html().replace(/\n/g, '<br>') + '</div></div>';
							}

							$('#wpaic-transcript-modal .wpaic-modal-body').html(html || '<p class="text-gray-500"><?php echo esc_js( __( 'No transcript available.', 'wp-ai-chatbot' ) ); ?></p>');
						} else {
							$('#wpaic-transcript-modal .wpaic-modal-body').html('<p class="text-red-600"><?php echo esc_js( __( 'Error loading transcript.', 'wp-ai-chatbot' ) ); ?></p>');
						}
					}
				});
			});

			$('.wpaic-status-select').on('change', function() {
				var $select = $(this);
				var id = $select.data('id');
				var status = $select.val();

				var colorMap = {
					'new': 'bg-yellow-100 text-yellow-800',
					'contacted': 'bg-blue-100 text-blue-800',
					'resolved': 'bg-green-100 text-green-800'
				};

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpaic_update_support_status',
						request_id: id,
						status: status,
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_support' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$select.removeClass('bg-yellow-100 text-yellow-800 bg-blue-100 text-blue-800 bg-green-100 text-green-800');
							$select.addClass(colorMap[status] || 'bg-gray-100 text-gray-800');
						}
					}
				});
			});

			$('.wpaic-modal-close, .wpaic-modal-backdrop').on('click', function() {
				$('#wpaic-transcript-modal').hide();
			});
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
				"SELECT transcript FROM $table WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$request_id
			)
		);

		if ( $request ) {
			wp_send_json_success( array( 'transcript' => $request->transcript ) );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Render the Train Bot settings tab.
	 */
	private function render_train_tab(): void {
		global $wpdb;
		$sources_table = $wpdb->prefix . 'wpaic_data_sources';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$data_sources = $wpdb->get_results( "SELECT * FROM $sources_table ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="space-y-6">
			<div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
				<div class="flex">
					<span class="dashicons dashicons-info text-blue-500 mr-2"></span>
					<p class="text-sm text-blue-700"><?php esc_html_e( 'Upload CSV files to train the chatbot with custom data. The bot can then answer questions about this data.', 'wp-ai-chatbot' ); ?></p>
				</div>
			</div>

			<div>
				<div id="wpaic-data-sources-header" class="flex items-center justify-between mb-4 p-3 -m-3 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
					<h3 class="text-lg font-medium text-gray-900"><?php esc_html_e( 'Data Sources', 'wp-ai-chatbot' ); ?></h3>
					<button type="button" id="wpaic-add-source" class="inline-flex items-center px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
						<span class="dashicons dashicons-plus-alt2 mr-1" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<?php esc_html_e( 'Add Data Source', 'wp-ai-chatbot' ); ?>
					</button>
				</div>

				<?php if ( empty( $data_sources ) ) : ?>
					<div class="p-8 text-center border border-gray-200 rounded-lg bg-gray-50">
						<span class="dashicons dashicons-database text-gray-400 text-4xl"></span>
						<p class="mt-4 text-gray-600"><?php esc_html_e( 'No data sources yet. Click "Add Data Source" to upload a CSV file.', 'wp-ai-chatbot' ); ?></p>
					</div>
				<?php else : ?>
					<div class="border border-gray-200 rounded-lg overflow-hidden">
						<table class="min-w-full divide-y divide-gray-200">
							<thead class="bg-gray-50">
								<tr>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Name', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Description', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Columns', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Rows', 'wp-ai-chatbot' ); ?></th>
									<th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Actions', 'wp-ai-chatbot' ); ?></th>
								</tr>
							</thead>
							<tbody class="bg-white divide-y divide-gray-200">
								<?php foreach ( $data_sources as $source ) : ?>
									<?php $columns = json_decode( $source->columns, true ) ?: array(); ?>
									<tr class="hover:bg-gray-50" data-source-id="<?php echo esc_attr( (string) $source->id ); ?>">
										<td class="px-4 py-3">
											<div class="text-sm font-medium text-gray-900"><?php echo esc_html( $source->label ); ?></div>
											<div class="text-xs text-gray-500 font-mono"><?php echo esc_html( $source->name ); ?></div>
										</td>
										<td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate"><?php echo esc_html( $source->description ); ?></td>
										<td class="px-4 py-3 text-sm text-gray-600">
											<div class="flex flex-wrap gap-1">
												<?php foreach ( array_slice( $columns, 0, 4 ) as $col ) : ?>
													<span class="inline-block px-2 py-0.5 text-xs bg-gray-100 text-gray-700 rounded"><?php echo esc_html( $col ); ?></span>
												<?php endforeach; ?>
												<?php if ( count( $columns ) > 4 ) : ?>
													<span class="inline-block px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded">+<?php echo esc_html( (string) ( count( $columns ) - 4 ) ); ?></span>
												<?php endif; ?>
											</div>
										</td>
										<td class="px-4 py-3 text-sm text-gray-600"><?php echo esc_html( (string) $source->row_count ); ?></td>
										<td class="px-4 py-3 text-sm">
											<button type="button" class="wpaic-delete-source text-red-600 hover:text-red-800" data-id="<?php echo esc_attr( (string) $source->id ); ?>">
												<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>

			<div class="mt-8 pt-8 border-t border-gray-200">
				<h3 class="text-lg font-medium text-gray-900 mb-4"><?php esc_html_e( 'FAQ Responses', 'wp-ai-chatbot' ); ?></h3>
				<div class="p-4 bg-blue-50 border border-blue-200 rounded-lg mb-4">
					<div class="flex">
						<span class="dashicons dashicons-info text-blue-500 mr-2"></span>
						<p class="text-sm text-blue-700"><?php esc_html_e( 'Add Q&A pairs for common questions. The bot will use these to answer customer queries.', 'wp-ai-chatbot' ); ?></p>
					</div>
				</div>
				<?php
				global $wpdb;
				$faqs_table = $wpdb->prefix . 'wpaic_faqs';
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$faqs       = $wpdb->get_results( "SELECT * FROM $faqs_table ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$faq_text   = '';
				if ( ! empty( $faqs ) ) {
					$pairs = array();
					foreach ( $faqs as $faq ) {
						$pairs[] = "Q: " . $faq->question . "\nA: " . $faq->answer;
					}
					$faq_text = implode( "\n\n", $pairs );
				}
				?>
				<div>
					<label for="wpaic_faq_content" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Questions & Answers', 'wp-ai-chatbot' ); ?></label>
					<textarea id="wpaic_faq_content" rows="10" class="w-full max-w-2xl px-3 py-2 border border-gray-300 rounded-md text-sm font-mono"
							  placeholder="Q: What is your return policy?
A: We offer 30-day returns on all items.

Q: Do you ship internationally?
A: Yes, we ship to over 50 countries."><?php echo esc_textarea( $faq_text ); ?></textarea>
					<p class="mt-1 text-xs text-gray-500"><?php esc_html_e( 'Format: "Q: question" then "A: answer". Separate pairs with a blank line.', 'wp-ai-chatbot' ); ?></p>
				</div>
				<div class="mt-4 flex items-center gap-4">
					<button type="button" id="wpaic-save-faqs" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
						<?php esc_html_e( 'Save FAQs', 'wp-ai-chatbot' ); ?>
					</button>
					<span id="wpaic-faq-status" class="text-sm hidden"></span>
				</div>
			</div>
		</div>

		<div id="wpaic-source-modal" style="display:none;">
			<div class="wpaic-modal-backdrop"></div>
			<div class="wpaic-modal-content">
				<div class="wpaic-modal-header">
					<h2><?php esc_html_e( 'Add Data Source', 'wp-ai-chatbot' ); ?></h2>
					<button type="button" class="wpaic-modal-close">&times;</button>
				</div>
				<div class="wpaic-modal-body">
					<form id="wpaic-source-form" enctype="multipart/form-data">
						<div class="space-y-4">
							<div>
								<label for="wpaic_source_name" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Name (slug)', 'wp-ai-chatbot' ); ?></label>
								<input type="text" id="wpaic_source_name" name="source_name" required pattern="[a-z0-9_-]+"
									   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" placeholder="e.g. services">
								<p class="mt-1 text-xs text-gray-500"><?php esc_html_e( 'Lowercase letters, numbers, underscores, hyphens only.', 'wp-ai-chatbot' ); ?></p>
							</div>
							<div>
								<label for="wpaic_source_label" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Label', 'wp-ai-chatbot' ); ?></label>
								<input type="text" id="wpaic_source_label" name="source_label" required
									   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" placeholder="e.g. Our Services">
							</div>
							<div>
								<label for="wpaic_source_desc" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Description', 'wp-ai-chatbot' ); ?></label>
								<textarea id="wpaic_source_desc" name="source_description" rows="2" required
										  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" placeholder="<?php esc_attr_e( 'What information does this data contain? The bot uses this to decide when to query it.', 'wp-ai-chatbot' ); ?>"></textarea>
							</div>
							<div>
								<label for="wpaic_source_file" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'CSV File', 'wp-ai-chatbot' ); ?></label>
								<input type="file" id="wpaic_source_file" name="csv_file" accept=".csv" required
									   class="w-full text-sm text-gray-700 border border-gray-300 rounded-md cursor-pointer file:mr-3 file:py-2 file:px-4 file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 file:cursor-pointer hover:file:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
								<p class="mt-1 text-xs text-gray-500"><?php esc_html_e( 'Max 5MB. First row must be column headers.', 'wp-ai-chatbot' ); ?></p>
							</div>
							<div id="wpaic-upload-status" class="hidden">
								<div class="flex items-center text-sm">
									<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
									<span><?php esc_html_e( 'Uploading...', 'wp-ai-chatbot' ); ?></span>
								</div>
							</div>
							<div id="wpaic-upload-result" class="hidden"></div>
						</div>
						<div class="mt-6 flex justify-end gap-3">
							<button type="button" class="wpaic-modal-cancel px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
								<?php esc_html_e( 'Cancel', 'wp-ai-chatbot' ); ?>
							</button>
							<button type="submit" id="wpaic-upload-btn" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
								<?php esc_html_e( 'Upload', 'wp-ai-chatbot' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<style>
			#wpaic-source-modal .wpaic-modal-backdrop {
				position: fixed;
				top: 0; left: 0; right: 0; bottom: 0;
				background: rgba(0,0,0,0.5);
				z-index: 100000;
			}
			#wpaic-source-modal .wpaic-modal-content {
				position: fixed;
				top: 50%; left: 50%;
				transform: translate(-50%, -50%);
				background: #fff;
				width: 500px;
				max-width: 90%;
				border-radius: 8px;
				z-index: 100001;
			}
			#wpaic-source-modal .wpaic-modal-header {
				padding: 16px 20px;
				border-bottom: 1px solid #e5e7eb;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			#wpaic-source-modal .wpaic-modal-header h2 {
				margin: 0;
				font-size: 18px;
			}
			#wpaic-source-modal .wpaic-modal-close {
				background: none;
				border: none;
				font-size: 24px;
				cursor: pointer;
				padding: 0;
				color: #6b7280;
			}
			#wpaic-source-modal .wpaic-modal-body {
				padding: 20px;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('#wpaic-add-source, #wpaic-data-sources-header').on('click', function() {
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
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						$('#wpaic-upload-status').addClass('hidden');
						$('#wpaic-upload-btn').prop('disabled', false);
						if (response.success) {
							$('#wpaic-upload-result').removeClass('hidden').html(
								'<div class="p-3 bg-green-50 text-green-700 rounded-md text-sm">' +
								'<strong><?php echo esc_js( __( 'Success!', 'wp-ai-chatbot' ) ); ?></strong> ' + response.data.message +
								'</div>'
							);
							setTimeout(function() { location.reload(); }, 1500);
						} else {
							$('#wpaic-upload-result').removeClass('hidden').html(
								'<div class="p-3 bg-red-50 text-red-700 rounded-md text-sm">' +
								'<strong><?php echo esc_js( __( 'Error:', 'wp-ai-chatbot' ) ); ?></strong> ' + (response.data ? response.data.message : '<?php echo esc_js( __( 'Upload failed.', 'wp-ai-chatbot' ) ); ?>') +
								'</div>'
							);
						}
					},
					error: function() {
						$('#wpaic-upload-status').addClass('hidden');
						$('#wpaic-upload-btn').prop('disabled', false);
						$('#wpaic-upload-result').removeClass('hidden').html(
							'<div class="p-3 bg-red-50 text-red-700 rounded-md text-sm"><?php echo esc_js( __( 'Request failed. Please try again.', 'wp-ai-chatbot' ) ); ?></div>'
						);
					}
				});
			});

			$('.wpaic-delete-source').on('click', function() {
				if (!confirm('<?php echo esc_js( __( 'Delete this data source? This cannot be undone.', 'wp-ai-chatbot' ) ); ?>')) {
					return;
				}
				var $btn = $(this);
				var id = $btn.data('id');
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpaic_delete_data_source',
						source_id: id,
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_delete_source' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$btn.closest('tr').fadeOut(function() { $(this).remove(); });
						} else {
							alert(response.data ? response.data.message : '<?php echo esc_js( __( 'Delete failed.', 'wp-ai-chatbot' ) ); ?>');
						}
					}
				});
			});

			$('#wpaic-save-faqs').on('click', function() {
				var $btn = $(this);
				var $status = $('#wpaic-faq-status');
				var content = $('#wpaic_faq_content').val();

				$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'wp-ai-chatbot' ) ); ?>');
				$status.addClass('hidden');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpaic_save_faqs',
						faq_content: content,
						_wpnonce: '<?php echo esc_js( wp_create_nonce( 'wpaic_save_faqs' ) ); ?>'
					},
					success: function(response) {
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save FAQs', 'wp-ai-chatbot' ) ); ?>');
						if (response.success) {
							$status.removeClass('hidden text-red-600').addClass('text-green-600').text(response.data.message);
						} else {
							$status.removeClass('hidden text-green-600').addClass('text-red-600').text(response.data ? response.data.message : '<?php echo esc_js( __( 'Save failed.', 'wp-ai-chatbot' ) ); ?>');
						}
					},
					error: function() {
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save FAQs', 'wp-ai-chatbot' ) ); ?>');
						$status.removeClass('hidden text-green-600').addClass('text-red-600').text('<?php echo esc_js( __( 'Request failed.', 'wp-ai-chatbot' ) ); ?>');
					}
				});
			});
		});
		</script>
		<?php
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

		global $wpdb;
		$sources_table = $wpdb->prefix . 'wpaic_data_sources';
		$data_table    = $wpdb->prefix . 'wpaic_training_data';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $sources_table WHERE name = %s", $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $data_table, array( 'source_id' => $existing ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $sources_table, array( 'id' => $existing ), array( '%d' ) );
		}

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
		$source_id = $wpdb->insert_id;

		if ( ! $source_id ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			wp_send_json_error( array( 'message' => __( 'Failed to create data source.', 'wp-ai-chatbot' ) ) );
		}

		$row_count = 0;
		$preview   = array();

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
			$row_data = array();
			foreach ( $headers as $i => $header ) {
				$row_data[ $header ] = isset( $row[ $i ] ) ? sanitize_text_field( $row[ $i ] ) : '';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$data_table,
				array(
					'source_id' => $source_id,
					'row_data'  => wp_json_encode( $row_data ),
				),
				array( '%d', '%s' )
			);
			++$row_count;

			if ( $row_count <= 3 ) {
				$preview[] = $row_data;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $sources_table, array( 'row_count' => $row_count ), array( 'id' => $source_id ), array( '%d' ), array( '%d' ) );

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

		// Clear existing FAQs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE $faqs_table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( '' === trim( $content ) ) {
			wp_send_json_success( array( 'message' => __( 'FAQs cleared.', 'wp-ai-chatbot' ) ) );
		}

		$pairs  = preg_split( '/\n\s*\n/', $content );
		$count  = 0;

		foreach ( $pairs as $pair ) {
			$pair = trim( $pair );
			if ( '' === $pair ) {
				continue;
			}

			if ( preg_match( '/^Q:\s*(.+?)\s*\nA:\s*(.+)$/si', $pair, $matches ) ) {
				$question = trim( $matches[1] );
				$answer   = trim( $matches[2] );

				if ( '' !== $question && '' !== $answer ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->insert(
						$faqs_table,
						array(
							'question' => $question,
							'answer'   => $answer,
						),
						array( '%s', '%s' )
					);
					++$count;
				}
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of FAQs saved */
					__( '%d FAQ(s) saved.', 'wp-ai-chatbot' ),
					$count
				),
			)
		);
	}
}
