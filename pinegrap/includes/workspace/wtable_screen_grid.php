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
 * also the New row form. Nothing here reads the database.
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
 * @return string HTML
 */
function ws_wtable_grid_cell_html($shape, $html, $title)
{
    $title = (string) $title;

    return '<td class="ws-wt-c ws-wt-c-' . h((string) $shape['width']) . (!empty($shape['end']) ? ' text-end' : '') . '">'
        . '<div class="ws-wt-cell"' . (($title !== '') ? ' title="' . str_replace(array("\r\n", "\r", "\n"), '&#10;', h($title)) . '"' : '') . '>' . $html . '</div></td>';
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
 * the "#" column held at its start, one-line cells in their widths and
 * figures in tabular digits.
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
}
</style>';
}
