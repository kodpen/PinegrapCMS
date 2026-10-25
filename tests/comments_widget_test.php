<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the comments system widget (includes/fn/widgets_comments.php):
 * the per-pass record of the pages whose thread a widget drew, which the
 * classic comments block of get_page_content.php reads to leave those pages
 * alone; the display options, the order and the featured modes; the thread
 * and row flags; the markup the renderer builds and its escaping; the
 * add-comment form's bindings and the binding-off table; the default layout
 * against the editor's starter; the draw end to end.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function comments_widget_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// -- the hand-off with the classic block ---------------------------------------

// A page is claimed once per pass; a second widget on it gets false. The
// record a pass kept comes back when the outer pass restores its own.
function test_comments_widget_registry_claims_once()
{
	$found = pg_comments_widget_pages(array());

	pg_assert_true(pg_comments_widget_claim(12), 'first claim');
	pg_assert_false(pg_comments_widget_claim(12), 'second claim of the same page');
	pg_assert_false(pg_comments_widget_claim('12'), 'the same page as a digit string');
	pg_assert_true(pg_comments_widget_drawn(12), 'drawn');
	pg_assert_true(pg_comments_widget_drawn('12'), 'drawn, asked with a digit string');
	pg_assert_false(pg_comments_widget_drawn(13), 'another page');

	foreach (array(0, -3, 'x', '12abc', '', ' 12', array(5), true, null, 1.5) as $bad) {
		pg_assert_false(pg_comments_widget_claim($bad), 'not a page id: ' . var_export($bad, true));
		pg_assert_false(pg_comments_widget_drawn($bad), 'never drawn: ' . var_export($bad, true));
	}

	pg_assert_true(pg_comments_widget_claim('14'), 'a digit string is a page id');

	$prev = pg_comments_widget_pages(array());
	pg_assert_same(array(12 => true, 14 => true), $prev, 'the pass kept its pages');
	pg_assert_false(pg_comments_widget_drawn(12), 'a new pass starts empty');
	pg_assert_true(pg_comments_widget_claim(12), 'and claims again');

	$pass = pg_comments_widget_pages($prev);
	pg_assert_same(array(12 => true), $pass, 'the inner pass hands back its own record');
	pg_assert_true(pg_comments_widget_drawn(12, $pass), 'drawn in the record handed over');
	pg_assert_false(pg_comments_widget_drawn(14, $pass), 'not in that record');
	pg_assert_true(pg_comments_widget_drawn(14), 'the outer record is back');
	pg_assert_false(pg_comments_widget_drawn(14, array()), 'an empty record names nothing');

	pg_comments_widget_pages($found);
}

// get_page_content.php tells the widgets which page is drawn, keeps one
// record per pass and lets the classic block skip a page a widget drew.
function test_comments_widget_page_hand_off_wiring()
{
	$page = comments_widget_test_source('get_page_content.php');

	// The widget pass sets its own context ($pg_sw_prev_ctx); the context set
	// for the whole render before it carries only the e-mail flag.
	$pass = strpos($page, '$pg_sw_prev_ctx = ');
	$context = ($pass === false) ? false : strpos($page, 'pg_sw_render_context(array(', $pass);
	$close = ($context === false) ? false : strpos($page, ';', $context);
	pg_assert_true(($context !== false) && ($close !== false), 'the widget pass sets the context');
	if (($context !== false) && ($close !== false)) {
		pg_assert_contains("'page_id' => (int)\$page_id", substr($page, $context, $close - $context), 'the drawn page goes into the context');
	}

	$open = strpos($page, 'pg_comments_widget_pages(array())');
	$expand = strpos($page, '_expand_system_widgets($content');
	$nested = strpos($page, 'pg_expand_nested_components($content');
	$restore = strpos($page, 'pg_comments_widget_pages($pg_cw_prev)');
	pg_assert_true(($open !== false) && ($expand !== false) && ($open < $expand), 'a fresh record before the widget pass');
	pg_assert_true(($restore !== false) && ($nested !== false) && ($nested < $restore), 'the outer record back after the nested pass');
	pg_assert_same(1, substr_count($page, '$pg_cw_pages = array();'), 'the pass starts with no page');

	$skip = "pg_comments_widget_drawn(\$system_region_properties['page_id'], \$pg_cw_pages)";
	pg_assert_same(1, substr_count($page, $skip), 'the classic block asks once');
	$record = strpos($page, 'pg_sw_record_comment_context($system_region_properties');
	$at = strpos($page, $skip);
	$item = strpos($page, "\$item_id = '0';");
	pg_assert_true(($record !== false) && ($at !== false) && ($item !== false) && ($record < $at) && ($at < $item), 'in the condition that opens the block');

	pg_assert_contains("str_replace('<!--pg-comments-block-->', \$comments_block_html", $page, 'a leftover marker is still emptied');
}

// A classic page that shows one item at a time keeps its classic block: a
// legacy form item view page always, a catalog, catalog detail or calendar
// event view page unless a record widget on it decides the thread (the
// classic block then reads that record's thread too).
function test_comments_widget_classic_thread_table()
{
	$rows = array(
		array('form item view', false, true),
		array('form item view', true, true),
		array('catalog', false, true),
		array('catalog detail', false, true),
		array('calendar event view', false, true),
		array('catalog', true, false),
		array('catalog detail', true, false),
		array('calendar event view', true, false),
		array('standard', false, false),
		array('standard', true, false),
		array('', false, false),
		array('folder view', false, false),
		array('Catalog Detail', false, false),
		array(null, false, false),
		array(array('catalog'), false, false),
	);

	foreach ($rows as $row) {
		pg_assert_same($row[2], pg_comments_widget_classic_thread($row[0], $row[1]), var_export($row[0], true) . ($row[1] ? ' with' : ' without') . ' a record widget');
	}
}

// The renderer asks before it claims: a page left to the classic block is
// never claimed, so the block still draws its item's thread there. Every
// other refusal comes after the claim.
function test_comments_widget_renderer_claims_after_classic_check()
{
	$module = comments_widget_test_source('includes/fn/widgets_comments.php');
	$start = strpos($module, 'function _render_system_widget_comments(');
	pg_assert_true($start !== false, 'the renderer');
	if ($start === false) {
		return;
	}

	$body = substr($module, $start);
	$classic = strpos($body, 'pg_comments_widget_classic_thread($page[\'page_type\'], ($record !== null))');
	$claim = strpos($body, 'pg_comments_widget_claim($page_id)');
	$enabled = strpos($body, "if (!\$settings['enabled'])");
	$nested = strpos($body, 'pg_comments_page_nested_record($page_id)');
	pg_assert_true(($classic !== false) && ($claim !== false) && ($classic < $claim), 'the classic check comes before the claim');
	pg_assert_true(($enabled !== false) && ($claim < $enabled), 'comments off: claimed, nothing drawn');
	pg_assert_true(($nested !== false) && ($claim < $nested), 'a nested record widget: claimed, nothing drawn');
}

// -- display options, order, links, flags --------------------------------------

function comments_widget_test_ids($entries)
{
	$ids = array();
	foreach ($entries as $entry) {
		$ids[] = $entry['id'];
	}
	return $ids;
}

function comments_widget_test_column($entries, $key)
{
	$out = array();
	foreach ($entries as $entry) {
		$out[] = $entry[$key];
	}
	return $out;
}

function test_comments_widget_options_defaults_and_clamps()
{
	$defaults = array('order' => 'oldest', 'featured' => 'first', 'layout' => 'flat', 'max_depth' => 1, 'per_page' => 0);
	pg_assert_same($defaults, pg_comments_widget_options(array()), 'defaults');
	pg_assert_same($defaults, pg_comments_widget_options('x'), 'not an array');
	pg_assert_same($defaults, pg_comments_widget_options(array('order' => 1, 'featured' => true, 'layout' => array('threaded'))), 'enums that are not strings');
	pg_assert_same($defaults, pg_comments_widget_options(array('order' => 'random', 'featured' => 'pinned', 'layout' => 'tree')), 'unknown words');

	$kept = pg_comments_widget_options(array('order' => 'newest', 'featured' => 'filter', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20));
	pg_assert_same(array('order' => 'newest', 'featured' => 'filter', 'layout' => 'threaded', 'max_depth' => 2, 'per_page' => 20), $kept, 'every option kept');
	pg_assert_same('none', pg_comments_widget_options(array('featured' => 'none'))['featured'], 'featured none');

	foreach (array(array(0, 1), array(9, 3), array('2', 2), array('x', 1), array(-1, 1), array('-1', 1), array(true, 1), array(2.5, 1), array(3, 3)) as $case) {
		pg_assert_same($case[1], pg_comments_widget_options(array('max_depth' => $case[0]))['max_depth'], 'max_depth ' . var_export($case[0], true));
	}

	foreach (array(array(20, 20), array(15, 0), array('50', 50), array('10', 10), array('x', 0), array(-10, 0), array(0, 0), array(true, 0)) as $case) {
		pg_assert_same($case[1], pg_comments_widget_options(array('per_page' => $case[0]))['per_page'], 'per_page ' . var_export($case[0], true));
	}
}

function test_comments_widget_order_featured_modes()
{
	$rows = array(
		array('id' => '1', 'featured' => '0'),
		array('id' => '2', 'featured' => '0'),
		array('id' => '3', 'featured' => '1'),
		array('id' => '4', 'featured' => '0'),
	);

	$first = pg_comments_widget_order($rows, array());
	pg_assert_same(array(3, 1, 2, 4), comments_widget_test_ids($first), 'first: featured pinned to the top');
	pg_assert_same(array(true, false, false, false), comments_widget_test_column($first, 'featured'), 'first: the pinned one is marked');
	pg_assert_same(array('row', 'id', 'parent_id', 'depth', 'root_id', 'replies', 'featured', 'quoted'), array_keys($first[0]), 'entry keys');
	pg_assert_same(array(0, 0, 0, 0), comments_widget_test_column($first, 'depth'), 'flat: every comment at depth 0');
	pg_assert_same(array(false, false, false, false), comments_widget_test_column($first, 'quoted'), 'no reply, no quote');

	pg_assert_same(array(3, 4, 2, 1), comments_widget_test_ids(pg_comments_widget_order($rows, array('order' => 'newest'))), 'newest first, featured still on top');

	$none = pg_comments_widget_order($rows, array('featured' => 'none'));
	pg_assert_same(array(1, 2, 3, 4), comments_widget_test_ids($none), 'none: the order read');
	pg_assert_same(array(false, false, false, false), comments_widget_test_column($none, 'featured'), 'none: nothing is featured');

	$filter = pg_comments_widget_order($rows, array('featured' => 'filter'));
	pg_assert_same(array(1, 2, 3, 4), comments_widget_test_ids($filter), 'filter: the order read');
	pg_assert_same(array(false, false, true, false), comments_widget_test_column($filter, 'featured'), 'filter: marked');

	pg_assert_same(array(1), comments_widget_test_ids(pg_comments_widget_order(array('x', $rows[0], null), array())), 'entries that are not rows are skipped');
	pg_assert_same(array(), pg_comments_widget_order('x', array()), 'no rows');

	// A featured reply stays in its place; only a top-level one is pinned.
	$replies = array(
		array('id' => '1', 'featured' => '0', 'parent_id' => '0'),
		array('id' => '2', 'featured' => '1', 'parent_id' => '1'),
		array('id' => '3', 'featured' => '1', 'parent_id' => '0'),
	);
	$flat = pg_comments_widget_order($replies, array());
	pg_assert_same(array(3, 1, 2), comments_widget_test_ids($flat), 'a featured reply is not pinned');
	pg_assert_same(array(false, false, true), comments_widget_test_column($flat, 'quoted'), 'flat: a reply quotes its comment');

	$orphan = pg_comments_widget_order(array(array('id' => '4', 'parent_id' => '99'), array('id' => '5', 'parent_id' => '4')), array('featured' => 'none'));
	pg_assert_same(array(false, true), comments_widget_test_column($orphan, 'quoted'), 'a reply to a comment that is not read quotes nothing');
}

// The thread fixture of the threaded order: 1 and 3 are top-level, 2 answers
// 1, 4 answers 2, 5 answers 4, 6 answers a comment that is not read, 7 is a
// featured top-level comment.
function comments_widget_test_thread_rows()
{
	return array(
		array('id' => '1', 'parent_id' => '0', 'featured' => '0'),
		array('id' => '2', 'parent_id' => '1', 'featured' => '0'),
		array('id' => '3', 'parent_id' => '0', 'featured' => '0'),
		array('id' => '4', 'parent_id' => '2', 'featured' => '0'),
		array('id' => '5', 'parent_id' => '4', 'featured' => '0'),
		array('id' => '6', 'parent_id' => '99', 'featured' => '0'),
		array('id' => '7', 'parent_id' => '0', 'featured' => '1'),
	);
}

// Threaded: each top-level comment with its replies under it, a reply
// deeper than max_depth drawn at the cap and quoting the comment it answers.
// 'newest' turns whole units around (a top-level comment and its replies),
// the replies stay oldest first; 'first' pins a featured unit.
function test_comments_widget_order_threaded()
{
	$rows = comments_widget_test_thread_rows();

	$none = pg_comments_widget_order($rows, array('layout' => 'threaded', 'max_depth' => 2, 'featured' => 'none'));
	pg_assert_same(array(1, 2, 4, 5, 3, 6, 7), comments_widget_test_ids($none), 'depth-first under each top-level comment');
	pg_assert_same(array(0, 1, 2, 2, 0, 0, 0), comments_widget_test_column($none, 'depth'), 'capped at 2');
	pg_assert_same(array(false, false, false, true, false, false, false), comments_widget_test_column($none, 'quoted'), 'only the capped reply quotes');
	pg_assert_same(array(1, 1, 1, 0, 0, 0, 0), comments_widget_test_column($none, 'replies'), 'replies counted');

	$first = pg_comments_widget_order($rows, array('layout' => 'threaded', 'max_depth' => 2));
	pg_assert_same(array(7, 1, 2, 4, 5, 3, 6), comments_widget_test_ids($first), 'the featured unit on top');
	pg_assert_true($first[0]['featured'], 'and marked');

	$newest = pg_comments_widget_order($rows, array('layout' => 'threaded', 'max_depth' => 2, 'featured' => 'none', 'order' => 'newest'));
	pg_assert_same(array(7, 6, 3, 1, 2, 4, 5), comments_widget_test_ids($newest), 'newest: units turned around, replies in order');

	$pinned_newest = pg_comments_widget_order($rows, array('layout' => 'threaded', 'max_depth' => 2, 'order' => 'newest'));
	pg_assert_same(array(7, 6, 3, 1, 2, 4, 5), comments_widget_test_ids($pinned_newest), 'newest with the featured one first');

	$one = pg_comments_widget_order($rows, array('layout' => 'threaded', 'max_depth' => 1, 'featured' => 'none'));
	pg_assert_same(array(0, 1, 1, 1, 0, 0, 0), comments_widget_test_column($one, 'depth'), 'max_depth 1');
	pg_assert_same(array(false, false, true, true, false, false, false), comments_widget_test_column($one, 'quoted'), 'both capped replies quote');

	// A featured reply never moves its unit.
	$reply = $rows;
	$reply[1]['featured'] = '1';
	$reply[6]['featured'] = '0';
	pg_assert_same(array(1, 2, 4, 5, 3, 6, 7), comments_widget_test_ids(pg_comments_widget_order($reply, array('layout' => 'threaded', 'max_depth' => 3))), 'a featured reply stays in its place');

	$loops = pg_comments_widget_order(array('x', array('id' => '1', 'parent_id' => '2'), array('id' => '2', 'parent_id' => '1'), null), array('layout' => 'threaded', 'max_depth' => 3));
	pg_assert_same(array(1, 2), comments_widget_test_ids($loops), 'a loop is cut, entries that are not rows are skipped');
	pg_assert_same(array(0, 1), comments_widget_test_column($loops, 'depth'), 'the loop drawn once');
}

// Which page of a thread a visitor gets: per_page units (a top-level comment
// with its replies), a page asked for by number, else the page that holds
// the comment asked for, else the first.
function test_comments_widget_paginate()
{
	$all = pg_comments_widget_paginate(pg_comments_widget_order(comments_widget_test_thread_rows(), array('layout' => 'threaded', 'max_depth' => 2, 'featured' => 'none')), 0, 2, 5);
	pg_assert_same(1, $all['page'], 'per_page 0: one page');
	pg_assert_same(1, $all['pages'], 'of one');
	pg_assert_same(7, count($all['entries']), 'with every comment');

	$flat = pg_comments_widget_order(array(array('id' => '1'), array('id' => '2'), array('id' => '3'), array('id' => '4'), array('id' => '5')), array());
	$two = pg_comments_widget_paginate($flat, 2, 2, 0);
	pg_assert_same(3, $two['pages'], 'five comments, two a page');
	pg_assert_same(2, $two['page'], 'page 2 asked for');
	pg_assert_same(array(3, 4), comments_widget_test_ids($two['entries']), 'its comments');
	pg_assert_same(array(3 => true, 4 => true), $two['ids'], 'their ids');

	pg_assert_same(3, pg_comments_widget_paginate($flat, 2, 9, 0)['page'], 'a page past the end is the last');
	pg_assert_same(array(5), comments_widget_test_ids(pg_comments_widget_paginate($flat, 2, 9, 0)['entries']), 'which holds the last comment');
	pg_assert_same(2, pg_comments_widget_paginate($flat, 2, 0, 4)['page'], 'the page of comment 4');
	pg_assert_same(1, pg_comments_widget_paginate($flat, 2, 0, 42)['page'], 'an unknown comment: the first page');
	pg_assert_same(1, pg_comments_widget_paginate($flat, 2, 1, 5)['page'], 'a page asked for wins over the comment');
	pg_assert_same(1, pg_comments_widget_paginate($flat, 2, '2', 0)['page'], 'a page number that is not a whole number is none');

	$threaded = pg_comments_widget_order(comments_widget_test_thread_rows(), array('layout' => 'threaded', 'max_depth' => 2, 'featured' => 'none'));
	$units = pg_comments_widget_paginate($threaded, 1, 1, 0);
	pg_assert_same(4, $units['pages'], 'four top-level comments, one a page');
	pg_assert_same(array(1, 2, 4, 5), comments_widget_test_ids($units['entries']), 'the replies stay with their comment');
	pg_assert_same(1, pg_comments_widget_paginate($threaded, 2, 0, 5)['page'], 'a deep reply is found on its comment\'s page');
	pg_assert_same(2, pg_comments_widget_paginate(array_merge(array_slice($threaded, 4), array_slice($threaded, 0, 4)), 2, 0, 5)['page'], 'and on a later page');
	pg_assert_same(2, pg_comments_widget_paginate($threaded, 1, 0, 3)['page'], 'the page of comment 3');
	pg_assert_same(4, pg_comments_widget_paginate($threaded, 1, 0, 7)['page'], 'the page of comment 7');

	$empty = pg_comments_widget_paginate(array(), 10, 3, 5);
	pg_assert_same(array('page' => 1, 'pages' => 1, 'entries' => array(), 'ids' => array()), $empty, 'no comments: page 1 of 1');
	pg_assert_same(1, pg_comments_widget_paginate('x', 10, 0, 0)['pages'], 'entries that are not a list');
}

