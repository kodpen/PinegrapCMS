<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the invoice register.
 *
 * This screen only lists. Raising an invoice from an order is
 * add_erp_invoice.php, typing one in is add_erp_manual_invoice.php, a draft
 * is edited on edit_erp_invoice_draft.php and a document is read on
 * edit_erp_invoice.php.
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
require_once(PG_FUNCTIONS_DIR . '/includes/erp/drawer.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_invoices');

// Repeating invoices that have come due are written on a visit too, so a
// store without a crontab still bills its contracts; one indexed read
// otherwise.
if (function_exists('erp_invoice_recurring_ready') && erp_invoice_recurring_ready() && !(defined('USER_ERP_READONLY') && USER_ERP_READONLY)) {
    $recurring_run = erp_invoice_recurring_run();

    if ($recurring_run['written'] > 0) {
        $liveform->add_notice(h(lang(array('string' => '{var:1} repeating invoice(s) were written.', 'vars' => (int) $recurring_run['written']))));
    }
}

// The e-document view in the address, checked against the keys the screen
// knows. It narrows the register on its own or together with a due-date view.
$edoc_filters = (erp_edoc_installed() && erp_edoc_in_use()) ? erp_edoc_filters() : array();
$edoc_filter = (string) ($_GET['edoc'] ?? '');
$edoc_filter = isset($edoc_filters[$edoc_filter]) ? $edoc_filter : '';

// Ticking rows and acting on them is offered only where there is a provider
// to act through.
$edoc_provider = erp_edoc_installed() ? erp_edoc_active() : '';
$edoc_bulk = ($edoc_provider !== '');

// The address this screen was opened at, rebuilt from the values it knows,
// so a bulk action comes back to the same view.
$self_query = array();
foreach (array('filter', 'direction', 'account_id', 'bucket', 'as_of', 'edoc') as $self_key) {
    if (isset($_GET[$self_key]) && ((string) $_GET[$self_key] !== '')) {
        $self_query[$self_key] = (string) $_GET[$self_key];
    }
}
$self_url = 'erp_invoices.php' . (!empty($self_query) ? ('?' . http_build_query($self_query)) : '');

