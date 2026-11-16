<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for publishing and taking back a release bundle
 * (includes/fn/page_bundles.php): the checks that stop a publish before any
 * write, the writes taken back at once when one fails, "partly published"
 * only when that take-back fails too, what a take-back cannot bring back and
 * why, and what stays live at once (a live page's own Publish, a shared
 * component). The runner has no connection, so the planners are called with
 * the answers the database would give, the writes are counted callables,
 * and the code's shape is read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function pg_test_bundle_items()
{
	return array(
		array('id' => 1, 'draft_page_id' => 21, 'live_page_id' => 0, 'draft_name' => 'campaign', 'live_name' => null),
		array('id' => 2, 'draft_page_id' => 22, 'live_page_id' => 5, 'draft_name' => 'home-copy', 'live_name' => 'home'),
		array('id' => 3, 'draft_page_id' => 23, 'live_page_id' => 6, 'draft_name' => 'about-copy', 'live_name' => 'about'),
	);
}

function pg_test_bundle_page_states($plan)
{
	$out = array();

	foreach ($plan['pages'] as $page) {
		$out[$page['item_id']] = $page['state'];
	}

	return $out;
}

function test_page_bundle_publish_plan_all_ready()
{
	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(), array(), array());

	pg_assert_true($plan['ok'], 'nothing in the way');
	pg_assert_same(array(1, 2, 3), $plan['write'], 'every page is written, in the bundle\'s order');
	pg_assert_same(array(), $plan['leave'], 'none left out');
	pg_assert_same(array(1 => 'ready', 2 => 'ready', 3 => 'ready'), pg_test_bundle_page_states($plan), 'each page ready');
	pg_assert_same('home', $plan['pages'][1]['name'], 'a replacement is named by its live page');
	pg_assert_same('campaign', $plan['pages'][0]['name'], 'a new page by its draft');
}

// B7: a locked page stops everything before any write.
function test_page_bundle_publish_plan_locked_stops_everything()
{
	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(), array(3 => array('locked' => true)), array());

	pg_assert_false($plan['ok'], 'a locked page stops the publish');
	pg_assert_same(array(), $plan['write'], 'and nothing is to be written');
	pg_assert_same('locked', $plan['pages'][2]['state'], 'the locked page is named');
	pg_assert_same('ready', $plan['pages'][0]['state'], 'the others are ready');
}

function test_page_bundle_publish_plan_dry_run_refusal_stops_everything()
{
	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(),
		array(1 => array('errors' => array('The page name "campaign" is already in use.'))), array());

	pg_assert_false($plan['ok'], 'a page the save refuses stops the publish');
	pg_assert_same(array(), $plan['write'], 'nothing is to be written');
	pg_assert_same('refused', $plan['pages'][0]['state'], 'the refused page');
	pg_assert_same('The page name "campaign" is already in use.', $plan['pages'][0]['text'], 'with the save\'s own answer');
}

// B7: "changed since" asks per page: Replace anyway or Leave out.
function test_page_bundle_publish_plan_changed_since_asks()
{
	$checks = array(2 => array('changed' => true));

	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(), $checks, array());
	pg_assert_false($plan['ok'], 'a page changed since needs a choice');
	pg_assert_same(array(2), $plan['ask'], 'the page asked about');
	pg_assert_same('changed', $plan['pages'][1]['state'], 'listed "changed since"');
	pg_assert_same(array(), $plan['write'], 'nothing written while one is asked');

	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(), $checks, array(2 => 'replace'));
	pg_assert_true($plan['ok'], 'Replace anyway');
	pg_assert_same(array(1, 2, 3), $plan['write'], 'the page is written over the change');

	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(), $checks, array(2 => 'leave'));
	pg_assert_true($plan['ok'], 'Leave out');
	pg_assert_same(array(1, 3), $plan['write'], 'the others are written');
	pg_assert_same(array(2), $plan['leave'], 'the page is left out');
	pg_assert_same('left_out', $plan['pages'][1]['state'], 'and listed so');

	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(), array(), array(2 => 'leave'));
	pg_assert_same(array(1, 2, 3), $plan['write'], 'a choice for a page not changed since is ignored');

	$plan = pg_page_bundle_publish_plan(pg_test_bundle_items(), $checks, array(2 => 'other'));
	pg_assert_same(array(2), $plan['ask'], 'an unknown choice is no choice');
}

