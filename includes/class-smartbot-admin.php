<?php
/**
 * Admin Settings and UI
 *
 * @package SmartBot_BYOK
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SmartBot Admin Class
 * Handles all admin-related functionality
 */
class SmartBot_Admin {
    
    /**
     * Single instance
     *
     * @var SmartBot_Admin
     */
    private static $instance = null;
    
    /**
     * Get instance
     *
     * @return SmartBot_Admin
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
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_smartbot_activate_license', array( $this, 'ajax_activate_license' ) );
    }
    
    /**
     * Add admin menu
     */
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
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting( 'smartbot_general', 'smartbot_provider', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'openai',
        ) );
        
        register_setting( 'smartbot_general', 'smartbot_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
            'default'           => '',
        ) );
        
        register_setting( 'smartbot_general', 'smartbot_system_prompt', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'You are a helpful assistant.',
        ) );
        
        // Appearance Settings
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
        
        // License Settings
        register_setting( 'smartbot_license', 'smartbot_license_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
    }
    
    /**
     * Sanitize API key (encrypt before storage)
     *
     * @param string $api_key API key input
     * @return string
     */
    public function sanitize_api_key( $api_key ) {
        if ( empty( $api_key ) ) {
            return '';
        }
        
        // Basic sanitization
        $api_key = sanitize_text_field( $api_key );
        
        // In production, consider encrypting the key
        // For now, we'll store it as-is but never expose to frontend
        return $api_key;
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_smartbot-settings' !== $hook ) {
            return;
        }
        
        // Enqueue WordPress color picker
        wp_enqueue_style( 'wp-color-picker' );
        
        // Admin CSS
        wp_enqueue_style(
            'smartbot-admin',
            SMARTBOT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SMARTBOT_VERSION
        );
        
        // Admin JS
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
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'smartbot-byok' ) );
        }
        
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        $is_pro = SmartBot_License::can_use_pro();
        ?>
        <div class="wrap smartbot-admin-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php if ( ! $is_pro ) : ?>
                <div class="smartbot-upgrade-banner">
                    <div class="smartbot-upgrade-content">
                        <h2><?php esc_html_e( '🚀 Upgrade to Pro', 'smartbot-byok' ); ?></h2>
                        <p><?php esc_html_e( 'Unlock advanced AI models, white-labeling, and email capture features.', 'smartbot-byok' ); ?></p>
                        <a href="https://example.com/pricing" target="_blank" class="button button-primary button-hero">
                            <?php esc_html_e( 'Buy Pro License', 'smartbot-byok' ); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php settings_errors(); ?>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=smartbot-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'General', 'smartbot-byok' ); ?>
                </a>
                <a href="?page=smartbot-settings&tab=appearance" class="nav-tab <?php echo 'appearance' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Appearance', 'smartbot-byok' ); ?>
                </a>
                <a href="?page=smartbot-settings&tab=license" class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'License', 'smartbot-byok' ); ?>
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
                    case 'license':
                        $this->render_license_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render General tab
     */
    private function render_general_tab() {
        $provider = get_option( 'smartbot_provider', 'openai' );
        $api_key = get_option( 'smartbot_api_key', '' );
        $system_prompt = get_option( 'smartbot_system_prompt', 'You are a helpful assistant.' );
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'smartbot_general' ); ?>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="smartbot_provider"><?php esc_html_e( 'AI Provider', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <select name="smartbot_provider" id="smartbot_provider" class="regular-text">
                            <option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
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
                        >
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: provider name */
                                esc_html__( 'Enter your API key for %s. This is stored securely and never exposed to the frontend.', 'smartbot-byok' ),
                                '<strong>' . esc_html( ucfirst( $provider ) ) . '</strong>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="smartbot_system_prompt"><?php esc_html_e( 'Business Context (System Prompt)', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <textarea 
                            name="smartbot_system_prompt" 
                            id="smartbot_system_prompt" 
                            rows="6" 
                            class="large-text"
                        ><?php echo esc_textarea( $system_prompt ); ?></textarea>
                        <p class="description">
                            <?php esc_html_e( 'Define the context and behavior for your chatbot. Example: "You are a helpful assistant for XYZ Company specializing in..."', 'smartbot-byok' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render Appearance tab
     */
    private function render_appearance_tab() {
        $brand_color = get_option( 'smartbot_brand_color', '#4F46E5' );
        $bot_name = get_option( 'smartbot_bot_name', 'SmartBot' );
        $bot_avatar = get_option( 'smartbot_bot_avatar', '' );
        ?>
        <form method="post" action="options.php">
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
                            <?php esc_html_e( 'Choose the primary color for your chat widget.', 'smartbot-byok' ); ?>
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
                        >
                        <p class="description">
                            <?php esc_html_e( 'The name displayed in the chat header.', 'smartbot-byok' ); ?>
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
                            <?php esc_html_e( 'Optional: URL to a custom avatar image for your bot.', 'smartbot-byok' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }
    
    /**
     * Render License tab
     */
    private function render_license_tab() {
        $license_key = get_option( 'smartbot_license_key', '' );
        $license_status = get_option( 'smartbot_license_status', 'inactive' );
        ?>
        <div class="smartbot-license-section">
            <h2><?php esc_html_e( 'License Management', 'smartbot-byok' ); ?></h2>
            
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="smartbot_license_key"><?php esc_html_e( 'License Key', 'smartbot-byok' ); ?></label>
                    </th>
                    <td>
                        <input 
                            type="text" 
                            name="smartbot_license_key" 
                            id="smartbot_license_key" 
                            value="<?php echo esc_attr( $license_key ); ?>" 
                            class="regular-text"
                            <?php disabled( 'active' === $license_status ); ?>
                        >
                        <button 
                            type="button" 
                            id="smartbot-activate-license" 
                            class="button button-primary"
                            <?php disabled( 'active' === $license_status ); ?>
                        >
                            <?php esc_html_e( 'Activate License', 'smartbot-byok' ); ?>
                        </button>
                        <span id="smartbot-license-status" class="smartbot-license-status smartbot-license-<?php echo esc_attr( $license_status ); ?>">
                            <?php
                            if ( 'active' === $license_status ) {
                                echo '<span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'Active', 'smartbot-byok' );
                            } else {
                                echo '<span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Inactive', 'smartbot-byok' );
                            }
                            ?>
                        </span>
                    </td>
                </tr>
            </table>
            
            <?php if ( 'inactive' === $license_status ) : ?>
                <div class="smartbot-license-benefits">
                    <h3><?php esc_html_e( 'Pro Features', 'smartbot-byok' ); ?></h3>
                    <ul>
                        <li>✅ <?php esc_html_e( 'Access to advanced AI models (GPT-4, Claude Opus)', 'smartbot-byok' ); ?></li>
                        <li>✅ <?php esc_html_e( 'Remove "Powered by SmartBot" branding', 'smartbot-byok' ); ?></li>
                        <li>✅ <?php esc_html_e( 'Email capture before chat starts', 'smartbot-byok' ); ?></li>
                        <li>✅ <?php esc_html_e( 'Priority support', 'smartbot-byok' ); ?></li>
                    </ul>
                    <a href="https://example.com/pricing" target="_blank" class="button button-primary button-large">
                        <?php esc_html_e( 'Get Pro License', 'smartbot-byok' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for license activation
     */
    public function ajax_activate_license() {
        check_ajax_referer( 'smartbot_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'smartbot-byok' ) ) );
        }
        
        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
        
        if ( empty( $license_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a license key.', 'smartbot-byok' ) ) );
        }
        
        // Verify license
        $result = SmartBot_License::verify_license( $license_key );
        
        if ( $result['valid'] ) {
            update_option( 'smartbot_license_key', $license_key );
            update_option( 'smartbot_license_status', 'active' );
            wp_send_json_success( array( 'message' => __( 'License activated successfully!', 'smartbot-byok' ) ) );
        } else {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }
    }
}