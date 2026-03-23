<?php
/**
 * Dispatcher logic for HookAnywhere.
 *
 * @package HookAnywhere
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dispatcher Class
 */
class HOOKAW_Dispatcher
{

    /**
     * Option name (legacy).
     *
     * @var string
     */
    private $option_name = 'hookaw_webhooks';

    /**
     * Prevents infinite loops when dispatching webhooks triggers internal actions like save_post.
     *
     * @var bool
     */
    private static $is_logging = false;

    /**
     * Initialize the dispatcher by reading active webhooks and attaching hooks.
     */
    public function init()
    {
        // Query all active webhook posts
        $args = [
            'post_type' => 'hookaw',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids', // We only need the IDs to check meta
        ];

        $webhook_query = new WP_Query($args);

        if (!$webhook_query->have_posts()) {
            return;
        }

        // Keep track of hooks we've already attached to avoid duplicates
        $attached_hooks = [];

        foreach ($webhook_query->posts as $post_id) {
            $hook_name = get_post_meta($post_id, '_hookaw_hook_name', true);
            $url = get_post_meta($post_id, '_hookaw_url', true);

            // Skip if missing critical data
            if (empty($hook_name) || empty($url)) {
                continue;
            }

            // Attach to the dynamic hook if we haven't already
            if (!in_array($hook_name, $attached_hooks, true)) {
                // We use 99 priority to run late and 99 accepted args to catch as many as possible
                add_action($hook_name, [$this, 'dispatch_webhook'], 99, 99);
                $attached_hooks[] = $hook_name;
            }
        }
    }

    /**
     * Callback function that actually formats and sends the webhook payload.
     *
     * @return void
     */
    public function dispatch_webhook()
    {
        // Prevent infinite loops triggered by our own internal logging operations
        if (self::$is_logging) {
            return;
        }

        self::$is_logging = true;

        $current_filter = current_filter();
        $args = func_get_args();

        // Redact sensitive data (passwords, etc.) from hook arguments
        $this->mask_sensitive_data($args);

        // Query webhooks that are assigned to this specific hook
        $query_args = [
            'post_type' => 'hookaw',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query' => [
                [
                    'key' => '_hookaw_hook_name',
                    'value' => $current_filter,
                    'compare' => '='
                ]
            ]
        ];

        $webhook_query = new WP_Query($query_args);

        if (!$webhook_query->have_posts()) {
            self::$is_logging = false;
            return;
        }

        $base_payload = [
            'hook' => $current_filter,
            'data' => $args,
            'time' => current_time('mysql'),
        ];

        foreach ($webhook_query->posts as $post) {
            $post_id = $post->ID;

            $url = get_post_meta($post_id, '_hookaw_url', true);
            $method = get_post_meta($post_id, '_hookaw_method', true) ?: 'POST';
            $auth_type = get_post_meta($post_id, '_hookaw_auth_type', true) ?: 'none';
            $auth_credentials = get_post_meta($post_id, '_hookaw_auth_credentials', true) ?: [];

            $is_active = get_post_meta($post_id, '_hookaw_is_active', true);
            $timeout = get_post_meta($post_id, '_hookaw_timeout', true);
            $timeout = (empty($timeout) || !is_numeric($timeout)) ? 5 : intval($timeout);

            if (empty($url) || $is_active === 'no') {
                continue; // Skip misconfigured or inactive webhooks
            }

            // Base headers
            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Apply Authentication Logic
            if ($auth_type === 'basic') {
                $user = $auth_credentials['basic_user'] ?? '';
                $pass = $auth_credentials['basic_pass'] ?? '';
                if (!empty($user)) {
                    $headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $pass);
                }
            } elseif ($auth_type === 'bearer') {
                $token = $auth_credentials['bearer_token'] ?? '';
                if (!empty($token)) {
                    $headers['Authorization'] = 'Bearer ' . $token;
                }
            } elseif ($auth_type === 'header') {
                $header_name = $auth_credentials['header_name'] ?? '';
                $header_value = $auth_credentials['header_value'] ?? '';
                if (!empty($header_name)) {
                    $headers[sanitize_text_field($header_name)] = sanitize_text_field($header_value);
                }
            } elseif ($auth_type === 'query') {
                $query_name = $auth_credentials['query_name'] ?? '';
                $query_value = $auth_credentials['query_value'] ?? '';
                if (!empty($query_name)) {
                    $url = add_query_arg($query_name, $query_value, $url);
                }
            }

