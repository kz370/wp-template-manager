<?php

/**
 * Template Manager Utility Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom template type for theme
 */
function tm_register_theme_support()
{
    // Theme support functions here
    // Custom template types will be handled by Elementor integration
}
add_action('after_setup_theme', 'tm_register_theme_support');

/**
 * Get available header sources - pages/posts whose title contains "header".
 */
function tm_get_available_headers()
{
    $headers = [];

    // 1. Elementor Headers (by type meta)
    if (class_exists('\Elementor\Plugin')) {
        $elementor_headers = get_posts([
            'post_type'              => 'elementor_library',
            'posts_per_page'         => -1,
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'             => [[
                'key'   => '_type',
                'value' => 'header',
            ]],
        ]);

        foreach ($elementor_headers as $post) {
            $headers[] = [
                'id'    => $post->ID,
                'title' => $post->post_title . ' (Elementor)',
                'type'  => 'elementor',
            ];
        }
    }

    // 2. Pages and Posts whose title contains the word "header"
    $wp_headers = get_posts([
        'post_type'      => ['page', 'post'],
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        's'              => 'header',
    ]);

    foreach ($wp_headers as $post) {
        // Only include if "header" is actually in the title (search also hits content)
        if (stripos($post->post_title, 'header') !== false) {
            $headers[] = [
                'id'    => $post->ID,
                'title' => $post->post_title . ' (' . ucfirst($post->post_type) . ')',
                'type'  => 'wp',
            ];
        }
    }

    return $headers;
}

/**
 * Get available footer sources - pages/posts whose title contains "footer".
 */
function tm_get_available_footers()
{
    $footers = [];

    // 1. Elementor Footers (by type meta)
    if (class_exists('\Elementor\Plugin')) {
        $elementor_footers = get_posts([
            'post_type'              => 'elementor_library',
            'posts_per_page'         => -1,
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'     => [[
                'key'   => '_type',
                'value' => 'footer',
            ]],
        ]);

        foreach ($elementor_footers as $post) {
            $footers[] = [
                'id'    => $post->ID,
                'title' => $post->post_title . ' (Elementor)',
                'type'  => 'elementor',
            ];
        }
    }

    // 2. Pages and Posts whose title contains the word "footer"
    $wp_footers = get_posts([
        'post_type'      => ['page', 'post'],
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        's'              => 'footer',
    ]);

    foreach ($wp_footers as $post) {
        // Only include if "footer" is actually in the title
        if (stripos($post->post_title, 'footer') !== false) {
            $footers[] = [
                'id'    => $post->ID,
                'title' => $post->post_title . ' (' . ucfirst($post->post_type) . ')',
                'type'  => 'wp',
            ];
        }
    }

    return $footers;
}

/**
 * Get Elementor IDs embedded in a template file or its associated header/footer files.
 */
function tm_get_template_elementor_ids($file)
{
    $ids = array(
        'header_id' => 0,
        'footer_id' => 0,
    );

    if (!is_readable($file)) {
        return $ids;
    }

    $directory = trailingslashit(dirname($file));
    $slug_base = str_replace(['template-cu-', '.php'], '', basename($file));

    $header_file = $directory . 'header-' . $slug_base . '.php';
    $footer_file = $directory . 'footer-' . $slug_base . '.php';

    // 1. Try to get header ID from header file
    if (file_exists($header_file)) {
        $header_content = file_get_contents($header_file);
        if (preg_match('/if\s*\((\d+)\)/', $header_content, $matches)) {
            $ids['header_id'] = absint($matches[1]);
        }
    }

    // 2. Try to get footer ID from footer file
    if (file_exists($footer_file)) {
        $footer_content = file_get_contents($footer_file);
        if (preg_match('/if\s*\((\d+)\)/', $footer_content, $matches)) {
            $ids['footer_id'] = absint($matches[1]);
        }
    }

    // Fallback: If for some reason they are still in the main file (legacy support)
    if (!$ids['header_id'] || !$ids['footer_id']) {
        $contents = file_get_contents($file);
        if (!$ids['header_id'] && preg_match('/\$custom_header_id\s*=\s*(\d+)\s*;/', $contents, $matches)) {
            $ids['header_id'] = absint($matches[1]);
        }
        if (!$ids['footer_id'] && preg_match('/\$custom_footer_id\s*=\s*(\d+)\s*;/', $contents, $matches)) {
            $ids['footer_id'] = absint($matches[1]);
        }
    }

    return $ids;
}

