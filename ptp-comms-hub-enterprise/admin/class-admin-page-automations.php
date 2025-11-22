<?php
/**
 * Automations admin page
 */
class PTP_Comms_Hub_Admin_Page_Automations {
    
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'new':
                self::render_form();
                break;
            case 'edit':
                self::render_form(isset($_GET['id']) ? intval($_GET['id']) : 0);
                break;
            case 'toggle':
                self::handle_toggle();
                break;
            case 'delete':
                self::handle_delete();
                break;
            default:
                self::render_list();
        }
    }
    
    private static function render_list() {
        // Handle form submissions
        if (isset($_POST['ptp_comms_automation_nonce'])) {
            check_admin_referer('ptp_comms_automation', 'ptp_comms_automation_nonce');
            self::handle_save();
        }
        
        $automations = PTP_Comms_Hub_Automations::get_all_automations();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Automations</h1>
            <a href="?page=ptp-comms-automations&action=new" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            
            <div class="ptp-comms-info-box" style="background: #f0f6fc; padding: 15px; margin: 20px 0; border-left: 4px solid #FCB900;">
                <h3 style="margin-top: 0;">About Automations</h3>
                <p>Automations allow you to automatically send messages based on specific triggers:</p>
                <ul>
                    <li><strong>Order Placed:</strong> Send confirmation when an order is completed</li>
                    <li><strong>Event Approaching (7 days):</strong> Reminder one week before event</li>
                    <li><strong>Event Approaching (1 day):</strong> Reminder one day before event</li>
                    <li><strong>Event Completed:</strong> Thank you message after event</li>
                    <li><strong>New Contact:</strong> Welcome message for new registrations</li>
                </ul>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Trigger</th>
                        <th>Template</th>
                        <th>Delay</th>
                        <th>Status</th>
                        <th>Executions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($automations)): ?>
                        <tr>
                            <td colspan="7">No automations found. <a href="?page=ptp-comms-automations&action=new">Create your first automation</a></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($automations as $automation): ?>
                            <tr>
                                <td><strong><?php echo esc_html($automation->name); ?></strong></td>
                                <td><?php echo esc_html(self::format_trigger_type($automation->trigger_type)); ?></td>
                                <td><?php echo esc_html($automation->template_name ?? 'None'); ?></td>
                                <td><?php echo esc_html(self::format_delay($automation->delay_minutes)); ?></td>
                                <td>
                                    <?php if ($automation->is_active): ?>
                                        <span style="color: #46b450;">● Active</span>
                                    <?php else: ?>
                                        <span style="color: #dc3232;">● Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($automation->execution_count); ?></td>
                                <td>
                                    <a href="?page=ptp-comms-automations&action=edit&id=<?php echo $automation->id; ?>">Edit</a> |
                                    <a href="?page=ptp-comms-automations&action=toggle&id=<?php echo $automation->id; ?>&_wpnonce=<?php echo wp_create_nonce('toggle_automation_' . $automation->id); ?>">
                                        <?php echo $automation->is_active ? 'Deactivate' : 'Activate'; ?>
                                    </a> |
                                    <a href="?page=ptp-comms-automations&action=delete&id=<?php echo $automation->id; ?>&_wpnonce=<?php echo wp_create_nonce('delete_automation_' . $automation->id); ?>" 
                                       onclick="return confirm('Are you sure you want to delete this automation?');" 
                                       style="color: #dc3232;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private static function render_form($automation_id = 0) {
        $automation = null;
        $is_edit = false;
        
        if ($automation_id > 0) {
            $automation = PTP_Comms_Hub_Automations::get_automation($automation_id);
            $is_edit = true;
        }
        
        $templates = PTP_Comms_Hub_Templates::get_all_templates();
        
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit Automation' : 'Add New Automation'; ?></h1>
            
            <form method="post" action="?page=ptp-comms-automations">
                <?php wp_nonce_field('ptp_comms_automation', 'ptp_comms_automation_nonce'); ?>
                
                <?php if ($is_edit): ?>
                    <input type="hidden" name="automation_id" value="<?php echo $automation_id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="name">Automation Name *</label></th>
                        <td>
                            <input type="text" id="name" name="name" value="<?php echo $automation ? esc_attr($automation->name) : ''; ?>" class="regular-text" required>
                            <p class="description">Give this automation a descriptive name</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="trigger_type">Trigger Type *</label></th>
                        <td>
                            <select id="trigger_type" name="trigger_type" class="regular-text" required>
                                <option value="">-- Select Trigger --</option>
                                <option value="order_placed" <?php selected($automation->trigger_type ?? '', 'order_placed'); ?>>Order Placed</option>
                                <option value="event_approaching_7d" <?php selected($automation->trigger_type ?? '', 'event_approaching_7d'); ?>>Event Approaching (7 days)</option>
                                <option value="event_approaching_1d" <?php selected($automation->trigger_type ?? '', 'event_approaching_1d'); ?>>Event Approaching (1 day)</option>
                                <option value="event_completed" <?php selected($automation->trigger_type ?? '', 'event_completed'); ?>>Event Completed</option>
                                <option value="new_contact" <?php selected($automation->trigger_type ?? '', 'new_contact'); ?>>New Contact</option>
                            </select>
                            <p class="description">When should this automation be triggered?</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="template_id">Message Template *</label></th>
                        <td>
                            <select id="template_id" name="template_id" class="regular-text" required>
                                <option value="">-- Select Template --</option>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template->id; ?>" <?php selected($automation->template_id ?? '', $template->id); ?>>
                                        <?php echo esc_html($template->name); ?> (<?php echo ucfirst($template->category); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Which template should be sent?</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="delay_minutes">Delay (minutes)</label></th>
                        <td>
                            <input type="number" id="delay_minutes" name="delay_minutes" value="<?php echo $automation ? esc_attr($automation->delay_minutes) : '0'; ?>" min="0" max="10080" class="small-text">
                            <p class="description">Delay before sending (0 = immediate, max 7 days = 10,080 minutes)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="is_active">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($automation->is_active ?? 1, 1); ?>>
                                Active (automation will run)
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php echo $is_edit ? 'Update Automation' : 'Create Automation'; ?>">
                    <a href="?page=ptp-comms-automations" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private static function handle_save() {
        $automation_id = isset($_POST['automation_id']) ? intval($_POST['automation_id']) : 0;
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'trigger_type' => sanitize_text_field($_POST['trigger_type']),
            'template_id' => intval($_POST['template_id']),
            'delay_minutes' => intval($_POST['delay_minutes']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        );
        
        if ($automation_id > 0) {
            PTP_Comms_Hub_Automations::update_automation($automation_id, $data);
            echo '<div class="notice notice-success"><p>Automation updated successfully!</p></div>';
        } else {
            PTP_Comms_Hub_Automations::create_automation($data);
            echo '<div class="notice notice-success"><p>Automation created successfully!</p></div>';
        }
    }
    
    private static function handle_toggle() {
        $automation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'toggle_automation_' . $automation_id)) {
            wp_die('Invalid nonce');
        }
        
        PTP_Comms_Hub_Automations::toggle_automation($automation_id);
        
        wp_redirect(admin_url('admin.php?page=ptp-comms-automations'));
        exit;
    }
    
    private static function handle_delete() {
        $automation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_automation_' . $automation_id)) {
            wp_die('Invalid nonce');
        }
        
        PTP_Comms_Hub_Automations::delete_automation($automation_id);
        
        wp_redirect(admin_url('admin.php?page=ptp-comms-automations'));
        exit;
    }
    
    private static function format_trigger_type($type) {
        $types = array(
            'order_placed' => 'Order Placed',
            'event_approaching_7d' => 'Event Approaching (7 days)',
            'event_approaching_1d' => 'Event Approaching (1 day)',
            'event_completed' => 'Event Completed',
            'new_contact' => 'New Contact'
        );
        
        return $types[$type] ?? $type;
    }
    
    private static function format_delay($minutes) {
        if ($minutes == 0) {
            return 'Immediate';
        } elseif ($minutes < 60) {
            return $minutes . ' min';
        } elseif ($minutes < 1440) {
            return round($minutes / 60, 1) . ' hrs';
        } else {
            return round($minutes / 1440, 1) . ' days';
        }
    }
}
