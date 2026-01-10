<?php
/**
 * Admin Settings and UI
 *
 * @package SmartBot_BYOK
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SmartBot_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_smartbot_test_api', array( $this, 'ajax_test_api' ) );
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __( 'SmartBot Settings', 'smartbot-byok' ),
            __( 'SmartBot', 'smartbot-byok' ),
            'manage_options',
            'smartbot-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-format-chat',
            80
        );
    }
    
    public function register_settings() {
        register_setting( 'smartbot_general', 'smartbot_provider', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'openai',
        ) );
        
        register_setting( 'smartbot_general', 'smartbot_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        
        register_setting( 'smartbot_general', 'smartbot_system_prompt', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'You are a helpful assistant.',
        ) );
        
        register_setting( 'smartbot_appearance', 'smartbot_brand_color', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#4F46E5',
        ) );
        
        register_setting( 'smartbot_appearance', 'smartbot_bot_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'SmartBot',
        ) );
        
        register_setting( 'smartbot_appearance', 'smartbot_bot_avatar', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ) );
    }
    
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_smartbot-settings' !== $hook ) {
            return;
        }
        
        wp_enqueue_style( 'wp-color-picker' );
        
        wp_enqueue_style(
            'smartbot-admin',
            SMARTBOT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SMARTBOT_VERSION
        );
        
        wp_enqueue_script(
            'smartbot-admin',
            SMARTBOT_PLUGIN_URL . 'assets/js/admin.js',
            array( 'wp-color-picker', 'jquery' ),
            SMARTBOT_VERSION,
            true
        );
        
        wp_localize_script(
            'smartbot-admin',
            'smartbotAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'smartbot_admin' ),
            )
        );
    }
    
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smartbot-byok' ) );
        }
        
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap smartbot-admin-wrap">
            <h1>
                <span class="dashicons dashicons-format-chat" style="font-size: 32px; margin-right: 10px;"></span>
                <?php echo esc_html( get_admin_page_title() ); ?>
            </h1>
            
            <p class="description" style="margin-bottom: 20px;">
                <?php esc_html_e( 'Configure your AI-powered chatbot widget. Bring your own API key for OpenAI, Google Gemini, or Anthropic Claude.', 'smartbot-byok' ); ?>
            </p>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=smartbot-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'General Settings', 'smartbot-byok' ); ?>
                </a>
                <a href="?page=smartbot-settings&tab=appearance" class="nav-tab <?php echo 'appearance' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-art"></span>
                    <?php esc_html_e( 'Appearance', 'smartbot-byok' ); ?>
                </a>
            </nav>
            
            <div class="smartbot-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'general':
                        $this->render_general_tab();
                        break;
                    case 'appearance':
                        $this->render_appearance_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_general_tab() {
        $provider = get_option( 'smartbot_provider', 'openai' );
        $api_key = get_option( 'smartbot_api_key', '' );
        $system_prompt = get_option( 'smartbot_system_prompt', 'You are a helpful assistant.' );
        ?>
        <form method="post" action="options.php" id="smartbot-general-form">
            <?php settings_fields( 'smartbot_general' ); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="smartbot_provider"><?php esc_html_e( 'AI Provider', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <select name="smartbot_provider" id="smartbot_provider" class="regular-text">
                            <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI (GPT-3.5, GPT-4)</option>
                            <option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google Gemini</option>
                            <option value="claude" <?php selected( $provider, 'claude' ); ?>>Anthropic Claude</option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Select your preferred AI provider.', 'smartbot-byok' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smartbot_api_key"><?php esc_html_e( 'API Key', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="password" 
                            name="smartbot_api_key" 
                            id="smartbot_api_key" 
                            value="<?php echo esc_attr( $api_key ); ?>" 
                            class="regular-text"
                            autocomplete="off"
                            placeholder="sk-..."
                        >
                        <button type="button" id="smartbot-test-api" class="button">
                            <?php esc_html_e( 'Test Connection', 'smartbot-byok' ); ?>
                        </button>
                        <p class="description">
                            <?php
                            printf(
                                esc_html__( 'Enter your API key for %s. Get your key from: ', 'smartbot-byok' ),
                                '<strong id="provider-name">' . esc_html( ucfirst( $provider ) ) . '</strong>'
                            );
                            ?>
                            <span id="api-key-link">
                                <?php
                                $link = '';
                                switch ( $provider ) {
                                    case 'openai':
                                        $link = 'https://platform.openai.com/api-keys';
                                        break;
                                    case 'gemini':
                                        $link = 'https://makersuite.google.com/app/apikey';
                                        break;
                                    case 'claude':
                                        $link = 'https://console.anthropic.com/';
                                        break;
                                }
                                ?>
                                <a href="<?php echo esc_url( $link ); ?>" target="_blank" id="provider-link"><?php echo esc_url( $link ); ?></a>
                            </span>
                        </p>
                        <div id="api-test-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smartbot_system_prompt"><?php esc_html_e( 'System Prompt', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <textarea 
                            name="smartbot_system_prompt" 
                            id="smartbot_system_prompt" 
                            rows="6" 
                            class="large-text"
                            placeholder="Example: You are a helpful customer support assistant for Acme Corp. Help users with product questions, troubleshooting, and general inquiries."
                        ><?php echo esc_textarea( $system_prompt ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Define the context and behavior for your chatbot. This helps the AI understand its role and respond appropriately.', 'smartbot-byok' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button( __( 'Save Changes', 'smartbot-byok' ), 'primary', 'submit', true, array( 'id' => 'smartbot-save-general' ) ); ?>
        </form>
        <?php
    }
    
    private function render_appearance_tab() {
        $brand_color = get_option( 'smartbot_brand_color', '#4F46E5' );
        $bot_name = get_option( 'smartbot_bot_name', 'SmartBot' );
        $bot_avatar = get_option( 'smartbot_bot_avatar', '' );
        ?>
        <form method="post" action="options.php" id="smartbot-appearance-form">
            <?php settings_fields( 'smartbot_appearance' ); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="smartbot_brand_color"><?php esc_html_e( 'Brand Color', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="smartbot_brand_color" 
                            id="smartbot_brand_color" 
                            value="<?php echo esc_attr( $brand_color ); ?>" 
                            class="smartbot-color-picker"
                        >
                        <p class="description">
                            <?php esc_html_e( 'Choose the primary color for your chat widget buttons and header.', 'smartbot-byok' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smartbot_bot_name"><?php esc_html_e( 'Bot Name', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="smartbot_bot_name" 
                            id="smartbot_bot_name" 
                            value="<?php echo esc_attr( $bot_name ); ?>" 
                            class="regular-text"
                            placeholder="SmartBot"
                        >
                        <p class="description">
                            <?php esc_html_e( 'The name displayed in the chat widget header.', 'smartbot-byok' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smartbot_bot_avatar"><?php esc_html_e( 'Bot Avatar URL', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="url" 
                            name="smartbot_bot_avatar" 
                            id="smartbot_bot_avatar" 
                            value="<?php echo esc_url( $bot_avatar ); ?>" 
                            class="regular-text"
                            placeholder="https://example.com/avatar.png"
                        >
                        <p class="description">
                            <?php esc_html_e( 'Optional: URL to a custom avatar image for your bot (recommended size: 64x64px).', 'smartbot-byok' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button( __( 'Save Changes', 'smartbot-byok' ), 'primary', 'submit', true, array( 'id' => 'smartbot-save-appearance' ) ); ?>
        </form>
        <?php
    }
    
    public function ajax_test_api() {
        check_ajax_referer( 'smartbot_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'smartbot-byok' ) ) );
        }
        
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        $provider_type = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : 'openai';
        
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter an API key.', 'smartbot-byok' ) ) );
        }
        
        $provider = null;
        switch ( $provider_type ) {
            case 'openai':
                $provider = new Provider_OpenAI();
                break;
            case 'gemini':
                $provider = new Provider_Gemini();
                break;
            case 'claude':
                $provider = new Provider_Claude();
                break;
        }
        
        if ( ! $provider ) {
            wp_send_json_error( array( 'message' => __( 'Invalid provider.', 'smartbot-byok' ) ) );
        }
        
        $result = $provider->send_message(
            'You are a helpful assistant.',
            'Say "API connection successful" if you can read this.',
            $api_key,
            array( 'max_tokens' => 50 )
        );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 
                'message' => __( 'API connection successful! Your key is working correctly.', 'smartbot-byok' ),
                'response' => $result['message']
            ) );
        } else {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }
    }
}
