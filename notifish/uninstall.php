<?php
/**
 * Uninstall script for Notifish plugin
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Obtém as opções antes de deletá-las
$options = get_option('notifish_options', array());
$remove_data = isset($options['remove_data_on_uninstall']) && $options['remove_data_on_uninstall'] == '1';

// Sempre remove as opções
delete_option('notifish_options');
delete_option('notifish_credentials_notice_dismissed');

// Remove dados apenas se o usuário configurou para remover
if ($remove_data) {
    // Drop database table
    global $wpdb;
    $table_name = $wpdb->prefix . 'notifish_requests';
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    
    // Delete log directory inside uploads/notifish/logs
    $upload_dir      = wp_upload_dir();
    $base_upload_dir = isset($upload_dir['basedir']) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';
    $log_dir         = trailingslashit($base_upload_dir) . 'notifish/logs';

    if (file_exists($log_dir) && is_dir($log_dir)) {
        $log_files = glob($log_dir . '/*.log');
        if ($log_files) {
            array_map('unlink', $log_files);
        }
        @rmdir($log_dir);
    }
}

