<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Automatic sends: the messages the site sends by itself
 * when something happens. Until the flows that send a message are listed
 * here it leads to the campaign profiles and, for who may open them, the
 * automation flows (pg_flows_menu_row(), the flows screen's own gate).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');

$user = validate_user();
validate_email_access($user);

$rows = array(
    array('title' => lang('My Campaign Profiles'), 'text' => lang('E-mail campaigns sent by themselves when a contact or an order meets a rule.'), 'url' => 'view_email_campaign_profiles.php', 'icon' => 'bi-envelope-paper', 'tone' => 'violet'),
);

if (pg_flows_menu_row($user) !== null) {
    $rows[] = array('title' => lang('Automation flows'), 'text' => lang('Flows whose boxes send an e-mail, SMS or WhatsApp message.'), 'url' => 'workspace_flows.php', 'icon' => 'bi-diagram-3', 'tone' => 'blue');
}

echo pg_messages_tab_page(
    'automatic',
    $user,
    lang('Messages the site sends by itself when something happens.'),
    pg_messages_place_rows(lang('Where the automatic sends are set'), $rows)
);
