<?php
/**
 * The core plugin class
 */
class PTP_Comms_Hub_Loader {
    
    protected $version;
    
    public function __construct() {
        $this->version = PTP_COMMS_HUB_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    private function load_dependencies() {
        // Helper functions
        require_once PTP_COMMS_HUB_PATH . 'includes/helpers.php';
        
        // Core classes
        require_once PTP_COMMS_HUB_PATH . 'includes/class-settings.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-contacts.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-events-sync.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-campaigns.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-templates.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-conversations.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-automations.php';
        
        // Service classes
        require_once PTP_COMMS_HUB_PATH . 'includes/class-sms-service.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-voice-service.php';
        
        // Integration classes
        require_once PTP_COMMS_HUB_PATH . 'includes/class-hubspot-sync.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-slack-integration.php';
        
        // API and webhooks
        require_once PTP_COMMS_HUB_PATH . 'includes/class-webhooks.php';
        require_once PTP_COMMS_HUB_PATH . 'includes/class-rest-api.php';
        
        // Admin classes
        if (is_admin()) {
            require_once PTP_COMMS_HUB_PATH . 'includes/class-admin-menu.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-dashboard.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-contacts.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-campaigns.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-logs.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-templates.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-inbox.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-automations.php';
            require_once PTP_COMMS_HUB_PATH . 'admin/class-admin-page-settings.php';
        }
        
        // Public-facing class
        require_once PTP_COMMS_HUB_PATH . 'public/class-public.php';
    }
    
    private function define_admin_hooks() {
        if (is_admin()) {
            // Initialize admin menu
            $admin_menu = new PTP_Comms_Hub_Admin_Menu();
            
            // Enqueue admin scripts and styles
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        }
    }
    
    private function define_public_hooks() {
        $public = new PTP_Comms_Hub_Public();
        
        // Enqueue public scripts and styles
        add_action('wp_enqueue_scripts', array($public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($public, 'enqueue_scripts'));
    }
    
    public function enqueue_admin_styles() {
        wp_enqueue_style(
            'ptp-comms-hub-admin',
            PTP_COMMS_HUB_URL . 'admin/css/admin.css',
            array(),
            $this->version
        );
    }
    
    public function enqueue_admin_scripts() {
        wp_enqueue_script(
            'ptp-comms-hub-admin',
            PTP_COMMS_HUB_URL . 'admin/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        wp_localize_script('ptp-comms-hub-admin', 'ptpComms', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ptp_comms_hub_nonce')
        ));
    }
    
    public function run() {
        // Initialize webhooks
        PTP_Comms_Hub_Webhooks::init();
        
        // Initialize REST API
        add_action('rest_api_init', array('PTP_Comms_Hub_REST_API', 'register_routes'));
        
        // Initialize WooCommerce hooks
        PTP_Comms_Hub_Events_Sync::init();
        
        // Initialize cron jobs
        add_action('ptp_comms_process_automations', array('PTP_Comms_Hub_Automations', 'process_pending_automations'));
        add_action('ptp_comms_sync_hubspot', array('PTP_Comms_Hub_HubSpot_Sync', 'sync_all_contacts'));
    }
    
    public function get_version() {
        return $this->version;
    }
}
