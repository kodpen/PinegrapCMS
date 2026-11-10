<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the readiness check of a bulk change that writes a text for
 * each record (operation generate, includes/workspace/bulk.php): Pinegrap AI
 * writes those texts when the change is applied, so the check made when it
 * is proposed asks Pinegrap AI's own settings, even inside a model
 * assistant's request, whose config replaces them while it runs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bulk.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// The generate case of ws_bulk_input() refuses by ws_bulk_generate_ready(),
// with the sentence it always had.
function test_ws_bulk_generate_ready_is_what_the_proposal_asks()
{
	$input = pg_test_function_source('ws_bulk_input');
	$case = strpos($input, "case 'generate':");
	$ready = strpos($input, 'ws_bulk_generate_ready()', (int) $case);
	$sentence = strpos($input, 'Writing a text for each record needs Pinegrap AI, which is not ready on this site. Give the values one by one instead.', (int) $case);

	pg_assert_true(($case !== false) && ($ready !== false) && ($sentence !== false) && ($ready < $sentence), 'the generate case asks ws_bulk_generate_ready() before its refusal');
	pg_assert_true(strpos(substr($input, (int) $case, (int) $sentence - (int) $case), 'ws_ai_ready()') === false, 'not ws_ai_ready(), which reads a running assistant\'s config');
}

// While a model assistant runs, Pinegrap AI's own settings are read: the
// assistant is let go around ws_ai_missing() and held again after, and the
// static of ws_ai_ready() is neither read nor filled then.
function test_ws_bulk_generate_ready_reads_pinegrap_ai_own_settings()
{
	$code = pg_test_function_source('ws_bulk_generate_ready');
	$held = strpos($code, 'ws_model_running()');
	$let_go = strpos($code, 'ws_model_running(null)');
	$missing = strpos($code, 'ws_ai_missing()');
	$again = strpos($code, 'ws_model_running($running)');

	pg_assert_true(($held !== false) && ($let_go !== false) && ($missing !== false) && ($again !== false), 'the running assistant is read, let go and held again');
	pg_assert_true(($held < $let_go) && ($let_go < $missing) && ($missing < $again), 'ws_ai_missing() runs with no assistant set');

	$branch = substr($code, (int) $held, (int) $again - (int) $held);
	pg_assert_true(strpos($branch, 'ws_ai_ready(') === false, 'the static is left alone while an assistant runs');
	pg_assert_true(strpos(substr($code, (int) $again), 'ws_ai_ready()') !== false, 'with no assistant, ws_ai_ready() as before');
}
