<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The data layer of a canvas document, for the server's draw
 * (includes/fn/image_draw.php): a port of the DATA section of
 * assets/js/pg_image_editor_model.js. A data layer describes a Workspace
 * table drawn as a chart or a plain table; it holds the description and
 * never the rows. The description is checked and filled with its defaults,
 * the rows read for the reader are grouped, sorted and cut, and the drawing
 * is a list of marks in the layer's box (filled outlines, round-capped
 * lines, texts) that the browser and this server paint the same way. Every
 * number is rounded to hundredths, so both give the same list; the shared
 * fixture tests/fixtures/canvas_data/layers.json holds them alike.
 *
 * All pure.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The bounds of a data layer (M.DATA_MAX).
 *
 * @return array values, title, colours, labels, slices, tableRows, rows, cell
 */
function pg_image_draw_data_max()
{
    return array('values' => 5, 'title' => 120, 'colours' => 5, 'labels' => 24, 'slices' => 6, 'tableRows' => 50, 'rows' => 5000, 'cell' => 80);
}

/**
 * The values of the Workspace canvas guide a data layer draws by
 * (M.DATA_GUIDE; tests/image_editor_data_test.php holds the two alike).
 *
 * @return array base, grid, type (role => [size, weight, ink]), spacing,
 *               palettes, marks
 */
function pg_image_draw_data_guide()
{
    return array(
        'base'     => 675,
        'grid'     => array('margin' => 48, 'gutter' => 24, 'axis' => 64),
        'type'     => array('title' => array(44, 700, 'ink'), 'figure' => array(72, 700, 'ink'), 'label' => array(20, 600, 'ink2'), 'axis' => array(18, 400, 'ink3'), 'note' => array(16, 400, 'ink3')),
        'spacing'  => array('legend' => 24, 'plot' => 40),
        'palettes' => array(
            'dark'  => array('background' => '#14161c', 'ink' => '#eef0f3', 'ink2' => '#a4a9b1', 'ink3' => '#6d737c', 'grid' => '#262930', 'baseline' => '#3a3e47',
                'series' => array('#5892ea', '#00ac86', '#9580e0', '#be8700', '#d46d83'), 'other' => '#5d636c'),
            'light' => array('background' => '#ffffff', 'ink' => '#12151a', 'ink2' => '#58606a', 'ink3' => '#8a919b', 'grid' => '#eceef1', 'baseline' => '#c9cdd3',
                'series' => array('#2f6fed', '#0e9a74', '#7150e6', '#b7791f', '#d43d6b'), 'other' => '#a3a9b1'),
        ),
        'marks'    => array('barMax' => 40, 'barBand' => 56, 'barRadius' => 6, 'groupGap' => 4, 'stackGap' => 2, 'line' => 6, 'dot' => 6, 'ring' => 3, 'areaMix' => 14,
            'grid' => 1, 'baseline' => 2, 'ticksMax' => 5, 'donutOuter' => 190, 'donutInner' => 128, 'row' => 44),
    );
}

/**
 * A number rounded as JavaScript's Math.round() does: halves up, towards
 * plus infinity (PHP's round() takes them away from zero).
 *
 * @param float $n
 * @return float
 */
function pg_image_draw_data_js_round($n)
{
    return floor($n + 0.5);
}

/**
 * A number rounded to hundredths, as the model rounds every mark.
 *
 * @param float $n
 * @return float
 */
function pg_image_draw_data_round($n)
{
    return pg_image_draw_data_js_round($n * 100) / 100;
}

/**
 * A value's truth as JavaScript tests it (!!value).
 *
 * @param mixed $v
 * @return bool
 */
function pg_image_draw_data_truthy($v)
{
    return !(($v === null) || ($v === false) || ($v === 0) || ($v === 0.0) || ($v === '') || (is_float($v) && is_nan($v)));
}

/**
 * Is a value a list, as JavaScript's Array.isArray() sees a decoded JSON
 * array?
 *
 * @param mixed $v
 * @return bool
 */
function pg_image_draw_data_list($v)
{
    return is_array($v) && (($v === array()) || (array_keys($v) === range(0, count($v) - 1)));
}

/**
 * A number as JavaScript's String() writes it, for the plain numbers a
 * table holds.
 *
 * @param int|float $n
 * @return string
 */
function pg_image_draw_data_js_number($n)
{
    if (is_int($n)) {
        return (string) $n;
    }

    if ((floor($n) === $n) && (abs($n) < 1e15)) {
        return sprintf('%.0f', $n);
    }

    // The shortest form that reads back as the same number.
    for ($digits = 1; $digits <= 17; $digits++) {
        $s = sprintf('%.' . $digits . 'G', $n);

        if ((float) $s === $n) {
            break;
        }
    }

    if (stripos($s, 'E') !== false) {
        $s = rtrim(rtrim(number_format($n, 20, '.', ''), '0'), '.');
    }

    return $s;
}

