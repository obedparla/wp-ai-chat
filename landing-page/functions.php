<?php
/**
 * CartScout Landing — single-purpose, speed-obsessed theme.
 * Compiled Tailwind CSS is inlined into <head>; fonts are preloaded;
 * every default WP frontend asset we don't use is removed.
 */

defined( 'ABSPATH' ) || exit;

const CARTSCOUT_VERSION = '1.0.0';

/** Strip WP frontend bloat — this theme renders one static page. */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
} );

add_action( 'wp_enqueue_scripts', function () {
	// Core styles this page never uses.
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'core-block-supports' );

	wp_enqueue_script(
		'cartscout-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array(),
		CARTSCOUT_VERSION,
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
} );

/** Preload variable fonts + inline the compiled CSS (no render-blocking stylesheet request). */
add_action( 'wp_head', function () {
	$theme_uri = get_template_directory_uri();
	foreach ( array( 'space-grotesk-var.woff2', 'archivo-var.woff2' ) as $font ) {
		printf(
			'<link rel="preload" href="%s/assets/fonts/%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( $theme_uri ),
			$font
		);
	}

	$css_file = get_template_directory() . '/assets/css/main.css';
	if ( is_readable( $css_file ) ) {
		$css = file_get_contents( $css_file );
		$css = str_replace( '__THEME_URI__', $theme_uri, $css );
		echo '<style id="cartscout-css">' . $css . '</style>' . "\n";
	}
}, 1 );
