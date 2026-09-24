<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - expenses.
 *
 * The receipts a business collects that are not purchase invoices from an
 * account: rent, electricity, fuel, a meal with a customer, a software
 * subscription. Each is one row with a date, a category, what it cost and the
 * VAT on it, kept in its own currency and in the base currency.
 *
 * An expense is paid now, from a till or bank account, or left to pay later
 * and paid from its own screen. Paying is a movement out of the till
 * (erp_cash_transactions, doc_type 'expense', doc_id the expense), written in
 * the same transaction as the expense's status, through erp_cash_post() like
 * every other till movement. Cancelling a paid expense turns that movement
 * round, dated today, the way a receipt is cancelled (doc_type 'cancel',
 * doc_id the movement), so the till book strikes it through with no special
 * case. Nothing touches an account's ledger: an expense is nobody's debt.
 *
 * The VAT on an expense is the store's to take back unless it says otherwise
 * (tax_deductible); the accountant's pack counts it with the purchases, and
 * the profit report counts the part that is not taken back as cost.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_EXPENSE_FILE_MAX_BYTES')) {
    define('ERP_EXPENSE_FILE_MAX_BYTES', 10 * 1048576);
}

/**
 * Whether the expense tables are there (2026.4.4, 4.95).
 *
 * @return bool
 */
function erp_expenses_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column')
            && waf_table_has_column('erp_expenses', 'total_base')
            && waf_table_has_column('erp_expense_categories', 'code');
    }

    return $ready;
}

/**
 * The statuses, as the screens name them.
 *
 * @return array
 */
function erp_expense_statuses()
{
    return array(
        'unpaid' => lang('To pay'),
        'paid' => lang('Paid'),
        'cancelled' => lang('Cancelled'),
    );
}

/**
 * The categories a new store starts with, in the operator's language.
 *
 * @return string[]
 */
function erp_expense_category_defaults()
{
    return array(
        lang('Rent'),
        lang('Electricity, water and gas'),
        lang('Phone and internet'),
        lang('Software and subscriptions'),
        lang('Fuel and travel'),
        lang('Meals and hospitality'),
        lang('Office supplies'),
        lang('Shipping and courier'),
        lang('Advertising'),
        lang('Salaries and social security'),
        lang('Accounting and consulting'),
        lang('Repairs and maintenance'),
        lang('Bank fees and commissions'),
        lang('Taxes and fees'),
        lang('Other'),
    );
}

/**
 * The categories, in their order. The first call on an empty table writes
 * the defaults; categories are switched off rather than deleted, so an empty
 * table only ever means one that was never filled.
 *
 * @param bool $only_active
 * @return array
 */
