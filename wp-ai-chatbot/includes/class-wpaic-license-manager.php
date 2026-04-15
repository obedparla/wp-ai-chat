<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_License_Manager {
	private const PROVIDER_SIGNATURE_TTL = 300;

	/**
	 * @return array<int, int>
	 */
	private function get_trial_notice_days(): array {
		return array( 3, 1 );
	}

	public function init(): void {
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );

		if ( ! $this->is_freemius_available() ) {
			return;
		}

		add_action( 'fs_after_account_connection_wp-ai-chatbot', array( $this, 'maybe_start_trial' ) );
		add_action( 'fs_after_license_change_wp-ai-chatbot', array( $this, 'maybe_start_trial' ) );
	}

	public function is_freemius_available(): bool {
		return function_exists( 'wpaic_is_freemius_configured' )
			&& wpaic_is_freemius_configured()
			&& function_exists( 'wpaic_fs' )
			&& is_object( wpaic_fs() );
	}

	public function has_valid_chat_license(): bool {
		if ( ! $this->is_freemius_available() ) {
			return false;
		}

		$fs = wpaic_fs();

		return $fs->is_trial() || $fs->has_active_valid_license();
	}

	public function has_provider_auth(): bool {
		return ! empty( $this->get_provider_request_headers( array() ) );
	}

	public function is_provider_url_configured(): bool {
		$provider_url = $this->get_provider_url();

		return '' !== $provider_url && 'PLACEHOLDER_PROVIDER_URL' !== $provider_url;
	}

	public function can_render_chat(): bool {
		return $this->has_valid_chat_license()
			&& $this->is_provider_url_configured()
			&& $this->has_provider_auth();
	}

	public function get_provider_url(): string {
		$provider_url = defined( 'WPAIC_PRODUCTION_PROVIDER_URL' ) ? (string) WPAIC_PRODUCTION_PROVIDER_URL : '';

		if ( $this->is_provider_url_override_allowed() ) {
			$constant_override = defined( 'WPAIC_PROVIDER_URL_OVERRIDE' ) ? (string) WPAIC_PROVIDER_URL_OVERRIDE : '';
			if ( '' !== $constant_override ) {
				return $constant_override;
			}

			$settings = get_option( 'wpaic_settings', array() );
			$override = is_array( $settings ) ? ( $settings['provider_url_override'] ?? '' ) : '';

			if ( is_string( $override ) && '' !== $override ) {
				return $override;
			}
		}

		return $provider_url;
	}

	public function is_provider_url_override_allowed(): bool {
		return defined( 'WPAIC_ALLOW_PROVIDER_URL_OVERRIDE' ) && true === WPAIC_ALLOW_PROVIDER_URL_OVERRIDE;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, string>
	 */
	public function get_provider_request_headers( array $body ): array {
		if ( ! $this->is_freemius_available() ) {
			return array();
		}

		$site = wpaic_fs()->get_site();
		if ( ! is_object( $site ) ) {
			return array();
		}

		$install_id = isset( $site->id ) ? (string) $site->id : '';
		$public_key = isset( $site->public_key ) ? (string) $site->public_key : '';
		$secret_key = isset( $site->secret_key ) ? (string) $site->secret_key : '';

		if ( '' === $install_id || '' === $public_key || '' === $secret_key ) {
			return array();
		}

		$timestamp = (string) time();
		$body_hash = hash( 'sha256', (string) wp_json_encode( $body ) );
		$signature = hash_hmac(
			'sha256',
			$this->build_provider_signature_payload( $install_id, $public_key, $timestamp, $body_hash ),
			$secret_key
		);

		return array(
			'Content-Type'                 => 'application/json',
			'X-WPAIC-FS-Install-Id'        => $install_id,
			'X-WPAIC-FS-Install-Public-Key' => $public_key,
			'X-WPAIC-Timestamp'            => $timestamp,
			'X-WPAIC-Signature'            => $signature,
			'X-WPAIC-Site-Url'             => home_url( '/' ),
		);
	}

	public function get_provider_signature_ttl(): int {
		return self::PROVIDER_SIGNATURE_TTL;
	}

	public function maybe_start_trial(): void {
		if ( ! $this->is_freemius_available() ) {
			return;
		}

		$fs = wpaic_fs();

		if ( $fs->is_trial() || $fs->is_paying() || ! $fs->has_trial_plan() || $fs->is_trial_utilized() ) {
			return;
		}

		$fs->start_trial();
	}

	public function render_admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = $this->get_admin_notice();
		if ( null === $notice ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			wp_kses_post( $notice['message'] )
		);
	}

	/**
	 * @return array{type: string, message: string}|null
	 */
	public function get_admin_notice(): ?array {
		if ( ! function_exists( 'wpaic_is_freemius_configured' ) || ! wpaic_is_freemius_configured() ) {
			return array(
				'type'    => 'warning',
				'message' => __( 'WP AI Chatbot billing is not configured yet. Add the product ID and public key to enable trials, payments, and updates.', 'wp-ai-chatbot' ),
			);
		}

		if ( ! $this->is_provider_url_configured() ) {
			return array(
				'type'    => 'warning',
				'message' => __( 'WP AI Chatbot is missing its provider URL. Replace the placeholder URL before enabling chat in production.', 'wp-ai-chatbot' ),
			);
		}

		if ( ! $this->is_freemius_available() ) {
			return array(
				'type'    => 'warning',
				'message' => __( 'WP AI Chatbot could not initialize billing. Licensing, payments, and updates are unavailable until setup is complete.', 'wp-ai-chatbot' ),
			);
		}

		if ( ! $this->has_valid_chat_license() ) {
			$message = __( 'Chat is hidden on the frontend until the trial or a valid license is active.', 'wp-ai-chatbot' );
			$activation_url = $this->get_activation_url();
			$account_url    = $this->get_account_url();
			$pricing_url    = $this->get_pricing_url();

			if ( '' !== $activation_url ) {
				$message .= ' <a href="' . esc_url( $activation_url ) . '">' . esc_html__( 'Activate License', 'wp-ai-chatbot' ) . '</a>';
			}

			if ( '' !== $account_url ) {
				$message .= ' | <a href="' . esc_url( $account_url ) . '">' . esc_html__( 'Manage account', 'wp-ai-chatbot' ) . '</a>';
			}

			if ( '' !== $pricing_url ) {
				$message .= ' | <a href="' . esc_url( $pricing_url ) . '">' . esc_html__( 'View pricing', 'wp-ai-chatbot' ) . '</a>';
			}

			return array(
				'type'    => 'warning',
				'message' => $message,
			);
		}

		$trial_days_remaining = $this->get_trial_days_remaining();
		if ( null !== $trial_days_remaining && in_array( $trial_days_remaining, $this->get_trial_notice_days(), true ) ) {
			return array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %d: remaining trial days */
					__( 'Your WP AI Chatbot trial ends in %d day(s). The chat will be hidden when the trial expires.', 'wp-ai-chatbot' ),
					$trial_days_remaining
				),
			);
		}

		return null;
	}

	public function get_license_status_label(): string {
		if ( ! function_exists( 'wpaic_is_freemius_configured' ) || ! wpaic_is_freemius_configured() ) {
			return __( 'Billing setup required', 'wp-ai-chatbot' );
		}

		if ( ! $this->is_freemius_available() ) {
			return __( 'Billing unavailable', 'wp-ai-chatbot' );
		}

		$fs = wpaic_fs();
		if ( $fs->is_trial() ) {
			return __( 'Trial active', 'wp-ai-chatbot' );
		}

		if ( $fs->has_active_valid_license() ) {
			return __( 'License active', 'wp-ai-chatbot' );
		}

		return __( 'License required', 'wp-ai-chatbot' );
	}

	public function get_trial_days_remaining(): ?int {
		if ( ! $this->is_freemius_available() || ! wpaic_fs()->is_trial() ) {
			return null;
		}

		$site       = wpaic_fs()->get_site();
		$trial_ends = ( is_object( $site ) && isset( $site->trial_ends ) ) ? (string) $site->trial_ends : '';
		if ( '' === $trial_ends ) {
			return null;
		}

		$trial_timestamp = strtotime( $trial_ends );
		if ( false === $trial_timestamp ) {
			return null;
		}

		$seconds_remaining = max( 0, $trial_timestamp - time() );

		return (int) ceil( $seconds_remaining / DAY_IN_SECONDS );
	}

	public function get_account_url(): string {
		if ( ! $this->is_freemius_available() || ! method_exists( wpaic_fs(), 'get_account_url' ) ) {
			return '';
		}

		return (string) wpaic_fs()->get_account_url();
	}

	public function get_activation_url(): string {
		if ( ! $this->is_freemius_available() || ! method_exists( wpaic_fs(), 'get_activation_url' ) ) {
			return '';
		}

		return (string) wpaic_fs()->get_activation_url();
	}

	public function get_pricing_url(): string {
		if ( ! $this->is_freemius_available() || ! method_exists( wpaic_fs(), 'get_upgrade_url' ) ) {
			return '';
		}

		return (string) wpaic_fs()->get_upgrade_url();
	}

	private function build_provider_signature_payload( string $install_id, string $public_key, string $timestamp, string $body_hash ): string {
		return implode(
			"\n",
			array(
				'POST',
				'/wp-json/wpaip/v1/chat',
				$install_id,
				$public_key,
				$timestamp,
				$body_hash,
			)
		);
	}
}
