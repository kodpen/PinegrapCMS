<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a CSV or spreadsheet file brought into a workflow table: a new
 * table whose columns are the file's header row, each with a type guessed
 * from its values, or rows added to a table the person may write, the
 * file's columns matched to the table's by name.
 *
 * The first part needs no database: reading the file, the header names, the
 * guess of a column's type and options, the matching by name, and turning
 * one typed value into what the store's cell check takes. A value it cannot
 * read is handed over as written, so that the store refuses it with its own
 * code. The second part writes: the table and its columns through the
 * store's own functions, and every row through ws_wtable_row_create(), so
 * the row limit, the cell checks and the rights stay the store's alone.
 * Rows brought in this way announce no webhook each (one listing tells a
 * receiver more than thousands of events).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** The column types an import guesses or lets the person choose. */
function ws_wtable_import_types()
{
    return array('text', 'number', 'money', 'date', 'status', 'checkbox');
}

/**
 * The column names of a file's header row: an empty name is "Column N", a
 * name met before gets " 2", " 3"..., each cut to the store's name length.
 *
 * @param array $header
 * @return string[]
 */
function ws_wtable_import_names($header)
{
    $most = ws_wtable_limits()['name'];
    $names = array();
    $seen = array();

    foreach (array_values((array) $header) as $i => $name) {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
        $name = ($name === '') ? lang(array('string' => 'Column {var:1}', 'vars' => $i + 1)) : $name;
        $name = mb_substr($name, 0, $most - 4);
        $base = $name;
        $n = 1;

        while (isset($seen[mb_strtolower($name)])) {
            $name = $base . ' ' . (++$n);
        }

        $seen[mb_strtolower($name)] = true;
        $names[] = $name;
    }

    return $names;
}

/**
 * A number as written in a file, as the store takes it ("1234.5"), or null.
 * Spaces and a thousands separator are dropped; with both "." and "," the
 * last one is the decimal mark, a lone "," is one too (1,5 and 12,50), and
 * the same mark more than once separates thousands.
 *
 * @param string $text
 * @return string|null
 */
function ws_wtable_import_number($text)
{
    $text = str_replace(array(' ', "\xC2\xA0", "'"), '', trim((string) $text));

    if (!preg_match('/^[-+]?[0-9.,]+$/', $text) || !preg_match('/[0-9]/', $text)) {
        return null;
    }

    $dot = strrpos($text, '.');
    $comma = strrpos($text, ',');

    if (($dot !== false) && ($comma !== false)) {
        $mark = ($dot > $comma) ? '.' : ',';
        $text = str_replace(($mark === '.') ? ',' : '.', '', $text);
    } else {
        $mark = ($dot !== false) ? '.' : (($comma !== false) ? ',' : '');

        if (($mark !== '') && (substr_count($text, $mark) > 1)) {
            $text = str_replace($mark, '', $text);
            $mark = '';
        }
    }

    $text = ltrim(($mark === ',') ? str_replace(',', '.', $text) : $text, '+');

    return preg_match('/^-?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/', $text) ? $text : null;
}

/**
 * An amount of money as written in a file: the number, and the currency its
 * sign or code names ('' for none), or null when it is no amount.
 *
 * @param string $text
 * @return array|null amount (as ws_wtable_import_number()), currency
 */
function ws_wtable_import_money($text)
{
    $signs = array('₺' => 'TRY', 'TL' => 'TRY', '$' => 'USD', '€' => 'EUR', '£' => 'GBP');
    $text = trim((string) $text);
    $currency = '';

    if (preg_match('/^(.*?)\s*\b([A-Z]{3})$/u', $text, $parts) || preg_match('/^([A-Z]{3})\b\s*(.*)$/u', $text, $parts)) {
        $code = preg_match('/^[A-Z]{3}$/', $parts[1]) ? $parts[1] : $parts[2];
        $text = preg_match('/^[A-Z]{3}$/', $parts[1]) ? $parts[2] : $parts[1];
        $currency = $code;
    } else {
        foreach ($signs as $sign => $code) {
            $at = mb_strpos($text, $sign);

            if (($at === 0) || (($at !== false) && ($at === mb_strlen($text) - mb_strlen($sign)))) {
                $text = trim(str_replace($sign, '', $text));
                $currency = $code;
                break;
            }
        }
    }

    $amount = ws_wtable_import_number($text);

    return ($amount === null) ? null : array('amount' => $amount, 'currency' => $currency);
}

