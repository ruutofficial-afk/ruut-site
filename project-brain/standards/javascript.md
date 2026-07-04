# Standards — JavaScript

## Language
Vanilla JavaScript (ES6+). No frameworks, no transpilation, no bundling.

## Global Exposure Pattern
All public functions are exposed via `window.*` inside the `DOMContentLoaded` listener:
```javascript
document.addEventListener('DOMContentLoaded', () => {
  // Private functions defined as const
  const renderCart = () => { ... };

  // Public functions exposed globally
  window.addToCart = (productNameOrId, price, ...args) => { ... };
  window.updateQty = (index, delta) => { ... };
  window.removeCartItem = (index) => { ... };
});
```

## Fetch Wrapper
All network requests MUST use `window.ruutFetch()`:
```javascript
// ✅ Correct
ruutFetch('/wp-admin/admin-ajax.php', { method: 'POST', ... })

// ❌ Wrong
fetch('/wp-admin/admin-ajax.php', { method: 'POST', ... })
```
The wrapper provides:
- 10-second timeout via `AbortController`
- `file://` protocol detection and early rejection

## Error Handling
- AJAX sync errors: catch silently, cart continues working locally
- REST API errors: log to console, show empty state fallback
- localStorage errors: wrap in try/catch, log error
```javascript
try {
  const savedCart = localStorage.getItem('ruut_cart');
  if (savedCart) cart = JSON.parse(savedCart);
} catch (e) {
  console.error('Failed to load cart from localStorage', e);
}
```

## AJAX Request Format
```javascript
const formBody = new URLSearchParams({ action, ...data }).toString();
ruutFetch('/wp-admin/admin-ajax.php', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: formBody
})
```

## Naming Conventions
- Functions: `camelCase` — `renderCart`, `addToCart`, `syncCartWithServer`
- DOM element variables: `camelCase` — `cartCountEl`, `cartDrawer`, `cartItemsList`
- Constants: `camelCase` — `scrollThreshold`, `slideInterval`
- Event handlers: verb-based — `openCart`, `closeCart`, `handleScroll`

## Animation Pattern
Force reflow before re-adding CSS class:
```javascript
element.classList.remove('pop');
void element.offsetWidth;  // Force reflow
element.classList.add('pop');
```

## Inline onclick Handlers
Product cards use inline `onclick` due to dynamic HTML generation:
```javascript
onclick="addToCart('${escapedTitle}', ${price})"
```
Escape single quotes in product titles:
```javascript
const escapedTitle = product.title.replace(/'/g, "\\'");
```
