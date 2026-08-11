<?php
/**
 * Shared Helper and Utility Functions
 * Extracted from ruut-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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

        // If no product-specific FAQs found, fallback to global published FAQs from 'ruut_faq' post type
        if ( empty($faqs) ) {
            $cpt_faqs = get_posts(array(
                'post_type'      => 'ruut_faq',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_key'       => '_faq_order',
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
            ));
            if ( !empty($cpt_faqs) ) {
                foreach ($cpt_faqs as $post) {
                    $faqs[] = array(
                        'question' => $post->post_title,
                        'answer'   => wp_strip_all_tags($post->post_content)
                    );
                }
            }
        }

        return $faqs;
    }
}

if (!function_exists('ruut_get_product_data_direct_fallback')) {
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

if (!function_exists("ruut_format_product_data")) {
    function ruut_format_product_data( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return null;
        }
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

        $scent_details = array();
        $scent_defaults = array(
            1 => array('title' => 'Feels Like', 'value' => $feels_like),
            2 => array('title' => 'The Story', 'value' => $the_story),
            3 => array('title' => 'The Name', 'value' => $the_name),
            4 => array('title' => 'Directions for Use', 'value' => $directions),
            5 => array('title' => 'Cautions', 'value' => $warnings)
        );

        for ($i = 1; $i <= 5; $i++) {
            $title = get_post_meta($product->get_id(), "scent_field_{$i}_title", true);
            if ($title === '') {
                $title = $scent_defaults[$i]['title'];
            }
            $val = get_post_meta($product->get_id(), "scent_field_{$i}_value", true);
            if ($val === '') {
                if (metadata_exists('post', $product->get_id(), "scent_field_{$i}_value")) {
                    $val = '';
                } else {
                    $val = $scent_defaults[$i]['value'];
                }
            }
            if ($val !== '') {
                $scent_details[] = array(
                    'title'   => $title,
                    'content' => $val,
                    'value'   => $val
                );
            }
        }

        // Diffuser & Essential Oils Add-on Logic
        $available_oils = array();
        $is_diffuser = false;
        $is_essential_oil = false;

        if (!empty($categories_list)) {
            foreach ($categories_list as $cat) {
                if (stripos($cat['name'], 'oil') !== false || stripos($cat['name'], 'oils') !== false) {
                    $is_essential_oil = true;
                }
            }
            if (!$is_essential_oil) {
                foreach ($categories_list as $cat) {
                    if (stripos($cat['name'], 'diffuser') !== false) {
                        $is_diffuser = true;
                    }
                }
            }
        }
        if (!$is_essential_oil && (stripos($product->get_name(), 'oil') !== false || stripos($product->get_name(), 'essential') !== false)) {
            $is_essential_oil = true;
        }
        if (!$is_essential_oil && !$is_diffuser && stripos($product->get_name(), 'diffuser') !== false) {
            $is_diffuser = true;
        }

        $promo_mode = get_option('ruut_diffuser_promo_mode', 'first_purchase_free');
        $bundle_pct = (float) get_option('ruut_diffuser_bundle_discount_pct', '40');

        if ($is_diffuser && function_exists('wc_get_products')) {
            $oil_cats = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'fields' => 'slugs'
            ));
            $oil_slugs = array();
            if (!is_wp_error($oil_cats) && !empty($oil_cats)) {
                foreach ($oil_cats as $slug) {
                    if (stripos($slug, 'oil') !== false) {
                        $oil_slugs[] = $slug;
                    }
                }
            }
            if (empty($oil_slugs)) {
                $oil_slugs = array('essential-oils', 'essential-oil', 'oils', 'oil', 'essential-oil-blends', 'diffuser-oil', 'diffuser-oils');
            }

            $oil_products_by_cat = wc_get_products(array(
                'status' => 'publish',
                'limit'  => 50,
                'category' => $oil_slugs
            ));
            $oil_products_by_search = wc_get_products(array(
                'status' => 'publish',
                'limit'  => 50,
                's'      => 'oil'
            ));

            $all_oil_products = array();
            $seen_ids = array();

            $merged_items = array_merge(
                is_array($oil_products_by_cat) ? $oil_products_by_cat : array(),
                is_array($oil_products_by_search) ? $oil_products_by_search : array()
            );

            foreach ($merged_items as $oil_item) {
                if ($oil_item && is_object($oil_item) && !in_array($oil_item->get_id(), $seen_ids)) {
                    if ($oil_item->get_id() === $product->get_id()) continue;
                    $seen_ids[] = $oil_item->get_id();
                    $all_oil_products[] = $oil_item;
                }
            }

            foreach ($all_oil_products as $oil) {
                $oil_img = wp_get_attachment_image_url($oil->get_image_id(), 'medium');
                $oil_price = (float)$oil->get_price();
                $promo_price = $oil_price;
                $promo_label = '';

                if ($promo_mode === 'first_purchase_free') {
                    $promo_price = 0;
                    $promo_label = 'FREE (1st Purchase)';
                } else if ($promo_mode === 'bundle_discount') {
                    $promo_price = max(0, round($oil_price * (1 - ($bundle_pct / 100)), 2));
                    $promo_label = $bundle_pct . '% OFF Bundle';
                }

                $available_oils[] = array(
                    'id' => $oil->get_id(),
                    'title' => $oil->get_name(),
                    'price' => $oil_price,
                    'promo_price' => $promo_price,
                    'promo_mode' => $promo_mode,
                    'promo_label' => $promo_label,
                    'formatted_price' => strip_tags(wc_price($oil_price)),
                    'formatted_promo_price' => strip_tags(wc_price($promo_price)),
                    'image' => $oil_img ?: $image_url,
                    'short_description' => strip_tags($oil->get_short_description())
                );
            }
        }

        // Fetch approved reviews dynamically to calculate real-time average rating and count
        $comments = get_comments(array(
            'post_id' => $product->get_id(),
            'status'  => 'approve',
            'type'    => 'review',
        ));
        
        $review_count = count($comments);
        if ($review_count > 0) {
            $total_rating = 0;
            foreach ($comments as $comment) {
                $rating = intval(get_comment_meta($comment->comment_ID, 'rating', true));
                if ($rating < 1) $rating = 5;
                $total_rating += $rating;
            }
            $avg_rating = round($total_rating / $review_count, 1);
        } else {
            $avg_rating = 4.5;
        }

        return array(
            "id"            => $product->get_id(),
            "title"         => $product->get_name(),
            "description"   => $product->get_short_description() ?: $product->get_description(),
            "image"         => $image_url,
            "gallery"       => $gallery,
            "average_rating" => $avg_rating,
            "review_count"   => $review_count,
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
            "scent_details"     => $scent_details,
            "is_diffuser"       => $is_diffuser,
            "is_essential_oil"  => $is_essential_oil,
            "available_oils"    => $available_oils,
            "faqs"              => ruut_get_product_faqs_data($product->get_id()),
            "cross_sell_ids"     => array_map( 'intval', (array) $product->get_cross_sell_ids() ),
            "upsell_ids"         => array_map( 'intval', (array) $product->get_upsell_ids() ),
            "linked_product_ids" => array_values( array_unique( array_merge(
                array_map( 'intval', (array) $product->get_cross_sell_ids() ),
                array_map( 'intval', (array) $product->get_upsell_ids() )
            ) ) )
        );
    }
}
