<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the side drawer of the account, invoice and delivery note lists.
 *
 * A row clicked on a list opens a drawer on the right with what the operator
 * usually opens the document for: the card and the latest movements of an
 * account, the lines and the e-document state of an invoice, the lines and
 * the links of a delivery note. The drawer only reads; every change is made
 * on the document's own screen, which the drawer links to. That keeps one
 * place per action, with the gates and the confirmations it already has.
 *
 * The content comes from get_erp_drawer.php as a fragment, so a list of five
 * hundred rows does not carry five hundred drawers in its page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// How much of an account's history the drawer shows. The statement itself is
// on the account screen; the drawer is for a glance.
if (!defined('ERP_DRAWER_STATEMENT_ROWS')) {
    define('ERP_DRAWER_STATEMENT_ROWS', 12);
}
if (!defined('ERP_DRAWER_DOCUMENT_ROWS')) {
    define('ERP_DRAWER_DOCUMENT_ROWS', 10);
}

/**
 * The drawer shell, printed once at the end of a list, and the script that
 * fills it.
 *
 * @param string $type  'account' | 'invoice' | 'waybill'
 * @return string
 */
function erp_drawer_markup($type)
{
    $script = PG_FUNCTIONS_DIR . '/assets/js/erp_lists.js';

    return '
<style>tr[data-erp-drawer-id] { cursor: pointer; } .pg-erp-drawer { --bs-offcanvas-width: min(560px, 100vw); } .pg-erp-drawer table.table { margin-top: 0; }</style>
<div class="offcanvas offcanvas-end pg-erp-drawer" tabindex="-1" id="erp_drawer" aria-labelledby="erp_drawer_title" data-erp-drawer-type="' . h($type) . '" data-erp-drawer-url="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/get_erp_drawer.php') . '" data-erp-drawer-error="' . h(lang('The details could not be loaded.')) . '">
    <div class="offcanvas-header border-bottom gap-2">
        <h5 class="offcanvas-title text-truncate me-auto" id="erp_drawer_title"></h5>
        <a class="btn btn-sm btn-outline-secondary d-none" id="erp_drawer_open" href="#"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . lang('Open in full') . '</a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="' . lang('Close') . '"></button>
    </div>
    <div class="offcanvas-body" id="erp_drawer_body"></div>
</div>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/erp_lists.js?v=' . @filemtime($script) . '"></script>';
}

/**
 * The row attributes that make a list row open the drawer.
 *
 * @param int $id
 * @return string  Starts with a space
 */
function erp_drawer_row_attributes($id)
{
    return ' data-erp-drawer-id="' . (int) $id . '" tabindex="0"';
}

/**
 * A two-column block of label / value pairs; empty values are left out, so
 * a card with no phone number does not show an empty "Phone" line.
 *
 * @param array $pairs  label => already escaped HTML
 * @return string
 */
function erp_drawer_facts($pairs)
{
    $output = '';

    foreach ($pairs as $label => $value) {
        if (trim(strip_tags((string) $value)) === '') {
            continue;
        }

        $output .= '
        <dt class="col-5 fw-normal text-body-secondary">' . h($label) . '</dt>
        <dd class="col-7 mb-2 text-break">' . $value . '</dd>';
    }

    return ($output !== '') ? '<dl class="row mb-0 small">' . $output . '</dl>' : '';
}

/**
 * A titled section of the drawer.
 *
 * @param string $title
 * @param string $body   HTML
 * @return string
 */
function erp_drawer_section($title, $body)
{
    return '
    <section class="mb-4">
        <h6 class="text-uppercase small fw-bold text-primary mb-2">' . h($title) . '</h6>
        ' . $body . '
    </section>';
}

/**
 * The words of an invoice status, with its tone.
 *
 * @return array  status => [label, text class]
 */
