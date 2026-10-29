<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipment labels - the labels a carrier label service opened for an order's
 * address, kept beside its parcels.
 *
 * One shipment_labels row per label: the service's reference (its own id of
 * the shipment, what label(), cancel() and track() are asked with), the
 * barcode, the driver, the parcel it belongs to by (ship_to_id, number) and
 * the document's file name. The parcel row itself stays the same whichever
 * service opened it: the tracking number reaches shipping_tracking_numbers
 * only through pg_shipment_record() (includes/fn/shipping.php), and a row
 * here outlives a parcel row pruned or renumbered.
 *
 * The document is a file under data/shipping_labels/, which the server rules
 * close to the web; only shipment_label.php hands it out, to whoever may open
 * the order. It holds the recipient's address, so it is kept only as long as
 * printing needs it: the daily sweep removes it 30 days after the parcel was
 * delivered or the label cancelled and 90 days after it was made at the
 * latest, and marks the row (label_removed_at). The row stays with the order;
 * a reprint after that asks the service for the label again by reference.
 * An erasure removes the files and keeps the rows (the reference and the
 * barcode name nobody); deleting the order removes both.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// A label's file is kept this many days after the parcel was delivered or
// the label cancelled...
define('PG_SHIPMENT_LABEL_KEEP_DAYS', 30);

// ...and never longer than this many days after it was made.
define('PG_SHIPMENT_LABEL_KEEP_DAYS_MAX', 90);

// Rows the daily sweep looks at in one run; the rest wait for the next day.
define('PG_SHIPMENT_LABEL_SWEEP_LIMIT', 500);

/**
 * The states a label row takes.
 *
 * @return array
 */
function pg_shipment_label_states()
{
    return array('active', 'cancelled');
}

/**
 * Whether the shipment_labels table exists (files land before the upgrade).
 *
 * @return bool
 */
function pg_shipment_labels_ready()
{
    return pg_schema_has('shipment_labels', 'label_removed_at');
}

/**
 * The folder the documents are kept in.
 *
 * @return string
 */
function pg_shipment_label_dir()
{
    return PG_FUNCTIONS_DIR . '/data/shipping_labels';
}

/**
 * The file name of a label's document: the order, the address, the driver
 * and a digest of the reference, so two labels never share a file and
 * nothing the service sent is written into a path. Pure.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $driver    a valid driver key
 * @param string $reference the service's reference
 * @param string $format    pdf or zpl
 * @return string '' when a part cannot be used
 */
function pg_shipment_label_file_name($order_id, $ship_to_id, $driver, $reference, $format)
{
    $order_id = (int) $order_id;
    $ship_to_id = (int) $ship_to_id;
    $reference = (string) $reference;

    if (($order_id <= 0) || ($ship_to_id < 0) || !pg_label_key_valid($driver) || ($reference === '')
        || !in_array($format, pg_label_formats(), true)) {
        return '';
    }

    return $order_id . '_' . $ship_to_id . '_' . $driver . '_' . substr(sha1($driver . "\n" . $reference), 0, 16) . '.' . $format;
}

/**
 * Whether a stored file name is one pg_shipment_label_file_name() could have
 * made: no folder, no dot but the extension's. Pure.
 *
 * @param mixed $name
 * @return bool
 */
function pg_shipment_label_file_safe($name)
{
    return is_string($name)
        && (strlen($name) <= 100)
        && (preg_match('/^[0-9]+_[0-9]+_[a-z][a-z0-9_]*_[0-9a-f]{16}\.(pdf|zpl)$/', $name) === 1);
}

/**
 * The full path of a stored file name, '' for a name that is not safe.
 *
 * @param string $name
 * @return string
 */
function pg_shipment_label_path($name)
{
    return pg_shipment_label_file_safe($name) ? pg_shipment_label_dir() . '/' . $name : '';
}

/**
 * The carrier an address ships with: the one its shipping method names, else
 * the one the method code the customer chose spells. Pure.
 *
 * @param string $method_carrier shipping_methods.carrier
 * @param string $method_code    ship_tos.shipping_method_code
 * @return string carrier key, '' when neither names one
 */
function pg_shipment_label_carrier($method_carrier, $method_code)
{
    $method_carrier = trim((string) $method_carrier);

    if (pg_shipping_carrier_exists($method_carrier)) {
        return $method_carrier;
    }

    return pg_shipping_method_carrier((string) $method_code);
}

