<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - cheques and promissory notes.
 *
 * A customer's cheque or note is money that arrives later: it is recorded as
 * a collection into a portfolio (a till the store keeps for them), so the
 * customer's debt comes down the day it is taken, and it waits there until
 * it is paid in, passed on or bounces. A cheque or note the store gives a
 * supplier is a payment out of a till for given cheques, and it is settled
 * when the bank pays it. Every step moves money through the ledger's own
 * doors - erp_post_receipt() and erp_post_transfer() - so the tills, the
 * accounts, the lock and the audit trail see it the way they see any
 * receipt:
 *
 *   received  portfolio -> deposited (handed to the bank, no money moves)
 *             portfolio | deposited -> collected: portfolio till to the bank
 *             portfolio -> endorsed: a payment to a supplier out of the portfolio
 *             portfolio | deposited -> bounced: a payment back to the customer
 *                                       out of the portfolio, so they owe it again
 *   given     given -> paid: the bank to the given-cheques till
 *
 * A step is dated on its own day and never rewrites the movement before it,
 * so a period locked in between stays as it was filed.
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
 * Whether the 4.101 table is there.
 *
 * @return bool
 */
function erp_cheques_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_cheques', 'close_cash_id');
    }

    return $ready;
}

/**
 * @return array  status => [label, badge colour]
 */
function erp_cheque_statuses()
{
    return array(
        'portfolio' => array(lang('In the portfolio'), 'primary'),
        'deposited' => array(lang('At the bank'), 'info'),
        'collected' => array(lang('Collected'), 'success'),
        'endorsed' => array(lang('Passed on'), 'secondary'),
        'bounced' => array(lang('Bounced'), 'danger'),
        'given' => array(lang('Given'), 'warning'),
        'paid' => array(lang('Paid'), 'success'),
    );
}

/**
 * @return array  kind => label
 */
function erp_cheque_kinds()
{
    return array(
        'cheque' => lang('Cheque'),
        'note' => lang('Promissory note'),
    );
}

/**
 * One cheque or note, with its account and tills.
 *
 * @param int $id
 * @return array|null
 */
