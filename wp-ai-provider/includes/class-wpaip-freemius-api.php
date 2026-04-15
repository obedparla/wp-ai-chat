<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Freemius_API {
	private const API_BASE   = 'https://api.freemius.com/v1';
	private const CACHE_TTL  = 300;

	/** @var callable|null */
	private $request_handler;

	public function __construct( ?callable $request_handler = null ) {
		$this->request_handler = $request_handler;
	}

	public function is_configured(): bool {
		$credentials = $this->get_credentials();
		$product_id  = $credentials['product_id'];
		$api_token   = $credentials['api_token'];

		return $product_id > 0 && '' !== $api_token;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_install( int $install_id, bool $force = false ): array|WP_Error {
		return $this->request(
			'install',
			$install_id,
			"/installs/{$install_id}.json?fields=id,site_id,public_key,secret_key,url,title,plan_id,license_id,trial_plan_id,trial_ends,is_active,is_uninstalled"
		,
			$force
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_license( int $license_id, bool $force = false ): array|WP_Error {
		return $this->request(
			'license',
			$license_id,
			"/licenses/{$license_id}.json?fields=id,plan_id,quota,activated,activated_local,expiration,is_cancelled,is_block_features"
		,
			$force
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function request( string $type, int $entity_id, string $path, bool $force ): array|WP_Error {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'freemius_not_configured',
				'Freemius API credentials are not configured.',
				array( 'status' => 503, 'retryable' => false )
			);
		}

		$cache_key = "wpaip_fs_{$type}_{$entity_id}";
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$credentials = $this->get_credentials();
		$product_id  = $credentials['product_id'];
		$api_token   = $credentials['api_token'];

		$url = trailingslashit( self::API_BASE ) . "products/{$product_id}" . $path;

		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
				'Accept'        => 'application/json',
			),
		);

		$response = is_callable( $this->request_handler )
			? call_user_func( $this->request_handler, $url, $args )
			: wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'freemius_request_failed',
				$response->get_error_message(),
				array( 'status' => 503, 'retryable' => true )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 500 ) {
			return new WP_Error(
				'freemius_server_error',
				'Freemius is temporarily unavailable.',
				array( 'status' => 503, 'retryable' => true )
			);
		}

		if ( $status_code >= 400 ) {
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? (string) $data['error']['message'] : 'Freemius rejected the request.';

			return new WP_Error(
				'freemius_invalid_response',
				$message,
				array( 'status' => 403, 'retryable' => false )
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'freemius_invalid_json',
				'Freemius returned an invalid response.',
				array( 'status' => 503, 'retryable' => true )
			);
		}

		set_transient( $cache_key, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * @return array{product_id: int, api_token: string}
	 */
	private function get_credentials(): array {
		$settings = get_option( 'wpaip_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$product_id = isset( $settings['freemius_product_id'] ) ? (int) $settings['freemius_product_id'] : 0;
		if ( $product_id <= 0 && defined( 'WPAIP_FREEMIUS_PRODUCT_ID' ) ) {
			$product_id = (int) WPAIP_FREEMIUS_PRODUCT_ID;
		}

		$api_token = isset( $settings['freemius_api_token'] ) ? (string) $settings['freemius_api_token'] : '';
		if ( '' === $api_token && defined( 'WPAIP_FREEMIUS_API_TOKEN' ) ) {
			$api_token = (string) WPAIP_FREEMIUS_API_TOKEN;
		}

		return array(
			'product_id' => $product_id,
			'api_token'  => $api_token,
		);
	}
}
