<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Parasut e-invoice inbox. Displays three tabs:
 *   1. Incoming e-invoices (purchase_bills)   — invoices received via GIB, the tax authority
 *   2. Outgoing invoices (sales_invoices)     — invoices sent via Parasut
 *   3. VKN lookup (e_invoice_inboxes)         — check whether a tax number is registered for e-invoice
 *
 * Requires ENABLE_PARASUT and PARASUT_COMPANY_ID to be set.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_parasut_inbox');

if (!defined('ENABLE_PARASUT') || !ENABLE_PARASUT || !defined('PARASUT_COMPANY_ID') || PARASUT_COMPANY_ID === '') {
    $liveform->mark_error('_error', lang('Parasut integration is not enabled. Please configure it in Settings.'));
    go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . pg_settings_return_url('commerce', 'pgset-invoice'));
}

// Determine active tab.
$tab = in_array($_GET['tab'] ?? '', ['incoming', 'outgoing', 'vkn']) ? ($_GET['tab'] ?? 'incoming') : 'incoming';

// ------------------------------------------------------------------
// Tab: VKN lookup — handle AJAX POST
// ------------------------------------------------------------------
$vkn_result = null;
if ($tab === 'vkn' && isset($_POST['vkn_query'])) {
    validate_token_field();
    $vkn = trim($_POST['vkn'] ?? '');
    if ($vkn === '') {
        $liveform->mark_error('vkn', lang('Please enter a VKN or TCKN.'));
    } else {
        $vkn_result = parasut_check_einvoice_address($vkn);
        if (!$vkn_result['success']) {
            $liveform->mark_error('_error', lang('Parasut API Error') . ': ' . h($vkn_result['error']));
        }
    }
}

// ------------------------------------------------------------------
// Pagination helper
// ------------------------------------------------------------------
$per_page = 25;
$page     = max(1, (int)($_GET['pg'] ?? 1));

// ------------------------------------------------------------------
// Tab: Incoming purchase bills
// ------------------------------------------------------------------
$incoming_result  = null;
$incoming_total   = 0;
$incoming_pages   = 1;
if ($tab === 'incoming') {
    $filters = [];
    if (!empty($_GET['start_date'])) { $filters['start_date'] = $_GET['start_date']; }
    if (!empty($_GET['end_date']))   { $filters['end_date']   = $_GET['end_date']; }
    if (!empty($_GET['vkn_filter'])) { /* Contact VKN — resolved via e_invoice_inboxes if needed */ }

    $incoming_result = parasut_list_purchase_bills($filters, $page, $per_page);
    if (!$incoming_result['success']) {
        $liveform->mark_error('_error', lang('Parasut API Error') . ': ' . h($incoming_result['error']));
    } else {
        $incoming_total = (int)($incoming_result['meta']['total_count'] ?? count($incoming_result['data']));
        $incoming_pages = max(1, (int)ceil($incoming_total / $per_page));
    }
}

// ------------------------------------------------------------------
// Tab: Outgoing sales invoices
// ------------------------------------------------------------------
$outgoing_result = null;
$outgoing_total  = 0;
$outgoing_pages  = 1;
if ($tab === 'outgoing') {
    $filters = [];
    if (!empty($_GET['start_date'])) { $filters['start_date'] = $_GET['start_date']; }
    if (!empty($_GET['end_date']))   { $filters['end_date']   = $_GET['end_date']; }

    $outgoing_result = parasut_list_sales_invoices($filters, $page, $per_page);
    if (!$outgoing_result['success']) {
        $liveform->mark_error('_error', lang('Parasut API Error') . ': ' . h($outgoing_result['error']));
    } else {
        $outgoing_total = (int)($outgoing_result['meta']['total_count'] ?? count($outgoing_result['data']));
        $outgoing_pages = max(1, (int)ceil($outgoing_total / $per_page));
    }
}

// ------------------------------------------------------------------
// Build filter form base URL
// ------------------------------------------------------------------
$base_url  = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=' . $tab;
$start_date_val = h($_GET['start_date'] ?? '');
$end_date_val   = h($_GET['end_date']   ?? '');

