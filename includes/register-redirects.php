<?php
// أضف هذا إلى wp-edu-lms/includes/auth.php أو إلى ملف جديد includes/register-redirects.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Redirect default WP register flow to custom register page using [wpedu_register_form]
 * - Creates a page /register/ automatically (if not exists) and stores its ID in option 'wpedu_register_page_id'
 * - Redirects wp-login.php?action=register to that page
 * - Filters register_url() to point to the custom page
 */

/** Ensure register page exists (runs on init) */
add_action( 'init', 'wpedu_ensure_register_page', 20 );
function wpedu_ensure_register_page() {
    // If already set, verify the page exists
    $id = get_option( 'wpedu_register_page_id' );
    if ( $id ) {
        if ( get_post_status( $id ) ) {
            return; // exists
        } else {
            // option stale, delete it and continue to create
            delete_option( 'wpedu_register_page_id' );
            $id = false;
        }
    }

    // try find page by slug 'register'
    $page = get_page_by_path( 'register' );
    if ( $page && $page->ID ) {
        update_option( 'wpedu_register_page_id', $page->ID );
        return;
    }

    // create page programmatically (only if registration UI is enabled)
    // we still create the page regardless; your custom form will handle whether to allow registration
    $postarr = array(
        'post_title'   => 'تسجيل',
        'post_name'    => 'register',
        'post_content' => '[wpedu_register_form]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    );

    // avoid creating during CLI installs
    if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) return;

    $new_id = wp_insert_post( $postarr );
    if ( $new_id && ! is_wp_error( $new_id ) ) {
        update_option( 'wpedu_register_page_id', $new_id );
    }
}

/** Return URL to the custom register page (fallback to /register/) */
function wpedu_get_register_page_url() {
    $id = get_option( 'wpedu_register_page_id' );
    if ( $id && get_post_status( $id ) ) {
        return get_permalink( $id );
    }
    $p = get_page_by_path( 'register' );
    if ( $p && $p->ID ) return get_permalink( $p->ID );
    return site_url( '/register/' );
}

/** Filter register_url() so WordPress functions/linkers use the custom page */
add_filter( 'register_url', 'wpedu_register_url', 10, 1 );
function wpedu_register_url( $url ) {
    return wpedu_get_register_page_url();
}

/** Redirect accesses to wp-login.php?action=register to custom page
 *  Use login_form_register hook which runs when action=register
 */
add_action( 'login_form_register', 'wpedu_redirect_wp_login_register' );
function wpedu_redirect_wp_login_register() {
    // Avoid redirecting in some CLI/cron contexts
    if ( php_sapi_name() === 'cli' ) return;

    $target = wpedu_get_register_page_url();
    // preserve redirect_to if provided
    if ( ! empty( $_REQUEST['redirect_to'] ) ) {
        $target = add_query_arg( 'redirect_to', rawurlencode( wp_unslash( $_REQUEST['redirect_to'] ) ), $target );
    }
    wp_safe_redirect( $target );
    exit;
}

/** Extra safety: if any plugin or link posts users to wp-login.php?redirect_to=...&action=register (rare), catch it earlier */
add_action( 'init', 'wpedu_catch_register_query_and_redirect', 1 );
function wpedu_catch_register_query_and_redirect() {
    if ( defined( 'WP_CLI' ) && WP_CLI ) return;
    if ( isset( $_SERVER['REQUEST_URI'] ) ) {
        $uri = $_SERVER['REQUEST_URI'];
        // quick check for "action=register" in URL
        if ( strpos( $uri, 'action=register' ) !== false ) {
            // build target keeping redirect_to if present
            $target = wpedu_get_register_page_url();
            if ( isset( $_GET['redirect_to'] ) ) {
                $target = add_query_arg( 'redirect_to', rawurlencode( wp_unslash( $_GET['redirect_to'] ) ), $target );
            }
            // avoid redirect loops: don't redirect if already on register page
            $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            if ( strpos( $current_url, '/register' ) === false ) {
                wp_safe_redirect( $target );
                exit;
            }
        }
    }
}