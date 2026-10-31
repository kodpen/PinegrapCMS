<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for carrier pickup requests of shipment labels
 * (includes/fn/shipment_labels.php): the upgrade step of pickup_ref and
 * pickup_at, which labels one request takes, the warning a booked label
 * gives, and the window read from the dialog. No service and no database
 * is reached.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of one top-level function, up to the next one.
function shipment_label_pickup_test_body($relative, $name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
	$start = strpos($source, "\nfunction " . $name . '(');

	if ($start === false) {
		return '';
	}

	$next = strpos($source, "\nfunction ", $start + 1);

	return ($next === false) ? substr($source, $start) : substr($source, $start, $next - $start);
}

function shipment_label_pickup_test_row($id, $changes = array())
{
	return array_merge(array(
		'id'         => $id,
		'order_id'   => 10,
		'ship_to_id' => 20,
		'number'     => 'N' . $id,
		'driver'     => 'shippo',
		'reference'  => 'ref' . $id,
		'state'      => 'active',
		'is_return'  => 0,
		'carrier'    => 'dpd',
		'pickup_ref' => '',
		'pickup_at'  => 0,
	), $changes);
}

// The step is called once with its label and adds both columns with the
// idempotent helper only: a second run finds them and changes nothing.
function test_shipment_label_pickup_step()
{
	$entry = shipment_label_pickup_test_body('includes/migrations/2026.11.0.php', 'upgrade_to_2026_11_0');
	$step = shipment_label_pickup_test_body('includes/migrations/2026.11.0.php', 'upgrade_2026_11_0_shipment_label_pickup');

	pg_assert_true(preg_match('/upgrade_2026_11_0_shipment_label_pickup\(\);\s+\/\/ 11\.0\.131\b/', $entry) === 1, 'called with its label');
	pg_assert_same(1, preg_match_all('/\/\/ 11\.0\.131\b/', $entry), 'the label is used once');
	pg_assert_contains("install_add_column('shipment_labels', 'pickup_ref', \"VARCHAR(64) NOT NULL DEFAULT ''\")", $step);
	pg_assert_contains("install_add_column('shipment_labels', 'pickup_at', \"INT UNSIGNED NOT NULL DEFAULT 0\")", $step);
	pg_assert_false(strpos($step, 'db(') !== false, 'no raw statement');
	pg_assert_false(stripos($step, 'ALTER TABLE') !== false, 'no hand-written ALTER');
}

// Both columns are read only after the exact-name probe.
function test_shipment_label_pickup_ready_probe()
{
	$body = shipment_label_pickup_test_body('includes/fn/shipment_labels.php', 'pg_shipment_labels_pickup_ready');

	pg_assert_contains("pg_schema_has('shipment_labels', 'pickup_ref')", $body);
	pg_assert_contains("pg_schema_has('shipment_labels', 'pickup_at')", $body);
	pg_assert_false(pg_shipment_labels_pickup_ready(), 'no database in the test run');
	pg_assert_same(array(), pg_shipment_label_pickup_drivers());
}

// One request per label service and carrier; cancelled labels, returns,
// labels without a reference and services that book no pickup are left out.
function test_shipment_label_pickup_groups_by_service_and_carrier()
{
	$rows = array(
		shipment_label_pickup_test_row(1),
		shipment_label_pickup_test_row(2, array('carrier' => 'ups')),
		shipment_label_pickup_test_row(3, array('order_id' => 11)),
		shipment_label_pickup_test_row(4, array('state' => 'cancelled')),
		shipment_label_pickup_test_row(5, array('is_return' => 1)),
		shipment_label_pickup_test_row(6, array('reference' => '')),
		shipment_label_pickup_test_row(7, array('driver' => 'geliver')),
		shipment_label_pickup_test_row(8, array('driver' => 'sendcloud')),
	);

	$groups = pg_shipment_label_pickup_groups($rows, array('shippo', 'sendcloud'));

	pg_assert_same(array('shippo dpd', 'shippo ups', 'sendcloud dpd'), array_keys($groups));
	pg_assert_same(array(1, 3), array_column($groups['shippo dpd'], 'id'));
	pg_assert_same(array(), pg_shipment_label_pickup_groups($rows, array()));
}

// A label with a pickup booked warns, naming the confirmation once.
function test_shipment_label_pickup_warns_on_a_booked_label()
{
	$groups = pg_shipment_label_pickup_groups(array(
		shipment_label_pickup_test_row(1, array('pickup_ref' => 'PRG4711', 'pickup_at' => 1793606400)),
		shipment_label_pickup_test_row(2, array('pickup_ref' => 'PRG4711', 'pickup_at' => 1793606400)),
		shipment_label_pickup_test_row(3),
	), array('shippo'));
	$booked = pg_shipment_label_pickup_booked($groups);

	pg_assert_same(array(1, 2), array_column($booked, 'id'));

	$warning = pg_shipment_label_pickup_warning($booked);

	pg_assert_contains('PRG4711', $warning);
	pg_assert_same(1, substr_count($warning, 'PRG4711'));
	pg_assert_contains('2', $warning);
	pg_assert_same('', pg_shipment_label_pickup_warning(array()));
	pg_assert_same(array(), pg_shipment_label_pickup_booked(pg_shipment_label_pickup_groups(array(shipment_label_pickup_test_row(3)), array('shippo'))));
}

// The window is read in the site's time zone; a wrong date or time, an end
// before the start and a window gone by are refused.
function test_shipment_label_pickup_window()
{
	$now = mktime(12, 0, 0, 11, 1, 2026);
	$window = pg_shipment_label_pickup_window('2026-11-02', '09:00', '17:30', $now);

	pg_assert_same('', $window['error']);
	pg_assert_same(mktime(9, 0, 0, 11, 2, 2026), $window['start']);
	pg_assert_same(mktime(17, 30, 0, 11, 2, 2026), $window['end']);

	pg_assert_true(pg_shipment_label_pickup_window('2026-02-30', '09:00', '17:00', $now)['error'] !== '', 'no such day');
	pg_assert_true(pg_shipment_label_pickup_window('2026-11-02', '9', '17:00', $now)['error'] !== '', 'no such time');
	pg_assert_true(pg_shipment_label_pickup_window('2026-11-02', '17:00', '09:00', $now)['error'] !== '', 'end before start');
	pg_assert_true(pg_shipment_label_pickup_window('2026-11-01', '08:00', '11:00', $now)['error'] !== '', 'gone by');
	pg_assert_same('', pg_shipment_label_pickup_window('2026-11-01', '08:00', '18:00', $now)['error'], 'today, still open');
	pg_assert_true(pg_shipment_label_pickup_window(array(), '08:00', '18:00', $now)['error'] !== '', 'not text');
}

// The dialog posts its fields with the form it names, and escapes the
// warning it shows.
function test_shipment_label_pickup_dialog()
{
	$html = pg_shipment_label_pickup_dialog('shipment_pickup_20', 'shipment-label-form', 'pickup-20', '<b>booked</b>');

	pg_assert_contains('value="pickup-20"', $html);
	pg_assert_same(5, substr_count($html, 'form="shipment-label-form"'));
	pg_assert_contains('name="pickup_again"', $html);
	pg_assert_contains('&lt;b&gt;booked', $html);
	pg_assert_false(strpos($html, 'form="') !== false && strpos(pg_shipment_label_pickup_dialog('d', '', 'bulk_pickup'), 'form="') !== false, 'the list dialog uses its own form');
}
