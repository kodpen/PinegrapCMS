<?php
/**
 * PineGrap - Customer-facing printable order invoice.
 *
 * GET endpoint. Renders a print-friendly invoice/receipt HTML page that
 * the visitor can save as a PDF via the browser's "Print → Save as PDF"
 * dialog (Ctrl+P / Cmd+P). Auto-triggers the print dialog on load so
 * users land directly on the save sheet.
 *
 * Why HTML-print and not server-side PDF: pinegrap doesn't bundle a PDF
 * library (no tcpdf/mpdf/dompdf in assets/lib/). Shipping one would be
 * disproportionate for a customer-facing invoice — browser print-to-PDF
 * is universal, looks identical, and needs zero extra dependencies.
 *
 * Request: GET order_id=N
 *   (no CSRF — pure read endpoint, no state changes)
 *
 * Security:
 *   1. ECOMMERCE must be enabled (defensive — order_view widget itself
 *      only renders under ECOMMERCE === true).
 *   2. Ownership — order.user_id must equal the logged-in USER_ID, OR
 *      visitor is admin (USER_ROLE === 0). Guest orders cannot print
 *      via this endpoint (no auth mechanism); they must use the legacy
 *      retrieve-order flow.
 *
 * Sister endpoint: print_order.php is the BACKEND admin print view (gated
 * by validate_ecommerce_access). This file is the customer-facing
 * counterpart and intentionally bypasses the admin gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @copyright   2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (!defined('ECOMMERCE') || ECOMMERCE !== true) {
    output_error('E-commerce is not enabled on this site.');
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    output_error('Order id is missing.');
}

$current_user_id = defined('USER_ID') ? (int)USER_ID : 0;
$is_admin        = defined('USER_ROLE') && (int)USER_ROLE === 0;

// Fetch order. Mirror the column list from _render_system_widget_order_view
// (legacy-safe SELECT — optional columns are probed separately below).
$order = db_item(
    "SELECT id, order_number, order_date, status,
            billing_first_name, billing_last_name, billing_company,
            billing_email_address, billing_phone_number, billing_salutation,
            billing_address_1, billing_address_2,
            billing_city, billing_state, billing_zip_code, billing_country,
            subtotal, discount, tax, shipping, surcharge, total,
            payment_method, transaction_id, special_offer_code,
            payment_installment, installment_charges,
            reference_code, tracking_code,
            user_id, contact_id
     FROM orders
     WHERE id = '" . e($order_id) . "'
     LIMIT 1"
);

if (!$order) {
    output_error('Order not found.');
}

// Ownership — same rule as cancel_order.php. Customer can print own; admin can print any.
if (!$is_admin) {
    if ($current_user_id <= 0 || (int)$order['user_id'] !== $current_user_id) {
        log_activity('Access denied — non-owner attempted to print invoice for order #' . $order_id);
        output_error('You are not allowed to view this invoice.');
    }
}

// Items — same JOIN as the widget so SKU/short-description surface.
$items = db_items(
    "SELECT oi.id AS item_id, oi.product_name,
            oi.quantity, oi.price,
            p.code AS item_code, p.short_description
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = '" . e($order_id) . "'
     ORDER BY oi.id ASC"
);
if (!is_array($items)) $items = array();

// Shipping address (first complete ship_to row).
$ship_to = db_item(
    "SELECT first_name, last_name, company, phone_number,
            address_1, address_2, city, state, zip_code, country
     FROM ship_tos
     WHERE order_id = '" . e($order_id) . "' AND complete = 1
     ORDER BY id ASC
     LIMIT 1"
);
if (!is_array($ship_to)) $ship_to = array();

// Site name — graceful fallback when the settings table or row is missing.
$site_name = '';
try {
    $site_name = (string)db_value("SELECT setting_value FROM settings WHERE setting_name = 'site_name' LIMIT 1");
} catch (\Throwable $_e) {
    $site_name = '';
}
if ($site_name === '' && defined('HOSTNAME')) $site_name = (string)HOSTNAME;

// Currency + money formatter — mirror the widget so totals match exactly.
$currency_symbol = defined('VISITOR_CURRENCY_SYMBOL') ? VISITOR_CURRENCY_SYMBOL : '₺';
$currency_symbol = html_entity_decode((string)$currency_symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$fmt = function ($cents) use ($currency_symbol) {
    return $currency_symbol . number_format((int)$cents / 100, 2, '.', ',');
};

// Order date — int unix timestamp, same as widget.
$order_date_ts = (int)$order['order_date'];
$order_date_str = $order_date_ts > 0 ? date('d.m.Y H:i', $order_date_ts) : '';

// Billing one-liner blocks
$billing_name = trim((string)$order['billing_first_name'] . ' ' . (string)$order['billing_last_name']);
$billing_lines = array_filter(array(
    $billing_name,
    (string)$order['billing_company'],
    (string)$order['billing_address_1'],
    (string)$order['billing_address_2'],
    trim((string)$order['billing_zip_code'] . ' ' . (string)$order['billing_city'] . ' ' . (string)$order['billing_state']),
    (string)$order['billing_country'],
    (string)$order['billing_phone_number'],
    (string)$order['billing_email_address'],
));

$shipping_name = trim((string)($ship_to['first_name'] ?? '') . ' ' . (string)($ship_to['last_name'] ?? ''));
$shipping_lines = array();
if (!empty($ship_to)) {
    $shipping_lines = array_filter(array(
        $shipping_name,
        (string)($ship_to['company'] ?? ''),
        (string)($ship_to['address_1'] ?? ''),
        (string)($ship_to['address_2'] ?? ''),
        trim((string)($ship_to['zip_code'] ?? '') . ' ' . (string)($ship_to['city'] ?? '') . ' ' . (string)($ship_to['state'] ?? '')),
        (string)($ship_to['country'] ?? ''),
        (string)($ship_to['phone_number'] ?? ''),
    ));
}

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title><?= h(lang('Invoice') . ' #' . (string)$order['order_number']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    *,*::before,*::after{box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#212529;line-height:1.5;margin:0;padding:2rem;background:#f5f5f5}
    .invoice{max-width:820px;margin:0 auto;background:#fff;padding:2.5rem;box-shadow:0 0 10px rgba(0,0,0,.08)}
    .hdr{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #212529;padding-bottom:1rem;margin-bottom:1.5rem}
    .hdr h1{margin:0;font-size:1.75rem}
    .hdr .meta{text-align:right;font-size:.9rem;color:#6c757d}
    .hdr .meta strong{color:#212529;display:block;font-size:1.1rem}
    .addrs{display:flex;gap:2rem;margin-bottom:1.5rem}
    .addrs .col{flex:1}
    .addrs h3{font-size:.85rem;text-transform:uppercase;color:#6c757d;letter-spacing:.05em;margin:0 0 .5rem}
    .addrs .lines{font-size:.95rem}
    table{width:100%;border-collapse:collapse;margin-bottom:1rem}
    th{text-align:left;font-size:.8rem;text-transform:uppercase;color:#6c757d;border-bottom:2px solid #dee2e6;padding:.5rem .25rem}
    td{padding:.65rem .25rem;border-bottom:1px solid #f1f3f5;vertical-align:top;font-size:.95rem}
    td.num,th.num{text-align:right;white-space:nowrap}
    .totals{margin-left:auto;width:50%;max-width:320px;font-size:.95rem}
    .totals .row{display:flex;justify-content:space-between;padding:.4rem 0}
    .totals .grand{border-top:2px solid #212529;margin-top:.25rem;padding-top:.6rem;font-weight:700;font-size:1.1rem}
    .meta-row{font-size:.85rem;color:#6c757d;margin-top:1.5rem;border-top:1px solid #dee2e6;padding-top:1rem;display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem 1.5rem}
    .actions{max-width:820px;margin:0 auto 1rem;text-align:right}
    .actions button{background:#0d6efd;color:#fff;border:0;padding:.55rem 1.25rem;border-radius:.35rem;font-size:.95rem;cursor:pointer}
    .actions button:hover{background:#0b5ed7}
    @media print{
        body{padding:0;background:#fff}
        .invoice{box-shadow:none;padding:1rem;max-width:none}
        .actions{display:none}
    }
</style>
</head>
<body>
<div class="actions">
    <button type="button" onclick="window.print()"><?= h(lang('Print Invoice')) ?></button>
</div>
<div class="invoice">
    <div class="hdr">
        <div>
            <h1><?= h(lang('Invoice')) ?></h1>
            <?php if ($site_name !== ''): ?>
                <div style="font-size:.95rem;color:#6c757d"><?= h($site_name) ?></div>
            <?php endif; ?>
        </div>
        <div class="meta">
            <strong><?= h('#' . (string)$order['order_number']) ?></strong>
            <?php if ($order_date_str !== ''): ?>
                <div><?= h($order_date_str) ?></div>
            <?php endif; ?>
            <?php if (!empty($order['reference_code'])): ?>
                <div><?= h(lang('Reference Code')) ?>: <?= h((string)$order['reference_code']) ?></div>
            <?php endif; ?>
            <div><?= h(lang('Status')) ?>: <?= h((string)$order['status']) ?></div>
        </div>
    </div>

    <div class="addrs">
        <div class="col">
            <h3><?= h(lang('Billing Address')) ?></h3>
            <div class="lines">
                <?php foreach ($billing_lines as $ln): ?>
                    <div><?= h($ln) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (!empty($shipping_lines)): ?>
            <div class="col">
                <h3><?= h(lang('Shipping Address')) ?></h3>
                <div class="lines">
                    <?php foreach ($shipping_lines as $ln): ?>
                        <div><?= h($ln) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th><?= h(lang('Item')) ?></th>
                <th><?= h(lang('SKU')) ?></th>
                <th class="num"><?= h(lang('Qty')) ?></th>
                <th class="num"><?= h(lang('Price')) ?></th>
                <th class="num"><?= h(lang('Total')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it):
                $qty = (int)$it['quantity'];
                $unit = (int)$it['price'];
                $line = $unit * $qty;
            ?>
                <tr>
                    <td><?= h((string)$it['product_name']) ?></td>
                    <td><?= h((string)($it['item_code'] ?? '')) ?></td>
                    <td class="num"><?= h((string)$qty) ?></td>
                    <td class="num"><?= h($fmt($unit)) ?></td>
                    <td class="num"><?= h($fmt($line)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span><?= h(lang('Subtotal')) ?></span><span><?= h($fmt((int)$order['subtotal'])) ?></span></div>
        <?php if ((int)$order['discount'] > 0): ?>
            <div class="row"><span><?= h(lang('Discount')) ?></span><span>-<?= h($fmt((int)$order['discount'])) ?></span></div>
        <?php endif; ?>
        <?php if ((int)$order['tax'] > 0): ?>
            <div class="row"><span><?= h(lang('Tax')) ?></span><span><?= h($fmt((int)$order['tax'])) ?></span></div>
        <?php endif; ?>
        <?php if ((int)$order['shipping'] > 0): ?>
            <div class="row"><span><?= h(lang('Shipping')) ?></span><span><?= h($fmt((int)$order['shipping'])) ?></span></div>
        <?php endif; ?>
        <?php if ((int)$order['surcharge'] > 0): ?>
            <div class="row"><span><?= h(lang('Surcharge')) ?></span><span><?= h($fmt((int)$order['surcharge'])) ?></span></div>
        <?php endif; ?>
        <div class="row grand"><span><?= h(lang('Total')) ?></span><span><?= h($fmt((int)$order['total'])) ?></span></div>
        <?php if ((int)$order['payment_installment'] > 1): ?>
            <div class="row" style="font-size:.85rem;color:#6c757d">
                <span><?= h((int)$order['payment_installment'] . ' ' . lang('Installments')) ?></span>
                <span><?= h($fmt((int)round(((int)$order['total'] + (int)$order['installment_charges']) / (int)$order['payment_installment']))) ?> / <?= h(lang('month')) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="meta-row">
        <?php if (!empty($order['payment_method'])): ?>
            <div><?= h(lang('Payment Method')) ?>: <strong><?= h((string)$order['payment_method']) ?></strong></div>
        <?php endif; ?>
        <?php if (!empty($order['transaction_id'])): ?>
            <div><?= h(lang('Transaction ID')) ?>: <?= h((string)$order['transaction_id']) ?></div>
        <?php endif; ?>
        <?php if (!empty($order['tracking_code'])): ?>
            <div><?= h(lang('Tracking Code')) ?>: <?= h((string)$order['tracking_code']) ?></div>
        <?php endif; ?>
        <?php if (!empty($order['special_offer_code'])): ?>
            <div><?= h(lang('Special Offer Code')) ?>: <?= h((string)$order['special_offer_code']) ?></div>
        <?php endif; ?>
    </div>
</div>
<script>
    // Auto-open the print dialog once the page is fully rendered.
    // Wrapped in try/catch so blocked print() (very rare browser policy)
    // doesn't surface as an uncaught error to the visitor.
    window.addEventListener('load', function () {
        try { window.print(); } catch (e) {}
    });
</script>
</body>
</html>
