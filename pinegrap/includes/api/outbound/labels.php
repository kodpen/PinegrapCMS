<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Shipping labels a marketplace makes for its own carrier, kept with the
// order's other labels.
//
// Trendyol makes the label of a parcel that Trendyol Express or Aras Kargo
// carries on its account (its "common label"): ZPL for a thermal printer,
// asked for by the package's cargo tracking number (connector entries
// request_label, read_label and label_carrier). The operator asks for it on
// the Imported orders card (marketplace_settings.php, action common_label).
//
// It is kept the way a carrier label service's label is kept
// (includes/fn/shipment_labels.php): a shipment_labels row whose driver is the
// marketplace's key and whose reference and number are the cargo number, and
// the document under data/shipping_labels/. So the order screen lists it with
// Print Label, shipment_label.php hands it out, the orders list's bulk print
// names it among the labels that are not in the PDF, and the daily sweep (30
// days after the parcel's delivery, 90 days at the latest), an erasure and an
// order delete remove it on the same terms. The number is the parcel's own:
// the import recorded it (source marketplace), and its delivery is what the
// sweep reads.
//
// No label driver answers to a marketplace's key, so what the drivers do by
// reference is not offered for such a label: no Cancel Label (there is no call
// for it; the label goes with the package, which is cancelled on the
// marketplace), no tracking job (the order feed reports where the package
// is), and no reprint once the sweep has removed the file. An order with a
// label kept is not asked about again.

if (!defined('PG_INIT_LOADED')) {

	exit;

}

require_once(dirname(__FILE__) . '/connectors/base.php');
require_once(dirname(__FILE__) . '/sync.php');

// The most boxes one request names. The marketplace documents no ceiling; a
// package in more boxes is labelled in its seller panel.
function mp_label_max_boxes() {

	return 99;

}

// The number of boxes a form posted, or 0 when it is not one: a whole number
// from 1 to mp_label_max_boxes().
function mp_label_boxes($value) {

	$value = (is_string($value) || is_int($value)) ? trim((string)$value) : '';

	if (preg_match('/^[0-9]{1,3}$/D', $value) !== 1) {

		return 0;

	}

	$boxes = (int)$value;

	return ($boxes >= 1 && $boxes <= mp_label_max_boxes()) ? $boxes : 0;

}

// Whether an account's connector asks for and reads labels.
function mp_label_can($provider) {

	return mp_can($provider, 'request_label') && mp_can($provider, 'read_label');

}

// The cargo number a package's label is asked for by: the newest number the
// marketplace handed over for the address (pg_shipment_rows(), oldest first,
// source marketplace). A package moved to another carrier gets a new number,
// recorded as a row of its own, so the newest is the one in force. Pure.
function mp_label_cargo_number($rows) {

	$number = '';

	foreach ((array)$rows as $row) {

		if (is_array($row) && isset($row['source'], $row['number']) && ($row['source'] === 'marketplace')
			&& (trim((string)$row['number']) !== '')) {

			$number = trim((string)$row['number']);

		}

	}

	return $number;

}

// What the Imported orders card offers for one package. Pure.
//
//   print   the order has an active label of the marketplace and its file is
//           still kept
//   create  no label yet, the package is open on the marketplace, the account
//           ships with the marketplace's carrier and that carrier is one the
//           marketplace labels for
//   ''      neither; a label whose file the sweep removed is not made again
//
// $row is the map row, $stage mp_order_stage(), $label the order's active
// label of the marketplace (id, label_file, label_removed_at) or null.
// Answers offer and label_id.
function mp_label_offer($row, $stage, $label, $ships_itself, $carrier_ok) {

	if (is_array($label)) {

		$kept = ((string)$label['label_file'] !== '') && ((int)$label['label_removed_at'] === 0);

		return array('offer' => $kept ? 'print' : '', 'label_id' => (int)$label['id']);

	}

	$open = is_array($row) && isset($row['status'], $row['order_id']) && ($row['status'] === 'imported') && ((int)$row['order_id'] > 0)
		&& ($stage === 'open');

	return array('offer' => ($open && !$ships_itself && $carrier_ok) ? 'create' : '', 'label_id' => 0);

}

