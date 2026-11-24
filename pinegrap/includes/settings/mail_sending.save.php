<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The save of Site Settings › E-mail sending. It writes config.php defines
 * and the sending service's account (pg_mail_settings_save()), then the
 * config row's campaign texts and the way campaigns leave (config_text
 * campaign_mail_way); a refusal marks its field and writes nothing.
 * Mailchimp's settings are saved on its page of Connections. A card button runs its job
 * after a successful save, with the values just saved.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_sending_rows.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');

// ── How system e-mail and campaigns leave ──
// The ways sheets post the ways chosen; they decide the accounts, the
// sending service and the mail servers saved below (pg_mail_way_post(),
// then pg_mail_campaign_way_post(), which reads the service system e-mail
// is left with). A way that lacks its account or service is refused before
// anything is checked or written.
$mail_way_accounts = array();

foreach (pg_mail_sending_accounts() as $mail_way_account) {
    $mail_way_accounts[(int) $mail_way_account['id']] = (string) $mail_way_account['provider'];
}

if (isset($_POST['system_mail_way'])) {
    $mail_way = pg_mail_way_post($_POST, pg_mail_way_enabled_service(), $mail_way_accounts);

    if ($mail_way['mark']) {
        $liveform->mark_error($mail_way['mark'][0], h($mail_way['mark'][1]));

        return;
    }

    $_POST = $mail_way['post'];
}

$mail_campaign_way = pg_mail_campaign_way_post($_POST, pg_mail_campaign_way(), $mail_way_accounts);

if ($mail_campaign_way['mark']) {
    $liveform->mark_error($mail_campaign_way['mark'][0], h($mail_campaign_way['mark'][1]));

    return;
}

$_POST = $mail_campaign_way['post'];

if ($liveform->check_form_errors()) {
    return;
}

$mail_saved = pg_mail_settings_save($_POST, $liveform);

if ($mail_saved !== false) {

    // The campaign texts: config row columns, written once
    // config.php has taken the mail settings, so a refused mail setting
    // leaves them as they were too.
    db("UPDATE config
        SET
            opt_in_label = '" . escape(post_value('opt_in_label')) . "',
            plain_text_email_campaign_footer = '" . escape(trim(post_value('plain_text_email_campaign_footer'))) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

    if ($mail_campaign_way['store'] !== null) {
        pg_config_text_save('campaign_mail_way', $mail_campaign_way['store']);
    }

    if (post_value('generate_dkim_key') === 'true') {
        pg_mail_settings_dkim_generate($mail_saved, $liveform);

    } elseif (post_value('send_system_email') === 'Send System Test Email') {
        // This request's SMTP constants still hold the old settings, so the
        // pane read after the save sends the test (mail_sending.php).
        $_SESSION['smtp_settings_send_test'] = true;
    }
}
