<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contacts list on the list table driver
 * (includes/fn/contact_list.php): its columns per view, the groups column
 * that follows its toggle, the editor link and its place among the
 * driver's lists.
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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

function test_contact_list_default_columns()
{
	$columns = pg_list_table_columns(pg_contact_list_columns('all_my_contacts', false));

	pg_assert_same(array('picture', 'first_name', 'last_name', 'company', 'business_phone', 'home_phone', 'mobile_phone', 'email', 'user', 'opt_in', 'last_modified', 'actions'), array_keys($columns));
	pg_assert_same(false, $columns['picture']['hideable'], 'the picture with the editor link is never hidden');
	pg_assert_same('contacts.email_address', $columns['email']['sort']);
	pg_assert_same('contact_user.user_username', $columns['user']['sort']);
	pg_assert_same('center', $columns['opt_in']['align']);
	pg_assert_same('contacts.mobile_phone', $columns['mobile_phone']['sort'], 'each phone has its own column and sorts');
	pg_assert_false($columns['actions']['sortable']);
}

function test_contact_list_groups_column_follows_its_toggle()
{
	$keys = array_keys(pg_contact_list_columns('my_subscribers', true));

	pg_assert_same('groups', $keys[count($keys) - 3], 'the groups come before Last Modified and the edit button');
	pg_assert_false(isset(pg_contact_list_columns('my_subscribers', false)['groups']), 'no groups column while they are hidden');
}

function test_contact_list_address_view_columns()
{
	$columns = pg_list_table_columns(pg_contact_list_columns('my_contacts_by_home_address', false));

	pg_assert_same(array('picture', 'first_name', 'last_name', 'company', 'address', 'address_2', 'city', 'state', 'zip_code', 'country', 'last_modified', 'actions'), array_keys($columns));
	pg_assert_same('contacts.home_city', $columns['city']['sort']);
	pg_assert_same('contacts.business_zip_code', pg_contact_list_columns('my_contacts_by_business_address', false)['zip_code']['sort']);
}

function test_contact_list_duplicates_columns()
{
	$columns = pg_contact_list_columns('all_duplicate_contacts', false);

	pg_assert_same(array('picture', 'email', 'first_name', 'last_name', 'company', 'address', 'business_phone', 'home_phone', 'mobile_phone', 'user', 'groups', 'last_modified', 'actions'), array_keys($columns), 'the duplicates view always shows the groups');
}

function test_contact_list_sort_columns_match_the_old_codes()
{
	$sorts = pg_list_table_sort_columns(pg_list_table_columns(pg_contact_list_columns('all_my_contacts', false)));

	pg_assert_same(array('first_name', 'last_name', 'company', 'business_phone', 'home_phone', 'mobile_phone', 'email', 'user', 'opt_in', 'last_modified'), array_keys($sorts), 'a kept sort still names its column');
}

function test_contact_list_is_a_driver_list()
{
	$definition = pg_list_table_definition('view_contacts');

	pg_assert_same('includes/fn/contact_list.php', $definition['file']);
	pg_assert_same('pg_contact_list_source', $definition['source']);
	pg_assert_true(function_exists($definition['source']), 'the source function is in its file');
}

function test_contact_list_edit_link()
{
	$html = pg_contact_list_edit_link(7, 'Ali <b>', '/pinegrap/view_contacts.php?filter=my_subscribers', '<span>AV</span>');

	pg_assert_contains('data-pg-open', $html, 'a double-click opens the editor');
	pg_assert_contains('edit_contact.php?id=7&amp;send_to=' . h(urlencode('/pinegrap/view_contacts.php?filter=my_subscribers')), $html, 'the editor returns to the view');
	pg_assert_contains('aria-label="' . h(lang(array('string' => 'Edit {var:1}', 'vars' => 'Ali <b>'))) . '"', $html, 'the link names the contact');
	pg_assert_contains('<span>AV</span>', $html, 'the picture is the link');
}
