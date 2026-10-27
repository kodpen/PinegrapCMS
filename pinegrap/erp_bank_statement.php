<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - one bank statement: its columns chosen, then its lines - matched to
 * the till's movements where the till has them, and recorded or set aside
 * where it does not. The work lives in includes/erp/bank_import.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'cash')) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_bank_statement');

$statement_id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$statement = erp_bank_statement($statement_id);

if ($statement === null) {
    output_error(lang('The statement could not be found.') . ' <a href="erp_bank_statements.php">' . lang('Bank statements') . '</a>');
    exit();
}

$self = PATH . SOFTWARE_DIRECTORY . '/erp_bank_statement.php?id=' . $statement_id;
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;

if ($_POST) {
    validate_token_field();

    // Every field of the forms is one value. One posted as an array counts as
    // not given: (int) read an array line, account, invoice or category as
    // record 1 and recorded the line there, an array column as column 1.
    pg_request_single_values(array('erp_action', 'header', 'date_order', 'col_date', 'col_description', 'col_amount', 'col_debit', 'col_credit',
        'separator', 'line_id', 'account_id', 'invoice_id', 'category_id'));

    $action = (string) ($_POST['erp_action'] ?? '');

    // A CSV's lines are split again on the separator chosen; the columns are
    // then chosen on the new split.
    if ($action === 'separator') {
        if (!erp_bank_statement_set_separator($statement_id, (string) ($_POST['separator'] ?? 'auto'))) {
            $liveform->mark_error('_error', h(lang('The statement could not be found.')));
        }

        go($self);
    }

    if ($action === 'map') {
        $built = erp_bank_statement_build($statement_id, array(
            'header' => !empty($_POST['header']),
            'date_order' => ((string) ($_POST['date_order'] ?? 'dmy') === 'mdy') ? 'mdy' : 'dmy',
            'date' => (int) ($_POST['col_date'] ?? -1),
            'description' => (int) ($_POST['col_description'] ?? -1),
            'amount' => (int) ($_POST['col_amount'] ?? -1),
            'debit' => (int) ($_POST['col_debit'] ?? -1),
            'credit' => (int) ($_POST['col_credit'] ?? -1),
            // The split shown on the screen, not one posted with the columns.
            'separator' => erp_bank_statement_mapping($statement)['separator'] ?? 'auto',
        ));

        if (!$built['success']) {
            $liveform->mark_error('_error', h($built['error']));
        } else {
            $liveform->add_notice(h(lang(array('string' => '{var:1} line(s) taken in, {var:2} matched to the books; {var:3} skipped (not a dated amount, or taken in before).', 'vars' => array($built['added'], $built['matched'], $built['skipped'])))));
        }

        go($self);
    }

    $line_id = (int) ($_POST['line_id'] ?? 0);

    if (($action === 'record') || ($action === 'expense')) {
        $done = erp_bank_line_record($line_id, array(
            'kind' => ($action === 'expense') ? 'expense' : 'account',
            'account_id' => (int) ($_POST['account_id'] ?? 0),
            'invoice_id' => (int) ($_POST['invoice_id'] ?? 0),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
        ), (int) $user['id']);

        if (!$done['success']) {
            $liveform->mark_error('_error', h($done['error']));
            go($self . '&line=' . $line_id . '#line-' . $line_id);
        }

        go($self . '#line-' . $line_id);
    }

    if ($action === 'record_suggested') {
        $recorded = 0;
        $failed = 0;

        foreach (erp_bank_statement_lines($statement_id) as $line) {
            if ((string) $line['status'] !== 'new') {
                continue;
            }

            $suggestion = erp_bank_line_suggestion($line);

            if ($suggestion['account_id'] <= 0) {
                continue;
            }

            $done = erp_bank_line_record((int) $line['id'], array('kind' => 'account', 'account_id' => $suggestion['account_id'], 'invoice_id' => $suggestion['invoice_id']), (int) $user['id']);
            $done['success'] ? $recorded++ : $failed++;
        }

        log_activity(lang(array('string' => 'erp bank statement (#{var:1}): {var:2} suggested line(s) recorded', 'vars' => array($statement_id, $recorded))), $_SESSION['sessionusername']);
        $liveform->add_notice(h(lang(array('string' => '{var:1} line(s) recorded as suggested; {var:2} could not be.', 'vars' => array($recorded, $failed)))));
        go($self);
    }

    if (($action === 'ignore') || ($action === 'unignore')) {
        erp_bank_line_ignore($line_id, $action === 'ignore');
        go($self . '#line-' . $line_id);
    }

    go($self);
}

