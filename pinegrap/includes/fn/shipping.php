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
 * A status a carrier's service reported that pg_shipment_record() writes on
 * a parcel: any of pg_shipment_statuses() but delivered, which only
 * pg_shipment_delivered() records, since an arrival is announced.
 *
 * @param  mixed $status
 * @return string '' when it is none of them
 */
function pg_shipment_status_reported($status)
{
    return (is_string($status) && ($status !== 'delivered') && in_array($status, pg_shipment_statuses(), true)) ? $status : '';
}

/**
 * Who wrote a row: the order screen (and the Workspace, which saves through
 * it), the external API, a marketplace import, a carrier's own API, a CSV
 * upload, a ShipWorks shipment update.
 *
 * @return string[]
 */
function pg_shipment_sources()
{
    return array('panel', 'api', 'marketplace', 'carrier', 'csv', 'shipworks');
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
 * A carrier's service that follows a parcel (the shipment tracking job)
 * passes where it stands now ($opts['status'], pg_shipment_status_reported(),
 * at $opts['status_at']). It is written on a row that is there, only when it
 * changes, so status_at is when it last changed, and never over an arrival:
 * delivered is recorded by pg_shipment_delivered() alone, which announces it.
 * With $opts['existing_only'] no row is added, so a parcel removed from its
 * address since the caller read it stays removed.
 *
 * $ship_to_id 0 is an order imported with no address at all: the parcel
 * hangs off the order, as the import always wrote it.
 *
 * @param int    $order_id
 * @param int    $ship_to_id
 * @param string $number
 * @param array  $opts carrier, carrier_chosen (bool), tracking_url (the
 *                     carrier's own page), source (pg_shipment_sources()),
 *                     shipped_at (unix time), status (a carrier's, see
 *                     above), status_at (unix time; 0 is now),
 *                     existing_only (bool)
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
        'status'         => '',
        'status_at'      => 0,
        'existing_only'  => false,
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
            if (!empty($opts['existing_only'])) {
                return $out;
            }

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

    if (!$existing && !empty($opts['existing_only'])) {
        return $out;
    }

    // A carrier someone chose (the order screen's select, the API's carrier)
    // is kept as typed when the store does not know it; a carrier another
    // writer passes along (a marketplace's or a label service's text) only
    // when it is known, so those rows keep the recognised one.
    $carrier = empty($opts['carrier_chosen'])
        ? pg_shipping_method_carrier($opts['carrier'])
        : pg_shipment_carrier_value($opts['carrier']);

    if (($carrier === '') && empty($opts['carrier_chosen']) && $existing && (trim((string) $existing['carrier']) !== '')) {
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

        // A status of its own statement, so its guard holds against a
        // delivery pg_shipment_delivered() recorded since the row was read.
        $status = pg_shipment_status_reported($opts['status']);

        if ($status !== '') {
            $status_at = ((int) $opts['status_at'] > 0) ? min((int) $opts['status_at'], $now) : $now;

            db("UPDATE shipping_tracking_numbers
                SET status = '" . e($status) . "', status_at = '" . $status_at . "'
                WHERE (id = '" . (int) $existing['id'] . "') AND (delivered_at = 0) AND (status != '" . e($status) . "')");
        }

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
        'parcels'       => pg_shipment_api_parcels($ship_to_id),
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
 * A row may hold a carrier the store does not know, kept as it was typed
 * (pg_shipment_carrier_value()): it is named, with no key and no link but the
 * row's own, and nothing is guessed in its place, since the guess would name
 * a carrier that never had the parcel.
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

    $typed = isset($row['carrier']) ? trim((string) $row['carrier']) : '';
    $carrier = pg_shipping_carrier_text($typed);
    $named = isset($carriers[$carrier]);
    $name = $named ? $carriers[$carrier]['name'] : '';

    if (!$named && ($typed !== '')) {
        $carrier = '';
        $named = true;
        $name = $typed;
    } elseif (!$named) {
        $carrier = pg_shipping_carrier($number, (string) $method, (string) $default);
        $name = ($carrier !== '') ? $carriers[$carrier]['name'] : '';
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
        'carrier_name' => $name,
        'url'          => $url,
        'status'       => $status,
        'warning'      => !empty($row['format_warning']),
    );
}

/**
 * One parcel as the external API's shipments and the order.shipped and
 * order.delivered webhooks give it: the carrier and the link the order screen
 * shows (pg_shipment_view()), the status and the two dates as UTC times. What
 * is not known is null, not an empty string.
 *
 * @param  array  $row     a row of pg_shipment_rows()
 * @param  string $method  ship_tos.shipping_method_code
 * @param  string $default the site's default carrier (pg_shipping_default_carrier())
 * @return array number, carrier (key), carrier_name, tracking_url, status,
 *               shipped_at, delivered_at
 */
function pg_shipment_api_parcel($row, $method, $default)
{
    $row = (array) $row;
    $view = pg_shipment_view($row, $method, $default);

    $time = function ($key) use ($row) {
        $unix = isset($row[$key]) ? (int) $row[$key] : 0;

        return ($unix > 0) ? gmdate('Y-m-d\TH:i:s\Z', $unix) : null;
    };

    return array(
        'number'       => $view['number'],
        'carrier'      => ($view['carrier'] !== '') ? $view['carrier'] : null,
        'carrier_name' => ($view['carrier_name'] !== '') ? $view['carrier_name'] : null,
        'tracking_url' => ($view['url'] !== '') ? $view['url'] : null,
        'status'       => $view['status'],
        'shipped_at'   => $time('shipped_at'),
        'delivered_at' => $time('delivered_at'),
    );
}

/**
 * The numbered parcels of an address, each as pg_shipment_api_parcel() gives
 * it, oldest first.
 *
 * @param  int         $ship_to_id
 * @param  string|null $method ship_tos.shipping_method_code when the caller
 *                             has it; null reads it
 * @param  string|null $number only the parcel with this number
 * @return array
 */
function pg_shipment_api_parcels($ship_to_id, $method = null, $number = null)
{
    $ship_to_id = (int) $ship_to_id;

    if ($ship_to_id <= 0) {
        return array();
    }

    if ($method === null) {
        $method = (string) db_value("SELECT shipping_method_code FROM ship_tos WHERE id = '" . $ship_to_id . "'");
    }

    $default = pg_shipping_default_carrier();
    $parcels = array();

    foreach (pg_shipment_rows($ship_to_id) as $row) {
        $parcel = pg_shipment_api_parcel($row, (string) $method, $default);

        if (($parcel['number'] === '') || (($number !== null) && (strcasecmp($parcel['number'], (string) $number) !== 0))) {
            continue;
        }

        $parcels[] = $parcel;
    }

    return $parcels;
}

/**
 * What a parcel row keeps for a carrier someone chose: the key of a carrier
 * the store knows (pg_shipping_method_carrier()), else the name as typed, cut
 * to the 20 characters of shipping_tracking_numbers.carrier.
 *
 * @param  mixed $carrier a carrier key or name
 * @return string key, typed name or ''
 */
function pg_shipment_carrier_value($carrier)
{
    $carrier = trim((string) $carrier);
    $key = pg_shipping_method_carrier($carrier);

    return ($key !== '') ? $key : trim(mb_substr($carrier, 0, 20, 'UTF-8'));
}

/**
 * The carrier part of the POST /orders/{id}/shipment answer. A carrier the
 * store knows gives its key and name; any other is accepted and kept as typed
 * (a local courier, a marketplace's own code), so the answer gives no key,
 * the name and a warning that it brings no tracking link. Nothing sent gives
 * three nulls.
 *
 * @param  mixed $carrier the carrier the call sent
 * @return array carrier (key or null), carrier_name, warning (string or null)
 */
function pg_shipment_api_carrier($carrier)
{
    $carrier = trim((string) $carrier);

    if ($carrier === '') {
        return array('carrier' => null, 'carrier_name' => null, 'warning' => null);
    }

    $key = pg_shipping_method_carrier($carrier);

    if ($key !== '') {
        $carriers = pg_shipping_carriers();

        return array('carrier' => $key, 'carrier_name' => $carriers[$key]['name'], 'warning' => null);
    }

    return array(
        'carrier'      => null,
        'carrier_name' => pg_shipment_carrier_value($carrier),
        'warning'      => lang(array(
            'string' => 'The carrier {var:1} is not one this store recognises: it was kept as sent, and its tracking numbers get no tracking link.',
            'vars'   => array($carrier),
        )),
    );
}

/**
 * The tracking numbers of a POST /orders/{id}/shipment call as update_order()
 * takes them, with the carrier the call names on each one: the key of a
 * carrier the store knows, else the name as typed (pg_shipment_carrier_value()),
 * which the parcel keeps without a link. No carrier sends the numbers plain,
 * so each row keeps the carrier it had.
 *
 * @param  string[] $numbers
 * @param  string   $carrier a carrier key or name, or ''
 * @return array numbers, or list of number, carrier
 */
function pg_shipment_numbers_with_carrier($numbers, $carrier)
{
    $numbers = array_values((array) $numbers);
    $carrier = pg_shipment_carrier_value($carrier);

    if ($carrier === '') {
        return $numbers;
    }

    $entries = array();

    foreach ($numbers as $number) {
        $entries[] = array('number' => $number, 'carrier' => $carrier);
    }

    return $entries;
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
            return 'theme-primary';
        case 'delivered':
            return 'theme-success';
        case 'returned':
            return 'theme-warning';
        case 'exception':
            return 'theme-danger';
        default:
            return 'theme-secondary';
    }
}

/**
 * The options of a parcel's carrier select: "Automatic" first, naming the
 * carrier it currently resolves to when there is one, then every carrier.
 * Labels are escaped here, because the select prints them as they are.
 *
 * A carrier the row keeps as typed (one the store does not know) is offered
 * too, so saving the screen keeps it; its value is escaped like its label,
 * because the select prints values as they are as well.
 *
 * @param  string $automatic the carrier name "Automatic" resolves to, or ''
 * @param  string $typed     the row's carrier when it is no key, or ''
 * @return array label => value, as liveform::output_field() takes them
 */
function pg_shipment_carrier_options($automatic, $typed = '')
{
    $automatic = trim((string) $automatic);
    $typed = trim((string) $typed);

    $options = array(
        h(($automatic !== '') ? lang(array('string' => 'Automatic ({var:1})', 'vars' => array($automatic))) : lang('Automatic')) => '',
    );

    $carriers = pg_shipping_carriers();

    foreach ($carriers as $carrier => $entry) {
        $options[h($entry['name'])] = $carrier;
    }

    if (($typed !== '') && !isset($carriers[$typed])) {
        $options[h($typed)] = h($typed);
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

/**
 * The province a Turkish postcode points to, when it is not the address's
 * own province; '' when they agree or the address cannot be checked.
 *
 * A Turkish postcode starts with the province's plate number (34xxx is
 * İstanbul, 06xxx Ankara), so the first two digits are compared with the
 * plate number of the province in the state field (since #2004 an n11 import
 * writes the province there too). Only a TR address with a five-digit postcode
 * and a state the sales map recognises, old spellings included, is checked:
 * older n11 imports kept the district in the state field, and those, other
 * countries and empty or malformed postcodes give ''. The answer is only
 * shown as a note; nothing is blocked or changed.
 *
 * @param  string $country  the stored country code
 * @param  string $state    the stored state (province)
 * @param  string $postcode the stored postcode
 * @return string the province name the postcode belongs to, or ''
 */
function pg_shipping_postcode_province($country, $state, $postcode)
{
    $country = is_scalar($country) ? strtoupper(trim((string) $country)) : '';
    $state = is_scalar($state) ? trim((string) $state) : '';
    $postcode = is_scalar($postcode) ? trim((string) $postcode) : '';

    if (($country !== 'TR') || ($state === '') || !preg_match('/^[0-9]{5}$/', $postcode)) {
        return '';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    $code = pg_sales_map_match_region('TR', $state);
    $regions = pg_sales_map_tr_regions();
    $postcode_code = 'TR-' . substr($postcode, 0, 2);

    if (($code === '') || !isset($regions[$postcode_code]) || ($postcode_code === $code)) {
        return '';
    }

    return $regions[$postcode_code];
}

/**
 * Whether step 11.0.134 has run: ship_tos.district exists. Until it has, an
 * address is written and copied without its district, as before.
 *
 * @return bool
 */
function pg_ship_to_district_ready()
{
    return function_exists('pg_schema_has') && pg_schema_has('ship_tos', 'district');
}

/**
 * Whether a country, as a code or a name ('TR', 'Turkey', 'Türkiye'), is
 * Turkey. Pure.
 *
 * @param  string $country countries.code or countries.name
 * @param  string $name    countries.name when $country is the code
 * @return bool
 */
function pg_ship_to_district_country_is_tr($country, $name = '')
{
    $country = is_scalar($country) ? trim((string) $country) : '';
    $name = is_scalar($name) ? trim((string) $name) : '';

    if (($country === '') && ($name === '')) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    return pg_sales_map_canonical_country($country, ($name !== '') ? $name : $country) === 'TR';
}

/**
 * The codes of the shop's countries rows that stand for Turkey, for the
 * script that shows the box when one of them is picked.
 *
 * @return array list of countries.code
 */
function pg_ship_to_district_tr_codes()
{
    static $codes = null;

    if ($codes !== null) {
        return $codes;
    }

    $codes = array();

    foreach ((array) db_items("SELECT code, name FROM countries") as $row) {
        if (((string) $row['code'] !== '') && pg_ship_to_district_country_is_tr((string) $row['code'], (string) $row['name'])) {
            $codes[] = (string) $row['code'];
        }
    }

    return $codes;
}

/**
 * Whether a carrier's label driver asks for the recipient's district. A
 * driver says so by defining pg_label_<key>_needs_district() that answers
 * true; none does yet, so the Turkey rule alone draws the box for now.
 *
 * @param  string $carrier a pg_shipping_carriers() key, '' when not known
 * @return bool
 */
function pg_ship_to_district_carrier_asks($carrier)
{
    $carrier = (string) $carrier;

    if (($carrier === '') || !function_exists('pg_label_drivers') || !function_exists('pg_label_info')) {
        return false;
    }

    foreach (array_keys(pg_label_drivers()) as $key) {
        $ask = 'pg_label_' . $key . '_needs_district';

        if (function_exists($ask) && in_array($carrier, (array) pg_label_info($key)['carriers'], true) && $ask()) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the optional district box is drawn for an address: only once
 * ship_tos.district exists, then for a Turkish address or one whose carrier's
 * driver asks for it.
 *
 * @param  string    $country countries.code or countries.name of the address
 * @param  bool      $carrier_asks pg_ship_to_district_carrier_asks()
 * @param  bool|null $ready pg_ship_to_district_ready(), asked when null
 * @return bool
 */
function pg_ship_to_district_shown($country, $carrier_asks = false, $ready = null)
{
    if ($ready === null) {
        $ready = pg_ship_to_district_ready();
    }

    if (!$ready) {
        return false;
    }

    return $carrier_asks || pg_ship_to_district_country_is_tr($country);
}

/**
 * A typed district as it is stored: one line, trimmed, at most 50
 * characters (cut by character, so a Turkish letter is never split). Empty
 * is allowed: the box is never required. Pure.
 *
 * @param  mixed  $value
 * @return string
 */
function pg_ship_to_district_clean($value)
{
    $value = is_scalar($value) ? (string) $value : '';
    // Line breaks and control characters become one space; a value that is
    // not UTF-8 is dropped rather than stored broken.
    $value = preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $value);
    $value = ($value === null) ? '' : trim($value);

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return trim(mb_substr($value, 0, 50, 'UTF-8'));
    }

    return trim(substr($value, 0, 50));
}

/**
 * The ", district = '…'" part of an UPDATE or INSERT … SET of ship_tos, or ''
 * while the column is missing, so a save before step 11.0.134 writes the
 * address without it, as before.
 *
 * @param  mixed     $value the typed district
 * @param  bool|null $ready pg_ship_to_district_ready(), asked when null
 * @return string
 */
function pg_ship_to_district_set_sql($value, $ready = null)
{
    if ($ready === null) {
        $ready = pg_ship_to_district_ready();
    }

    if (!$ready) {
        return '';
    }

    return ", district = '" . escape(pg_ship_to_district_clean($value)) . "'";
}

/**
 * Whether the carrier of a shipping method asks for the district
 * (pg_ship_to_district_carrier_asks()); no for a method without a carrier or
 * before the carrier column exists.
 *
 * @param  int  $method_id shipping_methods.id
 * @return bool
 */
function pg_ship_to_district_method_asks($method_id)
{
    $method_id = (int) $method_id;

    if (($method_id <= 0) || !pg_shipment_rows_ready()) {
        return false;
    }

    return pg_ship_to_district_carrier_asks((string) db_value("SELECT carrier FROM shipping_methods WHERE id = '" . $method_id . "'"));
}

/**
 * The district part of a ship_tos UPDATE for a posted address: the typed
 * value while the box applies to the address, '' when it does not (a box
 * left over from another country is not kept), nothing at all while the
 * column is missing.
 *
 * @param  mixed $value     the posted district
 * @param  string $country  the posted country code
 * @param  int   $method_id the address's shipping method, 0 when not chosen yet
 * @return string pg_ship_to_district_set_sql()
 */
function pg_ship_to_district_address_sql($value, $country, $method_id = 0)
{
    if (!pg_ship_to_district_ready()) {
        return '';
    }

    $shown = pg_ship_to_district_shown($country, pg_ship_to_district_method_asks($method_id), true);

    return pg_ship_to_district_set_sql($shown ? $value : '', true);
}

/**
 * Writes the district of a posted address on its recipient
 * (pg_ship_to_district_address_sql(), with the recipient's own shipping
 * method); nothing while the column is missing.
 *
 * @param  int    $ship_to_id
 * @param  mixed  $value   the posted district
 * @param  string $country the posted country code
 * @return void
 */
function pg_ship_to_district_save($ship_to_id, $value, $country)
{
    $ship_to_id = (int) $ship_to_id;

    if (($ship_to_id <= 0) || !pg_ship_to_district_ready()) {
        return;
    }

    $method_id = (int) db_value("SELECT shipping_method_id FROM ship_tos WHERE id = '" . $ship_to_id . "'");
    $set = pg_ship_to_district_address_sql($value, $country, $method_id);

    if ($set !== '') {
        db("UPDATE ship_tos SET " . substr($set, 2) . " WHERE id = '" . $ship_to_id . "'");
    }
}

/**
 * The path (child indexes) to the first node of a designer tree $match says
 * yes to, or null. Pure.
 *
 * @param  array    $tree
 * @param  callable $match receives a node
 * @return array|null
 */
function pg_ship_to_district_tree_path($tree, $match)
{
    if (!is_array($tree)) {
        return null;
    }

    if ($match($tree)) {
        return array();
    }

    if (!empty($tree['children']) && is_array($tree['children'])) {
        foreach ($tree['children'] as $i => $child) {
            $path = pg_ship_to_district_tree_path($child, $match);

            if ($path !== null) {
                return array_merge(array($i), $path);
            }
        }
    }

    return null;
}

/**
 * Whether a node only wraps its control and labels (a field's column), so it
 * goes and is hidden with the control. Pure.
 *
 * @param  array $node
 * @return bool
 */
function pg_ship_to_district_tree_is_wrap($node)
{
    if (!is_array($node) || empty($node['children']) || !is_array($node['children']) || (count($node['children']) > 3)) {
        return false;
    }

    $controls = 0;

    foreach ($node['children'] as $child) {
        $tag = isset($child['props']['tag']) ? strtolower((string) $child['props']['tag']) : '';

        if (in_array($tag, array('input', 'select', 'textarea'), true)) {
            $controls++;
        } elseif (!in_array($tag, array('label', 'small', 'span'), true)) {
            return false;
        }
    }

    return $controls === 1;
}

/**
 * Fits the district box into a widget's designer tree. While the column is
 * missing a district control (and the column that wraps it) is removed. Once
 * it exists, a tree designed before the box gets $node right after the City
 * control's column, so every saved checkout page draws it; a tree that has
 * the control keeps it where the designer put it. Pure.
 *
 * @param  array    $tree
 * @param  callable $is_city     receives a node: the City control
 * @param  callable $is_district receives a node: the district control
 * @param  array    $node        the field to add (a column with a label and the control)
 * @param  bool     $ready       pg_ship_to_district_ready()
 * @return void
 */
function pg_ship_to_district_tree_fit(&$tree, $is_city, $is_district, $node, $ready)
{
    $path = pg_ship_to_district_tree_path($tree, $is_district);

    if (!$ready) {
        if (($path === null) || (count($path) === 0)) {
            return;
        }

        $parent_path = array_slice($path, 0, -1);
        $parent = pg_ship_to_district_tree_node($tree, $parent_path);

        if ((count($parent_path) > 0) && pg_ship_to_district_tree_is_wrap($parent)) {
            $path = $parent_path;
        }

        pg_ship_to_district_tree_splice($tree, $path, null);

        return;
    }

    if ($path !== null) {
        return;
    }

    $path = pg_ship_to_district_tree_path($tree, $is_city);

    if (($path === null) || (count($path) === 0)) {
        return;
    }

    $parent_path = array_slice($path, 0, -1);

    if ((count($parent_path) > 0) && pg_ship_to_district_tree_is_wrap(pg_ship_to_district_tree_node($tree, $parent_path))) {
        $path = $parent_path;
    }

    pg_ship_to_district_tree_splice($tree, $path, $node);
}

// The node at $path of a tree, null when there is none.
function pg_ship_to_district_tree_node($tree, $path)
{
    foreach ($path as $i) {
        if (!isset($tree['children'][$i])) {
            return null;
        }

        $tree = $tree['children'][$i];
    }

    return $tree;
}

// Removes the node at $path ($add null) or puts $add right after it.
function pg_ship_to_district_tree_splice(&$tree, $path, $add)
{
    $last = array_pop($path);
    $parent = &$tree;

    foreach ($path as $i) {
        $parent = &$parent['children'][$i];
    }

    $children = array_values($parent['children']);
    array_splice($children, $last + (($add === null) ? 0 : 1), ($add === null) ? 1 : 0, ($add === null) ? array() : array($add));
    $parent['children'] = $children;
    unset($parent);
}

/**
 * Gives the district control its attributes for the show script
 * (pg_ship_to_district_script()): the name of the country control it
 * follows, the codes that stand for Turkey, whether the carrier asks for it
 * anyway, at most 50 characters; its column is marked as what is hidden.
 * Pure.
 *
 * @param  array    $tree
 * @param  callable $is_district receives a node: the district control
 * @param  string   $country_name name of the country control
 * @param  array    $tr_codes     pg_ship_to_district_tr_codes()
 * @param  bool     $asks         the carrier asks for the district
 * @return void
 */
function pg_ship_to_district_tree_mark(&$tree, $is_district, $country_name, $tr_codes, $asks)
{
    $path = pg_ship_to_district_tree_path($tree, $is_district);

    if ($path === null) {
        return;
    }

    $attrs = array(
        'data-pg-district' => (string) $country_name,
        'data-pg-district-codes' => implode(',', (array) $tr_codes),
        'maxlength' => '50',
    );

    if ($asks) {
        $attrs['data-pg-district-asks'] = '1';
    }

    $last = array_pop($path);
    $node = &$tree;

    foreach ($path as $i) {
        $node = &$node['children'][$i];
    }

    if ((count($path) > 0) && pg_ship_to_district_tree_is_wrap($node)) {
        pg_ship_to_district_node_attrs($node, array('data-pg-district-wrap' => ''));
    }

    pg_ship_to_district_node_attrs($node['children'][$last], $attrs);
    unset($node);
}

// Sets attributes on a node (props._attrs), replacing ones of the same name.
function pg_ship_to_district_node_attrs(&$node, $attrs)
{
    $list = (isset($node['props']['_attrs']) && is_array($node['props']['_attrs'])) ? $node['props']['_attrs'] : array();
    $list = array_values(array_filter($list, function ($a) use ($attrs) {
        return !(is_array($a) && isset($a['name']) && array_key_exists((string) $a['name'], $attrs));
    }));

    foreach ($attrs as $name => $value) {
        $list[] = array('name' => $name, 'value' => (string) $value);
    }

    $node['props']['_attrs'] = $list;
}

/**
 * The script that shows each district box only while its country is Turkey
 * (or its carrier asks for it), following the country control as the
 * visitor changes it. Once per page.
 *
 * @return string
 */
function pg_ship_to_district_script()
{
    static $done = false;

    if ($done) {
        return '';
    }

    $done = true;

    return '<script>(function(){'
        . 'function sync(i){var f=i.form,c=f?f.elements[i.getAttribute("data-pg-district")]:null;'
        .   'var w=i.closest("[data-pg-district-wrap]")||i,codes=(i.getAttribute("data-pg-district-codes")||"").split(",");'
        .   'var on=i.hasAttribute("data-pg-district-asks")||(c&&codes.indexOf(c.value)!==-1);w.hidden=!on;}'
        . 'function all(){document.querySelectorAll("[data-pg-district]").forEach(sync);}'
        . 'document.addEventListener("change",function(e){var t=e.target;if(!t||!t.name||!t.form)return;'
        .   't.form.querySelectorAll("[data-pg-district]").forEach(function(i){if(i.getAttribute("data-pg-district")===t.name)sync(i);});});'
        . 'if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",all);else all();'
        . '})();</script>';
}

/**
 * A Turkish province's licence plate number from its name ('İstanbul' ->
 * '34'), or '' for a name no province carries. Accents, case and spacing are
 * folded the way the sales map folds them, so 'IĞDIR', 'Iğdır' and the older
 * spellings the states table ships ('Afyon', 'Hakkari') all match.
 *
 * @param  string $name a states.name value
 * @return string two digits or ''
 */
function pg_state_plate_code($name)
{
    $name = is_scalar($name) ? trim((string) $name) : '';

    if ($name === '') {
        return '';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    $code = pg_sales_map_match_region('TR', $name);

    return ($code === '') ? '' : substr($code, 3);
}

/**
 * The plate codes to write into Turkish states rows: id => plate number for
 * each row whose plate_code is empty and whose name is a province. A row
 * that already has a plate code, whoever wrote it, is left out.
 *
 * @param  array $rows each: id, name, plate_code (missing reads as empty)
 * @return array int id => string plate number
 */
function pg_state_plate_codes($rows)
{
    $codes = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || !isset($row['id'])) {
            continue;
        }

        if (isset($row['plate_code']) && (trim((string) $row['plate_code']) !== '')) {
            continue;
        }

        $plate = pg_state_plate_code(isset($row['name']) ? $row['name'] : '');

        if ($plate !== '') {
            $codes[(int) $row['id']] = $plate;
        }
    }

    return $codes;
}

/**
 * The ids of the countries rows that stand for Turkey, read the way the sales
 * map reads a row: its code when that is a country code, else its name, else
 * its code as a name (a row whose code is 'TÜRKİYE').
 *
 * @param  array $rows each: id, name, code
 * @return array int ids
 */
function pg_state_plate_country_ids($rows)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    $ids = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row) || !isset($row['id'])) {
            continue;
        }

        $code = isset($row['code']) ? $row['code'] : '';
        $name = isset($row['name']) ? $row['name'] : '';

        if (pg_sales_map_canonical_country($code, $name) === 'TR') {
            $ids[] = (int) $row['id'];
        }
    }

    return $ids;
}

