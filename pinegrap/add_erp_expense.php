<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - record an expense, or change one.
 *
 * The receipt as it is: the total printed on it (or the net), the VAT rate
 * and, when the receipt shows it, the VAT amount to the kurus. Paid now from
 * a till or bank account, or left to pay later. A picture or a PDF of the
 * receipt can go with it.
 *
 * Paying needs the cash right; without it an expense is recorded as to pay.
 * A paid expense keeps its figures and date, which are what left the till.
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
$liveform = new liveform('add_erp_expense');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_expenses.php';
$can_cash = defined('USER_MANAGE_ERP_CASH') && USER_MANAGE_ERP_CASH;

if (!erp_expenses_ready()) {
    $liveform_list = new liveform('erp_expenses');
    $liveform_list->mark_error('_error', lang('Expenses come with the software update; run the update to use them.'));
    go($list_url);
}

$id = (int) ($_REQUEST['id'] ?? 0);
$existing = ($id > 0) ? erp_expense($id) : null;

if (($id > 0) && ($existing === null)) {
    $liveform_list = new liveform('erp_expenses');
    $liveform_list->mark_error('_error', lang('The expense could not be found.'));
    go($list_url);
}

if (is_array($existing) && ((string) $existing['status'] === 'cancelled')) {
    $liveform_view = new liveform('edit_erp_expense');
    $liveform_view->mark_error('_error', lang('A cancelled expense is not changed.'));
    go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_expense.php?id=' . $id);
}

$paid = is_array($existing) && ((string) $existing['status'] === 'paid');
$fx_on = erp_fx_enabled();
$base = erp_base_currency();
$page_title = is_array($existing) ? lang('Change the expense') : lang('New expense');
$back = PATH . SOFTWARE_DIRECTORY . '/add_erp_expense.php' . (($id > 0) ? ('?id=' . $id) : '');

