<?php
// wp-edu-lms/includes/auth.php
// نظام تسجيل دخول / تسجيل مستخدمين / خروج بسيط وآمن للواجهة (shortcodes + helpers)
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcode: [wpedu_register_form]
 * يعرض نموذج تسجيل (username, email, password) مع nonce والتحقق.
 * يعيد الاستخدام لمرة واحدة: عند النجاح يسجّل الدخول ويعيد التوجيه إلى الصفحة المحددة (redirect_to).
 */
if ( ! function_exists( 'wpedu_register_form_shortcode' ) ) {
    function wpedu_register_form_shortcode( $atts = array() ) {
        if ( is_user_logged_in() ) {
            return '<div class="wpedu-message">أنت مسجل دخول بالفعل.</div>';
        }

        $atts = shortcode_atts( array(
            'redirect_to' => '', // رابط إعادة التوجيه بعد التسجيل
        ), $atts, 'wpedu_register_form' );

        $redirect_to = esc_url_raw( $atts['redirect_to'] ?: ( isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : site_url() ) );

        $errors = array();
        $success = '';

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['wpedu_register_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['wpedu_register_nonce'], 'wpedu_register_action' ) ) {
                $errors[] = 'فشل التحقق الأمني.';
            } else {
                $user_login = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ), true );
                $user_email = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
                $user_pass  = $_POST['user_pass'] ?? '';

                if ( empty( $user_login ) ) $errors[] = 'اسم المستخدم مطلوب.';
                if ( empty( $user_email ) || ! is_email( $user_email ) ) $errors[] = 'البريد الإلكتروني غير صالح.';
                if ( empty( $user_pass ) || strlen( $user_pass ) < 6 ) $errors[] = 'كلمة المرور مطلوبة وطولها لا يقل عن 6 أحرف.';

                // إن كانت سياسة الشروط مفعلّة، تأكد من قبول المستخدم إن وُجد الحقل
                if ( function_exists( 'wpedu_get_terms_page_link' ) && empty( $_POST['wpedu_accept_terms'] ) ) {
                    $errors[] = 'يجب الموافقة على الشروط وسياسة الخصوصية.';
                }

                if ( username_exists( $user_login ) ) $errors[] = 'اسم المستخدم مستخدم بالفعل.';
                if ( email_exists( $user_email ) ) $errors[] = 'البريد الإلكتروني مسجل بالفعل.';

                if ( empty( $errors ) ) {
                    $user_id = wp_create_user( $user_login, $user_pass, $user_email );
                    if ( is_wp_error( $user_id ) ) {
                        $errors[] = $user_id->get_error_message();
                    } else {
                        // حفظ موافقة الشروط إن وُجدت الحقول
                        if ( ! empty( $_POST['wpedu_accept_terms'] ) ) {
                            update_user_meta( $user_id, 'wpedu_terms_accepted', 1 );
                            update_user_meta( $user_id, 'wpedu_terms_accepted_at', current_time( 'mysql' ) );
                            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
                            update_user_meta( $user_id, 'wpedu_terms_accepted_ip', $ip );
                        }

                        // تسجيل الدخول فورًا
                        wp_set_current_user( $user_id );
                        wp_set_auth_cookie( $user_id );

                        // إعادة التوجيه
                        wp_safe_redirect( $redirect_to ?: home_url() );
                        exit;
                    }
                }
            }
        }

        ob_start();
        if ( ! empty( $errors ) ) {
            echo '<div class="wpedu-errors"><ul>';
            foreach ( $errors as $e ) echo '<li>' . esc_html( $e ) . '</li>';
            echo '</ul></div>';
        }
        ?>
        <form method="post" class="wpedu-register-form">
            <?php wp_nonce_field( 'wpedu_register_action', 'wpedu_register_nonce' ); ?>
            <p>
                <label>اسم المستخدم<br>
                    <input type="text" name="user_login" value="<?php echo isset( $_POST['user_login'] ) ? esc_attr( wp_unslash( $_POST['user_login'] ) ) : ''; ?>" required>
                </label>
            </p>
            <p>
                <label>البريد الإلكتروني<br>
                    <input type="email" name="user_email" value="<?php echo isset( $_POST['user_email'] ) ? esc_attr( wp_unslash( $_POST['user_email'] ) ) : ''; ?>" required>
                </label>
            </p>
            <p>
                <label>كلمة المرور<br>
                    <input type="password" name="user_pass" required>
                </label>
            </p>

            <?php
            // إن كانت صفحات الشروط موجودة أضف خانة قبول
            if ( function_exists( 'wpedu_get_terms_page_link' ) ) {
                $terms = esc_url( wpedu_get_terms_page_link( 'terms' ) );
                $privacy = esc_url( wpedu_get_terms_page_link( 'privacy' ) );
                $checked = isset( $_POST['wpedu_accept_terms'] ) ? 'checked' : '';
                echo '<p><label><input type="checkbox" name="wpedu_accept_terms" value="1" ' . $checked . '> أوافق على <a target="_blank" href="' . $terms . '">شروط الاستخدام</a> و <a target="_blank" href="' . $privacy . '">سياسة الخصوصية</a></label></p>';
            }
            ?>

            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
            <p><button type="submit" class="wpedu-btn">تسجيل</button></p>
        </form>
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'wpedu_register_form', 'wpedu_register_form_shortcode' );
}

