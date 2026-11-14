<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - ready layouts: canvas templates shipped with the software (a
 * row of figures with a chart, a report page, a comparison, a slide), drawn
 * from the drawing guide's values (canvas_guide.php) in a dark or a light
 * board palette. An agent lists them beside the templates people marked
 * (GET /workspace/canvas/templates) and fills their named layers as it
 * fills a template. Every layer is made by the same add operations an agent
 * sends (ws_canvas_edit_apply()), so each is one the image editor holds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_edit.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_layer_write.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_template_fill.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_guide.php');

/**
 * The ready layouts: name => board (of the guide's boards), what it is for
 * and how many bars its chart has (0: none). Pure.
 *
 * @return array[]
 */
function ws_canvas_layouts()
{
    return array(
        'figures_chart' => array('board' => 'card', 'bars' => 6, 'use' => 'a row of three figures, each with its change, over a bar chart'),
        'report_page'   => array('board' => 'portrait', 'bars' => 6, 'use' => 'a report page: two figures, a short summary and a bar chart'),
        'comparison'    => array('board' => 'card', 'bars' => 0, 'use' => 'two things side by side, each with its figure, a caption and three points'),
        'slide'         => array('board' => 'slide', 'bars' => 5, 'use' => 'a slide: a title and three points at the left, a bar chart at the right'),
    );
}

/**
 * The board palettes a layout is drawn in. Pure.
 *
 * @return string[]
 */
function ws_canvas_layout_themes()
{
    return array('light', 'dark');
}

/**
 * A layout's chart values when the agent gives none. Pure.
 *
 * @param int $bars
 * @return int[]
 */
function ws_canvas_layout_sample_values($bars)
{
    return array_slice(array(18400, 21300, 19800, 24600, 27100, 31200), -$bars);
}

/**
 * A round top for a value axis and its step: 4 steps of 1, 2, 2.5 or 5
 * times a power of ten. Pure.
 *
 * @param array $values
 * @return array max, step
 */
function ws_canvas_layout_axis($values)
{
    $top = max(1, (float) max($values));
    $raw = $top / 4;
    $power = pow(10, floor(log10($raw)));
    $step = $power * 10;

    foreach (array(1, 2, 2.5, 5, 10) as $k) {
        if ($k * $power >= $raw) {
            $step = $k * $power;
            break;
        }
    }

    $whole = function ($n) {
        return ($n == floor($n)) ? (int) $n : $n;
    };

    return array('max' => $whole($step * 4), 'step' => $whole($step));
}

/**
 * A number as an axis or a value label writes it: whole, thousands
 * grouped with a dot. Pure.
 *
 * @param float $n
 * @return string
 */
function ws_canvas_layout_number($n)
{
    return number_format(round($n), 0, ',', '.');
}

/**
 * The add operations of a layout, in board pixels: texts in the guide's
 * type scale and colours, the chart's gridlines, axis, bars and labels as
 * shapes and texts, each part a named layer. Pure but for lang().
 *
 * @param string $name a key of ws_canvas_layouts()
 * @param string $theme light or dark
 * @param array|null $values the chart's values, one a bar
 * @return array artboard (width, height, background), operations
 */
