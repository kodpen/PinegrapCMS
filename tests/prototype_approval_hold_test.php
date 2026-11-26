<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the approval hold of a prototype link with "Publish only after
 * approval" (includes/fn/prototype_verdicts.php): which link holds a draft,
 * the words of the refusal and of the Publish sheet's held row, the
 * administrator's reason for publishing anyway, and every writer that puts
 * a draft on the site reading the hold.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A link of design 4 asking approval with the publish rule.
function prototype_approval_hold_test_link($extra = array())
{
	return array_merge(array(
		'id' => 11, 'style_id' => 4, 'token_hint' => 'ab12cd', 'audience' => 'team', 'expires_at' => 0, 'revoked_at' => 0,
		'bundle_id' => 0, 'approval' => 1, 'publish_rule' => 1, 'approver_name' => 'Ayşe', 'approver_email' => 'a@example.com',
		'answer_by' => '',
	), $extra);
}

function prototype_approval_hold_test_verdict($link_id, $verdict, $fingerprint, $id = 1)
{
	return array('id' => $id, 'link_id' => $link_id, 'page_id' => 9, 'verdict' => $verdict, 'fingerprint' => $fingerprint,
		'created_at' => 1000 + $id);
}

function test_prototype_approval_hold_pick_holds_a_draft_nobody_approved()
{
	$fp = str_repeat('a', 40);
	$got = pg_proto_approval_hold_pick(array(prototype_approval_hold_test_link()), array(), $fp, 'public', 5000);

	pg_assert_true(is_array($got), 'held');
	pg_assert_same(11, $got['link_id']);
	pg_assert_same('ab12cd', $got['hint']);
	pg_assert_same('Ayşe', $got['approver_name']);
}

function test_prototype_approval_hold_pick_lets_an_approval_of_the_draft_now_through()
{
	$fp = str_repeat('a', 40);
	$link = prototype_approval_hold_test_link();

	pg_assert_same(null, pg_proto_approval_hold_pick(array($link), array(prototype_approval_hold_test_verdict(11, 'approved', $fp)), $fp, 'public', 5000));

	// Approved, but the draft moved since: held again.
	$older = pg_proto_approval_hold_pick(array($link), array(prototype_approval_hold_test_verdict(11, 'approved', str_repeat('b', 40))), $fp, 'public', 5000);
	pg_assert_true(is_array($older), 'an approval of an older version holds');

	// The newest verdict stands: changes asked after an approval hold.
	$verdicts = array(prototype_approval_hold_test_verdict(11, 'approved', $fp, 1), prototype_approval_hold_test_verdict(11, 'changes_requested', $fp, 2));
	pg_assert_true(is_array(pg_proto_approval_hold_pick(array($link), $verdicts, $fp, 'public', 5000)), 'changes requested holds');

	// Another link's approval does not count for this one.
	pg_assert_true(is_array(pg_proto_approval_hold_pick(array($link), array(prototype_approval_hold_test_verdict(12, 'approved', $fp)), $fp, 'public', 5000)), 'per link');
}

function test_prototype_approval_hold_pick_skips_links_that_hold_nothing()
{
	$fp = str_repeat('a', 40);
	$cases = array(
		'no publish rule' => prototype_approval_hold_test_link(array('publish_rule' => 0)),
		'asks no approval' => prototype_approval_hold_test_link(array('approval' => 0)),
		'revoked' => prototype_approval_hold_test_link(array('revoked_at' => 100)),
		'expired' => prototype_approval_hold_test_link(array('expires_at' => 4000)),
		'a bundle link' => prototype_approval_hold_test_link(array('bundle_id' => 3)),
	);

	foreach ($cases as $label => $link) {
		pg_assert_same(null, pg_proto_approval_hold_pick(array($link), array(), $fp, 'public', 5000), $label);
	}

	// A link for anyone does not show a draft that goes live in a private folder.
	$anyone = prototype_approval_hold_test_link(array('audience' => 'anyone'));
	pg_assert_same(null, pg_proto_approval_hold_pick(array($anyone), array(), $fp, 'private', 5000), 'not shown');
	pg_assert_true(is_array(pg_proto_approval_hold_pick(array($anyone), array(), $fp, 'public', 5000)), 'shown');
}

function test_prototype_approval_hold_pick_takes_the_first_link_still_waiting()
{
	$fp = str_repeat('a', 40);
	$links = array(prototype_approval_hold_test_link(), prototype_approval_hold_test_link(array('id' => 12, 'token_hint' => 'zz99yy', 'approver_name' => 'Can')));
	$got = pg_proto_approval_hold_pick($links, array(prototype_approval_hold_test_verdict(11, 'approved', $fp)), $fp, 'public', 5000);

	pg_assert_same(12, $got['link_id'], 'every link with the rule must approve');
}

