<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Assistants and agents: Claude in the channels (its
 * connection, its key, the connection test, the latest requests and the
 * setup steps), Pinegrap AI in the channels (its switch, application, key
 * and connection test), the Workspace agents (each edited in a drawer with
 * its waiting and failed requests), the MCP servers, the voice message
 * transcripts and spoken answers (the services' addresses, models and keys),
 * the canvas templates and the assistants' bulk changes.
 *
 * Each part is one folded row (includes/settings/fold.php) holding its card
 * as the card's own function draws it; the rows' order, lines and pills are
 * includes/workspace/assistants_fold.php, each part's line of help and its
 * guide drawer includes/workspace/assistants_help.php. Fills
 * $pg_settings_cards with the one list of rows. Which cards belong here is said once, in
 * includes/settings/registry.php; this file holds them, and
 * workspace_assistants.save.php writes what they edit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_SETTINGS_ENTRY')) {
    exit;
}

// The Claude card names the scopes its key leaves out (includes/api/scopes.php).
if (!defined('PG_API_PANEL')) {
    define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/assistants_fold.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/assistants_help.php');
require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');

$ws_form = !empty($pg_settings_in_dialog) ? 'pg_settings_modal_form' : 'pg_settings_page_form';
$ws_viewer = ws_viewer($user);
$ws_now = time();

// Each part's card and its line of state, by row key; drawn as rows at the
// end in ws_assistants_fold_order().
$ws_parts = array();

// The key and secret Create or Reset secret handed over through the session
// (ws_claude_key_post()), read once by the draw that follows that save and
// removed at once; never by the dialog's search index, which draws every
// category. Pinegrap AI's are left for its own card.
$ws_credentials = $_SESSION['software']['ws_assistant_credentials'] ?? null;

if (is_array($ws_credentials) && (($ws_credentials['agent'] ?? '') === 'claude') && ((int) $ws_viewer['role'] === 0) && !isset($_GET['search'])) {
    unset($_SESSION['software']['ws_assistant_credentials']);

    if (!headers_sent()) {
        header('Cache-Control: no-store');
    }

    $pg_settings_modals[] = pg_settings_drawer('pgset-ws-claude-secret', h(!empty($ws_credentials['rotated']) ? lang('A new secret was issued') : lang('The application was created')) . ' · ' . h((string) ($ws_credentials['name'] ?? '')), ws_claude_credentials_drawer_html($ws_credentials), !empty($pg_settings_in_dialog), ws_claude_credentials_drawer_foot_html());
} else {
    $ws_credentials = null;
}

// ── Claude in the channels ──
$ws_card = ws_claude_settings_card('', $ws_viewer, $ws_credentials, $ws_form);

if ($ws_card !== '') {
    $ws_parts['ws-claude'] = array('html' => $ws_card) + ws_assistants_fold_claude(ws_assistants_fold_facts_claude(), $ws_now);
    // The set-up on claude.ai, as numbered steps, opened from the row.
    $pg_settings_modals[] = ws_settings_claude_setup_drawer(!empty($pg_settings_in_dialog));
    // The newest requests and the instructions' version, opened from the row.
    $pg_settings_modals[] = ws_settings_claude_requests_drawer($ws_viewer, !empty($pg_settings_in_dialog));
}

// ── Workspace agents ──
// Each agent's activity and form, and Add an agent, open in drawers whose
// fields and buttons join the settings form by its id.
$ws_card = ws_agents_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $ws_apps = ws_agents_settings_apps();
    $ws_agents = ws_agents_list();
    $ws_counts = function_exists('ws_agent_activity_read_counts') ? ws_agent_activity_read_counts($ws_agents) : array();
    // Every agent's open and today's requests, read once for the drawers
    // and the row's line.
    $ws_requests = ws_agent_request_counts($ws_agents, $ws_now);
    $ws_parts['ws-agents'] = array('html' => $ws_card) + ws_assistants_fold_agents(ws_assistants_fold_facts_agents($ws_agents, $ws_requests, $ws_now));

    foreach ($ws_agents as $ws_agent) {
        $pg_settings_modals[] = ws_settings_agent_drawer($ws_agent, $ws_viewer, $ws_apps, $ws_counts, $ws_form, !empty($pg_settings_in_dialog), $ws_requests);
    }

    if ((int) $ws_viewer['role'] === 0) {
        $pg_settings_modals[] = ws_settings_agent_drawer(null, $ws_viewer, $ws_apps, $ws_counts, $ws_form, !empty($pg_settings_in_dialog));
    }
}

