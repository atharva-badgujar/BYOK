<?php
/**
 * Google Gemini Provider Implementation
 *
 * @package SmartBot_BYOK
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Provider_Gemini implements AI_Provider {
    
    const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    public function send_message( $system_prompt, $user_message, $api_key, $options = array() ) {
        if ( ! $this->validate_api_key( $api_key ) ) {
            return array(
                'success' => false,
                'message' => '',
                'error'   => __( 'Invalid API key format.', 'smartbot-byok' ),
            );
        }
        
        $model = isset( $options['model'] ) ? $options['model'] : $this->get_default_model();
        $url = self::API_ENDPOINT . $model . ':generateContent?key=' . $api_key;
        
        $combined_message = $system_prompt . "\n\nUser: " . $user_message;
        
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
        
        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => '',
                'error'   => sprintf(
                    __( 'API request failed: %s', 'smartbot-byok' ),
                    $response->get_error_message()
                ),
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );
        
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
    
    public function get_name() {
        return 'Google Gemini';
    }
    
    public function get_default_model() {
        return 'gemini-pro';
    }
    
    public function validate_api_key( $api_key ) {
        return ! empty( $api_key ) && strlen( $api_key ) >= 30 && strpos( $api_key, 'AIza' ) === 0;
    }
    
    public function get_api_endpoint() {
        return self::API_ENDPOINT;
    }
}
