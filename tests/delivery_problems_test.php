<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Orders › Delivery problems (includes/fn/delivery_problems.php):
 * the filters and their WHERE, the days, the note's first words and its
 * closing note, why a closed problem closed, what an assign, note or close
 * changes, the audit view, "What happened" and the usual days; and the
 * screen's pins: the orders gate, the token, the menu entry, the printed
 * list, one filled button in a view and no danger button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/delivery_problems.php');

function _delivery_problems_test_problem(array $more = array())
{
	return array_merge(array('id' => 7, 'kind' => 1, 'found_at' => 1000, 'assigned_to' => 0, 'note' => '', 'closed_at' => 0, 'closed_by' => 0, 'close_reason' => 0), $more);
}

function test_delivery_problems_filters()
{
	pg_assert_same(array('view' => 'open', 'kind' => 0, 'carrier' => ''), pg_delivery_problem_filters(array()), 'open, every kind, every carrier by default');
	pg_assert_same(array('view' => 'closed', 'kind' => 3, 'carrier' => 'yurtici'), pg_delivery_problem_filters(array('view' => 'closed', 'kind' => '3', 'carrier' => ' yurtici ')), 'the address is read');
	pg_assert_same(array('view' => 'open', 'kind' => 0, 'carrier' => ''), pg_delivery_problem_filters(array('view' => 'x', 'kind' => '9', 'carrier' => array('a'))), 'unknown values fall back');

	list($sql, $params) = pg_delivery_problem_where(array('view' => 'open', 'kind' => 0, 'carrier' => ''));
	pg_assert_same('(shipment_problems.closed_at = 0)', $sql, 'open problems only');
	pg_assert_same(array(), $params, 'nothing bound');

	list($sql, $params) = pg_delivery_problem_where(array('view' => 'all', 'kind' => 2, 'carrier' => "x' OR 1"));
	pg_assert_same('(shipment_problems.kind = ?) AND (shipping_tracking_numbers.carrier = ?)', $sql, 'kind and carrier are bound, never written into the SQL');
	pg_assert_same(array(2, "x' OR 1"), $params, 'their values');

	list($sql) = pg_delivery_problem_where(array('view' => 'all', 'kind' => 0, 'carrier' => ''));
	pg_assert_same('1', $sql, 'All with no filter reads every row');

	pg_assert_same('view_delivery_problems.php', pg_delivery_problem_url(array('view' => 'open', 'kind' => 0, 'carrier' => '')), 'the default leaves the address bare');
	pg_assert_same('view_delivery_problems.php?view=closed&kind=4&carrier=ptt&problem=9', pg_delivery_problem_url(array('view' => 'closed', 'kind' => 4, 'carrier' => 'ptt'), array('problem' => 9)), 'the filters are kept beside the problem');
}

function test_delivery_problems_days_and_note()
{
	$found = mktime(10, 0, 0, 11, 16, 2026);
	pg_assert_same(4, pg_delivery_problem_days(array('found_at' => $found, 'closed_at' => 0), mktime(9, 0, 0, 11, 20, 2026)), 'an open problem counts to now');
	pg_assert_same(1, pg_delivery_problem_days(array('found_at' => $found, 'closed_at' => mktime(8, 0, 0, 11, 17, 2026)), mktime(9, 0, 0, 11, 20, 2026)), 'a closed one stops when it closed');

	pg_assert_same('Called the branch', pg_delivery_problem_note_start("  Called   the\nbranch "), 'a short note stays whole on one line');
	$start = pg_delivery_problem_note_start('The customer called twice and the branch says the parcel is waiting at the transfer centre', 40);
	pg_assert_same('The customer called twice and the…', $start, 'cut at a whole word with an ellipsis');

	pg_assert_same('Called', pg_delivery_problem_note_clean(' Called ', ''), 'trimmed');
	pg_assert_same('Called · Sent with Aras', pg_delivery_problem_note_clean('Called', 'Sent with Aras'), 'a closing note comes after the note');
	pg_assert_same('Sent with Aras', pg_delivery_problem_note_clean('', 'Sent with Aras'), 'or stands alone');
	pg_assert_same(PG_DELIVERY_PROBLEMS_NOTE, mb_strlen(pg_delivery_problem_note_clean(str_repeat('ş', 700))), 'held to the column');
}

