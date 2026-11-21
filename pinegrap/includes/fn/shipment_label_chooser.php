<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Create Label chooser of the order screen: for an address whose
 * shipping method names no carrier and links no driver, while two or more
 * label drivers are ready, the person picks the driver that carries the
 * parcel. The ready drivers are suggested in the order of
 * pg_delivery_tower_carrier_order(): the province's on-time share from the
 * nightly summary, then the price rate() quotes. A suggestion only; the
 * chosen driver goes to pg_shipment_label_create() as its one optional
 * argument (pg_shipment_label_chosen_driver()).
 *
 * The rules and the rows are pure; only the *_read() functions query.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Seconds the chooser's action spends asking prices; a driver not asked by
// then shows without one.
define('PG_SHIPMENT_LABEL_CHOOSER_SECONDS', 10);

/**
 * Whether Create Label opens the chooser: the method names no carrier and
 * links no driver, and two or more drivers are ready. Pure.
 *
 * @param string $carrier       pg_shipment_label_carrier()
 * @param string $method_driver shipping_methods.label_driver
 * @param array  $drivers       pg_shipment_label_drivers()
 * @return bool
 */
function pg_shipment_label_chooser_due($carrier, $method_driver, $drivers)
{
    return ((string) $carrier === '') && ((string) $method_driver === '') && (count((array) $drivers) >= 2);
}

/**
 * The carrier a driver's on-time share is read for: its one carrier; a
 * driver that ships with several lets its service pick, so it has none.
 * Pure.
 *
 * @param array $carriers the driver's carrier keys
 * @return string
 */
function pg_shipment_label_chooser_carrier($carriers)
{
    $carriers = array_values(array_unique(array_map('strval', (array) $carriers)));

    return (count($carriers) === 1) ? $carriers[0] : '';
}

/**
 * The chooser's rows: the ready drivers in the suggested order, the first
 * suggested. When no driver has an on-time share, today's order (the
 * drivers as given, whose first is the label path's pick) and nothing is
 * suggested. Pure.
 *
 * @param array  $drivers  key => array(name, carriers)
 * @param array  $prices   key => price in base minor units; a driver
 *                         missing or null has none
 * @param array  $on_time  the nightly summary's on_time
 * @param string $province key (pg_delivery_tower_province_key())
 * @return array list of array(key, name, carrier, price, share, scope:
 *               province|country|'', suggested)
 */
function pg_shipment_label_chooser_rows($drivers, $prices, $on_time, $province)
{
    $options = array();
    $province = (string) $province;

    foreach ((array) $drivers as $key => $driver) {
        $carrier = pg_shipment_label_chooser_carrier(isset($driver['carriers']) ? $driver['carriers'] : array());
        $price = (isset($prices[$key]) && is_numeric($prices[$key])) ? (int) $prices[$key] : null;
        $scope = '';

        if ($carrier !== '' && pg_delivery_tower_on_time_share($on_time, $carrier, $province) !== null) {
            $scope = (($province !== '') && isset($on_time[$carrier]['provinces'][$province])) ? 'province' : 'country';
        }

        $options[] = array(
            'key' => (string) $key,
            'name' => isset($driver['name']) ? (string) $driver['name'] : (string) $key,
            'carrier' => $carrier,
            'price' => $price,
            'scope' => $scope,
        );
    }

    $measured = false;

    foreach ($options as $option) {
        $measured = $measured || ($option['scope'] !== '');
    }

    if ($measured) {
        $rows = pg_delivery_tower_carrier_order($options, $on_time, $province);
    } else {
        $rows = array();

        foreach ($options as $option) {
            $rows[] = $option + array('share' => null);
        }
    }

    foreach ($rows as $index => $row) {
        // A driver with several carriers has no carrier, so no share.
        if ($row['carrier'] === '') {
            $rows[$index]['share'] = null;
        }

        $rows[$index]['suggested'] = $measured && ($index === 0);
    }

    return $rows;
}

