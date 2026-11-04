<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the payment step of a designed form on the site
 * (includes/fn/form_payment_step.php) and the driver's browser part
 * (pg_payment_box()): which payment choices a form offers, the step's
 * markup for each choice, where the step goes in the form's tree, the
 * order's payment values, and what a driver's answer does to the record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/* ---------------------------------------------------------------------------
   The payment choices
   --------------------------------------------------------------------------- */

function test_form_payment_methods_decide()
{
	pg_assert_same(
		array('Credit/Debit Card', 'PayTR', 'Offline Payment'),
		pg_form_payment_methods_decide(true, array('PayTR'), true, 1),
		'the hosted card, the redirect drivers, then offline payment');

	pg_assert_same(array('PayTR'), pg_form_payment_methods_decide(false, array('PayTR'), true, 0),
		'no hosted card driver: no card; the form does not allow offline payment');

	pg_assert_same(array(), pg_form_payment_methods_decide(false, array(), false, 1),
		'offline payment only when the store takes it too');

	pg_assert_same(array('Credit/Debit Card'), pg_form_payment_methods_decide(true, array('Credit/Debit Card', 'Offline Payment', ''), true, 0),
		'a built-in method among the driver methods is not offered twice');
}

/* ---------------------------------------------------------------------------
   The step's markup
   --------------------------------------------------------------------------- */

function _test_form_payment_view($extra = array())
{
	return array_merge(array(
		'amount'  => '₺150,00',
		'methods' => array('Credit/Debit Card' => 'Credit/Debit Card'),
		'chosen'  => '',
		'box'     => '<div data-pg-payment-hosted="{}"></div>',
		'error'   => '',
	), $extra);
}

function test_form_payment_step_html_single_card()
{
	$html = pg_form_payment_step_html(_test_form_payment_view());

	pg_assert_contains('data-pg-cf-payment', $html, 'the step is marked');
	pg_assert_contains('₺150,00', $html, 'the amount line');
	pg_assert_contains('<input type="hidden" name="payment_method" value="Credit/Debit Card">', $html, 'one choice is posted as a hidden value');
	pg_assert_false(strpos($html, 'type="radio"') !== false, 'no radio for a single choice');
	pg_assert_contains('data-pg-payment-hosted', $html, 'the card box is printed');
}

function test_form_payment_step_html_several_choices()
{
	$html = pg_form_payment_step_html(_test_form_payment_view(array(
		'methods' => array('Credit/Debit Card' => 'Card', 'PayTR' => 'PayTR <b>', 'Offline Payment' => 'Offline'),
		'chosen'  => 'PayTR',
	)));

	pg_assert_same(3, substr_count($html, 'type="radio" name="payment_method"'), 'one radio per choice');
	pg_assert_contains('value="PayTR" required checked', $html, 'the posted choice stays chosen');
	pg_assert_contains('PayTR &lt;b&gt;', $html, 'labels are escaped');
	pg_assert_contains('data-pg-payment-hosted', $html, 'the card box is printed beside the choices');
}

function test_form_payment_step_html_redirect_and_offline_only()
{
	$html = pg_form_payment_step_html(_test_form_payment_view(array(
		'methods' => array('PayTR' => 'PayTR', 'Offline Payment' => 'Offline'),
		'box'     => '<div data-pg-payment-hosted="{}"></div>',
	)));

	pg_assert_false(strpos($html, 'data-pg-payment-hosted') !== false, 'no card choice: no card box');
	pg_assert_same(2, substr_count($html, 'type="radio"'), 'two choices');
	pg_assert_contains('data-pg-cf-offline', $html, 'offline payment says what follows');
}

function test_form_payment_step_html_none_and_error()
{
	$html = pg_form_payment_step_html(_test_form_payment_view(array('methods' => array(), 'error' => 'Card <declined>')));

	pg_assert_contains('alert-warning', $html, 'no choice: the visitor is told payment is not available');
	pg_assert_false(strpos($html, 'name="payment_method"') !== false, 'nothing to post');
	pg_assert_contains('Card &lt;declined&gt;', $html, 'the driver\'s reason, escaped');
}

