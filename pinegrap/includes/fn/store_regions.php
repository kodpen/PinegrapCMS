<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Countries and states: the one write path the screens (add_country.php,
 * edit_country.php, add_state.php, edit_state.php) and the external API
 * (POST /store/countries, POST /store/states) share, so a country or state
 * written from either place gets the same default switch and the same
 * clean-up on delete.
 *
 * The values are the ones the form helpers check (includes/forms/
 * country_form.php, state_form.php): the caller checks them first; nothing
 * here checks who is asking either: the screens gate on
 * validate_ecommerce_access(), the API on the store:write scope.
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
 * Creates (id 0) or updates a country. A country made the default takes
 * the switch off every other one: the address forms select one country.
 *
 * @param int   $country_id 0 to create
 * @param array $values     name, code, zip_code_required (0/1),
 *                          transit_adjustment_days (whole number),
 *                          default_selected (0/1), checked by the caller
 * @param int   $user_id
 * @return int the country's id, 0 when a query failed
 */
function pg_store_country_save($country_id, $values, $user_id)
{
    $country_id = (int) $country_id;
    $row = array(
        (string) $values['name'],
        (string) $values['code'],
        ((int) $values['zip_code_required'] === 1) ? 1 : 0,
        max(0, (int) $values['transit_adjustment_days']),
        ((int) $values['default_selected'] === 1) ? 1 : 0,
        (int) $user_id,
    );

    if ($country_id > 0) {
        $row[] = $country_id;

        $result = db_q("UPDATE countries SET
                name = ?,
                code = ?,
                zip_code_required = ?,
                transit_adjustment_days = ?,
                default_selected = ?,
                user = ?,
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?", $row);
    } else {
        $result = db_q("INSERT INTO countries (name, code, zip_code_required, transit_adjustment_days, default_selected, user, timestamp)
            VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())", $row);

        $country_id = $result ? (int) mysqli_insert_id(db::$con) : 0;
    }

    if (!$result || ($country_id <= 0)) {
        return 0;
    }

    if ($row[4] === 1) {
        if (!db_q("UPDATE countries SET default_selected = 0 WHERE id != ?", array($country_id))) {
            return 0;
        }
    }

    return $country_id;
}

/**
 * Deletes a country with its states, and everything tied to them: their
 * places in shipping and tax zones and the verified shipping addresses of
 * its states.
 *
 * @param int $country_id
 * @return bool false when a query failed
 */
function pg_store_country_delete($country_id)
{
    $country_id = (int) $country_id;
    $result = db_q("SELECT id FROM states WHERE country_id = ?", array($country_id));

    if (!$result) {
        return false;
    }

    $state_ids = array();

    while ($state = mysqli_fetch_assoc($result)) {
        $state_ids[] = (int) $state['id'];
    }

    foreach ($state_ids as $state_id) {
        if (!pg_store_state_unlink($state_id)) {
            return false;
        }
    }

    foreach (array('states', 'zones_countries_xref', 'tax_zones_countries_xref') as $table) {
        if (!db_q("DELETE FROM " . $table . " WHERE country_id = ?", array($country_id))) {
            return false;
        }
    }

    return (bool) db_q("DELETE FROM countries WHERE id = ?", array($country_id));
}

/**
 * Creates (id 0) or updates a state.
 *
 * @param int   $state_id 0 to create
 * @param array $values   name, code, country_id, checked by the caller
 * @param int   $user_id
 * @return int the state's id, 0 when a query failed
 */
function pg_store_state_save($state_id, $values, $user_id)
{
    $state_id = (int) $state_id;
    $row = array((string) $values['name'], (string) $values['code'], (int) $values['country_id'], (int) $user_id);

    if ($state_id > 0) {
        $row[] = $state_id;

        return db_q("UPDATE states SET name = ?, code = ?, country_id = ?, user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", $row)
            ? $state_id
            : 0;
    }

    if (!db_q("INSERT INTO states (name, code, country_id, user, timestamp) VALUES (?, ?, ?, ?, UNIX_TIMESTAMP())", $row)) {
        return 0;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Takes a state out of the shipping and tax zones and deletes its verified
 * shipping addresses; the state row itself stays.
 *
 * @param int $state_id
 * @return bool false when a query failed
 */
function pg_store_state_unlink($state_id)
{
    foreach (array('zones_states_xref', 'tax_zones_states_xref', 'verified_shipping_addresses') as $table) {
        if (!db_q("DELETE FROM " . $table . " WHERE state_id = ?", array((int) $state_id))) {
            return false;
        }
    }

    return true;
}

/**
 * Deletes a state, its places in shipping and tax zones and its verified
 * shipping addresses.
 *
 * @param int $state_id
 * @return bool false when a query failed
 */
function pg_store_state_delete($state_id)
{
    if (!db_q("DELETE FROM states WHERE id = ?", array((int) $state_id))) {
        return false;
    }

    return pg_store_state_unlink($state_id);
}
