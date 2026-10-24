<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the order part of the marketplace account screen
 * (marketplace_settings.php): a marketplace whose connector cannot bring
 * orders in starts with order import off, and its screen
 * draws neither the order switches nor the "Imported orders" card, only a
 * line saying orders are not brought in from it yet. The scheduled job skips
 * such an account without a word, so a switch shown on would promise orders
 * that never come.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function marketplace_settings_orders_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');
}

// The column defaults to 1, so an INSERT that does not name it starts every
// account importing orders, whether its connector can or not.
function test_marketplace_settings_new_account_imports_orders_only_when_it_can()
{
	$source = marketplace_settings_orders_test_source();

	$start = strpos($source, 'INSERT INTO marketplace_accounts');

	pg_assert_true($start !== false, 'the account INSERT is there');

	$insert = substr($source, $start, (int) strpos($source, 'mysqli_insert_id', $start) - $start);

	pg_assert_contains('pull_orders', $insert);
	pg_assert_contains("mp_can(\$provider, 'pull_orders')", $insert);
}

function test_marketplace_settings_orders_card_needs_a_connector_that_pulls()
{
	$source = marketplace_settings_orders_test_source();

	pg_assert_contains("\$can_orders = mp_can(\$selected['provider'], 'pull_orders');", $source);
	pg_assert_contains("\$can_approve = \$can_orders && mp_can(\$selected['provider'], 'approve_lines');", $source);
	pg_assert_contains("if (\$can_orders && !empty(\$selected['pull_orders'])) {", $source);
}

// The two switches are drawn only behind the capability, and the line that
// replaces them is translated.
function test_marketplace_settings_order_switches_follow_the_connector()
{
	$source = marketplace_settings_orders_test_source();

	$orders_gate = strpos($source, '. ($can_orders ? \'');
	$pull_switch = strpos($source, 'id="edit_pull_orders"');
	// Searched after the import switch: the switch's own margin also reads
	// $can_approve.
	$approve_gate = ($pull_switch === false) ? false : strpos($source, '. ($can_approve ? \'', $pull_switch);
	$approve_switch = strpos($source, 'id="edit_auto_approve"');

	pg_assert_true($orders_gate !== false, 'the order switches are gated');
	pg_assert_true(($pull_switch !== false) && ($orders_gate < $pull_switch), 'the import switch sits behind the gate');
	pg_assert_true($approve_gate !== false, 'the accept switch has its own gate');
	pg_assert_true(($approve_switch !== false) && ($approve_gate < $approve_switch), 'the accept switch sits behind it');

	pg_assert_contains("'string' => 'Orders are not brought in from {var:1} yet.'", $source);
}
