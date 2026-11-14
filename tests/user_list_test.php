<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the users list on the list table driver
 * (includes/fn/user_list.php): its columns per view, the gate of its
 * source, its row cells and its place among the driver's lists.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Behind their gates these files only declare functions.
if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/user_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_user_list_default_columns()
{
	$columns = pg_list_table_columns(pg_user_list_columns('default'));
	$keys = array_keys($columns);

	pg_assert_same('username', $keys[0], 'the name comes first');
	pg_assert_same(false, $columns['username']['hideable'], 'the name is never hidden');
	pg_assert_same('actions', end($keys), 'the edit button comes last');
	pg_assert_same(false, $columns['actions']['sortable']);
	pg_assert_true(isset($columns['role'], $columns['site_manager'], $columns['site_designer'], $columns['last_online']));
	pg_assert_same('user.user_role', $columns['site_manager']['sort'], 'Site Manager follows the role');
	pg_assert_same('center', $columns['member_user']['align']);
	pg_assert_false($columns['private_folder']['sortable'], 'a right without a column does not sort');

	// The default view is listed by last change, which it shows no column
	// for: the column is there, hidden, so the sort is kept.
	pg_assert_true($columns['last_modified']['hidden'], 'last modified is hidden in the default view');
	pg_assert_same('user.user_timestamp', $columns['last_modified']['sort']);
	pg_assert_false(isset($columns['email']), 'no e-mail column in the default view');
}

function test_user_list_filter_columns()
{
	$columns = pg_list_table_columns(pg_user_list_columns('all_my_users'));

	pg_assert_same(array('username', 'email', 'start_page', 'user_contact', 'last_modified', 'last_online', 'actions'), array_keys($columns));
	pg_assert_false($columns['last_modified']['hidden']);
	pg_assert_same('start_page.page_name', $columns['start_page']['sort']);

	$columns = pg_list_table_columns(pg_user_list_columns('my_contact_managers'));

	pg_assert_same('Contact Groups', $columns['manage_contacts']['heading']);
	pg_assert_same('Send Campaigns', $columns['manage_email']['heading']);
	pg_assert_same('check', $columns['manage_email']['kind']);

	// Site staff carry no contact.
	pg_assert_false(isset(pg_user_list_columns('all_site_managers')['user_contact']));
}

function test_user_list_sort_columns_follow_the_columns()
{
	$view_sorts = pg_list_table_sort_columns(pg_list_table_columns(pg_user_list_columns('default')));

	pg_assert_true(isset($view_sorts['last_modified']), 'the hidden column still names its sort');
	pg_assert_false(isset($view_sorts['actions']));
}

function test_user_list_source_gate()
{
	pg_assert_same(null, pg_user_list_source(array('id' => 9, 'role' => 3), array()), 'a basic user may not open the list');
	pg_assert_same(null, pg_user_list_source(array('id' => 9, 'role' => 'x'), array()), 'no role, no list');
}

function test_user_list_is_a_driver_list()
{
	$definition = pg_list_table_definition('view_users');

	pg_assert_same('includes/fn/user_list.php', $definition['file']);
	pg_assert_same('pg_user_list_source', $definition['source']);
	pg_assert_true(function_exists($definition['source']), 'the source function is in its file');
}

function test_user_list_edit_cell_opens_the_editor()
{
	$cells = pg_user_list_edit_cells(7, 'bob<', '/pinegrap/view_users.php?filter=my_private_users');

	pg_assert_contains('data-pg-open', $cells['username']['html'], 'a double-click opens the editor');
	pg_assert_contains('edit_user.php?id=7&amp;send_to=', $cells['username']['html']);
	pg_assert_contains('bob&lt;', $cells['username']['html'], 'the name is escaped');
	pg_assert_contains(h(urlencode('/pinegrap/view_users.php?filter=my_private_users')), $cells['username']['html'], 'the editor returns to the view');
	pg_assert_contains('aria-label="' . h(lang('Edit')) . '"', $cells['actions']['html']);
}

function test_user_list_start_page_cell()
{
	pg_assert_same('', pg_user_list_start_page_cell(0, null), 'no start page');
	pg_assert_same('About &amp; us', pg_user_list_start_page_cell('12', 'About & us'));
	$gone = pg_user_list_start_page_cell('294', null);
	pg_assert_contains(h(lang('Deleted page')), $gone, 'a page that is gone is named as deleted');
	pg_assert_false(strpos(strip_tags($gone), '294') !== false, 'never the bare id as the text');
}
