<?php
/**
 * Plugin Name: Carbon Fields UI
 * Description: Visual field builder for Carbon Fields. Create custom fields for post types, page templates, taxonomies, user profiles and options pages from the WordPress admin — no code required.
 * Version: 1.0.0
 * Author: Your Site
 * Text Domain: carbon-fields-ui
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * License: GPL v2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'CFUI_PATH', plugin_dir_path( __FILE__ ) );
define( 'CFUI_URL', plugin_dir_url( __FILE__ ) );
define( 'CFUI_VERSION', '1.0.0' );

$cfui_autoload = CFUI_PATH . 'vendor/autoload.php';

if ( ! file_exists( $cfui_autoload ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Carbon Fields UI: the bundled Carbon Fields library is missing. Re-install the plugin.', 'carbon-fields-ui' ) . '</p></div>';
	} );
	return;
}

require_once $cfui_autoload;

/**
 * Carbon Fields expects to boot on after_setup_theme, matching its own
 * bootstrap example, so hooks/assets it registers internally fire on time.
 */
add_action( 'after_setup_theme', function () {
	\Carbon_Fields\Carbon_Fields::boot();
} );

require_once CFUI_PATH . 'includes/class-field-types.php';
require_once CFUI_PATH . 'includes/class-field-group-post-type.php';
require_once CFUI_PATH . 'includes/class-field-group-renderer.php';

add_action( 'plugins_loaded', function () {
	\CFUI\Field_Group_Post_Type::init();
	\CFUI\Field_Group_Renderer::init();
} );
