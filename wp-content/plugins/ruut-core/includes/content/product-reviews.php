<?php
/**
 * Product-Specific Reviews (WooCommerce Comments) — REST Endpoint
 * Extracted from ruut-core.php PART 6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

    // Insert comment as WooCommerce review using WordPress APIs safely
    $comment_data = array(
        'comment_post_ID'      => $product_id,
        'comment_author'       => $reviewer_name,
        'comment_author_email' => $reviewer_email,
        'comment_author_url'   => '',
        'comment_content'      => $review_content,
        'comment_type'         => 'review',
        'comment_parent'       => 0,
        'user_id'              => 0,
        'comment_author_IP'    => preg_replace( '/[^0-9a-fA-F:.,_]/', '', $_SERVER['REMOTE_ADDR'] ),
        'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ) : '',
        'comment_date'         => current_time( 'mysql' ),
        'comment_date_gmt'     => current_time( 'mysql', 1 ),
    );

    // Validate the comment with avoid_die = true to return a clean error without calling wp_die()
    $approved = wp_allow_comment( $comment_data, true );

    if ( is_wp_error( $approved ) ) {
        return new WP_REST_Response(array('success' => false, 'message' => $approved->get_error_message()), 400);
    }

    if ( $approved === 'spam' ) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Review flagged as spam.'), 400);
    }

    $comment_data['comment_approved'] = $approved;

    // Insert comment directly to prevent other plugins from calling wp_die()
    $comment_id = wp_insert_comment( $comment_data );

    if ( !$comment_id ) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Failed to save review.'), 500);
    }

    // Save rating metadata for WooCommerce
    update_comment_meta($comment_id, 'rating', $rating);

    // Sync WooCommerce rating cache for this product
    if ( class_exists( 'WC_Comments' ) ) {
        $product = wc_get_product( $product_id );
        if ( $product ) {
            WC_Comments::update_product_review_count( $product );
        }
    }

    $comment = get_comment($comment_id);
    $approved = $comment ? $comment->comment_approved : '0';
    $message = ($approved === '1') ? 'Thank you! Your review has been submitted.' : 'Thank you! Your review has been submitted and is pending moderation.';
    return new WP_REST_Response(array('success' => true, 'message' => $message), 200);
}

function ruut_get_product_reviews_api($request) {
    $product_id = $request->get_param('product_id');
    if (empty($product_id)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'product_id is required.'), 400);
    }

    $product_id = intval($product_id);
    if ( function_exists('wc_get_product') ) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Product not found.'), 404);
        }
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
