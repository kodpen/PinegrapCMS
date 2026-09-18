<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP: overdue receivable reminders. One digest per period, over the panel
 * bell, e-mail and device notifications, of the sales invoices that have
 * newly passed the reminder threshold.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Loaded from includes/erp/bootstrap.php after aging.php, never on its own.
//
// Nothing here delivers anything itself. The bell row goes through
// create_notification(), the device banner through the push queue that row
// feeds, the mail through email(): the three channels the panel already has,
// fed the same summary. What this file owns is the decision - which documents
// count, whether this period has been announced already - and the wording.
//
// A document is announced once. erp_invoices.overdue_notified_at is stamped
// when it first appears in a digest, and a later digest lists it only in the
// "still open" total. A reminder that repeated the same list every morning
// would be read once and ignored from then on; the dashboard card already
// shows the standing total for anyone who wants it.
if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Whether reminders are switched on at all: a threshold, and at least one
 * channel to send through.
 *
 * @return bool
 */
function erp_overdue_notify_enabled()
{
    if (!defined('ERP_ENABLED') || !ERP_ENABLED) {
        return false;
    }

    if (!defined('ERP_OVERDUE_NOTIFY_DAYS') || ((int) ERP_OVERDUE_NOTIFY_DAYS <= 0)) {
        return false;
    }

    return ((defined('ERP_OVERDUE_NOTIFY_PANEL') && ERP_OVERDUE_NOTIFY_PANEL)
        || (defined('ERP_OVERDUE_NOTIFY_EMAIL') && ERP_OVERDUE_NOTIFY_EMAIL)
        || (defined('ERP_OVERDUE_NOTIFY_PUSH') && ERP_OVERDUE_NOTIFY_PUSH));
}

/**
 * Whether the columns this feature writes have arrived. Files land before the
 * schema does; until the upgrade runs, the check quietly does nothing.
 *
 * @return bool
 */
function erp_overdue_notify_schema_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = ((bool) db_item("SHOW COLUMNS FROM erp_invoices LIKE 'overdue_notified_at'"))
            && ((bool) db_item("SHOW COLUMNS FROM config LIKE 'erp_overdue_notify_sent_at'"));
    }

    return $ready;
}

/**
 * The addresses the digest goes to.
 *
 * The configured list first; empty, the store's order e-mail address, and
 * failing that the site's own address, so a store that sets a threshold and
 * nothing else still gets the mail somewhere somebody reads.
 *
 * @return array  Valid, unique addresses; may be empty.
 */
function erp_overdue_notify_recipients()
{
    $configured = defined('ERP_OVERDUE_NOTIFY_RECIPIENTS') ? (string) ERP_OVERDUE_NOTIFY_RECIPIENTS : '';
    $candidates = array_map('trim', explode(',', $configured));

    if (trim($configured) === '') {
        $candidates = array(
            defined('ECOMMERCE_EMAIL_ADDRESS') ? (string) ECOMMERCE_EMAIL_ADDRESS : '',
            defined('EMAIL_ADDRESS') ? (string) EMAIL_ADDRESS : '',
        );
    }

    $recipients = array();

    foreach ($candidates as $address) {
        if (($address !== '') && filter_var($address, FILTER_VALIDATE_EMAIL) && !in_array($address, $recipients, true)) {
            $recipients[] = $address;
        }
    }

    // With no explicit list the fallbacks are alternatives, not a pair.
    if ((trim($configured) === '') && (count($recipients) > 1)) {
        $recipients = array($recipients[0]);
    }

    return $recipients;
}

/**
 * The most recent scheduled instant at or before $now.
 *
 * Daily, that is today's reminder hour if it has passed and yesterday's if
 * not; weekly, the same on Mondays. A digest sent at or after that instant
 * means this period is done. Working from the instant rather than from "was
 * one sent today" lets a check that comes late - the first panel visit on a
 * Tuesday, a job that runs at night - still send the period's digest instead
 * of skipping the week.
 *
 * @param int  $now
 * @param bool $with_hour  False for the scheduled job, which runs when the
 *                         server's schedule says and should not wait for the
 *                         hour meant for the panel.
 * @return int
 */
