# RUUT Cart System — Overview

## Project Identity
- **Name**: RUUT (yourruut.com)
- **Type**: Premium natural indoor environment air mists e-commerce store
- **Architecture**: Decoupled headless — static HTML/CSS/JS frontend + WordPress/WooCommerce backend
- **Hosting**: Hostinger (static files served directly, WordPress backend on same domain)

## Technology Stack
- **Frontend**: Vanilla HTML5, CSS3, JavaScript (no frameworks)
- **Backend**: WordPress 6.x + WooCommerce
- **Plugin**: `ruut-core` (custom plugin handling all API/cart/auth logic)
- **Database**: MySQL (managed by WordPress)
- **Styling**: Custom CSS with design tokens (CSS custom properties)
- **State Management**: localStorage (client) + WooCommerce sessions (server)

## Cart System Summary
The cart operates on a **hybrid local-server synchronization model**:
1. All cart mutations happen immediately in `localStorage` for instant UI responsiveness
2. Each mutation fires a background AJAX request to sync with WooCommerce server sessions
3. The WooCommerce session is the source of truth for checkout
4. Local `file://` protocol is detected and falls back to localStorage-only mode

## Key Files (Cart Domain)
| File | Role |
|------|------|
| `assets/js/app.js` | Core client-side cart orchestrator (1526 lines) |
| `cart.html` | Standalone cart page (282 lines) |
| `assets/css/styles.css` | Global styles including cart drawer & cart page (99KB) |
| `wp-content/plugins/ruut-core/ruut-core.php` | Backend AJAX handlers (134KB) |

## Pages Using Cart Components
The cart drawer (mini-cart) is embedded in ALL 6 page templates:
1. `index.html` — Landing page
2. `shop.html` — Catalog page
3. `product.html` — Single product detail
4. `the-atlas-collection.html` — Collection page
5. `about-us.html` — About page
6. `cart.html` — Standalone cart page (also has its own full cart view)

## Current State
- Cart AJAX sync is implemented and functional
- Coupon system supports `TEST-12` (₹200) and `TEST-34` (5%) codes (default coupon removed)
- Dynamic progress bar tracks multiple tier-based thresholds with stacked benefits (compulsory gift, stock-based random tester gift pool, and flat discount)
- Asset versioning: `styles.css?v=6.9`, `app.js?v=8.0`
