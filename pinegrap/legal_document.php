<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pre-information form or the distance sales contract.
 *
 * Without an order id: the document filled from the visitor's current cart,
 * for the checkout's acceptance checkboxes to link to. It shows the visitor's
 * own cart only (the order id in the session).
 *
 * With an order id: the copy kept with a placed order when it was placed
 * (pg_legal_documents_store()), with when it was accepted. Shown to the
 * customer the order belongs to, to the guest who has just placed it and to
 * staff who manage the store (pg_legal_document_may_view()); a kept copy
 * stays readable after the store switches the documents off.
 *
 * Changes nothing in either case.
 *
 * Request: GET type=preinfo|contract [order_id=N]
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$types = pg_legal_document_types();
$type = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';
$order_id = isset($_GET['order_id']) && is_scalar($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if (!defined('ECOMMERCE') || (ECOMMERCE !== true) || !isset($types[$type])) {
    output_error(lang('This page is not available.'));
}

$note = '';

if ($order_id > 0) {
    $order = db_item("SELECT id, user_id FROM orders WHERE id = '" . $order_id . "' AND status != 'incomplete'");
    $kept = $order ? pg_legal_documents_of_order($order_id) : array();

    // An order that is not the visitor's reads the same as one that does not
    // exist, so the page tells nothing about other people's orders.
    if (!$order || !pg_legal_document_may_view($order) || !isset($kept[$type])) {
        output_error(lang('This page is not available.'));
    }

    $document = (string) $kept[$type]['html'];
    $note = pg_legal_document_acceptance_note($kept[$type]);

} else {
    if (!pg_legal_documents_enabled()) {
        output_error(lang('This page is not available.'));
    }

    $documents = pg_legal_documents_for_cart();
    $document = isset($documents[$type]) ? $documents[$type] : '';

    if ($document === '') {
        $document = '<h2>' . h($types[$type]) . '</h2><p class="pg-legal-empty">' . h(lang('There is nothing in your cart, so there is no document to show.')) . '</p>';
    }
}

// The document carries the customer's own details: never kept by a cache.
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');

echo pg_legal_document_page_html($types[$type], $document, lang(array('info' => true)), $note);
