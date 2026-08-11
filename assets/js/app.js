// Ruut Application JavaScript


// Global fetch wrapper with automatic remote host fallback for local static testing & timeout control
window.ruutFetch = (url, options = {}) => {
  const timeout = 30000; // 30 seconds timeout for slow WordPress servers
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), timeout);

  // Determine target URL
  let targetUrl = url;
  const isRelative = url.startsWith('/');
  const isLocalHost = window.location.protocol === 'file:' ||
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1' ||
    window.location.hostname === '';

  const localWpUrl = 'http://ruut-local.local';
  const remoteWpUrl = window.RUUT_WP_URL || (isLocalHost ? localWpUrl : 'https://yourruut.com');
  const liveOrigin = (window.location.origin && window.location.origin !== 'null' && !isLocalHost) ? window.location.origin : remoteWpUrl;
  const wpBaseUrl = liveOrigin;

  if (isRelative) {
    if (isLocalHost) {
      targetUrl = wpBaseUrl + url;
    } else {
      targetUrl = url;
    }
  }

  return fetch(targetUrl, { ...options, signal: controller.signal })
    .then(res => {
      clearTimeout(id);
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      return res;
    })
    .catch(err => {
      clearTimeout(id);
      console.warn(`ruutFetch failed for ${targetUrl}:`, err);
      // Fall back if relative request failed
      const fallbackUrl = remoteWpUrl + url;
      if (isRelative && targetUrl !== fallbackUrl) {
        return fetch(fallbackUrl, { ...options })
          .then(res => {
            if (!res.ok) {
              throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res;
          });
      }
      throw err;
    });
};

// Helper for WooCommerce checkout redirection
window.getWooCommerceRedirectUrl = (page = 'checkout') => {
  const isLocalHost = window.location.protocol === 'file:' ||
    window.location.hostname === 'localhost' ||
    window.location.hostname === '127.0.0.1' ||
    window.location.hostname === '';
  const localWpUrl = 'http://ruut-local.local';
  const remoteWpUrl = window.RUUT_WP_URL || (isLocalHost ? localWpUrl : 'https://yourruut.com');
  const wpBaseUrl = (window.location.origin && window.location.origin !== 'null' && !isLocalHost) ? window.location.origin : remoteWpUrl;
  return `${wpBaseUrl}/${page}/`;
};