/**
 * A row's grey second line: "On time 94% in Ankara" (or "across the
 * country"), then " · 84,50 ₺" where a price answered; with no share, "Not
 * enough parcels yet". Pure but for lang() and the money format.
 *
 * @param array  $row           pg_shipment_label_chooser_rows()
 * @param string $province_name the province as the address writes it
 * @param string $symbol        the base currency symbol
 * @return string plain text
 */
function pg_shipment_label_chooser_line($row, $province_name, $symbol)
{
    if ($row['share'] === null || $row['scope'] === '') {
        $line = lang('Not enough parcels yet');
    } elseif ($row['scope'] === 'province') {
        $line = lang(array('string' => 'On time {var:1}% in {var:2}', 'vars' => array(pg_format_number($row['share'], 0), (string) $province_name)));
    } else {
        $line = lang(array('string' => 'On time {var:1}% across the country', 'vars' => array(pg_format_number($row['share'], 0))));
    }

    if ($row['price'] !== null) {
        $line .= ' · ' . pg_money_text($row['price'] / 100, (string) $symbol);
    }

    return $line;
}

/**
 * The rows of the chooser as the dialog shows them: each a label wrapping a
 * radio of the shipment-label-form, the first checked.
 *
 * @param array  $rows          pg_shipment_label_chooser_rows()
 * @param string $province_name
 * @param string $symbol
 * @return string HTML
 */
function pg_shipment_label_chooser_rows_html($rows, $province_name, $symbol)
{
    $html = '';

    foreach (array_values((array) $rows) as $index => $row) {
        $id = 'shipment_label_driver_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($row['key']));
        $html .= '<label class="pg-card-inner-row" for="' . h($id) . '">'
            . '<input class="radio" type="radio" form="shipment-label-form" name="label_driver" id="' . h($id) . '" value="' . h($row['key']) . '"' . (($index === 0) ? ' checked' : '') . '>'
            . '<span class="flex-grow-1 min-width-0">'
            . '<span class="d-flex flex-wrap align-items-center gap-3"><strong class="pg-list-row-title">' . h($row['name']) . '</strong>'
            . ($row['suggested'] ? '<span class="pg-pill pg-tone-teal">' . h(lang('Suggested')) . '</span>' : '') . '</span>'
            . '<span class="pg-text-meta d-block">' . h(pg_shipment_label_chooser_line($row, $province_name, $symbol)) . '</span>'
            . '</span></label>';
    }

    return $html;
}

/**
 * The dialog the chooser opens in: a drawer 480 px off the right edge (a
 * sheet from the bottom under 576 px, set by its script), printed once on
 * the order screen. Its rows are read when it opens
 * (shipment_label_choose.php); Create label posts shipment-label-form with
 * label_request create-<ship_to_id> and the chosen label_driver.
 *
 * @return string HTML
 */
function pg_shipment_label_chooser_dialog()
{
    return '<dialog class="drawer drawer-end" id="shipment-label-chooser" aria-labelledby="shipment-label-chooser-title" style="width:min(480px,100%);--pg-drawer-height:auto"'
        . ' data-reading="' . h(lang('Reading prices…')) . '" data-failed="' . h(lang('The carriers could not be read. Close this and try again.')) . '">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="shipment-label-chooser-title">' . lang('Create label') . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . lang('This shipping method names no carrier. Choose who carries the parcel.') . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body">
                <fieldset>
                    <legend class="visually-hidden">' . lang('Carrier') . '</legend>
                    <div class="pg-card-inner" id="shipment-label-chooser-rows" aria-live="polite"></div>
                </fieldset>
                <p class="pg-text-meta mt-3 mb-0">' . lang('A suggestion from your own deliveries; the choice is yours.') . '</p>
            </div>
            <div class="drawer-footer border-top d-flex align-items-center gap-3">
                <span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . lang('Cancel') . '</button>
                <button type="submit" form="shipment-label-form" name="label_request" value="" id="shipment-label-chooser-create" class="pg-button pg-button-fill" disabled>' . lang('Create label') . '</button>
            </div>
        </dialog>
        <script src="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY) . '/assets/js/shipment_label_chooser.js?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/assets/js/shipment_label_chooser.js') . '" defer></script>';
}

