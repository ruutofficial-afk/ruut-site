<?php
/**
 * RUUT - Unified WordPress, WooCommerce Customizations & Headless REST API Handler (Safe Edition)
 * 
 * This file combines:
 * 1. Standard WooCommerce My Account page custom tabs, OTP auth, onboarding flow, and dashboard layout.
 * 2. Headless REST API endpoints (/ruut/v1) for the static landing page's My Account panel.
 * 3. Direct checkout multi-cart synchronizer.
 * 4. Dynamic CORS credentials configuration.
 */

// ===================================================================================
// PART 1: LANDING PAGE DIRECT CHECKOUT & CART SYNC
// ===================================================================================
if (!function_exists('custom_multi_add_to_cart')) {
    add_action('template_redirect', 'custom_multi_add_to_cart');
    function custom_multi_add_to_cart() {
        if ((is_checkout() || is_cart()) && isset($_GET['add-to-cart'])) {
            $product_raw = sanitize_text_field($_GET['add-to-cart']);
            $quantity_raw = isset($_GET['quantity']) ? sanitize_text_field($_GET['quantity']) : '';
            
            $product_ids = explode(',', $product_raw);
            $quantities = explode(',', $quantity_raw);
            
            if (function_exists('WC') && WC()->cart) {
                WC()->cart->empty_cart();
                
                foreach ($product_ids as $index => $product_id) {
                    $product_id = intval(trim($product_id));
                    if ($product_id > 0) {
                        $qty = isset($quantities[$index]) && trim($quantities[$index]) !== '' ? intval(trim($quantities[$index])) : 1;
                        WC()->cart->add_to_cart($product_id, $qty);
                    }
                }
                
                $redirect_url = is_checkout() ? wc_get_checkout_url() : wc_get_cart_url();
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
}

// ===================================================================================
// PART 2: WOOCOMMERCE CHECKOUT OPTIMIZATIONS & CUSTOMIZATIONS
// ===================================================================================

add_filter( 'wc_ship_to_billing_address_only', '__return_true' );

if (!function_exists('ruut_remove_checkout_banners')) {
    add_action('init', 'ruut_remove_checkout_banners');
    function ruut_remove_checkout_banners() {
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
        remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
    }
}

if (!function_exists('ruut_inject_custom_header')) {
    add_action('woocommerce_before_checkout_form', 'ruut_inject_custom_header', 5);
    function ruut_inject_custom_header() {
        echo '<div class="ruut-header-wrapper"><div class="ruut-brand-logo">ṚUUT</div><div class="ruut-page-title">Secure Checkout</div></div>';
    }
}

if (!function_exists('ruut_inject_address_book_data')) {
    add_action('woocommerce_before_checkout_form', 'ruut_inject_address_book_data', 1);
    function ruut_inject_address_book_data() {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
            
            if ( ! empty( $address_book ) && is_array( $address_book ) ) {
                echo '<script>window.ruutAddressBook = ' . json_encode( $address_book ) . ';</script>';
            }
        }
    }
}

if (!function_exists('ruut_clean_error_messages')) {
    add_filter( 'woocommerce_checkout_required_field_notice', 'ruut_clean_error_messages', 10, 2 );
    function ruut_clean_error_messages( $error_message, $field_label ) {
        $clean_label = str_ireplace( array( 'Billing ', 'Shipping ' ), '', $field_label );
        return sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $clean_label ) . '</strong>' );
    }
}

add_filter( 'woocommerce_checkout_cart_item_quantity', '__return_empty_string' );

if (!function_exists('ruut_custom_checkout_item_display')) {
    add_filter( 'woocommerce_cart_item_name', 'ruut_custom_checkout_item_display', 10, 3 );
    function ruut_custom_checkout_item_display( $name, $cart_item, $cart_item_key ) {
        if ( is_checkout() && ! is_wc_endpoint_url() ) {
            $product = $cart_item['data'];
            $thumbnail = $product->get_image(array(90, 90)); 
            
            $vol = $product->get_attribute('pa_volume') ? $product->get_attribute('pa_volume') : $product->get_attribute('volume');
            if (!$vol) {
                $vol = $product->get_attribute('pa_size') ? $product->get_attribute('pa_size') : $product->get_attribute('size');
            }
            
            $meta_display = '';
            if ($vol) {
                $meta_display = '<div class="ruut-item-meta">' . $vol . '</div>';
            }

            $qty = $cart_item['quantity'];
            $qty_display = '<div class="ruut-item-qty">Quantity &nbsp;&nbsp;' . $qty . '</div>';

            return '<div class="ruut-item-wrapper"><div class="ruut-item-img">' . $thumbnail . '</div><div class="ruut-item-details"><div class="ruut-item-name">' . $name . '</div>' . $meta_display . $qty_display . '</div></div>';
        }
        return $name;
    }
}

if (!function_exists('ruut_strict_address_fields')) {
    add_filter( 'woocommerce_default_address_fields', 'ruut_strict_address_fields', 9999 );
    function ruut_strict_address_fields( $fields ) {
        $fields['address_1']['label'] = 'Address'; 
        $fields['address_1']['placeholder'] = '';
        $fields['address_2']['label'] = 'Address Line 2';
        $fields['address_2']['placeholder'] = '';
        $fields['address_2']['required'] = true;
        return $fields;
    }
}

if (!function_exists('ruut_custom_billing_fields')) {
    add_filter('woocommerce_checkout_fields', 'ruut_custom_billing_fields', 9999);
    function ruut_custom_billing_fields($fields) {
        $fields['billing']['billing_email']['priority'] = 10; 
        $fields['billing']['billing_phone']['priority'] = 20; 
        $fields['billing']['billing_first_name']['priority'] = 30;
        $fields['billing']['billing_last_name']['priority'] = 40;
        $fields['billing']['billing_postcode']['priority'] = 50;
        $fields['billing']['billing_city']['priority'] = 60;
        $fields['billing']['billing_state']['priority'] = 70;
        $fields['billing']['billing_address_1']['priority'] = 80;
        $fields['billing']['billing_address_2']['priority'] = 90;

        $fields['billing']['billing_landmark'] = array(
            'type'        => 'text',
            'label'       => 'Landmark (Optional)',
            'placeholder' => '',
            'required'    => false,
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 100,
        );
        
        $fields['billing']['billing_save_as'] = array(
            'type'        => 'text',
            'label'       => 'Save As',
            'placeholder' => 'e.g. Home, Office',
            'required'    => true, 
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 110,
        );

        return $fields;
    }
}

if (!function_exists('ruut_save_checkout_custom_fields')) {
    add_action('woocommerce_checkout_create_order', 'ruut_save_checkout_custom_fields');
    function ruut_save_checkout_custom_fields($order) {
        if (isset($_POST['billing_landmark'])) {
            $order->update_meta_data('_billing_landmark', sanitize_text_field($_POST['billing_landmark']));
        }
        if (isset($_POST['billing_save_as'])) {
            $order->update_meta_data('_billing_save_as', sanitize_text_field($_POST['billing_save_as']));
        }
    }
}

// ===================================================================================
// PART 3: COUPOUNS, RETENTION LOOP, AND LOGS
// ===================================================================================

