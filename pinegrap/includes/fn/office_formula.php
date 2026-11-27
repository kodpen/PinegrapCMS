<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Simple spreadsheet formulas, read and worked out the way Excel works them
 * out. Pure functions: no file, no database, no request.
 *
 * A simple formula holds numbers, cell references and ranges of its own sheet
 * ("A1", "$A$1", "A1:B9"), the operators + - * /, brackets, a leading minus
 * or plus, and the functions SUM, AVERAGE, MIN, MAX and COUNT with one or more
 * arguments. A function may be typed with its English name or, for a panel
 * language that has them, its local name; arguments are parted by "," or ";".
 * Anything else (another function, another sheet, a name, a text constant, a
 * comparison, a whole column, an array formula) is not a simple formula: the
 * reader answers null and the caller keeps the formula as it is.
 *
 * The reader gives a tree; from the tree come the cells the formula reads,
 * its text as Excel stores it (English names, commas, no leading "=") and its
 * value from the sheet's cell values. Values follow Excel: an empty cell is 0
 * in arithmetic, a text that reads as a number is that number, any other text
 * is #VALUE!, a division by zero is #DIV/0!, the first error met is passed
 * on, and the functions leave text, logical values and empty cells of a
 * reference out.
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
 * Excel's limits a formula keeps: characters of its text, levels of brackets
 * and functions inside each other, arguments of one function.
 */
define('PG_OFFICE_FORMULA_MAX_LENGTH', 8192);
define('PG_OFFICE_FORMULA_MAX_DEPTH', 64);
define('PG_OFFICE_FORMULA_MAX_ARGUMENTS', 255);

/* ---------------------------------------------------------------------------
   Function names
   --------------------------------------------------------------------------- */

/**
 * The simple functions' names in a language, English name => local name.
 * English names are always understood; a language without names of its own
 * gets the English ones.
 *
 * @param string $language A software language code ('en', 'tr').
 * @return array
 */
function pg_office_formula_names($language = 'en')
{
    $names = array(
        'tr' => array(
            'SUM' => 'TOPLA',
            'AVERAGE' => 'ORTALAMA',
            'MIN' => "M\xC4\xB0N",
            'MAX' => 'MAK',
            'COUNT' => 'SAY',
        ),
    );

    $language = strtolower((string) $language);

    if (isset($names[$language])) {
        return $names[$language];
    }

    return array('SUM' => 'SUM', 'AVERAGE' => 'AVERAGE', 'MIN' => 'MIN', 'MAX' => 'MAX', 'COUNT' => 'COUNT');
}

/**
 * A typed function name folded for comparison: upper case, and the Turkish
 * dotted and dotless i read as I, so "min", "MİN" and "mın" are one name.
 *
 * @param string $name
 * @return string
 */
function pg_office_formula_name_key($name)
{
    $name = str_replace(array("\xC4\xB1", "\xC4\xB0"), 'I', (string) $name);

    return strtoupper($name);
}

/**
 * The English name of a typed function name, or null when it is not one of
 * the simple functions in English or in the language given.
 *
 * @param string $name
 * @param string $language
 * @return string|null
 */
function pg_office_formula_function($name, $language = 'en')
{
    $key = pg_office_formula_name_key($name);

    foreach (pg_office_formula_names('en') as $english => $unused) {
        if ($key === $english) {
            return $english;
        }
    }

    foreach (pg_office_formula_names($language) as $english => $local) {
        if ($key === pg_office_formula_name_key($local)) {
            return $english;
        }
    }

    return null;
}

/* ---------------------------------------------------------------------------
   Reading a formula
   --------------------------------------------------------------------------- */

/**
 * A formula's text as tokens, or null when it holds a character or a word no
 * simple formula holds. Each token is [kind, text]: number, word, op
 * (+ - * /), open, close, colon, separator (, or ;).
 *
 * @param string $text
 * @return array|null
 */
