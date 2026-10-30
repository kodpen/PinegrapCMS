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

/* ---------------------------------------------------------------------------
   Customs declaration of a carrier label
   --------------------------------------------------------------------------- */

/**
 * The member states of the EU customs union: goods move between them with
 * no declaration.
 *
 * @return string[]
 */
function pg_label_customs_eu_countries()
{
    return array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE',
        'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    );
}

/**
 * Whether a parcel between two countries needs a customs declaration: every
 * route that crosses a border, except one within the EU customs union. A
 * route with a country missing needs none here; the driver refuses it for
 * the missing country. Pure.
 *
 * @param string $from ISO 3166-1 alpha-2
 * @param string $to
 * @return bool
 */
function pg_label_customs_needed($from, $to)
{
    $from = strtoupper(trim((string) $from));
    $to = strtoupper(trim((string) $to));

    if (($from === '') || ($to === '') || ($from === $to)) {
        return false;
    }

    $eu = pg_label_customs_eu_countries();

    return !(in_array($from, $eu, true) && in_array($to, $eu, true));
}

/**
 * The shop-defaults block of a label driver's settings, in the field shape
 * of pg_payment_fields_normalize(): what a declaration says beside its
 * lines, and the HS code and country of origin of a product that has none.
 * Values are carrier-neutral; each driver maps them to its service's words.
 *
 * @return array
 */
function pg_label_customs_fields()
{
    $countries = array('' => lang('None'));
    $rows = array();

    foreach (pg_product_origin_countries() as $code => $name) {
        $rows[] = array('code' => (string) $code, 'label' => pg_geo_country_name($code));
    }

    foreach (pg_geo_sort_by_label($rows) as $row) {
        $countries[$row['code']] = $row['label'] . ' (' . $row['code'] . ')';
    }

    return array(
        array(
            'name'    => 'customs_contents',
            'type'    => 'select',
            'label'   => lang('Customs: Contents Type'),
            'help'    => lang('Declared for parcels that leave the customs area, such as from Turkey or the EU to the US or Great Britain.'),
            'options' => array(
                'merchandise' => lang('Merchandise'),
                'gift'        => lang('Gift'),
                'sample'      => lang('Sample'),
                'documents'   => lang('Documents'),
                'return'      => lang('Returned goods'),
            ),
            'default' => 'merchandise',
        ),
        array(
            'name'    => 'customs_incoterm',
            'type'    => 'select',
            'label'   => lang('Customs: Incoterm'),
            'help'    => lang('Who pays duties and import taxes. DDU and DAP: the recipient. DDP: the shop, where the carrier account offers it.'),
            'options' => array('DDU' => 'DDU', 'DAP' => 'DAP', 'DDP' => 'DDP'),
            'default' => 'DDU',
        ),
        array(
            'name'    => 'customs_non_delivery',
            'type'    => 'select',
            'label'   => lang('Customs: If Undeliverable'),
            'options' => array('return' => lang('Return to the sender'), 'abandon' => lang('Abandon')),
            'default' => 'return',
        ),
        array(
            'name'       => 'customs_hs_code',
            'label'      => lang('Customs: Default HS Code'),
            'help'       => lang('Used for a product with no HS code of its own. With neither, a parcel that needs customs gets no label.'),
            'max_length' => PG_PRODUCT_HS_CODE_MAX,
        ),
        array(
            'name'    => 'customs_origin_country',
            'type'    => 'select',
            'label'   => lang('Customs: Default Country of Origin'),
            'help'    => lang('Used for a product with no country of origin of its own.'),
            'options' => $countries,
            'default' => '',
        ),
        array(
            'name'       => 'customs_signer',
            'label'      => lang('Customs: Signer Name'),
            'help'       => lang('The person who certifies the declaration. Empty uses the sender name.'),
            'max_length' => 100,
        ),
    );
}

/**
 * The block's values the settings form refuses: field name => sentence.
 * Pure.
 *
 * @param array $values field => text
 * @return array
 */
function pg_label_customs_settings_problems($values)
{
    $problems = array();

    if (isset($values['customs_hs_code']) && (pg_product_hs_code_parse($values['customs_hs_code']) === false)) {
        $problems['customs_hs_code'] = lang('The HS code may hold only digits and dots, up to 16 characters.');
    }

    if (isset($values['customs_origin_country']) && (pg_product_origin_country_parse($values['customs_origin_country']) === false)) {
        $problems['customs_origin_country'] = lang('The country of origin is not a known country.');
    }

    return $problems;
}

