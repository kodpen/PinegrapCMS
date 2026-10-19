<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a record's fields edited by a person from the Workspace drawer
 * (includes/workspace/quick_edit.php): the parts that need no database -
 * how a catalogue field is described to the drawer, how a value the drawer
 * sends back is laid against what the record holds now, which kinds are
 * offered, and the sentence a writer leaves in the person's name.
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
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/quick_edit.php');

// Each kind of field tells the drawer what to draw: a limit for text, int,
// decimal, lines and money; values with labels for an enum; whether the
// field has a column of its own.
function test_workspace_quick_edit_field_meta_describes_each_kind()
{
	$text = ws_quick_edit_field_meta('product', 'brand', array('text', 100, 'Brand', 'brand'));
	pg_assert_same('text', $text['kind'], 'kind');
	pg_assert_same(array('max_length' => 100), $text['limit'], 'text limit');
	pg_assert_true($text['column'], 'has a column');

	$money = ws_quick_edit_field_meta('product', 'price', array('money', 2147483647, 'Price', 'price'));
	pg_assert_same(array('max' => 2147483647), $money['limit'], 'money ceiling');

	$open = ws_quick_edit_field_meta('erp_account', 'credit_limit', array('money', 0, 'Credit limit', 'credit_limit'));
	pg_assert_same(null, $open['limit'], 'money without a ceiling');

	$int = ws_quick_edit_field_meta('product', 'minimum_quantity', array('int', array(0, 1000000), 'Minimum quantity', 'minimum_quantity'));
	pg_assert_same(array('min' => 0, 'max' => 1000000), $int['limit'], 'int bounds');

	$decimal = ws_quick_edit_field_meta('product', 'weight', array('decimal', array(0, 100000, 3, false), 'Weight', 'weight'));
	pg_assert_same(3, $decimal['limit']['decimals'], 'decimals');
	pg_assert_false($decimal['limit']['may_be_empty'], 'may not be empty');

	$lines = ws_quick_edit_field_meta('order', 'tracking_numbers', array('lines', array(20, 100), 'Tracking Numbers', '__tracking_numbers'));
	pg_assert_same(array('max_items' => 20, 'max_length' => 100), $lines['limit'], 'lines');

	$enum = ws_quick_edit_field_meta('order', 'status', array('enum', array('incomplete', 'complete', 'exported', 'cancelled'), 'Status', 'status'));
	pg_assert_same(array('incomplete', 'complete', 'exported', 'cancelled'), $enum['values'], 'enum values');
	pg_assert_same(lang('Cancelled'), $enum['labels']['cancelled'], 'enum labels as the card shows them');

	$reason = ws_quick_edit_field_meta('order', 'cancellation_reason', array('long', 500, 'Reason for cancelling', ''));
	pg_assert_false($reason['column'], 'a field that goes with another has no column');
	pg_assert_same(array('max_length' => 500), $reason['limit'], 'but still a limit');
}

// What the drawer sends back is laid against the record in the field's own
// shape: the same value, however it was typed, is no move; a different one,
// or one the field cannot hold, is.
function test_workspace_quick_edit_moved_compares_in_the_fields_shape()
{
	$money = array('money', 2147483647, 'Price', 'price');
	pg_assert_false(ws_quick_edit_moved($money, '1250', 1250), 'a number sent as text');
	pg_assert_true(ws_quick_edit_moved($money, 1250, 1300), 'somebody raised the price');
	pg_assert_true(ws_quick_edit_moved($money, 'abc', 1250), 'not a price at all');

	$bool = array('bool', 0, 'Published', 'enabled');
	pg_assert_false(ws_quick_edit_moved($bool, '1', true), 'true as "1"');
	pg_assert_false(ws_quick_edit_moved($bool, false, false), 'false as false');
	pg_assert_true(ws_quick_edit_moved($bool, '0', true), 'switched on meanwhile');

	$long = array('long', 2000, 'Short description', 'short_description');
	pg_assert_false(ws_quick_edit_moved($long, "a\r\nb", "a\nb"), 'line endings do not count');
	pg_assert_false(ws_quick_edit_moved($long, '', ''), 'empty stays empty');
	pg_assert_true(ws_quick_edit_moved($long, 'old words', 'new words'), 'rewritten meanwhile');

	$decimal = array('decimal', array(0, 100000, 3, false), 'Weight', 'weight');
	pg_assert_false(ws_quick_edit_moved($decimal, '1.5', '1.5'), 'a decimal as text');
	pg_assert_false(ws_quick_edit_moved($decimal, '1,5', '1.5'), 'typed with a comma');
	pg_assert_true(ws_quick_edit_moved($decimal, '1.5', '2'), 'changed');
}

