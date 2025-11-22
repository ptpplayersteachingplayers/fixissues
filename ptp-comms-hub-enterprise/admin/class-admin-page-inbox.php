<?php
/**
 * PTP Communications Hub - Inbox Admin Page
 * Enhanced with professional PTP styling and robust features
 * Version: 3.0
 */

class PTP_Comms_Hub_Admin_Page_Inbox {
    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $conversation_id = isset($_GET['conversation']) ? intval($_GET['conversation']) : 0;
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ptp_comms_inbox_action');
            
            if (isset($_POST['send_message'])) {
                self::handle_send_message();
            } elseif (isset($_POST['mark_read'])) {
                self::handle_mark_read();
            } elseif (isset($_POST['bulk_action'])) {
                self::handle_bulk_action();
            } elseif (isset($_POST['archive_conversation'])) {
                self::handle_archive();
            }
        }
        
        if ($action === 'view' && $conversation_id > 0) {
            self::render_conversation($conversation_id);
        } else {
            self::render_list();
        }
    }
    
    private static function render_list() {
        global $wpdb;
        
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Build query based on filter
        $where = "conv.status = 'active'";
        if ($filter === 'unread') {
            $where .= " AND conv.unread_count > 0";
        } elseif ($filter === 'archived') {
            $where = "conv.status = 'archived'";
        }
        
        // Add search condition
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (c.parent_first_name LIKE %s OR c.parent_last_name LIKE %s OR c.parent_phone LIKE %s OR conv.last_message LIKE %s)",
                $search_like, $search_like, $search_like, $search_like
            );
        }
        
        // Get total count for pagination
        $total_items = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}ptp_conversations conv
            JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
            WHERE {$where}
        ");
        
        $total_pages = ceil($total_items / $per_page);
        
        $conversations = $wpdb->get_results("
            SELECT conv.*, c.parent_first_name, c.parent_last_name, c.parent_phone, c.parent_email
            FROM {$wpdb->prefix}ptp_conversations conv
            JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
            WHERE {$where}
            ORDER BY conv.last_message_at DESC
            LIMIT {$per_page} OFFSET {$offset}
        ");
        
        $total_unread = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE unread_count > 0 AND status = 'active'");
        $total_active = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE status = 'active'");
        $total_archived = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ptp_conversations WHERE status = 'archived'");
        
        // Success/error messages
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        
        ?>
        <div class="wrap ptp-comms-wrap">
            <!-- Header Section -->
            <div class="ptp-flex ptp-justify-between ptp-items-center ptp-mb-6" style="margin-bottom: 30px;">
                <div class="ptp-flex ptp-items-center ptp-gap-3">
                    <h1 style="margin: 0;">
                        <span class="dashicons dashicons-email-alt" style="color: var(--ptp-primary); vertical-align: middle;"></span>
                        Inbox
                    </h1>
                    <?php if ($total_unread > 0): ?>
                    <span class="ptp-comms-badge error ptp-pulse" style="font-size: 14px;">
                        <?php echo $total_unread; ?> new
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="ptp-flex ptp-gap-2">
                    <button onclick="location.reload()" class="ptp-comms-button secondary small">
                        <span class="dashicons dashicons-update"></span>
                        Refresh
                    </button>
                    <a href="?page=ptp-comms-campaigns&action=new" class="ptp-comms-button small">
                        <span class="dashicons dashicons-email-alt"></span>
                        New Campaign
                    </a>
                </div>
            </div>

            <!-- Status Messages -->
            <?php if ($message === 'sent'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Message sent successfully!</strong> Your message has been delivered.
            </div>
            <?php elseif ($message === 'marked_read'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Marked as read.</strong> The conversation has been updated.
            </div>
            <?php elseif ($message === 'archived'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Conversation archived.</strong> You can find it in the archived filter.
            </div>
            <?php elseif ($message === 'bulk_success'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Bulk action completed!</strong> Selected conversations have been updated.
            </div>
            <?php elseif ($message === 'error'): ?>
            <div class="ptp-comms-alert danger">
                <span class="dashicons dashicons-warning"></span>
                <strong>Error!</strong> Something went wrong. Please try again.
            </div>
            <?php endif; ?>

            <!-- Filters & Search Bar -->
            <div class="ptp-comms-card compact" style="margin-bottom: 20px;">
                <div class="ptp-flex ptp-justify-between ptp-items-center" style="flex-wrap: wrap; gap: 15px;">
                    <!-- Filter Tabs -->
                    <div class="ptp-comms-tabs">
                        <a href="?page=ptp-comms-inbox&filter=all<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>" 
                           class="ptp-comms-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-email"></span>
                            All
                            <span class="ptp-comms-badge secondary"><?php echo number_format($total_active); ?></span>
                        </a>
                        <a href="?page=ptp-comms-inbox&filter=unread<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>" 
                           class="ptp-comms-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-warning"></span>
                            Unread
                            <?php if ($total_unread > 0): ?>
                            <span class="ptp-comms-badge error"><?php echo number_format($total_unread); ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?page=ptp-comms-inbox&filter=archived<?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>" 
                           class="ptp-comms-tab <?php echo $filter === 'archived' ? 'active' : ''; ?>">
                            <span class="dashicons dashicons-archive"></span>
                            Archived
                            <span class="ptp-comms-badge secondary"><?php echo number_format($total_archived); ?></span>
                        </a>
                    </div>
                    
                    <!-- Search Form -->
                    <form method="get" action="" class="ptp-search-bar" style="min-width: 300px;">
                        <input type="hidden" name="page" value="ptp-comms-inbox">
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                        <div class="ptp-search-input-wrapper">
                            <span class="dashicons dashicons-search"></span>
                            <input type="text" 
                                   name="s" 
                                   value="<?php echo esc_attr($search); ?>" 
                                   placeholder="Search conversations..."
                                   class="ptp-search-input">
                            <?php if (!empty($search)): ?>
                            <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>" class="ptp-search-clear">
                                <span class="dashicons dashicons-no-alt"></span>
                            </a>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="ptp-comms-button small">Search</button>
                    </form>
                </div>
            </div>
            
            <?php if (empty($conversations)): ?>
            <div class="ptp-comms-card">
                <div class="ptp-comms-empty-state">
                    <span class="dashicons dashicons-email-alt" style="font-size: 80px; opacity: 0.2; color: var(--ptp-primary);"></span>
                    <h3>
                        <?php if (!empty($search)): ?>
                            No conversations match your search
                        <?php elseif ($filter === 'unread'): ?>
                            No unread messages
                        <?php elseif ($filter === 'archived'): ?>
                            No archived conversations
                        <?php else: ?>
                            No conversations yet
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            Try adjusting your search terms or <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?>">clear the search</a>.
                        <?php else: ?>
                            Conversations will appear here when contacts reply to your messages.
                        <?php endif; ?>
                    </p>
                    <?php if (empty($search)): ?>
                    <a href="?page=ptp-comms-campaigns&action=new" class="ptp-comms-button">
                        <span class="dashicons dashicons-email-alt"></span>
                        Send Your First Campaign
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            
            <!-- Bulk Actions Form -->
            <form method="post" id="ptp-inbox-form">
                <?php wp_nonce_field('ptp_comms_inbox_action'); ?>
                
                <div class="ptp-comms-card no-padding">
                    <!-- Bulk Actions Bar -->
                    <div style="padding: 15px 20px; border-bottom: 2px solid var(--ptp-gray-100); background: var(--ptp-gray-50);">
                        <div class="ptp-flex ptp-items-center ptp-gap-3">
                            <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;">
                                <input type="checkbox" id="select-all-conversations" style="margin: 0;">
                                <span style="font-weight: 600; color: var(--ptp-gray-700);">Select All</span>
                            </label>
                            
                            <div class="ptp-flex ptp-items-center ptp-gap-2" id="bulk-actions-bar" style="display: none;">
                                <span style="color: var(--ptp-gray-600); font-size: 14px;" id="selected-count">0 selected</span>
                                <button type="submit" name="bulk_action" value="mark_read" class="ptp-comms-button small secondary">
                                    <span class="dashicons dashicons-yes"></span>
                                    Mark as Read
                                </button>
                                <button type="submit" name="bulk_action" value="archive" class="ptp-comms-button small secondary">
                                    <span class="dashicons dashicons-archive"></span>
                                    Archive
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conversations Table -->
                    <div style="overflow-x: auto;">
                        <table class="ptp-comms-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th style="width: 250px;">Contact</th>
                                    <th>Last Message</th>
                                    <th style="width: 100px; text-align: center;">Status</th>
                                    <th style="width: 80px; text-align: center;">Unread</th>
                                    <th style="width: 150px;">Last Activity</th>
                                    <th style="width: 120px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conversations as $conv): ?>
                                <tr class="<?php echo $conv->unread_count > 0 ? 'ptp-conversation-unread' : ''; ?>" 
                                    style="<?php echo $conv->unread_count > 0 ? 'background: #fff9e6;' : ''; ?>" data-conversation-id="<?php echo $conv->id; ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="conversation_ids[]" value="<?php echo $conv->id; ?>" class="conversation-checkbox">
                                    </td>
                                    <td>
                                        <div class="ptp-flex ptp-items-center ptp-gap-3">
                                            <div class="ptp-avatar">
                                                <?php echo strtoupper(substr($conv->parent_first_name, 0, 1) . substr($conv->parent_last_name, 0, 1)); ?>
                                            </div>
                                            <div style="min-width: 0;">
                                                <strong style="display: block; font-weight: 600; color: var(--ptp-black);">
                                                    <?php echo esc_html($conv->parent_first_name . ' ' . $conv->parent_last_name); ?>
                                                </strong>
                                                <small style="color: var(--ptp-gray-600); display: block; margin-top: 2px;">
                                                    <?php echo esc_html(ptp_comms_format_phone($conv->parent_phone)); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ptp-truncate" style="max-width: 400px;">
                                            <?php echo esc_html($conv->last_message ?: 'No messages yet'); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($conv->last_message_direction): ?>
                                        <span class="ptp-comms-badge <?php echo $conv->last_message_direction === 'inbound' ? 'success' : 'info'; ?>">
                                            <span class="dashicons dashicons-arrow-<?php echo $conv->last_message_direction === 'inbound' ? 'down' : 'up'; ?>-alt" 
                                                  style="font-size: 14px; vertical-align: middle;">
                                            </span>
                                            <?php echo ucfirst($conv->last_message_direction); ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($conv->unread_count > 0): ?>
                                        <span class="ptp-comms-badge error ptp-pulse">
                                            <?php echo $conv->unread_count; ?>
                                        </span>
                                        <?php else: ?>
                                        <span style="color: var(--ptp-gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="color: var(--ptp-gray-600); font-size: 13px;">
                                            <?php 
                                            if ($conv->last_message_at) {
                                                echo human_time_diff(strtotime($conv->last_message_at), current_time('timestamp')) . ' ago';
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="?page=ptp-comms-inbox&action=view&conversation=<?php echo $conv->id; ?>" 
                                           class="ptp-comms-button small">
                                            <span class="dashicons dashicons-visibility"></span>
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="ptp-pagination">
                <?php if ($paged > 1): ?>
                <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>&paged=<?php echo ($paged - 1); ?>" 
                   class="ptp-pagination-item">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </a>
                <?php else: ?>
                <span class="ptp-pagination-item disabled">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $paged - 2); $i <= min($total_pages, $paged + 2); $i++): ?>
                <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>&paged=<?php echo $i; ?>" 
                   class="ptp-pagination-item <?php echo $i === $paged ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($paged < $total_pages): ?>
                <a href="?page=ptp-comms-inbox&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&s=' . urlencode($search) : ''; ?>&paged=<?php echo ($paged + 1); ?>" 
                   class="ptp-pagination-item">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
                <?php else: ?>
                <span class="ptp-pagination-item disabled">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
        
        <?php self::render_inbox_scripts($filter, $total_unread); ?>
        <?php self::render_inbox_styles(); ?>
        <?php
    }
    
    private static function render_conversation($conversation_id) {
        global $wpdb;
        
        $conversation = $wpdb->get_row($wpdb->prepare("
            SELECT conv.*, c.*
            FROM {$wpdb->prefix}ptp_conversations conv
            JOIN {$wpdb->prefix}ptp_contacts c ON conv.contact_id = c.id
            WHERE conv.id = %d
        ", $conversation_id));
        
        if (!$conversation) {
            echo '<div class="wrap ptp-comms-wrap"><div class="ptp-comms-card"><p>Conversation not found.</p></div></div>';
            return;
        }
        
        // Mark as read
        PTP_Comms_Hub_Conversations::mark_as_read($conversation_id);
        
        // Get messages
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ptp_messages
            WHERE conversation_id = %d
            ORDER BY created_at ASC
        ", $conversation_id));
        
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $settings = get_option('ptp_comms_settings', []);
        $twilio_configured = !empty($settings['twilio_account_sid']) && !empty($settings['twilio_auth_token']);
        
        ?>
        <div class="wrap ptp-comms-wrap">
            <!-- Header with Breadcrumb -->
            <div class="ptp-flex ptp-items-center ptp-gap-3" style="margin-bottom: 20px;">
                <a href="?page=ptp-comms-inbox" class="ptp-comms-button secondary small">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    Back to Inbox
                </a>
                <span style="color: var(--ptp-gray-400);">/</span>
                <h1 style="margin: 0; font-size: 24px;">
                    Conversation with <?php echo esc_html($conversation->parent_first_name . ' ' . $conversation->parent_last_name); ?>
                </h1>
            </div>
            
            <!-- Status Messages -->
            <?php if ($message === 'sent'): ?>
            <div class="ptp-comms-alert success">
                <span class="dashicons dashicons-yes"></span>
                <strong>Message sent successfully!</strong> Your message has been delivered.
            </div>
            <?php elseif ($message === 'error'): ?>
            <div class="ptp-comms-alert danger">
                <span class="dashicons dashicons-warning"></span>
                <strong>Error!</strong> Failed to send message. Please check your Twilio configuration.
            </div>
            <?php endif; ?>
            
            <!-- Two Column Layout -->
            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 20px;">
                <!-- Main Conversation Area -->
                <div>
                    <!-- Conversation Thread -->
                    <div class="ptp-comms-card" style="padding: 0;">
                        <div style="padding: 20px; border-bottom: 2px solid var(--ptp-gray-100); background: var(--ptp-gray-50);">
                            <div class="ptp-flex ptp-justify-between ptp-items-center">
                                <div class="ptp-flex ptp-items-center ptp-gap-3">
                                    <div class="ptp-avatar" style="width: 48px; height: 48px; font-size: 18px;">
                                        <?php echo strtoupper(substr($conversation->parent_first_name, 0, 1) . substr($conversation->parent_last_name, 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong style="display: block; font-size: 18px; color: var(--ptp-black);">
                                            <?php echo esc_html($conversation->parent_first_name . ' ' . $conversation->parent_last_name); ?>
                                        </strong>
                                        <div style="color: var(--ptp-gray-600); font-size: 14px; margin-top: 2px;">
                                            <?php echo esc_html(ptp_comms_format_phone($conversation->parent_phone)); ?>
                                            <?php if ($conversation->parent_email): ?>
                                            · <?php echo esc_html($conversation->parent_email); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <form method="post" style="margin: 0;" onsubmit="return confirm('Are you sure you want to archive this conversation?');">
                                    <?php wp_nonce_field('ptp_comms_inbox_action'); ?>
                                    <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                                    <button type="submit" name="archive_conversation" class="ptp-comms-button secondary small">
                                        <span class="dashicons dashicons-archive"></span>
                                        Archive
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Messages Thread -->
                        <div class="ptp-conversation-thread" id="conversation-thread">
                            <?php if (empty($messages)): ?>
                            <div class="ptp-comms-empty-state" style="padding: 40px 20px;">
                                <span class="dashicons dashicons-email-alt" style="font-size: 60px; opacity: 0.15; color: var(--ptp-primary);"></span>
                                <p style="margin: 10px 0 0; color: var(--ptp-gray-500);">No messages yet</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="ptp-message <?php echo $msg->direction; ?>">
                                <div class="ptp-message-bubble">
                                    <div class="ptp-message-content">
                                        <?php echo nl2br(esc_html($msg->message_body)); ?>
                                    </div>
                                    <div class="ptp-message-meta">
                                        <?php 
                                        echo ucfirst($msg->message_type);
                                        if ($msg->status) {
                                            echo ' · ' . ucfirst($msg->status);
                                        }
                                        echo ' · ' . date('M j, Y g:i A', strtotime($msg->created_at));
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message Form -->
                        <div style="padding: 20px; border-top: 2px solid var(--ptp-gray-100); background: var(--ptp-gray-50);">
                            <?php if ($twilio_configured): ?>
                            <form method="post" id="send-message-form">
                                <?php wp_nonce_field('ptp_comms_inbox_action'); ?>
                                <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                                <input type="hidden" name="contact_id" value="<?php echo $conversation->contact_id; ?>">
                                
                                <div style="display: grid; grid-template-columns: 120px 1fr auto; gap: 10px; align-items: end;">
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--ptp-gray-700); margin-bottom: 5px;">
                                            Message Type
                                        </label>
                                        <select name="message_type" class="ptp-comms-form-control" style="height: 44px;">
                                            <option value="sms">SMS</option>
                                            <option value="voice">Voice</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--ptp-gray-700); margin-bottom: 5px;">
                                            Your Message
                                        </label>
                                        <textarea name="message" 
                                                  rows="2" 
                                                  placeholder="Type your message..." 
                                                  class="ptp-comms-form-control" 
                                                  style="resize: vertical; min-height: 44px;" 
                                                  required></textarea>
                                    </div>
                                    
                                    <button type="submit" name="send_message" class="ptp-comms-button" style="height: 44px; padding: 0 24px;">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        Send
                                    </button>
                                </div>
                            </form>
                            <?php else: ?>
                            <div class="ptp-comms-info-box danger">
                                <strong>Twilio not configured.</strong> 
                                <a href="?page=ptp-comms-settings">Configure Twilio in settings</a> to send messages.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Info Sidebar -->
                <div>
                    <div class="ptp-comms-card">
                        <h3 style="margin-top: 0; padding-bottom: 12px; border-bottom: 2px solid var(--ptp-gray-100);">
                            Contact Details
                        </h3>
                        
                        <div class="ptp-contact-info-grid">
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Status</div>
                                <?php if ($conversation->opted_in && !$conversation->opted_out): ?>
                                <span class="ptp-comms-badge success">
                                    <span class="dashicons dashicons-yes"></span>
                                    Opted In
                                </span>
                                <?php else: ?>
                                <span class="ptp-comms-badge error">
                                    <span class="dashicons dashicons-warning"></span>
                                    Opted Out
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($conversation->child_name): ?>
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Child Name</div>
                                <div class="ptp-info-value"><?php echo esc_html($conversation->child_name); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($conversation->child_age): ?>
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Child Age</div>
                                <div class="ptp-info-value"><?php echo esc_html($conversation->child_age); ?> years</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($conversation->zip_code): ?>
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Zip Code</div>
                                <div class="ptp-info-value"><?php echo esc_html($conversation->zip_code); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="ptp-info-item">
                                <div class="ptp-info-label">Total Messages</div>
                                <div class="ptp-info-value"><?php echo count($messages); ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--ptp-gray-100);">
                            <a href="?page=ptp-comms-contacts&action=edit&id=<?php echo $conversation->contact_id; ?>" 
                               class="ptp-comms-button secondary small ptp-w-full ptp-text-center">
                                <span class="dashicons dashicons-edit"></span>
                                Edit Contact
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Info Card -->
                    <div class="ptp-comms-card" style="margin-top: 20px; background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);">
                        <h4 style="margin: 0 0 10px; color: var(--ptp-black); font-size: 14px; font-weight: 600;">
                            <span class="dashicons dashicons-info" style="color: var(--ptp-primary);"></span>
                            Tips
                        </h4>
                        <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--ptp-gray-700); line-height: 1.6;">
                            <li>Keep messages clear and concise</li>
                            <li>Respect opt-out preferences</li>
                            <li>Response time matters for engagement</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php self::render_conversation_scripts(); ?>
        <?php self::render_conversation_styles(); ?>
        <?php
    }
    
    // Handler functions
    private static function handle_send_message() {
        if (!isset($_POST['conversation_id'], $_POST['contact_id'], $_POST['message'], $_POST['message_type'])) {
            return;
        }
        
        $conversation_id = intval($_POST['conversation_id']);
        $contact_id = intval($_POST['contact_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $message_type = sanitize_text_field($_POST['message_type']);
        
        // Send via Twilio
        if ($message_type === 'sms') {
            $result = PTP_Comms_Hub_SMS_Service::send_sms($contact_id, $message);
        } else {
            $result = PTP_Comms_Hub_Voice_Service::make_call($contact_id, $message);
        }
        
        if ($result['success']) {
            PTP_Comms_Hub_Conversations::update_conversation($conversation_id, $message, 'outbound');
            
            wp_redirect(add_query_arg([
                'page' => 'ptp-comms-inbox',
                'action' => 'view',
                'conversation' => $conversation_id,
                'message' => 'sent'
            ], admin_url('admin.php')));
            exit;
        } else {
            wp_redirect(add_query_arg([
                'page' => 'ptp-comms-inbox',
                'action' => 'view',
                'conversation' => $conversation_id,
                'message' => 'error'
            ], admin_url('admin.php')));
            exit;
        }
    }
    
    private static function handle_mark_read() {
        if (!isset($_POST['conversation_id'])) {
            return;
        }
        
        $conversation_id = intval($_POST['conversation_id']);
        PTP_Comms_Hub_Conversations::mark_as_read($conversation_id);
        
        wp_redirect(add_query_arg([
            'page' => 'ptp-comms-inbox',
            'message' => 'marked_read'
        ], admin_url('admin.php')));
        exit;
    }
    
    private static function handle_bulk_action() {
        if (!isset($_POST['bulk_action']) || !isset($_POST['conversation_ids'])) {
            return;
        }
        
        global $wpdb;
        $action = sanitize_text_field($_POST['bulk_action']);
        $conversation_ids = array_map('intval', $_POST['conversation_ids']);
        
        if (empty($conversation_ids)) {
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($conversation_ids), '%d'));
        
        if ($action === 'mark_read') {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}ptp_conversations 
                SET unread_count = 0 
                WHERE id IN ($placeholders)
            ", $conversation_ids));
        } elseif ($action === 'archive') {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}ptp_conversations 
                SET status = 'archived' 
                WHERE id IN ($placeholders)
            ", $conversation_ids));
        }
        
        wp_redirect(add_query_arg([
            'page' => 'ptp-comms-inbox',
            'message' => 'bulk_success'
        ], admin_url('admin.php')));
        exit;
    }
    
    private static function handle_archive() {
        if (!isset($_POST['conversation_id'])) {
            return;
        }
        
        global $wpdb;
        $conversation_id = intval($_POST['conversation_id']);
        
        $wpdb->update(
            $wpdb->prefix . 'ptp_conversations',
            ['status' => 'archived'],
            ['id' => $conversation_id],
            ['%s'],
            ['%d']
        );
        
        wp_redirect(add_query_arg([
            'page' => 'ptp-comms-inbox',
            'message' => 'archived'
        ], admin_url('admin.php')));
        exit;
    }
    
    // Script rendering functions
    private static function render_inbox_scripts($filter, $total_unread) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Select all functionality
            $('#select-all-conversations').on('change', function() {
                $('.conversation-checkbox').prop('checked', $(this).is(':checked'));
                updateBulkActionsBar();
            });
            
            $('.conversation-checkbox').on('change', function() {
                updateBulkActionsBar();
                const total = $('.conversation-checkbox').length;
                const checked = $('.conversation-checkbox:checked').length;
                $('#select-all-conversations').prop('checked', total === checked);
            });
            
            function updateBulkActionsBar() {
                const checked = $('.conversation-checkbox:checked').length;
                if (checked > 0) {
                    $('#bulk-actions-bar').show();
                    $('#selected-count').text(checked + ' selected');
                } else {
                    $('#bulk-actions-bar').hide();
                }
            }
            
            $('button[name="bulk_action"]').on('click', function(e) {
                const checked = $('.conversation-checkbox:checked').length;
                if (checked === 0) {
                    e.preventDefault();
                    alert('Please select at least one conversation.');
                    return false;
                }
                
                const action = $(this).val();
                const actionText = action === 'mark_read' ? 'mark as read' : 'archive';
                
                if (!confirm('Are you sure you want to ' + actionText + ' ' + checked + ' conversation(s)?')) {
                    e.preventDefault();
                    return false;
                }
            });
            
            <?php if ($filter === 'unread' || $total_unread > 0): ?>
            setInterval(function() {
                $.get('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'ptp_check_new_messages'
                }, function(response) {
                    if (response.success && response.data.new_count > 0) {
                        const notice = $('<div class="ptp-comms-alert info" style="position: fixed; top: 32px; right: 20px; z-index: 999999; animation: slideIn 0.3s ease; max-width: 400px;">')
                            .html('<span class="dashicons dashicons-email-alt"></span><strong>New messages!</strong> <a href="#" onclick="location.reload(); return false;" style="text-decoration: underline;">Refresh to view</a>')
                            .appendTo('body');
                        
                        setTimeout(function() {
                            notice.fadeOut(300, function() { $(this).remove(); });
                        }, 5000);
                    }
                });
            }, 30000);
            <?php endif; ?>
        });
        </script>
        <?php
    }
    
    private static function render_conversation_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Auto-scroll to bottom of conversation
            const thread = document.getElementById('conversation-thread');
            if (thread) {
                thread.scrollTop = thread.scrollHeight;
            }
            
            // Auto-expand textarea
            $('textarea[name="message"]').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Form validation
            $('#send-message-form').on('submit', function(e) {
                const message = $('textarea[name="message"]').val().trim();
                if (!message) {
                    e.preventDefault();
                    alert('Please enter a message.');
                    return false;
                }
            });
        });
        </script>
        <?php
    }
    
    private static function render_inbox_styles() {
        ?>
        <style>
        .ptp-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ptp-primary) 0%, var(--ptp-primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ptp-black);
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(252, 185, 0, 0.3);
        }
        
        .ptp-conversation-unread {
            border-left: 4px solid var(--ptp-primary) !important;
        }
        
        .ptp-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .ptp-search-input-wrapper {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }
        
        .ptp-search-input-wrapper .dashicons {
            position: absolute;
            left: 12px;
            color: var(--ptp-gray-400);
            pointer-events: none;
        }
        
        .ptp-search-input {
            width: 100%;
            padding: 10px 40px 10px 40px;
            border: 2px solid var(--ptp-gray-300);
            border-radius: var(--ptp-radius);
            font-size: 14px;
            transition: var(--ptp-transition);
        }
        
        .ptp-search-input:focus {
            border-color: var(--ptp-primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(252, 185, 0, 0.1);
        }
        
        .ptp-search-clear {
            position: absolute;
            right: 8px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ptp-gray-400);
            transition: var(--ptp-transition);
            border-radius: var(--ptp-radius-sm);
        }
        
        .ptp-search-clear:hover {
            color: var(--ptp-danger);
            background: var(--ptp-gray-100);
        }
        
        .ptp-comms-alert {
            padding: 15px 20px;
            border-radius: var(--ptp-radius);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            margin-bottom: 20px;
        }
        
        .ptp-comms-alert .dashicons {
            flex-shrink: 0;
        }
        
        .ptp-comms-alert.success {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f4 100%);
            border-color: var(--ptp-success);
            color: #1b5e20;
        }
        
        .ptp-comms-alert.danger {
            background: linear-gradient(135deg, #ffebee 0%, #fff5f5 100%);
            border-color: var(--ptp-danger);
            color: #b71c1c;
        }
        
        .ptp-comms-alert.info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f0f7fc 100%);
            border-color: var(--ptp-info);
            color: #01579b;
        }
        
        @media (max-width: 768px) {
            .ptp-search-bar {
                flex-direction: column;
                width: 100%;
            }
        }
        </style>
        <?php
    }
    
    private static function render_conversation_styles() {
        ?>
        <style>
        .ptp-conversation-thread {
            max-height: 600px;
            overflow-y: auto;
            padding: 20px;
            background: var(--ptp-gray-50);
            border-radius: var(--ptp-radius);
        }
        
        .ptp-message {
            margin-bottom: 20px;
            display: flex;
            animation: slideIn 0.3s ease;
        }
        
        .ptp-message.outbound {
            justify-content: flex-end;
        }
        
        .ptp-message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: var(--ptp-radius-lg);
            box-shadow: var(--ptp-shadow-sm);
        }
        
        .ptp-message.inbound .ptp-message-bubble {
            background: var(--ptp-white);
            border: 1px solid var(--ptp-gray-200);
        }
        
        .ptp-message.outbound .ptp-message-bubble {
            background: linear-gradient(135deg, var(--ptp-primary) 0%, var(--ptp-primary-dark) 100%);
            color: var(--ptp-black);
        }
        
        .ptp-message-content {
            margin-bottom: 8px;
            line-height: 1.5;
            font-size: 14px;
        }
        
        .ptp-message-meta {
            font-size: 11px;
            color: var(--ptp-gray-500);
        }
        
        .ptp-message.outbound .ptp-message-meta {
            color: var(--ptp-black);
            opacity: 0.7;
        }
        
        .ptp-comms-form-control {
            padding: 10px 12px;
            border: 2px solid var(--ptp-gray-300);
            border-radius: var(--ptp-radius);
            font-size: 14px;
            font-family: inherit;
            width: 100%;
            transition: var(--ptp-transition);
        }
        
        .ptp-comms-form-control:focus {
            outline: none;
            border-color: var(--ptp-primary);
            box-shadow: 0 0 0 3px rgba(252, 185, 0, 0.1);
        }
        
        .ptp-contact-info-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }
        
        .ptp-info-item {
            padding: 12px 15px;
            background: var(--ptp-gray-50);
            border-radius: var(--ptp-radius);
            border-left: 3px solid var(--ptp-primary);
        }
        
        .ptp-info-label {
            font-size: 11px;
            color: var(--ptp-gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .ptp-info-value {
            font-size: 14px;
            color: var(--ptp-black);
            font-weight: 500;
        }
        
        @media (max-width: 1024px) {
            .wrap.ptp-comms-wrap > div:first-of-type {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        <?php
    }
}
