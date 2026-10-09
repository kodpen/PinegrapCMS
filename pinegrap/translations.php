<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Translations: the pages of the visual designs in the site's other
 * languages - what is translated, what is pending, what was written by a
 * machine and still has to be read - and the editor that reads and corrects
 * them text by text.
 *
 * The left column lists the pages with their coverage; the right column is
 * the editor for the selected page (or for every text when no page is
 * selected). "Update translations" extracts the texts and sends the pending
 * ones to the language's engine; "Clear translations" deletes the ones of the
 * selection so they are sent again. The browser engine runs here, in this
 * screen's script (assets/js/translations.js), the server engines in
 * translations_action.php and the scheduled job.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require('init.php');

$user = validate_user();
validate_area_access($user, 'manager');

include_once('liveform.class.php');

$liveform = new liveform('translations');

if (!pg_tr_ready()) {
    output_error(lang('These settings arrive with the 2026.4.6 upgrade, which this site has not run yet.'));
}

pg_tr_load();

$source = pg_tr_source_language();
$targets = array();

foreach (pg_tr_languages() as $code => $row) {
    if ($code !== $source) {
        $targets[$code] = $row;
    }
}

$language = isset($_GET['language']) ? trim((string) $_GET['language']) : '';

if (!isset($targets[$language])) {
    $language = $targets ? (string) key($targets) : '';
}

$page_id = isset($_GET['page_id']) ? (int) $_GET['page_id'] : 0;

// A group of texts that are not on a page: the catalog, the forms, the
// cookie window. A group and a page are not selected together. The cookie
// window is translated here only for a language with no language file of
// its own: one that has a file speaks it (pg_tr_ui_text()). For the others
// the group is brought in line with the window before it is counted, so it
// lists the window's texts and nothing else (pg_tr_ui_sync()).
$owner_groups = pg_tr_owner_groups();

if (($language !== '') && pg_tr_ui_has_file($language)) {
    unset($owner_groups['ui']);
} elseif ($language !== '') {
    pg_tr_extract_group('ui');
}

$group = isset($_GET['group']) ? (string) $_GET['group'] : '';

if (!isset($owner_groups[$group])) {
    $group = '';
}

if ($group !== '') {
    $page_id = 0;
}
$filter = isset($_GET['filter']) ? (string) $_GET['filter'] : 'all';

if (!in_array($filter, array('all', 'pending', 'machine', 'reviewed', 'suspicious'), true)) {
    $filter = 'all';
}

$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_number = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
$per_page = 100;

