<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the content of a list's side drawer, as JSON.
 *
 * ?type=account|invoice|waybill&id=N. The answer carries the drawer's title,
 * the address of the document's own screen and the body as HTML; the drawer
 * only reads, so there is no POST here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

$builders = array(
    'account' => 'erp_drawer_account',
    'invoice' => 'erp_drawer_invoice',
    'waybill' => 'erp_drawer_waybill',
);

$drawer = (isset($builders[$type]) && ($id > 0)) ? call_user_func($builders[$type], $id) : null;

if (!is_array($drawer)) {
    http_response_code(404);
    echo json_encode(array('error' => lang('The document could not be found.')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

echo json_encode($drawer, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
