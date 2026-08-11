<?php
/**
 * Dynamic Customer Reviews (Testimonials) — CPT + REST
 * Extracted from ruut-core.php PART 4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
