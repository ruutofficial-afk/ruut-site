// Ruut Application JavaScript


// Global fetch wrapper with automatic remote host fallback for local static testing & timeout control
window.ruutFetch = (url, options = {}) => {
  const timeout = 10000; // 10 seconds timeout for slow WordPress servers
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), timeout);

  // Determine target URL
  let targetUrl = url;
  const isRelative = url.startsWith('/');
  const isLocalHost = window.location.protocol === 'file:' || 
                      window.location.hostname === 'localhost' || 
                      window.location.hostname === '127.0.0.1' ||
                      window.location.hostname === '';

  const wpBaseUrl = window.RUUT_WP_URL || 'https://yourruut.com';

  if (isRelative && isLocalHost) {
    targetUrl = wpBaseUrl + url;
  }

  const doFetch = (fetchUrl) => {
    return fetch(fetchUrl, { ...options, signal: controller.signal })
      .then(res => {
        clearTimeout(id);
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res;
      });
  };

  return doFetch(targetUrl).catch(err => {
    clearTimeout(id);
    // If relative request failed on local setup, attempt fallback to live WordPress host
    if (isRelative && targetUrl !== wpBaseUrl + url) {
      return fetch(wpBaseUrl + url, { ...options });
    }
    throw err;
  });
};

// Global helper to safely normalize API outputs ({ value: [...] } or { data: [...] } or [...]) to a clean JavaScript Array
window.ensureArray = (res) => {
  if (!res) return [];
  if (Array.isArray(res)) return res;
  if (Array.isArray(res.value)) return res.value;
  if (Array.isArray(res.data)) return res.data;
  return [];
};

