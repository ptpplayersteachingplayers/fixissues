<?php
/**
 * Webhooks handler for Twilio and Slack
 */
class PTP_Comms_Hub_Webhooks {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'register_endpoints'));
    }
    
    public static function register_endpoints() {
        // Twilio SMS webhook
        add_rewrite_rule(
            '^ptp-comms/sms-webhook/?$',
            'index.php?ptp_comms_webhook=sms',
            'top'
        );
        
        // Twilio Voice webhook
        add_rewrite_rule(
            '^ptp-comms/voice-webhook/?$',
            'index.php?ptp_comms_webhook=voice',
            'top'
        );
        
        // Slack Events API webhook
        add_rewrite_rule(
            '^ptp-comms/slack-webhook/?$',
            'index.php?ptp_comms_webhook=slack',
            'top'
        );
        
        // Slack Interactive components webhook
        add_rewrite_rule(
            '^ptp-comms/slack-interactive/?$',
            'index.php?ptp_comms_webhook=slack_interactive',
            'top'
        );
        
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
        add_action('template_redirect', array(__CLASS__, 'handle_webhook'));
    }
    
    public static function add_query_vars($vars) {
        $vars[] = 'ptp_comms_webhook';
        return $vars;
    }
    
    public static function handle_webhook() {
        $webhook_type = get_query_var('ptp_comms_webhook');
        
        switch ($webhook_type) {
            case 'sms':
                self::handle_sms_webhook();
                break;
            case 'voice':
                self::handle_voice_webhook();
                break;
            case 'slack':
                self::handle_slack_webhook();
                break;
            case 'slack_interactive':
                self::handle_slack_interactive();
                break;
        }
    }
    
    /**
     * Handle Twilio SMS webhook
     */
    private static function handle_sms_webhook() {
        $from = isset($_POST['From']) ? $_POST['From'] : '';
        $body = isset($_POST['Body']) ? sanitize_text_field($_POST['Body']) : '';
        $sid = isset($_POST['MessageSid']) ? sanitize_text_field($_POST['MessageSid']) : '';
        
        if (empty($from) || empty($body)) {
            wp_die('Invalid request', '', array('response' => 400));
        }
        
        // Get or create contact
        $contact_id = ptp_comms_get_or_create_contact($from);
        
        if (!$contact_id) {
            wp_die('Error processing contact', '', array('response' => 500));
        }
        
        $contact = PTP_Comms_Hub_Contacts::get_contact($contact_id);
        
        // Handle opt-out
        if (preg_match('/\b(stop|unsubscribe|opt-out)\b/i', $body)) {
            PTP_Comms_Hub_Contacts::opt_out($contact_id);
            
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $sms_service->send_sms($from, 'You have been unsubscribed from PTP Soccer Camps messages. Reply START to opt back in.');
            
            exit;
        }
        
        // Handle opt-in
        if (preg_match('/\b(start|subscribe|opt-in)\b/i', $body)) {
            PTP_Comms_Hub_Contacts::opt_in($contact_id);
            
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            $sms_service->send_sms($from, 'Welcome back! You are now subscribed to PTP Soccer Camps messages.');
            
            exit;
        }
        
        // Log message
        ptp_comms_log_message(
            $contact_id,
            'sms',
            'inbound',
            $body,
            array('twilio_sid' => $sid)
        );
        
        // Update conversation
        $conv_id = PTP_Comms_Hub_Conversations::get_or_create_conversation($contact_id);
        PTP_Comms_Hub_Conversations::update_conversation($conv_id, $body, 'inbound');
        
        // Notify Slack
        if (ptp_comms_is_slack_configured()) {
            PTP_Comms_Hub_Slack_Integration::notify_inbound_message($contact, $body, 'sms');
        }
        
        exit;
    }
    
    /**
     * Handle Twilio Voice webhook
     */
    private static function handle_voice_webhook() {
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response><Say>Thank you for calling PTP Soccer Camps. Please visit our website at ptpsoccercamps.com or text us for assistance.</Say></Response>';
        exit;
    }
    
    /**
     * Handle Slack Events API webhook
     */
    private static function handle_slack_webhook() {
        // Get raw input
        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);
        
        if (!$data) {
            wp_die('Invalid JSON', '', array('response' => 400));
        }
        
        // Verify signature
        $timestamp = isset($_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP']) ? $_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP'] : '';
        $signature = isset($_SERVER['HTTP_X_SLACK_SIGNATURE']) ? $_SERVER['HTTP_X_SLACK_SIGNATURE'] : '';
        
        if (!empty($timestamp) && !empty($signature)) {
            // Check timestamp to prevent replay attacks (within 5 minutes)
            if (abs(time() - $timestamp) > 300) {
                wp_die('Request timestamp too old', '', array('response' => 400));
            }
            
            if (!PTP_Comms_Hub_Slack_Integration::verify_signature($request_body, $timestamp, $signature)) {
                wp_die('Invalid signature', '', array('response' => 403));
            }
        }
        
        // Handle URL verification challenge
        if (isset($data['type']) && $data['type'] === 'url_verification') {
            header('Content-Type: application/json');
            echo json_encode(array('challenge' => $data['challenge']));
            exit;
        }
        
        // Handle events
        if (isset($data['type']) && $data['type'] === 'event_callback') {
            // Process event asynchronously to avoid timeout
            PTP_Comms_Hub_Slack_Integration::handle_event($data);
            
            // Respond immediately
            header('Content-Type: application/json');
            echo json_encode(array('ok' => true));
            exit;
        }
        
        wp_die('Unknown request type', '', array('response' => 400));
    }
    
    /**
     * Handle Slack Interactive components
     */
    private static function handle_slack_interactive() {
        // Get raw input
        $payload = isset($_POST['payload']) ? json_decode(stripslashes($_POST['payload']), true) : null;
        
        if (!$payload) {
            wp_die('Invalid payload', '', array('response' => 400));
        }
        
        // Verify signature
        $timestamp = isset($_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP']) ? $_SERVER['HTTP_X_SLACK_REQUEST_TIMESTAMP'] : '';
        $signature = isset($_SERVER['HTTP_X_SLACK_SIGNATURE']) ? $_SERVER['HTTP_X_SLACK_SIGNATURE'] : '';
        
        if (!empty($timestamp) && !empty($signature)) {
            $request_body = 'payload=' . $_POST['payload'];
            
            if (abs(time() - $timestamp) > 300) {
                wp_die('Request timestamp too old', '', array('response' => 400));
            }
            
            if (!PTP_Comms_Hub_Slack_Integration::verify_signature($request_body, $timestamp, $signature)) {
                wp_die('Invalid signature', '', array('response' => 403));
            }
        }
        
        // Handle different interaction types
        $type = isset($payload['type']) ? $payload['type'] : '';
        
        switch ($type) {
            case 'block_actions':
                self::handle_slack_block_actions($payload);
                break;
            case 'view_submission':
                self::handle_slack_view_submission($payload);
                break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(array('ok' => true));
        exit;
    }
    
    /**
     * Handle Slack block actions (button clicks, etc.)
     */
    private static function handle_slack_block_actions($payload) {
        // Extract action data
        $actions = isset($payload['actions']) ? $payload['actions'] : array();
        
        foreach ($actions as $action) {
            $action_id = isset($action['action_id']) ? $action['action_id'] : '';
            $value = isset($action['value']) ? $action['value'] : '';
            
            // Handle specific actions
            // This can be extended based on needs
            do_action('ptp_comms_slack_action', $action_id, $value, $payload);
        }
    }
    
    /**
     * Handle Slack view submissions (modal forms)
     */
    private static function handle_slack_view_submission($payload) {
        // Extract view data
        $view = isset($payload['view']) ? $payload['view'] : array();
        $callback_id = isset($view['callback_id']) ? $view['callback_id'] : '';
        $values = isset($view['state']['values']) ? $view['state']['values'] : array();
        
        // Handle specific view submissions
        do_action('ptp_comms_slack_view_submission', $callback_id, $values, $payload);
    }
}
