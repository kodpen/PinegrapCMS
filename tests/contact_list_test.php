<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contacts list (includes/fn/contact_list.php): the view names,
 * the filter's SQL and bound values for a manager (no database for the
 * views other than the duplicates one), the sort, the search columns, the
 * duplicates order, the address line and a row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');

// Without a request the site sits at the root, as elsewhere in the tests.
foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours', 'AFFILIATE_PROGRAM' => true) as $contact_list_test_name => $contact_list_test_value) {
	if (!defined($contact_list_test_name)) {
		define($contact_list_test_name, $contact_list_test_value);
	}
}

function contact_list_test_state($state)
{
	$_SESSION['software']['view_contacts'] = array_merge(array(
		'start_month' => '1', 'start_day' => '2', 'start_year' => '2025',
		'stop_month' => '3', 'stop_day' => '4', 'stop_year' => '2026',
		'contact_group' => '[' . lang('All') . ']',
		'advanced_filters' => false,
	), $state);
	unset($_SESSION['software']['list_state']['view_contacts']);
}

function contact_list_test_placeholders($list)
{
	return substr_count($list['from'] . $list['where'], '?');
}

function test_contact_list_filter_names()
{
	pg_assert_same('default', pg_contact_list_filter_name(''));
	pg_assert_same('default', pg_contact_list_filter_name('<x>'));
	pg_assert_same('default', pg_contact_list_filter_name(array('my_members')));
	pg_assert_same('my_members', pg_contact_list_filter_name('my_members'));
	pg_assert_same('all_duplicate_contacts', pg_contact_list_filter_name('all_duplicate_contacts'));

	pg_assert_true(isset(pg_contact_list_filters(2)['all_duplicate_contacts']), 'a manager may look for duplicates');
	pg_assert_false(isset(pg_contact_list_filters(3)['all_duplicate_contacts']), 'a user may not');

	pg_assert_same('business_', pg_contact_list_address_prefix('my_contacts_by_business_address'));
	pg_assert_same('home_', pg_contact_list_address_prefix('my_contacts_by_home_address'));
	pg_assert_same('', pg_contact_list_address_prefix('default'));
}

function test_contact_list_filter_default_view()
{
	contact_list_test_state(array());
	$list = pg_contact_list_filter(array('role' => 0, 'id' => 1), 'default', array());

	pg_assert_same('WHERE (contacts.timestamp >= ?) AND (contacts.timestamp <= ?)', $list['where'], '[All] for a manager adds no group condition');
	pg_assert_same(array(mktime(0, 0, 0, 1, 2, 2025), mktime(23, 59, 59, 3, 4, 2026)), $list['params']);
	pg_assert_false($list['advanced']);
	pg_assert_same('contacts.timestamp desc, contacts.id asc', $list['order_by']);
	pg_assert_same(lang('All My Contacts'), $list['heading']);
}

function test_contact_list_filter_user_role_keeps_to_its_groups()
{
	contact_list_test_state(array());
	$groups = array(array('id' => '4', 'name' => 'A'), array('id' => '9', 'name' => 'B'));

	$list = pg_contact_list_filter(array('role' => 3, 'id' => 5), 'default', $groups);
	pg_assert_contains('((contacts_contact_groups_xref.contact_group_id = ?) OR (contacts_contact_groups_xref.contact_group_id = ?))', $list['where']);
	pg_assert_same(array('4', '9'), array_slice($list['params'], 2));
	pg_assert_same(contact_list_test_placeholders($list), count($list['params']));

	$list = pg_contact_list_filter(array('role' => 3, 'id' => 5), 'default', array());
	pg_assert_contains('(0 = 1)', $list['where'], 'no group, no contact');
}

function test_contact_list_filter_picked_group_and_none()
{
	contact_list_test_state(array('contact_group' => "4' OR '1"));
	$list = pg_contact_list_filter(array('role' => 1, 'id' => 1), 'my_members', array());
	pg_assert_contains("(contacts.member_id != '')", $list['where']);
	pg_assert_contains('(contacts_contact_groups_xref.contact_group_id = ?)', $list['where']);
	pg_assert_same("4' OR '1", end($list['params']));
	pg_assert_false(strpos($list['where'], "4'") !== false, 'no value in the SQL text');
	pg_assert_same(lang('Member'), $list['membership_label']);

	contact_list_test_state(array('contact_group' => '[' . lang('None') . ']'));
	$list = pg_contact_list_filter(array('role' => 1, 'id' => 1), 'default', array());
	pg_assert_contains('(contacts_contact_groups_xref.contact_group_id IS NULL)', $list['where']);
}

