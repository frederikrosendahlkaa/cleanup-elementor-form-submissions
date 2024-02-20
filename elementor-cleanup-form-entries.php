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

//create class cleanup_elementor_form_submissions
class cleanup_elementor_form_submissions {
    
    //private
    private $options;
    public $options_name = 'cleanup_elementor_form_submissions_settings';
    
    public function __construct() {

        //if option $this->options_name is not set, set default value.
        if ( get_option( $this->options_name ) ) {
            $this->options = get_option( $this->options_name );
        }

        register_activation_hook( __FILE__, array( $this, 'plugins_activation' ) );

        register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );

        add_action( 'init', array( $this, 'load_textdomain' ) );

        add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

        add_action( 'admin_init', array( $this, 'cleanup_form_submissions_settings' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

        add_action( 'wp', array( $this, 'submissions_schedule_event' ) );

        add_action( 'cleanup_elementor_form_submissions_event', array( $this, 'delete_submissions' ) );

    }

    //activate plugin
    public function plugins_activation() {
        $options = $this->options;
        if ( ! $options ) {
            $options = array(
                'scheduled' => array(  
                    'type' => 'days',
                    'value' => 30
                )
            );
            update_option( $this->options_name, $options );
        }
    }

    //deactivate plugin
    public function plugin_deactivation() {
        wp_clear_scheduled_hook( 'cleanup_elementor_form_submissions_event' );
        // delete option $this->options_name
        delete_option( $this->options_name );
    }

    //load plugin textdomain
    public function load_textdomain() {
        load_plugin_textdomain( 'cleanup-elementor-form-submissions', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    //add a link to the plugin settings page.
    public function plugin_action_links( $links ) {
        $links[] = '<a href="' . esc_url( admin_url( 'tools.php?page=cleanup-elementor-form-submissions' ) ) . '">' . esc_html__( 'Settings', 'cleanup-elementor-form-submissions' ) . '</a>';
        return $links;
    }

    /**
     * add a submenu page under the "tools" admin menu.
     * This page will be used to manually delete all form submissions and select the number of days to keep.
     */
    public function add_menu_item() {
        add_management_page(
            esc_html__( 'Cleanup Elementor Form submissions', 'cleanup-elementor-form-submissions' ),
            esc_html__( 'Cleanup Elementor Form submissions', 'cleanup-elementor-form-submissions' ),
            'manage_options',
            'cleanup-elementor-form-submissions',
            array( $this, 'cleanup_form_submissions_page' )
        );
    }

    /**
     * Cleanup Form submissions page.
     */
    public function cleanup_form_submissions_page () {

        // if elementor or elementor pro is not active, don't proceed.
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        // if user is not admin, don't proceed.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        //add page title
        echo '<h1>' . esc_html__( 'Cleanup Elementor Form submissions', 'cleanup-elementor-form-submissions' ) . '</h1>';

        //add form to select the number of days to keep.
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'cleanup_form_submissions_settings' );
            do_settings_sections( 'cleanup_form_submissions_settings' );
            submit_button();
            ?>
        </form>
        <?php   

        //add button to manually delete all form submissions older than selected days.
        $options = $this->options;
        $keep_info = '';
        if ( $options AND is_array( $options ) AND key_exists( 'scheduled', $options ) ) {
            if ( key_exists( 'value', $options['scheduled'] ) ) {
                $keep_info = $options['scheduled']['value'].' '.$options['scheduled']['type'];
            }
        }

        //if cleanup is not active set cleanup button to disabled.
        $disbled = '';
        if ( !$this->check_if_cleanup_is_active() ) {
            $disbled = 'disabled="disabled"';
        }

        ?>
        <form action="" method="post">
            <input type="hidden" name="cleanup_elementor_form_submissions_nonce" value="<?php echo wp_create_nonce( 'cleanup_elementor_form_submissions_nonce' ); ?>" />
            <input class="button button-primary" type="submit" name="cleanup_elementor_form_submissions_delete" <?php echo $disbled; ?> value="<?php echo __( 'Delete all form submissions older then', 'cleanup-elementor-form-submissions' ).' '.$keep_info; ?>" />
        </form>
        <?php

        // if button is clicked, delete all form submissions older than selected days.
        if ( isset( $_POST['cleanup_elementor_form_submissions_delete'] ) && check_admin_referer( 'cleanup_elementor_form_submissions_nonce', 'cleanup_elementor_form_submissions_nonce' ) ) {
            $this->submissions_delete_submissions();
            echo '<p>' . esc_html__( 'All form submissions older than selected days have been deleted.', 'cleanup-elementor-form-submissions' ) . '</p>';
        }
    }

    /**
     * Register settings.
     */
    public function cleanup_form_submissions_settings() {
        register_setting( 'cleanup_form_submissions_settings', $this->options_name, array( $this, 'sanitize_settings' ) );
        add_settings_section( 'cleanup_form_submissions_settings_section', '', '', 'cleanup_form_submissions_settings' );
        add_settings_field( 'cleanup_form_submissions_field_activate', esc_html__( 'Activate Cleanup', 'cleanup-elementor-form-submissions' ), array( $this, 'setting_field_activate' ), 'cleanup_form_submissions_settings', 'cleanup_form_submissions_settings_section' );
        add_settings_field( 'cleanup_form_submissions_settings_field', esc_html__( 'How long submissions must be kept', 'cleanup-elementor-form-submissions' ), array( $this, 'cleanup_form_submissions_settings_field' ), 'cleanup_form_submissions_settings', 'cleanup_form_submissions_settings_section' );
    }

    //function to check if cleanup is active.
    private function check_if_cleanup_is_active() {
        $options = $this->options;
        if ( $options AND is_array( $options ) AND key_exists( 'active', $options ) AND $options['active'] == 'on') {
            return true;
        }

        return false;
    }

    /**
     * Settings field.
     * Activate or deactivate the cleanup.
     * true/false
     */
    public function setting_field_activate() {        
        $active = $this->check_if_cleanup_is_active();        
        ?>
        <input type="checkbox" name="<?php echo $this->options_name; ?>[active]" <?php checked( $active, '1' ); ?> />
        <?php
    }
    

    /**
     * Settings field.
     */
    public function cleanup_form_submissions_settings_field() {

        $value = 30;
        $type = 'days';

        $options = $this->options;
        if ( $options AND is_array( $options ) AND key_exists( 'scheduled', $options ) ) {

            if ( key_exists( 'type', $options['scheduled'] ) ) {
                $type = $options['scheduled']['type'];
            }

            if ( key_exists( 'value', $options['scheduled'] ) ) {
                $value = $options['scheduled']['value'];
            }

        }

        ?>
        <input type="number" name="<?php echo $this->options_name; ?>[scheduled][value]" value="<?php echo esc_attr( $value ); ?>" min="1" />
        <select name="<?php echo $this->options_name; ?>[scheduled][type]">
            <option value="hours" <?php selected( $type, 'hours' ); ?>><?php echo esc_html__( 'Hours', 'cleanup-elementor-form-submissions' ); ?></option>
            <option value="days" <?php selected( $type, 'days' ); ?>><?php echo esc_html__( 'Days', 'cleanup-elementor-form-submissions' ); ?></option>
            <option value="weeks" <?php selected( $type, 'weeks' ); ?>><?php echo esc_html__( 'Weeks', 'cleanup-elementor-form-submissions' ); ?></option>
            <option value="months" <?php selected( $type, 'months' ); ?>><?php echo esc_html__( 'Months', 'cleanup-elementor-form-submissions' ); ?></option>
            <option value="quaters" <?php selected( $type, 'quaters' ); ?>><?php echo esc_html__( 'Quaters', 'cleanup-elementor-form-submissions' ); ?></option>
            <option value="years" <?php selected( $type, 'years' ); ?>><?php echo esc_html__( 'Years', 'cleanup-elementor-form-submissions' ); ?></option>
        </select>
        <?php
    }

    // Schedule an action if it's not already scheduled.
    public function submissions_schedule_event() {

        //if cleanup is not active, don't proceed.
        if ( !$this->check_if_cleanup_is_active() ) {
            wp_clear_scheduled_hook( 'cleanup_elementor_form_submissions_event' );
            return;
        }

        if ( ! wp_next_scheduled( 'cleanup_elementor_form_submissions_event' ) ) {
            wp_schedule_event( time(), 'hourly', 'cleanup_elementor_form_submissions_event' );
        }
    }

    //delete all form submissions older than selected days.
    public function delete_submissions() {
        // if elementor or elementor pro is not active, don't proceed.
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        // if cleanup is not active, don't proceed.
        if ( !$this->check_if_cleanup_is_active() ) {
            return;
        }

        /**
         * Delete from prefix.e_submissions 
         * Delete prefix.e_submissions_actions_log submition_id
         * Delete prefix.e_submissions_values submition_id
         */
        $scheduled_value = false;
        $scheduled_type = false;
        $options = $this->options;
        if ( $options AND is_array( $options ) AND key_exists( 'scheduled', $options ) ) {
            if ( key_exists( 'value', $options['scheduled'] ) ) {
                $scheduled_value = $options['scheduled']['value'];
            }
            if ( key_exists( 'type', $options['scheduled'] ) ) {
                $scheduled_type = $options['scheduled']['type'];
                //convert type to sql interval type.
                switch ( $scheduled_type ) {
                    case 'hours':
                        $scheduled_type = 'HOUR';
                        break;
                    case 'days':
                        $scheduled_type = 'DAY';
                        break;
                    case 'weeks':
                        $scheduled_type = 'WEEK';
                        break;
                    case 'months':
                        $scheduled_type = 'MONTH';
                        break;
                    case 'quaters':
                        $scheduled_type = 'QUARTER';
                        break;
                    case 'years':
                        $scheduled_type = 'YEAR';
                        break;
                }
            }
        }

        //if scheduled_value and scheduled_type is not set, don't proceed.
        if ( !$scheduled_value OR !$scheduled_type ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'e_submissions';
        //check if table prefix.e_submissions exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
            return;
        }

        //get list of ids of form submissions older than selected $scheduled_value $scheduled_type.
        $sql = "SELECT id FROM $table_name WHERE created_at < DATE_SUB( NOW(), INTERVAL $scheduled_value $scheduled_type )";
        $ids = $wpdb->get_col( $sql );

        //delete form submissions older than selected $scheduled_value $scheduled_type.

        if ( !$ids OR !is_array( $ids ) OR count( $ids ) == 0 ) {
            return;
        }
        
        $sql = "DELETE FROM $table_name WHERE id IN ( " . implode( ',', $ids ) . " )";
        $wpdb->query( $sql );

        //delete from prefix.e_submissions_actions_log submition_id
        $table_name = $wpdb->prefix . 'e_submissions_actions_log';
        //check if table prefix.e_submissions_actions_log exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
            $sql = "DELETE FROM $table_name WHERE submission_id IN ( " . implode( ',', $ids ) . " )";
            $wpdb->query( $sql );
        }

        //delete from prefix.e_submissions_values submition_id
        $table_name = $wpdb->prefix . 'e_submissions_values';
        //check if table prefix.e_submissions_values exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
            $sql = "DELETE FROM $table_name WHERE submission_id IN ( " . implode( ',', $ids ) . " )";
            $wpdb->query( $sql );
        }

    }

}

new cleanup_elementor_form_submissions();