            // Apply Custom Headers
            $custom_headers = get_post_meta($post_id, '_hookaw_headers', true) ?: [];
            if (is_array($custom_headers) && !empty($custom_headers)) {
                foreach ($custom_headers as $header) {
                    if (!empty($header['key']) && !empty($header['value'])) {
                        $headers[sanitize_text_field($header['key'])] = sanitize_text_field($header['value']);
                    }
                }
            }

            // Prepare Payload
            $payload = $base_payload;

            // Append Custom Body Parameters
            $body_params = get_post_meta($post_id, '_hookaw_body_params', true) ?: [];
            if (is_array($body_params) && !empty($body_params)) {
                foreach ($body_params as $param) {
                    if (!empty($param['key'])) {
                        $payload[sanitize_text_field($param['key'])] = sanitize_text_field($param['value']);
                    }
                }
            }

            $json_payload = wp_json_encode($payload);

            // Check for Custom Body
            $custom_body_template = get_post_meta($post_id, '_hookaw_body', true);
            $final_payload = $json_payload;

            if (!empty($custom_body_template)) {
                // If a user defined a custom JSON structure, we pass that instead.
                // In a future advanced version, we could parse the payload data into the JSON string
                // via token replacement, but for V1 CPT we will just send their raw custom body.
                $final_payload = wp_unslash($custom_body_template);
            }

            $request_args = [
                'method' => $method,
                'timeout' => $timeout, // Uses user-defined timeout
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true, // Changed to true to receive response for logging
                'headers' => $headers,
                'cookies' => [],
            ];

            if ('GET' === $method) {
                // For GET, attach payload data as a query string parameter instead of body
                $url = add_query_arg('hookaw_payload', rawurlencode($final_payload), $url);
            } else {
                // For POST, PUT, PATCH, DELETE, send the JSON payload in the body
                $request_args['body'] = $final_payload;
            }

            // Execute blocking request
            $response = wp_remote_request($url, $request_args);

            // Log Request
            $this->log_request($post_id, $url, $method, $request_args, $response);
        }
        
        self::$is_logging = false;
    }

    /**
     * Log the webhook request and response to the hookaw_log Custom Post Type
     */
    private function log_request($webhook_id, $url, $method, $request_args, $response)
    {
        $log_title = sprintf('Log: %s - %s', current_time('mysql'), $method);

        $log_data = [
            'post_type' => 'hookaw_log',
            'post_title' => $log_title,
            'post_status' => 'publish',
        ];

        $log_id = wp_insert_post($log_data);

        if ($log_id && !is_wp_error($log_id)) {
            update_post_meta($log_id, '_hookaw_webhook_id', $webhook_id);
            update_post_meta($log_id, '_hookaw_request_url', $url);
            update_post_meta($log_id, '_hookaw_request_method', $method);

            // To prevent double JSON-encoded strings breaking the log viewer array decoding,
            // we will parse the body back to an array/object if it's a JSON string before saving it
            $log_request_args = $request_args;
            if (isset($log_request_args['body']) && is_string($log_request_args['body'])) {
                $decoded_body = json_decode($log_request_args['body'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $log_request_args['body'] = $decoded_body;
                }
            }

            update_post_meta($log_id, '_hookaw_request_args', wp_json_encode($log_request_args));

            if (is_wp_error($response)) {
                update_post_meta($log_id, '_hookaw_response_status', 'ERROR');
                update_post_meta($log_id, '_hookaw_response_error', $response->get_error_message());
            } else {
                update_post_meta($log_id, '_hookaw_response_status', wp_remote_retrieve_response_code($response));

                $headers = wp_remote_retrieve_headers($response);
                if (is_object($headers) && method_exists($headers, 'getAll')) {
                    $headers = $headers->getAll();
                } elseif (is_object($headers) && is_callable([$headers, 'getIterator'])) {
                    $headers = iterator_to_array($headers);
                } else {
                    $headers = (array) $headers;
                }

                update_post_meta($log_id, '_hookaw_response_headers', wp_json_encode($headers));
                update_post_meta($log_id, '_hookaw_response_body', wp_remote_retrieve_body($response));
            }
        }
    }

    /**
     * Recursively mask sensitive keys in an array or object.
     *
     * @param mixed $data The data to mask (passed by reference).
     */
    private function mask_sensitive_data(&$data)
    {
        $sensitive_keys = [
            'user_pass',
            'password',
            'pass',
            'pwd',
            'secret',
            'token',
            'api_key',
            'key',
            'auth',
            'authorization'
        ];

        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => &$value) {
                if (is_string($key) && in_array(strtolower($key), $sensitive_keys, true)) {
                    $value = '[REDACTED]';
                } else {
                    $this->mask_sensitive_data($value);
                }
            }
        }
    }
}