function test_prototype_approval_hold_text_names_the_page_the_approver_and_the_link()
{
	$text = pg_proto_approval_hold_text('Spring', array('link_id' => 11, 'hint' => 'ab12cd', 'approver_name' => 'Ayşe'));

	pg_assert_contains('Spring', $text);
	pg_assert_contains('Ayşe', $text);
	pg_assert_contains('ab12cd', $text);

	$nameless = pg_proto_approval_hold_text('Spring', array('link_id' => 11, 'hint' => 'ab12cd', 'approver_name' => ''));
	pg_assert_contains('ab12cd', $nameless);

	foreach (array($text, $nameless) as $words) {
		foreach (array('verdict', 'fingerprint', 'hash') as $word) {
			pg_assert_true(stripos($words, $word) === false, $word);
		}
	}
}

function test_prototype_approval_override_reason_is_role_0_with_a_reason()
{
	pg_assert_same('Client agreed by phone', pg_proto_approval_override_reason(array('approval_override' => "  Client agreed\n by phone "), array('role' => 0)));
	pg_assert_same('', pg_proto_approval_override_reason(array('approval_override' => 'Because'), array('role' => 1)), 'a designer may not');
	pg_assert_same('', pg_proto_approval_override_reason(array('approval_override' => 'Because'), array('role' => '1')), 'role as a string');
	pg_assert_same('', pg_proto_approval_override_reason(array('approval_override' => '   '), array('role' => 0)), 'a reason is needed');
	pg_assert_same('', pg_proto_approval_override_reason(array(), array('role' => 0)), 'none sent');
	pg_assert_same(500, mb_strlen(pg_proto_approval_override_reason(array('approval_override' => str_repeat('é', 900)), array('role' => 0)), 'UTF-8'), 'cut');
}

function test_prototype_approval_editor_state_is_a_held_row_waiting_for_approval()
{
	$hold = array('link_id' => 11, 'hint' => 'ab12cd', 'approver_name' => 'Ayşe');
	$got = pg_proto_approval_editor_state($hold, 'Spring', true);

	pg_assert_same('approval', $got['role']);
	pg_assert_true($got['held'], 'held');
	pg_assert_same(lang('Waiting for approval'), $got['label']);
	pg_assert_same(pg_proto_approval_hold_text('Spring', $hold), $got['hold_text']);
	pg_assert_true($got['override'], 'role 0 may publish anyway');
	pg_assert_false(isset($got['status']), 'no experiment chip');

	$other = pg_proto_approval_editor_state($hold, 'Spring', false);
	pg_assert_false($other['override'], 'others may not');
}

/* ---------------------------------------------------------------------------
   Every writer that puts a draft on the site
   --------------------------------------------------------------------------- */

function prototype_approval_hold_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// The body of a function, from its declaration to the next one.
function prototype_approval_hold_test_body($path, $name)
{
	$source = prototype_approval_hold_test_source($path);
	$start = strpos($source, 'function ' . $name . '(');

	pg_assert_true($start !== false, $name . ' is in ' . $path);

	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, (int) $start, (($end === false) ? strlen($source) : $end) - (int) $start);
}

// The name of the function a byte offset of a source lies in.
function prototype_approval_hold_test_function_at($source, $offset)
{
	return preg_match_all('/function\s+([A-Za-z0-9_]+)\s*\(/', substr($source, 0, $offset), $m) ? end($m[1]) : '';
}

// Every writer of a draft going live, and the text of its body that reads
// the hold: the save reads it itself, a writer copying a draft into a live
// page names the draft to the save, the folder move refuses a held draft.
function prototype_approval_hold_test_writers()
{
	return array(
		array('includes/fn/designer.php', 'pg_designer_save_page', 'pg_proto_approval_hold('),
		array('includes/fn/designer.php', 'pg_page_draft_move', 'pg_proto_approval_hold('),
		array('includes/fn/designer.php', 'pg_designer_design_set_draft', 'if (!pg_page_draft_move($pid, false'),
		array('includes/fn/page_schedule.php', 'pg_page_schedule_run', 'pg_proto_approval_hold('),
		array('includes/fn/page_bundles.php', 'pg_page_bundle_check_item', "'publish_from_draft'"),
		array('includes/fn/page_bundles.php', 'pg_page_bundle_write_item', "'publish_from_draft'"),
		array('includes/fn/experiments_sheet.php', 'pg_experiment_sheet_publish_winner', "'publish_from_draft'"),
		array('includes/fn/content_proposals.php', '_pg_cp_write', 'pg_designer_save_page('),
		array('includes/api/resources/design.php', 'api_design_save_as_owner', 'pg_designer_save_page('),
		array('includes/api/resources/design.php', 'api_design_pages_update', 'pg_designer_save_page('),
	);
}