function pg_office_formula_tokens($text)
{
    $tokens = array();
    $length = strlen($text);
    $position = 0;

    while ($position < $length) {
        $character = $text[$position];

        if (($character === ' ') || ($character === "\t") || ($character === "\n") || ($character === "\r")) {
            $position++;
            continue;
        }

        if (preg_match('/\G(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', $text, $match, 0, $position)) {
            $tokens[] = array('number', $match[0]);
            $position += strlen($match[0]);
            continue;
        }

        if (preg_match('/\G[A-Za-z_$\x80-\xFF][A-Za-z0-9_.$\x80-\xFF]*/', $text, $match, 0, $position)) {
            $tokens[] = array('word', $match[0]);
            $position += strlen($match[0]);
            continue;
        }

        $kinds = array('+' => 'op', '-' => 'op', '*' => 'op', '/' => 'op', '(' => 'open', ')' => 'close', ':' => 'colon', ',' => 'separator', ';' => 'separator');

        if (!isset($kinds[$character])) {
            return null;
        }

        $tokens[] = array($kinds[$character], $character);
        $position++;
    }

    return $tokens;
}

/**
 * A cell reference word as a cell node, or null when it is not one inside a
 * sheet's grid (16384 columns, 1048576 rows).
 *
 * @param string $word
 * @return array|null
 */
function pg_office_formula_cell_node($word)
{
    if (!preg_match('/^(\$?)([A-Za-z]{1,3})(\$?)([1-9][0-9]{0,6})$/', $word, $match)) {
        return null;
    }

    $letters = strtoupper($match[2]);
    $column = 0;

    for ($index = 0; $index < strlen($letters); $index++) {
        $column = ($column * 26) + (ord($letters[$index]) - 64);
    }

    $row = (int) $match[4];

    if (($column > 16384) || ($row > 1048576)) {
        return null;
    }

    return array(
        'type' => 'cell',
        'row' => $row,
        'column' => $column - 1,
        'absolute_column' => ($match[1] === '$'),
        'absolute_row' => ($match[3] === '$'),
    );
}

/**
 * A formula's text as a tree, or null when it is not a simple formula. A
 * leading "=" is allowed. Function names are understood in English and in
 * the language given.
 *
 * Nodes: number (value), cell (row 1-based, column 0-based, absolute_row,
 * absolute_column), range (from, to: cells), unary (op, operand), binary
 * (op, left, right), group (inner: a bracketed part), function (name in
 * English, arguments). A range stands only as a function's argument.
 *
 * @param string $text
 * @param string $language
 * @return array|null
 */
function pg_office_formula_parse($text, $language = 'en')
{
    $text = trim((string) $text);

    if (($text !== '') && ($text[0] === '=')) {
        $text = substr($text, 1);
    }

    if (($text === '') || (strlen($text) > PG_OFFICE_FORMULA_MAX_LENGTH)) {
        return null;
    }

    $tokens = pg_office_formula_tokens($text);

    if (!$tokens) {
        return null;
    }

    $state = array('tokens' => $tokens, 'position' => 0, 'depth' => 0, 'language' => (string) $language);
    $tree = pg_office_formula_parse_sum($state);

    if (($tree === null) || ($state['position'] !== count($tokens))) {
        return null;
    }

    if (!pg_office_formula_ranges_placed($tree, false)) {
        return null;
    }

    return $tree;
}

/**
 * The next token's kind and text, or [null, null] at the end.
 *
 * @param array $state
 * @return array
 */
function pg_office_formula_peek($state)
{
    return isset($state['tokens'][$state['position']]) ? $state['tokens'][$state['position']] : array(null, null);
}

/**
 * Additions and subtractions, left to right.
 *
 * @param array $state
 * @return array|null
 */
function pg_office_formula_parse_sum(&$state)
{
    $left = pg_office_formula_parse_product($state);

    while ($left !== null) {
        list($kind, $text) = pg_office_formula_peek($state);

        if (($kind !== 'op') || (($text !== '+') && ($text !== '-'))) {
            break;
        }

        $state['position']++;
        $right = pg_office_formula_parse_product($state);

        if ($right === null) {
            return null;
        }

        $left = array('type' => 'binary', 'op' => $text, 'left' => $left, 'right' => $right);
    }

    return $left;
}

/**
 * Multiplications and divisions, left to right.
 *
 * @param array $state
 * @return array|null
 */
function pg_office_formula_parse_product(&$state)
{
    $left = pg_office_formula_parse_unary($state);

    while ($left !== null) {
        list($kind, $text) = pg_office_formula_peek($state);

        if (($kind !== 'op') || (($text !== '*') && ($text !== '/'))) {
            break;
        }

        $state['position']++;
        $right = pg_office_formula_parse_unary($state);

        if ($right === null) {
            return null;
        }

        $left = array('type' => 'binary', 'op' => $text, 'left' => $left, 'right' => $right);
    }

    return $left;
}

