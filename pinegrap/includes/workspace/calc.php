<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - sums that come out right. A figure in a conversation that
 * follows from other figures is written as a formula and worked out here,
 * so a table's total is the sum of its rows however it was typed, and
 * whoever wrote it - a person or Claude.
 *
 * Two places take formulas. In a table, a cell that starts with = is one:
 * columns are letters and rows numbers, the header being row 1 (=B2*C2,
 * =SUM(D2:D5)). A ```hesap block (```calc too) holds one value a line,
 * "Name = expression", and a later line may use an earlier name
 * (Total = Rent + Dues). Both know + - * / ^, brackets, a percentage (18%),
 * and SUM, AVERAGE, MIN, MAX, COUNT, ROUND(x; places), ABS, with their
 * Turkish names too (TOPLA, ORTALAMA, MİN, MAKS, ADET, YUVARLA, MUTLAK).
 * Arguments are split by ; (a comma between two digits is a decimal comma).
 *
 * The same code works out a table in a message, in a note and in the
 * spreadsheet screen, so the three can never disagree.
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
 * The most rows and columns a sheet is worked out for.
 */
define('WS_CALC_MAX_ROWS', 200);
define('WS_CALC_MAX_COLUMNS', 26);

/**
 * The error codes, in the words of the site's language (the ones Excel uses
 * in that language, where it has one).
 *
 * @param string $code div0 | ref | value | name | cycle | syntax
 * @return string
 */
function ws_calc_error_label($code)
{
    static $labels = null;

    if ($labels === null) {
        $labels = array(
            'div0'   => lang('#DIV/0!'),
            'ref'    => lang('#REF!'),
            'value'  => lang('#VALUE!'),
            'name'   => lang('#NAME?'),
            'cycle'  => lang('#CYCLE!'),
            'syntax' => lang('#ERROR!'),
        );
    }

    return $labels[$code] ?? $labels['syntax'];
}

/**
 * What each error means, for the tooltip.
 *
 * @param string $code
 * @return string
 */
function ws_calc_error_help($code)
{
    switch ($code) {
        case 'div0':
            return lang('Division by zero.');
        case 'ref':
            return lang('The formula points at a cell or a name that is not there.');
        case 'value':
            return lang('A cell the formula uses holds text, not a number.');
        case 'name':
            return lang('An unknown function.');
        case 'cycle':
            return lang('The formula leads back to itself.');
    }

    return lang('The formula could not be read.');
}

/**
 * Does the site write 1.234,5 (Turkish) rather than 1,234.5?
 *
 * @return bool
 */
function ws_calc_decimal_comma()
{
    static $comma = null;

    if ($comma === null) {
        $comma = (lang(array('info' => '')) === 'tr');
    }

    return $comma;
}

/**
 * A cell as a number, or null when it holds text. Takes the forms people
 * type: 1350, 1.350, 1.350,75, 450 ₺, $12.50, -3, 18%.
 *
 * @param string $text
 * @return float|null
 */