function ws_canvas_layout_operations($name, $theme, $values = null)
{
    $layout = ws_canvas_layouts()[$name];
    $v = ws_canvas_guide_values();
    $p = $v['palettes'][$theme];
    $board = array_column($v['boards'], null, 'name')[$layout['board']];
    $s = min($board['width'], $board['height']) / $v['scale_base'];
    $px = function ($n) use ($s) {
        return (int) round($n * $s);
    };
    $m = $px($v['grid']['margin']);
    $gutter = $px($v['grid']['gutter']);
    $w = $board['width'];
    $h = $board['height'];
    $ops = array();

    // A text in a type role of the guide; its height by the editor's line
    // height, so the next part is placed under it.
    $text = function ($layer, $words, $role, $x, $y, $ink = null) use (&$ops, $v, $p, $px) {
        $t = $v['type'][$role];
        $ops[] = array('op' => 'add', 'type' => 'text', 'name' => $layer, 'text' => $words, 'font' => $v['font']['font'], 'size' => $px($t['size']),
            'weight' => $t['weight'], 'color' => $p[$ink ?: $t['ink']], 'x' => (int) round($x), 'y' => (int) round($y));

        return (int) round(count(explode("\n", $words)) * $px($t['size']) * 1.25);
    };
    $rect = function ($layer, $x, $y, $rw, $rh, $colour, $radius = 0) use (&$ops) {
        $ops[] = array('op' => 'add', 'type' => 'shape', 'shape' => 'rectangle', 'name' => $layer, 'x' => (int) round($x), 'y' => (int) round($y),
            'w' => max(1, (int) round($rw)), 'h' => max(1, (int) round($rh)), 'color' => $colour, 'fill' => 'solid', 'stroke' => 0, 'radius' => $radius);
    };
    $line = function ($layer, $x1, $y, $x2, $thick, $colour) use (&$ops) {
        $ops[] = array('op' => 'add', 'type' => 'shape', 'shape' => 'line', 'name' => $layer, 'x1' => (int) round($x1), 'y1' => (int) round($y),
            'x2' => (int) round($x2), 'y2' => (int) round($y), 'stroke' => $thick, 'color' => $colour);
    };

    // A bar chart in a box: horizontal gridlines on round steps, the
    // baseline, one bar a value growing from it, a label under each bar
    // and the last value over its bar.
    $chart = function ($x, $y, $cw, $ch, $values) use ($text, $rect, $line, $v, $p, $px) {
        $mk = $v['marks'];
        $axis = ws_canvas_layout_axis($values);
        $row = (int) round($px($v['type']['axis']['size']) * 1.25);
        $plot_x = $x + $px($v['grid']['axis_gutter']);
        $plot_w = $x + $cw - $plot_x;
        $top = $y + (int) round($row / 2);
        $base = (int) round($y + $ch - $row - $px(8));
        $plot_h = $base - $top;

        for ($i = 0; $i <= 4; $i++) {
            $at = $base - $plot_h * $i / 4;

            if ($i > 0) {
                $line('chart_grid_' . $i, $plot_x, $at, $x + $cw, max(1, $px($mk['grid'])), $p['grid']);
            }

            $text('chart_axis_' . $i, ws_canvas_layout_number($axis['step'] * $i), 'axis', $x, $at - $row / 2);
        }

        $line('chart_baseline', $plot_x, $base, $x + $cw, $px($mk['baseline']), $p['baseline']);

        $n = count($values);
        $band = $plot_w / $n;
        $bar = min($px($mk['bar_max']), $band * $mk['bar_band'] / 100);
        $size = $px($v['type']['axis']['size']);

        foreach (array_values($values) as $i => $value) {
            // The top is rounded, not the height, so every bar ends on the
            // baseline.
            $bh = $base - min($base - 1, (int) round($base - $plot_h * $value / $axis['max']));
            $bx = $plot_x + $i * $band + ($band - $bar) / 2;
            $rect('chart_bar_' . ($i + 1), $bx, $base - $bh, $bar, $bh, $p['series'][0], $px($mk['bar_radius']));

            $label = lang('Label') . ' ' . ($i + 1);
            $text('chart_label_' . ($i + 1), $label, 'axis', $bx + $bar / 2 - mb_strlen($label) * $size / 4, $base + $px(8));

            if ($i === $n - 1) {
                $words = ws_canvas_layout_number($value);
                $lsize = $px($v['type']['label']['size']);
                $text('chart_value', $words, 'label', $bx + $bar / 2 - mb_strlen($words) * $lsize / 4, $base - $bh - $px(8) - $lsize * 1.25, 'ink');
            }
        }
    };

    // A figure: its label, the number and its change with an arrow, the
    // third one falling so both change colours are seen.
    $figure = function ($n, $x, $y) use ($text, $px) {
        $sample = array(1 => array('31.200', '▲ 12,4 %', 'up'), 2 => array('1.284', '▲ 3,1 %', 'up'), 3 => array('86 %', '▼ 2,0 %', 'down'));
        $y += $text('figure_' . $n . '_label', lang('Figure') . ' ' . $n, 'label', $x, $y) + $px(8);
        $y += $text('figure_' . $n . '_value', $sample[$n][0], 'figure', $x, $y);
        $y += $text('figure_' . $n . '_change', $sample[$n][1], 'label', $x, $y, $sample[$n][2]);

        return $y;
    };

    $values = ($values === null) ? ws_canvas_layout_sample_values($layout['bars']) : $values;
    $y = $m;
    $note = $h - $m - (int) round($px($v['type']['note']['size']) * 1.25);

    if ($name === 'slide') {
        $left = (int) round(($w - 2 * $m - $gutter) * 5 / 12);
        $y += $text('kicker', lang('Section'), 'label', $m, $y) + $px(8);
        $y += $text('title', lang('Title of the slide'), 'title', $m, $y) + $px(8);
        $y += $text('subtitle', lang('What it shows, and the unit'), 'subtitle', $m, $y) + $px(40);

        for ($i = 1; $i <= 3; $i++) {
            $y += $text('point_' . $i, '• ' . lang('A point in one short line'), 'subtitle', $m, $y, 'ink') + $px(16);
        }

        $cx = $m + $left + $gutter;
        $text('chart_title', lang('Chart title'), 'label', $cx, $m, 'ink');
        $chart($cx, $m + $px(40) + $px($v['type']['label']['size']), $w - $m - $cx, $note - $px(24) - $m - $px(40) - $px($v['type']['label']['size']), $values);
    } else {
        $y += $text('title', lang('Title'), 'title', $m, $y) + $px(8);
        $y += $text('subtitle', lang('What it shows, and the unit'), 'subtitle', $m, $y) + $px(24);
    }

    if ($name === 'figures_chart') {
        $col = ($w - 2 * $m - 2 * $gutter) / 3;
        $bottom = $y;

        for ($i = 1; $i <= 3; $i++) {
            $bottom = $figure($i, $m + ($i - 1) * ($col + $gutter), $y);
        }

        $chart($m, $bottom + $px(40), $w - 2 * $m, $note - $px(24) - $bottom - $px(40), $values);
    }

    if ($name === 'report_page') {
        $col = ($w - 2 * $m - $gutter) / 2;
        $line('rule', $m, $y, $w - $m, max(1, $px(1)), $p['grid']);
        $y += $px(32);
        $bottom = $y;

        for ($i = 1; $i <= 2; $i++) {
            $bottom = $figure($i, $m + ($i - 1) * ($col + $gutter), $y);
        }

        $y = $bottom + $px(40);
        $y += $text('summary_title', lang('Summary'), 'label', $m, $y, 'ink') + $px(8);
        $y += $text('summary', lang('Two or three short lines on what the figures mean.') . "\n" . lang('Keep each line under fifty letters.'), 'subtitle', $m, $y, 'ink') + $px(40);
        $text('chart_title', lang('Chart title'), 'label', $m, $y, 'ink');
        $y += $px(40) + $px($v['type']['label']['size']);
        $chart($m, $y, $w - 2 * $m, $note - $px(24) - $y, $values);
    }

    if ($name === 'comparison') {
        $col = ($w - 2 * $m - $gutter) / 2;
        $pad = $px(32);
        $panel_h = $note - $px(24) - $y;

        foreach (array('left' => 0, 'right' => 1) as $side => $i) {
            $x = $m + $i * ($col + $gutter);
            $rect($side . '_panel', $x, $y, $col, $panel_h, $p['grid'], $px(12));
            $iy = $y + $pad;
            $lsize = $px($v['type']['label']['size']);
            $rect($side . '_swatch', $x + $pad, $iy + ($lsize * 1.25 - $px(16)) / 2, $px(16), $px(16), $p['series'][$i], $px(4));
            $iy += $text($side . '_name', ($i === 0) ? lang('First option') : lang('Second option'), 'label', $x + $pad + $px(28), $iy, 'ink') + $px(8);
            $iy += $text($side . '_value', ($i === 0) ? '31.200' : '27.100', 'figure', $x + $pad, $iy);
            $iy += $text($side . '_caption', lang('What the figure is'), 'subtitle', $x + $pad, $iy) + $px(24);

            for ($k = 1; $k <= 3; $k++) {
                $line($side . '_rule_' . $k, $x + $pad, $iy, $x + $col - $pad, max(1, $px(1)), $p['baseline']);
                $iy += $px(12);
                $text($side . '_point_' . $k, lang('Point') . ' ' . $k, 'label', $x + $pad, $iy);
                $text($side . '_point_' . $k . '_value', '—', 'label', $x + $col / 2, $iy, 'ink');
                $iy += (int) round($lsize * 1.25) + $px(12);
            }
        }
    }

    $text('note', lang('Source: where the figures come from'), 'note', $m, $note);

    return array('artboard' => array('width' => $w, 'height' => $h, 'background' => $p['background']), 'operations' => $ops);
}

