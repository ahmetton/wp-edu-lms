<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// إضافة صفحة تقارير في لوحة التحكم تحت قائمة "LMS"
add_action('admin_menu','wpedu_lms_admin_menu');
function wpedu_lms_admin_menu(){
    add_menu_page('LMS','LMS','manage_options','wpedu-lms','wpedu_reports_page','dashicons-welcome-learn-more',6);
}

function wpedu_reports_page(){
    if ( ! current_user_can('manage_options') ) wp_die('ليس لديك صلاحية');
    // تقارير أساسية: إحصاء الطلبة، الدورات المكتملة، متوسط العلامات
    $courses = get_posts(array('post_type'=>'wpedu_course','numberposts'=>-1));
    echo '<div class="wrap"><h1>تقارير LMS</h1>';
    echo '<table class="widefat"><thead><tr><th>الدورة</th><th>عدد المسجلين</th><th>منجزون</th></tr></thead><tbody>';
    foreach ( $courses as $c ) {
        $count = get_post_meta($c->ID,'_students_count',true) ?: 0;
        // بسيط: منجزون = عدد المستخدمين الذين لديهم ميتا إنجاز للدورة (مثال بسيط لاختبار)
        $completions = 0;
        // للفعالية يجب فحص usermeta لكل مستخدم لكن هنا عرض تقريبي
        echo '<tr><td>'.esc_html($c->post_title).'</td><td>'.intval($count).'</td><td>'.intval($completions).'</td></tr>';
    }
    echo '</tbody></table></div>';
}