if (!function_exists('ruut_process_custom_coupon')) {
    add_action('wp_ajax_nopriv_ruut_apply_coupon_custom', 'ruut_process_custom_coupon');
    add_action('wp_ajax_ruut_apply_coupon_custom', 'ruut_process_custom_coupon');
    function ruut_process_custom_coupon() {
        $coupon_code = sanitize_text_field($_POST['coupon']);
        if (empty($coupon_code)) {
            wp_send_json_error(array('message' => 'Please enter a coupon code.', 'cart_url' => false));
        }

        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) {
            wp_send_json_error(array('message' => 'Invalid coupon code.', 'cart_url' => false));
        }

        try {
            $coupon->is_valid(); 
            $applied = WC()->cart->add_discount($coupon_code);
            if ($applied) {
                wp_send_json_success(array('message' => 'Coupon applied successfully.'));
            } else {
                wp_send_json_error(array('message' => 'Coupon could not be applied.', 'cart_url' => false));
            }
        } catch (Exception $e) {
            if ($e->getCode() == 112 || $e->getCode() == 108) {
                $min_spend = $coupon->get_minimum_amount();
                $cart_total = WC()->cart->subtotal;
                if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
                    $cart_total += WC()->cart->subtotal_tax;
                }
                $difference = $min_spend - $cart_total;
                if ($difference > 0) {
                    $formatted_diff = html_entity_decode(wp_strip_all_tags(wc_price($difference)));
                    wp_send_json_error(array(
                        'status' => 'min_spend',
                        'message' => 'Add products worth ' . $formatted_diff . ' to enjoy the benefits.',
                        'cart_url' => wc_get_cart_url()
                    ));
                }
            }
            wp_send_json_error(array(
                'status' => 'general',
                'message' => wp_strip_all_tags($e->getMessage()),
                'cart_url' => wc_get_cart_url()
            ));
        }
    }
}

if (!function_exists('ruut_intercept_checkout_data')) {
    add_filter('woocommerce_checkout_posted_data', 'ruut_intercept_checkout_data', 9999, 1);
    function ruut_intercept_checkout_data( $data ) {
        $log_file = dirname(__FILE__) . '/checkout_debug.log';
        $log_content = "=== CHECKOUT INTERCEPT ===\n";
        $log_content .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $log_content .= "POST: " . print_r($_POST, true) . "\n";
        $log_content .= "DATA BEFORE: " . print_r($data, true) . "\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);

        $data['ship_to_different_address'] = false;

        if ( isset( $_POST['ruut_selected_address'] ) && $_POST['ruut_selected_address'] !== 'new' ) {
            $selected_key = sanitize_text_field( $_POST['ruut_selected_address'] );
            
            if ( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
                
                if ( is_array( $address_book ) && isset( $address_book[ $selected_key ] ) ) {
                    $addr = $address_book[ $selected_key ];
                    
                    $data['billing_address_1'] = !empty($addr['billing_address_1']) ? $addr['billing_address_1'] : (!empty($addr['address_1']) ? $addr['address_1'] : 'Saved Address'); 
                    $data['billing_address_2'] = !empty($addr['billing_address_2']) ? $addr['billing_address_2'] : (!empty($addr['address_2']) ? $addr['address_2'] : 'N/A');
                    $data['billing_city']      = !empty($addr['billing_city']) ? $addr['billing_city'] : (!empty($addr['city']) ? $addr['city'] : 'Saved City');
                    $data['billing_postcode']  = !empty($addr['billing_postcode']) ? $addr['billing_postcode'] : (!empty($addr['postcode']) ? $addr['postcode'] : '000000');
                    $data['billing_state']     = !empty($addr['billing_state']) ? $addr['billing_state'] : (!empty($addr['state']) ? $addr['state'] : '');
                    
                    $data['billing_landmark'] = !empty($addr['billing_landmark']) ? $addr['billing_landmark'] : (!empty($addr['landmark']) ? $addr['landmark'] : '');
                    $data['billing_save_as']  = !empty($addr['billing_save_as']) ? $addr['billing_save_as'] : (!empty($addr['save_as']) ? $addr['save_as'] : 'Saved Address');
                    
                    $_POST['billing_address_1'] = $data['billing_address_1'];
                    $_POST['billing_address_2'] = $data['billing_address_2'];
                    $_POST['billing_city']      = $data['billing_city'];
                    $_POST['billing_postcode']  = $data['billing_postcode'];
                    $_POST['billing_state']     = $data['billing_state'];
                    $_POST['billing_landmark']  = $data['billing_landmark'];
                    $_POST['billing_save_as']   = $data['billing_save_as'];
                }
            }
        }

        $data['billing_country'] = !empty($data['billing_country']) ? $data['billing_country'] : 'IN';
        $_POST['billing_country'] = !empty($_POST['billing_country']) ? $_POST['billing_country'] : 'IN';

        $billing_fields = array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'state', 'phone', 'email');
        foreach ($billing_fields as $field) {
            $key = 'billing_' . $field;
            if (isset($data[$key]) && (empty($_POST[$key]) || !isset($_POST[$key]))) {
                $_POST[$key] = $data[$key];
            }
        }

        $data['shipping_first_name'] = !empty($data['billing_first_name']) ? $data['billing_first_name'] : '';
        $data['shipping_last_name']  = !empty($data['billing_last_name']) ? $data['billing_last_name'] : '';
        $data['shipping_company']    = !empty($data['billing_company']) ? $data['billing_company'] : '';
        $data['shipping_address_1']  = !empty($data['billing_address_1']) ? $data['billing_address_1'] : '';
        $data['shipping_address_2']  = !empty($data['billing_address_2']) ? $data['billing_address_2'] : '';
        $data['shipping_city']       = !empty($data['billing_city']) ? $data['billing_city'] : '';
        $data['shipping_postcode']   = !empty($data['billing_postcode']) ? $data['billing_postcode'] : '';
        $data['shipping_state']      = !empty($data['billing_state']) ? $data['billing_state'] : '';
        $data['shipping_country']    = !empty($data['billing_country']) ? $data['billing_country'] : 'IN';

        $_POST['shipping_first_name'] = $data['shipping_first_name'];
        $_POST['shipping_last_name']  = $data['shipping_last_name'];
        $_POST['shipping_company']    = $data['shipping_company'];
        $_POST['shipping_address_1']  = $data['shipping_address_1'];
        $_POST['shipping_address_2']  = $data['shipping_address_2'];
        $_POST['shipping_city']       = $data['shipping_city'];
        $_POST['shipping_postcode']   = $data['shipping_postcode'];
        $_POST['shipping_state']      = $data['shipping_state'];
        $_POST['shipping_country']    = $data['shipping_country'];
        
        $log_content = "DATA AFTER: " . print_r($data, true) . "\n-----------------------\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);
        
        return $data;
    }
}

