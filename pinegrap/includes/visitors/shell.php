<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The frame of the Visitors overview (visitors.php): the period bar, the
 * section loader and the small pieces every section draws with (a figure
 * tile, the change against the earlier period, a bar list, a table, a chart
 * and the frame of a section). A section lives in includes/visitors/<name>.php
 * as pg_visitors_section_<name>($period, $user) and returns its HTML, ''
 * for nothing; the loader takes it in when the file is there, so a section
 * lands without an edit of the screen.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_VISITORS_SCREEN')) {
    exit;
}

/**
 * The HTML of every section there is, in the screen's order. A section file
 * that is missing, or whose function is not there, draws nothing.
 *
 * @param array $period pg_visitor_period()
 * @param array $user   validate_user()
 * @return string
 */
function pg_visitors_render_sections(array $period, array $user)
{
    $output = '';

    foreach (array_keys(pg_visitors_sections()) as $name) {

        if (!pg_visitors_section_exists($name)) {
            continue;
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/visitors/' . $name . '.php');

        $function = 'pg_visitors_section_' . $name;

        if (function_exists($function)) {
            $output .= (string) $function($period, $user);
        }
    }

    return $output;
}

/**
 * The change of a figure against the earlier period, as data. Pure.
 *
 *   direction  'up', 'down', 'same', or 'none' when there is no earlier
 *              figure to compare with (null, or 0 against a figure now)
 *   percent    the change in percent, rounded to a whole number; null for none
 *   good       true when the change is for the better, false for the worse,
 *              null when it is neither
 *
 * @param int|float|null $now
 * @param int|float|null $before
 * @param bool           $lower_is_better a fall is good (a load time, a position)
 * @return array
 */
function pg_visitors_change_data($now, $before, $lower_is_better = false)
{
    if (!is_numeric($now) || !is_numeric($before) || (((float) $before == 0) && ((float) $now != 0))) {
        return array('direction' => 'none', 'percent' => null, 'good' => null);
    }

    $now = (float) $now;
    $before = (float) $before;

    $percent = ($before == 0) ? 0 : (int) round(($now - $before) / abs($before) * 100);

    if ($percent === 0) {
        return array('direction' => 'same', 'percent' => 0, 'good' => null);
    }

    $direction = ($percent > 0) ? 'up' : 'down';

    return array(
        'direction' => $direction,
        'percent'   => $percent,
        'good'      => ($direction === 'up') !== (bool) $lower_is_better,
    );
}

/**
 * The change of a figure against the earlier period: an arrow, the percent
 * and the colour of a good or a bad change; "No earlier figure" when there
 * is nothing to compare with, never 0 %.
 *
 * @param int|float|null $now
 * @param int|float|null $before
 * @param bool           $lower_is_better
 * @return string HTML
 */
function pg_visitors_change($now, $before, $lower_is_better = false)
{
    $change = pg_visitors_change_data($now, $before, $lower_is_better);

    if ($change['direction'] === 'none') {
        return '<span class="pg-visitors-change is-none">' . h(lang('No earlier figure')) . '</span>';
    }

    if ($change['direction'] === 'same') {
        return '<span class="pg-visitors-change is-same"><i class="bi bi-dash" aria-hidden="true"></i>'
            . h(lang('No change')) . '</span>';
    }

    $up = ($change['direction'] === 'up');

    return '<span class="pg-visitors-change ' . ($change['good'] ? 'is-good' : 'is-bad') . '">'
        . '<i class="bi ' . ($up ? 'bi-arrow-up-right' : 'bi-arrow-down-right') . '" aria-hidden="true"></i>'
        . '<span class="visually-hidden">' . h($up ? lang('Up') : lang('Down')) . ' </span>'
        . h(pg_format_number(abs($change['percent']), 0)) . ' %</span>';
}

/**
 * A figure tile: the label, the value (already formatted) and under it the
 * change, or a note.
 *
 * @param string $label
 * @param string $value  formatted text, escaped here
 * @param string $change HTML from pg_visitors_change(), or ''
 * @param string $note   text under the value, escaped here
 * @return string HTML
 */
function pg_visitors_tile($label, $value, $change = '', $note = '')
{
    return '
        <div class="pg-visitors-tile">
            <div class="pg-visitors-tile-label">' . h($label) . '</div>
            <div class="pg-visitors-tile-value">' . h($value) . '</div>
            ' . ($change !== '' ? '<div class="pg-visitors-tile-change">' . $change . '</div>' : '') . '
            ' . ($note !== '' ? '<div class="pg-visitors-tile-note">' . h($note) . '</div>' : '') . '
        </div>';
}

/**
 * A list of labelled bars, each as long as its share of the largest value.
 *
 * @param array $rows list of array('label' => text, 'value' => number,
 *                    'url' => optional link, 'text' => optional value text)
 * @param array $opts 'label': the list's accessible name
 * @return string HTML; pg_visitors_empty() when there is no row
 */
function pg_visitors_bars(array $rows, array $opts = array())
{
    if (!$rows) {
        return pg_visitors_empty();
    }

    $max = 0;

    foreach ($rows as $row) {
        $max = max($max, (float) $row['value']);
    }

    $items = '';

    foreach ($rows as $row) {
        $width = ($max > 0) ? round((float) $row['value'] / $max * 100, 1) : 0;
        $label = h((string) $row['label']);

        if (!empty($row['url'])) {
            $label = '<a href="' . h((string) $row['url']) . '">' . $label . '</a>';
        }

        $text = isset($row['text']) ? (string) $row['text'] : pg_format_number($row['value'], 0);

        $items .= '
            <li class="pg-visitors-bar">
                <span class="pg-visitors-bar-label text-break">' . $label . '</span>
                <span class="pg-visitors-bar-value">' . h($text) . '</span>
                <span class="pg-visitors-bar-track" aria-hidden="true"><span style="width:' . $width . '%"></span></span>
            </li>';
    }

    $name = isset($opts['label']) ? ' aria-label="' . h((string) $opts['label']) . '"' : '';

    return '<ul class="pg-visitors-bars list-unstyled mb-0"' . $name . '>' . $items . '</ul>';
}

/**
 * A plain table. A cell is text (escaped here) or array('html' => markup,
 * 'sort' => value for data-sort); a heading is text or array('label' =>
 * text, 'end' => true for a right-aligned figure column).
 *
 * @param array $headings
 * @param array $rows list of lists of cells
 * @param array $opts 'caption': text; 'id': the table's id
 * @return string HTML; pg_visitors_empty() when there is no row
 */
function pg_visitors_table(array $headings, array $rows, array $opts = array())
{
    if (!$rows) {
        return pg_visitors_empty();
    }

    $ends = array();
    $head = '';

    foreach (array_values($headings) as $index => $heading) {
        $label = is_array($heading) ? (string) $heading['label'] : (string) $heading;
        $ends[$index] = is_array($heading) && !empty($heading['end']);
        $head .= '<th scope="col"' . ($ends[$index] ? ' class="text-end"' : '') . '>' . h($label) . '</th>';
    }

    $body = '';

    foreach ($rows as $row) {
        $body .= '<tr>';

        foreach (array_values($row) as $index => $cell) {
            $class = !empty($ends[$index]) ? ' class="text-end"' : '';

            if (is_array($cell)) {
                $sort = isset($cell['sort']) ? ' data-sort="' . h((string) $cell['sort']) . '"' : '';
                $body .= '<td' . $class . $sort . '>' . (isset($cell['html']) ? $cell['html'] : '') . '</td>';
            } else {
                $body .= '<td' . $class . '>' . h((string) $cell) . '</td>';
            }
        }

        $body .= '</tr>';
    }

    $id = isset($opts['id']) ? ' id="' . h((string) $opts['id']) . '"' : '';
    $caption = isset($opts['caption']) ? '<caption class="visually-hidden">' . h((string) $opts['caption']) . '</caption>' : '';

    return '
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 pg-visitors-table"' . $id . '>' . $caption . '
                <thead><tr>' . $head . '</tr></thead>
                <tbody>' . $body . '</tbody>
            </table>
        </div>';
}

/**
 * The line a section shows when the period has nothing to show.
 *
 * @param string $text '' for "Nothing in this period."
 * @return string HTML
 */
function pg_visitors_empty($text = '')
{
    return '<p class="pg-visitors-empty">' . h(($text !== '') ? $text : lang('Nothing in this period.')) . '</p>';
}

/**
 * The frame of a section: an anchor the menu and links jump to, its heading,
 * an aside at the heading's end (a link, a note) and the body.
 *
 * @param string $id    the section's name, also its anchor
 * @param string $title text
 * @param string $body  HTML
 * @param string $aside HTML
 * @return string HTML
 */
function pg_visitors_section($id, $title, $body, $aside = '')
{
    $id = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $id));

    return '
        <section id="' . $id . '" class="pg-visitors-section pg-visitors-' . $id . '" aria-labelledby="' . $id . '_heading">
            <header class="pg-visitors-section-head">
                <h2 class="pg-visitors-section-title" id="' . $id . '_heading">' . h($title) . '</h2>
                ' . ($aside !== '' ? '<div class="pg-visitors-section-aside">' . $aside . '</div>' : '') . '
            </header>
            <div class="pg-visitors-section-body">' . $body . '</div>
        </section>';
}

