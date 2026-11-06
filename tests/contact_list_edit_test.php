<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contacts list's quick edit (includes/fn/contact_list_edit.php):
 * which cells are fields in which view, the values they open with, the
 * contact screen's checks, and the endpoint answering before the screen
 * keeps the request in the session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/contact_list.php');

function pg_test_contact_quick_contact()
{
	return array(
		'id' => '15', 'image' => '', 'file_id' => '0', 'first_name' => 'Ali', 'last_name' => 'Veli', 'company' => 'Kodpen',
		'business_phone' => '1', 'home_phone' => '', 'mobile_phone' => '2', 'email_address' => 'a@x.com', 'opt_in' => '1',
		'address_1' => 'L1', 'address_2' => 'L2', 'city' => 'Ci', 'state' => 'St', 'zip_code' => 'Z', 'country' => 'Co',
		'business_address_1' => 'B1', 'business_address_2' => '', 'business_city' => 'BC', 'business_state' => '', 'business_zip_code' => '', 'business_country' => '',
		'home_address_1' => '', 'home_address_2' => '', 'home_city' => '', 'home_state' => '', 'home_zip_code' => '', 'home_country' => '',
		'last_modified_username' => '', 'user_id' => null, 'user_username' => null, 'user_role' => null, 'timestamp' => time(),
	);
}

// Every column the contact screen changes is a field (the groups when the
// user may tick some); the rest are computed or gathered and stay text. A
// new column fails here until it is one or the other.
function test_contact_quick_every_screen_value_is_a_field()
{
	$text = array('picture', 'user', 'last_modified', 'actions');

	foreach (array('default', 'my_subscribers', 'my_contacts_by_home_address', 'my_contacts_by_business_address') as $filter) {
		$fields = pg_contact_quick_edit_columns($filter, array('4' => 'News'));

		foreach (array_keys(pg_contact_list_columns($filter, true)) as $column) {
			pg_assert_true(isset($fields[$column]) || in_array($column, $text, true), $filter . ': ' . $column . ' is a field or computed');
		}
	}

	$fields = pg_contact_quick_edit_columns('all_duplicate_contacts');
	pg_assert_false(isset($fields['address']), 'the duplicates view shows both addresses in one cell, text');
	pg_assert_false(isset($fields['opt_in']), 'the duplicates view has no opt-in column');
	pg_assert_true(isset($fields['email']));
	pg_assert_false(isset(pg_contact_quick_edit_columns('default')['groups']), 'no groups to tick, no groups field');
	pg_assert_same(array('type' => 'multiselect', 'label' => lang('Contact Groups'), 'options' => array('4' => 'News')), pg_contact_quick_edit_columns('default', array('4' => 'News'))['groups']);
}

// The list marks each column a field writes editable: without the mark the
// driver gave the contacts list no quick edit at all.
function test_contact_quick_columns_are_marked_editable()
{
	$quick_edit = pg_contact_quick_edit_columns('default', array('4' => 'News'));
	$columns = pg_contact_list_editable_columns(pg_contact_list_columns('default', true), $quick_edit);

	pg_assert_same('text', $columns['email']['editable']);
	pg_assert_same('select', $columns['opt_in']['editable']);
	pg_assert_same('multiselect', $columns['groups']['editable']);
	pg_assert_false(isset($columns['user']['editable']), 'a computed column is not');

	$source = pg_list_table_source(array('list' => 'view_contacts', 'columns' => $columns, 'edit' => array('url' => 'view_contacts.php', 'columns' => $quick_edit)));
	pg_assert_true(is_array($source['edit']), 'the list gets its quick edit');
}

function test_contact_quick_groups_value_and_post()
{
	$options = array('7' => 'B', '3' => 'A', '9' => 'C');

	pg_assert_same('7,3', pg_contact_quick_group_value(array(3, '7', '12'), $options), 'in the choices\' order; a group not offered is left out');
	pg_assert_same('', pg_contact_quick_group_value(array(), $options));

	$states = array('3' => array('in' => true, 'opt_in' => false), '7' => array('in' => false, 'opt_in' => true));
	pg_assert_same(array(
		'contact_group_opt_in_7' => '1',
		'contact_group_3' => '1',
		'contact_group_opt_in_3' => '',
		'contact_group_opt_in_9' => '1',
	), pg_contact_quick_groups_post(array('3'), $options, $states), 'a switch for each chosen group, the opt-in as stored, on for a group never joined');
}

