<?php
/**
 * WooCommerce events synchronization
 */
class PTP_Comms_Hub_Events_Sync {
    
    public static function init() {
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'sync_order'), 10, 1);
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'sync_order'), 10, 1);
    }
    
    public static function sync_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Get order details
        $billing_first = $order->get_billing_first_name();
        $billing_last = $order->get_billing_last_name();
        $billing_phone = $order->get_billing_phone();
        $billing_email = $order->get_billing_email();
        
        if (empty($billing_phone)) {
            return;
        }
        
        // Get or create contact
        $contact_id = ptp_comms_get_or_create_contact($billing_phone, array(
            'parent_first_name' => $billing_first,
            'parent_last_name' => $billing_last,
            'parent_email' => $billing_email,
            'opted_in' => 1
        ));
        
        if (!$contact_id) {
            return;
        }
        
        // Process order items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            // Extract event details from product metadata
            $event_data = array(
                'contact_id' => $contact_id,
                'order_id' => $order_id,
                'event_name' => $product->get_name(),
                'program_type' => $product->get_meta('_program_type', true),
                'market_slug' => $product->get_meta('_market_slug', true),
                'event_location' => $order->get_meta('_event_location', true),
                'event_date' => $order->get_meta('_event_date', true),
                'registration_status' => 'completed'
            );
            
            // Create registration
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'ptp_registrations',
                $event_data
            );
        }
        
        // Sync to HubSpot
        if (ptp_comms_is_hubspot_configured()) {
            PTP_Comms_Hub_HubSpot_Sync::sync_contact($contact_id, $order_id);
        }
        
        // Send Slack notification
        if (ptp_comms_is_slack_configured()) {
            PTP_Comms_Hub_Slack_Integration::notify_new_order($order, $contact_id);
        }
        
        // Trigger automations
        PTP_Comms_Hub_Automations::trigger('order_placed', $contact_id, $event_data);
    }
}
