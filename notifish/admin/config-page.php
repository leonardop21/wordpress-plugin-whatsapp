<?php
/**
 * Config page view
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Configurações do Notifish</h1>
    
    <?php
    // Verifica se as configurações foram salvas
    if (isset($_GET['settings-updated']) && sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Configurações salvas com sucesso!</strong></p></div>';
    }
    
    // Notice para obter credenciais - dismissível
    $notice_dismissed = get_option('notifish_credentials_notice_dismissed', false);
    if (!$notice_dismissed) {
        // Monta a URL com UTM parameters
        $site_url = urlencode(get_site_url());
        $notifish_url = 'https://notifish.com/?utm_source=wordpress_plugin&utm_medium=admin_notice&utm_campaign=get_credentials&utm_content=' . $site_url;
        ?>
        <div class="notice notice-info is-dismissible notifish-credentials-notice">
            <p>
                <strong>🔑 Não tem as credenciais?</strong> 
                Obtenha sua API Key e UUID da instância em 
                <a href="<?php echo esc_url($notifish_url); ?>" target="_blank" rel="noopener noreferrer">
                    <strong>notifish.com</strong>
                </a>
            </p>
        </div>
        <?php
    }
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('notifish_group');
        do_settings_sections('notifish_group');
        $options = get_option('notifish_options');
        ?>
        <table class="form-table">
            <tr valign="top">
            <div style="position: relative; display: inline-block; width: 100%;">
                <th scope="row">URL da API</th>
                <td>
                    <input type="text" id="api_url" name="notifish_options[api_url]" value="<?php echo isset($options['api_url']) ? esc_attr($options['api_url']) : ''; ?>" style="width: 100%; padding-right: 40px;" />
                    <p class="description"><strong>Importante:</strong> A URL da API deve incluir a versão (ex: https://meu-dominio.notifish.com/api/v1/ ou https://meu-dominio.notifish.com/api/v2/).</p>
                </td>
            </div>
            </tr>
            <tr valign="top">
                <th scope="row">Uuid da instância</th>
                <td>
                    <div style="position: relative; display: inline-block; width: 100%;">
                        <input type="password" id="instance_uuid" name="notifish_options[instance_uuid]" value="<?php echo isset($options['instance_uuid']) ? esc_attr($options['instance_uuid']) : ''; ?>" style="width: 100%; padding-right: 40px;" />
                        <button type="button" onclick="togglePassword('instance_uuid')" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px;">👁️</button>
                    </div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">API Key</th>
                <td>
                    <div style="position: relative; display: inline-block; width: 100%;">
                        <?php 
                        // Se já existe uma API Key salva, mostra asteriscos, senão campo vazio
                        $api_key_display = (isset($options['api_key']) && !empty($options['api_key'])) ? '***************************' : '';
                        ?>
                        <input type="password" id="api_key" name="notifish_options[api_key]" value="<?php echo esc_attr($api_key_display); ?>" placeholder="Digite uma nova API Key para alterar" style="width: 100%; padding-right: 40px;" />
                        <button type="button" onclick="togglePassword('api_key')" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px;">👁️</button>
                    </div>
                    <p class="description">Deixe em branco ou com asteriscos para manter a chave atual. Digite uma nova chave apenas se desejar alterá-la.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Habilitar WhatsApp por padrão</th>
                <td>
                    <select name="notifish_options[default_whatsapp_enabled]">
                        <option value="0" <?php echo (isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '0') ? 'selected' : ''; ?>>Não</option>
                        <option value="1" <?php echo (isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '1') ? 'selected' : ''; ?>>Sim</option>
                    </select>
                    <p class="description">Se marcado como "Sim", o checkbox de compartilhar no WhatsApp virá marcado por padrão ao criar novos posts.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Habilitar Logs</th>
                <td>
                    <select name="notifish_options[enable_logging]">
                        <option value="0" <?php echo (!isset($options['enable_logging']) || $options['enable_logging'] == '0') ? 'selected' : ''; ?>>Não</option>
                        <option value="1" <?php echo (isset($options['enable_logging']) && $options['enable_logging'] == '1') ? 'selected' : ''; ?>>Sim</option>
                    </select>
                    <p class="description">Se desabilitado, nenhum log será gravado em <code>wp-content/logs-notifish/</code>. Os logs ajudam a diagnosticar problemas.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Remover dados ao desinstalar</th>
                <td>
                    <select name="notifish_options[remove_data_on_uninstall]">
                        <option value="0" <?php echo (!isset($options['remove_data_on_uninstall']) || $options['remove_data_on_uninstall'] == '0') ? 'selected' : ''; ?>>Não</option>
                        <option value="1" <?php echo (isset($options['remove_data_on_uninstall']) && $options['remove_data_on_uninstall'] == '1') ? 'selected' : ''; ?>>Sim</option>
                    </select>
                    <p class="description"><strong>Atenção:</strong> Se marcado como "Sim", ao desinstalar o plugin, a tabela de requests e os arquivos de log serão removidos permanentemente.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Idioma do plugin</th>
                <td>
                    <?php
                    $current_language = isset($options['language']) ? $options['language'] : '';
                    ?>
                    <select name="notifish_options[language]">
                        <option value="" <?php selected($current_language, ''); ?>>Usar idioma do site (recomendado)</option>
                        <option value="en_US" <?php selected($current_language, 'en_US'); ?>>English (US)</option>
                        <option value="pt_BR" <?php selected($current_language, 'pt_BR'); ?>>Português (Brasil)</option>
                    </select>
                    <p class="description">Esta opção afeta apenas os textos do plugin Notifish. Por padrão, ele segue o idioma configurado no WordPress.</p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