$self = 'translations.php';
$url = function ($changes = array()) use ($self, $language, $page_id, $group, $filter, $search) {
    $params = array_merge(array('language' => $language, 'page_id' => $page_id, 'group' => $group, 'filter' => $filter, 'q' => $search), $changes);
    $query = array();

    foreach ($params as $key => $value) {
        if (($value === '') || ($value === 0) || ($value === '0') || ($value === null)) {
            continue;
        }

        if (($key === 'filter') && ($value === 'all')) {
            continue;
        }

        $query[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
    }

    return $self . ($query ? '?' . implode('&', $query) : '');
};

$pages = pg_tr_translatable_pages();
$stats = ($language !== '') ? pg_tr_page_stats($language) : array();
$site_stats = ($language !== '') ? pg_tr_language_stats($language) : array('total' => 0, 'translated' => 0, 'reviewed' => 0, 'machine' => 0, 'pending' => 0);
$language_row = ($language !== '') ? $targets[$language] : null;
$engines = pg_tr_engines();
$engine_key = $language_row ? (string) $language_row['engine'] : 'manual';
$engine = isset($engines[$engine_key]) ? $engines[$engine_key] : $engines['manual'];

// The engine select in the toolbar: the language's engine, changed here as
// on the settings card. Pinegrap AI and Claude are listed only while the
// Workspace is on (the one set now is kept in the list either way).
$output_engine_options = '';

foreach (pg_tr_engine_options(array($engine_key)) as $option_key => $option_label) {
    $option_spec = isset($engines[$option_key]) ? $engines[$option_key] : null;
    $output_engine_options .= '<option value="' . h($option_key) . '"' . (($option_key === $engine_key) ? ' selected="selected"' : '')
        . '>' . h($option_label) . (($option_spec && !$option_spec['ready']) ? ' (' . h(lang('not ready')) . ')' : '') . '</option>';
}

$selected_page = null;

foreach ($pages as $row) {
    if ((int) $row['page_id'] === $page_id) {
        $selected_page = $row;
    }
}

if ($selected_page === null) {
    $page_id = 0;
}

// ── the texts ──

$rows = array();
$total_rows = 0;
$total_pages = 1;

if ($language !== '') {

    $owner_where = ($page_id > 0) ? pg_tr_owner_where(pg_tr_page_owners($page_id)) : (($group !== '') ? pg_tr_owner_where(pg_tr_scope_owners('group:' . $group)) : pg_tr_owner_where(array()));
    $where = array('u.string_id > 0');

    if ($owner_where !== '') {
        $where[] = '(' . $owner_where . ')';
    }

    switch ($filter) {
        case 'pending':
            $where[] = 't.id IS NULL';
            break;
        case 'machine':
            $where[] = "t.status = 'machine'";
            break;
        case 'reviewed':
            $where[] = "t.status = 'reviewed'";
            break;
        case 'suspicious':
            $where[] = 't.suspicious = 1';
            break;
    }

    if ($search !== '') {
        $like = "'%" . e(escape_like($search)) . "%'";
        $where[] = "(s.source_text LIKE $like OR t.text LIKE $like)";
    }

    $from = "FROM translation_uses u
             INNER JOIN translation_strings s ON s.id = u.string_id
             LEFT JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
             WHERE " . implode(' AND ', $where);

    $total_rows = (int) db_value("SELECT COUNT(DISTINCT s.id) $from");
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page_number = min($page_number, $total_pages);

    $ordered = db_items("SELECT s.id, MIN(u.position) AS pos $from GROUP BY s.id ORDER BY pos, s.id LIMIT " . (($page_number - 1) * $per_page) . ", $per_page");
    $ids = array();

    if (is_array($ordered)) {
        foreach ($ordered as $row) {
            $ids[] = (int) $row['id'];
        }
    }

    if ($ids) {
        $details = db_items("SELECT s.id, s.hash, s.source_text, s.format, s.kind, s.chars, t.text, t.engine, t.status, t.suspicious, t.updated_at
                             FROM translation_strings s
                             LEFT JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
                             WHERE s.id IN (" . implode(',', $ids) . ")");
        $by_id = array();

        if (is_array($details)) {
            foreach ($details as $row) {
                $by_id[(int) $row['id']] = $row;
            }
        }

        foreach ($ids as $id) {
            if (isset($by_id[$id])) {
                $rows[] = $by_id[$id];
            }
        }
    }
}

// ── the glossary: the terms for this language, and the ones for every language ──

$glossary = ($language !== '') ? pg_tr_glossary($language) : array();

// ── the recent jobs ──

$jobs = ($language !== '') ? db_items("SELECT * FROM translation_jobs WHERE language = '" . e($language) . "' ORDER BY id DESC LIMIT 8") : array();
$jobs = is_array($jobs) ? $jobs : array();
$open_job = null;

foreach ($jobs as $job) {
    if (in_array($job['status'], array('queued', 'sent', 'running'), true)) {
        $open_job = $job;
        break;
    }
}

// ── output ──

$engine_labels = array();

foreach ($engines as $key => $spec) {
    $engine_labels[$key] = $spec['label'];
}

$status_labels = array(
    'queued'    => lang('Queued'),
    'sent'      => lang('Sent'),
    'running'   => lang('Running'),
    'done'      => lang('Done'),
    'failed'    => lang('Failed'),
    'cancelled' => lang('Cancelled'),
);

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();
$liveform->remove_form();

$token = (string) ($_SESSION['software']['token'] ?? '');

// Language switcher: the primary control of the toolbar.
$output_language_menu = '';

foreach ($targets as $code => $row) {
    $output_language_menu .= '<li><a class="dropdown-item' . (($code === $language) ? ' active' : '') . '" href="' . h($url(array('language' => $code, 'page_id' => 0, 'p' => 0))) . '">'
        . '<span class="badge text-bg-secondary font-monospace me-2">' . h($code) . '</span>' . h(pg_tr_language_label($code)) . '</a></li>';
}

$output_language_menu .= '<li><hr class="dropdown-divider"></li>'
    . '<li><a class="dropdown-item" href="' . h(pg_settings_link('languages', 'pgset-languages')) . '"><i class="bi bi-gear me-2" aria-hidden="true"></i>' . lang('Languages and Translation') . '</a></li>';

// Filter chips.
$output_chips = '';

foreach (array(
    'all'        => array(lang('All'), $site_stats['total']),
    'pending'    => array(lang('Pending'), $site_stats['pending']),
    'machine'    => array(lang('Machine'), $site_stats['machine']),
    'reviewed'   => array(lang('Reviewed'), $site_stats['reviewed']),
    'suspicious' => array(lang('Suspicious'), null),
) as $key => $chip) {
    $output_chips .= '<a class="pg-chip' . (($key === $filter) ? ' active' : '') . '" href="' . h($url(array('filter' => $key, 'p' => 0))) . '">' . h($chip[0])
        . (($chip[1] !== null) ? ' <span class="opacity-75">' . (int) $chip[1] . '</span>' : '') . '</a>';
}

// The page list.
$output_pages = '';
$output_pages .= '<a class="list-group-item list-group-item-action d-flex align-items-center gap-2' . (($page_id === 0) ? ' active' : '') . '" href="' . h($url(array('page_id' => 0, 'p' => 0))) . '">'
    . '<i class="bi bi-collection" aria-hidden="true"></i><span class="flex-grow-1 text-truncate">' . lang('All pages') . '</span>'
    . '<span class="small opacity-75">' . (int) $site_stats['translated'] . '/' . (int) $site_stats['total'] . '</span></a>';

foreach ($pages as $row) {
    $pid = (int) $row['page_id'];
    $stat = isset($stats[$pid]) ? $stats[$pid] : array('total' => 0, 'translated' => 0, 'reviewed' => 0, 'seen_at' => 0);
    $coverage = ($stat['total'] > 0) ? (int) round(($stat['translated'] / $stat['total']) * 100) : 0;
    $bar_class = ($stat['total'] === 0) ? 'bg-secondary' : (($stat['reviewed'] === $stat['total']) ? 'bg-success' : (($coverage === 100) ? 'bg-info' : 'bg-warning'));
    $title = ($row['page_title'] !== '') ? $row['page_title'] : $row['page_name'];

    $output_pages .= '<a class="list-group-item list-group-item-action' . (($pid === $page_id) ? ' active' : '') . '" href="' . h($url(array('page_id' => $pid, 'p' => 0))) . '">'
        . '<div class="d-flex align-items-center gap-2">'
        . '<span class="flex-grow-1 text-truncate" title="' . h($title) . '">' . (($row['page_home'] === 'yes') ? '<i class="bi bi-house-door me-1" aria-hidden="true"></i>' : '') . h($row['page_name']) . '</span>'
        . '<span class="small opacity-75">' . (($stat['total'] > 0) ? (int) $stat['translated'] . '/' . (int) $stat['total'] : lang('Not scanned')) . '</span>'
        . '</div>'
        . '<div class="progress mt-1" style="height: 3px;"><div class="progress-bar ' . $bar_class . '" style="width: ' . $coverage . '%"></div></div>'
        . '</a>';
}

if (!$pages) {
    $output_pages .= '<div class="list-group-item text-body-secondary small">' . lang('No page of a visual design yet. Pages built in the Visual Page Editor can be translated.') . '</div>';
}

// The texts that are not on a page: the catalog, the forms and the interface
// wording, each with how far it is translated.
$output_groups = '';

if ($language !== '') {
    foreach ($owner_groups as $group_key => $group_spec) {
        $group_where = pg_tr_owner_where(pg_tr_scope_owners('group:' . $group_key));
        $group_stat = db_item("SELECT COUNT(DISTINCT u.string_id) AS total, COUNT(DISTINCT t.string_id) AS translated
                               FROM translation_uses u
                               LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = '" . e($language) . "'
                               WHERE u.string_id > 0 AND ($group_where)");
        $group_total = is_array($group_stat) ? (int) $group_stat['total'] : 0;
        $group_done = is_array($group_stat) ? (int) $group_stat['translated'] : 0;

        $output_groups .= '<a class="list-group-item list-group-item-action d-flex align-items-center gap-2' . (($group_key === $group) ? ' active' : '') . '" href="' . h($url(array('group' => $group_key, 'page_id' => 0, 'p' => 0))) . '">'
            . '<i class="bi ' . h($group_spec['icon']) . '" aria-hidden="true"></i><span class="flex-grow-1 text-truncate">' . h($group_spec['label']) . '</span>'
            . '<span class="small opacity-75">' . (($group_total > 0) ? $group_done . '/' . $group_total : (($group_key === 'ui') ? lang('None yet') : lang('Not scanned'))) . '</span></a>';
    }
}

// The editor rows.
$output_rows = '';

foreach ($rows as $row) {
    $has = ($row['text'] !== null);
    $state = $has ? (string) $row['status'] : 'pending';
    $state_label = $has ? (($state === 'reviewed') ? lang('Reviewed') : lang('Machine')) : lang('Pending');
    $state_class = $has ? (($state === 'reviewed') ? 'text-bg-success' : 'text-bg-warning') : 'text-bg-secondary';
    $engine_label = ($has && isset($engine_labels[$row['engine']])) ? $engine_labels[$row['engine']] : ($has ? (string) $row['engine'] : '');

    $output_rows .= '
            <div class="pg-tr-row border-bottom py-3" data-string-id="' . (int) $row['id'] . '" data-format="' . h($row['format']) . '">
                <div class="row g-3">
                    <div class="col-12 col-xl-5">
                        <div class="pg-tr-source' . (($row['format'] === 'inline') ? ' pg-tr-inline' : '') . '">' . h($row['source_text']) . '</div>
                        <div class="mt-1 small text-body-secondary">'
                            . (($row['kind'] === 'seo') ? '<span class="badge text-bg-light border me-1">SEO</span>' : '')
                            . (($row['format'] === 'inline') ? '<span class="badge text-bg-light border me-1">HTML</span>' : '')
                            . '<span class="font-monospace opacity-50">' . h(substr($row['hash'], 0, 8)) . '</span>'
                        . '</div>
                    </div>
                    <div class="col-12 col-xl-5">
                        <textarea class="form-control pg-tr-target" rows="2" lang="' . h($language) . '" placeholder="' . h(lang('Not translated yet: the source text is shown on the page.')) . '">' . h($has ? (string) $row['text'] : '') . '</textarea>
                    </div>
                    <div class="col-12 col-xl-2">
                        <div class="d-flex flex-xl-column align-items-start gap-2">
                            <div class="pg-tr-state">
                                <span class="badge ' . $state_class . ' pg-tr-state-badge">' . h($state_label) . '</span>
                                <span class="small text-body-secondary pg-tr-engine">' . h($engine_label) . '</span>'
                                . (($has && ((int) $row['suspicious'] === 1)) ? '<span class="badge text-bg-danger pg-tr-suspicious" title="' . h(lang('The length of the translation is out of proportion to the source text.')) . '"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>' : '<span class="badge text-bg-danger pg-tr-suspicious d-none"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>') . '
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary pg-tr-save" title="' . h(lang('Save')) . '"><i class="bi bi-check-lg" aria-hidden="true"></i> ' . lang('Save') . '</button>
                                <button type="button" class="btn btn-outline-secondary pg-tr-review' . ($has ? '' : ' d-none') . '" title="' . h(lang('Mark as reviewed')) . '" data-state="' . h($state) . '"><i class="bi ' . (($state === 'reviewed') ? 'bi-eye-slash' : 'bi-eye') . '" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';
}

if (($language !== '') && !$rows) {
    $output_rows = '<div class="py-5 text-center text-body-secondary">'
        . (($site_stats['total'] === 0)
            ? lang('No text has been scanned yet. Press "Update translations": the pages are read and their texts are listed here.')
            : lang('No text matches the filter.'))
        . '</div>';
}

// Pagination.
$output_pagination = '';

if ($total_pages > 1) {
    $output_pagination .= '<nav aria-label="' . h(lang('Pages')) . '"><ul class="pagination pagination-sm mb-0">';

    for ($i = 1; $i <= $total_pages; $i++) {
        if (($total_pages > 12) && ($i > 2) && ($i < $total_pages - 1) && (abs($i - $page_number) > 2)) {
            if (($i === 3) || ($i === $total_pages - 2)) {
                $output_pagination .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }

            continue;
        }

        $output_pagination .= '<li class="page-item' . (($i === $page_number) ? ' active' : '') . '"><a class="page-link" href="' . h($url(array('p' => $i))) . '">' . $i . '</a></li>';
    }

    $output_pagination .= '</ul></nav>';
}

// Recent jobs.
$output_jobs = '';

foreach ($jobs as $job) {
    $output_jobs .= '<div class="d-flex align-items-center gap-2 py-2 border-top small" data-job-id="' . (int) $job['id'] . '">'
        . '<span class="badge ' . (in_array($job['status'], array('done'), true) ? 'text-bg-success' : (in_array($job['status'], array('failed', 'cancelled'), true) ? 'text-bg-danger' : 'text-bg-warning')) . '">' . h(isset($status_labels[$job['status']]) ? $status_labels[$job['status']] : $job['status']) . '</span>'
        . '<span class="flex-grow-1 text-truncate">' . h(isset($engine_labels[$job['engine']]) ? $engine_labels[$job['engine']] : $job['engine']) . ' · ' . h(($job['scope'] === 'all') ? lang('All pages') : lang('One page')) . '</span>'
        . '<span class="text-body-secondary">' . (int) $job['done_count'] . '/' . (int) $job['total'] . (((int) $job['failed_count'] > 0) ? ' <span class="text-danger">(' . (int) $job['failed_count'] . ')</span>' : '') . '</span>'
        . '<span class="text-body-secondary">' . get_relative_time(array('timestamp' => (int) $job['created_at'])) . '</span>'
        . (($job['error'] !== null && $job['error'] !== '') ? '<i class="bi bi-exclamation-circle text-danger" title="' . h($job['error']) . '" aria-hidden="true"></i>' : '')
        . '</div>';
}

if ($output_jobs === '') {
    $output_jobs = '<div class="small text-body-secondary py-2">' . lang('No update has been run for this language yet.') . '</div>';
}

// The glossary rows. Each carries its values as data attributes, so the
// edit button fills the modal without a round trip.
$output_glossary = '';

foreach ($glossary as $term) {
    $for_all = ((string) $term['language'] === '');

    $output_glossary .= '<div class="d-flex align-items-start gap-2 py-2 border-top small pg-tr-term"'
        . ' data-id="' . (int) $term['id'] . '" data-term="' . h($term['term']) . '" data-translation="' . h($term['translation']) . '"'
        . ' data-keep="' . (int) $term['keep'] . '" data-case="' . (int) $term['case_sensitive'] . '" data-note="' . h($term['note']) . '" data-all="' . ($for_all ? 1 : 0) . '">'
        . '<div class="flex-grow-1 text-break">'
        . '<span class="fw-semibold">' . h($term['term']) . '</span>'
        . (((int) $term['keep'] === 1)
            ? ' <span class="badge text-bg-secondary fw-normal">' . lang('keep as written') . '</span>'
            : ' <span class="text-body-secondary">→</span> ' . h($term['translation']))
        . ($for_all ? ' <span class="badge text-bg-light fw-normal">' . lang('all languages') . '</span>' : '')
        . (((int) $term['case_sensitive'] === 1) ? ' <span class="badge text-bg-light fw-normal font-monospace">Aa</span>' : '')
        . (($term['note'] !== '') ? '<div class="text-body-secondary">' . h($term['note']) . '</div>' : '')
        . '</div>'
        . '<button type="button" class="btn btn-sm btn-ghost btn-icon pg-tr-term-edit" data-bs-toggle="modal" data-bs-target="#pg_tr_glossary_modal" aria-label="' . h(lang('Edit')) . '"><i class="bi bi-pencil" aria-hidden="true"></i></button>'
        . '<form method="post" action="translations_action.php" class="d-inline" onsubmit="return confirm(' . h(json_encode(lang('Remove this term from the glossary?'))) . ');">'
        . '<input type="hidden" name="action" value="glossary_delete"/>'
        . '<input type="hidden" name="language" value="' . h($language) . '"/>'
        . '<input type="hidden" name="id" value="' . (int) $term['id'] . '"/>'
        . get_token_field()
        . '<button type="submit" class="btn btn-sm btn-ghost btn-icon text-danger" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
        . '</form>'
        . '</div>';
}

if ($output_glossary === '') {
    $output_glossary = '<div class="small text-body-secondary py-2">' . lang('No term yet. A term with the translation to use, or a name to keep as written: the engines follow the glossary on every text they are sent.') . '</div>';
}

$scope = ($page_id > 0) ? 'page:' . $page_id : (($group !== '') ? 'group:' . $group : 'all');

// The machine translations of the scope one click marks reviewed.
$review_all_count = ($language !== '') ? pg_tr_review_all_count($language, $scope) : 0;

// What "Clear translations" deletes here: the machine translations, and with
// the reviewed ones the whole count. The dialog names the number.
$clear_machine_count = ($language !== '') ? pg_tr_clear_count($language, $scope, false) : 0;
$clear_all_count = ($language !== '') ? pg_tr_clear_count($language, $scope, true) : 0;
$export_base = 'translations_action.php?action=export&language=' . rawurlencode($language) . '&scope=' . rawurlencode($scope) . '&token=' . rawurlencode($token);

$engine_note = '';

if ($language_row) {
    if (!$engine['ready']) {
        $engine_note = '<div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>' . h($engine['reason']) . '</div>';
    } elseif ($engine_key === 'chrome') {
        $engine_note = '<div class="alert alert-secondary py-2 small mb-3 pg-tr-chrome-note"><i class="bi bi-browser-chrome me-1" aria-hidden="true"></i>' . lang('The browser engine translates on this computer with the Chrome Translator API (desktop Chrome 138 or Edge 148 and newer). The first run downloads the language model.') . pg_tr_browser_pages_html() . '</div>';
    } elseif ($engine_key === 'manual') {
        $engine_note = '<div class="alert alert-secondary py-2 small mb-3"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . lang('This language is translated by hand: "Update translations" scans the pages and lists the pending texts here.') . '</div>';
    } elseif ($engine_key === 'ai') {
        $engine_note = '<div class="alert alert-secondary py-2 small mb-3"><i class="bi bi-stars me-1" aria-hidden="true"></i>' . lang('Pinegrap AI translates on the server through the site\'s own model connection, a few texts at a time with their context, the glossary and the style note. The update runs while this screen is open; the scheduled job finishes what is left.') . '</div>';
    } elseif ($engine_key === 'claude') {
        $engine_note = '<div class="alert alert-secondary py-2 small mb-3"><i class="bi bi-robot me-1" aria-hidden="true"></i>' . lang('Claude\'s routine in the Workspace is told there is work: it fetches the texts through the API with their context, the glossary and the style note, and posts the translations back. The run shares the routine\'s daily allowance; this screen follows its progress.') . '</div>';
    }
}

$config = array(
    'action_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/translations_action.php',
    'token'      => $token,
    'language'   => $language,
    'source'     => $source,
    'engine'     => $engine_key,
    'scope'      => $scope,
    'open_job'   => $open_job ? array('id' => (int) $open_job['id'], 'engine' => $open_job['engine'], 'status' => $open_job['status'], 'total' => (int) $open_job['total'], 'done' => (int) $open_job['done_count'], 'failed' => (int) $open_job['failed_count']) : null,
    'strings'    => array(
        'saving'            => lang('Saving'),
        'saved'             => lang('Saved'),
        'failed'            => lang('Failed'),
        'reviewed'          => lang('Reviewed'),
        'machine'           => lang('Machine'),
        'pending'           => lang('Pending'),
        'manual'            => lang('By hand'),
        'mark_reviewed'     => lang('Mark as reviewed'),
        'mark_machine'      => lang('Mark as not reviewed'),
        'scanning'          => lang('Scanning the pages…'),
        'nothing_pending'   => lang('Every text already has a translation: nothing was sent.'),
        'pending_by_hand'   => lang('{var} text(s) are pending. Translate them below or choose an engine in the toolbar.'),
        'sent'              => lang('{var} text(s) sent to the engine.'),
        'progress'          => lang('{var} of {var2} translated'),
        'finished'          => lang('Finished: {var} translated, {var2} failed. The screen reloads.'),
        'translator_missing' => lang('This browser has no Translator API. Use desktop Chrome 138 or Edge 148 and newer, or choose another engine.'),
        'translator_unavailable' => lang('The browser cannot translate from {var} to {var2}.'),
        'downloading'       => lang('Downloading the language model… {var}%'),
        'network_error'     => lang('The request failed. Please try again.'),
        'confirm_cancel'    => lang('Stop the running update?'),
        'queue_waiting'     => lang('Waiting for Claude to pick the job up…'),
        'queue_held'        => lang('Claude is paused: {var}'),
        'glossary_add'      => lang('Add a term'),
        'glossary_edit'     => lang('Edit the term'),
        'confirm_review_all' => lang('Mark {var} machine translation(s) here as reviewed? The ones flagged suspicious are left to be looked at one by one.'),
        'clear_count'       => lang('{var} translation(s) will be deleted.'),
        'retrying'          => lang('The server did not answer in time; trying again…'),
    ),
);

echo pg_page_shell(array(
    'title'               => lang('Translations'),
    'extra classes'       => 'translations',
    'icon'                => 'settings',
    'heading'             => lang('Translations'),
    'heading_description' => lang('The pages of the visual designs in the other languages of the site: scanned, translated, reviewed.'),
)) . '
<main id="content" class="container-fluid">
    <style>
    /* The source text is read, the translation is typed: the source gets the
       quiet treatment and keeps the line breaks of a multi-line text. */
    .pg-tr-source { white-space: pre-wrap; word-break: break-word; font-size: .9rem; }
    .pg-tr-inline { font-family: var(--bs-font-monospace); font-size: .82rem; }
    .pg-tr-target { font-size: .9rem; }
    .pg-tr-row.pg-tr-dirty .pg-tr-save { --bs-btn-color: var(--bs-primary); --bs-btn-border-color: var(--bs-primary); }
    .pg-tr-pages { max-height: calc(100vh - 16rem); overflow-y: auto; }
    .pg-tr-progress { display: none; }
    .pg-tr-progress.show { display: block; }
    </style>
    ' . $liveform_messages . '
    <div id="pg_tr_app" data-config="' . h(encode_json($config)) . '">
        <div class="pg-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">' . ($targets ? '
            <div class="dropdown">
                <button class="btn btn-sm btn-primary rounded-pill px-3 dropdown-toggle no-popover" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-translate me-2" aria-hidden="true"></i>' . h(pg_tr_language_label($language)) . ' <span class="opacity-75 font-monospace">' . h($language) . '</span>
                </button>
                <ul class="dropdown-menu">' . $output_language_menu . '</ul>
            </div>
            <button type="button" class="btn btn-sm btn-ghost no-popover" id="pg_tr_update"' . ($engine['ready'] ? '' : ' disabled="disabled"') . ' data-bs-toggle="tooltip" title="' . h(($page_id > 0) ? lang('Scan this page and send its pending texts to the engine') : (($group !== '') ? lang('Scan these texts and send the pending ones to the engine') : lang('Scan every page and send the pending texts to the engine'))) . '">
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . (($page_id > 0) ? lang('Update this page') : (($group !== '') ? lang('Update these texts') : lang('Update translations'))) . '
            </button>
            <label class="visually-hidden" for="pg_tr_engine">' . lang('Translation engine') . '</label>
            <select class="form-select form-select-sm w-auto rounded-pill" id="pg_tr_engine" title="' . h(lang(array('string' => '{var:1} → {var:2} · engine', 'vars' => array($source, $language)))) . '">' . $output_engine_options . '</select>
            ' . (($review_all_count > 0) ? '<button type="button" class="btn btn-sm btn-ghost no-popover" id="pg_tr_review_all" data-count="' . (int) $review_all_count . '" data-bs-toggle="tooltip" title="' . h(lang('Mark every machine translation here as reviewed in one go')) . '">
                <i class="bi bi-check2-all me-1" aria-hidden="true"></i>' . lang('Approve all') . ' <span class="opacity-75">' . (int) $review_all_count . '</span>
            </button>' : '') . '
            <div class="pg-toolbar-grow pg-chips">' . $output_chips . '</div>
            <form class="input-group input-group-sm rounded-pill pg-toolbar-search" method="get" action="' . h($self) . '">
                <input type="hidden" name="language" value="' . h($language) . '"/>
                <input type="hidden" name="page_id" value="' . (int) $page_id . '"/>
                <input type="hidden" name="group" value="' . h($group) . '"/>
                <input type="hidden" name="filter" value="' . h($filter) . '"/>
                <span class="input-group-text bg-transparent border-end-0 rounded-start-pill"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" name="q" class="form-control border-start-0 rounded-end-pill" value="' . h($search) . '" placeholder="' . h(lang('Search in the texts')) . '" autocomplete="off"/>
            </form>
            <div class="dropdown">
                <button class="btn btn-sm btn-ghost no-popover" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="' . h(lang('More')) . '"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="' . h($export_base) . '"><i class="bi bi-download me-2" aria-hidden="true"></i>' . lang('Export CSV') . '</a></li>
                    <li><a class="dropdown-item" href="' . h($export_base . '&pending=1') . '"><i class="bi bi-download me-2" aria-hidden="true"></i>' . lang('Export pending texts as CSV') . '</a></li>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#pg_tr_import"><i class="bi bi-upload me-2" aria-hidden="true"></i>' . lang('Import CSV') . '</button></li>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#pg_tr_clear_modal"' . (($clear_all_count > 0) ? '' : ' disabled="disabled"') . '><i class="bi bi-eraser me-2" aria-hidden="true"></i>' . lang('Clear translations') . '</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="' . h(pg_settings_link('languages', 'pgset-languages')) . '"><i class="bi bi-gear me-2" aria-hidden="true"></i>' . lang('Languages and Translation') . '</a></li>
                </ul>
            </div>' : '
            <a class="btn btn-sm btn-primary rounded-pill px-3" href="' . h(pg_settings_link('languages', 'pgset-languages')) . '"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>' . lang('Add a language') . '</a>
            <span class="text-body-secondary small">' . lang('No target language yet. Add one in Settings > Languages and Translation; the pages of the visual designs are then served under its address prefix as well.') . '</span>') . '
        </div>
        ' . ($targets ? '
        ' . $engine_note . '
        <div class="pg-tr-progress alert alert-info py-2 mb-3" id="pg_tr_progress" role="status">
            <div class="d-flex align-items-center gap-3">
                <div class="spinner-border spinner-border-sm flex-shrink-0" role="status" aria-hidden="true"></div>
                <div class="flex-grow-1">
                    <div class="pg-tr-progress-text small"></div>
                    <div class="progress mt-1" style="height: 4px;"><div class="progress-bar pg-tr-progress-bar" style="width: 0%"></div></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary pg-tr-cancel">' . lang('Stop') . '</button>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-lg-4 col-xxl-3">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Pages') . '</span>
                        <span class="small text-body-secondary">' . (int) $site_stats['reviewed'] . ' ' . lang('reviewed') . '</span>
                    </div>
                    <div class="list-group list-group-flush pg-tr-pages">' . $output_pages . '</div>
                </div>' . (($output_groups !== '') ? '
                <div class="card mb-3">
                    <div class="card-header">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Other texts') . '</span>
                    </div>
                    <div class="list-group list-group-flush">' . $output_groups . '</div>
                </div>' : '') . '
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Recent updates') . '</span>
                    </div>
                    <div class="card-body py-2" id="pg_tr_jobs">' . $output_jobs . '</div>
                </div>
                <div class="card mb-3" id="pg_tr_glossary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . lang('Glossary') . '</span>
                        <button type="button" class="btn btn-sm btn-ghost no-popover pg-tr-term-add" data-bs-toggle="modal" data-bs-target="#pg_tr_glossary_modal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>' . lang('Add a term') . '</button>
                    </div>
                    <div class="card-body py-2">' . $output_glossary . '</div>
                </div>
            </div>
            <div class="col-12 col-lg-8 col-xxl-9">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . ($selected_page ? h($selected_page['page_name']) : (($group !== '') ? h($owner_groups[$group]['label']) : lang('All texts'))) . '</span>
                        <span class="small text-body-secondary">' . h(lang(array('string' => '{var:1} text(s)', 'vars' => array($total_rows)))) . ($selected_page ? ' · <a href="' . h(PATH . pg_tr_prefix($language) . '/' . (($selected_page['page_home'] === 'yes') ? '' : encode_url_path($selected_page['page_name']))) . '" target="_blank" rel="noopener">' . lang('Open the page') . ' <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>' : '') . '</span>
                    </div>
                    <div class="card-body pt-0">' . (($group === 'ui') ? '<div class="small text-body-secondary py-2 border-bottom"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . lang('The texts of the cookie window: the notice, its settings and what each cookie is for. This language has no language file, so the window is translated here; until a text is translated, the English wording is shown. The rest of the software\'s wording is not translated here.') . '</div>' : '') . $output_rows . '
                        ' . ($output_pagination !== '' ? '<div class="d-flex justify-content-center pt-3">' . $output_pagination . '</div>' : '') . '
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="pg_tr_import" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="translations_action.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import"/>
                    <input type="hidden" name="language" value="' . h($language) . '"/>
                    ' . get_token_field() . '
                    <div class="modal-header">
                        <h5 class="modal-title">' . lang('Import CSV') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="pg_tr_import_file">' . lang('CSV file') . '</label>
                            <input type="file" class="form-control" id="pg_tr_import_file" name="csv" accept=".csv,text/csv" required="required"/>
                            <div class="form-text">' . lang('A file exported from this screen, with its translation column filled in. Rows are matched by the hash column; a row whose source text has since changed is skipped.') . '</div>
                        </div>
                        <div class="form-check">
                            <input value="1" class="form-check-input" type="checkbox" id="pg_tr_import_machine" name="as_machine"/>
                            <label class="form-check-label" for="pg_tr_import_machine">' . lang('Import as machine translations (still to be reviewed)') . '</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="submit" class="btn btn-primary">' . lang('Import') . '</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="pg_tr_clear_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">' . lang('Clear translations') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-body-secondary">' . lang('The translations of the texts shown here are deleted, so the next "Update translations" sends the texts to the engine again. A text that is also used elsewhere loses its translation there too.') . '</p>
                        <div class="form-check mb-3">
                            <input value="1" class="form-check-input pg-tr-clear-reviewed" type="checkbox" id="pg_tr_clear_reviewed"' . (($clear_all_count > $clear_machine_count) ? '' : ' disabled="disabled"') . '/>
                            <label class="form-check-label" for="pg_tr_clear_reviewed">' . lang('Delete the reviewed translations as well') . ' <span class="opacity-75">' . ($clear_all_count - $clear_machine_count) . '</span></label>
                        </div>
                        <p class="fw-semibold mb-0 pg-tr-clear-count"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="button" class="btn btn-outline-warning pg-tr-clear" data-machine="' . $clear_machine_count . '" data-all="' . $clear_all_count . '"><i class="bi bi-eraser me-1" aria-hidden="true"></i>' . lang('Clear translations') . '</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="pg_tr_glossary_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="translations_action.php">
                    <input type="hidden" name="action" value="glossary_save"/>
                    <input type="hidden" name="language" value="' . h($language) . '"/>
                    <input type="hidden" name="id" value="0"/>
                    ' . get_token_field() . '
                    <div class="modal-header">
                        <h5 class="modal-title">' . lang('Add a term') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="pg_tr_term">' . lang('Term') . ' <span class="text-body-secondary fw-normal">(' . h(pg_tr_language_label($source)) . ')</span></label>
                            <input type="text" class="form-control" id="pg_tr_term" name="term" maxlength="255" required="required" autocomplete="off"/>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input value="1" class="form-check-input" type="checkbox" role="switch" id="pg_tr_term_keep" name="keep"/>
                            <label class="form-check-label" for="pg_tr_term_keep">' . lang('Keep as written (a brand, a product name)') . '</label>
                        </div>
                        <div class="mb-3 pg-tr-term-translation">
                            <label class="form-label" for="pg_tr_term_translation">' . lang('Translation to use') . ' <span class="text-body-secondary fw-normal">(' . h(pg_tr_language_label($language)) . ')</span></label>
                            <input type="text" class="form-control" id="pg_tr_term_translation" name="translation" maxlength="255" autocomplete="off"/>
                        </div>
                        <div class="form-check form-switch mb-2 pg-tr-term-all">
                            <input value="1" class="form-check-input" type="checkbox" role="switch" id="pg_tr_term_all" name="all_languages"/>
                            <label class="form-check-label" for="pg_tr_term_all">' . lang('For every language') . '</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input value="1" class="form-check-input" type="checkbox" role="switch" id="pg_tr_term_case" name="case_sensitive"/>
                            <label class="form-check-label" for="pg_tr_term_case">' . lang('Match upper and lower case exactly') . '</label>
                        </div>
                        <div>
                            <label class="form-label" for="pg_tr_term_note">' . lang('Note for the translator') . ' <span class="text-body-secondary fw-normal">(' . lang('optional') . ')</span></label>
                            <input type="text" class="form-control" id="pg_tr_term_note" name="note" maxlength="255" autocomplete="off"/>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">' . lang('Cancel') . '</button>
                        <button type="submit" class="btn btn-primary">' . lang('Save') . '</button>
                    </div>
                </form>
            </div>
        </div>' : '') . '
    </div>
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/translations.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/translations.js') . '"></script>
</main>' . output_footer();