/**
 * A date as written in a file: 2026-10-29, 29.10.2026 or 29/10/2026 (day
 * first), 2026/10/29, each with an optional time (14:30, 14:30:15).
 *
 * @param string $text
 * @return array|null date (YYYY-MM-DD), time (HH:MM, '' for none)
 */
function ws_wtable_import_date($text)
{
    $text = trim((string) $text);
    $time = '(?:[ T]([0-9]{1,2}):([0-9]{2})(?::[0-9]{2})?)?';

    if (preg_match('#^([0-9]{4})[-/]([0-9]{1,2})[-/]([0-9]{1,2})' . $time . '$#', $text, $parts)) {
        list($year, $month, $day) = array((int) $parts[1], (int) $parts[2], (int) $parts[3]);
    } elseif (preg_match('#^([0-9]{1,2})[./]([0-9]{1,2})[./]([0-9]{4})' . $time . '$#', $text, $parts)) {
        list($year, $month, $day) = array((int) $parts[3], (int) $parts[2], (int) $parts[1]);
    } else {
        return null;
    }

    if (!checkdate($month, $day, $year)) {
        return null;
    }

    $clock = '';

    if (isset($parts[4]) && ($parts[4] !== '')) {
        if (((int) $parts[4] > 23) || ((int) $parts[5] > 59)) {
            return null;
        }

        $clock = sprintf('%02d:%02d', (int) $parts[4], (int) $parts[5]);
    }

    return array('date' => sprintf('%04d-%02d-%02d', $year, $month, $day), 'time' => $clock);
}

/**
 * A tick as written in a file: yes, no, true, false, evet, hayır, x, ✓,
 * and 1 or 0 when $digits is set. Null when it is none of them.
 *
 * @param string $text
 * @param bool   $digits
 * @return bool|null
 */
function ws_wtable_import_checkbox($text, $digits = true)
{
    $words = array('yes' => true, 'no' => false, 'true' => true, 'false' => false, 'evet' => true, 'hayır' => false, 'hayir' => false, 'x' => true, '✓' => true, '✔' => true);
    $key = mb_strtolower(trim((string) $text));

    if (isset($words[$key])) {
        return $words[$key];
    }

    if ($digits && (($key === '1') || ($key === '0'))) {
        return ($key === '1');
    }

    return null;
}

/**
 * The type a column's values suggest, with the options it is made with.
 * Every non-empty value must read as the type: a tick word, a date, an
 * amount with a currency, a number; few labels repeated make a status;
 * anything else is text. People and record links are never guessed.
 *
 * @param string[] $values
 * @return array type, options
 */
