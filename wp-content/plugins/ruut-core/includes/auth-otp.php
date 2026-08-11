<?php
/**
 * Standard WooCommerce My Account OTP Authentication, Login Cookie, and Styling
 * Extracted from ruut-core.php PART 4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- EXTEND LOGIN COOKIE EXPIRATION TO 1 YEAR FOR PERSISTENT SESSION ---
if (!function_exists('ruut_extend_login_session')) {
    add_filter( 'auth_cookie_expiration', 'ruut_extend_login_session', 99, 3 );
    function ruut_extend_login_session( $expiration, $user_id, $remember ) {
        return 31536000;
    }
}

if (!function_exists('ruut_change_login_title')) {
    add_filter( 'woocommerce_endpoint_customer-logout_title', 'ruut_change_login_title' );
    function ruut_change_login_title( $title ) {
        return 'Login / Register';
    }
}

if (!function_exists('ruut_rename_username_label')) {
    add_filter('gettext', 'ruut_rename_username_label', 10, 3);
    function ruut_rename_username_label($translated_text, $text, $domain) {
        if ($domain === 'woocommerce' && function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) {
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
        if ( function_exists('is_account_page') && is_account_page() ) {
            ?>
            <style>
                body.woocommerce-account:not(.logged-in) .woocommerce:has(.woocommerce-form-login) {
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: center !important;
                    align-items: center !important;
                    min-height: 50vh !important;
                    margin-top: 120px !important;
                    margin-bottom: 20px !important;
                }
                .woocommerce-form-login {
                    width: 100% !important;
                    max-width: 420px !important;
                    margin: 0 auto !important;
                    position: relative;
                    background: rgba(255, 255, 255, 0.75) !important;
                    border: 1px solid rgba(74, 51, 36, 0.15) !important;
                    border-radius: 16px !important;
                    padding: 32px 28px !important;
                    box-shadow: 0 10px 30px rgba(74, 51, 36, 0.05) !important;
                    backdrop-filter: blur(10px) !important;
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
        $google_client_id = trim(get_option('ruut_google_client_id', ''));
        if ( ! empty($google_client_id) ) {
            ?>
            <style>
            .ruut-google-login-separator {
                display: flex !important;
                align-items: center !important;
                text-align: center !important;
                margin: 24px 0 16px 0 !important;
                width: 100% !important;
            }
            .ruut-google-login-separator::before,
            .ruut-google-login-separator::after {
                content: '' !important;
                flex: 1 !important;
                border-bottom: 1.5px solid rgba(74, 51, 36, 0.15) !important;
            }
            .ruut-google-login-separator:not(:empty)::before {
                margin-right: .8em !important;
            }
            .ruut-google-login-separator:not(:empty)::after {
                margin-left: .8em !important;
            }
            .ruut-google-login-separator span {
                font-family: 'Karla', sans-serif !important;
                font-size: 11px !important;
                font-weight: 700 !important;
                color: #664436 !important;
                letter-spacing: 2px !important;
            }
            .ruut-google-login-button-container {
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                width: 100% !important;
                margin-top: 15px !important;
                margin-bottom: 10px !important;
            }
            .g_id_signin {
                width: 100% !important;
                max-width: 364px !important;
                min-height: 40px !important;
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
                margin: 0 auto !important;
            }
            .g_id_signin iframe {
                margin: 0 auto !important;
            }
            </style>
            <script>
            window.handleGoogleCredentialResponse = function(response) {
                var $msg = jQuery('#otp_message');
                if ($msg.length === 0) {
                    $msg = jQuery('<div id="otp_message" class="otp-status-message"></div>').appendTo('form.woocommerce-form-login');
                }
                $msg.html('<span style="color:#4A3324;">Connecting to Google...</span>').slideDown();
                
                jQuery.ajax({
                    type: 'POST',
                    url: '<?php echo esc_url(admin_url("admin-ajax.php")); ?>',
                    data: {
                        action: 'ruut_google_login',
                        credential: response.credential,
                        security: '<?php echo esc_js(wp_create_nonce("ruut_google_nonce")); ?>'
                    },
                    success: function(res) {
                        if (res.success) {
                            $msg.html('<span style="color:green;">Authenticated! Logging you in...</span>').slideDown();
                            window.location.reload();
                        } else {
                            $msg.html('<span style="color:red;">Google Login Failed: ' + res.data.message + '</span>').slideDown();
                        }
                    },
                    error: function() {
                        $msg.html('<span style="color:red;">Network error during Google validation. Please try again.</span>').slideDown();
                    }
                });
            };
            </script>
            <div class="ruut-google-login-separator">
                <span>OR</span>
            </div>
            <div class="ruut-google-login-button-container">
                <div id="g_id_onload"
                     data-client_id="<?php echo esc_attr($google_client_id); ?>"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="handleGoogleCredentialResponse"
                     data-auto_select="false"
                     data-itp_support="true">
                </div>
                <div class="g_id_signin"
                     data-type="standard"
                     data-shape="rectangular"
                     data-theme="outline"
                     data-text="signin_with"
                     data-size="large"
                     data-logo_alignment="left"
                     data-width="364">
                </div>
            </div>
            <?php
        }
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
        $is_woo     = function_exists('is_woocommerce') && is_woocommerce();
        $is_account = function_exists('is_account_page') && is_account_page();
        $is_chk     = function_exists('is_checkout') && is_checkout();
        $is_crt     = function_exists('is_cart') && is_cart();
        
        if ( $is_account || $is_chk || $is_crt || $is_woo || is_page('track') || is_page('track-order') ) {
            // Use plugin root directory for asset paths (this file is in includes/ subdirectory)
            $plugin_root_file = dirname( __FILE__, 2 ) . '/ruut-core.php';
            $google_client_id = get_option('ruut_google_client_id', '');

            if ( ! empty($google_client_id) ) {
                wp_enqueue_script( 'google-gis', 'https://accounts.google.com/gsi/client', array(), null, true );
            }

            wp_enqueue_script( 'ruut-account-scripts', plugins_url( 'assets/checkout.js', $plugin_root_file ), array('jquery'), '4.23', true );
            wp_localize_script( 'ruut-account-scripts', 'ruut_account_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'home_url' => home_url('/'),
                'google_client_id' => $google_client_id,
                'google_nonce' => wp_create_nonce( 'ruut_google_nonce' )
            ));
            if ( ! is_user_logged_in() ) {
                wp_localize_script( 'ruut-account-scripts', 'ruut_ajax', array(
                    'url'   => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( 'ruut_otp_nonce' )
                ) );
            }
            wp_enqueue_style( 'ruut-google-fonts', 'https://fonts.googleapis.com/css2?family=Karla:ital,wght@0,200..800;1,200..800&family=Lexend+Deca:wght@100..900&display=swap', array(), null );
            wp_enqueue_style( 'ruut-fonts', site_url( '/assets/css/fonts.css' ), array(), '1.0' );
            wp_enqueue_style( 'ruut-main-styles', site_url( '/assets/css/styles.css' ), array(), '4.2' );
            wp_enqueue_style( 'ruut-header-styles', site_url( '/assets/css/components/header.css' ), array(), '2.0' );
            wp_enqueue_style( 'ruut-search-styles', site_url( '/assets/css/components/search.css' ), array(), '2.0' );
            wp_enqueue_style( 'ruut-account-styles', plugins_url( 'assets/checkout.css', $plugin_root_file ), array(), '8.5' );
        }
    }
}

if (!function_exists('ruut_add_async_defer_to_google_gis')) {
    add_filter( 'script_loader_tag', 'ruut_add_async_defer_to_google_gis', 10, 2 );
    function ruut_add_async_defer_to_google_gis( $tag, $handle ) {
        if ( 'google-gis' === $handle ) {
            return str_replace( ' src', ' async defer src', $tag );
        }
        return $tag;
    }
}

// --- GOOGLE SIGN-IN AJAX HANDLER ---
add_action( 'wp_ajax_nopriv_ruut_google_login', 'ruut_handle_google_login' );
add_action( 'wp_ajax_ruut_google_login', 'ruut_handle_google_login' );
function ruut_handle_google_login() {
    check_ajax_referer('ruut_google_nonce', 'security');

    $credential = isset($_POST['credential']) ? sanitize_text_field($_POST['credential']) : '';
    if ( empty($credential) ) {
        wp_send_json_error( array( 'message' => 'No credential token received from Google.' ) );
    }

    $google_client_id = trim(get_option('ruut_google_client_id', ''));
    if ( empty($google_client_id) ) {
        wp_send_json_error( array( 'message' => 'Google Login is currently disabled on this server.' ) );
    }

    // Call Google's tokeninfo endpoint to verify token authenticity
    $verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
    $response = wp_remote_get($verify_url);

    if ( is_wp_error($response) ) {
        error_log('RUUT Google Login: wp_remote_get failed. Error: ' . $response->get_error_message());
        wp_send_json_error( array( 'message' => 'Failed to connect to Google verification API.' ) );
    }

    $body = wp_remote_retrieve_body($response);
    $payload = json_decode($body, true);

    if ( ! $payload || isset($payload['error']) ) {
        error_log('RUUT Google Login: Token validation failed. Payload: ' . print_r($payload, true));
        wp_send_json_error( array( 'message' => 'Invalid or expired Google login token.' ) );
    }

    // Verify client ID matches our registered Client ID
    $aud = isset($payload['aud']) ? trim($payload['aud']) : '';
    if ( $aud !== $google_client_id ) {
        error_log('RUUT Google Login: Audience client ID mismatch. Aud: ' . $aud . ', Expected: ' . $google_client_id);
        wp_send_json_error( array( 'message' => 'Client ID mismatch. Verification failed.' ) );
    }

    // Check email verification status
    $email = isset($payload['email']) ? sanitize_email($payload['email']) : '';
    $email_verified = isset($payload['email_verified']) ? $payload['email_verified'] : '';

    if ( empty($email) || ($email_verified !== 'true' && $email_verified !== true) ) {
        wp_send_json_error( array( 'message' => 'Google account email is not verified.' ) );
    }

    // Find or create user
    $user = get_user_by( 'email', $email );

    if ( ! $user ) {
        // Generate a clean sanitized username from email prefix
        $username_base = sanitize_user( current( explode( '@', $email ) ) );
        $username = $username_base;
        $count = 1;
        while ( username_exists( $username ) ) {
            $username = $username_base . $count;
            $count++;
        }

        // Create new user
        $password = wp_generate_password();
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            error_log('RUUT Google Login: wp_create_user failed. Error: ' . $user_id->get_error_message());
            wp_send_json_error( array( 'message' => 'Failed to register new account: ' . $user_id->get_error_message() ) );
        }
        $user = new WP_User( $user_id );
        $user->set_role( 'customer' );

        // Update name details from Google
        $first_name = isset($payload['given_name']) ? sanitize_text_field($payload['given_name']) : '';
        $last_name = isset($payload['family_name']) ? sanitize_text_field($payload['family_name']) : '';
        $full_name = isset($payload['name']) ? sanitize_text_field($payload['name']) : '';

        wp_update_user( array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => !empty($full_name) ? $full_name : $username
        ) );
        update_user_meta( $user_id, '_ruut_needs_onboarding', 'yes' );
        
        // Refresh user object
        $user = get_user_by( 'id', $user_id );
    }

    // Authenticate user
    wp_clear_auth_cookie();
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true); // set persistent cookie (1 year)

    // Trigger wp_login action for standard cookies & WooCommerce session synchronization
    do_action( 'wp_login', $user->user_login, $user );

    // Sync WooCommerce session
    if ( class_exists( 'WC' ) && WC()->session ) {
        wc_set_customer_auth_cookie( $user->ID );
    }

    wp_send_json_success( array( 'message' => 'Login successful!' ) );
}
