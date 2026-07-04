# RUUT Cart System — Architecture

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────┐
│                    FRONTEND                          │
│  (Static HTML5 / Vanilla CSS3 / Vanilla JavaScript)  │
│                                                       │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │ Cart Drawer  │  │ Cart Page    │  │ Product Cards│ │
│  │ (mini-cart)  │  │ (cart.html)  │  │ (all pages)  │ │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘ │
│         │                 │                  │         │
│         └────────┬────────┴──────────────────┘         │
│                  │                                     │
│         ┌────────▼────────┐                            │
│         │    app.js        │                           │
│         │  Cart Module     │                           │
│         │  ┌────────────┐  │                           │
│         │  │ localStorage│  │                           │
│         │  │ ruut_cart   │  │                           │
│         │  │ ruut_coupon │  │                           │
│         │  └────────────┘  │                           │
│         └────────┬─────────┘                           │
└──────────────────┼─────────────────────────────────────┘
                   │ AJAX POST (fire-and-forget)
                   │ /wp-admin/admin-ajax.php
                   ▼
┌─────────────────────────────────────────────────────┐
│                    BACKEND                           │
│           WordPress / WooCommerce                    │
│                                                       │
│  ┌─────────────────────────────────────────────────┐  │
│  │              ruut-core.php                       │  │
│  │  ┌───────────────┐  ┌────────────────────────┐  │  │
│  │  │ AJAX Handlers │  │ REST API Endpoints     │  │  │
│  │  │ ruut_add_to_  │  │ /wp-json/ruut/v1/      │  │  │
│  │  │ ruut_get_cart │  │   products             │  │  │
│  │  │ ruut_update_  │  │   collections          │  │  │
│  │  │ ruut_remove_  │  │   story                │  │  │
│  │  │ ruut_apply_   │  │   reels                │  │  │
│  │  │ ruut_remove_  │  │   send-otp / verify    │  │  │
│  │  └───────┬───────┘  └────────────────────────┘  │  │
│  │          │                                       │  │
│  │  ┌───────▼───────┐                               │  │
│  │  │ WC()->cart     │                               │  │
│  │  │ WC()->session  │                               │  │
│  │  └───────┬───────┘                               │  │
│  └──────────┼───────────────────────────────────────┘  │
│             ▼                                          │
│      ┌──────────────┐                                  │
│      │   MySQL DB    │                                  │
│      └──────────────┘                                  │
└─────────────────────────────────────────────────────┘
```

## Cart Data Flow Model

### Add to Cart
```
User clicks "Add to Cart" / "Buy Now"
       │
       ▼
window.addToCart(productNameOrId, price)
       │
       ├─► Lookup product in window.allProducts
       │   (pre-fetched from /wp-json/ruut/v1/products)
       │
       ├─► Update local cart[] array
       │
       ├─► Call renderCart()
       │   ├─► Update cart badge (#cartCount)
       │   ├─► Update mini-cart items (#cartItemsList)
       │   ├─► Update progress bar (#ruutMinicartFill)
       │   ├─► Update upsell section (#cartUpsellSection)
       │   ├─► Update subtotals (#cartSubtotal)
       │   ├─► Save to localStorage('ruut_cart')
       │   └─► If cart.html: call window.renderCartPage()
       │
       ├─► Call openCart() (open mini-cart drawer)
       │
       ├─► Call window.showToast()
       │
       └─► Call syncCartWithServer('ruut_add_to_cart', {product_id, quantity})
            │
            └─► POST /wp-admin/admin-ajax.php
                 │
                 ▼
            ruut_ajax_add_to_cart()
                 ├─► ruut_ensure_cart()
                 ├─► WC()->cart->add_to_cart(id, qty)
                 └─► Return ruut_get_cart_response()
```

### Coupon Flow
```
User enters coupon code
       │
       ▼
window.tryApplyCoupon(code)
       │
       ├─► Calculate current discount (old coupon)
       ├─► Calculate new discount (new coupon)
       │
       ├── If new discount < old discount (loss prevention):
       │   └─► Show confirmation overlay (#ruut-confirm-overlay)
       │       ├── Cancel → keep old coupon
       │       └── Confirm → apply new coupon
       │
       └── Else: apply directly
            ├─► Set activeCoupon = code
            ├─► renderCart()
            ├─► syncCartWithServer('ruut_apply_coupon', {coupon_code})
            └─► showToast('Applied coupon: ...')
```

## State Ownership

| State | Owner | Sync Direction |
|-------|-------|---------------|
| Cart items | localStorage → WooCommerce | Frontend-first, server-synced |
| Coupon code | localStorage → WooCommerce | Frontend-first, server-synced |
| Product catalog | WooCommerce → Frontend | Server → cached in `window.allProducts` |
| WooCommerce session | Server-side cookies | Server-authoritative at checkout |
| Cart item keys | WooCommerce → Frontend | Server → merged into local items |
