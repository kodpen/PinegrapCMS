<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact group form shared by the create and the edit
 * screens (includes/forms/contact_group_form.php): the values a save
 * writes, the browser-side required name on create only, and that both
 * screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');

function test_contact_group_form_values_in_save_order()
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$_SESSION = array();

	$form = new liveform('contact_group_form_test');
	$form->set('name', 'Newsletter');
	$form->set('email_subscription', '1');
	$form->set('email_subscription_type', 'closed');
	$form->set('description', "Monthly <b>news</b> & O'Neil");

	pg_assert_same(
		array('Newsletter', '1', 'closed', "Monthly <b>news</b> & O'Neil"),
		pg_contact_group_form_values($form),
		'name, subscription, type, description as raw strings');

	$form = new liveform('contact_group_form_test_empty');
	pg_assert_same(array('', '', '', ''), pg_contact_group_form_values($form), 'unposted fields are empty strings');

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}
}

function test_contact_group_form_required_only_on_create()
{
	$add = (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_contact_group.php');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact_group.php');
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');

	pg_assert_contains('pg_contact_group_form_card($liveform, true)', $add, 'create marks the name required');
	pg_assert_contains('pg_contact_group_form_card($liveform)', $edit, 'edit leaves it as it was');
	pg_assert_contains("'class'=>'form-control') + \$required)", $form, 'required follows the class attribute');
	pg_assert_contains('WHERE (name = ?) AND (id != ?)', $form, 'edit skips its own name');
}

function test_contact_group_form_screens_use_shared_form()
{
	foreach (array('add_contact_group.php', 'edit_contact_group.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/contact_group_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_contact_group_form_card($liveform', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_contact_group_form_validate_name($liveform, ', $source, $file . ' checks the name the shared way');
		pg_assert_contains('pg_contact_group_form_values($liveform)', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('get_token_field()', $source, $file . ' prints the token');
		pg_assert_false(strpos($source, 'email_subscription_type_row') !== false, $file . ' keeps no copy of the card');
	}
}