function ws_calc_number($text)
{
    $text = trim(str_replace(array("\xc2\xa0", ' '), '', strip_tags((string) $text)));

    if ($text === '') {
        return null;
    }

    // Emphasis around a figure (**1.590**) is not part of it.
    $text = trim($text, '*_~`');

    $percent = false;

    if (substr($text, -1) === '%') {
        $percent = true;
        $text = substr($text, 0, -1);
    } elseif (substr($text, 0, 1) === '%') {
        $percent = true;
        $text = substr($text, 1);
    }

    $text = preg_replace('/^(₺|TL|TRY|\$|USD|€|EUR|£|GBP)/iu', '', $text);
    $text = preg_replace('/(₺|TL|TRY|\$|USD|€|EUR|£|GBP)$/iu', '', $text);

    if (!preg_match('/^[+-]?[0-9][0-9.,]*$/', $text)) {
        return null;
    }

    $negative = (substr($text, 0, 1) === '-');
    $digits = ltrim($text, '+-');
    $dot = strrpos($digits, '.');
    $comma = strrpos($digits, ',');

    if (($dot !== false) && ($comma !== false)) {
        // Both: the one written last marks the decimals.
        $decimal = ($dot > $comma) ? '.' : ',';
    } elseif (($dot !== false) || ($comma !== false)) {
        $mark = ($dot !== false) ? '.' : ',';
        $parts = explode($mark, $digits);

        // One mark: a group of three digits after each is thousands, unless
        // the language writes decimals with that mark and there is only one.
        $grouped = (count($parts) > 1);

        for ($i = 1; $i < count($parts); $i++) {
            if (strlen($parts[$i]) !== 3) {
                $grouped = false;
            }
        }

        $local_decimal = ws_calc_decimal_comma() ? ',' : '.';

        if ($grouped && ((count($parts) > 2) || ($mark !== $local_decimal))) {
            $decimal = ($mark === '.') ? ',' : '.';
        } else {
            $decimal = $mark;
        }

        if (count($parts) > 2 && ($decimal === $mark)) {
            return null;
        }
    } else {
        $decimal = '.';
    }

    $thousands = ($decimal === '.') ? ',' : '.';
    $digits = str_replace($thousands, '', $digits);
    $digits = str_replace($decimal, '.', $digits);

    if (!is_numeric($digits)) {
        return null;
    }

    $value = (float) $digits;

    if ($negative) {
        $value = -$value;
    }

    return $percent ? $value / 100 : $value;
}

/**
 * A worked-out figure as the site's language writes it: whole numbers
 * without decimals, the others with two.
 *
 * @param float $value
 * @return string
 */
function ws_calc_format($value)
{
    $value = (float) $value;

    if (!is_finite($value)) {
        return ws_calc_error_label('value');
    }

    $rounded = round($value, 2);
    $places = (abs($rounded - round($rounded)) < 0.0000001) ? 0 : 2;

    if (abs($rounded) < 0.005) {
        $rounded = 0.0;
    }

    return ws_calc_decimal_comma()
        ? number_format($rounded, $places, ',', '.')
        : number_format($rounded, $places, '.', ',');
}

/**
 * Letters and digits as one ASCII word, for comparing function names written
 * with or without Turkish letters, in either case.
 *
 * @param string $name
 * @return string
 */
function ws_calc_fold($name)
{
    $name = strtr((string) $name, array(
        'ç' => 'C', 'Ç' => 'C', 'ğ' => 'G', 'Ğ' => 'G', 'ı' => 'I', 'İ' => 'I', 'i' => 'I',
        'ö' => 'O', 'Ö' => 'O', 'ş' => 'S', 'Ş' => 'S', 'ü' => 'U', 'Ü' => 'U',
    ));

    return strtoupper($name);
}

/**
 * The functions a formula may call, by every name they answer to.
 *
 * @return array folded name => canonical name
 */
function ws_calc_functions()
{
    return array(
        'SUM' => 'SUM', 'TOPLA' => 'SUM',
        'AVERAGE' => 'AVERAGE', 'AVG' => 'AVERAGE', 'ORTALAMA' => 'AVERAGE',
        'MIN' => 'MIN', 'ENKUCUK' => 'MIN',
        'MAX' => 'MAX', 'MAKS' => 'MAX', 'ENBUYUK' => 'MAX',
        'COUNT' => 'COUNT', 'ADET' => 'COUNT', 'SAY' => 'COUNT', 'BAG_DEG_SAY' => 'COUNT',
        'ROUND' => 'ROUND', 'YUVARLA' => 'ROUND',
        'ABS' => 'ABS', 'MUTLAK' => 'ABS',
    );
}

/**
 * A column's number (A = 0) from its letters, or -1.
 *
 * @param string $letters
 * @return int
 */