// Bulk e-document work on the ticked rows: send, hand the provider's draft to
// GİB, or ask where each one stands. Every document goes through the same
// function its own screen's button calls, so the gates are the same ones; a
// document an action does not apply to is counted as skipped, not as a
// failure. The number per click is capped because each document is at least
// one call to the provider, and a request has to finish.
if ($_POST && $edoc_bulk && in_array((string) ($_POST['erp_action'] ?? ''), array('edoc_bulk_send', 'edoc_bulk_submit', 'edoc_bulk_poll'), true)) {
    validate_token_field();

    $bulk_action = (string) $_POST['erp_action'];
    $bulk_limit = 25;
    $bulk_ids = array();
    foreach ((array) ($_POST['invoice_ids'] ?? array()) as $bulk_id) {
        if ((int) $bulk_id > 0) {
            $bulk_ids[(int) $bulk_id] = true;
        }
    }
    $bulk_ids = array_keys($bulk_ids);
    $bulk_left = max(0, count($bulk_ids) - $bulk_limit);
    $bulk_ids = array_slice($bulk_ids, 0, $bulk_limit);

    if (empty($bulk_ids)) {
        $liveform->mark_error('_error', lang('Tick at least one invoice first.'));
        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $self_url);
    }

    @set_time_limit(300);

    $bulk_done = 0;
    $bulk_skipped = 0;
    $bulk_failed = array();

    foreach ($bulk_ids as $bulk_id) {
        $bulk_invoice = db_item("SELECT * FROM erp_invoices WHERE id = '" . (int) $bulk_id . "' LIMIT 1");

        if (!is_array($bulk_invoice)) {
            $bulk_skipped++;
            continue;
        }

        // Whether the action applies at all, asked before any call is made.
        if ($bulk_action === 'edoc_bulk_send') {
            $bulk_applies = erp_edoc_invoice_can_send($bulk_invoice)['ok'];
        } elseif ($bulk_action === 'edoc_bulk_submit') {
            $bulk_applies = ((string) $bulk_invoice['edoc_status'] === 'created');
        } else {
            $bulk_applies = ((string) ($bulk_invoice['edoc_external_id'] ?? '') !== '');
        }

        if (!$bulk_applies) {
            $bulk_skipped++;
            continue;
        }

        if ($bulk_action === 'edoc_bulk_send') {
            $bulk_result = erp_edoc_invoice_send($bulk_id, (int) $user['id']);
        } elseif ($bulk_action === 'edoc_bulk_submit') {
            $bulk_result = erp_edoc_invoice_submit($bulk_id, (int) $user['id']);
        } else {
            $bulk_result = erp_edoc_invoice_poll($bulk_id);
        }

        if ($bulk_result['success']) {
            $bulk_done++;
        } else {
            $bulk_failed[] = (((string) $bulk_invoice['full_number'] !== '') ? (string) $bulk_invoice['full_number'] : ('#' . (int) $bulk_id)) . ': ' . (string) $bulk_result['error'];
        }
    }

    $bulk_words = array(
        'edoc_bulk_send' => lang('sent'),
        'edoc_bulk_submit' => lang('handed to GİB'),
        'edoc_bulk_poll' => lang('asked after'),
    );

    $liveform->add_notice(lang(array('string' => '{var:1} document(s) {var:2}; {var:3} skipped because the action does not apply to them.', 'vars' => array($bulk_done, $bulk_words[$bulk_action], $bulk_skipped))));

    if ($bulk_left > 0) {
        $liveform->add_notice(lang(array('string' => 'Only the first {var:1} ticked documents are handled per click; {var:2} were left for the next one.', 'vars' => array($bulk_limit, $bulk_left))));
    }

    if (!empty($bulk_failed)) {
        // The first few in full; the rest are on each document's own screen.
        $liveform->mark_error('_error', h(implode(' · ', array_slice($bulk_failed, 0, 5)))
            . ((count($bulk_failed) > 5) ? ' ' . h(lang(array('string' => 'and {var:1} more', 'vars' => count($bulk_failed) - 5))) : ''));
    }

    log_activity(lang(array('string' => 'erp e-documents handled in bulk ({var:1}: {var:2} done, {var:3} failed)', 'vars' => array($bulk_action, $bulk_done, count($bulk_failed)))), $_SESSION['sessionusername']);

    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $self_url);
}

// A due-date view narrows the register to the documents the aging helpers
// pick out, so the list, the aging report and the dashboard agree to the
// document. Everything in the address is checked against the values the
// screen knows; anything else reads as the plain register.
$filter_names = array(
    'overdue' => lang('Overdue'),
    'due_week' => lang('Due this week'),
    'open' => lang('Open'),
);
$filter = (string) ($_GET['filter'] ?? '');
$filter_direction = (string) ($_GET['direction'] ?? '');
$filter_direction = (($filter_direction === 'sales') || ($filter_direction === 'purchase')) ? $filter_direction : '';
$filter_account_id = (int) ($_GET['account_id'] ?? 0);
$filter_bucket = (string) ($_GET['bucket'] ?? '');
$filter_bucket = erp_aging_bucket_valid($filter_bucket) ? $filter_bucket : '';
$filter_as_of = erp_aging_as_of($_GET['as_of'] ?? '');

if (!isset($filter_names[$filter])) {
    $filter = ($filter_bucket !== '') ? 'open' : '';
}

$conditions = array();
$parts = array();
$output_filter_note = '';