function erp_cheque($id)
{
    if (!erp_cheques_ready()) {
        return null;
    }

    $row = db_item("SELECT c.*, a.title AS account_title, e.title AS endorsed_title,
            p.name AS portfolio_name, b.name AS bank_till_name
        FROM erp_cheques c
        LEFT JOIN erp_accounts a ON a.id = c.account_id
        LEFT JOIN erp_accounts e ON e.id = c.endorsed_account_id
        LEFT JOIN erp_cash_accounts p ON p.id = c.portfolio_till_id
        LEFT JOIN erp_cash_accounts b ON b.id = c.bank_till_id
        WHERE c.id = '" . (int) $id . "' LIMIT 1");

    return is_array($row) ? $row : null;
}

/**
 * The register, soonest due first.
 *
 * @param array $filters  direction ('received'|'given'|''), status, search
 * @return array
 */
function erp_cheque_rows($filters = array())
{
    if (!erp_cheques_ready()) {
        return array();
    }

    $where = array('1 = 1');

    if (in_array((string) ($filters['direction'] ?? ''), array('received', 'given'), true)) {
        $where[] = "c.direction = '" . escape((string) $filters['direction']) . "'";
    }

    $status = (string) ($filters['status'] ?? '');
    if ($status === 'open') {
        $where[] = "c.status IN ('portfolio', 'deposited', 'given')";
    } elseif (array_key_exists($status, erp_cheque_statuses())) {
        $where[] = "c.status = '" . escape($status) . "'";
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $like = "'%" . escape(addcslashes($search, '%_\\')) . "%'";
        $where[] = "(c.serial_no LIKE " . $like . " OR c.drawer LIKE " . $like . " OR c.bank_name LIKE " . $like . " OR a.title LIKE " . $like . ")";
    }

    return (array) db_items("SELECT c.*, a.title AS account_title
        FROM erp_cheques c
        LEFT JOIN erp_accounts a ON a.id = c.account_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY (c.status IN ('portfolio', 'deposited', 'given')) DESC, c.due_date ASC, c.id DESC
        LIMIT 500");
}

/**
 * What is still open: in the portfolio or at the bank, and given out.
 *
 * @return array ['received' => [count, amount], 'given' => [count, amount], 'due_week' => count]
 */
function erp_cheque_totals()
{
    $out = array('received' => array(0, 0), 'given' => array(0, 0), 'due_week' => 0);

    if (!erp_cheques_ready()) {
        return $out;
    }

    foreach ((array) db_items("SELECT direction, COUNT(*) AS count, COALESCE(SUM(amount_base), 0) AS amount
        FROM erp_cheques WHERE status IN ('portfolio', 'deposited', 'given') GROUP BY direction") as $row) {
        $out[(string) $row['direction']] = array((int) $row['count'], (int) $row['amount']);
    }

    $out['due_week'] = (int) db_value("SELECT COUNT(*) FROM erp_cheques
        WHERE status IN ('portfolio', 'deposited', 'given') AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");

    return $out;
}

/**
 * The rate of a till's currency on a day, for a movement in it.
 *
 * @param int    $till_id
 * @param string $date
 * @return array ['currency', 'rate', 'rate_date', 'source', 'error']
 */
function erp_cheque_till_rate($till_id, $date)
{
    $base = erp_base_currency();
    $currency = erp_fx_enabled() ? strtoupper(trim((string) db_value("SELECT currency FROM erp_cash_accounts WHERE id = '" . (int) $till_id . "' LIMIT 1"))) : $base;
    $currency = ($currency !== '') ? $currency : $base;

    if ($currency === $base) {
        return array('currency' => $base, 'rate' => 1.0, 'rate_date' => $date, 'source' => 'base', 'error' => '');
    }

    $known = erp_fx_rate_for($currency, $date);

    if (!is_array($known)) {
        return array('currency' => $currency, 'rate' => 0.0, 'rate_date' => $date, 'source' => '', 'error' => lang(array(
            'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
            'vars' => array($currency, prepare_form_data_for_output($date, 'date')),
        )));
    }

    return array('currency' => $currency, 'rate' => (float) $known['rate'], 'rate_date' => (string) $known['rate_date'], 'source' => (string) $known['source'], 'error' => '');
}

/**
 * Record a cheque or note: taken from a customer into the portfolio, or
 * given to a supplier out of the given-cheques till.
 *
 * @param array $data  direction, kind, account_id, amount (kurus), doc_date, due_date,
 *                     serial_no, bank_name, branch, drawer, till_id, notes
 * @param int   $user_id
 * @return array ['success' => bool, 'id' => int, 'error' => string, 'field' => string]
 */
function erp_cheque_create($data, $user_id)
{
    $fail = function ($message, $field = '_error') {
        return array('success' => false, 'id' => 0, 'error' => $message, 'field' => $field);
    };

    if (!erp_cheques_ready()) {
        return $fail(lang('Cheques and notes come with the software update; run the update to use them.'));
    }

    $direction = ((string) ($data['direction'] ?? '') === 'given') ? 'given' : 'received';
    $kind = ((string) ($data['kind'] ?? '') === 'note') ? 'note' : 'cheque';
    $amount = (int) ($data['amount'] ?? 0);
    $doc_date = (string) ($data['doc_date'] ?? '');
    $due_date = (string) ($data['due_date'] ?? '');
    $till_id = (int) ($data['till_id'] ?? 0);

    if ($amount <= 0) {
        return $fail(lang('Enter an amount greater than zero.'), 'amount');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_date)) {
        return $fail(lang('Please enter a valid date.'), 'doc_date');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
        return $fail(lang('Enter the date it falls due.'), 'due_date');
    }
    if ($till_id <= 0) {
        return $fail(lang('Choose where it is kept.'), 'till_id');
    }

    $rate = erp_cheque_till_rate($till_id, $doc_date);
    if ($rate['error'] !== '') {
        return $fail($rate['error']);
    }

    $kinds = erp_cheque_kinds();
    $serial = mb_substr(trim((string) ($data['serial_no'] ?? '')), 0, 40);
    $description = trim($kinds[$kind] . ' ' . $serial) . ' · ' . lang(array('string' => 'due {var:1}', 'vars' => prepare_form_data_for_output($due_date, 'date', false)));

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    $posted = erp_post_receipt(array(
        'direction' => ($direction === 'received') ? 'collection' : 'payment',
        'account_id' => (int) ($data['account_id'] ?? 0),
        'cash_account_id' => $till_id,
        'amount' => $amount,
        'doc_date' => $doc_date,
        'currency' => $rate['currency'],
        'exchange_rate' => $rate['rate'],
        'exchange_rate_date' => $rate['rate_date'],
        'exchange_rate_source' => $rate['source'],
        'payment_method' => ($kind === 'cheque') ? 'cheque' : 'other',
        'description' => mb_substr($description, 0, 255),
        'created_by' => (int) $user_id,
    ));

    if (empty($posted['success'])) {
        erp_tx_rollback();
        return $fail((string) $posted['error']);
    }

    $ok = erp_query("INSERT INTO erp_cheques SET
            kind = '" . $kind . "',
            direction = '" . $direction . "',
            account_id = '" . (int) $data['account_id'] . "',
            amount = '" . $amount . "',
            currency = '" . escape($rate['currency']) . "',
            amount_base = '" . (($rate['currency'] === erp_base_currency()) ? $amount : erp_to_base($amount, $rate['rate'])) . "',
            doc_date = '" . escape($doc_date) . "',
            due_date = '" . escape($due_date) . "',
            serial_no = '" . escape($serial) . "',
            bank_name = '" . escape(mb_substr(trim((string) ($data['bank_name'] ?? '')), 0, 100)) . "',
            branch = '" . escape(mb_substr(trim((string) ($data['branch'] ?? '')), 0, 100)) . "',
            drawer = '" . escape(mb_substr(trim((string) ($data['drawer'] ?? '')), 0, 150)) . "',
            status = '" . (($direction === 'received') ? 'portfolio' : 'given') . "',
            portfolio_till_id = '" . $till_id . "',
            open_cash_id = '" . (int) $posted['cash_id'] . "',
            notes = '" . escape(mb_substr(trim((string) ($data['notes'] ?? '')), 0, 255)) . "',
            created_by = '" . (int) $user_id . "',
            created_at = UNIX_TIMESTAMP(),
            updated_at = UNIX_TIMESTAMP()");

    if ($ok === false) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    $id = (int) mysqli_insert_id(db::$con);

    if (!erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'id' => $id, 'error' => '', 'field' => '');
}

/**
 * The next step of a cheque or note.
 *
 * @param int    $id
 * @param string $step     deposit | collect | endorse | bounce | pay
 * @param array  $options  date, bank_till_id, account_id (endorse), reason
 * @param int    $user_id
 * @return array ['success' => bool, 'error' => string]
 */
function erp_cheque_step($id, $step, $options, $user_id)
{
    $fail = function ($message) {
        return array('success' => false, 'error' => $message);
    };

    $cheque = erp_cheque($id);

    if ($cheque === null) {
        return $fail(lang('The cheque or note could not be found.'));
    }

    $status = (string) $cheque['status'];
    $allowed = array(
        'deposit' => array('portfolio'),
        'collect' => array('portfolio', 'deposited'),
        'endorse' => array('portfolio'),
        'bounce' => array('portfolio', 'deposited'),
        'pay' => array('given'),
    );

    if (!in_array($status, $allowed[$step] ?? array(), true)) {
        return $fail(lang('It cannot take that step from where it is now.'));
    }

    $date = (string) ($options['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $fail(lang('Please enter a valid date.'));
    }

    $kinds = erp_cheque_kinds();
    $name = trim($kinds[(string) $cheque['kind']] . ' ' . (string) $cheque['serial_no']);
    $bank_till = (int) ($options['bank_till_id'] ?? 0);

    if (in_array($step, array('deposit', 'collect', 'pay'), true) && ($bank_till <= 0)) {
        $bank_till = (int) $cheque['bank_till_id'];
    }

    if (in_array($step, array('deposit', 'collect', 'pay'), true) && ($bank_till <= 0)) {
        return $fail(lang('Choose the bank account.'));
    }

    if ($step === 'deposit') {
        db("UPDATE erp_cheques SET status = 'deposited', bank_till_id = '" . $bank_till . "', updated_at = UNIX_TIMESTAMP()
            WHERE id = '" . (int) $cheque['id'] . "' AND status = 'portfolio'");

        return (mysqli_affected_rows(db::$con) === 1) ? array('success' => true, 'error' => '') : $fail(lang('It cannot take that step from where it is now.'));
    }

    if (!erp_tx_begin()) {
        return $fail(lang('Could not start a database transaction.'));
    }

    // Claimed first: a second click, or a second screen, takes no step.
    $claimed = db_item("SELECT id FROM erp_cheques WHERE id = '" . (int) $cheque['id'] . "' AND status = '" . escape($status) . "' LIMIT 1 FOR UPDATE");

    if (!is_array($claimed)) {
        erp_tx_rollback();
        return $fail(lang('It cannot take that step from where it is now.'));
    }

    $close_id = 0;
    $endorsed = 0;

    if (($step === 'collect') || ($step === 'pay')) {
        $moved = erp_post_transfer(array(
            'from_id' => ($step === 'collect') ? (int) $cheque['portfolio_till_id'] : $bank_till,
            'to_id' => ($step === 'collect') ? $bank_till : (int) $cheque['portfolio_till_id'],
            'amount' => (int) $cheque['amount'],
            'doc_date' => $date,
            'description' => mb_substr((($step === 'collect') ? lang(array('string' => '{var:1} collected', 'vars' => $name)) : lang(array('string' => '{var:1} paid by the bank', 'vars' => $name))), 0, 255),
            'created_by' => (int) $user_id,
        ));

        if (empty($moved['success'])) {
            erp_tx_rollback();
            return $fail((string) $moved['error']);
        }

        $close_id = (int) $moved['in_id'];
    } else {
        // Passed on to a supplier, or back to the customer: money out of the
        // portfolio to that account, which settles or restores the debt.
        $to_account = ($step === 'endorse') ? (int) ($options['account_id'] ?? 0) : (int) $cheque['account_id'];

        if ($to_account <= 0) {
            erp_tx_rollback();
            return $fail(lang('Choose an account.'));
        }

        $rate = erp_cheque_till_rate((int) $cheque['portfolio_till_id'], $date);
        if ($rate['error'] !== '') {
            erp_tx_rollback();
            return $fail($rate['error']);
        }

        $reason = mb_substr(trim((string) ($options['reason'] ?? '')), 0, 150);
        $posted = erp_post_receipt(array(
            'direction' => 'payment',
            'account_id' => $to_account,
            'cash_account_id' => (int) $cheque['portfolio_till_id'],
            'amount' => (int) $cheque['amount'],
            'doc_date' => $date,
            'currency' => $rate['currency'],
            'exchange_rate' => $rate['rate'],
            'exchange_rate_date' => $rate['rate_date'],
            'exchange_rate_source' => $rate['source'],
            'payment_method' => ((string) $cheque['kind'] === 'cheque') ? 'cheque' : 'other',
            'description' => mb_substr((($step === 'endorse')
                ? lang(array('string' => '{var:1} passed on', 'vars' => $name))
                : lang(array('string' => '{var:1} bounced', 'vars' => $name))) . (($reason !== '') ? ': ' . $reason : ''), 0, 255),
            'created_by' => (int) $user_id,
        ));

        if (empty($posted['success'])) {
            erp_tx_rollback();
            return $fail((string) $posted['error']);
        }

        $close_id = (int) $posted['cash_id'];
        $endorsed = ($step === 'endorse') ? $to_account : 0;
    }

    $new_status = array('collect' => 'collected', 'pay' => 'paid', 'endorse' => 'endorsed', 'bounce' => 'bounced');

    $ok = erp_query("UPDATE erp_cheques SET status = '" . $new_status[$step] . "',
            bank_till_id = IF('" . $bank_till . "' > 0, '" . $bank_till . "', bank_till_id),
            endorsed_account_id = '" . $endorsed . "',
            close_cash_id = '" . $close_id . "',
            closed_date = '" . escape($date) . "',
            notes = IF('" . escape(mb_substr(trim((string) ($options['reason'] ?? '')), 0, 150)) . "' = '', notes, TRIM(CONCAT(notes, ' ', '" . escape(mb_substr(trim((string) ($options['reason'] ?? '')), 0, 150)) . "'))),
            updated_at = UNIX_TIMESTAMP()
        WHERE id = '" . (int) $cheque['id'] . "'");

    if (($ok === false) || !erp_tx_commit()) {
        $error = erp_db_error();
        erp_tx_rollback();
        return $fail($error);
    }

    return array('success' => true, 'error' => '');
}
