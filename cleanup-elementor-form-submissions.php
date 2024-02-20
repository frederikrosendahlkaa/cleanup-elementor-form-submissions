<?php
/**
 * Plugin Name: Cleanup Elementor form submissions
 * Description: Cleanup Elementor form submissions older than selected days
 * Version: 1.0.0
 * Author: Nordic Custom Made
 * Author URI: https://nordiccustommade.dk
 * Text Domain: cleanup-elementor-form-submissions
 * Domain Path: /languages
 * Requires Plugins: elementor, elementor-pro
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants.
define( 'CEFS_VERSION', '1.0.0' );
define( 'CEFS_PLUGIN_FILE', __FILE__ );
define( 'CEFS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CEFS_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CEFS_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );


require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';