function erp_drawer_invoice_states()
{
    return array(
        'draft' => array(lang('Draft'), 'text-body-secondary'),
        'issued' => array(lang('Issued'), 'text-primary'),
        'partially_paid' => array(lang('Partly paid'), 'text-warning'),
        'paid' => array(lang('Paid'), 'text-success'),
        'cancelled' => array(lang('Cancelled'), 'text-danger'),
    );
}

/**
 * An account: the card, the latest movements with the running balance, and
 * the latest documents.
 *
 * @param int $account_id
 * @return array|null  ['title', 'url', 'html']
 */
function erp_drawer_account($account_id)
{
    $account_id = (int) $account_id;
    $account = db_item("SELECT * FROM erp_accounts WHERE id = '" . $account_id . "' LIMIT 1");

    if (!is_array($account)) {
        return null;
    }

    $kinds = array(
        'customer' => lang('Customer'),
        'supplier' => lang('Supplier'),
        'both' => lang('Customer and supplier'),
    );

    // The balance from the ledger, not the cached figure on the row: the
    // drawer sits beside the statement it summarises and must agree with it.
    $statement = erp_account_statement($account_id);
    $balance = (int) $statement['closing'];
    $balance_class = ($balance > 0) ? 'text-success' : (($balance < 0) ? 'text-danger' : 'text-body-secondary');
    $balance_side = ($balance > 0) ? lang('owes you') : (($balance < 0) ? lang('you owe') : '');

    $address = trim(implode(', ', array_filter(array(
        trim((string) $account['address']),
        erp_address_locality($account, (string) $account['country_code']),
    ))));

    $contact = ((int) $account['contact_id'] > 0) ? erp_contact_summary((int) $account['contact_id']) : null;
    $output_contact = is_array($contact)
        ? '<a href="edit_contact.php?id=' . (int) $account['contact_id'] . '" class="link-body-emphasis">' . h((string) ($contact['name'] ?? ('#' . (int) $account['contact_id']))) . '</a>'
        : '';

    $output_card = '
    <div class="d-flex align-items-baseline flex-wrap gap-2 mb-3">
        <span class="h3 mb-0 ' . $balance_class . '">' . h(erp_money_out(abs($balance))) . '</span>
        <span class="text-body-secondary">' . h($balance_side) . '</span>
        ' . (((string) $account['status'] === 'passive') ? '<span class="badge text-bg-secondary ms-auto">' . lang('Passive') . '</span>' : '') . '
    </div>' . erp_drawer_facts(array(
        lang('Type') => h($kinds[$account['kind']] ?? $account['kind']),
        erp_tax_id_label((string) $account['country_code']) => h((string) $account['tax_number'])
            . (((int) $account['einvoice_user'] === 1) ? ' <span class="badge text-bg-success">' . lang('e-Invoice taxpayer') . '</span>' : ''),
        lang('Tax Office') => h((string) $account['tax_office']),
        lang('Email') => ((string) $account['email'] !== '') ? '<a href="mailto:' . h($account['email']) . '" class="link-body-emphasis">' . h($account['email']) . '</a>' : '',
        lang('Phone') => h((string) $account['phone']),
        lang('Address') => h($address),
        lang('Contact') => $output_contact,
    ));

    // The tail of the statement: the running balance was worked out over
    // the whole ledger, so the last rows carry their true figures.
    $rows = array_slice($statement['rows'], -ERP_DRAWER_STATEMENT_ROWS);
    $output_statement = '';

    foreach (array_reverse($rows) as $row) {
        $signed = ((string) $row['direction'] === 'debit') ? (int) $row['amount_base'] : -((int) $row['amount_base']);
        $description = h((string) $row['description']);

        if (in_array((string) $row['doc_type'], array('collection', 'payment'), true) && ((int) $row['doc_id'] > 0)) {
            $description = '<a href="erp_receipt.php?id=' . (int) $row['doc_id'] . '" class="link-body-emphasis">' . (($description !== '') ? $description : ('#' . (int) $row['doc_id'])) . '</a>';
        } elseif (in_array((string) $row['doc_type'], array('invoice', 'return'), true) && ((int) $row['doc_id'] > 0)) {
            $description = '<a href="edit_erp_invoice.php?id=' . (int) $row['doc_id'] . '" class="link-body-emphasis">' . (($description !== '') ? $description : ('#' . (int) $row['doc_id'])) . '</a>';
        }

        $output_statement .= '
            <tr>
                <td class="text-nowrap text-body-secondary">' . h(prepare_form_data_for_output((string) $row['doc_date'], 'date')) . '</td>
                <td class="text-break">' . $description . '</td>
                <td class="text-end text-nowrap">' . h(erp_money_out($signed)) . '</td>
                <td class="text-end text-nowrap text-body-secondary">' . h(erp_money_out((int) $row['running_balance'])) . '</td>
            </tr>';
    }

    $output_statement = ($output_statement !== '')
        ? '<div class="table-responsive"><table class="table table-sm small mb-1"><tbody>' . $output_statement . '</tbody></table></div>'
            . ((count($statement['rows']) > ERP_DRAWER_STATEMENT_ROWS)
                ? '<a href="edit_erp_account.php?id=' . $account_id . '" class="small link-body-emphasis">' . h(lang(array('string' => 'All {var:1} movements', 'vars' => count($statement['rows'])))) . '</a>'
                : '')
        : '<p class="small text-body-secondary mb-0">' . lang('There are no movements on this account yet.') . '</p>';

    // The latest documents, with what is still open on each.
    $documents = (array) db_items("SELECT * FROM erp_invoices WHERE account_id = '" . $account_id . "'
        ORDER BY issue_date DESC, id DESC LIMIT " . (int) ERP_DRAWER_DOCUMENT_ROWS);
    $states = erp_drawer_invoice_states();
    $output_documents = '';

    foreach ($documents as $document) {
        $is_draft = ((string) $document['status'] === 'draft');
        $open = $is_draft ? 0 : erp_invoice_open_amount($document);
        $state = $states[$document['status']] ?? array($document['status'], '');
        $currency = strtoupper(trim((string) $document['currency']));

        $output_documents .= '
            <tr>
                <td class="text-nowrap"><a href="' . ($is_draft ? 'edit_erp_invoice_draft.php?id=' : 'edit_erp_invoice.php?id=') . (int) $document['id'] . '" class="link-body-emphasis">' . ($is_draft ? lang('Draft') : h($document['full_number'])) . '</a>'
                    . (((string) $document['doc_type'] === 'return') ? ' <span class="badge text-bg-secondary">' . lang('Return') . '</span>' : '') . '</td>
                <td class="text-nowrap text-body-secondary">' . h(prepare_form_data_for_output((string) $document['issue_date'], 'date')) . '</td>
                <td class="' . $state[1] . '">' . h($state[0]) . '</td>
                <td class="text-end text-nowrap">' . h(erp_money_out_currency((int) $document['grand_total'], $currency)) . '</td>
                <td class="text-end text-nowrap ' . (($open > 0) ? 'text-danger' : 'text-body-secondary') . '">' . h(erp_money_out_currency($open, $currency)) . '</td>
            </tr>';
    }

    $output_documents = ($output_documents !== '')
        ? '<div class="table-responsive"><table class="table table-sm small mb-1"><thead><tr class="text-body-secondary"><th class="fw-normal">' . lang('Document Number') . '</th><th class="fw-normal">' . lang('Date') . '</th><th class="fw-normal">' . lang('Status') . '</th><th class="fw-normal text-end">' . lang('Total') . '</th><th class="fw-normal text-end">' . lang('Outstanding') . '</th></tr></thead><tbody>' . $output_documents . '</tbody></table></div>'
            . '<a href="erp_invoices.php?filter=open&amp;account_id=' . $account_id . '" class="small link-body-emphasis">' . lang('Open documents of this account') . '</a>'
        : '<p class="small text-body-secondary mb-0">' . lang('There are no documents on this account yet.') . '</p>';

    $output_actions = '
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-sm btn-outline-secondary" href="add_erp_receipt.php?direction=collection&amp;account_id=' . $account_id . '"><i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>' . lang('Record a Receipt') . '</a>
        <a class="btn btn-sm btn-outline-secondary" href="erp_reconciliation.php?id=' . $account_id . '"><i class="bi bi-envelope-paper me-1" aria-hidden="true"></i>' . lang('Reconciliation Letter') . '</a>
    </div>';

    return array(
        'title' => (string) $account['title'],
        'url' => 'edit_erp_account.php?id=' . $account_id,
        'html' => $output_actions
            . erp_drawer_section(lang('Card'), $output_card)
            . erp_drawer_section(lang('Latest movements'), $output_statement)
            . erp_drawer_section(lang('Invoices'), $output_documents),
    );
}

