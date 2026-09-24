<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the audit trail: who did what in the books, when and from where,
 * one entry per action. The lines are written by includes/erp/audit.php.
 * Add type= and id= to the address to follow one record.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'read')) {
    exit();
}

// Who changed what is for the people who answer for the books: the ERP
// settings managers and the accountant.
if (((int) $user['role'] >= 3) && empty($user['manage_erp_settings']) && !(defined('USER_ERP_READONLY') && USER_ERP_READONLY)) {
    log_activity(lang('access denied to erp audit trail'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_audit');

$date_or_empty = function ($value) {
    $value = trim((string) $value);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
};

$types = erp_audit_object_types();
$filters = array(
    'from' => $date_or_empty($_GET['from'] ?? ''),
    'to' => $date_or_empty($_GET['to'] ?? ''),
    'user' => mb_substr(trim((string) ($_GET['user'] ?? '')), 0, 100),
    'type' => (isset($types[(string) ($_GET['type'] ?? '')]) || ((string) ($_GET['type'] ?? '') === 'log')) ? (string) $_GET['type'] : '',
    'id' => max(0, (int) ($_GET['id'] ?? 0)),
    'search' => mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 100),
);
$query = array_filter($filters, function ($value) {
    return ($value !== '') && ($value !== 0);
});

if (!erp_audit_ready()) {
    $liveform->mark_error('', lang('The audit trail comes with the software update; run the update to use it.'));
} elseif ((string) ($_GET['format'] ?? '') === 'csv') {
    log_activity(lang('erp audit trail was exported'), $_SESSION['sessionusername']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="erp-audit-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    erp_audit_csv($filters);
    exit();
}

$before = max(0, (int) ($_GET['before'] ?? 0));
$page = erp_audit_entries($filters, $before, 100);
$sources = erp_audit_sources();
$people = erp_audit_people();

$output_rows = '';

foreach ($page['entries'] as $entry) {
    $first = $entry['rows'][0];
    $lines = '';
    $records = '';

    foreach ($entry['rows'] as $row) {
        if ((string) $row['event'] === 'log') {
            $lines .= '<div>' . h((string) $row['description']) . '</div>';
            continue;
        }

        $type = (string) $row['object_type'];
        $label = ((string) $row['label'] !== '') ? (string) $row['label'] : ('#' . (int) $row['object_id']);
        $link = (isset($types[$type]) && ((int) $row['object_id'] > 0))
            ? '<a class="link-body-emphasis" href="' . h($types[$type][1] . (int) $row['object_id']) . '">' . h($label) . '</a>'
            : h($label);

        $records .= '<div class="small d-flex flex-wrap gap-2 align-items-baseline">'
            . '<span class="badge text-bg-light border">' . h(erp_audit_event_text($row)) . '</span>'
            . '<span>' . $link . '</span>'
            . (((int) $row['amount'] !== 0) ? '<span class="text-body-secondary">' . h(erp_money_out_currency((int) $row['amount'], (string) $row['currency'])) . '</span>' : '')
            . '</div>';
    }

    $who = ((string) $first['source'] === 'job') ? '<span class="text-body-secondary">' . h($sources['job']) . '</span>' : h((string) $first['username']);

    $output_rows .= '
        <tr>
            <td class="text-nowrap align-top" data-sort="' . (int) $first['created_at'] . '">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $first['created_at']), 'date and time')) . '</td>
            <td class="align-top">' . $who
                . (((string) $first['source'] === 'api') ? ' <span class="badge text-bg-info">' . h($sources['api']) . '</span>' : '')
                . (((string) $first['ip'] !== '') ? '<div class="small text-body-secondary font-monospace">' . h((string) $first['ip']) . '</div>' : '') . '</td>
            <td class="align-top">' . $lines . $records . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="3" class="text-center text-body-secondary py-4">'
        . (empty($query) ? lang('Nothing has been recorded yet. Every document, receipt and change made in the ERP from now on is listed here.') : lang('Nothing matches these filters.')) . '</td></tr>';
}

