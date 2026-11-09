<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The live rate of a shipping method linked to a carrier label service
 * (shipping_methods.label_driver, label_service). get_shipping_realtime_rate()
 * (shipping.php) hands a linked method with realtime_rate on to
 * pg_shipping_label_rate(), which asks the driver's rate() for the parcel the
 * label would carry and charges the linked service's quote, or the cheapest
 * one when no service is linked.
 *
 * Quotes are kept in shipping_rates, one row per service of the answer
 * ("label:<driver>:<service>"), its zip_code the zone the answer holds for
 * (pg_label_rate_zone(): '' for a national price list). A row is fresh for a
 * day; while the service cannot be reached a row up to seven days old is
 * charged; a driver that failed once is not asked again in the same request.
 * Amounts are kept in the base currency: a quote in another currency is
 * converted at the stored exchange rate, and one with no rate is dropped.
 * No price means the method is not offered (false).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// How long a quote is charged without asking again, in seconds.
define('PG_SHIPPING_LABEL_RATE_FRESH', 86400);

// How old a quote may be when the service cannot be reached, in seconds;
// pg_shipping_rates_purge() keeps the cache that long.
define('PG_SHIPPING_LABEL_RATE_STALE', 604800);

/**
 * The link columns of a shipping method for a query: ", <table>.label_driver
 * AS <prefix>label_driver, <table>.label_service AS <prefix>label_service",
 * or both as '' on a site that has not run the step that adds them (files
 * land first).
 *
 * @param string $table  the table or alias the columns are read from, '' for none
 * @param string $prefix the prefix of the names they are read back by
 * @return string
 */
function pg_shipping_label_rate_select($table = '', $prefix = '')
{
    $table = preg_replace('/[^a-z0-9_]/', '', (string) $table);
    $prefix = preg_replace('/[^a-z0-9_]/', '', (string) $prefix);
    $columns = '';

    foreach (array('label_driver', 'label_service') as $column) {
        $columns .= ",\n            " . (pg_schema_has('shipping_methods', $column) ? (($table !== '') ? $table . '.' : '') . $column : "''") . ' AS ' . $prefix . $column;
    }

    return $columns;
}

/**
 * The lines a live rate is asked for: those that do not ship free, each with
 * its quantity, weight and size. Pure.
 *
 * @param array $items list of quantity, weight, length, width, height, free_shipping
 * @return array
 */
function pg_shipping_label_rate_lines($items)
{
    $lines = array();

    foreach ((array) $items as $item) {
        if (!is_array($item) || !empty($item['free_shipping']) || (!isset($item['quantity']) || ((float) $item['quantity'] <= 0))) {
            continue;
        }

        $line = array();

        foreach (array('quantity', 'weight', 'length', 'width', 'height') as $name) {
            $line[$name] = (isset($item[$name]) && is_scalar($item[$name])) ? $item[$name] : 0;
        }

        $lines[] = $line;
    }

    return $lines;
}

/**
 * The service column of a cached quote: "label:<driver>:<service>". Pure.
 *
 * @param string $driver
 * @param string $service
 * @return string
 */
function pg_shipping_label_rate_service_key($driver, $service)
{
    return 'label:' . (string) $driver . ':' . (string) $service;
}

/**
 * The zone a driver's answer holds for, the zip_code of its cache rows.
 * pg_label_rate_zone() decides it from the driver's info; until that is
 * there every driver with a rate() prices by the parcel alone ('').
 *
 * @param string $driver
 * @param array  $recipient country, zip
 * @return string
 */
function pg_shipping_label_rate_zone($driver, $recipient)
{
    return function_exists('pg_label_rate_zone') ? (string) pg_label_rate_zone(pg_label_info($driver), $recipient) : '';
}

/**
 * Quotes with their amounts in the base currency: as they are in it, else
 * divided by the stored exchange rate (units of the currency per one unit
 * of the base), rounded; a quote in a currency with no rate is dropped. One
 * amount per service, the lower. Pure.
 *
 * @param array  $quotes pg_label_answer() quotes (service, amount, currency)
 * @param string $base   the base currency's code
 * @param array  $rates  currency code => exchange rate
 * @return array service => amount (minor units of the base)
 */
