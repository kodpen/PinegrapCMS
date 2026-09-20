<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP: overdue receivable reminders. One digest per period, over the panel
 * bell, e-mail and device notifications, of the sales invoices that have
 * newly passed the reminder threshold - and, a month later, of the ones still
 * open. Optionally the same reminder goes to the customer.
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
// A document is announced twice at most. erp_invoices.overdue_notified_at is
// stamped when it first appears in a digest; overdue_second_notified_at when
// it is still open a month past the threshold and is listed a second and last
// time. Between and after those two it is only counted in the "still open"
// total. A reminder that repeated the same list every morning would be read
// once and ignored from then on; the dashboard card already shows the
// standing total for anyone who wants it.
//
// A snoozed document (overdue_snoozed_until in the future) is left out of the
// digests altogether until the date passes: the operator has spoken to the
// customer and does not need to be told again this week.
if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Whether a table has a column, asked once per request.
 *
 * The WAF's cached probe when it is loaded; the same question put directly
 * otherwise. A page that does not load the firewall still has to know
 * whether the upgrade has run.
 *
 * @param string $table
 * @param string $column
 * @return bool
 */
function erp_overdue_column_exists($table, $column)
{
    static $cache = array();

    $key = $table . '.' . $column;

    if (!isset($cache[$key])) {
        if (function_exists('waf_table_has_column')) {
            $cache[$key] = (bool) waf_table_has_column($table, $column);
        } else {
            $cache[$key] = (bool) db_item("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` WHERE Field = '" . escape($column) . "'");
        }
    }

    return $cache[$key];
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
        || (defined('ERP_OVERDUE_NOTIFY_PUSH') && ERP_OVERDUE_NOTIFY_PUSH)
        || (defined('ERP_OVERDUE_NOTIFY_CUSTOMER') && ERP_OVERDUE_NOTIFY_CUSTOMER));
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
        $ready = (erp_overdue_column_exists('erp_invoices', 'overdue_notified_at'))
            && (erp_overdue_column_exists('config', 'erp_overdue_notify_sent_at'));
    }

    return $ready;
}

/**
 * Whether the follow-up columns (second notice, snooze, customer mail) have
 * arrived. Without them the digest behaves as it did before they existed:
 * one announcement per document, nobody outside the panel is written to.
 *
 * @return bool
 */
function erp_overdue_notify_followups_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = (erp_overdue_column_exists('erp_invoices', 'overdue_snoozed_until'))
            && (erp_overdue_column_exists('erp_accounts', 'overdue_notify_customer'));
    }

    return $ready;
}

/**
 * How many days past the threshold the second and last announcement comes.
 *
 * A fixed month rather than a setting: the threshold is the store's own
 * measure of "late", and one more nudge a month after it is the follow-up
 * every collections routine has. A document still open after that is on the
 * dashboard and in the aging report, where it belongs.
 *
 * @return int
 */
function erp_overdue_second_notice_days()
{
    return 30;
}

/**
 * The longest a reminder may be put off, in days.
 *
 * @return int
 */
