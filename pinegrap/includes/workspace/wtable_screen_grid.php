<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the grid and the row drawer of one Data table's screen
 * (workspace_wtables.php?id=N). A cell is one line cut with an ellipsis,
 * its whole text in the title; a column's type gives its cells a width
 * (number, money, date and checkbox narrow, text wide) and figures stand
 * right. The grid scrolls in its own box with the head row, the "#" column
 * and the totals rows held in view. A row opens in a form drawer, which is
 * also the New row form. A cell of a text, number, money, date, checkbox or
 * status column is changed in the grid itself
 * (assets/js/workspace_wtable_grid.js), one cell a post through the
 * screen's row_cell action. Nothing here reads the database.
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
 * The width and alignment of a column's cells by the type they show (a
 * lookup or rollup passes the type of the column it reads). Pure.
 *
 * @param string $type    a column type
 * @param array  $options the column's options (a date's time)
 * @return array array('width' => narrow|medium|wide, 'end' => bool)
 */
function ws_wtable_grid_shape($type, $options = array())
{
    switch ((string) $type) {
        case 'number':
        case 'money':
            return array('width' => 'narrow', 'end' => true);

        case 'formula':
            return array('width' => 'medium', 'end' => true);

        case 'date':
            return array('width' => empty($options['time']) ? 'narrow' : 'medium', 'end' => false);

        case 'checkbox':
            return array('width' => 'narrow', 'end' => false);

        case 'text':
        case 'list':
            return array('width' => 'wide', 'end' => false);
    }

    return array('width' => 'medium', 'end' => false);
}

/**
 * One cell of the grid: one line in its width, the whole text as its title.
 * Pure.
 *
 * @param array  $shape ws_wtable_grid_shape()
 * @param string $html  what the cell shows, escaped
 * @param string $title the cell's whole text, raw; empty for none
 * @param string $attrs more attributes of the td, escaped, with a leading
 *                      space (the grid's column, editor and value)
 * @return string HTML
 */
function ws_wtable_grid_cell_html($shape, $html, $title, $attrs = '')
{
    $title = (string) $title;

    return '<td class="ws-wt-c ws-wt-c-' . h((string) $shape['width']) . (!empty($shape['end']) ? ' text-end' : '') . '"' . (string) $attrs . '>'
        . '<div class="ws-wt-cell"' . (($title !== '') ? ' title="' . str_replace(array("\r\n", "\r", "\n"), '&#10;', h($title)) . '"' : '') . '>' . $html . '</div></td>';
}

/**
 * The field a cell turns into when it is changed in the grid, '' when the
 * cell opens the row drawer instead: formula, lookup, rollup, person, record
 * link, row link and list cells, and a text that has more than one line
 * (one line of the grid would lose its breaks). Pure.
 *
 * @param array $column type, options
 * @param mixed $value  the stored cell
 * @return string text, number, money, date, datetime, checkbox, status or ''
 */
function ws_wtable_grid_cell_editor($column, $value = null)
{
    $type = (string) ($column['type'] ?? '');

    switch ($type) {
        case 'text':
            return (is_string($value) && preg_match('/[\r\n]/', $value)) ? '' : 'text';

        case 'date':
            return empty($column['options']['time']) ? 'date' : 'datetime';

        case 'number':
        case 'money':
        case 'checkbox':
        case 'status':
            return $type;
    }

    return '';
}

/**
 * The row change one cell typed in the grid writes: that cell alone, read
 * as the row drawer reads its field (ws_wtable_screen_cell_from_form()), on
 * the revision the row was drawn with. Null for a column the grid does not
 * change. Pure.
 *
 * @param string|int $column_id
 * @param array      $column
 * @param mixed      $raw      the field's value as posted
 * @param mixed      $revision
 * @return array|null cells, revision (ws_wtable_row_update() input)
 */
function ws_wtable_grid_cell_change($column_id, $column, $raw, $revision)
{
    if (ws_wtable_grid_cell_editor($column) === '') {
        return null;
    }

    return array(
        'cells'    => array((string) (int) $column_id => ws_wtable_screen_cell_from_form($column, is_scalar($raw) ? (string) $raw : '')),
        'revision' => is_scalar($revision) ? (int) $revision : 0,
    );
}

