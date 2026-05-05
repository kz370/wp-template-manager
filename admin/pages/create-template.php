<?php

/**
 * Template Manager Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$template_name = '';
$header_id = 0;
$footer_id = 0;
$storage_type = 'database';
$errors = array();
$success_message = '';
$editing_slug = '';
$is_edit_mode = false;

$available_headers = anyphefo_get_available_headers();
$available_footers = anyphefo_get_available_footers();
$existing_templates = anyphefo_get_registered_templates();

$selected_header_label = __('Default', 'anypage-header-footer-for-elementor');
foreach ($available_headers as $header) {
    if ((int) $header['id'] === (int) $header_id) {
        $selected_header_label = $header['title'];
        break;
    }
}

$selected_footer_label = __('Default', 'anypage-header-footer-for-elementor');
foreach ($available_footers as $footer) {
    if ((int) $footer['id'] === (int) $footer_id) {
        $selected_footer_label = $footer['title'];
        break;
    }
}

$templates_by_slug = array();
foreach ($existing_templates as $template) {
    $templates_by_slug[$template['slug']] = $template;
}

$open_modal = false;

if (
    isset($_GET['action'], $_GET['template'])
    && 'edit' === sanitize_text_field(wp_unslash($_GET['action']))
    && current_user_can('manage_options')
) {
    $requested_slug = esc_attr(sanitize_file_name(wp_unslash($_GET['template'])));

    if (isset($templates_by_slug[$requested_slug])) {
        $template = $templates_by_slug[$requested_slug];
        $template_name = $template['name'];
        $header_id = (int) $template['header_id'];
        $footer_id = (int) $template['footer_id'];
        $storage_type = 'database';
        $editing_slug = $template['slug'];
        $is_edit_mode = true;
        $open_modal = true;
    } else {
        $errors[] = esc_html__('Template not found.', 'anypage-header-footer-for-elementor');
    }
}

if (
    isset($_POST['tm_submit_template'])
    && current_user_can('manage_options')
    && check_admin_referer('anyphefo_create_template', 'anyphefo_nonce')
) {
    $template_name = esc_html(sanitize_text_field(wp_unslash($_POST['tm_template_name'] ?? '')));
    $header_id = esc_attr(absint(wp_unslash($_POST['tm_header_id'] ?? 0)));
    $footer_id = esc_attr(absint(wp_unslash($_POST['tm_footer_id'] ?? 0)));
    $storage_type = 'database';
    $editing_slug = esc_attr(sanitize_file_name(wp_unslash($_POST['tm_editing_slug'] ?? '')));
    $is_edit_mode = '' !== $editing_slug;

    if ('' === $template_name) {
        $errors[] = esc_html__('Template name is required.', 'anypage-header-footer-for-elementor');
    }

    $existing_template = null;
    if ($is_edit_mode) {
        if (!isset($templates_by_slug[$editing_slug])) {
            $errors[] = esc_html__('The selected template no longer exists.', 'anypage-header-footer-for-elementor');
        } else {
            $existing_template = $templates_by_slug[$editing_slug];
            if ('database' !== $existing_template['source']) {
                $errors[] = esc_html__('Only database templates can be edited.', 'anypage-header-footer-for-elementor');
            }
        }
    }

    $new_slug = 'template-cu-' . sanitize_title($template_name) . '.php';
    if ('template-cu-.php' === $new_slug) {
        $new_slug = 'template-cu-custom-template.php';
    }

    if (empty($errors) && !$is_edit_mode && isset($templates_by_slug[$new_slug])) {
        $errors[] = esc_html__('A template with this generated file name already exists.', 'anypage-header-footer-for-elementor');
    }

    if (empty($errors) && $is_edit_mode && $editing_slug !== $new_slug && isset($templates_by_slug[$new_slug])) {
        $errors[] = esc_html__('Another template already uses this generated file name.', 'anypage-header-footer-for-elementor');
    }

    if (empty($errors)) {
        $db_templates = anyphefo_get_database_templates();

        if ($is_edit_mode && $existing_template) {
            unset($db_templates[$editing_slug]);
        }

        $db_templates[$new_slug] = array(
            'name' => $template_name,
            'header_id' => $header_id,
            'footer_id' => $footer_id,
        );

        anyphefo_update_database_templates($db_templates);
        $success_message = $is_edit_mode
            ? esc_html__('Template updated.', 'anypage-header-footer-for-elementor')
            : esc_html__('Template saved.', 'anypage-header-footer-for-elementor');
    }

    if (empty($errors)) {
        $template_name = '';
        $header_id = 0;
        $footer_id = 0;
        $storage_type = 'database';
        $editing_slug = '';
        $is_edit_mode = false;
        $open_modal = false;
        $existing_templates = anyphefo_get_registered_templates();
        $templates_by_slug = array();
        foreach ($existing_templates as $template) {
            $templates_by_slug[$template['slug']] = $template;
        }
    } else {
        $open_modal = true;
    }
}

if (
    isset($_GET['action'], $_GET['template'], $_GET['_wpnonce'])
    && 'delete' === $_GET['action']
    && current_user_can('manage_options')
) {
    $template_slug = sanitize_file_name(wp_unslash($_GET['template']));

    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'anyphefo_delete_template_' . $template_slug)) {
        $db_templates = anyphefo_get_database_templates();

        if (isset($db_templates[$template_slug])) {
            unset($db_templates[$template_slug]);
            anyphefo_update_database_templates($db_templates);
            /* translators: %s: Template name */
            $success_message = esc_html__('Template removed.', 'anypage-header-footer-for-elementor');
        } else {
            $errors[] = esc_html__('Only database templates can be deleted from this screen.', 'anypage-header-footer-for-elementor');
        }

        $existing_templates = anyphefo_get_registered_templates();
        $templates_by_slug = array();
        foreach ($existing_templates as $template) {
            $templates_by_slug[$template['slug']] = $template;
        }
    }
}

