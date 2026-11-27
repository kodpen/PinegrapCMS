<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests: the bar of one Data table's screen
 * (includes/workspace/wtable_screen_bar.php): the count and the tags of
 * what "Filter" sets, the one-line bar, the "⋯" menu and the drawers it
 * opens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// A table's columns: a text, a status, a checkbox and a person.
function ws_wtable_screen_bar_test_columns()
{
	$columns = array(
		'1' => array('Name', 'text', array()),
		'5' => array('State', 'status', array('choices' => array(array('key' => 'open', 'label' => 'Open'), array('key' => 'done', 'label' => 'Done')))),
		'6' => array('Paid', 'checkbox', array()),
		'8' => array('Owner', 'person', array()),
	);
	$out = array();

	foreach ($columns as $id => $column) {
		$out[$id] = array('id' => (int) $id, 'name' => $column[0], 'type' => $column[1], 'options' => ws_wtable_column_options_check($column[1], $column[2])['value']);
	}

	return $out;
}

// The count on "Filter": a condition each, and the order when it is not the
// table's own.
function test_ws_wtable_screen_query_count()
{
	pg_assert_same(0, ws_wtable_screen_query_count(ws_wtable_query_from_get(array())));
	pg_assert_same(1, ws_wtable_screen_query_count(ws_wtable_query_from_get(array('sort' => '5'))));
	pg_assert_same(1, ws_wtable_screen_query_count(ws_wtable_query_from_get(array('dir' => 'desc'))), 'the table order turned round');
	pg_assert_same(3, ws_wtable_screen_query_count(ws_wtable_query_from_get(array('q' => array(array('c' => '1', 'o' => 'contains', 'v' => 'a'), array('c' => '5', 'o' => 'eq', 'v' => 'Done')), 'sort' => '1'))));
}

// A tag a condition in the column's words, then the order; each takes
// itself away and keeps the rest and the totals switch.
function test_ws_wtable_screen_query_tags()
{
	$columns = ws_wtable_screen_bar_test_columns();
	$team = array(array('id' => 12, 'name' => 'Ayşe Kaya'));
	$screen = ws_wtable_query_from_get(array('q' => array(
		array('c' => '5', 'o' => 'eq', 'v' => 'done'),
		array('c' => '6', 'o' => 'eq', 'v' => '1'),
		array('c' => '8', 'o' => 'eq', 'v' => '12'),
		array('c' => '1', 'o' => 'empty', 'v' => ''),
	), 'sort' => '1', 'dir' => 'desc'));
	$tags = ws_wtable_screen_query_tags($columns, $screen, $team, 'workspace_wtables.php?id=7', true);

	pg_assert_same(5, count($tags));
	pg_assert_same('State ' . lang('is') . ' Done', $tags[0]['text'], 'a status by its label');
	pg_assert_same('Paid ' . lang('is') . ' ' . lang('Ticked'), $tags[1]['text']);
	pg_assert_same('Owner ' . lang('is') . ' Ayşe Kaya', $tags[2]['text'], 'a person by name');
	pg_assert_same('Name ' . lang('is empty'), $tags[3]['text'], 'no value for is empty');
	pg_assert_same(lang(array('string' => 'Sorted by {var:1}, {var:2}', 'vars' => array('Name', lang('Descending')))), $tags[4]['text']);

	// The first tag's address keeps the other three lines, the order and the totals.
	parse_str((string) parse_url(str_replace('#ws-wt-rows', '', $tags[0]['url']), PHP_URL_QUERY), $kept);
	pg_assert_same('7', $kept['id']);
	pg_assert_same(3, count($kept['q']));
	pg_assert_same('6', $kept['q'][0]['c']);
	pg_assert_same('1', $kept['sort']);
	pg_assert_same('all', $kept['totals']);

	// The order's tag keeps the conditions and drops the sort.
	parse_str((string) parse_url(str_replace('#ws-wt-rows', '', $tags[4]['url']), PHP_URL_QUERY), $unsorted);
	pg_assert_same(4, count($unsorted['q']));
	pg_assert_false(isset($unsorted['sort']) || isset($unsorted['dir']));

	pg_assert_same(array(), ws_wtable_screen_query_tags($columns, ws_wtable_query_from_get(array()), $team, 'workspace_wtables.php?id=7', false));
}

