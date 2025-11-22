<?php
/**
 * Fired during plugin deactivation
 */
class PTP_Comms_Hub_Deactivator {
    
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('ptp_comms_process_automations');
        wp_clear_scheduled_hook('ptp_comms_sync_hubspot');
    }
}
