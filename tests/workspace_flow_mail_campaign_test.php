<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow's e-mail box sending as an e-mail campaign sends
 * (includes/workspace/flow_mail_campaign.php and the page helpers in
 * includes/fn/mail.php): the sender kept and checked, the ^^fields^^ from
 * the start values and the contact, the record a page's PHP is given, the
 * group a run joined, a page body's base tag, absolute links, ?t= codes and
 * commercial footer, and the plain text footer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_mail_person.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_mail_campaign.php');

// The sender is kept as single lines; a box drawn before has none.
function test_workspace_flow_mail_campaign_sender_kept()
{
	$config = ws_step_config_normalize('email', array('to' => 'a@example.com', 'subject' => 'Hi', 'body' => 'Text',
		'from_name' => " Shop\nTeam ", 'from_email' => ' news@example.com ', 'reply_to' => 'help@example.com', 'bcc' => array('x')));

	pg_assert_same(array('Shop Team', 'news@example.com', 'help@example.com', ''), array($config['from_name'], $config['from_email'], $config['reply_to'], $config['bcc']));

	$before = ws_step_config_normalize('email', array('to' => 'a@example.com', 'subject' => 'Hi', 'body' => 'Text'));

	pg_assert_same(array('', '', '', ''), array($before['from_name'], $before['from_email'], $before['reply_to'], $before['bcc']), 'a box drawn before sends as the site');
	pg_assert_same(array(), ws_flow_mail_sender_properties($before), 'and sets no sender');
	pg_assert_same(100, mb_strlen(ws_flow_mail_sender_normalize(array('from_name' => str_repeat('a', 300)))['from_name']), 'cut to the campaign column');
}

// Each address is checked as a campaign's sender is.
function test_workspace_flow_mail_campaign_sender_checked()
{
	$problems = function ($config) {
		return ws_step_config_problems('email', ws_step_config_normalize('email', $config + array('to' => 'a@example.com', 'subject' => 'Hi', 'body' => 'Text')), 'Mail');
	};

	pg_assert_same(array(), $problems(array('from_email' => 'news@example.com', 'reply_to' => 'help@example.com', 'bcc' => 'copy@example.com')));
	pg_assert_same(3, count($problems(array('from_email' => 'news', 'reply_to' => 'a@b@c', 'bcc' => 'one@example.com, two@example.com'))), 'not an address, nor a list');
	pg_assert_same(array(), $problems(array('from_name' => 'Only a name')), 'a name alone uses the site\'s address');
}

function test_workspace_flow_mail_campaign_sender_properties()
{
	pg_assert_same(array('from_name' => 'Shop', 'reply_to' => 'help@example.com'), ws_flow_mail_sender_properties(array('from_name' => 'Shop', 'from_email' => '', 'reply_to' => 'help@example.com', 'bcc' => '')));
	pg_assert_same(array('from_name' => 'Shop', 'from_email_address' => 'news@example.com', 'reply_to' => 'r@example.com', 'bcc' => 'b@example.com'),
		ws_flow_mail_sender_properties(array('from_name' => 'Shop', 'from_email' => 'news@example.com', 'reply_to' => 'r@example.com', 'bcc' => 'b@example.com')));
}

// The fields: start values first, then the contact's names.
function test_workspace_flow_mail_campaign_fields()
{
	$fields = ws_flow_mail_fields(array('order_number' => 1042, 'paid' => true, 'items' => array(1, 2), 'bad key' => 'x', 'missing' => null),
		array('first_name' => 'Ada', 'last_name' => 'Lovelace', 'nickname' => '', 'salutation' => 'Ms', 'suffix' => ''));
	$byName = array();

	foreach ($fields as $field) {
		$byName[$field['name']] = $field['data'];
	}

	pg_assert_same(array('order_number' => '1042', 'paid' => '1', 'name' => 'Ms Ada Lovelace', 'first_name' => 'Ada', 'last_name' => 'Lovelace'), $byName, 'lists, nulls and odd names left out');
	pg_assert_same('Hello Ms Ada Lovelace, order 1042', replace_variables(array('content' => 'Hello ^^name^^, order ^^order_number^^', 'fields' => $fields, 'format' => 'plain_text')));

	$none = ws_flow_mail_fields(array(), null);

	pg_assert_same('Hello there', replace_variables(array('content' => 'Hello[[ ^^first_name^^||]] there', 'fields' => $none, 'format' => 'plain_text')), 'no contact: the fallback');
	pg_assert_same('Grace', pg_mail_merge_contact_fields(array('nickname' => 'Grace', 'first_name' => 'G', 'last_name' => 'H'))[0]['data'], 'a nickname is the name');
	pg_assert_same('Ada Lovelace, PhD', pg_mail_merge_contact_fields(array('first_name' => 'Ada', 'last_name' => 'Lovelace', 'suffix' => 'PhD'))[0]['data']);

	$first = ws_flow_mail_fields(array('name' => 'From the form'), array('first_name' => 'Ada'));

	pg_assert_same('From the form', replace_variables(array('content' => '^^name^^', 'fields' => $first, 'format' => 'plain_text')), 'a start value of the same name comes first');
}

