<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The shipment rows (shipping_tracking_numbers): the one way a tracking
 * number is written, removed and marked delivered.
 *
 * Three writers used to keep the table each in their own way. The order
 * screen replaced an address's list, the marketplace import inserted the
 * number it was handed, and neither wrote anything but the number, so the
 * carrier, the day the parcel left and the day it arrived were worked out
 * again at every read from the shipping method's code and checkout's
 * estimated dates. Since the 2026.11.0 schema (11.0.20) the row has columns
 * for all of that; pg_shipment_record() fills them, pg_shipment_prune() is the
 * only delete, and pg_shipment_delivered() is the only place a delivery is
 * announced, so order.delivered and the order_delivered e-mail go out once
 * per address whichever side learnt of it.
 *
 * Carrier recognition itself (pg_shipping_carriers(), pg_shipping_carrier())
 * stays in ecommerce.php beside the link and name helpers that read it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Where a parcel stands. Kept in code rather than an ENUM so a carrier that
 * reports more stages adds a value here, not an ALTER.
 *
 * @return string[]
 */
function pg_shipment_statuses()
{
    return array('created', 'in_transit', 'out_for_delivery', 'delivered', 'returned', 'exception');
}

/**
 * Who wrote a row: the order screen (and the Workspace, which saves through
 * it), the external API, a marketplace import, a carrier's own API, a CSV
 * upload.
 *
 * @return string[]
 */
function pg_shipment_sources()
{
    return array('panel', 'api', 'marketplace', 'carrier', 'csv');
}

/**
 * A source as stored: one of pg_shipment_sources(), 'panel' for anything else.
 *
 * @param  mixed $source
 * @return string
 */
function pg_shipment_source($source)
{
    $source = is_string($source) ? strtolower(trim($source)) : '';

    return in_array($source, pg_shipment_sources(), true) ? $source : 'panel';
}

/**
 * A tracking number as stored: trimmed and cut to the column (VARCHAR(100)).
 *
 * @param  mixed $number
 * @return string '' when there is no number
 */
function pg_shipment_number_clean($number)
{
    if (!is_scalar($number)) {
        return '';
    }

    return trim(mb_substr(trim((string) $number), 0, 100));
}

/**
 * A carrier's own tracking page for a parcel, as stored, or '' to build the
 * address from the carrier's pattern.
 *
 * The value is printed as a link on the order screens, so only an absolute
 * http(s) address passes: anything else (a javascript: address, a relative
 * path, text with spaces in it) is dropped rather than repaired.
 *
 * @param  mixed $url
 * @return string
 */
function pg_shipment_tracking_url_clean($url)
{
    if (!is_string($url)) {
        return '';
    }

    $url = trim($url);

    if (($url === '') || (strlen($url) > 500) || (preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1)) {
        return '';
    }

    if (preg_match('#^https?://[^/?\#]+#i', $url) !== 1) {
        return '';
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    return $url;
}

/**
 * A calendar day as Y-m-d, or '' for an empty, zero or impossible date.
 *
 * ship_tos keeps its dates in DATE columns, which read back as '0000-00-00'
 * when empty, and a form may post '' for the same thing.
 *
 * @param  mixed $value
 * @return string
 */
function pg_shipment_date($value)
{
    if (!is_string($value) || (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($value), $match) !== 1)) {
        return '';
    }

    if (!checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
        return '';
    }

    return $match[1] . '-' . $match[2] . '-' . $match[3];
}

/**
 * The moment written for something that happened on a calendar day.
 *
 * Today is now. An earlier day is its noon: the row keeps a unix time, the
 * screens read it back as a day in the site's time zone, and noon stays on
 * the same day under any offset within twelve hours, where midnight would
 * slip to the day before. A day after $now is never written as the future.
 *
 * @param  string $day Y-m-d
 * @param  int    $now unix time
 * @return int
 */
function pg_shipment_day_time($day, $now)
{
    $now = (int) $now;
    $day = pg_shipment_date($day);

    if (($day === '') || ($day === date('Y-m-d', $now))) {
        return $now;
    }

    $noon = strtotime($day . ' 12:00:00');

    if ($noon === false) {
        return $now;
    }

    return min($noon, $now);
}

