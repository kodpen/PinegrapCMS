<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - foreign currency.
 *
 * The module counts in the store's base currency, whatever that is. A document
 * may be drawn in another currency: its amounts stay in that currency, and one
 * rate fixed on the document's own day converts them into the base for the
 * ledger. This file is where the module asks which currencies are allowed,
 * which rate applies on a day, and how the base-currency gap between an
 * invoice and the money that closed it is settled.
 *
 * Everything here is behind the opt-in setting. With it off the module offers
 * no currency anywhere and every figure is in the base currency, which is what
 * the module did before this file existed.
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
 * The store's base currency code, upper case.
 *
 * @return string
 */
function erp_base_currency()
{
    return strtoupper(trim((string) (defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : 'USD')));
}

/**
 * Whether foreign-currency documents, accounts and tills are switched on.
 *
 * @return bool
 */
function erp_fx_enabled()
{
    return defined('ERP_FX_ENABLED') && ((int) ERP_FX_ENABLED === 1);
}

/**
 * Whether the base-currency difference is posted when a foreign invoice closes.
 *
 * @return bool
 */
function erp_fx_auto_diff()
{
    return erp_fx_enabled() && (!defined('ERP_FX_AUTO_DIFF') || ((int) ERP_FX_AUTO_DIFF === 1));
}

/**
 * The currencies the store lists, code => name.
 *
 * @return array
 */
function erp_fx_store_currencies()
{
    static $currencies = null;

    if ($currencies === null) {
        $currencies = array();
        foreach ((array) db_items("SELECT code, name FROM currencies ORDER BY base DESC, name ASC") as $row) {
            $code = strtoupper(trim((string) $row['code']));
            if ($code !== '') {
                $currencies[$code] = (string) $row['name'];
            }
        }
    }

    return $currencies;
}

/**
 * The foreign currencies a document may be drawn in.
 *
 * The configured list less the base, and only codes the store's currency table
 * knows: a code with no row has no symbol and no rate to be fetched for it.
 * Empty while the feature is off.
 *
 * @return array  Codes, upper case
 */
