<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - runs one step of an order's documents (invoice, e-document,
 * delivery note, e-delivery note) from the order screen and goes back to it.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/order_flow.php');
include_once('liveform.class.php');

$order_id = (int) ($_POST['order_id'] ?? 0);
$back = PATH . SOFTWARE_DIRECTORY . '/view_order.php?id=' . $order_id . '#erp_order_documents';

if (!$_POST || ($order_id <= 0)) {
    go(PATH . SOFTWARE_DIRECTORY . '/view_orders.php');
}

validate_token_field();

// A step talks to the provider at most a couple of times.
@set_time_limit(120);

$result = erp_order_flow_run($order_id, (string) ($_POST['erp_step'] ?? ''), (int) $user['id']);

$liveform = new liveform('view_order');

if ($result['success']) {
    foreach ($result['messages'] as $message) {
        $liveform->add_notice(h($message));
    }
} else {
    $liveform->mark_error('_error', h($result['error']));
}

go($back);
