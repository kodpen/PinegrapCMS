<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - money.
 *
 * Every multiplication and every rounding in the module happens here. Amounts
 * are whole kurus in integers, the way the rest of the software stores money;
 * rates are decimals because they are rates. Nothing in the module is allowed
 * to multiply an amount by a rate on its own - one rounding rule in one place
 * is what makes a ledger tie to the documents drawn from it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_ERP_ENTRY')) {
    exit;
}

/**
 * Read an amount typed by an operator and return whole kurus.
 *
 * Accepts both separators in either role: 1.234,56 and 1,234.56 both mean the
 * same amount, and the last separator with two digits behind it is the decimal
 * one. Anything that is not a digit or a separator is dropped, so a pasted
 * currency symbol does not turn the figure into zero.
 *
 * @param string|int|float $value
 * @return int  Kurus, negative when the input carried a minus sign
 */
function erp_kurus($value)
{
    $raw = trim((string) $value);

    if ($raw === '') {
        return 0;
    }

    $negative = (strpos($raw, '-') !== false);
    $digits = preg_replace('/[^0-9.,]/', '', $raw);

    if ($digits === '') {
        return 0;
    }

    $last_dot = strrpos($digits, '.');
    $last_comma = strrpos($digits, ',');
    $separator = '';

    if (($last_dot !== false) || ($last_comma !== false)) {
        $position = max(($last_dot === false) ? -1 : $last_dot, ($last_comma === false) ? -1 : $last_comma);
        // Two digits behind the last separator makes it the decimal point.
        // Three makes it a thousands separator, which is why 1.234 is one
        // thousand two hundred and thirty four and not one and a bit.
        if ((strlen($digits) - $position - 1) <= 2) {
            $separator = substr($digits, $position, 1);
        }
    }

    if ($separator === '') {
        $whole = preg_replace('/[^0-9]/', '', $digits);
        $fraction = '';
    } else {
        $parts = explode($separator, $digits);
        $fraction = array_pop($parts);
        $whole = preg_replace('/[^0-9]/', '', implode('', $parts));
        $fraction = preg_replace('/[^0-9]/', '', $fraction);
    }

    $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
    $kurus = ((int) $whole * 100) + (int) $fraction;

    return $negative ? -$kurus : $kurus;
}

/**
 * Whether the store's country writes a decimal comma (1,5) rather than a
 * decimal point (1.5). Most of continental Europe, Turkey and South America
 * use the comma; the English-speaking world, most of Asia and Mexico the
 * point. The ERP prints its own figures with a point either way; this only
 * settles how a typed figure with one comma is read.
 *
 * @return bool
 */
function erp_decimal_comma()
{
    static $comma = null;

    if ($comma === null) {
        $point = array('US', 'GB', 'IE', 'CA', 'AU', 'NZ', 'IN', 'PK', 'BD', 'LK', 'NP', 'CN', 'HK', 'MO', 'TW',
            'JP', 'KR', 'SG', 'MY', 'PH', 'TH', 'MX', 'GT', 'HN', 'SV', 'NI', 'PA', 'DO', 'PR', 'IL', 'SA', 'AE',
            'QA', 'KW', 'BH', 'OM', 'JO', 'EG', 'NG', 'KE', 'GH', 'UG', 'TZ', 'ZW', 'BW', 'MT', 'CH', 'LI');
        $country = function_exists('erp_account_country') ? erp_account_country('') : '';
        $comma = !in_array($country, $point, true);
    }

    return $comma;
}

/**
 * The column separator of the CSV files the ERP writes: a semicolon where the
 * comma is the decimal separator (the way a spreadsheet saved in Turkey or
 * Germany writes CSV), a comma where it is not. Imports detect it by
 * themselves, so either reads back.
 *
 * @return string
 */
function erp_csv_delimiter()
{
    return erp_decimal_comma() ? ';' : ',';
}

