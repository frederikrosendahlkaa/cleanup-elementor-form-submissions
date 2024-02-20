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

require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';