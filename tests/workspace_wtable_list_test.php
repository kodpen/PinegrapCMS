<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Data list of the Workspace (includes/workspace/wtable_list.php): the
 * workflow tables a person reaches, on the list table driver and printed
 * whole. Its scopes and their counts, the columns and the cells of a table
 * and of a deleted one, the segmented controls, and the screen that draws it
 * (workspace_wtables.php without ?id=).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtables.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_list.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $ws_wtable_list_test_name => $ws_wtable_list_test_value) {
	if (!defined($ws_wtable_list_test_name)) {
		define($ws_wtable_list_test_name, $ws_wtable_list_test_value);
	}
}

function ws_wtable_list_test_table(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'user_id' => '3', 'name' => 'Leads <2026>', 'description' => 'Fair & web',
		'row_count' => '24', 'created_at' => '1700000000', 'updated_at' => '1700003600', 'access' => 'owner',
	), $over);
}

function ws_wtable_list_test_person(array $over = array())
{
	return array_merge(array(
		'id' => 3, 'name' => 'Ayşe <Y>', 'initials' => 'AY', 'tone' => 2, 'avatar' => '', 'avatar_kind' => 'user',
	), $over);
}

// The opposite of pg_assert_contains().
function ws_wtable_list_test_lacks($needle, $haystack, $label = '')
{
	pg_assert_true(strpos((string) $haystack, (string) $needle) === false, ($label !== '') ? $label : ('no "' . $needle . '"'));
}

function ws_wtable_list_test_screen()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_wtables.php');
}

function test_ws_wtable_list_scopes_follow_the_store()
{
	pg_assert_same(array('all', 'mine', 'shared'), array_keys(ws_wtable_list_scopes()), 'no Channels segment before the store keeps channel tables');
	pg_assert_same(array('all', 'mine', 'channels', 'shared'), array_keys(ws_wtable_list_scopes(true)), 'Channels stands between Mine and Shared with me');

	pg_assert_same('mine', ws_wtable_list_scope('mine'));
	pg_assert_same('shared', ws_wtable_list_scope('shared'));
	pg_assert_same('all', ws_wtable_list_scope('channels'), 'a scope the store does not have is every table');
	pg_assert_same('channels', ws_wtable_list_scope('channels', true));
	pg_assert_same('all', ws_wtable_list_scope('all'));
	pg_assert_same('all', ws_wtable_list_scope(''));
	pg_assert_same('all', ws_wtable_list_scope('MINE'), 'the address is read as written');
	pg_assert_same('all', ws_wtable_list_scope(array('mine')), 'an array in the address is not a scope');
	pg_assert_same('all', ws_wtable_list_scope(null));
}

function test_ws_wtable_list_scope_of_a_table()
{
	pg_assert_same('mine', ws_wtable_list_scope_of(array('access' => 'owner')));
	pg_assert_same('shared', ws_wtable_list_scope_of(array('access' => 'edit')));
	pg_assert_same('shared', ws_wtable_list_scope_of(array('access' => 'view')));
	pg_assert_same('shared', ws_wtable_list_scope_of(array()), 'a row without an access is never the person\'s own');
	pg_assert_same('mine', ws_wtable_list_scope_of(array('access' => 'owner', 'channel_id' => '0')), 'no channel is a person\'s table');
	pg_assert_same('channels', ws_wtable_list_scope_of(array('access' => 'owner', 'channel_id' => '5')), 'a channel\'s table is the channel\'s, whoever looks');
	pg_assert_same('channels', ws_wtable_list_scope_of(array('access' => 'view', 'channel_id' => 5)));
}

function test_ws_wtable_list_counts_and_pick()
{
	$tables = array(
		ws_wtable_list_test_table(array('id' => '1')),
		ws_wtable_list_test_table(array('id' => '2', 'access' => 'edit')),
		ws_wtable_list_test_table(array('id' => '3', 'access' => 'view')),
		ws_wtable_list_test_table(array('id' => '4')),
		ws_wtable_list_test_table(array('id' => '5', 'access' => 'edit', 'channel_id' => '9')),
	);

	pg_assert_same(array('all' => 5, 'mine' => 2, 'channels' => 1, 'shared' => 2), ws_wtable_list_counts($tables));
	pg_assert_same(array('all' => 0, 'mine' => 0, 'channels' => 0, 'shared' => 0), ws_wtable_list_counts(array()));

	$ids = function (array $picked) {
		return array_map(function ($table) {
			return $table['id'];
		}, $picked);
	};

	pg_assert_same(array('1', '2', '3', '4', '5'), $ids(ws_wtable_list_pick($tables, 'all')), 'every table, in the order given');
	pg_assert_same(array('1', '4'), $ids(ws_wtable_list_pick($tables, 'mine')));
	pg_assert_same(array('2', '3'), $ids(ws_wtable_list_pick($tables, 'shared')));
	pg_assert_same(array('5'), $ids(ws_wtable_list_pick($tables, 'channels')));
	pg_assert_same(array(0, 1), array_keys(ws_wtable_list_pick(array(3 => $tables[0], 8 => $tables[3]), 'mine')), 'the picked tables are a list again');
}