$template_usage_counts = array();
$template_usage_map = array();
if (!empty($existing_templates)) {
    global $wpdb;

    $template_slugs = array_map(
        static function ($item) {
            return (string) $item['slug'];
        },
        $existing_templates
    );

    $post_types = get_post_types(array('public' => true), 'names');
    if (empty($post_types)) {
        $post_types = array('page', 'post');
    }


    $slug_placeholders = implode(', ', array_fill(0, count($template_slugs), '%s'));
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
    $sql = "
            SELECT pm.meta_value AS template_slug, p.ID, p.post_title, p.post_type, p.post_status
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_wp_page_template'
              AND pm.meta_value IN ($slug_placeholders)
              AND p.post_type IN ($type_placeholders)
              AND p.post_status NOT IN ('trash', 'auto-draft')
            ORDER BY p.post_type ASC, p.post_title ASC
        ";
    $args = array_merge($template_slugs, $post_types);

    $cache_key = 'anyphefo_usage_' . md5(implode(',', $args));
    $usage_rows = wp_cache_get($cache_key, 'anyphefo_templates');
    if (false === $usage_rows) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
        $usage_rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        wp_cache_set($cache_key, $usage_rows, 'anyphefo_templates', 300);
    }

    foreach ($template_slugs as $slug) {
        $template_usage_counts[$slug] = 0;
        $template_usage_map[$slug] = array();
    }

    foreach ($usage_rows as $row) {
        $slug = (string) $row['template_slug'];
        $post_id = (int) $row['ID'];
        $post_type = (string) $row['post_type'];
        $post_type_obj = get_post_type_object($post_type);
        $type_label = $post_type_obj ? $post_type_obj->labels->singular_name : ucfirst($post_type);

        $title = trim((string) $row['post_title']);
        if ('' === $title) {

            $title = sprintf(
                /* translators: %d: Post ID */
                esc_html__('(No title) #%d', 'anypage-header-footer-for-elementor'),
                $post_id
            );
        }

        $template_usage_counts[$slug] = isset($template_usage_counts[$slug])
            ? $template_usage_counts[$slug] + 1
            : 1;

        $template_usage_map[$slug][] = array(
            'id' => $post_id,
            'title' => $title,
            'post_type' => $post_type,
            'type' => $type_label,
            'status' => (string) $row['post_status'],
            'edit_link' => get_edit_post_link($post_id, 'raw') ?: '',
        );
    }
}

