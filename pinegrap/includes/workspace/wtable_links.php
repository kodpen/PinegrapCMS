<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - row links of workflow tables, as a foreign key is: a column
 * of type row names another table (options.table), and its cell keeps the
 * id of one of that table's rows. The writer must read that table and the
 * row must be there. A row shows as its key, else its first text, else its
 * # number; to a reader who does not read the linked table it is "a row of
 * a table you do not read". ws_refs keeps each link (source wtable_row, ref
 * wtable_row), so the rows that link to a row are found by index: a deleted
 * row clears those links, and its delete confirmation counts them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Where the screen's people list carries the texts of linked rows, so that
// the list, the board, the download and the bin, which take the people
// list already, show them too. A user id is never a string with a NUL.
define('WS_WTABLE_LINKS_KEY', "\0links");

/* ---------------------------------------------------------------------------
   Rules (pure)
   --------------------------------------------------------------------------- */

/**
 * The table a row column links to, 0 for any other column. Pure.
 *
 * @param array $column
 * @return int
 */
function ws_wtable_link_table($column)
{
    return ((($column['type'] ?? '') === 'row') && ws_wtable_is_whole($column['options']['table'] ?? null)) ? (int) $column['options']['table'] : 0;
}

/**
 * The row ids the cells of rows link to, by linked table. Pure.
 *
 * @param array   $columns column id => column
 * @param array[] $rows    each with cells by column id
 * @return array linked table id => row id => true
 */
function ws_wtable_link_ids($columns, $rows)
{
    $out = array();

    foreach ((array) $columns as $column_id => $column) {
        $linked = ws_wtable_link_table($column);

        if ($linked <= 0) {
            continue;
        }

        foreach ((array) $rows as $row) {
            $cell = $row['cells'][(string) $column_id] ?? null;

            if (ws_wtable_is_whole($cell) && ((int) $cell > 0)) {
                $out[$linked][(int) $cell] = true;
            }
        }
    }

    return $out;
}

/**
 * What a linked row shows as: its key, else its first filled text cell,
 * else #N, its stable number. Pure but for the site's separators.
 *
 * @param array $columns the linked table's columns
 * @param array $row     id, number, position, cells (decoded)
 * @return string
 */
function ws_wtable_link_label($columns, $row)
{
    $cells = (array) ($row['cells'] ?? array());
    $key = ws_wtable_key_column($columns);

    if ($key !== null) {
        $text = ws_wtable_screen_cell_text($key, $cells[(string) $key['id']] ?? null);

        if ($text !== '') {
            return $text;
        }
    }

    foreach ((array) $columns as $column_id => $column) {
        if ((($column['type'] ?? '') === 'text') && is_string($cells[(string) $column_id] ?? null) && (trim($cells[(string) $column_id]) !== '')) {
            return trim(preg_replace('/\s+/u', ' ', $cells[(string) $column_id]));
        }
    }

    return '#' . ws_wtable_row_label($row);
}

/**
 * The text of a reader who does not read the linked table. Pure but for lang().
 *
 * @return string
 */
function ws_wtable_link_unread_text()
{
    return lang('A row of a table you do not read');
}

/**
 * A people list (ws_people()) that carries the texts of linked rows too,
 * for ws_wtable_screen_cell_text(). Pure.
 *
 * @param array $people
 * @param array $texts row id => text (ws_wtable_link_texts())
 * @return array
 */
function ws_wtable_people_with_links($people, $texts)
{
    $people = (array) $people;
    $people[WS_WTABLE_LINKS_KEY] = (array) $texts;

    return $people;
}

/**
 * The text a row link cell shows: the linked row's, '' when the row is not
 * there (deleted since, or its table). Pure.
 *
 * @param mixed $value  the cell, a row id
 * @param array $people ws_wtable_people_with_links()
 * @return string
 */
function ws_wtable_link_cell_text($value, $people)
{
    $texts = (isset($people[WS_WTABLE_LINKS_KEY]) && is_array($people[WS_WTABLE_LINKS_KEY])) ? $people[WS_WTABLE_LINKS_KEY] : array();

    return ws_wtable_is_whole($value) ? (string) ($texts[(int) $value] ?? '') : '';
}

/* ---------------------------------------------------------------------------
   Reading linked rows
   --------------------------------------------------------------------------- */

/**
 * Whether the reader reads a table, read once a request for each.
 *
 * @param array $viewer
 * @param int   $table_id
 * @return bool
 */
function ws_wtable_link_readable($viewer, $table_id)
{
    static $seen = array();
    $key = (int) ($viewer['id'] ?? 0) . ':' . (int) $table_id;

    if (!array_key_exists($key, $seen)) {
        $table = ((int) $table_id > 0) ? ws_wtable((int) $table_id) : null;
        $seen[$key] = is_array($table) && ws_wtable_may(ws_wtable_access($viewer, $table), 'read');
    }

    return $seen[$key];
}

