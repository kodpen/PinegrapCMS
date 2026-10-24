<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the audit trail lines the Workspace's writes leave
 * (ws_change_write(), ws_change_audit_target() in
 * includes/workspace/changes.php): which kind of record becomes a line on
 * which table and with which action, that every caller writes through the
 * wrapper, and that a write recorded as the job's gives the switch back.
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

// A writer of a kind the trail does not follow: it answers what it was given
// and notes whether the trail was switched to the job while it ran.
if (!function_exists('ws_change_write_audit_probe')) {
	function ws_change_write_audit_probe($viewer, $action, $record, $values)
	{
		if (!empty($values['throw'])) {
			throw new RuntimeException('probe');
		}

		return array('ok' => true, 'error' => '', 'id' => (int) ($record['id'] ?? 0), 'as_job' => pg_audit_as_job(), 'action' => $action);
	}
}

// Each kind the trail follows is written on its own table; an update, and
// any action other than create or delete, is an "update".
function test_workspace_audit_target_maps_each_kind_to_its_table()
{
	$ok = array('ok' => true, 'error' => '', 'id' => 7);
	$tables = array(
		'product' => 'products',
		'stock'   => 'products',
		'order'   => 'orders',
		'user'    => 'user',
		'page'    => 'page',
		'file'    => 'files',
		'offer'   => 'offers',
	);

	foreach ($tables as $type => $table) {
		pg_assert_same(array('table' => $table, 'action' => 'update'), ws_change_audit_target($type, 'update', $ok), $type . ' update');
	}

	pg_assert_same(array('table' => 'products', 'action' => 'create'), ws_change_audit_target('product', 'create', $ok), 'a new product');
	pg_assert_same(array('table' => 'products', 'action' => 'update'), ws_change_audit_target('product', 'add', $ok), 'add is an update');
	pg_assert_same(array('table' => 'products', 'action' => 'update'), ws_change_audit_target('product', 'remove', $ok), 'remove is an update');
	pg_assert_same(array('table' => 'orders', 'action' => 'update'), ws_change_audit_target('order', '', $ok), 'no action given');

	foreach (array_keys($tables) as $type) {
		pg_assert_true(isset(pg_audit_tables()[$tables[$type]]), $tables[$type] . ' is a table of the trail');
	}
}

// The kinds the trail has no table for leave no line, whatever the action.
function test_workspace_audit_target_leaves_out_other_kinds()
{
	$ok = array('ok' => true, 'error' => '', 'id' => 7);

	foreach (array('contact', 'erp_account', 'product_group', 'form', 'calendar_event', 'channel', 'task', 'task_note', 'note', 'audit_probe', '') as $type) {
		foreach (array('create', 'update', 'delete', 'add', 'remove') as $action) {
			pg_assert_same(array(), ws_change_audit_target($type, $action, $ok), $type . ' ' . $action);
		}

		pg_assert_same(array(), ws_change_audit_target($type, 'delete', $ok + array('binned' => true)), $type . ' binned');
	}
}

// A record moved to the Recycle Bin is a "recycle"; a product or an offer
// deleted for good is left to its helper, which writes its own line; any
// other delete is a "delete".
function test_workspace_audit_target_delete_and_recycle()
{
	$ok = array('ok' => true, 'error' => '', 'id' => 7);
	$binned = $ok + array('binned' => true);

	pg_assert_same(array('table' => 'products', 'action' => 'recycle'), ws_change_audit_target('product', 'delete', $binned), 'product to the bin');
	pg_assert_same(array('table' => 'page', 'action' => 'recycle'), ws_change_audit_target('page', 'delete', $binned), 'page to the bin');
	pg_assert_same(array('table' => 'files', 'action' => 'recycle'), ws_change_audit_target('file', 'delete', $binned), 'file to the bin');

	pg_assert_same(array(), ws_change_audit_target('product', 'delete', $ok), 'pg_pb_delete_product() writes the product delete');
	pg_assert_same(array(), ws_change_audit_target('product', 'delete', $ok + array('binned' => false)), 'binned false is not binned');
	pg_assert_same(array(), ws_change_audit_target('offer', 'delete', $ok), '_pg_offer_delete() writes the offer delete');

	pg_assert_same(array('table' => 'page', 'action' => 'delete'), ws_change_audit_target('page', 'delete', $ok), 'a page deleted for good');
	pg_assert_same(array('table' => 'orders', 'action' => 'delete'), ws_change_audit_target('order', 'delete', $ok), 'an order deleted');
}

