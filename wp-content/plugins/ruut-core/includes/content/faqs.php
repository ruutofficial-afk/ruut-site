<?php
/**
 * FAQ Custom Post Type & REST Endpoint + Product-specific FAQs
 * Extracted from ruut-core.php PART 7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

// Product FAQs Metabox on WooCommerce Product Edit Screen
add_action('add_meta_boxes', 'ruut_product_faqs_add_meta_box');
function ruut_product_faqs_add_meta_box() {
    add_meta_box(
        'ruut_product_faqs_box',
        'Product FAQs (Single Product Page)',
        'ruut_product_faqs_metabox_html',
        'product',
        'normal',
        'default'
    );
}

function ruut_product_faqs_metabox_html($post) {
    wp_nonce_field('ruut_product_faqs_meta_nonce', 'ruut_product_faqs_meta_nonce_field');
    echo '<p style="color: #666; font-size: 13px; margin-bottom: 15px;">Add custom Frequently Asked Questions specific to this product. If left blank, general site FAQs will be automatically displayed on the single product page.</p>';
    for ($i = 1; $i <= 5; $i++) {
        $q = get_post_meta($post->ID, "faq_{$i}_question", true) ?: get_post_meta($post->ID, "_faq_{$i}_question", true);
        $a = get_post_meta($post->ID, "faq_{$i}_answer", true) ?: get_post_meta($post->ID, "_faq_{$i}_answer", true);
        ?>
        <div style="background: #f9f9f9; padding: 12px 15px; margin-bottom: 12px; border: 1px solid #e5e5e5; border-radius: 6px;">
            <p style="margin: 0 0 6px 0; font-weight: 600; color: #333;">FAQ #<?php echo $i; ?></p>
            <p style="margin: 0 0 8px 0;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #555;">Question:</label>
                <input type="text" name="faq_<?php echo $i; ?>_question" value="<?php echo esc_attr($q); ?>" class="widefat" placeholder="e.g. How long does the scent last?" />
            </p>
            <p style="margin: 0;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #555;">Answer:</label>
                <textarea name="faq_<?php echo $i; ?>_answer" rows="2" class="widefat" placeholder="e.g. Each 200ml mist provides up to 1200 sprays..."><?php echo esc_textarea($a); ?></textarea>
            </p>
        </div>
        <?php
    }
}

add_action('save_post', 'ruut_save_product_faqs_meta');
function ruut_save_product_faqs_meta($post_id) {
    if (!isset($_POST['ruut_product_faqs_meta_nonce_field']) || !wp_verify_nonce($_POST['ruut_product_faqs_meta_nonce_field'], 'ruut_product_faqs_meta_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== 'product') {
        return;
    }
    for ($i = 1; $i <= 5; $i++) {
        if (isset($_POST["faq_{$i}_question"])) {
            update_post_meta($post_id, "faq_{$i}_question", sanitize_text_field($_POST["faq_{$i}_question"]));
        }
        if (isset($_POST["faq_{$i}_answer"])) {
            update_post_meta($post_id, "faq_{$i}_answer", wp_kses_post($_POST["faq_{$i}_answer"]));
        }
    }
}
