<?php
class PTP_Comms_Hub_Admin_Page_Logs {
    public static function render() {
        global $wpdb;
        $logs = $wpdb->get_results("
            SELECT l.*, c.parent_first_name, c.parent_last_name, c.parent_phone
            FROM {$wpdb->prefix}ptp_communication_logs l
            JOIN {$wpdb->prefix}ptp_contacts c ON l.contact_id = c.id
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
        ?>
        <div class="wrap">
            <h1>Communication Logs</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>Date</th><th>Contact</th><th>Type</th><th>Direction</th><th>Message</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:ia', strtotime($log->created_at)); ?></td>
                            <td><?php echo esc_html($log->parent_first_name . ' ' . $log->parent_last_name); ?><br><small><?php echo esc_html(ptp_comms_format_phone($log->parent_phone)); ?></small></td>
                            <td><?php echo strtoupper($log->message_type); ?></td>
                            <td><?php echo ucfirst($log->direction); ?></td>
                            <td><?php echo esc_html(substr($log->message_content, 0, 100)); ?></td>
                            <td><?php echo esc_html($log->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
