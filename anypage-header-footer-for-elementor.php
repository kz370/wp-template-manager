<?php

/**
 * Plugin Name: AnyPage Header Footer for Elementor
 * Plugin URI: https://github.com/kz370/wp-anypage-header-footer-for-elementor
 * Description: Manage custom header/footer templates for Elementor. Use any page or post as a header or footer template. (No affiliation with Elementor.)
 * Version: 1.0.0
 * Author: kz370
 * Author URI: https://github.com/kz370
 * License: GPLv2 or later
 * Text Domain: anypage-header-footer-for-elementor
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * Requires Plugins: elementor
 * Stable tag: 1.0.0
 * Icon: icon.png
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

define('ANYPHEFO_VERSION', '1.0.0');
define('ANYPHEFO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANYPHEFO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ANYPHEFO_DATABASE_TEMPLATES_OPTION', 'anyphefo_database_templates');
define('ANYPHEFO_LEGACY_DATABASE_TEMPLATES_OPTION', 'tm_database_templates');

/**
 * Activate the plugin
 */
function anyphefo_activate()
{
    if (!wp_next_scheduled('anyphefo_plugin_activation')) {
        wp_schedule_single_event(time(), 'anyphefo_plugin_activation');
    }
}
register_activation_hook(__FILE__, 'anyphefo_activate');

/**
 * Deactivate the plugin
 */
function anyphefo_deactivate()
{
    wp_clear_scheduled_hook('anyphefo_plugin_activation');
    wp_clear_scheduled_hook('tm_plugin_activation');
}
register_deactivation_hook(__FILE__, 'anyphefo_deactivate');

// Include core classes
require_once ANYPHEFO_PLUGIN_DIR . 'includes/class-tm-loader.php';
require_once ANYPHEFO_PLUGIN_DIR . 'includes/class-tm-anypage-header-footer-for-elementor.php';
require_once ANYPHEFO_PLUGIN_DIR . 'includes/functions.php';
