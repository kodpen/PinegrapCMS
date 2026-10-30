<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * 404 log: the addresses visitors asked for that no page, file or short link
 * answers (includes/fn/not_found_log.php), most asked first, each with a way
 * to make it a redirect: the short link screen opened with the address as
 * the name and the answer 301 (pg_not_found_log_redirect_url()). A redirect
 * is a short link, so this screen sits beside the short link list and has
 * its gate: managers and up.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');
$liveform = new liveform('view_not_found_log');

$ready = pg_not_found_log_ready();

// ---- Filters --------------------------------------------------------------

// Each filter is one value; one sent as a list counts as not given.
$who_options = array(
    ''         => lang('Everyone'),
    'visitors' => lang('Visitors'),
    'crawlers' => lang('Search engines'),
);

$who = (isset($_GET['who']) && is_string($_GET['who']) && isset($who_options[$_GET['who']])) ? $_GET['who'] : '';
$search = (isset($_GET['q']) && is_string($_GET['q'])) ? mb_substr(trim($_GET['q']), 0, PG_NOT_FOUND_LOG_PATH_MAX, 'UTF-8') : '';

$kept = array_filter(array('who' => $who, 'q' => $search), 'strlen');

// The log as filtered now: a removal posts to it and lands on it, and the
// short link screens opened from a row come back to it (send_to).
$log_url = PATH . SOFTWARE_DIRECTORY . '/view_not_found_log.php' . ($kept ? '?' . http_build_query($kept) : '');

// ---- Actions ---------------------------------------------------------------

// One address out of the log, or all of them: POST with the token, then back
// to the list, so a stray GET cannot empty it.
if (isset($_POST['submit_delete']) || isset($_POST['submit_clear'])) {
    validate_token_field();

    if ($ready && isset($_POST['submit_clear'])) {
        db("DELETE FROM not_found_log");
        log_activity(lang('cleared the 404 log'), $_SESSION['sessionusername']);
        $liveform->add_notice(lang('The 404 log has been cleared.'));
    } elseif ($ready) {
        $delete_id = (isset($_POST['id']) && is_scalar($_POST['id'])) ? (int) $_POST['id'] : 0;
        $delete_path = ($delete_id > 0) ? db_value("SELECT path FROM not_found_log WHERE id = '" . $delete_id . "'") : null;

        if ($delete_path !== null) {
            db("DELETE FROM not_found_log WHERE id = '" . $delete_id . "'");
            log_activity(lang(array('string' => 'address ({var:1}) was removed from the 404 log', 'vars' => array((string) $delete_path))), $_SESSION['sessionusername']);
            $liveform->add_notice(lang('The address has been removed from the 404 log.'));
        }
    }

    go($log_url);
}

