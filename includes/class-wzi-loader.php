<?php
/**
 * Registra todos los hooks del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * Registra todos los hooks del plugin.
 *
 * Mantiene una lista de todos los hooks que son registrados a lo largo
 * del plugin, y los registra con la API de WordPress cuando se ejecuta run().
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Loader {

    /**
     * El array de acciones registradas con WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $actions    Las acciones registradas con WordPress.
     */
    protected $actions;

    /**
     * El array de filtros registrados con WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $filters    Los filtros registrados con WordPress.
     */
    protected $filters;

    /**
     * Inicializar las colecciones usadas para mantener las acciones y filtros.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Añadir una nueva acción a la colección para ser registrada con WordPress.
     *
     * @since    1.0.0
     * @param    string               $hook             El nombre del hook de WordPress al que se está registrando.
     * @param    object               $component        Una referencia a la instancia del objeto en el que se define el método.
     * @param    string               $callback         El nombre de la definición de función en el $component.
     * @param    int                  $priority         Opcional. La prioridad en la que se debe ejecutar la función. Por defecto es 10.
     * @param    int                  $accepted_args    Opcional. El número de argumentos que se deben pasar a la función $callback. Por defecto es 1.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Añadir un nuevo filtro a la colección para ser registrado con WordPress.
     *
     * @since    1.0.0
     * @param    string               $hook             El nombre del hook de WordPress al que se está registrando.
     * @param    object               $component        Una referencia a la instancia del objeto en el que se define el método.
     * @param    string               $callback         El nombre de la definición de función en el $component.
     * @param    int                  $priority         Opcional. La prioridad en la que se debe ejecutar la función. Por defecto es 10.
     * @param    int                  $accepted_args    Opcional. El número de argumentos que se deben pasar a la función $callback. Por defecto es 1.
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Una función de utilidad que se usa para registrar las acciones y hooks en una sola colección.
     *
     * @since    1.0.0
     * @access   private
     * @param    array                $hooks            La colección de hooks que se está registrando (acciones o filtros).
     * @param    string               $hook             El nombre del hook de WordPress al que se está registrando.
     * @param    object               $component        Una referencia a la instancia del objeto en el que se define el método.
     * @param    string               $callback         El nombre de la definición de función en el $component.
     * @param    int                  $priority         La prioridad en la que se debe ejecutar la función.
     * @param    int                  $accepted_args    El número de argumentos que se deben pasar a la función $callback.
     * @return   array                                  La colección de acciones y filtros registrados con WordPress.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Registrar los filtros y acciones con WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}