<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for store credit on the customer's screen
 * (includes/fn/store_credit_staff.php): the issue form's checks, where a
 * card stands and who may change credit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_store_credit_staff_issue_reads_the_amount_in_minor_units()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	$in = pg_credit_staff_issue_input('25', '', 'Late delivery', $now);
	pg_assert_same('', $in['error'], 'whole amount');
	pg_assert_same(2500, $in['amount'], 'minor units');
	pg_assert_same(0, $in['expires'], 'no last day never expires');
	pg_assert_same('Late delivery', $in['reason'], 'reason');

	pg_assert_same(1250, pg_credit_staff_issue_input('12,50', '', 'x', $now)['amount'], 'decimal comma');
	pg_assert_same(1, pg_credit_staff_issue_input('0.005', '', 'x', $now)['amount'], 'half a minor unit rounds up');
	pg_assert_same('amount', pg_credit_staff_issue_input('0', '', 'x', $now)['error'], 'zero');
	pg_assert_same('amount', pg_credit_staff_issue_input('-5', '', 'x', $now)['error'], 'negative');
	pg_assert_same('amount', pg_credit_staff_issue_input('abc', '', 'x', $now)['error'], 'not a number');
	pg_assert_same('amount', pg_credit_staff_issue_input(array('5'), '', 'x', $now)['error'], 'an array');
	pg_assert_same('amount', pg_credit_staff_issue_input('2000000', '', 'x', $now)['error'], 'above the most at once');
}

function test_store_credit_staff_issue_last_day_runs_to_its_end()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	$in = pg_credit_staff_issue_input('5', '2026-12-31', 'x', $now);
	pg_assert_same('', $in['error'], 'a future day');
	pg_assert_same(mktime(0, 0, 0, 1, 1, 2027), $in['expires'], 'stops at the start of the next day');
	pg_assert_same('2026-12-31', pg_credit_expiration_date($in['expires']), 'the gift card date is that day');

	pg_assert_same('', pg_credit_staff_issue_input('5', '2026-11-04', 'x', $now)['error'], 'today is still valid');
	pg_assert_same('date', pg_credit_staff_issue_input('5', '2026-11-03', 'x', $now)['error'], 'yesterday');
	pg_assert_same('date', pg_credit_staff_issue_input('5', '2026-02-30', 'x', $now)['error'], 'no such day');
	pg_assert_same('date', pg_credit_staff_issue_input('5', '31.12.2026', 'x', $now)['error'], 'another format');
}

function test_store_credit_staff_issue_needs_a_reason()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	pg_assert_same('reason', pg_credit_staff_issue_input('5', '', '   ', $now)['error'], 'blank');
	pg_assert_same('reason', pg_credit_staff_issue_input('5', '', array('x'), $now)['error'], 'an array');
	pg_assert_same(255, mb_strlen(pg_credit_staff_issue_input('5', '', str_repeat('é', 300), $now)['reason']), 'cut to the column');
}

function test_store_credit_staff_card_state()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);

	pg_assert_same('open', pg_credit_staff_card_state(array('balance' => 500, 'expires_at' => 0, 'expiration_date' => '0000-00-00'), $now), 'never expiring');
	pg_assert_same('used', pg_credit_staff_card_state(array('balance' => 0, 'expires_at' => 0, 'expiration_date' => '0000-00-00'), $now), 'spent or voided');
	pg_assert_same('expired', pg_credit_staff_card_state(array('balance' => 500, 'expires_at' => $now - 1, 'expiration_date' => '2026-11-03'), $now), 'past its end');
	pg_assert_same('expired', pg_credit_staff_card_state(array('balance' => 0, 'expires_at' => $now - 1, 'expiration_date' => '2026-11-03'), $now), 'closed by the expiry pass');
	pg_assert_same('voided', pg_credit_staff_card_state(array('balance' => 0, 'expires_at' => 0, 'expiration_date' => '0000-00-00', 'void_reason' => 'Duplicate'), $now), 'closed by staff');
	pg_assert_same('voided', pg_credit_staff_card_state(array('balance' => 0, 'expires_at' => $now - 1, 'expiration_date' => '2026-11-03', 'void_reason' => ''), $now), 'voided, then its last day went by');
	pg_assert_same('used', pg_credit_staff_card_state(array('balance' => 0, 'expires_at' => 0, 'expiration_date' => '0000-00-00', 'void_reason' => null), $now), 'spent: no void line');
	pg_assert_same('open', pg_credit_staff_card_state(array('balance' => 500, 'expires_at' => 0, 'expiration_date' => '0000-00-00', 'void_reason' => 'x'), $now), 'a void line on a card with money left is not a void');
}