/**
 * Read a quantity typed by an operator: a decimal with up to four places.
 *
 * Both separators are accepted. When both appear, the last is the decimal
 * one and the others group thousands (1.234,5 and 1,234.5). A separator that
 * appears more than once groups (1,000,000). A single point is a decimal
 * point: it is what the ERP writes back into its own fields. A single comma
 * with three digits behind it is the one case that reads two ways - 1,500 is
 * one and a half in Istanbul and fifteen hundred in Chicago - and the store's
 * country decides (erp_decimal_comma()).
 *
 * @param string|int|float $value
 * @return float  Never negative
 */
function erp_quantity_in($value)
{
    $raw = preg_replace('/[^0-9.,]/', '', (string) $value);

    if ($raw === '') {
        return 0.0;
    }

    $dots = substr_count($raw, '.');
    $commas = substr_count($raw, ',');
    $decimal = '';

    if (($dots > 0) && ($commas > 0)) {
        $decimal = (strrpos($raw, '.') > strrpos($raw, ',')) ? '.' : ',';
    } elseif ($dots === 1) {
        $decimal = '.';
    } elseif ($commas === 1) {
        $behind = strlen($raw) - strrpos($raw, ',') - 1;
        $decimal = (($behind === 3) && !erp_decimal_comma()) ? '' : ',';
    }

    if ($decimal === '') {
        return (float) preg_replace('/[^0-9]/', '', $raw);
    }

    $position = strrpos($raw, $decimal);
    $whole = preg_replace('/[^0-9]/', '', substr($raw, 0, $position));
    $fraction = preg_replace('/[^0-9]/', '', substr($raw, $position + 1));

    return (float) (($whole !== '' ? $whole : '0') . '.' . ($fraction !== '' ? $fraction : '0'));
}

/**
 * A quantity for the screen, written like the ERP's amounts: a point for the
 * decimals, commas between thousands, no trailing zeros (1,250.5).
 *
 * @param float|string $quantity
 * @return string
 */
function erp_quantity_text($quantity)
{
    $separators = erp_number_separators();
    $text = rtrim(rtrim(number_format((float) $quantity, 4, $separators['decimal'], $separators['thousands']), '0'), $separators['decimal']);

    return ($text === '' || $text === '-0') ? '0' : $text;
}

/**
 * A decimal as a whole number of its smallest unit at the given places
 * (1.15 at four places is 11500), half away from zero.
 *
 * The rounding is done on the decimal digits the number is written with -
 * sprintf() writes the same digits as JavaScript's toFixed() - and not on a
 * product of floats: 1.15 * 10000 is 11499.999... in binary, and how round()
 * treats that has changed between PHP versions. The invoice editor applies
 * the same rule (decimalUnits(), assets/js/erp_invoice_editor.js).
 *
 * The units are a whole number held in a float: a float is exact up to 2^53,
 * far above the 999999999999999 units a DECIMAL(15,4) quantity can hold,
 * while an int stops at PHP_INT_MAX, which on a 32-bit build is 214748.3647
 * at four places.
 *
 * @param float|int|string $value
 * @param int              $places  0 to 10
 * @return float
 */
function erp_decimal_units($value, $places)
{
    $value = (float) $value;
    $places = max(0, min(10, (int) $places));
    $parts = explode('.', sprintf('%.10F', abs($value)));
    $units = (float) ($parts[0] . substr($parts[1], 0, $places));

    if ((int) substr($parts[1], $places, 1) >= 5) {
        $units += 1;
    }

    // A negative value that rounds to nothing is 0.0, not -0.0.
    return (($value < 0) && ($units > 0)) ? -$units : $units;
}

/**
 * A quantity at the four places it is stored with (DECIMAL(15,4)).
 *
 * A line is priced with the quantity it is saved with, so the draft, the
 * preview and the issued invoice, which is worked out again from the stored
 * lines, all come to the same amount.
 *
 * @param float|int|string $quantity
 * @return float
 */
function erp_quantity_round($quantity)
{
    return erp_decimal_units($quantity, 4) / 10000.0;
}

