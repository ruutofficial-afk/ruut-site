<?php
/**
 * RUUT Core - WooCommerce Cart & Privileges API Module
 * 
 * Contains all WooCommerce cart AJAX endpoints, total calculations, volume discounts,
 * free gift logic, and coupon application handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// ===================================================================================
// HELPER FUNCTIONS & DEFAULTS
// ===================================================================================

// Helper to ensure WooCommerce cart is loaded during AJAX
if ( ! function_exists( 'ruut_ensure_cart' ) ) {
    function ruut_ensure_cart() {
        if ( ! defined( 'WC_ABSPATH' ) ) {
            return;
        }
        
        if ( is_null( WC()->cart ) || is_null( WC()->session ) ) {
            include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            include_once WC_ABSPATH . 'includes/class-wc-cart.php';
            include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
            include_once WC_ABSPATH . 'includes/class-wc-customer.php';
            
            wc_load_cart();
        }

        if ( WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }
}

// Helper to select the product with the highest stock level from an array of IDs
if ( ! function_exists( 'ruut_get_highest_stock_product_id' ) ) {
    function ruut_get_highest_stock_product_id( $product_ids = array() ) {
        if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
            if ( ! function_exists( 'wc_get_products' ) ) {
                return 0;
            }
            $products = wc_get_products( array(
                'status' => 'publish',
                'limit'  => -1,
            ) );
            if ( empty( $products ) ) {
                return 0;
            }
            $product_ids = array_map( function( $p ) { return $p->get_id(); }, $products );
        }
        
        $best_product_id = 0;
        $max_stock = -999999;
        
        foreach ( $product_ids as $id ) {
            $id = intval( $id );
            if ( ! $id ) {
                continue;
            }
            $prod = wc_get_product( $id );
            if ( ! $prod || ! $prod->is_in_stock() ) {
                continue;
            }
            
            $stock = 0;
            if ( $prod->managing_stock() ) {
                $stock = intval( $prod->get_stock_quantity() );
            } else {
                $stock = 9999;
            }
            
            if ( $stock > $max_stock ) {
                $max_stock = $stock;
                $best_product_id = $id;
            }
        }
        
        return $best_product_id;
    }
}

// Helper to select a random product biased towards testers
if ( ! function_exists( 'ruut_get_random_gift_product_id' ) ) {
    function ruut_get_random_gift_product_id() {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return 0;
        }
        
        $products = wc_get_products( array(
            'status' => 'publish',
            'limit'  => -1,
        ) );
        
        if ( empty( $products ) ) {
            return 0;
        }
        
        $testers = array();
        $full_products = array();
        
        foreach ( $products as $product ) {
            $name = strtolower( $product->get_name() );
            $sku = strtolower( $product->get_sku() );
            
            $is_tester = false;
            if ( strpos( $name, 'tester' ) !== false 
                 || strpos( $name, 'sample' ) !== false 
                 || strpos( $name, 'vial' ) !== false 
                 || strpos( $name, '5ml' ) !== false 
                 || strpos( $name, '10ml' ) !== false 
                 || strpos( $sku, 'test' ) !== false ) {
                $is_tester = true;
            }
            
            if ( $is_tester ) {
                $testers[] = $product;
            } else {
                $full_products[] = $product;
            }
        }
        
        $rand = rand( 1, 100 );
        if ( $rand <= 80 && ! empty( $testers ) ) {
            $selected_product = $testers[ array_rand( $testers ) ];
        } else {
            $selected_product = ! empty( $full_products ) ? $full_products[ array_rand( $full_products ) ] : ( ! empty( $testers ) ? $testers[ array_rand( $testers ) ] : null );
        }
        
        return $selected_product ? $selected_product->get_id() : 0;
    }
}

// Helper to get offer thresholds with fallback defaults
if ( ! function_exists( 'ruut_get_configured_offer_thresholds' ) ) {
    function ruut_get_configured_offer_thresholds() {
        $thresholds_json = get_option( 'ruut_offer_thresholds', '' );
        $thresholds = json_decode( $thresholds_json, true );
        if ( ! is_array( $thresholds ) || empty( $thresholds ) ) {
            $fallback_gift_id = ruut_get_highest_stock_product_id( array() );
            $thresholds = array(
                array(
                    'quantity' => 2,
                    'compulsory_gift_id' => $fallback_gift_id,
                    'random_gift_pool' => array(),
                    'discount' => 100
                ),
                array(
                    'quantity' => 3,
                    'compulsory_gift_id' => $fallback_gift_id,
                    'random_gift_pool' => array(),
                    'discount' => 250
                )
            );
        }
        return $thresholds;
    }
}

// Helper to get formatted cart response
if ( ! function_exists( 'ruut_get_cart_response' ) ) {
    function ruut_get_cart_response() {
        ruut_ensure_cart();
        $cart = WC()->cart;
        if ( ! $cart ) {
            return array(
                'items' => array(),
                'totals' => array('subtotal' => 0, 'total' => 0, 'discount_total' => 0, 'applied_coupons' => array())
            );
        }

        // Calculate totals first so that coupon validations run against fresh cart amounts
        $cart->calculate_totals();

        // Revalidate applied coupons using WC core check
        $cart->check_cart_coupons();

        // Check if any coupon was removed by check_cart_coupons() and grab notice
        $removed_coupon_message = '';
        $notices = wc_get_notices('error');
        if (!empty($notices)) {
            $removed_coupon_message = html_entity_decode( wp_strip_all_tags( $notices[0]['notice'] ) );
            wc_clear_notices();
        }

        // Additional manual validation check loop as a fallback
        $applied_coupons = $cart->get_applied_coupons();
        $coupon_removed = false;
        if ( ! empty( $applied_coupons ) ) {
            foreach ( $applied_coupons as $coupon_code ) {
                $coupon = new WC_Coupon( $coupon_code );
                $discounts = new WC_Discounts( $cart );
                $valid = $discounts->is_coupon_valid( $coupon );
                if ( is_wp_error( $valid ) ) {
                    $cart->remove_coupon( $coupon_code );
                    $removed_coupon_message = html_entity_decode( wp_strip_all_tags( $valid->get_error_message() ) );
                    $coupon_removed = true;
                }
            }
        }

        // Recalculate totals again if any coupon was invalid and removed
        if ( $coupon_removed || !empty($removed_coupon_message) ) {
            $cart->calculate_totals();
        }

        $cart_items = array();
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $product_id = $product->get_id();
            $image_id = $product->get_image_id();
            $image_url = wp_get_attachment_image_url($image_id, 'full') ?: wc_placeholder_img_src('full');
            
            $is_gift = ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true );
            $price = $is_gift ? 0.00 : $product->get_price();
            $original_price = $product->get_regular_price() ? $product->get_regular_price() : $product->get_price();
            
            $cart_items[] = array(
                'key' => $cart_item_key,
                'id' => $product_id,
                'name' => $product->get_name(),
                'price' => $price,
                'originalPrice' => (float) $original_price,
                'quantity' => $cart_item['quantity'],
                'image' => $image_url,
                'volume' => $product->get_attribute('pa_volume') ? $product->get_attribute('pa_volume') : ($product->get_attribute('volume') ?: '200ml'),
                'sku' => $product->get_sku(),
                'is_gift' => $is_gift
            );
        }

        // Calculate regular items quantity to compute volume discount
        $regular_items_qty = 0;
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true ) ) {
                $regular_items_qty += $cart_item['quantity'];
            }
        }

        // Fetch configured thresholds
        $thresholds = ruut_get_configured_offer_thresholds();

        // Find active threshold level and calculate volume discount
        $active_threshold = null;
        foreach ( $thresholds as $t ) {
            if ( intval($t['quantity']) <= $regular_items_qty ) {
                if ( empty($active_threshold) || intval($t['quantity']) > intval($active_threshold['quantity']) ) {
                    $active_threshold = $t;
                }
            }
        }
        $volume_discount = 0.00;
        $offer_enabled = get_option('ruut_progress_bar_enabled', 'yes') === 'yes';
        if ( $offer_enabled && $active_threshold && ! empty($active_threshold['discount']) ) {
            $volume_discount = floatval($active_threshold['discount']);
        }

        $totals = array(
            'subtotal' => (float) $cart->subtotal,
            'total' => (float) $cart->total,
            'discount_total' => (float) $cart->get_discount_total(),
            'applied_coupons' => $cart->get_applied_coupons(),
            'volume_discount' => $volume_discount
        );  
        
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

        return array(
            'items' => $cart_items,
            'totals' => $totals,
            'removed_coupon_message' => $removed_coupon_message,
            'goal_amount' => (float) get_option('ruut_cart_goal_amount', '3000'),
            'reward_threshold' => (int) get_option('ruut_reward_threshold', '3'),
            'reward_amount_text' => get_option('ruut_reward_amount', '₹250'),
            
            // Progress Bar and Gift settings
            'offer_enabled' => get_option('ruut_progress_bar_enabled', 'yes') === 'yes',
            'offer_thresholds' => $api_thresholds,

            // WooCommerce direct response fields
            'coupons' => $cart->get_applied_coupons(),
            'subtotal' => (float) $cart->subtotal,
            'discount_total' => (float) $cart->get_discount_total(),
            'total' => (float) $cart->total,
            'notices' => !empty($removed_coupon_message) ? array($removed_coupon_message) : array()
        );
    }
}

// ===================================================================================
// FREE GIFT & DISCOUNT HOOKS
// ===================================================================================

// Hook into WooCommerce before calculate totals to enforce free gift offer
if ( ! function_exists( 'ruut_apply_free_gift_discount' ) ) {
    add_action( 'woocommerce_before_calculate_totals', 'ruut_apply_free_gift_discount', 10, 1 );
    function ruut_apply_free_gift_discount( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $enabled = get_option('ruut_progress_bar_enabled', 'yes');
        
        // 1. Calculate regular items quantity (sum of quantities of non-gifts)
        $regular_items_qty = 0;
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true ) ) {
                $regular_items_qty += $cart_item['quantity'];
            }
        }

        // If progress bar/gift feature is globally disabled OR if a coupon code is applied, remove all gifts (mutually exclusive)
        if ( $enabled !== 'yes' || ! empty($cart->get_applied_coupons()) ) {
            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true ) {
                    $cart->remove_cart_item( $cart_item_key );
                }
            }
            return;
        }

        // 2. Fetch configured thresholds
        $thresholds = ruut_get_configured_offer_thresholds();
        
        // 3. Find active threshold level (highest threshold <= regular_items_qty)
        $active_threshold = null;
        foreach ( $thresholds as $t ) {
            if ( intval($t['quantity']) <= $regular_items_qty ) {
                if ( empty($active_threshold) || intval($t['quantity']) > intval($active_threshold['quantity']) ) {
                    $active_threshold = $t;
                }
            }
        }

        // 4. Determine target gift product IDs for the active threshold
        $target_gift_product_ids = array();
        if ( $active_threshold ) {
            // Compulsory Gift product ID
            if ( ! empty($active_threshold['compulsory_gift_id']) ) {
                $comp_id = intval($active_threshold['compulsory_gift_id']);
                $comp_prod = wc_get_product($comp_id);
                if ( $comp_prod && $comp_prod->is_purchasable() ) {
                    $target_gift_product_ids[] = $comp_id;
                } else {
                    $fallback_id = ruut_get_highest_stock_product_id(array());
                    if ($fallback_id > 0) {
                        $target_gift_product_ids[] = $fallback_id;
                    }
                }
            } else {
                $fallback_id = ruut_get_highest_stock_product_id(array());
                if ($fallback_id > 0) {
                    $target_gift_product_ids[] = $fallback_id;
                }
            }

            // Random Gift product ID from pool
            if ( ! empty($active_threshold['random_gift_pool']) && is_array($active_threshold['random_gift_pool']) ) {
                $random_id = ruut_get_highest_stock_product_id($active_threshold['random_gift_pool']);
                if ($random_id > 0 && ! in_array($random_id, $target_gift_product_ids)) {
                    $target_gift_product_ids[] = intval($random_id);
                }
            }

            // If active threshold requires 2 gifts (Qty >= 3) but random pool was empty, ensure a distinct second gift is added
            if ( count($target_gift_product_ids) < 2 && ( ( isset($active_threshold['quantity']) && intval($active_threshold['quantity']) >= 3 ) || ! empty($active_threshold['random_gift_id']) ) ) {
                $all_in_stock = ruut_get_highest_stock_product_id(array());
                if ($all_in_stock > 0 && ! in_array($all_in_stock, $target_gift_product_ids)) {
                    $target_gift_product_ids[] = intval($all_in_stock);
                } else {
                    $random_tester = ruut_get_random_gift_product_id();
                    if ($random_tester > 0 && ! in_array($random_tester, $target_gift_product_ids)) {
                        $target_gift_product_ids[] = intval($random_tester);
                    }
                }
            }

            $target_gift_product_ids = array_unique(array_filter($target_gift_product_ids));
        }

        // 5. Check cart items — ONLY manage items explicitly added as gifts (ruut_free_gift === true).
        $existing_gift_product_ids = array();
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! isset( $cart_item['ruut_free_gift'] ) || $cart_item['ruut_free_gift'] !== true ) {
                continue;
            }

            $prod_id = intval($cart_item['product_id']);

            if ( in_array($prod_id, $target_gift_product_ids) && ! in_array($prod_id, $existing_gift_product_ids) ) {
                if ( $cart_item['quantity'] != 1 ) {
                    $cart->set_quantity( $cart_item_key, 1 );
                }
                $cart->cart_contents[$cart_item_key]['data']->set_price( 0 );
                $existing_gift_product_ids[] = $prod_id;
            } else {
                $cart->remove_cart_item( $cart_item_key );
            }
        }

        // 6. Add missing target gifts
        foreach ( $target_gift_product_ids as $gift_id ) {
            if ( ! in_array($gift_id, $existing_gift_product_ids) ) {
                $cart->add_to_cart( $gift_id, 1, 0, array(), array( 'ruut_free_gift' => true ) );
            }
        }
    }
}

// Persist custom cart item data in session
if ( ! function_exists( 'ruut_get_cart_item_from_session' ) ) {
    add_filter( 'woocommerce_get_cart_item_from_session', 'ruut_get_cart_item_from_session', 10, 2 );
    function ruut_get_cart_item_from_session( $cart_item, $values ) {
        if ( isset( $values['ruut_free_gift'] ) ) {
            $cart_item['ruut_free_gift'] = $values['ruut_free_gift'];
        }
        return $cart_item;
    }
}

// Hook into WooCommerce calculate fees to apply dynamic flat discount
if ( ! function_exists( 'ruut_apply_dynamic_threshold_discounts' ) ) {
    add_action( 'woocommerce_cart_calculate_fees', 'ruut_apply_dynamic_threshold_discounts', 10, 1 );
    function ruut_apply_dynamic_threshold_discounts( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $enabled = get_option('ruut_progress_bar_enabled', 'yes');
        if ( $enabled !== 'yes' || ! empty($cart->get_applied_coupons()) ) {
            return;
        }

        $regular_items_qty = 0;
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true ) ) {
                $regular_items_qty += $cart_item['quantity'];
            }
        }

        $thresholds = ruut_get_configured_offer_thresholds();
        
        $active_threshold = null;
        foreach ( $thresholds as $t ) {
            if ( intval($t['quantity']) <= $regular_items_qty ) {
                if ( empty($active_threshold) || intval($t['quantity']) > intval($active_threshold['quantity']) ) {
                    $active_threshold = $t;
                }
            }
        }

        if ( $active_threshold && ! empty($active_threshold['discount']) ) {
            $discount = floatval($active_threshold['discount']);
            if ( $discount > 0 ) {
                $cart->add_fee( __('Ritual Benefit', 'ruut-core'), -$discount );
            }
        }
    }
}

// ===================================================================================
// WOOCOMMERCE AJAX ENDPOINTS
// ===================================================================================

// Get Cart
if ( ! function_exists( 'ruut_ajax_get_cart' ) ) {
    add_action('wp_ajax_ruut_get_cart', 'ruut_ajax_get_cart');
    add_action('wp_ajax_nopriv_ruut_get_cart', 'ruut_ajax_get_cart');
    function ruut_ajax_get_cart() {
        try {
            ruut_ensure_cart();
            wp_send_json_success(ruut_get_cart_response());
        } catch (Throwable $e) {
            wp_send_json_error('Fatal: ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }
}

// Add to Cart
if ( ! function_exists( 'ruut_ajax_add_to_cart' ) ) {
    add_action('wp_ajax_ruut_add_to_cart', 'ruut_ajax_add_to_cart');
    add_action('wp_ajax_nopriv_ruut_add_to_cart', 'ruut_ajax_add_to_cart');
    function ruut_ajax_add_to_cart() {
        try {
            ruut_ensure_cart();
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
            
            if ($product_id > 0) {
                $product = wc_get_product($product_id);
                if ($product && $product->is_type('variation')) {
                    $parent_id = $product->get_parent_id();
                    $added = WC()->cart->add_to_cart($parent_id, $quantity, $product_id);
                } else {
                    $added = WC()->cart->add_to_cart($product_id, $quantity);
                }
                if ($added) {
                    wp_send_json_success(ruut_get_cart_response());
                } else {
                    $notices = wc_get_notices('error');
                    $error_msg = 'Cannot add to cart.';
                    if (!empty($notices)) {
                        $error_msg = wp_strip_all_tags($notices[0]['notice']);
                        wc_clear_notices();
                    }
                    wp_send_json_error($error_msg);
                }
            } else {
                wp_send_json_error('Invalid product');
            }
        } catch (Throwable $e) {
            wp_send_json_error('Fatal Error: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }
}

// Update Cart Qty
if ( ! function_exists( 'ruut_ajax_update_cart_qty' ) ) {
    add_action('wp_ajax_ruut_update_cart_qty', 'ruut_ajax_update_cart_qty');
    add_action('wp_ajax_nopriv_ruut_update_cart_qty', 'ruut_ajax_update_cart_qty');
    function ruut_ajax_update_cart_qty() {
        try {
            ruut_ensure_cart();
            $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
            
            if ($cart_item_key) {
                WC()->cart->set_quantity($cart_item_key, $quantity);
                wp_send_json_success(ruut_get_cart_response());
            }
            wp_send_json_error('Invalid item');
        } catch (Throwable $e) {
            wp_send_json_error('Fatal: ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }
}

// Remove Cart Item
if ( ! function_exists( 'ruut_ajax_remove_cart_item' ) ) {
    add_action('wp_ajax_ruut_remove_cart_item', 'ruut_ajax_remove_cart_item');
    add_action('wp_ajax_nopriv_ruut_remove_cart_item', 'ruut_ajax_remove_cart_item');
    function ruut_ajax_remove_cart_item() {
        try {
            ruut_ensure_cart();
            $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
            
            if ($cart_item_key) {
                WC()->cart->remove_cart_item($cart_item_key);
                wp_send_json_success(ruut_get_cart_response());
            }
            wp_send_json_error('Invalid item');
        } catch (Throwable $e) {
            wp_send_json_error('Fatal: ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    }
}

// Custom Coupon Processor
if ( ! function_exists( 'ruut_process_custom_coupon' ) ) {
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
if ( ! function_exists( 'ruut_ajax_get_active_coupons' ) ) {
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
            $data = array();
            foreach ($coupons as $post) {
                $coupon = new WC_Coupon($post->ID);
                if ($coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time()) {
                    continue;
                }
                $data[] = array(
                    'id' => $coupon->get_id(),
                    'code' => $coupon->get_code(),
                    'amount' => $coupon->get_amount(),
                    'discount_type' => $coupon->get_discount_type(),
                    'description' => $post->post_excerpt ?: $post->post_content,
                    'minimum_amount' => (float) $coupon->get_minimum_amount(),
                    'maximum_amount' => (float) $coupon->get_maximum_amount(),
                );
            }
            wp_send_json_success($data);
        } catch (Throwable $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
}

// Apply Coupon
if ( ! function_exists( 'ruut_ajax_apply_coupon' ) ) {
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

            WC()->cart->calculate_totals();

            $discounts = new WC_Discounts( WC()->cart );
            $valid = $discounts->is_coupon_valid( $coupon );
            if ( is_wp_error( $valid ) ) {
                wp_send_json_error( html_entity_decode( wp_strip_all_tags( $valid->get_error_message() ) ) );
            }

            if ( WC()->cart->has_discount( $coupon_code ) ) {
                wp_send_json_error('Coupon "' . strtoupper($coupon_code) . '" is already applied.');
            }

            WC()->cart->remove_coupons();
            
            $applied = WC()->cart->add_discount($coupon_code);
            if ($applied) {
                wc_clear_notices();
                WC()->cart->calculate_totals();
                $discount_total = (float) WC()->cart->get_discount_total();
                
                $response = ruut_get_cart_response();
                $response['coupon_message'] = 'Coupon applied successfully.';
                $response['coupon_savings'] = $discount_total;
                wp_send_json_success($response);
            } else {
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
}

// Remove Coupon
if ( ! function_exists( 'ruut_ajax_remove_coupon' ) ) {
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
}
