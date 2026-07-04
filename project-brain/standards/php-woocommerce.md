# Standards — PHP / WooCommerce

## Plugin Structure
All backend logic lives in: `wp-content/plugins/ruut-core/ruut-core.php`

## AJAX Handler Pattern
Every cart AJAX handler follows this exact structure:
```php
// Register for both logged-in and guest users
add_action('wp_ajax_ruut_action_name', 'ruut_ajax_action_name');
add_action('wp_ajax_nopriv_ruut_action_name', 'ruut_ajax_action_name');

function ruut_ajax_action_name() {
    try {
        ruut_ensure_cart();  // ALWAYS first

        // Read POST parameters
        $param = isset($_POST['param']) ? sanitize_text_field($_POST['param']) : '';

        // Perform WooCommerce operation
        // ...

        // Return updated cart state
        wp_send_json_success(ruut_get_cart_response());
    } catch (Throwable $e) {
        wp_send_json_error('Fatal: ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
    }
}
```

## Critical Rules

### Always Call ruut_ensure_cart()
WooCommerce doesn't auto-load cart during AJAX. This MUST be the first call:
```php
ruut_ensure_cart();  // Loads WC()->cart and WC()->session
```

### Always Return ruut_get_cart_response()
Every mutation handler should return the full cart state so the frontend can merge server data:
```php
wp_send_json_success(ruut_get_cart_response());
```

### Input Sanitization
```php
// Integers
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// Strings
$cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
$coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
```

### Error Handling
Always wrap in `try/catch (Throwable $e)` to prevent white-screen deaths:
```php
try {
    // ... handler logic
} catch (Throwable $e) {
    wp_send_json_error('Fatal: ' . $e->getMessage());
}
```

## Function Naming
- Prefix all functions with `ruut_`
- AJAX handlers: `ruut_ajax_{action_name}`
- Helpers: `ruut_{descriptive_name}`
- Hooks: registered with `add_action` / `add_filter`

## REST API Endpoint Pattern
```php
add_action('rest_api_init', function() {
    register_rest_route('ruut/v1', '/endpoint-name', array(
        'methods'  => 'GET',
        'callback' => 'ruut_endpoint_callback',
        'permission_callback' => '__return_true',
    ));
});
```

## WordPress Options API
Settings stored via Options API:
```php
get_option('ruut_announcement_text', 'default value');
get_option('ruut_reward_threshold', '3');
get_option('ruut_reward_amount', '₹250');
```

## Function Existence Guard
Prevent redeclaration errors with:
```php
if (!function_exists('ruut_function_name')) {
    function ruut_function_name() { ... }
}
```
