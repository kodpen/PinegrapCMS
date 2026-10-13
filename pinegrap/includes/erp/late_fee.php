<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - late payment fees.
 *
 * What a customer owes for paying after the due date: a monthly rate on what
 * was owed past it, day by day. The store sets the rate (and the tax rate of
 * the fee line) on the ERP settings screen; the account screen and the
 * reconciliation letter screen say what the account has run up, and a button
 * writes it on a one-line sales invoice draft. Nothing is issued and nothing
 * reaches the ledger by itself: the draft is checked and issued like any
 * other, or deleted.
 *
 * The fee is worked out per invoice from its due date, the payments
 * (erp_settlements) and the returns against it, so an invoice paid late
 * earns its fee as surely as one still open. An issued fee invoice carries
 * the last day it charged (erp_invoices.late_fee_through); the next fee for
 * the account starts the day after, so a day is never charged twice. Fee
 * invoices themselves earn no fee. Figures are the base-currency ones the
 * ledger holds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_LATE_FEE_MAX_RATE')) {
    // Percent a month. Well above any rate a contract sets; it catches a
    // yearly rate typed into the monthly field.
    define('ERP_LATE_FEE_MAX_RATE', 50);
}

/**
 * Whether the 10.61 columns are there.
 *
 * @return bool
 */
function erp_late_fee_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('config', 'erp_late_fee_rate')
            && waf_table_has_column('erp_invoices', 'late_fee_through');
    }

    return $ready;
}

/**
 * The store's late fee terms, read once per request.
 *
 * @param bool $reload  Read again (after a save)
 * @return array ['rate' => float (percent a month, 0 = off), 'tax_rate' => float|null (null = the account's own rate)]
 */
function erp_late_fee_settings($reload = false)
{
    static $settings = null;

    if (($settings !== null) && !$reload) {
        return $settings;
    }

    $settings = array('rate' => 0.0, 'tax_rate' => null);

    if (!erp_late_fee_ready()) {
        return $settings;
    }

    $row = db_item("SELECT erp_late_fee_rate, erp_late_fee_tax_rate FROM config LIMIT 1");

    if (is_array($row)) {
        $settings['rate'] = max(0.0, (float) $row['erp_late_fee_rate']);
        $settings['tax_rate'] = ($row['erp_late_fee_tax_rate'] === null) ? null : (float) $row['erp_late_fee_tax_rate'];
    }

    return $settings;
}

/**
 * Save the terms from the settings screen. An empty tax rate means the
 * account's own rate: what a product without a rate of its own is sold to
 * that account at.
 *
 * @param string $rate_text      Percent a month, typed
 * @param string $tax_rate_text  Percent, typed, or ''
 * @return array ['success' => bool, 'error' => string, 'field' => string]
 */
