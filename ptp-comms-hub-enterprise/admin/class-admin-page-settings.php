<?php
/**
 * Settings admin page
 */
class PTP_Comms_Hub_Admin_Page_Settings {
    
    public static function render() {
        // Save settings if form submitted
        if (isset($_POST['ptp_comms_save_settings']) && check_admin_referer('ptp_comms_settings_nonce')) {
            self::save_settings();
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        // Get current settings
        $settings = PTP_Comms_Hub_Settings::get_all();
        
        // Active tab: prefer posted active_tab (for client-side switching persistence), fallback to GET
        if (isset($_POST['active_tab'])) {
            $active_tab = sanitize_text_field($_POST['active_tab']);
        } else {
            $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'twilio';
        }
        
        ?>
        <div class="wrap ptp-comms-wrap">
            <h1>⚙️ PTP Comms Hub Settings</h1>
            
            <nav class="ptp-comms-tabs nav-tab-wrapper">
                <a href="#twilio" data-tab="#twilio" class="nav-tab <?php echo $active_tab === 'twilio' ? 'nav-tab-active' : ''; ?>">
                    📱 Twilio (SMS/Voice)
                </a>
                <a href="#hubspot" data-tab="#hubspot" class="nav-tab <?php echo $active_tab === 'hubspot' ? 'nav-tab-active' : ''; ?>">
                    🔄 HubSpot
                </a>
                <a href="#slack" data-tab="#slack" class="nav-tab <?php echo $active_tab === 'slack' ? 'nav-tab-active' : ''; ?>">
                    💬 Slack
                </a>
                <a href="#woocommerce" data-tab="#woocommerce" class="nav-tab <?php echo $active_tab === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
                    🛒 WooCommerce
                </a>
                <a href="#general" data-tab="#general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    ⚙️ General
                </a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field('ptp_comms_settings_nonce'); ?>
                
                <div class="ptp-comms-card" style="margin-top: 20px;">
                    <?php
                        // Hidden input to persist active tab when submitting (client-side tab switching)
                        echo '<input type="hidden" name="active_tab" id="ptp_active_tab" value="' . esc_attr($active_tab) . '" />';

                        // Render all tab panels, showing only the active one. This allows client-side switching
                        // without reloading while preserving server-side rendering for the active tab.
                        echo '<div id="twilio" class="ptp-tab-content ' . ($active_tab === 'twilio' ? 'active' : '') . '">';
                        self::render_twilio_settings($settings);
                        echo '</div>';

                        echo '<div id="hubspot" class="ptp-tab-content ' . ($active_tab === 'hubspot' ? 'active' : '') . '">';
                        self::render_hubspot_settings($settings);
                        echo '</div>';

                        echo '<div id="slack" class="ptp-tab-content ' . ($active_tab === 'slack' ? 'active' : '') . '">';
                        self::render_slack_settings($settings);
                        echo '</div>';

                        echo '<div id="woocommerce" class="ptp-tab-content ' . ($active_tab === 'woocommerce' ? 'active' : '') . '">';
                        self::render_woocommerce_settings($settings);
                        echo '</div>';

                        echo '<div id="general" class="ptp-tab-content ' . ($active_tab === 'general' ? 'active' : '') . '">';
                        self::render_general_settings($settings);
                        echo '</div>';
                    ?>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <button type="submit" name="ptp_comms_save_settings" class="ptp-comms-button">
                            Save Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    private static function render_twilio_settings($settings) {
        $twilio_sid = isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : '';
        $twilio_token = isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : '';
        $twilio_phone = isset($settings['twilio_phone_number']) ? $settings['twilio_phone_number'] : '';
        $is_configured = ptp_comms_is_twilio_configured();
        
        ?>
        <h2>📱 Twilio Configuration</h2>
        
        <?php if ($is_configured): ?>
            <div class="ptp-comms-alert success">
                <strong>✅ Twilio is configured and connected!</strong>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert warning">
                <strong>⚠️ Twilio is not configured.</strong> Complete the settings below to enable SMS and Voice features.
            </div>
        <?php endif; ?>
        
        <div class="ptp-comms-form-group">
            <label for="twilio_account_sid">Account SID</label>
            <input type="text" id="twilio_account_sid" name="settings[twilio_account_sid]" value="<?php echo esc_attr($twilio_sid); ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
            <span class="ptp-comms-form-help">Find this in your <a href="https://console.twilio.com" target="_blank">Twilio Console</a></span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="twilio_auth_token">Auth Token</label>
            <input type="password" id="twilio_auth_token" name="settings[twilio_auth_token]" value="<?php echo esc_attr($twilio_token); ?>" placeholder="********************************">
            <span class="ptp-comms-form-help">Keep this secret! Find it in your Twilio Console.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="twilio_phone_number">Twilio Phone Number</label>
            <input type="tel" id="twilio_phone_number" name="settings[twilio_phone_number]" value="<?php echo esc_attr($twilio_phone); ?>" placeholder="+12025551234">
            <span class="ptp-comms-form-help">Your Twilio phone number in E.164 format (e.g., +12025551234)</span>
        </div>
        
        <div class="ptp-comms-info-box">
            <h3>Webhook Configuration</h3>
            <p><strong>SMS Webhook URL:</strong></p>
            <code><?php echo home_url('/ptp-comms/sms-webhook'); ?></code>
            <p style="margin-top: 15px;"><strong>Voice Webhook URL:</strong></p>
            <code><?php echo home_url('/ptp-comms/voice-webhook'); ?></code>
            <p style="margin-top: 15px; font-size: 13px; color: #666;">
                Add these URLs to your Twilio phone number configuration in the Twilio Console.
            </p>
        </div>
        <?php
    }
    
    private static function render_hubspot_settings($settings) {
        $hubspot_key = isset($settings['hubspot_api_key']) ? $settings['hubspot_api_key'] : '';
        $auto_sync = isset($settings['hubspot_auto_sync']) ? $settings['hubspot_auto_sync'] : 'yes';
        $sync_frequency = isset($settings['hubspot_sync_frequency']) ? $settings['hubspot_sync_frequency'] : 'daily';
        $is_configured = ptp_comms_is_hubspot_configured();
        
        ?>
        <h2>🔄 HubSpot Integration</h2>
        
        <?php if ($is_configured): ?>
            <div class="ptp-comms-alert success">
                <strong>✅ HubSpot is configured and connected!</strong>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert warning">
                <strong>⚠️ HubSpot is not configured.</strong> Add your API key below to enable contact syncing.
            </div>
        <?php endif; ?>
        
        <div class="ptp-comms-form-group">
            <label for="hubspot_api_key">HubSpot API Key (Private App Token)</label>
            <input type="password" id="hubspot_api_key" name="settings[hubspot_api_key]" value="<?php echo esc_attr($hubspot_key); ?>" placeholder="pat-na1-********">
            <span class="ptp-comms-form-help">Create a Private App in HubSpot Settings → Integrations → Private Apps</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="hubspot_auto_sync">Auto-Sync Contacts</label>
            <select id="hubspot_auto_sync" name="settings[hubspot_auto_sync]">
                <option value="yes" <?php selected($auto_sync, 'yes'); ?>>Yes - Automatically sync contacts to HubSpot</option>
                <option value="no" <?php selected($auto_sync, 'no'); ?>>No - Manual sync only</option>
            </select>
            <span class="ptp-comms-form-help">When enabled, contacts are synced immediately after registration.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="hubspot_sync_frequency">Sync Frequency</label>
            <select id="hubspot_sync_frequency" name="settings[hubspot_sync_frequency]">
                <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>>Every Hour</option>
                <option value="daily" <?php selected($sync_frequency, 'daily'); ?>>Daily</option>
                <option value="weekly" <?php selected($sync_frequency, 'weekly'); ?>>Weekly</option>
            </select>
            <span class="ptp-comms-form-help">How often to run batch sync for all contacts.</span>
        </div>
        
        <div class="ptp-comms-info-box">
            <h3>Required HubSpot Scopes</h3>
            <p>Your Private App needs these scopes:</p>
            <ul style="margin-left: 20px;">
                <li><code>crm.objects.contacts.write</code> - Create/update contacts</li>
                <li><code>crm.objects.contacts.read</code> - Read contact data</li>
                <li><code>crm.objects.deals.write</code> - Create deals from orders</li>
                <li><code>crm.schemas.contacts.read</code> - Read custom properties</li>
            </ul>
        </div>
        <?php
    }
    
    private static function render_slack_settings($settings) {
        $webhook_url = isset($settings['slack_webhook_url']) ? $settings['slack_webhook_url'] : '';
        $bot_token = isset($settings['slack_bot_token']) ? $settings['slack_bot_token'] : '';
        $signing_secret = isset($settings['slack_signing_secret']) ? $settings['slack_signing_secret'] : '';
        $notify_orders = isset($settings['slack_notify_orders']) ? $settings['slack_notify_orders'] : 'yes';
        $notify_messages = isset($settings['slack_notify_messages']) ? $settings['slack_notify_messages'] : 'yes';
        $notify_campaigns = isset($settings['slack_notify_campaigns']) ? $settings['slack_notify_campaigns'] : 'yes';
        $is_configured = ptp_comms_is_slack_configured();
        
        ?>
        <h2>💬 Slack Integration</h2>
        
        <?php if ($is_configured): ?>
            <div class="ptp-comms-alert success">
                <strong>✅ Slack is configured and connected!</strong>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert warning">
                <strong>⚠️ Slack is not configured.</strong> Add your webhook URL below to enable notifications.
            </div>
        <?php endif; ?>
        
        <h3>Outgoing Notifications (Required)</h3>
        
        <div class="ptp-comms-form-group">
            <label for="slack_webhook_url">Slack Webhook URL</label>
            <input type="text" id="slack_webhook_url" name="settings[slack_webhook_url]" value="<?php echo esc_attr($webhook_url); ?>" placeholder="https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXX">
            <span class="ptp-comms-form-help">Create an Incoming Webhook in your <a href="https://api.slack.com/apps" target="_blank">Slack App</a></span>
        </div>
        
        <h3>Incoming Messages & Commands (Optional)</h3>
        
        <div class="ptp-comms-form-group">
            <label for="slack_bot_token">Bot User OAuth Token</label>
            <input type="password" id="slack_bot_token" name="settings[slack_bot_token]" value="<?php echo esc_attr($bot_token); ?>" placeholder="xoxb-***********">
            <span class="ptp-comms-form-help">Required for two-way communication. Find in OAuth & Permissions.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="slack_signing_secret">Signing Secret</label>
            <input type="password" id="slack_signing_secret" name="settings[slack_signing_secret]" value="<?php echo esc_attr($signing_secret); ?>" placeholder="********************************">
            <span class="ptp-comms-form-help">Required to verify incoming requests. Find in Basic Information.</span>
        </div>
        
        <h3>Notification Preferences</h3>
        
        <div class="ptp-comms-form-group">
            <label>
                <input type="checkbox" name="settings[slack_notify_orders]" value="yes" <?php checked($notify_orders, 'yes'); ?>>
                Notify on new orders/registrations
            </label>
        </div>
        
        <div class="ptp-comms-form-group">
            <label>
                <input type="checkbox" name="settings[slack_notify_messages]" value="yes" <?php checked($notify_messages, 'yes'); ?>>
                Notify on incoming SMS messages
            </label>
        </div>
        
        <div class="ptp-comms-form-group">
            <label>
                <input type="checkbox" name="settings[slack_notify_campaigns]" value="yes" <?php checked($notify_campaigns, 'yes'); ?>>
                Notify when campaigns complete
            </label>
        </div>
        
        <div class="ptp-comms-info-box">
            <h3>Slack App Configuration</h3>
            <p><strong>Events API Webhook URL:</strong></p>
            <code><?php echo home_url('/ptp-comms/slack-webhook'); ?></code>
            <p style="margin-top: 15px;"><strong>Interactive Components URL:</strong></p>
            <code><?php echo home_url('/ptp-comms/slack-interactive'); ?></code>
            <p style="margin-top: 15px;"><strong>Required Bot Token Scopes:</strong></p>
            <ul style="margin-left: 20px;">
                <li><code>chat:write</code> - Send messages</li>
                <li><code>users:read</code> - Read user information</li>
                <li><code>channels:history</code> - Read channel messages</li>
                <li><code>app_mentions:read</code> - Receive @mentions</li>
            </ul>
            <p style="margin-top: 15px;"><strong>Event Subscriptions:</strong></p>
            <ul style="margin-left: 20px;">
                <li><code>message.channels</code> - Listen to channel messages</li>
                <li><code>app_mention</code> - Get notified when mentioned</li>
            </ul>
        </div>
        <?php
    }
    
    private static function render_woocommerce_settings($settings) {
        $auto_create_contacts = isset($settings['woo_auto_create_contacts']) ? $settings['woo_auto_create_contacts'] : 'yes';
        $auto_opt_in = isset($settings['woo_auto_opt_in']) ? $settings['woo_auto_opt_in'] : 'yes';
        $send_confirmation = isset($settings['woo_send_confirmation']) ? $settings['woo_send_confirmation'] : 'yes';
        $confirmation_template = isset($settings['woo_confirmation_template']) ? $settings['woo_confirmation_template'] : '';
        
        ?>
        <h2>🛒 WooCommerce Integration</h2>
        
        <?php if (class_exists('WooCommerce')): ?>
            <div class="ptp-comms-alert success">
                <strong>✅ WooCommerce is active!</strong>
            </div>
        <?php else: ?>
            <div class="ptp-comms-alert error">
                <strong>❌ WooCommerce is not active.</strong> Install and activate WooCommerce to use this integration.
            </div>
        <?php endif; ?>
        
        <div class="ptp-comms-form-group">
            <label>
                <input type="checkbox" name="settings[woo_auto_create_contacts]" value="yes" <?php checked($auto_create_contacts, 'yes'); ?>>
                Automatically create contacts from orders
            </label>
            <span class="ptp-comms-form-help">Creates a contact record when an order is placed.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label>
                <input type="checkbox" name="settings[woo_auto_opt_in]" value="yes" <?php checked($auto_opt_in, 'yes'); ?>>
                Auto opt-in new contacts from orders
            </label>
            <span class="ptp-comms-form-help">Automatically marks contacts as opted-in for SMS.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label>
                <input type="checkbox" name="settings[woo_send_confirmation]" value="yes" <?php checked($send_confirmation, 'yes'); ?>>
                Send SMS confirmation after order
            </label>
            <span class="ptp-comms-form-help">Sends a confirmation SMS when order status is 'completed' or 'processing'.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="woo_confirmation_template">Confirmation SMS Template</label>
            <select id="woo_confirmation_template" name="settings[woo_confirmation_template]">
                <option value="">Select a template...</option>
                <?php
                global $wpdb;
                $templates = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}ptp_templates WHERE category = 'confirmation'");
                foreach ($templates as $template) {
                    echo '<option value="' . $template->id . '" ' . selected($confirmation_template, $template->id, false) . '>' . esc_html($template->name) . '</option>';
                }
                ?>
            </select>
            <span class="ptp-comms-form-help">Template used for order confirmation messages.</span>
        </div>
        
        <div class="ptp-comms-info-box">
            <h3>Order Sync Details</h3>
            <p>When an order is placed:</p>
            <ol style="margin-left: 20px;">
                <li>Contact is created/updated with billing information</li>
                <li>Registration record is created for each product</li>
                <li>Contact is synced to HubSpot (if configured)</li>
                <li>Slack notification is sent (if configured)</li>
                <li>Automations are triggered based on rules</li>
                <li>Confirmation SMS is sent (if enabled)</li>
            </ol>
        </div>
        <?php
    }
    
    private static function render_general_settings($settings) {
        $company_name = isset($settings['company_name']) ? $settings['company_name'] : 'PTP Soccer Camps';
        $timezone = isset($settings['timezone']) ? $settings['timezone'] : 'America/New_York';
        $date_format = isset($settings['date_format']) ? $settings['date_format'] : 'F j, Y';
        $enable_logging = isset($settings['enable_logging']) ? $settings['enable_logging'] : 'yes';
        
        ?>
        <h2>⚙️ General Settings</h2>
        
        <div class="ptp-comms-form-group">
            <label for="company_name">Company Name</label>
            <input type="text" id="company_name" name="settings[company_name]" value="<?php echo esc_attr($company_name); ?>">
            <span class="ptp-comms-form-help">Used in messages and templates.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="timezone">Timezone</label>
            <select id="timezone" name="settings[timezone]">
                <option value="America/New_York" <?php selected($timezone, 'America/New_York'); ?>>Eastern Time (ET)</option>
                <option value="America/Chicago" <?php selected($timezone, 'America/Chicago'); ?>>Central Time (CT)</option>
                <option value="America/Denver" <?php selected($timezone, 'America/Denver'); ?>>Mountain Time (MT)</option>
                <option value="America/Phoenix" <?php selected($timezone, 'America/Phoenix'); ?>>Arizona Time (no DST)</option>
                <option value="America/Los_Angeles" <?php selected($timezone, 'America/Los_Angeles'); ?>>Pacific Time (PT)</option>
            </select>
            <span class="ptp-comms-form-help">Timezone for scheduling campaigns and automations.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label for="date_format">Date Format</label>
            <select id="date_format" name="settings[date_format]">
                <option value="F j, Y" <?php selected($date_format, 'F j, Y'); ?>>January 15, 2024</option>
                <option value="m/d/Y" <?php selected($date_format, 'm/d/Y'); ?>>01/15/2024</option>
                <option value="d/m/Y" <?php selected($date_format, 'd/m/Y'); ?>>15/01/2024</option>
                <option value="Y-m-d" <?php selected($date_format, 'Y-m-d'); ?>>2024-01-15</option>
            </select>
            <span class="ptp-comms-form-help">How dates appear in messages.</span>
        </div>
        
        <div class="ptp-comms-form-group">
            <label>
                <input type="checkbox" name="settings[enable_logging]" value="yes" <?php checked($enable_logging, 'yes'); ?>>
                Enable detailed logging
            </label>
            <span class="ptp-comms-form-help">Logs all API calls and webhook activity for debugging.</span>
        </div>
        
        <div class="ptp-comms-info-box">
            <h3>System Information</h3>
            <p><strong>Plugin Version:</strong> <?php echo PTP_COMMS_HUB_VERSION; ?></p>
            <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
            <p><strong>Database Prefix:</strong> <?php global $wpdb; echo $wpdb->prefix; ?></p>
        </div>
        <?php
    }
    
    private static function save_settings() {
        if (!isset($_POST['settings']) || !is_array($_POST['settings'])) {
            return;
        }
        
        $settings = $_POST['settings'];
        
        // Handle checkboxes (they're not sent if unchecked)
        $checkbox_fields = array(
            'hubspot_auto_sync',
            'slack_notify_orders',
            'slack_notify_messages',
            'slack_notify_campaigns',
            'woo_auto_create_contacts',
            'woo_auto_opt_in',
            'woo_send_confirmation',
            'enable_logging'
        );
        
        foreach ($checkbox_fields as $field) {
            if (!isset($settings[$field])) {
                $settings[$field] = 'no';
            }
        }
        
        // Sanitize all settings
        $sanitized = array();
        foreach ($settings as $key => $value) {
            $sanitized[$key] = sanitize_text_field($value);
        }
        
        // Save to database
        PTP_Comms_Hub_Settings::update_all($sanitized);
        
        // Flush rewrite rules if webhooks changed
        flush_rewrite_rules();
    }
}
