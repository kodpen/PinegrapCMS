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
 * Whether a label can name the return request it was made for (11.0.126).
 *
 * @return bool
 */
function pg_shipment_labels_link_returns()
{
    return pg_schema_has('shipment_labels', 'order_return_id');
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
 * @param string $format    pdf, zpl or svg
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
        && (preg_match('/^[0-9]+_[0-9]+_[a-z][a-z0-9_]*_[0-9a-f]{16}\.(pdf|zpl|svg)$/', $name) === 1);
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
 * @param int    $order_return_id the return request a return label was made
 *                                for; 0 writes no such column, so a label of
 *                                the order screen is saved before 11.0.126
 * @return array column => value
 */
function pg_shipment_label_row($order_id, $ship_to_id, $driver, $answer, $file, $user_id, $now, $return = false, $order_return_id = 0)
{
    $format = (isset($answer['label_format']) && in_array($answer['label_format'], pg_label_formats(), true)) ? $answer['label_format'] : '';

    $row = array(
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

    if ($return && ((int) $order_return_id > 0)) {
        $row['order_return_id'] = (int) $order_return_id;
    }

    return $row;
}

/**
 * Whether a label's number becomes one of its address's parcels. A return
 * label's never does: the parcel comes back to the store, and its delivery
 * would announce the order delivered. Pure.
 *
 * @param array $row pg_shipment_label_row()
 * @return bool
 */
function pg_shipment_label_records_parcel($row)
{
    return empty($row['is_return']);
}

/**
 * The code a customer gives the carrier for a return label: its barcode,
 * else its tracking number. Pure.
 *
 * @param array $row a shipment_labels row
 * @return string
 */
function pg_shipment_label_return_code($row)
{
    $barcode = isset($row['barcode']) ? trim((string) $row['barcode']) : '';

    return ($barcode !== '') ? $barcode : (isset($row['number']) ? trim((string) $row['number']) : '');
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
 * An address that already has an active label of the kind asked for gets no
 * second one: a second create() would buy a second shipment. A label made
 * from a return request is that request's (pg_shipment_label_create_for_return())
 * and is not counted here. An unclear
 * answer is reported as such, so the operator looks at the service before
 * trying again. A return label (collected from the recipient, delivered to
 * the store) is kept as a row only: its number is not one of the address's
 * parcels, whose delivery would announce the order delivered.
 *
 * @param int  $ship_to_id
 * @param int  $user_id
 * @param bool $return
 * @return array ok, error, id (the label row), number
 */
function pg_shipment_label_create($ship_to_id, $user_id, $return = false)
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
        WHERE (ship_to_id = '" . (int) $ship_to['id'] . "') AND (state = 'active') AND (is_return = '" . ($return ? 1 : 0) . "')"
        . (pg_shipment_labels_link_returns() ? " AND (order_return_id = '0')" : ""));

    if ($active > 0) {
        $out['error'] = $return
            ? lang('This address already has a return label. Print it again, or cancel it before creating another.')
            : lang('This address already has a label. Print it again, or cancel it before creating another.');
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

    return pg_shipment_label_open($driver, $order, $ship_to, pg_shipment_label_shipment($order, $ship_to, $items, $state_code, $return), $user_id, $return);
}

/**
 * Open the shipment with the driver, keep the document and the row, and for
 * an outbound label record the tracking number on the address through the
 * shipment write path. The caller has checked that no active label of the
 * same kind exists.
 *
 * @param string $driver
 * @param array  $order    id, order_number
 * @param array  $ship_to  id
 * @param array  $shipment pg_shipment_label_shipment()
 * @param int    $user_id
 * @param bool   $return
 * @param int    $order_return_id the return request, 0 for the order screen
 * @return array ok, error, id (the label row), number, row, carrier (the
 *               service's carrier key, or '')
 */
function pg_shipment_label_open($driver, $order, $ship_to, $shipment, $user_id, $return = false, $order_return_id = 0)
{
    $out = array('ok' => false, 'error' => '', 'id' => 0, 'number' => '', 'row' => array(), 'carrier' => '');
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
    $row = pg_shipment_label_row($order['id'], $ship_to['id'], $driver, $answer, $file, $user_id, time(), $return, $order_return_id);
    $values = array();

    foreach ($row as $column => $value) {
        $values[] = "`" . $column . "` = '" . e((string) $value) . "'";
    }

    db("INSERT INTO shipment_labels SET " . implode(', ', $values));
    $out['id'] = (int) mysqli_insert_id(db::$con);

    $out['ok'] = true;
    $out['number'] = $row['number'];
    $out['row'] = $row;
    $out['carrier'] = isset($answer['carrier']) ? (string) $answer['carrier'] : '';

    if (pg_shipment_label_records_parcel($row)) {
        $recorded = pg_shipment_record($order['id'], $ship_to['id'], $answer['tracking_number'], pg_label_shipment_options($answer));
        $out['number'] = $recorded['ok'] ? $recorded['number'] : $row['number'];
    }

    log_activity(lang(array('string' => 'A shipping label was created with {var:1} for order {var:2}: {var:3}', 'vars' => array($driver_name, $order['order_number'], $out['number']))));

    return $out;
}

/**
 * What "Create Return Label" on a return request may do. Only an approved
 * request gets a label, and only one active label: a second press, a form
 * sent again or a page left open finds it and the service is not called
 * again. A label of another request on the same address, or one made on the
 * order screen, does not count. The driver is the one that ships with the
 * approval's carrier (pg_shipment_label_driver_pick()); when none does, the
 * approval goes on with the carrier and code given by hand. Pure.
 *
 * @param array $return  id, status, carrier of the order_returns row
 * @param array $labels  shipment_labels rows of the request's address
 * @param array $drivers pg_shipment_label_drivers()
 * @return array driver ('' when no label is to be made), error, label_id
 *               (the request's active label, when it has one)
 */
function pg_shipment_label_return_plan($return, $labels, $drivers)
{
    $out = array('driver' => '', 'error' => '', 'label_id' => 0);

    if ((string) $return['status'] !== 'approved') {
        $out['error'] = lang('A return label is created only for an approved request.');
        return $out;
    }

    foreach ((array) $labels as $label) {
        if (((int) $label['order_return_id'] === (int) $return['id']) && !empty($label['is_return']) && ((string) $label['state'] === 'active')) {
            $out['label_id'] = (int) $label['id'];
            $out['error'] = lang('This request already has a return label. Print it again, or cancel it on the order before creating another.');
            return $out;
        }
    }

    $carrier = (string) $return['carrier'];
    $out['driver'] = pg_shipment_label_driver_pick($carrier, $drivers);

    if ($out['driver'] !== '') {
        return $out;
    }

    $out['error'] = ($carrier === '' || empty($drivers))
        ? lang('No carrier label service is switched on. Give the customer the return carrier and code by hand.')
        : lang(array('string' => 'No carrier label service that ships with {var:1} is switched on. Give the customer the return code by hand.', 'vars' => array(pg_return_carrier_label($carrier))));

    return $out;
}

/**
 * The name of the lock that lets one return label be made for a request at
 * a time.
 *
 * @param int $return_id
 * @return string
 */
function pg_shipment_label_return_lock_name($return_id)
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");

    return 'pg_label_return_' . md5($database) . '_' . (int) $return_id;
}

/**
 * Open the return label of a return request: the shipment carries the
 * request's lines, is collected from the delivery's address and comes back
 * to the store. Its row names the request (order_return_id); its number is
 * never recorded as one of the address's parcels.
 *
 * The check for the request's active label and the service call run under a
 * lock of the request, so two presses at once never buy two labels; a press
 * while another runs is told to wait.
 *
 * @param array $return  pg_return_load()
 * @param int   $user_id
 * @return array ok, error, id (the label row), code (what the customer gives
 *               the carrier), carrier (the service's carrier key, or '')
 */
function pg_shipment_label_create_for_return($return, $user_id)
{
    $out = array('ok' => false, 'error' => '', 'id' => 0, 'code' => '', 'carrier' => '');

    if (!pg_shipment_labels_ready() || !pg_shipment_labels_link_returns()) {
        $out['error'] = lang('Run the software update first: the table for shipping labels is missing.');
        return $out;
    }

    $lock = pg_shipment_label_return_lock_name($return['id']);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 0)") !== 1) {
        $out['error'] = lang('A return label is being created for this request. Wait a moment, then open the request again.');
        return $out;
    }

    $result = pg_shipment_label_return_open($return, $user_id);

    db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");

    return $result;
}

