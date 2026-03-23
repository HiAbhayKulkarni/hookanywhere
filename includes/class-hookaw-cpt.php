<?php
/**
 * Custom Post Type logic for the HookAnywhere plugin.
 *
 * @package HookAnywhere
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CPT Class
 */
class HOOKAW_CPT
{
    /**
     * The post type name.
     */
    private $post_type = 'hookaw';

    /**
     * Init function.
     */
    public function init()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_log_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('add_meta_boxes', [$this, 'rename_publish_meta_box'], 100); // Late priority to rename core boxes
        add_filter('post_updated_messages', [$this, 'hookaw_post_updated_messages']);
        add_filter('post_row_actions', [$this, 'remove_quick_edit_action'], 10, 2);
        add_filter('post_row_actions', [$this, 'modify_log_row_actions'], 10, 2);

        // Remove Date Filter and define custom Bulk Actions
        add_filter('bulk_actions-edit-hookaw', [$this, 'custom_hookaw_bulk_actions']);
        add_filter('bulk_actions-edit-hookaw_log', [$this, 'custom_hookaw_log_bulk_actions']);
        add_filter('disable_months_dropdown', [$this, 'disable_months_dropdown_for_hookaw'], 10, 2);
        // Add Taxonomy Filter Dropdown
        add_action('restrict_manage_posts', [$this, 'add_taxonomy_filters']);

        // Add Log Filter Dropdown
        add_action('restrict_manage_posts', [$this, 'add_log_filters']);
        add_action('pre_get_posts', [$this, 'filter_logs_by_webhook']);

        add_filter('screen_options_show_screen', [$this, 'disable_screen_options'], 10, 2);

        add_action('admin_head', [$this, 'custom_admin_css']);
        add_action('save_post', [$this, 'save_meta_boxes']);

        // Display read-only log title
        add_action('edit_form_top', [$this, 'display_log_title_readonly']);

        // Add toggle to the native publish box
        add_action('post_submitbox_misc_actions', [$this, 'add_submitbox_toggle']);
        // Custom list table columns
        add_filter('manage_hookaw_posts_columns', [$this, 'set_custom_edit_hookaw_columns']);
        add_action('manage_hookaw_posts_custom_column', [$this, 'custom_hookaw_column'], 10, 2);

        // Dynamic log title override for edit screens
        add_action('admin_init', [$this, 'dynamic_log_labels']);

        // Force meta box order
        add_filter('get_user_option_meta-box-order_hookaw', [$this, 'force_hookaw_meta_box_order']);
        add_filter('get_user_option_meta-box-order_hookaw_log', [$this, 'force_hookaw_log_meta_box_order']);

        // Log custom list table columns
        add_filter('manage_hookaw_log_posts_columns', [$this, 'set_custom_edit_hookaw_log_columns']);
        add_action('manage_hookaw_log_posts_custom_column', [$this, 'custom_hookaw_log_column'], 10, 2);

        // AJAX handler for list toggle
        add_action('wp_ajax_hookaw_toggle_status', [$this, 'ajax_toggle_status']);
        // Inline JS for list table toggle
        add_action('admin_footer-edit.php', [$this, 'list_table_toggle_js']);

