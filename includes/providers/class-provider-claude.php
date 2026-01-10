<?php
/**
 * Anthropic Claude Provider Implementation
 *
 * @package SmartBot_BYOK
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Provider_Claude implements AI_Provider {
    
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2023-06-01';
    
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
        
        if ( isset( $options['temperature'] ) ) {
            $body['temperature'] = $options['temperature'];
        }
        
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
    
    public function get_name() {
        return 'Anthropic Claude';
    }
    
    public function get_default_model() {
        return 'claude-3-haiku-20240307';
    }
    
    public function validate_api_key( $api_key ) {
        return ! empty( $api_key ) && strpos( $api_key, 'sk-ant-' ) === 0 && strlen( $api_key ) >= 50;
    }
    
    public function get_api_endpoint() {
        return self::API_ENDPOINT;
    }
}