// The offers of the packages the Imported orders card shows, by order id
// (mp_label_offer()). Two queries for the whole list: the orders' labels of
// this marketplace, and the shipping method code of each order's first
// address, which an import from the marketplace's carrier fills with that
// carrier's name.
function mp_label_card($account, $rows) {

	$provider = isset($account['provider']) ? (string)$account['provider'] : '';

	$offers = array();

	if (!mp_label_can($provider) || !pg_shipment_labels_ready()) {

		return $offers;

	}

	$order_ids = array();

	foreach ((array)$rows as $row) {

		if (($row['status'] === 'imported') && ((int)$row['order_id'] > 0)) {

			$order_ids[(int)$row['order_id']] = (int)$row['order_id'];

		}

	}

	if (!$order_ids) {

		return $offers;

	}

	$in = "'" . implode("','", $order_ids) . "'";

	$labels = array();

	foreach ((array) db_items("SELECT id, order_id, label_file, label_removed_at FROM shipment_labels
		WHERE (driver = '" . e($provider) . "') AND (state = 'active') AND (is_return = '0') AND (order_id IN (" . $in . "))
		ORDER BY id ASC") as $label) {

		$labels[(int)$label['order_id']] = $label;

	}

	// Newest first, so the order's first address is the one left standing.
	$methods = array();

	foreach ((array) db_items("SELECT order_id, shipping_method_code FROM ship_tos
		WHERE order_id IN (" . $in . ") ORDER BY id DESC") as $ship_to) {

		$methods[(int)$ship_to['order_id']] = (string)$ship_to['shipping_method_code'];

	}

	$ships_itself = mp_account_ships_itself($account);

	foreach ((array)$rows as $row) {

		$order_id = (int)$row['order_id'];

		if (!isset($order_ids[$order_id])) {

			continue;

		}

		$offers[$order_id] = mp_label_offer($row, mp_order_stage($account, $row),
			isset($labels[$order_id]) ? $labels[$order_id] : null, $ships_itself,
			isset($methods[$order_id]) && mp_label_carrier_ok($provider, $methods[$order_id]));

	}

	return $offers;

}

// Whether the marketplace labels a parcel of the carrier a shipping method
// code names. A connector that cannot tell leaves it to its answer.
function mp_label_carrier_ok($provider, $method_code) {

	return !mp_can($provider, 'label_carrier') || (mp_call($provider, 'label_carrier', array((string)$method_code)) === true);

}

// The lock that lets one request for a package's label run at a time.
function mp_label_lock_name($map_id) {

	$database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string)db_value("SELECT DATABASE()");

	return 'pg_mp_label_' . md5($database) . '_' . (int)$map_id;

}

// The shipment_labels row of a label the marketplace made, through the row the
// carrier label services' labels get (pg_shipment_label_row()). Its driver is
// the marketplace's key; the cargo number is its reference (unique per driver)
// and the parcel's number. Pure.
function mp_label_row($order_id, $ship_to_id, $provider, $number, $format, $file, $user_id, $now) {

	return pg_shipment_label_row($order_id, $ship_to_id, $provider,
		array('reference' => $number, 'tracking_number' => $number, 'barcode' => '', 'label_format' => $format),
		$file, $user_id, $now);

}

// Ask the marketplace for the shipping label of an imported package and keep
// it with the order's labels.
//
// A label already kept for the order is answered as it is, without a call. A
// label asked for before (here or in the seller panel) is read first and taken
// as it is. Otherwise a package nobody accepted yet is accepted first (their
// advice is to ask once it is being prepared), the label is asked for, and
// read up to three times two seconds apart, because it is made after the
// request. One run at a time per package, under a lock.
//
// Answers ok, existing (the label was there already), pending (asked for, not
// made yet: the operator presses again), error (the sentence shown, for a
// pending label too) and label_id.
function mp_label_create($account, $map_id, $boxes, $user_id) {

	$out = array('ok' => false, 'existing' => false, 'pending' => false, 'error' => '', 'label_id' => 0);

	$provider = isset($account['provider']) ? (string)$account['provider'] : '';

	if (!mp_label_can($provider)) {

		$out['error'] = lang(array('string' => '{var:1} cannot do this.', 'vars' => array(mp_provider_name($provider))));

		return $out;

	}

	if (!pg_shipment_labels_ready() || !pg_shipment_rows_ready()) {

		$out['error'] = lang('Run the software update first: the table for shipping labels is missing.');

		return $out;

	}

	if (mp_account_ships_itself($account)) {

		$out['error'] = lang(array(
			'string' => 'This account ships with the shop\'s own carrier, so {var:1} makes no label for its parcels.',
			'vars'   => array(mp_provider_name($provider))
		));

		return $out;

	}

	$boxes = (int)$boxes;

	if ($boxes < 1 || $boxes > mp_label_max_boxes()) {

		$out['error'] = lang(array('string' => 'Give the number of boxes as a whole number from 1 to {var:1}.', 'vars' => array(mp_label_max_boxes())));

		return $out;

	}

	$lock = mp_label_lock_name($map_id);

	if ((int)db_value("SELECT GET_LOCK('" . e($lock) . "', 0)") !== 1) {

		$out['error'] = lang('The label of this order is being asked for right now. Look again in a moment.');

		return $out;

	}

	$out = mp_label_create_locked($account, (int)$map_id, $boxes, $user_id);

	db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

	return $out;

}

// mp_label_create() under the package's lock.
function mp_label_create_locked($account, $map_id, $boxes, $user_id) {

	$out = array('ok' => false, 'existing' => false, 'pending' => false, 'error' => '', 'label_id' => 0);

	$provider = (string)$account['provider'];

	$name = mp_provider_name($provider);

	// Read under the lock: a press that finished meanwhile has kept its label.
	$map = db_item("SELECT * FROM marketplace_order_map
		WHERE (id = '" . e($map_id) . "') AND (account_id = '" . e((int)$account['id']) . "')
		  AND (status = 'imported') AND (order_id > 0)");

	if (!$map) {

		$out['error'] = lang('That order is no longer in the imported orders.');

		return $out;

	}

	$order_id = (int)$map['order_id'];

	$order_number = (string)$map['remote_order_id'];

	$label = db_item("SELECT id FROM shipment_labels
		WHERE (driver = '" . e($provider) . "') AND (order_id = '" . e($order_id) . "') AND (state = 'active') AND (is_return = '0')
		ORDER BY id DESC
		LIMIT 1");

	if ($label) {

		$out['ok'] = true;

		$out['existing'] = true;

		$out['label_id'] = (int)$label['id'];

		return $out;

	}

	if (mp_order_stage($account, $map) !== 'open') {

		$out['error'] = lang(array(
			'string' => 'Order {var:1} is no longer waiting to be shipped on {var:2}, so no label is asked for.',
			'vars'   => array($order_number, $name)
		));

		return $out;

	}

	$ship_to = db_item("SELECT id, shipping_method_code FROM ship_tos WHERE order_id = '" . e($order_id) . "' ORDER BY id ASC LIMIT 1");

	if (!$ship_to) {

		$out['error'] = lang(array('string' => 'Order {var:1} has no shipping address, so it has no parcel to label.', 'vars' => array($order_number)));

		return $out;

	}

	if (!mp_label_carrier_ok($provider, $ship_to['shipping_method_code'])) {

		$out['error'] = lang(array(
			'string' => '{var:1} makes no label for a parcel {var:2} carries. Label it with that carrier.',
			'vars'   => array($name, (string)$ship_to['shipping_method_code'])
		));

		return $out;

	}

	$number = mp_label_cargo_number(pg_shipment_rows((int)$ship_to['id']));

	if ($number === '') {

		$out['error'] = lang(array(
			'string' => '{var:1} has not given order {var:2} a cargo tracking number yet. Try again after the next order read.',
			'vars'   => array($name, $order_number)
		));

		return $out;

	}

	$read = mp_call($provider, 'read_label', array($account, $number));

	if (mp_label_read_done($read)) {

		return mp_label_keep($account, $map, (int)$ship_to['id'], $number, $read['data'], $user_id);

	}

	// Their outage or rate limit: nothing is asked for while it lasts.
	if (!$read['ok'] && !empty($read['retry'])) {

		$out['error'] = lang(array('string' => 'The label of order {var:1} could not be read from {var:2}: {var:3}', 'vars' => array($order_number, $name, $read['error'])));

		return $out;

	}

	// A package nobody accepted yet is accepted first, as the own-carrier
	// shipping pass does (mp_sync_ship_pending()).
	if (((string)$map['remote_status'] === 'Created') && empty($map['approved']) && mp_can($provider, 'approve_lines')) {

		$package = mp_sync_package_from_row($map);

		$line_ids = array();

		foreach ($package['lines'] as $line) {

			if (isset($line['remote_line_id']) && ((string)$line['remote_line_id'] !== '')) {

				$line_ids[] = (string)$line['remote_line_id'];

			}

		}

		$accepted = mp_call($provider, 'approve_lines', array($account, $line_ids, $package));

		if (!$accepted['ok']) {

			$out['error'] = lang(array(
				'string' => '{var:1} did not accept order {var:2}, so no label was asked for: {var:3}',
				'vars'   => array($name, $order_number, $accepted['error'])
			));

			return $out;

		}

		db("UPDATE marketplace_order_map
			SET remote_status = 'Picking', approved = 1, updated_timestamp = UNIX_TIMESTAMP()
			WHERE id = '" . e($map_id) . "'");

	}

	$requested = mp_call($provider, 'request_label', array($account, $number, $boxes));

	if (!$requested['ok'] && !empty($requested['retry'])) {

		$out['error'] = lang(array('string' => '{var:1} did not take the label request of order {var:2}: {var:3}', 'vars' => array($name, $order_number, $requested['error'])));

		return $out;

	}

	// The label is made after the request, so it is read a few times. A
	// refused request is read once: the label of an earlier request may be
	// what it refused for.
	$tries = $requested['ok'] ? 3 : 1;

	for ($try = 0; $try < $tries; $try++) {

		if ($try > 0) {

			sleep(2);

		}

		$read = mp_call($provider, 'read_label', array($account, $number));

		if (mp_label_read_done($read)) {

			return mp_label_keep($account, $map, (int)$ship_to['id'], $number, $read['data'], $user_id);

		}

		if (!$read['ok'] && !empty($read['retry'])) {

			break;

		}

	}

	if (!$requested['ok']) {

		$out['error'] = lang(array('string' => '{var:1} refused the label of order {var:2}: {var:3}', 'vars' => array($name, $order_number, $requested['error'])));

		return $out;

	}

	$out['pending'] = true;

	$out['error'] = lang(array(
		'string' => '{var:1} took the label request of order {var:2}, but the label is not ready yet. Press Get label again in a minute.',
		'vars'   => array($name, $order_number)
	));

	return $out;

}

// Whether a read_label answer holds a label. Pure.
function mp_label_read_done($read) {

	return is_array($read) && !empty($read['ok']) && isset($read['data']['label']) && is_string($read['data']['label'])
		&& ($read['data']['label'] !== '');

}

// Keep a label the marketplace made: its file under data/shipping_labels/ and
// its shipment_labels row. Without the file nothing is written: no driver
// could hand the label out again.
function mp_label_keep($account, $map, $ship_to_id, $number, $label, $user_id) {

	$out = array('ok' => false, 'existing' => false, 'pending' => false, 'error' => '', 'label_id' => 0);

	$provider = (string)$account['provider'];

	$order_number = (string)$map['remote_order_id'];

	$format = isset($label['format']) ? (string)$label['format'] : '';

	$document = (string)$label['label'];

	$file = pg_shipment_label_file_name((int)$map['order_id'], (int)$ship_to_id, $provider, $number, $format);

	if (($file === '') || !pg_label_document_valid($document, $format) || !pg_shipment_label_store($file, $document)) {

		$out['error'] = lang(array(
			'string' => 'The label of order {var:1} could not be kept on this server. Check that the folder data/shipping_labels can be written to, then try again.',
			'vars'   => array($order_number)
		));

		return $out;

	}

	$row = mp_label_row((int)$map['order_id'], (int)$ship_to_id, $provider, $number, $format, $file, (int)$user_id, time());

	$columns = array_keys($row);

	$saved = db_q("INSERT INTO shipment_labels (`" . implode('`, `', $columns) . "`)
		VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")", array_values($row));

	if (!$saved) {

		@unlink(pg_shipment_label_path($file));

		$out['error'] = lang(array('string' => 'The label of order {var:1} could not be saved: {var:2}', 'vars' => array($order_number, (string)mysqli_error(db::$con))));

		return $out;

	}

	$out['ok'] = true;

	$out['label_id'] = (int)mysqli_insert_id(db::$con);

	log_activity(lang(array(
		'string' => 'A shipping label was created with {var:1} for order {var:2}: {var:3}',
		'vars'   => array(mp_provider_name($provider), (string)db_value("SELECT order_number FROM orders WHERE id = '" . e((int)$map['order_id']) . "'"), $number)
	)));

	return $out;

}