// ------------------------------------------------------------------
// Build tab nav
// ------------------------------------------------------------------
$tab_incoming_active = ($tab === 'incoming') ? ' active' : '';
$tab_outgoing_active = ($tab === 'outgoing') ? ' active' : '';
$tab_vkn_active      = ($tab === 'vkn')      ? ' active' : '';

$output_tab_nav = '
<ul class="nav nav-tabs mb-3" id="parasutTabs">
    <li class="nav-item">
        <a class="nav-link' . $tab_incoming_active . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=incoming">
            <i class="bi bi-inbox me-2"></i>' . lang('Incoming Invoices') . '
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link' . $tab_outgoing_active . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=outgoing">
            <i class="bi bi-send me-2"></i>' . lang('Outgoing Invoices') . '
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link' . $tab_vkn_active . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=vkn">
            <i class="bi bi-search me-2"></i>' . lang('VKN / TCKN Lookup') . '
        </a>
    </li>
</ul>';

// ------------------------------------------------------------------
// Date filter bar (shared for incoming / outgoing)
// ------------------------------------------------------------------
$output_date_filter = '';
if (in_array($tab, ['incoming', 'outgoing'])) {
    $output_date_filter = '
    <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="' . h($tab) . '" />
        <div class="col-auto">
            <label class="form-label small text-muted mb-1">' . lang('Start Date') . '</label>
            <input type="date" class="form-control form-control-sm" name="start_date" value="' . $start_date_val . '" />
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-1">' . lang('End Date') . '</label>
            <input type="date" class="form-control form-control-sm" name="end_date" value="' . $end_date_val . '" />
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>' . lang('Filter') . '</button>
            <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=' . h($tab) . '" class="btn btn-sm btn-outline-secondary ms-1">' . lang('Clear') . '</a>
        </div>
    </form>';
}

// ------------------------------------------------------------------
// Build pagination links
// ------------------------------------------------------------------
function _parasut_pagination($current, $total, $tab, $extra_params = []) {
    if ($total <= 1) return '';
    $base = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=' . h($tab);
    foreach ($extra_params as $k => $v) {
        if ($v !== '') $base .= '&' . urlencode($k) . '=' . urlencode($v);
    }
    $out = '<nav><ul class="pagination pagination-sm justify-content-center mt-3">';
    $out .= '<li class="page-item' . ($current <= 1 ? ' disabled' : '') . '">
        <a class="page-link" href="' . $base . '&pg=' . ($current - 1) . '">&laquo;</a></li>';
    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);
    if ($start > 1) $out .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    for ($i = $start; $i <= $end; $i++) {
        $out .= '<li class="page-item' . ($i === $current ? ' active' : '') . '">
            <a class="page-link" href="' . $base . '&pg=' . $i . '">' . $i . '</a></li>';
    }
    if ($end < $total) $out .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    $out .= '<li class="page-item' . ($current >= $total ? ' disabled' : '') . '">
        <a class="page-link" href="' . $base . '&pg=' . ($current + 1) . '">&raquo;</a></li>';
    $out .= '</ul></nav>';
    return $out;
}

// ------------------------------------------------------------------
// Build status badge helper
// ------------------------------------------------------------------
function _parasut_status_badge($status) {
    $map = [
        'open'         => ['bg-warning text-dark', lang('Open')],
        'overdue'      => ['bg-danger',             lang('Overdue')],
        'paid'         => ['bg-success',            lang('Paid')],
        'partially_paid' => ['bg-info text-dark',   lang('Partially Paid')],
        'cancelled'    => ['bg-secondary',          lang('Cancelled')],
        'deleted'      => ['bg-dark',               lang('Deleted')],
    ];
    $info  = $map[$status] ?? ['bg-secondary', ucfirst(str_replace('_', ' ', $status))];
    return '<span class="badge ' . $info[0] . '">' . h($info[1]) . '</span>';
}

// ------------------------------------------------------------------
// Build item_type badge helper
// ------------------------------------------------------------------
function _parasut_type_badge($type) {
    $map = [
        'invoice'       => ['bg-primary',           lang('Invoice')],
        'e_invoice'     => ['bg-success',           lang('E-Invoice')],
        'e_archive'     => ['bg-info text-dark',    lang('E-Archive')],
        'e_smm'         => ['bg-warning text-dark', lang('E-SMM')],
        'basic'         => ['bg-secondary',         lang('Basic')],
        'refund'        => ['bg-danger',             lang('Refund')],
    ];
    $info  = $map[$type] ?? ['bg-secondary', ucfirst(str_replace('_', ' ', $type))];
    return '<span class="badge ' . $info[0] . '">' . h($info[1]) . '</span>';
}

