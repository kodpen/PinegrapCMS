<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Mailboxes: the mailboxes whose e-mail is read in
 * Communication › Incoming e-mail as folded rows (the boxes, the accounts
 * for mailboxes, the rules, the blocked senders, Bring in an .eml file),
 * each box's form, Add a mailbox and Google's and Microsoft's set-up steps
 * in drawers. Opened by a holder of the Workspace settings right,
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
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

$ws_form = !empty($pg_settings_in_dialog) ? 'pg_settings_modal_form' : 'pg_settings_page_form';

// connect.php sends the browser back here with what became of Connect an
// account for mailboxes on Google's or Microsoft's page of Connections, when
// the card or a box's form led there (a refusal, or the account connected);
// the search index draws every category and leaves the message alone.
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
// The pane is folded rows (ws_mail_settings_rows()); each box's form and Add
// a mailbox open in drawers; Bring in an .eml file, the last row, posts a
// form of its own printed outside the settings form, to this category's page.
$ws_card = ws_mail_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
    $pg_settings_modals[] = ws_mail_eml_pane_form();

    foreach (ws_mail_settings_boxes() as $ws_box) {
        $pg_settings_modals[] = ws_settings_mail_drawer($ws_box, $ws_viewer, $ws_form, !empty($pg_settings_in_dialog));
    }

    $pg_settings_modals[] = ws_settings_mail_drawer(null, $ws_viewer, $ws_form, !empty($pg_settings_in_dialog));

    // What is done at Google or Microsoft, opened from a box's Reading tab.
    foreach (array('google', 'microsoft') as $ws_provider) {
        $pg_settings_modals[] = ws_settings_mail_setup_drawer($ws_provider, $ws_viewer, !empty($pg_settings_in_dialog));
    }
}
