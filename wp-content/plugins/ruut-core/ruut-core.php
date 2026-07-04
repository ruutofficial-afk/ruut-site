<?php
/**
 * Plugin Name: RUUT Core System
 * Description: Unified WordPress, WooCommerce Customizations & Headless REST API Handler (Safe Edition)
 * Version: 1.0.0
 * Author: RUUT
 * 
 * This file combines:
 * 1. Standard WooCommerce My Account page custom tabs, OTP auth, onboarding flow, and dashboard layout.
 * 2. Headless REST API endpoints (/ruut/v1) for the static landing page's My Account panel.
 * 3. Direct checkout multi-cart synchronizer.
 * 4. Dynamic CORS credentials configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ===================================================================================
// PART 2: DYNAMIC ANNOUNCEMENT BAR
// ===================================================================================

// Register Admin Menu
add_action('admin_menu', 'ruut_register_admin_menu');
function ruut_register_admin_menu() {
    add_menu_page(
        'RUUT Settings',
        'RUUT Settings',
        'manage_options',
        'ruut-settings',
        'ruut_settings_page_html',
        'dashicons-store',
        30
    );
}

// Register Settings
add_action('admin_init', 'ruut_register_settings');
function ruut_register_settings() {
    register_setting('ruut_settings_group', 'ruut_announcement_text');
    register_setting('ruut_settings_group', 'ruut_story_title');
    register_setting('ruut_settings_group', 'ruut_story_content');
    register_setting('ruut_settings_group', 'ruut_story_image');
    register_setting('ruut_settings_group', 'ruut_progress_bar_enabled');
    register_setting('ruut_settings_group', 'ruut_offer_thresholds');
}

// Admin Page HTML
function ruut_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>RUUT Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('ruut_settings_group');
            do_settings_sections('ruut_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Announcement Bar Texts (Separate by | for multiple messages)</th>
                    <td>
                        <textarea name="ruut_announcement_text" rows="5" cols="50" class="large-text"><?php echo esc_attr(get_option('ruut_announcement_text', '✨ EXPLORE THE ATLAS COLLECTION ✨ | 🌿 FREE SHIPPING ON ALL ORDERS ABOVE ₹1500 🌿')); ?></textarea>
                        <p class="description">These will scroll across the top announcement bar on the landing page.</p>
                    </td>
                </tr>

                
                <tr valign="top" style="background:#f0f0f0; padding:10px 15px; font-weight:bold;">
                    <th scope="row" colspan="2">PROGRESS BAR & GIFT OFFER SETTINGS</th>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable Progress Bar & Gift Offer</th>
                    <td>
                        <select name="ruut_progress_bar_enabled">
                            <option value="yes" <?php selected(get_option('ruut_progress_bar_enabled', 'yes'), 'yes'); ?>>Enabled</option>
                            <option value="no" <?php selected(get_option('ruut_progress_bar_enabled', 'yes'), 'no'); ?>>Disabled</option>
                        </select>
                        <p class="description">Toggle to enable or disable the progress bar and free gift features in the mini-cart and cart page.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Dynamic Offer Thresholds</th>
                    <td>
                        <table class="wp-list-table widefat fixed striped" id="ruut-thresholds-table" style="max-width: 1000px; margin-bottom: 10px;">
                            <thead>
                                <tr>
                                    <th style="width: 15%; padding: 10px;">Qty Threshold</th>
                                    <th style="width: 35%; padding: 10px;">Compulsory Free Product</th>
                                    <th style="width: 25%; padding: 10px;">Random Gift Pool (Select Multiple)</th>
                                    <th style="width: 15%; padding: 10px;">Discount (₹)</th>
                                    <th style="width: 10%; padding: 10px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $thresholds_json = get_option('ruut_offer_thresholds', '[]');
                                $thresholds = json_decode($thresholds_json, true);
                                if (!is_array($thresholds)) {
                                    $thresholds = array();
                                }
                                
                                // Fetch all products for select
                                $all_prods = array();
                                if (function_exists('wc_get_products')) {
                                    $products_obj = wc_get_products(array('limit' => -1, 'status' => 'publish'));
                                    foreach ($products_obj as $prod) {
                                        $all_prods[] = array(
                                            'id' => $prod->get_id(),
                                            'name' => $prod->get_name()
                                        );
                                    }
                                }

                                foreach ($thresholds as $index => $t) {
                                    $qty = intval($t['quantity']);
                                    $compulsory_id = isset($t['compulsory_gift_id']) ? intval($t['compulsory_gift_id']) : 0;
                                    $random_pool = isset($t['random_gift_pool']) && is_array($t['random_gift_pool']) ? array_map('intval', $t['random_gift_pool']) : array();
                                    $discount = isset($t['discount']) ? floatval($t['discount']) : 0;
                                    ?>
                                    <tr class="threshold-row">
                                        <td style="padding: 10px;">
                                            <input type="number" class="row-qty" value="<?php echo esc_attr($qty); ?>" min="1" step="1" style="width: 90%;" required />
                                        </td>
                                        <td style="padding: 10px;">
                                            <select class="row-compulsory" style="width: 95%;">
                                                <option value="">-- None --</option>
                                                <?php
                                                foreach ($all_prods as $p) {
                                                    echo '<option value="' . esc_attr($p['id']) . '" ' . selected($compulsory_id, $p['id'], false) . '>' . esc_html($p['name']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td style="padding: 10px;">
                                            <div class="row-random-pool-wrap" style="max-height: 90px; overflow-y: auto; border: 1px solid #ccc; padding: 6px; background: #fff; border-radius: 4px;">
                                                <?php
                                                foreach ($all_prods as $p) {
                                                    $checked = in_array($p['id'], $random_pool) ? 'checked="checked"' : '';
                                                    echo '<label style="display: block; margin-bottom: 4px; font-size: 12px; cursor: pointer; line-height: 1.4;">';
                                                    echo '<input type="checkbox" class="pool-checkbox" value="' . esc_attr($p['id']) . '" ' . $checked . ' style="margin-right: 6px; vertical-align: middle;" /> ';
                                                    echo esc_html($p['name']);
                                                    echo '</label>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td style="padding: 10px;">
                                            <input type="number" class="row-discount" value="<?php echo esc_attr($discount); ?>" min="0" step="1" style="width: 90%;" />
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <button type="button" class="button remove-row-btn" style="color: #a00; border-color: #a00;">Remove</button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                        <button type="button" class="button button-primary" id="ruut-add-threshold-btn">Add Threshold Level</button>
                        <input type="hidden" name="ruut_offer_thresholds" id="ruut_offer_thresholds" value="<?php echo esc_attr($thresholds_json); ?>" />
                        <p class="description" style="margin-top: 10px;">Configure multiple tier-based thresholds. You can add a compulsory free product, select a pool for the random tester product, and/or apply a flat discount for each quantity level. Benefits stack together. The random tester product will dynamically choose the item with the highest stock level from the pool.</p>
                    </td>
                </tr>
                
                <tr valign="top" style="background:#f0f0f0; padding:10px 15px; font-weight:bold;">
                    <th scope="row" colspan="2">THE RUUT STORY SETTINGS</th>
                </tr>
                <tr valign="top">
                    <th scope="row">Story Title</th>
                    <td>
                        <input type="text" name="ruut_story_title" value="<?php echo esc_attr(get_option('ruut_story_title', 'The Ruut Story')); ?>" class="large-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Story Content</th>
                    <td>
                        <?php 
                        $story_content = get_option('ruut_story_content', 'Inspired by the quiet mornings in Landour, Mussoorie...');
                        wp_editor($story_content, 'ruut_story_content', array('textarea_rows' => 10)); 
                        ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Story Featured Image</th>
                    <td>
                        <input type="text" name="ruut_story_image" id="ruut_story_image" value="<?php echo esc_attr(get_option('ruut_story_image')); ?>" class="regular-text" />
                        <input type="button" id="ruut_story_image_btn" class="button-secondary" value="Upload Image" />
                        <p class="description">Select or upload the featured image for the Ruut Story section.</p>
                        <div id="ruut_story_image_preview" style="margin-top: 10px;">
                            <?php if ($img = get_option('ruut_story_image')): ?>
                                <img src="<?php echo esc_url($img); ?>" style="max-width: 300px; max-height: 200px; display: block; border: 1px solid #ccc; padding: 4px;" />
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Expose available products to JS
        const availableProducts = <?php echo json_encode($all_prods); ?>;
        
        // Remove row click handler
        $('#ruut-thresholds-table').on('click', '.remove-row-btn', function() {
            $(this).closest('tr').remove();
        });
        
        // Add row click handler
        $('#ruut-add-threshold-btn').on('click', function() {
            let optionsHtml = '';
            let checkboxHtml = '';
            availableProducts.forEach(function(p) {
                optionsHtml += '<option value="' + p.id + '">' + p.name + '</option>';
                checkboxHtml += `
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; cursor: pointer; line-height: 1.4;">
                        <input type="checkbox" class="pool-checkbox" value="${p.id}" style="margin-right: 6px; vertical-align: middle;" />
                        ${p.name}
                    </label>
                `;
            });
            let compulsoryOptions = '<option value="">-- None --</option>' + optionsHtml;
            
            const newRow = `
                <tr class="threshold-row">
                    <td style="padding: 10px;">
                        <input type="number" class="row-qty" value="1" min="1" step="1" style="width: 90%;" required />
                    </td>
                    <td style="padding: 10px;">
                        <select class="row-compulsory" style="width: 95%;">
                            ${compulsoryOptions}
                        </select>
                    </td>
                    <td style="padding: 10px;">
                        <div class="row-random-pool-wrap" style="max-height: 90px; overflow-y: auto; border: 1px solid #ccc; padding: 6px; background: #fff; border-radius: 4px;">
                            ${checkboxHtml}
                        </div>
                    </td>
                    <td style="padding: 10px;">
                        <input type="number" class="row-discount" value="0" min="0" step="1" style="width: 90%;" />
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <button type="button" class="button remove-row-btn" style="color: #a00; border-color: #a00;">Remove</button>
                    </td>
                </tr>
            `;
            $('#ruut-thresholds-table tbody').append(newRow);
        });
        
        // On form submit, serialize table to JSON
        $('form').on('submit', function() {
            const thresholds = [];
            $('#ruut-thresholds-table tbody tr.threshold-row').each(function() {
                const qty = parseInt($(this).find('.row-qty').val());
                const compulsory = $(this).find('.row-compulsory').val();
                
                const randomPool = [];
                $(this).find('.pool-checkbox:checked').each(function() {
                    randomPool.push(parseInt($(this).val()));
                });
                
                const discount = parseFloat($(this).find('.row-discount').val()) || 0;
                
                if (!isNaN(qty) && qty > 0) {
                    thresholds.push({
                        quantity: qty,
                        compulsory_gift_id: compulsory,
                        random_gift_pool: randomPool,
                        discount: discount
                    });
                }
            });
            
            // Sort ascending by quantity
            thresholds.sort(function(a, b) {
                return a.quantity - b.quantity;
            });
            
            $('#ruut_offer_thresholds').val(JSON.stringify(thresholds));
        });
    });
    </script>
    <?php
}

// REST Endpoint for Announcement
add_action('rest_api_init', function () {
    register_rest_route('ruut/v1', '/announcement', array(
        'methods' => 'GET',
        'callback' => 'ruut_get_announcement',
        'permission_callback' => '__return_true',
    ));
});

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

// ===================================================================================
// PART 1: WOOCOMMERCE AJAX CART SYNCHRONIZATION
// ===================================================================================

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
    // Calculate regular items quantity to compute the volume discount
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
        'offer_thresholds' => $api_thresholds,

        // WooCommerce direct response fields
        'coupons' => $cart->get_applied_coupons(),
        'subtotal' => (float) $cart->subtotal,
        'discount_total' => (float) $cart->get_discount_total(),
        'total' => (float) $cart->total,
        'notices' => !empty($removed_coupon_message) ? array($removed_coupon_message) : array()
    );
}

// Helper to get offer thresholds with fallback defaults
function ruut_get_configured_offer_thresholds() {
    $thresholds_json = get_option('ruut_offer_thresholds', '');
    $thresholds = json_decode($thresholds_json, true);
    if (!is_array($thresholds) || empty($thresholds)) {
        $fallback_gift_id = ruut_get_highest_stock_product_id(array());
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

// Helper to select the product with the highest stock level from an array of IDs
function ruut_get_highest_stock_product_id($product_ids = array()) {
    if (empty($product_ids) || !is_array($product_ids)) {
        if (!function_exists('wc_get_products')) {
            return 0;
        }
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit'  => -1,
        ));
        if (empty($products)) {
            return 0;
        }
        $product_ids = array_map(function($p) { return $p->get_id(); }, $products);
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

// Hook into WooCommerce before calculate totals to enforce free gift offer
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
    //    NEVER auto-convert a paid item into a gift, even if product IDs match.
    //    WooCommerce generates unique cart keys when cart item data differs (ruut_free_gift metadata),
    //    so a paid item and a gift item with the same product_id will have different keys and coexist safely.
    $existing_gift_product_ids = array();
    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        // Skip non-gift items entirely — paid items are never touched
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
            // Invalid gift (not in target list) or duplicate gift — remove
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

    // 1. Calculate regular items quantity
    $regular_items_qty = 0;
    foreach ( $cart->get_cart() as $cart_item ) {
        if ( ! ( isset( $cart_item['ruut_free_gift'] ) && $cart_item['ruut_free_gift'] === true ) ) {
            $regular_items_qty += $cart_item['quantity'];
        }
    }

    // 2. Fetch configured thresholds
    $thresholds_json = get_option('ruut_offer_thresholds', '[]');
    $thresholds = json_decode($thresholds_json, true);
    if ( ! is_array($thresholds) ) {
        $thresholds = array();
    }
    
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

// Update Cart Qty
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

// Remove Cart Item
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


// ===================================================================================
// PART 4: STANDARD WOOCOMMERCE MY ACCOUNT STYLING & GUEST OTP
// ===================================================================================

if (!function_exists('ruut_change_login_title')) {
    add_filter( 'woocommerce_endpoint_customer-logout_title', 'ruut_change_login_title' );
    function ruut_change_login_title( $title ) {
        return 'Login / Register';
    }
}

if (!function_exists('ruut_rename_username_label')) {
    add_filter('gettext', 'ruut_rename_username_label', 10, 3);
    function ruut_rename_username_label($translated_text, $text, $domain) {
        if ($domain === 'woocommerce' && is_account_page() && !is_user_logged_in()) {
            if ($text === 'Username or email address') {
                return 'Email address';
            }
        }
        return $translated_text;
    }
}

if (!function_exists('ruut_account_custom_css')) {
    add_action('wp_head', 'ruut_account_custom_css');
    function ruut_account_custom_css() {
        if ( is_account_page() ) {
            ?>
            <style>
                body.woocommerce-account:not(.logged-in) .woocommerce:has(.woocommerce-form-login) {
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: center !important;
                    align-items: center !important;
                    min-height: calc(100vh - 100px) !important;
                }
                .woocommerce-form-login {
                    width: 100% !important;
                    max-width: 400px !important;
                    margin: 0 auto !important;
                    position: relative;
                }
                .woocommerce-form-login::after {
                    content: "";
                    display: block;
                    height: 160px;
                    transition: height 0.3s ease;
                }
                .woocommerce-form-login.otp-active::after {
                    height: 0px !important;
                    display: none;
                }
                body.woocommerce-account.logged-in .woocommerce:has(.woocommerce-MyAccount-navigation) {
                    gap: 40px !important;
                }
                .woocommerce-MyAccount-content {
                    max-width: 900px;
                }
            </style>
            <?php
        }
    }
}

if (!function_exists('ruut_custom_login_form_otp')) {
    add_action( 'woocommerce_login_form_end', 'ruut_custom_login_form_otp' );
    function ruut_custom_login_form_otp() {
        ?>
        <button type="button" class="button" id="send_otp_btn">Get OTP</button>
        <div id="otp_container" style="display:none;">
            <p class="form-row form-row-wide">
                <label for="otp_full"><?php esc_html_e( 'Enter OTP', 'ruut' ); ?></label>
                <div class="otp-input-group">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                </div>
                <input type="hidden" name="otp" id="otp_full" />
            </p>
            <button type="button" class="button" id="verify_otp_btn">Verify OTP</button>
            <div id="resend_otp_link" class="disabled">Resend OTP in <span id="timer">30</span>s</div>
        </div>
        <div id="otp_message" class="otp-status-message" style="display:none;"></div>
        <?php
    }
}

if (!function_exists('ruut_handle_otp_request')) {
    add_action( 'wp_ajax_nopriv_ruut_request_otp', 'ruut_handle_otp_request' );
    add_action( 'wp_ajax_ruut_request_otp', 'ruut_handle_otp_request' );
    function ruut_handle_otp_request() {
        check_ajax_referer('ruut_otp_nonce', 'security');
        $email = isset($_POST['identifier']) ? sanitize_email($_POST['identifier']) : '';
        if ( ! is_email($email) ) {
            wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
        }

        $otp = wp_rand( 100000, 999999 );
        set_transient( 'ruut_otp_' . md5($email), $otp, 600 );

        $subject = "{$otp} - Your Exclusive Access Code - ṚUUT"; 
        $message = "
        <html>
        <head>
            <style>
                body { font-family: 'Georgia', serif; color: #4A3324; background-color: #E6DED1; padding: 40px; }
                .container { background-color: #FFFFFF; padding: 40px; max-width: 500px; margin: 0 auto; border: 1px solid #D1C7B7; text-align: center; }
                h2 { color: #4A3324; font-size: 24px; font-weight: normal; margin-bottom: 20px; }
                p { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #664436; margin-bottom: 20px; }
                .otp-code { display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #895158; margin: 20px 0; padding: 15px 30px; background-color: #EADBC8; border: 1px solid #4A3324; }
                .footer { margin-top: 40px; font-style: italic; color: #4A3324; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Welcome to ṚUUT</h2>
                <p>We are delighted to have you here. Step into a world of curated elegance and natural harmony. Please use the secure access code below to continue your journey with us.</p>
                <div class='otp-code'>{$otp}</div>
                <p>This code is valid for the next 10 minutes. For your security, please do not share it with anyone.</p>
                <div class='footer'>Warm regards,<br><strong>The ṚUUT Team</strong></div>
            </div>
        </body>
        </html>";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $mail_sent = wp_mail( $email, $subject, $message, $headers );

        if ($mail_sent) {
            wp_send_json_success( array( 'message' => 'OTP sent! Please check your inbox (and spam folder).' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to send OTP. Please try again.' ) );
        }
    }
}

if (!function_exists('ruut_authenticate_with_otp')) {
    add_filter( 'authenticate', 'ruut_authenticate_with_otp', 30, 3 );
    function ruut_authenticate_with_otp( $user, $username, $password ) {
        if ( isset( $_POST['otp'] ) && ! empty( $_POST['otp'] ) ) {
            $email = isset( $_POST['username'] ) ? sanitize_email( $_POST['username'] ) : '';
            $submitted_otp = sanitize_text_field( $_POST['otp'] );
            $stored_otp = get_transient( 'ruut_otp_' . md5($email) );

            if ( $stored_otp && $stored_otp == $submitted_otp ) {
                delete_transient( 'ruut_otp_' . md5($email) );
                $auth_user = get_user_by( 'email', $email );

                if ( $auth_user ) {
                    return $auth_user;
                } else {
                    $password = wp_generate_password();
                    $new_user_id = wp_create_user( $email, $password, $email );
                    if ( is_wp_error( $new_user_id ) ) {
                        return new WP_Error( 'registration_failed', __( 'Error creating account.', 'woocommerce' ) );
                    }
                    $new_user = new WP_User( $new_user_id );
                    $new_user->set_role( 'customer' );
                    update_user_meta( $new_user_id, '_ruut_needs_onboarding', 'yes' );
                    return $new_user;
                }
            } else {
                return new WP_Error( 'invalid_otp', __( 'The OTP entered is invalid or has expired.', 'woocommerce' ) );
            }
        }
        return $user;
    }
}

if (!function_exists('ruut_enqueue_my_account_scripts')) {
    add_action( 'wp_enqueue_scripts', 'ruut_enqueue_my_account_scripts' );
    function ruut_enqueue_my_account_scripts() {
        if ( is_account_page() || is_checkout() || is_cart() || is_woocommerce() ) {
            if ( ! is_user_logged_in() ) {
                wp_enqueue_script( 'ruut-login-otp', plugins_url( 'assets/checkout.js', __FILE__ ), array('jquery'), '4.0', true );
                wp_localize_script( 'ruut-login-otp', 'ruut_ajax', array(
                    'url'   => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'ruut_otp_nonce' )
                ) );
            }
            wp_enqueue_script( 'ruut-account-scripts', plugins_url( 'assets/checkout.js', __FILE__ ), array('jquery'), '4.0', true );
            wp_localize_script( 'ruut-account-scripts', 'ruut_account_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'home_url' => home_url('/')
            ));
            wp_enqueue_style( 'ruut-account-styles', plugins_url( 'assets/checkout.css', __FILE__ ), array(), '4.0' );
        }
    }
}

// ===================================================================================
// PART 5: WOOCOMMERCE ACCOUNT ONBOARDING OVERLAY & USER DETAILS
// ===================================================================================

if (!function_exists('ruut_render_onboarding_overlay')) {
    add_action( 'wp_footer', 'ruut_render_onboarding_overlay' );
    function ruut_render_onboarding_overlay() {
        if ( is_account_page() && is_user_logged_in() ) {
            if ( get_user_meta( get_current_user_id(), '_ruut_needs_onboarding', true ) === 'yes' ) {
                ?>
                <style>
                    body { overflow: hidden !important; }
                    .ruut-fullscreen-overlay {
                        position: fixed;
                        top: 0; left: 0; right: 0; bottom: 0;
                        background-color: var(--ruut-bg, #E6DED1);
                        z-index: 99999999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        overflow-y: auto;
                        padding: 40px 20px;
                    }
                    .ruut-fullscreen-overlay * { box-sizing: border-box; }
                    .ruut-onboarding-wrapper { width: 100%; max-width: 1000px; margin: auto; }
                    .ruut-onboarding-maintitle { grid-column: 1 / -1; text-align: center; font-family: var(--ruut-font-serif, 'Georgia', serif); font-size: 2rem; color: var(--ruut-coffee, #4A3324); margin: 0 0 40px 0; font-weight: normal; }
                    .ruut-onboarding-form { display: grid; grid-template-columns: 1fr 1fr; column-gap: 80px; align-items: start; }
                    .ruut-onboarding-col { display: flex; flex-direction: column; gap: 30px; }
                    .ruut-fullscreen-overlay label { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--ruut-coffee, #4A3324); margin-bottom: 8px; font-weight: 600; text-align: left; }
                    .ruut-form-row { width: 100%; }
                    .ruut-form-columns { display: flex; justify-content: space-between; gap: 30px; width: 100%; }
                    .ruut-form-col { flex: 1; }
                    .ruut-fullscreen-overlay input[type="text"], .ruut-fullscreen-overlay select.ruut-state-select {
                        width: 100% !important; padding: 10px 0 !important; border: none !important; border-bottom: 1px solid var(--ruut-coffee, #4A3324) !important; background-color: transparent !important; color: var(--ruut-coffee, #4A3324) !important; font-size: 0.95rem !important; box-shadow: none !important; border-radius: 0 !important; font-family: var(--ruut-font-sans, 'Helvetica Neue', Helvetica, Arial, sans-serif);
                    }
                    .ruut-fullscreen-overlay input[type="text"]:focus, .ruut-fullscreen-overlay select.ruut-state-select:focus { outline: none !important; border-bottom: 2px solid var(--ruut-coffee, #4A3324) !important; }
                    .ruut-fullscreen-overlay select.ruut-state-select { appearance: none; -webkit-appearance: none; -moz-appearance: none; cursor: pointer; }
                    .ruut-onboarding-submit-wrapper { grid-column: 1 / -1; display: flex; justify-content: center; margin-top: 40px; }
                    .ruut-fullscreen-overlay .ruut-onboarding-submit { background-color: var(--ruut-burnt-rose, #895158) !important; color: #ffffff !important; border: none !important; width: 100%; max-width: 400px; padding: 16px !important; font-size: 0.9rem; cursor: pointer; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; transition: all 0.3s ease; border-radius: 8px !important; }
                    .ruut-fullscreen-overlay .ruut-onboarding-submit:hover { background-color: var(--ruut-coffee, #4A3324) !important; }
                    @media (max-width: 768px) {
                        .ruut-onboarding-form { display: flex !important; flex-direction: column !important; row-gap: 30px; }
                        .ruut-onboarding-col { display: contents !important; }
                        .ruut-onboarding-maintitle { order: 1; }
                        .ruut-col-group-name { order: 2; }
                        .ruut-col-group-phone { order: 3; }
                        .ruut-col-group-saveas { order: 4; }
                        .ruut-col-group-address { order: 5; }
                        .ruut-col-group-landmark { order: 6; }
                        .ruut-col-group-pincity { order: 7; }
                        .ruut-col-group-state { order: 8; }
                        .ruut-onboarding-submit-wrapper { order: 9; }
                        .ruut-form-columns { flex-direction: column !important; gap: 30px !important; }
                        .ruut-onboarding-maintitle { font-size: 1.6rem; margin-bottom: 20px; }
                    }
                </style>
                <div class="ruut-fullscreen-overlay">
                    <div class="ruut-onboarding-wrapper">
                        <form method="post" action="" class="ruut-onboarding-form">
                            <?php wp_nonce_field( 'ruut_save_onboarding_action', 'ruut_onboarding_nonce' ); ?>
                            <input type="hidden" name="ruut_save_onboarding" value="1" />
                            
                            <h3 class="ruut-onboarding-maintitle">Personal Details</h3>
                            <div class="ruut-onboarding-col">
                                <div class="ruut-form-columns ruut-col-group-name">
                                    <div class="ruut-form-col">
                                        <label>First Name <span style="color: var(--ruut-accent);">*</span></label>
                                        <input type="text" name="billing_first_name" required />
                                    </div>
                                    <div class="ruut-form-col">
                                        <label>Last Name <span style="color: var(--ruut-accent);">*</span></label>
                                        <input type="text" name="billing_last_name" required />
                                    </div>
                                </div>
                                <div class="ruut-form-row ruut-col-group-phone">
                                    <label>Phone <span style="color: var(--ruut-accent);">*</span></label>
                                    <input type="text" name="billing_phone" required />
                                </div>
                                <div class="ruut-form-columns ruut-col-group-pincity">
                                    <div class="ruut-form-col">
                                        <label>Pin Code</label>
                                        <input type="text" name="shipping_postcode" />
                                    </div>
                                    <div class="ruut-form-col">
                                        <label>Town / City</label>
                                        <input type="text" name="shipping_city" />
                                    </div>
                                </div>
                                <div class="ruut-form-row ruut-col-group-state">
                                    <label>State</label>
                                    <select name="shipping_state" class="ruut-state-select">
                                        <option value="">Select...</option>
                                        <?php
                                            $states = WC()->countries->get_states( 'IN' );
                                            if ( ! empty( $states ) ) {
                                                foreach( $states as $code => $name ) {
                                                    echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="ruut-onboarding-col">
                                <div class="ruut-form-row ruut-col-group-saveas">
                                    <label>Save As (e.g. Home, Office)</label>
                                    <input type="text" name="shipping_title" placeholder="Home" />
                                </div>
                                <div class="ruut-form-row ruut-col-group-address">
                                    <label>Address</label>
                                    <input type="text" name="shipping_address_1" />
                                    <input type="text" name="shipping_address_2" style="margin-top: 15px;" />
                                </div>
                                <div class="ruut-form-row ruut-col-group-landmark">
                                    <label>Landmark</label>
                                    <input type="text" name="shipping_landmark" />
                                </div>
                            </div>
                            <div class="ruut-onboarding-submit-wrapper">
                                <button type="submit" class="button ruut-onboarding-submit">Complete Registration</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
            }
        }
    }
}

if (!function_exists('ruut_save_onboarding_data')) {
    add_action( 'template_redirect', 'ruut_save_onboarding_data' );
    function ruut_save_onboarding_data() {
        if ( is_account_page() && isset( $_POST['ruut_save_onboarding'] ) && isset($_POST['ruut_onboarding_nonce']) ) {
            if ( ! wp_verify_nonce( $_POST['ruut_onboarding_nonce'], 'ruut_save_onboarding_action' ) ) {
                return;
            }

            $user_id = get_current_user_id();
            $first_name = sanitize_text_field( $_POST['billing_first_name'] );
            $last_name  = sanitize_text_field( $_POST['billing_last_name'] );
            $phone      = sanitize_text_field( $_POST['billing_phone'] );
            
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => trim( $first_name . ' ' . $last_name ),
                'first_name'   => $first_name,
                'last_name'    => $last_name,
            ) );
            
            update_user_meta( $user_id, 'billing_first_name', $first_name );
            update_user_meta( $user_id, 'billing_last_name', $last_name );
            update_user_meta( $user_id, 'billing_phone', $phone );

            $address_title = isset($_POST['shipping_title']) && !empty($_POST['shipping_title']) ? sanitize_text_field($_POST['shipping_title']) : 'Home';
            $address_id = uniqid();

            $new_address = array(
                'id'                => $address_id,
                'title'             => $address_title,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'phone'             => $phone,
                'postcode'          => sanitize_text_field( $_POST['shipping_postcode'] ),
                'city'              => sanitize_text_field( $_POST['shipping_city'] ),
                'state'             => sanitize_text_field( $_POST['shipping_state'] ),
                'address_1'         => sanitize_text_field( $_POST['shipping_address_1'] ),
                'address_2'         => sanitize_text_field( $_POST['shipping_address_2'] ),
                'landmark'          => sanitize_text_field( $_POST['shipping_landmark'] ),
                
                // Headless legacy fields
                'billing_address_1' => sanitize_text_field( $_POST['shipping_address_1'] ),
                'billing_address_2' => sanitize_text_field( $_POST['shipping_address_2'] ),
                'billing_city'      => sanitize_text_field( $_POST['shipping_city'] ),
                'billing_postcode'  => sanitize_text_field( $_POST['shipping_postcode'] ),
                'billing_state'     => sanitize_text_field( $_POST['shipping_state'] ),
                'billing_landmark'  => sanitize_text_field( $_POST['shipping_landmark'] ),
                'billing_save_as'   => $address_title,
                'is_default'        => true
            );
            
            update_user_meta( $user_id, 'ruut_address_book', array( $new_address ) );
            delete_user_meta( $user_id, '_ruut_needs_onboarding' );

            wp_safe_redirect( home_url( '/shop' ) );
            exit;
        }
    }
}

if (!function_exists('ruut_redirect_login_to_shop')) {
    add_filter( 'woocommerce_login_redirect', 'ruut_redirect_login_to_shop', 9999, 2 );
    function ruut_redirect_login_to_shop( $redirect_to, $user ) {
        if ( strpos( $redirect_to, 'checkout' ) !== false ) {
            return $redirect_to;
        }
        return home_url( '/shop' );
    }
}

if (!function_exists('ruut_redirect_registration_to_shop')) {
    add_filter( 'woocommerce_registration_redirect', 'ruut_redirect_registration_to_shop', 9999, 1 );
    function ruut_redirect_registration_to_shop( $redirect_to ) {
        if ( strpos( $redirect_to, 'checkout' ) !== false ) {
            return $redirect_to;
        }
        return home_url( '/shop' );
    }
}

if (!function_exists('ruut_remove_my_account_menu_links')) {
    add_filter( 'woocommerce_account_menu_items', 'ruut_remove_my_account_menu_links' );
    function ruut_remove_my_account_menu_links( $menu_links ) {
        if ( isset( $menu_links['downloads'] ) ) unset( $menu_links['downloads'] );
        if ( isset( $menu_links['edit-address'] ) ) unset( $menu_links['edit-address'] );
        return $menu_links;
    }
}

if (!function_exists('ruut_custom_dashboard_layout')) {
    add_action( 'woocommerce_account_dashboard', 'ruut_custom_dashboard_layout' );
    function ruut_custom_dashboard_layout() {
        $current_user = wp_get_current_user();
        $first_name = $current_user->user_firstname ? $current_user->user_firstname : $current_user->display_name;
        $orders = wc_get_orders( array(
            'customer_id' => $current_user->ID,
            'limit'       => 1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );
        ?>
        <div class="ruut-custom-dashboard">
            <div class="ruut-dashboard-welcome">
                <h2>Welcome to the ṚUUT family, <?php echo esc_html( $first_name ); ?>.</h2>
                <p>We're honored to have you here. Your journey into curated elegance and natural harmony begins now.</p>
            </div>
            <?php if ( $orders ) : $order = $orders[0]; ?>
                <div class="ruut-recent-order-card">
                    <div class="ruut-order-header">
                        <h3>Recent Order</h3>
                        <a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">View</a>
                    </div>
                    <div class="ruut-order-images">
                        <?php 
                        $items = $order->get_items();
                        $count = 0;
                        foreach ( $items as $item ) {
                            if ( $count >= 3 ) break;
                            $product = $item->get_product();
                            if ( $product ) {
                                echo $product->get_image( 'thumbnail' );
                            }
                            $count++;
                        }
                        if ( count($items) > 3 ) {
                            echo '<span class="ruut-more-items">+' . (count($items) - 3) . '</span>';
                        }
                        ?>
                    </div>
                    <div class="ruut-order-footer">
                        <span class="ruut-order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                        <span class="ruut-order-total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                    </div>
                </div>
            <?php else : ?>
                <div class="ruut-recent-order-card empty">
                    <div class="ruut-order-header">
                        <h3>Recent Order</h3>
                    </div>
                    <p>Your curated collection awaits. You haven't placed any orders yet.</p>
                    <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button">Explore Shop</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('ruut_custom_orders_override')) {
    add_action( 'init', 'ruut_custom_orders_override' );
    function ruut_custom_orders_override() {
        remove_action( 'woocommerce_account_orders_endpoint', 'woocommerce_account_orders' );
        add_action( 'woocommerce_account_orders_endpoint', 'ruut_custom_account_orders' );
    }
}

if (!function_exists('ruut_custom_account_orders')) {
    function ruut_custom_account_orders() {
        $current_user_id = get_current_user_id();
        $four_years_ago = date('Y-m-d', strtotime('-4 years'));
        $orders = wc_get_orders( array(
            'customer_id'  => $current_user_id,
            'date_created' => '>=' . $four_years_ago,
            'limit'        => -1,
            'orderby'      => 'date',
            'order'        => 'DESC',
        ) );
        if ( empty( $orders ) ) {
            ?>
            <div class="ruut-empty-orders">
                <h3>Your Journey Begins Here</h3>
                <p>You haven't curated any rituals with us yet. Discover our collections and find your signature scent.</p>
                <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button">Explore The Collection</a>
            </div>
            <?php
        } else {
            echo '<div class="ruut-orders-grid">';
            foreach ( $orders as $order ) {
                $order_id = $order->get_id();
                $items = $order->get_items();
                $total_items = $order->get_item_count();
                ?>
                <div class="ruut-order-card">
                    <div class="ruut-order-card-header">
                        <span class="order-number">Order #<?php echo esc_html( $order->get_order_number() ); ?></span>
                        <span class="order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                    </div>
                    <div class="ruut-order-items-preview">
                        <?php 
                        $display_count = 0;
                        foreach ( $items as $item ) {
                            if ( $display_count >= 2 ) {
                                echo '<div class="ruut-more-items-text">+' . esc_html( $total_items - 2 ) . ' more item(s)</div>';
                                break;
                            }
                            $product = $item->get_product();
                            ?>
                            <div class="ruut-item-preview">
                                <?php echo $product ? $product->get_image( 'thumbnail' ) : ''; ?>
                                <div class="item-details">
                                    <span class="item-name"><?php echo esc_html( $item->get_name() ); ?></span>
                                    <span class="item-qty">Qty: <?php echo esc_html( $item->get_quantity() ); ?></span>
                                </div>
                            </div>
                            <?php
                            $display_count++;
                        }
                        ?>
                    </div>
                    <div class="ruut-order-summary">
                        <p>Total Items: <?php echo esc_html( $total_items ); ?></p>
                        <p class="total-price"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
                    </div>
                    <div class="ruut-order-card-footer">
                        <button type="button" class="ruut-show-details-btn" onclick="document.getElementById('ruut-modal-<?php echo esc_attr( $order_id ); ?>').classList.add('active');">Show Details</button>
                    </div>
                </div>
                <div id="ruut-modal-<?php echo esc_attr( $order_id ); ?>" class="ruut-modal">
                    <div class="ruut-modal-content">
                        <span class="ruut-modal-close" onclick="document.getElementById('ruut-modal-<?php echo esc_attr( $order_id ); ?>').classList.remove('active');">&times;</span>
                        <h3>Order #<?php echo esc_html( $order->get_order_number() ); ?> Details</h3>
                        <div class="ruut-modal-details-grid">
                            <div class="ruut-modal-section">
                                <h4>Order Date</h4>
                                <p><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></p>
                            </div>
                            <div class="ruut-modal-section">
                                <h4>Items</h4>
                                <?php foreach ( $items as $item ) : ?>
                                    <div class="ruut-modal-item-row">
                                        <span><?php echo esc_html( $item->get_name() ); ?> x <?php echo esc_html( $item->get_quantity() ); ?></span>
                                        <span><?php echo wp_kses_post( wc_price( $order->get_line_total( $item, true, true ) ) ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ruut-modal-totals">
                                <?php foreach ( $order->get_order_item_totals() as $key => $total ) : ?>
                                    <div class="ruut-modal-totals-row <?php echo ( 'order_total' === $key ) ? 'grand-total' : ''; ?>">
                                        <span><?php echo esc_html( $total['label'] ); ?></span>
                                        <span><?php echo wp_kses_post( $total['value'] ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        }
    }
}

// ===================================================================================
// PART 6: STANDARD ADDRESS BOOK COMPONENT & ACTIONS
// ===================================================================================

if (!function_exists('ruut_add_phone_to_account_details')) {
    add_action( 'woocommerce_edit_account_form', 'ruut_add_phone_to_account_details', 5 );
    function ruut_add_phone_to_account_details() {
        $user_id = get_current_user_id();
        $phone   = get_user_meta( $user_id, 'billing_phone', true );
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_phone">Phone Number <span style="color: var(--ruut-accent);">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_phone" id="account_phone" value="<?php echo esc_attr( $phone ); ?>" required />
        </p>
        <?php
    }
}

if (!function_exists('ruut_save_account_phone')) {
    add_action( 'woocommerce_save_account_details', 'ruut_save_account_phone', 10, 1 );
    function ruut_save_account_phone( $user_id ) {
        if ( isset( $_POST['account_phone'] ) ) {
            update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['account_phone'] ) );
        }
    }
}

if (!function_exists('ruut_render_address_book_ui')) {
    add_action( 'woocommerce_edit_account_form', 'ruut_render_address_book_ui', 10 );
    function ruut_render_address_book_ui() {
        $user_id = get_current_user_id();
        $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
        if ( ! is_array( $address_book ) ) $address_book = array();
        
        echo '<div class="ruut-address-book-wrapper">';
        echo '<h3>Saved Addresses</h3>';
        echo '<div class="ruut-address-list" id="ruut-address-list-container">';
        if ( empty( $address_book ) ) {
            echo '<p>You have no saved addresses yet.</p>';
        } else {
            foreach ( $address_book as $address ) {
                $addr_title = isset($address['title']) ? $address['title'] : (isset($address['billing_save_as']) ? $address['billing_save_as'] : 'Saved Address');
                $addr_1 = isset($address['address_1']) ? $address['address_1'] : (isset($address['billing_address_1']) ? $address['billing_address_1'] : '');
                $addr_2 = isset($address['address_2']) ? $address['address_2'] : (isset($address['billing_address_2']) ? $address['billing_address_2'] : '');
                $city = isset($address['city']) ? $address['city'] : (isset($address['billing_city']) ? $address['billing_city'] : '');
                $state = isset($address['state']) ? $address['state'] : (isset($address['billing_state']) ? $address['billing_state'] : '');
                $postcode = isset($address['postcode']) ? $address['postcode'] : (isset($address['billing_postcode']) ? $address['billing_postcode'] : '');
                $landmark = isset($address['landmark']) ? $address['landmark'] : (isset($address['billing_landmark']) ? $address['billing_landmark'] : '');
                $first = isset($address['first_name']) ? $address['first_name'] : '';
                $last = isset($address['last_name']) ? $address['last_name'] : '';
                $phone = isset($address['phone']) ? $address['phone'] : '';
                ?>
                <div class="ruut-address-card" id="address-card-<?php echo esc_attr( $address['id'] ); ?>">
                    <div class="ruut-address-card-header">
                        <h4><?php echo esc_html( $addr_title ); ?></h4>
                        <div class="ruut-address-actions">
                            <a onclick="ruutEditAddress('<?php echo esc_attr( wp_json_encode( $address ) ); ?>')">Edit</a>
                            <a onclick="ruutDeleteAddress('<?php echo esc_attr( $address['id'] ); ?>')">Delete</a>
                        </div>
                    </div>
                    <div class="ruut-address-details">
                        <p><strong><?php echo esc_html( $first . ' ' . $last ); ?></strong> (<?php echo esc_html( $phone ); ?>)</p>
                        <p>
                            <?php echo esc_html( $addr_1 ); ?><br>
                            <?php echo esc_html( $addr_2 ); ?>
                        </p>
                        <?php if ( ! empty( $landmark ) ) echo '<p>Landmark: ' . esc_html( $landmark ) . '</p>'; ?>
                        <p><?php echo esc_html( $city . ', ' . $state . ' ' . $postcode ); ?></p>
                    </div>
                </div>
                <?php
            }
        }
        echo '</div>';
        echo '<button type="button" class="ruut-btn" onclick="ruutOpenAddressModal()">+ Add New Address</button>';
        echo '</div>';
    }
}

if (!function_exists('ruut_address_book_modal_html')) {
    add_action( 'wp_footer', 'ruut_address_book_modal_html' );
    function ruut_address_book_modal_html() {
        if ( ! is_account_page() || ! is_user_logged_in() ) return;
        ?>
        <div id="ruut-address-modal" class="ruut-modal">
            <div class="ruut-modal-content">
                <span class="ruut-modal-close" onclick="ruutCloseAddressModal()">&times;</span>
                <h3 id="ruut-address-modal-title">Add New Address</h3>
                <form id="ruut-address-form" class="ruut-address-modal-form">
                    <input type="hidden" id="ruut_addr_id" name="id" value="" />
                    <div class="form-row">
                        <label>Save As (e.g. Home, Office) <span style="color: var(--ruut-accent);">*</span></label>
                        <input type="text" id="ruut_addr_title" name="title" required />
                    </div>
                    <div class="form-columns">
                        <div>
                            <label>Pin Code <span style="color: var(--ruut-accent);">*</span></label>
                            <input type="text" id="ruut_addr_postcode" name="postcode" required />
                        </div>
                        <div>
                            <label>Town / City <span style="color: var(--ruut-accent);">*</span></label>
                            <input type="text" id="ruut_addr_city" name="city" required />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>State <span style="color: var(--ruut-accent);">*</span></label>
                        <select id="ruut_addr_state" name="state" class="ruut-state-select" required>
                            <option value="">Select...</option>
                            <?php
                                $states = WC()->countries->get_states( 'IN' );
                                if ( ! empty( $states ) ) {
                                    foreach( $states as $code => $name ) {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                    }
                                }
                            ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Address <span style="color: var(--ruut-accent);">*</span></label>
                        <input type="text" id="ruut_addr_1" name="address_1" required />
                        <input type="text" id="ruut_addr_2" name="address_2" style="margin-top: 15px;" required />
                    </div>
                    <div class="form-row">
                        <label>Landmark</label>
                        <input type="text" id="ruut_addr_landmark" name="landmark" />
                    </div>
                    <button type="submit" class="ruut-btn" id="ruut_save_addr_btn" style="width: 100%; margin-top: 15px;">Save Address</button>
                    <div id="ruut-address-feedback"></div>
                </form>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('ruut_ajax_save_address')) {
    add_action( 'wp_ajax_ruut_save_address', 'ruut_ajax_save_address' );
    function ruut_ajax_save_address() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error();

        $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
        if ( ! is_array( $address_book ) ) $address_book = array();

        $address_id = isset( $_POST['id'] ) && ! empty( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : uniqid();
        $first_name = get_user_meta( $user_id, 'billing_first_name', true );
        $last_name  = get_user_meta( $user_id, 'billing_last_name', true );
        $phone      = get_user_meta( $user_id, 'billing_phone', true );

        if(empty($first_name)) {
            $user_info = get_userdata($user_id);
            $first_name = $user_info->first_name;
            $last_name = $user_info->last_name;
        }
        
        $new_address = array(
            'id'                => $address_id,
            'title'             => sanitize_text_field( $_POST['title'] ),
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'phone'             => $phone,
            'postcode'          => sanitize_text_field( $_POST['postcode'] ),
            'city'              => sanitize_text_field( $_POST['city'] ),
            'state'             => sanitize_text_field( $_POST['state'] ),
            'address_1'         => sanitize_text_field( $_POST['address_1'] ),
            'address_2'         => sanitize_text_field( $_POST['address_2'] ),
            'landmark'          => sanitize_text_field( $_POST['landmark'] ),
            
            // Legacy/headless compatibility fields
            'billing_address_1' => sanitize_text_field( $_POST['address_1'] ),
            'billing_address_2' => sanitize_text_field( $_POST['address_2'] ),
            'billing_city'      => sanitize_text_field( $_POST['city'] ),
            'billing_postcode'  => sanitize_text_field( $_POST['postcode'] ),
            'billing_state'     => sanitize_text_field( $_POST['state'] ),
            'billing_landmark'  => sanitize_text_field( $_POST['landmark'] ),
            'billing_save_as'   => sanitize_text_field( $_POST['title'] ),
            'is_default'        => empty($address_book)
        );

        $updated = false;
        foreach ( $address_book as $key => $address ) {
            if ( $address['id'] === $address_id ) {
                $address_book[$key] = $new_address;
                $updated = true;
                break;
            }
        }
        if ( ! $updated ) {
            $address_book[] = $new_address;
        }

        update_user_meta( $user_id, 'ruut_address_book', $address_book );
        wp_send_json_success();
    }
}

if (!function_exists('ruut_ajax_delete_address')) {
    add_action( 'wp_ajax_ruut_delete_address', 'ruut_ajax_delete_address' );
    function ruut_ajax_delete_address() {
        $user_id = get_current_user_id();
        if ( ! $user_id || empty( $_POST['id'] ) ) wp_send_json_error();

        $address_id = sanitize_text_field( $_POST['id'] );
        $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
        
        if ( is_array( $address_book ) ) {
            foreach ( $address_book as $key => $address ) {
                if ( $address['id'] === $address_id ) {
                    unset( $address_book[$key] );
                }
            }
            $address_book = array_values( $address_book );
            update_user_meta( $user_id, 'ruut_address_book', $address_book );
        }
        wp_send_json_success();
    }
}

// ===================================================================================
// PART 7: CORS & ALLOW-CREDENTIALS FOR HEADLESS LANDING PAGE API CALLS
// ===================================================================================

if (!function_exists('ruut_api_cors_init')) {
    add_action('rest_api_init', 'ruut_api_cors_init', 15);
    function ruut_api_cors_init() {
        $origin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        header("Access-Control-Allow-Origin: " . $origin);
        if ($origin !== '*') {
            header("Access-Control-Allow-Credentials: true");
        }
        header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With");
    }
}

if (!function_exists('ruut_rest_api_cors_pre_serve')) {
    add_filter('rest_pre_serve_request', 'ruut_rest_api_cors_pre_serve', 10, 4);
    function ruut_rest_api_cors_pre_serve($served, $result, $request, $server) {
        $origin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        header("Access-Control-Allow-Origin: " . $origin);
        if ($origin !== '*') {
            header("Access-Control-Allow-Credentials: true");
        }
        header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }
        return $served;
    }
}

// ===================================================================================
// PART 8: CUSTOM HEADLESS REST ROUTES FOR PORTAL (/ruut/v1)
// ===================================================================================

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

if (!function_exists('ruut_get_product_faqs_data')) {
    function ruut_get_product_faqs_data($product_id) {
        $faqs_meta = get_post_meta($product_id, '_product_faqs', true) 
                  ?: get_post_meta($product_id, 'product_faqs', true)
                  ?: get_post_meta($product_id, '_faqs', true)
                  ?: get_post_meta($product_id, 'faqs', true);

        $faqs = array();
        if ( is_array($faqs_meta) ) {
            $faqs = $faqs_meta;
        } elseif ( is_string($faqs_meta) && !empty($faqs_meta) ) {
            $decoded = json_decode($faqs_meta, true);
            if ( is_array($decoded) ) {
                $faqs = $decoded;
            } else {
                $lines = explode("\n", $faqs_meta);
                $current_q = '';
                foreach ( $lines as $line ) {
                    $line = trim($line);
                    if ( empty($line) ) continue;
                    if ( stripos($line, 'Q:') === 0 ) {
                        $current_q = trim(substr($line, 2));
                    } elseif ( stripos($line, 'A:') === 0 && !empty($current_q) ) {
                        $faqs[] = array(
                            'question' => $current_q,
                            'answer' => trim(substr($line, 2))
                        );
                        $current_q = '';
                    } else {
                        $parts = explode('|', $line);
                        if ( count($parts) >= 2 ) {
                            $faqs[] = array(
                                'question' => trim($parts[0]),
                                'answer' => trim($parts[1])
                            );
                        }
                    }
                }
            }
        }

        // Fetch Individual FAQ Custom Fields (faq_1_question to faq_5_question)
        for ($i = 1; $i <= 5; $i++) {
            $q = get_post_meta($product_id, "faq_{$i}_question", true) ?: get_post_meta($product_id, "_faq_{$i}_question", true);
            $a = get_post_meta($product_id, "faq_{$i}_answer", true) ?: get_post_meta($product_id, "_faq_{$i}_answer", true);
            if (!empty($q) && !empty($a)) {
                $faqs[] = array(
                    'question' => sanitize_text_field($q),
                    'answer' => wp_kses_post($a)
                );
            }
        }

        return $faqs;
    }
}

if (!function_exists("ruut_format_product_data")) {
    function ruut_format_product_data( $product ) {
        $image_id  = $product->get_image_id();
        $image_url = wp_get_attachment_image_url( $image_id, "full" );
        
        if ( !$image_url ) {
            $image_url = function_exists("wc_placeholder_img_src") ? wc_placeholder_img_src("full") : "";
        }

        $regular_price = $product->get_regular_price();
        $sale_price    = $product->get_sale_price();
        $price         = $product->get_price();

        // Fetch gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        $gallery = array( $image_url ); // main image is first
        if ( !empty($gallery_ids) ) {
            foreach ( $gallery_ids as $gal_id ) {
                $gal_url = wp_get_attachment_image_url( $gal_id, "full" );
                if ( $gal_url ) {
                    $gallery[] = $gal_url;
                }
            }
        }

        // Fetch meta fields or use brand-aligned fallbacks
        $feels_like = get_post_meta($product->get_id(), 'feels_like', true) ?: get_post_meta($product->get_id(), '_feels_like', true) ?: 'Earthy, fresh, and soothing with hints of mountain mist and damp pine needles.';
        $the_story = get_post_meta($product->get_id(), 'the_story', true) ?: get_post_meta($product->get_id(), '_the_story', true) ?: 'Inspired by the quiet mornings in Landour, Mussoorie, where the rising mist embraces the cedar and oak trees, capturing a moment of pristine serenity.';
        $the_name = get_post_meta($product->get_id(), 'the_name', true) ?: get_post_meta($product->get_id(), '_the_name', true) ?: 'Named after the historic Landour cantonment town nestled in Mussoorie. Each spray brings you closer to the clean, bracing air of the Himalayas.';
        $directions = get_post_meta($product->get_id(), 'directions', true) ?: get_post_meta($product->get_id(), '_directions_for_use', true) ?: 'Spray 2-3 times in the air towards the center of the room. Keep the nozzle at least 30 cm away from walls and fabrics. Shake well before use.';
        $warnings = get_post_meta($product->get_id(), 'cautions', true) ?: get_post_meta($product->get_id(), '_cautions_warnings', true) ?: 'Keep out of reach of children and pets. Flammable. Keep away from heat, open flames, and hot surfaces. In case of contact with eyes, rinse immediately with water.';

        // Calculate discount percentage
        $discount_percentage = 0;
        if ( $product->is_on_sale() && !empty($regular_price) && !empty($sale_price) && floatval($regular_price) > 0 ) {
            $discount_percentage = round( ( ( floatval($regular_price) - floatval($sale_price) ) / floatval($regular_price) ) * 100 );
        }

        // Fetch product categories
        $cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'all' ) );
        $categories_list = array();
        if ( !is_wp_error($cats) && !empty($cats) ) {
            foreach ( $cats as $c ) {
                $categories_list[] = array(
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'parent' => (int) $c->parent
                );
            }
        }

        $volume = $product->get_attribute('pa_volume') ? $product->get_attribute('pa_volume') : $product->get_attribute('volume');
        if ( !$volume ) {
            $volume = '';
        }

        // Fetch dynamic details/properties
        $properties = array();
        $meta_keys = array('properties', '_properties', 'details', '_details', 'product_details', '_product_details', 'features', '_features');
        foreach ($meta_keys as $key) {
            $val = get_post_meta($product->get_id(), $key, true);
            if (!empty($val)) {
                if (is_array($val)) {
                    $properties = array_merge($properties, $val);
                } elseif (is_string($val)) {
                    $decoded = json_decode($val, true);
                    if (is_array($decoded)) {
                        $properties = array_merge($properties, $decoded);
                    } else {
                        $lines = explode("\n", $val);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                $properties[] = $line;
                            }
                        }
                    }
                }
            }
        }
        
        // If meta properties empty, check custom non-volume attributes
        if (empty($properties)) {
            $attributes = $product->get_attributes();
            foreach ($attributes as $attr_name => $attr) {
                if ($attr_name !== 'pa_volume' && $attr_name !== 'volume') {
                    if ($attr->is_taxonomy()) {
                        $terms = wc_get_product_terms($product->get_id(), $attr->get_name(), array('fields' => 'names'));
                        if (!is_wp_error($terms)) {
                            $properties = array_merge($properties, $terms);
                        }
                    } else {
                        $options = $attr->get_options();
                        if (is_array($options)) {
                            $properties = array_merge($properties, $options);
                        }
                    }
                }
            }
        }
        $properties = array_map('sanitize_text_field', $properties);
        $properties = array_filter($properties);
        $properties = array_values(array_unique($properties));

        // Fetch all available volumes
        $volumes = array();
        $pa_volume_attr = $product->get_attribute('pa_volume');
        $volume_attr = $product->get_attribute('volume');
        if (!empty($pa_volume_attr)) {
            $volumes = array_map('trim', explode(',', $pa_volume_attr));
        } elseif (!empty($volume_attr)) {
            $volumes = array_map('trim', explode(',', $volume_attr));
        } else {
            $attributes = $product->get_attributes();
            foreach ($attributes as $attr_name => $attr) {
                if (stripos($attr_name, 'volume') !== false || stripos($attr_name, 'size') !== false) {
                    if ($attr->is_taxonomy()) {
                        $terms = wc_get_product_terms($product->get_id(), $attr->get_name(), array('fields' => 'names'));
                        if (!is_wp_error($terms)) {
                            $volumes = array_merge($volumes, $terms);
                        }
                    } else {
                        $options = $attr->get_options();
                        if (is_array($options)) {
                            $volumes = array_merge($volumes, $options);
                        }
                    }
                }
            }
        }
        $volumes = array_map('sanitize_text_field', $volumes);
        $volumes = array_filter($volumes);
        $volumes = array_values(array_unique($volumes));

        // Fetch variations if variable product
        $variations_data = array();
        if ( $product->is_type('variable') ) {
            $variation_ids = $product->get_children();
            foreach ( $variation_ids as $var_id ) {
                $variation = wc_get_product( $var_id );
                if ( $variation && $variation->get_status() === 'publish' ) {
                    $var_vol = $variation->get_attribute('pa_volume') ?: $variation->get_attribute('volume');
                    $var_image_id = $variation->get_image_id();
                    $var_image = $var_image_id ? wp_get_attachment_image_url( $var_image_id, "full" ) : $image_url;
                    $variations_data[] = array(
                        'id' => $var_id,
                        'price' => (float) $variation->get_price(),
                        'regular_price' => (float) ($variation->get_regular_price() ?: $variation->get_price()),
                        'sale_price' => $variation->get_sale_price() ? (float) $variation->get_sale_price() : null,
                        'volume' => $var_vol ? trim($var_vol) : '',
                        'sku' => $variation->get_sku(),
                        'image' => $var_image
                    );
                }
            }
        }

        return array(
            "id"            => $product->get_id(),
            "title"         => $product->get_name(),
            "description"   => $product->get_short_description() ?: $product->get_description(),
            "image"         => $image_url,
            "gallery"       => $gallery,
            "price"         => $price,
            "regular_price" => $regular_price,
            "sale_price"    => $sale_price,
            "permalink"     => $product->get_permalink(),
            "formatted_price" => strip_tags(wc_price($price)),
            "feels_like"    => $feels_like,
            "the_story"     => $the_story,
            "the_name"      => $the_name,
            "directions_for_use" => $directions,
            "directions"         => $directions,
            "cautions_warnings"  => $warnings,
            "cautions"           => $warnings,
            "discount_percentage" => $discount_percentage,
            "categories"         => $categories_list,
            "is_featured"        => $product->is_featured(),
            "total_sales"        => (int) get_post_meta( $product->get_id(), 'total_sales', true ),
            "volume"            => $volume,
            "volumes"           => $volumes,
            "properties"        => $properties,
            "variations"        => $variations_data,
            "faqs"              => ruut_get_product_faqs_data($product->get_id())
        );
    }
}

if (!function_exists("ruut_get_products_api")) {
    function ruut_get_products_api( $request ) {
        $id = $request->get_param("id");
        if ( !empty($id) ) {
            $product = wc_get_product( intval($id) );
            if ( $product && $product->get_status() === 'publish' ) {
                return rest_ensure_response( ruut_format_product_data( $product ) );
            } else {
                return new WP_Error( 'ruut_product_not_found', 'Product not found', array( 'status' => 404 ) );
            }
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

        $products = wc_get_products( $args );
        $data = array();

        foreach ( $products as $product ) {
            $data[] = ruut_format_product_data( $product );
        }

        return rest_ensure_response( $data );
    }
}

// ===================================================================================
// PART 6: CUSTOM FRONTEND ROUTING & REDIRECTS FOR CART
// ===================================================================================

/**
 * Filter WooCommerce Cart URL to target the custom static cart page.
 */
