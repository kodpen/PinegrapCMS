<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for routing incoming e-mail (includes/workspace/mail_route.php):
 * what a route may say, which addresses a mail was written to, which route
 * a new conversation takes, the box form's routes, and the line a known
 * customer's channel gets. Writing into channels needs the database and is
 * not run here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function test_workspace_mail_route_match()
{
	pg_assert_same('+billing', ws_mail_route_match(' +Billing '));
	pg_assert_same('+tr.sales-2', ws_mail_route_match('+tr.sales-2'));
	pg_assert_same('billing@shop.test', ws_mail_route_match('Billing@Shop.Test'));
	pg_assert_same('', ws_mail_route_match('+'), 'an empty tag');
	pg_assert_same('', ws_mail_route_match('+a b'), 'a space');
	pg_assert_same('', ws_mail_route_match('billing'), 'neither a tag nor an address');
	pg_assert_same('', ws_mail_route_match('+' . str_repeat('a', 65)), 'a tag too long');
}

function test_workspace_mail_routes_read_from_the_box()
{
	$box = array('settings' => json_encode(array('routes' => array(
		array('match' => '+billing', 'channel_id' => 4),
		array('match' => 'bad', 'channel_id' => 5),
		array('match' => 'sales@shop.test', 'channel_id' => 0),
		'junk',
		array('match' => 'Sales@Shop.test', 'channel_id' => '6'),
	))));

	pg_assert_same(array(array('match' => '+billing', 'channel_id' => 4), array('match' => 'sales@shop.test', 'channel_id' => 6)), ws_mail_routes($box), 'broken routes dropped');
	pg_assert_same(array(), ws_mail_routes(null));
	pg_assert_same(array(), ws_mail_routes(array('settings' => '')));
}

function test_workspace_mail_recipients()
{
	$parsed = pg_mail_parse(implode("\r\n", array(
		'From: a@example.com',
		'To: "Support" <Support+Billing@shop.test>, b@example.com',
		'Cc: c@example.com, b@example.com',
		'Delivered-To: sales@shop.test',
		'X-Original-To: <alias@shop.test>',
		'Subject: Hi',
		'',
		'Hello',
	)));

	pg_assert_same(array('support+billing@shop.test', 'b@example.com', 'c@example.com', 'sales@shop.test', 'alias@shop.test'), ws_mail_recipients($parsed));
}

function test_workspace_mail_route_pick()
{
	$routes = array(
		array('match' => '+billing', 'channel_id' => 4),
		array('match' => 'sales@shop.test', 'channel_id' => 6),
		array('match' => '+sales', 'channel_id' => 7),
	);

	pg_assert_same(4, ws_mail_route_pick($routes, array('support+billing@shop.test'), 'Support@Shop.test'), 'a tag of the box\'s address');
	pg_assert_same(0, ws_mail_route_pick($routes, array('other+billing@shop.test'), 'support@shop.test'), 'a tag of another address is not ours');
	pg_assert_same(6, ws_mail_route_pick($routes, array('x@example.com', 'sales@shop.test', 'support+sales@shop.test'), 'support@shop.test'), 'routes in order: the address rule comes first');
	pg_assert_same(7, ws_mail_route_pick($routes, array('support+sales@shop.test'), 'support@shop.test'));
	pg_assert_same(0, ws_mail_route_pick($routes, array('support@shop.test'), 'support@shop.test'), 'the plain address: the box\'s channel');
	pg_assert_same(0, ws_mail_route_pick(array(), array('support+billing@shop.test'), 'support@shop.test'), 'no routes');
	pg_assert_same(0, ws_mail_route_pick($routes, array('+billing@'), ''), 'a box without an address matches no tag');
}