$per_page = 8;
$current_page = isset($_GET['anyphefo_page']) ? max(1, absint($_GET['anyphefo_page'])) : 1;
$total_templates = count($existing_templates);
$total_pages = max(1, (int) ceil($total_templates / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $per_page;
$paged_templates = array_slice($existing_templates, $offset, $per_page);

$pagination_base = remove_query_arg(array('anyphefo_page', 'action', 'template', '_wpnonce'));
$existing_template_slugs = array_map(
    static function ($item) {
        return (string) $item['slug'];
    },
    $existing_templates
);

wp_add_inline_script(
    'anyphefo-admin-script',
    'window.anyphefoAdminConfig = ' . wp_json_encode(
        array(
            'usageMap' => $template_usage_map,
            'strings'  => array(
                'duplicateTemplateName' => __('Template name already exists.', 'anypage-header-footer-for-elementor'),
                'addTemplate'           => __('Add Template', 'anypage-header-footer-for-elementor'),
                'createTemplate'        => __('Create Template', 'anypage-header-footer-for-elementor'),
                'editTemplate'          => __('Edit Template', 'anypage-header-footer-for-elementor'),
                'saveChanges'           => __('Save Changes', 'anypage-header-footer-for-elementor'),
                'defaultStatusLabel'    => __('Publish', 'anypage-header-footer-for-elementor'),
                'noContentInFilter'     => __('No content in this filter.', 'anypage-header-footer-for-elementor'),
                'edit'                  => __('Edit', 'anypage-header-footer-for-elementor'),
                'all'                   => __('All', 'anypage-header-footer-for-elementor'),
                'postLabel'             => __('Post', 'anypage-header-footer-for-elementor'),
            ),
        )
    ) . ';',
    'before'
);

?>

<div class="wrap tm-settings-page">
    <div class="tm-shell">
        <div class="tm-header-bar">
            <div>
                <h1><?php esc_html_e('Template Settings', 'anypage-header-footer-for-elementor'); ?></h1>
                <p><?php esc_html_e('Create and manage templates from one fast page.', 'anypage-header-footer-for-elementor'); ?></p>
            </div>

            <button type="button" class="tm-primary-btn" data-tm-open-modal>
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e('Add Template', 'anypage-header-footer-for-elementor'); ?>
            </button>
        </div>

        <?php if ($success_message) : ?>
            <div class="tm-alert success"><?php echo esc_html($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)) : ?>
            <div class="tm-alert error">
                <?php foreach ($errors as $error) : ?>
                    <p><?php echo esc_html($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="tm-table-card">
            <div class="tm-table-head">
                <h2><?php esc_html_e('Templates', 'anypage-header-footer-for-elementor'); ?></h2>
                <span class="tm-total-chip">
                    <?php
                    printf(
                        /* translators: %d: Total templates */
                        esc_html__('Total: %d', 'anypage-header-footer-for-elementor'),
                        esc_html($total_templates)
                    );
                    ?>
                </span>
            </div>

            <?php if (empty($paged_templates)) : ?>
                <div class="tm-empty-state">
                    <span class="dashicons dashicons-layout"></span>
                    <p><?php esc_html_e('No templates created yet.', 'anypage-header-footer-for-elementor'); ?></p>
                </div>
            <?php else : ?>
                <div class="tm-table-wrap">
                    <table class="widefat fixed striped tm-template-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Name', 'anypage-header-footer-for-elementor'); ?></th>
                                <th scope="col"><?php esc_html_e('Template Name', 'anypage-header-footer-for-elementor'); ?></th>
                                <th scope="col"><?php esc_html_e('Type', 'anypage-header-footer-for-elementor'); ?></th>
                                <th scope="col"><?php esc_html_e('Used By', 'anypage-header-footer-for-elementor'); ?></th>
                                <th scope="col" class="tm-actions-col"><?php esc_html_e('Actions', 'anypage-header-footer-for-elementor'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paged_templates as $template) : ?>
                                <?php
                                $template_type = ('database' === $template['source'])
                                    ? esc_html__('Virtual (DB)', 'anypage-header-footer-for-elementor')
                                    : esc_html__('Legacy File (Read-only)', 'anypage-header-footer-for-elementor');
                                $can_manage = !empty($template['has_db_copy']);
                                $usage_count = isset($template_usage_counts[$template['slug']])
                                    ? (int) $template_usage_counts[$template['slug']]
                                    : 0;
                                $usage_label = sprintf(
                                    /* translators: %d: Number of pages using the template */
                                    esc_html(_n('%d page', '%d pages', $usage_count, 'anypage-header-footer-for-elementor')),
                                    esc_html($usage_count)
                                );
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($template['name']); ?></strong></td>
                                    <td><code><?php echo esc_html($template['slug']); ?></code></td>
                                    <td>
                                        <span class="tm-type-pill <?php echo esc_attr('database' === $template['source'] ? 'database' : 'file'); ?>">
                                            <?php echo esc_html($template_type); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($usage_count > 0) : ?>
                                            <button
                                                type="button"
                                                class="tm-usage-pill tm-usage-link"
                                                data-tm-usage-open="1"
                                                data-template-slug="<?php echo esc_attr($template['slug']); ?>"
                                                data-template-name="<?php echo esc_attr($template['name']); ?>"
                                                title="<?php esc_attr_e('View pages using this template', 'anypage-header-footer-for-elementor'); ?>">
                                                <?php echo esc_html($usage_label); ?>
                                            </button>
                                        <?php else : ?>
                                            <span class="tm-usage-pill"><?php echo esc_html($usage_label); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tm-actions-cell">
                                        <?php if ($can_manage) : ?>
                                            <button
                                                type="button"
                                                class="tm-icon-btn"
                                                data-tm-open-modal
                                                data-editing="1"
                                                data-template-slug="<?php echo esc_attr($template['slug']); ?>"
                                                data-template-name="<?php echo esc_attr($template['name']); ?>"
                                                data-header-id="<?php echo esc_attr((string) $template['header_id']); ?>"
                                                data-footer-id="<?php echo esc_attr((string) $template['footer_id']); ?>"
                                                data-storage="database"
                                                aria-label="<?php echo esc_attr(sprintf(/* translators: %s: Template name */__('Edit %s', 'anypage-header-footer-for-elementor'), esc_html($template['name']))); ?>"
                                                title="<?php echo esc_attr__('Edit template', 'anypage-header-footer-for-elementor'); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <a
                                                class="tm-icon-btn danger"
                                                href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=anyphefo-templates&action=delete&template=' . rawurlencode($template['slug'])), 'anyphefo_delete_template_' . $template['slug'])); ?>"
                                                aria-label="<?php echo esc_attr(sprintf(/* translators: %s: Template name */__('Delete %s', 'anypage-header-footer-for-elementor'), esc_html($template['name']))); ?>"
                                                title="<?php echo esc_attr__('Delete template', 'anypage-header-footer-for-elementor'); ?>"
                                                data-confirm-message="<?php echo esc_attr__('Delete this template?', 'anypage-header-footer-for-elementor'); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </a>
                                        <?php else : ?>
                                            <span class="tm-readonly-pill"><?php esc_html_e('Plugin', 'anypage-header-footer-for-elementor'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1) : ?>
                    <div class="tm-pagination-wrap">
                        <?php
                        echo wp_kses_post(
                            paginate_links(array(
                                'base' => add_query_arg('anyphefo_page', '%#%', $pagination_base),
                                'format' => '',
                                'current' => $current_page,
                                'total' => $total_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'type' => 'list',
                            ))
                        );
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="tm-modal" data-state="<?php echo $open_modal ? 'open' : 'closed'; ?>" aria-hidden="<?php echo $open_modal ? 'false' : 'true'; ?>">
        <div class="tm-modal-backdrop" data-tm-close-modal></div>

        <div class="tm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tm-modal-title">
            <div class="tm-modal-header">
                <h2 id="tm-modal-title"><?php echo esc_html($is_edit_mode ? __('Edit Template', 'anypage-header-footer-for-elementor') : __('Add Template', 'anypage-header-footer-for-elementor')); ?></h2>
                <button type="button" class="tm-close-btn" data-tm-close-modal aria-label="<?php esc_attr_e('Close', 'anypage-header-footer-for-elementor'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>

            <form method="post" class="tm-modal-form" id="tm-template-form" data-existing-slugs="<?php echo esc_attr(wp_json_encode($existing_template_slugs)); ?>">
                <?php wp_nonce_field('anyphefo_create_template', 'anyphefo_nonce'); ?>
                <input type="hidden" name="tm_editing_slug" id="tm_editing_slug" value="<?php echo esc_attr($editing_slug); ?>">

                <div class="tm-field-grid">
                    <label class="tm-field tm-field--full">
                        <span><?php esc_html_e('Name', 'anypage-header-footer-for-elementor'); ?></span>
                        <input id="tm_template_name" type="text" name="tm_template_name" value="<?php echo esc_attr($template_name); ?>" placeholder="<?php echo esc_attr__('Landing Page', 'anypage-header-footer-for-elementor'); ?>">
                        <small id="tm_template_name_error" class="tm-field-error" aria-live="polite"></small>
                    </label>

                    <label class="tm-field">
                        <span><?php esc_html_e('Header', 'anypage-header-footer-for-elementor'); ?></span>
                        <div class="tm-combobox" data-tm-combobox="header">
                            <input type="hidden" id="tm_header_id" name="tm_header_id" value="<?php echo esc_attr((string) $header_id); ?>">
                            <input
                                id="tm_header_display"
                                class="tm-combobox-input"
                                type="text"
                                autocomplete="off"
                                placeholder="<?php echo esc_attr__('Search header', 'anypage-header-footer-for-elementor'); ?>"
                                value="<?php echo esc_attr($header_id ? $selected_header_label : ''); ?>">
                            <div class="tm-combobox-menu" id="tm_header_menu">
                                <button type="button" class="tm-combobox-option" data-id="0" data-title="<?php echo esc_attr__('Default', 'anypage-header-footer-for-elementor'); ?>"><?php esc_html_e('Default', 'anypage-header-footer-for-elementor'); ?></button>
                                <?php foreach ($available_headers as $header) : ?>
                                    <button
                                        type="button"
                                        class="tm-combobox-option"
                                        data-id="<?php echo esc_attr((string) $header['id']); ?>"
                                        data-title="<?php echo esc_attr($header['title']); ?>">
                                        <?php echo esc_html($header['title']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </label>

                    <label class="tm-field">
                        <span><?php esc_html_e('Footer', 'anypage-header-footer-for-elementor'); ?></span>
                        <div class="tm-combobox" data-tm-combobox="footer">
                            <input type="hidden" id="tm_footer_id" name="tm_footer_id" value="<?php echo esc_attr((string) $footer_id); ?>">
                            <input
                                id="tm_footer_display"
                                class="tm-combobox-input"
                                type="text"
                                autocomplete="off"
                                placeholder="<?php echo esc_attr__('Search footer', 'anypage-header-footer-for-elementor'); ?>"
                                value="<?php echo esc_attr($footer_id ? $selected_footer_label : ''); ?>">
                            <div class="tm-combobox-menu" id="tm_footer_menu">
                                <button type="button" class="tm-combobox-option" data-id="0" data-title="<?php echo esc_attr__('Default', 'anypage-header-footer-for-elementor'); ?>"><?php esc_html_e('Default', 'anypage-header-footer-for-elementor'); ?></button>
                                <?php foreach ($available_footers as $footer) : ?>
                                    <button
                                        type="button"
                                        class="tm-combobox-option"
                                        data-id="<?php echo esc_attr((string) $footer['id']); ?>"
                                        data-title="<?php echo esc_attr($footer['title']); ?>">
                                        <?php echo esc_html($footer['title']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </label>

                    <div class="tm-field tm-storage-field">
                        <span><?php esc_html_e('Template Type', 'anypage-header-footer-for-elementor'); ?></span>
                        <p class="tm-help-note"><?php esc_html_e('Templates are stored in the database only. No PHP template files are written to your theme or plugin directories.', 'anypage-header-footer-for-elementor'); ?></p>
                        <input type="hidden" name="tm_storage_type" id="tm_storage_type_input" value="<?php echo esc_attr($storage_type); ?>">
                    </div>
                </div>

                <div class="tm-modal-actions">
                    <button type="button" class="tm-secondary-btn" data-tm-close-modal><?php esc_html_e('Cancel', 'anypage-header-footer-for-elementor'); ?></button>
                    <button type="submit" name="tm_submit_template" class="tm-primary-btn" id="tm_submit_btn">
                        <span class="dashicons dashicons-saved"></span>
                        <span id="tm_submit_label"><?php echo esc_html($is_edit_mode ? __('Save Changes', 'anypage-header-footer-for-elementor') : __('Create Template', 'anypage-header-footer-for-elementor')); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="tm-usage-modal" data-state="closed" aria-hidden="true">
        <div class="tm-usage-modal-backdrop" data-tm-usage-close></div>
        <div class="tm-usage-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tm-usage-title">
            <div class="tm-usage-modal-header">
                <h3 id="tm-usage-title"><?php esc_html_e('Template Usage', 'anypage-header-footer-for-elementor'); ?></h3>
                <button type="button" class="tm-close-btn" data-tm-usage-close aria-label="<?php esc_attr_e('Close', 'anypage-header-footer-for-elementor'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="tm-usage-modal-body">
                <p id="tm-usage-subtitle" class="tm-usage-subtitle"></p>
                <div id="tm-usage-filters" class="tm-usage-filters"></div>
                <div id="tm-usage-empty" class="tm-usage-empty"><?php esc_html_e('No content uses this template.', 'anypage-header-footer-for-elementor'); ?></div>
                <ul id="tm-usage-list" class="tm-usage-list"></ul>
            </div>
        </div>
    </div>
</div>

