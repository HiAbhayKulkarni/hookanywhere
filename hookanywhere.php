<?php
/**
 * Plugin Name:       HookAnywhere - Trigger Any Action, Send Anywhere
 * Description:       HookAnywhere connects WordPress to the outside world by triggering any action hook and sending data to virtually any API or webhook endpoint.
 * Version:           1.0.1
 * Author:            Abhay Kulkarni | abhay.co
 * Author URI:        https://abhay.co
 * Text Domain:       hookanywhere
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package           HookAnywhere
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Current plugin version.
 */
define('HOOKAW_VERSION', '1.0.1');
define('HOOKAW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HOOKAW_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-hookaw-helpers.php';
require plugin_dir_path(__FILE__) . 'includes/class-hookaw-admin.php';
require plugin_dir_path(__FILE__) . 'includes/class-hookaw-cpt.php';
require plugin_dir_path(__FILE__) . 'includes/class-hookaw-dispatcher.php';

/**
 * Begins execution of the plugin.
 */
function hookaw_run_hookanywhere()
{

	$plugin_admin = new HOOKAW_Admin();
	$plugin_admin->init();

	$plugin_cpt = new HOOKAW_CPT();
	$plugin_cpt->init();

	$plugin_dispatcher = new HOOKAW_Dispatcher();
	$plugin_dispatcher->init();

	// Schedule daily log cleanup cron (Fallback check if activation hook didn't run)
	if (!wp_next_scheduled('hookaw_cleanup_logs_cron')) {
		wp_schedule_event(time(), 'daily', 'hookaw_cleanup_logs_cron');
	}
	add_action('hookaw_cleanup_logs_cron', 'hookaw_execute_log_cleanup');

	// Handle CSV Export
	add_action('admin_post_hookaw_export_logs', 'hookaw_handle_log_export');

}

/**
 * Activation hook: Schedule the daily maintenance cron.
 */
function hookaw_activate_plugin()
{
	if (!wp_next_scheduled('hookaw_cleanup_logs_cron')) {
		wp_schedule_event(time(), 'daily', 'hookaw_cleanup_logs_cron');
	}

	// Flush rewrite rules for our CPTs
	$plugin_cpt = new HOOKAW_CPT();
	$plugin_cpt->register_post_type();
	$plugin_cpt->register_log_post_type();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'hookaw_activate_plugin');

/**
 * Deactivation hook: Clear the scheduled maintenance cron.
 */
function hookaw_deactivate_plugin()
{
	wp_clear_scheduled_hook('hookaw_cleanup_logs_cron');
	flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'hookaw_deactivate_plugin');

hookaw_run_hookanywhere();

/**
 * Executes the daily log cleanup based on the retention setting.
 */
function hookaw_execute_log_cleanup()
{
	$retention_days = absint(get_option('hookaw_log_retention_days', 30));
	if ($retention_days <= 0)
		return;

	$cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

	$args = [
		'post_type' => 'hookaw_log',
		'post_status' => 'any',
		'posts_per_page' => -1,
		'date_query' => [
			[
				'before' => $cutoff_date,
				'inclusive' => true,
			],
		],
		'fields' => 'ids',
		'no_found_rows' => true
	];

	$old_logs = get_posts($args);

	foreach ($old_logs as $log_id) {
		wp_delete_post($log_id, true); // Force delete permanently
	}
}

/**
 * Handles the "Export Logs to CSV" button.
 */
function hookaw_handle_log_export()
{
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to export logs.', 'hookanywhere'));
	}

	check_admin_referer('hookaw_export_logs_nonce', 'hookaw_export_nonce');

	$args = [
		'post_type' => 'hookaw_log',
		'post_status' => 'any',
		'posts_per_page' => -1,
	];

	$logs = get_posts($args);

	// Clean all output buffers to ensure no extra whitespace or HTML corrupts the CSV
	while (ob_get_level()) {
		ob_end_clean();
	}

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="hookaw_logs_' . gmdate('Y-m-d_H-i') . '.csv"');
	header('Pragma: no-cache');
	header('Expires: 0');

	$output = fopen('php://output', 'w');
	// Add UTF-8 BOM for proper Excel compatibility
	echo "\xEF\xBB\xBF";

	fputcsv($output, ['Webhook Title', 'Target URL', 'Request Method', 'Timestamp', 'Request Data', 'Response Body', 'Response Headers']);

	foreach ($logs as $log) {
		$timestamp = get_post_datetime($log)->format('Y-m-d H:i:s');
		$url = get_post_meta($log->ID, '_hookaw_request_url', true);
		$method = get_post_meta($log->ID, '_hookaw_request_method', true);
		$webhook_id = get_post_meta($log->ID, '_hookaw_webhook_id', true);
		$request_data = get_post_meta($log->ID, '_hookaw_request_args', true);

		if (is_array($request_data) || is_object($request_data)) {
			$request_data = wp_json_encode($request_data);
		}

		$response_body = get_post_meta($log->ID, '_hookaw_response_body', true);
		$response_headers = get_post_meta($log->ID, '_hookaw_response_headers', true);

		if (is_array($response_headers) || is_object($response_headers)) {
			$response_headers = wp_json_encode($response_headers);
		}

		$webhook_title = 'Deleted Webhook';
		if ($webhook_id) {
			$webhook_post = get_post($webhook_id);
			if ($webhook_post && $webhook_post->post_type === 'hookaw') {
				$webhook_title = $webhook_post->post_title;
			}
		}

		fputcsv($output, [
			$webhook_title,
			$url,
			$method,
			$timestamp,
			$request_data,
			$response_body,
			$response_headers
		]);
	}

	exit;
}
