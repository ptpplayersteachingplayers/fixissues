<?php
class PTP_Comms_Hub_Admin_Page_Contacts {
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ptp_comms_contact_action');
            
            if (isset($_POST['save_contact'])) {
                self::handle_save_contact();
            } elseif (isset($_POST['bulk_action'])) {
                self::handle_bulk_action();
            }
        }
        
        if ($action === 'new' || $action === 'edit') {
            self::render_form($action === 'edit' ? intval($_GET['id'] ?? 0) : 0);
        } else {
            self::render_list();
        }
    }
    
    private static function render_list() {
        global $wpdb;
        
        // Get filter parameters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        
        // Build query
        $where = ['1=1'];
        $params = [];
        
        if ($search) {
            $where[] = "(parent_first_name LIKE %s OR parent_last_name LIKE %s OR parent_phone LIKE %s OR parent_email LIKE %s OR child_name LIKE %s)";
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $params = array_fill(0, 5, $search_param);
        }
        
        if ($filter === 'opted_in') {
            $where[] = 'opted_in = 1 AND opted_out = 0';
        } elseif ($filter === 'opted_out') {
            $where[] = 'opted_out = 1';
        } elseif ($filter === 'pending') {
            $where[] = 'opted_in = 0 AND opted_out = 0';
        }
        
        $where_sql = implode(' AND ', $where);
        
        if (!empty($params)) {
            $contacts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE {$where_sql} ORDER BY created_at DESC",
                ...$params
            ));
        } else {
            $contacts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE {$where_sql} ORDER BY created_at DESC");
        }
        
        // Get counts for filters
        $total_contacts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts");
        $opted_in_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 1 AND opted_out = 0");
        $opted_out_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_out = 1");
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_contacts WHERE opted_in = 0 AND opted_out = 0");
        
        ?>
        <div class="wrap ptp-comms-wrap">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="margin: 0;">Contacts</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="?page=ptp-comms-contacts&action=import" class="ptp-comms-button secondary">
                        <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span>
                        Import CSV
                    </a>
                    <a href="?page=ptp-comms-contacts&action=new" class="ptp-comms-button">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                        Add Contact
                    </a>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="ptp-comms-stats">
                <div class="ptp-comms-stat-box">
                    <h2><?php echo number_format($total_contacts); ?></h2>
                    <p>Total Contacts</p>
                </div>
                <div class="ptp-comms-stat-box green">
                    <h2><?php echo number_format($opted_in_count); ?></h2>
                    <p>Opted In</p>
                </div>
                <div class="ptp-comms-stat-box blue">
                    <h2><?php echo number_format($opted_out_count); ?></h2>
                    <p>Opted Out</p>
                </div>
                <div class="ptp-comms-stat-box purple">
                    <h2><?php echo number_format($pending_count); ?></h2>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="ptp-comms-card">
                <!-- Filters & Search -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <div class="ptp-comms-contact-filters">
                        <a href="?page=ptp-comms-contacts&filter=all" 
                           class="ptp-comms-button <?php echo $filter === 'all' ? '' : 'secondary'; ?> small">
                            All (<?php echo $total_contacts; ?>)
                        </a>
                        <a href="?page=ptp-comms-contacts&filter=opted_in" 
                           class="ptp-comms-button <?php echo $filter === 'opted_in' ? '' : 'secondary'; ?> small">
                            Opted In (<?php echo $opted_in_count; ?>)
                        </a>
                        <a href="?page=ptp-comms-contacts&filter=opted_out" 
                           class="ptp-comms-button <?php echo $filter === 'opted_out' ? '' : 'secondary'; ?> small">
                            Opted Out (<?php echo $opted_out_count; ?>)
                        </a>
                        <a href="?page=ptp-comms-contacts&filter=pending" 
                           class="ptp-comms-button <?php echo $filter === 'pending' ? '' : 'secondary'; ?> small">
                            Pending (<?php echo $pending_count; ?>)
                        </a>
                    </div>
                    
                    <form method="get" style="display: flex; gap: 10px;">
                        <input type="hidden" name="page" value="ptp-comms-contacts">
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" 
                               placeholder="Search contacts..." 
                               style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; width: 250px;">
                        <button type="submit" class="ptp-comms-button small">
                            <span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
                        </button>
                        <?php if ($search): ?>
                        <a href="?page=ptp-comms-contacts&filter=<?php echo $filter; ?>" class="ptp-comms-button small secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- Contacts Table -->
                <?php if (empty($contacts)): ?>
                <div class="ptp-comms-empty-state">
                    <?php if ($search): ?>
                    <span class="dashicons dashicons-search" style="font-size: 80px; opacity: 0.2;"></span>
                    <h3>No contacts found</h3>
                    <p>Try adjusting your search or filters.</p>
                    <?php else: ?>
                    <span class="dashicons dashicons-admin-users" style="font-size: 80px; opacity: 0.2;"></span>
                    <h3>No contacts yet</h3>
                    <p>Add your first contact to start building your audience.</p>
                    <a href="?page=ptp-comms-contacts&action=new" class="ptp-comms-button">Add Contact</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <form method="post" id="contacts-form">
                    <?php wp_nonce_field('ptp_comms_contact_action'); ?>
                    
                    <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                        <select name="bulk_action_type" style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Bulk Actions</option>
                            <option value="opt_in">Mark as Opted In</option>
                            <option value="opt_out">Mark as Opted Out</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" name="bulk_action" class="ptp-comms-button small secondary">Apply</button>
                    </div>
                    
                    <table class="ptp-comms-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)">
                                </th>
                                <th>Name & Contact</th>
                                <th>Child Info</th>
                                <th style="width: 120px; text-align: center;">Status</th>
                                <th style="width: 150px;">Added</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_contacts[]" value="<?php echo $contact->id; ?>" class="contact-checkbox">
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #FCB900; display: flex; align-items: center; justify-content: center; color: #0e0f11; font-weight: 700; flex-shrink: 0;">
                                            <?php 
                                            $initials = '';
                                            if ($contact->parent_first_name) $initials .= strtoupper(substr($contact->parent_first_name, 0, 1));
                                            if ($contact->parent_last_name) $initials .= strtoupper(substr($contact->parent_last_name, 0, 1));
                                            echo $initials ?: '?';
                                            ?>
                                        </div>
                                        <div>
                                            <strong style="display: block;">
                                                <?php echo esc_html($contact->parent_first_name . ' ' . $contact->parent_last_name); ?>
                                            </strong>
                                            <div style="font-size: 13px; color: #666;">
                                                <?php echo esc_html(ptp_comms_format_phone($contact->parent_phone)); ?>
                                                <?php if ($contact->parent_email): ?>
                                                <br><?php echo esc_html($contact->parent_email); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($contact->child_name): ?>
                                    <strong><?php echo esc_html($contact->child_name); ?></strong>
                                    <?php if ($contact->child_age): ?>
                                    <span style="color: #666; font-size: 13px;">(<?php echo $contact->child_age; ?> yrs)</span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($contact->opted_in && !$contact->opted_out): ?>
                                    <span class="ptp-comms-badge success">Opted In</span>
                                    <?php elseif ($contact->opted_out): ?>
                                    <span class="ptp-comms-badge error">Opted Out</span>
                                    <?php else: ?>
                                    <span class="ptp-comms-badge warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($contact->created_at)); ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $contact->id; ?>" 
                                       class="ptp-comms-button small secondary">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.contact-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
        </script>
        
        <style>
        .ptp-comms-contact-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        </style>
        <?php
    }
    
    private static function render_form($contact_id = 0) {
        global $wpdb;
        
        $contact = null;
        if ($contact_id > 0) {
            $contact = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ptp_contacts WHERE id = %d",
                $contact_id
            ));
        }
        
        $is_edit = ($contact_id > 0 && $contact);
        
        ?>
        <div class="wrap ptp-comms-wrap">
            <div style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-contacts" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span> Back to Contacts
                </a>
            </div>
            
            <div class="ptp-comms-card">
                <h1 style="margin-top: 0;"><?php echo $is_edit ? 'Edit Contact' : 'Add New Contact'; ?></h1>
                
                <form method="post">
                    <?php wp_nonce_field('ptp_comms_contact_action'); ?>
                    <?php if ($is_edit): ?>
                    <input type="hidden" name="contact_id" value="<?php echo $contact->id; ?>">
                    <?php endif; ?>
                    
                    <div class="ptp-comms-grid">
                        <!-- Left Column - Parent Info -->
                        <div>
                            <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #f0f0f1;">Parent Information</h3>
                            
                            <div class="ptp-comms-form-group">
                                <label>First Name *</label>
                                <input type="text" name="parent_first_name" class="ptp-comms-form-control" 
                                       value="<?php echo esc_attr($contact->parent_first_name ?? ''); ?>" required>
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Last Name *</label>
                                <input type="text" name="parent_last_name" class="ptp-comms-form-control" 
                                       value="<?php echo esc_attr($contact->parent_last_name ?? ''); ?>" required>
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="parent_phone" class="ptp-comms-form-control" 
                                       value="<?php echo esc_attr($contact->parent_phone ?? ''); ?>" 
                                       placeholder="(555) 123-4567" required>
                                <span class="ptp-comms-form-help">Primary contact number for SMS and calls</span>
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Email Address</label>
                                <input type="email" name="parent_email" class="ptp-comms-form-control" 
                                       value="<?php echo esc_attr($contact->parent_email ?? ''); ?>" 
                                       placeholder="parent@email.com">
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Zip Code</label>
                                <input type="text" name="zip_code" class="ptp-comms-form-control" 
                                       value="<?php echo esc_attr($contact->zip_code ?? ''); ?>" 
                                       placeholder="19087">
                            </div>
                        </div>
                        
                        <!-- Right Column - Child Info & Status -->
                        <div>
                            <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 2px solid #f0f0f1;">Child Information</h3>
                            
                            <div class="ptp-comms-form-group">
                                <label>Child's Name</label>
                                <input type="text" name="child_name" class="ptp-comms-form-control" 
                                       value="<?php echo esc_attr($contact->child_name ?? ''); ?>" 
                                       placeholder="First name">
                            </div>
                            
                            <div class="ptp-comms-form-group">
                                <label>Child's Age</label>
                                <input type="number" name="child_age" class="ptp-comms-form-control" 
                                       value="<?php echo esc_attr($contact->child_age ?? ''); ?>" 
                                       placeholder="8" min="0" max="18">
                                <span class="ptp-comms-form-help">Age in years</span>
                            </div>
                            
                            <h3 style="margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f1;">Opt-In Status</h3>
                            
                            <div class="ptp-comms-form-group">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                    <input type="checkbox" name="opted_in" value="1" 
                                           <?php checked($contact->opted_in ?? 1, 1); ?>>
                                    <span>Contact has opted in to receive messages</span>
                                </label>
                                <span class="ptp-comms-form-help">
                                    Required for sending SMS and voice messages
                                </span>
                            </div>
                            
                            <?php if ($is_edit): ?>
                            <div class="ptp-comms-alert info" style="margin-top: 20px;">
                                <strong>Contact Created:</strong> <?php echo date('M j, Y \a\t g:i A', strtotime($contact->created_at)); ?><br>
                                <?php if ($contact->last_message_at): ?>
                                <strong>Last Message:</strong> <?php echo date('M j, Y \a\t g:i A', strtotime($contact->last_message_at)); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #f0f0f1; display: flex; gap: 15px;">
                        <button type="submit" name="save_contact" class="ptp-comms-button">
                            <span class="dashicons dashicons-yes" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php echo $is_edit ? 'Update Contact' : 'Add Contact'; ?>
                        </button>
                        <a href="?page=ptp-comms-contacts" class="ptp-comms-button secondary">Cancel</a>
                        
                        <?php if ($is_edit): ?>
                        <button type="button" onclick="if(confirm('Are you sure you want to delete this contact?')) window.location='?page=ptp-comms-contacts&action=delete&id=<?php echo $contact->id; ?>'" 
                                class="ptp-comms-button danger" style="margin-left: auto;">
                            Delete Contact
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .ptp-comms-form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        .ptp-comms-form-control:focus {
            outline: none;
            border-color: #FCB900;
            box-shadow: 0 0 0 1px #FCB900;
        }
        .ptp-comms-form-group {
            margin-bottom: 20px;
        }
        .ptp-comms-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0e0f11;
        }
        .ptp-comms-form-help {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #666;
            font-style: italic;
        }
        </style>
        <?php
    }
    
    private static function handle_save_contact() {
        global $wpdb;
        
        $contact_id = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
        
        $data = [
            'parent_first_name' => sanitize_text_field($_POST['parent_first_name']),
            'parent_last_name' => sanitize_text_field($_POST['parent_last_name']),
            'parent_phone' => ptp_comms_normalize_phone($_POST['parent_phone']),
            'parent_email' => sanitize_email($_POST['parent_email']),
            'child_name' => sanitize_text_field($_POST['child_name']),
            'child_age' => !empty($_POST['child_age']) ? intval($_POST['child_age']) : null,
            'zip_code' => sanitize_text_field($_POST['zip_code']),
            'opted_in' => isset($_POST['opted_in']) ? 1 : 0,
        ];
        
        if ($contact_id > 0) {
            // Update existing
            $wpdb->update(
                $wpdb->prefix . 'ptp_contacts',
                $data,
                ['id' => $contact_id]
            );
            $redirect = add_query_arg(['page' => 'ptp-comms-contacts', 'message' => 'updated'], admin_url('admin.php'));
        } else {
            // Create new
            $wpdb->insert($wpdb->prefix . 'ptp_contacts', $data);
            $contact_id = $wpdb->insert_id;
            $redirect = add_query_arg(['page' => 'ptp-comms-contacts', 'message' => 'created'], admin_url('admin.php'));
        }
        
        wp_redirect($redirect);
        exit;
    }
    
    private static function handle_bulk_action() {
        if (empty($_POST['selected_contacts']) || empty($_POST['bulk_action_type'])) {
            wp_redirect(add_query_arg(['page' => 'ptp-comms-contacts'], admin_url('admin.php')));
            exit;
        }
        
        global $wpdb;
        
        $contact_ids = array_map('intval', $_POST['selected_contacts']);
        $action = sanitize_text_field($_POST['bulk_action_type']);
        
        $placeholders = implode(',', array_fill(0, count($contact_ids), '%d'));
        
        switch ($action) {
            case 'opt_in':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ptp_contacts SET opted_in = 1, opted_out = 0 WHERE id IN ($placeholders)",
                    ...$contact_ids
                ));
                break;
                
            case 'opt_out':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}ptp_contacts SET opted_out = 1 WHERE id IN ($placeholders)",
                    ...$contact_ids
                ));
                break;
                
            case 'delete':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}ptp_contacts WHERE id IN ($placeholders)",
                    ...$contact_ids
                ));
                break;
        }
        
        wp_redirect(add_query_arg(['page' => 'ptp-comms-contacts', 'message' => 'bulk_action'], admin_url('admin.php')));
        exit;
    }
}
