<?php

use PHPUnit\Framework\TestCase;

class WPAIP_LicenseValidatorTest extends TestCase {
	private WPAIP_Install_Registry $registry;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_options']    = array();
		$GLOBALS['wp_actions']    = array();
		$GLOBALS['wp_transients'] = array();
		$this->registry           = new WPAIP_Install_Registry();
	}

	protected function tearDown(): void {
		$GLOBALS['wp_options']    = array();
		$GLOBALS['wp_actions']    = array();
		$GLOBALS['wp_transients'] = array();
		parent::tearDown();
	}

	public function test_validate_request_accepts_trial_signature_and_persists_registry(): void {
		$install = array(
			'id'             => 123,
			'public_key'     => 'pk_install_123',
			'secret_key'     => 'sk_install_123',
			'url'            => 'https://store.example.com',
			'plan_id'        => 44,
			'license_id'     => 0,
			'trial_ends'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'is_active'      => true,
			'is_uninstalled' => false,
		);
		$body    = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
		);

		$validator = new WPAIP_License_Validator(
			$this->create_freemius_api_mock(
				array(
					'install' => $install,
				)
			),
			$this->registry
		);

		$result = $validator->validate_request( $this->build_signed_request( $install, $body ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'trial', $result['status'] );
		$this->assertFalse( $result['is_grace'] );
		$this->assertSame( 'fs_install_123', $result['usage_bucket'] );

		$record = $this->registry->get( 123 );
		$this->assertIsArray( $record );
		$this->assertSame( 'trial', $record['status'] );
		$this->assertTrue( $record['is_trial'] );
		$this->assertSame( 'pk_install_123', $record['site_public_key'] );
		// Persisted so grace-period requests can be HMAC-verified during
		// Freemius outages.
		$this->assertSame( 'sk_install_123', $record['site_secret_key'] );
	}

	public function test_validate_request_rejects_invalid_signature_and_marks_record_invalid(): void {
		$install = array(
			'id'             => 200,
			'public_key'     => 'pk_install_200',
			'secret_key'     => 'sk_install_200',
			'url'            => 'https://store.example.com',
			'plan_id'        => 44,
			'license_id'     => 0,
			'trial_ends'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'is_active'      => true,
			'is_uninstalled' => false,
		);
		$body    = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
		);
		$request = $this->build_signed_request( $install, $body, 'invalid-signature' );

		$result = ( new WPAIP_License_Validator(
			$this->create_freemius_api_mock(
				array(
					'install' => $install,
				)
			),
			$this->registry
		) )->validate_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( 'Invalid request signature.', $result->get_error_message() );

		$record = $this->registry->get( 200 );
		$this->assertIsArray( $record );
		$this->assertSame( 'invalid', $record['status'] );
		$this->assertSame( 'invalid_signature', $record['last_error_code'] );
	}

	public function test_validate_request_uses_raw_body_for_signature_when_params_are_normalized(): void {
		$install = array(
			'id'             => 210,
			'public_key'     => 'pk_install_210',
			'secret_key'     => 'sk_install_210',
			'url'            => 'https://store.example.com',
			'plan_id'        => 44,
			'license_id'     => 0,
			'trial_ends'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'is_active'      => true,
			'is_uninstalled' => false,
		);
		$body    = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
			'tools'    => array(
				array(
					'type'     => 'function',
					'function' => array(
						'name'       => 'get_categories',
						'parameters' => array(
							'type'       => 'object',
							'properties' => new stdClass(),
						),
					),
				),
			),
		);
		$request = $this->build_signed_request( $install, $body );
		$request->set_params(
			array(
				'messages' => $body['messages'],
				'model'    => $body['model'],
				'tools'    => array(
					array(
						'type'     => 'function',
						'function' => array(
							'name'       => 'get_categories',
							'parameters' => array(
								'type'       => 'object',
								'properties' => array(),
							),
						),
					),
				),
			)
		);

		$result = ( new WPAIP_License_Validator(
			$this->create_freemius_api_mock(
				array(
					'install' => $install,
				)
			),
			$this->registry
		) )->validate_request( $request );

		$this->assertIsArray( $result );
		$this->assertSame( 'trial', $result['status'] );
	}

	public function test_validate_request_allows_grace_period_for_retryable_failures_after_recent_validation(): void {
		$this->registry->upsert(
			55,
			array(
				'install_id'        => 55,
				'license_id'        => 77,
				'status'            => 'licensed',
				'site_public_key'   => 'pk_install_55',
				'site_secret_key'   => 'sk_install_55',
				'last_validated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
			)
		);

		$install_identity = array(
			'id'         => 55,
			'public_key' => 'pk_install_55',
			'secret_key' => 'sk_install_55',
		);
		$body             = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
		);

		$result = ( new WPAIP_License_Validator(
			$this->create_freemius_api_mock(
				array(
					'install_error' => new WP_Error(
						'freemius_request_failed',
						'Freemius is temporarily unavailable.',
						array( 'retryable' => true )
					),
				)
			),
			$this->registry
		) )->validate_request( $this->build_signed_request( $install_identity, $body ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'grace', $result['status'] );
		$this->assertTrue( $result['is_grace'] );
		$this->assertSame( 'fs_install_55', $result['usage_bucket'] );
	}

	// S6: grace must require an HMAC with the stored install secret — a
	// replayed plaintext public key header alone is never enough.
	public function test_validate_request_denies_grace_period_when_replayed_headers_lack_valid_signature(): void {
		$this->registry->upsert(
			56,
			array(
				'install_id'        => 56,
				'license_id'        => 78,
				'status'            => 'licensed',
				'site_public_key'   => 'pk_install_56',
				'site_secret_key'   => 'sk_install_56',
				'last_validated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
			)
		);

		// Attacker saw the real public key on the wire but cannot sign with
		// the install secret key.
		$install_identity = array(
			'id'         => 56,
			'public_key' => 'pk_install_56',
			'secret_key' => 'sk_attacker_guess',
		);
		$body             = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
		);

		$result = ( new WPAIP_License_Validator(
			$this->create_freemius_api_mock(
				array(
					'install_error' => new WP_Error(
						'freemius_request_failed',
						'Freemius is temporarily unavailable.',
						array( 'retryable' => true )
					),
				)
			),
			$this->registry
		) )->validate_request( $this->build_signed_request( $install_identity, $body ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( 'Invalid request signature.', $result->get_error_message() );
	}

	public function test_validate_request_denies_grace_period_when_record_has_no_stored_secret_key(): void {
		$this->registry->upsert(
			57,
			array(
				'install_id'        => 57,
				'license_id'        => 79,
				'status'            => 'licensed',
				'site_public_key'   => 'pk_install_57',
				'last_validated_at' => gmdate( 'Y-m-d H:i:s', time() - 300 ),
			)
		);

		$install_identity = array(
			'id'         => 57,
			'public_key' => 'pk_install_57',
			'secret_key' => 'sk_install_57',
		);
		$body             = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
		);

		$result = ( new WPAIP_License_Validator(
			$this->create_freemius_api_mock(
				array(
					'install_error' => new WP_Error(
						'freemius_request_failed',
						'Freemius is temporarily unavailable.',
						array( 'retryable' => true )
					),
				)
			),
			$this->registry
		) )->validate_request( $this->build_signed_request( $install_identity, $body ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( 'Invalid request signature.', $result->get_error_message() );
	}

	public function test_validate_request_rejects_license_with_blocked_features(): void {
		$install = array(
			'id'             => 300,
			'public_key'     => 'pk_install_300',
			'secret_key'     => 'sk_install_300',
			'url'            => 'https://store.example.com',
			'plan_id'        => 55,
			'license_id'     => 987,
			'trial_ends'     => '',
			'is_active'      => true,
			'is_uninstalled' => false,
		);
		$license = array(
			'id'                => 987,
			'is_cancelled'      => false,
			'is_block_features' => true,
			'expiration'        => '',
		);
		$body    = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
		);

		$result = ( new WPAIP_License_Validator(
			$this->create_freemius_api_mock(
				array(
					'install' => $install,
					'license' => $license,
				)
			),
			$this->registry
		) )->validate_request( $this->build_signed_request( $install, $body ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
		$this->assertSame( 'License is expired, cancelled, or blocked.', $result->get_error_message() );

		$record = $this->registry->get( 300 );
		$this->assertIsArray( $record );
		$this->assertSame( 'invalid', $record['status'] );
		$this->assertSame( 'license_invalid', $record['last_error_code'] );
	}

	public function test_validate_request_allows_local_install_without_valid_license_on_local_provider(): void {
		$install = array(
			'id'             => 301,
			'public_key'     => 'pk_install_301',
			'secret_key'     => 'sk_install_301',
			'url'            => 'http://store.local',
			'plan_id'        => 55,
			'license_id'     => 987,
			'trial_ends'     => '',
			'is_active'      => true,
			'is_uninstalled' => false,
		);
		$body    = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => 'Hello',
				),
			),
			'model'    => 'gpt-5',
		);

		$validator = new class(
			$this->create_freemius_api_mock(
				array(
					'install' => $install,
					'license' => array(
						'id'                => 987,
						'is_cancelled'      => false,
						'is_block_features' => true,
						'expiration'        => '',
					),
				)
			),
			$this->registry
		) extends WPAIP_License_Validator {
			protected function is_local_environment(): bool {
				return true;
			}
		};

		$result = $validator->validate_request( $this->build_signed_request( $install, $body ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'local', $result['status'] );
		$this->assertFalse( $result['is_grace'] );

		$record = $this->registry->get( 301 );
		$this->assertIsArray( $record );
		$this->assertSame( 'local', $record['status'] );
		$this->assertTrue( $record['is_local'] );
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function create_freemius_api_mock( array $config ): WPAIP_Freemius_API {
		$install       = $config['install'] ?? array();
		$license       = $config['license'] ?? array();
		$install_error = $config['install_error'] ?? null;
		$license_error = $config['license_error'] ?? null;

		return new class( $install, $license, $install_error, $license_error ) extends WPAIP_Freemius_API {
			/** @var array<string, mixed> */
			private array $install;

			/** @var array<string, mixed> */
			private array $license;

			private ?WP_Error $install_error;
			private ?WP_Error $license_error;

			/**
			 * @param array<string, mixed> $install
			 * @param array<string, mixed> $license
			 */
			public function __construct( array $install, array $license, ?WP_Error $install_error, ?WP_Error $license_error ) {
				$this->install       = $install;
				$this->license       = $license;
				$this->install_error = $install_error;
				$this->license_error = $license_error;
			}

			public function is_configured(): bool {
				return true;
			}

			public function get_install( int $install_id, bool $force = false ): array|WP_Error {
				if ( $this->install_error instanceof WP_Error ) {
					return $this->install_error;
				}

				return $this->install;
			}

			public function get_license( int $license_id, bool $force = false ): array|WP_Error {
				if ( $this->license_error instanceof WP_Error ) {
					return $this->license_error;
				}

				return $this->license;
			}
		};
	}

	/**
	 * @param array<string, mixed> $install
	 * @param array<string, mixed> $body
	 */
	private function build_signed_request( array $install, array $body, ?string $signature = null ): WP_REST_Request {
		$request   = new WP_REST_Request();
		$timestamp = (string) time();

		$request->set_params( $body );
		$request->set_body( (string) wp_json_encode( $body ) );
		$request->set_header( 'X-WPAIC-FS-Install-Id', (string) $install['id'] );
		$request->set_header( 'X-WPAIC-FS-Install-Public-Key', (string) $install['public_key'] );
		$request->set_header( 'X-WPAIC-Timestamp', $timestamp );
		$request->set_header(
			'X-WPAIC-Signature',
			$signature ?? $this->sign_request( $install, $body, $timestamp )
		);

		return $request;
	}

	/**
	 * @param array<string, mixed> $install
	 * @param array<string, mixed> $body
	 */
	private function sign_request( array $install, array $body, string $timestamp ): string {
		$payload = implode(
			"\n",
			array(
				'POST',
				'/wp-json/wpaip/v1/chat',
				(string) $install['id'],
				(string) $install['public_key'],
				$timestamp,
				hash( 'sha256', (string) wp_json_encode( $body ) ),
			)
		);

		return hash_hmac( 'sha256', $payload, (string) $install['secret_key'] );
	}
}