function test_contact_list_filter_advanced_boxes()
{
	contact_list_test_state(array(
		'advanced_filters' => true,
		'contact_groups' => array('7', '[' . lang('None') . ']'),
		'last_name' => 'Kaya',
		'opt_in_status' => 'opt_out',
		'membership_status' => 'active_member',
		'affiliate_code' => 'A%',
	));
	$list = pg_contact_list_filter(array('role' => 0, 'id' => 1), 'default', array());

	pg_assert_true($list['advanced']);
	pg_assert_contains('((contacts_contact_groups_xref.contact_group_id = ?) OR (contacts_contact_groups_xref.contact_group_id IS NULL))', $list['where']);
	pg_assert_contains('(contacts.last_name LIKE ?)', $list['where']);
	pg_assert_contains("(contacts.opt_in = '0')", $list['where']);
	pg_assert_contains("(contacts.expiration_date IS NULL)))", $list['where'], 'the active member condition');
	pg_assert_contains('(contacts.affiliate_code LIKE ?)', $list['where']);
	pg_assert_true(in_array('%Kaya%', $list['params'], true));
	pg_assert_true(in_array('%A%%', $list['params'], true));
	pg_assert_same(contact_list_test_placeholders($list), count($list['params']), 'one value per placeholder');
	pg_assert_same(substr_count($list['where'], '('), substr_count($list['where'], ')'), 'balanced brackets');

	contact_list_test_state(array('advanced_filters' => true, 'contact_groups' => array()));
	$list = pg_contact_list_filter(array('role' => 0, 'id' => 1), 'my_subscribers', array());
	pg_assert_contains('(0 = 1)', $list['where'], 'no checked group finds nothing');
}

function test_contact_list_filter_membership_view_keeps_its_own_status()
{
	contact_list_test_state(array('advanced_filters' => true, 'contact_groups' => array('3'), 'membership_status' => 'non_member'));
	$list = pg_contact_list_filter(array('role' => 0, 'id' => 1), 'my_expired_members', array());

	pg_assert_false(strpos($list['where'], "(contacts.member_id = '')") !== false, 'the view decides the membership');
	pg_assert_contains('(contacts.expiration_date < CURRENT_DATE())', $list['where']);
}

function test_contact_list_sort_by_view()
{
	contact_list_test_state(array());
	$_SESSION['software']['list_state']['view_contacts'] = array('sort' => 'city', 'order' => 'asc');

	$sort = pg_contact_list_sort('my_contacts_by_home_address');
	pg_assert_same('contacts.home_city asc, contacts.id asc', $sort['order_by']);
	pg_assert_false(isset($sort['sort_columns']['email']), 'no e-mail column in an address view');

	$sort = pg_contact_list_sort('all_duplicate_contacts');
	pg_assert_same('contacts.email_address asc, contacts.id asc', $sort['order_by'], 'duplicates by address');
}

function test_contact_list_search_columns()
{
	$columns = pg_contact_list_search_columns('default', false, array());
	pg_assert_true(in_array('contacts.email_address', $columns, true));
	pg_assert_true(in_array('contact_user.user_username', $columns, true));

	$columns = pg_contact_list_search_columns('my_contacts_by_business_address', true, array(array('id' => '6', 'name' => 'G')));
	pg_assert_true(in_array('contacts.business_city', $columns, true));
	pg_assert_false(in_array('contacts.email_address', $columns, true), 'no e-mail column in an address view');
	pg_assert_contains('search_xref.contact_group_id IN (6)', end($columns), 'only the groups the user may edit');

	$where = pg_list_search_where(array('x'), $columns);
	pg_assert_same(substr_count($where['sql'], '?'), count($where['params']));
}

function test_contact_list_duplicates_order()
{
	$contacts = array(
		array('id' => 1, 'email_address' => 'a@x', 'user_id' => ''),
		array('id' => 2, 'email_address' => 'a@x', 'user_id' => '8'),
		array('id' => 3, 'email_address' => 'b@x', 'user_id' => ''),
		array('id' => 4, 'email_address' => 'b@x', 'user_id' => ''),
	);

	pg_assert_same(array(2, 1, 3, 4), array_column(pg_contact_list_duplicates_order($contacts), 'id'), 'the copy with a user account first');
	pg_assert_same(array(), pg_contact_list_duplicates_order(array()));
}

