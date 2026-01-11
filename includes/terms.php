<?php
// wp-edu-lms/includes/terms.php
// إضافة: إنشاء صفحات الشروط/الخصوصية وإجبار قبول الشروط أثناء التسجيل
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1) إنشاء الصفحات (إن لم تكن موجودة) وتخزينها في options
 *    keys: wpedu_terms_page_id, wpedu_privacy_page_id
 */
add_action( 'init', 'wpedu_terms_ensure_pages', 5 );
function wpedu_terms_ensure_pages() {
    // لا ننشئ الصفحات في WP-CLI أو أثناء عمليات معينة إن أردت التمديد
    if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) return;

    // شروط الاستخدام
    if ( ! get_option( 'wpedu_terms_page_id' ) ) {
        $slug = 'terms-of-use';
        $existing = get_page_by_path( $slug );
        if ( $existing ) {
            update_option( 'wpedu_terms_page_id', $existing->ID );
        } else {
            $content = wpedu_default_terms_content();
            $postarr = array(
                'post_title'   => 'شروط الاستخدام',
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            );
            $id = wp_insert_post( $postarr );
            if ( $id && ! is_wp_error( $id ) ) update_option( 'wpedu_terms_page_id', $id );
        }
    }

    // سياسة الخصوصية
    if ( ! get_option( 'wpedu_privacy_page_id' ) ) {
        $slug = 'privacy-policy';
        $existing = get_page_by_path( $slug );
        if ( $existing ) {
            update_option( 'wpedu_privacy_page_id', $existing->ID );
        } else {
            $content = wpedu_default_privacy_content();
            $postarr = array(
                'post_title'   => 'سياسة الخصوصية',
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            );
            $id = wp_insert_post( $postarr );
            if ( $id && ! is_wp_error( $id ) ) update_option( 'wpedu_privacy_page_id', $id );
        }
    }
}

/**
 * 2) المحتوى الافتراضي للصفحات (قابل للتعديل لاحقًا من خلال محرر الصفحات)
 */
function wpedu_default_terms_content() {
    return <<<HTML
<p>مرحبًا بكم في موقعنا. يرجى قراءة شروط الاستخدام التالية بعناية قبل التسجيل أو استخدام الموقع.</p>
<h3>1. القبول</h3>
<p>باستخدامك هذا الموقع فإنك توافق على الالتزام بهذه الشروط. إذا كنت لا توافق، فيرجى عدم التسجيل أو استخدام الخدمات.</p>
<h3>2. المحتوى</h3>
<p>المحتوى التعليمي مملوك للموقع أو للمدرّسين؛ يمنع إعادة النشر التجاري دون إذن صريح.</p>
<h3>3. المسؤولية</h3>
<p>نحن نبذل جهدنا لضمان دقة المواد، ولكن الموقع لا يتحمل مسؤولية الأضرار الناتجة عن الاعتماد الكامل على المحتوى.</p>
<h3>4. تعديلات</h3>
<p>نحتفظ بالحق في تعديل الشروط أو إيقاف الخدمة في أي وقت.</p>
HTML;
}

function wpedu_default_privacy_content() {
    return <<<HTML
<p>نحترم خصوصيتك. فيما يلي أهم النقاط حول جمع واستخدام البيانات.</p>
<h3>1. المعلومات التي نجمعها</h3>
<p>نقوم بجمع اسمك، بريدك الإلكتروني، وتفاصيل التسجيل. كذلك نخزن تقدمك في الدورات والمحتوى الذي تُقدّمه.</p>
<h3>2. كيفية الاستخدام</h3>
<p>نستخدم البيانات لتقديم الخدمات، التواصل معك، وتحسين التجربة التعليمية.</p>
<h3>3. مشاركة البيانات</h3>
<p>لن نشارك بياناتك مع أطراف ثالثة إلا إذا تطلّب الأمر قانونياً أو لتقديم خدمات تعتمد على مزودين (مثل بوابات دفع خارجية).</p>
<h3>4. حقوقك</h3>
<p>بإمكانك طلب حذف حسابك أو بياناتك وفق سياساتنا ولاستفسارات الخصوصية تواصل معنا.</p>
HTML;
}