/**
 * pg_shipment_label_create_for_return() under the request's lock.
 *
 * @param array $return
 * @param int   $user_id
 * @return array as pg_shipment_label_create_for_return()
 */
function pg_shipment_label_return_open($return, $user_id)
{
    $out = array('ok' => false, 'error' => '', 'id' => 0, 'code' => '', 'carrier' => '');
    $ship_to_id = (int) $return['ship_to_id'];

    // Read again: the status may have moved since the page was drawn.
    $current = db_item("SELECT id, status, carrier FROM order_returns WHERE id = '" . (int) $return['id'] . "'");

    if (!$current) {
        $out['error'] = lang('The return could not be found.');
        return $out;
    }

    $plan = pg_shipment_label_return_plan($current, pg_shipment_label_rows($ship_to_id), pg_shipment_label_drivers());

    if ($plan['driver'] === '') {
        $out['error'] = $plan['error'];
        return $out;
    }

    $ship_to = db_item("SELECT * FROM ship_tos WHERE id = '" . $ship_to_id . "'");
    $order = $ship_to ? db_item(
        "SELECT id, order_number, status, billing_email_address
        FROM orders
        WHERE id = '" . (int) $ship_to['order_id'] . "'") : null;

    if (!$order || ((int) $order['id'] !== (int) $return['order_id'])) {
        $out['error'] = lang('The order could not be found.');
        return $out;
    }

    // The request's lines, each with the quantity coming back.
    $items = db_items(
        "SELECT
            order_items.product_name,
            order_return_items.quantity,
            order_items.price,
            products.weight,
            products.length,
            products.width,
            products.height
        FROM order_return_items
        LEFT JOIN order_items ON order_items.id = order_return_items.order_item_id
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE order_return_items.return_id = '" . (int) $return['id'] . "'");

    $ship_to['total'] = 0;

    foreach ($items as $item) {
        $ship_to['total'] += (int) $item['price'] * (int) $item['quantity'];
    }

    $state_code = '';

    if (strtoupper(trim((string) $ship_to['country'])) === 'TR') {
        require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');
        $state_code = pg_sales_map_match_region('TR', (string) $ship_to['state']);
    }

    $order['currency'] = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '';
    $shipment = pg_shipment_label_shipment($order, $ship_to, $items, $state_code, true);

    $opened = pg_shipment_label_open($plan['driver'], $order, $ship_to, $shipment, $user_id, true, (int) $return['id']);

    if (!$opened['ok']) {
        $out['error'] = $opened['error'];
        return $out;
    }

    $out['ok'] = true;
    $out['id'] = $opened['id'];
    $out['code'] = pg_shipment_label_return_code($opened['row']);
    $out['carrier'] = isset($opened['carrier']) ? (string) $opened['carrier'] : '';

    return $out;
}

/**
 * The return labels made for one return request, newest first.
 *
 * @param int $return_id
 * @return array
 */
function pg_shipment_label_return_rows($return_id)
{
    if (!pg_shipment_labels_ready() || !pg_shipment_labels_link_returns()) {
        return array();
    }

    return db_items(
        "SELECT * FROM shipment_labels
        WHERE order_return_id = '" . (int) $return_id . "'
        ORDER BY created_at DESC, id DESC");
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
 * The headers a label's document is handed out with. A PDF opens in the
 * browser, a ZPL file is saved for the label printer. An SVG is never sent
 * as a document the panel's origin would run: it is shown on a print page
 * that holds it only as an image (pg_shipment_label_svg_page()), or saved
 * as an attachment the browser sandboxes. Pure.
 *
 * @param string $format    pdf, zpl or svg
 * @param string $file_name without the extension, [A-Za-z0-9-] only
 * @param bool   $download  true for the SVG file itself
 * @return string[]
 */
function pg_shipment_label_headers($format, $file_name, $download)
{
    $headers = array('Cache-Control: private, no-store', 'X-Content-Type-Options: nosniff');

    if ($format === 'svg') {
        if ($download) {
            return array_merge($headers, array(
                'Content-Type: image/svg+xml',
                'Content-Disposition: attachment; filename="' . $file_name . '.svg"',
                'Content-Security-Policy: sandbox',
            ));
        }

        return array_merge($headers, array(
            'Content-Type: text/html; charset=utf-8',
            "Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
        ));
    }

    if ($format === 'zpl') {
        return array_merge($headers, array(
            'Content-Type: application/octet-stream',
            'Content-Disposition: attachment; filename="' . $file_name . '.zpl"',
        ));
    }

    return array_merge($headers, array(
        'Content-Type: application/pdf',
        'Content-Disposition: inline; filename="' . $file_name . '.pdf"',
    ));
}

/**
 * The print page of an SVG label: the label as a data URL image sized to
 * its own page, a Print button and the link to the SVG file. Pure.
 *
 * @param string $svg      a document pg_label_svg_valid() accepted
 * @param string $title
 * @param string $download the address of the SVG attachment
 * @return string HTML
 */
function pg_shipment_label_svg_page($svg, $title, $download)
{
    $size = pg_shipment_label_svg_size($svg);

    return '<!DOCTYPE html><html lang="' . h(defined('SOFTWARE_LANGUAGE') ? SOFTWARE_LANGUAGE : 'tr') . '"><head><meta charset="utf-8"><title>' . h($title) . '</title>'
        . '<style>@page{size:' . $size[0] . 'pt ' . $size[1] . 'pt;margin:0}body{margin:0;font-family:sans-serif}'
        . '.bar{padding:8px;display:flex;gap:12px;align-items:center}img{display:block;width:' . $size[0] . 'pt;height:' . $size[1] . 'pt}'
        . '@media print{.bar{display:none}}</style></head><body>'
        . '<div class="bar"><button type="button" onclick="window.print()">' . h(lang('Print')) . '</button>'
        . '<a href="' . h($download) . '">' . h(lang('Download SVG')) . '</a></div>'
        . '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" alt="' . h($title) . '">'
        . '</body></html>';
}

/**
 * The page size of an SVG label in points: its root's width and height
 * (mm, cm, in, pt, px or bare numbers), else its view box at 100 mm wide,
 * else A6. Pure.
 *
 * @param string $svg
 * @return float[] width, height
 */
function pg_shipment_label_svg_size($svg)
{
    $root = preg_match('/<svg\b[^>]*>/i', (string) $svg, $match) ? $match[0] : '';
    $points = array('mm' => 72 / 25.4, 'cm' => 72 / 2.54, 'in' => 72.0, 'pt' => 1.0, 'px' => 0.75, '' => 0.75);
    $length = function ($name) use ($root, $points) {
        if (!preg_match('/\s' . $name . '\s*=\s*["\']\s*([0-9]+(?:\.[0-9]+)?)\s*(mm|cm|in|pt|px|)\s*["\']/i', $root, $value)) {
            return 0.0;
        }

        return (float) $value[1] * $points[strtolower($value[2])];
    };

    $width = $length('width');
    $height = $length('height');

    if (($width > 0) && ($height > 0)) {
        return array(round($width, 2), round($height, 2));
    }

    if (preg_match('/\sviewBox\s*=\s*["\']\s*[-0-9.]+[\s,]+[-0-9.]+[\s,]+([0-9.]+)[\s,]+([0-9.]+)/i', $root, $box) && ((float) $box[1] > 0)) {
        $width = round(100 * 72 / 25.4, 2);

        return array($width, round($width * (float) $box[2] / (float) $box[1], 2));
    }

    return array(round(105 * 72 / 25.4, 2), round(148 * 72 / 25.4, 2));
}

/**
 * An SVG label with its fonts named as the bundled DejaVu files, for the
 * PDF copy only. php-svg-lib draws text in the core PDF fonts, which have
 * no ı, ş, ğ or İ; a font named by its file stem is loaded as a Unicode
 * font instead. Monospace becomes DejaVu Sans Mono, serif DejaVu Serif,
 * any other family DejaVu Sans; the root gets DejaVu Sans when it names
 * none. Pure.
 *
 * @param string $svg
 * @param string $directory the folder of the DejaVu .ufm files
 * @return string
 */
function pg_shipment_label_svg_fonts($svg, $directory)
{
    $font = function ($family) use ($directory) {
        $family = strtolower($family);
        $stem = (strpos($family, 'mono') !== false) || (strpos($family, 'courier') !== false) ? 'DejaVuSansMono'
            : (((strpos($family, 'serif') !== false) && (strpos($family, 'sans') === false)) || (strpos($family, 'times') !== false) ? 'DejaVuSerif' : 'DejaVuSans');

        return htmlspecialchars($directory . '/' . $stem, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    $svg = preg_replace_callback('/(font-family\s*=\s*)(["\'])(.*?)\2/i', function ($match) use ($font) {
        return $match[1] . '"' . $font($match[3]) . '"';
    }, (string) $svg);

    $svg = preg_replace_callback('/(font-family\s*:\s*)([^;"\'}]+)/i', function ($match) use ($font) {
        return $match[1] . $font($match[2]);
    }, $svg);

    return preg_replace_callback('/<svg\b[^>]*>/i', function ($match) use ($font) {
        return (stripos($match[0], 'font-family') !== false) ? $match[0] : substr($match[0], 0, 4) . ' font-family="' . $font('sans-serif') . '"' . substr($match[0], 4);
    }, $svg, 1);
}

/**
 * An SVG label drawn into a one-page PDF of its own size by the bundled
 * dompdf (php-svg-lib), for the bulk PDF.
 *
 * @param string $svg
 * @return string the PDF, '' when it cannot be drawn
 */
function pg_shipment_label_svg_pdf($svg)
{
    $size = pg_shipment_label_svg_size($svg);
    $svg = pg_shipment_label_svg_fonts($svg, PG_FUNCTIONS_DIR . '/includes/dompdf/vendor/dompdf/dompdf/lib/fonts');
    $html = '<html><head><meta charset="utf-8"><style>@page{margin:0}body{margin:0}img{display:block;width:' . $size[0] . 'pt;height:' . $size[1] . 'pt}</style></head><body>'
        . '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '"></body></html>';

    try {
        $pdf = pg_privacy_export_pdf($html, array(0, 0, $size[0], $size[1]));
    } catch (\Throwable $exception) {
        $pdf = false;
    }

    return (is_string($pdf) && pg_label_document_valid($pdf, 'pdf')) ? $pdf : '';
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

/**
 * What a cancel() answer writes on the label row: only a success cancels;
 * a refusal or an unclear answer leaves the label as it was. Pure.
 *
 * @param array $answer pg_label_answer() of cancel()
 * @param int   $now
 * @return array column => value, empty when nothing is written
 */
function pg_shipment_label_cancel_update($answer, $now)
{
    return empty($answer['success']) ? array() : array('state' => 'cancelled', 'cancelled_at' => (int) $now);
}

/**
 * The address's tracking numbers once a cancelled label's number is taken
 * out, compared the way the parcel's unique key compares them. Pure.
 *
 * @param array  $numbers the address's numbers
 * @param string $number  the cancelled label's
 * @return array
 */
function pg_shipment_label_numbers_after_cancel($numbers, $number)
{
    $number = mb_strtolower(trim((string) $number));
    $kept = array();

    foreach ((array) $numbers as $existing) {
        if (($number === '') || (mb_strtolower(trim((string) $existing)) !== $number)) {
            $kept[] = trim((string) $existing);
        }
    }

    return $kept;
}

/**
 * Cancel a label at its service, then mark the row and take its number off
 * the address's parcels through the shipment write path. A return label
 * has no parcel row.
 *
 * @param int $id shipment_labels.id
 * @return array ok, error, order_id
 */
function pg_shipment_label_cancel($id)
{
    $out = array('ok' => false, 'error' => '', 'order_id' => 0);
    $row = pg_shipment_labels_ready() ? db_item("SELECT * FROM shipment_labels WHERE id = '" . (int) $id . "'") : null;

    if (!$row) {
        $out['error'] = lang('Record not found.');
        return $out;
    }

    $out['order_id'] = (int) $row['order_id'];
    $driver = (string) $row['driver'];

    if ($row['state'] !== 'active') {
        $out['error'] = lang('This label is already cancelled.');
        return $out;
    }

    if (!pg_label_can($driver, 'cancel') || !pg_label_ready($driver)) {
        $out['error'] = lang('Its carrier label service is not switched on or cannot cancel a label. Cancel it at the service.');
        return $out;
    }

    $account = pg_label_account(pg_label_fields($driver), pg_label_settings($driver));
    $answer = pg_label_call($driver, 'cancel', array((string) $row['reference'], $account));
    $update = pg_shipment_label_cancel_update($answer, time());

    if (!$update) {
        $out['error'] = $answer['unclear']
            ? lang('The service did not say whether the label was cancelled. Check the shipment there.')
            : lang(array('string' => 'The service refused to cancel the label: {var:1}', 'vars' => array($answer['error'])));
        return $out;
    }

    db("UPDATE shipment_labels SET state = '" . e($update['state']) . "', cancelled_at = '" . (int) $update['cancelled_at'] . "' WHERE id = '" . (int) $row['id'] . "'");

    if (!(int) $row['is_return'] && ((string) $row['number'] !== '')) {
        $numbers = array();

        foreach (pg_shipment_rows((int) $row['ship_to_id']) as $parcel) {
            $numbers[] = $parcel['number'];
        }

        pg_shipment_prune((int) $row['ship_to_id'], pg_shipment_label_numbers_after_cancel($numbers, $row['number']));
    }

    log_activity(lang(array('string' => 'A shipping label of order {var:1} was cancelled: {var:2}', 'vars' => array((string) db_value("SELECT order_number FROM orders WHERE id = '" . (int) $row['order_id'] . "'"), $row['number']))));

    $out['ok'] = true;

    return $out;
}

/**
 * The labels a bulk print takes: the active outbound ones of the selected
 * orders, in the order the orders were selected, then by address and age.
 * Pure.
 *
 * @param array $rows      shipment_labels rows
 * @param array $order_ids in the order selected
 * @return array
 */
function pg_shipment_label_print_selection($rows, $order_ids)
{
    $position = array_flip(array_map('intval', array_values((array) $order_ids)));
    $selection = array();

    foreach ((array) $rows as $row) {
        if (($row['state'] === 'active') && !(int) $row['is_return'] && isset($position[(int) $row['order_id']])) {
            $selection[] = $row;
        }
    }

    usort($selection, function ($a, $b) use ($position) {
        $order = array(
            array($position[(int) $a['order_id']], (int) $a['ship_to_id'], (int) $a['id']),
            array($position[(int) $b['order_id']], (int) $b['ship_to_id'], (int) $b['id']),
        );

        return ($order[0] < $order[1]) ? -1 : (($order[0] > $order[1]) ? 1 : 0);
    });

    return $selection;
}

/**
 * Open labels for the addresses of the selected orders that have no active
 * one, at most $limit a run (each is a call to the service).
 *
 * @param array $order_ids
 * @param int   $user_id
 * @param int   $limit
 * @return array created, failed (list of sentences), left (addresses not reached)
 */
function pg_shipment_label_bulk_create($order_ids, $user_id, $limit = 20)
{
    $out = array('created' => 0, 'failed' => array(), 'left' => 0);
    $order_ids = array_values(array_filter(array_map('intval', (array) $order_ids)));

    if (!$order_ids || !pg_shipment_labels_ready()) {
        return $out;
    }

    $addresses = db_values(
        "SELECT DISTINCT order_items.ship_to_id
        FROM order_items
        WHERE
            (order_items.order_id IN ('" . implode("','", $order_ids) . "'))
            AND (order_items.ship_to_id > 0)" . pg_order_item_active_sql() . "
            AND NOT EXISTS (
                SELECT 1 FROM shipment_labels
                WHERE (shipment_labels.ship_to_id = order_items.ship_to_id) AND (shipment_labels.state = 'active') AND (shipment_labels.is_return = '0'))
        ORDER BY order_items.ship_to_id");

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    foreach ($addresses as $index => $ship_to_id) {
        if ($index >= $limit) {
            $out['left'] = count($addresses) - $limit;
            break;
        }

        $result = pg_shipment_label_create((int) $ship_to_id, $user_id);

        if ($result['ok']) {
            $out['created']++;
        } else {
            $out['failed'][] = $result['error'];
        }
    }

    return $out;
}

/**
 * The labels of the selected orders as one PDF, merged now and never kept.
 * An SVG label is drawn into a page of its own. A label that is not a PDF
 * or an SVG (ZPL for a thermal printer) or cannot be read or drawn is
 * listed on a last page instead, to be printed from its order.
 *
 * @param array $order_ids in the order selected
 * @return array ok, error, document, count (labels in the PDF)
 */
function pg_shipment_label_bulk_pdf($order_ids)
{
    $out = array('ok' => false, 'error' => '', 'document' => '', 'count' => 0);
    $order_ids = array_values(array_filter(array_map('intval', (array) $order_ids)));

    $rows = ($order_ids && pg_shipment_labels_ready())
        ? db_items(
            "SELECT shipment_labels.*, orders.order_number
            FROM shipment_labels
            LEFT JOIN orders ON orders.id = shipment_labels.order_id
            WHERE shipment_labels.order_id IN ('" . implode("','", $order_ids) . "')")
        : array();

    $documents = array();
    $labels = array();
    $left_out = array();

    foreach (pg_shipment_label_print_selection($rows, $order_ids) as $row) {
        $document = pg_shipment_label_document($row);

        // An SVG joins as a page dompdf draws; one it cannot draw is listed.
        $pdf = ($document['ok'] && ($document['format'] === 'svg')) ? pg_shipment_label_svg_pdf($document['document']) : '';

        if ($document['ok'] && (($document['format'] === 'pdf') || ($pdf !== ''))) {
            $documents[] = ($pdf !== '') ? $pdf : $document['document'];
            $labels[] = $row;
        } else {
            $left_out[] = $row;
        }
    }

    $skipped = array();
    $merged = pg_pdf_merge($documents, $skipped);

    foreach ($skipped as $position) {
        $left_out[] = $labels[$position];
    }

    $out['count'] = count($documents) - count($skipped);

    if ($left_out) {
        $note = '<meta charset="utf-8"><h3>' . h(lang('Labels not in this PDF')) . '</h3><p>' . h(lang('Print these from their orders.')) . '</p><ul>';

        foreach ($left_out as $row) {
            $note .= '<li>' . h(lang(array('string' => 'Order {var:1}: {var:2} ({var:3})', 'vars' => array($row['order_number'], $row['number'], strtoupper($row['label_format']))))) . '</li>';
        }

        $page = pg_privacy_export_pdf($note . '</ul>');
        $merged = is_string($page) ? pg_pdf_merge(array_merge($merged !== '' ? array($merged) : array(), array($page))) : $merged;
    }

    if (($merged === '') || (($out['count'] === 0) && !$left_out)) {
        $out['error'] = lang('The selected orders have no active shipping label.');
        return $out;
    }

    $out['ok'] = true;
    $out['document'] = $merged;

    return $out;
}
