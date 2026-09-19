<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the typed invoice form, shared by the create and the draft screens.
 *
 * Two screens, one set of fields, described once: a draft that could be
 * created with a field the edit screen does not show is a draft nobody can
 * afterwards correct. The same file reads the posted form back into the
 * shape erp_invoice_draft_save() takes, so the fields and their parsing
 * cannot drift apart.
 *
 * The lines are posted as lines[n][field] arrays. liveform keeps the posted
 * array whole in the session, so after a refused save the rows come back as
 * they were typed; the row markup is rendered here from that array or from
 * the stored draft, and once more as a <template> the editor script clones.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/** Blank rows shown when there is nothing to show yet. */
if (!defined('ERP_INVOICE_FORM_BLANK_ROWS')) {
    define('ERP_INVOICE_FORM_BLANK_ROWS', 4);
}

/**
 * Unit codes offered on a line, UN/ECE Recommendation 20 codes as the
 * e-document side expects them. Free text is still accepted.
 *
 * @return array  code => label
 */
function erp_invoice_form_unit_codes()
{
    return array(
        'C62' => lang('piece'),
        'KGM' => lang('kilogram'),
        'GRM' => lang('gram'),
        'MTR' => lang('metre'),
        'MTK' => lang('square metre'),
        'LTR' => lang('litre'),
        'HUR' => lang('hour'),
        'DAY' => lang('day'),
        'MON' => lang('month'),
        'SET' => lang('set'),
        'PA' => lang('package'),
        'BX' => lang('box'),
    );
}

/**
 * One line of the form as a display row.
 *
 * Every value is a string ready for an input: what the operator typed, or the
 * stored figure written the way erp_kurus() and erp_fx_rate_in() read it back.
 *
 * @param array $source  A posted lines[n] array or an erp_invoice_items row
 * @param bool  $stored  true when $source is a database row
 * @return array
 */
function erp_invoice_form_line($source, $stored = false)
{
    $source = (array) $source;

    $trim_number = function ($value, $decimals) {
        $text = number_format((float) $value, $decimals, '.', '');
        $text = rtrim(rtrim($text, '0'), '.');
        return ($text === '') ? '0' : $text;
    };

    if ($stored) {
        return array(
            'product_id' => (int) ($source['product_id'] ?? 0),
            'product_name' => (string) ($source['product_name'] ?? ''),
            'description' => (string) ($source['description'] ?? ''),
            'quantity' => $trim_number($source['quantity'] ?? 0, 4),
            'unit_code' => (string) ($source['unit_code'] ?? 'C62'),
            'unit_price' => number_format(((int) ($source['unit_price'] ?? 0)) / 100, 2, '.', ''),
            'discount_rate' => ((float) ($source['discount_rate'] ?? 0) > 0) ? $trim_number($source['discount_rate'], 3) : '',
            'tax_rate' => $trim_number($source['tax_rate'] ?? 0, 3),
        );
    }

    return array(
        'product_id' => (int) ($source['product_id'] ?? 0),
        'product_name' => trim((string) ($source['product_name'] ?? '')),
        'description' => trim((string) ($source['description'] ?? '')),
        'quantity' => trim((string) ($source['quantity'] ?? '')),
        'unit_code' => trim((string) ($source['unit_code'] ?? 'C62')),
        'unit_price' => trim((string) ($source['unit_price'] ?? '')),
        'discount_rate' => trim((string) ($source['discount_rate'] ?? '')),
        'tax_rate' => trim((string) ($source['tax_rate'] ?? '')),
    );
}

/**
 * The rows to show: what was posted when the form came back refused,
 * otherwise the stored lines, otherwise blank rows.
 *
 * @param liveform $liveform
 * @param array    $stored_rows  erp_invoice_items rows, with product_name joined
 * @return array  Display rows, see erp_invoice_form_line()
 */