// Global helper to safely normalize API outputs ({ value: [...] } or { data: [...] } or [...]) to a clean JavaScript Array
window.ensureArray = (res) => {
  if (!res) return [];
  if (Array.isArray(res)) return res;
  if (Array.isArray(res.value)) return res.value;
  if (Array.isArray(res.data)) return res.data;
  if (Array.isArray(res.products)) return res.products;
  if (Array.isArray(res.items)) return res.items;
  if (res.data && Array.isArray(res.data.products)) return res.data.products;
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

  // Default catalog fallback if WordPress API is slow, offline, or returns empty
  const DEFAULT_CATALOG_FALLBACK = [
    {
      id: 101,
      title: "The Street of Kannauj",
      slug: "the-street-of-kannauj",
      price: "650.00",
      regular_price: "650.00",
      sale_price: "",
      image: "/assets/images/tac_hero.jpg",
      gallery: ["/assets/images/tac_hero.jpg"],
      volume: "200ml",
      sku: "RUUT-KANNAUJ-200",
      is_featured: true,
      feels_like: "Earthy, warm, and deeply soothing with notes of fresh petrichor and pure rose distillate.",
      description: "Capturing the historic perfumery legacy of Kannauj, rain on baked earth, and morning blossom.",
      categories: [
        { id: 1, name: "Air Mists", slug: "air-mists", parent: 0 },
        { id: 2, name: "The Atlas Collection", slug: "the-atlas-collection", parent: 1 }
      ],
      volumes: ["200ml", "100ml"],
      variations: [
        { id: 101, volume: "200ml", price: "650.00", regular_price: "650.00" },
        { id: 1011, volume: "100ml", price: "390.00", regular_price: "390.00" }
      ]
    },
    {
      id: 102,
      title: "Landour Cantonment",
      slug: "landour-cantonment",
      price: "650.00",
      regular_price: "650.00",
      sale_price: "",
      image: "/assets/images/tac_hero.jpg",
      gallery: ["/assets/images/tac_hero.jpg"],
      volume: "200ml",
      sku: "RUUT-LANDOUR-200",
      is_featured: true,
      feels_like: "Earthy, fresh, and soothing with hints of mountain mist and damp pine needles.",
      description: "Inspired by the quiet mornings in Landour, Mussoorie, where rising mist embraces cedar and oak trees.",
      categories: [
        { id: 1, name: "Air Mists", slug: "air-mists", parent: 0 },
        { id: 2, name: "The Atlas Collection", slug: "the-atlas-collection", parent: 1 }
      ],
      volumes: ["200ml", "100ml"],
      variations: [
        { id: 102, volume: "200ml", price: "650.00", regular_price: "650.00" },
        { id: 1021, volume: "100ml", price: "390.00", regular_price: "390.00" }
      ]
    },
    {
      id: 103,
      title: "Munnar Mist",
      slug: "munnar-mist",
      price: "650.00",
      regular_price: "650.00",
      sale_price: "",
      image: "/assets/images/tac_hero.jpg",
      gallery: ["/assets/images/tac_hero.jpg"],
      volume: "200ml",
      sku: "RUUT-MUNNAR-200",
      is_featured: true,
      feels_like: "Crisp, green, and invigorating tea leaf aroma bathed in cool mountain dew.",
      description: "Transporting you to the rolling tea plantations of Munnar with fresh, uplifting botanical notes.",
      categories: [
        { id: 1, name: "Air Mists", slug: "air-mists", parent: 0 },
        { id: 2, name: "The Atlas Collection", slug: "the-atlas-collection", parent: 1 }
      ],
      volumes: ["200ml", "100ml"],
      variations: [
        { id: 103, volume: "200ml", price: "650.00", regular_price: "650.00" },
        { id: 1031, volume: "100ml", price: "390.00", regular_price: "390.00" }
      ]
    },
    {
      id: 2490,
      title: "Tac Hero Air Mist",
      slug: "tac-hero-air-mist",
      price: "250.00",
      regular_price: "250.00",
      sale_price: "",
      image: "/assets/images/tac_hero.jpg",
      gallery: ["/assets/images/tac_hero.jpg"],
      volume: "200ml",
      sku: "RUUT-HERO-200",
      is_featured: false,
      feels_like: "Warm cedarwood, fresh amber, and aromatic spices.",
      description: "Our signature tester mist, formulated to elevate every room.",
      categories: [
        { id: 1, name: "Air Mists", slug: "air-mists", parent: 0 },
        { id: 2, name: "The Atlas Collection", slug: "the-atlas-collection", parent: 1 }
      ],
      volumes: ["200ml"],
      variations: [
        { id: 2490, volume: "200ml", price: "250.00", regular_price: "250.00" }
      ]
    },
    {
      id: 105,
      title: "RUUT Signature Reed Diffuser",
      slug: "ruut-signature-reed-diffuser",
      price: "1250.00",
      regular_price: "1250.00",
      sale_price: "",
      image: "/assets/images/diffuser_image.webp",
      gallery: ["/assets/images/diffuser_image.webp"],
      volume: "150ml",
      sku: "RUUT-DIFFUSER-150",
      is_featured: true,
      feels_like: "Subtle, continuous, and luxurious botanical ambiance.",
      description: "Slow-diffusing rattan reeds with essential oil fragrances.",
      categories: [
        { id: 3, name: "Diffusers", slug: "diffusers", parent: 0 }
      ],
      volumes: ["150ml"],
      variations: [
        { id: 105, volume: "150ml", price: "1250.00", regular_price: "1250.00" }
      ]
    }
  ];

  // Pre-fetch helper to cache catalog with instant LocalStorage fallback and live WP API fetching
  window.ensureProductsFetched = (forceRefresh = false) => {
    // If fetch is already in progress, return the pending promise
    if (window.activeProductsFetch && !forceRefresh) {
      return window.activeProductsFetch;
    }

    // Load cached products from LocalStorage for instant initial availability if catalog is empty
    if (!window.allProducts || window.allProducts.length === 0) {
      try {
        const cached = localStorage.getItem('ruut_cached_products');
        if (cached) {
          const parsed = JSON.parse(cached);
          if (Array.isArray(parsed) && parsed.length > 0) {
            window.allProducts = parsed;
          }
        }
      } catch (e) {
        console.warn('LocalStorage read error:', e);
      }
    }

    // If we have already fetched live products in this session and not forced, return resolved promise
    if (!forceRefresh && window.hasFetchedLiveProducts && window.allProducts && window.allProducts.length > 0) {
      return Promise.resolve(window.allProducts);
    }

    window.activeProductsFetch = window.ruutFetch('/wp-json/ruut/v1/products?limit=100')
      .then(res => {
        if (!res.ok) throw new Error('API down');
        return res.json();
      })
      .then(data => {
        const products = window.ensureArray(data);
        if (products.length > 0) {
          window.allProducts = products;
          window.hasFetchedLiveProducts = true;
          try {
            localStorage.setItem('ruut_cached_products', JSON.stringify(products));
          } catch (e) { }
        } else if (!window.allProducts || window.allProducts.length === 0) {
          window.allProducts = DEFAULT_CATALOG_FALLBACK;
        }
        window.activeProductsFetch = null;
        return window.allProducts;
      })
      .catch(err => {
        console.warn('Failed to pre-fetch products from WordPress API, using catalog fallback:', err);
        window.activeProductsFetch = null;
        if (!window.allProducts || window.allProducts.length === 0) {
          window.allProducts = DEFAULT_CATALOG_FALLBACK;
        }
        return window.allProducts;
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

  // Change quantity on product card (mobile design)
  window.changeCardQty = (btnElement, delta) => {
    if (!btnElement) return;
    const cardQtySelector = btnElement.closest('.card-qty-selector');
    if (!cardQtySelector) return;
    const valSpan = cardQtySelector.querySelector('.card-qty-val');
    if (!valSpan) return;
    let currentQty = parseInt(valSpan.textContent, 10) || 1;
    currentQty += delta;
    if (currentQty < 1) currentQty = 1;
    valSpan.textContent = currentQty;
  };

  // Add product from card to cart with quantity
  window.addCardProductToCart = (btnElement, productId, price) => {
    if (!btnElement) return;
    const card = btnElement.closest('.product-card');
    let quantity = 1;
    if (card) {
      const valSpan = card.querySelector('.card-qty-val');
      if (valSpan) {
        quantity = parseInt(valSpan.textContent, 10) || 1;
      }
    }
    if (window.addToCart) {
      window.addToCart(btnElement, productId, price, quantity);
    }
  };

  // Auto-inject card-qty-selector into any .add-to-cart-overlay if missing
  window.injectCardQtySelectors = () => {
    document.querySelectorAll('.add-to-cart-overlay').forEach(overlay => {
      if (!overlay.querySelector('.card-qty-selector')) {
        const btn = overlay.querySelector('.btn-add-cart');
        const qtySelector = document.createElement('div');
        qtySelector.className = 'card-qty-selector';
        qtySelector.innerHTML = `
          <button type="button" class="card-qty-btn minus" onclick="changeCardQty(this, -1)">-</button>
          <span class="card-qty-val">1</span>
          <button type="button" class="card-qty-btn plus" onclick="changeCardQty(this, 1)">+</button>
        `;
        overlay.insertBefore(qtySelector, btn);
        if (btn) {
          const onclickAttr = btn.getAttribute('onclick');
          if (onclickAttr && onclickAttr.includes('addToCart(') && !onclickAttr.includes('addCardProductToCart')) {
            const matches = onclickAttr.match(/addToCart\(([^)]+)\)/);
            if (matches && matches[1]) {
              btn.setAttribute('onclick', `addCardProductToCart(this, ${matches[1]})`);
            }
          }
        }
      }
    });
  };

  // Run initial injection
  setTimeout(window.injectCardQtySelectors, 100);
  document.addEventListener('DOMContentLoaded', window.injectCardQtySelectors);
  window.addEventListener('load', window.injectCardQtySelectors);

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
            <div class="card-qty-selector">
              <button type="button" class="card-qty-btn minus" onclick="changeCardQty(this, -1)">-</button>
              <span class="card-qty-val">1</span>
              <button type="button" class="card-qty-btn plus" onclick="changeCardQty(this, 1)">+</button>
            </div>
            <button class="btn-add-cart" onclick="addCardProductToCart(this, ${product.id}, ${price})">Add To Cart</button>
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
            <div class="mobile-add-to-cart-wrapper">
              <button class="btn-add-cart-mobile" onclick="addCardProductToCart(this, ${product.id}, ${price})">+ ADD</button>
            </div>
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

  // Mobile Submenu Accordion Toggles
  const dropdownToggles = mobileDrawer ? mobileDrawer.querySelectorAll('.mobile-dropdown-toggle') : [];
  dropdownToggles.forEach(toggle => {
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const parentItem = toggle.closest('.mobile-nav-item-has-children');
      if (parentItem) {
        parentItem.classList.toggle('open');
        const isExpanded = parentItem.classList.contains('open');
        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
      }
    });
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
    if (!item || item.is_gift) return false;
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
    return true;
  };

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

    const regularItemsQty = cart.reduce((acc, item) => acc + (isItemInOfferCategories(item) ? item.quantity : 0), 0);

    if (serverVolumeDiscount !== null && serverVolumeDiscount > 0) {
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
            if (response.data.offer_categories) {
              offerCategories = response.data.offer_categories;
            }
          }
          renderCart();
          if (window.renderCartPage) window.renderCartPage();
        }
        return response;
      })
      .catch(() => { });
  };

  // Centralized Checkout Navigation with Server Sync & Direct Checkout Fallback
  window.navigateToCheckout = (btnElement = null) => {
    const nonGiftItems = (cart || []).filter(item => !item.is_gift);
    if (!cart || cart.length === 0 || nonGiftItems.length === 0) {
      window.showToast('Your cart is empty!');
      return;
    }

    if (btnElement) {
      if (btnElement.tagName === 'BUTTON' || btnElement.tagName === 'A') {
        btnElement.style.opacity = '0.7';
        btnElement.style.pointerEvents = 'none';
      }
    }

    // Build URL query params for direct checkout (direct-checkout.php) as a robust fallback
    const productIds = nonGiftItems.map(item => item.id).filter(Boolean).join(',');
    const quantities = nonGiftItems.map(item => item.quantity || 1).join(',');
    let targetUrl = window.getWooCommerceRedirectUrl('checkout');
    const queryParams = [];
    if (productIds) {
      queryParams.push(`add-to-cart=${productIds}`);
      queryParams.push(`quantity=${quantities}`);
    }
    if (typeof activeCoupon !== 'undefined' && activeCoupon) {
      queryParams.push(`coupon_code=${encodeURIComponent(activeCoupon)}`);
    }
    if (queryParams.length > 0) {
      targetUrl += '?' + queryParams.join('&');
    }

    let redirected = false;
    const doRedirect = () => {
      if (!redirected) {
        redirected = true;
        window.location.href = targetUrl;
      }
    };

    // 800ms fallback timeout if AJAX network is slow or fails
    const timeoutId = setTimeout(doRedirect, 800);

    syncCartWithServer('ruut_sync_cart', { cart_items: JSON.stringify(cart) })
      .then(() => {
        clearTimeout(timeoutId);
        doRedirect();
      })
      .catch((err) => {
        console.warn('Cart sync before checkout notice:', err);
        clearTimeout(timeoutId);
        doRedirect();
      });
  };

  // Open & Close Drawer System (Ultra-robust with dynamic DOM lookup and event delegation)
  window.openCart = () => {
    // NEVER open mini cart drawer on Cart Page
    const isCartPage = document.body.id === 'cartPage' ||
      window.location.pathname.endsWith('cart.html') ||
      window.location.pathname === '/cart' ||
      window.location.pathname === '/cart/';
    if (isCartPage) return;

    if (typeof renderCart === 'function') {
      try {
        renderCart();
      } catch (err) {
        console.error('renderCart error:', err);
      }
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

  const openCartHandler = (e) => {
    if (e.target && (e.target.closest('.view-cart-btn') || e.target.closest('.checkout-btn') || e.target.closest('.cart-drawer-actions'))) {
      return;
    }
    const isCartPage = document.body.id === 'cartPage' ||
      window.location.pathname.endsWith('cart.html') ||
      window.location.pathname === '/cart' ||
      window.location.pathname === '/cart/';
    if (isCartPage) {
      return;
    }
    e.preventDefault();
    if (e.stopPropagation) e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    window.openCart();
  };

  // Attach to cart icon/toggle triggers (supporting all custom, Astra, WooCommerce cart icons)
  const cartSelectors = '#cartButton, .cart-icon, .ast-site-header-cart, .ast-header-cart-icon, .header-cart, .cart-link, .cart-toggle, a[href="#cart"], .open-cart-btn, [data-open-cart]';
  const cartElements = document.querySelectorAll(cartSelectors);
  cartElements.forEach(el => {
    el.addEventListener('click', openCartHandler, true);
  });

  // Global event delegation backup for cart icon opening
  document.addEventListener('click', (e) => {
    if (e.target && (e.target.closest('.view-cart-btn') || e.target.closest('.checkout-btn') || e.target.closest('.cart-drawer-actions'))) {
      return;
    }
    const target = e.target.closest(cartSelectors);
    if (target) {
      const isCartPage = document.body.id === 'cartPage' ||
        window.location.pathname.endsWith('cart.html') ||
        window.location.pathname === '/cart' ||
        window.location.pathname === '/cart/';
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

  // Checkout navigation handler (navigates to /checkout/)
  document.addEventListener('click', (e) => {
    const checkoutTarget = e.target.closest('.checkout-btn, #checkoutBtn, a[href*="checkout"], [data-checkout]');
    if (checkoutTarget) {
      e.preventDefault();
      e.stopPropagation();
      window.navigateToCheckout(checkoutTarget);
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

    // STRICT EXCLUSION: Free gift items do NOT count toward regular item quantity count or cart badge
    const regularItemsQty = cart.reduce((acc, item) => acc + (isItemInOfferCategories(item) ? item.quantity : 0), 0);
    const hasActiveCouponCode = Boolean(activeCoupon && activeCoupon.trim().length > 0);
    const hasQualifyingItems = regularItemsQty > 0 || (offerCategories && offerCategories.includes('all'));

    if (offerEnabled && offerThresholds.length > 0 && !hasActiveCouponCode && hasQualifyingItems) {
      const activeT = offerThresholds
        .filter(t => regularItemsQty >= t.quantity)
        .reduce((max, t) => (!max || t.quantity > max.quantity ? t : max), null);

      let targetGifts = []; // array of { id, gift_type, gift_label }
      if (activeT) {
        let compId = parseInt(activeT.compulsory_gift_id || 0);
        let randId = parseInt(activeT.random_gift_id || 0);

        if (window.allProducts && window.allProducts.length > 0) {
          if (compId > 0 && !window.allProducts.some(p => p.id == compId)) {
            compId = 0;
          }

          if (randId > 0 && (!window.allProducts.some(p => p.id == randId) || randId === compId)) {
            randId = 0;
          }
          if (randId === 0 && activeT.random_gift_pool && Array.isArray(activeT.random_gift_pool) && activeT.random_gift_pool.length > 0) {
            const validPoolId = activeT.random_gift_pool.find(id => id != compId && window.allProducts.some(p => p.id == id));
            if (validPoolId) randId = parseInt(validPoolId);
          }
        }

        if (compId > 0) {
          const label = activeT.compulsory_gift_name ? `Free ${activeT.compulsory_gift_name}` : 'Complimentary Gift';
          targetGifts.push({ id: compId, gift_type: 'compulsory', gift_label: label });
        }
        if (randId > 0 && randId !== compId) {
          const label = activeT.random_gift_name ? `Free ${activeT.random_gift_name}` : 'Special Tester Gift';
          targetGifts.push({ id: randId, gift_type: 'random', gift_label: label });
        }
      }

      // Filter existing gift items: ONLY keep gifts that match active milestone targetGifts
      const remainingTargetGifts = [...targetGifts];

      cart = cart.filter(item => {
        if (item.is_gift) {
          const matchIdx = remainingTargetGifts.findIndex(g => g.id == item.id);
          if (matchIdx !== -1) {
            const targetGift = remainingTargetGifts[matchIdx];
            item.quantity = 1;
            item.price = 0;
            item.gift_type = targetGift.gift_type;
            item.gift_label = targetGift.gift_label;
            item.key = `gift-${targetGift.gift_type}-${item.id}`;

            // Sync product metadata if catalog is loaded
            if (window.allProducts && window.allProducts.length > 0) {
              const prod = window.allProducts.find(p => p.id == item.id);
              if (prod) {
                item.name = prod.title;
                item.image = prod.image;
                item.volume = prod.volume || '150ml';
                item.sku = prod.sku || (`GIFT-${prod.id}`);
                item.originalPrice = parseFloat(prod.regular_price || prod.price || 250);
              }
            }
            remainingTargetGifts.splice(matchIdx, 1);
            return true;
          }
          // Remove old gift from previous milestone tier
          return false;
        }
        return true;
      });

      // Inject any newly earned gift products for the active milestone
      remainingTargetGifts.forEach(targetGift => {
        let giftProduct = null;
        if (window.allProducts && window.allProducts.length > 0) {
          giftProduct = window.allProducts.find(p => p.id == targetGift.id);
        }
        if (giftProduct) {
          cart.push({
            name: giftProduct.title,
            price: 0,
            originalPrice: parseFloat(giftProduct.regular_price || giftProduct.price || 250),
            quantity: 1,
            image: giftProduct.image || "/assets/images/ruut-logo-CF6400.png",
            volume: giftProduct.volume || "150ml",
            sku: giftProduct.sku || ("GIFT-" + giftProduct.id),
            id: giftProduct.id,
            key: "gift-" + targetGift.gift_type + "-" + giftProduct.id,
            is_gift: true,
            gift_type: targetGift.gift_type,
            gift_label: targetGift.gift_label
          });
        }
      });
    } else {
      // Remove all free gift items if offer is disabled or a coupon code is active
      cart = cart.filter(item => !item.is_gift);
    }
  };

  // Render Cart Items
  const renderCart = () => {
    window.updateGiftSimulation();

    let totalQuantity = 0;
    let regularItemsQty = cart.reduce((acc, item) => acc + (isItemInOfferCategories(item) ? item.quantity : 0), 0);
    const hasActiveCouponCode = Boolean(activeCoupon && activeCoupon.trim().length > 0);
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
    const cartCountEl = document.getElementById('cartCount');
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

    const cartItemsListEl = document.getElementById('cartItemsList');
    const minicartProgressEl = document.getElementById('ruut-minicart-progress');
    const cartpageProgressEl = document.getElementById('ruut-cartpage-progress');
    const cartSubtotalOriginalEl = document.getElementById('cartSubtotalOriginal');
    const cartSubtotalEl = document.getElementById('cartSubtotal');
    const cartUpsellSectionEl = document.getElementById('cartUpsellSection');

    // If drawer list isn't present, stop here
    if (!cartItemsListEl) return;

    if (cart.length === 0) {
      if (minicartProgressEl) minicartProgressEl.style.display = 'none';
      if (cartpageProgressEl) cartpageProgressEl.style.display = 'none';
      let bestsellersHtml = '';
      if (window.allProducts) {
        const sortedByRating = [...window.allProducts].sort((a, b) => (parseFloat(b.average_rating) || 0) - (parseFloat(a.average_rating) || 0));
        const productsToSuggest = sortedByRating.slice(0, 3);

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

      cartItemsListEl.innerHTML = `
        <div class="cart-empty-state" style="text-align: center; padding: 40px 20px 20px 20px;">
          <p style="color: var(--ruut-text); margin-bottom: 20px; font-size: 0.95rem;">Your cart is empty.</p>
          <a href="/shop" class="btn-solid" style="display: inline-block; padding: 10px 25px; border-radius: 20px; font-size: 0.8rem; text-decoration: none; text-align: center; margin-bottom: 10px;">Shop Now</a>
          ${bestsellersHtml}
        </div>
      `;

      if (cartSubtotalOriginalEl) cartSubtotalOriginalEl.style.display = 'none';
      if (cartSubtotalEl) cartSubtotalEl.innerHTML = '₹0.00';
      if (cartUpsellSectionEl) cartUpsellSectionEl.style.display = 'none';
      return;
    }

    const hasQualifyingItems = regularItemsQty > 0 || (offerCategories && offerCategories.includes('all'));
    if (minicartProgressEl) minicartProgressEl.style.display = (offerEnabled && hasQualifyingItems) ? 'block' : 'none';
    if (cartpageProgressEl) cartpageProgressEl.style.display = (offerEnabled && cart.length > 0 && hasQualifyingItems) ? 'block' : 'none';

    // Clear list
    cartItemsListEl.innerHTML = '';

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
      cartItemsListEl.insertAdjacentHTML('beforeend', itemHtml);
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
        if (serverDiscountTotal !== null && serverDiscountTotal > 0) {
          discount = serverDiscountTotal;
        } else if (activeCouponSavings > 0) {
          discount = activeCouponSavings;
        } else {
          const uppercaseCode = activeCoupon.toUpperCase();
          const couponData = (window.activeCoupons || []).find(c => c.code.toUpperCase() === uppercaseCode);
          if (couponData) {
            const amt = parseFloat(couponData.amount) || 0;
            discount = couponData.discount_type === 'percent' ? discountedSubtotal * (amt / 100) : amt;
          } else if (uppercaseCode === 'TEST-12') {
            discount = 200;
          } else if (uppercaseCode === 'TEST-34') {
            discount = discountedSubtotal * 0.05;
          }
        }
      }
    }

    // Calculate active threshold level discount (Volume Discount) — Mutually exclusive with Coupon Code
    let activeDiscount = window.getActiveVolumeDiscount();

    let finalDiscountedTotal = Math.max(0, discountedSubtotal - discount - activeDiscount);

    // Update Subtotal Summary text
    if (cartSubtotalOriginalEl) {
      if (discount > 0 || activeDiscount > 0 || discountedSubtotal < originalSubtotal) {
        cartSubtotalOriginalEl.style.display = 'inline';
        cartSubtotalOriginalEl.innerHTML = `₹${originalSubtotal.toFixed(2)}`;
      } else {
        cartSubtotalOriginalEl.style.display = 'none';
      }
    }

    if (cartSubtotalEl) {
      cartSubtotalEl.innerHTML = `₹${finalDiscountedTotal.toFixed(2)}`;
    }

    // Dynamically insert/update Privilege & Volume Discount breakdown rows in minicart drawer summary
    const cartSummarySection = document.querySelector('.cart-summary-section');
    if (cartSummarySection) {
      let discountRow = cartSummarySection.querySelector('.cart-summary-discount-row');
      let volumeDiscountRow = cartSummarySection.querySelector('.cart-summary-volume-row');

      if (activeCoupon && discount > 0) {
        if (!discountRow) {
          discountRow = document.createElement('div');
          discountRow.className = 'cart-summary-row cart-summary-discount-row';
          discountRow.style.cssText = 'display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--ruut-accent); font-weight: 600; margin-bottom: 8px; align-items: center;';
          cartSummarySection.insertBefore(discountRow, cartSummarySection.firstChild);
        }
        discountRow.innerHTML = `
          <span>Privilege Discount (${activeCoupon.toUpperCase()})</span>
          <span>-₹${discount.toFixed(2)}</span>
        `;
        discountRow.style.display = 'flex';
      } else if (discountRow) {
        discountRow.style.display = 'none';
      }

      if (!activeCoupon && activeDiscount > 0) {
        if (!volumeDiscountRow) {
          volumeDiscountRow = document.createElement('div');
          volumeDiscountRow.className = 'cart-summary-row cart-summary-volume-row';
          volumeDiscountRow.style.cssText = 'display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--ruut-accent); font-weight: 600; margin-bottom: 8px; align-items: center;';
          const refNode = discountRow && discountRow.parentNode ? discountRow.nextSibling : cartSummarySection.firstChild;
          cartSummarySection.insertBefore(volumeDiscountRow, refNode);
        }
        volumeDiscountRow.innerHTML = `
          <span>Tier Discount (${regularItemsQty} Items)</span>
          <span>-₹${activeDiscount.toFixed(2)}</span>
        `;
        volumeDiscountRow.style.display = 'flex';
      } else if (volumeDiscountRow) {
        volumeDiscountRow.style.display = 'none';
      }
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
      if (!t) return '<strong>Free Gift</strong>';
      const parts = [];
      if (t.discount > 0) parts.push(`₹${t.discount.toFixed(0)} OFF`);

      let giftCount = 0;
      if (t.compulsory_gift_id > 0 || t.compulsory_gift_name) giftCount++;
      if (t.random_gift_id > 0 || (t.random_gift_pool && t.random_gift_pool.length > 0) || t.random_gift_name) giftCount++;

      if (giftCount === 1) {
        parts.push(t.compulsory_gift_name ? `Free ${t.compulsory_gift_name}` : 'Free Gift');
      } else if (giftCount >= 2) {
        parts.push(`${giftCount} Free Gifts`);
      }

      if (parts.length > 0) {
        return '<strong>' + parts.join(' + ') + '</strong>';
      }
      return '<strong>Free Gift</strong>';
    };

    if (offerEnabled && offerThresholds.length > 0 && hasQualifyingItems) {
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
        // Below first threshold (0 or 1 item)
        const nextT = offerThresholds[0];
        needed = nextT.quantity - regularItemsQty;
        const firstDotPercent = (1 / N) * 100;
        progressPercent = (regularItemsQty / nextT.quantity) * firstDotPercent;
        const itemWord = needed === 1 ? 'item' : 'items';
        nextOfferText = `Add <strong>${needed} ${itemWord}</strong> to unlock ${getOfferUnlockText(nextT)}.`;
      } else {
        if (activeIndex < N - 1) {
          // Between activeIndex and activeIndex + 1 (e.g. 2 items)
          const prevT = offerThresholds[activeIndex];
          const nextT = offerThresholds[activeIndex + 1];
          needed = nextT.quantity - regularItemsQty;

          const prevDotPercent = ((activeIndex + 1) / N) * 100;
          const nextDotPercent = ((activeIndex + 2) / N) * 100;

          const qtyDiff = nextT.quantity - prevT.quantity;
          const qtyProgress = regularItemsQty - prevT.quantity;

          progressPercent = prevDotPercent + (qtyProgress / qtyDiff) * (nextDotPercent - prevDotPercent);
          const itemWord = needed === 1 ? 'item' : 'items';
          nextOfferText = `${getOfferUnlockText(prevT)} unlocked! Add <strong>${needed} ${itemWord}</strong> to get ${getOfferUnlockText(nextT)}.`;
        } else {
          // Reached/exceeded highest threshold (3+ items)
          progressPercent = 100;
          needed = 0;
          nextOfferText = `<strong>Congratulations! You have unlocked ${getOfferUnlockText(offerThresholds[activeIndex])}!</strong>`;
        }
      }

      if (hasActiveCouponCode) {
        progressPercent = 0;
        nextOfferText = `<span>Coupon <strong>${activeCoupon.toUpperCase()}</strong> active. (Remove coupon to restore volume discount & free gifts)</span>`;
      }

      // Dynamic dots helper
      const updateProgressDots = (fillEl, wrapEl) => {
        if (!wrapEl) return;
        if (fillEl) fillEl.style.width = `${progressPercent}%`;

        // Remove existing dots
        wrapEl.querySelectorAll('.cart-progress-dot').forEach(el => el.remove());

        if (hasActiveCouponCode) {
          return; // Don't render milestone dots when coupon code is active
        }

        // Append new dots
        dots.forEach((dot, idx) => {
          const reachedClass = progressPercent >= dot.percent ? 'ruut-dot-reached' : '';
          const activeColor = progressPercent >= dot.percent ? 'var(--ruut-accent)' : 'var(--ruut-white)';

          const dotHtml = `
            <div class="cart-progress-dot ${reachedClass}" style="left: ${dot.percent}%; background-color: ${activeColor};" title="Tier ${idx + 1}: ${dot.quantity} items"></div>
          `;
          wrapEl.insertAdjacentHTML('beforeend', dotHtml);
        });
      };

      // Render dots for mini-cart
      const minicartFillEl = document.getElementById('ruutMinicartFill');
      const minicartWrap = document.querySelector('#ruut-minicart-progress .cart-progress-bar-wrap');
      if (minicartWrap) {
        updateProgressDots(minicartFillEl, minicartWrap);
        minicartWrap.style.marginBottom = '25px';
      }

      // Render dots for cart page
      const cartpageFillEl = document.getElementById('ruutCartpageFill');
      const cartpageWrap = document.querySelector('#ruut-cartpage-progress .cart-progress-bar-wrap');
      if (cartpageWrap) {
        updateProgressDots(cartpageFillEl, cartpageWrap);
        cartpageWrap.style.marginBottom = '25px';
      }

      // Update text labels
      const minicartTextEl = document.getElementById('ruutMinicartText');
      if (minicartTextEl) {
        minicartTextEl.innerHTML = nextOfferText;
      }
      const cartpageTextEl = document.getElementById('ruutCartpageText');
      if (cartpageTextEl) {
        cartpageTextEl.innerHTML = nextOfferText;
      }
    } else {
      // Hide progress bar if disabled
      const minicartProgress = document.getElementById('ruut-minicart-progress');
      if (minicartProgress) minicartProgress.style.display = 'none';
      const cartpageProgress = document.getElementById('ruut-cartpage-progress');
      if (cartpageProgress) cartpageProgress.style.display = 'none';
    }

    // Parent-category matching logic for suggestions (Strictly same category, sorted by rating)
    if (cartUpsellSection) {
      const lastItem = cart[cart.length - 1];
      let parentCategorySlug = null;
      let matchedProducts = [];

      if (window.allProducts && lastItem) {
        const lastProduct = window.allProducts.find(p => p.id == lastItem.id);
        if (lastProduct && lastProduct.categories) {
          const parentCat = lastProduct.categories.find(c => !c.parent || c.parent === 0);
          if (parentCat) {
            parentCategorySlug = parentCat.slug;
          } else if (lastProduct.categories.length > 0) {
            parentCategorySlug = lastProduct.categories[0].slug;
          }
        }

        const currentCartItemIds = cart.map(item => Number(item.id));
        if (parentCategorySlug) {
          matchedProducts = window.allProducts.filter(p => {
            if (currentCartItemIds.includes(Number(p.id))) return false;
            return p.categories && p.categories.some(c => c.slug === parentCategorySlug);
          });

          // Sort by rating descending
          matchedProducts.sort((a, b) => (parseFloat(b.average_rating) || 0) - (parseFloat(a.average_rating) || 0));
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
        <span style="display:inline-flex;align-items:center;gap:6px;font-size:0.75rem;font-weight:600;color:var(--ruut-white);letter-spacing:0.05em;text-transform:uppercase;background:var(--ruut-accent);padding:4px 12px;border-radius:4px;">${item.gift_label || 'Complimentary Gift'}</span>
      ` : `
        <div class="qty-selector" style="display:inline-flex;align-items:center;border:1px solid var(--ruut-border);border-radius:6px;">
          <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)" style="border:none;background:none;padding:4px 10px;cursor:pointer;font-size:1rem;line-height:1;">&minus;</button>
          <span class="qty-val" style="padding:0 8px;min-width:20px;text-align:center;font-size:0.9rem;">${item.quantity}</span>
          <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)" style="border:none;background:none;padding:4px 10px;cursor:pointer;font-size:1rem;line-height:1;">&plus;</button>
        </div>
        <button type="button" onclick="removeCartItem(${index})" style="border:none;background:none;color:var(--ruut-accent);cursor:pointer;font-size:0.8rem;text-decoration:underline;padding:0;">Remove</button>
      `;

      const subTag = item.category_name ? `<span class="cart-item-cat-subtag" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--ruut-gray);font-family:var(--font-heading);">${item.category_name}</span>` : '';

      return `
        <div class="cart-item ${isGift ? 'cart-item--gift' : ''}" style="display:flex;gap:18px;padding:18px;background:transparent !important;background-color:transparent !important;border-radius:12px;border:${isGift ? '1.5px dashed var(--ruut-accent)' : '1px solid rgba(102, 68, 54, 0.15)'};position:relative;overflow:hidden;align-items:center;">
          ${isGift ? `
            <div class="cart-item-reward-badge" style="position:absolute;top:0;right:0;background:var(--ruut-accent);color:var(--ruut-white);font-size:0.65rem;font-weight:600;padding:3px 12px;border-bottom-left-radius:8px;text-transform:uppercase;letter-spacing:0.05em;">Unlocked Reward</div>
          ` : ''}
          <div class="cart-item-img-wrap" style="width:100px;height:100px;flex-shrink:0;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);background:transparent;">
            <img src="${item.image}" alt="${item.name}" style="width:100%;height:100%;object-fit:cover;">
          </div>
          <div class="cart-item-details" style="flex:1;display:flex;flex-direction:column;gap:4px;">
            ${subTag}
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
              <h4 style="margin:0;font-family:var(--font-serif);font-size:1.1rem;${isGift ? 'padding-right:90px;' : ''}">${item.name}</h4>
              <div style="white-space:nowrap;">${priceDisplay}</div>
            </div>
            ${item.volume ? `<span class="cart-item-volume-text" style="font-size:0.8rem;color:#777;">Volume: ${item.volume}</span>` : ''}
            <div class="cart-item-actions-row" style="display:flex;align-items:center;gap:12px;margin-top:6px;">
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
              <span style="color: var(--ruut-gray); font-style: italic;">${item.name} (${item.gift_label || 'Free Gift'})</span>
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
      if (serverDiscountTotal !== null && serverDiscountTotal > 0) {
        discount = serverDiscountTotal;
      } else if (activeCouponSavings > 0) {
        discount = activeCouponSavings;
      } else {
        const uppercaseCode = activeCoupon.toUpperCase();
        const couponData = (window.activeCoupons || []).find(c => c.code.toUpperCase() === uppercaseCode);
        if (couponData) {
          const amt = parseFloat(couponData.amount) || 0;
          discount = couponData.discount_type === 'percent' ? regularDiscountedSubtotal * (amt / 100) : amt;
        } else if (uppercaseCode === 'TEST-12') {
          discount = 200;
        } else if (uppercaseCode === 'TEST-34') {
          discount = regularDiscountedSubtotal * 0.05;
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
      checkoutBtn.href = window.getWooCommerceRedirectUrl('checkout');
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
    let customPrice = null;

    if (productNameOrId && typeof productNameOrId === 'object' && productNameOrId.nodeType === 1) {
      btnElement = productNameOrId;
      productNameOrId = price;
      price = args[0];
      quantity = args[1] || 1;
      customPrice = args[2] !== undefined ? args[2] : null;
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
      price = (customPrice !== null) ? parseFloat(customPrice) : parseFloat(product.price);
      originalPrice = parseFloat(product.regular_price || product.price);
      volume = product.volume || '';
      sku = product.sku || ('SKU-' + product.id);
      id = product.id;
    }

    // Override with variation details if present
    if (variation) {
      id = variation.id; // variation ID goes to cart
      price = (customPrice !== null) ? parseFloat(customPrice) : parseFloat(variation.price);
      originalPrice = parseFloat(variation.regular_price || variation.price);
      volume = variation.volume || volume;
      sku = variation.sku || sku;
      if (variation.image) {
        image = variation.image;
      }
    }

    const key = 'product-' + id + (customPrice === 0 ? '-gift' : '');
    const isGift = (customPrice === 0);

    // Check if product is already in cart by ID
    const existingIndex = cart.findIndex(item => item.id == id && item.is_gift === isGift);
    if (existingIndex > -1) {
      cart[existingIndex].quantity += quantity;
    } else {
      cart.push({
        name: name,
        price: price,
        originalPrice: originalPrice,
        quantity: quantity,
        image: image,
        volume: volume,
        sku: sku,
        id: id,
        key: key,
        is_gift: isGift,
        badge: isGift ? 'FREE GIFT' : ''
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

  const handleCouponsResponse = (data) => {
    let coupons = [];
    if (Array.isArray(data)) {
      coupons = data;
    } else if (data && typeof data === 'object') {
      if (Array.isArray(data.coupons)) coupons = data.coupons;
      if (typeof data.offer_enabled !== 'undefined') offerEnabled = Boolean(data.offer_enabled);
      if (Array.isArray(data.offer_thresholds) && data.offer_thresholds.length > 0) {
        offerThresholds = data.offer_thresholds;
      }
    }
    window.activeCoupons = coupons;
    populateCouponsModal(coupons);
    renderCart();
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
        if (response && response.success && response.data) {
          handleCouponsResponse(response.data);
        } else if (response && (response.coupons || Array.isArray(response))) {
          handleCouponsResponse(response);
        } else if (couponsListContainer && (!window.activeCoupons || window.activeCoupons.length === 0)) {
          couponsListContainer.innerHTML = '<p style="text-align: center; padding: 20px; color: #a00;">No coupons available at this time.</p>';
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
        if (response && response.success && response.data) {
          handleCouponsResponse(response.data);
        } else if (response && (response.coupons || Array.isArray(response))) {
          handleCouponsResponse(response);
        }
      })
      .catch(() => { });
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
      window.navigateToCheckout(btnElement);
    }, 400);
  };

  const getCouponDiscountValue = (coupon, subtotal) => {
    const amount = parseFloat(coupon.amount) || 0;
    if (coupon.discount_type === 'percent') {
      return subtotal * (amount / 100);
    }
    return amount;
  };

  // Confetti explosion helper on privilege unlock
  window.triggerConfetti = () => {
    let canvas = document.getElementById('ruut-confetti-canvas');
    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.id = 'ruut-confetti-canvas';
      canvas.style.position = 'fixed';
      canvas.style.top = '0';
      canvas.style.left = '0';
      canvas.style.width = '100vw';
      canvas.style.height = '100vh';
      canvas.style.pointerEvents = 'none';
      canvas.style.zIndex = '99999';
      document.body.appendChild(canvas);
    }
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const colors = ['#895158', '#D4AF37', '#664436', '#cf6400', '#7d8427', '#EADBC8'];
    const particles = [];
    for (let i = 0; i < 75; i++) {
      particles.push({
        x: canvas.width / 2,
        y: canvas.height / 3,
        vx: (Math.random() - 0.5) * 12,
        vy: (Math.random() - 0.8) * 14,
        size: Math.random() * 8 + 4,
        color: colors[Math.floor(Math.random() * colors.length)],
        rotation: Math.random() * 360,
        rSpeed: (Math.random() - 0.5) * 8,
        alpha: 1
      });
    }

    let animationFrame;
    const render = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      let alive = false;
      particles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.3; // gravity
        p.rotation += p.rSpeed;
        p.alpha -= 0.015;
        if (p.alpha > 0) {
          alive = true;
          ctx.save();
          ctx.globalAlpha = Math.max(0, p.alpha);
          ctx.translate(p.x, p.y);
          ctx.rotate((p.rotation * Math.PI) / 180);
          ctx.fillStyle = p.color;
          ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
          ctx.restore();
        }
      });
      if (alive) {
        animationFrame = requestAnimationFrame(render);
      } else {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas);
      }
    };
    render();
    setTimeout(() => {
      if (animationFrame) cancelAnimationFrame(animationFrame);
      if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas);
    }, 2500);
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
          activeCoupon = code.toUpperCase();
          const savings = (response && response.data && response.data.coupon_savings) || 0;
          activeCouponSavings = savings;
          try {
            localStorage.setItem('ruut_coupon', activeCoupon);
            localStorage.setItem('ruut_coupon_savings', String(savings));
          } catch (e) { }

          if (savings > 0) {
            window.showToast(`Coupon applied successfully. You saved ₹${savings.toFixed(0)}.`, 'success');
          } else {
            window.showToast(`Applied privilege: ${code}`, 'success');
          }
          if (typeof window.triggerConfetti === 'function') {
            window.triggerConfetti();
          }
          renderCart();
          if (typeof window.renderCartPage === 'function') {
            window.renderCartPage();
          }
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
    } catch (e) { }

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



  window.loadCartPageSuggestions = () => {
    const cartPageSuggestionsGrid = document.getElementById('cartPageSuggestionsGrid');
    if (!cartPageSuggestionsGrid) return;

    const handleSuggestions = (products) => {
      // Filter out products already in the cart
      const currentCartItemIds = cart.map(item => Number(item.id));
      let candidates = products.filter(p => !currentCartItemIds.includes(Number(p.id)));

      // If no candidates left, hide section
      if (candidates.length === 0) {
        const suggestionsSection = document.getElementById('cartPageSuggestionsSection');
        if (suggestionsSection) suggestionsSection.style.display = 'none';
        return;
      }

      let matchedProducts = [];

      // Determine parent category of the last item in the cart if cart is not empty
      const lastItem = cart[cart.length - 1];
      if (lastItem) {
        const lastProduct = products.find(p => p.id == lastItem.id);
        if (lastProduct && lastProduct.categories) {
          const parentCat = lastProduct.categories.find(c => !c.parent || c.parent === 0);
          if (parentCat) {
            const parentCategorySlug = parentCat.slug;
            matchedProducts = candidates.filter(p => p.categories && p.categories.some(c => c.slug === parentCategorySlug));

            // Sort by rating descending
            matchedProducts.sort((a, b) => (parseFloat(b.average_rating) || 0) - (parseFloat(a.average_rating) || 0));
          }
        }
      } else {
        // If cart is empty, sort candidates by average_rating descending
        matchedProducts = [...candidates].sort((a, b) => (parseFloat(b.average_rating) || 0) - (parseFloat(a.average_rating) || 0));
      }

      // Limit to 3 suggested products
      const suggestionsToRender = matchedProducts.slice(0, 3);

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
      cart = [];
    } catch(e) {}
  }

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
    .catch(() => { });

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
    return window.getWooCommerceRedirectUrl(page);
  };


  const checkoutBtns = document.querySelectorAll('.checkout-btn');
  const viewCartBtns = document.querySelectorAll('.view-cart-btn');

  checkoutBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      window.navigateToCheckout(btn);
    });
  });


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
  const categoryPage = document.getElementById('categoryPage');
  const collectionGrid = document.getElementById('collectionGrid');

  if (shopPage || homeBestsellersGrid || collectionGrid || categoryPage) {

    // Sort helper for "All Products" grid & collections
    const sortProducts = (products, sortBy) => {
      const sorted = [...products];
      switch (sortBy) {
        case 'latest':
        case 'newest':
          sorted.sort((a, b) => new Date(b.date_created || 0) - new Date(a.date_created || 0) || (b.id - a.id));
          break;
        case 'rating':
          sorted.sort((a, b) => (parseFloat(b.average_rating) || 0) - (parseFloat(a.average_rating) || 0));
          break;
        case 'price-low':
          sorted.sort((a, b) => parseFloat(a.price || 0) - parseFloat(b.price || 0));
          break;
        case 'price-high':
          sorted.sort((a, b) => parseFloat(b.price || 0) - parseFloat(a.price || 0));
          break;
        case 'popularity':
        default:
          // Featured first, then by total_sales or rating
          sorted.sort((a, b) => {
            if ((b.total_sales || 0) !== (a.total_sales || 0)) {
              return (b.total_sales || 0) - (a.total_sales || 0);
            }
            if (a.is_featured && !b.is_featured) return -1;
            if (!a.is_featured && b.is_featured) return 1;
            return (parseFloat(b.average_rating) || 0) - (parseFloat(a.average_rating) || 0);
          });
          break;
      }
      return sorted;
    };

    // Transform native selects to custom styled RUUT dropdowns (eliminates native OS blue selection lists)
    const setupCustomSortDropdown = (select) => {
      if (!select || select.dataset.customSortDone) return;
      select.dataset.customSortDone = 'true';

      const parent = select.parentElement;

      const dropdown = document.createElement('div');
      dropdown.className = 'custom-sort-dropdown';

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'custom-sort-toggle';

      const updateToggleText = () => {
        const opt = select.options[select.selectedIndex] || select.options[0];
        const label = opt ? opt.textContent : 'Sort';
        toggle.innerHTML = `<span>${label}</span><span class="custom-sort-arrow">▼</span>`;
      };

      updateToggleText();

      const list = document.createElement('ul');
      list.className = 'custom-sort-list';

      const buildItems = () => {
        list.innerHTML = '';
        Array.from(select.options).forEach(opt => {
          const li = document.createElement('li');
          li.textContent = opt.textContent;
          li.dataset.value = opt.value;
          if (opt.value === select.value || opt.selected) {
            li.classList.add('active');
          }

          li.addEventListener('click', (e) => {
            e.stopPropagation();
            select.value = opt.value;
            updateToggleText();

            list.querySelectorAll('li').forEach(item => item.classList.remove('active'));
            li.classList.add('active');
            list.classList.remove('open');
            toggle.classList.remove('active');

            select.dispatchEvent(new Event('change', { bubbles: true }));
          });

          list.appendChild(li);
        });
      };

      buildItems();

      toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = list.classList.contains('open');
        document.querySelectorAll('.custom-sort-list.open').forEach(l => l.classList.remove('open'));
        document.querySelectorAll('.custom-sort-toggle.active').forEach(t => t.classList.remove('active'));

        if (!isOpen) {
          list.classList.add('open');
          toggle.classList.add('active');
        }
      });

      select.style.display = 'none';

      dropdown.appendChild(toggle);
      dropdown.appendChild(list);
      dropdown.appendChild(select);
      if (parent) parent.appendChild(dropdown);
    };

    if (!window.customSortOutsideClickAttached) {
      window.customSortOutsideClickAttached = true;
      document.addEventListener('click', () => {
        document.querySelectorAll('.custom-sort-list.open').forEach(l => l.classList.remove('open'));
        document.querySelectorAll('.custom-sort-toggle.active').forEach(t => t.classList.remove('active'));
      });
    }

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

        // Attach sort change listener & custom dropdown
        if (shopSortSelect) {
          setupCustomSortDropdown(shopSortSelect);
          shopSortSelect.addEventListener('change', () => {
            renderAllProducts(products, shopSortSelect.value);
          });
        }
      }

      // Render Category Page Products (with sorting)
      const categoryAllProductsGrid = document.getElementById('categoryAllProductsGrid');
      const categorySortSelect = document.getElementById('categorySortSelect');
      const categoryAllProductsCount = document.getElementById('categoryAllProductsCount');
      const categoryNewArrivalsGrid = document.getElementById('categoryNewArrivalsGrid');

      if (categoryAllProductsGrid) {
        const urlParams = new URLSearchParams(window.location.search);
        const catParam = (urlParams.get('category') || urlParams.get('cat') || urlParams.get('slug') || 'airmist').toLowerCase().trim();
        const normCatParam = catParam.replace(/[-_\s]/g, '');

        // Strictly filter products by category or parent category (including sub-collections like The Atlas Collection under Air Mists)
        const isAirMistCat = (normCatParam === 'airmist' || normCatParam === 'airmists');
        const isDiffuserCat = (normCatParam === 'diffuser' || normCatParam === 'diffusers');

        const catProducts = products.filter(p => {
          if (!p.categories || !Array.isArray(p.categories)) return false;
          return p.categories.some(c => {
            const slug = (c.slug || '').toLowerCase();
            const slugNorm = slug.replace(/[-_\s]/g, '');
            const nameNorm = (c.name || '').toLowerCase().replace(/[-_\s]/g, '');
            const parentSlugNorm = (c.parent_slug || '').toLowerCase().replace(/[-_\s]/g, '');

            if (isAirMistCat) {
              if (slugNorm.includes('airmist') || nameNorm.includes('airmist')) return true;
              if (slug === 'the-atlas-collection' || slugNorm === 'theatlascollection') return true;
              if (c.parent === 1 || String(c.parent) === '1') return true;
              if (parentSlugNorm.includes('airmist')) return true;
              return false;
            }

            if (isDiffuserCat) {
              if (slugNorm.includes('diffuser') || nameNorm.includes('diffuser')) return true;
              if (c.parent === 3 || String(c.parent) === '3') return true;
              if (parentSlugNorm.includes('diffuser')) return true;
              return false;
            }

            return slugNorm === normCatParam || nameNorm === normCatParam || parentSlugNorm === normCatParam;
          });
        });

        // Update category page title & hero banner dynamically
        const catTitleEl = document.getElementById('categoryPageTitleDisplay');
        const catSubEl = document.getElementById('categoryPageSubtitleDisplay');
        const catBreadcrumbEl = document.getElementById('categoryBreadcrumbCurrent');
        const catTitleMetaEl = document.getElementById('categoryPageTitle');

        let displayTitle = catParam.charAt(0).toUpperCase() + catParam.slice(1);
        if (normCatParam === 'airmist' || normCatParam === 'airmists') displayTitle = 'Air Mists';
        else if (normCatParam === 'diffuser' || normCatParam === 'diffusers') displayTitle = 'Diffusers';

        if (catTitleEl) catTitleEl.textContent = displayTitle;
        if (catSubEl) catSubEl.textContent = `Explore RUUT premium ${displayTitle}.`;
        if (catBreadcrumbEl) catBreadcrumbEl.textContent = displayTitle;
        if (catTitleMetaEl) catTitleMetaEl.textContent = `${displayTitle} | RUUT`;

        const renderCategoryProducts = (prods, sortBy) => {
          const sorted = sortProducts(prods, sortBy);
          if (sorted.length > 0) {
            categoryAllProductsGrid.innerHTML = sorted.map(p => createProductCard(p, false, false)).join('');
          } else {
            categoryAllProductsGrid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px;">No products found in ${displayTitle}.</div>`;
          }
          if (categoryAllProductsCount) {
            categoryAllProductsCount.textContent = `Showing ${sorted.length} product${sorted.length !== 1 ? 's' : ''}`;
          }
        };

        const currentCatSort = categorySortSelect ? categorySortSelect.value : 'popularity';
        if (categorySortSelect) setupCustomSortDropdown(categorySortSelect);
        renderCategoryProducts(catProducts, currentCatSort);

        if (categorySortSelect && !categorySortSelect.dataset.listenerAttached) {
          categorySortSelect.dataset.listenerAttached = 'true';
          categorySortSelect.addEventListener('change', () => {
            renderCategoryProducts(catProducts, categorySortSelect.value);
          });
        }

        if (categoryNewArrivalsGrid) {
          const catNewArrivals = [...catProducts]
            .sort((a, b) => new Date(b.date_created || 0) - new Date(a.date_created || 0))
            .slice(0, 3);
          if (catNewArrivals.length > 0) {
            categoryNewArrivalsGrid.innerHTML = catNewArrivals.map(p => createProductCard(p, false, false)).join('');
          } else {
            categoryNewArrivalsGrid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #666;">No new arrivals in ${displayTitle}.</div>`;
          }
        }
      }

      // Render Dynamic Collection Page Grid
      if (collectionGrid) {
        const urlParams = new URLSearchParams(window.location.search);
        const collectionSlug = urlParams.get('collection') || urlParams.get('category') || urlParams.get('cat') || urlParams.get('slug') || '';

        let collectionProducts = [];
        if (collectionSlug) {
          const normSlug = collectionSlug.toLowerCase().trim().replace(/[-_\s]/g, '');
          collectionProducts = products.filter(p =>
            p.categories && p.categories.some(c => {
              const cSlug = (c.slug || '').toLowerCase().replace(/[-_\s]/g, '');
              const cName = (c.name || '').toLowerCase().replace(/[-_\s]/g, '');
              return cSlug === normSlug || cName === normSlug;
            })
          );
        }

        if (collectionProducts.length === 0 && !collectionSlug) {
          collectionProducts = products; // Fallback only if no collection parameter was given
        }

        const collectionSortSelect = document.getElementById('collectionSortSelect') || document.querySelector('.collection-controls .sort-select');

        const renderCollectionProductsGrid = (prods, sortBy) => {
          const sorted = sortProducts(prods, sortBy);
          if (sorted.length > 0) {
            collectionGrid.innerHTML = sorted.map(p => createProductCard(p, false, false)).join('');
          } else {
            collectionGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--ruut-text);">No products found in this collection.</div>';
          }
          const resultsCountEl = document.getElementById('collectionResultsCount');
          if (resultsCountEl) {
            resultsCountEl.textContent = `Showing all ${sorted.length} result${sorted.length !== 1 ? 's' : ''}`;
          }
        };

        const currentSort = collectionSortSelect ? collectionSortSelect.value : 'popularity';
        if (collectionSortSelect) setupCustomSortDropdown(collectionSortSelect);
        renderCollectionProductsGrid(collectionProducts, currentSort);

        if (collectionSortSelect && !collectionSortSelect.dataset.listenerAttached) {
          collectionSortSelect.dataset.listenerAttached = 'true';
          collectionSortSelect.addEventListener('change', () => {
            renderCollectionProductsGrid(collectionProducts, collectionSortSelect.value);
          });
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

            // Update Breadcrumbs (Shop > Category > Collection)
            const breadcrumbEl = document.getElementById('breadcrumbCurrent');
            if (breadcrumbEl) breadcrumbEl.textContent = collectionData.title;

            const breadcrumbCatEl = document.getElementById('breadcrumbCategory');
            if (breadcrumbCatEl) {
              const rawParentSlug = collectionData.parent_slug || 'airmist';
              const normParent = rawParentSlug.toLowerCase().replace(/[-_\s]/g, '');
              let parentTitle = 'Air Mists';
              let parentSlug = 'airmist';

              if (normParent.includes('diffuser')) {
                parentTitle = 'Diffusers';
                parentSlug = 'diffuser';
              } else if (normParent.includes('airmist')) {
                parentTitle = 'Air Mists';
                parentSlug = 'airmist';
              } else {
                parentTitle = rawParentSlug.replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                parentSlug = rawParentSlug;
              }
              breadcrumbCatEl.textContent = parentTitle;
              breadcrumbCatEl.href = `/category.html?category=${parentSlug}`;
            }

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

          const breadcrumbCatEl = document.getElementById('breadcrumbCategory');
          if (breadcrumbCatEl) {
            let parentSlug = 'airmist';
            let parentTitle = 'Air Mists';
            if (collectionSlug.includes('diffuser')) {
              parentSlug = 'diffuser';
              parentTitle = 'Diffusers';
            }
            breadcrumbCatEl.textContent = parentTitle;
            breadcrumbCatEl.href = `/category.html?category=${parentSlug}`;
          }
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
    if (window.allProducts && window.allProducts.length > 0) {
      handleProducts(window.allProducts);
    }
    window.ensureProductsFetched()
      .then(products => {
        if (products && products.length > 0) {
          handleProducts(products);
        }
      })
      .catch(err => {
        console.error('Failed to load products from WordPress:', err);
        if (!window.allProducts || window.allProducts.length === 0) {
          handleProducts([]);
        }
      });

  }

  // --- DYNAMIC RENDERING: HOMEPAGE CATEGORY CAROUSEL ---
  const homeCollectionsGrid = document.getElementById('collectionsGrid');
  const categoryPrevBtn = document.getElementById('categoryPrevBtn');
  const categoryNextBtn = document.getElementById('categoryNextBtn');

  // URL Helper to distinguish top-level Category cards vs Sub-Category / Collection cards
  const getCategoryOrCollectionUrl = (cat) => {
    if (!cat) return '/shop.html';

    if (typeof cat === 'string') {
      const slug = cat.toLowerCase();
      if (slug === 'airmist' || slug === 'air-mist' || slug === 'air-mists' || slug === 'diffuser' || slug === 'diffusers' || slug === 'gifts') {
        const catSlug = (slug === 'air-mist' || slug === 'air-mists') ? 'airmist' : (slug === 'diffusers' ? 'diffuser' : slug);
        return `/category.html?category=${catSlug}`;
      }
      return `/collection.html?collection=${slug}`;
    }

    if (cat.url) return cat.url;

    const slug = (cat.slug || '').toLowerCase();

    if (cat.type === 'category') {
      const catSlug = (slug === 'air-mist' || slug === 'air-mists') ? 'airmist' : (slug === 'diffusers' ? 'diffuser' : slug);
      return `/category.html?category=${catSlug}`;
    }
    if (cat.type === 'collection' || cat.type === 'subcategory') {
      return `/collection.html?collection=${cat.slug}`;
    }

    const isTopCategory = (cat.parent === 0 || cat.parent === '0' || !cat.parent) &&
      (slug === 'airmist' || slug === 'air-mist' || slug === 'air-mists' || slug === 'diffuser' || slug === 'diffusers' || slug === 'gifts');

    if (isTopCategory) {
      const catSlug = (slug === 'air-mist' || slug === 'air-mists') ? 'airmist' : (slug === 'diffusers' ? 'diffuser' : slug);
      return `/category.html?category=${catSlug}`;
    }

    return `/collection.html?collection=${cat.slug}`;
  };

  window.getCategoryOrCollectionUrl = getCategoryOrCollectionUrl;

  const DEFAULT_CATEGORIES_AND_COLLECTIONS = [
    {
      id: 1,
      title: "Air Mist",
      slug: "airmist",
      type: "category",
      parent: 0,
      image: "/assets/images/tac_hero.jpg",
      count: 2
    },
    {
      id: 2,
      title: "The Atlas Collection",
      slug: "the-atlas-collection",
      type: "collection",
      parent: 1,
      image: "/assets/images/landing_hero.webp",
      count: 2
    },
    {
      id: 3,
      title: "Diffuser",
      slug: "diffuser",
      type: "category",
      parent: 0,
      image: "/assets/images/diffuser_image.webp",
      count: 1
    }
  ];

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
    let list = window.ensureArray(categories);
    if (!list || list.length === 0) {
      list = DEFAULT_CATEGORIES_AND_COLLECTIONS;
    }

    if (list && list.length > 0) {
      homeCollectionsGrid.innerHTML = list.map((cat) => {
        const url = getCategoryOrCollectionUrl(cat);
        const isComingSoon = false;

        return `
          <div class="category-card" style="position: relative;">
            ${isComingSoon ? `
              <div style="position: absolute; top: 15px; left: 15px; background: #3D4A3E; color: #fff; font-size: 0.7rem; font-weight: 600; padding: 4px 12px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.05em; z-index: 10;">Coming Soon</div>
            ` : ''}
            <div class="category-card-inner" style="${isComingSoon ? 'filter: grayscale(40%) opacity(0.85); pointer-events: none;' : ''}">
              <img src="${cat.image || '/assets/images/tac_hero.jpg'}" alt="${cat.title}">
              <div class="category-content">
                ${isComingSoon ? `<span class="category-subtitle">Launching Soon</span>` : ''}
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
  const categoryPageSubcategoriesRow = document.getElementById('categoryPageSubcategoriesRow');

  // Normalize category slugs (handles air-mists, air-mist, airmists, airmist, diffusers, diffuser, etc.)
  const normCatSlug = (s) => (s || '').toLowerCase().replace(/[-_\s]/g, '').replace(/s$/, '');

  // Filter to only show sub-categories (collections) that belong to the current category
  const filterSubcategoriesOnly = (list) => {
    const urlParams = new URLSearchParams(window.location.search);
    const rawCatSlug = (urlParams.get('category') || urlParams.get('cat') || urlParams.get('slug') || 'airmist').toLowerCase().trim();
    const currentNorm = normCatSlug(rawCatSlug);

    // Find parent category object in list if available
    const parentCategoryObj = list.find(c =>
      (c.slug && normCatSlug(c.slug) === currentNorm) ||
      (c.title && normCatSlug(c.title) === currentNorm)
    );

    const filtered = list.filter(cat => {
      const catSlugNorm = normCatSlug(cat.slug);

      // Exclude main top-level categories (Air Mist, Diffuser, etc.)
      if (cat.type === 'category' && (cat.parent === 0 || !cat.parent)) return false;
      if (catSlugNorm === 'airmist' || catSlugNorm === 'diffuser') return false;

      // Must be a subcategory or collection (parent !== 0)
      const isSubcat = cat.type === 'collection' || cat.type === 'subcategory' || (cat.parent && cat.parent !== 0 && cat.parent !== '0');
      if (!isSubcat) return false;

      // Match current category parent
      if (currentNorm) {
        if (cat.parent_slug) {
          const parentSlugNorm = normCatSlug(cat.parent_slug);
          if (parentSlugNorm === currentNorm) return true;
        }
        if (parentCategoryObj && cat.parent) {
          if (cat.parent === parentCategoryObj.id || String(cat.parent) === String(parentCategoryObj.id)) return true;
        }
        if (currentNorm === 'airmist') {
          if (cat.parent === 1 || String(cat.parent) === '1') return true;
          if (cat.parent_slug && normCatSlug(cat.parent_slug) === 'airmist') return true;
        }
        return false;
      }
      return false;
    });

    return filtered;
  };

  const renderCategorySpheres = (categories, targetEl = shopCategorySpheres, allowFallback = true) => {
    let list = window.ensureArray(categories);
    if ((!list || list.length === 0) && allowFallback) {
      list = DEFAULT_CATEGORIES_AND_COLLECTIONS;
    }
    if (targetEl) {
      const section = targetEl.closest('.shop-categories-section');
      if (list && list.length > 0) {
        targetEl.innerHTML = list.map(cat => {
          const url = getCategoryOrCollectionUrl(cat);
          return `
            <a href="${url}" class="shop-category-sphere">
              <img src="${cat.image || '/assets/images/tac_hero.jpg'}" alt="${cat.title}" class="shop-category-sphere-img">
              <span class="shop-category-sphere-label">${cat.title}</span>
            </a>
          `;
        }).join('');
        if (section) section.style.display = targetEl.innerHTML.trim() ? '' : 'none';
      } else {
        targetEl.innerHTML = '';
        if (section) section.style.display = 'none';
      }
    }
  };

  const updateHeaderNavigationSubmenus = (categories) => {
    const list = window.ensureArray(categories);
    if (!list || list.length === 0) return;

    const normCatSlug = (s) => (s || '').toLowerCase().replace(/[-_\s]/g, '').replace(/s$/, '');

    ['airmist', 'diffuser'].forEach(catKey => {
      const targetNorm = normCatSlug(catKey);
      const parentCategoryObj = list.find(c =>
        (c.slug && normCatSlug(c.slug) === targetNorm) ||
        (c.title && normCatSlug(c.title) === targetNorm)
      );

      const subcats = list.filter(cat => {
        const catSlugNorm = normCatSlug(cat.slug);
        if (cat.type === 'category' && (cat.parent === 0 || !cat.parent)) return false;
        if (catSlugNorm === 'airmist' || catSlugNorm === 'diffuser') return false;

        const isSubcat = cat.type === 'collection' || cat.type === 'subcategory' || (cat.parent && cat.parent !== 0 && cat.parent !== '0');
        if (!isSubcat) return false;

        if (cat.parent_slug && normCatSlug(cat.parent_slug) === targetNorm) return true;
        if (parentCategoryObj && cat.parent && (cat.parent === parentCategoryObj.id || String(cat.parent) === String(parentCategoryObj.id))) return true;
        if (targetNorm === 'airmist' && (cat.parent === 1 || String(cat.parent) === '1')) return true;

        return false;
      });

      const desktopSubmenu = document.getElementById(`nav-submenu-${catKey}`);
      const mobileSubmenu = document.getElementById(`mobile-submenu-${catKey}`);

      const desktopLi = desktopSubmenu ? desktopSubmenu.closest('li') : null;
      const mobileLi = mobileSubmenu ? mobileSubmenu.closest('li') : null;

      if (subcats.length > 0) {
        if (desktopLi) desktopLi.classList.add('nav-item-has-children');
        if (mobileLi) mobileLi.classList.add('mobile-nav-item-has-children');

        const html = subcats.map(sub => `<li><a href="/collection.html?collection=${sub.slug}">${sub.title}</a></li>`).join('');
        if (desktopSubmenu) {
          desktopSubmenu.style.display = '';
          desktopSubmenu.innerHTML = html;
        }
        if (mobileSubmenu) {
          mobileSubmenu.style.display = '';
          mobileSubmenu.innerHTML = html;
        }
      } else {
        if (desktopLi) desktopLi.classList.remove('nav-item-has-children');
        if (mobileLi) mobileLi.classList.remove('mobile-nav-item-has-children');

        if (desktopSubmenu) {
          desktopSubmenu.style.display = 'none';
          desktopSubmenu.innerHTML = '';
        }
        if (mobileSubmenu) {
          mobileSubmenu.style.display = 'none';
          mobileSubmenu.innerHTML = '';
        }
        if (mobileLi) {
          const toggleBtn = mobileLi.querySelector('.mobile-dropdown-toggle');
          if (toggleBtn) toggleBtn.style.display = 'none';
        }
      }
    });
  };

  if (document.getElementById('mainHeader') || (homeCollectionsGrid && categoryPrevBtn && categoryNextBtn) || shopCategorySpheres || categoryPageSubcategoriesRow) {
    ruutFetch('/wp-json/ruut/v1/collections')
      .then(res => {
        if (!res.ok) throw new Error('API down');
        return res.json();
      })
      .then(categories => {
        const list = window.ensureArray(categories);
        if (!list || list.length === 0) throw new Error('Empty categories');
        if (homeCollectionsGrid) handleCollections(list);
        if (shopCategorySpheres) renderCategorySpheres(list.filter(c => c.type === 'category' || c.parent === 0), shopCategorySpheres);
        if (categoryPageSubcategoriesRow) renderCategorySpheres(filterSubcategoriesOnly(list), categoryPageSubcategoriesRow, false);
        updateHeaderNavigationSubmenus(list);
      })
      .catch(err => {
        console.error('Failed to load collections from WordPress, using fallback:', err);
        if (homeCollectionsGrid) handleCollections(DEFAULT_CATEGORIES_AND_COLLECTIONS);
        if (shopCategorySpheres) renderCategorySpheres(DEFAULT_CATEGORIES_AND_COLLECTIONS.filter(c => c.type === 'category' || c.parent === 0), shopCategorySpheres);
        if (categoryPageSubcategoriesRow) renderCategorySpheres(filterSubcategoriesOnly(DEFAULT_CATEGORIES_AND_COLLECTIONS), categoryPageSubcategoriesRow, false);
        updateHeaderNavigationSubmenus(DEFAULT_CATEGORIES_AND_COLLECTIONS);
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

  // --- DYNAMIC RENDERING: RELATED PRODUCTS (LINKED PRODUCTS / CROSS-SELLS / UPSELLS) ---
  window.loadRelatedProducts = (currentProduct) => {
    const relatedGrid = document.getElementById('relatedProductsGrid');
    if (!relatedGrid) return;

    const handleRelated = (products) => {
      // Exclude current product
      let candidates = products.filter(p => p.id != currentProduct.id);

      // Extract linked products (Cross-sells & Upsells) configured in WordPress for currentProduct
      let linkedIds = [];
      if (currentProduct.cross_sell_ids && Array.isArray(currentProduct.cross_sell_ids) && currentProduct.cross_sell_ids.length > 0) {
        linkedIds = [...linkedIds, ...currentProduct.cross_sell_ids];
      }
      if (currentProduct.upsell_ids && Array.isArray(currentProduct.upsell_ids) && currentProduct.upsell_ids.length > 0) {
        linkedIds = [...linkedIds, ...currentProduct.upsell_ids];
      }
      if (currentProduct.linked_product_ids && Array.isArray(currentProduct.linked_product_ids)) {
        linkedIds = [...linkedIds, ...currentProduct.linked_product_ids];
      }
      // Unique numeric linked IDs
      linkedIds = [...new Set(linkedIds.map(id => Number(id)).filter(id => id > 0 && id != currentProduct.id))];

      let linkedMatches = [];
      if (linkedIds.length > 0) {
        // Find matching product objects in catalog, maintaining the order defined in WordPress
        linkedIds.forEach(targetId => {
          const match = candidates.find(p => p.id == targetId);
          if (match && !linkedMatches.some(m => m.id == match.id)) {
            linkedMatches.push(match);
          }
        });
      }

      // Priority 1: Linked products (Cross-sells / Upsells) configured in WordPress
      let priority1 = linkedMatches;

      // Determine parent categories of current product (parent is 0 or undefined)
      const currentParentCats = currentProduct.categories
        ? currentProduct.categories.filter(c => !c.parent || c.parent === 0).map(c => c.slug)
        : [];

      // Priority 2: Match parent categories (excluding priority 1 linked matches)
      let priority2 = candidates.filter(p =>
        !priority1.some(p1 => p1.id == p.id) &&
        p.categories && p.categories.some(c => (!c.parent || c.parent === 0) && currentParentCats.includes(c.slug))
      );

      // Combine priority sets and limit to 4 items
      const related = [...priority1, ...priority2].slice(0, 4);

      if (related.length > 0) {
        relatedGrid.innerHTML = related.map(p => createProductCard(p, false, false)).join('');
        const relatedSection = document.getElementById('relatedProductsSection');
        if (relatedSection) relatedSection.style.display = 'block';
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
    const productId = urlParams.get('product') || urlParams.get('id') || urlParams.get('product_id') || urlParams.get('p');
    const productSlug = urlParams.get('slug') || urlParams.get('name');

    window.ensureProductsFetched().then(products => {
      const resolveProduct = (catProducts) => {
        if (!catProducts || catProducts.length === 0) return null;
        let found = null;
        if (productId) {
          found = catProducts.find(p => p.id == productId);
          if (!found) {
            found = catProducts.find(p => p.variations && p.variations.some(v => v.id == productId));
          }
        }
        if (!found && productSlug) {
          found = catProducts.find(p => p.slug && p.slug.toLowerCase() === productSlug.toLowerCase());
        }
        return found;
      };

      let product = resolveProduct(products || []);

      const renderProductDetails = (product) => {
        if (!product) {
          const mainContainer = document.querySelector('#productPage main');
          if (mainContainer) {
            mainContainer.innerHTML = '<h2 style="text-align: center; margin: 80px 0; font-family: var(--font-serif); color: var(--ruut-text);">Product not found.</h2>';
          }
          return;
        }
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

        // Smooth image slide helper function
        const changeProductImageWithSlide = (newSrc) => {
          if (!productImage || !newSrc || productImage.src === newSrc) return;
          const container = document.getElementById('productMainImageWrap');
          if (!container) {
            productImage.src = newSrc;
            return;
          }

          // Clean up any ongoing transitions
          const existingOutgoing = container.querySelectorAll('.outgoing-image');
          existingOutgoing.forEach(el => el.remove());

          // 1. Create the outgoing image clone
          const outgoingImg = document.createElement('img');
          outgoingImg.src = productImage.src;
          outgoingImg.className = 'outgoing-image';
          outgoingImg.style.borderRadius = '8px';
          container.appendChild(outgoingImg);

          // 2. Prepare incoming image
          productImage.style.transition = 'none';
          productImage.style.transform = 'translateX(100%)';
          productImage.style.opacity = '0';
          productImage.src = newSrc;

          // Force reflow to register the starting position
          void productImage.offsetWidth;
          void outgoingImg.offsetWidth;

          // 3. Start transitions
          productImage.style.transition = 'transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.5s cubic-bezier(0.25, 0.8, 0.25, 1)';
          productImage.style.transform = 'translateX(0)';
          productImage.style.opacity = '1';

          outgoingImg.style.transform = 'translateX(-100%)';
          outgoingImg.style.opacity = '0';

          // 4. Cleanup outgoing image after transition completes
          setTimeout(() => {
            if (outgoingImg && outgoingImg.parentNode) {
              outgoingImg.parentNode.removeChild(outgoingImg);
            }
          }, 500);
        };

        // Product Gallery Scroll Slide-In Observer
        const galleryContainer = document.querySelector('.product-gallery-container');
        if (galleryContainer) {
          const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
              if (entry.isIntersecting) {
                galleryContainer.classList.add('slide-in-active');
                observer.unobserve(galleryContainer);
              }
            });
          }, { threshold: 0.1 });
          observer.observe(galleryContainer);
          setTimeout(() => {
            galleryContainer.classList.add('slide-in-active');
          }, 50);
        }

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
                changeProductImageWithSlide(product.gallery[idx]);
              }
            });
          });
        }

        // 2. DYNAMIC VOLUMES & VARIATIONS SELECTOR
        const volumeSelector = document.getElementById('productVolumeSelector');
        const volumeValue = document.getElementById('productVolumeValue');

        if (volumeSelector && product.volumes && product.volumes.length > 0) {
          volumeSelector.style.display = 'flex';
          if (volumeValue) {
            volumeValue.textContent = product.volumes.join(', ');
          }

          const selectedVolume = product.volumes[0].trim();

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
                changeProductImageWithSlide(variation.image);
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
        }

        // Essential Oil Badge
        if (product.is_essential_oil) {
          const existingBadge = document.getElementById('essentialOilUsageBadge');
          if (!existingBadge && productTitle && productTitle.parentNode) {
            const badgeDiv = document.createElement('div');
            badgeDiv.id = 'essentialOilUsageBadge';
            badgeDiv.style.cssText = 'background: rgba(113, 0, 55, 0.06); border: 1px solid rgba(113, 0, 55, 0.2); border-radius: 6px; padding: 12px 16px; margin: 15px 0 25px 0; font-size: 0.85rem; color: #3B241C; display: flex; align-items: center; gap: 10px;';
            badgeDiv.innerHTML = '<span>✨</span> <div><strong>100% Pure Essential Oil:</strong> Formulated for direct use in RUUT Ambient & Waterless Nebulizing Diffusers. No water dilution required.</div>';
            productTitle.parentNode.insertBefore(badgeDiv, productTitle.nextSibling);
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
        const fallbackDirections = product.is_essential_oil
          ? "Insert or attach directly into your RUUT Diffuser bottle. Turn on your RUUT Diffuser to experience pure waterless cold-mist diffusion. No water dilution required."
          : "Spray 2-3 times in the air towards the center of the room. Keep the nozzle at least 30 cm away from walls and fabrics. Shake well before use.";
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

        if (product.scent_details && Array.isArray(product.scent_details) && product.scent_details.length > 0) {
          const sectionIds = ['infoFeelsLikeSection', 'infoTheStorySection', 'infoTheNameSection', 'infoDirectionsSection', 'infoCautionsSection'];
          const elems = [infoFeelsLike, infoTheStory, infoTheName, infoDirections, infoCautions];
          
          product.scent_details.forEach((detail, idx) => {
            if (idx < sectionIds.length) {
              const secId = sectionIds[idx];
              const elem = elems[idx];
              const sec = document.getElementById(secId);
              if (sec) {
                const headerSpan = sec.querySelector('.info-accordion-header span');
                if (headerSpan && detail.title) {
                  headerSpan.textContent = detail.title;
                }
                showIfContent(elem, secId, detail.content || detail.value);
              }
            }
          });
        } else {
          showIfContent(infoTheName, 'infoTheNameSection', product.the_name || fallbackTheName);
          showIfContent(infoFeelsLike, 'infoFeelsLikeSection', product.feels_like || fallbackFeelsLike);
          showIfContent(infoTheStory, 'infoTheStorySection', product.the_story || fallbackTheStory);
          showIfContent(infoDirections, 'infoDirectionsSection', product.directions_for_use || product.directions || fallbackDirections);
          showIfContent(infoCautions, 'infoCautionsSection', product.cautions_warnings || product.cautions || fallbackCautions);
        }


        // Info accordion interactions (RUUT Scent Details: All closed by default, smooth scroll to center on open)
        const infoAccordionItems = document.querySelectorAll('.info-accordion-item');
        infoAccordionItems.forEach(item => {
          const header = item.querySelector('.info-accordion-header');
          const body = item.querySelector('.info-accordion-body');
          const icon = item.querySelector('.info-icon');
          if (!header || !body) return;

          header.addEventListener('click', () => {
            const isOpen = item.classList.contains('open');

            // Close all other items
            infoAccordionItems.forEach(other => {
              other.classList.remove('open');
              const otherBody = other.querySelector('.info-accordion-body');
              const otherIcon = other.querySelector('.info-icon');
              if (otherBody) otherBody.style.maxHeight = '0';
              if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
            });

            if (!isOpen) {
              item.classList.add('open');
              body.style.maxHeight = body.scrollHeight + 'px';
              if (icon) icon.style.transform = 'rotate(45deg)';

              // Smoothly scroll the opened item into center of the viewport
              setTimeout(() => {
                item.scrollIntoView({
                  behavior: 'smooth',
                  block: 'center'
                });
              }, 220);
            }
          });
        });

        // 2.7 POPULATE SUB-CATEGORY / COLLECTION
        const subCat = (product.categories && product.categories.find(c => c.parent && c.parent !== 0)) ||
          (product.categories && product.categories.find(c => c.name && c.name.toLowerCase().includes('collection'))) ||
          (product.categories && product.categories.length > 1 ? product.categories[1] : null) ||
          { name: 'The Atlas Collection', slug: 'the-atlas-collection' };

        const subCatName = subCat.name || 'The Atlas Collection';
        const subCatUrl = getCategoryOrCollectionUrl(subCat);

        const collectionTagLink = document.getElementById('productCollectionTagLink');
        if (collectionTagLink) {
          collectionTagLink.textContent = subCatName;
          if (collectionTagLink.tagName === 'A') {
            collectionTagLink.href = subCatUrl;
          }
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
                <div class="faq-item" style="border-bottom: 1px solid rgba(0,0,0,0.06); padding: 0;">
                  <div class="faq-header" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-family: var(--font-heading); font-size: 0.95rem; font-weight: 600; color: var(--ruut-text); padding: 15px 5px;">
                    <span>${faq.question}</span>
                    <span class="faq-icon" style="transition: transform 0.3s ease; font-size: 1.2rem; min-width: 24px; text-align: center;">+</span>
                  </div>
                  <div class="faq-body" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s ease; padding-top: 0; padding-bottom: 0; color: var(--ruut-gray); font-size: 0.9rem; line-height: 1.5; padding-left: 5px; padding-right: 5px;">
                    <p style="margin: 0; padding-bottom: 15px;">${faq.answer}</p>
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

        // Bind Quantity selector & Add to Cart / Buy Now buttons on the single product page
        const qtyMinusBtn = document.getElementById('qtyMinusBtn');
        const qtyPlusBtn = document.getElementById('qtyPlusBtn');
        const qtyDisplayVal = document.getElementById('qtyDisplayVal');
        let currentQuantity = 1;

        if (qtyMinusBtn && qtyPlusBtn && qtyDisplayVal) {
          currentQuantity = parseInt(qtyDisplayVal.textContent) || 1;

          qtyMinusBtn.onclick = (e) => {
            e.preventDefault();
            if (currentQuantity > 1) {
              currentQuantity--;
              qtyDisplayVal.textContent = currentQuantity;
            }
          };

          qtyPlusBtn.onclick = (e) => {
            e.preventDefault();
            currentQuantity++;
            qtyDisplayVal.textContent = currentQuantity;
          };
        }

        // 6.5 DIFFUSER ESSENTIAL OIL SELECTION SWATCHES
        let selectedDiffuserOil = null;
        if (product.is_diffuser) {
          let oilsList = (product.available_oils && Array.isArray(product.available_oils)) ? [...product.available_oils] : [];

          // Also check window.allProducts for any oil products to ensure ALL created oils display
          if (window.allProducts && Array.isArray(window.allProducts)) {
            window.allProducts.forEach(p => {
              if (!p || Number(p.id) === Number(product.id)) return;
              const titleLower = (p.title || '').toLowerCase();
              const isOil = p.is_essential_oil || titleLower.includes('oil') || titleLower.includes('essential');
              if (isOil && !oilsList.some(o => Number(o.id) === Number(p.id))) {
                oilsList.push({
                  id: p.id,
                  title: p.title,
                  price: p.price || 0,
                  promo_price: 0,
                  promo_label: 'FREE (1st Purchase)',
                  formatted_promo_price: 'FREE',
                  image: p.image || '/assets/images/default_product.png',
                  short_description: p.description || ''
                });
              }
            });
          }

          if (oilsList.length > 0) {
            const qtySelectorDiv = document.querySelector('.product-qty-selector');
            const qtyParent = qtySelectorDiv ? qtySelectorDiv.closest('div[style*="gap"]') : null;
            
            if (qtyParent) {
              let oilBlock = document.getElementById('diffuserOilSelectorBlock');
              if (!oilBlock) {
                oilBlock = document.createElement('div');
                oilBlock.id = 'diffuserOilSelectorBlock';
                oilBlock.style.cssText = 'margin: 20px 0; padding: 18px; background: #FAF7F2; border: 1px solid rgba(113, 0, 55, 0.15); border-radius: 8px;';
                qtyParent.parentNode.insertBefore(oilBlock, qtyParent);
              }

              const promoLabel = oilsList[0].promo_label || 'Includes 1x Essential Oil';
              const initialRowOils = oilsList.slice(0, 3);
              const remainingOils = oilsList.slice(3);

              let oilsHtml = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
                  <label style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; font-weight:700; color:var(--ruut-text);">CHOOSE INCLUDED ESSENTIAL OIL</label>
                  <span style="font-size:0.75rem; background:rgba(113,0,55,0.1); color:var(--ruut-accent); padding:3px 8px; border-radius:4px; font-weight:600;">🎁 ${promoLabel}</span>
                </div>

                <!-- First Row of Essential Oils -->
                <div class="diffuser-oil-swatches-initial" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap:10px;">
              `;

              initialRowOils.forEach((oil, idx) => {
                const isDefault = idx === 0;
                if (isDefault) selectedDiffuserOil = oil;
                const priceText = oil.promo_price === 0 ? 'FREE' : (oil.formatted_promo_price || ('₹' + oil.promo_price));
                
                oilsHtml += `
                  <div class="oil-swatch-card ${isDefault ? 'active' : ''}" data-oil-id="${oil.id}" style="cursor:pointer; border:${isDefault ? '2px solid var(--ruut-accent)' : '1px solid #ddd'}; background:#fff; border-radius:6px; padding:10px; text-align:center; transition:all 0.2s ease; position:relative;">
                    <img src="${oil.image}" alt="${oil.title}" style="width:45px; height:45px; object-fit:cover; border-radius:4px; margin:0 auto 6px; display:block;" />
                    <div style="font-size:0.75rem; font-weight:600; color:#333; line-height:1.2; height:2.4em; overflow:hidden;">${oil.title}</div>
                    <div style="font-size:0.72rem; color:var(--ruut-accent); font-weight:700; margin-top:4px;">${priceText}</div>
                  </div>
                `;
              });

              oilsHtml += `</div>`;

              // Expandable Grid for Remaining Oils (if > 3 oils)
              if (remainingOils.length > 0) {
                oilsHtml += `
                  <div id="diffuserOilMoreContainer" style="display:none; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap:10px; margin-top:10px; padding-top:10px; border-top:1px dashed rgba(113,0,55,0.2);">
                `;

                remainingOils.forEach((oil) => {
                  const priceText = oil.promo_price === 0 ? 'FREE' : (oil.formatted_promo_price || ('₹' + oil.promo_price));
                  
                  oilsHtml += `
                    <div class="oil-swatch-card" data-oil-id="${oil.id}" style="cursor:pointer; border:1px solid #ddd; background:#fff; border-radius:6px; padding:10px; text-align:center; transition:all 0.2s ease; position:relative;">
                      <img src="${oil.image}" alt="${oil.title}" style="width:45px; height:45px; object-fit:cover; border-radius:4px; margin:0 auto 6px; display:block;" />
                      <div style="font-size:0.75rem; font-weight:600; color:#333; line-height:1.2; height:2.4em; overflow:hidden;">${oil.title}</div>
                      <div style="font-size:0.72rem; color:var(--ruut-accent); font-weight:700; margin-top:4px;">${priceText}</div>
                    </div>
                  `;
                });

                oilsHtml += `</div>`;

                // View More / Expand Button
                oilsHtml += `
                  <button type="button" id="diffuserOilViewMoreBtn" style="width:100%; margin-top:12px; padding:8px 12px; background:transparent; border:1px solid var(--ruut-accent); color:var(--ruut-accent); border-radius:4px; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; cursor:pointer; transition:all 0.2s ease;">
                    + View All Fragrances (${remainingOils.length} More) ▾
                  </button>
                `;
              }

              oilBlock.innerHTML = oilsHtml;

              // Bind Click handlers & Expand toggle
              const swatchCards = oilBlock.querySelectorAll('.oil-swatch-card');
              const viewMoreBtn = oilBlock.querySelector('#diffuserOilViewMoreBtn');
              const moreContainer = oilBlock.querySelector('#diffuserOilMoreContainer');

              const selectOilById = (oilId) => {
                selectedDiffuserOil = oilsList.find(o => Number(o.id) === Number(oilId)) || null;
                swatchCards.forEach(card => {
                  const cardOilId = Number(card.getAttribute('data-oil-id'));
                  if (cardOilId === Number(oilId)) {
                    card.classList.add('active');
                    card.style.border = '2px solid var(--ruut-accent)';
                  } else {
                    card.classList.remove('active');
                    card.style.border = '1px solid #ddd';
                  }
                });
              };

              swatchCards.forEach(card => {
                card.onclick = () => {
                  const oilId = Number(card.getAttribute('data-oil-id'));
                  selectOilById(oilId);
                };
              });

              if (viewMoreBtn && moreContainer) {
                let isExpanded = false;
                viewMoreBtn.onclick = () => {
                  isExpanded = !isExpanded;
                  if (isExpanded) {
                    moreContainer.style.display = 'grid';
                    viewMoreBtn.textContent = 'Show Less ▴';
                  } else {
                    moreContainer.style.display = 'none';
                    viewMoreBtn.textContent = `+ View All Fragrances (${remainingOils.length} More) ▾`;
                  }
                };
              }
            }
          }
        }

        const addToCartBtn = document.getElementById('addToCartBtn');
        const buyNowBtn = document.getElementById('buyNowBtn');

        if (addToCartBtn) {
          addToCartBtn.removeAttribute('onclick');
          addToCartBtn.onclick = (e) => {
            e.preventDefault();

            // If diffuser, push oil to cart FIRST (before addToCart which triggers server sync)
            if (product.is_diffuser && selectedDiffuserOil) {
              const oilPromoPrice = selectedDiffuserOil.promo_price;
              const oilItem = window.allProducts ? window.allProducts.find(p => p.id == selectedDiffuserOil.id) : null;
              const oilName = oilItem ? oilItem.title : (selectedDiffuserOil.title || 'Essential Oil');
              const oilImage = oilItem ? oilItem.image : (selectedDiffuserOil.image || '');
              const oilOrigPrice = oilItem ? parseFloat(oilItem.regular_price || oilItem.price) : (selectedDiffuserOil.price || oilPromoPrice);
              const oilId = selectedDiffuserOil.id;
              const oilKey = 'product-' + oilId + (oilPromoPrice === 0 ? '-gift' : '');
              const isOilGift = (oilPromoPrice === 0);
              const existingOilIdx = cart.findIndex(item => item.id == oilId && item.is_gift === isOilGift);
              if (existingOilIdx > -1) {
                cart[existingOilIdx].quantity += currentQuantity;
              } else {
                cart.push({
                  name: oilName,
                  price: oilPromoPrice,
                  originalPrice: oilOrigPrice,
                  quantity: currentQuantity,
                  image: oilImage,
                  volume: '',
                  sku: 'SKU-' + oilId,
                  id: oilId,
                  key: oilKey,
                  is_gift: isOilGift,
                  badge: isOilGift ? 'FREE GIFT' : ''
                });
              }

              // Show loading text on button immediately
              const originalText = addToCartBtn.textContent;
              addToCartBtn.textContent = 'ADDING...';
              addToCartBtn.disabled = true;

              // Sync oil first, then the diffuser
              syncCartWithServer('ruut_add_to_cart', { product_id: oilId, quantity: currentQuantity })
                .then(() => {
                  window.addToCart(addToCartBtn, selectedProductId, selectedPrice, currentQuantity);
                })
                .catch(() => {
                  window.addToCart(addToCartBtn, selectedProductId, selectedPrice, currentQuantity);
                });
            } else {
              window.addToCart(addToCartBtn, selectedProductId, selectedPrice, currentQuantity);
            }
          };
        }
        if (buyNowBtn) {
          buyNowBtn.removeAttribute('onclick');
          buyNowBtn.onclick = (e) => {
            e.preventDefault();
            if (product.is_diffuser && selectedDiffuserOil) {
              const oilPromoPrice = selectedDiffuserOil.promo_price;
              const oilItem = window.allProducts ? window.allProducts.find(p => p.id == selectedDiffuserOil.id) : null;
              const oilName = oilItem ? oilItem.title : (selectedDiffuserOil.title || 'Essential Oil');
              const oilImage = oilItem ? oilItem.image : (selectedDiffuserOil.image || '');
              const oilOrigPrice = oilItem ? parseFloat(oilItem.regular_price || oilItem.price) : (selectedDiffuserOil.price || oilPromoPrice);
              const oilId = selectedDiffuserOil.id;
              const oilKey = 'product-' + oilId + (oilPromoPrice === 0 ? '-gift' : '');
              const isOilGift = (oilPromoPrice === 0);
              const existingOilIdx = cart.findIndex(item => item.id == oilId && item.is_gift === isOilGift);
              if (existingOilIdx > -1) {
                cart[existingOilIdx].quantity += currentQuantity;
              } else {
                cart.push({
                  name: oilName,
                  price: oilPromoPrice,
                  originalPrice: oilOrigPrice,
                  quantity: currentQuantity,
                  image: oilImage,
                  volume: '',
                  sku: 'SKU-' + oilId,
                  id: oilId,
                  key: oilKey,
                  is_gift: isOilGift,
                  badge: isOilGift ? 'FREE GIFT' : ''
                });
              }

              // Show loading text on button immediately
              const originalText = buyNowBtn.textContent;
              buyNowBtn.textContent = 'ADDING...';
              buyNowBtn.disabled = true;

              // Sync oil first, then buy the diffuser
              syncCartWithServer('ruut_add_to_cart', { product_id: oilId, quantity: currentQuantity })
                .then(() => {
                  window.buyNow(buyNowBtn, selectedProductId, selectedPrice, currentQuantity);
                })
                .catch(() => {
                  window.buyNow(buyNowBtn, selectedProductId, selectedPrice, currentQuantity);
                });
            } else {
              window.buyNow(buyNowBtn, selectedProductId, selectedPrice, currentQuantity);
            }
          };
        }
      };

      if (product) {
        renderProductDetails(product);
      } else if (productId) {
        // Fetch specific product ID (e.g. 2489) directly from WP REST API
        window.ruutFetch('/wp-json/ruut/v1/products?id=' + encodeURIComponent(productId))
          .then(res => res.json())
          .then(singleData => {
            if (singleData && (singleData.id || singleData.title)) {
              renderProductDetails(singleData);
            } else if (products && products.length > 0) {
              renderProductDetails(products[0]);
            } else {
              renderProductDetails(null);
            }
          })
          .catch(() => {
            if (products && products.length > 0) {
              renderProductDetails(products[0]);
            } else {
              renderProductDetails(null);
            }
          });
      } else if (products && products.length > 0) {
        renderProductDetails(products[0]);
      } else {
        renderProductDetails(null);
      }
    });
  }

});