/**
 * 3) مساعدة لإخراج روابط الصفحات
 */
function wpedu_get_terms_page_link( $type = 'terms' ) {
    if ( $type === 'privacy' ) {
        $id = get_option( 'wpedu_privacy_page_id' );
    } else {
        $id = get_option( 'wpedu_terms_page_id' );
    }
    if ( $id ) return get_permalink( $id );
    return site_url( '/' . ( $type === 'privacy' ? 'privacy-policy' : 'terms-of-use' ) . '/' );
}

/**
 * 4) إضافة الحقل في نموذج التسجيل الافتراضي (wp-login.php)
 *    - عرض checkbox مع رابط الشروط/الخصوصية
 *    - تحقق في registration_errors
 *    - حفظ بوساطة user_register
 */
add_action( 'register_form', 'wpedu_terms_register_form' );
function wpedu_terms_register_form() {
    $terms_link = esc_url( wpedu_get_terms_page_link( 'terms' ) );
    $privacy_link = esc_url( wpedu_get_terms_page_link( 'privacy' ) );
    $checked = isset( $_POST['wpedu_accept_terms'] ) ? 'checked' : '';
    ?>
    <p>
        <label>
            <input name="wpedu_accept_terms" type="checkbox" value="1" <?php echo $checked; ?> />
            أوافق على <a href="<?php echo $terms_link; ?>" target="_blank" rel="noopener">شروط الاستخدام</a> و
            <a href="<?php echo $privacy_link; ?>" target="_blank" rel="noopener">سياسة الخصوصية</a>.
        </label>
    </p>
    <?php
}

add_filter( 'registration_errors', 'wpedu_terms_registration_errors', 10, 3 );
function wpedu_terms_registration_errors( $errors, $sanitized_user_login, $user_email ) {
    if ( empty( $_POST['wpedu_accept_terms'] ) ) {
        $errors->add( 'wpedu_terms_required', __( '<strong>خطأ:</strong> يجب الموافقة على الشروط وسياسة الخصوصية أولاً.' ) );
    }
    return $errors;
}

add_action( 'user_register', 'wpedu_terms_user_register' );
function wpedu_terms_user_register( $user_id ) {
    if ( ! empty( $_POST['wpedu_accept_terms'] ) ) {
        update_user_meta( $user_id, 'wpedu_terms_accepted', 1 );
        update_user_meta( $user_id, 'wpedu_terms_accepted_at', current_time( 'mysql' ) );
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
        update_user_meta( $user_id, 'wpedu_terms_accepted_ip', $ip );
    } else {
        // لن يحدث عادة لأننا نمنع التسجيل بدون قبول، لكن كـ fallback
        update_user_meta( $user_id, 'wpedu_terms_accepted', 0 );
    }
}

/**
 * 5) شورتكود نموذج التسجيل الأمامي: [wpedu_register_form]
 *    - يعرض form بسيط (username, email, password) مع حقل قبول الشروط
 *    - عند الإرسال يحاول إنشاء المستخدم ويعرض رسائل بسيطة
 */