/**
 * A leading minus or plus, which in Excel binds closer than * and /.
 *
 * @param array $state
 * @return array|null
 */
function pg_office_formula_parse_unary(&$state)
{
    list($kind, $text) = pg_office_formula_peek($state);

    if (($kind === 'op') && (($text === '-') || ($text === '+'))) {
        $state['position']++;

        if (++$state['depth'] > PG_OFFICE_FORMULA_MAX_DEPTH) {
            return null;
        }

        $operand = pg_office_formula_parse_unary($state);
        $state['depth']--;

        return ($operand === null) ? null : array('type' => 'unary', 'op' => $text, 'operand' => $operand);
    }

    return pg_office_formula_parse_primary($state);
}

/**
 * A number, a cell, a range, a bracketed part or a function call.
 *
 * @param array $state
 * @return array|null
 */
function pg_office_formula_parse_primary(&$state)
{
    list($kind, $text) = pg_office_formula_peek($state);

    if ($kind === 'number') {
        $state['position']++;
        $value = (float) $text;

        return is_finite($value) ? array('type' => 'number', 'value' => $value) : null;
    }

    if ($kind === 'open') {
        $state['position']++;

        if (++$state['depth'] > PG_OFFICE_FORMULA_MAX_DEPTH) {
            return null;
        }

        $inner = pg_office_formula_parse_sum($state);
        $state['depth']--;
        list($kind) = pg_office_formula_peek($state);

        if (($inner === null) || ($kind !== 'close')) {
            return null;
        }

        $state['position']++;

        return array('type' => 'group', 'inner' => $inner);
    }

    if ($kind !== 'word') {
        return null;
    }

    $state['position']++;
    list($next) = pg_office_formula_peek($state);

    if ($next === 'open') {
        return pg_office_formula_parse_call($state, $text);
    }

    $cell = pg_office_formula_cell_node($text);

    if ($cell === null) {
        return null;
    }

    if ($next !== 'colon') {
        return $cell;
    }

    $state['position']++;
    list($kind, $text) = pg_office_formula_peek($state);
    $to = ($kind === 'word') ? pg_office_formula_cell_node($text) : null;

    if ($to === null) {
        return null;
    }

    $state['position']++;

    return array('type' => 'range', 'from' => $cell, 'to' => $to);
}

/**
 * A function call, its name read and the next token its opening bracket.
 *
 * @param array $state
 * @param string $name
 * @return array|null
 */
function pg_office_formula_parse_call(&$state, $name)
{
    $english = pg_office_formula_function($name, $state['language']);

    if (($english === null) || (++$state['depth'] > PG_OFFICE_FORMULA_MAX_DEPTH)) {
        return null;
    }

    $state['position']++;
    $arguments = array();

    while (true) {
        $argument = pg_office_formula_parse_sum($state);

        if (($argument === null) || (count($arguments) >= PG_OFFICE_FORMULA_MAX_ARGUMENTS)) {
            return null;
        }

        $arguments[] = $argument;
        list($kind) = pg_office_formula_peek($state);
        $state['position']++;

        if ($kind === 'close') {
            break;
        }

        if ($kind !== 'separator') {
            return null;
        }
    }

    $state['depth']--;

    return array('type' => 'function', 'name' => $english, 'arguments' => $arguments);
}

/**
 * Whether every range of a tree stands as a function's argument, alone or in
 * brackets: elsewhere Excel would intersect it or spill it.
 *
 * @param array $node
 * @param bool $argument Whether the node is a function's argument.
 * @return bool
 */
function pg_office_formula_ranges_placed($node, $argument)
{
    switch ($node['type']) {
        case 'range':
            return $argument;
        case 'group':
            return pg_office_formula_ranges_placed($node['inner'], $argument);
        case 'unary':
            return pg_office_formula_ranges_placed($node['operand'], false);
        case 'binary':
            return pg_office_formula_ranges_placed($node['left'], false) && pg_office_formula_ranges_placed($node['right'], false);
        case 'function':
            foreach ($node['arguments'] as $child) {
                if (!pg_office_formula_ranges_placed($child, true)) {
                    return false;
                }
            }
    }

    return true;
}