/**
 * The driver that opens the label of a carrier: the first that ships with
 * it; for an address whose carrier is not known, the first driver (the
 * service then picks the carrier). A carrier no driver ships with gets none,
 * so a parcel is never handed to another carrier than the one the customer
 * chose. Pure.
 *
 * @param string $carrier
 * @param array  $drivers key => list of carrier keys (pg_shipment_label_drivers())
 * @return string driver key, '' when none fits
 */
function pg_shipment_label_driver_pick($carrier, $drivers)
{
    $carrier = (string) $carrier;

    foreach ((array) $drivers as $key => $carriers) {
        if (($carrier === '') || in_array($carrier, (array) $carriers, true)) {
            return (string) $key;
        }
    }

    return '';
}

/**
 * The drivers that are switched on, set up and able to open a shipment, with
 * the carriers each ships with.
 *
 * @return array key => list of carrier keys
 */
function pg_shipment_label_drivers()
{
    $drivers = array();

    foreach (array_keys(pg_label_drivers()) as $key) {
        if (pg_label_can($key, 'create') && pg_label_ready($key)) {
            $info = pg_label_info($key);
            $drivers[$key] = $info['carriers'];
        }
    }

    return $drivers;
}

/**
 * When a label's file is due to go: 30 days after the parcel was delivered
 * or the label cancelled, whichever came first, and 90 days after the label
 * was made at the latest. Pure.
 *
 * @param int $created_at
 * @param int $delivered_at  0 while the parcel is on its way
 * @param int $cancelled_at  0 for a label not cancelled
 * @return int unix time
 */
function pg_shipment_label_expires_at($created_at, $delivered_at, $cancelled_at)
{
    $day = 86400;
    $expires = (int) $created_at + (PG_SHIPMENT_LABEL_KEEP_DAYS_MAX * $day);

    foreach (array((int) $delivered_at, (int) $cancelled_at) as $ended) {
        if ($ended > 0) {
            $expires = min($expires, $ended + (PG_SHIPMENT_LABEL_KEEP_DAYS * $day));
        }
    }

    return $expires;
}

/**
 * The create() shipment of an address, from its rows. Weights and sizes are
 * kept in pounds and inches (products.weight, length, width, height) and
 * go to the service in kilograms and centimetres: the weight of every line,
 * the largest length, width and height of a line. A parcel with no weight
 * goes as 1 kg and a missing size as 10 cm, so the service never refuses an
 * empty parcel; the operator corrects it at the service when it matters.
 * Pure.
 *
 * @param array $order   order_number, billing_email_address, currency
 * @param array $ship_to the ship_tos row (first_name, last_name, company,
 *                       address_1, address_2, city, state, zip_code,
 *                       country, phone_number) and total (minor units)
 * @param array $items   list of product_name, quantity, weight, length, width, height
 * @param string $state_code the province's plate number, TR-34 or 34
 * @param bool  $return
 * @return array
 */
function pg_shipment_label_shipment($order, $ship_to, $items, $state_code = '', $return = false)
{
    $text = function ($source, $name) {
        return (is_array($source) && isset($source[$name]) && is_scalar($source[$name])) ? trim((string) $source[$name]) : '';
    };

    $name = trim($text($ship_to, 'first_name') . ' ' . $text($ship_to, 'last_name'));

    if ($name === '') {
        $name = $text($ship_to, 'company');
    }

    $weight = 0.0;
    $size = array('length' => 0.0, 'width' => 0.0, 'height' => 0.0);
    $lines = array();

    foreach ((array) $items as $item) {
        $quantity = max(1, (int) $text($item, 'quantity'));
        $weight += ((float) $text($item, 'weight')) * $quantity;

        foreach ($size as $dimension => $largest) {
            $size[$dimension] = max($largest, (float) $text($item, $dimension));
        }

        if ($text($item, 'product_name') !== '') {
            $lines[] = array('title' => $text($item, 'product_name'), 'quantity' => $quantity);
        }
    }

    $parcel = array();

    foreach ($size as $dimension => $inches) {
        $parcel[$dimension] = ($inches > 0) ? round($inches * 2.54, 2) : 10.0;
    }

    $parcel['weight'] = ($weight > 0) ? round($weight * 0.45359237, 3) : 1.0;

    return array(
        'order_number' => $text($order, 'order_number'),
        'recipient'    => array(
            'name'       => $name,
            'email'      => $text($order, 'billing_email_address'),
            'phone'      => $text($ship_to, 'phone_number'),
            'address_1'  => $text($ship_to, 'address_1'),
            'address_2'  => $text($ship_to, 'address_2'),
            'city'       => $text($ship_to, 'city'),
            'state'      => $text($ship_to, 'state'),
            'state_code' => (string) $state_code,
            'zip'        => $text($ship_to, 'zip_code'),
            'country'    => strtoupper($text($ship_to, 'country')),
        ),
        'parcel'       => $parcel,
        'items'        => $lines,
        'total'        => (int) $text($ship_to, 'total'),
        'currency'     => strtoupper($text($order, 'currency')),
        'service'      => '',
        'return'       => (bool) $return,
    );
}

