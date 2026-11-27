<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What the accounting sync tells the panel (table accounting_sync): the
 * "Events" card of the service's Connections page (the daily counts of the
 * last week, the last 50 sends, Try again on a failed one), the one line on
 * View Order and on the ERP invoice, and the daily operations alert when
 * sends fail and none pass. Reads only, but for Try again, which puts a
 * failed row back in the queue; no service is called here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/accounting_sync.php');

// Rows the card's "Last events" shows, and the days its counts cover.
define('PG_ACCOUNTING_SYNC_LOG_ROWS', 50);
define('PG_ACCOUNTING_SYNC_LOG_DAYS', 7);

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

/**
 * A service's name as the panel writes it. Pure.
 *
 * @param string $service
 * @return string
 */
function pg_accounting_sync_service_name($service)
{
    $names = array('xero' => 'Xero', 'quickbooks' => 'QuickBooks');

    return isset($names[$service]) ? $names[$service] : (string) $service;
}

/**
 * A row's state: its words and its pill's tone. Waiting is neutral (gray):
 * nobody has to act; sent is teal, failed rose. Pure but for lang().
 *
 * @param string $status   waiting, sent, failed
 * @param int    $attempts
 * @return array state (due, retrying, sent, failed), label, tone
 */
function pg_accounting_sync_log_state($status, $attempts)
{
    if ($status === 'sent') {
        return array('state' => 'sent', 'label' => lang('Sent'), 'tone' => 'teal');
    }

    if ($status === 'failed') {
        return array('state' => 'failed', 'label' => lang('Failed'), 'tone' => 'rose');
    }

    return ((int) $attempts > 0)
        ? array('state' => 'retrying', 'label' => lang('Tried again later'), 'tone' => 'gray')
        : array('state' => 'due', 'label' => lang('Waiting to be sent'), 'tone' => 'gray');
}

/**
 * A row as the card shows it: what it is, the record it came from, its state
 * and the service's reason for a failure or a retry. Pure but for lang().
 *
 * @param array $row an accounting_sync row, with order_id and number when
 *                   pg_accounting_sync_log_read() found them
 * @return array id, kind_label, icon, link, link_label, state, label, tone,
 *               reason, at, may_retry
 */
function pg_accounting_sync_log_item(array $row)
{
    $kinds = array(
        'sale' => array(lang('Sale'), 'bi-receipt'),
        'payment' => array(lang('Payment'), 'bi-cash-coin'),
        'refund' => array(lang('Refund'), 'bi-arrow-counterclockwise'),
    );
    $kind = (string) ($row['kind'] ?? '');
    $status = (string) ($row['status'] ?? '');
    $attempts = (int) ($row['attempts'] ?? 0);
    $state = pg_accounting_sync_log_state($status, $attempts);
    $number = trim((string) ($row['number'] ?? ''));
    $order_id = (int) ($row['order_id'] ?? 0);
    $link = '';
    $link_label = '';

    if (($row['local_table'] ?? '') === 'erp_invoices') {
        $link = 'edit_erp_invoice.php?id=' . (int) $row['local_id'];
        $link_label = ($number !== '') ? lang(array('string' => 'Invoice {var:1}', 'vars' => $number)) : lang('Invoice');
    } elseif ($order_id > 0) {
        $link = 'view_order.php?id=' . $order_id;
        $link_label = ($number !== '') ? lang(array('string' => 'Order {var:1}', 'vars' => $number)) : lang('Order');
    }

    $reason = (($status === 'failed') || (($status === 'waiting') && ($attempts > 0)))
        ? trim((string) ($row['last_error'] ?? ''))
        : '';
    $sent_at = (int) ($row['sent_at'] ?? 0);

    return array(
        'id' => (int) ($row['id'] ?? 0),
        'kind_label' => isset($kinds[$kind]) ? $kinds[$kind][0] : $kind,
        'icon' => isset($kinds[$kind]) ? $kinds[$kind][1] : 'bi-send',
        'link' => $link,
        'link_label' => $link_label,
        'state' => $state['state'],
        'label' => $state['label'],
        'tone' => $state['tone'],
        'reason' => $reason,
        'at' => (($status === 'sent') && ($sent_at > 0)) ? $sent_at : (int) ($row['updated_at'] ?? 0),
        'may_retry' => ($status === 'failed'),
    );
}