/**
 * An invoice: the figures, the lines, and where it stands with the tax
 * authority.
 *
 * @param int $invoice_id
 * @return array|null  ['title', 'url', 'html']
 */
function erp_drawer_invoice($invoice_id)
{
    $invoice_id = (int) $invoice_id;
    $invoice = db_item("SELECT i.*, a.title AS live_account_title, o.order_number
        FROM erp_invoices i
        LEFT JOIN erp_accounts a ON a.id = i.account_id
        LEFT JOIN orders o ON o.id = i.order_id
        WHERE i.id = '" . $invoice_id . "' LIMIT 1");

    if (!is_array($invoice)) {
        return null;
    }

    $is_draft = ((string) $invoice['status'] === 'draft');
    $currency = strtoupper(trim((string) $invoice['currency']));
    $open = $is_draft ? 0 : erp_invoice_open_amount($invoice);
    $states = erp_drawer_invoice_states();
    $state = $states[$invoice['status']] ?? array($invoice['status'], '');
    $account_title = (trim((string) $invoice['account_title']) !== '') ? (string) $invoice['account_title'] : (string) $invoice['live_account_title'];

    $output_summary = '
    <div class="d-flex align-items-baseline flex-wrap gap-2 mb-3">
        <span class="h3 mb-0">' . h(erp_money_out_currency((int) $invoice['grand_total'], $currency)) . '</span>
        <span class="' . $state[1] . '">' . h($state[0]) . '</span>
        ' . (($open > 0) ? '<span class="ms-auto text-danger small">' . h(lang(array('string' => '{var:1} outstanding', 'vars' => erp_money_out_currency($open, $currency)))) . '</span>' : '') . '
    </div>' . erp_drawer_facts(array(
        lang('Account') => ((int) $invoice['account_id'] > 0) ? '<a href="edit_erp_account.php?id=' . (int) $invoice['account_id'] . '" class="link-body-emphasis">' . h($account_title) . '</a>' : h($account_title),
        lang('Direction') => h(((string) $invoice['direction'] === 'purchase') ? lang('Purchase') : lang('Sales')),
        lang('Date') => h(prepare_form_data_for_output((string) $invoice['issue_date'], 'date')),
        lang('Due Date') => ((string) ($invoice['due_date'] ?? '') > '0000-00-00') ? h(prepare_form_data_for_output((string) $invoice['due_date'], 'date')) : '',
        lang('Order') => ((int) $invoice['order_id'] > 0) ? '<a href="view_order.php?id=' . (int) $invoice['order_id'] . '" class="link-body-emphasis">' . h($invoice['order_number'] ?: ('#' . (int) $invoice['order_id'])) . '</a>' : '',
        erp_tax_label('tax') => h(erp_money_out_currency((int) $invoice['tax_total'] - (int) ($invoice['tax2_total'] ?? 0), $currency)),
        ((erp_tax2_name() !== '') ? erp_tax2_name() : lang('Second tax')) => ((int) ($invoice['tax2_total'] ?? 0) !== 0) ? h(erp_money_out_currency((int) $invoice['tax2_total'], $currency)) : '',
    ));

    // The lines as they were written; a draft's are whatever the editor saved.
    $lines = (array) db_items("SELECT description, quantity, unit_price, tax_rate, line_total, tax_total"
        . (erp_invoice_lines_have_tax2() ? ", tax2_rate" : "") . "
        FROM erp_invoice_items WHERE invoice_id = '" . $invoice_id . "' ORDER BY line_no ASC, id ASC");
    $output_lines = '';
    $lines_have_tax2 = false;

    foreach ($lines as $line) {
        // The rate the way the invoice prints it: both rates on a line that
        // carries the second tax.
        $tax2_rate = (float) ($line['tax2_rate'] ?? 0);
        $lines_have_tax2 = $lines_have_tax2 || ($tax2_rate > 0);

        $output_lines .= '
            <tr>
                <td class="text-break">' . h((string) $line['description']) . '</td>
                <td class="text-end text-nowrap">' . h(erp_quantity_text($line['quantity'])) . '</td>
                <td class="text-end text-nowrap text-body-secondary">' . h(erp_percent_text($line['tax_rate']) . (($tax2_rate > 0) ? (' + ' . erp_percent_text($tax2_rate)) : '')) . '</td>
                <td class="text-end text-nowrap">' . h(erp_money_out_currency((int) $line['line_total'], $currency)) . '</td>
            </tr>';
    }

    $output_lines = ($output_lines !== '')
        ? '<div class="table-responsive"><table class="table table-sm small mb-0"><thead><tr class="text-body-secondary"><th class="fw-normal">' . lang('Description') . '</th><th class="fw-normal text-end">' . lang('Quantity') . '</th><th class="fw-normal text-end">' . h(erp_line_tax_label('tax', $lines_have_tax2)) . '</th><th class="fw-normal text-end">' . lang('Amount') . '</th></tr></thead><tbody>' . $output_lines . '</tbody></table></div>'
        : '<p class="small text-body-secondary mb-0">' . lang('The document has no lines yet.') . '</p>';

    // The e-document, only where there is one to speak of.
    $output_edoc = '';

    if (erp_edoc_installed() && !$is_draft && ((string) $invoice['direction'] === 'sales')
        && ((erp_edoc_active() !== '') || ((string) ($invoice['edoc_status'] ?? 'none') !== 'none'))) {
        $labels = erp_edoc_status_labels();
        $edoc_status = (string) ($invoice['edoc_status'] ?? 'none');
        $label = $labels[$edoc_status] ?? array($edoc_status, 'secondary');

        $output_edoc = erp_drawer_section(lang('e-Document'), '<div class="mb-2"><span class="badge text-bg-' . h($label[1]) . '">' . h($label[0]) . '</span></div>' . erp_drawer_facts(array(
            lang('GİB Number') => h((string) ($invoice['gib_number'] ?? '')),
            lang('ETTN') => h((string) ($invoice['gib_uuid'] ?? '')),
            lang('Last Message') => h((string) ($invoice['edoc_error'] ?? '')),
        )));
    }

    $output_actions = '
    <div class="d-flex flex-wrap gap-2 mb-4">
        ' . (!$is_draft ? '<a class="btn btn-sm btn-outline-secondary" href="get_erp_invoice_pdf.php?id=' . $invoice_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('PDF') . '</a>' : '') . '
        ' . ((!$is_draft && ($open > 0) && ((int) $invoice['account_id'] > 0))
            ? '<a class="btn btn-sm btn-outline-secondary" href="add_erp_receipt.php?direction=' . (((string) $invoice['direction'] === 'purchase') ? 'payment' : 'collection') . '&amp;invoice_id=' . $invoice_id . '"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>' . ((((string) $invoice['direction'] === 'purchase') ? lang('Record a Payment') : lang('Record a Receipt'))) . '</a>'
            : '') . '
    </div>';

    return array(
        'title' => $is_draft ? lang('Draft') : (string) $invoice['full_number'],
        'url' => ($is_draft ? 'edit_erp_invoice_draft.php?id=' : 'edit_erp_invoice.php?id=') . $invoice_id,
        'html' => $output_actions
            . erp_drawer_section(lang('Summary'), $output_summary)
            . erp_drawer_section(lang('Lines'), $output_lines)
            . $output_edoc,
    );
}

