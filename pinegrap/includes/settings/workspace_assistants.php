<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Assistants and agents: a card linking the cards still kept on the
 * Workspace Settings screen (Claude, the Workspace agents, Pinegrap AI, the MCP servers and
 * the assistants' bulk changes). They move here one at a
 * time; each that moves takes its link out of this card.
 *
 * Fills $pg_settings_cards, one grid column per card, in reading order. Which
 * cards belong here is said once, in includes/settings/registry.php.
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

$pg_settings_cards[] = ws_settings_screen_links_card('pgset-ws-assistants-screen', array(
    array('ws-claude', 'bi-stars', lang('Claude in the channels')),
    array('ws-agents', 'bi-robot', lang('Workspace agents')),
    array('ws-ai', 'bi-cpu', lang('Pinegrap AI in the channels')),
    array('ws-mcp', 'bi-plug', lang('MCP servers')),
    array('ws-bulk', 'bi-collection', lang('Bulk changes by the assistants')),
));
