<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - an agent's data layers on a canvas: a Workspace table drawn
 * as a chart or a plain table, added or changed by its description (the
 * data layer of pg_image_editor_data.js). The layer holds the description
 * and never the rows; whoever opens the canvas reads them with their own
 * rights. The description is checked as the editor's model checks it
 * (pg_image_draw_data_check()), the table must be one the caller may read
 * and the columns must be the table's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_draw_data.php');

/**
 * A description checked against the table it names: the filled
 * description, or the refusal. Pure but for the resolver.
 *
 * @param mixed         $data   the description as sent
 * @param callable|null $tables a table id => its column ids, or null when
 *                              the caller may not read it
 *                              (ws_canvas_data_table_resolver())
 * @return array|string the description, or the refusal (data, data_read,
 *                      or a code of pg_image_draw_data_check())
 */
function ws_canvas_data_write_check($data, $tables)
{
    if (!is_array($data) || ($data === array())) {
        return 'data';
    }

    $checked = pg_image_draw_data_check($data);

    if (!$checked['ok']) {
        return $checked['error'];
    }

    $d = $checked['data'];
    $columns = is_callable($tables) ? $tables((int) $d['table']) : null;

    if (!is_array($columns)) {
        return 'data_read';
    }

    $columns = array_map('strval', $columns);

    foreach (array_merge(array($d['label']), $d['values']) as $id) {
        if (!in_array((string) $id, $columns, true)) {
            return 'data_columns';
        }
    }

    return $d;
}

/**
 * A new data layer for an add of type data: the description checked, the
 * box the Data menu gives (60 % of the board wide at 16:9, in the middle)
 * unless x, y, w or h are sent. Pure but for the resolver.
 *
 * @param array         $doc    decoded as arrays
 * @param array         $op     data, and x, y, w, h, rot optional
 * @param callable|null $tables ws_canvas_data_table_resolver()
 * @return array|string the layer (without its id), or the refusal
 */
function ws_canvas_data_write_layer($doc, $op, $tables)
{
    $d = ws_canvas_data_write_check($op['data'] ?? null, $tables);

    if (!is_array($d)) {
        return $d;
    }

    $board = (array) ($doc['artboard'] ?? array());
    $bw = max(1, (int) ($board['width'] ?? 1));
    $bh = max(1, (int) ($board['height'] ?? 1));
    $w = (int) round(min($bw * 0.6, $bh * 0.6 * 16 / 9));
    $h = (int) round($w * 9 / 16);

    return array(
        'type'    => 'data',
        'x'       => (int) round(($bw - $w) / 2),
        'y'       => (int) round(($bh - $h) / 2),
        'w'       => max(1, $w),
        'h'       => max(1, $h),
        'rot'     => 0,
        'opacity' => 1,
        'data'    => $d,
    );
}

/**
 * A data layer's description changed by a data operation: the fields sent
 * laid over the layer's own, the whole checked again. Pure but for the
 * resolver.
 *
 * @param array         $layer
 * @param array         $op     data: the fields to change
 * @param callable|null $tables ws_canvas_data_table_resolver()
 * @return array|string the layer, or the refusal (not_data, data, ...)
 */
function ws_canvas_data_write_change($layer, $op, $tables)
{
    if (($layer['type'] ?? '') !== 'data') {
        return 'not_data';
    }

    $patch = $op['data'] ?? null;

    if (!is_array($patch) || ($patch === array()) || (array_values($patch) === $patch)) {
        return 'data';
    }

    $d = ws_canvas_data_write_check(array_merge((array) ($layer['data'] ?? array()), $patch), $tables);

    if (!is_array($d)) {
        return $d;
    }

    $layer['data'] = $d;

    return $layer;
}

/**
 * The words of a data layer's refusal, or '' for a code that is not one.
 *
 * @param string $error
 * @return string
 */
function ws_canvas_data_write_error_text($error)
{
    switch ($error) {
        case 'data':
            return lang('A data layer takes data: an object describing the table and how it is shown.');
        case 'data_read':
            return lang('That table is not one you may read: see GET /workspace/tables.');
        case 'not_data':
            return lang('Only a data layer takes the data operation.');
        case 'data_table':
            return lang('data.table is the id of a Workspace table.');
        case 'data_show':
            return lang('data.show is chart or table.');
        case 'data_kind':
            return lang('A data chart kind is bar, stacked_bar, line, area or donut.');
        case 'data_group':
            return lang('data.group is none, sum, count or average.');
        case 'data_columns':
            return lang('data.label and data.values are column ids of the table (GET /workspace/tables/{id}): up to five values, a chart at least one, a donut one.');
        case 'data_sort':
            return lang('data.sort is none, label or value, and dir asc or desc.');
        case 'data_format':
            return lang('data.format is en or tr.');
        case 'data_title':
            return lang('data.title is one line of at most 120 letters.');
        case 'data_colours':
            return lang('data.colours is up to five colours as #rrggbb.');
        case 'data_theme':
            return lang('data.theme is light or dark.');
        case 'data_font':
            return lang('That font is not offered: see GET /workspace/canvas/fonts.');
        case 'data_legend':
            return lang('data.legendAt is top or bottom.');
        case 'data_limit':
            return lang('data.limit is a whole number from 0 (the kind\'s own cap) to 50.');
        case 'data_size':
            return lang('data.size is s, m or l.');
        default:
            return '';
    }
}

/**
 * The text an agent reads about data layers in a canvas edit's
 * description.
 *
 * @return string
 */
function ws_canvas_data_op_text()
{
    return 'add with type data (data: table, the id of a Workspace table you may read; show chart or table; kind bar, stacked_bar, line, area or donut; label, the column id of the labels; values, up to five column ids of numbers, a donut one; group none, sum, count or average; sort none, label or value with dir asc or desc; limit 0 to 50; title; format en or tr; colours, up to five #rrggbb; theme light or dark; legend true or false, legendAt top or bottom; size s, m or l; x, y, w, h, rot optional, 60 % of the board wide at 16:9 in the middle when left out) and data (layer, data: the fields of a data layer\'s description to change): a table shown as a chart or a plain table that follows its rows, drawn from the rows of whoever opens the canvas, never kept in it. When the numbers live in a Workspace table, add a data layer instead of drawing a chart: the canvas then follows the table.';
}
