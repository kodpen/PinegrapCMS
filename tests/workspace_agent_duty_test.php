<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for an agent's duty text (includes/workspace/agents.php,
 * agent_instructions.php, agent_card.php): the save check's length and
 * cleaning, the row's shape, the "Your duty" part of the instructions and
 * the card's field.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// Line ends become \n, the ends are trimmed, and nothing else changes.
function test_workspace_agent_duty_clean()
{
	pg_assert_same('', ws_agent_duty_clean(null));
	pg_assert_same('', ws_agent_duty_clean("  \r\n "));
	pg_assert_same("Review the plans.\nAnswer in Turkish.", ws_agent_duty_clean("  Review the plans.\r\nAnswer in Turkish.\r\n"));
}

// The save check keeps a duty up to the limit and refuses a longer one.
function test_workspace_agent_duty_save_check()
{
	$app = array('kind' => 'app', 'handle' => 'worker-a', 'name' => 'Worker A', 'app_id' => 4, 'door_kind' => 'poll');

	$none = ws_agent_save_check($app, null, array(), array(), array(4));
	pg_assert_true($none['ok'], $none['error']);
	pg_assert_same('', $none['values']['duty'], 'no duty is allowed');

	$duty = ws_agent_save_check(array('duty' => " Keep the plans.\r\n") + $app, null, array(), array(), array(4));
	pg_assert_true($duty['ok'], $duty['error']);
	pg_assert_same('Keep the plans.', $duty['values']['duty']);

	$full = ws_agent_save_check(array('duty' => str_repeat('ğ', WS_AGENT_DUTY_MAX)) + $app, null, array(), array(), array(4));
	pg_assert_true($full['ok'], 'the limit counts characters, not bytes');

	$long = ws_agent_save_check(array('duty' => str_repeat('a', WS_AGENT_DUTY_MAX + 1)) + $app, null, array(), array(), array(4));
	pg_assert_false($long['ok'], 'a duty over the limit is refused');
	pg_assert_same('duty', $long['field']);
}

// The shaped row carries the duty; a site before the step reads it empty.
function test_workspace_agent_duty_shape()
{
	pg_assert_same('Keep the plans.', ws_agent_shape(array('id' => 1, 'duty' => 'Keep the plans.'))['duty']);
	pg_assert_same('', ws_agent_shape(array('id' => 1))['duty']);
}

// The instructions carry "Your duty" only when there is one, and their
// version changes with it.
function test_workspace_agent_duty_instructions()
{
	$agent = array('id' => 7, 'kind' => 'app', 'handle' => 'mimar', 'name' => 'Mimar', 'door_kind' => 'poll', 'door_url' => '');
	$base = 'https://shop.example/pinegrap/integration.php';
	$plain = ws_agent_instructions_text($agent, array(), $base);
	$with = ws_agent_instructions_text(array('duty' => "Keep the plans.\nAnswer in Turkish.") + $agent, array(), $base);

	pg_assert_false(strpos($plain, 'Your duty') !== false, 'no duty, no part');
	pg_assert_same($plain, ws_agent_instructions_text(array('duty' => "  \n") + $agent, array(), $base), 'a blank duty is none');
	pg_assert_contains("Your duty, as the person who set you up wrote it:\nKeep the plans.\nAnswer in Turkish.", $with);
	pg_assert_true(strpos($with, 'Your duty') < strpos($with, 'Site API:'), 'the duty comes with who the agent is, before the routes');
	pg_assert_true(ws_agent_instructions_version($plain) !== ws_agent_instructions_version($with), 'the version changes with the duty');
}

// The card's texts name the duty.
function test_workspace_agent_duty_card_string()
{
	pg_assert_true(isset(ws_agent_card_js_strings()['ac_duty']), 'the card labels the duty');
}