document.addEventListener('DOMContentLoaded', () => {
  // Helper to construct dynamic product page URLs based on categories
  const getProductUrl = (product) => {
    let parentSlug = 'airmist';
    let subCategorySlug = 'the-atlas-collection';

    if (product.categories && product.categories.length > 0) {
      const isDiffuser = product.categories.some(c => c.slug === 'diffusers' || c.slug === 'diffuser');
      if (isDiffuser) {
        return `/shop/diffuser/?product=${product.id}`;
      }

      const subCat = product.categories.find(c => c.parent && c.parent !== 0);
      if (subCat) {
        subCategorySlug = subCat.slug;
        if (subCat.parent_slug) {
          parentSlug = subCat.parent_slug.replace('-', '');
        } else {
          parentSlug = 'airmist';
        }
      } else {
        const mainCat = product.categories[0];
        if (mainCat.slug === 'air-mists' || mainCat.slug === 'air-mist') {
          parentSlug = 'airmist';
          subCategorySlug = 'the-atlas-collection';
        } else {
          subCategorySlug = mainCat.slug;
        }
      }
    }

    if (parentSlug === 'air-mists' || parentSlug === 'air-mist' || parentSlug === 'airmists') {
      parentSlug = 'airmist';
    }

    return `/shop/${parentSlug}/${subCategorySlug}/?product=${product.id}`;
  };

  // Expose URL Helper globally
  window.getProductUrl = getProductUrl;

  // Pre-fetch helper to cache catalog
  window.ensureProductsFetched = () => {
    if (window.allProducts && window.allProducts.length > 0) {
      return Promise.resolve(window.allProducts);
    }
    if (window.activeProductsFetch) {
      return window.activeProductsFetch;
    }
    window.activeProductsFetch = window.ruutFetch('/wp-json/ruut/v1/products?limit=100')
      .then(res => {
        if (!res.ok) throw new Error('API down');
        return res.json();
      })
      .then(data => {
        const products = window.ensureArray(data);
        window.allProducts = products;
        window.activeProductsFetch = null;
        return products;
      })
      .catch(err => {
        console.error('Failed to pre-fetch products:', err);
        window.activeProductsFetch = null;
        return [];
      });
    return window.activeProductsFetch;
  };


  // --- HERO SLIDER CONTROL ---
  const initHeroSlider = () => {
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.hero-dot');
    const prevBtn = document.getElementById('heroPrevBtn');
    const nextBtn = document.getElementById('heroNextBtn');
    if (slides.length === 0) return;

    let currentSlide = 0;
    let slideInterval;

    const showSlide = (index) => {
      slides.forEach(slide => slide.classList.remove('active'));
      dots.forEach(dot => dot.classList.remove('active'));

      currentSlide = (index + slides.length) % slides.length;

      slides[currentSlide].classList.add('active');
      if (dots[currentSlide]) {
        dots[currentSlide].classList.add('active');
      }
    };

    const nextSlide = () => {
      showSlide(currentSlide + 1);
    };

    const prevSlide = () => {
      showSlide(currentSlide - 1);
    };

    const startAutoPlay = () => {
      stopAutoPlay();
      slideInterval = setInterval(nextSlide, 3000);
    };

    const stopAutoPlay = () => {
      if (slideInterval) {
        clearInterval(slideInterval);
      }
    };

    if (prevBtn) {
      prevBtn.addEventListener('click', (e) => {
        e.preventDefault();
        prevSlide();
        startAutoPlay();
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', (e) => {
        e.preventDefault();
        nextSlide();
        startAutoPlay();
      });
    }

    dots.forEach((dot, idx) => {
      dot.addEventListener('click', (e) => {
        e.preventDefault();
        showSlide(idx);
        startAutoPlay();
      });
    });

    startAutoPlay();

    const heroSection = document.querySelector('.hero');
    if (heroSection) {
      heroSection.addEventListener('mouseenter', stopAutoPlay);
      heroSection.addEventListener('mouseleave', startAutoPlay);
    }
  };

  // Initialize Hero slideshow
  initHeroSlider();

  // Create Product Card template dynamically
  const createProductCard = (product, isFavoriteGrid = false, isBestsellerBadge = false) => {
    const url = getProductUrl(product);

    // Regular vs sale price calculation
    const price = parseFloat(product.price);
    const regPrice = parseFloat(product.regular_price);
    const salePrice = parseFloat(product.sale_price);

    let priceHtml = '';
    let saleBadgeHtml = '';

    if (product.sale_price && salePrice < regPrice) {
      priceHtml = `<span style="text-decoration: line-through; color: #999; margin-right: 8px; font-size: 0.85em;">₹ ${regPrice.toFixed(2)}</span>₹ ${salePrice.toFixed(2)}`;
      if (product.discount_percentage > 0) {
        saleBadgeHtml = `<span class="sale-badge">${product.discount_percentage}% OFF</span>`;
      }
    } else {
      priceHtml = `₹ ${price.toFixed(2)}`;
    }

    // Category label: find sub-category (where parent !== 0) first, else fallback to first category
    const subCat = product.categories && product.categories.find(c => c.parent && c.parent !== 0);
    const categoryLabel = subCat ? subCat.name : (product.categories && product.categories.length > 0 ? product.categories[0].name : 'Air Mist');

    // Rating
    const rating = product.average_rating || '4.5';

    const escapedTitle = product.title.replace(/'/g, "\\'");

    return `
      <div class="product-card" data-id="${product.id}">
        <div class="product-img-wrap">
          <a href="${url}" style="text-decoration: none; color: inherit;">
            <img src="${product.image}" alt="${product.title}">
            ${saleBadgeHtml}
          </a>
          <div class="add-to-cart-overlay">
            <button class="btn-add-cart" onclick="addToCart(${product.id})">Add To Cart</button>
          </div>
        </div>
        <div class="product-info">
          <div class="product-meta-row">
            <span class="product-category-tag">${categoryLabel}</span>
            <span class="product-rating">
              <span class="star-icon">★</span> ${rating}
            </span>
          </div>
          <h3 class="product-title" style="margin-bottom: 0.2rem;">
            <a href="${url}" style="color: inherit; text-decoration: none;">${product.title}</a>
          </h3>
          <div class="product-price-row">
            <span class="product-price" style="color: var(--ruut-accent); font-weight: 600; font-size: 0.9rem;">${priceHtml}</span>
            <button class="btn-buy-now" onclick="buyNow(${product.id})">Buy Now</button>
          </div>
        </div>
      </div>
    `;
  };

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

  // --- MOBILE NAV DRAWER (with null-safety for pages missing the drawer element) ---
  const menuToggle = document.getElementById('menuToggle');
  const closeDrawer = document.getElementById('closeDrawer');
  const mobileDrawer = document.getElementById('mobileDrawer');
  const mobileDrawerLinks = mobileDrawer ? mobileDrawer.querySelectorAll('a') : [];

  const openDrawerMenu = () => {
    if (!mobileDrawer) return;
    mobileDrawer.classList.add('open');
    document.body.style.overflow = 'hidden'; // Prevents background scroll
  };

  const closeDrawerMenu = () => {
    if (!mobileDrawer) return;
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
  const ruutCartpageFill = document.getElementById('ruutCartpageFill');
  const ruutCartpageText = document.getElementById('ruutCartpageText');
  const ruutCartpageDot1 = document.getElementById('ruutCartpageDot1');
  const ruutCartpageDot2 = document.getElementById('ruutCartpageDot2');
  const ruutCartpageProgress = document.getElementById('ruut-cartpage-progress');
  const ruutMinicartProgress = document.getElementById('ruut-minicart-progress');
  const cartSubtotalOriginal = document.getElementById('cartSubtotalOriginal');
  const cartSubtotal = document.getElementById('cartSubtotal');
  const cartUpsellSection = document.getElementById('cartUpsellSection');

  // Dynamic Modal Injector Helper
  const injectModalsIfMissing = () => {
    let couponsOverlay = document.getElementById('ruut-coupons-modal-overlay');
    if (!couponsOverlay) {
      const modalHtml = `
        <div id="ruut-coupons-modal-overlay" class="ruut-coupons-modal-overlay" style="display: none;">
          <div class="ruut-coupons-modal">
            <button type="button" class="ruut-modal-close" id="closeCouponsModal">&times;</button>
            <div class="ruut-modal-header">Privileges</div>
            <div class="ruut-modal-subheader">SELECT A REWARD TO ENHANCE YOUR RITUAL</div>
            <div class="ruut-modal-body" id="ruutCouponsList">
              <p style="text-align: center; padding: 20px; color: var(--ruut-gray);">Fetching active coupons...</p>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    let confirmOverlay = document.getElementById('ruut-confirm-overlay');
    if (!confirmOverlay) {
      const confirmHtml = `
        <div id="ruut-confirm-overlay" class="ruut-confirm-overlay" style="display: none;">
          <div class="ruut-confirm-modal">
            <div class="ruut-confirm-title">Confirm Change</div>
            <div class="ruut-confirm-desc">Applying this privilege will replace your current active privilege, resulting in a lower total benefit. Are you sure you wish to proceed?</div>
            <div class="ruut-confirm-actions">
              <button type="button" class="ruut-confirm-cancel" id="confirmCancelBtn">CANCEL</button>
              <button type="button" class="ruut-confirm-anyway" id="confirmAnywayBtn">APPLY ANYWAY</button>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', confirmHtml);
    }
  };

  // Run dynamic injector
  injectModalsIfMissing();

  // Coupons Modal Selectors
  const exploreCouponsBtn = document.getElementById('exploreCouponsBtn');
  const exploreCartPageCouponsBtn = document.getElementById('exploreCartPageCouponsBtn');
  const closeCouponsModal = document.getElementById('closeCouponsModal');
  const couponsModalOverlay = document.getElementById('ruut-coupons-modal-overlay');
  const ruutConfirmOverlay = document.getElementById('ruut-confirm-overlay');
  const confirmCancelBtn = document.getElementById('confirmCancelBtn');
  const confirmAnywayBtn = document.getElementById('confirmAnywayBtn');
  const ruutCouponCodeInput = document.getElementById('ruut_coupon_code');
  const cartpageCouponCodeInput = document.getElementById('cartpage_coupon_code');

  // Dynamic progress bar settings (retrieved from server or fallback defaults)
  let offerEnabled = true;
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

  // Active coupon state
  let activeCoupon = '';
  let activeCouponSavings = 0; // Tracks the actual ₹ savings of the active coupon
  try {
    activeCoupon = localStorage.getItem('ruut_coupon') || '';
  } catch (e) {
    console.error('Failed to load coupon from localStorage', e);
  }
  let pendingCoupon = null;

  // Server-reported cart totals (synced via WooCommerce session AJAX)
  let serverSubtotal = null;
  let serverTotal = null;
  let serverDiscountTotal = null;
  let serverVolumeDiscount = null;

  // Universal Volume Discount Calculation Helper
  window.getActiveVolumeDiscount = () => {
    const hasCoupon = Boolean(activeCoupon && activeCoupon.trim().length > 0);
    if (hasCoupon || !offerEnabled) return 0;

    const regularItemsQty = cart.reduce((acc, item) => acc + (item.is_gift ? 0 : item.quantity), 0);

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

  // Cart items loaded from localStorage (starts empty on first visit)
  let cart = [];
  try {
    const savedCart = localStorage.getItem('ruut_cart');
    if (savedCart) {
      cart = JSON.parse(savedCart);
    }
  } catch (e) {
    console.error('Failed to load cart from localStorage', e);
  }

  // Save Cart state helper
  const saveCartToStorage = () => {
    try {
      localStorage.setItem('ruut_cart', JSON.stringify(cart));
      localStorage.setItem('ruut_coupon', activeCoupon);
      localStorage.setItem('ruut_coupon_savings', activeCouponSavings.toString());
    } catch (e) {
      console.error('Failed to save cart to localStorage', e);
    }
  };

  // Sync cart action to WooCommerce AJAX server
  const syncCartWithServer = (action, data = {}) => {
    const formBody = new URLSearchParams({ action, ...data }).toString();

    return ruutFetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formBody
    })
      .then(res => res.json())
      .then(response => {
        if (response.success && response.data) {
          if (response.data.items) {
            cart = response.data.items.map(serverItem => ({
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
            } else {
              activeCoupon = '';
              activeCouponSavings = 0;
              try {
                localStorage.setItem('ruut_coupon', '');
                localStorage.setItem('ruut_coupon_savings', '0');
              } catch (e) {}
            }
          }
          const removedMsg = response.data.removed_coupon_message || (response.data.notices && response.data.notices.length > 0 ? response.data.notices[0] : '');
          if (removedMsg) {
            window.showToast(removedMsg);
            const cartCouponMsg = document.getElementById('cartPageCouponMessages');
            if (cartCouponMsg) {
              cartCouponMsg.textContent = removedMsg;
              cartCouponMsg.style.display = 'block';
              setTimeout(() => {
                cartCouponMsg.textContent = '';
                cartCouponMsg.style.display = 'none';
              }, 6000);
            }
            const minicartCouponMsg = document.getElementById('couponMessages');
            if (minicartCouponMsg) {
              minicartCouponMsg.textContent = removedMsg;
              minicartCouponMsg.style.display = 'block';
              setTimeout(() => {
                minicartCouponMsg.textContent = '';
                minicartCouponMsg.style.display = 'none';
              }, 6000);
            }
          }
          if (response.data.offer_enabled !== undefined) {
            offerEnabled = response.data.offer_enabled;
            if (response.data.offer_thresholds && response.data.offer_thresholds.length > 0) {
              offerThresholds = response.data.offer_thresholds;
            }
          }
          renderCart();
          if (window.renderCartPage) window.renderCartPage();
        }
        return response;
      })
      .catch(() => {});
  };

  // Open & Close Drawer System (Ultra-robust with dynamic DOM lookup and event delegation)
  window.openCart = () => {
    // NEVER open mini cart drawer on Cart Page
    const isCartPage = document.body.id === 'cartPage' || window.location.pathname.endsWith('cart.html') || window.location.pathname.endsWith('/cart');
    if (isCartPage) return;

    const drawer = document.getElementById('astra-mobile-cart-drawer');
    const overlay = document.getElementById('astra-cart-drawer-overlay');
    if (drawer) {
      drawer.style.display = 'flex';
      drawer.classList.add('open');
    }
    if (overlay) {
      overlay.style.display = 'block';
      overlay.classList.add('open');
    }
    document.body.style.overflow = 'hidden';
  };

  window.closeCart = () => {
    const drawer = document.getElementById('astra-mobile-cart-drawer');
    const overlay = document.getElementById('astra-cart-drawer-overlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
  };

  const openCartHandler = (e) => {
    const isCartPage = document.body.id === 'cartPage' || window.location.pathname.endsWith('cart.html') || window.location.pathname.endsWith('/cart');
    if (isCartPage) {
      return;
    }
    e.preventDefault();
    if (e.stopPropagation) e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    window.openCart();
  };

  // Attach to cart icon/toggle triggers
  const cartElements = document.querySelectorAll('#cartButton, .cart-icon, a[href="#cart"], .open-cart-btn');
  cartElements.forEach(el => {
    el.addEventListener('click', openCartHandler, true);
  });

  // Global event delegation backup for cart icon opening
  document.addEventListener('click', (e) => {
    const target = e.target.closest('#cartButton, .cart-icon, a[href="#cart"], .open-cart-btn');
    if (target) {
      const isCartPage = document.body.id === 'cartPage' || window.location.pathname.endsWith('cart.html') || window.location.pathname.endsWith('/cart');
      if (isCartPage) {
        return;
      }
      e.preventDefault();
      window.openCart();
    }
  }, true);

  // View Cart navigation handler (navigates to cart.html)
  document.addEventListener('click', (e) => {
    const viewCartTarget = e.target.closest('.view-cart-btn, a[href="/cart.html"], a[href="/cart"]');
    if (viewCartTarget) {
      e.preventDefault();
      e.stopPropagation();
      if (cart.length === 0) {
        window.showToast('Your cart is empty!');
        return;
      }
      window.location.href = '/cart.html';
    }
  });

  // Close triggers
  const closeElements = document.querySelectorAll('#closeCartDrawer, .cart-drawer-close, #astra-cart-drawer-overlay');
  closeElements.forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      window.closeCart();
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      window.closeCart();
    }
  });

  // Shared Free Gift Simulation & Catalog Metadata Resolution
  window.updateGiftSimulation = () => {
    // Resolve missing or generic names and images from window.allProducts catalog if loaded
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
          item.feels_like = prod.feels_like || '';
          item.category_name = (prod.categories && prod.categories.length > 0) ? prod.categories[0].name : '';
        }
      });
    }

    const regularItemsQty = cart.reduce((acc, item) => acc + (item.is_gift ? 0 : item.quantity), 0);
    const hasActiveCouponCode = Boolean(activeCoupon && activeCoupon.trim().length > 0);

    if (offerEnabled && offerThresholds.length > 0 && !hasActiveCouponCode) {
      const activeT = offerThresholds
        .filter(t => regularItemsQty >= t.quantity)
        .reduce((max, t) => (!max || t.quantity > max.quantity ? t : max), null);

      let targetGiftIds = [];
      if (activeT) {
        let compId = parseInt(activeT.compulsory_gift_id || activeT.gift_id || 0);
        let randId = parseInt(activeT.random_gift_id || (activeT.random_gift_pool && activeT.random_gift_pool[0]) || 0);

        if (window.allProducts && window.allProducts.length > 0) {
          if (compId > 0) {
            const foundComp = window.allProducts.find(p => p.id == compId);
            if (!foundComp) compId = parseInt(window.allProducts[0].id);
          } else {
            compId = parseInt(window.allProducts[0].id);
          }

          const needsSecondGift = (activeT.quantity >= 3 || (activeT.random_gift_pool && activeT.random_gift_pool.length > 0) || activeT.random_gift_id > 0);
          if (needsSecondGift) {
            if (randId > 0) {
              const foundRand = window.allProducts.find(p => p.id == randId);
              if (!foundRand || randId === compId) {
                const secondProd = window.allProducts.find(p => p.id != compId);
                randId = secondProd ? parseInt(secondProd.id) : compId;
              }
            } else {
              const secondProd = window.allProducts.find(p => p.id != compId);
              randId = secondProd ? parseInt(secondProd.id) : compId;
            }
          }
        }

        if (compId > 0) targetGiftIds.push(compId);
        if (randId > 0 && randId !== compId) targetGiftIds.push(randId);
      }

      targetGiftIds = [...new Set(targetGiftIds)];

      const remainingTargetGiftIds = [...targetGiftIds];
      const seenGiftIds = new Set();

      cart = cart.filter(item => {
        if (item.is_gift) {
          if (seenGiftIds.has(item.id)) return false;
          let index = remainingTargetGiftIds.indexOf(item.id);
          if (index === -1 && remainingTargetGiftIds.length > 0) {
            index = 0;
            if (window.allProducts && window.allProducts.some(p => p.id == item.id)) {
              // Keep item.id as valid gift product
            } else {
              item.id = remainingTargetGiftIds[0];
            }
          }
          if (index > -1 && index < remainingTargetGiftIds.length) {
            seenGiftIds.add(item.id);
            item.quantity = 1;
            item.price = 0;
            remainingTargetGiftIds.splice(index, 1);
            return true;
          }
          return false;
        }
        return true;
      });

      remainingTargetGiftIds.forEach(giftId => {
        let giftProduct = null;
        if (window.allProducts && window.allProducts.length > 0) {
          giftProduct = window.allProducts.find(p => p.id == giftId);
          if (!giftProduct) giftProduct = window.allProducts[0];
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
            id: giftProduct.id,
            key: "gift-local-" + giftProduct.id,
            is_gift: true
          });
        }
      });
    } else {
      cart = cart.filter(item => !item.is_gift);
    }
  };

  // Render Cart Items
  const renderCart = () => {
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

    // Update cart badge (present on all pages in the header)
    if (cartCountEl) {
      cartCountEl.textContent = totalQuantity;
      cartCountEl.classList.remove('pop');
      void cartCountEl.offsetWidth; // force reflow
      cartCountEl.classList.add('pop');
    }

    // Save cart state
    saveCartToStorage();

    // Dynamically update cart suggestions if present on page
    if (window.loadCartPageSuggestions) {
      window.loadCartPageSuggestions();
    }

    // Dynamically update standalone cart page if present
    if (typeof window.renderCartPage === 'function') {
      window.renderCartPage();
    }

    // If drawer list isn't present, stop here
    if (!cartItemsList) return;

    if (cart.length === 0) {
      if (ruutMinicartProgress) ruutMinicartProgress.style.display = 'none';
      if (ruutCartpageProgress) ruutCartpageProgress.style.display = 'none';

      let bestsellersHtml = '';
      if (window.allProducts) {
        const featured = window.allProducts.filter(p => p.is_featured).slice(0, 3);
        const productsToSuggest = featured.length > 0 ? featured : window.allProducts.slice(0, 3);
        
        bestsellersHtml = `
          <div class="cart-empty-suggestions-title" style="margin-top: 30px; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ruut-text); border-top: 1px solid var(--ruut-border); padding-top: 20px;">Suggested Bestsellers</div>
          <div class="cart-empty-suggestions-list" style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
            ${productsToSuggest.map(product => {
              const price = parseFloat(product.price);
              const escapedTitle = product.title.replace(/'/g, "\\'");
              return `
                <div class="cart-upsell-card" style="display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,0.3); padding: 10px; border-radius: 8px;">
                  <div class="cart-upsell-img-wrap" style="width: 50px; height: 50px; flex-shrink: 0; border-radius: 6px; overflow: hidden;">
                    <img src="${product.image}" alt="${product.title}" style="width: 100%; height: 100%; object-fit: cover;">
                  </div>
                  <div class="cart-upsell-info" style="flex: 1;">
                    <h4 class="cart-upsell-name" style="margin: 0; font-size: 0.85rem; font-family: var(--font-serif); font-weight: normal; color: var(--ruut-text);">${product.title}</h4>
                    <span class="cart-upsell-price" style="font-size: 0.8rem; font-weight: 600; color: var(--ruut-accent);">₹${price.toFixed(2)}</span>
                  </div>
                  <button type="button" class="btn-upsell-add" onclick="addToCart(${product.id})" style="font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; background: var(--ruut-accent); color: var(--ruut-white); border: none; font-weight: 500; cursor: pointer;">ADD +</button>
                </div>
              `;
            }).join('')}
          </div>
        `;
      }

      cartItemsList.innerHTML = `
        <div class="cart-empty-state" style="text-align: center; padding: 40px 20px 20px 20px;">
          <p style="color: var(--ruut-text); margin-bottom: 20px; font-size: 0.95rem;">Your cart is empty.</p>
          <a href="/shop" class="btn-solid" style="display: inline-block; padding: 10px 25px; border-radius: 20px; font-size: 0.8rem; text-decoration: none; text-align: center; margin-bottom: 10px;">Shop Now</a>
          ${bestsellersHtml}
        </div>
      `;

      if (cartSubtotalOriginal) cartSubtotalOriginal.style.display = 'none';
      if (cartSubtotal) cartSubtotal.innerHTML = '₹0.00';
      if (cartUpsellSection) cartUpsellSection.style.display = 'none';
      return;
    }

    if (ruutMinicartProgress) ruutMinicartProgress.style.display = offerEnabled ? 'block' : 'none';
    if (ruutCartpageProgress) ruutCartpageProgress.style.display = (offerEnabled && cart.length > 0) ? 'block' : 'none';

    // Clear list
    cartItemsList.innerHTML = '';

    cart.forEach((item, index) => {
      const isGift = item.is_gift || false;
      const priceDisplay = isGift ? 'FREE' : `₹${(item.price * item.quantity).toFixed(2)}`;
      // For gifts: show original price struck through if > 0. For sale items: show original struck through.
      const originalPriceDisplay = isGift
        ? (item.originalPrice > 0 ? `<span class="cart-item-original-price">₹${(item.originalPrice * item.quantity).toFixed(2)}</span>` : '')
        : (item.price < item.originalPrice ? `<span class="cart-item-original-price">₹${(item.originalPrice * item.quantity).toFixed(2)}</span>` : '');
      
      const giftBadgeHtml = isGift ? `
        <span class="cart-item-gift-badge">FREE GIFT</span>
      ` : '';

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
      cartItemsList.insertAdjacentHTML('beforeend', itemHtml);
    });

    // Apply coupon calculation — use server-reported savings when available,
    // fall back to local coupon data for offline mode
    let discount = 0;
    if (cart.length > 0 && activeCoupon) {
      // Local spend restriction check if coupon metadata is loaded
      const couponData = (window.activeCoupons || []).find(c => c.code.toUpperCase() === activeCoupon.toUpperCase());
      if (couponData) {
        const minSpend = parseFloat(couponData.minimum_amount) || 0;
        const maxSpend = parseFloat(couponData.maximum_amount) || 0;
        if (discountedSubtotal < minSpend || (maxSpend > 0 && discountedSubtotal > maxSpend)) {
          // Clear active coupon locally
          const oldCoupon = activeCoupon;
          activeCoupon = '';
          activeCouponSavings = 0;
          saveCartToStorage();
          syncCartWithServer('ruut_remove_coupon', { coupon_code: oldCoupon });

          const formattedMin = `₹${minSpend.toFixed(0)}`;
          const msg = `Coupon ${couponData.code.toUpperCase()} has been removed because your cart no longer meets the minimum spend of ${formattedMin}.`;
          window.showToast(msg, 'error');

          const cartCouponMsg = document.getElementById('cartPageCouponMessages');
          if (cartCouponMsg) {
            cartCouponMsg.textContent = msg;
            cartCouponMsg.style.display = 'block';
            setTimeout(() => {
              cartCouponMsg.textContent = '';
              cartCouponMsg.style.display = 'none';
            }, 6000);
          }
          const minicartCouponMsg = document.getElementById('couponMessages');
          if (minicartCouponMsg) {
            minicartCouponMsg.textContent = msg;
            minicartCouponMsg.style.display = 'block';
            setTimeout(() => {
              minicartCouponMsg.textContent = '';
              minicartCouponMsg.style.display = 'none';
            }, 6000);
          }
        }
      }

      if (activeCoupon) {
        if (serverDiscountTotal !== null) {
          discount = serverDiscountTotal;
        } else if (activeCouponSavings > 0) {
          discount = activeCouponSavings;
        } else {
          // Offline fallback: estimate from cached coupon data
          const couponData = (window.activeCoupons || []).find(c => c.code.toUpperCase() === activeCoupon.toUpperCase());
          if (couponData) {
            const amt = parseFloat(couponData.amount) || 0;
            discount = couponData.discount_type === 'percent' ? discountedSubtotal * (amt / 100) : amt;
          }
        }
      }
    }

    // Calculate active threshold level discount (Volume Discount) — Mutually exclusive with Coupon Code
    let activeDiscount = window.getActiveVolumeDiscount();

    let finalDiscountedTotal = serverTotal !== null ? serverTotal : Math.max(0, discountedSubtotal - discount - activeDiscount);

    // Update Subtotal Summary text
    if (cartSubtotalOriginal) {
      if (discount > 0 || activeDiscount > 0 || discountedSubtotal < originalSubtotal) {
        cartSubtotalOriginal.style.display = 'inline';
        cartSubtotalOriginal.innerHTML = `₹${originalSubtotal.toFixed(2)}`;
      } else {
        cartSubtotalOriginal.style.display = 'none';
      }
    }

    if (cartSubtotal) {
      cartSubtotal.innerHTML = `₹${finalDiscountedTotal.toFixed(2)}`;
    }

    // Update minicart coupon applied state
    const minicartCouponInput = document.getElementById('ruut_coupon_code');
    const minicartCouponApplyBtn = minicartCouponInput ? minicartCouponInput.parentElement.querySelector('.btn-promo-apply') : null;
    const minicartCouponSection = minicartCouponInput ? minicartCouponInput.closest('.cart-promo-code-section') : null;
    if (minicartCouponSection) {
      const existingApplied = minicartCouponSection.querySelector('.coupon-applied-state');
      if (activeCoupon) {
        // Show applied state
        if (minicartCouponInput) minicartCouponInput.parentElement.style.display = 'none';
        if (!existingApplied) {
          const appliedHtml = `
            <div class="coupon-applied-state">
              <span class="coupon-applied-code">${activeCoupon.toUpperCase()}</span>
              <span class="coupon-applied-check">Applied ✓</span>
              <button type="button" class="coupon-applied-remove" onclick="removeCoupon('${activeCoupon}')" aria-label="Remove coupon">&times;</button>
            </div>
          `;
          minicartCouponSection.insertAdjacentHTML('afterbegin', appliedHtml);
        } else {
          existingApplied.querySelector('.coupon-applied-code').textContent = activeCoupon.toUpperCase();
          existingApplied.querySelector('.coupon-applied-remove').setAttribute('onclick', `removeCoupon('${activeCoupon}')`);
        }
      } else {
        // Show input state
        if (minicartCouponInput) minicartCouponInput.parentElement.style.display = 'flex';
        if (existingApplied) existingApplied.remove();
      }
    }

    // Update Progress Bar & Tiers
    let progressPercent = 0;
    let needed = 0;
    let nextOfferText = '';

    // Helper: build unlocked reward description
    const getOfferUnlockText = (t) => {
      const rewards = [];
      let compName = t.compulsory_gift_name;
      let randName = t.random_gift_name;

      if (!compName && window.allProducts && window.allProducts.length > 0) {
        const foundComp = window.allProducts.find(p => p.id == t.compulsory_gift_id);
        compName = foundComp ? foundComp.title : window.allProducts[0].title;
      }
      if (t.quantity >= 3 && !randName && window.allProducts && window.allProducts.length > 1) {
        const foundRand = window.allProducts.find(p => p.id == t.random_gift_id && p.title !== compName);
        randName = foundRand ? foundRand.title : (window.allProducts.find(p => p.title !== compName) || window.allProducts[0]).title;
      }

      if (compName) rewards.push(`a free <strong>${compName}</strong>`);
      if (randName) rewards.push(`a free <strong>${randName}</strong>`);
      if (t.discount > 0) rewards.push(`<strong>₹${t.discount.toFixed(0)} off</strong>`);

      if (rewards.length === 0) return 'rewards';
      if (rewards.length === 1) return rewards[0];
      if (rewards.length === 2) return `${rewards[0]} and ${rewards[1]}`;
      return `${rewards.slice(0, -1).join(', ')}, and ${rewards[rewards.length - 1]}`;
    };

    if (offerEnabled && offerThresholds.length > 0) {
      const N = offerThresholds.length;
      const dots = [];
      for (let i = 0; i < N; i++) {
        dots.push({
          percent: ((i + 1) / N) * 100,
          quantity: offerThresholds[i].quantity,
          threshold: offerThresholds[i]
        });
      }

      // Find active threshold index
      let activeIndex = -1;
      for (let i = 0; i < N; i++) {
        if (regularItemsQty >= offerThresholds[i].quantity) {
          activeIndex = i;
        }
      }

      if (activeIndex === -1) {
        // Below first threshold
        const nextT = offerThresholds[0];
        needed = nextT.quantity - regularItemsQty;
        const firstDotPercent = (1 / N) * 100;
        progressPercent = (regularItemsQty / nextT.quantity) * firstDotPercent;
        nextOfferText = `Add <strong>${needed}</strong> more to unlock ${getOfferUnlockText(nextT)}.`;
      } else {
        if (activeIndex < N - 1) {
          // Between activeIndex and activeIndex + 1
          const prevT = offerThresholds[activeIndex];
          const nextT = offerThresholds[activeIndex + 1];
          needed = nextT.quantity - regularItemsQty;
          
          const prevDotPercent = ((activeIndex + 1) / N) * 100;
          const nextDotPercent = ((activeIndex + 2) / N) * 100;
          
          const qtyDiff = nextT.quantity - prevT.quantity;
          const qtyProgress = regularItemsQty - prevT.quantity;
          
          progressPercent = prevDotPercent + (qtyProgress / qtyDiff) * (nextDotPercent - prevDotPercent);
          nextOfferText = `Add <strong>${needed}</strong> more to unlock ${getOfferUnlockText(nextT)}.`;
        } else {
          // Reached/exceeded highest threshold
          progressPercent = 100;
          needed = 0;
          nextOfferText = `<strong>Congratulations! You have unlocked all benefits: ${getOfferUnlockText(offerThresholds[activeIndex])}.</strong>`;
        }
      }

      if (hasActiveCouponCode) {
        nextOfferText = `<span>Coupon <strong>${activeCoupon.toUpperCase()}</strong> active. (Remove coupon to restore volume discount & free gifts)</span>`;
      }

      // Dynamic dots helper
      const updateProgressDots = (fillEl, wrapEl) => {
        if (!wrapEl) return;
        if (fillEl) fillEl.style.width = `${progressPercent}%`;
        
        // Remove existing dots
        wrapEl.querySelectorAll('.cart-progress-dot').forEach(el => el.remove());
        
        // Append new dots
        dots.forEach((dot, idx) => {
          const reachedClass = progressPercent >= dot.percent ? 'ruut-dot-reached' : '';
          const activeColor = progressPercent >= dot.percent ? 'var(--ruut-accent)' : 'var(--ruut-white)';
          const dotHtml = `
            <div class="cart-progress-dot ${reachedClass}" style="left: ${dot.percent}%; background-color: ${activeColor};" title="Tier ${idx + 1}: ${dot.quantity} items">
              <span style="position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); font-size: 0.7rem; font-weight: 600; color: var(--ruut-text); white-space: nowrap;">${dot.quantity} Qty</span>
            </div>
          `;
          wrapEl.insertAdjacentHTML('beforeend', dotHtml);
        });
      };

      // Render dots for mini-cart
      const minicartWrap = document.querySelector('#ruut-minicart-progress .cart-progress-bar-wrap');
      if (minicartWrap) {
        updateProgressDots(ruutMinicartFill, minicartWrap);
        minicartWrap.style.marginBottom = '20px';
      }

      // Render dots for cart page
      const cartpageWrap = document.querySelector('#ruut-cartpage-progress .cart-progress-bar-wrap');
      if (cartpageWrap) {
        updateProgressDots(ruutCartpageFill, cartpageWrap);
        cartpageWrap.style.marginBottom = '20px';
      }

      // Update text labels
      if (ruutMinicartText) {
        ruutMinicartText.innerHTML = nextOfferText;
      }
      if (ruutCartpageText) {
        ruutCartpageText.innerHTML = nextOfferText;
      }
    } else {
      // Hide progress bar if disabled
      const minicartProgress = document.getElementById('ruut-minicart-progress');
      if (minicartProgress) minicartProgress.style.display = 'none';
      const cartpageProgress = document.getElementById('ruut-cartpage-progress');
      if (cartpageProgress) cartpageProgress.style.display = 'none';
    }

    // Sub-category matching logic for suggestions
    if (cartUpsellSection) {
      const lastItem = cart[cart.length - 1];
      let subCategorySlug = null;
      let matchedProducts = [];

      if (window.allProducts && lastItem) {
        const lastProduct = window.allProducts.find(p => p.id == lastItem.id);
        if (lastProduct && lastProduct.categories) {
          const subCat = lastProduct.categories.find(c => c.parent && c.parent !== 0);
          if (subCat) {
            subCategorySlug = subCat.slug;
          } else if (lastProduct.categories.length > 0) {
            subCategorySlug = lastProduct.categories[0].slug;
          }
        }

        const currentCartItemIds = cart.map(item => Number(item.id));
        if (subCategorySlug) {
          matchedProducts = window.allProducts.filter(p => {
            if (currentCartItemIds.includes(Number(p.id))) return false;
            return p.categories && p.categories.some(c => c.slug === subCategorySlug);
          });
        }

        // Fallback to featured or general catalog products if no matches in sub-category
        if (matchedProducts.length === 0) {
          const featured = window.allProducts.filter(p => p.is_featured);
          matchedProducts = featured.filter(p => !currentCartItemIds.includes(Number(p.id)));
          if (matchedProducts.length === 0) {
            matchedProducts = window.allProducts.filter(p => !currentCartItemIds.includes(Number(p.id)));
          }
        }
      }

      const suggestions = matchedProducts.slice(0, 2);

      if (suggestions.length > 0) {
        cartUpsellSection.style.display = 'block';
        const upsellCardsHtml = suggestions.map(product => {
          const price = parseFloat(product.price);
          const escapedTitle = product.title.replace(/'/g, "\\'");
          return `
            <div class="cart-upsell-card" style="display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,0.3); padding: 10px; border-radius: 8px; margin-top: 10px;">
              <div class="cart-upsell-img-wrap" style="width: 50px; height: 50px; flex-shrink: 0; border-radius: 6px; overflow: hidden;">
                <img src="${product.image}" alt="${product.title}" style="width: 100%; height: 100%; object-fit: cover;">
              </div>
              <div class="cart-upsell-info" style="flex: 1;">
                <h4 class="cart-upsell-name" style="margin: 0; font-size: 0.85rem; font-family: var(--font-serif); font-weight: normal; color: var(--ruut-text);">${product.title}</h4>
                <span class="cart-upsell-price" style="font-size: 0.8rem; font-weight: 600; color: var(--ruut-accent);">₹${price.toFixed(2)}</span>
              </div>
              <button type="button" class="btn-upsell-add" onclick="addToCart(${product.id})" style="font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; background: var(--ruut-accent); color: var(--ruut-white); border: none; font-weight: 500; cursor: pointer;">ADD +</button>
            </div>
          `;
        }).join('');

        cartUpsellSection.innerHTML = `
          <div class="cart-upsell-title">Complete The Ritual</div>
          <div class="cart-upsell-list" style="display: flex; flex-direction: column; gap: 10px;">
            ${upsellCardsHtml}
          </div>
        `;
      } else {
        cartUpsellSection.style.display = 'none';
      }
    }
    
    // Immediately sync and update the standalone cart page if on it
    if (window.renderCartPage) {
      window.renderCartPage();
    }
  };


  // Render the standalone cart page (cart.html)
  window.renderCartPage = () => {
    const cartItemsContainer = document.getElementById('cartPageItemsList');
    const billingSubtotal = document.getElementById('billingSubtotal');
    const billingTotal = document.getElementById('billingTotal');
    const billingRewardProductsContainer = document.getElementById('billingRewardProductsContainer');
    
    const cartpageCouponCodeInput = document.getElementById('cartpage_coupon_code');
    const cartpageCouponRemoveBtn = document.getElementById('cartpage_coupon_remove');
    const cartpageCouponApplyBtn = document.getElementById('cartpage_coupon_btn');

    if (!cartItemsContainer) return;

    // Ensure gift simulation and catalog metadata resolution run before rendering cart page
    if (typeof window.updateGiftSimulation === 'function') {
      window.updateGiftSimulation();
    }

    // Resolve missing or generic names, images, volumes, and metadata from window.allProducts catalog if loaded
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
          item.feels_like = prod.feels_like || '';
          item.category_name = (prod.categories && prod.categories.length > 0) ? prod.categories[0].name : '';
        }
      });
    }

    if (cart.length === 0) {
      cartItemsContainer.innerHTML = '<p style="text-align:center;padding:40px 0;color:var(--ruut-text);">Your cart is empty.</p>';
      if (billingSubtotal) billingSubtotal.textContent = '₹0.00';
      if (billingTotal) billingTotal.textContent = '₹0.00';
      if (billingRewardProductsContainer) {
        billingRewardProductsContainer.innerHTML = '';
        billingRewardProductsContainer.style.display = 'none';
      }
      if (cartpageCouponCodeInput) {
        cartpageCouponCodeInput.value = '';
        cartpageCouponCodeInput.disabled = false;
      }
      if (cartpageCouponRemoveBtn) cartpageCouponRemoveBtn.style.display = 'none';
      if (cartpageCouponApplyBtn) {
        cartpageCouponApplyBtn.textContent = 'APPLY';
        cartpageCouponApplyBtn.disabled = false;
        cartpageCouponApplyBtn.style.background = 'var(--ruut-accent)';
      }
      return;
    }

    let originalSubtotal = 0;
    let discountedSubtotal = 0;

    cartItemsContainer.innerHTML = cart.map((item, index) => {
      originalSubtotal += (item.originalPrice || item.price) * item.quantity;
      discountedSubtotal += item.price * item.quantity;
      
      const isGift = item.is_gift || false;
      const originalVal = item.originalPrice || 250.00;
      
      const priceDisplay = isGift ? `
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="text-decoration:line-through;color:#aaa;font-size:0.85rem;font-weight:400;">₹${originalVal.toFixed(2)}</span>
          <span style="font-weight:700;color:var(--ruut-accent);white-space:nowrap;">FREE</span>
        </div>
      ` : `₹${(item.price * item.quantity).toFixed(2)}`;
      
      const qtyHtml = isGift ? `
        <span style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:var(--ruut-white);letter-spacing:0.05em;text-transform:uppercase;background:var(--ruut-accent);padding:4px 12px;border-radius:4px;">🎁 Complimentary Gift</span>
      ` : `
        <div class="qty-selector" style="display:inline-flex;align-items:center;border:1px solid var(--ruut-border);border-radius:6px;">
          <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)" style="border:none;background:none;padding:4px 10px;cursor:pointer;font-size:1rem;line-height:1;">&minus;</button>
          <span class="qty-val" style="padding:0 8px;min-width:20px;text-align:center;font-size:0.9rem;">${item.quantity}</span>
          <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)" style="border:none;background:none;padding:4px 10px;cursor:pointer;font-size:1rem;line-height:1;">&plus;</button>
        </div>
        <button type="button" onclick="removeCartItem(${index})" style="border:none;background:none;color:var(--ruut-accent);cursor:pointer;font-size:0.8rem;text-decoration:underline;padding:0;">Remove</button>
      `;

      const subTag = item.category_name ? `<span style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--ruut-gray);font-family:var(--font-heading);">${item.category_name}</span>` : '';
      const feelsLikeText = item.feels_like ? `<p style="margin:2px 0 0;font-size:0.8rem;font-style:italic;color:#666;">Feels like: ${item.feels_like}</p>` : '';

      return `
        <div class="cart-item" style="display:flex;gap:18px;padding:18px;background:${isGift ? 'rgba(214, 189, 159, 0.15)' : 'rgba(255,255,255,0.5)'};border-radius:12px;border:${isGift ? '1.5px solid var(--ruut-accent)' : '1px solid rgba(0,0,0,0.05)'};position:relative;overflow:hidden;align-items:center;">
          ${isGift ? `
            <div style="position:absolute;top:0;right:0;background:var(--ruut-accent);color:var(--ruut-white);font-size:0.65rem;font-weight:600;padding:3px 12px;border-bottom-left-radius:8px;text-transform:uppercase;letter-spacing:0.05em;">Unlocked Reward</div>
          ` : ''}
          <div style="width:100px;height:100px;flex-shrink:0;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <img src="${item.image}" alt="${item.name}" style="width:100%;height:100%;object-fit:cover;">
          </div>
          <div style="flex:1;display:flex;flex-direction:column;gap:4px;">
            ${subTag}
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
              <h4 style="margin:0;font-family:var(--font-serif);font-size:1.1rem;${isGift ? 'padding-right:90px;' : ''}">${item.name}</h4>
              <div style="white-space:nowrap;">${priceDisplay}</div>
            </div>
            ${item.volume ? `<span style="font-size:0.8rem;color:#777;">Volume: ${item.volume}</span>` : ''}
            ${feelsLikeText}
            <div style="display:flex;align-items:center;gap:12px;margin-top:6px;">
              ${qtyHtml}
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Reward Product rendering in Order Summary
    const giftItems = cart.filter(item => item.is_gift);
    if (billingRewardProductsContainer) {
      if (giftItems.length > 0) {
        billingRewardProductsContainer.innerHTML = giftItems.map(item => {
          const originalVal = item.originalPrice || 250.00;
          return `
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem; margin-top: 10px;">
              <span style="color: var(--ruut-gray); font-style: italic;">🎁 ${item.name}</span>
              <div style="display: inline-flex; align-items: center; gap: 8px;">
                <span style="text-decoration: line-through; color: #aaa; font-size: 0.85rem;">₹${originalVal.toFixed(2)}</span>
                <span style="color: var(--ruut-accent); font-weight: 600; text-transform: uppercase;">FREE</span>
              </div>
            </div>
          `;
        }).join('');
        billingRewardProductsContainer.style.display = 'flex';
      } else {
        billingRewardProductsContainer.innerHTML = '';
        billingRewardProductsContainer.style.display = 'none';
      }
    }

    // Calculate regular and gift subtotals separately for detailed pricing summary
    let regularOriginalSubtotal = 0;
    let regularDiscountedSubtotal = 0;
    let giftValue = 0;

    cart.forEach(item => {
      if (item.is_gift) {
        giftValue += (item.originalPrice || 250.00) * item.quantity;
      } else {
        regularOriginalSubtotal += (item.originalPrice || item.price) * item.quantity;
        regularDiscountedSubtotal += item.price * item.quantity;
      }
    });

    // Use server-reported coupon savings or estimate from coupon data
    let discount = 0;
    if (cart.length > 0 && activeCoupon) {
      if (serverDiscountTotal !== null) {
        discount = serverDiscountTotal;
      } else if (activeCouponSavings > 0) {
        discount = activeCouponSavings;
      } else {
        const couponData = (window.activeCoupons || []).find(c => c.code.toUpperCase() === activeCoupon.toUpperCase());
        if (couponData) {
          const amt = parseFloat(couponData.amount) || 0;
          discount = couponData.discount_type === 'percent' ? regularDiscountedSubtotal * (amt / 100) : amt;
        }
      }
    }

    // Calculate active threshold level discount locally — Mutually exclusive with Coupon Code
    let activeDiscount = window.getActiveVolumeDiscount();

    const basePrice = regularOriginalSubtotal;
    const finalTotal = Math.max(0, regularDiscountedSubtotal - discount - activeDiscount);

    if (billingSubtotal) {
      billingSubtotal.textContent = `₹${basePrice.toFixed(2)}`;
    }
    if (billingTotal) {
      billingTotal.textContent = `₹${finalTotal.toFixed(2)}`;
    }

    // Render detailed price breakdown
    const billingDiscountsContainer = document.getElementById('billingDiscountsContainer');
    if (billingDiscountsContainer) {
      let breakdownHtml = '';

      // Catalog / Sale discounts
      const catalogDiscount = regularOriginalSubtotal - regularDiscountedSubtotal;
      if (catalogDiscount > 0) {
        breakdownHtml += `
          <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #a00;">
            <span style="color: var(--ruut-gray);">Product Sale Savings</span>
            <span>-₹${catalogDiscount.toFixed(2)}</span>
          </div>
        `;
      }



      // Active Privilege / Coupon Discount
      if (discount > 0) {
        breakdownHtml += `
          <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #a00;">
            <span style="color: var(--ruut-gray);">Privilege Discount (${activeCoupon})</span>
            <span>-₹${discount.toFixed(2)}</span>
          </div>
        `;
      }

      // Volume / Tier Discount
      if (activeDiscount > 0) {
        breakdownHtml += `
          <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #a00;">
            <span style="color: var(--ruut-gray);">Volume Discount</span>
            <span>-₹${activeDiscount.toFixed(2)}</span>
          </div>
        `;
      }

      if (breakdownHtml) {
        billingDiscountsContainer.innerHTML = breakdownHtml;
        billingDiscountsContainer.style.display = 'flex';
      } else {
        billingDiscountsContainer.innerHTML = '';
        billingDiscountsContainer.style.display = 'none';
      }
    }

    // Update cart page coupon applied state
    const cartpageCouponSection = cartpageCouponCodeInput ? cartpageCouponCodeInput.closest('.cart-promo-code-section') : null;
    if (cartpageCouponSection) {
      const existingApplied = cartpageCouponSection.querySelector('.coupon-applied-state');
      const inputWrap = cartpageCouponSection.querySelector('.cart-promo-input-wrap');
      if (activeCoupon) {
        // Show applied state
        if (inputWrap) inputWrap.style.display = 'none';
        if (!existingApplied) {
          const appliedHtml = `
            <div class="coupon-applied-state">
              <span class="coupon-applied-code">${activeCoupon.toUpperCase()}</span>
              <span class="coupon-applied-check">Applied ✓</span>
              <button type="button" class="coupon-applied-remove" onclick="removeCoupon('${activeCoupon}')" aria-label="Remove coupon">&times;</button>
            </div>
          `;
          cartpageCouponSection.insertAdjacentHTML('afterbegin', appliedHtml);
        } else {
          existingApplied.querySelector('.coupon-applied-code').textContent = activeCoupon.toUpperCase();
          existingApplied.querySelector('.coupon-applied-remove').setAttribute('onclick', `removeCoupon('${activeCoupon}')`);
        }
      } else {
        // Show input state
        if (inputWrap) inputWrap.style.display = 'flex';
        if (existingApplied) existingApplied.remove();
        if (cartpageCouponCodeInput) {
          cartpageCouponCodeInput.value = '';
          cartpageCouponCodeInput.disabled = false;
        }
      }
    }

    // Dynamically update checkout button link on cart page
    const checkoutBtn = document.querySelector('#cartPage .btn-primary[href*="checkout"]');
    if (checkoutBtn) {
      checkoutBtn.href = getWooCommerceRedirectUrl('checkout');
    }
  };


  // Update Item Quantity
  window.updateQty = (index, delta) => {
    // Reset server totals to force immediate local recalculation in UI
    serverSubtotal = null;
    serverTotal = null;
    serverDiscountTotal = null;
    serverVolumeDiscount = null;

    cart[index].quantity += delta;
    if (cart[index].quantity <= 0) {
      const itemKey = cart[index].key;
      cart.splice(index, 1);
      renderCart();
      if (itemKey && !itemKey.startsWith('product-')) {
        syncCartWithServer('ruut_remove_cart_item', { cart_item_key: itemKey });
      }
    } else {
      renderCart();
      const itemKey = cart[index].key;
      if (itemKey && !itemKey.startsWith('product-')) {
        syncCartWithServer('ruut_update_cart_qty', { cart_item_key: itemKey, quantity: cart[index].quantity });
      }
    }
  };

  // Remove Item
  window.removeCartItem = (index) => {
    // Reset server totals to force immediate local recalculation in UI
    serverSubtotal = null;
    serverTotal = null;
    serverDiscountTotal = null;
    serverVolumeDiscount = null;

    const itemKey = cart[index].key;
    cart.splice(index, 1);
    renderCart();
    if (itemKey && !itemKey.startsWith('product-')) {
      syncCartWithServer('ruut_remove_cart_item', { cart_item_key: itemKey });
    }
  };

  // Add Item to Cart
  window.addToCart = (productNameOrId, price, ...args) => {
    // Reset server totals to force immediate local recalculation in UI
    serverSubtotal = null;
    serverTotal = null;
    serverDiscountTotal = null;
    serverVolumeDiscount = null;

    let btnElement = null;
    let quantity = 1;
    if (productNameOrId && typeof productNameOrId === 'object' && productNameOrId.nodeType === 1) {
      btnElement = productNameOrId;
      productNameOrId = price;
      price = args[0];
      quantity = args[1] || 1;
    }

    if (btnElement) {
      const originalText = btnElement.textContent;
      btnElement.textContent = 'ADDING...';
      btnElement.disabled = true;
      setTimeout(() => {
        btnElement.textContent = 'ADDED!';
        setTimeout(() => {
          btnElement.textContent = originalText;
          btnElement.disabled = false;
        }, 1000);
      }, 500);
    }

    let product = null;
    let variation = null;

    // Lookup product / variation in window.allProducts (fetched from WordPress)
    if (window.allProducts) {
      const targetId = Number(productNameOrId);
      if (!isNaN(targetId)) {
        // Check if it's a variation ID directly
        for (const p of window.allProducts) {
          if (p.id == targetId) {
            product = p;
            break;
          }
          if (p.variations && p.variations.length > 0) {
            const foundVar = p.variations.find(v => v.id == targetId);
            if (foundVar) {
              product = p;
              variation = foundVar;
              break;
            }
          }
        }
      } else {
        product = window.allProducts.find(p => p.title === productNameOrId);
      }
    }

    let name = typeof productNameOrId === 'string' ? productNameOrId : '';
    let image = '';
    let originalPrice = price || 0;
    let volume = '';
    let sku = '';
    let id = typeof productNameOrId === 'number' ? productNameOrId : 0;

    if (product) {
      name = product.title;
      image = product.image;
      price = parseFloat(product.price);
      originalPrice = parseFloat(product.regular_price || product.price);
      volume = product.volume || '';
      sku = product.sku || ('SKU-' + product.id);
      id = product.id;
    }

    // Override with variation details if present
    if (variation) {
      id = variation.id; // variation ID goes to cart
      price = parseFloat(variation.price);
      originalPrice = parseFloat(variation.regular_price || variation.price);
      volume = variation.volume || volume;
      sku = variation.sku || sku;
      if (variation.image) {
        image = variation.image;
      }
    }

    const key = 'product-' + id;

    // Check if product is already in cart by ID (skip gift items — paid + gift can coexist)
    const existingIndex = cart.findIndex(item => item.id == id && !item.is_gift);
    if (existingIndex > -1) {
      cart[existingIndex].quantity += quantity;
    } else {
      cart.push({
        name: name,
        price: price || originalPrice,
        originalPrice: originalPrice,
        quantity: quantity,
        image: image,
        volume: volume,
        sku: sku,
        id: id,
        key: key
      });
    }

    renderCart();
    openCart();

    // Sync to server
    if (id > 0) {
      syncCartWithServer('ruut_add_to_cart', { product_id: id, quantity: quantity })
        .then(() => {
          window.showToast(`Added ${name} ${volume ? '(' + volume + ')' : ''} to cart`);
        });
    } else {
      window.showToast(`Added ${name} to cart`);
    }

    console.log(`[RUUT] Added "${name}" to cart (₹${(price || originalPrice).toFixed(2)})`);
  };

  // Add Upsell Item
  window.addUpsellToCart = () => {
    window.addToCart(2490);
  };

  // Coupons modal interactions
  const exploreRewardsLink = document.getElementById('exploreRewardsLink');
  
  const showCouponsModal = () => {
    if (couponsModalOverlay) {
      if (window.activeCoupons) {
        populateCouponsModal(window.activeCoupons);
      }
      couponsModalOverlay.style.display = 'flex';
      void couponsModalOverlay.offsetWidth;
      couponsModalOverlay.classList.add('open');
    }
  };
  
  const hideCouponsModal = () => {
    if (couponsModalOverlay) {
      couponsModalOverlay.classList.remove('open');
      couponsModalOverlay.style.display = 'none';
    }
  };

  const populateCouponsModal = (coupons) => {
    const couponsListContainer = document.getElementById('ruutCouponsList');
    if (!couponsListContainer) return;

    if (!coupons || coupons.length === 0) {
      couponsListContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--ruut-gray);">No coupons available at this time.</p>';
      return;
    }

    const currentCoupon = activeCoupon ? activeCoupon.toUpperCase() : '';

    couponsListContainer.innerHTML = coupons.map(c => {
      const code = c.code.toUpperCase();
      const isApplied = currentCoupon === code;
      const btnText = isApplied ? 'APPLIED' : 'APPLY';
      const btnClass = isApplied ? 'ruut-modal-apply-btn applied' : 'ruut-modal-apply-btn';
      const discountText = c.discount_type === 'percent' ? `${parseFloat(c.amount)}% OFF` : `₹${parseFloat(c.amount)} OFF`;
      const desc = c.description ? c.description : `Get ${discountText} on your order.`;

      return `
        <div class="ruut-modal-coupon-item" data-coupon-code="${code}" style="margin-bottom: 12px; padding: 12px; border: 1px solid rgba(0,0,0,0.06); border-radius: 8px; background: rgba(255,255,255,0.45); transition: all 0.3s ease;">
          <div class="ruut-modal-coupon-main" style="display: flex; justify-content: space-between; align-items: center; gap: 15px;">
            <div class="ruut-modal-coupon-info" style="flex: 1;">
              <div class="ruut-modal-coupon-code" style="font-weight: 700; font-size: 0.95rem; color: var(--ruut-text); letter-spacing: 0.05em;">${code}</div>
              <div class="ruut-modal-coupon-desc" style="font-size: 0.8rem; color: var(--ruut-gray); margin-top: 4px; line-height: 1.3;">${desc}</div>
            </div>
            <button type="button" class="${btnClass}" onclick="tryApplyCoupon('${code}')" style="font-size: 0.75rem; padding: 6px 12px; border-radius: 4px; background: ${isApplied ? '#777' : 'var(--ruut-accent)'}; color: #fff; border: none; font-weight: 600; cursor: pointer; transition: background-color 0.2s ease;">${btnText}</button>
          </div>
          <div class="ruut-modal-coupon-messages" id="coupon-msg-${code}" style="display: none; margin-top: 8px; font-size: 0.8rem; color: #a00; border-top: 1px dashed rgba(255,0,0,0.1); padding-top: 6px;"></div>
        </div>
      `;
    }).join('');
  };

  const openCouponsModalFunc = (e) => {
    if (e) e.preventDefault();
    
    const couponsListContainer = document.getElementById('ruutCouponsList');
    if (couponsListContainer && (!window.activeCoupons || window.activeCoupons.length === 0)) {
      couponsListContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: var(--ruut-gray);">Fetching active coupons...</p>';
    }
    
    showCouponsModal();

    ruutFetch('/wp-admin/admin-ajax.php?action=ruut_get_active_coupons', { method: 'GET' })
      .then(res => res.json())
      .then(response => {
        if (response.success && Array.isArray(response.data)) {
          window.activeCoupons = response.data;
          populateCouponsModal(response.data);
        } else if (couponsListContainer && (!window.activeCoupons || window.activeCoupons.length === 0)) {
          couponsListContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: #a00;">Failed to load coupons.</p>';
        }
      })
      .catch(err => {
        if (couponsListContainer && (!window.activeCoupons || window.activeCoupons.length === 0)) {
          couponsListContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: #a00;">Error fetching coupons.</p>';
        }
      });
  };

  if (exploreCouponsBtn) {
    exploreCouponsBtn.addEventListener('click', openCouponsModalFunc);
  }
  if (exploreCartPageCouponsBtn) {
    exploreCartPageCouponsBtn.addEventListener('click', openCouponsModalFunc);
  }
  if (exploreRewardsLink) {
    exploreRewardsLink.addEventListener('click', openCouponsModalFunc);
  }

  if (closeCouponsModal) {
    closeCouponsModal.addEventListener('click', () => {
      hideCouponsModal();
    });
  }

  const fetchActiveCoupons = () => {
    ruutFetch('/wp-admin/admin-ajax.php?action=ruut_get_active_coupons', { method: 'GET' })
      .then(res => res.json())
      .then(response => {
        if (response.success && Array.isArray(response.data)) {
          window.activeCoupons = response.data;
        }
      })
      .catch(() => {});
  };
  fetchActiveCoupons();

  // Toast helper — supports 'success', 'error', or default variant
  window.showToast = (message, variant = '') => {
    let toast = document.getElementById('ruut-toast-notif');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'ruut-toast-notif';
      toast.className = 'ruut-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.remove('show', 'ruut-toast--success', 'ruut-toast--error');
    if (variant === 'success') toast.classList.add('ruut-toast--success');
    if (variant === 'error') toast.classList.add('ruut-toast--error');
    void toast.offsetWidth; // force reflow for re-trigger
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
    }, 3500);
  };

  // Buy Now helper
  window.buyNow = (productNameOrId, price, ...args) => {
    let btnElement = null;
    let quantity = 1;
    if (productNameOrId && typeof productNameOrId === 'object' && productNameOrId.nodeType === 1) {
      btnElement = productNameOrId;
      productNameOrId = price;
      price = args[0];
      quantity = args[1] || 1;
    }

    if (btnElement) {
      btnElement.textContent = 'Redirecting...';
      btnElement.disabled = true;
    }

    window.addToCart(productNameOrId, price, quantity);

    setTimeout(() => {
      window.location.href = '/checkout/';
    }, 800);
  };

  const getCouponDiscountValue = (coupon, subtotal) => {
    const amount = parseFloat(coupon.amount) || 0;
    if (coupon.discount_type === 'percent') {
      return subtotal * (amount / 100);
    }
    return amount;
  };

  const applyCouponDirectly = (code) => {
    document.querySelectorAll('.ruut-modal-coupon-messages').forEach(el => {
      el.textContent = '';
      el.style.display = 'none';
    });
    
    const couponMsgArea = document.getElementById('couponMessages');
    const cartPageMsgArea = document.getElementById('cartPageCouponMessages');
    if (couponMsgArea) {
      couponMsgArea.textContent = '';
      couponMsgArea.style.display = 'none';
    }
    if (cartPageMsgArea) {
      cartPageMsgArea.textContent = '';
      cartPageMsgArea.style.display = 'none';
    }

    pendingCoupon = null;
    hideCouponsModal();
    
    window.showToast(`Applying privilege: ${code}...`);
    
    syncCartWithServer('ruut_apply_coupon', { coupon_code: code })
      .then(response => {
        if (response && !response.success) {
          const errorMsg = response.data || 'Privilege could not be applied.';
          window.showToast(errorMsg, 'error');
          activeCoupon = '';
          activeCouponSavings = 0;
          
          const specificMsgEl = document.getElementById(`coupon-msg-${code}`);
          if (specificMsgEl) {
            specificMsgEl.textContent = errorMsg;
            specificMsgEl.style.display = 'block';
            showCouponsModal();
          }
          
          if (couponMsgArea) {
            couponMsgArea.textContent = errorMsg;
            couponMsgArea.style.display = 'block';
          }
          if (cartPageMsgArea) {
            cartPageMsgArea.textContent = errorMsg;
            cartPageMsgArea.style.display = 'block';
          }
          
          syncCartWithServer('ruut_get_cart', {});
        } else {
          // Extract savings from server response for feedback
          const savings = (response && response.data && response.data.coupon_savings) || 0;
          activeCouponSavings = savings;
          if (savings > 0) {
            window.showToast(`Coupon applied successfully. You saved ₹${savings.toFixed(0)}.`, 'success');
          } else {
            window.showToast(`Applied privilege: ${code}`, 'success');
          }
          renderCart();
        }
      });
  };

  // Apply Coupon code logic
  window.tryApplyCoupon = (code) => {
    if (activeCoupon.toUpperCase() === code.toUpperCase()) {
      window.showToast(`Coupon ${code} is already applied!`);
      return;
    }

    // Calculate discount values using actual coupon data
    const subtotal = cart.reduce((acc, item) => acc + (item.is_gift ? 0 : item.price * item.quantity), 0);
    const newCoupon = (window.activeCoupons || []).find(c => c.code.toUpperCase() === code.toUpperCase());
    const oldCoupon = (window.activeCoupons || []).find(c => c.code.toUpperCase() === activeCoupon.toUpperCase());
    
    let newDiscount = newCoupon ? getCouponDiscountValue(newCoupon, subtotal) : 0;
    let oldDiscount = activeCouponSavings > 0 ? activeCouponSavings : (oldCoupon ? getCouponDiscountValue(oldCoupon, subtotal) : 0);

    if (newDiscount < oldDiscount && activeCoupon !== '') {
      pendingCoupon = code;
      const lossAmount = (oldDiscount - newDiscount).toFixed(0);
      // Dynamically update the confirm modal message with actual amounts
      const confirmDesc = document.querySelector('.ruut-confirm-desc');
      if (confirmDesc) {
        confirmDesc.innerHTML = `You are switching from <strong>${activeCoupon.toUpperCase()}</strong> (saving ₹${oldDiscount.toFixed(0)}) to <strong>${code.toUpperCase()}</strong> (saving ₹${newDiscount.toFixed(0)}). You may lose <strong>₹${lossAmount}</strong> in savings. Are you sure?`;
      }
      const confirmTitle = document.querySelector('.ruut-confirm-title');
      if (confirmTitle) {
        confirmTitle.textContent = 'Switch Coupon?';
      }
      if (ruutConfirmOverlay) {
        ruutConfirmOverlay.style.display = 'flex';
        void ruutConfirmOverlay.offsetWidth;
        ruutConfirmOverlay.classList.add('open');
      }
    } else {
      applyCouponDirectly(code);
    }
  };

  // Confirm Overlay Actions
  if (confirmCancelBtn) {
    confirmCancelBtn.addEventListener('click', () => {
      pendingCoupon = null;
      if (ruutConfirmOverlay) {
        ruutConfirmOverlay.classList.remove('open');
        ruutConfirmOverlay.style.display = 'none';
      }
    });
  }

  if (confirmAnywayBtn) {
    confirmAnywayBtn.addEventListener('click', () => {
      if (pendingCoupon) {
        const code = pendingCoupon;
        pendingCoupon = null;
        if (ruutConfirmOverlay) {
          ruutConfirmOverlay.classList.remove('open');
          ruutConfirmOverlay.style.display = 'none';
        }
        applyCouponDirectly(code);
      }
    });
  }

  // Textbox Apply action
  window.applyCouponCode = (isCartPage = false) => {
    const inputEl = isCartPage ? cartpageCouponCodeInput : ruutCouponCodeInput;
    if (!inputEl) return;
    const value = inputEl.value.trim().toUpperCase();
    if (!value) return;
    
    window.tryApplyCoupon(value);
    inputEl.value = '';
  };

  // Remove applied coupon
  window.removeCoupon = (code) => {
    activeCoupon = '';
    activeCouponSavings = 0;
    serverVolumeDiscount = null;
    serverDiscountTotal = 0;
    serverTotal = null;

    try {
      localStorage.removeItem('ruut_coupon');
      localStorage.setItem('ruut_coupon', '');
      localStorage.setItem('ruut_coupon_savings', '0');
    } catch (e) {}

    saveCartToStorage();
    renderCart();
    if (window.renderCartPage) window.renderCartPage();

    syncCartWithServer('ruut_remove_coupon', { coupon_code: code || '' }).then(() => {
      activeCoupon = '';
      activeCouponSavings = 0;
      saveCartToStorage();
      renderCart();
      if (window.renderCartPage) window.renderCartPage();
    });

    window.showToast('Coupon removed. Volume discount & free gifts restored!');
  };



  // --- DYNAMIC RENDERING: CART PAGE SUGGESTIONS ---
  window.loadCartPageSuggestions = () => {
    const cartPageSuggestionsGrid = document.getElementById('cartPageSuggestionsGrid');
    if (!cartPageSuggestionsGrid) return;

    const handleSuggestions = (products) => {
      // Filter out products already in the cart
      const currentCartItemIds = cart.map(item => Number(item.id));
      let suggestedProducts = products.filter(p => !currentCartItemIds.includes(Number(p.id)));

      // If no suggestions left, hide section
      if (suggestedProducts.length === 0) {
        const suggestionsSection = document.getElementById('cartPageSuggestionsSection');
        if (suggestionsSection) suggestionsSection.style.display = 'none';
        return;
      }

      // Limit to 3 suggested products
      const suggestionsToRender = suggestedProducts.slice(0, 3);

      if (suggestionsToRender.length > 0) {
        const suggestionsSection = document.getElementById('cartPageSuggestionsSection');
        if (suggestionsSection) suggestionsSection.style.display = 'block';

        cartPageSuggestionsGrid.innerHTML = suggestionsToRender.map(p => createProductCard(p, false, false)).join('');
      } else {
        const suggestionsSection = document.getElementById('cartPageSuggestionsSection');
        if (suggestionsSection) suggestionsSection.style.display = 'none';
      }
    };

    window.ensureProductsFetched()
      .then(products => {
        handleSuggestions(products);
      })
      .catch(err => {
        console.error('Failed to load suggestions from WordPress:', err);
      });
  };


  // Initialize Cart on Load
  renderCart();

  // Sync with server cart on load to ensure coupons, totals, and gifts are validated and synchronized
  syncCartWithServer('ruut_get_cart', {});

  // Fetch active coupons on load so coupon requirements are locally available
  ruutFetch('/wp-admin/admin-ajax.php?action=ruut_get_active_coupons', { method: 'GET' })
    .then(res => res.json())
    .then(response => {
      if (response.success && response.data) {
        if (Array.isArray(response.data)) {
          window.activeCoupons = response.data;
        } else if (typeof response.data === 'object') {
          window.activeCoupons = response.data.coupons || [];
          if (response.data.offer_thresholds && response.data.offer_thresholds.length > 0) {
            offerThresholds = response.data.offer_thresholds;
          }
          if (response.data.offer_enabled !== undefined) {
            offerEnabled = response.data.offer_enabled !== false;
          }
        }
        renderCart();
        if (window.renderCartPage) window.renderCartPage();
      }
    })
    .catch(() => {});

  // Load Cart Page Suggestions on Load
  if (window.loadCartPageSuggestions) {
    window.loadCartPageSuggestions();
  }

  // Fetch products on load to update suggestions and cart drawer bestsellers
  window.ensureProductsFetched().then(() => {
    renderCart();
    if (window.renderCartPage) window.renderCartPage();
  });


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
    const loadTestimonials = (reviews) => {
      if (!reviews || reviews.length === 0) {
        // Fallback reviews if API is empty or down
        reviews = [
          {
            reviewer_name: "Aditi Sharma",
            review_content: "The Landour Air Mist is pure heaven. It feels exactly like a crisp, misty morning in the hills. The cedarwood notes are beautifully balanced.",
            rating: 5,
            avatar: "https://secure.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=96&d=mp"
          },
          {
            reviewer_name: "Karan Johar",
            review_content: "Tac Hero is my go-to room spray now. Earthy, woody, and smells extremely luxurious. It completely transforms the vibe of my living room.",
            rating: 5,
            avatar: "https://secure.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=96&d=mp"
          },
          {
            reviewer_name: "Priyanka N.",
            review_content: "Absolutely love the packaging and quality. The scent is natural and not overwhelming at all. A premium product worth every rupee.",
            rating: 5,
            avatar: "https://secure.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=96&d=mp"
          }
        ];
      }

      // Render cards
      track.innerHTML = reviews.map(r => {
        const ratingVal = parseInt(r.rating) || 5;
        const starsHtml = '★'.repeat(ratingVal) + '☆'.repeat(5 - ratingVal);
        return `
          <div class="testimonial-card">
            <div class="testimonial-stars" style="color: var(--ruut-accent, #cf6400); font-size: 0.95rem;">${starsHtml}</div>
            <p class="testimonial-text">"${r.review_content}"</p>
            <div class="testimonial-author">
              <div class="author-avatar">
                <img src="${r.avatar || 'https://secure.gravatar.com/avatar/ad516503a11cd5ca435acc9bb6523536?s=96&d=mp'}" alt="${r.reviewer_name}">
              </div>
              <div class="author-info">
                <h4 class="author-name">${r.reviewer_name}</h4>
              </div>
            </div>
          </div>
        `;
      }).join('');

      const originalCards = Array.from(track.querySelectorAll('.testimonial-card'));
      const totalOriginal = originalCards.length;
      if (totalOriginal === 0) return;

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
    };

    // Fetch featured reviews from WordPress REST API
    ruutFetch('/wp-json/ruut/v1/reviews?featured=true')
      .then(res => {
        if (!res.ok) throw new Error('API down');
        return res.json();
      })
      .then(data => {
        loadTestimonials(window.ensureArray(data));
      })
      .catch(err => {
        console.error('Failed to load dynamic reviews, loading fallback testimonials:', err);
        loadTestimonials([]);
      });
  }

  // --- REDIRECT TO WOOCOMMERCE CHECKOUT / CART ---
  const getWooCommerceRedirectUrl = (page = 'checkout') => {
    return `/${page}/`;
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
      window.location.href = getWooCommerceRedirectUrl('checkout');
    });
  })


  viewCartBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (cart.length === 0) {
        window.showToast('Your cart is empty!');
        return;
      }
      window.location.href = 'cart.html';
    });
  });

  // --- DYNAMIC RENDERING: PRODUCTS (Shop, Home, Collections) ---
  const shopPage = document.getElementById('shopPage');
  const shopBestsellersGrid = document.getElementById('shopBestsellersGrid');
  const shopNewArrivalsGrid = document.getElementById('shopNewArrivalsGrid');
  const shopAllProductsGrid = document.getElementById('shopAllProductsGrid');
  const shopSortSelect = document.getElementById('shopSortSelect');
  const shopAllProductsCount = document.getElementById('shopAllProductsCount');
  
  const homeBestsellersGrid = document.getElementById('bestsellersGrid');
  const collectionGrid = document.getElementById('collectionGrid');

  if (shopPage || homeBestsellersGrid || collectionGrid) {

    // Sort helper for "All Products" grid
    const sortProducts = (products, sortBy) => {
      const sorted = [...products];
      switch (sortBy) {
        case 'newest':
          sorted.sort((a, b) => new Date(b.date_created || 0) - new Date(a.date_created || 0));
          break;
        case 'price-low':
          sorted.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
          break;
        case 'price-high':
          sorted.sort((a, b) => parseFloat(b.price) - parseFloat(a.price));
          break;
        case 'popularity':
        default:
          // Featured first, then by total_sales or rating
          sorted.sort((a, b) => {
            if (a.is_featured && !b.is_featured) return -1;
            if (!a.is_featured && b.is_featured) return 1;
            return (parseFloat(b.average_rating) || 0) - (parseFloat(a.average_rating) || 0);
          });
          break;
      }
      return sorted;
    };

    const renderAllProducts = (products, sortBy) => {
      if (!shopAllProductsGrid) return;
      const sorted = sortProducts(products, sortBy);
      if (sorted.length > 0) {
        shopAllProductsGrid.innerHTML = sorted.map(p => createProductCard(p, false, false)).join('');
      } else {
        shopAllProductsGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px;">No products found.</div>';
      }
      if (shopAllProductsCount) {
        shopAllProductsCount.textContent = `Showing ${sorted.length} product${sorted.length !== 1 ? 's' : ''}`;
      }
    };

    const handleProducts = (products) => {
      // Store in global window.allProducts so addToCart can use them
      window.allProducts = products;

      // Filter bestsellers (featured products)
      const bestsellers = products.filter(p => p.is_featured);
      // If no featured products, fallback to showing all products or top products
      const bestsellersToRender = bestsellers.length > 0 ? bestsellers : products.slice(0, 3);

      // Render Shop Bestsellers
      if (shopBestsellersGrid) {
        if (bestsellersToRender.length > 0) {
          shopBestsellersGrid.innerHTML = bestsellersToRender.map(p => createProductCard(p, false, true)).join('');
        } else {
          shopBestsellersGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px;">No bestsellers found.</div>';
        }
      }

      // Render Home Bestsellers
      if (homeBestsellersGrid) {
        if (bestsellersToRender.length > 0) {
          homeBestsellersGrid.innerHTML = bestsellersToRender.map(p => createProductCard(p, false, true)).join('');
        } else {
          homeBestsellersGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px;">No bestsellers found.</div>';
        }
      }

      // Render Shop New Arrivals (sorted by date, newest first, max 6)
      if (shopNewArrivalsGrid) {
        const newArrivals = [...products]
          .sort((a, b) => new Date(b.date_created || 0) - new Date(a.date_created || 0))
          .slice(0, 6);
        if (newArrivals.length > 0) {
          shopNewArrivalsGrid.innerHTML = newArrivals.map(p => createProductCard(p, false, false)).join('');
        } else {
          shopNewArrivalsGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px;">No new arrivals yet.</div>';
        }
      }

      // Render Shop All Products (with sorting)
      if (shopAllProductsGrid) {
        const currentSort = shopSortSelect ? shopSortSelect.value : 'popularity';
        renderAllProducts(products, currentSort);

        // Attach sort change listener
        if (shopSortSelect) {
          shopSortSelect.addEventListener('change', () => {
            renderAllProducts(products, shopSortSelect.value);
          });
        }
      }
      
      // Render Dynamic Collection Page Grid
      if (collectionGrid) {
        const urlParams = new URLSearchParams(window.location.search);
        const collectionSlug = urlParams.get('collection') || 'the-atlas-collection';

        const collectionProducts = products.filter(p => p.categories && p.categories.some(c => c.slug === collectionSlug));
        
        const resultsCountEl = document.getElementById('collectionResultsCount');
        if (resultsCountEl) {
          resultsCountEl.textContent = `Showing all ${collectionProducts.length} result${collectionProducts.length !== 1 ? 's' : ''}`;
        }

        if (collectionProducts.length > 0) {
          collectionGrid.innerHTML = collectionProducts.map(p => createProductCard(p, false, false)).join('');
        } else {
          collectionGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--ruut-text);">No products found in this collection.</div>';
        }
      }
    };

    // If we are on the collection page, fetch collection details dynamically
    if (collectionGrid) {
      const urlParams = new URLSearchParams(window.location.search);
      const collectionSlug = urlParams.get('collection') || 'the-atlas-collection';

      ruutFetch(`/wp-json/ruut/v1/collections?slug=${collectionSlug}`)
        .then(res => {
          if (!res.ok) throw new Error('Failed to fetch collection details');
          return res.json();
        })
        .then(collectionData => {
          if (collectionData) {
            // Update Title
            const titleEl = document.getElementById('collectionTitle');
            if (titleEl) titleEl.textContent = collectionData.title;

            // Update Breadcrumbs
            const breadcrumbEl = document.getElementById('breadcrumbCurrent');
            if (breadcrumbEl) breadcrumbEl.textContent = collectionData.title;

            // Update Page Title
            const pageTitleEl = document.getElementById('collectionPageTitle');
            if (pageTitleEl) pageTitleEl.textContent = `${collectionData.title} – yourruut.com`;

            // Update Meta Description
            const metaDescEl = document.getElementById('collectionMetaDescription');
            if (metaDescEl && collectionData.description) {
              metaDescEl.setAttribute('content', collectionData.description);
            }

            // Update Description Paragraph
            const descEl = document.getElementById('collectionDescription');
            if (descEl) descEl.innerHTML = collectionData.description || `Discover the ${collectionData.title} by RUUT.`;

            // Update Hero Banner Background Image (uses hero_image for 16:9 banner, falls back to image)
            const heroEl = document.getElementById('collectionHero');
            const heroImgUrl = collectionData.hero_image || collectionData.image;
            if (heroEl && heroImgUrl) {
              heroEl.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('${heroImgUrl}')`;
            }
          }
        })
        .catch(err => {
          console.warn('Could not fetch collection details from WordPress, using fallbacks:', err);
          // Fallback metadata for standard collection names if WordPress is slow/offline
          const fallbacks = {
            'the-atlas-collection': {
              title: 'The Atlas Collection',
              description: 'Discover The Atlas Collection by RUUT. Premium indoor environment air mists that capture the scent of unique places around the world.',
              image: '/assets/images/tac_hero.jpg'
            },
            'diffuser': {
              title: 'Diffusers',
              description: 'Explore premium RUUT diffusers to slowly release natural, bracing scents into your home.',
              image: '/assets/images/diffuser_image.webp'
            },
            'diffusers': {
              title: 'Diffusers',
              description: 'Explore premium RUUT diffusers to slowly release natural, bracing scents into your home.',
              image: '/assets/images/diffuser_image.webp'
            }
          };

          const data = fallbacks[collectionSlug] || {
            title: collectionSlug.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' '),
            description: `Explore the dynamic ${collectionSlug.replace(/-/g, ' ')} series from RUUT.`,
            image: '/assets/images/tac_hero.jpg'
          };

          const titleEl = document.getElementById('collectionTitle');
          if (titleEl) titleEl.textContent = data.title;
          const breadcrumbEl = document.getElementById('breadcrumbCurrent');
          if (breadcrumbEl) breadcrumbEl.textContent = data.title;
          const pageTitleEl = document.getElementById('collectionPageTitle');
          if (pageTitleEl) pageTitleEl.textContent = `${data.title} – yourruut.com`;
          const descEl = document.getElementById('collectionDescription');
          if (descEl) descEl.innerHTML = data.description;
          const heroEl = document.getElementById('collectionHero');
          if (heroEl) {
            heroEl.style.backgroundImage = `linear-gradient(rgba(0, 0, 0, 0.45), rgba(0, 0, 0, 0.45)), url('${data.image}')`;
          }
        });
    }

    // Fetch products
    window.ensureProductsFetched()
      .then(products => {
        if (!products || products.length === 0) throw new Error('Empty response');
        handleProducts(products);
      })
      .catch(err => {
        console.error('Failed to load products from WordPress:', err);
        handleProducts([]);
      });

  }

  // --- DYNAMIC RENDERING: HOMEPAGE CATEGORY CAROUSEL ---
  const homeCollectionsGrid = document.getElementById('collectionsGrid');
  const categoryPrevBtn = document.getElementById('categoryPrevBtn');
  const categoryNextBtn = document.getElementById('categoryNextBtn');

  const initCategoryCarousel = (categories) => {
    const track = document.getElementById('collectionsGrid');
    if (!track) return;

    const cards = Array.from(track.querySelectorAll('.category-card'));
    const totalCards = cards.length;
    if (totalCards === 0) return;

    let visibleCount = window.innerWidth <= 600 ? 1 : (window.innerWidth <= 1024 ? 2 : 3);
    let cardWidthPercent = 100 / visibleCount;
    let currentPos = 0;

    const updateCarouselLayout = () => {
      visibleCount = window.innerWidth <= 600 ? 1 : (window.innerWidth <= 1024 ? 2 : 3);
      cardWidthPercent = 100 / visibleCount;
      const allCards = track.querySelectorAll('.category-card');
      allCards.forEach(card => {
        card.style.minWidth = `${cardWidthPercent}%`;
        card.style.maxWidth = `${cardWidthPercent}%`;
      });
      slideTo(currentPos, false);
    };

    const slideTo = (pos, animate) => {
      if (animate) {
        track.style.transition = 'transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1)';
      } else {
        track.style.transition = 'none';
      }
      track.style.transform = `translateX(-${pos * cardWidthPercent}%)`;
    };

    if (categoryPrevBtn) {
      categoryPrevBtn.addEventListener('click', () => {
        if (currentPos > 0) {
          currentPos--;
          slideTo(currentPos, true);
        } else {
          currentPos = Math.max(0, totalCards - visibleCount);
          slideTo(currentPos, true);
        }
      });
    }

    if (categoryNextBtn) {
      categoryNextBtn.addEventListener('click', () => {
        if (currentPos < totalCards - visibleCount) {
          currentPos++;
          slideTo(currentPos, true);
        } else {
          currentPos = 0;
          slideTo(0, true);
        }
      });
    }

    updateCarouselLayout();
    window.addEventListener('resize', updateCarouselLayout);
  };

  const handleCollections = (categories) => {
    const list = window.ensureArray(categories);
    if (list && list.length > 0) {
      homeCollectionsGrid.innerHTML = list.map((cat, index) => {
        const url = `/collection.html?collection=${cat.slug}`;

        const isComingSoon = false;

        return `
          <div class="category-card" style="position: relative;">
            ${isComingSoon ? `
              <div style="position: absolute; top: 15px; left: 15px; background: #3D4A3E; color: #fff; font-size: 0.7rem; font-weight: 600; padding: 4px 12px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.05em; z-index: 10;">Coming Soon</div>
            ` : ''}
            <div class="category-card-inner" style="${isComingSoon ? 'filter: grayscale(40%) opacity(0.85); pointer-events: none;' : ''}">
              <img src="${cat.image || '/assets/images/tac_hero.jpg'}" alt="${cat.title}">
              <div class="category-content">
                <span class="category-subtitle">${isComingSoon ? 'Launching Soon' : `${cat.count} Product${cat.count !== 1 ? 's' : ''}`}</span>
                <h3 class="category-title" style="font-family: var(--font-serif); font-weight: normal; margin-bottom: 1rem; color: var(--ruut-white);">${cat.title}</h3>
                <a href="${isComingSoon ? '#' : url}" class="category-btn" style="text-decoration: none; ${isComingSoon ? 'background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.6); pointer-events: none;' : ''}">
                  ${isComingSoon ? 'Coming Soon' : 'Explore'}
                </a>
              </div>
            </div>
          </div>
        `;
      }).join('');

      initCategoryCarousel(list);
    } else {
      homeCollectionsGrid.innerHTML = '<div style="text-align:center; padding:40px; color:#4A3324; width:100%;">No collections found.</div>';
    }
  };

  const shopCategorySpheres = document.getElementById('shopCategorySpheres');

  const renderCategorySpheres = (categories) => {
    const list = window.ensureArray(categories);
    if (shopCategorySpheres && list && list.length > 0) {
      shopCategorySpheres.innerHTML = list.map(cat => `
        <a href="/collection.html?collection=${cat.slug}" class="shop-category-sphere">
          <img src="${cat.image || '/assets/images/tac_hero.jpg'}" alt="${cat.title}" class="shop-category-sphere-img">
          <span class="shop-category-sphere-label">${cat.title}</span>
        </a>
      `).join('');
    }
  };

  if ((homeCollectionsGrid && categoryPrevBtn && categoryNextBtn) || shopCategorySpheres) {
    ruutFetch('/wp-json/ruut/v1/collections')
      .then(res => {
        if (!res.ok) throw new Error('API down');
        return res.json();
      })
      .then(categories => {
        const list = window.ensureArray(categories);
        if (!list || list.length === 0) throw new Error('Empty categories');
        if (homeCollectionsGrid) handleCollections(list);
        if (shopCategorySpheres) renderCategorySpheres(list);
      })
      .catch(err => {
        console.error('Failed to load collections from WordPress:', err);
        if (homeCollectionsGrid) handleCollections([]);
        if (shopCategorySpheres) renderCategorySpheres([]);
      });
  }

  // --- SHOP HERO SLIDER ---
  const shopHeroSlider = document.getElementById('shopHeroSlider');
  if (shopHeroSlider) {
    const slides = shopHeroSlider.querySelectorAll('.shop-hero-slide');
    const dots = shopHeroSlider.querySelectorAll('.shop-hero-dot');
    let currentSlide = 0;
    let slideTimer;

    const showSlide = (index) => {
      slides.forEach((slide, i) => {
        if (i === index) {
          slide.classList.add('active');
        } else {
          slide.classList.remove('active');
        }
      });
      dots.forEach((dot, i) => {
        if (i === index) {
          dot.classList.add('active');
        } else {
          dot.classList.remove('active');
        }
      });
      currentSlide = index;
    };

    const nextSlide = () => {
      const next = (currentSlide + 1) % slides.length;
      showSlide(next);
    };

    const startTimer = () => {
      if (slideTimer) clearInterval(slideTimer);
      slideTimer = setInterval(nextSlide, 5000);
    };

    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => {
        showSlide(i);
        startTimer();
      });
    });

    shopHeroSlider.addEventListener('mouseenter', () => clearInterval(slideTimer));
    shopHeroSlider.addEventListener('mouseleave', startTimer);

  }

  // --- DYNAMIC RENDERING: RELATED PRODUCTS ---
  window.loadRelatedProducts = (currentProduct) => {
    const relatedGrid = document.getElementById('relatedProductsGrid');
    if (!relatedGrid) return;

    const handleRelated = (products) => {
      // Exclude current product
      let candidates = products.filter(p => p.id != currentProduct.id);

      // Determine sub-categories of current product (parent is not 0 or undefined)
      const currentSubCats = currentProduct.categories
        ? currentProduct.categories.filter(c => c.parent && c.parent !== 0).map(c => c.slug)
        : [];

      // Determine parent categories of current product (parent is 0 or undefined)
      const currentParentCats = currentProduct.categories
        ? currentProduct.categories.filter(c => !c.parent || c.parent === 0).map(c => c.slug)
        : [];

      // Priority 1: Match sub-categories
      let priority1 = candidates.filter(p =>
        p.categories && p.categories.some(c => c.parent && c.parent !== 0 && currentSubCats.includes(c.slug))
      );

      // Priority 2: Match parent categories (excluding priority 1 matches)
      let priority2 = candidates.filter(p =>
        !priority1.some(p1 => p1.id == p.id) &&
        p.categories && p.categories.some(c => (!c.parent || c.parent === 0) && currentParentCats.includes(c.slug))
      );

      // Priority 3: Fallback (all remaining candidates)
      let priority3 = candidates.filter(p => 
        !priority1.some(p1 => p1.id == p.id) && 
        !priority2.some(p2 => p2.id == p.id)
      );

      // Combine priority sets and limit to 4
      const related = [...priority1, ...priority2, ...priority3].slice(0, 4);

      if (related.length > 0) {
        relatedGrid.innerHTML = related.map(p => createProductCard(p, false, false)).join('');
      } else {
        const relatedSection = document.getElementById('relatedProductsSection');
        if (relatedSection) relatedSection.style.display = 'none';
      }
    };

    window.ensureProductsFetched()
      .then(products => {
        handleRelated(products);
      })
      .catch(err => {
        console.error('Failed to load related products:', err);
      });
  };

  // --- DYNAMIC RENDERING: SINGLE PRODUCT DETAIL PAGE ---
  const productPage = document.getElementById('productPage');
  if (productPage) {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('product') || urlParams.get('id');

    if (productId) {
      window.ensureProductsFetched().then(products => {
        const product = products.find(p => p.id == productId);
        if (product) {
          const productImage = document.getElementById('productImage');
          const productTitle = document.getElementById('productTitle');
          const productPrice = document.getElementById('productPrice');
          const productDescription = document.getElementById('productDescription');

          if (productImage) {
            productImage.src = product.image;
            productImage.alt = product.title;
          }
          if (productTitle) {
            productTitle.textContent = product.title;
          }
          if (productPrice) {
            const price = parseFloat(product.price);
            const regPrice = parseFloat(product.regular_price);
            const salePrice = parseFloat(product.sale_price);

            if (product.sale_price && salePrice < regPrice) {
              productPrice.innerHTML = `<span style="text-decoration: line-through; color: #999; margin-right: 12px; font-size: 0.85em;">₹ ${regPrice.toFixed(2)}</span>₹ ${salePrice.toFixed(2)}`;
            } else {
              productPrice.textContent = `₹ ${price.toFixed(2)}`;
            }
          }
          if (productDescription) {
            productDescription.innerHTML = product.description || product.short_description || '';
          }

          let selectedProductId = product.id;
          let selectedPrice = product.price;

          // 1. SINGLE PRODUCT GALLERY
          const thumbnailsContainer = document.getElementById('productThumbnails');
          if (thumbnailsContainer && product.gallery && product.gallery.length > 0) {
            thumbnailsContainer.innerHTML = product.gallery.map((imgUrl, index) => `
              <div class="thumbnail-item ${index === 0 ? 'active' : ''}" data-index="${index}">
                <img src="${imgUrl}" alt="${product.title} Gallery ${index + 1}" />
              </div>
            `).join('');

            // Gallery interaction
            const thumbItems = thumbnailsContainer.querySelectorAll('.thumbnail-item');
            thumbItems.forEach(item => {
              item.addEventListener('click', () => {
                thumbItems.forEach(t => t.classList.remove('active'));
                item.classList.add('active');
                const idx = parseInt(item.getAttribute('data-index'));
                if (productImage && product.gallery[idx]) {
                  productImage.src = product.gallery[idx];
                }
              });
            });
          }

          // 2. DYNAMIC VOLUMES & VARIATIONS SELECTOR
          const volumeSelector = document.getElementById('productVolumeSelector');
          const volumeOptionsContainer = document.getElementById('volumeOptionsContainer');

          if (volumeSelector && volumeOptionsContainer && product.volumes && product.volumes.length > 0) {
            volumeSelector.style.display = 'flex';
            volumeOptionsContainer.innerHTML = product.volumes.map((vol, index) => `
              <button type="button" class="volume-option-btn ${index === 0 ? 'active' : ''}" data-volume="${vol.trim()}">
                ${vol.trim()}
              </button>
            `).join('');

            const volButtons = volumeOptionsContainer.querySelectorAll('.volume-option-btn');
            
            const handleVolumeSelect = (volBtn) => {
              volButtons.forEach(btn => btn.classList.remove('active'));
              volBtn.classList.add('active');
              const selectedVolume = volBtn.getAttribute('data-volume');
              
              // Find matching variation
              if (product.variations && product.variations.length > 0) {
                const variation = product.variations.find(v => v.volume.toLowerCase() === selectedVolume.toLowerCase());
                if (variation) {
                  selectedProductId = variation.id;
                  selectedPrice = variation.price;
                  
                  // Update price display
                  if (productPrice) {
                    const varPrice = parseFloat(variation.price);
                    const varRegPrice = parseFloat(variation.regular_price || variation.price);
                    const varSalePrice = variation.sale_price ? parseFloat(variation.sale_price) : null;

                    if (varSalePrice && varSalePrice < varRegPrice) {
                      productPrice.innerHTML = `<span style="text-decoration: line-through; color: #999; margin-right: 12px; font-size: 0.85em;">₹ ${varRegPrice.toFixed(2)}</span>₹ ${varSalePrice.toFixed(2)}`;
                    } else {
                      productPrice.textContent = `₹ ${varPrice.toFixed(2)}`;
                    }
                  }
                  
                  // Update image if variation has custom image
                  if (variation.image && productImage) {
                    productImage.src = variation.image;
                    // Highlight matching gallery thumbnail if it exists
                    if (product.gallery) {
                      const galleryIdx = product.gallery.findIndex(imgUrl => imgUrl === variation.image);
                      if (galleryIdx > -1 && thumbnailsContainer) {
                        const thumbs = thumbnailsContainer.querySelectorAll('.thumbnail-item');
                        thumbs.forEach(t => t.classList.remove('active'));
                        if (thumbs[galleryIdx]) {
                          thumbs[galleryIdx].classList.add('active');
                        }
                      }
                    }
                  }
                }
              }
            };

            volButtons.forEach(btn => {
              btn.addEventListener('click', () => handleVolumeSelect(btn));
            });

            // Initialize selection of the first volume
            if (volButtons[0]) {
              handleVolumeSelect(volButtons[0]);
            }
          }

          // 2.5 POPULATE PRODUCT INFO COLLAPSIBLES (Feels Like, The Story, The Name, Directions, Cautions)
          const infoTheName = document.getElementById('infoTheName');
          const infoFeelsLike = document.getElementById('infoFeelsLike');
          const infoTheStory = document.getElementById('infoTheStory');
          const infoDirections = document.getElementById('infoDirections');
          const infoCautions = document.getElementById('infoCautions');

          const fallbackFeelsLike = "Earthy, fresh, and soothing with hints of mountain mist and damp pine needles.";
          const fallbackTheStory = "Inspired by the quiet mornings in Landour, Mussoorie, where the rising mist embraces the cedar and oak trees, capturing a moment of pristine serenity.";
          const fallbackTheName = "Named after the historic Landour cantonment town nestled in Mussoorie. Each spray brings you closer to the clean, bracing air of the Himalayas.";
          const fallbackDirections = "Spray 2-3 times in the air towards the center of the room. Keep the nozzle at least 30 cm away from walls and fabrics. Shake well before use.";
          const fallbackCautions = "Keep out of reach of children and pets. Flammable. Keep away from heat, open flames, and hot surfaces. In case of contact with eyes, rinse immediately with water.";

          const showIfContent = (elem, sectionId, content) => {
            const section = document.getElementById(sectionId);
            if (elem && section) {
              const safeContent = (content !== null && content !== undefined) ? String(content).trim() : '';
              if (safeContent !== '' && safeContent !== 'false' && safeContent !== '0') {
                elem.innerHTML = safeContent.replace(/\n/g, '<br>');
                section.style.display = 'block';
              } else {
                section.style.display = 'none';
              }
            }
          };

          showIfContent(infoTheName, 'infoTheNameSection', product.the_name || fallbackTheName);
          showIfContent(infoFeelsLike, 'infoFeelsLikeSection', product.feels_like || fallbackFeelsLike);
          showIfContent(infoTheStory, 'infoTheStorySection', product.the_story || fallbackTheStory);
          showIfContent(infoDirections, 'infoDirectionsSection', product.directions_for_use || product.directions || fallbackDirections);
          showIfContent(infoCautions, 'infoCautionsSection', product.cautions_warnings || product.cautions || fallbackCautions);


          // Info accordion interactions
          const infoAccordionItems = document.querySelectorAll('.info-accordion-item');
          infoAccordionItems.forEach(item => {
            const header = item.querySelector('.info-accordion-header');
            const body = item.querySelector('.info-accordion-body');
            const icon = item.querySelector('.info-icon');
            header.addEventListener('click', () => {
              const isOpen = item.classList.contains('open');
              
              // Close all other items
              infoAccordionItems.forEach(other => {
                other.classList.remove('open');
                other.querySelector('.info-accordion-body').style.maxHeight = '0';
                other.querySelector('.info-icon').style.transform = 'rotate(0deg)';
              });

              if (!isOpen) {
                item.classList.add('open');
                body.style.maxHeight = body.scrollHeight + 'px';
                icon.style.transform = 'rotate(45deg)';
              }
            });
          });

          // Initialize first accordion open by default
          const firstSection = document.getElementById('infoFeelsLikeSection');
          if (firstSection) {
            firstSection.classList.add('open');
            const body = firstSection.querySelector('.info-accordion-body');
            const icon = firstSection.querySelector('.info-icon');
            if (body) {
              setTimeout(() => {
                body.style.maxHeight = body.scrollHeight + 'px';
              }, 100);
            }
            if (icon) icon.style.transform = 'rotate(45deg)';
          }

          // 3. DYNAMIC DETAILS
          const detailsContainer = document.getElementById('productDetailsContainer');
          const detailsList = document.getElementById('productDetailsList');
          if (detailsContainer && detailsList) {
            if (product.properties && product.properties.length > 0) {
              detailsContainer.style.display = 'block';
              detailsList.innerHTML = product.properties.map(prop => `
                <li style="margin-bottom: 6px;">${prop}</li>
              `).join('');
            } else {
              detailsContainer.style.display = 'none';
            }
          }

          // 4. PRODUCT FAQS ACCORDION
          const faqsSection = document.getElementById('productFaqsSection');
          const faqsAccordion = document.getElementById('productFaqsAccordion');
          if (faqsSection && faqsAccordion) {
            if (product.faqs && product.faqs.length > 0) {
              faqsSection.style.display = 'block';
              faqsAccordion.innerHTML = product.faqs.map((faq, index) => `
                <div class="faq-item" style="border-bottom: 1px solid rgba(0,0,0,0.06); padding: 15px 0;">
                  <div class="faq-header" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-family: var(--font-heading); font-size: 0.95rem; font-weight: 600; color: var(--ruut-text);">
                    <span>${faq.question}</span>
                    <span class="faq-icon" style="transition: transform 0.3s ease; font-size: 1.2rem;">+</span>
                  </div>
                  <div class="faq-body" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s ease; padding-top: 0; color: var(--ruut-gray); font-size: 0.9rem; line-height: 1.5;">
                    <p style="margin: 0; padding-top: 10px;">${faq.answer}</p>
                  </div>
                </div>
              `).join('');

              // Accordion clicks
              const faqItems = faqsAccordion.querySelectorAll('.faq-item');
              faqItems.forEach(item => {
                const header = item.querySelector('.faq-header');
                const body = item.querySelector('.faq-body');
                const icon = item.querySelector('.faq-icon');
                header.addEventListener('click', () => {
                  const isOpen = item.classList.contains('open');
                  
                  // Close all other items
                  faqItems.forEach(other => {
                    other.classList.remove('open');
                    other.querySelector('.faq-body').style.maxHeight = '0';
                    other.querySelector('.faq-icon').style.transform = 'rotate(0deg)';
                    other.querySelector('.faq-icon').textContent = '+';
                  });

                  if (!isOpen) {
                    item.classList.add('open');
                    body.style.maxHeight = body.scrollHeight + 'px';
                    icon.style.transform = 'rotate(45deg)';
                  }
                });
              });
            } else {
              faqsSection.style.display = 'none';
            }
          }

          // 5. PRODUCT SPECIFIC REVIEWS
          const reviewsStars = document.getElementById('reviewsAverageStars');
          const reviewsText = document.getElementById('reviewsAverageText');
          const reviewsList = document.getElementById('productReviewsList');
          
          const loadReviewsList = () => {
            if (!reviewsList) return;
            
            ruutFetch(`/wp-json/ruut/v1/product-reviews?product_id=${product.id}`)
              .then(res => res.json())
              .then(data => {
                if (data.reviews) {
                  // Update average ratings
                  if (reviewsStars) {
                    const starsCount = Math.round(data.average_rating || 5);
                    reviewsStars.textContent = '★'.repeat(starsCount) + '☆'.repeat(5 - starsCount);
                  }
                  if (reviewsText) {
                    reviewsText.textContent = `${data.average_rating || '5.0'} (${data.total_reviews} ${data.total_reviews === 1 ? 'review' : 'reviews'})`;
                  }

                  if (data.reviews.length > 0) {
                    reviewsList.innerHTML = data.reviews.map(rev => `
                      <div class="review-item">
                        <div class="review-item-header">
                          <div class="reviewer-info-row">
                            <span class="reviewer-name">${rev.reviewer_name}</span>
                            ${rev.verified ? '<span class="verified-badge">✓ Verified Buyer</span>' : ''}
                          </div>
                          <span class="review-date">${rev.date}</span>
                        </div>
                        <div class="review-stars">
                          ${'★'.repeat(rev.rating)}${'☆'.repeat(5 - rev.rating)}
                        </div>
                        <p class="review-text">${rev.review_content}</p>
                      </div>
                    `).join('');
                  } else {
                    reviewsList.innerHTML = `
                      <div style="text-align: center; padding: 40px; background: rgba(0,0,0,0.02); border-radius: 8px;">
                        <p style="margin: 0; color: var(--ruut-gray); font-size: 0.9rem;">No reviews yet. Be the first to share your experience!</p>
                      </div>
                    `;
                  }
                }
              })
              .catch(err => {
                console.error('Failed to load reviews:', err);
                reviewsList.innerHTML = '<p style="text-align: center; color: var(--ruut-gray); padding: 20px;">Could not load reviews.</p>';
              });
          };

          loadReviewsList();

          // 6. WRITE A REVIEW MODAL & SUBMISSION
          const writeReviewBtn = document.getElementById('writeReviewBtn');
          const writeReviewModalOverlay = document.getElementById('writeReviewModalOverlay');
          const closeWriteReviewModal = document.getElementById('closeWriteReviewModal');
          const writeReviewForm = document.getElementById('writeReviewForm');
          const reviewProductIdInput = document.getElementById('reviewProductId');

          if (reviewProductIdInput) {
            reviewProductIdInput.value = product.id;
          }

          if (writeReviewBtn && writeReviewModalOverlay) {
            writeReviewBtn.addEventListener('click', () => {
              writeReviewModalOverlay.classList.add('open');
            });
          }

          const closeReviewModal = () => {
            if (writeReviewModalOverlay) {
              writeReviewModalOverlay.classList.remove('open');
            }
          };

          if (closeWriteReviewModal) {
            closeWriteReviewModal.addEventListener('click', closeReviewModal);
          }

          if (writeReviewModalOverlay) {
            writeReviewModalOverlay.addEventListener('click', (e) => {
              if (e.target === writeReviewModalOverlay) {
                closeReviewModal();
              }
            });
          }

          // Stars selection behavior
          const ratingStarsWrap = document.getElementById('writeReviewRatingStars');
          const ratingInput = document.getElementById('writeReviewRating');
          if (ratingStarsWrap && ratingInput) {
            const stars = ratingStarsWrap.querySelectorAll('span');
            
            const highlightStars = (val) => {
              stars.forEach(star => {
                const sVal = parseInt(star.getAttribute('data-val'));
                if (sVal <= val) {
                  star.classList.add('active');
                  star.style.color = '#D6BD9F';
                } else {
                  star.classList.remove('active');
                  star.style.color = '#ccc';
                }
              });
            };

            stars.forEach(star => {
              star.addEventListener('click', () => {
                const val = parseInt(star.getAttribute('data-val'));
                ratingInput.value = val;
                ratingStarsWrap.classList.add('selected');
                highlightStars(val);
              });

              star.addEventListener('mouseenter', () => {
                const val = parseInt(star.getAttribute('data-val'));
                highlightStars(val);
              });
            });

            ratingStarsWrap.addEventListener('mouseleave', () => {
              const currentVal = parseInt(ratingInput.value);
              highlightStars(currentVal);
            });
          }

          // Submit Handler
          if (writeReviewForm) {
            writeReviewForm.addEventListener('submit', (e) => {
              e.preventDefault();
              
              const submitBtn = writeReviewForm.querySelector('.btn-submit-review');
              const origText = submitBtn.textContent;
              submitBtn.textContent = 'SUBMITTING...';
              submitBtn.disabled = true;

              const payload = {
                product_id: parseInt(document.getElementById('reviewProductId').value),
                reviewer_name: document.getElementById('writeReviewName').value.trim(),
                reviewer_email: document.getElementById('writeReviewEmail').value.trim(),
                rating: parseInt(document.getElementById('writeReviewRating').value),
                review_content: document.getElementById('writeReviewContent').value.trim()
              };

              ruutFetch('/wp-json/ruut/v1/product-reviews', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
              })
                .then(res => res.json())
                .then(data => {
                  submitBtn.textContent = origText;
                  submitBtn.disabled = false;
                  
                  if (data.success) {
                    window.showToast(data.message || 'Review submitted successfully!');
                    writeReviewForm.reset();
                    if (ratingInput) ratingInput.value = '5';
                    if (ratingStarsWrap) highlightStars(5);
                    closeReviewModal();
                    
                    // Reload reviews list
                    loadReviewsList();
                  } else {
                    window.showToast(data.message || 'Failed to submit review.');
                  }
                })
                .catch(err => {
                  submitBtn.textContent = origText;
                  submitBtn.disabled = false;
                  console.error('Submit review error:', err);
                  window.showToast('Failed to submit review due to a server error.');
                });
            });
          }

          // 7. YOU MAY ALSO LIKE / RELATED PRODUCTS
          if (window.loadRelatedProducts) {
            window.loadRelatedProducts(product);
          }

          // Bind Add to Cart / Buy Now buttons on the single product page
          const addToCartBtn = document.getElementById('addToCartBtn');
          const buyNowBtn = document.getElementById('buyNowBtn');

          if (addToCartBtn) {
            addToCartBtn.removeAttribute('onclick');
            addToCartBtn.onclick = (e) => {
              e.preventDefault();
              window.addToCart(selectedProductId, selectedPrice);
            };
          }
          if (buyNowBtn) {
            buyNowBtn.removeAttribute('onclick');
            buyNowBtn.onclick = (e) => {
              e.preventDefault();
              window.buyNow(selectedProductId, selectedPrice);
            };
          }
        } else {
          const mainContainer = document.querySelector('#productPage main');
          if (mainContainer) {
            mainContainer.innerHTML = '<h2 style="text-align: center; margin: 80px 0; font-family: var(--font-serif); color: var(--ruut-text);">Product not found.</h2>';
          }
        }
      });
    }
  }

});

// Restore purchase buttons state on back-navigation (pageshow event)
window.addEventListener('pageshow', (event) => {
  const buyNowBtn = document.getElementById('buyNowBtn');
  if (buyNowBtn) {
    buyNowBtn.textContent = 'BUY NOW';
    buyNowBtn.disabled = false;
  }
  const addToCartBtn = document.getElementById('addToCartBtn');
  if (addToCartBtn) {
    addToCartBtn.textContent = 'Add To Cart';
    addToCartBtn.disabled = false;
  }
});