function test_comments_widget_page_param()
{
	pg_assert_same(2, pg_comments_widget_page_param('2'), 'a digit string');
	pg_assert_same(5, pg_comments_widget_page_param(5), 'a whole number');
	pg_assert_same(999999, pg_comments_widget_page_param('999999'), 'six digits');

	foreach (array(0, '0', '-1', -2, '1e3', ' 3', '3 ', 'x', array(2), '1234567', true, false, null, 1.5, '') as $bad) {
		pg_assert_same(0, pg_comments_widget_page_param($bad), 'no page: ' . var_export($bad, true));
	}
}

// The addresses a comment links to: its permalink leaves the page number,
// the featured view and a reply behind; the reply link keeps the page.
function test_comments_widget_page_url()
{
	$params = array('r' => 'ab c', 'lang' => 'en', 'comment' => '3', 'comments_page' => '2', 'comments' => 'all', 'reply_to' => '9', 'x' => array('1'));

	pg_assert_same('/topic?r=ab+c&lang=en&comment=7#c-7',
		pg_comments_widget_page_url('/topic', $params, array('comment', 'comments_page', 'comments', 'reply_to'), array('comment' => 7), 'c-7'), 'a permalink');
	pg_assert_same('/topic?r=ab+c&lang=en&comment=3&comments_page=2&comments=all&reply_to=7#software_add_comment',
		pg_comments_widget_page_url('/topic', $params, array('reply_to'), array('reply_to' => 7), 'software_add_comment'), 'a reply link keeps the page');
	pg_assert_same('/topic', pg_comments_widget_page_url('/topic', array('comment' => '3'), array('comment'), array(), ''), 'no ? without pairs');
	pg_assert_same('/topic#software_add_comment', pg_comments_widget_page_url('/topic', array(), array(), array(), 'software_add_comment'), 'the fragment alone');
	pg_assert_same('/t?q=%C3%BC%26&a%5Bb%5D=1', pg_comments_widget_page_url('/t', array('q' => 'ü&', 'a[b]' => '1'), array(), array(), ''), 'names and values encoded');
	pg_assert_same('?comment=4', pg_comments_widget_page_url(array('x'), 'y', 'z', array('comment' => 4), array('f')), 'arguments that are not what they should be');
}

function test_comments_widget_view_urls()
{
	$urls = pg_comments_widget_view_urls('/forum/topic', array('comments' => 'all', 'r' => 'ab c', 'x' => array('1'), 'q' => 'ü&', 'comment' => '5', 'comments_page' => '2'));
	pg_assert_same('/forum/topic?r=ab+c&q=%C3%BC%26&comments=all#software_comments', $urls['all'], 'show all keeps the other values');
	pg_assert_same('/forum/topic?r=ab+c&q=%C3%BC%26#software_comments', $urls['featured'], 'show featured drops ?comments');

	$bare = pg_comments_widget_view_urls('/p', array('comments' => 'featured'));
	pg_assert_same('/p?comments=all#software_comments', $bare['all'], 'a ? when nothing else is left');
	pg_assert_same('/p#software_comments', $bare['featured'], 'no ? at all');

	pg_assert_same(array('all' => '/p?comments=all#software_comments', 'featured' => '/p#software_comments'), pg_comments_widget_view_urls('/p', 'x'), 'params that are not an array');
}

// The widget reads ?comment=, ?comments_page=, ?comments= and ?reply_to=,
// its links write them and the redirect after a post adds comment= on every
// page, so a list filter or a form control's address prefill may not take
// its value from them. Every query parameter the module reads is checked.
function test_comments_widget_params_reserved()
{
	foreach (array('comment', 'comments_page', 'comments', 'reply_to') as $name) {
		pg_assert_same('', pg_sw_url_param_name($name), $name . ' is reserved');
	}

	preg_match_all('/\$_GET\[\'([a-z0-9_]+)\'\]/', comments_widget_test_source('includes/fn/widgets_comments.php'), $read);
	pg_assert_true(count($read[1]) > 0, 'the module reads the address');

	foreach (array_unique($read[1]) as $name) {
		pg_assert_same('', pg_sw_url_param_name($name), $name . ', read by the widget, is reserved');
	}
}

function comments_widget_test_facts($over = array())
{
	return $over + array(
		'allow_new' => true,
		'signed_in' => false,
		'require_login' => false,
		'search_index' => false,
		'count' => 2,
		'featured_view' => false,
		'has_featured' => false,
		'featured_mode' => 'first',
		'staff' => false,
		'name_mode' => 'typed',
		'rating_on' => false,
		'files_on' => false,
		'watching_on' => false,
		'pages' => 1,
		'replying' => false,
		'is_watcher' => false,
		'submitter_notified' => false,
		'manage_watchers' => false,
	);
}

function test_comments_widget_thread_flags_table()
{
	$open = pg_comments_widget_thread_flags(comments_widget_test_facts());
	$keys = array('comments_open', 'comments_closed', 'can_comment', 'must_sign_in', 'is_signed_in', 'is_signed_out', 'has_comments', 'no_comments',
		'featured_view', 'has_featured', 'is_staff', 'name_is_typed', 'name_is_chosen', 'name_is_fixed', 'rating_on', 'files_on', 'watching_on',
		'has_more_pages', 'is_replying', 'is_watching', 'not_watching', 'can_manage_watchers', 'is_email');
	pg_assert_same($keys, array_keys($open), 'exactly the 23 thread flags');
	pg_assert_false($open['is_email'], 'a live page is no e-mail');
	pg_assert_false(pg_comments_widget_thread_flags(comments_widget_test_facts(array('signed_in' => true, 'staff' => true)))['is_email'], 'whoever reads it');
	pg_assert_false($open['has_more_pages'] || $open['is_replying'] || $open['is_watching'] || $open['not_watching'] || $open['can_manage_watchers'], 'one page, not replying, watching off');
	pg_assert_true($open['comments_open'] && $open['can_comment'] && $open['is_signed_out'] && $open['has_comments'] && $open['name_is_typed'], 'open, signed out');
	pg_assert_false($open['comments_closed'] || $open['must_sign_in'] || $open['is_signed_in'] || $open['no_comments'], 'open, signed out: the rest');

	$closed = pg_comments_widget_thread_flags(comments_widget_test_facts(array('allow_new' => false, 'require_login' => true)));
	pg_assert_true($closed['comments_closed'], 'closed');
	pg_assert_false($closed['comments_open'] || $closed['can_comment'] || $closed['must_sign_in'], 'closed: no form and no sign-in note');
	pg_assert_false($closed['name_is_typed'], 'closed: no name control');

	$login = pg_comments_widget_thread_flags(comments_widget_test_facts(array('require_login' => true)));
	pg_assert_true($login['must_sign_in'], 'sign-in required, signed out');
	pg_assert_false($login['can_comment'], 'sign-in required, signed out: no form');

	$member = pg_comments_widget_thread_flags(comments_widget_test_facts(array('require_login' => true, 'signed_in' => true, 'name_mode' => 'chosen')));
	pg_assert_true($member['can_comment'] && $member['is_signed_in'] && $member['name_is_chosen'], 'signed in');
	pg_assert_false($member['must_sign_in'] || $member['is_signed_out'] || $member['name_is_typed'] || $member['name_is_fixed'], 'signed in: the rest');

	$index = pg_comments_widget_thread_flags(comments_widget_test_facts(array('search_index' => true, 'signed_in' => true, 'staff' => true, 'require_login' => true)));
	pg_assert_false($index['can_comment'] || $index['must_sign_in'] || $index['is_signed_in'] || $index['is_staff'] || $index['name_is_typed'], 'search index: no form, signed out, not staff');
	pg_assert_true($index['is_signed_out'] && $index['comments_open'], 'search index: read as a visitor');

	$staff = pg_comments_widget_thread_flags(comments_widget_test_facts(array('staff' => true, 'signed_in' => true)));
	pg_assert_true($staff['is_staff'], 'staff');

	$none = pg_comments_widget_thread_flags(comments_widget_test_facts(array('has_featured' => true, 'featured_mode' => 'none')));
	pg_assert_false($none['has_featured'], 'none ignores featured comments');
	$first = pg_comments_widget_thread_flags(comments_widget_test_facts(array('has_featured' => true, 'featured_view' => true)));
	pg_assert_true($first['has_featured'] && $first['featured_view'], 'featured, as given');

	$empty = pg_comments_widget_thread_flags(comments_widget_test_facts(array('count' => 0, 'rating_on' => true, 'files_on' => true, 'watching_on' => true, 'name_mode' => 'fixed')));
	pg_assert_true($empty['no_comments'] && $empty['rating_on'] && $empty['files_on'] && $empty['watching_on'] && $empty['name_is_fixed'], 'no comments yet, features on');
	pg_assert_false($empty['has_comments'], 'no comments yet');

	pg_assert_same($keys, array_keys(pg_comments_widget_thread_flags('x')), 'facts that are not an array');

	// Pages.
	pg_assert_true(pg_comments_widget_thread_flags(comments_widget_test_facts(array('pages' => 3)))['has_more_pages'], 'three pages');
	pg_assert_false(pg_comments_widget_thread_flags(comments_widget_test_facts(array('pages' => 1)))['has_more_pages'], 'one page');
	pg_assert_false(pg_comments_widget_thread_flags(comments_widget_test_facts(array('pages' => array(3))))['has_more_pages'], 'pages that are not a number');

	// Replying needs a form.
	pg_assert_true(pg_comments_widget_thread_flags(comments_widget_test_facts(array('replying' => true)))['is_replying'], 'replying');
	pg_assert_false(pg_comments_widget_thread_flags(comments_widget_test_facts(array('replying' => true, 'allow_new' => false)))['is_replying'], 'closed: not replying');
	pg_assert_false(pg_comments_widget_thread_flags(comments_widget_test_facts(array('replying' => true, 'require_login' => true)))['is_replying'], 'must sign in: not replying');

	// Watching: on for the page and signed in.
	$member = comments_widget_test_facts(array('watching_on' => true, 'signed_in' => true));
	$not = pg_comments_widget_thread_flags($member);
	pg_assert_true($not['not_watching'] && !$not['is_watching'], 'a member who does not watch');
	$watching = pg_comments_widget_thread_flags(array('is_watcher' => true) + $member);
	pg_assert_true($watching['is_watching'] && !$watching['not_watching'], 'a member who watches');
	$told = pg_comments_widget_thread_flags(array('submitter_notified' => true) + $member);
	pg_assert_false($told['is_watching'] || $told['not_watching'], 'the record\'s submitter is told anyway');
	$out = pg_comments_widget_thread_flags(comments_widget_test_facts(array('watching_on' => true, 'is_watcher' => true)));
	pg_assert_false($out['is_watching'] || $out['not_watching'], 'signed out');
	$off = pg_comments_widget_thread_flags(comments_widget_test_facts(array('signed_in' => true, 'manage_watchers' => true)));
	pg_assert_false($off['is_watching'] || $off['not_watching'] || $off['can_manage_watchers'], 'watching off');
	$search = pg_comments_widget_thread_flags(array('search_index' => true, 'manage_watchers' => true) + $member);
	pg_assert_false($search['is_watching'] || $search['not_watching'] || $search['can_manage_watchers'], 'the search index');

	pg_assert_true(pg_comments_widget_thread_flags(array('manage_watchers' => true) + $member)['can_manage_watchers'], 'a watcher manager');
	pg_assert_true(pg_comments_widget_thread_flags(array('manage_watchers' => true, 'submitter_notified' => true) + $member)['can_manage_watchers'], 'a submitter who manages');
	pg_assert_false(pg_comments_widget_thread_flags(comments_widget_test_facts(array('watching_on' => true, 'manage_watchers' => true)))['can_manage_watchers'], 'a manager must be signed in');
}

function comments_widget_test_state($over = array())
{
	return $over + array('is_staff' => false, 'sees_pending' => false, 'own_ids' => array(), 'rating_on' => true, 'badge_label' => '');
}

function test_comments_widget_row_flags_table()
{
	$zero = '0000-00-00 00:00:00';
	$keys = array('comment_is_featured', 'comment_is_pending', 'comment_is_own_pending', 'comment_is_scheduled', 'comment_has_rating',
		'comment_has_file', 'comment_has_badge', 'comment_is_verified', 'comment_has_seller_reply', 'comment_can_edit',
		'comment_is_root', 'comment_is_reply', 'comment_has_quote', 'comment_has_replies');
	$root_only = array('comment_is_root' => true) + array_fill_keys($keys, false);

	$plain = pg_comments_widget_row_flags(array('id' => '5', 'published' => '1', 'publish_date_and_time' => $zero), false, comments_widget_test_state());
	pg_assert_same($keys, array_keys($plain), 'exactly the 14 row flags');
	pg_assert_same(array_merge(array_fill_keys($keys, false), array('comment_is_root' => true)), $plain, 'a plain published comment, top-level');

	// Its place in the thread.
	$reply = pg_comments_widget_row_flags(array('id' => '5'), false, comments_widget_test_state(), array('depth' => 2, 'quoted' => true, 'replies' => 3));
	pg_assert_true($reply['comment_is_reply'] && $reply['comment_has_quote'] && $reply['comment_has_replies'], 'a reply that quotes and has replies');
	pg_assert_false($reply['comment_is_root'], 'a reply is no top-level comment');
	$root = pg_comments_widget_row_flags(array('id' => '5'), false, comments_widget_test_state(), array('depth' => 0, 'quoted' => false, 'replies' => 0));
	pg_assert_true($root['comment_is_root'] && !$root['comment_is_reply'] && !$root['comment_has_quote'] && !$root['comment_has_replies'], 'top-level, alone');
	$flat = pg_comments_widget_row_flags(array('id' => '5'), false, comments_widget_test_state(), array('depth' => 0, 'quoted' => true, 'replies' => 1));
	pg_assert_true($flat['comment_is_root'] && $flat['comment_has_quote'] && $flat['comment_has_replies'], 'flat: a reply drawn at depth 0 quotes');
	$odd = pg_comments_widget_row_flags(array('id' => '5'), false, comments_widget_test_state(), array('depth' => -1, 'replies' => 'x'));
	pg_assert_true($odd['comment_is_root'] && !$odd['comment_has_replies'], 'a place that is not one');
	pg_assert_true(pg_comments_widget_row_flags(array('id' => '5'), true, comments_widget_test_state())['comment_is_featured'], 'featured as given');

	$own = pg_comments_widget_row_flags(array('id' => '5', 'published' => '0', 'publish_date_and_time' => $zero), false, comments_widget_test_state(array('own_ids' => array(5))));
	pg_assert_true($own['comment_is_pending'] && $own['comment_is_own_pending'], 'own pending');
	pg_assert_false($own['comment_is_scheduled'], 'own pending is not scheduled');

	$other = pg_comments_widget_row_flags(array('id' => '6', 'published' => '0', 'publish_date_and_time' => $zero), false, comments_widget_test_state(array('own_ids' => array(5))));
	pg_assert_false($other['comment_is_own_pending'], 'another visitor\'s pending comment is not own');

	$scheduled = array('id' => '5', 'published' => '0', 'publish_date_and_time' => '2026-11-01 10:00:00');
	$staff = pg_comments_widget_row_flags($scheduled, false, comments_widget_test_state(array('is_staff' => true, 'sees_pending' => true, 'own_ids' => array(5))));
	pg_assert_true($staff['comment_is_scheduled'] && $staff['comment_can_edit'], 'scheduled, for staff');
	pg_assert_false($staff['comment_is_own_pending'], 'a scheduled comment is not awaiting approval');
	pg_assert_false(pg_comments_widget_row_flags($scheduled, false, comments_widget_test_state())['comment_is_scheduled'], 'scheduled shows only to who sees pending comments');

	foreach (array(array('0', true, false), array('3', true, true), array('7', true, false), array('3', false, false), array(array(3), true, false)) as $case) {
		$flags = pg_comments_widget_row_flags(array('rating' => $case[0]), false, comments_widget_test_state(array('rating_on' => $case[1])));
		pg_assert_same($case[2], $flags['comment_has_rating'], 'rating ' . var_export($case[0], true) . ($case[1] ? ' on' : ' off'));
	}

	pg_assert_true(pg_comments_widget_row_flags(array('user_badge' => '1', 'user_badge_label' => 'Mod'), false, comments_widget_test_state())['comment_has_badge'], 'badge with its own label');
	pg_assert_true(pg_comments_widget_row_flags(array('user_badge' => '1', 'user_badge_label' => ''), false, comments_widget_test_state(array('badge_label' => 'Pro')))['comment_has_badge'], 'badge with the site label');
	pg_assert_false(pg_comments_widget_row_flags(array('user_badge' => '1', 'user_badge_label' => ''), false, comments_widget_test_state())['comment_has_badge'], 'badge without a label');
	pg_assert_false(pg_comments_widget_row_flags(array('user_badge' => '0', 'user_badge_label' => 'Mod'), false, comments_widget_test_state())['comment_has_badge'], 'no badge');

	pg_assert_true(pg_comments_widget_row_flags(array('verified_order_id' => '12'), false, comments_widget_test_state())['comment_is_verified'], 'verified purchase');
	pg_assert_false(pg_comments_widget_row_flags(array('verified_order_id' => '0'), false, comments_widget_test_state())['comment_is_verified'], 'not verified');
	pg_assert_false(pg_comments_widget_row_flags(array('seller_reply' => "  \n "), false, comments_widget_test_state())['comment_has_seller_reply'], 'a blank reply');
	pg_assert_true(pg_comments_widget_row_flags(array('seller_reply' => 'Thanks'), false, comments_widget_test_state())['comment_has_seller_reply'], 'a reply');
	pg_assert_true(pg_comments_widget_row_flags(array('file_name' => 'a.pdf'), false, comments_widget_test_state())['comment_has_file'], 'a file');
	pg_assert_false(pg_comments_widget_row_flags(array('published' => array('0'), 'file_name' => array('x')), false, comments_widget_test_state())['comment_is_pending'], 'columns that are not values read off');
	pg_assert_same(array_merge(array_fill_keys($keys, false), $root_only), pg_comments_widget_row_flags('x', false, 'y'), 'a row that is not an array');
}

