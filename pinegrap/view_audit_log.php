<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit trail: who changed what, when and from where, with each changed
 * field before and after (audit_log, written by pg_audit()). Lines are
 * filtered by date, source, user, action, record type, record and request,
 * shown newest first a page at a time, and handed out as a CSV file with the
 * same filters. Each export is itself a line in the trail.
 *
 * Reading only: nothing here changes a line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_audit_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

// The picker posts its own date text; the filters read ISO.
$audit_view_input = $_GET;

foreach (array('from', 'to') as $audit_view_key) {
    $audit_view_date = (isset($_GET[$audit_view_key]) && is_string($_GET[$audit_view_key])) ? trim($_GET[$audit_view_key]) : '';
    $audit_view_input[$audit_view_key] = (($audit_view_date !== '') && validate_date($audit_view_date)) ? prepare_form_data_for_input($audit_view_date, 'date') : '';
}

$audit_view_filters = pg_audit_view_filters($audit_view_input);
$audit_view_where = pg_audit_view_where($audit_view_filters);
$audit_view_limits = pg_audit_view_limits();
$audit_view_columns = 'id, created_at, request_id, source, user_id, username, ip, action, entity_type, entity_id, label, before_json, after_json';

// The CSV file: the lines the filters select, newest first, read in slices
// by id so a long trail is never held in memory at once.
if (isset($_GET['export']) && ($_GET['export'] === 'csv')) {
    pg_audit('export', 'audit', 0, array(), array('format' => 'csv') + pg_audit_view_query($audit_view_filters), lang('Audit trail'));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-trail-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');

    $audit_view_out = fopen('php://output', 'w');

    // Byte order mark, so Excel opens the file as UTF-8.
    fwrite($audit_view_out, "\xEF\xBB\xBF");
    fputcsv($audit_view_out, array_values(pg_audit_view_csv_columns()), ',', '"', '\\');

    $audit_view_left = $audit_view_limits['export'];
    $audit_view_last = 0;

    while ($audit_view_left > 0) {
        $audit_view_rows = db_items(
            "SELECT " . $audit_view_columns . " FROM audit_log
            WHERE " . $audit_view_where . (($audit_view_last > 0) ? " AND (id < '" . $audit_view_last . "')" : '') . "
            ORDER BY id DESC
            LIMIT " . min(1000, $audit_view_left)
        );

        if (!$audit_view_rows) {
            break;
        }

        foreach ($audit_view_rows as $audit_view_row) {
            fputcsv($audit_view_out, pg_audit_view_csv_line($audit_view_row), ',', '"', '\\');
            $audit_view_last = (int) $audit_view_row['id'];
        }

        $audit_view_left -= count($audit_view_rows);
    }

    fclose($audit_view_out);
    exit();
}

// One page, newest first; one line more than shown tells whether an older page exists.
$audit_view_page = $audit_view_filters['page'];
$audit_view_rows = db_items(
    "SELECT " . $audit_view_columns . " FROM audit_log
    WHERE " . $audit_view_where . "
    ORDER BY created_at DESC, id DESC
    LIMIT " . (($audit_view_page - 1) * $audit_view_limits['page']) . ", " . ($audit_view_limits['page'] + 1)
);

$audit_view_more = count($audit_view_rows) > $audit_view_limits['page'];
$audit_view_rows = array_slice($audit_view_rows, 0, $audit_view_limits['page']);

$audit_view_query = pg_audit_view_query($audit_view_filters);

