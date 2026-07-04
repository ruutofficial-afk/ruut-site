# Design & API Specifications - RUUT E-Commerce System

This document provides detailed design specifications, function definitions, parameter lists, hooks, dynamic modals, and REST/AJAX API routes for both the frontend and backend systems of the RUUT e-commerce website. For exhaustive cart system documentation, see [`CART_DOCUMENTATION.md`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/CART_DOCUMENTATION.md).

---

## 1. Design Tokens & Styling (CSS Variables)

Defined in [`/assets/css/styles.css`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/assets/css/styles.css), these custom properties dictate the visual environment and theme tokens of the storefront.

| Token Name | Value | Purpose |
|---|---|---|
| `--font-serif` | `'Playfair Display', Georgia, serif` | Serif headers and luxury title typography |
| `--font-sans` | `'Inter', 'Karla', sans-serif` | Primary body text and UI text |
| `--font-heading` | `'Lexend Deca', 'Rubik', sans-serif` | Clean, geometric sub-headers and tags |
| `--ruut-bg` | `#E6DED1` | Warm beige background color |
| `--ruut-text` | `#664436` | Primary dark coffee brown color for body text |
| `--ruut-accent` | `#895158` | Deep rose mauve accent color for buttons and highlights |
| `--ruut-coffee` | `#4A3324` | Solid dark coffee color (buttons, header elements) |
| `--ruut-white` | `#FFFFFF` | Clear white for card backgrounds and drawer backings |
| `--ruut-border` | `#D1C7B7` | Neutral border outline separator |
| `--ruut-btn-bg` | `#EADBC8` | Light warm beige background for buttons |
| `--ruut-gray` | `#777777` | Secondary text label color |
| `--ruut-gold` | `#D4AF37` | Gold star rating and highlight accents |

---

## 2. Frontend Core Functions ([`assets/js/app.js`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/assets/js/app.js))

Core client-side orchestrator functions responsible for state management, server AJAX synchronization, layout rendering, modals, and dynamic transitions.

### Utility & Network Wrappers

#### `window.ruutFetch(url, options = {})`
- **Description**: Global fetch wrapper with automatic timeout control (10s), abort signal management, fallback to live WordPress host (`window.RUUT_WP_URL` or `https://yourruut.com`), and automatic relative path resolution during local static testing (`file://` or `localhost`).
- **Parameters**:
  - `url` (`string`): Target API endpoint or relative route.
  - `options` (`object`): Standard `fetch` options (method, headers, body, credentials).
- **Return Type**: `Promise<Response>`.

#### `window.ensureArray(res)`
- **Description**: Normalizes varied API response structures (`{ value: [...] }`, `{ data: [...] }`, or plain arrays) into a clean JavaScript array.
- **Parameters**:
  - `res` (`any`): API response payload.
- **Return Type**: `Array`.

#### `window.getProductUrl(product)`
- **Description**: Constructs category-aware product detail URLs (e.g. `/shop/airmist/the-atlas-collection/?product=12` or `/shop/diffuser/?product=15`).
- **Parameters**:
  - `product` (`object`): WooCommerce product object.
- **Return Type**: `string`.

#### `window.ensureProductsFetched()`
- **Description**: Pre-fetches and caches the full WooCommerce product catalog from `/wp-json/ruut/v1/products?limit=100`, avoiding redundant network fetches.
- **Parameters**: None.
- **Return Type**: `Promise<Array>`.

---

### Cart & Server Synchronizer

#### `syncCartWithServer(action, data = {})`
- **Description**: Communicates asynchronously with WooCommerce backend AJAX (`/wp-admin/admin-ajax.php`). Updates server-calculated totals (`serverSubtotal`, `serverTotal`, `serverDiscountTotal`, `serverVolumeDiscount`), updates active coupon state, handles server notice toasts, syncs progressive offer thresholds, and triggers re-renders of both the minicart drawer and standalone cart page.
- **Parameters**:
  - `action` (`string`): WooCommerce AJAX action hook (e.g., `ruut_add_to_cart`, `ruut_update_cart_qty`, `ruut_remove_cart_item`, `ruut_apply_coupon`, `ruut_remove_coupon`).
  - `data` (`object`): Payload parameters (e.g., `product_id`, `quantity`, `cart_item_key`, `coupon_code`).
