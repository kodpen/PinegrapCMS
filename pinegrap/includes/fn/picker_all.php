<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The picker's list of everything (picker.php, request list without a
 * folder): every item of a kind the user may use, from every folder, in one
 * list sorted by modified, name, size or type and paged by a cursor. The
 * cursor carries the sort keys of the last item sent, so the next page
 * starts after it (a keyset): no OFFSET over a large files table, and an
 * item added while the person scrolls neither repeats a row nor skips one.
 *
 * The access rule is the search's: pg_picker_where_for() without a folder
 * (never the Recycle Bin, a role 3 user only their folders).
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
 * The sorts of the list of everything, each with the order it starts in:
 * newest and largest first, names and types A to Z.
 *
 * @return array sort => 'asc' or 'desc'
 */
function pg_picker_all_sorts()
{
    return array('modified' => 'desc', 'name' => 'asc', 'size' => 'desc', 'type' => 'asc');
}

/**
 * The sort and order a request asks for, each fallen back to its default
 * when it is not one of the list's. A page has no size, so a page kind
 * sorted by size is sorted by modified. Pure.
 *
 * @param string $source 'files' or 'pages'
 * @param mixed  $sort
 * @param mixed  $order
 * @return array sort, order
 */
function pg_picker_all_sort($source, $sort, $order)
{
    $sorts = pg_picker_all_sorts();
    $sort = (is_string($sort) && isset($sorts[$sort])) ? $sort : 'modified';

    if (($source === 'pages') && ($sort === 'size')) {
        $sort = 'modified';
    }

    $order = (is_string($order) && in_array($order, array('asc', 'desc'), true)) ? $order : $sorts[$sort];

    return array('sort' => $sort, 'order' => $order);
}

/**
 * The keys a sort orders by, the last one the row id so that no two rows
 * tie; a type ties by name. A column that may hold NULL is marked null:
 * pg_picker_keyset_where() reaches its NULL rows without COALESCE(), which
 * would keep the column's index from serving the ORDER BY. Pure.
 *
 * @param string $source 'files' or 'pages'
 * @param string $sort   one of pg_picker_all_sorts()
 * @return array [{sql, key, int, null}]: the SQL column, the row's key
 *               (pg_picker_select()), whether it is a number, whether it
 *               may be NULL
 */
function pg_picker_all_columns($source, $sort)
{
    if ($source === 'pages') {
        // The name is never NULL here: the kind's condition asks for one.
        $id = array('sql' => 'page.page_id', 'key' => 'page_id', 'int' => true, 'null' => false);
        $name = array('sql' => 'page.page_name', 'key' => 'page_name', 'int' => false, 'null' => false);
        $keys = array(
            'modified' => array(array('sql' => 'page.page_timestamp', 'key' => 'page_timestamp', 'int' => true, 'null' => true)),
            'name' => array($name),
            'type' => array(array('sql' => 'page.page_type', 'key' => 'page_type', 'int' => false, 'null' => false), $name),
        );
    } else {
        $id = array('sql' => 'id', 'key' => 'id', 'int' => true, 'null' => false);
        $name = array('sql' => 'name', 'key' => 'name', 'int' => false, 'null' => false);
        $keys = array(
            'modified' => array(array('sql' => 'timestamp', 'key' => 'timestamp', 'int' => true, 'null' => true)),
            'name' => array($name),
            'size' => array(array('sql' => 'size', 'key' => 'size', 'int' => true, 'null' => true)),
            'type' => array(array('sql' => 'type', 'key' => 'type', 'int' => false, 'null' => true), $name),
        );
    }

    $columns = $keys[$sort] ?? $keys['modified'];
    $columns[] = $id;

    return $columns;
}

/**
 * A row's values of the sort keys, as a cursor carries them: a number as an
 * integer, a NULL of a column that may hold one as null. Pure.
 *
 * @param array $row     a row of pg_picker_select()
 * @param array $columns pg_picker_all_columns()
 * @return array
 */
function pg_picker_all_row_keys($row, $columns)
{
    $values = array();

    foreach ($columns as $column) {
        $value = $row[$column['key']] ?? null;

        if (($value === null) && $column['null']) {
            $values[] = null;
        } else {
            $values[] = $column['int'] ? (int) $value : (string) $value;
        }
    }

    return $values;
}

/**
 * The cursor of the next page: the sort, the order and the last item's
 * keys, in a URL-safe base64 of JSON. Opaque to the browser, which sends it
 * back as it got it. Pure.
 *
 * @param string $sort
 * @param string $order
 * @param array  $values pg_picker_all_row_keys()
 * @return string
 */
function pg_picker_cursor_encode($sort, $order, $values)
{
    $json = json_encode(array((string) $sort, (string) $order, array_values($values)), JSON_UNESCAPED_UNICODE);

    return rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');
}

/**
 * A cursor read back: the keys to start after, or null when it is not one
 * this sort and order made (a cursor of another sort, a changed or broken
 * text, a value of the wrong shape). A text key holds at most 255
 * characters, as a name does. Pure.
 *
 * @param mixed  $cursor
 * @param string $sort
 * @param string $order
 * @param array  $columns pg_picker_all_columns() of the sort
 * @return array|null
 */
