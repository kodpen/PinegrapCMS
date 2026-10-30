<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Server-side paging for the panel's big DataTables lists.
 *
 * A list screen that names a panel action on its table.chart
 * (data-pg-list-action, backend.src.js) draws no rows itself: DataTables asks
 * the action for one page at a time with the page window, the search box and
 * a draw counter, and the action answers with the rows of that page and two
 * counts. The helpers here are the parts every such action shares: the page
 * window, the search box read the way DataTables' own smart search read it
 * (every word somewhere in the row), and a row written once as cells that
 * both the JSON answer and a printed table can use.
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
 * The page lengths the list's length menu offers (backend.src.js); -1 is
 * "Unlimited".
 *
 * @return array
 */
function pg_list_page_lengths()
{
    return array(10, 25, 50, 100, 1000, -1);
}

/**
 * A list's remembered filter value checked against the ids its pick list
 * offers. An id that is no longer offered (the record was deleted, or the
 * user lost access to it) selects no option, so the browser shows the first
 * one, '[All] (N)', while the WHERE clause still narrows to the gone id and
 * the list comes up empty. Such a value falls back to '[All]'.
 *
 * @param mixed  $value the remembered value
 * @param array  $ids   the ids the pick list offers
 * @param string $all   the value that stands for every record
 * @return string
 */
function pg_list_filter_known($value, array $ids, $all = '[All]')
{
    if (!is_string($value) || ($value === $all)) {
        return is_string($value) ? $value : $all;
    }

    foreach ($ids as $id) {
        if ((string) $id === $value) {
            return $value;
        }
    }

    return $all;
}

/**
 * The rows one request may ask for. A length the menu does not offer falls
 * back to 100, the menu's default; -1 asks for every row from the offset on.
 * The offset is a whole number of 0 or more.
 *
 * @param mixed $start  the first row, counted from 0
 * @param mixed $length the number of rows
 * @return array array('offset' => int, 'limit' => int, or null for every row)
 */
function pg_list_page_window($start, $length)
{
    $offset = (is_scalar($start) && is_numeric($start) && ($start > 0) && ($start == floor($start))) ? (int) $start : 0;
    $length = (is_scalar($length) && is_numeric($length) && ($length == floor($length))) ? (int) $length : 100;

    if (!in_array($length, pg_list_page_lengths(), true)) {
        $length = 100;
    }

    return array('offset' => $offset, 'limit' => ($length === -1) ? null : $length);
}

/**
 * The SQL LIMIT clause of a page window, with a leading space, or '' when
 * every row is asked for from the first one on.
 *
 * @param array $window pg_list_page_window()'s answer
 * @return string
 */
function pg_list_limit_sql($window)
{
    $offset = max(0, (int) ($window['offset'] ?? 0));

    if (!isset($window['limit'])) {
        // MySQL has no OFFSET without a LIMIT; this is its documented "every row".
        return ($offset > 0) ? ' LIMIT ' . $offset . ', 18446744073709551615' : '';
    }

    return ' LIMIT ' . $offset . ', ' . max(0, (int) $window['limit']);
}

/**
 * The words of a search box, the way DataTables' smart search split them:
 * at spaces, with a "quoted phrase" kept as one word. Empty words and
 * repeats are dropped; at most ten words of the first 200 characters count.
 *
 * @param mixed $value the search box
 * @return array list of strings
 */
function pg_list_search_terms($value)
{
    if (!is_scalar($value)) {
        return array();
    }

    $value = mb_substr(trim((string) $value), 0, 200, 'UTF-8');

    if ($value === '') {
        return array();
    }

    preg_match_all('/"([^"]*)"|(\S+)/u', $value, $matches, PREG_SET_ORDER);

    $terms = array();

    foreach ($matches as $match) {
        $term = trim(isset($match[2]) ? $match[2] : $match[1]);

        if (($term === '') || in_array($term, $terms, true)) {
            continue;
        }

        $terms[] = $term;

        if (count($terms) === 10) {
            break;
        }
    }

    return $terms;
}

