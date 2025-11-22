<?php
/**
 * Slack integration for bidirectional communication
 * Supports: Outgoing webhooks, Events API, Interactive components, Slash commands
 */
class PTP_Comms_Hub_Slack_Integration {
    
    /**
     * Get Slack webhook URL for outgoing messages
     */
    private static function get_webhook_url() {
        return ptp_comms_get_setting('slack_webhook_url');
    }
    
    /**
     * Get Slack Bot Token for API calls
     */
    private static function get_bot_token() {
        return ptp_comms_get_setting('slack_bot_token');
    }
    
    /**
     * Get Slack Signing Secret for verification
     */
    private static function get_signing_secret() {
        return ptp_comms_get_setting('slack_signing_secret');
    }
    
    /**
     * Verify Slack request signature
     */
    public static function verify_signature($request_body, $timestamp, $signature) {
        $signing_secret = self::get_signing_secret();
        if (empty($signing_secret)) {
            return false;
        }
        
        $sig_basestring = 'v0:' . $timestamp . ':' . $request_body;
        $my_signature = 'v0=' . hash_hmac('sha256', $sig_basestring, $signing_secret);
        
        return hash_equals($my_signature, $signature);
    }
    
    /**
     * Send message to Slack (outgoing)
     */
    public static function send_message($message, $blocks = null, $channel = null) {
        $webhook_url = self::get_webhook_url();
        if (empty($webhook_url)) {
            return false;
        }
        
        $payload = array('text' => $message);
        
        if ($blocks) {
            $payload['blocks'] = $blocks;
        }
        
        if ($channel) {
            $payload['channel'] = $channel;
        }
        
        $response = wp_remote_post($webhook_url, array(
            'body' => json_encode($payload),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 15
        ));
        
        return !is_wp_error($response);
    }
    
    /**
     * Send message using Slack API (more control)
     */
    public static function post_message($channel, $text, $blocks = null, $thread_ts = null) {
        $bot_token = self::get_bot_token();
        if (empty($bot_token)) {
            return false;
        }
        
        $payload = array(
            'channel' => $channel,
            'text' => $text
        );
        
        if ($blocks) {
            $payload['blocks'] = $blocks;
        }
        
        if ($thread_ts) {
            $payload['thread_ts'] = $thread_ts;
        }
        
        $response = wp_remote_post('https://slack.com/api/chat.postMessage', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['ok']) && $body['ok'];
    }
    
    /**
     * Handle incoming Slack Events API webhook
     */
    public static function handle_event($event_data) {
        $event = $event_data['event'];
        
        // Handle different event types
        switch ($event['type']) {
            case 'message':
                // Only process user messages (not bot messages)
                if (!isset($event['bot_id']) && !isset($event['subtype'])) {
                    self::process_slack_message($event);
                }
                break;
                
            case 'app_mention':
                self::handle_app_mention($event);
                break;
        }
    }
    
    /**
     * Process incoming Slack message
     */
    private static function process_slack_message($event) {
        $user_id = $event['user'];
        $text = $event['text'];
        $channel = $event['channel'];
        $ts = $event['ts'];
        
        // Get user info from Slack
        $user_info = self::get_user_info($user_id);
        
        if (!$user_info) {
            return;
        }
        
        // Log the incoming message
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'ptp_slack_messages',
            array(
                'slack_user_id' => $user_id,
                'slack_user_name' => isset($user_info['real_name']) ? $user_info['real_name'] : 'Unknown',
                'channel' => $channel,
                'message' => $text,
                'thread_ts' => $ts,
                'direction' => 'inbound',
                'created_at' => current_time('mysql')
            )
        );
        