/* ---------------------------------------------------------------------------
   What a formula reads, and its text
   --------------------------------------------------------------------------- */

/**
 * The cells a formula reads, as areas [first row, first column, last row,
 * last column] in the order they are written, each once. A single cell is
 * an area of one cell.
 *
 * @param array $node A tree from pg_office_formula_parse().
 * @return array
 */
function pg_office_formula_cells($node)
{
    $areas = array();
    pg_office_formula_collect_cells($node, $areas);

    return array_values($areas);
}

/**
 * Adds a tree's areas to a list keyed by the area.
 *
 * @param array $node
 * @param array $areas
 * @return void
 */
function pg_office_formula_collect_cells($node, &$areas)
{
    switch ($node['type']) {
        case 'cell':
            $area = array($node['row'], $node['column'], $node['row'], $node['column']);
            break;
        case 'range':
            $area = pg_office_formula_area($node);
            break;
        case 'group':
            pg_office_formula_collect_cells($node['inner'], $areas);
            return;
        case 'unary':
            pg_office_formula_collect_cells($node['operand'], $areas);
            return;
        case 'binary':
            pg_office_formula_collect_cells($node['left'], $areas);
            pg_office_formula_collect_cells($node['right'], $areas);
            return;
        case 'function':
            foreach ($node['arguments'] as $child) {
                pg_office_formula_collect_cells($child, $areas);
            }
            return;
        default:
            return;
    }

    $areas[implode(',', $area)] = $area;
}

/**
 * A range node's area, top left first whichever corner was typed first.
 *
 * @param array $node
 * @return array [first row, first column, last row, last column]
 */
function pg_office_formula_area($node)
{
    return array(
        min($node['from']['row'], $node['to']['row']),
        min($node['from']['column'], $node['to']['column']),
        max($node['from']['row'], $node['to']['row']),
        max($node['from']['column'], $node['to']['column']),
    );
}

/**
 * Whether a formula reads a cell.
 *
 * @param array $node A tree from pg_office_formula_parse().
 * @param int $row 1-based.
 * @param int $column 0-based.
 * @return bool
 */
function pg_office_formula_reads($node, $row, $column)
{
    foreach (pg_office_formula_cells($node) as $area) {
        if (($row >= $area[0]) && ($row <= $area[2]) && ($column >= $area[1]) && ($column <= $area[3])) {
            return true;
        }
    }

    return false;
}

/**
 * A number as a formula writes it: whole numbers below 1E+15 without a
 * point, others with up to 15 significant digits, as Excel shows them.
 *
 * @param float $value
 * @return string
 */
function pg_office_formula_number_text($value)
{
    $value = (float) $value;

    if (($value === floor($value)) && (abs($value) < 1e15)) {
        return sprintf('%.0f', $value);
    }

    $text = sprintf('%.15G', $value);

    if (strpos($text, 'E') !== false) {
        list($mantissa, $exponent) = explode('E', $text);
        $mantissa = (strpos($mantissa, '.') !== false) ? rtrim(rtrim($mantissa, '0'), '.') : $mantissa;
        $sign = ($exponent[0] === '-') ? '-' : '+';

        // Excel writes the exponent with two digits at least: 1.5E-07.
        return $mantissa . 'E' . $sign . str_pad(ltrim($exponent, '+-'), 2, '0', STR_PAD_LEFT);
    }

    return (strpos($text, '.') !== false) ? rtrim(rtrim($text, '0'), '.') : $text;
}

/**
 * A tree as a formula's text without the leading "=": as Excel stores it
 * (English names, commas, upper-case references, no spaces) by default, or
 * with the language's function names for showing it to a person.
 *
 * @param array $node
 * @param string $language
 * @return string
 */
function pg_office_formula_text($node, $language = 'en')
{
    switch ($node['type']) {
        case 'number':
            return pg_office_formula_number_text($node['value']);
        case 'cell':
            return ($node['absolute_column'] ? '$' : '') . pg_office_xlsx_column_name($node['column'])
                . ($node['absolute_row'] ? '$' : '') . $node['row'];
        case 'range':
            return pg_office_formula_text($node['from'], $language) . ':' . pg_office_formula_text($node['to'], $language);
        case 'group':
            return '(' . pg_office_formula_text($node['inner'], $language) . ')';
        case 'unary':
            return $node['op'] . pg_office_formula_text($node['operand'], $language);
        case 'binary':
            return pg_office_formula_text($node['left'], $language) . $node['op'] . pg_office_formula_text($node['right'], $language);
        case 'function':
            $names = pg_office_formula_names($language);
            $parts = array();

            foreach ($node['arguments'] as $child) {
                $parts[] = pg_office_formula_text($child, $language);
            }

            return $names[$node['name']] . '(' . implode(',', $parts) . ')';
    }

    return '';
}

