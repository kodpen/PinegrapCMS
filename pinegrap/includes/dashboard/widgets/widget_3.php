<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 3 - Ecommerce Summary: order totals over recent periods.
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

function pg_dashboard_widget_3($request, $user)
{
    if ((ECOMMERCE === true) and (($user['role'] < 3) or USER_MANAGE_ECOMMERCE or USER_MANAGE_ECOMMERCE_REPORTS)) {

        // ── Orders ───────────────────────────────────────────────
        //
        // One aggregate rather than the whole table. The body that
        // stood here selected every completed order and bucketed
        // them in a PHP foreach, so a shop with fifty thousand
        // orders moved fifty thousand rows across the wire on every
        // dashboard load in order to arrive at eight numbers.
        //
        // The buckets are anchored to midnight rather than to
        // time(). "Today" was (time() - order_date) < 86400 -- the
        // last twenty-four hours -- so at three in the afternoon it
        // counted yesterday afternoon as today. Anchoring also
        // keeps the rows nested: today always sits inside the week
        // and the week inside the month, which is what a reader
        // assumes when four periods are stacked.
        $midnight   = strtotime(date('Y-m-d'));
        $from_week  = $midnight - (6 * 86400);
        $from_month = $midnight - (29 * 86400);

        $order_summary = db_item(
            "SELECT
                COUNT(*) AS all_count,
                COALESCE(SUM(orders.total), 0) AS all_total,
                COALESCE(MAX(orders.order_date), 0) AS last_order_date,
                COALESCE(SUM(CASE WHEN orders.order_date >= $midnight THEN 1 ELSE 0 END), 0) AS today_count,
                COALESCE(SUM(CASE WHEN orders.order_date >= $midnight THEN orders.total ELSE 0 END), 0) AS today_total,
                COALESCE(SUM(CASE WHEN orders.order_date >= $from_week THEN 1 ELSE 0 END), 0) AS week_count,
                COALESCE(SUM(CASE WHEN orders.order_date >= $from_week THEN orders.total ELSE 0 END), 0) AS week_total,
                COALESCE(SUM(CASE WHEN orders.order_date >= $from_month THEN 1 ELSE 0 END), 0) AS month_count,
                COALESCE(SUM(CASE WHEN orders.order_date >= $from_month THEN orders.total ELSE 0 END), 0) AS month_total
            FROM orders
            WHERE orders.status IN ('complete', 'exported')"
        );

        // ── Stock ────────────────────────────────────────────────
        //
        // Same treatment: the old body read every inventory-tracked
        // product in order to add up three numbers.
        //
        // The out-of-stock count deliberately does not filter on
        // inventory, so that it agrees with the Out of Stock
        // Products card, which does not filter on it either. A
        // product can be marked out of stock without inventory
        // tracking, and the operator who reads both cards should
        // not have to know that.
        $stock_summary = db_item(
            "SELECT
                COALESCE(SUM(CASE WHEN products.inventory = 1 THEN 1 ELSE 0 END), 0) AS product_count,
                COALESCE(SUM(CASE WHEN products.inventory = 1 THEN products.inventory_quantity ELSE 0 END), 0) AS quantity_total,
                COALESCE(SUM(CASE WHEN products.inventory = 1 THEN products.price * products.inventory_quantity ELSE 0 END), 0) AS stock_value,
                COALESCE(SUM(CASE WHEN products.out_of_stock = '1' THEN 1 ELSE 0 END), 0) AS out_of_stock_count
            FROM products"
        );

        $all_count          = (int) ($order_summary['all_count'] ?? 0);
        $all_total          = (float) ($order_summary['all_total'] ?? 0);
        $last_order_date    = (int) ($order_summary['last_order_date'] ?? 0);
        $product_count      = (int) ($stock_summary['product_count'] ?? 0);
        $quantity_total     = (int) ($stock_summary['quantity_total'] ?? 0);
        $stock_value        = (float) ($stock_summary['stock_value'] ?? 0);
        $out_of_stock_count = (int) ($stock_summary['out_of_stock_count'] ?? 0);

        // Separators are given explicitly. number_format() with
        // only a precision falls back to English ones, which is how
        // the piece count used to read "9,986" on the same line as
        // a stock value of "798.380,70". The other dashboard
        // widgets pass ',' and '.' the same way.
        $pg_money = function ($cents) {
            return pg_format_money($cents / 100, BASE_CURRENCY_SYMBOL);
        };
        $pg_count = function ($number) {
            return pg_format_number($number, 0);
        };

        // ── Head: stock on one line, two facts under it ──────────
        $output_stock_sub = $pg_count($product_count) . ' ' . lang('Product(s)')
            . ' <span class="pg-ec-dot">&middot;</span> '
            . $pg_count($quantity_total) . ' ' . lang('Piece(s)');

        // Only when there is something to act on. A steady "0
        // out of stock" is a phrase the eye learns to skip, and then the
        // day it says 3 it gets skipped too.
        if ($out_of_stock_count > 0) {
            $output_stock_sub .= ' <span class="pg-ec-dot">&middot;</span> '
                . '<span class="pg-ec-warn">'
                . lang(array(
                    'string' => '{var:1} out of stock',
                    'vars' => $pg_count($out_of_stock_count),
                ))
                . '</span>';
        }

        // Average basket and the age of the last order: the two
        // questions the removed tiles could not answer. The second
        // one is the cheapest "is this shop still trading?" signal
        // on the card -- a period row reading zero cannot tell a
        // quiet Tuesday from a checkout that has been broken since
        // Friday. get_relative_time() switches to a plain date past
        // a month, which is the right answer at that distance.
        $output_average_order = ($all_count > 0)
            ? $pg_money($all_total / $all_count)
            : '&mdash;';
        $output_last_order = ($last_order_date > 0)
            ? h(get_relative_time(array('timestamp' => $last_order_date, 'format' => 'plain_text')))
            : '&mdash;';

        // ── Period rows ──────────────────────────────────────────
        //
        // "Last 1 Year" is gone. On any shop older than a year it
        // says the same thing as the all-time figure, and on a shop
        // whose trade stopped a year ago it said 0 while the card
        // above it showed a lifetime total -- the state this dev
        // install is in. All Time carries the order count and the
        // lifetime total that used to need a tile of their own.
        $periods = array(
            array(
                'label' => lang('Today'),
                'icon' => 'bi-sun-fill',
                'color' => '#f59e0b',
                'count' => (int) ($order_summary['today_count'] ?? 0),
                'total' => (float) ($order_summary['today_total'] ?? 0),
            ),
            array(
                'label' => lang('Last 1 Week'),
                'icon' => 'bi-calendar-week',
                'color' => '#3b82f6',
                'count' => (int) ($order_summary['week_count'] ?? 0),
                'total' => (float) ($order_summary['week_total'] ?? 0),
            ),
            array(
                'label' => lang('Last 1 Month'),
                'icon' => 'bi-calendar-month',
                'color' => '#8b5cf6',
                'count' => (int) ($order_summary['month_count'] ?? 0),
                'total' => (float) ($order_summary['month_total'] ?? 0),
            ),
            array(
                'label' => lang('All Time'),
                'icon' => 'bi-infinity',
                'color' => '#10b981',
                'count' => $all_count,
                'total' => $all_total,
                'total_row' => true,
            ),
        );

        $output_period_rows = '';
        foreach ($periods as $p) {
            // Each period already has its own colour in $periods,
            // and the four of them are a scale rather than one card
            // accent, so they keep it.
            $output_period_rows .= pg_widget_row(array(
                'small' => true,
                'muted' => (($p['count'] == 0) && empty($p['total_row'])),
                'class' => (!empty($p['total_row']) ? 'pg-row-total' : ''),
                'color' => $p['color'],
                'badge' => '<i class="bi ' . $p['icon'] . '"></i>',
                'name'  => $p['label'],
                'aside' => '<span class="badge rounded-pill me-1" style="background:' . $p['color'] . '22;color:' . $p['color'] . '">' . $pg_count($p['count']) . '</span>'
                    . '<span class="fw-semibold">' . ($p['count'] > 0 ? $pg_money($p['total']) : '&mdash;') . '</span>',
            ));
        }

        $output_data = '
        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
            <div class="pg-ec-head">
                <div class="pg-ec-line">
                    <span class="pg-ec-val">' . $pg_money($stock_value) . '</span>
                    <span class="pg-ec-lbl text-muted">' . lang('Stock Value') . '</span>
                </div>
                <div class="pg-ec-sub text-muted">' . $output_stock_sub . '</div>
            </div>
            <div class="pg-ec-facts">
                <div class="pg-ec-fact">
                    <span class="text-muted">' . lang('Average order') . '</span>
                    <b>' . $output_average_order . '</b>
                </div>
                <div class="pg-ec-fact">
                    <span class="text-muted">' . lang('Last order') . '</span>
                    <b>' . $output_last_order . '</b>
                </div>
            </div>
            <div class="border-top mx-2 mb-1"></div>
            <div class="pg-list">' . $output_period_rows . '</div>
        </div>';

        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data,
        );
        return $response;
    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