// A link to this screen with the current filters changed by $change.
$audit_view_url = function (array $change) use ($audit_view_query) {
    $query = array_merge($audit_view_query, $change);

    foreach (array('from', 'to') as $key) {
        if (isset($query[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $query[$key])) {
            $query[$key] = prepare_form_data_for_output($query[$key], 'date', false);
        }
    }

    $query = array_filter($query, function ($value) {
        return ($value !== '') && ($value !== 0) && ($value !== null);
    });

    return 'view_audit_log.php' . ($query ? '?' . http_build_query($query) : '');
};

// A value as it is shown in the changes table.
$audit_view_cell = function (array $value) {
    switch ($value['kind']) {
        case 'none':
            return '<span class="fg-secondary">—</span>';
        case 'secret':
            return '<span class="badge theme-secondary">' . h(lang('Hidden')) . '</span>';
        case 'cut':
            return '<span class="text-break">' . h($value['text']) . '…</span> <span class="small fg-secondary" title="SHA-1 ' . h($value['sha1']) . '">(' . h(lang(array('string' => '{var:1} bytes', 'vars' => array(pg_format_number($value['length'], 0))))) . ')</span>';
        case 'json':
            return '<pre class="small mb-0" style="max-height:16rem;overflow:auto;white-space:pre-wrap;word-break:break-word">' . h($value['text']) . '</pre>';
    }

    return ($value['text'] === '') ? '<span class="fg-secondary fst-italic">' . h(lang('empty')) . '</span>' : '<span class="text-break" style="white-space:pre-wrap">' . h($value['text']) . '</span>';
};

$audit_view_list = '';

foreach ($audit_view_rows as $audit_view_row) {
    $audit_view_changes = pg_audit_view_changes($audit_view_row['before_json'], $audit_view_row['after_json']);
    $audit_view_type = (string) $audit_view_row['entity_type'];
    $audit_view_id = (int) $audit_view_row['entity_id'];

    // Who: the operator, the application (its owner's id) or the job.
    $audit_view_who = '<span class="badge badge-subtle theme-secondary border me-1">' . h(pg_audit_view_label('source', $audit_view_row['source'])) . '</span>';

    if ((string) $audit_view_row['username'] !== '') {
        $audit_view_who .= '<a href="' . h($audit_view_url(array('user' => (string) $audit_view_row['username'], 'page' => 1))) . '">' . h($audit_view_row['username']) . '</a>';
    } elseif ((int) $audit_view_row['user_id'] > 0) {
        $audit_view_who .= '<a href="' . h($audit_view_url(array('user' => (string) (int) $audit_view_row['user_id'], 'page' => 1))) . '">#' . (int) $audit_view_row['user_id'] . '</a>';
    }

    // What: the record's name links to its whole history; the icon opens it.
    $audit_view_name = ((string) $audit_view_row['label'] !== '') ? (string) $audit_view_row['label'] : (($audit_view_id > 0) ? '#' . $audit_view_id : pg_audit_view_label('type', $audit_view_type));
    $audit_view_record = '<div class="small fg-secondary">' . h(pg_audit_view_label('type', $audit_view_type)) . (($audit_view_id > 0) ? ' #' . $audit_view_id : '') . '</div>';
    $audit_view_record .= '<a class="text-break" href="' . h($audit_view_url(array('type' => $audit_view_type, 'id' => $audit_view_id, 'q' => '', 'page' => 1))) . '" title="' . h(lang('Show the history of this record')) . '">' . h($audit_view_name) . '</a>';

    $audit_view_open = pg_audit_view_record_url($audit_view_type, $audit_view_id);

    if ($audit_view_open !== '') {
        $audit_view_record .= ' <a href="' . h($audit_view_open) . '" class="pg-link-secondary" title="' . h(lang('Open')) . '" aria-label="' . h(lang('Open')) . '"><i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i></a>';
    }

    // The changed fields, folded: a line can hold dozens.
    $audit_view_details = '';

    if ($audit_view_changes) {
        $audit_view_change_rows = '';

        foreach ($audit_view_changes as $audit_view_change) {
            $audit_view_change_rows .= '
                                            <tr>
                                                <td class="text-nowrap"><code>' . h($audit_view_change['field']) . '</code></td>
                                                <td>' . ($audit_view_change['names_only'] ? '<span class="fg-secondary">' . h(lang('Too large to keep')) . '</span>' : $audit_view_cell($audit_view_change['before'])) . '</td>
                                                <td>' . ($audit_view_change['names_only'] ? '<span class="fg-secondary">' . h(lang('Too large to keep')) . '</span>' : $audit_view_cell($audit_view_change['after'])) . '</td>
                                            </tr>';
        }

        $audit_view_details = '
                                <details>
                                    <summary class="small">' . h(lang(array('string' => '{var:1} fields', 'vars' => array(count($audit_view_changes))))) . '</summary>
                                    <table class="table table-sm table-bordered small mt-3 mb-0" style="table-layout:fixed">
                                        <thead>
                                            <tr>
                                                <th style="width:30%">' . h(lang('Field')) . '</th>
                                                <th>' . h(lang('Before')) . '</th>
                                                <th>' . h(lang('After')) . '</th>
                                            </tr>
                                        </thead>
                                        <tbody>' . $audit_view_change_rows . '
                                        </tbody>
                                    </table>
                                </details>';
    }

    $audit_view_request = ((string) $audit_view_row['request_id'] !== '')
        ? '<div><a class="small pg-link-secondary" href="' . h($audit_view_url(array('request' => (string) $audit_view_row['request_id'], 'page' => 1))) . '" title="' . h(lang('Show every line of this request')) . '"><code>' . h($audit_view_row['request_id']) . '</code></a></div>'
        : '';

    $audit_view_list .= '
                        <tr>
                            <td class="text-nowrap">' . prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $audit_view_row['created_at']), 'date and time') . '</td>
                            <td>' . $audit_view_who . '</td>
                            <td class="text-nowrap"><a href="' . h($audit_view_url(array('action' => (string) $audit_view_row['action'], 'page' => 1))) . '" class="pg-link-body-emphasis">' . h(pg_audit_view_label('action', $audit_view_row['action'])) . '</a></td>
                            <td style="min-width:12rem">' . $audit_view_record . '</td>
                            <td style="min-width:16rem">' . $audit_view_details . '</td>
                            <td class="small text-nowrap">' . h($audit_view_row['ip']) . $audit_view_request . '</td>
                        </tr>';
}

if ($audit_view_list === '') {
    $audit_view_list = '
                        <tr>
                            <td colspan="6" class="text-center fg-secondary py-7">' . h($audit_view_query ? lang('No line matches these filters.') : lang('Nothing has been recorded yet.')) . '</td>
                        </tr>';
}

// The choices of the type and action filters: what the trail holds, plus the
// value already chosen.
$audit_view_select = function ($name, $kind, $column) use ($audit_view_filters) {
    $words = array_map('strval', (array) db_values("SELECT DISTINCT " . $column . " FROM audit_log WHERE " . $column . " <> '' ORDER BY " . $column));

    if (($audit_view_filters[$name] !== '') && !in_array($audit_view_filters[$name], $words, true)) {
        $words[] = $audit_view_filters[$name];
    }

    $options = '<option value="">' . h(lang('All')) . '</option>';

    foreach ($words as $word) {
        $options .= '<option value="' . h($word) . '"' . (($audit_view_filters[$name] === $word) ? ' selected' : '') . '>' . h(pg_audit_view_label($kind, $word)) . '</option>';
    }

    return '<select class="form-control form-control-sm" id="audit_' . $name . '" name="' . $name . '">' . $options . '</select>';
};

$audit_view_sources = '<option value="">' . h(lang('All')) . '</option>';

foreach (pg_audit_sources() as $audit_view_source) {
    $audit_view_sources .= '<option value="' . $audit_view_source . '"' . (($audit_view_filters['source'] === $audit_view_source) ? ' selected' : '') . '>' . h(pg_audit_view_label('source', $audit_view_source)) . '</option>';
}

$audit_view_date_value = function ($key) use ($audit_view_filters) {
    return ($audit_view_filters[$key] !== '') ? prepare_form_data_for_output($audit_view_filters[$key], 'date', false) : '';
};

// Filters kept by the form that are not among its fields.
$audit_view_hidden = '';

foreach (array('id', 'request') as $audit_view_key) {
    if (isset($audit_view_query[$audit_view_key])) {
        $audit_view_hidden .= '<input type="hidden" name="' . $audit_view_key . '" value="' . h((string) $audit_view_query[$audit_view_key]) . '">';
    }
}

$audit_view_narrowed = '';

if ($audit_view_filters['id'] > 0) {
    $audit_view_narrowed .= '<span class="badge badge-subtle theme-secondary border me-3">' . h(lang('Record')) . ' #' . (int) $audit_view_filters['id'] . ' <a class="pg-link-secondary ms-1" href="' . h($audit_view_url(array('id' => 0, 'page' => 1))) . '" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></a></span>';
}

if ($audit_view_filters['request'] !== '') {
    $audit_view_narrowed .= '<span class="badge badge-subtle theme-secondary border me-3">' . h(lang('Request ID')) . ' ' . h($audit_view_filters['request']) . ' <a class="pg-link-secondary ms-1" href="' . h($audit_view_url(array('request' => '', 'page' => 1))) . '" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></a></span>';
}

$audit_view_toolbar = '
            <form action="view_audit_log.php" method="get" class="disable_shortcut card mb-7">
                <div class="card-body">
                    ' . $audit_view_hidden . '
                    <div class="row g-3 align-items-end">
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_from" class="form-label small mb-1">' . h(lang('From')) . '</label>
                            <input type="text" class="form-control form-control-sm" id="audit_from" name="from" value="' . h($audit_view_date_value('from')) . '" autocomplete="off" data-pg-date>
                        </div>
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_to" class="form-label small mb-1">' . h(lang('To')) . '</label>
                            <input type="text" class="form-control form-control-sm" id="audit_to" name="to" value="' . h($audit_view_date_value('to')) . '" autocomplete="off" data-pg-date>
                        </div>
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_type" class="form-label small mb-1">' . h(lang('Type')) . '</label>
                            ' . $audit_view_select('type', 'type', 'entity_type') . '
                        </div>
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_action" class="form-label small mb-1">' . h(lang('Action')) . '</label>
                            ' . $audit_view_select('action', 'action', 'action') . '
                        </div>
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_source" class="form-label small mb-1">' . h(lang('Source')) . '</label>
                            <select class="form-control form-control-sm" id="audit_source" name="source">' . $audit_view_sources . '</select>
                        </div>
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_user" class="form-label small mb-1">' . h(lang('User')) . '</label>
                            <input type="text" class="form-control form-control-sm" id="audit_user" name="user" value="' . h($audit_view_filters['user']) . '" maxlength="100" placeholder="' . h(lang('Name or ID')) . '">
                        </div>
                    </div>
                    <div class="pg-toolbar mt-5 mb-0">
                        <label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                            <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-ghost" name="q" value="' . h($audit_view_filters['q']) . '" maxlength="255" placeholder="' . h(lang('Record name')) . '" aria-label="' . h(lang('Record name')) . '">
                        </label>
                        <button type="submit" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-funnel me-1" aria-hidden="true"></i>' . h(lang('Filter')) . '</button>
                        ' . ($audit_view_query ? '<a href="view_audit_log.php" class="btn btn-sm btn-text theme-secondary">' . h(lang('Clear filters')) . '</a>' : '') . '
                        <div class="pg-toolbar-grow">' . $audit_view_narrowed . '</div>
                        <a href="' . h($audit_view_url(array('export' => 'csv', 'page' => 0))) . '" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>' . h(lang('Export CSV')) . '</a>
                    </div>
                </div>
            </form>
            ' . get_date_picker_format() . pg_fields_script();

$audit_view_pages = '';

if (($audit_view_page > 1) || $audit_view_more) {
    $audit_view_pages = '
            <nav class="d-flex justify-content-between align-items-center mb-12" aria-label="' . h(lang('Pages')) . '">
                ' . (($audit_view_page > 1) ? '<a class="btn btn-sm btn-outline theme-secondary" href="' . h($audit_view_url(array('page' => (($audit_view_page > 2) ? $audit_view_page - 1 : 0)))) . '"><i class="bi bi-chevron-left me-1" aria-hidden="true"></i>' . h(lang('Newer')) . '</a>' : '<span></span>') . '
                <span class="small fg-secondary">' . h(lang(array('string' => 'Page {var:1}', 'vars' => array($audit_view_page)))) . '</span>
                ' . ($audit_view_more ? '<a class="btn btn-sm btn-outline theme-secondary" href="' . h($audit_view_url(array('page' => $audit_view_page + 1))) . '">' . h(lang('Older')) . '<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i></a>' : '<span></span>') . '
            </nav>';
}

echo
pg_page_shell(array(
        'title'               => lang('Audit trail'),
        'extra classes'       => 'setting',
        'icon'                => 'setting',
        'heading'             => lang('Audit trail'),
        'heading_description' => lang('Who changed what in the panel, through the API or in a scheduled job, with each changed field before and after. Secrets are kept as changed, never with their value.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $audit_view_toolbar . '
            <div class="card mb-5">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-top mb-0">
                        <thead>
                            <tr>
                                <th>' . h(lang('Date')) . '</th>
                                <th>' . h(lang('Who')) . '</th>
                                <th>' . h(lang('Action')) . '</th>
                                <th>' . h(lang('Record')) . '</th>
                                <th>' . h(lang('Changes')) . '</th>
                                <th>' . h(lang('IP Address')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $audit_view_list . '
                        </tbody>
                    </table>
                </div>
            </div>
            ' . $audit_view_pages . '
            <p class="small fg-secondary mb-12">' . h(lang(array('string' => 'A CSV export holds at most {var:1} lines, newest first, and is recorded in the trail.', 'vars' => array(pg_format_number($audit_view_limits['export'], 0))))) . '</p>
        </div>
    </div>
</main>
' .
output_footer();