function test_ws_wtable_list_segments()
{
	$html = ws_wtable_list_segments('Show <it>', array(
		array('label' => 'All', 'url' => 'workspace_wtables.php', 'current' => false, 'count' => 6),
		array('label' => 'Mine & more', 'url' => 'workspace_wtables.php?scope=mine&x=1', 'current' => true, 'count' => 0),
		array('label' => 'Tables', 'url' => 'workspace_wtables.php?view=deleted'),
	));

	pg_assert_true(strpos($html, '<span class="pg-seg" role="group" aria-label="Show &lt;it&gt;">') === 0, 'the control is named, escaped');
	pg_assert_contains('<a class="pg-seg-item" href="workspace_wtables.php">All <span class="pg-seg-count">6</span></a>', $html, 'a segment is an address with its count');
	pg_assert_contains('<a class="pg-seg-item is-active" href="workspace_wtables.php?scope=mine&amp;x=1" aria-current="page">Mine &amp; more <span class="pg-seg-count">0</span></a>', $html, 'the chosen one is the current page; a count of 0 still shows');
	pg_assert_contains('<a class="pg-seg-item" href="workspace_wtables.php?view=deleted">Tables</a>', $html, 'a segment without a count carries none');
	pg_assert_same(3, substr_count($html, 'pg-seg-item'), 'one segment per item');
	pg_assert_same(1, substr_count($html, 'aria-current'), 'one segment is chosen');
}

function test_ws_wtable_list_columns()
{
	$columns = ws_wtable_list_columns();

	pg_assert_same('name', key($columns), 'the name leads the row');
	pg_assert_same('link', $columns['name']['kind'], 'the name opens the table');
	pg_assert_same(array('name', 'rows', 'columns', 'place', 'updated'), array_keys(pg_list_table_printed_sort_columns($columns)), 'the row\'s buttons do not sort');
	pg_assert_true(in_array(WS_WTABLE_LIST, pg_list_table_printed_lists(), true), 'the list keeps a heading sort');
	pg_assert_true(!in_array(WS_WTABLE_LIST_DELETED, pg_list_table_printed_lists(), true), 'the deleted tables stay in the order the bin gives them');

	$deleted = ws_wtable_list_deleted_columns();

	pg_assert_same(array('name', 'rows', 'deleted', 'expires', 'actions'), array_keys($deleted));
	pg_assert_true(!isset($deleted['name']['kind']), 'a deleted table opens nothing');
}

function test_ws_wtable_list_cells_of_an_own_table()
{
	$table = ws_wtable_list_test_table();
	$cells = ws_wtable_list_cells($table, array(
		'url' => 'workspace_wtables.php', 'columns' => 5, 'owner' => ws_wtable_list_test_person(), 'confirm' => 'Delete "Leads"?',
	));

	pg_assert_same(array_keys(ws_wtable_list_columns()), array_keys($cells), 'one cell per column');

	pg_assert_contains('<a href="workspace_wtables.php?id=7" data-pg-open>Leads &lt;2026&gt;</a>', $cells['name']['html'], 'the escaped name opens the table');
	pg_assert_contains('· Fair &amp; web</span>', $cells['name']['html'], 'the escaped description follows in the second text');
	pg_assert_true(strpos($cells['name']['html'], '<span><span class="pg-icon-tile') === 0, 'one wrapper holds the tile, the name and the description');
	pg_assert_contains('pg-tone-teal', $cells['name']['html'], 'a live table wears the teal tile');
	pg_assert_same('Leads <2026>', $cells['name']['attributes']['data-pg-sort-value'], 'the name sorts by itself, not by its markup');

	pg_assert_same('24', $cells['rows']['html']);
	pg_assert_same('24', (string) $cells['rows']['attributes']['data-pg-sort-value'], 'rows sort as a number');
	pg_assert_same('5', $cells['columns']['html']);
	pg_assert_same('5', (string) $cells['columns']['attributes']['data-pg-sort-value']);
	pg_assert_same('1700003600', (string) $cells['updated']['attributes']['data-pg-sort-value'], 'the date sorts by its time');

	pg_assert_contains('>Mine</span>', $cells['place']['html'], 'the person\'s own table says so');
	ws_wtable_list_test_lacks('Ayşe', $cells['place']['html'], 'and does not name them');

	pg_assert_contains('href="workspace_wtables.php?id=7&amp;download=xlsx" download', $cells['actions']['html'], 'the row downloads the table');
	pg_assert_contains('class="pg-icon-button pg-icon-button-sm ws-wt-action" data-action="table_delete" data-table="7"', $cells['actions']['html'], 'the owner deletes it from the row');
	pg_assert_contains('data-confirm="Delete &quot;Leads&quot;?"', $cells['actions']['html'], 'the question is escaped into the button');
	pg_assert_same(2, substr_count($cells['actions']['html'], 'aria-label="'), 'each icon button is named');
}

