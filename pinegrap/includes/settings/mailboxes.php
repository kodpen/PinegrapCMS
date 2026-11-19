<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Mailboxes: the mailboxes whose e-mail is read in
 * Communication › Incoming e-mail, each box's form, its .eml upload and Add
 * a mailbox in drawers. Opened by a holder of the Workspace settings right,
 * whatever the Workspace's switch (pg_settings_mail_settings_allowed()).
 *
 * Fills $pg_settings_cards and $pg_settings_modals; mailboxes.save.php
 * writes what they edit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$ws_form = !empty($pg_settings_in_dialog) ? 'pg_settings_modal_form' : 'pg_settings_page_form';

// connect.php sends the browser back here with what became of Connect a
// Microsoft (or Google) account for mailboxes (a refusal, or the account
// connected); the search index draws every category and leaves the message alone.
if (empty($_GET['search']) && function_exists('pg_connect_flash_take')) {
    $ws_flash = pg_connect_flash_take();

    if ($ws_flash) {
        if ($ws_flash['kind'] === 'error') {
            $liveform->add_error(h($ws_flash['text']));
        } else {
            $liveform->add_notice(h($ws_flash['text']));
        }
    }
}
$ws_viewer = ws_viewer($user);

// ── Mailboxes ──
// Each box's form and Add a mailbox open in drawers; Bring in an .eml file
// is a form of its own in the box's drawer, posting to this category's page.
$ws_card = ws_mail_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;

    foreach (ws_mail_settings_boxes() as $ws_box) {
        $pg_settings_modals[] = ws_settings_mail_drawer($ws_box, $ws_viewer, $ws_form, !empty($pg_settings_in_dialog));
    }

    $pg_settings_modals[] = ws_settings_mail_drawer(null, $ws_viewer, $ws_form, !empty($pg_settings_in_dialog));

    // Forms cannot be nested, so the card's Microsoft button and a Gmail
    // box's Google button submit these by their form attribute; the service
    // sends the browser back to this category's page. A provider whose
    // application is not set up gets no form: its button is not drawn.
    if (function_exists('pg_connect_start_fields') && function_exists('pg_connect_purpose') && pg_connect_purpose('mail_read') && ((int) $ws_viewer['role'] <= 2)) {
        foreach (array('microsoft', 'google') as $ws_provider) {
            if (ws_mail_connect_ready($ws_provider)) {
                $pg_settings_modals[] = '
            <form method="post" action="connect.php" id="' . ws_mail_connect_form_id($ws_provider) . '" class="disable_shortcut d-none">
                ' . pg_connect_start_fields($ws_provider, 'mail_read', pg_settings_url('mailboxes') . '#pgset-ws-mail') . '
            </form>';
            }
        }
    }
}
