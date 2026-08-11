<?php
/**
 * Headless Custom REST API Endpoints (/ruut/v1/*) for user profile, authentication, order tracking, products, and announcements
 * Extracted from ruut-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register Headless REST Routes
if (!function_exists('ruut_register_api_routes')) {
    add_action('rest_api_init', 'ruut_register_api_routes');
    function ruut_register_api_routes() {
        register_rest_route('ruut/v1', '/send-otp', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_send_otp',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/verify-otp', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_verify_otp',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/user', array(
            'methods' => 'GET',
            'callback' => 'ruut_api_get_user',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/user', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_update_user',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/address', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_save_address',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/address', array(
            'methods' => 'DELETE',
            'callback' => 'ruut_api_delete_address',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/logout', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_logout',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/track-order', array(
            'methods' => 'GET',
            'callback' => 'ruut_api_track_order',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/user-orders', array(
            'methods' => 'GET',
            'callback' => 'ruut_api_get_user_orders',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/announcement', array(
            'methods' => 'GET',
            'callback' => 'ruut_get_announcement',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/shiprocket-test', array(
            'methods' => 'GET',
            'callback' => 'ruut_api_shiprocket_test',
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ));
        // Webhook endpoint for delivery partner tracking updates
        // NOTE: URL must NOT contain 'shiprocket', 'sr', 'kr', or 'kartrocket'
        register_rest_route('ruut/v1', '/delivery-hook', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_delivery_webhook',
            'permission_callback' => '__return_true',
        ));
    }
}

if (!function_exists('ruut_get_announcement')) {
    function ruut_get_announcement() {
        $text = get_option('ruut_announcement_text', '✨ EXPLORE THE ATLAS COLLECTION ✨ | 🌿 FREE SHIPPING ON ALL ORDERS ABOVE ₹1500 🌿');
        $messages = explode('|', $text);
        $messages = array_map('trim', $messages);
        
        $threshold = intval(get_option('ruut_reward_threshold', '3'));
        $amount = get_option('ruut_reward_amount', '₹250');
        
        return new WP_REST_Response(array(
            'messages' => $messages,
            'reward_threshold' => $threshold,
            'reward_amount' => $amount
        ), 200);
    }
}

// Diagnostic endpoint: GET /wp-json/ruut/v1/shiprocket-test?order_id=24072641 (admin only)
if (!function_exists('ruut_api_shiprocket_test')) {
    function ruut_api_shiprocket_test($request) {
        $order_id = sanitize_text_field($request->get_param('order_id'));
        if (empty($order_id)) {
            return new WP_REST_Response(array('error' => 'Please provide ?order_id= parameter'), 400);
        }

        $api_key = get_option('ruut_shiprocket_api_key', '');
        $email = get_option('ruut_shiprocket_email', '');
        $password = get_option('ruut_shiprocket_password', '');

        $diagnostics = array(
            'input_order_id' => $order_id,
            'token_present' => !empty($api_key),
            'token_first_10' => $api_key ? substr($api_key, 0, 10) . '...' : '(empty)',
            'email_set' => !empty($email),
            'password_set' => !empty($password),
        );

        // Step 1: Try current token
        if (!empty($api_key)) {
            $test_url = 'https://apiv2.shiprocket.in/v1/external/courier/track?order_id=' . urlencode($order_id);
            $result = ruut_shiprocket_api_get($test_url, $api_key);
            $diagnostics['direct_call'] = array(
                'url' => $test_url,
                'http_code' => isset($result['code']) ? $result['code'] : 'error',
                'response' => isset($result['body']) ? $result['body'] : (isset($result['wp_error']) ? $result['wp_error'] : 'unknown error'),
            );

            // Also try channel_order_id
            $test_url2 = 'https://apiv2.shiprocket.in/v1/external/courier/track?channel_order_id=' . urlencode($order_id);
            $result2 = ruut_shiprocket_api_get($test_url2, $api_key);
            $diagnostics['channel_order_call'] = array(
                'url' => $test_url2,
                'http_code' => isset($result2['code']) ? $result2['code'] : 'error',
                'response' => isset($result2['body']) ? $result2['body'] : (isset($result2['wp_error']) ? $result2['wp_error'] : 'unknown error'),
            );
        }

        // Step 2: If 401, try auto-refresh
        if (!empty($api_key) && isset($result['code']) && $result['code'] === 401) {
            $diagnostics['token_expired'] = true;
            $new_token = ruut_refresh_shiprocket_token();
            $diagnostics['auto_refresh_result'] = $new_token ? 'SUCCESS - new token: ' . substr($new_token, 0, 10) . '...' : 'FAILED (check email/password in RUUT Settings)';

            if ($new_token) {
                $result3 = ruut_shiprocket_api_get($test_url, $new_token);
                $diagnostics['retry_after_refresh'] = array(
                    'http_code' => isset($result3['code']) ? $result3['code'] : 'error',
                    'response' => isset($result3['body']) ? $result3['body'] : 'error',
                );
            }
        }

        // Step 3: Full tracking attempt
        $tracking_data = ruut_fetch_shiprocket_tracking(
            get_option('ruut_shiprocket_api_key', ''),
            '', '', $order_id
        );
        $diagnostics['full_tracking_result'] = $tracking_data ?: 'No tracking data returned';

        return new WP_REST_Response($diagnostics, 200);
    }
}

// Auto-refresh Shiprocket token via /auth/login when expired
if (!function_exists('ruut_refresh_shiprocket_token')) {
    function ruut_refresh_shiprocket_token() {
        $email = get_option('ruut_shiprocket_email', '');
        $password = get_option('ruut_shiprocket_password', '');

        if (empty($email) || empty($password)) {
            return false;
        }

        $res = wp_remote_post('https://apiv2.shiprocket.in/v1/external/auth/login', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array('email' => $email, 'password' => $password)),
            'timeout' => 15
        ));

        if (is_wp_error($res)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);

        if ($code === 200 && !empty($body['token'])) {
            update_option('ruut_shiprocket_api_key', $body['token']);
            return $body['token'];
        }

        return false;
    }
}

if (!function_exists('ruut_shiprocket_api_get')) {
    function ruut_shiprocket_api_get($url, $api_key) {
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . trim($api_key)
        );
        $res = wp_remote_get($url, array('headers' => $headers, 'timeout' => 15));

        if (is_wp_error($res)) {
            return array('error' => true, 'wp_error' => $res->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);

        return array('code' => $code, 'body' => $body);
    }
}

if (!function_exists('ruut_fetch_shiprocket_tracking')) {
    function ruut_fetch_shiprocket_tracking($api_key, $awb_code = '', $shipment_id = '', $order_id = '') {
        if (empty($api_key)) {
            // Try auto-refresh if no token
            $api_key = ruut_refresh_shiprocket_token();
            if (!$api_key) {
                return false;
            }
        }

        $urls_to_try = array();

        // 1. Try AWB Code
        if (!empty($awb_code)) {
            $urls_to_try[] = 'https://apiv2.shiprocket.in/v1/external/courier/track/awb/' . urlencode($awb_code);
        }
        // 2. Try Shipment ID
        if (!empty($shipment_id)) {
            $urls_to_try[] = 'https://apiv2.shiprocket.in/v1/external/courier/track/shipment/' . urlencode($shipment_id);
        }
        // 3. Try Order ID
        if (!empty($order_id)) {
            $urls_to_try[] = 'https://apiv2.shiprocket.in/v1/external/courier/track?order_id=' . urlencode($order_id);
            $urls_to_try[] = 'https://apiv2.shiprocket.in/v1/external/courier/track?channel_order_id=' . urlencode($order_id);
        }

        $got_401 = false;

        foreach ($urls_to_try as $url) {
            $result = ruut_shiprocket_api_get($url, $api_key);
            if (isset($result['error'])) {
                continue;
            }
            if ($result['code'] === 200 && !empty($result['body']['tracking_data'])) {
                $td = $result['body']['tracking_data'];
                $track_status = isset($td['track_status']) ? intval($td['track_status']) : 1;
                $shipment_track = isset($td['shipment_track'][0]) ? $td['shipment_track'][0] : array();
                $has_status = !empty($shipment_track['current_status']) || !empty($td['current_status']) || !empty($shipment_track['awb_code']);

                if ($track_status !== 0 && ($has_status || !empty($shipment_track))) {
                    return $result['body'];
                }
            }
            if ($result['code'] === 401) {
                $got_401 = true;
                break; // Token expired, stop and refresh
            }
        }

        // Auto-refresh token on 401 and retry all URLs
        if ($got_401) {
            $new_token = ruut_refresh_shiprocket_token();
            if ($new_token) {
                $api_key = $new_token;
                foreach ($urls_to_try as $url) {
                    $result = ruut_shiprocket_api_get($url, $api_key);
                    if (isset($result['error'])) {
                        continue;
                    }
                    if ($result['code'] === 200 && !empty($result['body']['tracking_data'])) {
                        $td = $result['body']['tracking_data'];
                        $track_status = isset($td['track_status']) ? intval($td['track_status']) : 1;
                        $shipment_track = isset($td['shipment_track'][0]) ? $td['shipment_track'][0] : array();
                        $has_status = !empty($shipment_track['current_status']) || !empty($td['current_status']) || !empty($shipment_track['awb_code']);

                        if ($track_status !== 0 && ($has_status || !empty($shipment_track))) {
                            return $result['body'];
                        }
                    }
                }
            }
        }

        return false;
    }
}

if (!function_exists('ruut_find_woocommerce_order')) {
    function ruut_find_woocommerce_order($order_input) {
        if (empty($order_input)) {
            return false;
        }

        global $wpdb;
        $raw = trim((string)$order_input);
        $raw_clean = ltrim($raw, '#');

        // 1. Search by AWB / Tracking Number meta keys in DB (exact match)
        if (!empty($raw) && isset($wpdb->postmeta)) {
            $matched_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_shiprocket_awb', 'shiprocket_awb', '_awb_code', '_tracking_number', 'awb_code') AND (meta_value = %s OR meta_value = %s) LIMIT 1",
                $raw, $raw_clean
            ));
            if ($matched_id) {
                $order = wc_get_order($matched_id);
                if ($order && is_a($order, 'WC_Order')) {
                    return $order;
                }
            }
        }

        // 2. Try wc_get_order_id_by_order_number (Sequential Order Numbers plugin support)
        if (function_exists('wc_get_order_id_by_order_number')) {
            $id = wc_get_order_id_by_order_number($raw_clean);
            if ($id) {
                $order = wc_get_order($id);
                if ($order && is_a($order, 'WC_Order')) {
                    return $order;
                }
            }
        }

        // 3. Query WC Order by meta_key _order_number
        if (isset($wpdb->postmeta)) {
            $matched_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_order_number' AND (meta_value = %s OR meta_value = %s) LIMIT 1",
                $raw, $raw_clean
            ));
            if ($matched_id) {
                $order = wc_get_order($matched_id);
                if ($order && is_a($order, 'WC_Order')) {
                    return $order;
                }
            }
        }

        // 4. Try wc_get_order directly ONLY IF input is purely numeric (e.g. "123" or "#123")
        if (ctype_digit($raw_clean) && function_exists('wc_get_order')) {
            $numeric_id = intval($raw_clean);
            if ($numeric_id > 0) {
                $order = wc_get_order($numeric_id);
                if ($order && is_a($order, 'WC_Order')) {
                    $ord_id = (int)$order->get_id();
                    $ord_num = (string)$order->get_order_number();
                    if ($ord_id === $numeric_id || $ord_num === $raw_clean) {
                        return $order;
                    }
                }
            }
        }

        return false;
    }
}

if (!function_exists('ruut_api_track_order')) {
    function ruut_api_track_order($request) {
        $order_id_raw = $request->get_param('order_id');
        $email = trim($request->get_param('email'));

        if (empty($order_id_raw)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Order ID or Tracking Number is required.'), 400);
        }

        $order = ruut_find_woocommerce_order($order_id_raw);
        $order_id = $order ? $order->get_id() : intval(preg_replace('/[^0-9]/', '', $order_id_raw));

        // Validate email if explicitly provided
        if ($order && !empty($email)) {
            $billing_email = $order->get_billing_email();
            if (strtolower($billing_email) !== strtolower($email)) {
                return new WP_REST_Response(array('success' => false, 'message' => 'Unauthorized access. Email does not match.'), 401);
            }
        }

        // Fetch Shiprocket API Key / Auth Token
        $api_key = get_option('ruut_shiprocket_api_key', '');

        // Look up tracking metadata on order
        $awb_code = '';
        $shipment_id = '';
        $carrier = '';

        if ($order) {
            $awb_code = get_post_meta($order_id, '_shiprocket_awb', true)
                     ?: get_post_meta($order_id, 'shiprocket_awb', true)
                     ?: get_post_meta($order_id, '_awb_code', true)
                     ?: get_post_meta($order_id, 'awb_code', true)
                     ?: get_post_meta($order_id, '_tracking_number', true);

            $shipment_id = get_post_meta($order_id, '_shiprocket_shipment_id', true)
                        ?: get_post_meta($order_id, 'shiprocket_shipment_id', true)
                        ?: get_post_meta($order_id, '_shipment_id', true);

            $tracking_items = get_post_meta($order_id, '_wc_shipment_tracking_items', true);
            if (is_array($tracking_items) && !empty($tracking_items)) {
                if (empty($awb_code) && !empty($tracking_items[0]['tracking_number'])) {
                    $awb_code = $tracking_items[0]['tracking_number'];
                }
                if (!empty($tracking_items[0]['tracking_provider'])) {
                    $carrier = $tracking_items[0]['tracking_provider'];
                }
            }

            // Also check webhook-stored courier name
            if (empty($carrier)) {
                $webhook_courier = get_post_meta($order_id, '_shiprocket_courier', true);
                $carrier = $webhook_courier ?: $order->get_shipping_method() ?: 'Standard Delivery';
            }
        } else {
            $awb_code = $order_id_raw;
            $carrier = 'Standard Delivery';
        }

        // Fetch live tracking data from Shiprocket API
        $shiprocket_data = ruut_fetch_shiprocket_tracking($api_key, $awb_code, $shipment_id, $order_id ? $order_id : $order_id_raw);

        if ($shiprocket_data && !empty($shiprocket_data['tracking_data'])) {
            $td = $shiprocket_data['tracking_data'];
            $track_details = isset($td['shipment_track'][0]) ? $td['shipment_track'][0] : array();
            
            $current_status = isset($track_details['current_status']) ? $track_details['current_status'] : (isset($td['current_status']) ? $td['current_status'] : '');
            if (!empty($track_details['courier_name'])) {
                $carrier = $track_details['courier_name'];
            }
            if (!empty($track_details['awb_code'])) {
                $awb_code = $track_details['awb_code'];
            }

            // Map scan activities to checkpoints
            $activities = isset($td['shipment_track_activities']) ? $td['shipment_track_activities'] : (isset($track_details['scans']) ? $track_details['scans'] : array());
            $checkpoints = array();

            if (is_array($activities) && !empty($activities)) {
                foreach ($activities as $act) {
                    $title = isset($act['activity']) ? $act['activity'] : (isset($act['status']) ? $act['status'] : 'Update');
                    $location = isset($act['location']) ? $act['location'] : '';
                    $date = isset($act['date']) ? $act['date'] : (isset($act['time']) ? $act['time'] : '');
                    
                    $checkpoints[] = array(
                        'title' => $title,
                        'desc' => $title . ($location ? ' - ' . $location : ''),
                        'time' => $date ? date('d M Y, h:i A', strtotime($date)) : '',
                        'location' => $location ?: ($order ? $order->get_billing_city() : 'Transit Hub'),
                        'status' => 'completed'
                    );
                }
            }

            if (empty($checkpoints) && $order) {
                $checkpoints = ruut_get_order_tracking_checkpoints($order);
            }

            $response = array(
                'success' => true,
                'order_id' => $order ? $order->get_id() : $order_id_raw,
                'status' => strtolower($current_status) ?: ($order ? $order->get_status() : 'processing'),
                'date_created' => $order && $order->get_date_created() ? $order->get_date_created()->date('d M Y') : date('d M Y'),
                'tracking_number' => $awb_code ?: ($order ? '#' . $order->get_order_number() : $order_id_raw),
                'carrier' => $carrier,
                'billing_first_name' => $order ? $order->get_billing_first_name() : '',
                'billing_last_name' => $order ? $order->get_billing_last_name() : '',
                'billing_city' => $order ? $order->get_billing_city() : '',
                'checkpoints' => $checkpoints
            );

            return new WP_REST_Response($response, 200);
        }

        // Fallback: If live Shiprocket API didn't return data, check for webhook-stored tracking data
        if ($order) {
            $webhook_status = get_post_meta($order_id, '_shiprocket_status', true);
            $webhook_scans_json = get_post_meta($order_id, '_shiprocket_scans', true);
            $webhook_courier = get_post_meta($order_id, '_shiprocket_courier', true);

            // If we have webhook-stored scan data, use it instead of synthetic checkpoints
            if (!empty($webhook_scans_json)) {
                $webhook_scans = json_decode($webhook_scans_json, true);
                $checkpoints = array();

                if (is_array($webhook_scans) && !empty($webhook_scans)) {
                    foreach ($webhook_scans as $scan) {
                        $title = !empty($scan['activity']) ? $scan['activity'] : (!empty($scan['status']) ? $scan['status'] : 'Update');
                        $location = !empty($scan['location']) ? $scan['location'] : '';
                        $date = !empty($scan['date']) ? $scan['date'] : '';

                        $checkpoints[] = array(
                            'title' => $title,
                            'desc' => $title . ($location ? ' - ' . $location : ''),
                            'time' => $date ? date('d M Y, h:i A', strtotime($date)) : '',
                            'location' => $location ?: $order->get_billing_city(),
                            'status' => 'completed'
                        );
                    }
                }

                if (empty($checkpoints)) {
                    $checkpoints = ruut_get_order_tracking_checkpoints($order);
                }

                $tracking_number = $awb_code ?: '#' . $order->get_order_number();

                $response = array(
                    'success' => true,
                    'order_id' => $order_id,
                    'status' => $webhook_status ? strtolower($webhook_status) : $order->get_status(),
                    'date_created' => $order->get_date_created() ? $order->get_date_created()->date('d M Y') : '',
                    'tracking_number' => $tracking_number,
                    'carrier' => $webhook_courier ?: $carrier,
                    'billing_first_name' => $order->get_billing_first_name(),
                    'billing_last_name' => $order->get_billing_last_name(),
                    'billing_city' => $order->get_billing_city(),
                    'checkpoints' => $checkpoints
                );

                return new WP_REST_Response($response, 200);
            }

            // Final fallback: pure WooCommerce data with synthetic checkpoints
            $tracking_number = $awb_code ?: '#' . $order->get_order_number();
            $checkpoints = ruut_get_order_tracking_checkpoints($order);

            $response = array(
                'success' => true,
                'order_id' => $order_id,
                'status' => $webhook_status ? strtolower($webhook_status) : $order->get_status(),
                'date_created' => $order->get_date_created() ? $order->get_date_created()->date('d M Y') : '',
                'tracking_number' => $tracking_number,
                'carrier' => $webhook_courier ?: $carrier,
                'billing_first_name' => $order->get_billing_first_name(),
                'billing_last_name' => $order->get_billing_last_name(),
                'billing_city' => $order->get_billing_city(),
                'checkpoints' => $checkpoints
            );

            return new WP_REST_Response($response, 200);
        }

        return new WP_REST_Response(array('success' => false, 'message' => 'Order not found. Please check your order number or AWB number and try again.'), 404);
    }
}

if (!function_exists('ruut_api_get_user_orders')) {
    function ruut_api_get_user_orders($request) {
        $email = trim($request->get_param('email'));
        $user_id = get_current_user_id();

        if (!$user_id && empty($email)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Please log in or provide email.'), 400);
        }

        $args = array(
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if ($user_id > 0) {
            $args['customer_id'] = $user_id;
        } elseif (!empty($email)) {
            $args['billing_email'] = strtolower($email);
        }

        $orders = function_exists('wc_get_orders') ? wc_get_orders($args) : array();
        
        // Filter out pending payment orders
        if (is_array($orders)) {
            $orders = array_filter($orders, function($order) {
                return is_a($order, 'WC_Order') && $order->get_status() !== 'pending';
            });
        }
        
        $result = array();

        if (is_array($orders)) {
            foreach ($orders as $order) {
                if (!is_a($order, 'WC_Order')) continue;
                
                $awb = get_post_meta($order->get_id(), '_shiprocket_awb', true)
                    ?: get_post_meta($order->get_id(), '_tracking_number', true)
                    ?: ('RT' . str_pad($order->get_id(), 9, '0', STR_PAD_LEFT) . 'IN');

                $result[] = array(
                    'id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'status' => $order->get_status(),
                    'date' => $order->get_date_created() ? $order->get_date_created()->date('d M Y') : '',
                    'total' => $order->get_formatted_order_total(),
                    'items_count' => $order->get_item_count(),
                    'awb' => $awb
                );
            }
        }

        return new WP_REST_Response(array('success' => true, 'orders' => $result), 200);
    }
}

if (!function_exists('ruut_get_order_tracking_checkpoints')) {
    function ruut_get_order_tracking_checkpoints($order) {
        $status = $order->get_status();
        $created_date = $order->get_date_created();
        $city = $order->get_billing_city();
        if (empty($city)) {
            $city = 'Destination';
        }

        $checkpoints = array();
        
        // Step 1: Order Placed
        $checkpoints[] = array(
            'title' => 'Order Placed',
            'desc' => 'Your order has been successfully placed.',
            'time' => $created_date ? $created_date->date('d M Y, h:i A') : '',
            'location' => 'Storefront',
            'status' => 'completed'
        );

        // Step 2: Order Packed
        $packed_active = in_array($status, array('processing', 'completed', 'shipping', 'shipped'));
        $packed_time = $created_date ? clone $created_date : null;
        if ($packed_time) {
            $packed_time->modify('+4 hours');
        }
        $checkpoints[] = array(
            'title' => 'Order Packed',
            'desc' => 'Items have been packed and are ready for dispatch.',
            'time' => ($packed_active && $packed_time) ? $packed_time->date('d M Y, h:i A') : '',
            'location' => 'Warehouse Facility',
            'status' => $packed_active ? 'completed' : 'pending'
        );

        // Step 3: Shipped / In Transit
        $shipped_active = in_array($status, array('completed', 'shipping', 'shipped'));
        $shipped_time = $created_date ? clone $created_date : null;
        if ($shipped_time) {
            $shipped_time->modify('+24 hours');
        }
        $checkpoints[] = array(
            'title' => 'Shipped to Location',
            'desc' => 'In transit to ' . $city . '.',
            'time' => ($shipped_active && $shipped_time) ? $shipped_time->date('d M Y, h:i A') : '',
            'location' => 'Sorting Hub',
            'status' => $shipped_active ? 'completed' : 'pending'
        );

        // Step 4: Out for Delivery
        $out_active = ($status === 'completed');
        $out_time = $created_date ? clone $created_date : null;
        if ($out_time) {
            $out_time->modify('+40 hours');
        }
        $checkpoints[] = array(
            'title' => 'Out for Delivery',
            'desc' => 'Your package is out for delivery with our courier partner.',
            'time' => ($out_active && $out_time) ? $out_time->date('d M Y, h:i A') : '',
            'location' => $city . ' Delivery Center',
            'status' => $out_active ? 'completed' : 'pending'
        );

        // Step 5: Delivered
        $delivered_active = ($status === 'completed');
        $delivered_time = $created_date ? clone $created_date : null;
        if ($delivered_time) {
            $delivered_time->modify('+44 hours');
        }
        $checkpoints[] = array(
            'title' => 'Delivered',
            'desc' => 'Package has been delivered successfully.',
            'time' => ($delivered_active && $delivered_time) ? $delivered_time->date('d M Y, h:i A') : '',
            'location' => $city,
            'status' => $delivered_active ? 'completed' : 'pending'
        );

        return $checkpoints;
    }
}

if (!function_exists('ruut_api_send_otp')) {
    function ruut_api_send_otp($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email']);
        if (!is_email($email)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Please enter a valid email.'), 400);
        }
        
        $otp = wp_rand(100000, 999999);
        set_transient('ruut_otp_' . md5($email), $otp, 10 * MINUTE_IN_SECONDS);
        
        $subject = $otp . " is your Ruut Secure Login Code";
        $message = '
        <div style="font-family: \'Times New Roman\', Times, serif; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #E6DED1; color: #5c4338; text-align: center; border: 1px solid rgba(92, 67, 56, 0.15);">
            <h1 style="font-size: 38px; letter-spacing: 12px; text-transform: uppercase; margin-bottom: 30px; font-weight: 400; margin-right: -12px;">ṚUUT</h1>
            <p style="font-family: sans-serif; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">Hello there,</p>
            <p style="font-family: sans-serif; font-size: 16px; line-height: 1.6; margin-bottom: 35px;">Verify your identity to log in to your RUUT panel.<br>Your secure verification code is:</p>
            <div style="background-color: #5c4338; color: #E6DED1; font-size: 36px; font-weight: bold; letter-spacing: 12px; padding: 20px 10px 20px 22px; border-radius: 2px; margin-bottom: 35px; display: inline-block;">' . $otp . '</div>
            <p style="font-family: sans-serif; font-size: 14px; opacity: 0.8; margin-bottom: 40px; color: #5c4338;">This code will expire in 10 minutes.</p>
        </div>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
        return new WP_REST_Response(array('success' => true, 'message' => 'OTP sent successfully! Please check your inbox.'), 200);
    }
}

if (!function_exists('ruut_api_verify_otp')) {
    function ruut_api_verify_otp($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email']);
        $otp = sanitize_text_field($params['otp']);

        $user = get_user_by('email', $email);
        if ($user && get_current_user_id() === $user->ID) {
            return new WP_REST_Response(array(
                'success' => true, 
                'message' => 'Welcome to RUUT.',
                'user' => array(
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'first_name' => get_user_meta($user->ID, 'billing_first_name', true),
                    'last_name' => get_user_meta($user->ID, 'billing_last_name', true),
                    'phone' => get_user_meta($user->ID, 'billing_phone', true)
                )
            ), 200);
        }

        $stored_otp = get_transient('ruut_otp_' . md5($email));
        
        if ($stored_otp && $stored_otp == $otp) {
            delete_transient('ruut_otp_' . md5($email));
            $user = get_user_by('email', $email);
            if (!$user) {
                $username = strstr($email, '@', true) . wp_rand(10, 99);
                while (username_exists($username)) {
                    $username = strstr($email, '@', true) . wp_rand(10, 99);
                }
                $password = wp_generate_password();
                $user_id = wp_create_user($username, $password, $email);
                if (is_wp_error($user_id)) {
                    return new WP_REST_Response(array('success' => false, 'message' => 'Failed to register account: ' . $user_id->get_error_message()), 500);
                }
                $user = get_user_by('id', $user_id);
            }
            
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            
            return new WP_REST_Response(array(
                'success' => true, 
                'message' => 'Welcome to RUUT.',
                'user' => array(
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'first_name' => get_user_meta($user->ID, 'billing_first_name', true),
                    'last_name' => get_user_meta($user->ID, 'billing_last_name', true),
                    'phone' => get_user_meta($user->ID, 'billing_phone', true)
                )
            ), 200);
        } else {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid or expired code. Please try again.'), 400);
        }
    }
}

if (!function_exists('ruut_api_get_user')) {
    function ruut_api_get_user() {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        $address_book = get_user_meta($user_id, 'ruut_address_book', true);
        if (empty($address_book) || !is_array($address_book)) {
            $address_book = array();
        }
        
        $orders = array();
        if (function_exists('wc_get_orders')) {
            $customer_orders = wc_get_orders(array(
                'customer' => $user_id,
                'limit' => 20,
                'orderby' => 'date',
                'order' => 'DESC',
            ));
            
            // Filter out pending payment orders
            if (is_array($customer_orders)) {
                $customer_orders = array_filter($customer_orders, function($order) {
                    return is_a($order, 'WC_Order') && $order->get_status() !== 'pending';
                });
            }
            
            foreach ($customer_orders as $order) {
                $items = array();
                foreach ($order->get_items() as $item_id => $item) {
                    $product = $item->get_product();
                    $items[] = array(
                        'name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'total' => $item->get_total(),
                        'image' => $product ? wp_get_attachment_url($product->get_image_id()) : ''
                    );
                }
                
                $orders[] = array(
                    'id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'date' => $order->get_date_created()->date('Y-m-d H:i'),
                    'status' => wc_get_order_status_name($order->get_status()),
                    'status_slug' => $order->get_status(),
                    'total' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'items' => $items
                );
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'user' => array(
                'id' => $user_id,
                'email' => $user->user_email,
                'first_name' => get_user_meta($user_id, 'billing_first_name', true),
                'last_name' => get_user_meta($user_id, 'billing_last_name', true),
                'phone' => get_user_meta($user_id, 'billing_phone', true)
            ),
            'address_book' => $address_book,
            'orders' => $orders
        ), 200);
    }
}

if (!function_exists('ruut_api_update_user')) {
    function ruut_api_update_user($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $first_name = sanitize_text_field($params['first_name']);
        $last_name = sanitize_text_field($params['last_name']);
        $phone = sanitize_text_field($params['phone']);
        
        update_user_meta($user_id, 'billing_first_name', $first_name);
        update_user_meta($user_id, 'billing_last_name', $last_name);
        update_user_meta($user_id, 'billing_phone', $phone);
        
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name)
        ));
        
        return new WP_REST_Response(array('success' => true, 'message' => 'Profile updated successfully.'), 200);
    }
}

if (!function_exists('ruut_api_save_address')) {
    function ruut_api_save_address($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $key = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        $address_book = get_user_meta($user_id, 'ruut_address_book', true);
        if (!is_array($address_book)) {
            $address_book = array();
        }
        
        $is_default = isset($params['is_default']) ? (bool)$params['is_default'] : false;
        
        $first_name = get_user_meta($user_id, 'billing_first_name', true);
        $last_name  = get_user_meta( $user_id, 'billing_last_name', true );
        $phone      = get_user_meta( $user_id, 'billing_phone', true );
        
        $new_address = array(
            'id'                => empty($key) ? uniqid() : $key,
            'title'             => sanitize_text_field($params['save_as']),
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'phone'             => $phone,
            'postcode'          => sanitize_text_field($params['postcode']),
            'city'              => sanitize_text_field($params['city']),
            'state'             => sanitize_text_field($params['state']),
            'address_1'         => sanitize_text_field($params['address_1']),
            'address_2'         => sanitize_text_field($params['address_2']),
            'landmark'          => sanitize_text_field($params['landmark']),
            
            // Legacy / Headless API fallback keys
            'billing_address_1' => sanitize_text_field($params['address_1']),
            'billing_address_2' => sanitize_text_field($params['address_2']),
            'billing_city'      => sanitize_text_field($params['city']),
            'billing_postcode'  => sanitize_text_field($params['postcode']),
            'billing_state'     => sanitize_text_field($params['state']),
            'billing_landmark'  => sanitize_text_field($params['landmark']),
            'billing_save_as'   => sanitize_text_field($params['save_as']),
            'is_default'        => $is_default
        );
        
        if (empty($new_address['address_1']) || empty($new_address['city']) || empty($new_address['postcode'])) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Address, City, and PIN code are required.'), 400);
        }
        
        $updated = false;
        if (!empty($key)) {
            foreach ($address_book as $k => $addr) {
                if (isset($addr['id']) && $addr['id'] === $key) {
                    $address_book[$k] = $new_address;
                    $updated = true;
                } elseif ($k === $key) {
                    $address_book[$k] = $new_address;
                    $updated = true;
                }
            }
        }
        
        if (!$updated) {
            $address_book[] = $new_address;
        }
        
        update_user_meta($user_id, 'ruut_address_book', $address_book);
        return new WP_REST_Response(array('success' => true, 'message' => 'Address saved successfully.', 'address_book' => $address_book), 200);
    }
}

if (!function_exists('ruut_api_delete_address')) {
    function ruut_api_delete_address($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $key = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        
        if (empty($key)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Address key is required.'), 400);
        }
        
        $address_book = get_user_meta($user_id, 'ruut_address_book', true);
        if (is_array($address_book)) {
            $deleted = false;
            foreach ($address_book as $k => $addr) {
                if ((isset($addr['id']) && $addr['id'] === $key) || $k === $key) {
                    unset($address_book[$k]);
                    $deleted = true;
                }
            }
            if ($deleted) {
                $address_book = array_values($address_book);
                update_user_meta($user_id, 'ruut_address_book', $address_book);
                return new WP_REST_Response(array('success' => true, 'message' => 'Address deleted successfully.', 'address_book' => $address_book), 200);
            }
        }
        
        return new WP_REST_Response(array('success' => false, 'message' => 'Address not found.'), 404);
    }
}

if (!function_exists('ruut_api_logout')) {
    function ruut_api_logout() {
        wp_logout();
        return new WP_REST_Response(array('success' => true, 'message' => 'Logged out successfully.'), 200);
    }
}

if (!function_exists("ruut_register_products_rest_endpoints")) {
    add_action( "rest_api_init", "ruut_register_products_rest_endpoints" );
    function ruut_register_products_rest_endpoints() {
        register_rest_route( "ruut/v1", "/products", array(
            "methods"  => "GET",
            "callback" => "ruut_get_products_api",
            "permission_callback" => "__return_true",
        ) );
    }
}

if (!function_exists("ruut_get_products_api")) {
    function ruut_get_products_api( $request ) {
        $id = $request->get_param("id");
        if ( !empty($id) ) {
            $product = wc_get_product( intval($id) );
            if ( $product ) {
                if ( $product->is_type('variation') ) {
                    $parent_id = $product->get_parent_id();
                    $parent_product = wc_get_product( $parent_id );
                    if ( $parent_product && ( $parent_product->get_status() === 'publish' || current_user_can('edit_products') ) ) {
                        return rest_ensure_response( ruut_format_product_data( $parent_product ) );
                    }
                }
                if ( $product->get_status() === 'publish' || current_user_can('edit_products') ) {
                    return rest_ensure_response( ruut_format_product_data( $product ) );
                }
            }
            return new WP_Error( 'ruut_product_not_found', 'Product not found', array( 'status' => 404 ) );
        }

        $category = $request->get_param("category");
        $featured = $request->get_param("featured");
        $limit = $request->get_param("limit") ? intval($request->get_param("limit")) : 12;

        $args = array(
            "status" => "publish",
            "limit" => $limit,
            "orderby" => "date",
            "order" => "DESC",
            "return" => "objects",
        );
        
        if ( !empty($category) ) {
            $args["category"] = array( sanitize_text_field( $category ) );
        }

        if ( !empty($featured) && ($featured === "true" || $featured === "1") ) {
            $args["featured"] = true;
        }

        if ( ! function_exists( 'wc_get_products' ) ) {
            return rest_ensure_response( array() );
        }

        $products = wc_get_products( $args );
        $data = array();

        foreach ( $products as $product ) {
            $data[] = ruut_format_product_data( $product );
        }

        return rest_ensure_response( $data );
    }
}

/**
 * Webhook receiver for delivery partner (Shiprocket) tracking updates.
 * Endpoint: POST /wp-json/ruut/v1/delivery-hook
 * 
 * Receives real-time shipment status updates and stores them as WooCommerce order meta.
 * This enables the Track My Order page to display live tracking data.
 */
