<?php
/**
 * HubSpot integration and synchronization
 */
class PTP_Comms_Hub_HubSpot_Sync {
    
    private static function get_api_key() {
        return ptp_comms_get_setting('hubspot_api_key');
    }
    
    public static function sync_contact($contact_id, $order_id = null) {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return false;
        }
        
        global $wpdb;
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            return false;
        }
        
        // Create/update contact in HubSpot
        $hubspot_id = self::create_or_update_hubspot_contact($contact, $api_key);
        
        if ($hubspot_id) {
            // Update local contact with HubSpot ID
            $wpdb->update(
                $wpdb->prefix . 'ptp_contacts',
                array('hubspot_contact_id' => $hubspot_id),
                array('id' => $contact_id)
            );
            
            // Create deal if order exists
            if ($order_id) {
                self::create_hubspot_deal($hubspot_id, $order_id, $api_key);
            }
            
            return true;
        }
        
        return false;
    }
    
    private static function create_or_update_hubspot_contact($contact, $api_key) {
        $properties = array(
            'email' => $contact->parent_email,
            'firstname' => $contact->parent_first_name,
            'lastname' => $contact->parent_last_name,
            'phone' => $contact->parent_phone,
            'ptp_child_name' => $contact->child_name,
            'ptp_child_age' => $contact->child_age
        );
        
        if ($contact->hubspot_contact_id) {
            // Update existing
            $url = "https://api.hubapi.com/contacts/v1/contact/vid/{$contact->hubspot_contact_id}/profile";
            $response = wp_remote_post($url, array(
                'method' => 'POST',
                'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
                'body' => json_encode(array('properties' => self::format_properties($properties)))
            ));
            
            return $contact->hubspot_contact_id;
        } else {
            // Create new
            $url = "https://api.hubapi.com/contacts/v1/contact/";
            $response = wp_remote_post($url, array(
                'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
                'body' => json_encode(array('properties' => self::format_properties($properties)))
            ));
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                return isset($body['vid']) ? $body['vid'] : null;
            }
        }
        
        return null;
    }
    
    private static function create_hubspot_deal($hubspot_contact_id, $order_id, $api_key) {
        $order = wc_get_order($order_id);
        if (!$order) return false;
        
        $url = "https://api.hubapi.com/deals/v1/deal/";
        $data = array(
            'properties' => self::format_properties(array(
                'dealname' => 'PTP Order #' . $order_id,
                'amount' => $order->get_total(),
                'dealstage' => 'closedwon',
                'pipeline' => 'default'
            )),
            'associations' => array(
                'associatedVids' => array($hubspot_contact_id)
            )
        );
        
        wp_remote_post($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
            'body' => json_encode($data)
        ));
    }
    
    public static function log_activity($hubspot_contact_id, $type, $content) {
        $api_key = self::get_api_key();
        if (empty($api_key)) return false;
        
        $url = "https://api.hubapi.com/engagements/v1/engagements";
        $data = array(
            'engagement' => array(
                'type' => $type,
                'timestamp' => time() * 1000
            ),
            'associations' => array(
                'contactIds' => array($hubspot_contact_id)
            ),
            'metadata' => array('body' => $content)
        );
        
        wp_remote_post($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
            'body' => json_encode($data)
        ));
    }
    
    private static function format_properties($properties) {
        $formatted = array();
        foreach ($properties as $key => $value) {
            $formatted[] = array('property' => $key, 'value' => $value);
        }
        return $formatted;
    }
    
    public static function sync_all_contacts() {
        global $wpdb;
        $contacts = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1 LIMIT 100"
        );
        
        foreach ($contacts as $contact) {
            self::sync_contact($contact->id);
        }
    }
}
