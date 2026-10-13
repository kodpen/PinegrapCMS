<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - write a purchase order.
 *
 * The invoice editor, on the purchase side: the supplier, the lines and the
 * day the goods are expected. Saving gives the order its number; nothing is
 * posted to the account and no stock moves. The work lives in
 * includes/erp/purchase_orders.php.
 *
 * ?copy=<id> opens the editor filled from another order (supplier, currency,
 * notes and lines), dated today; it is a new open order and takes its own
 * number when saved. ?account_id=<id> opens it on that supplier.
 * ?products[]=<id> (the minimum stock screen) opens it with a line for each
 * product: what it needs to get back to its minimum, at its last purchase
 * price.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');
include_once('liveform.class.php');
$liveform = new liveform('add_erp_purchase_order');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_purchase_orders.php';
$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/add_erp_purchase_order.php';

if (!$_POST) {

    // Read before the prefill writes the form into the session: a form
    // already there is one sent back with an error, and keeps what was typed.
    $fresh = !$liveform->field_in_session('issue_date');

    erp_invoice_form_prefill($liveform, null, 'purchase');

    $card_options = array('purchase_order' => true);
    $copy_id = max(0, (int) ($_GET['copy'] ?? 0));

    if ($copy_id > 0) {
        $source = erp_purchase_order($copy_id);

        if ($source === null) {
            $liveform->mark_error('', lang('The purchase order could not be found.'));
        } else {
            // The stored lines, without their keys: the copy is another
            // order and its lines are new ones.
            $copied = array();
            foreach (erp_purchase_order_editor_rows($source) as $line) {
                $line['source_line'] = '';
                $copied[] = $line;
            }
            $card_options['lines'] = erp_invoice_form_lines($liveform, $copied);

            if ($fresh) {
                $liveform->assign_field_value('account_id', (string) (int) $source['account_id']);
                $liveform->assign_field_value('currency', strtoupper(trim((string) $source['currency'])));
                $liveform->assign_field_value('notes', (string) $source['notes']);
                $liveform->add_notice(h(lang(array('string' => 'Copied from purchase order {var:1}', 'vars' => (string) $source['full_number']))));
            }
        }
    }

    // Products picked on the minimum stock screen, one line each.
    $product_ids = (isset($_GET['products']) && is_array($_GET['products'])) ? $_GET['products'] : array();

    if (($copy_id === 0) && !empty($product_ids)) {
        $card_options['lines'] = erp_invoice_form_lines($liveform, erp_purchase_order_rows_for_products($product_ids));

        if ($fresh) {
            $liveform->add_notice(h(lang('The lines are what each product needs to get back to its minimum, at its last purchase price. Pick the supplier and check them.')));
        }
    }

    // An order started from a supplier's card opens on that supplier.
    if (((int) ($_GET['account_id'] ?? 0) > 0) && !$liveform->field_in_session('account_id')) {
        $liveform->assign_field_value('account_id', (string) (int) $_GET['account_id']);
    }

    if (!erp_purchase_orders_ready()) {
        $liveform->mark_error('', lang('Purchase orders come with the software update; run the update to use them.'));
    }

    echo
    pg_page_shell([
        'title' => lang('New purchase order'),
        'extra classes' => 'erp erp_invoices',
        'icon' => 'erp',
        'heading' => lang('New purchase order'),
        'heading_description' => lang('What to ask a supplier for. It moves no money and no stock; as the goods arrive it becomes a purchase invoice.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_purchase_orders.php'),
        'breadcrumb' => array(
            array('label' => lang('Purchase orders'), 'url' => $list_url),
            array('label' => lang('New purchase order')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_purchase_order.php" method="post" autocomplete="off">
                ' . get_token_field() . '
                ' . erp_invoice_form_cards($liveform, $card_options) . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success"' . (erp_purchase_orders_ready() ? '' : ' disabled') . ' data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-cart-check me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Save the purchase order') . '</span></button>
                    </div>
                </nav>
            </form>
            ' . erp_account_quick_modal() . '
        </div>
    </div>
</main>' .
    output_footer();

    $liveform->remove_form();

} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    // An empty date leaves the delivery day open; the form reader would put
    // the account's payment term there instead.
    $expected_typed = trim((string) $liveform->get_field_value('due_date'));

    $read = erp_invoice_form_read($liveform, $user);

    if (!$read['success']) {
        $liveform->mark_error($read['field'], $read['error']);
        go($self_url);
    }

    $data = $read['data'];

    if ($expected_typed === '') {
        $data['due_date'] = '';
    }

    $result = erp_purchase_order_save($data, 0, (int) $user['id']);

    if (!$result['success']) {
        $liveform->mark_error($result['field'] ?? '_error', $result['error']);
        go($self_url);
    }

    log_activity(lang(array('string' => 'erp purchase order ({var:1}) was created', 'vars' => $result['full_number'])), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_order = new liveform('edit_erp_purchase_order');
    $liveform_order->add_notice(h(lang(array('string' => 'Purchase order {var:1} saved.', 'vars' => $result['full_number']))));

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_purchase_order.php?id=' . (int) $result['order_id']);
}
