<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for gift card lines in returns (includes/fn/returns.php): a shippable
 * gift card product travels with a recipient like goods, but the card it
 * issued stays spendable, so its line is never returnable and a request
 * naming it is refused in its own words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A gift card line has nothing left to ask for, whatever its quantity.
function test_returns_gift_card_line_not_returnable()
{
	pg_assert_same(2, pg_return_returnable(3, 1), 'goods: quantity less held');
	pg_assert_same(2, pg_return_returnable(3, 1, false), 'goods, flag given');
	pg_assert_same(0, pg_return_returnable(3, 0, true), 'gift card');
	pg_assert_same(0, pg_return_returnable(1, 0, 1), 'gift card flag as the column reads');
}

// A request naming a gift card line is refused with the line's name.
function test_returns_gift_card_line_refused()
{
	$lines = array(
		11 => array('name' => 'Mug', 'returnable' => 2),
		12 => array('name' => 'Gift Card 100', 'returnable' => 0, 'gift_card' => true),
	);

	$result = pg_return_check_lines(array(12 => 1), $lines);
	pg_assert_false($result['ok'], 'gift card refused');
	pg_assert_contains('Gift Card 100', $result['error'], 'names the line');
	pg_assert_contains('gift card', $result['error'], 'says why');

	// Even a stale returnable count that still says 1 does not let it through.
	$lines[12]['returnable'] = 1;
	pg_assert_false(pg_return_check_lines(array(11 => 1, 12 => 1), $lines)['ok'], 'mixed request refused');

	$result = pg_return_check_lines(array(11 => 1, 12 => 0), $lines);
	pg_assert_true($result['ok'], 'goods alone, gift card box left at 0');
	pg_assert_same(array(11 => 1), $result['lines']);
}

// The line reader takes the product's gift card flag and passes it on.
function test_returns_gift_card_line_reader()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/returns.php');
	$start = strpos($source, 'function pg_return_order_lines(');
	$body = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	pg_assert_contains('products.gift_card', $body, 'reads the flag');
	pg_assert_contains("'gift_card'", $body, 'keeps it on the line');
	pg_assert_contains("pg_return_returnable(\$row['quantity'], \$line_held, \$gift_card)", $body, 'returnable follows it');
}
