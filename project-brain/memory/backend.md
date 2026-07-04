# RUUT Cart System — Backend

## File Location
`wp-content/plugins/ruut-core/ruut-core.php` (134KB, ~2800 lines)

## Cart AJAX Endpoints

### ruut_ensure_cart()
**Purpose**: Ensures WooCommerce cart is loaded during AJAX calls.
**Location**: Lines 94-107 of `reconstructed_ruut_core.php`
```php
function ruut_ensure_cart() {
    if (!defined('WC_ABSPATH')) return;
    if (is_null(WC()->cart) || is_null(WC()->session)) {
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
        include_once WC_ABSPATH . 'includes/class-wc-cart.php';
        include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        include_once WC_ABSPATH . 'includes/class-wc-customer.php';
        wc_load_cart();
    }
}
```
**Critical**: Must be called at the start of every AJAX handler.

### ruut_get_cart_response()
**Purpose**: Returns formatted cart data as associative array.
**Returns**:
```json
{
  "items": [
    {
      "key": "cart_item_hash",
      "id": 123,
      "name": "Product Name",
      "price": "650.00",
      "originalPrice": "750.00",
      "quantity": 2,
      "image": "https://...",
      "volume": "200ml",
      "sku": "RUUT-001"
    }
  ],
  "totals": {
    "subtotal": 1300.00,
    "total": 1200.00,
    "discount_total": 100.00,
    "applied_coupons": ["TEST-12"]
  }
}
```

### AJAX Action Registry

| Action | Hook | Function | Method | Parameters |
|--------|------|----------|--------|------------|
| `ruut_get_cart` | `wp_ajax_*`, `wp_ajax_nopriv_*` | `ruut_ajax_get_cart()` | POST | none |
| `ruut_add_to_cart` | `wp_ajax_*`, `wp_ajax_nopriv_*` | `ruut_ajax_add_to_cart()` | POST | `product_id` (int), `quantity` (int) |
| `ruut_update_cart_qty` | `wp_ajax_*`, `wp_ajax_nopriv_*` | `ruut_ajax_update_cart_qty()` | POST | `cart_item_key` (string), `quantity` (int) |
| `ruut_remove_cart_item` | `wp_ajax_*`, `wp_ajax_nopriv_*` | `ruut_ajax_remove_cart_item()` | POST | `cart_item_key` (string) |
| `ruut_apply_coupon` | `wp_ajax_*`, `wp_ajax_nopriv_*` | `ruut_ajax_apply_coupon()` | POST | `coupon_code` (string) |
| `ruut_remove_coupon` | `wp_ajax_*`, `wp_ajax_nopriv_*` | `ruut_ajax_remove_coupon()` | POST | `coupon_code` (string) |

### Request Format
All AJAX requests use `application/x-www-form-urlencoded`:
```
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded

action=ruut_add_to_cart&product_id=123&quantity=1
```

### Response Format
All responses use `wp_send_json_success()` / `wp_send_json_error()`:
```json
// Success
{ "success": true, "data": { "items": [...], "totals": {...} } }

// Error
{ "success": false, "data": "Error message" }
```

## REST API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/ruut/v1/products` | GET | Fetch all products (cached in `window.allProducts`) |
| `/wp-json/ruut/v1/collections` | GET | Fetch product categories/collections |
| `/wp-json/ruut/v1/story` | GET | Fetch "The Ruut Story" content |
| `/wp-json/ruut/v1/reels` | GET | Fetch product reels for homepage carousel |
| `/wp-json/ruut/v1/send-otp` | POST | Send OTP for authentication |
| `/wp-json/ruut/v1/verify-otp` | POST | Verify OTP and authenticate user |

## CORS Configuration
- Origin verification against allowed frontend domains
- `Access-Control-Allow-Credentials: true` for cookie-based sessions
- Allowed headers: `Content-Type`, `Authorization`

## WooCommerce Admin Settings
- **Announcement bar texts**: `ruut_announcement_text` option
- **Reward threshold**: `ruut_reward_threshold` option (default: 3 unique items)
- **Reward amount**: `ruut_reward_amount` option (default: ₹250)
