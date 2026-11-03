<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 9 - Operations Desk: what the shop owes its customers right
 * now, in three lanes -- parcels to send, refunds to pay back, products to
 * restock.
 *
 * The lanes were three cards: Pending Shipments (this id), Out of Stock
 * Products (12) and Refund Pending (26). They answer one question -- what is
 * waiting on us -- and a reader had to add three cards up in their head to
 * get the answer. The strip across the top is that sum: one number per lane,
 * the most urgent lane open underneath, the others a click away.
 *
 * Each lane keeps the reading of the card it came from; the reasons behind
 * each query are written beside it.
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
    if ((ECOMMERCE !== true) || (($user['role'] >= 3) && ($user['manage_ecommerce'] != true))) {
        return array('status' => 'error', 'message' => 'Access denied');
    }

    $lanes = array();

    if (defined('ECOMMERCE_SHIPPING') && (ECOMMERCE_SHIPPING === true)) {
        $lanes['ship'] = pg_dashboard_ops_shipping();
    }

    // The refund columns arrive with the 2026.1.27 upgrade. Without them
    // there is nothing to report and nothing is wrong: an installation that
    // has not been upgraded is not one with unpaid refunds.
    if (pg_schema_has('orders', 'refund_status')) {
        $lanes['refund'] = pg_dashboard_ops_refunds();
    }

    $lanes['stock'] = pg_dashboard_ops_stock();

    // The lane that opens. Money owed back comes first, because a customer
    // waiting on a refund escalates to a chargeback; then a parcel that has
    // waited two days; then anything waiting at all, in lane order.
    $default = '';

    if (isset($lanes['refund']) && ($lanes['refund']['count'] > 0)) {
        $default = 'refund';
    } elseif (isset($lanes['ship']) && $lanes['ship']['late']) {
        $default = 'ship';
    } else {
        foreach ($lanes as $key => $lane) {
            if ($lane['count'] > 0) {
                $default = $key;
                break;
            }
        }
    }

    return array(
        'status' => 'success',
        'message' => lang('Data Received successfully.'),
        'data' => pg_widget_lanes('ops', lang('Operations Desk'), $lanes, $default),
    );
}

/**
 * Which orders the shipping lane looks at: the window and the conditions
 * both its queries add, shared with the Next up card's count.
 *
 * @return array window_days, sql (to append after a WHERE)
 */
function pg_dashboard_ops_shipping_scope()
{
    // What counts as outstanding
    // --------------------------
    // A shipment is outstanding while a shippable line still has quantity
    // that has not been shipped. Tracking numbers are deliberately not
    // consulted: one order is regularly sent in two runs under a single
    // carrier when stock arrives late, and the first number would otherwise
    // mark the whole order as gone.
    //
    // order_items.ship_to_id is the shippable marker. add_order_item() writes
    // it only when shipping is on AND products.shippable was 1 as the line
    // was added, so a digital line in a mixed order carries 0 and can never
    // join a recipient row. Reading products.shippable here instead would
    // answer for today's catalogue rather than for the order as it was placed.
    //
    // ship_tos.complete = 1 skips recipient rows left behind by abandoned
    // carts, and orders.type = 'online' skips counter sales, which are handed
    // over rather than shipped.
    //
    // 'exported' belongs with 'complete': shipworks.php sets it when an order
    // is pulled into fulfilment and writes the shipped quantity back only
    // afterwards, so an exported order is exactly one that is waiting to
    // leave.
    //
    // Both quantity columns are INT UNSIGNED, so subtracting them raises
    // "BIGINT UNSIGNED value is out of range" the moment a line has been
    // recorded as over-shipped, which nothing stops update_order.php from
    // accepting. Casting to SIGNED first lets GREATEST() floor the row at
    // zero instead.
    //
    // Save-for-later lines stay in order_items with the flag set. The column
    // arrived in 2026.1.28, so it is probed for before filtering.
    $sql_saved_for_later = '';
    if (pg_schema_has('order_items', 'saved_for_later')) {
        $sql_saved_for_later = " AND (order_items.saved_for_later = 0)";
    }

    // How far back to look: thirty days from midnight. A site that stops
    // filling in shipping information does not stop taking orders, and on
    // those installs every untouched order would stay outstanding for ever
    // and bury this week's real work under last year's. Anchored to midnight
    // so a row does not drop off mid-morning.
    $window_days = 30;
    $since = pg_dashboard_days_back(strtotime(date('Y-m-d')), $window_days);

    return array(
        'window_days' => $window_days,
        'sql'         => " AND (orders.order_date >= " . (int) $since . ")" . $sql_saved_for_later,
    );
}

