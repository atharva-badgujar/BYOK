<?php
/**
 * Uninstall Script
 * Fired when the plugin is uninstalled
 *
 * @package SmartBot_BYOK
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete plugin options
 */
function smartbot_delete_options() {
    $options = array(
        'smartbot_provider',
        'smartbot_api_key',
        'smartbot_system_prompt',
        'smartbot_brand_color',
        'smartbot_bot_name',
        'smartbot_bot_avatar',
        'smartbot_license_key',
        'smartbot_license_status',
    );
    
    foreach ( $options as $option ) {
        delete_option( $option );
    }
}

/**
 * Delete transients
 */
function smartbot_delete_transients() {
    global $wpdb;
    
    // Delete all SmartBot transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_smartbot_%' 
        OR option_name LIKE '_transient_timeout_smartbot_%'"
    );
}

// Execute cleanup
smartbot_delete_options();
smartbot_delete_transients();