<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Automatic sends (includes/fn/messages_automatic.php): the
 * flows whose boxes send a message, their state and sends, the ready recipes
 * that send one and the way into the quick setup.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php';
require_once PG_FUNCTIONS_DIR . '/includes/fn/messages_automatic.php';

function test_messages_automatic_send_kinds_are_flow_boxes()
{
	pg_assert_same(array('email', 'sms', 'whatsapp'), array_keys(pg_messages_send_kinds()), 'the three boxes that send a message, in the Channels order');

	$steps = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');

	foreach (array_keys(pg_messages_send_kinds()) as $kind) {
		pg_assert_true(preg_match("/'" . $kind . "'\\s*=> array\\('label'/", $steps) === 1, $kind . ' is a step kind of ws_step_kinds()');
	}
}

function test_messages_automatic_graph_sends_counts_send_boxes()
{
	$graph = array('nodes' => array(
		array('id' => 'start', 'kind' => 'event'),
		array('id' => 'a', 'kind' => 'whatsapp'),
		array('id' => 'b', 'kind' => 'delay'),
		array('id' => 'c', 'kind' => 'email'),
		array('id' => 'd', 'kind' => 'email'),
	));

	pg_assert_same(array('email' => 2, 'whatsapp' => 1), pg_messages_graph_sends($graph), 'counted per kind, in the kinds order');
	pg_assert_same(array(), pg_messages_graph_sends(array('nodes' => array(array('kind' => 'task_open')))), 'a flow that sends nothing');
	pg_assert_same(array(), pg_messages_graph_sends(array()), 'a graph without nodes');
	pg_assert_same(array(), pg_messages_graph_sends(array('nodes' => array('x', null))), 'nodes that are not boxes');
}

function test_messages_automatic_sends_words()
{
	pg_assert_same('', pg_messages_sends_words(array()), 'nothing to say');
	pg_assert_same('E-mail', pg_messages_sends_words(array('email' => 1)), 'one e-mail');
	pg_assert_same('3 e-mails', pg_messages_sends_words(array('email' => 3)), 'three e-mails');
	pg_assert_same('2 SMS messages', pg_messages_sends_words(array('sms' => 2)), 'two SMS');
	pg_assert_same('2 WhatsApp messages', pg_messages_sends_words(array('whatsapp' => 2)), 'two WhatsApp messages');
	pg_assert_same('E-mail, WhatsApp', pg_messages_sends_words(array('email' => 2, 'whatsapp' => 1)), 'two channels are named');
}

function test_messages_automatic_rows_keep_only_flows_that_send()
{
	$flows = array(
		array('id' => '1', 'name' => 'Tasks', 'status' => 'live'),
		array('id' => '2', 'name' => 'Draft welcome', 'status' => 'draft'),
		array('id' => '3', 'name' => 'Paused review', 'status' => 'paused'),
		array('id' => '4', 'name' => 'Thanks', 'status' => 'live'),
		array('id' => '5', 'name' => 'No graph', 'status' => 'live'),
	);
	$graphs = array(
		1 => array('nodes' => array(array('kind' => 'task_open'))),
		2 => array('nodes' => array(array('kind' => 'email'))),
		3 => array('nodes' => array(array('kind' => 'sms'))),
		4 => array('nodes' => array(array('kind' => 'whatsapp'), array('kind' => 'email'))),
	);

	$rows = pg_messages_automatic_rows($flows, $graphs);

	pg_assert_same(array(4, 3, 2), array_map(function ($row) {
		return (int) $row['flow']['id'];
	}, $rows), 'live first, then paused, then drafts; a flow that sends nothing is left out');
	pg_assert_same(array('email' => 1, 'whatsapp' => 1), $rows[0]['sends'], 'each row carries its sends');
}

