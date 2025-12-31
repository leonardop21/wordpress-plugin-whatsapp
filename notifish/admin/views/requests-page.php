<?php
/**
 * Requests/Logs page view
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct access
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $wpdb;
$table_name = $wpdb->prefix . 'notifish_requests';

if (isset($_POST['resend']) && isset($_POST['_wpnonce'])) {
    $id = intval($_POST['resend']);
    $nonce = sanitize_text_field($_POST['_wpnonce']);
    
    if (wp_verify_nonce($nonce, 'notifish_resend_' . $id)) {
        $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if ($request) {
            do_action('notifish_resend_message', $request);
            echo '<div class="notice notice-success is-dismissible"><p>Mensagem reenviada com sucesso!</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Erro de segurança. Tente novamente.</p></div>';
    }
}

$requests = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 20");
?>
<div class="wrap">
    <h1>Notifish Logs</h1>
    <h4>Listando às últimas 20</h4>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Post ID</th>
                <th>Título</th>
                <th>Telefone</th>
                <th>Status</th>
                <th>Resposta</th>
                <th>Data</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $request) : ?>
            <tr>
                <td><?php echo esc_html($request->id); ?></td>
                <td><?php echo esc_html($request->post_id); ?></td>
                <td><?php echo esc_html($request->post_title); ?></td>
                <td><?php echo esc_html($request->phone_number); ?></td>
                <td><?php echo esc_html($request->status_code); ?></td>
                <td><?php echo esc_html($request->response); ?></td>
                <td><?php echo esc_html($request->sent_at); ?></td>
                <td>
                    <form method="post">
                        <?php wp_nonce_field('notifish_resend_' . $request->id); ?>
                        <button type="submit" name="resend" value="<?php echo esc_attr($request->id); ?>" class="button button-small">
                            Reenviar
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

