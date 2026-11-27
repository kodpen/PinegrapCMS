<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a live page's new version (includes/fn/page_versions.php): the
 * shape that makes a release bundle a new version, the refusals of a start,
 * the tab state saved onto the copy, the bundle name, Discard's order and
 * the wiring of the editor's call and page load. The runner has no
 * connection, so the pure rules are called and the code's shape is read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_page_version_bundle($over = array())
{
	return $over + array('id' => 4, 'name' => 'New version of Home', 'status' => 'open', 'publish_at' => 0);
}

function _test_page_version_item($over = array())
{
	return $over + array('id' => 9, 'bundle_id' => 4, 'draft_page_id' => 31, 'live_page_id' => 12, 'status' => 'waiting');
}

// Open, no time, one waiting replacement: a new version, however it was made.
function test_page_version_shape_holds()
{
	pg_assert_true(pg_page_version_shape(_test_page_version_bundle(), array(_test_page_version_item())), 'one replacement in an open bundle');
	pg_assert_true(pg_page_version_shape(_test_page_version_bundle(array('name' => 'Spring')), array(_test_page_version_item())), 'the name does not matter');
}

// A second page, a time, another status, a new page or a written item make
// it a plain bundle.
function test_page_version_shape_refused()
{
	$item = _test_page_version_item();

	pg_assert_false(pg_page_version_shape(_test_page_version_bundle(), array($item, _test_page_version_item(array('id' => 10, 'draft_page_id' => 32, 'live_page_id' => 0)))), 'two pages');
	pg_assert_false(pg_page_version_shape(_test_page_version_bundle(), array()), 'no page');
	pg_assert_false(pg_page_version_shape(_test_page_version_bundle(array('publish_at' => 1790000000)), array($item)), 'a publish time');

	foreach (array('scheduled', 'published', 'partial', 'reverted', '') as $status) {
		pg_assert_false(pg_page_version_shape(_test_page_version_bundle(array('status' => $status)), array($item)), "status '" . $status . "'");
	}

	pg_assert_false(pg_page_version_shape(_test_page_version_bundle(), array(_test_page_version_item(array('live_page_id' => 0)))), 'a new page');
	pg_assert_false(pg_page_version_shape(_test_page_version_bundle(), array(_test_page_version_item(array('status' => 'written')))), 'a written item');
	pg_assert_false(pg_page_version_shape(null, array($item)), 'no bundle');
}

// Rows of several bundles give only the versions, each with its item.
function test_page_versions_from_rows()
{
	$bundles = array(
		_test_page_version_bundle(),
		_test_page_version_bundle(array('id' => 5, 'name' => 'Spring')),
		_test_page_version_bundle(array('id' => 6, 'name' => 'Timed', 'publish_at' => 1790000000)),
	);
	$items = array(
		_test_page_version_item(),
		_test_page_version_item(array('id' => 11, 'bundle_id' => 5, 'draft_page_id' => 40, 'live_page_id' => 13)),
		_test_page_version_item(array('id' => 12, 'bundle_id' => 5, 'draft_page_id' => 41, 'live_page_id' => 0)),
		_test_page_version_item(array('id' => 13, 'bundle_id' => 6, 'draft_page_id' => 42, 'live_page_id' => 14)),
	);

	pg_assert_same(array(array(
		'bundle_id' => 4, 'bundle_name' => 'New version of Home', 'item_id' => 9, 'draft_page_id' => 31, 'live_page_id' => 12,
	)), pg_page_versions_from_rows($bundles, $items));
}

// The refusals, in their order: rights, the page on the site, an experiment.
function test_page_version_start_refusals()
{
	pg_assert_same('', pg_page_version_start_refusal(true, true, false), 'allowed');

	$rights = pg_page_version_start_refusal(false, true, false);
	$site = pg_page_version_start_refusal(true, false, false);
	$experiment = pg_page_version_start_refusal(true, true, true);

	pg_assert_contains('administrators and designers', $rights);
	pg_assert_contains('on the site', $site);
	pg_assert_contains('experiment', $experiment);
	pg_assert_same($rights, pg_page_version_start_refusal(false, false, true), 'rights first');
	pg_assert_same($site, pg_page_version_start_refusal(true, false, true), 'the page before the experiment');
}

// The tab's state is written onto the copy as a draft of the live page's
// folder: its own id and name, never the home page, no times or hash.
function test_page_version_draft_page()
{
	$sent = array(
		'page_id' => 12, 'page_name' => 'home', 'page_folder' => 3, 'page_home' => 1, 'page_draft' => 0,
		'tree_json' => '{"type":"root"}', 'page_title' => 'Welcome', 'base_hash' => 'abc', 'unchanged' => 1,
		'page_publish_at' => '2026-12-01T10:00', 'page_unpublish_at' => '', 'page_name_redirect' => 1,
		'publish_from_draft' => 5, 'approval_override' => 'x',
	);

	$got = pg_page_version_draft_page($sent, array('page_id' => 31, 'page_name' => 'home[2]'), 7);

	pg_assert_same(31, $got['page_id']);
	pg_assert_same('home[2]', $got['page_name']);
	pg_assert_same(7, $got['page_folder']);
	pg_assert_same(1, $got['page_draft']);
	pg_assert_same(0, $got['page_home']);
	pg_assert_same('{"type":"root"}', $got['tree_json'], 'the tree is kept');
	pg_assert_same('Welcome', $got['page_title'], 'the title is kept');

	foreach (array('base_hash', 'unchanged', 'page_publish_at', 'page_unpublish_at', 'page_name_redirect', 'publish_from_draft', 'approval_override') as $key) {
		pg_assert_false(array_key_exists($key, $got), $key . ' is dropped');
	}
}

