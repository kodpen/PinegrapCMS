<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - what the storefront shows a signed-in customer of their own account.
 *
 * The customer's issued sales invoices and returns, what is still owed on
 * each and the account's balance, for the "My Invoices" widget
 * (_render_system_widget_account_invoices()) and its PDF endpoint
 * (get_account_document.php). Nothing here writes: the customer reads the
 * books, the operator keeps them.
 *
 * What is open and how late it is comes from the aging module, the same
 * figures the receivables screens show; the balance is the ledger's, as the
 * statement letter states it.
 *
 * And the prices the account has agreed (price_lists.php), as the store shows
 * and charges them to that customer when config.erp_storefront_account_prices
 * is on: erp_storefront_price() is the one rule, pg_erp_member_prices()
 * (includes/fn/ecommerce.php) applies it to the catalogue and the cart.
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
 * How a document reads to the customer.
 *
 * @param string $doc_type  'invoice' | 'return'
 * @param int    $open      Still owed on it (kurus), from the aging module
 * @param int    $days      Days past due (positive when late)
 * @param int    $paid      What has been collected on it (kurus)
 * @return string  'return' | 'paid' | 'overdue' | 'partial' | 'open'
 */
function erp_storefront_document_state($doc_type, $open, $days, $paid)
{
    if ((string) $doc_type === 'return') {
        return 'return';
    }
    if ((int) $open <= 0) {
        return 'paid';
    }
    if ((int) $days > 0) {
        return 'overdue';
    }

    return ((int) $paid > 0) ? 'partial' : 'open';
}

/**
 * The price an account's terms put on a product: its own price for the
 * product, or the list price less the account's discount (the per-product
 * discount, else the account-wide one, as erp_account_price_terms() gives
 * it). An own price wins over a discount on the same row, as in the line
 * editor.
 *
 * @param int|float  $list_price  products.price (kurus)
 * @param array|null $terms       erp_account_price_terms() for the product
 * @return int|null  Kurus, or null when the terms set nothing
 */
function erp_storefront_account_price($list_price, $terms)
{
    if (!is_array($terms)) {
        return null;
    }

    if ((int) ($terms['price'] ?? 0) > 0) {
        return (int) $terms['price'];
    }

    $rate = min(100.0, (float) ($terms['discount_rate'] ?? 0));

    if ($rate <= 0) {
        return null;
    }

    $list = (int) round((float) $list_price);

    return max(0, $list - erp_apply_rate($list, $rate));
}

/**
 * What a signed-in customer pays for a product in the store.
 *
 * The account's terms do not stack on the store's campaign: whichever of the
 * two is lower is the price, the storefront form of the line editor's rule
 * that agreed terms stand in for the campaign. A tie leaves the campaign. A
 * price the terms would put at or above what the customer pays anyway is no
 * price at all: the list price, or the campaign's, stands.
 *
 * With no terms the answer is the list price or the campaign's price exactly
 * as given, so a store with the setting off prices bit for bit as before.
 *
 * @param int|float      $list_price   products.price (kurus)
 * @param array|null     $terms        erp_account_price_terms() for the product
 * @param int|float|null $offer_price  What the campaign makes it, null for none
 * @return array  ['price' => int|float, 'source' => 'list' | 'offer' | 'account']
 */
function erp_storefront_price($list_price, $terms, $offer_price = null)
{
    $best = ($offer_price !== null)
        ? array('price' => $offer_price, 'source' => 'offer')
        : array('price' => $list_price, 'source' => 'list');

    $account = erp_storefront_account_price($list_price, $terms);

    if (($account !== null) && ($account < $best['price'])) {
        return array('price' => $account, 'source' => 'account');
    }

    return $best;
}

/**
 * The account's issued sales invoices and returns, newest first.
 *
 * Drafts and cancelled documents are never shown; a document without a
 * number has not been issued. Each row carries the invoice columns and:
 * open (kurus, document currency), open_base, days (past due, 0 when nothing
 * is owed) and state (erp_storefront_document_state()).
 *
 * @param int $account_id
 * @param int $limit  At most this many documents
 * @return array
 */