/* ---------------------------------------------------------------------------
   Where the step goes
   --------------------------------------------------------------------------- */

function _test_form_payment_submit()
{
	return array('type' => 'semantic', 'children' => array(), 'props' => array(
		'tag' => 'button', 'text' => 'Send', '_attrs' => array(array('name' => 'type', 'value' => 'submit'))));
}

function _test_form_payment_input($name)
{
	return array('type' => 'semantic', 'children' => array(), 'props' => array(
		'tag' => 'input', '_attrs' => array(array('name' => 'name', 'value' => $name))));
}

function _test_form_payment_break($title)
{
	return array('type' => 'semantic', 'children' => array(), 'props' => array(
		'tag' => 'div', 'text' => $title, '_bindings' => array('section' => 'form_step')));
}

function test_form_payment_insert_before_submit()
{
	$captcha = array('type' => 'semantic', 'children' => array(), 'props' => array('tag' => 'div', '_bindings' => array('section' => 'captcha')));
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_test_form_payment_input('name'), $captcha, _test_form_payment_submit()));

	pg_assert_true(pg_cf_insert_payment_step($tree, '<p>pay</p>', 'Payment'), 'inserted');
	pg_assert_same(4, count($tree['children']), 'one node added, no break in an unstepped form');
	pg_assert_same('<p>pay</p>', $tree['children'][1]['props']['html'], 'before the CAPTCHA that stands before the submit');
}

function test_form_payment_insert_as_last_step()
{
	$row = array('type' => 'semantic', 'props' => array('tag' => 'div', 'cssClass' => 'row'), 'children' => array(_test_form_payment_submit()));
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(
		_test_form_payment_break('One'), _test_form_payment_input('a'),
		_test_form_payment_break('Two'), _test_form_payment_input('b'), $row));

	pg_cf_insert_payment_step($tree, '<p>pay</p>', 'Payment');
	pg_assert_same(7, count($tree['children']), 'a break and the step added to the stepped container');
	pg_assert_true(pg_cf_is_step_break($tree['children'][4]), 'a step break before the payment');
	pg_assert_same('Payment', $tree['children'][4]['props']['text'], 'titled');
	pg_assert_same('<p>pay</p>', $tree['children'][5]['props']['html'], 'the payment, then the row holding the submit');

	pg_assert_same(3, pg_cf_apply_steps($tree), 'the payment is a step of its own, the last');
}

function test_form_payment_insert_without_submit()
{
	$tree = array('type' => 'semantic', 'props' => array('tag' => 'div'), 'children' => array(_test_form_payment_input('a')));

	pg_assert_true(pg_cf_insert_payment_step($tree, '<p>pay</p>', 'Payment'), 'inserted');
	pg_assert_same('<p>pay</p>', $tree['children'][1]['props']['html'], 'at the end of the form');
}

/* ---------------------------------------------------------------------------
   The order's payment values and the driver's answer
   --------------------------------------------------------------------------- */

function test_form_payment_settle_query()
{
	$query = pg_form_payment_settle_query(12, 'Credit/Debit Card', 'pi_1', 15000);

	pg_assert_contains("WHERE id = ? AND status = 'incomplete'", $query['sql'], 'only an incomplete order');
	pg_assert_same(array('Credit/Debit Card', 'pi_1', '15000', '15000', '12'), $query['params'], 'method, payment id, subtotal and total');
	pg_assert_same(null, pg_form_payment_settle_query(0, 'Offline Payment', '', 100), 'no order: nothing');
}

