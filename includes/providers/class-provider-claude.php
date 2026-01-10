<?php
/**
 * Anthropic Claude Provider Implementation
 *
 * @package SmartBot_BYOK
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provider_Claude Class
 * Implements AI_Provider interface for Anthropic Claude
 */
class Provider_Claude implements AI_Provider {
    
    /**
     * API endpoint for Claude
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    
    /**
     * API version
     */
    const API_VERSION = '2023-06-01';
    
    /**
     * Send message to Claude API
     *
     * @param string $system_prompt System prompt
     * @param string $user_message  User message
     * @param string $api_key       API key
     * @param array  $options       Additional options
     * @return array Response array
     */
    public function send_message( $system_prompt, $user_message, $api_key, $options = array() ) {
        // Validate API key
        if ( ! $this->validate_api_key( $api_key ) ) {
            return array(
                'success' => false,
                'message' => '',
                'error'   => __( 'Invalid API key format.', 'smartbot-byok' ),
            );
        }
        
        // Get model
        $model = isset( $options['model'] ) ? $options['model'] : $this->get_default_model();
        
        // Build request body according to Claude API spec
        $body = array(
            'model'      => $model,
            'max_tokens' => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 1000,
            'system'     => $system_prompt,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $user_message,
                ),
            ),
        );
        
        // Add temperature if specified
        if ( isset( $options['temperature'] ) ) {
            $body['temperature'] = $options['temperature'];
        }
        
        // Make API request
        $response = wp_remote_post(
            self::API_ENDPOINT,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $api_key,
                    'anthropic-version' => self::API_VERSION,
                ),
                'body'    => wp_json_encode( $body ),
            )
        );
        
        // Handle errors
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => '',
                'error'   => sprintf(
                    /* translators: %s: error message */
                    __( 'API request failed: %s', 'smartbot-byok' ),
                    $response->get_error_message()
                ),
            );
        }
        
        // Parse response
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );
        
        // Check for API errors
        if ( 200 !== $response_code ) {
            $error_message = __( 'Unknown API error.', 'smartbot-byok' );
            
            if ( isset( $data['error']['message'] ) ) {
                $error_message = sanitize_text_field( $data['error']['message'] );
            }
            
            return array(
                'success' => false,
                'message' => '',
                'error'   => $error_message,
            );
        }
        
        // Extract message from response
        if ( isset( $data['content'][0]['text'] ) ) {
            $message = sanitize_textarea_field( $data['content'][0]['text'] );
            
            return array(
                'success' => true,
                'message' => $message,
                'error'   => null,
            );
        }
        
        return array(
            'success' => false,
            'message' => '',
            'error'   => __( 'Invalid response format from Claude API.', 'smartbot-byok' ),
        );
    }
    
    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return 'Anthropic Claude';
    }
    
    /**
     * Get default model
     *
     * @return string
     */
    public function get_default_model() {
        // Check if Pro license allows advanced models
        if ( SmartBot_License::can_use_pro() ) {
            return 'claude-3-opus-20240229';
        }
        
        return 'claude-3-haiku-20240307';
    }
    
    /**
     * Validate API key format
     *
     * @param string $api_key API key
     * @return bool
     */
    public function validate_api_key( $api_key ) {
        // Claude API keys start with 'sk-ant-' and are typically around 108 characters
        return ! empty( $api_key ) && strpos( $api_key, 'sk-ant-' ) === 0 && strlen( $api_key ) >= 50;
    }
    
    /**
     * Get API endpoint
     *
     * @return string
     */
    public function get_api_endpoint() {
        return self::API_ENDPOINT;
    }
}