/**
 * The note under an address whose postcode belongs to another province.
 *
 * @param  string $province the province the postcode points to
 * @return string
 */
function pg_shipping_postcode_province_text($province)
{
    return lang(array('string' => 'This postcode belongs to {var:1}, not to the province given. Check the address before shipping.', 'vars' => array($province)));
}

/**
 * The Shipped and Delivered steps of the order view timeline. A step is a
 * fact only when a record says so; otherwise it is the checkout estimate,
 * even after the estimated day has passed.
 *
 * An address counts as shipped when it has a numbered parcel or a shipped
 * quantity ("Mark Shipped" writes the quantity and no parcel). Its day is its
 * earliest parcel shipped_at, else its ship_date. The earliest shipped address
 * wins; with none shipped, the earliest ship_date of all is the estimate.
 * Delivered is the earliest parcel delivered_at, else the earliest
 * delivery_date as an estimate.
 *
 * @param  array $addresses each: ship_date, delivery_date (Y-m-d, '' or
 *                          '0000-00-00'), shipped_quantity (int), parcels
 *                          (rows with shipped_at, delivered_at unix ints, 0
 *                          when unknown)
 * @return array shipped => {ts, estimate}, delivered => {ts, estimate}; ts 0
 *               is no step
 */
function pg_shipment_timeline_steps($addresses)
{
    $day = function ($date) {
        $date = trim((string) $date);
        if ($date === '' || $date === '0000-00-00') {
            return 0;
        }
        $ts = strtotime($date);

        return ($ts > 0) ? $ts : 0;
    };
    $earliest = function ($current, $ts) {
        return ($ts > 0 && ($current === 0 || $ts < $current)) ? $ts : $current;
    };

    $shipped_real = 0;
    $shipped_estimate = 0;
    $delivered_real = 0;
    $delivered_estimate = 0;

    foreach ((array) $addresses as $address) {
        if (!is_array($address)) {
            continue;
        }
        $ship_day = $day(isset($address['ship_date']) ? $address['ship_date'] : '');
        $shipped_estimate = $earliest($shipped_estimate, $ship_day);
        $delivered_estimate = $earliest($delivered_estimate, $day(isset($address['delivery_date']) ? $address['delivery_date'] : ''));

        $parcels = isset($address['parcels']) ? (array) $address['parcels'] : array();
        $parcel_shipped = 0;
        foreach ($parcels as $parcel) {
            $parcel_shipped = $earliest($parcel_shipped, isset($parcel['shipped_at']) ? (int) $parcel['shipped_at'] : 0);
            $delivered_real = $earliest($delivered_real, isset($parcel['delivered_at']) ? (int) $parcel['delivered_at'] : 0);
        }

        $shipped = !empty($parcels) || (isset($address['shipped_quantity']) && (int) $address['shipped_quantity'] > 0);
        if ($shipped) {
            $shipped_real = $earliest($shipped_real, ($parcel_shipped > 0) ? $parcel_shipped : $ship_day);
        }
    }

    return array(
        'shipped'   => ($shipped_real > 0)
            ? array('ts' => $shipped_real, 'estimate' => false)
            : array('ts' => $shipped_estimate, 'estimate' => true),
        'delivered' => ($delivered_real > 0)
            ? array('ts' => $delivered_real, 'estimate' => false)
            : array('ts' => $delivered_estimate, 'estimate' => true),
    );
}