/**
 * Is a value a column id (a string or number of letters, digits, _ and -)?
 *
 * @param mixed $v
 * @return bool
 */
function pg_image_draw_data_column_id($v)
{
    return (is_string($v) || is_int($v) || is_float($v)) && (bool) preg_match('/^[A-Za-z0-9_-]{1,40}$/', is_string($v) ? $v : pg_image_draw_data_js_number($v));
}

/**
 * A Google family's name as the editor cleans it (M.textFamilyName()).
 *
 * @param string $name
 * @return string
 */
function pg_image_draw_data_family_name($name)
{
    $s = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^A-Za-z0-9 _\-.]/', '', (string) $name)));

    return substr($s, 0, 60);
}

/**
 * A description checked and filled with its defaults (M.dataCheck()).
 *
 * @param mixed $d the layer's data, decoded from JSON as arrays
 * @return array ok, data; or ok false, error: data_table, data_show,
 *               data_kind, data_columns, data_group, data_sort,
 *               data_format, data_title, data_colours, data_theme, data_font
 */
function pg_image_draw_data_check($d)
{
    $fail = function ($code) {
        return array('ok' => false, 'error' => $code);
    };
    $has = function ($key) use (&$d) {
        return array_key_exists($key, $d);
    };
    $max = pg_image_draw_data_max();

    if (!is_array($d) || pg_image_draw_data_list($d) && ($d !== array())) {
        return $fail('data_table');
    }

    // Number(): a number, a string of one, true as 1.
    $table = $d['table'] ?? null;
    $table = (is_int($table) || is_float($table)) ? $table : ((is_string($table) && is_numeric(trim($table))) ? (float) trim($table) : (($table === true) ? 1 : 0));

    if (!($table > 0) || (floor($table) != $table) || is_infinite((float) $table)) {
        return $fail('data_table');
    }

    $out = array('table' => (int) $table);
    $out['show'] = $has('show') ? $d['show'] : 'chart';

    if (($out['show'] !== 'chart') && ($out['show'] !== 'table')) {
        return $fail('data_show');
    }

    $out['kind'] = ($out['show'] === 'table') ? 'table' : ($has('kind') ? $d['kind'] : 'bar');

    if (($out['show'] === 'chart') && !in_array($out['kind'], array('bar', 'stacked_bar', 'line', 'area', 'donut'), true)) {
        return $fail('data_kind');
    }

    $out['group'] = $has('group') ? $d['group'] : 'none';

    if (!in_array($out['group'], array('none', 'sum', 'count', 'average'), true)) {
        return $fail('data_group');
    }

    $values = $has('values') ? $d['values'] : array();

    if (!pg_image_draw_data_column_id($d['label'] ?? null) || !pg_image_draw_data_list($values) || (count($values) > $max['values'])
        || (count(array_filter($values, 'pg_image_draw_data_column_id')) !== count($values))) {
        return $fail('data_columns');
    }

    $out['values'] = array();
    $out['label'] = is_string($d['label']) ? $d['label'] : pg_image_draw_data_js_number($d['label']);

    foreach ($values as $v) {
        $v = is_string($v) ? $v : pg_image_draw_data_js_number($v);

        if (!in_array($v, $out['values'], true)) {
            $out['values'][] = $v;
        }
    }

    if ($out['group'] === 'count') {
        $out['values'] = array();
    } elseif (($out['show'] === 'chart') && (!$out['values'] || (($out['kind'] === 'donut') && (count($out['values']) > 1)))) {
        return $fail('data_columns');
    }

    $out['sort'] = $has('sort') ? $d['sort'] : 'none';
    $out['dir'] = $has('dir') ? $d['dir'] : 'asc';

    if (!in_array($out['sort'], array('none', 'label', 'value'), true) || (($out['dir'] !== 'asc') && ($out['dir'] !== 'desc'))) {
        return $fail('data_sort');
    }

    $out['format'] = $has('format') ? $d['format'] : 'en';

    if (($out['format'] !== 'en') && ($out['format'] !== 'tr')) {
        return $fail('data_format');
    }

    $out['title'] = $has('title') ? $d['title'] : '';

    if (!is_string($out['title']) || (mb_strlen($out['title']) > $max['title']) || preg_match('/[\n\r]/', $out['title'])) {
        return $fail('data_title');
    }

    $out['title'] = (string) preg_replace('/^\s+|\s+$/u', '', $out['title']);
    $colours = $has('colours') ? $d['colours'] : array();

    if (!pg_image_draw_data_list($colours) || (count($colours) > $max['colours'])) {
        return $fail('data_colours');
    }

    foreach ($colours as $c) {
        if (!is_string($c) || !preg_match('/^#[0-9a-fA-F]{6}$/', $c)) {
            return $fail('data_colours');
        }
    }

    $out['colours'] = array_map('strtolower', $colours);
    $out['theme'] = $has('theme') ? $d['theme'] : 'light';

    if (!is_string($out['theme']) || !isset(pg_image_draw_data_guide()['palettes'][$out['theme']])) {
        return $fail('data_theme');
    }

    $out['font'] = $has('font') ? $d['font'] : 'g:Inter';
    $font = $out['font'];

    if (!is_string($font) || !(in_array($font, array('sans', 'serif', 'hand', 'display', 'mono'), true)
        || ((substr($font, 0, 2) === 'g:') && (strlen($font) > 2) && (pg_image_draw_data_family_name(substr($font, 2)) === substr($font, 2))))) {
        return $fail('data_font');
    }

    $out['legend'] = $has('legend') ? pg_image_draw_data_truthy($d['legend']) : true;

    return array('ok' => true, 'data' => $out);
}