function ws_wtable_import_guess($values)
{
    $filled = array();

    foreach ((array) $values as $value) {
        if (trim((string) $value) !== '') {
            $filled[] = trim((string) $value);
        }
    }

    if (empty($filled)) {
        return array('type' => 'text', 'options' => array());
    }

    $all = function ($read) use ($filled) {
        foreach ($filled as $value) {
            if ($read($value) === null) {
                return false;
            }
        }

        return true;
    };

    if ($all(function ($value) { return ws_wtable_import_checkbox($value, false); })) {
        return array('type' => 'checkbox', 'options' => array());
    }

    foreach (array('date', 'money', 'number', 'status') as $type) {
        if ($type === 'money') {
            $marked = false;

            foreach ($filled as $value) {
                $money = ws_wtable_import_money($value);
                $marked = $marked || ($money && ($money['currency'] !== ''));
            }

            if (!$marked || !$all('ws_wtable_import_money')) {
                continue;
            }
        } elseif (($type === 'date') && !$all('ws_wtable_import_date')) {
            continue;
        } elseif (($type === 'number') && !$all('ws_wtable_import_number')) {
            continue;
        } elseif ($type === 'status') {
            $labels = array_unique(array_map('mb_strtolower', $filled));

            if ((count($filled) < 4) || (count($labels) > 10) || (count($labels) * 2 > count($filled))) {
                continue;
            }

            foreach ($filled as $value) {
                if (mb_strlen($value) > ws_wtable_limits()['status_label']) {
                    continue 2;
                }
            }
        }

        return array('type' => $type, 'options' => ws_wtable_import_options($type, $filled));
    }

    return array('type' => 'text', 'options' => array());
}

/**
 * The options a column of a type is made with, from its values: a number's
 * decimals (the most written, 0-6), money's currency (the first named) with
 * 2 decimals, whether a date keeps a time, a status's choices (each label
 * once, in the order met).
 *
 * @param string   $type
 * @param string[] $values
 * @return array
 */
function ws_wtable_import_options($type, $values)
{
    $values = array_filter(array_map('trim', array_map('strval', (array) $values)), 'strlen');

    switch ($type) {
        case 'number':
            $decimals = 0;

            foreach ($values as $value) {
                $number = (string) ws_wtable_import_number($value);
                $at = strpos($number, '.');
                $decimals = max($decimals, ($at === false) ? 0 : min(6, strlen($number) - $at - 1));
            }

            return array('decimals' => $decimals);

        case 'money':
            foreach ($values as $value) {
                $money = ws_wtable_import_money($value);

                if ($money && ($money['currency'] !== '')) {
                    return array('decimals' => 2, 'currency' => $money['currency']);
                }
            }

            return array('decimals' => 2, 'currency' => '');

        case 'date':
            foreach ($values as $value) {
                $date = ws_wtable_import_date($value);

                if ($date && ($date['time'] !== '') && ($date['time'] !== '00:00')) {
                    return array('time' => true);
                }
            }

            return array('time' => false);

        case 'status':
            $labels = array();

            foreach ($values as $value) {
                $labels[mb_strtolower($value)] = isset($labels[mb_strtolower($value)]) ? $labels[mb_strtolower($value)] : $value;
            }

            return array('choices' => ws_wtable_screen_status_choices(implode("\n", $labels)));
    }

    return array();
}

/**
 * One value of the file as the store's cell check takes it for a column:
 * a number, money in minor units (rounded, never cut), a date in the
 * column's form, a status's key by its label, a tick, a colleague by
 * name or user name ($people), a record link from kind:id. A value it cannot
 * read is given as written, so that the store answers with its own code.
 *
 * @param array  $column type, options
 * @param string $text
 * @param array  $people lower-case name or user name => user id
 * @return mixed
 */
function ws_wtable_import_cell($column, $text, $people = array())
{
    $type = (string) ($column['type'] ?? '');
    $options = (array) ($column['options'] ?? array());
    $text = trim((string) $text);

    if ($text === '') {
        return null;
    }

    switch ($type) {
        case 'number':
            $number = ws_wtable_import_number($text);

            return ($number === null) ? $text : $number;

        case 'money':
            $money = ws_wtable_import_money($text);

            if ($money === null) {
                return $text;
            }

            return (int) round((float) $money['amount'] * pow(10, (int) ($options['decimals'] ?? 2)));

        case 'date':
            $date = ws_wtable_import_date($text);

            if ($date === null) {
                return $text;
            }

            if (!empty($options['time'])) {
                return $date['date'] . ' ' . (($date['time'] !== '') ? $date['time'] : '00:00');
            }

            return (($date['time'] === '') || ($date['time'] === '00:00')) ? $date['date'] : $text;

        case 'status':
            foreach ((array) ($options['choices'] ?? array()) as $choice) {
                if ((mb_strtolower((string) $choice['label']) === mb_strtolower($text)) || ((string) $choice['key'] === $text)) {
                    return (string) $choice['key'];
                }
            }

            return $text;

        case 'checkbox':
            $tick = ws_wtable_import_checkbox($text);

            return ($tick === null) ? $text : $tick;

        case 'person':
            return $people[mb_strtolower($text)] ?? (ctype_digit($text) ? (int) $text : $text);

        case 'link':
            return ws_wtable_screen_cell_from_form($column, $text);
    }

    return $text;
}

