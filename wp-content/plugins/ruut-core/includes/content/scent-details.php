<?php
/**
 * Product Scent Details Metabox on WooCommerce Product Edit Screen
 * Extracted from ruut-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Product Scent Details Metabox on WooCommerce Product Edit Screen
add_action('add_meta_boxes', 'ruut_product_scent_details_add_meta_box');
function ruut_product_scent_details_add_meta_box() {
    add_meta_box(
        'ruut_product_scent_details_box',
        'Ruut Scent Details',
        'ruut_product_scent_details_metabox_html',
        'product',
        'normal',
        'default'
    );
}

function ruut_product_scent_details_metabox_html($post) {
    wp_nonce_field('ruut_product_scent_details_meta_nonce', 'ruut_product_scent_details_meta_nonce_field');
    echo '<p style="color: #666; font-size: 13px; margin-bottom: 15px;">Customize the scent details fields for this product. Edit both Field Title and Description. Leaving the description/content empty will completely hide that field on the product page.</p>';
    
    $defaults = array(
        1 => array('title' => 'Feels Like', 'meta_key' => 'feels_like'),
        2 => array('title' => 'The Story', 'meta_key' => 'the_story'),
        3 => array('title' => 'The Name', 'meta_key' => 'the_name'),
        4 => array('title' => 'Directions for Use', 'meta_key' => 'directions'),
        5 => array('title' => 'Cautions', 'meta_key' => 'cautions')
    );

    for ($i = 1; $i <= 5; $i++) {
        $saved_title = get_post_meta($post->ID, "scent_field_{$i}_title", true);
        if ($saved_title === '') {
            $saved_title = $defaults[$i]['title'];
        }
        
        $saved_content = get_post_meta($post->ID, "scent_field_{$i}_value", true);
        if ($saved_content === '' && !metadata_exists('post', $post->ID, "scent_field_{$i}_value")) {
            // Fallback to legacy meta key if present
            $legacy_key = $defaults[$i]['meta_key'];
            $saved_content = get_post_meta($post->ID, $legacy_key, true) ?: get_post_meta($post->ID, "_{$legacy_key}", true);
        }
        ?>
        <div style="background: #f9f9f9; padding: 12px 15px; margin-bottom: 12px; border: 1px solid #e5e5e5; border-radius: 6px;">
            <p style="margin: 0 0 6px 0; font-weight: 600; color: #333;">Scent Detail #<?php echo $i; ?></p>
            <p style="margin: 0 0 8px 0;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #555;">Field Title:</label>
                <input type="text" name="scent_field_<?php echo $i; ?>_title" value="<?php echo esc_attr($saved_title); ?>" class="widefat" placeholder="e.g. <?php echo esc_attr($defaults[$i]['title']); ?>" />
            </p>
            <p style="margin: 0;">
                <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #555;">Description / Content (Leave empty to hide):</label>
                <textarea name="scent_field_<?php echo $i; ?>_value" rows="2" class="widefat" placeholder="Enter content..."><?php echo esc_textarea($saved_content); ?></textarea>
            </p>
        </div>
        <?php
    }
}

add_action('save_post', 'ruut_save_product_scent_details_meta');
function ruut_save_product_scent_details_meta($post_id) {
    if (!isset($_POST['ruut_product_scent_details_meta_nonce_field']) || !wp_verify_nonce($_POST['ruut_product_scent_details_meta_nonce_field'], 'ruut_product_scent_details_meta_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== 'product') {
        return;
    }
    for ($i = 1; $i <= 5; $i++) {
        if (isset($_POST["scent_field_{$i}_title"])) {
            update_post_meta($post_id, "scent_field_{$i}_title", sanitize_text_field($_POST["scent_field_{$i}_title"]));
        }
        if (isset($_POST["scent_field_{$i}_value"])) {
            update_post_meta($post_id, "scent_field_{$i}_value", wp_kses_post($_POST["scent_field_{$i}_value"]));
        }
    }
}