/**
 * Rows of one table by id, decoded, ordered as the table lists them.
 *
 * @param int        $table_id
 * @param int[]|null $ids null for every row
 * @return array[] id => id, number, position, cells
 */
function ws_wtable_link_rows($table_id, $ids = null)
{
    if (is_array($ids)) {
        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            return array();
        }
    }

    $number = ws_wtable_numbers_ready() ? ', number' : '';
    $out = array();

    foreach ((array) db_items("SELECT id, position, cells" . $number . " FROM ws_wtable_rows
        WHERE table_id = '" . (int) $table_id . "'" . (is_array($ids) ? " AND id IN ('" . implode("','", $ids) . "')" : '') . "
        ORDER BY position, id") as $row) {
        $row['cells'] = ws_wtable_cells_decode($row['cells']);
        $out[(int) $row['id']] = $row;
    }

    return $out;
}

/**
 * The texts the row links of rows show to a reader: row id => the linked
 * row's key or first text, or "a row of a table you do not read". A row
 * that is not there has no text.
 *
 * @param array   $viewer
 * @param array   $columns column id => column
 * @param array[] $rows    each with cells
 * @return array row id => text
 */
function ws_wtable_link_texts($viewer, $columns, $rows)
{
    $texts = array();

    foreach (ws_wtable_link_ids($columns, $rows) as $table_id => $ids) {
        if (!ws_wtable_link_readable($viewer, $table_id)) {
            foreach (array_keys($ids) as $id) {
                $texts[(int) $id] = ws_wtable_link_unread_text();
            }

            continue;
        }

        $linked_columns = ws_wtable_columns((int) $table_id);

        foreach (ws_wtable_link_rows((int) $table_id, array_keys($ids)) as $id => $row) {
            $texts[(int) $id] = ws_wtable_link_label($linked_columns, $row);
        }
    }

    return $texts;
}

/**
 * The rows a row link column may pick, for its form: row id => its text
 * and #N, in the table's order; null when the reader does not read the
 * linked table.
 *
 * @param array $viewer
 * @param array $column
 * @return array|null
 */
function ws_wtable_link_choices($viewer, $column)
{
    $table_id = ws_wtable_link_table($column);

    if (!ws_wtable_link_readable($viewer, $table_id)) {
        return null;
    }

    $columns = ws_wtable_columns($table_id);
    $out = array();

    foreach (ws_wtable_link_rows($table_id) as $id => $row) {
        $label = ws_wtable_link_label($columns, $row);
        $number = '#' . ws_wtable_row_label($row);
        $out[(int) $id] = ($label === $number) ? $label : $label . ' · ' . $number;
    }

    return $out;
}

/**
 * The rows an import may link each row link column to: column id => the
 * linked row's text folded (its key or first text, the first row of a text
 * winning) and #N => row id. A table the person does not read gives none.
 *
 * @param array $viewer
 * @param array $columns column id => column
 * @return array
 */
function ws_wtable_link_import_rows($viewer, $columns)
{
    $out = array();

    foreach ((array) $columns as $column_id => $column) {
        $table_id = ws_wtable_link_table($column);

        if (($table_id <= 0) || !ws_wtable_link_readable($viewer, $table_id)) {
            continue;
        }

        $linked_columns = ws_wtable_columns($table_id);
        $map = array();

        foreach (ws_wtable_link_rows($table_id) as $id => $row) {
            $map['#' . ws_wtable_row_label($row)] = (int) $id;
            $map += array(ws_fold(ws_wtable_link_label($linked_columns, $row)) => (int) $id);
        }

        $out[(string) $column_id] = $map;
    }

    return $out;
}

/* ---------------------------------------------------------------------------
   Writing links
   --------------------------------------------------------------------------- */

/**
 * Why a new row link column is refused, null when it is not one or may be
 * made: the table it names must be one the person reads (row_table_invalid).
 *
 * @param array $viewer
 * @param array $sets ws_wtable_column_input_check() sets
 * @return array|null
 */
function ws_wtable_link_column_refusal($viewer, $sets)
{
    if (($sets['type'] ?? '') !== 'row') {
        return null;
    }

    return ws_wtable_link_readable($viewer, (int) ($sets['options']['table'] ?? 0)) ? null : ws_wtable_refusal('row_table_invalid', 'options');
}

/**
 * Why the row links among checked cells are refused, null when none is:
 * the writer reads the linked table (row_table_unread) and the row is one
 * of its rows (row_not_found). A link the row already holds is not checked
 * again, so a colleague who does not read the other table may change the
 * row's other cells.
 *
 * @param array $viewer
 * @param array $columns column id => column
 * @param array $cells   column id => checked value
 * @param array $old     column id => value the row holds
 * @return array|null ws_wtable_cells_refusal()
 */