function erp_overdue_snooze_max_days()
{
    return 365;
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
 * The address the store writes to customers from, and that their replies
 * come back to: the order address when there is one, the site's otherwise.
 *
 * @return string  '' when neither is a valid address
 */
function erp_overdue_notify_sender()
{
    foreach (array(
        defined('ECOMMERCE_EMAIL_ADDRESS') ? (string) ECOMMERCE_EMAIL_ADDRESS : '',
        defined('EMAIL_ADDRESS') ? (string) EMAIL_ADDRESS : '',
    ) as $address) {
        if (($address !== '') && filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return $address;
        }
    }

    return '';
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
 * The reminder threshold in days for one account: its own when it has one,
 * the store's otherwise - a customer who always pays at sixty days is not
 * late at thirty.
 *
 * @param int $account_days  erp_accounts.overdue_notify_days
 * @return int
 */
function erp_overdue_notify_threshold($account_days)
{
    $account_days = (int) $account_days;

    return ($account_days > 0) ? $account_days : (defined('ERP_OVERDUE_NOTIFY_DAYS') ? (int) ERP_OVERDUE_NOTIFY_DAYS : 0);
}

/**
 * The open sales invoices that have passed their reminder threshold, as of
 * today, sorted by what the next digest should do with them.
 *
 * 'new'     never announced: listed, and stamped when the digest goes out
 * 'second'  announced once and still open a month past the threshold:
 *           listed a second and last time
 * 'known'   announced already, nothing more to say: counted in the total
 * 'snoozed' put off by the operator until a date not yet reached: left out
 *
 * Each row is what erp_aging_invoices() returns, plus 'threshold', the
 * stamps, and the account's customer-mail choice and address.
 *
 * @param int $now
 * @return array  ['new' => rows, 'second' => rows, 'known' => rows, 'snoozed' => rows]
 */
function erp_overdue_notify_candidates($now = 0)
{
    $now = ($now > 0) ? (int) $now : time();
    $followups = erp_overdue_notify_followups_ready();
    $result = array('new' => array(), 'second' => array(), 'known' => array(), 'snoozed' => array());

    $rows = erp_aging_invoices('sales', date('Y-m-d', $now), array('overdue' => true));

    if (!$rows) {
        return $result;
    }

    $ids = array();
    $account_ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
        if ((int) $row['account_id'] > 0) {
            $account_ids[] = (int) $row['account_id'];
        }
    }

    $accounts = array();

    if ($account_ids && erp_overdue_column_exists('erp_accounts', 'overdue_notify_days')) {
        $account_columns = "id, email, overdue_notify_days" . ($followups ? ", overdue_notify_customer" : "");

        foreach ((array) db_items("SELECT " . $account_columns . " FROM erp_accounts WHERE id IN (" . implode(',', array_unique($account_ids)) . ")") as $account) {
            $accounts[(int) $account['id']] = $account;
        }
    }

    $stamps = array();
    $stamp_columns = "id, overdue_notified_at" . ($followups ? ", overdue_second_notified_at, overdue_snoozed_until, customer_notified_at" : "");

    foreach ((array) db_items("SELECT " . $stamp_columns . " FROM erp_invoices WHERE id IN (" . implode(',', $ids) . ")") as $invoice) {
        $stamps[(int) $invoice['id']] = $invoice;
    }

    $second_after = erp_overdue_second_notice_days();

    foreach ($rows as $row) {
        $account = isset($accounts[(int) $row['account_id']]) ? $accounts[(int) $row['account_id']] : array();
        $stamp = isset($stamps[(int) $row['id']]) ? $stamps[(int) $row['id']] : array();

        $threshold = erp_overdue_notify_threshold(isset($account['overdue_notify_days']) ? $account['overdue_notify_days'] : 0);

        if ((int) $row['days'] < $threshold) {
            continue;
        }

        $row['threshold'] = $threshold;
        $row['notified_at'] = (int) (isset($stamp['overdue_notified_at']) ? $stamp['overdue_notified_at'] : 0);
        $row['second_notified_at'] = (int) (isset($stamp['overdue_second_notified_at']) ? $stamp['overdue_second_notified_at'] : 0);
        $row['snoozed_until'] = (int) (isset($stamp['overdue_snoozed_until']) ? $stamp['overdue_snoozed_until'] : 0);
        $row['customer_notified_at'] = (int) (isset($stamp['customer_notified_at']) ? $stamp['customer_notified_at'] : 0);
        $row['account_email'] = trim((string) (isset($account['email']) ? $account['email'] : ''));
        $row['customer_wanted'] = !isset($account['overdue_notify_customer']) || ((int) $account['overdue_notify_customer'] === 1);

        if ($row['snoozed_until'] > $now) {
            $result['snoozed'][] = $row;
        } elseif ($row['notified_at'] === 0) {
            $result['new'][] = $row;
        } elseif ($followups && ($row['second_notified_at'] === 0) && ((int) $row['days'] >= ($threshold + $second_after))) {
            $result['second'][] = $row;
        } else {
            $result['known'][] = $row;
        }
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
 * The sentence a digest opens with.
 *
 * It is the bell row's own description, asked of the display function that
 * renders the bell, so the mail, the banner and the bell say the same thing
 * and the wording lives in one place.
 *
 * @param int    $new     Documents announced for the first time
 * @param int    $second  Documents announced a second time
 * @param string $total   The formatted total of both
 * @return string
 */
function erp_overdue_notify_headline($new, $second, $total)
{
    include_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

    $display = pg_notification_display(array(
        'action'      => 'erp_overdue',
        'title'       => (string) (int) $new,
        'form_id'     => (string) (int) $second,
        'order_total' => (string) $total,
    ));

    // The description is HTML-escaped for the bell; the mail escapes on output.
    return html_entity_decode((string) $display['description'], ENT_QUOTES, 'UTF-8');
}

/**
 * One table of documents for a mail body. Inline styles only: mail clients
 * drop stylesheets. Every value from a row is escaped here.
 *
 * @param array $rows
 * @param bool  $with_account  Show the account column (the panel's digest);
 *                             the customer's own mail leaves it out.
 * @param bool  $with_link     Link the number to the panel screen
 * @return string
 */
function erp_overdue_notify_table_html($rows, $with_account, $with_link)
{
    $cell = 'padding: 6px 10px; border-bottom: 1px solid #dddddd; font-size: 14px; vertical-align: top;';
    $head = $cell . ' text-align: left; background: #f3f3f3; font-weight: bold;';
    $right = $cell . ' text-align: right; white-space: nowrap;';

    $body = '';

    foreach ($rows as $row) {
        $number = h($row['full_number']);

        if ($with_link) {
            $number = '<a href="' . h(URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/edit_erp_invoice.php?id=' . (int) $row['id']) . '">' . $number . '</a>';
        }

        $body .= '
            <tr>' . ($with_account ? '
                <td style="' . $cell . '">' . h($row['title']) . '</td>' : '') . '
                <td style="' . $cell . '">' . $number . '</td>
                <td style="' . $cell . '">' . h(prepare_form_data_for_output($row['issue_date'], 'date')) . '</td>
                <td style="' . $cell . '">' . h(prepare_form_data_for_output($row['effective_due'], 'date')) . '</td>
                <td style="' . $right . '">' . (int) $row['days'] . '</td>
                <td style="' . $right . '">' . h(erp_money_out_currency((int) $row['open'], $row['currency'], false)) . '</td>
            </tr>';
    }

    return '
        <table cellspacing="0" cellpadding="0" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>' . ($with_account ? '
                    <th style="' . $head . '">' . h(lang('Account')) . '</th>' : '') . '
                    <th style="' . $head . '">' . h(lang('Document')) . '</th>
                    <th style="' . $head . '">' . h(lang('Date')) . '</th>
                    <th style="' . $head . '">' . h(lang('Due Date')) . '</th>
                    <th style="' . $head . ' text-align: right;">' . h(lang('Days overdue')) . '</th>
                    <th style="' . $head . ' text-align: right;">' . h(lang('Open amount')) . '</th>
                </tr>
            </thead>
            <tbody>' . $body . '
            </tbody>
        </table>';
}

/**
 * The HTML body of the panel's digest.
 *
 * email() adds the <base> tag and the text alternative itself.
 *
 * @param array $new        Rows announced for the first time
 * @param array $second     Rows announced for the second and last time
 * @param array $known      Rows already announced, still open
 * @param int   $customers  How many customers were written to by this digest
 * @return string
 */
function erp_overdue_notify_digest_html($new, $second, $known, $customers = 0)
{
    $paragraph = 'font-size: 14px;';
    $heading = 'font-size: 15px; margin: 22px 0 8px 0;';

    $total = erp_money_out(erp_overdue_notify_total($new) + erp_overdue_notify_total($second), false);

    $output_new = '';

    if ($new) {
        $output_new = '
        <h3 style="' . $heading . '">' . h(lang(array('string' => 'Passed the reminder threshold: {var:1} document(s)', 'vars' => count($new)))) . '</h3>'
            . erp_overdue_notify_table_html($new, true, true);
    }

    $output_second = '';

    if ($second) {
        $output_second = '
        <h3 style="' . $heading . '">' . h(lang(array('string' => 'Still open a month past the threshold: {var:1} document(s)', 'vars' => count($second)))) . '</h3>
        <p style="' . $paragraph . ' color: #555555; margin: 0 0 8px 0;">' . h(lang('These were announced in an earlier reminder. This is their second and last appearance; from now on they count only in the total below.')) . '</p>'
            . erp_overdue_notify_table_html($second, true, true);
    }

    $output_known = '';

    if ($known) {
        $output_known = '
        <p style="' . $paragraph . ' color: #555555;">' . h(lang(array(
            'string' => 'Still open from earlier reminders: {var:1} document(s), {var:2}.',
            'vars'   => array(count($known), erp_money_out(erp_overdue_notify_total($known), false))))) . '</p>';
    }

    $output_customers = '';

    if ((int) $customers > 0) {
        $output_customers = '
        <p style="' . $paragraph . ' color: #555555;">' . h(lang(array('string' => 'A reminder e-mail went to {var:1} customer(s).', 'vars' => (int) $customers))) . '</p>';
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
        <p style="' . $paragraph . '">' . h(erp_overdue_notify_headline(count($new), count($second), $total)) . '</p>'
        . $output_new . $output_second . $output_known . $output_customers . '
        <p style="' . $paragraph . ' margin-top: 18px;"><a href="' . h(erp_overdue_notify_url()) . '">' . h(lang('Open the overdue list')) . '</a></p>
        <p style="font-size: 12px; color: #888888;">' . h(lang('This reminder is sent by the ERP module. The threshold and the recipients are set on the ERP settings card.')) . '</p>
    </div>
</body>
</html>';
}

/**
 * The HTML body of the reminder a customer receives.
 *
 * Plain and short: who is writing, which documents, what is open, how to
 * reach the store. No link into the panel, which is not theirs to open. The
 * documents are listed in their own currency; a total is given only when they
 * share one, because a sum across currencies is not a figure anybody can pay.
 *
 * @param string $account_title
 * @param array  $rows          The account's documents in this digest
 * @param bool   $has_second    Any of them announced before
 * @return string
 */
function erp_overdue_customer_mail_html($account_title, $rows, $has_second)
{
    $paragraph = 'font-size: 14px; margin: 0 0 12px 0;';

    $currencies = array();
    $open = 0;

    foreach ($rows as $row) {
        $currencies[strtoupper((string) $row['currency'])] = true;
        $open += (int) $row['open'];
    }

    $output_total = '';

    if (count($currencies) === 1) {
        $output_total = '
        <p style="' . $paragraph . ' margin-top: 12px; font-weight: bold;">' . h(lang(array('string' => 'Total outstanding: {var:1}', 'vars' => erp_money_out_currency($open, key($currencies), false)))) . '</p>';
    }

    $output_second = '';

    if ($has_second) {
        $output_second = '
        <p style="' . $paragraph . '">' . h(lang('This is our second reminder for one or more of these documents.')) . '</p>';
    }

    $sender = erp_overdue_notify_sender();

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>' . h(lang('Payment reminder')) . '</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222222; background: #ffffff; margin: 0; padding: 20px;">
    <div style="max-width: 760px; margin: 0 auto;">
        <h2 style="font-size: 18px; margin: 0 0 16px 0;">' . h(ORGANIZATION_NAME) . ' &middot; ' . h(lang('Payment reminder')) . '</h2>
        <p style="' . $paragraph . '">' . h(lang(array('string' => 'Dear {var:1},', 'vars' => $account_title))) . '</p>
        <p style="' . $paragraph . '">' . h(lang('According to our records the following document(s) have passed their due date and are still open. If you have already paid, please disregard this message; the payment may not have reached our records yet.')) . '</p>'
        . erp_overdue_notify_table_html($rows, false, false)
        . $output_total
        . $output_second . '
        <p style="' . $paragraph . ' margin-top: 12px;">' . h(lang('If you have a question about any of these documents, simply reply to this e-mail.')) . '</p>
        <p style="' . $paragraph . '">' . h(ORGANIZATION_NAME) . (($sender !== '') ? '<br>' . h($sender) : '') . '</p>
    </div>
</body>
</html>';
}

/**
 * Writes to the customers whose documents are in this digest.
 *
 * One mail per account, listing that account's documents from both lists.
 * An account without a usable address, or one that asked not to be written
 * to, is passed over without complaint: the panel's own digest says how many
 * customers were reached. A mail that went out stamps its documents with
 * customer_notified_at so the invoice screen can say so.
 *
 * @param array $rows  New and second rows together
 * @param int   $now
 * @return array  ['sent' => accounts written to, 'skipped' => accounts passed over, 'failed' => mails refused]
 */
function erp_overdue_notify_customers($rows, $now)
{
    $result = array('sent' => 0, 'skipped' => 0, 'failed' => 0);

    if (!defined('ERP_OVERDUE_NOTIFY_CUSTOMER') || !ERP_OVERDUE_NOTIFY_CUSTOMER || !erp_overdue_notify_followups_ready()) {
        return $result;
    }

    $sender = erp_overdue_notify_sender();

    if ($sender === '') {
        return $result;
    }

    $by_account = array();

    foreach ($rows as $row) {
        $by_account[(int) $row['account_id']][] = $row;
    }

    foreach ($by_account as $account_id => $documents) {
        $first = $documents[0];
        $address = (string) $first['account_email'];

        if (!$first['customer_wanted'] || ($address === '') || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $result['skipped']++;
            continue;
        }

        $has_second = false;
        $ids = array();

        foreach ($documents as $row) {
            $ids[] = (int) $row['id'];
            if ((int) $row['notified_at'] > 0) {
                $has_second = true;
            }
        }

        $sent = email(array(
            'to'                 => $address,
            'from_name'          => ORGANIZATION_NAME,
            'from_email_address' => $sender,
            'reply_to'           => $sender,
            'subject'            => lang(array('string' => '{var:1} - payment reminder', 'vars' => ORGANIZATION_NAME)),
            'format'             => 'html',
            'body'               => erp_overdue_customer_mail_html((string) $first['title'], $documents, $has_second),
            'type'               => 'system',
        ));

        if ($sent) {
            db("UPDATE erp_invoices SET customer_notified_at = '" . (int) $now . "' WHERE id IN (" . implode(',', $ids) . ")");
            $result['sent']++;
        } else {
            $result['failed']++;
        }
    }

    return $result;
}

/**
 * Where one invoice stands with the reminders, for its screen.
 *
 * @param array $invoice  The erp_invoices row (SELECT *), with or without the
 *                        follow-up columns
 * @param int   $now
 * @return array  phase: 'off' (reminders off, or not a claim that can be late),
 *                'not_due', 'below' (late, under the threshold), 'snoozed',
 *                'pending' (in the next digest), 'pending_second', 'announced'
 *                (nothing more will be said); plus threshold, days, the four
 *                stamps and the account's customer-mail choice
 */
function erp_overdue_invoice_state($invoice, $now = 0)
{
    $now = ($now > 0) ? (int) $now : time();

    $state = array(
        'phase'                => 'off',
        'threshold'            => 0,
        'days'                 => 0,
        'notified_at'          => (int) (isset($invoice['overdue_notified_at']) ? $invoice['overdue_notified_at'] : 0),
        'second_notified_at'   => (int) (isset($invoice['overdue_second_notified_at']) ? $invoice['overdue_second_notified_at'] : 0),
        'snoozed_until'        => (int) (isset($invoice['overdue_snoozed_until']) ? $invoice['overdue_snoozed_until'] : 0),
        'customer_notified_at' => (int) (isset($invoice['customer_notified_at']) ? $invoice['customer_notified_at'] : 0),
        'customer_wanted'      => true,
        'customer_address'     => '',
    );

    if (((string) $invoice['direction'] !== 'sales') || ((string) $invoice['doc_type'] !== 'invoice')
        || !in_array((string) $invoice['status'], array('issued', 'partially_paid'), true)) {
        return $state;
    }

    $account = ((int) $invoice['account_id'] > 0)
        ? db_item("SELECT email" . (erp_overdue_column_exists('erp_accounts', 'overdue_notify_days') ? ", overdue_notify_days" : "")
            . (erp_overdue_notify_followups_ready() ? ", overdue_notify_customer" : "")
            . " FROM erp_accounts WHERE id = '" . (int) $invoice['account_id'] . "' LIMIT 1")
        : null;

    if (is_array($account)) {
        $state['customer_address'] = trim((string) $account['email']);
        $state['customer_wanted'] = !isset($account['overdue_notify_customer']) || ((int) $account['overdue_notify_customer'] === 1);
    }

    $state['threshold'] = erp_overdue_notify_threshold(is_array($account) && isset($account['overdue_notify_days']) ? $account['overdue_notify_days'] : 0);

    $due = ((string) $invoice['due_date'] !== '0000-00-00') ? (string) $invoice['due_date'] : (string) $invoice['issue_date'];
    $state['days'] = erp_aging_days($due, date('Y-m-d', $now));

    if (!erp_overdue_notify_enabled() || ($state['threshold'] <= 0)) {
        return $state;
    }

    if ($state['days'] <= 0) {
        $state['phase'] = 'not_due';
    } elseif ($state['snoozed_until'] > $now) {
        $state['phase'] = 'snoozed';
    } elseif ($state['days'] < $state['threshold']) {
        $state['phase'] = 'below';
    } elseif ($state['notified_at'] === 0) {
        $state['phase'] = 'pending';
    } elseif (erp_overdue_notify_followups_ready() && ($state['second_notified_at'] === 0)
        && ($state['days'] >= $state['threshold'] + erp_overdue_second_notice_days())) {
        $state['phase'] = 'pending_second';
    } else {
        $state['phase'] = 'announced';
    }

    return $state;
}

/**
 * Puts the reminders for one invoice off until a date.
 *
 * Only a claim that can be late takes a snooze: an open sales invoice. The
 * date has to be ahead of today and within a year; a snooze is a pause, not a
 * way of dropping a document from the reminders for good.
 *
 * @param int    $invoice_id
 * @param string $until  Y-m-d
 * @return array  ['success' => bool, 'error' => string, 'until' => int]
 */
function erp_overdue_snooze($invoice_id, $until)
{
    $invoice_id = (int) $invoice_id;

    if (!erp_overdue_notify_followups_ready()) {
        return array('success' => false, 'error' => lang('The database has not been upgraded for this yet.'), 'until' => 0);
    }

    $invoice = db_item("SELECT id, direction, doc_type, status FROM erp_invoices WHERE id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice) || ((string) $invoice['direction'] !== 'sales') || ((string) $invoice['doc_type'] !== 'invoice')
        || !in_array((string) $invoice['status'], array('issued', 'partially_paid'), true)) {
        return array('success' => false, 'error' => lang('Only an open sales invoice can have its reminders put off.'), 'until' => 0);
    }

    $until = trim((string) $until);
    $parts = explode('-', $until);

    if ((count($parts) !== 3) || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) !== 1) || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
        return array('success' => false, 'error' => lang('Please enter a valid date.'), 'until' => 0);
    }

    $stamp = mktime(0, 0, 0, (int) $parts[1], (int) $parts[2], (int) $parts[0]);
    $today = mktime(0, 0, 0);

    if ($stamp <= $today) {
        return array('success' => false, 'error' => lang('The date has to be after today.'), 'until' => 0);
    }

    if ($stamp > $today + erp_overdue_snooze_max_days() * 86400) {
        return array('success' => false, 'error' => lang(array('string' => 'Reminders can be put off for a year at most ({var:1} days).', 'vars' => erp_overdue_snooze_max_days())), 'until' => 0);
    }

    if (db("UPDATE erp_invoices SET overdue_snoozed_until = '" . $stamp . "', updated_at = '" . time() . "' WHERE id = '" . $invoice_id . "'") === false) {
        return array('success' => false, 'error' => lang('The change could not be saved.'), 'until' => 0);
    }

    return array('success' => true, 'error' => '', 'until' => $stamp);
}

