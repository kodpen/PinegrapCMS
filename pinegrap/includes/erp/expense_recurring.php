<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - repeating expenses.
 *
 * Rent, the internet line, a software subscription: the same expense every
 * month, every three months or every year. A recurrence is started from an
 * expense and keeps what the next one is made of - category, supplier,
 * amount, tax - and when it is due (next_date). Each one that has come due is
 * written as an expense of its own through erp_expense_save(), with the
 * lock, the tax and the till rules every expense has; the recurrence then
 * moves on to the next date.
 *
 * The next one is left to pay (with a due date, when the recurrence has a
 * term), or paid from the till the recurrence names on its own date - for a
 * direct debit or a card subscription. A bill whose amount changes every
 * month (electricity) is left to pay: the operator corrects the amount before
 * paying it.
 *
 * The run is the daily job (erp_expense_recurring_job.php) and a visit to the
 * expenses list by someone who may change them, so a store without a crontab
 * still gets its rent written. A run catches up at most twelve missed
 * occurrences of one recurrence; a date inside a locked period is skipped and
 * said so on the recurrence.
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
 * Whether the recurrence table is there (2026.4.4, 4.96).
 *
 * @return bool
 */
function erp_expense_recurring_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('erp_expenses_ready') && erp_expenses_ready()
            && waf_table_has_column('erp_expense_recurrences', 'next_date')
            && waf_table_has_column('erp_expenses', 'recurrence_id');
    }

    return $ready;
}

/**
 * How often a recurrence may come round, as the screens name it.
 *
 * @return array  months => label
 */
function erp_expense_recurring_periods()
{
    return array(
        1 => lang('Every month'),
        3 => lang('Every three months'),
        6 => lang('Every six months'),
        12 => lang('Every year'),
    );
}

/**
 * The date a number of months after another, on the day of the month the
 * recurrence keeps - the last day of a shorter month when it has no such day
 * (rent on the 31st is due on 30 April and 28 February).
 *
 * @param string $date    Y-m-d
 * @param int    $months
 * @param int    $day     1-31
 * @return string  Y-m-d
 */
function erp_expense_recurring_step($date, $months, $day)
{
    $year = (int) substr((string) $date, 0, 4);
    $month = (int) substr((string) $date, 5, 2) + max(1, (int) $months);

    while ($month > 12) {
        $month -= 12;
        $year++;
    }

    $last = (int) date('t', mktime(0, 0, 0, $month, 1, $year));

    return sprintf('%04d-%02d-%02d', $year, $month, min(max(1, (int) $day), $last));
}

/**
 * One recurrence with its category and till names, or null.
 *
 * @param int $id
 * @return array|null
 */
