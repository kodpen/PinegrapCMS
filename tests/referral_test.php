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

// A post of the settings card that passes; each test spoils one box.
function referral_test_post($changes = array())
{
	return array_merge(array(
		'referral_on'          => '1',
		'referral_offer_id'    => '12',
		'referral_reward'      => '50',
		'referral_reward_days' => '90',
		'referral_smallest'    => '200.50',
		'referral_month_cap'   => '5',
	), $changes);
}

function referral_test_offer_exists($offer_id)
{
	return $offer_id === 12;
}

function test_referral_settings_save_writes_what_the_rules_read()
{
	$answer = pg_referral_settings_check(referral_test_post(), 'referral_test_offer_exists');

	pg_assert_same(array(), $answer['errors']);
	pg_assert_same(array(
		'referral_on'          => '1',
		'referral_offer_id'    => '12',
		'referral_reward'      => '5000',
		'referral_reward_days' => '90',
		'referral_smallest'    => '20050',
		'referral_month_cap'   => '5',
	), $answer['values'], 'amounts in minor units, the names pg_referral_settings() reads');

	$empty = pg_referral_settings_check(referral_test_post(array('referral_offer_id' => '', 'referral_reward_days' => '', 'referral_smallest' => '', 'referral_month_cap' => '0')), 'referral_test_offer_exists');

	pg_assert_same(array(), $empty['errors'], 'empty boxes are none, never expires and no limit');
	pg_assert_same('', $empty['values']['referral_offer_id']);
	pg_assert_same('', $empty['values']['referral_reward_days']);
	pg_assert_same('', $empty['values']['referral_smallest']);
	pg_assert_same('', $empty['values']['referral_month_cap']);

	pg_assert_same('1250', pg_referral_settings_check(referral_test_post(array('referral_reward' => '12,50')), 'referral_test_offer_exists')['values']['referral_reward'], 'a decimal comma reads too');
	pg_assert_same('1999', pg_referral_settings_check(referral_test_post(array('referral_reward' => '19.99')), 'referral_test_offer_exists')['values']['referral_reward'], 'rounded, never cut');
}

function test_referral_settings_off_keeps_the_values()
{
	$answer = pg_referral_settings_check(referral_test_post(array('referral_on' => null, 'referral_reward' => '')), 'referral_test_offer_exists');

	pg_assert_same(array(), $answer['errors'], 'off needs no reward');
	pg_assert_same('', $answer['values']['referral_on'], 'off is stored as empty');
	pg_assert_same('90', $answer['values']['referral_reward_days'], 'the other values are kept for the next time');

	$kept = pg_referral_settings_check(referral_test_post(array('referral_on' => '0')), 'referral_test_offer_exists');

	pg_assert_same('', $kept['values']['referral_on']);
	pg_assert_same('5000', $kept['values']['referral_reward']);
}

