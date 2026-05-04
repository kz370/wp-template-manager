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

define('TEMPLATE_MANAGER_VERSION', '1.0.0');
define('TEMPLATE_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TEMPLATE_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Activate the plugin
 */
function tm_activate()
{
    if (!wp_next_scheduled('tm_plugin_activation')) {
        wp_schedule_single_event(time(), 'tm_plugin_activation');
    }
}
register_activation_hook(__FILE__, 'tm_activate');

/**
 * Deactivate the plugin
 */
function tm_deactivate()
{
    wp_clear_scheduled_hook('tm_plugin_activation');
}
register_deactivation_hook(__FILE__, 'tm_deactivate');

// Include core classes
require_once TEMPLATE_MANAGER_PLUGIN_DIR . 'includes/class-tm-loader.php';
require_once TEMPLATE_MANAGER_PLUGIN_DIR . 'includes/class-tm-anypage-header-footer-for-elementor.php';
require_once TEMPLATE_MANAGER_PLUGIN_DIR . 'includes/functions.php';