/**
 * The separators figures are written with on screen and on documents, by the
 * panel language: 1.234,56 in Turkish, German, Spanish and the like; 1 234,56
 * in French, Russian and the Nordic languages; 1,234.56 in English and the
 * rest. Only what is shown follows it - typed figures are read with either
 * separator (erp_kurus(), erp_quantity_in()), files and the API keep their
 * own fixed formats.
 *
 * @return array ['decimal' => string, 'thousands' => string]
 */
function erp_number_separators()
{
    // The storefront and the rest of the panel follow the same rule, read
    // from the language file (pg_number_separators(), ecommerce.php).
    if (function_exists('pg_number_separators')) {
        return pg_number_separators();
    }

    static $separators = null;

    if ($separators === null) {
        $language = defined('SOFTWARE_LANGUAGE') ? strtolower(substr((string) SOFTWARE_LANGUAGE, 0, 2)) : 'en';
        $dot_group = array('tr', 'de', 'es', 'it', 'pt', 'nl', 'id', 'da', 'ro', 'hr', 'sl', 'sr', 'el', 'az', 'ca', 'is');
        $space_group = array('fr', 'ru', 'pl', 'cs', 'sk', 'uk', 'bg', 'hu', 'sv', 'fi', 'nb', 'no', 'et', 'lt', 'lv', 'kk');

        if (in_array($language, $dot_group, true)) {
            $separators = array('decimal' => ',', 'thousands' => '.');
        } elseif (in_array($language, $space_group, true)) {
            // A no-break space, so a figure never wraps at its grouping.
            $separators = array('decimal' => ',', 'thousands' => "\u{00A0}");
        } else {
            $separators = array('decimal' => '.', 'thousands' => ',');
        }
    }

    return $separators;
}

/**
 * A percentage for the screen and the documents, the way the panel language
 * writes it: %20 in Turkish, 20% in English and most other languages. Up to
 * three decimals, trailing zeros dropped.
 *
 * @param float|string $rate
 * @return string
 */
function erp_percent_text($rate)
{
    $separators = erp_number_separators();
    $number = rtrim(rtrim(number_format((float) $rate, 3, $separators['decimal'], ''), '0'), $separators['decimal']);
    $language = defined('SOFTWARE_LANGUAGE') ? strtolower(substr((string) SOFTWARE_LANGUAGE, 0, 2)) : 'en';

    return ($language === 'tr') ? ('%' . $number) : ($number . '%');
}

/**
 * An amount times a decimal held as whole units of 1/$scale, rounded half
 * away from zero: $amount x $units / $scale, in whole numbers only.
 *
 * A product of floats lands a hair beside a half (1310 * 1.15 is
 * 1506.4999...), and round() treats that differently on PHP 8.3 and on 8.4
 * and later, as JavaScript's Math.round() does. Both factors are split at
 * the scale, so no partial product outgrows an integer while the result
 * fits, and the only division is of a remainder below the scale squared.
 * The invoice editor works the same way (multiplyUnits(),
 * assets/js/erp_invoice_editor.js), where every step also stays within the
 * integers a JavaScript number holds exactly.
 *
 * On a 32-bit build the remainder product reaches the scale squared (10^10
 * for a rate) and the units of a quantity past 214,748.3647 are already a
 * float (erp_decimal_units()), so there the same steps run on floats, which
 * hold every whole number below 2^53 exactly: fmod() of whole floats and the
 * division of a multiple of the scale have no rounding error.
 *
 * @param int|float $amount  A whole number
 * @param int|float $units   A whole number
 * @param int       $scale   10000 for four places, 100000 for a percentage with three
 * @return int|float  An int; a float only on a 32-bit build, for a result past PHP_INT_MAX
 */