if (!function_exists('ruut_save_new_address_to_vault')) {
    add_action('woocommerce_checkout_update_user_meta', 'ruut_save_new_address_to_vault', 10, 2);
    function ruut_save_new_address_to_vault( $customer_id, $posted ) {
        if ( isset( $_POST['ruut_selected_address'] ) && $_POST['ruut_selected_address'] === 'new' ) {
            $address_book = get_user_meta( $customer_id, 'ruut_address_book', true );
            if ( ! is_array( $address_book ) ) {
                $address_book = array();
            }
            
            $new_address = array(
                'id'                => uniqid(),
                'title'             => isset($_POST['billing_save_as']) ? sanitize_text_field($_POST['billing_save_as']) : 'Saved Address',
                'first_name'        => isset($posted['billing_first_name']) ? $posted['billing_first_name'] : '',
                'last_name'         => isset($posted['billing_last_name']) ? $posted['billing_last_name'] : '',
                'phone'             => isset($posted['billing_phone']) ? $posted['billing_phone'] : '',
                'postcode'          => isset($posted['billing_postcode']) ? $posted['billing_postcode'] : '',
                'city'              => isset($posted['billing_city']) ? $posted['billing_city'] : '',
                'state'             => isset($posted['billing_state']) ? $posted['billing_state'] : '',
                'address_1'         => isset($posted['billing_address_1']) ? $posted['billing_address_1'] : '',
                'address_2'         => isset($posted['billing_address_2']) ? $posted['billing_address_2'] : '',
                'landmark'          => isset($_POST['billing_landmark']) ? sanitize_text_field($_POST['billing_landmark']) : '',
                
                // Keep headless legacy keys for safety
                'billing_address_1' => isset($posted['billing_address_1']) ? $posted['billing_address_1'] : '',
                'billing_address_2' => isset($posted['billing_address_2']) ? $posted['billing_address_2'] : '',
                'billing_city'      => isset($posted['billing_city']) ? $posted['billing_city'] : '',
                'billing_postcode'  => isset($posted['billing_postcode']) ? $posted['billing_postcode'] : '',
                'billing_state'     => isset($posted['billing_state']) ? $posted['billing_state'] : '',
                'billing_landmark'  => isset($_POST['billing_landmark']) ? sanitize_text_field($_POST['billing_landmark']) : '',
                'billing_save_as'   => isset($_POST['billing_save_as']) ? sanitize_text_field($_POST['billing_save_as']) : 'Saved Address',
                'is_default'        => empty($address_book) ? true : false 
            );
            
            if ( !empty($new_address['address_1']) && !empty($new_address['city']) ) {
                $address_book[] = $new_address;
                update_user_meta( $customer_id, 'ruut_address_book', $address_book );
            }
        }
    }
}

if (!function_exists('ruut_log_validation_errors')) {
    add_action('woocommerce_after_checkout_validation', 'ruut_log_validation_errors', 9999, 2);
    function ruut_log_validation_errors( $data, $errors ) {
        $log_file = dirname(__FILE__) . '/checkout_debug.log';
        $log_content = "=== VALIDATION ERRORS ===\n";
        $log_content .= "Errors: " . print_r($errors->get_error_messages(), true) . "\n-----------------------\n";
        file_put_contents($log_file, $log_content, FILE_APPEND);
    }
}

add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );

// ===================================================================================
// PART 4: STANDARD WOOCOMMERCE MY ACCOUNT STYLING & GUEST OTP
// ===================================================================================

if (!function_exists('ruut_change_login_title')) {
    add_filter( 'woocommerce_endpoint_customer-logout_title', 'ruut_change_login_title' );
    function ruut_change_login_title( $title ) {
        return 'Login / Register';
    }
}

if (!function_exists('ruut_rename_username_label')) {
    add_filter('gettext', 'ruut_rename_username_label', 10, 3);
    function ruut_rename_username_label($translated_text, $text, $domain) {
        if ($domain === 'woocommerce' && is_account_page() && !is_user_logged_in()) {
            if ($text === 'Username or email address') {
                return 'Email address';
            }
        }
        return $translated_text;
    }
}

if (!function_exists('ruut_account_custom_css')) {
    add_action('wp_head', 'ruut_account_custom_css');
    function ruut_account_custom_css() {
        if ( is_account_page() ) {
            ?>
            <style>
                body.woocommerce-account:not(.logged-in) .woocommerce:has(.woocommerce-form-login) {
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: center !important;
                    align-items: center !important;
                    min-height: calc(100vh - 100px) !important;
                }
                .woocommerce-form-login {
                    width: 100% !important;
                    max-width: 400px !important;
                    margin: 0 auto !important;
                    position: relative;
                }
                .woocommerce-form-login::after {
                    content: "";
                    display: block;
                    height: 160px;
                    transition: height 0.3s ease;
                }
                .woocommerce-form-login.otp-active::after {
                    height: 0px !important;
                    display: none;
                }
                body.woocommerce-account.logged-in .woocommerce:has(.woocommerce-MyAccount-navigation) {
                    gap: 40px !important;
                }
                .woocommerce-MyAccount-content {
                    max-width: 900px;
                }
            </style>
            <?php
        }
    }
}

if (!function_exists('ruut_custom_login_form_otp')) {
    add_action( 'woocommerce_login_form_end', 'ruut_custom_login_form_otp' );
    function ruut_custom_login_form_otp() {
        ?>
        <button type="button" class="button" id="send_otp_btn">Get OTP</button>
        <div id="otp_container" style="display:none;">
            <p class="form-row form-row-wide">
                <label for="otp_full"><?php esc_html_e( 'Enter OTP', 'ruut' ); ?></label>
                <div class="otp-input-group">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]*" inputmode="numeric">
                </div>
                <input type="hidden" name="otp" id="otp_full" />
            </p>
            <button type="button" class="button" id="verify_otp_btn">Verify OTP</button>
            <div id="resend_otp_link" class="disabled">Resend OTP in <span id="timer">30</span>s</div>
        </div>
        <div id="otp_message" class="otp-status-message" style="display:none;"></div>
        <?php
    }
}

