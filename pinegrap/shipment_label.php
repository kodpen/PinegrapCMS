<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipping labels of an order (includes/fn/shipment_labels.php).
 *
 * POST from the order screen: open a label or a return label for an
 * address with the carrier label service its carrier takes, or cancel one,
 * or ask the marketplace for the label it makes for its own carrier (a
 * package imported from it, includes/api/outbound/labels.php); back to the
 * order with the outcome. POST from the orders list: open the
 * missing labels of the selected orders, or print their labels as one PDF
 * merged now and never kept. GET id: hands out one label's document, the
 * kept file or the service's copy; an SVG on a print page that holds it as
 * an image, or with download the SVG file itself, sandboxed. The documents
 * live under data/, which is closed to the web: this is their only door,
 * behind the order screen's own gate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_ecommerce_access($user);

if (!pg_shipment_labels_ready()) {
    output_error(lang('Run the software update first: the table for shipping labels is missing.'), 503);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    validate_token_field();

    include_once('liveform.class.php');

    // label_request: create-<ship_to_id>, return-<ship_to_id>,
    // cancel-<label id> and marketplace-<ship_to_id> (with
    // label_boxes_<ship_to_id>) from the order screen; bulk_create and
    // bulk_print with orders[] from the orders list.
    $request = is_scalar($_POST['label_request'] ?? null) ? (string) $_POST['label_request'] : '';
    $orders = (isset($_POST['orders']) && is_array($_POST['orders'])) ? array_slice(array_map('intval', $_POST['orders']), 0, 500) : array();

    if ($request === 'bulk_print') {
        $bulk = pg_shipment_label_bulk_pdf($orders);

        if (!$bulk['ok']) {
            output_error(h($bulk['error']), 404);
        }

        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="labels.pdf"');
        header('Content-Length: ' . strlen($bulk['document']));

        echo $bulk['document'];
        exit;
    }

    if ($request === 'bulk_create') {
        $bulk = pg_shipment_label_bulk_create($orders, USER_ID);
        $liveform = new liveform('view_orders');

        $liveform->add_notice(h(lang(array('string' => '{var:1} shipping label(s) were created.', 'vars' => array(pg_format_number($bulk['created'], 0))))));

        if ($bulk['left'] > 0) {
            $liveform->add_notice(h(lang(array('string' => '{var:1} address(es) are left; run Create Labels again for them.', 'vars' => array(pg_format_number($bulk['left'], 0))))));
        }

        foreach (array_unique($bulk['failed']) as $error) {
            $liveform->mark_error('_error', h($error));
        }

        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php');
    }

    if (!preg_match('/^(create|return|cancel|marketplace)-([1-9][0-9]*)$/', $request, $match)) {
        output_error(lang('Record not found.'), 404);
    }

    $order_id = (int) (($match[1] === 'cancel')
        ? db_value("SELECT order_id FROM shipment_labels WHERE id = '" . (int) $match[2] . "'")
        : db_value("SELECT order_id FROM ship_tos WHERE id = '" . (int) $match[2] . "'"));

    if ($order_id <= 0) {
        output_error(lang('Record not found.'), 404);
    }

    $liveform = new liveform('view_order');

    if ($match[1] === 'cancel') {
        $result = pg_shipment_label_cancel((int) $match[2]);
        $notice = lang('The shipping label was cancelled.');
    } elseif ($match[1] === 'marketplace') {
        $boxes = $_POST['label_boxes_' . $match[2]] ?? '';
        $result = pg_shipment_label_marketplace_ready()
            ? mp_label_create_for_ship_to((int) $match[2], mp_label_boxes($boxes), USER_ID)
            : array('ok' => false, 'existing' => false, 'pending' => false, 'error' => lang('This address belongs to no package a marketplace makes labels for.'), 'number' => '');
        $notice = $result['existing']
            ? lang('This address has its shipping label already.')
            : lang(array('string' => 'The shipping label was created. Tracking number: {var:1}', 'vars' => array(h($result['number']))));
    } else {
        $result = pg_shipment_label_create((int) $match[2], USER_ID, $match[1] === 'return');
        $notice = lang(array('string' => 'The shipping label was created. Tracking number: {var:1}', 'vars' => array(h($result['number']))));
    }

    if ($result['ok']) {
        $liveform->add_notice($notice);
    } elseif (!empty($result['pending'])) {
        $liveform->add_notice(h($result['error']));
    } else {
        $liveform->mark_error('_error', h($result['error']));
    }

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id);
}

$id = is_scalar($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
$row = ($id > 0) ? db_item("SELECT * FROM shipment_labels WHERE id = '" . $id . "'") : null;

if (!$row) {
    output_error(lang('Record not found.'), 404);
}

$document = pg_shipment_label_document($row);

if (!$document['ok']) {
    output_error(h($document['error']), 502);
}

$file_name = 'label-' . preg_replace('/[^A-Za-z0-9-]+/', '', (string) ($row['number'] !== '' ? $row['number'] : $row['id']));

$download = !empty($_GET['download']);

foreach (pg_shipment_label_headers((string) $document['format'], $file_name, $download) as $header) {
    header($header);
}

// An SVG is shown only as an image of a print page, or saved sandboxed.
if (($document['format'] === 'svg') && !$download) {
    echo pg_shipment_label_svg_page($document['document'], lang('Shipping Label') . ' ' . $file_name, 'shipment_label.php?id=' . (int) $row['id'] . '&download=1');
    exit;
}

header('Content-Length: ' . strlen($document['document']));

echo $document['document'];