/**
 * A layout's document: a blank artboard in the palette's background with
 * its layers added by the canvas operations, 20 at a time as an agent's
 * edits are. Null for an unknown layout or theme, or values that do not
 * fit its chart.
 *
 * @param string $name
 * @param string $theme
 * @param array|null $values
 * @return array|null decoded as arrays
 */
function ws_canvas_layout_doc($name, $theme, $values = null)
{
    if (!is_string($name) || !isset(ws_canvas_layouts()[$name]) || !in_array($theme, ws_canvas_layout_themes(), true)) {
        return null;
    }

    $made = ws_canvas_layout_operations($name, $theme, $values);
    $doc = array(
        'v'        => 1,
        'source'   => null,
        'artboard' => $made['artboard'],
        'geometry' => array('rot' => 0, 'flipX' => false, 'flipY' => false, 'straighten' => 0, 'crop' => null, 'cropShape' => 'rect', 'output' => null),
        'adjust'   => new stdClass(),
        'filter'   => 'none',
        'frame'    => null,
        'layers'   => array(),
    );

    foreach (array_chunk($made['operations'], ws_canvas_edit_max_ops()) as $chunk) {
        $done = ws_canvas_edit_apply($doc, $chunk);

        if ($done['error'] !== '') {
            return null;
        }

        $doc = $done['doc'];
    }

    // The picture's state is the caller's, as a template's is.
    unset($doc['picture']);

    return $doc;
}

