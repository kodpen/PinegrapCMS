<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tax columns a profile post writes
 * (pg_my_account_profile_tax_columns(), includes/fn/widgets_account.php):
 * my_account_profile.php uses the same list for the contact it updates and
 * for the contact it creates, so a member without a contact keeps the tax
 * number and tax office typed on the first save.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The legacy screen draws both fields: both are written, trimmed.
function test_my_account_profile_tax_columns_legacy_post()
{
	pg_assert_same(
		array('tax_number' => '1234567890', 'tax_office' => 'Kadıköy'),
		pg_my_account_profile_tax_columns(null, true, array('tax_number' => ' 1234567890 ', 'tax_office' => 'Kadıköy ')));
}

// A legacy post without the fields (a custom layout that leaves them out)
// writes neither, so the stored values stay.
function test_my_account_profile_tax_columns_legacy_without_fields()
{
	pg_assert_same(array(), pg_my_account_profile_tax_columns(null, false, array('tax_number' => '', 'tax_office' => '')));
}

// A designed page writes only the controls it drew.
function test_my_account_profile_tax_columns_widget_post()
{
	pg_assert_same(
		array('tax_number' => '11111111111'),
		pg_my_account_profile_tax_columns(array('first_name', 'tax_number'), false, array('tax_number' => '11111111111', 'tax_office' => 'X')));
	pg_assert_same(array(), pg_my_account_profile_tax_columns(array('first_name'), true, array('tax_number' => '1', 'tax_office' => 'X')));
}

// A value that did not come as one string is written empty.
function test_my_account_profile_tax_columns_non_string()
{
	pg_assert_same(
		array('tax_number' => '', 'tax_office' => ''),
		pg_my_account_profile_tax_columns(null, true, array('tax_number' => array('1'), 'tax_office' => null)));
}

// The contact created for a member without one carries the tax columns.
function test_my_account_profile_insert_writes_tax_columns()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/my_account_profile.php');
	$insert = substr($source, strpos($source, 'INSERT INTO contacts'));
	$insert = substr($insert, 0, strpos($insert, 'mysqli_insert_id'));
	pg_assert_contains('$sql_profile_tax_insert', $insert);
}
