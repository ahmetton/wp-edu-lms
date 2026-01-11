<?php
// wp-edu-lms/includes/register-page.php
// Creates a /register/ page (if missing) and provides the [wpedu_register_form] shortcode.
// Usage: ensure this file is loaded by the plugin (wp-edu-lms/wp-edu-lms.php should require_once it).
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ensure a register page exists and store its ID in option 'wpedu_register_page_id'
 */
if ( ! function_exists( 'wpedu_ensure_register_page' ) ) {
    function wpedu_ensure_register_page() {
        // avoid running during installs/CLI
        if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
            return;
        }

        $opt = get_option( 'wpedu_register_page_id' );
        if ( $opt && get_post_status( $opt ) ) {
            return; // already exists and valid
        }

        // try find by slug
        $page = get_page_by_path( 'register' );
        if ( $page && $page->ID ) {
            update_option( 'wpedu_register_page_id', $page->ID );
            return;
        }

        // create page programmatically
        $postarr = array(
            'post_title'   => 'تسجيل',
            'post_name'    => 'register',
            'post_content' => '[wpedu_register_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        );

        $id = wp_insert_post( $postarr );
        if ( $id && ! is_wp_error( $id ) ) {
            update_option( 'wpedu_register_page_id', $id );
        }
    }
}
add_action( 'init', 'wpedu_ensure_register_page', 20 );

/**
 * Get register page URL (fallback to /register/)
 */
if ( ! function_exists( 'wpedu_get_register_page_url' ) ) {
    function wpedu_get_register_page_url() {
        $id = get_option( 'wpedu_register_page_id' );
        if ( $id && get_post_status( $id ) ) {
            return get_permalink( $id );
        }
        $p = get_page_by_path( 'register' );
        if ( $p && $p->ID ) {
            return get_permalink( $p->ID );
        }
        return site_url( '/register/' );
    }
}

/**
 * Filter register_url() to point to our register page
 */
if ( ! function_exists( 'wpedu_filter_register_url' ) ) {
    function wpedu_filter_register_url( $url ) {
        return wpedu_get_register_page_url();
    }
    add_filter( 'register_url', 'wpedu_filter_register_url', 10, 1 );
}

/**
 * Shortcode [wpedu_register_form]
 * Shows registration form and handles submission.
 * (يستخدم وظائف wp_create_user و wp_signon)
 */
if ( ! function_exists( 'wpedu_register_form_shortcode' ) ) {
    function wpedu_register_form_shortcode( $atts = array() ) {
        if ( is_user_logged_in() ) {
            return '<div class="wpedu-msg">أنت مسجّل الدخول بالفعل.</div>';
        }

        $atts = shortcode_atts( array(
            'redirect_to' => '', // optional attribute
        ), $atts, 'wpedu_register_form' );

        $redirect_to = ! empty( $atts['redirect_to'] ) ? esc_url_raw( $atts['redirect_to'] ) :
                       ( ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url() );

        $errors = array();
        $posted = $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['wpedu_register_nonce'] );

        if ( $posted ) {
            if ( ! wp_verify_nonce( $_POST['wpedu_register_nonce'], 'wpedu_register_action' ) ) {
                $errors[] = 'فشل التحقق الأمني.';
            } else {
                $user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
                $user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
                $user_pass  = isset( $_POST['user_pass'] ) ? $_POST['user_pass'] : '';

                if ( empty( $user_login ) ) $errors[] = 'اسم المستخدم مطلوب.';
                if ( empty( $user_email ) || ! is_email( $user_email ) ) $errors[] = 'البريد الإلكتروني غير صالح.';
                if ( empty( $user_pass ) || strlen( $user_pass ) < 6 ) $errors[] = 'كلمة المرور مطلوبة وطولها لا يقل عن 6 أحرف.';

                if ( function_exists( 'wpedu_get_terms_page_link' ) ) {
                    if ( empty( $_POST['wpedu_accept_terms'] ) ) {
                        $errors[] = 'يجب الموافقة على الشروط وسياسة الخصوصية.';
                    }
                }

                if ( username_exists( $user_login ) ) $errors[] = 'اسم المستخدم مستخدم بالفعل.';
                if ( email_exists( $user_email ) ) $errors[] = 'البريد الإلكتروني مسجل بالفعل.';

                if ( empty( $errors ) ) {
                    $user_id = wp_create_user( $user_login, $user_pass, $user_email );
                    if ( is_wp_error( $user_id ) ) {
                        $errors[] = $user_id->get_error_message();
                    } else {
                        if ( ! empty( $_POST['wpedu_accept_terms'] ) ) {
                            update_user_meta( $user_id, 'wpedu_terms_accepted', 1 );
                            update_user_meta( $user_id, 'wpedu_terms_accepted_at', current_time( 'mysql' ) );
                            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
                            update_user_meta( $user_id, 'wpedu_terms_accepted_ip', $ip );
                        }

                        // Attempt to sign on user
                        $creds = array(
                            'user_login'    => $user_login,
                            'user_password' => $user_pass,
                            'remember'      => false,
                        );
                        $user_signon = wp_signon( $creds, is_ssl() );
                        if ( ! is_wp_error( $user_signon ) ) {
                            wp_set_current_user( $user_signon->ID );
                            wp_set_auth_cookie( $user_signon->ID, false );
                            wp_safe_redirect( $redirect_to ?: home_url() );
                            exit;
                        } else {
                            $success_msg = 'تم إنشاء الحساب بنجاح. يمكنك الآن <a href="' . esc_url( wp_login_url() ) . '">تسجيل الدخول</a>.';
                            return '<div class="wpedu-success">' . $success_msg . '</div>';
                        }
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
        <form method="post" class="wpedu-register-form" style="max-width:480px;">
            <?php wp_nonce_field( 'wpedu_register_action', 'wpedu_register_nonce' ); ?>
            <p><label>اسم المستخدم<br><input type="text" name="user_login" value="<?php echo isset( $_POST['user_login'] ) ? esc_attr( wp_unslash( $_POST['user_login'] ) ) : ''; ?>" required style="width:100%"></label></p>
            <p><label>البريد الإلكتروني<br><input type="email" name="user_email" value="<?php echo isset( $_POST['user_email'] ) ? esc_attr( wp_unslash( $_POST['user_email'] ) ) : ''; ?>" required style="width:100%"></label></p>
            <p><label>كلمة المرور<br><input type="password" name="user_pass" required style="width:100%"></label></p>

            <?php if ( function_exists( 'wpedu_get_terms_page_link' ) ) : 
                $terms = esc_url( wpedu_get_terms_page_link( 'terms' ) );
                $privacy = esc_url( wpedu_get_terms_page_link( 'privacy' ) );
                $checked = isset( $_POST['wpedu_accept_terms'] ) ? 'checked' : '';
            ?>
                <p><label><input type="checkbox" name="wpedu_accept_terms" value="1" <?php echo $checked; ?>> أوافق على <a href="<?php echo $terms; ?>" target="_blank">شروط الاستخدام</a> و <a href="<?php echo $privacy; ?>" target="_blank">سياسة الخصوصية</a></label></p>
            <?php endif; ?>

            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
            <p><button type="submit" class="wpedu-btn">تسجيل</button></p>
        </form>
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'wpedu_register_form', 'wpedu_register_form_shortcode' );
}

/**
 * Optional helper to echo register link
 */
if ( ! function_exists( 'wpedu_register_link' ) ) {
    function wpedu_register_link( $text = 'تسجيل' ) {
        echo '<a href="' . esc_url( wpedu_get_register_page_url() ) . '">' . esc_html( $text ) . '</a>';
    }
}