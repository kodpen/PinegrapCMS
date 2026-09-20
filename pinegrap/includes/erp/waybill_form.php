<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the delivery note form: the cards add_erp_waybill.php draws, and the
 * reading of what comes back from them.
 *
 * The lines table is the invoice editor's, without the money columns: the
 * same markup and the same script, which only touches the fields it finds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

// The unit list and the line editor's conventions are the invoice form's.
require_once(PG_FUNCTIONS_DIR . '/includes/erp/invoice_form.php');

/** Empty rows a fresh form opens with. */
if (!defined('ERP_WAYBILL_FORM_BLANK_ROWS')) {
    define('ERP_WAYBILL_FORM_BLANK_ROWS', 4);
}

/**
 * One line as a display row: what was typed, or a stored line written the way
 * the inputs read it.
 *
 * @param array $source  A posted lines[n] array, a stored erp_waybill_items
 *                       row, or a line from erp_waybill_data_from_order()
 * @return array
 */
function erp_waybill_form_line($source)
{
    $source = (array) $source;

    $quantity = $source['quantity'] ?? '';
    if (is_int($quantity) || is_float($quantity)) {
        $quantity = rtrim(rtrim(number_format((float) $quantity, 4, '.', ''), '0'), '.');
        $quantity = ($quantity === '') ? '0' : $quantity;
    } elseif (is_string($quantity) && preg_match('/^\d+\.\d{4}$/', $quantity) === 1) {
        // A DECIMAL(15,4) read back from the table.
        $quantity = rtrim(rtrim($quantity, '0'), '.');
    }

    return array(
        'product_id' => (int) ($source['product_id'] ?? 0),
        'product_name' => trim((string) ($source['product_name'] ?? '')),
        'description' => trim((string) ($source['description'] ?? '')),
        'quantity' => trim((string) $quantity),
        'unit_code' => trim((string) ($source['unit_code'] ?? 'C62')),
    );
}

/**
 * The rows to show: what was posted when the form came back refused,
 * otherwise the rows handed in (an order's items), otherwise blank rows.
 *
 * @param liveform $liveform
 * @param array    $given  Lines to start from
 * @return array
 */
function erp_waybill_form_lines($liveform, $given = array())
{
    $posted = $liveform->get_field_value('lines');
    $rows = array();

    if (is_array($posted) && !empty($posted)) {
        foreach (array_slice(array_values($posted), 0, ERP_WAYBILL_MAX_LINES) as $line) {
            $rows[] = erp_waybill_form_line($line);
        }
    } else {
        foreach ((array) $given as $line) {
            $rows[] = erp_waybill_form_line($line);
        }
    }

    while (count($rows) < ERP_WAYBILL_FORM_BLANK_ROWS) {
        $rows[] = erp_waybill_form_line(array());
    }

    return $rows;
}

/**
 * One editable line. The same markup serves the rendered rows and the
 * <template> the editor script clones.
 *
 * @param liveform   $liveform
 * @param int|string $index  Row index, or the placeholder for the template
 * @param array      $line   Display row
 * @return string
 */
function erp_waybill_form_line_row($liveform, $index, $line)
{
    $name = function ($field) use ($index) {
        return 'lines[' . $index . '][' . $field . ']';
    };
    $id = function ($field) use ($index) {
        return 'line_' . $field . '_' . $index;
    };

    $unit_codes = erp_waybill_unit_codes();
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
                    <div class="form-text small mt-1" data-erp-product-hint></div>
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
                <td class="align-middle text-end">
                    <button type="button" class="btn btn-sm btn-ghost no-submit" data-erp-remove-line title="' . lang('Remove line') . '" aria-label="' . lang('Remove line') . '"><i class="bi bi-x-lg"></i></button>
                </td>
            </tr>';
}

/**
 * The store's country list as select options, code-keyed.
 *
 * @return array  label => code
 */
function erp_waybill_country_options()
{
    $options = array();

    foreach ((array) db_items("SELECT name, code FROM countries ORDER BY default_selected DESC, name ASC") as $row) {
        $options[h((string) $row['name'])] = strtoupper((string) $row['code']);
    }

    if (empty($options)) {
        $options['TR'] = 'TR';
    }

    return $options;
}

/**
 * Put the values of a note-to-be into the form, unless the form is already
 * carrying what the operator typed into a save that came back refused.
 *
 * @param liveform $liveform
 * @param array    $data  See erp_waybill_header_build(); dates Y-m-d
 * @return void
 */