function test_page_bundle_publish_plan_nothing_to_write()
{
	$items = array_slice(pg_test_bundle_items(), 1, 1);
	$plan = pg_page_bundle_publish_plan($items, array(2 => array('changed' => true)), array(2 => 'leave'));

	pg_assert_false($plan['ok'], 'a bundle with every page left out publishes nothing');
	pg_assert_false(pg_page_bundle_publish_plan(array(), array(), array())['ok'], 'an empty bundle publishes nothing');
}

// The writes, counted.
function pg_test_bundle_ops($fail_write = array(), $fail_undo = array())
{
	$log = new ArrayObject();

	$write = function ($id) use ($log, $fail_write) {
		$log[] = 'write ' . $id;

		return in_array($id, $fail_write, true)
			? array('ok' => false, 'error' => 'refused ' . $id)
			: array('ok' => true, 'error' => '');
	};

	$undo = function ($id) use ($log, $fail_undo) {
		$log[] = 'undo ' . $id;

		return !in_array($id, $fail_undo, true);
	};

	return array($log, $write, $undo);
}

function test_page_bundle_publish_run_all_written()
{
	list($log, $write, $undo) = pg_test_bundle_ops();
	$run = pg_page_bundle_publish_run(array(1, 2, 3), $write, $undo);

	pg_assert_same('published', $run['outcome'], 'every page written');
	pg_assert_same(array(1, 2, 3), $run['written'], 'in order');
	pg_assert_same(array('write 1', 'write 2', 'write 3'), $log->getArrayCopy(), 'nothing taken back');
	pg_assert_same(0, $run['failed'], 'no failure');
}

// B9: a failure takes back at once what this publish wrote; the bundle stays open.
function test_page_bundle_publish_run_failure_takes_back()
{
	list($log, $write, $undo) = pg_test_bundle_ops(array(3));
	$run = pg_page_bundle_publish_run(array(1, 2, 3), $write, $undo);

	pg_assert_same('taken_back', $run['outcome'], 'the bundle stays as it was');
	pg_assert_same(3, $run['failed'], 'the page that failed is named');
	pg_assert_same('refused 3', $run['reason'], 'with the save\'s answer');
	pg_assert_same(array('write 1', 'write 2', 'write 3', 'undo 2', 'undo 1'), $log->getArrayCopy(), 'what was written is taken back, newest first');
	pg_assert_same(array(), $run['stands'], 'nothing stands');
}

function test_page_bundle_publish_run_first_failure_writes_nothing_more()
{
	list($log, $write, $undo) = pg_test_bundle_ops(array(1));
	$run = pg_page_bundle_publish_run(array(1, 2, 3), $write, $undo);

	pg_assert_same('taken_back', $run['outcome'], 'nothing was written, nothing stands');
	pg_assert_same(array('write 1'), $log->getArrayCopy(), 'no page after the failure is written');
}

// B9: "partly published" only when the take-back fails too.
function test_page_bundle_publish_run_partial_only_when_take_back_fails()
{
	list($log, $write, $undo) = pg_test_bundle_ops(array(3), array(1));
	$run = pg_page_bundle_publish_run(array(1, 2, 3), $write, $undo);

	pg_assert_same('partial', $run['outcome'], 'a take-back that failed leaves the bundle partly published');
	pg_assert_same(array(1), $run['stands'], 'what stands is listed');
	pg_assert_same(3, $run['failed'], 'the page that failed is still named');
	pg_assert_same(array('write 1', 'write 2', 'write 3', 'undo 2', 'undo 1'), $log->getArrayCopy(), 'every page is still tried');
}