function test_comments_widget_status_text()
{
	$own = array('comment_is_pending' => true, 'comment_is_own_pending' => true, 'comment_is_scheduled' => false);
	pg_assert_same('Your comment is awaiting approval', pg_comments_widget_status_text($own, false, 'comment', '', false), 'own pending');

	$scheduled = array('comment_is_pending' => true, 'comment_is_own_pending' => false, 'comment_is_scheduled' => true);
	pg_assert_same('Not Published (scheduled for in 2 hours, unless a new review is added first)', pg_comments_widget_status_text($scheduled, true, 'review', 'in 2 hours', true), 'staff, scheduled, cancel');
	pg_assert_same('Not Published (scheduled for in 2 hours)', pg_comments_widget_status_text($scheduled, true, 'review', 'in 2 hours', false), 'staff, scheduled');

	$pending = array('comment_is_pending' => true, 'comment_is_own_pending' => false, 'comment_is_scheduled' => false);
	pg_assert_same('Not Published', pg_comments_widget_status_text($pending, true, 'comment', '', false), 'staff, pending');
	pg_assert_same('', pg_comments_widget_status_text($pending, false, 'comment', '', false), 'a visitor reads nothing');
	pg_assert_same('', pg_comments_widget_status_text(array(), true, 'comment', '', false), 'published');
}

function test_comments_widget_stars_html()
{
	$three = pg_comments_stars_html(3);
	pg_assert_same(3, substr_count($three, 'class="bi bi-star-fill"'), 'three filled');
	pg_assert_same(2, substr_count($three, 'class="bi bi-star"'), 'two empty');
	pg_assert_same(5, substr_count($three, 'aria-hidden="true"'), 'each drawing is hidden from screen readers');
	pg_assert_contains('role="img" aria-label="Rated 3 out of 5"', $three, 'the rating is said once');
	pg_assert_same(0, strpos($three, '<span class="pg-comment-stars"'), 'one span around them');
	pg_assert_false(strpos($three, 'style'), 'no inline style');
	pg_assert_same(4, substr_count(pg_comments_stars_html('4'), 'bi-star-fill'), 'a digit string');

	foreach (array(0, 6, -1, 'x', array(3), null) as $bad) {
		pg_assert_same('', pg_comments_stars_html($bad), 'no stars for ' . var_export($bad, true));
	}
}

// A link to a comment's anchor keeps pointing at the anchor the row's root
// carries, after pg_sw_uniquify_row_ids() suffixed every id and #link.
function test_comments_widget_row_anchor_links()
{
	$suffixed = pg_sw_uniquify_row_ids('<article id="card"><a href="#c-7">Here</a> <a href="#c-3">Parent</a> <a href="#card">Card</a> <a href="#c-x">X</a></article>', 5, 7);
	$html = pg_comments_row_anchor_links($suffixed, 5, 7);
	pg_assert_contains('<a href="#c-7">Here</a>', $html, 'the comment\'s own anchor');
	pg_assert_contains('<a href="#c-3">Parent</a>', $html, 'another comment\'s anchor');
	pg_assert_contains('id="card_pgw5r7"', $html, 'an id of the design stays suffixed');
	pg_assert_contains('<a href="#card_pgw5r7">Card</a>', $html, 'and so does the link to it');
	pg_assert_contains('<a href="#c-x_pgw5r7">X</a>', $html, 'c- without a number is no comment anchor');
	pg_assert_contains('href="#c-7_pgw5r8"', pg_comments_row_anchor_links('<a href="#c-7_pgw5r8">', 5, 7), 'another row\'s suffix is left alone');
	pg_assert_same(array(1), pg_comments_row_anchor_links(array(1), 5, 7), 'not markup');
}

function test_comments_widget_row_root_id()
{
	pg_assert_same('<div id="c-7" class="card"><span id="x"></span></div>', pg_comments_row_root_id('<div id="card_pgw5r7" class="card"><span id="x"></span></div>', 7), 'replaces the suffixed id');
	pg_assert_same('<div id="c-7" class="card">x</div>', pg_comments_row_root_id('<div class="card">x</div>', 7), 'adds one');
	pg_assert_same("  <!--pg-vis:x:1-->\n<article id=\"c-7\" class=\"a\">y</article>", pg_comments_row_root_id("  <!--pg-vis:x:1-->\n<article class=\"a\">y</article>", '7'), 'skips leading whitespace and comments');
	pg_assert_same('<div id="c-7" data-id="3" title=\'a id="b"\'>z</div>', pg_comments_row_root_id('<div data-id="3" title=\'a id="b"\'>z</div>', 7), 'only the id attribute');
	pg_assert_same('<br id="c-7" />', pg_comments_row_root_id('<br />', 7), 'a void element');
	pg_assert_same('just text', pg_comments_row_root_id('just text', 7), 'text only');
	pg_assert_same('<div class="a">x</div>', pg_comments_row_root_id('<div class="a">x</div>', 0), 'no id');
	pg_assert_same('<div class="a">x</div>', pg_comments_row_root_id('<div class="a">x</div>', 'x'), 'not an id');

	// The depth a comment is drawn at, for a design's own indent.
	pg_assert_same('<div id="c-7" data-pg-comment-depth="2" class="card">x</div>', pg_comments_row_root_id('<div id="card_pgw5r7" class="card">x</div>', 7, 2), 'the depth after the id');
	pg_assert_same('<div id="c-7" data-pg-comment-depth="0" class="a">x</div>', pg_comments_row_root_id('<div class="a" data-pg-comment-depth="3">x</div>', 7, 0), 'replaces one the design wrote');
	pg_assert_same('<div id="c-7" data-pg-comment-depth="1">x</div>', pg_comments_row_root_id('<div>x</div>', 7, '1'), 'a digit string');
	pg_assert_same('<div id="c-7" class="a" data-pg-comment-depth="3">x</div>', pg_comments_row_root_id('<div class="a" data-pg-comment-depth="3">x</div>', 7), 'no depth: as before');
	pg_assert_same('<div id="c-7" class="a">x</div>', pg_comments_row_root_id('<div class="a">x</div>', 7, -1), 'a depth that is not one');
	pg_assert_same('<div id="c-7" class="a">x</div>', pg_comments_row_root_id('<div class="a">x</div>', 7, 'x'), 'nor is a word');
}

function test_comments_widget_quote_excerpt()
{
	pg_assert_same('Hello there friend', pg_comments_quote_excerpt("  Hello\n\tthere   friend \r\n"), 'white space folded');
	pg_assert_same('Short', pg_comments_quote_excerpt('Short'), 'a short text stays');

	$words = str_repeat('word ', 50);
	$cut = pg_comments_quote_excerpt($words, 40);
	pg_assert_same('word word word word word word word word…', $cut, 'cut on a word boundary');
	pg_assert_true(mb_strlen($cut, 'UTF-8') <= 41, 'within the limit and the ellipsis');

	$long = str_repeat('x', 100);
	pg_assert_same(str_repeat('x', 40) . '…', pg_comments_quote_excerpt($long, 40), 'one long word cut hard');
	pg_assert_same('Done, then.', pg_comments_quote_excerpt('Done, then.', 160), 'its own stop kept when it fits');
	pg_assert_same(str_repeat('a', 35) . '…', pg_comments_quote_excerpt(str_repeat('a', 35) . ', ' . str_repeat('b', 10), 40), 'trailing marks dropped before the ellipsis');

	$turkish = str_repeat('ğüş ', 20);
	$quoted = pg_comments_quote_excerpt($turkish, 20);
	pg_assert_same('ğüş ğüş ğüş ğüş ğüş…', $quoted, 'multibyte letters are counted as letters');
	pg_assert_same(str_repeat('a', 20) . '…', pg_comments_quote_excerpt(str_repeat('a', 30), 5), 'the limit is at least 20');

	pg_assert_same('', pg_comments_quote_excerpt("\xC3\x28 broken"), 'invalid UTF-8');
	pg_assert_same('12', pg_comments_quote_excerpt(12), 'a number');
	foreach (array(null, true, array('x')) as $bad) {
		pg_assert_same('', pg_comments_quote_excerpt($bad), 'not text: ' . var_export($bad, true));
	}
}

// What one comment says about its place: its links, the comment it answers
// and how deep it is drawn.
function test_comments_widget_link_values()
{
	$parent = array('id' => '3', 'name' => 'Ann <b>', 'message' => "First\nline");
	$entry = array('id' => 7, 'depth' => 1, 'replies' => 2, 'quoted' => true);
	$params = array('r' => 'ab', 'comment' => '9', 'comments_page' => '2', 'reply_to' => '4');

	$on = pg_comments_widget_link_values($entry, $parent, true, '/topic', $params, true);
	pg_assert_same(array('__comment_url', '__comment_reply_url', '__comment_parent_id', '__comment_parent_name', '__comment_parent_url', '__comment_quote', '__comment_depth', '__comment_reply_count'), array_keys($on), 'the keys');
	pg_assert_same('/topic?r=ab&comment=7#c-7', $on['__comment_url'], 'the permalink');
	pg_assert_same('/topic?r=ab&comment=9&comments_page=2&reply_to=7#software_add_comment', $on['__comment_reply_url'], 'the reply link keeps the page');
	pg_assert_same('3', $on['__comment_parent_id'], 'the parent');
	pg_assert_same('Ann <b>', $on['__comment_parent_name'], 'its name, raw (the token escape does h())');
	pg_assert_same('#c-3', $on['__comment_parent_url'], 'a parent on this page: its anchor');
	pg_assert_same('First line', $on['__comment_quote'], 'the quote');
	pg_assert_same('1', $on['__comment_depth'], 'the depth as text');
	pg_assert_same('2', $on['__comment_reply_count'], 'the replies as text');

	$off = pg_comments_widget_link_values($entry, $parent, false, '/topic', $params, false);
	pg_assert_same('/topic?r=ab&comment=3#c-3', $off['__comment_parent_url'], 'a parent on another page: its permalink');
	pg_assert_same('', $off['__comment_reply_url'], 'no reply link when the visitor may not answer');

	$anonymous = pg_comments_widget_link_values($entry, array('id' => '3', 'name' => '', 'message' => 'x'), true, '/t', array(), true);
	pg_assert_same('Anonymous', $anonymous['__comment_parent_name'], 'an unnamed parent');

	$unquoted = pg_comments_widget_link_values(array('quoted' => false) + $entry, $parent, true, '/t', array(), true);
	pg_assert_same('', $unquoted['__comment_quote'], 'a reply drawn under its comment quotes nothing');
	pg_assert_same('Ann <b>', $unquoted['__comment_parent_name'], 'and still names it');

	$root = pg_comments_widget_link_values(array('id' => 8, 'depth' => 0, 'replies' => 0, 'quoted' => true), null, false, '/t', array(), true);
	pg_assert_same(array('', '', '', ''), array($root['__comment_parent_id'], $root['__comment_parent_name'], $root['__comment_parent_url'], $root['__comment_quote']), 'no parent: no parent tokens');
	pg_assert_same('0', $root['__comment_depth'], 'depth 0');
	pg_assert_same('/t?reply_to=8#software_add_comment', $root['__comment_reply_url'], 'a reply link on a bare address');
}

// A thread's comment has the start of its own words too, and a node bound to
// a token only a list of recent comments fills is left out of a thread.
function test_comments_widget_excerpt_and_list_tokens()
{
	$row = array('id' => '4', 'message' => "Line one\n\nline   two", 'published' => '1');
	$flags = pg_comments_widget_row_flags($row, false, comments_widget_test_state(array('rating_on' => false)));
	$values = pg_comments_widget_row_values($row, $flags, array('is_staff' => false, 'sees_pending' => false, 'show_date' => false, 'label_lower' => 'comment', 'badge_label' => ''));
	pg_assert_same('Line one line two', isset($values['__comment_excerpt']) ? $values['__comment_excerpt'] : null, 'the excerpt: folded, raw');

	if (!function_exists('pg_comments_drop_mode_bindings')) {
		pg_assert_true(false, 'pg_comments_drop_mode_bindings() exists');
		return;
	}

	$tree = pg_comments_default_tree();
	$tree['children'][0]['children'][] = array('type' => 'semantic', 'props' => array('tag' => 'span', 'customName' => 'list title', '_bindings' => array('text' => '__comment_thread_title')), 'children' => array());
	pg_comments_drop_mode_bindings($tree, 'thread');
	pg_assert_same(pg_comments_default_tree(), $tree, 'the list token is dropped, and nothing else');
}

function test_comments_widget_escape_carets_once()
{
	$out = pg_comments_widget_escape(array(
		'__comment_name' => '<b>^^__x^^',
		'__comment_message_html' => '<p>^^__y^^ <?php echo 1; ?></p>',
		'__comment_rating' => 3,
	));
	pg_assert_same('&lt;b&gt;&#94;&#94;__x&#94;&#94;', $out['__comment_name'], 'plain text: escaped, carets as entities');
	pg_assert_same('<p>&#94;&#94;__y&#94;&#94; &lt;?php echo 1; ?></p>', $out['__comment_message_html'], 'raw: markup kept, PHP defused, carets as entities');
	pg_assert_same('3', $out['__comment_rating'], 'a number as text');
	pg_assert_same(array(), pg_comments_widget_escape('x'), 'not an array');

	$raw = pg_sw_raw_token_keys();
	foreach (array('__comment_message_html', '__comment_stars_html', '__comment_seller_reply_html') as $key) {
		pg_assert_true(isset($raw[$key]), $key . ' is raw');
	}
	pg_assert_false(isset($raw['__comment_name']), 'the name is not raw');
}

function test_comments_widget_nested_record_decision()
{
	$record = array(1 => 'form_item_view');
	pg_assert_false(pg_comments_nested_record_decision(array(1), array(), $record), 'a record widget on the page itself');
	pg_assert_true(pg_comments_nested_record_decision(array(1), array(1 => array(2)), array(1 => '', 2 => 'form_item_view')), 'one level down');
	pg_assert_true(pg_comments_nested_record_decision(array(1), array(1 => array(2), 2 => array(3)), array(1 => 'form_list_view', 2 => '', 3 => 'form_item_view')), 'two levels down');
	pg_assert_false(pg_comments_nested_record_decision(array(1), array(1 => array(2), 2 => array(1)), array(1 => '', 2 => '')), 'a loop ends');
	pg_assert_false(pg_comments_nested_record_decision(array(1), array(1 => array(5)), array(1 => '')), 'a child that was not read');
	pg_assert_false(pg_comments_nested_record_decision(array(1, 2), array(1 => array(2)), array(1 => '', 2 => 'form_item_view')), 'also placed on the page itself');
	pg_assert_false(pg_comments_nested_record_decision(array(), array(), array()), 'nothing placed');
	pg_assert_false(pg_comments_nested_record_decision('x', 'y', 'z'), 'not arrays');

	$children = array();
	$types = array();
	for ($i = 1; $i <= 10; $i++) {
		$children[$i] = array($i + 1);
		$types[$i] = '';
	}
	$deep = $types;
	$deep[10] = 'form_item_view';
	pg_assert_false(pg_comments_nested_record_decision(array(1), $children, $deep), 'nine levels down is past the cap');
	$eight = $types;
	$eight[9] = 'form_item_view';
	pg_assert_true(pg_comments_nested_record_decision(array(1), $children, $eight), 'eight levels down is read');
}

function test_comments_widget_name_mode_table()
{
	pg_assert_same('typed', pg_comments_widget_name_mode(false, '', false, false, true), 'signed out');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '0', false, false, true), 'administrator');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, 1, false, false, false), 'designer');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '2', false, false, false), 'manager');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '3', true, false, true), 'a user with access to the folder');
	pg_assert_same('typed', pg_comments_widget_name_mode(true, '3', false, true, true), 'the form editor');
	pg_assert_same('chosen', pg_comments_widget_name_mode(true, '3', false, false, true), 'a member who may choose');
	pg_assert_same('fixed', pg_comments_widget_name_mode(true, '3', false, false, false), 'a member who may not');
	pg_assert_same('fixed', pg_comments_widget_name_mode(true, '', false, false, false), 'no role');
}

// -- the add-comment form ------------------------------------------------------

function comments_widget_test_form_state($over = array())
{
	return $over + array('files_on' => true, 'watcher_box' => true, 'name_mode' => 'typed', 'rating_on' => true, 'captcha_on' => true, 'can_publish' => true);
}

function comments_widget_test_bound($kind, $value, $tag = 'div')
{
	return array('type' => 'semantic', 'props' => array('tag' => $tag, '_bindings' => array($kind => $value)), 'children' => array());
}

function test_comments_widget_binding_disabled_table()
{
	$on = comments_widget_test_form_state();
	$off = comments_widget_test_form_state(array('files_on' => false, 'watcher_box' => false, 'name_mode' => 'fixed', 'rating_on' => false, 'captcha_on' => false, 'can_publish' => false));
	$rows = array(
		array('value', 'comment_file'),
		array('value', 'comment_watcher'),
		array('value', 'comment_name'),
		array('value', 'comment_name_type'),
		array('section', 'comment_rating_input'),
		array('section', 'comment_captcha'),
		array('section', 'comment_publish'),
	);
	foreach ($rows as $row) {
		$node = comments_widget_test_bound($row[0], $row[1]);
		$state = ($row[1] === 'comment_name_type') ? comments_widget_test_form_state(array('name_mode' => 'chosen')) : $on;
		pg_assert_false(_pg_comments_binding_disabled($node, $state), $row[1] . ' on');
		pg_assert_true(_pg_comments_binding_disabled($node, $off), $row[1] . ' off');
	}

	$chosen = comments_widget_test_form_state(array('name_mode' => 'chosen'));
	pg_assert_true(_pg_comments_binding_disabled(comments_widget_test_bound('value', 'comment_name'), $chosen), 'a typed name while choosing');
	pg_assert_true(_pg_comments_binding_disabled(comments_widget_test_bound('value', 'comment_name_type'), $on), 'the select while typing');

	foreach (array(array('value', 'comment_message'), array('section', 'captcha'), array('action', 'comment_submit'), array('action', 'comment_form'), array('eo_visible_if', 'files_on')) as $row) {
		pg_assert_false(_pg_comments_binding_disabled(comments_widget_test_bound($row[0], $row[1]), $off), $row[1] . ' is never switched off');
	}
	pg_assert_false(_pg_comments_binding_disabled(array('type' => 'semantic', 'props' => array('tag' => 'div')), $off), 'a node without bindings');
	pg_assert_false(_pg_comments_binding_disabled('x', $off), 'not a node');
}

