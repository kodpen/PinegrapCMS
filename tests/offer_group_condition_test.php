<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the offers' "The customer is in the contact group" condition
 * (includes/fn/ecommerce.php: pg_offer_contact_group_met(),
 * pg_offer_customer_conditions_met(); edit_offer_f.php: the editor's check
 * and the stored row). A member gets the offer, a non-member and a guest do
 * not; with "contains this many of these products" and a product discount it
 * makes a quantity break for one group; an offer without it is judged as
 * before. The checks run in a PHP process of their own on a fake database
 * layer, one signed-in user each.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $body in a PHP process of its own with the offers code on a fake
// database: user 5 holds contact 50 (in groups 3 and 9), user 6 contact 60
// (in group 4), user 7 no contact; contact groups 3 Wholesale, 4 Retail and
// 9 VIP; $conditions the offer_conditions rows by offer id. $user_id 0 is a
// guest. Answers the JSON $body prints, null when PHP cannot be started.
function offer_group_condition_run($user_id, $conditions, $body)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgoffgrp');

	if ($file === false) {
		return null;
	}

	$script = '<?php' . "\n"
		. 'define("PG_FUNCTIONS_DIR", ' . var_export(PG_FUNCTIONS_DIR, true) . ');' . "\n"
		. ($user_id ? 'define("USER_ID", ' . (int) $user_id . ');' . "\n" : '')
		. '$GLOBALS["conds"] = ' . var_export($conditions, true) . ';' . "\n"
		. <<<'PHP'
$GLOBALS['writes'] = array();
$GLOBALS['contacts'] = array(5 => 50, 6 => 60, 7 => 0);
$GLOBALS['xref'] = array(50 => array(3, 9), 60 => array(4));

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (!is_array($text)) {
		return $text;
	}
	$out = $text['string'];
	foreach (array_values((array) $text['vars']) as $i => $var) {
		$out = str_replace('{var:' . ($i + 1) . '}', (string) $var, $out);
	}
	return $out;
}

function pg_schema_has($table, $column = null) { return true; }

function db_value($sql)
{
	if (preg_match("/SELECT user_contact FROM user WHERE user_id = '(\d+)'/", $sql, $m)) {
		return isset($GLOBALS['contacts'][(int) $m[1]]) ? $GLOBALS['contacts'][(int) $m[1]] : 0;
	}
	if (preg_match("/SELECT COUNT\(\*\) FROM products WHERE id = '11'/", $sql)) {
		return 1;
	}
	return 0;
}

function db_values($sql)
{
	if (preg_match("/FROM contacts_contact_groups_xref\s+WHERE contact_id = '(\d+)'/", $sql, $m)) {
		return isset($GLOBALS['xref'][(int) $m[1]]) ? $GLOBALS['xref'][(int) $m[1]] : array();
	}
	return array();
}

function db_items($sql)
{
	if (preg_match("/FROM offer_conditions\s+WHERE offer_id = '(\d+)'/", $sql, $m)) {
		return isset($GLOBALS['conds'][(int) $m[1]]) ? $GLOBALS['conds'][(int) $m[1]] : array();
	}
	if (strpos($sql, 'FROM contact_groups') !== false) {
		return array(array('id' => 3, 'name' => 'Wholesale'), array('id' => 4, 'name' => 'Retail'), array('id' => 9, 'name' => 'VIP'));
	}
	return array();
}

function db_item($sql) { return null; }

function db($sql)
{
	$GLOBALS['writes'][] = preg_replace('/\s+/', ' ', trim($sql));
	return true;
}

require PG_FUNCTIONS_DIR . '/includes/fn/ecommerce.php';
require PG_FUNCTIONS_DIR . '/edit_offer_f.php';

PHP
		. $body;

	file_put_contents($file, $script);

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : array('error' => implode("\n", $out));
}

// The rows of offer 1: for the contact group Wholesale (3).
function offer_group_condition_rows()
{
	return array(1 => array(array('type' => 'contact group', 'int_value' => 3, 'text_value' => '')));
}

// A list of the user's contact groups holds the group, a list without it or
// a guest (null) does not; a condition with no group matches nobody.
function test_offer_contact_group_met_is_pure()
{
	pg_assert_true(pg_offer_contact_group_met(3, array(3, 9)), 'a member');
	pg_assert_true(pg_offer_contact_group_met('3', array('9', '3')), 'ids as strings');
	pg_assert_false(pg_offer_contact_group_met(3, array(4)), 'a non-member');
	pg_assert_false(pg_offer_contact_group_met(3, array()), 'a contact in no group');
	pg_assert_false(pg_offer_contact_group_met(3, null), 'a guest');
	pg_assert_false(pg_offer_contact_group_met(0, array(0, 3)), 'no group named');
}