/**
 * Whether a save records a delivery: it enters a delivery date, or changes
 * the one there was, to a day that is not after today.
 *
 * ship_tos.delivery_date starts as checkout's estimate, so "there was no date
 * before" (the rule this replaces) almost never held and the delivery of most
 * web orders was never announced, while a date typed into an empty field was
 * announced even when it lay in the future and could only be an estimate.
 * A day beyond today is still an estimate; the same date sent back unchanged,
 * which the order screen does on every save, records nothing.
 *
 * @param  string $before the date stored before the save
 * @param  string $after  the date the save writes
 * @param  string $today  Y-m-d in the site's time zone
 * @return bool
 */
function pg_shipment_delivery_entered($before, $after, $today)
{
    $after = pg_shipment_date($after);
    $today = pg_shipment_date($today);

    if (($after === '') || ($today === '') || ($after > $today)) {
        return false;
    }

    return ($after !== pg_shipment_date($before));
}

/**
 * Validates the check digit of a UPU S10 item number (two letters, eight
 * digits, a check digit, two letters: PTT's international parcels and
 * registered mail, "RR123456785TR").
 *
 * @param  string $number
 * @return bool
 */
function pg_shipment_s10_valid($number)
{
    $number = strtoupper(str_replace(' ', '', trim((string) $number)));

    if (preg_match('/^[A-Z]{2}(\d{8})(\d)[A-Z]{2}$/', $number, $match) !== 1) {
        return false;
    }

    $weights = array(8, 6, 4, 2, 3, 5, 9, 7);
    $sum = 0;

    for ($i = 0; $i < 8; $i++) {
        $sum += ((int) $match[1][$i]) * $weights[$i];
    }

    $check = 11 - ($sum % 11);

    if ($check === 10) {
        $check = 0;
    } elseif ($check === 11) {
        $check = 5;
    }

    return ($check === (int) $match[2]);
}

/**
 * The number shapes known for carriers that have no recognition pattern in
 * pg_shipping_carriers(): the Turkish couriers issue plain digits (which is
 * why they cannot be told apart by shape), and PTT also uses S10 numbers for
 * international parcels. A carrier in neither list is not checked.
 *
 * @return array carrier key => 'digits' | 'digits_or_s10'
 */
function pg_shipment_number_shapes()
{
    return array(
        'yurtici' => 'digits',
        'surat'   => 'digits',
        'aras'    => 'digits',
        'mng'     => 'digits',
        'ptt'     => 'digits_or_s10',
    );
}

/**
 * Whether a tracking number looks wrong for its carrier.
 *
 * A warning only: the row is written either way and still counts as shipped
 * (_order_has_shipped() reads the number, never this). What is checked:
 *
 *   every number   letters, digits, spaces and hyphens, 5 to 40 of them and
 *                  starting with a letter or digit - a pasted link, a note
 *                  ("Aras: 123") or a stray character fails;
 *   UPS, FedEx,    one of the carrier's own recognition patterns;
 *   USPS
 *   Turkish        8 to 20 digits and nothing else (PTT: or a valid S10
 *   couriers       number).
 *
 * @param  string $number
 * @param  string $carrier a key of pg_shipping_carriers(), or '' when unknown
 * @return bool true when the number does not look like the carrier's
 */
function pg_shipment_number_warning($number, $carrier)
{
    $number = trim((string) $number);

    if ($number === '') {
        return false;
    }

    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{4,39}$/', $number) !== 1) {
        return true;
    }

    $carriers = pg_shipping_carriers();
    $carrier = pg_shipping_carrier_text($carrier);

    if (!isset($carriers[$carrier])) {
        return false;
    }

    $shapes = pg_shipment_number_shapes();

    if (isset($shapes[$carrier])) {

        if (preg_match('/^\d{8,20}$/', $number) === 1) {
            return false;
        }

        return !(($shapes[$carrier] === 'digits_or_s10') && pg_shipment_s10_valid($number));
    }

    if (empty($carriers[$carrier]['patterns'])) {
        return false;
    }

    foreach ($carriers[$carrier]['patterns'] as $pattern) {
        if (preg_match($pattern, $number) === 1) {
            return false;
        }
    }

    return true;
}

/**
 * Whether the 11.0.20 schema step has run: every column it adds to the
 * shipment row and the carrier column of shipping_methods. Until it has, the
 * functions below write the number alone, as before.
 *
 * @return bool
 */
