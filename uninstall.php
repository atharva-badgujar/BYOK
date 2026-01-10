<?php
/**
 * Uninstall Script
 *
 * @package SmartBot_BYOK
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function smartbot_delete_options() {
    $options = array(
        'smartbot_provider',
        'smartbot_api_key',
        'smartbot_system_prompt',
        'smartbot_brand_color',
        'smartbot_bot_name',
        'smartbot_bot_avatar',
    );
    
    foreach ( $options as $option ) {
        delete_option( $option );
    }
}

function smartbot_delete_transients() {
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_smartbot_%' 
        OR option_name LIKE '_transient_timeout_smartbot_%'"
    );
}

smartbot_delete_options();
smartbot_delete_transients();
