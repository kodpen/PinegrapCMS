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
if (!validate_erp_access($user)) {
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

    $rows = array();

    foreach ((array) ($_POST['categories'] ?? array()) as $category_id => $row) {
        if (!is_array($row)) {
            continue;
        }

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

$output_rows = '';

foreach ($ready ? erp_expense_categories() : array() as $category) {
    $category_id = (int) $category['id'];
    $field = 'categories[' . $category_id . ']';

    $output_rows .= '
        <tr' . (((int) $category['is_active'] !== 1) ? ' class="opacity-75"' : '') . '>
            <td style="width:6rem"><input type="number" class="form-control form-control-sm text-end" name="' . h($field) . '[sort_order]" value="' . (int) $category['sort_order'] . '" step="10" aria-label="' . h(lang('Sort Order')) . '" /></td>
            <td><input type="text" class="form-control form-control-sm" name="' . h($field) . '[name]" value="' . h((string) $category['name']) . '" maxlength="100" required aria-label="' . h(lang('Name')) . '" /></td>
            <td style="width:10rem"><input type="text" class="form-control form-control-sm font-monospace" name="' . h($field) . '[code]" value="' . h((string) $category['code']) . '" maxlength="32" placeholder="770" aria-label="' . h(lang('Account code')) . '" /></td>
            <td class="text-end text-body-secondary" style="width:6rem">' . (int) ($counts[$category_id] ?? 0) . '</td>
            ' . ($with_deductible ? '<td style="width:11rem">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="category_deductible_' . $category_id . '" name="' . h($field) . '[tax_deductible]" value="1"' . (((int) $category['tax_deductible'] === 1) ? ' checked' : '') . ' />
                    <label class="form-check-label small" for="category_deductible_' . $category_id . '">' . lang('Tax taken back') . '</label>
                </div>
            </td>' : '') . '
            <td style="width:7rem">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="category_active_' . $category_id . '" name="' . h($field) . '[is_active]" value="1"' . (((int) $category['is_active'] === 1) ? ' checked' : '') . ' />
                    <label class="form-check-label small" for="category_active_' . $category_id . '">' . lang('Offered') . '</label>
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
        <div class="col-12 col-xxl-9">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="post" action="erp_expense_categories.php">
                ' . get_token_field() . '
                <div class="card my-4">
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
                                <tr class="table-light">
                                    <td class="text-body-secondary small">' . lang('New') . '</td>
                                    <td><input type="text" class="form-control form-control-sm" name="new_name" maxlength="100" placeholder="' . h(lang('A new category')) . '" aria-label="' . h(lang('A new category')) . '" /></td>
                                    <td><input type="text" class="form-control form-control-sm font-monospace" name="new_code" maxlength="32" aria-label="' . h(lang('Account code')) . '" /></td>
                                    <td></td>
                                    ' . ($with_deductible ? '<td>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="new_tax_deductible" name="new_tax_deductible" value="1" checked />
                                            <label class="form-check-label small" for="new_tax_deductible">' . lang('Tax taken back') . '</label>
                                        </div>
                                    </td>' : '') . '
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text px-3 pb-3">' . lang('The account code is optional: the number the accountant books the category under (for example 770 for general expenses). It goes into the accountant\'s pack with each expense. A category switched off is no longer offered for new expenses; the ones already in it keep it.') . ($with_deductible ? ' ' . lang('"Tax taken back" is where a new expense of the category starts: switch it off for spending whose tax cannot be deducted (ask your accountant which). Each expense can still be changed, and the expenses already written keep theirs.') : '') . '</div>
                </div>
                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <button type="submit" class="btn my-1 btn-success"' . ($ready ? '' : ' disabled') . ' data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>' . lang(array('string' => 'Save')) . '</button>
                    </div>
                </nav>
            </form>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