if ($filter !== '') {
    $aged = erp_aging_invoices($filter_direction, $filter_as_of, array(
        'account_id' => $filter_account_id,
        'overdue' => ($filter === 'overdue'),
        'due_within' => ($filter === 'due_week') ? 7 : 0,
        'bucket' => $filter_bucket,
    ));

    $ids = array();
    foreach ($aged as $aged_row) {
        $ids[] = (int) $aged_row['id'];
    }

    // An empty match still has to be a query that returns nothing.
    $conditions[] = 'i.id IN (' . (empty($ids) ? '0' : implode(',', $ids)) . ')';

    $parts[] = $filter_names[$filter];
    if ($filter_bucket !== '') {
        $parts[] = erp_aging_buckets()[$filter_bucket];
    }
    if ($filter_direction !== '') {
        $parts[] = ($filter_direction === 'purchase') ? lang('Purchase') : lang('Sales');
    }
    if ($filter_account_id > 0) {
        $account_title = (string) db_value("SELECT title FROM erp_accounts WHERE id = '" . $filter_account_id . "' LIMIT 1");
        $parts[] = ($account_title !== '') ? $account_title : ('#' . $filter_account_id);
    }
    if ($filter_as_of !== date('Y-m-d')) {
        $parts[] = lang(array('string' => 'as of {var:1}', 'vars' => prepare_form_data_for_output($filter_as_of, 'date', false)));
    }
}

if ($edoc_filter !== '') {
    $conditions[] = erp_edoc_filter_sql($edoc_filter, 'i');
    $parts[] = lang('e-Document') . ': ' . $edoc_filters[$edoc_filter]['label'];
}

$where = !empty($conditions) ? (' WHERE ' . implode(' AND ', $conditions)) : '';