function erp_overdue_notify_slot($now, $with_hour = true)
{
    $hour = ($with_hour && defined('ERP_OVERDUE_NOTIFY_HOUR')) ? min(23, max(0, (int) ERP_OVERDUE_NOTIFY_HOUR)) : 0;
    $weekly = (defined('ERP_OVERDUE_NOTIFY_FREQUENCY') && (ERP_OVERDUE_NOTIFY_FREQUENCY === 'weekly'));

    $slot = mktime($hour, 0, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));

    if ($weekly) {
        // Back to this week's Monday, ISO numbering.
        $slot -= ((int) date('N', $now) - 1) * 86400;
    }

    if ($slot > $now) {
        $slot -= ($weekly ? 7 : 1) * 86400;
    }

    return $slot;
}

/**
 * The open sales invoices that have passed their reminder threshold, as of
 * today, split into the ones never announced and the ones already listed in
 * an earlier digest.
 *
 * The threshold is the account's own when it has one, the store's otherwise -
 * a customer who always pays at sixty days is not late at thirty.
 *
 * @return array  ['new' => rows, 'known' => rows]; rows as erp_aging_invoices()
 *                returns them, plus 'threshold'.
 */
function erp_overdue_notify_candidates()
{
    $store_days = (int) ERP_OVERDUE_NOTIFY_DAYS;
    $rows = erp_aging_invoices('sales', date('Y-m-d'), array('overdue' => true));

    if (!$rows) {
        return array('new' => array(), 'known' => array());
    }

    $ids = array();
    $account_ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
        if ((int) $row['account_id'] > 0) {
            $account_ids[] = (int) $row['account_id'];
        }
    }

    $account_days = array();

    if ($account_ids && db_item("SHOW COLUMNS FROM erp_accounts LIKE 'overdue_notify_days'")) {
        foreach ((array) db_items("SELECT id, overdue_notify_days FROM erp_accounts WHERE id IN (" . implode(',', array_unique($account_ids)) . ")") as $account) {
            $account_days[(int) $account['id']] = (int) $account['overdue_notify_days'];
        }
    }

    $notified = array();

    foreach ((array) db_items("SELECT id, overdue_notified_at FROM erp_invoices WHERE id IN (" . implode(',', $ids) . ")") as $invoice) {
        $notified[(int) $invoice['id']] = (int) $invoice['overdue_notified_at'];
    }

    $result = array('new' => array(), 'known' => array());

    foreach ($rows as $row) {
        $threshold = isset($account_days[(int) $row['account_id']]) && ($account_days[(int) $row['account_id']] > 0)
            ? $account_days[(int) $row['account_id']]
            : $store_days;

        if ((int) $row['days'] < $threshold) {
            continue;
        }

        $row['threshold'] = $threshold;
        $key = (isset($notified[(int) $row['id']]) && ($notified[(int) $row['id']] > 0)) ? 'known' : 'new';
        $result[$key][] = $row;
    }

    return $result;
}

/**
 * Sum of the open amounts in the base currency.
 *
 * @param array $rows
 * @return int  kurus
 */
function erp_overdue_notify_total($rows)
{
    $total = 0;

    foreach ($rows as $row) {
        $total += (int) $row['open_base'];
    }

    return $total;
}

/**
 * The absolute address of the overdue list in the panel, for the mail and the
 * device banner. The bell uses the relative form.
 *
 * @return string
 */
function erp_overdue_notify_url()
{
    return URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/erp_invoices.php?filter=overdue&direction=sales';
}

/**
 * The HTML body of the digest.
 *
 * Inline styles only: mail clients drop stylesheets. Every value from a row is
 * escaped here. email() adds the <base> tag and the text alternative itself.
 *
 * @param array $new    Rows announced for the first time
 * @param array $known  Rows already announced in an earlier digest
 * @return string
 */