function erp_multiply_units($amount, $units, $scale)
{
    $amount = is_int($amount) ? $amount : (float) $amount;
    $units = is_int($units) ? $units : (float) $units;
    $scale = max(1, (int) $scale);
    $negative = (($amount < 0) xor ($units < 0));

    $amount = abs($amount);
    $units = abs($units);

    // Whole units held in a float (erp_decimal_units()) take the integer
    // steps when they fit a 64-bit int.
    if ((PHP_INT_SIZE >= 8) && is_float($amount) && ($amount < PHP_INT_MAX)) {
        $amount = (int) $amount;
    }

    if ((PHP_INT_SIZE >= 8) && is_float($units) && ($units < PHP_INT_MAX)) {
        $units = (int) $units;
    }

    if (is_int($amount) && is_int($units) && (PHP_INT_SIZE >= 8)) {
        $amount_rest = $amount % $scale;
        $amount_whole = intdiv($amount, $scale);
        $units_rest = $units % $scale;
        $units_whole = intdiv($units, $scale);
        $rest = $amount_rest * $units_rest;
        $rest_rest = $rest % $scale;

        $result = ($amount_whole * $units_whole * $scale) + ($amount_whole * $units_rest)
            + ($amount_rest * $units_whole) + intdiv($rest, $scale);
    } else {
        $amount = (float) $amount;
        $units = (float) $units;
        $amount_rest = fmod($amount, $scale);
        $amount_whole = ($amount - $amount_rest) / $scale;
        $units_rest = fmod($units, $scale);
        $units_whole = ($units - $units_rest) / $scale;
        $rest = $amount_rest * $units_rest;
        $rest_rest = fmod($rest, $scale);

        $result = ($amount_whole * $units_whole * $scale) + ($amount_whole * $units_rest)
            + ($amount_rest * $units_whole) + (($rest - $rest_rest) / $scale);
    }

    if (($rest_rest * 2) >= $scale) {
        $result += 1;
    }

    if (is_float($result) && ($result < PHP_INT_MAX)) {
        $result = (int) $result;
    }

    return ($negative && ($result > 0)) ? -$result : $result;
}

/**
 * Apply a percentage to an amount.
 *
 * The module's only multiplication of money by a rate. The rate is taken at
 * the three places the rate columns keep, and the result rounds half away
 * from zero on the exact product (erp_multiply_units()): 4.35% of 30.00 is
 * 1.305, so 1.31, on every PHP version and in the invoice editor.
 *
 * @param int   $kurus
 * @param float $rate   Percentage, e.g. 20 for 20%
 * @return int|float  Kurus; a float only on a 32-bit build, past PHP_INT_MAX
 */
function erp_apply_rate($kurus, $rate)
{
    return erp_multiply_units((int) $kurus, erp_decimal_units($rate, 3), 100000);
}

/**
 * Take the tax out of an amount that already includes it.
 *
 * The net is the amount that, with erp_apply_rate() on top, comes back to the
 * gross; where rounding leaves no such amount the nearest one is kept and the
 * tax is what is left, a kurus away from rate x net at most. Net and tax add
 * up to the gross exactly, always.
 *
 * @param int   $gross  Kurus, tax included
 * @param float $rate   Percentage
 * @return array ['net' => int, 'tax' => int]
 */
function erp_split_gross($gross, $rate)
{
    $gross = (int) $gross;
    $rate = (float) $rate;

    if (($rate <= 0) || ($gross === 0)) {
        return array('net' => $gross, 'tax' => 0);
    }

    $net = (int) round($gross * 100 / (100 + $rate));

    foreach (array($net, $net - 1, $net + 1) as $candidate) {
        if (($candidate + erp_apply_rate($candidate, $rate)) === $gross) {
            $net = $candidate;
            break;
        }
    }

    return array('net' => $net, 'tax' => $gross - $net);
}

/**
 * The amount before tax that comes to a total once the tax at the rate is
 * taken on the whole of it (erp_apply_rate()); null when no amount does.
 *
 * There is one at most: a kurus more on the amount puts one or two on the
 * total, so at 20% one total in six (3 kurus off a multiple of 6, such as
 * 57.93) is never reached, at 10% one in eleven. A document whose lines at
 * one rate come to such a total cannot have its tax taken on their sum, the
 * way an e-document provider takes it.
 *
 * @param int   $total  Kurus, tax included
 * @param float $rate   Percentage
 * @return int|null
 */