// ------------------------------------------------------------------
// Tab content: Incoming invoices
// ------------------------------------------------------------------
$output_tab_content = '';

if ($tab === 'incoming') {
    $rows = '';
    $extra_params = ['start_date' => $_GET['start_date'] ?? '', 'end_date' => $_GET['end_date'] ?? ''];

    if ($incoming_result && $incoming_result['success'] && !empty($incoming_result['data'])) {
        foreach ($incoming_result['data'] as $bill) {
            $attr    = $bill['attributes'] ?? [];
            $bid     = $bill['id'];
            $contact_id = $bill['relationships']['supplier']['data']['id'] ?? null;
            $contact_name = $contact_id ? ($incoming_result['contacts'][$contact_id] ?? '—') : '—';

            $issue_date = $attr['issue_date'] ?? '';
            $due_date   = $attr['due_date']   ?? '';
            $gross      = pg_format_number((float)($attr['gross_total'] ?? 0), 2);
            $net        = pg_format_number((float)($attr['net_total'] ?? 0), 2);
            $item_type  = $attr['item_type']  ?? '';
            $status     = $attr['payment_status'] ?? ($attr['status'] ?? '');
            $description = h($attr['description'] ?? '');

            $js_detail_id = 'detail_' . preg_replace('/[^a-z0-9]/i', '_', $bid);

            $rows .= '
            <tr>
                <td class="align-middle">' . h($issue_date) . '</td>
                <td class="align-middle">' . h($contact_name) . '</td>
                <td class="align-middle">' . _parasut_type_badge($item_type) . '</td>
                <td class="align-middle text-end">' . $net . '</td>
                <td class="align-middle text-end">' . $gross . '</td>
                <td class="align-middle">' . _parasut_status_badge($status) . '</td>
                <td class="align-middle text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#detailModal"
                        data-bill-id="' . h($bid) . '"
                        data-contact="' . h($contact_name) . '"
                        data-date="' . h($issue_date) . '"
                        data-due="' . h($due_date) . '"
                        data-type="' . h($item_type) . '"
                        data-status="' . h($status) . '"
                        data-net="' . $net . '"
                        data-gross="' . $gross . '"
                        data-desc="' . h($description) . '">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>';
        }
    } elseif ($incoming_result && $incoming_result['success']) {
        $rows = '<tr><td colspan="7" class="text-center text-muted py-4">' . lang('No records found.') . '</td></tr>';
    } elseif (!$incoming_result) {
        $rows = '<tr><td colspan="7" class="text-center text-muted py-4">—</td></tr>';
    }

    $output_tab_content = $output_date_filter . '
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>' . lang('Date') . '</th>
                        <th>' . lang('Supplier') . '</th>
                        <th>' . lang('Type') . '</th>
                        <th class="text-end">' . lang('Net') . '</th>
                        <th class="text-end">' . lang('Gross') . '</th>
                        <th>' . lang('Status') . '</th>
                        <th class="text-end">' . lang('Action') . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </div>
    </div>
    ' . _parasut_pagination($page, $incoming_pages, $tab, $extra_params);
}

