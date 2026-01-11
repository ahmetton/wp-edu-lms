<?php
// wp-edu-lms/includes/certificate.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * نظام الشهادات: توليد رقم فريد، توقيع HMAC، تخزين سجل شهادة، توليد PDF/HTML، صفحة تحقق.
 * تم تعديل تحميل المفتاح السري ليتأخر إلى حدث init لتجنُّب استدعاء دوال ووردبريس قبل تهيئتها.
 */

/* ---- Random bytes fallback (works across PHP versions) ---- */
if ( ! function_exists( 'wpedu_random_bytes' ) ) {
    function wpedu_random_bytes( $length ) {
        if ( function_exists( 'random_bytes' ) ) {
            try {
                return random_bytes( $length );
            } catch ( Exception $e ) {
                // fallback
            }
        }
        if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes( $length, $strong );
            if ( $bytes !== false ) return $bytes;
        }
        $res = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $res .= chr( mt_rand( 0, 255 ) );
        }
        return $res;
    }
}

/* ---- Ensure secret key exists but only after WP is initialized ----
   (prevents calling wp_generate_password() at plugin include time) */
if ( ! function_exists( 'wpedu_ensure_cert_secret_key' ) ) {
    function wpedu_ensure_cert_secret_key() {
        if ( ! get_option( 'wpedu_cert_secret_key' ) ) {
            // Prefer WP helper if available
            if ( function_exists( 'wp_generate_password' ) ) {
                $key = wp_generate_password( 32, true, true );
            } else {
                // fallback: use secure random bytes hex
                $key = bin2hex( wpedu_random_bytes( 16 ) );
            }
            update_option( 'wpedu_cert_secret_key', $key );
        }
    }
}
add_action( 'init', 'wpedu_ensure_cert_secret_key', 5 );

/* ---- Register certificate CPT ---- */
if ( ! function_exists( 'wpedu_register_certificate_cpt' ) ) {
    function wpedu_register_certificate_cpt() {
        register_post_type( 'wpedu_certificate', array(
            'labels' => array( 'name' => 'الشهادات', 'singular_name' => 'شهادة' ),
            'public' => true,
            'has_archive' => false,
            'show_in_menu' => true,
            'supports' => array( 'title' ),
        ) );
    }
}
add_action( 'init', 'wpedu_register_certificate_cpt', 11 );

/* ---- Certificate helpers ---- */
if ( ! function_exists( 'wpedu_generate_certificate_number' ) ) {
    function wpedu_generate_certificate_number( $user_id, $course_id ) {
        $year = date( 'Y' );
        $rand_hex = strtoupper( substr( bin2hex( wpedu_random_bytes( 4 ) ), 0, 8 ) );
        $number = sprintf( 'EDU-%s-%s-%s', $year, $course_id, $rand_hex );

        // ensure uniqueness
        $exists = get_posts( array(
            'post_type' => 'wpedu_certificate',
            'meta_query' => array( array( 'key' => '_cert_number', 'value' => $number, 'compare' => '=' ) ),
            'fields' => 'ids',
            'posts_per_page' => 1,
        ) );
        if ( $exists ) {
            return wpedu_generate_certificate_number( $user_id, $course_id );
        }
        return $number;
    }
}

if ( ! function_exists( 'wpedu_generate_certificate_signature' ) ) {
    function wpedu_generate_certificate_signature( $cert_number, $user_id, $course_id, $issued_at ) {
        $secret = get_option( 'wpedu_cert_secret_key' );
        $data = $cert_number . '|' . intval( $user_id ) . '|' . intval( $course_id ) . '|' . $issued_at;
        return hash_hmac( 'sha256', $data, $secret );
    }
}

