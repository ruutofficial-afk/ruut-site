<?php
/**
 * Product Reels Custom Post Type & REST Endpoint
 * Extracted from ruut-core.php PART 10
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

if ( !function_exists('ruut_get_product_data_direct_fallback') ) {
    function ruut_get_product_data_direct_fallback( $product_id ) {
        global $wpdb;
        $product_id = intval($product_id);
        
        $post = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_title, post_type FROM $wpdb->posts WHERE ID = %d", $product_id ) );
        if ( !$post || !in_array( $post->post_type, array('product', 'product_variation') ) ) {
            return null;
        }
        
        $price = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = '_price'", $product_id ) ) ?: '0';
        $regular_price = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = '_regular_price'", $product_id ) ) ?: $price;
        $thumbnail_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = '_thumbnail_id'", $product_id ) );
        
        $discount_percentage = 0;
        if ( floatval($regular_price) > 0 && floatval($price) < floatval($regular_price) ) {
            $discount_percentage = round( ( ( floatval($regular_price) - floatval($price) ) / floatval($regular_price) ) * 100 );
        }
        
        $image_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '';
        if ( !$image_url ) {
            $image_url = function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('medium') : '';
        }
        
        $categories = array();
        $terms = wp_get_post_terms( $product_id, 'product_cat' );
        if ( !empty($terms) && !is_wp_error($terms) ) {
            foreach ( $terms as $term ) {
                $parent_slug = '';
                if ( $term->parent != 0 ) {
                    $parent_term = get_term( $term->parent, 'product_cat' );
                    if ( $parent_term && !is_wp_error($parent_term) ) {
                        $parent_slug = $parent_term->slug;
                    }
                }
                $categories[] = array(
                    'term_id'     => $term->term_id,
                    'name'        => $term->name,
                    'slug'        => $term->slug,
                    'parent'      => $term->parent,
                    'parent_slug' => $parent_slug,
                );
            }
        }
        
        return array(
            'id'                  => $post->ID,
            'title'               => $post->post_title,
            'price'               => $price,
            'regular_price'       => $regular_price,
            'formatted_price'     => '₹ ' . number_format( (float)$price, 0, '.', ',' ),
            'discount_percentage' => $discount_percentage,
            'image'               => $image_url,
            'permalink'           => get_permalink( $post->ID ),
            'categories'          => $categories,
        );
    }
}

function ruut_get_reels_api($request) {
    nocache_headers();
    $posts = get_posts(array(
        'post_type'        => 'ruut_reel',
        'posts_per_page'   => -1,
        'post_status'      => 'publish',
        'suppress_filters' => true,
    ));

    $data = array();
    foreach ($posts as $post) {
        $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large') ?: '';
        $product_id = get_post_meta($post->ID, '_reel_product_id', true);
        $product_data = null;
        if ( !empty($product_id) ) {
            $prod_id_int = intval(trim($product_id));
            $product = null;
            if ( function_exists('wc_get_product') ) {
                $product = wc_get_product( $prod_id_int );
            }
            if ( !$product ) {
                // Robust Fallback: check post existence (in case translations are suppressing WC query)
                $post_obj = get_post( $prod_id_int );
                if ( $post_obj && in_array( $post_obj->post_type, array('product', 'product_variation') ) ) {
                    $product = wc_get_product( $prod_id_int );
                }
            }
            if ( $product ) {
                $product_data = ruut_format_product_data( $product );
            } else {
                $product_data = ruut_get_product_data_direct_fallback( $prod_id_int );
            }
        }
        $data[] = array(
            'id'              => $post->ID,
            'title'           => $post->post_title,
            'video_url'       => get_post_meta($post->ID, '_reel_video_url', true),
            'thumbnail'       => $thumbnail_url,
            'order'           => intval(get_post_meta($post->ID, '_reel_order', true)),
            'reel_product_id' => $product_id,
            'product'         => $product_data,
        );
    }

    usort($data, function($a, $b) {
        return $a['order'] - $b['order'];
    });

    return new WP_REST_Response($data, 200);
}