function test_workspace_mail_routes_input()
{
	$ok = ws_mail_routes_input(array(' +Billing ', '', 'Sales@Shop.test'), array('4', '', '6'), array(4, 6), array());

	pg_assert_true($ok['ok']);
	pg_assert_same(array(array('match' => '+billing', 'channel_id' => 4), array('match' => 'sales@shop.test', 'channel_id' => 6)), $ok['routes'], 'empty rows skipped');

	pg_assert_same(lang('A route is an address such as billing@example.com or a tag of the mailbox\'s address such as +billing.'),
		ws_mail_routes_input(array('billing'), array(4), array(4), array())['error']);
	pg_assert_same(lang('Choose the channel for each route.'), ws_mail_routes_input(array('+billing'), array(''), array(4), array())['error'], 'no channel');
	pg_assert_same(lang('Choose the channel for each route.'), ws_mail_routes_input(array('+billing'), array(9), array(4), array())['error'], 'a channel the editor may not choose');
	pg_assert_true(ws_mail_routes_input(array('+billing'), array(9), array(4), array(9))['ok'], 'a channel the route already had stays');
	pg_assert_same(lang(array('string' => '{var:1} has two routes.', 'vars' => '+billing')), ws_mail_routes_input(array('+billing', '+BILLING'), array(4, 6), array(4, 6), array())['error']);
	pg_assert_same(lang(array('string' => 'A mailbox has at most {var:1} routes.', 'vars' => WS_MAIL_ROUTES_MAX)),
		ws_mail_routes_input(array_map(function ($i) {
			return '+t' . $i;
		}, range(1, WS_MAIL_ROUTES_MAX + 1)), array_fill(0, WS_MAIL_ROUTES_MAX + 1, 4), array(4), array())['error']);
	pg_assert_true(ws_mail_routes_input(array(array('x')), array(array(4)), array(4), array())['ok'], 'a posted array is an empty row, not a warning');
}

function test_workspace_mail_route_fields()
{
	$box = array('settings' => json_encode(array('routes' => array(array('match' => '+billing', 'channel_id' => 9)))));
	$html = ws_mail_route_fields('ws_mail_3_', $box, array(4 => array('id' => 4, 'name' => 'support')));

	pg_assert_contains('name="route_match[]" id="ws_mail_3_route_0" maxlength="255" value="+billing"', $html);
	pg_assert_contains('<option value="9" selected>' . h(lang('A channel you do not see')) . '</option>', $html, 'a channel the editor does not read stays chosen');
	pg_assert_same(4, substr_count($html, 'name="route_match[]"'), 'the kept route and three empty rows');
	pg_assert_contains('id="ws_mail_3_route_3"', $html);

	$form = ws_mail_box_form('workspace_settings.php', null, array());
	pg_assert_contains('name="route_match[]"', $form, 'on the box form');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("\$settings['routes'] = \$routes['routes'];", $source, 'kept in the box\'s settings');
}

function test_workspace_mail_customer_line_text()
{
	$text = ws_mail_customer_line_text('Ayşe <@user:1>', 12, "Order\n<#task:5> late");

	pg_assert_same(lang(array('string' => 'An e-mail from {var:1} came into {var:2}: {var:3}', 'vars' => array('Ayşe @user:1', '<#channel:12>', 'Order #task:5 late'))), $text,
		'the channel as a tag; tags a sender typed name nobody');
	pg_assert_contains(lang('(no subject)'), ws_mail_customer_line_text('a@example.com', 12, ' '));
}

function test_workspace_mail_routing_wiring()
{
	$ingest = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail.php');
	$route = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_route.php');

	pg_assert_contains("\$channel = \$conversation ? ws_channel((int) \$conversation['channel_id']) : ws_mail_route_channel(\$box, \$parsed);", $ingest, 'only a new conversation is routed');
	pg_assert_contains('ws_mail_customer_lines(array(', $ingest, 'a known customer\'s channels hear of it');
	pg_assert_true(strpos($ingest, 'ws_mail_customer_lines(') > strpos($ingest, "\$contact_id = (\$auth === 'pass')"), 'only a verified sender is tied to a customer');
	pg_assert_contains("!in_array((string) \$channel['kind'], array('public', 'private'), true)", $route, 'never a guest room or a discussion');
}
