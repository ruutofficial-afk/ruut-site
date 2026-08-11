<?php
/**
 * The Ruut Story REST Endpoint
 * Extracted from ruut-core.php PART 9
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('rest_api_init', 'ruut_register_story_rest_endpoint');
function ruut_register_story_rest_endpoint() {
    register_rest_route('ruut/v1', '/story', array(
        'methods'             => 'GET',
        'callback'            => 'ruut_get_story_api',
        'permission_callback' => '__return_true',
    ));
}

function ruut_get_story_api($request) {
    return rest_ensure_response(array(
        'title'   => get_option('ruut_story_title', 'The Ruut Story'),
        'content' => wpautop(get_option('ruut_story_content', '')),
        'image'   => get_option('ruut_story_image', ''),
    ));
}
