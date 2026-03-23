<?php
/**
 * Admin logic for the HookAnywhere plugin.
 *
 * @package HookAnywhere
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Class
 */
class HOOKAW_Admin
{

    /**
     * Option name.
     * (Kept temporarily if needed for future migrations)
     *
     * @var string
     */
    private $option_name = 'hookaw_webhooks';

    /**
     * Init function.
     */
    public function init()
    {
        // Temporary developer reset: add ?reset_hookaw=1 to your URL
        if (isset($_GET['reset_hookaw']) && current_user_can('manage_options')) {
            // Nonce check for the developer reset
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'hookaw_reset_nonce')) {
                delete_option('hookaw_welcome_completed');
            }
        }

        add_action('admin_init', [$this, 'set_installer_id'], 1);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_menu', [$this, 'restrict_admin_menu'], 99);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'restrict_cpt_access']);
        add_filter('admin_title', [$this, 'filter_admin_title'], 10, 2);

        // Handlers for the onboarding form
        add_action('admin_post_hookaw_welcome_submit', [$this, 'handle_welcome_submit']);
        add_action('admin_post_hookaw_welcome_skip', [$this, 'handle_welcome_skip']);

        // Allow authorized non-admins to save plugin settings
        add_filter('option_page_capability_hookaw_settings_group', function ($cap) {
            return self::is_user_allowed() ? 'read' : 'manage_options';
        });

        // CSS is now handled via wp_enqueue_style in assets/css/admin.css
    }

    /**
     * Set the installer ID securely during admin_init.
     */
    public function set_installer_id()
    {
        if (!get_option('hookaw_installer_user_id') && current_user_can('manage_options')) {
            update_option('hookaw_installer_user_id', get_current_user_id());
        }
    }

    /**
     * Check if the current user is allowed to access the plugin.
     */
    public static function is_user_allowed()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = wp_get_current_user();
        if (empty($current_user) || !$current_user->exists()) {
            return false;
        }

        // Administrators always have access
        if (in_array('administrator', (array) $current_user->roles)) {
            return true;
        }

        $installer_id = get_option('hookaw_installer_user_id');

        // Installer is always allowed
        if ($installer_id && $current_user->ID == $installer_id) {
            return true;
        }

        // Check if user has any allowed role
        $allowed_roles = get_option('hookaw_allowed_roles', []);
        if (!is_array($allowed_roles)) {
            $allowed_roles = [];
        }

        foreach ((array) $current_user->roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }

        // Fallback: If no installer is set yet (plugin was active before this update), admins are allowed
        if (empty($installer_id) && current_user_can('manage_options')) {
            return true;
        }

        return false;
    }

    /**
     * Restrict access to the CPT pages.
     */
    public function restrict_cpt_access()
    {
        global $pagenow;
        if (is_admin() && in_array($pagenow, ['edit.php', 'post-new.php', 'post.php'], true)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (empty($post_type) && isset($_GET['post'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $post_id = (int) $_GET['post'];
                $post_type = get_post_type($post_id);
            }
            if ($post_type === 'hookaw' || $post_type === 'hookaw_log') {
                if (!self::is_user_allowed()) {
                    wp_die(esc_html__('You do not have permission to access HookAnywhere.', 'hookanywhere'));
                }
            }
        }
    }

    /**
     * Remove the menu if the user is not allowed.
     */
    public function restrict_admin_menu()
    {
        if (!self::is_user_allowed()) {
            remove_menu_page('edit.php?post_type=hookaw');
        }
    }

    /**
     * Enqueue styles and scripts for the admin settings page.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets($hook)
    {
        $screen = get_current_screen();
        $post_type = $screen ? $screen->post_type : '';
        $is_hookaw_page = ($post_type === 'hookaw' || $post_type === 'hookaw_log' || strpos($hook, 'hookaw') !== false);


        if (!$is_hookaw_page) {
            return;
        }

        // Load Inter Font from Google Fonts
        wp_enqueue_style('hookaw-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap', [], '1.0.0');

        // Load custom admin dashboard CSS
        wp_enqueue_style('hookaw-admin-css', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', [], filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/admin.css'));

        // Load custom admin dashboard JS
        wp_enqueue_script('hookaw-admin-js', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js', ['jquery'], filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin.js'), true);

        // Prepare localized data
        $export_nonce = wp_create_nonce('hookaw_export_logs_nonce');
        $hookaw_data = [
            'nonceToggle'  => wp_create_nonce('hookaw_toggle_nonce'),
            'exportUrl'    => admin_url('admin-post.php?action=hookaw_export_logs&hookaw_export_nonce=' . $export_nonce),
            'i18n'         => [
                'exportLogs' => esc_html__('Export Logs to CSV', 'hookanywhere'),
            ]
        ];

        // Specific data based on screen
        if ($post_type === 'hookaw') {
            $hookaw_data['groupedHooks'] = $this->get_grouped_available_hooks();
        } elseif ($post_type === 'hookaw_log' && isset($screen) && $screen->base === 'post') {
            global $post;
            if (isset($post->post_title)) {
                $hookaw_data['logTitle'] = esc_attr($post->post_title);
            }
        }


        wp_localize_script('hookaw-admin-js', 'hookawData', $hookaw_data);
    }


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

    /**
     * Add the required admin menus.
     */
    public function add_admin_menus()
    {
        if (!self::is_user_allowed()) {
            return;
        }

        // Add the top level Home page (acts as the first item in the menu now)
        add_submenu_page(
            'edit.php?post_type=hookaw',
            esc_html__('Home', 'hookanywhere'),
            esc_html__('Home', 'hookanywhere'),
            'read',
            'hookaw-home',
            [$this, 'display_home_page'],
            0 // Priority to place it at the top
        );

        add_submenu_page(
            'edit.php?post_type=hookaw',
            esc_html__('Settings', 'hookanywhere'),
            esc_html__('Settings', 'hookanywhere'),
            'read',
            'hookaw-settings',
            [$this, 'display_settings_page']
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings()
    {
        register_setting('hookaw_settings_group', 'hookaw_log_retention_days', [
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => 'absint'
        ]);

        register_setting('hookaw_settings_group', 'hookaw_allowed_roles', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitize_allowed_roles']
        ]);
    }

    public function sanitize_allowed_roles($input)
    {
        if (!is_array($input)) {
            return [];
        }

        // Sanitize each part of the array
        $input = array_map('sanitize_key', $input);

        // Filter out any role that is NOT a valid WordPress role
        $wp_roles = wp_roles()->get_names();
        $valid_roles = array_keys($wp_roles);

        return array_intersect($input, $valid_roles);
    }

    /**
     * Display the Settings page UI.
     */
    public function display_settings_page()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'views/admin-settings.php';
    }

    /**
     * Display the Home / Welcome page UI.
     */
    public function display_home_page()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'views/admin-home.php';
    }


    /**
     * Handle the submission of the welcome subscription form.
     */
    public function handle_welcome_submit()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'hookanywhere'));
        }

        check_admin_referer('hookaw_welcome_nonce_action', 'hookaw_welcome_nonce');

        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        // Send data to self-hosted n8n webhook
        $webhook_url = 'https://n8n.490094.xyz/webhook/f211e69e-1625-4ecd-a5f5-13abd8a45bd4';
        $payload = [
            'firstName' => $first_name,
            'lastName' => $last_name,
            'email' => $email,
            'source' => 'HookAnywhere Plugin'
        ];

        wp_remote_post($webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'secrete'      => 'I4ZMyoa3lbQDxNzK41qh2159',
            ],
            'body'     => wp_json_encode($payload),
            'blocking' => false // Run async so it doesn't slow down the user
        ]);

        // Save subscription details so they can be displayed later
        update_option('hookaw_subscribed_firstname', $first_name);
        update_option('hookaw_subscribed_lastname', $last_name);
        update_option('hookaw_subscribed_email', $email);

        // Mark onboarding complete
        update_option('hookaw_welcome_completed', true);

        // Redirect back to home dashboard
        wp_safe_redirect(admin_url('edit.php?post_type=hookaw&page=hookaw-home'));
        exit;
    }

    /**
     * Handle the skipping of the welcome screen.
     */
    public function handle_welcome_skip()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'hookanywhere'));
        }

        check_admin_referer('hookaw_welcome_nonce_action', 'hookaw_welcome_nonce');

        // Mark onboarding complete
        update_option('hookaw_welcome_completed', true);

        // Redirect back to home dashboard
        wp_safe_redirect(admin_url('edit.php?post_type=hookaw&page=hookaw-home'));
        exit;
    }

    /**
     * Append "- HookAnywhere" to the admin page title for all plugin screens.
     *
     * @param string $admin_title Full formatted title string.
     * @param string $title       Current page title portion.
     * @return string
     */
    public function filter_admin_title( $admin_title, $title ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || empty( $title ) ) {
            return $admin_title;
        }

        $is_hookaw = (
            ( isset( $screen->post_type ) && strpos( $screen->post_type, 'hookaw' ) !== false ) ||
            strpos( $screen->id, 'hookaw' ) !== false
        );

        if ( $is_hookaw ) {
            $admin_title = str_replace(
                $title,
                $title . ' &#8211; HookAnywhere',
                $admin_title
            );
        }

        return $admin_title;
    }

}

