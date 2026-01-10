<?php
/**
 * Plugin Name: SmartBot - BYOK AI Widget
 * Plugin URI: https://example.com/smartbot
 * Description: A Bring Your Own Key (BYOK) AI chatbot widget supporting OpenAI, Gemini, and Claude with Freemium licensing.
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

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Constants
 */
define( 'SMARTBOT_VERSION', '1.0.0' );
define( 'SMARTBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMARTBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMARTBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main SmartBot Class
 * 
 * Initializes the plugin and loads all components
 */
class SmartBot_BYOK {
    
    /**
     * Single instance of the class
     *
     * @var SmartBot_BYOK
     */
    private static $instance = null;
    
    /**
     * Get instance of the class
     *
     * @return SmartBot_BYOK
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
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Load Provider Interface
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/interface-ai-provider.php';
        
        // Load Provider Classes
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/class-provider-openai.php';
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/class-provider-gemini.php';
        require_once SMARTBOT_PLUGIN_DIR . 'includes/providers/class-provider-claude.php';
        
        // Load Core Classes
        require_once SMARTBOT_PLUGIN_DIR . 'includes/class-smartbot-license.php';
        require_once SMARTBOT_PLUGIN_DIR . 'includes/class-smartbot-api.php';
        
        // Load Admin only in admin context
        if ( is_admin() ) {
            require_once SMARTBOT_PLUGIN_DIR . 'includes/class-smartbot-admin.php';
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        
        // Initialize components
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        
        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        
        // Add widget to footer
        add_action( 'wp_footer', array( $this, 'render_widget' ) );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = array(
            'smartbot_provider'        => 'openai',
            'smartbot_api_key'         => '',
            'smartbot_system_prompt'   => 'You are a helpful assistant.',
            'smartbot_brand_color'     => '#4F46E5',
            'smartbot_bot_name'        => 'SmartBot',
            'smartbot_bot_avatar'      => '',
            'smartbot_license_key'     => '',
            'smartbot_license_status'  => 'inactive',
        );
        
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient( 'smartbot_license_check' );
        flush_rewrite_rules();
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize REST API
        SmartBot_API::get_instance();
        
        // Initialize Admin if in admin context
        if ( is_admin() ) {
            SmartBot_Admin::get_instance();
        }
        
        // Load text domain
        load_plugin_textdomain( 'smartbot-byok', false, dirname( SMARTBOT_PLUGIN_BASENAME ) . '/languages' );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Enqueue widget CSS
        wp_enqueue_style(
            'smartbot-widget',
            SMARTBOT_PLUGIN_URL . 'assets/css/widget.css',
            array(),
            SMARTBOT_VERSION
        );
        
        // Enqueue widget JavaScript
        wp_enqueue_script(
            'smartbot-widget',
            SMARTBOT_PLUGIN_URL . 'assets/js/widget.js',
            array(),
            SMARTBOT_VERSION,
            true
        );
        
        // Localize script with settings
        wp_localize_script(
            'smartbot-widget',
            'smartbotConfig',
            array(
                'apiUrl'     => esc_url_raw( rest_url( 'smartbot/v1/chat' ) ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'botName'    => sanitize_text_field( get_option( 'smartbot_bot_name', 'SmartBot' ) ),
                'brandColor' => sanitize_hex_color( get_option( 'smartbot_brand_color', '#4F46E5' ) ),
                'avatar'     => esc_url( get_option( 'smartbot_bot_avatar', '' ) ),
                'isPro'      => SmartBot_License::can_use_pro(),
            )
        );
    }
    
    /**
     * Render chat widget in footer
     */
    public function render_widget() {
        $bot_name = sanitize_text_field( get_option( 'smartbot_bot_name', 'SmartBot' ) );
        $brand_color = sanitize_hex_color( get_option( 'smartbot_brand_color', '#4F46E5' ) );
        $is_pro = SmartBot_License::can_use_pro();
        ?>
        <div id="smartbot-widget" data-brand-color="<?php echo esc_attr( $brand_color ); ?>">
            <button id="smartbot-fab" aria-label="Open chat" style="--brand-color: <?php echo esc_attr( $brand_color ); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </button>
            
            <div id="smartbot-chat" class="smartbot-hidden">
                <div class="smartbot-header" style="background-color: <?php echo esc_attr( $brand_color ); ?>">
                    <div class="smartbot-header-content">
                        <h3><?php echo esc_html( $bot_name ); ?></h3>
                        <?php if ( ! $is_pro ) : ?>
                            <span class="smartbot-branding">Powered by SmartBot</span>
                        <?php endif; ?>
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
    
    /**
     * AI Provider Factory
     * Creates the appropriate provider instance based on settings
     *
     * @return AI_Provider|null
     */
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
                return null;
        }
    }
}

/**
 * Initialize the plugin
 */
function smartbot_init() {
    return SmartBot_BYOK::get_instance();
}

// Start the plugin
smartbot_init();