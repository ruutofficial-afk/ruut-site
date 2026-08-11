<?php
/**
 * WooCommerce Direct Checkout Multi Add-to-cart and Coupon sync from URL
 * Extracted from ruut-core.php PART 9 (⚠️ EXACT COPY of core logic)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_loaded', 'ruut_handle_multi_add_to_cart_from_url', 5 );
function ruut_handle_multi_add_to_cart_from_url() {
    if ( is_admin() || !function_exists('WC') ) {
        return;
    }
    if ( isset( $_GET['add-to-cart'] ) && ! empty( $_GET['add-to-cart'] ) ) {
        $product_ids_str = sanitize_text_field( $_GET['add-to-cart'] );
        
        // Parse the comma-separated or single product IDs
        $product_ids = array_map( 'intval', explode( ',', $product_ids_str ) );
        
        // Get corresponding quantities if provided
        $quantities = array();
        if ( isset( $_GET['quantity'] ) && ! empty( $_GET['quantity'] ) ) {
            $quantities_str = sanitize_text_field( $_GET['quantity'] );
            $quantities = array_map( 'intval', explode( ',', $quantities_str ) );
        }
        
        ruut_ensure_cart();
        
        if ( WC()->cart ) {
            // Clear the existing server cart session to exactly match the incoming frontend cart
            WC()->cart->empty_cart();
            
            foreach ( $product_ids as $index => $product_id ) {
                $qty = isset( $quantities[$index] ) ? $quantities[$index] : 1;
                if ( $product_id > 0 && $qty > 0 ) {
                    $product = wc_get_product( $product_id );
                    if ( $product ) {
                        if ( $product->is_type( 'variation' ) ) {
                            $parent_id = $product->get_parent_id();
                            WC()->cart->add_to_cart( $parent_id, $qty, $product_id );
                        } else {
                            WC()->cart->add_to_cart( $product_id, $qty );
                        }
                    }
                }
            }
            
            // Apply coupon if passed in the URL parameters
            if ( isset( $_GET['coupon_code'] ) && ! empty( $_GET['coupon_code'] ) ) {
                $coupon_code = sanitize_text_field( $_GET['coupon_code'] );
                if ( ! WC()->cart->has_discount( $coupon_code ) ) {
                    WC()->cart->add_discount( $coupon_code );
                }
            }
            
            // Clean up the URL query params so they don't keep adding on page reload/refresh
            $current_url = home_url( $_SERVER['REQUEST_URI'] );
            $clean_url = remove_query_arg( array( 'add-to-cart', 'quantity', 'coupon_code' ), $current_url );
            
            // Perform safe redirect to clear query params
            wp_safe_redirect( $clean_url );
            exit;
        }
    }
}
