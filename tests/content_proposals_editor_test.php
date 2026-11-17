<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the editor's side of content changes that wait for approval
 * (includes/fn/content_proposals.php): what the Page Design Studio hands
 * the editor (their own waiting change, the answer and its reason), the
 * count of waiting changes for designers, withdrawing, and nobody else's
 * change reaching an editor.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/content_proposals.php');

// An editor's change: page 7, editor 5, waiting.
function _content_proposals_editor_row($over = array())
{
	return array_merge(array(
		'id'           => 1,
		'page_id'      => 7,
		'status'       => 'pending',
		'requested_by' => 5,
		'decided_by'   => 0,
		'decided_at'   => 0,
		'created_at'   => 1000,
		'steps'        => '{"lines":["text n1"]}',
		'error'        => '',
	), $over);
}

// A5, A6: the editor is handed their own waiting change of the page.
function test_content_proposals_studio_own_waiting_change()
{
	$rows = array(_content_proposals_editor_row(array('id' => 4)));
	$state = pg_content_proposals_studio_rows($rows, 5, 3, 2000);

	pg_assert_same(4, $state[7]['waiting'], 'the own waiting change');
	pg_assert_same(null, $state[7]['answer'], 'no answer yet');
	pg_assert_same(0, $state[7]['count'], 'no count for role 3');
}

// A6: nobody else's waiting change reaches an editor.
function test_content_proposals_studio_nobody_elses_change()
{
	$rows = array(
		_content_proposals_editor_row(array('id' => 4, 'requested_by' => 6)),
		_content_proposals_editor_row(array('id' => 8, 'requested_by' => 6, 'status' => 'dismissed', 'decided_by' => 1, 'decided_at' => 1500)),
	);

	pg_assert_same(array(), pg_content_proposals_studio_rows($rows, 5, 3, 2000), 'another editor\'s rows give a role 3 nothing');
	pg_assert_same(array(), pg_content_proposals_studio_rows($rows, 5, 2, 2000), 'nor a Manager');
}

// A7 (6485): roles 0 and 1 see how many changes wait on each page.
function test_content_proposals_studio_count_by_role()
{
	$rows = array(
		_content_proposals_editor_row(array('id' => 4, 'requested_by' => 6)),
		_content_proposals_editor_row(array('id' => 5, 'requested_by' => 9)),
		_content_proposals_editor_row(array('id' => 6, 'requested_by' => 9, 'page_id' => 8, 'status' => 'stale')),
		_content_proposals_editor_row(array('id' => 7, 'requested_by' => 9, 'page_id' => 8, 'status' => 'applied', 'decided_by' => 1, 'decided_at' => 1500)),
	);

	foreach (array(0, 1) as $role) {
		$state = pg_content_proposals_studio_rows($rows, 1, $role, 2000);
		pg_assert_same(2, $state[7]['count'], 'role ' . $role . ': two waiting');
		pg_assert_same(0, $state[7]['waiting'], 'role ' . $role . ': none of them theirs');
		pg_assert_false(isset($state[8]), 'role ' . $role . ': stale and applied changes are not counted');
	}

	pg_assert_same(array(), pg_content_proposals_studio_rows($rows, 1, 2, 2000), 'a Manager sees no count');
}

