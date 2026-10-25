<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a return request staff record on the panel (add_return.php and
 * its helpers in includes/fn/returns.php): the customer's timing rule a
 * delivery breaks, which the customer is refused on and staff are warned of;
 * the deliveries the form lists; the quantity boxes it reads; the reason
 * options; how a refusal is answered; the notice staff land with; the order
 * number they type; when the order screen offers the form; and what the
 * screen and the model wrapper do around pg_return_create().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function returns_staff_request_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The source of one top-level function, up to the next one.
function returns_staff_request_test_body($source, $name)
{
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

// A delivery as pg_return_delivery_state() gives it.
function returns_staff_request_test_state($id, $name, $goods_left, $window)
{
	return array(
		'ship_to'    => array('id' => $id, 'name' => $name, 'ship_date' => '', 'delivery_date' => '', 'method_code' => ''),
		'parcels'    => array(),
		'goods_left' => $goods_left,
		'window'     => $window,
	);
}

// An order line as pg_return_order_lines() gives it.
function returns_staff_request_test_line($id, $ship_to_id, $quantity, $held)
{
	return array(
		'id'               => $id,
		'ship_to_id'       => $ship_to_id,
		'product_id'       => 70,
		'name'             => 'Product ' . $id,
		'code'             => 'P' . $id,
		'quantity'         => $quantity,
		'price'            => 1000,
		'shipped_quantity' => 0,
		'held'             => $held,
		'returnable'       => pg_return_returnable($quantity, $held),
	);
}

// The rules in the order a customer's request is refused on them: goods that
// have not left first, then a delivery with no date, then a closed window.
function test_returns_staff_request_rule_breach()
{
	$open = pg_return_window(array('delivered' => '2026-10-01'), '2026-10-10');
	$closed = pg_return_window(array('delivered' => '2026-10-01'), '2026-10-20');

	pg_assert_same('', pg_return_rule_breach(returns_staff_request_test_state(1, 'A', true, $open)), 'sent, dated, in time');
	pg_assert_same('not_sent', pg_return_rule_breach(returns_staff_request_test_state(1, 'A', false, $open)), 'not sent');
	pg_assert_same('not_sent', pg_return_rule_breach(returns_staff_request_test_state(1, 'A', false, null)), 'not sent comes before no date');
	pg_assert_same('delivery_date_unknown', pg_return_rule_breach(returns_staff_request_test_state(1, 'A', true, null)), 'no date');
	pg_assert_same('window_closed', pg_return_rule_breach(returns_staff_request_test_state(1, 'A', true, $closed)), 'period over');
	pg_assert_same('', pg_return_rule_breach(null), 'no delivery');
}

// The check refuses a customer on the same helper, with its codes and
// sentences unchanged (the external API maps the codes).
function test_returns_staff_request_check_uses_the_breach_helper()
{
	$check = returns_staff_request_test_body(returns_staff_request_test_source('includes/fn/returns.php'), 'pg_return_create_check');
	$rules = strpos($check, 'if (pg_return_customer_rules(');
	$breach = strpos($check, 'pg_return_rule_breach($state)');

	pg_assert_true(($rules !== false) && ($breach !== false) && ($breach > $rules), 'asked inside the customer rules block');
	pg_assert_contains("return \$refuse(lang('This delivery has not been sent yet. The order can be cancelled instead.'), 'not_sent');", $check);
	pg_assert_contains("return \$refuse(lang('We cannot tell when this delivery arrived. Please contact us to return it.'), 'delivery_date_unknown');", $check);
	pg_assert_contains("return \$refuse(lang('The return period for this delivery has ended.'), 'window_closed');", $check);
	pg_assert_false(strpos($check, "\$state['goods_left']") !== false, 'no second reading of the rules');
}

// The deliveries the staff form lists: grouped, only those with something
// left, labelled, with the rule they break and today's timing.
function test_returns_staff_request_deliveries()
{
	$window = pg_return_window(array('delivered' => '2026-10-01'), '2026-10-15');
	$states = array(
		11 => returns_staff_request_test_state(11, 'Ayşe Bir', true, $window),
		12 => returns_staff_request_test_state(12, '', false, null),
		13 => returns_staff_request_test_state(13, 'Can Üç', true, $window),
	);
	$lines = array(
		1 => returns_staff_request_test_line(1, 11, 2, 0),
		2 => returns_staff_request_test_line(2, 11, 1, 1),
		3 => returns_staff_request_test_line(3, 12, 1, 0),
		4 => returns_staff_request_test_line(4, 13, 1, 1),
		5 => returns_staff_request_test_line(5, 14, 1, 0),
	);

	$deliveries = pg_return_staff_deliveries($lines, $states, '2026-10-15');

	pg_assert_same(array(11, 12), array_keys($deliveries), 'a delivery with nothing left, or not the order\'s, is not listed');
	pg_assert_same(array(1, 2), array_column($deliveries[11]['lines'], 'id'), 'every line of the delivery, in order');
	pg_assert_same(2, $deliveries[11]['left'], 'what is left on its lines');
	pg_assert_same('Sent to Ayşe Bir', $deliveries[11]['label']);
	pg_assert_same('Delivery 2', $deliveries[12]['label'], 'a delivery without a name: its number in the order');
	pg_assert_same(array(1, 2), array_column($deliveries, 'number'));
	pg_assert_same('', $deliveries[11]['breach']);
	pg_assert_same('not_sent', $deliveries[12]['breach']);
	pg_assert_true($deliveries[11]['timing']['in_time'], 'the deadline day is in time');
	pg_assert_same('2026-10-15', $deliveries[11]['timing']['deadline']);
	pg_assert_same(null, $deliveries[12]['timing'], 'no date, no timing');

	$late = pg_return_staff_deliveries($lines, $states, '2026-10-16');
	pg_assert_false($late[11]['timing']['in_time'], 'the day after is late');
	pg_assert_same(array(), pg_return_staff_deliveries(array(), $states, '2026-10-15'), 'no lines');
}

// Every delivery in the switch reads differently: the name it was saved
// under when the recipient has none, and its number when two deliveries go
// to the same name.
function test_returns_staff_request_delivery_labels()
{
	$window = pg_return_window(array('delivered' => '2026-10-01'), '2026-10-15');
	$office = returns_staff_request_test_state(23, '', true, $window);
	$office['ship_to']['ship_to_name'] = 'Ofis Depo';
	$states = array(
		21 => returns_staff_request_test_state(21, 'Ayşe Bir', true, $window),
		22 => returns_staff_request_test_state(22, 'Ayşe Bir', true, $window),
		23 => $office,
		24 => returns_staff_request_test_state(24, 'Can Üç', true, $window),
		25 => returns_staff_request_test_state(25, 'Can Üç', true, $window),
	);
	$lines = array(
		returns_staff_request_test_line(1, 22, 1, 0),
		returns_staff_request_test_line(2, 21, 1, 0),
		returns_staff_request_test_line(3, 23, 1, 0),
		returns_staff_request_test_line(4, 24, 1, 0),
		returns_staff_request_test_line(5, 25, 1, 1),
	);

	$labels = array_column(pg_return_staff_deliveries($lines, $states, '2026-10-15'), 'label', 'ship_to_id');

	pg_assert_same(array(
		21 => 'Sent to Ayşe Bir (delivery 1)',
		22 => 'Sent to Ayşe Bir (delivery 2)',
		23 => 'Sent to Ofis Depo',
		24 => 'Sent to Can Üç',
	), $labels, 'numbered in the order the deliveries were made; a name shared only with a delivery that has nothing left stays plain');
}

// The quantity boxes are flat names (qty_<line>), read for the delivery's own
// lines only; anything that is not one value counts as an empty box.
function test_returns_staff_request_quantities()
{
	$lines = array(
		7 => returns_staff_request_test_line(7, 11, 2, 0),
		9 => returns_staff_request_test_line(9, 11, 1, 0),
	);

	pg_assert_same(array(7 => '2', 9 => ''), pg_return_staff_quantities($lines, array('qty_7' => '2', 'qty_8' => '5')), 'another line is never read');
	pg_assert_same(array(7 => '', 9 => '1'), pg_return_staff_quantities($lines, array('qty_7' => array('3'), 'qty_9' => '1')), 'an array box is empty');
	pg_assert_same(array(7 => '0', 9 => ''), pg_return_staff_quantities($lines, array('qty_7' => 0)), 'a number is read as text');
	pg_assert_same(array(), pg_return_staff_quantities(array(), array('qty_7' => '1')), 'no lines');
}

// A liveform select takes label => value and prints both as they are: each
// label is escaped and the posted value is the reason's code.
function test_returns_staff_request_reason_options()
{
	$options = pg_return_reason_options('Choose <one>');
	$values = array_values($options);

	pg_assert_same('', reset($options), 'the empty choice comes first');
	pg_assert_same('Choose &lt;one&gt;', key($options), 'its label is escaped');
	pg_assert_same(array_merge(array(''), array_keys(pg_return_reasons())), $values, 'the codes are posted');
	pg_assert_same('other', $options[h(lang('Another reason'))]);
}

// A refusal is answered by its code: what the form can fix goes back to it
// with the field marked; a missing or unreturnable order, or tables that are
// not there, leave the form.
function test_returns_staff_request_refusal()
{
	pg_assert_same(array('to' => 'form', 'field' => 'reason', 'message' => 'Choose a reason for the return.'), pg_return_staff_refusal('reason_missing', 'Any sentence'));
	pg_assert_same('note', pg_return_staff_refusal('note_missing', 'Tell us why you are returning the goods.')['field']);
	pg_assert_false(strpos(pg_return_staff_refusal('note_missing', 'Tell us why you are returning the goods.')['message'], 'Tell us') !== false, 'staff are not addressed as the customer');
	pg_assert_same(array('to' => 'form', 'field' => '_error', 'message' => 'At most 1'), pg_return_staff_refusal('lines', 'At most 1'));
	pg_assert_same('form', pg_return_staff_refusal('busy', 'Busy')['to']);
	pg_assert_same('form', pg_return_staff_refusal('delivery_not_in_order', 'Not this order')['to']);
	pg_assert_same(array('to' => 'list', 'field' => '_error', 'message' => 'Gone'), pg_return_staff_refusal('order_missing', 'Gone'));
	pg_assert_same('order', pg_return_staff_refusal('order_not_returnable', 'Not complete')['to']);
	pg_assert_same('order', pg_return_staff_refusal('not_installed', 'Not installed')['to']);
	pg_assert_same('form', pg_return_staff_refusal('something_new', 'New')['to'], 'an unknown code goes back to the form');
}

// The notice staff land on the request with.
function test_returns_staff_request_notice()
{
	pg_assert_same('The return request was recorded.', pg_return_staff_notice(array('repeat' => false, 'mailed' => null)), 'no e-mail asked for');
	pg_assert_same('The return request was recorded. The customer was told by e-mail.', pg_return_staff_notice(array('repeat' => false, 'mailed' => true)));
	pg_assert_same('The return request was recorded, but the e-mail to the customer could not be sent.', pg_return_staff_notice(array('repeat' => false, 'mailed' => false)));
	pg_assert_same('This request was already recorded a moment ago. Nothing else was done, and no e-mail was sent.', pg_return_staff_notice(array('repeat' => true, 'mailed' => null)), 'the same form sent again');
}

// The order number as staff type it.
function test_returns_staff_request_order_number_input()
{
	pg_assert_same('1042', pg_return_order_number_input('1042'));
	pg_assert_same('1042', pg_return_order_number_input(' #1042 '), 'as the order screens print it');
	pg_assert_same('1042', pg_return_order_number_input('# 001042'), 'leading zeros');
	pg_assert_same('', pg_return_order_number_input('0'), 'a cart has no number');
	pg_assert_same('', pg_return_order_number_input('10a42'));
	pg_assert_same('', pg_return_order_number_input('1234567890123456789'), 'longer than an order number');
	pg_assert_same('', pg_return_order_number_input(array('1042')), 'not one value');
}

// The order screen offers the form for a completed order with something left
// to return on a line sent to an address.
function test_returns_staff_request_recordable()
{
	$left = array(returns_staff_request_test_line(1, 11, 2, 1));
	$none = array(returns_staff_request_test_line(1, 11, 2, 2));

	pg_assert_true(pg_return_recordable('complete', $left));
	pg_assert_true(pg_return_recordable('exported', $left));
	pg_assert_false(pg_return_recordable('incomplete', $left), 'a cart');
	pg_assert_false(pg_return_recordable('cancelled', $left));
	pg_assert_false(pg_return_recordable('complete', $none), 'everything is in a return');
	pg_assert_false(pg_return_recordable('complete', array()), 'nothing sent to an address');
}

// The wrapper records as staff from the panel and does what follows itself:
// audit line, log line, the e-mail only when asked, and no bell.
function test_returns_staff_request_record_follows()
{
	$body = returns_staff_request_test_body(returns_staff_request_test_source('includes/fn/returns.php'), 'pg_return_staff_record');
	$repeat = strpos($body, "if (\$created['repeat'])");
	$audit = strpos($body, "pg_audit('create', 'order_return', ");

	pg_assert_contains("array('source' => 'panel', 'user_id' => \$user_id)", $body, 'source panel and the person');
	pg_assert_true(($repeat !== false) && ($audit !== false) && ($repeat < $audit), 'a repeat returns before anything follows');
	pg_assert_contains('pg_return_audit_create_fields($row)', $body);
	pg_assert_contains("log_activity(lang(array('string' => 'Return request {var:1} was recorded for order {var:2} from the panel.'", $body);
	pg_assert_contains("!empty(\$options['notify']) ? pg_return_send_request_mail(\$id) : null", $body, 'the e-mail only when asked');
	pg_assert_false(strpos($body, 'pg_return_notify_staff(') !== false, 'no bell for the person who recorded it');
	pg_assert_false(strpos($body, 'pg_return_announce(') !== false, 'the event comes from pg_return_create()');
}

// The screen's gate, token and request reads, and that it records through
// the wrapper.
function test_returns_staff_request_screen_gate()
{
	$screen = returns_staff_request_test_source('add_return.php');
	$single = strpos($screen, 'pg_request_single_values(array(');

	pg_assert_contains("output_error(lang('The request was too large for this server and arrived empty.'), 413);", $screen);
	pg_assert_contains("\$user = validate_user();\nvalidate_ecommerce_access(\$user);", $screen);
	pg_assert_contains("if (!defined('ECOMMERCE') || (ECOMMERCE !== true)) {", $screen);
	pg_assert_contains("\$order_id = is_scalar(\$_REQUEST['order_id'] ?? null) ? (int) \$_REQUEST['order_id'] : 0;", $screen);
	pg_assert_contains('get_token_field()', $screen);
	pg_assert_true(strpos($screen, 'validate_token_field();') < strpos($screen, '$recorded = pg_return_staff_record('), 'the token is checked before the write');
	pg_assert_false(strpos($screen, 'pg_return_create(') !== false, 'never the model directly');
	pg_assert_contains("'options' => pg_return_reason_options(", $screen, 'the reason posts its code');
	pg_assert_false(preg_match("/'options'\s*=>\s*pg_return_reasons\(\)/", $screen) === 1, 'never the reasons as they are');
	pg_assert_contains("pg_return_staff_refusal(\$recorded['code'], \$recorded['error'])", $screen, 'a refusal is answered by its code');

	foreach (array('$_GET', '$_POST', '$_REQUEST') as $array) {
		$first = strpos($screen, $array . '[');
		pg_assert_true(($single !== false) && (($first === false) || ($first > $single)), $array . ' is read after pg_request_single_values()');
	}
}

// Nothing typed on the form stays in the session when the screen sends the
// person elsewhere: every redirect but the refusal back to the form, and the
// "not found" answer, come after remove_form().
function test_returns_staff_request_screen_leaves_no_fields()
{
	$screen = returns_staff_request_test_source('add_return.php');
	$offset = 0;
	$count = 0;

	while (($at = strpos($screen, 'go(', $offset)) !== false) {
		$offset = $at + 3;

		if (substr($screen, $at, 13) === 'go($back_url)') {
			continue;
		}

		$count++;
		pg_assert_contains('->remove_form();', substr($screen, max(0, $at - 400), 400), 'go() at offset ' . $at);
	}

	pg_assert_true($count >= 3, 'the redirects were found');

	$not_found = strpos($screen, "output_error(lang('Record not found.'), 404);");
	pg_assert_true($not_found !== false, 'an unknown order is not found');
	pg_assert_contains('->remove_form();', substr($screen, max(0, $not_found - 200), 200), 'before "not found"');
	pg_assert_same(2, substr_count($screen, "</main>' . output_footer();\n\n    \$liveform->remove_form();"), 'both drawn screens clear the form right after drawing');
	pg_assert_same(6, substr_count($screen, '$liveform->remove_form();'), 'four exits and two drawn screens');
}

// The order screen's Returns card offers the form.
function test_returns_staff_request_panel_card()
{
	$card = returns_staff_request_test_body(returns_staff_request_test_source('includes/fn/returns.php'), 'pg_returns_panel_card');

	pg_assert_contains("'add_return.php?order_id=' . \$order_id", $card);
	pg_assert_contains('pg_return_recordable(', $card);
}
