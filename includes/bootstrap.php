<?php
/**
 * wp-edu-lms/includes/bootstrap.php
 * Bootstrapping the plugin: register autoload, logger, includes and activation hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

namespace WPEDU_LMS;

function safe_require( $path ) {
    if ( file_exists( $path ) ) {
        require_once $path;
        return true;
    }
    return false;
}

function bootstrap() {
    // logger first
    safe_require( WPEDU_LMS_INC . 'logger.php' );

    // register shutdown handler to catch fatals early (logger sets it up)
    logger\register_shutdown_handler();

    // includes core modules (use require_once to avoid redeclare)
    safe_require( WPEDU_LMS_INC . 'roles.php' );
    safe_require( WPEDU_LMS_INC . 'post-types.php' );
    safe_require( WPEDU_LMS_INC . 'ajax-handlers.php' );
    safe_require( WPEDU_LMS_INC . 'certificate.php' );
    safe_require( WPEDU_LMS_INC . 'jitsi.php' );
    safe_require( WPEDU_LMS_INC . 'admin/settings.php' );

    // activation/deactivation hooks (use functions if exist)
    if ( ! function_exists( 'wpedu_lms_activate' ) ) {
        function wpedu_lms_activate() {
            if ( function_exists( '\\WPEDU_LMS\\roles\\add_roles' ) ) {
                \WPEDU_LMS\roles\add_roles();
            } elseif ( function_exists( 'wpedu_lms_add_roles' ) ) {
                wpedu_lms_add_roles();
            }
            flush_rewrite_rules();
        }
    }

    if ( ! function_exists( 'wpedu_lms_deactivate' ) ) {
        function wpedu_lms_deactivate() {
            flush_rewrite_rules();
        }
    }

    register_activation_hook( WPEDU_LMS_PATH . basename( WPEDU_LMS_PATH ), 'wpedu_lms_activate' );
    register_deactivation_hook( WPEDU_LMS_PATH . basename( WPEDU_LMS_PATH ), 'wpedu_lms_deactivate' );

    // Init procedures
    add_action( 'init', function() {
        // Ensure certificate secret exists (but admin settings page can change it)
        if ( ! get_option( 'wpedu_cert_secret_key' ) ) {
            update_option( 'wpedu_cert_secret_key', wp_generate_password( 32, true, true ) );
        }
    } );
}