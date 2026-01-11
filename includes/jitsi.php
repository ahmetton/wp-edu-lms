<?php
// wp-edu-lms/includes/jitsi.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * وظائف بسيطة لدمج Jitsi (غرفة على مستوى الدرس)
 */

if ( ! function_exists( 'wpedu_get_jitsi_room_for_lesson' ) ) {
    function wpedu_get_jitsi_room_for_lesson( $lesson_id ) {
        $lesson_id = intval( $lesson_id );
        if ( ! $lesson_id ) return '';
        $room = get_post_meta( $lesson_id, '_wpedu_jitsi_room', true );
        return sanitize_text_field( $room );
    }
}

if ( ! function_exists( 'wpedu_jitsi_embed_html_for_lesson' ) ) {
    function wpedu_jitsi_embed_html_for_lesson( $lesson_id, $display_name = '', $allow_new_window = true ) {
        $room = wpedu_get_jitsi_room_for_lesson( $lesson_id );
        if ( empty( $room ) ) {
            return '<div class="wpedu-jitsi-error">لم تُحدد غرفة اجتماع لهذا الدرس.</div>';
        }
        $room_enc = rawurlencode( trim( $room ) );
        $display_name_enc = $display_name ? rawurlencode( $display_name ) : '';
        $hash_parts = array();
        if ( $display_name_enc ) {
            $hash_parts[] = 'userInfo.displayName="' . $display_name_enc . '"';
        }
        $hash_parts[] = 'lang=ar';
        $hash = '#' . implode( '&', $hash_parts );
        $src = 'https://meet.jit.si/' . $room_enc . $hash;

        $iframe  = '<div class="embed-responsive wpedu-jitsi-embed" style="max-width:1200px;margin:0 auto;">';
        $iframe .= '<iframe src="' . esc_url( $src ) . '" allow="camera; microphone; fullscreen; display-capture" allowfullscreen style="border:0;width:100%;height:75vh;"></iframe>';
        $iframe .= '</div>';

        if ( $allow_new_window ) {
            $iframe .= '<div style="text-align:center;margin-top:10px;">';
            $iframe .= '<a class="btn btn-outline" href="' . esc_url( $src ) . '" target="_blank" rel="noopener noreferrer">فتح الاجتماع في نافذة جديدة</a>';
            $iframe .= '</div>';
        }

        return $iframe;
    }
}

if ( ! function_exists( 'wpedu_jitsi_lesson_shortcode' ) ) {
    function wpedu_jitsi_lesson_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'lesson_id' => '',
            'display_name' => '',
            'allow_new_window' => '1',
        ), $atts, 'wpedu_jitsi_lesson' );

        $lesson_id = intval( $atts['lesson_id'] );
        if ( ! $lesson_id ) return '<p>المعرف غير صالح.</p>';
        $display = $atts['display_name'] ?: ( is_user_logged_in() ? wp_get_current_user()->display_name : '' );
        $allow = $atts['allow_new_window'] === '0' ? false : true;
        return wpedu_jitsi_embed_html_for_lesson( $lesson_id, $display, $allow );
    }
    add_shortcode( 'wpedu_jitsi_lesson', 'wpedu_jitsi_lesson_shortcode' );
}

if ( ! function_exists( 'wpedu_render_jitsi_page_for_lesson' ) ) {
    function wpedu_render_jitsi_page_for_lesson( $lesson_id ) {
        $lesson_id = intval( $lesson_id );
        if ( ! $lesson_id ) wp_die( 'معرّف الدرس غير صالح' );

        $room = wpedu_get_jitsi_room_for_lesson( $lesson_id );
        if ( empty( $room ) ) wp_die( 'لا توجد غرفة اجتماع لهذا الدرس' );

        $course_id = get_post_meta( $lesson_id, '_course_id', true );
        $can_join = false;
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $enrolled = get_user_meta( $user_id, 'wpedu_enrolled_courses', true ) ?: array();
            if ( in_array( intval( $course_id ), $enrolled, true ) ) $can_join = true;
            if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'edit_posts' ) ) $can_join = true;
        }
        if ( ! $can_join ) auth_redirect();

        $display = is_user_logged_in() ? wp_get_current_user()->display_name : '';
        get_header();
        echo '<main class="site-container" style="padding-top:24px;padding-bottom:40px;">';
        echo '<h1 style="margin-bottom:10px;">جلسة مباشرة — ' . esc_html( get_the_title( $lesson_id ) ) . '</h1>';
        echo wpedu_jitsi_embed_html_for_lesson( $lesson_id, $display, true );
        echo '</main>';
        get_footer();
        exit;
    }
}