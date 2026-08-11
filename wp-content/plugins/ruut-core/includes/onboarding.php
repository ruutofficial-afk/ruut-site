<?php
/**
 * WooCommerce Account Onboarding Overlay, Redirects, Custom Dashboard & Orders Overrides
 * Extracted from ruut-core.php PART 5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!function_exists('ruut_render_onboarding_overlay')) {
    add_action( 'wp_footer', 'ruut_render_onboarding_overlay' );
    function ruut_render_onboarding_overlay() {
        if ( function_exists('is_account_page') && is_account_page() && is_user_logged_in() ) {
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
                    .ruut-onboarding-maintitle { grid-column: 1 / -1; text-align: center; font-family: var(--ruut-font-serif, 'Conso', 'Georgia', serif); font-size: 2rem; color: var(--ruut-coffee, #4A3324); margin: 0 0 40px 0; font-weight: normal; }
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
                    .ruut-fullscreen-overlay .ruut-onboarding-submit:hover { background-color: var(--ruut-burnt-rose, #895158) !important; opacity: 0.9; }
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
                                        <label>First Name <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
                                        <input type="text" name="billing_first_name" required />
                                    </div>
                                    <div class="ruut-form-col">
                                        <label>Last Name <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
                                        <input type="text" name="billing_last_name" required />
                                    </div>
                                </div>
                                <div class="ruut-form-row ruut-col-group-phone">
                                    <label>Phone <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
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
        if ( function_exists('is_account_page') && is_account_page() && isset( $_POST['ruut_save_onboarding'] ) && isset($_POST['ruut_onboarding_nonce']) ) {
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

            wp_safe_redirect( home_url( '/my-account/' ) );
            exit;
        }
    }
}

if (!function_exists('ruut_redirect_login_to_account')) {
    add_filter( 'woocommerce_login_redirect', 'ruut_redirect_login_to_account', 9999, 2 );
    function ruut_redirect_login_to_account( $redirect_to, $user ) {
        if ( strpos( $redirect_to, 'checkout' ) !== false ) {
            return $redirect_to;
        }
        return home_url( '/my-account/' );
    }
}

if (!function_exists('ruut_redirect_registration_to_account')) {
    add_filter( 'woocommerce_registration_redirect', 'ruut_redirect_registration_to_account', 9999, 1 );
    function ruut_redirect_registration_to_account( $redirect_to ) {
        if ( strpos( $redirect_to, 'checkout' ) !== false ) {
            return $redirect_to;
        }
        return home_url( '/my-account/' );
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
        
        // Fetch up to 5 recent orders to find a non-pending one
        $orders = wc_get_orders( array(
            'customer_id' => $current_user->ID,
            'limit'       => 5,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        $recent_order = null;
        if ( ! empty( $orders ) && is_array( $orders ) ) {
            foreach ( $orders as $o ) {
                if ( $o->get_status() !== 'pending' ) {
                    $recent_order = $o;
                    break;
                }
            }
        }
        ?>
        <div class="ruut-custom-dashboard">
            <div class="ruut-dashboard-welcome">
                <h2>Welcome to the ṚUUT family, <?php echo esc_html( $first_name ); ?>.</h2>
                <p>We're honored to have you here. Your journey into curated elegance and natural harmony begins now.</p>
            </div>
            <?php if ( $recent_order ) : $order = $recent_order; ?>
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
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        $four_years_ago = date('Y-m-d', strtotime('-4 years'));
        $orders = wc_get_orders( array(
            'customer_id'  => $current_user_id,
            'date_created' => '>=' . $four_years_ago,
            'limit'        => -1,
            'orderby'      => 'date',
            'order'        => 'DESC',
        ) );

        // Filter out pending payment orders
        if ( ! empty( $orders ) && is_array( $orders ) ) {
            $orders = array_filter( $orders, function( $order ) {
                return is_a( $order, 'WC_Order' ) && $order->get_status() !== 'pending';
            } );
        }

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
                        <a href="/track?order_id=<?php echo esc_attr( $order->get_order_number() ); ?>&email=<?php echo esc_attr( $current_user->user_email ); ?>" class="ruut-track-order-btn">Track Order</a>
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
