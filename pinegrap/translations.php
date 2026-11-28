<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Translations: the pages of the visual designs in the site's other
 * languages - what is translated, what is pending, what was written by a
 * machine and still has to be read - and the editor that reads and corrects
 * them text by text.
 *
 * The head holds the language, the tabs "Texts", "Activity" and "Glossary",
 * and the two jobs: "Scan for new texts" extracts the texts of the scope,
 * "Translate pending" extracts them and sends the pending ones to the
 * language's engine. On "Texts" the rail lists the pages and the other
 * groups with their coverage, the filter tiles count the scope's texts and
 * the list is the editor for the selected scope. "Clear translations"
 * deletes the ones of the selection so they are sent again. Under the head
 * the automation line shows the engine, its fallback and "Translate on
 * save", changed in one dialog; while an update runs the progress card takes
 * its place. "Activity" lists the updates and opens one in a drawer with its
 * failed texts and their reasons. The browser engine runs here, in this
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

// The lang attribute (and dir="rtl" for a right-to-left language) of an
// element that holds text in the language $code: the panel's own <html lang>
// is the panel language, so screen readers would read the text in the wrong
// voice and a right-to-left translation would be typed left to right.
$text_lang = function ($code) {
    $code = (string) $code;

    if ($code === '') {
        return '';
    }

    $known = pg_tr_known_languages();

    return ' lang="' . h($code) . '"' . ((isset($known[$code]) && ($known[$code][5] === 1)) ? ' dir="rtl"' : '');
};

// Every target language, the ones not served as well: a language is
// translated here before it goes live (Site Settings > Languages and
// Translation turns "Served" on).
$served_first = '';

foreach (pg_tr_languages(false) as $code => $row) {
    if ($code !== $source) {
        $targets[$code] = $row;

        if (($served_first === '') && ((int) $row['enabled'] === 1)) {
            $served_first = (string) $code;
        }
    }
}

$language = (isset($_GET['language']) && is_string($_GET['language'])) ? trim($_GET['language']) : '';

// With no language asked for, the first one served opens.
if (!isset($targets[$language])) {
    $language = ($served_first !== '') ? $served_first : ($targets ? (string) key($targets) : '');
}

$page_id = (isset($_GET['page_id']) && is_string($_GET['page_id'])) ? (int) $_GET['page_id'] : 0;

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

$group = (isset($_GET['group']) && is_string($_GET['group'])) ? $_GET['group'] : '';

if (!isset($owner_groups[$group])) {
    $group = '';
}

if ($group !== '') {
    $page_id = 0;
}

// The queue: "Draw the waiting pictures" on the Pictures group draws each
// picture waiting or out of date in this browser, one after another, with
// the Artifact Canvas's own drawing (assets/js/picture_queue.js). The server
// checks each draw as the Artifact Canvas's Languages list does. Without this
// view the same rows are drawn by an open panel page (picture_auto.js) and
// by the translation job (pg_picture_tr_draw_due()). A Look by hand row's
// button opens the canvas on that language's view (data-pg-image-language).
if (($group === 'pictures') && !empty($_GET['draw'])) {
    $queue = pg_picture_tr_queue();
    $queue_back = 'translations.php?' . http_build_query(array('language' => $language, 'group' => 'pictures'));
    $queue_rows = '';
    $open_picture_label = lang('Edit in Artifact Canvas');

    foreach ($queue as $queue_item) {
        $queue_rows .= '<li class="pg-list-row" data-pq-row="1">
                <span class="pg-icon-tile" aria-hidden="true"><i class="bi bi-image"></i></span>
                <div class="pg-list-row-main">
                    <div class="pg-list-row-head pg-list-row-head-wrap"><span class="pg-list-row-title">' . h($queue_item['name']) . '</span><span class="pg-pill pg-tone-gray text-nowrap">' . lang('Waiting') . '</span></div>
                    <div class="pg-list-row-text"><span' . $text_lang($queue_item['language']) . '>' . h($queue_item['label']) . '</span><span data-pq-reason="1"> · ' . (($queue_item['state'] === 2) ? lang('Out of date') : lang('Not drawn yet')) . '</span><span class="d-block" data-pq-note="1" hidden></span></div>
                    <div class="pg-list-row-actions" data-pq-edit="1" hidden><button type="button" class="pg-button pg-button-quiet pg-button-sm" data-pg-image-edit="' . h($queue_item['name']) . '" data-pg-image-language="' . h($queue_item['language']) . '" title="' . h($open_picture_label) . '">' . pg_design_canvas_icon_one_colour('') . lang('Edit in Artifact Canvas') . '</button></div>
                </div>
            </li>';
    }

    echo pg_page_shell(array(
        'title'               => lang('Draw the waiting pictures'),
        'extra classes'       => 'translations pg-measure-list',
        'icon'                => 'settings',
        'heading'             => lang('Draw the waiting pictures'),
        'heading_description' => lang('The pictures made in the Artifact Canvas whose texts are translated, drawn now in each language one after another in this browser. They are drawn by themselves without this page too: by an open panel, or, with nobody in the panel, by the server where it can. Keep this page open until the count reaches zero.'),
    )) . '
<main id="content" class="container-fluid">
    <div id="pg_pq" data-config="' . h(encode_json(array(
        'items'   => $queue,
        'strings' => array(
            'waiting'    => lang('Waiting'),
            'drawing'    => lang('Drawing…'),
            'drawn'      => lang('Picture drawn'),
            'look'       => lang('Look by hand'),
            'skipped'    => lang('Not every text is translated'),
            'failed'     => lang('The picture could not be drawn.'),
            'left'       => lang('{n} left'),
            'stopped'    => lang('Stopped: {n} left'),
            'done'       => lang('All the waiting pictures are drawn.'),
            'doneLook'   => lang('All the waiting pictures are drawn; {n} to look at by hand.'),
            'doneFailed' => lang('Done; {n} could not be drawn.'),
            'stop'       => lang('Stop'),
            'stopping'   => lang('Stopping…'),
            'resume'     => lang('Continue'))))) . '">
        <div class="pg-toolbar d-flex flex-wrap align-items-center gap-3 mb-5">
            <a class="pg-button pg-button-quiet" href="' . h($queue_back) . '"><i class="bi bi-arrow-left" aria-hidden="true"></i>' . lang('Back to the pictures') . '</a>
            <span class="pg-toolbar-grow pg-text-2 pg-tabular" id="pg_pq_left" role="status" aria-live="polite">' . ($queue ? h(str_replace('{n}', (string) count($queue), lang('{n} left'))) : lang('Nothing is waiting to be drawn.')) . '</span>
            <button type="button" class="pg-button pg-button-quiet" id="pg_pq_stop"' . ($queue ? '' : ' hidden') . '>' . lang('Stop') . '</button>
        </div>
        <section class="pg-card p-5" aria-labelledby="pg_pq_title">
            <h2 class="pg-title-section" id="pg_pq_title">' . lang('Waiting pictures') . '</h2>
            <p class="pg-card-sub">' . lang('Each language is drawn from the picture\'s last saved version once every one of its texts is translated. The pages in that language show it from then on.') . ' ' . lang('A longer translation takes the free room around its text, then wraps and gets smaller; a picture where a text still does not fit ends as "Look by hand" and is corrected in the Artifact Canvas.') . '</p>
            ' . ($queue ? '<ul class="list-unstyled mb-0">' . $queue_rows . '</ul>' : '<p class="pg-text-2 mb-0">' . lang('Nothing is waiting to be drawn.') . '</p>') . '
        </section>
    </div>
    ' . ($queue ? pg_image_editor_includes() : '') . '
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/picture_queue.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/picture_queue.js') . '"></script>
</main>' . output_footer();

    exit;
}

$filter = (isset($_GET['filter']) && is_string($_GET['filter'])) ? $_GET['filter'] : 'all';

if (!in_array($filter, array('all', 'pending', 'machine', 'reviewed', 'suspicious', 'blocking'), true)) {
    $filter = 'all';
}

$search = (isset($_GET['q']) && is_string($_GET['q'])) ? trim($_GET['q']) : '';
// The tab open on arrival: the texts, the updates that ran, the glossary.
// Switching tabs happens on the screen; the address follows it.
$tab = (isset($_GET['tab']) && is_string($_GET['tab']) && in_array($_GET['tab'], array('activity', 'glossary'), true)) ? $_GET['tab'] : 'texts';
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

// The view a form posted from this screen comes back to.
$view_fields = '';

foreach (array('page_id' => $page_id, 'group' => $group, 'filter' => $filter, 'q' => $search) as $view_key => $view_value) {
    $view_fields .= '<input type="hidden" name="' . $view_key . '" value="' . h((string) $view_value) . '"/>';
}

$pages = pg_tr_translatable_pages();

// A page deleted or put in the recycle bin since the last update draws
// nothing: its texts leave the counts before they are read.
if ($language !== '') {
    pg_tr_uses_prune(array_column($pages, 'page_id'));
}

$stats = ($language !== '') ? pg_tr_page_stats($language) : array();
$site_stats = ($language !== '') ? pg_tr_language_stats($language) : array('total' => 0, 'translated' => 0, 'reviewed' => 0, 'machine' => 0, 'pending' => 0);
$language_row = ($language !== '') ? $targets[$language] : null;
$language_served = $language_row && ((int) $language_row['enabled'] === 1);
$engines = pg_tr_engines();
$engine_key = $language_row ? (string) $language_row['engine'] : 'manual';
$engine = isset($engines[$engine_key]) ? $engines[$engine_key] : $engines['manual'];

// The dialog "Automation for <language>": the engines the language may be
// set to, one not ready marked as on the settings card, and the fallback
// engines ("By hand" is none). Pinegrap AI and Claude are listed only while
// the Workspace is on; the ones set now are kept in the list either way.
$fallback_key = $language_row ? (string) $language_row['fallback_engine'] : '';
$auto_update_known = $language_row && array_key_exists('auto_update', $language_row);
$auto_update_on = $auto_update_known && ((int) $language_row['auto_update'] === 1) && ($engine_key !== 'manual');
$output_engine_options = '';
$output_fallback_options = '<option value="">' . h(lang('-None-')) . '</option>';