function pg_picker_cursor_decode($cursor, $sort, $order, $columns)
{
    if (!is_string($cursor) || ($cursor === '') || (strlen($cursor) > 1500) || !preg_match('/^[A-Za-z0-9_-]+$/', $cursor)) {
        return null;
    }

    $json = base64_decode(strtr($cursor, '-_', '+/'), true);
    $data = is_string($json) ? json_decode($json, true) : null;

    if (!is_array($data) || (count($data) !== 3) || ($data[0] !== $sort) || ($data[1] !== $order) || !is_array($data[2])) {
        return null;
    }

    $values = array_values($data[2]);

    if (count($values) !== count($columns)) {
        return null;
    }

    foreach ($columns as $index => $column) {
        $value = $values[$index];

        if (($value === null) && $column['null']) {
            continue;
        }

        if ($column['int'] ? (!is_int($value) || ($value < 0)) : (!is_string($value) || (mb_strlen($value, 'UTF-8') > 255))) {
            return null;
        }
    }

    return $values;
}

/**
 * The condition that keeps the rows after a cursor's keys in the sort's
 * order, written out as (a > x) OR (a = x AND b > y) …, which every MySQL and
 * MariaDB version reads; a row comparison (a, b) > (x, y) was not optimized
 * before MySQL 5.7. Every key runs in the same direction.
 *
 * MySQL and MariaDB sort NULL below every value (first ascending, last
 * descending), so "below x" also takes the NULLs, "above NULL" is IS NOT
 * NULL, "equal to NULL" is IS NULL and nothing is below NULL. Pure.
 *
 * @param array  $columns pg_picker_all_columns()
 * @param array  $values  pg_picker_cursor_decode()
 * @param string $order   'asc' or 'desc'
 * @return string
 */
function pg_picker_keyset_where($columns, $values, $order)
{
    $below = ($order === 'desc');
    $equal = array();
    $after = array();

    foreach ($columns as $index => $column) {
        $value = $values[$index];
        $sql = $column['sql'];

        if (($value === null) && $column['null']) {
            $equal[] = '(' . $sql . ' IS NULL)';
            $after[] = $below ? '' : '(' . $sql . ' IS NOT NULL)';
            continue;
        }

        $literal = $column['int'] ? (string) (int) $value : "'" . e((string) $value) . "'";
        $equal[] = '(' . $sql . ' = ' . $literal . ')';

        if ($below) {
            $after[] = $column['null'] ? '((' . $sql . ' < ' . $literal . ') OR (' . $sql . ' IS NULL))' : '(' . $sql . ' < ' . $literal . ')';
        } else {
            $after[] = '(' . $sql . ' > ' . $literal . ')';
        }
    }

    $branches = array();

    foreach ($columns as $index => $column) {
        // Nothing sorts below a NULL: this branch keeps no row.
        if ($after[$index] === '') {
            continue;
        }

        $branches[] = '(' . implode(' AND ', array_merge(array_slice($equal, 0, $index), array($after[$index]))) . ')';
    }

    return '(' . implode(' OR ', $branches) . ')';
}

/**
 * The ORDER BY of a sort's keys, each in the same direction. Pure.
 *
 * @param array  $columns pg_picker_all_columns()
 * @param string $order   'asc' or 'desc'
 * @return string
 */
function pg_picker_all_order_by($columns, $order)
{
    $direction = ($order === 'desc') ? 'DESC' : 'ASC';

    return implode(', ', array_map(function ($column) use ($direction) {
        return $column['sql'] . ' ' . $direction;
    }, $columns));
}

/**
 * The answer to a list request without a folder: one page of every item of
 * the kind the user may use, each with its folder line (path), and the
 * cursor of the next page. Binned pages are not in it: they sit in the
 * Recycle Bin, which the kind's condition leaves out, and search finds them.
 *
 * @param array $request pg_picker_request() with all
 * @param array $user    validate_user()
 * @return array folder (0), all (true), crumbs, items, sort, order,
 *               per_page, more, next ('' on the last page)
 */
function pg_picker_all_answer($request, $user)
{
    $kinds = pg_picker_kinds();
    $kind = $kinds[$request['kind']];
    $columns = pg_picker_all_columns($kind['source'], $request['sort']);
    $where = pg_picker_where_for($kind, $user);

    if ($request['after'] !== null) {
        $where .= ' AND ' . pg_picker_keyset_where($columns, $request['after'], $request['order']);
    }

    $rows = db_items(
        pg_picker_select($kind, $where)
        . ' ORDER BY ' . pg_picker_all_order_by($columns, $request['order'])
        . ' LIMIT ' . ($request['per_page'] + 1));

    $slice = pg_picker_page_slice(is_array($rows) ? $rows : array(), $request['per_page']);
    $last = end($slice['items']);

    return array(
        'folder' => 0,
        'all' => true,
        'crumbs' => array(),
        'items' => pg_picker_file_details($kind, array_map(function ($row) use ($kind) {
            return pg_picker_item($kind, $row);
        }, $slice['items']), true),
        'sort' => $request['sort'],
        'order' => $request['order'],
        'per_page' => $request['per_page'],
        'more' => $slice['more'],
        'next' => ($slice['more'] && $last) ? pg_picker_cursor_encode($request['sort'], $request['order'], pg_picker_all_row_keys($last, $columns)) : '',
    );
}

/**
 * How many of a kind's items the user may use in all, as the tree's All row
 * shows it: the sum of every folder's own count. Pure.
 *
 * @param int[] $direct folder id => items directly in it
 * @return int
 */
function pg_picker_total($direct)
{
    return (int) array_sum(array_map('intval', (array) $direct));
}
