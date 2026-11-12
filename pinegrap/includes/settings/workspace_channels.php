<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Channels, mail and voice: the channel templates (archive
 * and bring back here, edited on the template screen), the voice message
 * transcripts and spoken answers, the GitHub repositories linked to channels
 * (Link a repository in a drawer), and a card linking the mailboxes, still
 * kept on the Workspace Settings screen.
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

// ── Voice message transcripts ──
$ws_card = ws_voice_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
}

// ── GitHub repositories ──
$ws_card = ws_github_links_settings_card('', $ws_viewer, $ws_form);

if ($ws_card !== '') {
    $pg_settings_cards[] = $ws_card;
    $pg_settings_modals[] = ws_settings_github_link_drawer($ws_viewer, $ws_form, !empty($pg_settings_in_dialog));
}

$pg_settings_cards[] = ws_settings_screen_links_card('pgset-ws-channels-screen', array(
    array('ws-mail', 'bi-envelope', lang('Mailboxes')),
));
