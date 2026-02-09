<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Loader {
	public function init(): void {
		$this->load_dependencies();
		$this->init_admin();
	}

	private function load_dependencies(): void {
		require_once WPAIP_PLUGIN_DIR . 'includes/class-wpaip-admin.php';
		require_once WPAIP_PLUGIN_DIR . 'includes/class-wpaip-streamer.php';
		require_once WPAIP_PLUGIN_DIR . 'includes/class-wpaip-api.php';

		$api = new WPAIP_API();
		$api->init();
	}

	private function init_admin(): void {
		if ( is_admin() ) {
			$admin = new WPAIP_Admin();
			$admin->init();
		}
	}
}
