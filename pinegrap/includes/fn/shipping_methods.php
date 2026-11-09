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

    $columns = pg_shipping_method_link_save_columns($shipping_method_id, $columns);
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

/**
 * The columns of the carrier label link (label_driver, label_service) and
 * the carrier a linked method ships with, as pg_shipping_method_save()
 * writes them. The carrier follows the linked service when the driver ships
 * with the carrier the service code starts with; a link removed takes the
 * carrier it set with it. Files land before the upgrade runs, so a column
 * the database does not have yet is left out.
 *
 * @param int   $shipping_method_id 0 for a new method
 * @param array $columns            Column name => value
 * @return array
 */
function pg_shipping_method_link_save_columns($shipping_method_id, $columns)
{
    if (!array_key_exists('label_driver', $columns)) {
        return $columns;
    }

    if (!pg_schema_has('shipping_methods', 'label_driver') || !pg_schema_has('shipping_methods', 'label_service')) {
        unset($columns['label_driver'], $columns['label_service']);
        return $columns;
    }

    if (!pg_schema_has('shipping_methods', 'carrier')) {
        return $columns;
    }

    $stored = ((int) $shipping_method_id > 0)
        ? db_item("SELECT carrier, label_driver FROM shipping_methods WHERE id = '" . (int) $shipping_method_id . "'")
        : null;

    $driver = (string) $columns['label_driver'];
    $info = ($driver !== '') ? pg_label_info($driver) : array('carriers' => array());

    $columns['carrier'] = pg_shipping_method_link_carrier(
        $driver,
        (string) ($columns['label_service'] ?? ''),
        $info['carriers'],
        is_array($stored) ? (string) $stored['carrier'] : '',
        is_array($stored) ? (string) $stored['label_driver'] : '');

    return $columns;
}

/**
 * The carrier a shipping method ships with once its link is saved. Linked:
 * the carrier key the service code starts with (YURTICI_STANDART,
 * usps_priority, postnl:standard), when the driver ships with it, else ''
 * (the cheapest offer may come from any of them). Unlinked: '' when the
 * stored link set the carrier, else the stored carrier as it is. Pure.
 *
 * @param string $driver         The linked driver, '' for none
 * @param string $service        The linked service code, '' for the cheapest
 * @param array  $carriers       The carrier keys the driver ships with
 * @param string $stored_carrier shipping_methods.carrier before the save
 * @param string $stored_driver  shipping_methods.label_driver before the save
 * @return string
 */
function pg_shipping_method_link_carrier($driver, $service, $carriers, $stored_carrier, $stored_driver)
{
    if ((string) $driver === '') {
        return ((string) $stored_driver !== '') ? '' : (string) $stored_carrier;
    }

    $words = preg_split('/[^a-z0-9]+/', strtolower((string) $service), -1, PREG_SPLIT_NO_EMPTY);

    if ($words && in_array($words[0], (array) $carriers, true)) {
        return $words[0];
    }

    return '';
}

/**
 * The services a label driver names in its info ('services': code => name),
 * cleaned: string codes of at most 64 characters (label_service) with a
 * name, the code itself standing for a missing one. Pure.
 *
 * @param mixed $info The driver's _info() answer
 * @return array code => name
 */
function pg_shipping_method_label_services_clean($info)
{
    $services = array();

    if (!is_array($info) || !isset($info['services']) || !is_array($info['services'])) {
        return $services;
    }

    foreach ($info['services'] as $code => $name) {
        $code = trim((string) $code);

        if (($code === '') || (mb_strlen($code) > 64)) {
            continue;
        }

        $name = is_scalar($name) ? trim((string) $name) : '';
        $services[$code] = ($name !== '') ? $name : $code;
    }

    return $services;
}

/**
 * The label drivers a shipping method may be linked to: those switched on,
 * set up and able to make a label (pg_shipment_label_drivers()), each with
 * its name and the services its info lists (none: the service is typed).
 *
 * @return array key => array(name, services)
 */
function pg_shipping_method_label_drivers()
{
    $drivers = array();

    foreach (array_keys(pg_shipment_label_drivers()) as $key) {
        $services = array();

        try {
            $services = pg_shipping_method_label_services_clean(call_user_func(pg_label_function_name($key, 'info')));
        } catch (\Throwable $exception) {
            $services = array();
        }

        $drivers[$key] = array('name' => pg_shipment_label_driver_name($key), 'services' => $services);
    }

    return $drivers;
}

/**
 * The link a post of the method screen saves. label_link is
 * "<driver>:<service>" from the picker (the service '' for the cheapest);
 * for a driver without a service list the service is the typed code
 * (label_service_code). A known driver that is not set up now stays
 * linked as posted (the picker offers the stored link back), so a plain save
 * never drops a link whose service was switched off. Pure.
 *
 * @param mixed $link    The posted label_link
 * @param mixed $code    The posted label_service_code
 * @param array $drivers pg_shipping_method_label_drivers()
 * @param array $known   Every driver key (pg_label_drivers())
 * @return array label_driver, label_service
 */
function pg_shipping_method_label_link_decide($link, $code, $drivers, $known)
{
    $link = is_scalar($link) ? trim((string) $link) : '';

    if (strpos($link, ':') === false) {
        return array('label_driver' => '', 'label_service' => '');
    }

    list($driver, $service) = explode(':', $link, 2);

    if (!isset($drivers[$driver]) && !in_array($driver, (array) $known, true)) {
        return array('label_driver' => '', 'label_service' => '');
    }

    if (isset($drivers[$driver]) && !$drivers[$driver]['services']) {
        $service = is_scalar($code) ? (string) $code : '';
    }

    return array('label_driver' => $driver, 'label_service' => mb_substr(trim($service), 0, 64));
}

/**
 * Whether a method can charge a real-time rate: a legacy USPS or UPS
 * service (FedEx gives none) or a carrier label service link. Pure.
 *
 * @param string $service      shipping_methods.service
 * @param string $label_driver shipping_methods.label_driver
 * @return bool
 */
function pg_shipping_method_has_realtime_rate($service, $label_driver)
{
    if ((string) $label_driver !== '') {
        return true;
    }

    $service = (string) $service;

    return ($service !== '') && (substr($service, 0, 5) !== 'fedex');
}

