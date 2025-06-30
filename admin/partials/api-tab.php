<?php
/**
 * Vista de la pestaña de configuración de API
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/admin/partials/settings
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener instancia de autenticación
$auth = new WZI_Zoho_Auth();
$connected_services = $auth->get_connected_services();
$api_settings = get_option('wzi_api_settings', array());

// Manejar autorización
if (isset($_GET['action']) && $_GET['action'] === 'authorize') {
    $services = isset($_POST['services']) ? $_POST['services'] : array('crm');
    $auth_url = $auth->get_authorization_url($services);
    
    if ($auth_url) {
        wp_redirect($auth_url);
        exit;
    }
}

// Manejar revocación
if (isset($_GET['action']) && $_GET['action'] === 'revoke' && isset($_GET['service'])) {
    check_admin_referer('wzi_revoke_' . $_GET['service']);
    $auth->revoke_tokens($_GET['service']);
    wp_redirect(admin_url('admin.php?page=wzi-settings&tab=api&revoked=1'));
    exit;
}
?>

<div class="wzi-api-settings">
    <?php if (isset($_GET['auth']) && $_GET['auth'] === 'success'): ?>
        <div class="notice notice-success">
            <p><?php _e('Autorización completada exitosamente.', 'woocommerce-zoho-integration'); ?></p>
        </div>
    <?php elseif (isset($_GET['auth']) && $_GET['auth'] === 'failed'): ?>
        <div class="notice notice-error">
            <p><?php _e('Error durante la autorización. Por favor, intenta nuevamente.', 'woocommerce-zoho-integration'); ?></p>
        </div>
    <?php elseif (isset($_GET['revoked'])): ?>
        <div class="notice notice-info">
            <p><?php _e('Tokens revocados exitosamente.', 'woocommerce-zoho-integration'); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php _e('Configuración de API de Zoho', 'woocommerce-zoho-integration'); ?></h2>
    
    <!-- Estado de Conexión -->
    <div class="wzi-connection-status">
        <h3><?php _e('Estado de Conexión', 'woocommerce-zoho-integration'); ?></h3>
        
        <?php if (!empty($connected_services)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Servicio', 'woocommerce-zoho-integration'); ?></th>
                        <th><?php _e('Estado', 'woocommerce-zoho-integration'); ?></th>
                        <th><?php _e('Expiración', 'woocommerce-zoho-integration'); ?></th>
                        <th><?php _e('Acciones', 'woocommerce-zoho-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connected_services as $service): 
                        $token_info = $auth->get_token_info($service);
                    ?>
                        <tr>
                            <td><strong><?php echo ucfirst($service); ?></strong></td>
                            <td>
                                <span class="connection-status connected">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Conectado', 'woocommerce-zoho-integration'); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                if ($token_info && $token_info->expires_at) {
                                    $expires_in = human_time_diff(current_time('timestamp'), strtotime($token_info->expires_at));
                                    printf(__('Expira en %s', 'woocommerce-zoho-integration'), $expires_in);
                                }
                                ?>
                            </td>
                            <td>
                                <button type="button" class="button wzi-test-connection" data-service="<?php echo esc_attr($service); ?>">
                                    <?php _e('Probar Conexión', 'woocommerce-zoho-integration'); ?>
                                </button>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wzi-settings&tab=api&action=revoke&service=' . $service), 'wzi_revoke_' . $service); ?>" 
                                   class="button button-link-delete"
                                   onclick="return confirm('<?php esc_attr_e('¿Estás seguro de que deseas revocar esta conexión?', 'woocommerce-zoho-integration'); ?>');">
                                    <?php _e('Revocar', 'woocommerce-zoho-integration'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="connection-status disconnected">
                <span class="dashicons dashicons-warning"></span>
                <?php _e('No hay servicios conectados', 'woocommerce-zoho-integration'); ?>
            </p>
        <?php endif; ?>
    </div>
    
    <!-- Formulario de Configuración -->
    <form method="post" action="options.php">
        <?php
        settings_fields('wzi_api_settings_group');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wzi_api_settings[client_id]">
                        <?php _e('Client ID', 'woocommerce-zoho-integration'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="wzi_api_settings[client_id]" 
                           name="wzi_api_settings[client_id]" 
                           value="<?php echo esc_attr($api_settings['client_id'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Ingrese el Client ID de su aplicación Zoho.', 'woocommerce-zoho-integration'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wzi_api_settings[client_secret]">
                        <?php _e('Client Secret', 'woocommerce-zoho-integration'); ?>
                    </label>
                </th>
                <td>
                    <input type="password" 
                           id="wzi_api_settings[client_secret]" 
                           name="wzi_api_settings[client_secret]" 
                           value="<?php echo esc_attr($api_settings['client_secret'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Ingrese el Client Secret de su aplicación Zoho.', 'woocommerce-zoho-integration'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wzi_api_settings[redirect_uri]">
                        <?php _e('Redirect URI', 'woocommerce-zoho-integration'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="wzi_api_settings[redirect_uri]" 
                           name="wzi_api_settings[redirect_uri]" 
                           value="<?php echo esc_attr($api_settings['redirect_uri'] ?? admin_url('admin.php?page=wzi-settings&tab=api&action=callback')); ?>" 
                           class="large-text" 
                           readonly />
                    <p class="description">
                        <?php _e('Copie esta URL y péguela en la configuración de Redirect URI de su aplicación Zoho.', 'woocommerce-zoho-integration'); ?>
                        <button type="button" class="button button-small wzi-copy-url" data-target="wzi_api_settings[redirect_uri]">
                            <?php _e('Copiar', 'woocommerce-zoho-integration'); ?>
                        </button>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="wzi_api_settings[data_center]">
                        <?php _e('Centro de Datos', 'woocommerce-zoho-integration'); ?>
                    </label>
                </th>
                <td>
                    <select id="wzi_api_settings[data_center]" 
                            name="wzi_api_settings[data_center]">
                        <option value="com" <?php selected($api_settings['data_center'] ?? 'com', 'com'); ?>>
                            .com (Estados Unidos)
                        </option>
                        <option value="eu" <?php selected($api_settings['data_center'] ?? '', 'eu'); ?>>
                            .eu (Europa)
                        </option>
                        <option value="in" <?php selected($api_settings['data_center'] ?? '', 'in'); ?>>
                            .in (India)
                        </option>
                        <option value="com.cn" <?php selected($api_settings['data_center'] ?? '', 'com.cn'); ?>>
                            .com.cn (China)
                        </option>
                        <option value="com.au" <?php selected($api_settings['data_center'] ?? '', 'com.au'); ?>>
                            .com.au (Australia)
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Seleccione el centro de datos donde está alojada su cuenta de Zoho.', 'woocommerce-zoho-integration'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php _e('Modo Sandbox', 'woocommerce-zoho-integration'); ?>
                </th>
                <td>
                    <label for="wzi_api_settings[sandbox_mode]">
                        <input type="checkbox" 
                               id="wzi_api_settings[sandbox_mode]" 
                               name="wzi_api_settings[sandbox_mode]" 
                               value="yes" 
                               <?php checked($api_settings['sandbox_mode'] ?? 'no', 'yes'); ?> />
                        <?php _e('Usar entorno sandbox de Zoho', 'woocommerce-zoho-integration'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Active esta opción para usar el entorno de pruebas de Zoho.', 'woocommerce-zoho-integration'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Guardar Configuración', 'woocommerce-zoho-integration')); ?>
    </form>
    
    <!-- Autorización OAuth -->
    <?php if (!empty($api_settings['client_id']) && !empty($api_settings['client_secret'])): ?>
        <div class="wzi-oauth-section">
            <h3><?php _e('Autorización OAuth', 'woocommerce-zoho-integration'); ?></h3>
            
            <form method="post" action="<?php echo admin_url('admin.php?page=wzi-settings&tab=api&action=authorize'); ?>">
                <div class="wzi-info-box">
                    <h4><?php _e('Servicios a Autorizar', 'woocommerce-zoho-integration'); ?></h4>
                    <p><?php _e('Seleccione los servicios de Zoho que desea integrar:', 'woocommerce-zoho-integration'); ?></p>
                    
                    <div class="services-checkboxes">
                        <label>
                            <input type="checkbox" name="services[]" value="crm" checked />
                            <?php _e('Zoho CRM', 'woocommerce-zoho-integration'); ?>
                        </label>
                        <br/>
                        <label>
                            <input type="checkbox" name="services[]" value="inventory" />
                            <?php _e('Zoho Inventory', 'woocommerce-zoho-integration'); ?>
                        </label>
                        <br/>
                        <label>
                            <input type="checkbox" name="services[]" value="books" />
                            <?php _e('Zoho Books', 'woocommerce-zoho-integration'); ?>
                        </label>
                        <br/>
                        <label>
                            <input type="checkbox" name="services[]" value="campaigns" />
                            <?php _e('Zoho Campaigns', 'woocommerce-zoho-integration'); ?>
                        </label>
                    </div>
                </div>
                
                <p>
                    <button type="submit" class="button button-primary">
                        <?php _e('Autorizar con Zoho', 'woocommerce-zoho-integration'); ?>
                    </button>
                </p>
            </form>
        </div>
    <?php else: ?>
        <div class="wzi-warning-box">
            <h4><?php _e('Configuración Requerida', 'woocommerce-zoho-integration'); ?></h4>
            <p><?php _e('Por favor, ingrese su Client ID y Client Secret antes de autorizar.', 'woocommerce-zoho-integration'); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Instrucciones -->
    <div class="wzi-instructions">
        <h3><?php _e('Instrucciones de Configuración', 'woocommerce-zoho-integration'); ?></h3>
        
        <ol>
            <li>
                <?php _e('Vaya a la', 'woocommerce-zoho-integration'); ?> 
                <a href="https://api-console.zoho.com/" target="_blank">
                    <?php _e('Consola de API de Zoho', 'woocommerce-zoho-integration'); ?>
                </a>
            </li>
            <li><?php _e('Cree una nueva aplicación del tipo "Server-based Applications"', 'woocommerce-zoho-integration'); ?></li>
            <li><?php _e('Configure los siguientes detalles:', 'woocommerce-zoho-integration'); ?>
                <ul>
                    <li><strong><?php _e('Homepage URL:', 'woocommerce-zoho-integration'); ?></strong> <?php echo home_url(); ?></li>
                    <li><strong><?php _e('Authorized Redirect URIs:', 'woocommerce-zoho-integration'); ?></strong> 
                        <code class="wzi-code-display"><?php echo admin_url('admin.php?page=wzi-settings&tab=api&action=callback'); ?></code>
                    </li>
                </ul>
            </li>
            <li><?php _e('Copie el Client ID y Client Secret generados', 'woocommerce-zoho-integration'); ?></li>
            <li><?php _e('Pegue las credenciales en los campos correspondientes arriba', 'woocommerce-zoho-integration'); ?></li>
            <li><?php _e('Guarde la configuración', 'woocommerce-zoho-integration'); ?></li>
            <li><?php _e('Haga clic en "Autorizar con Zoho" y seleccione los servicios deseados', 'woocommerce-zoho-integration'); ?></li>
        </ol>
    </div>
</div>

<style>
.services-checkboxes {
    margin: 10px 0;
    padding-left: 20px;
}

.services-checkboxes label {
    display: block;
    margin: 5px 0;
}

.wzi-code-display {
    background: #f5f5f5;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    word-break: break-all;
}

.wzi-instructions {
    margin-top: 30px;
}

.wzi-instructions ol {
    margin-left: 20px;
}

.wzi-instructions li {
    margin: 10px 0;
}

.wzi-instructions ul {
    margin: 10px 0 10px 20px;
}

.wzi-oauth-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Copiar URL
    $('.wzi-copy-url').on('click', function() {
        var targetId = $(this).data('target');
        var input = $('input[name="' + targetId + '"]')[0];
        
        input.select();
        input.setSelectionRange(0, 99999);
        
        document.execCommand('copy');
        
        $(this).text('<?php _e('Copiado!', 'woocommerce-zoho-integration'); ?>');
        setTimeout(() => {
            $(this).text('<?php _e('Copiar', 'woocommerce-zoho-integration'); ?>');
        }, 2000);
    });
    
    // Probar conexión
    $('.wzi-test-connection').on('click', function() {
        var button = $(this);
        var service = button.data('service');
        
        button.prop('disabled', true).text('<?php _e('Probando...', 'woocommerce-zoho-integration'); ?>');
        
        $.ajax({
            url: wzi_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wzi_test_connection',
                service: service,
                nonce: wzi_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.text('<?php _e('✓ Conexión OK', 'woocommerce-zoho-integration'); ?>');
                    button.addClass('button-primary');
                } else {
                    button.text('<?php _e('✗ Error', 'woocommerce-zoho-integration'); ?>');
                    alert(response.message);
                }
            },
            error: function() {
                button.text('<?php _e('✗ Error', 'woocommerce-zoho-integration'); ?>');
            },
            complete: function() {
                setTimeout(function() {
                    button.prop('disabled', false).text('<?php _e('Probar Conexión', 'woocommerce-zoho-integration'); ?>');
                    button.removeClass('button-primary');
                }, 3000);
            }
        });
    });
});
</script>