function test_form_payment_answer_outcome()
{
	$ok = function ($status, $extra = array()) {
		return array_merge(array('success' => true, 'status' => $status, 'error' => '', 'html' => '', 'redirect_url' => ''), $extra);
	};

	pg_assert_same('complete', pg_form_payment_answer_outcome($ok('paid'))['outcome'], 'paid completes the order');
	pg_assert_same('complete', pg_form_payment_answer_outcome($ok('authorized'))['outcome'], 'authorized completes it');
	pg_assert_same('complete', pg_form_payment_answer_outcome($ok('pending'))['outcome'], 'pending completes it, unpaid');
	pg_assert_same('redirect', pg_form_payment_answer_outcome($ok('redirect', array('redirect_url' => 'https://x.test/3ds')))['outcome'], '3-D Secure page');

	$failed = pg_form_payment_answer_outcome(array('success' => false, 'status' => '', 'error' => 'The card was declined.'));
	pg_assert_same('failed', $failed['outcome'], 'a refusal');
	pg_assert_same('The card was declined.', $failed['error'], 'the driver\'s reason');

	$blank = pg_form_payment_answer_outcome(array('success' => false, 'status' => '', 'error' => ''));
	pg_assert_true($blank['error'] !== '', 'a refusal without words still says something');
}

/* ---------------------------------------------------------------------------
   The driver's browser part
   --------------------------------------------------------------------------- */

function test_payment_box_from_prepare()
{
	$definition = array('csp' => array('script-src' => array('https://js.stripe.com')));
	$prepare = array('success' => true, 'client' => array('script' => 'https://js.stripe.com/v3/', 'publishable_key' => 'pk_test_1'));
	$html = pg_payment_box('stripe', $prepare, $definition);

	pg_assert_contains('data-pg-payment-hosted=', $html, 'the box the script mounts');
	pg_assert_contains('pk_test_1', $html, 'the public values');
	pg_assert_contains('<input type="hidden" name="payment_token" value="">', $html, 'the token input');
	pg_assert_contains('<script src="https://js.stripe.com/v3/"></script>', $html, 'the service\'s script');
	pg_assert_contains('payment_hosted', $html, 'the site\'s script');

	$foreign = pg_payment_box('stripe', array('success' => true, 'client' => array('script' => 'https://evil.test/x.js')), $definition);
	pg_assert_contains('alert-warning', $foreign, 'a script from a host the driver does not list is refused');
	pg_assert_false(strpos($foreign, '<script') !== false, 'and not loaded');

	$failed = pg_payment_box('stripe', array('success' => false, 'client' => array()), $definition);
	pg_assert_contains('alert-warning', $failed, 'a failed prepare: not available');
}

/* ---------------------------------------------------------------------------
   The wiring
   --------------------------------------------------------------------------- */

function test_form_payment_wiring()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/custom_form.php');
	$return = strpos($form, 'pg_form_payment_return(');
	pg_assert_true(($return !== false) && ($return < strpos($form, 'validate_token_field();')), 'the return is taken before the token check');
	pg_assert_contains('pg_form_payment_retry(', $form, 'the payment alone posts back here');
	pg_assert_contains("'fee_owed'                      => (\$form_fee !== null),", $form, 'no e-mail for a record that owes a fee');
	pg_assert_true(strpos($form, 'pg_form_payment_start(') < strpos($form, 'if ($quiz == 1) {' . "\n" . '        // if quiz score'), 'charged before the confirmation');

	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains("pg_cf_insert_payment_step(\$tree_decoded, \$cf_payment['html'], lang('Payment'))", $widgets, 'the renderer draws the step');
	pg_assert_true(strpos($widgets, 'pg_cf_insert_payment_step(') < strpos($widgets, '$cf_steps = pg_cf_apply_steps('), 'before the steps are cut');

	$completion = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/order_completion.php');
	pg_assert_contains("if ((int) (\$values['paid_at'] ?? 0) > 0) {\n        pg_form_payment_mark_paid(\$order_id);", $completion, 'an offline completion leaves the record unpaid');
}
