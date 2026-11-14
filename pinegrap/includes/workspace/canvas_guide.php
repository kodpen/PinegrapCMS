<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the drawing guide an agent reads before it draws a canvas:
 * board sizes, grid, type scale, spacing, a dark and a light board palette
 * with a chart series, and the rules of each chart kind. One set of values
 * (ws_canvas_guide_values()) is the source of the text an agent reads (the
 * route GET /workspace/canvas/guide, its MCP resource and prompt), the short
 * form in the canvas tools' descriptions and, later, the server's own chart
 * drawing, so the three never disagree.
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
 * The guide's values. Pixel values are for the Card board (1200 x 675);
 * another board multiplies them by its scale (shorter side / 675), the
 * figure tile and the sparkline strip use them as they are. Series colours
 * are the panel's tones in a fixed order; the dark set is the same hues at
 * an even lightness that reads as a mark on a dark board. Weights are the
 * ones the image editor keeps (300, 400, 600, 700, 900). Pure.
 *
 * @return array
 */
function ws_canvas_guide_values()
{
    return array(
        'boards'  => array(
            array('name' => 'card', 'width' => 1200, 'height' => 675, 'use' => 'a chart or report card in a channel (the default)'),
            array('name' => 'slide', 'width' => 1920, 'height' => 1080, 'use' => 'a slide or a wide report'),
            array('name' => 'square', 'width' => 1080, 'height' => 1080, 'use' => 'a social post'),
            array('name' => 'portrait', 'width' => 1080, 'height' => 1350, 'use' => 'a 4:5 post'),
            array('name' => 'story', 'width' => 1080, 'height' => 1920, 'use' => 'a 9:16 story'),
            array('name' => 'figure_tile', 'width' => 600, 'height' => 360, 'use' => 'one figure with its trend'),
            array('name' => 'sparkline_strip', 'width' => 600, 'height' => 120, 'use' => 'a name, a figure and a sparkline in one row'),
        ),
        'scale_base' => 675,
        'grid'    => array('columns' => 12, 'margin' => 48, 'gutter' => 24, 'unit' => 8, 'axis_gutter' => 64),
        'font'    => array('font' => 'g:Inter', 'fallback' => 'sans'),
        'type'    => array(
            'title'    => array('size' => 44, 'weight' => 700, 'ink' => 'ink'),
            'figure'   => array('size' => 72, 'weight' => 700, 'ink' => 'ink'),
            'subtitle' => array('size' => 24, 'weight' => 400, 'ink' => 'ink2'),
            'label'    => array('size' => 20, 'weight' => 600, 'ink' => 'ink2'),
            'axis'     => array('size' => 18, 'weight' => 400, 'ink' => 'ink3'),
            'note'     => array('size' => 16, 'weight' => 400, 'ink' => 'ink3'),
        ),
        'spacing' => array(
            'steps'           => array(8, 16, 24, 32, 40, 48, 64),
            'title_subtitle'  => 8,
            'subtitle_legend' => 24,
            'legend_plot'     => 40,
            'plot_note'       => 24,
        ),
        'palettes' => array(
            'dark'  => array(
                'background' => '#14161c', 'ink' => '#eef0f3', 'ink2' => '#a4a9b1', 'ink3' => '#6d737c', 'grid' => '#262930', 'baseline' => '#3a3e47',
                'series' => array('#5892ea', '#00ac86', '#9580e0', '#be8700', '#d46d83'), 'other' => '#5d636c', 'up' => '#3fd0a8', 'down' => '#f58aa0',
            ),
            'light' => array(
                'background' => '#ffffff', 'ink' => '#12151a', 'ink2' => '#58606a', 'ink3' => '#8a919b', 'grid' => '#eceef1', 'baseline' => '#c9cdd3',
                'series' => array('#2f6fed', '#0e9a74', '#7150e6', '#b7791f', '#d43d6b'), 'other' => '#a3a9b1', 'up' => '#0e9a74', 'down' => '#d43d6b',
            ),
        ),
        'marks'   => array(
            'bar_max'       => 40,
            'bar_band'      => 56,
            'bar_radius'    => 6,
            'group_gap'     => 4,
            'stack_gap'     => 2,
            'line'          => 3,
            'dot'           => 6,
            'ring'          => 3,
            'area_mix'      => 14,
            'grid'          => 1,
            'baseline'      => 2,
            'ticks_min'     => 4,
            'ticks_max'     => 5,
            'series_max'    => 5,
            'donut_outer'   => 190,
            'donut_inner'   => 128,
        ),
        'kinds'   => array('bar', 'stacked_bar', 'line', 'area', 'donut', 'sparkline', 'figure_tile'),
    );
}

/**
 * A palette as one line: each role and its colour. Pure.
 *
 * @param array $p A palette of ws_canvas_guide_values()
 * @return string
 */
function ws_canvas_guide_palette_line($p)
{
    return 'background ' . $p['background'] . ', ink ' . $p['ink'] . ' / ' . $p['ink2'] . ' / ' . $p['ink3']
        . ', grid ' . $p['grid'] . ', baseline ' . $p['baseline']
        . ', series in this order ' . implode(', ', $p['series'])
        . ', other ' . $p['other'] . ', up ' . $p['up'] . ', down ' . $p['down'];
}

/**
 * The guide an agent reads, in English, written from the values. Pure.
 *
 * @return string
 */