function test_page_bundle_publish_outcome_status()
{
	pg_assert_same('published', pg_page_bundle_publish_status('published', 'open'), 'open to published');
	pg_assert_same('published', pg_page_bundle_publish_status('published', 'partial'), 'Publish the rest');
	pg_assert_same('partial', pg_page_bundle_publish_status('partial', 'scheduled'), 'scheduled to partial');
	pg_assert_same('', pg_page_bundle_publish_status('taken_back', 'open'), 'an open bundle stays open');
	pg_assert_same('', pg_page_bundle_publish_status('taken_back', 'scheduled'), 'a scheduled one stays scheduled');
	pg_assert_same('', pg_page_bundle_publish_status('partial', 'partial'), 'a partial one stays partial');
}

function pg_test_bundle_written()
{
	return array(
		array('id' => 1, 'live_page_id' => 0, 'revision_before' => 0, 'written_state' => 'n1', 'draft_name' => 'campaign', 'live_name' => null),
		array('id' => 2, 'live_page_id' => 5, 'revision_before' => 7, 'written_state' => 'w2', 'draft_name' => 'home-copy', 'live_name' => 'home'),
		array('id' => 3, 'live_page_id' => 6, 'revision_before' => 4, 'written_state' => 'w3', 'draft_name' => 'about-copy', 'live_name' => 'about'),
	);
}

function test_page_bundle_take_back_plan_all()
{
	$probes = array(
		1 => array('exists' => true),
		2 => array('exists' => true, 'state' => 'w2', 'revision' => true),
		3 => array('exists' => true, 'state' => 'w3', 'revision' => true),
	);
	$plan = pg_page_bundle_take_back_plan(pg_test_bundle_written(), $probes);

	pg_assert_same(array(1, 2, 3), $plan['can'], 'every page comes back');
	pg_assert_same(array(), $plan['cannot'], 'none kept');
}

// B11, B12: a page saved since is not taken back; a swept revision is named.
function test_page_bundle_take_back_plan_reasons()
{
	$probes = array(
		1 => array('exists' => false),
		2 => array('exists' => true, 'state' => 'saved-by-hand', 'revision' => true),
		3 => array('exists' => true, 'state' => 'w3', 'revision' => false),
	);
	$plan = pg_page_bundle_take_back_plan(pg_test_bundle_written(), $probes);

	pg_assert_same(array(), $plan['can'], 'none comes back');
	pg_assert_same(array(
		array('item_id' => 1, 'name' => 'campaign', 'reason' => 'gone'),
		array('item_id' => 2, 'name' => 'home', 'reason' => 'changed'),
		array('item_id' => 3, 'name' => 'about', 'reason' => 'swept'),
	), $plan['cannot'], 'each with its reason');

	$plan = pg_page_bundle_take_back_plan(pg_test_bundle_written(), array(1 => array('exists' => true, 'home' => true)));
	pg_assert_same('home', $plan['cannot'][0]['reason'], 'a new page made the home page since stays on the site');
	pg_assert_same('gone', $plan['cannot'][1]['reason'], 'a page with no answer counts as gone');

	$items = pg_test_bundle_written();
	$items[1]['revision_before'] = 0;
	$plan = pg_page_bundle_take_back_plan($items, array(2 => array('exists' => true, 'state' => 'w2', 'revision' => true)));
	pg_assert_same('swept', $plan['cannot'][1]['reason'], 'a replacement with no revision before has nothing to return to');
}

// B12: nothing taken back silently.
function test_page_bundle_take_back_allowed()
{
	$plan = array('can' => array(1, 3), 'cannot' => array(array('item_id' => 2, 'name' => 'home', 'reason' => 'changed')));

	pg_assert_false(pg_page_bundle_take_back_allowed($plan, array()), 'a page kept must have been shown');
	pg_assert_true(pg_page_bundle_take_back_allowed($plan, array(2)), 'the person saw it');
	pg_assert_true(pg_page_bundle_take_back_allowed($plan, array('2')), 'ids from a form');
	pg_assert_true(pg_page_bundle_take_back_allowed(array('can' => array(1), 'cannot' => array()), array()), 'nothing kept');
	pg_assert_false(pg_page_bundle_take_back_allowed(array('can' => array(), 'cannot' => array()), array()), 'nothing to take back');

	foreach (array('gone', 'changed', 'swept', 'home') as $reason) {
		pg_assert_true(pg_page_bundle_take_back_reason_text($reason) !== '', 'a text for ' . $reason);
	}
}

