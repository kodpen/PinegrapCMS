<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the File Manager's access drawers and folder details: the rows a
 * rights save writes, the drawers listing only rights holders and finding
 * the others by search, changed rows only, the end date under its own label,
 * the inherit note following the select, the totals under a folder and the
 * Default style option naming the inherited style.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

function pg_test_access_drawer_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Valid rows only, the last one of a user winning; an end date only for
// view access and only a real date.
function test_folder_access_entries()
{
	pg_assert_same(
		array(
			5 => array('rights' => 1, 'expiration_date' => '2026-12-31'),
			6 => array('rights' => 0, 'expiration_date' => ''),
			7 => array('rights' => 2, 'expiration_date' => '')),
		pg_explorer_folder_access_entries(array(
			array('user_id' => 5, 'rights' => 2),
			array('user_id' => 5, 'rights' => 1, 'expiration_date' => ' 2026-12-31 '),
			array('user_id' => 6, 'rights' => 0, 'expiration_date' => '2026-12-31'),
			array('user_id' => 7, 'rights' => '2', 'expiration_date' => '2026-12-31'),
			array('user_id' => 0, 'rights' => 1),
			array('user_id' => 8, 'rights' => 3),
			array('user_id' => 9),
			array('user_id' => 10, 'rights' => array(1)),
			'junk')));

	pg_assert_same(array(), pg_explorer_folder_access_entries('nope'));
	pg_assert_same('', pg_explorer_folder_access_entries(array(array('user_id' => 4, 'rights' => 1, 'expiration_date' => '2026-02-30')))[4]['expiration_date'], 'no such day');
}

// None deletes the row; no zero-rights row is written; one role query.
function test_folder_access_save_writes_only_rights()
{
	$handler = pg_test_access_drawer_source('includes/explorer/handle_folder_access.php');

	pg_assert_contains('pg_explorer_folder_access_entries($request[\'user_rights\'])', $handler);
	pg_assert_contains("DELETE FROM aclfolder WHERE (aclfolder_user = ", $handler);
	pg_assert_contains("if (\$entry['rights'] > 0) {", $handler);
	pg_assert_true(strpos($handler, 'UPDATE aclfolder SET') === false, 'no update of a zero row');
	pg_assert_true(strpos($handler, "WHERE user_role = '3'\n            ORDER BY user_username\");") === false, 'not every basic user listed');
	pg_assert_contains("(user_id IN (\" . implode(',', array_map('intval', \$listed_ids))", $handler);
}

// The search is a manager's, bound with placeholders, and a page cache read.
function test_access_user_search()
{
	$handler = pg_test_access_drawer_source('includes/explorer/handle_folder_access.php');

	pg_assert_same(array(), pg_explorer_access_user_search_rows('   '), 'an empty term asks nothing');
	pg_assert_contains("\$pattern = pg_list_like_pattern(\$term);", $handler);
	pg_assert_contains('array($pattern, $pattern, $pattern, $pattern)', $handler);
	pg_assert_contains("if (\$user['role'] > 2) {", substr($handler, strpos($handler, 'function pg_explorer_handle_access_user_search'), 300));
	pg_assert_contains("case 'explorer_access_user_search':", pg_test_access_drawer_source('view_folder_and_files_f.php'));
	pg_assert_contains("'explorer_access_user_search'", pg_test_access_drawer_source('includes/fn/page_cache.php'));

	$catalog = pg_test_access_drawer_source('includes/explorer/handle_catalog_edit.php');
	pg_assert_contains("(user_manage_ecommerce = 'yes') OR (manage_ecommerce_reports = '1') OR (user_set_offline_payment = '1')", $catalog, 'commerce lists rights holders');
}

function test_access_drawers_send_changed_rows()
{
	$view = pg_test_access_drawer_source('view_folders.php');

	pg_assert_contains('var changed = accessChangedRows(body);', $view);
	pg_assert_contains("data-initial-rights=\"' + initial + '\"", $view);
	pg_assert_contains("!== row.getAttribute('data-initial')", $view, 'commerce compares too');
	pg_assert_contains('bindAccessUserSearch(body, function (found) {', $view);
	pg_assert_contains("'<label class=\"form-label small mb-1\" for=\"' + dateId + '\">' + esc(L.expiration_date)", $view, 'the end date has a label');
	pg_assert_contains("document.getElementById('access_inherit_note').hidden = (this.value !== '');", $view);
	pg_assert_true(strpos($view, '<th></th>') === false, 'no blank column heading');
	pg_assert_contains('grant.expiration_label', $view, 'shared folders in the panel format');
}

function test_folder_totals()
{
	// 1 root > 2 > 3; 1 > 50 (bin) > 51.
	$parents = array(1 => 0, 2 => 1, 3 => 2, 50 => 1, 51 => 50);
	$counts = array(
		1 => array('pages' => 1, 'files' => 0, 'bytes' => 0),
		2 => array('pages' => 0, 'files' => 1, 'bytes' => 100),
		3 => array('pages' => 48, 'files' => 3, 'bytes' => 7000),
		51 => array('pages' => 5, 'files' => 2, 'bytes' => 900));

	$totals = pg_explorer_folder_totals($counts, $parents, 50);

	pg_assert_same(array('folders' => 1, 'pages' => 48, 'files' => 4, 'bytes' => 7100), $totals[2]);
	pg_assert_same(array('folders' => 0, 'pages' => 48, 'files' => 3, 'bytes' => 7000), $totals[3]);
	pg_assert_same(array('folders' => 1, 'pages' => 5, 'files' => 2, 'bytes' => 900), $totals[50], 'the bin counts what it holds');
	pg_assert_same(array('folders' => 2, 'pages' => 49, 'files' => 4, 'bytes' => 7100), $totals[1], 'the bin adds nothing above it');

	// A broken chain ends.
	$loop = pg_explorer_folder_totals(array(7 => array('pages' => 1)), array(7 => 8, 8 => 7));
	pg_assert_true($loop[7]['pages'] >= 1, 'a loop does not hang');
}

function test_folder_settings_default_names_the_inherited_style()
{
	pg_assert_same('X-a-b-a', pg_explorer_replace_first('a', 'X', 'a-a-b-a'));
	pg_assert_same('abc', pg_explorer_replace_first('z', 'X', 'abc'));

	$handler = pg_test_access_drawer_source('includes/explorer/handle_folders.php');
	pg_assert_contains("pg_explorer_folder_style_default_label(select_style(", $handler);
	pg_assert_contains("'Default: {var:1} (from the parent folder)'", $handler);
	pg_assert_contains('data-role="open-access"', pg_test_access_drawer_source('view_folders.php'), 'rights holders link to the drawer');
}
