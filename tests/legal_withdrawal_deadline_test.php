<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the last day of an order's right of withdrawal in
 * includes/fn/legal_documents.php: the deadline of the last delivery, when it
 * is an estimate, when none is given, how parcel arrivals count, and the line
 * the order view prints.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// One delivery with a known date gives its own deadline, not an estimate.
function test_legal_withdrawal_single_known_delivery()
{
	$window = array('delivered' => '2026-10-20', 'estimated' => false, 'deadline' => '2026-11-03', 'open' => true);

	pg_assert_same(
		array('deadline' => '2026-11-03', 'estimated' => false, 'open' => true),
		pg_legal_withdrawal_deadline(array($window))
	);
}

// Goods of one order delivered separately: the period runs from the last delivery.
function test_legal_withdrawal_latest_delivery_wins()
{
	$early = pg_return_window(array('delivered' => '2026-10-02'), '2026-10-20');
	$late = pg_return_window(array('delivered' => '2026-10-10'), '2026-10-20');

	pg_assert_same('2026-10-16', $early['deadline'], 'the first delivery alone has passed');
	pg_assert_false($early['open'], 'its window is closed');

	$out = pg_legal_withdrawal_deadline(array($early, $late));

	pg_assert_same('2026-10-24', $out['deadline'], 'the later delivery sets the date');
	pg_assert_true($out['open'], 'still open on the later delivery');
	pg_assert_same($out, pg_legal_withdrawal_deadline(array($late, $early)), 'the order of the deliveries does not matter');
}

// Any estimated delivery makes the date an estimate, even when a known one is later.
function test_legal_withdrawal_estimate_spreads()
{
	$known = array('delivered' => '2026-10-20', 'estimated' => false, 'deadline' => '2026-11-03', 'open' => true);
	$planned = pg_return_window(array('ship_date' => '2026-10-10', 'delivery_date' => ''), '2026-10-20');

	pg_assert_true($planned['estimated'], 'a ship date alone is an estimate');

	$out = pg_legal_withdrawal_deadline(array($known, $planned));

	pg_assert_same('2026-11-03', $out['deadline'], 'the known delivery is the later one');
	pg_assert_true($out['estimated'], 'the planned one may still arrive later');
}

// No delivery, or a delivery with no date at all, gives no deadline rather than one too early.
function test_legal_withdrawal_unknown_gives_none()
{
	$known = array('delivered' => '2026-10-20', 'estimated' => false, 'deadline' => '2026-11-03', 'open' => true);
	$none = pg_return_window(array('ship_date' => '0000-00-00', 'delivery_date' => ''), '2026-10-20');

	pg_assert_same(null, $none, 'no date is known');
	pg_assert_same(null, pg_legal_withdrawal_deadline(array()), 'no delivery');
	pg_assert_same(null, pg_legal_withdrawal_deadline(array($known, $none)), 'one delivery has no date');
	pg_assert_same(null, pg_legal_withdrawal_deadline(array(array('deadline' => 'soon', 'open' => true))), 'not a date');
}

// The order view's line: the date, the estimate note, or that the right has ended.
function test_legal_withdrawal_text()
{
	$open = array('deadline' => '2026-11-03', 'estimated' => false, 'open' => true);
	$estimated = array('deadline' => '2026-11-03', 'estimated' => true, 'open' => true);
	$ended = array('deadline' => '2026-10-16', 'estimated' => true, 'open' => false);

	pg_assert_same(lang(array('string' => 'Last day of the right of withdrawal: {var:1}', 'vars' => array('03 Kasım 2026'))), pg_legal_withdrawal_text($open, '03 Kasım 2026'));
	pg_assert_same(lang(array('string' => 'Last day of the right of withdrawal: {var:1} (estimated; the 14 days run from delivery).', 'vars' => array('3/11/2026'))), pg_legal_withdrawal_text($estimated, '3/11/2026'));
	pg_assert_same(lang(array('string' => 'The right of withdrawal ended on {var:1}.', 'vars' => array('16/10/2026'))), pg_legal_withdrawal_text($ended, '16/10/2026'));
	pg_assert_same('', pg_legal_withdrawal_text(null, '3/11/2026'), 'no deadline');
	pg_assert_same('', pg_legal_withdrawal_text($open, ' '), 'no date text');
}

// The fallback card prints the line under the links, escaped, and only with a kept copy.
function test_legal_withdrawal_in_order_view_block()
{
	$urls = array('preinfo' => '', 'contract' => '/p/legal_document.php?order_id=7&type=contract');

	pg_assert_contains('<p class="small text-muted mt-2 mb-0">a &lt;b&gt;</p>', pg_legal_documents_order_view_block($urls, 'a <b>'));
	pg_assert_false(strpos(pg_legal_documents_order_view_block($urls), '<p class="small') !== false, 'no line without a deadline');
	pg_assert_same('', pg_legal_documents_order_view_block(array('preinfo' => '', 'contract' => ''), 'a'), 'no card without a kept copy');
}

// Every parcel arrived after the expected date: the known arrival sets the date, not the estimate.
function test_legal_withdrawal_known_delivery_after_estimate()
{
	$arrived = pg_return_parcel_delivery(array('2026-10-25'));
	$window = pg_return_window(array(
		'delivered'        => $arrived['delivered'],
		'parcel_delivered' => $arrived['latest'],
		'ship_date'        => '2026-10-10',
		'delivery_date'    => '2026-10-14',
	), '2026-10-30');

	pg_assert_same(
		array('deadline' => '2026-11-08', 'estimated' => false, 'open' => true),
		pg_legal_withdrawal_deadline(array($window))
	);
}

// Some parcels arrived and others not: the latest arrival moves the estimate later.
function test_legal_withdrawal_partial_arrival()
{
	$arrived = pg_return_parcel_delivery(array('2026-10-25', ''));
	$window = pg_return_window(array(
		'delivered'        => $arrived['delivered'],
		'parcel_delivered' => $arrived['latest'],
		'ship_date'        => '2026-10-10',
		'delivery_date'    => '2026-10-14',
	), '2026-10-30');

	pg_assert_same(
		array('deadline' => '2026-11-08', 'estimated' => true, 'open' => true),
		pg_legal_withdrawal_deadline(array($window))
	);
}

// The order's deliveries go through the returns flow's state, so parcel arrivals reach the date.
function test_legal_withdrawal_reads_parcel_arrivals()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/legal_documents.php');

	pg_assert_contains('$state = pg_return_delivery_state($order_id, (int) $ship_to[\'id\'], $today);', $source, 'each delivery is read with its parcels');
	pg_assert_contains('$windows[] = is_array($state) ? $state[\'window\'] : null;', $source, 'a missing delivery gives no date');
	pg_assert_false(strpos($source, "'delivered'     => '',") !== false, 'no delivery is taken as unknown');
}

// The order view gives the token and its flags, never in the receipt e-mail.
function test_legal_withdrawal_order_view_wiring()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');

	pg_assert_contains("empty(\$sw_ctx['email']) ? pg_legal_withdrawal_of_order((int)\$order['id']) : null", $source, 'none in the e-mail');
	pg_assert_contains("'__withdrawal_deadline'     => h(\$withdrawal_date)", $source, 'the token is escaped');
	pg_assert_contains("'has_withdrawal_deadline'       => \$withdrawal_date !== ''", $source, 'the flag');
	pg_assert_contains("strpos(\$placed_json, '__withdrawal_deadline') === false", $source, 'a bound token keeps the line out of the card');
}