/**
 * The daily counts from grouped rows, newest day first, every day of the
 * window present. Pure.
 *
 * @param array $groups rows of day (day number in the site zone), status
 *                      (sent, failed), n
 * @param int   $last   the newest day number of the window
 * @param int   $days
 * @return array list of day (Y-m-d), sent, failed
 */
function pg_accounting_sync_daily_counts(array $groups, $last, $days)
{
    $last = (int) $last;
    $counts = array();

    for ($day = $last; $day > $last - max(1, (int) $days); $day--) {
        $counts[$day] = array('day' => gmdate('Y-m-d', $day * 86400), 'sent' => 0, 'failed' => 0);
    }

    foreach ($groups as $group) {
        $day = (int) ($group['day'] ?? 0);
        $status = (string) ($group['status'] ?? '');

        if (isset($counts[$day]) && in_array($status, array('sent', 'failed'), true)) {
            $counts[$day][$status] += max(0, (int) ($group['n'] ?? 0));
        }
    }

    return array_values($counts);
}

/**
 * Whether a day's counts are sends that all failed. Pure.
 *
 * @param array $count one day of pg_accounting_sync_daily_counts()
 * @return bool
 */
function pg_accounting_sync_failed_all($count)
{
    return is_array($count) && ((int) ($count['failed'] ?? 0) > 0) && ((int) ($count['sent'] ?? 0) === 0);
}

/**
 * The one line of a record on View Order or the ERP invoice: "Sent to Xero",
 * "Waiting to be sent" or "Not sent:" with the reason. Pure but for lang().
 *
 * @param array|null $row     the record's sale row, null when it has none
 * @param string     $service
 * @param string     $missing why a record without a row is not sent, '' to
 *                            show nothing for it
 * @return array|null label, tone; null for no line
 */
function pg_accounting_sync_line($row, $service, $missing = '')
{
    if (!is_array($row)) {
        return ($missing === '') ? null
            : array('label' => lang(array('string' => 'Not sent: {var:1}', 'vars' => $missing)), 'tone' => 'rose');
    }

    $status = (string) ($row['status'] ?? '');

    if ($status === 'sent') {
        return array('label' => lang(array('string' => 'Sent to {var:1}', 'vars' => pg_accounting_sync_service_name($service))), 'tone' => 'teal');
    }

    if ($status === 'failed') {
        $reason = trim((string) ($row['last_error'] ?? ''));

        return array('label' => lang(array('string' => 'Not sent: {var:1}', 'vars' => ($reason !== '') ? mb_substr($reason, 0, 200) : lang('No answer.'))), 'tone' => 'rose');
    }

    return array('label' => lang('Waiting to be sent'), 'tone' => 'gray');
}

/**
 * The statement that puts a failed row back in the queue, due at once with
 * its attempts cleared. Values: now twice, id, service, company. Pure.
 *
 * @return string
 */
function pg_accounting_sync_try_again_sql()
{
    return "UPDATE accounting_sync SET status = 'waiting', attempts = 0, next_try_at = ?, last_error = '', updated_at = ?
        WHERE id = ? AND service = ? AND company = ? AND status = 'failed' AND kind IN ('sale', 'payment', 'refund')";
}

/**
 * The alert's sentence. Pure but for lang().
 *
 * @param string $service
 * @param array  $count  the day's counts
 * @param string $reason the last failure's words
 * @return string
 */
function pg_accounting_sync_failed_sentence($service, array $count, $reason)
{
    $sentence = lang(array(
        'string' => 'No send to {var:1} passed on {var:2}: {var:3} failed.',
        'vars' => array(pg_accounting_sync_service_name($service), (string) $count['day'], (int) $count['failed']),
    ));
    $reason = trim((string) $reason);

    return ($reason === '') ? $sentence
        : $sentence . ' ' . lang(array('string' => 'Last reason: {var:1}', 'vars' => mb_substr($reason, 0, 200)));
}

