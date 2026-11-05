<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the expense categories: their names, the accountant's account code
 * for each, their order, and which are still offered. A category is switched
 * off rather than deleted, so the expenses already in it keep it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
// The read-only accountant is told on arrival, not after filling the form in.
if (!validate_erp_access($user)
    || (defined('USER_ERP_READONLY') && USER_ERP_READONLY && !validate_erp_access($user, 'write'))) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_expense_categories');

$self = PATH . SOFTWARE_DIRECTORY . '/erp_expense_categories.php';
$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_expenses.php';

$with_deductible = erp_expense_categories_have_deductible();

if ($_POST) {
    validate_token_field();

    // The new category's fields are one value each; categories is the list
    // of the others. One posted as an array counts as not given: (string)
    // named the category "Array".
    pg_request_single_values(array('new_name', 'new_code', 'new_tax_deductible'));

    $rows = array();

    foreach ((array) ($_POST['categories'] ?? array()) as $category_id => $row) {
        if (!is_array($row)) {
            continue;
        }

        // A field of the row posted as an array (categories[3][name][]=x) is
        // not given: the name is then missing, and the save says so.
        $row = pg_request_row_values($row);

        $rows[(int) $category_id] = array(
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'is_active' => !empty($row['is_active']),
        );

        // The switch is on the form whenever the column is there; an empty
        // one is a category whose VAT is not taken back.
        if ($with_deductible) {
            $rows[(int) $category_id]['tax_deductible'] = !empty($row['tax_deductible']);
        }
    }

    $new = array(
        'name' => (string) ($_POST['new_name'] ?? ''),
        'code' => (string) ($_POST['new_code'] ?? ''),
    );

    if ($with_deductible) {
        $new['tax_deductible'] = !empty($_POST['new_tax_deductible']);
    }

    $saved = erp_expense_categories_save($rows, $new);

    if (!$saved['success']) {
        // Nothing was written: the form comes back with what was typed and
        // the refused row's name marked.
        $_SESSION['erp_expense_categories_sent'] = array(
            'rows' => $rows,
            'new' => $new,
            'id' => (int) ($saved['id'] ?? 0),
        );
        $liveform->mark_error('_error', h($saved['error']));
        go($self);
    }

    log_activity(lang('erp expense categories were saved'), $_SESSION['sessionusername']);
    $liveform->add_notice(lang('The categories were saved.'));
    go($self);
}

$ready = erp_expenses_ready();

if (!$ready) {
    $liveform->mark_error('', lang('Expenses come with the software update; run the update to use them.'));
}

// How many expenses each category holds, so the operator sees what a
// category switched off still carries.
$counts = array();
if ($ready) {
    foreach ((array) db_items("SELECT category_id, COUNT(*) AS count FROM erp_expenses WHERE status <> 'cancelled' GROUP BY category_id") as $row) {
        $counts[(int) $row['category_id']] = (int) $row['count'];
    }
}

// A refused save brings back what was typed, once.
$sent = (isset($_SESSION['erp_expense_categories_sent']) && is_array($_SESSION['erp_expense_categories_sent']))
    ? $_SESSION['erp_expense_categories_sent'] : array();
unset($_SESSION['erp_expense_categories_sent']);
$sent_rows = (array) ($sent['rows'] ?? array());
$sent_new = (array) ($sent['new'] ?? array());
$refused_id = (int) ($sent['id'] ?? 0);

$output_rows = '';

// Read in the status column: how many categories there are, how many are
// still offered, and how many expenses they hold.
$summary_total = 0;
$summary_offered = 0;
$summary_expenses = 0;

foreach ($ready ? erp_expense_categories() : array() as $category) {
    $category_id = (int) $category['id'];
    $field = 'categories[' . $category_id . ']';

    if (isset($sent_rows[$category_id]) && is_array($sent_rows[$category_id])) {
        $typed = $sent_rows[$category_id];
        $category['name'] = (string) ($typed['name'] ?? '');
        $category['code'] = (string) ($typed['code'] ?? '');
        $category['sort_order'] = (int) ($typed['sort_order'] ?? 0);
        $category['is_active'] = !empty($typed['is_active']) ? 1 : 0;
        if (array_key_exists('tax_deductible', $typed)) {
            $category['tax_deductible'] = !empty($typed['tax_deductible']) ? 1 : 0;
        }
    }

    $summary_total++;
    $summary_offered += ((int) $category['is_active'] === 1) ? 1 : 0;
    $summary_expenses += (int) ($counts[$category_id] ?? 0);

    $name_invalid = ($category_id === $refused_id);

    $output_rows .= '
        <tr' . (((int) $category['is_active'] !== 1) ? ' class="opacity-75"' : '') . '>
            <td style="width:6rem"><input type="number" class="form-control form-control-sm text-end" name="' . h($field) . '[sort_order]" value="' . (int) $category['sort_order'] . '" step="1" min="-2147483648" max="2147483647" aria-label="' . h(lang('Sort Order')) . '" /></td>
            <td style="min-width:12rem"><input type="text" class="form-control form-control-sm' . ($name_invalid ? ' is-invalid' : '') . '" name="' . h($field) . '[name]" value="' . h((string) $category['name']) . '" maxlength="100" required aria-label="' . h(lang('Name')) . '"' . ($name_invalid ? ' aria-invalid="true" autofocus' : '') . ' /></td>
            <td style="min-width:8rem"><input type="text" class="form-control form-control-sm font-monospace" name="' . h($field) . '[code]" value="' . h((string) $category['code']) . '" maxlength="32" placeholder="770" aria-label="' . h(lang('Account code')) . '" /></td>
            <td class="text-end fg-secondary" style="width:6rem">' . (int) ($counts[$category_id] ?? 0) . '</td>
            ' . ($with_deductible ? '<td style="width:11rem">
                <div class="form-field mb-0">
                    <div class="switch"><input class="" type="checkbox" id="category_deductible_' . $category_id . '" name="' . h($field) . '[tax_deductible]" value="1"' . (((int) $category['tax_deductible'] === 1) ? ' checked' : '') . ' /></div>
                    <label class="small" for="category_deductible_' . $category_id . '">' . lang('Tax taken back') . '</label>
                </div>
            </td>' : '') . '
            <td style="width:7rem">
                <div class="form-field mb-0">
                    <div class="switch"><input class="" type="checkbox" id="category_active_' . $category_id . '" name="' . h($field) . '[is_active]" value="1"' . (((int) $category['is_active'] === 1) ? ' checked' : '') . ' /></div>
                    <label class="small" for="category_active_' . $category_id . '">' . lang('Offered') . '</label>
                </div>
            </td>
        </tr>';
}

