<?php
/**
 * Plugin Name: Elementor Cleanup Form Entries
 * Description: Cleanup Elementor Form Entries older than selected days | Default 30 days | Runs every 1 hour.
 * Version: 1.0.0
 * Author: Nordic Custom Made
 * Author URI: https://nordiccustommade.dk
 * Text Domain: ncm-elementor-cleanup-form-entries
 * Domain Path: /languages
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create a new cron schedule.
 * Run every 1 hour.
 */
function ncm_elementor_cleanup_form_entries_cron_schedule( $schedules ) {
    $schedules['every_one_hour'] = array(
        'interval' => 3600,
        'display'  => esc_html__( 'Every 1 hour', 'ncm-elementor-cleanup-form-entries' ),
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'ncm_elementor_cleanup_form_entries_cron_schedule' );

/**
 * Schedule an action if it's not already scheduled.
 */
function ncm_elementor_cleanup_form_entries_schedule_event() {
    if ( ! wp_next_scheduled( 'ncm_elementor_cleanup_form_entries_event' ) ) {
        wp_schedule_event( time(), 'every_one_hour', 'ncm_elementor_cleanup_form_entries_event' );
    }
}
add_action( 'wp', 'ncm_elementor_cleanup_form_entries_schedule_event' );

/**
 * When plugin is installed, activate or update set option ncm_elementor_cleanup_form_entries_settings to 30 days and schedule the cron event.
 */
function ncm_elementor_cleanup_form_entries_activation() {
    $options = get_option( 'ncm_elementor_cleanup_form_entries_settings' );
    if ( ! $options ) {
        $options = array(
            'days' => 30,
        );
        update_option( 'ncm_elementor_cleanup_form_entries_settings', $options );
    }
    ncm_elementor_cleanup_form_entries_schedule_event();
}
register_activation_hook( __FILE__, 'ncm_elementor_cleanup_form_entries_activation' );

/**
 * if plugin deactivated or uninstalled, clear the cron event.
 */
function ncm_elementor_cleanup_form_entries_deactivation() {
    wp_clear_scheduled_hook( 'ncm_elementor_cleanup_form_entries_event' );
    // delete option ncm_elementor_cleanup_form_entries_settings
    delete_option( 'ncm_elementor_cleanup_form_entries_settings' );
}
register_deactivation_hook( __FILE__, 'ncm_elementor_cleanup_form_entries_deactivation' );

/**
 * Delete all form entries older than 30 days.
 */
function ncm_elementor_cleanup_form_entries_delete_entries() {
    // if elementor or elementor pro is not active, don't proceed.
    if ( ! did_action( 'elementor/loaded' ) ) {
        return;
    }

    /**
     * Delete from prefix.e_submissions 
     * Delete prefix.e_submissions_actions_log submition_id
     * Delete prefix.e_submissions_values submition_id
     * ncm_elementor_cleanup_form_entries_settings days 
     */
    $days = 30;
    $options = get_option( 'ncm_elementor_cleanup_form_entries_settings' );
    if ( $options AND isset( $options['days'] ) ) {
        $days = $options['days'];
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'e_submissions';
    //get list of ids of form entries older than selected days.
    $sql = "SELECT id FROM $table_name WHERE created_at < DATE_SUB( NOW(), INTERVAL $days DAY )";
    $ids = $wpdb->get_col( $sql );
    
    //delete form entries older than selected days.
    $sql = "DELETE FROM $table_name WHERE id IN ( " . implode( ',', $ids ) . " )";
    $wpdb->query( $sql );

    //delete from prefix.e_submissions_actions_log submition_id
    $table_name = $wpdb->prefix . 'e_submissions_actions_log';
    $sql = "DELETE FROM $table_name WHERE submission_id IN ( " . implode( ',', $ids ) . " )";
    $wpdb->query( $sql );

    //delete from prefix.e_submissions_values submition_id
    $table_name = $wpdb->prefix . 'e_submissions_values';
    $sql = "DELETE FROM $table_name WHERE submission_id IN ( " . implode( ',', $ids ) . " )";
    $wpdb->query( $sql );

}
add_action( 'ncm_elementor_cleanup_form_entries_event', 'ncm_elementor_cleanup_form_entries_delete_entries' );


/**
 * add a submenu page under the "tolls" admin menu.
 * This page will be used to manually delete all form entries and select the number of days to keep.
 */
function ncm_elementor_cleanup_form_entries_menu() {
    add_management_page(
        esc_html__( 'Cleanup Elementor Form Entries', 'ncm-elementor-cleanup-form-entries' ),
        esc_html__( 'Cleanup Elementor Form Entries', 'ncm-elementor-cleanup-form-entries' ),
        'manage_options',
        'ncm-elementor-cleanup-form-entries',
        'ncm_elementor_cleanup_form_entries_page'
    );
}
add_action( 'admin_menu', 'ncm_elementor_cleanup_form_entries_menu' );

/**
 * add a link to the plugin settings page.
 */
function ncm_elementor_cleanup_form_entries_plugin_action_links( $links ) {
    $links[] = '<a href="' . esc_url( admin_url( 'tools.php?page=ncm-elementor-cleanup-form-entries' ) ) . '">' . esc_html__( 'Settings', 'ncm-elementor-cleanup-form-entries' ) . '</a>';
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ncm_elementor_cleanup_form_entries_plugin_action_links' );

/**
 * Cleanup Form Entries page.
 */
function ncm_elementor_cleanup_form_entries_page () {

    // if elementor or elementor pro is not active, don't proceed.
    if ( ! did_action( 'elementor/loaded' ) ) {
        return;
    }

    // if user is not admin, don't proceed.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    //add page title
    echo '<h1>' . esc_html__( 'Cleanup Elementor Form Entries', 'ncm-elementor-cleanup-form-entries' ) . '</h1>';

    //add form to select the number of days to keep.
    ?>
    <form action="options.php" method="post">
        <?php
        settings_fields( 'ncm_elementor_cleanup_form_entries_settings' );
        do_settings_sections( 'ncm_elementor_cleanup_form_entries_settings' );
        submit_button();
        ?>
    </form>
    <?php   

    //add button to manually delete all form entries older than selected days.
    $options = get_option( 'ncm_elementor_cleanup_form_entries_settings' );
    $days = isset( $options['days'] ) ? $options['days'] : 30;
    ?>
    <form action="" method="post">
        <input type="hidden" name="ncm_elementor_cleanup_form_entries_nonce" value="<?php echo wp_create_nonce( 'ncm_elementor_cleanup_form_entries_nonce' ); ?>" />
        <input class="button button-primary" type="submit" name="ncm_elementor_cleanup_form_entries_delete" value="<?php echo __( 'Delete all form entries older then', 'ncm-elementor-cleanup-form-entries' ).' '.$days.' '.__( 'days', 'ncm-elementor-cleanup-form-entries' ); ?>" />
    </form>
    <?php

    // if button is clicked, delete all form entries older than selected days.
    if ( isset( $_POST['ncm_elementor_cleanup_form_entries_delete'] ) && check_admin_referer( 'ncm_elementor_cleanup_form_entries_nonce', 'ncm_elementor_cleanup_form_entries_nonce' ) ) {
        ncm_elementor_cleanup_form_entries_delete_entries();
        echo '<p>' . esc_html__( 'All form entries older than selected days have been deleted.', 'ncm-elementor-cleanup-form-entries' ) . '</p>';
    }
}

/**
 * Register settings.
 */
function ncm_elementor_cleanup_form_entries_settings() {
    register_setting( 'ncm_elementor_cleanup_form_entries_settings', 'ncm_elementor_cleanup_form_entries_settings' );
    add_settings_section( 'ncm_elementor_cleanup_form_entries_settings_section', '', '', 'ncm_elementor_cleanup_form_entries_settings' );
    add_settings_field( 'ncm_elementor_cleanup_form_entries_settings_field', esc_html__( 'Number of days to keep', 'ncm-elementor-cleanup-form-entries' ), 'ncm_elementor_cleanup_form_entries_settings_field', 'ncm_elementor_cleanup_form_entries_settings', 'ncm_elementor_cleanup_form_entries_settings_section' );
}
add_action( 'admin_init', 'ncm_elementor_cleanup_form_entries_settings' );

/**
 * Settings field.
 */
function ncm_elementor_cleanup_form_entries_settings_field() {
    $options = get_option( 'ncm_elementor_cleanup_form_entries_settings' );
    $days = isset( $options['days'] ) ? $options['days'] : 30;
    ?>
    <input type="number" name="ncm_elementor_cleanup_form_entries_settings[days]" value="<?php echo esc_attr( $days ); ?>" min="1" max="365" />
    <?php
}