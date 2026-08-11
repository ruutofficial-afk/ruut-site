<?php
/**
 * Admin Menu, Settings Page, and Configuration Options
 * Extracted from ruut-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register Admin Menu
add_action('admin_menu', 'ruut_register_admin_menu');
function ruut_register_admin_menu() {
    add_menu_page(
        'RUUT Settings',
        'RUUT Settings',
        'manage_options',
        'ruut-settings',
        'ruut_settings_page_html',
        'dashicons-store',
        30
    );
}

// Register Settings
add_action('admin_init', 'ruut_register_settings');
function ruut_register_settings() {
    register_setting('ruut_settings_group', 'ruut_announcement_text');
    register_setting('ruut_settings_group', 'ruut_story_title');
    register_setting('ruut_settings_group', 'ruut_story_content');
    register_setting('ruut_settings_group', 'ruut_story_image');
    register_setting('ruut_settings_group', 'ruut_progress_bar_enabled');
    register_setting('ruut_settings_group', 'ruut_offer_categories');
    register_setting('ruut_settings_group', 'ruut_offer_thresholds');
    register_setting('ruut_settings_group', 'ruut_google_client_id');
    register_setting('ruut_settings_group', 'ruut_shiprocket_api_key');
    register_setting('ruut_settings_group', 'ruut_shiprocket_email');
    register_setting('ruut_settings_group', 'ruut_shiprocket_password');
    register_setting('ruut_settings_group', 'ruut_webhook_security_token');
    register_setting('ruut_settings_group', 'ruut_diffuser_promo_mode');
    register_setting('ruut_settings_group', 'ruut_diffuser_bundle_discount_pct');
    register_setting('ruut_settings_group', 'ruut_online_payment_discount_pct');
}

// Admin Page HTML
function ruut_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>RUUT Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('ruut_settings_group');
            do_settings_sections('ruut_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Shiprocket API Token (Auto-refreshed)</th>
                    <td>
                        <input type="text" name="ruut_shiprocket_api_key" value="<?php echo esc_attr(get_option('ruut_shiprocket_api_key', '')); ?>" class="regular-text" />
                        <p class="description">Auto-generated token. Leave blank if email/password are set below — the system will generate it automatically.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Shiprocket API Email</th>
                    <td>
                        <input type="email" name="ruut_shiprocket_email" value="<?php echo esc_attr(get_option('ruut_shiprocket_email', '')); ?>" class="regular-text" />
                        <p class="description">Your Shiprocket API user email (Settings → API → API Users in Shiprocket dashboard).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Shiprocket API Password</th>
                    <td>
                        <input type="password" name="ruut_shiprocket_password" value="<?php echo esc_attr(get_option('ruut_shiprocket_password', '')); ?>" class="regular-text" />
                        <p class="description">Your Shiprocket API user password. Used to auto-refresh the token when it expires (every 10 days).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Webhook URL (for Shiprocket)</th>
                    <td>
                        <code style="padding: 6px 10px; background: #f0f0f1; display: inline-block; font-size: 13px;"><?php echo esc_url(rest_url('ruut/v1/delivery-hook')); ?></code>
                        <p class="description">Copy this URL and paste it in Shiprocket Dashboard → Settings → API → Webhooks → Callback URL.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Webhook Security Token</th>
                    <td>
                        <input type="text" name="ruut_webhook_security_token" value="<?php echo esc_attr(get_option('ruut_webhook_security_token', '')); ?>" class="regular-text" />
                        <p class="description">Optional. If set, paste the same token in Shiprocket's "Security Token" field. Shiprocket will send it as <code>x-api-key</code> header.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Announcement Bar Texts (Separate by | for multiple messages)</th>
                    <td>
                        <textarea name="ruut_announcement_text" rows="5" cols="50" class="large-text"><?php echo esc_attr(get_option('ruut_announcement_text', '✨ EXPLORE THE ATLAS COLLECTION ✨ | 🌿 FREE SHIPPING ON ALL ORDERS ABOVE ₹1500 🌿')); ?></textarea>
                        <p class="description">These will scroll across the top announcement bar on the landing page.</p>
                    </td>
                </tr>

                
                <tr valign="top" style="background:#f0f0f0; padding:10px 15px; font-weight:bold;">
                    <th scope="row" colspan="2">PROGRESS BAR & GIFT OFFER SETTINGS</th>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable Progress Bar & Gift Offer</th>
                    <td>
                        <select name="ruut_progress_bar_enabled">
                            <option value="yes" <?php selected(get_option('ruut_progress_bar_enabled', 'yes'), 'yes'); ?>>Enabled</option>
                            <option value="no" <?php selected(get_option('ruut_progress_bar_enabled', 'yes'), 'no'); ?>>Disabled</option>
                        </select>
                        <p class="description">Toggle to enable or disable the progress bar and free gift features in the mini-cart and cart page.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Progress Bar Qualifying Categories</th>
                    <td>
                        <?php
                        $selected_cats = get_option('ruut_offer_categories', array('all'));
                        if (!is_array($selected_cats)) {
                            $selected_cats = array('all');
                        }
                        
                        // "All Categories" option
                        $all_checked = in_array('all', $selected_cats) ? 'checked' : '';
                        echo '<label style="margin-right: 15px; display: inline-block; font-weight: bold;"><input type="checkbox" name="ruut_offer_categories[]" value="all" ' . $all_checked . '> All Categories</label><br><br>';
                        
                        $categories = get_terms( array(
                            'taxonomy'   => 'product_cat',
                            'hide_empty' => false,
                        ) );
                        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
                            foreach ( $categories as $cat ) {
                                $checked = in_array( (string)$cat->term_id, $selected_cats ) || in_array( $cat->slug, $selected_cats ) ? 'checked' : '';
                                echo '<label style="margin-right: 15px; display: inline-block;"><input type="checkbox" name="ruut_offer_categories[]" value="' . esc_attr( $cat->term_id ) . '" ' . $checked . '> ' . esc_html( $cat->name ) . '</label>';
                            }
                        } else {
                            echo '<p>No categories found.</p>';
                        }
                        ?>
                        <p class="description">Select the product categories that qualify for the progress bar offer. If "All Categories" is selected, all categories will qualify.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Dynamic Offer Thresholds</th>
                    <td>
                        <table class="wp-list-table widefat fixed striped" id="ruut-thresholds-table" style="max-width: 1000px; margin-bottom: 10px;">
                            <thead>
                                <tr>
                                    <th style="width: 15%; padding: 10px;">Qty Threshold</th>
                                    <th style="width: 35%; padding: 10px;">Compulsory Free Product</th>
                                    <th style="width: 25%; padding: 10px;">Random Gift Pool (Select Multiple)</th>
                                    <th style="width: 15%; padding: 10px;">Discount (₹)</th>
                                    <th style="width: 10%; padding: 10px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $thresholds_json = get_option('ruut_offer_thresholds', '[]');
                                $thresholds = json_decode($thresholds_json, true);
                                if (!is_array($thresholds)) {
                                    $thresholds = array();
                                }
                                
                                // Fetch all products for select
                                $all_prods = array();
                                if (function_exists('wc_get_products')) {
                                    $products_obj = wc_get_products(array('limit' => -1, 'status' => 'publish'));
                                    foreach ($products_obj as $prod) {
                                        $all_prods[] = array(
                                            'id' => $prod->get_id(),
                                            'name' => $prod->get_name()
                                        );
                                    }
                                }

                                foreach ($thresholds as $index => $t) {
                                    $qty = intval($t['quantity']);
                                    $compulsory_id = isset($t['compulsory_gift_id']) ? intval($t['compulsory_gift_id']) : 0;
                                    $random_pool = isset($t['random_gift_pool']) && is_array($t['random_gift_pool']) ? array_map('intval', $t['random_gift_pool']) : array();
                                    $discount = isset($t['discount']) ? floatval($t['discount']) : 0;
                                    ?>
                                    <tr class="threshold-row">
                                        <td style="padding: 10px;">
                                            <input type="number" class="row-qty" value="<?php echo esc_attr($qty); ?>" min="1" step="1" style="width: 90%;" required />
                                        </td>
                                        <td style="padding: 10px;">
                                            <input type="text" class="compulsory-search" placeholder="Search product..." style="width: 95%; margin-bottom: 6px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px;" />
                                            <select class="row-compulsory" style="width: 95%;">
                                                <option value="">-- None --</option>
                                                <?php
                                                foreach ($all_prods as $p) {
                                                    echo '<option value="' . esc_attr($p['id']) . '" ' . selected($compulsory_id, $p['id'], false) . '>' . esc_html($p['name']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td style="padding: 10px;">
                                            <input type="text" class="pool-search" placeholder="Search pool..." style="width: 95%; margin-bottom: 6px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px;" />
                                            <div class="row-random-pool-wrap" style="max-height: 90px; overflow-y: auto; border: 1px solid #ccc; padding: 6px; background: #fff; border-radius: 4px;">
                                                <?php
                                                foreach ($all_prods as $p) {
                                                    $checked = in_array($p['id'], $random_pool) ? 'checked="checked"' : '';
                                                    echo '<label style="display: block; margin-bottom: 4px; font-size: 12px; cursor: pointer; line-height: 1.4;">';
                                                    echo '<input type="checkbox" class="pool-checkbox" value="' . esc_attr($p['id']) . '" ' . $checked . ' style="margin-right: 6px; vertical-align: middle;" /> ';
                                                    echo esc_html($p['name']);
                                                    echo '</label>';
                                                }
                                                ?>
                                            </div>
                                        </td>
                                        <td style="padding: 10px;">
                                            <input type="number" class="row-discount" value="<?php echo esc_attr($discount); ?>" min="0" step="1" style="width: 90%;" />
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <button type="button" class="button remove-row-btn" style="color: #a00; border-color: #a00;">Remove</button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                        <button type="button" class="button button-primary" id="ruut-add-threshold-btn">Add Threshold Level</button>
                        <input type="hidden" name="ruut_offer_thresholds" id="ruut_offer_thresholds" value="<?php echo esc_attr($thresholds_json); ?>" />
                        <p class="description" style="margin-top: 10px;">Configure multiple tier-based thresholds. You can add a compulsory free product, select a pool for the random tester product, and/or apply a flat discount for each quantity level. Benefits stack together. The random tester product will dynamically choose the item with the highest stock level from the pool.</p>
                      </td>
                </tr>
                
                <tr valign="top" style="background:#f0f0f0; padding:10px 15px; font-weight:bold;">
                    <th scope="row" colspan="2">THE RUUT STORY SETTINGS</th>
                </tr>
                <tr valign="top">
                    <th scope="row">Story Title</th>
                    <td>
                        <input type="text" name="ruut_story_title" value="<?php echo esc_attr(get_option('ruut_story_title', 'The Ruut Story')); ?>" class="large-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Story Content</th>
                    <td>
                        <?php 
                        $story_content = get_option('ruut_story_content', 'Inspired by the quiet mornings in Landour, Mussoorie...');
                        wp_editor($story_content, 'ruut_story_content', array('textarea_rows' => 10)); 
                        ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Story Featured Image</th>
                    <td>
                        <input type="text" name="ruut_story_image" id="ruut_story_image" value="<?php echo esc_attr(get_option('ruut_story_image')); ?>" class="regular-text" />
                        <input type="button" id="ruut_story_image_btn" class="button-secondary" value="Upload Image" />
                        <p class="description">Select or upload the featured image for the Ruut Story section.</p>
                        <div id="ruut_story_image_preview" style="margin-top: 10px;">
                            <?php if ($img = get_option('ruut_story_image')): ?>
                                <img src="<?php echo esc_url($img); ?>" style="max-width: 300px; max-height: 200px; display: block; border: 1px solid #ccc; padding: 4px;" />
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <tr valign="top" style="background:#f0f0f0; padding:10px 15px; font-weight:bold;">
                    <th scope="row" colspan="2">DIFFUSER & ESSENTIAL OIL PROMOTION SETTINGS</th>
                </tr>
                <tr valign="top">
                    <th scope="row">Diffuser Oil Promotional Mode</th>
                    <td>
                        <?php $promo_mode = get_option('ruut_diffuser_promo_mode', 'first_purchase_free'); ?>
                        <select name="ruut_diffuser_promo_mode" style="width: 100%; max-width: 500px; padding: 6px;">
                            <option value="first_purchase_free" <?php selected($promo_mode, 'first_purchase_free'); ?>>Option 1: First Purchase Free Essential Oil (100% OFF 1st Oil)</option>
                            <option value="bundle_discount" <?php selected($promo_mode, 'bundle_discount'); ?>>Option 2: Fixed Bundle Discount on Essential Oil MRP (% OFF)</option>
                            <option value="disabled" <?php selected($promo_mode, 'disabled'); ?>>Disabled (No Promotion)</option>
                        </select>
                        <p class="description">Select how essential oil promotional pricing is applied when purchased with a Diffuser.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Bundle Discount Percentage (%)</th>
                    <td>
                        <input type="number" name="ruut_diffuser_bundle_discount_pct" value="<?php echo esc_attr(get_option('ruut_diffuser_bundle_discount_pct', '40')); ?>" min="0" max="100" class="small-text" /> %
                        <p class="description">Applicable when Option 2 (Bundle Discount) is selected. E.g., 40 means 40% OFF Essential Oil MRP.</p>
                    </td>
                </tr>
                
                <tr valign="top" style="background:#f0f0f0; padding:10px 15px; font-weight:bold;">
                    <th scope="row" colspan="2">ONLINE PAYMENT DISCOUNT SETTINGS</th>
                </tr>
                <tr valign="top">
                    <th scope="row">Online Payment Discount Percentage (%)</th>
                    <td>
                        <input type="number" name="ruut_online_payment_discount_pct" value="<?php echo esc_attr(get_option('ruut_online_payment_discount_pct', '0')); ?>" min="0" max="100" class="small-text" /> %
                        <p class="description">Set the percentage discount to offer when a customer selects an online payment method at checkout (i.e. anything other than Cash on Delivery).</p>
                    </td>
                </tr>

                <tr valign="top" style="background:#f0f0f0; padding:10px 15px; font-weight:bold;">
                    <th scope="row" colspan="2">GOOGLE SIGN-IN SETTINGS</th>
                </tr>
                <tr valign="top">
                    <th scope="row">Google API Client ID</th>
                    <td>
                        <input type="text" name="ruut_google_client_id" value="<?php echo esc_attr(get_option('ruut_google_client_id', '')); ?>" class="regular-text" style="width: 100%; max-width: 500px;" />
                        <p class="description">Enter your Google API Client ID from Google Cloud Console (OAuth Client ID). Leave empty to disable Google Login.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Expose available products to JS
        const availableProducts = <?php echo json_encode($all_prods); ?>;
        
        // Remove row click handler
        $('#ruut-thresholds-table').on('click', '.remove-row-btn', function() {
            $(this).closest('tr').remove();
        });
        
        // Add row click handler
        $('#ruut-add-threshold-btn').on('click', function() {
            let optionsHtml = '';
            let checkboxHtml = '';
            availableProducts.forEach(function(p) {
                optionsHtml += '<option value="' + p.id + '">' + p.name + '</option>';
                checkboxHtml += `
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; cursor: pointer; line-height: 1.4;">
                        <input type="checkbox" class="pool-checkbox" value="${p.id}" style="margin-right: 6px; vertical-align: middle;" />
                        ${p.name}
                    </label>
                `;
            });
            let compulsoryOptions = '<option value="">-- None --</option>' + optionsHtml;
            
            const newRow = `
                <tr class="threshold-row">
                    <td style="padding: 10px;">
                        <input type="number" class="row-qty" value="1" min="1" step="1" style="width: 90%;" required />
                    </td>
                    <td style="padding: 10px;">
                        <input type="text" class="compulsory-search" placeholder="Search product..." style="width: 95%; margin-bottom: 6px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px;" />
                        <select class="row-compulsory" style="width: 95%;">
                            ${compulsoryOptions}
                        </select>
                    </td>
                    <td style="padding: 10px;">
                        <input type="text" class="pool-search" placeholder="Search pool..." style="width: 95%; margin-bottom: 6px; padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px;" />
                        <div class="row-random-pool-wrap" style="max-height: 90px; overflow-y: auto; border: 1px solid #ccc; padding: 6px; background: #fff; border-radius: 4px;">
                            ${checkboxHtml}
                        </div>
                    </td>
                    <td style="padding: 10px;">
                        <input type="number" class="row-discount" value="0" min="0" step="1" style="width: 90%;" />
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <button type="button" class="button remove-row-btn" style="color: #a00; border-color: #a00;">Remove</button>
                    </td>
                </tr>
            `;
            $('#ruut-thresholds-table tbody').append(newRow);
        });
        
        // Keyup search handlers for filtering Compulsory Select
        $('#ruut-thresholds-table').on('keyup', '.compulsory-search', function() {
            const query = $(this).val().toLowerCase();
            const select = $(this).siblings('.row-compulsory');
            const currentValue = select.val();
            let html = '<option value="">-- None --</option>';
            availableProducts.forEach(function(p) {
                if (!query || p.name.toLowerCase().indexOf(query) > -1 || p.id == currentValue) {
                    const selectedAttr = (p.id == currentValue) ? 'selected="selected"' : '';
                    html += '<option value="' + p.id + '" ' + selectedAttr + '>' + p.name + '</option>';
                }
            });
            select.html(html);
        });

        // Keyup search handlers for filtering Random Gift Pool checkboxes
        $('#ruut-thresholds-table').on('keyup', '.pool-search', function() {
            const query = $(this).val().toLowerCase();
            const checkboxesDiv = $(this).siblings('.row-random-pool-wrap');
            checkboxesDiv.find('label').each(function() {
                const labelText = $(this).text().toLowerCase();
                if (labelText.indexOf(query) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // On form submit, serialize table to JSON
        $('form').on('submit', function() {
            const thresholds = [];
            $('#ruut-thresholds-table tbody tr.threshold-row').each(function() {
                const qty = parseInt($(this).find('.row-qty').val());
                const compulsory = $(this).find('.row-compulsory').val();
                
                const randomPool = [];
                $(this).find('.pool-checkbox:checked').each(function() {
                    randomPool.push(parseInt($(this).val()));
                });
                
                const discount = parseFloat($(this).find('.row-discount').val()) || 0;
                
                if (!isNaN(qty) && qty > 0) {
                    thresholds.push({
                        quantity: qty,
                        compulsory_gift_id: compulsory,
                        random_gift_pool: randomPool,
                        discount: discount
                    });
                }
            });
            
            // Sort ascending by quantity
            thresholds.sort(function(a, b) {
                return a.quantity - b.quantity;
            });
            
            $('#ruut_offer_thresholds').val(JSON.stringify(thresholds));
        });
    });
    </script>
    <?php
}

// Media Uploader Script for Story Image
add_action('admin_enqueue_scripts', 'ruut_story_image_enqueue');
function ruut_story_image_enqueue($hook) {
    if ($hook !== 'toplevel_page_ruut-settings') {
        return;
    }
    wp_enqueue_media();
    wp_add_inline_script('media-editor', "
        jQuery(document).ready(function($) {
            var mediaUploader;
            $('#ruut_story_image_btn').click(function(e) {
                e.preventDefault();
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media({
                    title: 'Choose Image',
                    button: {
                        text: 'Choose Image'
                    },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#ruut_story_image').val(attachment.url);
                    $('#ruut_story_image_preview').html('<img src=\"' + attachment.url + '\" style=\"max-width:300px; max-height:200px; display:block; border:1px solid #ccc; padding:4px;\" />');
                });
                mediaUploader.open();
            });
        });
    ");
}