function pg_shipping_label_rate_in_base($quotes, $base, $rates)
{
    $base = strtoupper(trim((string) $base));
    $amounts = array();

    foreach ((array) $quotes as $quote) {
        if (!is_array($quote) || !isset($quote['service'], $quote['amount'], $quote['currency'])) {
            continue;
        }

        $currency = strtoupper(trim((string) $quote['currency']));

        if ($currency === $base) {
            $amount = (int) $quote['amount'];
        } elseif (isset($rates[$currency]) && is_numeric($rates[$currency]) && ((float) $rates[$currency] > 0)) {
            $amount = (int) round(((int) $quote['amount']) / (float) $rates[$currency]);
        } else {
            continue;
        }

        $service = (string) $quote['service'];

        if (($amount >= 0) && (!isset($amounts[$service]) || ($amount < $amounts[$service]))) {
            $amounts[$service] = $amount;
        }
    }

    return $amounts;
}

/**
 * The amount charged from a list of quotes: the linked service's (letter
 * case aside), else for no linked service the cheapest. null when there is
 * none. Pure.
 *
 * @param array  $amounts service => amount
 * @param string $service shipping_methods.label_service, '' for the cheapest
 * @return int|null
 */
function pg_shipping_label_rate_pick($amounts, $service)
{
    $service = trim((string) $service);
    $pick = null;

    foreach ((array) $amounts as $code => $amount) {
        if (($service !== '') && (strcasecmp((string) $code, $service) !== 0)) {
            continue;
        }

        if (($pick === null) || ((int) $amount < $pick)) {
            $pick = (int) $amount;
        }
    }

    return $pick;
}

/**
 * The WHERE of a driver's cached quotes for one zone and parcel.
 *
 * @param string $driver
 * @param string $zone
 * @param array  $parcel pg_shipment_label_parcel()
 * @return string
 */
function pg_shipping_label_rate_where($driver, $zone, $parcel)
{
    $prefix = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), pg_shipping_label_rate_service_key($driver, ''));

    return "(service LIKE '" . e($prefix) . "%')
            AND (zip_code = '" . e($zone) . "')
            AND (weight = '" . e((string) $parcel['weight']) . "')
            AND (length = '" . e((string) $parcel['length']) . "')
            AND (width = '" . e((string) $parcel['width']) . "')
            AND (height = '" . e((string) $parcel['height']) . "')";
}

/**
 * A driver's cached quotes for one zone and parcel stored since a time.
 *
 * @param string $driver
 * @param string $zone
 * @param array  $parcel
 * @param int    $since  unix time
 * @return array service => amount
 */
