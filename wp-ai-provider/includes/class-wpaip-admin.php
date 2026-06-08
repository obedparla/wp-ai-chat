<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Admin {
	public const DEFAULT_MODEL            = 'gpt-5-mini';
	public const DEFAULT_REASONING_EFFORT = 'medium';

	private WPAIP_Install_Registry $registry;

	public function __construct( ?WPAIP_Install_Registry $registry = null ) {
		$this->registry = $registry ?? new WPAIP_Install_Registry();
	}

	/**
	 * Models the provider may send to OpenAI. The provider — not the chatbot —
	 * picks one per request.
	 *
	 * @return array<string, string> Model ID => human label.
	 */
	public static function get_available_models(): array {
		return array(
			'gpt-5-mini'   => 'GPT-5 Mini',
			'gpt-5.4-mini' => 'GPT-5.4 Mini',
			'gpt-5.4-nano' => 'GPT-5.4 Nano',
		);
	}

	/**
	 * Reasoning-effort levels, chosen independently of the model.
	 *
	 * @return array<string, string> Effort value => human label.
	 */
	public static function get_available_reasoning_efforts(): array {
		return array(
			'none'   => 'None',
			'low'    => 'Low',
			'medium' => 'Medium',
			'high'   => 'High',
		);
	}

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_admin_menu(): void {
		add_menu_page(
			__( 'AI Provider', 'wp-ai-provider' ),
			__( 'AI Provider', 'wp-ai-provider' ),
			'manage_options',
			'wp-ai-provider',
			array( $this, 'render_settings_page' ),
			'dashicons-cloud',
			81
		);
	}

	public function register_settings(): void {
		register_setting(
			'wpaip_settings_group',
			'wpaip_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'wpaip_main_section',
			__( 'Provider Settings', 'wp-ai-provider' ),
			'__return_null',
			'wp-ai-provider'
		);

		add_settings_field(
			'freemius_product_id',
			__( 'Freemius Product ID', 'wp-ai-provider' ),
			array( $this, 'render_freemius_product_id_field' ),
			'wp-ai-provider',
			'wpaip_main_section'
		);

		add_settings_field(
			'freemius_api_token',
			__( 'Freemius API Token', 'wp-ai-provider' ),
			array( $this, 'render_freemius_api_token_field' ),
			'wp-ai-provider',
			'wpaip_main_section'
		);

		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'wp-ai-provider' ),
			array( $this, 'render_api_key_field' ),
			'wp-ai-provider',
			'wpaip_main_section'
		);

		add_settings_field(
			'model',
			__( 'Model', 'wp-ai-provider' ),
			array( $this, 'render_model_field' ),
			'wp-ai-provider',
			'wpaip_main_section'
		);

		add_settings_field(
			'reasoning_effort',
			__( 'Reasoning Effort', 'wp-ai-provider' ),
			array( $this, 'render_reasoning_effort_field' ),
			'wp-ai-provider',
			'wpaip_main_section'
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$existing = get_option( 'wpaip_settings', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$sanitized = array();

		$sanitized['freemius_product_id'] = max( 0, (int) ( $input['freemius_product_id'] ?? ( $existing['freemius_product_id'] ?? 0 ) ) );
		$sanitized['freemius_api_token']  = sanitize_text_field( trim( $input['freemius_api_token'] ?? ( $existing['freemius_api_token'] ?? '' ) ) );
		$sanitized['openai_api_key']      = sanitize_text_field( trim( $input['openai_api_key'] ?? '' ) );

		$model           = sanitize_text_field( $input['model'] ?? self::DEFAULT_MODEL );
		$valid_models    = array_keys( self::get_available_models() );
		$sanitized['model'] = in_array( $model, $valid_models, true ) ? $model : self::DEFAULT_MODEL;

		$reasoning_effort           = sanitize_text_field( $input['reasoning_effort'] ?? self::DEFAULT_REASONING_EFFORT );
		$valid_efforts              = array_keys( self::get_available_reasoning_efforts() );
		$sanitized['reasoning_effort'] = in_array( $reasoning_effort, $valid_efforts, true ) ? $reasoning_effort : self::DEFAULT_REASONING_EFFORT;

		return $sanitized;
	}

	public function render_freemius_product_id_field(): void {
		$value = $this->get_freemius_product_id();
		echo '<input type="number" min="0" step="1" name="wpaip_settings[freemius_product_id]" value="' . esc_attr( (string) $value ) . '" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Internal only. Used by the provider to validate chatbot installs and licenses against Freemius. Defaults to the constant-defined product ID when the saved value is empty.', 'wp-ai-provider' ) . '</p>';
	}

	public function render_freemius_api_token_field(): void {
		$settings      = get_option( 'wpaip_settings', array() );
		$saved_value   = is_array( $settings ) ? (string) ( $settings['freemius_api_token'] ?? '' ) : '';
		$placeholder   = $this->has_constant_freemius_api_token() ? __( 'Configured via wp-config.php constant', 'wp-ai-provider' ) : '';

		echo '<input type="password" name="wpaip_settings[freemius_api_token]" value="' . esc_attr( $saved_value ) . '" class="regular-text code" placeholder="' . esc_attr( $placeholder ) . '" />';
		echo '<p class="description">' . esc_html__( 'Bearer token from Freemius. Stored only on the internal provider site. You can also provide it via the WPAIP_FREEMIUS_API_TOKEN constant for local development.', 'wp-ai-provider' ) . '</p>';
	}

	public function render_api_key_field(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['openai_api_key'] ?? '' ) : '';
		echo '<input type="password" name="wpaip_settings[openai_api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	public function render_model_field(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['model'] ?? self::DEFAULT_MODEL ) : self::DEFAULT_MODEL;
		$models   = self::get_available_models();
		echo '<select name="wpaip_settings[model]">';
		foreach ( $models as $model_id => $label ) {
			$selected = ( $value === $model_id ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $model_id ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Model sent to OpenAI for every chatbot request. Decided here, not by chatbot installs.', 'wp-ai-provider' ) . '</p>';
	}

	public function render_reasoning_effort_field(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['reasoning_effort'] ?? self::DEFAULT_REASONING_EFFORT ) : self::DEFAULT_REASONING_EFFORT;
		$efforts  = self::get_available_reasoning_efforts();
		echo '<select name="wpaip_settings[reasoning_effort]">';
		foreach ( $efforts as $effort_value => $label ) {
			$selected = ( $value === $effort_value ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $effort_value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Reasoning effort sent to OpenAI, independent of the model.', 'wp-ai-provider' ) . '</p>';
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$records = $this->registry->all();
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<p>' . esc_html__( 'The provider validates each chatbot request against Freemius using the product ID and API token below.', 'wp-ai-provider' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Freemius validation status:', 'wp-ai-provider' ) . '</strong> ' . esc_html( $this->get_freemius_status_label() ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'wpaip_settings_group' );
		do_settings_sections( 'wp-ai-provider' );
		submit_button();
		echo '</form>';
		echo '<hr />';
		echo '<h2>' . esc_html__( 'Validated Chatbot Installs', 'wp-ai-provider' ) . '</h2>';
		echo '<p>' . esc_html__( 'Recent Freemius-backed install validations handled by this provider. This is the internal monitoring view for license/trial checks and future rate-limiting keys.', 'wp-ai-provider' ) . '</p>';

		if ( empty( $records ) ) {
			echo '<p>' . esc_html__( 'No validated installs yet.', 'wp-ai-provider' ) . '</p>';
		} else {
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Site', 'wp-ai-provider' ) . '</th>';
			echo '<th>' . esc_html__( 'Install ID', 'wp-ai-provider' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'wp-ai-provider' ) . '</th>';
			echo '<th>' . esc_html__( 'License', 'wp-ai-provider' ) . '</th>';
			echo '<th>' . esc_html__( 'Usage Bucket', 'wp-ai-provider' ) . '</th>';
			echo '<th>' . esc_html__( 'Last Validated', 'wp-ai-provider' ) . '</th>';
			echo '<th>' . esc_html__( 'Last Seen', 'wp-ai-provider' ) . '</th>';
			echo '<th>' . esc_html__( 'Last Error', 'wp-ai-provider' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $records as $record ) {
				$site_url       = (string) ( $record['site_url'] ?? '' );
				$site_label     = '' !== $site_url ? $site_url : __( 'Unknown site', 'wp-ai-provider' );
				$license_label  = isset( $record['license_id'] ) && null !== $record['license_id'] ? (string) $record['license_id'] : '—';
				$error_message  = (string) ( $record['last_error_message'] ?? '' );

				echo '<tr>';
				echo '<td>' . esc_html( $site_label ) . '</td>';
				echo '<td>' . esc_html( (string) ( $record['install_id'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $record['status'] ?? '' ) ) . '</td>';
				echo '<td>' . esc_html( $license_label ) . '</td>';
				echo '<td><code>' . esc_html( (string) ( $record['usage_bucket_key'] ?? '' ) ) . '</code></td>';
				echo '<td>' . esc_html( (string) ( $record['last_validated_at'] ?? '—' ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $record['last_seen_at'] ?? '—' ) ) . '</td>';
				echo '<td>' . esc_html( '' !== $error_message ? $error_message : '—' ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
		echo '</div>';
	}

	private function get_freemius_product_id(): int {
		$settings = get_option( 'wpaip_settings', array() );
		$value    = is_array( $settings ) ? (int) ( $settings['freemius_product_id'] ?? 0 ) : 0;

		if ( $value <= 0 && defined( 'WPAIP_FREEMIUS_PRODUCT_ID' ) ) {
			return (int) WPAIP_FREEMIUS_PRODUCT_ID;
		}

		return $value;
	}

	private function has_constant_freemius_api_token(): bool {
		return defined( 'WPAIP_FREEMIUS_API_TOKEN' ) && '' !== (string) WPAIP_FREEMIUS_API_TOKEN;
	}

	private function get_freemius_status_label(): string {
		$api = new WPAIP_Freemius_API();

		return $api->is_configured()
			? __( 'Configured', 'wp-ai-provider' )
			: __( 'Missing product ID or API token', 'wp-ai-provider' );
	}
}