function test_page_bundle_take_back_run()
{
	list($log, $write, $undo) = pg_test_bundle_ops(array(), array(2));
	$run = pg_page_bundle_take_back_run(array(1, 2, 3), $undo);

	pg_assert_same(array(1, 3), $run['done'], 'each page taken back whole');
	pg_assert_same(array(2), $run['failed'], 'the one that failed stays written');
	pg_assert_same(array('undo 1', 'undo 2', 'undo 3'), $log->getArrayCopy(), 'every page is tried');
}

// B10: History names the bundle beside its revision.
function test_page_bundle_revision_bundle_names()
{
	$revisions = array(
		array('revision_no' => 9, 'source' => 'restore'),
		array('revision_no' => 8, 'source' => 'bundle'),
		array('revision_no' => 6, 'source' => 'publish'),
		array('revision_no' => 5, 'source' => 'bundle'),
		array('revision_no' => 3, 'source' => 'bundle'),
	);
	$items = array(
		array('revision_before' => 4, 'bundle_name' => 'Spring'),
		array('revision_before' => 7, 'bundle_name' => 'Summer'),
		array('revision_before' => 0, 'bundle_name' => 'Never written'),
	);

	pg_assert_same(array(5 => 'Spring', 8 => 'Summer'), pg_page_bundle_revision_bundle_names($revisions, $items), 'the first bundle revision after the one before');
	pg_assert_true(in_array('bundle', pg_page_revision_sources(), true), 'a revision carries the source bundle');
}

// B17: a live page's own Publish goes live at once with a replacement waiting.
function test_page_bundle_hold_is_a_draft_only()
{
	$hold = pg_test_function_source('pg_page_bundle_hold');

	pg_assert_contains('page_bundle_item.draft_page_id =', $hold, 'the hold reads the draft');
	pg_assert_true(strpos($hold, 'live_page_id') === false, 'never the live page it stands for');

	$save = pg_test_function_source('pg_designer_save_page');
	pg_assert_contains("\$draft_plan['was'] && !\$draft_plan['want'] && function_exists('pg_experiment_variant_hold_message')", $save,
		'the save asks the hold only of a draft going on the site');
}

// B18: a shared component saved from a replacement's tab is live at once; no
// menu, component or collection record is written by a bundle.
function test_page_bundle_writes_pages_only()
{
	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_bundles.php');

	foreach (array('shared_components', 'menu_items', 'collection_') as $table) {
		pg_assert_true(stripos($module, $table) === false, 'a bundle never touches ' . $table);
	}

	foreach (array('pg_page_bundle_write_item', 'pg_page_bundle_undo_item') as $function) {
		$code = pg_test_function_source($function);
		pg_assert_contains('pg_designer_save_page(', $code, $function . ' writes through the page save');
		pg_assert_contains('pg_collab_may_edit_page(', $code, $function . ' asks the lock on the save path');
	}

	pg_assert_contains("'bundle'", pg_test_function_source('pg_page_bundle_write_item'), 'a written replacement is a revision with the source bundle');
	pg_assert_contains("'restore'", pg_test_function_source('pg_page_bundle_undo_item'), 'a page taken back is a new publish with the source restore');
}

// B7: the checks run before a single write, the write and its take-back only after.
function test_page_bundle_publish_checks_before_writes()
{
	$code = pg_test_function_source('pg_page_bundle_publish');
	$plan = strpos($code, 'pg_page_bundle_publish_check(');
	$run = strpos($code, 'pg_page_bundle_publish_run(');

	pg_assert_true(($plan !== false) && ($run !== false) && ($plan < $run), 'the checks come first');
	pg_assert_contains("if (!\$check['plan']['ok'])", $code, 'and stop the publish');

	$check = pg_test_function_source('pg_page_bundle_publish_check');
	pg_assert_contains('pg_collab_may_edit_page(', $check, 'the lock');
	pg_assert_contains(', true)', $check, 'the save\'s dry run');
	pg_assert_contains('pg_page_bundle_item_changed(', $check, '"changed since"');
}