// The bundle's name stays within its 100 characters.
function test_page_version_name()
{
	pg_assert_same('New version of Home', pg_page_version_name('Home'));

	$long = pg_page_version_name(str_repeat('a', 150));

	pg_assert_same(100, mb_strlen($long, 'UTF-8'), 'cut to 100');
	pg_assert_same('…', mb_substr($long, -1, 1, 'UTF-8'), 'ends in an ellipsis');
	pg_assert_same($long, pg_page_bundle_clean_name($long), 'a name the bundle keeps');
}

// Steps run in their order and stop at the first that fails.
function test_page_version_run_steps_order()
{
	$ran = array();
	$step = function ($name, $ok) use (&$ran) {
		return function () use ($name, $ok, &$ran) {
			$ran[] = $name;

			return array('ok' => $ok, 'error' => $ok ? '' : $name . ' failed');
		};
	};

	$all = pg_page_version_run_steps(array('remove_item' => $step('remove_item', true), 'delete_bundle' => $step('delete_bundle', true), 'draft' => $step('draft', true)));

	pg_assert_true($all['ok']);
	pg_assert_same(array('remove_item', 'delete_bundle', 'draft'), $all['done']);
	pg_assert_same(array('remove_item', 'delete_bundle', 'draft'), $ran);

	$ran = array();
	$stopped = pg_page_version_run_steps(array('remove_item' => $step('remove_item', true), 'delete_bundle' => $step('delete_bundle', false), 'draft' => $step('draft', true)));

	pg_assert_false($stopped['ok']);
	pg_assert_same('delete_bundle failed', $stopped['error']);
	pg_assert_same(array('remove_item'), $stopped['done']);
	pg_assert_same(array('remove_item', 'delete_bundle'), $ran, 'the draft is not binned while its bundle stands');
}

// Discard's steps are the item, the emptied bundle, then the draft, and the
// draft is binned (not deleted) under the bundle's lock.
function test_page_version_discard_order_in_code()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_versions.php');

	$steps = pg_page_version_end_steps(_test_page_version_bundle(), _test_page_version_item(), function () {
		return array('ok' => true);
	});
	pg_assert_same(array('remove_item', 'delete_bundle', 'draft'), array_keys($steps));

	$discard = substr($src, strpos($src, 'function pg_page_version_discard('));
	pg_assert_contains('pg_page_bundle_with_lock(', $discard);
	pg_assert_contains('pg_recycle_page(', $discard);
	pg_assert_true(strpos($discard, 'pg_delete_page_record(') === false, 'a discarded draft goes to the Recycle Bin');

	$take_back = substr($src, strpos($src, 'function pg_page_version_take_back('));
	$take_back = substr($take_back, 0, strpos($take_back, 'function pg_page_version_end_steps('));
	pg_assert_contains('pg_delete_page_record(', $take_back, 'a start taken back deletes its copy');
}

// A start writes the bundle only through the bundle module and the page
// only through the page save; it answers an existing version first.
function test_page_version_start_uses_the_modules()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_versions.php');
	$start = substr($src, strpos($src, 'function pg_page_version_start('));
	$start = substr($start, 0, strpos($start, 'function pg_page_version_take_back('));

	foreach (array('pg_page_bundle_create(', 'pg_page_bundle_add_copy(', 'pg_designer_save_page(', 'pg_page_version_take_back(') as $call) {
		pg_assert_contains($call, $start);
	}

	pg_assert_true(strpos($start, 'pg_page_version_of_live(') < strpos($start, 'pg_page_bundle_create('), 'an existing version is answered before a bundle is made');
	pg_assert_true(preg_match('/\b(INSERT|UPDATE|DELETE)\b/', $src) === 0, 'no write of its own');
}

// The editor's call is a designer sub action and its page load carries the
// versions; functions.php loads the module.
function test_page_version_wiring()
{
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');
	$manifest = (string) file_get_contents(PG_FUNCTIONS_DIR . '/functions.php');

	pg_assert_contains("\$sub === 'version_start'", $panel);
	pg_assert_contains("\$sub === 'version_discard'", $panel);
	pg_assert_true(strpos($panel, "\$sub === 'version_start'") > strpos($panel, 'if (!pg_designer_is_full($user))'), 'after the content-level gate');
	pg_assert_contains("'versions'      => pg_page_version_editor_config(\$tab_page_ids)", $screen);
	pg_assert_contains("/includes/fn/page_versions.php'", $manifest);
}