function pg_shipment_rows_ready()
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    if (!function_exists('pg_schema_has')) {
        return false;
    }

    foreach (array('carrier', 'tracking_url', 'status', 'status_at', 'shipped_at', 'delivered_at', 'created_at', 'source', 'format_warning') as $column) {
        if (!pg_schema_has('shipping_tracking_numbers', $column)) {
            $ready = false;

            return $ready;
        }
    }

    $ready = pg_schema_has('shipping_methods', 'carrier');

    return $ready;
}

/**
 * Records one parcel of an address: the row is added, or the one already
 * there for the same number is updated in place - never deleted and written
 * again, so its id, its source and its dates survive every later save.
 *
 * The carrier kept on the row is one somebody named, in this order: the
 * caller ($opts['carrier'], a key or a carrier's name), the row itself from
 * an earlier write, the shipping method's carrier (shipping_methods.carrier),
 * then a carrier the method's code spells out. A caller that names nothing
 * leaves the row's own carrier alone, unless it says the carrier was chosen
 * ($opts['carrier_chosen']: the order screen's per-number select, where
 * "Automatic" is a choice that drops what the row had). The site's default carrier
 * and the number's shape are not written down: they stay guesses made at
 * read time, so correcting the default corrects every parcel that relied on
 * it. The format warning is worked out against the carrier the customer is
 * shown, guessed or named.
 *
 * A new row is 'created', shipped now (or at $opts['shipped_at']). An old row
 * whose shipped_at is still 0 (older than the schema, with a ship date that
 * was in the future then) takes the address's ship date once that day has
 * come; its status, source and dates are otherwise left alone. The number is
 * found again by the column's collation (1Z… and 1z… are one parcel, as the
 * unique key says) and stored as written now, so a corrected spelling is
 * kept and the format warning is the stored number's. A marketplace poll
 * leaves the spelling alone: it hands the same number back on every poll and
 * would undo a correction made on the order screen.
 *
 * $ship_to_id 0 is an order imported with no address at all: the parcel
 * hangs off the order, as the import always wrote it.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $number
 * @param array  $opts carrier, carrier_chosen (bool), tracking_url (the
 *                     carrier's own page), source (pg_shipment_sources()),
 *                     shipped_at (unix time)
 * @return array ok, id, created (the row did not exist), number (as stored),
 *               carrier (as stored), format_warning
 */