/**
 * A word as a LIKE pattern that finds it anywhere in a value: the LIKE
 * wildcards and the escape character in it stand for themselves. The
 * pattern is a value to bind (db_q()), never text for the query.
 *
 * @param string $term
 * @return string
 */
function pg_list_like_pattern($term)
{
    return '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string) $term) . '%';
}

/**
 * The charset a list search matches I and ı in under Turkish case rules:
 * the connection's ('utf8' for a utf8/utf8mb3 connection, else 'utf8mb4')
 * when the panel or the pages are in Turkish, '' otherwise or without a
 * connection.
 *
 * @return string
 */
function pg_list_search_turkish_charset()
{
    $turkish = (strtolower(substr((string) lang(array('info' => '')), 0, 2)) === 'tr')
        || (function_exists('pg_tr_source_language') && (strtolower(substr((string) pg_tr_source_language(), 0, 2)) === 'tr'));

    if (!$turkish || !class_exists('db', false) || !isset(db::$con) || !db::$con) {
        return '';
    }

    $charset = strtolower((string) @mysqli_character_set_name(db::$con));

    return in_array($charset, array('utf8', 'utf8mb3'), true) ? 'utf8' : 'utf8mb4';
}

/**
 * A text in lower case under Turkish rules: I is ı and İ is i.
 *
 * @param string $text
 * @return string
 */
function pg_list_turkish_lower($text)
{
    return mb_strtolower(strtr((string) $text, array('İ' => 'i', 'I' => 'ı')), 'UTF-8');
}

/**
 * The search condition of a list: every word must be found in one of the
 * columns, or name one of the labels a column shows for a stored value (a
 * role number shown as "Manager"). Answers '' and no values when there is
 * no word.
 *
 * @param array $terms   pg_list_search_terms()'s answer
 * @param array $columns SQL expressions the words are looked for in; written
 *                       into the query as they are, so never from a request.
 *                       A column may instead be array('expr' => SQL
 *                       expression, 'term' => callable): the callable turns
 *                       the word into what the column holds (a shown amount
 *                       into stored digits), or answers '' when the word
 *                       cannot be in that column
 * @param array $labels  list of array('expr' => SQL expression,
 *                       'labels' => stored value => shown label)
 * @param string|null $turkish the connection charset ('utf8mb4' or 'utf8')
 *                       when a word with I or ı is also matched under
 *                       Turkish case rules (I = ı, İ = i), '' when it is
 *                       not; null asks pg_list_search_turkish_charset()
 * @return array array('sql' => '(…) AND (…)' or '', 'params' => values for
 *               its placeholders, in order)
 */
function pg_list_search_where($terms, $columns, $labels = array(), $turkish = null)
{
    if ($turkish === null) {
        $turkish = pg_list_search_turkish_charset();
    }

    $turkish = (string) $turkish;

    $parts = array();
    $params = array();

    foreach ((array) $terms as $term) {
        $term = (string) $term;
        $alternatives = array();

        foreach ($columns as $column) {
            $column_term = $term;
            $plain = !is_array($column);

            if (is_array($column)) {
                $column_term = (string) call_user_func($column['term'], $term);
                $column = $column['expr'];

                if ($column_term === '') {
                    continue;
                }
            }

            // utf8mb4_unicode_ci folds I with i, not with ı, so "YILDIZ" missed
            // "Yıldız". The plain LIKE stays: turkish_ci alone loses the ş/s and
            // ç/c folding and finds "iPhone" for "IPHONE" no more. CONVERT, not
            // COLLATE alone, so a numeric or utf8 (mb3) expression takes it too.
            $alternatives[] = $column . ' LIKE ?';
            $params[] = pg_list_like_pattern($column_term);

            if ($plain && ($turkish !== '') && preg_match('/[Iı]/u', $column_term)) {
                $alternatives[] = 'CONVERT(' . $column . ' USING ' . $turkish . ') COLLATE ' . $turkish . '_turkish_ci LIKE ?';
                $params[] = pg_list_like_pattern($column_term);
            }
        }

        foreach ($labels as $label_column) {
            $values = array();

            foreach ($label_column['labels'] as $value => $label) {
                if ((mb_stripos((string) $label, $term, 0, 'UTF-8') !== false)
                    || (($turkish !== '') && (mb_strpos(pg_list_turkish_lower($label), pg_list_turkish_lower($term), 0, 'UTF-8') !== false))) {
                    $values[] = (string) $value;
                }
            }

            if ($values) {
                $alternatives[] = $label_column['expr'] . ' IN (' . implode(', ', array_fill(0, count($values), '?')) . ')';
                $params = array_merge($params, $values);
            }
        }

        $parts[] = $alternatives ? '(' . implode(' OR ', $alternatives) . ')' : '(1 = 0)';
    }

    return array('sql' => implode(' AND ', $parts), 'params' => $params);
}