/**
 * The address's method as the chooser weighs it: the carrier it names (or
 * its code spells) and the driver it links.
 *
 * @param int $ship_to_id
 * @return array|null carrier, method_driver, state, city; null when the
 *                    address is not there
 */
function pg_shipment_label_chooser_method_read($ship_to_id)
{
    $link = pg_schema_has('shipping_methods', 'label_driver');

    $row = db_item(
        "SELECT
            ship_tos.shipping_method_code,
            ship_tos.state,
            ship_tos.city,
            shipping_methods.carrier AS method_carrier" . ($link ? ",
            shipping_methods.label_driver AS method_label_driver" : "") . "
        FROM ship_tos
        LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
        WHERE ship_tos.id = '" . (int) $ship_to_id . "'");

    if (!$row) {
        return null;
    }

    return array(
        'carrier' => pg_shipment_label_carrier(isset($row['method_carrier']) ? $row['method_carrier'] : '', $row['shipping_method_code']),
        'method_driver' => isset($row['method_label_driver']) ? (string) $row['method_label_driver'] : '',
        'state' => (string) $row['state'],
        'city' => (string) $row['city'],
    );
}

/**
 * What the chooser's action answers for an address: whether the chooser
 * applies, the province as written and the rows' HTML. Prices are asked
 * through pg_shipping_label_rate() (its day-long cache first) for the
 * address's whole parcel, cheapest service; a driver without rate(), one
 * that fails, or one not reached in PG_SHIPMENT_LABEL_CHOOSER_SECONDS shows
 * without a price. Nothing of the address beyond the province is answered.
 *
 * @param int $ship_to_id
 * @return array due, html
 */
function pg_shipment_label_chooser_read($ship_to_id)
{
    $method = pg_shipment_label_chooser_method_read($ship_to_id);
    $ready = pg_shipment_label_drivers();

    if (!$method || !pg_shipment_label_chooser_due($method['carrier'], $method['method_driver'], $ready)) {
        return array('due' => false, 'html' => '');
    }

    $ship_to = db_item("SELECT country, zip_code, state, city FROM ship_tos WHERE id = '" . (int) $ship_to_id . "'");
    $items = db_items(
        "SELECT
            order_items.quantity,
            products.weight,
            products.length,
            products.width,
            products.height
        FROM order_items
        LEFT JOIN products ON products.id = order_items.product_id
        WHERE (order_items.ship_to_id = '" . (int) $ship_to_id . "')" . pg_order_item_active_sql());

    $drivers = array();
    $prices = array();
    $until = microtime(true) + PG_SHIPMENT_LABEL_CHOOSER_SECONDS;

    foreach ($ready as $key => $carriers) {
        $drivers[$key] = array('name' => pg_shipment_label_driver_name($key), 'carriers' => $carriers);

        if (!pg_label_can($key, 'rate') || (microtime(true) > $until)) {
            continue;
        }

        $price = pg_shipping_label_rate(array(
            'label_driver' => $key,
            'label_service' => '',
            'items' => (array) $items,
            'country' => $ship_to['country'],
            'zip_code' => $ship_to['zip_code'],
            'state' => $ship_to['state'],
            'city' => $ship_to['city'],
        ));

        if ($price !== false && $price > 0) {
            $prices[$key] = (int) $price;
        }
    }

    $summary = pg_delivery_tower_summary();
    $on_time = ($summary && isset($summary['on_time']) && is_array($summary['on_time'])) ? $summary['on_time'] : array();
    $province_name = trim((string) (($method['state'] !== '') ? $method['state'] : $method['city']));
    $rows = pg_shipment_label_chooser_rows($drivers, $prices, $on_time, pg_delivery_tower_province_key($method['state'], $method['city']));

    return array(
        'due' => true,
        'html' => pg_shipment_label_chooser_rows_html($rows, $province_name, defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : ''),
    );
}
