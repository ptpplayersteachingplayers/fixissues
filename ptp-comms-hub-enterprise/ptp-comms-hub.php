<?php
/**
 * Plugin Name: PTP Comms Hub Enterprise
 * Plugin URI: https://ptpsoccercamps.com
 * Description: Enterprise communication platform with SMS, Voice, Slack, HubSpot integration, and intelligent automations for PTP Soccer Camps
 * Version: 1.0.0
 * Author: PTP Soccer Camps
 * Author URI: https://ptpsoccercamps.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ptp-comms-hub
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('PTP_COMMS_HUB_VERSION', '1.0.0');

// Plugin paths
define('PTP_COMMS_HUB_PATH', plugin_dir_path(__FILE__));
define('PTP_COMMS_HUB_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_ptp_comms_hub() {
    require_once PTP_COMMS_HUB_PATH . 'includes/class-activator.php';
    PTP_Comms_Hub_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_ptp_comms_hub() {
    require_once PTP_COMMS_HUB_PATH . 'includes/class-deactivator.php';
    PTP_Comms_Hub_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_ptp_comms_hub');
register_deactivation_hook(__FILE__, 'deactivate_ptp_comms_hub');

/**
 * The core plugin class
 */
require PTP_COMMS_HUB_PATH . 'includes/class-loader.php';

/**
 * Begins execution of the plugin.
 */
function run_ptp_comms_hub() {
    $plugin = new PTP_Comms_Hub_Loader();
    $plugin->run();
}
run_ptp_comms_hub();
