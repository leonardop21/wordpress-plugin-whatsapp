<?php
/**
 * Plugin Name: Notifish
 * Plugin URI: https://notifish.com/lp/plugin-whatsapp-wordpress
 * Description: Plugin para gerenciar notificações via API do Notifish.
 * Version: 2.0.0
 * Author: Notifish
 * Author URI: https://notifish.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: notifish
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NOTIFISH_VERSION', '2.0.0');
define('NOTIFISH_PLUGIN_FILE', __FILE__);
define('NOTIFISH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOTIFISH_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load main plugin class
require_once NOTIFISH_PLUGIN_DIR . 'includes/class-notifish.php';

// Initialize plugin
function notifish_init() {
    return new Notifish();
}

// Start the plugin
notifish_init();