function ws_calc_column_index($letters)
{
    $letters = strtoupper((string) $letters);
    $index = 0;

    if (!preg_match('/^[A-Z]{1,2}$/', $letters)) {
        return -1;
    }

    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

/**
 * A column's letters from its number (0 = A).
 *
 * @param int $index
 * @return string
 */
function ws_calc_column_letters($index)
{
    $index = (int) $index + 1;
    $letters = '';

    while ($index > 0) {
        $rest = ($index - 1) % 26;
        $letters = chr(65 + $rest) . $letters;
        $index = (int) (($index - $rest - 1) / 26);
    }

    return $letters;
}

/**
 * Splits a formula into tokens.
 *
 * @param string $formula without the leading =
 * @return array[]|string the tokens, or the error code
 */
function ws_calc_tokens($formula)
{
    $formula = (string) $formula;
    $length = strlen($formula);
    $tokens = array();
    $i = 0;

    // Signs people type for multiplying and dividing, and a minus that is a dash.
    $formula = str_replace(array('×', '÷', '−', '–'), array('*', '/', '-', '-'), $formula);
    $length = strlen($formula);

    while ($i < $length) {
        $char = $formula[$i];

        if (ctype_space($char)) {
            $i++;
            continue;
        }

        // A name from a hesap block, put in place by the block.
        if (($char === '@') && preg_match('/\G@L([0-9]+)/', $formula, $match, 0, $i)) {
            $tokens[] = array('var', (int) $match[1]);
            $i += strlen($match[0]);
            continue;
        }

        if (ctype_digit($char) || (($char === '.') && ($i + 1 < $length) && ctype_digit($formula[$i + 1]))) {
            preg_match('/\G[0-9]*(?:[.,][0-9]+)?/', $formula, $match, 0, $i);
            $tokens[] = array('num', (float) str_replace(',', '.', $match[0]));
            $i += strlen($match[0]);
            continue;
        }

        if (preg_match('/\G\$?([A-Za-z]{1,2})\$?([0-9]{1,4})(?::\$?([A-Za-z]{1,2})\$?([0-9]{1,4}))?(?![A-Za-z0-9_(])/', $formula, $match, 0, $i)) {
            $from = array(ws_calc_column_index($match[1]), (int) $match[2] - 1);

            if (!empty($match[3])) {
                $tokens[] = array('range', $from, array(ws_calc_column_index($match[3]), (int) $match[4] - 1));
            } else {
                $tokens[] = array('ref', $from);
            }

            $i += strlen($match[0]);
            continue;
        }

        if (preg_match('/\G([\p{L}_][\p{L}\p{N}_]*)\s*\(/u', $formula, $match, 0, $i)) {
            $tokens[] = array('func', ws_calc_fold($match[1]));
            $tokens[] = array('(');
            $i += strlen($match[0]);
            continue;
        }

        if (strpos('+-*/^%();', $char) !== false) {
            $tokens[] = array($char);
            $i++;
            continue;
        }

        // A comma that is not a decimal one separates arguments.
        if ($char === ',') {
            $tokens[] = array(';');
            $i++;
            continue;
        }

        // A word that is not a function: a name nobody gave.
        if (preg_match('/\G[\p{L}_]/u', $formula, $match, 0, $i)) {
            return 'ref';
        }

        return 'syntax';
    }

    return $tokens;
}

/**
 * Works out one formula.
 *
 * @param string   $formula  without the leading =
 * @param callable $resolve  function (array $token) returning a number, an
 *                           array of numbers (a range), or an error code string
 * @return array ok (bool), value (float|null), error (code|'')
 */
function ws_calc_evaluate($formula, $resolve)
{
    $tokens = ws_calc_tokens($formula);

    if (!is_array($tokens)) {
        return array('ok' => false, 'value' => null, 'error' => $tokens);
    }

    if (empty($tokens)) {
        return array('ok' => false, 'value' => null, 'error' => 'syntax');
    }

    $position = 0;
    $functions = ws_calc_functions();

    $peek = function () use (&$tokens, &$position) {
        return $tokens[$position][0] ?? null;
    };

    // Recursive descent: expression > term > power > unary > postfix > primary.
    // Each answers a number, an array (a range, inside a function) or an
    // error code string, which travels up unchanged.
    $expression = null;
    $term = null;
    $power = null;
    $unary = null;
    $postfix = null;
    $primary = null;

    $number = function ($value) {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return (count($value) === 1) ? (float) reset($value) : 'value';
        }

        return (float) $value;
    };

    $expression = function () use (&$term, &$peek, &$position, $number) {
        $left = $term();

        while (in_array($peek(), array('+', '-'), true)) {
            $op = $peek();
            $position++;
            $right = $term();
            $left = $number($left);
            $right = $number($right);

            if (is_string($left)) {
                return $left;
            }

            if (is_string($right)) {
                return $right;
            }

            $left = ($op === '+') ? $left + $right : $left - $right;
        }

        return $left;
    };

    $term = function () use (&$power, &$peek, &$position, $number) {
        $left = $power();

        while (in_array($peek(), array('*', '/'), true)) {
            $op = $peek();
            $position++;
            $right = $power();
            $left = $number($left);
            $right = $number($right);

            if (is_string($left)) {
                return $left;
            }

            if (is_string($right)) {
                return $right;
            }

            if ($op === '/') {
                if (abs($right) < 1e-12) {
                    return 'div0';
                }

                $left = $left / $right;
            } else {
                $left = $left * $right;
            }
        }

        return $left;
    };

    $power = function () use (&$unary, &$power, &$peek, &$position, $number) {
        $base = $unary();

        if ($peek() === '^') {
            $position++;
            $exponent = $number($power());
            $base = $number($base);

            if (is_string($base)) {
                return $base;
            }

            if (is_string($exponent)) {
                return $exponent;
            }

            $result = pow($base, $exponent);

            return is_finite($result) ? $result : 'value';
        }

        return $base;
    };

    $unary = function () use (&$unary, &$postfix, &$peek, &$position, $number) {
        if ($peek() === '-') {
            $position++;
            $value = $number($unary());

            return is_string($value) ? $value : -$value;
        }

        if ($peek() === '+') {
            $position++;

            return $unary();
        }

        return $postfix();
    };

    $postfix = function () use (&$primary, &$peek, &$position, $number) {
        $value = $primary();

        while ($peek() === '%') {
            $position++;
            $value = $number($value);

            if (is_string($value)) {
                return $value;
            }

            $value = $value / 100;
        }

        return $value;
    };

    $primary = function () use (&$expression, &$tokens, &$position, &$peek, $resolve, $functions, $number) {
        $token = $tokens[$position] ?? null;

        if ($token === null) {
            return 'syntax';
        }

        $position++;

        switch ($token[0]) {
            case 'num':
                return $token[1];

            case 'ref':
            case 'range':
            case 'var':
                return $resolve($token);

            case '(':
                $value = $expression();

                if ($peek() !== ')') {
                    return 'syntax';
                }

                $position++;

                return $value;

            case 'func':
                if (!isset($functions[$token[1]])) {
                    return 'name';
                }

                $name = $functions[$token[1]];
                $position++; // the opening bracket
                $args = array();

                if ($peek() !== ')') {
                    while (true) {
                        $value = $expression();

                        if (is_string($value)) {
                            return $value;
                        }

                        $args[] = $value;

                        if ($peek() === ';') {
                            $position++;
                            continue;
                        }

                        break;
                    }
                }

                if ($peek() !== ')') {
                    return 'syntax';
                }

                $position++;

                return ws_calc_call($name, $args);
        }

        return 'syntax';
    };

    $value = $expression();

    if (!is_string($value) && ($position < count($tokens))) {
        $value = 'syntax';
    }

    $value = $number($value);

    if (is_string($value)) {
        return array('ok' => false, 'value' => null, 'error' => $value);
    }

    if (!is_finite($value)) {
        return array('ok' => false, 'value' => null, 'error' => 'value');
    }

    return array('ok' => true, 'value' => $value, 'error' => '');
}

