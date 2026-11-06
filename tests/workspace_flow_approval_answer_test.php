<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the answer an approval box asks of the people it asks
 * (includes/workspace/steps.php, approvals.php, flow_runs.php): its
 * settings, the values it gives, the check of an answer before it is
 * written, how a number is read from it and whose answer the box keeps.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/calc.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/approvals.php');

// The answer settings are kept, anything else is none.
function test_workspace_flow_approval_answer_config()
{
	$plain = ws_step_config_normalize('approval', array('channel_id' => 3, 'title' => 'Delivery'));

	pg_assert_same('none', $plain['answer']);
	pg_assert_same('', $plain['answer_label']);

	$number = ws_step_config_normalize('approval', array('answer' => 'number', 'answer_label' => "  How many kg\narrived? "));

	pg_assert_same('number', $number['answer']);
	pg_assert_same('How many kg arrived?', $number['answer_label']);
	pg_assert_same('text', ws_step_config_normalize('approval', array('answer' => 'text'))['answer']);
	pg_assert_same('none', ws_step_config_normalize('approval', array('answer' => 'html'))['answer']);
	pg_assert_same(120, mb_strlen(ws_step_config_normalize('approval', array('answer_label' => str_repeat('a', 300)))['answer_label']));
}

// A box with no answer gives what it gave; one with an answer adds three values.
function test_workspace_flow_approval_answer_outputs()
{
	$plain = array_column(ws_step_outputs('approval', ws_step_config_normalize('approval', array())), 'type', 'key');

	pg_assert_same(array('approval_id' => 'number', 'message_id' => 'number'), $plain);

	$asked = array_column(ws_step_outputs('approval', ws_step_config_normalize('approval', array('answer' => 'text'))), 'type', 'key');

	pg_assert_same('text', $asked['answer']);
	pg_assert_same('number', $asked['answer_number']);
	pg_assert_same('number', $asked['decided_by']);
}

// The number of an answer keeps its decimals in the box's values.
function test_workspace_flow_approval_answer_result_data()
{
	$config = ws_step_config_normalize('approval', array('answer' => 'number'));
	$data = ws_step_result_data('approval', $config, array('approval_id' => 4, 'data' => array('answer' => '27,5', 'answer_number' => '27.5', 'decided_by' => 9)));

	pg_assert_same('27,5', $data['answer']);
	pg_assert_same('27.5', $data['answer_number']);
	pg_assert_same(9, $data['decided_by']);

	$empty = ws_step_result_data('approval', $config, array('approval_id' => 4, 'data' => array('answer' => 'late', 'answer_number' => '', 'decided_by' => 9)));

	pg_assert_same(null, $empty['answer_number'], 'an unreadable number is empty');
}

// A number is read as the calculator reads it, else it is empty.
function test_workspace_flow_approval_answer_number()
{
	pg_assert_same('27', ws_approval_answer_number('27'));
	pg_assert_same('27.5', ws_approval_answer_number('27.5'));
	pg_assert_same('30', ws_approval_answer_number('25 + 5'));
	pg_assert_same('', ws_approval_answer_number('about twenty'));
	pg_assert_same('', ws_approval_answer_number(''));
	pg_assert_same('', ws_approval_answer_number('1/0'));
	pg_assert_same('', ws_approval_answer_number('A1'), 'a cell is no number here');
}

// An approval asks for its answer; a refusal and a box with none do not.
function test_workspace_flow_approval_answer_problem()
{
	pg_assert_same('', ws_approval_answer_problem('none', 'approve', ''));
	pg_assert_same('', ws_approval_answer_problem('text', 'reject', ''));
	pg_assert_same('', ws_approval_answer_problem('text', 'approve', 'Arrived'));
	pg_assert_same('', ws_approval_answer_problem('number', 'approve', '27'));
	pg_assert_true(ws_approval_answer_problem('text', 'approve', '   ') !== '', 'the answer is required');
	pg_assert_true(ws_approval_answer_problem('number', 'approve', '') !== '');
	pg_assert_true(ws_approval_answer_problem('number', 'approve', 'about twenty') !== '', 'a number must be readable');
}

// The box keeps the last decider's answer (rule all), and who gave it.
function test_workspace_flow_approval_answer_values()
{
	$people = array(
		array('user_id' => 5, 'decision' => 'approved', 'note' => '25', 'decided_at' => 100),
		array('user_id' => 7, 'decision' => 'approved', 'note' => '27', 'decided_at' => 200),
		array('user_id' => 9, 'decision' => 'pending', 'note' => '', 'decided_at' => 0),
	);

	pg_assert_same(array('answer' => '27', 'answer_number' => '27', 'decided_by' => 7), ws_approval_answer_values($people));
	pg_assert_same(array('answer' => null, 'answer_number' => null, 'decided_by' => null), ws_approval_answer_values(array()));
}

// The panel, the API and its dry run check the answer in the shared check.
function test_workspace_flow_approval_answer_checked_once()
{
	$function = new ReflectionFunction('ws_approval_decide_check');

	pg_assert_same(4, $function->getNumberOfParameters(), 'the check takes the answer');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_resources.php');

	pg_assert_true(strpos($source, "ws_approval_decide_check(\$viewer, \$approval, (string) \$params['decision'], (string) (\$params['note'] ?? ''))") !== false,
		'the API checks the answer before its dry run stops');
}
