<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Channels › Notifications: the screen "Send a notification"
 * of visitor notifications (includes/fn/site_push_panel.php). The form sends
 * news to every browser subscribed with Notify me, or a test to this
 * browser when it is subscribed itself; "Pick a page or record" fills the
 * form from the site search index (?pick=, JSON). Below, the sent news and
 * tests with their counts. Role <= 2, as the Channels tab.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_sends.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/site_push_panel.php');
include_once('liveform.class.php');

$user = validate_user();
validate_area_access($user, 'manager');

$self = PATH . SOFTWARE_DIRECTORY . '/' . PG_SITE_PUSH_SCREEN;

// The picker's search: JSON, read only.
if (isset($_GET['pick'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode(array('items' => pg_site_push_pick(is_string($_GET['pick']) ? $_GET['pick'] : '')));
    exit;
}

$liveform = new liveform('messages_notifications');

if ($_POST) {
    validate_token_field();

    $input = array(
        'title' => isset($_POST['title']) && is_string($_POST['title']) ? $_POST['title'] : '',
        'body'  => isset($_POST['body']) && is_string($_POST['body']) ? $_POST['body'] : '',
        'url'   => isset($_POST['url']) && is_string($_POST['url']) ? $_POST['url'] : '',
    );
    $test = (isset($_POST['send']) && ($_POST['send'] === 'test'));
    $result = 'device';

    if ($test) {
        $subscription = pg_site_push_subscription_by_endpoint(isset($_POST['endpoint']) ? $_POST['endpoint'] : '');

        if ($subscription > 0) {
            $result = pg_site_push_message_send($input, PG_SITE_PUSH_KIND_TEST, (int) $user['id'], $subscription);
        }
    } else {
        $result = pg_site_push_message_send($input, PG_SITE_PUSH_KIND_NEWS, (int) $user['id']);
    }

    if (is_int($result)) {
        $liveform->add_notice($test
            ? lang('The test is on its way to this device; it arrives within a minute or two.')
            : lang('The notification is on its way; the counts below fill in as it is delivered.'));
    } else {
        $liveform->add_warning(pg_site_push_send_error($result));
        $liveform->assign_field_value('title', $input['title']);
        $liveform->assign_field_value('body', $input['body']);
        $liveform->assign_field_value('url', $input['url']);
    }

    go($self);
}

$facts = pg_site_push_card_facts();
$body = $liveform->output_errors() . $liveform->get_warnings() . $liveform->output_notices();

if (!$facts['ready']) {
    $body .= pg_messages_note(lang('Visitor notifications are not available yet; run the upgrade.'), '', '', 'amber');
} elseif (!$facts['on']) {
    $body .= pg_messages_note(pg_site_push_send_error('off'), '', '', 'amber');
}

$values = array(
    'title' => $liveform->get_field_value('title'),
    'body'  => $liveform->get_field_value('body'),
    'url'   => $liveform->get_field_value('url'),
);
$liveform->remove();

$body .= pg_site_push_form_html(array(
    'token'       => get_token_field(),
    'subscribers' => $facts['subscribers'],
    'enabled'     => $facts['ready'] && $facts['on'],
    'values'      => $values,
));

$columns = pg_site_push_list_columns();
$sort_columns = pg_list_table_printed_sort_columns($columns);
$sort_default = array('sort' => 'sent_at', 'order' => 'desc');
$list_state = pg_list_state('site_push_messages', $sort_columns, $sort_default);
$list = pg_site_push_list($list_state['sorts']);

$body .= '<h2 class="pg-title-section mb-3">' . h(lang('Sent notifications')) . '</h2>'
    . pg_screen_toolbar(array(
        'search' => array(pg_list_table_search_box('site_push_messages'), pg_list_sort_menu(PG_SITE_PUSH_SCREEN, $sort_columns, $list_state['sorts'], $sort_default)),
    ))
    . pg_list_table_html($list['source'], $list['page'], lang('Sent notifications'))
    . pg_js_bundle_tags('assets/js/pg_list_table.min.js')
    . '<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_site_push_panel.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_site_push_panel.js') . '" defer></script>';

echo pg_messages_tab_page('channels', $user, lang('Send the site\'s news to the browsers that asked for it.'), $body);
