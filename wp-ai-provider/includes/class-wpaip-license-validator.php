<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_License_Validator {
	private const GRACE_PERIOD_SECONDS = DAY_IN_SECONDS;
	private const SIGNATURE_TTL        = 300;

	private WPAIP_Freemius_API $freemius_api;
	private WPAIP_Install_Registry $registry;

	public function __construct( ?WPAIP_Freemius_API $freemius_api = null, ?WPAIP_Install_Registry $registry = null ) {
		$this->freemius_api = $freemius_api ?? new WPAIP_Freemius_API();
		$this->registry     = $registry ?? new WPAIP_Install_Registry();
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function validate_request( WP_REST_Request $request ): array|WP_Error {
		$headers = $this->extract_headers( $request );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$install_id = (int) $headers['install_id'];
		$record     = $this->registry->get( $install_id );

		if ( ! $this->freemius_api->is_configured() ) {
			return new WP_Error(
				'rest_service_unavailable',
				'Freemius API credentials are not configured on the provider.',
				array( 'status' => 503 )
			);
		}

		if ( ! $this->is_timestamp_fresh( $headers['timestamp'] ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Expired request signature.',
				array( 'status' => 403 )
			);
		}

		$install = $this->freemius_api->get_install( $install_id );
		if ( is_wp_error( $install ) ) {
			return $this->maybe_allow_grace_period( $record, $install );
		}

		if ( ! $this->is_install_active( $install ) ) {
			$this->store_invalid_record( $install_id, $record, $install, null, 'inactive_install', 'Install is not active.' );

			return new WP_Error(
				'rest_forbidden',
				'Install is no longer active.',
				array( 'status' => 403 )
			);
		}

		if ( (string) ( $install['public_key'] ?? '' ) !== $headers['public_key'] ) {
			$this->store_invalid_record( $install_id, $record, $install, null, 'public_key_mismatch', 'Install public key mismatch.' );

			return new WP_Error(
				'rest_forbidden',
				'Install public key mismatch.',
				array( 'status' => 403 )
			);
		}

		if ( ! $this->has_valid_signature( $request, $headers, $install ) ) {
			$this->store_invalid_record( $install_id, $record, $install, null, 'invalid_signature', 'Request signature check failed.' );

			return new WP_Error(
				'rest_forbidden',
				'Invalid request signature.',
				array( 'status' => 403 )
			);
		}

		if ( $this->should_allow_local_development_request( $install ) ) {
			$this->store_valid_record( $install_id, $headers, $install, null, 'local' );

			return array(
				'install_id'   => $install_id,
				'license_id'   => $install['license_id'] ?? null,
				'status'       => 'local',
				'is_grace'     => false,
				'usage_bucket' => 'fs_install_' . $install_id,
			);
		}

		$license = null;
		if ( $this->is_trial_active( $install ) ) {
			$this->store_valid_record( $install_id, $headers, $install, null, 'trial' );

			return array(
				'install_id'   => $install_id,
				'license_id'   => $install['license_id'] ?? null,
				'status'       => 'trial',
				'is_grace'     => false,
				'usage_bucket' => 'fs_install_' . $install_id,
			);
		}

		$license_id = isset( $install['license_id'] ) ? (int) $install['license_id'] : 0;
		if ( $license_id <= 0 ) {
			$this->store_invalid_record( $install_id, $record, $install, null, 'missing_license', 'Install has no active trial or license.' );

			return new WP_Error(
				'rest_forbidden',
				'Install has no active trial or license.',
				array( 'status' => 403 )
			);
		}

		$license = $this->freemius_api->get_license( $license_id );
		if ( is_wp_error( $license ) ) {
			return $this->maybe_allow_grace_period( $record, $license );
		}

		if ( ! $this->is_license_valid( $license ) ) {
			$this->store_invalid_record( $install_id, $record, $install, $license, 'license_invalid', 'License is expired, cancelled, or blocked.' );

			return new WP_Error(
				'rest_forbidden',
				'License is expired, cancelled, or blocked.',
				array( 'status' => 403 )
			);
		}

		$this->store_valid_record( $install_id, $headers, $install, $license, 'licensed' );

		return array(
			'install_id'   => $install_id,
			'license_id'   => $license_id,
			'status'       => 'licensed',
			'is_grace'     => false,
			'usage_bucket' => 'fs_install_' . $install_id,
		);
	}

	/**
	 * @return array{install_id: string, public_key: string, timestamp: string, signature: string}|WP_Error
	 */
	private function extract_headers( WP_REST_Request $request ): array|WP_Error {
		$install_id = $request->get_header( 'X-WPAIC-FS-Install-Id' );
		$public_key = $request->get_header( 'X-WPAIC-FS-Install-Public-Key' );
		$timestamp  = $request->get_header( 'X-WPAIC-Timestamp' );
		$signature  = $request->get_header( 'X-WPAIC-Signature' );

		if ( empty( $install_id ) || empty( $public_key ) || empty( $timestamp ) || empty( $signature ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Missing Freemius authentication headers.',
				array( 'status' => 403 )
			);
		}

		if ( ! ctype_digit( (string) $install_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Invalid Freemius install ID.',
				array( 'status' => 403 )
			);
		}

		return array(
			'install_id' => (string) $install_id,
			'public_key' => (string) $public_key,
			'timestamp'  => (string) $timestamp,
			'signature'  => (string) $signature,
		);
	}

	private function is_timestamp_fresh( string $timestamp ): bool {
		if ( ! ctype_digit( $timestamp ) ) {
			return false;
		}

		return abs( time() - (int) $timestamp ) <= self::SIGNATURE_TTL;
	}

	/**
	 * @param array<string, mixed> $install
	 */
	private function is_install_active( array $install ): bool {
		return ! empty( $install['is_active'] ) && empty( $install['is_uninstalled'] );
	}

	/**
	 * @param array<string, mixed> $install
	 */
	private function is_trial_active( array $install ): bool {
		$trial_ends = isset( $install['trial_ends'] ) ? (string) $install['trial_ends'] : '';
		if ( '' === $trial_ends ) {
			return false;
		}

		$trial_timestamp = strtotime( $trial_ends );

		return false !== $trial_timestamp && $trial_timestamp > time();
	}

	/**
	 * @param array<string, mixed> $license
	 */
	private function is_license_valid( array $license ): bool {
		if ( ! empty( $license['is_cancelled'] ) || ! empty( $license['is_block_features'] ) ) {
			return false;
		}

		$expiration = isset( $license['expiration'] ) ? (string) $license['expiration'] : '';
		if ( '' === $expiration ) {
			return true;
		}

		$expiration_timestamp = strtotime( $expiration );

		return false !== $expiration_timestamp && $expiration_timestamp > time();
	}

	/**
	 * @param array<string, mixed> $headers
	 * @param array<string, mixed> $install
	 */
	private function has_valid_signature( WP_REST_Request $request, array $headers, array $install ): bool {
		$secret_key = isset( $install['secret_key'] ) ? (string) $install['secret_key'] : '';
		if ( '' === $secret_key ) {
			return false;
		}

		$body_hash = hash( 'sha256', $this->get_signed_request_body( $request ) );
		$payload   = implode(
			"\n",
			array(
				'POST',
				'/wp-json/wpaip/v1/chat',
				$headers['install_id'],
				$headers['public_key'],
				$headers['timestamp'],
				$body_hash,
			)
		);

		$expected_signature = hash_hmac( 'sha256', $payload, $secret_key );

		return hash_equals( $expected_signature, $headers['signature'] );
	}

	private function get_signed_request_body( WP_REST_Request $request ): string {
		if ( method_exists( $request, 'get_body' ) ) {
			$raw_body = (string) $request->get_body();
			if ( '' !== $raw_body ) {
				return $raw_body;
			}
		}

		return (string) wp_json_encode( $this->build_signed_body( $request ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_signed_body( WP_REST_Request $request ): array {
		$body = array(
			'input'        => $request->get_param( 'input' ),
			'instructions' => $request->get_param( 'instructions' ),
		);

		$tools = $request->get_param( 'tools' );
		if ( null !== $tools ) {
			$body['tools'] = $tools;
		}

		return $body;
	}

	/**
	 * @param array<string, mixed>|null $record
	 */
	private function maybe_allow_grace_period( ?array $record, WP_Error $error ): WP_Error|array {
		$error_data = $error->get_error_data();
		$is_retryable = is_array( $error_data ) && ! empty( $error_data['retryable'] );

		if ( ! $is_retryable ) {
			return new WP_Error(
				'rest_forbidden',
				$error->get_error_message(),
				array( 'status' => 403 )
			);
		}

		if ( ! $this->can_use_grace_period( $record ) ) {
			return new WP_Error(
				'rest_service_unavailable',
				$error->get_error_message(),
				array( 'status' => 503 )
			);
		}

		return array(
			'install_id'   => (int) $record['install_id'],
			'license_id'   => $record['license_id'] ?? null,
			'status'       => 'grace',
			'is_grace'     => true,
			'usage_bucket' => 'fs_install_' . (int) $record['install_id'],
		);
	}

	/**
	 * @param array<string, mixed>|null $record
	 */
	private function can_use_grace_period( ?array $record ): bool {
		if ( ! is_array( $record ) || empty( $record['last_validated_at'] ) || empty( $record['status'] ) ) {
			return false;
		}

		if ( ! in_array( $record['status'], array( 'licensed', 'trial', 'local' ), true ) ) {
			return false;
		}

		$validated_at = strtotime( (string) $record['last_validated_at'] );
		if ( false === $validated_at ) {
			return false;
		}

		return ( time() - $validated_at ) <= self::GRACE_PERIOD_SECONDS;
	}

	/**
	 * @param array<string, mixed> $headers
	 * @param array<string, mixed> $install
	 * @param array<string, mixed>|null $license
	 */
	private function store_valid_record( int $install_id, array $headers, array $install, ?array $license, string $status ): void {
		$now = gmdate( 'Y-m-d H:i:s' );

		$this->registry->upsert(
			$install_id,
			array(
				'install_id'         => $install_id,
				'license_id'         => isset( $install['license_id'] ) ? (int) $install['license_id'] : null,
				'site_public_key'    => $headers['public_key'],
				'site_url'           => (string) ( $install['url'] ?? '' ),
				'plan_id'            => isset( $install['plan_id'] ) ? (int) $install['plan_id'] : null,
				'status'             => $status,
				'usage_bucket_key'   => 'fs_install_' . $install_id,
				'last_validated_at'  => $now,
				'last_seen_at'       => $now,
				'grace_expires_at'   => gmdate( 'Y-m-d H:i:s', time() + self::GRACE_PERIOD_SECONDS ),
				'last_error_code'    => '',
				'last_error_message' => '',
				'is_trial'           => 'trial' === $status,
				'is_local'           => $this->is_local_url( (string) ( $install['url'] ?? '' ) ),
				'license_snapshot'   => is_array( $license ) ? $license : array(),
			)
		);
	}

	/**
	 * @param array<string, mixed>|null $existing_record
	 * @param array<string, mixed> $install
	 * @param array<string, mixed>|null $license
	 */
	private function store_invalid_record( int $install_id, ?array $existing_record, array $install, ?array $license, string $error_code, string $error_message ): void {
		$previous = is_array( $existing_record ) ? $existing_record : array();

		$this->registry->upsert(
			$install_id,
			array_merge(
				$previous,
				array(
					'install_id'         => $install_id,
					'license_id'         => isset( $install['license_id'] ) ? (int) $install['license_id'] : null,
					'site_public_key'    => (string) ( $install['public_key'] ?? '' ),
					'site_url'           => (string) ( $install['url'] ?? '' ),
					'plan_id'            => isset( $install['plan_id'] ) ? (int) $install['plan_id'] : null,
					'status'             => 'invalid',
					'usage_bucket_key'   => 'fs_install_' . $install_id,
					'last_seen_at'       => gmdate( 'Y-m-d H:i:s' ),
					'grace_expires_at'   => '',
					'last_error_code'    => $error_code,
					'last_error_message' => $error_message,
					'is_trial'           => false,
					'is_local'           => $this->is_local_url( (string) ( $install['url'] ?? '' ) ),
					'license_snapshot'   => is_array( $license ) ? $license : array(),
				)
			)
		);
	}

	private function is_local_url( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $host ) {
			return false;
		}

		return 'localhost' === $host
			|| str_ends_with( $host, '.local' )
			|| str_ends_with( $host, '.test' )
			|| str_ends_with( $host, '.staging' )
			|| str_starts_with( $host, 'dev.' )
			|| str_starts_with( $host, 'staging.' );
	}

	/**
	 * Local installs should keep working against the local provider even when
	 * the remote license state is missing or stale.
	 *
	 * @param array<string, mixed> $install
	 */
	protected function should_allow_local_development_request( array $install ): bool {
		return $this->is_local_environment()
			&& $this->is_local_url( (string) ( $install['url'] ?? '' ) );
	}

	protected function is_local_environment(): bool {
		return defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === (string) WP_ENVIRONMENT_TYPE;
	}
}