/**
 * Shortcode: [wpedu_login_form redirect_to="/"]
 * نموذج تسجيل دخول بسيط (username/email + password + remember)
 * عند نجاح تسجيل الدخول يعيد التوجيه إلى redirect_to إن وُجد أو الصفحة الحالية.
 */
if ( ! function_exists( 'wpedu_login_form_shortcode' ) ) {
    function wpedu_login_form_shortcode( $atts = array() ) {
        if ( is_user_logged_in() ) {
            return '<div class="wpedu-message">أنت مسجل دخول بالفعل.</div>';
        }

        $atts = shortcode_atts( array(
            'redirect_to' => '',
        ), $atts, 'wpedu_login_form' );

        $redirect_to = esc_url_raw( $atts['redirect_to'] ?: ( isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : home_url() ) );

        $errors = array();

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['wpedu_login_nonce'] ) ) {
            if ( ! wp_verify_nonce( $_POST['wpedu_login_nonce'], 'wpedu_login_action' ) ) {
                $errors[] = 'فشل التحقق الأمني.';
            } else {
                $user_login = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ), true );
                $user_pass = $_POST['user_pass'] ?? '';
                $remember = ! empty( $_POST['rememberme'] ) ? true : false;

                if ( empty( $user_login ) ) $errors[] = 'اسم المستخدم أو البريد الإلكتروني مطلوب.';
                if ( empty( $user_pass ) ) $errors[] = 'كلمة المرور مطلوبة.';

                if ( empty( $errors ) ) {
                    // حاول تسجيل الدخول عن طريق wp_signon
                    $creds = array(
                        'user_login'    => $user_login,
                        'user_password' => $user_pass,
                        'remember'      => $remember,
                    );
                    // إذا أُدخل بريد إلكتروني فحوّله إلى اسم المستخدم لاحقًا
                    if ( is_email( $user_login ) ) {
                        $user = get_user_by( 'email', $user_login );
                        if ( $user ) $creds['user_login'] = $user->user_login;
                    }

                    $user_signon = wp_signon( $creds, is_ssl() );
                    if ( is_wp_error( $user_signon ) ) {
                        $errors[] = $user_signon->get_error_message();
                    } else {
                        wp_set_current_user( $user_signon->ID );
                        wp_set_auth_cookie( $user_signon->ID, $remember );
                        wp_safe_redirect( $redirect_to ?: home_url() );
                        exit;
                    }
                }
            }
        }

        ob_start();
        if ( ! empty( $errors ) ) {
            echo '<div class="wpedu-errors"><ul>';
            foreach ( $errors as $e ) echo '<li>' . esc_html( $e ) . '</li>';
            echo '</ul></div>';
        }
        ?>
        <form method="post" class="wpedu-login-form">
            <?php wp_nonce_field( 'wpedu_login_action', 'wpedu_login_nonce' ); ?>
            <p>
                <label>اسم المستخدم أو البريد الإلكتروني<br>
                    <input type="text" name="user_login" value="<?php echo isset( $_POST['user_login'] ) ? esc_attr( wp_unslash( $_POST['user_login'] ) ) : ''; ?>" required>
                </label>
            </p>
            <p>
                <label>كلمة المرور<br>
                    <input type="password" name="user_pass" required>
                </label>
            </p>
            <p>
                <label><input type="checkbox" name="rememberme" value="1"> تذكرني</label>
            </p>
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
            <p><button type="submit" class="wpedu-btn">دخول</button></p>
        </form>
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'wpedu_login_form', 'wpedu_login_form_shortcode' );
}

