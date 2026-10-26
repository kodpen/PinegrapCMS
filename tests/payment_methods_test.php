<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the one list of checkout payment methods (includes/fn/payments.php):
 * which methods an order is offered for the store's switches and the redirect
 * drivers switched on, their order, the values every list knows and the label
 * of a driver's method. No database is reached: the redirect driver below is
 * registered with this file's path and its switch is passed in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_bridge.php');

function pg_payment_pgtestmethods_capabilities()
{
	return array();
}

function payment_methods_test_register()
{
	static $done = false;

	if (!$done) {
		pg_payment_driver_register('pgtestmethods', array('label' => 'Pg Methods Pay', 'kind' => 'redirect', 'method' => 'Pay With Pg Methods', 'file' => __FILE__));
		$done = true;
	}
}

function payment_methods_test_switches($on = array())
{
	$switches = array(
		'Credit/Debit Card'       => false,
		'PayPal Express Checkout' => false,
		'Pay With Iyzico'         => false,
		'Offline Payment'         => false,
	);

	foreach ($on as $method) {
		$switches[$method] = true;
	}

	return $switches;
}

function test_payment_methods_all_on_in_the_checkout_order()
{
	$all = payment_methods_test_switches(array('Offline Payment', 'Pay With Iyzico', 'PayPal Express Checkout', 'Credit/Debit Card'));

	pg_assert_same(
		array('Credit/Debit Card', 'PayPal Express Checkout', 'Pay With Iyzico', 'Pay With Example', 'Offline Payment'),
		pg_payment_checkout_methods_decide($all, array('Pay With Example'), false, true)
	);
}

function test_payment_methods_switches_off_leave_methods_out()
{
	pg_assert_same(array(), pg_payment_checkout_methods_decide(payment_methods_test_switches(), array(), false, true));
	pg_assert_same(
		array('PayPal Express Checkout'),
		pg_payment_checkout_methods_decide(payment_methods_test_switches(array('PayPal Express Checkout')), array(), false, true)
	);
	pg_assert_same(
		array('Pay With Example'),
		pg_payment_checkout_methods_decide(payment_methods_test_switches(), array('Pay With Example'), false, true),
		'a redirect driver switched on is offered without any config switch'
	);
}

function test_payment_methods_recurring_order_gets_no_redirect_method()
{
	$all = payment_methods_test_switches(array('Offline Payment', 'Pay With Iyzico', 'PayPal Express Checkout', 'Credit/Debit Card'));

	pg_assert_same(
		array('Credit/Debit Card', 'Offline Payment'),
		pg_payment_checkout_methods_decide($all, array('Pay With Example'), true, true)
	);
}

function test_payment_methods_offline_only_when_the_order_allows_it()
{
	$switches = payment_methods_test_switches(array('Offline Payment', 'Credit/Debit Card'));

	pg_assert_same(array('Credit/Debit Card'), pg_payment_checkout_methods_decide($switches, array(), false, false));
	pg_assert_same(array(), pg_payment_checkout_methods_decide(payment_methods_test_switches(), array(), false, true), 'allowed but switched off');
}

function test_payment_methods_driver_list_ignores_builtin_and_unknown_values()
{
	$all = payment_methods_test_switches(array('Pay With Iyzico'));

	pg_assert_same(
		array('Pay With Iyzico', 'Pay With Example'),
		pg_payment_checkout_methods_decide($all, array('Pay With Iyzico', 'Pay With Example', 'Pay With Example', '', 12), false, true),
		'a built-in value follows its own switch, a value comes once'
	);
}

function test_payment_methods_values_and_driver_methods()
{
	payment_methods_test_register();

	$drivers = pg_payment_driver_methods();

	pg_assert_same('pgtestmethods', $drivers['Pay With Pg Methods'] ?? null);
	pg_assert_false(isset($drivers['Pay With Iyzico']), 'Pay With Iyzico keeps its config switch');

	$values = pg_payment_method_values();

	pg_assert_same(array('Credit/Debit Card', 'PayPal Express Checkout', 'Pay With Iyzico', 'Offline Payment'), array_slice($values, 0, 4));
	pg_assert_true(in_array('Pay With Pg Methods', $values, true));
	pg_assert_same(count($values), count(array_unique($values)));
}

function test_payment_methods_driver_method_label()
{
	payment_methods_test_register();

	pg_assert_same('Pg Methods Pay', pg_payment_method_label('Pay With Pg Methods'));
	pg_assert_same('Unknown Method', pg_payment_method_label('Unknown Method'));
}

function test_payment_methods_driver_method_is_an_intermediary_on_the_e_archive()
{
	payment_methods_test_register();

	pg_assert_same('ODEMEARACISI', erp_payment_method_code('Pay With Pg Methods'));
	pg_assert_same('ODEMEARACISI', erp_payment_method_code('Pay With Iyzico'));
	pg_assert_same('KREDIKARTI/BANKAKARTI', erp_payment_method_code('Credit/Debit Card'));
	pg_assert_same('', erp_payment_method_code('Unknown Method'));
}

function test_payment_methods_legal_documents_name_driver_methods()
{
	payment_methods_test_register();

	$methods = pg_legal_document_payment_methods();

	pg_assert_same('Pg Methods Pay', $methods['Pay With Pg Methods'] ?? null);
	pg_assert_same(array('Credit/Debit Card', 'PayPal Express Checkout', 'Offline Payment', 'Pay With Iyzico'), array_slice(array_keys($methods), 0, 4));
}