function test_ws_wtable_list_cells_of_a_shared_table()
{
	foreach (array('edit' => 'Can edit', 'view' => 'Can view') as $access => $level) {
		$cells = ws_wtable_list_cells(ws_wtable_list_test_table(array('access' => $access, 'description' => '')), array(
			'url' => 'workspace_wtables.php', 'columns' => 0, 'owner' => ws_wtable_list_test_person(), 'confirm' => 'never asked',
		));

		ws_wtable_list_test_lacks('table_delete', $cells['actions']['html'], 'only the owner deletes a table (' . $access . ')');
		ws_wtable_list_test_lacks('never asked', $cells['actions']['html']);
		pg_assert_contains('download=xlsx', $cells['actions']['html'], 'whoever reads it downloads it');
		pg_assert_contains('Ayşe &lt;Y&gt; · ' . $level . '</span>', $cells['place']['html'], 'the owner, escaped, and what the share allows');
		ws_wtable_list_test_lacks('pg-text-2 fg-secondary', $cells['name']['html'], 'no description, no second text');
		pg_assert_same('0', $cells['columns']['html'], 'a table without columns shows 0');
	}

	$orphan = ws_wtable_list_place_html(array('access' => 'view'), null);

	pg_assert_contains('Former member · Can view', $orphan, 'an owner who is not known any more');
}

function test_ws_wtable_list_place_of_a_channel_table()
{
	$public = ws_wtable_list_place_html(array('access' => 'edit'), ws_wtable_list_test_person(), array('name' => 'sales <eu>', 'kind' => 'public'));

	pg_assert_contains('bi-hash', $public, 'a public channel');
	pg_assert_contains('<span>sales &lt;eu&gt;</span>', $public, 'the channel\'s name, escaped');
	ws_wtable_list_test_lacks('Ayşe', $public, 'the channel stands in the owner\'s place');
	ws_wtable_list_test_lacks('Can edit', $public);

	pg_assert_contains('bi-lock', ws_wtable_list_place_html(array('access' => 'owner'), null, array('name' => 'board', 'kind' => 'private')), 'a private channel');
}

function test_ws_wtable_list_rows()
{
	$rows = ws_wtable_list_rows(array(
		ws_wtable_list_test_table(array('id' => '7', 'user_id' => '3')),
		ws_wtable_list_test_table(array('id' => '9', 'user_id' => '4', 'access' => 'view', 'name' => 'Stock')),
		ws_wtable_list_test_table(array('id' => '11', 'user_id' => '3', 'access' => 'edit', 'name' => 'Plan', 'channel_id' => '2')),
	), array(
		'url'      => 'workspace_wtables.php',
		'counts'   => array(7 => 4, 11 => 2),
		'people'   => array(3 => ws_wtable_list_test_person()),
		'channels' => array(11 => array('name' => 'ops', 'kind' => 'public')),
		'confirms' => array(7 => 'Sure about 7?'),
	));

	pg_assert_same(array(7, 9, 11), array_map(function ($row) {
		return $row['id'];
	}, $rows), 'a row per table, by its id');

	pg_assert_same('4', $rows[0]['cells']['columns']['html'], 'each table gets its own column count');
	pg_assert_same('0', $rows[1]['cells']['columns']['html'], 'a table left out of the counts has none');
	pg_assert_contains('Sure about 7?', $rows[0]['cells']['actions']['html']);
	pg_assert_contains('Former member', $rows[1]['cells']['place']['html'], 'an owner the people do not hold');
	pg_assert_contains('<span>ops</span>', $rows[2]['cells']['place']['html'], 'a channel\'s table shows its channel');
	pg_assert_contains('?id=9"', $rows[1]['cells']['name']['html']);
}