function comments_widget_test_control($tag, $value, $extra = array())
{
	$props = $extra + array('tag' => $tag, 'cssClass' => 'form-control', '_bindings' => array('value' => $value));
	return array('type' => 'semantic', 'props' => $props, 'children' => array());
}

function comments_widget_test_label($for, $text)
{
	return array('type' => 'semantic', 'props' => array('tag' => 'label', 'text' => $text, '_attrs' => array(array('name' => 'for', 'value' => $for))), 'children' => array());
}

function comments_widget_test_div($children, $class = 'mb-3')
{
	return array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => $class), 'children' => $children);
}

function comments_widget_test_form_tree()
{
	$form = array('type' => 'semantic', 'props' => array('tag' => 'form', 'cssClass' => 'card card-body', 'id' => 'mine',
		'_attrs' => array(array('name' => 'action', 'value' => '/elsewhere'), array('name' => 'novalidate', 'value' => '')),
		'_bindings' => array('action' => 'comment_form')), 'children' => array(
		comments_widget_test_div(array(comments_widget_test_label('comment_message', 'Comment'), comments_widget_test_control('textarea', 'comment_message', array('id' => 'comment_message')))),
		comments_widget_test_div(array(comments_widget_test_label('my_name', 'Name'), comments_widget_test_control('input', 'comment_name', array('id' => 'my_name')))),
		comments_widget_test_div(array(comments_widget_test_control('select', 'comment_name_type', array('id' => 'comment_name_type')))),
		comments_widget_test_div(array(comments_widget_test_label('comment_file', 'Attach a File'), comments_widget_test_control('input', 'comment_file', array('id' => 'comment_file')))),
		comments_widget_test_div(array(comments_widget_test_control('input', 'comment_watcher', array('cssClass' => 'form-check-input')), comments_widget_test_label('comment_watcher', 'Notify me')), 'form-check'),
		comments_widget_test_div(array(array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Add', '_bindings' => array('action' => 'comment_submit')), 'children' => array())), ''),
	));
	$second = array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'comment_form')), 'children' => array(
		comments_widget_test_control('textarea', 'comment_message'),
	));

	return array('type' => 'root', 'props' => array(), 'children' => array(
		$form,
		$second,
		comments_widget_test_control('input', 'comment_message', array('id' => 'stray')),
		array('type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Go', '_bindings' => array('action' => 'comment_submit')), 'children' => array()),
		comments_widget_test_control('div', 'comment_message'),
	));
}

function comments_widget_test_form_ctx($over = array())
{
	return $over + array(
		'can_comment' => true,
		'action_url' => '/software/add_comment.php',
		'hidden_html' => '<input type="hidden" name="page_id" value="5">',
		'multipart' => false,
		'label_slot' => 'pg_comment_form_label',
		'watcher_checked' => true,
		'state' => comments_widget_test_form_state(array('files_on' => false)),
	);
}

function test_comments_widget_form_bindings()
{
	$tree = comments_widget_test_form_tree();
	pg_assert_same(1, pg_comments_apply_form_bindings($tree, comments_widget_test_form_ctx()), 'one form wired');
	pg_assert_same('custom_html', $tree['children'][0]['children'][0]['props']['contentType'], 'the hidden fields come first');
	pg_assert_same('<input type="hidden" name="page_id" value="5">', $tree['children'][0]['children'][0]['props']['html'], 'as given');

	$html = _render_tree_node($tree, 0, 0);
	pg_assert_same(1, substr_count($html, '<form'), 'a second comment form is dropped');
	pg_assert_contains('<form id="software_add_comment" class="card card-body"', $html, 'the handler\'s anchor as its id');
	pg_assert_contains('action="/software/add_comment.php" method="post"', $html, 'posts to add_comment.php');
	pg_assert_contains('data-pg-comment-form="" data-pg-comment-label="^^pg_comment_form_label^^"', $html, 'the frontend hooks');
	pg_assert_false(strpos($html, 'enctype'), 'no multipart without attachments');
	pg_assert_false(strpos($html, 'novalidate'), 'the browser checks the required message');
	pg_assert_false(strpos($html, '/elsewhere'), 'the designer\'s action is replaced');
	pg_assert_false(strpos($html, 'id="mine"'), 'the designer\'s form id is replaced');

	pg_assert_same(1, substr_count($html, 'name="message"'), 'one message box');
	pg_assert_true((bool) preg_match('#<textarea id="comment_message"[^>]*name="message"[^>]*required=""[^>]*>\^\^pg_comment_form_message\^\^</textarea>#', $html), 'the message box: name, required, its text slot');
	pg_assert_true((bool) preg_match('#<input id="my_name"[^>]*name="name"[^>]*type="text"[^>]*maxlength="50"[^>]*value="\^\^pg_comment_form_name\^\^"#', $html), 'the name box keeps the designer\'s id');
	pg_assert_false(strpos($html, 'name_type'), 'no name select while the name is typed');
	pg_assert_false(strpos($html, 'comment_file'), 'the attachment box is dropped');
	pg_assert_false(strpos($html, 'Attach a File'), 'with its label');
	pg_assert_true((bool) preg_match('#<input id="comment_watcher"[^>]*name="watcher"[^>]*type="checkbox"[^>]*value="1"[^>]*checked=""#', $html), 'the watcher box, ticked, with the default id');
	pg_assert_true((bool) preg_match('#<button[^>]*type="submit"[^>]*data-pg-comment-submit=""#', $html), 'the submit button');
	pg_assert_same(1, substr_count($html, 'data-pg-comment-submit'), 'a submit outside the form is dropped');
	pg_assert_false(strpos($html, 'stray'), 'a message box outside the form is dropped');
	pg_assert_false(strpos($html, '<div class="form-control"'), 'a value binding on an element that is no control is dropped');
	// The design has no CAPTCHA box, so the form gets one (empty here: the
	// sections are filled later in the draw).
	pg_assert_same(1, count(array_keys(comments_widget_test_sections_of($tree['children'][0]), 'comment_captcha', true)), 'the form got a CAPTCHA box');
	pg_assert_same(3 + 1, substr_count($html, '<div class="mb-3">') + substr_count($html, '<div class="form-check">'), 'the emptied wrappers went with their controls; the CAPTCHA box came in');

	$multi = comments_widget_test_form_tree();
	pg_comments_apply_form_bindings($multi, comments_widget_test_form_ctx(array('multipart' => true, 'watcher_checked' => false, 'state' => comments_widget_test_form_state(array('name_mode' => 'chosen')))));
	$multi_html = _render_tree_node($multi, 0, 0);
	pg_assert_true((bool) preg_match('#<select id="comment_name_type"[^>]*name="name_type"[^>]*>\s*\^\^pg_comment_form_name_options\^\^\s*</select>#', $multi_html), 'the name select gets its options slot');
	pg_assert_false(strpos($multi_html, 'my_name'), 'no typed name while it is chosen');
	pg_assert_contains('enctype="multipart/form-data"', $multi_html, 'multipart with attachments');
	pg_assert_true((bool) preg_match('#<input id="comment_file"[^>]*name="file"[^>]*type="file"#', $multi_html), 'the attachment box');
	pg_assert_false((bool) preg_match('#name="watcher"[^>]*checked#', $multi_html), 'the watcher box unticked');

	$closed = comments_widget_test_form_tree();
	pg_assert_same(0, pg_comments_apply_form_bindings($closed, comments_widget_test_form_ctx(array('can_comment' => false))), 'nothing wired');
	$closed_html = _render_tree_node($closed, 0, 0);
	pg_assert_false(strpos($closed_html, '<form'), 'no form when the visitor cannot comment');
	pg_assert_false(strpos($closed_html, 'name="message"'), 'and no stray control');
}

// A form whose design left out the publish options or the CAPTCHA still
// posts what add_comment.php needs: a staff comment follows the page's
// publish setting, a signed-out visitor gets the CAPTCHA before the button.
function comments_widget_test_bare_form($inside = array(), $outside = array())
{
	$button = array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Add', '_bindings' => array('action' => 'comment_submit')), 'children' => array());
	$form = array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'comment_form')), 'children' => array_merge(
		array(comments_widget_test_control('textarea', 'comment_message')),
		$inside,
		array(comments_widget_test_div(array($button), 'actions'))
	));

	return array('type' => 'root', 'props' => array(), 'children' => array_merge($outside, array($form)));
}

function comments_widget_test_sections_of($node)
{
	$out = array();
	foreach ($node['children'] as $child) {
		$out[] = isset($child['props']['_bindings']['section']) ? $child['props']['_bindings']['section'] : '';
	}
	return $out;
}

function test_comments_widget_form_fallbacks()
{
	$staff = comments_widget_test_form_ctx(array('publish_default' => 'now', 'state' => comments_widget_test_form_state(array('captcha_on' => true, 'can_publish' => true))));

	$tree = comments_widget_test_bare_form();
	pg_comments_apply_form_bindings($tree, $staff);
	$form = $tree['children'][0];
	pg_assert_contains('<input type="hidden" name="page_id" value="5"><input type="hidden" name="publish" value="now">', $form['children'][0]['props']['html'], 'the page\'s publish default as a hidden field');
	pg_assert_same(array('', '', 'comment_captcha', ''), comments_widget_test_sections_of($form), 'a CAPTCHA box before the button\'s box');

	pg_cf_apply_section_bindings($tree, array('comment_captcha' => '<div class="captcha">What is 1 + 2 ?</div>', 'comment_rating_input' => '', 'comment_publish' => ''));
	$html = _render_tree_node($tree, 0, 0);
	$question = strpos($html, 'What is 1 + 2 ?');
	pg_assert_true(($question !== false) && ($question < strpos($html, '<button')) && ($question < strpos($html, '</form>')), 'the CAPTCHA in the form, before the button');

	$manual = comments_widget_test_bare_form();
	pg_comments_apply_form_bindings($manual, comments_widget_test_form_ctx(array('publish_default' => 'manual')));
	pg_assert_contains('name="publish" value="manual"', $manual['children'][0]['children'][0]['props']['html'], 'held for approval when the page does not publish at once');

	$visitor = comments_widget_test_bare_form();
	pg_comments_apply_form_bindings($visitor, comments_widget_test_form_ctx(array('state' => comments_widget_test_form_state(array('captcha_on' => false, 'can_publish' => false)))));
	pg_assert_false(strpos($visitor['children'][0]['children'][0]['props']['html'], 'name="publish"'), 'no publish field for a visitor');
	pg_assert_same(array('', '', ''), comments_widget_test_sections_of($visitor['children'][0]), 'no CAPTCHA box when there is no CAPTCHA');

	// The design's own sections are used; none is added twice.
	$designed = comments_widget_test_bare_form(array(
		comments_widget_test_bound('section', 'comment_publish'),
		comments_widget_test_div(array(comments_widget_test_bound('section', 'comment_captcha'))),
	));
	pg_comments_apply_form_bindings($designed, $staff);
	pg_assert_false(strpos($designed['children'][0]['children'][0]['props']['html'], 'name="publish"'), 'the publish options of the design');
	pg_assert_same(array('', '', 'comment_publish', '', ''), comments_widget_test_sections_of($designed['children'][0]), 'the CAPTCHA box of the design');

	// A section in a box the visitor does not get counts as missing.
	$hidden_box = array('type' => 'semantic', 'props' => array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'is_signed_out')), 'children' => array(
		comments_widget_test_bound('section', 'comment_captcha'),
		comments_widget_test_bound('section', 'comment_publish'),
	));
	$hidden = comments_widget_test_bare_form(array($hidden_box));
	pg_comments_apply_form_bindings($hidden, $staff + array('flags' => array('is_signed_out' => false, 'is_signed_in' => true)));
	pg_assert_contains('name="publish" value="now"', $hidden['children'][0]['children'][0]['props']['html'], 'publish options the visitor does not get');
	pg_assert_same(array('', '', '', 'comment_captcha', ''), comments_widget_test_sections_of($hidden['children'][0]), 'a CAPTCHA the visitor does not get');

	$shown = comments_widget_test_bare_form(array($hidden_box));
	pg_comments_apply_form_bindings($shown, $staff + array('flags' => array('is_signed_out' => true)));
	pg_assert_false(strpos($shown['children'][0]['children'][0]['props']['html'], 'name="publish"'), 'a box the visitor gets counts');

	// Outside the form a section would print fields nobody posts.
	$outside = comments_widget_test_bare_form(array(), array(
		comments_widget_test_bound('section', 'comment_captcha'),
		comments_widget_test_div(array(comments_widget_test_bound('section', 'comment_publish'), comments_widget_test_bound('section', 'comment_rating_input'))),
	));
	pg_comments_apply_form_bindings($outside, $staff);
	pg_assert_same(2, count($outside['children']), 'the sections outside the form are dropped');
	pg_assert_same(array(), $outside['children'][0]['children'], 'with what they held');
	pg_assert_same(array('', '', 'comment_captcha', ''), comments_widget_test_sections_of($outside['children'][1]), 'the form gets its own CAPTCHA');
}

// Only a button sends the comment: a link bound to comment_submit would
// jump to # and post nothing, so it is left out.
function test_comments_widget_submit_only_on_buttons()
{
	$submit = array('action' => 'comment_submit');
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'form', '_bindings' => array('action' => 'comment_form')), 'children' => array(
			comments_widget_test_control('textarea', 'comment_message'),
			array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => 'Send link', 'href' => '#', '_bindings' => $submit), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'a', 'text' => 'Send anchor', '_attrs' => array(array('name' => 'href', 'value' => '#')), '_bindings' => $submit), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'span', 'text' => 'Send span', '_bindings' => $submit), 'children' => array()),
			array('type' => 'component', 'props' => array('componentType' => 'btn', 'text' => 'Send btn', '_bindings' => $submit), 'children' => array()),
			array('type' => 'semantic', 'props' => array('tag' => 'button', 'text' => 'Send button', '_bindings' => $submit), 'children' => array()),
		)),
	));

	pg_comments_apply_form_bindings($tree, comments_widget_test_form_ctx());
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_false(strpos($html, 'Send link'), 'a content link is left out');
	pg_assert_false(strpos($html, 'Send anchor'), 'a semantic link is left out');
	pg_assert_false(strpos($html, 'Send span'), 'so is any other element');
	pg_assert_same(2, substr_count($html, 'data-pg-comment-submit'), 'the button component and the button send');
	pg_assert_contains('Send btn', $html, 'the button component');
	pg_assert_contains('Send button', $html, 'the button');
}

function test_comments_widget_hidden_html()
{
	$html = pg_comments_form_hidden_html('<input type="hidden" name="token" value="t">', '/p?x=^^a^^&y="1"', '7', 'submitted_form', '12', array(3));
	$order = array('name="token"', 'name="send_to"', 'name="page_id"', 'name="item_id"', 'name="item_type"', 'name="parent_id"');
	$previous = -1;
	foreach ($order as $needle) {
		$at = strpos($html, $needle);
		pg_assert_true(($at !== false) && ($at > $previous), 'in order: ' . $needle);
		$previous = ($at === false) ? $previous : $at;
	}
	pg_assert_contains('name="send_to" value="/p?x=&#94;&#94;a&#94;&#94;&amp;y=&quot;1&quot;"', $html, 'the address escaped, carets as entities');
	pg_assert_contains('name="page_id" value="7"', $html, 'the page');
	pg_assert_contains('name="item_id" value="12"', $html, 'the item');
	pg_assert_contains('name="item_type" value="submitted_form"', $html, 'the item type');
	pg_assert_contains('name="parent_id" value="0"', $html, 'an id that is not a value is 0');
	pg_assert_contains('name="page_id" value="0"', pg_comments_form_hidden_html('', '', true, '', '', 0), 'a bool is no page');
}

function test_comments_widget_rating_input_html()
{
	$default = pg_comments_rating_input_html(null);
	pg_assert_same(5, substr_count($default, 'type="radio" class="btn-check" name="rating"'), 'five radios named rating');
	for ($i = 1; $i <= 5; $i++) {
		pg_assert_contains('id="comment_rating_' . $i . '" value="' . $i . '"', $default, 'radio ' . $i);
		pg_assert_contains('for="comment_rating_' . $i . '"', $default, 'label ' . $i);
	}
	pg_assert_same(1, substr_count($default, ' checked'), 'one checked');
	pg_assert_contains('value="5" autocomplete="off" checked', $default, 'five by default');
	pg_assert_contains('role="group" aria-label="Rating"', $default, 'a labelled group');
	pg_assert_contains('<span class="visually-hidden">3 out of 5</span>', $default, 'each number said in words');
	pg_assert_false(strpos($default, 'style'), 'no style');

	pg_assert_contains('value="3" autocomplete="off" checked', pg_comments_rating_input_html(3), 'three');
	pg_assert_contains('value="2" autocomplete="off" checked', pg_comments_rating_input_html('2'), 'a digit string');
	pg_assert_contains('value="5" autocomplete="off" checked', pg_comments_rating_input_html(9), 'out of range');
	pg_assert_contains('value="5" autocomplete="off" checked', pg_comments_rating_input_html('x'), 'not a number');
}

function test_comments_widget_name_options_html()
{
	$choices = array('username' => 'Jane Doe', 'first_name' => 'Jane', 'first_name_last_initial' => 'Jane D.', 'full_name' => 'Jane Doe', 'anonymous' => 'Anonymous');
	$html = pg_comments_name_options_html($choices, 'first_name', 'Anonim');
	pg_assert_same(1, substr_count($html, '>Jane Doe</option>'), 'equal labels collapse');
	pg_assert_same(0, strpos($html, '<option value="full_name">Jane Doe</option>'), 'the later value, the first place');
	pg_assert_contains('<option value="first_name" selected>Jane</option>', $html, 'the chosen one');
	pg_assert_contains('<option value="anonymous">Anonim</option>', $html, 'anonymous in the given words');
	pg_assert_same(4, substr_count($html, '<option'), 'four options');

	$odd = pg_comments_name_options_html(array('username' => 'A^^b^^ <i>'), '', 'Anonymous');
	pg_assert_same('<option value="username">A&#94;&#94;b&#94;&#94; &lt;i&gt;</option>', $odd, 'escaped, carets as entities');
	pg_assert_same('', pg_comments_name_options_html('x', '', 'Anonymous'), 'no choices');
}