/**
 * Collect all registered custom templates from the plugin, theme, and database.
 */
function tm_get_registered_templates()
{
    $templates = array();

    // 1. Get database-stored templates
    $db_templates = get_option('tm_database_templates', []);
    foreach ($db_templates as $slug => $data) {
        $templates[$slug] = array(
            'name' => $data['name'],
            'slug' => $slug,
            'full_path' => 'db://' . $slug,
            'source' => 'database',
            'has_plugin_copy' => false,
            'has_theme_copy' => false,
            'has_db_copy' => true,
            'header_id' => $data['header_id'],
            'footer_id' => $data['footer_id'],
        );
    }

    // 2. Get file-based templates
    $directories = array(
        'plugin' => trailingslashit(TEMPLATE_MANAGER_PLUGIN_DIR . 'templates'),
    );

    $theme_dir = get_stylesheet_directory();

    if ($theme_dir && is_dir($theme_dir)) {
        $directories['theme'] = trailingslashit($theme_dir);
    }

    foreach ($directories as $source => $directory) {
        if (!is_dir($directory)) {
            continue;
        }

        $files = glob($directory . 'template-cu-*.php');

        if (empty($files)) {
            continue;
        }

        foreach ($files as $file) {
            $slug = basename($file);
            $file_data = get_file_data($file, array('Template Name' => 'Template Name'));

            if (empty($file_data['Template Name'])) {
                continue;
            }

            $ids = tm_get_template_elementor_ids($file);

            if (!isset($templates[$slug])) {
                $templates[$slug] = array(
                    'name' => $file_data['Template Name'],
                    'slug' => $slug,
                    'full_path' => $file,
                    'source' => $source,
                    'has_plugin_copy' => false,
                    'has_theme_copy' => false,
                    'has_db_copy' => false,
                    'header_id' => $ids['header_id'],
                    'footer_id' => $ids['footer_id'],
                );
            }

            if ('theme' === $source) {
                $templates[$slug]['name'] = $file_data['Template Name'];
                $templates[$slug]['full_path'] = $file;
                $templates[$slug]['source'] = 'theme';
                $templates[$slug]['has_theme_copy'] = true;
                $templates[$slug]['header_id'] = $ids['header_id'];
                $templates[$slug]['footer_id'] = $ids['footer_id'];
            } elseif ('plugin' === $source) {
                $templates[$slug]['has_plugin_copy'] = true;
            }

            if ('theme' !== $source && !$templates[$slug]['has_theme_copy'] && 'database' !== $templates[$slug]['source']) {
                $templates[$slug]['full_path'] = $file;
            }
        }
    }

    uasort(
        $templates,
        static function ($left, $right) {
            return strcasecmp($left['name'], $right['name']);
        }
    );

    return array_values($templates);
}

/**
 * Find the preferred template file or data for a selected template slug.
 */
function tm_get_template_file_by_slug($slug)
{
    foreach (tm_get_registered_templates() as $template) {
        if ($template['slug'] === $slug) {
            if ($template['source'] === 'database') {
                return $template;
            }
            if (file_exists($template['full_path'])) {
                return $template['full_path'];
            }
        }
    }

    return '';
}

/**
 * Deprecated: template creation now uses database storage only.
 */
function tm_create_custom_template($slug, $content)
{
    unset($slug, $content);

    return [
        'success' => false,
        'error' => 'Filesystem template creation is disabled. Use database template storage.',
    ];
}

/**
 * Activate plugin functions
 */
function tm_activate_functions()
{
    // Plugin activation cleanup
    if (!wp_next_scheduled('tm_plugin_activation')) {
        wp_schedule_single_event(time(), 'tm_plugin_activation');
    }
}

/**
 * Sanitize template name for slug
 */
function tm_slugify($name)
{
    return sanitize_title($name);
}

/**
 * Get page template info
 */
function tm_get_template_info()
{
    if (is_page()) {
        $template = get_page_template_slug();

        return [
            'name' => get_the_title(),
            'slug' => $template,
            'file' => tm_get_template_file_by_slug($template),
            'exists' => (bool) tm_get_template_file_by_slug($template),
        ];
    }

    return [
        'name' => null,
        'slug' => null,
        'file' => null,
        'exists' => false,
    ];
}

/**
 * Register custom template type for themes using this plugin
 */
function tm_register_template_type($template_types)
{
    return array_merge($template_types, [
        'cu-custom-template' => __('Custom Template', 'anypage-header-footer-for-elementor'),
    ]);
}
