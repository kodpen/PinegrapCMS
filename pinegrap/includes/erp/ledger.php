<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the ledger core: transactions, guarded queries and balance caches.
 *
 * A receipt is two rows - money into a till and a credit against a customer -
 * and a half-written pair is a balance that disagrees with its own ledger. The
 * rest of the software has never needed a database transaction; this module
 * does, which is why the ERP tables are InnoDB and why every posting goes
 * through erp_tx_* here rather than calling db() and hoping.
 *
 * db() ends the request when a query fails. Inside a transaction that is the
 * wrong answer: the rollback has to happen first and the caller has to be told.
 * erp_query() is the same call without the exit.
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
 * Run a statement without ending the request when it fails.
 *
 * @param string $query
 * @return mysqli_result|bool  false on failure
 */
function erp_query($query)
{
    if (!isset(db::$con) || !db::$con) {
        return false;
    }

    return @mysqli_query(db::$con, $query);
}

/**
 * Last database error, for reporting a failed posting.
 *
 * @return string
 */
function erp_db_error()
{
    return (isset(db::$con) && db::$con) ? (string) mysqli_error(db::$con) : '';
}

/**
 * Open a transaction.
 *
 * Nested calls are counted rather than opened again: MySQL has no nested
 * transactions, and a second START would silently commit the first.
 *
 * The depth lives in one place, $GLOBALS['_erp_tx_depth'], and the three
 * functions below all read and write that same counter. A second counter that
 * only one of them reset would leave the next posting in the request believing
 * a transaction was already open when none was.
 *
 * @return bool
 */
function erp_tx_begin()
{
    $depth = erp_tx_depth();

    if ($depth === 0) {
        if (!@mysqli_begin_transaction(db::$con)) {
            return false;
        }
    }

    $GLOBALS['_erp_tx_depth'] = $depth + 1;

    return true;
}

/**
 * How many erp_tx_begin() calls are waiting for their commit.
 *
 * @return int
 */
function erp_tx_depth()
{
    return isset($GLOBALS['_erp_tx_depth']) ? (int) $GLOBALS['_erp_tx_depth'] : 0;
}

/**
 * Commit, once the outermost caller is done.
 *
 * @return bool
 */
function erp_tx_commit()
{
    $depth = erp_tx_depth();

    if ($depth <= 1) {
        $GLOBALS['_erp_tx_depth'] = 0;
        return @mysqli_commit(db::$con);
    }

    $GLOBALS['_erp_tx_depth'] = $depth - 1;

    return true;
}

/**
 * Roll back the whole transaction, whatever the depth.
 *
 * A failure anywhere in a posting invalidates all of it, so an inner caller
 * cannot undo only its own part and leave the rest standing.
 *
 * @return bool
 */
function erp_tx_rollback()
{
    $GLOBALS['_erp_tx_depth'] = 0;

    return @mysqli_rollback(db::$con);
}

/* ------------------------------------------------------------------ ledger */

/**
 * Append one movement to an account's ledger.
 *
 * Append only: a correction is an opposite movement, never an edit. amount is
 * always positive and direction says which way it goes, so a ledger can be read
 * without having to remember which kinds carry a sign.
 *
 * Runs inside the caller's transaction and never opens one of its own - a
 * movement on its own is rarely the whole story.
 *
 * @param array $movement  account_id, doc_date, kind, direction, amount (kurus),
 *                         currency, exchange_rate, exchange_rate_date,
 *                         exchange_rate_source, amount_base, doc_type, doc_id,
 *                         description, created_by
 * @return int|false  Row id, or false with the caller expected to roll back
 */
