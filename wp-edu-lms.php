<?php
/**
 * Plugin Name: WP EDU LMS (مضمن)
 * Description: إضافة LMS مخصصة مدمجة مع ثيم WP EDU — مستقرة ومحسّنة.
 * Version: 1.1.0
 * Author: Ahmad Bilal 
 * Text Domain: wp-edu-lms
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'WPEDU_LMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPEDU_LMS_URL',  plugin_dir_url( __FILE__ ) );
define( 'WPEDU_LMS_VERSION', '1.1.0' );

// Include logger first to capture fatal errors early
if ( file_exists( WPEDU_LMS_PATH . 'includes/logger.php' ) ) {
    require_once WPEDU_LMS_PATH . 'includes/logger.php';
}

// Safe require helper
function wpedu_safe_require( $file ) {
    $path = WPEDU_LMS_PATH . 'includes/' . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
        return true;
    }
    return false;
}

// Load includes (order matters)
wpedu_safe_require( 'roles.php' );
wpedu_safe_require( 'post-types.php' );
wpedu_safe_require( 'ajax-handlers.php' );
wpedu_safe_require( 'certificate.php' );
wpedu_safe_require( 'jitsi.php' );
wpedu_safe_require( 'admin/settings.php' );

// Integration with Next.js authentication system
wpedu_safe_require( 'nextjs-auth-integration.php' );

// Activation / Deactivation
register_activation_hook( __FILE__, 'wpedu_lms_activate' );
function wpedu_lms_activate() {
    if ( function_exists( 'wpedu_lms_add_roles' ) ) {
        wpedu_lms_add_roles();
    }
    // ensure certificate secret exists
    if ( ! get_option( 'wpedu_cert_secret_key' ) ) {
        update_option( 'wpedu_cert_secret_key', wp_generate_password( 32, true, true ) );
    }
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'wpedu_lms_deactivate' );
function wpedu_lms_deactivate() {
    flush_rewrite_rules();
}

// Front assets (minimal)
add_action( 'wp_enqueue_scripts', 'wpedu_lms_front_assets' );
function wpedu_lms_front_assets() {
    // You can replace these with real files in assets/ if needed
    wp_register_style( 'wpedu-lms-style', WPEDU_LMS_URL . 'assets/css/lms.css', array(), WPEDU_LMS_VERSION );
    wp_enqueue_style( 'wpedu-lms-style' );

    wp_register_script( 'wpedu-lms-js', WPEDU_LMS_URL . 'assets/js/lms.js', array( 'jquery' ), WPEDU_LMS_VERSION, true );
    wp_enqueue_script( 'wpedu-lms-js' );
    wp_localize_script( 'wpedu-lms-js', 'wpedu_lms_ajax', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
    ) );
}

// Short safety: register shutdown handler if logger provided
if ( function_exists( 'wpedu_logger_register_shutdown_handler' ) ) {
    wpedu_logger_register_shutdown_handler();
}