/* ---------------------------------------------------------------------------
   Reading and writing
   --------------------------------------------------------------------------- */

/**
 * The newest queued rows of the company the site books into, with the order
 * or invoice each came from.
 *
 * @param array $target service, company
 * @param int   $limit
 * @return array list of pg_accounting_sync_log_item()
 */
function pg_accounting_sync_log_read(array $target, $limit = PG_ACCOUNTING_SYNC_LOG_ROWS)
{
    $items = array();
    $result = db_q("SELECT s.*,
            CASE s.local_table WHEN 'orders' THEN s.local_id WHEN 'order_refunds' THEN r.order_id ELSE 0 END AS order_id,
            CASE s.local_table WHEN 'erp_invoices' THEN i.full_number ELSE o.order_number END AS number
        FROM accounting_sync s
        LEFT JOIN order_refunds r ON s.local_table = 'order_refunds' AND r.id = s.local_id
        LEFT JOIN orders o ON o.id = IF(s.local_table = 'orders', s.local_id, r.order_id)
        LEFT JOIN erp_invoices i ON s.local_table = 'erp_invoices' AND i.id = s.local_id
        WHERE s.service = ? AND s.company = ? AND s.kind IN ('sale', 'payment', 'refund')
        ORDER BY s.updated_at DESC, s.id DESC LIMIT " . max(1, (int) $limit),
        array((string) $target['service'], (string) $target['company']));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $items[] = pg_accounting_sync_log_item($row);
    }

    return $items;
}

/**
 * The daily counts of the last days: a sent row on the day it was sent, a
 * failed one on the day it failed.
 *
 * @param array $target service, company
 * @param int   $now
 * @param int   $days
 * @return array pg_accounting_sync_daily_counts()
 */
function pg_accounting_sync_counts_read(array $target, $now, $days)
{
    $offset = (int) date('Z', (int) $now);
    $last = (int) floor(((int) $now + $offset) / 86400);
    $since = (($last - max(1, (int) $days) + 1) * 86400) - $offset;
    $groups = array();

    // The offset is a whole number written into the expression.
    $result = db_q("SELECT status, FLOOR((IF(status = 'sent', sent_at, updated_at) + " . $offset . ") / 86400) AS day, COUNT(*) AS n
        FROM accounting_sync
        WHERE service = ? AND company = ? AND status IN ('sent', 'failed') AND kind IN ('sale', 'payment', 'refund')
            AND IF(status = 'sent', sent_at, updated_at) >= ?
        GROUP BY status, day",
        array((string) $target['service'], (string) $target['company'], $since));

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $groups[] = $row;
    }

    return pg_accounting_sync_daily_counts($groups, $last, $days);
}

/**
 * Try again on a failed row of the company the site books into.
 *
 * @param int $id
 * @return bool whether a row was put back
 */
function pg_accounting_sync_try_again($id)
{
    $target = pg_accounting_sync_target();

    if ($target === null) {
        return false;
    }

    $now = time();
    $result = db_q(pg_accounting_sync_try_again_sql(), array($now, $now, (int) $id, (string) $target['service'], (string) $target['company']));

    return $result && (mysqli_affected_rows(db::$con) > 0);
}

/**
 * The line of a local record, as HTML for a pg-kv value; '' while the site
 * books into no service or the record has no line.
 *
 * @param string $local_table orders or erp_invoices
 * @param int    $local_id
 * @return array label (the service's name), html; or an empty array
 */
