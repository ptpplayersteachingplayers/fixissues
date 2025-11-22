<?php
/**
 * Campaigns management class
 */
class PTP_Comms_Hub_Campaigns {
    
    public static function create_campaign($data) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'draft',
            'message_type' => 'sms',
            'target_segment' => 'all',
            'created_by' => get_current_user_id()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptp_campaigns',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function send_campaign($campaign_id) {
        global $wpdb;
        
        // Rate limiting check
        $recent_campaigns = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ptp_campaigns 
            WHERE status IN ('sending', 'completed') 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND created_by = %d",
            get_current_user_id()
        ));
        
        if ($recent_campaigns >= 5) {
            return array(
                'success' => false,
                'error' => 'Rate limit exceeded. Maximum 5 campaigns per hour.'
            );
        }
        
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_campaigns WHERE id = %d",
            $campaign_id
        ));
        
        if (!$campaign || $campaign->status !== 'draft') {
            return array('success' => false, 'error' => 'Invalid campaign or status');
        }
        
        // Update status
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            array('status' => 'sending', 'started_at' => current_time('mysql')),
            array('id' => $campaign_id)
        );
        
        // Get recipients based on segment
        $recipients = self::get_campaign_recipients($campaign->target_segment);
        
        // Limit recipients per campaign
        if (count($recipients) > 1000) {
            $wpdb->update(
                $wpdb->prefix . 'ptp_campaigns',
                array('status' => 'draft'),
                array('id' => $campaign_id)
            );
            return array(
                'success' => false,
                'error' => 'Campaign exceeds maximum of 1000 recipients. Please segment your audience.'
            );
        }
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            array('total_recipients' => count($recipients)),
            array('id' => $campaign_id)
        );
        
        // Send messages with rate limiting
        $sent = 0;
        $failed = 0;
        $rate_limit_delay = 100000; // 0.1 seconds between messages
        
        if ($campaign->message_type === 'sms') {
            $sms_service = new PTP_Comms_Hub_SMS_Service();
            
            foreach ($recipients as $contact) {
                // Rate limiting delay
                usleep($rate_limit_delay);
                
                $message = ptp_comms_replace_variables($campaign->message_content, (array) $contact);
                $result = $sms_service->send_sms($contact->parent_phone, $message);
                
                if ($result['success']) {
                    $sent++;
                    ptp_comms_log_message(
                        $contact->id,
                        'sms',
                        'outbound',
                        $message,
                        array('campaign_id' => $campaign_id, 'twilio_sid' => $result['sid'])
                    );
                } else {
                    $failed++;
                    ptp_comms_log_message(
                        $contact->id,
                        'sms',
                        'outbound',
                        $message,
                        array(
                            'campaign_id' => $campaign_id, 
                            'status' => 'failed',
                            'error' => $result['error']
                        )
                    );
                }
            }
        }
        
        // Update campaign stats
        $wpdb->update(
            $wpdb->prefix . 'ptp_campaigns',
            array(
                'status' => 'completed',
                'sent_count' => $sent,
                'failed_count' => $failed,
                'completed_at' => current_time('mysql')
            ),
            array('id' => $campaign_id)
        );
        
        // Notify Slack
        if (ptp_comms_is_slack_configured()) {
            PTP_Comms_Hub_Slack_Integration::notify_campaign_complete($campaign, $sent, $failed);
        }
        
        return array(
            'success' => true,
            'sent' => $sent,
            'failed' => $failed
        );
    }
    
    private static function get_campaign_recipients($segment) {
        global $wpdb;
        
        $where_conditions = array('opted_in = 1', 'opted_out = 0');
        $where_params = array();
        
        if ($segment !== 'all') {
            $where_conditions[] = 'market_slug = %s';
            $where_params[] = $segment;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE {$where_clause}";
        
        if (!empty($where_params)) {
            $sql = $wpdb->prepare($sql, ...$where_params);
        }
        
        return $wpdb->get_results($sql);
    }
}
