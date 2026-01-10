<?php
/**
 * REST API Handler
 *
 * @package SmartBot_BYOK
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SmartBot_API {
    
    private static $instance = null;
    const NAMESPACE = 'smartbot/v1';
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
    
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
    
    public function check_permissions( $request ) {
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
    
    public function validate_message( $message ) {
        if ( empty( $message ) || strlen( $message ) > 2000 ) {
            return false;
        }
        return true;
    }
    
    public function handle_chat_request( $request ) {
        $user_message = $request->get_param( 'message' );
        $system_prompt = get_option( 'smartbot_system_prompt', 'You are a helpful assistant.' );
        $api_key = get_option( 'smartbot_api_key', '' );
        
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'no_api_key',
                __( 'API key is not configured. Please contact the site administrator.', 'smartbot-byok' ),
                array( 'status' => 500 )
            );
        }
        
        $provider = SmartBot_BYOK::get_ai_provider();
        
        if ( null === $provider ) {
            return new WP_Error(
                'invalid_provider',
                __( 'Invalid AI provider selected.', 'smartbot-byok' ),
                array( 'status' => 500 )
            );
        }
        
        if ( ! $this->check_rate_limit() ) {
            return new WP_Error(
                'rate_limit_exceeded',
                __( 'Too many requests. Please wait a moment and try again.', 'smartbot-byok' ),
                array( 'status' => 429 )
            );
        }
        
        $result = $provider->send_message(
            $system_prompt,
            $user_message,
            $api_key,
            array(
                'temperature' => 0.7,
                'max_tokens'  => 1000,
            )
        );
        
        if ( ! $result['success'] ) {
            return new WP_Error(
                'ai_error',
                $result['error'],
                array( 'status' => 500 )
            );
        }
        
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => $result['message'],
            ),
            200
        );
    }
    
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'smartbot_rate_limit_' . md5( $ip );
        
        $request_count = get_transient( $transient_key );
        
        $max_requests = 30;
        $time_window = 60;
        
        if ( false === $request_count ) {
            set_transient( $transient_key, 1, $time_window );
            return true;
        }
        
        if ( $request_count >= $max_requests ) {
            return false;
        }
        
        set_transient( $transient_key, $request_count + 1, $time_window );
        return true;
    }
    
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