/* ---------------------------------------------------------------------------
   Working a formula out
   --------------------------------------------------------------------------- */

/**
 * An error value, as a cell holds it.
 *
 * @param string $code '#DIV/0!', '#VALUE!', '#REF!', '#NUM!' …
 * @return array
 */
function pg_office_formula_error($code)
{
    return array('error' => (string) $code);
}

/**
 * Whether a value is an error value.
 *
 * @param mixed $value
 * @return bool
 */
function pg_office_formula_is_error($value)
{
    return is_array($value) && isset($value['error']);
}

/**
 * A formula's value from the values of its sheet's cells.
 *
 * The cells are [row => [column => value]] (row 1-based, column 0-based;
 * cells left out are empty), each value a number, a text, a logical value,
 * null (empty) or an error value [error => code]. A formula cell is given
 * with the value it was worked out to.
 *
 * The value is a number (float), or, for a formula that is one reference
 * (=A1), that cell's text or logical value as it is (an empty cell is 0), or
 * an error value: #VALUE! for a text that does not read as a number in
 * arithmetic, #DIV/0! for a division by zero or an AVERAGE of no numbers,
 * #NUM! for a result too large for a number, or the first error met in a
 * cell read.
 *
 * @param array $tree A tree from pg_office_formula_parse().
 * @param array $cells
 * @return float|string|bool|array
 */
function pg_office_formula_value($tree, $cells)
{
    $reference = pg_office_formula_ungroup($tree);

    if ($reference['type'] === 'cell') {
        $value = pg_office_formula_cell_value($cells, $reference['row'], $reference['column']);

        if ($value === null) {
            return 0.0;
        }

        return (is_int($value) || is_float($value)) ? pg_office_formula_finite((float) $value) : $value;
    }

    return pg_office_formula_evaluate($tree, $cells);
}

/**
 * A node without the brackets around it.
 *
 * @param array $node
 * @return array
 */
function pg_office_formula_ungroup($node)
{
    while ($node['type'] === 'group') {
        $node = $node['inner'];
    }

    return $node;
}

/**
 * One cell's value from the cells given, null when it is empty.
 *
 * @param array $cells
 * @param int $row
 * @param int $column
 * @return mixed
 */
function pg_office_formula_cell_value($cells, $row, $column)
{
    if (!isset($cells[$row]) || !is_array($cells[$row]) || !array_key_exists($column, $cells[$row])) {
        return null;
    }

    $value = $cells[$row][$column];

    return ($value === '') ? null : $value;
}

/**
 * A number, or #NUM! when it is too large for one.
 *
 * @param float $value
 * @return float|array
 */
function pg_office_formula_finite($value)
{
    if (!is_finite($value)) {
        return pg_office_formula_error('#NUM!');
    }

    // A negative zero is written by Excel as 0.
    return ($value == 0) ? 0.0 : $value;
}

/**
 * A value as a number for arithmetic, the way Excel converts it: empty is 0,
 * TRUE 1 and FALSE 0, a text that reads as a number ("12", " 1.5 ", "-3e2",
 * "50%") that number, any other text #VALUE!; an error stays the error.
 *
 * @param mixed $value
 * @return float|array
 */
function pg_office_formula_number($value)
{
    if (pg_office_formula_is_error($value)) {
        return $value;
    }

    if ($value === null) {
        return 0.0;
    }

    if (is_bool($value)) {
        return $value ? 1.0 : 0.0;
    }

    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }

    if (is_string($value) && preg_match('/^\s*([+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)\s*(%?)\s*$/', $value, $match)) {
        $number = (float) $match[1];

        return pg_office_formula_finite(($match[2] === '%') ? $number / 100 : $number);
    }

    return pg_office_formula_error('#VALUE!');
}

/**
 * A node's value as a number or an error.
 *
 * @param array $node
 * @param array $cells
 * @return float|array
 */