/**
 * Shortcode: [wpedu_logout_link text="خروج" redirect="/"]
 * يُرجع رابط الخروج (wp_logout_url) مع زر/رابط.
 */
if ( ! function_exists( 'wpedu_logout_link_shortcode' ) ) {
    function wpedu_logout_link_shortcode( $atts = array() ) {
        $atts = shortcode_atts( array(
            'text' => 'تسجيل الخروج',
            'redirect' => home_url(),
            'class' => 'wpedu-logout-link',
        ), $atts, 'wpedu_logout_link' );

        if ( ! is_user_logged_in() ) {
            return '';
        }

        $logout_url = wp_logout_url( esc_url_raw( $atts['redirect'] ) );
        return '<a class="' . esc_attr( $atts['class'] ) . '" href="' . esc_url( $logout_url ) . '">' . esc_html( $atts['text'] ) . '</a>';
    }
    add_shortcode( 'wpedu_logout_link', 'wpedu_logout_link_shortcode' );
}

/**
 * Helper functions: عرض روابط الدخول/التسجيل/الخروج
 */
if ( ! function_exists( 'wpedu_auth_links' ) ) {
    function wpedu_auth_links( $args = array() ) {
        $defaults = array(
            'login_text' => 'تسجيل دخول',
            'register_text' => 'تسجيل',
            'logout_text' => 'خروج',
            'login_url' => wp_login_url(),
            'register_url' => wp_registration_url(),
            'logout_redirect' => home_url(),
        );
        $r = wp_parse_args( $args, $defaults );
        if ( is_user_logged_in() ) {
            echo '<a href="' . esc_url( wp_logout_url( $r['logout_redirect'] ) ) . '">' . esc_html( $r['logout_text'] ) . '</a>';
        } else {
            echo '<a href="' . esc_url( $r['login_url'] ) . '">' . esc_html( $r['login_text'] ) . '</a> | ';
            echo '<a href="' . esc_url( $r['register_url'] ) . '">' . esc_html( $r['register_text'] ) . '</a>';
        }
    }
}

/**
 * Optional: Redirect unauthenticated users from specific pages (hookable)
 * الاستخدام: add_action('template_redirect','wpedu_require_login_for_course_page');
 */
if ( ! function_exists( 'wpedu_require_login_for_course_page' ) ) {
    function wpedu_require_login_for_course_page() {
        if ( is_singular( 'wpedu_course' ) || is_singular( 'wpedu_lesson' ) ) {
            if ( ! is_user_logged_in() ) {
                auth_redirect(); // يعيد إلى صفحة الدخول
            }
        }
    }
    // لم نفعل hook افتراضياً — تفعيله إن رغبت:
    // add_action( 'template_redirect', 'wpedu_require_login_for_course_page' );
}