<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings - Channels, mail and voice: a card linking the cards still kept on the
 * Workspace Settings screen (the channel templates, the mailboxes, the voice message
 * transcripts and the GitHub repositories). They move here one at a
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

$pg_settings_cards[] = ws_settings_screen_links_card('pgset-ws-channels-screen', array(
    array('ws-templates', 'bi-layout-text-window', lang('Channel templates')),
    array('ws-mail', 'bi-envelope', lang('Mailboxes')),
    array('ws-voice', 'bi-mic', lang('Voice message transcripts')),
    array('ws-github', 'bi-github', lang('GitHub repositories')),
));
