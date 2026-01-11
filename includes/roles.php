<?php
// wp-edu-lms/includes/logger.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * بسيط: تسجيل رسائل للإضافة + shutdown handler لتسجيل الأخطاء الفادحة
 */

if ( ! function_exists( 'wpedu_logger_log' ) ) {
    function wpedu_logger_log( $msg ) {
        if ( is_array( $msg ) || is_object( $msg ) ) {
            $msg = print_r( $msg, true );
        }
        $line = '[wpedu-lms] ' . $msg;
        // نكتب دائمًا إلى سجل الأخطاء حتى لو لم يكن WP_DEBUG
        error_log( $line );
    }
}

if ( ! function_exists( 'wpedu_logger_register_shutdown_handler' ) ) {
    function wpedu_logger_register_shutdown_handler() {
        register_shutdown_function( function() {
            $err = error_get_last();
            if ( $err && in_array( $err['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
                $msg = sprintf( "Fatal error (%s): %s in %s on line %d", $err['type'], $err['message'], $err['file'], $err['line'] );
                wpedu_logger_log( $msg );
            }
        } );
    }
}