function erp_rate_amount($total, $rate)
{
    $total = (int) $total;
    $near = (int) round($total * 100 / (100 + (float) $rate));

    foreach (array($near, $near - 1, $near + 1) as $amount) {
        if (($amount >= 0) && (($amount + erp_apply_rate($amount, $rate)) === $total)) {
            return $amount;
        }
    }

    return null;
}

/**
 * Convert an amount into the base currency at a given exchange rate.
 *
 * The one place a document-currency figure becomes a base-currency one. The
 * ledger, the settlements and the invoice header all call this, so the same
 * amount at the same rate is the same number of kurus everywhere.
 *
 * @param int   $kurus          Amount in the document currency
 * @param float $exchange_rate  Base units per unit of the document currency
 * @return int  Kurus of the base currency
 */
function erp_to_base($kurus, $exchange_rate)
{
    return (int) round(((int) $kurus) * ((float) $exchange_rate));
}

/**
 * A line's amount from its unit price and quantity.
 *
 * Quantities are decimals (1.5 metres), so this is the module's one
 * multiplication of money by a quantity. The quantity is taken at the four
 * places it is stored with, and the result rounds half away from zero on the
 * exact product (erp_multiply_units()): 13.10 x 1.15 is 15.065, so 15.07.
 *
 * @param int   $unit_price  Kurus
 * @param float $quantity
 * @return int|float  Kurus; a float only on a 32-bit build, past PHP_INT_MAX
 */
function erp_line_total($unit_price, $quantity)
{
    return erp_multiply_units((int) $unit_price, erp_decimal_units($quantity, 4), 10000);
}

/**
 * Split an amount across lines in proportion to their weights.
 *
 * Used wherever a figure held once on a document has to be shown per line - an
 * order discount, say, which sits on the order header but has to appear against
 * the lines on the invoice.
 *
 * The parts always add up to the total. Each line gets its floor share, and the
 * kurus left over go to the lines with the largest remainders, biggest first
 * and equal ones in the lines' own order; rounding each line on its own would
 * leave the document a kurus or two short of itself. The checkout splits an
 * order's tax with the same rule (pg_tax_spread()).
 *
 * @param int   $total    Kurus to distribute
 * @param array $weights  key => weight (line totals, usually)
 * @return array  key => kurus, summing exactly to $total
 */
function erp_allocate($total, $weights)
{
    $total = (int) $total;
    $shares = array();

    $sum = 0;
    foreach ($weights as $key => $weight) {
        $weight = (int) $weight;
        $shares[$key] = 0;
        $sum += $weight;
    }

    if (($sum <= 0) || empty($weights)) {
        // Nothing to go on. The whole amount lands on the first line rather
        // than disappearing, because a document that does not add up is worse
        // than one with an odd-looking line.
        $keys = array_keys($weights);
        if (!empty($keys)) {
            $shares[$keys[0]] = $total;
        }
        return $shares;
    }

    $remainders = array();
    $position = array();
    $allocated = 0;

    foreach ($weights as $key => $weight) {
        $exact = ($total * (int) $weight) / $sum;
        $floor = (int) floor($exact);
        $shares[$key] = $floor;
        $remainders[$key] = $exact - $floor;
        $position[$key] = count($position);
        $allocated += $floor;
    }

    $left = $total - $allocated;

    if ($left !== 0) {
        $step = ($left > 0) ? 1 : -1;
        // Equal remainders are ordered by position, not left to arsort():
        // sorting is stable only from PHP 8.0, and before it the equal
        // remainders of more than 16 lines came out in another order, so the
        // kurus went to other lines on PHP 7 than on PHP 8.
        $order = array_keys($remainders);
        usort($order, function ($a, $b) use ($remainders, $position) {
            if ($remainders[$a] == $remainders[$b]) {
                return $position[$a] - $position[$b];
            }
            return ($remainders[$a] < $remainders[$b]) ? 1 : -1;
        });
        if ($left < 0) {
            $order = array_reverse($order);
        }
        $index = 0;
        $count = count($order);
        while (($left !== 0) && ($count > 0)) {
            $shares[$order[$index % $count]] += $step;
            $left -= $step;
            $index++;
        }
    }

    return $shares;
}

