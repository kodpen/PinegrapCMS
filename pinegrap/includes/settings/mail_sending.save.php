<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The save of Site Settings › E-mail sending. It writes config.php defines
 * and the sending service's account (pg_mail_settings_save()), never the
 * config row; a refusal marks its field and writes nothing. A card button
 * runs its job after a successful save, with the values just saved.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_settings.php');

$mail_saved = pg_mail_settings_save($_POST, $liveform);

if ($mail_saved !== false) {

    if (post_value('generate_dkim_key') === 'true') {
        pg_mail_settings_dkim_generate($mail_saved, $liveform);

    } elseif (post_value('test_dkim') === 'true') {
        pg_mail_settings_dkim_test($mail_saved, $liveform);

    } elseif (post_value('send_system_email') === 'Send System Test Email') {
        // This request's SMTP constants still hold the old settings, so the
        // pane read after the save sends the test (mail_sending.php).
        $_SESSION['smtp_settings_send_test'] = true;
    }
}