function erp_invoice_form_lines($liveform, $stored_rows = array())
{
    $posted = $liveform->get_field_value('lines');
    $rows = array();

    if (is_array($posted) && !empty($posted)) {
        foreach (array_slice(array_values($posted), 0, ERP_MANUAL_MAX_LINES) as $line) {
            $rows[] = erp_invoice_form_line($line, false);
        }
    } else {
        foreach ((array) $stored_rows as $row) {
            $rows[] = erp_invoice_form_line($row, true);
        }
    }

    while (count($rows) < ERP_INVOICE_FORM_BLANK_ROWS) {
        $rows[] = erp_invoice_form_line(array(), false);
    }

    return $rows;
}

/**
 * One editable line of the lines table.
 *
 * The same markup serves the rendered rows and the <template> the script
 * clones; the template is rendered with the index placeholder the script
 * replaces.
 *
 * @param liveform   $liveform
 * @param int|string $index  Row index, or the placeholder for the template
 * @param array      $line   Display row, see erp_invoice_form_line()
 * @return string  HTML <tr>
 */
function erp_invoice_form_line_row($liveform, $index, $line)
{
    $name = function ($field) use ($index) {
        return 'lines[' . $index . '][' . $field . ']';
    };
    $id = function ($field) use ($index) {
        return 'line_' . $field . '_' . $index;
    };

    $unit_codes = erp_invoice_form_unit_codes();
    $unit_options = array();
    foreach ($unit_codes as $code => $label) {
        $unit_options[h($code . ' - ' . $label)] = $code;
    }
    $unit_code = (string) $line['unit_code'];
    if (($unit_code !== '') && !isset($unit_codes[$unit_code])) {
        $unit_options[h($unit_code)] = $unit_code;
    }

    return '
            <tr class="erp-line" data-erp-line>
                <td class="text-body-secondary align-middle text-nowrap" data-erp-line-no>' . (is_int($index) ? ($index + 1) : '') . '</td>
                <td class="position-relative">
                    <input type="hidden" name="' . h($name('product_id')) . '" value="' . (int) $line['product_id'] . '" data-erp-product-id />
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => $id('product_search'), 'name' => $name('product_name'),
                        'value' => h($line['product_name']),
                        'class' => 'form-control form-control-sm erp-product-search', 'maxlength' => '100', 'autocomplete' => 'off',
                        'placeholder' => lang('Search products'), 'aria-label' => lang('Product'))) . '
                    <div class="dropdown-menu shadow-sm w-100" data-erp-product-results></div>
                </td>
                <td>' . $liveform->output_field(array(
                    'type' => 'text', 'id' => $id('description'), 'name' => $name('description'),
                    'value' => h($line['description']),
                    'class' => 'form-control form-control-sm', 'maxlength' => '255', 'autocomplete' => 'off',
                    'aria-label' => lang('Description'))) . '</td>
                <td>' . $liveform->output_field(array(
                    'type' => 'text', 'id' => $id('quantity'), 'name' => $name('quantity'),
                    'value' => h($line['quantity']),
                    'class' => 'form-control form-control-sm text-end', 'maxlength' => '12', 'inputmode' => 'decimal', 'autocomplete' => 'off',
                    'aria-label' => lang('Quantity'))) . '</td>
                <td>' . $liveform->output_field(array(
                    'type' => 'select', 'id' => $id('unit_code'), 'name' => $name('unit_code'),
                    'value' => h($unit_code),
                    'class' => 'form-select form-select-sm', 'options' => $unit_options,
                    'aria-label' => lang('Unit'))) . '</td>
                <td>' . $liveform->output_field(array(
                    'type' => 'text', 'id' => $id('unit_price'), 'name' => $name('unit_price'),
                    'value' => h($line['unit_price']),
                    'class' => 'form-control form-control-sm text-end', 'maxlength' => '15', 'inputmode' => 'decimal', 'autocomplete' => 'off',
                    'aria-label' => lang('Unit price'))) . '</td>
                <td>' . $liveform->output_field(array(
                    'type' => 'text', 'id' => $id('discount_rate'), 'name' => $name('discount_rate'),
                    'value' => h($line['discount_rate']),
                    'class' => 'form-control form-control-sm text-end', 'maxlength' => '7', 'inputmode' => 'decimal', 'autocomplete' => 'off',
                    'aria-label' => lang('Discount %'))) . '</td>
                <td>' . $liveform->output_field(array(
                    'type' => 'text', 'id' => $id('tax_rate'), 'name' => $name('tax_rate'),
                    'value' => h($line['tax_rate']),
                    'class' => 'form-control form-control-sm text-end', 'maxlength' => '7', 'inputmode' => 'decimal', 'autocomplete' => 'off',
                    'aria-label' => lang('VAT %'))) . '</td>
                <td class="text-end align-middle text-nowrap" data-erp-line-total></td>
                <td class="align-middle text-end">
                    <button type="button" class="btn btn-sm btn-ghost no-submit" data-erp-remove-line title="' . lang('Remove line') . '" aria-label="' . lang('Remove line') . '"><i class="bi bi-x-lg"></i></button>
                </td>
            </tr>';
}

