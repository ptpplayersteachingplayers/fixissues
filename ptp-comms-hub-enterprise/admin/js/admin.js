(function($) {
    'use strict';
    
    // Configuration
    const PTPCommsAdmin = {
        config: {
            autoRefreshInterval: 30000,
            notificationDuration: 5000,
            tooltipDelay: 200
        },
        
        init: function() {
            this.bindEvents();
            this.initializeComponents();
            this.setupInboxAutoRefresh();
        },
        
        bindEvents: function() {
            // Form submissions
            $(document).on('submit', '#contacts-form', this.handleBulkActions);
            $(document).on('submit', 'form[data-validate="true"]', this.validateForm);
            $(document).on('submit', 'form[data-ajax="true"]', this.handleAjaxForm);
            
            // Phone number formatting
            $(document).on('blur', 'input[type="tel"]', this.formatPhoneNumber);
            
            // Copy to clipboard
            $(document).on('click', '.ptp-copy-button', this.copyToClipboard);
            
            // Table row actions
            $(document).on('click', '.ptp-comms-table .row-action', this.handleRowAction);
            
            // Tooltips
            $(document).on('mouseenter', '[data-tooltip]', this.showTooltip);
            $(document).on('mouseleave', '[data-tooltip]', this.hideTooltip);
            
            // Tabs
            $(document).on('click', '.ptp-comms-tabs .nav-tab', this.handleTabClick);
            
            // Modal
            $(document).on('click', '[data-modal-trigger]', this.showModal);
            $(document).on('click', '.ptp-modal-close, .ptp-modal-overlay', this.hideModal);
            
            // Alert dismiss
            $(document).on('click', '.ptp-comms-alert-dismiss', this.dismissAlert);
            
            // Smooth scroll for anchors
            $(document).on('click', 'a[href^="#"]:not([href="#"])', this.smoothScroll);
            
            // Select all checkboxes
            $(document).on('change', '.select-all-checkbox', this.handleSelectAll);
        },
        
        initializeComponents: function() {
            // Auto-hide success messages
            $('.ptp-comms-alert.success').delay(this.config.notificationDuration).fadeOut();
            
            // Character counter for textareas
            this.initCharacterCounter();
            
            // Auto-scroll conversation thread
            this.scrollConversationThread();
            
            // Initialize datepickers if available
            this.initializeDatePickers();
            
            // Initialize select2 if available
            this.initializeSelect2();
        },
        
        setupInboxAutoRefresh: function() {
            if ($('body').hasClass('ptp-comms_page_ptp-comms-inbox')) {
                setInterval(() => {
                    this.checkForNewMessages();
                }, this.config.autoRefreshInterval);
            }
        },
        
        // Event Handlers
        handleBulkActions: function(e) {
            const action = $('select[name="bulk_action_type"]').val();
            const selected = $('.contact-checkbox:checked').length;
            
            if (action && selected > 0) {
                let message = 'Are you sure you want to ';
                
                switch(action) {
                    case 'delete':
                        message += `delete ${selected} contact(s)?`;
                        break;
                    case 'opt_in':
                        message += `mark ${selected} contact(s) as opted in?`;
                        break;
                    case 'opt_out':
                        message += `mark ${selected} contact(s) as opted out?`;
                        break;
                    default:
                        message += `perform this action on ${selected} contact(s)?`;
                }
                
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            }
        },
        
        formatPhoneNumber: function() {
            const $input = $(this);
            const phone = $input.val().replace(/\D/g, '');
            
            if (phone.length === 10) {
                $input.val(`(${phone.substr(0,3)}) ${phone.substr(3,3)}-${phone.substr(6,4)}`);
            } else if (phone.length === 11 && phone[0] === '1') {
                $input.val(`+1 (${phone.substr(1,3)}) ${phone.substr(4,3)}-${phone.substr(7,4)}`);
            }
        },
        
        validateForm: function(e) {
            const $form = $(this);
            let isValid = true;
            let errorMessage = '';
            
            // Clear previous errors
            $form.find('.ptp-comms-form-group').removeClass('error');
            
            // Check required fields
            $form.find('[required]').each(function() {
                const $field = $(this);
                const $group = $field.closest('.ptp-comms-form-group');
                
                if (!$field.val() || $field.val().trim() === '') {
                    isValid = false;
                    $group.addClass('error');
                    const fieldName = $field.attr('name') || $field.attr('id') || 'This field';
                    errorMessage += `• ${fieldName} is required\n`;
                }
            });
            
            // Email validation
            $form.find('input[type="email"]').each(function() {
                const $field = $(this);
                const $group = $field.closest('.ptp-comms-form-group');
                const email = $field.val();
                
                if (email && !PTPCommsAdmin.isValidEmail(email)) {
                    isValid = false;
                    $group.addClass('error');
                    errorMessage += `• Please enter a valid email address\n`;
                }
            });
            
            // Phone validation
            $form.find('input[type="tel"]').each(function() {
                const $field = $(this);
                const $group = $field.closest('.ptp-comms-form-group');
                const phone = $field.val().replace(/\D/g, '');
                
                if (phone && phone.length !== 10 && phone.length !== 11) {
                    isValid = false;
                    $group.addClass('error');
                    errorMessage += `• Please enter a valid phone number\n`;
                }
            });
            
            if (!isValid) {
                PTPCommsAdmin.showNotification('Please correct the following errors:\n\n' + errorMessage, 'error');
                e.preventDefault();
                
                // Scroll to first error
                const $firstError = $form.find('.ptp-comms-form-group.error').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 300);
                }
                
                return false;
            }
        },
        
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $button = $form.find('button[type="submit"]');
            const originalHtml = $button.html();
            
            // Disable button and show loading state
            $button.prop('disabled', true)
                   .html('<span class="ptp-comms-spinner small white"></span> Processing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        PTPCommsAdmin.showNotification(
                            response.data.message || 'Success!',
                            'success'
                        );
                        
                        if (response.data.redirect) {
                            setTimeout(() => {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else if (response.data.reload) {
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        PTPCommsAdmin.showNotification(
                            response.data.message || 'An error occurred',
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    PTPCommsAdmin.showNotification(
                        'Network error. Please try again.',
                        'error'
                    );
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },
        
        copyToClipboard: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const text = $button.data('copy') || $button.closest('[data-copy-target]').find($button.data('target')).text();
            
            if (!text) return;
            
            // Modern clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    PTPCommsAdmin.showCopySuccess($button);
                }).catch(err => {
                    PTPCommsAdmin.fallbackCopyToClipboard(text, $button);
                });
            } else {
                PTPCommsAdmin.fallbackCopyToClipboard(text, $button);
            }
        },
        
        fallbackCopyToClipboard: function(text, $button) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                PTPCommsAdmin.showCopySuccess($button);
            } catch (err) {
                PTPCommsAdmin.showNotification('Failed to copy', 'error');
            }
            
            $temp.remove();
        },
        
        showCopySuccess: function($button) {
            const originalText = $button.text();
            $button.text('Copied!').addClass('success');
            
            setTimeout(() => {
                $button.text(originalText).removeClass('success');
            }, 2000);
        },
        
        handleRowAction: function(e) {
            e.preventDefault();
            
            const $link = $(this);
            const action = $link.data('action');
            const id = $link.data('id');
            const confirmMessage = $link.data('confirm');
            
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }
            
            if (action === 'delete' && !confirmMessage && !confirm('Are you sure you want to delete this item?')) {
                return;
            }
            
            // Show loading state
            const $row = $link.closest('tr');
            $row.css('opacity', '0.5');
            
            $.post(ajaxurl, {
                action: 'ptp_comms_' + action,
                id: id,
                _wpnonce: ptpComms.nonce || ''
            }, function(response) {
                if (response.success) {
                    if (action === 'delete') {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        location.reload();
                    }
                    PTPCommsAdmin.showNotification(
                        response.data.message || 'Action completed successfully',
                        'success'
                    );
                } else {
                    $row.css('opacity', '1');
                    PTPCommsAdmin.showNotification(
                        response.data.message || 'Action failed',
                        'error'
                    );
                }
            }).fail(function() {
                $row.css('opacity', '1');
                PTPCommsAdmin.showNotification('Network error', 'error');
            });
        },
        
        showTooltip: function() {
            const $element = $(this);
            const text = $element.data('tooltip');
            
            if (!text) return;
            
            setTimeout(() => {
                if (!$element.is(':hover')) return;
                
                const $tooltip = $('<div class="ptp-tooltip">' + text + '</div>');
                $('body').append($tooltip);
                
                const pos = $element.offset();
                const elementWidth = $element.outerWidth();
                const tooltipWidth = $tooltip.outerWidth();
                
                $tooltip.css({
                    top: pos.top - $tooltip.outerHeight() - 10,
                    left: pos.left + (elementWidth / 2) - (tooltipWidth / 2)
                }).fadeIn(200);
            }, PTPCommsAdmin.config.tooltipDelay);
        },
        
        hideTooltip: function() {
            $('.ptp-tooltip').remove();
        },
        
        handleTabClick: function(e) {
            const $tab = $(this);
            const rawTarget = $tab.data('tab') || $tab.attr('href');

            // If target is a hash or a selector (starts with '#'), handle it in-page
            if (rawTarget && typeof rawTarget === 'string' && rawTarget.trim().startsWith('#')) {
                e.preventDefault();

                const target = rawTarget.trim();

                // Update active states
                $tab.closest('.ptp-comms-tabs').find('.nav-tab').removeClass('nav-tab-active');
                $tab.addClass('nav-tab-active');

                // Show/hide content
                $('.ptp-tab-content').removeClass('active').hide();
                $(target).addClass('active').fadeIn(300);

                // Update URL hash without scrolling
                history.replaceState(null, null, target);

                // If there's a hidden field to persist the active tab on form submit, update it
                try {
                    var tabName = target.replace('#', '');
                    var $hidden = $('#ptp_active_tab');
                    if ($hidden.length) {
                        $hidden.val(tabName);
                    }
                } catch (err) {
                    // ignore
                }
            } else {
                // For full URL / query links (e.g. ?page=...&tab=...), allow normal navigation
                // Do not preventDefault so the browser will load the correct page and server-side
                // rendered tab content will be shown (ensures admin CSS applies correctly).
            }
        },
        
        showModal: function(e) {
            e.preventDefault();
            
            const modalId = $(this).data('modal-trigger');
            const $modal = $(modalId);
            
            if ($modal.length) {
                const $overlay = $('<div class="ptp-modal-overlay"></div>');
                $overlay.append($modal.show());
                $('body').append($overlay);
                
                // Prevent body scroll
                $('body').css('overflow', 'hidden');
            }
        },
        
        hideModal: function(e) {
            if ($(e.target).hasClass('ptp-modal-overlay') || $(e.target).hasClass('ptp-modal-close')) {
                $('.ptp-modal-overlay').fadeOut(200, function() {
                    $(this).remove();
                    $('body').css('overflow', '');
                });
            }
        },
        
        dismissAlert: function() {
            $(this).closest('.ptp-comms-alert').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        smoothScroll: function(e) {
            const $target = $($(this).attr('href'));
            
            if ($target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: $target.offset().top - 100
                }, 500);
            }
        },
        
        handleSelectAll: function() {
            const $checkbox = $(this);
            const $table = $checkbox.closest('table');
            const checked = $checkbox.prop('checked');
            
            $table.find('tbody input[type="checkbox"]').prop('checked', checked);
        },
        
        // Component Initializers
        initCharacterCounter: function() {
            $('textarea[data-counter="true"]').each(function() {
                const $textarea = $(this);
                const maxLength = $textarea.attr('maxlength');
                
                const $counter = $('<div class="char-counter" style="margin-top: 5px; font-size: 12px; color: var(--ptp-gray-600);"></div>');
                $textarea.after($counter);
                
                const updateCounter = function() {
                    const length = $textarea.val().length;
                    let text = `${length} characters`;
                    
                    if (maxLength) {
                        text += ` / ${maxLength}`;
                        if (length > maxLength * 0.9) {
                            $counter.css('color', 'var(--ptp-danger)');
                        } else {
                            $counter.css('color', 'var(--ptp-gray-600)');
                        }
                    }
                    
                    $counter.text(text);
                };
                
                $textarea.on('input', updateCounter);
                updateCounter();
            });
        },
        
        scrollConversationThread: function() {
            const $thread = $('.ptp-conversation-thread');
            if ($thread.length) {
                $thread.scrollTop($thread[0].scrollHeight);
            }
        },
        
        initializeDatePickers: function() {
            if ($.fn.datepicker) {
                $('input[type="date"].use-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },
        
        initializeSelect2: function() {
            if ($.fn.select2) {
                $('.ptp-select2').select2({
                    width: '100%',
                    theme: 'default'
                });
            }
        },
        
        // Utility Functions
        checkForNewMessages: function() {
            const $unreadBadge = $('.ptp-unread-badge');
            if (!$unreadBadge.length) return;
            
            $.get(ajaxurl, {
                action: 'ptp_comms_check_unread'
            }, function(response) {
                if (response.success && response.data.count > 0) {
                    $unreadBadge.text(response.data.count).show();
                    
                    // Optional: play notification sound
                    if (response.data.newMessages) {
                        PTPCommsAdmin.playNotificationSound();
                    }
                } else {
                    $unreadBadge.hide();
                }
            });
        },
        
        showNotification: function(message, type) {
            type = type || 'info';
            
            const alertClass = 'ptp-comms-alert ' + (type === 'error' ? 'error' : type);
            const icon = PTPCommsAdmin.getNotificationIcon(type);
            
            const $alert = $(`
                <div class="${alertClass}">
                    <span class="dashicons ${icon}"></span>
                    <div class="ptp-comms-alert-content">
                        <p>${message}</p>
                    </div>
                    <button class="ptp-comms-alert-dismiss dashicons dashicons-no-alt"></button>
                </div>
            `);
            
            $('.wrap.ptp-comms-wrap').prepend($alert);
            
            setTimeout(function() {
                $alert.fadeOut(300, function() {
                    $(this).remove();
                });
            }, PTPCommsAdmin.config.notificationDuration);
        },
        
        getNotificationIcon: function(type) {
            const icons = {
                success: 'dashicons-yes-alt',
                error: 'dashicons-warning',
                warning: 'dashicons-warning',
                info: 'dashicons-info'
            };
            
            return icons[type] || icons.info;
        },
        
        playNotificationSound: function() {
            // Optional: implement notification sound
            // const audio = new Audio('/path/to/notification.mp3');
            // audio.play().catch(e => console.log('Could not play notification sound'));
        },
        
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        PTPCommsAdmin.init();
    });
    
    // Export for global use
    window.ptpCommsAdmin = PTPCommsAdmin;
    
})(jQuery);
