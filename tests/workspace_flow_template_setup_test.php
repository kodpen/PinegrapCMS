<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for placing a flow template and its setup panel
 * (includes/workspace/flow_templates.php): the order and timing of the
 * placement (boxes one after another, arrows once both their boxes are in,
 * the data threads last), the answers of the setup panel checked as the
 * server checks them, and the answers written into the boxes the template
 * names, only where those boxes are still the template's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_conditions.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_anchors.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_templates.php');

function ws_flow_template_setup_test_templates($fill = array())
{
	return array_column(ws_flow_templates($fill + array(
		'me'       => 5,
		'channel'  => 0,
		'site'     => 'https://example.com/',
		'email'    => 'owner@example.com',
		'today'    => '2026-10-27',
		'metrics'  => array('tasks_overdue'),
		'recap_ai' => false,
	)), null, 'key');
}

function ws_flow_template_setup_test_node($graph, $id)
{
	foreach ($graph['nodes'] as $node) {
		if ($node['id'] === $id) {
			return $node;
		}
	}

	return null;
}

// Boxes come one after another, 150 ms apart, each in 420 ms; an arrow
// grows once both its boxes are in; the threads draw after the last arrow,
// 200 ms apart, each in 650 ms; then the panel.
function test_workspace_flow_template_motion_schedule()
{
	$uptime = ws_flow_template_setup_test_templates()['uptime'];
	$motion = $uptime['motion'];

	pg_assert_same(array('start', 'check', 'tell', 'mail'), array_column($motion['boxes'], 'id'), 'boxes in the graph\'s order');
	pg_assert_same(array(0, 150, 300, 450), array_column($motion['boxes'], 'at'));
	pg_assert_same(array(420, 420, 420, 420), array_column($motion['boxes'], 'ms'));

	// start→check once check is in (150 + 420), check→tell once tell is.
	pg_assert_same(array(0, 1, 2), array_column($motion['arrows'], 'edge'));
	pg_assert_same(array(570, 720, 870), array_column($motion['arrows'], 'at'));
	pg_assert_same(array(200, 200, 200), array_column($motion['arrows'], 'ms'));

	// Text of tell, subject and body of mail read the check's sentence.
	pg_assert_same(array(
		array('from' => 'check', 'to' => 'tell', 'key' => 'text', 'at' => 1070, 'ms' => 650),
		array('from' => 'check', 'to' => 'mail', 'key' => 'subject', 'at' => 1270, 'ms' => 650),
		array('from' => 'check', 'to' => 'mail', 'key' => 'body', 'at' => 1470, 'ms' => 650),
	), $motion['threads']);
	pg_assert_same(2120, $motion['panel'], 'the panel after the last thread');
	pg_assert_same(count($motion['threads']), $uptime['threads'], 'the card counts the threads drawn');
}

// Without threads the panel follows the last arrow; every template has one.
function test_workspace_flow_template_motion_without_threads()
{
	$templates = ws_flow_template_setup_test_templates();
	$motion = $templates['task_mail']['motion'];

	pg_assert_same(array(), $motion['threads']);
	pg_assert_same(570, $motion['arrows'][0]['at']);
	pg_assert_same(770, $motion['panel']);

	foreach ($templates as $key => $template) {
		pg_assert_same(count($template['graph']['nodes']), count($template['motion']['boxes']), $key . ' every box');
		pg_assert_same(count($template['graph']['edges']), count($template['motion']['arrows']), $key . ' every arrow');
	}

	// An empty graph places nothing and opens the panel at once.
	pg_assert_same(array('boxes' => array(), 'arrows' => array(), 'threads' => array(), 'panel' => 0), ws_flow_template_motion(ws_flow_graph_empty()));
}