function erp_expense_categories($only_active = false)
{
    static $checked = false;

    if (!erp_expenses_ready()) {
        return array();
    }

    if (!$checked) {
        $checked = true;

        if ((int) db_value("SELECT COUNT(*) FROM erp_expense_categories") === 0) {
            // Two first visits at once must not write the list twice.
            $locked = ((int) db_value("SELECT GET_LOCK('erp_expense_categories_seed', 5)") === 1);

            if ($locked && ((int) db_value("SELECT COUNT(*) FROM erp_expense_categories") === 0)) {
                $order = 0;

                foreach (erp_expense_category_defaults() as $name) {
                    $order += 10;
                    db("INSERT INTO erp_expense_categories (name, code, sort_order, is_active, created_at, updated_at)
                        VALUES ('" . escape(mb_substr($name, 0, 100)) . "', '', '" . $order . "', 1, '" . time() . "', '" . time() . "')");
                }
            }

            if ($locked) {
                db_value("SELECT RELEASE_LOCK('erp_expense_categories_seed')");
            }
        }
    }

    return (array) db_items("SELECT * FROM erp_expense_categories"
        . ($only_active ? " WHERE is_active = 1" : "")
        . " ORDER BY sort_order ASC, name ASC, id ASC");
}

/**
 * Whether the categories carry their own "VAT taken back" default (2026.4.4,
 * 4.103).
 *
 * @return bool
 */
function erp_expense_categories_have_deductible()
{
    return function_exists('waf_table_has_column') && waf_table_has_column('erp_expense_categories', 'tax_deductible');
}

/**
 * Whether an expense of a category takes its VAT back unless the operator
 * says otherwise: the category's own default, on for a category that has
 * none (and before 4.103).
 *
 * @param int $category_id
 * @return bool
 */
function erp_expense_category_deductible($category_id)
{
    if (!erp_expense_categories_have_deductible() || ((int) $category_id <= 0)) {
        return true;
    }

    $value = db_value("SELECT tax_deductible FROM erp_expense_categories WHERE id = '" . (int) $category_id . "'");

    return ($value === null) || ($value === false) || ((int) $value === 1);
}

/**
 * Save the category list: the rows shown, and at most one new one.
 *
 * @param array $rows  id => ['name', 'code', 'sort_order', 'is_active',
 *                     'tax_deductible']
 * @param array $new   ['name', 'code', 'tax_deductible']
 * @return array ['success' => bool, 'error' => string, 'added' => int]
 */
function erp_expense_categories_save($rows, $new = array())
{
    if (!erp_expenses_ready()) {
        return array('success' => false, 'error' => lang('Expenses come with the software update; run the update to use them.'), 'added' => 0);
    }

    $known = array();
    foreach (erp_expense_categories() as $category) {
        $known[(int) $category['id']] = $category;
    }

    $with_deductible = erp_expense_categories_have_deductible();

    foreach ((array) $rows as $id => $row) {
        $id = (int) $id;

        if (!isset($known[$id])) {
            continue;
        }

        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            return array('success' => false, 'error' => lang('Every category needs a name.'), 'added' => 0);
        }

        db("UPDATE erp_expense_categories SET
                name = '" . escape(mb_substr($name, 0, 100)) . "',
                code = '" . escape(mb_substr(trim((string) ($row['code'] ?? '')), 0, 32)) . "',
                sort_order = '" . (int) ($row['sort_order'] ?? $known[$id]['sort_order']) . "',
                is_active = '" . (!empty($row['is_active']) ? 1 : 0) . "',"
                . (($with_deductible && array_key_exists('tax_deductible', $row)) ? "
                tax_deductible = '" . (!empty($row['tax_deductible']) ? 1 : 0) . "'," : "") . "
                updated_at = '" . time() . "'
            WHERE id = '" . $id . "'");
    }

    $added = 0;
    $new_name = trim((string) ($new['name'] ?? ''));

    if ($new_name !== '') {
        $order = (int) db_value("SELECT COALESCE(MAX(sort_order), 0) FROM erp_expense_categories") + 10;

        if (db("INSERT INTO erp_expense_categories (name, code, sort_order, is_active, " . ($with_deductible ? "tax_deductible, " : "") . "created_at, updated_at)
            VALUES (
                '" . escape(mb_substr($new_name, 0, 100)) . "',
                '" . escape(mb_substr(trim((string) ($new['code'] ?? '')), 0, 32)) . "',
                '" . $order . "', 1, " . ($with_deductible ? "'" . ((!array_key_exists('tax_deductible', $new) || !empty($new['tax_deductible'])) ? 1 : 0) . "', " : "") . "'" . time() . "', '" . time() . "')") !== false) {
            $added = (int) mysqli_insert_id(db::$con);
        }
    }

    return array('success' => true, 'error' => '', 'added' => $added);
}

/**
 * Net, tax and total from what was typed.
 *
 * The amount is the total as printed on the receipt (tax included) or the
 * net before tax. A tax amount typed from the receipt wins over the one
 * worked out from the rate, so the books carry what the paper says, to the
 * kurus; it has to be one the rate could give, give or take the rounding of a
 * receipt with several lines.
 *
 * @param int      $amount        Kurus
 * @param bool     $includes_tax
 * @param float    $rate          Percentage
 * @param int|null $typed_tax     Kurus, or null to work it out
 * @return array ['net', 'tax', 'total', 'error']
 */
function erp_expense_figures($amount, $includes_tax, $rate, $typed_tax = null)
{
    $amount = (int) $amount;
    $rate = (float) $rate;
    $fail = function ($message) {
        return array('net' => 0, 'tax' => 0, 'total' => 0, 'error' => $message);
    };

    if ($amount <= 0) {
        return $fail(lang('Enter an amount greater than zero.'));
    }
    if (($rate < 0) || ($rate >= 100)) {
        return $fail(lang('Enter a tax rate between 0 and 100.'));
    }

    if ($includes_tax) {
        $split = erp_split_gross($amount, $rate);
        $net = $split['net'];
        $tax = $split['tax'];
    } else {
        $net = $amount;
        $tax = erp_apply_rate($net, $rate);
    }

    if ($typed_tax !== null) {
        $typed_tax = (int) $typed_tax;

        if ($typed_tax < 0) {
            return $fail(lang('The tax amount cannot be negative.'));
        }
        if (($typed_tax > 0) && ($rate <= 0)) {
            return $fail(lang('A tax amount needs a tax rate.'));
        }

        // A receipt rounds each of its lines; allow a kurus per ten units
        // of the net, and never more than a tenth of the worked-out tax.
        $slack = max(2, min((int) round($tax / 10), (int) ceil($net / 1000)));

        if (abs($typed_tax - $tax) > $slack) {
            return $fail(lang(array(
                'string' => 'The tax amount does not fit the rate: at {var:1} it would be about {var:2}. Check the rate, or leave the tax amount empty.',
                'vars' => array(erp_percent_text($rate), erp_money_out($tax, false)),
            )));
        }

        if ($includes_tax) {
            $net = $amount - $typed_tax;
        }

        $tax = $typed_tax;
    }

    return array('net' => $net, 'tax' => $tax, 'total' => $net + $tax, 'error' => '');
}

/**
 * One expense with its category and till, or null.
 *
 * @param int $id
 * @return array|null
 */
function erp_expense($id)
{
    if (!erp_expenses_ready()) {
        return null;
    }

    $row = db_item("SELECT e.*, c.name AS category_name, c.code AS category_code, t.name AS till_name, t.currency AS till_currency,
            u.user_username AS created_by_name
        FROM erp_expenses e
        LEFT JOIN erp_expense_categories c ON c.id = e.category_id
        LEFT JOIN erp_cash_accounts t ON t.id = e.cash_account_id
        LEFT JOIN user u ON u.user_id = e.created_by
        WHERE e.id = '" . (int) $id . "'
        LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The expenses of a period, newest first.
 *
 * @param array $filters  from, to (Y-m-d), category_id, status, query, limit
 * @return array
 */
function erp_expenses_list($filters = array())
{
    if (!erp_expenses_ready()) {
        return array();
    }

    $where = erp_expenses_where($filters);
    $limit = max(1, min(5000, (int) ($filters['limit'] ?? 1000)));

    return (array) db_items("SELECT e.*, c.name AS category_name, c.code AS category_code, t.name AS till_name
        FROM erp_expenses e
        LEFT JOIN erp_expense_categories c ON c.id = e.category_id
        LEFT JOIN erp_cash_accounts t ON t.id = e.cash_account_id
        WHERE " . $where . "
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT " . $limit);
}

/**
 * The WHERE of a list or a total, over erp_expenses e.
 *
 * @param array $filters
 * @return string
 */
function erp_expenses_where($filters)
{
    $parts = array('1 = 1');
    $date = '/^\d{4}-\d{2}-\d{2}$/';

    if (preg_match($date, (string) ($filters['from'] ?? ''))) {
        $parts[] = "e.expense_date >= '" . escape((string) $filters['from']) . "'";
    }
    if (preg_match($date, (string) ($filters['to'] ?? ''))) {
        $parts[] = "e.expense_date <= '" . escape((string) $filters['to']) . "'";
    }
    if ((int) ($filters['category_id'] ?? 0) > 0) {
        $parts[] = "e.category_id = '" . (int) $filters['category_id'] . "'";
    }

    $status = (string) ($filters['status'] ?? '');

    if (isset(erp_expense_statuses()[$status])) {
        $parts[] = "e.status = '" . escape($status) . "'";
    } elseif ($status === 'standing') {
        $parts[] = "e.status <> 'cancelled'";
    }

    $query = trim((string) ($filters['query'] ?? ''));

    if ($query !== '') {
        $like = "'%" . escape(addcslashes($query, '%_\\')) . "%'";
        $parts[] = "(e.supplier LIKE " . $like . " OR e.document_no LIKE " . $like . " OR e.description LIKE " . $like
            . " OR e.supplier_tax_number LIKE " . $like . ")";
    }

    return implode(' AND ', $parts);
}

/**
 * Totals of a period by category, cancelled expenses left out. Amounts are
 * in the base currency; cost is what the expense costs the store: the net,
 * plus the VAT when it is not taken back.
 *
 * @param string $from
 * @param string $to
 * @return array ['categories' => [...], 'total' => [...]]
 */
function erp_expense_totals($from, $to)
{
    $empty = array('count' => 0, 'net' => 0, 'tax' => 0, 'deductible_tax' => 0, 'total' => 0, 'cost' => 0, 'unpaid' => 0);
    $result = array('categories' => array(), 'total' => $empty);

    if (!erp_expenses_ready()) {
        return $result;
    }

    foreach ((array) db_items("SELECT e.category_id, c.name AS category_name, c.code AS category_code,
            COUNT(*) AS count,
            SUM(e.net_base) AS net,
            SUM(e.tax_base) AS tax,
            SUM(IF(e.tax_deductible = 1, e.tax_base, 0)) AS deductible_tax,
            SUM(e.total_base) AS total,
            SUM(IF(e.status = 'unpaid', e.total_base, 0)) AS unpaid
        FROM erp_expenses e
        LEFT JOIN erp_expense_categories c ON c.id = e.category_id
        WHERE e.status <> 'cancelled'
          AND e.expense_date BETWEEN '" . escape((string) $from) . "' AND '" . escape((string) $to) . "'
        GROUP BY e.category_id, c.name, c.code
        ORDER BY SUM(e.total_base) DESC") as $row) {

        $line = array(
            'category_id' => (int) $row['category_id'],
            'name' => ((string) $row['category_name'] !== '') ? (string) $row['category_name'] : lang('No category'),
            'code' => (string) $row['category_code'],
            'count' => (int) $row['count'],
            'net' => (int) $row['net'],
            'tax' => (int) $row['tax'],
            'deductible_tax' => (int) $row['deductible_tax'],
            'total' => (int) $row['total'],
            'unpaid' => (int) $row['unpaid'],
        );
        $line['cost'] = $line['total'] - $line['deductible_tax'];

        $result['categories'][] = $line;

        foreach (array('count', 'net', 'tax', 'deductible_tax', 'total', 'cost', 'unpaid') as $key) {
            $result['total'][$key] += $line[$key];
        }
    }

    return $result;
}

/**
 * Check a till can pay an amount in a currency.
 *
 * @param int    $cash_account_id
 * @param string $currency
 * @return string  Why not, or ''
 */
function erp_expense_till_refusal($cash_account_id, $currency)
{
    $till = db_item("SELECT id, currency, is_active FROM erp_cash_accounts WHERE id = '" . (int) $cash_account_id . "' LIMIT 1");

    if (!is_array($till)) {
        return lang('Choose a till or bank account.');
    }

    // A till holds one currency, as for a receipt; only asked while foreign
    // currency is on, before which every till carried one fixed code.
    $till_currency = strtoupper(trim((string) $till['currency']));

    if (erp_fx_enabled() && ($till_currency !== '') && ($till_currency !== $currency)) {
        return lang(array(
            'string' => 'That till or bank account is kept in {var:1}, so it cannot take a movement in {var:2}.',
            'vars' => array($till_currency, $currency),
        ));
    }

    return '';
}

/**
 * Write the payment of an expense: the movement out of the till and the
 * expense's paid fields. Runs inside the caller's transaction.
 *
 * @param array $expense  erp_expenses row (id, currency, exchange_rate, ...)
 * @param array $payment  cash_account_id, paid_date, payment_method
 * @param int   $user_id
 * @return string  Why it failed, or ''
 */
function erp_expense_post_payment($expense, $payment, $user_id)
{
    $paid_date = (string) ($payment['paid_date'] ?? date('Y-m-d'));
    $method = (string) ($payment['payment_method'] ?? 'cash');
    $cash_account_id = (int) ($payment['cash_account_id'] ?? 0);

    if (!in_array($method, erp_cash_payment_methods(), true)) {
        return lang('Choose a payment method.');
    }

    if (($refusal = erp_lock_refusal($paid_date)) !== '') {
        return $refusal;
    }

    if ($paid_date > date('Y-m-d')) {
        return lang('The payment cannot be dated after today.');
    }

    if ($paid_date < (string) $expense['expense_date']) {
        return lang('The payment cannot be dated before the expense.');
    }

    if (($refusal = erp_expense_till_refusal($cash_account_id, (string) $expense['currency'])) !== '') {
        return $refusal;
    }

    $description = trim((string) $expense['supplier']);
    $description = mb_substr(lang(array(
        'string' => 'Expense #{var:1}',
        'vars' => (int) $expense['id'],
    )) . (($description !== '') ? (' - ' . $description) : '')
        . (((string) $expense['description'] !== '') ? (' - ' . (string) $expense['description']) : ''), 0, 255);

    $cash_id = erp_cash_post(array(
        'cash_account_id' => $cash_account_id,
        'doc_date' => $paid_date,
        'direction' => 'out',
        'amount' => (int) $expense['total_amount'],
        'amount_base' => (int) $expense['total_base'],
        'currency' => (string) $expense['currency'],
        'exchange_rate' => (float) $expense['exchange_rate'],
        'exchange_rate_date' => (string) $expense['exchange_rate_date'],
        'exchange_rate_source' => (string) $expense['exchange_rate_source'],
        'account_id' => 0,
        'doc_type' => 'expense',
        'doc_id' => (int) $expense['id'],
        'payment_method' => $method,
        'description' => $description,
        'created_by' => (int) $user_id,
    ));

    if ($cash_id === false) {
        $error = erp_db_error();
        return ($error !== '') ? $error : lang('The payment was not saved.');
    }

    if (erp_query("UPDATE erp_expenses SET
            status = 'paid',
            cash_account_id = '" . $cash_account_id . "',
            cash_id = '" . (int) $cash_id . "',
            payment_method = '" . escape($method) . "',
            paid_date = '" . escape($paid_date) . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $expense['id'] . "'") === false) {
        return erp_db_error();
    }

    if (!erp_cash_refresh_balance($cash_account_id)) {
        return erp_db_error();
    }

    return '';
}

/**
 * Record an expense, or change one.
 *
 * A paid expense keeps its figures and date, which are what left the till;
 * what it is (category, supplier, receipt number, words, whether its VAT is
 * taken back) can still be corrected. Nothing dated in a locked period is
 * written or changed.
 *
 * @param array $data     expense_date, category_id, supplier, supplier_tax_number,
 *                        document_no, description, currency, exchange_rate,
 *                        exchange_rate_date, exchange_rate_source, amount (kurus),
 *                        includes_tax (bool), tax_rate, tax_amount (kurus or null),
 *                        tax_deductible (bool), due_date; to pay it now:
 *                        pay (bool), cash_account_id, payment_method, paid_date
 * @param int   $user_id
 * @param int   $id       0 to record a new one
 * @return array ['success' => bool, 'id' => int, 'error' => string, 'field' => string]
 */
function erp_expense_save($data, $user_id = 0, $id = 0)
{
    $fail = function ($message, $field = '') {
        return array('success' => false, 'id' => 0, 'error' => $message, 'field' => $field);
    };

    if (!erp_expenses_ready()) {
        return $fail(lang('Expenses come with the software update; run the update to use them.'));
    }

    $id = (int) $id;
    $existing = null;

    if ($id > 0) {
        $existing = erp_expense($id);

        if ($existing === null) {
            return $fail(lang('The expense could not be found.'));
        }
        if ((string) $existing['status'] === 'cancelled') {
            return $fail(lang('A cancelled expense is not changed.'));
        }
        if (($refusal = erp_lock_refusal((string) $existing['expense_date'])) !== '') {
            return $fail($refusal);
        }
    }

    $paid = is_array($existing) && ((string) $existing['status'] === 'paid');

    $category_id = (int) ($data['category_id'] ?? 0);
    $category = ($category_id > 0) ? db_item("SELECT id, is_active FROM erp_expense_categories WHERE id = '" . $category_id . "' LIMIT 1") : null;

    // A category switched off since keeps the expenses already in it.
    if (!is_array($category) || (((int) $category['is_active'] !== 1) && (!is_array($existing) || ((int) $existing['category_id'] !== $category_id)))) {
        return $fail(lang('Choose a category.'), 'category_id');
    }

    $tax_number = erp_tax_number_check((string) ($data['supplier_tax_number'] ?? ''));

    if ($tax_number['error'] !== '') {
        return $fail($tax_number['error'], 'supplier_tax_number');
    }

    $columns = array(
        'category_id' => $category_id,
        'supplier' => mb_substr(trim((string) ($data['supplier'] ?? '')), 0, 255),
        'supplier_tax_number' => mb_substr((string) $tax_number['value'], 0, 32),
        'document_no' => mb_substr(trim((string) ($data['document_no'] ?? '')), 0, 64),
        'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 255),
        'tax_deductible' => !empty($data['tax_deductible']) ? 1 : 0,
    );

    if (!$paid) {
        $date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
        $expense_date = (string) ($data['expense_date'] ?? '');

        if (!preg_match($date_pattern, $expense_date)) {
            return $fail(lang('Please enter a valid date.'), 'expense_date');
        }
        if (($refusal = erp_lock_refusal($expense_date)) !== '') {
            return $fail($refusal, 'expense_date');
        }

        // A receipt is written when the money is spent, never ahead of it.
        if ($expense_date > date('Y-m-d')) {
            return $fail(lang('The date of an expense cannot be after today.'), 'expense_date');
        }

        $base = erp_base_currency();
        $currency = erp_fx_enabled() ? strtoupper(trim((string) ($data['currency'] ?? $base))) : $base;
        $currency = ($currency !== '') ? $currency : $base;

        if (!erp_fx_currency_allowed($currency)) {
            return $fail(lang('That currency is not enabled for the ERP.'), 'currency');
        }

        $exchange_rate = ($currency === $base) ? 1.0 : (float) ($data['exchange_rate'] ?? 0);

        if ($exchange_rate <= 0) {
            return $fail(lang('Enter an exchange rate greater than zero.'), 'exchange_rate');
        }

        $figures = erp_expense_figures(
            (int) ($data['amount'] ?? 0),
            !empty($data['includes_tax']),
            (float) ($data['tax_rate'] ?? 0),
            (array_key_exists('tax_amount', $data) && ($data['tax_amount'] !== null)) ? (int) $data['tax_amount'] : null
        );

        if ($figures['error'] !== '') {
            return $fail($figures['error'], 'amount');
        }

        // The base figures add up the way the document ones do: the total
        // is converted once, the tax once, and the net is what is left.
        $total_base = ($currency === $base) ? $figures['total'] : erp_to_base($figures['total'], $exchange_rate);
        $tax_base = ($currency === $base) ? $figures['tax'] : erp_to_base($figures['tax'], $exchange_rate);

        $due_date = (string) ($data['due_date'] ?? '');

        $columns += array(
            'expense_date' => $expense_date,
            'currency' => $currency,
            'exchange_rate' => number_format($exchange_rate, 6, '.', ''),
            'exchange_rate_date' => preg_match($date_pattern, (string) ($data['exchange_rate_date'] ?? '')) ? (string) $data['exchange_rate_date'] : $expense_date,
            'exchange_rate_source' => ($currency === $base) ? 'base' : mb_substr((string) ($data['exchange_rate_source'] ?? 'manual'), 0, 32),
            'net_amount' => $figures['net'],
            'tax_rate' => number_format((float) ($data['tax_rate'] ?? 0), 3, '.', ''),
            'tax_amount' => $figures['tax'],
            'total_amount' => $figures['total'],
            'net_base' => $total_base - $tax_base,
            'tax_base' => $tax_base,
            'total_base' => $total_base,
            'due_date' => preg_match($date_pattern, $due_date) ? $due_date : '0000-00-00',
        );
    }

    $pay = !$paid && !empty($data['pay']);

    if ($pay && ((int) ($data['cash_account_id'] ?? 0) <= 0)) {
        return $fail(lang('Choose a till or bank account.'), 'cash_account_id');
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $sets = array();
    foreach ($columns as $column => $value) {
        $sets[] = $column . " = '" . escape((string) $value) . "'";
    }
    $sets[] = "updated_at = '" . time() . "'";

    if ($id > 0) {
        $ok = erp_query("UPDATE erp_expenses SET " . implode(', ', $sets) . " WHERE id = '" . $id . "'");
    } else {
        $sets[] = "status = 'unpaid'";
        $sets[] = "created_by = '" . (int) $user_id . "'";
        $sets[] = "created_at = '" . time() . "'";
        $ok = erp_query("INSERT INTO erp_expenses SET " . implode(', ', $sets));

        if ($ok !== false) {
            $id = (int) mysqli_insert_id(db::$con);
        }
    }

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail(lang('The expense was not saved.') . ' ' . $error);
    }

    if ($pay) {
        $expense = db_item("SELECT * FROM erp_expenses WHERE id = '" . $id . "' LIMIT 1 FOR UPDATE");
        $paid_date = (string) ($data['paid_date'] ?? '');

        $error = erp_expense_post_payment($expense, array(
            'cash_account_id' => (int) $data['cash_account_id'],
            'payment_method' => (string) ($data['payment_method'] ?? 'cash'),
            'paid_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $paid_date) ? $paid_date : (string) $expense['expense_date'],
        ), $user_id);

        if ($error !== '') {
            erp_tx_rollback();
            return $fail($error, 'cash_account_id');
        }
    }

    // Queued inside the transaction, so a save that rolls back announces
    // nothing; a new expense paid on the spot announces both steps.
    if ($existing === null) {
        erp_event_expense($id, 'erp.expense.created');
    }

    if ($pay) {
        erp_event_expense($id, 'erp.expense.paid');
    }

    if (!erp_tx_commit()) {
        erp_tx_rollback();
        return $fail(lang('The expense was not saved.'));
    }

    return array('success' => true, 'id' => $id, 'error' => '', 'field' => '');
}

/**
 * Pay an expense that was left to pay later.
 *
 * @param int   $id
 * @param array $payment  cash_account_id, paid_date, payment_method
 * @param int   $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_expense_pay($id, $payment, $user_id = 0)
{
    if (!erp_tx_begin()) {
        return array('success' => false, 'error' => lang('Could not start a database transaction.'));
    }

    $expense = db_item("SELECT * FROM erp_expenses WHERE id = '" . (int) $id . "' LIMIT 1 FOR UPDATE");

    if (!is_array($expense) || ((string) $expense['status'] !== 'unpaid')) {
        erp_tx_rollback();
        return array('success' => false, 'error' => is_array($expense) ? lang('That expense is not waiting to be paid.') : lang('The expense could not be found.'));
    }

    $error = erp_expense_post_payment($expense, $payment, $user_id);

    if ($error !== '') {
        erp_tx_rollback();
        return array('success' => false, 'error' => $error);
    }

    erp_event_expense((int) $expense['id'], 'erp.expense.paid');

    if (!erp_tx_commit()) {
        erp_tx_rollback();
        return array('success' => false, 'error' => lang('The payment was not saved.'));
    }

    return array('success' => true, 'error' => '');
}

/**
 * Cancel an expense. A paid one gets its money back into the till with a
 * reversal dated today; neither the expense nor its payment may sit in a
 * locked period.
 *
 * @param int    $id
 * @param string $reason
 * @param int    $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_expense_cancel($id, $reason, $user_id = 0)
{
    $reason = trim((string) $reason);

    if ($reason === '') {
        return array('success' => false, 'error' => lang('The reason is required.'));
    }

    if (!erp_tx_begin()) {
        return array('success' => false, 'error' => lang('Could not start a database transaction.'));
    }

    $fail = function ($message) {
        erp_tx_rollback();
        return array('success' => false, 'error' => $message);
    };

    $expense = db_item("SELECT * FROM erp_expenses WHERE id = '" . (int) $id . "' LIMIT 1 FOR UPDATE");

    if (!is_array($expense)) {
        return $fail(lang('The expense could not be found.'));
    }
    if ((string) $expense['status'] === 'cancelled') {
        return $fail(lang('That expense has already been cancelled.'));
    }
    if (($refusal = erp_lock_refusal((string) $expense['expense_date'])) !== '') {
        return $fail($refusal);
    }

    if ((string) $expense['status'] === 'paid') {
        $movement = db_item("SELECT * FROM erp_cash_transactions WHERE id = '" . (int) $expense['cash_id'] . "' LIMIT 1");

        if (!is_array($movement)) {
            return $fail(lang('The payment of this expense could not be found.'));
        }
        if (($refusal = erp_lock_refusal((string) $movement['doc_date'])) !== '') {
            return $fail($refusal);
        }

        $reversal = erp_cash_post(array(
            'cash_account_id' => (int) $movement['cash_account_id'],
            'doc_date' => date('Y-m-d'),
            'direction' => ((string) $movement['direction'] === 'out') ? 'in' : 'out',
            'amount' => (int) $movement['amount'],
            'amount_base' => (int) $movement['amount_base'],
            'currency' => (string) $movement['currency'],
            'exchange_rate' => (float) $movement['exchange_rate'],
            'exchange_rate_date' => (string) $movement['exchange_rate_date'],
            'exchange_rate_source' => (string) $movement['exchange_rate_source'],
            'account_id' => 0,
            'doc_type' => 'cancel',
            'doc_id' => (int) $movement['id'],
            'payment_method' => (string) $movement['payment_method'],
            'description' => mb_substr(lang(array(
                'string' => 'Cancellation of expense #{var:1}: {var:2}',
                'vars' => array((int) $expense['id'], $reason),
            )), 0, 255),
            'created_by' => (int) $user_id,
        ));

        if ($reversal === false) {
            $error = erp_db_error();
            return $fail(($error !== '') ? $error : lang('The expense could not be cancelled.'));
        }

        if (!erp_cash_refresh_balance((int) $movement['cash_account_id'])) {
            return $fail(erp_db_error());
        }
    }

    if (erp_query("UPDATE erp_expenses SET
            status = 'cancelled',
            cancel_reason = '" . escape(mb_substr($reason, 0, 255)) . "',
            cancelled_at = '" . time() . "',
            cancelled_by = '" . (int) $user_id . "',
            updated_at = '" . time() . "'
        WHERE id = '" . (int) $expense['id'] . "'") === false) {
        return $fail(erp_db_error());
    }

    erp_event_expense((int) $expense['id'], 'erp.expense.cancelled');

    if (!erp_tx_commit()) {
        return $fail(lang('The expense could not be cancelled.'));
    }

    return array('success' => true, 'error' => '');
}

/**
 * What kind of file a receipt is, read from its bytes rather than from its
 * name or from what the browser said.
 *
 * @param string $bytes
 * @return string  'pdf', 'jpg', 'png', 'gif', 'webp', or '' for anything else
 */
function erp_expense_receipt_type($bytes)
{
    $bytes = (string) $bytes;

    if (strncmp($bytes, '%PDF-', 5) === 0) {
        return 'pdf';
    }

    if (!function_exists('getimagesizefromstring')) {
        return '';
    }

    $image = @getimagesizefromstring($bytes);
    $types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif');

    if (defined('IMAGETYPE_WEBP')) {
        $types[IMAGETYPE_WEBP] = 'webp';
    }

    return (is_array($image) && isset($types[$image[2]])) ? $types[$image[2]] : '';
}

/**
 * The content type a kept receipt is served with.
 *
 * @param string $extension
 * @return string
 */
function erp_expense_receipt_mime($extension)
{
    $types = array(
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    );

    return $types[strtolower((string) $extension)] ?? 'application/octet-stream';
}

/**
 * Why the receipt kept with an expense cannot be replaced now, or ''.
 *
 * A receipt that was already kept is replaced only while the expense can
 * still be changed: a cancelled expense is not changed, and one dated in a
 * locked period has gone to the accountant with the file it had.
 *
 * @param array $expense  erp_expenses row
 * @return string
 */
function erp_expense_receipt_refusal($expense)
{
    if ((string) ($expense['status'] ?? '') === 'cancelled') {
        return lang('A cancelled expense is not changed.');
    }

    if (!erp_locked((string) ($expense['expense_date'] ?? ''))) {
        return '';
    }

    return lang(array(
        'string' => 'The books are locked up to {var:1}: this receipt went to the accountant as it is, so its file is not replaced.',
        'vars' => prepare_form_data_for_output(erp_lock_date(), 'date', false),
    ));
}

/**
 * Keep the picture or PDF of an expense's receipt.
 *
 * One file stands for the expense. A second one is only taken in its place
 * when asked ($replace): the earlier file is not removed but stays with the
 * expense, listed as replaced, so what the books were shown before can
 * still be seen.
 *
 * @param int    $id
 * @param string $bytes
 * @param int    $user_id
 * @param bool   $replace  Take this file in place of the one kept
 * @return array ['success' => bool, 'error' => string, 'replaced' => bool, 'code' => string]
 *               code: '' | 'not_found' | 'not_ready' | 'exists' | 'refused' | 'invalid' | 'disk'
 */
function erp_expense_keep_receipt($id, $bytes, $user_id = 0, $replace = false)
{
    $fail = function ($message, $code) {
        return array('success' => false, 'error' => $message, 'replaced' => false, 'code' => $code);
    };

    $expense = erp_expense($id);

    if ($expense === null) {
        return $fail(lang('The expense could not be found.'), 'not_found');
    }
    if (!function_exists('erp_archive_ready') || !erp_archive_ready()) {
        return $fail(lang('Files can be kept once the software update has been run.'), 'not_ready');
    }

    $existing = erp_archive_file('expense', (int) $id);

    if (($existing !== null) && !$replace) {
        return $fail(lang('This expense already has its receipt.'), 'exists');
    }

    if (($existing !== null) && (($refusal = erp_expense_receipt_refusal($expense)) !== '')) {
        return $fail($refusal, 'refused');
    }

    $bytes = (string) $bytes;
    $size = strlen($bytes);

    if (($size <= 0) || ($size > ERP_EXPENSE_FILE_MAX_BYTES)) {
        return $fail(lang(array('string' => 'The file can be at most {var:1} MB.', 'vars' => (int) (ERP_EXPENSE_FILE_MAX_BYTES / 1048576))), 'invalid');
    }

    $extension = erp_expense_receipt_type($bytes);

    if ($extension === '') {
        return $fail(lang('Choose a picture (JPG, PNG, WEBP) or a PDF of the receipt.'), 'invalid');
    }

    $label = ((string) $expense['document_no'] !== '') ? (string) $expense['document_no'] : ('G' . (int) $id);
    $kept = erp_archive_store('expense', (int) $id, $bytes, $label, $extension, (int) $user_id, $existing !== null);

    if (($kept === null) || (($existing !== null) && ((int) $kept['id'] === (int) $existing['id']))) {
        return $fail(lang('The file could not be kept.'), 'disk');
    }

    return array('success' => true, 'error' => '', 'replaced' => ($existing !== null), 'code' => '');
}

/**
 * Keep the receipt a form uploaded.
 *
 * @param int   $id
 * @param array $upload   One entry of $_FILES
 * @param int   $user_id
 * @param bool  $replace  Take it in place of the file already kept
 * @return array ['success' => bool, 'error' => string, 'replaced' => bool, 'code' => string]
 */
function erp_expense_attach($id, $upload, $user_id = 0, $replace = false)
{
    if (!is_array($upload) || ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        $code = is_array($upload) ? (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        return array(
            'success' => false,
            'error' => in_array($code, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)
                ? lang('The file is larger than the server accepts.')
                : lang('Choose a picture or a PDF of the receipt.'),
            'replaced' => false,
            'code' => 'invalid',
        );
    }

    // Measured before it is read: a file far over the limit is refused
    // without taking its size in memory.
    $size = (int) filesize((string) $upload['tmp_name']);

    if (($size <= 0) || ($size > ERP_EXPENSE_FILE_MAX_BYTES)) {
        return array(
            'success' => false,
            'error' => lang(array('string' => 'The file can be at most {var:1} MB.', 'vars' => (int) (ERP_EXPENSE_FILE_MAX_BYTES / 1048576))),
            'replaced' => false,
            'code' => 'invalid',
        );
    }

    return erp_expense_keep_receipt($id, (string) file_get_contents((string) $upload['tmp_name']), $user_id, $replace);
}

/**
 * Every file kept for an expense's receipt, newest first: the one in use,
 * then the ones it replaced.
 *
 * @param int $id
 * @return array  From erp_archive_files()
 */
function erp_expense_receipts($id)
{
    return function_exists('erp_archive_files') ? erp_archive_files('expense', (int) $id) : array();
}

/**
 * The expense a till movement pays or cancels, for the till book.
 *
 * @param array $movement  erp_cash_transactions row
 * @return int  Expense id, or 0
 */
function erp_expense_of_movement($movement)
{
    return ((string) ($movement['doc_type'] ?? '') === 'expense') ? (int) $movement['doc_id'] : 0;
}