/**
 * A cell as a number (M.dataNumber()): a number, or a string that is one
 * ('1250.5'); else null.
 *
 * @param mixed $v
 * @return int|float|null
 */
function pg_image_draw_data_number($v)
{
    if (is_int($v) || (is_float($v) && is_finite($v))) {
        return $v;
    }

    if (is_string($v) && preg_match('/^\s*-?(\d+\.?\d*|\.\d+)\s*$/', $v)) {
        return (float) trim($v);
    }

    return null;
}

/**
 * A cell as words (M.dataText()): a list joined by commas, an object or
 * nothing as ''.
 *
 * @param mixed $v
 * @return string
 */
function pg_image_draw_data_text($v)
{
    if (pg_image_draw_data_list($v)) {
        return implode(', ', array_filter(array_map('pg_image_draw_data_text', $v), function ($s) {
            return $s !== '';
        }));
    }

    if (is_int($v) || (is_float($v) && is_finite($v))) {
        return pg_image_draw_data_js_number($v);
    }

    if (is_bool($v)) {
        return $v ? '1' : '0';
    }

    return is_string($v) ? (string) preg_replace('/^\s+|\s+$/u', '', (string) preg_replace('/\s+/u', ' ', $v)) : '';
}

/**
 * A number as the chart writes it (M.dataFormat(), ws_canvas_chart_number()):
 * whole below 10,000 with a decimal when it has one, else in thousands
 * with one decimal and K (en) or B (tr); a minus kept. Full: a plain
 * table's cell, every digit with up to two decimals.
 *
 * @param float  $n
 * @param string $format en | tr
 * @param bool   $full
 * @return string
 */
function pg_image_draw_data_format($n, $format, $full = false)
{
    $tr = ($format === 'tr');
    $minus = ($n < 0) ? '-' : '';
    $a = abs($n);
    $short = !$full && ($a >= 10000);

    if ($full) {
        $a = pg_image_draw_data_js_round($a * 100) / 100;
        $places = (floor($a) == $a) ? 0 : ((pg_image_draw_data_js_round($a * 10) / 10 == $a) ? 1 : 2);
    } else {
        $a = $short ? pg_image_draw_data_js_round($a / 100) / 10 : pg_image_draw_data_js_round($a * 10) / 10;
        $places = (floor($a) == $a) ? 0 : 1;
    }

    $parts = explode('.', number_format($a, $places, '.', ''));
    $whole = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', $tr ? '.' : ',', $parts[0]);

    return $minus . $whole . (isset($parts[1]) ? ($tr ? ',' : '.') . $parts[1] : '') . ($short ? ($tr ? ' B' : 'K') : '');
}

/**
 * The step of a value axis (M.dataStep(), ws_canvas_chart_step()).
 *
 * @param float $max
 * @param int   $steps
 * @return float
 */
function pg_image_draw_data_step($max, $steps)
{
    if ($max <= 0) {
        return 1;
    }

    $unit = pow(10, floor(log10($max / $steps) + 1e-12));

    foreach (array(1, 2, 2.5, 5, 10) as $m) {
        if ($max / ($unit * $m) <= $steps) {
            return $unit * $m;
        }
    }

    return $unit * 10;
}

/**
 * Two labels compared as the model sorts them: as numbers when both are,
 * else as words without case.
 *
 * @param string $a
 * @param string $b
 * @return float|int
 */
