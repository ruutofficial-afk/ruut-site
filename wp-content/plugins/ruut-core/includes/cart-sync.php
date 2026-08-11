<?php
/**
 * WooCommerce AJAX Cart Synchronization, Thresholds & Free Gift Logic
 * Extracted from ruut-core.php PART 1 (⚠️ EXACT COPY of core logic)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Helper to ensure WooCommerce cart is loaded during AJAX
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

// Helper to get formatted cart response
function ruut_get_cart_response() {
    ruut_ensure_cart();
    $cart = WC()->cart;
    if (!$cart) {
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
            try {
                $coupon = new WC_Coupon( $coupon_code );
                $discounts = new WC_Discounts( $cart );
                $valid = $discounts->is_coupon_valid( $coupon );
                if ( is_wp_error( $valid ) ) {
                    $cart->remove_coupon( $coupon_code );
                    $removed_coupon_message = html_entity_decode( wp_strip_all_tags( $valid->get_error_message() ) );
                    $coupon_removed = true;
                }
            } catch (Throwable $e) {
                $cart->remove_coupon( $coupon_code );
                $removed_coupon_message = html_entity_decode( wp_strip_all_tags( $e->getMessage() ) );
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
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;
        if ( ! is_a( $product, 'WC_Product' ) ) {
            continue;
        }
        $product_id = $product->get_id();
        $image_id = $product->get_image_id();
        $image_url = wp_get_attachment_image_url($image_id, 'full') ?: wc_placeholder_img_src('full');
        
        $is_gift = ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true );
        $price = $is_gift ? 0.00 : $product->get_price();
        // For gifts, show the real retail price as originalPrice (for struck-through display)
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
    // Calculate regular items quantity in qualifying categories to compute the volume discount
    $regular_items_qty = ruut_get_qualifying_cart_items_qty( $cart );

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
        'subtotal' => (float) $cart->subtotal, // raw float
        'total' => (float) $cart->total, // raw float
        'discount_total' => (float) $cart->get_discount_total(), // raw float
        'applied_coupons' => $cart->get_applied_coupons(), // array of codes
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
        'offer_categories' => get_option('ruut_offer_categories', array('all')),
        'offer_thresholds' => $api_thresholds,

        // WooCommerce direct response fields
        'coupons' => $cart->get_applied_coupons(),
        'subtotal' => (float) $cart->subtotal,
        'discount_total' => (float) $cart->get_discount_total(),
        'total' => (float) $cart->total,
        'notices' => !empty($removed_coupon_message) ? array($removed_coupon_message) : array(),
        'is_user_logged_in' => is_user_logged_in()
    );
}

// Helper to get offer thresholds with fallback defaults
function ruut_get_configured_offer_thresholds() {
    $thresholds_json = get_option('ruut_offer_thresholds', '');
    $thresholds = json_decode($thresholds_json, true);
    if (!is_array($thresholds) || empty($thresholds)) {
        return array();
    }
    return $thresholds;
}

// Helper to check if a product belongs to selected offer categories
function ruut_is_product_in_offer_categories( $product_id ) {
    $selected_cats = get_option( 'ruut_offer_categories', array( 'all' ) );
    if ( ! is_array( $selected_cats ) || empty( $selected_cats ) || in_array( 'all', $selected_cats ) ) {
        return true;
    }
    
    $cat_ids = array_map( 'intval', array_filter( $selected_cats, 'is_numeric' ) );
    if ( empty( $cat_ids ) ) {
        return true;
    }

    if ( has_term( $cat_ids, 'product_cat', $product_id ) ) {
        return true;
    }

    $product_terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
    if ( ! is_wp_error( $product_terms ) && ! empty( $product_terms ) ) {
        foreach ( $product_terms as $term_id ) {
            $ancestors = get_ancestors( $term_id, 'product_cat', 'taxonomy' );
            if ( ! empty( $ancestors ) && array_intersect( $cat_ids, $ancestors ) ) {
                return true;
            }
        }
    }

    return false;
}

// Helper to sum qualifying items quantity in cart
function ruut_get_qualifying_cart_items_qty( $cart ) {
    $qualifying_qty = 0;
    if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
        return 0;
    }
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( ! ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true ) ) {
            $prod_id = 0;
            if ( isset( $cart_item['product_id'] ) && intval( $cart_item['product_id'] ) > 0 ) {
                $prod_id = intval( $cart_item['product_id'] );
            } elseif ( isset( $cart_item['data'] ) && is_a( $cart_item['data'], 'WC_Product' ) ) {
                $prod_id = $cart_item['data']->get_id();
            }
            if ( $prod_id > 0 && ruut_is_product_in_offer_categories( $prod_id ) ) {
                $qualifying_qty += intval( $cart_item['quantity'] );
            }
        }
    }
    return $qualifying_qty;
}

// Helper to select the product with the highest stock level from an array of IDs
function ruut_get_highest_stock_product_id($product_ids = array()) {
    if (empty($product_ids) || !is_array($product_ids)) {
        return 0;
    }
    
    $best_product_id = 0;
    $max_stock = -999999;
    
    foreach ($product_ids as $id) {
        $id = intval($id);
        if (!$id) {
            continue;
        }
        $prod = wc_get_product($id);
        if (!$prod || !$prod->is_in_stock()) {
            continue;
        }
        
        $stock = 0;
        if ($prod->managing_stock()) {
            $stock = intval($prod->get_stock_quantity());
        } else {
            // In stock but stock management is disabled: treat as default high stock
            $stock = 9999;
        }
        
        if ($stock > $max_stock) {
            $max_stock = $stock;
            $best_product_id = $id;
        }
    }
    
    return $best_product_id;
}

// Helper to select a random product biased towards testers
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
    
    // Bias: mostly tester (80% chance), rarely full product (20% chance)
    $rand = rand( 1, 100 );
    if ( $rand <= 80 && ! empty( $testers ) ) {
        $selected_product = $testers[ array_rand( $testers ) ];
    } else {
        $selected_product = ! empty( $full_products ) ? $full_products[ array_rand( $full_products ) ] : ( ! empty( $testers ) ? $testers[ array_rand( $testers ) ] : null );
    }
    
    return $selected_product ? $selected_product->get_id() : 0;
}

// Helper to check if customer has already purchased a diffuser
function ruut_has_customer_purchased_diffuser($email_or_user_id) {
    if (!function_exists('wc_get_orders')) {
        return false;
    }
    
    $args = array(
        'status' => array('completed', 'processing'),
        'limit'  => -1,
    );
    
    if (is_numeric($email_or_user_id)) {
        $args['customer_id'] = intval($email_or_user_id);
    } else {
        $args['billing_email'] = sanitize_email($email_or_user_id);
    }
    
    $orders = wc_get_orders($args);
    if (empty($orders)) {
        return false;
    }
    
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
                $is_oil = false;
                foreach ((array)$cats as $cat_name) {
                    if (stripos($cat_name, 'oil') !== false) {
                        $is_oil = true;
                        break;
                    }
                }
                if (!$is_oil && (stripos($product->get_name(), 'oil') !== false || stripos($product->get_name(), 'essential') !== false)) {
                    $is_oil = true;
                }
                
                // If it is not an oil, and name or category contains diffuser, then they purchased a diffuser before
                if (!$is_oil && (in_array('diffuser', array_map('strtolower', (array)$cats)) || stripos($product->get_name(), 'diffuser') !== false)) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

// Apply Diffuser Essential Oil promo pricing in WooCommerce cart
add_action('woocommerce_before_calculate_totals', 'ruut_apply_diffuser_oil_bundle_discount', 20, 1);
function ruut_apply_diffuser_oil_bundle_discount($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    $promo_mode = get_option('ruut_diffuser_promo_mode', 'first_purchase_free');
    $bundle_pct = (float) get_option('ruut_diffuser_bundle_discount_pct', '40');
    if ($promo_mode === 'disabled') return;

    // Check if customer has already purchased a diffuser (if in first_purchase_free mode)
    if ($promo_mode === 'first_purchase_free') {
        $customer_id = get_current_user_id();
        $billing_email = '';
        
        if (isset($_POST['billing_email'])) {
            $billing_email = sanitize_email($_POST['billing_email']);
        } elseif (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
            if (isset($post_data['billing_email'])) {
                $billing_email = sanitize_email($post_data['billing_email']);
            }
        }
        
        if (empty($billing_email) && function_exists('WC') && WC()->checkout) {
            $billing_email = WC()->checkout->get_value('billing_email');
        }
        
        $already_purchased = false;
        if ($customer_id > 0) {
            $already_purchased = ruut_has_customer_purchased_diffuser($customer_id);
        }
        if (!$already_purchased && !empty($billing_email)) {
            $already_purchased = ruut_has_customer_purchased_diffuser($billing_email);
        }
        
        if ($already_purchased) {
            return; // Already purchased a diffuser, so no promo is applied
        }
    }

    $has_diffuser = false;
    $has_oil = false;

    foreach ($cart->get_cart() as $cart_item) {
        $prod = isset($cart_item['data']) ? $cart_item['data'] : null;
        if ($prod && is_object($prod)) {
            $cats = wp_get_post_terms($prod->get_id(), 'product_cat', array('fields' => 'names'));
            $is_oil = false;
            foreach ((array)$cats as $cat_name) {
                if (stripos($cat_name, 'oil') !== false) {
                    $is_oil = true;
                    break;
                }
            }
            if (!$is_oil && (stripos($prod->get_name(), 'oil') !== false || stripos($prod->get_name(), 'essential') !== false)) {
                $is_oil = true;
            }

            if ($is_oil) {
                $has_oil = true;
            } elseif (in_array('diffuser', array_map('strtolower', (array)$cats)) || stripos($prod->get_name(), 'diffuser') !== false) {
                $has_diffuser = true;
            }
        }
    }

    // Auto-add default oil if diffuser is in cart but no oil is present (first time purchase only)
    if ($has_diffuser && !$has_oil && $promo_mode === 'first_purchase_free') {
        $oil_id = 0;
        
        $oil_cats = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'fields' => 'slugs'
        ));
        $oil_slugs = array();
        if (!is_wp_error($oil_cats) && !empty($oil_cats)) {
            foreach ($oil_cats as $slug) {
                if (stripos($slug, 'oil') !== false) {
                    $oil_slugs[] = $slug;
                }
            }
        }
        if (empty($oil_slugs)) {
            $oil_slugs = array('essential-oils', 'essential-oil', 'oils', 'oil', 'essential-oil-blends', 'diffuser-oil', 'diffuser-oils');
        }

        $oils = wc_get_products(array(
            'status'   => 'publish',
            'limit'    => 1,
            'category' => $oil_slugs,
        ));
        
        if (empty($oils)) {
            $oils = wc_get_products(array(
                'status' => 'publish',
                'limit'  => 1,
                's'      => 'oil',
            ));
        }

        if (!empty($oils)) {
            $default_oil = reset($oils);
            $oil_id = $default_oil->get_id();
        }

        if ($oil_id > 0) {
            static $adding_default_oil = false;
            if (!$adding_default_oil) {
                $adding_default_oil = true;
                $cart->add_to_cart($oil_id, 1);
                $adding_default_oil = false;
                
                // Allow recursive calculation with the new item in cart
                $cart->calculate_totals();
                return;
            }
        }
    }

    if (!$has_diffuser) return;

    // Apply promo price to 1 essential oil item in cart
    $oil_discounted = false;
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if ($oil_discounted) break;
        $prod = isset($cart_item['data']) ? $cart_item['data'] : null;
        if ($prod && is_object($prod)) {
            $cats = wp_get_post_terms($prod->get_id(), 'product_cat', array('fields' => 'names'));
            $is_oil = false;
            foreach ((array)$cats as $cat_name) {
                if (stripos($cat_name, 'oil') !== false) {
                    $is_oil = true;
                    break;
                }
            }
            if (!$is_oil && (stripos($prod->get_name(), 'oil') !== false || stripos($prod->get_name(), 'essential') !== false)) {
                $is_oil = true;
            }

            if ($is_oil) {
                $orig_price = (float)$prod->get_regular_price() ?: (float)$prod->get_price();
                if ($promo_mode === 'first_purchase_free') {
                    $cart_item['data']->set_price(0);
                } else if ($promo_mode === 'bundle_discount') {
                    $discounted_price = max(0, round($orig_price * (1 - ($bundle_pct / 100)), 2));
                    $cart_item['data']->set_price($discounted_price);
                }
                $oil_discounted = true;
            }
        }
    }
}

// Hook into WooCommerce before calculate totals to enforce free gift offer
add_action( 'woocommerce_before_calculate_totals', 'ruut_apply_free_gift_discount', 10, 1 );
function ruut_apply_free_gift_discount( $cart ) {
    static $doing_free_gift = false;
    if ( $doing_free_gift ) {
        return;
    }
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $doing_free_gift = true;
    try {
        $enabled = get_option('ruut_progress_bar_enabled', 'yes');
    
    // 1. Calculate regular items quantity (sum of quantities of non-gifts in qualifying categories)
    $regular_items_qty = ruut_get_qualifying_cart_items_qty( $cart );

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
        // Compulsory Gift product ID — ONLY if admin explicitly set one (not "-- None --")
        if ( ! empty($active_threshold['compulsory_gift_id']) && intval($active_threshold['compulsory_gift_id']) > 0 ) {
            $comp_id = intval($active_threshold['compulsory_gift_id']);
            $comp_prod = wc_get_product($comp_id);
            if ( $comp_prod && $comp_prod->is_purchasable() ) {
                $target_gift_product_ids[] = $comp_id;
            }
        }
        // Random Tester Gift product ID from pool
        if ( ! empty($active_threshold['random_gift_pool']) && is_array($active_threshold['random_gift_pool']) ) {
            $random_id = ruut_get_highest_stock_product_id($active_threshold['random_gift_pool']);
            if ($random_id > 0) {
                $target_gift_product_ids[] = intval($random_id);
            }
        }
        $target_gift_product_ids = array_unique(array_filter($target_gift_product_ids));
    }

    // 5. Check cart items — ONLY manage items explicitly added as gifts (ruut_free_gift === true).
    $existing_gift_product_ids = array();
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        // Skip non-gift items entirely
        if ( ! isset( $cart_item['ruut_free_gift'] ) || $cart_item['ruut_free_gift'] !== true ) {
            continue;
        }

        $prod_id = intval($cart_item['product_id']);

        if ( in_array($prod_id, $target_gift_product_ids) && ! in_array($prod_id, $existing_gift_product_ids) ) {
            // Valid gift: keep it, enforce quantity = 1, price = 0
            if ( $cart_item['quantity'] != 1 ) {
                $cart->set_quantity( $cart_item_key, 1 );
            }
            $cart->cart_contents[$cart_item_key]['data']->set_price( 0 );
            $existing_gift_product_ids[] = $prod_id;
        } else {
            // Invalid gift or duplicate — remove
            $cart->remove_cart_item( $cart_item_key );
        }
    }

    // 6. Add missing target gifts
    foreach ( $target_gift_product_ids as $gift_id ) {
        if ( ! in_array($gift_id, $existing_gift_product_ids) ) {
            $cart->add_to_cart( $gift_id, 1, 0, array(), array( 'ruut_free_gift' => true ) );
        }
    }
    } finally {
        $doing_free_gift = false;
    }
}

// Persist custom cart item data in session
add_filter( 'woocommerce_get_cart_item_from_session', 'ruut_get_cart_item_from_session', 10, 2 );
function ruut_get_cart_item_from_session( $cart_item, $values ) {
    if ( isset( $values['ruut_free_gift'] ) ) {
        $cart_item['ruut_free_gift'] = $values['ruut_free_gift'];
    }
    return $cart_item;
}

// Hook into WooCommerce calculate fees to apply dynamic flat discount
add_action( 'woocommerce_cart_calculate_fees', 'ruut_apply_dynamic_threshold_discounts', 10, 1 );
function ruut_apply_dynamic_threshold_discounts( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $enabled = get_option('ruut_progress_bar_enabled', 'yes');
    if ( $enabled !== 'yes' || ! empty($cart->get_applied_coupons()) ) {
        return;
    }

    // 1. Calculate regular items quantity in qualifying categories
    $regular_items_qty = ruut_get_qualifying_cart_items_qty( $cart );

    // 2. Fetch configured thresholds
    $thresholds = ruut_get_configured_offer_thresholds();
    
    // 3. Find active threshold level
    $active_threshold = null;
    foreach ( $thresholds as $t ) {
        if ( intval($t['quantity']) <= $regular_items_qty ) {
            if ( empty($active_threshold) || intval($t['quantity']) > intval($active_threshold['quantity']) ) {
                $active_threshold = $t;
            }
        }
    }

    // 4. Apply discount as fee if set
    if ( $active_threshold && ! empty($active_threshold['discount']) ) {
        $discount = floatval($active_threshold['discount']);
        if ( $discount > 0 ) {
            $cart->add_fee( __('Ritual Benefit', 'ruut-core'), -$discount );
        }
    }
}

// Get Cart
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

// Add to Cart
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

// Sync Cart Bulk
add_action('wp_ajax_ruut_sync_cart', 'ruut_ajax_sync_cart');
add_action('wp_ajax_nopriv_ruut_sync_cart', 'ruut_ajax_sync_cart');
function ruut_ajax_sync_cart() {
    try {
        ruut_ensure_cart();
        $cart_items_raw = isset($_POST['cart_items']) ? wp_unslash($_POST['cart_items']) : '';
        $items = json_decode($cart_items_raw, true);
        
        if (is_array($items)) {
            WC()->cart->empty_cart();
            foreach ($items as $item) {
                // Ignore gifts as they are dynamically recalculated
                if (isset($item['is_gift']) && $item['is_gift']) {
                    continue;
                }
                
                $product_id = isset($item['id']) ? intval($item['id']) : 0;
                $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
                
                if ($product_id > 0) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        if ($product->is_type('variation')) {
                            $parent_id = $product->get_parent_id();
                            WC()->cart->add_to_cart($parent_id, $quantity, $product_id);
                        } else {
                            WC()->cart->add_to_cart($product_id, $quantity);
                        }
                    }
                }
            }
            wp_send_json_success(ruut_get_cart_response());
        } else {
            wp_send_json_error('Invalid cart items data');
        }
    } catch (Throwable $e) {
        wp_send_json_error('Fatal Error: ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
    }
}

// Update Cart Qty
add_action('wp_ajax_ruut_update_cart_qty', 'ruut_ajax_update_cart_qty');
add_action('wp_ajax_nopriv_ruut_update_cart_qty', 'ruut_ajax_update_cart_qty');
function ruut_ajax_update_cart_qty() {
    try {
        ruut_ensure_cart();
        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $product_id = 0;

        if (strpos($cart_item_key, 'product-') === 0) {
            $product_id = intval(str_replace('product-', '', $cart_item_key));
            $cart_item_key = '';
        }

        if ($cart_item_key) {
            WC()->cart->set_quantity($cart_item_key, $quantity);
            wp_send_json_success(ruut_get_cart_response());
        } elseif ($product_id > 0) {
            $found_key = '';
            foreach (WC()->cart->get_cart() as $key => $item) {
                if (intval($item['product_id']) === $product_id && ! (isset($item['ruut_free_gift']) && $item['ruut_free_gift'] === true)) {
                    $found_key = $key;
                    break;
                }
            }
            if ($found_key) {
                WC()->cart->set_quantity($found_key, $quantity);
            } else {
                WC()->cart->add_to_cart($product_id, $quantity);
            }
            wp_send_json_success(ruut_get_cart_response());
        }
        wp_send_json_error('Invalid item');
    } catch (Throwable $e) {
        wp_send_json_error('Fatal: ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    }
}

// Remove Cart Item
add_action('wp_ajax_ruut_remove_cart_item', 'ruut_ajax_remove_cart_item');
add_action('wp_ajax_nopriv_ruut_remove_cart_item', 'ruut_ajax_remove_cart_item');
function ruut_ajax_remove_cart_item() {
    try {
        ruut_ensure_cart();
        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        $product_id = 0;

        if (strpos($cart_item_key, 'product-') === 0) {
            $product_id = intval(str_replace('product-', '', $cart_item_key));
            $cart_item_key = '';
        }

        if ($cart_item_key) {
            WC()->cart->remove_cart_item($cart_item_key);
            wp_send_json_success(ruut_get_cart_response());
        } elseif ($product_id > 0) {
            $found_key = '';
            foreach (WC()->cart->get_cart() as $key => $item) {
                if (intval($item['product_id']) === $product_id && ! (isset($item['ruut_free_gift']) && $item['ruut_free_gift'] === true)) {
                    $found_key = $key;
                    break;
                }
            }
            if ($found_key) {
                WC()->cart->remove_cart_item($found_key);
            }
            wp_send_json_success(ruut_get_cart_response());
        }
        wp_send_json_error('Invalid item');
    } catch (Throwable $e) {
        wp_send_json_error('Fatal: ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    }
}
