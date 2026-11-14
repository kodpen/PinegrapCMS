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

    // Forms cannot be nested, so the card's Connect button submits this one
    // by its form attribute; the service sends the browser back to this
    // category's page.
    if (function_exists('pg_connect_start_fields') && function_exists('pg_connect_purpose') && pg_connect_purpose('mail_read') && ((int) $ws_viewer['role'] <= 2)) {
        $pg_settings_modals[] = '
            <form method="post" action="connect.php" id="pgset_mail_connect" class="disable_shortcut d-none">
                ' . pg_connect_start_fields('microsoft', 'mail_read', pg_settings_url('mailboxes') . '#pgset-ws-mail') . '
            </form>';
    }
}
