<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the content import framework in
 * includes/fn/importers.php: the kind told from a file's first bytes, a
 * writer's answer, the tally, the report's cap, the undo window and the
 * mapping the screen posts.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');

// A WXR file is RSS with the WordPress export namespace, BOM or not.
function test_import_detect_wordpress()
{
	$wxr = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n"
		. '<rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/">';

	pg_assert_same('wordpress', pg_import_detect_kind($wxr), 'plain');
	pg_assert_same('wordpress', pg_import_detect_kind("\xEF\xBB\xBF" . $wxr), 'bom');

	// RSS of another site is not an export.
	pg_assert_same('', pg_import_detect_kind('<rss version="2.0"><channel>'), 'plain rss');
}

// CSV kinds are told apart by their header line, any case, any separator.
function test_import_detect_csv_kinds()
{
	pg_assert_same('shopify', pg_import_detect_kind("Handle,Title,Body (HTML),Vendor\nshirt,Shirt,<p>x</p>,Acme"), 'shopify products');
	pg_assert_same('shopify', pg_import_detect_kind("First Name,Last Name,Email,Accepts Marketing\r\nA,B,a@b.c,yes"), 'shopify customers before 2023');
	pg_assert_same('shopify', pg_import_detect_kind("Customer ID;First Name;Last Name;Email;Accepts Email Marketing"), 'shopify customers, semicolons');
	pg_assert_same('woocommerce', pg_import_detect_kind("\xEF\xBB\xBF" . 'ID,Type,SKU,Name,Published,"Regular price",Parent'), 'woocommerce');
	pg_assert_same('', pg_import_detect_kind("from,to,code\n/a,/b,301"), 'redirect csv');
	pg_assert_same('', pg_import_detect_kind(''), 'empty');
}

// A writer's answer is one shape; anything else is a failure.
function test_import_writer_answer()
{
	pg_assert_same(array('result' => 'made', 'id' => 7, 'reason' => ''), pg_import_writer_answer(array('result' => 'made', 'id' => '7')), 'made');
	pg_assert_same('failed', pg_import_writer_answer(null)['result'], 'nothing');
	pg_assert_same('failed', pg_import_writer_answer(array('result' => 'done'))['result'], 'unknown result');

	// Made without an id could never be undone.
	pg_assert_same('failed', pg_import_writer_answer(array('result' => 'made', 'id' => 0))['result'], 'made without id');
	pg_assert_same(array('result' => 'skipped', 'id' => 0, 'reason' => 'Same SKU'), pg_import_writer_answer(array('result' => 'skipped', 'reason' => ' Same SKU ')), 'skipped');
}

function test_import_tally_and_totals()
{
	$counts = pg_import_tally(array(), 'record', 'made');
	$counts = pg_import_tally($counts, 'record', 'made');
	$counts = pg_import_tally($counts, 'comment', 'failed');
	$counts = pg_import_tally($counts, 'record', 'skipped');
	$counts = pg_import_tally($counts, 'record', 'other');

	pg_assert_same(array('made' => 2, 'skipped' => 1, 'failed' => 0), $counts['record'], 'record');
	pg_assert_same(array('made' => 0, 'skipped' => 0, 'failed' => 1), $counts['comment'], 'comment');
	pg_assert_same(array('made' => 2, 'skipped' => 1, 'failed' => 1), pg_import_totals($counts), 'totals');
}

// Made rows are not listed; past the cap lines are only counted.
function test_import_report_cap()
{
	$item = array('kind' => 'record', 'source_key' => 'post:1', 'title' => 'Hello');

	$report = pg_import_report_add(array(), 'made', $item, '');
	pg_assert_same(array(), $report['lines'], 'made not listed');

	$noted = pg_import_report_add(array(), 'made', $item, 'Kept at the old address: x');
	pg_assert_same('made', $noted['lines'][0]['result'], 'made with a note listed');

	$report = pg_import_report_add($report, 'failed', $item, 'No title');
	pg_assert_same(array('result' => 'failed', 'kind' => 'record', 'key' => 'post:1', 'title' => 'Hello', 'reason' => 'No title'), $report['lines'][0], 'line');

	$report['lines'] = array_fill(0, PG_IMPORT_REPORT_LINES, $report['lines'][0]);
	$report = pg_import_report_add($report, 'skipped', $item, 'Imported already.');

	pg_assert_same(PG_IMPORT_REPORT_LINES, count($report['lines']), 'capped');
	pg_assert_same(1, $report['more'], 'counted');
}