function erp_overdue_notify_digest_html($new, $known)
{
    $cell = 'padding: 6px 10px; border-bottom: 1px solid #dddddd; font-size: 14px; vertical-align: top;';
    $head = $cell . ' text-align: left; background: #f3f3f3; font-weight: bold;';
    $right = $cell . ' text-align: right; white-space: nowrap;';

    $rows = '';

    foreach ($new as $row) {
        $rows .= '
            <tr>
                <td style="' . $cell . '">' . h($row['title']) . '</td>
                <td style="' . $cell . '"><a href="' . h(URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $row['id']) . '">' . h($row['full_number']) . '</a></td>
                <td style="' . $cell . '">' . h(prepare_form_data_for_output($row['effective_due'], 'date')) . '</td>
                <td style="' . $right . '">' . (int) $row['days'] . '</td>
                <td style="' . $right . '">' . h(erp_money_out_currency((int) $row['open'], $row['currency'], false)) . '</td>
            </tr>';
    }

    $known_line = '';

    if ($known) {
        $known_line = '
        <p style="font-size: 14px; color: #555555;">' . h(lang(array(
            'string' => 'Still open from earlier reminders: {var:1} document(s), {var:2}.',
            'vars'   => array(count($known), erp_money_out(erp_overdue_notify_total($known), false))))) . '</p>';
    }

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . h(lang('Overdue receivables')) . '</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222222; background: #ffffff; margin: 0; padding: 20px;">
    <div style="max-width: 760px; margin: 0 auto;">
        <h2 style="font-size: 18px; margin: 0 0 12px 0;">' . h(ORGANIZATION_NAME) . ' &middot; ' . h(lang('Overdue receivables')) . '</h2>
        <p style="font-size: 14px;">' . h(lang(array(
            'string' => '{var:1} receivable(s) passed the reminder threshold, {var:2} in total.',
            'vars'   => array(count($new), erp_money_out(erp_overdue_notify_total($new), false))))) . '</p>
        <table cellspacing="0" cellpadding="0" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th style="' . $head . '">' . h(lang('Account')) . '</th>
                    <th style="' . $head . '">' . h(lang('Document')) . '</th>
                    <th style="' . $head . '">' . h(lang('Due Date')) . '</th>
                    <th style="' . $head . ' text-align: right;">' . h(lang('Days overdue')) . '</th>
                    <th style="' . $head . ' text-align: right;">' . h(lang('Open amount')) . '</th>
                </tr>
            </thead>
            <tbody>' . $rows . '
            </tbody>
        </table>' . $known_line . '
        <p style="font-size: 14px; margin-top: 18px;"><a href="' . h(erp_overdue_notify_url()) . '">' . h(lang('Open the overdue list')) . '</a></p>
        <p style="font-size: 12px; color: #888888;">' . h(lang('This reminder is sent by the ERP module. The threshold and the recipients are set on the ERP settings card.')) . '</p>
    </div>
</body>
</html>';
}

/**
 * Runs the reminder if this period's digest is still owed, and sends it when
 * there is something new to say.
 *
 * Called from the scheduled job (daily, whenever the dispatcher gets to it)
 * and from the ERP dashboard on every load, throttled to one attempt an hour
 * so a site without a cron entry still gets its digest on the first visit
 * after the reminder hour. The attempt is recorded before the work, the way
 * the other panel piggybacks do, so a failing mail server is retried a few
 * times a day rather than on every page view.
 *
 * @param bool $force     Skip the period and throttle checks (the CLI test
 *                        and a manual run); the once-per-document rule holds.
 * @param bool $from_job  The scheduled job: the period counts from midnight,
 *                        not from the reminder hour meant for the panel.
 * @return array  ['ran' => bool, 'reason' => string, 'new' => rows, 'known' => rows,
 *                 'notification_id' => int, 'email' => bool|null, 'push' => bool,
 *                 'recipients' => array]
 */