// ── Pinegrap AI in the channels ──
$ws_card = function_exists('ws_ai_settings_card') ? ws_ai_settings_card('', $ws_viewer, $ws_form) : '';

if ($ws_card !== '') {
    $ws_parts['ws-ai'] = array('html' => $ws_card) + ws_assistants_fold_ai(ws_assistants_fold_facts_ai());
}

// ── Voice message transcripts ──
$ws_card = ws_voice_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $ws_parts['ws-voice'] = array('html' => $ws_card) + ws_assistants_fold_voice(ws_assistants_fold_facts_voice());
}

// ── MCP servers ──
// What a refused server form or an address check kept, handed over through
// the session by the save before this draw (ws_mcp_settings_relay), read once
// and never by the dialog's search index.
$ws_mcp_kept = array();

if (!isset($_GET['search']) && isset($_SESSION['software']['ws_mcp_kept'])) {
    $ws_mcp_kept = is_array($_SESSION['software']['ws_mcp_kept']) ? $_SESSION['software']['ws_mcp_kept'] : array();
    unset($_SESSION['software']['ws_mcp_kept']);
}

$ws_mcp_relay = new ws_mcp_settings_relay(null, $ws_mcp_kept);

// Back on the page from a service's sign-in (connect.php): its message, and
// the tools read when an administrator connected the site.
if (empty($pg_settings_in_dialog) && isset($_GET['mcp_connected']) && !isset($_GET['search'])) {
    ws_mcp_settings_returned($ws_viewer, $liveform);
}

$ws_card = ws_mcp_settings_card('', $ws_viewer, $ws_mcp_relay, $ws_form);

if ($ws_card !== '') {
    $ws_mcp_servers = ws_mcp_servers();
    $ws_parts['ws-mcp'] = array('html' => $ws_card) + ws_assistants_fold_mcp(ws_assistants_fold_facts_mcp($ws_mcp_servers));
    $ws_mcp_kept = ws_mcp_settings_kept(((int) $ws_viewer['role'] === 0) ? $ws_mcp_relay : null);

    foreach ($ws_mcp_servers as $ws_mcp_server) {
        $pg_settings_modals[] = ws_settings_mcp_drawer($ws_mcp_server, $ws_viewer, $ws_mcp_kept, $ws_mcp_servers, $ws_form, !empty($pg_settings_in_dialog));
    }

    if ((int) $ws_viewer['role'] === 0) {
        $pg_settings_modals[] = ws_settings_mcp_drawer(null, $ws_viewer, $ws_mcp_kept, $ws_mcp_servers, $ws_form, !empty($pg_settings_in_dialog));
    }
}

// ── Bulk changes by the assistants ──
$ws_card = ws_bulk_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $ws_parts['ws-bulk'] = array('html' => $ws_card) + ws_assistants_fold_bulk(ws_bulk_delete_allowed());
}

// ── Canvas templates ──
$ws_card = ws_canvas_templates_settings_card($ws_viewer);

if ($ws_card !== '') {
    $ws_parts['ws-canvas-templates'] = array('html' => $ws_card) + ws_assistants_fold_templates(count(pg_canvas_templates_rows()));
}

// ── The parts' guides ──
// "How does this work?" under a part's line opens its numbered steps.
foreach (ws_assistants_help_keys() as $ws_key) {
    if (isset($ws_parts[$ws_key])) {
        $pg_settings_modals[] = ws_settings_assistants_guide_drawer($ws_key, !empty($pg_settings_in_dialog));
    }
}

// ── The rows ──
$ws_rows = array();

foreach (ws_assistants_fold_order() as $ws_key) {
    if (isset($ws_parts[$ws_key])) {
        $ws_rows[] = array('key' => $ws_key) + ws_assistants_fold_face($ws_key) + $ws_parts[$ws_key];
    }
}

$ws_card = pg_settings_fold($ws_rows);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}
