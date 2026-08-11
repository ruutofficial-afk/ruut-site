<?php
/**
 * CORS & Allow-Credentials for Headless Landing Page API Calls
 * Extracted from ruut-core.php PART 7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!function_exists('ruut_api_cors_init')) {
    add_action('rest_api_init', 'ruut_api_cors_init', 15);
    function ruut_api_cors_init() {
        $origin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        header("Access-Control-Allow-Origin: " . $origin);
        if ($origin !== '*') {
            header("Access-Control-Allow-Credentials: true");
        }
        header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With");
    }
}

if (!function_exists('ruut_rest_api_cors_pre_serve')) {
    add_filter('rest_pre_serve_request', 'ruut_rest_api_cors_pre_serve', 10, 4);
    function ruut_rest_api_cors_pre_serve($served, $result, $request, $server) {
        $origin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        header("Access-Control-Allow-Origin: " . $origin);
        if ($origin !== '*') {
            header("Access-Control-Allow-Credentials: true");
        }
        header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Requested-With");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }
        return $served;
    }
}