if (!$_POST) {

    if ($liveform->field_in_session('expense_date') == false) {
        if (is_array($existing)) {
            $currency = strtoupper((string) $existing['currency']);
            $liveform->assign_field_value('expense_date', prepare_form_data_for_output((string) $existing['expense_date'], 'date'));
            $liveform->assign_field_value('category_id', (string) (int) $existing['category_id']);
            $liveform->assign_field_value('supplier', (string) $existing['supplier']);
            $liveform->assign_field_value('supplier_tax_number', (string) $existing['supplier_tax_number']);
            $liveform->assign_field_value('document_no', (string) $existing['document_no']);
            $liveform->assign_field_value('description', (string) $existing['description']);
            $liveform->assign_field_value('amount', erp_money_out_currency((int) $existing['total_amount'], $currency, false));
            $liveform->assign_field_value('includes_tax', '1');
            $liveform->assign_field_value('tax_rate', erp_quantity_text((float) $existing['tax_rate']));
            $liveform->assign_field_value('tax_amount', erp_money_out_currency((int) $existing['tax_amount'], $currency, false));
            $liveform->assign_field_value('tax_deductible', ((int) $existing['tax_deductible'] === 1) ? '1' : '');
            $liveform->assign_field_value('currency', $currency);
            $liveform->assign_field_value('exchange_rate', ($currency !== $base) ? erp_fx_rate_out((float) $existing['exchange_rate']) : '');
            $liveform->assign_field_value('pay', '0');
            $liveform->assign_field_value('due_date', ((string) $existing['due_date'] > '0000-00-00') ? prepare_form_data_for_output((string) $existing['due_date'], 'date') : '');
        } else {
            // A new receipt: today, tax included, the store's usual rate, and
            // the category and till of the last expense, which is usually the
            // same kind of spending.
            $last = db_item("SELECT category_id, cash_account_id, payment_method FROM erp_expenses WHERE status <> 'cancelled' ORDER BY id DESC LIMIT 1");
            $liveform->assign_field_value('expense_date', prepare_form_data_for_output(date('Y-m-d'), 'date'));
            $liveform->assign_field_value('includes_tax', '1');
            $liveform->assign_field_value('tax_deductible', erp_expense_category_deductible(is_array($last) ? (int) $last['category_id'] : 0) ? '1' : '');
            $liveform->assign_field_value('tax_rate', erp_quantity_text(erp_default_tax_rate()));
            $liveform->assign_field_value('currency', $base);
            $liveform->assign_field_value('pay', $can_cash ? '1' : '0');
            $liveform->assign_field_value('payment_method', is_array($last) && ((string) $last['payment_method'] !== '') ? (string) $last['payment_method'] : 'cash');

            if (is_array($last)) {
                $liveform->assign_field_value('category_id', (string) (int) $last['category_id']);
                $liveform->assign_field_value('cash_account_id', ((int) $last['cash_account_id'] > 0) ? (string) (int) $last['cash_account_id'] : '');
            }
        }
    }

    $category_options = array(lang('Choose a category') => '');
    // What "Tax taken back" starts from for each category, for a new expense.
    $category_deductible = array();
    foreach (erp_expense_categories() as $category) {
        $category_deductible[(int) $category['id']] = !isset($category['tax_deductible']) || ((int) $category['tax_deductible'] === 1);
        // A category that was switched off stays on the expense it already holds.
        if (((int) $category['is_active'] !== 1) && (!is_array($existing) || ((int) $existing['category_id'] !== (int) $category['id']))) {
            continue;
        }
        $category_options[h($category['name'] . (((string) $category['code'] !== '') ? ' (' . $category['code'] . ')' : ''))] = (string) (int) $category['id'];
    }

    $till_options = array(lang('Choose a till or bank account') => '');
    foreach ((array) db_items("SELECT id, name, currency FROM erp_cash_accounts WHERE is_active = 1 ORDER BY sort_order ASC, name ASC") as $till) {
        $till_options[h($till['name'] . ($fx_on ? (' (' . strtoupper(trim((string) $till['currency'])) . ')') : ''))] = (string) (int) $till['id'];
    }

    $method_options = array(
        lang('Cash') => 'cash',
        lang('Bank transfer') => 'transfer',
        lang('Credit card') => 'card',
        lang('Cheque') => 'cheque',
        lang('Other') => 'other',
    );

    // The rates the store uses, for the rate box to offer.
    $rates = array(0.0, erp_default_tax_rate());
    foreach ((array) db_items("SELECT DISTINCT tax_rate FROM erp_invoice_items WHERE tax_rate > 0 ORDER BY tax_rate ASC LIMIT 20") as $row) {
        $rates[] = (float) $row['tax_rate'];
    }
    if (erp_account_country() === 'TR') {
        $rates = array_merge($rates, array(1.0, 10.0, 20.0));
    }
    $rates = array_unique(array_map(function ($rate) {
        return round((float) $rate, 3);
    }, $rates));
    sort($rates);

    $output_rates = '';
    foreach ($rates as $rate) {
        $output_rates .= '<option value="' . h(erp_quantity_text($rate)) . '"></option>';
    }

    // Suppliers written before, to pick again.
    $output_suppliers = '';
    foreach ((array) db_items("SELECT supplier, MAX(supplier_tax_number) AS tax_number FROM erp_expenses
        WHERE supplier <> '' GROUP BY supplier ORDER BY MAX(id) DESC LIMIT 60") as $row) {
        $output_suppliers .= '<option value="' . h((string) $row['supplier']) . '" data-tax-number="' . h((string) $row['tax_number']) . '"></option>';
    }

    $currency_label = h(strtoupper((string) ($liveform->get_field_value('currency') ?: $base)));

    // The payment: now from a till, or later. A paid expense says where the
    // money went instead.
    if ($paid) {
        $output_payment = '
                        <div class="alert alert-info mb-0">'
                            . h(lang(array(
                                'string' => 'Paid on {var:1} from {var:2}. The amount and the date are what left the till; to change them, cancel the expense and record it again.',
                                'vars' => array(prepare_form_data_for_output((string) $existing['paid_date'], 'date', false), (string) $existing['till_name']),
                            ))) . '
                        </div>';
    } else {
        $output_payment = ($can_cash
            ? '
                        <div class="d-flex flex-wrap gap-3 mb-2">
                            <div class="form-check">
                                ' . $liveform->output_field(array('type' => 'radio', 'id' => 'pay_now', 'name' => 'pay', 'value' => '1', 'class' => 'form-check-input')) . '
                                <label class="form-check-label" for="pay_now">' . lang('Paid now') . '</label>
                            </div>
                            <div class="form-check">
                                ' . $liveform->output_field(array('type' => 'radio', 'id' => 'pay_later', 'name' => 'pay', 'value' => '0', 'class' => 'form-check-input')) . '
                                <label class="form-check-label" for="pay_later">' . lang('To pay later') . '</label>
                            </div>
                        </div>
                        <div class="row" id="erp_expense_pay_now">'
            : '
                        <input type="hidden" name="pay" value="0" />
                        <div class="form-text mb-2">' . lang('Paying from a till needs the right to the tills; the expense is recorded as to pay.') . '</div>
                        <div class="row" id="erp_expense_pay_now" hidden>') . '
                            <div class="col-12 col-lg-5 my-2">
                                <label for="cash_account_id" class="form-label">' . lang('Paid from') . '</label>
                                ' . $liveform->output_field(array('type' => 'select', 'id' => 'cash_account_id', 'name' => 'cash_account_id', 'class' => 'form-select', 'options' => $till_options)) . '
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4 my-2">
                                <label for="payment_method" class="form-label">' . lang('Payment Method') . '</label>
                                ' . $liveform->output_field(array('type' => 'select', 'id' => 'payment_method', 'name' => 'payment_method', 'class' => 'form-select', 'options' => $method_options)) . '
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="paid_date" class="form-label">' . lang('Paid on') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'paid_date', 'name' => 'paid_date', 'class' => 'form-control', 'maxlength' => '10', 'autocomplete' => 'off', 'placeholder' => lang('the expense date'))) . '
                            </div>
                        </div>
                        <div class="row" id="erp_expense_pay_later">
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="due_date" class="form-label">' . lang('Due Date') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'due_date', 'name' => 'due_date', 'class' => 'form-control', 'maxlength' => '10', 'autocomplete' => 'off')) . '
                                <div class="form-text">' . lang('Optional. Shown on the expenses list when it has passed.') . '</div>
                            </div>
                        </div>';
    }

    // The figures: to type for an expense still to pay, to read for a paid
    // one, whose amount is what left the till.
    if ($paid) {
        $paid_currency = strtoupper((string) $existing['currency']);
        $output_money_card = '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Amount and tax') . '</div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-4 mb-3">
                            <span>' . lang('Net') . ': <b>' . h(erp_money_out_currency((int) $existing['net_amount'], $paid_currency)) . '</b></span>
                            <span>' . h(erp_tax_label('tax')) . ' ' . h(erp_percent_text((float) $existing['tax_rate'])) . ': <b>' . h(erp_money_out_currency((int) $existing['tax_amount'], $paid_currency)) . '</b></span>
                            <span>' . lang('Total') . ': <b>' . h(erp_money_out_currency((int) $existing['total_amount'], $paid_currency)) . '</b></span>
                        </div>
                        <div class="form-check form-switch">
                            ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'tax_deductible', 'name' => 'tax_deductible', 'value' => '1', 'class' => 'form-check-input')) . '
                            <label class="form-check-label" for="tax_deductible">' . lang('Tax taken back') . '</label>
                        </div>
                    </div>
                </div>';
    } else {
        $output_money_card = '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Amount and tax') . '</div>
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="amount" class="form-label">' . lang('Amount') . '</label>
                                <div class="input-group">
                                    ' . $liveform->output_field(array('type' => 'text', 'id' => 'amount', 'name' => 'amount', 'class' => 'form-control text-end', 'maxlength' => '15', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'required' => 'required')) . '
                                    <span class="input-group-text" id="erp_expense_currency_label">' . $currency_label . '</span>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <div class="form-check form-switch mb-2">
                                    ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'includes_tax', 'name' => 'includes_tax', 'value' => '1', 'class' => 'form-check-input')) . '
                                    <label class="form-check-label" for="includes_tax">' . h(erp_tax_label('included')) . '</label>
                                </div>
                            </div>
                            <div class="col-6 col-lg-2 my-2">
                                <label for="tax_rate" class="form-label">' . h(erp_tax_label('percent')) . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'tax_rate', 'name' => 'tax_rate', 'class' => 'form-control text-end', 'maxlength' => '7', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'datalist' => 'erp_expense_rates')) . '
                                <datalist id="erp_expense_rates">' . $output_rates . '</datalist>
                            </div>
                            <div class="col-6 col-lg-2 my-2">
                                <label for="tax_amount" class="form-label">' . h(erp_tax_label('amount')) . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'tax_amount', 'name' => 'tax_amount', 'class' => 'form-control text-end', 'maxlength' => '15', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => lang('worked out'))) . '
                            </div>
                            <div class="col-12 col-lg-2 my-2">
                                <div class="form-check form-switch mb-2">
                                    ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'tax_deductible', 'name' => 'tax_deductible', 'value' => '1', 'class' => 'form-check-input')) . '
                                    <label class="form-check-label" for="tax_deductible">' . lang('Tax taken back') . '</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">' . h(lang(array(
                            'string' => 'Leave the {var:1} amount empty to have it worked out from the rate; type it when the receipt prints it, so the books carry the same figure. Switch off "Tax taken back" for a receipt whose tax cannot be deducted: it then counts as cost.',
                            'vars' => erp_tax_label('tax'),
                        ))) . '</div>
                        <div class="d-flex flex-wrap gap-4 mt-3 p-2 rounded bg-body-tertiary small" id="erp_expense_preview" aria-live="polite">
                            <span>' . lang('Net') . ': <b data-part="net">—</b></span>
                            <span>' . h(erp_tax_label('tax')) . ': <b data-part="tax">—</b></span>
                            <span>' . lang('Total') . ': <b data-part="total">—</b></span>
                        </div>
                        ' . ($fx_on
                            ? '<div class="row mt-2">
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="currency" class="form-label">' . lang('Currency') . '</label>
                                ' . $liveform->output_field(array('type' => 'select', 'id' => 'currency', 'name' => 'currency', 'class' => 'form-select', 'options' => erp_fx_currency_options())) . '
                            </div>
                            <div class="col-12 col-sm-4 col-lg-3 my-2">
                                <label for="exchange_rate" class="form-label">' . lang('Exchange Rate') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'exchange_rate', 'name' => 'exchange_rate', 'class' => 'form-control text-end', 'maxlength' => '20', 'inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => lang('rate of the expense date'))) . '
                                <div class="form-text">' . h(lang(array('string' => '{var:1} per unit; leave empty for the recorded rate of the expense date.', 'vars' => $base))) . '</div>
                            </div>
                        </div>'
                            : '') . '
                    </div>
                </div>';
    }

    // Repeating: a new expense can be written again by itself, every month
    // or every few months (includes/erp/expense_recurring.php).
    $output_repeat = '';

    if (!is_array($existing) && erp_expense_recurring_ready()) {
        $period_options = '';
        foreach (erp_expense_recurring_periods() as $months => $label) {
            $period_options .= '<option value="' . (int) $months . '"' . (((string) $liveform->get_field_value('repeat_every') === (string) $months) ? ' selected' : '') . '>' . h($label) . '</option>';
        }

        $output_repeat = '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Repeat') . '</div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-2">
                            ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'repeat', 'name' => 'repeat', 'value' => '1', 'class' => 'form-check-input')) . '
                            <label class="form-check-label" for="repeat">' . lang('Write this expense again by itself (rent, the internet line, a subscription)') . '</label>
                        </div>
                        <div class="row" id="erp_expense_repeat_fields">
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="repeat_every" class="form-label">' . lang('How often') . '</label>
                                <select class="form-select" id="repeat_every" name="repeat_every">' . $period_options . '</select>
                            </div>
                            <div class="col-6 col-lg-2 my-2">
                                <label for="repeat_due_days" class="form-label">' . lang('Due after (days)') . '</label>
                                ' . $liveform->output_field(array('type' => 'number', 'id' => 'repeat_due_days', 'name' => 'repeat_due_days', 'class' => 'form-control', 'min' => '0', 'max' => '365', 'placeholder' => '0')) . '
                            </div>
                            <div class="col-6 col-lg-3 my-2">
                                <label for="repeat_end" class="form-label">' . lang('Until (optional)') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'repeat_end', 'name' => 'repeat_end', 'class' => 'form-control', 'maxlength' => '10', 'autocomplete' => 'off')) . '
                            </div>
                            ' . ($can_cash ? '<div class="col-12 col-lg-4 my-2 d-flex align-items-end">
                                <div class="form-check form-switch mb-2">
                                    ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'repeat_pay', 'name' => 'repeat_pay', 'value' => '1', 'class' => 'form-check-input')) . '
                                    <label class="form-check-label" for="repeat_pay">' . lang('Write the next ones as paid from the same till') . '</label>
                                </div>
                            </div>' : '') . '
                        </div>
                        <div class="form-text">' . lang('The next one is written on the same day of the month, with this amount and tax, and appears on the expenses list; one whose amount changes (electricity) is best left to pay, so the amount can be corrected before paying it. Paid ones suit a direct debit or a card subscription.') . '</div>
                    </div>
                </div>';
    }

    $output_file = '';
    $kept = is_array($existing) ? erp_archive_file('expense', $id) : null;

    if ($kept === null) {
        $output_file = '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('The receipt') . '</div>
                    <div class="card-body">
                        <label for="receipt_file" class="form-label">' . lang('A picture or a PDF of the receipt') . '</label>
                        <input class="form-control" type="file" id="receipt_file" name="receipt_file" accept="image/jpeg,image/png,image/webp,application/pdf" />
                        <div class="form-text">' . h(lang(array('string' => 'Optional; at most {var:1} MB. On a phone the camera can take it.', 'vars' => (int) (ERP_EXPENSE_FILE_MAX_BYTES / 1048576)))) . '</div>
                    </div>
                </div>';
    }

    echo
    pg_page_shell([
        'title' => $page_title,
        'extra classes' => 'erp erp_expenses',
        'icon' => 'erp',
        'heading' => $page_title,
        'heading_description' => lang('The receipt as it is: what it cost, the tax on it, and how it was paid.'),
        'cancel' => array('enable' => 'true', 'url' => is_array($existing) ? ('edit_erp_expense.php?id=' . $id) : 'erp_expenses.php'),
        'breadcrumb' => array(
            array('label' => lang('Expenses'), 'url' => $list_url),
            array('label' => $page_title),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12 col-xxl-10">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form name="form" action="add_erp_expense.php" method="post" enctype="multipart/form-data" id="erp_expense_form" data-decimal-comma="' . (erp_decimal_comma() ? '1' : '0') . '">
                ' . get_token_field() . '
                ' . (($id > 0) ? '<input type="hidden" name="id" value="' . $id . '" />' : '') . '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('What was spent') . '</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="expense_date" class="form-label">' . lang('Date') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'expense_date', 'name' => 'expense_date', 'class' => 'form-control', 'maxlength' => '10', 'autocomplete' => 'off', 'required' => 'required') + ($paid ? array('readonly' => 'readonly') : array())) . '
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4 my-2">
                                <label for="category_id" class="form-label">' . lang('Category') . '</label>
                                ' . $liveform->output_field(array('type' => 'select', 'id' => 'category_id', 'name' => 'category_id', 'class' => 'form-select', 'options' => $category_options, 'required' => 'required')) . '
                            </div>
                            <div class="col-12 col-lg-5 my-2">
                                <label for="supplier" class="form-label">' . lang('Supplier') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'supplier', 'name' => 'supplier', 'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off', 'datalist' => 'erp_expense_suppliers', 'placeholder' => lang('Where it was spent'))) . '
                                <datalist id="erp_expense_suppliers">' . $output_suppliers . '</datalist>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="supplier_tax_number" class="form-label">' . h(erp_tax_id_label()) . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'supplier_tax_number', 'name' => 'supplier_tax_number', 'class' => 'form-control', 'maxlength' => '32', 'autocomplete' => 'off')) . '
                                <div class="form-text">' . lang('The supplier\'s, as printed on the receipt.') . '</div>
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="document_no" class="form-label">' . lang('Receipt number') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'document_no', 'name' => 'document_no', 'class' => 'form-control', 'maxlength' => '64', 'autocomplete' => 'off')) . '
                            </div>
                            <div class="col-12 col-lg-6 my-2">
                                <label for="description" class="form-label">' . lang('Description') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'description', 'name' => 'description', 'class' => 'form-control', 'maxlength' => '255', 'autocomplete' => 'off')) . '
                            </div>
                        </div>
                    </div>
                </div>

                ' . $output_money_card . '

                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Payment') . '</div>
                    <div class="card-body">' . $output_payment . '
                    </div>
                </div>
                ' . $output_repeat . '
                ' . $output_file . '
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_save" value="save" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang(array('string' => 'Save')) . '</span></button>
                            ' . (!is_array($existing) ? '<button type="submit" name="submit_save" value="another" class="btn my-1 btn-outline-success" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i><span class="btn-text">' . lang('Save and record another') . '</span></button>' : '') . '
                        </div>
                    </div>
                </nav>
            </form>
        </div>
    </div>
