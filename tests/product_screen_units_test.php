<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the product screen's weight, dimension and commission rate
 * limit fields (product_builder.php): how a typed value with either decimal
 * separator reaches the columns, how much of a metric figure survives the
 * conversion to pounds and inches, and how the screen names its units.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The rich text is stored with {path} for the site's path; without a request
// the path is the root, as elsewhere in the tests.
if (!defined('PATH')) {
	define('PATH', '/');
}

require_once(PG_FUNCTIONS_DIR . '/product_builder.php');
require_once(__DIR__ . '/helpers/source_reader.php');

function product_screen_units_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/product_builder.php');
}

/**
 * Runs pg_pb_common_from_post() over the given post and gives the post back
 * as it was.
 */
function product_screen_units_test_common($post)
{
	$kept = $_POST;
	$_POST = $post;

	$common = pg_pb_common_from_post();

	$_POST = $kept;

	return $common;
}

// A rate typed with a comma or a dot is the same figure, stored as the
// decimal(5,2) column holds it; blank is 0.00, no limit.
function test_commission_rate_limit_reads_either_separator()
{
	pg_assert_same('7.50', pg_pb_commission_rate_from_input('7,5'));
	pg_assert_same('7.50', pg_pb_commission_rate_from_input('7.5'));
	pg_assert_same('12.50', pg_pb_commission_rate_from_input('12.50'));
	pg_assert_same('15.00', pg_pb_commission_rate_from_input(' 15 '), 'a whole number');
	pg_assert_same('0.00', pg_pb_commission_rate_from_input(''), 'blank');
	pg_assert_same('0.00', pg_pb_commission_rate_from_input('0'));
	pg_assert_same('0.00', pg_pb_commission_rate_from_input(null));
	pg_assert_same('7.56', pg_pb_commission_rate_from_input('7,555'), 'rounded to the column');
}

// What decimal(5,2) unsigned cannot hold is cut to its top.
function test_commission_rate_limit_held_by_the_column()
{
	pg_assert_same('999.99', pg_pb_commission_rate_from_input('1000'));
	pg_assert_same('999.99', pg_pb_commission_rate_from_input('999,995'), 'rounding past the top');
	pg_assert_same('999.99', pg_pb_commission_rate_from_input('999.99'));
	pg_assert_same('0.00', pg_pb_commission_rate_from_input('abc'), 'no digits');
}

// The post reader stores the rate through that reader, not the raw text.
function test_commission_rate_limit_posted_through_the_reader()
{
	$source = pg_test_function_source('pg_pb_common_from_post');

	pg_assert_true(strpos($source, "pg_pb_commission_rate_from_input(\$post('commission_rate_limit'))") !== false, 'the rate is read');
	pg_assert_false(strpos($source, "\$post('commission_rate_limit');") !== false, 'the raw text is not stored');
}

// A metric figure keeps the four places the decimal(12,4) columns hold, so
// 0.11 kg comes back as 0.11 kg and not as 0.1089.
function test_metric_dimensions_keep_four_places()
{
	$common = product_screen_units_test_common(array(
		'convert_to_metric_system' => '1',
		'weight' => '0,11',
		'length' => '2,5',
		'width'  => '10',
		'height' => '',
	));

	pg_assert_same(0.2425, $common['weight'], 'kg to lb');
	pg_assert_same(0.9843, $common['length'], 'cm to in');
	pg_assert_same(3.937, $common['width']);
	pg_assert_same(0.0, $common['height'], 'blank');

	pg_assert_same(0.11, round($common['weight'] * 0.45359237, 2), 'the stored pounds give the typed kilograms back');
	pg_assert_same(2.5, round($common['length'] * 2.54, 2), 'the stored inches give the typed centimetres back');
}

// Without the switch the typed figure is the stored one, whichever
// separator was used.
function test_imperial_dimensions_stored_as_typed()
{
	$common = product_screen_units_test_common(array(
		'weight' => '0,2500',
		'length' => '12.5',
		'width'  => '0',
		'height' => '1',
	));

	pg_assert_same(0.25, $common['weight']);
	pg_assert_same(12.5, $common['length']);
	pg_assert_same(0.0, $common['width']);
	pg_assert_same(1.0, $common['height']);
}

// The unit labels beside the fields go through lang(), and the commission
// rate limit box takes a decimal of the column's width.
function test_product_screen_units_and_rate_box()
{
	$source = product_screen_units_test_source();

	pg_assert_true(strpos($source, "for=\"weight\">' . lang('lbs') . '</label>") !== false, 'weight unit');
	pg_assert_true(strpos($source, "for=\"length\">' . lang('in') . '</label>") !== false, 'length unit');
	pg_assert_true(strpos($source, "for=\"width\">' . lang('in') . '</label>") !== false, 'width unit');
	pg_assert_true(strpos($source, "for=\"height\">' . lang('in') . '</label>") !== false, 'height unit');
	pg_assert_false(strpos($source, '>lbs</label>') !== false, 'no literal lbs');
	pg_assert_false(strpos($source, '>inc</label>') !== false, 'no literal inc');

	pg_assert_true(preg_match('/<input[^>]*name="commission_rate_limit"[^>]*>/', $source, $box) === 1, 'the rate box');
	pg_assert_true(strpos($box[0], 'inputmode="decimal"') !== false, 'a decimal keyboard');
	pg_assert_true(strpos($box[0], 'maxlength="6"') !== false, 'as wide as 999.99');
	pg_assert_false(strpos($box[0], 'maxlength="3"') !== false);
}

// A required field's label carries no hand-written asterisk: the panel
// script adds the one marker for every required control.
function test_product_screen_labels_carry_no_hand_written_asterisk()
{
	$source = product_screen_units_test_source();

	pg_assert_false(strpos($source, ">*' . lang(") !== false, 'no label starts with *');
	pg_assert_true(strpos($source, "for=\"name\" class=\"form-label\">' . lang('Product ID / SKU') . '</label>") !== false, 'the SKU label');
}
