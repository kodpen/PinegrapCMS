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
	foreach (array('add_referral_source.php', 'edit_referral_source.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$check = strpos($source, 'pg_referral_source_form_error($values, $others)');
		pg_assert_true($check !== false, $file . ' refuses a blank name or code');
		pg_assert_true($check < strpos($source, 'pg_store_referral_source_save('), $file . ' refuses them before the save');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_referral_source.php');
	pg_assert_contains('<button type="submit" name="submit_delete" value="Delete" formnovalidate', $edit, 'Delete is not blocked by an empty required box');
	pg_assert_true(strpos($edit, 'pg_referral_source_form_error($values, $others)') > strpos($edit, 'pg_store_referral_source_delete($id)'), 'Delete is never refused for a blank value');
}

function test_referral_source_form_error_sort_order_range()
{
	$base = array('name' => 'Radio', 'code' => 'RAD');

	foreach (array('', '0', '7', '999') as $ok) {
		pg_assert_same('', pg_referral_source_form_error($base + array('sort_order' => $ok)), 'sort order "' . $ok . '" is accepted');
	}

	foreach (array('1000', '-5', 'abc', '12,50', '1.5', '40000') as $bad) {
		pg_assert_contains(lang('Sort Order'), pg_referral_source_form_error($base + array('sort_order' => $bad)), 'sort order "' . $bad . '" is refused');
	}
}

function test_referral_source_form_error_duplicate_name_or_code()
{
	$others = array(
		array('name' => 'Radio', 'code' => 'RAD'),
		array('name' => 'Friend', 'code' => 'FRI'),
	);

	pg_assert_same('', pg_referral_source_form_error(array('name' => 'Search', 'code' => 'SEA', 'sort_order' => ''), $others), 'a new name and code');
	pg_assert_contains('RAD', pg_referral_source_form_error(array('name' => 'Radio ads', 'code' => 'rad', 'sort_order' => ''), $others), 'a code of another source is refused, case aside');
	pg_assert_contains('Friend', pg_referral_source_form_error(array('name' => 'FRIEND', 'code' => 'NEW', 'sort_order' => ''), $others), 'a name of another source is refused, case aside');
	pg_assert_same('', pg_referral_source_form_error(array('name' => 'Radio', 'code' => 'RAD', 'sort_order' => '')), 'no other sources given, nothing to clash with');
}

function test_referral_source_form_sort_order_value()
{
	pg_assert_same(0, pg_referral_source_sort_order(''), 'an empty sort order is 0');
	pg_assert_same(25, pg_referral_source_sort_order('025'), 'leading zeros dropped');
}

function test_referral_source_form_card_texts_and_bounds()
{
	$create = pg_referral_source_form_card(null);
	$edit = pg_referral_source_form_card(array('name' => 'A', 'code' => 'B', 'sort_order' => '1'));

	pg_assert_contains(lang('New Referral Source Information'), $create, 'create card heading');
	pg_assert_contains(lang('Referral Source Information'), $edit, 'edit card heading');
	pg_assert_true(strpos($edit, lang('New Referral Source Information')) === false, 'edit does not say new');
	pg_assert_true(strpos($create, 'Order Preview Page') === false && strpos($create, lang('Order Preview Page Display Options')) === false, 'no order preview group heading');
	pg_assert_contains('min="0" max="999"', $create, 'the sort order box carries its range for the +/- buttons');
	pg_assert_contains('data-inputmask-alias="integer" data-inputmask-allowMinus="false"', $create, 'whole numbers only');
}

function test_referral_source_screens_check_others_and_notice()
{
	foreach (array('add_referral_source.php', 'edit_referral_source.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_referral_source_form_error($values, $others)', $source, $file . ' checks the other sources');
		pg_assert_contains("new liveform('view_referral_sources')", $source, $file . ' leaves a notice for the list');
	}

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_referral_source.php');
	pg_assert_contains('pg_store_referral_source_others($id)', $edit, 'edit leaves its own row out of the clash check');
	pg_assert_contains('WHERE id <> ?', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/store_lookups.php'), 'the clash check reads every other row');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_referral_sources.php');
	pg_assert_contains("'sort_order'", $list, 'the list sorts by sort order');
	pg_assert_contains('output_notices()', $list, 'the list prints the notices');
}

function test_referral_source_form_wide_layout()
{
	$card = pg_referral_source_form_card(null);
	pg_assert_contains('col-12 lg:col-8 2xl:col-9 order-1', $card, 'the card sits in the form column');

	$status = pg_referral_source_form_status(array('name' => 'Friend <A>', 'code' => 'F&A', 'sort_order' => ''), pg_referral_source_form_status_row('Extra', 'x'));
	pg_assert_contains('col-12 lg:col-4 2xl:col-3 order-2', $status, 'the Summary sits in the status column');
	pg_assert_contains('class="position-sticky" style="top:3.3rem;"', $status, 'the status box sticks below the top bar');
	pg_assert_contains('<span id="referral_source_summary_name">Friend &lt;A&gt;</span>', $status, 'the name is shown, escaped');
	pg_assert_contains('<span id="referral_source_summary_code">F&amp;A</span>', $status, 'the code is shown, escaped');
	pg_assert_contains('<span id="referral_source_summary_sort_order">0</span>', $status, 'an empty sort order reads 0, as stored');
	pg_assert_contains('<dt class="fw-normal">Extra</dt>', $status, 'the screen row follows');

	$create = pg_referral_source_form_status(null);
	pg_assert_contains('<span id="referral_source_summary_name"></span>', $create, 'create starts with an empty name');
	pg_assert_true(strpos($create, 'border-bottom">' . "\n" . '                                                <dt class="fw-normal">' . h(lang('Sort Order'))) === false, 'the last row draws no line under it');

	$layout = pg_referral_source_form_layout($card . $create, '<button>Go</button>');
	pg_assert_true(strpos($layout, 'order-1') < strpos($layout, 'order-2'), 'the status column comes after the form column, for narrow screens');
	pg_assert_contains('col-12 order-3 position-sticky" style="bottom:0; z-index:3;"', $layout, 'the save bar sticks on its own column');
	pg_assert_contains('<button>Go</button>', $layout, 'the buttons sit in the bar');
}

function test_referral_source_screens_use_wide_layout()
{
	pg_assert_contains("pg_referral_source_form_status(null, pg_referral_source_form_status_row(lang('Other Referral Sources')", (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_referral_source.php'), 'create shows the other sources count');
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_referral_source.php');
	pg_assert_contains("pg_referral_source_form_status(\$values, pg_referral_source_form_status_row(lang('Last Modified')", $edit, 'edit shows the last change');
	foreach (array('add_referral_source.php', 'edit_referral_source.php') as $file) {
		pg_assert_contains('pg_referral_source_form_layout(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file), $file . ' draws the shared save bar');
	}
}
