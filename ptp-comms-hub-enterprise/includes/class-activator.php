<?php
/**
 * Fired during plugin activation
 */
class PTP_Comms_Hub_Activator {
    
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Contacts table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_contacts (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_first_name varchar(100) DEFAULT '',
            parent_last_name varchar(100) DEFAULT '',
            parent_phone varchar(20) DEFAULT '',
            parent_email varchar(255) DEFAULT '',
            child_name varchar(100) DEFAULT '',
            child_age int(3) DEFAULT 0,
            zip_code varchar(10) DEFAULT '',
            opted_in tinyint(1) DEFAULT 0,
            opted_out tinyint(1) DEFAULT 0,
            hubspot_contact_id bigint(20) DEFAULT NULL,
            last_message_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY parent_phone (parent_phone),
            KEY parent_email (parent_email),
            KEY opted_in (opted_in),
            KEY zip_code (zip_code)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Registrations table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_registrations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            market_slug varchar(100) DEFAULT '',
            event_name varchar(255) DEFAULT '',
            event_date date DEFAULT NULL,
            event_location varchar(255) DEFAULT '',
            program_type varchar(100) DEFAULT '',
            registration_status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY order_id (order_id),
            KEY event_date (event_date)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Campaigns table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            message_type varchar(50) DEFAULT 'sms',
            message_content text DEFAULT NULL,
            message_preview varchar(200) DEFAULT '',
            target_segment varchar(100) DEFAULT 'all',
            schedule_time datetime DEFAULT NULL,
            status varchar(50) DEFAULT 'draft',
            total_recipients int(11) DEFAULT 0,
            sent_count int(11) DEFAULT 0,
            delivered_count int(11) DEFAULT 0,
            failed_count int(11) DEFAULT 0,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY schedule_time (schedule_time),
            KEY created_by (created_by)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Communication logs table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_communication_logs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            message_type varchar(50) DEFAULT 'sms',
            direction varchar(20) DEFAULT 'outbound',
            message_content text DEFAULT NULL,
            twilio_sid varchar(100) DEFAULT '',
            status varchar(50) DEFAULT 'pending',
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY campaign_id (campaign_id),
            KEY message_type (message_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Templates table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            message_type varchar(50) DEFAULT 'sms',
            content text NOT NULL,
            category varchar(100) DEFAULT 'general',
            is_active tinyint(1) DEFAULT 1,
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category (category),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Conversations table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_conversations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            contact_id bigint(20) UNSIGNED NOT NULL,
            last_message text DEFAULT NULL,
            last_message_direction varchar(20) DEFAULT 'outbound',
            last_message_at datetime DEFAULT NULL,
            unread_count int(11) DEFAULT 0,
            status varchar(50) DEFAULT 'active',
            assigned_to bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY contact_id (contact_id),
            KEY status (status),
            KEY unread_count (unread_count)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Automations table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_automations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            trigger_type varchar(100) NOT NULL,
            template_id bigint(20) UNSIGNED DEFAULT NULL,
            delay_minutes int(11) DEFAULT 0,
            conditions text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            execution_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY trigger_type (trigger_type),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Product settings table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_product_settings (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            confirmation_template_id bigint(20) UNSIGNED DEFAULT NULL,
            reminder_template_id bigint(20) UNSIGNED DEFAULT NULL,
            reminder_days int(3) DEFAULT 7,
            enable_automations tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY product_id (product_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Slack messages table
        $sql = "CREATE TABLE {$wpdb->prefix}ptp_slack_messages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            slack_user_id varchar(50) DEFAULT '',
            slack_user_name varchar(255) DEFAULT '',
            channel varchar(50) DEFAULT '',
            message text DEFAULT NULL,
            thread_ts varchar(50) DEFAULT '',
            direction varchar(20) DEFAULT 'inbound',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY slack_user_id (slack_user_id),
            KEY channel (channel),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Create default templates
        self::create_default_templates();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('ptp_comms_process_automations')) {
            wp_schedule_event(time(), 'hourly', 'ptp_comms_process_automations');
        }
        
        if (!wp_next_scheduled('ptp_comms_sync_hubspot')) {
            wp_schedule_event(time(), 'daily', 'ptp_comms_sync_hubspot');
        }
        
        // Flush rewrite rules for webhooks
        flush_rewrite_rules();
    }
    
    private static function create_default_templates() {
        global $wpdb;
        $table = $wpdb->prefix . 'ptp_templates';
        
        $templates = array(
            array(
                'name' => 'Registration Confirmation',
                'content' => 'Hi {parent_first_name}! Thanks for registering {child_name} for {event_name} on {event_date}. We\'re excited to see you at {event_location}! Reply STOP to opt out.',
                'category' => 'confirmation',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Event Reminder - 7 Days',
                'content' => 'Hi {parent_first_name}! Just a reminder that {child_name}\'s {event_name} is coming up on {event_date} at {event_location}. Can\'t wait to see you there!',
                'category' => 'reminder',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Event Reminder - 1 Day',
                'content' => 'Tomorrow\'s the day! {child_name}\'s {event_name} at {event_location}. See you at {event_date}!',
                'category' => 'reminder',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Thank You Follow-up',
                'content' => 'Thanks for joining us at {event_name}, {parent_first_name}! We hope {child_name} had a great time. Check ptpsoccercamps.com for more camps coming soon!',
                'category' => 'follow_up',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Welcome New Contact',
                'content' => 'Welcome to PTP Soccer Camps! We\'re excited to have you join our community. Stay tuned for upcoming camps and training sessions. Visit ptpsoccercamps.com to learn more!',
                'category' => 'welcome',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Payment Reminder',
                'content' => 'Hi {parent_first_name}, friendly reminder about the outstanding payment for {child_name}\'s registration. Please visit your account to complete payment. Questions? Just reply to this message!',
                'category' => 'payment',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'Cancellation Notice',
                'content' => 'Hi {parent_first_name}, unfortunately we need to reschedule {event_name} on {event_date}. We\'ll contact you soon with the new date. Sorry for any inconvenience!',
                'category' => 'cancellation',
                'message_type' => 'sms'
            ),
            array(
                'name' => 'General Announcement',
                'content' => 'PTP Soccer Camps Update: {announcement_text}. Visit ptpsoccercamps.com for details!',
                'category' => 'announcement',
                'message_type' => 'sms'
            )
        );
        
        foreach ($templates as $template) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE name = %s",
                $template['name']
            ));
            
            if (!$existing) {
                $wpdb->insert($table, $template);
            }
        }
    }
}
