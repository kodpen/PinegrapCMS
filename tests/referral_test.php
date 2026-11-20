<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the referral programme's rules (includes/fn/referral.php): the
 * signed code, the checks a friend's order passes before the reward, the
 * month's room, the order's value after refunds, the take-back on a cancel
 * and on a refund, the card marker and where each rule is wired.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function referral_test_key()
{
	return 'test-key-0123456789abcdef';
}

// Facts of an order that passes every check; each test spoils one.
function referral_test_facts($changes = array())
{
	return array_merge(array(
		'on'          => true,
		'contact_id'  => 41,
		'affiliate'   => false,
		'referrer'    => array('user_id' => 7, 'contact_id' => 41, 'email' => 'Ayse.Kaya@example.com', 'affiliate_approved' => 0, 'orders' => 2),
		'buyer'       => array('user_id' => 9, 'contact_id' => 52, 'email' => 'friend@example.com'),
		'friend_orders' => 0,
		'value'       => 10000,
		'smallest'    => 5000,
		'month_count' => 1,
		'month_cap'   => 3,
	), $changes);
}

function test_referral_code_round_trip()
{
	$code = pg_referral_code_make(41, referral_test_key());

	pg_assert_true(pg_referral_code_shaped($code), 'a made code has the referral shape');
	pg_assert_same(41, pg_referral_code_contact($code, referral_test_key()), 'the code carries the contact');
	pg_assert_same(41, pg_referral_code_contact(strtolower($code), referral_test_key()), 'a lower-cased link still reads');
	pg_assert_same('', pg_referral_code_make(41, ''), 'no site key: no code');
	pg_assert_same('', pg_referral_code_make(0, referral_test_key()), 'no contact: no code');
	pg_assert_true(strlen($code) <= 19, 'short enough for a link');
}

function test_referral_code_refuses_a_forgery()
{
	$code = pg_referral_code_make(41, referral_test_key());
	$other = pg_referral_code_make(42, referral_test_key());

	pg_assert_same(0, pg_referral_code_contact($code, 'another-site-key'), 'another site\'s key');
	pg_assert_same(0, pg_referral_code_contact(substr($other, 0, strpos($other, '-')) . substr($code, strpos($code, '-')), referral_test_key()), 'one customer\'s signature on another\'s number');
	pg_assert_same(0, pg_referral_code_contact('ABCDE', referral_test_key()), 'an affiliate code');
	pg_assert_same(0, pg_referral_code_contact('', referral_test_key()), 'nothing');
	pg_assert_same(0, pg_referral_code_contact($code, ''), 'no site key');
}

function test_referral_kind_tells_the_two_programmes_apart()
{
	$code = pg_referral_code_make(41, referral_test_key());

	pg_assert_same('referral', pg_referral_commission_kind($code));
	pg_assert_same('affiliate', pg_referral_commission_kind('K7Q2M'));
	pg_assert_same('affiliate', pg_referral_commission_kind('Ayse'));
	pg_assert_same('affiliate', pg_referral_commission_kind(''));
	pg_assert_contains("REGEXP '" . PG_REFERRAL_CODE_PATTERN . "'", pg_referral_row_sql('commissions.affiliate_code', true), 'the SQL twin uses the same pattern');
	pg_assert_contains('NOT REGEXP', pg_referral_row_sql('commissions.affiliate_code', false), 'and its opposite');
}

function test_referral_check_passes_a_good_order()
{
	pg_assert_same('', pg_referral_check(referral_test_facts()));
}

function test_referral_check_refusals()
{
	pg_assert_same('off', pg_referral_check(referral_test_facts(array('on' => false))), 'the card off: nothing');
	pg_assert_same('code', pg_referral_check(referral_test_facts(array('contact_id' => 0))), 'not a signed referral code');
	pg_assert_same('affiliate', pg_referral_check(referral_test_facts(array('affiliate' => true))), 'an approved affiliate\'s code wins');
	pg_assert_same('referrer', pg_referral_check(referral_test_facts(array('referrer' => null))), 'the contact has no account');
	pg_assert_same('referrer', pg_referral_check(referral_test_facts(array('referrer' => array('user_id' => 7, 'contact_id' => 41, 'email' => 'a@example.com', 'affiliate_approved' => 1, 'orders' => 2)))), 'the referrer is an approved affiliate');
	pg_assert_same('referrer', pg_referral_check(referral_test_facts(array('referrer' => array('user_id' => 7, 'contact_id' => 41, 'email' => 'a@example.com', 'affiliate_approved' => 0, 'orders' => 0)))), 'the referrer has no order that counts');
	pg_assert_same('self', pg_referral_check(referral_test_facts(array('buyer' => array('user_id' => 7, 'contact_id' => 52, 'email' => 'x@example.com')))), 'the same account');
	pg_assert_same('self', pg_referral_check(referral_test_facts(array('buyer' => array('user_id' => 0, 'contact_id' => 41, 'email' => 'x@example.com')))), 'the same contact');
	pg_assert_same('self', pg_referral_check(referral_test_facts(array('buyer' => array('user_id' => 0, 'contact_id' => 0, 'email' => ' ayse.kaya+friend@EXAMPLE.com ')))), 'the same e-mail, folded');
	pg_assert_same('friend', pg_referral_check(referral_test_facts(array('friend_orders' => 1))), 'the friend ordered before');
	pg_assert_same('smallest', pg_referral_check(referral_test_facts(array('value' => 4999))), 'under the smallest order');
	pg_assert_same('', pg_referral_check(referral_test_facts(array('value' => 5000))), 'the smallest order itself counts');
	pg_assert_same('smallest', pg_referral_check(referral_test_facts(array('value' => 0, 'smallest' => 0))), 'an order of nothing never counts');
	pg_assert_same('month', pg_referral_check(referral_test_facts(array('month_count' => 3))), 'the month is full');
	pg_assert_same('', pg_referral_check(referral_test_facts(array('month_count' => 30, 'month_cap' => 0))), 'no cap set: no limit');
}

