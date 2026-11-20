<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for release bundles (includes/fn/page_bundles.php): the statuses and
 * the moves between them, which bundles hold their drafts, one open bundle
 * per draft and per live page, the hold's four refusals (published alone,
 * binned, deleted over the API, made an experiment's variant) and the
 * "changed since" check of a replacement. The runner has no connection, so
 * the pure rules are called and the code's shape is read.
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

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/resources/pages.php');

function test_page_bundle_statuses()
{
	pg_assert_same(array('open', 'scheduled', 'published', 'partial', 'reverted'), pg_page_bundle_statuses(), 'the five statuses');

	foreach (array('open', 'scheduled', 'partial') as $status) {
		pg_assert_true(pg_page_bundle_holds($status), $status . ' holds its drafts');
	}

	foreach (array('published', 'reverted', '', 'ended') as $status) {
		pg_assert_true(!pg_page_bundle_holds($status), "'" . $status . "' holds nothing");
	}
}

function test_page_bundle_allowed_moves()
{
	$allowed = array(
		array('open', 'scheduled'),
		array('scheduled', 'open'),
		array('open', 'published'),
		array('scheduled', 'published'),
		array('open', 'partial'),
		array('scheduled', 'partial'),
		array('partial', 'published'),
		array('partial', 'reverted'),
		array('published', 'reverted'),
	);

	foreach ($allowed as $move) {
		pg_assert_true(pg_page_bundle_may_move($move[0], $move[1]), $move[0] . ' to ' . $move[1]);
	}

	$refused = array(
		array('open', 'open'),
		array('open', 'reverted'),
		array('scheduled', 'reverted'),
		array('published', 'open'),
		array('published', 'partial'),
		array('reverted', 'open'),
		array('reverted', 'published'),
		array('partial', 'open'),
		array('open', 'gone'),
		array('', 'open'),
	);

	foreach ($refused as $move) {
		pg_assert_true(!pg_page_bundle_may_move($move[0], $move[1]), 'not ' . $move[0] . ' to ' . $move[1]);
	}
}

function test_page_bundle_may_change_and_delete()
{
	pg_assert_true(pg_page_bundle_may_change('open'), 'pages are added to an open bundle');
	pg_assert_true(pg_page_bundle_may_change('scheduled'), 'and to a scheduled one');

	foreach (array('partial', 'published', 'reverted') as $status) {
		pg_assert_true(!pg_page_bundle_may_change($status), 'no page added or removed when ' . $status);
	}

	pg_assert_true(pg_page_bundle_may_delete('open'), 'an open bundle is deleted');
	pg_assert_true(pg_page_bundle_may_delete('scheduled'), 'a scheduled one too');
	pg_assert_true(pg_page_bundle_may_delete('reverted'), 'a taken back one too');
	pg_assert_true(!pg_page_bundle_may_delete('partial'), 'a partly published one keeps its record');
	pg_assert_true(!pg_page_bundle_may_delete('published'), 'a published one can still be taken back');
}

function test_page_bundle_user_may()
{
	pg_assert_true(pg_page_bundle_user_may(array('role' => 0)), 'administrator');
	pg_assert_true(pg_page_bundle_user_may(array('role' => '1')), 'designer');
	pg_assert_true(!pg_page_bundle_user_may(array('role' => 2)), 'manager');
	pg_assert_true(!pg_page_bundle_user_may(array('role' => 3)), 'user');
	pg_assert_true(!pg_page_bundle_user_may(array()), 'nobody');
}

function test_page_bundle_clean_name()
{
	pg_assert_same('Spring campaign', pg_page_bundle_clean_name('  Spring campaign '), 'trimmed');
	pg_assert_same('', pg_page_bundle_clean_name('   '), 'empty');
	pg_assert_same('', pg_page_bundle_clean_name(str_repeat('ş', 101)), 'over 100 characters');
	pg_assert_same(str_repeat('ş', 100), pg_page_bundle_clean_name(str_repeat('ş', 100)), '100 characters in any script');
	pg_assert_same('a b', pg_page_bundle_clean_name("a\tb"), 'a control character is a space');
}

