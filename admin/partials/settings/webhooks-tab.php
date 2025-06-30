<?php
/**
 * Vista de la pestaña de configuración de Webhooks
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
    settings_fields('wzi_webhook_settings_group'); // Grupo de opciones para esta pestaña
    do_settings_sections('wzi_webhook_settings');    // Página de opciones para esta pestaña
    submit_button(__('Guardar Configuración de Webhooks', 'woocommerce-zoho-integration'));
    ?>
</form>
