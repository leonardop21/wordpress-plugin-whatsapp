<?php
/**
 * Logger class for Notifish plugin
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Notifish_Logger {
    private $log_file;
    private $log_dir;

    public function __construct() {
        // Define log directory inside uploads/notifish/logs to comply with WP.org guidelines
        $upload_dir      = wp_upload_dir();
        $base_upload_dir = isset($upload_dir['basedir']) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';

        $this->log_dir = trailingslashit($base_upload_dir) . 'notifish/logs';

        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        $this->log_file = trailingslashit($this->log_dir) . 'notifish-' . date('Y-m-d') . '.log';
        // log_file mantido para compatibilidade; o path real é calculado em get_log_file_path() a cada escrita
    }

    /**
     * Verifica se o logging está habilitado (lê a opção a cada verificação para refletir alterações).
     *
     * @return bool
     */
    private function is_logging_enabled() {
        $options = get_option('notifish_options', array());
        $val = isset($options['enable_logging']) ? $options['enable_logging'] : '';
        return $val === '1' || $val === 1;
    }

    /**
     * Retorna o caminho do arquivo de log para a data atual (usado em cada escrita).
     *
     * @return string
     */
    private function get_log_file_path() {
        return trailingslashit($this->log_dir) . 'notifish-' . date('Y-m-d') . '.log';
    }

    /**
     * Write log message
     * Verifica automaticamente se logging está habilitado antes de gravar
     *
     * @param string $message Log message
     * @param mixed $data Additional data to log
     * @return void
     */
    public function write($message, $data = null) {
        if (!$this->is_logging_enabled()) {
            return;
        }

        if (!is_dir($this->log_dir) && !wp_mkdir_p($this->log_dir)) {
            error_log('Notifish: não foi possível criar o diretório de logs: ' . $this->log_dir);
            return;
        }

        $log_file = $this->get_log_file_path();
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message";
        
        if ($data !== null) {
            $log_message .= "\n" . print_r($data, true);
        }
        
        $log_message .= "\n" . str_repeat('-', 80) . "\n";

        $written = @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            $err = error_get_last();
            error_log('Notifish: não foi possível gravar no arquivo de log: ' . $log_file . (is_array($err) ? ' | PHP: ' . $err['message'] : ''));
        }
    }

    /**
     * Get log directory path
     *
     * @return string
     */
    public function get_log_dir() {
        return $this->log_dir;
    }

    /**
     * Get current log file path (para a data de hoje)
     *
     * @return string
     */
    public function get_log_file() {
        return $this->get_log_file_path();
    }
}

