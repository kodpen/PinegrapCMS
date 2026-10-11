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
 * comparisons (< > <= >= = <>) and the functions of ws_calc_functions(),
 * by their English or Turkish names. Arguments are split by ; (a comma
 * between two digits is a decimal comma).
 *
 * A hesap block knows a little more than one value a line:
 *   Expenses:            a heading opens a group; the group's name is then
 *   Rent = 15000         the SUM of its lines ("Scores: AVERAGE" takes
 *   Dues = 750           another function), shown as a subtotal row. The
 *                        group ends at a blank line or the next heading.
 *   _Rate = 34,20        a line (or a heading) starting with _ is worked
 *                        out and may be used, but is not shown.
 *   # Note               a remark; a blank line shows as a gap.
 * The fence may carry settings: ```hesap ondalık=2 birim=₺ (decimals=2
 * unit=₺) fixes the decimal places and the unit the values are shown with.
 *
 * A name given in a hesap block is known to every table of the same message
 * or note (=B2*Rate) and to the hesap blocks after it. A reader may try other
 * figures for a block's plain values without changing what was written
 * (ws_calc_live()).
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
 * Where the names a block inherits are kept among its own: far above any
 * line number, so the two can never meet.
 */
define('WS_CALC_SEED_SLOT', 100000);

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
 * without decimals, the others with two - or always with the places asked
 * for - and with a unit when one is given.
 *
 * @param float    $value
 * @param int|null $decimals null for the default above, 0-6 otherwise
 * @param string   $unit     shown before the figure for $, €, £, after it otherwise
 * @return string
 */
function ws_calc_format($value, $decimals = null, $unit = '')
{
    $value = (float) $value;

    if (!is_finite($value)) {
        return ws_calc_error_label('value');
    }

    if ($decimals === null) {
        $rounded = round($value, 2);
        $places = (abs($rounded - round($rounded)) < 0.0000001) ? 0 : 2;
    } else {
        $places = max(0, min(6, (int) $decimals));
        $rounded = round($value, $places);
    }

    // No "-0" for a figure that rounds away.
    if (abs($rounded) < 0.5 * pow(10, -$places)) {
        $rounded = 0.0;
    }

    $text = ws_calc_decimal_comma()
        ? number_format($rounded, $places, ',', '.')
        : number_format($rounded, $places, '.', ',');

    $unit = (string) $unit;

    if ($unit === '') {
        return $text;
    }

    if (in_array($unit, array('$', '€', '£', '¥'), true)) {
        return ($rounded < 0) ? '-' . $unit . substr($text, 1) : $unit . $text;
    }

    return $text . "\xc2\xa0" . $unit;
}

/**
 * A share as the site's language writes it: %20 in Turkish, 20% elsewhere,
 * with as many decimals as it has (up to four).
 *
 * @param float $value the share (0.2 for 20%)
 * @return string
 */
function ws_calc_format_percent($value)
{
    $value = round((float) $value * 100, 4);

    if (!is_finite($value)) {
        return ws_calc_error_label('value');
    }

    if ($value == 0) {
        $value = 0.0;
    }

    $text = rtrim(rtrim(number_format($value, 4, ws_calc_decimal_comma() ? ',' : '.', ws_calc_decimal_comma() ? '.' : ','), '0'), '.,');

    return ws_calc_decimal_comma() ? '%' . $text : $text . '%';
}

/**
 * The settings written after a hesap block's language, "ondalık=2 birim=₺"
 * (or decimals=2 unit=₺).
 *
 * @param string $info what follows the language on the opening fence
 * @return array decimals (int|null), unit (string)
 */
function ws_calc_block_options($info)
{
    $options = array('decimals' => null, 'unit' => '');
    $info = preg_replace('/\s*([=:])\s*/u', '$1', trim((string) $info));

    if ($info === '') {
        return $options;
    }

    foreach (preg_split('/[\s;,]+/u', $info) as $pair) {
        if (!preg_match('/^([\p{L}_]+)[=:](.+)$/u', $pair, $match)) {
            continue;
        }

        $key = ws_calc_fold($match[1]);
        $value = trim($match[2]);

        if (in_array($key, array('ONDALIK', 'DECIMALS', 'DECIMAL', 'PLACES'), true)) {
            if (ctype_digit($value)) {
                $options['decimals'] = min(6, (int) $value);
            }
        } elseif (in_array($key, array('BIRIM', 'UNIT'), true)) {
            $options['unit'] = mb_substr(preg_replace('/[\x00-\x1F\x7F<>"\'`&]/u', '', $value), 0, 8);
        }
    }

    return $options;
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
        'ROUNDUP' => 'ROUNDUP', 'YUKARIYUVARLA' => 'ROUNDUP',
        'ROUNDDOWN' => 'ROUNDDOWN', 'ASAGIYUVARLA' => 'ROUNDDOWN',
        'ABS' => 'ABS', 'MUTLAK' => 'ABS',
        'SQRT' => 'SQRT', 'KAREKOK' => 'SQRT',
        'POWER' => 'POWER', 'KUVVET' => 'POWER',
        'MOD' => 'MOD',
        'PRODUCT' => 'PRODUCT', 'CARPIM' => 'PRODUCT',
        'MEDIAN' => 'MEDIAN', 'ORTANCA' => 'MEDIAN',
        'IF' => 'IF', 'EGER' => 'IF',
        'IFERROR' => 'IFERROR', 'EGERHATA' => 'IFERROR',
        'AND' => 'AND', 'VE' => 'AND',
        'OR' => 'OR', 'YADA' => 'OR',
        'NOT' => 'NOT', 'DEGIL' => 'NOT',
        'PMT' => 'PMT', 'DEVRESEL_ODEME' => 'PMT',
        'INSTALLMENT' => 'INSTALLMENT', 'TAKSIT' => 'INSTALLMENT',
    );
}

