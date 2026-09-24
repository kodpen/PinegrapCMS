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
include_once('liveform.class.php');
$liveform = new liveform('erp_inbox');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_inbox.php';
$installed = erp_edoc_inbox_installed();
$provider = erp_edoc_inbox_provider();
$states = erp_edoc_inbox_states();

if ($_POST) {

    validate_token_field();

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
            $liveform->mark_error('_error', $result['error']);
        }

        go($self_url . '?state=' . urlencode((string) ($_POST['state'] ?? 'new')));
    }

    go($self_url);
}

$state = (string) ($_GET['state'] ?? 'new');
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

$output_rows = '';

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $row_state = erp_edoc_inbox_state($row);
    $tone = $states[$row_state]['tone'];

    $output_state = '<span class="badge rounded-pill text-bg-' . $tone . '">' . h($states[$row_state]['label']) . '</span>';

    if ($row_state === 'drafted') {
        $output_state .= ' <a href="edit_erp_invoice_draft.php?id=' . (int) $row['invoice_id'] . '" class="small">' . lang('Open the draft') . '</a>';
    } elseif ($row_state === 'imported') {
        $output_state .= ' <a href="edit_erp_invoice.php?id=' . (int) $row['invoice_id'] . '" class="small">' . h($row['invoice_number']) . '</a>';
    }

    $output_rows .=
        '<tr' . (($row_state === 'ignored') ? ' class="text-body-secondary"' : '') . '>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2" data-loading-content=" " title="' . lang('View') . '" onclick="window.location.href=\'erp_inbox_document.php?id=' . $id . '\'"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </td>
            <td class="align-middle text-nowrap" data-order="' . h($row['issue_date']) . '">' . h(prepare_form_data_for_output($row['issue_date'], 'date')) . '</td>
            <td class="align-middle text-nowrap chart_label">' . h($row['gib_number']) . '</td>
            <td style="max-width:280px" class="align-middle text-truncate">
                <div class="text-truncate">' . h($row['supplier_title']) . '</div>
                <div class="small text-body-secondary">' . h($row['supplier_tax_number']) . '</div>
            </td>
            <td class="align-middle text-nowrap">' . h(erp_edoc_inbox_type_label($row['invoice_type'])) . '<div class="small text-body-secondary">' . h($row['profile']) . '</div></td>
            <td class="align-middle text-end text-nowrap" data-order="' . (int) $row['total'] . '">' . h(erp_money_out_currency((int) $row['total'], $row['currency'])) . '</td>
            <td class="align-middle">' . h($row['provider_status']) . '</td>
            <td class="align-middle">' . $output_state . '</td>
        </tr>';
}

$output_filter = '';
foreach (array_merge(array_keys($states), array('all')) as $key) {
    $label = ($key === 'all') ? lang('All') : $states[$key]['label'];
    $count = ($key === 'all') ? array_sum($counts) : (int) $counts[$key];
    $output_filter .= '<a class="btn btn-sm btn-ghost' . (($state === $key) ? ' active' : '') . '" href="erp_inbox.php?state=' . $key . '">'
        . h($label) . ' <span class="badge rounded-pill text-bg-light">' . $count . '</span></a>';
}

$output_notice = '';

if (!$installed) {
    $output_notice = '<div class="alert alert-warning my-4">' . lang('Run the software upgrade first: the incoming e-invoice table is not there yet.') . '</div>';
} elseif ($provider === '') {
    $active = erp_edoc_active();
    $output_notice = '<div class="alert alert-info my-4">'
        . (($active === '')
            ? lang('Incoming e-invoices are read from the e-document provider, and no provider is selected.')
            : lang(array('string' => '{var:1} does not hand over incoming invoices through this screen yet.', 'vars' => erp_edoc_info($active)['label'])))
        . ' <a href="' . h(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('commerce', 'pgset-invoice')) . '">' . lang('E-Invoice settings') . '</a>'
        . '</div>';
}

$output_fetch = '';

if ($provider !== '') {
    $output_fetch = '
                <form action="erp_inbox.php" method="post" class="d-flex flex-wrap align-items-center gap-1">
                    ' . get_token_field() . '
                    <input type="hidden" name="erp_action" value="fetch" />
                    <input type="hidden" name="state" value="' . h($state) . '" />
                    <input type="date" name="from" value="' . h($default_from) . '" class="form-control form-control-sm w-auto" aria-label="' . lang('Start date') . '" required />
                    <span class="text-body-secondary">&ndash;</span>
                    <input type="date" name="to" value="' . h($default_to) . '" class="form-control form-control-sm w-auto" aria-label="' . lang('End date') . '" required />
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 text-nowrap" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>' . lang('Read from the provider') . '</button>
                </form>
                ' . (($read_at > 0)
                    ? '<span class="small text-body-secondary">' . lang(array('string' => 'Last read {var:1}', 'vars' => prepare_form_data_for_output(date('Y-m-d H:i:s', $read_at), 'date and time'))) . '</span>'
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
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="' . lang('Status') . '">' . $output_filter . '</div>
            </nav>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string' => 'Action')) . '</th>
                                <th>' . lang('Date') . '</th>
                                <th>' . lang('Document Number') . '</th>
                                <th>' . lang('Supplier') . '</th>
                                <th>' . lang('Type') . '</th>
                                <th class="text-end">' . lang('Total') . '</th>
                                <th>' . lang('At GİB') . '</th>
                                <th>' . lang('Status') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