/**
 * The file's columns matched to a table's by name, case and spaces aside;
 * each table column takes one file column, a formula column none.
 *
 * @param string[] $names   the file's column names
 * @param array    $columns column id => column (name, type)
 * @return array map (file column index => column id), unmatched (names)
 */
function ws_wtable_import_match($names, $columns)
{
    $key = function ($name) {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
    };

    $by_name = array();

    foreach ((array) $columns as $id => $column) {
        if (ws_wtable_type_stores((string) $column['type']) && !isset($by_name[$key($column['name'])])) {
            $by_name[$key($column['name'])] = (int) $id;
        }
    }

    $map = array();
    $unmatched = array();

    foreach (array_values((array) $names) as $i => $name) {
        $id = $by_name[$key($name)] ?? 0;

        if (($id > 0) && !in_array($id, $map, true)) {
            $map[$i] = $id;
        } else {
            $unmatched[] = (string) $name;
        }
    }

    return array('map' => $map, 'unmatched' => $unmatched);
}

/**
 * The cells of one file row for a table: column id => value for the store,
 * by the map of ws_wtable_import_match().
 *
 * @param array $row     the file row's values
 * @param array $map     file column index => column id
 * @param array $columns column id => column
 * @param array $people  as ws_wtable_import_cell()
 * @return array
 */
function ws_wtable_import_row_cells($row, $map, $columns, $people = array())
{
    $cells = array();

    foreach ((array) $map as $i => $id) {
        if (isset($columns[$id])) {
            $cells[$id] = ws_wtable_import_cell($columns[$id], (string) ($row[$i] ?? ''), $people);
        }
    }

    return $cells;
}

/**
 * A CSV or spreadsheet file as a table of trimmed values, blank lines
 * dropped: a spreadsheet through the product import's reader, a CSV with
 * the separator its first line uses most. Reads one line more than $max so
 * that a longer file is told (more).
 *
 * The one reader of a table file: the bulk product drafts read through it
 * too (ws_bulk_file_table()), capped at a product card's rows.
 *
 * @param string $path
 * @param string $file_name the name the person's machine gave it
 * @param int    $max       lines to keep, header included
 * @return array ok, error, table, more
 */
function ws_wtable_import_file_table($path, $file_name, $max)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'table' => array(), 'more' => false);
    };

    if (!preg_match('/\.(csv|xlsx|xlsm|xls|ods)$/i', (string) $file_name)) {
        return $fail(lang('Import a CSV, Excel or OpenDocument file.'));
    }

    $csv = (string) $path;
    $spreadsheet = !preg_match('/\.csv$/i', (string) $file_name);

    if ($spreadsheet) {
        require_once(PG_FUNCTIONS_DIR . '/import_products_f.php');

        $error = '';
        $csv = pg_products_import_to_csv($path, $file_name, $error);

        if ($csv === '') {
            return $fail(($error !== '') ? $error : lang('The file could not be read.'));
        }
    }

    $handle = @fopen($csv, 'rb');

    if (!$handle) {
        return $fail(lang('The file could not be read.'));
    }

    $first = (string) fgets($handle);
    $first = (strpos($first, "\xEF\xBB\xBF") === 0) ? substr($first, 3) : $first;
    $counts = array(';' => substr_count($first, ';'), ',' => substr_count($first, ','), "\t" => substr_count($first, "\t"));
    arsort($counts);
    $separator = (string) key($counts);
    rewind($handle);

    $table = array();
    $more = false;

    while (($row = fgetcsv($handle, 0, $separator, '"', '\\')) !== false) {
        if (empty($table) && isset($row[0]) && (strpos((string) $row[0], "\xEF\xBB\xBF") === 0)) {
            $row[0] = substr((string) $row[0], 3);
        }

        $row = array_map('trim', array_map('strval', (array) $row));

        if (implode('', $row) === '') {
            continue;
        }

        if (count($table) >= $max) {
            $more = true;
            break;
        }

        $table[] = $row;
    }

    fclose($handle);

    if ($spreadsheet) {
        @unlink($csv);
    }

    if (empty($table)) {
        return $fail(lang('The file was empty.'));
    }

    return array('ok' => true, 'error' => '', 'table' => $table, 'more' => $more);
}

