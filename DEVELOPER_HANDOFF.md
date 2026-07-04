# RUUT E-Commerce Project Developer & AI Handoff Handbook

Welcome! This document outlines the current working state of the RUUT e-commerce storefront, visual design layouts, routing configurations, and data integration between the decoupled static frontend and the WordPress/WooCommerce backend. It is designed to allow any future developer or AI coding agent to instantly understand the system and resume work without breaking existing patterns.

---

## 1. System & Deployment Architecture

RUUT utilizes a **decoupled headless e-commerce architecture**:
*   **Static Frontend (HTML5 / Vanilla CSS3 / Vanilla JS)**: Hosted directly on the web server (Hostinger) and served statically with root-relative paths.
*   **WordPress/WooCommerce Backend**: Serves as the CMS and checkout gateway. Exposes custom REST API endpoints under `/wp-json/ruut/v1/` and standard WooCommerce AJAX actions under `/wp-admin/admin-ajax.php`.
*   **Asset Cache Management**: To bypass browser/CDN caches, all assets are referenced in HTML templates with version query parameters (currently `styles.css?v=16.0` and `app.js?v=110.0`). **Whenever you modify JS or CSS files, you must bump these version numbers across all HTML template files.**

---

## 2. File Directory & Component Registry

### A. Frontend Repository Structure
*   [`/index.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/index.html): Landing page template. Features hero slideshow, ingredients banner, Product Reels carousel track & 3D Cover Flow Gallery Modal, Bestsellers grid, diffuser callout, custom Ruut Story section, customer reviews carousel & review submission modal, FAQs, and contact banner.
*   [`/shop.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/shop.html): Main catalog page. Contains product grids for Bestsellers, Air Mists, and Diffusers, alongside global FAQs.
*   [`/product.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/product.html): Single product detail template. Dynamically parses URL parameters (e.g. `?product=ID`), populates images, descriptions, volume selectors, pricing, ingredients, directions, breadcrumbs, and recommended products.
*   [`/collection.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/collection.html): Collection taxonomy directory template.
*   [`/the-atlas-collection.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/the-atlas-collection.html): Dedicated collection landing page for the "Atlas" series.
*   [`/cart.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/cart.html): Custom standalone shopping cart page. Features item list with gift badges (`#cartPageItemsList`), progressive reward progress bar, sticky Order Summary with detailed discount breakdown (`#billingDiscountsContainer`), coupon input with applied states, and "Suggested for You" catalog recommendations (`#cartPageSuggestionsSection`).
*   [`/CART_DOCUMENTATION.md`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/CART_DOCUMENTATION.md): Dedicated comprehensive documentation covering the minicart drawer, cart page, progressive free gifts, coupon interaction rules, and server sync.
*   [`/about-us.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/about-us.html): Brand story, founders' favorites grid, and botanical philosophies.
*   [`/coming-soon.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/coming-soon.html): Pre-launch countdown page.
*   [`/assets/css/styles.css`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/assets/css/styles.css): Global stylesheet containing CSS tokens (`--ruut-bg`, `--ruut-accent`, `--ruut-text`), typography rules, responsive grids, hover overlays, glassmorphic buttons, modal styling, and 3D cover flow transforms (`v=16.0`).
*   [`/assets/js/app.js`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/assets/js/app.js): Core client-side orchestrator (`v=110.0`). Handles server cart synchronization (`syncCartWithServer`), localStorage fallbacks, cart drawers, standalone cart page rendering (`renderCartPage`), progressive free gift evaluation (`updateGiftSimulation`), volume discount calculations, privilege/coupon loss prevention modals, 3D Reels modal, and review submissions.

### B. WordPress Custom Plugins
*   [`/wp-content/plugins/ruut-core/ruut-core.php`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/wp-content/plugins/ruut-core/ruut-core.php): Core backend plugin. Handles WooCommerce cart AJAX hooks (`ruut_add_to_cart`, `ruut_update_cart_qty`, `ruut_remove_cart_item`, `ruut_apply_coupon`, `ruut_remove_coupon`), registers REST API routes under `/wp-json/ruut/v1/`, custom metaboxes, CPTs (`ruut_faq`, `ruut_reel`), customer reviews, OTP auth transients, and user meta address book vault.
*   `wordpress-theme/`: Directory for staging custom WordPress theme templates.