add_filter( 'woocommerce_get_cart_url', 'ruut_custom_cart_url' );
function ruut_custom_cart_url( $url ) {
    return site_url( '/cart' );
}

/**
 * Redirect standard WooCommerce cart page loads to the custom static cart page.
 */
add_action( 'template_redirect', 'ruut_redirect_woo_cart_to_custom_cart' );
function ruut_redirect_woo_cart_to_custom_cart() {
    // Redirect when accessing the default WooCommerce cart page.
    if ( is_cart() && !is_admin() ) {
        wp_redirect( site_url( '/cart' ), 301 );
        exit;
    }
}

// ===================================================================================
// PART 7: MY ACCOUNT ADDRESS BOOK AJAX HANDLERS
// ===================================================================================

// Handle AJAX Save Address Book Entry
add_action( 'wp_ajax_ruut_save_address', 'ruut_ajax_save_address' );
function ruut_ajax_save_address() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error();

    $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
    if ( ! is_array( $address_book ) ) $address_book = array();

    $address_id = isset( $_POST['id'] ) && ! empty( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : uniqid();
    
    // Automatically grab the logged in user's first/last name and phone since the modal no longer asks for it!
    $first_name = get_user_meta( $user_id, 'billing_first_name', true );
    $last_name  = get_user_meta( $user_id, 'billing_last_name', true );
    $phone      = get_user_meta( $user_id, 'billing_phone', true );

    if(empty($first_name)) {
        $user_info = get_userdata($user_id);
        $first_name = $user_info->first_name;
        $last_name = $user_info->last_name;
    }
    
    $new_address = array(
        'id'         => $address_id,
        'title'      => sanitize_text_field( $_POST['title'] ),
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'phone'      => $phone,
        'postcode'   => sanitize_text_field( $_POST['postcode'] ),
        'city'       => sanitize_text_field( $_POST['city'] ),
        'state'      => sanitize_text_field( $_POST['state'] ),
        'address_1'  => sanitize_text_field( $_POST['address_1'] ),
        'address_2'  => sanitize_text_field( $_POST['address_2'] ),
        'landmark'   => sanitize_text_field( $_POST['landmark'] )
    );

    // Update existing or add new
    $updated = false;
    foreach ( $address_book as $key => $address ) {
        if ( isset($address['id']) && $address['id'] === $address_id ) {
            $address_book[$key] = $new_address;
            $updated = true;
            break;
        }
    }
    if ( ! $updated ) {
        $address_book[] = $new_address;
    }

    update_user_meta( $user_id, 'ruut_address_book', $address_book );
    wp_send_json_success();
}

