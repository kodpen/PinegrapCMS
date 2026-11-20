<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Channels: the channel templates (archive and bring back
 * here, edited on the template screen), the GitHub repositories card (how
 * many are linked; they are linked on GitHub's page of Connections) and the
 * link previews switch and the Calls card (includes/workspace/calls_settings.php).
 * The mailboxes have a category of their own (includes/settings/mailboxes.php);
 * the voice message transcripts are a card of Assistants and agents
 * (includes/settings/workspace_assistants.php).
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php; this file
 * holds them, and workspace_channels.save.php writes what they edit.
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

// ── Channel templates ──
$ws_card = ws_templates_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}

// ── GitHub repositories ──
$ws_card = ws_github_links_settings_card($ws_viewer);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}

// ── Link previews ──
$ws_card = ws_link_preview_settings_card($ws_viewer);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}

// ── Calls ──
$ws_card = ws_calls_settings_card($ws_viewer);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}
