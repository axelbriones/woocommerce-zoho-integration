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

// Si hay opciones para la pestaña avanzada, se registrarían con un grupo como 'wzi_advanced_settings_group'
// settings_fields('wzi_advanced_settings_group');
// do_settings_sections('wzi_advanced_settings');
?>
<div class="wrap">
    <p><?php esc_html_e('Configuraciones avanzadas para la integración de WooCommerce con Zoho.', 'woocommerce-zoho-integration'); ?></p>

    <?php
    // Aquí irían los campos para las configuraciones avanzadas.
    // Por ejemplo, opciones de debug, timeouts, comportamiento de la cola, etc.

    // Si hay un botón de guardado general para algunas opciones de esta pestaña:
    // submit_button(__('Guardar Configuración Avanzada', 'woocommerce-zoho-integration'));
    ?>
     <div class="notice notice-info inline">
        <p><?php esc_html_e('Actualmente no hay configuraciones avanzadas definidas. Esta pestaña se utilizará para futuras opciones.', 'woocommerce-zoho-integration'); ?></p>
    </div>
</div>
