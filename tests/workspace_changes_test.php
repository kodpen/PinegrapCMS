<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the record changes the Workspace writes
 * (includes/workspace/changes.php): the checks that need no database, and
 * the sentences a writer leaves on the record in the name of whoever made
 * the change - the assistant that proposed it, or a scheduled action.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

// With no source set, the sentence is the one written for Claude's proposal;
// a scheduled action names itself instead, and clearing the source brings
// Claude's sentence back.
function test_workspace_changes_sentence_names_the_scheduled_action()
{
	ws_change_source(false);

	pg_assert_same(lang('Cancelled in the Workspace, on Claude\'s proposal.'),
		ws_change_sentence('Cancelled in the Workspace, on Claude\'s proposal.', 'Cancelled in the Workspace by the scheduled action “{var:1}”.'),
		'a proposal being applied');

	ws_change_source(array('kind' => 'scheduled', 'name' => 'Pazartesi iptali'));

	$sentence = ws_change_sentence('Cancelled in the Workspace, on Claude\'s proposal.', 'Cancelled in the Workspace by the scheduled action “{var:1}”.');

	pg_assert_contains('Pazartesi iptali', $sentence, 'the action is named');
	pg_assert_false(strpos($sentence, 'Claude') !== false, 'and Claude is not');

	// The sentence's own variable comes first, the action's name second.
	$deleted = ws_change_sentence('Offer ({var:1}) was deleted in the Workspace, on Claude\'s proposal.', 'Offer ({var:1}) was deleted in the Workspace by the scheduled action “{var:2}”.', 'YAZ10');

	pg_assert_contains('YAZ10', $deleted, 'the offer code');
	pg_assert_contains('Pazartesi iptali', $deleted, 'and the action');

	ws_change_source(false);

	pg_assert_contains('YAZ10', ws_change_sentence('Offer ({var:1}) was deleted in the Workspace, on Claude\'s proposal.', 'Offer ({var:1}) was deleted in the Workspace by the scheduled action “{var:2}”.', 'YAZ10'), 'cleared: the proposal sentence with its variable');
	pg_assert_same(null, ws_change_source(), 'and no source');
}

// The stock writer stamps "out of stock since" only on the way in, and the
// product writer recomputes the flag when stock tracking is switched.
function test_workspace_changes_stock_flag_follows_the_product_screen()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
	$start = strpos($source, 'function ws_change_write_stock(');
	$stock = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains("IF(out_of_stock = '1', out_of_stock_timestamp, UNIX_TIMESTAMP())", $stock, 'stamped once, when the product goes out of stock');
	pg_assert_true(strpos($stock, 'out_of_stock_timestamp') < strpos($stock, "out_of_stock = '"), 'read before the flag is written');

	$start = strpos($source, 'function ws_change_write_product(');
	$product = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains("array_key_exists('track_stock', \$values)", $product, 'switching tracking');
	pg_assert_contains("out_of_stock = IF(inventory = '1' AND inventory_quantity <= 0, '1', '0')", $product, 'recomputes the flag');
}

// A money field with a ceiling refuses an amount the column cannot hold,
// instead of letting the non-strict session clamp it; one without a ceiling
// (a BIGINT column) takes any amount.
function test_workspace_changes_money_stays_within_the_column()
{
	// The catalogue (ws_change_types()) reads the database to build itself,
	// so the two fields are written here as it defines them.
	$price = array('money', 2147483647, 'Price', 'price');
	$credit = array('money', 0, 'Credit limit', 'credit_limit');

	pg_assert_same(2147483647, ws_change_value($price, '2147483647'), 'the ceiling itself is taken');
	pg_assert_same(null, ws_change_value($price, '2147483648'), 'one more is refused');
	pg_assert_same(null, ws_change_value($price, -1), 'as is a negative amount');
	pg_assert_same(12550, ws_change_value($price, 12550), 'an ordinary price');
	pg_assert_same(900000000000, ws_change_value($credit, '900000000000'), 'a BIGINT column takes a large limit');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

	pg_assert_contains("'price'                   => array('money', 2147483647, lang('Price'), 'price'),", $source, 'products.price carries the INT ceiling');
	pg_assert_contains("'extra_shipping_cost'     => array('money', 2147483647, lang('Extra Shipping Cost'), 'extra_shipping_cost'),", $source, 'and so does the shipping cost');
	pg_assert_contains("'credit_limit' => array('money', 0, lang('Credit limit'), 'credit_limit'),", $source, 'the BIGINT credit limit has none');
}
