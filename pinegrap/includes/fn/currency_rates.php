<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Exchange rates: fetching them from the public feeds and keeping a dated
 * history of them.
 *
 * The store has always held one rate per currency (currencies.exchange_rate,
 * units of the foreign currency per base unit) and overwritten it every day.
 * That is enough to show a price in a visitor's currency; it is not enough to
 * book an invoice, which needs the rate of its own issue date months later,
 * and the receipt that closes it the rate of the day the money came in. The
 * currency_rates table holds one row per (day, base, currency), in the
 * direction the ledger multiplies in - base units per unit of the currency -
 * and never loses a day to the next run.
 *
 * Nothing in here knows about a country. The base is whatever the store marks
 * as base, and the feeds are the two the software has always used.
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
 * The currencies the Frankfurter feed (ECB reference rates) can answer for.
 *
 * @return array
 */
function pg_currency_rates_frankfurter_codes()
{
    return array('USD', 'EUR', 'GBP', 'CHF', 'JPY', 'AUD', 'CAD', 'SEK', 'NOK', 'DKK',
        'PLN', 'HUF', 'CZK', 'RON', 'BGN', 'ISK', 'NZD', 'SGD', 'HKD', 'KRW', 'CNY',
        'INR', 'BRL', 'MXN', 'ZAR', 'TRY', 'ILS', 'IDR', 'MYR', 'PHP', 'THB');
}

/**
 * Fetch a public HTTPS document.
 *
 * Goes through the outbound HTTP client when it is loaded (verified TLS, no
 * redirects, pinned address); otherwise plain cURL with the same verification
 * the update channel insists on. The certificate check is never turned off
 * here: an exchange rate that an attacker can rewrite is a ledger they can
 * rewrite.
 *
 * @param string $url
 * @return string|false  Body, or false
 */
function pg_currency_rates_http_get($url)
{
    if (!function_exists('api_http_request') && defined('PG_INIT_LOADED')
        && is_file(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/api/outbound/http.php');
    }

    if (function_exists('api_http_request')) {
        // The default body cap suits an error message; a rate list is a few
        // kilobytes.
        $result = api_http_request('GET', $url, array('timeout' => 10, 'max_body' => 65536, 'agent' => 'rates'));

        return (!empty($result['ok']) && ((int) $result['status'] === 200)) ? (string) $result['body'] : false;
    }

    if (!function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, function_exists('pinegrap_user_agent') ? pinegrap_user_agent() : 'Pinegrap');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ));

    if (function_exists('pg_curl_tls')) {
        pg_curl_tls($ch);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return (($response !== false) && ($status === 200)) ? (string) $response : false;
}

/**
 * Today's rates for a list of currencies against a base.
 *
 * Frankfurter first, in one call, for every code it knows; HexaRate one call
 * per code for the rest and for anything Frankfurter left out. The figures
 * come back the way the feeds give them - units of each currency per one unit
 * of the base - because that is what currencies.exchange_rate stores; the
 * history table takes the reciprocal when it stores them.
 *
 * @param string $base_code
 * @param array  $codes
 * @return array  code => array('rate' => float, 'source' => 'frankfurter'|'hexarate'); codes with no answer are absent
 */
function pg_currency_rates_fetch($base_code, array $codes)
{
    $base_code = strtoupper(trim((string) $base_code));
    $wanted = array();

    foreach ($codes as $code) {
        $code = strtoupper(trim((string) $code));
        if (($code !== '') && ($code !== $base_code) && preg_match('/^[A-Z]{3}$/', $code)) {
            $wanted[$code] = true;
        }
    }

    $rates = array();

    if (empty($wanted) || ($base_code === '')) {
        return $rates;
    }

    $known = pg_currency_rates_frankfurter_codes();

    if (in_array($base_code, $known, true)) {
        $batch = array_values(array_intersect(array_keys($wanted), $known));

        if (!empty($batch)) {
            $body = pg_currency_rates_http_get('https://api.frankfurter.app/latest?from=' . $base_code . '&to=' . implode(',', $batch));
            $response = ($body !== false) ? json_decode($body, true) : null;

            if (is_array($response) && isset($response['rates']) && is_array($response['rates'])) {
                foreach ($response['rates'] as $code => $rate) {
                    $code = strtoupper((string) $code);
                    if (isset($wanted[$code]) && ((float) $rate > 0)) {
                        $rates[$code] = array('rate' => (float) $rate, 'source' => 'frankfurter');
                    }
                }
            }
        }
    }

    foreach (array_keys($wanted) as $code) {
        if (isset($rates[$code])) {
            continue;
        }

        $body = pg_currency_rates_http_get('https://hexarate.paikama.co/api/rates/latest/' . $base_code . '?target=' . $code);
        $response = ($body !== false) ? json_decode($body, true) : null;

        if (is_array($response) && isset($response['data']['mid']) && ((float) $response['data']['mid'] > 0)) {
            $rates[$code] = array('rate' => (float) $response['data']['mid'], 'source' => 'hexarate');
        }
    }

    return $rates;
}