function ws_canvas_guide_text()
{
    $v = ws_canvas_guide_values();
    $g = $v['grid'];
    $s = $v['spacing'];
    $m = $v['marks'];
    $t = $v['type'];

    $boards = array();

    foreach ($v['boards'] as $board) {
        $boards[] = '- ' . $board['name'] . ' ' . $board['width'] . ' x ' . $board['height'] . ': ' . $board['use'] . '.';
    }

    $type = array();

    foreach ($t as $role => $row) {
        $type[] = '- ' . $role . ': size ' . $row['size'] . ', weight ' . $row['weight'] . ', colour ' . $row['ink'] . '.';
    }

    return 'Drawing guide for canvases' . "\n\n"
        . 'Follow it for every chart, report card, slide or post you draw, so your drawings look like the rest of the panel. Pixel values are for the card board; on another board multiply them by its scale, its shorter side / ' . $v['scale_base'] . ' (the figure tile and the sparkline strip use them as they are).' . "\n\n"
        . 'Boards (width x height):' . "\n" . implode("\n", $boards) . "\n\n"
        . 'Grid: ' . $g['columns'] . ' columns, margin ' . $g['margin'] . ', gutter ' . $g['gutter'] . ', base unit ' . $g['unit'] . '; a value axis takes ' . $g['axis_gutter'] . ' at the left of the plot. Everything is left aligned, never a centred title: the title at the top left, the subtitle under it, the legend under that, the plot in the space left, a source note at the bottom.' . "\n\n"
        . 'Type: one family, font ' . $v['font']['font'] . ' (' . $v['font']['fallback'] . ' when it is not offered). Weights ' . implode(', ', array(300, 400, 600, 700, 900)) . ' only.' . "\n" . implode("\n", $type) . "\n"
        . 'Nothing smaller than ' . $t['note']['size'] . ' on a card board: a channel shows it at half size.' . "\n\n"
        . 'Spacing: multiples of ' . $g['unit'] . ' only (' . implode(', ', $s['steps']) . '). Title to subtitle ' . $s['title_subtitle'] . ', subtitle to legend ' . $s['subtitle_legend'] . ', legend to plot ' . $s['legend_plot'] . ', plot to note ' . $s['plot_note'] . '.' . "\n\n"
        . 'Colours: choose a dark or a light board and keep to its set; give the board its background colour.' . "\n"
        . '- dark: ' . ws_canvas_guide_palette_line($v['palettes']['dark']) . '.' . "\n"
        . '- light: ' . ws_canvas_guide_palette_line($v['palettes']['light']) . '.' . "\n"
        . 'Series take their colours in the order given and keep them when a series is filtered out; at most ' . $m['series_max'] . ' series, the rest folds into other. Text never wears a series colour: titles, labels, values and legends use ink, ink2 and ink3. Up and down colour only a change figure, always with an arrow. Use solid colours, no transparency, so every part stays a layer people can edit.' . "\n\n"
        . 'Marks:' . "\n"
        . '- Bar: at most ' . $m['bar_max'] . ' thick and ' . $m['bar_band'] . '% of its band; the data end rounded ' . $m['bar_radius'] . ', square at the baseline; all bars grow from one baseline. ' . $m['group_gap'] . ' between bars of a group; a gap of ' . $m['stack_gap'] . ' in the background colour between the parts of a stack; no outlines.' . "\n"
        . '- Line: ' . $m['line'] . ' thick, round joins and caps; a dot of radius ' . $m['dot'] . ' at its end with a ring of ' . $m['ring'] . ' in the background colour, and the last value beside it.' . "\n"
        . '- Area: the line over a fill of its series colour mixed ' . $m['area_mix'] . '% into the background, as one solid colour.' . "\n"
        . '- Donut: outer radius ' . $m['donut_outer'] . ', inner ' . $m['donut_inner'] . ', a thin gap between slices, the total in the middle, the legend at the right with each name, count and share.' . "\n"
        . '- Grid and axes: horizontal gridlines only, ' . $m['grid'] . ' thick and solid (never dashed), in grid; the baseline ' . $m['baseline'] . ' thick in baseline; ' . $m['ticks_min'] . ' to ' . $m['ticks_max'] . ' round steps (0, 10K, 20K).' . "\n"
        . '- Values: label only what the story is about (the highlighted bar, the end of a line), never a number on every point. A legend from two series up; one series needs none, the title says what it is.' . "\n"
        . '- Numbers as the channel\'s language writes them, with the unit in the subtitle.' . "\n\n"
        . 'Chart kinds: ' . implode(', ', $v['kinds']) . '. A bar chart compares amounts, a stacked bar the parts of each amount, a line or an area a change over time, a donut the parts of one whole, a figure tile one number with its change and a sparkline of its trend, a sparkline strip a trend in one row. Two measures of a different scale are two charts, never a second axis.';
}

/**
 * The guide in a few lines, for the canvas tools' descriptions. Pure.
 *
 * @return string
 */
function ws_canvas_guide_short()
{
    $v = ws_canvas_guide_values();
    $t = $v['type'];

    return 'Draw by the drawing guide (GET /workspace/canvas/guide has it whole): card board ' . $v['boards'][0]['width'] . ' x ' . $v['boards'][0]['height']
        . ', margin ' . $v['grid']['margin'] . ', left aligned; font ' . $v['font']['font'] . '; title ' . $t['title']['size'] . '/' . $t['title']['weight']
        . ', subtitle ' . $t['subtitle']['size'] . ', labels ' . $t['label']['size'] . '/' . $t['label']['weight'] . ', axis ' . $t['axis']['size'] . ', nothing under ' . $t['note']['size']
        . '; dark board ' . $v['palettes']['dark']['background'] . ' with series ' . implode(' ', $v['palettes']['dark']['series'])
        . ', light board ' . $v['palettes']['light']['background'] . ' with series ' . implode(' ', $v['palettes']['light']['series'])
        . '; text in ink, never in a series colour; solid colours, thin horizontal gridlines, values only where they matter.';
}
