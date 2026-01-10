<?php
/**
 * Plugin Name: SmartBot - BYOK AI Widget
 * Plugin URI: https://example.com/smartbot
 * Description: A Bring Your Own Key (BYOK) AI chatbot widget supporting OpenAI, Gemini, and Claude.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: smartbot-byok
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SMARTBOT_VERSION', '1.0.0' );
define( 'SMARTBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMARTBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMARTBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

class SmartBot_BYOK {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/interface-ai-provider.php';
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/class-provider-openai.php';
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/class-provider-gemini.php';
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/class-provider-claude.php';
        require_once SMARTBOT_PLUGIN_DIR . 'includes/class-smartbot-api.php';
        
        if ( is_admin() ) {
            require_once SMARTBOT_PLUGIN_DIR . 'includes/class-smartbot-admin.php';
        }
    }
    
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_widget' ) );
    }
    
    public function activate() {
        $defaults = array(
            'smartbot_provider'        => 'openai',
            'smartbot_api_key'         => '',
            'smartbot_system_prompt'   => 'You are a helpful assistant.',
            'smartbot_brand_color'     => '#4F46E5',
            'smartbot_bot_name'        => 'SmartBot',
            'smartbot_bot_avatar'      => '',
        );
        
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }
    
    public function deactivate() {
        // Cleanup if needed
    }
    
    public function init() {
        SmartBot_API::get_instance();
        
        if ( is_admin() ) {
            SmartBot_Admin::get_instance();
        }
        
        load_plugin_textdomain( 'smartbot-byok', false, dirname( SMARTBOT_PLUGIN_BASENAME ) . '/languages' );
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'smartbot-widget',
            SMARTBOT_PLUGIN_URL . 'assets/css/widget.css',
            array(),
            SMARTBOT_VERSION
        );
        
        wp_enqueue_script(
            'smartbot-widget',
            SMARTBOT_PLUGIN_URL . 'assets/js/widget.js',
            array(),
            SMARTBOT_VERSION,
            true
        );
        
        wp_localize_script(
            'smartbot-widget',
            'smartbotConfig',
            array(
                'apiUrl'     => esc_url_raw( rest_url( 'smartbot/v1/chat' ) ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'botName'    => sanitize_text_field( get_option( 'smartbot_bot_name', 'SmartBot' ) ),
                'brandColor' => sanitize_hex_color( get_option( 'smartbot_brand_color', '#4F46E5' ) ),
                'avatar'     => esc_url( get_option( 'smartbot_bot_avatar', '' ) ),
            )
        );
    }
    
    public function render_widget() {
        $bot_name = sanitize_text_field( get_option( 'smartbot_bot_name', 'SmartBot' ) );
        $brand_color = sanitize_hex_color( get_option( 'smartbot_brand_color', '#4F46E5' ) );
        ?>
        <div id="smartbot-widget">
            <button id="smartbot-fab" aria-label="Open chat">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </button>
            
            <div id="smartbot-chat" class="smartbot-hidden">
                <div class="smartbot-header" style="background-color: <?php echo esc_attr( $brand_color ); ?>">
                    <div class="smartbot-header-content">
                        <h3><?php echo esc_html( $bot_name ); ?></h3>
                        <span class="smartbot-branding">AI Assistant</span>
                    </div>
                    <button id="smartbot-close" aria-label="Close chat">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                
                <div id="smartbot-messages" class="smartbot-messages"></div>
                
                <div class="smartbot-input-wrapper">
                    <input 
                        type="text" 
                        id="smartbot-input" 
                        placeholder="Type your message..."
                        autocomplete="off"
                    >
                    <button id="smartbot-send" aria-label="Send message" style="background-color: <?php echo esc_attr( $brand_color ); ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public static function get_ai_provider() {
        $provider = get_option( 'smartbot_provider', 'openai' );
        
        switch ( $provider ) {
            case 'openai':
                return new Provider_OpenAI();
            case 'gemini':
                return new Provider_Gemini();
            case 'claude':
                return new Provider_Claude();
            default:
                return new Provider_OpenAI();
        }
    }
}

function smartbot_init() {
    return SmartBot_BYOK::get_instance();
}

smartbot_init();
