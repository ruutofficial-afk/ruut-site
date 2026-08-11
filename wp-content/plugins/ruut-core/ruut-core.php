<?php
/**
 * Plugin Name: RUUT Core System
 * Description: Unified WordPress, WooCommerce Customizations & Headless REST API Handler (Safe Edition)
 * Version: 1.0.0
 * Author: RUUT
 * 
 * This file combines:
 * 1. Standard WooCommerce My Account page custom tabs, OTP auth, onboarding flow, and dashboard layout.
 * 2. Headless REST API endpoints (/ruut/v1) for the static landing page's My Account panel.
 * 3. Direct checkout multi-cart synchronizer.
 * 4. Dynamic CORS credentials configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Plugin Path
define( 'RUUT_CORE_PATH', plugin_dir_path( __FILE__ ) );

// Load Core Helpers & Utilities
require_once RUUT_CORE_PATH . 'includes/helpers.php';

// Load CORS Configuration
require_once RUUT_CORE_PATH . 'includes/cors.php';

// Load Routing & Redirections
require_once RUUT_CORE_PATH . 'includes/routing.php';

// Load Admin Settings Pages
require_once RUUT_CORE_PATH . 'includes/admin-settings.php';

// Load OTP & Authentication
require_once RUUT_CORE_PATH . 'includes/auth-otp.php';

// Load User Onboarding Overlay
require_once RUUT_CORE_PATH . 'includes/onboarding.php';

// Load Address Book Functionality
require_once RUUT_CORE_PATH . 'includes/address-book.php';

// Load Checkout Customizations & OTP Verification
require_once RUUT_CORE_PATH . 'includes/checkout.php';

// Load Coupon Processing
require_once RUUT_CORE_PATH . 'includes/coupons-retention.php';

// Load Cart Synchronization (AJAX)
require_once RUUT_CORE_PATH . 'includes/cart-sync.php';

// Load Direct Checkout (URL Handler)
require_once RUUT_CORE_PATH . 'includes/direct-checkout.php';

// Load Headless REST API Base Routes
require_once RUUT_CORE_PATH . 'includes/rest-api.php';

// Load Content & Custom Post Type Modules
require_once RUUT_CORE_PATH . 'includes/content/story.php';
require_once RUUT_CORE_PATH . 'includes/content/faqs.php';
require_once RUUT_CORE_PATH . 'includes/content/scent-details.php';
require_once RUUT_CORE_PATH . 'includes/content/reviews.php';
require_once RUUT_CORE_PATH . 'includes/content/collections.php';
require_once RUUT_CORE_PATH . 'includes/content/product-reviews.php';
require_once RUUT_CORE_PATH . 'includes/content/reels.php';