/* ---------------------------------------------------------------------------
   A note's tables
   --------------------------------------------------------------------------- */

/**
 * The tables a note's text holds, read the way ws_render_body() draws them:
 * a row of cells, the row of dashes under it, then every row that follows;
 * a table inside a code fence is code. Each row has the drawn table's
 * column count (at most 12), cells padded or cut to it. A "::: Title" line
 * right above a table names it.
 *
 * @param string $body the note's text
 * @param int    $max  rows to keep per table, header included
 * @return array list of title, line (0-based, of the header), table, more
 */
function ws_wtable_import_note_tables($body, $max = 5001)
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $body);
    $count = count($lines);
    $row_pattern = '/^\s*\|.*\|\s*$/';
    $tables = array();

    for ($i = 0; $i < $count; $i++) {
        if (ws_code_fence_open($lines[$i]) !== null) {
            $i++;

            while (($i < $count) && !ws_code_fence_close($lines[$i])) {
                $i++;
            }

            continue;
        }

        $alignments = (($i + 1) < $count) ? ws_table_alignments($lines[$i + 1]) : null;

        if (!preg_match($row_pattern, $lines[$i]) || ($alignments === null)) {
            continue;
        }

        $header = ws_table_cells($lines[$i]);
        $columns = min(12, max(count($header), count($alignments)));
        $fit = function ($cells) use ($columns) {
            return array_slice(array_pad($cells, $columns, ''), 0, $columns);
        };
        $table = array($fit($header));
        $more = false;
        $j = $i + 2;

        while (($j < $count) && preg_match($row_pattern, $lines[$j])) {
            if (count($table) >= $max) {
                $more = true;
            } else {
                $table[] = $fit(ws_table_cells($lines[$j]));
            }

            $j++;
        }

        $title = ($i > 0) ? ws_block_title_line($lines[$i - 1]) : null;
        $tables[] = array('title' => (string) $title, 'line' => $i, 'table' => $table, 'more' => $more);
        $i = $j - 1;
    }

    return $tables;
}

/**
 * One cell of a note's table as plain text: a person's tag becomes their
 * name ($names, else the user id, which a person column takes too), a
 * record's tag its kind:id (what a record link column takes), a link its
 * words, and the bold, italic, strike, highlight and code marks around the
 * whole cell go.
 *
 * @param string $text
 * @param array  $names user id => name
 * @return string
 */
function ws_wtable_import_note_cell($text, $names = array())
{
    $text = preg_replace_callback('/<([@#])([a-z_]+):([0-9]{1,10})>/', function ($match) use ($names) {
        if ($match[1] === '@') {
            return ($match[2] === 'user') ? (string) ($names[(int) $match[3]] ?? $match[3]) : '';
        }

        return $match[2] . ':' . $match[3];
    }, (string) $text);

    $text = preg_replace('/\[([^\]]*)\]\([^)\s]*\)/u', '$1', $text);
    $text = trim($text);

    while (preg_match('/^(\*\*|__|~~|==|`|\*|_)(\S(?:.*\S)?)\1$/us', $text, $match)) {
        $text = trim($match[2]);
    }

    return $text;
}

