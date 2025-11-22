<?php
/**
 * Automation processing class
 */
class PTP_Comms_Hub_Automations {
    
    /**
     * Get all active automations
     */
    public static function get_active_automations($trigger_type = null) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$wpdb->prefix}ptp_automations WHERE is_active = 1";
        
        if ($trigger_type) {
            $sql .= $wpdb->prepare(" AND trigger_type = %s", $trigger_type);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Execute automation for a contact
     */
    public static function execute_automation($automation_id, $contact_id, $event_data = array()) {
        global $wpdb;
        
        // Get automation details
        $automation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_automations WHERE id = %d AND is_active = 1",
            $automation_id
        ));
        
        if (!$automation) {
            return false;
        }
        
        // Get contact details
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact || !ptp_comms_is_opted_in($contact_id)) {
            return false;
        }
        
        // Check if delay is needed
        if ($automation->delay_minutes > 0) {
            // Schedule for later execution
            wp_schedule_single_event(
                time() + ($automation->delay_minutes * 60),
                'ptp_comms_execute_delayed_automation',
                array($automation_id, $contact_id, $event_data)
            );
            return true;
        }
        
        // Get template
        if ($automation->template_id) {
            $template = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_templates WHERE id = %d",
                $automation->template_id
            ));
            
            if ($template) {
                // Replace variables in template
                $message = ptp_comms_replace_variables(
                    $template->content,
                    (array) $contact,
                    $event_data
                );
                
                // Send message
                if ($template->message_type === 'sms') {
                    $sms_service = new PTP_Comms_Hub_SMS_Service();
                    $result = $sms_service->send_sms($contact->parent_phone, $message);
                    
                    if ($result) {
                        // Log the message
                        ptp_comms_log_message(
                            $contact_id,
                            'sms',
                            'outbound',
                            $message,
                            array('twilio_sid' => $result['sid'])
                        );
                        
                        // Update execution count
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}ptp_automations SET execution_count = execution_count + 1 WHERE id = %d",
                            $automation_id
                        ));
                        
                        return true;
                    }
                } elseif ($template->message_type === 'voice') {
                    $voice_service = new PTP_Comms_Hub_Voice_Service();
                    $result = $voice_service->make_call($contact->parent_phone, $message);
                    
                    if ($result) {
                        ptp_comms_log_message(
                            $contact_id,
                            'voice',
                            'outbound',
                            $message,
                            array('twilio_sid' => $result['sid'])
                        );
                        
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}ptp_automations SET execution_count = execution_count + 1 WHERE id = %d",
                            $automation_id
                        ));
                        
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Trigger automations based on event
     */
    public static function trigger($trigger_type, $contact_id, $event_data = array()) {
        $automations = self::get_active_automations($trigger_type);
        
        foreach ($automations as $automation) {
            // Check conditions if any
            if ($automation->conditions) {
                $conditions = maybe_unserialize($automation->conditions);
                if (!self::check_conditions($conditions, $contact_id, $event_data)) {
                    continue;
                }
            }
            
            // Execute automation
            self::execute_automation($automation->id, $contact_id, $event_data);
        }
    }
    
    /**
     * Check if automation conditions are met
     */
    private static function check_conditions($conditions, $contact_id, $event_data) {
        if (empty($conditions)) {
            return true;
        }
        
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        // Simple condition checking
        foreach ($conditions as $key => $value) {
            if (isset($contact->$key) && $contact->$key != $value) {
                return false;
            }
            if (isset($event_data[$key]) && $event_data[$key] != $value) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Process pending automations (cron callback)
     */
    public static function process_pending_automations() {
        global $wpdb;
        
        // Process event reminders
        $upcoming_events = $wpdb->get_results("
            SELECT r.*, c.* 
            FROM {$wpdb->prefix}ptp_registrations r
            JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            WHERE r.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND r.registration_status = 'completed'
        ");
        
        foreach ($upcoming_events as $event) {
            $days_until = floor((strtotime($event->event_date) - time()) / 86400);
            
            // Trigger 7-day reminder
            if ($days_until == 7) {
                self::trigger('event_approaching_7d', $event->contact_id, (array) $event);
            }
            
            // Trigger 1-day reminder
            if ($days_until == 1) {
                self::trigger('event_approaching_1d', $event->contact_id, (array) $event);
            }
        }
        
        // Process completed events
        $completed_events = $wpdb->get_results("
            SELECT r.*, c.* 
            FROM {$wpdb->prefix}ptp_registrations r
            JOIN {$wpdb->prefix}ptp_contacts c ON r.contact_id = c.id
            WHERE r.event_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND r.registration_status = 'completed'
        ");
        
        foreach ($completed_events as $event) {
            self::trigger('event_completed', $event->contact_id, (array) $event);
        }
    }
    
    /**
     * Create new automation
     */
    public static function create_automation($data) {
        global $wpdb;
        
        $defaults = array(
            'is_active' => 1,
            'delay_minutes' => 0,
            'execution_count' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Serialize conditions if array
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = maybe_serialize($data['conditions']);
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ptp_automations',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update automation
     */
    public static function update_automation($id, $data) {
        global $wpdb;
        
        // Serialize conditions if array
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $data['conditions'] = maybe_serialize($data['conditions']);
        }
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_automations',
            $data,
            array('id' => $id)
        );
    }
    
    /**
     * Delete automation
     */
    public static function delete_automation($id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'ptp_automations',
            array('id' => $id)
        );
    }
    
    /**
     * Toggle automation status
     */
    public static function toggle_automation($id) {
        global $wpdb;
        
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT is_active FROM {$wpdb->prefix}ptp_automations WHERE id = %d",
            $id
        ));
        
        return $wpdb->update(
            $wpdb->prefix . 'ptp_automations',
            array('is_active' => !$current),
            array('id' => $id)
        );
    }
    
    /**
     * Get automation by ID
     */
    public static function get_automation($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_automations WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get all automations
     */
    public static function get_all_automations() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT a.*, t.name as template_name
            FROM {$wpdb->prefix}ptp_automations a
            LEFT JOIN {$wpdb->prefix}ptp_templates t ON a.template_id = t.id
            ORDER BY a.created_at DESC
        ");
    }
}
