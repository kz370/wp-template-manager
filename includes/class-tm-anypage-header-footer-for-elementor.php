<?php

/**
 * Template Manager Class - Handles template creation, override, and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Anyphefo_Template_Manager
{

    /**
     * Initialize the template manager
     */
    public static function init()
    {
        add_filter('theme_page_templates', array(self::class, 'register_page_templates'), 20, 4);
        add_filter('theme_post_templates', array(self::class, 'register_page_templates'), 20, 4);
        add_filter('theme_templates', array(self::class, 'register_page_templates'), 20, 4);
        add_filter('template_include', array(self::class, 'force_template_override'), 9999);

        // Enqueue admin assets for template management
        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_admin_assets'));

        if (current_user_can('edit_posts') || current_user_can('manage_options')) {
            add_action('admin_menu', array(self::class, 'add_template_management_menu'));
        }
    }

    /**
     * Register plugin templates so they appear in the page editor.
     */
    public static function register_page_templates($templates, $theme, $post, $post_type)
    {
        $available_templates = anyphefo_get_registered_templates();

        foreach ($available_templates as $template) {
            $templates[$template['slug']] = $template['name'];
        }

        return $templates;
    }

    /**
     * Force template override filter
     */
    public static function force_template_override($template)
    {
        if (!is_singular()) {
            return $template;
        }

        $slug = get_page_template_slug();

        if (!$slug) {
            return $template;
        }

        $template_data = anyphefo_get_template_file_by_slug($slug);

        if (!$template_data) {
            return $template;
        }

        // If it's a database template, we need to handle it specially
        if (is_array($template_data) && $template_data['source'] === 'database') {
            self::render_database_template($template_data);
            exit; // Stop further execution since we already rendered the content
        }

        // If it's a file path
        if (is_string($template_data) && file_exists($template_data)) {
            return $template_data;
        }

        return $template;
    }

    /**
     * Render a template stored in the database
     */
    public static function render_database_template($data)
    {
        $header_id = $data['header_id'];
        $footer_id = $data['footer_id'];
?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <?php wp_head(); ?>
        </head>

        <body <?php body_class(); ?>>
            <?php wp_body_open(); ?>

            <?php
            // 🔝 Header (Elementor or WP Post/Page)
            if ($header_id) {
                if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::instance()->documents->get($header_id) && \Elementor\Plugin::instance()->documents->get($header_id)->is_built_with_elementor()) {
                    echo wp_kses_post(\Elementor\Plugin::instance()->frontend->get_builder_content_for_display($header_id));
                } else {
                    $header_post = get_post($header_id);
                    if ($header_post) {
                        echo wp_kses_post(apply_filters('the_content', $header_post->post_content));
                    }
                }
            }
            ?>

            <?php
            while (have_posts()) :
                the_post();
                the_content();
            endwhile;
            ?>

            <?php
            // 🔻 Footer (Elementor or WP Post/Page)
            if ($footer_id) {
                if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::instance()->documents->get($footer_id) && \Elementor\Plugin::instance()->documents->get($footer_id)->is_built_with_elementor()) {
                    echo wp_kses_post(\Elementor\Plugin::instance()->frontend->get_builder_content_for_display($footer_id));
                } else {
                    $footer_post = get_post($footer_id);
                    if ($footer_post) {
                        echo wp_kses_post(apply_filters('the_content', $footer_post->post_content));
                    }
                }
            }
            ?>

            <?php wp_footer(); ?>
        </body>

        </html>
