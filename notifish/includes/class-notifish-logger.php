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
    private $logging_enabled;

    public function __construct() {
        $this->log_dir = WP_CONTENT_DIR . '/logs-notifish';
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
        $this->log_file = $this->log_dir . '/notifish-' . date('Y-m-d') . '.log';
        
        // Verifica se logging está habilitado (padrão: desabilitado)
        $options = get_option('notifish_options', array());
        $this->logging_enabled = isset($options['enable_logging']) ? ($options['enable_logging'] == '1') : false;
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
        // Verifica se logging está habilitado (verificação inteligente - sem if em cada chamada)
        if (!$this->logging_enabled) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message";
        
        if ($data !== null) {
            $log_message .= "\n" . print_r($data, true);
        }
        
        $log_message .= "\n" . str_repeat('-', 80) . "\n";
        
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
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
     * Get current log file path
     *
     * @return string
     */
    public function get_log_file() {
        return $this->log_file;
    }
}