if ( ! function_exists( 'wpedu_issue_certificate_for_user' ) ) {
    function wpedu_issue_certificate_for_user( $user_id, $course_id ) {
        $user_id = intval( $user_id );
        $course_id = intval( $course_id );
        if ( ! $user_id || ! $course_id ) return false;

        $existing = get_user_meta( $user_id, 'wpedu_cert_issued_' . $course_id, true );
        if ( $existing ) return $existing;

        $issued_at = current_time( 'mysql' );
        $cert_number = wpedu_generate_certificate_number( $user_id, $course_id );
        $signature = wpedu_generate_certificate_signature( $cert_number, $user_id, $course_id, $issued_at );

        $postarr = array(
            'post_title' => sprintf( 'شهادة %s - %s', get_the_title( $course_id ), get_userdata( $user_id )->display_name ),
            'post_type' => 'wpedu_certificate',
            'post_status' => 'publish',
            'post_author' => $user_id,
        );
        $cert_post_id = wp_insert_post( $postarr );
        if ( ! $cert_post_id || is_wp_error( $cert_post_id ) ) return false;

        update_post_meta( $cert_post_id, '_cert_number', $cert_number );
        update_post_meta( $cert_post_id, '_cert_user_id', $user_id );
        update_post_meta( $cert_post_id, '_cert_course_id', $course_id );
        update_post_meta( $cert_post_id, '_cert_issued_at', $issued_at );
        update_post_meta( $cert_post_id, '_cert_signature', $signature );

        $verify_url = add_query_arg( array( 'wpedu_verify' => $cert_number ), site_url() );
        update_post_meta( $cert_post_id, '_cert_verify_url', esc_url_raw( $verify_url ) );

        update_user_meta( $user_id, 'wpedu_cert_issued_' . $course_id, $cert_number );

        return $cert_number;
    }
}

if ( ! function_exists( 'wpedu_get_certificate_by_number' ) ) {
    function wpedu_get_certificate_by_number( $cert_number ) {
        $certs = get_posts( array(
            'post_type' => 'wpedu_certificate',
            'meta_query' => array( array( 'key' => '_cert_number', 'value' => $cert_number, 'compare' => '=' ) ),
            'posts_per_page' => 1,
        ) );
        if ( ! $certs ) return false;
        return $certs[0];
    }
}

if ( ! function_exists( 'wpedu_verify_certificate_record' ) ) {
    function wpedu_verify_certificate_record( $cert_post ) {
        if ( ! $cert_post || $cert_post->post_type !== 'wpedu_certificate' ) return false;
        $cert_number = get_post_meta( $cert_post->ID, '_cert_number', true );
        $user_id = intval( get_post_meta( $cert_post->ID, '_cert_user_id', true ) );
        $course_id = intval( get_post_meta( $cert_post->ID, '_cert_course_id', true ) );
        $issued_at = get_post_meta( $cert_post->ID, '_cert_issued_at', true );
        $signature = get_post_meta( $cert_post->ID, '_cert_signature', true );

        if ( ! $cert_number || ! $user_id || ! $course_id || ! $issued_at || ! $signature ) return false;

        $expected = wpedu_generate_certificate_signature( $cert_number, $user_id, $course_id, $issued_at );
        return hash_equals( $expected, $signature );
    }
}

if ( ! function_exists( 'wpedu_get_certificate_html' ) ) {
    function wpedu_get_certificate_html( $user_id, $course_id, $cert_number = '', $issued_at = '' ) {
        $user = get_userdata( $user_id );
        $course = get_post( $course_id );
        $date = $issued_at ? date_i18n( get_option( 'date_format' ), strtotime( $issued_at ) ) : date_i18n( get_option( 'date_format' ) );
        $verify_url = $cert_number ? esc_url( add_query_arg( array( 'wpedu_verify' => $cert_number ), site_url() ) ) : '';

        $html = '<!doctype html><html lang="ar"><head><meta charset="utf-8"><title>شهادة إتمام</title>';
        $html .= '<style>body{font-family: "Noto Naskh Arabic", serif; direction:rtl; text-align:center; padding:40px;background:#fff;} .cert{border:8px solid #0a6ebd;padding:40px;max-width:900px;margin:0 auto;} h1{font-size:36px;} .meta{margin-top:20px;font-size:18px;} .cert-number{margin-top:18px;padding:10px;background:#f7f7f7;border-radius:6px;font-size:16px;}</style>';
        $html .= '</head><body><div class="cert">';
        $html .= '<h1>شهادة إتمام</h1>';
        $html .= '<p class="meta">تشهد هذه الوثيقة أن</p>';
        $html .= '<h2 style="margin:6px 0;">' . esc_html( $user->display_name ) . '</h2>';
        $html .= '<p class="meta">قد أكمل بنجاح دورة</p>';
        $html .= '<h3 style="margin:6px 0;">' . esc_html( $course->post_title ) . '</h3>';
        $html .= '<p class="meta">تاريخ الإصدار: ' . esc_html( $date ) . '</p>';
        if ( $cert_number ) {
            $html .= '<div class="cert-number">رقم الشهادة: <strong>' . esc_html( $cert_number ) . '</strong></div>';
            $html .= '<p class="meta">للتحقق إلكترونياً: <a href="' . $verify_url . '" target="_blank" rel="noopener noreferrer">' . $verify_url . '</a></p>';
        }
        $html .= '</div></body></html>';
        return $html;
    }
}

