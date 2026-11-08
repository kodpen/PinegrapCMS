<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of tools/free_work.php: which lines of a plan file
 * are steps, when a step is done, which open PR holds it, and which plans
 * are shelved.
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

function test_free_work_steps_nested_and_continued_lines()
{
	$text = "## Which steps are there?\n- A3 (M, schema) Registry. After A2.\n  - A3 `done` (#8038): tables.\n  - A3b (M) after A3: the picker.\n"
		. "- V (visual pass). One step per screen group.\n  - Every V step: the screen beside its picture.\n  - V3 (M) pictures 05-11.\n\n"
		. "2. TD2 (L) After TD1. Browser core.\n   Cut in two: TD2a (#6246) the core; TD2b done (#6304): the Pages list.\n\n"
		. "6. TD14 (M) After every other step. Cut in two: TD14a (#7392); TD14b (open).\n";
	$steps = free_work_steps($text);

	pg_assert_same(array('A3', 'A3b', 'V3', 'TD2', 'TD14'), array_column($steps, 0));
	pg_assert_true(free_work_step_done($steps[0][1]));
	pg_assert_false(free_work_step_done($steps[1][1]));
	pg_assert_true(free_work_step_done($steps[3][1]));
	pg_assert_false(free_work_step_done($steps[4][1]));
}

function test_free_work_steps_done_list_and_word()
{
	$text = "## Which steps?\n- Done: N1 #4692, V2 #6263, N10d #6464.\n- N10d (S) Week card.\n- N15 (S) New card.\n";

	pg_assert_same(array('N15'), array_column(free_work_steps($text), 0));
	pg_assert_true(free_work_step_done('(M) After TD9. Cut in two: TD10a (#6584); TD10b (#6616). TD10 done. Commerce lists.'));
}

function test_free_work_steps_next_part_of_a_several_pr_step()
{
	$text = "## Which steps?\n- D4 (S) Lazy checks. Done (#8091).\n- D5 (M, several PRs) Parts. One PR per part. Done: part 1 (#8112), part 2 (#8131). Next: part 3, the code view, then fonts.\n";
	$steps = free_work_steps($text);

	pg_assert_same(array('D4', 'D5 part 3'), array_column($steps, 0));
	pg_assert_false(free_work_step_done($steps[1][1]));
}

function test_free_work_steps_skip_a_section_beside_another_plan()
{
	$text = "## How does it fit beside designer-performance-and-publish?\n- D5 (parts) keeps the family files bundled.\n\n## Launch\n- L1 (M) Done (#8052).\n- L4 (M) More.\n";

	pg_assert_same(array('L1', 'L4'), array_column(free_work_steps($text), 0));
}

function test_free_work_step_blocked_owner_work_and_free_marks()
{
	pg_assert_true(free_work_step_blocked("(S, `.github/`, owner's machine) Release packages.", ''));
	pg_assert_true(free_work_step_blocked("(S, the owner's public keys) Keys.", ''));
	pg_assert_false(free_work_step_blocked('(M) Risk tiers. After A4.', ''));

	$plan = "- S under 400. The holder marks lines `free`.\n";
	pg_assert_false(free_work_step_blocked('(M) Demux and decode. Part `video`. `free`.', $plan));
	pg_assert_true(free_work_step_blocked('(M) The video layer.', $plan));
}

function test_free_work_sequential_plans()
{
	pg_assert_true(free_work_sequential("## Steps (claim as `[plan:x] <n> — <topic>`; each waits for the one before it, Working mode 1)\n"));
	pg_assert_false(free_work_sequential("## Which steps are there?\n- All eight run side by side.\n"));
}

function test_free_work_own_looks_next_step_per_template()
{
	$text = "## How does a template's polishing go?\n- Step `<id>-1`: its blocks. Step `<id>-2`: home rebuilt on it.\n\n"
		. "## In which order, and why?\n0. G1, G2 (after L1): general blocks. Done in one PR (#8082).\n"
		. "1. Built on the shared builders (`\$story_band`, `\$framed`): they look alike. Most builders first: wedding, photo, school.\n"
		. "4. Templates that already have a family: news, magazine (one step each: motion), forum.\n"
		. "- Wedding: `-1` ([[designer-blocks-wedding]], #8167) done.\n\n"
		. "## Where do the open template plans come?\n- Skiresort: `skiresort-1` (its family) done (#8050); it needs no `-2`.\n"
		. "- Coworking: `-1` ([[a]]) and `-2` ([[b]]) done (#8113, #8127).\n"
		. "- Pharmacy: `-1` ([[designer-blocks-pharmacy]], #8160) done; `-2` places it on home.\n";

	pg_assert_same(array(
		array('wedding-2', true),
		array('pharmacy-2', true),
		array('photo-1', false),
		array('school-1', false),
		array('news-1', false),
		array('magazine-1', false),
		array('forum-1', false),
	), free_work_own_looks($text));
	pg_assert_same(array(), free_work_own_looks("## Which steps are there?\n- A1 (S) One.\n"));
}