/**
 * A document's taxes, each taken once on the sum of the lines at its rate.
 *
 * Lines with the same VAT rate and the same withholding rate form a group. The
 * group's VAT is erp_apply_rate() on the sum of its lines' net amounts and is
 * spread back over those lines by largest remainder (erp_allocate()), so the
 * lines add up to their group and each stays within a kurus of the VAT it
 * would carry on its own. A withheld line and a plain one at the same rate are
 * two groups.
 *
 * The withholding is every group's VAT times its withholding rate, summed over
 * the document and rounded once - the one rounding here that is not
 * erp_apply_rate(), because a rounding per group would round the document's
 * withholding several times. It is spread over the withheld lines in
 * proportion to what each of them withholds. The second tax is taken per its
 * own rate on the sum of the same net amounts and is never withheld.
 *
 * Taking a tax on the sum instead of line by line is also how an e-document
 * provider that works the taxes out from the rates reaches its figures, so a
 * document built here has the same totals there, to the kurus.
 *
 * @param array $lines  key => [
 *                          'net'              => int, kurus after discount, zero or more
 *                          'tax_rate'         => float, VAT percentage
 *                          'withholding_rate' => float, percentage of the VAT withheld
 *                          'tax2_rate'        => float, second tax percentage
 *                      ]; a missing rate is zero
 * @return array [
 *     'lines'       => key => ['vat' => int, 'tax2' => int, 'withholding' => int],
 *     'groups'      => list of ['tax_rate', 'withholding_rate', 'net', 'vat'],
 *                      in the order the groups first appear,
 *     'vat'         => int,
 *     'tax2'        => int,
 *     'withholding' => int,
 * ]
 */
function erp_rate_vat($lines)
{
    $out = array();
    $groups = array();
    $members = array();
    $tax2_groups = array();
    $tax2_members = array();

    foreach ((array) $lines as $key => $line) {
        $net = (int) ($line['net'] ?? 0);
        $tax_rate = (float) ($line['tax_rate'] ?? 0);
        // Nothing is withheld from a line that carries no VAT.
        $withholding_rate = ($tax_rate > 0) ? (float) ($line['withholding_rate'] ?? 0) : 0.0;
        $tax2_rate = (float) ($line['tax2_rate'] ?? 0);

        $out[$key] = array('vat' => 0, 'tax2' => 0, 'withholding' => 0);

        // A rate comes as a float from a form and as a DECIMAL string from the
        // database; three places is the rate columns' own scale.
        $group = sprintf('%.3F|%.3F', $tax_rate, $withholding_rate);
        if (!isset($groups[$group])) {
            $groups[$group] = array(
                'tax_rate' => $tax_rate,
                'withholding_rate' => $withholding_rate,
                'net' => 0,
                'vat' => 0,
            );
            $members[$group] = array();
        }
        $groups[$group]['net'] += $net;
        $members[$group][$key] = $net;

        if ($tax2_rate > 0) {
            $tax2_group = sprintf('%.3F', $tax2_rate);
            if (!isset($tax2_groups[$tax2_group])) {
                $tax2_groups[$tax2_group] = array('rate' => $tax2_rate, 'net' => 0);
                $tax2_members[$tax2_group] = array();
            }
            $tax2_groups[$tax2_group]['net'] += $net;
            $tax2_members[$tax2_group][$key] = $net;
        }
    }

    $vat_total = 0;
    $withheld = 0.0;
    $withholding_weights = array();

    foreach ($groups as $group => $data) {
        $vat = erp_apply_rate($data['net'], $data['tax_rate']);
        $groups[$group]['vat'] = $vat;
        $vat_total += $vat;

        foreach (erp_allocate($vat, $members[$group]) as $key => $share) {
            $out[$key]['vat'] = $share;
        }

        if ($data['withholding_rate'] > 0) {
            $withheld += $vat * $data['withholding_rate'];
            // erp_allocate() takes whole weights: thousandths of a kurus keep
            // a rate's three places.
            foreach (array_keys($members[$group]) as $key) {
                $withholding_weights[$key] = (int) round($out[$key]['vat'] * $data['withholding_rate'] * 1000);
            }
        }
    }

    $withholding_total = (int) round($withheld / 100);

    if ($withholding_total !== 0) {
        foreach (erp_allocate($withholding_total, $withholding_weights) as $key => $share) {
            $out[$key]['withholding'] = $share;
        }
    }

    $tax2_total = 0;

    foreach ($tax2_groups as $group => $data) {
        $tax2 = erp_apply_rate($data['net'], $data['rate']);
        $tax2_total += $tax2;

        foreach (erp_allocate($tax2, $tax2_members[$group]) as $key => $share) {
            $out[$key]['tax2'] = $share;
        }
    }

    return array(
        'lines' => $out,
        'groups' => array_values($groups),
        'vat' => $vat_total,
        'tax2' => $tax2_total,
        'withholding' => $withholding_total,
    );
}

