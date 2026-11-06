<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipping methods: the one write path the shipping method screens
 * (add_shipping_method.php, edit_shipping_method.php) and the external API
 * (POST /store/shipping-methods) share, so a method written from either
 * place gets the same zones and excluded dates, the same clean-up on delete
 * and the same webhook event.
 *
 * The screens turn their form into columns with
 * pg_shipping_method_form_columns() (includes/forms/shipping_method_form.php),
 * the API from its typed fields (includes/api/resources/store.php); both hand
 * the columns here. Nothing here checks who is asking: the screens gate on
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
 * The real-time rate services shipping_methods.service holds (its ENUM, the
 * blank aside), as the service picker of the screen offers them.
 *
 * @return string[]
 */
function pg_shipping_method_services()
{
    return array(
        'usps_express', 'usps_priority', 'usps_ground',
        'ups_next_day_air', 'ups_next_day_air_early', 'ups_next_day_air_saver', 'ups_2nd_day_air', 'ups_2nd_day_air_am', 'ups_3_day_select', 'ups_ground',
        'fedex_first_overnight', 'fedex_priority_overnight', 'fedex_standard_overnight', 'fedex_2_day_am', 'fedex_2_day', 'fedex_express_saver', 'fedex_ground',
    );
}

/**
 * Creates or updates a shipping method with its zones and excluded transit
 * dates, and announces shipping_method.created or shipping_method.updated.
 *
 * @param int    $shipping_method_id  0 creates one
 * @param array  $columns             Column name => value, without user and timestamp
 * @param array  $zone_ids            The zones it ships to
 * @param array  $excluded_dates      Y-m-d dates nothing is in transit on
 * @param int    $user_id             Who made the change
 * @return int  The method's id, 0 when a query failed
 */
function pg_shipping_method_save($shipping_method_id, $columns, $zone_ids, $excluded_dates, $user_id)
{
    $shipping_method_id = (int) $shipping_method_id;
    $created = ($shipping_method_id < 1);

    $columns['user'] = (int) $user_id;

    if ($created) {
        $saved = db_q(
            "INSERT INTO shipping_methods (" . implode(', ', array_keys($columns)) . ", timestamp)
            VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", UNIX_TIMESTAMP())",
            array_values($columns));

        $shipping_method_id = $saved ? (int) mysqli_insert_id(db::$con) : 0;
    } else {
        $assignments = array();

        foreach (array_keys($columns) as $column) {
            $assignments[] = $column . ' = ?';
        }

        $parameters = array_values($columns);
        $parameters[] = $shipping_method_id;

        $saved = db_q(
            "UPDATE shipping_methods SET " . implode(', ', $assignments) . ", timestamp = UNIX_TIMESTAMP()
            WHERE id = ?",
            $parameters);
    }

    if (!$saved || ($shipping_method_id < 1) || !pg_shipping_method_save_links($shipping_method_id, $zone_ids, $excluded_dates)) {
        return 0;
    }

    pg_announce($created ? 'shipping_method.created' : 'shipping_method.updated', array('id' => $shipping_method_id, 'name' => (string) ($columns['name'] ?? '')));

    return $shipping_method_id;
}

/**
 * Replaces the zones of a shipping method (the shipping method list's
 * Zones field writes them alone).
 *
 * @param int   $shipping_method_id
 * @param array $zone_ids
 * @return bool  false when a query failed
 */
function pg_shipping_method_save_zones($shipping_method_id, $zone_ids)
{
    $shipping_method_id = (int) $shipping_method_id;

    if (!db_q("DELETE FROM shipping_methods_zones_xref WHERE shipping_method_id = ?", array($shipping_method_id))) {
        return false;
    }

    foreach (pg_store_zone_link_ids($zone_ids) as $zone_id) {
        if (!db_q("INSERT INTO shipping_methods_zones_xref (shipping_method_id, zone_id) VALUES (?, ?)", array($shipping_method_id, $zone_id))) {
            return false;
        }
    }

    return true;
}

/**
 * Replaces the zones and the excluded transit dates of a shipping method.
 *
 * @param int   $shipping_method_id
 * @param array $zone_ids
 * @param array $excluded_dates  Y-m-d
 * @return bool  false when a query failed
 */
function pg_shipping_method_save_links($shipping_method_id, $zone_ids, $excluded_dates)
{
    $shipping_method_id = (int) $shipping_method_id;

    if (!pg_shipping_method_save_zones($shipping_method_id, $zone_ids)) {
        return false;
    }

    if (!db_q("DELETE FROM excluded_transit_dates WHERE shipping_method_id = ?", array($shipping_method_id))) {
        return false;
    }

    foreach (array_unique((array) $excluded_dates) as $date) {
        if (!db_q("INSERT INTO excluded_transit_dates (shipping_method_id, date) VALUES (?, ?)", array($shipping_method_id, (string) $date))) {
            return false;
        }
    }

    return true;
}

/**
 * Deletes a shipping method with its zones, excluded dates, offer actions,
 * cut-offs and ship date adjustments; announces shipping_method.deleted.
 *
 * @param int    $shipping_method_id
 * @param string $name  For the event
 * @return bool  false when a query failed
 */
function pg_shipping_method_delete($shipping_method_id, $name = '')
{
    $shipping_method_id = (int) $shipping_method_id;

    foreach (array('shipping_methods_zones_xref', 'excluded_transit_dates', 'offer_actions_shipping_methods_xref', 'shipping_cutoffs', 'ship_date_adjustments') as $table) {
        if (!db_q("DELETE FROM " . $table . " WHERE shipping_method_id = ?", array($shipping_method_id))) {
            return false;
        }
    }

    if (!db_q("DELETE FROM shipping_methods WHERE id = ?", array($shipping_method_id))) {
        return false;
    }

    pg_announce('shipping_method.deleted', array('id' => $shipping_method_id, 'name' => (string) $name));

    return true;
}

/**
 * The word under a shipping method's name on the list when checkout does
 * not offer it: Disabled when switched off, Inactive when on but outside its
 * start and end time, '' when offered. The name's red colour alone told a
 * colour-blind user or a screen reader nothing.
 *
 * @param array  $method 'status', 'start_time', 'end_time'
 * @param string $now    'Y-m-d H:i:s' on the clock the times are stored in
 * @return string  The untranslated word, or ''
 */
function pg_shipping_method_list_state($method, $now)
{
    if (($method['status'] ?? '') != 'enabled') {
        return 'Disabled';
    }

    if ((string) ($method['start_time'] ?? '') > $now || (string) ($method['end_time'] ?? '') < $now) {
        return 'Inactive';
    }

    return '';
}

/**
 * Whether the list shows the Service and Real-Time Rate columns: only
 * while a method names a carrier service; otherwise both are empty on every
 * row and squeeze the name and message.
 *
 * @return bool
 */
function pg_shipping_method_list_has_service()
{
    return (int) db_value("SELECT COUNT(*) FROM shipping_methods WHERE service != ''") > 0;
}
