<?php
// wp-edu-lms/includes/admin/settings.php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * إعداد بسيط لإدارة مفتاح توقيع الشهادات (wpedu_cert_secret_key)
 */

add_action( 'admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'WP EDU LMS Settings',
        'WP EDU LMS',
        'manage_options',
        'wpedu-lms-settings',
        'wpedu_lms_render_settings_page'
    );
} );

add_action( 'admin_init', function() {
    register_setting( 'wpedu_lms_settings_group', 'wpedu_cert_secret_key', array(
        'sanitize_callback' => 'sanitize_text_field',
    ) );
} );

function wpedu_lms_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1>إعدادات WP EDU LMS</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'wpedu_lms_settings_group' ); ?>
            <?php do_settings_sections( 'wpedu_lms_settings_group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">مفتاح توقيع الشهادات (HMAC)</th>
                    <td>
                        <input type="text" name="wpedu_cert_secret_key" value="<?php echo esc_attr( get_option( 'wpedu_cert_secret_key', '' ) ); ?>" style="width:420px;">
                        <p class="description">غيّر المفتاح فقط بحذر. تغييره سيجعل الشهادات القديمة غير قابلة للتحقق.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}