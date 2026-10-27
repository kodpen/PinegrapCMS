<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact form shared by the create and the edit screens
 * (includes/forms/contact_form.php): the column values a posted contact is
 * saved with, the commission rate the affiliate card shows, the contact
 * group switches, and that both screens use the shared form and the token.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

function test_contact_form_posted()
{
	pg_assert_same('Ayşe', pg_contact_form_posted(array('first_name' => 'Ayşe'), 'first_name'), 'posted text');
	pg_assert_same('1', pg_contact_form_posted(array('opt_in' => 1), 'opt_in'), 'number as text');
	pg_assert_same('', pg_contact_form_posted(array(), 'first_name'), 'missing field');
	pg_assert_same('', pg_contact_form_posted(array('first_name' => array('x')), 'first_name'), 'array field');
}

function test_contact_form_columns()
{
	$post = array(
		'first_name' => 'Ayşe',
		'last_name' => "O'Neil",
		'email_address' => 'ayse@example.com',
		'opt_in' => '1',
		'expiration_date' => '2027-3-9',
		'tax_number' => ' 1234567890 ',
		'tax_office' => ' Kadıköy ',
		'file_id' => '42',
		'image_url' => '',
		'submit_save' => 'Save',
		'parasut_contact_id' => '7',
	);

	$columns = pg_contact_form_columns($post, 5);

	pg_assert_same('Ayşe', $columns['first_name'], 'first name');
	pg_assert_same("O'Neil", $columns['last_name'], 'raw value, bound later');
	pg_assert_same('', $columns['salutation'], 'unposted text column');
	pg_assert_same('1', $columns['opt_in'], 'opt-in');
	pg_assert_same('2027-03-09', $columns['expiration_date'], 'ISO date padded');
	pg_assert_same('1234567890', $columns['tax_number'], 'tax number trimmed');
	pg_assert_same('Kadıköy', $columns['tax_office'], 'tax office trimmed');
	pg_assert_same('42', $columns['file_id'], 'picked file');
	pg_assert_same('', $columns['image'], 'no image URL');
	pg_assert_same('5', $columns['user'], 'saving user');
	pg_assert_false(isset($columns['submit_save']), 'buttons are no column');
	pg_assert_false(isset($columns['parasut_contact_id']), 'Parasut id is the edit screen\'s own');
	pg_assert_false(isset($columns['affiliate_code']), 'affiliate columns apart');

	foreach ($columns as $name => $value) {
		pg_assert_true(is_string($value), $name . ' is bound as a string');
	}

	pg_assert_same(count(pg_contact_form_text_columns()) + 6, count($columns), 'text columns and the six others');

	$empty = pg_contact_form_columns(array(), 1);
	pg_assert_same('', $empty['expiration_date'], 'no expiration date');
	pg_assert_same('', $empty['file_id'], 'no file');
}

function test_contact_form_affiliate_columns()
{
	pg_assert_same(
		array('affiliate_approved' => '1', 'affiliate_name' => 'Shop', 'affiliate_code' => 'ABC123', 'affiliate_commission_rate' => '10'),
		pg_contact_form_affiliate_columns(array('affiliate_approved' => '1', 'affiliate_name' => 'Shop', 'affiliate_code' => 'typed', 'affiliate_commission_rate' => '10'), 'ABC123'),
		'the settled code wins over the posted one');

	pg_assert_same(
		array('affiliate_approved' => '', 'affiliate_name' => '', 'affiliate_code' => '', 'affiliate_commission_rate' => ''),
		pg_contact_form_affiliate_columns(array(), null),
		'unticked box and no code');
}

function test_contact_form_commission_rate_value()
{
	pg_assert_same('', pg_contact_form_commission_rate_value(0), 'zero shown blank');
	pg_assert_same('', pg_contact_form_commission_rate_value('0'), 'stored zero shown blank');
	pg_assert_same('', pg_contact_form_commission_rate_value('0.00'), 'decimal zero shown blank');
	pg_assert_same('', pg_contact_form_commission_rate_value(''), 'blank');
	pg_assert_same('', pg_contact_form_commission_rate_value(null), 'none');
	pg_assert_same('15', pg_contact_form_commission_rate_value('15'), 'rate kept');
}

function test_contact_form_group_cells()
{
	$groups = array(
		array('id' => '3', 'name' => 'News <letter>', 'email_subscription' => '1'),
		array('id' => '8', 'name' => 'VIP', 'email_subscription' => '0'),
	);

	// A new contact: no group ticked, opted in to every subscription.
	$html = pg_contact_form_group_cells($groups, array());
	pg_assert_contains('name="contact_group_3" id="contact_group_3" value="1" class="collapse-switcher" data-bs-target="#contact_group_opt_in_cell_3"/>', $html, 'subscription group unticked, with its opt-in');
	pg_assert_contains('name="contact_group_opt_in_3" id="contact_group_opt_in_3" value="1" checked="checked"', $html, 'opted in');
	pg_assert_contains('name="contact_group_8" id="contact_group_8" value="1" class="collapse-switcher"/>', $html, 'plain group unticked');
	pg_assert_false(strpos($html, 'contact_group_opt_in_8') !== false, 'no opt-in without subscription');
	pg_assert_contains('News &lt;letter&gt;', $html, 'name escaped');

	// A stored contact: in group 3 and opted out of it, in group 8.
	$html = pg_contact_form_group_cells($groups, array('3' => array('in' => true, 'opt_in' => false), '8' => array('in' => true, 'opt_in' => true)));
	pg_assert_contains('name="contact_group_3" id="contact_group_3" value="1" class="collapse-switcher" checked="checked" data-bs-target=', $html, 'member ticked');
	pg_assert_contains('name="contact_group_opt_in_3" id="contact_group_opt_in_3" value="1" class=""', $html, 'opted out');
	pg_assert_contains('name="contact_group_8" id="contact_group_8" value="1" class="collapse-switcher" checked="checked"/>', $html, 'plain group ticked');
}

function test_contact_form_screens_use_the_shared_form()
{
	foreach (array('add_contact.php', 'edit_contact.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');", $source, $file . ': loads the form');
		pg_assert_contains('get_token_field()', $source, $file . ': prints the token');
		pg_assert_contains('validate_token_field();', $source, $file . ': checks the token');
		pg_assert_contains('validate_contacts_access($user);', $source, $file . ': contacts gate');
		pg_assert_contains('pg_contact_form_detail_cards(', $source, $file . ': shared cards');
		pg_assert_contains('pg_contact_form_save_groups(', $source, $file . ': shared group save');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact.php');
	pg_assert_contains('validate_contact_access($user, $_REQUEST[\'id\'])', $edit, 'edit: contact access check');
	pg_assert_contains("pg_audit_record('update', 'contacts'", $edit, 'edit: update audit line');
	pg_assert_contains("pg_audit_record('delete', 'contacts'", $edit, 'edit: delete audit line');

	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_contact.php');
	pg_assert_contains("pg_audit_record('create', 'contacts', \$contact_id);", $add, 'add: create audit line');
	pg_assert_contains('pg_announce_contact_created($contact_id);', $add, 'add: create event');
}