function test_referral_fold_email()
{
	pg_assert_same('ayse.kaya@example.com', pg_referral_fold_email(' Ayse.Kaya+shop@Example.COM '));
	pg_assert_same('aysekaya@gmail.com', pg_referral_fold_email('Ayse.Kaya+x@googlemail.com'), 'gmail ignores dots and the plus part');
	pg_assert_same('', pg_referral_fold_email('not an address'));
}

function test_referral_month_start()
{
	$now = mktime(15, 30, 0, 11, 20, 2026);

	pg_assert_same(mktime(0, 0, 0, 11, 1, 2026), pg_referral_month_start($now));
	pg_assert_same(mktime(0, 0, 0, 12, 1, 2026), pg_referral_month_start(mktime(0, 0, 0, 12, 1, 2026)), 'the first moment is in its own month');
}

function test_referral_order_value_less_refunds()
{
	$order = array('total' => 9000, 'gift_card_discount' => 1500, 'surcharge' => 500);

	pg_assert_same(10000, pg_referral_order_value($order, 0), 'what the goods were worth, however paid, without the card surcharge');
	pg_assert_same(6000, pg_referral_order_value($order, 4000), 'less what was refunded');
	pg_assert_same(0, pg_referral_order_value($order, 20000), 'never below zero');
}

function test_referral_take_back_after_a_refund()
{
	pg_assert_same(false, pg_referral_refund_takes_back(6000, 5000), 'one item sent back: the reward stays');
	pg_assert_same(true, pg_referral_refund_takes_back(4999, 5000), 'under the smallest order: taken back');
	pg_assert_same(true, pg_referral_refund_takes_back(0, 0), 'an order emptied by refunds');
	pg_assert_same(false, pg_referral_refund_takes_back(100, 0), 'no smallest order and something left');
}

function test_referral_card_marker()
{
	pg_assert_same('Referral reward of order 305', pg_referral_card_marker(305));
	pg_assert_true(pg_referral_card_marked("Referral reward of order 305\nStore credit voided on 2026-11-20: x", 305), 'the first line names the order');
	pg_assert_true(!pg_referral_card_marked("Referral reward of order 3050", 305), 'another order');
	pg_assert_true(!pg_referral_card_marked("x\nReferral reward of order 305", 305), 'only the first line');
}

function test_referral_take_back_plan_notes_what_was_spent()
{
	$cards = array(array('id' => 3, 'balance' => 400), array('id' => 8, 'balance' => 300));

	pg_assert_same(array('cuts' => array(8 => 300, 3 => 400), 'spent' => 300), pg_referral_take_back_plan($cards, 1000), 'newest card first, the rest was spent');
	pg_assert_same(array('cuts' => array(8 => 300, 3 => 200), 'spent' => 0), pg_referral_take_back_plan($cards, 500), 'all of it still there');
	pg_assert_same(array('cuts' => array(), 'spent' => 1000), pg_referral_take_back_plan(array(), 1000), 'all spent');
}

function test_referral_kind_is_store_credit()
{
	pg_assert_true(array_key_exists('referral', pg_credit_kinds()), 'a store credit kind');
	pg_assert_same('', pg_credit_issue_check(7, 1000, 'referral', 0, time()), 'pg_credit_issue() takes it');
}

function test_referral_migration_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_contains('upgrade_2026_11_0_referral_credit();', $source, 'the step is called');
	pg_assert_contains("install_column_info('gift_cards', 'kind')", $source, 'the values are read from the column');
	pg_assert_contains("\$values[] = \"'referral'\";", $source, 'referral appended at the end');
}

function test_referral_is_wired_to_the_order_path()
{
	$root = PG_FUNCTIONS_DIR . '/';
	$completion = (string) file_get_contents($root . 'includes/fn/order_completion.php');
	$ecommerce = (string) file_get_contents($root . 'includes/fn/ecommerce.php');
	$returns = (string) file_get_contents($root . 'includes/fn/returns.php');
	$view_order = (string) file_get_contents($root . 'view_order.php');
	$commissions = (string) file_get_contents($root . 'includes/fn/commission_list.php');
	$referral = (string) file_get_contents($root . 'includes/fn/referral.php');

	pg_assert_contains('pg_referral_order_completed($order_id, $values[\'affiliate_code\']);', $completion, 'the reward beside the commission');
	pg_assert_true(strpos($completion, 'if ($context[\'affiliate_rate\'] === null)') !== false, 'only when no affiliate commission was earned');
	pg_assert_contains('pg_referral_order_cancelled($order_id);', $ecommerce, 'a cancel takes it back');
	pg_assert_contains('pg_referral_order_restored($order_id);', $ecommerce, 'a restore gives it again');
	pg_assert_contains('pg_referral_order_refunded($return[\'order_id\']);', $returns, 'a return refund');
	pg_assert_contains('pg_referral_order_refunded($order_id);', $view_order, 'the order screen\'s refund');
	pg_assert_contains("pg_referral_commission_kind((string) \$row['affiliate_code']) === 'referral'", $commissions, 'a referral\'s row is never set by hand');
	pg_assert_contains('GET_LOCK', $referral, 'the month\'s room is taken under a named lock');
	pg_assert_contains("status = 'paid'", $referral, 'the take-back moves only a paid row: never twice');
}