function test_messages_automatic_status_pill()
{
	pg_assert_same(array('label' => 'Live', 'tone' => 'theme-success'), pg_messages_automatic_status('live'), 'live');
	pg_assert_same(array('label' => 'Paused', 'tone' => 'theme-warning'), pg_messages_automatic_status('paused'), 'paused');
	pg_assert_same(array('label' => 'Draft', 'tone' => ''), pg_messages_automatic_status('draft'), 'draft');
	pg_assert_same(array('label' => 'Draft', 'tone' => ''), pg_messages_automatic_status('other'), 'anything else reads as a draft');
}

function test_messages_automatic_recipes_that_send()
{
	$recipes = array(
		array('key' => 'mail', 'title' => 'Mail', 'icon' => 'bi-envelope', 'fits' => array('event:order.created'), 'steps' => array(array('kind' => 'email'), array('kind' => 'delay'))),
		array('key' => 'task', 'title' => 'Task', 'icon' => 'bi-check2', 'fits' => array('record'), 'steps' => array(array('kind' => 'task_open'))),
		array('key' => 'held', 'title' => 'Held', 'icon' => 'bi-x', 'fits' => array('event:form.submitted'), 'steps' => array(array('kind' => 'condition', 'steps' => array(array('kind' => 'sms'))))),
		array('key' => 'nostart', 'title' => 'No start', 'icon' => 'bi-x', 'fits' => array('event:gone'), 'steps' => array(array('kind' => 'email'))),
		array('key' => 'anyrecord', 'title' => 'Any record', 'icon' => 'bi-x', 'fits' => array('record'), 'steps' => array(array('kind' => 'whatsapp'))),
	);
	$starts = array('event:order.created' => array(), 'event:form.submitted' => array());

	$out = pg_messages_send_recipes($recipes, $starts);

	pg_assert_same(array('mail', 'held'), array_column($out, 'key'), 'recipes with a send step and a start the person may use; a held step counts');
	pg_assert_same('event:order.created', $out[0]['start'], 'the start the dialog opens on');
}

function test_messages_automatic_recipe_chain()
{
	$recipe = array('icon' => 'bi-bag-check', 'steps' => array(array('kind' => 'delay'), array('kind' => 'whatsapp'), array('kind' => 'unknown')));
	$chain = pg_messages_recipe_chain($recipe);

	pg_assert_same(array('bi-bag-check', 'teal'), array($chain[0]['icon'], $chain[0]['tone']), 'the start is the recipe icon on teal');
	pg_assert_same(array('bi-hourglass-split', 'gray'), array($chain[1]['icon'], $chain[1]['tone']), 'a wait');
	pg_assert_same(array('bi-whatsapp', 'teal'), array($chain[2]['icon'], $chain[2]['tone']), 'WhatsApp');
	pg_assert_same(array('bi-dot', 'gray'), array($chain[3]['icon'], $chain[3]['tone']), 'a kind without its own icon');
}

function test_messages_automatic_quick_address()
{
	pg_assert_same('workspace_flows.php?quick=1', pg_messages_quick_url(''), 'the quick setup on its first stage');
	pg_assert_same('workspace_flows.php?quick=thank_then_review', pg_messages_quick_url('thank_then_review'), 'a recipe');
	pg_assert_same('workspace_flows.php?quick=1', pg_messages_quick_url('a b<'), 'a key outside the token form opens the first stage');
}

function test_messages_automatic_today_figure()
{
	pg_assert_same('0', pg_messages_automatic_today(0, 1000), 'none');
	pg_assert_same('41', pg_messages_automatic_today(41, 1000), 'a count');
	pg_assert_same('999+', pg_messages_automatic_today(1000, 1000), 'at the cap');
}

function test_messages_automatic_tab_gate_admits_flow_builders()
{
	pg_assert_same(array('automatic'), pg_messages_tabs_for(3, false, true), 'a role 3 team member without the campaign right opens Automatic sends only');
	pg_assert_same(array('campaigns', 'automatic', 'templates', 'sends'), pg_messages_tabs_for(3, true, false), 'the campaign right keeps the tab (the profiles still run there)');
	pg_assert_same(array(), pg_messages_tabs_for(3, false, false), 'neither right, no tab');
}