/**
 * Format kurus for the screen, in the base currency.
 *
 * @param int  $kurus
 * @param bool $show_sign  true keeps a leading minus on negative amounts
 * @return string
 */
function erp_money_out($kurus, $show_sign = true)
{
    return erp_money_out_currency($kurus, defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : '', $show_sign);
}

/**
 * Format kurus for the screen, in a named currency.
 *
 * The symbol comes from the currencies table (the base symbol for the base
 * currency; the ISO code when the store does not list the currency). No
 * conversion happens here: the amount is already in that currency. The
 * store-side price helpers multiply by the visitor's exchange rate, which is
 * right for a shop window and wrong for a ledger - an operator whose browser
 * had picked another currency for the shop would otherwise read every ERP
 * figure converted, under the wrong symbol.
 *
 * @param int    $kurus
 * @param string $currency_code
 * @param bool   $show_sign  true keeps a leading minus on negative amounts
 * @return string  Plain text, for callers to put through h()
 */
function erp_money_out_currency($kurus, $currency_code, $show_sign = true)
{
    static $symbols = null;

    $kurus = (int) $kurus;
    $currency_code = strtoupper(trim((string) $currency_code));

    $symbol = '';
    $suffix = '';

    if (defined('BASE_CURRENCY_CODE') && ($currency_code === strtoupper((string) BASE_CURRENCY_CODE))) {
        $symbol = (string) BASE_CURRENCY_SYMBOL;
    } elseif ($currency_code !== '') {
        // Read only for another currency: a screen of home-currency figures
        // needs no table read.
        if ($symbols === null) {
            $symbols = array();
            foreach ((array) db_items("SELECT code, symbol FROM currencies") as $row) {
                $symbols[strtoupper(trim((string) $row['code']))] = (string) $row['symbol'];
            }
        }

        if (isset($symbols[$currency_code]) && (trim($symbols[$currency_code]) !== '')) {
            $symbol = $symbols[$currency_code];
        } else {
            $suffix = ' ' . $currency_code;
        }
    }

    // An entity such as &euro; is written as the character it stands for:
    // the output is plain text that callers escape themselves.
    if (($symbol !== '') && (mb_substr($symbol, 0, 1) === '&')) {
        $symbol = html_entity_decode($symbol, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $separators = erp_number_separators();
    $output = $symbol . number_format(abs($kurus) / 100, 2, $separators['decimal'], $separators['thousands']) . $suffix;

    return (($kurus < 0) && $show_sign) ? ('-' . $output) : $output;
}