// Handle AJAX Delete Address Book Entry
add_action( 'wp_ajax_ruut_delete_address', 'ruut_ajax_delete_address' );
function ruut_ajax_delete_address() {
    $user_id = get_current_user_id();
    if ( ! $user_id || empty( $_POST['id'] ) ) wp_send_json_error();

    $address_id = sanitize_text_field( $_POST['id'] );
    $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
    
    if ( is_array( $address_book ) ) {
        foreach ( $address_book as $key => $address ) {
            if ( isset($address['id']) && $address['id'] === $address_id ) {
                unset( $address_book[$key] );
            }
        }
        // Re-index array
        $address_book = array_values( $address_book );
        update_user_meta( $user_id, 'ruut_address_book', $address_book );
    }
    
    wp_send_json_success();
}

// ===================================================================================
// PART 8: WOOCOMMERCE CHECKOUT OPTIMIZATIONS & CUSTOMIZATIONS
// ===================================================================================

add_filter( 'wc_ship_to_billing_address_only', '__return_true' );

if (!function_exists('ruut_remove_checkout_banners')) {
    add_action('init', 'ruut_remove_checkout_banners');
    function ruut_remove_checkout_banners() {
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
        remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
    }
}

if (!function_exists('ruut_inject_custom_header')) {
    add_action('woocommerce_before_checkout_form', 'ruut_inject_custom_header', 5);
    function ruut_inject_custom_header() {
        echo '<div class="ruut-header-wrapper"><div class="ruut-brand-logo">ṚUUT</div><div class="ruut-page-title">Secure Checkout</div></div>';
    }
}

