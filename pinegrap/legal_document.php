<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pre-information form or the distance sales contract.
 *
 * Without an order id: the document filled from the visitor's current cart,
 * for the checkout's acceptance checkboxes to link to. It shows the visitor's
 * own cart only (the order id in the session). The link posts what the
 * checkout form holds at the moment of the click
 * (assets/js/pg_legal_documents.js): the billing fields and the payment
 * method, with the form's token. Those values fill this one page; a post
 * without this session's token, or a plain GET, shows the values the
 * checkout page was drawn with.
 *
 * With an order id: the copy kept with a placed order when it was placed
 * (pg_legal_documents_store()), the KVKK copy included,
 * with when it was accepted. Shown to the
 * customer the order belongs to, to the guest who has just placed it and to
 * staff who manage the store (pg_legal_document_may_view()); a kept copy
 * stays readable after the store switches the documents off.
 *
 * Changes nothing in either case.
 *
 * Request: GET type=preinfo|contract [order_id=N], type=kvkk order_id=N
 *          POST to the same address: token, billing_*, payment_method
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$types = pg_legal_document_labels();
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
    // The KVKK notice is a page of the site; only a kept copy is shown here.
    if (!pg_legal_documents_enabled() || !isset(pg_legal_document_types()[$type])) {
        output_error(lang('This page is not available.'));
    }

    // What the checkout form held when its link was clicked; only with this
    // session's token, so another site cannot put words into the visitor's
    // document. A body the server dropped (post_max_size) is no values.
    $posted = array();

    if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && !pg_post_body_discarded()
        && pg_legal_document_token_matches($_SESSION['software']['token'] ?? '', $_POST['token'] ?? '')) {
        $posted = $_POST;
    }

    $documents = pg_legal_documents_for_cart($posted);
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