function test_contact_quick_columns_follow_the_view_address()
{
	pg_assert_same('home_address_1', pg_contact_quick_columns('my_contacts_by_home_address')['address']);
	pg_assert_same('business_zip_code', pg_contact_quick_columns('my_contacts_by_business_address')['zip_code']);
	pg_assert_same('email_address', pg_contact_quick_columns('default')['email']);
	pg_assert_false(isset(pg_contact_quick_columns('default')['city']), 'the address parts only in an address view');

	$fields = pg_contact_quick_edit_columns('my_contacts_by_home_address');
	pg_assert_same(10, $fields['zip_code']['max'], 'the screen\'s zip box takes 10');
	pg_assert_same(100, pg_contact_quick_edit_columns('default')['email']['max']);
	pg_assert_same(array('1', '0'), array_map('strval', array_keys(pg_contact_quick_edit_columns('default')['opt_in']['options'])));
}

function test_contact_quick_values()
{
	$contact = pg_test_contact_quick_contact();

	pg_assert_same('1', pg_contact_quick_edit_value('opt_in', $contact, 'default'));
	pg_assert_same('a@x.com', pg_contact_quick_edit_value('email', $contact, 'default'));
	pg_assert_same('L1', pg_contact_quick_edit_value('address', $contact, 'my_contacts_by_home_address'), 'the list reads address_1 under its short name');
	pg_assert_same('B1', pg_contact_quick_edit_value('address', array('business_address_1' => 'B1'), 'my_contacts_by_business_address'), 'the stored row under its own name');
	pg_assert_same('', pg_contact_quick_edit_value('city', $contact, 'default'), 'not a field of the view');
}

function test_contact_quick_change_checks_as_the_screen()
{
	$valid = function ($address) {
		return strpos($address, '@') !== false;
	};

	$change = pg_contact_quick_change('email', 'bad', 'default', $valid);
	pg_assert_false($change['ok'], 'an address that is no address is refused');

	$change = pg_contact_quick_change('email', '', 'default', $valid);
	pg_assert_true($change['ok'], 'the screen saves an empty address');

	$change = pg_contact_quick_change('city', 'Ankara', 'my_contacts_by_home_address', $valid);
	pg_assert_same(array('ok' => true, 'column' => 'home_city', 'value' => 'Ankara', 'message' => ''), $change);

	$change = pg_contact_quick_change('opt_in', '0', 'default', $valid);
	pg_assert_same('0', $change['value']);

	pg_assert_false(pg_contact_quick_change('city', 'x', 'default', $valid)['ok'], 'a column the view does not change');
	pg_assert_false(pg_contact_quick_change('opt_in', '1', 'all_duplicate_contacts', $valid)['ok']);
}

function test_contact_quick_rows_carry_their_fields()
{
	$row = pg_contact_list_row(pg_test_contact_quick_contact(), array('role' => 2), 'default', '', '');

	pg_assert_same('email', $row['cells']['email']['attributes']['data-pg-edit']);
	pg_assert_same('15', $row['cells']['email']['attributes']['data-pg-edit-id']);
	pg_assert_same('1', $row['cells']['opt_in']['attributes']['data-pg-edit-value']);
	pg_assert_same('2', $row['cells']['mobile_phone']['attributes']['data-pg-edit-value']);
	pg_assert_false(isset($row['cells']['user']['attributes']), 'the connected user stays text');
	pg_assert_false(isset($row['cells']['picture']['attributes']));

	$row = pg_contact_list_row(pg_test_contact_quick_contact(), array('role' => 2), 'my_contacts_by_home_address', '', '');
	pg_assert_same('L2', $row['cells']['address_2']['attributes']['data-pg-edit-value']);

	$row = pg_contact_list_row(pg_test_contact_quick_contact(), array('role' => 0), 'all_duplicate_contacts', '', '');
	pg_assert_false(isset($row['cells']['address']['attributes']), 'the duplicates view\'s address summary stays text');
	pg_assert_false(isset($row['cells']['groups']['attributes']), 'no groups field without choices');

	$row = pg_contact_list_row(pg_test_contact_quick_contact(), array('role' => 0), 'default', 'A', '', array('3', '12'), array('3' => 'A', '4' => 'B'));
	pg_assert_same('groups', $row['cells']['groups']['attributes']['data-pg-edit']);
	pg_assert_same('3', $row['cells']['groups']['attributes']['data-pg-edit-value'], 'the groups among the choices');
}

function test_contact_quick_endpoint_answers_before_the_session_keeps_the_request()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_contacts.php');
	$endpoint = strpos($source, 'pg_list_edit_requested()');

	pg_assert_true($endpoint !== false, 'the list answers its quick edits');
	pg_assert_true($endpoint < strpos($source, "\$_SESSION['software']['view_contacts'][\$key] = \$value;"), 'the edit\'s field and value are not kept as filters');
	pg_assert_contains("'all_duplicate_contacts'", substr($source, $endpoint, 1200), 'a User does not edit through the duplicates view');
}
