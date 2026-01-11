<?php
/**
 * WordPress to Next.js Auth Integration
 * 
 * This file redirects WordPress registration/login to the professional Next.js auth app
 * Configure the AUTH_APP_URL below to point to your Next.js authentication application
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ========================================
// CONFIGURATION - Update this URL to your Next.js auth app
// ========================================
// For local development: http://localhost:3000
// For production: https://auth.yourdomain.com or https://yourdomain.com/auth
define( 'WPEDU_AUTH_APP_URL', 'http://localhost:3000' );

/**
 * Redirect WordPress registration to Next.js auth app
 */
add_action( 'login_form_register', 'wpedu_redirect_to_nextjs_register' );
function wpedu_redirect_to_nextjs_register() {
    // Avoid redirecting in CLI/cron contexts
    if ( php_sapi_name() === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }
    
    $auth_url = WPEDU_AUTH_APP_URL . '/auth/signin';
    
    // Preserve redirect_to parameter if provided
    if ( ! empty( $_REQUEST['redirect_to'] ) ) {
        $redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
        $auth_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $auth_url );
    }
    
    wp_safe_redirect( $auth_url );
    exit;
}

/**
 * Filter register_url() to point to Next.js auth app
 * This affects WordPress core functions that generate registration links
 */
add_filter( 'register_url', 'wpedu_filter_register_url_to_nextjs', 10, 1 );
function wpedu_filter_register_url_to_nextjs( $url ) {
    return WPEDU_AUTH_APP_URL . '/auth/signin';
}

/**
 * Optional: Also redirect login to Next.js (uncomment if you want this)
 */
// add_action( 'login_init', 'wpedu_maybe_redirect_login_to_nextjs' );
// function wpedu_maybe_redirect_login_to_nextjs() {
//     // Only redirect if not on a specific action page (like lostpassword, logout, etc)
//     if ( empty( $_REQUEST['action'] ) || $_REQUEST['action'] === 'login' ) {
//         if ( php_sapi_name() !== 'cli' && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
//             $auth_url = WPEDU_AUTH_APP_URL . '/auth/signin';
//             if ( ! empty( $_REQUEST['redirect_to'] ) ) {
//                 $redirect_to = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) );
//                 $auth_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $auth_url );
//             }
//             wp_safe_redirect( $auth_url );
//             exit;
//         }
//     }
// }

/**
 * Catch any remaining registration attempts via query parameters
 */
add_action( 'init', 'wpedu_catch_register_and_redirect_to_nextjs', 1 );
function wpedu_catch_register_and_redirect_to_nextjs() {
    if ( defined( 'WP_CLI' ) && WP_CLI ) return;
    
    if ( isset( $_SERVER['REQUEST_URI'] ) ) {
        $uri = $_SERVER['REQUEST_URI'];
        
        // Check for action=register in URL
        if ( strpos( $uri, 'action=register' ) !== false || strpos( $uri, '/register' ) !== false ) {
            $auth_url = WPEDU_AUTH_APP_URL . '/auth/signin';
            
            // Preserve redirect_to if present
            if ( isset( $_GET['redirect_to'] ) ) {
                $redirect_to = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
                $auth_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $auth_url );
            }
            
            // Avoid redirect loops - don't redirect if we're already going to the auth app
            if ( strpos( $_SERVER['HTTP_HOST'], 'localhost:3000' ) === false ) {
                wp_safe_redirect( $auth_url );
                exit;
            }
        }
    }
}

/**
 * Remove or modify WordPress registration page if it exists
 */
add_action( 'init', 'wpedu_handle_register_page', 25 );
function wpedu_handle_register_page() {
    $page = get_page_by_path( 'register' );
    
    if ( $page && $page->ID ) {
        // Option 1: Update the page content to redirect to Next.js
        $current_content = get_post_field( 'post_content', $page->ID );
        
        // If it still has the old shortcode, replace with redirect message
        if ( strpos( $current_content, '[wpedu_register_form]' ) !== false ) {
            $new_content = sprintf(
                '<div style="text-align: center; padding: 40px 20px;">
                    <h2>التسجيل</h2>
                    <p>يتم توجيهك إلى صفحة التسجيل الاحترافية...</p>
                    <script>window.location.href = "%s";</script>
                    <noscript>
                        <p><a href="%s" style="display: inline-block; padding: 12px 24px; background: #4F46E5; color: white; text-decoration: none; border-radius: 8px;">انقر هنا للتسجيل</a></p>
                    </noscript>
                </div>',
                esc_url( WPEDU_AUTH_APP_URL . '/auth/signin' ),
                esc_url( WPEDU_AUTH_APP_URL . '/auth/signin' )
            );
            
            wp_update_post( array(
                'ID' => $page->ID,
                'post_content' => $new_content
            ) );
        }
    }
}