if ( ! function_exists( 'wpedu_generate_certificate_pdf_and_download' ) ) {
    function wpedu_generate_certificate_pdf_and_download( $user_id, $course_id ) {
        $cert_number = get_user_meta( $user_id, 'wpedu_cert_issued_' . intval( $course_id ), true );
        if ( ! $cert_number && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'لم تُصدر شهادة لهذه الدورة بعد.' );
        }

        $cert_post = wpedu_get_certificate_by_number( $cert_number );
        $issued_at = $cert_post ? get_post_meta( $cert_post->ID, '_cert_issued_at', true ) : current_time( 'mysql' );

        $html = wpedu_get_certificate_html( $user_id, $course_id, $cert_number, $issued_at );

        if ( class_exists( 'Imagick' ) && ! ini_get( 'safe_mode' ) ) {
            try {
                $im = new Imagick();
                $im->setBackgroundColor( new ImagickPixel( 'white' ) );
                $im->readImageBlob( $html );
                $im->setImageFormat( 'pdf' );
                $pdf = $im->getImageBlob();
                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: application/pdf' );
                header( 'Content-Disposition: attachment; filename=certificate_' . $course_id . '_' . $user_id . '.pdf' );
                header( 'Content-Length: ' . strlen( $pdf ) );
                echo $pdf;
                exit;
            } catch ( Exception $e ) {
                if ( function_exists( 'wpedu_logger_log' ) ) {
                    wpedu_logger_log( 'Imagick failed to render PDF: ' . $e->getMessage() );
                }
            }
        }

        header( 'Content-Type: text/html; charset=utf-8' );
        echo $html;
        exit;
    }
}

if ( ! function_exists( 'wpedu_render_certificate_verification_page' ) ) {
    function wpedu_render_certificate_verification_page( $cert_number ) {
        $cert_post = wpedu_get_certificate_by_number( $cert_number );
        if ( ! $cert_post ) {
            get_header();
            echo '<main class="site-container"><h1>التحقق من الشهادة</h1><p>لم يتم العثور على شهادة بهذا الرقم.</p></main>';
            get_footer();
            exit;
        }

        $is_valid = wpedu_verify_certificate_record( $cert_post );
        $user_id = intval( get_post_meta( $cert_post->ID, '_cert_user_id', true ) );
        $course_id = intval( get_post_meta( $cert_post->ID, '_cert_course_id', true ) );
        $issued_at = get_post_meta( $cert_post->ID, '_cert_issued_at', true );
        $user = get_userdata( $user_id );
        $course = get_post( $course_id );

        get_header();
        echo '<main class="site-container" style="padding:30px 0;">';
        echo '<h1>التحقق من الشهادة</h1>';
        echo '<div style="background:#fff;border:1px solid #eee;padding:18px;border-radius:8px;max-width:900px;">';
        echo '<p><strong>رقم الشهادة:</strong> ' . esc_html( $cert_number ) . '</p>';
        echo '<p><strong>المستلم:</strong> ' . esc_html( $user->display_name ) . '</p>';
        echo '<p><strong>الدورة:</strong> ' . esc_html( $course->post_title ) . '</p>';
        echo '<p><strong>تاريخ الإصدار:</strong> ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $issued_at ) ) ) . '</p>';
        echo '<p><strong>حالة التحقق:</strong> ';
        if ( $is_valid ) {
            echo '<span style="color:green;font-weight:700;">صحيحة ومعتمدة</span>';
        } else {
            echo '<span style="color:red;font-weight:700;">غير صالحة أو تم التلاعب بها</span>';
        }
        echo '</p>';
        echo '<p>للمزيد من المعلومات تواصل مع مسؤول الموقع.</p>';
        echo '</div>';
        echo '</main>';
        get_footer();
        exit;
    }
}