function test_delivery_problems_close_label()
{
	$codes = pg_delivery_tower_close_reasons();
	pg_assert_same('', pg_delivery_problem_close_label(array('close_reason' => 0), null, 'paid'), 'open: no reason');
	pg_assert_same('Refunded', pg_delivery_problem_close_label(array('close_reason' => $codes['refunded']), null, 'paid'), 'a reason chosen by hand');
	pg_assert_same('Delivered', pg_delivery_problem_close_label(array('close_reason' => $codes['status']), array('status' => 'in_transit', 'delivered_at' => 5), 'paid'), 'a delivery closed it');
	pg_assert_same('Scanned by the carrier', pg_delivery_problem_close_label(array('close_reason' => $codes['status']), array('status' => 'in_transit', 'delivered_at' => 0), 'paid'), 'a later scan closed it');
	pg_assert_same('The order was cancelled', pg_delivery_problem_close_label(array('close_reason' => $codes['status']), array('status' => 'in_transit', 'delivered_at' => 0), 'cancelled'), 'its order was cancelled');
	pg_assert_same('The parcel was removed', pg_delivery_problem_close_label(array('close_reason' => $codes['status']), null, 'paid'), 'its parcel row is gone');
}

function test_delivery_problems_changes()
{
	$people = array(3 => 'ayse', 5 => 'mehmet');
	$codes = pg_delivery_tower_close_reasons();

	$out = pg_delivery_problem_changes(_delivery_problems_test_problem(), array('assigned_to' => 5, 'note' => ' Called '), $people, 2000, 3);
	pg_assert_same(array('assigned_to' => 5, 'note' => 'Called', 'updated_at' => 2000), $out['changes'], 'assign and note');

	$out = pg_delivery_problem_changes(_delivery_problems_test_problem(), array('assigned_to' => 99), $people, 2000, 3);
	pg_assert_same(array(), $out['changes'], 'a person without the orders right is nobody, and nobody was already set');

	$out = pg_delivery_problem_changes(_delivery_problems_test_problem(array('assigned_to' => 3, 'note' => 'Called')), array('assigned_to' => 3, 'note' => 'Called'), $people, 2000, 3);
	pg_assert_same(array(), $out['changes'], 'nothing changed: no write and no audit line');

	$out = pg_delivery_problem_changes(_delivery_problems_test_problem(array('note' => 'Called')), array('close' => $codes['sent_again'], 'close_note' => 'Aras 123'), $people, 2000, 3);
	pg_assert_same(array('closed_at' => 2000, 'closed_by' => 3, 'close_reason' => $codes['sent_again'], 'note' => 'Called · Aras 123', 'updated_at' => 2000), $out['changes'], 'closed by hand with its note; the bulk close keeps the assignee');

	$out = pg_delivery_problem_changes(_delivery_problems_test_problem(), array('close' => $codes['status']), $people, 2000, 3);
	pg_assert_same('reason', $out['error'], 'the pass\'s own reason is never chosen by hand');

	$out = pg_delivery_problem_changes(_delivery_problems_test_problem(array('closed_at' => 1500)), array('note' => 'late'), $people, 2000, 3);
	pg_assert_same('closed', $out['error'], 'a closed problem is not changed');
	pg_assert_same(array(), $out['changes'], 'nothing written');

	$view = pg_delivery_problem_audit_view(_delivery_problems_test_problem(array('number' => '123', 'billing_first_name' => 'Ayşe', 'city' => 'İzmir')));
	pg_assert_same(array('kind', 'assigned_to', 'note', 'closed_at', 'closed_by', 'close_reason'), array_keys($view), 'the audit line keeps no name or address');
}

