<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Assistants and agents writes: Claude's connection and
 * the bulk delete switch with the pane's Save, an agent from its drawer
 * (ws_agent_save, value its id, 0 for a new one), then the job of the card
 * button that asked for the save: Claude's (ws_claude_test,
 * ws_claude_key_create, ws_claude_key_reset), an agent's (ws_agent_delete,
 * ws_agent_test) or a request row's (ws_agent_request_cancel, _retry,
 * _knock, value the request's id).
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The checks and the writes are the ones Workspace Settings uses
 * (ws_claude_settings_save(), ws_claude_settings_post(),
 * ws_bulk_settings_save(), ws_agents_settings_check() and _write(),
 * ws_agents_settings_act(), ws_agent_activity_post()), so a form still open
 * there saves the same way.
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

// An agent's drawer asked for this save: its own fields, by its id, checked
// before anything is written.
$ws_agent_id = post_value('ws_agent_save');
$ws_agent = null;

if (($ws_agent_id !== null) && is_scalar($ws_agent_id)) {
    $ws_agent_id = (int) $ws_agent_id;
    $ws_agent = ws_agents_settings_check($ws_viewer, $ws_agent_id, ws_agents_settings_input(is_array($_POST['ws_agent'] ?? null) ? ($_POST['ws_agent'][$ws_agent_id] ?? array()) : array()));

    if (!$ws_agent['ok']) {
        $liveform->mark_error('ws_agent[' . $ws_agent_id . '][' . (($ws_agent['field'] !== '') ? $ws_agent['field'] : 'handle') . ']', h($ws_agent['error']));
        return;
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

if ($ws_agent !== null) {
    ws_agents_settings_write($ws_viewer, $ws_agent);
    $liveform->add_notice(lang('The agent was saved.'));
}

$ws_warn = function ($sentence) use ($liveform) {
    $liveform->add_warning($sentence);
};

// An agent's Delete or Try, and a request row's buttons.
foreach (array('agent_delete', 'agent_test') as $ws_action) {
    $ws_value = post_value('ws_' . $ws_action);

    if (($ws_value !== null) && is_scalar($ws_value)) {
        ws_agents_settings_act($ws_viewer, $ws_action, (int) $ws_value, $liveform, $ws_warn);
    }
}

foreach (array('agent_request_cancel', 'agent_request_retry', 'agent_request_knock') as $ws_action) {
    $ws_value = post_value('ws_' . $ws_action);

    if (($ws_value !== null) && is_scalar($ws_value)) {
        ws_agent_activity_post($ws_viewer, $ws_action, $liveform, (int) $ws_value, true);
    }
}

// The Claude card's button that asked for this save: the rest of the pane is
// saved by now, so a refusal is a warning.
foreach (array('claude_test', 'claude_key_create', 'claude_key_reset') as $ws_action) {
    if (post_value('ws_' . $ws_action) !== null) {
        ws_claude_settings_post($ws_viewer, $ws_action, $liveform, $user, true);
        break;
    }
}