if (!function_exists('ruut_inject_address_book_data')) {
    add_action('woocommerce_before_checkout_form', 'ruut_inject_address_book_data', 1);
    function ruut_inject_address_book_data() {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
            
            if ( ! empty( $address_book ) && is_array( $address_book ) ) {
                echo '<script>window.ruutAddressBook = ' . json_encode( $address_book ) . ';</script>';
            }
        }
    }
}

if (!function_exists('ruut_clean_error_messages')) {
    add_filter( 'woocommerce_checkout_required_field_notice', 'ruut_clean_error_messages', 10, 2 );
    function ruut_clean_error_messages( $error_message, $field_label ) {
        $clean_label = str_ireplace( array( 'Billing ', 'Shipping ' ), '', $field_label );
        return sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $clean_label ) . '</strong>' );
    }
}

add_filter( 'woocommerce_checkout_cart_item_quantity', '__return_empty_string' );

if (!function_exists('ruut_custom_checkout_item_display')) {
    add_filter( 'woocommerce_cart_item_name', 'ruut_custom_checkout_item_display', 10, 3 );
    function ruut_custom_checkout_item_display( $name, $cart_item, $cart_item_key ) {
        if ( is_checkout() && ! is_wc_endpoint_url() ) {
            $product = $cart_item['data'];
            $thumbnail = $product->get_image(array(90, 90)); 
            
            $vol = $product->get_attribute('pa_volume') ? $product->get_attribute('pa_volume') : $product->get_attribute('volume');
            if (!$vol) {
                $vol = $product->get_attribute('pa_size') ? $product->get_attribute('pa_size') : $product->get_attribute('size');
            }
            
            $meta_display = '';
            if ($vol) {
                $meta_display = '<div class="ruut-item-meta">' . $vol . '</div>';
            }

            $qty = $cart_item['quantity'];
            $qty_display = '<div class="ruut-item-qty">Quantity &nbsp;&nbsp;' . $qty . '</div>';

            return '<div class="ruut-item-wrapper"><div class="ruut-item-img">' . $thumbnail . '</div><div class="ruut-item-details"><div class="ruut-item-name">' . $name . '</div>' . $meta_display . $qty_display . '</div></div>';
        }
        return $name;
    }
}