foreach (pg_tr_engine_options(array($engine_key, $fallback_key)) as $option_key => $option_label) {
    $option_spec = isset($engines[$option_key]) ? $engines[$option_key] : null;
    $option_text = h($option_label) . (($option_spec && !$option_spec['ready']) ? ' (' . h(lang('not ready')) . ')' : '');
    $option_kind = $option_spec ? $option_spec['kind'] : 'panel';
    $output_engine_options .= '<option value="' . h($option_key) . '" data-kind="' . h($option_kind) . '"' . (($option_key === $engine_key) ? ' selected="selected"' : '') . '>' . $option_text . '</option>';

    if ($option_key !== 'manual') {
        $output_fallback_options .= '<option value="' . h($option_key) . '"' . (($option_key === $fallback_key) ? ' selected="selected"' : '') . '>' . $option_text . '</option>';
    }
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

// The two jobs of the head: "Scan for new texts" only scans and lists,
// "Translate pending" scans and sends what is pending to the engine. By hand
// nothing goes to an engine, so only the scan is offered. The notes that
// point at a job use its label.
$scan_button_label = lang('Scan for new texts');
$scan_button_title = ($page_id > 0) ? lang('Scan this page and list its pending texts here') : (($group !== '') ? lang('Scan these texts and list the pending ones here') : lang('Scan every page and list the pending texts here'));
$update_button_label = ($engine_key === 'manual') ? $scan_button_label : lang('Translate pending');
$update_button_title = ($page_id > 0) ? lang('Scan this page and send its pending texts to the engine') : (($group !== '') ? lang('Scan these texts and send the pending ones to the engine') : lang('Scan every page and send the pending texts to the engine'));

// Under the reviewed policy a page is indexed in the language only when its
// own words stand approved: what each page answered the last time it was
// drawn (one read), and for the selected page, on request, which texts kept
// it out.
$index_watch = $language_row && ((string) $language_row['index_policy'] === 'reviewed') && pg_tr_page_verdict_ready();
$verdicts = $index_watch ? pg_tr_page_verdicts($language) : array();

// ── the texts ──

$rows = array();
$total_rows = 0;
$total_pages = 1;
// The counts of the chips: the selected page's or group's texts, the whole
// site's otherwise.
$scope_stats = $site_stats;

if ($language !== '') {

    $page_owners = ($page_id > 0) ? pg_tr_page_owners($page_id) : array();
    $owner_where = ($page_id > 0) ? pg_tr_owner_where($page_owners) : (($group !== '') ? pg_tr_owner_where(pg_tr_scope_owners('group:' . $group)) : pg_tr_owner_where(array()));
    $where = array('u.string_id > 0');

    if (($page_id > 0) || ($group !== '')) {
        $scope_stats = pg_tr_language_stats($language, $owner_where);
    }

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
        // What keeps a page out of the search engines under the reviewed
        // policy: no translation, or an unapproved one that differs from
        // the source. A list's records never do; a record view's keep the
        // record's own page out (the selected page's widgets, or the site's).
        case 'blocking':
            $where[] = pg_tr_screen_blocking_sql();
            $where[] = pg_tr_screen_blocking_records_sql(pg_tr_item_view_forms(($page_id > 0) ? $page_owners : null));
            break;
    }

    if ($search !== '') {
        $like = "'%" . e(escape_like($search)) . "%'";
        // A row shows the first 8 characters of its hash; "Open the text"
        // of an update's drawer searches for them.
        $hash_like = preg_match('/^[0-9a-f]{8,64}$/', $search) ? " OR s.hash LIKE '" . e($search) . "%'" : '';
        $where[] = "(s.source_text LIKE $like OR t.text LIKE $like$hash_like)";
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

// The counts of the two tiles the language's figures do not hold: the
// suspicious texts of the scope, and the ones that keep its pages out of
// search (only while that tile is shown).
$suspicious_count = 0;
$blocking_count = 0;

if ($language !== '') {
    $tile_where = array('u.string_id > 0');

    if ($owner_where !== '') {
        $tile_where[] = '(' . $owner_where . ')';
    }

    $suspicious_count = (int) db_value("SELECT COUNT(DISTINCT t.string_id)
                                        FROM translation_uses u
                                        INNER JOIN translations t ON t.string_id = u.string_id AND t.language = '" . e($language) . "'
                                        WHERE t.suspicious = 1 AND " . implode(' AND ', $tile_where));

    if ($index_watch || ($filter === 'blocking')) {
        $blocking_count = (($filter === 'blocking') && ($search === '')) ? $total_rows : (int) db_value("SELECT COUNT(DISTINCT s.id)
                                        FROM translation_uses u
                                        INNER JOIN translation_strings s ON s.id = u.string_id
                                        LEFT JOIN translations t ON t.string_id = s.id AND t.language = '" . e($language) . "'
                                        WHERE " . implode(' AND ', array_merge($tile_where, array(pg_tr_screen_blocking_sql(), pg_tr_screen_blocking_records_sql(pg_tr_item_view_forms(($page_id > 0) ? $page_owners : null))))));
    }
}

// ── why the selected page is kept out: read only when asked for (the
//    "blocking" filter), where each text listed above comes from ──

$index_reasons = array();
$index_row_owners = array();
$index_owner_names = array();

if ($index_watch && ($page_id > 0) && ($filter === 'blocking')) {
    $index_reasons = pg_tr_screen_reasons($language, $page_id);
    $index_row_owners = pg_tr_screen_string_owners(array_column($rows, 'id'), $page_id);

    foreach ($index_reasons as $reason) {
        $index_owner_names[$reason['kind'] . ':' . $reason['id']] = $reason['name'];
    }
}

// The label of a place a page draws texts from.
$index_owner_label = function ($kind, $name) {
    switch ($kind) {
        case 'page':
            return lang('The page itself');
        case 'page_seo':
            return lang('Its title and description');
        case 'shared':
            return lang(array('string' => 'Shared component "{var:1}"', 'vars' => array($name)));
        case 'menu':
            return lang(array('string' => 'Menu "{var:1}"', 'vars' => array($name)));
        case 'form_records':
            return lang(array('string' => 'Records of the form "{var:1}"', 'vars' => array($name)));
    }

    return lang('Other texts drawn on the page');
};

// ── the glossary: the terms for this language, and the ones for every language ──

$glossary = ($language !== '') ? pg_tr_glossary($language) : array();

// ── the updates: the one still open, which the screen follows, and the
//    page of them the "Activity" tab lists ──

$open_job = ($language !== '') ? db_item("SELECT * FROM translation_jobs WHERE language = '" . e($language) . "' AND status IN ('queued', 'sent', 'running') ORDER BY id DESC LIMIT 1") : null;
$open_job = is_array($open_job) ? $open_job : null;
// The count on the "Activity" tab: the updates still open, and the finished
// ones that left failed texts.
$activity_count = ($language !== '') ? (int) db_value("SELECT COUNT(*) FROM translation_jobs WHERE language = '" . e($language) . "' AND (status IN ('queued', 'sent', 'running') OR failed_count > 0)") : 0;
$activity_filter = (isset($_GET['af']) && is_string($_GET['af']) && in_array($_GET['af'], array('running', 'failed'), true)) ? $_GET['af'] : 'all';
$activity_page = isset($_GET['ap']) ? max(1, (int) $_GET['ap']) : 1;
$activity = ($language !== '') ? pg_tr_activity_jobs($language, $activity_filter, $activity_page) : array('rows' => array(), 'total' => 0);

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

// A refused glossary save comes back with what was typed: the dialog opens
// again with it, the refused field marked.
$glossary_back = null;

if (((string) $liveform->get('glossary_form') === '1') && $liveform->check_form_errors()) {
    $glossary_back = array('error' => $liveform->check_field_error('translation') ? 'translation' : 'term');

    foreach (array('id', 'term', 'translation', 'keep', 'case_sensitive', 'all_languages', 'note') as $glossary_field) {
        $glossary_back[$glossary_field] = (string) $liveform->get($glossary_field);
    }
}

$liveform_messages = $liveform->output_errors() . $liveform->output_notices();
$liveform->remove_form();

// The glossary dialog's field values and marks: empty, or the refused save's.
$glossary_value = function ($field) use ($glossary_back) {
    return $glossary_back ? h($glossary_back[$field]) : '';
};
$glossary_checked = function ($field) use ($glossary_back) {
    return ($glossary_back && ($glossary_back[$field] === '1')) ? ' checked="checked"' : '';
};
$glossary_invalid = function ($field) use ($glossary_back) {
    return ($glossary_back && ($glossary_back['error'] === $field)) ? ' is-invalid" aria-invalid="true' : '';
};

$token = (string) ($_SESSION['software']['token'] ?? '');

// Language switcher: the primary control of the toolbar.
$output_language_menu = '';

// Each language with the share of the site's texts it has a translation for.
foreach ($targets as $code => $row) {
    $code_stats = ($code === $language) ? $site_stats : pg_tr_language_stats($code);
    $code_share = pg_tr_screen_share($code_stats['translated'], $code_stats['total']);

    $output_language_menu .= '<li><a class="menu-item d-flex align-items-center gap-3' . (($code === $language) ? ' active" aria-current="page' : '') . '" href="' . h($url(array('language' => $code, 'page_id' => 0, 'group' => '', 'p' => 0))) . '">'
        . '<span class="flex-grow-1"' . $text_lang($code) . '>' . h(pg_tr_language_label($code)) . '</span>'
        . '<span class="pg-text-meta font-monospace">' . h($code) . '</span>'
        . '<span class="pg-text-meta pg-tabular pg-tr-share">' . h(lang(array('string' => '{var:1}%', 'vars' => array($code_share)))) . '</span>'
        . (((int) $row['enabled'] === 1) ? '' : '<span class="pg-pill pg-tone-gray text-nowrap">' . lang('Not served') . '</span>')
        . '</a></li>';
}

$output_language_menu .= '<li><hr class="menu-divider"></li>'
    . '<li><a class="menu-item" href="' . h(pg_settings_link('languages', 'pgset-languages')) . '"><i class="bi bi-gear me-3" aria-hidden="true"></i>' . lang('Languages and Translation') . '</a></li>';

// The filter tiles: the scope's count of each state, the one chosen marked.
// The four state counts follow a save on the screen (.pg-tr-chip-count).
$output_tiles = '';
$tile_specs = array(
    'all'        => array(lang('All'), $scope_stats['total'], ''),
    'pending'    => array(lang('Pending'), $scope_stats['pending'], 'pg-tone-gray'),
    'machine'    => array(lang('Machine'), $scope_stats['machine'], 'pg-tone-amber'),
    'reviewed'   => array(lang('Reviewed'), $scope_stats['reviewed'], 'pg-tone-teal'),
    'suspicious' => array(lang('Suspicious'), $suspicious_count, 'pg-tone-rose'),
    'blocking'   => ($index_watch || ($filter === 'blocking')) ? array(lang('Keeping pages out of search'), $blocking_count, 'blocking') : null,
);

foreach ($tile_specs as $key => $tile_spec) {
    if ($tile_spec === null) {
        continue;
    }

    $tile_mark = ($tile_spec[2] === 'blocking') ? '<i class="bi bi-eye-slash" aria-hidden="true"></i>' : (($tile_spec[2] !== '') ? '<span class="pg-tr-dot ' . $tile_spec[2] . '" aria-hidden="true"></span>' : '');

    $output_tiles .= '<a class="pg-card pg-figure pg-tr-tile' . (($key === $filter) ? ' active" aria-current="true' : '') . '" href="' . h($url(array('filter' => $key, 'p' => 0))) . '">'
        . '<span class="pg-figure-value' . (in_array($key, array('all', 'pending', 'machine', 'reviewed'), true) ? ' pg-tr-chip-count" data-state="' . h($key) : '') . '">' . number_format((int) $tile_spec[1]) . '</span>'
        . '<span class="pg-figure-label">' . $tile_mark . '<span>' . h($tile_spec[0]) . '</span></span></a>';
}

// A row of the rail: its name, its count, an optional mark after the count
// and the bar of its share. The current one carries aria-current.
$rail_current = null;
$rail_row = function ($href, $current, $icon, $name, $count, $share, $mark = '', $title = '', $page = false) use (&$rail_current) {
    if ($current) {
        $rail_current = array('icon' => $icon, 'name' => $name, 'count' => $count);
    }

    return '<a class="pg-tr-rail-row' . ($current ? ' active" aria-current="page' : '') . '" href="' . h($href) . '"' . ($page ? ' data-pg-tr-page="1"' : '') . '>'
        . (($icon !== '') ? '<i class="bi ' . h($icon) . '" aria-hidden="true"></i>' : '')
        . '<span class="pg-tr-rail-name"' . (($title !== '') ? ' title="' . h($title) . '"' : '') . '>' . $name . '</span>'
        . '<span class="pg-tr-rail-count pg-tabular">' . $count . '</span>'
        . $mark
        . '<span class="pg-tr-rail-bar" aria-hidden="true"><span style="width: ' . (int) $share . '%"></span></span>'
        . '</a>';
};

// The page list. "All texts" is the view with no page and no group: a group
// chosen is the one current row, and pressing a page or "All texts" leaves
// the group.
$output_pages = '';
$output_all_row = $rail_row($url(array('page_id' => 0, 'group' => '', 'p' => 0)), ($page_id === 0) && ($group === ''), 'bi-collection', h(lang('All texts')),
    '<span class="pg-tr-all-done">' . number_format((int) $site_stats['translated']) . '</span>/' . number_format((int) $site_stats['total']), pg_tr_screen_share($site_stats['translated'], $site_stats['total']));

foreach ($pages as $row) {
    $pid = (int) $row['page_id'];
    $stat = isset($stats[$pid]) ? $stats[$pid] : array('total' => 0, 'translated' => 0, 'reviewed' => 0, 'seen_at' => 0);
    $title = ($row['page_title'] !== '') ? $row['page_title'] : $row['page_name'];
    $output_verdict = '';

    // The counter counts the page's own texts; whether the page is indexed
    // depends on everything drawn on it, so the answer it gave is shown
    // beside the counter.
    if ($index_watch) {
        switch (pg_tr_screen_verdict_state($verdicts, $pid, (int) $row['noindex'] === 1)) {
            case 'own_noindex':
                $verdict_icon = 'bi-slash-circle opacity-75';
                $verdict_label = lang('Kept out of search results by the page\'s own setting');
                break;
            case 'indexed':
                $verdict_icon = 'bi-search';
                $verdict_label = lang('In search results');
                break;
            case 'noindex':
                $verdict_icon = 'bi-eye-slash fg-warning';
                $verdict_label = lang('Kept out of search results: select the page to see why');
                break;
            default:
                $verdict_icon = 'bi-hourglass opacity-50';
                $verdict_label = lang('Not checked yet: the page has not been opened in this language since its translations last changed');
        }

        $output_verdict = '<i class="bi ' . $verdict_icon . ' pg-tr-rail-mark" title="' . h($verdict_label) . '" aria-hidden="true"></i><span class="visually-hidden">' . h($verdict_label) . '</span>';
    }

    $output_pages .= $rail_row($url(array('page_id' => $pid, 'group' => '', 'p' => 0)), $pid === $page_id, ($row['page_home'] === 'yes') ? 'bi-house-door' : '', h($row['page_name']),
        ($stat['total'] > 0) ? (int) $stat['translated'] . '/' . (int) $stat['total'] : h(lang('Not scanned')), pg_tr_screen_share($stat['translated'], $stat['total']), $output_verdict, $title, true);
}

if (!$pages) {
    $output_pages .= '<p class="pg-text-meta px-3 py-2 mb-0">' . lang('No design page yet. Pages built in the Page Design Studio can be translated.') . '</p>';
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

        $output_groups .= $rail_row($url(array('group' => $group_key, 'page_id' => 0, 'p' => 0)), $group_key === $group, (string) $group_spec['icon'], h($group_spec['label']),
            ($group_total > 0) ? number_format($group_done) . '/' . number_format($group_total) : h(in_array($group_key, array('ui', 'pictures'), true) ? lang('None yet') : lang('Not scanned')), pg_tr_screen_share($group_done, $group_total));
    }
}

// The Pictures group: the picture each text comes from, which opens in the
// Artifact Canvas (pg_image_editor_includes(), its data-pg-image-edit button).
$row_pictures = ($group === 'pictures') ? pg_tr_picture_files(array_column($rows, 'id')) : array();

// How many pictures the queue would draw for this user (up to its bound).
$picture_queue_count = ($group === 'pictures') ? count(pg_picture_tr_queue()) : 0;
$open_picture_label = lang('Edit in Artifact Canvas');

// The editor rows.
$output_rows = '';

foreach ($rows as $row) {
    $has = ($row['text'] !== null);
    $state = $has ? (string) $row['status'] : 'pending';
    $state_label = $has ? (($state === 'reviewed') ? lang('Reviewed') : lang('Machine')) : lang('Pending');
    // The review toggle is named by what pressing it does: a reviewed row
    // goes back to not reviewed.
    $review_label = ($state === 'reviewed') ? lang('Mark as not reviewed') : lang('Mark as reviewed');
    $state_class = $has ? (($state === 'reviewed') ? 'pg-tone-teal' : 'pg-tone-amber') : 'pg-tone-gray';
    $engine_label = ($has && isset($engine_labels[$row['engine']])) ? $engine_labels[$row['engine']] : ($has ? (string) $row['engine'] : '');
    $row_owner = isset($index_row_owners[(int) $row['id']]) ? $index_row_owners[(int) $row['id']] : null;
    $row_owner_name = $row_owner ? (isset($index_owner_names[$row_owner['kind'] . ':' . $row_owner['id']]) ? $index_owner_names[$row_owner['kind'] . ':' . $row_owner['id']] : '') : '';

    $row_id = (int) $row['id'];
    $flagged = ($has && ((int) $row['suspicious'] === 1));
    $flag_reason = $flagged ? pg_tr_screen_suspicious_reason((string) $row['source_text'], (string) $row['text']) : '';

    // One grid a row: the tick, the source, the translation, the state
    // column (the pill, the engine, "Suspicious" on a line of its own, then
    // Save, the eye and the "Details" caret). "Details" opens under the
    // source and the translation and is read when first opened.
    $output_rows .= '
            <div class="pg-tr-row" data-string-id="' . $row_id . '" data-format="' . h($row['format']) . '" data-state="' . h($state) . '">
                <div class="pg-tr-tick">
                    <input class="check pg-tr-pick" type="checkbox" value="' . $row_id . '" aria-label="' . h(lang('Select this text')) . '"/>
                </div>
                <div class="pg-tr-src min-w-0">
                    <div class="pg-tr-source pg-tr-clamp' . (($row['format'] === 'inline') ? ' pg-tr-inline' : '') . '" id="pg_tr_source_' . $row_id . '"' . $text_lang($source) . '>' . h($row['source_text']) . '</div>
                    <button type="button" class="pg-tr-show-all d-none" aria-controls="pg_tr_source_' . $row_id . '" aria-expanded="false">' . lang('Show all') . '</button>
                    <div class="pg-tr-marks">'
                        . ($row_owner ? '<span class="pg-pill pg-tone-gray">' . h($index_owner_label($row_owner['kind'], $row_owner_name)) . '</span>' : '')
                        . implode('', array_map(function ($picture) use ($open_picture_label) {
                            return '<button type="button" class="badge badge-subtle theme-secondary border pg-tr-picture" data-pg-image-edit="' . h($picture['name']) . '" title="' . h($open_picture_label) . '"><i class="bi bi-image me-1" aria-hidden="true"></i>' . h($picture['name']) . '<span class="visually-hidden"> · ' . h($open_picture_label) . '</span></button>';
                        }, isset($row_pictures[$row_id]) ? $row_pictures[$row_id] : array()))
                        . (($row['kind'] === 'seo') ? '<span class="pg-pill pg-tone-gray">SEO</span>' : '')
                        . (($row['format'] === 'inline') ? '<span class="pg-pill pg-tone-gray">HTML</span>' : '')
                        . '<span class="font-monospace pg-tr-hash">' . h(substr($row['hash'], 0, 8)) . '</span>'
                    . '</div>
                </div>
                <div class="pg-tr-text min-w-0">
                    <textarea class="pg-input pg-tr-target" rows="' . pg_tr_screen_target_rows($has ? (string) $row['text'] : '') . '" aria-label="' . h(lang('Translation')) . '" aria-describedby="pg_tr_source_' . $row_id . ' pg_tr_why_' . $row_id . '"' . $text_lang($language) . ' placeholder="' . h(lang('Not translated yet: the source text is shown on the page.')) . '">' . h($has ? (string) $row['text'] : '') . '</textarea>
                    <div class="pg-field-help pg-field-error pg-tr-why" id="pg_tr_why_' . $row_id . '" role="alert" hidden><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span class="pg-tr-why-text"></span></div>
                </div>
                <div class="pg-tr-state">
                    <span class="pg-pill ' . $state_class . ' pg-tr-state-badge">' . h($state_label) . '</span>
                    <span class="pg-text-meta pg-tr-engine">' . h($engine_label) . '</span>
                    <span class="pg-pill pg-tone-rose pg-tr-suspicious' . ($flagged ? '' : ' d-none') . '"' . ($flagged ? ' title="' . h($flag_reason) . '"' : '') . '>' . lang('Suspicious') . '</span>
                    <div class="pg-tr-acts">
                        <button type="button" class="pg-button pg-button-quiet pg-button-sm pg-tr-save" title="' . h(lang('Save')) . '" aria-describedby="pg_tr_source_' . $row_id . '"><i class="bi bi-check-lg" aria-hidden="true"></i>' . lang('Save') . '</button>
                        <button type="button" class="pg-icon-button pg-icon-button-sm pg-tr-review no-popover' . ($has ? '' : ' d-none') . '" title="' . h($review_label) . '" aria-label="' . h($review_label) . '" data-state="' . h($state) . '"><i class="bi ' . (($state === 'reviewed') ? 'bi-eye-slash' : 'bi-eye') . '" aria-hidden="true"></i></button>
                        <button type="button" class="pg-icon-button pg-icon-button-sm pg-tr-details-toggle no-popover" title="' . h(lang('Details')) . '" aria-label="' . h(lang('Details')) . '" aria-expanded="false" aria-controls="pg_tr_details_' . $row_id . '"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="pg-tr-details" id="pg_tr_details_' . $row_id . '" hidden></div>
            </div>';
}

if (($language !== '') && !$rows) {
    $output_rows = '<div class="py-12 text-center fg-secondary">'
        // No text at all in the page or group, whatever the filter: it has
        // not been scanned yet. A picture enters only once a page showing it
        // is saved in the Visual Page Editor or opened in another language
        // (its file_translation rows), so Update lists none before that.
        . (($scope_stats['total'] === 0)
            ? (($group === 'pictures')
                ? lang('No picture yet. A picture made in the Artifact Canvas whose words are text layers is listed here once a page that shows it is saved in the Visual Page Editor or opened in another language.')
                : lang(array('string' => 'No text has been scanned yet. Press "{var:1}": the pages are read and their texts are listed here.', 'vars' => h($scan_button_label))))
            : lang('No text matches the filter.'))
        . '</div>';
}

// Pagination.
$output_pagination = '';

if ($total_pages > 1) {
    $output_pagination .= '<nav aria-label="' . h(lang('Pagination')) . '"><ul class="pagination pagination-sm mb-0">';

    for ($i = 1; $i <= $total_pages; $i++) {
        if (($total_pages > 12) && ($i > 2) && ($i < $total_pages - 1) && (abs($i - $page_number) > 2)) {
            if (($i === 3) || ($i === $total_pages - 2)) {
                $output_pagination .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }

            continue;
        }

        $output_pagination .= '<li class="page-item' . (($i === $page_number) ? ' active' : '') . '"><a class="page-link"' . (($i === $page_number) ? ' aria-current="page"' : '') . ' href="' . h($url(array('p' => $i))) . '">' . $i . '</a></li>';
    }

    $output_pagination .= '</ul></nav>';
}

// "Activity": every update of the language, 20 a page, newest first. A
// row opens its drawer (the "activity_job" action reads its failed texts).
$job_groups = pg_tr_owner_groups();
$job_page_names = array();

foreach ($pages as $job_page) {
    $job_page_names[(int) $job_page['page_id']] = (string) $job_page['page_name'];
}

$job_people = pg_tr_activity_user_names(array_column($activity['rows'], 'created_by'));
$job_tone = function ($status) {
    return ($status === 'done') ? 'pg-tone-teal' : (in_array($status, array('failed', 'cancelled'), true) ? 'pg-tone-rose' : 'pg-tone-amber');
};
$output_jobs = '';

foreach ($activity['rows'] as $job) {
    $job_total = (int) $job['total'];
    $job_done = (int) $job['done_count'];
    $job_failed = (int) $job['failed_count'];
    $job_open = pg_tr_activity_open($job['status']);
    $job_scope = pg_tr_activity_scope_label($job['scope'], $job_groups, $job_page_names);
    $job_engine = isset($engine_labels[$job['engine']]) ? $engine_labels[$job['engine']] : (string) $job['engine'];

    $output_jobs .= '<tr class="pg-tr-job" data-job-id="' . (int) $job['id'] . '" data-scope-label="' . h($job_scope) . '" data-engine="' . h($job['engine']) . '">'
        . '<td><span class="pg-pill ' . $job_tone($job['status']) . '">' . h(isset($status_labels[$job['status']]) ? $status_labels[$job['status']] : $job['status']) . '</span></td>'
        . '<td><button type="button" class="pg-tr-job-open">' . h($job_scope) . '</button></td>'
        . '<td class="pg-text-2">' . h($job_engine) . '</td>'
        . '<td><span class="pg-tr-job-progress"><span class="text-nowrap">' . h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array(number_format($job_done), number_format($job_total))))) . '</span>'
        . ($job_open ? '<span class="progress" role="presentation"><span class="progress-bar" style="width: ' . (($job_total > 0) ? (int) round(($job_done / $job_total) * 100) : 0) . '%"></span></span>' : '')
        . (($job_failed > 0) ? '<span class="fg-danger text-nowrap">' . h(lang(array('string' => '{var:1} failed', 'vars' => array(number_format($job_failed))))) . '</span>' : '')
        . '</span></td>'
        . '<td>' . h(isset($job_people[(int) $job['created_by']]) ? $job_people[(int) $job['created_by']] : '—') . '</td>'
        . '<td class="pg-text-2 text-nowrap">' . get_relative_time(array('timestamp' => (int) $job['created_at'])) . '</td>'
        . '<td class="pg-text-2 text-nowrap">' . h(pg_tr_activity_duration_text(pg_tr_activity_took($job))) . '</td>'
        . '</tr>';
}