// The drawer edits the kinds with a screen of their own outside the
// workspace; the workspace's own things and a user's role are not here, and
// the product's stock rides along as one more field.
function test_workspace_quick_edit_types_and_stock_field()
{
	$types = ws_quick_edit_types();

	pg_assert_true(in_array('product', $types, true), 'products');
	pg_assert_true(in_array('order', $types, true), 'orders');
	pg_assert_true(in_array('contact', $types, true), 'contacts');
	pg_assert_false(in_array('stock', $types, true), 'stock is a field of the product here');
	pg_assert_false(in_array('user', $types, true), 'a role is the user screen\'s');
	pg_assert_false(in_array('task', $types, true), 'a task has the task drawer');
	pg_assert_false(in_array('note', $types, true), 'a note has the notes screen');
	pg_assert_false(in_array('channel', $types, true), 'a summary is the channel\'s own');
	pg_assert_same('quantity', WS_QUICK_EDIT_STOCK_FIELD, 'the stock field is the catalogue\'s quantity');
}

// A writer's sentence left on the record names the person when they made
// the change themselves, the scheduled action when one ran, and the
// assistant otherwise.
function test_workspace_quick_edit_sentence_names_the_person()
{
	ws_change_source(array('kind' => 'person', 'name' => 'Ayşe Yılmaz'));

	$sentence = ws_change_sentence('Cancelled in the Workspace, on Claude\'s proposal.', 'Cancelled in the Workspace by the scheduled action “{var:1}”.', null, 'Cancelled in the Workspace by {var:1}.');

	pg_assert_contains('Ayşe Yılmaz', $sentence, 'the person is named');
	pg_assert_false(strpos($sentence, 'Claude') !== false, 'and Claude is not');

	$deleted = ws_change_sentence('Offer ({var:1}) was deleted in the Workspace, on Claude\'s proposal.', 'Offer ({var:1}) was deleted in the Workspace by the scheduled action “{var:2}”.', 'YAZ10', 'Offer ({var:1}) was deleted in the Workspace by {var:2}.');

	pg_assert_contains('YAZ10', $deleted, 'the offer code first');
	pg_assert_contains('Ayşe Yılmaz', $deleted, 'the person second');

	// A writer that has no sentence for a person falls back to the
	// assistant's, which is better than nothing but is not what the
	// callers in changes.php do.
	ws_change_source(false);

	pg_assert_same(lang('Cancelled in the Workspace, on Claude\'s proposal.'), ws_change_sentence('Cancelled in the Workspace, on Claude\'s proposal.', 'Cancelled in the Workspace by the scheduled action “{var:1}”.', null, 'Cancelled in the Workspace by {var:1}.'), 'no source: the proposal');
}

// Both writers that leave a sentence on the record carry the three wordings.
function test_workspace_quick_edit_writers_carry_a_person_sentence()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/changes.php');

	pg_assert_contains("null, 'Cancelled in the Workspace by {var:1}.');", $source, 'the order writer');
	pg_assert_contains("(string) \$record['code'], 'Offer ({var:1}) was deleted in the Workspace by {var:2}.'));", $source, 'the offer writer');
}
