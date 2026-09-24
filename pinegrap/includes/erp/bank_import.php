<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - bank statements.
 *
 * The statement a bank lets its customers download, taken in line by line
 * and matched against the books: a line the till already has (the same
 * amount, the same way, within a few days) is matched to that movement; the
 * rest are recorded from the statement screen - as a collection or a payment
 * on an account, closing an invoice when one is named, or as an expense paid
 * from the bank - or set aside. Nothing is written to the books until the
 * operator says so.
 *
 * Banks export in their own layout, so the columns are chosen once per bank
 * account and remembered (erp_bank_statements.mapping). A line is known by a
 * hash of its day, amount and words, so a statement taken in twice, or two
 * that overlap, add each line once.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

if (!defined('ERP_BANK_IMPORT_MAX_ROWS')) {
    define('ERP_BANK_IMPORT_MAX_ROWS', 3000);
}

/**
 * Whether the 4.102 tables are there.
 *
 * @return bool
 */
function erp_bank_import_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_bank_statements', 'mapping')
            && waf_table_has_column('erp_bank_statement_lines', 'line_hash');
    }

    return $ready;
}

/**
 * @return array  status => [label, badge colour]
 */
function erp_bank_line_statuses()
{
    return array(
        'new' => array(lang('To do'), 'warning'),
        'matched' => array(lang('Matched'), 'success'),
        'recorded' => array(lang('Recorded'), 'primary'),
        'ignored' => array(lang('Put aside'), 'secondary'),
    );
}

/**
 * Read an uploaded CSV into rows of cells: the separator is guessed from the
 * first lines, and a file that is not UTF-8 is read as Windows-1254, the way
 * Turkish banks save it.
 *
 * @param string $path
 * @return array ['rows' => array, 'error' => string]
 */
function erp_bank_read_csv($path)
{
    $text = @file_get_contents($path);

    if (($text === false) || ($text === '')) {
        return array('rows' => array(), 'error' => lang('The file could not be read.'));
    }

    if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
        $text = substr($text, 3);
    }

    if (!mb_check_encoding($text, 'UTF-8')) {
        $converted = function_exists('iconv') ? @iconv('Windows-1254', 'UTF-8//IGNORE', $text) : false;
        $text = ($converted !== false) ? $converted : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-9');
    }

    $lines = preg_split('/\r\n|\r|\n/', $text);
    $sample = implode("\n", array_slice($lines, 0, 10));
    $separator = ';';
    $best = -1;

    foreach (array(';', ',', "\t", '|') as $candidate) {
        $count = substr_count($sample, $candidate);
        if ($count > $best) {
            $best = $count;
            $separator = $candidate;
        }
    }

    $rows = array();
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }

        $rows[] = array_map('trim', str_getcsv($line, $separator, '"'));

        if (count($rows) >= ERP_BANK_IMPORT_MAX_ROWS) {
            break;
        }
    }

    if (empty($rows)) {
        return array('rows' => array(), 'error' => lang('The file has no lines.'));
    }

    return array('rows' => $rows, 'error' => '');
}

/**
 * A date as a bank writes it: 23.09.2026, 23/09/2026, 2026-09-23, with or
 * without a time after it. A day-first date is read month-first when the
 * bank writes them that way (US banks).
 *
 * @param string $text
 * @param string $order  dmy | mdy
 * @return string  Y-m-d, or '' when it is not a date
 */
function erp_bank_date($text, $order = 'dmy')
{
    $text = trim((string) $text);

    if (preg_match('/^(\d{4})[-.\/](\d{1,2})[-.\/](\d{1,2})/', $text, $m)) {
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : '';
    }

    if (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{2,4})/', $text, $m)) {
        $year = (int) $m[3];
        $year = ($year < 100) ? 2000 + $year : $year;
        $day = ($order === 'mdy') ? (int) $m[2] : (int) $m[1];
        $month = ($order === 'mdy') ? (int) $m[1] : (int) $m[2];

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    return '';
}

/**
 * An amount as a bank writes it: 1.234,56 or 1,234.56, a minus in front or a
 * trailing one, in parentheses, with a currency word around it.
 *
 * @param string $text
 * @return int|null  kurus, signed; null when it is not a number
 */