        // Force 'publish' status when untrashing webhooks and logs
        add_filter('wp_untrash_post_status', [$this, 'force_publish_on_restore'], 10, 3);
    }

    /**
     * Force posts to explicitly restore as 'publish' instead of 'draft' 
     * since the UI drops the default publish meta box features.
     *
     * @param string $new_status      The new status of the post being restored.
     * @param int    $post_id         The ID of the post being restored.
     * @param string $previous_status The status of the post at the point where it was trashed.
     * @return string
     */
    public function force_publish_on_restore($new_status, $post_id, $previous_status)
    {
        $post = get_post($post_id);
        if ($post && in_array($post->post_type, ['hookaw', 'hookaw_log'])) {
            return 'publish';
        }
        return $new_status;
    }

    /**
     * Register the custom post type.
     */
    public function register_post_type()
    {
        $labels = [
            'name' => _x('All Webhooks', 'Post type general name', 'hookanywhere'),
            'singular_name' => _x('Webhook', 'Post type singular name', 'hookanywhere'),
            'menu_name' => _x('HookAnywhere', 'Admin Menu text', 'hookanywhere'),
            'name_admin_bar' => _x('Webhook', 'Add New on Toolbar', 'hookanywhere'),
            'add_new' => __('Add New', 'hookanywhere'),
            'add_new_item' => __('Add New Webhook', 'hookanywhere'),
            'new_item' => __('New Webhook', 'hookanywhere'),
            'edit_item' => __('Edit Webhook', 'hookanywhere'),
            'view_item' => __('View Webhook', 'hookanywhere'),
            'all_items' => __('All Webhooks', 'hookanywhere'),
            'search_items' => __('Search Webhooks', 'hookanywhere'),
            'parent_item_colon' => __('Parent Webhooks:', 'hookanywhere'),
            'not_found' => __('No webhooks found.', 'hookanywhere'),
            'not_found_in_trash' => __('No webhooks found in Trash.', 'hookanywhere'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true, // Act as a top level menu natively
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'exclude_from_search' => true, // Don't show in frontend search
            'show_in_nav_menus' => false, // Don't allow adding to menus
            'menu_position' => 80,
            'menu_icon' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB2aWV3Qm94PSIwLDAsMjU2LDI1NiIgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgZmlsbC1ydWxlPSJub256ZXJvIj48ZyBmaWxsPSIjMDAwMDAwIiBmaWxsLXJ1bGU9Im5vbnplcm8iIHN0cm9rZT0ibm9uZSIgc3Ryb2tlLXdpZHRoPSIxIiBzdHJva2UtbGluZWNhcD0iYnV0dCIgc3Ryb2tlLWxpbmVqb2luPSJtaXRlciIgc3Ryb2tlLW1pdGVybGltaXQ9IjEwIiBzdHJva2UtZGFzaGFycmF5PSIiIHN0cm9rZS1kYXNob2Zmc2V0PSIwIiBmb250LWZhbWlseT0ibm9uZSIgZm9udC13ZWlnaHQ9Im5vbmUiIGZvbnQtc2l6ZT0ibm9uZSIgdGV4dC1hbmNob3I9Im5vbmUiIHN0eWxlPSJtaXgtYmxlbmQtbW9kZTogbm9ybWFsIj48ZyB0cmFuc2Zvcm09InNjYWxlKDEwLjY2NjY3LDEwLjY2NjY3KSI+PHBhdGggZD0iTTExLjY1NjI1LDIuMzQzNzVjLTEuNjAxNTYsMC4xMTMyOCAtMy4xNDA2MiwxLjAwNzgxIC00LDIuNWMtMS4yMDcwMywyLjA4OTg0IC0wLjcxMDk0LDQuNzEwOTQgMS4wMzEyNSw2LjI1bC0xLjY4NzUsMi45MDYyNWMtMC42ODc1LDAuMDAzOTEgLTEuMzUxNTYsMC4zNTkzOCAtMS43MTg3NSwxYy0wLjU1MDc4LDAuOTU3MDMgLTAuMjM4MjgsMi4xNjQwNiAwLjcxODc1LDIuNzE4NzVjMC45NTcwMywwLjU1MDc4IDIuMTY3OTcsMC4yMzgyOCAyLjcxODc1LC0wLjcxODc1YzAuMzY3MTksLTAuNjQwNjIgMC4zNDM3NSwtMS40MDIzNCAwLC0ybDIuNjU2MjUsLTQuNTYyNWwtMC44NzUsLTAuNWMtMS40MzM1OSwtMC44MjgxMiAtMS45MjE4NywtMi42NjAxNiAtMS4wOTM3NSwtNC4wOTM3NWMwLjgyODEzLC0xLjQzMzU5IDIuNjYwMTYsLTEuOTIxODcgNC4wOTM3NSwtMS4wOTM3NWMxLjQzMzU5LDAuODI4MTMgMS45MjE4OCwyLjY2MDE2IDEuMDkzNzUsNC4wOTM3NWwxLjc1LDFjMS4zNzg5MSwtMi4zODY3MiAwLjU0Mjk3LC01LjQ2NDg0IC0xLjg0Mzc1LC02Ljg0Mzc1Yy0wLjg5NDUzLC0wLjUxNTYyIC0xLjg4MjgxLC0wLjcyMjY2IC0yLjg0Mzc1LC0wLjY1NjI1ek0xMS43NSw1LjM0Mzc1Yy0wLjI1NzgxLDAuMDMxMjUgLTAuNTExNzIsMC4xMTMyOCAtMC43NSwwLjI1Yy0wLjk1NzAzLDAuNTUwNzggLTEuMjY5NTMsMS43OTI5NyAtMC43MTg3NSwyLjc1YzAuMzY3MTksMC42NDA2MyAxLjAzMTI1LDAuOTk2MDkgMS43MTg3NSwxbDIuNjI1LDQuNTYyNWwwLjg3NSwtMC41YzEuNDMzNTksLTAuODI4MTIgMy4yNjU2MywtMC4zMzk4NCA0LjA5Mzc1LDEuMDkzNzVjMC44MjgxMywxLjQzMzU5IDAuMzM5ODQsMy4yNjU2MyAtMS4wOTM3NSw0LjA5Mzc1Yy0xLjQzMzU5LDAuODI4MTMgLTMuMjY1NjIsMC4zMzk4NCAtNC4wOTM3NSwtMS4wOTM3NWwtMS43NSwxYzEuMzc4OTEsMi4zODY3MiA0LjQ1NzAzLDMuMjIyNjYgNi44NDM3NSwxLjg0Mzc1YzIuMzg2NzIsLTEuMzc4OTEgMy4yMjI2NiwtNC40NTcwMyAxLjg0Mzc1LC02Ljg0Mzc1Yy0xLjIwNzAzLC0yLjA4OTg0IC0zLjczMDQ3LC0yLjk4ODI4IC01LjkzNzUsLTIuMjVsLTEuNjg3NSwtMi45MDYyNWMwLjM0Mzc1LC0wLjU5NzY2IDAuMzY3MTksLTEuMzU5MzcgMCwtMmMtMC40MTQwNiwtMC43MTg3NSAtMS4xOTUzMSwtMS4wOTc2NiAtMS45Njg3NSwtMXpNNywxMWMtMi43NTc4MSwwIC01LDIuMjQyMTkgLTUsNWMwLDIuNzU3ODEgMi4yNDIxOSw1IDUsNWMyLjQxNDA2LDAgNC40NDE0MSwtMS43MjI2NiA0LjkwNjI1LC00aDMuMzc1YzAuMzQ3NjYsMC41OTM3NSAwLjk4MDQ3LDEgMS43MTg3NSwxYzEuMTA1NDcsMCAyLC0wLjg5NDUzIDIsLTJjMCwtMS4xMDU0NyAtMC44OTQ1MywtMiAtMiwtMmMtMC43MzgyOCwwIC0xLjM3MTA5LDAuNDA2MjUgLTEuNzE4NzUsMWgtNS4yODEyNXYxYzAsMS42NTIzNCAtMS4zNDc2NiwzIC0zLDNjLTEuNjUyMzQsMCAtMywtMS4zNDc2NiAtMywtM3MwLC0xLjY1MjM0IDEuMzQ3NjYsLTMgMywtM3oiPjwvcGF0aD48L2c+PC9nPjwvc3ZnPg==',
            'supports' => ['title'], // Only need title for the name
        ];

        register_post_type($this->post_type, $args);
    }

    /**
     * Register the logs custom post type.
     */
    public function register_log_post_type()
    {
        $labels = [
            'name' => _x('Logs', 'Post type general name', 'hookanywhere'),
            'singular_name' => _x('Log', 'Post type singular name', 'hookanywhere'),
            'edit_item' => __('Log Details', 'hookanywhere'),
            'view_item' => __('View Log', 'hookanywhere'),
            'search_items' => __('Search Logs', 'hookanywhere'),
            'not_found' => __('No logs found.', 'hookanywhere'),
            'not_found_in_trash' => __('No logs found in Trash.', 'hookanywhere'),
        ];


        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=hookaw', // Unused if it's top-level, but here it shouldn't be top level. Wait, the main menu is hookaw.
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'do_not_allow', // Prevents users from manually creating logs
            ],
            'map_meta_cap' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'show_in_nav_menus' => false,
            'supports' => false, // No title, no editor, strictly read-only
        ];

        register_post_type('hookaw_log', $args);
    }

    /**
     * Dynamically override the hookaw_log labels based on the current post being edited.
     */
    public function dynamic_log_labels()
    {
        global $pagenow;

        if ('post.php' === $pagenow && isset($_GET['post'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_id = intval($_GET['post']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post = get_post($post_id);

            if ($post && 'hookaw_log' === $post->post_type) {
                global $wp_post_types;
                if (isset($wp_post_types['hookaw_log'])) {
                    $wp_post_types['hookaw_log']->labels->edit_item = esc_html($post->post_title);
                }
            }
        }
    }

    /**
     * Register the custom taxonomy for webhooks tags.
     */
    public function register_taxonomy()
    {
        $labels = [
            'name' => _x('Tags', 'taxonomy general name', 'hookanywhere'),
            'singular_name' => _x('Tag', 'taxonomy singular name', 'hookanywhere'),
            'search_items' => __('Search Tags', 'hookanywhere'),
            'all_items' => __('All Tags', 'hookanywhere'),
            'edit_item' => __('Edit Tag', 'hookanywhere'),
            'update_item' => __('Update Tag', 'hookanywhere'),
            'add_new_item' => __('Add New Tag', 'hookanywhere'),
            'new_item_name' => __('New Tag Name', 'hookanywhere'),
            'menu_name' => __('Tags', 'hookanywhere'),
        ];

        $args = [
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => false,
            'show_in_menu' => false,
            'query_var' => true,
            'rewrite' => ['slug' => 'hookaw-tag'],
        ];

        register_taxonomy('hookaw_tag', ['hookaw'], $args);
    }

    /**
     * Set custom bulk actions for the HookAnywhere list table.
     * Only allow 'Move to Trash'.
     */
    public function custom_hookaw_bulk_actions($bulk_actions)
    {
        return ['trash' => __('Move to Trash', 'hookanywhere')];
    }

    /**
     * Add the custom taxonomy filter dropdown to the list table.
     */
    public function add_taxonomy_filters($post_type)
    {
        if ($post_type !== 'hookaw') {
            return;
        }

        $taxonomy_slug = 'hookaw_tag';

        // Only display the filter if there are actually tags assigned to webhooks
        $terms = get_terms([
            'taxonomy' => $taxonomy_slug,
            'hide_empty' => true,
            'fields' => 'count',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return; // No used tags, don't show the filter
        }

        $taxonomy_obj = get_taxonomy($taxonomy_slug);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected = isset($_GET[$taxonomy_slug]) ? sanitize_text_field(wp_unslash($_GET[$taxonomy_slug])) : '';

        wp_dropdown_categories([
            'show_option_all' => __('All ', 'hookanywhere') . $taxonomy_obj->labels->name,
            'taxonomy' => $taxonomy_slug,
            'name' => $taxonomy_slug,
            'orderby' => 'name',
            'selected' => $selected,
            'show_count' => false,
            'hide_empty' => true,
            'value_field' => 'slug',
        ]);
    }

    /**
     * Add meta boxes for the CPT.
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'hookaw_configuration_meta_box',
            'Configuration',
            [$this, 'render_configuration_meta_box'],
            $this->post_type,
            'normal',
            'high'
        );

        add_meta_box(
            'hookaw_log_details_meta_box',
            'Log',
            [$this, 'render_log_details_meta_box'],
            'hookaw_log',
            'normal',
            'high'
        );

        // Remove the default 'Publish' box entirely and replace it with a read-only one
        remove_meta_box('submitdiv', 'hookaw_log', 'side');

        // Remove the 'Slug' box from both screens
        remove_meta_box('slugdiv', 'hookaw', 'normal');
        remove_meta_box('slugdiv', 'hookaw_log', 'normal');

        add_meta_box(
            'hookaw_log_actions_meta_box',
            'Details',
            [$this, 'render_log_actions_meta_box'],
            'hookaw_log',
            'side',
            'high'
        );
    }

    /**
     * Force the meta box order for Webhooks to prevent user layout issues.
     */
    public function force_hookaw_meta_box_order()
    {
        return [
            'normal' => 'hookaw_configuration_meta_box',
            'side' => 'submitdiv',
            'advanced' => '',
        ];
    }

    /**
     * Force the meta box order for Logs to prevent user layout issues.
     */
    public function force_hookaw_log_meta_box_order()
    {
        return [
            'normal' => 'hookaw_log_details_meta_box',
            'side' => 'hookaw_log_actions_meta_box',
            'advanced' => '',
        ];
    }

    /**
     * Rename the default "Publish" meta box to "Details"
     */
    public function rename_publish_meta_box()
    {
        global $wp_meta_boxes;
        if (isset($wp_meta_boxes[$this->post_type]['side']['core']['submitdiv'])) {
            $wp_meta_boxes[$this->post_type]['side']['core']['submitdiv']['title'] = __('Details', 'hookanywhere');
        }
    }

    /**
     * Remove the "Quick Edit" (inline edit) option from the row actions
     */
    public function remove_quick_edit_action($actions, $post)
    {
        if ($post->post_type === 'hookaw') {
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }

    /**
     * Remove unnecessary row actions from the hookaw_log list table.
     */
    public function modify_log_row_actions($actions, $post)
    {
        if ($post->post_type === 'hookaw_log') {
            unset($actions['inline hide-if-no-js']); // Quick Edit
            unset($actions['view']); // View
            unset($actions['edit']); // Edit (we will make title point to edit automatically)
        }
        return $actions;
    }

    /**
     * Filter post updated messages for the HookAnywhere Custom Post Type.
     */
    public function hookaw_post_updated_messages($messages)
    {
        global $post;

        $messages['hookaw'] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => __('Webhook updated.', 'hookanywhere'),
            2 => __('Custom field updated.', 'hookanywhere'), // Should never happen
            3 => __('Custom field deleted.', 'hookanywhere'), // Should never happen
            4 => __('Webhook updated.', 'hookanywhere'),
            5 => isset($_GET['revision']) ? sprintf( // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                /* translators: %s: revision title */
                __('Webhook restored to revision from %s.', 'hookanywhere'),
                wp_post_revision_title((int) $_GET['revision'], false) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            ) : false,
            6 => __('Webhook published.', 'hookanywhere'),
            7 => __('Webhook saved.', 'hookanywhere'),
            8 => __('Webhook submitted.', 'hookanywhere'),
            9 => sprintf(
                /* translators: %s: scheduled date */
                __('Webhook scheduled for: <strong>%1$s</strong>.', 'hookanywhere'),
                // translators: Publish box date format, see http://php.net/date
                date_i18n(__('M j, Y @ G:i', 'hookanywhere'), strtotime($post->post_date))
            ),
            10 => __('Webhook draft updated.', 'hookanywhere'),
        );

        return $messages;
    }

    /**
     * Disable the screen options tab for all HookAnywhere CPT screens.
     */
    public function disable_screen_options($show_screen, $screen)
    {
        if ($screen && in_array($screen->id, ['hookaw', 'edit-hookaw', 'hookaw_log', 'edit-hookaw_log'])) {
            return false;
        }
        return $show_screen;
    }

    /**
     * Add admin CSS to hide native publish box features (status, visibility)
     */
    public function custom_admin_css()
    {
        // CSS is now fully enqueued from assets/css/admin.css
    }

    /**
     * Add custom columns to the HookAnywhere list table
     */
    public function set_custom_edit_hookaw_columns($columns)
    {
        // Insert 'Tags' and 'Enable Webhook' after 'title' and remove 'date'
        $new_columns = [];
        foreach ($columns as $key => $title) {
            if ($key === 'date') {
                continue; // Do not include Date for Webhooks
            }

            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['hookaw_tags'] = __('Tags', 'hookanywhere');
                $new_columns['hookaw_status'] = __('Enable Webhook', 'hookanywhere');
            }
        }
        return $new_columns;
    }

    /**
     * Render the custom columns in the HookAnywhere list table
     */
    public function custom_hookaw_column($column, $post_id)
    {
        if ($column === 'hookaw_tags') {
            $terms = get_the_terms($post_id, 'hookaw_tag');
            if (!empty($terms) && !is_wp_error($terms)) {
                $out = [];
                foreach ($terms as $term) {
                    $url = add_query_arg([
                        'post_type' => 'hookaw',
                        'hookaw_tag' => $term->slug
                    ], admin_url('edit.php'));
                    $out[] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($term->name));
                }
                echo wp_kses_post(implode(', ', $out));
            } else {
                echo '&mdash;';
            }
        }

        if ($column === 'hookaw_status') {
            $is_active = get_post_meta($post_id, '_hookaw_is_active', true);
            if ($is_active === '') {
                $is_active = 'yes'; // Default to active
            }

            $checked = $is_active === 'yes' ? 'checked="checked"' : '';

            echo '<label class="hookaw-switch">';
            echo '<input type="checkbox" class="hookaw-list-toggle" data-post-id="' . esc_attr($post_id) . '" ' . disabled(true, false, false) . ' ' . checked($is_active, 'yes', false) . '>';
            echo '<span class="hookaw-slider hookaw-round"></span>';
            echo '</label>';
            echo '<span class="spinner" id="hookaw-spinner-' . esc_attr($post_id) . '" style="float: none; margin-left: 5px; margin-top: 0;"></span>';
        }
    }

    /**
     * AJAX handler to safely toggle the status from the list table
     */
    public function ajax_toggle_status()
    {
        check_ajax_referer('hookaw_toggle_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === 'yes' ? 'yes' : 'no';

        if ($post_id) {
            update_post_meta($post_id, '_hookaw_is_active', $is_active);
            wp_send_json_success();
        } else {
            wp_send_json_error('Invalid Post ID');
        }
    }

    /**
     * Add JS to handle list table toggles via AJAX
     */
    public function list_table_toggle_js()
    {
        global $post_type;
        if ('hookaw' !== $post_type) {
            return;
        }


    }


    // --- Meta Box Renderers ---

    /**
     * Injects the Webhook Active toggle straight into the native Publish meta box.
     */
    public function add_submitbox_toggle($post)
    {
        if ($post->post_type !== 'hookaw') {
            return;
        }

        $is_active = get_post_meta($post->ID, '_hookaw_is_active', true);
        if ($is_active === '') {
            $is_active = 'yes'; // Default to active for new webhooks
        }

        $logs_url = admin_url('edit.php?post_type=hookaw_log&hookaw_filter_webhook=' . $post->ID);

        ?>
        <div class="misc-pub-section misc-pub-hookaw-status" style="padding: 15px 10px; border-bottom: none;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                <label style="font-weight: 600; margin: 0; color: #3c434a;">Enable Webhook</label>
                <label class="hookaw-switch" style="margin: 0;">
                    <input type="checkbox" name="hookaw_is_active" value="yes" <?php checked($is_active, 'yes'); ?>>
                    <span class="hookaw-slider hookaw-round"></span>
                </label>
            </div>
            <p class="description" style="margin: 0; padding-bottom: 5px;">Disable to pause this webhook instead of deleting it
                permanently.</p>
        </div>
        <div class="misc-pub-section misc-pub-hookaw-logs"
            style="padding: 0 10px 15px 10px; border-bottom: none; border-top: none;">
            <a href="<?php echo esc_url($logs_url); ?>" class="button button-primary" style="width: 100%; text-align: center;">
                <?php esc_html_e('View Webhook Logs', 'hookanywhere'); ?>
            </a>
        </div>
        <?php
    }

    public function render_configuration_meta_box($post)
    {
        $grouped_hooks = $this->get_grouped_available_hooks();
        $saved_hook = get_post_meta($post->ID, '_hookaw_hook_name', true);

        $current_integration = '';
        $is_custom = true;

        if (!empty($saved_hook)) {
            foreach ($grouped_hooks as $group => $hooks) {
                if (in_array($saved_hook, $hooks, true)) {
                    $current_integration = $group;
                    $is_custom = false;
                    break;
                }
            }
        } else {
            $is_custom = false;
        }

        $integration_val = $is_custom ? 'custom_advanced' : $current_integration;

        wp_nonce_field('hookaw_save_meta_boxes', 'hookaw_meta_nonce');

        $url = get_post_meta($post->ID, '_hookaw_url', true);
        $method = get_post_meta($post->ID, '_hookaw_method', true) ?: 'POST';
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $timeout = get_post_meta($post->ID, '_hookaw_timeout', true);
        if ($timeout === '') {
            $timeout = '5'; // Default timeout to 5 seconds
        }

        $auth_type = get_post_meta($post->ID, '_hookaw_auth_type', true) ?: 'none';
        $auth_credentials = get_post_meta($post->ID, '_hookaw_auth_credentials', true) ?: [];

        $basic_user = esc_attr($auth_credentials['basic_user'] ?? '');
        $basic_pass = esc_attr($auth_credentials['basic_pass'] ?? '');
        $bearer_token = esc_attr($auth_credentials['bearer_token'] ?? '');
        $header_name = esc_attr($auth_credentials['header_name'] ?? '');
        $header_value = esc_attr($auth_credentials['header_value'] ?? '');
        $query_name = esc_attr($auth_credentials['query_name'] ?? '');
        $query_value = esc_attr($auth_credentials['query_value'] ?? '');

        $headers = get_post_meta($post->ID, '_hookaw_headers', true) ?: [];
        $body_params = get_post_meta($post->ID, '_hookaw_body_params', true) ?: [];

        ?>
        <div class="hookaw-dashboard-wrap" style="padding: 20px 40px; background: transparent; box-sizing: border-box;">

            <!-- Step 1: Trigger Event -->
            <h3 style="margin-top:0; margin-bottom: 10px;">1. Trigger Event</h3>
            <div class="hookaw-webhook-card hookaw-grid-2-col" style="margin-bottom: 30px;">
                <div class="hookaw-field-group">
                    <label>Plugin:</label>
                    <select class="hookaw-integration-select" style="width: 100%;">
                        <option value="" disabled <?php selected($integration_val, ''); ?>>-- Select Plugin --</option>
                        <?php foreach ($grouped_hooks as $group_label => $hooks): ?>
                            <option value="<?php echo esc_attr($group_label); ?>" <?php selected($integration_val, $group_label); ?>><?php echo esc_html($group_label); ?></option>
                        <?php endforeach; ?>
                        <option value="custom_advanced" <?php selected($integration_val, 'custom_advanced'); ?>>Advanced Custom
                            Action...</option>
                    </select>
                </div>

                <div class="hookaw-field-group">
                    <label>Event:</label>
                    <select class="hookaw-action-select" style="width: 100%;"
                        data-current-val="<?php echo esc_attr($saved_hook); ?>"></select>

                    <div class="hookaw-custom-hook-wrap" style="display: <?php echo $is_custom ? 'block' : 'none'; ?>;">
                        <input type="text" name="hookaw_hook_name" value="<?php echo esc_attr($saved_hook); ?>"
                            placeholder="Enter custom hook name (e.g. my_custom_event)" style="width: 100%;" />
                    </div>
                </div>
            </div>

            <!-- Step 2: HTTP Request Details -->
            <h3 style="margin-top:0; margin-bottom: 10px;">2. HTTP Request Details</h3>
            <div class="hookaw-webhook-card hookaw-grid-3-col" style="margin-bottom: 30px;">
                <div class="hookaw-field-group">
                    <label>HTTP Method:</label>
                    <select name="hookaw_method" style="width: 100%;">
                        <?php foreach ($methods as $m): ?>
                            <option value="<?php echo esc_attr($m); ?>" <?php selected($method, $m); ?>><?php echo esc_html($m); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="hookaw-field-group">
                    <label>Destination Webhook URL:</label>
                    <input type="url" name="hookaw_url" value="<?php echo esc_url($url); ?>" placeholder="https://..."
                        style="width: 100%;" required />
                </div>

                <div class="hookaw-field-group">
                    <label>Timeout (seconds):</label>
                    <input type="number" name="hookaw_timeout" value="<?php echo esc_attr($timeout); ?>" min="1" max="60"
                        style="width: 100%;" />
                </div>
            </div>

            <!-- Step 3: Authentication -->
            <h3 style="margin-top:0; margin-bottom: 10px;">3. Authentication <span
                    style="font-weight:normal; color:#6b7280; font-size:13px;">(Optional)</span></h3>
            <div class="hookaw-webhook-card" style="margin-bottom: 30px;">
                <div class="hookaw-grid-2-col" style="margin-bottom: 15px;">
                    <div class="hookaw-field-group">
                        <label>Authentication Type:</label>
                        <select name="hookaw_auth_type" id="hookaw-auth-type-select" style="width: 100%;">
                            <option value="none" <?php selected($auth_type, 'none'); ?>>None</option>
                            <option value="basic" <?php selected($auth_type, 'basic'); ?>>Basic Auth</option>
                            <option value="bearer" <?php selected($auth_type, 'bearer'); ?>>Bearer Token</option>
                            <option value="header" <?php selected($auth_type, 'header'); ?>>Header</option>
                            <option value="query" <?php selected($auth_type, 'query'); ?>>Query Parameter</option>
                        </select>
                    </div>
                </div>

                <!-- Basic Auth Fields -->
                <div id="hookaw-auth-basic-fields" class="hookaw-grid-2-col"
                    style="display: <?php echo $auth_type === 'basic' ? 'grid' : 'none'; ?>;">
                    <div class="hookaw-field-group">
                        <label>Username:</label>
                        <input type="text" name="hookaw_auth_basic_user" value="<?php echo esc_attr($basic_user); ?>"
                            style="width: 100%;" />
                    </div>
                    <div class="hookaw-field-group">
                        <label>Password:</label>
                        <input type="password" name="hookaw_auth_basic_pass" value="<?php echo esc_attr($basic_pass); ?>"
                            style="width: 100%;" />
                    </div>
                </div>

                <!-- Bearer Token Field -->
                <div id="hookaw-auth-bearer-fields" class="hookaw-grid-full"
                    style="display: <?php echo $auth_type === 'bearer' ? 'grid' : 'none'; ?>;">
                    <div class="hookaw-field-group">
                        <label>Token:</label>
                        <input type="text" name="hookaw_auth_bearer_token" value="<?php echo esc_attr($bearer_token); ?>"
                            style="width: 100%;" />
                        <p class="description">Provide exactly the token value. The plugin will automatically prepend 'Bearer '.
                        </p>
                    </div>
                </div>

                <!-- Header Auth Fields -->
                <div id="hookaw-auth-header-fields" class="hookaw-grid-2-col"
                    style="display: <?php echo $auth_type === 'header' ? 'grid' : 'none'; ?>;">
                    <div class="hookaw-field-group">
                        <label>Header Name:</label>
                        <input type="text" name="hookaw_auth_header_name" value="<?php echo esc_attr($header_name); ?>"
                            style="width: 100%;" placeholder="e.g. x-api-key" />
                    </div>
                    <div class="hookaw-field-group">
                        <label>Header Value:</label>
                        <input type="text" name="hookaw_auth_header_value" value="<?php echo esc_attr($header_value); ?>"
                            style="width: 100%;" />
                    </div>
                </div>

                <!-- Query Auth Fields -->
                <div id="hookaw-auth-query-fields" class="hookaw-grid-2-col"
                    style="display: <?php echo $auth_type === 'query' ? 'grid' : 'none'; ?>;">
                    <div class="hookaw-field-group">
                        <label>Query Parameter Name:</label>
                        <input type="text" name="hookaw_auth_query_name" value="<?php echo esc_attr($query_name); ?>"
                            style="width: 100%;" placeholder="e.g. key" />
                    </div>
                    <div class="hookaw-field-group">
                        <label>Query Parameter Value:</label>
                        <input type="text" name="hookaw_auth_query_value" value="<?php echo esc_attr($query_value); ?>"
                            style="width: 100%;" />
                    </div>
                </div>
            </div>

            <!-- Step 4: Headers & Parameters -->
            <h3 style="margin-top:0; margin-bottom: 10px;">4. Headers & Parameters <span
                    style="font-weight:normal; color:#6b7280; font-size:13px;">(Optional)</span></h3>
            <div class="hookaw-webhook-card hookaw-repeater-container" id="hookaw-headers-container" style="margin-bottom: 20px;">
                <h4 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:8px;">Custom Headers</h4>
                <div class="hookaw-repeater-rows">
                    <?php if (empty($headers)): ?>
                        <div class="hookaw-grid-repeater hookaw-repeater-row" style="align-items: end; margin-bottom:10px;">
                            <div class="hookaw-field-group">
                                <label>Header Name:</label>
                                <input type="text" name="hookaw_headers[0][key]" style="width: 100%;"
                                    placeholder="e.g. Content-Type" />
                            </div>
                            <div class="hookaw-field-group">
                                <label>Header Value:</label>
                                <input type="text" name="hookaw_headers[0][value]" style="width: 100%;"
                                    placeholder="e.g. application/json" />
                            </div>
                            <div class="hookaw-field-group" style="max-width: 50px;">
                                <label style="visibility: hidden;">Header Name:</label>
                                <button type="button" class="hookaw-remove-row">&times;</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($headers as $index => $header): ?>
                            <div class="hookaw-grid-repeater hookaw-repeater-row" style="align-items: end; margin-bottom:10px;">
                                <div class="hookaw-field-group">
                                    <label>Header Name:</label>
                                    <input type="text" name="hookaw_headers[<?php echo esc_attr($index); ?>][key]"
                                        value="<?php echo esc_attr($header['key']); ?>" style="width: 100%;" />
                                </div>
                                <div class="hookaw-field-group">
                                    <label>Header Value:</label>
                                    <input type="text" name="hookaw_headers[<?php echo esc_attr($index); ?>][value]"
                                        value="<?php echo esc_attr($header['value']); ?>" style="width: 100%;" />
                                </div>
                                <div class="hookaw-field-group" style="max-width: 50px;">
                                    <label style="visibility: hidden;">Header Name:</label>
                                    <button type="button" class="hookaw-remove-row">&times;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="hookaw-add-row" data-type="headers"
                    style="background-color: #fff; color: #374151; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid #d1d5db; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" style="width: 14px; height: 14px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Header
                </button>

                <h4 style="margin-top:25px; border-bottom:1px solid #eee; padding-bottom:8px;">Data & Parameters</h4>
                <p class="description" style="margin-bottom: 15px;">Add custom key-value pairs that will be included in the JSON
                    body (or URL Query string for GET requests).</p>
                <div class="hookaw-repeater-rows">
                    <?php if (empty($body_params)): ?>
                        <div class="hookaw-grid-repeater hookaw-repeater-row" style="align-items: end; margin-bottom:10px;">
                            <div class="hookaw-field-group">
                                <label>Parameter key:</label>
                                <input type="text" name="hookaw_body_params[0][key]" style="width: 100%;" placeholder="e.g. source" />
                            </div>
                            <div class="hookaw-field-group">
                                <label>Value:</label>
                                <input type="text" name="hookaw_body_params[0][value]" style="width: 100%;"
                                    placeholder="e.g. wordpress" />
                            </div>
                            <div class="hookaw-field-group" style="max-width: 50px;">
                                <label style="visibility: hidden;">Parameter key:</label>
                                <button type="button" class="hookaw-remove-row">&times;</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($body_params as $index => $param): ?>
                            <div class="hookaw-grid-repeater hookaw-repeater-row" style="align-items: end; margin-bottom:10px;">
                                <div class="hookaw-field-group">
                                    <label>Parameter key:</label>
                                    <input type="text" name="hookaw_body_params[<?php echo esc_attr($index); ?>][key]"
                                        value="<?php echo esc_attr($param['key']); ?>" style="width: 100%;" />
                                </div>
                                <div class="hookaw-field-group">
                                    <label>Value:</label>
                                    <input type="text" name="hookaw_body_params[<?php echo esc_attr($index); ?>][value]"
                                        value="<?php echo esc_attr($param['value']); ?>" style="width: 100%;" />
                                </div>
                                <div class="hookaw-field-group" style="max-width: 50px;">
                                    <label style="visibility: hidden;">Parameter key:</label>
                                    <button type="button" class="hookaw-remove-row">&times;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="hookaw-add-row" data-type="body_params"
                    style="background-color: #fff; color: #374151; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid #d1d5db; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" style="width: 14px; height: 14px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Parameter
                </button>
            </div>

            <?php // Output JS logic ?>
            <!-- JS is now handled in admin.js -->
        </div>
        <?php
    }


    public function render_log_details_meta_box($post)
    {
        $request_args = get_post_meta($post->ID, '_hookaw_request_args', true);
        $response_headers = get_post_meta($post->ID, '_hookaw_response_headers', true);
        $response_body = get_post_meta($post->ID, '_hookaw_response_body', true);
        $webhook_id = get_post_meta($post->ID, '_hookaw_webhook_id', true);
        $request_url = get_post_meta($post->ID, '_hookaw_request_url', true);
        $request_method = get_post_meta($post->ID, '_hookaw_request_method', true);

        // Standardize timestamp to site timezone
        $timestamp = wp_date(get_option('date_format') . ' @ ' . get_option('time_format'), get_post_datetime($post->ID)->getTimestamp());

        // Try decoding as JSON. If json_decode results in null (except for literal "null") 
        // or throws an error, we fallback to the raw string.
        $req_arr = json_decode($request_args, true);
        if ($req_arr === null && json_last_error() !== JSON_ERROR_NONE) {
            $req_arr = $request_args;
        }

        $res_hdrs = json_decode($response_headers, true);
        if ($res_hdrs === null && json_last_error() !== JSON_ERROR_NONE) {
            $res_hdrs = $response_headers;
        }

        // If they are empty arrays, let's explicitly set them to empty strings so they show up blank or raw instead of 'Array()'
        if (is_array($req_arr) && empty($req_arr)) {
            $req_arr = $request_args ?: 'No Data';
        }
        if (is_array($res_hdrs) && empty($res_hdrs)) {
            $res_hdrs = $response_headers ?: 'No Headers';
        }

        // Helper to format for display
        $display_req = is_array($req_arr) ? wp_json_encode($req_arr, JSON_PRETTY_PRINT) : $req_arr;
        $display_res_hdrs = is_array($res_hdrs) ? wp_json_encode($res_hdrs, JSON_PRETTY_PRINT) : $res_hdrs;

        ?>
        <div class="hookaw-dashboard-wrap" style="padding: 20px 40px; background: transparent; box-sizing: border-box;">

            <h3 style="margin-top:0; margin-bottom: 10px;">1. Request Information</h3>
            <div class="hookaw-webhook-card hookaw-grid-full" style="margin-bottom: 30px;">
                <div class="hookaw-field-group">
                    <div
                        style="background: #f6f7f7; padding: 15px; border: 1px solid #dcdcde; border-radius: 4px; font-size: 13px; font-family: monospace;">
                        <p style="margin: 0 0 8px 0;"><strong>Timestamp:</strong> <?php echo esc_html($timestamp); ?></p>
                        <p style="margin: 0 0 8px 0;"><strong>Request URL:</strong> <?php echo esc_url($request_url); ?></p>
                        <p style="margin: 0;"><strong>HTTP Method:</strong> <?php echo esc_html($request_method); ?></p>
                    </div>
                </div>
            </div>

            <h3 style="margin-top:0; margin-bottom: 10px;">2. Request Data</h3>
            <div class="hookaw-webhook-card" style="margin-bottom: 30px;">
                <div class="hookaw-field-group">
                    <pre
                        style="background: #f6f7f7; padding: 15px; margin: 0; border: 1px solid #dcdcde; border-radius: 4px; overflow: auto; max-height: 400px; font-size: 13px; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($display_req); ?></pre>
                </div>
            </div>

            <h3 style="margin-top:0; margin-bottom: 10px;">3. Response Body</h3>
            <div class="hookaw-webhook-card" style="margin-bottom: 30px;">
                <div class="hookaw-field-group">
                    <pre
                        style="background: #f6f7f7; padding: 15px; margin: 0; border: 1px solid #dcdcde; border-radius: 4px; overflow: auto; max-height: 400px; font-size: 13px; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($response_body); ?></pre>
                </div>
            </div>

            <h3 style="margin-top:0; margin-bottom: 10px;">4. Response Headers</h3>
            <div class="hookaw-webhook-card" style="margin-bottom: 30px;">
                <div class="hookaw-field-group">
                    <pre
                        style="background: #f6f7f7; padding: 15px; margin: 0; border: 1px solid #dcdcde; border-radius: 4px; overflow: auto; max-height: 200px; font-size: 13px; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($display_res_hdrs); ?></pre>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Render the custom Log Actions meta box (Read-Only)
     */
    public function render_log_actions_meta_box($post)
    {
        $status = get_post_meta($post->ID, '_hookaw_response_status', true);
        // Standardize timestamp to site timezone
        $timestamp = wp_date(get_option('date_format') . ' @ ' . get_option('time_format') . ' T', get_post_datetime($post->ID)->getTimestamp());

        $color = ($status && $status >= 200 && $status < 300) ? '#00a32a' : '#d63638';
        if ($status === 'ERROR')
            $color = '#d63638';
        $status_display = $status ?: '—';

        $webhook_id = get_post_meta($post->ID, '_hookaw_webhook_id', true);
        $webhook = get_post($webhook_id);
        $webhook_exists = $webhook && $webhook->post_type === 'hookaw';

        ?>
        <div style="padding: 10px 0 12px 0;">
            <p style="margin: 0 0 15px 0;"><strong>Logged On:</strong><br>
                <span style="font-size: 13px; color: #646970;"><?php echo esc_html($timestamp); ?></span>
            </p>

            <p style="margin: 0; margin-bottom: 12px;"><strong>Final Status:</strong> <span
                    style="color: <?php echo esc_attr($color); ?>; font-weight: bold;"><?php echo esc_html($status_display); ?></span>
            </p>

            <?php if ($webhook_exists): ?>
                <a href="<?php echo esc_url(get_edit_post_link($webhook_id)); ?>" class="button button-primary"
                    style="width: 100%; text-align: center;">
                    <?php esc_html_e('View / Edit Webhook', 'hookanywhere'); ?>
                </a>
            <?php else: ?>
                <button type="button" class="button button-primary button-disabled" style="width: 100%; text-align: center;"
                    disabled title="<?php esc_attr_e('This Webhook has been deleted.', 'hookanywhere'); ?>">
                    <?php esc_html_e('View / Edit Webhook', 'hookanywhere'); ?>
                </button>
            <?php endif; ?>
        </div>
        <div style="background-color: #f6f7f7; border-top: 1px solid #dcdcde; padding: 10px 12px; margin: 0 -12px -12px -12px;">
            <?php if (current_user_can('delete_post', $post->ID)): ?>
                <a style="color: #b32d2e; text-decoration: none;" href="<?php echo esc_url(get_delete_post_link($post->ID)); ?>"
                    onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">Move to
                    Trash</a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Display the log title as a non-editable h1 at the top of the edit screen
     */
    public function display_log_title_readonly($post)
    {
        if ($post->post_type === 'hookaw_log') {
            // Replace the default "Edit Post" heading with our Log title securely
            ?>
            <!-- Extracted to admin.js -->
            <?php
        }
    }

    /**
     * Save meta box data.
     *
     * @param int $post_id The post ID.
     */
    public function save_meta_boxes($post_id)
    {
        // Check if our nonce is set.
        if (!isset($_POST['hookaw_meta_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce(sanitize_key(wp_unslash($_POST['hookaw_meta_nonce'])), 'hookaw_save_meta_boxes')) {
            return;
        }

        // Bail if we're doing an auto save
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save Hook Name
        if (isset($_POST['hookaw_hook_name'])) {
            update_post_meta($post_id, '_hookaw_hook_name', sanitize_text_field(wp_unslash($_POST['hookaw_hook_name'])));
        }

        // Save Action URL and Method
        if (isset($_POST['hookaw_url'])) {
            update_post_meta($post_id, '_hookaw_url', esc_url_raw(wp_unslash($_POST['hookaw_url'])));
        }
        if (isset($_POST['hookaw_method'])) {
            update_post_meta($post_id, '_hookaw_method', sanitize_text_field(wp_unslash($_POST['hookaw_method'])));
        }
        if (isset($_POST['hookaw_timeout'])) {
            update_post_meta($post_id, '_hookaw_timeout', intval(wp_unslash($_POST['hookaw_timeout'])));
        }

        // Save Auth Types and Credentials
        if (isset($_POST['hookaw_auth_type'])) {
            $auth_type = sanitize_text_field(wp_unslash($_POST['hookaw_auth_type']));
            update_post_meta($post_id, '_hookaw_auth_type', $auth_type);

            $credentials = [];
            if ($auth_type === 'basic') {
                $credentials['basic_user'] = sanitize_text_field(wp_unslash($_POST['hookaw_auth_basic_user'] ?? ''));
                $credentials['basic_pass'] = sanitize_text_field(wp_unslash($_POST['hookaw_auth_basic_pass'] ?? ''));
            } elseif ($auth_type === 'bearer') {
                $credentials['bearer_token'] = sanitize_text_field(wp_unslash($_POST['hookaw_auth_bearer_token'] ?? ''));
            } elseif ($auth_type === 'header') {
                $credentials['header_name'] = sanitize_text_field(wp_unslash($_POST['hookaw_auth_header_name'] ?? ''));
                $credentials['header_value'] = sanitize_text_field(wp_unslash($_POST['hookaw_auth_header_value'] ?? ''));
            } elseif ($auth_type === 'query') {
                $credentials['query_name'] = sanitize_text_field(wp_unslash($_POST['hookaw_auth_query_name'] ?? ''));
                $credentials['query_value'] = sanitize_text_field(wp_unslash($_POST['hookaw_auth_query_value'] ?? ''));
            }

            update_post_meta($post_id, '_hookaw_auth_credentials', $credentials);
        }

        // Save Custom Headers
        $headers = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $posted_headers = isset($_POST['hookaw_headers']) ? map_deep(wp_unslash($_POST['hookaw_headers']), 'sanitize_text_field') : [];
        if (is_array($posted_headers)) {
            foreach ($posted_headers as $header) {
                if (!empty($header['key'])) {
                    $headers[] = [
                        'key' => sanitize_text_field($header['key']),
                        'value' => sanitize_text_field($header['value'])
                    ];
                }
            }
        }
        update_post_meta($post_id, '_hookaw_headers', $headers);

        // Save Body Parameters
        $body_params = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $posted_body_params = isset($_POST['hookaw_body_params']) ? map_deep(wp_unslash($_POST['hookaw_body_params']), 'sanitize_text_field') : [];
        if (is_array($posted_body_params)) {
            foreach ($posted_body_params as $param) {
                if (!empty($param['key'])) {
                    $body_params[] = [
                        'key' => sanitize_text_field($param['key']),
                        'value' => sanitize_text_field($param['value'])
                    ];
                }
            }
        }
        update_post_meta($post_id, '_hookaw_body_params', $body_params);

        // Save Active Toggle
        $is_active = isset($_POST['hookaw_is_active']) && sanitize_text_field(wp_unslash($_POST['hookaw_is_active'])) === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, '_hookaw_is_active', $is_active);
    }
    // --- Helper Methods ---

    /**
     * Get a combined list of predefined and actively used WordPress hooks grouped dynamically.
     * Hooks are traced back to their originating plugin file using PHP Reflection.
     *
     * @return array Array of grouped hook names.
     */
    private function get_grouped_available_hooks()
    {
        return HOOKAW_Helpers::get_grouped_available_hooks();
    }

    public function set_custom_edit_hookaw_log_columns($columns)
    {
        $new_columns = [];
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb']; // Keep checkbox
        }
        $new_columns['title'] = esc_html__('Time / Action', 'hookanywhere');
        $new_columns['hookaw_log_webhook'] = esc_html__('Webhook', 'hookanywhere');
        $new_columns['hookaw_log_url'] = esc_html__('Request URL', 'hookanywhere');
        $new_columns['hookaw_log_status'] = esc_html__('Status', 'hookanywhere');
        return $new_columns;
    }

    public function custom_hookaw_log_column($column, $post_id)
    {
        if ($column === 'hookaw_log_webhook') {
            $webhook_id = get_post_meta($post_id, '_hookaw_webhook_id', true);
            if ($webhook_id) {
                $webhook_title = get_the_title($webhook_id);
                if ($webhook_title) {
                    $edit_link = get_edit_post_link($webhook_id);
                    echo '<a href="' . esc_url((string) $edit_link) . '">' . esc_html($webhook_title) . '</a>';
                } else {
                    echo esc_html__('Deleted Webhook', 'hookanywhere');
                }
            } else {
                echo '&mdash;';
            }
        } elseif ($column === 'hookaw_log_url') {
            $url = get_post_meta($post_id, '_hookaw_request_url', true);
            $method = get_post_meta($post_id, '_hookaw_request_method', true);
            echo '<strong>' . esc_html($method) . '</strong><br>';
            echo '<span style="font-size: 11px;">' . esc_html($url) . '</span>';
        } elseif ($column === 'hookaw_log_status') {
            $status = get_post_meta($post_id, '_hookaw_response_status', true);
            if ($status === 'ERROR') {
                $error = get_post_meta($post_id, '_hookaw_response_error', true);
                echo '<span style="color: #d63638; font-weight: bold;">Failed</span><br>';
                echo '<span style="font-size: 11px; color: #d63638;">' . esc_html($error) . '</span>';
            } else {
                $color = ($status >= 200 && $status < 300) ? '#00a32a' : '#d63638';
                echo '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">' . esc_html($status) . '</span>';
            }
        }
    }


    /**
     * Disable the months dropdown for the hookaw and hookaw_log post types
     */
    public function disable_months_dropdown_for_hookaw($disable, $post_type)
    {
        if ($post_type === 'hookaw' || $post_type === 'hookaw_log') {
            return true;
        }
        return $disable;
    }

    /**
     * Add a dropdown to filter logs by Webhook
     */
    public function add_log_filters($post_type)
    {
        if ($post_type !== 'hookaw_log') {
            return;
        }

        // Get all webhooks to populate the dropdown
        $webhooks = get_posts([
            'post_type' => 'hookaw',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        if (empty($webhooks)) {
            return;
        }

        // Check if there is already a filtered webhook selected
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_webhook = isset($_GET['hookaw_filter_webhook']) ? sanitize_text_field(wp_unslash($_GET['hookaw_filter_webhook'])) : '';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<select name="hookaw_filter_webhook" id="hookaw_filter_webhook">';
        echo '<option value="">' . esc_html__('All Webhooks', 'hookanywhere') . '</option>';

        foreach ($webhooks as $webhook) {
            $selected = selected($selected_webhook, $webhook->ID, false);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<option value="' . esc_attr($webhook->ID) . '" ' . $selected . '>' . esc_html($webhook->post_title) . '</option>';
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</select>';

        // Use inline JavaScript to place the Export CSV Button
        $nonce = wp_create_nonce('hookaw_export_logs_nonce');
        $export_url = admin_url('admin-post.php?action=hookaw_export_logs&hookaw_export_nonce=' . $nonce);
        ?>
        <!-- Extracted to admin.js -->
        <?php
    }

    /**
     * Filter the logs based on the selected Webhook in the dropdown
     */
    public function filter_logs_by_webhook($query)
    {
        global $pagenow;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!is_admin() || $pagenow !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'hookaw_log') {
            return;
        }

        if (!$query->is_main_query()) {
            return;
        }

        // Apply the filter if a webhook was selected
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['hookaw_filter_webhook']) && !empty($_GET['hookaw_filter_webhook'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $webhook_id = sanitize_text_field(wp_unslash($_GET['hookaw_filter_webhook']));

            $meta_query = $query->get('meta_query');
            if (!is_array($meta_query)) {
                $meta_query = [];
            }

            $meta_query[] = [
                'key' => '_hookaw_webhook_id',
                'value' => $webhook_id,
            ];

            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Remove 'Edit' from the bulk actions dropdown on the Logs list table
     */
    public function custom_hookaw_log_bulk_actions($actions)
    {
        if (isset($actions['edit'])) {
            unset($actions['edit']);
        }
        return $actions;
    }
}
