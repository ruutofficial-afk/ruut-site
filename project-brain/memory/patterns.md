# RUUT Cart System — Patterns

## Pattern 1: Local-First Cart Mutation
Every cart operation follows this pattern:
1. **Mutate local state immediately** (for instant UI responsiveness)
2. **Re-render UI** via `renderCart()`
3. **Sync to server** via `syncCartWithServer()` (fire-and-forget)
4. **Merge server response** (update `item.key` from WooCommerce hash)

```javascript
// Template:
cart[index].quantity += delta;     // 1. Mutate local
renderCart();                       // 2. Re-render
syncCartWithServer('action', {});   // 3. Server sync
```

**Why**: Users see instant feedback. If the server is slow or offline, the cart still works. WooCommerce session catches up asynchronously.

## Pattern 2: Product Lookup Before Cart Add
When adding to cart, always resolve product details from `window.allProducts`:
```javascript
let product = null;
if (window.allProducts) {
  // Try numeric ID first, then title string
  if (typeof productNameOrId === 'number' || !isNaN(productNameOrId)) {
    product = window.allProducts.find(p => p.id == productNameOrId);
  } else {
    product = window.allProducts.find(p => p.title === productNameOrId);
  }
}
```
**Why**: Ensures consistent pricing, image URLs, and metadata. Prevents stale data from inline `onclick` handlers.

## Pattern 3: Cart Key Differentiation
Local cart items use `product-{id}` as the key. WooCommerce uses a hash string.
```javascript
// Only sync to server if key is a real WooCommerce hash
if (itemKey && !itemKey.startsWith('product-')) {
  syncCartWithServer('ruut_remove_cart_item', { cart_item_key: itemKey });
}
```
**Why**: Prevents sending invalid keys to WooCommerce before the initial sync response arrives.

## Pattern 4: Coupon Loss Prevention
Before applying a new coupon, check if the new discount is lower than the current one:
```javascript
if (newDiscount < oldDiscount && activeCoupon !== '') {
  // Show confirmation overlay
  pendingCoupon = code;
  ruutConfirmOverlay.style.display = 'flex';
} else {
  // Apply directly
  activeCoupon = code;
}
```
**Why**: Protects users from accidentally replacing a higher-value coupon.

## Pattern 5: Dynamic Multiple Thresholds Progress Bar
Track total quantity (sum of quantities) of regular (non-gift) items across multiple tier-based thresholds:
- Expose settings (`offer_enabled`, `offer_thresholds` JSON array) from the backend.
- Automatically add compulsory gift and/or dynamic stock-based random tester gift products (resolved to the product in the configured pool with the highest stock quantity) via `woocommerce_before_calculate_totals` hook.
- Apply active threshold's flat discount as a negative fee via `woocommerce_cart_calculate_fees` hook.
- Render dots and position labels dynamically via JavaScript on the frontend, displaying the specific resolved highest-stock product name in next offer texts, adjusting fill percentage smoothly across threshold boundary slots.
- Render gift items on both mini-cart and cart page without quantity adjustment selectors or delete buttons.

## Pattern 6: Upsell Sub-Category Matching
Suggest products from the same sub-category as the last item added:
```javascript
const lastItem = cart[cart.length - 1];
const lastProduct = window.allProducts.find(p => p.id == lastItem.id);
// Find sub-category of last product
// Filter allProducts by same sub-category, excluding cart items
// Fallback: featured products → all products
```
**Why**: Relevant cross-sell suggestions increase conversion. Sub-category matching ensures thematic coherence.

## Pattern 7: Button State Animation
Add-to-cart buttons show progressive feedback:
```javascript
btnElement.textContent = 'ADDING...';
btnElement.disabled = true;
// After 500ms:
btnElement.textContent = 'ADDED!';
// After 1000ms more:
btnElement.textContent = originalText;
btnElement.disabled = false;
```
**Why**: Gives users visual confirmation without blocking interaction.

## Pattern 8: Cart Badge Pop Animation
```javascript
cartCountEl.classList.remove('pop');
void cartCountEl.offsetWidth;  // Force reflow
cartCountEl.classList.add('pop');
```
**Why**: CSS animation restart requires removing the class, forcing a reflow, then re-adding.

## Pattern 9: Lazy Product Catalog Fetch
```javascript
window.ensureProductsFetched = () => {
  if (window.allProducts && window.allProducts.length > 0) {
    return Promise.resolve(window.allProducts);  // Already cached
  }
  if (window.activeProductsFetch) {
    return window.activeProductsFetch;  // Dedup in-flight request
  }
  window.activeProductsFetch = ruutFetch('/wp-json/ruut/v1/products?limit=100')
    .then(...)
  return window.activeProductsFetch;
};
```
**Why**: Single request, cached globally, deduplicated across concurrent callers.

## Pattern 10: PHP Cart Session Loading
```php
function ruut_ensure_cart() {
    if (is_null(WC()->cart) || is_null(WC()->session)) {
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
        include_once WC_ABSPATH . 'includes/class-wc-cart.php';
        include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        include_once WC_ABSPATH . 'includes/class-wc-customer.php';
        wc_load_cart();
    }
}
```
**Why**: WooCommerce doesn't auto-load cart objects during AJAX calls. This must be called at the start of every cart AJAX handler.