add_shortcode( 'wpedu_register_form', 'wpedu_register_form_shortcode' );
function wpedu_register_form_shortcode( $atts = array() ) {
    if ( is_user_logged_in() ) {
        return '<p>أنت مسجل دخول بالفعل.</p>';
    }

    $out = '';
    $errors = array();
    $success = '';

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['wpedu_register_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['wpedu_register_nonce'], 'wpedu_register_action' ) ) {
            $errors[] = 'فشل التحقق الأمني.';
        } else {
            $user_login = sanitize_user( $_POST['user_login'] ?? '' );
            $user_email = sanitize_email( $_POST['user_email'] ?? '' );
            $user_pass  = $_POST['user_pass'] ?? '';

            if ( empty( $user_login ) ) $errors[] = 'اسم المستخدم مطلوب.';
            if ( empty( $user_email ) || ! is_email( $user_email ) ) $errors[] = 'البريد الإلكتروني غير صالح.';
            if ( empty( $user_pass ) || strlen( $user_pass ) < 6 ) $errors[] = 'كلمة المرور مطلوبة وطولها لا يقل عن 6 أحرف.';
            if ( empty( $_POST['wpedu_accept_terms'] ) ) $errors[] = 'يجب الموافقة على الشروط وسياسة الخصوصية.';

            if ( empty( $errors ) ) {
                // تحقق من وجود المستخدم أو البريد
                if ( username_exists( $user_login ) ) $errors[] = 'اسم المستخدم مستخدم بالفعل.';
                if ( email_exists( $user_email ) ) $errors[] = 'البريد الإلكتروني مسجل بالفعل.';
            }

            if ( empty( $errors ) ) {
                $user_id = wp_create_user( $user_login, $user_pass, $user_email );
                if ( is_wp_error( $user_id ) ) {
                    $errors[] = $user_id->get_error_message();
                } else {
                    // حفظ موافقة الشروط
                    update_user_meta( $user_id, 'wpedu_terms_accepted', 1 );
                    update_user_meta( $user_id, 'wpedu_terms_accepted_at', current_time( 'mysql' ) );
                    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
                    update_user_meta( $user_id, 'wpedu_terms_accepted_ip', $ip );

                    // تسجيل الدخول تلقائياً (اختياري)
                    wp_set_current_user( $user_id );
                    wp_set_auth_cookie( $user_id );

                    $success = 'تم إنشاء الحساب بنجاح. تم قبول الشروط.';
                }
            }
        }
    }

    if ( ! empty( $errors ) ) {
        $out .= '<div class="wpedu-errors"><ul>';
        foreach ( $errors as $e ) $out .= '<li>' . esc_html( $e ) . '</li>';
        $out .= '</ul></div>';
    }

    if ( $success ) {
        $out .= '<div class="wpedu-success">' . esc_html( $success ) . '</div>';
        return $out;
    }

    // عرض النموذج
    $terms_link = esc_url( wpedu_get_terms_page_link( 'terms' ) );
    $privacy_link = esc_url( wpedu_get_terms_page_link( 'privacy' ) );

    $out .= '<form method="post" class="wpedu-register-form">';
    $out .= wp_nonce_field( 'wpedu_register_action', 'wpedu_register_nonce', true, false );
    $out .= '<p><label>اسم المستخدم<br><input type="text" name="user_login" required></label></p>';
    $out .= '<p><label>البريد الإلكتروني<br><input type="email" name="user_email" required></label></p>';
    $out .= '<p><label>كلمة المرور<br><input type="password" name="user_pass" required></label></p>';
    $out .= '<p><label><input type="checkbox" name="wpedu_accept_terms" value="1"> أوافق على <a href="' . $terms_link . '" target="_blank">شروط الاستخدام</a> و <a href="' . $privacy_link . '" target="_blank">سياسة الخصوصية</a></label></p>';
    $out .= '<p><button type="submit" class="wpedu-btn">تسجيل</button></p>';
    $out .= '</form>';

    return $out;
}

/**
 * 6) دالة مساعدة لعرض حالة قبول الشروط في صفحة الملف الشخصي أو التقرير
 */
function wpedu_user_terms_status_html( $user_id ) {
    $accepted = get_user_meta( $user_id, 'wpedu_terms_accepted', true );
    if ( $accepted ) {
        $at = get_user_meta( $user_id, 'wpedu_terms_accepted_at', true );
        $ip = get_user_meta( $user_id, 'wpedu_terms_accepted_ip', true );
        return sprintf( '<div class="wpedu-terms-status">الموافقة: نعم — التاريخ: %s — IP: %s</div>', esc_html( $at ), esc_html( $ip ) );
    }
    return '<div class="wpedu-terms-status">الموافقة: لا</div>';
}