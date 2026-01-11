<?php
// wp-edu-lms/includes/post-types.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * تسجيل أنواع المنشورات: course, lesson, quiz, question, submission
 * meta boxes: دورة - درس
 * حماية نشر درس بدون تحديد دورة
 */

// Register CPTs
add_action( 'init', 'wpedu_register_post_types', 0 );
function wpedu_register_post_types() {
    register_post_type( 'wpedu_course', array(
        'labels' => array( 'name' => 'الدورات', 'singular_name' => 'دورة' ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array( 'slug' => 'courses' ),
        'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'excerpt' ),
    ) );

    register_post_type( 'wpedu_lesson', array(
        'labels' => array( 'name' => 'الدروس', 'singular_name' => 'درس' ),
        'public' => true,
        'has_archive' => false,
        'rewrite' => array( 'slug' => 'lessons' ),
        'supports' => array( 'title', 'editor', 'author', 'page-attributes' ),
        'show_in_menu' => true,
    ) );

    register_post_type( 'wpedu_quiz', array(
        'labels' => array( 'name' => 'الاختبارات', 'singular_name' => 'اختبار' ),
        'public' => false,
        'show_ui' => true,
        'supports' => array( 'title' ),
    ) );

    register_post_type( 'wpedu_question', array(
        'labels' => array( 'name' => 'الأسئلة', 'singular_name' => 'سؤال' ),
        'public' => false,
        'show_ui' => true,
        'supports' => array( 'title', 'editor' ),
    ) );

    register_post_type( 'wpedu_submission', array(
        'labels' => array( 'name' => 'التسليمات', 'singular_name' => 'تسليم' ),
        'public' => false,
        'show_ui' => true,
        'supports' => array( 'title', 'editor', 'author' ),
    ) );
}

// Add meta boxes
add_action( 'add_meta_boxes', function() {
    add_meta_box( 'wpedu_course_meta', 'بيانات الدورة', 'wpedu_course_meta_box', 'wpedu_course', 'normal', 'high' );
    add_meta_box( 'wpedu_lesson_meta', 'بيانات الدرس', 'wpedu_lesson_meta_box', 'wpedu_lesson', 'side', 'default' );
} );

function wpedu_course_meta_box( $post ) {
    wp_nonce_field( 'wpedu_course_meta', 'wpedu_course_meta_nonce' );
    $price = get_post_meta( $post->ID, '_wpedu_price', true );
    echo '<label>سعر الدورة (اتركه فارغاً لاعتباره مجاني):</label><br>';
    echo '<input type="text" name="wpedu_price" value="' . esc_attr( $price ) . '" style="width:100%">';
    echo '<p>ملاحظة: خيارات المؤتمر المباشر تُحدَّد على مستوى الدرس فقط.</p>';
}

function wpedu_course_dropdown( $name = 'lesson_course_id', $selected = 0 ) {
    $args = array(
        'post_type' => 'wpedu_course',
        'post_status' => array( 'publish', 'draft' ),
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    );
    $courses = get_posts( $args );
    echo '<select name="' . esc_attr( $name ) . '" style="width:100%;">';
    echo '<option value="0">-- اختر دورة --</option>';
    foreach ( $courses as $c ) {
        $sel = ( intval( $selected ) === intval( $c->ID ) ) ? ' selected' : '';
        echo '<option value="' . intval( $c->ID ) . '"' . $sel . '>' . esc_html( $c->post_title ) . '</option>';
    }
    echo '</select>';
}

function wpedu_lesson_meta_box( $post ) {
    wp_nonce_field( 'wpedu_lesson_meta', 'wpedu_lesson_meta_nonce' );

    $course_id = get_post_meta( $post->ID, '_course_id', true );
    if ( empty( $course_id ) && isset( $_GET['lesson_course_id'] ) ) {
        $course_id = intval( $_GET['lesson_course_id'] );
    }

    echo '<label>تابع إلى الدورة:</label><br>';
    wpedu_course_dropdown( 'lesson_course_id', $course_id );

    echo '<p>ترتيب الدرس (menu order)</p>';

    $jitsi_room = get_post_meta( $post->ID, '_wpedu_jitsi_room', true );
    echo '<p>اسم غرفة الاجتماع (Jitsi) الخاصة بهذا الدرس:</p>';
    echo '<input type="text" name="wpedu_jitsi_room" value="' . esc_attr( $jitsi_room ) . '" style="width:100%">';
    echo '<p class="description">اتركه فارغاً إن لم يكن هناك جلسة مباشرة لهذا الدرس.</p>';
}

// Save metadata
add_action( 'save_post', function( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    $type = get_post_type( $post_id );

    if ( $type === 'wpedu_course' ) {
        if ( isset( $_POST['wpedu_course_meta_nonce'] ) && wp_verify_nonce( $_POST['wpedu_course_meta_nonce'], 'wpedu_course_meta' ) ) {
            if ( isset( $_POST['wpedu_price'] ) ) update_post_meta( $post_id, '_wpedu_price', sanitize_text_field( $_POST['wpedu_price'] ) );
        }
    }

    if ( $type === 'wpedu_lesson' ) {
        if ( isset( $_POST['wpedu_lesson_meta_nonce'] ) && wp_verify_nonce( $_POST['wpedu_lesson_meta_nonce'], 'wpedu_lesson_meta' ) ) {
            if ( isset( $_POST['lesson_course_id'] ) ) {
                $course_val = intval( $_POST['lesson_course_id'] );
                update_post_meta( $post_id, '_course_id', $course_val );
            }
            if ( isset( $_POST['wpedu_jitsi_room'] ) ) {
                update_post_meta( $post_id, '_wpedu_jitsi_room', sanitize_text_field( $_POST['wpedu_jitsi_room'] ) );
            }
        }

        // prevent publish without course server-side backup (if user bypass JS)
        $course_selected = isset( $_POST['lesson_course_id'] ) ? intval( $_POST['lesson_course_id'] ) : get_post_meta( $post_id, '_course_id', true );
        if ( ! $course_selected ) {
            $post_status = get_post_status( $post_id );
            if ( $post_status === 'publish' ) {
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
                // log
                if ( function_exists( 'wpedu_logger_log' ) ) {
                    wpedu_logger_log( "Lesson $post_id published without course - reverted to draft" );
                }
            }
        }
    }
} );

// Prevent publish via server filter as a backup
add_filter( 'wp_insert_post_data', function( $data, $postarr ) {
    if ( isset( $data['post_type'] ) && $data['post_type'] === 'wpedu_lesson' && isset( $data['post_status'] ) && $data['post_status'] === 'publish' ) {
        $has_course = false;
        if ( ! empty( $_POST['lesson_course_id'] ) ) {
            $has_course = intval( $_POST['lesson_course_id'] ) > 0;
        } elseif ( ! empty( $postarr['ID'] ) ) {
            $existing_course = get_post_meta( intval( $postarr['ID'] ), '_course_id', true );
            if ( $existing_course ) $has_course = true;
        }
        if ( ! $has_course ) {
            // revert to draft
            $data['post_status'] = 'draft';
            if ( function_exists( 'wpedu_logger_log' ) ) {
                wpedu_logger_log( 'Blocked publishing lesson without course (post data changed to draft).' );
            }
        }
    }
    return $data;
}, 10, 2 );

// Admin improvements: show course column
add_filter( 'manage_wpedu_lesson_posts_columns', function( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'title' ) {
            $new['wpedu_course'] = 'الدورة';
        }
    }
    return $new;
} );