/**
 * A refused cell in the store's words: the code the store gave that cell,
 * else the refusal as a whole (a key taken names its rows). Pure but for
 * lang().
 *
 * @param array      $result    a store refusal
 * @param string|int $column_id
 * @return string
 */
function ws_wtable_grid_cell_refusal_text($result, $column_id)
{
    $errors = (array) ($result['errors'] ?? array());

    if (isset($errors[(string) (int) $column_id]) && is_string($errors[(string) (int) $column_id])) {
        return ws_wtable_screen_error_text($errors[(string) (int) $column_id]);
    }

    return ws_wtable_screen_refusal_text($result);
}

/**
 * A row's cells as the grid draws them, by column id: the text one line in
 * its width, a status as its badge, a formula's error as #CODE. A person
 * who may change the row gets on each cell its column, and on a cell the
 * grid changes its field and the value the field opens with. Pure but for
 * lang().
 *
 * @param array $columns  column id => column
 * @param array $shapes   column id => ws_wtable_grid_shape()
 * @param array $row      a row as read (cells, formula_errors)
 * @param array $people   ws_people() with link and lookup texts
 * @param bool  $can_write
 * @return array column id => td HTML
 */
function ws_wtable_grid_row_cells($columns, $shapes, $row, $people, $can_write)
{
    $out = array();

    foreach ($columns as $column_id => $column) {
        $value = $row['cells'][$column_id] ?? null;
        $text = ws_wtable_screen_cell_text($column, $value, $people);

        if (($column['type'] === 'formula') && isset($row['formula_errors'][$column_id])) {
            $text = (string) $row['formula_errors'][$column_id];
            $cell = '<span class="fg-danger">#' . h(strtoupper($text)) . '</span>';
        } elseif (($column['type'] === 'status') && ($text !== '')) {
            $color = '';

            foreach ((array) ($column['options']['choices'] ?? array()) as $choice) {
                if (((string) $choice['key'] === (string) $value) && ((string) ($choice['color'] ?? '') !== '')) {
                    $color = ' style="background: ' . h($choice['color']) . '; color: #fff;"';
                }
            }

            $cell = '<span class="badge theme-secondary fw-normal"' . $color . '>' . h($text) . '</span>';
        } else {
            // One line: the line breaks of a text show as spaces, the
            // whole text in the title.
            $cell = h($text);
        }

        $attrs = '';

        if ($can_write) {
            $editor = ws_wtable_grid_cell_editor($column, $value);
            $attrs = ' data-ws-wt-col="' . (int) $column_id . '" tabindex="-1"'
                . (($editor !== '') ? ' data-ws-wt-edit="' . $editor . '" data-ws-wt-value="' . h(ws_wtable_screen_cell_to_form($column, $value)) . '"' : '');
        }

        $out[(string) $column_id] = ws_wtable_grid_cell_html($shapes[(string) $column_id] ?? ws_wtable_grid_shape($column['type']), $cell, $text, $attrs);
    }

    return $out;
}

/**
 * What the grid's script needs to turn a cell into its field: where it
 * posts, the table, each column's name, a money column's currency and a
 * status column's choices, and its texts. Pure but for lang().
 *
 * @param string $url      the screen's address
 * @param int    $table_id
 * @param string $token    the form token
 * @param array  $columns  column id => column
 * @return array
 */
function ws_wtable_grid_script_config($url, $table_id, $token, $columns)
{
    $out = array();

    foreach ($columns as $column_id => $column) {
        if (ws_wtable_grid_cell_editor($column) === '') {
            continue;
        }

        $item = array('name' => (string) $column['name']);

        if (($column['type'] === 'money') && ((string) ($column['options']['currency'] ?? '') !== '')) {
            $item['currency'] = (string) $column['options']['currency'];
        }

        if ($column['type'] === 'status') {
            $item['choices'] = array();

            foreach ((array) ($column['options']['choices'] ?? array()) as $choice) {
                $item['choices'][] = array('key' => (string) $choice['key'], 'label' => (string) $choice['label']);
            }
        }

        $out[(string) (int) $column_id] = $item;
    }

    return array(
        'url'     => (string) $url,
        'table'   => (int) $table_id,
        'token'   => (string) $token,
        'columns' => $out,
        'texts'   => array(
            'none'    => lang('(none)'),
            'saving'  => lang('Saving…'),
            'saved'   => lang('The cell was saved.'),
            'error'   => lang('Something went wrong. Please try again.'),
            'keys'    => lang('Enter saves, Escape gives it up, Tab goes to the next cell.'),
        ),
    );
}

