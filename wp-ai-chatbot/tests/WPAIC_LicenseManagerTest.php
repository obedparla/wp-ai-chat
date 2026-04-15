<?php

use PHPUnit\Framework\TestCase;

class WPAIC_LicenseManagerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		WPAICTestHelper::set_option( 'test_freemius_configured', true );
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_get_provider_url_uses_override_when_allowed(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url_override' => 'https://staging.example.com/wp-json/wpaip/v1/chat',
			)
		);

		$manager = new WPAIC_License_Manager();

		$this->assertSame( 'https://staging.example.com/wp-json/wpaip/v1/chat', $manager->get_provider_url() );
	}

	public function test_get_provider_request_headers_builds_signed_headers(): void {
		WPAICTestHelper::set_option(
			'test_freemius_instance',
			$this->create_freemius_mock(
				array(
					'site' => (object) array(
						'id'         => 321,
						'public_key' => 'pk_test_321',
						'secret_key' => 'sk_test_321',
					),
				)
			)
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
		$manager = new WPAIC_License_Manager();
		$headers = $manager->get_provider_request_headers( $body );

		$this->assertSame( '321', $headers['X-WPAIC-FS-Install-Id'] );
		$this->assertSame( 'pk_test_321', $headers['X-WPAIC-FS-Install-Public-Key'] );
		$this->assertSame( home_url( '/' ), $headers['X-WPAIC-Site-Url'] );

		$expected_payload = implode(
			"\n",
			array(
				'POST',
				'/wp-json/wpaip/v1/chat',
				'321',
				'pk_test_321',
				$headers['X-WPAIC-Timestamp'],
				hash( 'sha256', (string) wp_json_encode( $body ) ),
			)
		);

		$this->assertSame(
			hash_hmac( 'sha256', $expected_payload, 'sk_test_321' ),
			$headers['X-WPAIC-Signature']
		);
	}

	public function test_can_render_chat_requires_license_provider_url_and_auth(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url_override' => 'https://provider.example.com/wp-json/wpaip/v1/chat',
			)
		);
		WPAICTestHelper::set_option(
			'test_freemius_instance',
			$this->create_freemius_mock(
				array(
					'license_active' => true,
					'site'           => (object) array(
						'id'         => 12,
						'public_key' => 'pk_live',
						'secret_key' => 'sk_live',
					),
				)
			)
		);

		$manager = new WPAIC_License_Manager();

		$this->assertTrue( $manager->can_render_chat() );
	}

	public function test_can_render_chat_returns_false_without_active_license(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url_override' => 'https://provider.example.com/wp-json/wpaip/v1/chat',
			)
		);
		WPAICTestHelper::set_option(
			'test_freemius_instance',
			$this->create_freemius_mock(
				array(
					'license_active' => false,
					'site'           => (object) array(
						'id'         => 12,
						'public_key' => 'pk_live',
						'secret_key' => 'sk_live',
					),
				)
			)
		);

		$manager = new WPAIC_License_Manager();

		$this->assertFalse( $manager->can_render_chat() );
	}

	public function test_maybe_start_trial_starts_trial_when_eligible(): void {
		$freemius = $this->create_freemius_mock(
			array(
				'trial'          => false,
				'license_active' => false,
				'paying'         => false,
				'has_trial_plan' => true,
				'trial_utilized' => false,
			)
		);
		WPAICTestHelper::set_option( 'test_freemius_instance', $freemius );

		$manager = new WPAIC_License_Manager();
		$manager->maybe_start_trial();

		$this->assertSame( 1, $freemius->start_trial_calls );
	}

	public function test_get_admin_notice_shows_trial_warning_three_days_before_expiry(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url_override' => 'https://provider.example.com/wp-json/wpaip/v1/chat',
			)
		);
		WPAICTestHelper::set_option(
			'test_freemius_instance',
			$this->create_freemius_mock(
				array(
					'trial' => true,
					'site'  => (object) array(
						'id'         => 88,
						'public_key' => 'pk_trial',
						'secret_key' => 'sk_trial',
						'trial_ends' => gmdate( 'Y-m-d H:i:s', time() + ( 3 * DAY_IN_SECONDS ) - 60 ),
					),
				)
			)
		);

		$manager = new WPAIC_License_Manager();
		$notice  = $manager->get_admin_notice();

		$this->assertIsArray( $notice );
		$this->assertSame( 'info', $notice['type'] );
		$this->assertStringContainsString( '3 day(s)', $notice['message'] );
	}

	public function test_get_activation_url_returns_sdk_activation_link(): void {
		WPAICTestHelper::set_option(
			'test_freemius_instance',
			$this->create_freemius_mock(
				array(
					'activation_url' => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot',
				)
			)
		);

		$manager = new WPAIC_License_Manager();

		$this->assertSame( 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot', $manager->get_activation_url() );
	}

	public function test_get_admin_notice_includes_activate_license_link_when_license_missing(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url_override' => 'https://provider.example.com/wp-json/wpaip/v1/chat',
			)
		);
		WPAICTestHelper::set_option(
			'test_freemius_instance',
			$this->create_freemius_mock(
				array(
					'license_active' => false,
					'activation_url' => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot',
					'account_url'    => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot-account',
					'upgrade_url'    => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot-pricing',
				)
			)
		);

		$manager = new WPAIC_License_Manager();
		$notice  = $manager->get_admin_notice();

		$this->assertIsArray( $notice );
		$this->assertSame( 'warning', $notice['type'] );
		$this->assertStringContainsString( 'Activate License', $notice['message'] );
		$this->assertStringContainsString( 'Manage account', $notice['message'] );
		$this->assertStringContainsString( 'View pricing', $notice['message'] );
		$this->assertStringContainsString( 'page=wp-ai-chatbot', $notice['message'] );
	}

	private function create_freemius_mock( array $overrides = array() ): object {
		$defaults = array(
			'trial'          => false,
			'license_active' => false,
			'paying'         => false,
			'has_trial_plan' => true,
			'trial_utilized' => false,
			'site'           => (object) array(
				'id'         => 1,
				'public_key' => 'pk_default',
				'secret_key' => 'sk_default',
			),
			'activation_url' => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot',
			'account_url'    => 'https://example.com/account',
			'upgrade_url'    => 'https://example.com/pricing',
		);

		return new class( array_merge( $defaults, $overrides ) ) {
			public int $start_trial_calls = 0;

			/** @var array<string, mixed> */
			private array $config;

			/**
			 * @param array<string, mixed> $config
			 */
			public function __construct( array $config ) {
				$this->config = $config;
			}

			public function is_trial(): bool {
				return (bool) $this->config['trial'];
			}

			public function has_active_valid_license(): bool {
				return (bool) $this->config['license_active'];
			}

			public function get_site(): object {
				return $this->config['site'];
			}

			public function is_paying(): bool {
				return (bool) $this->config['paying'];
			}

			public function has_trial_plan(): bool {
				return (bool) $this->config['has_trial_plan'];
			}

			public function is_trial_utilized(): bool {
				return (bool) $this->config['trial_utilized'];
			}

			public function start_trial(): void {
				++$this->start_trial_calls;
			}

			public function get_account_url(): string {
				return (string) $this->config['account_url'];
			}

			public function get_activation_url(): string {
				return (string) $this->config['activation_url'];
			}

			public function get_upgrade_url(): string {
				return (string) $this->config['upgrade_url'];
			}
		};
	}
}