// An order moved to cancelled is a "cancel" and one taken out of cancelled a
// "restore", as the order screen and the API record them: the move
// pg_order_status_move() names, which the writer carries out. Any other
// change to an order, a cancelled one included, is an "update"; a move the
// rule refuses is never written, so its "update" is never recorded.
function test_workspace_audit_target_order_cancel_and_restore()
{
	$ok = array('ok' => true, 'error' => '', 'id' => 7);
	$cancel = array('table' => 'orders', 'action' => 'cancel');
	$restore = array('table' => 'orders', 'action' => 'restore');
	$update = array('table' => 'orders', 'action' => 'update');
	$complete = array('id' => 7, 'status' => 'complete');
	$cancelled = array('id' => 7, 'status' => 'cancelled');

	pg_assert_same($cancel, ws_change_audit_target('order', 'update', $ok, array('status' => 'cancelled'), $complete), 'complete to cancelled');
	pg_assert_same($cancel, ws_change_audit_target('order', 'update', $ok, array('status' => 'cancelled', 'cancellation_reason' => 'Stok yok'), array('id' => 7, 'status' => 'exported')), 'exported to cancelled, with a reason');

	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('notes' => 'Aranacak'), $cancelled), 'a note on a cancelled order');
	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('status' => 'cancelled', 'notes' => 'x'), $cancelled), 'cancelled stays cancelled');

	pg_assert_same($restore, ws_change_audit_target('order', 'update', $ok, array('status' => 'complete'), $cancelled), 'cancelled to complete');
	pg_assert_same($restore, ws_change_audit_target('order', 'update', $ok, array('status' => 'exported', 'notes' => 'x'), $cancelled), 'cancelled to exported');
	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('status' => 'incomplete'), $cancelled), 'back to a basket is no restore (the rule refuses it)');
	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('status' => 'cancelled'), array('id' => 7, 'status' => 'incomplete')), 'a basket is not cancelled (the rule refuses it)');
	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('status' => 'canceled'), $cancelled), 'the single-L spelling is no restore');
	pg_assert_same($cancel, ws_change_audit_target('order', 'update', $ok, array('status' => 'cancelled'), array('id' => 7, 'status' => '')), 'a lost status cancelled');

	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('status' => 'exported'), $complete), 'complete to exported');
	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('notes' => 'x'), $complete), 'a note');
	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok), 'no values or record given');
	pg_assert_same($update, ws_change_audit_target('order', 'update', $ok, array('status' => 'cancelled')), 'no record given');
	pg_assert_same(array(), ws_change_audit_target('order', 'update', array('ok' => false), array('status' => 'cancelled'), $complete), 'a refused cancellation');
	pg_assert_same(array('table' => 'offers', 'action' => 'update'), ws_change_audit_target('offer', 'update', $ok, array('status' => 'cancelled'), $complete), 'only orders are cancelled');
}

// A write that did not go through leaves no line.
function test_workspace_audit_target_needs_a_write_that_went_through()
{
	pg_assert_same(array(), ws_change_audit_target('product', 'update', array('ok' => false, 'error' => 'x', 'id' => 0)), 'refused');
	pg_assert_same(array(), ws_change_audit_target('page', 'delete', array('ok' => false, 'error' => 'x', 'id' => 7, 'binned' => true)), 'refused, though binned');
	pg_assert_same(array(), ws_change_audit_target('user', 'update', array()), 'no answer');
	pg_assert_same(array(), ws_change_audit_target('offer', 'update', array('ok' => null)), 'no ok');
}

// The wrapper hands the writer's answer back unchanged, switches the trail to
// the job only while the writer runs, and gives the switch back even when
// the writer throws.
function test_workspace_audit_write_gives_the_job_switch_back()
{
	$was = pg_audit_as_job(false);

	try {
		$done = ws_change_write(array('id' => 1), 'audit_probe', 'update', array('id' => 5), array(), false);

		pg_assert_same(array('ok' => true, 'error' => '', 'id' => 5, 'as_job' => false, 'action' => 'update'), $done, 'the answer as the writer gave it');

		$done = ws_change_write(array('id' => 1), 'audit_probe', 'update', array('id' => 5), array(), true);

		pg_assert_true($done['as_job'], 'recorded as the job while the writer ran');
		pg_assert_false(pg_audit_as_job(), 'switched back after it');

		$thrown = false;

		try {
			ws_change_write(array('id' => 1), 'audit_probe', 'update', array('id' => 5), array('throw' => true), true);
		} catch (RuntimeException $e) {
			$thrown = true;
		}

		pg_assert_true($thrown, 'the writer\'s exception goes on');
		pg_assert_false(pg_audit_as_job(), 'and the switch is back');

		pg_audit_as_job(true);
		ws_change_write(array('id' => 1), 'audit_probe', 'create', null, array(), true);
		pg_assert_true(pg_audit_as_job(), 'a switch already on stays on');
	} finally {
		pg_audit_as_job($was);
	}
}

// Every Workspace write goes through ws_change_write(): the writers are
// called nowhere else, and a bulk slice or a scheduled action is recorded as
// the job while a proposal applied or a drawer save is the person's.
function test_workspace_audit_every_caller_goes_through_the_wrapper()
{
	$expected = array(
		'changes.php'    => '$done = ws_change_write($viewer, $type, $action, $record, $values, false);',
		'quick_edit.php' => '$done = ws_change_write($viewer, $row[\'type\'], \'update\', $check[\'record\'], $values, false);',
		'bulk.php'       => '$done = ws_change_write($viewer, $type, $spec[\'action\'], $record, $values, true);',
		'scheduled.php'  => '$done = ws_change_write($viewer, $type, $change[\'action\'], $record, $values, true);',
	);

	foreach ($expected as $file => $call) {
		pg_assert_same(1, substr_count((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/' . $file), $call), $file);
	}

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/workspace/*.php') as $path) {
		$source = (string) file_get_contents($path);

		if (basename($path) === 'changes.php') {
			$start = strpos($source, "\nfunction ws_change_write(");
			$end = ($start === false) ? false : strpos($source, "\nfunction ", $start + 1);

			pg_assert_true(($start !== false) && ($end !== false), 'ws_change_write() is in changes.php');
			pg_assert_same(1, substr_count(substr($source, $start, $end - $start), "call_user_func('ws_change_write_' . \$type,"), 'the wrapper calls the writer');
			pg_assert_contains("ws_change_audit_target(\$type, \$action, is_array(\$done) ? \$done : array(), is_array(\$values) ? \$values : array(), \$record)", substr($source, $start, $end - $start), 'and names the line with the values and the record');

			$source = substr($source, 0, $start) . substr($source, $end);
		}

		pg_assert_false(strpos($source, "'ws_change_write_' ."), basename($path) . ' calls no writer by name');
	}
}
