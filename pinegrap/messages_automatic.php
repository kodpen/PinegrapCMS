<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Automatic sends: the messages the site sends by itself
 * when something happens. They are automation flows: the list shows the
 * flows whose boxes send an e-mail, SMS or WhatsApp message, with how they
 * start, what they send, their state and their runs today; a row opens its
 * flow on the Flows screen. The quick setup's ready recipes that send a
 * message open the quick setup there with the recipe chosen.
 *
 * Opens to who may open the Flows screen (pg_flows_user_may()) or the
 * campaign screens (validate_email_access()'s rule): the campaign profiles
 * still running are counted here, with the way to their list, until they
 * are turned into flows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_sends.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_automatic.php');

$user = validate_user();

$automatic_email = pg_messages_gate_allows('email', $user['role'], !empty($user['manage_emails']));
$automatic_flows = function_exists('pg_flows_user_may') && pg_flows_user_may($user);

if (!$automatic_email && !$automatic_flows) {
    validate_email_access($user);
}

$self = 'messages_automatic.php';
$body = '';

// The campaign profiles still running, for who may open their list.
if ($automatic_email) {
    $profiles = pg_messages_profiles_running($user);

    if ($profiles > 0) {
        $body .= pg_messages_note(
            lang(array('string' => '{var:1} campaign profile(s) still send as before, beside the flows.', 'vars' => array(pg_format_number($profiles, 0)))),
            lang('Campaign profiles'),
            'view_email_campaign_profiles.php',
            'violet'
        );
    }
}

if (!$automatic_flows) {
    $body .= pg_messages_note(
        function_exists('pg_flows_enabled') && pg_flows_enabled()
            ? lang('Automatic sends are automation flows; they open to the members of the team.')
            : lang('Automation flows are not available yet; run the upgrade.'),
        '',
        '',
        'blue'
    );

    echo pg_messages_tab_page('automatic', $user, lang('Messages the site sends by itself when something happens.'), $body);
    exit();
}

$columns = pg_messages_automatic_columns();
$sort_columns = pg_list_table_printed_sort_columns($columns);
$sort_default = array('sort' => 'status', 'order' => 'asc');
$list_state = pg_list_state('messages_automatic', $sort_columns, $sort_default);
$list = pg_messages_automatic_list($user, $list_state['sorts']);

$primary = $list['quick']
    ? array('label' => lang('Quick setup'), 'url' => pg_messages_quick_url(''), 'icon' => 'bi-magic')
    : '';

$body .= pg_screen_toolbar(array(
        'primary'   => $primary,
        'secondary' => array(array('label' => lang('Automation flows'), 'url' => 'workspace_flows.php', 'icon' => 'bi-diagram-3')),
        'search'    => array(pg_list_table_search_box('messages_automatic'), pg_list_sort_menu($self, $sort_columns, $list_state['sorts'], $sort_default)),
    ))
    . pg_list_table_html($list['source'], $list['page'], lang('Automatic sends'))
    . pg_messages_recipes_html($list['recipes'])
    . pg_js_bundle_tags('assets/js/pg_list_table.min.js');

echo pg_messages_tab_page('automatic', $user, lang('Flows that send an e-mail, SMS or WhatsApp message when something happens.'), $body);
