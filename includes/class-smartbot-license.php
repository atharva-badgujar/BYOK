<?php
/**
 * License Management and Verification
 *
 * @package SmartBot_BYOK
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SmartBot License Class
 * Handles license verification and Pro feature gates
 */
class SmartBot_License {
    
    /**
     * Remote license server URL
     * In production, replace with your actual license server
     */
    const LICENSE_SERVER_URL = 'https://example.com/api/verify-license';
    
    /**
     * Transient cache duration (24 hours)
     */
    const CACHE_DURATION = DAY_IN_SECONDS;
    
    /**
     * Verify license key against remote server
     *
     * @param string $license_key License key to verify
     * @return array Verification result with 'valid' and 'message' keys
     */
    public static function verify_license( $license_key ) {
        if ( empty( $license_key ) ) {
            return array(
                'valid'   => false,
                'message' => __( 'License key is required.', 'smartbot-byok' ),
            );
        }
        
        // Check transient cache first to avoid API spam
        $cache_key = 'smartbot_license_check_' . md5( $license_key );
        $cached_result = get_transient( $cache_key );
        
        if ( false !== $cached_result ) {
            return $cached_result;
        }
        
        // Make remote API call
        $response = wp_remote_post(
            self::LICENSE_SERVER_URL,
            array(
                'timeout' => 15,
                'body'    => array(
                    'license_key' => $license_key,
                    'site_url'    => get_site_url(),
                    'product'     => 'smartbot-byok',
                ),
            )
        );
        
        // Handle connection errors
        if ( is_wp_error( $response ) ) {
            return array(
                'valid'   => false,
                'message' => sprintf(
                    /* translators: %s: error message */
                    __( 'Connection error: %s', 'smartbot-byok' ),
                    $response->get_error_message()
                ),
            );
        }
        
        // Parse response
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // MOCK RESPONSE FOR DEVELOPMENT
        // In production, remove this and use actual API response
        $data = array(
            'valid'   => true,
            'message' => 'License is valid',
            'expires' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
        );
        
        // Build result
        $result = array(
            'valid'   => isset( $data['valid'] ) && $data['valid'],
            'message' => isset( $data['message'] ) ? $data['message'] : __( 'Invalid response from server.', 'smartbot-byok' ),
            'expires' => isset( $data['expires'] ) ? $data['expires'] : null,
        );
        
        // Cache the result
        if ( $result['valid'] ) {
            set_transient( $cache_key, $result, self::CACHE_DURATION );
        }
        
        return $result;
    }
    
    /**
     * Check if Pro features are available
     *
     * @return bool True if user has active Pro license
     */
    public static function can_use_pro() {
        $license_status = get_option( 'smartbot_license_status', 'inactive' );
        
        if ( 'active' !== $license_status ) {
            return false;
        }
        
        // Verify license is still valid (cached check)
        $license_key = get_option( 'smartbot_license_key', '' );
        
        if ( empty( $license_key ) ) {
            return false;
        }
        
        $result = self::verify_license( $license_key );
        
        // If license became invalid, update status
        if ( ! $result['valid'] ) {
            update_option( 'smartbot_license_status', 'inactive' );
            return false;
        }
        
        return true;
    }
    
    /**
     * Get available AI models based on license
     *
     * @return array Available models for current license tier
     */
    public static function get_available_models() {
        $is_pro = self::can_use_pro();
        
        $models = array(
            'openai' => array(
                'free' => array(
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                ),
                'pro' => array(
                    'gpt-4'         => 'GPT-4',
                    'gpt-4-turbo'   => 'GPT-4 Turbo',
                ),
            ),
            'gemini' => array(
                'free' => array(
                    'gemini-pro' => 'Gemini Pro',
                ),
                'pro' => array(
                    'gemini-ultra' => 'Gemini Ultra',
                ),
            ),
            'claude' => array(
                'free' => array(
                    'claude-3-haiku-20240307' => 'Claude 3 Haiku',
                ),
                'pro' => array(
                    'claude-3-opus-20240229'  => 'Claude 3 Opus',
                    'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
                ),
            ),
        );
        
        $provider = get_option( 'smartbot_provider', 'openai' );
        
        if ( ! isset( $models[ $provider ] ) ) {
            return array();
        }
        
        $available = $models[ $provider ]['free'];
        
        if ( $is_pro && isset( $models[ $provider ]['pro'] ) ) {
            $available = array_merge( $available, $models[ $provider ]['pro'] );
        }
        
        return $available;
    }
    
    /**
     * Check if email capture feature is available
     *
     * @return bool
     */
    public static function can_use_email_capture() {
        return self::can_use_pro();
    }
    
    /**
     * Check if white-labeling is available
     *
     * @return bool
     */
    public static function can_remove_branding() {
        return self::can_use_pro();
    }
    
    /**
     * Deactivate license
     *
     * @return bool Success status
     */
    public static function deactivate_license() {
        $license_key = get_option( 'smartbot_license_key', '' );
        
        if ( empty( $license_key ) ) {
            return false;
        }
        
        // Clear transient cache
        $cache_key = 'smartbot_license_check_' . md5( $license_key );
        delete_transient( $cache_key );
        
        // Update status
        update_option( 'smartbot_license_status', 'inactive' );
        
        // Optionally notify license server
        wp_remote_post(
            self::LICENSE_SERVER_URL . '/deactivate',
            array(
                'timeout' => 10,
                'body'    => array(
                    'license_key' => $license_key,
                    'site_url'    => get_site_url(),
                ),
            )
        );
        
        return true;
    }
    
    /**
     * Get license expiration date
     *
     * @return string|null Expiration date or null
     */
    public static function get_license_expiry() {
        $license_key = get_option( 'smartbot_license_key', '' );
        
        if ( empty( $license_key ) ) {
            return null;
        }
        
        $result = self::verify_license( $license_key );
        
        return isset( $result['expires'] ) ? $result['expires'] : null;
    }
    
    /**
     * Check if license is expiring soon (within 7 days)
     *
     * @return bool
     */
    public static function is_license_expiring_soon() {
        $expiry = self::get_license_expiry();
        
        if ( null === $expiry ) {
            return false;
        }
        
        $expiry_timestamp = strtotime( $expiry );
        $warning_threshold = time() + ( 7 * DAY_IN_SECONDS );
        
        return $expiry_timestamp <= $warning_threshold;
    }
    
    /**
     * Get license status message
     *
     * @return string Status message
     */
    public static function get_status_message() {
        if ( ! self::can_use_pro() ) {
            return __( 'No active license. Using free features.', 'smartbot-byok' );
        }
        
        $expiry = self::get_license_expiry();
        
        if ( null === $expiry ) {
            return __( 'Pro license active.', 'smartbot-byok' );
        }
        
        if ( self::is_license_expiring_soon() ) {
            return sprintf(
                /* translators: %s: expiration date */
                __( 'Pro license expires on %s. Please renew soon.', 'smartbot-byok' ),
                gmdate( 'F j, Y', strtotime( $expiry ) )
            );
        }
        
        return sprintf(
            /* translators: %s: expiration date */
            __( 'Pro license active until %s.', 'smartbot-byok' ),
            gmdate( 'F j, Y', strtotime( $expiry ) )
        );
    }
}