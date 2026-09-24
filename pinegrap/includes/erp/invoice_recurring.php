<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - repeating invoices.
 *
 * A maintenance contract, a rent the store collects, a monthly service: the
 * same sales invoice every month, every three months or every year. A
 * recurrence is started from an issued invoice typed in the ERP and keeps
 * what the next one is made of - the account, the currency, the lines at
 * their prices, the note - and when it is due (next_date). Each one that has
 * come due is written through the invoice module's own doors: as a draft for
 * the operator to check and issue (the default), or issued straight away
 * with the next number (erp_invoice_create_manual()), in which case the
 * store's e-document and e-mail settings take it from there.
 *
 * Only an invoice typed in the ERP can repeat: an order's invoice carries its
 * shipping and fees in the header rather than on lines, and a copy of its
 * lines would bill less than the order did.
 *
 * The run is the daily job (erp_invoice_recurring_job.php) and a visit to
 * the invoice list, the way repeating expenses run. A date inside a locked
 * period is skipped and said so; any other failure gives the date back so
 * the next run tries again.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Whether the 4.93 table is there.
 *
 * @return bool
 */
function erp_invoice_recurring_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_invoice_recurrences', 'next_date');
    }

    return $ready;
}

/**
 * @return array  mode => label
 */
function erp_invoice_recurring_modes()
{
    return array(
        'draft' => lang('As a draft to check and issue'),
        'issue' => lang('Issued straight away'),
    );
}

/**
 * Why an invoice cannot be made to repeat, or '' when it can.
 *
 * @param array $invoice  erp_invoices row
 * @return string
 */
function erp_invoice_recurring_refusal($invoice)
{
    if ((string) $invoice['direction'] !== 'sales' || (string) $invoice['doc_type'] !== 'invoice') {
        return lang('Only a sales invoice can repeat.');
    }

    if (in_array((string) $invoice['status'], array('draft', 'cancelled'), true)) {
        return lang('Only an issued invoice can repeat.');
    }

    if ((int) $invoice['order_id'] > 0) {
        return lang('An order\'s invoice does not repeat: its shipping and fees are not on its lines. Type the invoice in the ERP to repeat it.');
    }

    return '';
}

/**
 * The recurrence started from an invoice that is still running, or null.
 *
 * @param int $invoice_id
 * @return array|null
 */