/**
 * The layouts as an agent reads them beside the templates: name, what it
 * is for, the themes, size, and the named layers of the light one (the
 * dark one has the same), with chart_values when it has a chart. Pure but
 * for lang().
 *
 * @return array[]
 */
function ws_canvas_layouts_list()
{
    $list = array();

    foreach (ws_canvas_layouts() as $name => $layout) {
        $summary = pg_canvas_template_summary(ws_canvas_layout_doc($name, 'light'));
        $list[] = array(
            'name'         => $name,
            'use'          => $layout['use'],
            'themes'       => ws_canvas_layout_themes(),
            'width'        => $summary['width'],
            'height'       => $summary['height'],
            'chart_values' => $layout['bars'],
            'layers'       => $summary['layers'],
        );
    }

    return $list;
}

/**
 * A layout filled: chart_values (when given) redraw its chart, the other
 * names fill its text layers as a template's are filled. The refusals of
 * ws_canvas_template_fill(), and chart (values that do not fit) with the
 * count the chart takes.
 *
 * @param string $name
 * @param string $theme
 * @param mixed  $fill
 * @return array error, name, names, doc (as ws_canvas_template_fill()), bars
 */
function ws_canvas_layout_fill($name, $theme, $fill)
{
    $bars = ws_canvas_layouts()[$name]['bars'];
    $values = null;

    if (is_array($fill) && array_key_exists('chart_values', $fill)) {
        $values = $fill['chart_values'];
        unset($fill['chart_values']);
        $ok = ($bars > 0) && is_array($values) && (array_values($values) === $values) && (count($values) === $bars);

        foreach ($ok ? $values : array() as $value) {
            $ok = $ok && (is_int($value) || is_float($value)) && is_finite((float) $value) && ($value >= 0) && ($value <= 1e12);
        }

        if (!$ok) {
            return array('error' => 'chart', 'name' => 'chart_values', 'names' => array(), 'doc' => null, 'bars' => $bars);
        }
    }

    $filled = ws_canvas_template_fill(ws_canvas_layout_doc($name, $theme, $values), $fill);
    $filled['bars'] = $bars;

    return $filled;
}

/**
 * The words of a refusal of ws_canvas_layout_fill().
 *
 * @param array $result
 * @return string
 */
function ws_canvas_layout_fill_error_text($result)
{
    if ($result['error'] !== 'chart') {
        return ws_canvas_template_fill_error_text($result);
    }

    if ($result['bars'] === 0) {
        return lang('This layout has no chart, so it takes no chart_values.');
    }

    return lang(array('string' => 'chart_values is a list of {var:1} numbers, from 0 up, one a bar from the left.', 'vars' => $result['bars']));
}
