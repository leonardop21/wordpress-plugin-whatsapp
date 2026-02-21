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
$options = get_option('notifish_options');
?>
<div class="wrap">
    <h1>Configurações do Notifish</h1>

    <?php
    $settings_errors = get_settings_errors('notifish_group');
    $has_settings_errors = !empty(array_filter($settings_errors, function ($e) { return isset($e['type']) && $e['type'] === 'error'; }));
    if (isset($_GET['settings-updated']) && sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true' && !$has_settings_errors) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Configurações salvas com sucesso!</strong></p></div>';
    }
    settings_errors('notifish_group');

    $notice_dismissed = get_option('notifish_credentials_notice_dismissed', false);
    if (!$notice_dismissed) {
        $site_url = urlencode(get_site_url());
        $notifish_url = 'https://notifish.com/?utm_source=wordpress_plugin&utm_medium=admin_notice&utm_campaign=get_credentials&utm_content=' . $site_url;
        ?>
        <div class="notice notice-info is-dismissible notifish-credentials-notice">
            <p>
                <strong>🔑 Não tem as credenciais?</strong>
                Obtenha sua API Key e UUID da instância em
                <a href="<?php echo esc_url($notifish_url); ?>" target="_blank" rel="noopener noreferrer"><strong>notifish.com</strong></a>
            </p>
        </div>
        <?php
    }
    ?>

    <nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 0;">
        <a href="#tab-whatsapp" class="nav-tab nav-tab-active" data-tab="whatsapp">WhatsApp</a>
        <a href="#tab-midia_social" class="nav-tab" data-tab="midia_social">Mídias sociais</a>
    </nav>

    <form method="post" action="options.php">
        <?php
        settings_fields('notifish_group');
        do_settings_sections('notifish_group');
        ?>
        <div id="tab-whatsapp" class="notifish-tab-content">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">URL da API</th>
                    <td>
                        <input type="text" id="api_url" name="notifish_options[api_url]" value="<?php echo isset($options['api_url']) ? esc_attr($options['api_url']) : ''; ?>" class="large-text" />
                        <p class="description"><strong>Importante:</strong> A URL da API deve incluir a versão (ex: https://meu-dominio.notifish.com/api/v2/).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Uuid da instância</th>
                    <td>
                        <div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">
                            <input type="password" id="instance_uuid" name="notifish_options[instance_uuid]" value="<?php echo isset($options['instance_uuid']) ? esc_attr($options['instance_uuid']) : ''; ?>" class="large-text" style="padding-right: 40px;" />
                            <button type="button" onclick="togglePassword('instance_uuid')" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px;">👁️</button>
                        </div>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">API Key</th>
                    <td>
                        <div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">
                            <?php $api_key_display = (isset($options['api_key']) && !empty($options['api_key'])) ? '***************************' : ''; ?>
                            <input type="password" id="api_key" name="notifish_options[api_key]" value="<?php echo esc_attr($api_key_display); ?>" placeholder="Digite uma nova API Key para alterar" class="large-text" style="padding-right: 40px;" />
                            <button type="button" onclick="togglePassword('api_key')" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 16px;">👁️</button>
                        </div>
                        <p class="description">Deixe em branco ou com asteriscos para manter a chave atual.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Habilitar WhatsApp por padrão</th>
                    <td>
                        <select name="notifish_options[default_whatsapp_enabled]">
                            <option value="0" <?php selected(isset($options['default_whatsapp_enabled']) ? $options['default_whatsapp_enabled'] : '', '0'); ?>>Não</option>
                            <option value="1" <?php selected(isset($options['default_whatsapp_enabled']) ? $options['default_whatsapp_enabled'] : '', '1'); ?>>Sim</option>
                        </select>
                        <p class="description">Se "Sim", o checkbox de compartilhar no WhatsApp virá marcado por padrão ao criar novos posts.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Habilitar Logs</th>
                    <td>
                        <select name="notifish_options[enable_logging]">
                            <option value="0" <?php selected(!isset($options['enable_logging']) || $options['enable_logging'] == '0', true); ?>>Não</option>
                            <option value="1" <?php selected(isset($options['enable_logging']) && $options['enable_logging'] == '1', true); ?>>Sim</option>
                        </select>
                        <p class="description">Logs em <code>wp-content/logs-notifish/</code>.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Remover dados ao desinstalar</th>
                    <td>
                        <select name="notifish_options[remove_data_on_uninstall]">
                            <option value="0" <?php selected(!isset($options['remove_data_on_uninstall']) || $options['remove_data_on_uninstall'] == '0', true); ?>>Não</option>
                            <option value="1" <?php selected(isset($options['remove_data_on_uninstall']) && $options['remove_data_on_uninstall'] == '1', true); ?>>Sim</option>
                        </select>
                        <p class="description">Se "Sim", ao desinstalar o plugin os dados e logs serão removidos.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Idioma do plugin</th>
                    <td>
                        <select name="notifish_options[language]">
                            <option value="" <?php selected(isset($options['language']) ? $options['language'] : '', ''); ?>>Usar idioma do site</option>
                            <option value="en_US" <?php selected(isset($options['language']) ? $options['language'] : '', 'en_US'); ?>>English (US)</option>
                            <option value="pt_BR" <?php selected(isset($options['language']) ? $options['language'] : '', 'pt_BR'); ?>>Português (Brasil)</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <div id="tab-midia_social" class="notifish-tab-content" style="display: none;">
        <h4 class="description" style="margin-bottom: 15px; margin-top:20px;">
            Atualmente, o sistema gera <strong>Stories e Reels para o Instagram</strong> e realiza a publicação automaticamente (quando configurado no Notifish). Em breve: TikTok e Facebook. Caso o post não possua imagem de destaque, não será gerado story/reels.
        </h4>            
        <table class="form-table">
                <tr valign="top">
                    <th scope="row">Habilitar Mídias sociais</th>
                    <td>
                        <?php $midia_enabled = isset($options['midia_social_enabled']) ? $options['midia_social_enabled'] : '0'; ?>
                        <select name="notifish_options[midia_social_enabled]" id="midia_social_enabled">
                            <option value="0" <?php selected($midia_enabled, '0'); ?>>Não</option>
                            <option value="1" <?php selected($midia_enabled, '1'); ?>>Sim</option>
                        </select>
                        <p class="description">Se "Sim", ao enviar notícias com imagem de destaque o plugin envia o array <code>social_media</code> para geração de story/reels. Por padrão vem desabilitado.</p>
                    </td>
                </tr>
            </table>
            <div id="notifish-midia-social-campos" style="<?php echo $midia_enabled !== '1' ? 'display:none;' : ''; ?>">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Logo</th>
                    <td>
                        <input type="url" id="midia_social_logo_url" name="notifish_options[midia_social_logo_url]" value="<?php echo isset($options['midia_social_logo_url']) ? esc_url($options['midia_social_logo_url']) : ''; ?>" class="large-text" placeholder="https://exemplo.com/logo.png" style="max-width: 100%; margin-bottom: 8px;" />
                        <p class="description" style="margin-bottom: 8px;">Informe a URL da logo ou use o botão abaixo para escolher na biblioteca de mídia.</p>
                        <button type="button" id="notifish-select-logo" class="button">Procurar logo na biblioteca</button>
                        <button type="button" id="notifish-remove-logo" class="button" style="<?php echo empty($options['midia_social_logo_url']) ? 'display:none;' : ''; ?>">Remover logo</button>
                        <div id="notifish-logo-preview" style="margin-top: 10px;">
                            <?php if (!empty($options['midia_social_logo_url'])) : ?>
                                <img src="<?php echo esc_url($options['midia_social_logo_url']); ?>" alt="Logo" style="max-width: 120px; max-height: 60px; border: 1px solid #ccc;" />
                            <?php endif; ?>
                        </div>
                        <p class="description">Recomendado: máx. 200 KB, PNG ou WebP.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Cor de fundo padrão</th>
                    <td>
                        <?php
                        $bg_color = isset($options['midia_social_bg_color']) ? $options['midia_social_bg_color'] : '#333333';
                        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $bg_color) !== 1) {
                            $bg_color = '#333333';
                        }
                        ?>
                        <input type="color" name="notifish_options[midia_social_bg_color]" value="<?php echo esc_attr($bg_color); ?>" style="width: 60px; height: 36px; padding: 2px; cursor: pointer;" />
                        <p class="description">Cor de fundo do vídeo.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Inserir som no vídeo</th>
                    <td>
                        <select name="notifish_options[midia_social_music]">
                            <option value="0" <?php selected(!isset($options['midia_social_music']) || $options['midia_social_music'] == '0', true); ?>>Não</option>
                            <option value="1" <?php selected(isset($options['midia_social_music']) && $options['midia_social_music'] == '1', true); ?>>Sim</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">ID da música</th>
                    <td>
                        <input type="number" name="notifish_options[midia_social_music_id]" value="<?php echo isset($options['midia_social_music_id']) ? absint($options['midia_social_music_id']) : '0'; ?>" min="0" step="1" class="small-text" />
                        <p class="description">Identificador da música (número). Use 0 se não aplicar.</p>
                        <p class="description">Acesse <a href="https://notifish.com/sons-midias-sociais" target="_blank" rel="noopener noreferrer">notifish.com/sons-midias-socias</a> para ver os sons disponíveis. Deixe em branco para um som padrão.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Publicar nas mídias sociais</th>
                    <td>
                        <select name="notifish_options[midia_social_publish]">
                            <option value="0" <?php selected(!isset($options['midia_social_publish']) || $options['midia_social_publish'] == '0', true); ?>>Não</option>
                            <option value="1" <?php selected(isset($options['midia_social_publish']) && $options['midia_social_publish'] == '1', true); ?>>Sim</option>
                        </select>
                        <p class="description">Se "Sim", publica no Instagram.</p>
                    </td>
                </tr>
            </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(function($) {
    var tab = localStorage.getItem('notifish_config_tab') || 'whatsapp';
    $('.nav-tab').removeClass('nav-tab-active').filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');
    $('.notifish-tab-content').hide().filter('#tab-' + tab).show();

    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var t = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.notifish-tab-content').hide();
        $('#tab-' + t).show();
        localStorage.setItem('notifish_config_tab', t);
    });

    $('#midia_social_enabled').on('change', function() {
        var val = $(this).val();
        $('#notifish-midia-social-campos').toggle(val === '1');
    });
});
</script>