/**
 * Lets the reminders for one invoice resume.
 *
 * @param int $invoice_id
 * @return bool
 */
function erp_overdue_unsnooze($invoice_id)
{
    if (!erp_overdue_notify_followups_ready()) {
        return false;
    }

    return (db("UPDATE erp_invoices SET overdue_snoozed_until = 0, updated_at = '" . time() . "' WHERE id = '" . (int) $invoice_id . "'") !== false);
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
 * @return array  ['ran' => bool, 'reason' => string, 'new' => rows, 'second' => rows,
 *                 'known' => rows, 'snoozed' => rows, 'notification_id' => int,
 *                 'email' => bool|null, 'push' => bool, 'recipients' => array,
 *                 'customers' => ['sent' => int, 'skipped' => int, 'failed' => int]]
 */
function erp_overdue_check($force = false, $from_job = false)
{
    $result = array(
        'ran'             => false,
        'reason'          => '',
        'new'             => array(),
        'second'          => array(),
        'known'           => array(),
        'snoozed'         => array(),
        'notification_id' => 0,
        'email'           => null,
        'push'            => false,
        'recipients'      => array(),
        'customers'       => array('sent' => 0, 'skipped' => 0, 'failed' => 0),
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

    $candidates = erp_overdue_notify_candidates($now);
    $result['new'] = $candidates['new'];
    $result['second'] = $candidates['second'];
    $result['known'] = $candidates['known'];
    $result['snoozed'] = $candidates['snoozed'];

    if (!$candidates['new'] && !$candidates['second']) {
        $result['reason'] = 'nothing_new';
        return $result;
    }

    $result['ran'] = true;
    $count = count($candidates['new']);
    $count_second = count($candidates['second']);
    $total = erp_overdue_notify_total($candidates['new']) + erp_overdue_notify_total($candidates['second']);

    // The customers first, so the panel's own digest can say how many were
    // reached.
    $result['customers'] = erp_overdue_notify_customers(array_merge($candidates['new'], $candidates['second']), $now);

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

        // The row has no counter of its own: title carries the number of
        // documents announced for the first time and form_id - unused by this
        // action - the number announced for the second time; order_total the
        // formatted total of both, the way an order row carries its total.
        $notification_id = create_notification(array(
            'action'      => 'erp_overdue',
            'type'        => 'warning',
            'title'       => (string) $count,
            'form_id'     => $count_second,
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
                'subject'            => lang(array('string' => '{var:1}: {var:2} overdue receivable(s)', 'vars' => array(ORGANIZATION_NAME, $count + $count_second))),
                'format'             => 'html',
                'body'               => erp_overdue_notify_digest_html($candidates['new'], $candidates['second'], $candidates['known'], $result['customers']['sent']),
                'type'               => 'system',
            ));
        } else {
            $result['email'] = false;
        }
    }

    $ids = array();
    $ids_second = array();
    $second_after = erp_overdue_second_notice_days();

    foreach ($candidates['new'] as $row) {
        $ids[] = (int) $row['id'];

        // Already a month past the threshold when first announced: this one
        // appearance is all it gets, or the next digest would list it again.
        if ((int) $row['days'] >= ((int) $row['threshold'] + $second_after)) {
            $ids_second[] = (int) $row['id'];
        }
    }

    foreach ($candidates['second'] as $row) {
        $ids_second[] = (int) $row['id'];
    }

    if ($ids) {
        db("UPDATE erp_invoices SET overdue_notified_at = '" . $now . "' WHERE id IN (" . implode(',', $ids) . ")");
    }

    if ($ids_second && erp_overdue_notify_followups_ready()) {
        db("UPDATE erp_invoices SET overdue_second_notified_at = '" . $now . "' WHERE id IN (" . implode(',', $ids_second) . ")");
    }

    db("UPDATE config SET erp_overdue_notify_sent_at = '" . $now . "'");

    return $result;
}