/**
 * Whether the history table exists yet.
 *
 * The rate job may run on a site whose files were replaced before its upgrade
 * was, and it must still refresh the store's own rates then.
 *
 * @return bool
 */
function pg_currency_rates_table_exists()
{
    static $exists = null;

    if ($exists === null) {
        $exists = (count((array) db_items("SHOW TABLES LIKE 'currency_rates'")) > 0);
    }

    return $exists;
}

/**
 * Keep one day's rate for one currency.
 *
 * The rate stored is base units per one unit of the currency. Running the job
 * twice on a day corrects the row rather than adding a second one.
 *
 * @param string $date    Y-m-d
 * @param string $base    Base currency code
 * @param string $code    Currency code
 * @param float  $rate    Base units per 1 unit of $code
 * @param string $source  Feed name, or 'manual'
 * @return bool
 */
function pg_currency_rate_store($date, $base, $code, $rate, $source = '')
{
    $rate = (float) $rate;
    $base = strtoupper(trim((string) $base));
    $code = strtoupper(trim((string) $code));

    if (($rate <= 0) || ($base === '') || ($code === '') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
        return false;
    }

    $value = number_format($rate, 8, '.', '');

    return (db("INSERT INTO currency_rates
            (rate_date, base_code, currency_code, rate, source, fetched_at)
        VALUES (
            '" . escape($date) . "',
            '" . escape($base) . "',
            '" . escape($code) . "',
            '" . $value . "',
            '" . escape(substr((string) $source, 0, 32)) . "',
            '" . time() . "')
        ON DUPLICATE KEY UPDATE
            rate = '" . $value . "',
            source = '" . escape(substr((string) $source, 0, 32)) . "',
            fetched_at = '" . time() . "'") !== false);
}

/**
 * The rate in force on a date.
 *
 * The row for that day when there is one, otherwise the latest earlier day:
 * feeds publish nothing on a weekend or a holiday, and the rate that applies
 * then is the last one published. Nothing later than the date is ever used,
 * so a document keeps the rate of its own day however often the job runs.
 *
 * @param string      $code  Currency code
 * @param string      $date  Y-m-d
 * @param string|null $base  Base code; the store's base when omitted
 * @return array|false  array('rate' => float, 'rate_date' => 'Y-m-d', 'source' => string), or false when nothing is recorded
 */
function pg_currency_rate($code, $date, $base = null)
{
    $code = strtoupper(trim((string) $code));
    $base = strtoupper(trim((string) (($base === null) ? (defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '') : $base)));

    if (($code === '') || ($base === '')) {
        return false;
    }

    if ($code === $base) {
        return array('rate' => 1.0, 'rate_date' => (string) $date, 'source' => 'base');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
        return false;
    }

    $row = db_item("SELECT rate, rate_date, source FROM currency_rates
        WHERE base_code = '" . escape($base) . "'
          AND currency_code = '" . escape($code) . "'
          AND rate_date <= '" . escape($date) . "'
        ORDER BY rate_date DESC
        LIMIT 1");

    if (!is_array($row) || ((float) $row['rate'] <= 0)) {
        return false;
    }

    return array('rate' => (float) $row['rate'], 'rate_date' => (string) $row['rate_date'], 'source' => (string) $row['source']);
}

/**
 * The most recent rate recorded for a currency, whatever its date.
 *
 * @param string      $code
 * @param string|null $base
 * @return array|false  As pg_currency_rate()
 */
function pg_currency_rate_latest($code, $base = null)
{
    return pg_currency_rate($code, '9999-12-31', $base);
}