/**
 * The functions a group heading may take ("Scores: AVERAGE").
 *
 * @return string[] canonical names
 */
function ws_calc_group_functions()
{
    return array('SUM', 'AVERAGE', 'MIN', 'MAX', 'COUNT', 'PRODUCT', 'MEDIAN');
}

/**
 * What a group's function is called beside its subtotal.
 *
 * @param string $name canonical
 * @return string
 */
function ws_calc_group_function_label($name)
{
    switch ($name) {
        case 'AVERAGE':
            return lang('Average');
        case 'MIN':
            return lang('Minimum');
        case 'MAX':
            return lang('Maximum');
        case 'COUNT':
            return lang('Count');
        case 'PRODUCT':
            return lang('Multiplied together');
        case 'MEDIAN':
            return lang('Median');
    }

    return lang('Total');
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

    // Signs people type for multiplying and dividing, a minus that is a dash,
    // and the comparison signs of a keyboard that has them.
    $formula = str_replace(array('×', '÷', '−', '–', '≤', '≥', '≠'), array('*', '/', '-', '-', '<=', '>=', '<>'), $formula);
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

        // A comparison comes to 1 when it holds and 0 when it does not.
        if (strpos('<>=!', $char) !== false) {
            $pair = substr($formula, $i, 2);

            if (in_array($pair, array('<=', '>=', '<>', '!=', '=='), true)) {
                $tokens[] = array('cmp', ($pair === '!=') ? '<>' : (($pair === '==') ? '=' : $pair));
                $i += 2;
                continue;
            }

            if ($char === '!') {
                return 'syntax';
            }

            $tokens[] = array('cmp', $char);
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
 * A value as one number: a range of one cell is that cell, a longer range is
 * a mistake, an error code stays what it is.
 *
 * @param float|array|string $value
 * @return float|string
 */
function ws_calc_scalar($value)
{
    if (is_string($value)) {
        return $value;
    }

    if (is_array($value)) {
        return (count($value) === 1) ? (float) reset($value) : 'value';
    }

    return (float) $value;
}

/**
 * Works out one formula.
 *
 * A mistake inside the formula (a division by zero, a reference to nothing)
 * travels up as its error code while the rest of the formula is still read,
 * so IF() and IFERROR() can step around it; a formula that cannot be read
 * at all is a syntax error.
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
    $broken = false;
    $functions = ws_calc_functions();

    $peek = function () use (&$tokens, &$position) {
        return $tokens[$position][0] ?? null;
    };

    // Two operands into one: an error in either is the answer.
    $apply = function ($left, $right, $operate) {
        $left = ws_calc_scalar($left);
        $right = ws_calc_scalar($right);

        if (is_string($left)) {
            return $left;
        }

        if (is_string($right)) {
            return $right;
        }

        return $operate($left, $right);
    };

    // Recursive descent: compare > expression > term > power > unary >
    // postfix > primary. Each answers a number, an array (a range, inside a
    // function) or an error code string.
    $compare = null;
    $expression = null;
    $term = null;
    $power = null;
    $unary = null;
    $postfix = null;
    $primary = null;

    $compare = function () use (&$expression, &$peek, &$position, &$tokens, $apply) {
        $left = $expression();

        while ($peek() === 'cmp') {
            $op = $tokens[$position][1];
            $position++;
            $right = $expression();

            $left = $apply($left, $right, function ($a, $b) use ($op) {
                // Sums of decimals are not exact; figures this close are equal.
                $equal = abs($a - $b) <= 1e-9 * max(1.0, abs($a), abs($b));

                switch ($op) {
                    case '=':
                        $holds = $equal;
                        break;
                    case '<>':
                        $holds = !$equal;
                        break;
                    case '<':
                        $holds = !$equal && ($a < $b);
                        break;
                    case '>':
                        $holds = !$equal && ($a > $b);
                        break;
                    case '<=':
                        $holds = $equal || ($a < $b);
                        break;
                    default:
                        $holds = $equal || ($a > $b);
                }

                return $holds ? 1.0 : 0.0;
            });
        }

        return $left;
    };

    $expression = function () use (&$term, &$peek, &$position, $apply) {
        $left = $term();

        while (in_array($peek(), array('+', '-'), true)) {
            $op = $peek();
            $position++;
            $right = $term();

            $left = $apply($left, $right, function ($a, $b) use ($op) {
                return ($op === '+') ? $a + $b : $a - $b;
            });
        }

        return $left;
    };

    $term = function () use (&$power, &$peek, &$position, $apply) {
        $left = $power();

        while (in_array($peek(), array('*', '/'), true)) {
            $op = $peek();
            $position++;
            $right = $power();

            $left = $apply($left, $right, function ($a, $b) use ($op) {
                if ($op === '*') {
                    return $a * $b;
                }

                return (abs($b) < 1e-12) ? 'div0' : $a / $b;
            });
        }

        return $left;
    };

    $power = function () use (&$unary, &$power, &$peek, &$position, $apply) {
        $base = $unary();

        if ($peek() === '^') {
            $position++;
            $exponent = $power();

            return $apply($base, $exponent, function ($a, $b) {
                $result = pow($a, $b);

                return is_finite($result) ? $result : 'value';
            });
        }

        return $base;
    };

    $unary = function () use (&$unary, &$postfix, &$peek, &$position) {
        if ($peek() === '-') {
            $position++;
            $value = ws_calc_scalar($unary());

            return is_string($value) ? $value : -$value;
        }

        if ($peek() === '+') {
            $position++;

            return $unary();
        }

        return $postfix();
    };

    $postfix = function () use (&$primary, &$peek, &$position) {
        $value = $primary();

        while ($peek() === '%') {
            $position++;
            $value = ws_calc_scalar($value);

            if (!is_string($value)) {
                $value = $value / 100;
            }
        }

        return $value;
    };

    $primary = function () use (&$compare, &$tokens, &$position, &$peek, &$broken, $resolve, $functions) {
        $token = $tokens[$position] ?? null;

        if ($token === null) {
            $broken = true;

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
                $value = $compare();

                if ($peek() !== ')') {
                    $broken = true;

                    return 'syntax';
                }

                $position++;

                return $value;

            case 'func':
                $position++; // the opening bracket
                $args = array();

                if ($peek() !== ')') {
                    while (!$broken) {
                        // An argument left out (ROUND(x;)) is not read as nought.
                        if (in_array($peek(), array(';', ')', null), true)) {
                            $broken = true;
                            break;
                        }

                        $args[] = $compare();

                        if ($peek() === ';') {
                            $position++;
                            continue;
                        }

                        break;
                    }
                }

                if ($broken || ($peek() !== ')')) {
                    $broken = true;

                    return 'syntax';
                }

                $position++;

                if (!isset($functions[$token[1]])) {
                    return 'name';
                }

                return ws_calc_call($functions[$token[1]], $args);
        }

        $broken = true;

        return 'syntax';
    };

    $value = $compare();

    if ($broken || ($position < count($tokens))) {
        $value = 'syntax';
    }

    $value = ws_calc_scalar($value);

    if (is_string($value)) {
        return array('ok' => false, 'value' => null, 'error' => $value);
    }

    if (!is_finite($value)) {
        return array('ok' => false, 'value' => null, 'error' => 'value');
    }

    return array('ok' => true, 'value' => $value, 'error' => '');
}

/**
 * Runs a function on its arguments: numbers, arrays of numbers from ranges
 * (where text cells are already left out) or error codes.
 *
 * @param string $name canonical
 * @param array  $args
 * @return float|string the value or an error code
 */
function ws_calc_call($name, $args)
{
    $args = array_values((array) $args);

    // These two look at an error rather than pass it on.
    if ($name === 'IF') {
        if ((count($args) < 2) || (count($args) > 3)) {
            return 'value';
        }

        $test = ws_calc_scalar($args[0]);

        if (is_string($test)) {
            return $test;
        }

        return ws_calc_scalar((abs($test) > 1e-12) ? $args[1] : ($args[2] ?? 0.0));
    }

    if ($name === 'IFERROR') {
        if (count($args) !== 2) {
            return 'value';
        }

        $first = ws_calc_scalar($args[0]);

        return is_string($first) ? ws_calc_scalar($args[1]) : $first;
    }

    foreach ($args as $arg) {
        if (is_string($arg)) {
            return $arg;
        }
    }

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

    // The functions that take single numbers, not lists.
    $single = function ($index, $default = null) use ($args) {
        if (!array_key_exists($index, $args)) {
            return $default;
        }

        return is_array($args[$index]) ? null : (float) $args[$index];
    };

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

        case 'PRODUCT':
            return empty($flat) ? 0.0 : (float) array_product($flat);

        case 'MEDIAN':
            if (empty($flat)) {
                return 'value';
            }

            sort($flat);
            $middle = (int) floor(count($flat) / 2);

            return (count($flat) % 2) ? $flat[$middle] : ($flat[$middle - 1] + $flat[$middle]) / 2;

        case 'AND':
        case 'OR':
            if (empty($flat)) {
                return 'value';
            }

            $true = 0;

            foreach ($flat as $value) {
                if (abs($value) > 1e-12) {
                    $true++;
                }
            }

            return (($name === 'AND') ? ($true === count($flat)) : ($true > 0)) ? 1.0 : 0.0;

        case 'NOT':
            $value = $single(0);

            return ((count($args) !== 1) || ($value === null)) ? 'value' : ((abs($value) > 1e-12) ? 0.0 : 1.0);

        case 'ROUND':
        case 'ROUNDUP':
        case 'ROUNDDOWN':
            $value = $single(0);
            $places = $single(1, 0.0);

            if ((count($args) < 1) || (count($args) > 2) || ($value === null) || ($places === null)) {
                return 'value';
            }

            $places = max(-6, min(6, (int) $places));

            if ($name === 'ROUND') {
                return round($value, $places);
            }

            // Away from nought, or towards it; the round(…, 9) keeps a figure
            // like 2.675 * 100 = 267.49999… from stepping the wrong way.
            $scale = pow(10, $places);
            $scaled = round(abs($value) * $scale, 9);
            $whole = ($name === 'ROUNDUP') ? ceil($scaled) : floor($scaled);

            return (($value < 0) ? -1 : 1) * $whole / $scale;

        case 'ABS':
            $value = $single(0);

            return ((count($args) !== 1) || ($value === null)) ? 'value' : abs($value);

        case 'SQRT':
            $value = $single(0);

            return ((count($args) !== 1) || ($value === null) || ($value < 0)) ? 'value' : sqrt($value);

        case 'POWER':
            $base = $single(0);
            $exponent = $single(1);

            if ((count($args) !== 2) || ($base === null) || ($exponent === null)) {
                return 'value';
            }

            $result = pow($base, $exponent);

            return is_finite($result) ? $result : 'value';

        case 'MOD':
            $number = $single(0);
            $divisor = $single(1);

            if ((count($args) !== 2) || ($number === null) || ($divisor === null)) {
                return 'value';
            }

            if (abs($divisor) < 1e-12) {
                return 'div0';
            }

            // The remainder takes the divisor's sign, as in a spreadsheet.
            return $number - $divisor * floor($number / $divisor);

        case 'PMT':
        case 'INSTALLMENT':
            // PMT(rate; periods; present value; [future value]; [due at start])
            // answers what is paid each period, negative as money going out;
            // INSTALLMENT(rate; periods; amount) the same as a plain figure.
            $rate = $single(0);
            $periods = $single(1);
            $present = $single(2);
            $future = $single(3, 0.0);
            $due = $single(4, 0.0);

            if ((count($args) < 3) || (count($args) > (($name === 'PMT') ? 5 : 3))
                || ($rate === null) || ($periods === null) || ($present === null) || ($future === null) || ($due === null)) {
                return 'value';
            }

            if (abs($periods) < 1e-12) {
                return 'div0';
            }

            if (abs($rate) < 1e-12) {
                $payment = -($present + $future) / $periods;
            } else {
                $growth = pow(1 + $rate, $periods);

                if (!is_finite($growth) || (abs($growth - 1) < 1e-12)) {
                    return 'value';
                }

                $payment = -($rate * ($present * $growth + $future)) / ((1 + $rate * ((abs($due) > 1e-12) ? 1 : 0)) * ($growth - 1));
            }

            if (!is_finite($payment)) {
                return 'value';
            }

            return ($name === 'INSTALLMENT') ? -$payment : $payment;
    }

    return 'name';
}

/**
 * Puts the names a formula may use in its place: each name written in it
 * becomes a "@L<slot>" token, longest names first so that "Net total" is not
 * read as "Net". A name followed by a bracket is a function, not the name.
 *
 * @param string  $expression
 * @param array[] $known folded name => text (as written), slot
 * @return array the expression, and whether a name was used
 */
function ws_calc_bind($expression, $known)
{
    $expression = (string) $expression;

    if (empty($known) || ($expression === '')) {
        return array($expression, false);
    }

    $names = array_values($known);
    usort($names, function ($a, $b) {
        return mb_strlen($b['text']) - mb_strlen($a['text']);
    });

    $used = false;

    foreach ($names as $name) {
        if (($name['text'] === '') || (mb_stripos($expression, $name['text']) === false)) {
            continue;
        }

        $pattern = '/(?<![\p{L}\p{N}_@])' . preg_quote($name['text'], '/') . '(?![\p{L}\p{N}_])(?!\s*\()/iu';
        $replaced = preg_replace($pattern, '@L' . (int) $name['slot'], $expression);

        if (($replaced !== null) && ($replaced !== $expression)) {
            $expression = $replaced;
            $used = true;
        }
    }

    return array($expression, $used);
}

/**
 * The names of a scope as ws_calc_bind() takes them, with their values by slot.
 *
 * @param array[] $scope folded name => text, value (ws_calc_block())
 * @param int     $first the first slot
 * @return array known (folded => text, slot), values (slot => float|null)
 */
function ws_calc_scope_slots($scope, $first = 0)
{
    $known = array();
    $values = array();
    $slot = (int) $first;

    foreach ((array) $scope as $folded => $entry) {
        $known[$folded] = array('text' => (string) $entry['text'], 'slot' => $slot);
        $values[$slot] = isset($entry['value']) ? $entry['value'] : null;
        $slot++;
    }

    return array($known, $values);
}

/**
 * Works out a table: every cell starting with = becomes its value. Row 1 is
 * the first row given (a table's header). A formula may use the names of the
 * scope (the hesap blocks of the same message or note): =B2*Rate.
 *
 * @param array[] $rows  cells as written, row by row
 * @param array[] $scope folded name => text, value
 * @return array[] per cell: formula (bool), value (float|null), display
 *                 (string), error (code|''), source (as written), named
 *                 (uses a name of the scope)
 */
function ws_calc_grid($rows, $scope = array())
{
    $rows = array_slice(array_values((array) $rows), 0, WS_CALC_MAX_ROWS);
    $grid = array();

    foreach ($rows as $r => $row) {
        $grid[$r] = array_slice(array_values((array) $row), 0, WS_CALC_MAX_COLUMNS);
    }

    list($known, $scope_values) = ws_calc_scope_slots($scope);

    $state = array();   // "r:c" => done | busy
    $result = array();  // "r:c" => array(value|null, error)
    $named = array();   // "r:c" => true for a formula that uses a name

    $cell = null;

    $cell = function ($r, $c) use (&$cell, &$grid, &$state, &$result, &$named, $known, $scope_values) {
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

        list($formula, $uses) = ws_calc_bind(substr($source, 1), $known);

        if ($uses) {
            $named[$key] = true;
        }

        $outcome = ws_calc_evaluate($formula, function ($token) use (&$cell, &$grid, $r, $c, $scope_values) {
            if ($token[0] === 'var') {
                $value = $scope_values[$token[1]] ?? null;

                return ($value === null) ? 'ref' : $value;
            }

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
                $out[$r][$c] = array('formula' => false, 'value' => ws_calc_number($source), 'display' => $source, 'error' => '', 'source' => $source, 'named' => false);
                continue;
            }

            list($value, $error) = $cell($r, $c);

            $out[$r][$c] = array(
                'formula' => true,
                'value'   => $value,
                'display' => ($error !== '') ? ws_calc_error_label($error) : ws_calc_format($value),
                'error'   => $error,
                'source'  => trim($source),
                'named'   => !empty($named[$r . ':' . $c]),
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
 * Where a line's "Name = expression" splits: the first = that is not part of
 * a comparison (<=, >=, ==, !=).
 *
 * @param string $line
 * @return int|false
 */
function ws_calc_assignment_at($line)
{
    $line = (string) $line;
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        if ($line[$i] !== '=') {
            continue;
        }

        $before = ($i > 0) ? $line[$i - 1] : '';

        if (($before !== '') && (strpos('<>!=', $before) !== false)) {
            continue;
        }

        if ((($i + 1) < $length) && ($line[$i + 1] === '=')) {
            $i++;
            continue;
        }

        return $i;
    }

    return false;
}

/**
 * Works out a hesap block: one value a line, "Name = expression" or a bare
 * expression; a name may be used by the lines after it. A line starting with
 * # or // is a remark, a blank line a gap. "Name:" opens a group that ends at
 * a blank line or the next heading; its name is then the SUM (or the function
 * after the colon) of its lines. A line or a heading starting with _ is
 * worked out but not shown; the name answers with or without the _.
 *
 * @param string[] $lines
 * @param array[]  $seed    names known before the block: folded => text, value
 * @param array    $options decimals, unit (ws_calc_block_options())
 * @param array[]  $scope   set to the names known after the block
 * @return array[] per line: kind (value | group | total | remark | blank),
 *                 label, source, value, display, error, derived (uses a
 *                 name), hidden, grouped (inside a group), input (a plain
 *                 figure a reader may change), line (its index)
 */
function ws_calc_block($lines, $seed = array(), $options = array(), &$scope = null)
{
    $decimals = isset($options['decimals']) ? $options['decimals'] : null;
    $unit = isset($options['unit']) ? (string) $options['unit'] : '';

    list($known, $values) = ws_calc_scope_slots($seed, WS_CALC_SEED_SLOT);

    $out = array();
    $group = null;

    $show = function ($value, $error) use ($decimals, $unit) {
        return ($error === '') ? ws_calc_format($value, $decimals, $unit) : ws_calc_error_label($error);
    };

    // A later line of the same name takes its place.
    $name = function ($label, $slot, $hidden) use (&$known) {
        if ($label === '') {
            return;
        }

        $known[mb_strtolower($label)] = array('text' => $label, 'slot' => $slot);

        if ($hidden) {
            $known[mb_strtolower('_' . $label)] = array('text' => '_' . $label, 'slot' => $slot);
        }
    };

    $close = function () use (&$group, &$out, &$values, $name, $show) {
        if ($group === null) {
            return;
        }

        $numbers = array();
        $error = '';
        $total = null;

        foreach ($group['members'] as $member) {
            if (is_string($member)) {
                if ($error === '') {
                    $error = $member;
                }
            } elseif ($member !== null) {
                $numbers[] = $member;
            }
        }

        if ($error === '') {
            $total = ws_calc_call($group['func'], array($numbers));

            if (is_string($total)) {
                $error = $total;
            } elseif (!is_finite($total)) {
                $error = 'value';
            }
        }

        $value = ($error === '') ? (float) $total : null;
        $values[$group['slot']] = $value;
        $name($group['label'], $group['slot'], $group['hidden']);

        $out[] = array(
            'kind'    => 'total',
            'label'   => $group['label'],
            'func'    => $group['func'],
            'source'  => '',
            'value'   => $value,
            'display' => $show($value, $error),
            'error'   => $error,
            'derived' => true,
            'hidden'  => $group['hidden'],
            'grouped' => false,
            'input'   => false,
            'line'    => $group['slot'],
        );

        $group = null;
    };

    $functions = ws_calc_functions();

    foreach (array_slice(array_values((array) $lines), 0, WS_CALC_MAX_ROWS) as $index => $raw) {
        $line = trim((string) $raw);

        if ($line === '') {
            $close();
            $out[] = array('kind' => 'blank', 'hidden' => false, 'grouped' => false);
            continue;
        }

        if ((substr($line, 0, 1) === '#') || (substr($line, 0, 2) === '//')) {
            $out[] = array(
                'kind'    => 'remark',
                'label'   => trim(ltrim($line, '#/')),
                'hidden'  => ($group !== null) && $group['hidden'],
                'grouped' => ($group !== null),
            );
            continue;
        }

        $hidden = false;

        if (substr($line, 0, 1) === '_') {
            $hidden = true;
            $line = ltrim(substr($line, 1));
        }

        $at = ws_calc_assignment_at($line);

        // A heading: "Name:" or "Name: AVERAGE".
        if (($at === false) && preg_match('/^([^:=()<>]*\p{L}[^:=()<>]*?)\s*:\s*([\p{L}_]*)\s*$/u', $line, $match)) {
            $func = ($match[2] === '') ? 'SUM' : ($functions[ws_calc_fold($match[2])] ?? '');

            if (in_array($func, ws_calc_group_functions(), true)) {
                $close();

                $group = array('label' => trim($match[1]), 'func' => $func, 'hidden' => $hidden, 'slot' => $index, 'members' => array());

                $out[] = array(
                    'kind'    => 'group',
                    'label'   => $group['label'],
                    'func'    => $func,
                    'hidden'  => $hidden,
                    'grouped' => false,
                    'line'    => $index,
                );
                continue;
            }
        }

        // Everything in a hidden group is hidden.
        if (($group !== null) && $group['hidden']) {
            $hidden = true;
        }

        $label = '';
        $source = $line;

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
            list($expression, $derived) = ws_calc_bind($source, $known);

            $result = ws_calc_evaluate($expression, function ($token) use (&$values) {
                if ($token[0] !== 'var') {
                    return 'ref';
                }

                $value = $values[$token[1]] ?? null;

                return ($value === null) ? 'ref' : $value;
            });
        }

        $values[$index] = $result['ok'] ? $result['value'] : null;
        $name($label, $index, $hidden);

        // A share written as one ("KDV = %20") is shown as one.
        $display = (($plain !== null) && (strpos($source, '%') !== false)) ? ws_calc_format_percent($plain) : $show($values[$index], $result['error']);

        if ($group !== null) {
            $group['members'][] = $result['ok'] ? $result['value'] : $result['error'];
        }

        $out[] = array(
            'kind'    => 'value',
            'label'   => $label,
            'source'  => $source,
            'value'   => $values[$index],
            'display' => $display,
            'error'   => $result['error'],
            'derived' => $derived,
            'hidden'  => $hidden,
            'grouped' => ($group !== null),
            'input'   => ($plain !== null) && !$hidden,
            'line'    => $index,
        );
    }

    $close();

    $scope = array();

    foreach ($known as $folded => $entry) {
        $scope[$folded] = array('text' => $entry['text'], 'value' => $values[$entry['slot']] ?? null);
    }

    return $out;
}

/**
 * The names the hesap blocks of a text give, in their order: what each
 * block inherits from the blocks above it, and what a table of the text may
 * use (every name of every block).
 *
 * @param string[] $lines the text, line by line
 * @param array[]  $seed  names known before the text
 * @return array seeds (opening line index => names), all (names)
 */
function ws_calc_body_scope($lines, $seed = array())
{
    $lines = array_values((array) $lines);
    $count = count($lines);
    $scope = (array) $seed;
    $seeds = array();

    for ($i = 0; $i < $count; $i++) {
        $language = ws_code_fence_open($lines[$i]);

        if ($language === null) {
            continue;
        }

        $code = array();
        $j = $i + 1;

        while (($j < $count) && !ws_code_fence_close($lines[$j])) {
            $code[] = $lines[$j];
            $j++;
        }

        if (ws_calc_block_language($language)) {
            $seeds[$i] = $scope;
            ws_calc_block($code, $scope, ws_calc_block_options(ws_code_fence_info($lines[$i])), $scope);
        }

        $i = $j;
    }

    return array('seeds' => $seeds, 'all' => $scope);
}

/**
 * A worked-out cell as HTML: the value, the formula in its title.
 *
 * @param array  $cell from ws_calc_grid()
 * @param string $key  "row:column" for a table a reader may work out again
 * @return string
 */
function ws_calc_cell_html($cell, $key = '')
{
    $title = $cell['source'] . (($cell['error'] !== '') ? ' · ' . ws_calc_error_help($cell['error']) : '');

    return '<span class="ws-calc' . (($cell['error'] !== '') ? ' ws-calc-error' : '') . '" title="' . h($title) . '" data-ws-formula="' . h($cell['source']) . '"'
        . (($key !== '') ? ' data-ws-cell="' . h($key) . '"' : '') . '>'
        . h($cell['display']) . '</span>';
}

/**
 * A hesap block as HTML. The block keeps what it was written as, so that a
 * reader's other figures can be worked out (ws_calc_live()); what a hidden
 * line holds is not shown, but it is not a secret from the reader either.
 *
 * @param string[] $lines
 * @param array[]  $seed names known before the block
 * @param string   $info the settings on the opening fence
 * @return string
 */
function ws_calc_block_html($lines, $seed = array(), $info = '')
{
    $lines = array_slice(array_map('strval', array_values((array) $lines)), 0, WS_CALC_MAX_ROWS);
    $info = mb_substr(trim((string) $info), 0, 80);
    $rows = '';
    $gap = false;
    $shown = 0;

    foreach (ws_calc_block($lines, $seed, ws_calc_block_options($info)) as $line) {
        if ($line['kind'] === 'blank') {
            $gap = ($shown > 0);
            continue;
        }

        if (!empty($line['hidden'])) {
            continue;
        }

        if ($gap) {
            $rows .= '<tr class="ws-calc-gap" aria-hidden="true"><td colspan="2"></td></tr>';
            $gap = false;
        }

        $shown++;
        $in = !empty($line['grouped']) ? ' ws-calc-in' : '';

        if ($line['kind'] === 'remark') {
            $rows .= '<tr class="ws-calc-remark' . $in . '"><td colspan="2">' . h($line['label']) . '</td></tr>';
            continue;
        }

        if ($line['kind'] === 'group') {
            $rows .= '<tr class="ws-calc-group"><th colspan="2" scope="rowgroup">' . h($line['label']) . '</th></tr>';
            continue;
        }

        $error = ($line['error'] !== '') ? ' ws-calc-error' : '';

        if ($line['kind'] === 'total') {
            $function = ws_calc_group_function_label($line['func']);
            $title = $function . (($line['error'] !== '') ? ' · ' . ws_calc_error_help($line['error']) : '');

            $rows .= '<tr class="ws-calc-total"><th scope="row">' . h($line['label'])
                . (($line['func'] !== 'SUM') ? ' <span class="ws-calc-func">' . h($function) . '</span>' : '') . '</th>'
                . '<td class="text-end"><span class="ws-calc' . $error . '" title="' . h($title) . '">' . h($line['display']) . '</span></td></tr>';
            continue;
        }

        $title = $line['source'] . (($line['error'] !== '') ? ' · ' . ws_calc_error_help($line['error']) : '')
            . (!empty($line['input']) ? ' · ' . lang('Click to try another figure; nothing is saved.') : '');
        $class = trim(($line['derived'] ? 'ws-calc-derived' : '') . $in);

        $rows .= '<tr' . (($class !== '') ? ' class="' . $class . '"' : '') . '>'
            . '<th scope="row">' . h($line['label']) . '</th>'
            . '<td class="text-end"><span class="ws-calc' . $error . '" title="' . h($title) . '" data-ws-formula="' . h($line['source']) . '"'
            . (!empty($line['input']) ? ' data-ws-calc-input="' . (int) $line['line'] . '"' : '') . '>' . h($line['display']) . '</span></td>'
            . '</tr>';
    }

    $data = json_encode(array('lines' => $lines, 'info' => $info), JSON_UNESCAPED_UNICODE);

    return '<div class="ws-calc-block"' . (is_string($data) ? ' data-ws-calc="' . h($data) . '"' : '') . '>'
        . '<div class="ws-calc-block-head"><i class="bi bi-calculator" aria-hidden="true"></i> <span>' . h(lang('Calculation')) . '</span></div>'
        . '<table class="table table-sm ws-table ws-calc-table"><tbody>' . $rows . '</tbody></table></div>';
}

/**
 * The hesap blocks and formula tables of a message or a note worked out
 * again with the figures a reader typed in, in the order they stand: a block
 * passes its names to the blocks after it and to every table. Nothing is
 * stored.
 *
 * @param array[] $units each lines + info (a hesap block) or grid (a table's cells)
 * @return array[] per unit: html (a block) or cells (r, c, display, error)
 */
function ws_calc_live($units)
{
    $clean = array();
    $scope = array();
    $seeds = array();

    foreach (array_slice(array_values((array) $units), 0, 50) as $index => $unit) {
        $unit = (array) $unit;

        if (isset($unit['lines']) && is_array($unit['lines'])) {
            $lines = array();

            foreach (array_slice(array_values($unit['lines']), 0, WS_CALC_MAX_ROWS) as $line) {
                $lines[] = is_scalar($line) ? mb_substr((string) $line, 0, 1000) : '';
            }

            $info = is_scalar($unit['info'] ?? '') ? mb_substr((string) ($unit['info'] ?? ''), 0, 80) : '';
            $seeds[$index] = $scope;
            ws_calc_block($lines, $scope, ws_calc_block_options($info), $scope);
            $clean[$index] = array('lines' => $lines, 'info' => $info);
        } elseif (isset($unit['grid']) && is_array($unit['grid'])) {
            $rows = array();

            foreach (array_slice(array_values($unit['grid']), 0, WS_CALC_MAX_ROWS) as $row) {
                $cells = array();

                foreach (array_slice(array_values((array) $row), 0, WS_CALC_MAX_COLUMNS) as $cell) {
                    $cells[] = is_scalar($cell) ? mb_substr((string) $cell, 0, 1000) : '';
                }

                $rows[] = $cells;
            }

            $clean[$index] = array('grid' => $rows);
        } else {
            $clean[$index] = array();
        }
    }

    $out = array();

    foreach ($clean as $index => $unit) {
        if (isset($unit['lines'])) {
            $out[] = array('html' => ws_calc_block_html($unit['lines'], $seeds[$index], $unit['info']));
        } elseif (isset($unit['grid'])) {
            $cells = array();

            foreach (ws_calc_grid($unit['grid'], $scope) as $r => $row) {
                foreach ($row as $c => $cell) {
                    if ($cell['formula']) {
                        $cells[] = array(
                            'r'       => (int) $r,
                            'c'       => (int) $c,
                            'display' => $cell['display'],
                            'error'   => ($cell['error'] !== '') ? ws_calc_error_help($cell['error']) : '',
                        );
                    }
                }
            }

            $out[] = array('cells' => $cells);
        } else {
            $out[] = array();
        }
    }

    return $out;
}

/**
 * A stored text with its formulas replaced by what they come to, for plain
 * text readers: an e-mail line, a notification, what Claude reads of a
 * channel. A formula cell reads "1.590 (=SUM(D2:D3))"; a hidden line of a
 * hesap block is left out, as it is on the screen.
 *
 * @param string $body
 * @return string
 */
function ws_calc_plain($body)
{
    $body = (string) $body;

    if ((strpos($body, '=') === false) && (strpos($body, '```') === false)) {
        return $body;
    }

    $lines = preg_split('/\r\n|\r|\n/', $body);
    $count = count($lines);
    $calc = ws_calc_body_scope($lines);
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

            foreach (ws_calc_block($block, $calc['seeds'][$i] ?? array(), ws_calc_block_options(ws_code_fence_info($lines[$i]))) as $line) {
                if (!empty($line['hidden'])) {
                    continue;
                }

                if ($line['kind'] === 'value') {
                    $shown = (ws_calc_number($line['source']) !== null) ? '' : ' (' . $line['source'] . ')';
                    $out[] = (($line['label'] !== '') ? $line['label'] . ' = ' : '') . $line['display'] . $shown;
                } elseif ($line['kind'] === 'total') {
                    $out[] = $line['label'] . ' = ' . $line['display'];
                } elseif ($line['kind'] === 'group') {
                    $out[] = $line['label'] . ':';
                } elseif ($line['kind'] === 'remark') {
                    $out[] = $line['label'];
                } elseif (!empty($out) && (end($out) !== '')) {
                    $out[] = '';
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
                $grid = ws_calc_grid($rows, $calc['all']);

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

/**
 * A function's name as the site's language writes it: TOPLA on a Turkish
 * site, SUM elsewhere. Either name works in a formula; this is the one the
 * writing windows put in.
 *
 * @param string $name canonical
 * @return string
 */
function ws_calc_local_name($name)
{
    $turkish = array(
        'SUM' => 'TOPLA', 'AVERAGE' => 'ORTALAMA', 'MIN' => 'MİN', 'MAX' => 'MAKS', 'COUNT' => 'ADET',
        'ROUND' => 'YUVARLA', 'ROUNDUP' => 'YUKARIYUVARLA', 'ROUNDDOWN' => 'AŞAĞIYUVARLA', 'ABS' => 'MUTLAK',
        'SQRT' => 'KAREKÖK', 'POWER' => 'KUVVET', 'MOD' => 'MOD', 'PRODUCT' => 'ÇARPIM', 'MEDIAN' => 'ORTANCA',
        'IF' => 'EĞER', 'IFERROR' => 'EĞERHATA', 'AND' => 'VE', 'OR' => 'YADA', 'NOT' => 'DEĞİL',
        'PMT' => 'DEVRESEL_ÖDEME', 'INSTALLMENT' => 'TAKSİT',
    );

    return ws_calc_decimal_comma() ? ($turkish[$name] ?? $name) : $name;
}

/**
 * Ready-made hesap blocks for the calculation window, in the site's
 * language: names, function names and decimal marks as a reader writes them.
 *
 * @return array[] key, label, body (the block's lines, without the fences)
 */
function ws_calc_templates()
{
    $decimal = function ($whole, $fraction) {
        return $whole . (ws_calc_decimal_comma() ? ',' : '.') . $fraction;
    };

    $income = lang('Income');
    $expenses = lang('Expenses');
    $net = lang('Net amount');
    $vat = lang('VAT');
    $vat_rate = lang('VAT rate');
    $list_price = lang('List price');
    $discount_rate = lang('Discount rate');
    $discount = lang('Discount');
    $cost = lang('Cost');
    $sale = lang('Sale price');
    $profit = lang('Profit');
    $loan = lang('Loan amount');
    $interest = lang('Monthly interest');
    $months = lang('Term (months)');
    $installment = lang('Monthly installment');
    $repayment = lang('Total repayment');
    $items = lang('Line items');
    $sales = lang('Weekly sales');
    $target = lang('Target');
    $rate = lang('Exchange rate');
    $amount = lang('Amount');

    return array(
        array(
            'key'   => 'budget',
            'label' => lang('Monthly budget'),
            'body'  => implode("\n", array(
                $income . ':',
                lang('Salary') . ' = 40000',
                lang('Other income') . ' = 5000',
                '',
                $expenses . ':',
                lang('Rent') . ' = 15000',
                lang('Bills') . ' = 3500',
                lang('Groceries') . ' = 8000',
                '',
                lang('Left over') . ' = ' . $income . ' - ' . $expenses,
            )),
        ),
        array(
            'key'   => 'vat',
            'label' => lang('VAT'),
            'body'  => implode("\n", array(
                $net . ' = 1000',
                $vat_rate . ' = 20%',
                $vat . ' = ' . $net . ' * ' . $vat_rate,
                lang('Total') . ' = ' . $net . ' + ' . $vat,
            )),
        ),
        array(
            'key'   => 'invoice',
            'label' => lang('Invoice lines'),
            'body'  => implode("\n", array(
                $items . ':',
                lang('Line item') . ' 1 = 2 * 450',
                lang('Line item') . ' 2 = 3 * 120',
                lang('Line item') . ' 3 = 1 * 1250',
                '',
                '_' . $vat_rate . ' = 20%',
                $vat . ' = ' . $items . ' * ' . $vat_rate,
                lang('Grand total') . ' = ' . $items . ' + ' . $vat,
            )),
        ),
        array(
            'key'   => 'discount',
            'label' => $discount,
            'body'  => implode("\n", array(
                $list_price . ' = 2500',
                $discount_rate . ' = 15%',
                $discount . ' = ' . $list_price . ' * ' . $discount_rate,
                lang('Price to pay') . ' = ' . $list_price . ' - ' . $discount,
            )),
        ),
        array(
            'key'   => 'margin',
            'label' => lang('Profit margin'),
            'body'  => implode("\n", array(
                $cost . ' = 750',
                $sale . ' = 1200',
                $profit . ' = ' . $sale . ' - ' . $cost,
                lang('Margin (%)') . ' = ' . ws_calc_local_name('ROUND') . '(' . $profit . ' / ' . $sale . ' * 100; 1)',
            )),
        ),
        array(
            'key'   => 'loan',
            'label' => lang('Loan installment'),
            'body'  => implode("\n", array(
                $loan . ' = 100000',
                $interest . ' = ' . $decimal(3, 5) . '%',
                $months . ' = 12',
                $installment . ' = ' . ws_calc_local_name('INSTALLMENT') . '(' . $interest . '; ' . $months . '; ' . $loan . ')',
                $repayment . ' = ' . $installment . ' * ' . $months,
                lang('Total interest') . ' = ' . $repayment . ' - ' . $loan,
            )),
        ),
        array(
            'key'   => 'average',
            'label' => lang('Average'),
            'body'  => implode("\n", array(
                $sales . ': ' . ws_calc_local_name('AVERAGE'),
                lang('Week') . ' 1 = 120',
                lang('Week') . ' 2 = 95',
                lang('Week') . ' 3 = 140',
                lang('Week') . ' 4 = 110',
                '',
                $target . ' = 115',
                lang('Difference') . ' = ' . $sales . ' - ' . $target,
            )),
        ),
        array(
            'key'   => 'exchange',
            'label' => lang('Currency conversion'),
            'body'  => implode("\n", array(
                $rate . ' = ' . $decimal(34, 20),
                $amount . ' = 49',
                lang('Converted amount') . ' = ' . $amount . ' * ' . $rate,
            )),
        ),
    );
}

/**
 * What the writing windows are told about sums (assets/js/workspace_editor.js):
 * the templates, the functions offered with a word on each, the functions a
 * group heading takes, and the setting words of the site's language.
 *
 * @return array
 */
function ws_calc_js_config()
{
    $help = array(
        'SUM'         => lang('Adds the values up'),
        'AVERAGE'     => lang('The average of the values'),
        'MIN'         => lang('The smallest value'),
        'MAX'         => lang('The largest value'),
        'COUNT'       => lang('How many values there are'),
        'ROUND'       => lang('Rounds to the places given: (value; 2)'),
        'ROUNDUP'     => lang('Rounds up'),
        'ROUNDDOWN'   => lang('Rounds down'),
        'IF'          => lang('A condition: (a > b; when true; when false)'),
        'IFERROR'     => lang('A value to use when the first one fails'),
        'ABS'         => lang('The value without its sign'),
        'SQRT'        => lang('The square root'),
        'POWER'       => lang('A power: (value; 2)'),
        'MOD'         => lang('The remainder of a division'),
        'PRODUCT'     => lang('Multiplies the values'),
        'MEDIAN'      => lang('The middle value'),
        'INSTALLMENT' => lang('A loan installment: (rate; periods; amount)'),
    );

    $functions = array();

    foreach ($help as $name => $text) {
        $functions[] = array('name' => ws_calc_local_name($name), 'canonical' => $name, 'help' => $text);
    }

    $groups = array();

    foreach (ws_calc_group_functions() as $name) {
        $groups[] = array('name' => ws_calc_local_name($name), 'label' => ws_calc_group_function_label($name));
    }

    return array(
        'templates' => ws_calc_templates(),
        'functions' => $functions,
        'groups'    => $groups,
        'decimal'   => ws_calc_decimal_comma() ? ',' : '.',
        'keys'      => array('decimals' => lang('decimals'), 'unit' => lang('unit')),
    );
}