$shell = array(
    'title'               => lang('404 Log'),
    'extra classes'       => 'page',
    'icon'                => 'page',
    'heading'             => lang('404 Log'),
    'heading_description' => lang('Addresses visitors asked for that no page, file or short link answers, kept for 90 days after they were last asked for. Bots are not counted; search engine crawlers the firewall verified are counted on their own.'),
    'breadcrumb'          => array(
        array('label' => lang('Short Links'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_short_links.php'),
        array('label' => lang('404 Log')),
    ),
);

// The short link list and this one, as one view switcher.
$view_switcher =
    '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('View')) . '">
        <a class="btn btn-text theme-secondary" href="view_short_links.php">' . h(lang('Short Links')) . '</a>
        <a class="btn btn-text theme-secondary active" href="view_not_found_log.php" aria-current="page">' . h(lang('404 Log')) . '</a>
    </div>';

if (!$ready) {
    echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    <nav id="button_bar" class="pg-toolbar my-3" aria-label="' . h(lang('Button Bar')) . '">
        <div class="pg-toolbar-grow"></div>
        ' . $view_switcher . '
    </nav>
    <div class="alert theme-warning my-7">
        <i class="bi bi-exclamation-triangle me-3" aria-hidden="true"></i>' . h(lang('The 404 log table does not exist yet. Please run the software upgrade to create it.')) . '
    </div>
</main>' . output_footer();

    $liveform->remove_form();
    exit();
}

// ---- Sort ------------------------------------------------------------------

$sort_columns = array(
    'path'       => lang('Address'),
    'hits'       => lang('Requests'),
    'crawlers'   => lang('Search engines'),
    'first_seen' => lang('First asked for'),
    'last_seen'  => lang('Last asked for'),
);

$sort_default = array('sort' => 'hits', 'order' => 'desc');
$list_state = pg_list_state('view_not_found_log', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// Only the codes pg_list_state() let through reach pg_list_order_by(); the id
// keeps rows with equal values in one order.
$order_by = pg_list_order_by($sort_levels, array(
    'path'       => 'path',
    'hits'       => 'hit_count',
    'crawlers'   => 'crawler_hits',
    'first_seen' => 'first_seen',
    'last_seen'  => 'last_seen',
)) . ', id DESC';

$kept_extras = $kept ? h('&' . http_build_query($kept)) : '';

$where = pg_not_found_log_where(array('since' => pg_not_found_log_since(), 'who' => $who, 'search' => $search));

// One row per address, so the counts are sums and the rows are counted.
$summary = db_item(
    "SELECT
        COUNT(*) AS addresses,
        COALESCE(SUM(hit_count), 0) AS hits,
        COALESCE(SUM(crawler_hits), 0) AS crawler_hits
    FROM not_found_log
    WHERE " . $where);

$row_limit = 1000;

$rows = db_items(
    "SELECT id, path, referrer, hit_count, crawler_hits, first_seen, last_seen
    FROM not_found_log
    WHERE " . $where . "
    ORDER BY " . $order_by . "
    LIMIT " . $row_limit);

$answers = pg_not_found_log_answers(array_column($rows, 'path'));

// ---- Rows ------------------------------------------------------------------

$output_rows = '';

foreach ($rows as $row) {
    $path = (string) $row['path'];
    $answer = isset($answers[$path]) ? $answers[$path] : null;
    $address = OUTPUT_PATH . h($path);

    $output_state = '';
    $output_create = '';

    if ($answer === null) {
        $output_create = '<a class="m-1 btn-data-control btn btn-outline theme-primary border-2" href="' . h(pg_not_found_log_redirect_url($path, $log_url)) . '" title="' . h(lang('Create redirect')) . '" aria-label="' . h(lang('Create redirect')) . '"><i class="bi bi-signpost-split" aria-hidden="true"></i></a>';
    } elseif (($answer['state'] === 'redirect') || ($answer['state'] === 'pattern')) {
        // A short link answers the address now (a * pattern only once no page
        // does): no new 404 comes, and an exact link would take it from the
        // pattern.
        $output_state = ' <span class="badge rounded-pill badge-subtle theme-secondary border">' . h(($answer['state'] === 'pattern') ? lang('Wildcard') : lang('Short link')) . '</span>';
        $output_create = '<a class="m-1 btn-data-control btn btn-outline theme-primary border-2" href="edit_short_link.php?id=' . (int) $answer['id'] . h('&send_to=' . urlencode($log_url)) . '" title="' . h(lang('Edit')) . '" aria-label="' . h(lang('Edit')) . '"><i class="bi bi-pencil" aria-hidden="true"></i></a>';
    } elseif ($answer['state'] === 'binned') {
        $output_state = '<div class="small fg-secondary">' . h(lang('A short link in the Recycle Bin has this name: restore it, or delete it for good, in the File Manager.')) . '</div>';
    } else {
        $output_state = ' <span class="badge rounded-pill badge-subtle theme-secondary border">' . h(($answer['state'] === 'page') ? lang('A page answers now') : lang('A file answers now')) . '</span>';
    }

    $hits = (int) $row['hit_count'];
    $crawler_hits = (int) $row['crawler_hits'];
    $referrer = (string) $row['referrer'];

    $output_rows .=
        '<tr>
            <td class="align-middle text-start action-buttons text-nowrap">
                ' . $output_create . '
                <a class="m-1 btn-data-control btn btn-outline theme-secondary border-2" href="' . OUTPUT_PATH . h(encode_url_path($path)) . '" target="_blank" rel="noopener" title="' . h(lang('Visit')) . '" aria-label="' . h(lang('Visit')) . '"><i class="bi bi-link" aria-hidden="true"></i></a>
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 no-submit pg-404-delete" data-id="' . (int) $row['id'] . '" data-path="' . h($path) . '" title="' . h(lang('Delete')) . '" aria-label="' . h(lang('Delete')) . '"><i class="bi bi-trash" aria-hidden="true"></i></button>
            </td>
            <td class="align-middle chart_label"><span class="font-monospace text-break">' . $address . '</span>' . $output_state . '</td>
            <td class="align-middle" data-order="' . $hits . '">' . pg_format_number($hits, 0) . '</td>
            <td class="align-middle" data-order="' . $crawler_hits . '">' . (($crawler_hits > 0) ? pg_format_number($crawler_hits, 0) : '<span class="fg-secondary">-</span>') . '</td>
            <td class="align-middle small text-break">' . (($referrer !== '') ? h(mb_substr($referrer, 0, 90, 'UTF-8')) . ((mb_strlen($referrer, 'UTF-8') > 90) ? '…' : '') : '<span class="fg-secondary">-</span>') . '</td>
            <td class="align-middle text-nowrap" data-order="' . (int) $row['first_seen'] . '">' . get_relative_time(array('timestamp' => (int) $row['first_seen'])) . '</td>
            <td class="align-middle text-nowrap" data-order="' . (int) $row['last_seen'] . '">' . get_relative_time(array('timestamp' => (int) $row['last_seen'])) . '</td>
        </tr>';
}

$addresses = (int) $summary['addresses'];

$output_summary = h(lang(array(
    'string' => '{var:1} address(es), {var:2} request(s), {var:3} of them from search engines.',
    'vars'   => array(pg_format_number($addresses, 0), pg_format_number((int) $summary['hits'], 0), pg_format_number((int) $summary['crawler_hits'], 0)),
)));

if ($addresses > $row_limit) {
    $output_summary .= ' ' . h(lang(array('string' => 'The first {var:1} are shown; search to find the others.', 'vars' => array(pg_format_number($row_limit, 0)))));
}

// ---- Screen ----------------------------------------------------------------

$output_table = ($output_rows === '')
    ? '<p class="fg-secondary m-7">' . h(($search !== '' || $who !== '') ? lang('No address in the log matches.') : lang('No missing addresses were recorded in the last 90 days.')) . '</p>'
    : '<table class="chart table-hover table" style="width:100%;display:none">
        <thead>
            <tr>
                <th class="noVis">' . h(lang('Action')) . '</th>
                <th>' . get_column_heading(lang('Address'), $sort_levels, '', $kept_extras, 'path') . '</th>
                <th>' . get_column_heading(lang('Requests'), $sort_levels, '', $kept_extras, 'hits') . '</th>
                <th>' . get_column_heading(lang('Search engines'), $sort_levels, '', $kept_extras, 'crawlers') . '</th>
                <th>' . h(lang('Referrer')) . '</th>
                <th>' . get_column_heading(lang('First asked for'), $sort_levels, '', $kept_extras, 'first_seen') . '</th>
                <th>' . get_column_heading(lang('Last asked for'), $sort_levels, '', $kept_extras, 'last_seen') . '</th>
            </tr>
        </thead>
        <tbody>
            ' . $output_rows . '
        </tbody>
    </table>';

echo pg_page_shell($shell) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <nav id="button_bar" class="pg-toolbar my-3" aria-label="' . h(lang('Button Bar')) . '">
                <div class="pg-toolbar-grow"></div>
                <form method="get" action="view_not_found_log.php" class="pg-toolbar-search disable_shortcut" role="search">
                    ' . (($who !== '') ? '<input type="hidden" name="who" value="' . h($who) . '">' : '') . '
                    <label class="form-control form-control-sm form-adorn rounded-pill">
                        <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-ghost" id="pg_404_search" name="q" value="' . h($search) . '" maxlength="' . (int) PG_NOT_FOUND_LOG_PATH_MAX . '" placeholder="' . h(lang('Search addresses')) . '" aria-label="' . h(lang('Search')) . '">
                    </label>
                </form>
                ' . pg_filter_select('who', $who_options, $who, array('keep' => array('q'), 'label' => lang('Who asked'), 'icon' => 'person', 'action' => 'view_not_found_log.php')) . '
                ' . pg_list_sort_menu('view_not_found_log.php', $sort_columns, $sort_levels, $sort_default, $kept) . '
                ' . $view_switcher . '
                <div class="pg-menu-wrap">
                    <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="menu" data-bs-placement="bottom-end" aria-expanded="false" title="' . h(lang('More')) . '" aria-label="' . h(lang('More')) . '"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                    <ul class="menu pg-menu-end">
                        <li><button type="button" class="menu-item pg-link-body-emphasis no-submit pg-404-clear"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Clear Log')) . '</button></li>
                    </ul>
                </div>
            </nav>
            <p class="small fg-secondary mb-0">' . $output_summary . '</p>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    ' . $output_table . '
                </div>
            </div>
        </div>
    </div>
    <form id="pg-404-delete-form" method="post" action="' . h($log_url) . '" class="d-none">
        ' . get_token_field() . '
        <input type="hidden" name="id" value="">
        <input type="hidden" name="submit_delete" value="1">
    </form>
    <form id="pg-404-clear-form" method="post" action="view_not_found_log.php" class="d-none">
        ' . get_token_field() . '
        <input type="hidden" name="submit_clear" value="1">
    </form>
    <script>
    // Delete one address or clear the log: confirmed, then the hidden form
    // outside the table is posted. Delegated from document, since the table
    // is a DataTable whose rows are detached and drawn again.
    (function () {
        function ask(question, title, done) {
            if (typeof window.pgConfirm !== "function") {
                if (window.confirm(question)) done();
                return;
            }
            window.pgConfirm({ title: title, message: question, confirmText: ' . json_encode(lang('Delete')) . ', cancelText: ' . json_encode(lang('Cancel')) . ', variant: "danger" })
                .then(function (ok) { if (ok) done(); });
        }

        document.addEventListener("click", function (ev) {
            var target = ev.target && ev.target.closest ? ev.target : null;
            if (!target) return;

            var row = target.closest(".pg-404-delete");
            if (row) {
                ev.preventDefault();
                var form = document.getElementById("pg-404-delete-form");
                ask(' . json_encode(lang('Remove this address from the 404 log?')) . ' + " " + (row.getAttribute("data-path") || ""), ' . json_encode(lang('Delete')) . ', function () {
                    form.elements["id"].value = row.getAttribute("data-id") || "";
                    form.submit();
                });
                return;
            }

            if (target.closest(".pg-404-clear")) {
                ev.preventDefault();
                ask(' . json_encode(lang('Clear the whole 404 log?')) . ', ' . json_encode(lang('Clear Log')) . ', function () {
                    document.getElementById("pg-404-clear-form").submit();
                });
            }
        });
    })();
    </script>
</main>
' . output_footer();

$liveform->remove_form();