function erp_late_fee_settings_save($rate_text, $tax_rate_text)
{
    if (!erp_late_fee_ready()) {
        return array('success' => false, 'error' => lang('Late payment fees come with the software update; run the update to use them.'), 'field' => '_error');
    }

    $rate = (trim((string) $rate_text) === '') ? 0.0 : erp_fx_rate_in($rate_text);

    if (($rate < 0) || ($rate > ERP_LATE_FEE_MAX_RATE)) {
        return array('success' => false, 'error' => lang(array('string' => 'The monthly rate runs from 0 to {var:1}.', 'vars' => ERP_LATE_FEE_MAX_RATE)), 'field' => 'late_fee_rate');
    }

    $tax_rate = (trim((string) $tax_rate_text) === '') ? null : erp_fx_rate_in($tax_rate_text);

    if (($tax_rate !== null) && (($tax_rate < 0) || ($tax_rate > 100))) {
        return array('success' => false, 'error' => lang('The tax rate runs from 0 to 100.'), 'field' => 'late_fee_tax_rate');
    }

    $ok = db("UPDATE config SET
            erp_late_fee_rate = '" . escape(number_format($rate, 3, '.', '')) . "',
            erp_late_fee_tax_rate = " . (($tax_rate === null) ? "NULL" : "'" . escape(number_format($tax_rate, 3, '.', '')) . "'"));

    if ($ok === false) {
        return array('success' => false, 'error' => erp_db_error(), 'field' => '_error');
    }

    erp_late_fee_settings(true);

    return array('success' => true, 'error' => '', 'field' => '');
}

/**
 * The fee one invoice has run up after a given day.
 *
 * Interest runs on what was owed past the due date, a day at a time, from
 * the day after the due date - or after $after, the last day already
 * charged, when that is later - to the day a payment or a return took its
 * part off, and on what is still open to the as-of date. A payment on or
 * before the starting day earns nothing; one after the as-of date is not
 * known yet. A month is thirty days. Rounded once, at the end, as
 * erp_apply_rate() rounds.
 *
 * @param int    $total       What the invoice claims, kurus
 * @param string $due_date    Y-m-d
 * @param array  $reductions  Payments and returns: [['date' => Y-m-d, 'amount' => kurus], ...]
 * @param string $after       Y-m-d, the last day already charged, or ''
 * @param string $as_of       Y-m-d
 * @param float  $rate        Percent a month
 * @return array ['fee' => kurus, 'days' => int (days charged), 'average' => kurus (average owed over them)]
 */
function erp_late_fee_for_invoice($total, $due_date, $reductions, $after, $as_of, $rate)
{
    $none = array('fee' => 0, 'days' => 0, 'average' => 0);
    $start = (string) $due_date;

    if (((string) $after !== '') && ((string) $after > $start)) {
        $start = (string) $after;
    }

    if (((float) $rate <= 0) || ((string) $as_of <= $start)) {
        return $none;
    }

    usort($reductions, function ($a, $b) {
        return strcmp((string) $a['date'], (string) $b['date']);
    });

    $owed = (int) $total;
    $cursor = $start;
    $kurus_days = 0;
    $days = 0;

    foreach ($reductions as $reduction) {
        $date = (string) $reduction['date'];

        if ($date > (string) $as_of) {
            break;
        }

        if (($date > $cursor) && ($owed > 0)) {
            $span = erp_aging_days($cursor, $date);
            $kurus_days += $owed * $span;
            $days += $span;
            $cursor = $date;
        }

        $owed -= (int) $reduction['amount'];
    }

    if ($owed > 0) {
        $span = erp_aging_days($cursor, (string) $as_of);
        $kurus_days += $owed * $span;
        $days += $span;
    }

    if ($days <= 0) {
        return $none;
    }

    return array(
        'fee' => (int) round($kurus_days * ((float) $rate) / 100 / 30),
        'days' => $days,
        'average' => (int) round($kurus_days / $days),
    );
}

/**
 * The last day a late fee has been charged for the account: the latest
 * late_fee_through among its fee invoices that were issued and not
 * cancelled. A draft charges nothing until it is issued.
 *
 * @param int $account_id
 * @return string  Y-m-d, or ''
 */
function erp_late_fee_charged_through($account_id)
{
    if (!erp_late_fee_ready()) {
        return '';
    }

    $date = db_value("SELECT MAX(late_fee_through) FROM erp_invoices
        WHERE account_id = '" . (int) $account_id . "' AND direction = 'sales' AND doc_type = 'invoice'
          AND status NOT IN ('draft', 'cancelled') AND late_fee_through IS NOT NULL");

    return (is_string($date) && ($date > '0000-00-00')) ? $date : '';
}

/**
 * The account's fee invoice draft not issued yet, so a second one is not
 * written beside it.
 *
 * @param int $account_id
 * @return int  Invoice id, or 0
 */
function erp_late_fee_open_draft($account_id)
{
    if (!erp_late_fee_ready()) {
        return 0;
    }

    return (int) db_value("SELECT id FROM erp_invoices
        WHERE account_id = '" . (int) $account_id . "' AND status = 'draft' AND late_fee_through IS NOT NULL
        ORDER BY id DESC LIMIT 1");
}

/**
 * The first day a fee period starts on when the screen names none: the
 * first of January of the as-of year, as the reconciliation letter's
 * movements do.
 *
 * @param string $as_of  Y-m-d
 * @return string  Y-m-d
 */
function erp_late_fee_default_from($as_of)
{
    return substr((string) $as_of, 0, 4) . '-01-01';
}

/**
 * What an account has run up in late fees over a period.
 *
 * The period runs from $from (or the day after the last day already
 * charged, when that is later) to $as_of, both included. Sales invoices the
 * account was billed, issued by the as-of date and due before it, each with
 * its payments and returns; an invoice paid on time, or settled whole before
 * the period, adds nothing and is left out.
 *
 * @param int    $account_id
 * @param string $from   Y-m-d, '' for the default
 * @param string $as_of  Y-m-d, '' for today
 * @return array  rate, tax_rate, from, to, after, total (kurus), lines => [[invoice_id,
 *                full_number, due_date, days, average, fee], ...]
 */
function erp_late_fee_account($account_id, $from = '', $as_of = '')
{
    $settings = erp_late_fee_settings();
    $as_of = erp_aging_as_of($as_of);
    $from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) === 1) ? (string) $from : erp_late_fee_default_from($as_of);

    // The day before the period: what the per-invoice sum calls "already charged".
    $after = date('Y-m-d', strtotime($from . ' -1 day'));
    $charged = erp_late_fee_charged_through($account_id);

    if ($charged > $after) {
        $after = $charged;
    }

    $result = array(
        'rate' => (float) $settings['rate'],
        'tax_rate' => $settings['tax_rate'],
        'from' => date('Y-m-d', strtotime($after . ' +1 day')),
        'to' => $as_of,
        'after' => $after,
        'total' => 0,
        'lines' => array(),
    );

    if (!erp_late_fee_ready() || ($result['rate'] <= 0) || ($after >= $as_of)) {
        return $result;
    }

    $due = erp_aging_due_sql('i');

    // Still open, or something came off it after the period started: the
    // rest earned no fee in the period whatever their history.
    $invoices = (array) db_items("SELECT i.id, i.full_number, i.grand_total_base, " . $due . " AS effective_due
        FROM erp_invoices i
        WHERE i.account_id = '" . (int) $account_id . "' AND i.direction = 'sales' AND i.doc_type = 'invoice'
          AND i.status IN ('issued', 'partially_paid', 'paid') AND i.late_fee_through IS NULL
          AND i.issue_date <= '" . escape($as_of) . "' AND " . $due . " < '" . escape($as_of) . "'
          AND (i.status <> 'paid'
            OR EXISTS (SELECT 1 FROM erp_settlements s WHERE s.invoice_id = i.id AND s.doc_date > '" . escape($after) . "' AND s.doc_date > " . $due . ")
            OR EXISTS (SELECT 1 FROM erp_invoices r WHERE r.parent_invoice_id = i.id AND r.doc_type = 'return' AND r.status <> 'cancelled'
                AND r.issue_date > '" . escape($after) . "' AND r.issue_date > " . $due . "))
        ORDER BY effective_due ASC, i.id ASC");

    if (empty($invoices)) {
        return $result;
    }

    $ids = implode(',', array_map(function ($row) {
        return (int) $row['id'];
    }, $invoices));

    $reductions = array();

    foreach ((array) db_items("SELECT invoice_id, doc_date, amount_base FROM erp_settlements WHERE invoice_id IN (" . $ids . ")") as $row) {
        $reductions[(int) $row['invoice_id']][] = array('date' => (string) $row['doc_date'], 'amount' => (int) $row['amount_base']);
    }

    foreach ((array) db_items("SELECT parent_invoice_id, issue_date, grand_total_base FROM erp_invoices
        WHERE parent_invoice_id IN (" . $ids . ") AND doc_type = 'return' AND status <> 'cancelled'") as $row) {
        $reductions[(int) $row['parent_invoice_id']][] = array('date' => (string) $row['issue_date'], 'amount' => (int) $row['grand_total_base']);
    }

    foreach ($invoices as $invoice) {
        $fee = erp_late_fee_for_invoice((int) $invoice['grand_total_base'], (string) $invoice['effective_due'],
            $reductions[(int) $invoice['id']] ?? array(), $after, $as_of, $result['rate']);

        if ($fee['fee'] <= 0) {
            continue;
        }

        $result['lines'][] = array(
            'invoice_id' => (int) $invoice['id'],
            'full_number' => (string) $invoice['full_number'],
            'due_date' => (string) $invoice['effective_due'],
            'days' => $fee['days'],
            'average' => $fee['average'],
            'fee' => $fee['fee'],
        );
        $result['total'] += $fee['fee'];
    }

    return $result;
}

/**
 * The fee as a line of text for a screen: the period, the amount and how
 * many invoices it comes from.
 *
 * @param array $fee  From erp_late_fee_account()
 * @return string
 */
function erp_late_fee_summary_text($fee)
{
    return lang(array('string' => 'Late payment fee {var:1} - {var:2}: {var:3} on {var:4} invoice(s), at {var:5} a month.', 'vars' => array(
        prepare_form_data_for_output($fee['from'], 'date', false),
        prepare_form_data_for_output($fee['to'], 'date', false),
        erp_money_out((int) $fee['total']),
        count($fee['lines']),
        erp_percent_text($fee['rate']),
    )));
}

/**
 * The fee line under an account's balance on the account screen and the
 * reconciliation letter screen: what the account has run up, with the
 * button that writes it on a draft; or, when such a draft is waiting, the
 * way to it. Nothing for a supplier, or while the rate is 0.
 *
 * @param array  $account  erp_accounts row
 * @param string $from     Y-m-d, '' for the default
 * @param string $as_of    Y-m-d, '' for today
 * @param string $back     account | reconciliation: where a refusal comes back to
 * @return string  HTML, '' for nothing to say
 */
function erp_late_fee_panel($account, $from = '', $as_of = '', $back = 'account')
{
    if (!erp_late_fee_ready() || ((string) $account['kind'] === 'supplier')) {
        return '';
    }

    $draft = erp_late_fee_open_draft((int) $account['id']);

    if ($draft > 0) {
        return '<div class="d-flex flex-wrap align-items-center gap-2 small">'
            . '<i class="bi bi-hourglass-split text-warning" aria-hidden="true"></i>'
            . '<span>' . h(lang('A late payment fee draft of this account is waiting to be issued.')) . '</span>'
            . '<a class="btn btn-sm btn-outline-primary" href="edit_erp_invoice_draft.php?id=' . (int) $draft . '">' . h(lang('Open the draft')) . '</a>'
            . '</div>';
    }

    if (erp_late_fee_settings()['rate'] <= 0) {
        return '';
    }

    $fee = erp_late_fee_account((int) $account['id'], $from, $as_of);

    if ($fee['total'] <= 0) {
        return '';
    }

    $button = '';

    if (!(defined('USER_ERP_READONLY') && USER_ERP_READONLY)) {
        $button = '<form method="post" action="add_erp_late_fee_invoice.php" class="d-inline-flex mb-0">' . get_token_field()
            . '<input type="hidden" name="account_id" value="' . (int) $account['id'] . '" />'
            . '<input type="hidden" name="from" value="' . h((string) $from) . '" />'
            . '<input type="hidden" name="as_of" value="' . h((string) $as_of) . '" />'
            . '<input type="hidden" name="back" value="' . h(($back === 'reconciliation') ? 'reconciliation' : 'account') . '" />'
            . '<button type="submit" class="btn btn-sm btn-outline-primary" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-receipt me-1" aria-hidden="true"></i>' . h(lang('Late payment fee invoice')) . '</button>'
            . '</form>';
    }

    return '<div class="d-flex flex-wrap align-items-center gap-2 small">'
        . '<i class="bi bi-hourglass-split text-warning" aria-hidden="true"></i>'
        . '<span>' . h(erp_late_fee_summary_text($fee)) . '</span>'
        . $button
        . '</div>';
}

/**
 * Write the fee on a one-line sales invoice draft: today's date, the
 * account's payment term, the store's base currency, the period in the
 * line's description and the invoices it comes from in the notes. The draft
 * remembers the period's last day, which counts once it is issued.
 *
 * @param int    $account_id
 * @param string $from     Y-m-d, '' for the default
 * @param string $as_of    Y-m-d, '' for today
 * @param int    $user_id
 * @return array ['success' => bool, 'invoice_id' => int, 'error' => string]
 */
function erp_late_fee_draft($account_id, $from, $as_of, $user_id)
{
    $fail = function ($message) {
        return array('success' => false, 'invoice_id' => 0, 'error' => $message);
    };

    if (!erp_late_fee_ready()) {
        return $fail(lang('Late payment fees come with the software update; run the update to use them.'));
    }

    $account = erp_account((int) $account_id);

    if (!is_array($account)) {
        return $fail(lang('The account could not be found.'));
    }

    $open = erp_late_fee_open_draft((int) $account['id']);

    if ($open > 0) {
        return array('success' => true, 'invoice_id' => $open, 'error' => '');
    }

    $fee = erp_late_fee_account((int) $account['id'], $from, $as_of);

    if ($fee['total'] <= 0) {
        return $fail(lang('There is no late payment fee to charge for this period.'));
    }

    $period = prepare_form_data_for_output($fee['from'], 'date', false) . ' - ' . prepare_form_data_for_output($fee['to'], 'date', false);
    $notes = array(lang(array('string' => 'Late payment fee at {var:1} a month on what was owed past the due date.', 'vars' => erp_percent_text($fee['rate']))));

    foreach ($fee['lines'] as $line) {
        $notes[] = lang(array('string' => '{var:1} (due {var:2}): {var:3} owed on average for {var:4} day(s), fee {var:5}', 'vars' => array(
            $line['full_number'],
            prepare_form_data_for_output($line['due_date'], 'date', false),
            erp_money_out($line['average']),
            $line['days'],
            erp_money_out($line['fee']),
        )));
    }

    $tax_rate = ($fee['tax_rate'] === null) ? (float) erp_account_zone_rate((int) $account['id']) : (float) $fee['tax_rate'];

    $saved = erp_invoice_draft_save(array(
        'direction' => 'sales',
        'account_id' => (int) $account['id'],
        'issue_date' => date('Y-m-d'),
        'due_date' => '',
        'currency' => erp_base_currency(),
        'series' => erp_invoice_series('sales'),
        'notes' => implode("\n", $notes),
        'created_by' => (int) $user_id,
        'lines' => array(array(
            'description' => lang(array('string' => 'Late payment fee {var:1}', 'vars' => $period)),
            'quantity' => 1,
            'unit_code' => 'C62',
            'unit_price' => (int) $fee['total'],
            'tax_rate' => $tax_rate,
        )),
        'late_fee_through' => $fee['to'],
    ), 0);

    if (empty($saved['success'])) {
        return $fail((string) $saved['error']);
    }

    return array('success' => true, 'invoice_id' => (int) $saved['invoice_id'], 'error' => '');
}
