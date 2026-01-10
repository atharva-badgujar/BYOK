<?php
/**
 * OpenAI Provider Implementation
 *
 * @package SmartBot_BYOK
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provider_OpenAI Class
 * Implements AI_Provider interface for OpenAI
 */
class Provider_OpenAI implements AI_Provider {
    
    /**
     * API endpoint for OpenAI
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Send message to OpenAI API
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
        
        // Build request body according to OpenAI API spec
        $body = array(
            'model'    => $model,
            'messages' => array(
                array(
                    'role'    => 'system',
                    'content' => $system_prompt,
                ),
                array(
                    'role'    => 'user',
                    'content' => $user_message,
                ),
            ),
            'temperature'   => isset( $options['temperature'] ) ? $options['temperature'] : 0.7,
            'max_tokens'    => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 1000,
        );
        
        // Make API request
        $response = wp_remote_post(
            self::API_ENDPOINT,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
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
        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $message = sanitize_textarea_field( $data['choices'][0]['message']['content'] );
            
            return array(
                'success' => true,
                'message' => $message,
                'error'   => null,
            );
        }
        
        return array(
            'success' => false,
            'message' => '',
            'error'   => __( 'Invalid response format from OpenAI API.', 'smartbot-byok' ),
        );
    }
    
    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return 'OpenAI';
    }
    
    /**
     * Get default model
     *
     * @return string
     */
    public function get_default_model() {
        // Check if Pro license allows advanced models
        if ( SmartBot_License::can_use_pro() ) {
            return 'gpt-4-turbo';
        }
        
        return 'gpt-3.5-turbo';
    }
    
    /**
     * Validate API key format
     *
     * @param string $api_key API key
     * @return bool
     */
    public function validate_api_key( $api_key ) {
        // OpenAI API keys start with 'sk-' and are typically 48-51 characters
        return ! empty( $api_key ) && strpos( $api_key, 'sk-' ) === 0 && strlen( $api_key ) >= 40;
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