$user_options = '<option value="">' . lang('Everyone') . '</option><option value="@job"' . (($filters['user'] === '@job') ? ' selected' : '') . '>' . h($sources['job']) . '</option>';
foreach ($people as $name => $source) {
    $user_options .= '<option value="' . h($name) . '"' . (($filters['user'] === $name) ? ' selected' : '') . '>' . h($name . (($source === 'api') ? ' (' . $sources['api'] . ')' : '')) . '</option>';
}

$type_options = '<option value="">' . lang('All records') . '</option>';
foreach ($types as $code => $type) {
    $type_options .= '<option value="' . h($code) . '"' . (($filters['type'] === $code) ? ' selected' : '') . '>' . h($type[0]) . '</option>';
}
$type_options .= '<option value="log"' . (($filters['type'] === 'log') ? ' selected' : '') . '>' . lang('Screen log lines') . '</option>';

$output_record = '';
if (($filters['id'] > 0) && isset($types[$filters['type']])) {
    $output_record = '<div class="alert alert-info d-flex flex-wrap gap-2 align-items-center">'
        . '<span>' . h(lang(array('string' => 'Only the entries for this record: {var:1} #{var:2}', 'vars' => array($types[$filters['type']][0], $filters['id'])))) . '</span>'
        . '<a class="ms-auto" href="erp_audit.php">' . lang('All entries') . '</a></div>';
}

$output_more = ($page['next'] > 0)
    ? '<div class="text-center mb-4"><a class="btn btn-sm btn-outline-secondary" href="erp_audit.php?' . h(http_build_query($query + array('before' => $page['next']))) . '">' . lang('Older entries') . '</a></div>'
    : '';

echo
pg_page_shell([
    'title' => lang('Audit trail'),
    'extra classes' => 'erp erp_audit',
    'icon' => 'erp',
    'heading' => lang('Audit trail'),
    'heading_description' => lang('Who did what in the ERP, when and from where: documents, receipts, accounts, expenses and every change the ERP screens record. Kept for as long as the books are, unlike the site log, which is emptied after six months.'),
    'cancel' => false,
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12 col-xxl-10">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '

            <form method="get" action="erp_audit.php" class="card my-4" role="search">
                <div class="card-body row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="audit_from">' . lang('Start date') . '</label>
                        <input type="date" class="form-control form-control-sm" id="audit_from" name="from" value="' . h($filters['from']) . '" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="audit_to">' . lang('End date') . '</label>
                        <input type="date" class="form-control form-control-sm" id="audit_to" name="to" value="' . h($filters['to']) . '" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="audit_user">' . lang('User') . '</label>
                        <select class="form-select form-select-sm" id="audit_user" name="user">' . $user_options . '</select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small" for="audit_type">' . lang('Record') . '</label>
                        <select class="form-select form-select-sm" id="audit_type" name="type">' . $type_options . '</select>
                        ' . (($filters['id'] > 0) ? '<input type="hidden" name="id" value="' . (int) $filters['id'] . '" />' : '') . '
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small" for="audit_search">' . lang('Search') . '</label>
                        <div class="input-group input-group-sm">
                            <input type="search" class="form-control" id="audit_search" name="search" value="' . h($filters['search']) . '" placeholder="' . h(lang('Number, name, user or IP')) . '" />
                            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">' . lang('Search') . '</span></button>
                        </div>
                    </div>
                </div>
            </form>

            <nav id="button_bar" class="pg-toolbar navigation" aria-label="' . lang('Button Bar') . '">
                <div class="pg-toolbar-grow small text-body-secondary">' . lang('Newest first; the lines one action wrote are shown together.') . '</div>
                ' . (!empty($query) ? '<a class="btn btn-sm btn-outline-secondary" href="erp_audit.php">' . lang('Clear filters') . '</a>' : '') . '
                ' . (erp_audit_ready() ? '<a class="btn btn-sm btn-outline-secondary" href="erp_audit.php?' . h(http_build_query($query + array('format' => 'csv'))) . '"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>' . lang('Download as CSV') . '</a>' : '') . '
            </nav>

            ' . $output_record . '

            <div class="card my-4">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0" data-pg-sort>
                        <thead>
                            <tr>
                                <th style="width:11rem">' . lang('Date') . '</th>
                                <th style="width:14rem">' . lang('User') . '</th>
                                <th>' . lang('Action') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
            ' . $output_more . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
