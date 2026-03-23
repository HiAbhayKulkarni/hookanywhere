<?php
/**
 * View: Settings Page
 *
 * @package HookAnywhere
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap hookaw-wrap hookaw-dashboard-wrap"
    style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <form method="post" action="options.php" id="hookaw-settings-form">
        <?php settings_fields('hookaw_settings_group'); ?>
        <?php do_settings_sections('hookaw_settings_group'); ?>

        <!-- Header -->
        <h1 class="wp-heading-inline">
            <?php esc_html_e('Settings', 'hookanywhere'); ?>
        </h1>
        <hr class="wp-header-end">

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-1">
                <div id="postbox-container-1" class="postbox-container" style="padding-bottom: 10px;">

                    <!-- Card 1: Log Retention -->
                    <div class="postbox" style="margin-bottom: 20px;">
                        <div class="postbox-header">
                            <h2 class="hndle" style="padding: 12px 15px; margin: 0; font-size: 14px;">
                                <?php esc_html_e('Log Retention', 'hookanywhere'); ?>
                            </h2>
                        </div>
                        <div class="inside" style="padding: 12px 12px 20px;">
                            <p class="description">
                                <?php esc_html_e('Define how many days webhook logs should be kept in the database before being automatically deleted by the daily maintenance task.', 'hookanywhere'); ?>
                            </p>
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row">
                                            <label for="hookaw_log_retention_days">
                                                <?php esc_html_e('Retention Period (Days)', 'hookanywhere'); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="number" id="hookaw_log_retention_days"
                                                name="hookaw_log_retention_days" class="regular-text"
                                                value="<?php echo esc_attr(get_option('hookaw_log_retention_days', 30)); ?>"
                                                min="1" step="1">
                                            <p class="description">
                                                <?php esc_html_e('Default: 30 days. Logs older than this threshold will be permanently deleted.', 'hookanywhere'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Card 3: Access Control -->
                    <div class="postbox" style="margin-bottom: 20px;">
                        <div class="postbox-header">
                            <h2 class="hndle" style="padding: 12px 15px; margin: 0; font-size: 14px;">
                                <?php esc_html_e('Access Control', 'hookanywhere'); ?>
                            </h2>
                        </div>
                        <div class="inside" style="padding: 12px 12px 20px;">
                            <p class="description" style="margin-bottom: 15px;">
                                <?php esc_html_e('Select which user roles are allowed to access and manage HookAnywhere. The Administrator role always has access.', 'hookanywhere'); ?>
                            </p>

                            <?php
                            $hookaw_allowed_roles = get_option('hookaw_allowed_roles', []);
                            if (!is_array($hookaw_allowed_roles)) {
                                $hookaw_allowed_roles = [];
                            }
                            
                            global $wp_roles;
                            if ( ! isset( $wp_roles ) ) {
                                $wp_roles = new WP_Roles();
                            }
                            $hookaw_roles = $wp_roles->role_names;
                            ?>

                            <div style="background: #fcfcfc; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; max-height: 250px; overflow-y: auto;">
                                <?php foreach ($hookaw_roles as $hookaw_role_slug => $hookaw_role_name): ?>
                                    <?php
                                    $hookaw_is_admin = ($hookaw_role_slug === 'administrator');
                                    $hookaw_is_checked = $hookaw_is_admin || in_array($hookaw_role_slug, $hookaw_allowed_roles);
                                    ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="hookaw_allowed_roles[]" value="<?php echo esc_attr($hookaw_role_slug); ?>"
                                            <?php checked($hookaw_is_checked); ?>
                                            <?php disabled($hookaw_is_admin); ?> />
                                        <?php echo esc_html(translate_user_role($hookaw_role_name)); ?>
                                        
                                        <?php if ($hookaw_is_admin) : ?>
                                            <span style="color: #646970; font-size: 12px; margin-left: 5px;">(<?php esc_html_e('Always allowed', 'hookanywhere'); ?>)</span>
                                            <input type="hidden" name="hookaw_allowed_roles[]" value="<?php echo esc_attr($hookaw_role_slug); ?>">
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <p class="submit" style="margin-top: 20px; padding-top: 0;">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Settings', 'hookanywhere'); ?>
                        </button>
                    </p>

                </div>
            </div>
        </div>
    </form>



</div>