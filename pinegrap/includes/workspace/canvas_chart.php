<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a chart drawn by the server as a canvas operation (chart):
 * the agent gives the kind, the data and the words, and the drawing guide
 * (canvas_guide.php) decides the board, grid, type, colours and marks. The
 * chart is written as SVG and laid in as the svg operation does with layers
 * split, so every text is a text layer and every mark a layer a person moves
 * or recolours in the Design Canvas. A chart of one series takes the colour
 * of the channel it is drawn in, at a tone that reads on its board.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_guide.php');

/**
 * The limits of a chart's data. Pure.
 *
 * @return array
 */
function ws_canvas_chart_limits()
{
    return array('labels' => 60, 'label' => 40, 'title' => 120, 'line' => 160, 'value' => 1e15, 'slices' => 6);
}

/**
 * A #rrggbb colour in OKLab (L, a, b). Pure.
 *
 * @param string $hex
 * @return float[]
 */
function ws_canvas_chart_oklab($hex)
{
    $c = array();

    foreach (array(1, 3, 5) as $i) {
        $v = hexdec(substr($hex, $i, 2)) / 255;
        $c[] = ($v <= 0.04045) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }

    $l = pow(0.4122214708 * $c[0] + 0.5363325363 * $c[1] + 0.0514459929 * $c[2], 1 / 3);
    $m = pow(0.2119034982 * $c[0] + 0.6806995451 * $c[1] + 0.1073969566 * $c[2], 1 / 3);
    $s = pow(0.0883024619 * $c[0] + 0.2817188376 * $c[1] + 0.6299787005 * $c[2], 1 / 3);

    return array(
        0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s,
        1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s,
        0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s,
    );
}

/**
 * An OKLab colour as #rrggbb, clipped into sRGB. Pure.
 *
 * @param float[] $lab
 * @return string
 */
function ws_canvas_chart_hex($lab)
{
    $l = pow($lab[0] + 0.3963377774 * $lab[1] + 0.2158037573 * $lab[2], 3);
    $m = pow($lab[0] - 0.1055613458 * $lab[1] - 0.0638541728 * $lab[2], 3);
    $s = pow($lab[0] - 0.0894841775 * $lab[1] - 1.2914855480 * $lab[2], 3);
    $out = '#';

    foreach (array(
        4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
        -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
        -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
    ) as $v) {
        $v = max(0.0, min(1.0, $v));
        $v = ($v <= 0.0031308) ? 12.92 * $v : 1.055 * pow($v, 1 / 2.4) - 0.055;
        $out .= sprintf('%02x', (int) round($v * 255));
    }

    return $out;
}

/**
 * The WCAG contrast ratio of two #rrggbb colours. Pure.
 *
 * @param string $a
 * @param string $b
 * @return float
 */
