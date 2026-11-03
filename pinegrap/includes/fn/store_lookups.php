<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The store's lookup lists: arrival dates with their shipping cut-offs,
 * ship date adjustments, verified shipping addresses and referral
 * sources. One write path the create and edit screens and the external
 * API (/store/arrival-dates, /store/ship-date-adjustments,
 * /store/verified-addresses, /store/referral-sources) share, so a row
 * written from either place is written the same way.
 *
 * The callers check the values first with the form helpers
 * (includes/forms/*_form.php); nothing here checks who is asking: the
 * screens gate on validate_ecommerce_access(), the API on store:write.
 * Every function returns 0/false when a query failed.
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
 * Creates (id 0) or updates an arrival date. One made the default takes
 * the switch off every other one: checkout selects one arrival date.
 *
 * @param int   $arrival_date_id 0 to create
 * @param array $values the twelve values of pg_arrival_date_form_values():
 *                      name, description, code, arrival_date, status,
 *                      start_date, end_date, default_selected, sort_order,
 *                      custom, custom_maximum_arrival_date, user
 * @return int the arrival date's id, 0 when a query failed
 */
function pg_store_arrival_date_save($arrival_date_id, $values)
{
    $arrival_date_id = (int) $arrival_date_id;
    $values = array_values($values);

    if ($arrival_date_id > 0) {
        $result = db_q("UPDATE arrival_dates SET
                name = ?,
                description = ?,
                code = ?,
                arrival_date = ?,
                status = ?,
                start_date = ?,
                end_date = ?,
                default_selected = ?,
                sort_order = ?,
                custom = ?,
                custom_maximum_arrival_date = ?,
                user = ?,
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", array_merge($values, array($arrival_date_id)));
    } else {
        $result = db_q("INSERT INTO arrival_dates (name, description, code, arrival_date, status, start_date, end_date, default_selected, sort_order, custom, custom_maximum_arrival_date, user, timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())", $values);

        $arrival_date_id = $result ? (int) mysqli_insert_id(db::$con) : 0;
    }

    if (!$result || ($arrival_date_id <= 0)) {
        return 0;
    }

    if ((string) $values[7] === '1') {
        if (!db_q("UPDATE arrival_dates SET default_selected = 0 WHERE id != ?", array($arrival_date_id))) {
            return 0;
        }
    }

    return $arrival_date_id;
}

/**
 * Replaces the shipping cut-offs of an arrival date.
 *
 * @param int   $arrival_date_id
 * @param array $cutoffs list of array(shipping method id, 'Y-m-d H:i:s'),
 *                       checked by the caller (pg_arrival_date_form_cutoff())
 * @return bool
 */
function pg_store_arrival_date_cutoffs_save($arrival_date_id, $cutoffs)
{
    if (!db_q("DELETE FROM shipping_cutoffs WHERE arrival_date_id = ?", array((int) $arrival_date_id))) {
        return false;
    }

    foreach ($cutoffs as $cutoff) {
        if (!db_q("INSERT INTO shipping_cutoffs (arrival_date_id, shipping_method_id, date_and_time) VALUES (?, ?, ?)", array((int) $arrival_date_id, (int) $cutoff[0], (string) $cutoff[1]))) {
            return false;
        }
    }

    return true;
}

/**
 * Deletes an arrival date with its shipping cut-offs. Orders keep the
 * arrival date they were placed with.
 *
 * @param int $arrival_date_id
 * @return bool
 */
function pg_store_arrival_date_delete($arrival_date_id)
{
    $arrival_date_id = (int) $arrival_date_id;

    return db_q("DELETE FROM shipping_cutoffs WHERE arrival_date_id = ?", array($arrival_date_id))
        && db_q("DELETE FROM arrival_dates WHERE id = ?", array($arrival_date_id));
}

/**
 * Creates (id 0) or updates a ship date adjustment.
 *
 * @param int    $adjustment_id 0 to create
 * @param string $zip_code_prefix three characters
 * @param int    $shipping_method_id
 * @param int    $adjustment signed days, negative for earlier
 * @param int    $user_id
 * @return int the adjustment's id, 0 when a query failed
 */
function pg_store_ship_date_adjustment_save($adjustment_id, $zip_code_prefix, $shipping_method_id, $adjustment, $user_id)
{
    $adjustment_id = (int) $adjustment_id;
    $row = array((string) $zip_code_prefix, (int) $shipping_method_id, (int) $adjustment, (int) $user_id);

    if ($adjustment_id > 0) {
        $row[] = $adjustment_id;

        return db_q("UPDATE ship_date_adjustments SET
                zip_code_prefix = ?,
                shipping_method_id = ?,
                adjustment = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row) ? $adjustment_id : 0;
    }

    $row[] = (int) $user_id;

    if (!db_q("INSERT INTO ship_date_adjustments (zip_code_prefix, shipping_method_id, adjustment, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
        VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", $row)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * @param int $adjustment_id
 * @return bool
 */
function pg_store_ship_date_adjustment_delete($adjustment_id)
{
    return (bool) db_q("DELETE FROM ship_date_adjustments WHERE id = ?", array((int) $adjustment_id));
}

/**
 * Creates (id 0) or updates a verified shipping address.
 *
 * @param int   $address_id 0 to create
 * @param array $values company, address_1, address_2, city, state_id,
 *                      zip_code (pg_verified_shipping_address_form_values())
 * @param int   $user_id
 * @return int the address's id, 0 when a query failed
 */
function pg_store_verified_address_save($address_id, $values, $user_id)
{
    $address_id = (int) $address_id;
    $columns = array('company', 'address_1', 'address_2', 'city', 'state_id', 'zip_code');
    $row = array();

    foreach ($columns as $column) {
        $row[] = ($column === 'state_id') ? (int) ($values[$column] ?? 0) : (string) ($values[$column] ?? '');
    }

    $row[] = (int) $user_id;

    if ($address_id > 0) {
        $row[] = $address_id;

        return db_q("UPDATE verified_shipping_addresses SET
                company = ?,
                address_1 = ?,
                address_2 = ?,
                city = ?,
                state_id = ?,
                zip_code = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row) ? $address_id : 0;
    }

    $row[] = (int) $user_id;

    if (!db_q("INSERT INTO verified_shipping_addresses (company, address_1, address_2, city, state_id, zip_code, created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())", $row)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * @param int $address_id
 * @return bool
 */
function pg_store_verified_address_delete($address_id)
{
    return (bool) db_q("DELETE FROM verified_shipping_addresses WHERE id = ?", array((int) $address_id));
}

/**
 * The name and code of every referral source but one, as
 * pg_referral_source_form_error() compares them.
 *
 * @param int|null $except_id the source being edited, null on create
 * @return array|false rows with name and code, false when the query failed
 */
function pg_store_referral_source_others($except_id)
{
    $result = ($except_id === null)
        ? db_q("SELECT name, code FROM referral_sources")
        : db_q("SELECT name, code FROM referral_sources WHERE id <> ?", array((int) $except_id));

    if (!$result) {
        return false;
    }

    $others = array();

    while ($other = mysqli_fetch_assoc($result)) {
        $others[] = $other;
    }

    return $others;
}

/**
 * Creates (id 0) or updates a referral source.
 *
 * @param int   $source_id 0 to create
 * @param array $values name, code, sort_order (pg_referral_source_form_values()),
 *                      checked by pg_referral_source_form_error()
 * @param int   $user_id
 * @return int the source's id, 0 when a query failed
 */
function pg_store_referral_source_save($source_id, $values, $user_id)
{
    $source_id = (int) $source_id;
    $row = array((string) $values['name'], (string) $values['code'], (int) $values['sort_order'], (int) $user_id);

    if ($source_id > 0) {
        $row[] = $source_id;

        return db_q("UPDATE referral_sources SET
                name = ?,
                code = ?,
                sort_order = ?,
                user = ?,
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row) ? $source_id : 0;
    }

    if (!db_q("INSERT INTO referral_sources (name, code, sort_order, user, timestamp) VALUES (?, ?, ?, ?, UNIX_TIMESTAMP())", $row)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Deletes a referral source. Contacts and orders keep the code they were
 * written with.
 *
 * @param int $source_id
 * @return bool
 */
function pg_store_referral_source_delete($source_id)
{
    return (bool) db_q("DELETE FROM referral_sources WHERE id = ?", array((int) $source_id));
}
