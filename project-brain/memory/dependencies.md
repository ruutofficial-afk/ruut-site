# RUUT Cart System — Dependencies

## Runtime Dependencies

### WordPress/WooCommerce (Backend)
- WordPress 6.x
- WooCommerce (latest stable)
- PHP 7.4+ (for `Throwable` catch)
- MySQL 5.7+

### Browser APIs (Frontend)
- `fetch` API (with `AbortController` for timeouts)
- `localStorage` API
- `IntersectionObserver` API (for animations)
- `URLSearchParams` API (for query parsing)

## Internal Dependencies

### app.js Module Dependencies
```
addToCart()
  └─► window.allProducts (from ensureProductsFetched)
  └─► renderCart()
  └─► openCart()
  └─► showToast()
  └─► syncCartWithServer()

renderCart()
  └─► saveCartToStorage()
  └─► window.loadCartPageSuggestions() (if cart.html)
  └─► window.renderCartPage() (if cart.html)

syncCartWithServer()
  └─► ruutFetch() (global wrapper)

buyNow()
  └─► addToCart()
  └─► window.location.href redirect

tryApplyCoupon()
  └─► renderCart()
  └─► syncCartWithServer()
  └─► showToast()
```

### PHP Function Dependencies
```
ruut_ajax_add_to_cart()
  └─► ruut_ensure_cart()
  └─► WC()->cart->add_to_cart()
  └─► ruut_get_cart_response()

ruut_get_cart_response()
  └─► ruut_ensure_cart()
  └─► WC()->cart->get_cart()
  └─► WC()->cart->calculate_totals()
  └─► wp_get_attachment_image_url()
```

## localStorage Keys

| Key | Type | Purpose | Default |
|-----|------|---------|---------|
| `ruut_cart` | JSON Array | Cart items state | `[]` |
| `ruut_coupon` | String | Active coupon code | `'DEFAULT'` |

## Global JavaScript Variables

| Variable | Type | Set By | Purpose |
|----------|------|--------|---------|
| `window.allProducts` | Array | `ensureProductsFetched()` | Cached product catalog |
| `window.activeProductsFetch` | Promise | `ensureProductsFetched()` | Dedup guard for API calls |
| `window.ruutFetch` | Function | Top-level (line 5) | Fetch wrapper with timeout |
| `window.getProductUrl` | Function | DOMContentLoaded | URL builder for products |
| `window.addToCart` | Function | DOMContentLoaded | Global add-to-cart handler |
| `window.updateQty` | Function | DOMContentLoaded | Global quantity updater |
| `window.removeCartItem` | Function | DOMContentLoaded | Global item remover |
| `window.buyNow` | Function | DOMContentLoaded | Add + redirect to checkout |
| `window.showToast` | Function | DOMContentLoaded | Toast notification |
| `window.renderCartPage` | Function | DOMContentLoaded | Cart page renderer |
| `window.loadCartPageSuggestions` | Function | DOMContentLoaded | Cart page suggestions |
| `window.tryApplyCoupon` | Function | DOMContentLoaded | Coupon applicator |
| `window.applyCouponCode` | Function | DOMContentLoaded | Coupon input handler |
| `window.removeCoupon` | Function | DOMContentLoaded | Coupon remover |

## Asset Versioning
Every HTML file must reference assets with version query parameters:

| Asset | Current Version | Location |
|-------|----------------|----------|
| `styles.css` | `?v=6.9` | All 6 HTML files `<head>` |
| `app.js` | `?v=7.5` | All 6 HTML files `<head>` |

**Affected Files** (when bumping versions):
1. `index.html`
2. `shop.html`
3. `product.html`
4. `cart.html`
5. `about-us.html`
6. `the-atlas-collection.html`

## CSS Custom Properties (Cart-Relevant)

| Property | Usage |
|----------|-------|
| `--ruut-accent` | Button backgrounds, price color, progress bar fill |
| `--ruut-text` | Primary text color |
| `--ruut-gray` | Secondary text, labels |
| `--ruut-white` | White backgrounds |
| `--ruut-border` | Subtle borders |
| `--font-serif` | Heading typography |