</main>
' . get_date_picker_format() . '
<script>
$("#expense_date' . ($paid ? '-none' : '') . ', #paid_date, #due_date, #repeat_end").datepicker(datetimepicker_options);
(function () {
    var form = document.getElementById("erp_expense_form");
    if (!form) { return; }
    var comma = form.getAttribute("data-decimal-comma") === "1";
    var locale = document.documentElement.lang || undefined;

    // The same reading of a typed amount as erp_kurus(): two digits behind
    // the last separator make it the decimal point.
    function kurus(value) {
        var raw = String(value || "").replace(/[^0-9.,]/g, "");
        if (raw === "") { return null; }
        var position = Math.max(raw.lastIndexOf("."), raw.lastIndexOf(","));
        if (position >= 0 && (raw.length - position - 1) <= 2) {
            var whole = raw.slice(0, position).replace(/[^0-9]/g, "") || "0";
            var fraction = (raw.slice(position + 1) + "00").slice(0, 2);
            return parseInt(whole, 10) * 100 + parseInt(fraction, 10);
        }
        return parseInt(raw.replace(/[^0-9]/g, ""), 10) * 100;
    }
    function rate(value) {
        var raw = String(value || "").replace(/\s/g, "");
        raw = comma ? raw.replace(/\./g, "").replace(",", ".") : raw.replace(/,/g, "");
        var number = parseFloat(raw);
        return isNaN(number) ? 0 : number;
    }
    function money(value) {
        return (value / 100).toLocaleString(locale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    // Half away from zero, as round() does on the server.
    function round(value) {
        return value < 0 ? -Math.round(-value) : Math.round(value);
    }
    function preview() {
        if (!form.amount) { return; }
        var amount = kurus(form.amount.value);
        var percent = rate(form.tax_rate.value);
        var typed = kurus(form.tax_amount.value);
        var included = form.includes_tax.checked;
        var net, tax;
        if (amount === null || amount <= 0) {
            form.querySelectorAll("#erp_expense_preview b").forEach(function (b) { b.textContent = "—"; });
            return;
        }
        if (included) {
            net = round(amount * 100 / (100 + percent));
            tax = amount - net;
        } else {
            net = amount;
            tax = round(net * percent / 100);
        }
        if (typed !== null) {
            if (included) { net = amount - typed; }
            tax = typed;
        }
        form.querySelector("[data-part=net]").textContent = money(net);
        form.querySelector("[data-part=tax]").textContent = money(tax);
        form.querySelector("[data-part=total]").textContent = money(net + tax);
    }
    // The tax amount an expense was saved with belongs to its old figures:
    // once the amount or the rate changes, it is worked out again unless the
    // operator types one.
    if (form.tax_amount && form.querySelector("input[name=id]") && form.tax_amount.value !== "") {
        form.tax_amount.setAttribute("data-kept", "1");
    }
    ["amount", "tax_rate"].forEach(function (name) {
        if (!form[name]) { return; }
        form[name].addEventListener("input", function () {
            if (form.tax_amount && form.tax_amount.getAttribute("data-kept") === "1") {
                form.tax_amount.value = "";
                form.tax_amount.removeAttribute("data-kept");
            }
            preview();
        });
    });
    if (form.tax_amount) {
        form.tax_amount.addEventListener("input", function () {
            form.tax_amount.removeAttribute("data-kept");
            preview();
        });
    }
    if (form.includes_tax) { form.includes_tax.addEventListener("change", preview); }

    // Paid now or later: only the fields of the choice are shown.
    function payment() {
        var now = form.querySelector("#pay_now");
        var later = document.getElementById("erp_expense_pay_later");
        var box = document.getElementById("erp_expense_pay_now");
        if (!now || !later || !box) { return; }
        box.hidden = !now.checked;
        later.hidden = now.checked;
        if (form.cash_account_id) { form.cash_account_id.required = now.checked; }
    }
    form.querySelectorAll("input[name=pay]").forEach(function (radio) { radio.addEventListener("change", payment); });

    // A supplier picked from the list brings the tax number it had.
    var supplier = document.getElementById("supplier");
    var taxNumber = document.getElementById("supplier_tax_number");
    if (supplier && taxNumber) {
        supplier.addEventListener("change", function () {
            var option = document.querySelector("#erp_expense_suppliers option[value=\\"" + CSS.escape(supplier.value) + "\\"]");
            if (option && taxNumber.value === "" && option.getAttribute("data-tax-number")) {
                taxNumber.value = option.getAttribute("data-tax-number");
            }
        });
    }

    var currency = document.getElementById("currency");
    if (currency) {
        currency.addEventListener("change", function () {
            document.getElementById("erp_expense_currency_label").textContent = currency.value;
        });
    }

    // The repeat fields only while repeating is asked for.
    var repeat = document.getElementById("repeat");
    var repeatFields = document.getElementById("erp_expense_repeat_fields");
    if (repeat && repeatFields) {
        var toggleRepeat = function () { repeatFields.hidden = !repeat.checked; };
        repeat.addEventListener("change", toggleRepeat);
        toggleRepeat();
    }

    // A new expense starts from the "Tax taken back" of its category; the
    // operator can still change it, and a written expense keeps its own.
    var categorySelect = document.getElementById("category_id");
    var deductibleBox = document.getElementById("tax_deductible");
    var categoryDeductible = ' . (is_array($existing) ? 'null' : json_encode((object) $category_deductible)) . ';
    if (categorySelect && deductibleBox && categoryDeductible) {
        categorySelect.addEventListener("change", function () {
            if (Object.prototype.hasOwnProperty.call(categoryDeductible, categorySelect.value)) {
                deductibleBox.checked = !!categoryDeductible[categorySelect.value];
            }
        });
    }

    preview();
    payment();
})();
</script>
' .
    output_footer();

    $liveform->remove_form();

} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    $date_in = function ($field) use ($liveform) {
        $value = trim((string) $liveform->get_field_value($field));

        if ($value === '') {
            return '';
        }

        return (validate_date($value) == false) ? false : prepare_form_data_for_input($value, 'date');
    };

    $expense_date = $paid ? (string) $existing['expense_date'] : $date_in('expense_date');

    if (($expense_date === false) || ($expense_date === '')) {
        $liveform->mark_error('expense_date', lang('Please enter a valid date.'));
        go($back);
    }

    $due_date = $date_in('due_date');
    $paid_date = $date_in('paid_date');

    if (($due_date === false) || ($paid_date === false)) {
        $liveform->mark_error(($due_date === false) ? 'due_date' : 'paid_date', lang('Please enter a valid date.'));
        go($back);
    }

    // The currency and the rate of its day, as a receipt reads them.
    $currency = $base;
    $exchange_rate = 1.0;
    $rate_date = $expense_date;
    $rate_source = 'base';

    if ($fx_on && !$paid) {
        $currency = strtoupper(trim((string) $liveform->get_field_value('currency')));
        $currency = ($currency !== '') ? $currency : $base;

        if ($currency !== $base) {
            $recorded = erp_fx_rate_for($currency, $expense_date);
            $typed = erp_fx_rate_in($liveform->get_field_value('exchange_rate'));

            if ($typed > 0) {
                $exchange_rate = $typed;
                $rate_source = (is_array($recorded) && (abs($recorded['rate'] - $typed) < 0.0000005)) ? $recorded['source'] : 'manual';
                $rate_date = ($rate_source === 'manual') ? $expense_date : $recorded['rate_date'];
            } elseif (is_array($recorded)) {
                $exchange_rate = $recorded['rate'];
                $rate_date = $recorded['rate_date'];
                $rate_source = $recorded['source'];
            } else {
                $liveform->mark_error('exchange_rate', lang(array(
                    'string' => 'No exchange rate is recorded for {var:1} on {var:2}. Run Update Exchange Rates or enter the rate.',
                    'vars' => array($currency, prepare_form_data_for_output($expense_date, 'date')),
                )));
                go($back);
            }
        }
    }

    $typed_tax = trim((string) $liveform->get_field_value('tax_amount'));
    $pay = $can_cash && ((string) $liveform->get_field_value('pay') === '1');

    $result = erp_expense_save(array(
        'expense_date' => $expense_date,
        'category_id' => (int) $liveform->get_field_value('category_id'),
        'supplier' => (string) $liveform->get_field_value('supplier'),
        'supplier_tax_number' => (string) $liveform->get_field_value('supplier_tax_number'),
        'document_no' => (string) $liveform->get_field_value('document_no'),
        'description' => (string) $liveform->get_field_value('description'),
        'currency' => $currency,
        'exchange_rate' => $exchange_rate,
        'exchange_rate_date' => $rate_date,
        'exchange_rate_source' => $rate_source,
        'amount' => erp_kurus($liveform->get_field_value('amount')),
        'includes_tax' => ((string) $liveform->get_field_value('includes_tax') !== ''),
        'tax_rate' => erp_quantity_in($liveform->get_field_value('tax_rate')),
        'tax_amount' => ($typed_tax !== '') ? erp_kurus($typed_tax) : null,
        'tax_deductible' => ((string) $liveform->get_field_value('tax_deductible') !== ''),
        'due_date' => $pay ? '' : (string) $due_date,
        'pay' => $pay,
        'cash_account_id' => (int) $liveform->get_field_value('cash_account_id'),
        'payment_method' => (string) $liveform->get_field_value('payment_method'),
        'paid_date' => ($paid_date !== '') ? $paid_date : $expense_date,
    ), (int) $user['id'], $id);

    if (!$result['success']) {
        $liveform->mark_error(($result['field'] !== '') ? $result['field'] : '_error', h($result['error']));
        go($back);
    }

    $saved_id = (int) $result['id'];
    $another = ((string) ($_POST['submit_save'] ?? '') === 'another');

    // Repeating, asked for on a new expense. A repeat that cannot be saved
    // does not cost the operator the expense; the view says why.
    $repeat_warning = '';

    if (($id === 0) && ((string) $liveform->get_field_value('repeat') !== '') && erp_expense_recurring_ready()) {
        $repeat_end = $date_in('repeat_end');
        $repeat = erp_expense_recurrence_create($saved_id, array(
            'every_months' => (int) $liveform->get_field_value('repeat_every'),
            'due_days' => (int) $liveform->get_field_value('repeat_due_days'),
            'pay' => $pay && ((string) $liveform->get_field_value('repeat_pay') !== ''),
            'cash_account_id' => (int) $liveform->get_field_value('cash_account_id'),
            'payment_method' => (string) $liveform->get_field_value('payment_method'),
            'end_date' => is_string($repeat_end) ? $repeat_end : '',
        ), (int) $user['id']);

        if (!$repeat['success']) {
            $repeat_warning = $repeat['error'];
        }
    }

    // The receipt's file goes after the expense is safely in: a file that
    // cannot be kept does not cost the operator the expense.
    $file_warning = '';

    if (isset($_FILES['receipt_file']) && ((int) ($_FILES['receipt_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $attached = erp_expense_attach($saved_id, $_FILES['receipt_file'], (int) $user['id']);

        if (!$attached['success']) {
            $file_warning = $attached['error'];
        }
    }

    log_activity(($id > 0) ? lang('erp expense was changed') : lang('erp expense was recorded'), $_SESSION['sessionusername']);

    $liveform->remove_form();

    if ($another && ($file_warning === '') && ($repeat_warning === '')) {
        $liveform_next = new liveform('add_erp_expense');
        $liveform_next->add_notice(h(lang(array('string' => 'Expense #{var:1} recorded. The next one:', 'vars' => $saved_id))) . ' <a class="alert-link" href="edit_erp_expense.php?id=' . $saved_id . '">' . lang('Open the expense') . '</a>');
        go(PATH . SOFTWARE_DIRECTORY . '/add_erp_expense.php');
    }

    $liveform_view = new liveform('edit_erp_expense');
    $liveform_view->add_notice(($id > 0) ? lang('The expense was saved.') : lang('The expense was recorded.'));

    if ($file_warning !== '') {
        $liveform_view->add_warning(h(lang('The expense was saved, but its file was not kept:') . ' ' . $file_warning));
    }

    if ($repeat_warning !== '') {
        $liveform_view->add_warning(h(lang('The expense was saved, but it does not repeat:') . ' ' . $repeat_warning));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/edit_erp_expense.php?id=' . $saved_id);
}