---

## 3. Core Features & Configuration Specifications

### A. Routing & Path Resolution (`.htaccess`)
1. **Clean URLs**: Redirection rules automatically strip `.html` from public paths (e.g., `/shop`, `/about-us`, `/cart`).
2. **Product Deep Linking**: Product pages are accessible at URLs like `/shop/airmist/the-atlas-collection/?product=12`. A rewrite rule in `.htaccess` captures any path matching `/shop/` containing a `product` query parameter and internally forwards it to `product.html`:
    ```apache
    RewriteCond %{QUERY_STRING} (?:^|&)(?:product|id)=([^&]+) [NC]
    RewriteRule ^shop/.*$ /product.html [QSA,L]
    ```
3. **Root-Relative Paths**: To ensure images, styles, and scripts load correctly under deep paths (e.g., `/shop/airmist/`), **all** paths in HTML template files are root-relative (starting with `/`).

### B. Product Card Layout & Design
1. **Image Overlay (Add to Cart)**: The **Add to Cart** button is a glassmorphic blurred badge centered over the product image wrap on hover (`.add-to-cart-overlay` & `.btn-add-cart`).
2. **Card Details (Buy Now)**: The bottom section displays a flex row with price details on the left (regular price crossed out + sale price) and a styled **Buy Now** button (`.btn-buy-now`) on the right.
3. **Dynamic Sale Percentage**: Automatically computes sale badges (e.g. `29% OFF`) when `sale_price` is lower than `regular_price`.

### C. Product Reels Section & 3D Cover Flow Gallery Modal
1. **Scrolling Track Carousel**: Located on `index.html` (`#reelsSection`), displaying short vertical video cards with hover play overlays (`.reel-play-overlay`, `.reel-play-btn-circle`).
2. **3D Cover Flow Gallery Modal (`#reelsModal`)**: Clicking any reel card opens a full-screen blurred backdrop modal with a 3D perspective slider track (`#reelsModalTrack`). Cards calculate 3D transforms (`scale`, `rotateY`, `translateZ`), highlight active videos with golden-orange glowing borders (`#cf6400`), auto-play active videos while pausing others, and feature navigation arrow controls.
3. **Associated WooCommerce Product Card**: Under each reel, if an associated WooCommerce Product ID is configured (`_reel_product_id`), a horizontal product card (thumbnail, title, price, discount badge) is displayed, linking directly to the product detail page.

### D. Ingredients Banner Badges
1. **Formula Badges**: Displays 3 natural formula badges in a single row on all viewports (`100% Clean Formulas`, `Cruelty Free`, `Sustainable Vessels`).
2. **Color Tint Filter**: CSS color filter tints transparent PNGs to the brand's target olive tone (`#7d8427`):
    ```css
    filter: invert(45%) sepia(5%) saturate(6082%) hue-rotate(25deg) brightness(106%) contrast(79%);
    ```

### E. Suggested Products on the Cart Page ([`cart.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/cart.html))
1. **HTML Container (`#cartPageSuggestionsSection`)**: Located directly below the main cart item list container on `cart.html`. Contains a header ("Suggested for You") and a 3-column product grid (`#cartPageSuggestionsGrid`).
2. **Active Cart Filtering**: Loader function `window.loadCartPageSuggestions()` in [`app.js`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/assets/js/app.js) pre-fetches the catalog via `ensureProductsFetched()`, compares items with the active `cart` array, and filters out items the user has already added to cart.
3. **Real-Time Synchronization**: Tying into the centralized `renderCart()` loop ensures that adding a suggested product, changing item quantities, or removing products instantly triggers `window.loadCartPageSuggestions()`, updating recommendations without reloads.

---

## 4. Current State of the Cart & Checkout System

The cart system operates on a **fully synchronized hybrid server-local model**:

### How it is configured and functioning:
1. **WooCommerce Server Cart Sync (ACTIVE)**:
    * All cart operations in `app.js` (`addToCart`, `updateQty`, `removeCartItem`, `tryApplyCoupon`, `executeApplyCoupon`, `removeCoupon`) execute asynchronous POST requests via `syncCartWithServer(action, payload)` to `/wp-admin/admin-ajax.php`.
    * Synchronizes WooCommerce session cart state using backend actions: `ruut_add_to_cart`, `ruut_update_cart_qty`, `ruut_remove_cart_item`, `ruut_apply_coupon`, `ruut_remove_coupon`, `ruut_get_cart`.
    * Server responses report exact item lists, subtotals, catalog sale savings, applied coupon discounts, volume discounts, server notice messages, and progressive offer thresholds.
2. **Unified Cart Page Renderer (`window.renderCartPage`) (ACTIVE)**:
    * Standardized rendering function in `app.js` updates both the minicart drawer (`#astra-mobile-cart-drawer`) and the standalone [`cart.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/cart.html) page simultaneously.
    * Dynamically populates item cards (`#cartPageItemsList`), gift reward badges, billing subtotal (`#billingSubtotal`), detailed discount breakdown container (`#billingDiscountsContainer`), reward items container (`#billingRewardProductsContainer`), final total (`#billingTotal`), coupon code input states, and checkout links (`/checkout/`).
3. **Progressive Free Gift & Volume Discount Engine (ACTIVE)**:
    * `updateGiftSimulation()` checks regular item counts against offer thresholds (`offerThresholds`). Automatically injects compulsory gifts and random pool gifts with `price: 0` and `is_gift: true` tag.
    * `getActiveVolumeDiscount()` computes volume tier discounts when no coupon is active. Applying a coupon code seamlessly restores regular item states and disables volume discounts to prevent benefit stacking.
4. **Local Fallbacks & Testing**:
    * Stores current cart array under `ruut_cart` and coupon under `ruut_coupon` in `localStorage`.
    * When running static local previews via `file://` or offline, `ruutFetch` catches network failures gracefully and falls back to local calculations, ensuring zero frontend breakage.

---

## 5. Completed Implementation Milestones & Next Steps

### Completed Milestones:
- [x] **Task 1: Complete Cart AJAX Synchronizer in `app.js`**: `syncCartWithServer` is fully implemented, wiring `addToCart`, `updateQty`, and `removeCartItem` to `/wp-admin/admin-ajax.php`.
- [x] **Task 2: Refactor Coupon Actions for Server Sync**: `tryApplyCoupon`, `executeApplyCoupon`, and `removeCoupon` are connected to backend WooCommerce coupon validation with Loss Prevention confirmation modals (`#ruut-confirm-overlay`) and confetti celebrations.
- [x] **Task 3: Unify Cart Rendering in `cart.html`**: `window.renderCartPage()` is centralized in `app.js`, driving both minicart drawer and standalone `cart.html` with real-time suggested products (`loadCartPageSuggestions`).
- [x] **Task 4: Product Reels 3D Cover Flow Gallery Modal**: Full 3D perspective gallery modal (`#reelsModal`) with active card glowing borders (`#cf6400`), play/pause state management, and WooCommerce product links.

---

### Recommended Next Steps for Future Developers / AI Agents:

#### Task 1: Web Server Cache & Header Optimization
Configure Hostinger `.htaccess` directives for optimal static caching of assets (`/assets/css/` and `/assets/js/`) while disabling cache for REST API responses (`/wp-json/ruut/v1/`) and AJAX endpoints (`/wp-admin/admin-ajax.php`).

#### Task 2: End-to-End Cart & Checkout Testing
Perform validation of cart state persistence across multi-tab browser sessions, testing guest users vs. logged-in user OTP flows, address vault selections, and payment gateway redirects.

#### Task 3: Video Asset Compression for Product Reels CPT
Ensure all user-uploaded MP4 videos for the Product Reels CPT are compressed (H.264/AAC, target < 3MB) to maintain fast mobile load times when opening the 3D Cover Flow Gallery Modal.

#### Task 4: Asset Versioning Protocol
When modifying `assets/css/styles.css` or `assets/js/app.js`, update the version query parameters (`?v=16.1` and `?v=111.0`, etc.) in all 7 HTML templates (`index.html`, `shop.html`, `product.html`, `collection.html`, `the-atlas-collection.html`, `cart.html`, `about-us.html`).
