<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for keeping a marketplace's shipping label with the order's labels
 * (includes/api/outbound/labels.php): the boxes a form posts, the cargo number
 * a label is asked for by, what the Imported orders card offers, and the
 * shipment_labels row and file name the label gets, which the order screen,
 * shipment_label.php and the daily sweep read like any carrier label's. The
 * request itself needs a database and Trendyol, and is not run here; the
 * connector's calls are tested in marketplace_trendyol_common_label_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/labels.php');

function marketplace_labels_test_map($over = array())
{
	return array_merge(array(
		'id' => 12, 'account_id' => 7, 'order_id' => 501, 'status' => 'imported',
		'remote_order_id' => '10654411111', 'remote_package_id' => '3330111111', 'remote_status' => 'Picking', 'approved' => 1,
	), $over);
}

function test_marketplace_labels_boxes_from_the_form()
{
	foreach (array('1' => 1, '2' => 2, ' 3 ' => 3, '99' => 99, '07' => 7) as $posted => $boxes) {
		pg_assert_same($boxes, mp_label_boxes((string) $posted), var_export($posted, true));
	}

	pg_assert_same(4, mp_label_boxes(4), 'a number');

	foreach (array('', '0', '100', '-1', '2.5', '1e2', 'abc', '1 2', array('2'), null, true) as $posted) {
		pg_assert_same(0, mp_label_boxes($posted), var_export($posted, true));
	}

	pg_assert_same(99, mp_label_max_boxes());
}

function test_marketplace_labels_cargo_number_is_the_newest_marketplace_parcel()
{
	$rows = array(
		array('id' => 1, 'number' => '7280027504111111', 'source' => 'marketplace'),
		array('id' => 2, 'number' => 'YK123456789', 'source' => 'panel'),
		array('id' => 3, 'number' => ' 7330029292541813 ', 'source' => 'marketplace'),
		array('id' => 4, 'number' => 'GLV-55', 'source' => 'carrier'),
	);

	pg_assert_same('7330029292541813', mp_label_cargo_number($rows), 'a number given since, for another carrier');
	pg_assert_same('7280027504111111', mp_label_cargo_number(array_slice($rows, 0, 2)));
	pg_assert_same('', mp_label_cargo_number(array($rows[1], $rows[3])), 'none from the marketplace');
	pg_assert_same('', mp_label_cargo_number(array(array('number' => '', 'source' => 'marketplace'))), 'an empty number');
	pg_assert_same('', mp_label_cargo_number(array(array('number' => '7280027504111111'))), 'a row without its source');
	pg_assert_same('', mp_label_cargo_number('nothing'));
}

function test_marketplace_labels_offer_print_a_kept_label()
{
	$kept = array('id' => 40, 'label_file' => '501_77_trendyol_0123456789abcdef.zpl', 'label_removed_at' => 0);

	pg_assert_same(array('offer' => 'print', 'label_id' => 40), mp_label_offer(marketplace_labels_test_map(), 'open', $kept, false, true));
	pg_assert_same('print', mp_label_offer(marketplace_labels_test_map(), 'shipped', $kept, true, false)['offer'],
		'a label made is printed whatever happened since');

	$swept = array_merge($kept, array('label_removed_at' => 1793000000));
	pg_assert_same(array('offer' => '', 'label_id' => 40), mp_label_offer(marketplace_labels_test_map(), 'open', $swept, false, true),
		'the sweep removed it: not asked for again');

	pg_assert_same('', mp_label_offer(marketplace_labels_test_map(), 'open', array_merge($kept, array('label_file' => '')), false, true)['offer']);
}

function test_marketplace_labels_offer_create_only_for_an_open_package()
{
	pg_assert_same(array('offer' => 'create', 'label_id' => 0), mp_label_offer(marketplace_labels_test_map(), 'open', null, false, true));
	pg_assert_same('create', mp_label_offer(marketplace_labels_test_map(array('remote_status' => 'Created', 'approved' => 0)), 'open', null, false, true)['offer'],
		'a package not accepted yet is accepted on the way');

	foreach (array('shipped', 'delivered', 'cancelled') as $stage) {
		pg_assert_same('', mp_label_offer(marketplace_labels_test_map(), $stage, null, false, true)['offer'], $stage);
	}

	pg_assert_same('', mp_label_offer(marketplace_labels_test_map(), 'open', null, true, true)['offer'], 'the account ships itself');
	pg_assert_same('', mp_label_offer(marketplace_labels_test_map(), 'open', null, false, false)['offer'], 'a carrier they do not label');
	pg_assert_same('', mp_label_offer(marketplace_labels_test_map(array('status' => 'failed')), 'open', null, false, true)['offer']);
	pg_assert_same('', mp_label_offer(marketplace_labels_test_map(array('order_id' => 0)), 'open', null, false, true)['offer'],
		'no order was written');
}