// --- INLINE HEADER SEARCH INTERACTION ---
window.getFuzzyScore = (product, query) => {
  const q = query.toLowerCase();
  const title = (product.title || '').toLowerCase();
  const desc = (product.description || '').toLowerCase();
  let score = 0;
  if (title === q) score += 1.0;
  else if (title.startsWith(q)) score += 0.8;
  else if (title.includes(q)) score += 0.6;
  if (desc.includes(q)) score += 0.3;
  return score;
};

window.initHeaderSearch = () => {
  const searchContainer = document.querySelector('.header-search-container');
  const inputField = document.getElementById('headerSearchInputField');
  const suggestionsBox = document.getElementById('headerSearchSuggestions');
  const searchBtn = document.getElementById('searchButton');

  if (searchContainer && window.innerWidth > 768) {
    searchContainer.classList.add('active');
  }

  if (searchBtn && inputField && suggestionsBox && searchContainer) {
    const isMobile = () => window.innerWidth <= 768;

    const handleSearchAction = () => {
      const val = inputField.value.trim();
      if (val.length > 0) {
        window.location.href = `/category.html?search=${encodeURIComponent(val)}`;
      } else {
        inputField.focus();
      }
    };

    searchBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (isMobile()) {
        const isActive = searchContainer.classList.contains('active');
        const headerEl = document.getElementById('mainHeader') || document.querySelector('header');
        if (!isActive) {
          searchContainer.classList.add('active');
          if (headerEl) headerEl.classList.add('search-active');
          setTimeout(() => inputField.focus(), 100);
        } else {
          const val = inputField.value.trim();
          if (val.length > 0) {
            handleSearchAction();
          } else {
            searchContainer.classList.remove('active');
            if (headerEl) headerEl.classList.remove('search-active');
            suggestionsBox.style.display = 'none';
          }
        }
      } else {
        handleSearchAction();
      }
    });

    inputField.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        handleSearchAction();
      }
    });

    const urlParams = new URLSearchParams(window.location.search);
    const searchQuery = urlParams.get('search');
    if (searchQuery) {
      searchContainer.classList.add('active');
      const headerEl = document.getElementById('mainHeader') || document.querySelector('header');
      if (headerEl) headerEl.classList.add('search-active');
      inputField.value = searchQuery;
    }

    document.addEventListener('click', (e) => {
      if (!searchContainer.contains(e.target)) {
        if (isMobile() && inputField.value.trim() === '') {
          searchContainer.classList.remove('active');
          const headerEl = document.getElementById('mainHeader') || document.querySelector('header');
          if (headerEl) headerEl.classList.remove('search-active');
        }
        suggestionsBox.style.display = 'none';
      }
    });

    inputField.addEventListener('input', () => {
      const val = inputField.value.trim();
      if (val.length < 2) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';
        return;
      }

      const fetchProducts = typeof window.ensureProductsFetched === 'function'
        ? window.ensureProductsFetched()
        : Promise.resolve(window.allProducts || window.DEFAULT_CATALOG_FALLBACK || []);

      fetchProducts.then(products => {
        const scored = products.map(p => ({
          product: p,
          score: window.getFuzzyScore(p, val)
        })).filter(item => item.score > 0)
          .sort((a, b) => b.score - a.score);

        if (scored.length === 0) {
          suggestionsBox.innerHTML = '<div class="no-suggestions">No products found</div>';
          suggestionsBox.style.display = 'block';
          return;
        }

        let html = '';
        scored.slice(0, 5).forEach(item => {
          const p = item.product;
          const img = p.image || '/assets/images/ruut-logo-CF6400.png';
          const link = window.getProductUrl ? window.getProductUrl(p) : `/product.html?id=${p.id}`;
          html += `
            <a href="${link}" class="search-suggestion-item">
              <img src="${img}" alt="${p.title}" class="search-suggestion-thumb">
              <div class="search-suggestion-info">
                <div class="search-suggestion-title">${p.title}</div>
                <div class="search-suggestion-price">₹ ${parseFloat(p.price || 0).toFixed(2)}</div>
              </div>
            </a>
          `;
        });
        suggestionsBox.innerHTML = html;
        suggestionsBox.style.display = 'block';
      });
    });
  }
};

document.addEventListener('DOMContentLoaded', () => {
  if (typeof window.initHeaderSearch === 'function') {
    window.initHeaderSearch();
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