if (!function_exists('ruut_strict_address_fields')) {
    add_filter( 'woocommerce_default_address_fields', 'ruut_strict_address_fields', 9999 );
    function ruut_strict_address_fields( $fields ) {
        $fields['address_1']['label'] = 'Address'; 
        $fields['address_1']['placeholder'] = '';
        $fields['address_2']['label'] = 'Address Line 2';
        $fields['address_2']['placeholder'] = '';
        $fields['address_2']['required'] = true;
        return $fields;
    }
}

if (!function_exists('ruut_custom_billing_fields')) {
    add_filter('woocommerce_checkout_fields', 'ruut_custom_billing_fields', 9999);
    function ruut_custom_billing_fields($fields) {
        $fields['billing']['billing_email']['priority'] = 10; 
        $fields['billing']['billing_phone']['priority'] = 20; 
        $fields['billing']['billing_first_name']['priority'] = 30;
        $fields['billing']['billing_last_name']['priority'] = 40;
        $fields['billing']['billing_postcode']['priority'] = 50;
        $fields['billing']['billing_city']['priority'] = 60;
        $fields['billing']['billing_state']['priority'] = 70;
        $fields['billing']['billing_address_1']['priority'] = 80;
        $fields['billing']['billing_address_2']['priority'] = 90;

        $fields['billing']['billing_landmark'] = array(
            'type'        => 'text',
            'label'       => 'Landmark (Optional)',
            'placeholder' => '',
            'required'    => false,
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 100,
        );
        
        $fields['billing']['billing_save_as'] = array(
            'type'        => 'text',
            'label'       => 'Save As',
            'placeholder' => 'e.g. Home, Office',
            'required'    => true, 
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 110,
        );

        return $fields;
    }
}