/**
 * A sort expression that orders a column of stored codes by the labels the
 * list shows for them (a page type code shown as "Shopping Cart"), in the
 * order of the current language (pg_geo_label_compare()). The answer is
 * FIELD($expr, 'code', ...) with the codes in label order, for a sort map
 * that pg_list_order_by() adds asc or desc to; a code outside the list
 * sorts before every listed one. Only codes made of letters, digits,
 * spaces, '_' and '-' are written into the query; the others are left out.
 *
 * @param string $expr   SQL expression of the stored code; written into the
 *                       query as it is, so never from a request
 * @param array  $labels stored code => shown label
 * @return string
 */
function pg_list_label_order($expr, array $labels)
{
    $codes = array();

    foreach ($labels as $code => $label) {
        $code = (string) $code;

        if (preg_match('/^[A-Za-z0-9 _-]*$/', $code)) {
            $codes[] = array('code' => $code, 'label' => (string) $label);
        }
    }

    if (!$codes) {
        return (string) $expr;
    }

    $quoted = array();

    foreach (pg_geo_sort_by_label($codes) as $code) {
        $quoted[] = "'" . $code['code'] . "'";
    }

    return 'FIELD(' . $expr . ', ' . implode(', ', $quoted) . ')';
}

/**
 * A list read into PHP sorted by its levels the way a database list sorts:
 * a number key as a number, a key that shows a label for a stored code by
 * that label, any other text the way the panel language orders it
 * (pg_geo_label_compare(): case does not matter, "Çanta" comes before "D").
 * Items that tie on every level keep the order of their first key's text.
 *
 * @param array $items   list of arrays
 * @param array $levels  pg_list_state()'s sorts: array('sort', 'order')
 * @param array $keys    sort code => the item key it orders by
 * @param array $numbers item keys compared as numbers (a size, a time)
 * @param array $labels  item key => array(stored code => shown label)
 * @return array the items, renumbered
 */
function pg_list_sort_items(array $items, array $levels, array $keys, array $numbers = array(), array $labels = array())
{
    $first_key = $keys ? reset($keys) : '';

    usort($items, function ($a, $b) use ($levels, $keys, $numbers, $labels, $first_key) {
        foreach ($levels as $level) {
            $key = $keys[$level['sort'] ?? ''] ?? null;

            if ($key === null) {
                continue;
            }

            $x = $a[$key] ?? '';
            $y = $b[$key] ?? '';

            if (in_array($key, $numbers, true)) {
                $result = ((float) $x <=> (float) $y);
            } else {
                if (isset($labels[$key])) {
                    $x = $labels[$key][$x] ?? $x;
                    $y = $labels[$key][$y] ?? $y;
                }

                $result = pg_geo_label_compare((string) $x, (string) $y);
            }

            if ($result !== 0) {
                return (($level['order'] ?? '') === 'desc') ? -$result : $result;
            }
        }

        return pg_geo_label_compare((string) ($a[$first_key] ?? ''), (string) ($b[$first_key] ?? ''));
    });

    return $items;
}

