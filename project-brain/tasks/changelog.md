# Changelog

## 2026-06-27 — Project Brain v2 Initialized

### System Layer
- Created `system/system.md` — Global engineering workflow
- Created `system/planner.md` — Task decomposition and risk assessment
- Created `system/workflow.md` — Deterministic execution lifecycle
- Created `system/orchestrator.md` — 10-stage pipeline definition

### Memory Layer
- Created `memory/overview.md` — Project summary and tech stack
- Created `memory/architecture.md` — Cart data flow and state ownership
- Created `memory/backend.md` — WooCommerce AJAX handlers and REST endpoints
- Created `memory/frontend.md` — JavaScript functions, DOM elements, data structures
- Created `memory/routing.md` — URL patterns and .htaccess rules
- Created `memory/api.md` — Complete API reference with request/response schemas
- Created `memory/dependencies.md` — Runtime, internal, and asset dependencies
- Created `memory/patterns.md` — 10 core engineering patterns

### Graph Layer
- Created `graph/graph.json` — Root graph with entry points and critical paths
- Created `graph/graph-index.json` — Lookup index by name, file, DOM ID, API
- Created `graph/nodes.json` — 42 nodes (functions, DOM, APIs, storage)
- Created `graph/edges.json` — 58 edges (calls, renders, syncs, reads, writes)

### Runtime Layer
- Created 7 runtime components (context-loader, execution-engine, graph-retriever, memory-updater, graph-updater, review-engine, task-classifier)

### Standards Layer
- Created 6 standard definitions (javascript, css, php-woocommerce, security, naming, documentation)

### Review Layer
- Created 5 review checklists (architecture, performance, security, code-quality, documentation)

### Task History
- Created `tasks/active.md`, `tasks/completed.md`, `tasks/failed.md`, `tasks/changelog.md`

### Cache Layer
- Created `cache/recent-context.md`, `cache/last-plan.md`, `cache/recent-files.md`, `cache/recent-review.md`

## 2026-06-27 — Dynamic Gift Offer & Progress Bar Implementation
- Removed default coupon code flat discount in frontend calculations (`app.js` and `cart.html`).
- Added WooCommerce Settings options: `ruut_progress_bar_enabled` (toggle), `ruut_offer_qty_threshold` (threshold), `ruut_offer_gift_mode` (compulsory vs random), and `ruut_offer_free_product_id` (compulsory product selection).
- Implemented `woocommerce_before_calculate_totals` hook in `ruut-core.php` to automatically add, enforce price override (₹0), and remove free gift products based on threshold.
- Updated `ruut_get_cart_response()` to pass settings and `is_gift` parameters.
- Updated `app.js` progress bar calculations to count total quantity of non-gift products and compare with settings threshold.
- Updated `renderCart()` and `renderCartPage()` to format free gift items without quantity selectors and delete buttons, showing "Free Gift" badge.
- Added progress bar HTML container to `cart.html` and updated version in all HTML templates to `app.js?v=7.6`.
- Updated patterns and overview memory documentation in Project Brain.

## 2026-06-27 — Dynamic Multiple Thresholds & Stacked Rewards
- Replaced old individual offer settings with a single JSON option `ruut_offer_thresholds` supporting multiple tiers.
- Integrated a repeating options table in WooCommerce settings admin page with jQuery adding/removing tier rows.
- Updated `woocommerce_before_calculate_totals` cart hook to identify active tier based on total quantity and apply target compulsory and/or random gifts.
- Added session-based random product caching mapped to unique threshold keys to keep selected tester product stable.
- Implemented `woocommerce_cart_calculate_fees` hook to apply active tier's flat discount as a negative fee line item.
- Exposed configured thresholds with product name lookups in REST cart API.
- Implemented smooth progress percent interpolation and dynamic progress dots generation via JavaScript in `app.js`.
- Cleaned up static dot elements in `cart.html` and bumped asset version in all 6 templates to `app.js?v=7.7`.