if (!function_exists('ruut_save_checkout_custom_fields')) {
    add_action('woocommerce_checkout_create_order', 'ruut_save_checkout_custom_fields');
    function ruut_save_checkout_custom_fields($order) {
        if (isset($_POST['billing_landmark'])) {
            $order->update_meta_data('_billing_landmark', sanitize_text_field($_POST['billing_landmark']));
        }
        if (isset($_POST['billing_save_as'])) {
            $order->update_meta_data('_billing_save_as', sanitize_text_field($_POST['billing_save_as']));
        }
    }
}

// ===================================================================================
// PART 9: COUPOUNS, RETENTION LOOP, AND LOGS
// ===================================================================================

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

if (!function_exists('ruut_intercept_checkout_data')) {
    add_filter('woocommerce_checkout_posted_data', 'ruut_intercept_checkout_data', 9999, 1);
    function ruut_intercept_checkout_data( $data ) {
        $log_file = dirname(__FILE__) . '/checkout_debug.log';
        $log_content = "=== CHECKOUT INTERCEPT ===\n";
        $log_content .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $log_content .= "POST: " . print_r($_POST, true) . "\n";
        $log_content .= "DATA BEFORE: " . print_r($data, true) . "\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);

        $data['ship_to_different_address'] = false;

        if ( isset( $_POST['ruut_selected_address'] ) && $_POST['ruut_selected_address'] !== 'new' ) {
            $selected_key = sanitize_text_field( $_POST['ruut_selected_address'] );
            
            if ( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
                
                if ( is_array( $address_book ) && isset( $address_book[ $selected_key ] ) ) {
                    $addr = $address_book[ $selected_key ];
                    
                    $data['billing_address_1'] = !empty($addr['billing_address_1']) ? $addr['billing_address_1'] : (!empty($addr['address_1']) ? $addr['address_1'] : 'Saved Address'); 
                    $data['billing_address_2'] = !empty($addr['billing_address_2']) ? $addr['billing_address_2'] : (!empty($addr['address_2']) ? $addr['address_2'] : 'N/A');
                    $data['billing_city']      = !empty($addr['billing_city']) ? $addr['billing_city'] : (!empty($addr['city']) ? $addr['city'] : 'Saved City');
                    $data['billing_postcode']  = !empty($addr['billing_postcode']) ? $addr['billing_postcode'] : (!empty($addr['postcode']) ? $addr['postcode'] : '000000');
                    $data['billing_state']     = !empty($addr['billing_state']) ? $addr['billing_state'] : (!empty($addr['state']) ? $addr['state'] : '');
                    
                    $data['billing_landmark'] = !empty($addr['billing_landmark']) ? $addr['billing_landmark'] : (!empty($addr['landmark']) ? $addr['landmark'] : '');
                    $data['billing_save_as']  = !empty($addr['billing_save_as']) ? $addr['billing_save_as'] : (!empty($addr['save_as']) ? $addr['save_as'] : 'Saved Address');
                    
                    $_POST['billing_address_1'] = $data['billing_address_1'];
                    $_POST['billing_address_2'] = $data['billing_address_2'];
                    $_POST['billing_city']      = $data['billing_city'];
                    $_POST['billing_postcode']  = $data['billing_postcode'];
                    $_POST['billing_state']     = $data['billing_state'];
                    $_POST['billing_landmark']  = $data['billing_landmark'];
                    $_POST['billing_save_as']   = $data['billing_save_as'];
                }
            }
        }

        $data['billing_country'] = !empty($data['billing_country']) ? $data['billing_country'] : 'IN';
        $_POST['billing_country'] = !empty($_POST['billing_country']) ? $_POST['billing_country'] : 'IN';

        $billing_fields = array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'state', 'phone', 'email');
        foreach ($billing_fields as $field) {
            $key = 'billing_' . $field;
            if (isset($data[$key]) && (empty($_POST[$key]) || !isset($_POST[$key]))) {
                $_POST[$key] = $data[$key];
            }
        }

        $data['shipping_first_name'] = !empty($data['billing_first_name']) ? $data['billing_first_name'] : '';
        $data['shipping_last_name']  = !empty($data['billing_last_name']) ? $data['billing_last_name'] : '';
        $data['shipping_company']    = !empty($data['billing_company']) ? $data['billing_company'] : '';
        $data['shipping_address_1']  = !empty($data['billing_address_1']) ? $data['billing_address_1'] : '';
        $data['shipping_address_2']  = !empty($data['billing_address_2']) ? $data['billing_address_2'] : '';
        $data['shipping_city']       = !empty($data['billing_city']) ? $data['billing_city'] : '';
        $data['shipping_postcode']   = !empty($data['billing_postcode']) ? $data['billing_postcode'] : '';
        $data['shipping_state']      = !empty($data['billing_state']) ? $data['billing_state'] : '';
        $data['shipping_country']    = !empty($data['billing_country']) ? $data['billing_country'] : 'IN';

        $_POST['shipping_first_name'] = $data['shipping_first_name'];
        $_POST['shipping_last_name']  = $data['shipping_last_name'];
        $_POST['shipping_company']    = $data['shipping_company'];
        $_POST['shipping_address_1']  = $data['shipping_address_1'];
        $_POST['shipping_address_2']  = $data['shipping_address_2'];
        $_POST['shipping_city']       = $data['shipping_city'];
        $_POST['shipping_postcode']   = $data['shipping_postcode'];
        $_POST['shipping_state']      = $data['shipping_state'];
        $_POST['shipping_country']    = $data['shipping_country'];
        
        $log_content = "DATA AFTER: " . print_r($data, true) . "\n-----------------------\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);
        
        return $data;
    }
}

if (!function_exists('ruut_save_new_address_to_vault')) {
    add_action('woocommerce_checkout_update_user_meta', 'ruut_save_new_address_to_vault', 10, 2);
    function ruut_save_new_address_to_vault( $customer_id, $posted ) {
        if ( isset( $_POST['ruut_selected_address'] ) && $_POST['ruut_selected_address'] === 'new' ) {
            $address_book = get_user_meta( $customer_id, 'ruut_address_book', true );
            if ( ! is_array( $address_book ) ) {
                $address_book = array();
            }
            
            $new_address = array(
                'id'                => uniqid(),
                'title'             => isset($_POST['billing_save_as']) ? sanitize_text_field($_POST['billing_save_as']) : 'Saved Address',
                'first_name'        => isset($posted['billing_first_name']) ? $posted['billing_first_name'] : '',
                'last_name'         => isset($posted['billing_last_name']) ? $posted['billing_last_name'] : '',
                'phone'             => isset($posted['billing_phone']) ? $posted['billing_phone'] : '',
                'postcode'          => isset($posted['billing_postcode']) ? $posted['billing_postcode'] : '',
                'city'              => isset($posted['billing_city']) ? $posted['billing_city'] : '',
                'state'             => isset($posted['billing_state']) ? $posted['billing_state'] : '',
                'address_1'         => isset($posted['billing_address_1']) ? $posted['billing_address_1'] : '',
                'address_2'         => isset($posted['billing_address_2']) ? $posted['billing_address_2'] : '',
                'landmark'          => isset($_POST['billing_landmark']) ? sanitize_text_field($_POST['billing_landmark']) : '',
                
                // Keep headless legacy keys for safety
                'billing_address_1' => isset($posted['billing_address_1']) ? $posted['billing_address_1'] : '',
                'billing_address_2' => isset($posted['billing_address_2']) ? $posted['billing_address_2'] : '',
                'billing_city'      => isset($posted['billing_city']) ? $posted['billing_city'] : '',
                'billing_postcode'  => isset($posted['billing_postcode']) ? $posted['billing_postcode'] : '',
                'billing_state'     => isset($posted['billing_state']) ? $posted['billing_state'] : '',
                'billing_landmark'  => isset($_POST['billing_landmark']) ? sanitize_text_field($_POST['billing_landmark']) : '',
                'billing_save_as'   => isset($_POST['billing_save_as']) ? sanitize_text_field($_POST['billing_save_as']) : 'Saved Address',
                'is_default'        => empty($address_book) ? true : false 
            );
            
            if ( !empty($new_address['address_1']) && !empty($new_address['city']) ) {
                $address_book[] = $new_address;
                update_user_meta( $customer_id, 'ruut_address_book', $address_book );
            }
        }
    }
}

if (!function_exists('ruut_log_validation_errors')) {
    add_action('woocommerce_after_checkout_validation', 'ruut_log_validation_errors', 9999, 2);
    function ruut_log_validation_errors( $data, $errors ) {
        $log_file = dirname(__FILE__) . '/checkout_debug.log';
        $log_content = "=== VALIDATION ERRORS ===\n";
        $log_content .= "Errors: " . print_r($errors->get_error_messages(), true) . "\n-----------------------\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);
    }
}

add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );

// ===================================================================================
// PART 4: DYNAMIC CUSTOMER REVIEWS (TESTIMONIALS)
// ===================================================================================

// Register Reviews Custom Post Type
add_action('init', 'ruut_register_reviews_post_type');
function ruut_register_reviews_post_type() {
    register_post_type('ruut_review', array(
        'labels' => array(
            'name' => 'Reviews',
            'singular_name' => 'Review',
            'add_new' => 'Add New Review',
            'add_new_item' => 'Add New Customer Review',
            'edit_item' => 'Edit Customer Review',
            'new_item' => 'New Customer Review',
            'view_item' => 'View Customer Review',
            'search_items' => 'Search Customer Reviews',
            'not_found' => 'No reviews found',
            'not_found_in_trash' => 'No reviews found in Trash'
        ),
        'public' => true,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'thumbnail'), // Title as Name, Editor as Content, Thumbnail as Reviewer Avatar
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-testimonial',
    ));
}

