<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The audit trail: who changed what, when and from where, with each changed
 * field before and after (audit_log, written by pg_audit()). Lines are
 * filtered by date, source, user, action, record type, record and request,
 * listed by the list table driver one batch at a time
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

// The picker posts its own date text; the filters read ISO. The site's
// order is read with any separator people type, the Turkish "01.10.2026"
// included. A date that cannot be read stays in its box with a warning and
// filters nothing, instead of vanishing while every line is listed.
$audit_view_input = $_GET;
$audit_view_bad_dates = array();

foreach (array('from', 'to') as $audit_view_key) {
    $audit_view_date = (isset($_GET[$audit_view_key]) && is_string($_GET[$audit_view_key])) ? trim($_GET[$audit_view_key]) : '';
    $audit_view_input[$audit_view_key] = ($audit_view_date !== '') ? pg_date_input_iso($audit_view_date) : '';

    if (($audit_view_date !== '') && ($audit_view_input[$audit_view_key] === '')) {
        $audit_view_bad_dates[$audit_view_key] = mb_substr($audit_view_date, 0, 40, 'UTF-8');
    }
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

// The list table driver prints the first lines (includes/fn/audit_list.php);
// the next ones come from its list_table_rows action as the list scrolls,
// sorted by the kept sort.
$audit_view_sort = pg_audit_list_sort();
$audit_view_kept = pg_audit_list_link_query($audit_view_query);
$audit_view_source = pg_audit_list_source($user, array('filters' => $audit_view_query));

if ($audit_view_source === null) {
    output_error(lang('Access denied'));
}

$audit_view_page = pg_list_table_page($audit_view_source, array(), null);

if ($audit_view_page === false) {
    output_error(lang('Query failed.'));
}

// The choices of the type and action filters: what the trail holds, plus the
// value already chosen, ordered by the name shown.
$audit_view_select = function ($name, $kind, $column) use ($audit_view_filters) {
    $words = (array) db_values("SELECT DISTINCT " . $column . " FROM audit_log WHERE " . $column . " <> ''");

    $options = '<option value="">' . h(lang('All')) . '</option>';

    foreach (pg_audit_view_filter_options($kind, $words, $audit_view_filters[$name]) as $word => $label) {
        $word = (string) $word;
        $options .= '<option value="' . h($word) . '"' . (($audit_view_filters[$name] === $word) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    return '<select class="form-control form-control-sm" id="audit_' . $name . '" name="' . $name . '">' . $options . '</select>';
};

// The loop has a name of its own: $audit_view_source holds the list source
// that pg_list_table_html() draws below, and a string in its place is a fatal
// TypeError (the whole screen answered HTTP 500).
$audit_view_sources = '<option value="">' . h(lang('All')) . '</option>';

foreach (pg_audit_sources() as $audit_view_source_name) {
    $audit_view_sources .= '<option value="' . h($audit_view_source_name) . '"' . (($audit_view_filters['source'] === $audit_view_source_name) ? ' selected' : '') . '>' . h(pg_audit_view_label('source', $audit_view_source_name)) . '</option>';
}

$audit_view_date_value = function ($key) use ($audit_view_filters, $audit_view_bad_dates) {
    if (isset($audit_view_bad_dates[$key])) {
        return $audit_view_bad_dates[$key];
    }

    return ($audit_view_filters[$key] !== '') ? prepare_form_data_for_output($audit_view_filters[$key], 'date', false) : '';
};

// A date box that could not be read: marked invalid, the warning under it.
$audit_view_date_field = function ($key, $label) use ($audit_view_date_value, $audit_view_bad_dates) {
    $bad = isset($audit_view_bad_dates[$key]);

    return '<label for="audit_' . $key . '" class="form-label small mb-1">' . h($label) . '</label>
                            <input type="text" class="form-control form-control-sm' . ($bad ? ' is-invalid' : '') . '" id="audit_' . $key . '" name="' . $key . '" value="' . h($audit_view_date_value($key)) . '" autocomplete="off" data-pg-date' . ($bad ? ' aria-invalid="true" aria-describedby="audit_' . $key . '_error"' : '') . '>'
        . ($bad ? '<div class="invalid-feedback d-block" id="audit_' . $key . '_error">' . h(lang('Enter a valid date.')) . '</div>' : '');
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

// The search box, Filter and Clear filters sit in the screen toolbar; the
// search box and Filter belong to the filter form below by their form
// attribute, so one submit carries the fields and the search together.
$audit_view_toolbar = pg_screen_toolbar(array(
    'primary'   => '<button type="submit" form="audit_view_filter" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-funnel me-1" aria-hidden="true"></i>' . h(lang('Filter')) . '</button>',
    'secondary' => ($audit_view_query || $audit_view_bad_dates) ? '<a href="view_audit_log.php" class="btn btn-sm btn-text theme-secondary">' . h(lang('Clear filters')) . '</a>' : '',
    'grow'      => $audit_view_narrowed,
    'search'    => '<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                        <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-ghost" form="audit_view_filter" name="q" value="' . h($audit_view_filters['q']) . '" maxlength="255" placeholder="' . h(lang('Record name')) . '" aria-label="' . h(lang('Record name')) . '">
                    </label>',
    'view'      => pg_list_sort_menu('view_audit_log.php', $audit_view_sort['sort_columns'], $audit_view_sort['sort_levels'], $audit_view_sort['sort_default'], $audit_view_kept),
    'overflow'  => '<a href="' . h(pg_audit_list_link($audit_view_query, array('export' => 'csv'))) . '" class="btn btn-sm btn-text theme-secondary"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>' . h(lang('Export CSV')) . '</a>',
)) . '
            <form action="view_audit_log.php" method="get" id="audit_view_filter" class="disable_shortcut card mb-7">
                <div class="card-body">
                    ' . $audit_view_hidden . '
                    <div class="row g-3 align-items-end">
                        <div class="col-6 md:col-4 xl:col-2">
                            ' . $audit_view_date_field('from', lang('Start')) . '
                        </div>
                        <div class="col-6 md:col-4 xl:col-2">
                            ' . $audit_view_date_field('to', lang('End')) . '
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
                </div>
            </form>
            ' . get_date_picker_format() . pg_fields_script();

echo
pg_page_shell(array(
        'title'               => lang('Audit trail'),
        'extra classes'       => 'setting pg-measure-list',
        'icon'                => 'setting',
        'heading'             => lang('Audit trail'),
        'heading_description' => lang('Who changed what in the panel, through the API or in a scheduled job, with each changed field before and after. Secrets are kept as changed, never with their value.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    ' . $audit_view_toolbar . '
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-end mb-3">
                <label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
                    <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-ghost" data-pg-list-search="view_audit_log" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">
                </label>
            </div>
            <div class="mb-5" data-pg-audit-failed="' . h(lang('Query failed.')) . '" data-pg-audit-loading="' . h(lang('Loading...')) . '">
                ' . pg_list_table_html($audit_view_source, $audit_view_page, lang('Audit trail')) . '
            </div>
            <p class="small fg-secondary mb-12">' . h(lang(array('string' => 'A CSV export holds at most {var:1} lines, newest first, and is recorded in the trail.', 'vars' => array(pg_format_number($audit_view_limits['export'], 0))))) . '</p>
        </div>
    </div>
    <style>@layer custom {
        /* A long record name wraps anywhere and the cell is capped, so the
           IP column stays in view. */
        .pg-audit-record { white-space: normal; overflow-wrap: anywhere; max-width: 24rem; }
    }</style>
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
            var texts = fold.closest("[data-pg-audit-failed]");
            var failed = (texts && texts.getAttribute("data-pg-audit-failed")) || "";

            body.textContent = (texts && texts.getAttribute("data-pg-audit-loading")) || "";
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
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') .
output_footer();
