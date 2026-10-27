<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for filling a flow box's texts in one pass: a {{node.*}} value
 * (ws_step_fill_nodes()) or a scheduled placeholder's value
 * (ws_scheduled_fill()) is never read again as a placeholder, so a value a
 * form visitor typed or a webhook returned cannot pull in other data.
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

// Both fillers in the order a box runs them.
function ws_flow_fill_test_both($text, $values, $context)
{
	return ws_scheduled_fill(ws_step_fill_nodes($text, $values), $context);
}

// A box's value holding placeholders stays as written.
function test_workspace_flow_fill_node_value_stays_literal()
{
	$values = array('form' => array('text' => 'Hi {{customer}} {{form_fields}} {{date}} {{node.form.secret}} {{count:tasks_open}}', 'secret' => 'hidden'));
	$filled = ws_flow_fill_test_both('Said: {{node.form.text}}', $values, array('row' => array('name' => 'Flow')));

	pg_assert_true(strpos($filled, 'hidden') === false, 'a node value does not pull in another node value');
	pg_assert_true(strpos($filled, date('d.m.Y')) === false, 'a node value does not pull in the date');
	pg_assert_same(1, preg_match('/^Said: Hi \{.?\{customer\}\} \{.?\{form_fields\}\} \{.?\{date\}\} \{.?\{node\.form\.secret\}\} \{.?\{count:tasks_open\}\}$/u', $filled), 'the braces stay readable: ' . $filled);

	// A brace at either end of a value cannot meet one of the text.
	$values = array('a' => array('text' => '{', 'end' => 'x{'));
	pg_assert_same(0, preg_match('/\{\{date\}\}/', ws_step_fill_nodes('{{node.a.end}}{date}}', $values)), 'a trailing brace');
	pg_assert_same(0, preg_match('/\{\{date\}\}/', ws_step_fill_nodes('{{{node.a.text}}date}}', $values)), 'a lone brace');
}

// A scheduled placeholder's value holding placeholders stays as written.
function test_workspace_flow_fill_scheduled_value_stays_literal()
{
	$context = array('row' => array('name' => '{{date}}'), 'result' => '{{count:tasks_open}} {{node.x.y}}', 'source' => '{{run}}');

	pg_assert_same('{{date}} / {{count:tasks_open}} {{node.x.y}} / {{run}}', ws_scheduled_fill('{{name}} / {{result}} / {{source}}', $context));
	pg_assert_same('{{count:tasks_open}} {{node.x.y}}', ws_flow_fill_test_both('{{result}}', array('x' => array('y' => 'leak')), $context));
}

// Values without braces fill as before.
function test_workspace_flow_fill_normal_values_unchanged()
{
	$values = array('say' => array('text' => 'written in #sales', 'message_id' => '42'));
	$context = array('row' => array('name' => 'Daily', 'run_count' => 4), 'result' => 'ok');

	pg_assert_same('written in #sales (42) Daily 5 ok ' . date('d.m.Y') . ' ', ws_flow_fill_test_both('{{node.say.text}} ({{node.say.message_id}}) {{name}} {{run}} {{result}} {{date}} {{count:tasks_open}}', $values, $context));
	pg_assert_same('a } b { c', ws_step_fill_nodes('{{node.say.x}}a } b { c', $values));
}
