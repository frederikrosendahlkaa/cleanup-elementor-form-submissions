<?php
// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

// delete options
$option_name = 'cefs_cleanup_elementor_form_submissions_settings';
delete_option( $option_name );

// for site options in Multisite
delete_site_option( $option_name );

//remove cron job
wp_clear_scheduled_hook( 'cleanup_elementor_form_submissions_event' );