$invoices = (array) db_items("SELECT i.*, a.title AS live_account_title, o.order_number,
        " . erp_aging_due_sql('i') . " AS effective_due
    FROM erp_invoices i
    LEFT JOIN erp_accounts a ON i.account_id = a.id
    LEFT JOIN orders o ON i.order_id = o.id
    " . $where . "
    ORDER BY i.issue_date DESC, i.id DESC
    LIMIT 500");

if (!empty($parts)) {
    $output_filter_note = '
            <div class="alert alert-light d-flex align-items-center gap-2 py-2 mb-3" role="status">
                <i class="bi bi-funnel"></i>
                <span>' . h(lang(array('string' => 'Showing: {var:1}', 'vars' => implode(' · ', $parts)))) . '</span>
                <span class="text-body-secondary">(' . count($invoices) . ')</span>
                <a href="erp_invoices.php" class="ms-auto link-body-emphasis">' . lang('Clear the filter') . '</a>
            </div>';
}

$today = date('Y-m-d');
$now = time();

// The e-document column is drawn only where there is something to say: the
// provider table has to be there (4.61), and the screen has to know the
// words. A register on an installation that never turned e-documents on
// keeps the columns it always had.
// Only for a store that works with e-documents; elsewhere every invoice
// would carry a "not sent" badge for a step that does not exist.
$edoc_column = erp_edoc_installed() && erp_edoc_in_use();
$edoc_labels = $edoc_column ? erp_edoc_status_labels() : array();

$status_labels = array(
    'draft' => lang('Draft'),
    'issued' => lang('Issued'),
    'partially_paid' => lang('Partly paid'),
    'paid' => lang('Paid'),
    'cancelled' => lang('Cancelled'),
);

$status_classes = array(
    'draft' => 'text-body-secondary',
    'issued' => 'text-primary',
    'partially_paid' => 'text-warning',
    'paid' => 'text-success',
    'cancelled' => 'text-danger',
);

$direction_labels = array(
    'sales' => lang('Sales'),
    'purchase' => lang('Purchase'),
);

$output_rows = '';

foreach ($invoices as $invoice) {
    $id = (int) $invoice['id'];
    $status = (string) $invoice['status'];

    // A draft has no number yet and opens in the editor rather than as a document.
    $is_draft = ($status === 'draft');

    // Nothing is owed on a draft: no movement has been posted for it.
    $open = $is_draft ? 0 : erp_invoice_open_amount($invoice);

    // The title as it was when the document was issued; the live card for
    // documents written before the copy existed.
    $account_title = (trim((string) $invoice['account_title']) !== '') ? (string) $invoice['account_title'] : (string) $invoice['live_account_title'];
    $output_link_url = ($is_draft ? 'edit_erp_invoice_draft.php?id=' : 'edit_erp_invoice.php?id=') . $id;
    $output_number = $is_draft
        ? '<span class="text-body-secondary fst-italic">' . lang('Draft') . '</span>'
        : h($invoice['full_number']);

    $order_cell = ((int) $invoice['order_id'] > 0)
        ? '<a href="view_order.php?id=' . (int) $invoice['order_id'] . '">' . h($invoice['order_number'] ?: ('#' . (int) $invoice['order_id'])) . '</a>'
        : '';

    // Figures in the document's own currency; a foreign document also shows
    // what its total came to in the base currency.
    $currency = strtoupper(trim((string) $invoice['currency']));
    $is_foreign = ($currency !== erp_base_currency());

    // The term, and how it stands today. Only a document with money owing on
    // it can be late; a return or a draft has no term to keep.
    $effective_due = (string) $invoice['effective_due'];
    $output_due = '';
    if (!$is_draft && ((string) $invoice['doc_type'] === 'invoice')) {
        $output_due = h(prepare_form_data_for_output($effective_due, 'date'));

        if ($open > 0) {
            $days = erp_aging_days($effective_due, $today);

            if ($days > 0) {
                // Reminders put off: the bell is crossed out until the date.
                $snoozed_until = (int) ($invoice['overdue_snoozed_until'] ?? 0);
                $output_snoozed = ($snoozed_until > $now)
                    ? ' <i class="bi bi-bell-slash text-body-secondary" title="' . h(lang(array('string' => 'Reminders put off until {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d', $snoozed_until), 'date')))) . '"></i>'
                    : '';
                $output_due .= '<div><span class="badge text-bg-danger">' . h(lang(array('string' => '{var:1} days overdue', 'vars' => $days))) . '</span>' . $output_snoozed . '</div>';
            } elseif ($days === 0) {
                $output_due .= '<div><span class="badge text-bg-warning">' . lang('Due today') . '</span></div>';
            } elseif (-$days <= 7) {
                $output_due .= '<div><span class="badge text-bg-warning">' . h(lang(array('string' => 'due in {var:1} days', 'vars' => -$days))) . '</span></div>';
            }
        }
    }

    // Where the document stands with the tax authority. A sales invoice that
    // has not been sent says so plainly; a purchase invoice or a draft has
    // nothing to say here at all.
    $output_edoc_cell = '';

    if ($edoc_column) {
        $edoc_status = (string) ($invoice['edoc_status'] ?? 'none');
        $edoc_sendable = (!$is_draft && ((string) $invoice['direction'] === 'sales') && ((string) $invoice['status'] !== 'cancelled'));

        if (($edoc_status === 'none') && !$edoc_sendable) {
            $output_edoc_cell = '<td class="align-middle text-body-secondary" data-order="">&mdash;</td>';
        } else {
            $edoc_label = $edoc_labels[$edoc_status] ?? array($edoc_status, 'secondary');
            $output_edoc_cell = '<td class="align-middle text-nowrap" data-order="' . h($edoc_status) . '">'
                . '<span class="badge text-bg-' . h($edoc_label[1]) . '">' . h($edoc_label[0]) . '</span>'
                . (((string) ($invoice['gib_number'] ?? '') !== '') ? '<div class="small text-body-secondary">' . h($invoice['gib_number']) . '</div>' : '')
                . '</td>';
        }
    }

    // Only a document a provider would carry can be ticked; the others keep an
    // empty cell so the columns line up, and are left out of "select all".
    $output_pick_cell = '';
    $row_class = '';

    if ($edoc_bulk) {
        $pickable = !$is_draft && ((string) $invoice['direction'] === 'sales') && ($status !== 'cancelled');

        $output_pick_cell = $pickable
            ? '<td class="select-all align-middle text-start"><input class="form-check-input" type="checkbox" name="invoice_ids[]" value="' . $id . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => (string) $invoice['full_number']))) . '" /></td>'
            : '<td class="align-middle"></td>';
        $row_class = $pickable ? '' : ' class="unselectable"';
    }

    $output_rows .=
        '<tr' . erp_drawer_row_attributes($id) . $row_class . '>
            ' . $output_pick_cell . '
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . ($is_draft ? lang('Edit') : lang('View')) . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi ' . ($is_draft ? 'bi-pencil' : 'bi-eye') . '"></i></button>
            </td>
            <td class="align-middle text-nowrap chart_label" data-order="' . h($is_draft ? ('~' . $id) : $invoice['full_number']) . '">' . $output_number . '</td>
            <td class="align-middle text-nowrap" data-order="' . h($invoice['issue_date']) . '">' . h(prepare_form_data_for_output($invoice['issue_date'], 'date')) . '</td>
            <td class="align-middle text-nowrap" data-order="' . h($effective_due) . '">' . $output_due . '</td>
            <td style="max-width:240px" class="text-nowrap text-truncate align-middle">' . h($account_title) . '</td>
            <td class="align-middle">' . h($direction_labels[$invoice['direction']] ?? $invoice['direction']) . '</td>
            <td class="align-middle ' . ($status_classes[$status] ?? '') . '">' . h($status_labels[$status] ?? $status) . '</td>
            ' . $output_edoc_cell . '
            <td class="align-middle">' . $order_cell . '</td>
            <td class="align-middle text-end" data-order="' . (int) $invoice['tax_total'] . '">' . h(erp_money_out_currency((int) $invoice['tax_total'], $currency)) . '</td>
            <td class="align-middle text-end fw-bold" data-order="' . (int) $invoice['grand_total_base'] . '">' . h(erp_money_out_currency((int) $invoice['grand_total'], $currency))
                . ($is_foreign ? '<div class="text-body-secondary small fw-normal">' . h(erp_money_out((int) $invoice['grand_total_base'])) . '</div>' : '') . '</td>
            <td class="align-middle text-end ' . (($open > 0) ? 'text-danger' : 'text-body-secondary') . '" data-order="' . $open . '">' . h(erp_money_out_currency($open, $currency)) . '</td>
        </tr>';
}

