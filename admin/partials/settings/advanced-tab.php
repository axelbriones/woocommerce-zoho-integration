<?php
/**
 * Vista de la pestaña de configuración Avanzada
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
    settings_fields('wzi_advanced_settings_group'); // Nombre del grupo de opciones
    do_settings_sections('wzi_advanced_settings');    // ID/slug de la página de opciones donde se registró la sección
    submit_button(__('Guardar Configuración Avanzada', 'woocommerce-zoho-integration'));
    ?>
</form>
</div>
