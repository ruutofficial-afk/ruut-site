jQuery(document).ready(function($) {
    if ($('form.woocommerce-checkout').length === 0) return;

    // --- INJECT DYNAMIC CSS FIXES & CARD STYLES ---
    if ($('#ruut-dynamic-checkout-css').length === 0) {
        var customStyles = '<style id="ruut-dynamic-checkout-css">' +
            'html { overflow-y: scroll !important; overflow-x: hidden !important; } ' + 
            '.woocommerce-error, .woocommerce-error li, .woocommerce-invalid span.error, .woocommerce-invalid label.error, .woocommerce-checkout-review-order .woocommerce-error, ul.woocommerce-error li { font-size: 11px !important; font-weight: 500 !important; letter-spacing: 0.5px !important; margin-top: 6px !important; line-height: 1.3 !important; color: #d9534f !important; } ' + 
            '.ruut-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(92, 67, 56, 0.35); backdrop-filter: blur(5px); z-index: 999999; display: flex; justify-content: center; align-items: center; opacity: 0; visibility: hidden; transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); } ' +
            '.ruut-modal-overlay.active { opacity: 1; visibility: visible; } ' +
            '.ruut-modal-box { background: #F1ECE6; padding: 45px 35px 35px 35px; border: 1px solid rgba(92, 67, 56, 0.15); border-radius: 2px; max-width: 440px; width: 90%; text-align: center; position: relative; box-shadow: 0 20px 40px rgba(92, 67, 56, 0.12); transform: translateY(-20px) scale(0.98); transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); } ' +
            '.ruut-modal-overlay.active .ruut-modal-box { transform: translateY(0) scale(1); } ' +
            '.ruut-modal-close { position: absolute; top: 12px; right: 18px; font-size: 30px; cursor: pointer; color: #5c4338; line-height: 1; font-weight: 300; opacity: 0.6; transition: 0.3s ease; } ' +
            '.ruut-modal-close:hover { opacity: 1; } ' +
            '#ruut_coupon_modal_text { color: #5c4338; font-family: sans-serif; font-size: 16px; margin: 0 0 30px 0; line-height: 1.5; font-weight: 500; font-style: normal; letter-spacing: 0.5px; } ' +
            '.ruut-modal-btn { background-color: #5c4338 !important; color: #fff !important; padding: 15px 35px !important; text-decoration: none !important; font-family: sans-serif !important; font-size: 12px !important; font-weight: 600 !important; letter-spacing: 2px !important; text-transform: uppercase !important; border-radius: 0px !important; transition: all 0.3s ease; border: 1px solid #5c4338; } ' +
            '.ruut-modal-btn:hover { background-color: transparent !important; color: #5c4338 !important; } ' +
            '.woocommerce-checkout-review-order-table tr.cart-discount td a { display: none !important; } ' +
            '#ruut-dynamic-progress, .ruut-upsell-container { display: none !important; } ' +
            '.ruut-saved-addresses-container { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; width: 100%; order: 8; } ' +
            '.ruut-address-card { background-color: transparent; border: 1px solid rgba(92, 67, 56, 0.3); border-radius: 4px; padding: 15px; width: calc(50% - 7.5px); cursor: pointer; transition: all 0.3s ease; display: flex; flex-direction: column; justify-content: center; box-sizing: border-box; } ' +
            '.ruut-address-card:hover { border-color: #5c4338; background-color: rgba(92, 67, 56, 0.03); } ' +
            '.ruut-address-card.active { border-color: #5c4338; background-color: #5c4338; color: #E6DED1; } ' +
            '.ruut-address-card strong { font-family: sans-serif; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: inherit; letter-spacing: 0.5px; text-transform: uppercase; } ' +
            '.ruut-address-card span { font-family: sans-serif; font-size: 11px; opacity: 0.8; line-height: 1.4; color: inherit; } ' +
            '.ruut-address-card.active strong, .ruut-address-card.active span { color: #E6DED1 !important; } ' +
            '@media(max-width: 768px) { .ruut-address-card { width: 100%; } } ' +
            '</style>';
        $('head').append(customStyles);
    }

    // --- ELIMINATE CHECKOUT REFRESH LAYOUT SHIFT ---
    $(document).on('before_update_checkout', function() {
        var $form = $('form.woocommerce-checkout');
        if ($form.length) {
            $form.css('min-height', $form.height() + 'px');
        }
    });

    // --- VISUAL FEEDBACK: Change Button State During Checkout ---
    var originalBtnText = $('#place_order').text() || $('#place_order').val() || 'PLACE ORDER';
    
    $('form.woocommerce-checkout').on('checkout_place_order', function() {
        // Triggered exactly when validation passes and checkout process begins
        $('#place_order')
            .text('PLACING ORDER...')
            .val('PLACING ORDER...')
            .css({
                'opacity': '0.7', 
                'pointer-events': 'none'
            });
    });

    var step = 'email'; 
    var resendTimer; 

    // --- DEEP DATA EXTRACTOR FOR VAULT ---
    function extractAddressData(addr, key) {
        function fuzzyExtract(obj, keywords) {
            var result = '';
            function searchObj(o) {
                for (var k in o) {
                    if (o.hasOwnProperty(k)) {
                        if (typeof o[k] === 'object' && o[k] !== null) {
                            searchObj(o[k]);
                            if (result) return;
                        } else if (typeof o[k] === 'string' || typeof o[k] === 'number') {
                            var lowerK = k.toLowerCase();
                            for (var i=0; i<keywords.length; i++) {
                                if (lowerK.indexOf(keywords[i]) !== -1) {
                                    result = o[k].toString();
                                    return;
                                }
                            }
                        }
                    }
                }
            }
            searchObj(obj);
            return result;
        }

        var addr1 = addr.billing_address_1 || addr.address_1 || fuzzyExtract(addr, ['address_1', 'address1', 'street1', 'add1']);
        if (!addr1) addr1 = fuzzyExtract(addr, ['address', 'street']);
        
        var addr2 = addr.billing_address_2 || addr.address_2 || fuzzyExtract(addr, ['address_2', 'address2', 'street2', 'add2']);
        var city  = addr.billing_city || addr.city || fuzzyExtract(addr, ['city', 'town', 'district']);
        var pin   = addr.billing_postcode || addr.postcode || fuzzyExtract(addr, ['pin', 'zip', 'post']);
        var st    = addr.billing_state || addr.state || fuzzyExtract(addr, ['state', 'region', 'prov']);
        var mark  = addr.billing_landmark || addr.landmark || fuzzyExtract(addr, ['land', 'mark']);
        var save  = addr.save_as || addr.title || fuzzyExtract(addr, ['save', 'title', 'alias']) || key || 'Saved Address';

        return {
            addr1: addr1, addr2: addr2, city: city, pin: pin, st: st, mark: mark, save: save
        };
    }

    function startResendTimer() {
        var timeLeft = 30;
        var $resendBtn = $('#ruut_resend_otp_btn');
        
        $('#ruut_resend_wrapper').slideDown();
        $resendBtn.css({'pointer-events': 'none', 'opacity': '0.5'}).text('Resend OTP in ' + timeLeft + 's');
        
        clearInterval(resendTimer);
        resendTimer = setInterval(function() {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(resendTimer);
                $resendBtn.css({'pointer-events': 'auto', 'opacity': '1'}).text('Resend OTP');
            } else {
                $resendBtn.text('Resend OTP in ' + timeLeft + 's');
            }
        }, 1000);
    }

    function ruut_enforce_email_lock() {
        var verifiedEmail = sessionStorage.getItem('ruut_guest_email');
        
        if ($('body').hasClass('logged-in')) {
            $('#billing_email').prop('readonly', true).css({'opacity': '0.5', 'pointer-events': 'none'});
        } else if (verifiedEmail) {
            $('#billing_email').val(verifiedEmail).prop('readonly', true).css({'opacity': '0.5', 'pointer-events': 'none'});
        } else if (step === 'otp') {
            $('#billing_email').prop('readonly', true).css({'opacity': '0.5', 'pointer-events': 'none'});
        }
    }

    function ruut_enforce_place_order_lock() {
        var isLocked = false;
        if (!$('body').hasClass('logged-in') && !sessionStorage.getItem('ruut_guest_email')) {
            isLocked = true;
        }
        
        var $btn = $('#place_order');
        if (isLocked) {
            $btn.css({'opacity': '0.4', 'pointer-events': 'none'})
                .text('VERIFY EMAIL FIRST')
                .val('VERIFY EMAIL FIRST');
        } else {
            // Restore ONLY if it's not currently processing an order
            if ($btn.text() !== 'PLACING ORDER...' && $btn.val() !== 'PLACING ORDER...') {
                $btn.css({'opacity': '1', 'pointer-events': 'auto'})
                    .text(originalBtnText)
                    .val(originalBtnText);
            }
        }
    }

    function ruut_build_layout() {
        var wrapper = $('.woocommerce-billing-fields__field-wrapper');
        
        // --- INJECT HIDDEN ADDRESS TRACKER FOR BACKEND INTERCEPT ---
        if ($('#ruut_selected_address').length === 0) {
            wrapper.append('<input type="hidden" name="ruut_selected_address" id="ruut_selected_address" value="new">');
        }
        
        if ($('#custom_contacts_wrapper').length === 0) {
            wrapper.prepend('<div id="custom_contacts_wrapper"><h3>Contacts</h3></div>');
        }
        
        if ($('#custom_shipping_wrapper').length === 0) {
            wrapper.append('<div id="custom_shipping_wrapper"><h3>Shipping address</h3></div>');

            // --- INJECT ELEGANT ADDRESS CARDS ---
            if (window.ruutAddressBook && Object.keys(window.ruutAddressBook).length > 0) {
                var buttonsHtml = '<div id="ruut_saved_addresses_wrapper" class="ruut-saved-addresses-container">';
                var hasDefault = false;
                
                $.each(window.ruutAddressBook, function(key, addr) {
                    var data = extractAddressData(addr, key);
                    var defaultClass = '';
                    if (addr.is_default && !hasDefault) {
                        defaultClass = ' ruut-default-addr';
                        hasDefault = true;
                    }
                    
                    var preview = '';
                    if (data.addr1) preview += data.addr1;
                    if (data.addr2) preview += (preview ? ', ' : '') + data.addr2;
                    if (data.mark)  preview += (preview ? ', ' : '') + data.mark;
                    if (data.city)  preview += (preview ? ', ' : '') + data.city;
                    if (data.st)    preview += (preview ? ', ' : '') + data.st;
                    if (data.pin)   preview += (preview ? ' - ' : '') + data.pin;
                    
                    buttonsHtml += '<div class="ruut-address-card' + defaultClass + '" data-key="' + key + '">' +
                                   '<strong>' + data.save + '</strong>' +
                                   '<span>' + preview + '</span>' +
                                   '</div>';
                });
                
                // Add New Address Card
                buttonsHtml += '<div class="ruut-address-card ruut-add-new-card" data-key="new">' +
                               '<strong style="margin-bottom:0;">+ Add New Address</strong>' +
                               '</div>';
                
                buttonsHtml += '</div>';
                $('#custom_shipping_wrapper').after(buttonsHtml);

                // Auto-select Default Address
                setTimeout(function() {
                    var $targetBtn = $('.ruut-default-addr').length ? $('.ruut-default-addr') : $('.ruut-address-card').first();
                    if ($targetBtn.length && !$('.ruut-address-card.active').length) {
                        $targetBtn.trigger('click');
                    }
                }, 100);
            }
        }

        var verifiedEmail = sessionStorage.getItem('ruut_guest_email');

        if (!$('body').hasClass('logged-in')) {
            if (verifiedEmail) {
                if ($('#ruut_guest_welcome').length === 0) {
                    $('#billing_email_field').after('<div id="ruut_guest_welcome" style="width:100%; margin-top:15px; color:#5c4338; font-size:14px; font-weight:600; order:3;">Welcome to the Ruut family! The journey is about to be exciting. Let\'s get your details.</div>');
                }
                $('#ruut_otp_wrapper, #custom_button_wrapper').remove();
                wrapper.addClass('ruut-fields-revealed');
            } else {
                if ($('#ruut_otp_wrapper').length === 0) {
                    var otpHTML = '<div id="ruut_otp_wrapper" style="display:none; width: 100%; order: 3; margin-top: 15px;">' +
                                  '<label style="font-family: sans-serif; font-size: 11px !important; text-transform: uppercase; color: #5c4338 !important; font-weight: 700 !important; margin-bottom:8px; display:block;">Enter OTP</label>' +
                                  '<div class="ruut-otp-boxes-container">' +
                                  '<div class="ruut-otp-box">X</div><div class="ruut-otp-box">X</div><div class="ruut-otp-box">X</div><div class="ruut-otp-box">X</div><div class="ruut-otp-box">X</div><div class="ruut-otp-box">X</div>' +
                                  '<input type="tel" id="ruut_otp_input" maxlength="6" autocomplete="one-time-code">' +
                                  '</div></div>';
                    $('#billing_email_field').after(otpHTML);
                }
                
                if ($('#custom_button_wrapper').length === 0) {
                    var buttonsHTML = '<div id="custom_button_wrapper" style="order: 4; width: 100%;">' +
                                      '<button type="button" id="ruut_action_btn">GET OTP</button>' +
                                      '<div id="ruut_resend_wrapper" style="display:none; text-align:center; margin-top:15px;">' +
                                      '<a href="#" id="ruut_resend_otp_btn" style="color:#5c4338; font-size:13px; font-weight:600; text-decoration:underline;"></a>' +
                                      '<span style="color:#a89f91; font-size:12px; margin: 0 10px;">|</span>' +
                                      '<a href="#" id="ruut_change_email_btn" style="color:#5c4338; font-size:13px; font-weight:600; text-decoration:underline;">Change Email</a>' +
                                      '</div>' +
                                      '<div id="ruut_status_message" style="display:none; margin-top:15px; font-size:14px; font-weight: 500; text-align:center;"></div>' +
                                      '</div>';
                    wrapper.append(buttonsHTML);
                }
            }
        }
        
        ruut_enforce_email_lock();
        ruut_enforce_place_order_lock();
    }
    
    // --- HIGHLY ROBUST CARD MAPPING & VISIBILITY TOGGLE ---
    $(document).on('click', '.ruut-address-card', function() {
        var $btn = $(this);
        var key = $btn.data('key');
        var fieldsToToggle = $('#billing_postcode_field, #billing_city_field, #billing_state_field, #billing_address_1_field, #billing_address_2_field, #billing_landmark_field, #billing_save_as_field');

        $('.ruut-address-card').removeClass('active');
        $btn.addClass('active');

        // 1. DUAL BACKEND INTERCEPT: Update hidden tracking field for the server
        $('#ruut_selected_address').val(key);

        if (key === 'new') {
            // Scenario B: Show fields for new address entry
            fieldsToToggle.slideDown(300);
            
            // Clear inputs SILENTLY without triggering WC's instant validation 'change' event
            $('#billing_address_1, #billing_address_2, #billing_city, #billing_postcode, #billing_landmark, #billing_save_as').val('');
            
            var $stateField = $('#billing_state');
            $stateField.val('');
            if ($stateField.hasClass('select2-hidden-accessible')) {
                $stateField.trigger('change.select2'); // Only update select2 UI, not WC validation
            }
            
            // Manually strip away any red validation classes from the wrappers
            fieldsToToggle.removeClass('woocommerce-invalid woocommerce-invalid-required-field woocommerce-validated');
            
        } else {
            // Scenario A: Hide fields completely. The backend intercept handles the heavy lifting.
            fieldsToToggle.slideUp(300);
            
            var addr = window.ruutAddressBook[key];
            var data = extractAddressData(addr, key);
            
            // Fill fields silently
            $('#billing_address_1').val(data.addr1);
            $('#billing_address_2').val(data.addr2);
            $('#billing_city').val(data.city);
            $('#billing_postcode').val(data.pin);
            $('#billing_landmark').val(data.mark);
            $('#billing_save_as').val(data.save);
            
            // State Resolution Engine
            if (data.st) {
                var $stateField = $('#billing_state');
                if ($stateField.is('select')) {
                    if ($stateField.find('option[value="' + data.st + '"]').length) {
                        $stateField.val(data.st);
                    } else {
                        var foundMatch = false;
                        $stateField.find('option').each(function() {
                            if ($(this).text().trim().toLowerCase() === data.st.trim().toLowerCase()) {
                                $stateField.val($(this).val());
                                foundMatch = true;
                                return false; 
                            }
                        });
                        if (!foundMatch) $stateField.val(data.st);
                    }
                    if ($stateField.hasClass('select2-hidden-accessible')) {
                        $stateField.trigger('change.select2');
                    }
                } else {
                    $stateField.val(data.st);
                }
            }
            
            // Critical fix: Force WooCommerce to sync ONLY when an existing address is chosen
            // so shipping calculators use the vault data instantly.
            $('body').trigger('update_checkout');
        }
    });

    // Renders the OTP visual boxes with a blinking cursor when active
    function renderOtpBoxes() {
        var $input = $('#ruut_otp_input');
        var val = $input.val() ? $input.val().replace(/\D/g, '').substring(0, 6) : '';
        var isFocused = $input.is(':focus');
        
        $('.ruut-otp-box').each(function(index) {
            $(this).removeClass('ruut-cursor-active');
            if (index < val.length) {
                $(this).text(val[index]);
            } else if (index === val.length && isFocused) {
                $(this).html('<span class="ruut-cursor">|</span>');
            } else {
                $(this).text('X');
            }
        });
    }

    $(document).on('input focus blur', '#ruut_otp_input', function() {
        var val = $(this).val().replace(/\D/g, '').substring(0, 6); 
        $(this).val(val);
        renderOtpBoxes();
    });

    function ruut_inject_order_elements() {
        if ($('.ruut-inline-edit-cart').length === 0) {
            $('.woocommerce-checkout-review-order-table th.product-name').html('<span class="ruut-order-heading-text">Your order</span> <a href="https://yourruut.com/cart-2/" class="ruut-inline-edit-cart">Edit cart</a>');
        }

        var $table = $('.woocommerce-checkout-review-order-table');
        if ($table.find('tbody tr.cart_item').length > 0) {
            $table.find('tbody tr.cart_item').prependTo($table.find('tfoot'));
            $table.find('tbody').hide();
        }

        if ($('.ruut-coupon-row').length === 0 && $('.woocommerce-checkout-review-order-table tr.cart-subtotal').length > 0) {
            var couponHTML = '<tr class="ruut-coupon-row"><td colspan="2" style="padding:0 !important; border-bottom: 1px solid rgba(92, 67, 56, 0.15) !important;">' +
                '<div class="ruut-custom-coupon-wrapper">' +
                '<div class="ruut-coupon-toggle"><span>Add coupons</span> <span style="font-size:10px;">▼</span></div>' +
                '<div class="ruut-coupon-form-content" style="display:none;">' +
                '<input type="text" id="ruut_custom_coupon_input" placeholder="Coupon code">' +
                '<button type="button" id="ruut_custom_coupon_btn">Apply</button>' +
                '</div></div></td></tr>';
            
            $('.woocommerce-checkout-review-order-table tr.cart-subtotal').before(couponHTML);
        }
    }

    $(document).on('click', '.ruut-coupon-toggle', function() {
        $('.ruut-coupon-form-content').slideToggle();
    });

    $(document).on('keypress', '#ruut_custom_coupon_input', function(e) {
        if (e.which === 13) {
            e.preventDefault(); 
            e.stopPropagation(); 
            $('#ruut_custom_coupon_btn').click(); 
            return false;
        }
    });

    var modalTimeout;
    function ruut_show_coupon_modal(message, showLink, autoClose) {
        if ($('#ruut_coupon_modal').length === 0) {
            var modalHtml = '<div id="ruut_coupon_modal" class="ruut-modal-overlay">' +
                '<div class="ruut-modal-box">' +
                '<span class="ruut-modal-close">&times;</span>' +
                '<p id="ruut_coupon_modal_text"></p>' +
                '<a href="https://yourruut.com/cart-2/" id="ruut_coupon_modal_link" class="ruut-modal-btn">GO TO CART</a>' +
                '</div></div>';
            $('body').append(modalHtml);
        }
        
        $('#ruut_coupon_modal_text').text(message);
        
        if (showLink) {
            $('#ruut_coupon_modal_link').attr('style', 'display: inline-block !important;');
        } else {
            $('#ruut_coupon_modal_link').attr('style', 'display: none !important;');
        }
        
        $('#ruut_coupon_modal').addClass('active');

        clearTimeout(modalTimeout);
        if (autoClose) {
            modalTimeout = setTimeout(function() {
                $('#ruut_coupon_modal').removeClass('active');
            }, 2000); 
        }
    }

    $(document).on('click', '.ruut-modal-close, .ruut-modal-overlay', function(e) {
        if ($(e.target).hasClass('ruut-modal-overlay') || $(e.target).hasClass('ruut-modal-close')) {
            $('#ruut_coupon_modal').removeClass('active');
            clearTimeout(modalTimeout);
        }
    });

    $(document).on('click', '#ruut_custom_coupon_btn', function(e) {
        e.preventDefault();
        var code = $('#ruut_custom_coupon_input').val();
        if(!code) return;
        
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.text('...');
        
        var data = {
            security: typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.apply_coupon_nonce : '',
            coupon_code: code
        };

        $.ajax({
            type: 'POST',
            url: typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' ) : woocommerce_params.ajax_url + '?action=apply_coupon',
            data: data,
            dataType: 'html',
            success: function(response) {
                $btn.text(originalText);
                
                var $html = $('<div>').html(response);
                var isError = $html.find('.woocommerce-error').length > 0;
                var text = $html.text().replace(/Dismiss/gi, '').trim(); 
                
                if (!isError && text) {
                    $('body').trigger('update_checkout');
                    $('#ruut_custom_coupon_input').val(''); 
                    $('.ruut-coupon-form-content').slideUp();
                } else {
                    var finalMessage = "Coupon could not be applied.";
                    var showLink = true;
                    var autoClose = false;

                    if (text.toLowerCase().indexOf('minimum spend') !== -1) {
                        var minSpendText = text.substring(text.indexOf('is '));
                        var minSpend = parseFloat(minSpendText.replace(/[^\d.]/g, ''));
                        
                        var rawSubtotal = $('.cart-subtotal .woocommerce-Price-amount').text();
                        var subtotal = parseFloat(rawSubtotal.replace(/[^\d.]/g, ''));
                        
                        if (!isNaN(minSpend) && !isNaN(subtotal) && subtotal < minSpend) {
                            var diff = minSpend - subtotal;
                            var currencySymbol = rawSubtotal.replace(/[\d.,\s]/g, '').trim().charAt(0) || '₹'; 
                            var diffFormatted = diff % 1 === 0 ? diff.toString() : diff.toFixed(2);
                            finalMessage = "Add products worth " + currencySymbol + diffFormatted + " to enjoy the benefits.";
                        } else {
                            finalMessage = text; 
                        }
                    } 
                    else if (text.toLowerCase().indexOf('does not exist') !== -1 || text.toLowerCase().indexOf('invalid') !== -1 || text.toLowerCase().indexOf('not applicable') !== -1) {
                        finalMessage = "The coupon doesn't exist.";
                        showLink = false;
                        autoClose = true;
                    } 
                    else if (text) {
                        finalMessage = text;
                    }

                    ruut_show_coupon_modal(finalMessage, showLink, autoClose);
                }
            },
            error: function() {
                $btn.text(originalText);
                ruut_show_coupon_modal("Something went wrong. Please try again.", false, true);
            }
        });
    });

    ruut_build_layout();
    ruut_inject_order_elements();

    $(document).on('updated_checkout', function() {
        $('form.woocommerce-checkout').css('min-height', ''); 
        ruut_build_layout();
        ruut_inject_order_elements();
        ruut_enforce_email_lock();
        ruut_enforce_place_order_lock();
    });

    $(document).ajaxComplete(function() {
        ruut_enforce_email_lock();
        ruut_enforce_place_order_lock();
    });

    $(document.body).on('checkout_error', function() {
        // Reset the visually locked Place Order button if checkout failed!
        $('#place_order')
            .text(originalBtnText)
            .val(originalBtnText)
            .css({
                'opacity': '1', 
                'pointer-events': 'auto'
            });
            
        ruut_enforce_place_order_lock();

        var $addressFields = $('#billing_postcode_field, #billing_city_field, #billing_state_field, #billing_address_1_field, #billing_address_2_field, #billing_landmark_field, #billing_save_as_field');
        
        // ONLY open the address box if one of the specific address fields has the red invalid class!
        if ($addressFields.filter('.woocommerce-invalid').length > 0) {
            $addressFields.slideDown(300);
        }
        
        // Scroll logic so the user perfectly sees exactly which field threw the error
        var $errorBox = $('.woocommerce-NoticeGroup-checkout, .woocommerce-error').first();
        if ($errorBox.length && $errorBox.text().trim() !== '') {
            $('html, body').animate({
                scrollTop: ($errorBox.offset().top - 150)
            }, 600);
        } else {
            var $invalidField = $('.woocommerce-invalid').first();
            if ($invalidField.length) {
                $('html, body').animate({
                    scrollTop: ($invalidField.offset().top - 150)
                }, 600);
            }
        }
    });

    $(document).on('keypress', '#billing_email, #ruut_otp_input', function(e) {
        if (e.which === 13) {
            e.preventDefault(); 
            $('#ruut_action_btn').click(); 
        }
    });

    $(document).on('click', '#ruut_resend_otp_btn', function(e) {
        e.preventDefault();
        var emailVal = $('#billing_email').val();
        var $msg = $('#ruut_status_message');
        
        $msg.html('<span style="color:#5c4338;">Resending code...</span>').slideDown();
        
        $.ajax({
            type: 'POST',
            url: woocommerce_params.ajax_url,
            data: { action: 'ruut_send_otp', email: emailVal },
            success: function(response) {
                if (response.success) {
                    $msg.html('<span style="color:#5c4338;">' + response.data.message + '</span>').slideDown();
                    startResendTimer(); 
                } else {
                    $msg.html('<span style="color:red;">' + response.data.message + '</span>').slideDown();
                }
            }
        });
    });

    $(document).on('click', '#ruut_change_email_btn', function(e) {
        e.preventDefault();
        
        step = 'email';
        
        $('#ruut_otp_wrapper').slideUp().removeClass('show-ruut-otp');
        $('#ruut_resend_wrapper').slideUp();
        $('#ruut_status_message').slideUp().empty();
        
        $('#billing_email').prop('readonly', false).css({'opacity': '1', 'pointer-events': 'auto'}).focus();
        
        $('#ruut_action_btn').text('GET OTP').show();
        
        $('#ruut_otp_input').val('');
        renderOtpBoxes();
        
        clearInterval(resendTimer);
    });

    $(document).on('click', '#ruut_action_btn', function(e) {
        e.preventDefault();
        var emailVal = $('#billing_email').val();
        var $btn = $(this);
        var $msg = $('#ruut_status_message');
        var $wrapper = $('.woocommerce-billing-fields__field-wrapper');

        if (step === 'email') {
            if (emailVal.trim() === '' || !emailVal.includes('@')) {
                $msg.html('<span style="color:red;">Please enter a valid email.</span>').slideDown();
                return;
            }

            $btn.text('SENDING...');

            $.ajax({
                type: 'POST',
                url: woocommerce_params.ajax_url,
                data: { action: 'ruut_send_otp', email: emailVal },
                success: function(response) {
                    if (response.success) {
                        step = 'otp';
                        ruut_enforce_email_lock();
                        
                        $('#ruut_otp_wrapper').slideDown().addClass('show-ruut-otp'); 
                        
                        $msg.html('<span style="color:#5c4338;">' + response.data.message + '</span>').slideDown();
                        $btn.text('VERIFY OTP');
                        
                        startResendTimer(); 
                        
                        setTimeout(function(){ 
                            $('#ruut_otp_input').focus(); 
                            renderOtpBoxes(); 
                        }, 100);
                    } else {
                        $msg.html('<span style="color:red;">' + response.data.message + '</span>').slideDown();
                        $btn.text('GET OTP');
                    }
                }
            });

        } else if (step === 'otp') {
            var otpVal = $('#ruut_otp_input').val().replace(/\s/g, ''); 
            
            if (otpVal.length < 6) {
                $msg.html('<span style="color:red;">Please enter the full 6-digit OTP.</span>').slideDown();
                return;
            }

            $btn.text('VERIFYING...');

            $.ajax({
                type: 'POST',
                url: woocommerce_params.ajax_url,
                data: { action: 'ruut_verify_otp', email: emailVal, otp: otpVal },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'existing') {
                            $msg.html('<span style="color:#5c4338;">Welcome back! Loading your saved details...</span>').slideDown();
                            setTimeout(function(){ window.location.reload(); }, 1500);
                        } else {
                            sessionStorage.setItem('ruut_guest_email', emailVal);
                            ruut_enforce_email_lock();
                            ruut_enforce_place_order_lock();
                            
                            $msg.html('<span style="color:#5c4338;">' + response.data.message + '</span>').slideDown();
                            $btn.hide();
                            $('#ruut_resend_wrapper').hide(); 
                            $('#ruut_otp_wrapper').remove(); 
                            $wrapper.addClass('ruut-fields-revealed');
                        }
                    } else {
                        $msg.html('<span style="color:red;">' + response.data.message + '</span>').slideDown();
                        $btn.text('VERIFY OTP');
                    }
                }
            });
        }
    });
});