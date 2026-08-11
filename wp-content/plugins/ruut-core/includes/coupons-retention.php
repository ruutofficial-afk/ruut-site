<?php
/**
 * WooCommerce Custom Coupon Validation, AJAX Handlers, and Active Coupons list
 * Extracted from ruut-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!function_exists('ruut_process_custom_coupon')) {
    add_action('wp_ajax_nopriv_ruut_apply_coupon_custom', 'ruut_process_custom_coupon');
    add_action('wp_ajax_ruut_apply_coupon_custom', 'ruut_process_custom_coupon');
    function ruut_process_custom_coupon() {
        $coupon_code = sanitize_text_field($_POST['coupon']);
        if (empty($coupon_code)) {
            wp_send_json_error(array('message' => 'Please enter a coupon code.', 'cart_url' => false));
        }

        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) {
            wp_send_json_error(array('message' => 'Invalid coupon code.', 'cart_url' => false));
        }

        try {
            $coupon->is_valid(); 
            $applied = WC()->cart->add_discount($coupon_code);
            if ($applied) {
                wp_send_json_success(array('message' => 'Coupon applied successfully.'));
            } else {
                wp_send_json_error(array('message' => 'Coupon could not be applied.', 'cart_url' => false));
            }
        } catch (Exception $e) {
            if ($e->getCode() == 112 || $e->getCode() == 108) {
                $min_spend = $coupon->get_minimum_amount();
                $cart_total = WC()->cart->subtotal;
                if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
                    $cart_total += WC()->cart->subtotal_tax;
                }
                $difference = $min_spend - $cart_total;
                if ($difference > 0) {
                    $formatted_diff = html_entity_decode(wp_strip_all_tags(wc_price($difference)));
                    wp_send_json_error(array(
                        'status' => 'min_spend',
                        'message' => 'Add products worth ' . $formatted_diff . ' to enjoy the benefits.',
                        'cart_url' => wc_get_cart_url()
                    ));
                }
            }
            wp_send_json_error(array(
                'status' => 'general',
                'message' => wp_strip_all_tags($e->getMessage()),
                'cart_url' => wc_get_cart_url()
            ));
        }
    }
}

// Get Active Coupons
add_action('wp_ajax_ruut_get_active_coupons', 'ruut_ajax_get_active_coupons');
add_action('wp_ajax_nopriv_ruut_get_active_coupons', 'ruut_ajax_get_active_coupons');
function ruut_ajax_get_active_coupons() {
    try {
        $args = array(
            'posts_per_page' => -1,
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
        );
        $coupons = get_posts($args);
        $coupon_data = array();
        foreach ($coupons as $post) {
            $coupon = new WC_Coupon($post->ID);
            if ($coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time()) {
                continue;
            }
            $coupon_data[] = array(
                'id' => $coupon->get_id(),
                'code' => $coupon->get_code(),
                'amount' => $coupon->get_amount(),
                'discount_type' => $coupon->get_discount_type(),
                'description' => $post->post_excerpt ?: $post->post_content,
                'minimum_amount' => (float) $coupon->get_minimum_amount(),
                'maximum_amount' => (float) $coupon->get_maximum_amount(),
            );
        }

        // Include Dynamic Offer Thresholds from Ruut Settings so frontend can sync
        $thresholds = ruut_get_configured_offer_thresholds();
        $api_thresholds = array();
        foreach ($thresholds as $t) {
            $compulsory_name = '';
            if (!empty($t['compulsory_gift_id'])) {
                $prod = wc_get_product(intval($t['compulsory_gift_id']));
                if ($prod) {
                    $compulsory_name = $prod->get_name();
                }
            }
            $random_gift_pool = isset($t['random_gift_pool']) && is_array($t['random_gift_pool']) ? array_map('intval', $t['random_gift_pool']) : array();
            $random_gift_name = '';
            $random_gift_id = 0;
            if (!empty($random_gift_pool)) {
                $random_gift_id = ruut_get_highest_stock_product_id($random_gift_pool);
                if ($random_gift_id > 0) {
                    $random_prod = wc_get_product($random_gift_id);
                    if ($random_prod) {
                        $random_gift_name = $random_prod->get_name();
                    }
                }
            }
            $api_thresholds[] = array(
                'quantity' => intval($t['quantity']),
                'compulsory_gift_id' => intval($t['compulsory_gift_id']),
                'compulsory_gift_name' => $compulsory_name,
                'random_gift_pool' => $random_gift_pool,
                'random_gift_id' => $random_gift_id,
                'random_gift_name' => $random_gift_name,
                'discount' => isset($t['discount']) ? floatval($t['discount']) : 0
            );
        }

        wp_send_json_success(array(
            'coupons' => $coupon_data,
            'offer_enabled' => get_option('ruut_progress_bar_enabled', 'yes') === 'yes',
            'offer_thresholds' => $api_thresholds
        ));
    } catch (Throwable $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

// Apply Coupon (consolidated — with detailed error forwarding)
add_action('wp_ajax_ruut_apply_coupon', 'ruut_ajax_apply_coupon');
add_action('wp_ajax_nopriv_ruut_apply_coupon', 'ruut_ajax_apply_coupon');
function ruut_ajax_apply_coupon() {
    try {
        ruut_ensure_cart();
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        if (empty($coupon_code)) {
            wp_send_json_error('Please enter a coupon code.');
        }

        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) {
            wp_send_json_error('Invalid coupon code.');
        }

        // Recalculate totals first so spending limits check against the latest subtotal
        WC()->cart->calculate_totals();

        // Validate coupon — is_valid() throws WC_Data_Exception with specific error messages
        $discounts = new WC_Discounts( WC()->cart );
        $valid = $discounts->is_coupon_valid( $coupon );
        if ( is_wp_error( $valid ) ) {
            wp_send_json_error( html_entity_decode( wp_strip_all_tags( $valid->get_error_message() ) ) );
        }

        // Check if already applied
        if ( WC()->cart->has_discount( $coupon_code ) ) {
            wp_send_json_error('Coupon "' . strtoupper($coupon_code) . '" is already applied.');
        }

        // Remove existing coupons to replace privilege (single-coupon policy)
        WC()->cart->remove_coupons();
        
        $applied = WC()->cart->add_discount($coupon_code);
        if ($applied) {
            // Clear WooCommerce notices that add_discount may generate
            wc_clear_notices();
            
            // Calculate the discount amount for feedback
            WC()->cart->calculate_totals();
            $discount_total = (float) WC()->cart->get_discount_total();
            
            $response = ruut_get_cart_response();
            $response['coupon_message'] = 'Coupon applied successfully.';
            $response['coupon_savings'] = $discount_total;
            wp_send_json_success($response);
        } else {
            // Grab WooCommerce notices for detailed feedback
            $notices = wc_get_notices('error');
            $error_msg = 'Coupon could not be applied.';
            if (!empty($notices)) {
                $error_msg = html_entity_decode( wp_strip_all_tags( $notices[0]['notice'] ) );
                wc_clear_notices();
            }
            wp_send_json_error($error_msg);
        }
    } catch (Exception $e) {
        $message = wp_strip_all_tags($e->getMessage());
        // Map common WooCommerce coupon error codes to user-friendly messages
        $code = $e->getCode();
        if ($code == 100) {
            $message = 'This coupon does not exist.';
        } elseif ($code == 101) {
            $message = 'This coupon has expired.';
        } elseif ($code == 108 || $code == 112) {
            $min = $coupon->get_minimum_amount();
            $message = 'Minimum order value of ₹' . number_format($min, 0) . ' not met.';
        } elseif ($code == 106) {
            $message = 'Coupon usage limit has been reached.';
        } elseif ($code == 109) {
            $message = 'This coupon is not applicable to products in your cart.';
        }
        wp_send_json_error($message);
    } catch (Throwable $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}

// Remove Coupon
add_action('wp_ajax_ruut_remove_coupon', 'ruut_ajax_remove_coupon');
add_action('wp_ajax_nopriv_ruut_remove_coupon', 'ruut_ajax_remove_coupon');
function ruut_ajax_remove_coupon() {
    try {
        ruut_ensure_cart();
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        if (!empty($coupon_code)) {
            WC()->cart->remove_coupon($coupon_code);
        }
        WC()->cart->remove_coupons();
        WC()->cart->calculate_totals();
        wp_send_json_success(ruut_get_cart_response());
    } catch (Throwable $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}