/**
 * Runs a function on its arguments (numbers, or arrays of numbers from ranges,
 * where text cells are already left out).
 *
 * @param string $name canonical
 * @param array  $args
 * @return float|string the value or an error code
 */
function ws_calc_call($name, $args)
{
    $flat = array();

    foreach ($args as $arg) {
        if (is_array($arg)) {
            foreach ($arg as $value) {
                $flat[] = (float) $value;
            }
        } else {
            $flat[] = (float) $arg;
        }
    }

    switch ($name) {
        case 'SUM':
            return array_sum($flat);

        case 'AVERAGE':
            return empty($flat) ? 'div0' : array_sum($flat) / count($flat);

        case 'MIN':
            return empty($flat) ? 0.0 : min($flat);

        case 'MAX':
            return empty($flat) ? 0.0 : max($flat);

        case 'COUNT':
            return (float) count($flat);

        case 'ROUND':
            if ((count($args) < 1) || is_array($args[0])) {
                return 'value';
            }

            $places = isset($args[1]) && !is_array($args[1]) ? (int) $args[1] : 0;

            return round((float) $args[0], max(-6, min(6, $places)));

        case 'ABS':
            if ((count($args) !== 1) || is_array($args[0])) {
                return 'value';
            }

            return abs((float) $args[0]);
    }

    return 'name';
}

