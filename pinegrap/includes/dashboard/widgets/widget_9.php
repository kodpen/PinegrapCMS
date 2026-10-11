<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 9 - Pending Shipments: orders with shippable lines not yet shipped.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_9($request, $user)
{
    $output_rows = '';
    if ((ECOMMERCE === true) && (ECOMMERCE_SHIPPING === true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

        // What counts as outstanding
        // --------------------------
        // A shipment is outstanding while a shippable line still
        // has quantity that has not been shipped. Tracking numbers
        // are deliberately not consulted: one order is regularly
        // sent in two runs under a single carrier when stock
        // arrives late, and the first number would otherwise mark
        // the whole order as gone.
        //
        // order_items.ship_to_id is the shippable marker.
        // add_order_item() writes it only when shipping is on AND
        // products.shippable was 1 as the line was added, so a
        // digital line in a mixed order carries 0 and can never
        // join a recipient row. Reading products.shippable here
        // instead would answer for today's catalogue rather than
        // for the order as it was placed.
        //
        // ship_tos.complete = 1 skips recipient rows left behind by
        // abandoned carts, and orders.type = 'online' skips
        // counter sales, which are handed over rather than shipped.
        //
        // 'exported' belongs with 'complete': shipworks.php sets it
        // when an order is pulled into fulfilment and writes the
        // shipped quantity back only afterwards, so an exported
        // order is exactly one that is waiting to leave. Sites on
        // the export flow would otherwise see an empty card.

        // Both quantity columns are INT UNSIGNED, so subtracting
        // them raises "BIGINT UNSIGNED value is out of range" and
        // aborts the query the moment a line has been recorded as
        // over-shipped, which nothing stops update_order.php from
        // accepting. Casting to SIGNED first lets GREATEST() floor
        // the row at zero instead. Since PHP 8.1 mysqli throws on
        // that error, so the card would take the dashboard with it.
        //
        // Save-for-later lines stay in order_items with the flag
        // set. The column arrived in 2026.1.28, so probe for it
        // before filtering, or the widget dies on older schemas.
        $sql_saved_for_later = '';
        if (db_value("SHOW COLUMNS FROM order_items LIKE 'saved_for_later'") != '') {
            $sql_saved_for_later = " AND (order_items.saved_for_later = 0)";
        }

        // How far back to look
        // --------------------
        // A site that stops filling in shipping information does
        // not stop taking orders. On those installs every untouched
        // order stays outstanding for ever, the card fills with
        // three to five hundred of them, and this week's real work
        // is buried under last year's. An order that has sat here a
        // month is not a shipment waiting to leave -- it is a site
        // that does not use this screen -- and a card nobody can
        // read is worse than a card that admits a horizon.
        //
        // The cut is on orders.order_date, the date the order was
        // placed. An order nobody ever touched has nothing else to
        // date it by; a shipping column would only date the orders
        // that were already being handled.
        //
        // Anchored to midnight rather than "now minus thirty days",
        // so a row does not drop off mid-morning as the clock
        // passes the hour its order was placed at.
        $pending_window_days = 30;
        $pending_since = strtotime(date('Y-m-d')) - ($pending_window_days * 86400);
        $sql_pending_window = " AND (orders.order_date >= " . $pending_since . ")";

        // Headline figures, grouped per order rather than per
        // recipient: the package count is the same either way, but
        // orders.total must be summed once per order or a
        // multi-recipient order would contribute its value twice.
        // MAX(orders.total) rather than a bare column because
        // ONLY_FULL_GROUP_BY does not always trace the functional
        // dependency on orders.id through a join.
        $pending_summary = db_item(
            "SELECT
                COALESCE(SUM(pending.pending_quantity), 0) AS package_count,
                COALESCE(SUM(pending.order_total), 0) AS pending_value
            FROM (
                SELECT
                    orders.id,
                    MAX(orders.total) AS order_total,
                    SUM(GREATEST(CAST(order_items.quantity AS SIGNED) - CAST(order_items.shipped_quantity AS SIGNED), 0)) AS pending_quantity
                FROM orders
                INNER JOIN ship_tos ON (ship_tos.order_id = orders.id) AND (ship_tos.complete = '1')
                INNER JOIN order_items ON order_items.ship_to_id = ship_tos.id
                WHERE
                    (orders.status IN ('complete', 'exported'))
                    AND (orders.type = 'online')
                    $sql_pending_window
                    $sql_saved_for_later
                GROUP BY orders.id
                HAVING pending_quantity > 0
            ) AS pending"
        );

        $package_count = (int) ($pending_summary['package_count'] ?? 0);
        $pending_value = (int) ($pending_summary['pending_value'] ?? 0);

        // Rows are grouped per recipient because the carrier lives
        // on ship_tos: one order going to two addresses can leave
        // by two different carriers, and a row that averaged them
        // would name neither. One row over the display cap is
        // fetched so we can tell whether to offer the full list.
        $row_limit = 12;
        $pending_shipments = db_items(
            "SELECT
                ship_tos.id AS ship_to_id,
                MAX(ship_tos.ship_to_name) AS ship_to_name,
                MAX(ship_tos.shipping_method_code) AS shipping_method_code,
                MAX(shipping_methods.name) AS shipping_method_name,
                orders.id AS order_id,
                MAX(orders.order_number) AS order_number,
                MAX(orders.order_date) AS order_date,
                SUM(GREATEST(CAST(order_items.quantity AS SIGNED) - CAST(order_items.shipped_quantity AS SIGNED), 0)) AS pending_quantity
            FROM ship_tos
            INNER JOIN orders ON orders.id = ship_tos.order_id
            INNER JOIN order_items ON order_items.ship_to_id = ship_tos.id
            LEFT JOIN shipping_methods ON shipping_methods.id = ship_tos.shipping_method_id
            WHERE
                (orders.status IN ('complete', 'exported'))
                AND (orders.type = 'online')
                AND (ship_tos.complete = '1')
                $sql_pending_window
                $sql_saved_for_later
            GROUP BY ship_tos.id, orders.id
            HAVING pending_quantity > 0
            ORDER BY MAX(orders.order_date) ASC, ship_tos.id ASC
            LIMIT " . ($row_limit + 1)
        );

        $more_shipments_exist = (count($pending_shipments) > $row_limit);
        if ($more_shipments_exist) {
            $pending_shipments = array_slice($pending_shipments, 0, $row_limit);
        }

        // An order that ships to several addresses puts the same
        // order number on more than one row. Count the rows per
        // order so those rows can name their recipient and stay
        // tellable apart; single-recipient rows say nothing extra.
        $rows_per_order = array();
        foreach ($pending_shipments as $shipment) {
            $oid = $shipment['order_id'];
            $rows_per_order[$oid] = ($rows_per_order[$oid] ?? 0) + 1;
        }

        // Fixed palette keyed by a hash of the carrier name, so a
        // carrier keeps its colour across refreshes and installs
        // without anyone having to configure one.
        $carrier_colors = array('#f59e0b', '#8b5cf6', '#3b82f6', '#10b981', '#ec4899', '#14b8a6');

        $output_rows = '';
        if (!empty($pending_shipments)) {
            foreach ($pending_shipments as $shipment) {
                $carrier = trim((string) $shipment['shipping_method_name']);
                if ($carrier === '') {
                    $carrier = trim((string) $shipment['shipping_method_code']);
                }
                if ($carrier === '') {
                    $carrier = lang('No Shipping Method');
                }

                $carrier_color = $carrier_colors[abs(crc32($carrier)) % count($carrier_colors)];
                $shipment_packages = (int) $shipment['pending_quantity'];

                $meta = '#' . h($shipment['order_number'])
                    . ' &middot; ' . h(lang(array(
                        'string' => '{var:1} package{suffix:1}',
                        'vars' => pg_format_number($shipment_packages, 0),
                        'suffix' => ($shipment_packages == 1 ? '' : 's'),
                    )));

                if (($rows_per_order[$shipment['order_id']] ?? 0) > 1) {
                    $meta .= ' &middot; ' . h($shipment['ship_to_name']);
                }

                // A real link rather than an onclick handler, so
                // the row is reachable by keyboard and opens in a
                // new tab on middle click like any other row here.
                $output_rows .= pg_widget_row(array(
                    'href'  => 'view_order.php?id=' . (int) $shipment['order_id'],
                    'badge' => '<i class="bi bi-box-seam"></i>',
                    'color' => $carrier_color,
                    'name'  => h($carrier),
                    'meta'  => $meta,
                ));
            }

            // Only offered when the list was actually cut short,
            // so the link never implies there is more to see when
            // the card already shows everything.
            if ($more_shipments_exist) {
                $output_rows .= '
                <div class="pg-ship-more">
                    <a href="view_orders.php" class="small text-decoration-none">' . lang('All Orders') . '</a>
                </div>';
            }
        } else {
            // Not "everything has been shipped": outside the
            // window this card has not looked, and on the very
            // installs the window exists for there are hundreds
            // sitting there. Say what was actually checked.
            $output_rows = pg_widget_empty(
                'bi-check2-circle',
                lang(array(
                    'string' => 'Nothing waiting from the last {var:1} days.',
                    'vars' => $pending_window_days,
                )),
                'good');
        }

        // Package meter. Twelve slots is a readable width at the
        // narrowest dashboard column; past that the count in the
        // headline carries the magnitude and the meter just reads
        // as full. Both dot states are styled in backend.src.css;
        // only the on/off class is decided here.
        $meter_slots = 12;
        $meter_filled = min($package_count, $meter_slots);
        $output_meter = '';
        for ($slot = 0; $slot < $meter_slots; $slot++) {
            $output_meter .= '<span' . ($slot < $meter_filled ? ' class="is-on"' : '') . '></span>';
        }

        // Two keys rather than one sentence: the entity between
        // them is markup, and a translator should not have to carry
        // it through to keep the line from breaking.
        $output_summary =
            lang(array(
                'string' => 'package{suffix:1}',
                'suffix' => ($package_count == 1 ? '' : 's'),
            ))
            . ' &middot; '
            . lang(array(
                'string' => 'worth {var:1}',
                'vars' => pg_format_money($pending_value / 100, BASE_CURRENCY_SYMBOL),
            ));

        $output_data = '
        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
            <div class="pg-ship-head">
                <div class="d-flex align-items-baseline flex-wrap gap-2">
                    <span class="pg-ship-count">' . pg_format_number($package_count, 0) . '</span>
                    <span class="pg-ship-summary text-muted">' . $output_summary . '</span>
                </div>
                <div class="pg-ship-meter">' . $output_meter . '</div>
            </div>
            <div class="pg-list">
                ' . $output_rows . '
            </div>
        </div>';

        $response = array(
            'status' => 'success',
            'message' => lang('Data Received successfully.'),
            'data' => $output_data,
        );
        return $response;

    } else {
        $response = array('status' => 'error', 'message' => 'Access denied');
        return $response;
    }
}