// The tag line: each tag with its "×" named, then Clear; nothing set, none.
function test_ws_wtable_screen_tags_html()
{
	pg_assert_same('', ws_wtable_screen_tags_html(array(), 'x'));

	$html = ws_wtable_screen_tags_html(array(array('text' => 'A <b>', 'url' => '?id=7&sort=1')), '?id=7');

	pg_assert_contains('<div class="ws-bar-tags">', $html);
	pg_assert_contains('A &lt;b&gt;', $html, 'escaped');
	pg_assert_contains('href="?id=7&amp;sort=1"', $html);
	pg_assert_contains(h(lang(array('string' => 'Remove the filter: {var:1}', 'vars' => array('A <b>')))), $html);
	pg_assert_contains('>' . lang('Clear') . '</a>', $html);
}

// The bar: one line in its frame, New row filled first, the views as a
// segmented control and a select, "Filter" with its count and its box,
// "⋯" last; no #button_bar and no old buttons.
function test_ws_wtable_screen_bar()
{
	$html = ws_wtable_screen_bar(array(
		'new_row' => 'workspace_wtables.php?id=7#ws-wt-row-form',
		'views'   => array(
			array('label' => 'Table', 'icon' => 'bi-table', 'url' => '?id=7', 'here' => true),
			array('label' => 'Board', 'icon' => 'bi-kanban', 'url' => '?id=7&view=board&by=5', 'here' => false),
		),
		'filter'  => '<form></form>',
		'count'   => 2,
		'menu'    => ws_wtable_screen_menu(array(array('label' => 'Columns', 'icon' => 'bi-layout-three-columns', 'drawer' => 'ws-wt-columns'))),
		'tags'    => '<div class="ws-bar-tags"></div>',
	));

	pg_assert_true(strpos($html, '<div class="ws-bar-frame"><nav class="pg-toolbar ws-bar"') === 0, 'one bar line in its frame');
	pg_assert_false(strpos($html, 'button_bar') !== false);
	pg_assert_false((bool) preg_match('/btn btn-|nav-tabs|nav-pills/', $html));
	pg_assert_same(1, substr_count($html, 'pg-button-fill'), 'New row is the one fill');
	pg_assert_true(strpos($html, 'pg-button-fill') < strpos($html, 'ws-bar-views'), 'New row first');
	pg_assert_contains('<a class="pg-seg-item is-active" href="?id=7" aria-current="page">', $html);
	pg_assert_contains('<select class="pg-input pg-input-sm ws-bar-views-select" data-ws-bar-go', $html);
	pg_assert_contains('aria-controls="ws-wt-filter-box"', $html);
	pg_assert_contains('aria-label="' . h(lang(array('string' => 'Filter, {var:1} set', 'vars' => array(2)))) . '"', $html);
	pg_assert_contains('<span class="ws-bar-count" aria-hidden="true">2</span>', $html);
	pg_assert_contains('id="ws-wt-filter-box" role="dialog"', $html);
	pg_assert_true(strpos($html, 'ws-bar-filter') < strpos($html, 'pg-menu-wrap'), '"⋯" last');
	pg_assert_true(strpos($html, '</nav><div class="ws-bar-tags">') !== false, 'the tags under the bar');

	// No views, no filter, no row form: neither is drawn; the count is hidden at 0.
	$bare = ws_wtable_screen_bar(array('new_row' => '', 'views' => array(), 'filter' => '', 'count' => 0, 'menu' => '', 'tags' => ''));
	pg_assert_false(strpos($bare, 'pg-button-fill') !== false || strpos($bare, 'ws-bar-views') !== false || strpos($bare, 'ws-bar-filter') !== false);
	pg_assert_contains('<span class="pg-toolbar-grow"></span>', $bare);
}