function test_delivery_problems_events()
{
	$parcel = array('created_at' => 100, 'shipped_at' => 200, 'status' => 'exception', 'status_at' => 400, 'delivered_at' => 0);
	$problems = array(array('id' => 1, 'kind' => 1, 'found_at' => 300, 'closed_at' => 450, 'close_reason' => 1), array('id' => 2, 'kind' => 3, 'found_at' => 500, 'closed_at' => 0, 'close_reason' => 0));
	$events = pg_delivery_problem_events($parcel, $problems, array(1 => 'Scanned by the carrier'));

	pg_assert_same(array(500, 450, 400, 300, 200, 100), array_column($events, 'at'), 'newest first');
	pg_assert_same('Found: Delivery problem', $events[0]['text'], 'a problem found');
	pg_assert_same('Closed: Scanned by the carrier', $events[1]['text'], 'a problem closed with its reason');
	pg_assert_same('Delivery problem', $events[2]['text'], 'the parcel\'s latest status');
	pg_assert_same('Label made', $events[5]['text'], 'the label first of all');

	$events = pg_delivery_problem_events(array('created_at' => 100, 'shipped_at' => 100, 'status' => 'delivered', 'status_at' => 600, 'delivered_at' => 600), array());
	pg_assert_same(array('Delivered', 'Label made'), array_column($events, 'text'), 'shipped with its label and delivered: no second line for either');

	pg_assert_same(array(), pg_delivery_problem_events(null, array()), 'a removed parcel with nothing found');
}

function test_delivery_problems_transit_and_usual_days()
{
	$shipped = mktime(10, 0, 0, 11, 16, 2026);
	pg_assert_same(4, pg_delivery_problem_transit_days(array('shipped_at' => $shipped, 'delivered_at' => 0), mktime(9, 0, 0, 11, 20, 2026)), 'on its way to now');
	pg_assert_same(2, pg_delivery_problem_transit_days(array('shipped_at' => $shipped, 'delivered_at' => mktime(9, 0, 0, 11, 18, 2026)), mktime(9, 0, 0, 11, 20, 2026)), 'to its delivery');
	pg_assert_same(null, pg_delivery_problem_transit_days(array('shipped_at' => 0), 5), 'not shipped');

	$summary = array('at' => 1, 'figures' => array('yurtici' => array(
		'country'   => array('count' => 40, 'median' => 2.5, 'p90' => 4),
		'provinces' => array('izmir' => array('count' => 25, 'median' => 1.25, 'p90' => 2)),
	)));
	pg_assert_same(1.3, pg_delivery_problem_usual_days($summary, 'yurtici', 'İzmir', ''), 'the province\'s median, to a tenth');
	pg_assert_same(2.5, pg_delivery_problem_usual_days($summary, 'yurtici', 'Van', ''), 'else the country\'s');
	pg_assert_same(null, pg_delivery_problem_usual_days($summary, 'aras', 'İzmir', ''), 'a carrier not measured');
	pg_assert_same(null, pg_delivery_problem_usual_days(null, 'yurtici', 'İzmir', ''), 'no summary yet');
}

function test_delivery_problems_screen_pins()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/view_delivery_problems.php');
	$module = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/delivery_problems.php');

	pg_assert_contains('validate_ecommerce_access($user);', $screen, 'the orders right');
	pg_assert_contains('validate_token_field();', $screen, 'every change carries the token');
	pg_assert_contains("'ids_field' => 'problems[]'", $screen, 'the bulk bar posts the chosen ids');
	pg_assert_false(strpos($screen, 'pg-button-fill') !== false, 'the list screen has no fill: nothing is added here');
	pg_assert_false(strpos($screen . $module, 'pg-button-danger') !== false, 'a problem is never deleted');
	pg_assert_false(stripos($module, 'DELETE FROM') !== false, 'the desk deletes no row');
	pg_assert_contains("pg_audit(isset(\$changes['closed_at']) ? 'close' : 'update', 'shipment_problem'", $module, 'every assign, note and close is an audit line');
	pg_assert_contains('AND closed_at = 0', $module, 'a problem the pass closed meanwhile is not written over');

	pg_assert_true(in_array('delivery_problems', pg_list_table_printed_lists(), true), 'the printed list keeps its sort');
	pg_assert_contains('/view_delivery_problems.php', file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php'), 'Orders › Delivery problems in the menu');
}
