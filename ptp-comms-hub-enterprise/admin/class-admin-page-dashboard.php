<?php
class PTP_Comms_Hub_Admin_Page_Dashboard {
    public static function render() {
        global $wpdb;
        
        // Get statistics
        $total_contacts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts");
        $opted_in = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1");
        $opted_out = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_out = 1");
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_communication_logs");
        $active_automations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_automations WHERE is_active = 1");
        $unread_conversations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE unread_count > 0");
        
        // Get recent activity
        $recent_messages = $wpdb->get_results("
            SELECT cl.*, c.parent_first_name, c.parent_last_name, c.parent_phone
            FROM {$wpdb->prefix}ptp_communication_logs cl
            JOIN {$wpdb->prefix}ptp_contacts c ON cl.contact_id = c.id
            ORDER BY cl.created_at DESC
            LIMIT 5
        ");
        
        // Get campaign stats (last 30 days)
        $campaign_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_campaigns,
                SUM(sent_count) as total_sent,
                SUM(total_recipients) as total_recipients
            FROM {$wpdb->prefix}ptp_campaigns
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Get messages by day (last 7 days)
        $messages_by_day = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM {$wpdb->prefix}ptp_communication_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        ?>
        <div class="wrap ptp-comms-wrap">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin: 0;">PTP Communications Hub</h1>
                <div>
                    <a href="?page=ptp-comms-campaigns&action=new" class="ptp-comms-button">
                        <span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                        New Campaign
                    </a>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="ptp-comms-stats">
                <div class="ptp-comms-stat-box">
                    <h2><?php echo number_format($total_contacts); ?></h2>
                    <p>Total Contacts</p>
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        <span style="color: #46b450;">✓ <?php echo number_format($opted_in); ?> Opted In</span>
                    </div>
                </div>
                
                <div class="ptp-comms-stat-box green">
                    <h2><?php echo number_format($total_messages); ?></h2>
                    <p>Messages Sent</p>
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        All time communications
                    </div>
                </div>
                
                <div class="ptp-comms-stat-box blue">
                    <h2><?php echo number_format($unread_conversations); ?></h2>
                    <p>Unread Messages</p>
                    <div style="margin-top: 10px;">
                        <a href="?page=ptp-comms-inbox" style="font-size: 12px; text-decoration: none;">View Inbox →</a>
                    </div>
                </div>
                
                <div class="ptp-comms-stat-box purple">
                    <h2><?php echo number_format($active_automations); ?></h2>
                    <p>Active Automations</p>
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        Running workflows
                    </div>
                </div>
            </div>
            
            <!-- 30-Day Campaign Summary -->
            <?php if ($campaign_stats && $campaign_stats->total_campaigns > 0): ?>
            <div class="ptp-comms-card">
                <h2>Last 30 Days</h2>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                    <div>
                        <div style="font-size: 32px; font-weight: 700; color: #0e0f11; margin-bottom: 5px;">
                            <?php echo number_format($campaign_stats->total_campaigns); ?>
                        </div>
                        <div style="color: #666; font-size: 14px;">Campaigns Sent</div>
                    </div>
                    <div>
                        <div style="font-size: 32px; font-weight: 700; color: #0e0f11; margin-bottom: 5px;">
                            <?php echo number_format($campaign_stats->total_sent); ?>
                        </div>
                        <div style="color: #666; font-size: 14px;">Messages Delivered</div>
                    </div>
                    <div>
                        <div style="font-size: 32px; font-weight: 700; color: #0e0f11; margin-bottom: 5px;">
                            <?php 
                            $rate = $campaign_stats->total_recipients > 0 
                                ? round(($campaign_stats->total_sent / $campaign_stats->total_recipients) * 100, 1) 
                                : 0;
                            echo $rate . '%'; 
                            ?>
                        </div>
                        <div style="color: #666; font-size: 14px;">Delivery Rate</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Main Grid -->
            <div class="ptp-comms-grid">
                <!-- Quick Actions -->
                <div class="ptp-comms-card">
                    <h2>Quick Actions</h2>
                    <ul class="ptp-comms-list">
                        <li>
                            <div>
                                <span class="dashicons dashicons-email-alt" style="color: #FCB900; vertical-align: middle;"></span>
                                <a href="?page=ptp-comms-campaigns&action=new">Send New Campaign</a>
                            </div>
                            <span class="ptp-comms-badge info">SMS/Voice</span>
                        </li>
                        <li>
                            <div>
                                <span class="dashicons dashicons-admin-users" style="color: #FCB900; vertical-align: middle;"></span>
                                <a href="?page=ptp-comms-contacts&action=new">Add New Contact</a>
                            </div>
                        </li>
                        <li>
                            <div>
                                <span class="dashicons dashicons-media-text" style="color: #FCB900; vertical-align: middle;"></span>
                                <a href="?page=ptp-comms-templates">Manage Templates</a>
                            </div>
                        </li>
                        <li>
                            <div>
                                <span class="dashicons dashicons-admin-generic" style="color: #FCB900; vertical-align: middle;"></span>
                                <a href="?page=ptp-comms-automations">Setup Automation</a>
                            </div>
                        </li>
                        <li>
                            <div>
                                <span class="dashicons dashicons-chart-line" style="color: #FCB900; vertical-align: middle;"></span>
                                <a href="?page=ptp-comms-logs">View Activity Log</a>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <!-- System Status -->
                <div class="ptp-comms-card">
                    <h2>System Status</h2>
                    <div style="space-y: 15px;">
                        <div class="ptp-comms-connection">
                            <div class="ptp-comms-connection-icon <?php echo ptp_comms_is_twilio_configured() ? 'connected' : 'disconnected'; ?>"></div>
                            <div style="flex: 1;">
                                <strong>Twilio</strong>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo ptp_comms_is_twilio_configured() ? 'SMS & Voice ready' : 'Not configured'; ?>
                                </div>
                            </div>
                            <?php if (!ptp_comms_is_twilio_configured()): ?>
                            <a href="?page=ptp-comms-settings" class="ptp-comms-button small secondary">Configure</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ptp-comms-connection">
                            <div class="ptp-comms-connection-icon <?php echo ptp_comms_is_hubspot_configured() ? 'connected' : 'disconnected'; ?>"></div>
                            <div style="flex: 1;">
                                <strong>HubSpot</strong>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo ptp_comms_is_hubspot_configured() ? 'Syncing contacts' : 'Not configured'; ?>
                                </div>
                            </div>
                            <?php if (!ptp_comms_is_hubspot_configured()): ?>
                            <a href="?page=ptp-comms-settings" class="ptp-comms-button small secondary">Configure</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ptp-comms-connection">
                            <div class="ptp-comms-connection-icon <?php echo ptp_comms_is_slack_configured() ? 'connected' : 'disconnected'; ?>"></div>
                            <div style="flex: 1;">
                                <strong>Slack</strong>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo ptp_comms_is_slack_configured() ? 'Notifications active' : 'Not configured'; ?>
                                </div>
                            </div>
                            <?php if (!ptp_comms_is_slack_configured()): ?>
                            <a href="?page=ptp-comms-settings" class="ptp-comms-button small secondary">Configure</a>
                            <?php endif; ?>
                        </div>
                        
                        <?php
                        $all_configured = ptp_comms_is_twilio_configured() && 
                                         ptp_comms_is_hubspot_configured() && 
                                         ptp_comms_is_slack_configured();
                        ?>
                        <?php if ($all_configured): ?>
                        <div class="ptp-comms-alert success" style="margin-top: 20px; padding: 10px 15px;">
                            <strong>✓ All Systems Operational</strong>
                        </div>
                        <?php else: ?>
                        <div class="ptp-comms-alert warning" style="margin-top: 20px; padding: 10px 15px;">
                            <strong>⚠ Configuration Required</strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <?php if (!empty($recent_messages)): ?>
            <div class="ptp-comms-card">
                <h2>Recent Activity</h2>
                <table class="ptp-comms-table">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Message</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_messages as $msg): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($msg->parent_first_name . ' ' . $msg->parent_last_name); ?></strong><br>
                                <small style="color: #666;"><?php echo esc_html(ptp_comms_format_phone($msg->parent_phone)); ?></small>
                            </td>
                            <td>
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo esc_html(substr($msg->message_content, 0, 100)); ?>
                                </div>
                            </td>
                            <td>
                                <span class="ptp-comms-badge <?php echo $msg->message_type === 'sms' ? 'info' : 'success'; ?>">
                                    <?php echo strtoupper($msg->message_type); ?>
                                </span>
                            </td>
                            <td>
                                <span class="dashicons dashicons-arrow-<?php echo $msg->direction === 'inbound' ? 'down' : 'up'; ?>-alt" 
                                      style="color: <?php echo $msg->direction === 'inbound' ? '#46b450' : '#0073aa'; ?>;">
                                </span>
                                <?php echo ucfirst($msg->direction); ?>
                            </td>
                            <td>
                                <?php 
                                $status_class = $msg->status === 'sent' || $msg->status === 'delivered' ? 'success' : 
                                              ($msg->status === 'failed' ? 'error' : 'warning');
                                ?>
                                <span class="ptp-comms-badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($msg->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo human_time_diff(strtotime($msg->created_at), current_time('timestamp')); ?> ago
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="?page=ptp-comms-logs" class="ptp-comms-button secondary">View All Activity</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
