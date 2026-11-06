<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Currencies: the one write path the currency screens (add_currency.php,
 * edit_currency.php), the rate refresh (update_exchange_rates.php, the
 * button and the scheduled job) and the external API (POST
 * /store/currencies, POST /store/currencies/rates) share, so a currency
 * written from any of them gets the same single base and the same
 * currency.* events.
 *
 * The values are the ones the form helpers check (includes/forms/
 * currency_form.php): the caller checks them first; nothing here checks who
 * is asking either: the screens gate on validate_ecommerce_access(), the API
 * on the store:write scope.
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
 * Creates (id 0) or updates a currency. A currency made the base takes the
 * switch off every other one: prices are kept in one base currency.
 * Announces currency.created or currency.updated.
 *
 * An update leaves out the columns named in $kept (exchange_rate, base):
 * a caller that did not change them keeps a rate refresh, or another
 * currency made the base, that landed after it read the row.
 *
 * @param int   $currency_id 0 to create
 * @param array $values      name, code, symbol (as stored: HTML the pages
 *                           print), exchange_rate (five decimals, 1 for the
 *                           base), base (0/1), checked by the caller
 * @param int   $user_id
 * @param array $kept        update only: columns not written
 * @return int the currency's id, 0 when a query failed
 */
function pg_store_currency_save($currency_id, $values, $user_id, $kept = array())
{
    $currency_id = (int) $currency_id;
    $base = ((int) $values['base'] === 1) ? 1 : 0;
    $kept = ($currency_id > 0) ? (array) $kept : array();

    if ($currency_id > 0) {
        list($sets, $row) = pg_store_currency_update_sets($values, $kept);
        $row[] = (int) $user_id;
        $row[] = $currency_id;

        $result = db_q("UPDATE currencies SET
                " . implode(",\n                ", $sets) . ",
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row);
        $created = false;
    } else {
        $row = array(
            (string) $values['name'],
            $base,
            (string) $values['code'],
            (string) $values['symbol'],
            (string) $values['exchange_rate'],
            (int) $user_id,
            (int) $user_id,
        );

        $result = db_q("INSERT INTO currencies (name, base, code, symbol, exchange_rate, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
            VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", $row);

        $currency_id = $result ? (int) mysqli_insert_id(db::$con) : 0;
        $created = true;
    }

    if (!$result || ($currency_id <= 0)) {
        return 0;
    }

    if (($base === 1) && !in_array('base', $kept, true)) {
        if (!db_q("UPDATE currencies SET base = 0 WHERE (base = 1) AND (id != ?)", array($currency_id))) {
            return 0;
        }
    }

    $rate = (string) $values['exchange_rate'];

    // A kept column is announced as it stands now, not as it was read.
    if ($kept) {
        $stored = db_item("SELECT exchange_rate, base FROM currencies WHERE id = '" . $currency_id . "'");

        if ($stored) {
            $rate = (string) $stored['exchange_rate'];
            $base = ((int) $stored['base'] === 1) ? 1 : 0;
        }
    }

    pg_announce($created ? 'currency.created' : 'currency.updated', pg_store_currency_event((string) $values['code'], $currency_id, $rate, $base));

    return $currency_id;
}

/**
 * The SET clauses and their values of a currency update, without the
 * columns in $kept. Pure.
 *
 * @param array $values as pg_store_currency_save() takes them
 * @param array $kept   columns not written (exchange_rate, base)
 * @return array array(clauses, values)
 */
function pg_store_currency_update_sets($values, $kept)
{
    $columns = array(
        'name' => (string) $values['name'],
        'base' => ((int) $values['base'] === 1) ? 1 : 0,
        'code' => (string) $values['code'],
        'symbol' => (string) $values['symbol'],
        'exchange_rate' => (string) $values['exchange_rate'],
    );
    $sets = array();
    $row = array();

    foreach ($columns as $column => $value) {
        if (!in_array($column, (array) $kept, true)) {
            $sets[] = $column . ' = ?';
            $row[] = $value;
        }
    }

    return array($sets, $row);
}

/**
 * Deletes a currency. The caller refuses the base currency first
 * (pg_currency_form_delete_error()). Orders keep the code they were written
 * with. Announces currency.deleted.
 *
 * @param int $currency_id
 * @return bool false when a query failed
 */
function pg_store_currency_delete($currency_id)
{
    $currency_id = (int) $currency_id;
    $code = (string) db_value("SELECT code FROM currencies WHERE id = '" . $currency_id . "'");

    if (!db_q("DELETE FROM currencies WHERE id = ?", array($currency_id))) {
        return false;
    }

    pg_announce('currency.deleted', array('id' => $currency_id, 'code' => $code));

    return true;
}

/**
 * What a currency.* event carries. Pure.
 *
 * @param string $code
 * @param int    $currency_id
 * @param string $exchange_rate
 * @param int    $base
 * @return array
 */
function pg_store_currency_event($code, $currency_id, $exchange_rate, $base)
{
    return array(
        'id' => (int) $currency_id,
        'code' => (string) $code,
        'exchange_rate' => (float) $exchange_rate,
        'base' => ((int) $base === 1),
    );
}

/**
 * The codes a rate refresh asks the feeds for: the store's currencies and
 * the ERP's document currencies, without the base and without repeats. Pure.
 *
 * @param array  $store_codes the store's codes, any case
 * @param string $erp_codes   ERP_FX_CURRENCIES, comma separated
 * @param string $base_code
 * @return array
 */
function pg_store_currency_rates_wanted($store_codes, $erp_codes, $base_code)
{
    $wanted = array();
    $base_code = strtoupper(trim((string) $base_code));

    foreach (array_merge((array) $store_codes, explode(',', (string) $erp_codes)) as $code) {
        $code = strtoupper(trim((string) $code));

        if (($code !== '') && ($code !== $base_code)) {
            $wanted[] = $code;
        }
    }

    return array_values(array_unique($wanted));
}

/**
 * Refreshes the exchange rates from the feeds (pg_currency_rates_fetch()):
 * the store's currencies get the new rate, the dated history
 * (currency_rates) gets the reciprocal for the ERP. The base is 1 to itself
 * in both. A store currency whose stored rate moved announces
 * currency.updated.
 *
 * @param int $user_id 0 for the scheduled job
 * @return array wanted (codes asked for), reached (whether a feed answered),
 *               failed (codes no feed answered), updated (how many rates
 *               were written)
 */
function pg_store_currency_rates_refresh($user_id)
{
    $base_code = strtoupper((string) BASE_CURRENCY_CODE);
    $today = date('Y-m-d');

    // The store's own list, keyed by code.
    $store_currencies = array();

    foreach ((array) db_items("SELECT id, code, exchange_rate FROM currencies") as $row) {
        $store_currencies[strtoupper(trim((string) $row['code']))] = $row;
    }

    $wanted = pg_store_currency_rates_wanted(array_keys($store_currencies), defined('ERP_FX_CURRENCIES') ? (string) ERP_FX_CURRENCIES : '', $base_code);

    if (isset($store_currencies[$base_code])) {
        db_q("UPDATE currencies SET
                exchange_rate = '1.00000',
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", array((int) $user_id, (int) $store_currencies[$base_code]['id']));
    }

    $history = pg_currency_rates_table_exists();

    if ($history) {
        pg_currency_rate_store($today, $base_code, $base_code, 1, 'base');
    }

    $fetched = pg_currency_rates_fetch($base_code, $wanted);
    $failed = array();
    $updated = 0;

    foreach ($wanted as $code) {
        if (!isset($fetched[$code])) {
            $failed[] = $code;
            continue;
        }

        // Units of the currency per base unit, as the feeds and the store both count.
        $store_rate = number_format((float) $fetched[$code]['rate'], 5, '.', '');

        if (isset($store_currencies[$code])) {
            $currency = $store_currencies[$code];

            db_q("UPDATE currencies SET
                    exchange_rate = ?,
                    last_modified_user_id = ?,
                    last_modified_timestamp = UNIX_TIMESTAMP()
                WHERE id = ?", array($store_rate, (int) $user_id, (int) $currency['id']));

            if ($store_rate !== number_format((float) $currency['exchange_rate'], 5, '.', '')) {
                pg_announce('currency.updated', pg_store_currency_event($code, (int) $currency['id'], $store_rate, 0));
            }
        }

        // The history keeps the reciprocal: base units per unit of the currency,
        // which is what an amount on a document is multiplied by.
        if ($history) {
            pg_currency_rate_store($today, $base_code, $code, 1 / (float) $fetched[$code]['rate'], (string) $fetched[$code]['source']);
        }

        $updated++;
    }

    return array(
        'wanted' => $wanted,
        'reached' => !empty($fetched),
        'failed' => $failed,
        'updated' => $updated,
    );
}