function pg_image_draw_data_compare($a, $b)
{
    $x = pg_image_draw_data_number($a);
    $y = pg_image_draw_data_number($b);

    if (($x !== null) && ($y !== null)) {
        return $x - $y;
    }

    return strcmp(mb_strtolower((string) $a), mb_strtolower((string) $b));
}

/**
 * The rows of a table as the description shows them (M.dataSeries()):
 * labels, series [{name, values, texts}], cut. Rows: [{cells: {column id:
 * value}}] in the table's order, 5000 at most taken; columns: {id: {name}}.
 * Grouping gathers the rows of one label in the order they first come;
 * count counts them, average is over the cells that are numbers. A chart
 * keeps 24 labels, a donut 5 and Other, a table 50; cut is how many were
 * left out.
 *
 * @param array $d       pg_image_draw_data_check()'s data
 * @param array $columns
 * @param array $rows
 * @param array $words   count, other
 * @return array labelName, labels, series, cut
 */
function pg_image_draw_data_series($d, $columns, $rows, $words)
{
    $max = pg_image_draw_data_max();
    $columns = is_array($columns) ? $columns : array();
    $rows = array_slice(pg_image_draw_data_list($rows) ? $rows : array(), 0, $max['rows']);
    $name = function ($id) use ($columns) {
        return (is_array($columns[$id] ?? null) && isset($columns[$id]['name'])) ? (string) $columns[$id]['name'] : '';
    };
    $names = ($d['group'] === 'count') ? array((string) (($words['count'] ?? '') ?: 'Count')) : array_map($name, $d['values']);
    $keys = array();
    $by = array();
    $zeros = array_fill(0, count($d['values']), 0);

    foreach ($rows as $r) {
        $cells = (is_array($r) && is_array($r['cells'] ?? null)) ? $r['cells'] : array();
        $label = mb_substr(pg_image_draw_data_text($cells[$d['label']] ?? null), 0, $max['cell']);
        $key = ($d['group'] === 'none') ? 'r' . count($keys) : '=' . $label;

        if (!isset($by[$key])) {
            $by[$key] = array('label' => $label, 'n' => 0, 'sums' => $zeros, 'counts' => $zeros, 'texts' => array_fill(0, count($d['values']), null));
            $keys[] = $key;
        }

        $by[$key]['n']++;

        foreach ($d['values'] as $j => $id) {
            $v = pg_image_draw_data_number($cells[$id] ?? null);

            if ($v !== null) {
                $by[$key]['sums'][$j] += $v;
                $by[$key]['counts'][$j]++;
            } elseif ($d['group'] === 'none') {
                $by[$key]['texts'][$j] = mb_substr(pg_image_draw_data_text($cells[$id] ?? null), 0, $max['cell']);
            }
        }
    }

    $entries = array();

    foreach ($keys as $key) {
        $e = $by[$key];
        $values = array();

        foreach ($e['sums'] as $j => $s) {
            $values[] = ($d['group'] === 'average') ? ($e['counts'][$j] ? pg_image_draw_data_round($s / $e['counts'][$j]) : 0) : pg_image_draw_data_round($s);
        }

        $entries[] = array('label' => $e['label'], 'values' => ($d['group'] === 'count') ? array($e['n']) : $values, 'texts' => ($d['group'] === 'count') ? array(null) : $e['texts']);
    }

    if ($d['sort'] !== 'none') {
        $pairs = array();

        foreach ($entries as $i => $e) {
            $pairs[] = array($e, $i);
        }

        usort($pairs, function ($a, $b) use ($d) {
            $c = ($d['sort'] === 'label') ? pg_image_draw_data_compare($a[0]['label'], $b[0]['label']) : array_sum($a[0]['values']) - array_sum($b[0]['values']);

            if ($c != 0) {
                return (($d['dir'] === 'desc') ? -$c : $c) < 0 ? -1 : 1;
            }

            return $a[1] - $b[1];
        });
        $entries = array_column($pairs, 0);
    }

    $cut = 0;

    if (($d['kind'] === 'donut') && (count($entries) > $max['slices'])) {
        $other = array('label' => (string) (($words['other'] ?? '') ?: 'Other'), 'values' => array(0), 'texts' => array(null));

        for ($i = $max['slices'] - 1; $i < count($entries); $i++) {
            $other['values'][0] = pg_image_draw_data_round($other['values'][0] + $entries[$i]['values'][0]);
        }

        $cut = count($entries) - $max['slices'] + 1;
        $entries = array_merge(array_slice($entries, 0, $max['slices'] - 1), array($other));
    } else {
        $keep = ($d['kind'] === 'table') ? $max['tableRows'] : $max['labels'];
        $cut = max(0, count($entries) - $keep);
        $entries = array_slice($entries, 0, $keep);
    }

    $series = array();

    foreach ($names as $j => $n) {
        $series[] = array('name' => $n, 'values' => array_map(function ($e) use ($j) {
            return $e['values'][$j];
        }, $entries), 'texts' => array_map(function ($e) use ($j) {
            return $e['texts'][$j];
        }, $entries));
    }

    return array('labelName' => $name($d['label']), 'labels' => array_column($entries, 'label'), 'series' => $series, 'cut' => $cut);
}