// The signed-in member gets the offer; a non-member, a user with no contact
// and a guest do not. The guest view (what is kept for everybody) never
// gets it, even for the member.
function test_offer_contact_group_condition_by_customer()
{
	$body = 'echo json_encode(array("met" => pg_offer_customer_conditions_met(1), "guestView" => (pg_offer_guest_view(true) || true) && pg_offer_customer_conditions_met(1)));';
	$member = offer_group_condition_run(5, offer_group_condition_rows(), $body);

	if ($member === null) {
		return;
	}

	pg_assert_false(isset($member['error']), isset($member['error']) ? $member['error'] : 'ran');
	pg_assert_true($member['met'], 'the member gets the offer');
	pg_assert_false($member['guestView'], 'the guest view never does');

	foreach (array(6 => 'a customer outside the group', 7 => 'a user with no contact', 0 => 'a guest') as $user_id => $label) {
		$r = offer_group_condition_run($user_id, offer_group_condition_rows(), $body);
		pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
		pg_assert_false($r['met'], $label . ' does not get the offer');
	}
}

// An offer without the condition is judged as before: no rows, every
// customer; a new-customer row beside it still decides alone.
function test_offer_without_group_condition_as_before()
{
	$body = 'echo json_encode(array("none" => pg_offer_customer_conditions_met(2), "newOnly" => pg_offer_customer_conditions_met(3)));';
	$rows = array(3 => array(array('type' => 'new customer', 'int_value' => 30, 'text_value' => 'no_orders')));

	foreach (array(5, 6, 0) as $user_id) {
		$r = offer_group_condition_run($user_id, $rows, $body);

		if ($r === null) {
			return;
		}

		pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
		pg_assert_true($r['none'], 'an offer with no condition holds for user ' . $user_id);
		pg_assert_true($r['newOnly'], 'a customer with no order is new, user ' . $user_id);
	}
}

// A quantity break for one group needs nothing new: "contains 10 of these
// products" stays on the rule row checkout already reads, the group is one
// offer_conditions row beside it, and the product discount is the action.
// Without the group the same offer is the break for everybody.
function test_offer_group_quantity_break()
{
	$body = <<<'PHP'
$payload = array(
	'offer' => array('code' => 'WHOLESALE10', 'enabled' => 1),
	'conditions' => array(
		array('type' => 'contact group', 'group_id' => 3),
		array('type' => 'products', 'product_ids' => array(11), 'quantity' => 10)),
	'actions' => array(array('type' => 'discount product', 'unit' => 'percent', 'value' => 15, 'target' => 'product', 'product_id' => 11)));
$checked = _pg_offer_validate($payload);
$conditions = $checked['clean']['conditions'];
_pg_offer_conditions_write(1, $conditions, $checked['clean']['actions']);
$everybody = $payload;
array_shift($everybody['conditions']);
$open = _pg_offer_validate($everybody);
$missing = $payload;
$missing['conditions'][0]['group_id'] = 77;
echo json_encode(array(
	'errors' => $checked['errors'],
	'rule' => _pg_offer_rule_conditions($conditions),
	'cart' => _pg_offer_cart_conditions($conditions),
	'writes' => $GLOBALS['writes'],
	'text' => _pg_offer_conditions_text($conditions, array(array('id' => 11, 'label' => 'Box'))),
	'openErrors' => $open['errors'],
	'openRule' => _pg_offer_rule_conditions($open['clean']['conditions']),
	'missing' => _pg_offer_validate($missing)['errors'],
	'member' => pg_offer_customer_conditions_met(1)));
PHP;
	$rows = offer_group_condition_rows();
	$r = offer_group_condition_run(5, $rows, $body);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	pg_assert_same(array(), $r['errors'], 'the editor takes the group with a quantity rule and a product discount');
	pg_assert_same(array(array('type' => 'products', 'product_ids' => array(11), 'quantity' => 10)), $r['rule'], 'the break stays on the rule row');
	pg_assert_same(array(array('type' => 'products', 'product_ids' => array(11), 'quantity' => 10)), $r['cart'], 'the up-sell counts the products, not the group');
	pg_assert_same(array("DELETE FROM offer_conditions WHERE offer_id = '1'",
		"INSERT INTO offer_conditions (offer_id, type, int_value, text_value) VALUES ( '1', 'contact group', '3', '')"), $r['writes'], 'the group is one row: type, group id');
	pg_assert_contains('the customer is in Wholesale', $r['text'], 'the sentence names the group');
	pg_assert_same(array(), $r['openErrors'], 'without the group the break is for everybody');
	pg_assert_same(1, count($r['openRule']), 'on the same rule row');
	pg_assert_same(array('conditions.0.group_id' => 'Please select a contact group.'), $r['missing'], 'a group that does not exist is refused');
	pg_assert_true($r['member'], 'and the member meets the stored row');

	$r = offer_group_condition_run(6, $rows, 'echo json_encode(array("met" => pg_offer_customer_conditions_met(1)));');
	pg_assert_false($r['met'], 'a customer outside the group does not get the break');
}
