<?php
/**
 * Plugin Name: WooCommerce Zoho Integration
 * Plugin URI: https://bbrion.es/woocommerce-zoho-integration
 * Description: Integración avanzada entre WooCommerce y la suite Zoho
 * Version: 1.2.4
 * Author: Byron Briones
 * Author URI: https://bbrion.es
 * License: GPL-2.0+
 * Text Domain: woocommerce-zoho-integration
 * Domain Path: /languages
 * WC requires at least: 5.0.0
 * WC tested up to: 7.0
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

/**
 * Versión actual del plugin.
 */
define('WZI_VERSION', '1.2.4');

/**
 * Definir constantes del plugin
 */
define('WZI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WZI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WZI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WZI_PLUGIN_NAME', 'woocommerce-zoho-integration');

/**
 * El código que se ejecuta durante la activación del plugin.
 */
function activate_woocommerce_zoho_integration() {
    require_once WZI_PLUGIN_DIR . 'includes/class-wzi-activator.php';
    WZI_Activator::activate();
}

/**
 * El código que se ejecuta durante la desactivación del plugin.
 */
function deactivate_woocommerce_zoho_integration() {
    require_once WZI_PLUGIN_DIR . 'includes/class-wzi-deactivator.php';
    WZI_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woocommerce_zoho_integration');
register_deactivation_hook(__FILE__, 'deactivate_woocommerce_zoho_integration');

/**
 * La clase principal del plugin
 */
require WZI_PLUGIN_DIR . 'includes/class-wzi-main.php';

/**
 * Verificar si WooCommerce está activo
 */
function wzi_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wzi_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Aviso si WooCommerce no está activo
 */
function wzi_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('WooCommerce Zoho Integration requiere que WooCommerce esté instalado y activo.', 'woocommerce-zoho-integration'); ?></p>
    </div>
    <?php
}

/**
 * Comienza la ejecución del plugin.
 */
function run_woocommerce_zoho_integration() {
    if (wzi_check_woocommerce()) {
        $plugin = new WZI_Main();
        $plugin->run();
    }
}

// Esperar a que todos los plugins estén cargados
add_action('plugins_loaded', 'run_woocommerce_zoho_integration');

/**
 * Función de acceso global a la instancia del plugin
 */
function WZI() {
    return WZI_Main::get_instance();
}