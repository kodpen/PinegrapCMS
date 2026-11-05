<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - incoming e-invoices: the register.
 *
 * What suppliers sent the store through GİB, read from the e-document
 * provider on request, and where each document stands here: not yet taken
 * in, a purchase draft, taken in, or set aside. Reading the list writes only
 * to this register; a document becomes a purchase invoice on its own screen
 * (erp_inbox_document.php), after it has been looked over.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_inbox');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_inbox.php';
$installed = erp_edoc_inbox_installed();
$provider = erp_edoc_inbox_provider();
$states = erp_edoc_inbox_states();

if ($_POST) {

    validate_token_field();

    // Every field of the form is one value. One posted as an array counts as
    // not given: (string) read the dates and the tab as "Array" with a notice.
    pg_request_single_values(array('erp_action', 'from', 'to', 'state'));

    if ((($_POST['erp_action'] ?? '') === 'fetch') && ($provider !== '')) {

        $from = (string) ($_POST['from'] ?? '');
        $to = (string) ($_POST['to'] ?? '');
        $result = erp_edoc_inbox_fetch($from, $to);

        if ($result['success']) {
            $liveform->add_notice(lang(array(
                'string' => '{var:1} document(s) read from the provider, {var:2} of them new.',
                'vars' => array($result['found'], $result['added']),
            )));

            if ($result['truncated']) {
                $liveform->add_notice(lang('There are more documents in that range than one read takes. Read the rest with a narrower range.'));
            }
        } else {
            // Offer the refused range again, so it can be corrected.
            $liveform->assign_field_value('from', $from);
            $liveform->assign_field_value('to', $to);
            $liveform->mark_error('_error', h($result['error']));
        }

        go($self_url . '?state=' . urlencode((string) ($_POST['state'] ?? 'new')));
    }

    go($self_url);
}

$state = is_string($_GET['state'] ?? null) ? $_GET['state'] : 'new';
if (($state !== 'all') && !isset($states[$state])) {
    $state = 'new';
}

$counts = erp_edoc_inbox_counts();
$rows = array();

if ($installed) {
    $where = ($state === 'all') ? '' : ('WHERE ' . erp_edoc_inbox_state_sql($state));
    $rows = (array) db_items("SELECT x.id, x.provider, x.gib_number, x.gib_uuid, x.invoice_type, x.profile, x.issue_date,
            x.supplier_title, x.supplier_tax_number, x.currency, x.total, x.provider_status, x.status, x.invoice_id,
            i.status AS invoice_status, i.full_number AS invoice_number
        FROM erp_edoc_inbox x
        LEFT JOIN erp_invoices i ON i.id = x.invoice_id
        " . $where . "
        ORDER BY x.issue_date DESC, x.id DESC
        LIMIT 2000");
}

// The range offered for the next read: from a week before the last one
// ended (a supplier's document can reach the provider days after its date),
// or the last thirty days the first time.
$read_to = ($provider !== '') ? (string) (erp_edoc_settings($provider)['inbox_read_to'] ?? '') : '';
$read_at = ($provider !== '') ? (int) (erp_edoc_settings($provider)['inbox_read_at'] ?? 0) : 0;
$default_to = date('Y-m-d');
$default_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $read_to)
    ? date('Y-m-d', max(strtotime($read_to . ' -7 days'), strtotime('-' . ERP_EDOC_INBOX_MAX_DAYS . ' days')))
    : date('Y-m-d', strtotime('-30 days'));

// A range the provider read refused comes back as it was entered.
$is_date = function ($value) {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
};
$fetch_from = $is_date($liveform->get_field_value('from')) ? $liveform->get_field_value('from') : $default_from;
$fetch_to = $is_date($liveform->get_field_value('to')) ? $liveform->get_field_value('to') : $default_to;

// Every document of the view is printed: the list table driver sorts and
// searches them in the browser (browser mode) and keeps a heading's sort.
$list_rows = array();

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $row_state = erp_edoc_inbox_state($row);
    $tone = $states[$row_state]['tone'];

    $output_state = '<span class="badge rounded-pill theme-' . $tone . '">' . h($states[$row_state]['label']) . '</span>';

    if ($row_state === 'drafted') {
        $output_state .= ' <a href="edit_erp_invoice_draft.php?id=' . (int) $row['invoice_id'] . '" class="small">' . lang('Open the draft') . '</a>';
    } elseif ($row_state === 'imported') {
        $output_state .= ' <a href="edit_erp_invoice.php?id=' . (int) $row['invoice_id'] . '" class="small">' . h($row['invoice_number']) . '</a>';
    }

    $list_rows[] = array(
        'id' => $id,
        'class' => ($row_state === 'ignored') ? 'fg-secondary' : '',
        'cells' => array(
            'number' => pg_list_cell('<a href="erp_inbox_document.php?id=' . $id . '" class="pg-link-body-emphasis" data-pg-open>' . h(((string) $row['gib_number'] !== '') ? $row['gib_number'] : $row['gib_uuid']) . '</a>', 'align-middle text-nowrap', array('data-pg-sort-value' => (string) $row['gib_number'])),
            'date' => pg_list_cell(h(prepare_form_data_for_output($row['issue_date'], 'date')), 'align-middle text-nowrap', array('data-pg-sort-value' => (string) $row['issue_date'])),
            'supplier' => pg_list_cell('<div class="text-break">' . h($row['supplier_title']) . '</div><div class="small fg-secondary">' . h($row['supplier_tax_number']) . '</div>', 'align-middle text-wrap text-break', array('data-pg-sort-value' => (string) $row['supplier_title'])),
            'type' => pg_list_cell(h(erp_edoc_inbox_type_label($row['invoice_type'])) . '<div class="small fg-secondary">' . h($row['profile']) . '</div>', 'align-middle text-nowrap'),
            'total' => pg_list_cell(h(erp_money_out_currency((int) $row['total'], $row['currency'])), 'align-middle text-nowrap', array('data-pg-sort-value' => (int) $row['total'])),
            'provider_status' => pg_list_cell(h($row['provider_status'])),
            'status' => pg_list_cell($output_state),
        ),
    );
}