// Each answer is checked by its kind and given back as the box keeps it.
function test_workspace_flow_template_answers_check()
{
	$uptime = ws_flow_template_setup_test_templates()['uptime'];
	$good = array('url' => ' https://shop.example.org/ ', 'person' => array('9', 9, 4), 'address' => 'A@example.org; b@example.org');
	$check = ws_flow_template_answers_check($uptime, $good);

	pg_assert_true($check['ok'], 'good answers pass');
	pg_assert_same(array('url' => 'https://shop.example.org/', 'person' => array(9, 4), 'address' => 'a@example.org, b@example.org'), $check['values']);

	$refused = function ($answers, $field) use ($uptime) {
		$check = ws_flow_template_answers_check($uptime, $answers);

		pg_assert_false($check['ok'], 'refused: ' . json_encode($answers));
		pg_assert_same($field, $check['field']);
		pg_assert_true($check['error'] !== '', 'a reason');
	};

	$refused(array('person' => array(9), 'address' => 'a@example.org'), 'url');
	$refused(array('url' => 'ftp://example.org/') + $good, 'url');
	$refused(array('url' => 'javascript:alert(1)') + $good, 'url');
	$refused(array('person' => array()) + $good, 'person');
	$refused(array('person' => array(0, -3)) + $good, 'person');
	$refused(array('person' => 'everyone') + $good, 'person');
	$refused(array('address' => 'not an address') + $good, 'address');
	$refused(array('address' => '') + $good, 'address');

	// A time of day and a channel.
	$monthly = ws_flow_template_setup_test_templates()['monthly'];
	pg_assert_true(ws_flow_template_answers_check($monthly, array('time' => '07:05', 'person' => array(5)))['ok'], 'time');
	pg_assert_same('07:05', ws_flow_template_answers_check($monthly, array('time' => '7:05', 'person' => array(5)))['values']['time'], 'an hour of one digit');

	foreach (array('24:00', '12:60', '1200', '', array('09:00')) as $time) {
		pg_assert_same('time', ws_flow_template_answers_check($monthly, array('time' => $time, 'person' => array(5)))['field'], 'time ' . json_encode($time));
	}

	$ssl = ws_flow_template_setup_test_templates(array('channel' => 3))['ssl'];
	pg_assert_same(array('time' => '09:00', 'url' => 'https://example.org/', 'channel' => 7),
		ws_flow_template_answers_check($ssl, array('time' => '09:00', 'url' => 'https://example.org/', 'channel' => '7'))['values']);
	pg_assert_same('channel', ws_flow_template_answers_check($ssl, array('time' => '09:00', 'url' => 'https://example.org/', 'channel' => 0))['field']);

	// Answers to settings the template does not have are not taken.
	pg_assert_false(isset(ws_flow_template_answers_check($monthly, array('time' => '07:05', 'person' => array(5), 'url' => 'x'))['values']['url']), 'only its own settings');
}

// The answers go into the boxes the template names, as long as they are
// still the template's boxes; the rest of the graph is left as it is.
function test_workspace_flow_template_apply()
{
	$uptime = ws_flow_template_setup_test_templates()['uptime'];
	$values = array('url' => 'https://shop.example.org/', 'person' => array(9, 4), 'address' => 'a@example.org');
	$graph = ws_flow_template_apply($uptime, $uptime['graph'], $values);

	pg_assert_same('https://shop.example.org/', ws_flow_template_setup_test_node($graph, 'check')['config']['url']);
	pg_assert_same(array(9, 4), ws_flow_template_setup_test_node($graph, 'tell')['config']['user_ids']);
	pg_assert_same('a@example.org', ws_flow_template_setup_test_node($graph, 'mail')['config']['to']);
	pg_assert_same(ws_flow_graph_normalize($graph), $graph, 'kept as the store keeps it');
	pg_assert_same(array(), array_column(ws_flow_graph_check($graph), 'text'), 'publishable');

	// A box removed or turned into another kind meanwhile is not written.
	$changed = $uptime['graph'];
	$changed['nodes'] = array_values(array_filter($changed['nodes'], function ($node) {
		return $node['id'] !== 'mail';
	}));

	foreach ($changed['nodes'] as $n => $node) {
		if ($node['id'] === 'tell') {
			$changed['nodes'][$n]['kind'] = 'email';
			$changed['nodes'][$n]['config'] = ws_flow_config_normalize('email', array('to' => 'x@example.org'));
		}
	}

	$changed = ws_flow_graph_normalize($changed);
	$graph = ws_flow_template_apply($uptime, $changed, $values);

	pg_assert_same('https://shop.example.org/', ws_flow_template_setup_test_node($graph, 'check')['config']['url']);
	pg_assert_same('x@example.org', ws_flow_template_setup_test_node($graph, 'tell')['config']['to'], 'another kind left alone');
	pg_assert_same(null, ws_flow_template_setup_test_node($graph, 'mail'), 'nothing added back');
}

// The panel action takes the answers through the server's check, and the
// canvas places with motion unless the person asks for less.
function test_workspace_flow_template_setup_reaches_the_canvas()
{
	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_flow.js');
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flow_templates.php');

	pg_assert_true(strpos($actions, "case 'ws_flow_template_setup':") !== false, 'panel action');
	pg_assert_true(strpos($panel, "'ws_flow_template_setup'") !== false, 'listed with the flow actions');
	pg_assert_true(strpos($source, 'ws_flow_template_answers_check(') !== false && strpos($source, 'ws_flow_publish(') !== false, 'checked, then published');
	pg_assert_true(strpos($script, 'prefers-reduced-motion') !== false, 'reduced motion places at once');
	pg_assert_true(strpos($script, "K.api('ws_flow_template_setup'") !== false, 'the setup panel sends its answers');

	$map = pg_test_language_map('tr');

	preg_match_all("/lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $source, $matches);

	foreach ($matches[1] as $text) {
		pg_assert_true(isset($map[stripslashes($text)]), 'translated: ' . stripslashes($text));
	}
}