function erp_account_post($movement)
{
    $account_id = (int) ($movement['account_id'] ?? 0);
    $amount = (int) ($movement['amount'] ?? 0);

    if (($account_id <= 0) || ($amount < 0)) {
        return false;
    }

    // A movement with no currency is in the store's base currency, and a base
    // movement is its own base value; anything else is converted once, here.
    $base = erp_base_currency();
    $currency = strtoupper(trim((string) ($movement['currency'] ?? $base)));
    $exchange_rate = ($currency === $base) ? 1.0 : (float) ($movement['exchange_rate'] ?? 1);
    $amount_base = isset($movement['amount_base'])
        ? (int) $movement['amount_base']
        : (($currency === $base) ? $amount : erp_to_base($amount, $exchange_rate));

    $ok = erp_query("INSERT INTO erp_account_transactions
            (account_id, doc_date, kind, direction, amount, currency, exchange_rate,
             exchange_rate_date, exchange_rate_source, amount_base, doc_type, doc_id,
             description, created_by, created_at)
        VALUES (
            '" . $account_id . "',
            '" . escape($movement['doc_date'] ?? date('Y-m-d')) . "',
            '" . escape($movement['kind'] ?? 'adjustment') . "',
            '" . escape($movement['direction'] ?? 'debit') . "',
            '" . $amount . "',
            '" . escape($currency) . "',
            '" . escape((string) $exchange_rate) . "',
            '" . escape($movement['exchange_rate_date'] ?? ($movement['doc_date'] ?? date('Y-m-d'))) . "',
            '" . escape($movement['exchange_rate_source'] ?? '') . "',
            '" . $amount_base . "',
            '" . escape($movement['doc_type'] ?? '') . "',
            '" . (int) ($movement['doc_id'] ?? 0) . "',
            '" . escape($movement['description'] ?? '') . "',
            '" . (int) ($movement['created_by'] ?? 0) . "',
            '" . time() . "')");

    if ($ok === false) {
        return false;
    }

    return (int) mysqli_insert_id(db::$con);
}

/**
 * Recompute an account's cached balance from its own ledger.
 *
 * balance is the position in the base currency and balance_fc the same in the
 * account's own currency, counting only the movements actually made in it - a
 * base-currency movement on a euro account says nothing about the euro
 * position.
 *
 * The cache exists so a list of two thousand accounts does not become two
 * thousand SUMs. It is never the source of the figure: this function derives it
 * from the ledger every time it is called, and every posting calls it.
 *
 * @param int $account_id
 * @return bool
 */
function erp_account_refresh_balance($account_id)
{
    $account_id = (int) $account_id;

    if ($account_id <= 0) {
        return false;
    }

    $currency = (string) db_value("SELECT currency FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");

    if ($currency === '') {
        return false;
    }

    return (erp_query("UPDATE erp_accounts SET
            balance = COALESCE((
                SELECT SUM(CASE WHEN direction = 'debit' THEN amount_base ELSE -amount_base END)
                FROM erp_account_transactions WHERE account_id = '" . $account_id . "'), 0),
            balance_fc = COALESCE((
                SELECT SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END)
                FROM erp_account_transactions
                WHERE account_id = '" . $account_id . "' AND currency = '" . escape($currency) . "'), 0),
            balance_updated_at = '" . time() . "'
        WHERE id = '" . $account_id . "'") !== false);
}

/**
 * An account's movements with a running balance.
 *
 * Ordered the way the ledger is keyed (date, then id) so the running figure is
 * the same on every reading, including two movements posted on one day.
 *
 * @param int    $account_id
 * @param string $from  Y-m-d, optional
 * @param string $to    Y-m-d, optional
 * @return array  Rows with a running_balance in base-currency kurus
 */
function erp_account_statement($account_id, $from = '', $to = '')
{
    $account_id = (int) $account_id;
    $where = "account_id = '" . $account_id . "'";

    if ($from !== '') {
        $where .= " AND doc_date >= '" . escape($from) . "'";
    }
    if ($to !== '') {
        $where .= " AND doc_date <= '" . escape($to) . "'";
    }

    // Anything before the window is one opening figure rather than a list.
    $opening = 0;
    if ($from !== '') {
        $opening = (int) db_value("SELECT COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount_base ELSE -amount_base END), 0)
            FROM erp_account_transactions
            WHERE account_id = '" . $account_id . "' AND doc_date < '" . escape($from) . "'");
    }

    $rows = (array) db_items("SELECT * FROM erp_account_transactions WHERE " . $where . " ORDER BY doc_date ASC, id ASC");

    $running = $opening;
    foreach ($rows as $index => $row) {
        $running += ($row['direction'] === 'debit') ? (int) $row['amount_base'] : -((int) $row['amount_base']);
        $rows[$index]['running_balance'] = $running;
    }

    return array('opening' => $opening, 'rows' => $rows, 'closing' => $running);
}
