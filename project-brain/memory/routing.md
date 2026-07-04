# RUUT Cart System — Routing

## .htaccess Clean URLs
All pages are served without `.html` extensions via Apache rewrite rules.

### Active Rewrite Rules
```apache
# Remove .html extension
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME}.html -f
RewriteRule ^(.+)$ $1.html [L]

# Product deep linking
RewriteCond %{QUERY_STRING} (?:^|&)(?:product|id)=([^&]+) [NC]
RewriteRule ^shop/.*$ /product.html [QSA,L]
```

## URL Patterns

### Page Routes
| URL | File | Description |
|-----|------|-------------|
| `/` or `/index.html` | `index.html` | Landing page |
| `/shop` | `shop.html` | Catalog page |
| `/the-atlas-collection` | `the-atlas-collection.html` | Atlas collection |
| `/about-us` | `about-us.html` | About page |
| `/cart.html` | `cart.html` | Standalone cart |
| `/my-account/` | WordPress | WooCommerce account |
| `/checkout/` | WordPress | WooCommerce checkout |

### Product Deep Links
Pattern: `/shop/{parentSlug}/{subCategorySlug}/?product={id}`

Built by `getProductUrl(product)` in app.js (lines 27-61):
```javascript
// Logic:
// 1. Check if product is a diffuser → /shop/diffuser/?product={id}
// 2. Find sub-category (parent !== 0) → use its slug
// 3. Normalize parent slug: 'air-mists' → 'airmist'
// 4. Default: /shop/airmist/the-atlas-collection/?product={id}
```

Examples:
- `/shop/airmist/the-atlas-collection/?product=123`
- `/shop/diffuser/?product=456`

### Cart/Checkout Redirect
Built by `getWooCommerceRedirectUrl(page)` in app.js (lines 1172-1180):
```javascript
// Pattern: /{page}/?add-to-cart={id1,id2}&quantity={qty1,qty2}
// Example: /checkout/?add-to-cart=123,456&quantity=1,2
```

## Asset Path Rules
All asset paths in HTML must be **root-relative** (starting with `/`):
- ✅ `/assets/css/styles.css`
- ✅ `/assets/js/app.js`
- ✅ `/assets/images/hero_desktop.webp`
- ❌ `assets/css/styles.css` (breaks under deep paths like `/shop/airmist/`)
- ❌ `./assets/css/styles.css` (breaks under deep paths)

## Logged-In Redirect
Each page `<head>` contains an inline script that checks for the `wordpress_logged_in_` cookie:
```javascript
if (document.cookie.split(';').some(item => item.trim().startsWith('wordpress_logged_in_'))) {
    window.location.replace('shop');
}
```
This redirects authenticated users from the landing page to the shop.