/**
 * Product groups and products in their sort order, ties by name the way the
 * panel language orders it (pg_geo_label_compare(): "Çanta" before "D").
 * An empty sort order counts as 0, as the forms save it.
 *
 * @param array $items list of arrays with 'sort_order' and 'name'
 * @return array the items, renumbered
 */
function pg_list_sort_by_order_and_name(array $items)
{
    usort($items, function ($a, $b) {
        $result = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));

        return ($result !== 0) ? $result : pg_geo_label_compare((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $items;
}

/**
 * One cell of a list row.
 *
 * @param string $html       the cell's content, already escaped
 * @param string $class      the cell's classes
 * @param array  $attributes further attributes of the cell, name => value,
 *                           not escaped (a quick edit's data-pg-edit, a tip)
 * @return array
 */
function pg_list_cell($html, $class = 'align-middle', $attributes = array())
{
    $cell = array('class' => (string) $class, 'html' => (string) $html);

    if ($attributes) {
        $cell['attributes'] = array_map('strval', $attributes);
    }

    return $cell;
}

/**
 * When a record was last changed and, when known, by whom ("15 days ago by
 * ali"); an unknown user leaves the time alone instead of a dangling "by".
 *
 * @param mixed  $timestamp
 * @param string $username
 * @return string escaped HTML
 */
function pg_list_when($timestamp, $username)
{
    $html = get_relative_time(array('timestamp' => $timestamp));

    if (trim((string) $username) !== '') {
        $html .= ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($username))));
    }

    return $html;
}

/**
 * A list row as table markup.
 *
 * @param array $row array('class' => row classes, 'cells' => list of
 *                   pg_list_cell()[, 'id' => the row's id attribute])
 * @return string
 */
function pg_list_row_html($row)
{
    $output = '<tr' . (isset($row['id']) ? ' id="' . h($row['id']) . '"' : '') . ' class="' . h($row['class'] ?? '') . '">';

    foreach ($row['cells'] as $cell) {
        $output .= '<td' . (($cell['class'] !== '') ? ' class="' . h($cell['class']) . '"' : '');

        foreach ($cell['attributes'] ?? array() as $name => $value) {
            $output .= ' ' . h($name) . '="' . h($value) . '"';
        }

        $output .= '>' . $cell['html'] . '</td>';
    }

    return $output . '</tr>';
}

/**
 * A list row as DataTables takes it from the server: the cells' content by
 * column number, the row's classes in DT_RowClass, its id (when it has one)
 * in DT_RowId and the cells' classes in DT_RowData, which backend.src.js puts
 * back on the cells it draws; so are the cells' other attributes, by column
 * number, when a cell has any.
 *
 * @param array $row as pg_list_row_html() takes it
 * @return array
 */
function pg_list_row_data($row)
{
    $data = array();
    $classes = array();
    $attributes = array();

    foreach (array_values($row['cells']) as $number => $cell) {
        $data[(string) $number] = $cell['html'];
        $classes[] = $cell['class'];

        if (!empty($cell['attributes'])) {
            $attributes[(string) $number] = $cell['attributes'];
        }
    }

    $data['DT_RowClass'] = (string) ($row['class'] ?? '');

    if (isset($row['id'])) {
        $data['DT_RowId'] = (string) $row['id'];
    }
    $data['DT_RowData'] = array('pgCells' => $classes);

    if ($attributes) {
        $data['DT_RowData']['pgAttrs'] = $attributes;
    }

    return $data;
}

/**
 * The answer to one DataTables page request.
 *
 * @param mixed $draw     the request's draw counter, handed back as a number
 * @param int   $total    rows of the list without the search
 * @param int   $filtered rows the search leaves
 * @param array $rows     the page's rows, as pg_list_row_html() takes them
 * @return array
 */
function pg_list_rows_answer($draw, $total, $filtered, $rows)
{
    return array(
        'status' => 'success',
        'draw' => (is_scalar($draw) && is_numeric($draw)) ? (int) $draw : 0,
        'recordsTotal' => (int) $total,
        'recordsFiltered' => (int) $filtered,
        'data' => array_map('pg_list_row_data', array_values($rows)),
    );
}
