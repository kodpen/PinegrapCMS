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

// A refused value is named for what is wrong with it: a number the field
// cannot hold is out of range, anything else is of the wrong kind, and a
// value the field takes has no refusal at all.
function test_workspace_changes_refusal_names_a_value_out_of_range()
{
	$price = array('money', 2147483647, 'Price', 'price');
	$stock = array('int', array(0, 9999999), 'Stock', 'inventory_quantity');
	$weight = array('decimal', array(0, 99999, 2, true), 'Weight', 'weight');
	$range = lang(array('string' => '{var:1}: the value is out of range.', 'vars' => 'price'));
	$kind = lang(array('string' => '{var:1} does not have a value of the right kind.', 'vars' => 'price'));

	pg_assert_same('', ws_change_value_refusal($price, 12550, 'price'), 'a price the column holds');
	pg_assert_same($range, ws_change_value_refusal($price, 2147483648, 'price'), 'one over the INT ceiling');
	pg_assert_same($range, ws_change_value_refusal($price, '2147483648', 'price'), 'the same as text');
	pg_assert_same($range, ws_change_value_refusal($price, -1, 'price'), 'a negative amount');
	pg_assert_same($range, ws_change_value_refusal($price, '1e30', 'price'), 'far beyond any integer');
	pg_assert_same($kind, ws_change_value_refusal($price, 'ucuz', 'price'), 'a word is the wrong kind');
	pg_assert_same($kind, ws_change_value_refusal($price, 125.5, 'price'), 'so is a fraction of a minor unit');
	pg_assert_same($kind, ws_change_value_refusal($price, array(1), 'price'), 'and a list');
	pg_assert_same($kind, ws_change_value_refusal($price, true, 'price'), 'and a yes/no');

	pg_assert_contains('Stock', ws_change_value_refusal($stock, -3, 'Stock'), 'the label is the one the caller gives');
	pg_assert_same(lang(array('string' => '{var:1}: the value is out of range.', 'vars' => 'Stock')), ws_change_value_refusal($stock, -3, 'Stock'), 'stock below zero');
	pg_assert_same(lang(array('string' => '{var:1} does not have a value of the right kind.', 'vars' => 'Stock')), ws_change_value_refusal($stock, '2.5', 'Stock'), 'half a piece');

	pg_assert_same('', ws_change_value_refusal($weight, '12,5', 'Weight'), 'a weight with a decimal comma');
	pg_assert_same(lang(array('string' => '{var:1}: the value is out of range.', 'vars' => 'Weight')), ws_change_value_refusal($weight, '100000,5', 'Weight'), 'a weight over the maximum');
	pg_assert_same(lang(array('string' => '{var:1} does not have a value of the right kind.', 'vars' => 'Weight')), ws_change_value_refusal($weight, 'ağır', 'Weight'), 'a weight in words');

	// Text fields have no range: their refusal stays the wrong kind.
	pg_assert_same(lang(array('string' => '{var:1} does not have a value of the right kind.', 'vars' => 'Name')), ws_change_value_refusal(array('text', 100, 'Name', 'name'), array('a'), 'Name'), 'a list given for a text');
}

// The writers that check a value against its field say why it was refused.
function test_workspace_changes_writers_use_the_refusal_sentence()
{
	foreach (array('changes.php' => 'return $refuse(ws_change_value_refusal(', 'bulk.php' => 'return $fail(ws_change_value_refusal(', 'scheduled.php' => 'return $fail(ws_change_value_refusal(') as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file);

		pg_assert_same(($file === 'bulk.php') ? 2 : 1, substr_count($source, $call), $file . ' names the refusal');
	}
}

// An order's status moves by the store's rule (pg_order_status_move()), as
// the external API moves it: a move the rule refuses is refused when it is
// proposed, applied, bulk-run or scheduled, before anything is written.
function test_workspace_changes_order_status_follows_the_store_rule()
{
	$viewer = array('id' => 1, 'role' => 0);
	$complete = array('id' => 7, 'status' => 'complete');
	$cancelled = array('id' => 7, 'status' => 'cancelled');
	$cart = array('id' => 7, 'status' => 'incomplete');

	pg_assert_same(pg_order_status_move_refusal('complete', 'incomplete'), ws_change_check($viewer, 'order', 'update', $complete, array('status' => 'incomplete')), 'an order is not turned into a basket');
	pg_assert_same(pg_order_status_move_refusal('cancelled', 'incomplete'), ws_change_check($viewer, 'order', 'update', $cancelled, array('status' => 'incomplete')), 'nor a cancelled one');
	pg_assert_true(ws_change_check($viewer, 'order', 'update', $cart, array('status' => 'complete')) !== '', 'a basket is not completed');
	pg_assert_true(ws_change_check($viewer, 'order', 'update', $cart, array('status' => 'cancelled')) !== '', 'nor cancelled');

	pg_assert_same('', ws_change_check($viewer, 'order', 'update', $complete, array('status' => 'exported')), 'complete to exported');
	pg_assert_same('', ws_change_check($viewer, 'order', 'update', $complete, array('status' => 'cancelled')), 'a cancellation');
	pg_assert_same('', ws_change_check($viewer, 'order', 'update', $cancelled, array('status' => 'complete')), 'a restore');
	pg_assert_same('', ws_change_check($viewer, 'order', 'update', $cart, array('notes' => 'Aranacak')), 'a note on a basket');
	pg_assert_same('', ws_change_check($viewer, 'order', 'update', $cart, array()), 'the drawer opening a basket');
}

// The order writer decides by the same rule and never writes a status into
// or out of cancelled by hand: the cancellation and the restore are the
// store's helpers, and its one status write is a plain move that lands only
// while the order still has the status it was read with.
function test_workspace_changes_order_writer_uses_the_helpers()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');
	$start = strpos($source, "\nfunction ws_change_write_order(");
	$end = ($start === false) ? false : strpos($source, "\nfunction ", $start + 1);

	pg_assert_true(($start !== false) && ($end !== false), 'ws_change_write_order() is in changes.php');

	$writer = substr($source, $start, $end - $start);

	pg_assert_contains('$move = ($status === null) ? \'none\' : pg_order_status_move((string) $record[\'status\'], (string) $status);', $writer, 'the move is the rule\'s');
	pg_assert_contains("if (\$move === 'refuse') {", $writer, 'a refused move fails');
	pg_assert_contains('process_order_cancellation((int) $record[\'id\'], $reason, true, (int) $viewer[\'id\']);', $writer, 'a cancel through the cancellation');
	pg_assert_contains('pg_order_restore_cancelled((int) $record[\'id\'], $status)', $writer, 'a restore through the restore');

	preg_match_all('/UPDATE\s+orders\s+SET[^"]*\bstatus\s*=[^;]*;/', $writer, $writes);

	pg_assert_same(1, count($writes[0]), 'one status write of its own');
	pg_assert_contains("AND status = '\" . e(\$record['status']) . \"'", isset($writes[0][0]) ? $writes[0][0] : '', 'guarded by the status it was read with');
}
