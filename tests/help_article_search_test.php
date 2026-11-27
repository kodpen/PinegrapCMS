<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the article search: the Search Results widget's "Search only in"
 * folder (pg_search_folder_setting(), pg_search_narrow_folders()), the public
 * article suggestions read by article_suggest.php (pg_article_suggest_query(),
 * pg_article_suggest_rows()) and the form control that calls it as the
 * visitor types (pg_cf_mark_article_suggest()). The queries need a database,
 * so their call sites are read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _help_article_search_fn($file, $name)
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($src, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . ' is found');
	$end = strpos($src, "\nfunction ", (int) $start + 10);
	return ($end === false) ? substr($src, (int) $start) : substr($src, (int) $start, (int) $end - (int) $start);
}

function _help_article_search_control($tag, array $attrs, array $cf)
{
	$list = array();
	foreach ($attrs as $k => $v) $list[] = array('name' => $k, 'value' => $v);
	return array('type' => 'semantic', 'props' => array('tag' => $tag, '_attrs' => $list, '_cf' => $cf), 'children' => array());
}

function _help_article_search_attr($node, $name)
{
	foreach ($node['props']['_attrs'] as $a) {
		if ($a['name'] === $name) return $a['value'];
	}
	return null;
}

function test_help_article_search_folder_setting()
{
	pg_assert_same(0, pg_search_folder_setting(array()));
	pg_assert_same(0, pg_search_folder_setting(null));
	pg_assert_same(0, pg_search_folder_setting(array('search_folder_id' => -4)));
	pg_assert_same(0, pg_search_folder_setting(array('search_folder_id' => array(3))));
	pg_assert_same(12, pg_search_folder_setting(array('search_folder_id' => '12')));
	pg_assert_same(7, pg_search_folder_setting(array('search_folder_id' => 7)));
}

function test_help_article_search_narrow_folders()
{
	// Staff (no visitor fence): the folder and its sub-folders.
	pg_assert_same(array(5, 8, 9), pg_search_narrow_folders(array(5, 8, '9', 8, 0, -1), null));
	// A visitor: only the ones they may view.
	pg_assert_same(array(5, 9), pg_search_narrow_folders(array(5, 8, 9), array(1, 5, 9)));
	// None they may view: nothing to search.
	pg_assert_same(array(), pg_search_narrow_folders(array(5, 8), array(1, 2)));
}

function test_help_article_search_suggest_query()
{
	pg_assert_same('', pg_article_suggest_query(null));
	pg_assert_same('', pg_article_suggest_query(array('refund')));
	pg_assert_same('', pg_article_suggest_query('  ab  '));
	pg_assert_same('', pg_article_suggest_query('çı'));
	pg_assert_same('iade', pg_article_suggest_query(' iade '));
	pg_assert_same(100, mb_strlen(pg_article_suggest_query(str_repeat('a', 300)), 'UTF-8'));
}

function test_help_article_search_suggest_rows()
{
	$found = array(
		'featured' => array(array('title' => 'Refunds', 'url' => '/help/refunds', 'excerpt' => 'How a refund works', 'type' => 'record')),
		'catalog'  => array(array('title' => 'A product', 'url' => '/p', 'excerpt' => '', 'type' => 'product')),
		'pages'    => array(
			array('title' => 'Refunds', 'url' => '/help/refunds', 'excerpt' => 'again', 'type' => 'record'),
			array('title' => 'Shipping', 'url' => '/help/shipping', 'excerpt' => str_repeat('x', 400), 'type' => 'record'),
			array('title' => 'A file', 'url' => '/f.pdf', 'excerpt' => '', 'type' => 'file'),
			array('title' => 'Returns', 'url' => '/help/returns', 'excerpt' => '', 'type' => 'page'),
		),
	);
	$rows = pg_article_suggest_rows($found, 5);
	pg_assert_same(array('/help/refunds', '/help/shipping', '/help/returns'), array_column($rows, 'url'));
	pg_assert_same(array('title', 'url', 'excerpt'), array_keys($rows[0]));
	pg_assert_true(mb_strlen($rows[1]['excerpt'], 'UTF-8') <= 161, 'the excerpt is cut');
	pg_assert_same(2, count(pg_article_suggest_rows($found, 2)));
	pg_assert_same(array(), pg_article_suggest_rows(array(), 5));
}

function test_help_article_search_marks_the_subject_control()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_help_article_search_control('input', array('type' => 'text', 'name' => 'subject'), array('suggest_folder' => 12)),
		_help_article_search_control('textarea', array('name' => 'body'), array('suggest_folder' => '0')),
		_help_article_search_control('input', array('type' => 'email', 'name' => 'mail'), array('suggest_folder' => 12)),
		_help_article_search_control('input', array('type' => 'text', 'name' => 'staff'), array('suggest_folder' => 12, 'office_use_only' => true)),
		_help_article_search_control('textarea', array('name' => 'question'), array('suggest_folder' => 4)),
	));
	pg_assert_same(2, pg_cf_mark_article_suggest($tree, '/article_suggest.php'));
	$c = $tree['children'];
	pg_assert_same('/article_suggest.php?folder=12', _help_article_search_attr($c[0], 'data-pg-suggest'));
	pg_assert_same(null, _help_article_search_attr($c[1], 'data-pg-suggest'));
	pg_assert_same(null, _help_article_search_attr($c[2], 'data-pg-suggest'));
	pg_assert_same(null, _help_article_search_attr($c[3], 'data-pg-suggest'));
	pg_assert_same('/article_suggest.php?folder=4', _help_article_search_attr($c[4], 'data-pg-suggest'));
	pg_assert_same('off', _help_article_search_attr($c[0], 'autocomplete'));
	// Marked twice, the attribute is not doubled.
	pg_assert_same(2, pg_cf_mark_article_suggest($tree, '/article_suggest.php'));
	$n = 0;
	foreach ($tree['children'][0]['props']['_attrs'] as $a) if ($a['name'] === 'data-pg-suggest') $n++;
	pg_assert_same(1, $n);
}

function test_help_article_search_wired_in()
{
	$one = _help_article_search_fn('includes/fn/widgets.php', '_pg_sw_search_collect_one');
	pg_assert_true(strpos($one, 'pg_search_folder_setting($cfg)') !== false, 'the widget reads its folder');
	pg_assert_true(strpos($one, 'pg_search_narrow_folders(') !== false, 'the folder narrows the search');
	$collect = _help_article_search_fn('includes/fn/widgets.php', '_pg_sw_search_collect');
	pg_assert_true(strpos($collect, 'pg_search_folder_setting($cfg)') !== false, 'a folder search lists no pinned page');

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/article_suggest.php');
	pg_assert_true(strpos($endpoint, 'pg_article_suggest_throttled()') !== false, 'the endpoint is rate limited');
	pg_assert_true(strpos($endpoint, "'search_folder_id'") !== false, 'the endpoint is narrowed to a folder');
	pg_assert_true(strpos($endpoint, 'no-store') !== false, 'the answer is not cached');

	$throttle = _help_article_search_fn('includes/fn/site_search.php', 'pg_article_suggest_throttled');
	pg_assert_true(strpos($throttle, 'waf_log_event(') !== false, 'a refusal is logged');

	$render = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_true(strpos($render, 'pg_cf_mark_article_suggest($tree_decoded') !== false, 'the custom form marks its controls');
	pg_assert_true(strpos($render, 'pg_cf_article_suggest_script()') !== false, 'the custom form prints the script');
}