/**
 * Whether a shop declares customs: a default of the block or a line's own
 * HS code or country of origin is filled. Until then a label driver sends
 * what it sent before the block existed. Pure.
 *
 * @param array $goods   the shipment's goods (pg_label_customs_lines())
 * @param array $account the driver's account
 * @return bool
 */
function pg_label_customs_in_use($goods, $account)
{
    foreach (array('customs_hs_code', 'customs_origin_country', 'customs_signer') as $name) {
        if (isset($account[$name]) && is_scalar($account[$name]) && (trim((string) $account[$name]) !== '')) {
            return true;
        }
    }

    foreach (is_array($goods) ? $goods : array() as $line) {
        foreach (array('hs_code', 'origin_country') as $name) {
            if (is_array($line) && isset($line[$name]) && is_scalar($line[$name]) && (trim((string) $line[$name]) !== '')) {
                return true;
            }
        }
    }

    return false;
}

/**
 * The declared lines of a parcel. Each order line is declared with its
 * product name, quantity, unit price in the order's currency (whole minor
 * units) and unit weight; its HS code and country of origin are the
 * product's, else the shop default. A line with no HS code or country of
 * origin anywhere refuses the declaration with its reason: no code is
 * guessed. A line with no weight shares what the parcel weighs beyond the
 * lines that have one, at least 10 g a unit. Pure.
 *
 * @param array $goods         list of name, quantity, price (minor units),
 *                             weight (kg a unit), hs_code, origin_country
 * @param array $defaults      hs_code, origin_country of the shop
 * @param float $parcel_weight kg, 0 when unknown
 * @return array ok, error (translated, '' when ok), lines (list of name,
 *               quantity, unit_price, unit_weight, hs_code, origin_country)
 */
function pg_label_customs_lines($goods, $defaults, $parcel_weight = 0.0)
{
    $value = function ($source, $name) {
        return (is_array($source) && isset($source[$name]) && is_scalar($source[$name])) ? (string) $source[$name] : '';
    };

    $default_hs = pg_product_hs_code_parse($value($defaults, 'hs_code'));
    $default_origin = pg_product_origin_country_parse($value($defaults, 'origin_country'));
    $lines = array();
    $known = 0.0;
    $weightless = 0;

    foreach (is_array($goods) ? $goods : array() as $line) {
        $name = trim(preg_replace('/\s+/u', ' ', $value($line, 'name')));
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 100, 'UTF-8') : substr($name, 0, 100);
        $hs_code = pg_product_hs_code_parse($value($line, 'hs_code'));
        $origin = pg_product_origin_country_parse($value($line, 'origin_country'));
        $hs_code = (($hs_code === false) || ($hs_code === '')) ? $default_hs : $hs_code;
        $origin = (($origin === false) || ($origin === '')) ? $default_origin : $origin;
        $label = ($name === '') ? '?' : $name;

        if (($hs_code === false) || ($hs_code === '')) {
            return array('ok' => false, 'lines' => array(), 'error' => lang(array(
                'string' => '{var:1} has no HS code, neither on the product nor as the shop default of the label service.',
                'vars'   => array($label),
            )));
        }

        if (($origin === false) || ($origin === '')) {
            return array('ok' => false, 'lines' => array(), 'error' => lang(array(
                'string' => '{var:1} has no country of origin, neither on the product nor as the shop default of the label service.',
                'vars'   => array($label),
            )));
        }

        if ($name === '') {
            return array('ok' => false, 'lines' => array(), 'error' => lang('A line of the parcel has no product name to declare.'));
        }

        $quantity = max(1, (int) $value($line, 'quantity'));
        $price = $value($line, 'price');
        $weight = $value($line, 'weight');
        $weight = (is_numeric($weight) && ((float) $weight > 0)) ? round((float) $weight, 3) : 0.0;

        if ($weight > 0) {
            $known += $weight * $quantity;
        } else {
            $weightless += $quantity;
        }

        $lines[] = array(
            'name'           => $name,
            'quantity'       => $quantity,
            'unit_price'     => is_numeric($price) ? max(0, (int) round((float) $price)) : 0,
            'unit_weight'    => $weight,
            'hs_code'        => $hs_code,
            'origin_country' => $origin,
        );
    }

    if (!$lines) {
        return array('ok' => false, 'lines' => array(), 'error' => lang('The parcel has no line to declare to customs.'));
    }

    if ($weightless > 0) {
        $share = max(0.01, round(max(0.0, (float) $parcel_weight - $known) / $weightless, 3));

        foreach ($lines as $index => $line) {
            if ($line['unit_weight'] <= 0) {
                $lines[$index]['unit_weight'] = $share;
            }
        }
    }

    return array('ok' => true, 'error' => '', 'lines' => $lines);
}
