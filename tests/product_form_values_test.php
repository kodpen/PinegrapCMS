<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the check of a product form value the designer cart widget posts
 * (pg_product_form_value_error(), cart_action.php): a date, a date and time,
 * an e-mail address or a time that is not one, and a pick list, radio button
 * or check box value outside the field's option list, are refused, as the
 * classic cart and the express order refuse them. cart_action.php stored
 * whatever was posted.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Valid values and empty values pass.
function test_product_form_value_valid_passes()
{
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'date', 'label' => 'Gün'), '2026-11-05', array()), 'ISO date');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'date and time', 'label' => 'An'), '2026-11-05T17:30', array()), 'ISO date and time');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'email address', 'label' => 'E-posta'), 'a@ornek.com', array()), 'e-mail address');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'time', 'label' => 'Saat'), '09:15', array()), 'time');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'date', 'label' => 'Gün'), '', array()), 'empty date');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'email address', 'label' => 'E-posta'), '  ', array()), 'blank e-mail');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'text box', 'label' => 'Not'), 'anything', array()), 'text box is free');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'pick list', 'label' => 'Renk'), 'Mavi', array('Kırmızı', 'Mavi')), 'option of the list');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'check box', 'label' => 'Ek'), array('A', 'B'), array('A', 'B', 'C')), 'boxes of the list');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'check box', 'label' => 'Onay'), 'on', array()), 'a lone box without options');
	pg_assert_same(null, pg_product_form_value_error(array('type' => 'pick list', 'label' => 'Renk'), '', array('Kırmızı')), 'no choice');
}

// A value that is not of its type, or not of the list, gets an error.
function test_product_form_value_invalid_refused()
{
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'date', 'label' => 'Gün'), 'yarın', array())), 'not a date');
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'date', 'label' => 'Gün'), '2026-02-30', array())), 'no such day');
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'date and time', 'label' => 'An'), '2026-11-05T25:00', array())), 'no such hour');
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'email address', 'label' => 'E-posta'), "a@ornek.com\r\nBcc: b@ornek.com", array())), 'not one address');
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'time', 'label' => 'Saat'), '9 o\'clock', array())), 'not a time');
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'pick list', 'label' => 'Renk'), 'Yeşil', array('Kırmızı', 'Mavi'))), 'outside the list');
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'radio button', 'label' => 'Boy'), 'XXL', array('S', 'M'))), 'radio outside the list');
	pg_assert_true(is_string(pg_product_form_value_error(array('type' => 'check box', 'label' => 'Ek'), array('A', 'Z'), array('A', 'B'))), 'one box outside the list');
}

// The label goes into the message escaped.
function test_product_form_value_label_escaped()
{
	$error = pg_product_form_value_error(array('type' => 'email address', 'label' => '<b>E</b>'), 'x', array());
	pg_assert_true(strpos($error, '<b>') === false, 'label escaped');
}

// The designer cart widget's handler and the express order use the checks.
function test_product_form_value_wired()
{
	$cart = file_get_contents(PG_FUNCTIONS_DIR . '/cart_action.php');
	pg_assert_true(strpos($cart, 'pg_product_form_value_error(') !== false, 'cart_action.php checks product form values');

	$express = file_get_contents(PG_FUNCTIONS_DIR . '/express_order.php');
	pg_assert_true(strpos($express, 'pg_cf_option_value_error(') !== false, 'express_order.php checks product form options');
}
