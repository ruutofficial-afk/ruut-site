<?php
/**
 * WooCommerce Checkout Optimizations, Customizations, OTP Processing, and Thank-You Page layouts
 * Extracted from ruut-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'wc_ship_to_billing_address_only', '__return_true' );

if (!function_exists('ruut_remove_checkout_banners')) {
    add_action('init', 'ruut_remove_checkout_banners');
    function ruut_remove_checkout_banners() {
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10);
        remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
        remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
    }
}

if (!function_exists('ruut_inject_custom_header')) {
    add_action('woocommerce_before_checkout_form', 'ruut_inject_custom_header', 5);
    function ruut_inject_custom_header() {
        echo '<div class="ruut-header-wrapper"><div class="ruut-brand-logo">ṚUUT</div><div class="ruut-header-underline" style="width: 80px; height: 1px; background: rgba(74, 51, 36, 0.2); margin: 6px auto 10px auto;"></div><div class="ruut-page-title">Secure Checkout</div></div>';
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
        $is_chk = function_exists('is_checkout') && is_checkout();
        $is_ep  = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url();
        if ( $is_chk && ! $is_ep ) {
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
        $fields['address_1']['label'] = 'Full Address'; 
        $fields['address_1']['placeholder'] = 'Flat/House No., Building, Street, Area, Landmark';
        if ( isset( $fields['address_2'] ) ) {
            $fields['address_2']['required'] = false;
        }
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
        
        // Keep address_2 field in checkout — position after address_1
        $fields['billing']['billing_address_2']['priority'] = 85;
        $fields['billing']['billing_address_2']['label'] = 'Address Line 2 (Optional)';
        $fields['billing']['billing_address_2']['placeholder'] = 'Apartment, Suite, Floor, etc.';
        $fields['billing']['billing_address_2']['required'] = false;
        $fields['billing']['billing_address_2']['class'] = array('form-row-wide');

        // Remove shipping address_2 (we mirror billing to shipping in the backend)
        unset($fields['shipping']['shipping_address_2']);

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

if ( ! function_exists( 'ruut_process_send_otp' ) ) {
    add_action('wp_ajax_nopriv_ruut_send_otp', 'ruut_process_send_otp');
    add_action('wp_ajax_ruut_send_otp', 'ruut_process_send_otp');
    function ruut_process_send_otp() {
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }

        // Rate-limit: block duplicate OTP sends within 30 seconds per email
        $cooldown_key = 'ruut_otp_cooldown_' . md5($email);
        if ( get_transient( $cooldown_key ) ) {
            wp_send_json_success(array('message' => 'OTP sent! Please check your inbox (and spam folder).'));
        }

        $otp = wp_rand(100000, 999999);
        set_transient('ruut_otp_' . md5($email), $otp, 10 * MINUTE_IN_SECONDS);
        set_transient( $cooldown_key, true, 30 ); // 30-second cooldown

        $subject = $otp . " is your Ruut Secure Checkout Code";
        
        $message = '
        <div style="font-family: \'Times New Roman\', Times, serif; max-width: 600px; margin: 0 auto; padding: 40px 20px; background-color: #E6DED1; color: #5c4338; text-align: center; border: 1px solid rgba(92, 67, 56, 0.15);">
            <h1 style="font-size: 38px; letter-spacing: 12px; text-transform: uppercase; margin-bottom: 30px; font-weight: 400; margin-right: -12px;">ṚUUT</h1>
            <p style="font-family: sans-serif; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">Hello there,</p>
            <p style="font-family: sans-serif; font-size: 16px; line-height: 1.6; margin-bottom: 35px;">You are just one step away from bringing luxury into your space.<br>Your OTP is:</p>
            <div style="background-color: #5c4338; color: #E6DED1; font-size: 36px; font-weight: bold; letter-spacing: 12px; padding: 20px 10px 20px 22px; border-radius: 2px; margin-bottom: 35px; display: inline-block;">' . $otp . '</div>
            <p style="font-family: sans-serif; font-size: 14px; opacity: 0.8; margin-bottom: 40px; color: #5c4338;">Enter this on the checkout page to continue your journey. This code will expire in 10 minutes.</p>
            <p style="font-family: sans-serif; font-size: 14px; line-height: 1.6; color: #5c4338;">Warm regards,<br><strong>The Ruut Team</strong></p>
        </div>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($email, $subject, $message, $headers);
        wp_send_json_success(array('message' => 'OTP sent! Please check your inbox (and spam folder).'));
    }
}

if ( ! function_exists( 'ruut_process_verify_otp' ) ) {
    add_action('wp_ajax_nopriv_ruut_verify_otp', 'ruut_process_verify_otp');
    add_action('wp_ajax_ruut_verify_otp', 'ruut_process_verify_otp');
    function ruut_process_verify_otp() {
        $email = sanitize_email($_POST['email']);
        $otp = sanitize_text_field($_POST['otp']);

        $user = get_user_by('email', $email);
        if ($user && get_current_user_id() === $user->ID) {
            wp_send_json_success(array('status' => 'existing', 'message' => 'Welcome back! Loading your saved details...'));
        }

        $stored_otp = get_transient('ruut_otp_' . md5($email));

        if ($stored_otp && $stored_otp == $otp) {
            delete_transient('ruut_otp_' . md5($email));
            $user = get_user_by('email', $email);
            if ($user) {
                wp_clear_auth_cookie();
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);
                wp_send_json_success(array('status' => 'existing', 'message' => 'Welcome back! Loading your saved details...'));
            } else {
                wp_send_json_success(array('status' => 'new', 'message' => 'Welcome to the Ruut family! The journey is about to be exciting. Let\'s get your details.'));
            }
        } else {
            wp_send_json_error(array('message' => 'Invalid or expired code. Please try again.'));
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
            
            $editing_id = isset($_POST['ruut_editing_address_id']) ? sanitize_text_field($_POST['ruut_editing_address_id']) : '';
            $target_key = -1;
            
            if ( !empty($editing_id) ) {
                foreach ( $address_book as $k => $addr ) {
                    if ( isset($addr['id']) && ($addr['id'] === $editing_id || $k === intval($editing_id)) ) {
                        $target_key = $k;
                        break;
                    }
                }
            }
            
            $billing_first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : (isset($posted['billing_first_name']) ? $posted['billing_first_name'] : '');
            $billing_last_name  = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : (isset($posted['billing_last_name']) ? $posted['billing_last_name'] : '');
            $billing_phone      = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : (isset($posted['billing_phone']) ? $posted['billing_phone'] : '');
            $billing_postcode   = isset($_POST['billing_postcode']) ? sanitize_text_field($_POST['billing_postcode']) : (isset($posted['billing_postcode']) ? $posted['billing_postcode'] : '');
            $billing_city       = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : (isset($posted['billing_city']) ? $posted['billing_city'] : '');
            $billing_state      = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : (isset($posted['billing_state']) ? $posted['billing_state'] : '');
            $billing_address_1  = isset($_POST['billing_address_1']) ? sanitize_text_field($_POST['billing_address_1']) : (isset($posted['billing_address_1']) ? $posted['billing_address_1'] : '');
            $billing_address_2  = isset($_POST['billing_address_2']) ? sanitize_text_field($_POST['billing_address_2']) : (isset($posted['billing_address_2']) ? $posted['billing_address_2'] : '');
            $billing_landmark   = isset($_POST['billing_landmark']) ? sanitize_text_field($_POST['billing_landmark']) : '';
            $billing_save_as    = isset($_POST['billing_save_as']) ? sanitize_text_field($_POST['billing_save_as']) : 'Saved Address';

            $new_address = array(
                'id'                => ($target_key !== -1) ? $address_book[$target_key]['id'] : uniqid(),
                'title'             => $billing_save_as,
                'first_name'        => $billing_first_name,
                'last_name'         => $billing_last_name,
                'phone'             => $billing_phone,
                'postcode'          => $billing_postcode,
                'city'              => $billing_city,
                'state'             => $billing_state,
                'address_1'         => $billing_address_1,
                'address_2'         => $billing_address_2,
                'landmark'          => $billing_landmark,
                
                // Keep headless legacy keys for safety
                'billing_address_1' => $billing_address_1,
                'billing_address_2' => $billing_address_2,
                'billing_city'      => $billing_city,
                'billing_postcode'  => $billing_postcode,
                'billing_state'     => $billing_state,
                'billing_landmark'  => $billing_landmark,
                'billing_save_as'   => $billing_save_as,
                'is_default'        => ($target_key !== -1) ? $address_book[$target_key]['is_default'] : (empty($address_book) ? true : false) 
            );
            
            if ( !empty($new_address['address_1']) && !empty($new_address['city']) ) {
                if ($target_key !== -1) {
                    $address_book[$target_key] = $new_address;
                } else {
                    $address_book[] = $new_address;
                }
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

// ===================================================================================
// CUSTOM THANK YOU / ORDER RECEIVED PAGE LAYOUTS
// ===================================================================================
if ( ! function_exists( 'ruut_custom_thankyou_text' ) ) {
    add_filter('woocommerce_thankyou_order_received_text', 'ruut_custom_thankyou_text', 10, 2);
    function ruut_custom_thankyou_text( $text, $order ) {
        return '<div class="ruut-thankyou-header" style="text-align: center; padding: 20px; border-bottom: 1px solid rgba(92, 67, 56, 0.15);">
                    <h1 class="ruut-brand-logo" style="font-family: \'Times New Roman\', Times, serif; font-size:42px; margin-bottom:15px; color:#4A3324; font-weight:400; letter-spacing:14px; text-transform:uppercase; margin-right: -14px;">ṚUUT</h1>
                    <h2 class="ruut-thankyou-title" style="font-family: \'Times New Roman\', Times, serif; font-size: 24px; font-weight: 300; color: #4A3324; margin-bottom: 20px; letter-spacing: 1px;">Your Order is Confirmed</h2>
                    <p class="ruut-thankyou-msg" style="font-family: \'Times New Roman\', serif; font-size:18px; font-style:italic; color:#664436; line-height: 1.7; max-width: 650px; margin: 0 auto;">Welcome to the ṚUUT family. We are truly honored to bring a touch of luxury into your space. Your beautiful journey begins here.</p>
                </div>';
    }
}

if ( ! function_exists( 'ruut_thankyou_item_display' ) ) {
    add_filter( 'woocommerce_order_item_name', 'ruut_thankyou_item_display', 10, 3 );
    function ruut_thankyou_item_display( $name, $item, $is_visible ) {
        if ( ! is_admin() && ! wp_doing_ajax() && is_wc_endpoint_url( 'order-received' ) ) {
            $product = $item->get_product();
            if ( $product ) {
                $thumbnail = $product->get_image(array(70, 70)); 
                
                $vol = $product->get_attribute('pa_volume') ? $product->get_attribute('pa_volume') : $product->get_attribute('volume');
                if (!$vol) {
                    $vol = $product->get_attribute('pa_size') ? $product->get_attribute('pa_size') : $product->get_attribute('size');
                }
                
                $meta_display = '';
                if ($vol) {
                    $meta_display = '<div class="ruut-item-meta" style="margin-top:4px; font-family: sans-serif; font-size: 11px; opacity: 0.8; color: #5c4338;">' . $vol . '</div>';
                }
                
                $name = '<div class="ruut-item-wrapper" style="display: flex; align-items: center; gap: 15px;"><div class="ruut-item-img" style="flex-shrink:0;">' . $thumbnail . '</div><div class="ruut-item-details"><div class="ruut-item-name" style="font-weight: 700; color: #5c4338;">' . $name . '</div>' . $meta_display . '</div></div>';
            }
        }
        return $name;
    }
}

if (!function_exists('ruut_custom_thankyou_cta')) {
    add_action('woocommerce_thankyou', 'ruut_custom_thankyou_cta', 20);
    function ruut_custom_thankyou_cta($order_id) {
        $home_url = home_url('/');
        echo '<div class="ruut-thankyou-cta-wrapper" style="text-align: center; margin-top: 50px; width: 100%; clear: both;">
                <a href="' . esc_url($home_url) . '" class="button ruut-thankyou-cta-btn" style="background-color: #4A3324 !important; color: #E6DED1 !important; padding: 18px 45px !important; font-family: sans-serif !important; font-size: 13px !important; font-weight: 600 !important; letter-spacing: 2px !important; text-transform: uppercase !important; border-radius: 4px !important; display: inline-block !important; text-decoration: none !important; transition: all 0.3s ease !important; border: 1px solid #4A3324 !important;">Continue Shopping</a>
              </div>';
    }
}

add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );

// Apply online payment discount to WooCommerce cart
add_action( 'woocommerce_cart_calculate_fees', 'ruut_apply_online_payment_discount', 25, 1 );
function ruut_apply_online_payment_discount( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $discount_pct = floatval( get_option( 'ruut_online_payment_discount_pct', 0 ) );
    if ( $discount_pct <= 0 || $discount_pct > 100 ) {
        return;
    }

    // Get the chosen payment method
    $chosen_payment = '';
    if ( isset( WC()->session ) ) {
        $chosen_payment = WC()->session->get( 'chosen_payment_method' );
    }

    if ( empty( $chosen_payment ) && isset( $_POST['payment_method'] ) ) {
        $chosen_payment = sanitize_key( $_POST['payment_method'] );
    }

    if ( empty( $chosen_payment ) && isset( $_POST['post_data'] ) ) {
        parse_str( $_POST['post_data'], $post_data );
        if ( isset( $post_data['payment_method'] ) ) {
            $chosen_payment = sanitize_key( $post_data['payment_method'] );
        }
    }

    if ( empty( $chosen_payment ) ) {
        // Fallback: If chosen payment method is not set at checkout, check default payment gateway
        if ( function_exists('WC') && isset(WC()->payment_gateways) ) {
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            if ( ! empty( $available_gateways ) ) {
                $default_gateway = get_option( 'woocommerce_default_gateway' );
                $chosen_payment = ! empty( $default_gateway ) && isset( $available_gateways[ $default_gateway ] ) ? $default_gateway : array_key_first( $available_gateways );
            }
        }
    }

    // If payment method is not COD, apply online payment discount
    if ( ! empty( $chosen_payment ) && $chosen_payment !== 'cod' ) {
        // Calculate discount on net cart subtotal
        // Net subtotal = Subtotal - Coupon discounts
        $subtotal = floatval( $cart->get_subtotal() );
        $coupon_discounts = floatval( $cart->get_discount_total() );
        $discountable_amount = $subtotal - $coupon_discounts;

        // Also subtract other negative fees (like volume discount / Ritual Benefit)
        foreach ( $cart->get_fees() as $fee_key => $fee ) {
            // Skip the online payment discount fee itself to prevent duplicate calculation on recalculate loops
            if ( $fee_key === 'online-payment-discount' || stripos( $fee->name, 'Online Payment' ) !== false ) {
                continue;
            }
            if ( $fee->amount < 0 ) {
                $discountable_amount += $fee->amount; // negative fee reduces the amount
            }
        }

        if ( $discountable_amount > 0 ) {
            $discount_amount = round( ( $discountable_amount * $discount_pct ) / 100, 2 );
            if ( $discount_amount > 0 ) {
                // Add the negative fee (discount)
                $cart->add_fee( sprintf( __( 'Online Payment Discount (%d%%)', 'ruut-core' ), $discount_pct ), -$discount_amount, false );
            }
        }
    }
}
