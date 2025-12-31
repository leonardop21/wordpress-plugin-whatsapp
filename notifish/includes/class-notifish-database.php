<?php
/**
 * Database operations class for Notifish plugin
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Notifish_Database {
    private $table_name;
    private $logger;

    public function __construct($logger) {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'notifish_requests';
        $this->logger = $logger;
    }

    /**
     * Create database table
     *
     * @return void
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_name varchar(255) NOT NULL,
            post_id bigint(20) NOT NULL,
            post_title text NOT NULL,
            phone_number varchar(255) NOT NULL,
            status_code int(3) NOT NULL,
            response text NOT NULL,
            sent_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            INDEX idx_post_id (post_id),
            INDEX idx_phone_number (phone_number),
            INDEX idx_status_code (status_code),
            INDEX idx_user_id (user_id),
            INDEX idx_sent_at (sent_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Save request to database
     *
     * @param array $data Request data
     * @return int|false Insert ID on success, false on failure
     */
    public function save_request($data) {
        global $wpdb;

        // Sanitize all input data to prevent SQL injection and XSS
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'post_id' => absint($data['post_id']),
                'post_title' => sanitize_text_field($data['post_title']),
                'phone_number' => sanitize_text_field($data['phone_number']),
                'status_code' => absint($data['status_code']),
                'user_id' => absint($data['user_id']),
                'user_name' => sanitize_text_field($data['user_name']),
                'response' => sanitize_textarea_field($data['response']),
                'sent_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
        );

        if ($result) {
            $this->logger->write("Request salvo no banco de dados", [
                'insert_id' => $wpdb->insert_id,
                'status_code' => $data['status_code']
            ]);
            return $wpdb->insert_id;
        } else {
            $this->logger->write("Erro ao salvar request no banco", [
                'error' => $wpdb->last_error
            ]);
            return false;
        }
    }

    /**
     * Get request by ID
     *
     * @param int $id Request ID
     * @return object|null
     */
    public function get_request($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Get recent requests
     *
     * @param int $limit Number of requests to retrieve
     * @return array
     */
    public function get_recent_requests($limit = 20) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Check if post was already sent
     *
     * @param int $post_id Post ID
     * @return bool
     */
    public function post_was_sent($post_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d",
            $post_id
        ));
        return $count > 0;
    }

    /**
     * Get table name
     *
     * @return string
     */
    public function get_table_name() {
        return $this->table_name;
    }
}