if (!function_exists('ruut_api_delivery_webhook')) {
    function ruut_api_delivery_webhook($request) {
        // 1. Validate security token (optional but recommended)
        $expected_token = get_option('ruut_webhook_security_token', '');
        if (!empty($expected_token)) {
            $received_token = $request->get_header('x-api-key');
            if (empty($received_token) || $received_token !== $expected_token) {
                return new WP_REST_Response(array('status' => 'error', 'message' => 'Unauthorized'), 401);
            }
        }

        // 2. Parse the incoming JSON payload
        $body = $request->get_json_params();
        if (empty($body)) {
            $body = json_decode($request->get_body(), true);
        }

        if (empty($body)) {
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Empty payload'), 400);
        }

        // 3. Extract key fields from the webhook payload
        $awb             = isset($body['awb']) ? sanitize_text_field($body['awb']) : '';
        $courier_name    = isset($body['courier_name']) ? sanitize_text_field($body['courier_name']) : '';
        $current_status  = isset($body['current_status']) ? sanitize_text_field($body['current_status']) : '';
        $channel_order_id = isset($body['order_id']) ? sanitize_text_field($body['order_id']) : '';
        $sr_order_id     = isset($body['sr_order_id']) ? sanitize_text_field($body['sr_order_id']) : '';
        $shipment_id     = isset($body['shipment_id']) ? sanitize_text_field($body['shipment_id']) : '';
        $is_return       = isset($body['is_return']) ? intval($body['is_return']) : 0;
        $etd             = isset($body['etd']) ? sanitize_text_field($body['etd']) : '';
        $pod             = isset($body['pod']) ? esc_url_raw($body['pod']) : '';
        $scans           = isset($body['scans']) && is_array($body['scans']) ? $body['scans'] : array();

        // 4. Find the WooCommerce order
        $order = null;

        // Try by channel_order_id (this is the WooCommerce order number Shiprocket knows)
        if (!empty($channel_order_id)) {
            $order = ruut_find_woocommerce_order($channel_order_id);
        }

        // Fallback: try by AWB if we couldn't find by order ID
        if (!$order && !empty($awb)) {
            $order = ruut_find_woocommerce_order($awb);
        }

        if (!$order) {
            // Log for debugging but still return 200 to avoid Shiprocket retries
            error_log('[RUUT Webhook] Order not found for channel_order_id=' . $channel_order_id . ' awb=' . $awb);
            return new WP_REST_Response(array('status' => 'ok', 'message' => 'Order not found, webhook acknowledged'), 200);
        }

        $order_id = $order->get_id();

        // 5. Store tracking metadata on the WooCommerce order
        if (!empty($awb)) {
            update_post_meta($order_id, '_shiprocket_awb', $awb);
        }
        if (!empty($courier_name)) {
            update_post_meta($order_id, '_shiprocket_courier', $courier_name);
        }
        if (!empty($current_status)) {
            update_post_meta($order_id, '_shiprocket_status', $current_status);
        }
        if (!empty($sr_order_id)) {
            update_post_meta($order_id, '_shiprocket_order_id', $sr_order_id);
        }
        if (!empty($shipment_id)) {
            update_post_meta($order_id, '_shiprocket_shipment_id', $shipment_id);
        }
        if (!empty($etd)) {
            update_post_meta($order_id, '_shiprocket_etd', $etd);
        }
        if (!empty($pod)) {
            update_post_meta($order_id, '_shiprocket_pod', $pod);
        }
        if ($is_return) {
            update_post_meta($order_id, '_shiprocket_is_return', 1);
        }

        // 6. Store scan activities as JSON for the tracking timeline
        if (!empty($scans)) {
            $clean_scans = array();
            foreach ($scans as $scan) {
                $clean_scans[] = array(
                    'date'     => isset($scan['date']) ? sanitize_text_field($scan['date']) : '',
                    'activity' => isset($scan['activity']) ? sanitize_text_field($scan['activity']) : '',
                    'location' => isset($scan['location']) ? sanitize_text_field($scan['location']) : '',
                    'status'   => isset($scan['sr-status-label']) ? sanitize_text_field($scan['sr-status-label']) : '',
                );
            }
            update_post_meta($order_id, '_shiprocket_scans', json_encode($clean_scans, JSON_UNESCAPED_UNICODE));
        }

        // 7. Update WooCommerce order status based on Shiprocket status
        $status_lower = strtolower($current_status);
        $wc_status_map = array(
            'delivered'        => 'completed',
            'rto delivered'    => 'cancelled',
            'cancelled'        => 'cancelled',
        );

        if (isset($wc_status_map[$status_lower])) {
            $new_wc_status = $wc_status_map[$status_lower];
            if ($order->get_status() !== $new_wc_status) {
                $order->update_status($new_wc_status, 'Auto-updated via delivery partner webhook: ' . $current_status);
            }
        }

        // 8. Store last webhook timestamp
        update_post_meta($order_id, '_shiprocket_webhook_last', current_time('mysql'));

        // 9. Add order note for audit trail
        $order->add_order_note(
            sprintf(
                'Delivery update received — Status: %s | AWB: %s | Courier: %s',
                $current_status,
                $awb,
                $courier_name
            )
        );

        return new WP_REST_Response(array('status' => 'ok'), 200);
    }
}
