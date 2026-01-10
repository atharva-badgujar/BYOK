<?php
/**
 * REST API Handler
 *
 * @package SmartBot_BYOK
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SmartBot API Class
 * Handles REST API endpoints for chat functionality
 */
class SmartBot_API {
    
    /**
     * Single instance
     *
     * @var SmartBot_API
     */
    private static $instance = null;
    
    /**
     * API namespace
     */
    const NAMESPACE = 'smartbot/v1';
    
    /**
     * Get instance
     *
     * @return SmartBot_API
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/chat',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_chat_request' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'message' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => array( $this, 'validate_message' ),
                    ),
                ),
            )
        );
    }
    
    /**
     * Check permissions for API requests
     * Validates WordPress nonce for security
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function check_permissions( $request ) {
        // Verify nonce for security
        $nonce = $request->get_header( 'X-WP-Nonce' );
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid security token.', 'smartbot-byok' ),
                array( 'status' => 403 )
            );
        }
        
        return true;
    }
    
    /**
     * Validate message parameter
     *
     * @param string $message Message to validate
     * @return bool
     */
    public function validate_message( $message ) {
        if ( empty( $message ) || strlen( $message ) > 2000 ) {
            return false;
        }
        return true;
    }
    
    /**
     * Handle chat request
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function handle_chat_request( $request ) {
        // Get user message
        $user_message = $request->get_param( 'message' );
        
        // Get settings (securely from backend only)
        $system_prompt = get_option( 'smartbot_system_prompt', 'You are a helpful assistant.' );
        $api_key = get_option( 'smartbot_api_key', '' );
        
        // Validate API key exists
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'no_api_key',
                __( 'API key is not configured. Please contact the site administrator.', 'smartbot-byok' ),
                array( 'status' => 500 )
            );
        }
        
        // Get AI provider using Factory Pattern
        $provider = SmartBot_BYOK::get_ai_provider();
        
        if ( null === $provider ) {
            return new WP_Error(
                'invalid_provider',
                __( 'Invalid AI provider selected.', 'smartbot-byok' ),
                array( 'status' => 500 )
            );
        }
        
        // Rate limiting (basic implementation)
        if ( ! $this->check_rate_limit() ) {
            return new WP_Error(
                'rate_limit_exceeded',
                __( 'Too many requests. Please wait a moment and try again.', 'smartbot-byok' ),
                array( 'status' => 429 )
            );
        }
        
        // Send message to AI provider
        $result = $provider->send_message(
            $system_prompt,
            $user_message,
            $api_key,
            array(
                'temperature' => 0.7,
                'max_tokens'  => 1000,
            )
        );
        
        // Handle response
        if ( ! $result['success'] ) {
            return new WP_Error(
                'ai_error',
                $result['error'],
                array( 'status' => 500 )
            );
        }
        
        // Return successful response
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => $result['message'],
                'provider' => $provider->get_name(),
            ),
            200
        );
    }
    
    /**
     * Basic rate limiting
     * Prevents abuse by limiting requests per IP
     *
     * @return bool True if within rate limit
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'smartbot_rate_limit_' . md5( $ip );
        
        $request_count = get_transient( $transient_key );
        
        // Allow 30 requests per minute
        $max_requests = 30;
        $time_window = 60; // seconds
        
        if ( false === $request_count ) {
            // First request in this time window
            set_transient( $transient_key, 1, $time_window );
            return true;
        }
        
        if ( $request_count >= $max_requests ) {
            return false;
        }
        
        // Increment counter
        set_transient( $transient_key, $request_count + 1, $time_window );
        return true;
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        return $ip;
    }
}