function test_workspace_flow_mail_campaign_record_and_group()
{
	pg_assert_same(array('order' => array('id' => 5)), ws_flow_mail_record_data('order', array('id' => 5)));
	pg_assert_same(array(), ws_flow_mail_record_data('mail', array('id' => 2)), 'a received e-mail gives none');
	pg_assert_same(array(), ws_flow_mail_record_data('', array('id' => 2)));
	pg_assert_same(array(), ws_flow_mail_record_data('order', null), 'a record gone');

	pg_assert_same(array('contact_id' => 7, 'group_id' => 3), ws_flow_mail_joined_group(array('event' => 'contact_group.joined', 'event_payload' => array('contact_id' => 7, 'group_id' => 3))));
	pg_assert_same(array('contact_id' => 0, 'group_id' => 0), ws_flow_mail_joined_group(array('event' => 'order.created', 'event_payload' => array('contact_id' => 7, 'group_id' => 3))));
}

// A page body goes as a campaign's page.
function test_workspace_flow_mail_campaign_page_html()
{
	$page = '<html><head><title>T</title></head><body><a href="/shop">Shop</a> <a href="https://other.example/x">Out</a> <a href="mailto:a@b.c">Mail</a>'
		. ' <a href="^^review_url^^">Review</a> <img src="files/a.png"></body></html>';
	$organization = array('name' => 'Acme', 'address_1' => 'Main St 1', 'address_2' => '', 'city' => 'Ankara', 'state' => '', 'zip_code' => '06100', 'country' => 'TR');
	$info = ws_flow_mail_page_html($page, false, 'https://example.com', 'Page_1', 'example.com', $organization, 'https://example.com/email_preferences.php?id=' . ws_flow_mail_preferences_placeholder());

	pg_assert_true(strpos($info, '<base href="https://example.com/" />') !== false, 'a base tag');
	pg_assert_true(strpos($info, 'href="https://example.com/shop"') !== false, 'an absolute link');
	pg_assert_true(strpos($info, 'src="https://example.com/files/a.png"') !== false, 'an absolute image');
	pg_assert_true(strpos($info, 'href="^^review_url^^"') !== false, 'a field\'s address left as written');
	pg_assert_true(strpos($info, '?t=') === false, 'information carries no codes');
	pg_assert_true(strpos($info, 'software_email_footer') === false, 'nor the footer');

	$marketing = ws_flow_mail_page_html($page, true, 'https://example.com', 'Page_1', 'example.com', $organization, 'https://example.com/email_preferences.php?id=' . ws_flow_mail_preferences_placeholder());

	pg_assert_true(strpos($marketing, 'href="https://example.com/shop?t=Page_1"') !== false, 'a code on a link to the site');
	pg_assert_true(strpos($marketing, 'href="https://other.example/x"') !== false, 'none on another site\'s');
	pg_assert_true(strpos($marketing, 'href="mailto:a@b.c"') !== false, 'nor on mailto');
	pg_assert_true(strpos($marketing, 'Acme') !== false && strpos($marketing, 'Ankara') !== false, 'the organization');
	pg_assert_true(strpos($marketing, ws_flow_mail_preferences_placeholder()) !== false, 'the preferences link waits for the address');
	pg_assert_true(strpos($marketing, '</div></body>') !== false, 'the footer before </body>');

	$based = '<html><head><base href="https://cdn.example/"></head><body><a href="x">X</a></body></html>';

	pg_assert_same($based, pg_mail_page_absolute_links($based, 'https://example.com'), 'a page with a base of its own stays');
	pg_assert_same('<a href="/a?q=1&amp;t=C#top">A</a>', pg_mail_page_tracking_codes('<a href="/a?q=1#top">A</a>', 'C', 'example.com'), 'query and fragment kept');
	pg_assert_same('<a href="/a?t=old">A</a>', pg_mail_page_tracking_codes('<a href="/a?t=old">A</a>', 'C', 'example.com'), 'a code already there');
	pg_assert_same('<a href="https://www.example.com/a?t=C">A</a>', pg_mail_page_tracking_codes('<a href="https://www.example.com/a">A</a>', 'C', 'example.com'), 'www is this site');
}

function test_workspace_flow_mail_campaign_plain_footer()
{
	pg_assert_same('Acme Main St 1 Ankara', pg_mail_organization_line(array('name' => 'Acme', 'address_1' => 'Main St 1', 'address_2' => '', 'city' => 'Ankara')));

	$plain = ws_flow_mail_unsubscribe_footer("Hello\n", 'plain_text', 'https://example.com/p', 'Acme Ankara');

	pg_assert_true(strpos($plain, "Hello\n\nAcme Ankara\n\n") === 0, 'the organization before the link');
	pg_assert_true(substr($plain, -21) === "https://example.com/p", 'the link last');
	pg_assert_same("Hello\n\nAcme", ws_flow_mail_unsubscribe_footer("Hello", 'plain_text', '', 'Acme'), 'no link without a key, the organization still');
}