// 6485: the answer shown to the editor: applied, dismissed with its reason,
// or stale; the newest one not yet closed, for 30 days.
function test_content_proposals_studio_answers()
{
	$now = 100 * 86400;
	$applied = _content_proposals_editor_row(array('id' => 2, 'status' => 'applied', 'decided_by' => 1, 'decided_at' => $now - 60));
	$dismissed = _content_proposals_editor_row(array('id' => 3, 'status' => 'dismissed', 'decided_by' => 1, 'decided_at' => $now - 30, 'steps' => '{"reason":"Too long"}'));
	$stale = _content_proposals_editor_row(array('id' => 4, 'status' => 'stale', 'error' => 'node gone'));

	$state = pg_content_proposals_studio_rows(array($applied), 5, 3, $now);
	pg_assert_same(array('id' => 2, 'kind' => 'applied', 'reason' => ''), $state[7]['answer'], 'applied');

	$state = pg_content_proposals_studio_rows(array($applied, $dismissed), 5, 3, $now);
	pg_assert_same(array('id' => 3, 'kind' => 'dismissed', 'reason' => 'Too long'), $state[7]['answer'], 'dismissed with its reason, the newest');

	$state = pg_content_proposals_studio_rows(array($applied, $dismissed, $stale), 5, 3, $now);
	pg_assert_same(array('id' => 4, 'kind' => 'stale', 'reason' => ''), $state[7]['answer'], 'stale');

	$seen = _content_proposals_editor_row(array('id' => 4, 'status' => 'stale', 'steps' => '{"seen":1}'));
	$state = pg_content_proposals_studio_rows(array($applied, $seen), 5, 3, $now);
	pg_assert_same(2, $state[7]['answer']['id'], 'an answer the editor closed is not shown again');

	$old = _content_proposals_editor_row(array('id' => 2, 'status' => 'applied', 'decided_by' => 1, 'decided_at' => $now - 31 * 86400));
	pg_assert_same(array(), pg_content_proposals_studio_rows(array($old), 5, 3, $now), 'older than 30 days');

	$withdrawn = _content_proposals_editor_row(array('id' => 2, 'status' => 'dismissed', 'decided_by' => 5, 'decided_at' => $now - 60));
	pg_assert_same(array(), pg_content_proposals_studio_rows(array($withdrawn), 5, 3, $now), 'a change the editor withdrew is no answer');

	$reverted = _content_proposals_editor_row(array('id' => 2, 'status' => 'reverted', 'decided_by' => 1, 'decided_at' => $now - 60));
	pg_assert_same(array(), pg_content_proposals_studio_rows(array($reverted), 5, 3, $now), 'a change taken back is no answer here');
}

// The answer's words are the bell's own sentence, the reason whole.
function test_content_proposals_studio_answer_words()
{
	pg_assert_contains('Too long', pg_content_proposals_answer_text('dismissed', 'about-us', 'Too long'));
	pg_assert_same(pg_content_proposals_bell_words('content_applied', 'about-us')['title'], pg_content_proposals_answer_text('applied', 'about-us', ''));
	pg_assert_same(pg_content_proposals_bell_words('content_stale', 'about-us')['title'], pg_content_proposals_answer_text('stale', 'about-us', ''));
}

// A5: only the editor who made a waiting change withdraws it.
function test_content_proposals_withdraw_check()
{
	pg_assert_same('', pg_content_proposals_withdraw_check(_content_proposals_editor_row(), 5));
	pg_assert_same('other', pg_content_proposals_withdraw_check(_content_proposals_editor_row(), 6), 'another editor');
	pg_assert_same('closed', pg_content_proposals_withdraw_check(_content_proposals_editor_row(array('status' => 'stale')), 5), 'no longer waiting');
	pg_assert_same('closed', pg_content_proposals_withdraw_check(null, 5), 'gone');
}

// Closing an answer keeps the rest of the steps.
function test_content_proposals_steps_seen()
{
	pg_assert_same(array('reason' => 'Too long', 'seen' => 1), json_decode(pg_content_proposals_steps_seen('{"reason":"Too long"}'), true));
	pg_assert_same(array('seen' => 1), json_decode(pg_content_proposals_steps_seen('not json'), true));
}

// The Studio reads the state and withdraws through the designer's panel
// actions, open to the content levels.
function test_content_proposals_studio_wiring()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("array('content_change_state', 'content_change_withdraw', 'content_change_seen')", $panel);
	pg_assert_true(strpos($panel, 'pg_content_proposals_studio($sub, $request, $user)') < strpos($panel, 'if (!pg_designer_is_full($user)) {'), 'before the designers-only gate');
	pg_assert_contains("_pgApiPost('content_change_state'", $js);
	pg_assert_contains("_pgApiPost('content_change_withdraw'", $js);
	pg_assert_contains('_pgContentChangesStart();', $js);
}