function ws_wtable_link_cells_refusal($viewer, $columns, $cells, $old = array())
{
    $errors = array();

    foreach ((array) $cells as $column_id => $value) {
        $table_id = ws_wtable_link_table($columns[(string) $column_id] ?? array());

        if (($table_id <= 0) || !is_int($value) || (($old[(string) $column_id] ?? null) === $value)) {
            continue;
        }

        if (!ws_wtable_link_readable($viewer, $table_id)) {
            $errors[(string) $column_id] = 'row_table_unread';
        } elseif (!db_value("SELECT id FROM ws_wtable_rows WHERE id = '" . (int) $value . "' AND table_id = '" . $table_id . "'")) {
            $errors[(string) $column_id] = 'row_not_found';
        }
    }

    return empty($errors) ? null : ws_wtable_cells_refusal($errors);
}

/* ---------------------------------------------------------------------------
   The rows that link to a row
   --------------------------------------------------------------------------- */

/**
 * The rows that link to rows, as ws_refs keeps them: row id => the ids of
 * the rows whose cells link to it. Empty before ws_refs takes the source.
 *
 * @param int[] $row_ids
 * @return array row id => int[]
 */
function ws_wtable_linking_rows($row_ids)
{
    $row_ids = array_filter(array_map('intval', (array) $row_ids));

    if (empty($row_ids) || !function_exists('ws_ref_sources_ready') || !ws_ref_sources_ready()) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT DISTINCT ref_id, source_id FROM ws_refs
        WHERE ref_type = 'wtable_row' AND ref_id IN ('" . implode("','", $row_ids) . "') AND source_type = 'wtable_row'") as $ref) {
        $out[(int) $ref['ref_id']][] = (int) $ref['source_id'];
    }

    return $out;
}

/**
 * How many rows link to each of the rows of a table, for its delete
 * confirmation: row id => count, rows nothing links to left out. Read only
 * when a row link column points at the table.
 *
 * @param int   $table_id
 * @param int[] $row_ids
 * @return array row id => int
 */
function ws_wtable_link_counts($table_id, $row_ids)
{
    $linked = db_value("SELECT id FROM ws_wtable_columns WHERE type = 'row' AND options LIKE '%\"table\":" . (int) $table_id . "%' LIMIT 1");

    if (!$linked) {
        return array();
    }

    return array_map('count', ws_wtable_linking_rows($row_ids));
}

/**
 * Clear the links to a row that was deleted: each row whose row link
 * cell points at it loses that cell, as ON DELETE SET NULL does. Each is
 * written on its revision, read again when another write came first (three
 * tries), and its ws_refs tags rewritten. No row event is sent.
 *
 * @param int $table_id the deleted row's table
 * @param int $row_id
 * @param int $actor_id who deleted it
 * @return int the rows changed
 */
function ws_wtable_links_clear($table_id, $row_id, $actor_id)
{
    $sources = ws_wtable_linking_rows(array((int) $row_id));
    $changed = 0;
    $columns_of = array();

    foreach ($sources[(int) $row_id] ?? array() as $source_id) {
        for ($try = 0; $try < 3; $try++) {
            $row = db_item("SELECT id, table_id, cells, revision FROM ws_wtable_rows WHERE id = '" . (int) $source_id . "'");

            if (!$row) {
                break;
            }

            $source_table = (int) $row['table_id'];
            $columns_of[$source_table] = $columns_of[$source_table] ?? ws_wtable_columns($source_table);
            $cells = ws_wtable_cells_decode($row['cells']);
            $kept = $cells;

            foreach ($columns_of[$source_table] as $column_id => $column) {
                if ((ws_wtable_link_table($column) === (int) $table_id) && ws_wtable_is_whole($kept[(string) $column_id] ?? null) && ((int) $kept[(string) $column_id] === (int) $row_id)) {
                    unset($kept[(string) $column_id]);
                }
            }

            if ($kept === $cells) {
                break;
            }

            db("UPDATE ws_wtable_rows SET cells = '" . e(ws_wtable_cells_encode($kept)) . "', revision = revision + 1,
                    updated_by = '" . (int) $actor_id . "', updated_at = '" . time() . "'
                WHERE id = '" . (int) $row['id'] . "' AND revision = '" . (int) $row['revision'] . "'");

            if ((int) mysqli_affected_rows(db::$con) === 1) {
                ws_ref_source_write('wtable_row', array((int) $row['id'] => ws_wtable_row_ref_tokens($columns_of[$source_table], $kept)));
                $changed++;
                break;
            }
        }
    }

    return $changed;
}