function test_prototype_approval_hold_every_writer_reads_the_hold()
{
	foreach (prototype_approval_hold_test_writers() as $writer) {
		pg_assert_contains($writer[2], prototype_approval_hold_test_body($writer[0], $writer[1]), $writer[1] . ' reads the approval hold');
	}

	$save = prototype_approval_hold_test_body('includes/fn/designer.php', 'pg_designer_save_page');
	pg_assert_contains("'publish_from_draft'", $save, 'the save reads the draft a writer names');
	pg_assert_contains('pg_proto_approval_override_reason(', $save, 'the save takes role 0\'s reason');
}

// A new place that moves a draft onto the site or empties page_drafts is
// a writer this list has not met: it must be added, with its hold.
function test_prototype_approval_hold_no_writer_is_missing()
{
	$known = array();

	foreach (prototype_approval_hold_test_writers() as $writer) {
		$known[$writer[1]] = true;
	}

	$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));

	foreach ($dir as $file) {
		$path = (string) $file;

		if (substr($path, -4) !== '.php' || strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
			continue;
		}

		$source = (string) file_get_contents($path);

		if (!preg_match_all('/pg_page_draft_move\(\s*\$[A-Za-z_]+\s*,\s*false|DELETE FROM page_drafts/', $source, $m, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($m[0] as $hit) {
			$fn = prototype_approval_hold_test_function_at($source, $hit[1]);
			pg_assert_true(isset($known[$fn]), 'a draft goes live in ' . $fn . ' (' . basename($path) . ') without the approval hold');
		}
	}
}

// Every direct write of page.page_folder, by file and function ('' for a
// screen's own code): each one either keeps a draft in the Drafts folder or
// goes through the hold. A move out of the Drafts folder past them took a
// draft live without its approval, experiment or bundle hold.
function prototype_approval_hold_test_folder_writers()
{
	return array(
		'designer.php pg_page_draft_move' => 'the draft move itself',
		'designer.php pg_designer_save_page' => 'the save reads the hold',
		'designer.php pg_designer_delete_design' => 'into the recycle bin',
		'handle_recycle.php pg_explorer_handle_recycle_delete' => 'into the recycle bin',
		'handle_recycle.php pg_explorer_handle_recycle_restore' => 'back where it was binned from',
		'recycle_pages_styles.php pg_recycle_page' => 'into the recycle bin',
		'handle_items.php pg_explorer_handle_move' => 'pg_explorer_move_page_refusal()',
		'pages.php api_pages_update' => 'api_page_move_target() refuses a draft',
		'pages.php api_pages_delete' => 'into the recycle bin',
		'changes.php ws_change_write_page' => 'into the recycle bin',
		'legacy.php upgrade_to_2017_2_2' => 'a system page of an old upgrade',
		'edit_page.php ' => 'pg_page_form_folder_write()',
	);
}

function test_prototype_approval_hold_no_folder_writer_is_missing()
{
	$known = prototype_approval_hold_test_folder_writers();
	$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PG_FUNCTIONS_DIR, FilesystemIterator::SKIP_DOTS));
	$seen = array();

	foreach ($dir as $file) {
		$path = (string) $file;

		if (substr($path, -4) !== '.php' || strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
			continue;
		}

		$source = (string) file_get_contents($path);

		if (!preg_match_all('/UPDATE\s+page\s+SET\b(?:(?!WHERE).)*?\bpage_folder\s*=|\$set\[\]\s*=\s*"page_folder\s*=/is', $source, $m, PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($m[0] as $hit) {
			$key = basename($path) . ' ' . prototype_approval_hold_test_function_at($source, $hit[1]);
			$seen[$key] = true;
			pg_assert_true(isset($known[$key]), 'page_folder is written in ' . $key . ' without a check that keeps a draft a draft');
		}
	}

	foreach (array_keys($known) as $key) {
		pg_assert_true(isset($seen[$key]), $key . ' still writes page_folder');
	}

	pg_assert_contains('pg_explorer_move_page_refusal(', prototype_approval_hold_test_body('includes/explorer/handle_items.php', 'pg_explorer_handle_move'), 'the explorer keeps a draft a draft');
	pg_assert_contains('pg_page_form_folder_write(', prototype_approval_hold_test_source('edit_page.php'), 'page properties keep a draft a draft');
}
