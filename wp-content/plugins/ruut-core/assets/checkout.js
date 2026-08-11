jQuery(document).ready(function($) {
    // Determine the dynamic WordPress home URL prefix (supporting subdirectories)
    var homeUrl = (typeof ruut_account_ajax !== 'undefined' && ruut_account_ajax.home_url) ? ruut_account_ajax.home_url : '/';
    // Ensure homeUrl ends with a single slash
    if (homeUrl.slice(-1) !== '/') {
        homeUrl += '/';
    }

    // Determine the dynamic WordPress AJAX URL (supporting subdirectories and missing localized parameters)
    var ajaxUrl = '';
    if (typeof ruut_ajax !== 'undefined' && ruut_ajax.url) {
        ajaxUrl = ruut_ajax.url;
    } else if (typeof ruut_account_ajax !== 'undefined' && ruut_account_ajax.ajax_url) {
        ajaxUrl = ruut_account_ajax.ajax_url;
    } else if (typeof woocommerce_params !== 'undefined' && woocommerce_params.ajax_url) {
        ajaxUrl = woocommerce_params.ajax_url;
    } else if (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.ajax_url) {
        ajaxUrl = wc_checkout_params.ajax_url;
    } else {
        ajaxUrl = homeUrl + 'wp-admin/admin-ajax.php';
    }

    // Clear client-side cart on successful payment (WooCommerce Thank You / Order Received page)
    if ($('body').hasClass('woocommerce-order-received') || window.location.pathname.indexOf('order-received') !== -1) {
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('ruut_cart');
            localStorage.removeItem('ruut_coupon');
            localStorage.removeItem('ruut_coupon_savings');
            console.log('Ruut: Cart cleared on successful payment.');
        }
    }

    // --- INJECT NAVIGATION FROM LANDING PAGE ---
    if ($('header#mainHeader').length === 0 && !$('body').hasClass('woocommerce-checkout')) {
        var headerHtml = '<header id="mainHeader">' +
            '<div class="nav-container">' +
                '<!-- Left Navigation (Desktop) -->' +
                '<ul class="nav-links left">' +
                    '<li><a href="' + homeUrl + 'index.html#home">Home</a></li>' +
                    '<li class="nav-item-has-children">' +
                        '<a href="' + homeUrl + 'category.html?category=airmist">Air Mist</a>' +
                        '<ul class="sub-menu" id="nav-submenu-airmist">' +
                            '<li><a href="' + homeUrl + 'collection.html?collection=the-atlas-collection">The Atlas Collection</a></li>' +
                        '</ul>' +
                    '</li>' +
                    '<li class="nav-item-has-children">' +
                        '<a href="' + homeUrl + 'category.html?category=diffuser">Diffuser</a>' +
                        '<ul class="sub-menu" id="nav-submenu-diffuser"></ul>' +
                    '</li>' +
                    '<li>' +
                        '<a href="' + homeUrl + 'category.html?category=essential-oils">Essential Oils</a>' +
                    '</li>' +
                    '<li class="nav-item-gifts">' +
                        '<a href="' + homeUrl + 'index.html#gifts">' +
                            '<span class="gifts-normal">Gifts</span>' +
                            '<span class="gifts-hover">coming soon</span>' +
                        '</a>' +
                    '</li>' +
                '</ul>' +
                '<!-- Hamburger Menu (Mobile) -->' +
                '<button class="menu-toggle" id="menuToggle" aria-label="Open menu">' +
                    '<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
                        '<line x1="3" y1="12" x2="21" y2="12"></line>' +
                        '<line x1="3" y1="6" x2="21" y2="6"></line>' +
                        '<line x1="3" y1="18" x2="21" y2="18"></line>' +
                    '</svg>' +
                '</button>' +
                '<!-- Center Logo -->' +
                '<a href="' + homeUrl + 'index.html#home" class="logo-container" aria-label="RUUT Home">' +
                    '<img class="logo-img" src="' + homeUrl + 'assets/images/ruut-logo-CF6400.png" alt="yourruut.com logo">' +
                '</a>' +
                '<!-- Right Navigation (Desktop) + Actions -->' +
                '<div class="header-actions">' +
                    '<ul class="nav-links right">' +
                        '<li><a href="' + homeUrl + 'about-us.html">About Us</a></li>' +
                        '<li><a href="' + homeUrl + 'my-account/">My Account</a></li>' +
                    '</ul>' +
                    '<div class="header-search-container">' +
                        '<input type="text" class="header-search-input" id="headerSearchInputField" placeholder="Search products..." autocomplete="off" style="width: 0; opacity: 0; pointer-events: none; border: none; background: transparent; padding: 4px 0; outline: none; font-family: var(--font-sans); font-size: 0.85rem; color: #3A2E28; transition: width 0.3s ease, opacity 0.3s ease; margin-right: 0;">' +
                        '<button id="searchButton" class="search-toggle-btn" aria-label="Search Products" style="background: none; border: none; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: rgb(58, 46, 40); margin-right: 15px; transition: opacity 0.2s;">' +
                            '<svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block;">' +
                                '<circle cx="11" cy="11" r="8"></circle>' +
                                '<line x1="21" y1="21" x2="16.65" y2="16.65"></line>' +
                            '</svg>' +
                        '</button>' +
                    '</div>' +
                    '<a href="' + homeUrl + 'cart" class="cart-icon" id="cartButton" aria-label="View Cart">' +
                        '<img class="cart-icon-img" src="' + homeUrl + 'assets/images/cart.png" alt="Cart" width="24" height="24">' +
                        '<span class="cart-badge" id="cartCount">0</span>' +
                    '</a>' +
                '</div>' +
            '</div>' +
        '</header>';

        var mobileDrawerHtml = '<div id="mobileDrawer" class="mobile-nav-drawer">' +
            '<button class="close-btn" id="closeDrawer" aria-label="Close menu">&times;</button>' +
            '<ul class="mobile-nav-links">' +
                '<li><a href="' + homeUrl + 'index.html#home">Home</a></li>' +
                '<li><a href="' + homeUrl + 'category.html?category=airmist">Air Mist</a></li>' +
                '<li><a href="' + homeUrl + 'category.html?category=diffuser">Diffuser</a></li>' +
                '<li><a href="' + homeUrl + 'index.html#gifts">Gifts</a></li>' +
                '<li><a href="' + homeUrl + 'about-us.html">About Us</a></li>' +
                '<li><a href="' + homeUrl + 'my-account/">My Account</a></li>' +
            '</ul>' +
        '</div>' +
        '<div class="mobile-nav-overlay" id="mobileNavOverlay"></div>';

        $('body').prepend(headerHtml);
        $('body').append(mobileDrawerHtml);

        // Inject the custom footer on My Account/Cart pages
        if ($('footer#mainFooter').length === 0) {
            var footerHtml = '<footer id="mainFooter">' +
                '<div class="container">' +
                  '<div class="footer-grid">' +
                    '<!-- Col 1 -->' +
                    '<div class="footer-col">' +
                      '<h3>Useful Links</h3>' +
                      '<ul class="footer-links">' +
                        '<li><a href="' + homeUrl + 'about-us">About Us</a></li>' +
                        '<li><a href="' + homeUrl + '#contact">Contact Us</a></li>' +
                        '<li><a href="' + homeUrl + 'my-account/">My account</a></li>' +
                        '<li><a href="' + homeUrl + '#faqSection">FAQs</a></li>' +
                      '</ul>' +
                    '</div>' +
                    '<!-- Col 2 -->' +
                    '<div class="footer-col">' +
                      '<h3>Categories</h3>' +
                      '<ul class="footer-links">' +
                        '<li><a href="' + homeUrl + 'category.html?category=airmist">Collection</a></li>' +
                        '<li><a href="' + homeUrl + '#gifts">Gifts</a></li>' +
                      '</ul>' +
                    '</div>' +
                    '<!-- Col 3 -->' +
                    '<div class="footer-col">' +
                      '<h3>Contact Us</h3>' +
                      '<p>+91 98985 71044</p>' +
                      '<p><a href="mailto:connect@yourruut.com">connect@yourruut.com</a></p>' +
                      '<div class="footer-socials">' +
                        '<a href="https://www.instagram.com/your.ruut?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==" target="_blank" aria-label="Instagram">' +
                          '<svg class="social-icon" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" /></svg>' +
                        '</a>' +
                        '<a href="https://wa.me/919898571044" target="_blank" aria-label="WhatsApp">' +
                          '<svg class="social-icon" viewBox="0 0 448 512"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L3 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z" /></svg>' +
                        '</a>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                  '<div class="footer-bottom">' +
                    '<span class="footer-bottom-brand">ṚUUT</span>' +
                    '<span class="copyright">Copyright &copy; 2026 yourruut.com</span>' +
                  '</div>' +
                '</div>' +
              '</footer>';
            $('body').append(footerHtml);
        }


        // Bind Search toggle and search execution on WooCommerce page
        $(document).on('click', '#searchButton', function(e) {
            e.stopPropagation();
            var $container = $('.header-search-container');
            var $input = $('#headerSearchInputField');
            var isActive = $container.hasClass('active');
            if (!isActive) {
                $container.addClass('active');
                $input.css({
                    'width': '160px',
                    'opacity': 1,
                    'pointer-events': 'auto',
                    'margin-right': '8px',
                    'padding-left': '4px'
                });
                setTimeout(function() { $input.focus(); }, 100);
            } else {
                var query = $input.val().trim();
                if (query === '') {
                    $container.removeClass('active');
                    $input.css({
                        'width': '0',
                        'opacity': 0,
                        'pointer-events': 'none',
                        'margin-right': '0',
                        'padding-left': '0'
                    });
                } else {
                    // Redirect to category search page
                    window.location.href = homeUrl + 'category.html?search=' + encodeURIComponent(query);
                }
            }
        });

        $(document).on('keydown', '#headerSearchInputField', function(e) {
            if (e.key === 'Enter') {
                var query = $(this).val().trim();
                if (query !== '') {
                    window.location.href = homeUrl + 'category.html?search=' + encodeURIComponent(query);
                }
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.header-search-container').length) {
                var $container = $('.header-search-container');
                var $input = $('#headerSearchInputField');
                $container.removeClass('active');
                $input.css({
                    'width': '0',
                    'opacity': 0,
                    'pointer-events': 'none',
                    'margin-right': '0',
                    'padding-left': '0'
                });
            }
        });

        // Bind Mobile Drawer events
        $(document).on('click', '#menuToggle', function(e) {
            e.preventDefault();
            $('#mobileDrawer').addClass('open');
            $('#mobileNavOverlay').addClass('open');
            $('body').css('overflow', 'hidden');
        });

        $(document).on('click', '#closeDrawer, #mobileNavOverlay, .mobile-nav-links a', function(e) {
            $('#mobileDrawer').removeClass('open');
            $('#mobileNavOverlay').removeClass('open');
            $('body').css('overflow', '');
        });

        // Dynamic Cart Count Loader
        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: { action: 'ruut_get_cart' },
            success: function(response) {
                if (response.success && response.data.items) {
                    var totalQty = 0;
                    $.each(response.data.items, function(i, item) {
                        totalQty += item.quantity;
                    });
                    $('#cartCount').text(totalQty);
                }
            }
        });

        // --- INJECT TRACK ORDER BUTTONS ON ACCOUNT ORDERS TAB ---
        var injectTrackButtons = function() {
            $('.woocommerce-orders-table__row, .my_account_orders tbody tr').each(function() {
                var $row = $(this);
                var orderId = '';
                
                // 1. Try to find the Order ID from the Order Number cell
                var $numCell = $row.find('.woocommerce-orders-table__cell-order-number, .order-number');
                if ($numCell.length > 0) {
                    orderId = $numCell.text().replace('#', '').trim();
                }
                
                // 2. Fallback to parsing from link href
                if (!orderId) {
                    var href = $row.find('a').attr('href');
                    if (href) {
                        var matches = href.match(/view-order\/(\d+)/) || href.match(/id=(\d+)/);
                        if (matches && matches[1]) {
                            orderId = matches[1];
                        }
                    }
                }
                
                if (orderId) {
                    var $actionsCell = $row.find('.woocommerce-orders-table__cell-order-actions, .order-actions');
                    if ($actionsCell.length > 0 && $actionsCell.find('.ruut-track-order-btn').length === 0) {
                        var trackUrl = homeUrl + 'track.html?order_id=' + orderId;
                        var $btn = $('<a href="' + trackUrl + '" class="button ruut-track-order-btn" style="margin-left: 8px; background-color: var(--ruut-accent, #895158) !important; border-color: var(--ruut-accent, #895158) !important; color: white !important; padding: 5px 12px !important; font-size: 11px !important; font-weight: 500 !important; text-transform: uppercase !important; letter-spacing: 1px !important; display: inline-block !important; text-align: center !important;">Track</a>');
                        $actionsCell.append($btn);
                    }
                }
            });
        };
        
        injectTrackButtons();
        $(document).ajaxComplete(function() {
            injectTrackButtons();
        });
    }

    if ($('form.woocommerce-checkout').length === 0) return;

    // --- INJECT DYNAMIC CSS FIXES & CARD STYLES ---
    if ($('#ruut-dynamic-checkout-css').length === 0) {
        var customStyles = '<' + 'style id="ruut-dynamic-checkout-css">' +
            'html { overflow-y: scroll !important; overflow-x: hidden !important; } ' + 
            '.woocommerce-error, .woocommerce-error li, .woocommerce-invalid span.error, .woocommerce-invalid label.error, .woocommerce-checkout-review-order .woocommerce-error, ul.woocommerce-error li, .woocommerce-notices-wrapper, .woocommerce-NoticeGroup, .woocommerce-NoticeGroup-checkout { display: none !important; } ' + 
            '.ruut-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(74, 51, 36, 0.35); backdrop-filter: blur(5px); z-index: 999999; display: flex; justify-content: center; align-items: center; opacity: 0; visibility: hidden; transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); } ' +
            '.ruut-modal-overlay.active { opacity: 1; visibility: visible; } ' +
            '.ruut-modal-box { background: var(--ruut-light-bg, #F1ECE6); padding: 45px 35px 35px 35px; border: 1px solid var(--ruut-border, rgba(74, 51, 36, 0.15)); border-radius: 2px; max-width: 440px; width: 90%; text-align: center; position: relative; box-shadow: 0 20px 40px rgba(74, 51, 36, 0.12); transform: translateY(-20px) scale(0.98); transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1); } ' +
            '.ruut-modal-overlay.active .ruut-modal-box { transform: translateY(0) scale(1); } ' +
            '.ruut-modal-close { position: absolute; top: 12px; right: 18px; font-size: 30px; cursor: pointer; color: var(--ruut-coffee, #4A3324); line-height: 1; font-weight: 300; opacity: 0.6; transition: 0.3s ease; } ' +
            '.ruut-modal-close:hover { opacity: 1; } ' +
            '#ruut_coupon_modal_text { color: var(--ruut-coffee, #4A3324); font-family: sans-serif; font-size: 16px; margin: 0 0 30px 0; line-height: 1.5; font-weight: 500; font-style: normal; letter-spacing: 0.5px; } ' +
            '.ruut-modal-btn { background-color: var(--ruut-coffee, #4A3324) !important; color: #fff !important; padding: 15px 35px !important; text-decoration: none !important; font-family: sans-serif !important; font-size: 12px !important; font-weight: 600 !important; letter-spacing: 2px !important; text-transform: uppercase !important; border-radius: 0px !important; transition: all 0.3s ease; border: 1px solid var(--ruut-coffee, #4A3324); } ' +
            '.ruut-modal-btn:hover { background-color: transparent !important; color: var(--ruut-coffee, #4A3324) !important; } ' +
            '.woocommerce-checkout-review-order-table tr.cart-discount td a { display: none !important; } ' +
            '#ruut-dynamic-progress, .ruut-upsell-container { display: none !important; } ' +
            '.ruut-saved-addresses-container { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; width: 100%; order: 8; } ' +
            '.ruut-address-card { border: 1px solid rgba(59, 36, 28, 0.15); border-radius: 8px; padding: 12px; margin-bottom: 10px; cursor: pointer; transition: all 0.3s ease; position: relative; } ' +
            '.ruut-address-card:hover { border-color: var(--ruut-coffee, #3B241C); background-color: rgba(59, 36, 28, 0.03); } ' +
            '.ruut-address-card.active { border-color: var(--ruut-coffee, #3B241C); background-color: var(--ruut-coffee, #3B241C); color: var(--ruut-bg, #F3E8D0); } ' +
            '.ruut-address-card .card-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; } ' +
            '.ruut-address-card .card-body { font-size: 0.85rem; line-height: 1.3; opacity: 0.85; } ' +
            '.ruut-address-card.active strong, .ruut-address-card.active span { color: var(--ruut-bg, #F3E8D0) !important; } ' +
            '@media(max-width: 768px) { .ruut-address-card { width: 100%; } } ' +
            '</' + 'style>';
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
    var otpSending = false; // In-flight lock to prevent multiple OTP sends

    // --- GOOGLE SIGN-IN HANDLER FOR CHECKOUT ---
    window.handleGoogleCredentialResponse = window.handleGoogleCredentialResponse || function(response) {
        var $msg = $('#ruut_status_message');
        if ($msg.length === 0) {
            $msg = $('<div id="ruut_status_message" style="display:block; margin-top:15px; text-align:center;"></div>').appendTo('#custom_button_wrapper');
        }
        $msg.html('<span style="color:#5c4338;">Connecting to Google...</span>').slideDown();
        
        var nonce = (typeof ruut_account_ajax !== 'undefined' && ruut_account_ajax.google_nonce) ? ruut_account_ajax.google_nonce : '';
        
        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: {
                action: 'ruut_google_login',
                credential: response.credential,
                security: nonce
            },
            success: function(res) {
                if (res.success) {
                    $msg.html('<span style="color:green;">Authenticated! Logging you in...</span>').slideDown();
                    window.location.reload();
                } else {
                    $msg.html('<span style="color:red;">Google Login Failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown error') + '</span>').slideDown();
                }
            },
            error: function() {
                $msg.html('<span style="color:red;">Network error during Google validation. Please try again.</span>').slideDown();
            }
        });
    };

    function ruut_init_google_checkout() {
        var clientId = (typeof ruut_account_ajax !== 'undefined' && ruut_account_ajax.google_client_id) ? ruut_account_ajax.google_client_id : '';
        if (!clientId || $('body').hasClass('logged-in')) return;

        if ($('#custom_button_wrapper').length > 0 && $('#ruut_google_checkout_container').length === 0) {
            var googleHTML = '<div class="ruut-google-login-separator" id="ruut_google_checkout_sep">' +
                             '<span>OR</span>' +
                             '</div>' +
                             '<div class="ruut-google-login-button-container" id="ruut_google_checkout_container">' +
                             '<div id="ruut_google_checkout_btn"></div>' +
                             '</div>';
            $('#custom_button_wrapper').append(googleHTML);
        }

        if (window.google && window.google.accounts && window.google.accounts.id) {
            try {
                window.google.accounts.id.initialize({
                    client_id: clientId,
                    callback: window.handleGoogleCredentialResponse,
                    auto_select: false,
                    itp_support: true
                });
                var container = document.getElementById('ruut_google_checkout_btn');
                if (container && container.children.length === 0) {
                    window.google.accounts.id.renderButton(container, {
                        type: 'standard',
                        shape: 'rectangular',
                        theme: 'outline',
                        text: 'signin_with',
                        size: 'large',
                        logo_alignment: 'left',
                        width: 364
                    });
                }
            } catch(e) {
                console.error('Ruut Google Checkout render error:', e);
            }
        } else {
            setTimeout(ruut_init_google_checkout, 300);
        }
    }

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

        var first = addr.first_name || addr.billing_first_name || fuzzyExtract(addr, ['first', 'fname']) || '';
        var last  = addr.last_name || addr.billing_last_name || fuzzyExtract(addr, ['last', 'lname']) || '';
        var phone = addr.phone || addr.billing_phone || fuzzyExtract(addr, ['phone', 'mobile', 'tel']) || '';

        var addr1 = addr.billing_address_1 || addr.address_1 || fuzzyExtract(addr, ['address_1', 'address1', 'street1', 'add1']);
        if (!addr1) addr1 = fuzzyExtract(addr, ['address', 'street']);
        
        var addr2 = addr.billing_address_2 || addr.address_2 || fuzzyExtract(addr, ['address_2', 'address2', 'street2', 'add2']);
        var city  = addr.billing_city || addr.city || fuzzyExtract(addr, ['city', 'town', 'district']);
        var pin   = addr.billing_postcode || addr.postcode || fuzzyExtract(addr, ['pin', 'zip', 'post']);
        var st    = addr.billing_state || addr.state || fuzzyExtract(addr, ['state', 'region', 'prov']);
        var mark  = addr.billing_landmark || addr.landmark || fuzzyExtract(addr, ['land', 'mark']);
        var save  = addr.save_as || addr.title || fuzzyExtract(addr, ['save', 'title', 'alias']) || key || 'Saved Address';

        return {
            first: first, last: last, phone: phone,
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

    // --- DEFINE GLOBAL ADDRESS MODAL CRUD FUNCTIONS ---
    window.ruutOpenAddressModal = function() {
        $('#ruut_addr_id').val('');
        $('#ruut_addr_title').val('');
        $('#ruut_addr_postcode').val('');
        $('#ruut_addr_city').val('');
        $('#ruut_addr_state').val('');
        $('#ruut_addr_1').val('');
        $('#ruut_addr_2').val('');
        $('#ruut_addr_landmark').val('');
        $('#ruut-address-modal-title').text('Add New Address');
        $('#ruut-address-modal').addClass('active');
        $('#ruut-address-feedback').empty().hide();
    };

    window.ruutCloseAddressModal = function() {
        $('#ruut-address-modal').removeClass('active');
    };

    window.ruutEditAddress = function(addressJson) {
        try {
            var address = typeof addressJson === 'string' ? JSON.parse(addressJson) : addressJson;
            $('#ruut_addr_id').val(address.id || '');
            $('#ruut_addr_title').val(address.title || address.billing_save_as || '');
            $('#ruut_addr_postcode').val(address.postcode || address.billing_postcode || '');
            $('#ruut_addr_city').val(address.city || address.billing_city || '');
            
            var stateVal = address.state || address.billing_state || '';
            $('#ruut_addr_state').val(stateVal);
            
            $('#ruut_addr_1').val(address.address_1 || address.billing_address_1 || '');
            $('#ruut_addr_2').val(address.address_2 || address.billing_address_2 || '');
            $('#ruut_addr_landmark').val(address.landmark || address.billing_landmark || '');
            
            $('#ruut-address-modal-title').text('Edit Address');
            $('#ruut-address-modal').addClass('active');
            $('#ruut-address-feedback').empty().hide();
        } catch(e) {
            console.error("Error parsing address JSON:", e);
        }
    };

    window.ruutDeleteAddress = function(id) {
        if (confirm("Are you sure you want to delete this address?")) {
            $.post(ajaxUrl, {
                action: 'ruut_delete_address',
                id: id
            }, function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert("Failed to delete address.");
                }
            });
        }
    };

    // Bind AJAX Form Submission for Saved Addresses modal
    $(document).on('submit', '#ruut-address-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $feedback = $('#ruut-address-feedback');
        var $btn = $('#ruut_save_addr_btn');
        
        $btn.prop('disabled', true).text('Saving...');
        $feedback.removeClass('error success').text('Saving address...').show();
        
        var data = $form.serialize();
        data += '&action=ruut_save_address';
        
        $.post(ajaxUrl, data, function(response) {
            $btn.prop('disabled', false).text('Save Address');
            if (response.success) {
                $feedback.addClass('success').text('Address saved successfully! Reloading...');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            } else {
                $feedback.addClass('error').text(response.data && response.data.message ? response.data.message : 'Failed to save address.');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Save Address');
            $feedback.addClass('error').text('Network error. Please try again.');
        });
    });

    $(document).on('click', '.ruut-edit-address-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var key = $(this).data('key');
        if (window.ruutAddressBook && window.ruutAddressBook[key]) {
            var addr = window.ruutAddressBook[key];
            var data = extractAddressData(addr, key);
            
            // Prefill checkout billing fields
            $('#billing_first_name').val(data.first);
            $('#billing_last_name').val(data.last);
            $('#billing_phone').val(data.phone);
            $('#billing_address_1').val(data.addr1);
            $('#billing_address_2').val(data.addr2 || '');
            $('#billing_city').val(data.city);
            $('#billing_postcode').val(data.pin);
            $('#billing_landmark').val(data.mark);
            $('#billing_save_as').val(data.save);
            
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
            
            $('.ruut-address-card').removeClass('active');
            
            // Set selection status
            $('#ruut_selected_address').val('new');
            $('#ruut_editing_address_id').val(addr.id || key);
            
            // Show all inputs
            var fieldsToToggle = $('#billing_postcode_field, #billing_city_field, #billing_state_field, #billing_address_1_field, #billing_address_2_field, #billing_landmark_field, #billing_save_as_field');
            fieldsToToggle.slideDown(300);
            
            // Bulletproof: Force Address Line 2 field to display block and make input editable
            $('#billing_address_2_field').show().css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });
            $('#billing_address_2').prop('disabled', false).prop('readonly', false).show();
            
            // Scroll to form fields
            $('html, body').animate({
                scrollTop: ($('#billing_first_name_field').offset().top - 120)
            }, 500);
            setTimeout(function() {
                $('#billing_first_name').focus();
            }, 400);
        }
    });
    function ruut_build_layout() {
        var wrapper = $('.woocommerce-billing-fields__field-wrapper');
        
        // --- INJECT DYNAMIC ADDRESS LINE 2 IF MISSING ---
        if ($('#billing_address_2').length === 0 && $('#billing_address_1_field').length > 0) {
            var addr2_html = '<p class="form-row form-row-wide address-field" id="billing_address_2_field" data-priority="85">' +
                             '<label for="billing_address_2">Address Line 2 (Optional)</label>' +
                             '<span class="woocommerce-input-wrapper">' +
                             '<input type="text" class="input-text" name="billing_address_2" id="billing_address_2" placeholder="Apartment, Suite, Unit, etc." value="" autocomplete="address-line2">' +
                             '</span></p>';
            $('#billing_address_1_field').after(addr2_html);
        }
        
        // --- INJECT HIDDEN ADDRESS TRACKER FOR BACKEND INTERCEPT ---
        if ($('#ruut_selected_address').length === 0) {
            wrapper.append('<input type="hidden" name="ruut_selected_address" id="ruut_selected_address" value="new">');
        }
        if ($('#ruut_editing_address_id').length === 0) {
            wrapper.append('<input type="hidden" name="ruut_editing_address_id" id="ruut_editing_address_id" value="">');
        }
        
        if ($('#custom_contacts_wrapper').length === 0) {
            wrapper.prepend('<div id="custom_contacts_wrapper"><h3>Customer details</h3></div>');
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
                    
                    var lines = [];
                    if (data.first || data.last) {
                        lines.push('<strong class="addr-name" style="display:block; margin-bottom: 2px; color:inherit; line-height:1.25;">' + data.first + ' ' + data.last + '</strong>');
                    }
                    if (data.phone) {
                        lines.push('<span class="addr-phone" style="display:block; margin-bottom: 4px; opacity:0.9; line-height:1.25;">Phone: ' + data.phone + '</span>');
                    }
                    if (data.addr1) {
                        lines.push('<div style="margin-bottom: 2px; line-height:1.3;">' + data.addr1 + '</div>');
                    }
                    if (data.addr2) {
                        lines.push('<div style="margin-bottom: 2px; line-height:1.3;">' + data.addr2 + '</div>');
                    }
                    if (data.mark) {
                        lines.push('<div style="margin-bottom: 2px; line-height:1.3;">Landmark: ' + data.mark + '</div>');
                    }
                    if (data.city || data.st || data.pin) {
                        lines.push('<div style="margin-bottom: 0px; line-height:1.3;">' + data.city + ', ' + data.st + ' ' + data.pin + '</div>');
                    }
                    
                    var preview = lines.join('');
                    
                    // Render premium card header with a Pen/Pencil Edit Option
                    buttonsHtml += '<div class="ruut-address-card' + defaultClass + '" data-key="' + key + '">' +
                                   '<div class="ruut-address-card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 4px;">' +
                                   '<strong class="card-title" style="margin-bottom:0; font-size:14px; font-weight:700;">' + data.save + '</strong>' +
                                   '<button type="button" class="ruut-edit-address-btn" data-key="' + key + '" style="background:none; border:none; color:inherit; font-size:11px; font-weight:700; text-transform: uppercase; letter-spacing:0.5px; cursor:pointer; padding:0; line-height:1; display:flex; align-items:center; opacity:0.85;">' +
                                   '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg> Edit</button>' +
                                   '</div>' +
                                   '<div class="card-body" style="font-size:0.85rem; line-height:1.3; opacity:0.9;">' + preview + '</div>' +
                                   '</div>';
                });
                
                // Add New Address Card
                buttonsHtml += '<div class="ruut-address-card ruut-add-new-card" data-key="new" onclick="ruutOpenAddressModal()">' +
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
                ruut_init_google_checkout();
            }
        }
        
        ruut_enforce_email_lock();
        ruut_enforce_place_order_lock();
    }
    
    // --- HIGHLY ROBUST CARD MAPPING & VISIBILITY TOGGLE ---
    $(document).on('click', '.ruut-address-card', function() {
        var $btn = $(this);
        if ($btn.hasClass('ruut-add-new-card')) return;
        var key = $btn.data('key');
        var fieldsToToggle = $('#billing_postcode_field, #billing_city_field, #billing_state_field, #billing_address_1_field, #billing_address_2_field, #billing_landmark_field, #billing_save_as_field');

        $('.ruut-address-card').removeClass('active');
        $btn.addClass('active');

        // Reset editing ID to blank since they are selecting a saved card directly (no edits)
        $('#ruut_editing_address_id').val('');
        
        // 1. DUAL BACKEND INTERCEPT: Update hidden tracking field for the server
        $('#ruut_selected_address').val(key);

        if (key === 'new') {
            // Scenario B: Show fields for new address entry
            fieldsToToggle.slideDown(300);
            
            // Bulletproof: Force Address Line 2 field to display block and make input editable
            $('#billing_address_2_field').show().css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });
            $('#billing_address_2').prop('disabled', false).prop('readonly', false).show();
            
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
            $('.woocommerce-checkout-review-order-table th.product-name').html('<span class="ruut-order-heading-text">Your order</span> <a href="' + homeUrl + 'cart" class="ruut-inline-edit-cart">Edit cart</a>');
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
                '<a href="' + homeUrl + 'cart" id="ruut_coupon_modal_link" class="ruut-modal-btn">GO TO CART</a>' +
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
            url: typeof wc_checkout_params !== 'undefined' ? wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' ) : ajaxUrl + '?action=apply_coupon',
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

    // Force checkout update when payment method changes to recalculate online payment discount instantly
    $(document).on('change', 'input[name="payment_method"]', function() {
        $('body').trigger('update_checkout');
    });

    $(document).ajaxComplete(function() {
        ruut_enforce_email_lock();
        ruut_enforce_place_order_lock();
    });

    $(document.body).on('checkout_error', function() {
        // Instantly delete error notices from DOM so they are never visible
        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error').remove();

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
        var $invalidField = $('.woocommerce-invalid:visible').first();
        if ($invalidField.length) {
            $('html, body').animate({
                scrollTop: ($invalidField.offset().top - 150)
            }, 600);
        }
    });

    $(document).on('keypress', '#billing_email, #ruut_otp_input', function(e) {
        if (e.which === 13) {
            e.preventDefault(); 
            if (!otpSending) {
                $('#ruut_action_btn').click(); 
            }
        }
    });

    $(document).on('click', '#ruut_resend_otp_btn', function(e) {
        e.preventDefault();
        if (otpSending) return; // Block duplicate resend clicks
        var emailVal = $('#billing_email').val();
        var $msg = $('#ruut_status_message');
        var $resendBtn = $(this);
        
        otpSending = true;
        $resendBtn.css({'pointer-events': 'none', 'opacity': '0.5'});
        $msg.html('<span style="color:#5c4338;">Resending code...</span>').slideDown();
        
        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: { action: 'ruut_send_otp', email: emailVal },
            success: function(response) {
                if (response.success) {
                    $msg.html('<span style="color:#5c4338;">' + response.data.message + '</span>').slideDown();
                    startResendTimer(); 
                } else {
                    $msg.html('<span style="color:red;">' + response.data.message + '</span>').slideDown();
                    $resendBtn.css({'pointer-events': 'auto', 'opacity': '1'});
                }
            },
            error: function() {
                $msg.html('<span style="color:red;">Network error. Please try again.</span>').slideDown();
                $resendBtn.css({'pointer-events': 'auto', 'opacity': '1'});
            },
            complete: function() {
                otpSending = false;
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
        if (otpSending) return; // Block duplicate clicks while AJAX is in-flight
        var emailVal = $('#billing_email').val();
        var $btn = $(this);
        var $msg = $('#ruut_status_message');
        var $wrapper = $('.woocommerce-billing-fields__field-wrapper');

        if (step === 'email') {
            if (emailVal.trim() === '' || !emailVal.includes('@')) {
                $msg.html('<span style="color:red;">Please enter a valid email.</span>').slideDown();
                return;
            }

            otpSending = true;
            $btn.text('SENDING...').css({'pointer-events': 'none', 'opacity': '0.6'});

            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: { action: 'ruut_send_otp', email: emailVal },
                success: function(response) {
                    if (response.success) {
                        step = 'otp';
                        ruut_enforce_email_lock();
                        
                        $('#ruut_otp_wrapper').slideDown().addClass('show-ruut-otp'); 
                        
                        $msg.html('<span style="color:#5c4338;">' + response.data.message + '</span>').slideDown();
                        $btn.text('VERIFY OTP').css({'pointer-events': 'auto', 'opacity': '1'});
                        
                        startResendTimer(); 
                        
                        setTimeout(function(){ 
                            $('#ruut_otp_input').focus(); 
                            renderOtpBoxes(); 
                        }, 100);
                    } else {
                        $msg.html('<span style="color:red;">' + response.data.message + '</span>').slideDown();
                        $btn.text('GET OTP').css({'pointer-events': 'auto', 'opacity': '1'});
                    }
                },
                error: function() {
                    $msg.html('<span style="color:red;">Network error. Please try again.</span>').slideDown();
                    $btn.text('GET OTP').css({'pointer-events': 'auto', 'opacity': '1'});
                },
                complete: function() {
                    otpSending = false;
                }
            });

        } else if (step === 'otp') {
            var otpVal = $('#ruut_otp_input').val().replace(/\s/g, ''); 
            
            if (otpVal.length < 6) {
                $msg.html('<span style="color:red;">Please enter the full 6-digit OTP.</span>').slideDown();
                return;
            }

            otpSending = true;
            $btn.text('VERIFYING...').css({'pointer-events': 'none', 'opacity': '0.6'});

            $.ajax({
                type: 'POST',
                url: ajaxUrl,
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
                            $('#ruut_otp_wrapper, #ruut_google_checkout_sep, #ruut_google_checkout_container').remove(); 
                            $wrapper.addClass('ruut-fields-revealed');
                        }
                    } else {
                        $msg.html('<span style="color:red;">' + response.data.message + '</span>').slideDown();
                        $btn.text('VERIFY OTP').css({'pointer-events': 'auto', 'opacity': '1'});
                    }
                },
                error: function() {
                    $msg.html('<span style="color:red;">Network error. Please try again.</span>').slideDown();
                    $btn.text('VERIFY OTP').css({'pointer-events': 'auto', 'opacity': '1'});
                },
                complete: function() {
                    otpSending = false;
                }
            });
        }
    });

    // --- GIFTS NAV ITEM COMING SOON ---
    $(document).on('mouseenter', 'a[href*="gifts"]', function() {
        var $el = $(this);
        if (!$el.data('originalText')) {
            $el.data('originalText', $el.text());
        }
        $el.text('Coming Soon').css('color', 'var(--ruut-accent)');
    }).on('mouseleave', 'a[href*="gifts"]', function() {
        var $el = $(this);
        $el.text($el.data('originalText') || 'Gifts').css('color', '');
    }).on('click', 'a[href*="gifts"]', function(e) {
        e.preventDefault();
        showCheckoutToast('Gifts Collection is Coming Soon!');
    });

    function showCheckoutToast(message) {
        var $toast = $('#ruut-toast');
        if ($toast.length === 0) {
            $toast = $('<div id="ruut-toast"></div>').appendTo('body');
            $('<' + 'style>' +
              '#ruut-toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px); background-color: var(--ruut-text, #4A3324); color: var(--ruut-white, #FFF); padding: 12px 28px; border-radius: 30px; font-family: var(--font-sans, sans-serif); font-size: 0.9rem; letter-spacing: 0.8px; z-index: 99999; box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.4s ease; }' +
              '#ruut-toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }' +
              '</' + 'style>').appendTo('head');
        }
        $toast.text(message).addClass('show');
        if ($toast.data('timeoutId')) {
            clearTimeout($toast.data('timeoutId'));
        }
        var timeoutId = setTimeout(function() {
            $toast.removeClass('show');
        }, 3000);
        $toast.data('timeoutId', timeoutId);
    }

    // --- DYNAMIC SUB-MENU LOADER FOR WOOCOMMERCE PAGES ---
    function populateCheckoutSubmenus(categories) {
        var list = categories || [];
        var airmistSub = list.filter(function(c) {
            return c.parent_slug === 'airmist' || c.parent_slug === 'air-mist' || c.parent_slug === 'air-mists';
        });
        var diffuserSub = list.filter(function(c) {
            return c.parent_slug === 'diffuser' || c.parent_slug === 'diffusers';
        });

        // Always fallback to Atlas Collection if empty to ensure hover works
        if (airmistSub.length === 0) {
            airmistSub.push({ slug: 'the-atlas-collection', title: 'The Atlas Collection' });
        }

        var $airmistMenu = $('#nav-submenu-airmist, #mobile-submenu-airmist');
        if ($airmistMenu.length > 0) {
            if (airmistSub.length > 0) {
                var html = airmistSub.map(function(c) {
                    return '<li><a href="' + homeUrl + 'collection.html?collection=' + c.slug + '">' + c.title + '</a></li>';
                }).join('');
                $airmistMenu.html(html);
                $airmistMenu.parent().addClass('nav-item-has-children');
            } else {
                $airmistMenu.html('');
                $airmistMenu.parent().removeClass('nav-item-has-children');
            }
        }

        var $diffuserMenu = $('#nav-submenu-diffuser, #mobile-submenu-diffuser');
        if ($diffuserMenu.length > 0) {
            if (diffuserSub.length > 0) {
                var html = diffuserSub.map(function(c) {
                    return '<li><a href="' + homeUrl + 'collection.html?collection=' + c.slug + '">' + c.title + '</a></li>';
                }).join('');
                $diffuserMenu.html(html);
                $diffuserMenu.parent().addClass('nav-item-has-children');
            } else {
                $diffuserMenu.html('');
                $diffuserMenu.parent().removeClass('nav-item-has-children');
            }
        }
    }

    function initCheckoutSubmenus() {
        var restUrl = homeUrl + 'wp-json/ruut/v1/collections';
        $.ajax({
            type: 'GET',
            url: restUrl,
            success: function(res) {
                var list = Array.isArray(res) ? res : [];
                populateCheckoutSubmenus(list);
            },
            error: function() {
                populateCheckoutSubmenus([
                    { slug: 'the-atlas-collection', title: 'The Atlas Collection', parent_slug: 'airmist' }
                ]);
            }
        });
    }

    // --- MY ACCOUNT / LOGIN PAGE OTP LOGIN ---
    var $usernameField = $('#username');
    var $passwordField = $('#password').closest('.form-row'); 
    var $loginForm = $('form.woocommerce-form-login');



    if ($loginForm.length > 0 && $('#send_otp_btn').length > 0) {
        $passwordField.hide();
        $loginForm.find('button[name="login"]').hide();
        $loginForm.find('.woocommerce-form-login__rememberme').hide();

        var otpLoginSending = false;
        var loginTimer;

        function startLoginResendTimer() {
            var time = 30;
            var $timer = $('#timer');
            var $resendLink = $('#resend_otp_link');
            
            $resendLink.addClass('disabled').html('Resend OTP in <span id="timer">' + time + '</span>s');
            
            clearInterval(loginTimer);
            loginTimer = setInterval(function() {
                time--;
                if (time <= 0) {
                    clearInterval(loginTimer);
                    $resendLink.removeClass('disabled').html('<a href="#" id="resend_login_otp_btn" style="color: var(--ruut-accent, #895158); text-decoration: underline;">Resend OTP</a>');
                } else {
                    $('#timer').text(time);
                }
            }, 1000);
        }

        // OTP input field focus jumping
        $(document).on('input', '.otp-input-group .otp-digit', function() {
            var $this = $(this);
            if ($this.val().length >= 1) {
                $this.next('.otp-digit').focus();
            }
            combineLoginOtp();
        });

        $(document).on('keydown', '.otp-input-group .otp-digit', function(e) {
            var $this = $(this);
            if (e.which === 8 && $this.val().length === 0) {
                $this.prev('.otp-digit').focus();
            }
        });

        function combineLoginOtp() {
            var otp = '';
            $('.otp-digit').each(function() {
                otp += $(this).val();
            });
            $('#otp_full').val(otp);
        }

        function sendLoginOtp(emailVal, $msg, $btn) {
            otpLoginSending = true;
            $msg.html('<span style="color:#4A3324;">Sending secure access code...</span>').slideDown();

            $.ajax({
                type: 'POST',
                url: ruut_ajax.url,
                data: {
                    action: 'ruut_request_otp',
                    identifier: emailVal,
                    security: ruut_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $loginForm.addClass('otp-active');
                        $('#otp_container').slideDown();
                        $btn.hide(); 
                        $msg.html('<span style="color:#4A3324;">' + response.data.message + '</span>').slideDown();
                        startLoginResendTimer();
                        setTimeout(function() {
                            $('.otp-digit').first().focus();
                        }, 100);
                    } else {
                        $msg.html('<span style="color:red;">' + response.data.message + '</span>').slideDown();
                        $btn.prop('disabled', false).text('Get OTP');
                    }
                },
                error: function() {
                    $msg.html('<span style="color:red;">Network error. Please try again.</span>').slideDown();
                    $btn.prop('disabled', false).text('Get OTP');
                },
                complete: function() {
                    otpLoginSending = false;
                }
            });
        }

        $(document).on('click', '#send_otp_btn', function(e) {
            e.preventDefault();
            if (otpLoginSending) return;
            var emailVal = $usernameField.val();
            var $msg = $('#otp_message');
            var $btn = $(this);

            if (!emailVal || !emailVal.includes('@')) {
                $msg.html('<span style="color:red;">Please enter a valid email address.</span>').slideDown();
                return;
            }

            $btn.prop('disabled', true).text('Sending...');
            sendLoginOtp(emailVal, $msg, $btn);
        });

        $(document).on('click', '#resend_login_otp_btn', function(e) {
            e.preventDefault();
            if (otpLoginSending) return;
            var emailVal = $usernameField.val();
            var $msg = $('#otp_message');
            sendLoginOtp(emailVal, $msg, $('#send_otp_btn'));
        });

        $(document).on('click', '#verify_otp_btn', function(e) {
            e.preventDefault();
            combineLoginOtp();
            var otpVal = $('#otp_full').val();
            var $msg = $('#otp_message');

            if (otpVal.length < 6) {
                $msg.html('<span style="color:red;">Please enter the full 6-digit access code.</span>').slideDown();
                return;
            }

            $('#password').val('dummy-otp-pass-123');
            $msg.html('<span style="color:#4A3324;">Verifying code...</span>').slideDown();
            $loginForm.submit();
        });
    }

    // Delay initialization slightly to guarantee DOM elements are ready
    setTimeout(initCheckoutSubmenus, 100);
});