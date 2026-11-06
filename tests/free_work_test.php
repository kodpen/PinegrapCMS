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

function test_free_work_steps_with_numbered_ids()
{
	$text = "## Which steps are there?\n- 1 Done (#7468). Data: `parts.php`.\n\n- 5 Volunteers: `volunteer.php`.\n\n"
		. "- 20-25, 390x844 (E1b): the phone layout.\n- 2026-10-19: open this later.\n\n"
		. "## Steps (claim as `[plan:x] <n> — <topic>`; each waits for the one before it)\n- 3 (done, #7501): `tours.php`.\n\n"
		. "- 4: `mail.php` and `bookings.php`; test.\n- E20 (S) Close-out.\n- **12a** Bold one.\n\n"
		. "## What is there already?\n- 22 start events: orders.\n";
	$steps = free_work_steps($text);

	pg_assert_same(array(
		array('1', 'Done (#7468). Data: `parts.php`.'),
		array('5', 'Volunteers: `volunteer.php`.'),
		array('3', '(done, #7501): `tours.php`.'),
		array('4', '`mail.php` and `bookings.php`; test.'),
		array('E20', '(S) Close-out.'),
		array('12a', 'Bold one.'),
	), $steps);

	pg_assert_true(free_work_step_done($steps[0][1]));
	pg_assert_false(free_work_step_done($steps[1][1]));
	pg_assert_true(free_work_step_done($steps[2][1]));
	pg_assert_false(free_work_step_done($steps[3][1]));
}

function test_free_work_steps_numbered_ids_need_a_steps_heading()
{
	pg_assert_same(array(), free_work_steps("## What is left?\n- 409 panel files.\n- 201 with the order.\n"));
}

function test_free_work_holder_numbered_step()
{
	$titles = array(7540 => '[plan:design-template-charity] 5 — volunteers', 7541 => '[plan:design-template-travel] 4-5 — bookings and enquiries');

	pg_assert_same(7540, free_work_holder('design-template-charity', '5', $titles));
	pg_assert_same(0, free_work_holder('design-template-charity', '6', $titles));
	pg_assert_same(7541, free_work_holder('design-template-travel', '5', $titles));
	pg_assert_same(0, free_work_holder('design-template-travel', '7540', $titles));
	pg_assert_same(0, free_work_holder('design-template-gym', '8', array(7542 => '[plan:design-template-gym] 3 — trainers on Bootstrap 5.3.8')));
	pg_assert_same(7542, free_work_holder('design-template-gym', '3', array(7542 => '[plan:design-template-gym] 3 — trainers on Bootstrap 5.3.8')));
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
