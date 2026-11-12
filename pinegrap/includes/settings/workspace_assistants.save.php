<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Assistants and agents writes: Claude's connection and
 * the bulk delete switch with the pane's Save, then the job of the Claude
 * card's button that asked for the save (ws_claude_test, ws_claude_key_create,
 * ws_claude_key_reset).
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The checks and the writes are the ones Workspace Settings uses
 * (ws_claude_settings_save(), ws_claude_settings_post(),
 * ws_bulk_settings_save()), so a form still open there saves the same way.
 * Both cards are an administrator's: for anybody else their fields are drawn
 * disabled and not posted, and a posted one is left alone. Everything is
 * checked before anything is written: a refused value is marked on its field
 * and nothing is stored.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// Create and Reset secret make and rotate an application key (includes/api/keys.php).
if (!defined('PG_API_PANEL')) {
    define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$ws_viewer = ws_viewer($user);
$ws_admin = ((int) $ws_viewer['role'] === 0);

// Claude's connection: written only when the card's fields came and differ
// from what is stored, as a write clears the routine's last error and wait.
$ws_claude = null;

if ($ws_admin && ws_claude_schema_ready() && isset($_POST['ws_claude_routine_url'])) {
    $ws_claude = ws_claude_settings_input($_POST, 'ws_');

    if (!ws_claude_settings_changed($ws_claude, ws_claude_config(true), ws_claude_summary_ready() ? ws_claude_summary_config(true) : null)) {
        $ws_claude = null;
    }
}

// The bulk delete switch: the hidden ws_bulk_card says the card was posted,
// as an unticked box is not.
$ws_bulk = null;

if ($ws_admin && ws_bulk_ready() && (post_value('ws_bulk_card') !== null)) {
    $ws_bulk = !empty($_POST['ws_bulk_delete']);

    if ($ws_bulk === ws_bulk_delete_allowed()) {
        $ws_bulk = null;
    }
}

if ($ws_claude !== null) {
    $ws_refused = ws_claude_settings_save($ws_claude);

    foreach ($ws_refused['refused'] as $ws_field => $ws_sentence) {
        $liveform->mark_error('ws_claude_' . $ws_field, $ws_sentence);
    }

    if ($ws_refused['refused']) {
        return;
    }

    log_activity(lang('the Claude connection of the workspace was changed'), (string) ($_SESSION['sessionusername'] ?? ''));
}

if ($ws_bulk !== null) {
    ws_bulk_settings_save($ws_bulk);
}

// The Claude card's button that asked for this save: the rest of the pane is
// saved by now, so a refusal is a warning.
foreach (array('claude_test', 'claude_key_create', 'claude_key_reset') as $ws_action) {
    if (post_value('ws_' . $ws_action) !== null) {
        ws_claude_settings_post($ws_viewer, $ws_action, $liveform, $user, true);
        break;
    }
}