function erp_expense_recurrence($id)
{
    if (!erp_expense_recurring_ready()) {
        return null;
    }

    $row = db_item("SELECT r.*, c.name AS category_name, t.name AS till_name
        FROM erp_expense_recurrences r
        LEFT JOIN erp_expense_categories c ON c.id = r.category_id
        LEFT JOIN erp_cash_accounts t ON t.id = r.cash_account_id
        WHERE r.id = '" . (int) $id . "'
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The recurrence an expense started or came from, or null: an active one
 * first, then the one it is linked to even if stopped.
 *
 * @param array $expense  erp_expenses row
 * @return array|null
 */
function erp_expense_recurrence_of($expense)
{
    if (!erp_expense_recurring_ready()) {
        return null;
    }

    $active = (int) db_value("SELECT id FROM erp_expense_recurrences
        WHERE status = 'active' AND (source_expense_id = '" . (int) $expense['id'] . "' OR id = '" . (int) ($expense['recurrence_id'] ?? 0) . "')
        ORDER BY id DESC LIMIT 1");

    if ($active > 0) {
        return erp_expense_recurrence($active);
    }

    if ((int) ($expense['recurrence_id'] ?? 0) > 0) {
        return erp_expense_recurrence((int) $expense['recurrence_id']);
    }

    $id = (int) db_value("SELECT id FROM erp_expense_recurrences WHERE source_expense_id = '" . (int) $expense['id'] . "'
        ORDER BY id DESC LIMIT 1");

    return ($id > 0) ? erp_expense_recurrence($id) : null;
}

/**
 * The recurrences, active first, the soonest due first.
 *
 * @param bool $active_only
 * @return array
 */
function erp_expense_recurrences($active_only = true)
{
    if (!erp_expense_recurring_ready()) {
        return array();
    }

    return (array) db_items("SELECT r.*, c.name AS category_name, t.name AS till_name
        FROM erp_expense_recurrences r
        LEFT JOIN erp_expense_categories c ON c.id = r.category_id
        LEFT JOIN erp_cash_accounts t ON t.id = r.cash_account_id"
        . ($active_only ? " WHERE r.status = 'active'" : "") . "
        ORDER BY r.status ASC, r.next_date ASC, r.id ASC");
}

/**
 * Start repeating an expense. The next one is due the given number of months
 * after the expense's date, on the same day of the month.
 *
 * @param int   $expense_id
 * @param array $options   every_months (1, 3, 6, 12), due_days, pay (bool:
 *                         write the next ones as paid), cash_account_id,
 *                         payment_method, end_date (Y-m-d or '')
 * @param int   $user_id
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function erp_expense_recurrence_create($expense_id, $options, $user_id = 0)
{
    $fail = function ($message) {
        return array('success' => false, 'id' => 0, 'error' => $message);
    };

    if (!erp_expense_recurring_ready()) {
        return $fail(lang('Repeating expenses come with the software update; run the update to use them.'));
    }

    $expense = erp_expense($expense_id);

    if ($expense === null) {
        return $fail(lang('The expense could not be found.'));
    }
    if ((string) $expense['status'] === 'cancelled') {
        return $fail(lang('A cancelled expense does not repeat.'));
    }
    $current = erp_expense_recurrence_of($expense);

    if (is_array($current) && ((string) $current['status'] === 'active')) {
        return $fail(lang('This expense repeats already.'));
    }

    $every = (int) ($options['every_months'] ?? 1);

    if (!isset(erp_expense_recurring_periods()[$every])) {
        return $fail(lang('Choose how often the expense repeats.'));
    }

    $pay = !empty($options['pay']);
    $cash_account_id = $pay ? (int) ($options['cash_account_id'] ?? 0) : 0;
    $method = $pay ? (string) ($options['payment_method'] ?? 'cash') : '';

    if ($pay) {
        if (($refusal = erp_expense_till_refusal($cash_account_id, strtoupper((string) $expense['currency']))) !== '') {
            return $fail($refusal);
        }
        if (!in_array($method, erp_cash_payment_methods(), true)) {
            return $fail(lang('Choose a payment method.'));
        }
    }

    $end_date = (string) ($options['end_date'] ?? '');
    $end_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) ? $end_date : '0000-00-00';

    $day = (int) substr((string) $expense['expense_date'], 8, 2);
    $next = erp_expense_recurring_step((string) $expense['expense_date'], $every, $day);

    // What the next one is made of: the amount as the receipt printed it, so
    // the tax is worked out again at the same rate.
    $ok = db("INSERT INTO erp_expense_recurrences SET
            source_expense_id = '" . (int) $expense['id'] . "',
            category_id = '" . (int) $expense['category_id'] . "',
            supplier = '" . escape((string) $expense['supplier']) . "',
            supplier_tax_number = '" . escape((string) $expense['supplier_tax_number']) . "',
            description = '" . escape((string) $expense['description']) . "',
            currency = '" . escape((string) $expense['currency']) . "',
            amount = '" . (int) $expense['total_amount'] . "',
            includes_tax = 1,
            tax_rate = '" . escape((string) $expense['tax_rate']) . "',
            tax_deductible = '" . ((int) $expense['tax_deductible'] === 1 ? 1 : 0) . "',
            every_months = '" . $every . "',
            day_of_month = '" . $day . "',
            due_days = '" . min(365, max(0, (int) ($options['due_days'] ?? 0))) . "',
            pay_mode = '" . ($pay ? 'paid' : 'unpaid') . "',
            cash_account_id = '" . $cash_account_id . "',
            payment_method = '" . escape($method) . "',
            next_date = '" . escape($next) . "',
            end_date = '" . escape($end_date) . "',
            status = '" . ((($end_date !== '0000-00-00') && ($next > $end_date)) ? 'stopped' : 'active') . "',
            created_by = '" . (int) $user_id . "',
            created_at = '" . time() . "',
            updated_at = '" . time() . "'");

    if ($ok === false) {
        return $fail(lang('The repeat could not be saved.'));
    }

    $id = (int) mysqli_insert_id(db::$con);

    db("UPDATE erp_expenses SET recurrence_id = '" . $id . "' WHERE id = '" . (int) $expense['id'] . "'");

    return array('success' => true, 'id' => $id, 'error' => '');
}

/**
 * Stop a recurrence. The expenses it wrote stay as they are.
 *
 * @param int $id
 * @param int $user_id
 * @return bool
 */
function erp_expense_recurrence_stop($id, $user_id = 0)
{
    if (!erp_expense_recurring_ready()) {
        return false;
    }

    return (db("UPDATE erp_expense_recurrences SET status = 'stopped', stopped_at = '" . time() . "', stopped_by = '" . (int) $user_id . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $id . "' AND status = 'active'") !== false);
}

/**
 * The words that say what a recurrence's expense is, with its month:
 * "Shop rent (October 2026)".
 *
 * @param array  $recurrence
 * @param string $date
 * @return string
 */
function erp_expense_recurring_description($recurrence, $date)
{
    $month = get_month_name_from_number(substr((string) $date, 5, 2)) . ' ' . substr((string) $date, 0, 4);
    $words = trim((string) $recurrence['description']);

    // A description that already names its month (copied from the first one)
    // loses that name rather than carrying two.
    $words = trim(preg_replace('/\s*\([^)]*\d{4}\)\s*$/u', '', $words));

    return mb_substr((($words !== '') ? $words . ' ' : '') . '(' . $month . ')', 0, 255);
}

/**
 * Write every expense that has come due. Safe to call often: one indexed read
 * when nothing is due, and each recurrence is claimed by moving its date in
 * the same statement that checks it, so two runs cannot write the same one.
 *
 * @param string|null $today  Y-m-d, today when null
 * @param int         $limit  Recurrences looked at in one run
 * @return array ['written' => int, 'skipped' => int, 'errors' => string[]]
 */
function erp_expense_recurring_run($today = null, $limit = 50)
{
    $result = array('written' => 0, 'skipped' => 0, 'errors' => array());

    if (!erp_expense_recurring_ready()) {
        return $result;
    }

    $today = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $today) ? (string) $today : date('Y-m-d');

    foreach ((array) db_items("SELECT * FROM erp_expense_recurrences
        WHERE status = 'active' AND next_date > '0000-00-00' AND next_date <= '" . escape($today) . "'
        ORDER BY next_date ASC, id ASC
        LIMIT " . max(1, (int) $limit)) as $recurrence) {

        for ($round = 0; ($round < 12) && ((string) $recurrence['next_date'] <= $today); $round++) {
            $date = (string) $recurrence['next_date'];
            $following = erp_expense_recurring_step($date, (int) $recurrence['every_months'], (int) $recurrence['day_of_month']);
            $ended = ((string) $recurrence['end_date'] !== '0000-00-00') && ($following > (string) $recurrence['end_date']);

            // Claim this date: only the run that moves it on writes it.
            db("UPDATE erp_expense_recurrences SET next_date = '" . escape($following) . "', updated_at = '" . time() . "'"
                . ($ended ? ", status = 'stopped', stopped_at = '" . time() . "'" : '') . "
                WHERE id = '" . (int) $recurrence['id'] . "' AND next_date = '" . escape($date) . "' AND status = 'active'");

            if (mysqli_affected_rows(db::$con) !== 1) {
                break;
            }

            $recurrence['next_date'] = $following;

            if (($refusal = erp_lock_refusal($date)) !== '') {
                $result['skipped']++;
                db("UPDATE erp_expense_recurrences SET last_error = '" . escape(mb_substr(lang(array(
                    'string' => '{var:1} was not written: the books are locked up to {var:2}.',
                    'vars' => array(prepare_form_data_for_output($date, 'date', false), prepare_form_data_for_output(erp_lock_date(), 'date', false)),
                )), 0, 255)) . "' WHERE id = '" . (int) $recurrence['id'] . "'");
            } else {
                $pay = ((string) $recurrence['pay_mode'] === 'paid');
                $base = erp_base_currency();
                $currency = strtoupper((string) $recurrence['currency']);
                $rate = 1.0;
                $rate_date = $date;
                $rate_source = 'base';

                // A foreign-currency expense takes the rate recorded for its
                // day; with none recorded it cannot be written yet.
                if ($currency !== $base) {
                    $recorded = erp_fx_rate_for($currency, $date);
                    $rate = is_array($recorded) ? (float) $recorded['rate'] : 0.0;
                    $rate_date = is_array($recorded) ? (string) $recorded['rate_date'] : $date;
                    $rate_source = is_array($recorded) ? (string) $recorded['source'] : '';
                }

                $saved = erp_expense_save(array(
                    'expense_date' => $date,
                    'category_id' => (int) $recurrence['category_id'],
                    'supplier' => (string) $recurrence['supplier'],
                    'supplier_tax_number' => (string) $recurrence['supplier_tax_number'],
                    'document_no' => '',
                    'description' => erp_expense_recurring_description($recurrence, $date),
                    'currency' => $currency,
                    'exchange_rate' => $rate,
                    'exchange_rate_date' => $rate_date,
                    'exchange_rate_source' => $rate_source,
                    'amount' => (int) $recurrence['amount'],
                    'includes_tax' => ((int) $recurrence['includes_tax'] === 1),
                    'tax_rate' => (float) $recurrence['tax_rate'],
                    'tax_amount' => null,
                    'tax_deductible' => ((int) $recurrence['tax_deductible'] === 1),
                    'due_date' => (!$pay && ((int) $recurrence['due_days'] > 0)) ? date('Y-m-d', strtotime($date . ' +' . (int) $recurrence['due_days'] . ' day')) : '',
                    'pay' => $pay,
                    'cash_account_id' => (int) $recurrence['cash_account_id'],
                    'payment_method' => (string) $recurrence['payment_method'],
                    'paid_date' => $date,
                ), (int) $recurrence['created_by']);

                if ($saved['success']) {
                    $result['written']++;
                    db("UPDATE erp_expenses SET recurrence_id = '" . (int) $recurrence['id'] . "' WHERE id = '" . (int) $saved['id'] . "'");
                    db("UPDATE erp_expense_recurrences SET occurrences = occurrences + 1, last_expense_id = '" . (int) $saved['id'] . "', last_error = ''
                        WHERE id = '" . (int) $recurrence['id'] . "'");
                } else {
                    // Given back its date, so the next run tries again once
                    // whatever stood in the way (a till, a rate) is put right.
                    $result['errors'][] = '#' . (int) $recurrence['id'] . ' ' . $date . ': ' . $saved['error'];
                    db("UPDATE erp_expense_recurrences SET next_date = '" . escape($date) . "', last_error = '" . escape(mb_substr($saved['error'], 0, 255)) . "'"
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
