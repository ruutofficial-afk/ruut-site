<?php
/**
 * Serve static index.html for local frontend development
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route isolated staging pages (/staging/, /staging/airmist, /staging/diffuser)
if ($uri === '/staging' || $uri === '/staging/') {
    if (file_exists(__DIR__ . '/staging/index.html')) {
        require __DIR__ . '/staging/index.html';
        exit;
    }
}
if ($uri === '/staging/airmist' || $uri === '/staging/airmist/') {
    if (file_exists(__DIR__ . '/staging/airmist.html')) {
        require __DIR__ . '/staging/airmist.html';
        exit;
    }
}
if ($uri === '/staging/diffuser' || $uri === '/staging/diffuser/') {
    if (file_exists(__DIR__ . '/staging/diffuser.html')) {
        require __DIR__ . '/staging/diffuser.html';
        exit;
    }
}

// Serve static assets directly if they exist
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Redirect direct checkout visits on localhost:3000 to local WordPress site
if (strpos($uri, '/checkout') === 0) {
    header('Location: http://ruut-local.local/checkout/');
    exit;
}

// Route product detail URLs to product.html
if (isset($_GET['product']) || isset($_GET['slug']) || strpos($uri, '/product') !== false || strpos($uri, '/shop/') !== false) {
    if (file_exists(__DIR__ . '/product.html')) {
        require __DIR__ . '/product.html';
        exit;
    }
}

// Route category URLs to category.html
if (isset($_GET['category']) || strpos($uri, '/category') !== false) {
    if (file_exists(__DIR__ . '/category.html')) {
        require __DIR__ . '/category.html';
        exit;
    }
}

// Default fallback to coming-soon.html during pre-launch phase
if ( file_exists( __DIR__ . '/coming-soon.html' ) ) {
    require __DIR__ . '/coming-soon.html';
    exit;
}
if ( file_exists( __DIR__ . '/index.html' ) ) {
    require __DIR__ . '/index.html';
    exit;
}