echo
pg_page_shell([
    'title' => lang('Expense categories'),
    'extra classes' => 'erp erp_expenses',
    'icon' => 'erp',
    'heading' => lang('Expense categories'),
    'heading_description' => lang('How expenses are grouped, and the account code the accountant books each group under.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_expenses.php'),
    'breadcrumb' => array(
        array('label' => lang('Expenses'), 'url' => $list_url),
        array('label' => lang('Categories')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="post" action="erp_expense_categories.php">
                ' . get_token_field() . '
                <div class="row">
                <div class="col-12 lg:col-8 2xl:col-9 order-1">
                <div class="card my-7">
                    <div class="card-body p-0 table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>' . lang('Sort Order') . '</th>
                                    <th>' . lang('Name') . '</th>
                                    <th>' . lang('Account code') . '</th>
                                    <th class="text-end">' . lang('Expenses') . '</th>
                                    ' . ($with_deductible ? '<th>' . h(erp_tax_label('tax')) . '</th>' : '') . '
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>' . $output_rows . '
                                <tr class="pg-table-light">
                                    <td class="fg-secondary small">' . lang('New') . '</td>
                                    <td><input type="text" class="form-control form-control-sm" name="new_name" value="' . h((string) ($sent_new['name'] ?? '')) . '" maxlength="100" placeholder="' . h(lang('A new category')) . '" aria-label="' . h(lang('A new category')) . '" /></td>
                                    <td style="min-width:8rem"><input type="text" class="form-control form-control-sm font-monospace" name="new_code" value="' . h((string) ($sent_new['code'] ?? '')) . '" maxlength="32" aria-label="' . h(lang('Account code')) . '" /></td>
                                    <td></td>
                                    ' . ($with_deductible ? '<td>
                                        <div class="form-field mb-0">
                                            <div class="switch"><input class="" type="checkbox" id="new_tax_deductible" name="new_tax_deductible" value="1"' . ((!array_key_exists('tax_deductible', $sent_new) || !empty($sent_new['tax_deductible'])) ? ' checked' : '') . ' /></div>
                                            <label class="small" for="new_tax_deductible">' . lang('Tax taken back') . '</label>
                                        </div>
                                    </td>' : '') . '
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
                <div class="col-12 lg:col-4 2xl:col-3 order-2">
                    <div class="card my-7 position-sticky" style="top:3.3rem;">
                        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Summary')) . '</div>
                        <div class="card-body">
                            <dl class="row small mb-0">
                                <dt class="col-8 fw-normal fg-secondary">' . h(lang('Categories')) . '</dt>
                                <dd class="col-4 text-end mb-2">' . pg_format_number($summary_total, 0) . '</dd>
                                <dt class="col-8 fw-normal fg-secondary">' . h(lang('Offered')) . '</dt>
                                <dd class="col-4 text-end mb-2">' . pg_format_number($summary_offered, 0) . '</dd>
                                <dt class="col-8 fw-normal fg-secondary">' . h(lang('Expenses')) . '</dt>
                                <dd class="col-4 text-end mb-0">' . pg_format_number($summary_expenses, 0) . '</dd>
                            </dl>
                            <div class="form-text mt-5 mb-0">' . lang('The account code is optional: the number the accountant books the category under (for example 770 for general expenses). It goes into the accountant\'s pack with each expense. A category switched off is no longer offered for new expenses; the ones already in it keep it.') . ($with_deductible ? ' ' . lang('"Tax taken back" is where a new expense of the category starts: switch it off for spending whose tax cannot be deducted (ask your accountant which). Each expense can still be changed, and the expenses already written keep theirs.') : '') . '</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 order-3">
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-solid theme-success"' . ($ready ? '' : ' disabled') . '><i class="bi bi-check-circle me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Save')) . '</span></button>
                    </div>
                </nav>
                </div>
                </div>
            </form>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