/**
 * A chart drawn by assets/js/pg_visitors.src.js with Chart.js: a canvas
 * hidden from screen readers, the figures beside it as a visually hidden
 * table, and the configuration as JSON.
 *
 *   type     'line' (default) or 'bar'
 *   labels   the x-axis labels (text)
 *   series   list of array('label' => text, 'data' => list of numbers or
 *            null, 'dashed' => bool, 'axis' => 'right' for a second axis)
 *   notes    one more tooltip line for each label (text, '' for none)
 *   caption  the hidden table's caption
 *   height   CSS height of the drawing, '16rem' by default
 *
 * The series take the theme's ink colours in order; the script draws them
 * again when the theme changes.
 *
 * @param string $id
 * @param array  $config
 * @return string HTML
 */
function pg_visitors_chart($id, array $config)
{
    $id = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $id));
    $labels = isset($config['labels']) ? array_values($config['labels']) : array();
    $series = array();

    foreach (isset($config['series']) ? $config['series'] : array() as $one) {
        $series[] = array(
            'label'  => (string) $one['label'],
            'data'   => array_values($one['data']),
            'dashed' => !empty($one['dashed']),
            'axis'   => (isset($one['axis']) && ($one['axis'] === 'right')) ? 'right' : 'left',
        );
    }

    $head = '<th scope="col">' . h(lang('Period')) . '</th>';

    foreach ($series as $one) {
        $head .= '<th scope="col">' . h($one['label']) . '</th>';
    }

    $rows = '';

    foreach ($labels as $index => $label) {
        $rows .= '<tr><th scope="row">' . h((string) $label) . '</th>';

        foreach ($series as $one) {
            $value = isset($one['data'][$index]) ? $one['data'][$index] : null;
            $rows .= '<td>' . (($value === null) ? '–' : h(pg_format_number($value, (floor((float) $value) == (float) $value) ? 0 : 1))) . '</td>';
        }

        $rows .= '</tr>';
    }

    $data = array(
        'type'   => (isset($config['type']) && ($config['type'] === 'bar')) ? 'bar' : 'line',
        'labels' => array_map('strval', $labels),
        'series' => $series,
        'notes'  => isset($config['notes']) ? array_map('strval', array_values($config['notes'])) : array(),
    );

    $height = isset($config['height']) && preg_match('/^\d+(\.\d+)?(rem|px)$/', (string) $config['height']) ? $config['height'] : '16rem';

    return '
        <div class="pg-visitors-chart" style="height:' . $height . '">
            <canvas id="' . $id . '" data-pg-chart="' . $id . '_data" aria-hidden="true"></canvas>
        </div>
        <script type="application/json" id="' . $id . '_data">' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>
        <table class="visually-hidden">
            <caption>' . h(isset($config['caption']) ? (string) $config['caption'] : '') . '</caption>
            <thead><tr>' . $head . '</tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>';
}