/**
 * The entries of a column's head menu (ws_wtable_screen_menu_entries()):
 * Sort ascending and descending, Filter by this column, then for a writer
 * Rename and Change type (opening the Columns drawer at the column), Move
 * left and right as far as the column's place allows, and Delete with the
 * confirm the Columns drawer asks. Pure but for lang().
 *
 * @param int        $column_id
 * @param int        $place     the column's place, 1-based
 * @param int        $count     the table's columns
 * @param array|null $sort      asc, desc (addresses), now ('', asc, desc);
 *                              null for a column that sorts nothing
 * @param bool       $filter    the Filter box takes the column
 * @param bool       $can_write
 * @param bool       $retype    the column's type may change
 * @param string     $name      the column's name, for the delete confirm
 * @return array
 */
function ws_wtable_grid_column_menu_items($column_id, $place, $count, $sort, $filter, $can_write, $retype, $name = '')
{
    $column_id = (int) $column_id;
    $reads = array();
    $writes = array();

    if (is_array($sort)) {
        $reads[] = array('label' => lang('Sort ascending'), 'icon' => 'bi-sort-up', 'href' => (string) $sort['asc'], 'current' => ((string) $sort['now'] === 'asc'));
        $reads[] = array('label' => lang('Sort descending'), 'icon' => 'bi-sort-down', 'href' => (string) $sort['desc'], 'current' => ((string) $sort['now'] === 'desc'));
    }

    if ($filter) {
        $reads[] = array('label' => lang('Filter by this column'), 'icon' => 'bi-funnel', 'attrs' => 'data-ws-wt-filter-col="' . $column_id . '"');
    }

    if ($can_write) {
        $prefix = 'ws_wt_col_' . $column_id;
        $writes[] = array('label' => lang('Rename'), 'icon' => 'bi-pencil', 'attrs' => 'data-ws-wt-goto="ws-wt-col-' . $column_id . '" data-ws-wt-focus="' . $prefix . '_name"');

        if ($retype) {
            $writes[] = array('label' => lang('Change type'), 'icon' => 'bi-arrow-left-right', 'attrs' => 'data-ws-wt-goto="ws-wt-col-' . $column_id . '" data-ws-wt-focus="' . $prefix . '_to"');
        }

        if ((int) $place > 1) {
            $writes[] = array('label' => lang('Move left'), 'icon' => 'bi-arrow-left', 'action' => 'column_move',
                'data' => array('column' => $column_id, 'position' => (int) $place - 1, 'target' => 'rows'));
        }

        if ((int) $place < (int) $count) {
            $writes[] = array('label' => lang('Move right'), 'icon' => 'bi-arrow-right', 'action' => 'column_move',
                'data' => array('column' => $column_id, 'position' => (int) $place + 1, 'target' => 'rows'));
        }

        $writes[] = array('divider' => true);
        $writes[] = array('label' => lang('Delete'), 'icon' => 'bi-trash', 'danger' => true, 'action' => 'column_delete',
            'data' => array('column' => $column_id, 'target' => 'rows'),
            'confirm' => lang(array('string' => 'Delete the column {var:1}? Its cells cannot be brought back.', 'vars' => (string) $name)));
    }

    return (!empty($reads) && !empty($writes)) ? array_merge($reads, array(array('divider' => true)), $writes) : array_merge($reads, $writes);
}