        // Check for commands or special handling
        self::process_slack_command($text, $channel, $ts, $user_info);
    }
    
    /**
     * Get Slack user info
     */
    private static function get_user_info($user_id) {
        $bot_token = self::get_bot_token();
        if (empty($bot_token)) {
            return null;
        }
        
        $response = wp_remote_get(
            'https://slack.com/api/users.info?user=' . $user_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $bot_token
                ),
                'timeout' => 10
            )
        );
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['ok']) && $body['ok'] ? $body['user'] : null;
    }
    
    /**
     * Process Slack commands
     */
    private static function process_slack_command($text, $channel, $thread_ts, $user_info) {
        $text_lower = strtolower(trim($text));
        
        // Check for help command
        if (strpos($text_lower, 'help') !== false) {
            $help_message = "*PTP Comms Hub Commands:*\n\n" .
                "• `stats` - View communication statistics\n" .
                "• `recent messages` - Show recent messages\n" .
                "• `search <phone>` - Search for a contact\n" .
                "• `help` - Show this help message";
            
            self::post_message($channel, $help_message, null, $thread_ts);
            return;
        }
        
        // Check for stats command
        if (strpos($text_lower, 'stats') !== false) {
            self::send_stats_to_slack($channel, $thread_ts);
            return;
        }
        
        // Check for search command
        if (preg_match('/search\s+(.+)/i', $text, $matches)) {
            $search_term = trim($matches[1]);
            self::search_contact_via_slack($search_term, $channel, $thread_ts);
            return;
        }
    }
    
    /**
     * Send stats to Slack
     */
    private static function send_stats_to_slack($channel, $thread_ts = null) {
        global $wpdb;
        
        $total_contacts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts");
        $opted_in = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs");
        
        $blocks = array(
            array(
                'type' => 'header',
                'text' => array('type' => 'plain_text', 'text' => '📊 PTP Comms Hub Stats')
            ),
            array(
                'type' => 'section',
                'fields' => array(
                    array('type' => 'mrkdwn', 'text' => "*Total Contacts:*\n" . number_format($total_contacts)),
                    array('type' => 'mrkdwn', 'text' => "*Opted In:*\n" . number_format($opted_in)),
                    array('type' => 'mrkdwn', 'text' => "*Total Messages:*\n" . number_format($total_messages)),
                    array('type' => 'mrkdwn', 'text' => "*Opt-in Rate:*\n" . ($total_contacts > 0 ? round(($opted_in / $total_contacts) * 100, 1) . '%' : '0%'))
                )
            )
        );
        
        self::post_message($channel, 'PTP Comms Hub Statistics', $blocks, $thread_ts);
    }
    
    /**
     * Search contact via Slack
     */
    private static function search_contact_via_slack($search_term, $channel, $thread_ts = null) {
        global $wpdb;
        
        // Search by phone or email
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts 
            WHERE parent_phone LIKE %s OR parent_email LIKE %s
            LIMIT 1",
            '%' . $search_term . '%',
            '%' . $search_term . '%'
        ));
        
        if (!$contact) {
            self::post_message($channel, "No contact found for: " . $search_term, null, $thread_ts);
            return;
        }
        
        $blocks = array(
            array(
                'type' => 'header',
                'text' => array('type' => 'plain_text', 'text' => '👤 Contact Found')
            ),
            array(
                'type' => 'section',
                'fields' => array(
                    array('type' => 'mrkdwn', 'text' => "*Name:*\n{$contact->parent_first_name} {$contact->parent_last_name}"),
                    array('type' => 'mrkdwn', 'text' => "*Phone:*\n" . ptp_comms_format_phone($contact->parent_phone)),
                    array('type' => 'mrkdwn', 'text' => "*Email:*\n{$contact->parent_email}"),
                    array('type' => 'mrkdwn', 'text' => "*Status:*\n" . ($contact->opted_in ? '✅ Opted In' : '❌ Opted Out'))
                )
            )
        );
        
        self::post_message($channel, 'Contact Information', $blocks, $thread_ts);
    }
    
    /**
     * Handle app mention
     */
    private static function handle_app_mention($event) {
        $channel = $event['channel'];
        $ts = $event['ts'];
        $text = isset($event['text']) ? $event['text'] : '';
        
        // Remove the mention from the text
        $text = preg_replace('/<@[A-Z0-9]+>/', '', $text);
        $text = trim($text);
        
        if (empty($text) || strpos(strtolower($text), 'help') !== false) {
            $help_message = "Hi! I'm the PTP Comms Hub bot. Here's what I can do:\n\n" .
                "• Type `stats` to see communication statistics\n" .
                "• Type `search <phone or email>` to find a contact\n" .
                "• Type `help` to see this message again";
            
            self::post_message($channel, $help_message, null, $ts);
        }
    }
    
    /**
     * Notify Slack of inbound SMS/Voice message
     */
    public static function notify_inbound_message($contact, $message, $type = 'sms') {
        $blocks = array(
            array(
                'type' => 'header',
                'text' => array('type' => 'plain_text', 'text' => '📨 New ' . strtoupper($type) . ' Message')
            ),
            array(
                'type' => 'section',
                'fields' => array(
                    array('type' => 'mrkdwn', 'text' => "*From:*\n{$contact->parent_first_name} {$contact->parent_last_name}"),
                    array('type' => 'mrkdwn', 'text' => "*Phone:*\n" . ptp_comms_format_phone($contact->parent_phone))
                )
            ),
            array(
                'type' => 'section',
                'text' => array(
                    'type' => 'mrkdwn',
                    'text' => "*Message:*\n```" . $message . "```"
                )
            ),
            array(
                'type' => 'actions',
                'elements' => array(
                    array(
                        'type' => 'button',
                        'text' => array('type' => 'plain_text', 'text' => 'View in Dashboard'),
                        'url' => admin_url('admin.php?page=ptp-comms-inbox&contact_id=' . $contact->id),
                        'style' => 'primary'
                    )
                )
            )
        );
        
        return self::send_message("New message from {$contact->parent_first_name}", $blocks);
    }
    
    /**
     * Notify Slack of new WooCommerce order
     */
    public static function notify_new_order($order, $contact_id) {
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) return false;
        
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = '• ' . $item->get_name();
        }
        $items_text = implode("\n", $items);
        
        $blocks = array(
            array(
                'type' => 'header',
                'text' => array('type' => 'plain_text', 'text' => '🎉 New Registration')
            ),
            array(
                'type' => 'section',
                'fields' => array(
                    array('type' => 'mrkdwn', 'text' => "*Customer:*\n{$contact->parent_first_name} {$contact->parent_last_name}"),
                    array('type' => 'mrkdwn', 'text' => "*Order:*\n#{$order->get_id()}"),
                    array('type' => 'mrkdwn', 'text' => "*Total:*\n$" . number_format($order->get_total(), 2)),
                    array('type' => 'mrkdwn', 'text' => "*Phone:*\n" . ptp_comms_format_phone($contact->parent_phone))
                )
            ),
            array(
                'type' => 'section',
                'text' => array(
                    'type' => 'mrkdwn',
                    'text' => "*Items:*\n" . $items_text
                )
            ),
            array(
                'type' => 'actions',
                'elements' => array(
                    array(
                        'type' => 'button',
                        'text' => array('type' => 'plain_text', 'text' => 'View Order'),
                        'url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
                        'style' => 'primary'
                    ),
                    array(
                        'type' => 'button',
                        'text' => array('type' => 'plain_text', 'text' => 'View Contact'),
                        'url' => admin_url('admin.php?page=ptp-comms-contacts&action=edit&id=' . $contact_id)
                    )
                )
            )
        );
        
        return self::send_message("New order #{$order->get_id()}", $blocks);
    }
    
    /**
     * Notify Slack when campaign is complete
     */
    public static function notify_campaign_complete($campaign, $sent, $failed) {
        $success_rate = $sent > 0 ? round(($sent / ($sent + $failed)) * 100, 1) : 0;
        
        $blocks = array(
            array(
                'type' => 'header',
                'text' => array('type' => 'plain_text', 'text' => '📊 Campaign Complete')
            ),
            array(
                'type' => 'section',
                'fields' => array(
                    array('type' => 'mrkdwn', 'text' => "*Campaign:*\n{$campaign->name}"),
                    array('type' => 'mrkdwn', 'text' => "*Type:*\n" . strtoupper($campaign->message_type)),
                    array('type' => 'mrkdwn', 'text' => "*Sent:*\n{$sent}"),
                    array('type' => 'mrkdwn', 'text' => "*Failed:*\n{$failed}"),
                    array('type' => 'mrkdwn', 'text' => "*Success Rate:*\n{$success_rate}%"),
                )
            ),
            array(
                'type' => 'actions',
                'elements' => array(
                    array(
                        'type' => 'button',
                        'text' => array('type' => 'plain_text', 'text' => 'View Campaign'),
                        'url' => admin_url('admin.php?page=ptp-comms-campaigns&action=view&id=' . $campaign->id),
                        'style' => 'primary'
                    )
                )
            )
        );
        
        return self::send_message("Campaign '{$campaign->name}' completed", $blocks);
    }
    
    /**
     * Send error notification to Slack
     */
    public static function notify_error($error_message, $context = array()) {
        $context_text = '';
        if (!empty($context)) {
            $context_items = array();
            foreach ($context as $key => $value) {
                $context_items[] = "*{$key}:* " . $value;
            }
            $context_text = implode("\n", $context_items);
        }
        
        $blocks = array(
            array(
                'type' => 'header',
                'text' => array('type' => 'plain_text', 'text' => '⚠️ Error Alert')
            ),
            array(
                'type' => 'section',
                'text' => array(
                    'type' => 'mrkdwn',
                    'text' => "*Error:*\n```" . $error_message . "```"
                )
            )
        );
        
        if (!empty($context_text)) {
            $blocks[] = array(
                'type' => 'section',
                'text' => array(
                    'type' => 'mrkdwn',
                    'text' => "*Context:*\n" . $context_text
                )
            );
        }
        
        return self::send_message('Error in PTP Comms Hub', $blocks);
    }
}