// One open bundle per draft and per live page: the open items are given.
function test_page_bundle_add_conflict()
{
	$open = array(
		array('bundle_id' => 4, 'bundle_name' => 'Spring', 'draft_page_id' => 10, 'live_page_id' => 0),
		array('bundle_id' => 4, 'bundle_name' => 'Spring', 'draft_page_id' => 11, 'live_page_id' => 3),
	);

	pg_assert_same(array('kind' => '', 'bundle_name' => ''), pg_page_bundle_add_conflict(12, 0, $open), 'a free draft');
	pg_assert_same(array('kind' => '', 'bundle_name' => ''), pg_page_bundle_add_conflict(12, 5, $open), 'a free live page');
	pg_assert_same(array('kind' => 'draft', 'bundle_name' => 'Spring'), pg_page_bundle_add_conflict(10, 0, $open), 'a draft already in an open bundle');
	pg_assert_same(array('kind' => 'draft', 'bundle_name' => 'Spring'), pg_page_bundle_add_conflict(11, 0, $open), 'a replacement is a draft too');
	pg_assert_same(array('kind' => 'live', 'bundle_name' => 'Spring'), pg_page_bundle_add_conflict(0, 3, $open), 'a live page already replaced');
	pg_assert_same(array('kind' => '', 'bundle_name' => ''), pg_page_bundle_add_conflict(0, 0, $open), 'nothing asked');
	pg_assert_same(array('kind' => '', 'bundle_name' => ''), pg_page_bundle_add_conflict(10, 0, array()), 'no open bundle');
}

function test_page_bundle_state_hash()
{
	$a = pg_page_bundle_state_hash('{"type":"root"}', 'Home', 'Welcome');

	pg_assert_same(40, strlen($a), 'a SHA-1');
	pg_assert_same($a, pg_page_bundle_state_hash('{"type":"root"}', 'Home', 'Welcome'), 'the same state');
	pg_assert_true($a !== pg_page_bundle_state_hash('{"type":"root","x":1}', 'Home', 'Welcome'), 'the tree');
	pg_assert_true($a !== pg_page_bundle_state_hash('{"type":"root"}', 'Home!', 'Welcome'), 'the title');
	pg_assert_true($a !== pg_page_bundle_state_hash('{"type":"root"}', 'Home', 'Welcome!'), 'the description');
	pg_assert_true(pg_page_bundle_state_hash('ab', 'c', '') !== pg_page_bundle_state_hash('a', 'bc', ''), 'no two fields run together');
}

function test_page_bundle_changed_since()
{
	$state = pg_page_bundle_state_hash('{"type":"root"}', 'Home', 'Welcome');
	$item = array('live_page_id' => 3, 'live_state' => $state);

	pg_assert_true(!pg_page_bundle_changed_since($item, $state), 'the live page as it was copied');
	pg_assert_true(pg_page_bundle_changed_since($item, pg_page_bundle_state_hash('{"type":"root"}', 'Home 2', 'Welcome')), 'published by hand since');
	pg_assert_true(pg_page_bundle_changed_since($item, ''), 'the live page is gone');
	pg_assert_true(!pg_page_bundle_changed_since(array('live_page_id' => 0, 'live_state' => ''), $state), 'a new page never changed');
}

function test_page_bundle_hold_text_names_the_bundle()
{
	$text = pg_page_bundle_hold_text('About us (copy)', 'Spring campaign');

	pg_assert_contains('About us (copy)', $text, 'the page');
	pg_assert_contains('Spring campaign', $text, 'the bundle');
}

// The hold's four refusals all go through pg_experiment_variant_hold_message():
// the page save (published alone), the bin (both panel paths), the API's
// delete; and a held draft is no variant choice.
function test_page_bundle_hold_answers_the_experiment_hold()
{
	$message = pg_test_function_source('pg_experiment_variant_hold_message');

	pg_assert_contains('pg_page_bundle_hold(', $message, 'the hold message asks the bundle');
	pg_assert_contains('pg_page_bundle_hold_text(', $message, 'and names it');

	$choices = pg_test_function_source('pg_experiment_sheet_variant_choices');

	pg_assert_contains('pg_page_bundle_hold(', $choices, 'a bundled draft is not offered as a variant');
}

