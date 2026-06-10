<?php
/**
 * Tests for WPAIC_System_Prompt.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-page-context.php';
require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
require_once __DIR__ . '/../includes/class-wpaic-system-prompt.php';

class WPAIC_SystemPromptTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
	}

	/** @param array<string, mixed> $settings */
	private function invoke_private( array $settings, string $method_name, mixed ...$args ): mixed {
		$system_prompt = new WPAIC_System_Prompt( $settings );
		$method        = new ReflectionMethod( $system_prompt, $method_name );
		$method->setAccessible( true );

		return $method->invoke( $system_prompt, ...$args );
	}

	private function get_rule_constant( string $name ): string {
		$constant = new ReflectionClassConstant( WPAIC_System_Prompt::class, $name );

		return $constant->getValue();
	}

	/**
	 * The fixture stores one rule per line so a deliberate rule edit diffs as a
	 * single changed line. The assembled instruction is the rules joined with
	 * single spaces behind a leading space, mirroring
	 * get_tool_response_instruction() (rules are single-line constants — the
	 * no-outer-whitespace test below keeps the join lossless).
	 */
	private function load_fixture(): string {
		$rules = file( __DIR__ . '/fixtures/tool-response-instruction.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		return ' ' . implode( ' ', $rules );
	}

	/**
	 * Characterization test: the tool-response instruction was captured to the
	 * fixture before being split into per-rule constants, and the live-verified
	 * prompt must stay byte-identical. The fixture holds the promotions-enabled
	 * variant (get_active_promotions was unconditional at capture time). When a
	 * rule is deliberately edited, regenerate the fixture by writing the joined
	 * rule constants one per line, in get_tool_response_instruction() order.
	 */
	public function test_tool_response_instruction_matches_captured_fixture_when_promotions_enabled(): void {
		$this->assertSame(
			$this->load_fixture(),
			$this->invoke_private( array( 'promotions_enabled' => true ), 'get_tool_response_instruction' )
		);
	}

	/**
	 * With promotions off (the default), only the discounts rule may differ from
	 * the captured fixture — every other rule must stay byte-identical.
	 */
	public function test_tool_response_instruction_swaps_only_discounts_rule_when_promotions_disabled(): void {
		$fixture  = $this->load_fixture();
		$expected = str_replace(
			$this->get_rule_constant( 'RULE_DISCOUNTS_PROMOTIONS' ),
			$this->get_rule_constant( 'RULE_DISCOUNTS_PROMOTIONS_DISABLED' ),
			$fixture
		);

		$this->assertNotSame( $fixture, $expected );
		$this->assertSame(
			$expected,
			$this->invoke_private( array(), 'get_tool_response_instruction' )
		);
	}

	/**
	 * The assembler joins rules with single spaces, so a rule constant carrying
	 * its own leading/trailing whitespace would silently double a separator.
	 */
	public function test_tool_response_rule_constants_carry_no_outer_whitespace(): void {
		$reflection = new ReflectionClass( WPAIC_System_Prompt::class );

		$rule_constants = array_filter(
			$reflection->getConstants(),
			static fn ( string $name ): bool => str_starts_with( $name, 'RULE_' ),
			ARRAY_FILTER_USE_KEY
		);

		$this->assertNotEmpty( $rule_constants );

		foreach ( $rule_constants as $name => $rule ) {
			$this->assertIsString( $rule, "{$name} must be a string" );
			$this->assertSame( trim( $rule ), $rule, "{$name} must not carry leading/trailing whitespace" );
			$this->assertNotSame( '', $rule, "{$name} must not be empty" );
		}
	}
}