function pg_shipment_record($order_id, $ship_to_id, $number, $opts = array())
{
    $opts = (array) $opts + array(
        'carrier'        => '',
        'carrier_chosen' => false,
        'tracking_url'   => '',
        'source'         => 'panel',
        'shipped_at'     => 0,
    );

    $order_id = (int) $order_id;
    $ship_to_id = (int) $ship_to_id;
    $number = pg_shipment_number_clean($number);

    $out = array(
        'ok'             => false,
        'id'             => 0,
        'created'        => false,
        'number'         => $number,
        'carrier'        => '',
        'format_warning' => false,
    );

    if (($order_id <= 0) || ($ship_to_id < 0) || ($number === '')) {
        return $out;
    }

    if ($ship_to_id > 0) {
        $owner = (int) db_value("SELECT order_id FROM ship_tos WHERE id = '" . $ship_to_id . "'");
    } else {
        $owner = (int) db_value("SELECT id FROM orders WHERE id = '" . $order_id . "'");
    }

    if ($owner !== $order_id) {
        return $out;
    }

    // Recipient 0 is shared by every order without an address, so the order
    // is part of what makes the number the same parcel there.
    $same_parcel = "(ship_to_id = '" . $ship_to_id . "') AND (number = '" . e($number) . "')"
        . (($ship_to_id === 0) ? " AND (order_id = '" . $order_id . "')" : '');

    $respell = (pg_shipment_source($opts['source']) !== 'marketplace');

    if (!pg_shipment_rows_ready()) {

        $found = db_item("SELECT id, number FROM shipping_tracking_numbers WHERE " . $same_parcel . " LIMIT 1");
        $id = $found ? (int) $found['id'] : 0;

        if (($id > 0) && ((string) $found['number'] !== $number)) {
            if ($respell) {
                db("UPDATE shipping_tracking_numbers SET number = '" . e($number) . "' WHERE id = '" . $id . "'");
            } else {
                $out['number'] = (string) $found['number'];
            }
        }

        if ($id <= 0) {
            db("INSERT INTO shipping_tracking_numbers (order_id, ship_to_id, number)
                VALUES ('" . $order_id . "', '" . $ship_to_id . "', '" . e($number) . "')");

            $id = (int) mysqli_insert_id(db::$con);
            $out['created'] = true;
        }

        $out['ok'] = true;
        $out['id'] = $id;

        return $out;
    }

    $ship_to = array('ship_date' => '', 'shipping_method_code' => '', 'method_carrier' => '');

    if ($ship_to_id > 0) {
        $ship_to = (array) db_item(
            "SELECT
                ship_tos.ship_date,
                ship_tos.shipping_method_code,
                shipping_methods.carrier AS method_carrier
            FROM ship_tos
            LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
            WHERE ship_tos.id = '" . $ship_to_id . "'") + $ship_to;
    }

    $existing = db_item(
        "SELECT id, number, carrier, tracking_url, shipped_at
        FROM shipping_tracking_numbers
        WHERE " . $same_parcel . "
        LIMIT 1");

    $carrier = pg_shipping_method_carrier($opts['carrier']);

    if (($carrier === '') && empty($opts['carrier_chosen']) && $existing && pg_shipping_carrier_exists($existing['carrier'])) {
        $carrier = (string) $existing['carrier'];
    }

    if (($carrier === '') && pg_shipping_carrier_exists($ship_to['method_carrier'])) {
        $carrier = trim((string) $ship_to['method_carrier']);
    }

    if ($carrier === '') {
        $carrier = pg_shipping_method_carrier($ship_to['shipping_method_code']);
    }

    $tracking_url = pg_shipment_tracking_url_clean($opts['tracking_url']);

    if (($tracking_url === '') && $existing) {
        $tracking_url = (string) $existing['tracking_url'];
    }

    // The spelling the row keeps, which the carrier guess and the warning read.
    if ($existing && !$respell) {
        $out['number'] = (string) $existing['number'];
    }

    $shown = ($carrier !== '')
        ? $carrier
        : pg_shipping_carrier($out['number'], (string) $ship_to['shipping_method_code'], pg_shipping_default_carrier());

    $warning = pg_shipment_number_warning($out['number'], $shown) ? 1 : 0;

    $now = time();

    $out['carrier'] = $carrier;
    $out['format_warning'] = ($warning === 1);

    if ($existing) {

        $set = "number = '" . e($out['number']) . "',
            carrier = '" . e($carrier) . "',
            tracking_url = '" . e($tracking_url) . "',
            format_warning = '" . $warning . "'";

        if ((int) $existing['shipped_at'] === 0) {

            $ship_date = pg_shipment_date((string) $ship_to['ship_date']);

            if (($ship_date !== '') && ($ship_date <= date('Y-m-d', $now))) {
                $set .= ", shipped_at = '" . pg_shipment_day_time($ship_date, $now) . "'";
            }

        }

        db("UPDATE shipping_tracking_numbers SET " . $set . " WHERE id = '" . (int) $existing['id'] . "'");

        $out['ok'] = true;
        $out['id'] = (int) $existing['id'];

        return $out;
    }

    $shipped_at = ((int) $opts['shipped_at'] > 0) ? min((int) $opts['shipped_at'], $now) : $now;

    $values = "'" . $order_id . "', '" . $ship_to_id . "', '" . e($number) . "', '" . e($carrier) . "',
        '" . e($tracking_url) . "', 'created', '" . $now . "', '" . $shipped_at . "', '" . $now . "',
        '" . e(pg_shipment_source($opts['source'])) . "', '" . $warning . "'";

    $columns = "order_id, ship_to_id, number, carrier, tracking_url, status, status_at, shipped_at, created_at, source, format_warning";

    // Two writers can reach here for the same parcel at once (the order
    // screen and a marketplace poll). The unique key (ship_to_id, number)
    // settles it: the second insert becomes a no-op that hands back the
    // first one's id. Recipient 0 is not unique per order, so there a
    // collision is skipped instead of touching another order's row.
    if ($ship_to_id > 0) {
        db("INSERT INTO shipping_tracking_numbers (" . $columns . ") VALUES (" . $values . ")
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
    } else {
        db("INSERT IGNORE INTO shipping_tracking_numbers (" . $columns . ") VALUES (" . $values . ")");
    }

    $out['created'] = (mysqli_affected_rows(db::$con) === 1);
    $out['id'] = (int) mysqli_insert_id(db::$con);
    $out['ok'] = ($out['id'] > 0);

    return $out;
}

/**
 * Removes the parcels of an address that are not in a list: how the order
 * screen and the external API replace what an address has. The rows that are
 * in the list are not touched here (pg_shipment_record() updates them).
 *
 * Numbers compare the way the table's unique key does (its collation), so
 * "ABC123" in the list keeps a row stored as "abc123".
 *
 * @param  int      $ship_to_id
 * @param  string[] $numbers the numbers to keep
 * @return int rows removed
 */
function pg_shipment_prune($ship_to_id, $numbers)
{
    $ship_to_id = (int) $ship_to_id;

    if ($ship_to_id <= 0) {
        return 0;
    }

    $keep = '';

    foreach ((array) $numbers as $number) {

        $number = pg_shipment_number_clean($number);

        if ($number !== '') {
            $keep .= " AND (number != '" . e($number) . "')";
        }

    }

    db("DELETE FROM shipping_tracking_numbers WHERE (ship_to_id = '" . $ship_to_id . "')" . $keep);

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * An address's goods arrived: its parcels are marked delivered, and the first
 * time that happens order.delivered is announced and the order_delivered
 * automatic e-mail starts.
 *
 * Once per address. The rows are the record: the call that stamps parcels
 * none of which had arrived before is the one that announces, so the order
 * screen saved again, a marketplace that re-sends a delivered package on
 * every poll and two of those at the same moment all end in one event. A
 * parcel added after the address was delivered is stamped without a second
 * announcement, and a parcel's first arrival time is never moved.
 *
 * An address with no parcel number (sent without tracking) has no row to keep
 * the record on; there the caller's own guard decides ($opts['first']: the
 * delivery date it has just written was not there before).
 *
 * @param int   $ship_to_id
 * @param int   $time unix time the goods arrived; 0 is now
 * @param array $opts notify (default true: start the customer e-mail; false
 *                    for an import whose buyer the marketplace tells), first
 * @return bool true when this call announced the delivery
 */
function pg_shipment_delivered($ship_to_id, $time, $opts = array())
{
    $opts = (array) $opts + array(
        'notify' => true,
        'first'  => false,
    );

    $ship_to_id = (int) $ship_to_id;
    $now = time();
    $time = ((int) $time > 0) ? min((int) $time, $now) : $now;

    if ($ship_to_id <= 0) {
        return false;
    }

    $recipient = db_item(
        "SELECT
            ship_tos.id,
            ship_tos.order_id,
            ship_tos.delivery_date,
            orders.order_number,
            orders.contact_id,
            orders.billing_email_address
        FROM ship_tos
        INNER JOIN orders ON orders.id = ship_tos.order_id
        WHERE ship_tos.id = '" . $ship_to_id . "'");

    if (!$recipient) {
        return false;
    }

    $announce = !empty($opts['first']);

    if (pg_shipment_rows_ready()) {

        $parcels = db_item(
            "SELECT
                COUNT(*) AS parcels,
                COALESCE(SUM(delivered_at > 0), 0) AS delivered
            FROM shipping_tracking_numbers
            WHERE (ship_to_id = '" . $ship_to_id . "') AND (TRIM(number) != '')");

        if ((int) $parcels['parcels'] > 0) {

            db("UPDATE shipping_tracking_numbers
                SET
                    status = 'delivered',
                    status_at = '" . $now . "',
                    delivered_at = '" . $time . "'
                WHERE
                    (ship_to_id = '" . $ship_to_id . "')
                    AND (TRIM(number) != '')
                    AND (delivered_at = 0)");

            // Of two calls that both read "nothing arrived yet", only the one
            // whose update changed the rows gets here with a count.
            $announce = (mysqli_affected_rows(db::$con) > 0) && ((int) $parcels['delivered'] === 0);

        }

    }

    if (!$announce) {
        return false;
    }

    $day = pg_shipment_date((string) $recipient['delivery_date']);

    if ($day === '') {
        $day = date('Y-m-d', $time);
    }

    pg_announce('order.delivered', array(
        'id'            => (int) $recipient['order_id'],
        'order_number'  => $recipient['order_number'],
        'ship_to_id'    => $ship_to_id,
        'delivery_date' => $day,
    ));

    if (!empty($opts['notify'])) {

        create_auto_email_campaigns(array(
            'action'        => 'order_delivered',
            'contact_id'    => $recipient['contact_id'],
            'email_address' => $recipient['billing_email_address'],
            'fields'        => array_merge(
                array(array('name' => 'order_number', 'data' => $recipient['order_number'])),
                pg_shipment_mail_fields(array($ship_to_id))),
            'data'          => array('order' => array(
                'id'                    => (int) $recipient['order_id'],
                'order_number'          => $recipient['order_number'],
                'contact_id'            => $recipient['contact_id'],
                'billing_email_address' => $recipient['billing_email_address'],
                'recipients'            => array(array(
                    'id'            => $ship_to_id,
                    'delivery_date' => $day,
                )),
            )),
        ));

        pg_sms_order_delivered((int) $recipient['order_id'], $ship_to_id);

    }

    return true;
}

/**
 * The parcels of an address as the order screen lists them, oldest first.
 *
 * Before the 11.0.20 step the row is only a number; the other keys are filled
 * with what a new row would hold, so a reader needs no second code path.
 *
 * @param  int $ship_to_id
 * @return array list of id, number, carrier, tracking_url, status, status_at,
 *               shipped_at, delivered_at, source, format_warning
 */
function pg_shipment_rows($ship_to_id)
{
    $ship_to_id = (int) $ship_to_id;

    if ($ship_to_id <= 0) {
        return array();
    }

    $columns = pg_shipment_rows_ready()
        ? 'id, number, carrier, tracking_url, status, status_at, shipped_at, delivered_at, source, format_warning'
        : 'id, number';

    $rows = array();

    foreach (db_items("SELECT " . $columns . " FROM shipping_tracking_numbers WHERE ship_to_id = '" . $ship_to_id . "' ORDER BY id ASC") as $row) {
        $rows[] = $row + array(
            'carrier'        => '',
            'tracking_url'   => '',
            'status'         => 'created',
            'status_at'      => 0,
            'shipped_at'     => 0,
            'delivered_at'   => 0,
            'source'         => 'panel',
            'format_warning' => 0,
        );
    }

    return $rows;
}

/**
 * What a parcel row looks like to the people reading it: the carrier and the
 * link the row names, or else the ones worked out from the shipping method,
 * the site's default and the number's shape, the way every screen has always
 * worked them out.
 *
 * @param  array  $row     a row of pg_shipment_rows()
 * @param  string $method  ship_tos.shipping_method_code
 * @param  string $default the site's default carrier (pg_shipping_default_carrier())
 * @return array number, carrier (key or ''), named (the row holds the carrier),
 *               carrier_name, url, status, warning (bool)
 */
function pg_shipment_view($row, $method, $default)
{
    $row = (array) $row;
    $number = isset($row['number']) ? trim((string) $row['number']) : '';
    $carriers = pg_shipping_carriers();

    $carrier = isset($row['carrier']) ? pg_shipping_carrier_text($row['carrier']) : '';
    $named = isset($carriers[$carrier]);

    if (!$named) {
        $carrier = pg_shipping_carrier($number, (string) $method, (string) $default);
    }

    $url = isset($row['tracking_url']) ? pg_shipment_tracking_url_clean($row['tracking_url']) : '';

    if (($url === '') && ($carrier !== '') && ($number !== '')) {
        $url = str_replace('{code}', rawurlencode($number), $carriers[$carrier]['url']);
    }

    $status = (isset($row['status']) && in_array($row['status'], pg_shipment_statuses(), true)) ? $row['status'] : 'created';

    return array(
        'number'       => $number,
        'carrier'      => $carrier,
        'named'        => $named,
        'carrier_name' => ($carrier !== '') ? $carriers[$carrier]['name'] : '',
        'url'          => $url,
        'status'       => $status,
        'warning'      => !empty($row['format_warning']),
    );
}

/**
 * The mail-merge fields the order_shipped and order_delivered e-mails offer:
 * ^^tracking_numbers^^ (every number), ^^carrier_name^^ (each carrier once)
 * and ^^tracking_url^^ (the first parcel that has a link: one link is what an
 * href can hold). Always all three, empty when there is no parcel, so a
 * [[...||...]] conditional falls to its else part.
 *
 * @param  array $views rows of pg_shipment_view()
 * @return array list of name, data
 */
function pg_shipment_merge_fields($views)
{
    $numbers = array();
    $carriers = array();
    $url = '';

    foreach ((array) $views as $view) {
        $number = isset($view['number']) ? trim((string) $view['number']) : '';

        if ($number === '') {
            continue;
        }

        $numbers[] = $number;
        $carrier = isset($view['carrier_name']) ? (string) $view['carrier_name'] : '';

        if (($carrier !== '') && !in_array($carrier, $carriers, true)) {
            $carriers[] = $carrier;
        }

        if (($url === '') && !empty($view['url'])) {
            $url = (string) $view['url'];
        }
    }

    return array(
        array('name' => 'tracking_numbers', 'data' => implode(', ', $numbers)),
        array('name' => 'carrier_name', 'data' => implode(', ', $carriers)),
        array('name' => 'tracking_url', 'data' => $url),
    );
}

/**
 * The tracking mail-merge fields of the given addresses of one order, read
 * from their parcel rows the way the order screen shows them.
 *
 * @param  int[] $ship_to_ids
 * @return array list of name, data (pg_shipment_merge_fields())
 */
function pg_shipment_mail_fields($ship_to_ids)
{
    $default = pg_shipping_default_carrier();
    $views = array();

    foreach (array_unique(array_map('intval', (array) $ship_to_ids)) as $ship_to_id) {
        if ($ship_to_id <= 0) {
            continue;
        }

        $method = (string) db_value("SELECT shipping_method_code FROM ship_tos WHERE id = '" . $ship_to_id . "'");

        foreach (pg_shipment_rows($ship_to_id) as $row) {
            $views[] = pg_shipment_view($row, $method, $default);
        }
    }

    return pg_shipment_merge_fields($views);
}

/**
 * The name of a parcel's status, for a badge.
 *
 * @param  string $status
 * @return string
 */
function pg_shipment_status_label($status)
{
    switch ((string) $status) {
        case 'in_transit':
            return lang('In transit');
        case 'out_for_delivery':
            return lang('Out for delivery');
        case 'delivered':
            return lang('Delivered');
        case 'returned':
            return lang('Returned to sender');
        case 'exception':
            return lang('Delivery problem');
        default:
            return lang('Recorded');
    }
}

/**
 * The badge colour of a parcel's status.
 *
 * @param  string $status
 * @return string Bootstrap classes
 */
function pg_shipment_status_class($status)
{
    switch ((string) $status) {
        case 'in_transit':
        case 'out_for_delivery':
            return 'text-bg-primary';
        case 'delivered':
            return 'text-bg-success';
        case 'returned':
            return 'text-bg-warning';
        case 'exception':
            return 'text-bg-danger';
        default:
            return 'text-bg-secondary';
    }
}

/**
 * The options of a parcel's carrier select: "Automatic" first, naming the
 * carrier it currently resolves to when there is one, then every carrier.
 * Labels are escaped here, because the select prints them as they are.
 *
 * @param  string $automatic the carrier name "Automatic" resolves to, or ''
 * @return array label => value, as liveform::output_field() takes them
 */
function pg_shipment_carrier_options($automatic)
{
    $automatic = trim((string) $automatic);

    $options = array(
        h(($automatic !== '') ? lang(array('string' => 'Automatic ({var:1})', 'vars' => array($automatic))) : lang('Automatic')) => '',
    );

    foreach (pg_shipping_carriers() as $carrier => $entry) {
        $options[h($entry['name'])] = $carrier;
    }

    return $options;
}

/**
 * The note beside a number whose shape does not fit its carrier.
 *
 * @param  string $carrier_name the carrier the number was checked against, or ''
 * @return string
 */
function pg_shipment_warning_text($carrier_name)
{
    $carrier_name = trim((string) $carrier_name);

    if ($carrier_name === '') {
        return lang('This does not look like a tracking number. It was saved anyway.');
    }

    return lang(array('string' => 'This does not look like a {var:1} tracking number. It was saved anyway.', 'vars' => array($carrier_name)));
}
