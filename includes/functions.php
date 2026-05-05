<?php

/**
 * Template Manager Utility Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get database-backed templates, migrating from the legacy option when needed.
 *
 * @return array<string, array<string, mixed>>
 */
function anyphefo_get_database_templates()
{
    $templates = get_option(ANYPHEFO_DATABASE_TEMPLATES_OPTION, null);

    if (null === $templates) {
        $legacy_templates = get_option(ANYPHEFO_LEGACY_DATABASE_TEMPLATES_OPTION, null);

        if (is_array($legacy_templates)) {
            update_option(ANYPHEFO_DATABASE_TEMPLATES_OPTION, $legacy_templates);

            return $legacy_templates;
        }

        return array();
    }

    return is_array($templates) ? $templates : array();
}

/**
 * Persist database-backed templates and remove the legacy option after migration.
 *
 * @param array<string, array<string, mixed>> $templates Template data keyed by slug.
 * @return bool
 */
function anyphefo_update_database_templates($templates)
{
    $templates = is_array($templates) ? $templates : array();
    $did_update = update_option(ANYPHEFO_DATABASE_TEMPLATES_OPTION, $templates);

    if (false !== get_option(ANYPHEFO_LEGACY_DATABASE_TEMPLATES_OPTION, false)) {
        delete_option(ANYPHEFO_LEGACY_DATABASE_TEMPLATES_OPTION);
    }

    return $did_update;
}

/**
 * Register custom template type for theme.
 */
function anyphefo_register_theme_support()
{
    // Theme support functions here.
    // Custom template types will be handled by Elementor integration.
}
add_action('after_setup_theme', 'anyphefo_register_theme_support');

/**
 * Get available header sources - pages/posts whose title contains "header".
 *
 * @return array<int, array<string, mixed>>
 */
function anyphefo_get_available_headers()
{
    $headers = array();

    // 1. Elementor Headers (by type meta).
    if (class_exists('\Elementor\Plugin')) {
        $elementor_headers = get_posts(
            array(
                'post_type'              => 'elementor_library',
                'posts_per_page'         => -1,
                'post_status'            => 'publish',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'meta_query'             => array(
                    array(
                        'key'   => '_type',
                        'value' => 'header',
                    ),
                ),
            )
        );

        foreach ($elementor_headers as $post) {
            $headers[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title . ' (Elementor)',
                'type'  => 'elementor',
            );
        }
    }

    // 2. Pages and Posts whose title contains the word "header".
    $wp_headers = get_posts(
        array(
            'post_type'      => array('page', 'post'),
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            's'              => 'header',
        )
    );

    foreach ($wp_headers as $post) {
        // Only include if "header" is actually in the title (search also hits content).
        if (false !== stripos($post->post_title, 'header')) {
            $headers[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title . ' (' . ucfirst($post->post_type) . ')',
                'type'  => 'wp',
            );
        }
    }

    return $headers;
}

/**
 * Get available footer sources - pages/posts whose title contains "footer".
 *
 * @return array<int, array<string, mixed>>
 */
function anyphefo_get_available_footers()
{
    $footers = array();

    // 1. Elementor Footers (by type meta).
    if (class_exists('\Elementor\Plugin')) {
        $elementor_footers = get_posts(
            array(
                'post_type'              => 'elementor_library',
                'posts_per_page'         => -1,
                'post_status'            => 'publish',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'meta_query'             => array(
                    array(
                        'key'   => '_type',
                        'value' => 'footer',
                    ),
                ),
            )
        );

        foreach ($elementor_footers as $post) {
            $footers[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title . ' (Elementor)',
                'type'  => 'elementor',
            );
        }
    }

    // 2. Pages and Posts whose title contains the word "footer".
    $wp_footers = get_posts(
        array(
            'post_type'      => array('page', 'post'),
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            's'              => 'footer',
        )
    );

    foreach ($wp_footers as $post) {
        // Only include if "footer" is actually in the title.
        if (false !== stripos($post->post_title, 'footer')) {
            $footers[] = array(
                'id'    => $post->ID,
                'title' => $post->post_title . ' (' . ucfirst($post->post_type) . ')',
                'type'  => 'wp',
            );
        }
    }

    return $footers;
}

/**
 * Get Elementor IDs embedded in a template file or its associated header/footer files.
 *
 * @param string $file Template file path.
 * @return array{header_id:int, footer_id:int}
 */