function pg_office_formula_evaluate($node, $cells)
{
    switch ($node['type']) {
        case 'number':
            return (float) $node['value'];
        case 'cell':
            return pg_office_formula_number(pg_office_formula_cell_value($cells, $node['row'], $node['column']));
        case 'group':
            return pg_office_formula_evaluate($node['inner'], $cells);
        case 'unary':
            $operand = pg_office_formula_evaluate($node['operand'], $cells);

            if (pg_office_formula_is_error($operand) || ($node['op'] === '+')) {
                return $operand;
            }

            return pg_office_formula_finite(-$operand);
        case 'binary':
            $left = pg_office_formula_evaluate($node['left'], $cells);

            if (pg_office_formula_is_error($left)) {
                return $left;
            }

            $right = pg_office_formula_evaluate($node['right'], $cells);

            if (pg_office_formula_is_error($right)) {
                return $right;
            }

            switch ($node['op']) {
                case '+':
                    return pg_office_formula_finite($left + $right);
                case '-':
                    return pg_office_formula_finite($left - $right);
                case '*':
                    return pg_office_formula_finite($left * $right);
            }

            if ($right == 0) {
                return pg_office_formula_error('#DIV/0!');
            }

            return pg_office_formula_finite($left / $right);
        case 'function':
            return pg_office_formula_call($node, $cells);
    }

    // A range outside a function's arguments is refused by the reader.
    return pg_office_formula_error('#VALUE!');
}

/**
 * A function's value. A reference argument (a cell or a range, bracketed or
 * not) gives its numbers only: text, logical values and empty cells are left
 * out. Any other argument is worked out and counts as one number. An error
 * met is the function's value, but COUNT counts past it.
 *
 * @param array $node
 * @param array $cells
 * @return float|array
 */
function pg_office_formula_call($node, $cells)
{
    $count = ($node['name'] === 'COUNT');
    $numbers = array();

    foreach ($node['arguments'] as $argument) {
        $argument = pg_office_formula_ungroup($argument);

        if (($argument['type'] === 'cell') || ($argument['type'] === 'range')) {
            $area = ($argument['type'] === 'cell')
                ? array($argument['row'], $argument['column'], $argument['row'], $argument['column'])
                : pg_office_formula_area($argument);

            foreach (pg_office_formula_area_values($cells, $area) as $value) {
                if (pg_office_formula_is_error($value)) {
                    if (!$count) {
                        return $value;
                    }
                } elseif (is_int($value) || is_float($value)) {
                    $numbers[] = (float) $value;
                }
            }

            continue;
        }

        $value = pg_office_formula_evaluate($argument, $cells);

        if (pg_office_formula_is_error($value)) {
            if (!$count) {
                return $value;
            }

            continue;
        }

        $numbers[] = $value;
    }

    switch ($node['name']) {
        case 'COUNT':
            return (float) count($numbers);
        case 'SUM':
            return pg_office_formula_finite(array_sum($numbers));
        case 'AVERAGE':
            if (!$numbers) {
                return pg_office_formula_error('#DIV/0!');
            }

            return pg_office_formula_finite(array_sum($numbers) / count($numbers));
        case 'MIN':
            return $numbers ? min($numbers) : 0.0;
        case 'MAX':
            return $numbers ? max($numbers) : 0.0;
    }

    return pg_office_formula_error('#VALUE!');
}

/**
 * The values of the cells given inside an area, row by row, left to right;
 * empty cells are left out. Only the cells given are visited, so a large
 * range costs no more than the sheet's cells.
 *
 * @param array $cells
 * @param array $area [first row, first column, last row, last column]
 * @return array
 */
function pg_office_formula_area_values($cells, $area)
{
    $values = array();
    $rows = array();

    foreach ($cells as $row => $columns) {
        if (is_int($row) && ($row >= $area[0]) && ($row <= $area[2]) && is_array($columns)) {
            $rows[] = $row;
        }
    }

    sort($rows);

    foreach ($rows as $row) {
        $columns = array();

        foreach ($cells[$row] as $column => $value) {
            if (is_int($column) && ($column >= $area[1]) && ($column <= $area[3]) && ($value !== null) && ($value !== '')) {
                $columns[$column] = $value;
            }
        }

        ksort($columns);

        foreach ($columns as $value) {
            $values[] = $value;
        }
    }

    return $values;
}