// A publish's answer: published; a live page changed since asks "Replace
// anyway" (changed, nothing written); a page in the way says why.
function test_page_version_publish_answer()
{
	$version = array('bundle_id' => 4, 'item_id' => 9, 'draft_page_id' => 31, 'live_page_id' => 12, 'draft_name' => 'home-copy');
	$plan = function ($over) {
		return array('check' => array('error' => '', 'plan' => $over + array('ok' => false, 'write' => array(), 'leave' => array(), 'ask' => array(), 'pages' => array())));
	};

	$done = pg_page_version_publish_answer(array('ok' => true, 'error' => '') + $plan(array('ok' => true, 'write' => array(9))), $version);
	pg_assert_true($done['ok'], 'published');
	pg_assert_false($done['changed']);
	pg_assert_same(12, $done['live_page_id']);
	pg_assert_same(31, $done['draft_page_id']);

	$asked = pg_page_version_publish_answer(array('ok' => false, 'error' => 'Some pages are in the way; nothing was published.')
		+ $plan(array('ask' => array('9'), 'pages' => array(array('state' => 'changed', 'text' => '')))), $version);
	pg_assert_false($asked['ok']);
	pg_assert_true($asked['changed'], 'the live page changed since: the sheet asks');
	pg_assert_same('The page on the site was changed since this version was started.', $asked['error']);

	$locked = pg_page_version_publish_answer(array('ok' => false, 'error' => 'Some pages are in the way; nothing was published.')
		+ $plan(array('pages' => array(array('state' => 'locked', 'text' => '')))), $version);
	pg_assert_false($locked['changed']);
	pg_assert_contains('open in the Page Design Studio', $locked['error'], 'a page open elsewhere says so');

	$refused = pg_page_version_publish_answer(array('ok' => false, 'error' => 'Some pages are in the way; nothing was published.')
		+ $plan(array('pages' => array(array('state' => 'refused', 'text' => 'The page name is taken.')))), $version);
	pg_assert_same('The page name is taken.', $refused['error'], 'the page save\'s refusal');

	$busy = pg_page_version_publish_answer(array('ok' => false, 'error' => 'The bundle is being published right now. Try again in a moment.', 'busy' => true), $version);
	pg_assert_same('The bundle is being published right now. Try again in a moment.', $busy['error'], 'a held lock');
	pg_assert_false($busy['changed']);

	$bare = pg_page_version_publish_answer(array('ok' => false, 'error' => ''), $version);
	pg_assert_same('The new version could not be published.', $bare['error']);
}

// A publish runs the bundle's own publish under its lock, after the shape is
// read again there; "Replace anyway" is the item's choice.
function test_page_version_publish_uses_the_bundle()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_versions.php');
	$fn = substr($src, strpos($src, 'function pg_page_version_publish('));
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/designer.php');

	pg_assert_contains('pg_page_bundle_with_lock(', $fn);
	pg_assert_true(strpos($fn, 'pg_page_version_shape(') < strpos($fn, 'pg_page_bundle_publish_locked('), 'the shape is read under the lock first');
	pg_assert_contains("=> 'replace')", $fn);
	pg_assert_contains("\$sub === 'version_publish'", $panel);
	pg_assert_true(strpos($panel, "\$sub === 'version_publish'") > strpos($panel, 'if (!pg_designer_is_full($user))'), 'after the content-level gate');
}

// The editor publishing a version has its draft open: its own page locks do
// not count, every other caller's lock does; the key is cleared afterwards.
function test_page_version_publish_acting_session()
{
	pg_assert_same('', pg_page_bundle_acting_session(), 'nobody by default');
	pg_assert_same('sdabc', pg_page_bundle_acting_session('sdabc'));
	pg_assert_same('sdabc', pg_page_bundle_acting_session());
	pg_assert_same('', pg_page_bundle_acting_session(''));

	$bundles = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_bundles.php');
	$check = substr($bundles, strpos($bundles, 'function pg_page_bundle_check_item('));
	$check = substr($check, 0, strpos($check, "\n}\n"));
	pg_assert_contains('pg_collab_may_edit_page($acting, $draft_id)', $check);
	$write = substr($bundles, strpos($bundles, 'function pg_page_bundle_write_item('));
	$write = substr($write, 0, strpos($write, 'function pg_page_bundle_undo_item('));
	pg_assert_true(strpos($write, "pg_collab_may_edit_page(''") === false, 'the write asks with the acting session too');

	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_versions.php');
	$fn = substr($src, strpos($src, 'function pg_page_version_publish('));
	pg_assert_true(strpos($fn, 'pg_page_bundle_acting_session((string) $session_key)') < strpos($fn, 'pg_page_bundle_with_lock('), 'set before the publish');
	pg_assert_contains("finally {\n        pg_page_bundle_acting_session('');", $fn, 'and cleared whatever happens');
}