function test_store_credit_staff_void_reason_comes_from_the_notes()
{
	$lines = array('Store credit voided on {var:1}: {var:2}', 'Mağaza kredisi {var:1} tarihinde iptal edildi: {var:2}');

	pg_assert_same(null, pg_credit_staff_void_reason('', $lines), 'no notes');
	pg_assert_same(null, pg_credit_staff_void_reason("Points for order 1042\nSpent on order 1043", $lines), 'notes without a void line');
	pg_assert_same('Duplicate card', pg_credit_staff_void_reason('Store credit voided on 2026-11-12: Duplicate card', $lines), 'the English line');
	pg_assert_same('Yanlış müşteri', pg_credit_staff_void_reason("Kampanya\r\nMağaza kredisi 2026-11-12 tarihinde iptal edildi: Yanlış müşteri", $lines), 'the Turkish line, after another note');
	pg_assert_same('Note: see ticket 7', pg_credit_staff_void_reason('Store credit voided on 2026-11-12: Note: see ticket 7', $lines), 'a colon in the reason');
	pg_assert_same('', pg_credit_staff_void_reason('Store credit voided on 2026-11-12: ', $lines), 'an empty reason is still a void');
	pg_assert_same('second', pg_credit_staff_void_reason("Store credit voided on 2026-11-10: first\nStore credit voided on 2026-11-12: second", $lines), 'the last line wins');
	pg_assert_same(null, pg_credit_staff_void_reason('Store credit voided on yesterday: x', $lines), 'the date is the one pg_credit_void() writes');
	pg_assert_same(null, pg_credit_staff_void_reason('Store credit voided on 2026-11-12: x', array('Voided')), 'a form without its placeholders matches nothing');
}

function test_store_credit_staff_issue_error_names_its_field()
{
	pg_assert_same('credit_amount', pg_credit_staff_issue_field('amount'));
	pg_assert_same('credit_last_day', pg_credit_staff_issue_field('date'));
	pg_assert_same('credit_reason', pg_credit_staff_issue_field('reason'));
	pg_assert_same('', pg_credit_staff_issue_field(''), 'no error');
	pg_assert_same('', pg_credit_staff_issue_field('other'));
}

function test_store_credit_staff_rights_are_the_commerce_ones()
{
	pg_assert_true(pg_credit_staff_allowed(array('role' => 2)), 'manager');
	pg_assert_true(pg_credit_staff_allowed(array('role' => 3, 'manage_ecommerce' => 1)), 'user with commerce rights');
	pg_assert_false(pg_credit_staff_allowed(array('role' => 3, 'manage_ecommerce' => 0)), 'user without');
	pg_assert_false(pg_credit_staff_allowed(array()), 'nobody');
}

/**
 * The gift card screen names whose credit a bound card is: it used to call
 * it "created manually" with no customer, kind, reason or expiry.
 */
function test_store_credit_staff_gift_card_info()
{
	$expires = mktime(0, 0, 0, 12, 1, 2026);
	$html = pg_credit_staff_gift_card_info_html(array('kind' => 'refund', 'reason' => 'Late <b>delivery</b>', 'expires_at' => $expires), '<a href="edit_user.php?id=7">ayse</a>');

	pg_assert_contains(lang('Store Credit'), $html, 'the heading');
	pg_assert_contains('<a href="edit_user.php?id=7">ayse</a>', $html, 'the customer as given');
	pg_assert_contains(h(lang('Refund')), $html, 'the kind');
	pg_assert_contains('Late &lt;b&gt;delivery&lt;/b&gt;', $html, 'the reason, escaped');
	pg_assert_contains('2026-11-30', $html, 'the last day is the day before the end');

	$open = pg_credit_staff_gift_card_info_html(array('kind' => 'manual', 'reason' => '', 'expires_at' => 0), '#9');
	pg_assert_contains(h(lang('Never')), $open, 'no end');
	pg_assert_contains(h(lang('Issued by staff')), $open, 'the staff kind');
	pg_assert_false(strpos($open, h(lang('Reason')) . ':') !== false, 'no empty reason row');

	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_gift_card.php');
	pg_assert_contains('pg_credit_staff_gift_card_info_html(', $edit, 'the gift card screen draws it');
	pg_assert_contains("pg_audit('update', 'store_credit'", $edit, 'a balance edit of credit is audited');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/gift_card_list_edit.php');
	pg_assert_contains("pg_list_status_word(lang('Store credit'))", $list, 'the list marks credit under the code');
	pg_assert_contains("'<span hidden>' . h(\$gift_card['code']) . '</span>'", $list, 'the search finds the code without dashes');
}

/**
 * The currencies table keeps a symbol as HTML (&#8378;): the card's balance
 * and amounts show the character once, and a symbol typed as markup shows
 * as text.
 */
function test_store_credit_staff_money_shows_the_symbol_once()
{
	pg_assert_same('₺' . pg_format_number(0, 2), pg_credit_staff_money(0, '&#8378;'), 'the lira sign, not &#8378;');
	pg_assert_same('₺' . pg_format_number(500, 2), pg_credit_staff_money(50000, '&#8378;'), 'minor units');
	pg_assert_same('€' . pg_format_number(12.5, 2), pg_credit_staff_money('1250', '&euro;'), 'a named entity');
	pg_assert_same('$' . pg_format_number(12.5, 2), pg_credit_staff_money(1250, '$'), 'a plain symbol');
	pg_assert_same('&lt;b&gt;TL&lt;/b&gt;' . pg_format_number(1, 2), pg_credit_staff_money(100, '<b>TL</b>'), 'markup shows as text');
	pg_assert_same('&lt;i&gt;' . pg_format_number(1, 2), pg_credit_staff_money(100, '&lt;i&gt;'), 'an escaped tag stays text');
}
