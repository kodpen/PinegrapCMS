<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Customs data of a product (2026.11.0, 11.0.130): products.hs_code, the
 * Harmonized System code a customs declaration names the goods by, and
 * products.origin_country, the ISO 3166-1 alpha-2 code of the country the
 * goods were made in. A label service declares each order line of a parcel
 * that leaves the customs area with both; an empty value means "use the
 * shop default" of the label driver's settings.
 *
 * Both are edited in the product screen's shipping part, copied with a
 * product and carried by the product import and export. Every reader asks
 * pg_product_customs_ready() first: files land before the upgrade adds the
 * columns.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** products.hs_code is VARCHAR(16). */
define('PG_PRODUCT_HS_CODE_MAX', 16);

/**
 * Whether the upgrade has added both columns.
 *
 * @return bool
 */
function pg_product_customs_ready()
{
    return function_exists('pg_schema_has')
        && pg_schema_has('products', 'hs_code')
        && pg_schema_has('products', 'origin_country');
}

/**
 * An HS code as typed, made ready to store: '' for none, the code for a
 * valid one, false for anything else. Digits in groups split by single dots
 * ("6109.10" or "610910"), at most 16 characters: the national tariff
 * numbers (Turkey's GTİP has 12 digits, written with dots) fit, and spaces
 * around the code are dropped. A space or a letter inside is refused rather
 * than taken out, since the carrier would declare a code nobody wrote.
 *
 * @param mixed $value null is none
 * @return string|false
 */
function pg_product_hs_code_parse($value)
{
    if ($value === null) {
        return '';
    }

    if (!is_scalar($value)) {
        return false;
    }

    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if ((strlen($value) > PG_PRODUCT_HS_CODE_MAX) || (preg_match('/^[0-9]+(\.[0-9]+)*$/', $value) !== 1)) {
        return false;
    }

    return $value;
}

/**
 * The countries a product may come from: ISO 3166-1 alpha-2 code => English
 * name, the whole list rather than the shop's shipping countries, because
 * goods are made in countries a shop never ships to.
 *
 * @return array
 */
function pg_product_origin_countries()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    return pg_sales_map_iso_countries();
}

/**
 * A country of origin as typed or picked, made ready to store: '' for none,
 * the upper-case code for a known one, false for anything else.
 *
 * @param mixed $value
 * @return string|false
 */
function pg_product_origin_country_parse($value)
{
    if ($value === null) {
        return '';
    }

    if (!is_scalar($value)) {
        return false;
    }

    $value = strtoupper(trim((string) $value));

    if ($value === '') {
        return '';
    }

    if ((preg_match('/^[A-Z]{2}$/', $value) !== 1) || !array_key_exists($value, pg_product_origin_countries())) {
        return false;
    }

    return $value;
}

/**
 * The first problem of a pair of posted values, as an English text for
 * lang(), or '' when both can be stored.
 *
 * @param mixed $hs_code
 * @param mixed $origin_country
 * @return string
 */
function pg_product_customs_problem($hs_code, $origin_country)
{
    if (pg_product_hs_code_parse($hs_code) === false) {
        return 'The HS code may hold only digits and dots, up to 16 characters.';
    }

    if (pg_product_origin_country_parse($origin_country) === false) {
        return 'The country of origin is not a known country.';
    }

    return '';
}

/**
 * The two columns as a posted product form gives them, ready to store; a
 * value that cannot be stored becomes '' (the screens refuse it first with
 * pg_product_customs_problem(), so it is not seen there).
 *
 * @param array $posted
 * @return array ('hs_code' => string, 'origin_country' => string)
 */
function pg_product_customs_from_post($posted)
{
    $hs_code = pg_product_hs_code_parse(isset($posted['hs_code']) ? $posted['hs_code'] : '');
    $origin_country = pg_product_origin_country_parse(isset($posted['origin_country']) ? $posted['origin_country'] : '');

    return array(
        'hs_code'        => ($hs_code === false) ? '' : $hs_code,
        'origin_country' => ($origin_country === false) ? '' : $origin_country,
    );
}

/**
 * The options of the country of origin picker: an empty first option (the
 * shop default) and every country named in the panel language, in its
 * alphabetical order (pg_geo_country_name(), pg_geo_sort_by_label()). A
 * stored code the list does not know stays selectable, so saving the screen
 * does not drop it in silence.
 *
 * @param string $selected stored code
 * @return string HTML
 */
function pg_product_origin_country_options($selected)
{
    $selected = strtoupper(trim((string) $selected));
    $rows = array();

    foreach (pg_product_origin_countries() as $code => $name) {
        $rows[] = array('code' => (string) $code, 'label' => pg_geo_country_name($code));
    }

    if (($selected !== '') && !array_key_exists($selected, pg_product_origin_countries())) {
        $rows[] = array('code' => $selected, 'label' => $selected);
    }

    $output = '<option value="">' . h(lang('Shop default')) . '</option>';

    foreach (pg_geo_sort_by_label($rows) as $row) {
        $output .= '<option value="' . h($row['code']) . '"' . (($row['code'] === $selected) ? ' selected="selected"' : '') . '>'
            . h($row['label']) . ' (' . h($row['code']) . ')</option>';
    }

    return $output;
}

/**
 * The two columns' part of a product copy's INSERT, from the original's
 * SELECT * row: the column list and the value list, each ending in a comma,
 * or two empty strings on a site that has not run the upgrade. A stored
 * value the screen would refuse is copied as '' (the shop default).
 *
 * @param array $row
 * @return array ('columns' => string, 'values' => string)
 */
function pg_product_customs_copy_sql($row)
{
    if (!is_array($row) || !array_key_exists('hs_code', $row) || !array_key_exists('origin_country', $row)) {
        return array('columns' => '', 'values' => '');
    }

    // Read with the screen's rules, which leave digits, dots and two capital
    // letters only, so the values need no escaping.
    $hs_code = pg_product_hs_code_parse($row['hs_code']);
    $origin_country = pg_product_origin_country_parse($row['origin_country']);

    return array(
        'columns' => 'hs_code, origin_country,',
        'values'  => "'" . (($hs_code === false) ? '' : $hs_code) . "', '" . (($origin_country === false) ? '' : $origin_country) . "',",
    );
}

/**
 * One product import row with its customs cells made ready to store, or
 * false when one of them cannot be stored and the row is left out. An empty
 * cell stays empty: the import leaves a column alone for an empty cell.
 *
 * @param array $row       the CSV row
 * @param array $positions column name => position in the row
 * @return array|false
 */
function pg_product_customs_import_row($row, $positions)
{
    foreach ($positions as $column => $position) {

        if (!isset($row[$position])) {
            continue;
        }

        $value = ($column === 'hs_code')
            ? pg_product_hs_code_parse($row[$position])
            : pg_product_origin_country_parse($row[$position]);

        if ($value === false) {
            return false;
        }

        $row[$position] = $value;
    }

    return $row;
}