/**
 * The cards of the typed invoice form.
 *
 * @param liveform $liveform
 * @param array    $options  lines (display rows), invoice_id
 * @return string  HTML
 */
function erp_invoice_form_cards($liveform, $options = array())
{
    $lines = isset($options['lines']) ? (array) $options['lines'] : erp_invoice_form_lines($liveform);
    $direction = (string) $liveform->get_field_value('direction');
    $is_purchase = ($direction === 'purchase');

    $account_options = array();
    $account_options[lang('Choose an account')] = '';
    // liveform prints option labels as-is; account titles come from contact names typed at checkout.
    foreach (erp_accounts(array('status' => 'active')) as $account) {
        $account_options[h($account['title'])] = (string) (int) $account['id'];
    }

    $direction_options = array();
    $direction_options[lang('Sales invoice')] = 'sales';
    $direction_options[lang('Purchase invoice')] = 'purchase';

    $base = erp_base_currency();
    $fx_on = erp_fx_enabled();

    $output_fx = '';

    if ($fx_on) {
        // Today's recorded rate for each allowed currency, so the operator can
        // see what the empty rate box will be filled with.
        $rate_hints = array();
        foreach (erp_fx_currencies() as $code) {
            $known = erp_fx_rate_for($code, date('Y-m-d'));
            $rate_hints[] = $code . ': ' . (is_array($known)
                ? (erp_fx_rate_out($known['rate']) . ' (' . prepare_form_data_for_output($known['rate_date'], 'date') . ')')
                : lang('no rate recorded'));
        }

        $output_fx = '
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="currency" class="form-label">' . lang('Currency') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'currency', 'name' => 'currency',
                        'class' => 'form-select', 'options' => erp_fx_currency_options())) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="exchange_rate" class="form-label">' . lang('Exchange Rate') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'exchange_rate', 'name' => 'exchange_rate',
                        'class' => 'form-control text-end', 'maxlength' => '20', 'inputmode' => 'decimal',
                        'autocomplete' => 'off', 'placeholder' => lang('rate of the issue date'))) . '
                    <div class="form-text">' . h(lang(array('string' => '{var:1} per unit of the document currency. Leave empty to use the recorded rate of the issue date.', 'vars' => $base))) . (!empty($rate_hints) ? (' ' . h(implode(', ', $rate_hints))) : '') . '</div>
                </div>';
    }

    $output_rows = '';
    foreach (array_values($lines) as $index => $line) {
        $output_rows .= erp_invoice_form_line_row($liveform, $index, $line);
    }

    $output_template = erp_invoice_form_line_row($liveform, '__INDEX__', erp_invoice_form_line(array(), false));

    $date_format = (defined('DATE_FORMAT') && (DATE_FORMAT == 'month_day')) ? 'month_day' : 'day_month';

    return '
    <div id="erp_invoice_editor"
         data-erp-editor
         data-products-url="get_erp_products.php"
         data-rate-url="get_erp_rate.php"
         data-fx="' . ($fx_on ? '1' : '0') . '"
         data-base-currency="' . h($base) . '"
         data-date-format="' . $date_format . '"
         data-max-lines="' . (int) ERP_MANUAL_MAX_LINES . '"
         data-text-no-results="' . lang('No products match.') . '"
         data-text-max-lines="' . lang(array('string' => 'An invoice can carry at most {var:1} lines.', 'vars' => ERP_MANUAL_MAX_LINES)) . '">
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Invoice') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-3 my-2">
                    <label for="direction" class="form-label">' . lang('Direction') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'direction', 'name' => 'direction',
                        'class' => 'form-select', 'options' => $direction_options)) . '
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <label for="account_id" class="form-label">' . lang('Account') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'account_id', 'name' => 'account_id',
                        'class' => 'form-select', 'options' => $account_options)) . '
                    <div class="form-text"><a href="add_erp_account.php" class="link-body-emphasis">' . lang('New account') . '</a></div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="issue_date" class="form-label">' . lang('Date') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'issue_date', 'name' => 'issue_date',
                        'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                        'autocomplete' => 'off')) . '
                    ' . get_date_picker_format() . '
                    <script>$("#issue_date, #due_date, #supplier_invoice_date").datepicker(datetimepicker_options);</script>
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="due_date" class="form-label">' . lang('Due Date') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'due_date', 'name' => 'due_date',
                        'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                        'autocomplete' => 'off')) . '
                    <div class="form-text">' . lang('Leave empty for the account\'s payment term, or the store default.') . '</div>
                </div>
                ' . $output_fx . '
            </div>
            <div class="row' . ($is_purchase ? '' : ' d-none') . '" data-erp-purchase-only>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="supplier_invoice_no" class="form-label">' . lang('Supplier Invoice Number') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'supplier_invoice_no', 'name' => 'supplier_invoice_no',
                        'class' => 'form-control', 'maxlength' => '32', 'autocomplete' => 'off')) . '
                    <div class="form-text">' . lang('The number printed on the bill the supplier sent. Our own number comes from the purchase series.') . '</div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="supplier_invoice_date" class="form-label">' . lang('Supplier Invoice Date') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'supplier_invoice_date', 'name' => 'supplier_invoice_date',
                        'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                        'autocomplete' => 'off')) . '
                </div>
            </div>
            <div class="row">
                <div class="col-12 my-2">
                    <label for="notes" class="form-label">' . lang('Notes') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'textarea', 'id' => 'notes', 'name' => 'notes',
                        'class' => 'form-control', 'rows' => '2', 'maxlength' => '5000')) . '
                    <div class="form-text">' . lang('Printed at the foot of the document.') . '</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>' . lang('Lines') . '</span>
            <button type="button" class="btn btn-sm btn-outline-secondary no-submit" data-erp-add-line><i class="bi bi-plus-lg me-2"></i>' . lang('Add line') . '</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-erp-lines-table>
                    <thead>
                        <tr>
                            <th style="width:2.5rem">#</th>
                            <th style="width:16%">' . lang('Product') . '</th>
                            <th>' . lang('Description') . '</th>
                            <th class="text-end" style="width:8%">' . lang('Quantity') . '</th>
                            <th style="width:11%">' . lang('Unit') . '</th>
                            <th class="text-end" style="width:11%">' . lang('Unit price') . '</th>
                            <th class="text-end" style="width:7%">' . lang('Discount %') . '</th>
                            <th class="text-end" style="width:7%">' . lang('VAT %') . '</th>
                            <th class="text-end" style="width:11%">' . lang('Line total') . '</th>
                            <th style="width:2.5rem"></th>
                        </tr>
                    </thead>
                    <tbody data-erp-lines>' . $output_rows . '</tbody>
                </table>
            </div>
            <template data-erp-line-template>' . $output_template . '</template>
            <div class="form-text">' . lang('Amounts are in the document currency. Empty rows are ignored. Picking a product fills the line; everything on it stays editable.') . '</div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-5 ms-auto">
            <div class="card my-4" data-erp-totals>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-1">
                        <span>' . lang('Subtotal') . '</span><b data-erp-total="subtotal">&mdash;</b>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>' . lang('Discount') . '</span><b>&minus;<span data-erp-total="discount_total">&mdash;</span></b>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>' . lang('VAT') . '</span><b data-erp-total="tax_total">&mdash;</b>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-top h5 mb-0">
                        <span>' . lang('Total') . '</span><b data-erp-total="grand_total">&mdash;</b>
                    </div>
                    <div class="form-text text-end">' . lang('A preview; the figures are worked out again when the invoice is saved.') . '</div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/erp_invoice_editor.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/erp_invoice_editor.js') . '"></script>';
}