function test_marketplace_labels_row_is_a_shipment_label_row()
{
	$file = pg_shipment_label_file_name(501, 77, 'trendyol', '7280027504111111', 'zpl');

	pg_assert_true(pg_shipment_label_file_safe($file), 'the file name the sweep and shipment_label.php accept: ' . $file);
	pg_assert_true(pg_shipment_label_path($file) !== '');
	pg_assert_same(1, preg_match('/^501_77_trendyol_[0-9a-f]{16}\.zpl$/', $file));

	$row = mp_label_row(501, 77, 'trendyol', '7280027504111111', 'zpl', $file, 3, 1793000000);

	pg_assert_same(501, $row['order_id']);
	pg_assert_same(77, $row['ship_to_id']);
	pg_assert_same('7280027504111111', $row['number'], 'the parcel the import recorded, whose delivery the sweep reads');
	pg_assert_same('trendyol', $row['driver']);
	pg_assert_same('7280027504111111', $row['reference'], 'what the label is asked for by');
	pg_assert_same('', $row['barcode']);
	pg_assert_same('zpl', $row['label_format']);
	pg_assert_same($file, $row['label_file']);
	pg_assert_same('active', $row['state']);
	pg_assert_same(0, $row['is_return']);
	pg_assert_same(3, $row['user_id']);
	pg_assert_same(1793000000, $row['created_at']);
	pg_assert_false(array_key_exists('order_return_id', $row), 'no return request: saved before 11.0.126 too');

	pg_assert_false(pg_label_driver('trendyol') !== null, 'no label driver answers to the key: no cancel, tracking or reprint by reference');
	pg_assert_false(pg_label_can('trendyol', 'cancel'));

	// So the order screen draws Print Label and no Cancel Label for it.
	$order_screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_order.php');
	pg_assert_contains("((\$shipment_label_cancelled || !pg_label_can(\$shipment_label['driver'], 'cancel')) ? '' : '<button type=\"submit\" form=\"shipment-label-form\" name=\"label_request\" value=\"cancel-'", $order_screen);
}

function test_marketplace_labels_read_done_needs_a_label()
{
	pg_assert_true(mp_label_read_done(mp_ok(array('label' => '^XA^XZ', 'format' => 'zpl', 'count' => 1))));
	pg_assert_false(mp_label_read_done(mp_ok(array('label' => '', 'format' => 'zpl', 'count' => 0))), 'not made yet');
	pg_assert_false(mp_label_read_done(mp_error('Trendyol answered 400.')));
	pg_assert_false(mp_label_read_done(mp_ok(array())));
	pg_assert_false(mp_label_read_done(null));
}

function test_marketplace_labels_carrier_through_the_connector()
{
	pg_assert_true(mp_label_can('trendyol'));
	pg_assert_false(mp_label_can('n11'));

	pg_assert_true(mp_label_carrier_ok('trendyol', 'Trendyol Express Marketplace'));
	pg_assert_true(mp_label_carrier_ok('trendyol', 'Aras Kargo Marketplace'));
	pg_assert_false(mp_label_carrier_ok('trendyol', 'Yurtiçi Kargo Marketplace'));
	pg_assert_true(mp_label_carrier_ok('n11', 'Yurtiçi Kargo'), 'a connector that cannot tell leaves it to its answer');
}

// The screen's parts, pinned in its source: the action, the card's offers
// and the line under each package.
function test_marketplace_labels_screen_asks_and_prints()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/marketplace_settings.php');

	pg_assert_contains("require_once(dirname(__FILE__) . '/includes/api/outbound/labels.php');", $source);
	pg_assert_contains("if (\$action === 'common_label' && \$account) {", $source);
	pg_assert_contains("mp_label_create(\$account, \$map_id, mp_label_boxes(isset(\$_POST['boxes']) ? \$_POST['boxes'] : ''), (int)\$user['id']);", $source);
	pg_assert_contains('$order_labels = mp_label_card($selected, $orders);', $source);
	pg_assert_contains("mp_settings_label_line(\$selected_id, \$row, \$order_labels[(int)\$row['order_id']]", $source);
	pg_assert_contains('<input type="hidden" name="mp_action" value="common_label">', $source);
	pg_assert_contains('href="shipment_label.php?id=\' . (int)$offer[\'label_id\'] . \'"', $source, 'printed through the order screen\'s door');

	$action = substr($source, (int) strpos($source, "if (\$action === 'common_label'"), 2500);
	pg_assert_contains('add_warning(h($made[\'error\']))', $action, 'the marketplace\'s words are escaped');
	pg_assert_false(strpos($action, 'last_error') !== false && strpos($action, 'SET last_error') !== false, 'a refusal is not kept on the map row');
}