function test_import_undo_window()
{
	$day = 86400;
	$now = 100 * $day;

	pg_assert_true(pg_import_undo_open('done', $now - (29 * $day), $now), '29 days');
	pg_assert_false(pg_import_undo_open('done', $now - (30 * $day), $now), '30 days');
	pg_assert_true(pg_import_undo_open('failed', 0, $now), 'stopped without end time');
	pg_assert_false(pg_import_undo_open('undone', $now, $now), 'undone');
	pg_assert_false(pg_import_undo_open('dry_run', 0, $now), 'not started');
}

// Only the rows asked for and the choices offered are kept.
function test_import_mapping_clean()
{
	$rows = array(
		'form'   => array('choices' => array('12' => 'Articles', '14' => 'News'), 'required' => true),
		'folder' => array('choices' => array('3' => 'Blog')),
		'group'  => array('choices' => array('5' => 'Shirts'), 'required' => true),
	);

	$clean = pg_import_mapping_clean($rows, array('form' => '14', 'folder' => '9', 'group' => array('5'), 'extra' => '1'));

	pg_assert_same(array('form' => '14'), $clean['mapping'], 'kept');
	pg_assert_same(array('group'), $clean['missing'], 'missing');
}

// A field row offers only the field types its writer fills; an information
// field never.
function test_import_field_fits()
{
	pg_assert_true(pg_import_field_fits('text box', array('text box', 'text area')), 'a listed type');
	pg_assert_true(!pg_import_field_fits('file upload', array('text box', 'text area')), 'a file field does not take the title');
	pg_assert_true(pg_import_field_fits('file upload', array()), 'no list takes every type');
	pg_assert_true(!pg_import_field_fits('information', array()), 'an information field holds no value');
	pg_assert_true(!pg_import_field_fits('', array()), 'no type');

	require_once(PG_FUNCTIONS_DIR . '/includes/import/wordpress.php');
	$mapping = pg_import_wordpress_mapping();
	pg_assert_same(array('file upload'), $mapping['picture']['field_types'], 'the featured image goes to a file field');
	pg_assert_true(!in_array('file upload', $mapping['title']['field_types'], true), 'the title never goes to a file field');
}

// Tree pickers: path labels in tree order, the lone top node out of the
// paths, a left-out node with its subtree dropped.
function test_import_tree_choices()
{
	$rows = array(
		1  => array('parent' => 0, 'name' => 'Root'),
		2  => array('parent' => 1, 'name' => 'Shop'),
		3  => array('parent' => 2, 'name' => 'private'),
		4  => array('parent' => 1, 'name' => 'Blog'),
		5  => array('parent' => 4, 'name' => 'private'),
		9  => array('parent' => 1, 'name' => 'Recycle Bin'),
		10 => array('parent' => 9, 'name' => 'pgbin_10_Old'),
	);

	pg_assert_same(array(
		'1' => 'Root',
		'2' => 'Shop',
		'3' => 'Shop › private',
		'4' => 'Blog',
		'5' => 'Blog › private',
	), pg_import_tree_choices($rows, array(9)), 'folders');

	$groups = array(
		43 => array('parent' => 7, 'name' => 'e-Gift Cards'),
		56 => array('parent' => 8, 'name' => 'e-Gift Cards'),
		7  => array('parent' => 0, 'name' => 'Store'),
		8  => array('parent' => 0, 'name' => 'Outlet'),
	);

	pg_assert_same(array(
		'7'  => 'Store',
		'43' => 'Store › e-Gift Cards',
		'8'  => 'Outlet',
		'56' => 'Outlet › e-Gift Cards',
	), pg_import_tree_choices($groups, array()), 'several top nodes lead their paths');

	pg_assert_same(array(), pg_import_tree_choices(array(), array()), 'empty');
}