function erp_storefront_documents($account_id, $limit = 50)
{
    $account_id = (int) $account_id;
    $limit = max(1, min(500, (int) $limit));

    if ($account_id <= 0) {
        return array();
    }

    $today = date('Y-m-d');
    $open = array();

    foreach (erp_aging_invoices('sales', $today, array('account_id' => $account_id)) as $aged) {
        $open[(int) $aged['id']] = $aged;
    }

    $rows = (array) db_items("SELECT i.id, i.doc_type, i.full_number, i.issue_date, i.due_date, i.currency,
            i.grand_total, i.paid_total, i.status, i.order_id,
            " . erp_aging_due_sql('i') . " AS effective_due,
            o.order_number
        FROM erp_invoices i
        LEFT JOIN orders o ON o.id = i.order_id
        WHERE i.account_id = '" . $account_id . "'
          AND i.direction = 'sales'
          AND i.doc_type IN ('invoice', 'return')
          AND i.status NOT IN ('draft', 'cancelled')
          AND i.full_number <> ''
        ORDER BY i.issue_date DESC, i.id DESC
        LIMIT " . $limit);

    foreach ($rows as $index => $row) {
        $aged = isset($open[(int) $row['id']]) ? $open[(int) $row['id']] : null;

        $row['open'] = ($aged !== null) ? (int) $aged['open'] : 0;
        $row['open_base'] = ($aged !== null) ? (int) $aged['open_base'] : 0;
        $row['days'] = ($aged !== null) ? (int) $aged['days'] : 0;
        $row['state'] = erp_storefront_document_state($row['doc_type'], $row['open'], $row['days'], (int) $row['paid_total']);

        $rows[$index] = $row;
    }

    return $rows;
}

/**
 * The account's figures for the top of the widget.
 *
 * The balance is the ledger's as of today, in the account's own currency when
 * it keeps one (the way the statement letter states it); the overdue total is
 * what the aging module counts late, in the base currency.
 *
 * @param int $account_id
 * @return array  balance (kurus, signed: positive = the customer owes),
 *                currency, overdue (kurus, base currency), overdue_count
 */
function erp_storefront_account_figures($account_id)
{
    $account_id = (int) $account_id;
    $today = date('Y-m-d');
    $position = erp_reconciliation_balance($account_id, $today);
    $base = erp_base_currency();
    $foreign = ($position['currency'] !== '') && ($position['currency'] !== $base);

    $overdue = 0;
    $count = 0;

    foreach (erp_aging_invoices('sales', $today, array('account_id' => $account_id, 'overdue' => true)) as $aged) {
        $overdue += (int) $aged['open_base'];
        $count++;
    }

    return array(
        'balance' => $foreign ? (int) $position['fc'] : (int) $position['base'],
        'currency' => $foreign ? $position['currency'] : $base,
        'overdue' => $overdue,
        'overdue_count' => $count,
    );
}

/**
 * One issued sales invoice or return of the account, or null when the
 * document is not the account's, not issued, cancelled or not a sales
 * document. The storefront answers every null the same way, so a guessed
 * number says nothing about whether it exists.
 *
 * @param int $account_id
 * @param int $invoice_id
 * @return array|null  id, full_number, doc_type
 */
function erp_storefront_document($account_id, $invoice_id)
{
    $account_id = (int) $account_id;
    $invoice_id = (int) $invoice_id;

    if (($account_id <= 0) || ($invoice_id <= 0)) {
        return null;
    }

    $row = db_item("SELECT id, full_number, doc_type FROM erp_invoices
        WHERE id = '" . $invoice_id . "'
          AND account_id = '" . $account_id . "'
          AND direction = 'sales'
          AND doc_type IN ('invoice', 'return')
          AND status NOT IN ('draft', 'cancelled')
          AND full_number <> ''
        LIMIT 1");

    return is_array($row) ? $row : null;
}
