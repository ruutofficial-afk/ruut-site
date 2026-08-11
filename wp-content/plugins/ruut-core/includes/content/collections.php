<?php
/**
 * Dynamic Collections & Product Categories — REST + Hero Image
 * Extracted from ruut-core.php PART 5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
    $parent_param = $request->get_param('parent');
    
    if (!empty($slug)) {
        $category = get_term_by('slug', sanitize_text_field($slug), 'product_cat');
        if (!$category && in_array(strtolower($slug), array('airmist', 'air-mist', 'air-mists', 'airmists'))) {
            foreach (array('air-mist', 'air-mists', 'airmists', 'airmist') as $alt) {
                $category = get_term_by('slug', $alt, 'product_cat');
                if ($category) break;
            }
        }
        if (!$category && in_array(strtolower($slug), array('diffuser', 'diffusers'))) {
            foreach (array('diffusers', 'diffuser') as $alt) {
                $category = get_term_by('slug', $alt, 'product_cat');
                if ($category) break;
            }
        }
        if (!$category && in_array(strtolower($slug), array('essential-oils', 'essential-oil', 'oils', 'oil'))) {
            foreach (array('essential-oils', 'essential-oil', 'oils', 'oil') as $alt) {
                $category = get_term_by('slug', $alt, 'product_cat');
                if ($category) break;
            }
        }

        if ($category) {
            return rest_ensure_response(ruut_format_category_data($category));
        } else {
            return new WP_Error('ruut_collection_not_found', 'Collection not found', array('status' => 404));
        }
    }
    
    $args = array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    );

    if ( isset($parent_param) && $parent_param !== '' ) {
        if ( is_numeric($parent_param) ) {
            $args['parent'] = intval($parent_param);
        } else {
            $parent_term = get_term_by('slug', sanitize_text_field($parent_param), 'product_cat');
            if (!$parent_term && in_array(strtolower($parent_param), array('airmist', 'air-mist', 'air-mists', 'airmists'))) {
                foreach (array('air-mist', 'air-mists', 'airmists', 'airmist') as $alt) {
                    $parent_term = get_term_by('slug', $alt, 'product_cat');
                    if ($parent_term) break;
                }
            }
            if (!$parent_term && in_array(strtolower($parent_param), array('diffuser', 'diffusers'))) {
                foreach (array('diffusers', 'diffuser') as $alt) {
                    $parent_term = get_term_by('slug', $alt, 'product_cat');
                    if ($parent_term) break;
                }
            }

            if ( $parent_term ) {
                $args['parent'] = $parent_term->term_id;
            } else {
                return rest_ensure_response(array());
            }
        }
    }
    
    $categories = get_terms($args);
    if ( is_wp_error($categories) ) {
        return rest_ensure_response(array());
    }
    
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
        'type' => ($cat->parent == 0 ? 'category' : 'collection'),
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
