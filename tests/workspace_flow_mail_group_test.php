<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a flow's e-mail box written to a contact group
 * (includes/workspace/flow_mail_campaign.php): the recipient "group" with its
 * group and the records box it draws, sent as one e-mail campaign through
 * the campaigns' own path, so opt-in, bounced addresses, İYS and the
 * unsubscribe footer are the campaign's; nothing sent when the records box
 * read none; the body drawn with every text escaped; Try mailing only the
 * person trying, as a campaign's test, and moving no window.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// A function's source without its comment lines.
function workspace_flow_mail_group_test_source($function)
{
	return (string) preg_replace('~^\s*(//|\*|/\*\*).*$~m', '', pg_test_function_source($function));
}

// The recipient "group" keeps its group and its records box; the other
// recipients keep neither, so a box drawn before is saved as it was.
function test_workspace_flow_mail_group_settings()
{
	$group = ws_step_config_normalize('email', array('recipient' => 'group', 'group_id' => '7', 'records' => 'read', 'subject' => 'S', 'body' => 'B', 'purpose' => 'marketing'));

	pg_assert_same('group', $group['recipient']);
	pg_assert_same(7, $group['group_id']);
	pg_assert_same('read', $group['records']);

	$typed = ws_step_config_normalize('email', array('to' => 'a@example.com', 'group_id' => 7, 'records' => 'read'));
	pg_assert_same('typed', $typed['recipient']);
	pg_assert_same(0, $typed['group_id']);
	pg_assert_same('', $typed['records']);

	pg_assert_same('', ws_step_config_normalize('email', array('recipient' => 'group', 'records' => 'Not an id!'))['records']);
	pg_assert_same('typed', ws_step_config_normalize('email', array('recipient' => 'everybody'))['recipient']);
}

// What it lacks: the group; a page is not sent to a group; records only in
// a group e-mail, from a records box of the same flow.
function test_workspace_flow_mail_group_problems()
{
	$ok = ws_step_config_normalize('email', array('recipient' => 'group', 'group_id' => 7, 'subject' => 'S', 'body' => 'B'));
	pg_assert_same(array(), ws_step_config_problems('email', $ok, 'Mail'), 'no address needed');

	pg_assert_true(count(ws_flow_mail_group_problems(array('recipient' => 'group', 'group_id' => 0), 'Mail')) === 1, 'the group');
	pg_assert_true(count(ws_flow_mail_group_problems(array('recipient' => 'group', 'group_id' => 7, 'page_id' => 4), 'Mail')) === 1, 'a page');
	pg_assert_true(count(ws_flow_mail_group_problems(array('recipient' => 'typed', 'records' => 'read'), 'Mail')) === 1, 'records only to a group');

	$graph = array('nodes' => array(array('id' => 'read', 'kind' => 'list_records'), array('id' => 'other', 'kind' => 'rows_get')));
	pg_assert_same(array(), ws_flow_mail_records_box_problems(array('records' => 'read'), 'Mail', $graph));
	pg_assert_same(array(), ws_flow_mail_records_box_problems(array('records' => ''), 'Mail', $graph));
	pg_assert_true(count(ws_flow_mail_records_box_problems(array('records' => 'other'), 'Mail', $graph)) === 1, 'not a records box');
	pg_assert_true(count(ws_flow_mail_records_box_problems(array('records' => 'gone'), 'Mail', $graph)) === 1, 'not in the flow');
	pg_assert_contains('ws_flow_mail_records_box_problems(', workspace_flow_mail_group_test_source('ws_flow_graph_check'));

	// A role-3 owner is refused before any read.
	pg_assert_true(ws_flow_mail_group_refusal_for(array('id' => 5, 'role' => 3), array('group_id' => 7)) !== '', 'role 3');
	pg_assert_contains('ws_flow_mail_group_refusal_for(', workspace_flow_mail_group_test_source('ws_step_check'), 'checked at publish and run');
}

// A group send is one campaign, made through the campaigns' own path with
// the group included: one campaign row and its recipient rows, sent now.
function test_workspace_flow_mail_group_one_campaign()
{
	$do = workspace_flow_mail_group_test_source('ws_flow_mail_group_do');

	pg_assert_contains('pg_email_campaign_pick_recipients($owner, ws_flow_mail_group_purpose($config), array((int) $config[\'group_id\'] => \'included\'), \'\')', $do);
	pg_assert_same(1, substr_count($do, 'pg_email_campaign_insert('), 'one campaign');
	pg_assert_false(strpos($do, 'email(') !== false && strpos($do, 'pg_email_campaign_insert(') === false, 'never a direct mail');
	pg_assert_false(strpos($do, 'ws_scheduled_mail(') !== false, 'not the queue of single mails');
	pg_assert_contains('ws_flow_mail_group_do(', workspace_flow_mail_group_test_source('ws_step_run'));

	// The campaigns' create path is not loaded by functions.php.
	foreach (array('ws_flow_mail_group_do', 'ws_flow_mail_group_try', 'ws_flow_mail_group_campaign') as $function) {
		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_create.php');", workspace_flow_mail_group_test_source($function), $function);
	}

	$campaign = workspace_flow_mail_group_test_source('ws_flow_mail_group_campaign');
	pg_assert_contains("'status'              => 'ready'", $campaign);
	pg_assert_contains("'1000-01-01 00:00:00'", $campaign, 'sent now');
	pg_assert_contains('pg_email_campaign_prepare_html_body(', $campaign, 'the campaigns\' footer and links');
}