add_action( 'manage_wpedu_lesson_posts_custom_column', function( $column, $post_id ) {
    if ( $column === 'wpedu_course' ) {
        $course_id = get_post_meta( $post_id, '_course_id', true );
        if ( $course_id ) {
            echo '<a href="' . esc_url( get_edit_post_link( $course_id ) ) . '">' . esc_html( get_the_title( $course_id ) ) . '</a>';
        } else {
            echo '<span style="color:#999;">غير محدد</span>';
        }
    }
}, 10, 2 );

// Admin enqueue: simple script to block publish if no course selected (covers Classic + basic Gutenberg clicks)
add_action( 'admin_enqueue_scripts', function( $hook ) {
    $screen = get_current_screen();
    if ( ! $screen ) return;
    if ( $screen->post_type === 'wpedu_lesson' ) {
        $inline = <<<JS
(function(){
    function hasCourseSelected() {
        var sel = document.querySelector('select[name="lesson_course_id"]');
        if (!sel) return false;
        return sel.value && parseInt(sel.value,10) > 0;
    }
    var postForm = document.getElementById('post');
    if (postForm) {
        postForm.addEventListener('submit', function(e){
            if (!hasCourseSelected()) {
                e.preventDefault();
                alert('يجب تحديد دورة لهذا الدرس قبل النشر.');
                return false;
            }
        }, true);
    }
    document.addEventListener('click', function(ev){
        var btn = ev.target.closest && (ev.target.closest('.editor-post-publish-button') || ev.target.closest('.editor-post-publish-panel__toggle'));
        if (btn) {
            if (!hasCourseSelected()) {
                ev.preventDefault();
                ev.stopPropagation();
                alert('يجب تحديد دورة لهذا الدرس قبل النشر.');
                return false;
            }
        }
    }, true);
})();
JS;
        wp_register_script( 'wpedu-lesson-admin-inline', false );
        wp_add_inline_script( 'wpedu-lesson-admin-inline', $inline );
        wp_enqueue_script( 'wpedu-lesson-admin-inline' );
    }
} );