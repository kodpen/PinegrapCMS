<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_panel_search_scope_sql() in includes/fn/auth.php and its use
 * in the panel search (includes/panel/search.php): a record result is listed
 * only when the edit screen it links to would let the viewer in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/auth.php');

function test_search_scope_folder_rule()
{
	foreach (array(0, 1, 2) as $role) {
		pg_assert_same('', pg_panel_search_scope_sql('folder', $role, 'page_folder', array()), 'role ' . $role);
	}
	pg_assert_same(' AND (page_folder IN (3,7))', pg_panel_search_scope_sql('folder', 3, 'page_folder', array('3', 7, '7')));
	pg_assert_same(' AND (0 = 1)', pg_panel_search_scope_sql('folder', 3, 'page_folder', array()), 'no folder right');
	pg_assert_same(' AND (folder IN (5))', pg_panel_search_scope_sql('folder', '3', 'folder', array('5 OR 1=1', 0)), 'ids cast to integers');
}

function test_search_scope_contact_group_rule()
{
	pg_assert_same('', pg_panel_search_scope_sql('contact_group', 2, 'c.id', array()));
	pg_assert_same(
		' AND (c.id IN (SELECT contact_id FROM contacts_contact_groups_xref WHERE contact_group_id IN (4,9)))',
		pg_panel_search_scope_sql('contact_group', 3, 'c.id', array(4, '9'))
	);
	pg_assert_same(' AND (0 = 1)', pg_panel_search_scope_sql('contact_group', 3, 'c.id', array()), 'no contact group');
}

function test_search_scope_user_role_rule()
{
	pg_assert_same('', pg_panel_search_scope_sql('user_role', 0, 'user_role'));
	pg_assert_same(' AND (user_role > 1)', pg_panel_search_scope_sql('user_role', 1, 'user_role'));
	pg_assert_same(' AND (user_role > 2)', pg_panel_search_scope_sql('user_role', '2', 'user_role'));
	pg_assert_same(' AND (0 = 1)', pg_panel_search_scope_sql('user_role', 3, 'user_role'));
}

function test_search_scope_unknown_rule_lists_nothing()
{
	pg_assert_same(' AND (0 = 1)', pg_panel_search_scope_sql('nope', 3, 'id'));
	pg_assert_same(' AND (0 = 1)', pg_panel_search_scope_sql('nope', 0, 'id'));
}

function test_search_applies_each_screen_rule()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/search.php');
	pg_assert_same(3, substr_count($php, "pg_panel_search_scope_sql('folder', \$role,"), 'pages, files and forms by folder right');
	pg_assert_contains("pg_panel_search_scope_sql('user_role', \$role, 'user_role')", $php, 'users below the viewer only');
	pg_assert_contains("pg_panel_search_scope_sql('contact_group', \$role, 'c.id',", $php, 'contacts in the viewer groups only');
	pg_assert_contains("erp_doc_type = ''", $php, 'ERP-kept documents left out');
	pg_assert_contains("waf_table_has_column('files', 'erp_doc_type')", $php, 'ERP column guarded');
	pg_assert_contains('AND design = 0', $php, 'design files filtered in SQL');
	pg_assert_false(strpos($php, "if (\$r['design'] && !\$can_design)") !== false, 'no filter after the query');
	pg_assert_true((bool) preg_match('/if \(\$can_design\) \{\s*\$rows = db_items\(\s*"SELECT id, name\s*FROM menus/', $php), 'menus for designers');
}
