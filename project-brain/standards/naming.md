# Standards — Naming Conventions

## JavaScript

| Type | Convention | Examples |
|------|-----------|----------|
| Functions | camelCase | `renderCart`, `addToCart`, `syncCartWithServer` |
| Variables | camelCase | `cartCountEl`, `discountedSubtotal`, `activeCoupon` |
| DOM elements | camelCase (prefixed with element type hint) | `cartDrawer`, `cartItemsList`, `ruutMinicartFill` |
| Global functions | `window.functionName` | `window.addToCart`, `window.updateQty` |
| Constants | camelCase | `scrollThreshold = 50`, `slideInterval = 4000` |

## HTML IDs

| Domain | Prefix | Examples |
|--------|--------|----------|
| Cart drawer elements | `cart*` or `ruut*` | `#cartCount`, `#cartItemsList`, `#ruutMinicartFill` |
| Cart page elements | `cartPage*` or `billing*` | `#cartPageItemsList`, `#billingSubtotal` |
| Coupon elements | `coupon*` or `ruut_coupon*` | `#ruut_coupon_code`, `#couponMessages` |
| Modals/overlays | `ruut-*-overlay` | `#ruut-confirm-overlay`, `#ruut-coupons-modal-overlay` |
| Toast notification | `ruut-toast-*` | `#ruut-toast-notif` |

## CSS Classes

| Type | Convention | Examples |
|------|-----------|----------|
| Layout components | `.cart-*` | `.cart-drawer`, `.cart-item`, `.cart-summary-section` |
| State classes | `.open`, `.active`, `.animate` | `.cart-drawer.open`, `.hero-dot.active` |
| Interactive | `.btn-*` | `.btn-primary`, `.btn-add-cart`, `.btn-upsell-add` |
| Progress | `.cart-progress-*` | `.cart-progress-bar`, `.cart-progress-dot` |
| Product cards | `.product-*` | `.product-card`, `.product-price`, `.product-title` |

## PHP

| Type | Convention | Examples |
|------|-----------|----------|
| Functions | `ruut_` prefix, snake_case | `ruut_ensure_cart`, `ruut_ajax_add_to_cart` |
| AJAX actions | `ruut_` prefix | `ruut_add_to_cart`, `ruut_get_cart` |
| Options | `ruut_` prefix | `ruut_announcement_text`, `ruut_reward_threshold` |
| User meta | `_ruut_` prefix | `_ruut_needs_onboarding` |
| Transients | `ruut_` prefix | `ruut_otp_{hash}` |
| REST namespace | `ruut/v1` | `/wp-json/ruut/v1/products` |

## localStorage Keys

| Key | Format | Examples |
|-----|--------|----------|
| Cart data | `ruut_cart` | JSON array |
| Coupon | `ruut_coupon` | String: `'DEFAULT'`, `'TEST-12'`, `'TEST-34'` |

## File Naming
- HTML: kebab-case — `cart.html`, `about-us.html`, `the-atlas-collection.html`
- CSS: kebab-case — `styles.css`, `fonts.css`
- JS: kebab-case — `app.js`, `checkout.js`
- PHP: kebab-case — `ruut-core.php`
- Assets: kebab-case with descriptive names — `hero_desktop.webp`, `ruut-logo-CF6400.png`
