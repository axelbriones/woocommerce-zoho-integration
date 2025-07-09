<?php
/**
 * Define la funcionalidad de internacionalización
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * Define la funcionalidad de internacionalización.
 *
 * Carga y define los archivos de internacionalización para este plugin
 * para que esté listo para traducción.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_i18n {

    /**
     * Cargar el dominio de texto del plugin para traducción.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {
        // WordPress debería usar el 'Domain Path' especificado en la cabecera del plugin
        // cuando el tercer argumento (deprecado para path absoluto) es false o se omite.
        // El 'Text Domain' y 'Domain Path' están definidos en woocommerce-zoho-integration.php
        load_plugin_textdomain('woocommerce-zoho-integration');
    }
}