function test_comments_widget_publish_html()
{
	$html = pg_comments_publish_html('schedule', '10/24/2026 3:00 PM', true, 'comment', '<script>init()</script>');
	pg_assert_same(0, strpos($html, '<div class="pg-comment-publish" data-pg-comment-publish>'), 'the wrapper hook');
	pg_assert_contains('id="comment_publish" name="publish" data-pg-comment-publish-select>', $html, 'the select hook');
	pg_assert_contains('<option value="schedule" selected>At a Scheduled Time</option>', $html, 'the chosen option');
	pg_assert_contains('<option value="now">Now</option>', $html, 'now');
	pg_assert_contains('<option value="manual">Maybe Later</option>', $html, 'later');
	pg_assert_contains('<span class="ms-2" data-pg-comment-publish-schedule>', $html, 'the schedule shows');
	pg_assert_contains('name="publish_date_and_time" maxlength="19" size="21" value="10/24/2026 3:00 PM"', $html, 'the date');
	pg_assert_true((bool) preg_match('#name="publish_cancel" value="1" checked#', $html), 'cancel ticked');
	pg_assert_contains('Cancel if a new comment is added first.', $html, 'its label');
	pg_assert_same(strlen($html) - strlen('<script>init()</script></div>'), strpos($html, '<script>init()</script></div>'), 'the scripts last');
	pg_assert_false(strpos($html, 'style='), 'no inline style');

	$now = pg_comments_publish_html('now', '"><b>^^x^^', false, 'comment', '');
	pg_assert_contains('data-pg-comment-publish-schedule hidden>', $now, 'the schedule hidden');
	pg_assert_contains('value="&quot;&gt;&lt;b&gt;&#94;&#94;x&#94;&#94;"', $now, 'the date escaped');
	pg_assert_false((bool) preg_match('#name="publish_cancel" value="1" checked#', $now), 'cancel unticked');
	pg_assert_contains('<option value="now" selected>', $now, 'now chosen');
}

function test_comments_widget_watcher_box()
{
	pg_assert_true(pg_comments_watcher_box(true, true, false, false), 'watching on, signed in, not watching, not notified');
	pg_assert_false(pg_comments_watcher_box(false, true, false, false), 'watching off');
	pg_assert_false(pg_comments_watcher_box(true, false, false, false), 'signed out');
	pg_assert_false(pg_comments_watcher_box(true, true, true, false), 'already watching');
	pg_assert_false(pg_comments_watcher_box(true, true, false, true), 'notified another way');
}

// -- replies, the watch box and the staff controls -------------------------------

function comments_widget_test_node($type, $props, $children = array())
{
	return array('type' => $type, 'props' => $props, 'children' => $children);
}

function comments_widget_test_form($action, $children = array())
{
	return comments_widget_test_node('semantic', array('tag' => 'form', '_bindings' => array('action' => $action)), $children);
}

function comments_widget_test_button($text, $action, $extra = array())
{
	return comments_widget_test_node('semantic', $extra + array('tag' => 'button', 'text' => $text, '_bindings' => array('action' => $action)));
}

function comments_widget_test_link($text, $action, $extra = array())
{
	return comments_widget_test_node('content', $extra + array('contentType' => 'link', 'text' => $text, 'href' => '#', 'cssClass' => '', '_bindings' => array('action' => $action)));
}

function comments_widget_test_controls($over = array())
{
	return $over + array(
		'can_reply' => false,
		'replying' => false,
		'cancel_url' => '/p?r=ab#software_add_comment',
		'share_copied' => 'Link copied.',
		'share_prompt' => 'Copy the link to this comment:',
		'can_watch' => false,
		'is_watcher' => false,
		'watch_action_url' => '/software/add_or_remove_watcher.php',
		'watch_hidden_html' => '<input type="hidden" name="action" value="add">',
		'is_staff' => false,
		'allow_new' => true,
		'allow_action_url' => '/software/allow_or_disallow_new_comments.php',
		'allow_hidden_html' => '<input type="hidden" name="action" value="disallow">',
	);
}

// A form inside another form is broken markup the browser closes at the
// first </form>: a small form inside the comment form, the comment form
// inside a small one, and the watcher list (it holds forms) inside any form
// are left out.
function test_comments_widget_drop_nested_forms()
{
	$tree = comments_widget_test_node('root', array(), array(
		comments_widget_test_form('comment_form', array(
			comments_widget_test_form('comment_watch_form'),
			comments_widget_test_node('semantic', array('tag' => 'div'), array(comments_widget_test_form('comment_allow_new_form'))),
			comments_widget_test_node('semantic', array('tag' => 'div', '_bindings' => array('section' => 'comment_watchers'))),
			comments_widget_test_node('semantic', array('tag' => 'div', 'text' => 'kept')),
		)),
		comments_widget_test_form('comment_watch_form', array(
			comments_widget_test_form('comment_form'),
		)),
		comments_widget_test_node('semantic', array('tag' => 'form'), array(
			comments_widget_test_node('semantic', array('tag' => 'div', '_bindings' => array('section' => 'comment_watchers'))),
		)),
		comments_widget_test_form('comment_allow_new_form'),
		comments_widget_test_node('semantic', array('tag' => 'div', '_bindings' => array('section' => 'comment_watchers'))),
	));

	pg_comments_drop_nested_forms($tree);
	pg_assert_same(5, count($tree['children']), 'the top-level forms and the watcher list are kept');
	pg_assert_same(2, count($tree['children'][0]['children']), 'the comment form keeps its own boxes');
	pg_assert_same(array(), $tree['children'][0]['children'][0]['children'], 'a small form in a box of it is left out');
	pg_assert_same('kept', $tree['children'][0]['children'][1]['props']['text'], 'other boxes stay');
	pg_assert_same(array(), $tree['children'][1]['children'], 'a comment form inside the watch form is left out');
	pg_assert_same(array(), $tree['children'][2]['children'], 'the watcher list inside any form is left out');
}

// While the visitor may answer, the "Replying to" box stays in the page with
// the hidden attribute until a reply link fills it.
function test_comments_widget_mark_reply_boxes()
{
	$build = function () {
		return comments_widget_test_node('root', array(), array(
			comments_widget_test_node('semantic', array('tag' => 'div', 'cssClass' => 'alert', '_bindings' => array('eo_visible_if' => 'is_replying')), array(
				comments_widget_test_node('semantic', array('tag' => 'strong', '_bindings' => array('text' => '__reply_to_name'))),
			)),
			comments_widget_test_node('content', array('contentType' => 'paragraph', 'text' => 'p', '_bindings' => array('eo_visible_if' => 'is_replying'))),
			comments_widget_test_node('loop_area', array(), array(
				comments_widget_test_node('semantic', array('tag' => 'div', '_bindings' => array('eo_visible_if' => 'is_replying'))),
			)),
		));
	};

	$tree = $build();
	pg_comments_mark_reply_boxes($tree, false);
	$box = $tree['children'][0];
	pg_assert_false(isset($box['props']['_bindings']['eo_visible_if']), 'the box no longer waits for the flag');
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_contains('<div class="alert d-none" data-pg-comment-replying="" hidden="">', $html, 'hidden until a reply is chosen, also past a display class');
	pg_assert_contains('<strong data-pg-comment-reply-to-name=""', $html, 'the name the browser fills');
	pg_assert_same('is_replying', $tree['children'][1]['props']['_bindings']['eo_visible_if'], 'a content node keeps the flag');
	pg_assert_same('is_replying', $tree['children'][2]['children'][0]['props']['_bindings']['eo_visible_if'], 'the loop_area is left alone');

	$replying = $build();
	pg_comments_mark_reply_boxes($replying, true);
	$shown = _render_tree_node($replying, 0, 0);
	pg_assert_contains('<div class="alert" data-pg-comment-replying="">', $shown, 'shown while replying');
	pg_assert_same(1, substr_count($shown, 'data-pg-comment-replying'), 'once');
}

function test_comments_widget_control_bindings()
{
	$build = function () {
		return comments_widget_test_node('root', array(), array(
			comments_widget_test_form('comment_watch_form', array(
				comments_widget_test_button('Notify me', 'comment_watch'),
				comments_widget_test_button('Stop', 'comment_unwatch'),
				comments_widget_test_link('Watch link', 'comment_watch'),
				comments_widget_test_button('Close here', 'comment_close'),
			)),
			comments_widget_test_form('comment_watch_form', array(comments_widget_test_button('Second watch', 'comment_watch'))),
			comments_widget_test_form('comment_allow_new_form', array(
				comments_widget_test_button('Stop new', 'comment_close'),
				comments_widget_test_node('component', array('componentType' => 'btn', 'text' => 'Allow new', '_bindings' => array('action' => 'comment_reopen'))),
			)),
			comments_widget_test_button('Stray watch', 'comment_watch'),
			comments_widget_test_link('Cancel link', 'comment_reply_cancel'),
			comments_widget_test_button('Cancel button', 'comment_reply_cancel'),
			comments_widget_test_node('semantic', array('tag' => 'span', 'text' => 'Cancel span', '_bindings' => array('action' => 'comment_reply_cancel'))),
			comments_widget_test_link('Reply outside', 'comment_reply'),
			comments_widget_test_button('Share outside', 'comment_share'),
			comments_widget_test_node('loop_area', array(), array(comments_widget_test_link('Reply inside', 'comment_reply'))),
		));
	};

	$member = $build();
	pg_comments_apply_control_bindings($member, comments_widget_test_controls(array('can_watch' => true, 'can_reply' => true)));
	$html = _render_tree_node($member, 0, 0);
	pg_assert_same(1, substr_count($html, 'id="software_watcher"'), 'one watch form');
	pg_assert_contains('<form id="software_watcher" action="/software/add_or_remove_watcher.php" method="post">', $html, 'posting to the watcher handler');
	pg_assert_true(strpos($html, '<input type="hidden" name="action" value="add">') < strpos($html, 'Notify me'), 'its hidden fields first');
	pg_assert_true((bool) preg_match('#<button type="submit">\s*Notify me#', $html) || (strpos($html, '<button type="submit">Notify me</button>') !== false), 'the watch button sends');
	pg_assert_false(strpos($html, 'Stop</button>'), 'no unwatch for a visitor who does not watch');
	pg_assert_false(strpos($html, 'Watch link'), 'a link sends nothing');
	pg_assert_false(strpos($html, 'Close here'), 'a staff button in the watch form');
	pg_assert_false(strpos($html, 'Second watch'), 'a second watch form');
	pg_assert_false(strpos($html, 'Stray watch'), 'a watch button outside its form');
	pg_assert_false(strpos($html, 'software_allow_or_disallow_new_comments'), 'no staff form for a member');
	pg_assert_false(strpos($html, 'Reply outside') || strpos($html, 'Share outside'), 'reply and share belong to a comment');
	pg_assert_contains('Reply inside', $html, 'the loop_area is left to the rows');
	$loop = null;
	foreach ($member['children'] as $child) {
		if ($child['type'] === 'loop_area') {
			$loop = $child;
		}
	}
	pg_assert_same('comment_reply', ($loop === null) ? '' : $loop['children'][0]['props']['_bindings']['action'], 'with its binding');
	pg_assert_contains('<a href="/p?r=ab#software_add_comment" class="d-none" data-pg-comment-reply-cancel="" data-pg-comment-replying="" hidden="">Cancel link</a>', $html, 'the cancel link, hidden until replying');
	pg_assert_contains('<button class="d-none" type="button" data-pg-comment-reply-cancel="" data-pg-comment-replying="" hidden="">Cancel button</button>', $html, 'a cancel button sends nothing');
	pg_assert_false(strpos($html, 'Cancel span'), 'cancel on what is no link or button');

	$watcher = $build();
	pg_comments_apply_control_bindings($watcher, comments_widget_test_controls(array('can_watch' => true, 'is_watcher' => true, 'can_reply' => true, 'replying' => true)));
	$watcher_html = _render_tree_node($watcher, 0, 0);
	pg_assert_contains('<button type="submit">Stop</button>', $watcher_html, 'a watcher stops');
	pg_assert_false(strpos($watcher_html, 'Notify me'), 'and is not asked again');
	pg_assert_contains('<a href="/p?r=ab#software_add_comment" data-pg-comment-reply-cancel="" data-pg-comment-replying="">Cancel link</a>', $watcher_html, 'the cancel link shows while replying');

	$visitor = $build();
	pg_comments_apply_control_bindings($visitor, comments_widget_test_controls());
	$visitor_html = _render_tree_node($visitor, 0, 0);
	pg_assert_false(strpos($visitor_html, '<form'), 'no small form for a visitor who may not watch');
	pg_assert_false(strpos($visitor_html, 'Cancel'), 'no cancel without replies');

	$staff = $build();
	pg_comments_apply_control_bindings($staff, comments_widget_test_controls(array('is_staff' => true)));
	$staff_html = _render_tree_node($staff, 0, 0);
	pg_assert_contains('<form id="software_allow_or_disallow_new_comments" action="/software/allow_or_disallow_new_comments.php" method="post">', $staff_html, 'the staff form');
	pg_assert_contains('<input type="hidden" name="action" value="disallow">', $staff_html, 'its hidden fields');
	pg_assert_contains('<button type="submit">Stop new</button>', $staff_html, 'open: close');
	pg_assert_false(strpos($staff_html, 'Allow new'), 'open: no reopen');

	$closed = $build();
	pg_comments_apply_control_bindings($closed, comments_widget_test_controls(array('is_staff' => true, 'allow_new' => false)));
	$closed_html = _render_tree_node($closed, 0, 0);
	pg_assert_true((bool) preg_match('#<button type="submit" class="btn btn-primary">Allow new</button>#', $closed_html), 'closed: the button component reopens');
	pg_assert_false(strpos($closed_html, 'Stop new'), 'closed: no close');
}

function test_comments_widget_row_bindings()
{
	$build = function () {
		return comments_widget_test_node('root', array(), array(
			comments_widget_test_node('semantic', array('tag' => 'div'), array(
				comments_widget_test_link('Reply', 'comment_reply', array('rel' => 'noopener')),
				comments_widget_test_button('Answer', 'comment_reply'),
				comments_widget_test_node('semantic', array('tag' => 'span', 'text' => 'Reply span', '_bindings' => array('action' => 'comment_reply'))),
				comments_widget_test_link('Share', 'comment_share'),
				comments_widget_test_button('Share button', 'comment_share'),
				comments_widget_test_link('Cancel', 'comment_reply_cancel'),
				comments_widget_test_button('Watch', 'comment_watch'),
				comments_widget_test_node('semantic', array('tag' => 'div', 'text' => 'Watchers', '_bindings' => array('section' => 'comment_watchers'))),
				comments_widget_test_node('semantic', array('tag' => 'div', 'text' => 'Pages', '_bindings' => array('section' => 'comment_pagination'))),
				comments_widget_test_form('comment_watch_form', array(comments_widget_test_node('semantic', array('tag' => 'span', 'text' => 'Watch form')))),
				comments_widget_test_form('comment_allow_new_form', array(comments_widget_test_node('semantic', array('tag' => 'span', 'text' => 'Allow form')))),
			)),
		));
	};

	$tree = $build();
	pg_comments_apply_row_bindings($tree, comments_widget_test_controls(array('can_reply' => true)));
	$wrap = $tree['children'][0]['children'][0];
	pg_assert_same('root', $wrap['type'], 'a reply control waits for the row\'s answer');
	pg_assert_same(array('eo_visible_if' => 'pg_comment_reply_ok'), $wrap['props']['_bindings'], 'on the internal flag');
	$html = _render_tree_node($tree, 0, 0);
	pg_assert_contains('<a href="^^__comment_reply_url^^" rel="noopener nofollow" data-pg-comment-reply="^^__comment_id^^" data-pg-comment-reply-name="^^__comment_name^^">Reply</a>', $html, 'the reply link');
	pg_assert_contains('<button type="button" data-pg-comment-reply="^^__comment_id^^" data-pg-comment-reply-name="^^__comment_name^^">Answer</button>', $html, 'the reply button');
	pg_assert_false(strpos($html, 'Reply span'), 'reply on what is no link or button');
	pg_assert_contains('<a href="^^__comment_url^^" rel="nofollow" data-pg-comment-share="" data-pg-comment-url="^^__comment_url^^" data-pg-comment-copied="Link copied." data-pg-comment-copy-prompt="Copy the link to this comment:">Share</a>', $html, 'the share link');
	pg_assert_contains('<button type="button" data-pg-comment-share="" data-pg-comment-url="^^__comment_url^^" data-pg-comment-copied="Link copied." data-pg-comment-copy-prompt="Copy the link to this comment:">Share button</button>', $html, 'the share button');
	foreach (array('Cancel', 'Watch', 'Watchers', 'Pages', 'Watch form', 'Allow form') as $gone) {
		pg_assert_false(strpos($html, '>' . $gone . '<'), 'not in a comment: ' . $gone);
	}

	$visitor = $build();
	pg_comments_apply_row_bindings($visitor, comments_widget_test_controls());
	$visitor_html = _render_tree_node($visitor, 0, 0);
	pg_assert_false(strpos($visitor_html, 'Reply') || strpos($visitor_html, 'Answer'), 'no reply control when the visitor may not answer');
	pg_assert_contains('>Share</a>', $visitor_html, 'everybody may share');
}

function test_comments_widget_small_form_hidden_html()
{
	$add = pg_comments_small_form_hidden_html('<input type="hidden" name="token" value="t">', '/p?x="1"&y=^^z^^', '7', 'submitted_form', '12', 'add');
	pg_assert_same('<input type="hidden" name="token" value="t">'
		. '<input type="hidden" name="send_to" value="/p?x=&quot;1&quot;&amp;y=&#94;&#94;z&#94;&#94;">'
		. '<input type="hidden" name="page_id" value="7">'
		. '<input type="hidden" name="item_id" value="12">'
		. '<input type="hidden" name="item_type" value="submitted_form">'
		. '<input type="hidden" name="action" value="add">', $add, 'the watch form');

	$remove = pg_comments_small_form_hidden_html('', '/p', 7, '', 0, 'remove', array('management' => true, 'id' => '33'));
	pg_assert_contains('<input type="hidden" name="action" value="remove"><input type="hidden" name="management" value="true"><input type="hidden" name="id" value="33">', $remove, 'a watcher manager removes one');
	pg_assert_contains('name="page_id" value="0"', pg_comments_small_form_hidden_html('', '/p', array(7), '', 0, 'allow'), 'an id that is no value is 0');
	pg_assert_contains('name="action" value="disallow"', pg_comments_small_form_hidden_html('', '/p', 7, '', 0, 'disallow'), 'staff close');

	foreach (array('delete', '', 'ADD', array('add'), null) as $bad) {
		pg_assert_same('', pg_comments_small_form_hidden_html('', '/p', 7, '', 0, $bad), 'not an action: ' . var_export($bad, true));
	}
}

