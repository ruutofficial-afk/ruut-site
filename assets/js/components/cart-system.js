// Ruut Shopping Cart System Engine
// Preserves all core logic: item addition, quantity updates, WooCommerce session syncing, coupon application, free gift calculation.

(function () {
  let offerEnabled = true;
  let offerCategories = ['all'];
  let offerThresholds = [
    {
      quantity: 2,
      compulsory_gift_id: 101,
      compulsory_gift_name: "Miniature Lavender Mist",
      random_gift_pool: [],
      random_gift_id: 0,
      random_gift_name: "",
      discount: 100
    },
    {
      quantity: 3,
      compulsory_gift_id: 101,
      compulsory_gift_name: "Miniature Lavender Mist",
      random_gift_pool: [],
      random_gift_id: 2490,
      random_gift_name: "Tac Hero Air Mist",
      discount: 250
    }
  ];

  const isItemInOfferCategories = (item) => {
    if (item.is_gift) return false;
    if (!offerCategories || !Array.isArray(offerCategories) || offerCategories.length === 0 || offerCategories.includes('all')) {
      return true;
    }
    const allowed = offerCategories.map(c => String(c).trim());
    let prod = null;
    if (window.allProducts && window.allProducts.length > 0) {
      prod = window.allProducts.find(p => p.id == item.id);
    }
    if (prod && prod.categories && Array.isArray(prod.categories)) {
      for (const cat of prod.categories) {
        if (allowed.includes(String(cat.id)) || allowed.includes(String(cat.parent))) return true;
        if (cat.slug && allowed.includes(cat.slug.toLowerCase())) return true;
        if (cat.name && allowed.includes(cat.name.toLowerCase())) return true;
      }
      return false;
    }
    if (item.category_id && allowed.includes(String(item.category_id))) return true;
    if (item.category_name && allowed.some(c => c.toLowerCase() === String(item.category_name).toLowerCase())) return true;
    return false;
  };

  let activeCoupon = '';
  let activeCouponSavings = 0;
  try {
    activeCoupon = localStorage.getItem('ruut_coupon') || '';
  } catch (e) {
    console.error('Failed to load coupon from localStorage', e);
  }

  let serverSubtotal = null;
  let serverTotal = null;
  let serverDiscountTotal = null;
  let serverVolumeDiscount = null;

  // Clear cart if on order received page or logout page
  if (
    window.location.search.includes('key=wc_order_') || 
    window.location.pathname.includes('order-received') ||
    window.location.search.includes('loggedout=true') || 
    window.location.pathname.includes('customer-logout') || 
    window.location.search.includes('action=logout')
  ) {
    try {
      localStorage.removeItem('ruut_cart');
      localStorage.removeItem('ruut_coupon');
      localStorage.removeItem('ruut_coupon_savings');
    } catch(e) {}
  }

  let cart = [];
  try {
    const savedCart = localStorage.getItem('ruut_cart');
    if (savedCart) {
      cart = JSON.parse(savedCart);
    }
  } catch (e) {
    console.error('Failed to load cart from localStorage', e);
  }

  const saveCartToStorage = () => {
    try {
      localStorage.setItem('ruut_cart', JSON.stringify(cart));
      localStorage.setItem('ruut_coupon', activeCoupon);
      localStorage.setItem('ruut_coupon_savings', activeCouponSavings.toString());
    } catch (e) {
      console.error('Failed to save cart to localStorage', e);
    }
  };

  window.getActiveVolumeDiscount = () => {
    const hasCoupon = Boolean(activeCoupon && activeCoupon.trim().length > 0);
    if (hasCoupon || !offerEnabled) return 0;

    const regularItemsQty = cart.reduce((acc, item) => acc + (isItemInOfferCategories(item) ? item.quantity : 0), 0);

    if (serverVolumeDiscount !== null && serverVolumeDiscount >= 0) {
      return parseFloat(serverVolumeDiscount);
    }

    if (offerThresholds && offerThresholds.length > 0) {
      const activeT = offerThresholds
        .filter(t => regularItemsQty >= t.quantity)
        .reduce((max, t) => (!max || t.quantity > max.quantity ? t : max), null);
      if (activeT && activeT.discount > 0) {
        return parseFloat(activeT.discount);
      }
    }

    return 0;
  };

  const syncCartWithServer = (action, data = {}) => {
    const formBody = new URLSearchParams({ action, ...data }).toString();

    return window.ruutFetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formBody
    })
      .then(res => res.json())
      .then(response => {
        if (response.success && response.data) {
          const isUserLoggedIn = response.data.is_user_logged_in === true;
          const serverItems = response.data.items || [];

          if (serverItems.length > 0) {
            cart = serverItems.map(serverItem => ({
              name: serverItem.name,
              price: parseFloat(serverItem.price),
              originalPrice: parseFloat(serverItem.originalPrice || serverItem.price),
              quantity: parseInt(serverItem.quantity),
              image: serverItem.image,
              volume: serverItem.volume || '',
              sku: serverItem.sku || '',
              id: serverItem.id,
              key: serverItem.key,
              is_gift: serverItem.is_gift || false
            }));
            saveCartToStorage();
          } else {
            // Server cart is empty. If logged in, the server is the source of truth, so clear localStorage.
            if (isUserLoggedIn || action === 'ruut_remove_cart_item' || action === 'ruut_sync_cart') {
              cart = [];
              saveCartToStorage();
            }
          }

          if (response.data.totals) {
            serverSubtotal = parseFloat(response.data.subtotal || response.data.totals.subtotal || 0);
            serverTotal = parseFloat(response.data.total || response.data.totals.total || 0);
            serverDiscountTotal = parseFloat(response.data.discount_total || response.data.totals.discount_total || 0);
            serverVolumeDiscount = parseFloat(response.data.volume_discount || response.data.totals.volume_discount || 0);

            const coupons = response.data.coupons || response.data.totals.applied_coupons;
            if (coupons && coupons.length > 0) {
              activeCoupon = coupons[0];
              activeCouponSavings = serverDiscountTotal;
            } else if (action === 'ruut_remove_coupon') {
              activeCoupon = '';
              activeCouponSavings = 0;
              try {
                localStorage.setItem('ruut_coupon', '');
                localStorage.setItem('ruut_coupon_savings', '0');
              } catch (e) { }
            }
          }
          if (response.data.offer_enabled !== undefined) {
            offerEnabled = response.data.offer_enabled;
            if (response.data.offer_categories) {
              offerCategories = response.data.offer_categories;
            }
            if (response.data.offer_thresholds && response.data.offer_thresholds.length > 0) {
              offerThresholds = response.data.offer_thresholds;
            }
          }
          window.renderCart();
          if (window.renderCartPage) window.renderCartPage();
        }
        return response;
      })
      .catch(() => { });
  };

  window.openCart = () => {
    const isCartPage = document.body.id === 'cartPage' ||
      window.location.pathname.endsWith('cart.html') ||
      window.location.pathname === '/cart' ||
      window.location.pathname === '/cart/';
    if (isCartPage) return;

    if (typeof window.renderCart === 'function') {
      window.renderCart();
    }

    const drawer = document.getElementById('astra-mobile-cart-drawer') ||
      document.querySelector('.cart-drawer') ||
      document.getElementById('cart-drawer');

    const overlay = document.getElementById('astra-cart-drawer-overlay') ||
      document.querySelector('.cart-drawer-overlay') ||
      document.getElementById('cart-drawer-overlay');

    if (drawer) {
      drawer.style.display = 'flex';
      drawer.style.visibility = 'visible';
      drawer.style.right = '0px';
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden', 'false');
    }
    if (overlay) {
      overlay.style.display = 'block';
      overlay.style.opacity = '1';
      overlay.style.visibility = 'visible';
      overlay.classList.add('open');
    }
    document.body.style.overflow = 'hidden';
  };

  window.closeCart = () => {
    const drawer = document.getElementById('astra-mobile-cart-drawer') ||
      document.querySelector('.cart-drawer');

    const overlay = document.getElementById('astra-cart-drawer-overlay') ||
      document.querySelector('.cart-drawer-overlay');

    if (drawer) {
      drawer.style.right = '';
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden', 'true');
    }
    if (overlay) {
      overlay.classList.remove('open');
      overlay.style.opacity = '0';
      overlay.style.visibility = 'hidden';
      setTimeout(() => {
        if (!overlay.classList.contains('open')) {
          overlay.style.display = 'none';
        }
      }, 400);
    }
    document.body.style.overflow = '';
  };

  window.applyCouponCode = (isCartPage = false) => {
    const input = isCartPage
      ? document.getElementById('cartpage_coupon_code')
      : document.getElementById('ruut_coupon_code');
    const code = input ? input.value.trim() : '';
    if (!code) return;
    window.applyCoupon(code);
  };

  window.applyCoupon = (code) => {
    if (!code) return;
    activeCoupon = code.toUpperCase();
    saveCartToStorage();
    window.renderCart();
    syncCartWithServer('ruut_apply_coupon', { coupon_code: code });
  };

  window.removeCoupon = (code) => {
    activeCoupon = '';
    activeCouponSavings = 0;
    saveCartToStorage();
    window.renderCart();
    syncCartWithServer('ruut_remove_coupon', { coupon_code: code });
  };

  window.updateGiftSimulation = () => {
    if (window.allProducts && window.allProducts.length > 0) {
      cart.forEach(item => {
        const prod = window.allProducts.find(p => p.id == item.id);
        if (prod) {
          if (!item.name || item.name === 'Free Gift') item.name = prod.title;
          if (!item.image || item.image.includes('tac_hero.jpg')) item.image = prod.image;
          if (!item.volume) item.volume = prod.volume || '200ml';
          if (item.is_gift) {
            item.originalPrice = parseFloat(prod.regular_price || prod.price || 250);
          }
        }
      });
    }

    const regularItemsQty = cart.reduce((acc, item) => acc + (isItemInOfferCategories(item) ? item.quantity : 0), 0);
    const hasActiveCouponCode = Boolean(activeCoupon && activeCoupon.trim().length > 0);
    const hasQualifyingItems = regularItemsQty > 0 || (offerCategories && offerCategories.includes('all'));

    if (offerEnabled && offerThresholds.length > 0 && !hasActiveCouponCode && hasQualifyingItems) {
      const activeT = offerThresholds
        .filter(t => regularItemsQty >= t.quantity)
        .reduce((max, t) => (!max || t.quantity > max.quantity ? t : max), null);

      let targetGifts = [];
      if (activeT) {
        let compId = parseInt(activeT.compulsory_gift_id || activeT.gift_id || 0);
        let randId = parseInt(activeT.random_gift_id || (activeT.random_gift_pool && activeT.random_gift_pool[0]) || 0);

        if (window.allProducts && window.allProducts.length > 0) {
          if (compId > 0 && !window.allProducts.some(p => p.id == compId)) {
            compId = parseInt(window.allProducts[0].id);
          }

          if (activeT.quantity >= 3 || randId > 0 || (activeT.random_gift_pool && activeT.random_gift_pool.length > 0)) {
            let validRand = window.allProducts.find(p => p.id == randId && p.id != compId);
            if (!validRand && activeT.random_gift_pool && activeT.random_gift_pool.length > 0) {
              const poolId = activeT.random_gift_pool.find(id => id != compId && window.allProducts.some(p => p.id == id));
              if (poolId) validRand = window.allProducts.find(p => p.id == poolId);
            }
            if (!validRand) {
              validRand = window.allProducts.find(p => p.id != compId) || window.allProducts[0];
            }
            if (validRand) {
              randId = parseInt(validRand.id);
            }
          }
        }

        if (compId > 0) {
          targetGifts.push({ id: compId, gift_type: 'compulsory', gift_label: 'Complimentary Gift' });
        }
        if (activeT.quantity >= 3 || (randId > 0 && randId !== compId)) {
          const finalRandId = (randId > 0 && randId !== compId) ? randId : (window.allProducts && window.allProducts.find(p => p.id != compId) ? window.allProducts.find(p => p.id != compId).id : (compId + 1));
          targetGifts.push({ id: parseInt(finalRandId), gift_type: 'random', gift_label: 'Special Tester Gift' });
        }
      }

      const remainingTargetGifts = [...targetGifts];
      const seenGiftKeys = new Set();

      cart = cart.filter(item => {
        if (item.is_gift) {
          const giftKey = `${item.id}-${item.gift_type || 'compulsory'}`;
          if (seenGiftKeys.has(giftKey)) return false;

          let matchIdx = remainingTargetGifts.findIndex(g => g.id == item.id);
          if (matchIdx === -1 && remainingTargetGifts.length > 0) {
            matchIdx = 0;
            item.id = remainingTargetGifts[0].id;
          }
          if (matchIdx > -1 && matchIdx < remainingTargetGifts.length) {
            const targetGift = remainingTargetGifts[matchIdx];
            seenGiftKeys.add(`${item.id}-${targetGift.gift_type}`);
            item.quantity = 1;
            item.price = 0;
            item.gift_type = targetGift.gift_type;
            item.gift_label = targetGift.gift_label;
            item.key = `gift-${targetGift.gift_type}-${item.id}`;
            remainingTargetGifts.splice(matchIdx, 1);
            return true;
          }
          return false;
        }
        return true;
      });

      remainingTargetGifts.forEach(targetGift => {
        let giftProduct = null;
        if (window.allProducts && window.allProducts.length > 0) {
          giftProduct = window.allProducts.find(p => p.id == targetGift.id);
          if (!giftProduct) giftProduct = window.allProducts[0];
        } else {
          giftProduct = {
            title: targetGift.gift_type === 'random' ? "Special Tester Fragrance" : "Complimentary Miniature Fragrance",
            regular_price: 250,
            image: "/assets/images/tac_hero.jpg",
            volume: "200ml",
            sku: "GIFT-" + (targetGift.id || 101),
            id: targetGift.id || 101
          };
        }
        if (giftProduct) {
          cart.push({
            name: giftProduct.title,
            price: 0,
            originalPrice: parseFloat(giftProduct.regular_price || giftProduct.price || 250),
            quantity: 1,
            image: giftProduct.image || "/assets/images/tac_hero.jpg",
            volume: giftProduct.volume || "200ml",
            sku: giftProduct.sku || ("GIFT-" + giftProduct.id),
            id: giftProduct.id || targetGift.id,
            key: "gift-" + targetGift.gift_type + "-" + (giftProduct.id || targetGift.id),
            is_gift: true,
            gift_type: targetGift.gift_type,
            gift_label: targetGift.gift_label
          });
        }
      });
    } else {
      cart = cart.filter(item => !item.is_gift);
    }
  };

  window.renderCart = () => {
    window.updateGiftSimulation();

    let totalQuantity = 0;
    let originalSubtotal = 0;
    let discountedSubtotal = 0;

    cart.forEach((item) => {
      if (!item.is_gift) {
        totalQuantity += item.quantity;
      }
      originalSubtotal += (item.originalPrice || item.price) * item.quantity;
      discountedSubtotal += item.price * item.quantity;
    });

    const cartCountEl = document.getElementById('cartCount');
    if (cartCountEl) {
      cartCountEl.textContent = totalQuantity;
      cartCountEl.classList.remove('pop');
      void cartCountEl.offsetWidth;
      cartCountEl.classList.add('pop');
    }

    saveCartToStorage();

    if (window.loadCartPageSuggestions) {
      window.loadCartPageSuggestions();
    }

    if (typeof window.renderCartPage === 'function') {
      window.renderCartPage();
    }

    const cartItemsListEl = document.getElementById('cartItemsList');
    const minicartProgressEl = document.getElementById('ruut-minicart-progress');
    const cartSubtotalEl = document.getElementById('cartSubtotal');

    if (!cartItemsListEl) return;

    if (cart.length === 0) {
      if (minicartProgressEl) minicartProgressEl.style.display = 'none';

      cartItemsListEl.innerHTML = `
        <div class="cart-empty-state" style="text-align: center; padding: 40px 20px 20px 20px;">
          <p style="color: var(--ruut-text); margin-bottom: 20px; font-size: 0.95rem;">Your cart is empty.</p>
          <a href="/shop" class="btn-solid" style="display: inline-block; padding: 10px 25px; border-radius: 20px; font-size: 0.8rem; text-decoration: none; text-align: center; margin-bottom: 10px;">Shop Now</a>
        </div>
      `;

      if (cartSubtotalEl) cartSubtotalEl.innerHTML = '₹0.00';
      return;
    }

    let regularItemsQty = cart.reduce((acc, item) => acc + (isItemInOfferCategories(item) ? item.quantity : 0), 0);
    const hasQualifyingItems = regularItemsQty > 0 || (offerCategories && offerCategories.includes('all'));

    if (minicartProgressEl) minicartProgressEl.style.display = (offerEnabled && hasQualifyingItems) ? 'block' : 'none';

    cartItemsListEl.innerHTML = '';

    cart.forEach((item, index) => {
      const isGift = item.is_gift || false;
      const priceDisplay = isGift ? 'FREE' : `₹${(item.price * item.quantity).toFixed(2)}`;
      const originalPriceDisplay = isGift
        ? (item.originalPrice > 0 ? `<span class="cart-item-original-price">₹${(item.originalPrice * item.quantity).toFixed(2)}</span>` : '')
        : (item.price < item.originalPrice ? `<span class="cart-item-original-price">₹${(item.originalPrice * item.quantity).toFixed(2)}</span>` : '');

      const giftBadgeHtml = isGift ? `<span class="cart-item-gift-badge">FREE GIFT</span>` : '';

      const qtyHtml = isGift ? `
        <span class="cart-item-gift-label">Complimentary</span>
      ` : `
        <div class="qty-selector">
          <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)">&minus;</button>
          <span class="qty-val">${item.quantity}</span>
          <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)">&plus;</button>
        </div>
      `;

      const removeHtml = isGift ? '' : `
        <a role="button" href="#" class="cart-drawer-close" aria-label="Remove ${item.name} from cart" onclick="removeCartItem(${index}); return false;" style="font-size: 1.5rem; align-self: flex-start; margin-left: auto;">&times;</a>
      `;

      const itemHtml = `
        <div class="cart-item ${isGift ? 'cart-item--gift' : ''}">
          <div class="cart-item-img-wrap">
            <img src="${item.image}" alt="${item.name}">
            ${giftBadgeHtml}
          </div>
          <div class="cart-item-details">
            <div class="cart-item-title-row">
              <h4 class="cart-item-name">${item.name}</h4>
              <div class="cart-item-price-wrap">
                ${originalPriceDisplay}
                <span class="cart-item-price">${priceDisplay}</span>
              </div>
            </div>
            <span class="cart-item-volume">${item.volume}</span>
            <div class="cart-item-qty-row">
              ${qtyHtml}
            </div>
          </div>
          ${removeHtml}
        </div>
      `;
      cartItemsListEl.insertAdjacentHTML('beforeend', itemHtml);
    });

    let activeDiscount = window.getActiveVolumeDiscount();
    let finalDiscountedTotal = Math.max(0, discountedSubtotal - activeDiscount);

    if (cartSubtotalEl) {
      cartSubtotalEl.innerHTML = `₹${finalDiscountedTotal.toFixed(2)}`;
    }
  };

  window.addToCart = (productId, customPrice = null, customVolume = null) => {
    let product = null;

    if (window.allProducts && window.allProducts.length > 0) {
      product = window.allProducts.find(p => p.id == productId);
    }

    if (!product && window.DEFAULT_CATALOG_FALLBACK) {
      product = window.DEFAULT_CATALOG_FALLBACK.find(p => p.id == productId);
    }

    if (!product) {
      product = {
        id: productId,
        title: "Ruut Product",
        price: customPrice || 650,
        regular_price: customPrice || 650,
        image: "/assets/images/tac_hero.jpg",
        volume: customVolume || "200ml",
        sku: "RUUT-ITEM-" + productId
      };
    }

    const price = customPrice ? parseFloat(customPrice) : parseFloat(product.price);
    const regPrice = parseFloat(product.regular_price || product.price);
    const volume = customVolume || product.volume || "200ml";

    const existingIndex = cart.findIndex(item => item.id == productId && !item.is_gift && item.volume === volume);

    if (existingIndex > -1) {
      cart[existingIndex].quantity += 1;
    } else {
      cart.push({
        name: product.title,
        price: price,
        originalPrice: regPrice,
        quantity: 1,
        image: product.image,
        volume: volume,
        sku: product.sku || '',
        id: product.id,
        is_gift: false
      });
    }

    saveCartToStorage();
    window.openCart();

    syncCartWithServer('ruut_add_to_cart', {
      product_id: productId,
      quantity: 1,
      volume: volume
    });
  };

  window.updateQty = (index, change) => {
    if (cart[index]) {
      if (cart[index].is_gift) return;

      const item = cart[index];
      const newQty = item.quantity + change;

      if (newQty <= 0) {
        window.removeCartItem(index);
        return;
      }

      item.quantity = newQty;
      saveCartToStorage();
      window.renderCart();

      syncCartWithServer('ruut_update_cart_quantity', {
        cart_item_key: item.key || '',
        product_id: item.id,
        quantity: newQty
      });
    }
  };

  window.removeCartItem = (index) => {
    if (cart[index]) {
      const item = cart[index];
      cart.splice(index, 1);
      saveCartToStorage();
      window.renderCart();

      syncCartWithServer('ruut_remove_cart_item', {
        cart_item_key: item.key || '',
        product_id: item.id
      });
    }
  };

  window.buyNow = (productId, customPrice = null, customVolume = null) => {
    window.addToCart(productId, customPrice, customVolume);
    setTimeout(() => {
      if (typeof window.navigateToCheckout === 'function') {
        window.navigateToCheckout();
      } else {
        window.location.href = '/checkout/';
      }
    }, 400);
  };

  window.getRuutCart = () => cart;

  // Global click event delegation for cart icons & close buttons
  document.addEventListener('DOMContentLoaded', () => {
    const cartSelectors = '#cartButton, .cart-icon, .open-cart-btn, [data-open-cart]';

    document.addEventListener('click', (e) => {
      const target = e.target.closest(cartSelectors);
      if (target) {
        if (target.closest('.view-cart-btn, .checkout-btn, .cart-drawer-actions')) return;
        const isCartPage = document.body.id === 'cartPage' || window.location.pathname.endsWith('cart.html') || window.location.pathname === '/cart' || window.location.pathname === '/cart/';
        if (isCartPage) return;
        e.preventDefault();
        window.openCart();
      }

      const closeTarget = e.target.closest('#closeCartDrawer, .cart-drawer-close, #astra-cart-drawer-overlay');
      if (closeTarget) {
        e.preventDefault();
        window.closeCart();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        window.closeCart();
      }
    });
  });
})();
