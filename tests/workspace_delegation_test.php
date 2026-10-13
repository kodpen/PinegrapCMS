<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of a task handed to an assistant
 * (includes/workspace/delegation.php: the instruction as it is kept, the
 * request statuses a run is still under way in). Which assistants are
 * connected is read from the database: a sandbox check.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/delegation.php');

function test_delegation_instruction_is_one_trimmed_block()
{
	pg_assert_same("Check the stock\nand tell me", ws_delegation_instruction_clean("  Check the stock\r\nand tell me \r\n"));
	pg_assert_same('', ws_delegation_instruction_clean('   '));
	pg_assert_same('', ws_delegation_instruction_clean(null));
}

function test_delegation_instruction_is_cut_to_the_limit()
{
	$long = str_repeat('ş', WS_DELEGATION_INSTRUCTION_MAX + 50);
	$kept = ws_delegation_instruction_clean($long);

	pg_assert_same(WS_DELEGATION_INSTRUCTION_MAX, mb_strlen($kept), 'counted in characters, not bytes');
}

function test_delegation_open_statuses()
{
	pg_assert_true(ws_delegation_status_open('queued'));
	pg_assert_true(ws_delegation_status_open('sent'));
	pg_assert_true(ws_delegation_status_open('running'));
	pg_assert_false(ws_delegation_status_open('answered'));
	pg_assert_false(ws_delegation_status_open('failed'));
	pg_assert_false(ws_delegation_status_open('cancelled'));
	pg_assert_false(ws_delegation_status_open(''));
}
