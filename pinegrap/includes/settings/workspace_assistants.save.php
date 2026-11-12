<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - what Assistants and agents writes: Claude's and Pinegrap
 * AI's connections and the bulk delete switch with the pane's Save, an agent
 * from its drawer
 * (ws_agent_save, value its id, 0 for a new one), then the job of the card
 * button that asked for the save: Claude's (ws_claude_test,
 * ws_claude_key_create, ws_claude_key_reset), Pinegrap AI's (ws_ai_test,
 * ws_ai_key_create), an agent's (ws_agent_delete,
 * ws_agent_test), a request row's (ws_agent_request_cancel, _retry,
 * _knock, value the request's id) or the MCP servers card's (ws_mcp_save,
 * ws_mcp_tools, ws_mcp_refresh, ... read by ws_mcp_settings_pressed()).
 *
 * Runs in the scope of includes/settings/screen.php or of
 * pg_settings_apply(), after the token check and after post_value() is
 * defined. The checks and the writes are the ones Workspace Settings uses
 * (ws_claude_settings_save(), ws_claude_settings_post(),
 * ws_bulk_settings_save(), ws_agents_settings_check() and _write(),
 * ws_agents_settings_act(), ws_agent_activity_post(), ws_ai_settings_check()
 * and _write(), ws_ai_settings_post(), ws_mcp_settings_post()), so a form still open
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

// Pinegrap AI's connection, checked before anything is written: its
// application may not be Claude's, as stored or as posted in this save.
$ws_ai = null;

if ($ws_admin && function_exists('ws_ai_schema_ready') && ws_ai_schema_ready() && isset($_POST['ws_ai_app_id'])) {
    $ws_ai = ws_ai_settings_input($_POST, 'ws_');

    if (ws_ai_settings_changed($ws_ai, ws_ai_config(true))) {
        $ws_claude_app = ($ws_claude !== null) ? (int) $ws_claude['app_id'] : (int) ws_claude_config()['app_id'];

        foreach (ws_ai_settings_check($ws_ai, $ws_claude_app) as $ws_field => $ws_sentence) {
            $liveform->mark_error('ws_ai_' . $ws_field, $ws_sentence);
        }

        if ($liveform->check_form_errors()) {
            return;
        }
    } else {
        $ws_ai = null;
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

if ($ws_ai !== null) {
    ws_ai_settings_write($ws_ai, $liveform, $ws_warn);
}

// Pinegrap AI's Create key or Try the connection.
foreach (array('ai_test', 'ai_key_create') as $ws_action) {
    if (post_value('ws_' . $ws_action) !== null) {
        ws_ai_settings_post($ws_viewer, $ws_action, $liveform, $user, true);
        break;
    }
}

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

// An MCP servers card button: a server's Save, its tools' Save or the warned
// declare, Ask again, Turn off or on, Remove, the site's or the reader's own
// sign-in, a ready-made connector's Add, Check the address or the short form
// after it. The rest of the pane is saved by now, so a refusal is a warning;
// what a refused form or the check kept goes to the pane drawn next, and a
// sign-in is handed to the dialog or the page as the address to go to.
$ws_mcp_pressed = ws_mcp_ready() ? ws_mcp_settings_pressed($_POST) : null;

if ($ws_mcp_pressed !== null) {
    $ws_mcp_relay = new ws_mcp_settings_relay($liveform);

    ws_mcp_settings_place(array('return' => pg_settings_url('workspace_assistants'), 'anchor' => '#pgset-ws-mcp', 'hold' => true));
    ws_mcp_settings_post($ws_viewer, $ws_mcp_pressed['action'], $ws_mcp_relay, $ws_mcp_pressed['input']);

    if ($ws_mcp_relay->kept) {
        $_SESSION['software']['ws_mcp_kept'] = $ws_mcp_relay->kept;
    }

    $ws_mcp_go = (string) ws_mcp_settings_place()['go'];

    if ($ws_mcp_go !== '') {
        pg_settings_go($ws_mcp_go);
    }
}