// Opt-in: a marketing box is a commercial campaign, whose recipients are
// opted-in contacts only. Bounced addresses: the campaign's send skips a
// suppressed address.
function test_workspace_flow_mail_group_opt_in_and_bounces()
{
	pg_assert_same('commercial', ws_flow_mail_group_purpose(array('purpose' => 'marketing')));
	pg_assert_same('transactional', ws_flow_mail_group_purpose(array('purpose' => 'info')));
	pg_assert_true(pg_email_campaign_opt_in_required('commercial'));
	pg_assert_contains(pg_email_campaign_opt_in_condition(true), pg_email_campaign_recipient_query('commercial', true), 'opted in only');

	$send = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/email_campaign_send.php');
	pg_assert_contains('function pg_email_campaign_skip_suppressed(', $send);
	pg_assert_contains('pg_mail_address_blocked(', $send, 'a bounced address is held back by the campaign');
}

// No mail when the records box read none: the box ends before any
// recipient is picked or campaign made.
function test_workspace_flow_mail_group_no_records()
{
	pg_assert_same(array('box' => true, 'count' => 0, 'items' => array()), ws_flow_mail_group_records(array('records' => 'read'), array('read' => array('records' => '[]', 'count' => '0'))));
	pg_assert_same(array('box' => false, 'count' => 0, 'items' => array()), ws_flow_mail_group_records(array('records' => ''), array()));
	pg_assert_same(1, ws_flow_mail_group_records(array('records' => 'read'), array('read' => array('records' => '[{"title":"A"}]')))['count']);

	$do = workspace_flow_mail_group_test_source('ws_flow_mail_group_do');
	$stop = strpos($do, "\$records['count'] === 0");
	$pick = strpos($do, 'pg_email_campaign_pick_recipients(');
	pg_assert_true(($stop !== false) && ($pick !== false) && ($stop < $pick), 'stops before picking anyone');
}

// The body: the box's text, then each record with its picture, linked
// title and summary; every text escaped, a link or picture only http(s).
function test_workspace_flow_mail_group_body()
{
	$items = ws_flow_mail_digest_items(json_encode(array(
		array('title' => 'News <b>1</b>', 'summary' => 'A & B', 'picture' => 'https://example.com/a.jpg', 'link' => 'https://example.com/a'),
		array('title' => 'Two', 'summary' => '', 'picture' => 'javascript:alert(1)', 'link' => 'javascript:alert(1)'),
		'not a record',
	)));

	pg_assert_same(2, count($items));
	pg_assert_same('', $items[1]['picture']);
	pg_assert_same('', $items[1]['link']);

	$html = ws_flow_mail_digest_html('Week <1>', "Hello <there>\n\nSecond", $items);
	pg_assert_contains('<title>Week &lt;1&gt;</title>', $html);
	pg_assert_contains('Hello &lt;there&gt;', $html);
	pg_assert_contains('News &lt;b&gt;1&lt;/b&gt;', $html);
	pg_assert_contains('A &amp; B', $html);
	pg_assert_contains('<a href="https://example.com/a"', $html);
	pg_assert_contains('src="https://example.com/a.jpg"', $html);
	pg_assert_false(strpos($html, 'javascript:') !== false, 'no script address');
	pg_assert_same(1, substr_count($html, '<img '), 'a record with no picture draws none');
	pg_assert_contains('</body>', $html, 'a whole document, for the campaign footer');
}

// Try mails the person trying alone, as a campaign's test with its hourly
// limit; it makes no campaign and writes nothing, so no window moves.
function test_workspace_flow_mail_group_try()
{
	$try = workspace_flow_mail_group_test_source('ws_flow_mail_group_try');

	pg_assert_contains('pg_campaign_test_send($campaign, array($to)', $try);
	pg_assert_contains('waf_rate_exceeded(', $try);
	pg_assert_false(strpos($try, 'pg_email_campaign_insert(') !== false, 'no campaign');

	foreach (array('ws_flow_mail_group_try', 'ws_flow_mail_group_try_records', 'ws_flow_records_window_try', 'ws_flow_records_read', 'ws_flow_records_earlier') as $function) {
		$source = workspace_flow_mail_group_test_source($function);
		pg_assert_false((bool) preg_match('/\b(INSERT|UPDATE|DELETE)\b/', $source), $function . ' writes nothing');
	}

	pg_assert_contains('ws_flow_mail_group_try(', workspace_flow_mail_group_test_source('ws_flow_box_try'));
}