// The e-document views, each with how many documents stand in it. Counted
// over the whole register, not the rows on screen, so the number matches the
// list the line opens.
$output_edoc_menu = '';

if (!empty($edoc_filters)) {
    $edoc_counts = erp_edoc_filter_counts();
    $output_edoc_items = '';

    foreach ($edoc_filters as $edoc_key => $edoc_item) {
        $output_edoc_items .= '
                        <li><a class="dropdown-item d-flex align-items-center gap-2 link-body-emphasis' . (($edoc_filter === $edoc_key) ? ' active' : '') . '" href="erp_invoices.php?edoc=' . $edoc_key . '"><i class="bi ' . $edoc_item['icon'] . '" aria-hidden="true"></i><span class="me-auto">' . h($edoc_item['label']) . '</span><span class="badge rounded-pill text-bg-' . (($edoc_counts[$edoc_key] > 0) ? $edoc_item['tone'] : 'secondary') . '">' . number_format($edoc_counts[$edoc_key]) . '</span></a></li>';
    }

    $output_edoc_menu = '
                <div class="dropdown">
                    <button class="btn btn-sm btn-ghost dropdown-toggle' . (($edoc_filter !== '') ? ' active' : '') . '" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-send-check me-1" aria-hidden="true"></i>' . (($edoc_filter !== '') ? h($edoc_filters[$edoc_filter]['label']) : lang('e-Document')) . '</button>
                    <ul class="dropdown-menu dropdown-menu-end">' . $output_edoc_items . '
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item link-body-emphasis" href="erp_invoices.php">' . lang('All') . '</a></li>
                    </ul>
                </div>';
}

// The bulk buttons act on the ticked rows of the page on screen; they stay
// disabled until something is ticked (chart_checkbox_state_change()).
$output_bulk_buttons = '';