/**
 * Works out a table: every cell starting with = becomes its value. Row 1 is
 * the first row given (a table's header).
 *
 * @param array[] $rows cells as written, row by row
 * @return array[] per cell: formula (bool), value (float|null), display
 *                 (string), error (code|''), source (as written)
 */
function ws_calc_grid($rows)
{
    $rows = array_slice(array_values((array) $rows), 0, WS_CALC_MAX_ROWS);
    $grid = array();

    foreach ($rows as $r => $row) {
        $grid[$r] = array_slice(array_values((array) $row), 0, WS_CALC_MAX_COLUMNS);
    }

    $state = array();   // "r:c" => done | busy
    $result = array();  // "r:c" => array(value|null, error)

    $cell = null;

    $cell = function ($r, $c) use (&$cell, &$grid, &$state, &$result) {
        $key = $r . ':' . $c;

        if (isset($result[$key])) {
            return $result[$key];
        }

        if (!isset($grid[$r]) || !array_key_exists($c, $grid[$r])) {
            return $result[$key] = array(null, '');
        }

        $source = trim((string) $grid[$r][$c]);

        if (substr($source, 0, 1) !== '=') {
            return $result[$key] = array(ws_calc_number($source), '');
        }

        if (($state[$key] ?? '') === 'busy') {
            return array(null, 'cycle');
        }

        $state[$key] = 'busy';

        $outcome = ws_calc_evaluate(substr($source, 1), function ($token) use (&$cell, &$grid, $r, $c) {
            if ($token[0] === 'ref') {
                list($col, $row) = $token[1];

                if (($col < 0) || ($row < 0) || ($row >= WS_CALC_MAX_ROWS) || ($col >= WS_CALC_MAX_COLUMNS)) {
                    return 'ref';
                }

                list($value, $error) = $cell($row, $col);

                if ($error !== '') {
                    return $error;
                }

                // An empty cell counts as nought, text as a mistake.
                if ($value === null) {
                    return (trim((string) ($grid[$row][$col] ?? '')) === '') ? 0.0 : 'value';
                }

                return $value;
            }

            if ($token[0] === 'range') {
                list($col_a, $row_a) = $token[1];
                list($col_b, $row_b) = $token[2];

                if (min($col_a, $col_b, $row_a, $row_b) < 0) {
                    return 'ref';
                }

                $values = array();

                for ($row = min($row_a, $row_b); $row <= min(WS_CALC_MAX_ROWS - 1, max($row_a, $row_b)); $row++) {
                    for ($col = min($col_a, $col_b); $col <= min(WS_CALC_MAX_COLUMNS - 1, max($col_a, $col_b)); $col++) {
                        // A range that holds the formula itself leaves its cell out.
                        if (($row === $r) && ($col === $c)) {
                            continue;
                        }

                        list($value, $error) = $cell($row, $col);

                        if ($error !== '') {
                            return $error;
                        }

                        if ($value !== null) {
                            $values[] = $value;
                        }
                    }
                }

                return $values;
            }

            return 'ref';
        });

        $state[$key] = 'done';

        return $result[$key] = $outcome['ok'] ? array($outcome['value'], '') : array(null, $outcome['error']);
    };

    $out = array();

    foreach ($grid as $r => $row) {
        foreach ($row as $c => $source) {
            $source = (string) $source;
            $formula = (substr(trim($source), 0, 1) === '=');

            if (!$formula) {
                $out[$r][$c] = array('formula' => false, 'value' => ws_calc_number($source), 'display' => $source, 'error' => '', 'source' => $source);
                continue;
            }

            list($value, $error) = $cell($r, $c);

            $out[$r][$c] = array(
                'formula' => true,
                'value'   => $value,
                'display' => ($error !== '') ? ws_calc_error_label($error) : ws_calc_format($value),
                'error'   => $error,
                'source'  => trim($source),
            );
        }
    }

    return $out;
}