/**
 * A colour mixed into the board by the guide's area share, solid.
 *
 * @param string $colour     #rrggbb
 * @param string $background #rrggbb
 * @return string
 */
function pg_image_draw_data_wash($colour, $background)
{
    $t = pg_image_draw_data_guide()['marks']['areaMix'] / 100;
    $out = '#';

    for ($i = 1; $i < 7; $i += 2) {
        $out .= sprintf('%02x', (int) pg_image_draw_data_js_round(hexdec(substr($colour, $i, 2)) * $t + hexdec(substr($background, $i, 2)) * (1 - $t)));
    }

    return $out;
}

/**
 * The points of an arc around (cx, cy) from angle a to b, in pieces of at
 * most three degrees.
 *
 * @return array[]
 */
function pg_image_draw_data_arc($cx, $cy, $r, $a, $b)
{
    $n = max(2, (int) ceil(abs($b - $a) / (M_PI / 60) - 1e-9));
    $out = array();

    for ($i = 0; $i <= $n; $i++) {
        $t = $a + ($b - $a) * $i / $n;
        $out[] = array($cx + $r * cos($t), $cy + $r * sin($t));
    }

    return $out;
}

/**
 * The drawing of a data layer as marks in its box's frame (M.dataMarks()),
 * in drawing order: {t: poly, pts, fill}, {t: line, pts, stroke, width},
 * {t: text, x, y (the baseline), v, size, weight, fill, anchor}. The board
 * is laid out in the guide's units (675 on its shorter side) and scaled to
 * the box.
 *
 * @param array      $d     pg_image_draw_data_check()'s data
 * @param float      $w
 * @param float      $h
 * @param array|null $read  pg_image_draw_data_series(), state refused, or
 *                          null before the rows are read
 * @param array      $words empty, refused, more (with %d)
 * @return array[]
 */