$activity_link = function ($changes) use ($url, $activity_filter, $activity_page) {
    return $url(array_merge(array('tab' => 'activity', 'af' => ($activity_filter === 'all') ? '' : $activity_filter, 'ap' => ($activity_page > 1) ? $activity_page : 0, 'p' => 0), $changes));
};
$output_activity_filter = '';

foreach (array('all' => lang('All'), 'running' => lang('Running'), 'failed' => lang('Failed')) as $filter_key => $filter_label) {
    $output_activity_filter .= '<a class="pg-seg-item' . (($filter_key === $activity_filter) ? ' active" aria-current="true' : '') . '" href="' . h($activity_link(array('af' => ($filter_key === 'all') ? '' : $filter_key, 'ap' => 0))) . '">' . h($filter_label) . '</a>';
}

if ($output_jobs !== '') {
    $activity_from = (($activity_page - 1) * PG_TR_ACTIVITY_PER_PAGE) + 1;
    $activity_to = min($activity['total'], $activity_page * PG_TR_ACTIVITY_PER_PAGE);
    $output_jobs = '<div class="table-responsive"><table class="table table-hover align-middle mb-0 pg-tr-jobs">'
        . '<thead class="pg-table-light"><tr><th scope="col">' . lang('Status') . '</th><th scope="col">' . lang('What') . '</th><th scope="col">' . lang('Engine') . '</th><th scope="col">' . lang('Progress') . '</th><th scope="col">' . lang('By') . '</th><th scope="col">' . lang('Started') . '</th><th scope="col">' . lang('Took') . '</th></tr></thead>'
        . '<tbody>' . $output_jobs . '</tbody></table></div>'
        . '<div class="d-flex align-items-center justify-content-end gap-3 p-3 border-top"><span class="pg-text-2">' . h(lang(array('string' => '{var:1}–{var:2} of {var:3}', 'vars' => array($activity_from, $activity_to, $activity['total'])))) . '</span>'
        . (($activity_page > 1) ? '<a class="pg-icon-button pg-icon-button-sm" href="' . h($activity_link(array('ap' => $activity_page - 1))) . '" aria-label="' . h(lang('Previous')) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>' : '')
        . (($activity_to < $activity['total']) ? '<a class="pg-icon-button pg-icon-button-sm" href="' . h($activity_link(array('ap' => $activity_page + 1))) . '" aria-label="' . h(lang('Next')) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>' : '')
        . '</div>';
} else {
    $output_jobs = '<div class="pg-card-inner m-3"><div class="ws-empty py-5"><i class="bi bi-clock-history" aria-hidden="true"></i>'
        . (($activity_filter === 'all') ? lang('No update has been run for this language yet.') : lang('No update matches this filter.')) . '</div></div>';
}

// "Glossary": one table row a term. Each row carries its values as data
// attributes, so the edit button fills the dialog without a round trip; the
// remove buttons submit the one form below the table (no form in a row).
$output_glossary = '';
$glossary_language = h(pg_tr_language_label($language));

foreach ($glossary as $term) {
    $for_all = ((string) $term['language'] === '');
    $kept = ((int) $term['keep'] === 1);

    $output_glossary .= '<tr class="pg-tr-term"'
        . ' data-id="' . (int) $term['id'] . '" data-term="' . h($term['term']) . '" data-translation="' . h($term['translation']) . '"'
        . ' data-keep="' . (int) $term['keep'] . '" data-case="' . (int) $term['case_sensitive'] . '" data-note="' . h($term['note']) . '" data-all="' . ($for_all ? 1 : 0) . '">'
        . '<td class="text-break"><span class="fw-semibold"' . $text_lang($source) . '>' . h($term['term']) . '</span>'
        . (((int) $term['case_sensitive'] === 1) ? ' <span class="pg-pill pg-tone-gray font-monospace">Aa</span>' : '') . '</td>'
        . '<td class="text-break">' . ($kept ? '<span class="pg-pill pg-tone-gray">' . lang('Keep as written') . '</span>' : '<span' . $text_lang($language) . '>' . h($term['translation']) . '</span>') . '</td>'
        . '<td class="pg-text-2 text-nowrap">' . ($for_all ? lang('Every language') : $glossary_language) . '</td>'
        . '<td class="pg-text-2 text-break">' . h($term['note']) . '</td>'
        . '<td class="text-end text-nowrap">'
        . '<button type="button" class="pg-icon-button pg-icon-button-sm pg-tr-term-edit" data-bs-toggle="dialog" data-bs-target="#pg_tr_glossary_modal" aria-label="' . h(lang('Edit')) . '"><i class="bi bi-pencil" aria-hidden="true"></i></button>'
        . '<button type="submit" form="pg_tr_glossary_delete" name="id" value="' . (int) $term['id'] . '" class="pg-icon-button pg-icon-button-sm fg-danger pg-tr-term-remove" aria-label="' . h(lang('Remove')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
        . '</td>'
        . '</tr>';
}

if ($output_glossary !== '') {
    $output_glossary = '<div class="table-responsive"><table class="table align-middle mb-0 pg-tr-terms">'
        . '<thead class="pg-table-light"><tr><th scope="col">' . lang('Term') . '</th><th scope="col">' . lang('Translation to use') . '</th><th scope="col">' . lang('Language') . '</th><th scope="col">' . lang('Note for the translator') . '</th><th scope="col"><span class="visually-hidden">' . lang('Actions') . '</span></th></tr></thead>'
        . '<tbody>' . $output_glossary . '</tbody></table></div>'
        . '<p class="pg-text-meta px-3 py-3 mb-0" id="pg_tr_term_none" hidden>' . lang('No term matches the search.') . '</p>'
        . '<form method="post" action="translations_action.php" id="pg_tr_glossary_delete" class="d-none" data-confirm="' . h(lang('Remove this term from the glossary?')) . '">'
        . '<input type="hidden" name="action" value="glossary_delete"/>'
        . '<input type="hidden" name="language" value="' . h($language) . '"/>'
        . $view_fields
        . '<input type="hidden" name="tab" value="glossary"/>'
        . get_token_field()
        . '</form>';
} else {
    $output_glossary = '<div class="pg-card-inner m-3"><div class="ws-empty py-5"><i class="bi bi-book" aria-hidden="true"></i>'
        . lang('No term yet. A term with the translation to use, or a name to keep as written: the engines follow the glossary on every text they are sent.') . '</div></div>';
}

$scope = ($page_id > 0) ? 'page:' . $page_id : (($group !== '') ? 'group:' . $group : 'all');

// The machine translations of the scope one click marks reviewed, and the
// ones flagged suspicious it marks only when asked to. With only flagged
// ones left the button still shows: its dialog offers them.
$review_all_count = ($language !== '') ? pg_tr_review_all_count($language, $scope) : 0;
$review_all_flagged = ($language !== '') ? max(0, pg_tr_review_all_count($language, $scope, true) - $review_all_count) : 0;
$review_all_shown = ($review_all_count > 0) ? $review_all_count : $review_all_flagged;

// What "Clear translations" deletes here: the machine translations, and with
// the reviewed ones the whole count. The dialog names the number.
$clear_machine_count = ($language !== '') ? pg_tr_clear_count($language, $scope, false) : 0;
$clear_all_count = ($language !== '') ? pg_tr_clear_count($language, $scope, true) : 0;
$export_base = 'translations_action.php?action=export&language=' . rawurlencode($language) . '&scope=' . rawurlencode($scope) . '&token=' . rawurlencode($token);

// What each engine does, in the dialog under its select; a browser
// engine's note also stands under the automation line, where it names the
// browser's own pages and, for the browser AI, a language its model does not
// write.
$engine_notes = array(
    'manual'    => lang(array('string' => 'This language is translated by hand: "{var:1}" scans the pages and lists the pending texts here.', 'vars' => h($scan_button_label))),
    'chrome'    => lang('The browser engine translates on this computer with the Chrome Translator API (desktop Chrome 138 or Edge 148 and newer). The first run downloads the language model.'),
    'chrome_ai' => lang('The browser AI translates on this computer with the browser\'s built-in model, Gemini Nano (Prompt API, a recent desktop Chrome): one text at a time, with where it stands, the glossary and the style note, keeping inline HTML. A text takes a few seconds; the first run downloads the model.') . ' ' . lang('The model does not officially read every language (Turkish, for one): look its translations over before approving them.'),
    'ai'        => lang('Pinegrap AI translates on the server through the site\'s own model connection, a few texts at a time with their context, the glossary and the style note. The update runs while this screen is open; the scheduled job finishes what is left.'),
    'claude'    => lang('Claude\'s routine in the Workspace is told there is work: it fetches the texts through the API with their context, the glossary and the style note, and posts the translations back. The run shares the routine\'s daily allowance; this screen follows its progress.'),
);
$engine_note = '';

if ($language_row && $engine['ready']) {
    if ($engine_key === 'chrome') {
        $engine_note = '<div class="pg-text-2 small mb-4 px-3 pg-tr-chrome-note"><i class="bi bi-browser-chrome me-1" aria-hidden="true"></i>' . $engine_notes['chrome'] . pg_tr_browser_pages_html() . '</div>';
    } elseif ($engine_key === 'chrome_ai') {
        $engine_note = '<div class="pg-text-2 small mb-4 px-3 pg-tr-chrome-note"><i class="bi bi-cpu me-1" aria-hidden="true"></i>' . $engine_notes['chrome_ai']
            . '<div class="small mt-3">' . lang('The model\'s state is shown at this browser address:') . ' <code class="user-select-all">chrome://on-device-internals</code></div></div>';
    }
}

$output_engine_notes = '';

foreach ($engine_notes as $note_key => $note_text) {
    $output_engine_notes .= '<p class="pg-field-help mb-0" data-pg-tr-engine-note="' . h($note_key) . '"' . (($note_key === $engine_key) ? '' : ' hidden') . '>' . $note_text . '</p>';
}

// The automation line: the engine, the fallback and "Translate on save" to
// read, "Change" to open the dialog. Amber with what is missing while the
// engine is not ready; "By hand" has no fallback and says how texts come in.
$automation_value = function ($label, $value, $tone = 'pg-tone-gray') {
    return '<span class="pg-pill ' . $tone . ' pg-tr-automation-value"><span class="fg-2">' . h($label) . '</span> <b>' . h($value) . '</b></span>';
};
$automation_warn = ($language_row && !$engine['ready']);
$automation_values = $automation_value(lang('Engine'), $engine['label'], $automation_warn ? 'pg-tone-amber' : 'pg-tone-gray');

if ($engine_key !== 'manual') {
    $automation_values .= $automation_value(lang('Fallback'), (($fallback_key !== '') && isset($engines[$fallback_key])) ? $engines[$fallback_key]['label'] : lang('None'));
}

if ($auto_update_known) {
    $automation_values .= $automation_value(lang('Translate on save'), $auto_update_on ? lang('On') : lang('Off'));
}

$output_automation = $language_row ? '<div class="pg-card pg-tr-automation' . ($automation_warn ? ' pg-tr-automation-warn' : '') . '" id="pg_tr_automation">'
    . '<i class="bi ' . ($automation_warn ? 'bi-exclamation-triangle' : (($engine_key === 'manual') ? 'bi-pencil' : 'bi-lightning-charge')) . '" aria-hidden="true"></i>'
    . '<span class="fw-medium">' . lang('Automation') . '</span>' . $automation_values
    . ($automation_warn ? '<span class="pg-tr-automation-reason">' . h($engine['reason']) . '</span>' : '')
    . (($engine_key === 'manual') ? '<span class="pg-text-2 small">' . $engine_notes['manual'] . '</span>' : '')
    . '<span class="pg-toolbar-grow"></span>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="dialog" data-bs-target="#pg_tr_automation_modal">' . lang('Change') . '</button>'
    . '</div>' : '';

// The selected page under the reviewed policy: what it answered the search
// engines when it was last drawn, and on request ("Why?", the blocking
// filter) where the texts that keep it out come from.
$output_index_note = '';

if ($index_watch && $selected_page) {
    $index_state = pg_tr_screen_verdict_state($verdicts, $page_id, (int) $selected_page['noindex'] === 1);
    $index_language = pg_tr_language_label($language);
    $index_why = ($filter !== 'blocking') ? ' <a href="' . h($url(array('filter' => 'blocking', 'p' => 0))) . '">' . lang('Why?') . '</a>' : '';

    // The page's own setting writes noindex whatever its translations, so
    // no translation is named as the reason and no "Why?" is offered.
    if ($index_state === 'own_noindex') {
        $output_index_note = '<div class="small fg-secondary py-3 border-bottom"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => 'Kept out of search results in {var:1} by the page\'s own setting ("Close to Search Engines" in the page properties), whatever its translations.', 'vars' => array($index_language))));
    } elseif ($index_state === 'noindex') {
        $output_index_note = '<div class="alert theme-warning py-3 small mt-5 mb-3"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => 'Kept out of search results in {var:1}: when the page was last drawn, some of the texts on it had no approved translation.', 'vars' => array($index_language)))) . $index_why;
    } elseif ($index_state === 'indexed') {
        $output_index_note = '<div class="small fg-secondary py-3 border-bottom"><i class="bi bi-search me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => 'Search engines may index this page in {var:1}.', 'vars' => array($index_language))));
    } else {
        $output_index_note = '<div class="small fg-secondary py-3 border-bottom"><i class="bi bi-hourglass me-1" aria-hidden="true"></i>'
            . h(lang(array('string' => 'Not checked yet: the page has not been drawn in {var:1} since its translations last changed. It is checked the next time it is opened in this language.', 'vars' => array($index_language))))
            . (($filter !== 'blocking') ? ' <a href="' . h($url(array('filter' => 'blocking', 'p' => 0))) . '">' . lang('Show the texts that would keep it out') . '</a>' : '');
    }

    if ($filter === 'blocking') {
        if ($index_reasons) {
            $output_index_note .= '<div class="mt-3">' . h(lang(array('string' => '{var:1} text(s) here have no translation, or one that is neither approved nor the same as the source text. They come from:', 'vars' => array($total_rows)))) . '</div><ul class="mb-1 ps-5">';

            foreach ($index_reasons as $reason) {
                $output_index_note .= '<li>' . h($index_owner_label($reason['kind'], $reason['name'])) . ' <span class="opacity-75">' . (int) $reason['count'] . '</span></li>';
            }

            $output_index_note .= '</ul>';
        } elseif ($index_state !== 'own_noindex') {
            $output_index_note .= '<div class="mt-3">' . h(lang(array('string' => 'None of the texts last read from this page is pending. Run "{var:1}" so they are read again, then open the page once in this language.', 'vars' => array($scan_button_label)))) . '</div>';
        }

        $output_index_note .= '<div class="mt-1 opacity-75">' . lang('Counted from the texts last read from this page and from what it places: shared components, menus and, on a record\'s own page, the record it shows. Form fields a widget draws count too when the page is drawn. The records a list shows, products and other texts read from the database never keep a page out: they are drawn in the source language until translated.') . '</div>';
    }

    $output_index_note .= '</div>';
}

