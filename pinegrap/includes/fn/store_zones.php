<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipping zones and tax zones: the one write path the zone screens
 * (add_zone.php, edit_zone.php, add_tax_zone.php, edit_tax_zone.php) and the
 * external API (POST /store/zones, POST /store/tax-zones) share, so a zone
 * written from either place gets the same links, the same clean-up on delete
 * and the same webhook event.
 *
 * A zone is a name, four rates in minor units added to what a shipping method
 * charges, and the countries and states it covers. A tax zone is a name, a
 * rate in percent and the countries and states it covers. Nothing here checks
 * who is asking: the screens gate on validate_ecommerce_access(), the API on
 * the store:write scope.
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
 * The four rate columns of a shipping zone.
 *
 * @return array
 */
function pg_store_zone_rate_columns()
{
    return array('base_rate', 'primary_weight_rate', 'secondary_weight_rate', 'item_rate');
}

/**
 * Country or state ids as a list of distinct positive integers, in the order
 * first given. Anything that is not a positive whole number is dropped.
 *
 * @param mixed $ids
 * @return int[]
 */
function pg_store_zone_link_ids($ids)
{
    $out = array();

    if (!is_array($ids)) {
        return $out;
    }

    foreach ($ids as $id) {
        if (!is_scalar($id) || !ctype_digit(trim((string) $id))) {
            continue;
        }

        $id = (int) $id;

        if (($id > 0) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * The link tables of a zone kind.
 *
 * @param string $kind  'zone' or 'tax_zone'
 * @return array  'table', 'key', 'countries', 'states'
 */
function pg_store_zone_tables($kind)
{
    if ($kind === 'tax_zone') {
        return array('table' => 'tax_zones', 'key' => 'tax_zone_id', 'countries' => 'tax_zones_countries_xref', 'states' => 'tax_zones_states_xref');
    }

    return array('table' => 'zones', 'key' => 'zone_id', 'countries' => 'zones_countries_xref', 'states' => 'zones_states_xref');
}

/**
 * Replaces the countries and states a zone covers.
 *
 * @param string $kind     'zone' or 'tax_zone'
 * @param int    $zone_id
 * @param array  $country_ids
 * @param array  $state_ids
 * @return bool  false when a query failed
 */
function pg_store_zone_save_links($kind, $zone_id, $country_ids, $state_ids)
{
    $t = pg_store_zone_tables($kind);
    $zone_id = (int) $zone_id;

    if (!db_q("DELETE FROM " . $t['countries'] . " WHERE " . $t['key'] . " = ?", array($zone_id))) {
        return false;
    }

    if (!db_q("DELETE FROM " . $t['states'] . " WHERE " . $t['key'] . " = ?", array($zone_id))) {
        return false;
    }

    foreach (pg_store_zone_link_ids($country_ids) as $country_id) {
        if (!db_q("INSERT INTO " . $t['countries'] . " (" . $t['key'] . ", country_id) VALUES (?, ?)", array($zone_id, $country_id))) {
            return false;
        }
    }

    foreach (pg_store_zone_link_ids($state_ids) as $state_id) {
        if (!db_q("INSERT INTO " . $t['states'] . " (" . $t['key'] . ", state_id) VALUES (?, ?)", array($zone_id, $state_id))) {
            return false;
        }
    }

    return true;
}

/**
 * Creates or updates a shipping zone and its countries and states, and
 * announces zone.created or zone.updated.
 *
 * @param int    $zone_id      0 creates one
 * @param array  $values       'name' and the four rates in minor units
 * @param array  $country_ids
 * @param array  $state_ids
 * @param int    $user_id      Who made the change
 * @return int  The zone's id, 0 when a query failed
 */
function pg_store_zone_save($zone_id, $values, $country_ids, $state_ids, $user_id)
{
    $zone_id = (int) $zone_id;
    $created = ($zone_id < 1);

    $parameters = array((string) $values['name']);

    foreach (pg_store_zone_rate_columns() as $column) {
        $parameters[] = max(0, (int) round((float) ($values[$column] ?? 0)));
    }

    $parameters[] = (int) $user_id;

    if (!$created) {
        $parameters[] = $zone_id;

        $saved = db_q(
            "UPDATE zones SET name = ?, base_rate = ?, primary_weight_rate = ?, secondary_weight_rate = ?, item_rate = ?, user = ?, timestamp = UNIX_TIMESTAMP()
            WHERE id = ?",
            $parameters);
    } else {
        $saved = db_q(
            "INSERT INTO zones (name, base_rate, primary_weight_rate, secondary_weight_rate, item_rate, user, timestamp)
            VALUES (?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())",
            $parameters);

        $zone_id = $saved ? (int) mysqli_insert_id(db::$con) : 0;
    }

    if (!$saved || ($zone_id < 1) || !pg_store_zone_save_links('zone', $zone_id, $country_ids, $state_ids)) {
        return 0;
    }

    pg_announce($created ? 'zone.created' : 'zone.updated', array('id' => $zone_id, 'name' => (string) $values['name']));

    return $zone_id;
}

/**
 * Deletes a shipping zone, its countries and states, and its place on the
 * shipping methods and products that named it; announces zone.deleted.
 *
 * @param int    $zone_id
 * @param string $name  For the event
 * @return bool  false when a query failed
 */
function pg_store_zone_delete($zone_id, $name = '')
{
    $zone_id = (int) $zone_id;

    foreach (array('zones_countries_xref', 'zones_states_xref', 'shipping_methods_zones_xref', 'products_zones_xref') as $table) {
        if (!db_q("DELETE FROM " . $table . " WHERE zone_id = ?", array($zone_id))) {
            return false;
        }
    }

    if (!db_q("DELETE FROM zones WHERE id = ?", array($zone_id))) {
        return false;
    }

    pg_announce('zone.deleted', array('id' => $zone_id, 'name' => (string) $name));

    return true;
}

/**
 * Creates or updates a tax zone and its countries and states, and announces
 * tax_zone.created or tax_zone.updated.
 *
 * @param int    $tax_zone_id  0 creates one
 * @param string $name
 * @param float  $tax_rate     Percent, as parse_tax_rate() reads it
 * @param array  $country_ids
 * @param array  $state_ids
 * @param int    $user_id      Who made the change
 * @return int  The tax zone's id, 0 when a query failed
 */
function pg_store_tax_zone_save($tax_zone_id, $name, $tax_rate, $country_ids, $state_ids, $user_id)
{
    $tax_zone_id = (int) $tax_zone_id;
    $created = ($tax_zone_id < 1);

    if ($created) {
        $saved = db_q(
            "INSERT INTO tax_zones (name, tax_rate, user, timestamp) VALUES (?, ?, ?, UNIX_TIMESTAMP())",
            array((string) $name, $tax_rate, (int) $user_id));

        $tax_zone_id = $saved ? (int) mysqli_insert_id(db::$con) : 0;
    } else {
        $saved = db_q(
            "UPDATE tax_zones SET name = ?, tax_rate = ?, user = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?",
            array((string) $name, $tax_rate, (int) $user_id, $tax_zone_id));
    }

    if (!$saved || ($tax_zone_id < 1) || !pg_store_zone_save_links('tax_zone', $tax_zone_id, $country_ids, $state_ids)) {
        return 0;
    }

    pg_announce($created ? 'tax_zone.created' : 'tax_zone.updated', array('id' => $tax_zone_id, 'name' => (string) $name));

    return $tax_zone_id;
}

/**
 * Deletes a tax zone with its countries and states; announces
 * tax_zone.deleted.
 *
 * @param int    $tax_zone_id
 * @param string $name  For the event
 * @return bool  false when a query failed
 */
function pg_store_tax_zone_delete($tax_zone_id, $name = '')
{
    $tax_zone_id = (int) $tax_zone_id;

    foreach (array('tax_zones_countries_xref', 'tax_zones_states_xref') as $table) {
        if (!db_q("DELETE FROM " . $table . " WHERE tax_zone_id = ?", array($tax_zone_id))) {
            return false;
        }
    }

    if (!db_q("DELETE FROM tax_zones WHERE id = ?", array($tax_zone_id))) {
        return false;
    }

    pg_announce('tax_zone.deleted', array('id' => $tax_zone_id, 'name' => (string) $name));

    return true;
}
