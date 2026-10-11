<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 26 - Refund Pending: cancelled orders that need a manual refund.
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

function pg_dashboard_widget_26($request, $user)
{
    // ── Pending refunds ─────────────────────────────────────
    //
    // cancel_order.php is customer-facing self-service. A customer
    // cancels; Iyzipay's auto-refund either fails or was never
    // possible for that payment method; process_order_cancellation()
    // writes orders.refund_status = 'manual_required' and a
    // REFUND ACTION REQUIRED line into the activity log.
    //
    // That log line was the only thing on the operator's side. On
    // the customer's side the cancellation email already says
    // "Payment refunds are processed manually. Please contact us
    // for refund status." — so the software has made a promise
    // that nothing in the panel reminded anyone to keep.
    //
    // view_orders.php filters on orders.status, so "cancelled" is
    // reachable but "still owes a refund" was not; the flag showed
    // up one order at a time on view_order.php. A refund filter is
    // added there in the same change for the full list — this card
    // exists to say the queue is non-empty at all.
    //
    // Oldest first: a customer waiting on money escalates to a
    // chargeback, and the fee plus the gateway risk score costs
    // more than the refund did.
    if (defined('ECOMMERCE') && ECOMMERCE
        && (($user['role'] < 3) || (isset($user['manage_ecommerce']) && $user['manage_ecommerce']))
    ) {

        // Columns arrive with the 2026.1.27 upgrade. Without them
        // there is nothing to report and nothing is wrong — an
        // installation that has not been upgraded is not an
        // installation with unpaid refunds.
        if (!db_item("SHOW COLUMNS FROM orders LIKE 'refund_status'")) {

            $output_data = '
            <div class="card-body d-flex align-items-center justify-content-center text-center">
                <div>
                    <i class="bi bi-database-exclamation d-block mb-2" style="font-size:22px;opacity:.4"></i>
                    <p class="text-muted mb-0" style="font-size:12px">' . lang('The refund columns do not exist yet. Please run the software upgrade to create them.') . '</p>
                </div>
            </div>';

            $response = array('status' => 'success', 'message' => 'Action Success', 'data' => $output_data);
            return $response;
        }

        // 'pending' and 'failed' belong here as much as
        // 'manual_required': all three mean money has not gone
        // back yet. Only 'refunded' and '' are settled.
        //
        // No orders.status test. refund_status is written by the
        // cancellation path alone, so it already implies a
        // cancelled order, and adding the condition would push the
        // planner off idx_refund_status for nothing.
        $rf_states = "('manual_required', 'failed', 'pending')";

        // Grouped by currency on purpose. Summing a 500 TRY refund
        // and a 40 EUR refund into "540" would be a made-up number
        // on any multi-currency shop, so the total is only shown
        // when every waiting refund is in one currency; otherwise
        // the count stands on its own.
        $rf_groups = db_items(
            "SELECT currency_code, COUNT(*) AS orders_count, SUM(total) AS orders_total
             FROM orders
             WHERE refund_status IN " . $rf_states . "
             GROUP BY currency_code");

        $rf_total_count = 0;

        foreach ($rf_groups as $rf_group) {
            $rf_total_count += (int) $rf_group['orders_count'];
        }

        $rf_amount_line = '';

        if (count($rf_groups) === 1) {
            $rf_amount_line = pg_format_number(((float) $rf_groups[0]['orders_total']) / 100, 2)
                . ' ' . h($rf_groups[0]['currency_code']);
        }

        $rf_items = db_items(
            "SELECT
                orders.id,
                orders.order_number,
                orders.billing_first_name,
                orders.billing_last_name,
                orders.total,
                orders.currency_code,
                orders.cancelled_at,
                orders.refund_status
             FROM orders
             WHERE orders.refund_status IN " . $rf_states . "
             ORDER BY orders.cancelled_at ASC, orders.id ASC
             LIMIT 5");

        $rf_rows = '';

        foreach ($rf_items as $rf_item) {

            $rf_waited = time() - (int) $rf_item['cancelled_at'];

            // Two days is where a refund stops being slow and
            // starts being the thing a customer opens a dispute
            // about.
            $rf_wait_class = ($rf_waited >= 172800) ? 'text-danger' : 'text-warning';

            $rf_who = trim((string) $rf_item['billing_first_name'] . ' ' . (string) $rf_item['billing_last_name']);

            if ($rf_who === '') {
                $rf_who = lang('Unknown');
            }

            $rf_number = ((string) $rf_item['order_number'] !== '')
                ? (string) $rf_item['order_number']
                : (string) (int) $rf_item['id'];

            $rf_rows .= '
            <a class="d-block px-3 py-2 border-top text-decoration-none text-body" href="view_order.php?id=' . (int) $rf_item['id'] . '&amp;send_to=' . h(PATH . SOFTWARE_DIRECTORY . '/view_orders.php') . '">
                <div class="d-flex align-items-center" style="gap:6px">
                    <span class="text-truncate fw-semibold" style="font-size:12px">#' . h($rf_number) . ' · ' . h($rf_who) . '</span>
                    <span class="' . $rf_wait_class . ' ms-auto flex-shrink-0" style="font-size:10px">' . get_relative_time(array('timestamp' => (int) $rf_item['cancelled_at'])) . '</span>
                </div>
                <div class="text-muted" style="font-size:11px">'
                    . pg_format_number(((float) $rf_item['total']) / 100, 2) . ' ' . h($rf_item['currency_code'])
                . '</div>
            </a>';
        }

        if ($rf_rows === '') {
            $rf_rows = '
            <div class="text-center py-4">
                <i class="bi bi-check2-circle d-block mb-2 text-success" style="font-size:22px;opacity:.8"></i>
                <p class="text-muted mb-0" style="font-size:12px">' . lang('No refunds are waiting.') . '</p>
            </div>';
        }

        $rf_more = '';

        if ($rf_total_count > count($rf_items)) {
            $rf_more = '
            <div class="px-3 py-1 text-center border-top">
                <span class="text-muted" style="font-size:10px">' . lang(array(
                    'string' => '{var:1} more waiting',
                    'vars'   => pg_format_number($rf_total_count - count($rf_items), 0),
                )) . '</span>
            </div>';
        }

        $output_data = '
        <div class="card-body p-0 d-flex flex-column" style="overflow-x:hidden;overflow-y:auto">
            <div class="d-flex align-items-center justify-content-between px-3 pt-2 pb-1">
                <div class="overflow-hidden">
                    <span class="text-muted d-block" style="font-size:11px">' . lang('Refund Pending') . '</span>'
                    . ($rf_amount_line !== ''
                        ? '<span class="text-muted text-truncate d-block" style="font-size:10px">' . $rf_amount_line . '</span>'
                        : '')
                . '</div>
                <span class="fw-semibold text-' . ($rf_total_count > 0 ? 'danger' : 'success') . ' flex-shrink-0" style="font-size:15px">' . pg_format_number($rf_total_count, 0) . '</span>
            </div>
            ' . $rf_rows . '
            ' . $rf_more . '
        </div>
        <div class="card-footer border-0 bg-reset py-1 text-center">
            <a href="view_orders.php?status=refund_pending" class="text-decoration-none" style="font-size:11px">'
            . lang('Orders') . ' <i class="bi bi-arrow-right-short"></i></a>
        </div>';

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