// The selection: a tick on every row, "Select all on this page" above the
// rows, and the bar that rises at the foot of the list once a row is ticked
// with what can be done to the ticked texts. "Translate again" needs an
// engine to send to: with "By hand" or an engine not ready it says why.
$again_reason = ($engine['kind'] === 'panel')
    ? lang('This language is translated by hand: there is no engine to send the texts to.')
    : ($engine['ready'] ? '' : (string) $engine['reason']);

$output_bulk = $rows ? '<div class="pg-tr-bulk">
                            <input class="check pg-tr-pick-all" type="checkbox" id="pg_tr_pick_all"/>
                            <label class="mb-0" for="pg_tr_pick_all">' . lang('Select all on this page') . '</label>
                        </div>' : '';

$output_selection_bar = $rows ? '<div class="pg-tr-selbar-wrap">
                        <div class="pg-tr-selbar" id="pg_tr_selbar" role="region" aria-label="' . h(lang('Selected texts')) . '" hidden>
                            <span class="pg-tr-selbar-count" aria-live="polite"></span>
                            <button type="button" class="pg-button pg-button-quiet pg-button-sm no-popover pg-tr-sel-approve" title="' . h(lang('Approve')) . '"><i class="bi bi-check2" aria-hidden="true"></i><span class="pg-tr-selbar-label">' . lang('Approve') . '</span></button>
                            <button type="button" class="pg-button pg-button-quiet pg-button-sm no-popover pg-tr-sel-again" title="' . h(($again_reason !== '') ? $again_reason : lang('Translate again')) . '"' . (($again_reason !== '') ? ' disabled="disabled"' : '') . '><i class="bi bi-arrow-repeat" aria-hidden="true"></i><span class="pg-tr-selbar-label">' . lang('Translate again') . '</span></button>
                            <button type="button" class="pg-button pg-button-quiet pg-button-sm no-popover pg-tr-sel-keep pg-tr-selbar-wide"><i class="bi bi-check2-square" aria-hidden="true"></i>' . lang('Keep as in the source and approve') . '</button>
                            <button type="button" class="pg-button pg-button-ghost pg-button-sm no-popover pg-tr-sel-clear pg-tr-selbar-wide">' . lang('Clear') . '</button>
                            <div class="pg-menu-wrap pg-tr-selbar-narrow">
                                <button class="pg-icon-button pg-icon-button-sm no-popover" type="button" data-bs-toggle="menu" data-bs-placement="top-end" aria-expanded="false" aria-label="' . h(lang('More')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                                <ul class="menu pg-menu-end">
                                    <li><button type="button" class="menu-item pg-tr-sel-keep"><i class="bi bi-check2-square me-3" aria-hidden="true"></i>' . lang('Keep as in the source and approve') . '</button></li>
                                    <li><button type="button" class="menu-item pg-tr-sel-clear"><i class="bi bi-eraser me-3" aria-hidden="true"></i>' . lang('Clear') . '</button></li>
                                </ul>
                            </div>
                            <button type="button" class="pg-icon-button pg-icon-button-sm no-popover pg-tr-sel-none" title="' . h(lang('Take the ticks off')) . '" aria-label="' . h(lang('Take the ticks off')) . '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </div>
                    </div>' : '';

