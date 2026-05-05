<?php
/**
 * Class for loading all plugin components
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template Manager Loader
 */
class Anyphefo_Loader {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - initialize all components
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize plugin hooks
     */
    private function init_hooks() {
        // Load the template manager class
        add_action('init', array(Anyphefo_Template_Manager::class, 'init'), 1);
    }
}

// Initialize loader
Anyphefo_Loader::get_instance();