function test_comments_widget_watcher_helpers()
{
	pg_assert_same('', pg_comments_watcher_count_text(0, false, 'comment'), 'nobody');
	pg_assert_same('1 person will be notified when a comment is added.', pg_comments_watcher_count_text(1, false, 'comment'), 'one, not the visitor');
	pg_assert_same('', pg_comments_watcher_count_text(1, true, 'comment'), 'one: the visitor');
	pg_assert_same('3 people will be notified when a reply is added.', pg_comments_watcher_count_text('3', true, 'reply'), 'three');
	pg_assert_same('', pg_comments_watcher_count_text(array(3), false, 'comment'), 'not a number');

	$masks = array(
		array('jane@example.com', 'j***@example.com'),
		array('Ölçü@örnek.com', 'Ö***@örnek.com'),
		array('@example.com', '***@example.com'),
		array('nobody', 'n***'),
		array('', ''),
		array(array('x'), ''),
	);
	foreach ($masks as $mask) {
		pg_assert_same($mask[1], pg_comments_mask_email($mask[0]), 'mask ' . var_export($mask[0], true));
	}

	pg_assert_same('jane', pg_comments_watcher_label('jane', 'jane@example.com', false), 'a member, address hidden');
	pg_assert_same('jane (jane@example.com)', pg_comments_watcher_label('jane', 'jane@example.com', true), 'a member, address shown');
	pg_assert_same('Jane@Example.com', pg_comments_watcher_label('Jane@Example.com', 'jane@example.com', true), 'a username that is the address');
	pg_assert_same('j***@example.com', pg_comments_watcher_label('', 'jane@example.com', false), 'an address only, masked');
	pg_assert_same('jane@example.com', pg_comments_watcher_label('', 'jane@example.com', true), 'an address only, shown');
	pg_assert_same('', pg_comments_watcher_label('', '', true), 'nobody');

	$html = pg_comments_watchers_html(array(
		'action_url' => '/software/add_or_remove_watcher.php',
		'token_field' => '<input type="hidden" name="token" value="t">',
		'send_to' => '/p?r=ab',
		'page_id' => 7,
		'item_type' => 'submitted_form',
		'item_id' => 12,
		'fixed' => array('editor (editor@example.com)', 's***@example.com'),
		'watchers' => array(array('id' => 31, 'label' => 'jane'), array('id' => 32, 'label' => 'b^^x^^<i>')),
		'typed' => 'new^^y^^"',
	));
	pg_assert_same(0, strpos($html, '<div class="pg-comment-watchers"><ul class="list-unstyled mb-2">'), 'the list first');
	pg_assert_contains('<li class="fst-italic mb-1">editor (editor@example.com)</li>', $html, 'the form editor, not removable');
	pg_assert_contains('<li class="fst-italic mb-1">s***@example.com</li>', $html, 'the submitter, not removable');
	pg_assert_same(2, substr_count($html, 'name="action" value="remove"'), 'a remove form for each watcher');
	pg_assert_same(3, substr_count($html, 'name="management" value="true"'), 'every form manages');
	pg_assert_contains('name="action" value="remove"><input type="hidden" name="management" value="true"><input type="hidden" name="id" value="31">', $html, 'the watcher it removes');
	pg_assert_contains('<span>b&#94;&#94;x&#94;&#94;&lt;i&gt;</span>', $html, 'a label escaped, carets as entities');
	pg_assert_contains('aria-label="Remove the watcher jane"', $html, 'the remove button is named');
	pg_assert_contains('name="action" value="add">', $html, 'the add form');
	pg_assert_contains('<label class="visually-hidden" for="comment_watcher_add">Username or e-mail address</label>', $html, 'its label');
	pg_assert_contains('id="comment_watcher_add" name="username_or_email_address" maxlength="100" placeholder="username or email address" value="new&#94;&#94;y&#94;&#94;&quot;"', $html, 'the typed value kept, escaped');
	pg_assert_contains('<button type="submit" class="btn btn-sm btn-outline-secondary">Add Watcher</button>', $html, 'the add button');
	pg_assert_same(3, substr_count($html, '<input type="hidden" name="token" value="t">'), 'every form carries the token');
	pg_assert_false(strpos($html, 'style='), 'no inline style');

	$empty = pg_comments_watchers_html(array('action_url' => '/w', 'watchers' => 'x', 'fixed' => array('')));
	pg_assert_false(strpos($empty, '<ul'), 'no list without entries');
	pg_assert_contains('name="username_or_email_address"', $empty, 'the add box still');
}

// The pagination can end its links at an anchor; without one it prints what
// it always printed.
function test_comments_widget_pagination_fragment()
{
	$saved = $_GET;
	$_GET = array('r' => 'ab', 'comments_page' => '2');

	$old = '<nav class="pg-sw-pagination" aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center mt-3 mb-0">'
		. '<li class="page-item"><a class="page-link" href="?r=ab&amp;comments_page=1" aria-label="Previous">&laquo;</a></li>'
		. '<li class="page-item"><a class="page-link" href="?r=ab&amp;comments_page=1">1</a></li>'
		. '<li class="page-item active" aria-current="page"><span class="page-link">2</span></li>'
		. '<li class="page-item"><a class="page-link" href="?r=ab&amp;comments_page=3">3</a></li>'
		. '<li class="page-item"><a class="page-link" href="?r=ab&amp;comments_page=3" aria-label="Next">&raquo;</a></li></ul></nav>';
	pg_assert_same($old, pg_sw_pagination_html('comments_page', 2, 3), 'no anchor: as before');

	$with = pg_sw_pagination_html('comments_page', 2, 3, 'software_comments');
	preg_match_all('/href="([^"]*)"/', $with, $hrefs);
	pg_assert_same(4, count($hrefs[1]), 'four links');
	foreach ($hrefs[1] as $href) {
		pg_assert_same('#software_comments', substr($href, -18), 'ends at the anchor: ' . $href);
	}
	pg_assert_same($old, str_replace('#software_comments"', '"', $with), 'nothing else changes');
	pg_assert_same('', pg_sw_pagination_html('comments_page', 1, 1, 'software_comments'), 'one page: nothing');

	$_GET = $saved;
}

// -- the default layout in three places ----------------------------------------

// The starter's case block in style_designer.js: from its case line to the
// next case of _buildStarterTree() or its default, so a case added after it
// is never read as part of it.
function comments_widget_test_starter_block()
{
	$js = comments_widget_test_source('assets/js/style_designer.js');
	$start = strpos($js, "\n            case 'comments': {");

	if ($start === false) {
		return '';
	}

	$rest = substr($js, $start + 1);

	if (!preg_match("/\n            (?:case '|default: \\{)/", $rest, $m, PREG_OFFSET_CAPTURE)) {
		return '';
	}

	return substr($rest, 0, $m[0][1]);
}

// Each binding object of the starter, as sorted key:value pairs, and
// 'messages' for the Messages node, in source order.
function comments_widget_test_js_bindings($block)
{
	preg_match_all("/_bindings:\\s*\\{([^}]*)\\}|contentType:\\s*'messages'/", $block, $hits, PREG_SET_ORDER);
	$out = array();

	foreach ($hits as $hit) {
		if (strpos($hit[0], '_bindings') !== 0) {
			$out[] = 'messages';
			continue;
		}

		preg_match_all("/(\\w+):\\s*'([^']*)'/", $hit[1], $pairs, PREG_SET_ORDER);
		$list = array();
		foreach ($pairs as $pair) {
			$list[] = $pair[1] . ':' . $pair[2];
		}
		sort($list);
		$out[] = implode(',', $list);
	}

	return $out;
}

// The same, walking the PHP tree depth first; with $key, that prop instead.
function comments_widget_test_php_walk($node, &$out, $key = '')
{
	if (!is_array($node)) {
		return;
	}

	if ($key !== '') {
		if (isset($node['props'][$key])) {
			$out[] = (string) $node['props'][$key];
		}
	} else {
		if (isset($node['props']['contentType']) && ($node['props']['contentType'] === 'messages')) {
			$out[] = 'messages';
		}

		if (!empty($node['props']['_bindings'])) {
			$list = array();
			foreach ($node['props']['_bindings'] as $name => $value) {
				$list[] = $name . ':' . $value;
			}
			sort($list);
			$out[] = implode(',', $list);
		}
	}

	foreach ((isset($node['children']) && is_array($node['children'])) ? $node['children'] : array() as $child) {
		comments_widget_test_php_walk($child, $out, $key);
	}
}

function test_comments_widget_default_tree_matches_starter()
{
	$block = comments_widget_test_starter_block();
	pg_assert_true($block !== '', 'the starter is found');
	pg_assert_same(1, substr_count($block, "case '"), 'the block holds one case');

	$js = comments_widget_test_js_bindings($block);
	$php = array();
	comments_widget_test_php_walk(pg_comments_default_tree(), $php);
	pg_assert_true(count($js) > 30, 'the starter binds its elements: ' . count($js));
	pg_assert_same($js, $php, 'the same bindings in the same order');

	$form = array_search('action:comment_form,eo_visible_if:can_comment', $php, true);
	pg_assert_true(($form !== false) && isset($php[$form + 1]) && ($php[$form + 1] === 'messages'), 'the Messages node comes first in the form');
	$watch = array_search('action:comment_watch_form', $php, true);
	pg_assert_true(($watch !== false) && isset($php[$watch + 1]) && ($php[$watch + 1] === 'messages'), 'and first in the watch form');
	pg_assert_same(2, count(array_keys($php, 'messages', true)), 'two Messages nodes');

	foreach (array('action:comment_reply', 'action:comment_share', 'action:comment_reply_cancel', 'section:comment_pagination', 'eo_visible_if:is_replying',
		'action:comment_watch_form', 'action:comment_watch', 'action:comment_unwatch', 'section:comment_watchers', 'action:comment_allow_new_form',
		'action:comment_close', 'action:comment_reopen', 'eo_visible_if:comment_is_reply', 'eo_visible_if:comment_has_quote', 'eo_visible_if:is_staff',
		'href:__comment_parent_url,text:__comment_parent_name', 'text:__comment_quote', 'text:__reply_to_name', 'text:__watcher_count_text') as $binding) {
		pg_assert_true(in_array($binding, $php, true), 'the default layout binds ' . $binding);
	}

	// The list keeps its place: container children 1 (the list) and 3 (the
	// add-comment box, its form fifth) did not move.
	$container = pg_comments_default_tree()['children'][0]['children'];
	pg_assert_same('Comment List', $container[1]['props']['customName'], 'the list second');
	pg_assert_same('comment_form', $container[3]['children'][4]['props']['_bindings']['action'], 'the form where it was');
	pg_assert_same('Watch', $container[4]['props']['customName'], 'the watch box after it');
	pg_assert_contains('pg-hide-if-empty', $container[4]['props']['cssClass'], 'which leaves when all it holds left');
	pg_assert_same('Staff Controls', $container[5]['props']['customName'], 'the staff controls last');

	preg_match_all("/cssClass:\\s*'([^']*)'/", $block, $classes);
	$php_classes = array();
	comments_widget_test_php_walk(pg_comments_default_tree(), $php_classes, 'cssClass');
	pg_assert_same($classes[1], $php_classes, 'the same classes in the same order');

	preg_match_all("/\\btag:\\s*'([^']*)'/", $block, $tags);
	$php_tags = array();
	comments_widget_test_php_walk(pg_comments_default_tree(), $php_tags, 'tag');
	pg_assert_same($tags[1], $php_tags, 'the same elements in the same order');

	$split = _split_widget_tree(pg_comments_default_tree());
	pg_assert_true(is_array($split['loop_children']) && (count($split['loop_children']) === 1), 'one comment card repeats');
	pg_assert_false(strpos($block, 'createNode(\'loop_area\', {}, []'), 'the starter\'s loop_area is not empty');
}

// -- the draw ------------------------------------------------------------------

function comments_widget_test_draw_data($over = array())
{
	$facts = (isset($over['facts']) ? $over['facts'] : array()) + array('count' => 1);
	$thread = pg_comments_widget_thread_flags(comments_widget_test_facts($facts));
	$row = array('id' => '7', 'published' => '1', 'publish_date_and_time' => '0000-00-00 00:00:00');
	$flags = pg_comments_widget_row_flags($row, false, comments_widget_test_state(array('rating_on' => false)));

	return (isset($over['data']) ? $over['data'] : array()) + array(
		'widget_id' => 5,
		'thread_flags' => $thread,
		'thread_values' => array(
			'__comments_title' => '1 Comment',
			'__comments_count' => '1',
			'__comments_label' => 'Comment',
			'__comments_label_plural' => 'Comments',
			'__comments_intro' => '',
			'__comments_closed_message' => 'No longer open',
			'__comments_show_all_url' => '',
			'__comments_show_featured_url' => '',
			'__login_url' => '',
			'__comment_author_name' => '',
		),
		'rows' => array(array(
			'id' => 7,
			'flags' => $flags,
			'values' => array(
				'__comment_id' => '7',
				'__comment_anchor' => 'c-7',
				'__comment_name' => '^^__comments_count^^ <b>',
				'__comment_staff_name' => '',
				'__comment_message_html' => 'Hi ^^__login_url^^',
				'__comment_date' => '',
				'__comment_edit_url' => '',
			),
			'datetime' => '',
		)),
		'form' => comments_widget_test_form_ctx(array('state' => comments_widget_test_form_state(array('files_on' => false, 'watcher_box' => false, 'rating_on' => false, 'captcha_on' => false, 'can_publish' => false)))),
		'sections' => array('comment_captcha' => '', 'comment_rating_input' => '', 'comment_publish' => ''),
		'slots' => array('pg_comment_form_message' => 'Typed &#94;&#94;x&#94;&#94;', 'pg_comment_form_name' => '', 'pg_comment_form_name_options' => '', 'pg_comment_form_label' => 'comment'),
		'edit_chips' => false,
		'controls' => comments_widget_test_controls(),
	);
}

function comments_widget_test_stamped_tree()
{
	$tree = pg_comments_default_tree();
	_pg_inject_messages_node($tree, 'add_comment');
	return $tree;
}

function test_comments_widget_draw_end_to_end()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), comments_widget_test_draw_data());
	pg_assert_same(0, strpos($html, '<a id="software_comments"></a>'), 'the #software_comments anchor first');
	pg_assert_same(1, substr_count($html, 'id="c-7"'), 'the comment\'s anchor once');
	pg_assert_contains('&#94;&#94;__comments_count&#94;&#94; &lt;b&gt;', $html, 'the name escaped once, carets as entities');
	pg_assert_contains('Hi &#94;&#94;__login_url&#94;&#94;', $html, 'a token typed into the text stays text');
	pg_assert_false(strpos($html, '<b>'), 'no markup from the visitor');
	pg_assert_false(strpos($html, '^^'), 'no token left');
	pg_assert_contains('<h2 class="h5 mb-0"', $html, 'the heading');
	pg_assert_contains('>1 Comment</h2>', $html, 'with the title');
	pg_assert_false(strpos($html, 'class="small text-body-secondary"'), 'no staff name for a visitor');
	pg_assert_false(strpos($html, 'No comments yet.'), 'no empty note with a comment');
	pg_assert_false(strpos($html, 'No longer open'), 'no closed note while open');
	pg_assert_contains('<form id="software_add_comment"', $html, 'the form');
	pg_assert_true((bool) preg_match('#<textarea id="comment_message"[^>]*name="message"[^>]*required=""[^>]*>Typed &\\#94;&\\#94;x&\\#94;&\\#94;</textarea>#', $html), 'the message box keeps the typed text');
	pg_assert_contains('data-pg-comment-label="comment"', $html, 'the label for the leave warning');
	pg_assert_contains('name="page_id" value="5"', $html, 'the hidden fields');
	pg_assert_false(strpos($html, '>Sign in</a>'), 'no sign-in link when the visitor may comment');
	pg_assert_same(1, substr_count($html, '<div id="c-7" data-pg-comment-depth="0" class="d-flex mb-3">'), 'the row root carries its anchor and depth');
	pg_assert_false(strpos($html, 'name="watcher"'), 'no watch box when it is off');
	pg_assert_false(strpos($html, 'Featured Links'), 'customName is not printed');
	pg_assert_false(strpos($html, 'pg-comments-list'), 'the designed loop is used');

	$closed = pg_comments_widget_draw(comments_widget_test_stamped_tree(), comments_widget_test_draw_data(array(
		'facts' => array('allow_new' => false),
		'data' => array('form' => comments_widget_test_form_ctx(array('can_comment' => false))),
	)));
	pg_assert_false(strpos($closed, '<form'), 'no form when closed');
	pg_assert_contains('No longer open', $closed, 'the closed note');
	pg_assert_false(strpos($closed, 'Add a comment</h3>'), 'no form heading when closed');

	$login_data = comments_widget_test_draw_data(array(
		'facts' => array('require_login' => true),
		'data' => array('form' => comments_widget_test_form_ctx(array('can_comment' => false))),
	));
	$login_data['thread_values']['__login_url'] = '/sign-in?send_to=%2Fp%23software_add_comment';
	$login = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $login_data);
	pg_assert_true((bool) preg_match('#<a href="/sign-in\\?send_to=%2Fp%23software_add_comment" class="fw-semibold" rel="nofollow"#', $login), 'the sign-in link, nofollow');
	pg_assert_false(strpos($login, '<form'), 'no form until signed in');

	// A layout without a loop_area draws its comments in the default card.
	$bare = comments_widget_test_stamped_tree();
	$bare['children'][0]['children'][1]['children'] = array();
	$fallback = pg_comments_widget_draw($bare, comments_widget_test_draw_data());
	pg_assert_contains('<div class="pg-comments-list">', $fallback, 'the fallback list');
	pg_assert_same(1, substr_count($fallback, 'id="c-7"'), 'with the comment');
	pg_assert_true(strpos($fallback, 'pg-comments-list') < strpos($fallback, 'software_add_comment'), 'before the rest of the layout');

	$empty_loop = comments_widget_test_stamped_tree();
	$empty_loop['children'][0]['children'][1]['children'] = array(array('type' => 'loop_area', 'props' => array(), 'children' => array()));
	$slot = pg_comments_widget_draw($empty_loop, comments_widget_test_draw_data());
	pg_assert_true((bool) preg_match('#<div>\\s*<div class="pg-comments-list">#', $slot), 'an empty loop_area takes the fallback in its place');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// The comments go where the loop_area is. A box the design hides takes them