function erp_fx_currencies()
{
    if (!erp_fx_enabled()) {
        return array();
    }

    $base = erp_base_currency();
    $known = erp_fx_store_currencies();
    $codes = array();

    foreach (explode(',', (string) (defined('ERP_FX_CURRENCIES') ? ERP_FX_CURRENCIES : '')) as $code) {
        $code = strtoupper(trim($code));
        if (($code !== '') && ($code !== $base) && isset($known[$code]) && !in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }

    return $codes;
}

/**
 * Whether a currency may be used on a document right now.
 *
 * @param string $code
 * @return bool
 */
function erp_fx_currency_allowed($code)
{
    $code = strtoupper(trim((string) $code));

    return ($code === erp_base_currency()) || in_array($code, erp_fx_currencies(), true);
}

/**
 * Options for a currency select: the base first, then the allowed currencies.
 *
 * @return array  label => code, shaped for liveform
 */
function erp_fx_currency_options()
{
    $names = erp_fx_store_currencies();
    $base = erp_base_currency();
    $options = array();

    $options[h($base . (isset($names[$base]) ? (' - ' . $names[$base]) : ''))] = $base;

    foreach (erp_fx_currencies() as $code) {
        $options[h($code . ' - ' . $names[$code])] = $code;
    }

    return $options;
}

/**
 * The rate for a currency on a date.
 *
 * The base is always 1 on any day. Otherwise the history is asked for the
 * day's rate or the last one before it.
 *
 * @param string $currency
 * @param string $date  Y-m-d
 * @return array|false  array('rate' => float, 'rate_date' => 'Y-m-d', 'source' => string), or false when no rate is recorded
 */
function erp_fx_rate_for($currency, $date)
{
    $currency = strtoupper(trim((string) $currency));

    if ($currency === erp_base_currency()) {
        return array('rate' => 1.0, 'rate_date' => (string) $date, 'source' => 'base');
    }

    if (!function_exists('pg_currency_rate')) {
        return false;
    }

    return pg_currency_rate($currency, $date, erp_base_currency());
}

/**
 * Read a rate typed by an operator.
 *
 * Both decimal separators are accepted; anything that is not a digit or a
 * separator is dropped.
 *
 * @param string $value
 * @return float  0 when nothing usable was typed
 */
function erp_fx_rate_in($value)
{
    $raw = preg_replace('/[^0-9.,]/', '', (string) $value);

    if ($raw === '') {
        return 0.0;
    }

    // The last separator is the decimal one; the others are grouping.
    $last_dot = strrpos($raw, '.');
    $last_comma = strrpos($raw, ',');
    $position = max(($last_dot === false) ? -1 : $last_dot, ($last_comma === false) ? -1 : $last_comma);

    if ($position < 0) {
        return (float) $raw;
    }

    $whole = preg_replace('/[^0-9]/', '', substr($raw, 0, $position));
    $fraction = preg_replace('/[^0-9]/', '', substr($raw, $position + 1));

    return (float) ($whole . '.' . $fraction);
}

/**
 * A rate for the screen: as many decimals as it needs, at least four.
 *
 * @param float $rate
 * @return string
 */
function erp_fx_rate_out($rate)
{
    $text = number_format((float) $rate, 6, '.', '');
    $text = rtrim($text, '0');

    if ((strlen($text) - strpos($text, '.') - 1) < 4) {
        $text = number_format((float) $rate, 4, '.', '');
    }

    return $text;
}

/**
 * Settle the base-currency gap on a foreign invoice that has been paid off.
 *
 * The invoice put its base value on the account at the issue-date rate; each
 * receipt took its own base value off at the receipt-date rate. In the
 * document currency the account is square once the invoice is paid, but in
 * the base currency the two sides differ by whatever the rate did in between.
 * One fx_diff movement in the base currency for exactly that gap brings the
 * account's base balance back to zero, so the statement closes the way the
 * document did.
 *
 * Returns raised against the invoice are part of what it asks for, at the
 * rate they copied from it, so they are taken off the invoice side first.
 *
 * Idempotent: one movement per invoice, keyed on doc_type/doc_id. Runs inside
 * the caller's transaction and never opens one.
 *
 * @param array $invoice     Invoice row (id, account_id, direction, currency, grand_total_base)
 * @param int   $created_by
 * @return bool  false only when a write failed; nothing to post is true
 */
function erp_fx_post_difference($invoice, $created_by = 0)
{
    $invoice_id = (int) ($invoice['id'] ?? 0);
    $account_id = (int) ($invoice['account_id'] ?? 0);

    if (($invoice_id <= 0) || ($account_id <= 0)) {
        return true;
    }

    if (strtoupper((string) ($invoice['currency'] ?? '')) === erp_base_currency()) {
        return true;
    }

    // One difference per closing. A difference that was reversed when the
    // receipt behind it was cancelled does not count: the invoice is open
    // again and will need a fresh one when it closes next.
    $already = (int) db_value("SELECT COUNT(*) FROM erp_account_transactions d
        WHERE d.kind = 'fx_diff' AND d.doc_type = 'fx_diff' AND d.doc_id = '" . $invoice_id . "'
          AND NOT EXISTS (SELECT 1 FROM erp_account_transactions r
              WHERE r.kind = 'fx_diff' AND r.doc_type = 'cancel' AND r.doc_id = d.id)");

    if ($already > 0) {
        return true;
    }

    $collected_base = (int) db_value("SELECT COALESCE(SUM(amount_base), 0) FROM erp_settlements
        WHERE invoice_id = '" . $invoice_id . "'");

    $returned_base = (int) db_value("SELECT COALESCE(SUM(grand_total_base), 0) FROM erp_invoices
        WHERE parent_invoice_id = '" . $invoice_id . "'
          AND doc_type = 'return'
          AND status <> 'cancelled'");

    $invoiced_base = (int) ($invoice['grand_total_base'] ?? 0) - $returned_base;
    $difference = $collected_base - $invoiced_base;

    if ($difference === 0) {
        return true;
    }

    // A sale debited the account for the invoice and credited it for the
    // money: more base collected than invoiced leaves a credit that a debit
    // clears. A purchase is the mirror image.
    $is_sales = ((string) ($invoice['direction'] ?? 'sales') === 'sales');
    $direction = (($difference > 0) xor !$is_sales) ? 'debit' : 'credit';

    $posted = erp_account_post(array(
        'account_id' => $account_id,
        'doc_date' => date('Y-m-d'),
        'kind' => 'fx_diff',
        'direction' => $direction,
        'amount' => abs($difference),
        'currency' => erp_base_currency(),
        'exchange_rate' => 1,
        'exchange_rate_source' => 'base',
        'amount_base' => abs($difference),
        'doc_type' => 'fx_diff',
        'doc_id' => $invoice_id,
        'description' => lang(array(
            'string' => 'Exchange difference on {var:1}',
            'vars' => (string) ($invoice['full_number'] ?? ('#' . $invoice_id)),
        )),
        'created_by' => (int) $created_by,
    ));

    return ($posted !== false);
}

/**
 * Take back the exchange difference posted when an invoice closed.
 *
 * The receipt that closed a foreign-currency invoice has been cancelled, or
 * its allocation removed, so the invoice is open again and the difference
 * booked against it says something that is no longer true. Like every other
 * correction it is reversed by an opposite movement, never deleted, and the
 * reversal points at the difference row it undoes so the closing that comes
 * next can post a fresh one.
 *
 * Runs inside the caller's transaction.
 *
 * @param int $invoice_id
 * @param int $created_by
 * @return bool  false when a posting failed
 */
function erp_fx_reverse_difference($invoice_id, $created_by = 0)
{
    $invoice_id = (int) $invoice_id;

    $differences = (array) db_items("SELECT d.* FROM erp_account_transactions d
        WHERE d.kind = 'fx_diff' AND d.doc_type = 'fx_diff' AND d.doc_id = '" . $invoice_id . "'
          AND NOT EXISTS (SELECT 1 FROM erp_account_transactions r
              WHERE r.kind = 'fx_diff' AND r.doc_type = 'cancel' AND r.doc_id = d.id)");

    foreach ($differences as $difference) {
        $posted = erp_account_post(array(
            'account_id' => (int) $difference['account_id'],
            'doc_date' => date('Y-m-d'),
            'kind' => 'fx_diff',
            'direction' => ((string) $difference['direction'] === 'debit') ? 'credit' : 'debit',
            'amount' => (int) $difference['amount'],
            'currency' => (string) $difference['currency'],
            'exchange_rate' => 1,
            'exchange_rate_source' => 'base',
            'amount_base' => (int) $difference['amount_base'],
            'doc_type' => 'cancel',
            'doc_id' => (int) $difference['id'],
            'description' => lang(array(
                'string' => '{var:1} cancelled',
                'vars' => (string) $difference['description'],
            )),
            'created_by' => (int) $created_by,
        ));

        if ($posted === false) {
            return false;
        }
    }

    return true;
}
