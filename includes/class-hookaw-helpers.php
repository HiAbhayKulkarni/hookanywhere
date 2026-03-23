<?php
/**
 * Helper utilities for the HookAnywhere plugin.
 *
 * @package HookAnywhere
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helpers Class
 */
class HOOKAW_Helpers
{
    /**
     * Get a combined list of predefined and actively used WordPress hooks grouped dynamically.
     * Hooks are traced back to their originating plugin file using PHP Reflection.
     *
     * @return array Array of grouped hook names.
     */
    public static function get_grouped_available_hooks()
    {
        global $wp_filter;

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed_plugins = get_plugins();
        $plugin_folder_to_name = [];

        // Build a map of "plugin-folder" => "Human Readable Name"
        foreach ($installed_plugins as $plugin_file => $plugin_data) {
            $folder_parts = explode('/', $plugin_file);
            $folder_name = $folder_parts[0];

            // Exclude our own plugin from the dropdown options
            if ($folder_name === 'hookanywhere') {
                continue;
            }

            $plugin_folder_to_name[$folder_name] = $plugin_data['Name'];
        }

        $grouped_hooks = [
            'WordPress Core' => [
                'init',
                'wp_loaded',
                'user_register',
                'profile_update',
                'wp_login',
                'wp_logout',
                'publish_post',
                'save_post',
                'delete_post',
                'wp_insert_comment',
            ]
        ];

        if (!is_array($wp_filter)) {
            return $grouped_hooks;
        }

        $wp_plugin_dir = wp_normalize_path(WP_PLUGIN_DIR);

        foreach ($wp_filter as $hook_name => $wp_hook_obj) {
            // Very noisy core/internal hooks we don't need to show
            if (strpos($hook_name, 'gettext') !== false || strpos($hook_name, 'option_') !== false || strpos($hook_name, 'rest_') === 0 || strpos($hook_name, 'pre_') === 0 || strpos($hook_name, 'sanitize_') === 0 || strpos($hook_name, 'default_') === 0 || strpos($hook_name, 'theme_mod_') === 0) {
                continue;
            }

            // Also skip common WordPress prefixes aggressively to prevent them being categorized as "Other"
            if (
                strpos($hook_name, 'wp_') === 0 ||
                strpos($hook_name, 'admin_') === 0 ||
                strpos($hook_name, 'update_') === 0 ||
                strpos($hook_name, 'delete_') === 0 ||
                strpos($hook_name, 'transition_') === 0 ||
                strpos($hook_name, 'edit_') === 0 ||
                strpos($hook_name, 'save_') === 0 ||
                strpos($hook_name, 'clean_') === 0 ||
                strpos($hook_name, 'xmlrpc_') === 0 ||
                strpos($hook_name, 'ajax_') === 0 ||
                strpos($hook_name, 'login_') === 0 ||
                strpos($hook_name, 'auth_') === 0 ||
                strpos($hook_name, 'set_') === 0 ||
                strpos($hook_name, 'get_') === 0 ||
                strpos($hook_name, 'add_') === 0 ||
                strpos($hook_name, 'added_') === 0 ||
                strpos($hook_name, 'created_') === 0 ||
                strpos($hook_name, 'deleted_') === 0 ||
                strpos($hook_name, 'remove_') === 0 ||
                strpos($hook_name, 'register_') === 0 ||
                strpos($hook_name, 'registered_') === 0 ||
                strpos($hook_name, 'upgrader_') === 0 ||
                in_array($hook_name, ['plugins_loaded', 'setup_theme', 'after_setup_theme', 'template_redirect', 'shutdown', 'phpmailer_init'])
            ) {
                $grouped_hooks['WordPress Core'][] = $hook_name;
                continue;
            }

            // Now, attempt to find exactly where this hook was attached using Reflection
            if (isset($wp_hook_obj->callbacks) && is_array($wp_hook_obj->callbacks)) {
                foreach ($wp_hook_obj->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_data) {
                        try {
                            $function = $callback_data['function'];
                            $file_name = '';

                            if (is_array($function)) {
                                if (is_object($function[0])) {
                                    $reflector = new ReflectionMethod($function[0], $function[1]);
                                } else {
                                    $reflector = new ReflectionMethod($function[0], $function[1]);
                                }
                                $file_name = wp_normalize_path($reflector->getFileName());
                            } elseif (is_string($function) && function_exists($function)) {
                                $reflector = new ReflectionFunction($function);
                                $file_name = wp_normalize_path($reflector->getFileName());
                            } elseif ($function instanceof Closure) {
                                $reflector = new ReflectionFunction($function);
                                $file_name = wp_normalize_path($reflector->getFileName());
                            }

                            if ($file_name && strpos($file_name, $wp_plugin_dir) !== false) {
                                // Extract the plugin folder name
                                $relative_path = str_replace($wp_plugin_dir . '/', '', $file_name);
                                $folder_parts = explode('/', $relative_path);
                                $plugin_folder = $folder_parts[0];

                                if (isset($plugin_folder_to_name[$plugin_folder])) {
                                    $human_readable_plugin_name = $plugin_folder_to_name[$plugin_folder];
                                    if (!isset($grouped_hooks[$human_readable_plugin_name])) {
                                        $grouped_hooks[$human_readable_plugin_name] = [];
                                    }
                                    $grouped_hooks[$human_readable_plugin_name][] = $hook_name;
                                    break 2; // Move on to the next hook
                                }
                            }
                        } catch (Exception $e) {
                            // Ignore reflection errors
                        }
                    }
                }
            }
        }

        // Clean up empty groups and sort inner arrays
        foreach ($grouped_hooks as $group => &$hooks) {
            if (empty($hooks)) {
                unset($grouped_hooks[$group]);
                continue;
            }
            $hooks = array_unique($hooks);
            sort($hooks);
        }

        // Sort keys alphabetically but keep Core at the top
        $core_group = isset($grouped_hooks['WordPress Core']) ? ['WordPress Core' => $grouped_hooks['WordPress Core']] : [];
        unset($grouped_hooks['WordPress Core']);
        ksort($grouped_hooks);
        $grouped_hooks = array_merge($core_group, $grouped_hooks);

        return $grouped_hooks;
    }
}
