<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the board view of a workflow table: its rows as cards in one
 * column per choice of a status column, drawn by the channel Board tab's
 * component (assets/js/workspace_board_channel.js) on the tables screen
 * (workspace_wtables.php). What is here only shapes rows the store read; a
 * card moved to another column is written as any row change is
 * (ws_wtable_row_update(): rights, revision, events).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Cards a column shows before "more" asks for the rest. */
const WS_WTABLE_BOARD_CARDS = 100;

/**
 * The status columns of a table, the ones a board can be grouped by.
 *
 * @param array $columns column id => column
 * @return array column id => name
 */
function ws_wtable_board_status_columns($columns)
{
    $out = array();

    foreach ((array) $columns as $id => $column) {
        if ((($column['type'] ?? '') === 'status') && !empty($column['options']['choices'])) {
            $out[(int) $id] = (string) $column['name'];
        }
    }

    return $out;
}

/**
 * The status column a board is grouped by: the one asked for when it is a
 * status column, else the table's first.
 *
 * @param array $columns column id => column
 * @param mixed $asked   the column id from the address
 * @return int 0 when the table has none
 */
function ws_wtable_board_column_id($columns, $asked)
{
    $statuses = ws_wtable_board_status_columns($columns);

    if (is_scalar($asked) && isset($statuses[(int) $asked])) {
        return (int) $asked;
    }

    return empty($statuses) ? 0 : (int) array_keys($statuses)[0];
}

/**
 * A row as a card: its first text cell as the title (else "Row n"), and up
 * to three other filled cells as short lines.
 *
 * @param array $row       ws_wtable_row_out()
 * @param array $columns   column id => column
 * @param int   $status_id the column the board groups by
 * @param array $people    user id => brief, for person cells
 * @param bool  $can_edit
 * @return array the card the board component draws (kind 'row')
 */
function ws_wtable_board_card($row, $columns, $status_id, $people, $can_edit)
{
    $title = '';
    $lines = array();

    foreach ((array) $columns as $id => $column) {
        if ((int) $id === (int) $status_id) {
            continue;
        }

        $text = trim(ws_wtable_screen_cell_text($column, $row['cells'][$id] ?? null, $people));

        if (($column['type'] === 'formula') && isset($row['formula_errors'][$id])) {
            $text = '#' . strtoupper((string) $row['formula_errors'][$id]);
        }

        if ($text === '') {
            continue;
        }

        if (($title === '') && ($column['type'] === 'text')) {
            $title = mb_substr($text, 0, 200);
        } elseif (count($lines) < 3) {
            $lines[] = array('label' => (string) $column['name'], 'text' => mb_substr($text, 0, 120));
        }
    }

    return array(
        'kind'     => 'row',
        'id'       => (int) $row['id'],
        'number'   => '#' . (int) $row['position'],
        'title'    => ($title !== '') ? $title : lang(array('string' => 'Row {var:1}', 'vars' => (int) $row['position'])),
        'lines'    => $lines,
        'revision' => (int) $row['revision'],
        'can_edit' => (bool) $can_edit,
    );
}

/**
 * The board of a table by a status column: one column per choice in the
 * column's order, then "No status" for rows without one (or with a choice
 * since taken away). Each column shows its first cards; `more` counts the
 * rest unless all are asked for.
 *
 * @param array $columns   column id => column
 * @param array $rows      ws_wtable_rows(), in order
 * @param int   $status_id a status column of the table
 * @param array $people    user id => brief, for person cells
 * @param bool  $can_edit
 * @param bool  $all       every card, no "more"
 * @return array group_by, column_id, columns (key, title, color, drop, add, items, more)
 */
function ws_wtable_board($columns, $rows, $status_id, $people = array(), $can_edit = false, $all = false)
{
    $status = $columns[$status_id] ?? null;
    $board = array();
    $keys = array();

    foreach ((array) ($status['options']['choices'] ?? array()) as $choice) {
        $key = (string) $choice['key'];
        $keys[$key] = count($board);
        $board[] = array(
            'key'   => $key,
            'title' => (string) $choice['label'],
            'color' => (string) ($choice['color'] ?? ''),
            'icon'  => 'bi-circle-fill',
            'drop'  => $key,
            'add'   => null,
            'items' => array(),
            'more'  => 0,
        );
    }

    $none = array(
        'key'   => '',
        'title' => lang('No status'),
        'color' => '',
        'icon'  => 'bi-circle',
        'drop'  => '',
        'add'   => null,
        'items' => array(),
        'more'  => 0,
    );

    foreach ((array) $rows as $row) {
        $value = $row['cells'][$status_id] ?? null;
        $value = is_scalar($value) ? (string) $value : '';

        if (isset($keys[$value])) {
            $target = &$board[$keys[$value]];
        } else {
            $target = &$none;
        }

        if ($all || (count($target['items']) < WS_WTABLE_BOARD_CARDS)) {
            $target['items'][] = ws_wtable_board_card($row, $columns, $status_id, $people, $can_edit);
        } else {
            $target['more']++;
        }

        unset($target);
    }

    // "No status" shows only when a row is in it, or when it is all there is.
    if (!empty($none['items']) || $none['more'] || empty($board)) {
        $board[] = $none;
    }

    return array(
        'group_by'  => 'status',
        'column_id' => (int) $status_id,
        'columns'   => $board,
    );
}

/**
 * The row change a card moved to another column writes: only the status
 * cell (the store lays it over the others), on the revision the card was
 * drawn with, so a row changed meanwhile is refused, not overwritten.
 *
 * @param int    $status_id
 * @param string $drop     the target column's choice key, '' for "No status"
 * @param mixed  $revision
 * @return array cells, revision (ws_wtable_row_update() input)
 */
function ws_wtable_board_move_input($status_id, $drop, $revision)
{
    $drop = is_scalar($drop) ? (string) $drop : '';

    return array(
        'cells'    => array((string) (int) $status_id => ($drop === '') ? null : $drop),
        'revision' => is_scalar($revision) ? (int) $revision : 0,
    );
}