<?php
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook)
    {
        // Load on any of our plugin pages (page param check is most reliable)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin page slug only, not processing form data.
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $is_anyphefo_page = in_array($page, array('anyphefo-templates', 'anyphefo-create-template', 'anyphefo-template-list'), true)
            || in_array($hook, array('post-new.php', 'post.php'), true);

        if ($is_anyphefo_page) {
            wp_enqueue_style(
                'anyphefo-google-fonts',
                'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
                array(),
                ANYPHEFO_VERSION
            );
            wp_enqueue_style(
                'anyphefo-admin-style',
                ANYPHEFO_PLUGIN_URL . 'admin/css/admin.css',
                array('anyphefo-google-fonts'),
                ANYPHEFO_VERSION
            );
            wp_enqueue_script(
                'anyphefo-admin-script',
                ANYPHEFO_PLUGIN_URL . 'admin/js/admin.js',
                array(),
                ANYPHEFO_VERSION,
                true
            );
        }
    }
    

    /**
     * Add admin menu for template management
     */
    public static function add_template_management_menu()
    {
        add_menu_page(
            'Anypage Header & Footer',
            'Anypage Header & Footer',
            'manage_options',
            'anyphefo-templates',
            array(self::class, 'render_admin_page'),
            'dashicons-layout',
            65
        );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page()
    {
        include ANYPHEFO_PLUGIN_DIR . 'admin/pages/create-template.php';
    }

    /**
     * Render create template page
     */
    public static function render_create_template()
    {
        wp_safe_redirect(admin_url('admin.php?page=anyphefo-templates'));
        exit;
    }

    /**
     * Render template list page
     */
    public static function render_template_list()
    {
        wp_safe_redirect(admin_url('admin.php?page=anyphefo-templates'));
        exit;
    }

    /**
     * Generate a custom template file for Elementor
     * 
     * @param string $name Template name (e.g., "New Layout 2026")
     * @param int|null $header_id Custom header Elementor ID
     * @param int|null $footer_id Custom footer Elementor ID
     * @return array Generated template data
     */
    public static function generate_elementor_template($name, $header_id = null, $footer_id = null)
    {
        $header_id = absint($header_id);
        $footer_id = absint($footer_id);
        $slug_base = sanitize_title($name);
        $slug = ($slug_base ?: 'custom-template');

        $main_file = 'template-cu-' . $slug . '.php';
        $header_file = 'header-' . $slug . '.php';
        $footer_file = 'footer-' . $slug . '.php';

        return [
            'name' => $name,
            'slug' => $slug,
            'files' => [
                $main_file => self::get_main_template_content($name, $slug),
                $header_file => self::get_header_content($header_id),
                $footer_file => self::get_footer_content($footer_id),
            ],
            'header_id' => $header_id,
            'footer_id' => $footer_id,
        ];
    }

    /**
     * Get template content with Elementor integration
     */
    /**
     * Get main template content
     */
    public static function get_main_template_content($name, $slug)
    {
        return '<?php' . "\n"
            . '/* Template Name: ' . $name . ' */' . "\n\n"
            . 'get_header(\'' . $slug . '\');' . "\n\n"
            . 'while ( have_posts() ) :' . "\n"
            . '    the_post();' . "\n"
            . '    the_content();' . "\n"
            . 'endwhile;' . "\n\n"
            . 'get_footer(\'' . $slug . '\');' . "\n";
    }

    /**
     * Get header file content
     */
    public static function get_header_content($header_id)
    {
        return '<?php' . "\n"
            . '/**' . "\n"
            . ' * Custom Header File' . "\n"
            . ' */' . "\n"
            . '?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html <?php language_attributes(); ?>>' . "\n"
            . '<head>' . "\n"
            . '    <meta charset="<?php bloginfo( \'charset\' ); ?>">' . "\n"
            . '    <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '    <?php wp_head(); ?>' . "\n"
            . '</head>' . "\n"
            . '<body <?php body_class(); ?>>' . "\n"
            . '<?php wp_body_open(); ?>' . "\n\n"
            . '<?php' . "\n"
            . '$anyphefo_has_custom_header = false;' . "\n\n"
            . 'if (' . $header_id . ') {' . "\n"
            . '    $anyphefo_document = class_exists(\'\Elementor\Plugin\') ? \Elementor\Plugin::instance()->documents->get(' . $header_id . ') : null;' . "\n\n"
            . '    if ($anyphefo_document && $anyphefo_document->is_built_with_elementor()) {' . "\n"
            . '        echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(' . $header_id . ');' . "\n"
            . '        $anyphefo_has_custom_header = true;' . "\n"
            . '    } else {' . "\n"
            . '        $header_post = get_post(' . $header_id . ');' . "\n"
            . '        if ($header_post instanceof WP_Post) {' . "\n"
            . '            echo apply_filters(\'the_content\', $header_post->post_content);' . "\n"
            . '            $anyphefo_has_custom_header = true;' . "\n"
            . '        }' . "\n"
            . '    }' . "\n"
            . '}' . "\n\n"
            . 'if (!$anyphefo_has_custom_header) {' . "\n"
            . '    get_header();' . "\n"
            . '}' . "\n"
            . '?>' . "\n";
    }

    /**
     * Get footer file content
     */
    public static function get_footer_content($footer_id)
    {
        return '<?php' . "\n"
            . '/**' . "\n"
            . ' * Custom Footer File' . "\n"
            . ' */' . "\n\n"
            . '$anyphefo_has_custom_footer = false;' . "\n\n"
            . 'if (' . $footer_id . ') {' . "\n"
            . '    $anyphefo_document = class_exists(\'\Elementor\Plugin\') ? \Elementor\Plugin::instance()->documents->get(' . $footer_id . ') : null;' . "\n\n"
            . '    if ($anyphefo_document && $anyphefo_document->is_built_with_elementor()) {' . "\n"
            . '        echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(' . $footer_id . ');' . "\n"
            . '        $anyphefo_has_custom_footer = true;' . "\n"
            . '    } else {' . "\n"
            . '        $footer_post = get_post(' . $footer_id . ');' . "\n"
            . '        if ($footer_post instanceof WP_Post) {' . "\n"
            . '            echo apply_filters(\'the_content\', $footer_post->post_content);' . "\n"
            . '            $anyphefo_has_custom_footer = true;' . "\n"
            . '        }' . "\n"
            . '    }' . "\n"
            . '}' . "\n\n"
            . 'if (!$anyphefo_has_custom_footer) {' . "\n"
            . '    get_footer();' . "\n"
            . '}' . "\n"
            . '?>' . "\n"
            . '<?php wp_footer(); ?>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }
}
