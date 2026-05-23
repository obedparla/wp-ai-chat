<?php
/**
 * PHPStan stubs for namespaced WP-CLI helpers used by CLI commands.
 */

namespace WP_CLI\Utils;

if (!function_exists('WP_CLI\\Utils\\make_progress_bar')) {
    function make_progress_bar(string $message, int $count): \WPAIC_PHPStan_ProgressBar {
        return new \WPAIC_PHPStan_ProgressBar();
    }
}
