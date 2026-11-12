<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Assistants and agents: Claude in the channels (its
 * connection, its key, the connection test, the latest requests and the
 * setup steps), the assistants' bulk changes, and a card linking the cards
 * still kept on the Workspace Settings screen (the Workspace agents,
 * Pinegrap AI and the MCP servers). They move here one at a time; each that
 * moves takes its link out of that card.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and workspace_assistants.save.php writes what they edit.
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

$ws_form = !empty($pg_settings_in_dialog) ? 'pg_settings_modal_form' : 'pg_settings_page_form';
$ws_viewer = ws_viewer($user);

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

    $pg_settings_modals[] = pg_settings_drawer('pgset-ws-claude-secret', h(!empty($ws_credentials['rotated']) ? lang('A new secret was issued') : lang('The application was created')) . ' · ' . h((string) ($ws_credentials['name'] ?? '')), ws_claude_credentials_drawer_html($ws_credentials), !empty($pg_settings_in_dialog));
} else {
    $ws_credentials = null;
}

// ── Claude in the channels ──
$ws_card = ws_claude_settings_card('', $ws_viewer, $ws_credentials, $ws_form);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}

// ── Bulk changes by the assistants ──
$ws_card = ws_bulk_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}

$pg_settings_cards[] = ws_settings_screen_links_card('pgset-ws-assistants-screen', array(
    array('ws-agents', 'bi-robot', lang('Workspace agents')),
    array('ws-ai', 'bi-cpu', lang('Pinegrap AI in the channels')),
    array('ws-mcp', 'bi-plug', lang('MCP servers')),
));
