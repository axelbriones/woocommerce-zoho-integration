<?php
/**
 * Vista de la pestaña de configuración de Sincronización
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/admin/partials/settings
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>
<form method="post" action="options.php">
    <?php
    settings_fields('wzi_sync_settings_group'); // Grupo de opciones para esta pestaña
    do_settings_sections('wzi_sync_settings');    // Página de opciones para esta pestaña
    submit_button(__('Guardar Configuración de Sincronización', 'woocommerce-zoho-integration'));
    ?>
</form>