/**
 * The orders waiting to be sent, summed: what the lane's strip and the Next
 * up card count.
 *
 * @return array orders, packages, value (minor units), oldest, late, window_days
 */
function pg_dashboard_ops_shipping_summary()
{
    $scope = pg_dashboard_ops_shipping_scope();
    $sql_scope = $scope['sql'];

    // Per order rather than per recipient: orders.total must be summed once
    // per order or a multi-recipient order would count its value twice.
    $summary = db_item(
        "SELECT
            COUNT(*) AS order_count,
            COALESCE(SUM(pending.pending_quantity), 0) AS package_count,
            COALESCE(SUM(pending.order_total), 0) AS pending_value,
            MIN(pending.order_date) AS oldest
        FROM (
            SELECT
                orders.id,
                MAX(orders.total) AS order_total,
                MAX(orders.order_date) AS order_date,
                SUM(GREATEST(CAST(order_items.quantity AS SIGNED) - CAST(order_items.shipped_quantity AS SIGNED), 0)) AS pending_quantity
            FROM orders
            INNER JOIN ship_tos ON (ship_tos.order_id = orders.id) AND (ship_tos.complete = '1')
            INNER JOIN order_items ON order_items.ship_to_id = ship_tos.id
            WHERE
                (orders.status IN ('complete', 'exported'))
                AND (orders.type = 'online')
                $sql_scope
            GROUP BY orders.id
            HAVING pending_quantity > 0
        ) AS pending");

    $orders = (int) ($summary['order_count'] ?? 0);
    $oldest = (int) ($summary['oldest'] ?? 0);

    return array(
        'orders'      => $orders,
        'packages'    => (int) ($summary['package_count'] ?? 0),
        'value'       => (int) ($summary['pending_value'] ?? 0),
        'oldest'      => $oldest,
        // Two days is where a parcel stops being today's packing and starts
        // being one that was left behind.
        'late'        => (($orders > 0) && ($oldest > 0) && ($oldest < (time() - 172800))),
        'window_days' => $scope['window_days'],
    );
}

/**
 * The parcels waiting to be sent, oldest first, one row per recipient:
 * the lane's rows and the Operations Desk card's.
 *
 * @param int $limit
 * @return array ship_to_id, ship_to_name, shipping_method_code,
 *               shipping_method_name, order_id, order_number, order_date,
 *               pending_quantity
 */
function pg_dashboard_ops_shipments($limit)
{
    $scope = pg_dashboard_ops_shipping_scope();
    $sql_scope = $scope['sql'];

    // Grouped per recipient because the carrier lives on ship_tos: one order
    // going to two addresses can leave by two carriers.
    return db_items(
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
            $sql_scope
        GROUP BY ship_tos.id, orders.id
        HAVING pending_quantity > 0
        ORDER BY MAX(orders.order_date) ASC, ship_tos.id ASC
        LIMIT " . max(1, (int) $limit));
}

/**
 * Orders paid for and not yet sent.
 *
 * @return array label, icon, color, count, sub, late, body
 */
function pg_dashboard_ops_shipping()
{
    $scope = pg_dashboard_ops_shipping_scope();
    $window_days = $scope['window_days'];
    $shipping = pg_dashboard_ops_shipping_summary();

    $order_count   = $shipping['orders'];
    $package_count = $shipping['packages'];
    $pending_value = $shipping['value'];

    // One row over the cap is fetched to know whether to offer the full list.
    $row_limit = 10;
    $shipments = pg_dashboard_ops_shipments($row_limit + 1);

    $more = (count($shipments) > $row_limit);
    $shipments = array_slice($shipments, 0, $row_limit);

    // An order shipping to several addresses puts its number on more than one
    // row; those rows name their recipient so they can be told apart.
    $rows_per_order = array();
    foreach ($shipments as $shipment) {
        $rows_per_order[$shipment['order_id']] = ($rows_per_order[$shipment['order_id']] ?? 0) + 1;
    }

    // A fixed palette keyed by a hash of the carrier name, so a carrier keeps
    // its colour across refreshes and installs without anyone choosing one.
    // The row is what the colour identifies here, not the card.
    $carrier_colors = array('#f59e0b', '#8b5cf6', '#3b82f6', '#10b981', '#ec4899', '#14b8a6');

    $output_rows = '';

    foreach ($shipments as $shipment) {
        $carrier = trim((string) $shipment['shipping_method_name']);
        if ($carrier === '') {
            $carrier = trim((string) $shipment['shipping_method_code']);
        }
        if ($carrier === '') {
            $carrier = lang('No Shipping Method');
        }

        $packages = (int) $shipment['pending_quantity'];
        $waited = time() - (int) $shipment['order_date'];

        $meta = '#' . h($shipment['order_number'])
            . ' &middot; ' . h(lang(array(
                'string' => '{var:1} package{suffix:1}',
                'vars' => pg_format_number($packages, 0),
                'suffix' => ($packages == 1 ? '' : 's'),
            )));

        if (($rows_per_order[$shipment['order_id']] ?? 0) > 1) {
            $meta .= ' &middot; ' . h($shipment['ship_to_name']);
        }

        $output_rows .= pg_widget_row(array(
            'href'  => 'view_order.php?id=' . (int) $shipment['order_id'],
            'badge' => '<i class="bi bi-box-seam"></i>',
            'color' => $carrier_colors[abs(crc32($carrier)) % count($carrier_colors)],
            'name'  => h($carrier),
            // Two days is where a parcel stops being today's packing and
            // starts being one that was left behind.
            'aside' => '<span class="' . (($waited >= 172800) ? 'fg-danger' : '') . '">'
                . get_relative_time(array('timestamp' => (int) $shipment['order_date'])) . '</span>',
            'meta'  => $meta,
        ));
    }

    if ($output_rows === '') {
        // Not "everything has been shipped": outside the window this lane
        // has not looked, so it says what was checked.
        $output_rows = pg_widget_empty('bi-check2-circle', lang(array(
            'string' => 'Nothing waiting from the last {var:1} days.',
            'vars' => $window_days,
        )), 'good');
    } elseif ($more) {
        $output_rows .= '<a class="pg-filter-more" href="view_orders.php?reset=true">' . lang('All Orders') . ' <i class="bi bi-arrow-right-short"></i></a>';
    }

    // Twelve slots is a readable meter at the narrowest column; past that the
    // count carries the magnitude and the meter just reads as full.
    $meter = '';
    for ($slot = 0; $slot < 12; $slot++) {
        $meter .= '<span' . (($slot < min($package_count, 12)) ? ' class="is-on"' : '') . '></span>';
    }

    $late = $shipping['late'];

    $sub = ($order_count > 0)
        ? h(lang(array(
            'string' => '{var:1} package{suffix:1}',
            'vars'   => pg_format_number($package_count, 0),
            'suffix' => ($package_count == 1 ? '' : 's'),
        ))) . ' &middot; ' . pg_format_money($pending_value / 100, BASE_CURRENCY_SYMBOL)
        : h(lang('All sent'));

    return array(
        'label'  => lang('To ship'),
        'icon'   => 'bi-truck',
        'color'  => '#d97706',
        'count'  => $order_count,
        'urgent' => $late,
        'late'   => $late,
        'sub'    => $sub,
        'body'   => (($order_count > 0) ? '<div class="pg-ops-meter pg-ship-meter">' . $meter . '</div>' : '')
            . '<div class="pg-list">' . $output_rows . '</div>',
    );
}

/**
 * The refund states that mean money has not gone back yet, as an SQL list.
 *
 * @return string
 */
function pg_dashboard_ops_refund_states_sql()
{
    // All three states mean money has not gone back yet; only 'refunded'
    // and '' are settled. No orders.status test: refund_status is written by
    // the cancellation path alone, and the condition would push the planner
    // off idx_refund_status for nothing.
    return "('manual_required', 'failed', 'pending')";
}

/**
 * The cancelled orders still owed money back, summed: what the lane's strip
 * and the Next up card count. The caller checks orders.refund_status exists.
 *
 * @return array count, owed (minor units, base currency), oldest
 */
function pg_dashboard_ops_refund_summary()
{
    // One sum of what each order still owes (pg_order_cancel_refund_owed():
    // the payment money only when a payment arrived, plus the gift card
    // part), in the store's base currency, the unit every payment driver
    // charges. orders.currency_code is only the currency the visitor was
    // shown prices in, so it does not split the money.
    $summary = db_item(
        "SELECT COUNT(*) AS orders_count, SUM(" . pg_order_cancel_refund_owed_sql(pg_schema_has('orders', 'paid_at'), pg_schema_has('orders', 'refunded_amount')) . ") AS orders_total, MIN(order_date) AS oldest
         FROM orders
         WHERE refund_status IN " . pg_dashboard_ops_refund_states_sql());

    return array(
        'count'  => (int) ($summary['orders_count'] ?? 0),
        'owed'   => (int) round((float) ($summary['orders_total'] ?? 0)),
        'oldest' => (int) ($summary['oldest'] ?? 0),
    );
}

/**
 * The cancelled orders still owed money back, the oldest first: the lane's
 * rows and the Operations Desk card's. The caller checks
 * orders.refund_status exists.
 *
 * @param int $limit
 * @return array orders rows, with what pg_order_cancel_refund_owed() reads
 */
function pg_dashboard_ops_refund_list($limit)
{
    $states = pg_dashboard_ops_refund_states_sql();
    $has_paid_at = pg_schema_has('orders', 'paid_at');
    $has_refunded_amount = pg_schema_has('orders', 'refunded_amount');

    // Oldest first: the customer who has waited longest is the one closest
    // to opening a dispute.
    return db_items(
        "SELECT
            orders.id,
            orders.order_number,
            orders.billing_first_name,
            orders.billing_last_name,
            orders.total,
            orders.payment_method,
            orders.gift_card_discount,
            " . ($has_paid_at ? 'orders.paid_at,' : '') . "
            " . ($has_refunded_amount ? 'orders.refunded_amount,' : '') . "
            orders.cancelled_at,
            orders.refund_status
         FROM orders
         WHERE orders.refund_status IN " . $states . "
         ORDER BY orders.cancelled_at ASC, orders.id ASC
         LIMIT " . max(1, (int) $limit));
}

/**
 * Cancelled orders whose money has not gone back yet.
 *
 * cancel_order.php is customer-facing self-service. A customer cancels; the
 * gateway's automatic refund either fails or was never possible for that
 * payment method; process_order_cancellation() writes
 * orders.refund_status = 'manual_required'. The cancellation email has
 * already promised the customer a manual refund, so this lane is the
 * reminder to keep that promise.
 *
 * @return array label, icon, color, count, sub, body
 */
function pg_dashboard_ops_refunds()
{
    $summary = pg_dashboard_ops_refund_summary();
    $count = $summary['count'];
    $oldest = $summary['oldest'];

    $rows = pg_dashboard_ops_refund_list(10);

    $output_rows = '';
    $urgent = false;

    foreach ($rows as $row) {

        $waited = time() - (int) $row['cancelled_at'];
        $late = ($waited >= 172800);
        $urgent = $urgent || $late;

        $who = trim((string) $row['billing_first_name'] . ' ' . (string) $row['billing_last_name']);
        $number = ((string) $row['order_number'] !== '') ? (string) $row['order_number'] : (string) (int) $row['id'];

        $output_rows .= pg_widget_row(array(
            'href'  => 'view_order.php?id=' . (int) $row['id'] . '&amp;send_to=' . h(urlencode(PATH . SOFTWARE_DIRECTORY . '/welcome.php')),
            'badge' => '<i class="bi bi-arrow-counterclockwise"></i>',
            'color' => '#ec4899',
            'name'  => h(($who !== '') ? $who : lang('Unknown')),
            'aside' => '<span class="' . ($late ? 'fg-danger' : 'fg-emphasis-warning') . '">'
                . get_relative_time(array('timestamp' => (int) $row['cancelled_at'])) . '</span>',
            'meta'  => '#' . h($number) . ' &middot; '
                . pg_format_money(pg_order_cancel_refund_owed($row)['total'] / 100, BASE_CURRENCY_SYMBOL),
        ));
    }

    if ($output_rows === '') {
        $output_rows = pg_widget_empty('bi-check2-circle', lang('No refunds are waiting.'), 'good');
    } elseif ($count > count($rows)) {
        // Every type of order and from the oldest one waiting: the list's own
        // range is the last month of online orders, which would leave some of
        // the ones counted here off it.
        $more_url = pg_dashboard_list_url('view_orders.php', array(
            'reset'  => 'true',
            'status' => 'refund_pending',
            'type'   => 'any'), ($oldest > 0) ? $oldest : time(), time());

        $output_rows .= '<a class="pg-filter-more" href="' . h($more_url) . '">'
            . lang(array('string' => '{var:1} more waiting', 'vars' => pg_format_number($count - count($rows), 0)))
            . ' <i class="bi bi-arrow-right-short"></i></a>';
    }

    $sub = h(lang('Nothing owed'));

    if ($count > 0) {
        $sub = pg_format_money(((float) $summary['owed']) / 100, BASE_CURRENCY_SYMBOL);
    }

    return array(
        'label'  => lang('To refund'),
        'icon'   => 'bi-arrow-counterclockwise',
        'color'  => '#ec4899',
        'count'  => $count,
        'urgent' => $urgent,
        'sub'    => $sub,
        'body'   => '<div class="pg-list">' . $output_rows . '</div>',
    );
}

/**
 * The condition of a product running low, or '' when the shop has set no
 * threshold.
 *
 * @return string
 */
function pg_dashboard_ops_low_stock_where()
{
    // Running low, only where the shop has set a threshold of its own: a
    // made-up line would call a shop that keeps two of everything short of
    // stock every day. Products on backorder never run out, and ones that do
    // not track stock have no level to run low on.
    if (!defined('ECOMMERCE_LOW_STOCK_THRESHOLD') || ((int) ECOMMERCE_LOW_STOCK_THRESHOLD <= 0)) {
        return '';
    }

    return "inventory = '1' AND backorder != '1'
            AND inventory_quantity BETWEEN 1 AND " . (int) ECOMMERCE_LOW_STOCK_THRESHOLD;
}

/**
 * Products out of stock and running low, counted: what the lane's strip and
 * the Next up card count.
 *
 * @return array out, low
 */
function pg_dashboard_ops_stock_counts()
{
    // Not filtered on inventory, so it agrees with the products list's own
    // out of stock filter: a product can be marked out of stock without
    // inventory tracking. Products in the Recycle Bin are left out, as that
    // list leaves them out of its rows and its count.
    $out = (int) db_value("SELECT COUNT(*) FROM products WHERE out_of_stock = '1'" . pg_catalog_bin_live_sql('products'));

    $low_where = pg_dashboard_ops_low_stock_where();
    $low = ($low_where !== '')
        ? (int) db_value("SELECT COUNT(*) FROM products WHERE " . $low_where . pg_catalog_bin_live_sql('products'))
        : 0;

    return array('out' => $out, 'low' => $low);
}

/**
 * Products out of stock, the latest first: the lane's rows and the
 * Operations Desk card's.
 *
 * @param int $limit
 * @return array id, name, short_description, image_name, price, timestamp
 */
function pg_dashboard_ops_out_of_stock_list($limit)
{
    return db_items(
        "SELECT
            products.id,
            products.name,
            products.short_description,
            products.image_name,
            products.price,
            products.out_of_stock_timestamp AS timestamp
        FROM products
        WHERE products.out_of_stock = '1'" . pg_catalog_bin_live_sql('products') . "
        ORDER BY products.out_of_stock_timestamp DESC
        LIMIT " . max(1, (int) $limit));
}

/**
 * Products out of stock, and the ones about to be.
 *
 * @return array label, icon, color, count, sub, body
 */
function pg_dashboard_ops_stock()
{
    $counts = pg_dashboard_ops_stock_counts();
    $count = $counts['out'];

    $rows = pg_dashboard_ops_out_of_stock_list(8);

    $low_count = $counts['low'];
    $low_rows = array();
    $low_where = pg_dashboard_ops_low_stock_where();

    if (($low_where !== '') && ($low_count > 0)) {
        $low_rows = db_items(
            "SELECT id, name, short_description, image_name, inventory_quantity
             FROM products
             WHERE " . $low_where . pg_catalog_bin_live_sql('products') . "
             ORDER BY inventory_quantity ASC, id ASC
             LIMIT 5");
    }

    // A picture deleted in the File Manager leaves image_name behind; the
    // badge then keeps its icon instead of an empty <img>.
    $thumb = function ($row, $icon) {
        return (!empty($row['image_name']) && is_file(FILE_DIRECTORY_PATH . '/' . basename($row['image_name'])))
            ? '<img src="' . PATH . h($row['image_name']) . '" alt="">'
            : '<i class="bi ' . $icon . '"></i>';
    };

    $output_rows = '';

    foreach ($rows as $row) {
        $output_rows .= pg_widget_row(array(
            'href'  => 'edit_product.php?id=' . (int) $row['id'],
            'badge' => $thumb($row, 'bi-exclamation-diamond'),
            'color' => '#ef4444',
            'name'  => h(((string) $row['short_description'] !== '') ? $row['short_description'] : $row['name']),
            'aside' => prepare_amount($row['price'] / 100),
            'meta'  => h($row['name']) . ' &middot; ' . get_relative_time(array('timestamp' => (int) $row['timestamp'])),
        ));
    }

    if ($count > count($rows)) {
        $output_rows .= '<a class="pg-filter-more" href="view_products.php?filter=out_of_stock_products">'
            . lang(array('string' => '{var:1} more out of stock', 'vars' => pg_format_number($count - count($rows), 0)))
            . ' <i class="bi bi-arrow-right-short"></i></a>';
    }

    if (!empty($low_rows)) {
        $output_rows .= pg_widget_row_heading(lang('Running low'), pg_format_number($low_count, 0));

        foreach ($low_rows as $row) {
            $output_rows .= pg_widget_row(array(
                'small' => true,
                'href'  => 'edit_product.php?id=' . (int) $row['id'],
                'badge' => $thumb($row, 'bi-box-seam'),
                'color' => '#f59e0b',
                'name'  => h(((string) $row['short_description'] !== '') ? $row['short_description'] : $row['name']),
                'aside' => h(lang(array('string' => '{var:1} left', 'vars' => pg_format_number((int) $row['inventory_quantity'], 0)))),
            ));
        }
    }

    if ($output_rows === '') {
        $output_rows = pg_widget_empty('bi-check2-circle', lang('Every product is in stock.'), 'good');
    }

    $sub = ($low_count > 0)
        ? h(lang(array('string' => '{var:1} running low', 'vars' => pg_format_number($low_count, 0))))
        : h(($count > 0) ? lang('Not selling') : lang('All in stock'));

    return array(
        'label' => lang('Out of stock'),
        'icon'  => 'bi-exclamation-diamond',
        'color' => '#ef4444',
        'count' => $count,
        'sub'   => $sub,
        'body'  => '<div class="pg-list">' . $output_rows . '</div>',
    );
}
