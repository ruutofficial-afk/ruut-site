# RUUT Cart System — Frontend

## File Location
`assets/js/app.js` (57KB, 1526 lines)

## Core Cart Functions

### renderCart() — Lines 377-635
**Purpose**: Master render function for the mini-cart drawer.
**Triggers**: Called after every cart mutation (add, remove, update qty, coupon change).
**Responsibilities**:
1. Calculate `totalQuantity`, `originalSubtotal`, `discountedSubtotal`
2. Update cart badge (`#cartCount`) with pop animation
3. Save to localStorage
4. Trigger `window.loadCartPageSuggestions()` if available
5. Trigger `window.renderCartPage()` if on cart.html
6. Render empty state with bestseller suggestions if cart is empty
7. Render each cart item with quantity controls and remove button
8. Apply active coupon discount calculation
9. Update progress bar (3 unique items → ₹250 off)
10. Render upsell suggestions ("Complete The Ritual")

### addToCart(productNameOrId, price, ...args) — Lines 731-812
**Purpose**: Add item to cart.
**Flow**:
1. Detect if `btnElement` was passed (for button state animation)
2. Lookup product in `window.allProducts` by ID or name
3. Check if already in cart → increment quantity
4. Else → push new item object
5. Call `renderCart()` + `openCart()` + `showToast()`
6. Sync to server via `syncCartWithServer('ruut_add_to_cart', ...)`

### updateQty(index, delta) — Lines 702-718
**Purpose**: Increment/decrement item quantity.
**Flow**:
1. Modify `cart[index].quantity` by `delta`
2. If quantity ≤ 0 → splice item, sync removal to server
3. Else → renderCart(), sync new quantity to server

### removeCartItem(index) — Lines 721-728
**Purpose**: Remove item from cart entirely.
**Flow**:
1. Get `itemKey` for server sync
2. Splice from cart array
3. renderCart()
4. Sync removal to server (if key is a real WooCommerce key, not `product-*`)

### syncCartWithServer(action, data) — Lines 328-353
**Purpose**: Fire-and-forget AJAX sync to WooCommerce.
**Flow**:
1. Skip if `file://` protocol
2. POST to `/wp-admin/admin-ajax.php`
3. On success: merge server `item.key` values into local cart items
4. Catch errors silently (cart still works locally)

### renderCartPage() — Lines 639-698
**Purpose**: Render the standalone cart page (`cart.html`).
**DOM targets**: `#cartPageItemsList`, `#billingSubtotal`, `#billingTotal`
**Flow**: Maps cart items into styled cards with quantity controls and remove buttons.

### loadCartPageSuggestions() — Lines 969-1006
**Purpose**: Load "Suggested for You" products on cart.html.
**Flow**: Fetch all products, filter out items already in cart, render up to 3 suggestions.

## Cart Item Data Structure
```javascript
{
  name: "The Street of Kannauj",
  price: 650.00,
  originalPrice: 750.00,
  quantity: 1,
  image: "https://yourruut.com/wp-content/uploads/...",
  volume: "200ml",
  sku: "RUUT-001",
  id: 123,
  key: "product-123"  // or WooCommerce hash after server sync
}
```

## DOM Elements (Cart Drawer)

| Element ID | Purpose |
|-----------|---------|
| `#astra-mobile-cart-drawer` | Cart drawer container |
| `#astra-cart-drawer-overlay` | Backdrop overlay |
| `#cartButton` | Header cart icon (opens drawer) |
| `#closeCartDrawer` | Close button inside drawer |
| `#cartCount` | Badge showing item count |
| `#cartItemsList` | Container for mini-cart item list |
| `#ruut-minicart-progress` | Progress bar wrapper |
| `#ruutMinicartText` | Progress bar text |
| `#ruutMinicartFill` | Progress bar fill element |
| `#ruutDot1`, `#ruutDot2` | Progress dots (at 66% and 100%) |
| `#cartSubtotalOriginal` | Crossed-out original price |
| `#cartSubtotal` | Discounted subtotal |
| `#cartUpsellSection` | "Complete The Ritual" upsell area |
| `#ruut_coupon_code` | Promo code input field |
| `#couponMessages` | Coupon status messages |
| `#exploreCouponsBtn` | "Explore all active coupons" link |

## DOM Elements (Cart Page - cart.html)

| Element ID | Purpose |
|-----------|---------|
| `#cartPage` | Body ID for cart page detection |
| `#cartPageItemsList` | Full cart items container |
| `#billingSubtotal` | Order summary subtotal |
| `#billingTotal` | Order summary total |
| `#cartPageSuggestionsSection` | "Suggested for You" section wrapper |
| `#cartPageSuggestionsGrid` | Product cards grid for suggestions |

## Coupon State
- `activeCoupon` variable: `'DEFAULT'` | `'TEST-12'` | `'TEST-34'`
- Stored in `localStorage('ruut_coupon')`
- Discount logic:
  - `DEFAULT` → ₹100 flat
  - `TEST-12` → ₹200 flat
  - `TEST-34` → 5% of discounted subtotal

## Helper Functions
- `openCart()` / `closeCart()` — Toggle drawer visibility
- `showToast(message)` — Display notification toast
- `buyNow(productNameOrId, price)` — Add + redirect to `/checkout/`
- `applyCouponCode()` — Read input, validate, apply
- `tryApplyCoupon(code)` — Apply with loss-prevention check
- `removeCoupon(code)` — Revert to DEFAULT
- `getWooCommerceRedirectUrl(page)` — Build checkout/cart URL with item IDs
- `ensureProductsFetched()` — Lazy-fetch product catalog from API
- `getProductUrl(product)` — Build dynamic product page URL