function erp_waybill_form_prefill($liveform, $data)
{
    if ($liveform->field_in_session('account_id')) {
        return;
    }

    $date_out = function ($date) {
        $date = (string) $date;
        return ($date !== '' && $date !== '0000-00-00') ? prepare_form_data_for_output($date, 'date') : '';
    };

    $liveform->assign_field_value('account_id', (string) (int) ($data['account_id'] ?? 0));
    $liveform->assign_field_value('order_id', (string) (int) ($data['order_id'] ?? 0));
    $liveform->assign_field_value('invoice_id', (string) (int) ($data['invoice_id'] ?? 0));
    $liveform->assign_field_value('issue_date', $date_out($data['issue_date'] ?? date('Y-m-d')));
    $liveform->assign_field_value('ship_date', $date_out($data['ship_date'] ?? ''));
    $liveform->assign_field_value('ship_time', (string) ($data['ship_time'] ?? ''));

    foreach (array('carrier_title', 'carrier_vkn', 'plate', 'driver_name', 'driver_tckn',
        'ship_to_title', 'ship_to_address', 'ship_to_district', 'ship_to_city', 'notes') as $field) {
        $liveform->assign_field_value($field, (string) ($data[$field] ?? ''));
    }

    $country = strtoupper(trim((string) ($data['ship_to_country'] ?? '')));
    $liveform->assign_field_value('ship_to_country', ($country !== '') ? $country : erp_default_country_code());
}

/**
 * The cards of the form.
 *
 * @param liveform $liveform
 * @param array    $options  lines (display rows), order (the orders row when
 *                           the note is for an order), invoice (the
 *                           erp_invoices row when the note is for an invoice)
 * @return string  HTML
 */