// The "⋯" menu: headers, dividers, a drawer, a file, an action with its
// confirm and the red Delete last; nothing but headers, no menu.
function test_ws_wtable_screen_menu()
{
	$html = ws_wtable_screen_menu(array(
		array('header' => 'Show on a canvas'),
		array('label' => 'As a chart', 'icon' => 'bi-bar-chart', 'attrs' => 'data-ws-wt-canvas="chart"'),
		array('divider' => true),
		array('label' => 'Download as Excel', 'icon' => 'bi-file-earmark-spreadsheet', 'href' => '?id=7&download=xlsx', 'download' => true),
		array('label' => 'Shares', 'icon' => 'bi-share', 'drawer' => 'ws-wt-shares'),
		array('label' => 'Delete table', 'icon' => 'bi-trash', 'danger' => true, 'action' => 'table_delete', 'confirm' => 'Delete "A"?'),
	));

	pg_assert_contains('<span class="pg-menu-wrap"><button type="button" class="pg-icon-button ws-wt-more" data-bs-toggle="menu" aria-expanded="false"', $html);
	pg_assert_contains('aria-label="' . h(lang('More actions')) . '"', $html, 'the icon button is named');
	pg_assert_contains('<ul class="menu pg-menu-end ws-wt-menu">', $html);
	pg_assert_contains('<h6 class="menu-header">Show on a canvas</h6>', $html);
	pg_assert_contains('<button type="button" class="menu-item" data-ws-wt-canvas="chart">', $html);
	pg_assert_contains('<hr class="menu-divider">', $html);
	pg_assert_contains('href="?id=7&amp;download=xlsx" download>', $html);
	pg_assert_contains('data-bs-toggle="drawer" data-bs-target="#ws-wt-shares" aria-controls="ws-wt-shares"', $html);
	pg_assert_contains('class="menu-item fg-emphasis-danger ws-wt-action" data-action="table_delete" data-confirm="Delete &quot;A&quot;?"', $html);

	pg_assert_same('', ws_wtable_screen_menu(array(array('header' => 'x'), array('divider' => true))));
}

// A drawer: the old card's anchor as its id, named by its title, the close
// cross and the body as given.
function test_ws_wtable_screen_drawer()
{
	$html = ws_wtable_screen_drawer('ws-wt-columns', 'bi-layout-three-columns', 'blue', 'Columns & more', '<p>x</p>');

	pg_assert_contains('<dialog class="drawer drawer-end ws-wt-drawer" id="ws-wt-columns" aria-labelledby="ws-wt-columns-title">', $html);
	pg_assert_contains('<span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><i class="bi bi-layout-three-columns"></i></span>', $html);
	pg_assert_contains('id="ws-wt-columns-title">Columns &amp; more</h5>', $html);
	pg_assert_contains('data-bs-dismiss="drawer"', $html);
	pg_assert_contains('<div class="drawer-body"><p>x</p></div>', $html);
}

// The screen: the bar drawn by ws_wtable_screen_bar(), the cards that stood
// beside the rows as drawers, the grid on the page's width.
function test_ws_wtable_screen_uses_the_bar()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');

	pg_assert_contains('$toolbar = ws_wtable_screen_bar(array(', $screen);
	pg_assert_false(strpos($screen, '<nav id="button_bar"') !== false, 'no #button_bar left');
	pg_assert_false(strpos($screen, 'xl:col-4') !== false, 'no column of cards beside the rows');

	foreach (array('ws-wt-columns', 'ws-wt-shares', 'ws-wt-import-rows', 'ws-wt-details', 'ws-wt-deleted-rows') as $drawer) {
		pg_assert_contains("ws_wtable_screen_drawer('" . $drawer . "'", $screen);
		pg_assert_contains("'drawer' => '" . $drawer . "'", $screen, $drawer . ' opens from "⋯"');
	}

	pg_assert_contains("'drawer' => 'ws-wt-home'", $screen);
}