/**
 * The shipment_labels row of a label create() opened. Pure.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $driver
 * @param array  $answer  pg_label_answer() of create(), a success
 * @param string $file    the stored file name, '' when none was kept
 * @param int    $user_id
 * @param int    $now
 * @param bool   $return
 * @return array column => value
 */
function pg_shipment_label_row($order_id, $ship_to_id, $driver, $answer, $file, $user_id, $now, $return = false)
{
    $format = (isset($answer['label_format']) && in_array($answer['label_format'], pg_label_formats(), true)) ? $answer['label_format'] : '';

    return array(
        'order_id'         => (int) $order_id,
        'ship_to_id'       => (int) $ship_to_id,
        'number'           => mb_substr(pg_shipment_number_clean(isset($answer['tracking_number']) ? $answer['tracking_number'] : ''), 0, 40),
        'driver'           => (string) $driver,
        'reference'        => mb_substr((string) (isset($answer['reference']) ? $answer['reference'] : ''), 0, 100),
        'barcode'          => mb_substr((string) (isset($answer['barcode']) ? $answer['barcode'] : ''), 0, 100),
        'label_format'     => $format,
        'label_file'       => pg_shipment_label_file_safe($file) ? $file : '',
        'state'            => 'active',
        'is_return'        => $return ? 1 : 0,
        'user_id'          => (int) $user_id,
        'created_at'       => (int) $now,
        'cancelled_at'     => 0,
        'label_removed_at' => 0,
    );
}

/**
 * Keep a label's document. The folder is made on first use.
 *
 * @param string $name     pg_shipment_label_file_name()
 * @param string $document the bytes
 * @return bool
 */
function pg_shipment_label_store($name, $document)
{
    $path = pg_shipment_label_path($name);
    $directory = pg_shipment_label_dir();

    if (($path === '') || ((string) $document === '')) {
        return false;
    }

    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return false;
    }

    return @file_put_contents($path, (string) $document, LOCK_EX) === strlen((string) $document);
}

/**
 * The label rows of an address, newest first.
 *
 * @param int $ship_to_id
 * @return array
 */