function erp_waybill_form_cards($liveform, $options = array())
{
    $lines = isset($options['lines']) ? (array) $options['lines'] : erp_waybill_form_lines($liveform);
    $order = isset($options['order']) && is_array($options['order']) ? $options['order'] : null;

    $account_options = array();
    $account_options[lang('Choose an account')] = '';
    // liveform prints option labels as-is; account titles come from names typed at checkout.
    foreach (erp_accounts(array('status' => 'active')) as $account) {
        $account_options[h($account['title'])] = (string) (int) $account['id'];
    }

    $output_rows = '';
    foreach (array_values($lines) as $index => $line) {
        $output_rows .= erp_waybill_form_line_row($liveform, $index, $line);
    }

    $output_template = erp_waybill_form_line_row($liveform, '__INDEX__', erp_waybill_form_line(array()));

    $invoice = isset($options['invoice']) && is_array($options['invoice']) ? $options['invoice'] : null;

    $output_order = '';
    if ($order !== null) {
        $output_order = '
                <div class="col-12 my-2">
                    <div class="alert alert-secondary mb-0 py-2">
                        <i class="bi bi-cart me-2"></i>' . h(lang(array('string' => 'For order #{var:1}. The goods and the address are the order\'s; the ship date and the carrier are what the order screen recorded. Correct anything that differs from what left the door.', 'vars' => (string) $order['order_number'])))
                        . (($invoice !== null) ? ' ' . h(lang(array('string' => 'The note is tied to invoice {var:1}.', 'vars' => (string) $invoice['full_number']))) : '') . '
                    </div>
                </div>';
    } elseif ($invoice !== null) {
        $output_order = '
                <div class="col-12 my-2">
                    <div class="alert alert-secondary mb-0 py-2">
                        <i class="bi bi-receipt me-2"></i>' . h(lang(array('string' => 'For invoice {var:1}. The goods are its lines; the address is the account\'s. Say how they went and correct what differs.', 'vars' => (string) $invoice['full_number']))) . '
                    </div>
                </div>';
    }

    return '
    <div id="erp_waybill_editor"
         data-erp-editor
         data-products-url="get_erp_products.php"
         data-max-lines="' . (int) ERP_WAYBILL_MAX_LINES . '"
         data-text-no-results="' . lang('No products match.') . '"
         data-text-stock="' . h(lang('In stock: {var:1}')) . '"
         data-text-no-stock-tracking="' . h(lang('Stock not tracked')) . '"
         data-text-out-of-stock="' . h(lang('Out of stock')) . '"
         data-text-disabled="' . h(lang('not on sale')) . '"
         data-text-sku="' . h(lang('SKU')) . '"
         data-text-over-stock="' . h(lang('More than is in stock ({var:1}).')) . '"
         data-text-max-lines="' . lang(array('string' => 'A delivery note can carry at most {var:1} lines.', 'vars' => ERP_WAYBILL_MAX_LINES)) . '">
    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Delivery Note') . '
        </div>
        <div class="card-body">
            <div class="row">' . $output_order . '
                <div class="col-12 col-lg-6 my-2">
                    <label for="account_id" class="form-label">' . lang('Account') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'account_id', 'name' => 'account_id',
                        'class' => 'form-select', 'options' => $account_options)) . '
                    <div class="form-text"><a href="add_erp_account.php" class="link-body-emphasis">' . lang('New account') . '</a></div>
                </div>
                <div class="col-6 col-lg-2 my-2">
                    <label for="issue_date" class="form-label">' . lang('Date') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'issue_date', 'name' => 'issue_date',
                        'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                        'autocomplete' => 'off')) . '
                    ' . get_date_picker_format() . '
                    <script>$("#issue_date, #ship_date").datepicker(datetimepicker_options);</script>
                </div>
                <div class="col-6 col-lg-2 my-2">
                    <label for="ship_date" class="form-label">' . lang('Shipment Date') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'ship_date', 'name' => 'ship_date',
                        'class' => 'form-control', 'size' => '10', 'maxlength' => '10',
                        'autocomplete' => 'off')) . '
                    <div class="form-text">' . lang('Empty means the date above.') . '</div>
                </div>
                <div class="col-6 col-lg-2 my-2">
                    <label for="ship_time" class="form-label">' . lang('Shipment Time') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'ship_time', 'name' => 'ship_time',
                        'class' => 'form-control', 'size' => '5', 'maxlength' => '5',
                        'placeholder' => 'HH:MM', 'inputmode' => 'numeric', 'autocomplete' => 'off')) . '
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Delivered to') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-6 my-2">
                    <label for="ship_to_title" class="form-label">' . lang('Recipient') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'ship_to_title', 'name' => 'ship_to_title',
                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                    <div class="form-text">' . lang('Empty means the account itself.') . '</div>
                </div>
                <div class="col-12 col-lg-6 my-2">
                    <label for="ship_to_address" class="form-label">' . lang('Address') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'ship_to_address', 'name' => 'ship_to_address',
                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-sm-4 my-2">
                    <label for="ship_to_district" class="form-label">' . lang('District') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'ship_to_district', 'name' => 'ship_to_district',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-4 my-2">
                    <label for="ship_to_city" class="form-label">' . lang('City') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'ship_to_city', 'name' => 'ship_to_city',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-4 my-2">
                    <label for="ship_to_country" class="form-label">' . lang('Country') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'select', 'id' => 'ship_to_country', 'name' => 'ship_to_country',
                        'class' => 'form-select', 'options' => erp_waybill_country_options())) . '
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Transport') . '
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-5 my-2">
                    <label for="carrier_title" class="form-label">' . lang('Carrier') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'carrier_title', 'name' => 'carrier_title',
                        'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                    <div class="form-text">' . lang('The shipping company, or the store itself when its own vehicle delivers.') . '</div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="carrier_vkn" class="form-label">' . lang('Carrier VKN') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'carrier_vkn', 'name' => 'carrier_vkn',
                        'class' => 'form-control', 'maxlength' => '11', 'inputmode' => 'numeric', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-4 my-2">
                    <label for="plate" class="form-label">' . lang('Plate') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'plate', 'name' => 'plate',
                        'class' => 'form-control', 'maxlength' => '20', 'autocomplete' => 'off')) . '
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-lg-5 my-2">
                    <label for="driver_name" class="form-label">' . lang('Driver') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'driver_name', 'name' => 'driver_name',
                        'class' => 'form-control', 'maxlength' => '100', 'autocomplete' => 'off')) . '
                </div>
                <div class="col-12 col-sm-6 col-lg-3 my-2">
                    <label for="driver_tckn" class="form-label">' . lang('Driver ID No') . '</label>
                    ' . $liveform->output_field(array(
                        'type' => 'text', 'id' => 'driver_tckn', 'name' => 'driver_tckn',
                        'class' => 'form-control', 'maxlength' => '11', 'inputmode' => 'numeric', 'autocomplete' => 'off')) . '
                </div>
            </div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>' . lang('Lines') . '</span>
            <button type="button" class="btn btn-sm btn-outline-secondary no-submit" data-erp-add-line><i class="bi bi-plus-lg me-2"></i>' . lang('Add line') . '</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-erp-lines-table>
                    <thead>
                        <tr>
                            <th style="width:3rem">#</th>
                            <th style="min-width:14rem">' . lang('Product') . '</th>
                            <th style="min-width:16rem">' . lang('Description') . '</th>
                            <th style="width:8rem" class="text-end">' . lang('Quantity') . '</th>
                            <th style="width:11rem">' . lang('Unit') . '</th>
                            <th style="width:3rem"></th>
                        </tr>
                    </thead>
                    <tbody data-erp-lines>' . $output_rows . '</tbody>
                </table>
            </div>
            <template data-erp-line-template>' . $output_template . '</template>
        </div>
        <div class="card-footer bg-reset border-0">
            <div class="form-text">' . lang('Choose a product to fill the description, or type a description; the quantity is what is on the vehicle. Prices are not part of a delivery note; they come when it is invoiced.') . '</div>
        </div>
    </div>

    <div class="card my-4">
        <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
            ' . lang('Notes') . '
        </div>
        <div class="card-body">
            ' . $liveform->output_field(array(
                'type' => 'textarea', 'id' => 'notes', 'name' => 'notes',
                'class' => 'form-control', 'rows' => '2', 'maxlength' => '5000',
                'aria-label' => lang('Notes'))) . '
            <div class="form-text">' . lang('Printed at the foot of the document.') . '</div>
        </div>
    </div>
    </div>
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/erp_invoice_editor.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/erp_invoice_editor.js') . '"></script>';
}

