<?php
/**
 * OpenAI Provider Implementation
 *
 * @package SmartBot_BYOK
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Provider_OpenAI implements AI_Provider {
    
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    
    public function send_message( $system_prompt, $user_message, $api_key, $options = array() ) {
        if ( ! $this->validate_api_key( $api_key ) ) {
            return array(
                'success' => false,
                'message' => '',
                'error'   => __( 'Invalid API key format.', 'smartbot-byok' ),
            );
        }
        
        $model = isset( $options['model'] ) ? $options['model'] : $this->get_default_model();
        
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
    
    public function get_name() {
        return 'OpenAI';
    }
    
    public function get_default_model() {
        return 'gpt-3.5-turbo';
    }
    
    public function validate_api_key( $api_key ) {
        return ! empty( $api_key ) && strpos( $api_key, 'sk-' ) === 0 && strlen( $api_key ) >= 40;
    }
    
    public function get_api_endpoint() {
        return self::API_ENDPOINT;
    }
}