- **Return Type**: `Promise<object>`.

#### `openCart()` / `closeCart()`
- **Description**: Controls visual sliding overlay transitions for the persistent minicart drawer (`#astra-mobile-cart-drawer`). Does not trigger on `cart.html`.
- **Parameters**: None.
- **Return Type**: `void`.

#### `addToCart(productNameOrId, price, ...args)`
- **Description**: Adds an item to the cart state, updates local storage, triggers `renderCart()`, opens minicart drawer, and dispatches server sync (`ruut_add_to_cart`). Provides visual button state updates ('ADDING...' -> 'ADDED!') when passed a button element.
- **Parameters**:
  - `productNameOrId` (`HTMLElement` | `number` | `string`): Button element, WooCommerce Product/Variation ID, or product name.
  - `price` (`number` | `string`): Optional override price or Product ID.
  - `...args`: Additional quantity or parameter overrides.
- **Return Type**: `void`.

#### `updateQty(index, delta)`
- **Description**: Modifies item quantity at specified cart array index. If quantity reaches `0`, removes item. Resets cached server totals to force immediate UI updates and dispatches server sync (`ruut_update_cart_qty` or `ruut_remove_cart_item`).
- **Parameters**:
  - `index` (`number`): Index position in `cart` array.
  - `delta` (`number`): Quantity change (+1 or -1).
- **Return Type**: `void`.

#### `removeCartItem(index)`
- **Description**: Completely removes item at specified index from cart array, updates UI, and dispatches server sync (`ruut_remove_cart_item`).
- **Parameters**:
  - `index` (`number`): Index position in `cart` array.
- **Return Type**: `void`.

#### `buyNow(productNameOrId, price, ...args)`
- **Description**: Adds product to cart session and redirects instantly to the checkout page (`/checkout/`).
- **Parameters**: Same as `addToCart`.
- **Return Type**: `void`.

#### `renderCart()`
- **Description**: Main cart drawer renderer. Evaluates progressive free gifts (`updateGiftSimulation()`), updates cart icon badge, populates minicart drawer HTML, computes coupon/volume discounts, updates threshold progress bar dots, and updates cross-sell recommendations (`cartUpsellSection`). Also invokes `window.renderCartPage()` if on `cart.html`.
- **Parameters**: None.
- **Return Type**: `void`.