/**
 * The entries of a row's menu: Move up and Move down while the rows stand
 * in the table's own order (no filter, no sort) and the row is not at that
 * end of the table, then its Delete. A move posts the row's new place to
 * ws_wtable_row_move(). Pure but for lang().
 *
 * @param array  $row        a row as read (id, position, revision)
 * @param int    $row_count  the table's rows
 * @param bool   $order_free the rows are shown in the table's order
 * @param string $confirm    the delete's confirm
 * @return array
 */
function ws_wtable_grid_row_menu_items($row, $row_count, $order_free, $confirm)
{
    $id = (int) $row['id'];
    $position = (int) $row['position'];
    $items = array();

    if ($order_free && ($position > 1)) {
        $items[] = array('label' => lang('Move up'), 'icon' => 'bi-arrow-up', 'action' => 'row_move', 'data' => array('row' => $id, 'position' => $position - 1));
    }

    if ($order_free && ($position < (int) $row_count)) {
        $items[] = array('label' => lang('Move down'), 'icon' => 'bi-arrow-down', 'action' => 'row_move', 'data' => array('row' => $id, 'position' => $position + 1));
    }

    if (!empty($items)) {
        $items[] = array('divider' => true);
    }

    $items[] = array('label' => lang('Delete'), 'icon' => 'bi-trash', 'danger' => true, 'action' => 'row_delete',
        'data' => array('row' => $id, 'revision' => (int) $row['revision']), 'confirm' => (string) $confirm);

    return $items;
}

/**
 * A menu of the grid: its toggle and the entries under it, placed fixed on
 * the page and opened in the body, so that neither the grid's scrolling box
 * cuts it nor its sticky head and totals rows draw over it. '' when no
 * entry is left.
 *
 * @param string $class the toggle's classes
 * @param string $label the toggle's name, for its title and screen readers
 * @param string $inner the toggle's content, HTML
 * @param array  $items ws_wtable_screen_menu_entries()
 * @return string HTML
 */
function ws_wtable_grid_menu($class, $label, $inner, $items)
{
    $entries = ws_wtable_screen_menu_entries($items);

    if ($entries === '') {
        return '';
    }

    return '<span class="pg-menu-wrap"><button type="button" class="' . h($class) . '" data-bs-toggle="menu" data-bs-strategy="fixed" data-bs-container="body" aria-expanded="false" title="' . h($label) . '" aria-label="' . h($label) . '">'
        . $inner . '</button><ul class="menu ws-wt-menu">' . $entries . '</ul></span>';
}

/**
 * How many rows a text field of the row drawer takes to show its value
 * whole: a line each, a line longer than the drawer's width counted as the
 * lines it wraps to, two at least. Pure.
 *
 * @param string $value
 * @param int    $per_line letters a line holds in the drawer
 * @param int    $max      the most rows, past which the field scrolls
 * @return int
 */
function ws_wtable_grid_text_rows($value, $per_line = 56, $max = 30)
{
    $rows = 0;

    foreach (preg_split('/\r\n|\r|\n/', (string) $value) as $line) {
        $length = function_exists('mb_strlen') ? mb_strlen($line, 'UTF-8') : strlen($line);
        $rows += max(1, (int) ceil($length / max(1, (int) $per_line)));
    }

    return min((int) $max, max(2, $rows));
}

/**
 * The row drawer (#ws-wt-row-form): one row's fields, or a new row's. The
 * form posts as the card under the grid did; Delete stands at the left of
 * an existing row, Save or Add at the right, and the cross or Escape closes
 * it. Pure.
 *
 * @param array $drawer action, title, icon, form (hidden fields and fields,
 *                      HTML), submit (the button's text), delete (HTML, or
 *                      empty on a new row)
 * @return string HTML
 */