// Add Metabox for Rating and Featured Status
add_action('add_meta_boxes', 'ruut_reviews_add_meta_boxes');
function ruut_reviews_add_meta_boxes() {
    add_meta_box(
        'ruut_review_details',
        'Review Details',
        'ruut_review_details_metabox_html',
        'ruut_review',
        'normal',
        'default'
    );
}

function ruut_review_details_metabox_html($post) {
    $rating = get_post_meta($post->ID, '_rating', true) ?: '5';
    $is_featured = get_post_meta($post->ID, '_is_featured', true) ?: 'yes';
    wp_nonce_field('ruut_review_meta_nonce', 'ruut_review_meta_nonce_field');
    ?>
    <p>
        <label for="ruut_review_rating">Rating (1 to 5 Stars):</label>
        <select name="ruut_review_rating" id="ruut_review_rating" class="widefat">
            <option value="5" <?php selected($rating, '5'); ?>>5 Stars</option>
            <option value="4" <?php selected($rating, '4'); ?>>4 Stars</option>
            <option value="3" <?php selected($rating, '3'); ?>>3 Stars</option>
            <option value="2" <?php selected($rating, '2'); ?>>2 Stars</option>
            <option value="1" <?php selected($rating, '1'); ?>>1 Star</option>
        </select>
    </p>
    <p>
        <label>
            <input type="checkbox" name="ruut_review_is_featured" value="yes" <?php checked($is_featured, 'yes'); ?> />
            Featured/Starred Review (Will display in the Customer Reviews section on homepage)
        </label>
    </p>
    <?php
}

// Save Meta Box Data
add_action('save_post', 'ruut_save_review_meta');
function ruut_save_review_meta($post_id) {
    if (!isset($_POST['ruut_review_meta_nonce_field']) || !wp_verify_nonce($_POST['ruut_review_meta_nonce_field'], 'ruut_review_meta_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['post_type']) && 'ruut_review' === $_POST['post_type']) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
    
    if (isset($_POST['ruut_review_rating'])) {
        update_post_meta($post_id, '_rating', sanitize_text_field($_POST['ruut_review_rating']));
    }
    
    $is_featured = isset($_POST['ruut_review_is_featured']) ? 'yes' : 'no';
    update_post_meta($post_id, '_is_featured', $is_featured);
}

// Register Reviews REST Route
add_action('rest_api_init', 'ruut_register_reviews_rest_endpoints');
function ruut_register_reviews_rest_endpoints() {
    register_rest_route('ruut/v1', '/reviews', array(
        'methods' => 'GET',
        'callback' => 'ruut_get_reviews_api',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('ruut/v1', '/reviews', array(
        'methods' => 'POST',
        'callback' => 'ruut_create_review_api',
        'permission_callback' => '__return_true',
    ));
}

function ruut_create_review_api($request) {
    $params = $request->get_json_params();
    if (empty($params)) {
        $params = $_POST;
    }

    $reviewer_name = isset($params['reviewer_name']) ? sanitize_text_field($params['reviewer_name']) : '';
    $reviewer_email = isset($params['reviewer_email']) ? sanitize_email($params['reviewer_email']) : '';
    $review_content = isset($params['review_content']) ? sanitize_textarea_field($params['review_content']) : '';
    $rating = isset($params['rating']) ? intval($params['rating']) : 5;

    if (empty($reviewer_name) || empty($review_content)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Name and review content are required.'), 400);
    }

    if ($rating < 1 || $rating > 5) {
        $rating = 5;
    }

    // Insert pending post (for admin moderation before publishing)
    $post_id = wp_insert_post(array(
        'post_title'   => $reviewer_name,
        'post_content' => $review_content,
        'post_status'  => 'pending',
        'post_type'    => 'ruut_review',
    ));

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Failed to save review: ' . $post_id->get_error_message()), 500);
    }

    // Update metadata
    update_post_meta($post_id, '_rating', $rating);
    update_post_meta($post_id, '_is_featured', 'no'); // Default to not featured
    if (!empty($reviewer_email)) {
        update_post_meta($post_id, '_reviewer_email', $reviewer_email);
    }

    return new WP_REST_Response(array('success' => true, 'message' => 'Thank you! Your review has been submitted for moderation.'), 200);
}

function ruut_get_reviews_api($request) {
    $featured_only = $request->get_param('featured') !== 'false';
    
    $args = array(
        'post_type' => 'ruut_review',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    if ($featured_only) {
        $args['meta_query'] = array(
            array(
                'key' => '_is_featured',
                'value' => 'yes',
                'compare' => '='
            )
        );
    }
    
    $posts = get_posts($args);
    $data = array();
    
    foreach ($posts as $post) {
        $avatar_id = get_post_thumbnail_id($post->ID);
        $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
        if (!$avatar_url) {
            $email = get_post_meta($post->ID, '_reviewer_email', true);
            if ($email) {
                $avatar_url = get_avatar_url($email, array('size' => 96, 'default' => 'mp'));
            } else {
                $avatar_url = 'https://secure.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=96&d=mp';
            }
        }
        
        $rating = get_post_meta($post->ID, '_rating', true) ?: '5';
        
        $data[] = array(
            'id' => $post->ID,
            'reviewer_name' => $post->post_title,
            'review_content' => wp_strip_all_tags($post->post_content),
            'rating' => intval($rating),
            'avatar' => $avatar_url,
            'date' => get_the_date('', $post->ID),
        );
    }
    
    return rest_ensure_response($data);
}

// ===================================================================================
// PART 5: DYNAMIC COLLECTIONS & PRODUCT CATEGORIES
// ===================================================================================

// Register Collections REST Route
add_action('rest_api_init', 'ruut_register_collections_rest_endpoints');
function ruut_register_collections_rest_endpoints() {
    register_rest_route('ruut/v1', '/collections', array(
        'methods' => 'GET',
        'callback' => 'ruut_get_collections_api',
        'permission_callback' => '__return_true',
    ));
}

function ruut_get_collections_api($request) {
    $slug = $request->get_param('slug');
    
    if (!empty($slug)) {
        $category = get_term_by('slug', sanitize_text_field($slug), 'product_cat');
        if ($category) {
            return rest_ensure_response(ruut_format_category_data($category));
        } else {
            return new WP_Error('ruut_collection_not_found', 'Collection not found', array('status' => 404));
        }
    }
    
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ));
    
    $data = array();
    foreach ($categories as $cat) {
        if ($cat->slug === 'uncategorized') {
            continue;
        }
        $data[] = ruut_format_category_data($cat);
    }
    
    return rest_ensure_response($data);
}

function ruut_format_category_data($cat) {
    $thumbnail_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
    $image_url = wp_get_attachment_url($thumbnail_id);
    
    if (!$image_url) {
        $image_url = '/assets/images/tac_hero.jpg';
    }
    
    // Hero banner image (16:9 wide) — separate from the card thumbnail (1:1)
    $hero_image_id = get_term_meta($cat->term_id, 'ruut_hero_image_id', true);
    $hero_image_url = $hero_image_id ? wp_get_attachment_url($hero_image_id) : '';
    
    $parent_slug = '';
    if ($cat->parent !== 0) {
        $parent_term = get_term($cat->parent, 'product_cat');
        if ($parent_term && !is_wp_error($parent_term)) {
            $parent_slug = $parent_term->slug;
        }
    }
    
    return array(
        'id' => $cat->term_id,
        'title' => $cat->name,
        'description' => $cat->description,
        'slug' => $cat->slug,
        'image' => $image_url,
        'hero_image' => $hero_image_url ? $hero_image_url : $image_url,
        'count' => $cat->count,
        'parent' => (int) $cat->parent,
        'parent_slug' => $parent_slug,
    );
}

// --- Custom Hero Banner Image field for Product Categories ---

// Add field to the "Add New Category" form
add_action('product_cat_add_form_fields', 'ruut_category_add_hero_image_field');
function ruut_category_add_hero_image_field() {
    ?>
    <div class="form-field">
        <label for="ruut_hero_image_id"><?php esc_html_e('Hero Banner Image (16:9)', 'ruut'); ?></label>
        <div id="ruut_hero_image_preview" style="margin-bottom: 10px;"></div>
        <input type="hidden" name="ruut_hero_image_id" id="ruut_hero_image_id" value="">
        <button type="button" class="button" id="ruut_upload_hero_image"><?php esc_html_e('Upload / Select Image', 'ruut'); ?></button>
        <button type="button" class="button" id="ruut_remove_hero_image" style="display:none;"><?php esc_html_e('Remove Image', 'ruut'); ?></button>
        <p class="description"><?php esc_html_e('Upload a wide banner image (recommended 16:9 ratio, e.g. 1920×1080) for the collection hero section. The regular WooCommerce thumbnail (1:1) will be used for category cards.', 'ruut'); ?></p>
    </div>
    <?php
}

// Add field to the "Edit Category" form
add_action('product_cat_edit_form_fields', 'ruut_category_edit_hero_image_field');
function ruut_category_edit_hero_image_field($term) {
    $hero_image_id = get_term_meta($term->term_id, 'ruut_hero_image_id', true);
    $hero_image_url = $hero_image_id ? wp_get_attachment_url($hero_image_id) : '';
    ?>
    <tr class="form-field">
        <th scope="row"><label for="ruut_hero_image_id"><?php esc_html_e('Hero Banner Image (16:9)', 'ruut'); ?></label></th>
        <td>
            <div id="ruut_hero_image_preview" style="margin-bottom: 10px;">
                <?php if ($hero_image_url) : ?>
                    <img src="<?php echo esc_url($hero_image_url); ?>" style="max-width: 400px; height: auto; border-radius: 6px;">
                <?php endif; ?>
            </div>
            <input type="hidden" name="ruut_hero_image_id" id="ruut_hero_image_id" value="<?php echo esc_attr($hero_image_id); ?>">
            <button type="button" class="button" id="ruut_upload_hero_image"><?php esc_html_e('Upload / Select Image', 'ruut'); ?></button>
            <button type="button" class="button" id="ruut_remove_hero_image" style="<?php echo $hero_image_id ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove Image', 'ruut'); ?></button>
            <p class="description"><?php esc_html_e('Upload a wide banner image (recommended 16:9 ratio, e.g. 1920×1080) for the collection hero section. The regular WooCommerce thumbnail (1:1) will be used for category cards.', 'ruut'); ?></p>
        </td>
    </tr>
    <?php
}

// Save the hero image meta on category create/edit
add_action('created_product_cat', 'ruut_save_category_hero_image');
add_action('edited_product_cat', 'ruut_save_category_hero_image');
function ruut_save_category_hero_image($term_id) {
    if (isset($_POST['ruut_hero_image_id'])) {
        $hero_image_id = absint($_POST['ruut_hero_image_id']);
        if ($hero_image_id) {
            update_term_meta($term_id, 'ruut_hero_image_id', $hero_image_id);
        } else {
            delete_term_meta($term_id, 'ruut_hero_image_id');
        }
    }
}

// Enqueue WP Media uploader scripts on category admin pages
add_action('admin_enqueue_scripts', 'ruut_category_hero_image_admin_scripts');
function ruut_category_hero_image_admin_scripts($hook) {
    if ($hook !== 'edit-tags.php' && $hook !== 'term.php') return;
    if (!isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'product_cat') return;
    
    wp_enqueue_media();
    wp_add_inline_script('media-editor', "
        jQuery(document).ready(function($) {
            var frame;
            $('#ruut_upload_hero_image').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: 'Select Hero Banner Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#ruut_hero_image_id').val(attachment.id);
                    $('#ruut_hero_image_preview').html('<img src=\"' + attachment.url + '\" style=\"max-width:400px;height:auto;border-radius:6px;\">');
                    $('#ruut_remove_hero_image').show();
                });
                frame.open();
            });
            $('#ruut_remove_hero_image').on('click', function(e) {
                e.preventDefault();
                $('#ruut_hero_image_id').val('');
                $('#ruut_hero_image_preview').html('');
                $(this).hide();
            });
        });
    ");
}

// ===================================================================================
// PART 6: PRODUCT-SPECIFIC REVIEWS (WooCommerce Comments)
// ===================================================================================

add_action('rest_api_init', 'ruut_register_product_reviews_endpoint');
function ruut_register_product_reviews_endpoint() {
    register_rest_route('ruut/v1', '/product-reviews', array(
        'methods'  => 'GET',
        'callback' => 'ruut_get_product_reviews_api',
        'permission_callback' => '__return_true',
    ));
    register_rest_route('ruut/v1', '/product-reviews', array(
        'methods'  => 'POST',
        'callback' => 'ruut_create_product_review_api',
        'permission_callback' => '__return_true',
    ));
}