function pg_accounting_sync_record_line($local_table, $local_id)
{
    $target = pg_accounting_sync_target();

    if (($target === null) || ((int) $local_id <= 0)) {
        return array();
    }

    $missing = '';
    $kind = 'sale';

    if ($local_table === 'orders') {
        // With the ERP the order's invoice is sent, and an order without one
        // is not.
        if (pg_accounting_sync_erp_on()) {
            $invoice_id = (int) db_value("SELECT erp_invoice_id FROM orders WHERE id = '" . (int) $local_id . "'");
            $local_table = 'erp_invoices';
            $local_id = $invoice_id;
            $missing = ($invoice_id > 0) ? '' : lang('the order has no ERP invoice.');
        }
    } elseif ($local_table === 'erp_invoices') {
        $type = (string) db_value("SELECT doc_type FROM erp_invoices WHERE id = '" . (int) $local_id . "'");
        $kind = ($type === 'return') ? 'refund' : 'sale';
    }

    $row = ((int) $local_id > 0) ? db_item("SELECT status, last_error FROM accounting_sync
        WHERE service = '" . e($target['service']) . "' AND company = '" . e($target['company']) . "'
            AND kind = '" . e($kind) . "' AND local_table = '" . e($local_table) . "' AND local_id = '" . (int) $local_id . "'") : null;
    $line = pg_accounting_sync_line(is_array($row) ? $row : null, $target['service'], $missing);

    if ($line === null) {
        return array();
    }

    return array(
        'label' => pg_accounting_sync_service_name($target['service']),
        'html' => '<span class="pg-pill pg-tone-' . h($line['tone']) . '">' . h($line['label']) . '</span>',
    );
}

/**
 * The operations alerts' source: once a day, for yesterday, the service the
 * site books into when its sends failed and none passed. The day is claimed
 * in the alert state file first, so the read runs once a day.
 *
 * @param int $now
 * @return string[] the codes told
 */
function pg_accounting_sync_check_failed($now)
{
    $target = pg_accounting_sync_target();

    if ($target === null) {
        return array();
    }

    $now = (int) $now;
    $day = (int) floor(($now + (int) date('Z', $now)) / 86400) - 1;
    $claimed = true;
    $state = pg_ops_alert_state(function ($state) use ($day, &$claimed) {

        if (isset($state['accounting_sync']) && ((int) $state['accounting_sync'] >= $day)) {
            $claimed = false;
            return $state;
        }

        $state['accounting_sync'] = $day;

        return $state;
    });

    if (($state !== null) && !$claimed) {
        return array();
    }

    $yesterday = pg_accounting_sync_counts_read($target, $now, 2)[1];

    if (!pg_accounting_sync_failed_all($yesterday)) {
        return array();
    }

    $reason = (string) db_value("SELECT last_error FROM accounting_sync
        WHERE service = '" . e($target['service']) . "' AND company = '" . e($target['company']) . "' AND status = 'failed'
        ORDER BY updated_at DESC, id DESC LIMIT 1");

    pg_ops_alert('accounting_sync_failed', pg_accounting_sync_failed_sentence($target['service'], $yesterday, $reason), $target['service']);

    return array('accounting_sync_failed');
}

/* ---------------------------------------------------------------------------
   The service's page
   --------------------------------------------------------------------------- */

/**
 * The "Events" card of the service's Connections page: a note while the
 * connection holds the queue, the daily counts of the last week and the last
 * 50 sends, Try again on a failed one.
 *
 * @param bool   $may_manage whether Try again is offered
 * @param string $service    the page's service; '' for whichever the site
 *                           books into
 * @return string HTML, '' while the site books into no service, or into
 *                another one than the page's
 */
function pg_accounting_sync_log_card($may_manage, $service = '')
{
    $target = pg_accounting_sync_target();

    if (($target === null) || (($service !== '') && ($target['service'] !== $service))) {
        return '';
    }

    $name = pg_accounting_sync_service_name($target['service']);
    $held = pg_accounting_sync_held()
        ? '
			<div class="pg-card-inner mb-5">
				<div class="pg-card-inner-row connections-row">
					<span class="connections-row-main">
						<span class="connections-row-name">' . h(lang('Sending is held')) . '</span>
						<span class="pg-text-meta d-block">' . h(lang(array('string' => 'Nothing is sent until {var:1} is connected again; the sends wait.', 'vars' => $name))) . '</span>
					</span>
					<span class="connections-row-side"><span class="pg-pill pg-tone-amber">' . h(lang('Must be connected again')) . '</span></span>
				</div>
			</div>'
        : '';
    $items = pg_accounting_sync_log_read($target);

    if ($items === array()) {
        $body = '
			<div class="pg-card-inner"><div class="ws-empty py-5"><i class="bi bi-send" aria-hidden="true"></i>' . h(lang('Nothing has been sent yet.')) . '</div></div>';
    } else {
        $counts = '';

        foreach (pg_accounting_sync_counts_read($target, time(), PG_ACCOUNTING_SYNC_LOG_DAYS) as $count) {
            $counts .= '
				<div class="pg-card-inner-row connections-row">
					<span class="connections-row-main">
						<span class="connections-row-name">' . h(get_absolute_time(array('timestamp' => strtotime($count['day'] . ' 12:00:00'), 'type' => 'date', 'format' => 'plain_text', 'size' => 'short'))) . '</span>
						<span class="pg-text-meta d-block">' . h(lang(array('string' => '{var:1} sent, {var:2} failed', 'vars' => array((int) $count['sent'], (int) $count['failed'])))) . '</span>
					</span>
					<span class="connections-row-side">' . (pg_accounting_sync_failed_all($count) ? '<span class="pg-pill pg-tone-rose">' . h(lang('All failed')) . '</span>' : '') . '</span>
				</div>';
        }

        $rows = '';

        foreach ($items as $item) {
            $retry = ($may_manage && $item['may_retry']) ? '
							<form method="post" action="connections.php" class="d-inline">
								' . get_token_field() . '
								<input type="hidden" name="connections_action" value="accounting_sync_again">
								<input type="hidden" name="connections_return" value="' . h($target['service']) . '">
								<input type="hidden" name="sync_row" value="' . (int) $item['id'] . '">
								<button type="submit" class="pg-button pg-button-sm pg-button-quiet">' . h(lang('Try again')) . '</button>
							</form>' : '';

            $rows .= '
				<div class="pg-list-row is-read">
					<span class="pg-icon-tile pg-tone-gray" aria-hidden="true"><span class="bi ' . h($item['icon']) . '"></span></span>
					<div class="pg-list-row-main">
						<div class="pg-list-row-head">
							<span class="pg-list-row-title">' . h($item['kind_label']) . '</span>
							<span class="pg-list-row-time">' . h(get_relative_time(array('timestamp' => $item['at'], 'format' => 'plain_text'))) . '</span>
						</div>' . (($item['reason'] !== '') ? '
						<div class="pg-list-row-text text-break">' . h(mb_substr($item['reason'], 0, 300)) . '</div>' : '') . '
						<div class="pg-list-row-actions"><span class="pg-pill pg-tone-' . h($item['tone']) . '">' . h($item['label']) . '</span>'
                . (($item['link'] !== '') ? '
							<span class="pg-list-row-place"><a href="' . h($item['link']) . '">' . h($item['link_label']) . '</a></span>' : '') . $retry . '</div>
					</div>
					<div class="pg-list-row-side"></div>
				</div>';
        }

        $body = '
			<h3 class="pg-field-label mb-2">' . h(lang('Daily counts')) . '</h3>
			<div class="pg-card-inner mb-5">' . $counts . '
			</div>
			<h3 class="pg-field-label mb-2">' . h(lang('Last events')) . '</h3>
			<div class="pg-card-inner">' . $rows . '
			</div>';
    }

    return '
		<section class="pg-card p-5 mb-6" id="connections-events" aria-labelledby="connections-page-events">
			<h2 class="pg-title-section mb-1" id="connections-page-events">' . h(lang('Events')) . '</h2>
			<p class="pg-card-sub mb-4">' . h(lang(array('string' => 'The sales, payments and refunds sent to {var:1} in the last week, and the last 50 sends.', 'vars' => $name))) . '</p>' . $held . $body . '
		</section>';
}