/**
 * A note's table as a file import reads it: the header, then the rows with
 * each cell as plain text (ws_wtable_import_note_cell()) and a formula's
 * worked-out value in place of its source (empty on an error).
 *
 * @param array $table  rows of cells as written, header first
 * @param array $names  user id => name
 * @param array $scope  the note's calculation names (ws_calc_body_scope())
 * @return array[]
 */
function ws_wtable_import_note_rows($table, $names = array(), $scope = array())
{
    $table = array_values((array) $table);
    $grid = (function_exists('ws_calc_has_formula') && ws_calc_has_formula($table)) ? ws_calc_grid($table, $scope) : array();
    $out = array();

    foreach ($table as $r => $row) {
        foreach (array_values((array) $row) as $c => $cell) {
            if (!empty($grid[$r][$c]['formula'])) {
                $value = $grid[$r][$c]['value'];
                $cell = (($grid[$r][$c]['error'] !== '') || ($value === null)) ? ''
                    : (is_numeric($value) ? rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.') : (string) $value);
            }

            $out[$r][$c] = ($r === 0) ? ws_wtable_import_note_cell($cell) : ws_wtable_import_note_cell($cell, $names);
        }
    }

    return $out;
}

/**
 * The notes a person may read whose text holds a table, each table one
 * choice: own notes and notes shared with them by name, as the notes list
 * reads them. A note longer than the list's cut is read whole (up to 50 of
 * them), so a table further down is offered too.
 *
 * @param array $viewer
 * @return array list of note (id), block (index), label
 */
function ws_wtable_import_note_choices($viewer)
{
    if (!function_exists('ws_notes_list')) {
        return array();
    }

    $list = ws_notes_list($viewer);
    $notes = array_merge((array) $list['mine'], (array) $list['shared']);
    $long = array();

    foreach ($notes as $note) {
        if ((mb_strlen((string) $note['body']) >= WS_NOTE_LIST_BODY) && (strpos((string) $note['body'], '|') !== false) && (count($long) < 50)) {
            $long[] = (int) $note['id'];
        }
    }

    $whole = array();

    if (!empty($long)) {
        foreach ((array) db_items("SELECT id, body FROM ws_notes WHERE id IN (" . implode(',', $long) . ")") as $row) {
            $whole[(int) $row['id']] = (string) $row['body'];
        }
    }

    $choices = array();

    foreach ($notes as $note) {
        $body = $whole[(int) $note['id']] ?? (string) $note['body'];

        if (strpos($body, '|') === false) {
            continue;
        }

        $tables = ws_wtable_import_note_tables($body, 2);
        $note_title = trim((string) ($note['title'] ?? ''));
        $note_title = ($note_title !== '') ? $note_title : lang('Untitled note');

        foreach ($tables as $index => $table) {
            $label = ($table['title'] !== '') ? $table['title'] : implode(', ', array_filter($table['table'][0], 'strlen'));
            $choices[] = array(
                'note'  => (int) $note['id'],
                'block' => $index,
                'label' => mb_strimwidth($note_title . ((count($tables) > 1 || $label !== '') ? ' — ' . $label : ''), 0, 120, '…'),
            );
        }
    }

    return $choices;
}

/**
 * Read one table of a note the person may read, as a file import's read:
 * the note through ws_note_access(), never changed.
 *
 * @param array $viewer
 * @param int   $note_id
 * @param int   $block   the table's index in the note
 * @param int   $max     rows to keep, header included
 * @return array ok, error, table, more, name (the note's title, or the table's)
 */
function ws_wtable_import_note_read($viewer, $note_id, $block, $max)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'table' => array(), 'more' => false, 'name' => '');
    };

    $note = function_exists('ws_note') ? ws_note((int) $note_id) : null;

    if (!$note || (ws_note_access($viewer, $note) === '')) {
        return $fail(lang('That note could not be found.'));
    }

    $tables = ws_wtable_import_note_tables((string) $note['body'], $max);

    if (!isset($tables[(int) $block])) {
        return $fail(lang('That table is no longer in the note.'));
    }

    $chosen = $tables[(int) $block];
    $names = array();

    foreach ((array) ws_team_members() as $person) {
        $names[(int) $person['id']] = (string) $person['name'];
    }

    $scope = function_exists('ws_calc_body_scope') ? ws_calc_body_scope(preg_split('/\r\n|\r|\n/', (string) $note['body']))['all'] : array();
    $name = ($chosen['title'] !== '') ? $chosen['title'] : trim((string) $note['title']);

    return array(
        'ok'    => true,
        'error' => '',
        'table' => ws_wtable_import_note_rows($chosen['table'], $names, $scope),
        'more'  => $chosen['more'],
        'name'  => ($name !== '') ? $name : lang('Untitled note'),
    );
}

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

