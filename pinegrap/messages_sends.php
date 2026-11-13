<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Sends: every message the site sends, campaigns and the
 * e-mail, SMS and WhatsApp queues in one list. Until that list is built it
 * leads to the screens that hold them today: the mail queue (role <= 2, as
 * it holds password resets and receipts) and the campaign history.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

$user = validate_user();
validate_email_access($user);

$rows = array();

if ($user['role'] < 3) {
    $rows[] = array('title' => lang('E-mail queue'), 'text' => lang('Every e-mail waiting, sent or failed, with retry.'), 'url' => 'mail_queue.php', 'icon' => 'bi-envelope', 'tone' => 'blue');

    if (pg_sms_queue_shown()) {
        $rows[] = array('title' => lang('SMS queue'), 'text' => lang('Every text message waiting, sent or failed, with retry.'), 'url' => 'mail_queue.php?tab=sms', 'icon' => 'bi-chat-text', 'tone' => 'amber');
    }
}

$rows[] = array('title' => lang('Finished campaigns'), 'text' => lang('Completed and cancelled e-mail and SMS campaigns.'), 'url' => 'view_email_campaigns.php?state=finished', 'icon' => 'bi-send-check', 'tone' => 'violet');

echo pg_messages_tab_page(
    'sends',
    $user,
    lang('Every message the site sends, with its state.'),
    pg_messages_place_rows(lang('Where the sends are listed'), $rows)
);