function pg_shipment_label_rows($ship_to_id)
{
    if (!pg_shipment_labels_ready()) {
        return array();
    }

    return db_items(
        "SELECT * FROM shipment_labels
        WHERE ship_to_id = '" . (int) $ship_to_id . "'
        ORDER BY created_at DESC, id DESC");
}

/**
 * Open a label for an order's address with the driver its carrier takes,
 * keep the document and the row, and record the tracking number on the
 * address through the shipment write path.
 *
 * An address that already has an active label gets no second one: a second
 * create() would buy a second shipment. An unclear answer is reported as
 * such, so the operator looks at the service before trying again.
 *
 * @param int $ship_to_id
 * @param int $user_id
 * @return array ok, error, id (the label row), number
 */
function pg_shipment_label_create($ship_to_id, $user_id)
{
    $out = array('ok' => false, 'error' => '', 'id' => 0, 'number' => '');

    if (!pg_shipment_labels_ready()) {
        $out['error'] = lang('Run the software update first: the table for shipping labels is missing.');
        return $out;
    }

    $ship_to = db_item(
        "SELECT
            ship_tos.*,
            shipping_methods.carrier AS method_carrier
        FROM ship_tos
        LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
        WHERE ship_tos.id = '" . (int) $ship_to_id . "'");

    $order = $ship_to ? db_item(
        "SELECT id, order_number, status, billing_email_address
        FROM orders
        WHERE id = '" . (int) $ship_to['order_id'] . "'") : null;

    if (!$order) {
        $out['error'] = lang('The order could not be found.');
        return $out;
    }

    if (in_array((string) $order['status'], array('incomplete', 'cancelled'), true)) {
        $out['error'] = lang('A label is created only for a completed order.');
        return $out;
    }

    $active = (int) db_value(
        "SELECT COUNT(*) FROM shipment_labels
        WHERE (ship_to_id = '" . (int) $ship_to['id'] . "') AND (state = 'active') AND (is_return = '0')");

    if ($active > 0) {
        $out['error'] = lang('This address already has a label. Print it again, or cancel it before creating another.');
        return $out;
    }

    $carrier = pg_shipment_label_carrier(isset($ship_to['method_carrier']) ? $ship_to['method_carrier'] : '', $ship_to['shipping_method_code']);
    $driver = pg_shipment_label_driver_pick($carrier, pg_shipment_label_drivers());

    if ($driver === '') {
        $out['error'] = lang('No carrier label service that ships with this address\'s carrier is switched on.');
        return $out;
    }

    $items = db_items(
        "SELECT
            order_items.product_name,
            order_items.quantity,
            products.weight,
            products.length,
            products.width,
            products.height
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE (order_items.ship_to_id = '" . (int) $ship_to['id'] . "')" . pg_order_item_active_sql());

    $ship_to['total'] = (int) db_value(
        "SELECT SUM(order_items.price * order_items.quantity) FROM order_items
        WHERE (order_items.ship_to_id = '" . (int) $ship_to['id'] . "')" . pg_order_item_active_sql());

    $state_code = '';

    if (strtoupper(trim((string) $ship_to['country'])) === 'TR') {
        require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');
        $state_code = pg_sales_map_match_region('TR', (string) $ship_to['state']);
    }

    $order['currency'] = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';
    $shipment = pg_shipment_label_shipment($order, $ship_to, $items, $state_code);
    $account = pg_label_account(pg_label_fields($driver), pg_label_settings($driver));
    $answer = pg_label_call($driver, 'create', array($shipment, $account));
    $driver_name = pg_label_driver($driver)['label'];

    if (!$answer['success']) {
        $out['error'] = $answer['unclear']
            ? lang(array('string' => '{var:1} did not say whether the label was created. Check the shipment there before trying again.', 'vars' => array($driver_name)))
            : lang(array('string' => '{var:1} refused the label: {var:2}', 'vars' => array($driver_name, $answer['error'])));

        log_activity(lang(array('string' => 'A shipping label for order {var:1} could not be created: {var:2}', 'vars' => array($order['order_number'], $answer['error']))));

        return $out;
    }

    // A service that sends the document separately is asked for it once;
    // without it the label is still kept and the reprint asks again.
    $document = $answer['label'];
    $format = $answer['label_format'];

    if (($document === '') && pg_label_can($driver, 'label')) {
        $info = pg_label_info($driver);
        $again = pg_label_call($driver, 'label', array($answer['reference'], $info['formats'] ? reset($info['formats']) : 'pdf', $account));

        if ($again['success']) {
            $document = $again['label'];
            $format = $again['label_format'];
        }
    }

    $file = '';

    if ($document !== '') {
        $name = pg_shipment_label_file_name($order['id'], $ship_to['id'], $driver, $answer['reference'], $format);

        if (pg_shipment_label_store($name, $document)) {
            $file = $name;
        }
    }

    $answer['label_format'] = $format;
    $row = pg_shipment_label_row($order['id'], $ship_to['id'], $driver, $answer, $file, $user_id, time());
    $values = array();

    foreach ($row as $column => $value) {
        $values[] = "`" . $column . "` = '" . e((string) $value) . "'";
    }

    db("INSERT INTO shipment_labels SET " . implode(', ', $values));
    $out['id'] = (int) mysqli_insert_id(db::$con);

    $recorded = pg_shipment_record($order['id'], $ship_to['id'], $answer['tracking_number'], pg_label_shipment_options($answer));

    $out['ok'] = true;
    $out['number'] = $recorded['ok'] ? $recorded['number'] : $row['number'];

    log_activity(lang(array('string' => 'A shipping label was created with {var:1} for order {var:2}: {var:3}', 'vars' => array($driver_name, $order['order_number'], $out['number']))));

    return $out;
}

/**
 * A label's document for printing: the kept file, else the service's copy
 * asked for by reference (a file the sweep removed, a create() that sent
 * none). The service's copy is not written back: it would restart the time
 * the address is kept.
 *
 * @param array $row a shipment_labels row
 * @return array ok, error, document, format
 */
function pg_shipment_label_document($row)
{
    $out = array('ok' => false, 'error' => '', 'document' => '', 'format' => '');
    $path = ((int) $row['label_removed_at'] === 0) ? pg_shipment_label_path((string) $row['label_file']) : '';

    if (($path !== '') && is_file($path)) {
        $document = @file_get_contents($path);

        if (is_string($document) && ($document !== '')) {
            return array('ok' => true, 'error' => '', 'document' => $document, 'format' => (string) $row['label_format']);
        }
    }

    $driver = (string) $row['driver'];

    if (!pg_label_can($driver, 'label') || !pg_label_ready($driver)) {
        $out['error'] = lang('The label is no longer kept and its carrier label service is not switched on.');
        return $out;
    }

    $info = pg_label_info($driver);
    $format = in_array($row['label_format'], $info['formats'], true) ? $row['label_format'] : ($info['formats'] ? reset($info['formats']) : 'pdf');
    $account = pg_label_account(pg_label_fields($driver), pg_label_settings($driver));
    $answer = pg_label_call($driver, 'label', array((string) $row['reference'], $format, $account));

    if (!$answer['success']) {
        $out['error'] = $answer['error'];
        return $out;
    }

    return array('ok' => true, 'error' => '', 'document' => $answer['label'], 'format' => $answer['label_format']);
}

/**
 * Remove the kept files of the rows a condition finds and mark the rows.
 *
 * @param string $where SQL condition on shipment_labels
 * @return int files removed
 */
function pg_shipment_label_remove_files($where)
{
    if (!pg_shipment_labels_ready() || ((string) $where === '')) {
        return 0;
    }

    $removed = 0;
    $rows = db_items("SELECT id, label_file FROM shipment_labels WHERE (label_removed_at = '0') AND (" . $where . ")");

    foreach ($rows as $row) {
        $path = pg_shipment_label_path((string) $row['label_file']);

        if (($path !== '') && is_file($path) && @unlink($path)) {
            $removed++;
        }

        db("UPDATE shipment_labels SET label_removed_at = '" . time() . "' WHERE id = '" . (int) $row['id'] . "'");
    }

    return $removed;
}

/**
 * Delete an order's labels: the files and the rows (delete_order()).
 *
 * @param int $order_id
 */
function pg_shipment_labels_delete_for_order($order_id)
{
    if (!pg_shipment_labels_ready()) {
        return;
    }

    $where = "order_id = '" . (int) $order_id . "'";

    pg_shipment_label_remove_files($where);
    db("DELETE FROM shipment_labels WHERE " . $where);
}

/**
 * The rows whose file is due to go (pg_shipment_label_expires_at()). The
 * delivery is the parcel's (shipping_tracking_numbers.delivered_at of the
 * same address and number).
 *
 * @param int $now
 * @param int $limit
 * @return array ids
 */
function pg_shipment_label_expired_ids($now, $limit)
{
    $delivered = pg_shipment_rows_ready()
        ? "(SELECT MAX(stn.delivered_at) FROM shipping_tracking_numbers AS stn
            WHERE (stn.ship_to_id = shipment_labels.ship_to_id) AND (stn.number = shipment_labels.number))"
        : "0";

    $keep = (int) $now - (PG_SHIPMENT_LABEL_KEEP_DAYS * 86400);
    $rows = db_items(
        "SELECT id, created_at, cancelled_at, " . $delivered . " AS delivered_at
        FROM shipment_labels
        WHERE
            (label_removed_at = '0')
            AND (
                (created_at <= '" . ((int) $now - (PG_SHIPMENT_LABEL_KEEP_DAYS_MAX * 86400)) . "')
                OR ((cancelled_at > '0') AND (cancelled_at <= '" . $keep . "'))
                OR ((" . $delivered . ") BETWEEN 1 AND '" . $keep . "')
            )
        ORDER BY id
        LIMIT " . (int) $limit);

    $ids = array();

    foreach ($rows as $row) {
        if (pg_shipment_label_expires_at($row['created_at'], (int) $row['delivered_at'], $row['cancelled_at']) <= (int) $now) {
            $ids[] = (int) $row['id'];
        }
    }

    return $ids;
}

/**
 * @return string
 */
function pg_shipment_label_sweep_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/shipment_label_sweep.txt';
}

/**
 * The daily sweep of the general job: once a day, the files whose time is up
 * are removed and their rows marked. A tick between runs reads one small file.
 *
 * @return int files removed
 */
function pg_shipment_label_sweep_daily()
{
    if (!pg_shipment_labels_ready()) {
        return 0;
    }

    $path = pg_shipment_label_sweep_state_path();
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;
    $now = time();

    if (($now - $last) < 86400) {
        return 0;
    }

    @file_put_contents($path, (string) $now, LOCK_EX);

    $ids = pg_shipment_label_expired_ids($now, PG_SHIPMENT_LABEL_SWEEP_LIMIT);

    if (!$ids) {
        return 0;
    }

    return pg_shipment_label_remove_files("id IN ('" . implode("','", $ids) . "')");
}
