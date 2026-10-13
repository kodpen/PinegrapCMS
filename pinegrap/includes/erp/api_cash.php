<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - moving money between tills and writing cheques and notes through the
 * external API.
 *
 * A transfer is the one the Cash and Bank screen records (erp_post_transfer()
 * in cash.php): money out of one till and into another, nobody's debt
 * changed. A cheque or note is recorded and moved on with the functions its
 * screens call (cheques.php), so the tills, the accounts, the period lock and
 * the audit trail see an application's movement the way they see an
 * operator's. Every path here moves a till, so every one passes the cash
 * gate: the erp_cash:write scope on the key and the cash right of its owner.
 *
 * Every check is made before the first write, which is where X-Dry-Run stops
 * (includes/api/dry_run.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
    exit;
}

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * An active till by its id, or a 422 naming the parameter it came in.
 *
 * @param int    $cash_account_id
 * @param string $field
 * @return array  id, name, kind, currency, is_active, balance
 */
function erp_api_cash_till($cash_account_id, $field)
{
    $till = db_item("SELECT id, name, kind, currency, is_active, balance FROM erp_cash_accounts WHERE id = '" . (int) $cash_account_id . "' LIMIT 1");

    if (!is_array($till)) {
        api_fail_validation(lang('That till or bank account could not be found.'), $field);
    }

    if ((int) $till['is_active'] !== 1) {
        api_fail_validation(lang('That till or bank account is not active.'), $field);
    }

    return $till;
}

/**
 * The currency a till's movements are written in: its own while foreign
 * currency is switched on, the store's otherwise.
 *
 * @param array $till
 * @return string
 */
function erp_api_cash_till_currency($till)
{
    $currency = erp_fx_enabled() ? strtoupper(trim((string) $till['currency'])) : '';

    return ($currency !== '') ? $currency : erp_base_currency();
}

/**
 * A 422 for a day in a locked period.
 *
 * @param string $date   Y-m-d
 * @param string $field
 */
function erp_api_cash_lock_check($date, $field)
{
    if (($refusal = erp_lock_refusal($date)) !== '') {
        api_fail(422, 'period_locked', $refusal, $field);
    }
}

/* ---------------------------------------------------------------------------
   Transfers between tills
   --------------------------------------------------------------------------- */

/**
 * One transfer, read from its two movements.
 *
 * @param int $out_id  The movement that took the money out
 * @return array|null
 */