#### `window.renderCartPage()`
- **Description**: Dedicated standalone cart page renderer for [`cart.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/cart.html). Renders cart item cards with volume & gift badges (`#cartPageItemsList`), billing subtotal (`#billingSubtotal`), detailed price breakdown (`#billingDiscountsContainer`: Sale Savings, Privilege Discount, Volume Discount), reward products container (`#billingRewardProductsContainer`), final total (`#billingTotal`), coupon code input state, and checkout action link.
- **Parameters**: None.
- **Return Type**: `void`.

#### `window.loadCartPageSuggestions()`
- **Description**: Populates the "Suggested for You" grid on [`cart.html`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/cart.html) (`#cartPageSuggestionsSection`). Fetches product catalog, filters out items already in the active cart array, and renders up to 3 recommendation product cards with real-time updates when cart items change.
- **Parameters**: None.
- **Return Type**: `void`.

---

### Progressive Free Gift & Discount Engine

#### `window.updateGiftSimulation()`
- **Description**: Evaluates current cart regular item quantity against offer thresholds (`offerThresholds`). Automatically injects or updates free gift items (compulsory gift & random gift pool) with `price: 0` and `is_gift: true` tag, resolving gift titles/images from catalog. Removes gifts if total regular quantity drops below requirement or if a coupon code is applied.
- **Parameters**: None.
- **Return Type**: `void`.

#### `window.getActiveVolumeDiscount()`
- **Description**: Calculates volume tier discount based on regular item quantities. Mutually exclusive with coupon codes (returns `0` if coupon code is active).
- **Parameters**: None.
- **Return Type**: `number`.

---

### Coupon & Privileges System

#### `fetchCoupons()`
- **Description**: Queries available active promotional privileges from WooCommerce backend, rendering them inside the Privileges Modal overlay list (`#ruutCouponsList`).
- **Parameters**: None.
- **Return Type**: `void`.

#### `tryApplyCoupon(code)`
- **Description**: Evaluates coupon application. If another coupon is currently active, displays the Loss Prevention confirmation modal (`#ruut-confirm-overlay`) warning about potential benefit loss before proceeding.
- **Parameters**:
  - `code` (`string`): Target promotional code (e.g. `TEST-12`, `TEST-34`).
- **Return Type**: `void`.

#### `executeApplyCoupon(code)`
- **Description**: Applies coupon code to WooCommerce cart session via `syncCartWithServer('ruut_apply_coupon', { coupon_code: code })`, updates UI, and triggers a confetti animation (`triggerConfetti()`) on success.
- **Parameters**:
  - `code` (`string`): Privilege code.
- **Return Type**: `void`.

#### `removeCoupon(code)`
- **Description**: Removes active coupon code via `syncCartWithServer('ruut_remove_coupon', { coupon_code: code })` and restores volume discounts/free gifts.
- **Parameters**:
  - `code` (`string`): Privilege code.
- **Return Type**: `void`.

#### `triggerConfetti()`
- **Description**: Renders a multi-colored canvas confetti explosion when a privilege coupon is successfully redeemed.
- **Parameters**: None.
- **Return Type**: `void`.

---

### Component Builders & Interactivity

#### `createProductCard(product, isFavoriteGrid, isBestsellerBadge)`
- **Description**: Dynamic product card HTML template builder. Calculates regular vs sale prices, discount badges (e.g. `29% OFF`), sub-category tags, star ratings, and glassmorphic Add to Cart overlay buttons.
- **Parameters**:
  - `product` (`object`): Product details object.
  - `isFavoriteGrid` (`boolean`): Flag for favorite card styling.
  - `isBestsellerBadge` (`boolean`): Renders BESTSELLER badge overlay.
- **Return Type**: `string` (HTML).

#### `initHeroSlider()`
- **Description**: Controls hero banner slideshow. 3s cycling interval, mouse hover pause/resume, previous/next arrow triggers, and dot pagination.
- **Parameters**: None.
- **Return Type**: `void`.

#### `initCategoryCarousel(categoriesData)`
- **Description**: Controls infinite collection slide carousel with smooth tracks, drag/touch bounds, and navigation arrows.
- **Parameters**:
  - `categoriesData` (`array`): Array of category taxonomy objects.
- **Return Type**: `void`.

#### Product Reels 3D Gallery Modal System (`initReelsModal`)
- **Description**: Manages Product Reels carousel track and interactive 3D Cover Flow Gallery Modal (`#reelsModal`). Calculates perspective 3D card transforms, active glowing borders (`#cf6400`), video play/pause sync, arrow navigation (`#reelsModalPrev`, `#reelsModalNext`), and associated WooCommerce product recommendation cards.
- **Parameters**: None.
- **Return Type**: `void`.

#### Customer Review Submission Handler (`openReviewModal` / `submitReview`)
- **Description**: Manages customer review submission popup (`#reviewModalOverlay`). Features star rating selector, input validation, and dispatches POST request to `/wp-json/ruut/v1/reviews`.
- **Parameters**: None.
- **Return Type**: `void`.

#### `showToast(message, type = 'success')`
- **Description**: Displays centermost floating notification toasts for user actions (added to cart, coupon warnings, errors).
- **Parameters**:
  - `message` (`string`): Toast message.
  - `type` (`string`): `'success'` or `'error'`.
- **Return Type**: `void`.

---

## 3. Frontend Checkout Script ([`assets/checkout.js`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/assets/checkout.js))

Headless address vault selector and OTP authentication scripting for WooCommerce checkout.

#### `extractAddressData(addr, key)` / `fuzzyExtract(obj, keywords)`
- **Description**: Performs deep recursive scanning of address metadata objects to resolve city, state, zip, phone numbers, and street address lines.
- **Parameters**:
  - `addr` (`object`): Native WooCommerce address record.
  - `key` (`string`): Field key prefix.
- **Return Type**: `string`.

#### `ruut_build_layout()`
- **Description**: Re-orchestrates standard WooCommerce checkout DOM into a unified single-page layout with address vault selectors, login panel, shipping rate options, and live cart summary.
- **Parameters**: None.
- **Return Type**: `void`.

#### `renderOtpBoxes()`
- **Description**: Renders 4-digit OTP box inputs with automatic focus tabbing, arrow key navigation, and backspace handling.
- **Parameters**: None.
- **Return Type**: `void`.

---

## 4. Headless REST API Endpoints (`/wp-json/ruut/v1/`)

Custom REST API routes exposed by the `ruut-core` plugin.

### A. Announcement Settings: `GET /announcement`
- **Callback**: `ruut_get_announcement()`
- **Permission**: `__return_true`
- **Response**:
  ```json
  {
    "messages": [
      "✨ USE CODE TEST-12 TO UNLOCK ₹200 OFF ON YOUR RITUAL ✨",
      "🌿 FREE SHIPPING ON ALL ORDERS ABOVE ₹1500 🌿"
    ],
    "reward_threshold": 3,
    "reward_amount": "₹250"
  }
  ```

### B. Send OTP Code: `POST /send-otp`
- **Callback**: `ruut_api_send_otp($request)`
- **Parameters**: `username` (`string`, Email or Mobile).
- **Response**: `{"success": true, "message": "OTP sent successfully."}`

### C. Verify OTP Code: `POST /verify-otp`
- **Callback**: `ruut_api_verify_otp($request)`
- **Parameters**: `username` (`string`), `otp` (`string`).
- **Response**: `{"success": true, "cookie": "wordpress_logged_in_...", "user_id": 15}`

### D. Get User Profile: `GET /user`
- **Callback**: `ruut_api_get_user()`
- **Header**: Requires cookie session auth.
- **Response**: Returns profile ID, name, email, phone, billing details, and saved address vault array (`ruut_address_book`).

### E. Update User Details: `POST /user`
- **Callback**: `ruut_api_update_user($request)`
- **Parameters**: `first_name`, `last_name`, `phone`, `email`.
- **Response**: `{"success": true}`

### F. Save Saved Address Record: `POST /save-address`
- **Callback**: `ruut_api_save_address($request)`
- **Parameters**: `id` (to edit) or empty (to add), `first_name`, `last_name`, `address_1`, `address_2`, `city`, `postcode`, `phone`.
- **Response**: `{"success": true, "address_book": [...]}`

### G. Delete Saved Address Record: `POST /delete-address`
- **Callback**: `ruut_api_delete_address($request)`
- **Parameters**: `id` (`string`, address hash ID).
- **Response**: `{"success": true}`

### H. Products Catalog Query: `GET /products`
- **Callback**: `ruut_get_products_api($request)`
- **Parameters**: `id` (`int`), `category` (`string`), `featured` (`string`), `limit` (`int`).
- **Response Structure**: Array of formatted product objects containing title, price, regular_price, sale_price, discount_percentage, volume, gallery images, categories, and custom metaboxes (`feels_like`, `story`, `directions`, `cautions`).

### I. Collections Catalog: `GET /collections`
- **Callback**: `ruut_get_collections_api($request)`
- **Parameters**: `slug` (`string`).
- **Response**: Array of collection taxonomy objects containing titles, thumbnails, descriptions, and parent IDs.

### J. Customer Reviews Submission: `POST /reviews` & `GET /reviews`
- **Callback**: `ruut_handle_review_submission($request)` / `ruut_get_reviews_api($request)`
- **Parameters (POST)**: `author_name`, `author_email`, `rating`, `content`, `product_id`.
- **Response**: `{"success": true, "message": "Thank you! Your review has been submitted for approval."}`

### K. Global FAQs List: `GET /faqs`
- **Callback**: `ruut_get_faqs_api($request)`
- **Response**: JSON array of FAQs sorted by display order.

### L. The Ruut Story: `GET /story`
- **Callback**: `ruut_get_story_api($request)`
- **Response**: `{"title": "The Ruut Story", "content": "<p>...</p>", "image": "http://..."}`

### M. Product Reels Carousel: `GET /reels`
- **Callback**: `ruut_get_reels_api($request)`
- **Response**: Array of reel items containing `id`, `title`, `video_url`, `thumbnail`, `order`, and associated WooCommerce `product` object formatted data.

---

## 5. WooCommerce AJAX Actions (`/wp-admin/admin-ajax.php`)

Backend AJAX endpoints in [`ruut-core.php`](file:///c:/Users/RAHUL/OneDrive/Desktop/ruut/wp-content/plugins/ruut-core/ruut-core.php) for WooCommerce session synchronization:

| Action Name | Callback Function | Purpose |
|---|---|---|
| `wp_ajax_ruut_get_cart` | `ruut_ajax_get_cart()` | Returns active WooCommerce cart items, pricing, subtotals, applied coupons, and offer thresholds. |
| `wp_ajax_ruut_add_to_cart` | `ruut_ajax_add_to_cart()` | Safely adds product/variation ID & quantity to WooCommerce session cart. |
| `wp_ajax_ruut_update_cart_qty` | `ruut_ajax_update_cart_qty()` | Updates quantity for item hash key in active session. |
| `wp_ajax_ruut_remove_cart_item` | `ruut_ajax_remove_cart_item()` | Deletes item hash key from WooCommerce cart session. |
| `wp_ajax_ruut_get_active_coupons` | `ruut_ajax_get_active_coupons()` | Fetches valid promotional coupon codes fitting store conditions. |
| `wp_ajax_ruut_apply_coupon` | `ruut_ajax_apply_coupon()` | Registers promotional coupon string into WooCommerce session validation. |
| `wp_ajax_ruut_remove_coupon` | `ruut_ajax_remove_coupon()` | Removes applied discount coupon code from session cart. |
| `wp_ajax_ruut_save_address` | `ruut_ajax_save_address()` | Saves delivery address to WooCommerce user meta vault. |
| `wp_ajax_ruut_delete_address` | `ruut_ajax_delete_address()` | Deletes delivery address entry from user meta vault. |

---

## 6. Visual Component Layouts & Styling

### A. Product Cards (`.product-card`)
1. **Image Container (`.product-img-wrap`)**: Contains product image, sale badge (`.sale-badge`), and frosted glass Add to Cart overlay (`.add-to-cart-overlay` & `.btn-add-cart`) visible on hover.
2. **Product Info (`.product-info`)**: Meta row with sub-category tag (`.product-category-tag`) and star rating (`.product-rating`), product title (`.product-title`), and price/action row (`.product-price-row` with `.product-price` and `.btn-buy-now`).

### B. Product Reels Section & 3D Cover Flow Gallery Modal (`#reelsSection`)
1. **Track Carousel (`.reels-track`)**: Continuous horizontal scrolling short video cards with hover play overlays (`.reel-play-overlay`, `.reel-play-btn-circle`).
2. **3D Cover Flow Gallery Modal (`.reels-modal`)**: Full-screen blurred backdrop modal overlay (`.reels-modal.open`), centered 3D perspective track (`.reels-modal-track`), cards transformed along 3D axes (`scale`, `rotateY`, `translateZ`), active card glowing border (`#cf6400`), arrow navigation (`.reels-modal-nav-btn`), and associated WooCommerce product detail card row.

### C. Ingredients Banner (`.ingredients-banner`)
Displays 3 natural formula badges in a single row on all viewports (`repeat(3, 1fr)`). PNG icons are tinted to the brand's olive shade (`#7d8427`) via CSS filters.

### D. Cart Page Order Summary & Suggested Products (`cart.html`)
1. **Cart Page Grid (`.cart-layout-container`)**: Split 70/30 layout with progressive progress bar, item list (`#cartPageItemsList`), and "Suggested for You" recommendations grid (`#cartPageSuggestionsSection`).
2. **Sticky Order Summary (`.cart-summary-card`)**: Lists subtotal (`#billingSubtotal`), unlocked reward items (`#billingRewardProductsContainer`), promo input state (`#cartpage_coupon_code`), detailed discount breakdown container (`#billingDiscountsContainer`), free delivery badge, total (`#billingTotal`), and Proceed to Checkout action button.
