<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit trail: who changed what, when and from where, with each changed
 * field before and after (audit_log, written by pg_audit()). Lines are
 * filtered by date, source, user, action, record type, record and request,
 * listed one page at a time by the audit_log_list_rows action
 * (includes/fn/audit_list.php), sorted by the headings and searched on the
 * server, and handed out as a CSV file with the same filters. Each export is
 * itself a line in the trail.
 *
 * Reading only: nothing here changes a line.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/audit_list.php');

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
$audit_view_condition = pg_audit_view_condition($audit_view_filters);
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
        $audit_view_result = db_q(
            "SELECT " . $audit_view_columns . " FROM audit_log
            WHERE " . $audit_view_condition['sql'] . (($audit_view_last > 0) ? " AND (id < ?)" : '') . "
            ORDER BY id DESC
            LIMIT " . min(1000, $audit_view_left),
            array_merge($audit_view_condition['params'], ($audit_view_last > 0) ? array($audit_view_last) : array())
        );
        $audit_view_rows = array();

        while ($audit_view_result && ($audit_view_row = mysqli_fetch_assoc($audit_view_result))) {
            $audit_view_rows[] = $audit_view_row;
        }

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

$audit_view_query = pg_audit_view_query($audit_view_filters);

// The lines come from the audit_log_list_rows action one page at a time
// (includes/fn/audit_list.php), sorted by the headings' kept sort.
$audit_view_sort = pg_audit_list_sort();
$audit_view_kept = pg_audit_list_link_query($audit_view_query);
$audit_view_heading_query = $audit_view_kept ? h('&' . http_build_query($audit_view_kept)) : '';

$audit_view_heading = function ($code) use ($audit_view_sort, $audit_view_heading_query) {
    return get_column_heading($audit_view_sort['sort_columns'][$code], $audit_view_sort['sort_levels'], '', $audit_view_heading_query, $code);
};

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
    $audit_view_narrowed .= '<span class="badge badge-subtle theme-secondary border me-3">' . h(lang('Record')) . ' #' . (int) $audit_view_filters['id'] . ' <a class="pg-link-secondary ms-1" href="' . h(pg_audit_list_link($audit_view_query, array('id' => 0))) . '" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></a></span>';
}

if ($audit_view_filters['request'] !== '') {
    $audit_view_narrowed .= '<span class="badge badge-subtle theme-secondary border me-3">' . h(lang('Request ID')) . ' ' . h($audit_view_filters['request']) . ' <a class="pg-link-secondary ms-1" href="' . h(pg_audit_list_link($audit_view_query, array('request' => ''))) . '" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></a></span>';
}

$audit_view_toolbar = '
            <form action="view_audit_log.php" method="get" class="disable_shortcut card mb-7">
                <div class="card-body">
                    ' . $audit_view_hidden . '
                    <div class="row g-3 align-items-end">
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_from" class="form-label small mb-1">' . h(lang('Start')) . '</label>
                            <input type="text" class="form-control form-control-sm" id="audit_from" name="from" value="' . h($audit_view_date_value('from')) . '" autocomplete="off" data-pg-date>
                        </div>
                        <div class="col-6 md:col-4 xl:col-2">
                            <label for="audit_to" class="form-label small mb-1">' . h(lang('End')) . '</label>
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
                        <a href="' . h(pg_audit_list_link($audit_view_query, array('export' => 'csv'))) . '" class="btn btn-sm btn-outline theme-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>' . h(lang('Export CSV')) . '</a>
                    </div>
                </div>
            </form>
            ' . get_date_picker_format() . pg_fields_script();

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
            <div class="pg-toolbar mb-3">
                <div class="pg-toolbar-grow"></div>
                ' . pg_list_sort_menu('view_audit_log.php', $audit_view_sort['sort_columns'], $audit_view_sort['sort_levels'], $audit_view_sort['sort_default'], $audit_view_kept) . '
            </div>
            <div class="card mb-5">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table align-top" style="width:100%;display:none" data-pg-list-action="audit_log_list_rows" data-pg-list-params="' . h(encode_json(array('filters' => (object) $audit_view_query))) . '" data-pg-audit-failed="' . h(lang('Query failed.')) . '" data-pg-audit-loading="' . h(lang('Loading...')) . '">
                        <thead>
                            <tr>
                                <th>' . $audit_view_heading('date') . '</th>
                                <th>' . $audit_view_heading('who') . '</th>
                                <th>' . $audit_view_heading('action') . '</th>
                                <th>' . $audit_view_heading('record') . '</th>
                                <th>' . h(lang('Changes')) . '</th>
                                <th>' . $audit_view_heading('ip') . '</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <p class="small fg-secondary mb-12">' . h(lang(array('string' => 'A CSV export holds at most {var:1} lines, newest first, and is recorded in the trail.', 'vars' => array(pg_format_number($audit_view_limits['export'], 0))))) . '</p>
        </div>
    </div>
    <script>
        // The changed fields of a line are read when its fold is first opened
        // (audit_log_changes). The toggle event does not bubble, so it is
        // caught on the way down; rows drawn later are found the same way.
        document.addEventListener("toggle", function (event) {
            var fold = event.target;

            if (!fold || !fold.matches || !fold.matches("details[data-pg-audit-line]") || !fold.open || fold.hasAttribute("data-pg-audit-read")) {
                return;
            }

            fold.setAttribute("data-pg-audit-read", "1");

            var body = fold.querySelector(".pg-audit-changes-body");
            var table = fold.closest("table");
            var failed = (table && table.getAttribute("data-pg-audit-failed")) || "";

            body.textContent = (table && table.getAttribute("data-pg-audit-loading")) || "";
            body.setAttribute("aria-busy", "true");

            window.jQuery.ajax({
                contentType: "application/json",
                url: "api.php",
                type: "POST",
                dataType: "json",
                data: JSON.stringify({ action: "audit_log_changes", token: software_token, id: fold.getAttribute("data-pg-audit-line") })
            }).done(function (response) {
                body.removeAttribute("aria-busy");
                if (response && (response.status === "success")) {
                    body.innerHTML = response.html;
                } else {
                    body.textContent = (response && response.message) || failed;
                    fold.removeAttribute("data-pg-audit-read");
                }
            }).fail(function (xhr) {
                body.removeAttribute("aria-busy");
                body.textContent = (xhr.responseJSON && xhr.responseJSON.message) || failed;
                fold.removeAttribute("data-pg-audit-read");
            });
        }, true);
    </script>
</main>
' .
output_footer();