$config = array(
    'action_url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/translations_action.php',
    'token'      => $token,
    'language'   => $language,
    'source'     => $source,
    'engine'     => $engine_key,
    'engine_ready' => (bool) $engine['ready'],
    'engine_label' => (string) $engine['label'],
    // What the browser's language model is told before the texts: the
    // session is made in the click itself, before the first package.
    'ai_instructions' => (($engine_key === 'chrome_ai') || ($open_job && ($open_job['engine'] === 'chrome_ai'))) ? pg_tr_ai_instructions($source, $language, 'item') : '',
    'scope'      => $scope,
    'open_job'   => $open_job ? array('id' => (int) $open_job['id'], 'engine' => $open_job['engine'], 'status' => $open_job['status'], 'total' => (int) $open_job['total'], 'done' => (int) $open_job['done_count'], 'failed' => (int) $open_job['failed_count'], 'scope_label' => pg_tr_activity_scope_label($open_job['scope'], $job_groups, $job_page_names)) : null,
    // The progress card names what an update covers and its engine; the
    // drawer's retry offers the fallback engine unless the update ran on it.
    'scope_label' => pg_tr_activity_scope_label($scope, $job_groups, $job_page_names),
    'engine_labels' => $engine_labels,
    'engine_kinds' => array_map(function ($spec) { return $spec['kind']; }, $engines),
    'fallback'   => $fallback_key,
    'tab_url'    => $url(array('p' => 0)),
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
        'scanning_progress' => lang('Scanning the pages… {var} of {var2}'),
        'nothing_pending'   => lang('Every text already has a translation: nothing was sent.'),
        'pending_by_hand'   => lang('{var} text(s) are pending. Translate them below or choose an engine under Automation.'),
        'scanned'           => lang('Scanned: {var} text(s) are pending.'),
        'scanned_none'      => lang('Scanned: every text has a translation.'),
        'sent'              => lang('{var} text(s) sent to the engine.'),
        'progress'          => lang('{var} of {var2} translated'),
        'finished'          => lang('Finished: {var} translated, {var2} failed. The screen reloads.'),
        'translator_missing' => lang('This browser has no Translator API. Use desktop Chrome 138 or Edge 148 and newer, or choose another engine.'),
        'translator_unavailable' => lang('The browser cannot translate from {var} to {var2}.'),
        'downloading'       => lang('Downloading the language model… {var}%'),
        'preparing'         => lang('Preparing the browser translator…'),
        'model_preparing'   => lang('Preparing the browser\'s AI model…'),
        'translator_stalled' => lang('The browser did not get its translator ready for {var} to {var2}: its translation service or language pack is not available. Try again later or choose another engine.'),
        'model_stalled'     => lang('The browser did not get its AI model ready: the model is not available on this computer. Try again later or choose another engine.'),
        'stopped'           => lang('The update was stopped.'),
        'model_missing'     => lang('This browser has no built-in AI model (Prompt API). Use a recent desktop Chrome, or choose another engine.'),
        'model_unavailable' => lang('The browser\'s AI model cannot write {var}.'),
        'model_fallback'    => lang('The browser\'s AI model does not write {var} on this computer: this language is translated with the browser\'s translation model (Translator API) instead, in runs of plain text, without the context and the style note.'),
        'network_error'     => lang('The request failed. Please try again.'),
        'confirm_cancel'    => lang('Stop the running update?'),
        'queue_waiting'     => lang('Waiting for Claude to pick the job up…'),
        'queue_held'        => lang('Claude is paused: {var}'),
        'glossary_add'      => lang('Add a term'),
        'glossary_edit'     => lang('Edit the term'),
        'review_all_count'  => lang('{var} translation(s) will be marked as reviewed.'),
        'clear_count'       => lang('{var} translation(s) will be deleted.'),
        'select_text'       => lang('Select this text'),
        'retrying'          => lang('The server did not answer in time; trying again…'),
        'accept_count'      => lang('{var} text(s) will be approved as written in the source language.'),
        'suspicious_same'   => lang('The translation is the same as the source text.'),
        'suspicious_length' => lang('The length of the translation is out of proportion to the source text.'),
        'details_used'      => lang('Used in:'),
        'details_earlier'   => lang('Earlier wording:'),
        'details_more'      => lang('and {var} more'),
        'details_unused'    => lang('No page shows it now.'),
        'keep_source'       => lang('Keep as in the source and approve'),
        'again'             => lang('Translate again'),
        'again_count'       => lang('{var} text(s) will be sent to {var2} again.'),
        'again_reason'      => $again_reason,
        'selected'          => lang('{var} selected'),
        'show_all'          => lang('Show all'),
        'show_less'         => lang('Show less'),
        'translating'       => lang('Translating "{var}" with {var2}…'),
        'automation_saved'  => lang('The automation of this language was saved.'),
        'job_title'         => lang('Update of "{var}"'),
        'translated'        => lang('Translated'),
        'skipped'           => lang('Skipped'),
        'engine_label'      => lang('Engine'),
        'started'           => lang('Started'),
        'finished_label'    => lang('Finished'),
        'not_yet'           => lang('Not yet'),
        'failed_texts'      => lang('Failed texts'),
        'none_so_far'       => lang('None so far.'),
        'none_failed'       => lang('No text failed.'),
        'open_text'         => lang('Open the text'),
        'no_reason'         => lang('The engine gave no reason.'),
        'loading'           => lang('Loading…'),
        'retry_none'        => lang('The failed texts have been translated since: nothing was sent.'),
        'status_labels'     => $status_labels,
    ),
);