function test_ws_wtable_list_cells_of_a_deleted_table()
{
	$cells = ws_wtable_list_deleted_cells(array(
		'id' => '5', 'name' => 'Old <prices>', 'description' => 'Until & 2025', 'row_count' => '120',
		'deleted_at' => '1700000000', 'expires_at' => '1702592000',
	));

	pg_assert_same(array_keys(ws_wtable_list_deleted_columns()), array_keys($cells), 'one cell per column');
	pg_assert_contains('<span>Old &lt;prices&gt;</span>', $cells['name']['html'], 'the escaped name opens nothing');
	ws_wtable_list_test_lacks('<a ', $cells['name']['html']);
	ws_wtable_list_test_lacks('data-pg-open', $cells['name']['html']);
	pg_assert_contains('pg-tone-gray', $cells['name']['html'], 'a deleted table wears the gray tile');
	pg_assert_contains('Until &amp; 2025', $cells['name']['html']);
	pg_assert_same('120', $cells['rows']['html']);
	pg_assert_same('1700000000', (string) $cells['deleted']['attributes']['data-pg-sort-value']);
	pg_assert_same(date('d.m.Y', 1702592000), $cells['expires']['html'], 'the day it is deleted for good');
	pg_assert_same('1702592000', (string) $cells['expires']['attributes']['data-pg-sort-value']);
	pg_assert_contains('ws-wt-action" data-action="table_restore" data-table="5"', $cells['actions']['html'], 'the row restores the table');
	pg_assert_contains('pg-button pg-button-quiet pg-button-sm', $cells['actions']['html']);
}

function test_ws_wtable_list_count_text()
{
	pg_assert_contains('12', ws_wtable_list_count_text(12));
	pg_assert_contains('0', ws_wtable_list_count_text('junk'), 'anything else counts as none');
}

// The screen without ?id= draws the list on the driver, under the name Data.
function test_ws_wtable_list_screen_is_wired()
{
	$screen = ws_wtable_list_test_screen();

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');", $screen, 'the driver is loaded');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/wtable_list.php');", $screen, 'the list module is loaded');
	pg_assert_contains("\$title = lang('Data');", $screen, 'the place is named Data');
	pg_assert_contains('ws_wtable_list_count_text($counts[\'all\'])', $screen, 'the count stands under the name');
	pg_assert_contains("ws_wtables_reached(\$viewer, 'all')", $screen, 'the store decides what the person reaches');
	pg_assert_contains('pg_list_table_printed(', $screen, 'the list is printed whole');
	pg_assert_contains('pg_list_table_html($list[\'source\'], $list[\'page\'], lang(\'Data\'))', $screen, 'the list is named for readers');
	pg_assert_contains("pg_js_bundle_tags('assets/js/pg_list_table.min.js')", $screen, 'the list\'s script is served');
	pg_assert_contains("(\$table ? '' : ' pg-measure-list')", $screen, 'only the list wears the list measure');
	pg_assert_contains('pg_screen_toolbar(', $screen, 'the toolbar is the shared one');

	pg_assert_contains('data-bs-toggle="dialog" data-bs-target="#ws_wt_new"', $screen, 'New table opens its window');
	pg_assert_contains('data-bs-toggle="dialog" data-bs-target="#ws_wt_import_new"', $screen, 'Import from file opens its window');
	pg_assert_contains('<dialog class="dialog pg-dialog-centered" id="ws_wt_new"', $screen);
	pg_assert_contains('<dialog class="dialog pg-dialog-centered" id="ws_wt_import_new"', $screen);

	pg_assert_contains("'?view=deleted'", $screen, 'the deleted tables are a view of the list');
	pg_assert_contains("lang('Deleted tables')", $screen);
	ws_wtable_list_test_lacks('datatable', strtolower($screen), 'no DataTables markup');
	ws_wtable_list_test_lacks("lang('Workflow tables')", $screen, 'the old name is gone from the screen');
	ws_wtable_list_test_lacks("lang('A workflow table of the Workspace.')", $screen);
}

// The other places that name it.
function test_ws_wtable_list_place_is_named_data_elsewhere()
{
	pg_assert_contains("'wtables'                => lang('Data')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php'), 'the record picker\'s group');

	$privacy = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/privacy.php');

	pg_assert_contains("'ws_wtable_cells'              => lang('Data table cells naming the person')", $privacy, 'the personal data report');
	pg_assert_contains("'ws_wtable_bin_cells'          => lang('Deleted Data table rows naming the person')", $privacy);

	// Addresses, API names and scopes keep their names.
	pg_assert_contains("'workspace_wtables.php'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/nav.php'), 'the address stays');
	pg_assert_contains("'summary'     => 'List workflow tables'", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_wtables.php'), 'the API keeps its wording');
}
