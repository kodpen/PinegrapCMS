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
