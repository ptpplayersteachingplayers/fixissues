<?php
/**
 * Helper functions for PTP Comms Hub
 */

/**
 * Replace template variables with actual contact/event data
 *
 * @param string $message The message template with variables
 * @param array $contact Contact data array
 * @param array $event Event data array (optional)
 * @return string Message with variables replaced
 */
function ptp_comms_replace_variables($message, $contact, $event = array()) {
    $replacements = array();
    
    // Contact variables
    $replacements['{parent_first_name}'] = !empty($contact['parent_first_name']) ? $contact['parent_first_name'] : '';
    $replacements['{parent_last_name}'] = !empty($contact['parent_last_name']) ? $contact['parent_last_name'] : '';
    $replacements['{parent_phone}'] = !empty($contact['parent_phone']) ? $contact['parent_phone'] : '';
    $replacements['{parent_email}'] = !empty($contact['parent_email']) ? $contact['parent_email'] : '';
    $replacements['{child_name}'] = !empty($contact['child_name']) ? $contact['child_name'] : '';
    $replacements['{child_age}'] = !empty($contact['child_age']) ? $contact['child_age'] : '';
    
    // Event variables
    $replacements['{event_name}'] = !empty($event['event_name']) ? $event['event_name'] : '';
    $replacements['{event_date}'] = !empty($event['event_date']) ? date('F j, Y', strtotime($event['event_date'])) : '';
    $replacements['{event_location}'] = !empty($event['event_location']) ? $event['event_location'] : '';
    $replacements['{market_slug}'] = !empty($event['market_slug']) ? $event['market_slug'] : '';
    $replacements['{program_type}'] = !empty($event['program_type']) ? $event['program_type'] : '';
    
    // Replace all variables
    $message = str_replace(array_keys($replacements), array_values($replacements), $message);
    
    // Clean up any remaining unreplaced variables
    $message = preg_replace('/{[^}]+}/', '', $message);
    
    return trim($message);
}

/**
 * Normalize phone number to E.164 format
 *
 * @param string $phone Phone number to normalize
 * @return string|false Normalized phone number or false on failure
 */
function ptp_comms_normalize_phone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle US numbers
    if (strlen($phone) == 10) {
        return '+1' . $phone;
    } elseif (strlen($phone) == 11 && substr($phone, 0, 1) == '1') {
        return '+' . $phone;
    }
    
    // Already has country code
    if (strlen($phone) > 10 && substr($phone, 0, 1) != '1') {
        return '+' . $phone;
    }
    
    return false;
}

/**
 * Log communication activity
 *
 * @param int $contact_id Contact ID
 * @param string $message_type Type of message (sms, voice)
 * @param string $direction Direction (inbound, outbound)
 * @param string $content Message content
 * @param array $meta Additional metadata
 * @return int|false Log ID on success, false on failure
 */
function ptp_comms_log_message($contact_id, $message_type, $direction, $content, $meta = array()) {
    global $wpdb;
    
    $data = array(
        'contact_id' => $contact_id,
        'message_type' => $message_type,
        'direction' => $direction,
        'message_content' => $content,
        'status' => !empty($meta['status']) ? $meta['status'] : 'sent',
        'twilio_sid' => !empty($meta['twilio_sid']) ? $meta['twilio_sid'] : '',
        'campaign_id' => !empty($meta['campaign_id']) ? $meta['campaign_id'] : null,
        'error_message' => !empty($meta['error']) ? $meta['error'] : null,
    );
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'ptp_communication_logs',
        $data
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Get contact by phone number
 *
 * @param string $phone Phone number
 * @return object|null Contact object or null
 */
function ptp_comms_get_contact_by_phone($phone) {
    global $wpdb;
    
    $normalized = ptp_comms_normalize_phone($phone);
    if (!$normalized) {
        return null;
    }
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE parent_phone = %s",
        $normalized
    ));
}

/**
 * Get or create contact ID from phone number
 *
 * @param string $phone Phone number
 * @param array $data Additional contact data
 * @return int|false Contact ID or false on failure
 */
function ptp_comms_get_or_create_contact($phone, $data = array()) {
    global $wpdb;
    
    $normalized = ptp_comms_normalize_phone($phone);
    if (!$normalized) {
        return false;
    }
    
    // Check if contact exists
    $contact = ptp_comms_get_contact_by_phone($normalized);
    
    if ($contact) {
        return $contact->id;
    }
    
    // Create new contact
    $insert_data = array_merge(array(
        'parent_phone' => $normalized,
        'opted_in' => 1
    ), $data);
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'ptp_contacts',
        $insert_data
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Check if contact has opted in
 *
 * @param int $contact_id Contact ID
 * @return bool True if opted in, false otherwise
 */
function ptp_comms_is_opted_in($contact_id) {
    global $wpdb;
    
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT opted_in FROM {$wpdb->prefix}ptp_contacts WHERE id = %d AND opted_out = 0",
        $contact_id
    ));
    
    return (bool) $result;
}

/**
 * Format phone number for display
 *
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function ptp_comms_format_phone($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($cleaned) == 11 && substr($cleaned, 0, 1) == '1') {
        $cleaned = substr($cleaned, 1);
    }
    
    if (strlen($cleaned) == 10) {
        return sprintf('(%s) %s-%s',
            substr($cleaned, 0, 3),
            substr($cleaned, 3, 3),
            substr($cleaned, 6)
        );
    }
    
    return $phone;
}

/**
 * Get setting value
 *
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed Setting value
 */
function ptp_comms_get_setting($key, $default = '') {
    return PTP_Comms_Hub_Settings::get($key, $default);
}

/**
 * Check if Twilio is configured
 *
 * @return bool True if configured, false otherwise
 */
function ptp_comms_is_twilio_configured() {
    $sid = ptp_comms_get_setting('twilio_account_sid');
    $token = ptp_comms_get_setting('twilio_auth_token');
    $from = ptp_comms_get_setting('twilio_phone_number');
    
    return !empty($sid) && !empty($token) && !empty($from);
}

/**
 * Check if HubSpot is configured
 *
 * @return bool True if configured, false otherwise
 */
function ptp_comms_is_hubspot_configured() {
    $api_key = ptp_comms_get_setting('hubspot_api_key');
    return !empty($api_key);
}

/**
 * Check if Slack is configured
 *
 * @return bool True if configured, false otherwise
 */
function ptp_comms_is_slack_configured() {
    $webhook = ptp_comms_get_setting('slack_webhook_url');
    return !empty($webhook);
}
