<?php
/**
 * Vista de la página de configuración
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/admin/partials
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener tab activa
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Verificar si se guardó la configuración
if (isset($_GET['settings-updated'])) {
    add_settings_error('wzi_messages', 'wzi_message', __('Configuración guardada', 'woocommerce-zoho-integration'), 'updated');
}

// Mostrar mensajes
settings_errors('wzi_messages');
?>

<div class="wrap wzi-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Tabs de navegación -->
    <nav class="nav-tab-wrapper">
        <a href="?page=wzi-settings&tab=general" 
           class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'woocommerce-zoho-integration'); ?>
        </a>
        <a href="?page=wzi-settings&tab=api" 
           class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <?php _e('API de Zoho', 'woocommerce-zoho-integration'); ?>
        </a>
        <a href="?page=wzi-settings&tab=sync" 
           class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Sincronización', 'woocommerce-zoho-integration'); ?>
        </a>
        <a href="?page=wzi-settings&tab=mapping" 
           class="nav-tab <?php echo $active_tab === 'mapping' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Mapeo de Campos', 'woocommerce-zoho-integration'); ?>
        </a>
        <a href="?page=wzi-settings&tab=webhooks" 
           class="nav-tab <?php echo $active_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Webhooks', 'woocommerce-zoho-integration'); ?>
        </a>
        <a href="?page=wzi-settings&tab=advanced" 
           class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Avanzado', 'woocommerce-zoho-integration'); ?>
        </a>
    </nav>
    
    <div class="tab-content">
        <?php
        switch ($active_tab) {
            case 'general':
                include 'settings/general-tab.php';
                break;
            case 'api':
                include 'settings/api-tab.php';
                break;
            case 'sync':
                include 'settings/sync-tab.php';
                break;
            case 'mapping':
                include 'settings/mapping-tab.php';
                break;
            case 'webhooks':
                include 'settings/webhooks-tab.php';
                break;
            case 'advanced':
                include 'settings/advanced-tab.php';
                break;
            default:
                include 'settings/general-tab.php';
        }
        ?>
    </div>
</div>

<style>
.wzi-settings {
    max-width: 1200px;
}

.tab-content {
    background: #fff;
    padding: 20px;
    margin-top: 0;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.wzi-settings .form-table th {
    width: 250px;
}

.wzi-settings .description {
    font-style: normal;
    color: #666;
}

.wzi-settings .button-group {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.connection-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 3px;
    font-weight: 500;
}

.connection-status.connected {
    background: #d4edda;
    color: #155724;
}

.connection-status.disconnected {
    background: #f8d7da;
    color: #721c24;
}

.wzi-info-box {
    background: #f0f8ff;
    border: 1px solid #b8daff;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
}

.wzi-info-box h4 {
    margin-top: 0;
    color: #004085;
}

.wzi-warning-box {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
}

.wzi-warning-box h4 {
    margin-top: 0;
    color: #856404;
}

.field-mapping-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.field-mapping-table th,
.field-mapping-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.field-mapping-table th {
    background: #f5f5f5;
    font-weight: 600;
}

.field-mapping-table select,
.field-mapping-table input[type="text"] {
    width: 100%;
}

.mapping-actions {
    display: flex;
    gap: 5px;
}

.webhook-url-display {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    font-family: monospace;
    word-break: break-all;
    margin: 5px 0;
}

.sync-direction-selector {
    display: flex;
    gap: 20px;
    margin: 10px 0;
}

.sync-direction-selector label {
    display: flex;
    align-items: center;
    gap: 5px;
}
</style>