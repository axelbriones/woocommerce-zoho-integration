<?php
/**
 * Vista de la pestaña de configuración General
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
    settings_fields('wzi_general_settings_group'); // Grupo de opciones para esta pestaña
    do_settings_sections('wzi_general_settings');    // Página de opciones para esta pestaña
    submit_button(__('Guardar Configuración General', 'woocommerce-zoho-integration'));
    ?>
</form>
