<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Communication › Surveys › Results: the satisfaction survey report. The
 * tools row holds the month stepper (or the last 12 months), the survey
 * (one, or every survey of a kind) and what it follows, and Excel. Figures:
 * the score, the answers, the answer rate, the open follow-ups; under them
 * a table grouped by what it follows, service, staff member, course, event,
 * form or month; then the comments with their Low, Middle, High filter and
 * the frequent words. A manager and up, or a user with the contacts right,
 * who then gets no staff grouping. Helpers: includes/fn/feedback_report.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/messages_area.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/feedback_report.php');

$user = validate_user();

if (!pg_feedback_results_may($user)) {
    log_activity(lang('access denied to survey results'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

$description = lang('See how your customers rated you, what they wrote, and where scores are low.');

if (!pg_feedback_surveys_ready()) {
    echo pg_messages_tab_page('surveys', $user, $description, pg_messages_note(lang('Surveys are not available yet; run the upgrade.'), '', '', 'amber'));
    exit();
}

$now = time();
$may_staff = ((int) $user['role'] <= 2);
$surveys = pg_feedback_report_surveys();
$survey_kinds = array_column($surveys, 'kind', 'id');
$scope = pg_feedback_report_scope($_GET['survey'] ?? '', $survey_kinds, $surveys ? reset($surveys)['kind'] : PG_FEEDBACK_NPS);
$touchpoints = pg_feedback_touchpoints();
$touchpoint = (is_string($_GET['after'] ?? null) && isset($touchpoints[$_GET['after']])) ? $_GET['after'] : '';
$groupings = pg_feedback_report_groupings($may_staff);
$grouping = (is_string($_GET['group'] ?? null) && isset($groupings[$_GET['group']])) ? $_GET['group'] : 'touchpoint';
$month = pg_feedback_report_month($_GET['month'] ?? '', $now);
$year = (($_GET['view'] ?? '') === 'year');
$months = pg_feedback_report_months($month, $year);
$bounds = pg_feedback_report_bounds($months);
$band_word = is_string($_GET['band'] ?? null) ? $_GET['band'] : '';
$band = pg_feedback_band_key($band_word);
$band_word = ($band > 0) ? $band_word : '';
$kind = $scope['kind'];
$kinds = pg_feedback_kind_names();

$report = pg_feedback_report_read($scope, $touchpoint, $grouping, $months);
$fold = pg_feedback_report_fold($kind, $report['cells']);
$labels = pg_feedback_report_labels($grouping, array_keys($fold['rows']));
$comments = pg_feedback_report_comments($scope, $touchpoint, $bounds, $band);
$first_lang = array_keys(pg_feedback_survey_languages())[0];
$words = pg_feedback_words(array_column($comments, 'comment'), $first_lang, PG_FEEDBACK_WORDS_MAX);
$period = $year ? pg_customer_value_month_name($months[0]) . ' – ' . pg_customer_value_month_name($month) : pg_customer_value_month_name($month);

// The address of this screen with some of its choices changed.
$state = array('month' => $month, 'view' => $year ? 'year' : '', 'survey' => $scope['value'], 'after' => $touchpoint, 'group' => $grouping, 'band' => $band_word);
$link = function ($changes) use ($state) {
    return 'messages_survey_results.php?' . http_build_query(array_filter(array_merge($state, $changes), 'strlen'));
};
$row_name = function ($key) use ($labels, $grouping) {
    if (isset($labels[$key]) && ($labels[$key] !== '')) {
        return $labels[$key];
    }

    return ($grouping === 'touchpoint') ? (string) $key : '#' . $key;
};

// The table's rows, best scores first, as shown and as written to Excel.
$lines = array();

foreach ($fold['rows'] as $key => $row) {
    $few = pg_feedback_report_few($grouping, $row);
    $lines[] = array('key' => (string) $key, 'name' => $row_name((string) $key), 'row' => $row, 'few' => $few);
}

if ($grouping === 'month') {
    usort($lines, function ($a, $b) {
        return strcmp($a['key'], $b['key']);
    });
} else {
    usort($lines, function ($a, $b) {
        return ($b['row']['answered'] - $a['row']['answered']) ?: strcmp($a['name'], $b['name']);
    });
}

// ------------------------------------------------------------------ export
if (($_GET['export'] ?? '') === 'xlsx') {
    $head = array(lang($groupings[$grouping]), lang('Sent'), lang('Answers'), lang('Answer rate') . ' %', lang($kinds[$kind]), lang('Low scores'));
    $table = array();

    foreach ($lines as $line) {
        $row = $line['row'];
        $table[] = $line['few']
            ? array($line['name'], $row['sent'], null, null, lang('Fewer than 5 answers'), null)
            : array($line['name'], $row['sent'], $row['answered'], $row['rate'], pg_feedback_report_score_text($kind, $row['sum']), $row['low']);
    }

    $table[] = array(lang('Total'), $fold['total']['sent'], $fold['total']['answered'], $fold['total']['rate'], pg_feedback_report_score_text($kind, $fold['total']['sum']), $fold['total']['low']);
    $notes = array();

    foreach ($comments as $comment) {
        $notes[] = array(date('Y-m-d H:i', (int) $comment['answered_at']), (string) $comment['survey'], (int) $comment['score'], (string) $comment['comment']);
    }

    $sheets = array(
        pg_feedback_report_sheet($head, $table),
        pg_feedback_report_sheet(array(lang('Answered'), lang('Survey'), lang('Score'), lang('Comment')), $notes),
    );
    $path = tempnam(sys_get_temp_dir(), 'pgf');

    if (($path === false) || !pg_xlsx_write_book(array(array('name' => lang('Results')), array('name' => lang('Comments'))), function ($index) use ($sheets) {
        return $sheets[$index];
    }, $path)) {
        if ($path !== false) {
            @unlink($path);
        }
        output_error(lang('The workbook could not be written.'));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="survey_results_' . ($year ? $months[0] . '_' : '') . $month . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit();
}

// ------------------------------------------------------------------ display
$total = $fold['total'];
$score_text = pg_feedback_report_score_text($kind, $total['sum']);
$figures = pg_report_figures_html(array(
    pg_report_view_figure(($kind === PG_FEEDBACK_NPS) ? lang('NPS') : lang(array('string' => '{var:1} mean', 'vars' => array(lang($kinds[$kind])))), ($score_text !== '') ? $score_text : '–', 'bi-emoji-smile', 'blue'),
    pg_report_view_figure(lang('Answers'), pg_format_number($total['answered'], 0), 'bi-chat-left-text', 'teal', lang(array('string' => '{var:1} sent', 'vars' => array(pg_format_number($total['sent'], 0))))),
    pg_report_view_figure(lang('Answer rate'), ($total['rate'] !== null) ? lang(array('string' => '{var:1}%', 'vars' => array($total['rate']))) : '–', 'bi-percent', 'violet'),
    pg_report_view_figure(lang('Open follow-ups'), pg_format_number($report['open'], 0), 'bi-flag', 'amber', lang(array('string' => '{var:1} low scores', 'vars' => array(pg_format_number($total['low'], 0))))),
));

$output_rows = '';

foreach ($lines as $line) {
    $row = $line['row'];
    $output_rows .= '<tr><th scope="row">' . h($line['name']) . '</th><td class="text-end">' . pg_format_number($row['sent'], 0) . '</td>';

    if ($line['few']) {
        $output_rows .= '<td colspan="4" class="fg-secondary">' . h(lang('Fewer than 5 answers')) . '</td></tr>';
        continue;
    }

    $score = pg_feedback_report_score_text($kind, $row['sum']);
    $output_rows .= '<td class="text-end">' . pg_format_number($row['answered'], 0) . '</td>'
        . '<td class="text-end">' . (($row['rate'] !== null) ? h(lang(array('string' => '{var:1}%', 'vars' => array($row['rate'])))) : '–') . '</td>'
        . '<td class="text-end">' . (($score !== '') ? h($score) : '–') . '</td>'
        . '<td class="text-end">' . pg_format_number($row['low'], 0) . '</td></tr>';
}

$output_rows = ($output_rows === '')
    ? '<tr><td colspan="6" class="fg-secondary">' . h(lang('No survey was sent in this period.')) . '</td></tr>'
    : $output_rows . '<tr class="pg-report-total"><th scope="row">' . h(lang('Total')) . '</th><td class="text-end">' . pg_format_number($total['sent'], 0) . '</td><td class="text-end">' . pg_format_number($total['answered'], 0) . '</td>'
        . '<td class="text-end">' . (($total['rate'] !== null) ? h(lang(array('string' => '{var:1}%', 'vars' => array($total['rate'])))) : '–') . '</td><td class="text-end">' . (($score_text !== '') ? h($score_text) : '–') . '</td><td class="text-end">' . pg_format_number($total['low'], 0) . '</td></tr>';

$grouping_names = array();

foreach ($groupings as $key => $label) {
    $grouping_names[$key] = lang($label);
}

// The tools row: the month stepper and the last 12 months, the survey and
// what it follows, Excel at the right.
$survey_options = '';

foreach ($kinds as $key => $name) {
    $value = 'k' . $key;
    $survey_options .= '<option value="' . $value . '"' . (($scope['value'] === $value) ? ' selected' : '') . '>' . h(lang(array('string' => 'All {var:1} surveys', 'vars' => array(lang($name))))) . '</option>';
}

foreach ($surveys as $survey) {
    $survey_options .= '<option value="' . $survey['id'] . '"' . (($scope['survey'] === $survey['id']) ? ' selected' : '') . '>' . h($survey['name'] . ($survey['archived_at'] ? ' (' . lang('Archived') . ')' : '')) . '</option>';
}

$after_options = '<option value="">' . h(lang('Anything')) . '</option>';

foreach ($touchpoints as $key => $point) {
    $after_options .= '<option value="' . h($key) . '"' . (($touchpoint === $key) ? ' selected' : '') . '>' . h(lang($point['label'])) . '</option>';
}

$output_tools = '
    <form method="get" action="messages_survey_results.php" class="pg-report-tools">
        <div class="pg-seg" role="group" aria-label="' . h(lang('Month')) . '">
            <a class="pg-seg-item pg-report-step" href="' . h($link(array('month' => pg_feedback_report_month_step($month, -1)))) . '" title="' . h(lang('Previous')) . '" aria-label="' . h(lang('Previous')) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            <a class="pg-seg-item' . (!$year ? ' is-active' : '') . '" href="' . h($link(array('view' => ''))) . '"' . (!$year ? ' aria-current="page"' : '') . '>' . h(pg_customer_value_month_name($month)) . '</a>
            <a class="pg-seg-item pg-report-step" href="' . h($link(array('month' => pg_feedback_report_month_step($month, 1)))) . '" title="' . h(lang('Next')) . '" aria-label="' . h(lang('Next')) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        </div>
        <div class="pg-seg" role="group" aria-label="' . h(lang('Period')) . '">
            <a class="pg-seg-item' . ($year ? ' is-active' : '') . '" href="' . h($link(array('view' => 'year'))) . '"' . ($year ? ' aria-current="page"' : '') . '>' . h(lang('Last 12 months')) . '</a>
        </div>
        <span class="pg-report-field"><label class="visually-hidden" for="survey">' . h(lang('Survey')) . '</label><select class="form-control" id="survey" name="survey">' . $survey_options . '</select></span>
        <span class="pg-report-field"><label class="pg-report-label" for="after">' . h(lang('Sent after')) . '</label><select class="form-control" id="after" name="after">' . $after_options . '</select></span>
        <input type="hidden" name="month" value="' . h($month) . '">' . ($year ? '<input type="hidden" name="view" value="year">' : '') . '
        <input type="hidden" name="group" value="' . h($grouping) . '">
        <button type="submit" class="pg-button pg-button-quiet"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>' . h(lang('Show')) . '</button>
        <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h($period) . '</span>
        <a class="pg-button pg-button-quiet ms-auto" href="' . h($link(array('export' => 'xlsx'))) . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . h(lang('Excel')) . '</a>
    </form>';

// Comments: a row each with the score's pill, the comment and its record;
// the frequent words beside them.
$output_comments = '';

foreach (array_slice($comments, 0, PG_FEEDBACK_COMMENTS_SHOWN) as $comment) {
    $url = pg_feedback_report_record_url((string) $comment['record_type'], (int) $comment['record_id']);
    $output_comments .= '<div class="pg-list-row is-read">' . pg_feedback_score_pill((int) $comment['score'], (int) $comment['band'])
        . '<div class="pg-list-row-main"><div class="pg-list-row-head"><span class="pg-list-row-title">' . h((string) $comment['survey']) . '</span>'
        . '<span class="pg-list-row-time">' . h(get_absolute_time(array('timestamp' => (int) $comment['answered_at'], 'type' => 'date', 'format' => 'plain_text'))) . '</span></div>'
        . '<div class="pg-list-row-text text-break">' . nl2br(h((string) $comment['comment'])) . '</div>'
        . (($url !== '') ? '<div class="pg-list-row-actions"><a class="pg-button pg-button-quiet pg-button-sm" href="' . h($url) . '">' . h(lang('Open the record')) . '</a></div>' : '')
        . '</div></div>';
}

if ($output_comments === '') {
    $output_comments = '<p class="pg-text-meta mb-0">' . h(lang('No comment in this period.')) . '</p>';
}

$output_words = '';

foreach ($words as $word => $count) {
    $output_words .= '<div class="pg-gather-line"><span class="text-break">' . h((string) $word) . '</span><span class="pg-gather-line-end">' . pg_format_number($count, 0) . '</span></div>';
}

$band_options = array('' => lang('All'));

foreach (pg_feedback_band_names() as $word => $spec) {
    $band_options[$word] = lang($spec[1]);
}

$body = pg_feedback_segments_html('results', $user) . $output_tools . '
    <div class="mb-3">' . $figures . '</div>
    <div class="mb-3">' . pg_messages_seg_filter('group', $grouping_names, $grouping, 'touchpoint', 'messages_survey_results.php', array_merge($state, array('group' => '')), lang('Group by')) . '</div>
    <section class="pg-card pg-report-frame" aria-labelledby="feedback_results_title">
        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="feedback_results_title">' . h($grouping_names[$grouping]) . '</h3><span class="pg-report-frame-note">' . h($period) . ' · ' . h(lang(array('string' => '{var:1} answers', 'vars' => array(pg_format_number($total['answered'], 0))))) . '</span></div>
        <div class="pg-report-frame-body">
            <table class="pg-report-table">
                <thead><tr><th>' . h($grouping_names[$grouping]) . '</th><th class="text-end">' . h(lang('Sent')) . '</th><th class="text-end">' . h(lang('Answers')) . '</th><th class="text-end">' . h(lang('Answer rate')) . '</th><th class="text-end">' . h(lang('Score')) . '</th><th class="text-end">' . h(lang('Low scores')) . '</th></tr></thead>
                <tbody>' . $output_rows . '</tbody>
            </table>
        </div>
    </section>
    <div class="row g-5 mb-5">
        <div class="lg:col-8">
            <section class="pg-card p-5 h-100" aria-labelledby="feedback_comments_title">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3"><h3 class="pg-title-section mb-0 me-auto" id="feedback_comments_title">' . h(lang('Comments')) . '</h3>'
                . pg_messages_seg_filter('band', $band_options, $band_word, '', 'messages_survey_results.php', array_merge($state, array('band' => '')), lang('Score')) . '</div>
                ' . $output_comments . '
            </section>
        </div>
        <div class="lg:col-4">
            <section class="pg-card p-5 h-100" aria-labelledby="feedback_words_title">
                <h3 class="pg-title-section mb-1" id="feedback_words_title">' . h(lang('Frequent words')) . '</h3>
                <p class="pg-card-sub mb-3">' . h(lang('In how many comments each word stands.')) . '</p>
                ' . (($output_words !== '') ? '<div class="pg-gather">' . $output_words . '</div>' : '<p class="pg-text-meta mb-0">' . h(lang('No comment in this period.')) . '</p>') . '
            </section>
        </div>
    </div>
    <p class="fg-secondary small">' . h(lang('Surveys are counted in the month they were sent. A staff member\'s figures show from 5 answers on.')) . '</p>';

echo pg_messages_tab_page('surveys', $user, $description, $body);
