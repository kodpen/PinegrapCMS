<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The barcode stock screens' Read button and the order screen's card refund
 * are sent once: the Read button is disabled exactly while its request runs
 * and a status line announces the result; the refund dialog lets its first
 * submit through, cancels any later one, marks itself busy and says so; the
 * refund branch runs under a per-order lock (pg_payment_refund_lock_name()).
 * The screens are checked by their source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function refund_once_source($file)
{
	return (string) file_get_contents(__DIR__ . '/../pinegrap/' . $file);
}

// The lock name carries the database (GET_LOCK names are server-wide) and the order.
function test_refund_once_lock_name()
{
	$name = pg_payment_refund_lock_name(42, 'shop@localhost');
	pg_assert_same('pg_refund_' . md5('shop@localhost') . '_42', $name);
	pg_assert_true(strlen($name) <= 64, 'GET_LOCK names stop at 64 characters');
	pg_assert_true($name !== pg_payment_refund_lock_name(42, 'other@localhost'), 'another database');
	pg_assert_true($name !== pg_payment_refund_lock_name(43, 'shop@localhost'), 'another order');
	pg_assert_same(pg_payment_refund_lock_name(42, 'shop@localhost'), pg_payment_refund_lock_name('42', 'shop@localhost'));
}

// Both barcode screens: an id on Read, disabled while the request runs and
// enabled again on every path; a polite status line filled with textContent.
function test_refund_once_barcode_read_button()
{
	foreach (array('barcode_increase_inventory.php', 'barcode_decrease_inventory.php') as $file) {
		$source = refund_once_source($file);
		pg_assert_contains('id="barcode_read_button"', $source, $file . ' Read id');
		pg_assert_contains('role="status"', $source, $file . ' status line');
		pg_assert_contains('id="barcode_status"', $source, $file . ' status id');
		pg_assert_contains('read_button.disabled = true;', $source, $file . ' disabled while it runs');
		pg_assert_contains('complete: function', $source, $file . ' enabled again on every path');
		pg_assert_contains('read_button.disabled = false;', $source, $file . ' enabled again');
		pg_assert_contains('.textContent = ', $source, $file . ' status set as text');
		pg_assert_true(strpos($source, 'onclick="read_manual()"') === false, $file . ' no inline handler');
	}
}

// The refund dialog: first submit through, later ones cancelled until the
// page leaves, aria-busy and a status line; the button is never disabled.
function test_refund_once_refund_dialog()
{
	$source = refund_once_source('view_order.php');
	pg_assert_contains('id="payment-refund-status"', $source, 'status line');
	pg_assert_contains('payment_refund_sent', $source, 'sent flag');
	pg_assert_contains('setAttribute("aria-busy", "true")', $source, 'busy dialog');
	pg_assert_contains('event.persisted', $source, 'reset on a page restored from the cache');
	pg_assert_contains('pg_payment_refund_lock_name(', $source, 'server lock');
	pg_assert_contains("GET_LOCK('\" . e(\$refund_lock) . \"', 0)", $source, 'lock taken without waiting');
}