function ruut_create_product_review_api($request) {
    $params = $request->get_json_params();
    if (empty($params)) {
        $params = $_POST;
    }

    $product_id = isset($params['product_id']) ? intval($params['product_id']) : 0;
    $reviewer_name = isset($params['reviewer_name']) ? sanitize_text_field($params['reviewer_name']) : '';
    $reviewer_email = isset($params['reviewer_email']) ? sanitize_email($params['reviewer_email']) : '';
    $review_content = isset($params['review_content']) ? sanitize_textarea_field($params['review_content']) : '';
    $rating = isset($params['rating']) ? intval($params['rating']) : 5;

    if (empty($product_id)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Product ID is required.'), 400);
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Product not found.'), 404);
    }

    if (empty($reviewer_name) || empty($review_content)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Name and review content are required.'), 400);
    }

    if ($rating < 1 || $rating > 5) {
        $rating = 5;
    }

    // Insert comment as WooCommerce review
    $comment_data = array(
        'comment_post_ID'      => $product_id,
        'comment_author'       => $reviewer_name,
        'comment_author_email' => $reviewer_email,
        'comment_content'      => $review_content,
        'comment_type'         => 'review',
        'comment_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 254) : '',
        'comment_author_IP'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',
    );

    $approved = wp_allow_comment($comment_data, true);
    if (is_wp_error($approved)) {
        return new WP_REST_Response(array('success' => false, 'message' => $approved->get_error_message()), 400);
    }
    
    $comment_data['comment_approved'] = $approved;
    $comment_id = wp_insert_comment($comment_data);

    if (is_wp_error($comment_id) || !$comment_id) {
        $error_msg = is_wp_error($comment_id) ? $comment_id->get_error_message() : 'Unknown error';
        return new WP_REST_Response(array('success' => false, 'message' => 'Failed to save review: ' . $error_msg), 500);
    }

    // Save rating metadata for WooCommerce
    update_comment_meta($comment_id, 'rating', $rating);

    $message = ($approved === 1) ? 'Thank you! Your review has been submitted.' : 'Thank you! Your review has been submitted and is pending moderation.';
    return new WP_REST_Response(array('success' => true, 'message' => $message), 200);
}

function ruut_get_product_reviews_api($request) {
    $product_id = $request->get_param('product_id');
    if (empty($product_id)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'product_id is required.'), 400);
    }

    $product_id = intval($product_id);
    $product = wc_get_product($product_id);
    if (!$product) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Product not found.'), 404);
    }

    $comments = get_comments(array(
        'post_id' => $product_id,
        'status'  => 'approve',
        'type'    => 'review',
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
    ));

    $reviews = array();
    $total_rating = 0;

    foreach ($comments as $comment) {
        $rating = intval(get_comment_meta($comment->comment_ID, 'rating', true));
        if ($rating < 1) $rating = 5;
        $total_rating += $rating;

        $avatar_url = get_avatar_url($comment->comment_author_email, array('size' => 96, 'default' => 'mp'));

        $verified = function_exists('wc_review_is_from_verified_owner') ? wc_review_is_from_verified_owner($comment->comment_ID) : false;
        
        $reviews[] = array(
            'id'              => $comment->comment_ID,
            'reviewer_name'   => $comment->comment_author,
            'review_content'  => wp_strip_all_tags($comment->comment_content),
            'rating'          => $rating,
            'avatar'          => $avatar_url,
            'date'            => date('M j, Y', strtotime($comment->comment_date)),
            'verified'        => (bool) $verified
        );
    }

    $avg_rating = count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 0;

    return rest_ensure_response(array(
        'product_id'   => $product_id,
        'total_reviews' => count($reviews),
        'average_rating' => $avg_rating,
        'reviews'      => $reviews,
    ));
}

// ===================================================================================
// PART 7: FAQ CUSTOM POST TYPE & REST ENDPOINT
// ===================================================================================

add_action('init', 'ruut_register_faq_post_type');
function ruut_register_faq_post_type() {
    register_post_type('ruut_faq', array(
        'labels' => array(
            'name'               => 'FAQs',
            'singular_name'      => 'FAQ',
            'add_new'            => 'Add New FAQ',
            'add_new_item'       => 'Add New FAQ',
            'edit_item'          => 'Edit FAQ',
            'new_item'           => 'New FAQ',
            'view_item'          => 'View FAQ',
            'search_items'       => 'Search FAQs',
            'not_found'          => 'No FAQs found',
            'not_found_in_trash' => 'No FAQs found in Trash',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'has_archive'  => false,
        'supports'     => array('title', 'editor'),
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-editor-help',
    ));
}

// FAQ Order Metabox
add_action('add_meta_boxes', 'ruut_faq_add_meta_boxes');
function ruut_faq_add_meta_boxes() {
    add_meta_box(
        'ruut_faq_order',
        'FAQ Order',
        'ruut_faq_order_metabox_html',
        'ruut_faq',
        'side',
        'default'
    );
}

function ruut_faq_order_metabox_html($post) {
    $order = get_post_meta($post->ID, '_faq_order', true) ?: '0';
    wp_nonce_field('ruut_faq_meta_nonce', 'ruut_faq_meta_nonce_field');
    ?>
    <p>
        <label for="ruut_faq_order">Display Order (lower = first):</label>
        <input type="number" id="ruut_faq_order" name="ruut_faq_order" value="<?php echo esc_attr($order); ?>" class="widefat" min="0" step="1" />
    </p>
    <?php
}

add_action('save_post', 'ruut_save_faq_meta');
function ruut_save_faq_meta($post_id) {
    if (!isset($_POST['ruut_faq_meta_nonce_field']) || !wp_verify_nonce($_POST['ruut_faq_meta_nonce_field'], 'ruut_faq_meta_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== 'ruut_faq') {
        return;
    }
    if (isset($_POST['ruut_faq_order'])) {
        update_post_meta($post_id, '_faq_order', intval($_POST['ruut_faq_order']));
    }
}

// FAQ REST Endpoint
add_action('rest_api_init', 'ruut_register_faq_rest_endpoint');
function ruut_register_faq_rest_endpoint() {
    register_rest_route('ruut/v1', '/faqs', array(
        'methods'             => 'GET',
        'callback'            => 'ruut_get_faqs_api',
        'permission_callback' => '__return_true',
    ));
}

function ruut_get_faqs_api($request) {
    $posts = get_posts(array(
        'post_type'      => 'ruut_faq',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => '_faq_order',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
    ));

    $data = array();
    foreach ($posts as $post) {
        $data[] = array(
            'id'       => $post->ID,
            'question' => $post->post_title,
            'answer'   => wp_strip_all_tags($post->post_content),
            'order'    => intval(get_post_meta($post->ID, '_faq_order', true)),
        );
    }

    return rest_ensure_response($data);
}

// ===================================================================================
// PART 8: MEDIA UPLOAD ENQUEUE FOR RUUT SETTINGS PAGE
// ===================================================================================

add_action('admin_enqueue_scripts', 'ruut_enqueue_settings_media');
function ruut_enqueue_settings_media($hook) {
    if ($hook !== 'toplevel_page_ruut-settings') {
        return;
    }
    wp_enqueue_media();
    wp_add_inline_script('media-editor', "
        jQuery(document).ready(function($) {
            var frame;
            $('#ruut_story_image_btn').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: 'Select Story Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#ruut_story_image').val(attachment.url);
                    $('#ruut_story_image_preview').html('<img src=\"' + attachment.url + '\" style=\"max-width:300px;max-height:200px;display:block;border:1px solid #ccc;padding:4px;\" />');
                });
                frame.open();
            });
        });
    ");
}

// ===================================================================================
// PART 9: THE RUUT STORY REST ENDPOINT
// ===================================================================================

add_action('rest_api_init', 'ruut_register_story_rest_endpoint');
function ruut_register_story_rest_endpoint() {
    register_rest_route('ruut/v1', '/story', array(
        'methods'             => 'GET',
        'callback'            => 'ruut_get_story_api',
        'permission_callback' => '__return_true',
    ));
}

function ruut_get_story_api($request) {
    return rest_ensure_response(array(
        'title'   => get_option('ruut_story_title', 'The Ruut Story'),
        'content' => wpautop(get_option('ruut_story_content', '')),
        'image'   => get_option('ruut_story_image', ''),
    ));
}

// ===================================================================================
// PART 10: PRODUCT REELS CUSTOM POST TYPE & REST ENDPOINT
// ===================================================================================

add_action('init', 'ruut_register_reel_post_type');
function ruut_register_reel_post_type() {
    register_post_type('ruut_reel', array(
        'labels' => array(
            'name'               => 'Reels',
            'singular_name'      => 'Reel',
            'add_new'            => 'Add New Reel',
            'add_new_item'       => 'Add New Reel',
            'edit_item'          => 'Edit Reel',
            'new_item'           => 'New Reel',
            'view_item'          => 'View Reel',
            'search_items'       => 'Search Reels',
            'not_found'          => 'No Reels found',
            'not_found_in_trash' => 'No Reels found in Trash',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'has_archive'  => false,
        'supports'     => array('title', 'thumbnail'),
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-video-alt3',
    ));
}

// Reel Metaboxes
add_action('add_meta_boxes', 'ruut_reel_add_meta_boxes');
function ruut_reel_add_meta_boxes() {
    add_meta_box(
        'ruut_reel_details',
        'Reel Details',
        'ruut_reel_details_metabox_html',
        'ruut_reel',
        'normal',
        'high'
    );
}

function ruut_reel_details_metabox_html($post) {
    $video_url  = get_post_meta($post->ID, '_reel_video_url', true) ?: '';
    $order      = get_post_meta($post->ID, '_reel_order', true) ?: '0';
    $product_id = get_post_meta($post->ID, '_reel_product_id', true) ?: '';
    wp_nonce_field('ruut_reel_meta_nonce', 'ruut_reel_meta_nonce_field');
    ?>
    <table class="form-table">
        <tr>
            <th><label for="ruut_reel_video_url">Video URL</label></th>
            <td>
                <input type="url" id="ruut_reel_video_url" name="ruut_reel_video_url" value="<?php echo esc_attr($video_url); ?>" class="large-text" placeholder="https://example.com/video.mp4" />
                <p class="description">Direct link to the video file (MP4 recommended).</p>
            </td>
        </tr>
        <tr>
            <th><label for="ruut_reel_product_id">Associated Product ID</label></th>
            <td>
                <input type="number" id="ruut_reel_product_id" name="ruut_reel_product_id" value="<?php echo esc_attr($product_id); ?>" class="regular-text" placeholder="e.g. 2489" />
                <p class="description">WooCommerce Product ID to display under this reel.</p>
            </td>
        </tr>
        <tr>
            <th><label for="ruut_reel_order">Display Order</label></th>
            <td>
                <input type="number" id="ruut_reel_order" name="ruut_reel_order" value="<?php echo esc_attr($order); ?>" class="small-text" min="0" step="1" />
                <p class="description">Lower numbers appear first.</p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('save_post', 'ruut_save_reel_meta');
function ruut_save_reel_meta($post_id) {
    if (!isset($_POST['ruut_reel_meta_nonce_field']) || !wp_verify_nonce($_POST['ruut_reel_meta_nonce_field'], 'ruut_reel_meta_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== 'ruut_reel') {
        return;
    }
    if (isset($_POST['ruut_reel_video_url'])) {
        update_post_meta($post_id, '_reel_video_url', esc_url_raw($_POST['ruut_reel_video_url']));
    }
    if (isset($_POST['ruut_reel_product_id'])) {
        update_post_meta($post_id, '_reel_product_id', sanitize_text_field($_POST['ruut_reel_product_id']));
    }
    if (isset($_POST['ruut_reel_order'])) {
        update_post_meta($post_id, '_reel_order', intval($_POST['ruut_reel_order']));
    }
}

// Reels REST Endpoint
add_action('rest_api_init', 'ruut_register_reels_rest_endpoint');
function ruut_register_reels_rest_endpoint() {
    register_rest_route('ruut/v1', '/reels', array(
        'methods'             => 'GET',
        'callback'            => 'ruut_get_reels_api',
        'permission_callback' => '__return_true',
    ));
}

function ruut_get_reels_api($request) {
    $posts = get_posts(array(
        'post_type'      => 'ruut_reel',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => '_reel_order',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
    ));

    $data = array();
    foreach ($posts as $post) {
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large') ?: '';
        $product_id = get_post_meta($post->ID, '_reel_product_id', true);
        $product_data = null;
        if ( !empty($product_id) && function_exists('wc_get_product') ) {
            $product = wc_get_product( intval($product_id) );
            if ( $product && $product->get_status() === 'publish' ) {
                $product_data = ruut_format_product_data( $product );
            }
        }
        $data[] = array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'video_url' => get_post_meta($post->ID, '_reel_video_url', true),
            'thumbnail' => $thumbnail_url,
            'order'     => intval(get_post_meta($post->ID, '_reel_order', true)),
            'product'   => $product_data,
        );
    }

    return rest_ensure_response($data);
}
