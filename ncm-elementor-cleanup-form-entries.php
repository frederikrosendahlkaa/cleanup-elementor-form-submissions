<?php
/**
 * Plugin Name: Elementor Cleanup Form Entries
 * Description: Cleanup Elementor Form Entries older than selected days | Default 30 days | Runs every 1 hour.
 * Version: 1.0.0
 * Author: Nordic Custom Made
 * Author URI: https://nordiccustommade.dk
 * Text Domain: ncm-elementor-cleanup-form-entries
 * Domain Path: /languages
 * Requires Plugins: elementor
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

//create class ncm_elementor_cleanup_form_entries
class ncm_elementor_cleanup_form_entries {
    
    
    public function __construct() {

        register_activation_hook( __FILE__, array( $this, 'plugins_activation' ) );

        register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );

        add_action( 'init', array( $this, 'load_textdomain' ) );

        add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

        add_action( 'admin_init', array( $this, 'cleanup_form_entries_settings' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

        add_action( 'wp', array( $this, 'entries_schedule_event' ) );

        add_action( 'ncm_elementor_cleanup_form_entries_event', array( $this, 'entries_delete_entries' ) );

    }

    //activate plugin
    public function plugins_activation() {
        $options = get_option( 'ncm_elementor_cleanup_form_entries_settings' );
        if ( ! $options ) {
            $options = array(
                'days' => 30,
            );
            update_option( 'ncm_elementor_cleanup_form_entries_settings', $options );
        }
        $this->entries_schedule_event();
    }

    //deactivate plugin
    public function plugin_deactivation() {
        wp_clear_scheduled_hook( 'ncm_elementor_cleanup_form_entries_event' );
        // delete option ncm_elementor_cleanup_form_entries_settings
        delete_option( 'ncm_elementor_cleanup_form_entries_settings' );
    }

    //load plugin textdomain
    public function load_textdomain() {
        load_plugin_textdomain( 'ncm-elementor-cleanup-form-entries', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    //add a link to the plugin settings page.
    public function plugin_action_links( $links ) {
        $links[] = '<a href="' . esc_url( admin_url( 'tools.php?page=ncm-elementor-cleanup-form-entries' ) ) . '">' . esc_html__( 'Settings', 'ncm-elementor-cleanup-form-entries' ) . '</a>';
        return $links;
    }

    /**
     * add a submenu page under the "tools" admin menu.
     * This page will be used to manually delete all form entries and select the number of days to keep.
     */
    public function add_menu_item() {
        add_management_page(
            esc_html__( 'Cleanup Elementor Form Entries', 'ncm-elementor-cleanup-form-entries' ),
            esc_html__( 'Cleanup Elementor Form Entries', 'ncm-elementor-cleanup-form-entries' ),
            'manage_options',
            'ncm-elementor-cleanup-form-entries',
            array( $this, 'cleanup_form_entries_page' )
        );
    }

    /**
     * Cleanup Form Entries page.
     */
    public function cleanup_form_entries_page () {

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
            settings_fields( 'cleanup_form_entries_settings' );
            do_settings_sections( 'cleanup_form_entries_settings' );
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
            // ncm_elementor_cleanup_form_entries_delete_entries();
            echo '<p>' . esc_html__( 'All form entries older than selected days have been deleted.', 'ncm-elementor-cleanup-form-entries' ) . '</p>';
        }
    }

    /**
     * Register settings.
     */
    public function cleanup_form_entries_settings() {
        register_setting( 'cleanup_form_entries_settings', 'cleanup_form_entries_settings' );
        add_settings_section( 'cleanup_form_entries_settings_section', '', '', 'cleanup_form_entries_settings' );
        add_settings_field( 'cleanup_form_entries_settings_field', esc_html__( 'Number of days to keep', 'ncm-elementor-cleanup-form-entries' ), array( $this, 'cleanup_form_entries_settings_field' ), 'cleanup_form_entries_settings', 'cleanup_form_entries_settings_section' );
    }

    /**
     * Settings field.
     */
    public function cleanup_form_entries_settings_field() {
        $options = get_option( 'ncm_elementor_cleanup_form_entries_settings' );
        $days = isset( $options['days'] ) ? $options['days'] : 30;
        ?>
        <input type="number" name="ncm_elementor_cleanup_form_entries_settings[days]" value="<?php echo esc_attr( $days ); ?>" min="1" max="365" />
        <?php
    }

    // Schedule an action if it's not already scheduled.
    public function entries_schedule_event() {
        if ( ! wp_next_scheduled( 'ncm_elementor_cleanup_form_entries_event' ) ) {
            wp_schedule_event( time(), 'hourly', 'ncm_elementor_cleanup_form_entries_event' );
        }
    }

    //delete all form entries older than selected days.
    public function entries_delete_entries() {
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

}

new ncm_elementor_cleanup_form_entries();