// with it, as in every other widget; only a layout without a loop_area gets
// the default card in front of the rest.
function test_comments_widget_draw_hidden_list()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$members = comments_widget_test_stamped_tree();
	$members['children'][0]['children'][1]['props']['_bindings'] = array('eo_visible_if' => 'is_signed_in');
	$html = pg_comments_widget_draw($members, comments_widget_test_draw_data());
	pg_assert_false(strpos($html, 'id="c-7"'), 'a list shown only to members has no comment for a visitor');
	pg_assert_false(strpos($html, 'pg-comments-list'), 'and no default card either');
	pg_assert_contains('<form id="software_add_comment"', $html, 'the rest is drawn');

	$signed_in = pg_comments_widget_draw($members, comments_widget_test_draw_data(array('facts' => array('signed_in' => true))));
	pg_assert_same(1, substr_count($signed_in, 'id="c-7"'), 'a member gets the comment');

	// A loop_area inside the form goes with the form.
	$in_form = comments_widget_test_stamped_tree();
	$list = $in_form['children'][0]['children'][1];
	$in_form['children'][0]['children'][1] = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array());
	$in_form['children'][0]['children'][3]['children'][4]['children'][] = $list;
	$closed = pg_comments_widget_draw($in_form, comments_widget_test_draw_data(array(
		'facts' => array('allow_new' => false),
		'data' => array('form' => comments_widget_test_form_ctx(array('can_comment' => false))),
	)));
	pg_assert_false(strpos($closed, 'id="c-7"'), 'no comment when the form that holds the list is dropped');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// A link to a comment's anchor reaches the anchor the comment carries.
function test_comments_widget_draw_anchor_link()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'article'), 'children' => array(
				array('type' => 'content', 'props' => array('contentType' => 'custom_html', 'html' => '<a href="#^^__comment_anchor^^">Link</a>'), 'children' => array()),
			)),
		)),
	));

	$html = pg_comments_widget_draw($tree, comments_widget_test_draw_data());
	pg_assert_contains('<article id="c-7" data-pg-comment-depth="0">', $html, 'the anchor on the comment');
	pg_assert_contains('<a href="#c-7">Link</a>', $html, 'the link reaches it');
}

// The loop_area of a layout that shows a comment's date in a format of the
// design's, and its name.
function comments_widget_test_dated_tree()
{
	return array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'article', 'id' => 'card'), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'span', '_bindings' => array('text' => '__comment_datetime'), '_bindFormats' => array('text' => 'Y')), 'children' => array()),
				array('type' => 'semantic', 'props' => array('tag' => 'span', '_bindings' => array('text' => '__comment_name')), 'children' => array()),
			)),
		)),
	));
}

// Staff get the edit pencil on each comment; the anchor stays the comment's
// own.
function test_comments_widget_draw_edit_chip()
{
	$data = comments_widget_test_draw_data();
	$data['rows'][0]['values']['__comment_edit_url'] = '/software/edit_comment.php?id=7&send_to=%2Fp';
	$data['edit_chips'] = true;

	$html = pg_comments_widget_draw(comments_widget_test_dated_tree(), $data);
	pg_assert_true((bool) preg_match('#<article id="c-7" data-pg-comment-depth="0" class="pg-edit-host"><a class="pg-edit-chip#', $html), 'the pencil in the comment, the anchor on it');
	pg_assert_contains('href="/software/edit_comment.php?id=7&amp;send_to=%2Fp"', $html, 'to the comment\'s edit screen');
	pg_assert_same(1, substr_count($html, 'id="c-7"'), 'one anchor');

	$data['edit_chips'] = false;
	pg_assert_false(strpos(pg_comments_widget_draw(comments_widget_test_dated_tree(), $data), 'pg-edit-chip'), 'no pencil for anyone else');
}

// A row of the draw data: a comment with its place in the thread.
function comments_widget_test_row($id, $depth, $quoted, $reply_ok, $values = array())
{
	$flags = pg_comments_widget_row_flags(array('id' => (string) $id, 'published' => '1'), false, comments_widget_test_state(array('rating_on' => false)),
		array('depth' => $depth, 'quoted' => $quoted, 'replies' => 0));

	return array(
		'id' => $id,
		'flags' => $flags,
		'values' => $values + array(
			'__comment_id' => (string) $id,
			'__comment_anchor' => 'c-' . $id,
			'__comment_name' => 'Writer ' . $id,
			'__comment_message_html' => 'Message ' . $id,
			'__comment_url' => '/p?comment=' . $id . '#c-' . $id,
			'__comment_reply_url' => $reply_ok ? '/p?reply_to=' . $id . '#software_add_comment' : '',
		),
		'datetime' => '',
		'depth' => $depth,
		'reply_ok' => $reply_ok,
	);
}

function comments_widget_test_with_session($callback)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();
	$callback();
	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

// Replies in the default layout: each row says how deep it is drawn, a reply
// that quotes shows the words it answers, the reply link appears where the
// visitor may answer, and the "Replying to" box follows the visitor.
function test_comments_widget_draw_replies()
{
	comments_widget_test_with_session(function () {
		$data = comments_widget_test_draw_data(array('facts' => array('count' => 2, 'replying' => true)));
		$data['controls'] = comments_widget_test_controls(array('can_reply' => true, 'replying' => true));
		$data['thread_values']['__reply_to_name'] = 'Writer 7';
		$data['form']['hidden_html'] = pg_comments_form_hidden_html('', '/p', 5, '', 0, 7);
		$data['rows'] = array(
			comments_widget_test_row(7, 0, false, true),
			comments_widget_test_row(8, 1, true, false, array(
				'__comment_parent_id' => '7',
				'__comment_parent_name' => 'Writer 7',
				'__comment_parent_url' => '#c-7',
				'__comment_quote' => 'Quoted words',
			)),
		);

		$html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $data);
		pg_assert_contains('<div id="c-7" data-pg-comment-depth="0" class="d-flex mb-3">', $html, 'a top-level comment');
		pg_assert_contains('<div id="c-8" data-pg-comment-depth="1" class="d-flex mb-3">', $html, 'a reply');
		pg_assert_same(1, substr_count($html, 'flex-shrink-0 border-start border-2 ms-2 me-3'), 'only the reply is indented');
		pg_assert_same(1, substr_count($html, 'Quoted words'), 'the quote, once');
		pg_assert_true((bool) preg_match('#<a href="\\#c-7" class="fw-semibold link-secondary"[^>]*>Writer 7</a>#', $html), 'naming the comment it answers, linked to it');
		pg_assert_contains('data-pg-comment-reply="7" data-pg-comment-reply-name="Writer 7">Reply</a>', $html, 'the reply link of a comment the visitor may answer');
		pg_assert_contains('href="/p?reply_to=7#software_add_comment"', $html, 'which works without the browser\'s help');
		pg_assert_false(strpos($html, 'data-pg-comment-reply="8"'), 'no reply link where the visitor may not answer');
		pg_assert_contains('data-pg-comment-share="" data-pg-comment-url="/p?comment=8#c-8"', $html, 'every comment can be shared');
		pg_assert_contains('<div class="alert alert-secondary d-flex flex-wrap align-items-center gap-2 py-2 small" data-pg-comment-replying="">', $html, 'the "Replying to" box shows');
		pg_assert_true((bool) preg_match('#<strong data-pg-comment-reply-to-name=""[^>]*>Writer 7</strong>#', $html), 'with the name');
		pg_assert_contains('data-pg-comment-reply-cancel="" data-pg-comment-replying="">Cancel</a>', $html, 'and the way out');
		pg_assert_contains('name="parent_id" value="7"', $html, 'the form answers the comment');
		pg_assert_false(strpos($html, '^^'), 'no token left');

		$data['controls']['replying'] = false;
		$data['thread_flags'] = pg_comments_widget_thread_flags(comments_widget_test_facts(array('count' => 2)));
		$data['thread_values']['__reply_to_name'] = '';
		$idle = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $data);
		// The starter's box carries d-flex, which beats Bootstrap's [hidden]:
		// d-none comes after it.
		pg_assert_contains('<div class="alert alert-secondary d-flex flex-wrap align-items-center gap-2 py-2 small d-none" data-pg-comment-replying="" hidden="">', $idle, 'not replying: the box waits, hidden');
		pg_assert_contains('class="ms-auto d-none" data-pg-comment-reply-cancel="" data-pg-comment-replying="" hidden="">Cancel</a>', $idle, 'and its way out');

		$data['controls']['can_reply'] = false;
		$data['rows'][0]['reply_ok'] = false;
		$none = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $data);
		pg_assert_false(strpos($none, 'data-pg-comment-replying'), 'no replies: no box');
		pg_assert_false(strpos($none, '>Reply</a>'), 'and no reply link');
	});
}

// The watch box and the staff controls of the default layout.
function test_comments_widget_draw_watch_staff()
{
	comments_widget_test_with_session(function () {
		$member = comments_widget_test_draw_data(array('facts' => array('watching_on' => true, 'signed_in' => true)));
		$member['controls'] = comments_widget_test_controls(array('can_watch' => true));
		$member['thread_values']['__watcher_count_text'] = '3 people will be notified when a comment is added.';
		$html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $member);
		pg_assert_contains('<form id="software_watcher" class="d-flex flex-wrap align-items-center gap-2" action="/software/add_or_remove_watcher.php" method="post">', $html, 'the watch form');
		pg_assert_contains('Notify me of new comments', $html, 'asks a member who does not watch');
		pg_assert_false(strpos($html, 'Stop notifying me'), 'and nothing else');
		pg_assert_contains('3 people will be notified when a comment is added.', $html, 'the count');
		pg_assert_false(strpos($html, 'pg-comment-watchers'), 'no watcher list unless the visitor manages it');
		pg_assert_false(strpos($html, 'software_allow_or_disallow_new_comments'), 'no staff form for a member');
		pg_assert_false(strpos($html, '^^'), 'no token left');

		$member['controls']['is_watcher'] = true;
		pg_assert_contains('Stop notifying me', pg_comments_widget_draw(comments_widget_test_stamped_tree(), $member), 'a watcher stops');
		pg_assert_false(strpos(pg_comments_widget_draw(comments_widget_test_stamped_tree(), $member), 'Notify me of new comments'), 'and is not asked');

		$visitor = comments_widget_test_draw_data(array('facts' => array('watching_on' => true)));
		$visitor_html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $visitor);
		pg_assert_false(strpos($visitor_html, 'software_watcher'), 'no watch form when signed out');
		pg_assert_false(strpos($visitor_html, 'pg-hide-if-empty"></div>'), 'an empty count goes');

		$staff = comments_widget_test_draw_data(array('facts' => array('watching_on' => true, 'signed_in' => true, 'staff' => true, 'manage_watchers' => true)));
		$staff['controls'] = comments_widget_test_controls(array('can_watch' => true, 'is_staff' => true));
		$staff['sections'] = array('comment_watchers' => '<div class="pg-comment-watchers">The list</div>');
		$staff_html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $staff);
		pg_assert_contains('<div class="pg-comment-watchers">The list</div>', $staff_html, 'the watcher list');
		pg_assert_contains('<form id="software_allow_or_disallow_new_comments" action="/software/allow_or_disallow_new_comments.php" method="post">', $staff_html, 'the staff form');
		pg_assert_contains('Stop new comments', $staff_html, 'open: close');
		pg_assert_false(strpos($staff_html, 'Allow new comments'), 'open: no reopen');

		$closed = comments_widget_test_draw_data(array(
			'facts' => array('watching_on' => true, 'signed_in' => true, 'staff' => true, 'allow_new' => false),
			'data' => array('form' => comments_widget_test_form_ctx(array('can_comment' => false))),
		));
		$closed['controls'] = comments_widget_test_controls(array('is_staff' => true, 'allow_new' => false));
		$closed_html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $closed);
		pg_assert_contains('Allow new comments', $closed_html, 'closed: reopen');
		pg_assert_false(strpos($closed_html, 'Stop new comments'), 'closed: no close');
		pg_assert_false(strpos($closed_html, 'pg-comment-watchers'), 'the list prints only when given');
	});
}

// add_or_remove_watcher.php answers at #software_watcher with its errors and
// notices. A manager who is not offered the watch form (the record's
// submitter, told of new comments anyway) reads them in the watcher list,
// which carries the anchor; a manager who is offered it reads them once, in
// the watch form.
function test_comments_widget_draw_watchers_feedback()
{
	$tree = comments_widget_test_stamped_tree();
	pg_comments_stamp_form_messages($tree);
	$list = '<div class="pg-comment-watchers">The list</div>';
	$error = 'Sorry, that watcher has already been added.';
	$refused = function () use ($error) {
		$_SESSION = array('software' => array('liveforms' => array('add_or_remove_watcher' => array(12 => array(
			'username_or_email_address' => array('value' => 'jo', 'error' => true, 'error_message' => $error),
		)))));
	};

	comments_widget_test_with_session(function () use ($tree, $list, $error, $refused) {
		$refused();
		$submitter = comments_widget_test_draw_data(array('facts' => array('watching_on' => true, 'signed_in' => true, 'submitter_notified' => true, 'manage_watchers' => true)));
		$submitter['sections'] = array('comment_watchers' => $list);
		pg_assert_true($submitter['thread_flags']['can_manage_watchers'] && !$submitter['thread_flags']['not_watching'] && empty($submitter['controls']['can_watch']), 'a manager who is not asked to watch');
		$html = pg_comments_widget_draw($tree, $submitter);
		pg_assert_false(strpos($html, 'Notify me of new comments'), 'no watch form');
		pg_assert_same(1, substr_count($html, $error), 'the list prints the handler\'s error');
		pg_assert_same(1, substr_count($html, 'id="software_watcher"'), 'and carries the anchor the handler returns to');
		$anchor = strpos($html, 'id="software_watcher"');
		$message = strpos($html, $error);
		pg_assert_true(($anchor !== false) && ($message !== false) && ($anchor < $message) && ($message < strpos($html, $list)), 'anchor, message, list');
		pg_assert_false(isset($_SESSION['software']['liveforms']['add_or_remove_watcher']), 'the state is used up');

		$_SESSION = array('software' => array('liveforms' => array('add_or_remove_watcher' => array(12 => array('notices' => array('The watcher has been removed.'))))));
		pg_assert_contains('The watcher has been removed.', pg_comments_widget_draw($tree, $submitter), 'and its notices');

		$refused();
		$staff = comments_widget_test_draw_data(array('facts' => array('watching_on' => true, 'signed_in' => true, 'staff' => true, 'manage_watchers' => true)));
		$staff['controls'] = comments_widget_test_controls(array('can_watch' => true, 'is_staff' => true));
		$staff['sections'] = array('comment_watchers' => $list);
		$staff_html = pg_comments_widget_draw($tree, $staff);
		pg_assert_same(1, substr_count($staff_html, $error), 'a manager with the watch form: printed once');
		pg_assert_same(1, substr_count($staff_html, 'id="software_watcher"'), 'one anchor, the watch form\'s');
		$form = strpos($staff_html, '<form id="software_watcher"');
		$message = strpos($staff_html, $error);
		pg_assert_true(($form !== false) && ($message > $form) && ($message < strpos($staff_html, $list)), 'in the watch form');

		$refused();
		$member = comments_widget_test_draw_data(array('facts' => array('watching_on' => true, 'signed_in' => true, 'submitter_notified' => true)));
		$member_html = pg_comments_widget_draw($tree, $member);
		pg_assert_false(strpos($member_html, 'software_watcher'), 'no list and no watch form: no anchor');
		pg_assert_false(strpos($member_html, $error), 'and no message');
	});
}

// A signed-out visitor, nobody told yet, no list: the default layout's Watch
// box leaves with its contents instead of drawing an empty rule.
function test_comments_widget_draw_empty_watch_box()
{
	comments_widget_test_with_session(function () {
		$visitor = comments_widget_test_draw_data(array('facts' => array('watching_on' => true)));
		$visitor['thread_values']['__watcher_count_text'] = '';
		pg_assert_false(strpos(pg_comments_widget_draw(comments_widget_test_stamped_tree(), $visitor), 'border-top pt-3 mt-4'), 'no empty Watch box');

		$visitor['thread_values']['__watcher_count_text'] = '2 people will be notified when a comment is added.';
		$counted = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $visitor);
		pg_assert_contains('border-top pt-3 mt-4', $counted, 'a box with words stays');
		pg_assert_contains('2 people will be notified when a comment is added.', $counted, 'with them');
	});
}

// One function of style_designer.js, from its declaration to its closing
// brace.
function comments_widget_test_js_function($js, $name)
{
	$start = strpos($js, "\n    function " . $name . '(');
	$end = ($start === false) ? false : strpos($js, "\n    }\n", $start);

	return (($start === false) || ($end === false)) ? '' : substr($js, $start, $end - $start + 6);
}

// The canvas twin fades what the site leaves out for where it sits: reply
// and share outside the comment, the thread's controls, small forms and
// sections inside it, a button that sends a small form outside that form,
// and a bound form or the watcher list inside any form, bound or not, as
// pg_comments_drop_nested_forms() drops them. Run under Node; skipped
// without it.
function test_comments_widget_canvas_twin_fades_by_place()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$js = comments_widget_test_source('assets/js/style_designer.js');
	$code_js = '';
	foreach (array('_parentIndexFor', 'findParent', '_cfNodeAttr', '_sdFindByPropId', '_sdCommentsPlace', '_swKindOf', '_sdCommentsModeOffBindings', '_sdCommentsModeOff', '_sdCommentsBindingOff') as $name) {
		$body = comments_widget_test_js_function($js, $name);
		pg_assert_true($body !== '', 'style_designer.js has ' . $name . '()');
		$code_js .= $body . "\n";
	}

	$script = <<<'JS'
