document.addEventListener('DOMContentLoaded', () => {
  // --- STICKY HEADER ---
  const header = document.getElementById('mainHeader');
  const scrollThreshold = 50;

  const handleScroll = () => {
    if (window.scrollY > scrollThreshold) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  };

  window.addEventListener('scroll', handleScroll);
  // Initial check in case of page reload/restored scroll position
  handleScroll();

  // --- MOBILE NAV DRAWER ---
  const menuToggle = document.getElementById('menuToggle');
  const closeDrawer = document.getElementById('closeDrawer');
  const mobileDrawer = document.getElementById('mobileDrawer');
  const mobileDrawerLinks = mobileDrawer.querySelectorAll('a');

  const openDrawerMenu = () => {
    mobileDrawer.classList.add('open');
    document.body.style.overflow = 'hidden'; // Prevents background scroll
  };

  const closeDrawerMenu = () => {
    mobileDrawer.classList.remove('open');
    document.body.style.overflow = '';
  };

  if (menuToggle) menuToggle.addEventListener('click', openDrawerMenu);
  if (closeDrawer) closeDrawer.addEventListener('click', closeDrawerMenu);

  // Close drawer when clicking any link inside
  mobileDrawerLinks.forEach(link => {
    link.addEventListener('click', closeDrawerMenu);
  });

  // --- INTERACTION: CART DRAWER SYSTEM ---
  const cartCountEl = document.getElementById('cartCount');
  const cartDrawer = document.getElementById('astra-mobile-cart-drawer');
  const cartDrawerOverlay = document.getElementById('astra-cart-drawer-overlay');
  const cartButton = document.getElementById('cartButton');
  const closeCartDrawer = document.getElementById('closeCartDrawer');
  const cartItemsList = document.getElementById('cartItemsList');
  const ruutMinicartFill = document.getElementById('ruutMinicartFill');
  const ruutMinicartText = document.getElementById('ruutMinicartText');
  const ruutDot1 = document.getElementById('ruutDot1');
  const ruutDot2 = document.getElementById('ruutDot2');
  const cartSubtotalOriginal = document.getElementById('cartSubtotalOriginal');
  const cartSubtotal = document.getElementById('cartSubtotal');
  const cartUpsellSection = document.getElementById('cartUpsellSection');
  
  // Coupons Modal Selectors
  const exploreCouponsBtn = document.getElementById('exploreCouponsBtn');
  const closeCouponsModal = document.getElementById('closeCouponsModal');
  const couponsModalOverlay = document.getElementById('ruut-coupons-modal-overlay');
  const ruutConfirmOverlay = document.getElementById('ruut-confirm-overlay');
  const confirmCancelBtn = document.getElementById('confirmCancelBtn');
  const confirmAnywayBtn = document.getElementById('confirmAnywayBtn');
  const ruutCouponCodeInput = document.getElementById('ruut_coupon_code');

  // Active coupon state
  let activeCoupon = 'DEFAULT';
  try {
    activeCoupon = localStorage.getItem('ruut_coupon') || 'DEFAULT';
  } catch (e) {
    console.error('Failed to load coupon from localStorage', e);
  }
  let pendingCoupon = null;

  // Initial cart items (mocking the user's screenshot, loading from localStorage if present)
  let cart = [];
  try {
    const savedCart = localStorage.getItem('ruut_cart');
    if (savedCart) {
      cart = JSON.parse(savedCart);
    } else {
      cart = [
        {
          name: 'Pangong Tso Paradise',
          price: 650.00,
          originalPrice: 650.00,
          quantity: 1,
          image: 'pangong_tso_paradise.webp',
          volume: '200ml',
          sku: 'PAN-200',
          id: 2489,
          key: '3430095c577593aad3c39c701712bcfe'
        },
        {
          name: 'The Landour Morning',
          price: 520.00,
          originalPrice: 650.00,
          quantity: 1,
          image: 'the_landour_mornig.webp',
          volume: '200 ml',
          sku: 'LAN-200',
          id: 2130,
          key: 'f15d337c70078947cfe1b5d6f0ed3f13'
        }
      ];
    }
  } catch (e) {
    console.error('Failed to load cart from localStorage', e);
  }

  // Save Cart state helper
  const saveCartToStorage = () => {
    try {
      localStorage.setItem('ruut_cart', JSON.stringify(cart));
      localStorage.setItem('ruut_coupon', activeCoupon);
    } catch (e) {
      console.error('Failed to save cart to localStorage', e);
    }
  };

  // Open & Close Drawer
  const openCart = () => {
    if (cartDrawer) cartDrawer.classList.add('open');
    if (cartDrawerOverlay) cartDrawerOverlay.classList.add('open');
  };

  const closeCart = () => {
    if (cartDrawer) cartDrawer.classList.remove('open');
    if (cartDrawerOverlay) cartDrawerOverlay.classList.remove('open');
  };

  if (cartButton) {
    cartButton.addEventListener('click', (e) => {
      e.preventDefault();
      openCart();
    });
  }

  if (closeCartDrawer) closeCartDrawer.addEventListener('click', closeCart);
  if (cartDrawerOverlay) cartDrawerOverlay.addEventListener('click', closeCart);

  // Render Cart Items
  const renderCart = () => {
    if (!cartItemsList) return;
    
    // Clear list
    cartItemsList.innerHTML = '';
    
    let totalQuantity = 0;
    let originalSubtotal = 0;
    let discountedSubtotal = 0;

    cart.forEach((item, index) => {
      totalQuantity += item.quantity;
      originalSubtotal += item.originalPrice * item.quantity;
      discountedSubtotal += item.price * item.quantity;

      const itemHtml = `
        <div class="cart-item">
          <div class="cart-item-img-wrap">
            <img src="${item.image}" alt="${item.name}">
          </div>
          <div class="cart-item-details">
            <div class="cart-item-title-row">
              <h4 class="cart-item-name">${item.name}</h4>
              <div class="cart-item-price-wrap">
                ${item.price < item.originalPrice ? `<span class="cart-item-original-price">₹${(item.originalPrice * item.quantity).toFixed(2)}</span>` : ''}
                <span class="cart-item-price">₹${(item.price * item.quantity).toFixed(2)}</span>
              </div>
            </div>
            <span class="cart-item-volume">${item.volume}</span>
            <div class="cart-item-qty-row">
              <div class="qty-selector">
                <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)">&minus;</button>
                <span class="qty-val">${item.quantity}</span>
                <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)">&plus;</button>
              </div>
            </div>
          </div>
          <a role="button" href="#" class="cart-drawer-close" aria-label="Remove ${item.name} from cart" onclick="removeCartItem(${index}); return false;" style="font-size: 1.5rem; align-self: flex-start; margin-left: auto;">&times;</a>
        </div>
      `;
      cartItemsList.insertAdjacentHTML('beforeend', itemHtml);
    });

    // Update cart badge
    if (cartCountEl) {
      cartCountEl.textContent = totalQuantity;
      cartCountEl.classList.remove('pop');
      void cartCountEl.offsetWidth; // force reflow
      cartCountEl.classList.add('pop');
    }

    // Apply coupon calculation
    let discount = 0;
    if (cart.length > 0) {
      if (activeCoupon === 'DEFAULT') {
        discount = 100.00; // default discount from screenshot
      } else if (activeCoupon === 'TEST-12') {
        discount = 200.00; // ₹200 off
      } else if (activeCoupon === 'TEST-34') {
        discount = discountedSubtotal * 0.05; // 5% additional discount
      }
    }
    
    let finalDiscountedTotal = Math.max(0, discountedSubtotal - discount);

    // Update Subtotal Summary text
    if (cartSubtotalOriginal) {
      if (discount > 0 || discountedSubtotal < originalSubtotal) {
        cartSubtotalOriginal.style.display = 'inline';
        cartSubtotalOriginal.innerHTML = `₹${originalSubtotal.toFixed(2)}`;
      } else {
        cartSubtotalOriginal.style.display = 'none';
      }
    }

    if (cartSubtotal) {
      cartSubtotal.innerHTML = `₹${finalDiscountedTotal.toFixed(2)}`;
    }

    // Update Progress Bar (unlock ₹250 off when we have 3 distinct products in cart)
    const distinctCount = cart.length;
    let progressPercent = 0;
    let needed = 3 - distinctCount;

    if (distinctCount === 0) {
      progressPercent = 0;
      needed = 3;
    } else if (distinctCount === 1) {
      progressPercent = 33.3333;
      needed = 2;
    } else if (distinctCount === 2) {
      progressPercent = 66.6667;
      needed = 1;
    } else {
      progressPercent = 100;
      needed = 0;
    }

    if (ruutMinicartFill) ruutMinicartFill.style.width = `${progressPercent}%`;
    if (ruutDot1) {
      if (progressPercent >= 66.6667) {
        ruutDot1.classList.add('ruut-dot-reached');
        ruutDot1.style.backgroundColor = 'var(--ruut-accent)';
      } else {
        ruutDot1.classList.remove('ruut-dot-reached');
        ruutDot1.style.backgroundColor = 'var(--ruut-white)';
      }
    }
    if (ruutDot2) {
      if (progressPercent >= 100) {
        ruutDot2.classList.add('ruut-dot-reached');
        ruutDot2.style.backgroundColor = 'var(--ruut-accent)';
      } else {
        ruutDot2.classList.remove('ruut-dot-reached');
        ruutDot2.style.backgroundColor = 'var(--ruut-white)';
      }
    }
    
    if (ruutMinicartText) {
      if (needed > 0) {
        ruutMinicartText.innerHTML = `Add <strong>${needed}</strong> more to unlock <strong>₹250</strong> off.`;
      } else {
        ruutMinicartText.innerHTML = `<strong>Congratulations! ₹250 off has been unlocked.</strong>`;
      }
    }

    // Show/Hide upsell card if "The Street of Kannauj" is already in cart
    const hasKannauj = cart.some(item => item.name === 'The Street of Kannauj');
    if (cartUpsellSection) {
      cartUpsellSection.style.display = hasKannauj ? 'none' : 'block';
    }

    // Save cart state
    saveCartToStorage();
  };

  // Update Item Quantity
  window.updateQty = (index, delta) => {
    cart[index].quantity += delta;
    if (cart[index].quantity <= 0) {
      cart.splice(index, 1);
    }
    renderCart();
  };

  // Remove Item
  window.removeCartItem = (index) => {
    cart.splice(index, 1);
    renderCart();
  };

  // Add Item to Cart
  window.addToCart = (productName, price) => {
    // Determine details
    let image = 'pangong_tso_paradise.webp';
    let originalPrice = price;
    let volume = '200ml';
    let sku = 'PAN-200';
    let id = 2489;
    let key = '3430095c577593aad3c39c701712bcfe';

    if (productName === 'The Landour Morning') {
      image = 'the_landour_mornig.webp';
      originalPrice = 650.00; // Match original price from screenshot
      volume = '200 ml';
      sku = 'LAN-200';
      id = 2130;
      key = 'f15d337c70078947cfe1b5d6f0ed3f13';
    } else if (productName === 'The Street of Kannauj') {
      image = 'the_street_of kannuj.webp';
      originalPrice = 650.00;
      volume = '200ml';
      sku = 'KAN-200';
      id = 2490;
      key = 'e871db68a735a947cfe1b5d6f0ed3f13';
    }

    // Check if product is already in cart
    const existingIndex = cart.findIndex(item => item.name === productName);
    if (existingIndex > -1) {
      cart[existingIndex].quantity += 1;
    } else {
      cart.push({
        name: productName,
        price: price,
        originalPrice: originalPrice,
        quantity: 1,
        image: image,
        volume: volume,
        sku: sku,
        id: id,
        key: key
      });
    }

    renderCart();
    openCart();
    window.showToast(`Added ${productName} to cart`);
    
    console.log(`[RUUT] Added "${productName}" to cart (₹${price.toFixed(2)})`);
  };

  // Add Upsell Item
  window.addUpsellToCart = () => {
    window.addToCart('The Street of Kannauj', 650.00);
  };

  // Coupons modal interactions
  if (exploreCouponsBtn) {
    exploreCouponsBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (couponsModalOverlay) couponsModalOverlay.classList.add('open');
    });
  }

  if (closeCouponsModal) {
    closeCouponsModal.addEventListener('click', () => {
      if (couponsModalOverlay) couponsModalOverlay.classList.remove('open');
    });
  }

  // Toast helper
  window.showToast = (message) => {
    let toast = document.getElementById('ruut-toast-notif');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'ruut-toast-notif';
      toast.className = 'ruut-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  };

  // Buy Now helper
  window.buyNow = (productName, price) => {
    window.addToCart(productName, price);
    openCart();
  };

  // Apply Coupon code logic
  window.tryApplyCoupon = (code) => {
    let newDiscount = 0;
    let oldDiscount = 0;

    // Calculate current discount
    let discountedSubtotal = cart.reduce((acc, item) => acc + item.price * item.quantity, 0);
    if (activeCoupon === 'DEFAULT') oldDiscount = 100.00;
    else if (activeCoupon === 'TEST-12') oldDiscount = 200.00;
    else if (activeCoupon === 'TEST-34') oldDiscount = discountedSubtotal * 0.05;

    // Calculate new discount
    if (code === 'TEST-12') newDiscount = 200.00;
    else if (code === 'TEST-34') newDiscount = discountedSubtotal * 0.05;

    // Loss prevention check: if new discount is LOWER than old discount, warn the user
    if (newDiscount < oldDiscount && activeCoupon !== 'DEFAULT') {
      pendingCoupon = code;
      if (ruutConfirmOverlay) ruutConfirmOverlay.style.display = 'flex';
    } else {
      activeCoupon = code;
      pendingCoupon = null;
      if (couponsModalOverlay) couponsModalOverlay.classList.remove('open');
      renderCart();
      window.showToast(`Applied coupon: ${code}`);
    }
  };

  // Confirm Overlay Actions
  if (confirmCancelBtn) {
    confirmCancelBtn.addEventListener('click', () => {
      pendingCoupon = null;
      if (ruutConfirmOverlay) ruutConfirmOverlay.style.display = 'none';
    });
  }

  if (confirmAnywayBtn) {
    confirmAnywayBtn.addEventListener('click', () => {
      if (pendingCoupon) {
        activeCoupon = pendingCoupon;
        pendingCoupon = null;
      }
      if (ruutConfirmOverlay) ruutConfirmOverlay.style.display = 'none';
      if (couponsModalOverlay) couponsModalOverlay.classList.remove('open');
      renderCart();
      window.showToast(`Applied coupon: ${activeCoupon}`);
    });
  }

  // Textbox Apply action
  window.applyCouponCode = () => {
    if (!ruutCouponCodeInput) return;
    const value = ruutCouponCodeInput.value.trim().toUpperCase();
    if (value === 'TEST-12' || value === 'TEST-34') {
      window.tryApplyCoupon(value);
      ruutCouponCodeInput.value = '';
    } else {
      alert('Invalid Promo Code. Try "TEST-12" or "TEST-34"');
    }
  };

  // Initialize Cart on Load
  renderCart();

  // --- SCROLL ENTRANCE ANIMATIONS (IntersectionObserver) ---
  const animateOnScroll = () => {
    const observerOptions = {
      root: null, // Viewport
      threshold: 0.15, // Trigger when 15% of element is visible
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate');
          // Once animated, we don't need to observe it anymore
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    // Observe hero products
    const heroProducts = document.querySelectorAll('.hero-product');
    heroProducts.forEach(el => observer.observe(el));

    // Observe other elements we want to fade/slide in
    const fadeElements = document.querySelectorAll('.product-card, .favorite-item, .badge-item');
    fadeElements.forEach(el => {
      // Add initial styling class via JS so it doesn't break if JS is disabled
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
      observer.observe(el);
    });

    // Handle intersection changes for custom styles
    const heroProduct = document.querySelector('.hero-product');
    if (heroProduct) {
      observer.observe(heroProduct);
    }
  };

  // Add customized animation styles on intersection
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, { threshold: 0.1 });

  const fadeElements = document.querySelectorAll(
    '.product-card, .favorite-item, .badge-item, .section-title, .ingredients-text h2, .founder-bio, .founder-img-wrap, .q-column'
  );
  fadeElements.forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(25px)';
    el.style.transition = 'opacity 0.8s cubic-bezier(0.25, 0.8, 0.25, 1), transform 0.8s cubic-bezier(0.25, 0.8, 0.25, 1)';
    observer.observe(el);
  });

  // Animate hero items directly on load
  const heroProducts = document.querySelectorAll('.hero-product');
  heroProducts.forEach((el, index) => {
    setTimeout(() => {
      el.classList.add('animate');
    }, 150 * (index + 1));
  });

  // --- TESTIMONIAL CAROUSEL (Infinite Scroll) ---
  const track = document.getElementById('testimonialsTrack');
  const dotsContainer = document.getElementById('testimonialDots');

  if (track) {
    const originalCards = Array.from(track.querySelectorAll('.testimonial-card'));
    const totalOriginal = originalCards.length;
    let visibleCount = window.innerWidth <= 768 ? 1 : 3;
    let cardWidthPercent = 100 / visibleCount;
    let currentPos = 0;
    let autoSlideInterval = null;
    const slideInterval = 4000;
    let isTransitioning = false;

    // Clone ALL original cards and append for seamless infinite loop
    originalCards.forEach(card => {
      const clone = card.cloneNode(true);
      track.appendChild(clone);
    });

    function updateLayout() {
      visibleCount = window.innerWidth <= 768 ? 1 : 3;
      cardWidthPercent = 100 / visibleCount;
      const allCards = track.querySelectorAll('.testimonial-card');
      allCards.forEach(card => {
        card.style.minWidth = `${cardWidthPercent}%`;
        card.style.maxWidth = `${cardWidthPercent}%`;
      });
      slideTo(currentPos, false);
    }

    // Initialize layout
    updateLayout();

    window.addEventListener('resize', updateLayout);

    function slideTo(pos, animate) {
      if (animate) {
        track.style.transition = 'transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1)';
        void track.offsetHeight; // Force reflow to ensure transition is registered before transform changes
      } else {
        track.style.transition = 'none';
        void track.offsetHeight; // Force reflow to apply none instantly
      }
      track.style.transform = `translateX(-${pos * cardWidthPercent}%)`;
    }

    function nextSlide() {
      if (isTransitioning) return;
      isTransitioning = true;
      currentPos++;
      slideTo(currentPos, true);

      // Wait for the 600ms transition to complete before resetting transition state
      setTimeout(() => {
        isTransitioning = false;
        if (currentPos >= totalOriginal) {
          currentPos = 0;
          slideTo(0, false);
        }
      }, 650);
    }

    function startAutoSlide() {
      if (autoSlideInterval) clearInterval(autoSlideInterval);
      autoSlideInterval = setInterval(nextSlide, slideInterval);
    }

    // Pause on hover
    const testimonialSection = document.querySelector('.testimonials');
    if (testimonialSection) {
      testimonialSection.addEventListener('mouseenter', () => clearInterval(autoSlideInterval));
      testimonialSection.addEventListener('mouseleave', () => startAutoSlide());
    }

    // Hide dots — not needed for infinite scroll
    if (dotsContainer) dotsContainer.style.display = 'none';

    startAutoSlide();
  }

  // --- REDIRECT TO WOOCOMMERCE CHECKOUT / CART ---
  const getWooCommerceRedirectUrl = (page = 'checkout') => {
    const baseUrl = 'https://yourruut.com';
    if (cart.length === 0) {
      return `${baseUrl}/${page}/`;
    }
    
    // WooCommerce standard URL for adding a single item and redirecting:
    if (cart.length === 1) {
      const item = cart[0];
      return `${baseUrl}/${page}/?add-to-cart=${item.id}&quantity=${item.quantity}`;
    }
    
    // For multiple items, we compile a comma-separated ID and quantity list
    const itemIds = cart.map(item => item.id).join(',');
    const itemQties = cart.map(item => item.quantity).join(',');
    return `${baseUrl}/${page}/?add-to-cart=${itemIds}&quantity=${itemQties}`;
  };

  const checkoutBtns = document.querySelectorAll('.checkout-btn');
  const viewCartBtns = document.querySelectorAll('.view-cart-btn');

  checkoutBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (cart.length === 0) {
        window.showToast('Your cart is empty!');
        return;
      }
      window.showToast('Redirecting to Checkout...');
      setTimeout(() => {
        window.location.href = getWooCommerceRedirectUrl('checkout');
      }, 800);
    });
  });

  viewCartBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (cart.length === 0) {
        window.showToast('Your cart is empty!');
        return;
      }
      window.showToast('Redirecting to Cart...');
      setTimeout(() => {
        window.location.href = getWooCommerceRedirectUrl('cart');
      }, 800);
    });
  });
});
