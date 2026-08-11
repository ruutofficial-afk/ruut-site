<?php
/**
 * Custom Frontend Routing & Redirects for Cart
 * Extracted from ruut-core.php PART 6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
    // Redirect when accessing the default WooCommerce cart page if WooCommerce is active
    if ( function_exists( 'is_cart' ) && is_cart() && ! is_admin() ) {
        wp_redirect( site_url( '/cart.html' ), 301 );
        exit;
    }
}

/**
 * Disable canonical redirect for the cart page to prevent loop between /cart and /cart/
 */
add_filter( 'redirect_canonical', 'ruut_disable_cart_canonical_redirect', 10, 2 );
function ruut_disable_cart_canonical_redirect( $redirect_url, $requested_url ) {
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        return false;
    }
    return $redirect_url;
}