/**
 * Does any cell of these rows hold a formula?
 *
 * @param array[] $rows
 * @return bool
 */
function ws_calc_has_formula($rows)
{
    foreach ((array) $rows as $row) {
        foreach ((array) $row as $source) {
            if (substr(trim((string) $source), 0, 1) === '=') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Is this code fence a hesap block?
 *
 * @param string $language
 * @return bool
 */
function ws_calc_block_language($language)
{
    return in_array(ws_calc_fold($language), array('HESAP', 'CALC', 'MATH'), true);
}

/**
 * Works out a hesap block: one value a line, "Name = expression" or a bare
 * expression; a name may be used by the lines after it. A line starting with
 * # or // is a remark.
 *
 * @param string[] $lines
 * @return array[] per line: kind (value | remark | blank), label, source,
 *                 value, display, error, derived (uses another line)
 */
function ws_calc_block($lines)
{
    $names = array();   // folded label => index
    $labels = array();  // index => label as written
    $values = array();  // index => value|null
    $out = array();

    foreach (array_slice(array_values((array) $lines), 0, WS_CALC_MAX_ROWS) as $index => $line) {
        $line = trim((string) $line);

        if ($line === '') {
            $out[] = array('kind' => 'blank');
            continue;
        }

        if ((substr($line, 0, 1) === '#') || (substr($line, 0, 2) === '//')) {
            $out[] = array('kind' => 'remark', 'label' => trim(ltrim($line, '#/')));
            continue;
        }

        $label = '';
        $source = $line;
        $at = strpos($line, '=');

        if ($at !== false) {
            $label = trim(substr($line, 0, $at));
            $source = trim(substr($line, $at + 1));
        }

        // A bare figure with a name ("Kira = 15.000") is read the way a cell is.
        $plain = ws_calc_number($source);

        if ($plain !== null) {
            $result = array('ok' => true, 'value' => $plain, 'error' => '');
            $derived = false;
        } else {
            // The names of the lines above, longest first, become references.
            $expression = $source;
            $used = false;

            $known = array_keys($names);
            usort($known, function ($a, $b) {
                return mb_strlen($b) - mb_strlen($a);
            });

            foreach ($known as $folded) {
                $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($labels[$names[$folded]], '/') . '(?![\p{L}\p{N}_])/iu';
                $replaced = preg_replace($pattern, '@L' . $names[$folded], $expression);

                if (($replaced !== null) && ($replaced !== $expression)) {
                    $expression = $replaced;
                    $used = true;
                }
            }

            $result = ws_calc_evaluate($expression, function ($token) use (&$values) {
                if ($token[0] !== 'var') {
                    return 'ref';
                }

                $value = $values[$token[1]] ?? null;

                return ($value === null) ? 'ref' : $value;
            });

            $derived = $used;
        }

        $values[$index] = $result['ok'] ? $result['value'] : null;

        if ($label !== '') {
            $folded = mb_strtolower($label);
            $names[$folded] = $index;
            $labels[$index] = $label;
        }

        $out[] = array(
            'kind'    => 'value',
            'label'   => $label,
            'source'  => $source,
            'value'   => $values[$index],
            'display' => $result['ok'] ? ws_calc_format($result['value']) : ws_calc_error_label($result['error']),
            'error'   => $result['error'],
            'derived' => $derived,
        );
    }

    return $out;
}

/**
 * A worked-out cell as HTML: the value, the formula in its title.
 *
 * @param array $cell from ws_calc_grid()
 * @return string
 */
function ws_calc_cell_html($cell)
{
    $title = $cell['source'] . (($cell['error'] !== '') ? ' · ' . ws_calc_error_help($cell['error']) : '');

    return '<span class="ws-calc' . (($cell['error'] !== '') ? ' ws-calc-error' : '') . '" title="' . h($title) . '" data-ws-formula="' . h($cell['source']) . '">'
        . h($cell['display']) . '</span>';
}

/**
 * A hesap block as HTML.
 *
 * @param string[] $lines
 * @return string
 */
function ws_calc_block_html($lines)
{
    $rows = '';

    foreach (ws_calc_block($lines) as $line) {
        if ($line['kind'] === 'blank') {
            continue;
        }

        if ($line['kind'] === 'remark') {
            $rows .= '<tr class="ws-calc-remark"><td colspan="2">' . h($line['label']) . '</td></tr>';
            continue;
        }

        $title = $line['source'] . (($line['error'] !== '') ? ' · ' . ws_calc_error_help($line['error']) : '');

        $rows .= '<tr' . ($line['derived'] ? ' class="ws-calc-derived"' : '') . '>'
            . '<th scope="row">' . h($line['label']) . '</th>'
            . '<td class="text-end"><span class="ws-calc' . (($line['error'] !== '') ? ' ws-calc-error' : '') . '" title="' . h($title) . '" data-ws-formula="' . h($line['source']) . '">' . h($line['display']) . '</span></td>'
            . '</tr>';
    }

    return '<div class="ws-calc-block"><div class="ws-calc-block-head"><i class="bi bi-calculator" aria-hidden="true"></i> ' . h(lang('Calculation')) . '</div>'
        . '<table class="table table-sm ws-table ws-calc-table"><tbody>' . $rows . '</tbody></table></div>';
}

/**
 * A stored text with its formulas replaced by what they come to, for plain
 * text readers: an e-mail line, a notification, what Claude reads of a
 * channel. A formula cell reads "1.590 (=SUM(D2:D3))".
 *
 * @param string $body
 * @return string
 */
function ws_calc_plain($body)
{
    $body = (string) $body;

    if (strpos($body, '=') === false) {
        return $body;
    }

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $count = count($lines);
    $out = array();

    for ($i = 0; $i < $count; $i++) {
        $language = ws_code_fence_open($lines[$i]);

        if (($language !== null) && ws_calc_block_language($language)) {
            $block = array();
            $j = $i + 1;

            while (($j < $count) && !ws_code_fence_close($lines[$j])) {
                $block[] = $lines[$j];
                $j++;
            }

            foreach (ws_calc_block($block) as $line) {
                if ($line['kind'] === 'value') {
                    $shown = (ws_calc_number($line['source']) !== null) ? '' : ' (' . $line['source'] . ')';
                    $out[] = (($line['label'] !== '') ? $line['label'] . ' = ' : '') . $line['display'] . $shown;
                } elseif ($line['kind'] === 'remark') {
                    $out[] = $line['label'];
                }
            }

            $i = $j;
            continue;
        }

        $is_row = preg_match('/^\s*\|.*\|\s*$/', $lines[$i]);
        $alignments = (($i + 1) < $count) ? ws_table_alignments($lines[$i + 1]) : null;

        if ($is_row && ($alignments !== null)) {
            $table = array($i);
            $j = $i + 2;

            while (($j < $count) && preg_match('/^\s*\|.*\|\s*$/', $lines[$j])) {
                $table[] = $j;
                $j++;
            }

            $rows = array();

            foreach ($table as $index) {
                $rows[] = ws_table_cells($lines[$index]);
            }

            if (ws_calc_has_formula($rows)) {
                $grid = ws_calc_grid($rows);

                foreach ($table as $r => $index) {
                    $cells = array();

                    foreach ($rows[$r] as $c => $source) {
                        $cells[] = !empty($grid[$r][$c]['formula']) ? $grid[$r][$c]['display'] . ' (' . $grid[$r][$c]['source'] . ')' : $source;
                    }

                    $lines[$index] = '| ' . implode(' | ', $cells) . ' |';
                }
            }

            foreach (range($i, $j - 1) as $index) {
                $out[] = $lines[$index];
            }

            $i = $j - 1;
            continue;
        }

        // A line that ends with "=" reads with what it comes to.
        $inline = ws_calc_inline($lines[$i]);
        $out[] = $lines[$i] . (($inline !== null) ? ' ' . $inline['display'] : '');
    }

    return implode("\n", $out);
}

/**
 * A line that ends with "=": the figures in it worked out, the words around
 * them left out ("Rent 15.000 + dues 750 =" comes to 15.750). x and × multiply
 * and : and ÷ divide between figures; a figure is read the way a cell is
 * (15.000 is fifteen thousand in Turkish). An "=" earlier in the line starts
 * what is worked out ("Total = 3 * 450 =").
 *
 * @param string $line
 * @return array|null source (what was worked out), value, display, error
 */
function ws_calc_inline($line)
{
    $line = rtrim((string) $line);

    if ((substr($line, -1) !== '=') || (substr($line, -2) === '==') || (strpos($line, '|') === 0)) {
        return null;
    }

    $text = substr($line, 0, -1);

    // Tags, links and emphasis marks are not figures.
    $text = preg_replace('/<[@#][a-z_]+:[0-9]+>/', ' ', $text);
    $text = preg_replace('#\[([^\]\n]*)\]\([^)\n]*\)#', ' ', $text);
    $text = str_replace(array('**', '~~', '`'), ' ', $text);

    $at = strrpos($text, '=');

    if ($at !== false) {
        $text = substr($text, $at + 1);
    }

    if (!preg_match_all('/[0-9][0-9.,]*%?|(?<=[0-9\s)])[xX:](?=\s*[0-9(])|[+\-*\/^()×÷−–]|\p{L}+|\S/u', $text, $match)) {
        return null;
    }

    $parts = array();
    $figures = 0;
    $operators = 0;

    foreach ($match[0] as $piece) {
        if (preg_match('/^[0-9]/', $piece)) {
            $value = ws_calc_number($piece);

            if ($value === null) {
                return null;
            }

            $parts[] = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
            $figures++;
        } elseif (in_array($piece, array('x', 'X', '×'), true)) {
            $parts[] = '*';
            $operators++;
        } elseif (in_array($piece, array(':', '÷'), true)) {
            $parts[] = '/';
            $operators++;
        } elseif (in_array($piece, array('−', '–'), true)) {
            $parts[] = '-';
            $operators++;
        } elseif (in_array($piece, array('+', '-', '*', '/', '^'), true)) {
            $parts[] = $piece;
            $operators++;
        } elseif (($piece === '(') || ($piece === ')')) {
            $parts[] = $piece;
        }
    }

    if (($figures < 2) || ($operators < 1)) {
        return null;
    }

    // Signs left at either end by the words taken out.
    $expression = trim(preg_replace('/^[\s*\/^+]+|[\s*\/^+\-]+$/', '', implode(' ', $parts)));

    if ($expression === '') {
        return null;
    }

    $result = ws_calc_evaluate($expression, function () {
        return 'ref';
    });

    return array(
        'source'  => $expression,
        'value'   => $result['ok'] ? $result['value'] : null,
        'display' => $result['ok'] ? ws_calc_format($result['value']) : ws_calc_error_label($result['error']),
        'error'   => $result['ok'] ? '' : (string) $result['error'],
    );
}

/**
 * The results of a set of lines, for a writing box that shows them as they
 * are typed.
 *
 * @param string[] $lines
 * @return array[] index => display, error (help), source; null for a line with nothing to work out
 */
function ws_calc_inline_lines($lines)
{
    $out = array();

    foreach (array_slice(array_values((array) $lines), 0, 500) as $index => $line) {
        $inline = is_string($line) ? ws_calc_inline(mb_substr($line, 0, 1000)) : null;

        $out[$index] = ($inline === null) ? null : array(
            'display' => $inline['display'],
            'error'   => ($inline['error'] !== '') ? ws_calc_error_help($inline['error']) : '',
            'source'  => $inline['source'],
        );
    }

    return $out;
}

/**
 * The HTML of a line's result, after its "=".
 *
 * @param array $inline ws_calc_inline()
 * @return string
 */
function ws_calc_inline_html($inline)
{
    $title = $inline['source'] . (($inline['error'] !== '') ? ' · ' . ws_calc_error_help($inline['error']) : '');

    return ' <span class="ws-calc ws-calc-inline' . (($inline['error'] !== '') ? ' ws-calc-error' : '') . '" title="' . h($title) . '">' . h($inline['display']) . '</span>';
}