function erp_bank_amount($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return null;
    }

    $negative = (bool) preg_match('/^\(.*\)$|^-|-$|^−/u', $text);
    $digits = preg_replace('/[^0-9.,]/', '', $text);

    if ($digits === '') {
        return null;
    }

    $at = max(strrpos($digits, ','), strrpos($digits, '.'));
    if (($at !== false) && ((strlen($digits) - $at - 1) <= 2) && (strlen($digits) - $at - 1 > 0)) {
        $whole = preg_replace('/[.,]/', '', substr($digits, 0, $at));
        $fraction = str_pad(substr($digits, $at + 1), 2, '0');
    } else {
        $whole = preg_replace('/[.,]/', '', $digits);
        $fraction = '00';
    }

    $kurus = ((int) $whole) * 100 + (int) $fraction;

    return $negative ? -$kurus : $kurus;
}

/**
 * Take a file in for a bank account: the statement row, with its cells kept
 * until the columns are chosen.
 *
 * @param int    $cash_account_id
 * @param string $path
 * @param string $file_name
 * @param int    $user_id
 * @return array ['success' => bool, 'id' => int, 'error' => string]
 */
function erp_bank_statement_upload($cash_account_id, $path, $file_name, $user_id)
{
    if (!erp_bank_import_ready()) {
        return array('success' => false, 'id' => 0, 'error' => lang('Bank statements come with the software update; run the update to use them.'));
    }

    $till = db_item("SELECT id FROM erp_cash_accounts WHERE id = '" . (int) $cash_account_id . "' AND is_active = 1 LIMIT 1");

    if (!is_array($till)) {
        return array('success' => false, 'id' => 0, 'error' => lang('Choose the bank account.'));
    }

    $read = erp_bank_read_csv($path);

    if ($read['error'] !== '') {
        return array('success' => false, 'id' => 0, 'error' => $read['error']);
    }

    // The columns chosen for this bank account last time are offered again.
    $last = db_value("SELECT mapping FROM erp_bank_statements WHERE cash_account_id = '" . (int) $cash_account_id . "' AND mapping IS NOT NULL ORDER BY id DESC LIMIT 1");

    $ok = db("INSERT INTO erp_bank_statements SET
            cash_account_id = '" . (int) $cash_account_id . "',
            file_name = '" . escape(mb_substr(basename((string) $file_name), 0, 150)) . "',
            status = 'mapping',
            raw_rows = '" . escape(json_encode($read['rows'], JSON_UNESCAPED_UNICODE)) . "',
            mapping = " . (is_string($last) && ($last !== '') ? "'" . escape($last) . "'" : "NULL") . ",
            created_by = '" . (int) $user_id . "',
            created_at = UNIX_TIMESTAMP()");

    return ($ok === false)
        ? array('success' => false, 'id' => 0, 'error' => erp_db_error())
        : array('success' => true, 'id' => (int) mysqli_insert_id(db::$con), 'error' => '');
}

/**
 * One statement with its bank account.
 *
 * @param int $id
 * @return array|null
 */
function erp_bank_statement($id)
{
    if (!erp_bank_import_ready()) {
        return null;
    }

    $row = db_item("SELECT s.*, c.name AS till_name, c.currency AS till_currency
        FROM erp_bank_statements s
        LEFT JOIN erp_cash_accounts c ON c.id = s.cash_account_id
        WHERE s.id = '" . (int) $id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * Turn the kept cells into lines with the columns chosen, matching each to
 * the till where the till already has it.
 *
 * @param int   $statement_id
 * @param array $mapping  header (bool), date_order (dmy | mdy), date, description,
 *                        amount | debit + credit (column numbers, -1 for none)
 * @return array ['success' => bool, 'added' => int, 'skipped' => int, 'matched' => int, 'error' => string]
 */
function erp_bank_statement_build($statement_id, $mapping)
{
    $fail = function ($message) {
        return array('success' => false, 'added' => 0, 'skipped' => 0, 'matched' => 0, 'error' => $message);
    };

    $statement = erp_bank_statement($statement_id);

    if (($statement === null) || ((string) $statement['status'] !== 'mapping')) {
        return $fail(lang('The statement could not be found.'));
    }

    $column = function ($name) use ($mapping) {
        return isset($mapping[$name]) ? (int) $mapping[$name] : -1;
    };

    if (($column('date') < 0) || (($column('amount') < 0) && ($column('debit') < 0) && ($column('credit') < 0))) {
        return $fail(lang('Choose the date column and the amount column (or the debit and credit columns).'));
    }

    $rows = json_decode((string) $statement['raw_rows'], true);
    $rows = is_array($rows) ? $rows : array();

    if (!empty($mapping['header'])) {
        array_shift($rows);
    }

    $added = 0;
    $skipped = 0;
    $line_no = 0;
    $till_id = (int) $statement['cash_account_id'];

    foreach ($rows as $cells) {
        $cells = (array) $cells;
        $date = erp_bank_date($cells[$column('date')] ?? '', ((string) ($mapping['date_order'] ?? 'dmy') === 'mdy') ? 'mdy' : 'dmy');

        if ($date === '') {
            $skipped++;
            continue;
        }

        if ($column('amount') >= 0) {
            $amount = erp_bank_amount($cells[$column('amount')] ?? '');
        } else {
            $debit = erp_bank_amount($cells[$column('debit')] ?? '');
            $credit = erp_bank_amount($cells[$column('credit')] ?? '');
            $amount = (int) abs((int) $credit) - (int) abs((int) $debit);
            $amount = (($debit === null) && ($credit === null)) ? null : $amount;
        }

        if (($amount === null) || ($amount === 0)) {
            $skipped++;
            continue;
        }

        $description = ($column('description') >= 0) ? mb_substr(trim((string) ($cells[$column('description')] ?? '')), 0, 255) : '';
        $hash = sha1($till_id . '|' . $date . '|' . $amount . '|' . mb_strtolower($description));
        $line_no++;

        $ok = db("INSERT IGNORE INTO erp_bank_statement_lines SET
                statement_id = '" . (int) $statement['id'] . "',
                cash_account_id = '" . $till_id . "',
                line_no = '" . $line_no . "',
                doc_date = '" . escape($date) . "',
                description = '" . escape($description) . "',
                amount = '" . (int) $amount . "',
                status = 'new',
                line_hash = '" . escape($hash) . "'");

        if (($ok !== false) && (mysqli_affected_rows(db::$con) === 1)) {
            $added++;
        } else {
            $skipped++;
        }
    }

    db("UPDATE erp_bank_statements SET status = 'open', raw_rows = NULL,
            mapping = '" . escape(json_encode($mapping)) . "',
            line_count = '" . $added . "'
        WHERE id = '" . (int) $statement['id'] . "'");

    $matched = erp_bank_statement_match((int) $statement['id']);

    return array('success' => true, 'added' => $added, 'skipped' => $skipped, 'matched' => $matched, 'error' => '');
}

/**
 * Match the statement's open lines to the till's movements: the same amount,
 * the same way, within three days, and not matched to another line yet.
 *
 * @param int $statement_id
 * @return int  Lines matched
 */
function erp_bank_statement_match($statement_id)
{
    $matched = 0;

    foreach ((array) db_items("SELECT id, cash_account_id, doc_date, amount FROM erp_bank_statement_lines
        WHERE statement_id = '" . (int) $statement_id . "' AND status = 'new'") as $line) {

        $cash_id = (int) db_value("SELECT t.id FROM erp_cash_transactions t
            WHERE t.cash_account_id = '" . (int) $line['cash_account_id'] . "'
              AND t.direction = '" . (((int) $line['amount'] > 0) ? 'in' : 'out') . "'
              AND t.amount = '" . abs((int) $line['amount']) . "'
              AND t.doc_date BETWEEN DATE_SUB('" . escape($line['doc_date']) . "', INTERVAL 3 DAY) AND DATE_ADD('" . escape($line['doc_date']) . "', INTERVAL 3 DAY)
              AND NOT EXISTS (SELECT 1 FROM erp_bank_statement_lines l WHERE l.cash_id = t.id)
            ORDER BY ABS(DATEDIFF(t.doc_date, '" . escape($line['doc_date']) . "')) ASC, t.id ASC
            LIMIT 1");

        if ($cash_id > 0) {
            db("UPDATE erp_bank_statement_lines SET status = 'matched', cash_id = '" . $cash_id . "' WHERE id = '" . (int) $line['id'] . "' AND status = 'new'");
            $matched++;
        }
    }

    return $matched;
}

/**
 * A statement's lines, with the movement each one is matched or recorded as.
 *
 * @param int $statement_id
 * @return array
 */
function erp_bank_statement_lines($statement_id)
{
    return (array) db_items("SELECT l.*, t.description AS cash_description, t.doc_type AS cash_doc_type, a.title AS account_title
        FROM erp_bank_statement_lines l
        LEFT JOIN erp_cash_transactions t ON t.id = l.cash_id
        LEFT JOIN erp_accounts a ON a.id = t.account_id
        WHERE l.statement_id = '" . (int) $statement_id . "'
        ORDER BY l.doc_date ASC, l.line_no ASC");
}

/**
 * Who an unmatched line is likely from: an account whose tax number, or
 * whose name, the bank wrote in the line; failing that, the one open invoice
 * the amount closes exactly.
 *
 * @param array $line
 * @return array ['account_id' => int, 'invoice_id' => int, 'why' => string]
 */
function erp_bank_line_suggestion($line)
{
    static $accounts = null;

    if ($accounts === null) {
        $accounts = (array) db_items("SELECT id, title, tax_number FROM erp_accounts WHERE status = 'active'");
    }

    $words = mb_strtolower((string) $line['description']);
    $none = array('account_id' => 0, 'invoice_id' => 0, 'why' => '');

    if ($words !== '') {
        foreach ($accounts as $account) {
            $tax = preg_replace('/\D/', '', (string) $account['tax_number']);
            if ((strlen($tax) >= 10) && (strpos(preg_replace('/\D/', '', $words), $tax) !== false)) {
                return array('account_id' => (int) $account['id'], 'invoice_id' => 0, 'why' => lang('tax number in the line'));
            }
        }

        foreach ($accounts as $account) {
            $title = mb_strtolower(trim((string) $account['title']));
            if ((mb_strlen($title) >= 6) && (mb_strpos($words, $title) !== false)) {
                return array('account_id' => (int) $account['id'], 'invoice_id' => 0, 'why' => lang('name in the line'));
            }
        }
    }

    $amount = abs((int) $line['amount']);
    $direction = ((int) $line['amount'] > 0) ? 'sales' : 'purchase';
    $open = (array) db_items("SELECT id, account_id FROM erp_invoices
        WHERE direction = '" . $direction . "' AND status IN ('issued', 'partially_paid') AND (grand_total - paid_total) = '" . $amount . "'
        LIMIT 2");

    if (count($open) === 1) {
        return array('account_id' => (int) $open[0]['account_id'], 'invoice_id' => (int) $open[0]['id'], 'why' => lang('the one open invoice of this amount'));
    }

    return $none;
}

/**
 * Record a line in the books: a collection or payment on an account, or an
 * expense paid from the bank.
 *
 * @param int   $line_id
 * @param array $what     kind (account | expense), account_id, invoice_id, category_id
 * @param int   $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_bank_line_record($line_id, $what, $user_id)
{
    $line = db_item("SELECT l.*, c.currency AS till_currency FROM erp_bank_statement_lines l
        LEFT JOIN erp_cash_accounts c ON c.id = l.cash_account_id
        WHERE l.id = '" . (int) $line_id . "' LIMIT 1");

    if (!is_array($line) || ((string) $line['status'] !== 'new')) {
        return array('success' => false, 'error' => lang('That line has already been dealt with.'));
    }

    $amount = abs((int) $line['amount']);
    $base = erp_base_currency();
    $currency = (erp_fx_enabled() && ((string) $line['till_currency'] !== '')) ? strtoupper((string) $line['till_currency']) : $base;
    $rate = array('rate' => 1.0, 'rate_date' => (string) $line['doc_date'], 'source' => 'base');

    if ($currency !== $base) {
        $known = erp_fx_rate_for($currency, (string) $line['doc_date']);
        if (!is_array($known)) {
            return array('success' => false, 'error' => lang(array('string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.', 'vars' => array($currency, prepare_form_data_for_output((string) $line['doc_date'], 'date')))));
        }
        $rate = array('rate' => (float) $known['rate'], 'rate_date' => (string) $known['rate_date'], 'source' => (string) $known['source']);
    }

    $description = mb_substr(((string) $line['description'] !== '') ? (string) $line['description'] : lang('Bank statement line'), 0, 255);

    if ((string) ($what['kind'] ?? 'account') === 'expense') {
        if ((int) $line['amount'] > 0) {
            return array('success' => false, 'error' => lang('Money coming in is not an expense.'));
        }

        $saved = erp_expense_save(array(
            'expense_date' => (string) $line['doc_date'],
            'category_id' => (int) ($what['category_id'] ?? 0),
            'supplier' => '',
            'supplier_tax_number' => '',
            'document_no' => '',
            'description' => $description,
            'currency' => $currency,
            'exchange_rate' => $rate['rate'],
            'exchange_rate_date' => $rate['rate_date'],
            'exchange_rate_source' => $rate['source'],
            'amount' => $amount,
            'includes_tax' => true,
            'tax_rate' => 0,
            'tax_amount' => null,
            'tax_deductible' => false,
            'due_date' => '',
            'pay' => true,
            'cash_account_id' => (int) $line['cash_account_id'],
            'payment_method' => 'transfer',
            'paid_date' => (string) $line['doc_date'],
        ), (int) $user_id);

        if (empty($saved['success'])) {
            return array('success' => false, 'error' => (string) $saved['error']);
        }

        $cash_id = (int) db_value("SELECT cash_id FROM erp_expenses WHERE id = '" . (int) $saved['id'] . "' LIMIT 1");
    } else {
        $posted = erp_post_receipt(array(
            'direction' => ((int) $line['amount'] > 0) ? 'collection' : 'payment',
            'account_id' => (int) ($what['account_id'] ?? 0),
            'cash_account_id' => (int) $line['cash_account_id'],
            'amount' => $amount,
            'doc_date' => (string) $line['doc_date'],
            'currency' => $currency,
            'exchange_rate' => $rate['rate'],
            'exchange_rate_date' => $rate['rate_date'],
            'exchange_rate_source' => $rate['source'],
            'payment_method' => 'transfer',
            'description' => $description,
            'invoice_id' => (int) ($what['invoice_id'] ?? 0),
            'created_by' => (int) $user_id,
        ));

        if (empty($posted['success'])) {
            return array('success' => false, 'error' => (string) $posted['error']);
        }

        $cash_id = (int) $posted['cash_id'];
    }

    db("UPDATE erp_bank_statement_lines SET status = 'recorded', cash_id = '" . $cash_id . "' WHERE id = '" . (int) $line['id'] . "'");

    return array('success' => true, 'error' => '');
}

/**
 * Set a line aside, or bring it back.
 *
 * @param int  $line_id
 * @param bool $ignore
 * @return void
 */
function erp_bank_line_ignore($line_id, $ignore = true)
{
    db("UPDATE erp_bank_statement_lines SET status = '" . ($ignore ? 'ignored' : 'new') . "'
        WHERE id = '" . (int) $line_id . "' AND status = '" . ($ignore ? 'new' : 'ignored') . "'");
}

/**
 * The statements taken in, newest first, with how many lines are left.
 *
 * @return array
 */
function erp_bank_statements_list()
{
    if (!erp_bank_import_ready()) {
        return array();
    }

    return (array) db_items("SELECT s.id, s.cash_account_id, s.file_name, s.status, s.line_count, s.created_at, c.name AS till_name,
            (SELECT COUNT(*) FROM erp_bank_statement_lines l WHERE l.statement_id = s.id AND l.status = 'new') AS open_lines
        FROM erp_bank_statements s
        LEFT JOIN erp_cash_accounts c ON c.id = s.cash_account_id
        ORDER BY s.id DESC
        LIMIT 200");
}
