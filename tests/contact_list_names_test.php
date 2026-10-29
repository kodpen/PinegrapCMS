<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names and marks of the contacts list (view_contacts.php and
 * includes/fn/contact_list.php): one Website filter, labels that point at
 * their own field, the group counts in the panel's number format, a named
 * row check box, a readable Opt-In mark and silent icon words.
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

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $contact_list_names_test_name => $contact_list_names_test_value) {
	if (!defined($contact_list_names_test_name)) {
		define($contact_list_names_test_name, $contact_list_names_test_value);
	}
}

function contact_list_names_test_source()
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/view_contacts.php');
}

function contact_list_names_test_contact($fields)
{
	return array_merge(array(
		'id' => '15', 'image' => '', 'file_id' => '0', 'first_name' => '', 'last_name' => '', 'company' => '',
		'business_phone' => '', 'home_phone' => '', 'mobile_phone' => '', 'email_address' => '', 'opt_in' => '1',
		'last_modified_username' => '', 'user_id' => null, 'user_username' => null, 'user_role' => null, 'timestamp' => time(),
	), $fields);
}

function test_contact_list_names_one_website_filter()
{
	$source = contact_list_names_test_source();

	pg_assert_same(1, substr_count($source, 'id="website" name="website"'), 'a second box with the same name sends an empty value last and the filter is dropped');
	pg_assert_same(1, substr_count($source, '<label for="website"'));
}

function test_contact_list_names_labels_point_at_their_field()
{
	$source = contact_list_names_test_source();

	pg_assert_contains('<label for="expiration_date" class="form-label">\' . lang(\'Expiration Date\')', $source);
	pg_assert_same(1, substr_count($source, '<label for="member_id"'), 'Member ID has one label');
	pg_assert_true(strpos($source, '$output_opt_status = lang(\'Opt-In\');') === false, 'My Subscribers shows the status in a box the label names');
	pg_assert_contains('id="opt_in_status" name="fake_opt_in_status"', $source);
}

function test_contact_list_names_group_counts_formatted()
{
	$source = contact_list_names_test_source();

	pg_assert_true(strpos($source, '(\' . $number_of_contacts . \')') === false, 'a raw count reads 1234 where the select shows 1.234');
	pg_assert_contains('[\' . lang(\'None\') . \'] (\' . pg_format_number(get_number_of_contacts(', $source);
}

function test_contact_list_names_silent_icon_words()
{
	$source = contact_list_names_test_source();

	foreach (array('filter_list', 'sync', 'healing', 'radio_button_checked', 'radio_button_unchecked', 'delete') as $word) {
		pg_assert_true(preg_match('/<(span|i) class="material-icons[^"]*">' . $word . '</', $source) === 0, $word . ' is read before the label');
	}

	pg_assert_true(strpos($source, 'aria-label="Button Bar"') === false);
	pg_assert_true(strpos($source, 'aria-label="data edit buttons ') === false);
	pg_assert_contains('aria-label="\' . h(lang(\'Button Bar\')) . \'"', $source);
	pg_assert_contains('aria-label="\' . h(lang(\'data edit buttons\')) . \'"', $source);
}

function test_contact_list_names_row_check_box()
{
	$label = function ($name) {
		return 'aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => $name))) . '"';
	};

	$row = pg_contact_list_row(contact_list_names_test_contact(array('first_name' => '<b>Ali</b>', 'last_name' => 'Veli', 'company' => 'Acme', 'email_address' => 'a@x')), array('role' => 0), 'default', '', '');
	pg_assert_contains($label('<b>Ali</b> Veli'), $row['cells'][0]['html'], 'the name first');
	pg_assert_same(1, substr_count($row['cells'][0]['html'], 'class='), 'one class attribute');

	$row = pg_contact_list_row(contact_list_names_test_contact(array('company' => 'Acme', 'email_address' => 'a@x')), array('role' => 0), 'default', '', '');
	pg_assert_contains($label('Acme'), $row['cells'][0]['html'], 'then the company');

	$row = pg_contact_list_row(contact_list_names_test_contact(array('email_address' => 'a@x')), array('role' => 0), 'default', '', '');
	pg_assert_contains($label('a@x'), $row['cells'][0]['html'], 'then the e-mail address');
}

function test_contact_list_names_opt_in_mark()
{
	$row = pg_contact_list_row(contact_list_names_test_contact(array('email_address' => 'a@x')), array('role' => 0), 'default', '', '');

	pg_assert_contains('<span class="material-icons" aria-hidden="true">task_alt</span>', $row['cells'][9]['html']);
	pg_assert_contains('<span class="visually-hidden">' . h(lang('Yes')) . '</span>', $row['cells'][9]['html']);

	$row = pg_contact_list_row(contact_list_names_test_contact(array('opt_in' => '0')), array('role' => 0), 'default', '', '');
	pg_assert_same('', $row['cells'][9]['html']);
}