/**
 * The team by lower-case name and user name, for person cells.
 *
 * @return array
 */
function ws_wtable_import_people()
{
    $people = array();

    foreach ((array) ws_team_members() as $person) {
        foreach (array('name', 'username') as $key) {
            if (isset($person[$key]) && ((string) $person[$key] !== '')) {
                $people[mb_strtolower((string) $person[$key])] = (int) $person['id'];
            }
        }
    }

    return $people;
}

/**
 * Add the file's rows to a table, each through ws_wtable_row_create() as
 * one person's add, none announced. A refused row is reported with its
 * number (the header is row 1) and its cells' codes by column name; at the row limit the rest
 * is reported as one too_many_rows line.
 *
 * @param array $viewer
 * @param array $table ws_wtable()
 * @param array $rows  the file's rows, header left out
 * @param array $map   file column index => column id
 * @return array added, refused (list of line, error, errors), refused_count
 */
function ws_wtable_import_rows($viewer, $table, $rows, $map)
{
    $columns = ws_wtable_columns((int) $table['id']);
    $people = ws_wtable_import_people();
    $report = array('added' => 0, 'refused' => array(), 'refused_count' => 0);
    $refuse = function ($line, $error, $errors) use (&$report) {
        $report['refused_count']++;

        if (count($report['refused']) < 100) {
            $report['refused'][] = array('line' => $line, 'error' => $error, 'errors' => $errors);
        }
    };

    ws_wtable_row_announce_quiet(true);

    foreach (array_values($rows) as $i => $row) {
        $cells = ws_wtable_import_row_cells($row, $map, $columns, $people);

        $filled = array_filter($cells, function ($value) {
            return ($value !== null) && ($value !== false);
        });

        if (empty($filled)) {
            continue;
        }

        $result = ws_wtable_row_create($viewer, $table, array('cells' => $cells));

        if ($result['ok']) {
            $report['added']++;
            continue;
        }

        if ($result['error'] === 'too_many_rows') {
            $refuse($i + 2, 'too_many_rows', array());
            $report['refused_count'] += count($rows) - $i - 1;
            break;
        }

        $errors = array();

        foreach ((array) ($result['errors'] ?? array()) as $id => $code) {
            $errors[(string) ($columns[$id]['name'] ?? $id)] = (string) $code;
        }

        $refuse($i + 2, (string) $result['error'], $errors);

        if (!in_array($result['error'], array('cells_invalid'), true)) {
            $report['refused_count'] += count($rows) - $i - 1;
            break;
        }
    }

    ws_wtable_row_announce_quiet(false);

    return $report;
}

/**
 * Make a table from a file: the table, then a column per kept file column
 * in the type the person chose, then the rows. A refused table or column
 * stops it, and a table made by then is deleted, so nothing half-made
 * stays.
 *
 * @param array  $viewer
 * @param string $name     the table's name
 * @param array  $plan     list of index (file column), name, type
 * @param array  $rows     the file's rows, header left out
 * @return array ok, error, table_id, report (as ws_wtable_import_rows())
 */