function test_referral_settings_save_refusals()
{
	$refused = function ($changes) {
		return pg_referral_settings_check(referral_test_post($changes), 'referral_test_offer_exists');
	};

	pg_assert_same(array('referral_reward' => 'reward_needed'), $refused(array('referral_reward' => ''))['errors'], 'on without a reward');
	pg_assert_same(array('referral_reward' => 'reward_needed'), $refused(array('referral_reward' => '0'))['errors'], 'on with a reward of 0');
	pg_assert_same(array('referral_reward' => 'reward'), $refused(array('referral_reward' => '-5'))['errors'], 'a negative reward');
	pg_assert_same(array('referral_reward' => 'reward'), $refused(array('referral_reward' => 'ten'))['errors'], 'letters');
	pg_assert_same(array('referral_reward' => 'reward'), $refused(array('referral_reward' => '42949673'))['errors'], 'above the column');
	pg_assert_same(array(), $refused(array('referral_reward' => '42949672.95'))['errors'], 'the column\'s top still fits');
	pg_assert_same(array('referral_reward' => 'reward'), $refused(array('referral_reward' => array('5')))['errors'], 'not a box value');
	pg_assert_same(array('referral_reward_days' => 'days'), $refused(array('referral_reward_days' => '3651'))['errors'], 'more than ten years');
	pg_assert_same(array(), $refused(array('referral_reward_days' => '3650'))['errors'], 'ten years');
	pg_assert_same(array('referral_reward_days' => 'days'), $refused(array('referral_reward_days' => '1.5'))['errors'], 'part of a day');
	pg_assert_same(array('referral_reward_days' => 'days'), $refused(array('referral_reward_days' => '-1'))['errors'], 'a negative day');
	pg_assert_same(array('referral_smallest' => 'smallest'), $refused(array('referral_smallest' => '-1'))['errors'], 'a negative smallest order');
	pg_assert_same(array('referral_month_cap' => 'month_cap'), $refused(array('referral_month_cap' => '1001'))['errors'], 'above the month\'s limit');
	pg_assert_same(array(), $refused(array('referral_month_cap' => '1000'))['errors'], 'the month\'s limit');
	pg_assert_same(array('referral_month_cap' => 'month_cap'), $refused(array('referral_month_cap' => 'x'))['errors'], 'letters for the month');
	pg_assert_same(array('referral_offer_id' => 'offer'), $refused(array('referral_offer_id' => '13'))['errors'], 'an offer that does not exist');
	pg_assert_same(array('referral_offer_id' => 'offer'), $refused(array('referral_offer_id' => '12 OR 1'))['errors'], 'not an id');

	$two = $refused(array('referral_reward' => '-1', 'referral_month_cap' => '5000'));

	pg_assert_same(array('referral_reward' => 'reward', 'referral_month_cap' => 'month_cap'), $two['errors'], 'every refusal at once');
	pg_assert_same(array(), $two['values'], 'a refused save writes nothing');

	foreach (array('reward', 'reward_needed', 'days', 'smallest', 'month_cap', 'offer') as $code) {
		pg_assert_true(isset(pg_referral_settings_errors()[$code]), 'a sentence for ' . $code);
	}
}

function test_referral_mail_placeholders()
{
	$body = pg_referral_mail_body(array(
		'customer'    => 'Ayşe <Kaya>',
		'amount'      => '₺50,00',
		'balance'     => '₺75,00',
		'last_day'    => '18 February 2027',
		'account_url' => 'https://shop.example.com/my-account',
		'from_name'   => 'Shop & Co',
	));

	pg_assert_contains('Ayşe &lt;Kaya&gt;', $body, 'the name, escaped');
	pg_assert_contains('₺50,00 was added to your account as store credit', $body, 'what they earned');
	pg_assert_contains('Your store credit balance is now ₺75,00.', $body, 'their balance');
	pg_assert_contains('until 18 February 2027', $body, 'the last day');
	pg_assert_contains('href="https://shop.example.com/my-account"', $body, 'the account link');
	pg_assert_contains('Shop &amp; Co', $body, 'the sender, escaped');
	pg_assert_true(strpos($body, '{var:') === false, 'no placeholder left');

	$plain = pg_referral_mail_body(array('amount' => '₺50,00', 'balance' => '₺50,00', 'last_day' => '', 'account_url' => 'javascript:alert(1)'));

	pg_assert_true(strpos($plain, 'until') === false, 'a credit that never expires names no last day');
	pg_assert_true(strpos($plain, 'href=') === false, 'no link but an http address');
	pg_assert_contains('Hello,', $plain, 'no name: a plain greeting');
}

function test_referral_mail_wired_after_the_lock()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/referral.php');
	$release = strpos($source, "SELECT RELEASE_LOCK('\" . e(\$lock)");
	$mail = strpos($source, 'pg_referral_mail_reward($contact_id');

	pg_assert_true(($release !== false) && ($mail !== false) && ($mail > $release), 'the mail is sent once the referrer\'s lock is let go');
	pg_assert_contains("'queue'              => true", $source, 'through the mail queue');
}

// A referral link written with zeros before the contact's number passed its
// signature (the number reads the same), but the month's room counts the
// rows by the exact code, so each such spelling of one customer's code had
// a month of its own.
function test_referral_code_refuses_another_spelling_of_the_number()
{
	$code = pg_referral_code_make(41, referral_test_key());
	$padded = 'R0' . substr($code, 1);
	$padded_more = 'R00' . substr($code, 1);

	pg_assert_true(pg_referral_code_shaped($padded), 'the padded spelling has the referral shape');
	pg_assert_same(0, pg_referral_code_contact($padded, referral_test_key()), 'a zero before the number is not the customer\'s code');
	pg_assert_same(0, pg_referral_code_contact($padded_more, referral_test_key()), 'nor two zeros');
	pg_assert_same(41, pg_referral_code_contact(' ' . strtolower($code) . ' ', referral_test_key()), 'the code itself still reads, in any case');
}