function pg_image_draw_data_marks($d, $w, $h, $read, $words)
{
    $g = pg_image_draw_data_guide();
    $p = $g['palettes'][$d['theme']];
    $mk = $g['marks'];
    $s = max(0.01, min($w, $h) / $g['base']);
    $W = $w / $s;
    $H = $h / $s;
    $M0 = $g['grid']['margin'];
    $series = (is_array($read) && isset($read['series'])) ? $read['series'] : array();
    $labels = (is_array($read) && isset($read['labels'])) ? $read['labels'] : array();
    $n = count($labels);
    $refused = is_array($read) && (($read['state'] ?? '') === 'refused');
    $marks = array();

    $pt = function ($x, $y) use ($s) {
        return array(pg_image_draw_data_round($x * $s), pg_image_draw_data_round($y * $s));
    };
    $poly = function ($pts, $fill) use (&$marks, $pt) {
        $marks[] = array('t' => 'poly', 'pts' => array_map(function ($q) use ($pt) {
            return $pt($q[0], $q[1]);
        }, $pts), 'fill' => $fill);
    };
    $rect = function ($x, $y, $rw, $rh, $fill) use ($poly) {
        if (($rw > 0) && ($rh > 0)) {
            $poly(array(array($x, $y), array($x + $rw, $y), array($x + $rw, $y + $rh), array($x, $y + $rh)), $fill);
        }
    };
    $line = function ($pts, $stroke, $width) use (&$marks, $pt, $s) {
        $marks[] = array('t' => 'line', 'pts' => array_map(function ($q) use ($pt) {
            return $pt($q[0], $q[1]);
        }, $pts), 'stroke' => $stroke, 'width' => pg_image_draw_data_round($width * $s));
    };
    $text = function ($x, $y, $v, $role, $fill = null, $anchor = null) use (&$marks, $g, $p, $s) {
        $r = $g['type'][$role];

        if ($v !== '') {
            $marks[] = array('t' => 'text', 'x' => pg_image_draw_data_round($x * $s), 'y' => pg_image_draw_data_round($y * $s), 'v' => $v,
                'size' => pg_image_draw_data_round($r[0] * $s), 'weight' => $r[1], 'fill' => $fill ?: $p[$r[2]], 'anchor' => $anchor ?: 'start');
        }
    };
    // Words cut to a width, by the guide's average letter of 0.56 em.
    $fit = function ($v, $role, $room) use ($g) {
        $most = max(1, (int) floor($room / ($g['type'][$role][0] * 0.56)));

        return (mb_strlen($v) > $most) ? mb_substr($v, 0, max(1, $most - 1)) . '…' : $v;
    };
    $dot = function ($x, $y, $colour) use ($poly, $mk, $p) {
        $poly(array_slice(pg_image_draw_data_arc($x, $y, $mk['dot'] + $mk['ring'], 0, 2 * M_PI), 0, -1), $p['background']);
        $poly(array_slice(pg_image_draw_data_arc($x, $y, $mk['dot'], 0, 2 * M_PI), 0, -1), $colour);
    };
    $axis = function ($x0, $x1, $top, $bot, $max) use ($rect, $text, $mk, $p, $d) {
        $step = pg_image_draw_data_step($max, $mk['ticksMax']);
        $k = max(1, (int) ceil($max / $step - 1e-9));

        if ($max > $step * ($k - 0.25)) {
            $k++;
        }

        for ($i = 0; $i <= $k; $i++) {
            $y = $bot - ($bot - $top) * $i / $k;

            if ($i) {
                $rect($x0, $y, $x1 - $x0, $mk['grid'], $p['grid']);
            } else {
                $rect($x0, $y - $mk['baseline'] / 2, $x1 - $x0, $mk['baseline'], $p['baseline']);
            }

            $text($x0 - 14, $y + 6, pg_image_draw_data_format($step * $i, $d['format']), 'axis', null, 'end');
        }

        return $step * $k;
    };

    $rect(0, 0, $W, $H, $p['background']);
    $colours = $d['colours'] ? $d['colours'] : (((count($series) === 1) && ($d['kind'] !== 'donut')) ? array($p['series'][0]) : $p['series']);
    $empty = !is_array($read) || $refused || !$n;
    $legend = array();

    if (!$empty && $d['legend'] && (count($series) > 1) && ($d['kind'] !== 'donut') && ($d['kind'] !== 'table')) {
        foreach ($series as $j => $one) {
            $legend[] = array($fit($one['name'], 'label', $W / 3), $colours[$j % count($colours)]);
        }
    }

    // The head: title and legend; the plot starts below it.
    $top = $M0;
    $x = $M0;

    if ($d['title'] !== '') {
        $top += $g['type']['title'][0] * 0.8;
        $text($M0, $top, $fit($d['title'], 'title', $W - 2 * $M0), 'title');
    }

    if ($legend) {
        $top += (($d['title'] !== '') ? $g['spacing']['legend'] : 0) + $g['type']['label'][0];

        foreach ($legend as $e) {
            $rect($x, $top - 15, 16, 16, $e[1]);
            $text($x + 24, $top, $e[0], 'label');
            $x += 24 + mb_strlen($e[0]) * $g['type']['label'][0] * 0.56 + $g['grid']['gutter'];
        }
    }

    $top += (($d['title'] !== '') || $legend) ? $g['spacing']['plot'] : 0;
    $bot = $H - $M0 - $g['type']['axis'][0] - 14;

    if ($empty) {
        $text($W / 2, max($top, ($top + $H - $M0) / 2), $fit((string) ($refused ? ($words['refused'] ?? '') : ($words['empty'] ?? '')), 'label', $W - 2 * $M0), 'label', $p['ink3'], 'middle');

        return $marks;
    }

    $every = max(1, (int) ceil($n / 12));

    if ($d['kind'] === 'table') {
        $row = $mk['row'];
        $x0 = $M0;
        $x1 = $W - $M0;
        $cols = count($series);
        $first = $cols ? ($x1 - $x0) * 0.4 : ($x1 - $x0);
        $each = $cols ? ($x1 - $x0 - $first) / $cols : 0;
        $room = (int) floor(($H - $M0 - $top - $row) / $row);
        $shown = min($n, max(0, $room));
        $more = $n + (int) ($read['cut'] ?? 0) - $shown;
        $y = $top;

        if ($more > 0) {
            $shown = max(0, min($n, $room - 1));
            $more = $n + (int) ($read['cut'] ?? 0) - $shown;
        }

        $text($x0, $y + 28, $fit((string) ($read['labelName'] ?? ''), 'label', $first - 16), 'label');

        foreach ($series as $j => $one) {
            $text($x0 + $first + $each * ($j + 1), $y + 28, $fit($one['name'], 'label', $each - 16), 'label', null, 'end');
        }

        $y += $row;
        $rect($x0, $y - $mk['baseline'] / 2, $x1 - $x0, $mk['baseline'], $p['baseline']);

        for ($i = 0; $i < $shown; $i++) {
            $text($x0, $y + 29, $fit($labels[$i], 'axis', $first - 16), 'axis', $p['ink']);

            foreach ($series as $j => $one) {
                $v = ($one['texts'][$i] !== null) ? $one['texts'][$i] : pg_image_draw_data_format($one['values'][$i], $d['format'], true);
                $text($x0 + $first + $each * ($j + 1), $y + 29, $fit($v, 'axis', $each - 16), 'axis', $p['ink'], 'end');
            }

            $y += $row;
            $rect($x0, $y, $x1 - $x0, $mk['grid'], $p['grid']);
        }

        if ($more > 0) {
            $text($x0, $y + 29, str_replace('%d', (string) $more, (string) ($words['more'] ?? '')), 'note');
        }
    } elseif ($d['kind'] === 'donut') {
        $values = array_map(function ($v) {
            return max(0, $v);
        }, $series[0]['values']);
        $total = array_sum($values);
        $wide = ($W >= 1000);
        $R = max(40, min($mk['donutOuter'], ($W - 2 * $M0) / 2, $wide ? ($H - $M0 - $top) / 2 : ($H - $M0 - $top - 64 * $n) / 2));
        $r = $R * $mk['donutInner'] / $mk['donutOuter'];
        $cx = $wide ? $M0 + $R + 90 : $W / 2;
        $cy = $wide ? max($top + $R, ($top + $H) / 2) : $top + $R;
        $a = -M_PI / 2;
        $live = count(array_filter($values, function ($v) {
            return $v > 0;
        }));
        $lx = $wide ? $cx + $R + 110 : $M0;
        $rx = $W - $M0;
        $ly = $wide ? $cy - ($n - 1) * 32 : $cy + $R + 64;
        $colour = function ($i) use ($d, $p) {
            return $d['colours'] ? $d['colours'][$i % count($d['colours'])] : (($i < count($p['series'])) ? $p['series'][$i] : $p['other']);
        };
        $gap = ($live > 1) ? 0.012 : 0;

        foreach ($values as $i => $v) {
            if ($v <= 0) {
                continue;
            }

            $b = $a + 2 * M_PI * $v / $total;
            $poly(array_merge(pg_image_draw_data_arc($cx, $cy, $R, $a + $gap, $b - $gap), pg_image_draw_data_arc($cx, $cy, $r, $b - $gap * $R / $r, $a + $gap * $R / $r)), $colour($i));
            $a = $b;
        }

        $text($cx, $cy + 20, pg_image_draw_data_format($total, $d['format']), 'figure', null, 'middle');

        foreach ($values as $i => $v) {
            $y = $ly + $i * 64;
            $rect($lx, $y - 16, 18, 18, $colour($i));
            $text($lx + 32, $y, $fit($labels[$i], 'label', $rx - $lx - 180), 'label', $p['ink']);
            $text($rx, $y, ($total ? (int) pg_image_draw_data_js_round($v / $total * 100) : 0) . ' %', 'label', $p['ink'], 'end');
            $text($rx - 100, $y, pg_image_draw_data_format($v, $d['format']), 'axis', null, 'end');
        }
    } elseif (($d['kind'] === 'line') || ($d['kind'] === 'area')) {
        $x0 = $M0 + $g['grid']['axis'];
        $x1 = $W - $M0 - 90;
        $max = 1e-9;

        foreach ($series as $one) {
            foreach ($one['values'] as $v) {
                $max = max($max, $v);
            }
        }

        $peak = $axis($x0, $x1, $top, $bot, $max);
        $X = function ($i) use ($n, $x0, $x1) {
            return ($n > 1) ? $x0 + 12 + ($x1 - $x0 - 24) * $i / ($n - 1) : ($x0 + $x1) / 2;
        };
        $Y = function ($v) use ($bot, $top, $peak) {
            return $bot - ($bot - $top) * max(0, $v) / $peak;
        };

        for ($i = 0; $i < $n; $i++) {
            if (($i % $every === 0) || ($i === $n - 1)) {
                $text($X($i), $bot + 32, $fit($labels[$i], 'axis', ($x1 - $x0) / min($n, 12) - 8), 'axis', null, 'middle');
            }
        }

        foreach ($series as $j => $one) {
            $c = $colours[$j % count($colours)];
            $pts = array();

            foreach ($one['values'] as $i => $v) {
                $pts[] = array($X($i), $Y($v));
            }

            $end = $one['values'][$n - 1];

            if ($d['kind'] === 'area') {
                $poly(array_merge($pts, array(array($X($n - 1), $bot), array($X(0), $bot))), pg_image_draw_data_wash($c, $p['background']));
            }

            $line($pts, $c, $mk['line']);
            $dot($X($n - 1), $Y($end), $c);
            $text($X($n - 1) + 18, $Y($end) + 7, pg_image_draw_data_format($end, $d['format']), 'label', $p['ink']);
        }
    } else {
        $x0 = $M0 + $g['grid']['axis'];
        $x1 = $W - $M0;
        $stacked = ($d['kind'] === 'stacked_bar');
        $totals = array();

        for ($i = 0; $i < $n; $i++) {
            $totals[$i] = 0;

            foreach ($series as $one) {
                $v = max(0, $one['values'][$i]);
                $totals[$i] = $stacked ? $totals[$i] + $v : max($totals[$i], $v);
            }
        }

        $peak = $axis($x0, $x1, $top, $bot, max(max($totals), 1e-9));
        $Y = function ($v) use ($bot, $top, $peak) {
            return $bot - ($bot - $top) * $v / $peak;
        };
        $band = ($x1 - $x0) / $n;
        $k = $stacked ? 1 : count($series);
        $bw = min($mk['barMax'], $band * $mk['barBand'] / 100 / $k);
        $radius = min($mk['barRadius'], $bw / 2);
        $mark = array_search(max($totals), $totals);
        $last = count($series) - 1;

        for ($i = 0; $i < $n; $i++) {
            $cx = $x0 + $band * ($i + 0.5);
            $acc = 0;

            foreach ($series as $j => $one) {
                $v = max(0, $one['values'][$i]);
                $x = $stacked ? $cx - $bw / 2 : $cx - ($k * $bw + ($k - 1) * $mk['groupGap']) / 2 + $j * ($bw + $mk['groupGap']);
                $y0 = $Y($acc) - (($stacked && $j) ? $mk['stackGap'] : 0);
                $y1 = $Y($acc + $v);

                if ($y0 - $y1 > 0.5) {
                    if (!$stacked || ($j === $last)) {
                        $rr = min($radius, ($y0 - $y1) / 2);
                        $poly(array_merge(array(array($x, $y0)), pg_image_draw_data_arc($x + $rr, $y1 + $rr, $rr, M_PI, 1.5 * M_PI),
                            pg_image_draw_data_arc($x + $bw - $rr, $y1 + $rr, $rr, 1.5 * M_PI, 2 * M_PI), array(array($x + $bw, $y0))), $colours[$j % count($colours)]);
                    } else {
                        $rect($x, $y1, $bw, $y0 - $y1, $colours[$j % count($colours)]);
                    }
                }

                if ($stacked) {
                    $acc += $v;
                }
            }

            if (($i % $every === 0) || ($i === $n - 1)) {
                $text($cx, $bot + 32, $fit($labels[$i], 'axis', $band * $every - 8), 'axis', null, 'middle');
            }

            if ($i === $mark) {
                $text($cx, $Y($totals[$i]) - 14, pg_image_draw_data_format($totals[$i], $d['format']), 'label', $p['ink'], 'middle');
            }
        }
    }

    return $marks;
}

