<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the referral source form shared by add_referral_source.php and
 * edit_referral_source.php (includes/forms/referral_source_form.php): the
 * posted values, the card on each screen and both screens drawing it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/referral_source_form.php');

function test_referral_source_form_values()
{
	pg_assert_same(
		array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => '3'),
		pg_referral_source_form_values(array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => 3, 'id' => '5')),
		'the three fields as text, other keys ignored');

	pg_assert_same(
		array('name' => '', 'code' => '', 'sort_order' => ''),
		pg_referral_source_form_values(array('name' => array('x'), 'code' => null)),
		'missing, null or nested values are empty');
}

function test_referral_source_form_card()
{
	$create = pg_referral_source_form_card(null);
	pg_assert_contains('id="name" name="name" class="form-control" maxlength="50" required />', $create, 'create prints no name value');
	pg_assert_contains('id="code" name="code" class="form-control" maxlength="50" required />', $create, 'create prints no code value');
	pg_assert_contains('border-end-0" value="" type="text" name="sort_order"', $create, 'create leaves the sort order empty');

	$edit = pg_referral_source_form_card(array('name' => 'Friend "A"', 'code' => 'F&A', 'sort_order' => '7'));
	pg_assert_contains('maxlength="50" value="Friend &quot;A&quot;" required />', $edit, 'edit shows the stored name, escaped');
	pg_assert_contains('maxlength="50" value="F&amp;A" required />', $edit, 'edit shows the stored code, escaped');
	pg_assert_contains('border-end-0" value="7" type="text" name="sort_order"', $edit, 'edit shows the stored sort order');
}

function test_referral_source_form_both_screens_draw_the_shared_card()
{
	foreach (array('add_referral_source.php', 'edit_referral_source.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_referral_source_form_card(', $source, $file . ' draws the shared card');
		pg_assert_contains('pg_referral_source_form_values($_POST)', $source, $file . ' reads the posted values through the shared helper');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps its gate');
	}
}

function test_referral_source_form_values_are_trimmed()
{
	pg_assert_same(
		array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => '3'),
		pg_referral_source_form_values(array('name' => '  Radio ', 'code' => ' RAD  ', 'sort_order' => ' 3 ')),
		'spaces around the values are dropped');
	pg_assert_same(
		array('name' => '', 'code' => '', 'sort_order' => ''),
		pg_referral_source_form_values(array('name' => '   ', 'code' => "\t", 'sort_order' => '')),
		'a value of only spaces is empty');
}

function test_referral_source_form_error()
{
	pg_assert_same('', pg_referral_source_form_error(array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => '')), 'name and code given');
	pg_assert_contains(lang('Referral Source Name'), pg_referral_source_form_error(array('name' => '', 'code' => 'RAD', 'sort_order' => '')), 'a blank name is named');
	pg_assert_contains(lang('Referral Source Code'), pg_referral_source_form_error(array('name' => 'Radio', 'code' => '', 'sort_order' => '')), 'a blank code is named');
}

function test_referral_source_screens_refuse_blank_values()
{
	$queries = array('add_referral_source.php' => 'INSERT INTO referral_sources', 'edit_referral_source.php' => 'UPDATE referral_sources SET');

	foreach ($queries as $file => $query) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$check = strpos($source, 'pg_referral_source_form_error($values)');
		pg_assert_true($check !== false, $file . ' refuses a blank name or code');
		pg_assert_true($check < strpos($source, '$result = db_q($query, array($values[\'name\']'), $file . ' refuses them before the save');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_referral_source.php');
	pg_assert_contains('<button type="submit" name="submit_delete" value="Delete" formnovalidate', $edit, 'Delete is not blocked by an empty required box');
	pg_assert_true(strpos($edit, 'pg_referral_source_form_error($values)') > strpos($edit, 'DELETE FROM referral_sources WHERE id = ?'), 'Delete is never refused for a blank value');
}
