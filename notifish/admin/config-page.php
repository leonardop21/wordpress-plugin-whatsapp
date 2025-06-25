<div class="wrap">
    <h1>Configurações do Notifish</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('notifish_group');
        do_settings_sections('notifish_group');
        $options = get_option('notifish_options');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">URL da API</th>
                <td><input type="text" name="notifish_options[api_url]" value="<?php echo isset($options['api_url']) ? esc_attr($options['api_url']) : ''; ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">API Key</th>
                <td><input type="password" name="notifish_options[api_key]" value="<?php echo isset($options['api_key']) && !empty($options['api_key']) ? '***************************' : ''; ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Uuid da instância</th>
                <td><input type="password" name="notifish_options[instance_uuid]" value="<?php echo isset($options['instance_uuid']) ? esc_attr($options['instance_uuid']) : ''; ?>" /></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