// ------------------------------------------------------------------
// Tab content: Outgoing invoices
// ------------------------------------------------------------------
if ($tab === 'outgoing') {
    $rows = '';
    $extra_params = ['start_date' => $_GET['start_date'] ?? '', 'end_date' => $_GET['end_date'] ?? ''];

    if ($outgoing_result && $outgoing_result['success'] && !empty($outgoing_result['data'])) {
        foreach ($outgoing_result['data'] as $inv) {
            $attr    = $inv['attributes'] ?? [];
            $iid     = $inv['id'];
            $contact_id = $inv['relationships']['contact']['data']['id'] ?? null;
            $contact_name = $contact_id ? ($outgoing_result['contacts'][$contact_id] ?? '—') : '—';

            $issue_date  = $attr['issue_date']  ?? '';
            $due_date    = $attr['due_date']    ?? '';
            $invoice_no  = ($attr['invoice_series'] ?? '') . ($attr['invoice_id'] ? '/' . $attr['invoice_id'] : '');
            $gross       = pg_format_number((float)($attr['gross_total'] ?? 0), 2);
            $net         = pg_format_number((float)($attr['net_total'] ?? 0), 2);
            $item_type   = $attr['item_type']   ?? '';
            $status      = $attr['payment_status'] ?? ($attr['status'] ?? '');
            $description = h($attr['description'] ?? '');

            $rows .= '
            <tr>
                <td class="align-middle">' . h($issue_date) . '</td>
                <td class="align-middle">' . h($invoice_no) . '</td>
                <td class="align-middle">' . h($contact_name) . '</td>
                <td class="align-middle">' . _parasut_type_badge($item_type) . '</td>
                <td class="align-middle text-end">' . $net . '</td>
                <td class="align-middle text-end">' . $gross . '</td>
                <td class="align-middle">' . _parasut_status_badge($status) . '</td>
                <td class="align-middle text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#detailModal"
                        data-bill-id="' . h($iid) . '"
                        data-contact="' . h($contact_name) . '"
                        data-date="' . h($issue_date) . '"
                        data-due="' . h($due_date) . '"
                        data-type="' . h($item_type) . '"
                        data-status="' . h($status) . '"
                        data-net="' . $net . '"
                        data-gross="' . $gross . '"
                        data-desc="' . h($description) . '">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>';
        }
    } elseif ($outgoing_result && $outgoing_result['success']) {
        $rows = '<tr><td colspan="8" class="text-center text-muted py-4">' . lang('No records found.') . '</td></tr>';
    } else {
        $rows = '<tr><td colspan="8" class="text-center text-muted py-4">—</td></tr>';
    }

    $output_tab_content = $output_date_filter . '
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>' . lang('Date') . '</th>
                        <th>' . lang('Invoice No') . '</th>
                        <th>' . lang('Customer') . '</th>
                        <th>' . lang('Type') . '</th>
                        <th class="text-end">' . lang('Net') . '</th>
                        <th class="text-end">' . lang('Gross') . '</th>
                        <th>' . lang('Status') . '</th>
                        <th class="text-end">' . lang('Action') . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </div>
    </div>
    ' . _parasut_pagination($page, $outgoing_pages, $tab, $extra_params);
}

// ------------------------------------------------------------------
// Tab content: VKN lookup
// ------------------------------------------------------------------
if ($tab === 'vkn') {
    $output_vkn_results = '';

    if ($vkn_result !== null && $vkn_result['success']) {
        if (!empty($vkn_result['data'])) {
            $output_vkn_results = '<div class="mt-3">';
            foreach ($vkn_result['data'] as $inbox) {
                $attr    = $inbox['attributes'] ?? [];
                $vkn_val = h($attr['vkn'] ?? '');
                $addr    = h($attr['e_invoice_address'] ?? '');
                $output_vkn_results .= '
                <div class="alert alert-success d-flex align-items-start gap-3 mb-2">
                    <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                    <div>
                        <div class="fw-semibold">' . lang('Registered for E-Invoice') . '</div>
                        <div class="small"><strong>VKN/TCKN:</strong> ' . $vkn_val . '</div>
                        <div class="small"><strong>' . lang('E-Invoice Address') . ':</strong> ' . $addr . '</div>
                    </div>
                </div>';
            }
            $output_vkn_results .= '</div>';
        } elseif (isset($_POST['vkn_query'])) {
            $output_vkn_results = '
            <div class="alert alert-warning mt-3 d-flex align-items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                <div>
                    <div class="fw-semibold">' . lang('Not registered for E-Invoice') . '</div>
                    <div class="small">' . lang('This VKN/TCKN is not registered in the GIB e-invoice system. You should issue an e-archive invoice instead.') . '</div>
                </div>
            </div>';
        }
    }

    $vkn_input_val = h($_POST['vkn'] ?? '');
    $output_tab_content = '
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">' . lang('Check if a VKN or TCKN is registered for e-invoice in the GIB system.') . '</h6>
        </div>
        <div class="card-body">
            <form method="post" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=vkn">
                ' . get_token_field() . '
                <input type="hidden" name="vkn_query" value="1" />
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-6 col-lg-4">
                        <label class="form-label">' . lang('VKN / TCKN') . '</label>
                        ' . $liveform->output_field(['type' => 'text', 'id' => 'vkn', 'name' => 'vkn', 'class' => 'form-control', 'value' => $vkn_input_val, 'maxlength' => '11', 'inputmode' => 'numeric', 'placeholder' => '1234567890']) . '
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary" data-loading-content="' . lang('Querying') . '">
                            <i class="bi bi-search me-1"></i>' . lang('Query') . '
                        </button>
                    </div>
                </div>
            </form>
            ' . $output_vkn_results . '
        </div>
    </div>';
}