/**
 * Read the form back into the data erp_waybill_create() takes.
 *
 * @param liveform $liveform
 * @param array    $user
 * @return array ['success' => bool, 'data' => array, 'field' => string, 'error' => string]
 */
function erp_waybill_form_read($liveform, $user)
{
    $fail = function ($field, $message) {
        return array('success' => false, 'data' => array(), 'field' => $field, 'error' => $message);
    };

    $date_in = function ($field) use ($liveform) {
        $typed = trim((string) $liveform->get_field_value($field));
        if ($typed === '') {
            return '';
        }
        if (validate_date($typed) == false) {
            return null;
        }
        return prepare_form_data_for_input($typed, 'date');
    };

    $issue_date = $date_in('issue_date');
    if ($issue_date === null) {
        return $fail('issue_date', lang('Please enter a valid date.'));
    }

    $ship_date = $date_in('ship_date');
    if ($ship_date === null) {
        return $fail('ship_date', lang('Please enter a valid shipment date.'));
    }

    $lines = $liveform->get_field_value('lines');

    $data = array(
        'account_id' => (int) $liveform->get_field_value('account_id'),
        'order_id' => (int) $liveform->get_field_value('order_id'),
        'invoice_id' => (int) $liveform->get_field_value('invoice_id'),
        'issue_date' => ($issue_date !== '') ? $issue_date : date('Y-m-d'),
        'ship_date' => $ship_date,
        'ship_time' => trim((string) $liveform->get_field_value('ship_time')),
        'carrier_title' => (string) $liveform->get_field_value('carrier_title'),
        'carrier_vkn' => (string) $liveform->get_field_value('carrier_vkn'),
        'plate' => (string) $liveform->get_field_value('plate'),
        'driver_name' => (string) $liveform->get_field_value('driver_name'),
        'driver_tckn' => (string) $liveform->get_field_value('driver_tckn'),
        'ship_to_title' => (string) $liveform->get_field_value('ship_to_title'),
        'ship_to_address' => (string) $liveform->get_field_value('ship_to_address'),
        'ship_to_district' => (string) $liveform->get_field_value('ship_to_district'),
        'ship_to_city' => (string) $liveform->get_field_value('ship_to_city'),
        'ship_to_country' => (string) $liveform->get_field_value('ship_to_country'),
        'notes' => (string) $liveform->get_field_value('notes'),
        'lines' => is_array($lines) ? $lines : array(),
        'created_by' => (int) ($user['id'] ?? 0),
    );

    // The recipient left empty is the account: the note is delivered to the
    // customer at their own address.
    if (trim($data['ship_to_title']) === '') {
        $account = erp_account($data['account_id']);
        if (is_array($account)) {
            $data['ship_to_title'] = (string) $account['title'];
            if (trim($data['ship_to_address']) === '') {
                $data['ship_to_address'] = (string) $account['address'];
                $data['ship_to_district'] = (string) $account['district'];
                $data['ship_to_city'] = (string) $account['city'];
                $data['ship_to_country'] = (string) $account['country_code'];
            }
        }
    }

    return array('success' => true, 'data' => $data, 'field' => '', 'error' => '');
}
