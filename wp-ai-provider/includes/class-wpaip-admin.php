<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Admin {

	/**
	 * @return array<string, string>
	 */
	public function get_available_models(): array {
		return array(
			'gpt-4o-mini' => 'GPT-4o Mini (Fast & Cheap)',
			'gpt-4o'      => 'GPT-4o (Balanced)',
			'gpt-5'       => 'GPT-5 (Best - Expensive)',
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
			'site_key',
			__( 'Site Key', 'wp-ai-provider' ),
			array( $this, 'render_site_key_field' ),
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
			__( 'Default Model', 'wp-ai-provider' ),
			array( $this, 'render_model_field' ),
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

		$sanitized['site_key'] = $existing['site_key'] ?? '';

		$sanitized['openai_api_key'] = sanitize_text_field( trim( $input['openai_api_key'] ?? '' ) );

		$model           = sanitize_text_field( $input['model'] ?? 'gpt-4o-mini' );
		$valid_models    = array_keys( $this->get_available_models() );
		$sanitized['model'] = in_array( $model, $valid_models, true ) ? $model : 'gpt-4o-mini';

		return $sanitized;
	}

	public function render_site_key_field(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['site_key'] ?? '' ) : '';
		echo '<input type="text" id="wpaip-site-key" value="' . esc_attr( $value ) . '" class="regular-text" readonly />';
		echo ' <button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'wpaip-site-key\').value).then(function(){alert(\'Copied!\')})">Copy</button>';
		echo '<p class="description">' . esc_html__( 'Auto-generated key used by chatbot instances to authenticate with this provider.', 'wp-ai-provider' ) . '</p>';
	}

	public function render_api_key_field(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['openai_api_key'] ?? '' ) : '';
		echo '<input type="password" name="wpaip_settings[openai_api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	public function render_model_field(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$value    = is_array( $settings ) ? ( $settings['model'] ?? 'gpt-4o-mini' ) : 'gpt-4o-mini';
		$models   = $this->get_available_models();
		echo '<select name="wpaip_settings[model]">';
		foreach ( $models as $model_id => $label ) {
			$selected = ( $value === $model_id ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $model_id ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'wpaip_settings_group' );
		do_settings_sections( 'wp-ai-provider' );
		submit_button();
		echo '</form>';
		echo '</div>';
	}
}
