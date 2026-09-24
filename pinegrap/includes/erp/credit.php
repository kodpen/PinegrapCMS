<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - a customer's credit limit.
 *
 * The limit is the most a customer may owe the store (erp_accounts.credit_limit,
 * 4.98), in the base currency and in kurus like erp_accounts.balance, with 0
 * for none. It is weighed against the balance - what the ledger says the
 * account owes today - plus the invoice about to be issued.
 *
 * What a sale past the limit does is the store's choice
 * (config.erp_credit_limit_mode): 'warn' issues it and says so, 'block'
 * refuses it before it takes a number. Only invoices typed in the ERP are
 * weighed (erp_invoice_issue()); an order's invoice records a sale that has
 * already happened, usually paid, and is never held back.
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
 * Whether the 4.98 column is there.
 *
 * @return bool
 */
function erp_credit_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('waf_table_has_column') && waf_table_has_column('erp_accounts', 'credit_limit');
    }

    return $ready;
}

/**
 * What a sale past the limit does: 'warn' or 'block'.
 *
 * @return string
 */
function erp_credit_mode()
{
    static $mode = null;

    if ($mode === null) {
        $mode = 'warn';

        if (erp_credit_ready() && waf_table_has_column('config', 'erp_credit_limit_mode')) {
            $mode = ((string) db_value("SELECT erp_credit_limit_mode FROM config LIMIT 1") === 'block') ? 'block' : 'warn';
        }
    }

    return $mode;
}

/**
 * Where an account stands against its limit, with an amount about to be
 * added to what it owes.
 *
 * @param array $account   erp_accounts row (credit_limit, balance)
 * @param int   $adding    Kurus in the base currency, 0 for today's standing
 * @return array|null  ['limit', 'balance', 'after', 'available', 'over', 'over_after'], null without a limit
 */
function erp_credit_state($account, $adding = 0)
{
    $limit = erp_credit_ready() ? (int) ($account['credit_limit'] ?? 0) : 0;

    if ($limit <= 0) {
        return null;
    }

    $balance = (int) ($account['balance'] ?? 0);
    $after = $balance + max(0, (int) $adding);

    return array(
        'limit' => $limit,
        'balance' => $balance,
        'after' => $after,
        'available' => $limit - $balance,
        'over' => ($balance > $limit),
        'over_after' => ($after > $limit),
    );
}

/**
 * The sentence that says a sale takes the account past its limit.
 *
 * @param array $state  From erp_credit_state()
 * @return string
 */
function erp_credit_sentence($state)
{
    return lang(array(
        'string' => 'The account\'s credit limit is {var:1}; with this invoice it would owe {var:2}, {var:3} over.',
        'vars' => array(erp_money_out($state['limit']), erp_money_out($state['after']), erp_money_out($state['after'] - $state['limit'])),
    ));
}

/**
 * Why a sales invoice cannot be issued for its credit limit, or ''. Only in
 * block mode; in warn mode the invoice goes and the screen says so.
 *
 * @param array $account
 * @param int   $adding   The invoice total in the base currency
 * @return string
 */
function erp_credit_refusal($account, $adding)
{
    if (erp_credit_mode() !== 'block') {
        return '';
    }

    $state = erp_credit_state($account, $adding);

    if (($state === null) || !$state['over_after']) {
        return '';
    }

    return erp_credit_sentence($state) . ' ' . lang('Take a payment first, or raise the limit on the account card.');
}

/**
 * The warning a screen shows for an account that is, or would be, past its
 * limit, or ''.
 *
 * @param array $account
 * @param int   $adding
 * @return string
 */
function erp_credit_warning($account, $adding = 0)
{
    $state = erp_credit_state($account, $adding);

    if ($state === null) {
        return '';
    }

    if ((int) $adding > 0) {
        if (!$state['over_after']) {
            return '';
        }

        return erp_credit_sentence($state) . ' ' . ((erp_credit_mode() === 'block')
            ? lang('It cannot be issued until a payment comes in or the limit is raised.')
            : lang('It can still be issued.'));
    }

    return $state['over']
        ? lang(array(
            'string' => 'Over its credit limit: owes {var:1} against a limit of {var:2}.',
            'vars' => array(erp_money_out($state['balance']), erp_money_out($state['limit'])),
        ))
        : '';
}

/**
 * The limit as the account form shows it: the figure with the store's
 * separators, empty for none.
 *
 * @param int $kurus
 * @return string
 */
function erp_credit_input_value($kurus)
{
    if ((int) $kurus <= 0) {
        return '';
    }

    $separators = erp_number_separators();

    return number_format((int) $kurus / 100, 2, $separators['decimal'], $separators['thousands']);
}

/**
 * The accounts over their limit, most over first.
 *
 * @param int $limit
 * @return array
 */
function erp_credit_over_accounts($limit = 100)
{
    if (!erp_credit_ready()) {
        return array();
    }

    return (array) db_items("SELECT id, title, balance, credit_limit, (balance - credit_limit) AS over_by
        FROM erp_accounts
        WHERE credit_limit > 0 AND balance > credit_limit AND status = 'active'
        ORDER BY (balance - credit_limit) DESC
        LIMIT " . max(1, (int) $limit));
}

/**
 * The warning for a sales invoice just issued whose account is now past its
 * limit, or ''. In block mode the invoice could not have been issued past it,
 * so this only speaks in warn mode.
 *
 * @param int $invoice_id
 * @return string
 */
function erp_credit_issued_warning($invoice_id)
{
    if (!erp_credit_ready() || (erp_credit_mode() !== 'warn')) {
        return '';
    }

    $invoice = db_item("SELECT direction, account_id FROM erp_invoices WHERE id = '" . (int) $invoice_id . "' LIMIT 1");

    if (!is_array($invoice) || ((string) $invoice['direction'] !== 'sales')) {
        return '';
    }

    $account = erp_account((int) $invoice['account_id']);

    return is_array($account) ? erp_credit_warning($account) : '';
}

/**
 * The warning a sales draft shows when issuing it would take its account
 * past the limit, or ''.
 *
 * @param array $draft  erp_invoices row
 * @return string
 */
function erp_credit_draft_warning($draft)
{
    if (!erp_credit_ready() || ((string) ($draft['direction'] ?? '') !== 'sales') || ((int) ($draft['account_id'] ?? 0) <= 0)) {
        return '';
    }

    $account = erp_account((int) $draft['account_id']);

    if (!is_array($account)) {
        return '';
    }

    $total = ((int) ($draft['grand_total_base'] ?? 0) > 0) ? (int) $draft['grand_total_base'] : (int) ($draft['grand_total'] ?? 0);

    return erp_credit_warning($account, $total);
}