function erp_invoice_recurrence_of($invoice_id)
{
    if (!erp_invoice_recurring_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM erp_invoice_recurrences
        WHERE source_invoice_id = '" . (int) $invoice_id . "' AND status = 'active'
        ORDER BY id DESC LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * One recurrence, or null.
 *
 * @param int $id
 * @return array|null
 */
function erp_invoice_recurrence($id)
{
    if (!erp_invoice_recurring_ready()) {
        return null;
    }

    $row = db_item("SELECT * FROM erp_invoice_recurrences WHERE id = '" . (int) $id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The recurrences for the list, with their account and invoices.
 *
 * @param bool $active_only
 * @return array
 */
function erp_invoice_recurrences($active_only = true)
{
    if (!erp_invoice_recurring_ready()) {
        return array();
    }

    return (array) db_items("SELECT r.*, a.title AS account_title,
            s.full_number AS source_number, l.full_number AS last_number, l.status AS last_status
        FROM erp_invoice_recurrences r
        LEFT JOIN erp_accounts a ON a.id = r.account_id
        LEFT JOIN erp_invoices s ON s.id = r.source_invoice_id
        LEFT JOIN erp_invoices l ON l.id = r.last_invoice_id
        " . ($active_only ? "WHERE r.status = 'active'" : "") . "
        ORDER BY (r.status = 'active') DESC, r.next_date ASC, r.id DESC
        LIMIT 500");
}

/**
 * Start repeating an invoice.
 *
 * @param int   $invoice_id
 * @param array $options  every_months (1, 3, 6, 12), first_date (Y-m-d), end_date (Y-m-d or ''), mode (draft | issue)
 * @param int   $user_id
 * @return array ['success' => bool, 'id' => int, 'error' => string, 'field' => string]
 */
function erp_invoice_recurrence_create($invoice_id, $options, $user_id)
{
    $fail = function ($message, $field = '_error') {
        return array('success' => false, 'id' => 0, 'error' => $message, 'field' => $field);
    };

    if (!erp_invoice_recurring_ready()) {
        return $fail(lang('Repeating invoices come with the software update; run the update to use them.'));
    }

    $invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return $fail(lang('The invoice could not be found.'));
    }

    if (($refusal = erp_invoice_recurring_refusal($invoice)) !== '') {
        return $fail($refusal);
    }

    if (erp_invoice_recurrence_of((int) $invoice['id']) !== null) {
        return $fail(lang('This invoice already repeats. Stop it first to start it again differently.'));
    }

    $months = (int) ($options['every_months'] ?? 1);
    if (!array_key_exists($months, erp_expense_recurring_periods())) {
        return $fail(lang('Choose how often it repeats.'), 'recur_every');
    }

    $first = (string) ($options['first_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $first)) {
        return $fail(lang('Please enter a valid date.'), 'recur_first');
    }

    if ($first <= (string) $invoice['issue_date']) {
        return $fail(lang('The next invoice has to be dated after this one.'), 'recur_first');
    }

    $end = (string) ($options['end_date'] ?? '');
    if (($end !== '') && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || ($end < $first))) {
        return $fail(lang('The end date must come after the first date.'), 'recur_end');
    }

    $mode = ((string) ($options['mode'] ?? 'draft') === 'issue') ? 'issue' : 'draft';

    $lines = array();
    foreach ((array) db_items("SELECT * FROM erp_invoice_items WHERE invoice_id = '" . (int) $invoice['id'] . "' ORDER BY line_no ASC, id ASC") as $row) {
        $lines[] = array(
            'product_id' => (int) $row['product_id'],
            'description' => (string) $row['description'],
            'quantity' => (float) $row['quantity'],
            'unit_code' => (string) $row['unit_code'],
            'unit_price' => (int) $row['unit_price'],
            'offer_id' => (int) ($row['offer_id'] ?? 0),
            'offer_discount_rate' => (float) ($row['offer_discount_rate'] ?? 0),
            'discount_rate' => (float) $row['discount_rate'],
            'tax_rate' => (float) $row['tax_rate'],
            'tax2_rate' => (float) ($row['tax2_rate'] ?? 0),
            'withholding_code' => (string) $row['withholding_code'],
            'withholding_rate' => (float) $row['withholding_rate'],
        );
    }

    if (empty($lines)) {
        return $fail(lang('The invoice has no lines to repeat.'));
    }

    $data = array(
        'direction' => 'sales',
        'account_id' => (int) $invoice['account_id'],
        'currency' => (string) $invoice['currency'],
        'series' => (string) $invoice['series'],
        'notes' => (string) $invoice['notes'],
        'lines' => $lines,
    );

    $ok = db("INSERT INTO erp_invoice_recurrences SET
            source_invoice_id = '" . (int) $invoice['id'] . "',
            account_id = '" . (int) $invoice['account_id'] . "',
            every_months = '" . $months . "',
            day_of_month = '" . (int) substr($first, 8, 2) . "',
            next_date = '" . escape($first) . "',
            end_date = '" . escape(($end !== '') ? $end : '0000-00-00') . "',
            mode = '" . $mode . "',
            form_data = '" . escape(json_encode($data, JSON_UNESCAPED_UNICODE)) . "',
            grand_total = '" . (int) $invoice['grand_total'] . "',
            currency = '" . escape((string) $invoice['currency']) . "',
            status = 'active',
            created_by = '" . (int) $user_id . "',
            created_at = UNIX_TIMESTAMP(),
            updated_at = UNIX_TIMESTAMP()");

    if ($ok === false) {
        return $fail(erp_db_error());
    }

    return array('success' => true, 'id' => (int) mysqli_insert_id(db::$con), 'error' => '', 'field' => '');
}

/**
 * Stop a recurrence. The invoices it wrote stay.
 *
 * @param int $id
 * @param int $user_id
 * @return bool
 */
function erp_invoice_recurrence_stop($id, $user_id)
{
    db("UPDATE erp_invoice_recurrences SET status = 'stopped', stopped_at = UNIX_TIMESTAMP(), stopped_by = '" . (int) $user_id . "', updated_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $id . "' AND status = 'active'");

    return (mysqli_affected_rows(db::$con) === 1);
}

/**
 * The sentence a recurrence is described by: how often, the next date, how.
 *
 * @param array $recurrence
 * @return string
 */
function erp_invoice_recurrence_text($recurrence)
{
    $periods = erp_expense_recurring_periods();
    $modes = erp_invoice_recurring_modes();

    return lang(array('string' => '{var:1}; next on {var:2}, {var:3}.', 'vars' => array(
        $periods[(int) $recurrence['every_months']] ?? '',
        prepare_form_data_for_output((string) $recurrence['next_date'], 'date', false),
        mb_strtolower($modes[(string) $recurrence['mode']] ?? ''),
    )));
}

/**
 * Write every invoice that has come due.
 *
 * @param string|null $today  Y-m-d, today when null
 * @param int         $limit  Recurrences in one run
 * @return array ['written' => int, 'skipped' => int, 'errors' => string[]]
 */
function erp_invoice_recurring_run($today = null, $limit = 50)
{
    $result = array('written' => 0, 'skipped' => 0, 'errors' => array());

    if (!erp_invoice_recurring_ready()) {
        return $result;
    }

    $today = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today) ? (string) $today : date('Y-m-d');

    foreach ((array) db_items("SELECT * FROM erp_invoice_recurrences
        WHERE status = 'active' AND next_date > '0000-00-00' AND next_date <= '" . escape($today) . "'
        ORDER BY next_date ASC, id ASC
        LIMIT " . max(1, (int) $limit)) as $recurrence) {

        for ($round = 0; ($round < 12) && ((string) $recurrence['next_date'] <= $today); $round++) {
            $date = (string) $recurrence['next_date'];
            $following = erp_expense_recurring_step($date, (int) $recurrence['every_months'], (int) $recurrence['day_of_month']);
            $ended = ((string) $recurrence['end_date'] !== '0000-00-00') && ($following > (string) $recurrence['end_date']);

            // Claim this date: only the run that moves it on writes it.
            db("UPDATE erp_invoice_recurrences SET next_date = '" . escape($following) . "', updated_at = UNIX_TIMESTAMP()"
                . ($ended ? ", status = 'stopped', stopped_at = UNIX_TIMESTAMP()" : '') . "
                WHERE id = '" . (int) $recurrence['id'] . "' AND next_date = '" . escape($date) . "' AND status = 'active'");

            if (mysqli_affected_rows(db::$con) !== 1) {
                break;
            }

            $recurrence['next_date'] = $following;

            if (($refusal = erp_lock_refusal($date)) !== '') {
                $result['skipped']++;
                db("UPDATE erp_invoice_recurrences SET last_error = '" . escape(mb_substr(lang(array(
                    'string' => '{var:1} was not written: the books are locked up to {var:2}.',
                    'vars' => array(prepare_form_data_for_output($date, 'date', false), prepare_form_data_for_output(erp_lock_date(), 'date', false)),
                )), 0, 255)) . "' WHERE id = '" . (int) $recurrence['id'] . "'");
            } else {
                $saved = erp_invoice_recurring_write($recurrence, $date);

                if ($saved['success']) {
                    $result['written']++;
                    db("UPDATE erp_invoice_recurrences SET occurrences = occurrences + 1, last_invoice_id = '" . (int) $saved['invoice_id'] . "', last_error = ''
                        WHERE id = '" . (int) $recurrence['id'] . "'");
                } else {
                    // Given back its date, so the next run tries again once
                    // whatever stood in the way (an account, a rate) is put right.
                    $result['errors'][] = '#' . (int) $recurrence['id'] . ' ' . $date . ': ' . $saved['error'];
                    db("UPDATE erp_invoice_recurrences SET next_date = '" . escape($date) . "', last_error = '" . escape(mb_substr((string) $saved['error'], 0, 255)) . "'"
                        . ($ended ? ", status = 'active', stopped_at = 0" : '') . "
                        WHERE id = '" . (int) $recurrence['id'] . "'");
                    break;
                }
            }

            if ($ended) {
                break;
            }
        }
    }

    return $result;
}

/**
 * Write one invoice of a recurrence, dated $date.
 *
 * @param array  $recurrence
 * @param string $date  Y-m-d
 * @return array ['success' => bool, 'invoice_id' => int, 'error' => string]
 */
function erp_invoice_recurring_write($recurrence, $date)
{
    $data = json_decode((string) $recurrence['form_data'], true);

    if (!is_array($data)) {
        return array('success' => false, 'invoice_id' => 0, 'error' => lang('The recurrence\'s lines could not be read.'));
    }

    $base = erp_base_currency();
    $currency = strtoupper((string) ($data['currency'] ?? $base));

    $data['issue_date'] = $date;
    $data['due_date'] = '';
    $data['created_by'] = (int) $recurrence['created_by'];
    $data['exchange_rate'] = 1.0;
    $data['exchange_rate_date'] = $date;
    $data['exchange_rate_source'] = 'base';

    // A foreign-currency invoice takes the rate recorded for its day; a
    // draft may wait for one, an issued invoice may not.
    if ($currency !== $base) {
        $recorded = erp_fx_rate_for($currency, $date);
        $data['exchange_rate'] = is_array($recorded) ? (float) $recorded['rate'] : 0.0;
        $data['exchange_rate_date'] = is_array($recorded) ? (string) $recorded['rate_date'] : $date;
        $data['exchange_rate_source'] = is_array($recorded) ? (string) $recorded['source'] : '';
    }

    if ((string) $recurrence['mode'] === 'issue') {
        $made = erp_invoice_create_manual($data);

        if (!empty($made['success']) && function_exists('log_activity')) {
            log_activity(lang(array('string' => 'erp invoice ({var:1}) was created', 'vars' => $made['full_number'])), 'SYSTEM');
        }

        return array('success' => !empty($made['success']), 'invoice_id' => (int) ($made['invoice_id'] ?? 0), 'error' => (string) ($made['error'] ?? ''));
    }

    $saved = erp_invoice_draft_save($data, 0);

    if (!empty($saved['success']) && function_exists('log_activity')) {
        log_activity(lang(array('string' => 'erp invoice draft ({var:1}) was saved', 'vars' => (int) $saved['invoice_id'])), 'SYSTEM');
    }

    return array('success' => !empty($saved['success']), 'invoice_id' => (int) ($saved['invoice_id'] ?? 0), 'error' => (string) ($saved['error'] ?? ''));
}