## 2026-06-27 — Dynamic Stock-Based Random Gift Pool
- Replaced binary `random_gift` option with multiple-select product selector dropdown (`row-random-pool`) in admin settings page.
- Implemented `ruut_get_highest_stock_product_id($product_ids)` helper to dynamically evaluate and sort stock level descending to return the product with the highest inventory.
- Updated `woocommerce_before_calculate_totals` cart hook to dynamically add/remove products from the selected random pool based on stock levels.
- Updated REST API cart data to resolve the specific product name and ID with the highest stock level and return it in `random_gift_name` and `random_gift_id` respectively.
- Updated frontend progress bar text in `app.js` to display the specific resolved product name (e.g. `a free [Product Name]`) in the unlock instructions.
- Bumped script version in all 6 templates to `app.js?v=7.8`.

## 2026-06-27 — Random Gift Checklist Selector UI
- Replaced the multiple-select dropdown in RUUT Settings panel table rows with a scrollable list of checkboxes (`.pool-checkbox`).
- Updated settings form serialization jQuery to iterate over checked checkboxes and save selected IDs array as `random_gift_pool`.
- Updated new tier row HTML templates to dynamically generate product option checklists.

## 2026-06-27 — Obsolete Settings Page Fields Cleanup
- Removed outdated settings fields from WordPress admin dashboard (`ruut_reward_threshold`, `ruut_reward_amount`, and `ruut_cart_goal_amount`).
- Removed registration of these obsolete settings in `ruut_register_settings()`.

## 2026-06-27 — Bestsellers/Upsell "Complete the Ritual" Add to Cart Syncing Fix
- Fixed an issue where products added from the empty cart bestsellers and "Complete the Ritual" suggestions disappeared from the cart drawer during subsequent synchronization sweeps.
- Replaced title-string arguments with unique numeric product ID references in button click handlers (`addToCart(${product.id})` and `buyNow(${product.id})`).
- Bumped app.js version query strings across all 6 static page templates to `app.js?v=7.9`.

## 2026-06-27 — Dynamic WooCommerce Active Coupons & Validation Feedback
- Implemented `injectModalsIfMissing()` to dynamically inject `#ruut-coupons-modal-overlay` and `#ruut-confirm-overlay` into the DOM.
- Replaced hardcoded popup coupon lists with live WooCommerce coupon fetching from `/wp-admin/admin-ajax.php?action=ruut_get_active_coupons` API endpoint.
- Integrated discount value checks comparing new vs old active coupon discounts, triggering the lower-benefit confirmation popup if downgrade detected.
- Captured backend eligibility error messages (e.g. minimum spend restrictions) and rendered the exact WooCommerce validation text dynamically inside the modal under specific coupon items.
- Added Promo Code input container, apply action, and explorer link to the sidebar Order Summary on `cart.html`.
- Bumped app.js version query strings across all 6 static page templates to `app.js?v=8.0`.

## 2026-06-29 — Cart Page Restructure & Mini-Cart Upsell Fix
- Fixed the "Complete the Ritual" mini-cart upsell adding issue in `assets/js/app.js` by changing `addUpsellToCart()` to use the numeric product ID `2490` instead of a string title argument, which prevents WooCommerce sweeps from discarding it.
- Hid the static `#cartUpsellSection` in `the-atlas-collection.html` by default on load using inline CSS to make it consistent with other pages.
- Restructured the Cart Page's Order Summary section in `cart.html` and `assets/js/app.js` to match the exact requested sequence (Subtotal, Reward Product, Coupon input, Explore link, Delivery Free, Total, and Checkout button).
- Updated coupon interaction behavior on the cart page: when a coupon is applied, the code is shown in the input box, a clear/cross button is displayed to remove it, the input is disabled, and the Apply button is updated to "APPLIED" with disabled styles.
- Integrated threshold discounts in the cart page's final total calculation.