function pg_shipping_label_rate_cached($driver, $zone, $parcel, $since)
{
    $rows = db_items(
        "SELECT service, rate
        FROM shipping_rates
        WHERE " . pg_shipping_label_rate_where($driver, $zone, $parcel) . "
            AND (timestamp > '" . (int) $since . "')");

    $amounts = array();
    $length = strlen(pg_shipping_label_rate_service_key($driver, ''));

    foreach ((array) $rows as $row) {
        $amounts[substr((string) $row['service'], $length)] = (int) $row['rate'];
    }

    return $amounts;
}

/**
 * A driver's answer for one zone and parcel kept in place of the one before.
 *
 * @param string $driver
 * @param string $zone
 * @param array  $parcel
 * @param array  $amounts service => amount
 * @param int    $now
 */
function pg_shipping_label_rate_store($driver, $zone, $parcel, $amounts, $now)
{
    db("DELETE FROM shipping_rates WHERE " . pg_shipping_label_rate_where($driver, $zone, $parcel));

    foreach ($amounts as $service => $amount) {
        db(
            "INSERT INTO shipping_rates (service, zip_code, weight, length, width, height, rate, timestamp)
            VALUES (
                '" . e(pg_shipping_label_rate_service_key($driver, $service)) . "',
                '" . e($zone) . "',
                '" . e((string) $parcel['weight']) . "',
                '" . e((string) $parcel['length']) . "',
                '" . e((string) $parcel['width']) . "',
                '" . e((string) $parcel['height']) . "',
                '" . (int) $amount . "',
                '" . (int) $now . "')");
    }
}

/**
 * Removes the live rates older than the oldest one a failed call may still
 * charge. Run now and then by get_shipping_realtime_rate().
 *
 * @param int $now
 */
function pg_shipping_rates_purge($now)
{
    db("DELETE FROM shipping_rates WHERE timestamp < '" . ((int) $now - PG_SHIPPING_LABEL_RATE_STALE) . "'");
}

/**
 * The exchange rates of the store's currencies, code => units per one unit
 * of the base. Once a request.
 *
 * @return array
 */
function pg_shipping_label_rate_exchange_rates()
{
    static $rates = null;

    if ($rates === null) {
        $rates = array();

        foreach ((array) db_items("SELECT code, exchange_rate FROM currencies WHERE exchange_rate > 0") as $row) {
            $rates[strtoupper((string) $row['code'])] = (float) $row['exchange_rate'];
        }
    }

    return $rates;
}

/**
 * Whether a driver's call failed earlier in this request; with $failed the
 * failure is remembered.
 *
 * @param string $driver
 * @param bool   $failed
 * @return bool
 */
function pg_shipping_label_rate_failed($driver, $failed = false)
{
    static $drivers = array();

    if ($failed) {
        $drivers[(string) $driver] = true;
    }

    return isset($drivers[(string) $driver]);
}

/**
 * The live rate of a linked shipping method: the quote of its service for
 * the parcel of the lines that do not ship free, in minor units of the base
 * currency with VAT inside; 0 when every line ships free; false when there is
 * no price, so the method is not offered.
 *
 * @param array $properties label_driver, label_service, items, and the
 *                          recipient's country, zip_code, state, city
 * @return int|false
 */
function pg_shipping_label_rate($properties)
{
    $driver = (string) $properties['label_driver'];
    $lines = pg_shipping_label_rate_lines($properties['items']);

    if (!$lines) {
        return 0;
    }

    if (!pg_label_can($driver, 'rate') || !pg_label_ready($driver)) {
        return false;
    }

    $parcel = pg_shipment_label_parcel($lines);
    $recipient = array(
        'country' => strtoupper(trim((string) $properties['country'])),
        'zip'     => trim((string) $properties['zip_code']),
        'state'   => trim((string) $properties['state']),
        'city'    => trim((string) $properties['city']),
    );
    $zone = pg_shipping_label_rate_zone($driver, $recipient);
    $service = (string) $properties['label_service'];
    $now = time();

    $amounts = pg_shipping_label_rate_cached($driver, $zone, $parcel, $now - PG_SHIPPING_LABEL_RATE_FRESH);

    if ($amounts) {
        $pick = pg_shipping_label_rate_pick($amounts, $service);

        return ($pick === null) ? false : $pick;
    }

    if (!pg_shipping_label_rate_failed($driver)) {
        $shipment = array(
            'recipient' => $recipient,
            'parcel'    => $parcel,
            'service'   => $service,
            'currency'  => defined('BASE_CURRENCY_CODE') ? strtoupper((string) BASE_CURRENCY_CODE) : '',
        );

        $answer = pg_label_call($driver, 'rate', array($shipment, pg_label_account(pg_label_fields($driver), pg_label_settings($driver))));
        $amounts = $answer['success']
            ? pg_shipping_label_rate_in_base($answer['quotes'], $shipment['currency'], pg_shipping_label_rate_exchange_rates())
            : array();

        if ($amounts) {
            pg_shipping_label_rate_store($driver, $zone, $parcel, $amounts, $now);
            $pick = pg_shipping_label_rate_pick($amounts, $service);

            return ($pick === null) ? false : $pick;
        }

        pg_shipping_label_rate_failed($driver, true);

        log_activity($answer['success']
            ? pg_log_lang(array(
                'string' => 'The live shipping rate of {var:1} could not be charged: no quote is in the base currency or in a currency with an exchange rate.',
                'vars'   => array(pg_shipment_label_driver_name($driver))))
            : pg_log_lang(array(
                'string' => 'The live shipping rate of {var:1} could not be read: {var:2}',
                'vars'   => array(pg_shipment_label_driver_name($driver), $answer['error']))));
    }

    $pick = pg_shipping_label_rate_pick(pg_shipping_label_rate_cached($driver, $zone, $parcel, $now - PG_SHIPPING_LABEL_RATE_STALE), $service);

    return ($pick === null) ? false : $pick;
}