function erp_api_cash_transfer_row($out_id)
{
    $row = db_item("SELECT o.id AS out_id, o.transfer_pair_id AS in_id, o.cash_account_id AS from_id, i.cash_account_id AS to_id,
            o.doc_date, o.amount, o.currency, o.exchange_rate, o.amount_base, o.description, o.created_at,
            f.name AS from_name, f.balance AS from_balance, t.name AS to_name, t.balance AS to_balance
        FROM erp_cash_transactions o
        JOIN erp_cash_transactions i ON i.id = o.transfer_pair_id
        LEFT JOIN erp_cash_accounts f ON f.id = o.cash_account_id
        LEFT JOIN erp_cash_accounts t ON t.id = i.cash_account_id
        WHERE o.id = '" . (int) $out_id . "' AND o.doc_type = 'transfer' AND o.direction = 'out'
        LIMIT 1");

    return is_array($row) ? $row : null;
}

function erp_api_cash_transfer_present($row)
{
    return array(
        'id' => (int) $row['out_id'],
        'out_id' => (int) $row['out_id'],
        'in_id' => (int) $row['in_id'],
        'from_cash_account_id' => (int) $row['from_id'],
        'from_cash_account_name' => (string) ($row['from_name'] ?? ''),
        'to_cash_account_id' => (int) $row['to_id'],
        'to_cash_account_name' => (string) ($row['to_name'] ?? ''),
        'amount' => api_money($row['amount']),
        'currency' => (string) $row['currency'],
        'exchange_rate' => (float) $row['exchange_rate'],
        'amount_base' => api_money($row['amount_base']),
        'date' => erp_api_date($row['doc_date']),
        'description' => (string) $row['description'],
        'from_balance' => api_money($row['from_balance']),
        'to_balance' => api_money($row['to_balance']),
        'created_at' => api_time($row['created_at']),
    );
}

// What erp_api_cash_transfer_present() returns, declared for the OpenAPI document.
function erp_api_cash_transfer_schema()
{
    return array(
        'id' => 'integer',
        'out_id' => 'integer',
        'in_id' => 'integer',
        'from_cash_account_id' => 'integer',
        'from_cash_account_name' => 'string',
        'to_cash_account_id' => 'integer',
        'to_cash_account_name' => 'string',
        'amount' => 'integer',
        'currency' => 'string',
        'exchange_rate' => 'number',
        'amount_base' => 'integer',
        'date' => 'string?',
        'description' => 'string',
        'from_balance' => 'integer',
        'to_balance' => 'integer',
        'created_at' => 'string?',
    );
}

function erp_api_cash_transfers_create($params)
{
    erp_api_expense_cash_gate();

    $owner = erp_api_owner();
    $from = erp_api_cash_till((int) $params['from_cash_account_id'], 'from_cash_account_id');
    $to = erp_api_cash_till((int) $params['to_cash_account_id'], 'to_cash_account_id');

    if ((int) $from['id'] === (int) $to['id']) {
        api_fail_validation(lang('Choose two different accounts.'), 'to_cash_account_id');
    }

    $amount = (int) $params['amount'];
    $date = isset($params['date']) ? erp_api_day($params['date']) : date('Y-m-d');

    erp_api_cash_lock_check($date, 'date');

    // One currency on both sides: a transfer that changed currency would
    // need a rate and a difference of its own (erp_post_transfer()).
    $currency = erp_api_cash_till_currency($from);
    $to_currency = erp_api_cash_till_currency($to);

    if ($currency !== $to_currency) {
        api_fail_validation(lang(array(
            'string' => 'The two accounts are kept in different currencies ({var:1} and {var:2}); a transfer has to stay in one.',
            'vars' => array($currency, $to_currency),
        )), 'to_cash_account_id');
    }

    // The rate recorded for the day wins; the one sent stands in only when
    // there is none, so the base figures never come out at one to one.
    $rate = 1.0;

    if ($currency !== erp_base_currency()) {
        $known = erp_fx_rate_for($currency, $date);

        if (is_array($known)) {
            $rate = (float) $known['rate'];
        } elseif ((float) ($params['exchange_rate'] ?? 0) > 0) {
            $rate = (float) $params['exchange_rate'];
        } else {
            api_fail_validation(lang(array(
                'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
                'vars' => array($currency, $date),
            )), 'exchange_rate');
        }
    }

    $description = mb_substr(trim((string) ($params['description'] ?? '')), 0, 255);

    api_dry_run_stop('created', 'erp_cash_transfer', array(
        'from_cash_account_id' => (int) $from['id'],
        'to_cash_account_id' => (int) $to['id'],
        'amount' => $amount,
        'currency' => $currency,
        'exchange_rate' => $rate,
        'date' => $date,
    ));

    $moved = erp_post_transfer(array(
        'from_id' => (int) $from['id'],
        'to_id' => (int) $to['id'],
        'amount' => $amount,
        'doc_date' => $date,
        'description' => $description,
        'exchange_rate' => $rate,
        'created_by' => $owner['id'],
    ));

    if (empty($moved['success'])) {
        api_fail_validation((string) $moved['error'], 'amount');
    }

    erp_api_log(lang(array(
        'string' => 'ERP transfer #{var:1} from {var:2} to {var:3} was recorded through the API.',
        'vars' => array((int) $moved['out_id'], (string) $from['name'], (string) $to['name']),
    )));

    api_ok(erp_api_cash_transfer_present(erp_api_cash_transfer_row((int) $moved['out_id'])), 201);
}

/* ---------------------------------------------------------------------------
   Cheques and promissory notes
   --------------------------------------------------------------------------- */

/**
 * The cheque as the endpoints answer with it.
 *
 * @param int $cheque_id
 * @param int $status
 */
function erp_api_cheque_answer($cheque_id, $status = 200)
{
    $cheque = erp_cheque((int) $cheque_id);

    if ($cheque === null) {
        api_fail(404, 'not_found', lang('The cheque or note could not be found.'));
    }

    api_ok(erp_api_cheque_present($cheque), $status);
}

/**
 * Why a step cannot be taken, naming where the cheque is now.
 *
 * @param string $status
 * @return string
 */
function erp_api_cheque_step_refusal($status)
{
    $statuses = erp_cheque_statuses();

    return lang('It cannot take that step from where it is now.') . ' (' . ($statuses[$status][0] ?? $status) . ')';
}

function erp_api_cheques_create($params)
{
    erp_api_cheques_ready();
    erp_api_expense_cash_gate();

    $owner = erp_api_owner();
    $account_id = (int) $params['account_id'];

    if (!is_array(erp_account($account_id))) {
        api_fail_validation(lang('That account could not be found.'), 'account_id');
    }

    $till = erp_api_cash_till((int) $params['cash_account_id'], 'cash_account_id');
    $amount = (int) $params['amount'];
    $date = isset($params['date']) ? erp_api_day($params['date']) : date('Y-m-d');
    $due_date = erp_api_day($params['due_date']);

    if ($due_date === '') {
        api_fail_validation(lang('Enter the date it falls due.'), 'due_date');
    }

    erp_api_cash_lock_check($date, 'date');

    // What erp_cheque_create() and the receipt under it would refuse, asked
    // here so a rehearsal gives the same answer.
    $rate = erp_cheque_till_rate((int) $till['id'], $date);

    if ($rate['error'] !== '') {
        api_fail_validation($rate['error'], 'cash_account_id');
    }

    if (!erp_fx_currency_allowed($rate['currency'])) {
        api_fail_validation(lang('That currency is not enabled for the ERP.'), 'cash_account_id');
    }

    $direction = (string) $params['direction'];
    $kind = (string) ($params['kind'] ?? 'cheque');

    api_dry_run_stop('created', 'erp_cheque', array(
        'direction' => $direction,
        'kind' => $kind,
        'status' => ($direction === 'received') ? 'portfolio' : 'given',
        'account_id' => $account_id,
        'cash_account_id' => (int) $till['id'],
        'amount' => $amount,
        'currency' => $rate['currency'],
        'date' => $date,
        'due_date' => $due_date,
    ));

    $created = erp_cheque_create(array(
        'direction' => $direction,
        'kind' => $kind,
        'account_id' => $account_id,
        'amount' => $amount,
        'doc_date' => $date,
        'due_date' => $due_date,
        'serial_no' => (string) ($params['serial_no'] ?? ''),
        'bank_name' => (string) ($params['bank_name'] ?? ''),
        'branch' => (string) ($params['branch'] ?? ''),
        'drawer' => (string) ($params['drawer'] ?? ''),
        'till_id' => (int) $till['id'],
        'notes' => (string) ($params['notes'] ?? ''),
    ), $owner['id']);

    if (empty($created['success'])) {
        $fields = array('till_id' => 'cash_account_id', 'doc_date' => 'date', 'due_date' => 'due_date', 'amount' => 'amount');

        api_fail_validation((string) $created['error'], $fields[(string) $created['field']] ?? null);
    }

    erp_api_log(lang(array(
        'string' => 'ERP cheque or note #{var:1} was recorded through the API.',
        'vars' => (int) $created['id'],
    )));

    erp_api_cheque_answer((int) $created['id'], 201);
}

function erp_api_cheques_step($params)
{
    erp_api_cheques_ready();
    erp_api_expense_cash_gate();

    $owner = erp_api_owner();
    $cheque = erp_cheque((int) $params['id']);

    if ($cheque === null) {
        api_fail(404, 'not_found', lang('The cheque or note could not be found.'));
    }

    $step = (string) $params['step'];
    $status = (string) $cheque['status'];
    $from = erp_cheque_step_from();

    if (!in_array($status, $from[$step] ?? array(), true)) {
        api_fail(409, 'cheque_step_not_allowed', erp_api_cheque_step_refusal($status));
    }

    // Each step takes only what it uses: the bank account for the steps that
    // go through the bank, the supplier for passing on, a reason for the
    // steps that move money.
    $uses = array(
        'cash_account_id' => array('deposit', 'collect', 'pay'),
        'account_id' => array('endorse'),
        'reason' => array('collect', 'endorse', 'bounce', 'pay'),
    );

    foreach ($uses as $name => $steps) {
        if (isset($params[$name]) && !in_array($step, $steps, true)) {
            api_fail_validation(lang(array(
                'string' => 'The {var:1} step does not take {var:2}.',
                'vars' => array($step, $name),
            )), $name);
        }
    }

    $date = isset($params['date']) ? erp_api_day($params['date']) : date('Y-m-d');
    $bank_till = 0;
    $to_account = 0;

    if ($step !== 'deposit') {
        erp_api_cash_lock_check($date, 'date');
    }

    if (in_array($step, $uses['cash_account_id'], true)) {
        $bank_till = isset($params['cash_account_id']) ? (int) $params['cash_account_id'] : (int) $cheque['bank_till_id'];

        if ($bank_till <= 0) {
            api_fail_validation(lang('Choose the bank account.'), 'cash_account_id');
        }

        $bank = erp_api_cash_till($bank_till, 'cash_account_id');

        if ((int) $bank['id'] === (int) $cheque['portfolio_till_id']) {
            api_fail_validation(lang('Choose two different accounts.'), 'cash_account_id');
        }

        // Collecting and paying move the money between the two tills, which
        // has to stay in one currency.
        if ($step !== 'deposit') {
            $kept = erp_cheque_till_rate((int) $cheque['portfolio_till_id'], $date)['currency'];
            $bank_currency = erp_api_cash_till_currency($bank);

            if ($kept !== $bank_currency) {
                api_fail_validation(lang(array(
                    'string' => 'The two accounts are kept in different currencies ({var:1} and {var:2}); a transfer has to stay in one.',
                    'vars' => array($kept, $bank_currency),
                )), 'cash_account_id');
            }
        }
    }

    if ($step === 'endorse') {
        $to_account = (int) ($params['account_id'] ?? 0);

        if ($to_account <= 0) {
            api_fail_validation(lang('Choose an account.'), 'account_id');
        }

        $account = erp_account($to_account);

        if (!is_array($account)) {
            api_fail_validation(lang('That account could not be found.'), 'account_id');
        }

        if ((string) $account['status'] !== 'active') {
            api_fail_validation(lang('That account is not active.'), 'account_id');
        }

        if ($to_account === (int) $cheque['account_id']) {
            api_fail_validation(lang('Pass it on to an account other than the one it came from.'), 'account_id');
        }
    }

    // Passing on and bouncing are payments out of the portfolio, at the rate
    // of the day.
    if (($step === 'endorse') || ($step === 'bounce')) {
        $rate = erp_cheque_till_rate((int) $cheque['portfolio_till_id'], $date);

        if ($rate['error'] !== '') {
            api_fail_validation($rate['error'], 'date');
        }
    }

    $to_status = array('deposit' => 'deposited', 'collect' => 'collected', 'endorse' => 'endorsed', 'bounce' => 'bounced', 'pay' => 'paid');

    api_dry_run_stop($to_status[$step], 'erp_cheque', array(
        'id' => (int) $cheque['id'],
        'step' => $step,
        'from_status' => $status,
        'to_status' => $to_status[$step],
        'date' => $date,
        'amount' => api_money($cheque['amount']),
        'currency' => (string) $cheque['currency'],
        'cash_account_id' => $bank_till,
        'account_id' => $to_account,
    ));

    $done = erp_cheque_step((int) $cheque['id'], $step, array(
        'date' => $date,
        'bank_till_id' => $bank_till,
        'account_id' => $to_account,
        'reason' => mb_substr(trim((string) ($params['reason'] ?? '')), 0, 150),
    ), $owner['id']);

    if (empty($done['success'])) {
        // Another screen took a step in between: say so the way the check
        // above would have.
        $now = erp_cheque((int) $cheque['id']);

        if (is_array($now) && ((string) $now['status'] !== $status)) {
            api_fail(409, 'cheque_step_not_allowed', erp_api_cheque_step_refusal((string) $now['status']));
        }

        api_fail_validation((string) $done['error'], 'step');
    }

    $labels = array('deposit' => lang('At the bank'), 'collect' => lang('Collected'), 'endorse' => lang('Passed on'), 'bounce' => lang('Bounced'), 'pay' => lang('Paid'));

    erp_api_log(lang(array(
        'string' => 'ERP cheque or note #{var:1} was marked {var:2} through the API.',
        'vars' => array((int) $cheque['id'], $labels[$step]),
    )));

    erp_api_cheque_answer((int) $cheque['id']);
}