/**
 * The period bar: the presets, the custom range on two calendar fields, the
 * compare switch, and Apply for a browser without scripts. A GET form, so
 * every choice is a link that can be kept and shared.
 *
 * @param array $period pg_visitor_period()
 * @return string HTML
 */
function pg_visitors_period_bar(array $period)
{
    $options = '';

    foreach (pg_visitor_period_presets() as $key => $label) {
        $options .= '<option value="' . h($key) . '"' . (($key === $period['key']) ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $custom = ($period['key'] === 'custom');

    return '
        <form method="get" action="visitors.php" class="pg-visitors-period" id="pg_visitors_period">
            <label class="visually-hidden" for="pg_visitors_p">' . h(lang('Period')) . '</label>
            <select class="form-control form-control-sm" id="pg_visitors_p" name="p">' . $options . '</select>
            <span class="pg-visitors-range" id="pg_visitors_range"' . ($custom ? '' : ' hidden') . '>
                <label class="visually-hidden" for="pg_visitors_from">' . h(lang('Start date')) . '</label>
                <input type="text" class="form-control form-control-sm" id="pg_visitors_from" name="from" value="' . h(prepare_form_data_for_output($period['from'], 'date', false)) . '" autocomplete="off" data-pg-date>
                <span aria-hidden="true">–</span>
                <label class="visually-hidden" for="pg_visitors_to">' . h(lang('End date')) . '</label>
                <input type="text" class="form-control form-control-sm" id="pg_visitors_to" name="to" value="' . h(prepare_form_data_for_output($period['to'], 'date', false)) . '" autocomplete="off" data-pg-date>
            </span>
            <input type="hidden" name="compare" value="0">
            <span class="pg-visitors-compare">
                <span class="switch"><input type="checkbox" role="switch" id="pg_visitors_compare" name="compare" value="1"' . ($period['compare'] ? ' checked' : '') . '></span>
                <label for="pg_visitors_compare">' . h(lang('Compare with the previous period')) . '</label>
            </span>
            <button type="submit" class="btn btn-sm btn-text theme-secondary pg-visitors-apply">' . h(lang('Apply')) . '</button>
        </form>';
}

/**
 * The dates a period covers, and with comparing on, the dates it is set
 * against, as one line for the toolbar's grow slot.
 *
 * @param array $period pg_visitor_period()
 * @return string HTML
 */
function pg_visitors_period_text(array $period)
{
    $range = function ($from, $to) {
        $text = prepare_form_data_for_output($from, 'date', false);

        return ($from === $to) ? $text : $text . ' – ' . prepare_form_data_for_output($to, 'date', false);
    };

    $text = '<span class="small">' . h($range($period['from'], $period['to'])) . '</span>';

    if ($period['compare']) {
        $text .= ' <span class="small fg-secondary">' . h(lang(array(
            'string' => 'against {var:1}',
            'vars'   => array($range($period['previous_from'], $period['previous_to'])),
        ))) . '</span>';
    }

    return $text;
}

/**
 * The overview's stylesheet: the minified copy when the site runs minified
 * and the copy is there, else the source, versioned by its modification time.
 *
 * @return string URL
 */
function pg_visitors_style_url()
{
    $minified = 'assets/css/pg_visitors.min.css';
    $bundles = pg_css_bundles();
    $file = $minified;

    if ((defined('ENVIRONMENT_SUFFIX') && (ENVIRONMENT_SUFFIX !== 'min')) || !is_file(PG_FUNCTIONS_DIR . '/' . $minified)) {
        $file = $bundles[$minified];
    }

    return OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . $file . '?v=' . (int) @filemtime(PG_FUNCTIONS_DIR . '/' . $file);
}