/**
 * A delivery note: where it went, what was on it, and what it belongs to.
 *
 * @param int $waybill_id
 * @return array|null  ['title', 'url', 'html']
 */
function erp_drawer_waybill($waybill_id)
{
    $waybill = erp_waybill((int) $waybill_id);

    if (!is_array($waybill)) {
        return null;
    }

    $waybill_id = (int) $waybill['id'];
    $is_cancelled = ((string) $waybill['status'] === 'cancelled');

    $address = trim(implode(', ', array_filter(array(
        trim((string) $waybill['ship_to_address']),
        trim((string) $waybill['ship_to_district']),
        trim((string) $waybill['ship_to_city']),
        trim((string) ($waybill['ship_to_state'] ?? '')),
    ))));

    $output_summary = ($is_cancelled ? '<div class="mb-2"><span class="badge text-bg-secondary">' . lang('Cancelled') . '</span></div>' : '') . erp_drawer_facts(array(
        lang('Recipient') => h((string) $waybill['ship_to_title']),
        lang('Address') => h($address),
        lang('Account') => ((int) $waybill['account_id'] > 0) ? '<a href="edit_erp_account.php?id=' . (int) $waybill['account_id'] . '" class="link-body-emphasis">' . h((string) $waybill['account_title']) . '</a>' : '',
        lang('Date') => h(prepare_form_data_for_output((string) $waybill['issue_date'], 'date')),
        lang('Ship Date') => ((string) $waybill['ship_date'] > '0000-00-00') ? h(prepare_form_data_for_output((string) $waybill['ship_date'], 'date')) : '',
        lang('Carrier') => h(trim((string) $waybill['carrier_title'] . (((string) $waybill['carrier_vkn'] !== '') ? ' · ' . $waybill['carrier_vkn'] : ''))),
        lang('Plate') => h((string) $waybill['plate']),
        lang('Order') => ((int) $waybill['order_id'] > 0) ? '<a href="view_order.php?id=' . (int) $waybill['order_id'] . '" class="link-body-emphasis">' . h($waybill['order_number'] ?: ('#' . (int) $waybill['order_id'])) . '</a>' : '',
        lang('Invoice') => ((int) $waybill['invoice_id'] > 0) ? '<a href="edit_erp_invoice.php?id=' . (int) $waybill['invoice_id'] . '" class="link-body-emphasis">' . h((string) ($waybill['invoice_number'] ?: ('#' . (int) $waybill['invoice_id']))) . '</a>' : '',
    ));

    $output_lines = '';
    foreach (erp_waybill_lines($waybill_id) as $line) {
        $output_lines .= '
            <tr>
                <td class="text-break">' . h((string) $line['description']) . '</td>
                <td class="text-end text-nowrap">' . h(erp_quantity_text($line['quantity'])) . ' <span class="text-body-secondary">' . h(erp_waybill_unit_label((string) $line['unit_code'])) . '</span></td>
            </tr>';
    }

    $output_lines = ($output_lines !== '')
        ? '<div class="table-responsive"><table class="table table-sm small mb-0"><tbody>' . $output_lines . '</tbody></table></div>'
        : '<p class="small text-body-secondary mb-0">' . lang('The document has no lines yet.') . '</p>';

    $output_actions = '
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-sm btn-outline-secondary" href="get_erp_waybill_pdf.php?id=' . $waybill_id . '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>' . lang('PDF') . '</a>
    </div>';

    return array(
        'title' => (string) $waybill['full_number'],
        'url' => 'edit_erp_waybill.php?id=' . $waybill_id,
        'html' => $output_actions
            . erp_drawer_section(lang('Summary'), $output_summary)
            . erp_drawer_section(lang('Lines'), $output_lines),
    );
}