$columns = array(
    'number' => array('heading' => lang('Document Number'), 'kind' => 'link'),
    'date' => array('heading' => lang('Date'), 'kind' => 'date'),
    'supplier' => array('heading' => lang('Supplier')),
    'type' => array('heading' => lang('Type')),
    'total' => array('heading' => lang('Total'), 'kind' => 'money'),
    'provider_status' => array('heading' => lang('At GİB')),
    'status' => array('heading' => lang('Status'), 'kind' => 'badge'),
);
$sort_columns = pg_list_table_printed_sort_columns($columns);
$sort_default = array('sort' => 'date', 'order' => 'desc');
$list_state = pg_list_state('erp_inbox', $sort_columns, $sort_default);
$list = pg_list_table_printed(array(
    'list' => 'erp_inbox',
    'columns' => $columns,
    'levels' => $list_state['sorts'],
    'rows' => $list_rows,
));

// The states, each with how many documents stand in it.
$filter_options = array();
foreach (array_merge(array_keys($states), array('all')) as $key) {
    $label = ($key === 'all') ? lang('All') : $states[$key]['label'];
    $count = ($key === 'all') ? array_sum($counts) : (int) $counts[$key];
    $filter_options[$key] = $label . ' (' . $count . ')';
}
$output_filter = pg_filter_select('state', $filter_options, $state, array('label' => lang('Status'), 'icon' => 'funnel', 'action' => 'erp_inbox.php'));

$output_notice = '';

if (!$installed) {
    $output_notice = '<div class="alert theme-warning my-7">' . lang('Run the software upgrade first: the incoming e-invoice table is not there yet.') . '</div>';
} elseif ($provider === '') {
    $active = erp_edoc_active();
    $output_notice = '<div class="alert theme-info my-7">'
        . (($active === '')
            ? lang('Incoming e-invoices are read from the e-document provider, and no provider is selected.')
            : lang(array('string' => '{var:1} does not hand over incoming invoices through this screen yet.', 'vars' => erp_edoc_info($active)['label'])))
        . ' <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('accounting', 'pgset-invoice')) . '">' . lang('E-Invoice settings') . '</a>'
        . '</div>';
}

$output_fetch = '';

if ($provider !== '') {
    // Reading writes rows, so a read-only user is shown when it last ran, not the button.
    $output_fetch = ((defined('USER_ERP_READONLY') && USER_ERP_READONLY) ? '' : '
                <form action="erp_inbox.php" method="post" class="d-flex flex-wrap align-items-center gap-1 row-gap-2">
                    ' . get_token_field() . '
                    <input type="hidden" name="erp_action" value="fetch" />
                    <input type="hidden" name="state" value="' . h($state) . '" />
                    <span class="d-inline-flex align-items-center gap-1">
                        <input type="date" data-pg-date name="from" value="' . h($fetch_from) . '" max="' . h($default_to) . '" class="form-control form-control-sm w-auto" aria-label="' . lang('Start date') . '" required />
                        <span class="fg-secondary">&ndash;</span>
                        <input type="date" data-pg-date name="to" value="' . h($fetch_to) . '" max="' . h($default_to) . '" class="form-control form-control-sm w-auto" aria-label="' . lang('End date') . '" required />
                    </span>
                    <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5 text-nowrap"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>' . lang('Read from the provider') . '</button>
                    <span class="small fg-secondary">' . lang(array('string' => 'Read at most {var:1} days at a time.', 'vars' => ERP_EDOC_INBOX_MAX_DAYS)) . '</span>
                </form>') . '
                ' . (($read_at > 0)
                    ? '<span class="small fg-secondary">' . lang(array('string' => 'Last read {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d H:i:s', $read_at), 'date and time'))) . '</span>'
                    : '');
}

echo
pg_page_shell([
        'title' => lang('Incoming e-invoices'),
        'extra classes' => 'erp erp_inbox',
        'icon' => 'erp',
        'heading' => lang('Incoming e-invoices'),
        'heading_description' => lang('What suppliers sent the store through GİB, and the purchase invoices they became.'),
        'cancel' => false,
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_notice . '

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                ' . $output_fetch . '
                <div class="pg-toolbar-grow"></div>
                ' . pg_list_table_search_box('erp_inbox') . '
                ' . pg_list_sort_menu('erp_inbox.php', $sort_columns, $list_state['sorts'], $sort_default, array('state' => $state)) . '
                ' . $output_filter . '
            </nav>
            <div class="card my-7">
                ' . pg_list_table_html($list['source'], $list['page'], lang('Incoming e-invoices')) . '
            </div>
        </div>
    </div>
</main>
' . get_date_picker_format() . pg_fields_script() . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();

$liveform->remove_form();