## 2026-06-29 — Mini-Cart Unification, Free Gift Conflict Fix & Discount Totals
- Unified the mini-cart drawer HTML in `the-atlas-collection.html` and `about-us.html` to match the `index.html` reference version: added `<div class="cart-scrollable-area">` wrapper, replaced static hardcoded upsell card with dynamic `#cartUpsellImg`/`#cartUpsellName`/`#cartUpsellBtn` elements, changed summary label from "Subtotal" to "Total", and removed hardcoded initial price values.
- Replaced static hardcoded coupons modal in `the-atlas-collection.html` and `about-us.html` with the dynamic `id="ruutCouponsList"` version that fetches active coupons from the WooCommerce API on demand.
- Fixed the "Complete the Ritual" / free gift conflict in `addToCart()`: the duplicate detection (`cart.findIndex`) now excludes `is_gift: true` items, allowing a paid product and its free gift equivalent to coexist as separate line items in the cart.
- Enhanced the "Free Gift" tag visual treatment on the cart page (`renderCartPage()`) with a prominent accent-colored badge with gift emoji instead of plain text.

## 2026-06-29 — Cart Page Highlighted Rewards & Detailed Price Breakdown
- Updated `renderCartPage()` in `assets/js/app.js` to highlight free reward items in the cart list: applied a custom sand background color, a solid accent border, and a "Free Reward" top corner banner badge.
- Added `#billingDiscountsContainer` to `cart.html` and implemented a fully detailed price breakdown in the Order Summary sidebar, showing the base price (original prices + gift original value), product sale discounts, free gift discounts, active coupon discounts, and volume discounts to make all savings highly transparent.

## 2026-06-30 — "Coming Soon" Launch Landing Page
- Created a premium glassmorphism "Coming Soon" landing page `coming-soon.html` to act as a placeholder for unfinished site areas.
- Designed `assets/css/coming-soon.css` following Option 1 (Botanical Narrative) with responsive tokens, frosted glass panels, and slow-moving radial light overlays.
- Implemented `assets/js/coming-soon.js` featuring an automatic 15-day countdown timer loop and an interactive newsletter signup action that validates inputs and saves subscription state to `localStorage`, revealing an exclusive 15% discount code (`LAUNCH-15`) on success.

## 2026-06-30 — Landing Page Launch Countdown & Cart Interception
- Added a responsive, forest-green `#launch-countdown-banner` to `index.html` right above the Ingredients section.
- Configured `assets/js/app.js` to initialize the live homepage countdown timer elements (`homeDays`, `homeHours`, `homeMinutes`, `homeSeconds`) using the relative 15-day launch date cache.
- Blocked all ordering features by intercepting `openCart()`, `addToCart()`, `buyNow()`, and all document-level clicks towards cart/checkout pages (`useCapture: true`), presenting a dynamic "🚀 RUUT products are launching soon! Ordering is currently unavailable" toast warning.
- Updated `handleCollections()` category rendering: marked all secondary collections as "Coming Soon" (grayscale overlay, disabled click/pointer states, and explicit overlay badges) while leaving the primary first collection ("The Atlas Collection") active.

## 2026-06-30 — main page swap to coming-soon & contact details updates
- Swapped DirectoryIndex in `.htaccess` to prioritize `coming-soon.html` as the default root page, while adjusting redirect conditions to allow explicit access to `index.html` via `/index`.
- Updated WhatsApp links to point to the new WhatsApp number `98985 71044` (using WA.me API `https://wa.me/919898571044`) across all HTML files.
- Replaced all Instagram social icons and schema metadata links with the user's specific Instagram URL.
- Updated telephone metadata and footer listings to use `+91 98985 71044` across all views.
- Resolved nesting and closing tag malformations in the HTML footers of `the-atlas-collection.html` and `about-us.html`.
- Replaced the placeholder text in `coming-soon.html` with a concise, styled brand explanation detailing what Ruut represents (the intersection of base/Root and seasonal/Rutu notes).
