<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Shipping labels of an order (includes/fn/shipment_labels.php).
 *
 * POST create (ship_to_id): opens a label for the address with the carrier
 * label service its carrier takes and returns to the order screen with the
 * outcome. GET id: hands out the label's document for printing, the kept
 * file or the service's copy. The documents live under data/, which is
 * closed to the web: this is their only door, behind the order screen's
 * own gate.
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

    $ship_to_id = is_scalar($_POST['ship_to_id'] ?? null) ? (int) $_POST['ship_to_id'] : 0;
    $order_id = ($ship_to_id > 0) ? (int) db_value("SELECT order_id FROM ship_tos WHERE id = '" . $ship_to_id . "'") : 0;

    if (($order_id <= 0) || (($_POST['action'] ?? '') !== 'create')) {
        output_error(lang('Record not found.'), 404);
    }

    include_once('liveform.class.php');
    $liveform = new liveform('view_order');

    $result = pg_shipment_label_create($ship_to_id, USER_ID);

    if ($result['ok']) {
        $liveform->add_notice(lang(array('string' => 'The shipping label was created. Tracking number: {var:1}', 'vars' => array(h($result['number'])))));
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

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

// A PDF opens in the browser to print; a ZPL file is saved for the label
// printer.
if ($document['format'] === 'zpl') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '.zpl"');
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $file_name . '.pdf"');
}

header('Content-Length: ' . strlen($document['document']));

echo $document['document'];