function ws_wtable_grid_row_drawer($drawer)
{
    $new = ((string) $drawer['delete'] === '');

    return '
    <dialog class="drawer drawer-end ws-wt-drawer" id="ws-wt-row-form" aria-labelledby="ws-wt-row-form-title"' . ($new ? ' data-ws-wt-new="1"' : '') . '>
        <form method="post" action="' . h((string) $drawer['action']) . '" class="d-contents disable_shortcut">
            <div class="drawer-header">
                <span class="pg-icon-tile pg-tone-blue" aria-hidden="true"><i class="bi ' . h((string) $drawer['icon']) . '"></i></span>
                <h5 class="drawer-title flex-grow-1 mb-0" id="ws-wt-row-form-title">' . h((string) $drawer['title']) . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body">' . $drawer['form'] . '</div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">'
                . ($new ? '<span class="flex-grow-1"></span>' : '<span class="me-auto">' . $drawer['delete'] . '</span>')
                . '<button type="submit" class="pg-button pg-button-fill pg-button-sm">' . h((string) $drawer['submit']) . '</button>
            </div>
        </form>
    </dialog>';
}

/**
 * The grid's own rules, printed on the table's screen only: the box that
 * scrolls both ways, the head row and the totals rows held at its edges,
 * the "#" column held at its start, one-line cells in their widths,
 * figures in tabular digits, the column heads as menu buttons and a row's
 * drag handle with the line where it would land.
 *
 * @return string HTML
 */
function ws_wtable_grid_style()
{
    return '<style>
@layer custom {
    .ws-wt-grid {
        overflow: auto;
        max-height: max(20rem, calc(100dvh - 16rem));
        border: var(--pg-border-width) var(--pg-border-style) var(--pg-border-color);
        border-radius: var(--pg-radius);
        overscroll-behavior-x: contain;
    }
    .ws-wt-grid > .table {
        margin: 0;
    }
    .ws-wt-grid thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        white-space: nowrap;
    }
    .ws-wt-grid tfoot {
        position: sticky;
        bottom: 0;
        z-index: 2;
    }
    .ws-wt-grid tr > :first-child {
        position: sticky;
        inset-inline-start: 0;
        z-index: 1;
        white-space: nowrap;
    }
    .ws-wt-grid thead tr > :first-child {
        z-index: 3;
    }
    .ws-wt-grid .text-end {
        font-variant-numeric: tabular-nums;
    }
    .ws-wt-cell {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .ws-wt-c-narrow .ws-wt-cell {
        max-width: 9rem;
    }
    .ws-wt-c-medium .ws-wt-cell {
        max-width: 14rem;
    }
    .ws-wt-c-wide .ws-wt-cell {
        min-width: 10rem;
        max-width: 24rem;
    }
    .ws-wt-grid tbody tr[data-ws-wt-open] {
        cursor: pointer;
    }
    .ws-wt-grid .ws-wt-open {
        color: inherit;
        text-decoration: none;
    }
    .ws-wt-grid .ws-wt-open:focus-visible {
        outline: 2px solid var(--pg-link-color);
        outline-offset: 2px;
    }
    .ws-wt-grid td[data-ws-wt-col]:focus-visible {
        outline: 2px solid var(--pg-link-color);
        outline-offset: -2px;
    }
    .ws-wt-grid td[data-ws-wt-edit] {
        cursor: cell;
    }
    .ws-wt-grid td.ws-wt-editing {
        padding-block: 0;
    }
    .ws-wt-grid td.ws-wt-editing .pg-input {
        min-width: 8rem;
    }
    .ws-wt-grid .ws-wt-cell-note {
        max-width: 24rem;
        white-space: normal;
    }
    .ws-wt-col-button {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        max-width: 100%;
        padding: 0;
        border: 0;
        background: none;
        color: inherit;
        font: inherit;
        text-align: inherit;
    }
    .ws-wt-col-button:focus-visible {
        outline: 2px solid var(--pg-link-color);
        outline-offset: 2px;
    }
    .ws-wt-col-caret {
        color: var(--pg-fg-3);
    }
    .ws-wt-handle {
        cursor: grab;
        color: var(--pg-fg-3);
        margin-inline-end: .25rem;
    }
    .ws-wt-grid tbody tr.ws-wt-dragging {
        opacity: .5;
    }
    .ws-wt-grid tbody tr.ws-wt-drop-before > * {
        box-shadow: inset 0 2px 0 var(--pg-link-color);
    }
    .ws-wt-grid tbody tr.ws-wt-drop-after > * {
        box-shadow: inset 0 -2px 0 var(--pg-link-color);
    }
}
</style>';
}
