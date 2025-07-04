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