if (!function_exists('ruut_handle_otp_request')) {
    add_action( 'wp_ajax_nopriv_ruut_request_otp', 'ruut_handle_otp_request' );
    add_action( 'wp_ajax_ruut_request_otp', 'ruut_handle_otp_request' );
    function ruut_handle_otp_request() {
        check_ajax_referer('ruut_otp_nonce', 'security');
        $email = isset($_POST['identifier']) ? sanitize_email($_POST['identifier']) : '';
        if ( ! is_email($email) ) {
            wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
        }

        $otp = wp_rand( 100000, 999999 );
        set_transient( 'ruut_otp_' . md5($email), $otp, 600 );

        $subject = "{$otp} - Your Exclusive Access Code - ṚUUT"; 
        $message = "
        <html>
        <head>
            <style>
                body { font-family: 'Georgia', serif; color: #4A3324; background-color: #E6DED1; padding: 40px; }
                .container { background-color: #FFFFFF; padding: 40px; max-width: 500px; margin: 0 auto; border: 1px solid #D1C7B7; text-align: center; }
                h2 { color: #4A3324; font-size: 24px; font-weight: normal; margin-bottom: 20px; }
                p { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #664436; margin-bottom: 20px; }
                .otp-code { display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #895158; margin: 20px 0; padding: 15px 30px; background-color: #EADBC8; border: 1px solid #4A3324; }
                .footer { margin-top: 40px; font-style: italic; color: #4A3324; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Welcome to ṚUUT</h2>
                <p>We are delighted to have you here. Step into a world of curated elegance and natural harmony. Please use the secure access code below to continue your journey with us.</p>
                <div class='otp-code'>{$otp}</div>
                <p>This code is valid for the next 10 minutes. For your security, please do not share it with anyone.</p>
                <div class='footer'>Warm regards,<br><strong>The ṚUUT Team</strong></div>
            </div>
        </body>
        </html>";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $mail_sent = wp_mail( $email, $subject, $message, $headers );

        if ($mail_sent) {
            wp_send_json_success( array( 'message' => 'OTP sent! Please check your inbox (and spam folder).' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to send OTP. Please try again.' ) );
        }
    }
}

if (!function_exists('ruut_authenticate_with_otp')) {
    add_filter( 'authenticate', 'ruut_authenticate_with_otp', 30, 3 );
    function ruut_authenticate_with_otp( $user, $username, $password ) {
        if ( isset( $_POST['otp'] ) && ! empty( $_POST['otp'] ) ) {
            $email = isset( $_POST['username'] ) ? sanitize_email( $_POST['username'] ) : '';
            $submitted_otp = sanitize_text_field( $_POST['otp'] );
            $stored_otp = get_transient( 'ruut_otp_' . md5($email) );

            if ( $stored_otp && $stored_otp == $submitted_otp ) {
                delete_transient( 'ruut_otp_' . md5($email) );
                $auth_user = get_user_by( 'email', $email );

                if ( $auth_user ) {
                    return $auth_user;
                } else {
                    $password = wp_generate_password();
                    $new_user_id = wp_create_user( $email, $password, $email );
                    if ( is_wp_error( $new_user_id ) ) {
                        return new WP_Error( 'registration_failed', __( 'Error creating account.', 'woocommerce' ) );
                    }
                    $new_user = new WP_User( $new_user_id );
                    $new_user->set_role( 'customer' );
                    update_user_meta( $new_user_id, '_ruut_needs_onboarding', 'yes' );
                    return $new_user;
                }
            } else {
                return new WP_Error( 'invalid_otp', __( 'The OTP entered is invalid or has expired.', 'woocommerce' ) );
            }
        }
        return $user;
    }
}

if (!function_exists('ruut_enqueue_my_account_scripts')) {
    add_action( 'wp_enqueue_scripts', 'ruut_enqueue_my_account_scripts' );
    function ruut_enqueue_my_account_scripts() {
        if ( is_account_page() ) {
            if ( ! is_user_logged_in() ) {
                wp_enqueue_script( 'ruut-login-otp', get_stylesheet_directory_uri() . '/js/login-otp.js', array('jquery'), '1.1', true );
                wp_localize_script( 'ruut-login-otp', 'ruut_ajax', array(
                    'url'   => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'ruut_otp_nonce' )
                ) );
            }
            wp_enqueue_script( 'ruut-account-scripts', get_stylesheet_directory_uri() . '/js/account-scripts.js', array('jquery'), '1.1', true );
            wp_localize_script( 'ruut-account-scripts', 'ruut_account_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' )
            ));
        }
    }
}

// ===================================================================================
// PART 5: WOOCOMMERCE ACCOUNT ONBOARDING OVERLAY & USER DETAILS
// ===================================================================================

if (!function_exists('ruut_render_onboarding_overlay')) {
    add_action( 'wp_footer', 'ruut_render_onboarding_overlay' );
    function ruut_render_onboarding_overlay() {
        if ( is_account_page() && is_user_logged_in() ) {
            if ( get_user_meta( get_current_user_id(), '_ruut_needs_onboarding', true ) === 'yes' ) {
                ?>
                <style>
                    body { overflow: hidden !important; }
                    .ruut-fullscreen-overlay {
                        position: fixed;
                        top: 0; left: 0; right: 0; bottom: 0;
                        background-color: var(--ruut-bg, #E6DED1);
                        z-index: 99999999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        overflow-y: auto;
                        padding: 40px 20px;
                    }
                    .ruut-fullscreen-overlay * { box-sizing: border-box; }
                    .ruut-onboarding-wrapper { width: 100%; max-width: 1000px; margin: auto; }
                    .ruut-onboarding-maintitle { grid-column: 1 / -1; text-align: center; font-family: var(--ruut-font-serif, 'Georgia', serif); font-size: 2rem; color: var(--ruut-coffee, #4A3324); margin: 0 0 40px 0; font-weight: normal; }
                    .ruut-onboarding-form { display: grid; grid-template-columns: 1fr 1fr; column-gap: 80px; align-items: start; }
                    .ruut-onboarding-col { display: flex; flex-direction: column; gap: 30px; }
                    .ruut-fullscreen-overlay label { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--ruut-coffee, #4A3324); margin-bottom: 8px; font-weight: 600; text-align: left; }
                    .ruut-form-row { width: 100%; }
                    .ruut-form-columns { display: flex; justify-content: space-between; gap: 30px; width: 100%; }
                    .ruut-form-col { flex: 1; }
                    .ruut-fullscreen-overlay input[type="text"], .ruut-fullscreen-overlay select.ruut-state-select {
                        width: 100% !important; padding: 10px 0 !important; border: none !important; border-bottom: 1px solid var(--ruut-coffee, #4A3324) !important; background-color: transparent !important; color: var(--ruut-coffee, #4A3324) !important; font-size: 0.95rem !important; box-shadow: none !important; border-radius: 0 !important; font-family: var(--ruut-font-sans, 'Helvetica Neue', Helvetica, Arial, sans-serif);
                    }
                    .ruut-fullscreen-overlay input[type="text"]:focus, .ruut-fullscreen-overlay select.ruut-state-select:focus { outline: none !important; border-bottom: 2px solid var(--ruut-coffee, #4A3324) !important; }
                    .ruut-fullscreen-overlay select.ruut-state-select { appearance: none; -webkit-appearance: none; -moz-appearance: none; cursor: pointer; }
                    .ruut-onboarding-submit-wrapper { grid-column: 1 / -1; display: flex; justify-content: center; margin-top: 40px; }
                    .ruut-fullscreen-overlay .ruut-onboarding-submit { background-color: var(--ruut-burnt-rose, #895158) !important; color: #ffffff !important; border: none !important; width: 100%; max-width: 400px; padding: 16px !important; font-size: 0.9rem; cursor: pointer; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; transition: all 0.3s ease; border-radius: 8px !important; }
                    .ruut-fullscreen-overlay .ruut-onboarding-submit:hover { background-color: var(--ruut-coffee, #4A3324) !important; }
                    @media (max-width: 768px) {
                        .ruut-onboarding-form { display: flex !important; flex-direction: column !important; row-gap: 30px; }
                        .ruut-onboarding-col { display: contents !important; }
                        .ruut-onboarding-maintitle { order: 1; }
                        .ruut-col-group-name { order: 2; }
                        .ruut-col-group-phone { order: 3; }
                        .ruut-col-group-saveas { order: 4; }
                        .ruut-col-group-address { order: 5; }
                        .ruut-col-group-landmark { order: 6; }
                        .ruut-col-group-pincity { order: 7; }
                        .ruut-col-group-state { order: 8; }
                        .ruut-onboarding-submit-wrapper { order: 9; }
                        .ruut-form-columns { flex-direction: column !important; gap: 30px !important; }
                        .ruut-onboarding-maintitle { font-size: 1.6rem; margin-bottom: 20px; }
                    }
                </style>
                <div class="ruut-fullscreen-overlay">
                    <div class="ruut-onboarding-wrapper">
                        <form method="post" action="" class="ruut-onboarding-form">
                            <?php wp_nonce_field( 'ruut_save_onboarding_action', 'ruut_onboarding_nonce' ); ?>
                            <input type="hidden" name="ruut_save_onboarding" value="1" />
                            
                            <h3 class="ruut-onboarding-maintitle">Personal Details</h3>
                            <div class="ruut-onboarding-col">
                                <div class="ruut-form-columns ruut-col-group-name">
                                    <div class="ruut-form-col">
                                        <label>First Name <span style="color: var(--ruut-accent);">*</span></label>
                                        <input type="text" name="billing_first_name" required />
                                    </div>
                                    <div class="ruut-form-col">
                                        <label>Last Name <span style="color: var(--ruut-accent);">*</span></label>
                                        <input type="text" name="billing_last_name" required />
                                    </div>
                                </div>
                                <div class="ruut-form-row ruut-col-group-phone">
                                    <label>Phone <span style="color: var(--ruut-accent);">*</span></label>
                                    <input type="text" name="billing_phone" required />
                                </div>
                                <div class="ruut-form-columns ruut-col-group-pincity">
                                    <div class="ruut-form-col">
                                        <label>Pin Code</label>
                                        <input type="text" name="shipping_postcode" />
                                    </div>
                                    <div class="ruut-form-col">
                                        <label>Town / City</label>
                                        <input type="text" name="shipping_city" />
                                    </div>
                                </div>
                                <div class="ruut-form-row ruut-col-group-state">
                                    <label>State</label>
                                    <select name="shipping_state" class="ruut-state-select">
                                        <option value="">Select...</option>
                                        <?php
                                            $states = WC()->countries->get_states( 'IN' );
                                            if ( ! empty( $states ) ) {
                                                foreach( $states as $code => $name ) {
                                                    echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="ruut-onboarding-col">
                                <div class="ruut-form-row ruut-col-group-saveas">
                                    <label>Save As (e.g. Home, Office)</label>
                                    <input type="text" name="shipping_title" placeholder="Home" />
                                </div>
                                <div class="ruut-form-row ruut-col-group-address">
                                    <label>Address</label>
                                    <input type="text" name="shipping_address_1" />
                                    <input type="text" name="shipping_address_2" style="margin-top: 15px;" />
                                </div>
                                <div class="ruut-form-row ruut-col-group-landmark">
                                    <label>Landmark</label>
                                    <input type="text" name="shipping_landmark" />
                                </div>
                            </div>
                            <div class="ruut-onboarding-submit-wrapper">
                                <button type="submit" class="button ruut-onboarding-submit">Complete Registration</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
            }
        }
    }
}

if (!function_exists('ruut_save_onboarding_data')) {
    add_action( 'template_redirect', 'ruut_save_onboarding_data' );
    function ruut_save_onboarding_data() {
        if ( is_account_page() && isset( $_POST['ruut_save_onboarding'] ) && isset($_POST['ruut_onboarding_nonce']) ) {
            if ( ! wp_verify_nonce( $_POST['ruut_onboarding_nonce'], 'ruut_save_onboarding_action' ) ) {
                return;
            }

            $user_id = get_current_user_id();
            $first_name = sanitize_text_field( $_POST['billing_first_name'] );
            $last_name  = sanitize_text_field( $_POST['billing_last_name'] );
            $phone      = sanitize_text_field( $_POST['billing_phone'] );
            
            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => trim( $first_name . ' ' . $last_name ),
                'first_name'   => $first_name,
                'last_name'    => $last_name,
            ) );
            
            update_user_meta( $user_id, 'billing_first_name', $first_name );
            update_user_meta( $user_id, 'billing_last_name', $last_name );
            update_user_meta( $user_id, 'billing_phone', $phone );

            $address_title = isset($_POST['shipping_title']) && !empty($_POST['shipping_title']) ? sanitize_text_field($_POST['shipping_title']) : 'Home';
            $address_id = uniqid();

            $new_address = array(
                'id'                => $address_id,
                'title'             => $address_title,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'phone'             => $phone,
                'postcode'          => sanitize_text_field( $_POST['shipping_postcode'] ),
                'city'              => sanitize_text_field( $_POST['shipping_city'] ),
                'state'             => sanitize_text_field( $_POST['shipping_state'] ),
                'address_1'         => sanitize_text_field( $_POST['shipping_address_1'] ),
                'address_2'         => sanitize_text_field( $_POST['shipping_address_2'] ),
                'landmark'          => sanitize_text_field( $_POST['shipping_landmark'] ),
                
                // Headless legacy fields
                'billing_address_1' => sanitize_text_field( $_POST['shipping_address_1'] ),
                'billing_address_2' => sanitize_text_field( $_POST['shipping_address_2'] ),
                'billing_city'      => sanitize_text_field( $_POST['shipping_city'] ),
                'billing_postcode'  => sanitize_text_field( $_POST['shipping_postcode'] ),
                'billing_state'     => sanitize_text_field( $_POST['shipping_state'] ),
                'billing_landmark'  => sanitize_text_field( $_POST['shipping_landmark'] ),
                'billing_save_as'   => $address_title,
                'is_default'        => true
            );
            
            update_user_meta( $user_id, 'ruut_address_book', array( $new_address ) );
            delete_user_meta( $user_id, '_ruut_needs_onboarding' );

            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    }
}

if (!function_exists('ruut_remove_my_account_menu_links')) {
    add_filter( 'woocommerce_account_menu_items', 'ruut_remove_my_account_menu_links' );
    function ruut_remove_my_account_menu_links( $menu_links ) {
        if ( isset( $menu_links['downloads'] ) ) unset( $menu_links['downloads'] );
        if ( isset( $menu_links['edit-address'] ) ) unset( $menu_links['edit-address'] );
        return $menu_links;
    }
}

if (!function_exists('ruut_custom_dashboard_layout')) {
    add_action( 'woocommerce_account_dashboard', 'ruut_custom_dashboard_layout' );
    function ruut_custom_dashboard_layout() {
        $current_user = wp_get_current_user();
        $first_name = $current_user->user_firstname ? $current_user->user_firstname : $current_user->display_name;
        $orders = wc_get_orders( array(
            'customer_id' => $current_user->ID,
            'limit'       => 1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );
        ?>
        <div class="ruut-custom-dashboard">
            <div class="ruut-dashboard-welcome">
                <h2>Welcome to the ṚUUT family, <?php echo esc_html( $first_name ); ?>.</h2>
                <p>We're honored to have you here. Your journey into curated elegance and natural harmony begins now.</p>
            </div>
            <?php if ( $orders ) : $order = $orders[0]; ?>
                <div class="ruut-recent-order-card">
                    <div class="ruut-order-header">
                        <h3>Recent Order</h3>
                        <a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">View</a>
                    </div>
                    <div class="ruut-order-images">
                        <?php 
                        $items = $order->get_items();
                        $count = 0;
                        foreach ( $items as $item ) {
                            if ( $count >= 3 ) break;
                            $product = $item->get_product();
                            if ( $product ) {
                                echo $product->get_image( 'thumbnail' );
                            }
                            $count++;
                        }
                        if ( count($items) > 3 ) {
                            echo '<span class="ruut-more-items">+' . (count($items) - 3) . '</span>';
                        }
                        ?>
                    </div>
                    <div class="ruut-order-footer">
                        <span class="ruut-order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                        <span class="ruut-order-total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                    </div>
                </div>
            <?php else : ?>
                <div class="ruut-recent-order-card empty">
                    <div class="ruut-order-header">
                        <h3>Recent Order</h3>
                    </div>
                    <p>Your curated collection awaits. You haven't placed any orders yet.</p>
                    <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button">Explore Shop</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('ruut_custom_orders_override')) {
    add_action( 'init', 'ruut_custom_orders_override' );
    function ruut_custom_orders_override() {
        remove_action( 'woocommerce_account_orders_endpoint', 'woocommerce_account_orders' );
        add_action( 'woocommerce_account_orders_endpoint', 'ruut_custom_account_orders' );
    }
}

if (!function_exists('ruut_custom_account_orders')) {
    function ruut_custom_account_orders() {
        $current_user_id = get_current_user_id();
        $four_years_ago = date('Y-m-d', strtotime('-4 years'));
        $orders = wc_get_orders( array(
            'customer_id'  => $current_user_id,
            'date_created' => '>=' . $four_years_ago,
            'limit'        => -1,
            'orderby'      => 'date',
            'order'        => 'DESC',
        ) );
        if ( empty( $orders ) ) {
            ?>
            <div class="ruut-empty-orders">
                <h3>Your Journey Begins Here</h3>
                <p>You haven't curated any rituals with us yet. Discover our collections and find your signature scent.</p>
                <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button">Explore The Collection</a>
            </div>
            <?php
        } else {
            echo '<div class="ruut-orders-grid">';
            foreach ( $orders as $order ) {
                $order_id = $order->get_id();
                $items = $order->get_items();
                $total_items = $order->get_item_count();
                ?>
                <div class="ruut-order-card">
                    <div class="ruut-order-card-header">
                        <span class="order-number">Order #<?php echo esc_html( $order->get_order_number() ); ?></span>
                        <span class="order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
                    </div>
                    <div class="ruut-order-items-preview">
                        <?php 
                        $display_count = 0;
                        foreach ( $items as $item ) {
                            if ( $display_count >= 2 ) {
                                echo '<div class="ruut-more-items-text">+' . esc_html( $total_items - 2 ) . ' more item(s)</div>';
                                break;
                            }
                            $product = $item->get_product();
                            ?>
                            <div class="ruut-item-preview">
                                <?php echo $product ? $product->get_image( 'thumbnail' ) : ''; ?>
                                <div class="item-details">
                                    <span class="item-name"><?php echo esc_html( $item->get_name() ); ?></span>
                                    <span class="item-qty">Qty: <?php echo esc_html( $item->get_quantity() ); ?></span>
                                </div>
                            </div>
                            <?php
                            $display_count++;
                        }
                        ?>
                    </div>
                    <div class="ruut-order-summary">
                        <p>Total Items: <?php echo esc_html( $total_items ); ?></p>
                        <p class="total-price"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
                    </div>
                    <div class="ruut-order-card-footer">
                        <button type="button" class="ruut-show-details-btn" onclick="document.getElementById('ruut-modal-<?php echo esc_attr( $order_id ); ?>').classList.add('active');">Show Details</button>
                    </div>
                </div>
                <div id="ruut-modal-<?php echo esc_attr( $order_id ); ?>" class="ruut-modal">
                    <div class="ruut-modal-content">
                        <span class="ruut-modal-close" onclick="document.getElementById('ruut-modal-<?php echo esc_attr( $order_id ); ?>').classList.remove('active');">&times;</span>
                        <h3>Order #<?php echo esc_html( $order->get_order_number() ); ?> Details</h3>
                        <div class="ruut-modal-details-grid">
                            <div class="ruut-modal-section">
                                <h4>Order Date</h4>
                                <p><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></p>
                            </div>
                            <div class="ruut-modal-section">
                                <h4>Items</h4>
                                <?php foreach ( $items as $item ) : ?>
                                    <div class="ruut-modal-item-row">
                                        <span><?php echo esc_html( $item->get_name() ); ?> x <?php echo esc_html( $item->get_quantity() ); ?></span>
                                        <span><?php echo wp_kses_post( wc_price( $order->get_line_total( $item, true, true ) ) ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ruut-modal-totals">
                                <?php foreach ( $order->get_order_item_totals() as $key => $total ) : ?>
                                    <div class="ruut-modal-totals-row <?php echo ( 'order_total' === $key ) ? 'grand-total' : ''; ?>">
                                        <span><?php echo esc_html( $total['label'] ); ?></span>
                                        <span><?php echo wp_kses_post( $total['value'] ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
            echo '</div>';
        }
    }
}

// ===================================================================================
// PART 6: STANDARD ADDRESS BOOK COMPONENT & ACTIONS
// ===================================================================================

if (!function_exists('ruut_add_phone_to_account_details')) {
    add_action( 'woocommerce_edit_account_form', 'ruut_add_phone_to_account_details', 5 );
    function ruut_add_phone_to_account_details() {
        $user_id = get_current_user_id();
        $phone   = get_user_meta( $user_id, 'billing_phone', true );
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_phone">Phone Number <span style="color: var(--ruut-accent);">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_phone" id="account_phone" value="<?php echo esc_attr( $phone ); ?>" required />
        </p>
        <?php
    }
}

if (!function_exists('ruut_save_account_phone')) {
    add_action( 'woocommerce_save_account_details', 'ruut_save_account_phone', 10, 1 );
    function ruut_save_account_phone( $user_id ) {
        if ( isset( $_POST['account_phone'] ) ) {
            update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['account_phone'] ) );
        }
    }
}

if (!function_exists('ruut_render_address_book_ui')) {
    add_action( 'woocommerce_edit_account_form', 'ruut_render_address_book_ui', 10 );
    function ruut_render_address_book_ui() {
        $user_id = get_current_user_id();
        $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
        if ( ! is_array( $address_book ) ) $address_book = array();
        
        echo '<div class="ruut-address-book-wrapper">';
        echo '<h3>Saved Addresses</h3>';
        echo '<div class="ruut-address-list" id="ruut-address-list-container">';
        if ( empty( $address_book ) ) {
            echo '<p>You have no saved addresses yet.</p>';
        } else {
            foreach ( $address_book as $address ) {
                $addr_title = isset($address['title']) ? $address['title'] : (isset($address['billing_save_as']) ? $address['billing_save_as'] : 'Saved Address');
                $addr_1 = isset($address['address_1']) ? $address['address_1'] : (isset($address['billing_address_1']) ? $address['billing_address_1'] : '');
                $addr_2 = isset($address['address_2']) ? $address['address_2'] : (isset($address['billing_address_2']) ? $address['billing_address_2'] : '');
                $city = isset($address['city']) ? $address['city'] : (isset($address['billing_city']) ? $address['billing_city'] : '');
                $state = isset($address['state']) ? $address['state'] : (isset($address['billing_state']) ? $address['billing_state'] : '');
                $postcode = isset($address['postcode']) ? $address['postcode'] : (isset($address['billing_postcode']) ? $address['billing_postcode'] : '');
                $landmark = isset($address['landmark']) ? $address['landmark'] : (isset($address['billing_landmark']) ? $address['billing_landmark'] : '');
                $first = isset($address['first_name']) ? $address['first_name'] : '';
                $last = isset($address['last_name']) ? $address['last_name'] : '';
                $phone = isset($address['phone']) ? $address['phone'] : '';
                ?>
                <div class="ruut-address-card" id="address-card-<?php echo esc_attr( $address['id'] ); ?>">
                    <div class="ruut-address-card-header">
                        <h4><?php echo esc_html( $addr_title ); ?></h4>
                        <div class="ruut-address-actions">
                            <a onclick="ruutEditAddress('<?php echo esc_attr( wp_json_encode( $address ) ); ?>')">Edit</a>
                            <a onclick="ruutDeleteAddress('<?php echo esc_attr( $address['id'] ); ?>')">Delete</a>
                        </div>
                    </div>
                    <div class="ruut-address-details">
                        <p><strong><?php echo esc_html( $first . ' ' . $last ); ?></strong> (<?php echo esc_html( $phone ); ?>)</p>
                        <p>
                            <?php echo esc_html( $addr_1 ); ?><br>
                            <?php echo esc_html( $addr_2 ); ?>
                        </p>
                        <?php if ( ! empty( $landmark ) ) echo '<p>Landmark: ' . esc_html( $landmark ) . '</p>'; ?>
                        <p><?php echo esc_html( $city . ', ' . $state . ' ' . $postcode ); ?></p>
                    </div>
                </div>
                <?php
            }
        }
        echo '</div>';
        echo '<button type="button" class="ruut-btn" onclick="ruutOpenAddressModal()">+ Add New Address</button>';
        echo '</div>';
    }
}

if (!function_exists('ruut_address_book_modal_html')) {
    add_action( 'wp_footer', 'ruut_address_book_modal_html' );
    function ruut_address_book_modal_html() {
        if ( ! is_account_page() || ! is_user_logged_in() ) return;
        ?>
        <div id="ruut-address-modal" class="ruut-modal">
            <div class="ruut-modal-content">
                <span class="ruut-modal-close" onclick="ruutCloseAddressModal()">&times;</span>
                <h3 id="ruut-address-modal-title">Add New Address</h3>
                <form id="ruut-address-form" class="ruut-address-modal-form">
                    <input type="hidden" id="ruut_addr_id" name="id" value="" />
                    <div class="form-row">
                        <label>Save As (e.g. Home, Office) <span style="color: var(--ruut-accent);">*</span></label>
                        <input type="text" id="ruut_addr_title" name="title" required />
                    </div>
                    <div class="form-columns">
                        <div>
                            <label>Pin Code <span style="color: var(--ruut-accent);">*</span></label>
                            <input type="text" id="ruut_addr_postcode" name="postcode" required />
                        </div>
                        <div>
                            <label>Town / City <span style="color: var(--ruut-accent);">*</span></label>
                            <input type="text" id="ruut_addr_city" name="city" required />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>State <span style="color: var(--ruut-accent);">*</span></label>
                        <select id="ruut_addr_state" name="state" class="ruut-state-select" required>
                            <option value="">Select...</option>
                            <?php
                                $states = WC()->countries->get_states( 'IN' );
                                if ( ! empty( $states ) ) {
                                    foreach( $states as $code => $name ) {
                                        echo '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
                                    }
                                }
                            ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Address <span style="color: var(--ruut-accent);">*</span></label>
                        <input type="text" id="ruut_addr_1" name="address_1" required />
                        <input type="text" id="ruut_addr_2" name="address_2" style="margin-top: 15px;" required />
                    </div>
                    <div class="form-row">
                        <label>Landmark</label>
                        <input type="text" id="ruut_addr_landmark" name="landmark" />
                    </div>
                    <button type="submit" class="ruut-btn" id="ruut_save_addr_btn" style="width: 100%; margin-top: 15px;">Save Address</button>
                    <div id="ruut-address-feedback"></div>
                </form>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('ruut_ajax_save_address')) {
    add_action( 'wp_ajax_ruut_save_address', 'ruut_ajax_save_address' );
    function ruut_ajax_save_address() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error();

        $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
        if ( ! is_array( $address_book ) ) $address_book = array();

        $address_id = isset( $_POST['id'] ) && ! empty( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : uniqid();
        $first_name = get_user_meta( $user_id, 'billing_first_name', true );
        $last_name  = get_user_meta( $user_id, 'billing_last_name', true );
        $phone      = get_user_meta( $user_id, 'billing_phone', true );

        if(empty($first_name)) {
            $user_info = get_userdata($user_id);
            $first_name = $user_info->first_name;
            $last_name = $user_info->last_name;
        }
        
        $new_address = array(
            'id'                => $address_id,
            'title'             => sanitize_text_field( $_POST['title'] ),
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'phone'             => $phone,
            'postcode'          => sanitize_text_field( $_POST['postcode'] ),
            'city'              => sanitize_text_field( $_POST['city'] ),
            'state'             => sanitize_text_field( $_POST['state'] ),
            'address_1'         => sanitize_text_field( $_POST['address_1'] ),
            'address_2'         => sanitize_text_field( $_POST['address_2'] ),
            'landmark'          => sanitize_text_field( $_POST['landmark'] ),
            
            // Legacy/headless compatibility fields
            'billing_address_1' => sanitize_text_field( $_POST['address_1'] ),
            'billing_address_2' => sanitize_text_field( $_POST['address_2'] ),
            'billing_city'      => sanitize_text_field( $_POST['city'] ),
            'billing_postcode'  => sanitize_text_field( $_POST['postcode'] ),
            'billing_state'     => sanitize_text_field( $_POST['state'] ),
            'billing_landmark'  => sanitize_text_field( $_POST['landmark'] ),
            'billing_save_as'   => sanitize_text_field( $_POST['title'] ),
            'is_default'        => empty($address_book)
        );

        $updated = false;
        foreach ( $address_book as $key => $address ) {
            if ( $address['id'] === $address_id ) {
                $address_book[$key] = $new_address;
                $updated = true;
                break;
            }
        }
        if ( ! $updated ) {
            $address_book[] = $new_address;
        }

        update_user_meta( $user_id, 'ruut_address_book', $address_book );
        wp_send_json_success();
    }
}

if (!function_exists('ruut_ajax_delete_address')) {
    add_action( 'wp_ajax_ruut_delete_address', 'ruut_ajax_delete_address' );
    function ruut_ajax_delete_address() {
        $user_id = get_current_user_id();
        if ( ! $user_id || empty( $_POST['id'] ) ) wp_send_json_error();

        $address_id = sanitize_text_field( $_POST['id'] );
        $address_book = get_user_meta( $user_id, 'ruut_address_book', true );
        
        if ( is_array( $address_book ) ) {
            foreach ( $address_book as $key => $address ) {
                if ( $address['id'] === $address_id ) {
                    unset( $address_book[$key] );
                }
            }
            $address_book = array_values( $address_book );
            update_user_meta( $user_id, 'ruut_address_book', $address_book );
        }
        wp_send_json_success();
    }
}

// ===================================================================================
// PART 7: CORS & ALLOW-CREDENTIALS FOR HEADLESS LANDING PAGE API CALLS
// ===================================================================================

if (!function_exists('ruut_api_cors_init')) {
    add_action('rest_api_init', 'ruut_api_cors_init', 15);
    function ruut_api_cors_init() {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With");
        }
    }
}

if (!function_exists('ruut_rest_api_cors_pre_serve')) {
    add_filter('rest_pre_serve_request', 'ruut_rest_api_cors_pre_serve', 10, 4);
    function ruut_rest_api_cors_pre_serve($served, $result, $request, $server) {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if (isset($_SERVER['HTTP_ORIGIN'])) {
                header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
                header("Access-Control-Allow-Credentials: true");
                header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
                header("Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With");
                status_header(200);
                exit;
            }
        }
        return $served;
    }
}

// ===================================================================================
// PART 8: CUSTOM HEADLESS REST ROUTES FOR PORTAL (/ruut/v1)
// ===================================================================================

if (!function_exists('ruut_register_api_routes')) {
    add_action('rest_api_init', 'ruut_register_api_routes');
    function ruut_register_api_routes() {
        register_rest_route('ruut/v1', '/send-otp', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_send_otp',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/verify-otp', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_verify_otp',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/user', array(
            'methods' => 'GET',
            'callback' => 'ruut_api_get_user',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/user', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_update_user',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/address', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_save_address',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/address', array(
            'methods' => 'DELETE',
            'callback' => 'ruut_api_delete_address',
            'permission_callback' => '__return_true',
        ));
        register_rest_route('ruut/v1', '/logout', array(
            'methods' => 'POST',
            'callback' => 'ruut_api_logout',
            'permission_callback' => '__return_true',
        ));
    }
}

if (!function_exists('ruut_api_send_otp')) {
    function ruut_api_send_otp($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email']);
        if (!is_email($email)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Please enter a valid email.'), 400);
        }
        
        $otp = wp_rand(100000, 999999);
        set_transient('ruut_otp_' . md5($email), $otp, 10 * MINUTE_IN_SECONDS);
        
        $subject = $otp . " is your Ruut Secure Login Code";
        $message = '
        <div style="font-family: \'Times New Roman\', Times, serif; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #E6DED1; color: #5c4338; text-align: center; border: 1px solid rgba(92, 67, 56, 0.15);">
            <h1 style="font-size: 38px; letter-spacing: 12px; text-transform: uppercase; margin-bottom: 30px; font-weight: 400; margin-right: -12px;">ṚUUT</h1>
            <p style="font-family: sans-serif; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">Hello there,</p>
            <p style="font-family: sans-serif; font-size: 16px; line-height: 1.6; margin-bottom: 35px;">Verify your identity to log in to your RUUT panel.<br>Your secure verification code is:</p>
            <div style="background-color: #5c4338; color: #E6DED1; font-size: 36px; font-weight: bold; letter-spacing: 12px; padding: 20px 10px 20px 22px; border-radius: 2px; margin-bottom: 35px; display: inline-block;">' . $otp . '</div>
            <p style="font-family: sans-serif; font-size: 14px; opacity: 0.8; margin-bottom: 40px; color: #5c4338;">This code will expire in 10 minutes.</p>
        </div>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
        return new WP_REST_Response(array('success' => true, 'message' => 'OTP sent successfully! Please check your inbox.'), 200);
    }
}

if (!function_exists('ruut_api_verify_otp')) {
    function ruut_api_verify_otp($request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email']);
        $otp = sanitize_text_field($params['otp']);
        $stored_otp = get_transient('ruut_otp_' . md5($email));
        
        if ($stored_otp && $stored_otp == $otp) {
            delete_transient('ruut_otp_' . md5($email));
            $user = get_user_by('email', $email);
            if (!$user) {
                $username = strstr($email, '@', true) . wp_rand(10, 99);
                while (username_exists($username)) {
                    $username = strstr($email, '@', true) . wp_rand(10, 99);
                }
                $password = wp_generate_password();
                $user_id = wp_create_user($username, $password, $email);
                if (is_wp_error($user_id)) {
                    return new WP_REST_Response(array('success' => false, 'message' => 'Failed to register account: ' . $user_id->get_error_message()), 500);
                }
                $user = get_user_by('id', $user_id);
            }
            
            wp_clear_auth_cookie();
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            
            return new WP_REST_Response(array(
                'success' => true, 
                'message' => 'Welcome to RUUT.',
                'user' => array(
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'first_name' => get_user_meta($user->ID, 'billing_first_name', true),
                    'last_name' => get_user_meta($user->ID, 'billing_last_name', true),
                    'phone' => get_user_meta($user->ID, 'billing_phone', true)
                )
            ), 200);
        } else {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid or expired code. Please try again.'), 400);
        }
    }
}

if (!function_exists('ruut_api_get_user')) {
    function ruut_api_get_user() {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        $address_book = get_user_meta($user_id, 'ruut_address_book', true);
        if (empty($address_book) || !is_array($address_book)) {
            $address_book = array();
        }
        
        $orders = array();
        if (function_exists('wc_get_orders')) {
            $customer_orders = wc_get_orders(array(
                'customer' => $user_id,
                'limit' => 20,
                'orderby' => 'date',
                'order' => 'DESC',
            ));
            
            foreach ($customer_orders as $order) {
                $items = array();
                foreach ($order->get_items() as $item_id => $item) {
                    $product = $item->get_product();
                    $items[] = array(
                        'name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'total' => $item->get_total(),
                        'image' => $product ? wp_get_attachment_url($product->get_image_id()) : ''
                    );
                }
                
                $orders[] = array(
                    'id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'date' => $order->get_date_created()->date('Y-m-d H:i'),
                    'status' => wc_get_order_status_name($order->get_status()),
                    'status_slug' => $order->get_status(),
                    'total' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'items' => $items
                );
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'user' => array(
                'id' => $user_id,
                'email' => $user->user_email,
                'first_name' => get_user_meta($user_id, 'billing_first_name', true),
                'last_name' => get_user_meta($user_id, 'billing_last_name', true),
                'phone' => get_user_meta($user_id, 'billing_phone', true)
            ),
            'address_book' => $address_book,
            'orders' => $orders
        ), 200);
    }
}

if (!function_exists('ruut_api_update_user')) {
    function ruut_api_update_user($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $first_name = sanitize_text_field($params['first_name']);
        $last_name = sanitize_text_field($params['last_name']);
        $phone = sanitize_text_field($params['phone']);
        
        update_user_meta($user_id, 'billing_first_name', $first_name);
        update_user_meta($user_id, 'billing_last_name', $last_name);
        update_user_meta($user_id, 'billing_phone', $phone);
        
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name)
        ));
        
        return new WP_REST_Response(array('success' => true, 'message' => 'Profile updated successfully.'), 200);
    }
}

if (!function_exists('ruut_api_save_address')) {
    function ruut_api_save_address($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $key = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        $address_book = get_user_meta($user_id, 'ruut_address_book', true);
        if (!is_array($address_book)) {
            $address_book = array();
        }
        
        $is_default = isset($params['is_default']) ? (bool)$params['is_default'] : false;
        
        $first_name = get_user_meta($user_id, 'billing_first_name', true);
        $last_name  = get_user_meta( $user_id, 'billing_last_name', true );
        $phone      = get_user_meta( $user_id, 'billing_phone', true );
        
        $new_address = array(
            'id'                => empty($key) ? uniqid() : $key,
            'title'             => sanitize_text_field($params['save_as']),
            'first_name'        => $first_name,
            'last_name'         => $last_name,
            'phone'             => $phone,
            'postcode'          => sanitize_text_field($params['postcode']),
            'city'              => sanitize_text_field($params['city']),
            'state'             => sanitize_text_field($params['state']),
            'address_1'         => sanitize_text_field($params['address_1']),
            'address_2'         => sanitize_text_field($params['address_2']),
            'landmark'          => sanitize_text_field($params['landmark']),
            
            // Legacy / Headless API fallback keys
            'billing_address_1' => sanitize_text_field($params['address_1']),
            'billing_address_2' => sanitize_text_field($params['address_2']),
            'billing_city'      => sanitize_text_field($params['city']),
            'billing_postcode'  => sanitize_text_field($params['postcode']),
            'billing_state'     => sanitize_text_field($params['state']),
            'billing_landmark'  => sanitize_text_field($params['landmark']),
            'billing_save_as'   => sanitize_text_field($params['save_as']),
            'is_default'        => $is_default
        );
        
        if (empty($new_address['address_1']) || empty($new_address['city']) || empty($new_address['postcode'])) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Address, City, and PIN code are required.'), 400);
        }
        
        $updated = false;
        if (!empty($key)) {
            foreach ($address_book as $k => $addr) {
                if (isset($addr['id']) && $addr['id'] === $key) {
                    $address_book[$k] = $new_address;
                    $updated = true;
                } elseif ($k === $key) {
                    $address_book[$k] = $new_address;
                    $updated = true;
                }
            }
        }
        
        if (!$updated) {
            $address_book[] = $new_address;
        }
        
        update_user_meta($user_id, 'ruut_address_book', $address_book);
        return new WP_REST_Response(array('success' => true, 'message' => 'Address saved successfully.', 'address_book' => $address_book), 200);
    }
}

if (!function_exists('ruut_api_delete_address')) {
    function ruut_api_delete_address($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array('success' => false, 'message' => 'User is not logged in.'), 401);
        }
        
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $key = isset($params['key']) ? sanitize_text_field($params['key']) : '';
        
        if (empty($key)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Address key is required.'), 400);
        }
        
        $address_book = get_user_meta($user_id, 'ruut_address_book', true);
        if (is_array($address_book)) {
            $deleted = false;
            foreach ($address_book as $k => $addr) {
                if ((isset($addr['id']) && $addr['id'] === $key) || $k === $key) {
                    unset($address_book[$k]);
                    $deleted = true;
                }
            }
            if ($deleted) {
                $address_book = array_values($address_book);
                update_user_meta($user_id, 'ruut_address_book', $address_book);
                return new WP_REST_Response(array('success' => true, 'message' => 'Address deleted successfully.', 'address_book' => $address_book), 200);
            }
        }
        
        return new WP_REST_Response(array('success' => false, 'message' => 'Address not found.'), 404);
    }
}

if (!function_exists('ruut_api_logout')) {
    function ruut_api_logout() {
        wp_logout();
        return new WP_REST_Response(array('success' => true, 'message' => 'Logged out successfully.'), 200);
    }
}
