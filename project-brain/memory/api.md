# RUUT Cart System — API Reference

## AJAX Endpoints (Cart Operations)
Base URL: `/wp-admin/admin-ajax.php`
Method: POST
Content-Type: `application/x-www-form-urlencoded`
Credentials: `include` (for cookie-based WooCommerce sessions)

### GET Cart
```
action=ruut_get_cart
```
**Response**: `{ success: true, data: { items: [...], totals: {...} } }`

### Add to Cart
```
action=ruut_add_to_cart
product_id=123
quantity=1
```
**Response**: Full cart response with updated items and totals.

### Update Quantity
```
action=ruut_update_cart_qty
cart_item_key=abc123def456
quantity=3
```
**Note**: `cart_item_key` is the WooCommerce hash, not the `product-{id}` local key.

### Remove Item
```
action=ruut_remove_cart_item
cart_item_key=abc123def456
```

### Apply Coupon
```
action=ruut_apply_coupon
coupon_code=TEST-12
```

### Remove Coupon
```
action=ruut_remove_coupon
coupon_code=TEST-12
```

## REST API Endpoints
Base URL: `/wp-json/ruut/v1/`
Method: GET (for all cart-relevant endpoints)

### Products
```
GET /wp-json/ruut/v1/products?limit=100
```
**Response**: Array of product objects:
```json
[
  {
    "id": 123,
    "title": "The Street of Kannauj",
    "price": "650.00",
    "regular_price": "750.00",
    "sale_price": "650.00",
    "image": "https://...",
    "categories": [
      { "slug": "the-atlas-collection", "name": "The Atlas Collection", "parent": 15, "parent_slug": "air-mists" }
    ],
    "is_featured": true,
    "average_rating": "4.5",
    "sku": "RUUT-001",
    "volume": "200ml",
    "discount_percentage": 13,
    "description": "...",
    "short_description": "..."
  }
]
```

### Collections
```
GET /wp-json/ruut/v1/collections
```
**Response**: Array of category objects:
```json
[
  {
    "title": "The Atlas Collection",
    "slug": "the-atlas-collection",
    "count": 5,
    "image": "https://..."
  }
]
```

## Frontend API Wrapper
All API calls go through `window.ruutFetch(url, options)`:
- Adds 10-second timeout via `AbortController`
- Detects `file://` protocol and rejects immediately
- Returns standard `fetch()` Promise

## Product Catalog Cache
- Fetched once via `window.ensureProductsFetched()`
- Stored in `window.allProducts` (global)
- Prevents duplicate API calls via `window.activeProductsFetch` guard
- Used by: `addToCart()`, `renderCart()`, `loadCartPageSuggestions()`, product page rendering

## Error Handling
- AJAX sync errors are caught silently (cart continues working locally)
- REST API errors log to console and show fallback empty states
- `file://` protocol falls back to localStorage-only mode