function test_page_bundle_hold_four_refusals_ask_it()
{
	$save = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains('pg_experiment_variant_hold_message($page_id, $name)', $save, 'published alone: the page save');

	$bin = file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_recycle.php')
		. file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_pages_styles.php');
	pg_assert_true(substr_count($bin, 'pg_experiment_variant_hold_message(') >= 2, 'binned: both panel paths');

	pg_assert_contains('pg_experiment_variant_hold_message(', pg_test_function_source('api_pages_delete'), 'deleted over the API');

	$create = pg_test_function_source('pg_experiment_sheet_create');
	pg_assert_contains('pg_experiment_sheet_variant_choices(', $create, 'made a variant: only an offered draft');
}

// A copy is refused for a page whose experiment is open (B3) and a draft
// that is an open experiment's variant is not added (B4).
function test_page_bundle_add_refuses_experiments()
{
	$copy = pg_test_function_source('pg_page_bundle_add_copy_locked');
	pg_assert_contains('pg_page_bundle_add_copy_check(', $copy, 'the copy runs its checks first');
	pg_assert_contains('FROM experiments', pg_test_function_source('pg_page_bundle_add_copy_check'), 'the copy asks for an open experiment');
	pg_assert_contains('duplicate_page(', $copy, 'copied as a variant is');
	pg_assert_contains('page_drafts', $copy, 'and remembers the live page\'s folder');

	$draft = pg_test_function_source('pg_page_bundle_add_draft_locked');
	pg_assert_contains('pg_page_bundle_add_draft_check(', $draft, 'the draft runs its checks first');
	pg_assert_contains('pg_experiment_variant_hold(', pg_test_function_source('pg_page_bundle_add_draft_check'), 'an open experiment\'s variant is refused');
	pg_assert_contains('publish_at = 0', $draft, 'a publish time of its own is cleared');
}

// Two people adding the same draft or the same live page at once: both pass
// the check, both write; the smaller id stays.
function test_page_bundle_race_keeps_the_smaller_id()
{
	pg_assert_true(pg_page_bundle_race_keeps(7, array(7)), 'alone');
	pg_assert_true(pg_page_bundle_race_keeps(7, array()), 'not read back');
	pg_assert_true(pg_page_bundle_race_keeps(7, array('7', '9')), 'the first of two');
	pg_assert_true(!pg_page_bundle_race_keeps(9, array(7, 9)), 'the second of two is taken back');
	pg_assert_true(!pg_page_bundle_race_keeps(0, array()), 'nothing written');
}

function test_page_bundle_insert_item_reads_its_id_first_and_rechecks()
{
	$source = pg_test_function_source('pg_page_bundle_insert_item');

	$insert = strpos($source, 'INSERT INTO page_bundle_item');
	$id = strpos($source, 'mysqli_insert_id(');
	$update = strpos($source, 'UPDATE page_bundle SET');
	$race = strpos($source, 'pg_page_bundle_race_keeps(');

	pg_assert_true($insert !== false && $id !== false && $update !== false && $race !== false, 'all four are there');
	pg_assert_true($insert < $id && $id < $race && $id < $update, 'the id is read before any other query');
	pg_assert_contains('DELETE FROM page_bundle_item', $source, 'a lost race takes its row back');

	pg_assert_contains('pg_delete_page_record(', pg_test_function_source('pg_page_bundle_add_copy_locked'), 'and the copy it made');
}

function test_page_bundle_time_keeps_who_set_it()
{
	$source = pg_test_function_source('pg_page_bundle_set_time');

	pg_assert_contains('scheduled_by', $source, 'the person setting the time is kept');
	pg_assert_contains("(\$publish_at > 0) ? (int) \$user['id'] : 0", $source, 'and cleared with the time');
}

function test_page_bundle_failure_kept_as_written()
{
	$move = pg_test_function_source('pg_page_bundle_move');
	pg_assert_contains("mb_substr((string) \$value, 0, 500, 'UTF-8')", $move, 'cut at 500, as written');

	$clear = pg_test_function_source('pg_page_bundle_clear_failure');
	pg_assert_contains("failed_page_id = 0, failed_reason = ''", $clear, 'a new attempt starts without it');
}
