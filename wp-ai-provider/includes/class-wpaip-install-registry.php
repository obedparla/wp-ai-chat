<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Install_Registry {
	private const OPTION_NAME = 'wpaip_install_registry';

	/**
	 * @return array<int|string, array<string, mixed>>
	 */
	private function get_registry(): array {
		$registry = get_option( self::OPTION_NAME, array() );

		return is_array( $registry ) ? $registry : array();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( int $install_id ): ?array {
		$registry = $this->get_registry();

		return isset( $registry[ $install_id ] ) && is_array( $registry[ $install_id ] ) ? $registry[ $install_id ] : null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$registry = $this->get_registry();

		$records = array_values(
			array_filter(
				$registry,
				static function ( mixed $record ): bool {
					return is_array( $record );
				}
			)
		);

		usort(
			$records,
			static function ( array $left, array $right ): int {
				$left_seen  = strtotime( (string) ( $left['last_seen_at'] ?? '' ) ) ?: 0;
				$right_seen = strtotime( (string) ( $right['last_seen_at'] ?? '' ) ) ?: 0;

				return $right_seen <=> $left_seen;
			}
		);

		return $records;
	}

	/**
	 * @param array<string, mixed> $record
	 */
	public function upsert( int $install_id, array $record ): void {
		$registry               = $this->get_registry();
		$registry[ $install_id ] = $record;
		update_option( self::OPTION_NAME, $registry );
	}
}
