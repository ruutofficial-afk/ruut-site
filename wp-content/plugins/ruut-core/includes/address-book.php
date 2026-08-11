<?php
/**
 * Standard WooCommerce My Account Address Book Component & CRUD Actions
 * Extracted from ruut-core.php PART 6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!function_exists('ruut_add_phone_to_account_details')) {
    add_action( 'woocommerce_edit_account_form', 'ruut_add_phone_to_account_details', 5 );
    function ruut_add_phone_to_account_details() {
        $user_id = get_current_user_id();
        $phone   = get_user_meta( $user_id, 'billing_phone', true );
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_phone">Phone Number <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
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
        $is_acc = function_exists('is_account_page') && is_account_page();
        $is_chk = function_exists('is_checkout') && is_checkout();
        if ( ( ! $is_acc && ! $is_chk ) || ! is_user_logged_in() ) return;
        ?>
        <div id="ruut-address-modal" class="ruut-modal">
            <div class="ruut-modal-content">
                <span class="ruut-modal-close" onclick="ruutCloseAddressModal()">&times;</span>
                <h3 id="ruut-address-modal-title">Add New Address</h3>
                <form id="ruut-address-form" class="ruut-address-modal-form">
                    <input type="hidden" id="ruut_addr_id" name="id" value="" />
                    <div class="form-row">
                        <label>Save As (e.g. Home, Office) <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
                        <input type="text" id="ruut_addr_title" name="title" required />
                    </div>
                    <div class="form-columns">
                        <div>
                            <label>Pin Code <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
                            <input type="text" id="ruut_addr_postcode" name="postcode" required />
                        </div>
                        <div>
                            <label>Town / City <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
                            <input type="text" id="ruut_addr_city" name="city" required />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>State <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
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
                        <label>Address <span style="color: var(--ruut-accent, #c69c6d);">*</span></label>
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
