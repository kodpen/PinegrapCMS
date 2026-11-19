<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The save of Site Settings › E-mail sending. It writes config.php defines
 * and the sending service's account (pg_mail_settings_save()), then the
 * config row's campaign texts and MailChimp columns; a refusal, Mailchimp's
 * included, marks its field and writes nothing. A card button runs its job
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

// ── MailChimp ──
//
// Everything here runs BEFORE any write, the mail settings' included, and
// can stop it. A key, a list or a store that Mailchimp refuses is the
// operator's mistake and not the software's, so it is marked on the field
// rather than thrown: the dialog then says which one and leaves on screen
// everything that was typed.
//
// Nothing in this category is written when that happens. Writing the rest
// would leave the site's own settings saved and the integration's refused,
// with one Save button reporting both.
$mailchimp_post            = post_value('mailchimp') ? 1 : 0;
// The key is never printed back into the form: an empty box keeps the
// stored key, the tick removes it.
$mailchimp_key_post        = pg_mailchimp_key_for_save(post_value('mailchimp_key'), (string) db_value("SELECT mailchimp_key FROM config"), (bool) post_value('mailchimp_key_clear'));
$mailchimp_list_post       = trim((string) post_value('mailchimp_list_id'));
$mailchimp_store_post      = trim((string) post_value('mailchimp_store_id'));
$mailchimp_days_post       = pg_parse_whole_number(post_value('mailchimp_sync_days'), 65535);
$mailchimp_limit_post      = pg_parse_whole_number(post_value('mailchimp_sync_limit'), 4294967295);
$mailchimp_automation_post = post_value('mailchimp_automation') ? 1 : 0;

if ($mailchimp_post) {

    if ($mailchimp_key_post === '') {
        $liveform->mark_error('mailchimp_key', lang(array('string' => '{var:1} is required.', 'vars' => lang('API Key'))));
    }

    // The data center at the end of the key is the host the key is sent
    // to; a key without a plain one is refused before any request.
    if (($mailchimp_key_post !== '') && (pg_mailchimp_dc($mailchimp_key_post) === '')) {
        $liveform->mark_error('mailchimp_key', lang('The API key must end with its data center, like -us6.'));
    }

    if ($mailchimp_list_post === '') {
        $liveform->mark_error('mailchimp_list_id', lang(array('string' => '{var:1} is required.', 'vars' => lang('List ID'))));
    }

    if ($mailchimp_store_post === '') {
        $liveform->mark_error('mailchimp_store_id', lang(array('string' => '{var:1} is required.', 'vars' => lang('Store ID'))));
    }
}

if ($mailchimp_post && !$liveform->check_form_errors()) {

    require_once(PG_FUNCTIONS_DIR . '/mailchimp.php');

    // Is the key a key at all.
    $mailchimp_answer = mailchimp_request(array(
        'path' => '/ping',
        'key'  => $mailchimp_key_post));

    if ($mailchimp_answer['status'] == 'error') {
        $liveform->mark_error('mailchimp_key', lang('Sorry, the API key is not valid') . '. ' . h($mailchimp_answer['message']));
    }
}

if ($mailchimp_post && !$liveform->check_form_errors()) {

    $mailchimp_answer = mailchimp_request(array(
        'path' => '/lists/' . $mailchimp_list_post . '?fields=id',
        'key'  => $mailchimp_key_post));

    if ($mailchimp_answer['status'] == 'error') {
        $liveform->mark_error('mailchimp_list_id', lang('Sorry, the List ID is not valid') . '. ' . h($mailchimp_answer['message']));
    }
}

if ($mailchimp_post && !$liveform->check_form_errors()) {

    // A store that is not there yet is not an error -- it is what the
    // screen creates. Any other refusal is.
    $mailchimp_answer = mailchimp_request(array(
        'path'  => '/ecommerce/stores/' . $mailchimp_store_post . '?fields=id,is_syncing',
        'key'   => $mailchimp_key_post,
        'quiet' => true));

    $mailchimp_missing = (($mailchimp_answer['status'] == 'error')
        && ($mailchimp_answer['mailchimp_response']['status'] == 404));

    if (($mailchimp_answer['status'] == 'error') && !$mailchimp_missing) {
        $liveform->mark_error('mailchimp_store_id', lang('Sorry, the Store ID is not valid') . '. ' . h($mailchimp_answer['message']));

    } elseif ($mailchimp_missing) {

        $mailchimp_store = array(
            'id'            => $mailchimp_store_post,
            'list_id'       => $mailchimp_list_post,
            'name'          => HOSTNAME_SETTING,
            'platform'      => 'Pinegrap',
            'domain'        => HOSTNAME_SETTING,
            'is_syncing'    => ($mailchimp_automation_post ? false : true),
            'email_address' => EMAIL_ADDRESS,
            'currency_code' => BASE_CURRENCY_CODE,
        );

        $mailchimp_answer = mailchimp_request(array(
            'method' => 'post',
            'path'   => '/ecommerce/stores',
            'data'   => $mailchimp_store,
            'key'    => $mailchimp_key_post));

        if ($mailchimp_answer['status'] == 'error') {
            $liveform->mark_error('mailchimp_store_id', lang('Sorry, the store could not be created') . '. ' . h($mailchimp_answer['message']));
        }

    } else {
        $mailchimp_store = $mailchimp_answer['mailchimp_response'];
    }

    // is_syncing is the opposite of automation: while the store is syncing
    // its history, Mailchimp holds the automations back.
    if (!$liveform->check_form_errors()
        && isset($mailchimp_store['is_syncing'])
        && ($mailchimp_store['is_syncing'] == (bool) $mailchimp_automation_post)
    ) {
        $mailchimp_store['is_syncing'] = !$mailchimp_automation_post;

        $mailchimp_answer = mailchimp_request(array(
            'method' => 'patch',
            'path'   => '/ecommerce/stores/' . $mailchimp_store_post,
            'data'   => $mailchimp_store,
            'key'    => $mailchimp_key_post));

        if ($mailchimp_answer['status'] == 'error') {
            $liveform->mark_error('mailchimp_automation', lang('Sorry, automation could not be updated') . '. ' . h($mailchimp_answer['message']));
        }
    }
}

if ($liveform->check_form_errors()) {
    return;
}

$mail_saved = pg_mail_settings_save($_POST, $liveform);

if ($mail_saved !== false) {

    // The campaign texts and MailChimp: config row columns, written once
    // config.php has taken the mail settings, so a refused mail setting
    // leaves them as they were too.
    db("UPDATE config
        SET
            opt_in_label = '" . escape(post_value('opt_in_label')) . "',
            plain_text_email_campaign_footer = '" . escape(trim(post_value('plain_text_email_campaign_footer'))) . "',
            mailchimp = '" . escape($mailchimp_post) . "',
            mailchimp_key = '" . escape($mailchimp_key_post) . "',
            mailchimp_list_id = '" . escape($mailchimp_list_post) . "',
            mailchimp_store_id = '" . escape($mailchimp_store_post) . "',
            mailchimp_sync_days = '" . $mailchimp_days_post . "',
            mailchimp_sync_limit = '" . $mailchimp_limit_post . "',
            mailchimp_automation = '" . escape($mailchimp_automation_post) . "',
            last_modified_user_id = '" . USER_ID . "',
            last_modified_timestamp = UNIX_TIMESTAMP()");

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