function test_contact_list_address_line()
{
	pg_assert_same('Home: 1 Main St Springfield, IL 62701 US', pg_contact_list_address_line('Home', array('address_1' => '1 Main St', 'city' => 'Springfield', 'state' => 'IL', 'zip_code' => '62701', 'country' => 'US')));
	pg_assert_same('', pg_contact_list_address_line('Home', array()));
	pg_assert_same('Business: Ankara', pg_contact_list_address_line('Business', array('city' => 'Ankara')));
}

function test_contact_list_row_default_view()
{
	$contact = array(
		'id' => '15', 'image' => '', 'file_id' => '0', 'first_name' => '<b>Ali</b>', 'last_name' => 'Veli', 'company' => '',
		'business_phone' => '1', 'home_phone' => '', 'mobile_phone' => '2', 'email_address' => 'a@x', 'opt_in' => '1',
		'last_modified_username' => 'ed', 'user_id' => '3', 'user_username' => 'ali', 'user_role' => '3', 'timestamp' => time(),
	);

	$row = pg_contact_list_row($contact, array('role' => 2), 'default', 'G1', '');

	pg_assert_same('15', $row['id']);
	pg_assert_same(12, count($row['cells']), 'check box, action, picture, 3 names, phone, e-mail, user, opt-in, groups, modified');
	pg_assert_contains('value="15"', $row['cells'][0]['html']);
	pg_assert_contains('edit_contact.php?id=15', $row['cells'][1]['html']);
	pg_assert_same('&lt;b&gt;Ali&lt;/b&gt;', $row['cells'][3]['html']);
	pg_assert_contains(lang('Mobile') . ': 2', $row['cells'][6]['html']);
	pg_assert_contains('mailto:a@x', $row['cells'][7]['html']);
	pg_assert_contains('edit_user.php?id=3', $row['cells'][8]['html'], 'a manager may open a user account');
	pg_assert_contains('task_alt', $row['cells'][9]['html']);
	pg_assert_same('G1', $row['cells'][10]['html']);

	$row = pg_contact_list_row($contact, array('role' => 3), 'default', '', '');
	pg_assert_same('ali', $row['cells'][8]['html'], 'a user may not');
}

function test_contact_list_row_address_and_duplicates_views()
{
	$contact = array(
		'id' => '15', 'image' => 'http://x/a.png', 'file_id' => '0', 'first_name' => 'A', 'last_name' => 'B', 'company' => 'C',
		'business_phone' => '', 'home_phone' => '', 'mobile_phone' => '', 'email_address' => 'a@x', 'opt_in' => '0',
		'address_1' => 'L1', 'address_2' => 'L2', 'city' => 'Ci', 'state' => 'St', 'zip_code' => 'Z', 'country' => 'Co',
		'business_address_1' => 'B1', 'business_address_2' => '', 'business_city' => 'BC', 'business_state' => '', 'business_zip_code' => '', 'business_country' => '',
		'home_address_1' => '', 'home_address_2' => '', 'home_city' => '', 'home_state' => '', 'home_zip_code' => '', 'home_country' => '',
		'last_modified_username' => '', 'user_id' => null, 'user_username' => null, 'user_role' => null, 'timestamp' => time(),
	);

	$row = pg_contact_list_row($contact, array('role' => 0), 'my_contacts_by_home_address', '', '');
	pg_assert_same(13, count($row['cells']), 'check box, action, picture, 3 names, 5 address columns, groups, modified');
	pg_assert_same('L1,<br />L2', $row['cells'][6]['html']);
	pg_assert_contains('data-src="http://x/a.png"', $row['cells'][2]['html'], 'a picture by address, loaded lazily');

	$row = pg_contact_list_row($contact, array('role' => 0), 'all_duplicate_contacts', 'G', '');
	pg_assert_same(12, count($row['cells']), 'check box, action, picture, e-mail, 3 names, address, phone, user, groups, modified');
	pg_assert_contains('mailto:a@x', $row['cells'][3]['html']);
	pg_assert_same(h(lang('Business') . ': B1 BC'), $row['cells'][7]['html']);

	$contact['file_id'] = '5';
	$row = pg_contact_list_row($contact, array('role' => 0), 'default', '', 'pic.png');
	pg_assert_contains('data-src="/pic.png"', $row['cells'][2]['html'], 'a picture from the file manager');
}
