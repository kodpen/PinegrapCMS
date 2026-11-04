<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of tools/free_work.php: which lines of a plan file
 * are steps, when a step is done, which open PR holds it, which issues a PR
 * body fixes, and which plans are shelved.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The tool returns before its command line part when PG_TEST_RUNNER is set.
require_once(dirname(__DIR__) . '/tools/free_work.php');

function test_free_work_plan_name_from_claim_or_file_name()
{
	pg_assert_same('appointment-booking', free_work_plan_name('docs/memory/plans/appointment-booking-steps-2.md', 'Steps BK6-BK10 of plans/appointment-booking.md; claim as [plan:appointment-booking] BK<n>'));
	pg_assert_same('table-driver', free_work_plan_name('docs/memory/plans/table-driver-steps.md', 'Steps of the table driver'));
	pg_assert_same('activitypub', free_work_plan_name('docs/memory/plans/activitypub.md', 'Plan (owner 2026-11-02): the site as a Fediverse actor'));
}

function test_free_work_steps_skip_order_sections()
{
	$text = "---\nname: x-steps\n---\n\n## Which run side by side?\n- AP1 alone, then AP2 and AP3 together.\n\n"
		. "## Which steps are there?\n- AP1 (M) Actor. `done` (#6378).\n\n- **AP2** (M) Inbox.\n  - detail line\n\n"
		. "## In what order?\n- AP2 first.\n";
	$steps = free_work_steps($text);

	pg_assert_same(array(array('AP1', '(M) Actor. `done` (#6378).'), array('AP2', '(M) Inbox.')), $steps);
}

function test_free_work_steps_without_a_steps_heading()
{
	$text = "## What is left?\n- X2 Returns read and moves: PR #5925.\n- X4 Users read.\n- X1a, X1b: PRs #1, #2.\n- GIF89a : one palette.\n";
	$steps = free_work_steps($text);

	pg_assert_same(array('X2', 'X4'), array_column($steps, 0));
}

function test_free_work_step_done_forms()
{
	foreach (array('(M) Actor. `done` (#6378).', '(S) API. After BK3. Done in #6377.', '(M) Keyframes. Done (#6290).', '(M) Model. Done in PR #6156.', 'Returns read: PR #5925.', 'zones: PRs #5377, #5491.', 'to M4 are done; what is left') as $line) {
		pg_assert_true(free_work_step_done($line), $line);
	}

	foreach (array('(M) Replies as comments. After AP2 and AP3.', 'Users write (#5237): `free` once X4 has landed.', '(S) Close-out. After PX1-PX7.') as $line) {
		pg_assert_false(free_work_step_done($line), $line);
	}
}

function test_free_work_holder_matches_plan_and_whole_id()
{
	$titles = array(
		6465 => '[plan:activitypub] AP3 — publishing and the outbox queue',
		6224 => '[plan:appointment-booking] BK4 — booking widget (1/2)',
		6307 => '[plan:audiences] AU4b — rows the recompute cannot reach',
	);

	pg_assert_same(6465, free_work_holder('activitypub', 'AP3', $titles));
	pg_assert_same(0, free_work_holder('activitypub', 'AP4', $titles));
	pg_assert_same(0, free_work_holder('appointment-booking', 'BK1', $titles));
	pg_assert_same(0, free_work_holder('audiences', 'AU4', $titles));
	pg_assert_same(6307, free_work_holder('audiences', 'AU4b', $titles));
	pg_assert_same(0, free_work_holder('store-credit', 'BK4', $titles));
}

function test_free_work_fixed_issues_forms()
{
	pg_assert_same(array(6392, 6393), free_work_fixed_issues("Fixes #6392, #6393.\n"));
	pg_assert_same(array(1, 2, 3), free_work_fixed_issues("Closes #1 and #2\nresolves: #3"));
	pg_assert_same(array(), free_work_fixed_issues('See #5 for the decision.'));
}

function test_free_work_shelved_by_description_or_file_name()
{
	pg_assert_true(free_work_shelved('DRAFT, not to be started (owner, 2026-10-29): a Workspace channel'));
	pg_assert_true(free_work_shelved('Plan (approved 2026-10-19, watch list): visitor-facing AI assistant'));
	pg_assert_true(free_work_shelved('Team-device API work', 'docs/memory/plans/mobile-api-shelved.md'));
	pg_assert_false(free_work_shelved('Plan (owner 2026-11-02): appointments with real time slots', 'docs/memory/plans/appointment-booking.md'));
}

function test_free_work_cut_keeps_short_lines_and_cuts_long_ones()
{
	pg_assert_same('short line', free_work_cut("short\n line"));
	pg_assert_same(str_repeat('ğ', 9) . '…', free_work_cut(str_repeat('ğ', 20), 10));
}