/**
 * A data layer's marks for its box, as the browser's layer type draws it
 * (dataMarksOf() in pg_image_editor_data.js): a description that does not
 * pass its check draws as an empty layer; the rows go through
 * pg_image_draw_data_series().
 *
 * @param mixed      $data  the layer's description
 * @param float      $w
 * @param float      $h
 * @param array|null $read  columns and rows, state refused, or null unread
 * @param array      $words empty, refused, more, count, other
 * @return array font (the description's), marks
 */
function pg_image_draw_data_layer_marks($data, $w, $h, $read, $words)
{
    $c = pg_image_draw_data_check($data);

    if (!$c['ok']) {
        $fallback = pg_image_draw_data_check(array('table' => 1, 'label' => 'x', 'values' => array('x')));

        return array('font' => 'g:Inter', 'marks' => pg_image_draw_data_marks($fallback['data'], $w, $h, array('labels' => array(), 'series' => array()), $words));
    }

    if (is_array($read) && (($read['state'] ?? '') !== 'refused')) {
        $read = pg_image_draw_data_series($c['data'], $read['columns'] ?? array(), $read['rows'] ?? array(), $words);
    }

    return array('font' => $c['data']['font'], 'marks' => pg_image_draw_data_marks($c['data'], $w, $h, is_array($read) ? $read : null, $words));
}