$money = function ($kurus) use ($statement) {
    return h(erp_money_out_currency((int) $kurus, (string) $statement['till_currency']));
};

$post_form = function ($fields, $button, $class) use ($statement_id) {
    return '<form method="post" action="erp_bank_statement.php" class="d-inline-flex flex-wrap gap-1 align-items-center">' . get_token_field()
        . '<input type="hidden" name="id" value="' . $statement_id . '" />' . $fields
        . '<button type="submit" class="btn btn-sm ' . $class . '">' . $button . '</button></form>';
};

$output_body = '';

if ((string) $statement['status'] === 'mapping') {
    // Choosing the columns: the first lines of the file as they are, with a
    // number over each column. A CSV is split on the separator chosen.
    $mapping = erp_bank_statement_mapping($statement);
    $separator_key = erp_bank_csv_separator_key($mapping['separator'] ?? 'auto');
    $rows = erp_bank_statement_rows($statement['raw_rows'], $separator_key);
    $width = 0;
    foreach (array_slice($rows, 0, 12) as $cells) {
        $width = max($width, count((array) $cells));
    }

    // Only a CSV statement taken in with its lines kept can be split again;
    // a workbook, or a CSV taken in before, holds its cells already.
    $separator_form = '';
    if (!$readonly && erp_bank_statement_is_csv($statement['raw_rows'])) {
        $separator_labels = array(
            'auto' => lang('Automatic'),
            'semicolon' => lang('Semicolon (;)'),
            'comma' => lang('Comma (,)'),
            'tab' => lang('Tab character'),
            'pipe' => lang('Vertical bar (|)'),
        );
        $separator_options = '';
        foreach (array_keys(erp_bank_csv_separators()) as $key) {
            $separator_options .= '<option value="' . h($key) . '"' . (($key === $separator_key) ? ' selected' : '') . '>' . h($separator_labels[$key]) . '</option>';
        }

        $separator_form = '<div class="card-body border-bottom">'
            . $post_form('<input type="hidden" name="erp_action" value="separator" /><label class="small" for="separator">' . lang('Separator') . '</label>'
                . '<select class="form-control form-control-sm w-auto" id="separator" name="separator">' . $separator_options . '</select>', lang('Split again'), 'btn-outline-secondary')
            . '<div class="form-text">' . lang('If the columns come out wrong, choose the separator and split the lines again.') . '</div></div>';
    }

    $select = function ($name, $key, $label, $optional) use ($width, $mapping) {
        $options = $optional ? '<option value="-1">—</option>' : '<option value="-1">' . lang('Choose') . '</option>';
        for ($i = 0; $i < $width; $i++) {
            $options .= '<option value="' . $i . '"' . (((int) ($mapping[$key] ?? -1) === $i) ? ' selected' : '') . '>' . h(lang(array('string' => 'Column {var:1}', 'vars' => $i + 1))) . '</option>';
        }

        return '<div class="col-6 md:col-2"><label class="form-label small">' . $label . '</label><select class="form-control form-control-sm" name="' . $name . '">' . $options . '</select></div>';
    };

    $head = '<tr>';
    for ($i = 0; $i < $width; $i++) {
        $head .= '<th class="small text-nowrap">' . h(lang(array('string' => 'Column {var:1}', 'vars' => $i + 1))) . '</th>';
    }
    $head .= '</tr>';

    $body = '';
    foreach (array_slice($rows, 0, 8) as $cells) {
        $body .= '<tr>';
        for ($i = 0; $i < $width; $i++) {
            $body .= '<td class="small text-nowrap">' . h(mb_substr((string) ($cells[$i] ?? ''), 0, 60)) . '</td>';
        }
        $body .= '</tr>';
    }

    $output_body = '
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('The first lines of the file') . '</div>
                ' . $separator_form . '
                <div class="card-body p-0 table-responsive"><table class="table table-sm table-bordered mb-0"><thead>' . $head . '</thead><tbody>' . $body . '</tbody></table></div>
            </div>
            ' . (!$readonly ? '<form method="post" action="erp_bank_statement.php" class="card my-7">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $statement_id . '" />
                <input type="hidden" name="erp_action" value="map" />
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Which column is which') . '</div>
                <div class="card-body row g-3 align-items-end">
                    ' . $select('col_date', 'date', lang('Date'), false) . '
                    ' . $select('col_description', 'description', lang('Description'), true) . '
                    ' . $select('col_amount', 'amount', lang('Amount (+/−)'), true) . '
                    ' . $select('col_debit', 'debit', lang('Money out'), true) . '
                    ' . $select('col_credit', 'credit', lang('Money in'), true) . '
                    <div class="col-6 md:col-2"><label class="form-label small">' . lang('Dates are written') . '</label><select class="form-control form-control-sm" name="date_order"><option value="dmy">' . lang('day first') . '</option><option value="mdy"' . (((string) ($mapping['date_order'] ?? 'dmy') === 'mdy') ? ' selected' : '') . '>' . lang('month first') . '</option></select></div>
                    <div class="col-12"><div class="form-field"><input class="check" type="checkbox" id="header" name="header" value="1"' . (!empty($mapping['header']) ? ' checked' : '') . ' /><label class="" for="header">' . lang('The first line is the column names') . '</label></div></div>
                    <div class="col-12 form-text">' . lang('Either one amount column, with money out as a minus, or a money-out and a money-in column. Lines without a date or an amount (totals, balances, headings) are left out.') . '</div>
                    <div class="col-12"><button type="submit" class="btn btn-solid theme-primary">' . lang('Take the lines in') . '</button></div>
                </div>
            </form>' : '');
} else {
    $statuses = erp_bank_line_statuses();
    // One number: an array (?line[]=x) cast to 1 and opened the first line.
    $open_line = is_scalar($_GET['line'] ?? null) ? (int) $_GET['line'] : 0;
    $lines = erp_bank_statement_lines($statement_id);
    $suggested = 0;

    $all_accounts = null;
    $categories = null;
    $output_bank_quick = '';
    $output_rows = '';

    foreach ($lines as $line) {
        $status = $statuses[(string) $line['status']] ?? array((string) $line['status'], 'secondary');
        $in = ((int) $line['amount'] > 0);
        $action = '';

        if ((string) $line['status'] === 'new' && !$readonly) {
            $suggestion = erp_bank_line_suggestion($line);
            $hidden = '<input type="hidden" name="line_id" value="' . (int) $line['id'] . '" />';

            if ($suggestion['account_id'] > 0) {
                $suggested++;
                $title = (string) db_value("SELECT title FROM erp_accounts WHERE id = '" . (int) $suggestion['account_id'] . "' LIMIT 1");
                $action .= $post_form($hidden . '<input type="hidden" name="erp_action" value="record" /><input type="hidden" name="account_id" value="' . (int) $suggestion['account_id'] . '" /><input type="hidden" name="invoice_id" value="' . (int) $suggestion['invoice_id'] . '" />',
                    '<i class="bi bi-check2 me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Record for {var:1}', 'vars' => $title))), 'btn-outline-success')
                    . '<div class="small fg-secondary">' . h($suggestion['why']) . '</div>';
            }

            if ($open_line === (int) $line['id']) {
                if ($all_accounts === null) {
                    $all_accounts = '';
                    foreach (erp_accounts(array('status' => 'active')) as $account) {
                        $all_accounts .= '<option value="' . (int) $account['id'] . '">' . h($account['title']) . '</option>';
                    }
                }

                $action .= '<div class="mt-1">' . $post_form($hidden . '<input type="hidden" name="erp_action" value="record" /><select class="form-control form-control-sm" data-pg-combobox id="erp_bank_line_account_id" name="account_id" style="max-width:16rem" required aria-label="' . h(lang('Account')) . '"><option value="">' . lang('Choose an account') . '</option>' . $all_accounts . '</select>',
                    $in ? lang('Record as a collection') : lang('Record as a payment'), 'btn-primary')
                    . erp_account_quick_link('#erp_bank_line_account_id', $in ? 'customer' : 'supplier') . '</div>';
                $output_bank_quick = erp_account_quick_modal();

                if (!$in) {
                    if ($categories === null) {
                        $categories = '';
                        foreach (erp_expense_categories(true) as $category) {
                            $categories .= '<option value="' . (int) $category['id'] . '">' . h((string) $category['name']) . '</option>';
                        }
                    }
                    $action .= '<div class="mt-1">' . $post_form($hidden . '<input type="hidden" name="erp_action" value="expense" /><select class="form-control form-control-sm" name="category_id" style="max-width:16rem" required><option value="">' . lang('Expense category') . '</option>' . $categories . '</select>',
                        lang('Record as an expense'), 'btn-outline-primary') . '</div>';
                }
            } else {
                $action .= ' <a class="btn btn-sm btn-link" href="erp_bank_statement.php?id=' . $statement_id . '&amp;line=' . (int) $line['id'] . '#line-' . (int) $line['id'] . '">' . lang('Record…') . '</a>';
            }

            $action .= ' ' . $post_form($hidden . '<input type="hidden" name="erp_action" value="ignore" />', lang('Set aside'), 'btn-link fg-secondary');
        } elseif ((string) $line['status'] === 'ignored' && !$readonly) {
            $action = $post_form('<input type="hidden" name="line_id" value="' . (int) $line['id'] . '" /><input type="hidden" name="erp_action" value="unignore" />', lang('Bring back'), 'btn-link');
        } elseif ((int) $line['cash_id'] > 0) {
            $link = in_array((string) $line['cash_doc_type'], array('collection', 'payment'), true) ? 'erp_receipt.php?id=' . (int) $line['cash_id'] : 'erp_cash.php';
            $action = '<a class="small pg-link-body-emphasis" href="' . h($link) . '">' . h(trim((string) $line['cash_description'])) . '</a>'
                . (((string) $line['account_title'] !== '') ? '<div class="small fg-secondary">' . h((string) $line['account_title']) . '</div>' : '');
        }

        $output_rows .= '
            <tr id="line-' . (int) $line['id'] . '">
                <td class="text-nowrap" data-sort="' . h(str_replace('-', '', (string) $line['doc_date'])) . '">' . h(prepare_form_data_for_output((string) $line['doc_date'], 'date')) . '</td>
                <td class="small" style="max-width:360px">' . h((string) $line['description']) . '</td>
                <td class="text-end text-nowrap fw-semibold ' . ($in ? 'fg-success' : 'fg-danger') . '" data-sort="' . (int) $line['amount'] . '">' . ($in ? '+' : '−') . $money(abs((int) $line['amount'])) . '</td>
                <td><span class="badge text-bg-' . h($status[1]) . '">' . h($status[0]) . '</span></td>
                <td>' . $action . '</td>
            </tr>';
    }

    if ($output_rows === '') {
        $output_rows = '<tr data-pg-sort-fixed><td colspan="5" class="text-center fg-secondary py-7">' . lang('The statement has no new lines.') . '</td></tr>';
    }

    $output_body = $output_bank_quick . '
            ' . (($suggested > 0 && !$readonly) ? '<div class="d-flex justify-content-end my-5">' . $post_form('<input type="hidden" name="erp_action" value="record_suggested" />', '<i class="bi bi-check2-all me-1" aria-hidden="true"></i>' . h(lang(array('string' => 'Record the {var:1} suggested line(s)', 'vars' => $suggested))), 'btn-success') . '</div>' : '') . '
            <div class="card my-7">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort>
                        <thead><tr><th>' . lang('Date') . '</th><th>' . lang('Description') . '</th><th class="text-end">' . lang('Amount') . '</th><th>' . lang('Status') . '</th><th data-pg-sort="none"></th></tr></thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-5 pb-5">' . lang('Matched: the books already have the movement (same amount and way, within three days). A suggestion comes from a tax number or an account name in the line, or from the one open invoice of that amount; it is written only when you record it.') . '</div>
            </div>';
}

echo
pg_page_shell([
    'title' => lang('Bank statement'),
    'extra classes' => 'erp erp_cash',
    'icon' => 'erp',
    'heading' => h(lang(array('string' => 'Statement of {var:1}', 'vars' => (string) $statement['till_name']))),
    'heading_description' => (string) $statement['file_name'] . ' · ' . prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $statement['created_at']), 'date and time', false),
    'cancel' => array('enable' => 'true', 'url' => 'erp_bank_statements.php'),
    'breadcrumb' => array(
        array('label' => lang('Bank statements'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_bank_statements.php'),
        array('label' => (string) $statement['till_name']),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_body . '
        </div>
    </div>
</main>
' . pg_fields_script() .
output_footer();

$liveform->remove_form();
