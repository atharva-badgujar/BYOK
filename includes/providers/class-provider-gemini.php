<?php
/**
 * Google Gemini Provider Implementation
 *
 * @package SmartBot_BYOK
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provider_Gemini Class
 * Implements AI_Provider interface for Google Gemini
 */
class Provider_Gemini implements AI_Provider {
    
    /**
     * API endpoint for Gemini
     */
    const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    /**
     * Send message to Gemini API
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
        
        // Build API URL
        $url = self::API_ENDPOINT . $model . ':generateContent?key=' . $api_key;
        
        // Combine system prompt and user message
        // Gemini doesn't have separate system role, so we prepend it to user message
        $combined_message = $system_prompt . "\n\nUser: " . $user_message;
        
        // Build request body according to Gemini API spec
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $combined_message,
                        ),
                    ),
                ),
            ),
            'generationConfig' => array(
                'temperature'     => isset( $options['temperature'] ) ? $options['temperature'] : 0.7,
                'maxOutputTokens' => isset( $options['max_tokens'] ) ? $options['max_tokens'] : 1000,
            ),
        );
        
        // Make API request
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/json',
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
        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $message = sanitize_textarea_field( $data['candidates'][0]['content']['parts'][0]['text'] );
            
            return array(
                'success' => true,
                'message' => $message,
                'error'   => null,
            );
        }
        
        return array(
            'success' => false,
            'message' => '',
            'error'   => __( 'Invalid response format from Gemini API.', 'smartbot-byok' ),
        );
    }
    
    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name() {
        return 'Google Gemini';
    }
    
    /**
     * Get default model
     *
     * @return string
     */
    public function get_default_model() {
        // Check if Pro license allows advanced models
        if ( SmartBot_License::can_use_pro() ) {
            return 'gemini-pro';
        }
        
        return 'gemini-pro';
    }
    
    /**
     * Validate API key format
     *
     * @param string $api_key API key
     * @return bool
     */
    public function validate_api_key( $api_key ) {
        // Gemini API keys are typically 39 characters
        // Format: AIzaSy... (starts with AIzaSy)
        return ! empty( $api_key ) && strlen( $api_key ) >= 30 && strpos( $api_key, 'AIza' ) === 0;
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