function ws_canvas_chart_contrast($a, $b)
{
    $lum = function ($hex) {
        $c = array();

        foreach (array(1, 3, 5) as $i) {
            $v = hexdec(substr($hex, $i, 2)) / 255;
            $c[] = ($v <= 0.04045) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };

    $x = $lum($a);
    $y = $lum($b);

    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

/**
 * The colour of a one-series chart: the channel's colour where it reads as a
 * mark on the board (3:1), else its own hue lightened on a dark board or
 * darkened on a light one until it does, else the guide's first colour; the
 * guide's first colour too for no channel colour. Pure.
 *
 * @param string $channel #rrggbb, or '' for none
 * @param array  $palette a palette of ws_canvas_guide_values()
 * @return string
 */
function ws_canvas_chart_tone($channel, $palette)
{
    $board = $palette['background'];

    if (!is_string($channel) || !preg_match('/^#[0-9a-f]{6}$/', strtolower($channel))) {
        return $palette['series'][0];
    }

    $channel = strtolower($channel);

    if (ws_canvas_chart_contrast($channel, $board) >= 3) {
        return $channel;
    }

    $lab = ws_canvas_chart_oklab($channel);
    $step = (ws_canvas_chart_oklab($board)[0] < 0.5) ? 0.02 : -0.02;

    for ($i = 0; $i < 40; $i++) {
        $lab[0] += $step;

        if (($lab[0] <= 0) || ($lab[0] >= 1)) {
            break;
        }

        $hex = ws_canvas_chart_hex($lab);

        if (ws_canvas_chart_contrast($hex, $board) >= 3) {
            return $hex;
        }
    }

    return $palette['series'][0];
}

/**
 * The chart operation as the edit route and edit_canvas describe it. Pure.
 *
 * @return string
 */
function ws_canvas_chart_op_text()
{
    return 'chart (kind bar, stacked_bar, line, area, donut, sparkline or figure_tile; theme dark or light, light when left out; board card (the default), slide, square, portrait or story; title, subtitle and note; labels and values for one series, or series of up to five {name, values}, a value of 0 or more per label; highlight, the label whose value is written, the highest when left out; format en or tr for the numbers; a sparkline or a figure tile takes label, value and values, a tile also delta such as +12% and vs): the whole canvas drawn anew by the drawing guide, every text a text layer and every mark its own layer. A chart of one series takes the channel\'s colour at a tone that reads on its board; send it again with changed data to redraw it.';
}

/**
 * The colour of a channel row for a one-series chart: its palette colour
 * (stored as its place, or given as #rrggbb), or '' when it has none.
 *
 * @param array $channel
 * @return string
 */
function ws_canvas_chart_channel_tone($channel)
{
    $color = $channel['color'] ?? 0;

    if (is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color)) {
        return strtolower($color);
    }

    return (((int) $color > 0) && function_exists('ws_palette_hex')) ? ws_palette_hex((int) $color) : '';
}

/**
 * Checks a chart operation and fills in its defaults: the refusal code, or
 * the chart (kind, theme, board, words, labels, series, highlight). Pure.
 *
 * @param array $op
 * @return array error ('' or a code of ws_canvas_edit_error_text()), chart
 */
function ws_canvas_chart_check($op)
{
    $v = ws_canvas_guide_values();
    $max = ws_canvas_chart_limits();
    $fail = function ($error) {
        return array('error' => $error, 'chart' => null);
    };
    $text = function ($value, $limit) {
        return (is_string($value) && (mb_strlen(trim($value)) <= $limit)) ? trim(preg_replace('/[\x00-\x1f]+/u', ' ', $value)) : null;
    };
    $numbers = function ($list) use ($max) {
        if (!is_array($list) || ($list === array()) || (array_values($list) !== $list)) {
            return null;
        }

        foreach ($list as $n) {
            if (!(is_int($n) || is_float($n)) || ($n < 0) || ($n > $max['value']) || is_nan((float) $n)) {
                return null;
            }
        }

        return array_map('floatval', $list);
    };

    $kind = (string) ($op['kind'] ?? '');

    if (!in_array($kind, $v['kinds'], true)) {
        return $fail('chart_kind');
    }

    $chart = array('kind' => $kind, 'theme' => (string) ($op['theme'] ?? 'light'), 'format' => (string) ($op['format'] ?? 'en'));

    if (!in_array($chart['theme'], array('dark', 'light'), true) || !in_array($chart['format'], array('en', 'tr'), true)) {
        return $fail('chart_theme');
    }

    $boards = array_column($v['boards'], null, 'name');
    $own = array('figure_tile' => 'figure_tile', 'sparkline' => 'sparkline_strip');
    $board = isset($own[$kind]) ? $own[$kind] : (string) ($op['board'] ?? 'card');

    if (!isset($boards[$board]) || (isset($own[$kind]) && isset($op['board']) && ($op['board'] !== $own[$kind])) || (!isset($own[$kind]) && in_array($board, $own, true))) {
        return $fail('chart_board');
    }

    $chart['board'] = $boards[$board];

    foreach (array('title' => $max['title'], 'subtitle' => $max['line'], 'note' => $max['line'], 'label' => $max['line'], 'value' => $max['label'], 'delta' => $max['label'], 'vs' => $max['line']) as $key => $limit) {
        $chart[$key] = array_key_exists($key, $op) ? $text($op[$key], $limit) : '';

        if ($chart[$key] === null) {
            return $fail('chart_words');
        }
    }

    if (isset($own[$kind]) && (($chart['label'] === '') || ($chart['value'] === ''))) {
        return $fail('chart_words');
    }

    if (isset($own[$kind])) {
        $chart['values'] = array_key_exists('values', $op) ? $numbers($op['values']) : array();

        if (($chart['values'] === null) || (count($chart['values']) > $max['labels']) || (($kind === 'sparkline') && (count($chart['values']) < 2))) {
            return $fail('chart_data');
        }

        return array('error' => '', 'chart' => $chart);
    }

    if ($chart['title'] === '') {
        return $fail('chart_words');
    }

    $labels = $op['labels'] ?? null;

    if (!is_array($labels) || ($labels === array()) || (array_values($labels) !== $labels) || (count($labels) > $max['labels'])) {
        return $fail('chart_data');
    }

    foreach ($labels as $i => $label) {
        $labels[$i] = (is_string($label) || is_int($label)) ? $text((string) $label, $max['label']) : null;

        if ($labels[$i] === null) {
            return $fail('chart_data');
        }
    }

    $series = $op['series'] ?? (array_key_exists('values', $op) ? array(array('name' => '', 'values' => $op['values'])) : null);

    if (!is_array($series) || ($series === array()) || (array_values($series) !== $series) || (count($series) > $v['marks']['series_max'])) {
        return $fail('chart_data');
    }

    foreach ($series as $j => $one) {
        $values = is_array($one) ? $numbers($one['values'] ?? null) : null;
        $name = is_array($one) ? $text((string) ($one['name'] ?? ''), $max['label']) : null;

        if (($values === null) || ($name === null) || (count($values) !== count($labels))) {
            return $fail('chart_data');
        }

        $series[$j] = array('name' => $name, 'values' => $values);
    }

    if ((($kind === 'donut') || ($kind === 'area')) && (count($series) !== 1)) {
        return $fail('chart_data');
    }

    if (($kind === 'donut') && ((count($labels) > $max['slices']) || (array_sum($series[0]['values']) <= 0))) {
        return $fail('chart_data');
    }

    if (in_array($kind, array('line', 'area'), true) && (count($labels) < 2)) {
        return $fail('chart_data');
    }

    $highlight = $op['highlight'] ?? null;

    if (($highlight !== null) && (!is_int($highlight) || ($highlight < 0) || ($highlight >= count($labels)))) {
        return $fail('chart_data');
    }

    $chart['labels'] = $labels;
    $chart['series'] = $series;
    $chart['highlight'] = $highlight;

    return array('error' => '', 'chart' => $chart);
}

/**
 * A number as the chart writes it: whole below 10,000, else in thousands
 * with one decimal and K (en) or B (tr). Pure.
 *
 * @param float  $n
 * @param string $format en or tr
 * @return string
 */
function ws_canvas_chart_number($n, $format)
{
    $tr = ($format === 'tr');

    if ($n >= 10000) {
        $k = round($n / 1000, 1);

        return number_format($k, (floor($k) == $k) ? 0 : 1, $tr ? ',' : '.', $tr ? '.' : ',') . ($tr ? ' B' : 'K');
    }

    $decimals = (floor($n) == $n) ? 0 : 1;

    return number_format($n, $decimals, $tr ? ',' : '.', $tr ? '.' : ',');
}

/**
 * The step of a value axis for a top value: 1, 2, 2.5 or 5 times a power of
 * ten, so the axis has at most the guide's most steps. Pure.
 *
 * @param float $max
 * @param int   $steps
 * @return float
 */
function ws_canvas_chart_step($max, $steps)
{
    if ($max <= 0) {
        return 1.0;
    }

    $unit = pow(10, floor(log10($max / $steps)));

    foreach (array(1, 2, 2.5, 5, 10) as $m) {
        if ($max / ($unit * $m) <= $steps) {
            return $unit * $m;
        }
    }

    return $unit * 10;
}

/**
 * The chart's drawing as SVG and the board's colour, by the guide. Pure.
 *
 * @param array  $chart   ws_canvas_chart_check()'s chart
 * @param string $channel the channel's colour, '' for none
 * @return array svg, background
 */
function ws_canvas_chart_svg($chart, $channel = '')
{
    $v = ws_canvas_guide_values();
    $p = $v['palettes'][$chart['theme']];
    $kind = $chart['kind'];
    $w = $chart['board']['width'];
    $h = $chart['board']['height'];
    $own = in_array($kind, array('figure_tile', 'sparkline'), true);
    $scale = $own ? 1 : min($w, $h) / $v['scale_base'];
    $bw = round($w / $scale, 2);
    $bh = round($h / $scale, 2);
    $one = $own || ($kind === 'area') || ((count($chart['series'] ?? array()) === 1) && ($kind !== 'donut'));
    $colours = $one ? array(ws_canvas_chart_tone($channel, $p)) : $p['series'];
    $d = new WsCanvasChartDraw($v, $p, $chart['format']);

    if ($own) {
        $d->{$kind === 'figure_tile' ? 'tile' : 'sparkline'}($chart, $bw, $bh, $colours[0]);
    } elseif ($kind === 'donut') {
        $d->donut($chart, $bw, $bh);
    } elseif (($kind === 'line') || ($kind === 'area')) {
        $d->line($chart, $bw, $bh, $colours, $kind === 'area');
    } else {
        $d->bars($chart, $bw, $bh, $colours, $kind === 'stacked_bar');
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $bw . ' ' . $bh . '">' . implode('', $d->parts) . '</svg>';

    return array('svg' => $svg, 'background' => $p['background']);
}

/**
 * The marks of a chart, each a top-level element with an id so the split
 * names its layer. Pure.
 */
class WsCanvasChartDraw
{
    public $parts = array();
    private $v;
    private $p;
    private $format;
    private $ids = array();

    public function __construct($v, $p, $format)
    {
        $this->v = $v;
        $this->p = $p;
        $this->format = $format;
    }

    private function id($name)
    {
        $this->ids[$name] = ($this->ids[$name] ?? 0) + 1;

        return ($this->ids[$name] > 1) ? $name . '-' . $this->ids[$name] : $name;
    }

    private function n($x)
    {
        return (string) round($x, 2);
    }

    public function text($name, $x, $y, $words, $role, $colour = null, $anchor = '')
    {
        $t = $this->v['type'][$role];
        $this->parts[] = '<text id="' . $this->id($name) . '" x="' . $this->n($x) . '" y="' . $this->n($y) . '" font-family="Inter" font-size="' . $t['size'] . '" font-weight="' . $t['weight'] . '" fill="' . ($colour ?: $this->p[$t['ink']]) . '"'
            . (($anchor !== '') ? ' text-anchor="' . $anchor . '"' : '') . '>' . htmlspecialchars($words, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</text>';
    }

    private function rect($name, $x, $y, $w, $h, $fill)
    {
        $this->parts[] = '<rect id="' . $this->id($name) . '" x="' . $this->n($x) . '" y="' . $this->n($y) . '" width="' . $this->n($w) . '" height="' . $this->n(max(0, $h)) . '" fill="' . $fill . '"/>';
    }

    private function path($name, $d, $fill, $stroke = '', $width = 0)
    {
        $this->parts[] = '<path id="' . $this->id($name) . '" d="' . $d . '" fill="' . $fill . '"'
            . (($stroke !== '') ? ' stroke="' . $stroke . '" stroke-width="' . ($width ?: $this->v['marks']['line']) . '" stroke-linejoin="round" stroke-linecap="round"' : '') . '/>';
    }

    private function dot($name, $x, $y, $colour)
    {
        $m = $this->v['marks'];
        $this->parts[] = '<circle id="' . $this->id($name . '-ring') . '" cx="' . $this->n($x) . '" cy="' . $this->n($y) . '" r="' . ($m['dot'] + $m['ring']) . '" fill="' . $this->p['background'] . '"/>';
        $this->parts[] = '<circle id="' . $this->id($name) . '" cx="' . $this->n($x) . '" cy="' . $this->n($y) . '" r="' . $m['dot'] . '" fill="' . $colour . '"/>';
    }

    // The background mixed with a colour by the guide's area share, as one solid colour.
    private function wash($colour)
    {
        $t = $this->v['marks']['area_mix'] / 100;
        $out = '#';

        for ($i = 1; $i < 7; $i += 2) {
            $out .= sprintf('%02x', (int) round(hexdec(substr($colour, $i, 2)) * $t + hexdec(substr($this->p['background'], $i, 2)) * (1 - $t)));
        }

        return $out;
    }

    // Title, subtitle, legend and note; the top of the plot.
    private function head($chart, $w, $h, $legend)
    {
        $g = $this->v['grid'];
        $s = $this->v['spacing'];
        $t = $this->v['type'];
        $m = $g['margin'];
        $y = $m + $t['title']['size'] * 0.8;
        $this->text('title', $m, $y, $chart['title'], 'title');

        if ($chart['subtitle'] !== '') {
            $y += $s['title_subtitle'] + round($t['subtitle']['size'] * 1.3);
            $this->text('subtitle', $m, $y, $chart['subtitle'], 'subtitle');
        }

        if ($legend) {
            $y += $s['subtitle_legend'] + $t['label']['size'];
            $x = $m;

            foreach ($legend as $i => $entry) {
                $this->rect('legend-key', $x, $y - 15, 16, 16, $entry[1]);
                $this->text('legend', $x + 24, $y, $entry[0], 'label');
                $x += 24 + mb_strlen($entry[0]) * $t['label']['size'] * 0.56 + $g['gutter'];
            }
        }

        if ($chart['note'] !== '') {
            $this->text('note', $m, $h - $m, $chart['note'], 'note');
        }

        return $y + $s['legend_plot'];
    }

    // The bottom of the plot: above the axis labels and the note.
    private function bottom($chart, $h)
    {
        $m = $this->v['grid']['margin'];

        return $h - $m - $this->v['type']['axis']['size'] - 14 - (($chart['note'] !== '') ? $this->v['type']['note']['size'] + $this->v['spacing']['plot_note'] : 0);
    }

    // Gridlines and value labels; returns the top value of the axis.
    private function axis($x0, $x1, $top, $bot, $max)
    {
        $m = $this->v['marks'];
        $step = ws_canvas_chart_step($max, $m['ticks_max']);
        $n = max(1, (int) ceil($max / $step - 1e-9));

        // The largest value carries its label above it: leave a step free when it would end near the top gridline.
        if ($max > $step * ($n - 0.25)) {
            $n++;
        }

        for ($i = 0; $i <= $n; $i++) {
            $y = $bot - ($bot - $top) * $i / $n;
            $this->rect($i ? 'grid' : 'baseline', $x0, $i ? $y : $y - $m['baseline'] / 2, $x1 - $x0, $i ? $m['grid'] : $m['baseline'], $i ? $this->p['grid'] : $this->p['baseline']);
            $this->text('axis', $x0 - 14, $y + 6, ws_canvas_chart_number($step * $i, $this->format), 'axis', null, 'end');
        }

        return $step * $n;
    }

    public function bars($chart, $w, $h, $colours, $stacked)
    {
        $g = $this->v['grid'];
        $mk = $this->v['marks'];
        $legend = array();

        foreach ($chart['series'] as $j => $one) {
            if (count($chart['series']) > 1) {
                $legend[] = array($one['name'], $colours[$j]);
            }
        }

        $top = $this->head($chart, $w, $h, $legend);
        $bot = $this->bottom($chart, $h);
        $x0 = $g['margin'] + $g['axis_gutter'];
        $x1 = $w - $g['margin'];
        $count = count($chart['labels']);
        $totals = array();

        for ($i = 0; $i < $count; $i++) {
            $totals[$i] = 0;

            foreach ($chart['series'] as $one) {
                $totals[$i] = $stacked ? $totals[$i] + $one['values'][$i] : max($totals[$i], $one['values'][$i]);
            }
        }

        $peak = $this->axis($x0, $x1, $top, $bot, max(max($totals), 1e-9));
        $y = function ($value) use ($top, $bot, $peak) {
            return $bot - ($bot - $top) * $value / $peak;
        };
        $band = ($x1 - $x0) / $count;
        $k = $stacked ? 1 : count($chart['series']);
        $bw = min($mk['bar_max'], $band * $mk['bar_band'] / 100 / $k);
        $r = min($mk['bar_radius'], $bw / 2);
        $mark = ($chart['highlight'] !== null) ? $chart['highlight'] : array_search(max($totals), $totals);

        for ($i = 0; $i < $count; $i++) {
            $cx = $x0 + $band * ($i + 0.5);
            $acc = 0;
            $last = count($chart['series']) - 1;

            foreach ($chart['series'] as $j => $one) {
                $x = $stacked ? $cx - $bw / 2 : $cx - ($k * $bw + ($k - 1) * $mk['group_gap']) / 2 + $j * ($bw + $mk['group_gap']);
                $gap = ($stacked && $j) ? $mk['stack_gap'] : 0;
                $y0 = $y($acc) - $gap;
                $y1 = $y($acc + $one['values'][$i]);

                if ($y0 - $y1 > 0.5) {
                    if (!$stacked || ($j === $last)) {
                        $rr = min($r, ($y0 - $y1) / 2);
                        $this->path('bar', 'M' . $this->n($x) . ' ' . $this->n($y0) . 'V' . $this->n($y1 + $rr) . 'Q' . $this->n($x) . ' ' . $this->n($y1) . ' ' . $this->n($x + $rr) . ' ' . $this->n($y1)
                            . 'H' . $this->n($x + $bw - $rr) . 'Q' . $this->n($x + $bw) . ' ' . $this->n($y1) . ' ' . $this->n($x + $bw) . ' ' . $this->n($y1 + $rr) . 'V' . $this->n($y0) . 'Z', $colours[$j]);
                    } else {
                        $this->rect('bar', $x, $y1, $bw, $y0 - $y1, $colours[$j]);
                    }
                }

                if ($stacked) {
                    $acc += $one['values'][$i];
                }
            }

            $this->text('label', $cx, $bot + 32, $chart['labels'][$i], 'axis', null, 'middle');

            if ($i === $mark) {
                $this->text('value', $cx, $y($totals[$i]) - 14, ws_canvas_chart_number($totals[$i], $this->format), 'label', $this->p['ink'], 'middle');
            }
        }
    }

    public function line($chart, $w, $h, $colours, $area)
    {
        $g = $this->v['grid'];
        $legend = array();

        foreach ($chart['series'] as $j => $one) {
            if (count($chart['series']) > 1) {
                $legend[] = array($one['name'], $colours[$j]);
            }
        }

        $top = $this->head($chart, $w, $h, $legend);
        $bot = $this->bottom($chart, $h);
        $x0 = $g['margin'] + $g['axis_gutter'];
        $x1 = $w - $g['margin'] - 90;
        $max = 1e-9;

        foreach ($chart['series'] as $one) {
            $max = max($max, max($one['values']));
        }

        $peak = $this->axis($x0, $x1, $top, $bot, $max);
        $n = count($chart['labels']);
        $X = function ($i) use ($x0, $x1, $n) {
            return $x0 + 12 + ($x1 - $x0 - 24) * $i / ($n - 1);
        };
        $Y = function ($value) use ($top, $bot, $peak) {
            return $bot - ($bot - $top) * $value / $peak;
        };
        $every = max(1, (int) ceil($n / 12));

        for ($i = 0; $i < $n; $i++) {
            if (($i % $every === 0) || ($i === $n - 1)) {
                $this->text('label', $X($i), $bot + 32, $chart['labels'][$i], 'axis', null, 'middle');
            }
        }

        foreach ($chart['series'] as $j => $one) {
            $d = '';

            foreach ($one['values'] as $i => $value) {
                $d .= ($i ? 'L' : 'M') . $this->n($X($i)) . ' ' . $this->n($Y($value));
            }

            if ($area) {
                $this->path('area', $d . 'L' . $this->n($X($n - 1)) . ' ' . $this->n($bot) . 'L' . $this->n($X(0)) . ' ' . $this->n($bot) . 'Z', $this->wash($colours[$j]));
            }

            $this->path('line', $d, 'none', $colours[$j]);
            $end = $one['values'][$n - 1];
            $this->dot('end', $X($n - 1), $Y($end), $colours[$j]);
            $this->text('value', $X($n - 1) + 18, $Y($end) + 7, ws_canvas_chart_number($end, $this->format), 'label', $this->p['ink']);
        }
    }

    public function donut($chart, $w, $h)
    {
        $mk = $this->v['marks'];
        $top = $this->head($chart, $w, $h, array());
        $values = $chart['series'][0]['values'];
        $total = array_sum($values);
        $wide = ($w >= 1000);
        $R = $mk['donut_outer'];
        $r = $mk['donut_inner'];
        $cx = $wide ? $this->v['grid']['margin'] + $R + 90 : $w / 2;
        $cy = $wide ? max($top + $R, ($top + $h) / 2) : $top + $R;
        $a = -M_PI / 2;
        $colour = function ($i) {
            return ($i < count($this->p['series'])) ? $this->p['series'][$i] : $this->p['other'];
        };
        $point = function ($rad, $ang) use ($cx, $cy) {
            return $this->n($cx + $rad * cos($ang)) . ' ' . $this->n($cy + $rad * sin($ang));
        };

        foreach ($values as $i => $value) {
            if ($value <= 0) {
                continue;
            }

            $b = $a + 2 * M_PI * $value / $total;
            $gap = (count(array_filter($values)) > 1) ? 0.012 : 0;
            $large = (($b - $a) > M_PI) ? 1 : 0;

            if ($b - $a >= 2 * M_PI - 1e-6) {
                $b = $a + 2 * M_PI - 0.0001;
            }

            $this->path('slice', 'M' . $point($R, $a + $gap) . 'A' . $R . ' ' . $R . ' 0 ' . $large . ' 1 ' . $point($R, $b - $gap)
                . 'L' . $point($r, $b - $gap * $R / $r) . 'A' . $r . ' ' . $r . ' 0 ' . $large . ' 0 ' . $point($r, $a + $gap * $R / $r) . 'Z', $colour($i));
            $a = $b;
        }

        $this->text('total', $cx, $cy + 20, ws_canvas_chart_number($total, $this->format), 'figure', null, 'middle');

        $lx = $wide ? $cx + $R + 110 : $this->v['grid']['margin'];
        $rx = $w - $this->v['grid']['margin'];
        $ly = $wide ? $cy - (count($values) - 1) * 32 : $cy + $R + 64;

        foreach ($values as $i => $value) {
            $y = $ly + $i * 64;
            $this->rect('legend-key', $lx, $y - 16, 18, 18, $colour($i));
            $this->text('legend', $lx + 32, $y, $chart['labels'][$i], 'label', $this->p['ink']);
            $this->text('legend-share', $rx, $y, round($value / $total * 100) . ' %', 'label', $this->p['ink'], 'end');
            $this->text('legend-count', $rx - 100, $y, ws_canvas_chart_number($value, $this->format), 'axis', null, 'end');
        }
    }

    private function trend($values, $x, $y, $w, $h, $colour)
    {
        $n = count($values);

        if ($n < 2) {
            return;
        }

        $hi = max($values);
        $lo = min($values);
        $d = '';

        foreach ($values as $i => $value) {
            $d .= ($i ? 'L' : 'M') . $this->n($x + $w * $i / ($n - 1)) . ' ' . $this->n($y + $h - $h * ($value - $lo) / (($hi - $lo) ?: 1));
        }

        $this->path('trend-area', $d . 'L' . $this->n($x + $w) . ' ' . $this->n($y + $h) . 'L' . $this->n($x) . ' ' . $this->n($y + $h) . 'Z', $this->wash($colour));
        $this->path('trend', $d, 'none', $colour, $this->v['marks']['trend_line']);
        $last = $values[$n - 1];
        $this->dot('trend-end', $x + $w, $y + $h - $h * ($last - $lo) / (($hi - $lo) ?: 1), $colour);
    }

    public function tile($chart, $w, $h, $colour)
    {
        $this->text('label', 40, 72, $chart['label'], 'label');
        $this->text('figure', 40, 158, $chart['value'], 'figure');

        if ($chart['delta'] !== '') {
            $up = (substr($chart['delta'], 0, 1) !== '-');
            $c = $up ? $this->p['up'] : $this->p['down'];
            $this->path('delta-arrow', $up ? 'M40 206L49 194L58 206Z' : 'M40 194L49 206L58 194Z', $c);
            $this->text('delta', 66, 207, $chart['delta'], 'label', $c);

            if ($chart['vs'] !== '') {
                $this->text('vs', 82 + mb_strlen($chart['delta']) * 12, 207, $chart['vs'], 'label', $this->p['ink3']);
            }
        }

        $this->trend($chart['values'], 40, 246, $w - 80, $h - 286, $colour);
    }

    public function sparkline($chart, $w, $h, $colour)
    {
        $this->text('label', 24, 50, $chart['label'], 'label');
        $this->text('figure', 24, 92, $chart['value'], 'label', $this->p['ink']);
        $this->trend($chart['values'], 240, 26, $w - 264, $h - 52, $colour);
    }
}
