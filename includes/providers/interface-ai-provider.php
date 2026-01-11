<?php
/**
 * AI Provider Interface
 *
 * @package SmartBot_BYOK
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AI Provider Interface
 * 
 * All AI provider classes must implement this interface
 * to ensure consistent behavior across different providers
 */
interface AI_Provider {
    
    /**
     * Send message to AI provider and get response
     *
     * @param string $system_prompt System/context prompt defining bot behavior
     * @param string $user_message  User's message/question
     * @param string $api_key       API key for the provider
     * @param array  $options       Optional parameters (model, temperature, etc.)
     * @return array Response array with keys:
     *               - 'success' (bool): Whether request was successful
     *               - 'message' (string): AI response or error message
     *               - 'error' (string|null): Error details if applicable
     */
    public function send_message( $system_prompt, $user_message, $api_key, $options = array() );
    
    /**
     * Get provider name
     *
     * @return string Provider name (e.g., 'OpenAI', 'Gemini', 'Claude')
     */
    public function get_name();
    
    /**
     * Get default model for this provider
     *
     * @return string Default model identifier
     */
    public function get_default_model();
    
    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @return bool True if key format is valid
     */
    public function validate_api_key( $api_key );
    
    /**
     * Get API endpoint URL
     *
     * @return string API endpoint URL
     */
    public function get_api_endpoint();
}
