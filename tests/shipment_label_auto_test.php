<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the automatic shipping label (includes/fn/shipment_label_auto.php):
 * when a driver's switch counts as on, which paid address is due, and the row
 * a failure leaves. No service and no database is reached.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/shipment_label_auto.php');

function test_shipment_label_auto_since()
{
	$on = array('enabled' => true, 'values' => array('auto_label' => '1', 'auto_label_since' => '1700000000'));
	pg_assert_same(1700000000, pg_shipment_label_auto_since($on));

	$driver_off = $on;
	$driver_off['enabled'] = false;
	pg_assert_same(0, pg_shipment_label_auto_since($driver_off));

	pg_assert_same(0, pg_shipment_label_auto_since(array('enabled' => true, 'values' => array('auto_label' => '0', 'auto_label_since' => '5'))));
	pg_assert_same(0, pg_shipment_label_auto_since(array('enabled' => true, 'values' => array())));
	pg_assert_same(0, pg_shipment_label_auto_since(null));
}

function test_shipment_label_auto_due()
{
	$now = 2000000000;
	$auto = array('geliver' => $now - 86400 * 10);

	pg_assert_true(pg_shipment_label_auto_due('geliver', $now - 3600, $auto, $now));
	pg_assert_true(pg_shipment_label_auto_due('geliver', $now - 3 * 86400, $auto, $now));

	// Older than three days, before the switch, unpaid, another driver.
	pg_assert_false(pg_shipment_label_auto_due('geliver', $now - 3 * 86400 - 1, $auto, $now));
	pg_assert_false(pg_shipment_label_auto_due('geliver', $now - 3600, array('geliver' => $now - 60), $now));
	pg_assert_false(pg_shipment_label_auto_due('geliver', 0, $auto, $now));
	pg_assert_false(pg_shipment_label_auto_due('shippo', $now - 3600, $auto, $now));
	pg_assert_false(pg_shipment_label_auto_due('', $now - 3600, $auto, $now));
}

function test_shipment_label_auto_failed_row()
{
	$row = pg_shipment_label_failed_row(12, 34, 'geliver', 1700000000);

	pg_assert_same('failed', $row['state']);
	pg_assert_same('failed-34', $row['reference']);
	pg_assert_same('', $row['number']);
	pg_assert_same('', $row['label_file']);
	pg_assert_same(0, $row['is_return']);
	pg_assert_same(34, $row['ship_to_id']);
	pg_assert_same(12, $row['order_id']);
	pg_assert_true(in_array($row['state'], pg_shipment_label_states(), true));

	// The same columns as a created label's row, so the insert fits the table.
	$created = pg_shipment_label_row(12, 34, 'geliver', array('reference' => 'r', 'tracking_number' => 'N1'), '', 0, 1700000000);
	pg_assert_same(array_keys($created), array_keys($row));
}

function test_shipment_label_auto_failed_row_never_printed()
{
	$document = pg_shipment_label_document(array('state' => 'failed', 'driver' => 'geliver', 'label_file' => '', 'label_removed_at' => 0, 'label_format' => '', 'reference' => 'failed-34'));

	pg_assert_false($document['ok']);
	pg_assert_true($document['error'] !== '');
}

function test_shipment_label_auto_walk_reads_past_refused_addresses()
{
	// 120 addresses the run passes over (a marketplace's parcels, a service
	// with the switch off), oldest first, then one that is due.
	$rows = array();

	for ($i = 1; $i <= 121; $i++) {
		$rows[] = array('ship_to_id' => $i, 'paid_at' => 1000 + $i);
	}

	$reads = array();
	$read = function ($after, $limit) use ($rows, &$reads) {
		$reads[] = $after;
		$page = array();

		foreach ($rows as $row) {
			if (($after === null) || ($row['paid_at'] > $after['paid_at'])
				|| (($row['paid_at'] === $after['paid_at']) && ($row['ship_to_id'] > $after['ship_to_id']))) {
				$page[] = $row;
			}

			if (count($page) >= $limit) {
				break;
			}
		}

		return $page;
	};

	$tried = array();
	$try = function ($row) use (&$tried) {
		$tried[] = $row['ship_to_id'];

		return ($row['ship_to_id'] === 121) ? 'created' : '';
	};

	$out = pg_shipment_label_auto_walk($read, $try, 10, 50, 500);

	pg_assert_same(array('created' => 1, 'failed' => 0), $out);
	pg_assert_same(121, count($tried), 'every address is looked at once');
	pg_assert_same(array('paid_at' => 1050, 'ship_to_id' => 50), $reads[1], 'the next page starts after the last address read');

	// The run stops at its label limit and at its bound of addresses read.
	$tried = array();
	$all = function ($row) use (&$tried) {
		$tried[] = $row['ship_to_id'];

		return 'failed';
	};
	pg_assert_same(array('created' => 0, 'failed' => 3), pg_shipment_label_auto_walk($read, $all, 3, 50, 500));
	pg_assert_same(3, count($tried));

	$tried = array();
	pg_assert_same(array('created' => 0, 'failed' => 0), pg_shipment_label_auto_walk($read, $try, 10, 50, 100));
	pg_assert_same(100, count($tried), 'no more addresses than the bound are read');
}
