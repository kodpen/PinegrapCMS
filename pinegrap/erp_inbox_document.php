<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one incoming e-invoice, looked over before it is taken in.
 *
 * The document is shown as the supplier wrote it - lines, taxes, totals,
 * the supplier's details - beside what the ERP would make of it: the account
 * it belongs to (or the one that would be opened) and the purchase invoice
 * the lines add up to. A difference is shown before anything is created, and
 * a document the ERP cannot hold is refused with the reason. Taking it in
 * writes a draft; the draft is issued from its own screen, where it can still
 * be corrected.
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
include_once('liveform.class.php');
$liveform = new liveform('erp_inbox_document');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_inbox.php';
$id = (int) ($_REQUEST['id'] ?? 0);
$row = erp_edoc_inbox_row($id);

if ($row === null) {
    output_error(lang('The document could not be found.') . ' <a href="' . h($list_url) . '">' . lang('Incoming e-invoices') . '</a>');
    exit();
}

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_inbox_document.php?id=' . $id;

if ($_POST) {

    validate_token_field();

    $action = (string) ($_POST['erp_action'] ?? '');

    if ($action === 'import') {

        $result = erp_edoc_inbox_import($id, (int) $user['id']);

        if ($result['success']) {
            $liveform_draft = new liveform('edit_erp_invoice_draft');
            $liveform_draft->add_notice(lang('The purchase invoice has been drafted from the e-invoice. Check it and issue it; no number has been used yet.'));

            if ($result['account_opened']) {
                $liveform_draft->add_notice(lang('A supplier account was opened from the details on the e-invoice.'));
            }

            go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_erp_invoice_draft.php?id=' . (int) $result['invoice_id']);
        }

        $liveform->mark_error('_error', $result['error']);

    } elseif ($action === 'link') {

        $result = erp_edoc_inbox_link($id, (int) ($_POST['invoice_id'] ?? 0), (int) $user['id']);

        if ($result['success']) {
            $liveform->add_notice(lang('The e-invoice is now marked as taken in by that purchase invoice.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

    } elseif (($action === 'aside') || ($action === 'restore')) {

        $result = erp_edoc_inbox_set_aside($id, ($action === 'aside'), (int) $user['id']);

        if ($result['success']) {
            $liveform->add_notice(($action === 'aside')
                ? lang('The e-invoice has been set aside. It stays in the register and can be brought back.')
                : lang('The e-invoice is waiting to be taken in again.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }

    } elseif ($action === 'refresh') {

        $result = erp_edoc_inbox_document($row, true);

        if ($result['success']) {
            $liveform->add_notice(lang('The document has been read again from the provider.'));
        } else {
            $liveform->mark_error('_error', $result['error']);
        }
    }

    go($self_url);
}

$states = erp_edoc_inbox_states();
$state = erp_edoc_inbox_state($row);
$proposal = erp_edoc_inbox_proposal($row);
$currency = !empty($proposal['success']) ? (string) $proposal['currency'] : (string) $row['currency'];

$money = function ($kurus) use ($currency) {
    return h(erp_money_out_currency((int) $kurus, $currency));
};

$form = function ($action, $button, $extra = '') use ($id) {
    return '<form action="erp_inbox_document.php" method="post" class="d-inline">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $id . '" />
                <input type="hidden" name="erp_action" value="' . h($action) . '" />
                ' . $extra . $button . '
            </form>';
};

$output_alerts = '';
$output_lines = '';
$output_supplier = '';
$output_totals = '';
$output_references = '';
$output_actions = '';

if (empty($proposal['success'])) {

    $output_alerts = '<div class="alert alert-danger my-4">'
        . lang(array('string' => 'The document could not be read: {var:1}', 'vars' => h((string) $proposal['error'])))
        . '</div>';

} else {

    $document = $proposal['document'];
    $supplier = (array) $document['supplier'];

    if (($state === 'new') && !empty($proposal['refusals'])) {
        $items = '';
        foreach ($proposal['refusals'] as $refusal) {
            $items .= '<li>' . h($refusal) . '</li>';
        }
        $output_alerts .= '<div class="alert alert-danger my-4"><div class="fw-bold mb-1">' . lang('This e-invoice cannot be taken in as it stands') . '</div><ul class="mb-0">' . $items . '</ul></div>';
    }

    if (($state === 'new') && !empty($proposal['warnings'])) {
        $items = '';
        foreach ($proposal['warnings'] as $warning) {
            $items .= '<li>' . h($warning) . '</li>';
        }
        $output_alerts .= '<div class="alert alert-warning my-4"><ul class="mb-0 ps-3">' . $items . '</ul></div>';
    }

    if (($state === 'new') && ($proposal['existing'] !== null)) {
        $output_alerts .= '<div class="alert alert-info my-4">'
            . lang(array('string' => 'Purchase invoice {var:1} already carries this ETTN.', 'vars' => '<a href="edit_erp_invoice.php?id=' . (int) $proposal['existing']['id'] . '">' . h(((string) $proposal['existing']['full_number'] !== '') ? $proposal['existing']['full_number'] : lang('Draft')) . '</a>'))
            . '</div>';
    } elseif (($state === 'new') && ($proposal['candidate'] !== null)) {
        $candidate = $proposal['candidate'];
        $output_alerts .= '<div class="alert alert-info my-4 d-flex flex-wrap align-items-center gap-2">
                <span class="flex-grow-1">' . lang(array(
                    'string' => 'Purchase invoice {var:1} from this supplier already has the number {var:2}. If it is the same invoice, link the two instead of taking it in twice.',
                    'vars' => array('<a href="edit_erp_invoice.php?id=' . (int) $candidate['id'] . '">' . h(((string) $candidate['full_number'] !== '') ? $candidate['full_number'] : lang('Draft')) . '</a>', h($document['number'])),
                )) . '</span>
                ' . $form('link', '<button type="submit" class="btn btn-sm btn-outline-secondary" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . lang('Link to that invoice') . '</button>',
                    '<input type="hidden" name="invoice_id" value="' . (int) $candidate['id'] . '" />') . '
            </div>';
    }

    // The lines as the supplier wrote them, flagged where the purchase
    // invoice would come out different.
    $built_lines = (array) ($proposal['built']['lines'] ?? array());

    foreach ((array) $document['lines'] as $index => $line) {
        $source = $proposal['source'][$index] ?? array('text' => '', 'net' => 0, 'vat' => 0, 'withholding' => 0, 'withholding_label' => '', 'exemption' => '');
        $built = $built_lines[$index] ?? null;

        $discount = 0;
        foreach ((array) $line['allowances'] as $allowance) {
            if (empty($allowance['charge'])) {
                $discount += (int) $allowance['amount'];
            }
        }

        $vat_rate = '';
        foreach ((array) $line['taxes'] as $tax) {
            if (((string) $tax['code'] === ERP_UBL_VAT_CODE) || (mb_strtoupper((string) $tax['name'], 'UTF-8') === 'KDV')) {
                $vat_rate = rtrim(rtrim(number_format((float) $tax['rate'], 2, '.', ''), '0'), '.');
                break;
            }
        }

        $flag = '';
        if (($built !== null) && (((int) $built['line_total'] - (int) $built['discount_amount'] !== (int) $source['net'])
            || ((int) $built['tax_total'] !== (int) $source['vat']) || ((int) $built['withholding_amount'] !== (int) $source['withholding']))) {
            $flag = '<i class="bi bi-exclamation-triangle text-warning ms-1" aria-hidden="true" title="' . h(lang(array(
                'string' => 'In the purchase invoice: {var:1} before VAT, {var:2} VAT, {var:3} withheld',
                'vars' => array(erp_money_out_currency((int) $built['line_total'] - (int) $built['discount_amount'], $currency), erp_money_out_currency((int) $built['tax_total'], $currency), erp_money_out_currency((int) $built['withholding_amount'], $currency)),
            ))) . '"></i>';
        } elseif (in_array((string) $line['no'], (array) $proposal['folded'], true)) {
            $flag = '<i class="bi bi-info-circle text-body-secondary ms-1" aria-hidden="true" title="' . h(lang('Kept as one unit of its amount')) . '"></i>';
        }

        $detail = array_filter(array($line['seller_code'], trim($line['brand'] . ' ' . $line['model']), $source['exemption'],
            ((string) $source['withholding_label'] !== '') ? lang(array('string' => 'VAT withholding {var:1}: {var:2}', 'vars' => array($source['withholding_label'], erp_money_out_currency((int) $source['withholding'], $currency)))) : ''), 'strlen');
        $price = (strpos((string) $line['price'], '.') !== false) ? rtrim(rtrim((string) $line['price'], '0'), '.') : (string) $line['price'];

        $output_lines .= '<tr>
                <td class="text-body-secondary">' . h($line['no']) . '</td>
                <td>' . h($source['text']) . $flag . (!empty($detail) ? '<div class="small text-body-secondary">' . h(implode(' · ', $detail)) . '</div>' : '') . '</td>
                <td class="text-end text-nowrap" data-sort="' . (float) $line['quantity'] . '">' . h(erp_quantity_text($line['quantity'])) . ' <span class="text-body-secondary small">' . h($line['unit_code']) . '</span></td>
                <td class="text-end text-nowrap">' . h($price) . '</td>
                <td class="text-end text-nowrap" data-sort="' . (int) $discount . '">' . (($discount > 0) ? $money($discount) : '<span class="text-body-secondary">—</span>') . '</td>
                <td class="text-end text-nowrap" data-sort="' . (int) $source['net'] . '">' . $money($source['net']) . '</td>
                <td class="text-end text-nowrap">' . h($vat_rate) . '</td>
                <td class="text-end text-nowrap" data-sort="' . (int) $source['vat'] . '">' . $money($source['vat']) . '</td>
            </tr>';
    }

    // The supplier, and the account it is or would become.
    $account = $proposal['account'];
    $address = trim(implode(' ', array_filter(array($supplier['address'], $supplier['postcode'], $supplier['district']), 'strlen')));
    $place = trim(implode(' / ', array_filter(array($supplier['city'], $supplier['country']), 'strlen')));

    if ($account !== null) {
        $output_account = '<div class="form-label text-body-secondary">' . lang('Account') . '</div>
                <div><a href="edit_erp_account.php?id=' . (int) $account['id'] . '">' . h($account['title']) . '</a></div>
                <div class="small text-body-secondary">' . lang('The account with this tax number. The purchase invoice is written to it.') . '</div>';
    } else {
        $output_account = '<div class="form-label text-body-secondary">' . lang('Account') . '</div>
                <div class="text-warning-emphasis"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>' . lang('No account has this tax number yet.') . '</div>
                <div class="small text-body-secondary">' . lang('Taking the invoice in opens a supplier account with the details on the left.') . '</div>';
    }

    $output_supplier = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Supplier') . '</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-7 my-2">
                            <div class="fw-bold">' . h($supplier['title']) . '</div>
                            <div>' . h($supplier['tax_scheme'] !== '' ? $supplier['tax_scheme'] : lang('VKN / TCKN')) . ' ' . h($supplier['tax_number']) . ' <span class="text-body-secondary">' . h($supplier['tax_office']) . '</span></div>
                            ' . (($address !== '') ? '<div>' . h($address) . '</div>' : '') . '
                            ' . (($place !== '') ? '<div>' . h($place) . '</div>' : '') . '
                            ' . ((trim($supplier['phone'] . $supplier['email']) !== '') ? '<div class="text-body-secondary small">' . h(trim(implode(' · ', array_filter(array($supplier['phone'], $supplier['email']), 'strlen')))) . '</div>' : '') . '
                        </div>
                        <div class="col-12 col-md-5 my-2">' . $output_account . '</div>
                    </div>
                </div>
            </div>';

    // The totals: the document's and the purchase invoice's, side by side.
    $totals = (array) ($proposal['built']['totals'] ?? array());
    $document_vat = 0;
    foreach ((array) $document['taxes'] as $tax) {
        if (((string) $tax['code'] === ERP_UBL_VAT_CODE) || (mb_strtoupper((string) $tax['name'], 'UTF-8') === 'KDV')) {
            $document_vat += (int) $tax['amount'];
        }
    }

    $total_rows = array(
        array(lang('Net'), (int) $document['totals']['tax_exclusive'], isset($totals['subtotal']) ? (int) $totals['subtotal'] - (int) $totals['discount_total'] : null),
        array(lang('VAT'), $document_vat, isset($totals['tax_total']) ? (int) $totals['tax_total'] : null),
    );

    if (((int) $document['totals']['withholding'] !== 0) || ((int) ($totals['withholding_total'] ?? 0) !== 0)) {
        $total_rows[] = array(lang('VAT withholding'), -(int) $document['totals']['withholding'], isset($totals['withholding_total']) ? -(int) $totals['withholding_total'] : null);
    }

    $total_rows[] = array(lang('Total'), (int) $document['totals']['payable'], isset($totals['grand_total']) ? (int) $totals['grand_total'] : null);

    $output_total_rows = '';
    foreach ($total_rows as $total_row) {
        $differs = ($total_row[2] !== null) && ($total_row[2] !== $total_row[1]);
        $output_total_rows .= '<tr>
                <th scope="row" class="fw-normal">' . h($total_row[0]) . '</th>
                <td class="text-end text-nowrap">' . $money($total_row[1]) . '</td>
                <td class="text-end text-nowrap' . ($differs ? ' text-warning-emphasis fw-bold' : ' text-body-secondary') . '">' . (($total_row[2] === null) ? '—' : $money($total_row[2])) . '</td>
            </tr>';
    }

    $output_totals = '
            <div class="row">
                <div class="col-12 col-lg-6 ms-auto">
                    <div class="card my-4">
                        <div class="card-body p-0 table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th></th><th class="text-end">' . lang('On the e-invoice') . '</th><th class="text-end">' . lang('Purchase invoice') . '</th></tr></thead>
                                <tbody>' . $output_total_rows . '</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>';

    // What the document refers to, and what the supplier wrote on it.
    $references = array();
    if ((string) $document['billing_reference']['number'] !== '') {
        $references[] = lang(array('string' => 'Returns invoice {var:1}', 'vars' => h($document['billing_reference']['number'])));
    }
    foreach ((array) $document['despatch'] as $despatch) {
        $references[] = lang(array('string' => 'Delivery note {var:1}', 'vars' => h($despatch['number'])));
    }
    if ((string) $document['order_reference'] !== '') {
        $references[] = lang(array('string' => 'Order {var:1}', 'vars' => h($document['order_reference'])));
    }

    $notes = '';
    foreach ((array) $document['notes'] as $note) {
        $notes .= '<li>' . h($note) . '</li>';
    }

    if (!empty($references) || ($notes !== '')) {
        $output_references = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Notes') . '</div>
                <div class="card-body">
                    ' . (!empty($references) ? '<div class="mb-2">' . implode(' · ', $references) . '</div>' : '') . '
                    ' . (($notes !== '') ? '<ul class="mb-0 ps-3 text-body-secondary">' . $notes . '</ul>' : '') . '
                </div>
            </div>';
    }
}

// What can be done from here, by the state the document is in.
$wait = ' data-loading-content="' . lang(array('string' => 'Please Wait')) . '"';

if ($state === 'new') {
    if (!empty($proposal['success']) && ($proposal['existing'] !== null)) {
        $output_actions .= $form('link', '<button type="submit" class="btn my-1 btn-primary"' . $wait . '><i class="bi bi-link-45deg me-2" aria-hidden="true"></i>' . lang('Mark as taken in') . '</button>',
            '<input type="hidden" name="invoice_id" value="' . (int) $proposal['existing']['id'] . '" />');
    } elseif (!empty($proposal['success']) && empty($proposal['refusals'])) {
        $output_actions .= $form('import', '<button type="submit" class="btn my-1 btn-primary"' . $wait . '><i class="bi bi-box-arrow-in-down me-2" aria-hidden="true"></i>'
            . (($proposal['account'] === null) ? lang('Open the supplier and draft the purchase invoice') : lang('Draft the purchase invoice')) . '</button>');
    }

    $output_actions .= $form('aside', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-archive me-2" aria-hidden="true"></i>' . lang('Set aside') . '</button>');
} elseif ($state === 'ignored') {
    $output_actions .= $form('restore', '<button type="submit" class="btn my-1 btn-outline-secondary"' . $wait . '><i class="bi bi-arrow-counterclockwise me-2" aria-hidden="true"></i>' . lang('Bring back') . '</button>');
} elseif ($state === 'drafted') {
    $output_actions .= '<a class="btn my-1 btn-primary" href="edit_erp_invoice_draft.php?id=' . (int) $row['invoice_id'] . '"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>' . lang('Open the draft') . '</a>';
} elseif ($state === 'imported') {
    $output_actions .= '<a class="btn my-1 btn-outline-secondary" href="edit_erp_invoice.php?id=' . (int) $row['invoice_id'] . '"><i class="bi bi-receipt me-2" aria-hidden="true"></i>' . h($row['invoice_number']) . '</a>';
}

// The status column: what is read, not worked on.
$document = !empty($proposal['success']) ? $proposal['document'] : array();
$rate = (array) ($document['exchange_rate'] ?? array());
$status_items = array(
    lang('Document Number') => h($row['gib_number']),
    lang('ETTN') => '<span class="small text-break">' . h($row['gib_uuid']) . '</span>',
    lang('Date') => h(prepare_form_data_for_output($row['issue_date'], 'date')) . ((($document['issue_time'] ?? '') !== '') ? ' <span class="text-body-secondary">' . h(substr($document['issue_time'], 0, 5)) . '</span>' : ''),
    lang('Due Date') => ((($document['due_date'] ?? '') !== '') ? h(prepare_form_data_for_output($document['due_date'], 'date')) : '—'),
    lang('Type') => h(erp_edoc_inbox_type_label(($document['type_code'] ?? '') !== '' ? $document['type_code'] : $row['invoice_type']))
        . ' <span class="text-body-secondary">' . h(($document['profile'] ?? '') !== '' ? $document['profile'] : $row['profile']) . '</span>',
    lang('At GİB') => h(((string) $row['provider_status'] !== '') ? $row['provider_status'] : '—'),
    lang('Currency') => h($currency) . ((!empty($rate['rate']) && ($currency !== erp_base_currency()))
        ? ' <span class="text-body-secondary">' . h(erp_fx_rate_text((float) $rate['rate'])) . ' ' . h($rate['target']) . '</span>' : ''),
    lang('Status') => '<span class="badge rounded-pill text-bg-' . $states[$state]['tone'] . '">' . h($states[$state]['label']) . '</span>',
);

$output_status = '';
foreach ($status_items as $label => $value) {
    $output_status .= '<div class="col-6 col-sm-4 col-lg-12 my-2">
                                <div class="form-label text-body-secondary">' . h($label) . '</div>
                                <div>' . $value . '</div>
                            </div>';
}

$provider_info = erp_edoc_info((string) $row['provider']);

// Where it was talked about in the workspace, and the tasks about it.
$output_workspace_button = '';

if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
    $output_workspace_button = ws_record_button($user, 'edoc', (int) $id, lang('Incoming e-invoice') . ' ' . $row['gib_number']);
}

echo
pg_page_shell([
        'title' => lang('Incoming e-invoice'),
        'extra classes' => 'erp erp_inbox',
        'icon' => 'erp',
        'heading' => h(((string) $row['supplier_title'] !== '') ? $row['supplier_title'] : $row['gib_number']),
        'heading_description' => lang('The e-invoice as the supplier sent it, and the purchase invoice it becomes.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_inbox.php'),
        'breadcrumb' => array(
            array('label' => lang('Incoming e-invoices'), 'url' => $list_url),
            array('label' => $row['gib_number']),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-outline-secondary" href="get_erp_inbox_file.php?id=' . $id . '&amp;format=pdf" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('PDF') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="get_erp_inbox_file.php?id=' . $id . '&amp;format=ubl"><i class="bi bi-filetype-xml me-1" aria-hidden="true"></i>' . lang('UBL') . '</a>
                ' . $form('refresh', '<button type="submit" class="btn btn-sm btn-ghost"' . $wait . '><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Read the document again') . '</button>') . '
                ' . $output_workspace_button . '
            </nav>
        </div>

        <!--
            The document column, with the summary beside it rather than
            above it: the pattern the invoice screens use.
        -->
        <div class="col-12 col-lg-8 col-xxl-9 order-1">

            ' . $output_alerts . '

            ' . (($output_lines !== '')
                ? '<div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Lines') . '</div>
                <div class="card-body p-0 position-relative table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th style="width:3rem">#</th>
                                <th>' . lang('Description') . '</th>
                                <th class="text-end">' . lang('Quantity') . '</th>
                                <th class="text-end">' . lang('Unit price') . '</th>
                                <th class="text-end">' . lang('Discount') . '</th>
                                <th class="text-end">' . lang('Taxable Amount') . '</th>
                                <th class="text-end">' . lang('Rate') . '</th>
                                <th class="text-end">' . lang('VAT') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_lines . '</tbody>
                    </table>
                </div>
            </div>'
                : '') . '

            ' . $output_totals . '
            ' . $output_supplier . '
            ' . $output_references . '

            ' . (($output_actions !== '')
                ? '<nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                <div class="container">
                    <div class="d-flex flex-wrap justify-content-center gap-2">' . $output_actions . '</div>
                </div>
            </nav>'
                : '') . '
        </div>

        <div class="col-12 col-lg-4 col-xxl-3 order-2">
            <div class="position-sticky" style="top:3.3rem;">
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('e-Invoice') . '</div>
                    <div class="card-body">
                        <div class="row">' . $output_status . '</div>
                        <div class="small text-body-secondary border-top mt-2 pt-2">' . lang(array('string' => 'Read from {var:1}.', 'vars' => h(is_array($provider_info) ? $provider_info['label'] : $row['provider']))) . '</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