function ws_wtable_import_create($viewer, $name, $plan, $rows)
{
    $made = ws_wtable_create($viewer, array('name' => $name, 'description' => ''));

    if (!$made['ok']) {
        return array('ok' => false, 'error' => $made['error'], 'table_id' => 0, 'report' => array());
    }

    $table = ws_wtable((int) $made['table_id']);
    $map = array();

    foreach ((array) $plan as $column) {
        $values = array();

        foreach ($rows as $row) {
            $values[] = (string) ($row[(int) $column['index']] ?? '');
        }

        $type = in_array($column['type'], ws_wtable_import_types(), true) ? $column['type'] : 'text';
        $result = ws_wtable_column_create($viewer, $table, array('name' => $column['name'], 'type' => $type, 'options' => ws_wtable_import_options($type, $values)));

        if (!$result['ok']) {
            ws_wtable_delete($viewer, $table);

            return array('ok' => false, 'error' => $result['error'], 'table_id' => 0, 'report' => array(), 'column' => (string) $column['name']);
        }

        $map[(int) $column['index']] = (int) $result['column_id'];
    }

    $report = ws_wtable_import_rows($viewer, ws_wtable((int) $made['table_id']), $rows, $map);

    return array('ok' => true, 'error' => '', 'table_id' => (int) $made['table_id'], 'report' => $report);
}

/**
 * Keep a read file between the upload and the confirmation: its table as
 * JSON in the system's temp folder, the path in the person's session. A
 * file kept before is dropped.
 *
 * @param array  $read      ws_wtable_import_file_table()
 * @param string $file_name
 * @param int    $table_id  the table rows are added to, 0 for a new one
 * @param bool   $note      read from a note: $file_name is the name to offer
 * @return bool
 */
function ws_wtable_import_keep($read, $file_name, $table_id, $note = false)
{
    ws_wtable_import_drop();

    $path = @tempnam(sys_get_temp_dir(), 'pg_wtimport_');

    if (($path === false) || (@file_put_contents($path, json_encode($read['table'], JSON_UNESCAPED_UNICODE)) === false)) {
        return false;
    }

    $_SESSION['software']['ws_wtable_import'] = array('path' => $path, 'file' => (string) $file_name, 'table' => (int) $table_id, 'more' => !empty($read['more']), 'note' => (bool) $note);

    return true;
}

/**
 * The file kept by ws_wtable_import_keep(), or null: file (name), table
 * (id, 0 for a new one), more, note (read from a note), names (the
 * header), rows.
 *
 * @return array|null
 */
function ws_wtable_import_kept()
{
    $kept = $_SESSION['software']['ws_wtable_import'] ?? null;
    $path = is_array($kept) ? (string) ($kept['path'] ?? '') : '';

    // Only a file this code wrote is read back.
    if (($path === '') || (strpos(pg_basename($path), 'pg_wtimport_') !== 0) || (realpath(dirname($path)) !== realpath(sys_get_temp_dir())) || !is_file($path)) {
        return null;
    }

    $table = json_decode((string) @file_get_contents($path), true);

    if (!is_array($table) || empty($table)) {
        return null;
    }

    return array(
        'file'  => (string) $kept['file'],
        'table' => (int) $kept['table'],
        'more'  => !empty($kept['more']),
        'note'  => !empty($kept['note']),
        'names' => ws_wtable_import_names(array_shift($table)),
        'rows'  => $table,
    );
}

/** Forget the kept file and delete it. */
function ws_wtable_import_drop()
{
    $kept = $_SESSION['software']['ws_wtable_import'] ?? null;
    $path = is_array($kept) ? (string) ($kept['path'] ?? '') : '';

    if (($path !== '') && (strpos(pg_basename($path), 'pg_wtimport_') === 0) && is_file($path)) {
        @unlink($path);
    }

    unset($_SESSION['software']['ws_wtable_import']);
}
