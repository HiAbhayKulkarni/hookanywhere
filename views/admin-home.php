<?php
/**
 * Admin View: Home / Welcome Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$hookaw_is_onboarded = get_option('hookaw_welcome_completed', false);
$current_user = wp_get_current_user();

// Fetch dynamic stats
$hookaw_active_webhooks_query = new WP_Query([
    'post_type' => 'hookaw',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    'meta_query' => [
        [
            'key' => '_hookaw_is_active',
            'value' => 'yes',
        ],
    ],
]);
$hookaw_active_webhooks_count = $hookaw_active_webhooks_query->post_count;

$hookaw_success_count_query = new WP_Query([
    'post_type' => 'hookaw_log',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    'meta_query' => [
        [
            'key' => '_hookaw_response_status',
            'value' => '2',
            'compare' => 'LIKE',
        ],
    ],
]);
$hookaw_success_count = $hookaw_success_count_query->post_count;

$hookaw_failed_count_query = new WP_Query([
    'post_type' => 'hookaw_log',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    'meta_query' => [
        [
            'key' => '_hookaw_response_status',
            'value' => '2',
            'compare' => 'NOT LIKE',
        ],
    ],
]);
$hookaw_failed_count = $hookaw_failed_count_query->post_count;
?>
<!DOCTYPE html>

<div
    style="display: flex; justify-content: center; align-items: center; min-height: calc(100vh - 100px); box-sizing: border-box; width: 100%;">
    <div class="wrap hookaw-wrap hookaw-dashboard-wrap"
        style="width: 100%; max-width: 1200px; padding: 0 40px; box-sizing: border-box; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

        <!-- Header Block (Transparent) -->
        <div style="text-align: center; margin-bottom: 48px;">
            <h1 style="margin: 0 0 12px 0; font-size: 32px; font-weight: 700; color: #111827; letter-spacing: -0.02em;">
                <?php esc_html_e('HookAnywhere', 'hookanywhere'); ?>
            </h1>
            <p style="margin: 0 0 28px 0; font-size: 16px; color: #6b7280;">
                <?php esc_html_e('Trigger Any Action, Send Anywhere', 'hookanywhere'); ?>
            </p>
            <div style="display: flex; gap: 16px; justify-content: center;">
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=hookaw')); ?>"
                    style="background-color: #2271b1; color: #fff; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(34, 113, 177, 0.25); transition: background-color 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" style="width: 16px; height: 16px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <?php esc_html_e('Add New Webhook', 'hookanywhere'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=hookaw')); ?>"
                    style="background-color: #fff; color: #374151; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid #d1d5db; transition: background-color 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                    <?php esc_html_e('All Webhooks', 'hookanywhere'); ?>
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px;">
            <!-- Stat 1: Active Webhooks -->
            <div
                style="background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
                <div
                    style="background-color: #f0f6fc; color: #2271b1; width: 64px; height: 64px; border-radius: 16px; display: flex; justify-content: center; align-items: center; flex-shrink: 0;">
                    <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB2aWV3Qm94PSIwLDAsMjU2LDI1NiIgd2lkdGg9IjI0cHgiIGhlaWdodD0iMjRweCIgZmlsbC1ydWxlPSJub256ZXJvIj48ZyBmaWxsPSIjMjI3MWIxIiBmaWxsLXJ1bGU9Im5vbnplcm8iIHN0cm9rZT0ibm9uZSIgc3Ryb2tlLXdpZHRoPSIxIiBzdHJva2UtbGluZWNhcD0iYnV0dCIgc3Ryb2tlLWxpbmVqb2luPSJtaXRlciIgc3Ryb2tlLW1pdGVybGltaXQ9IjEwIiBzdHJva2UtZGFzaGFycmF5PSIiIHN0cm9rZS1kYXNob2Zmc2V0PSIwIiBmb250LWZhbWlseT0ibm9uZSIgZm9udC13ZWlnaHQ9Im5vbmUiIGZvbnQtc2l6ZT0ibm9uZSIgdGV4dC1hbmNob3I9Im5vbmUiIHN0eWxlPSJtaXgtYmxlbmQtbW9kZTogbm9ybWFsIj48ZyB0cmFuc2Zvcm09InNjYWxlKDEwLjY2NjY3LDEwLjY2NjY3KSI+PHBhdGggZD0iTTExLjY1NjI1LDIuMzQzNzVjLTEuNjAxNTYsMC4xMTMyOCAtMy4xNDA2MiwxLjAwNzgxIC00LDIuNWMtMS4yMDcwMywyLjA4OTg0IC0wLjcxMDk0LDQuNzEwOTQgMS4wMzEyNSw2LjI1bC0xLjY4NzUsMi45MDYyNWMtMC42ODc1LDAuMDAzOTEgLTEuMzUxNTYsMC4zNTkzOCAtMS43MTg3NSwxYy0wLjU1MDc4LDAuOTU3MDMgLTAuMjM4MjgsMi4xNjQwNiAwLjcxODc1LDIuNzE4NzVjMC45NTcwMywwLjU1MDc4IDIuMTY3OTcsMC4yMzgyOCAyLjcxODc1LC0wLjcxODc1YzAuMzY3MTksLTAuNjQwNjIgMC4zNDM3NSwtMS40MDIzNCAwLC0ybDIuNjU2MjUsLTQuNTYyNWwtMC44NzUsLTAuNWMtMS40MzM1OSwtMC44MjgxMiAtMS45MjE4NywtMi42NjAxNiAtMS4wOTM3NSwtNC4wOTM3NWMwLjgyODEzLC0xLjQzMzU5IDIuNjYwMTYsLTEuOTIxODcgNC4wOTM3NSwtMS4wOTM3NWMxLjQzMzU5LDAuODI4MTMgMS45MjE4OCwyLjY2MDE2IDEuMDkzNzUsNC4wOTM3NWwxLjc1LDFjMS4zNzg5MSwtMi4zODY3MiAwLjU0Mjk3LC01LjQ2NDg0IC0xLjg0Mzc1LC02Ljg0Mzc1Yy0wLjg5NDUzLC0wLjUxNTYyIC0xLjg4MjgxLC0wLjcyMjY2IC0yLjg0Mzc1LC0wLjY1NjI1ek0xMS43NSw1LjM0Mzc1Yy0wLjI1NzgxLDAuMDMxMjUgLTAuNTExNzIsMC4xMTMyOCAtMC43NSwwLjI1Yy0wLjk1NzAzLDAuNTUwNzggLTEuMjY5NTMsMS43OTI5NyAtMC43MTg3NSwyLjc1YzAuMzY3MTksMC42NDA2MyAxLjAzMTI1LDAuOTk2MDkgMS43MTg3NSwxbDIuNjI1LDQuNTYyNWwwLjg3NSwtMC41YzEuNDMzNTksLTAuODI4MTIgMy4yNjU2MywtMC4zMzk4NCA0LjA5Mzc1LDEuMDkzNzVjMC44MjgxMywxLjQzMzU5IDAuMzM5ODQsMy4yNjU2MyAtMS4wOTM3NSw0LjA5Mzc1Yy0xLjQzMzU5LDAuODI4MTMgLTMuMjY1NjIsMC4zMzk4NCAtNC4wOTM3NSwtMS4wOTM3NWwtMS43NSwxYzEuMzc4OTEsMi4zODY3MiA0LjQ1NzAzLDMuMjIyNjYgNi44NDM3NSwxLjg0Mzc1YzIuMzg2NzIsLTEuMzc4OTEgMy4yMjI2NiwtNC40NTcwMyAxLjg0Mzc1LC02Ljg0Mzc1Yy0xLjIwNzAzLC0yLjA4OTg0IC0zLjczMDQ3LC0yLjk4ODI4IC01LjkzNzUsLTIuMjVsLTEuNjg3NSwtMi45MDYyNWMwLjM0Mzc1LC0wLjU5NzY2IDAuMzY3MTksLTEuMzU5MzcgMCwtMmMtMC40MTQwNiwtMC43MTg3NSAtMS4xOTUzMSwtMS4wOTc2NiAtMS45Njg3NSwtMXpNNywxMWMtMi43NTc4MSwwIC01LDIuMjQyMTkgLTUsNWMwLDIuNzU3ODEgMi4yNDIxOSw1IDUsNWMyLjQxNDA2LDAgNC40NDE0MSwtMS43MjI2NiA0LjkwNjI1LC00aDMuMzc1YzAuMzQ3NjYsMC41OTM3NSAwLjk4MDQ3LDEgMS43MTg3NSwxYzEuMTA1NDcsMCAyLC0wLjg5NDUzIDIsLTJjMCwtMS4xMDU0NyAtMC44OTQ1MywtMiAtMiwtMmMtMC43MzgyOCwwIC0xLjM3MTA5LDAuNDA2MjUgLTEuNzE4NzUsMWgtNS4yODEyNXYxYzAsMS42NTIzNCAtMS4zNDc2NiwzIC0zLDNjLTEuNjUyMzQsMCAtMywtMS4zNDc2NiAtMywtM3MwLC0xLjY1MjM0IDEuMzQ3NjYsLTMgMywtM3oiPjwvcGF0aD48L2c+PC9nPjwvc3ZnPg=="
                        style="width: 28px; height: 28px;"
                        alt="<?php esc_attr_e('Active Webhooks', 'hookanywhere'); ?>">
                </div>
                <div>
                    <p
                        style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php esc_html_e('Active Webhooks', 'hookanywhere'); ?>
                    </p>
                    <div style="font-size: 32px; font-weight: 700; color: #111827; line-height: 1;">
                        <?php echo esc_html($hookaw_active_webhooks_count); ?>
                    </div>
                </div>
            </div>

            <!-- Stat 2: Successful Calls -->
            <div
                style="background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
                <div
                    style="background-color: #ecfdf5; color: #10b981; width: 64px; height: 64px; border-radius: 16px; display: flex; justify-content: center; align-items: center; flex-shrink: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                        stroke="currentColor" style="width: 28px; height: 28px;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <p
                        style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php esc_html_e('Successful Calls', 'hookanywhere'); ?>
                    </p>
                    <div style="font-size: 32px; font-weight: 700; color: #111827; line-height: 1;">
                        <?php echo esc_html($hookaw_success_count); ?>
                    </div>
                </div>
            </div>

            <!-- Stat 3: Failed Calls -->
            <div
                style="background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
                <div
                    style="background-color: #fef2f2; color: #ef4444; width: 64px; height: 64px; border-radius: 16px; display: flex; justify-content: center; align-items: center; flex-shrink: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                        stroke="currentColor" style="width: 28px; height: 28px;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <p
                        style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">
                        <?php esc_html_e('Failed Calls', 'hookanywhere'); ?>
                    </p>
                    <div style="font-size: 32px; font-weight: 700; color: #111827; line-height: 1;">
                        <?php echo esc_html($hookaw_failed_count); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscription Form -->
        <div
            style="background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 32px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
            <!-- Header inside form -->
            <div style="display: flex; gap: 16px; align-items: flex-start; margin-bottom: 32px;">
                <div
                    style="background-color: #f3f4f6; color: #4b5563; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" style="width: 24px; height: 24px;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                </div>
                <div style="padding-top: 2px;">
                    <h2 style="margin: 0 0 6px 0; font-size: 18px; font-weight: 700; color: #111827;">
                        <?php esc_html_e('Stay Updated', 'hookanywhere'); ?>
                    </h2>
                    <p style="margin: 0; font-size: 14px; color: #6b7280;">
                        <?php esc_html_e('Subscribe to our email newsletter to receive important updates about this plugin, tips, and special announcements.', 'hookanywhere'); ?>
                    </p>
                </div>
            </div>

            <!-- Form -->
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="hookaw_welcome_submit">
                <?php wp_nonce_field('hookaw_welcome_nonce_action', 'hookaw_welcome_nonce'); ?>

                <?php
                $hookaw_display_first_name = get_option('hookaw_subscribed_firstname') ?: $current_user->user_firstname;
                $hookaw_display_last_name = get_option('hookaw_subscribed_lastname') ?: $current_user->user_lastname;
                $hookaw_display_email = get_option('hookaw_subscribed_email') ?: $current_user->user_email;
                ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                    <div>
                        <label for="first_name"
                            style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;"><?php esc_html_e('First Name', 'hookanywhere'); ?></label>
                        <input type="text" id="first_name" name="first_name"
                            value="<?php echo esc_attr($hookaw_display_first_name); ?>" required <?php disabled($hookaw_is_onboarded); ?>
                            style="width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #111827; background: #fff; box-shadow: none;">
                    </div>
                    <div>
                        <label for="last_name"
                            style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;"><?php esc_html_e('Last Name', 'hookanywhere'); ?></label>
                        <input type="text" id="last_name" name="last_name"
                            value="<?php echo esc_attr($hookaw_display_last_name); ?>" required <?php disabled($hookaw_is_onboarded); ?>
                            style="width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #111827; background: #fff; box-shadow: none;">
                    </div>
                </div>

                <div style="margin-bottom: 32px;">
                    <label for="email"
                        style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;"><?php esc_html_e('Email Address', 'hookanywhere'); ?></label>
                    <input type="email" id="email" name="email" value="<?php echo esc_attr($hookaw_display_email); ?>"
                        required <?php disabled($hookaw_is_onboarded); ?>
                        style="width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; font-size: 14px; color: #111827; background: #fff; box-shadow: none;">
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <?php if ($hookaw_is_onboarded): ?>
                        <div style="display: flex; gap: 16px;">
                            <button type="button" disabled
                                style="background-color: #e5e7eb; color: #9ca3af; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: not-allowed;">
                                <?php esc_html_e('Subscribed', 'hookanywhere'); ?>
                            </button>
                            <span style="font-size: 12px; color: #9ca3af; display: flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-yes-alt"
                                    style="color: #10b981; font-size: 16px; width: 16px; height: 16px;"></span>
                                <?php esc_html_e('Thank you for subscribing!', 'hookanywhere'); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <button type="submit"
                            style="background-color: #2271b1; color: #fff; padding: 12px 32px; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: background-color 0.2s;">
                            <?php esc_html_e('Submit & Subscribe', 'hookanywhere'); ?>
                        </button>
                        <span style="font-size: 12px; color: #9ca3af; display: flex; align-items: center; gap: 6px;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor" style="width: 14px; height: 14px;">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <?php esc_html_e('We respect your privacy. Your data is not sold and you will not be spammed.', 'hookanywhere'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    </div>
</div>