if ($edoc_bulk) {
    $output_bulk_buttons = '
                <span class="enable-on-selected d-inline-flex flex-wrap gap-1">
                    <button type="submit" form="erp_edoc_bulk" name="erp_action" value="edoc_bulk_send" class="btn btn-sm btn-outline-secondary disabled" data-confirm-content="' . h(lang('Send the ticked invoices to the e-document provider? Invoices that were already sent are skipped.')) . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Send selected') . '</button>
                    <button type="submit" form="erp_edoc_bulk" name="erp_action" value="edoc_bulk_submit" class="btn btn-sm btn-outline-secondary disabled" data-confirm-content="' . h(lang('Hand the ticked drafts to GİB? Only documents waiting as a draft at the provider are handed over.')) . '" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-bank me-1" aria-hidden="true"></i>' . lang('Hand to GİB') . '</button>
                    <button type="submit" form="erp_edoc_bulk" name="erp_action" value="edoc_bulk_poll" class="btn btn-sm btn-outline-secondary disabled" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . lang('Ask status') . '</button>
                </span>';
}

echo
pg_page_shell([
        'title' => lang('Invoices'),
        'extra classes' => 'erp erp_invoices',
        'icon' => 'erp',
        'heading' => lang('Invoices'),
        'heading_description' => lang('Sales and purchase invoices, their e-document status and their returns.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <a class="btn btn-sm btn-primary rounded-pill px-3" href="add_erp_manual_invoice.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-plus-lg me-1"></i>' . lang('New Invoice') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="add_erp_invoice.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-receipt me-1"></i>' . lang(array('string' => 'Invoice an Order')) . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="erp_export.php?entity=invoices" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-box-arrow-up me-1"></i>' . lang('Export') . '</a>
                <a class="btn btn-sm btn-outline-secondary" href="erp_aging.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-hourglass-split me-1"></i>' . lang('Aging report') . '</a>
                ' . ((function_exists('erp_invoice_recurring_ready') && erp_invoice_recurring_ready()) ? '<a class="btn btn-sm btn-outline-secondary" href="erp_invoice_recurrences.php" data-loading-content="' . lang(array('string' => 'Loading')) . '"><i class="bi bi-arrow-repeat me-1"></i>' . lang('Repeating invoices') . '</a>' : '') . '
                ' . $output_bulk_buttons . '
                <div class="pg-toolbar-grow"></div>
                ' . $output_edoc_menu . '
                <div class="btn-group btn-group-sm" role="group" aria-label="' . lang('Due date') . '">
                    <a class="btn btn-sm btn-ghost' . (($filter === '') ? ' active' : '') . '" href="erp_invoices.php">' . lang('All') . '</a>
                    <a class="btn btn-sm btn-ghost' . (($filter === 'overdue') ? ' active' : '') . '" href="erp_invoices.php?filter=overdue"><i class="bi bi-exclamation-circle me-1"></i>' . lang('Overdue') . '</a>
                    <a class="btn btn-sm btn-ghost' . (($filter === 'due_week') ? ' active' : '') . '" href="erp_invoices.php?filter=due_week"><i class="bi bi-calendar-week me-1"></i>' . lang('Due this week') . '</a>
                </div>
            </nav>
            ' . $output_filter_note . '
            ' . ($edoc_bulk ? '<form id="erp_edoc_bulk" method="post" action="' . h($self_url) . '">' . get_token_field() : '') . '
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                ' . ($edoc_bulk ? '<th class="noVis"><input class="form-check-input" title="' . lang(array('string' => 'Select/Deselect All')) . '" type="checkbox" id="select_all"></th>' : '') . '
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Document Number') . '</th>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Due Date') . '</th>
                                <th>' . lang('Account') . '</th>
                                <th>' . lang('Direction') . '</th>
                                <th>' . lang('Status') . '</th>
                                ' . ($edoc_column ? '<th>' . lang('e-Document') . '</th>' : '') . '
                                <th>' . lang('Order') . '</th>
                                <th class="text-end">' . h(erp_tax_label('tax')) . '</th>
                                <th class="text-end">' . lang('Total') . '</th>
                                <th class="text-end">' . lang('Outstanding') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
            ' . ($edoc_bulk ? '</form>' : '') . '
        </div>
    </div>
</main>
' . erp_drawer_markup('invoice') .
output_footer();

$liveform->remove_form();