function anyphefo_get_template_elementor_ids($file)
{
    $ids = array(
        'header_id' => 0,
        'footer_id' => 0,
    );

    if (!is_readable($file)) {
        return $ids;
    }

    $directory = trailingslashit(dirname($file));
    $slug_base = str_replace(array('template-cu-', '.php'), '', basename($file));

    $header_file = $directory . 'header-' . $slug_base . '.php';
    $footer_file = $directory . 'footer-' . $slug_base . '.php';

    // 1. Try to get header ID from header file.
    if (file_exists($header_file)) {
        $header_content = file_get_contents($header_file);
        if ($header_content && preg_match('/if\s*\((\d+)\)/', $header_content, $matches)) {
            $ids['header_id'] = absint($matches[1]);
        }
    }

    // 2. Try to get footer ID from footer file.
    if (file_exists($footer_file)) {
        $footer_content = file_get_contents($footer_file);
        if ($footer_content && preg_match('/if\s*\((\d+)\)/', $footer_content, $matches)) {
            $ids['footer_id'] = absint($matches[1]);
        }
    }

    // Fallback: If for some reason they are still in the main file (legacy support).
    if (!$ids['header_id'] || !$ids['footer_id']) {
        $contents = file_get_contents($file);

        if ($contents) {
            if (!$ids['header_id'] && preg_match('/\$custom_header_id\s*=\s*(\d+)\s*;/', $contents, $matches)) {
                $ids['header_id'] = absint($matches[1]);
            }

            if (!$ids['footer_id'] && preg_match('/\$custom_footer_id\s*=\s*(\d+)\s*;/', $contents, $matches)) {
                $ids['footer_id'] = absint($matches[1]);
            }
        }
    }

    return $ids;
}

/**
 * Collect all registered custom templates from the plugin, theme, and database.
 *
 * @return array<int, array<string, mixed>>
 */
function anyphefo_get_registered_templates()
{
    $templates = array();

    // 1. Get database-stored templates.
    $db_templates = anyphefo_get_database_templates();
    foreach ($db_templates as $slug => $data) {
        $templates[$slug] = array(
            'name'            => $data['name'],
            'slug'            => $slug,
            'full_path'       => 'db://' . $slug,
            'source'          => 'database',
            'has_plugin_copy' => false,
            'has_theme_copy'  => false,
            'has_db_copy'     => true,
            'header_id'       => $data['header_id'],
            'footer_id'       => $data['footer_id'],
        );
    }

    // 2. Get file-based templates.
    $directories = array(
        'plugin' => trailingslashit(ANYPHEFO_PLUGIN_DIR . 'templates'),
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

            $ids = anyphefo_get_template_elementor_ids($file);

            if (!isset($templates[$slug])) {
                $templates[$slug] = array(
                    'name'            => $file_data['Template Name'],
                    'slug'            => $slug,
                    'full_path'       => $file,
                    'source'          => $source,
                    'has_plugin_copy' => false,
                    'has_theme_copy'  => false,
                    'has_db_copy'     => false,
                    'header_id'       => $ids['header_id'],
                    'footer_id'       => $ids['footer_id'],
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
 *
 * @param string $slug Template slug.
 * @return array<string, mixed>|string
 */
function anyphefo_get_template_file_by_slug($slug)
{
    foreach (anyphefo_get_registered_templates() as $template) {
        if ($template['slug'] === $slug) {
            if ('database' === $template['source']) {
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
 *
 * @param string $slug Template slug.
 * @param string $content Template contents.
 * @return array{success:bool, error:string}
 */
function anyphefo_create_custom_template($slug, $content)
{
    unset($slug, $content);

    return array(
        'success' => false,
        'error'   => 'Filesystem template creation is disabled. Use database template storage.',
    );
}

/**
 * Activate plugin functions.
 */
function anyphefo_activate_functions()
{
    // Plugin activation cleanup.
    if (!wp_next_scheduled('anyphefo_plugin_activation')) {
        wp_schedule_single_event(time(), 'anyphefo_plugin_activation');
    }
}

/**
 * Sanitize template name for slug.
 *
 * @param string $name Template name.
 * @return string
 */
function anyphefo_slugify($name)
{
    return sanitize_title($name);
}

/**
 * Get page template info.
 *
 * @return array{name:?string, slug:?string, file:array<string,mixed>|string|null, exists:bool}
 */
function anyphefo_get_template_info()
{
    if (is_page()) {
        $template = get_page_template_slug();
        $template_file = anyphefo_get_template_file_by_slug($template);

        return array(
            'name'   => get_the_title(),
            'slug'   => $template,
            'file'   => $template_file,
            'exists' => (bool) $template_file,
        );
    }

    return array(
        'name'   => null,
        'slug'   => null,
        'file'   => null,
        'exists' => false,
    );
}

/**
 * Register custom template type for themes using this plugin.
 *
 * @param array<string, string> $template_types Existing template types.
 * @return array<string, string>
 */
function anyphefo_register_template_type($template_types)
{
    return array_merge(
        $template_types,
        array(
            'cu-custom-template' => __('Custom Template', 'anypage-header-footer-for-elementor'),
        )
    );
}
