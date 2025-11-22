<?php
class PTP_Comms_Hub_Admin_Page_Templates {
    public static function render() {
        $templates = PTP_Comms_Hub_Templates::get_all_templates();
        ?>
        <div class="wrap">
            <h1>Message Templates</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>Name</th><th>Category</th><th>Type</th><th>Content</th><th>Usage</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $template): ?>
                        <tr>
                            <td><strong><?php echo esc_html($template->name); ?></strong></td>
                            <td><?php echo ucfirst($template->category); ?></td>
                            <td><?php echo strtoupper($template->message_type); ?></td>
                            <td><small><?php echo esc_html(substr($template->content, 0, 100)) . '...'; ?></small></td>
                            <td><?php echo number_format($template->usage_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; background: #f0f6fc; padding: 15px; border-left: 4px solid #FCB900;">
                <h3>Available Variables</h3>
                <p>Use these variables in your templates. They will be automatically replaced:</p>
                <ul>
                    <li><code>{parent_first_name}</code>, <code>{parent_last_name}</code></li>
                    <li><code>{parent_phone}</code>, <code>{parent_email}</code></li>
                    <li><code>{child_name}</code>, <code>{child_age}</code></li>
                    <li><code>{event_name}</code>, <code>{event_date}</code>, <code>{event_location}</code></li>
                    <li><code>{market_slug}</code>, <code>{program_type}</code></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