// ------------------------------------------------------------------
// Detail modal (populated via JS from data attributes)
// ------------------------------------------------------------------
$output_detail_modal = '
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel"><i class="bi bi-file-earmark-text me-2"></i>' . lang('Invoice Detail') . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted">' . lang('Contact') . '</td><td id="modal-contact" class="fw-semibold"></td></tr>
                    <tr><td class="text-muted">' . lang('Date') . '</td><td id="modal-date"></td></tr>
                    <tr><td class="text-muted">' . lang('Due Date') . '</td><td id="modal-due"></td></tr>
                    <tr><td class="text-muted">' . lang('Type') . '</td><td id="modal-type"></td></tr>
                    <tr><td class="text-muted">' . lang('Status') . '</td><td id="modal-status"></td></tr>
                    <tr><td class="text-muted">' . lang('Net') . '</td><td id="modal-net"></td></tr>
                    <tr><td class="text-muted">' . lang('Gross') . '</td><td id="modal-gross"></td></tr>
                    <tr id="modal-desc-row"><td class="text-muted">' . lang('Description') . '</td><td id="modal-desc"></td></tr>
                    <tr><td class="text-muted">' . lang('Parasut ID') . '</td><td id="modal-id" class="font-monospace small text-muted"></td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var modal = document.getElementById("detailModal");
    if (!modal) return;
    modal.addEventListener("show.bs.modal", function (e) {
        var btn = e.relatedTarget;
        document.getElementById("modal-contact").textContent = btn.dataset.contact || "—";
        document.getElementById("modal-date").textContent    = btn.dataset.date    || "—";
        document.getElementById("modal-due").textContent     = btn.dataset.due     || "—";
        document.getElementById("modal-type").textContent    = btn.dataset.type    || "—";
        document.getElementById("modal-status").textContent  = btn.dataset.status  || "—";
        document.getElementById("modal-net").textContent     = btn.dataset.net     || "—";
        document.getElementById("modal-gross").textContent   = btn.dataset.gross   || "—";
        document.getElementById("modal-id").textContent      = btn.dataset.billId  || "—";
        var desc = btn.dataset.desc || "";
        document.getElementById("modal-desc").textContent    = desc;
        document.getElementById("modal-desc-row").style.display = desc ? "" : "none";
    });
});
</script>';

// ------------------------------------------------------------------
// Output
// ------------------------------------------------------------------
echo pg_page_shell([
    'title'         => lang('Parasut E-Invoice Inbox'),
    'extra classes' => 'store',
    'icon'          => 'store',
    'heading'       => lang('Parasut E-Invoice Inbox'),
    'heading_description' => lang('View and manage e-invoices via Parasut API.'),
    'cancel'        => false,
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <div class="row mb-2 flex-wrap">
                <div class="col-12 text-center text-md-start">
                    
                    <nav id="button_bar" class="navigation" aria-label="Button Bar">
                        <a class="btn btn-sm btn-outline-secondary"
                           href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_parasut_inbox.php?tab=' . h($tab) . '"
                           title="' . lang('Refresh') . '">
                            <i class="bi bi-arrow-clockwise"></i>
                        </a>
                    </nav>
                </div>
            </div>

            ' . $output_tab_nav . '
            ' . $output_tab_content . '
        </div>
    </div>
' .
$output_detail_modal .
'</main>' . output_footer();

$liveform->remove_form();
