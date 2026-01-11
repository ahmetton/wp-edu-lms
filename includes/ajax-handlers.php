<?php
// wp-edu-lms/includes/ajax-handlers.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers: enroll, submit assignment, grade, mark lesson complete
 */

// Enroll (both logged & not)
add_action( 'wp_ajax_nopriv_wpedu_enroll', 'wpedu_ajax_enroll' );
add_action( 'wp_ajax_wpedu_enroll', 'wpedu_ajax_enroll' );
function wpedu_ajax_enroll() {
    $course_id = intval( $_POST['course_id'] ?? 0 );
    if ( ! $course_id ) wp_send_json_error( 'معرّف الدورة غير صالح' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'تحتاج تسجيل الدخول' );
    if ( ! wp_verify_nonce( $_POST['wpedu_enroll_nonce'] ?? '', 'wpedu_enroll_' . $course_id ) ) wp_send_json_error( 'فشل التحقق' );

    $user_id = get_current_user_id();
    $enrolled = get_user_meta( $user_id, 'wpedu_enrolled_courses', true ) ?: array();
    if ( ! in_array( $course_id, $enrolled, true ) ) {
        $enrolled[] = $course_id;
        update_user_meta( $user_id, 'wpedu_enrolled_courses', $enrolled );
        $count = get_post_meta( $course_id, '_students_count', true ) ?: 0;
        update_post_meta( $course_id, '_students_count', intval( $count ) + 1 );
    }

    wp_send_json_success();
}

// Submit assignment
add_action( 'wp_ajax_wpedu_submit_assignment', 'wpedu_ajax_submit_assignment' );
function wpedu_ajax_submit_assignment() {
    if ( ! is_user_logged_in() ) wp_send_json_error( 'تحتاج تسجيل الدخول' );
    $user_id = get_current_user_id();
    $course_id = intval( $_POST['course_id'] ?? 0 );
    $lesson_id = intval( $_POST['lesson_id'] ?? 0 );
    $content = sanitize_textarea_field( $_POST['content'] ?? '' );

    $submission = wp_insert_post( array(
        'post_type' => 'wpedu_submission',
        'post_title' => 'تسليم #' . $course_id . '-' . $lesson_id . ' بواسطة ' . $user_id,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_author' => $user_id,
    ) );

    if ( $submission && ! is_wp_error( $submission ) ) {
        update_post_meta( $submission, '_course_id', $course_id );
        update_post_meta( $submission, '_lesson_id', $lesson_id );
        update_post_meta( $submission, '_graded', '0' );
        wp_send_json_success( array( 'submission_id' => $submission ) );
    }

    wp_send_json_error( 'فشل الحفظ' );
}

// Grade submission
add_action( 'wp_ajax_wpedu_grade_submission', 'wpedu_ajax_grade_submission' );
function wpedu_ajax_grade_submission() {
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'ليس لديك صلاحية' );
    $submission_id = intval( $_POST['submission_id'] ?? 0 );
    $grade = sanitize_text_field( $_POST['grade'] ?? '' );
    if ( ! $submission_id ) wp_send_json_error( 'معرّف غير صالح' );
    update_post_meta( $submission_id, '_grade', $grade );
    update_post_meta( $submission_id, '_graded', '1' );
    wp_send_json_success();
}

// Mark lesson complete and issue certificate if completed
add_action( 'wp_ajax_wpedu_mark_lesson_complete', 'wpedu_ajax_mark_lesson_complete' );
function wpedu_ajax_mark_lesson_complete() {
    if ( ! is_user_logged_in() ) wp_send_json_error( 'تحتاج تسجيل الدخول' );

    $user_id = get_current_user_id();
    $lesson_id = intval( $_POST['lesson_id'] ?? 0 );
    if ( ! $lesson_id ) wp_send_json_error( 'معرّف الدرس غير صالح' );

    $course_id = intval( get_post_meta( $lesson_id, '_course_id', true ) );
    if ( ! $course_id ) wp_send_json_error( 'الدرس غير مرتبط بدورة' );

    $progress = get_user_meta( $user_id, 'wpedu_course_progress', true );
    if ( ! is_array( $progress ) ) $progress = array();
    if ( ! isset( $progress[ $course_id ] ) || ! is_array( $progress[ $course_id ] ) ) {
        $progress[ $course_id ] = array();
    }

    if ( ! in_array( $lesson_id, $progress[ $course_id ], true ) ) {
        $progress[ $course_id ][] = $lesson_id;
    }

    update_user_meta( $user_id, 'wpedu_course_progress', $progress );

    $lessons = get_posts( array(
        'post_type' => 'wpedu_lesson',
        'meta_key' => '_course_id',
        'meta_value' => $course_id,
        'posts_per_page' => -1,
        'fields' => 'ids',
    ) );

    $all_lesson_ids = array_map( 'intval', $lessons );
    $completed_ids = array_map( 'intval', $progress[ $course_id ] );

    $course_completed = empty( array_diff( $all_lesson_ids, $completed_ids ) );

    $certificate_number = '';
    $certificate_issued = false;

    if ( $course_completed ) {
        if ( function_exists( 'wpedu_issue_certificate_for_user' ) ) {
            $cert_num = wpedu_issue_certificate_for_user( $user_id, $course_id );
            if ( $cert_num ) {
                $certificate_issued = true;
                $certificate_number = $cert_num;
            }
        } else {
            update_user_meta( $user_id, 'wpedu_cert_issued_' . $course_id, current_time( 'mysql' ) );
            $certificate_issued = true;
        }
    }

    wp_send_json_success( array(
        'course_completed' => $course_completed,
        'certificate_issued' => $certificate_issued,
        'certificate_number' => $certificate_number,
        'progress_count' => count( $progress[ $course_id ] ),
        'total_lessons' => count( $all_lesson_ids ),
    ) );
}