echo pg_page_shell(array(
    'title'               => lang('Translations'),
    'extra classes'       => 'translations pg-measure-list',
    'icon'                => 'settings',
    'heading'             => lang('Translations'),
    'heading_description' => lang('The pages of the designs in the other languages of the site: scanned, translated, reviewed.'),
)) . '
<main id="content" class="container-fluid">
    <style>@layer custom {
    /* The source text is read, the translation is typed: the source gets the
       quiet treatment and keeps the line breaks of a multi-line text. */
    .pg-tr-source { white-space: pre-wrap; word-break: break-word; font-size: var(--pg-text-base); line-height: 1.5; }
    .pg-tr-inline { font-family: var(--pg-font-mono); font-size: var(--pg-text-2); color: var(--pg-fg-2); }
    /* A long source shows four lines until "Show all". */
    .pg-tr-clamp { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 4; line-clamp: 4; overflow: hidden; }
    .pg-tr-show-all { padding: 0; margin-top: 2px; border: 0; background: none; color: var(--pg-accent); font-size: var(--pg-text-meta); }
    /* One grid for every row: tick 28 px, the source and the translation in
       halves, the state column 176 px. */
    .pg-tr-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) minmax(0, 1fr) 176px; column-gap: 16px; align-items: start; padding: 14px 0; }
    .pg-tr-row + .pg-tr-row { border-top: 1px solid var(--pg-border-color); }
    .pg-tr-tick { display: flex; padding-top: 10px; }
    .pg-tr-src { padding-top: 8px; }
    .pg-tr-marks { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 6px; min-height: 20px; }
    .pg-tr-hash { font-size: var(--pg-text-meta); color: var(--pg-fg-3); }
    textarea.pg-input.pg-tr-target { min-height: 39px; max-height: 24rem; padding: 8px 12px; resize: none; field-sizing: content; }
    .pg-tr-why { display: flex; gap: 6px; align-items: baseline; }
    .pg-tr-why[hidden] { display: none; }
    .pg-tr-state { display: flex; flex-direction: column; align-items: flex-start; gap: 5px; padding-top: 8px; min-width: 0; }
    .pg-tr-engine { max-width: 100%; padding-left: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pg-tr-engine:empty { display: none; }
    .pg-tr-acts { display: flex; align-items: center; gap: 4px; margin-top: 3px; }
    .pg-tr-details-toggle[aria-expanded="true"] > .bi { transform: rotate(180deg); }
    .pg-tr-row.pg-tr-dirty .pg-tr-save { color: var(--pg-accent); background: color-mix(in srgb, var(--pg-accent) 9%, transparent); box-shadow: inset 0 0 0 1px var(--pg-accent); }
    /* "Details" grows the row downward under the source and the translation. */
    .pg-tr-details { grid-column: 2 / 5; display: grid; grid-template-columns: 124px minmax(0, 1fr); gap: 9px 12px; margin-top: 12px; padding: 12px 14px; border-radius: 12px; background: var(--pg-quiet); box-shadow: inset 0 0 0 1px var(--pg-border-color); font-size: var(--pg-text-2); }
    .pg-tr-details[hidden] { display: none; }
    .pg-tr-details-key { color: var(--pg-fg-2); }
    .pg-tr-details-value { display: flex; flex-wrap: wrap; gap: 4px 16px; min-width: 0; }
    .pg-tr-details-value .bi { margin-right: 6px; }
    .pg-tr-details-arrow { margin: 0 6px; color: var(--pg-fg-3); }
    .pg-tr-details-reason { grid-column: 1 / -1; display: flex; gap: 7px; align-items: baseline; color: var(--pg-tone-rose-text); }
    .pg-tr-details-actions { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; padding-top: 3px; }
    /* At the width of a phone a row stacks: the tick and the source, then the
       box, then the state on one line with the actions at its end. */
    @media (max-width: 767.98px) {
        .pg-tr-row { grid-template-columns: 28px minmax(0, 1fr); row-gap: 10px; }
        .pg-tr-src { padding-top: 0; }
        .pg-tr-tick { padding-top: 2px; }
        .pg-tr-text, .pg-tr-state, .pg-tr-details { grid-column: 1 / -1; }
        .pg-tr-state { flex-direction: row; flex-wrap: wrap; align-items: center; padding-top: 0; gap: 8px; }
        .pg-tr-acts { margin: 0 0 0 auto; gap: 6px; }
        .pg-tr-details { grid-template-columns: minmax(0, 1fr); margin-top: 0; }
    }
    /* The selection: "Select all on this page" above the rows, and the bar
       that rises at the foot of the list once a row is ticked and stays at
       the lower edge of the window while the list scrolls. */
    .pg-tr-bulk { display: flex; align-items: center; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--pg-border-color); font-size: var(--pg-text-2); color: var(--pg-fg-2); }
    .pg-tr-selbar-wrap { position: sticky; bottom: 0; z-index: 3; padding-top: 2px; }
    .pg-tr-selbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: max-content; max-width: 100%; min-height: 48px; margin: 0 auto 12px; padding: 6px 9px 6px 16px; border-radius: 14px; background: var(--pg-surface-float); box-shadow: inset 0 0 0 1px var(--pg-accent), var(--pg-shadow-float); font-size: var(--pg-text-2); }
    .pg-tr-selbar[hidden] { display: none; }
    .pg-tr-selbar-count { margin-right: 6px; font-weight: var(--pg-weight-strong); white-space: nowrap; font-variant-numeric: tabular-nums; }
    .pg-tr-selbar-narrow { display: none; }
    /* On a phone "Approve" and "Translate again" are icons; the rest goes
       under "More". */
    @media (max-width: 767.98px) {
        .pg-tr-selbar-label, .pg-tr-selbar-wide { display: none; }
        .pg-tr-selbar-narrow { display: block; }
    }
    /* The head: the language menu lists each language with its share. */
    .pg-tr-language-menu { min-width: 18rem; }
    .pg-tr-share { min-width: 2.5rem; text-align: end; }
    /* The automation line, and the progress card that takes its place while
       an update runs: one line of 46 px, radius 14. */
    .pg-tr-automation, .pg-tr-progress { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; min-height: 46px; margin-bottom: 1rem; padding: 8px 9px 8px 14px; border-radius: 14px; }
    .pg-tr-automation > .bi { color: var(--pg-fg-2); }
    .pg-tr-automation-warn { background: color-mix(in srgb, var(--pg-tone-amber) 7%, var(--pg-surface-1)); border-color: color-mix(in srgb, var(--pg-tone-amber) 28%, transparent); }
    .pg-tr-automation-warn > .bi, .pg-tr-automation-reason { color: color-mix(in srgb, var(--pg-tone-amber) 80%, var(--pg-fg-emphasis)); font-size: var(--pg-text-2); }
    .pg-tr-automation[hidden], .pg-tr-progress { display: none; }
    .pg-tr-progress.show { display: flex; }
    .pg-tr-progress-meter { flex: 1 1 60px; min-width: 60px; height: 4px; }
    /* "Activity": a row opens the drawer of its update. */
    .pg-tr-job { cursor: pointer; }
    .pg-tr-term-find { width: 360px; max-width: 100%; }
    .pg-tr-job-open { padding: 0; border: 0; background: none; color: inherit; font-weight: var(--pg-weight-strong); text-align: start; }
    .pg-tr-job-progress { display: inline-flex; align-items: center; gap: 8px; }
    .pg-tr-job-progress .progress { width: 80px; height: 4px; }
    .pg-tr-job-facts { --pg-figures: 3; gap: 10px; }
    .pg-tr-failed + .pg-tr-failed { border-top: 1px solid var(--pg-border-color); }
    .pg-tr-failed-source { display: -webkit-box; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .pg-tr-failed-reason { display: flex; flex-wrap: wrap; gap: 7px; align-items: baseline; color: var(--pg-tone-rose-text); font-size: var(--pg-text-2); }
    /* The rail beside the main column: one card that stays in place while
       the list scrolls, and scrolls on its own when it is longer than the
       window. Under 992 px it stands above the list. */
    .pg-tr-body { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 20px; align-items: start; }
    .pg-tr-rail { position: sticky; top: 1rem; max-height: calc(100vh - 2rem); overflow-y: auto; padding: 10px; }
    .pg-tr-rail-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; padding: 14px 8px 4px; color: var(--pg-fg-3); }
    .pg-tr-rail-row { position: relative; display: flex; align-items: center; gap: 8px; min-height: 42px; padding: 6px 8px 9px; border-radius: 9px; color: var(--pg-fg-body); text-decoration: none; }
    .pg-tr-rail-row:hover { background: var(--pg-quiet); }
    .pg-tr-rail-row.active { background: color-mix(in srgb, var(--pg-accent) 10%, transparent); box-shadow: inset 3px 0 0 var(--pg-accent); }
    .pg-tr-rail-row > .bi { flex: none; color: var(--pg-fg-2); }
    .pg-tr-rail-name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pg-tr-rail-count { flex: none; color: var(--pg-fg-2); font-size: var(--pg-text-meta); }
    .pg-tr-rail-mark { flex: none; font-size: var(--pg-text-meta); }
    .pg-tr-rail-bar { position: absolute; left: 8px; right: 8px; bottom: 3px; height: 3px; border-radius: 2px; background: var(--pg-quiet); overflow: hidden; }
    .pg-tr-rail-bar > span { display: block; height: 100%; background: var(--pg-accent); }
    /* The filter tiles: six of equal width; the chosen one framed in the accent. */
    .pg-tr-tiles { grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
    .pg-tr-tile { color: var(--pg-fg-body); text-decoration: none; border-radius: 12px; }
    .pg-tr-tile:hover { box-shadow: inset 0 0 0 1px var(--pg-border-color); }
    .pg-tr-tile.active { box-shadow: inset 0 0 0 1.5px var(--pg-accent); background: color-mix(in srgb, var(--pg-accent) 6%, transparent); }
    .pg-tr-dot { flex: none; width: 6px; height: 6px; border-radius: 50%; background: var(--pg-tone); }
    .pg-tr-tools { padding: 14px 18px; }
    .pg-tr-search { flex: 0 1 26rem; min-width: 12rem; }
    /* The rows, notes and bars of the list keep 18 px from the sides of the card while
       the rules between the rows run its full width. */
    .pg-tr-list > * { padding-inline: 18px; }
    .pg-tr-scope, .pg-tr-rail-backdrop, .pg-tr-more-scan { display: none; }
    @media (max-width: 1199.98px) {
        .pg-tr-body { grid-template-columns: 240px minmax(0, 1fr); }
        .pg-tr-tiles { gap: 8px; }
        .pg-tr-tile { padding: 12px 12px 11px; }
        .pg-tr-tile .pg-figure-label { align-items: flex-start; }
    }
    @media (max-width: 991.98px) {
        .pg-tr-body { grid-template-columns: minmax(0, 1fr); }
        .pg-tr-rail { position: static; max-height: 20rem; }
    }
    /* A phone: the language button and "More" on the first line, the tabs on
       the second, the fill button the full width under them; "Scan for new
       texts" beside an engine moves into "More". The rail becomes the scope
       button that opens its list in a sheet from the lower edge; the tiles
       are one row that scrolls sideways. */
    @media (max-width: 767.98px) {
        .pg-tr-head { gap: 10px; }
        .pg-tr-head > .pg-toolbar-grow, .pg-tr-head > .pg-tr-more { order: 1; }
        .pg-tr-head > .pg-seg { order: 2; display: flex; flex: 1 1 100%; }
        .pg-tr-head > .pg-seg > .pg-seg-item { flex: 1 1 0; justify-content: center; }
        .pg-tr-head > #pg_tr_update, .pg-tr-head > #pg_tr_scan { order: 3; flex: 1 1 100%; justify-content: center; }
        .pg-tr-head > #pg_tr_scan.pg-button-quiet { display: none; }
        .pg-tr-more-scan { display: list-item; list-style: none; }
        .pg-tr-scope { display: flex; align-items: center; gap: 8px; width: 100%; min-height: 44px; padding: 8px 14px; border: 0; color: var(--pg-fg-body); text-align: start; }
        .pg-tr-scope > .pg-tr-rail-name { flex: 1 1 auto; }
        .pg-tr-scope > .bi:first-child { color: var(--pg-fg-2); }
        .pg-tr-rail { display: none; position: fixed; inset: auto 0 0; z-index: 1056; max-height: 75vh; padding: 8px 12px calc(12px + env(safe-area-inset-bottom)); border-radius: 16px 16px 0 0; box-shadow: var(--pg-shadow-float); }
        .pg-tr-rail.show { display: block; }
        .pg-tr-rail::before { content: ""; display: block; width: 36px; height: 4px; margin: 0 auto 10px; border-radius: 2px; background: var(--pg-border-color); }
        .pg-tr-rail-row { min-height: 44px; }
        .pg-tr-rail-backdrop.show { display: block; position: fixed; inset: 0; z-index: 1055; background: color-mix(in srgb, var(--pg-fg-emphasis) 35%, transparent); }
        .pg-tr-tiles { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; margin-inline: -4px; padding: 2px 4px; }
        .pg-tr-tiles > .pg-tr-tile { flex: 0 0 42%; scroll-snap-align: start; }
        .pg-tr-tiles > .pg-figure:last-child:nth-child(odd) { grid-column: auto; }
        .pg-tr-list > * { padding-inline: 14px; }
    }
    }</style>
    ' . $liveform_messages . '
    <div id="pg_tr_app" data-config="' . h(encode_json($config)) . '">
        <div class="pg-toolbar pg-tr-head d-flex flex-wrap align-items-center gap-3 mb-4">' . ($targets ? '
            <div class="pg-menu-wrap">
                <button class="pg-button pg-button-fill pg-menu-caret no-popover" type="button" data-bs-toggle="menu" aria-expanded="false">
                    <i class="bi bi-translate" aria-hidden="true"></i><span' . $text_lang($language) . '>' . h(pg_tr_language_label($language)) . '</span> <span class="opacity-75 font-monospace">' . h($language) . '</span>
                </button>
                <ul class="menu pg-tr-language-menu">' . $output_language_menu . '</ul>
            </div>
            <div class="pg-seg" role="tablist" aria-label="' . h(lang('Translations')) . '">
                <button type="button" class="pg-seg-item' . (($tab === 'texts') ? ' active' : '') . '" role="tab" data-bs-toggle="tab" data-bs-target="#pg_tr_pane_texts" id="pg_tr_tab_texts" aria-controls="pg_tr_pane_texts" aria-selected="' . (($tab === 'texts') ? 'true' : 'false') . '" data-pg-tr-tab="texts">' . lang('Texts') . '</button>
                <button type="button" class="pg-seg-item' . (($tab === 'activity') ? ' active' : '') . '" role="tab" data-bs-toggle="tab" data-bs-target="#pg_tr_pane_activity" id="pg_tr_tab_activity" aria-controls="pg_tr_pane_activity" aria-selected="' . (($tab === 'activity') ? 'true' : 'false') . '" data-pg-tr-tab="activity">' . lang('Activity') . (($activity_count > 0) ? ' <span class="pg-seg-count">' . $activity_count . '</span>' : '') . '</button>
                <button type="button" class="pg-seg-item' . (($tab === 'glossary') ? ' active' : '') . '" role="tab" data-bs-toggle="tab" data-bs-target="#pg_tr_pane_glossary" id="pg_tr_tab_glossary" aria-controls="pg_tr_pane_glossary" aria-selected="' . (($tab === 'glossary') ? 'true' : 'false') . '" data-pg-tr-tab="glossary">' . lang('Glossary') . (count($glossary) ? ' <span class="pg-seg-count">' . count($glossary) . '</span>' : '') . '</button>
            </div>
            <span class="pg-toolbar-grow"></span>' . (($engine_key !== 'manual') ? '
            <button type="button" class="pg-button pg-button-fill no-popover" id="pg_tr_update"' . ($engine['ready'] ? '' : ' disabled="disabled"') . ' data-bs-toggle="tooltip" title="' . h($update_button_title) . '">
                <i class="bi bi-play-fill" aria-hidden="true"></i>' . h($update_button_label) . ' <span class="pg-count pg-tr-pending-count">' . number_format((int) $scope_stats['pending']) . '</span>
            </button>' : '') . '
            <button type="button" class="pg-button ' . (($engine_key === 'manual') ? 'pg-button-fill' : 'pg-button-quiet') . ' no-popover" id="pg_tr_scan" data-bs-toggle="tooltip" title="' . h($scan_button_title) . '">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . h($scan_button_label) . '
            </button>
            <div class="pg-menu-wrap pg-tr-more">
                <button class="pg-icon-button no-popover" type="button" data-bs-toggle="menu" data-bs-placement="bottom-end" aria-expanded="false" aria-label="' . h(lang('More')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                <ul class="menu pg-menu-end">' . (($engine_key !== 'manual') ? '
                    <li class="pg-tr-more-scan"><button type="button" class="menu-item" data-pg-tr-scan-proxy="1"><i class="bi bi-arrow-repeat me-3" aria-hidden="true"></i>' . h($scan_button_label) . '</button></li>
                    <li class="pg-tr-more-scan"><hr class="menu-divider"></li>' : '') . '
                    <li><a class="menu-item" href="' . h($export_base) . '"><i class="bi bi-download me-3" aria-hidden="true"></i>' . lang('Export CSV') . '</a></li>
                    <li><a class="menu-item" href="' . h($export_base . '&pending=1') . '"><i class="bi bi-download me-3" aria-hidden="true"></i>' . lang('Export pending texts as CSV') . '</a></li>
                    <li>' . pg_list_excel_link($export_base, 'menu-item') . '</li>
                    <li><button type="button" class="menu-item" data-bs-toggle="dialog" data-bs-target="#pg_tr_import"><i class="bi bi-upload me-3" aria-hidden="true"></i>' . lang('Import CSV') . '</button></li>
                    <li><hr class="menu-divider"></li>
                    <li><button type="button" class="menu-item fg-danger" data-bs-toggle="dialog" data-bs-target="#pg_tr_clear_modal"' . (($clear_all_count > 0) ? '' : ' disabled="disabled"') . '><i class="bi bi-eraser me-3" aria-hidden="true"></i>' . lang('Clear translations') . '</button></li>
                    <li><hr class="menu-divider"></li>
                    <li><a class="menu-item" href="' . h(pg_settings_link('languages', 'pgset-languages')) . '"><i class="bi bi-gear me-3" aria-hidden="true"></i>' . lang('Languages and Translation') . '</a></li>
                </ul>
            </div>' : '
            <a class="pg-button pg-button-fill" href="' . h(pg_settings_link('languages', 'pgset-languages')) . '"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . lang('Add a language') . '</a>
            <span class="pg-text-2">' . lang('No target language yet. Add one in Settings > Languages and Translation; the pages of the designs are then served under its address prefix as well.') . '</span>') . '
        </div>
        ' . ($targets ? '
        ' . (($language_row && !$language_served) ? '<div class="alert theme-secondary py-3 px-5 small mb-4" id="pg_tr_not_served"><i class="bi bi-eye-slash me-2" aria-hidden="true"></i>' . lang('This language is not served yet: visitors do not see it.') . ' <a href="' . h(pg_settings_link('languages', 'pgset-languages')) . '">' . lang('Languages and Translation') . '</a></div>' : '') . '
        ' . $output_automation . '
        <div class="pg-card pg-tr-progress" id="pg_tr_progress" role="status">
            <span class="spinner-border spinner-border-sm flex-shrink-0" aria-hidden="true"></span>
            <span class="fw-medium pg-tr-progress-title"></span>
            <span class="pg-text-2 small pg-tr-progress-text"></span>
            <span class="progress pg-tr-progress-meter" role="presentation"><span class="progress-bar pg-tr-progress-bar" style="width: 0%"></span></span>
            <button type="button" class="pg-button pg-button-quiet pg-button-sm pg-tr-cancel">' . lang('Stop') . '</button>
            <a class="pg-text-2 small pg-tr-progress-open" href="' . h($url(array('tab' => 'activity', 'p' => 0))) . '" hidden>' . lang('Open in Activity') . '</a>
        </div>
        ' . $engine_note . '
        <div class="tab-content">
        <div class="tab-pane fade' . (($tab === 'texts') ? ' show active' : '') . '" id="pg_tr_pane_texts" role="tabpanel" aria-labelledby="pg_tr_tab_texts">
        <div class="pg-tr-body">
            <button type="button" class="pg-card pg-tr-scope" aria-controls="pg_tr_rail" aria-expanded="false">' . (($rail_current && ($rail_current['icon'] !== '')) ? '<i class="bi ' . h($rail_current['icon']) . '" aria-hidden="true"></i>' : '') . '<span class="pg-tr-rail-name">' . ($rail_current ? $rail_current['name'] : h(lang('All texts'))) . '</span>' . ($rail_current ? '<span class="pg-tr-rail-count pg-tabular">' . $rail_current['count'] . '</span>' : '') . '<i class="bi bi-chevron-down" aria-hidden="true"></i></button>
            <div class="pg-tr-rail-backdrop" aria-hidden="true"></div>
            <nav class="pg-card pg-tr-rail" id="pg_tr_rail" aria-label="' . h(lang('Pages')) . '">
                <label class="form-control form-control-sm form-adorn rounded-pill mb-3">
                    <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-ghost" id="pg_tr_find_page" placeholder="' . h(lang('Find a page')) . '" aria-label="' . h(lang('Find a page')) . '" autocomplete="off"/>
                </label>
                ' . $output_all_row . '
                <div class="pg-tr-rail-head"><h2 class="pg-text-meta fw-medium mb-0">' . lang('Pages') . '</h2><span><span class="pg-tr-reviewed-total">' . (int) $site_stats['reviewed'] . '</span> ' . lang('reviewed') . '</span></div>
                <div class="pg-tr-rail-pages">' . $output_pages . '</div>
                <p class="pg-text-meta px-3 py-2 mb-0" id="pg_tr_find_none" hidden>' . lang('No page matches the search.') . '</p>' . (($output_groups !== '') ? '
                <div class="pg-tr-rail-head"><h2 class="pg-text-meta fw-medium mb-0">' . lang('Other texts') . '</h2></div>
                ' . $output_groups : '') . '
            </nav>
            <div class="pg-tr-main min-w-0">
                <div class="d-flex flex-wrap align-items-baseline gap-3 mb-3">
                    <h2 class="pg-title-section mb-0">' . ($selected_page ? h($selected_page['page_name']) : (($group !== '') ? h($owner_groups[$group]['label']) : lang('All texts'))) . '</h2>
                    <span class="pg-text-2">' . h(lang(array('string' => '{var:1} text(s)', 'vars' => array($total_rows)))) . '</span>' . (($selected_page && $language_served) ? '
                    <a class="ms-auto pg-text-2" href="' . h(PATH . pg_tr_prefix($language) . '/' . (($selected_page['page_home'] === 'yes') ? '' : encode_url_path($selected_page['page_name']))) . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> ' . lang('Open the page') . '</a>' : '') . '
                </div>
                <nav class="pg-figures pg-tr-tiles" aria-label="' . h(lang('Filter')) . '">' . $output_tiles . '</nav>
                <div class="pg-card mb-5">
                    <div class="pg-tr-tools d-flex flex-wrap align-items-center gap-3">
                        <form class="pg-tr-search" method="get" action="' . h($self) . '" role="search">
                            <input type="hidden" name="language" value="' . h($language) . '"/>
                            <input type="hidden" name="page_id" value="' . (int) $page_id . '"/>
                            <input type="hidden" name="group" value="' . h($group) . '"/>
                            <input type="hidden" name="filter" value="' . h($filter) . '"/>
                            <label class="form-control form-control-sm form-adorn rounded-pill">
                                <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                                <input type="search" name="q" class="form-ghost" id="pg_tr_search" value="' . h($search) . '" placeholder="' . h(lang('Search in the texts')) . '" aria-label="' . h(lang('Search in the texts')) . '" aria-keyshortcuts="/" autocomplete="off"/>
                            </label>
                        </form>
                        <span class="pg-toolbar-grow"></span>' . (($picture_queue_count > 0) ? '
                        <a class="pg-button pg-button-quiet pg-button-sm" href="' . h($url(array('draw' => 1, 'p' => 0))) . '"><i class="bi bi-brush" aria-hidden="true"></i>' . lang('Draw the waiting pictures') . ' <span class="pg-count">' . (int) $picture_queue_count . '</span></a>' : '') . '
                        <button type="button" class="pg-button pg-button-quiet no-popover' . (($review_all_shown > 0) ? '' : ' d-none') . '" id="pg_tr_review_all" data-bs-toggle="tooltip" title="' . h(lang('Mark every machine translation here as reviewed in one go')) . '">
                            <i class="bi bi-check2-all" aria-hidden="true"></i>' . lang('Approve all') . ' <span class="pg-count pg-tr-review-all-shown">' . (int) $review_all_shown . '</span>
                        </button>
                    </div>
                    <div class="card-body pt-0 px-0 pg-tr-list">' . $output_index_note . (($group === 'ui') ? '<div class="small fg-secondary py-3 border-bottom"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . lang('The texts of the cookie window: the notice, its settings and what each cookie is for. This language has no language file, so the window is translated here; until a text is translated, the English wording is shown. The rest of the software\'s wording is not translated here.') . '</div>' : '')
                        . (($group === 'pictures') ? '<div class="small fg-secondary py-3 border-bottom"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . lang('The texts of pictures made in the Artifact Canvas whose words are text layers. A picture is listed once a page that shows it is saved in the Visual Page Editor or opened in another language; a photo or an uploaded picture whose words are in its pixels is not. Once all of a language\'s texts are translated, its picture is drawn by itself: by an open panel, or, with nobody in the panel, by the server where it can. "Draw the waiting pictures" draws them now.') . '</div>' : '')
                        . $output_bulk . $output_rows . $output_selection_bar . '
                        ' . ($output_pagination !== '' ? '<div class="d-flex justify-content-center pt-5">' . $output_pagination . '</div>' : '') . '
                    </div>
                </div>
            </div>
        </div>
        </div>
        <div class="tab-pane fade' . (($tab === 'activity') ? ' show active' : '') . '" id="pg_tr_pane_activity" role="tabpanel" aria-labelledby="pg_tr_tab_activity">
            <div class="pg-seg mb-3" aria-label="' . h(lang('Filter')) . '">' . $output_activity_filter . '</div>
            <section class="pg-card overflow-hidden mb-5" id="pg_tr_jobs" aria-label="' . h(lang('Activity')) . '">' . $output_jobs . '</section>
        </div>
        <div class="tab-pane fade' . (($tab === 'glossary') ? ' show active' : '') . '" id="pg_tr_pane_glossary" role="tabpanel" aria-labelledby="pg_tr_tab_glossary">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">' . (($glossary !== array()) ? '
                <label class="form-control form-control-sm form-adorn rounded-pill pg-tr-term-find">
                    <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-ghost" id="pg_tr_find_term" placeholder="' . h(lang('Search the glossary')) . '" aria-label="' . h(lang('Search the glossary')) . '" autocomplete="off"/>
                </label>' : '') . '
                <button type="button" class="pg-button pg-button-fill no-popover ms-auto pg-tr-term-add" data-bs-toggle="dialog" data-bs-target="#pg_tr_glossary_modal"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . lang('Add a term') . '</button>
            </div>
            <section class="pg-card overflow-hidden mb-5" id="pg_tr_glossary" aria-label="' . h(lang('Glossary')) . '">' . $output_glossary . '</section>
        </div>
        </div>
        <dialog class="dialog" id="pg_tr_import" aria-labelledby="pg_tr_import_title">
                <form class="d-contents" method="post" action="translations_action.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="import"/>
                    <input type="hidden" name="language" value="' . h($language) . '"/>
                    ' . $view_fields . '
                    ' . get_token_field() . '
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_import_title">' . lang('Import CSV') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <div class="mb-5">
                            <label class="form-label" for="pg_tr_import_file">' . lang('CSV file') . '</label>
                            <input type="file" class="form-control" id="pg_tr_import_file" name="csv" accept=".csv,text/csv,' . PG_SHEET_IMPORT_ACCEPT . '" required="required"/>
                            ' . pg_sheet_import_hint() . pg_upload_limit_hint() . '
                            <div class="form-text">' . lang('A file exported from this screen, with its translation column filled in. Rows are matched by the hash column; a row whose source text has since changed is skipped.') . '</div>
                        </div>
                        <div class="form-field">
                            <input value="1" class="check" type="checkbox" id="pg_tr_import_machine" name="as_machine"/>
                            <label class="" for="pg_tr_import_machine">' . lang('Import as machine translations (still to be reviewed)') . '</label>
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="submit" class="pg-button pg-button-fill">' . lang('Import') . '</button>
                    </div>
                </form>
        </dialog>
        <dialog class="dialog" id="pg_tr_clear_modal" aria-labelledby="pg_tr_clear_modal_title">
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_clear_modal_title">' . lang('Clear translations') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="small fg-secondary">' . lang(array('string' => 'The translations of the texts shown here are deleted, so the next "{var:1}" sends the texts to the engine again. A text that is also used elsewhere loses its translation there too.', 'vars' => h($update_button_label))) . '</p>
                        <div class="form-field mb-5">
                            <input value="1" class="check pg-tr-clear-reviewed" type="checkbox" id="pg_tr_clear_reviewed"' . (($clear_all_count > $clear_machine_count) ? '' : ' disabled="disabled"') . '/>
                            <label class="" for="pg_tr_clear_reviewed">' . lang('Delete the reviewed translations as well') . ' <span class="opacity-75 pg-tr-clear-reviewed-count">' . ($clear_all_count - $clear_machine_count) . '</span></label>
                        </div>
                        <p class="fw-semibold mb-0 pg-tr-clear-count"></p>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="pg-button pg-button-danger pg-tr-clear" data-machine="' . $clear_machine_count . '" data-all="' . $clear_all_count . '"><i class="bi bi-eraser" aria-hidden="true"></i>' . lang('Clear translations') . '</button>
                    </div>
        </dialog>
        <dialog class="dialog" id="pg_tr_review_all_modal" aria-labelledby="pg_tr_review_all_modal_title">
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_review_all_modal_title">' . lang('Approve all') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="small fg-secondary">' . lang('The machine translations of the texts shown here are marked as reviewed, as the engine gave them.') . '</p>
                        <div class="form-field mb-1">
                            <input value="1" class="check pg-tr-review-all-flagged" type="checkbox" id="pg_tr_review_all_flagged"' . (($review_all_flagged > 0) ? '' : ' disabled="disabled"') . '/>
                            <label class="" for="pg_tr_review_all_flagged">' . lang('Approve the ones flagged suspicious as well') . ' <span class="opacity-75 pg-tr-review-all-flagged-count">' . (int) $review_all_flagged . '</span></label>
                        </div>
                        <p class="small fg-secondary ms-7 mb-5">' . lang('An answer that repeats the source text, or whose length is out of proportion to it, is flagged suspicious.') . (($review_all_flagged > 0) ? ' <a href="' . h($url(array('filter' => 'suspicious', 'p' => 0))) . '">' . lang('Show the suspicious ones') . '</a>' : '') . '</p>
                        <p class="fw-semibold mb-0 pg-tr-review-all-count"></p>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="pg-button pg-button-fill pg-tr-review-all-go" data-plain="' . (int) $review_all_count . '" data-flagged="' . (int) $review_all_flagged . '"><i class="bi bi-check2-all" aria-hidden="true"></i>' . lang('Approve') . '</button>
                    </div>
        </dialog>
        <dialog class="dialog" id="pg_tr_accept_modal" aria-labelledby="pg_tr_accept_modal_title">
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_accept_modal_title">' . lang('Keep as in the source and approve') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="small fg-secondary">' . lang('The selected texts are kept as they are written in the source language: the source text becomes their translation, approved by hand. Meant for names, codes and words that read the same in both languages. A machine translation among them is replaced; a text used in other places too is approved there as well.') . '</p>
                        <p class="fw-semibold mb-0 pg-tr-accept-count"></p>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="pg-button pg-button-fill pg-tr-accept-go"><i class="bi bi-check2-square" aria-hidden="true"></i>' . lang('Approve') . '</button>
                    </div>
        </dialog>
        <dialog class="dialog" id="pg_tr_again_modal" aria-labelledby="pg_tr_again_modal_title">
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_again_modal_title">' . lang('Translate again') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="fw-semibold pg-tr-again-count"></p>
                        <div class="form-field mb-1">
                            <input value="1" class="check pg-tr-again-reviewed" type="checkbox" id="pg_tr_again_reviewed"/>
                            <label class="" for="pg_tr_again_reviewed">' . lang('Replace the reviewed ones as well') . ' <span class="opacity-75 pg-tr-again-reviewed-count">0</span></label>
                        </div>
                        <p class="small fg-secondary ms-7 mb-0">' . lang('Their translations are deleted first: until the engine answers, the page shows the source text.') . '</p>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="pg-button pg-button-fill pg-tr-again-go"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Translate again') . '</button>
                    </div>
        </dialog>
        <dialog class="dialog" id="pg_tr_clear_picked_modal" aria-labelledby="pg_tr_clear_picked_modal_title">
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_clear_picked_modal_title">' . lang('Clear translations') . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <p class="small fg-secondary">' . lang(array('string' => 'The translations of the selected texts are deleted, so the next "{var:1}" sends the texts to the engine again. A text that is also used elsewhere loses its translation there too.', 'vars' => h($update_button_label))) . '</p>
                        <div class="form-field mb-5">
                            <input value="1" class="check pg-tr-clear-picked-reviewed" type="checkbox" id="pg_tr_clear_picked_reviewed"/>
                            <label class="" for="pg_tr_clear_picked_reviewed">' . lang('Delete the reviewed translations as well') . ' <span class="opacity-75 pg-tr-clear-picked-reviewed-count">0</span></label>
                        </div>
                        <p class="fw-semibold mb-0 pg-tr-clear-picked-count"></p>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="pg-button pg-button-danger pg-tr-clear-picked-go"><i class="bi bi-eraser" aria-hidden="true"></i>' . lang('Clear translations') . '</button>
                    </div>
        </dialog>
        ' . ($language_row ? '<dialog class="dialog" id="pg_tr_automation_modal" aria-labelledby="pg_tr_automation_modal_title">
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_automation_modal_title">' . h(lang(array('string' => 'Automation for {var:1}', 'vars' => array(pg_tr_language_label($language))))) . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <div class="form-field mb-5">
                            <label class="form-label pg-field-label" for="pg_tr_auto_engine">' . lang('Translation engine') . '</label>
                            <select class="pg-input" id="pg_tr_auto_engine">' . $output_engine_options . '</select>
                            ' . $output_engine_notes . '
                        </div>
                        <div class="form-field mb-5">
                            <label class="form-label pg-field-label" for="pg_tr_auto_fallback">' . lang('Fallback engine') . '</label>
                            <select class="pg-input" id="pg_tr_auto_fallback">' . $output_fallback_options . '</select>
                            <p class="pg-field-help mb-0">' . lang('Takes the texts the engine could not translate when an update ends. A browser engine takes them only from "Try again with the failed ones".') . '</p>
                        </div>' . ($auto_update_known ? '
                        <div class="pg-card-inner mb-5"><div class="pg-card-inner-row">
                            <div class="flex-grow-1 min-w-0">
                                <label class="pg-list-row-title d-block" for="pg_tr_auto_save">' . lang('Translate on save') . '</label>
                                <div class="pg-text-meta">' . lang('A saved page, form, form record, product, product group, offer or picture is sent to the engine by itself.') . '</div>
                                <div class="pg-text-meta pg-tr-automation-reason pg-tr-auto-save-off"' . (($engine_key === 'manual') ? '' : ' hidden') . '>' . lang('Off with "By hand": there is no engine to send the texts to.') . '</div>
                            </div>
                            <input type="checkbox" class="pg-switch" id="pg_tr_auto_save"' . ($auto_update_on ? ' checked="checked"' : '') . (($engine_key === 'manual') ? ' disabled="disabled"' : '') . '/>
                        </div></div>' : '') . '
                        <p class="pg-text-meta mb-0 pg-tr-auto-schedule"><i class="bi bi-clock me-1" aria-hidden="true"></i>' . lang('Waiting work is picked up every 5 minutes, with nobody on this screen.') . '</p>
                    </div>
                    <div class="dialog-footer">
                        <a class="pg-text-meta fg-2 me-auto" href="' . h(pg_settings_link('languages', 'pgset-languages')) . '">' . lang('Languages and Translation') . '</a>
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="button" class="pg-button pg-button-fill pg-tr-auto-go">' . lang('Save') . '</button>
                    </div>
        </dialog>
        <dialog class="drawer drawer-end" id="pg_tr_job_drawer" aria-labelledby="pg_tr_job_drawer_title" style="width:min(34rem,100%)">
            <div class="d-flex flex-column h-100">
                <div class="drawer-header">
                    <div class="flex-grow-1 min-w-0 d-flex align-items-center gap-3">
                        <h5 class="drawer-title mb-0 text-truncate" id="pg_tr_job_drawer_title"></h5>
                        <span class="pg-pill pg-tr-job-pill"></span>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
                </div>
                <div class="drawer-body flex-grow-1 overflow-auto pg-tr-job-body" aria-live="polite"></div>
                <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">
                    <div class="pg-menu-wrap d-inline-flex pg-tr-job-retry-wrap" hidden>
                        <button type="button" class="pg-button pg-button-fill pg-tr-job-retry"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . lang('Try again with the failed ones') . '</button>' . ((($fallback_key !== '') && isset($engines[$fallback_key])) ? '
                        <button type="button" class="pg-button pg-button-fill pg-tr-job-retry-more" data-bs-toggle="menu" data-bs-placement="top-start" aria-expanded="false" aria-label="' . h(lang('More')) . '"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                        <ul class="menu">
                            <li><button type="button" class="menu-item pg-tr-job-retry-fallback"><i class="bi bi-arrow-repeat me-3" aria-hidden="true"></i>' . h(lang(array('string' => 'Try again with {var:1}', 'vars' => array($engines[$fallback_key]['label'])))) . '</button></li>
                        </ul>' : '') . '
                    </div>
                    <button type="button" class="pg-button pg-button-quiet pg-tr-job-stop" hidden>' . lang('Stop') . '</button>
                </div>
            </div>
        </dialog>' : '') . '
        <dialog class="dialog" id="pg_tr_glossary_modal" aria-labelledby="pg_tr_glossary_modal_title"' . ($glossary_back ? ' data-reopen="1"' : '') . '>
                <form class="d-contents" method="post" action="translations_action.php">
                    <input type="hidden" name="action" value="glossary_save"/>
                    <input type="hidden" name="language" value="' . h($language) . '"/>
                    ' . $view_fields . '
                    <input type="hidden" name="tab" value="glossary"/>
                    <input type="hidden" name="id" value="' . ($glossary_back ? (int) $glossary_back['id'] : 0) . '"/>
                    ' . get_token_field() . '
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="pg_tr_glossary_modal_title">' . (($glossary_back && ((int) $glossary_back['id'] > 0)) ? lang('Edit the term') : lang('Add a term')) . '</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <div class="mb-5">
                            <label class="form-label" for="pg_tr_term">' . lang('Term') . ' <span class="fg-secondary fw-normal">(' . h(pg_tr_language_label($source)) . ')</span></label>
                            <input type="text" class="form-control' . $glossary_invalid('term') . '" id="pg_tr_term" name="term" value="' . $glossary_value('term') . '" maxlength="255" required="required" autocomplete="off"' . $text_lang($source) . '/>
                        </div>
                        <div class="form-field mb-5">
                            <div class="switch"><input value="1" class="" type="checkbox" role="switch" id="pg_tr_term_keep" name="keep"' . $glossary_checked('keep') . '/></div>
                            <label class="" for="pg_tr_term_keep">' . lang('Keep as written (a brand, a product name)') . '</label>
                        </div>
                        <div class="mb-5 pg-tr-term-translation">
                            <label class="form-label" for="pg_tr_term_translation">' . lang('Translation to use') . ' <span class="fg-secondary fw-normal">(' . h(pg_tr_language_label($language)) . ')</span></label>
                            <input type="text" class="form-control' . $glossary_invalid('translation') . '" id="pg_tr_term_translation" name="translation" value="' . $glossary_value('translation') . '" maxlength="255" autocomplete="off"' . $text_lang($language) . '/>
                        </div>
                        <div class="form-field mb-3 pg-tr-term-all">
                            <div class="switch"><input value="1" class="" type="checkbox" role="switch" id="pg_tr_term_all" name="all_languages"' . $glossary_checked('all_languages') . '/></div>
                            <label class="" for="pg_tr_term_all">' . lang('For every language') . '</label>
                        </div>
                        <div class="form-field mb-5">
                            <div class="switch"><input value="1" class="" type="checkbox" role="switch" id="pg_tr_term_case" name="case_sensitive"' . $glossary_checked('case_sensitive') . '/></div>
                            <label class="" for="pg_tr_term_case">' . lang('Match upper and lower case exactly') . '</label>
                        </div>
                        <div>
                            <label class="form-label" for="pg_tr_term_note">' . lang('Note for the translator') . ' <span class="fg-secondary fw-normal">(' . lang('optional') . ')</span></label>
                            <input type="text" class="form-control" id="pg_tr_term_note" name="note" value="' . $glossary_value('note') . '" maxlength="255" autocomplete="off"/>
                        </div>
                    </div>
                    <div class="dialog-footer">
                        <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="dialog">' . lang('Cancel') . '</button>
                        <button type="submit" class="pg-button pg-button-fill">' . lang('Save') . '</button>
                    </div>
                </form>
        </dialog>' : '') . '
    </div>
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/translations.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/translations.js') . '"></script>
    ' . ($row_pictures ? pg_image_editor_includes() : '') . '
</main>' . output_footer();