/**
 * Prefill the header fields of an empty editor, or of a stored draft.
 *
 * Nothing is assigned when the form is already in the session: that is the
 * form coming back after a refused save, and what the operator typed wins.
 *
 * @param liveform   $liveform
 * @param array|null $invoice  erp_invoices row of the draft, or null for a new one
 * @param string     $direction  Default direction for a new invoice
 * @return void
 */
function erp_invoice_form_prefill($liveform, $invoice = null, $direction = 'sales')
{
    if ($liveform->field_in_session('issue_date')) {
        return;
    }

    $date_out = function ($date) {
        $date = (string) $date;
        return (($date !== '') && ($date !== '0000-00-00')) ? prepare_form_data_for_output($date, 'date') : '';
    };

    if (is_array($invoice)) {
        $base = erp_base_currency();
        $currency = strtoupper(trim((string) $invoice['currency']));

        $liveform->assign_field_value('direction', ((string) $invoice['direction'] === 'purchase') ? 'purchase' : 'sales');
        $liveform->assign_field_value('account_id', (string) (int) $invoice['account_id']);
        $liveform->assign_field_value('issue_date', $date_out($invoice['issue_date']));
        $liveform->assign_field_value('due_date', $date_out($invoice['due_date']));
        $liveform->assign_field_value('currency', $currency);
        // A rate the draft carries is shown; a draft saved without one keeps
        // the box empty so the issue date's recorded rate is used.
        $liveform->assign_field_value('exchange_rate', (($currency !== $base) && ((float) $invoice['exchange_rate'] > 0)) ? erp_fx_rate_out((float) $invoice['exchange_rate']) : '');
        $liveform->assign_field_value('supplier_invoice_no', (string) $invoice['supplier_invoice_no']);
        $liveform->assign_field_value('supplier_invoice_date', $date_out($invoice['supplier_invoice_date']));
        $liveform->assign_field_value('notes', (string) ($invoice['notes'] ?? ''));
        return;
    }

    $liveform->assign_field_value('direction', ($direction === 'purchase') ? 'purchase' : 'sales');
    $liveform->assign_field_value('issue_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));
    // Left empty so the account's payment term applies when the form is
    // saved; a date typed here always wins over the term.
    $liveform->assign_field_value('due_date', '');
    $liveform->assign_field_value('currency', erp_base_currency());
    $liveform->assign_field_value('exchange_rate', '');
}

/**
 * Read the posted form back into what erp_invoice_draft_save() takes.
 *
 * The rate of the issue date is used unless the operator typed another one;
 * a typed rate that matches the recorded one keeps the feed as its source.
 * With no rate typed and none recorded the result says so through
 * rate_missing, and the caller decides: a draft may be saved without one,
 * an issue may not.
 *
 * @param liveform $liveform
 * @param array    $user
 * @return array ['success' => bool, 'data' => array, 'field' => string, 'error' => string, 'rate_missing' => bool]
 */
function erp_invoice_form_read($liveform, $user)
{
    $fail = function ($field, $message) {
        return array('success' => false, 'data' => array(), 'field' => $field, 'error' => $message, 'rate_missing' => false);
    };

    $date_in = function ($field, $required) use ($liveform, $fail) {
        $typed = trim((string) $liveform->get_field_value($field));
        if ($typed === '') {
            return $required ? date('Y-m-d') : '';
        }
        if (validate_date($typed) == false) {
            return null;
        }
        return prepare_form_data_for_input($typed, 'date');
    };

    $issue_date = $date_in('issue_date', true);
    if ($issue_date === null) {
        return $fail('issue_date', lang('Please enter a valid date.'));
    }

    $due_date = $date_in('due_date', false);
    if ($due_date === null) {
        return $fail('due_date', lang('Please enter a valid date.'));
    }
    if ($due_date === '') {
        $due_date = erp_account_due_date((int) $liveform->get_field_value('account_id'), $issue_date);
    }

    $direction = ((string) $liveform->get_field_value('direction') === 'purchase') ? 'purchase' : 'sales';

    $supplier_invoice_date = '';
    if ($direction === 'purchase') {
        $supplier_invoice_date = $date_in('supplier_invoice_date', false);
        if ($supplier_invoice_date === null) {
            return $fail('supplier_invoice_date', lang('Please enter a valid date.'));
        }
    }

    $base = erp_base_currency();
    $currency = erp_fx_enabled() ? strtoupper(trim((string) $liveform->get_field_value('currency'))) : $base;
    if ($currency === '') {
        $currency = $base;
    }

    $exchange_rate = 1.0;
    $rate_date = $issue_date;
    $rate_source = 'base';
    $rate_missing = false;

    if ($currency !== $base) {
        if (!erp_fx_currency_allowed($currency)) {
            return $fail('currency', lang('That currency is not enabled for the ERP.'));
        }

        $recorded = erp_fx_rate_for($currency, $issue_date);
        $typed = erp_fx_rate_in($liveform->get_field_value('exchange_rate'));

        if ($typed > 0) {
            $exchange_rate = $typed;
            $rate_source = (is_array($recorded) && (abs($recorded['rate'] - $typed) < 0.0000005)) ? $recorded['source'] : 'manual';
            $rate_date = ($rate_source === 'manual') ? $issue_date : $recorded['rate_date'];
        } elseif (is_array($recorded)) {
            $exchange_rate = $recorded['rate'];
            $rate_date = $recorded['rate_date'];
            $rate_source = $recorded['source'];
        } else {
            $exchange_rate = 0.0;
            $rate_source = '';
            $rate_missing = true;
        }
    }

    $posted_lines = $liveform->get_field_value('lines');
    $lines = array();

    if (is_array($posted_lines)) {
        if (count($posted_lines) > ERP_MANUAL_MAX_LINES) {
            return $fail('_error', lang(array('string' => 'An invoice can carry at most {var:1} lines.', 'vars' => ERP_MANUAL_MAX_LINES)));
        }

        foreach (array_values($posted_lines) as $line) {
            $line = (array) $line;
            $lines[] = array(
                'product_id' => (int) ($line['product_id'] ?? 0),
                'description' => (string) ($line['description'] ?? ''),
                'quantity' => erp_fx_rate_in($line['quantity'] ?? ''),
                'unit_code' => (string) ($line['unit_code'] ?? 'C62'),
                'unit_price' => erp_kurus($line['unit_price'] ?? ''),
                'discount_rate' => erp_fx_rate_in($line['discount_rate'] ?? ''),
                'tax_rate' => erp_fx_rate_in($line['tax_rate'] ?? ''),
            );
        }
    }

    return array(
        'success' => true,
        'field' => '',
        'error' => '',
        'rate_missing' => $rate_missing,
        'data' => array(
            'direction' => $direction,
            'account_id' => (int) $liveform->get_field_value('account_id'),
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'exchange_rate_date' => $rate_date,
            'exchange_rate_source' => $rate_source,
            'issue_date' => $issue_date,
            'due_date' => $due_date,
            'supplier_invoice_no' => (string) $liveform->get_field_value('supplier_invoice_no'),
            'supplier_invoice_date' => $supplier_invoice_date,
            'notes' => (string) $liveform->get_field_value('notes'),
            'lines' => $lines,
            'created_by' => (int) $user['id'],
        ),
    );
}

/**
 * The message shown when a foreign-currency document has no rate to use.
 *
 * @param string $currency
 * @param string $issue_date  Y-m-d
 * @return string
 */
function erp_invoice_form_rate_missing_message($currency, $issue_date)
{
    return lang(array(
        'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
        'vars' => array($currency, prepare_form_data_for_output($issue_date, 'date')),
    ));
}