function erp_overdue_check($force = false, $from_job = false)
{
    $result = array(
        'ran'             => false,
        'reason'          => '',
        'new'             => array(),
        'known'           => array(),
        'notification_id' => 0,
        'email'           => null,
        'push'            => false,
        'recipients'      => array(),
    );

    if (!erp_overdue_notify_enabled()) {
        $result['reason'] = 'off';
        return $result;
    }

    if (!erp_overdue_notify_schema_ready()) {
        $result['reason'] = 'schema';
        return $result;
    }

    $now = time();

    if (!$force) {
        $config = db_item("SELECT erp_overdue_notify_checked, erp_overdue_notify_sent_at FROM config");
        $checked = $config ? (int) $config['erp_overdue_notify_checked'] : 0;
        $sent_at = $config ? (int) $config['erp_overdue_notify_sent_at'] : 0;

        if ($sent_at >= erp_overdue_notify_slot($now, !$from_job)) {
            $result['reason'] = 'sent';
            return $result;
        }

        if (!$from_job && ($checked > ($now - 3600))) {
            $result['reason'] = 'throttled';
            return $result;
        }
    }

    db("UPDATE config SET erp_overdue_notify_checked = '" . $now . "'");

    $candidates = erp_overdue_notify_candidates();
    $result['new'] = $candidates['new'];
    $result['known'] = $candidates['known'];

    if (!$candidates['new']) {
        $result['reason'] = 'nothing_new';
        return $result;
    }

    $result['ran'] = true;
    $count = count($candidates['new']);
    $total = erp_overdue_notify_total($candidates['new']);

    if (ERP_OVERDUE_NOTIFY_PANEL || ERP_OVERDUE_NOTIFY_PUSH) {

        include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

        // One digest in the bell at a time. A previous one nobody has opened
        // yet is replaced rather than stacked; one that was read stays as
        // history.
        if (pg_notification_reads_available()) {
            $stale = db_values("SELECT n.id FROM notifications n
                LEFT JOIN notification_reads r ON r.notification_id = n.id
                WHERE n.action = 'erp_overdue' AND r.notification_id IS NULL");
        } else {
            $stale = db_values("SELECT id FROM notifications WHERE action = 'erp_overdue' AND readed = 0");
        }

        foreach ((array) $stale as $stale_id) {
            pg_notification_delete($stale_id);
        }

        $notification_id = create_notification(array(
            'action'      => 'erp_overdue',
            'type'        => 'warning',
            'title'       => (string) $count,
            'order_total' => erp_money_out($total, false),
            'user'        => 'system',
        ));

        $result['notification_id'] = (int) $notification_id;

        // The device banner is the same row, queued for everybody the bell
        // would show it to; with no subscribed devices this writes nothing.
        if (ERP_OVERDUE_NOTIFY_PUSH && ($notification_id > 0)) {

            include_once(PG_FUNCTIONS_DIR . '/includes/push.php');

            pg_push_enqueue_notification(array(
                'id'     => (int) $notification_id,
                'action' => 'erp_overdue',
            ));

            $result['push'] = true;
        }

        // The panel switch off and the push switch on: the row exists only to
        // carry the banner, so the bell should not show it. Reading it for
        // everybody who could see it is what hides it there.
        if (!ERP_OVERDUE_NOTIFY_PANEL && ($notification_id > 0) && pg_notification_reads_available()) {
            db("INSERT IGNORE INTO notification_reads (notification_id, user_id, timestamp)
                SELECT '" . (int) $notification_id . "', user_id, '" . $now . "' FROM user");
        }
    }

    if (ERP_OVERDUE_NOTIFY_EMAIL) {

        $recipients = erp_overdue_notify_recipients();
        $result['recipients'] = $recipients;

        if ($recipients) {
            $result['email'] = (bool) email(array(
                'to'                 => $recipients,
                'from_name'          => ORGANIZATION_NAME,
                'from_email_address' => EMAIL_ADDRESS,
                'subject'            => lang(array('string' => '{var:1}: {var:2} overdue receivable(s)', 'vars' => array(ORGANIZATION_NAME, $count))),
                'format'             => 'html',
                'body'               => erp_overdue_notify_digest_html($candidates['new'], $candidates['known']),
                'type'               => 'system',
            ));
        } else {
            $result['email'] = false;
        }
    }

    $ids = array();

    foreach ($candidates['new'] as $row) {
        $ids[] = (int) $row['id'];
    }

    db("UPDATE erp_invoices SET overdue_notified_at = '" . $now . "' WHERE id IN (" . implode(',', $ids) . ")");
    db("UPDATE config SET erp_overdue_notify_sent_at = '" . $now . "'");

    return $result;
}