var dragData = null, _parentIndex = null, _sysWidgetRenderTreeRoot = null, sdDesign = { siteFlags: {} };
function _sdT(s) { return s; }
var _cmt = { pg_comments: '1', pg_comments_rating: '1', pg_comments_email_page: '5', pg_comments_allow_new: '1' };
function _getCmtVal(k) { return _cmt[k] || ''; }
function n(type, props, kids) { return { type: type, props: props || {}, children: kids || [] }; }
function sem(tag, b, kids, name) { return n('semantic', { tag: tag, customName: name, _bindings: b || {} }, kids); }
function link(action, name) { return n('content', { contentType: 'link', customName: name, _bindings: { action: action } }); }
function btn(action, name) { return sem('button', { action: action }, [], name); }
var named = {};
function keep(node) { named[node.props.customName] = node; return node; }
var tree = n('root', {}, [n('container', {}, [
    sem('div', {}, [keep(link('comment_share', 'share outside')), keep(link('comment_reply', 'reply outside'))]),
    n('loop_area', {}, [sem('div', {}, [
        keep(link('comment_reply', 'reply inside')), keep(link('comment_share', 'share inside')),
        keep(link('comment_reply_cancel', 'cancel inside')), keep(btn('comment_close', 'close inside')),
        keep(sem('form', { action: 'comment_watch_form' }, [], 'watch form inside')),
        keep(sem('div', { section: 'comment_watchers' }, [], 'watchers inside')),
        keep(sem('div', { section: 'comment_pagination' }, [], 'pages inside'))
    ])]),
    keep(link('comment_reply_cancel', 'cancel outside')),
    keep(sem('div', { section: 'comment_watchers' }, [], 'watchers outside')),
    sem('form', { action: 'comment_watch_form' }, [sem('div', {}, [keep(btn('comment_watch', 'watch in its form'))]), keep(btn('comment_close', 'close in the watch form'))]),
    sem('form', { action: 'comment_allow_new_form' }, [keep(btn('comment_reopen', 'reopen in its form'))]),
    keep(btn('comment_unwatch', 'unwatch outside a form')),
    sem('form', {}, [sem('div', {}, [keep(sem('div', { section: 'comment_watchers' }, [], 'watchers in a plain form'))]),
        keep(sem('form', { action: 'comment_watch_form' }, [], 'watch form in a plain form')),
        keep(sem('div', { action: 'comment_watch_form' }, [], 'watch div in a plain form'))]),
    keep(sem('form', { action: 'comment_form' }, [
        keep(sem('div', { section: 'comment_watchers' }, [], 'watchers in the comment form')),
        keep(sem('form', { action: 'comment_watch_form' }, [], 'watch form in the comment form')),
        keep(sem('form', { action: 'comment_allow_new_form' }, [], 'staff form in the comment form'))
    ], 'comment form')),
    sem('form', { action: 'comment_watch_form' }, [keep(sem('form', { action: 'comment_form' }, [], 'comment form in the watch form'))])
])]);
_sysWidgetRenderTreeRoot = tree;
var cfg = { regionType: 'comments', per_page: 10 };
var res = {};
Object.keys(named).forEach(function (k) { res[k] = _sdCommentsBindingOff(named[k], cfg); });
process.stdout.write(JSON.stringify(res));
JS;

	$file = tempnam(sys_get_temp_dir(), 'pgcw');
	file_put_contents($file, $code_js . $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
	$res = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($res), 'the twin answers');
	if (!is_array($res)) {
		return;
	}

	foreach (array('reply outside', 'share outside') as $name) {
		pg_assert_same('Off — works only inside a comment', $res[$name], $name);
	}
	foreach (array('cancel inside', 'close inside', 'watch form inside', 'watchers inside', 'pages inside') as $name) {
		pg_assert_same('Off — has no place inside a comment', $res[$name], $name);
	}
	foreach (array('close in the watch form', 'unwatch outside a form') as $name) {
		pg_assert_same('Off — works only inside its own form', $res[$name], $name);
	}
	foreach (array('watchers in a plain form', 'watch form in a plain form', 'watchers in the comment form', 'watch form in the comment form',
		'staff form in the comment form', 'comment form in the watch form') as $name) {
		pg_assert_same('Off — a form cannot sit inside another form', $res[$name], $name);
	}
	foreach (array('reply inside', 'share inside', 'cancel outside', 'watchers outside', 'watch in its form', 'reopen in its form',
		'comment form', 'watch div in a plain form') as $name) {
		pg_assert_same('', $res[$name], $name . ' is drawn');
	}
}

// The page links: in the list where the design put them, after the comments
// when it put none, nothing on a single page.
function test_comments_widget_draw_pagination()
{
	comments_widget_test_with_session(function () {
		$nav = '<nav class="pg-sw-pagination">Pages</nav>';
		$data = comments_widget_test_draw_data();
		$data['sections'] = array('comment_pagination' => $nav);

		$html = pg_comments_widget_draw(comments_widget_test_stamped_tree(), $data);
		pg_assert_same(1, substr_count($html, $nav), 'once');
		pg_assert_true((bool) preg_match('#<div class="mt-3">\s*' . preg_quote($nav, '#') . '#', $html), 'in its box');
		pg_assert_true(strpos($html, 'id="c-7"') < strpos($html, $nav) && strpos($html, $nav) < strpos($html, 'software_add_comment'), 'after the comments, before the form');

		$bare = array('type' => 'root', 'props' => array(), 'children' => array(
			array('type' => 'loop_area', 'props' => array(), 'children' => array(
				array('type' => 'semantic', 'props' => array('tag' => 'article', '_bindings' => array('text' => '__comment_name')), 'children' => array()),
			)),
			array('type' => 'semantic', 'props' => array('tag' => 'p', 'text' => 'After'), 'children' => array()),
		));
		$fallback = pg_comments_widget_draw($bare, $data);
		pg_assert_same(1, substr_count($fallback, $nav), 'a layout without the section still gets them');
		pg_assert_true(strpos($fallback, '</article>') < strpos($fallback, $nav) && strpos($fallback, $nav) < strpos($fallback, 'After'), 'right after the comments');

		$list = array('type' => 'root', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'ul'), 'children' => array(
				array('type' => 'loop_area', 'props' => array(), 'children' => array(
					array('type' => 'semantic', 'props' => array('tag' => 'li', '_bindings' => array('text' => '__comment_name')), 'children' => array()),
				)),
			)),
		));
		$in_list = pg_comments_widget_draw($list, $data);
		pg_assert_true(strpos($in_list, '</ul>') < strpos($in_list, $nav), 'never inside a list');
		pg_assert_false(strpos($in_list, 'pg-loop-'), 'no marker left');

		$data['sections'] = array('comment_pagination' => '');
		pg_assert_false(strpos(pg_comments_widget_draw(comments_widget_test_stamped_tree(), $data), 'Pages'), 'one page: nothing');
		pg_assert_false(strpos(pg_comments_widget_draw($bare, $data), 'pg-sw-pagination'), 'nor in a layout without the section');
	});
}

// The date takes the design's format before the visitor's text goes in, so a
// token typed into a name is never read as the date. Drawn in a PHP process
// of its own: formatting a date reads USER_LOGGED_IN and USER_TIMEZONE, which
// the tests after this one must not find defined.
function test_comments_widget_draw_datetime()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return;
	}

	$data = comments_widget_test_draw_data();
	$data['rows'][0]['datetime'] = '2026-10-24 10:00:00';
	$data['rows'][0]['values']['__comment_name'] = '^^__comment_datetime^^';
	$file = @tempnam(sys_get_temp_dir(), 'pgcomments');

	if ($file === false) {
		return;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('USER_LOGGED_IN', false);\n"
		. "define('USER_TIMEZONE', '');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. 'echo pg_comments_widget_draw(' . var_export(comments_widget_test_dated_tree(), true) . ', ' . var_export($data, true) . ");\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$html = implode("\n", $out);

	pg_assert_same(0, $code, 'drawn: ' . $html);
	pg_assert_contains('>2026</span>', $html, 'the date in the design\'s format');
	pg_assert_contains('>&#94;&#94;__comment_datetime&#94;&#94;</span>', $html, 'a typed token is not a date');
}

// -- wiring --------------------------------------------------------------------

function test_comments_widget_module_wiring()
{
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_comments.php');", comments_widget_test_source('functions.php'), 'loaded with the other widgets');

	$designer = comments_widget_test_source('includes/fn/designer.php');
	pg_assert_contains("if (\$region_type === 'comments') {", $designer, 'dispatched');
	pg_assert_contains('return _render_system_widget_comments($tree_json, $sid, $cfg, $mode);', $designer, 'to its renderer');

	$module = comments_widget_test_source('includes/fn/widgets_comments.php');
	pg_assert_contains("_pg_inject_messages_node(\$tree, 'add_comment');", $module, 'its Messages node shows the add_comment errors');
	pg_assert_same('Comments', pg_sw_type_labels()['comments'], 'named for the panel');

	$start = strpos($module, 'function _render_system_widget_comments(');
	$renderer = ($start === false) ? '' : substr($module, $start);
	pg_assert_contains("'parent_id' => true", $renderer, 'the rows carry their parent');
	pg_assert_contains("'thread_key' => true", $renderer, 'and their thread');
	pg_assert_contains("\$search ? 0 : \$options['per_page']", $renderer, 'the search index is never paged');
	$inject = strpos($renderer, "_pg_inject_messages_node(\$tree, 'add_comment');");
	$stamp = strpos($renderer, 'pg_comments_stamp_form_messages($tree);');
	pg_assert_true(($inject !== false) && ($stamp !== false) && ($inject < $stamp), 'the small forms\' Messages are re-stamped after');
	$draw = strpos($renderer, 'pg_comments_widget_draw($tree');
	$watcher_form = strpos($renderer, "(new liveform('add_or_remove_watcher', \$page_id))->remove_form();");
	pg_assert_true(($draw !== false) && ($watcher_form !== false) && ($draw < $watcher_form), 'the watcher form state is cleared after the draw');
	pg_assert_contains("pg_sw_pagination_html('comments_page', \$paged['page'], \$paged['pages'], 'software_comments')", $renderer, 'the page links land on the thread');
	pg_assert_contains('pg_comment_reply_to(', $renderer, '?reply_to= is checked before it fills the form');

	foreach (array('frontend.src.js', 'frontend.min.js') as $file) {
		$js = comments_widget_test_source($file);
		foreach (array('init_comment_forms', 'data-pg-comment-form', 'data-pg-comment-submit', 'data-pg-comment-publish-select', 'data-pg-comment-publish-schedule', 'data-pg-comment-label',
			'init_comment_actions', 'data-pg-comment-reply', 'data-pg-comment-reply-cancel', 'data-pg-comment-replying', 'data-pg-comment-reply-to-name',
			'data-pg-comment-share', 'data-pg-comment-url', 'input[name="parent_id"]', 'data-pg-comment-copied', 'data-pg-comment-copy-prompt') as $needle) {
			pg_assert_contains($needle, $js, $file . ': ' . $needle);
		}
		pg_assert_true((bool) preg_match('/init_comment_forms\(\)[;,]\s*software\.init_comment_actions\(\)/', $js), $file . ': both run on ready');
		pg_assert_true((bool) preg_match('/\[data-pg-comment-replying\]["\']\)\.prop\(["\']hidden["\'],\s*(id === \'0\'|"0"===e)\)\.toggleClass\(["\']d-none["\'],\s*(id === \'0\'|"0"===e)\)/', $js), $file . ': the reply box shows and hides past a display class');
		pg_assert_contains('init_add_comment_publish', $js, $file . ': the classic form keeps its own');
		// The message may be a textarea or a one-line input.
		pg_assert_contains('.find(\'[name="message"]\')', $js, $file . ': the leave warning finds the message whatever its tag');
		pg_assert_false(strpos($js, 'textarea[name="message"]'), $file . ': not only a textarea');
	}

	// The canvas twin: a page with comments off fades the whole widget, and
	// only a button is offered the send action.
	$designer_js = comments_widget_test_source('assets/js/style_designer.js');
	$twin = strpos($designer_js, 'function _sdCommentsBindingOff(');
	$twin_end = ($twin === false) ? false : strpos($designer_js, "\n    }\n", $twin);
	pg_assert_true(($twin !== false) && ($twin_end !== false), 'the canvas twin');
	if (($twin !== false) && ($twin_end !== false)) {
		$body = substr($designer_js, $twin, $twin_end - $twin);
		pg_assert_contains("_getCmtVal('pg_comments') !== '1'", $body, 'the page\'s comments switch');
		pg_assert_contains("b.action === 'comment_submit'", $body, 'a send action on what is no button');
		pg_assert_false(strpos($body, 'turn it on in the settings panel'), 'no pointer to a panel the widget lacks');
	}
	pg_assert_contains("if (region === 'comments' && (isRealButton || cur.action === 'comment_submit')) {", $designer_js, 'the send action offered to buttons');

	if (($twin !== false) && ($twin_end !== false)) {
		$body = substr($designer_js, $twin, $twin_end - $twin);
		foreach (array('comment_watch_form', 'comment_unwatch', 'comment_watchers', 'comment_reply', 'comment_share', 'comment_reply_cancel', 'comment_close', 'comment_reopen', 'comment_pagination', "_getCmtVal('pg_comments_allow_new')") as $needle) {
			pg_assert_contains($needle, $body, 'the canvas twin knows ' . $needle);
		}
	}
	foreach (array("['comment_watch_form'", "['comment_allow_new_form'", "['comment_reply'", "['comment_share'", "['comment_reply_cancel'", "['comment_watchers'", "['comment_pagination'") as $needle) {
		pg_assert_contains($needle, $designer_js, 'the panel offers ' . $needle);
	}
}

// The object member $head opens (up to and including its '{'), through the
// '}' that closes it; braces inside quoted strings and comments are
// skipped. '' when it is not found.
function comments_widget_test_js_member($js, $head)
{
	$start = strpos($js, $head);

	if ($start === false) {
		return '';
	}

	$depth = 0;
	$quote = '';
	$length = strlen($js);

	for ($i = $start + strlen($head) - 1; $i < $length; $i++) {
		$c = $js[$i];

		if ($quote !== '') {
			if ($c === '\\') {
				$i++;
			} elseif ($c === $quote) {
				$quote = '';
			}
			continue;
		}

		if (($c === '/') && (substr($js, $i, 2) === '//')) {
			$end = strpos($js, "\n", $i);
			$i = ($end === false) ? $length : $end;
		} elseif (($c === '/') && (substr($js, $i, 2) === '/*')) {
			$end = strpos($js, '*/', $i + 2);
			$i = ($end === false) ? $length : $end + 1;
		} elseif (($c === '\'') || ($c === '"')) {
			$quote = $c;
		} elseif ($c === '{') {
			$depth++;
		} elseif (($c === '}') && (--$depth === 0)) {
			return substr($js, $start, $i - $start + 1);
		}
	}

	return '';
}

// The watch form, the staff's open-or-close form and the watcher list's
// forms lock their submit buttons once sent, so a double click posts once
// (add_or_remove_watcher.php answers a repeated watch or unwatch with an
// error page), and a page restored from the back/forward cache unlocks
// them. software.init_comment_actions() of both frontend files runs under
// Node against a stand-in for jQuery; skipped without Node.
function test_comments_widget_small_forms_lock_on_submit()
{
	pg_assert_contains('<div class="pg-comment-watchers">', pg_comments_watchers_html(array('action_url' => '/w')), 'the watcher list\'s hook');

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$script = <<<'JS'
var calls = [], bound = [];
var document = { createElement: function () { return {}; } }, window = {}, navigator = {};
function software_$(target) {
    return {
        on: function (event, selector, handler) {
            if (typeof selector === 'function') { handler = selector; selector = ''; }
            bound.push({ target: target === document ? 'document' : (target === window ? 'window' : String(target)), event: event, selector: selector, handler: handler });
            return this;
        },
        find: function (selector) {
            var from = (target && target.form) ? target.form : String(target);
            return { prop: function (name, value) { calls.push([from, selector, name, value]); return this; } };
        },
        prop: function (name, value) { calls.push([String(target), '', name, value]); return this; },
        first: function () { return this; }
    };
}
var software = {};
software.init_comment_actions = INIT;
software.init_comment_actions();
software.init_comment_actions();
var res = { bound: bound.map(function (b) { return [b.target, b.event, b.selector]; }) };
bound.forEach(function (b) {
    if (b.target === 'document' && b.event === 'submit') {
        calls = [];
        b.handler.call({ form: 'the posted form' });
        res.submit = calls;
    }
    if (b.target === 'window' && b.event === 'pageshow') {
        calls = [];
        b.handler({ originalEvent: { persisted: false } });
        res.fresh = calls;
        calls = [];
        b.handler({ originalEvent: { persisted: true } });
        res.restored = calls;
    }
});
process.stdout.write(JSON.stringify(res));
JS;

	foreach (array('frontend.src.js' => 'init_comment_actions: function() {', 'frontend.min.js' => 'init_comment_actions:function(){') as $file => $head) {
		$member = comments_widget_test_js_member(comments_widget_test_source($file), $head);
		pg_assert_true($member !== '', $file . ': init_comment_actions found');
		if ($member === '') {
			continue;
		}

		$tmp = tempnam(sys_get_temp_dir(), 'pgcw');
		file_put_contents($tmp, str_replace('INIT', 'function' . substr($member, strpos($member, '(')), $script));
		$out = array();
		exec('node ' . escapeshellarg($tmp) . ' 2>&1', $out, $code);
		unlink($tmp);
		pg_assert_same(0, $code, $file . ': the Node script runs: ' . implode("\n", array_slice($out, 0, 5)));
		$res = json_decode(implode("\n", $out), true);
		pg_assert_true(is_array($res) && isset($res['submit'], $res['fresh'], $res['restored']), $file . ': a submit and a pageshow handler');
		if (!is_array($res) || !isset($res['submit'], $res['fresh'], $res['restored'])) {
			continue;
		}

		$submit = array();
		foreach ($res['bound'] as $row) {
			if (($row[0] === 'document') && ($row[1] === 'submit')) {
				$submit = array_map('trim', explode(',', $row[2]));
			}
		}
		foreach (array('form#software_watcher', 'form#software_allow_or_disallow_new_comments', '.pg-comment-watchers form') as $hook) {
			pg_assert_true(in_array($hook, $submit, true), $file . ': the submit lock covers ' . $hook);
		}
		pg_assert_same(1, count(array_filter($res['bound'], function ($row) {
			return $row[1] === 'submit';
		})), $file . ': bound once');

		pg_assert_same(1, count($res['submit']), $file . ': one change on submit');
		pg_assert_same(array('the posted form', 'disabled', true), array($res['submit'][0][0], $res['submit'][0][2], $res['submit'][0][3]), $file . ': the sent form\'s buttons locked');
		pg_assert_contains('button[type="submit"]', $res['submit'][0][1], $file . ': its submit buttons');

		pg_assert_same(array(), $res['fresh'], $file . ': a page loaded afresh is left alone');
		pg_assert_true(count($res['restored']) > 0, $file . ': a restored page is unlocked');
		foreach ($res['restored'] as $call) {
			pg_assert_same(array('disabled', false), array($call[2], $call[3]), $file . ': unlocked');
		}
	}
}
