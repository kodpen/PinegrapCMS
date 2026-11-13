<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The shipping zone form, shared by the create and the edit screens
 * (add_zone.php, edit_zone.php): the four cards (name, fees, countries,
 * states), the summary column and the save bar, the country and state
 * lists with the zone's own picks selected and listed first, and the
 * reading of a posted rate. The screens keep
 * what differs: the edit screen loads the zone, posts its id, offers
 * delete and rewrites the country and state links; each writes its own row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The largest rate in minor units: the rate columns are INT UNSIGNED and a
// larger amount would be clamped by MySQL without an error.
if (!defined('PG_ZONE_FORM_RATE_MAX')) {
    define('PG_ZONE_FORM_RATE_MAX', 4294967295);
}

/**
 * A posted rate in minor units: the amount as typed (group separators and
 * spaces allowed) times 100, rounded. Anything that is not a number is 0.
 *
 * @param mixed $value
 * @return int
 */
function pg_zone_form_rate($value)
{
    $amount = str_replace(' ', '', pg_normalize_amount(is_scalar($value) ? $value : ''));

    if (!is_numeric($amount)) {
        return 0;
    }

    return (int) round($amount * 100);
}

/**
 * The posted form as the screens redraw it: the name trimmed, the rates as
 * typed (pg_zone_form_rate() reads them on the write) and the chosen
 * country and state ids. A missing or non-scalar value is empty.
 *
 * @param array $post The posted form ($_POST)
 * @return array  'name', the four rates, 'allowed_countries', 'allowed_states'
 */
function pg_zone_form_post_values($post)
{
    $values = array();

    foreach (array('name', 'base_rate', 'primary_weight_rate', 'secondary_weight_rate', 'item_rate') as $key) {
        $values[$key] = (isset($post[$key]) && is_scalar($post[$key])) ? (string) $post[$key] : '';
    }

    $values['name'] = trim($values['name']);

    foreach (array('allowed_countries', 'allowed_states') as $key) {
        $ids = (isset($post[$key]) && is_array($post[$key])) ? $post[$key] : array();
        $values[$key] = array_values(array_map('strval', array_filter($ids, 'is_scalar')));
    }

    return $values;
}

/**
 * The message for a blank zone name, or '': a zone with no name is an empty
 * row in the zone list and in the product and shipping method pickers.
 *
 * @param string $name
 * @return string
 */
function pg_zone_form_name_error($name)
{
    return (trim((string) $name) === '') ? lang('Enter a name.') : '';
}

/**
 * The messages for the rate boxes that hold no amount of 0 or more: rate
 * field => message. The rate columns are unsigned and the save clamped a
 * negative rate to 0 without a word, and MySQL clamps a rate above
 * PG_ZONE_FORM_RATE_MAX the same way; a blank box is 0.
 *
 * @param array $values pg_zone_form_post_values()
 * @return array
 */
function pg_zone_form_rate_errors($values)
{
    $errors = array();

    foreach (pg_zone_form_rate_labels() as $rate => $label) {
        $value = preg_replace('/[\s\x{00A0}]+/u', '', (string) ($values[$rate] ?? ''));

        if (($value !== '') && (preg_match('/^[0-9]+([.,][0-9]+)*$/', $value) !== 1)) {
            $errors[$rate] = lang(array('string' => '{var:1} takes an amount, such as {var:2}.', 'vars' => array($label, pg_format_number(12.5, 2))));
        } elseif (($value !== '') && (pg_zone_form_rate($value) > PG_ZONE_FORM_RATE_MAX)) {
            $errors[$rate] = lang(array('string' => '{var:1} can be at most {var:2}.', 'vars' => array($label, pg_format_number(PG_ZONE_FORM_RATE_MAX / 100, 2))));
        }
    }

    return $errors;
}

/**
 * The zone's rate fields and their labels, in form order.
 *
 * @return array field => label
 */
function pg_zone_form_rate_labels()
{
    return array(
        'base_rate' => lang('Base Rate'),
        'primary_weight_rate' => lang('Primary Weight Rate'),
        'secondary_weight_rate' => lang('Secondary Weight Rate'),
        'item_rate' => lang('Item Rate'),
    );
}

/**
 * The options of a multiple select: the chosen items first, selected, then
 * the rest, each list in the order given.
 *
 * @param array $items        Rows with 'id' and 'label' (plain text)
 * @param array $selected_ids The ids to select
 * @return string  HTML
 */
function pg_zone_form_option_list($items, $selected_ids)
{
    $selected_ids = array_map('strval', $selected_ids);
    $output_selected = '';
    $output_others = '';

    foreach ($items as $item) {
        if (in_array((string) $item['id'], $selected_ids, true)) {
            $output_selected .= '<option selected="selected" value="' . h($item['id']) . '">' . h($item['label']) . '</option>';
        } else {
            $output_others .= '<option value="' . h($item['id']) . '">' . h($item['label']) . '</option>';
        }
    }

    return $output_selected . $output_others;
}

/**
 * The country and state options of the form, a zone's own links selected,
 * or the given ids when a refused post is drawn again.
 *
 * @param int        $zone_id  The zone being edited, 0 for a new one
 * @param array|null $selected 'allowed_countries' and 'allowed_states' ids
 *                             to select instead of the zone's links
 * @return array  'countries' and 'states' (HTML), 'country_count' and
 *                'state_count' (how many are chosen)
 */
function pg_zone_form_choices($zone_id = 0, $selected = null)
{
    $countries = array();
    $states = array();
    $zone_country_ids = array();
    $zone_state_ids = array();

    $result = mysqli_query(db::$con, "SELECT id, name, code FROM countries") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $countries[] = array('id' => $row['id'], 'label' => pg_geo_country_label($row['name'], $row['code']));
    }

    // Named and ordered in the panel's language (pg_geo_country_label()).
    $countries = pg_geo_sort_by_label($countries);

    // States under their country's name in the panel's language, ordered
    // by it; the query's state name order is kept within each country.
    $result = mysqli_query(db::$con,
        "SELECT states.id, states.name, countries.name as country_name, countries.code as country_code
        FROM states
        LEFT JOIN countries ON countries.id = states.country_id
        ORDER BY states.name") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $country_label = ($row['country_name'] !== null) ? pg_geo_country_label($row['country_name'], $row['country_code']) : '';
        $states[] = array('id' => $row['id'], 'country' => $country_label, 'label' => $country_label . ': ' . $row['name']);
    }

    $states = pg_geo_sort_by_label($states, 'country');

    if ($selected !== null) {
        $zone_country_ids = (array) ($selected['allowed_countries'] ?? array());
        $zone_state_ids = (array) ($selected['allowed_states'] ?? array());
    } elseif ($zone_id > 0) {
        $result = db_q("SELECT country_id FROM zones_countries_xref WHERE zone_id = ?", array((string) $zone_id)) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $zone_country_ids[] = $row['country_id'];
        }

        $result = db_q("SELECT state_id FROM zones_states_xref WHERE zone_id = ?", array((string) $zone_id)) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $zone_state_ids[] = $row['state_id'];
        }
    }

    return array(
        'countries' => pg_zone_form_option_list($countries, $zone_country_ids),
        'states' => pg_zone_form_option_list($states, $zone_state_ids),
        'country_count' => pg_zone_form_selected_count($countries, $zone_country_ids),
        'state_count' => pg_zone_form_selected_count($states, $zone_state_ids),
    );
}

/**
 * How many of the given items are chosen.
 *
 * @param array $items        Rows with 'id'
 * @param array $selected_ids
 * @return int
 */
function pg_zone_form_selected_count($items, $selected_ids)
{
    $selected_ids = array_map('strval', $selected_ids);
    $count = 0;

    foreach ($items as $item) {
        if (in_array((string) $item['id'], $selected_ids, true)) {
            $count++;
        }
    }

    return $count;
}

/**
 * The form column (name, fees, countries and states cards) and the sticky
 * status column (the summary card) of the edit screen layout
 * (ui/edit-screen-wide-layout.md); pg_zone_form_layout() wraps them in their
 * row with the save bar.
 *
 * @param array  $values          'name', 'base_rate', 'primary_weight_rate',
 *                                'secondary_weight_rate', 'item_rate' (plain text)
 * @param string $country_options pg_zone_form_choices()['countries']
 * @param string $state_options   pg_zone_form_choices()['states']
 * @param array  $errors          Field => message; 'name' and each rate are
 *                                printed under their field
 * @param array  $summary         'countries', 'states' (chosen counts,
 *                                pg_zone_form_choices()), and on edit
 *                                'products', 'methods' (the zone's users)
 * @return string  HTML
 */
function pg_zone_form_cards($values, $country_options, $state_options, $errors = array(), $summary = array())
{
    $name_error = isset($errors['name']) ? (string) $errors['name'] : '';
    $name_class = ($name_error !== '') ? ' is-invalid' : '';
    $name_aria = ($name_error !== '') ? ' aria-invalid="true" aria-describedby="name-error"' : '';
    $name_feedback = ($name_error !== '') ? '
                                            <div id="name-error" class="invalid-feedback d-block">' . h($name_error) . '</div>' : '';

    $output_rates = '';

    foreach (pg_zone_form_rate_labels() as $rate => $label) {
        $rate_error = isset($errors[$rate]) ? (string) $errors[$rate] : '';

        $output_rates .= '
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="' . $rate . '" class="form-label">' . $label . '</label>
                                            <label class="form-control form-adorn form-adorn-end' . (($rate_error !== '') ? ' is-invalid' : '') . '">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values[$rate]) . '" type="text" name="' . $rate . '" id="' . $rate . '" class="form-ghost text-end" inputmode="decimal"' . pg_money_mask_html() . ' data-inputmask-allowMinus="false" data-inputmask-max="' . h(PG_ZONE_FORM_RATE_MAX / 100) . '"' . (($rate_error !== '') ? ' aria-invalid="true" aria-describedby="' . $rate . '-error"' : '') . ' />
                                            </label>' . (($rate_error !== '') ? '
                                            <div id="' . $rate . '-error" class="invalid-feedback d-block">' . h($rate_error) . '</div>' : '') . '
                                        </div>';
    }

    // What the zone reaches, read only: the chosen countries and states
    // (kept current as they are picked) and, on edit, what uses the zone.
    $summary_rows = array(
        array('zone_country_count', lang('Countries'), (int) ($summary['countries'] ?? 0)),
        array('zone_state_count', lang('States'), (int) ($summary['states'] ?? 0)),
    );

    if (isset($summary['products'])) {
        $summary_rows[] = array('', lang('Products'), (int) $summary['products']);
        $summary_rows[] = array('', lang('Shipping Methods'), (int) ($summary['methods'] ?? 0));
    }

    $output_summary = '';

    foreach ($summary_rows as $row) {
        $output_summary .= '
                                            <div class="d-flex justify-content-between py-2 border-bottom">
                                                <dt class="fw-normal">' . $row[1] . '</dt>
                                                <dd class="mb-0 fw-bold"' . ($row[0] !== '' ? ' id="' . $row[0] . '"' : '') . '>' . $row[2] . '</dd>
                                            </div>';
    }

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Zone Name') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Shipping Zone Name') . '</label>
                                            <input value="' . h($values['name']) . '" type="text" name="name" placeholder="' . lang('Zone Name') . '" id="name" maxlength="50" class="form-control' . $name_class . '" required' . $name_aria . ' />' . $name_feedback . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Shipping Zone Fees') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">' . $output_rates . '
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 mb-7">
                                    <div class="card h-100">
                                        <h2 class="card-header bg-reset border-0 pg-title-section">
                                            ' . lang('Shipping Zone Countries') . '
                                        </h2>
                                        <div class="card-body pt-0">
                                            <label for="allowed_countries" class="form-label mt-3">' . lang('Allowed Countries') . '</label>
                                            <select data-pg-combobox class="form-control w-100" data-placeholder="' . lang('Click to select country(s)') . '" id="allowed_countries" name="allowed_countries[]" multiple="multiple">' . $country_options . '</select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 mb-7">
                                    <div class="card h-100">
                                        <h2 class="card-header bg-reset border-0 pg-title-section">
                                            ' . lang('Shipping Zone States or Provinces') . '
                                        </h2>
                                        <div class="card-body pt-0">
                                            <label for="allowed_states" class="form-label mt-3">' . lang('Allowed States') . '</label>
                                            <select data-pg-combobox class="form-control w-100" data-placeholder="' . lang('Click to select state(s)') . '" id="allowed_states" name="allowed_states[]" multiple="multiple">' . $state_options . '</select>
                                        </div>
                                    </div>
                                </div>
                            </div>' . pg_fields_script() . '
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . $output_summary . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            $(document).on(\'change\', \'#allowed_countries, #allowed_states\', function () {
                                $(this.id === \'allowed_countries\' ? \'#zone_country_count\' : \'#zone_state_count\').text($(this).find(\'option:selected\').length);
                            });
                        </script>';
}

/**
 * The screen row: the cards (pg_zone_form_cards()) and the sticky save bar
 * with the given buttons last.
 *
 * @param string $cards   pg_zone_form_cards()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_zone_form_layout($cards, $buttons)
{
    return '
                    <div class="row">' . $cards . '
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">' . $buttons . '
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>';
}

/**
 * What a zone delete unlinks: the products and shipping methods that use
 * the zone, and how many of them have no other zone. Checkout intersects
 * the allowed zones of the cart's shippable products and offers a method
 * only through its zones, so a shippable product or a method left with no
 * zone can no longer ship.
 *
 * @param int $zone_id
 * @return array list of (products, methods, stranded products, stranded methods)
 */
function pg_zone_form_delete_counts($zone_id)
{
    $first = function ($result) {
        $row = $result ? mysqli_fetch_row($result) : null;

        return $row ? (int) $row[0] : 0;
    };
    $zone_id = (int) $zone_id;

    return array(
        $first(db_q("SELECT COUNT(DISTINCT product_id) FROM products_zones_xref WHERE zone_id = ?", array($zone_id))),
        $first(db_q("SELECT COUNT(DISTINCT shipping_method_id) FROM shipping_methods_zones_xref WHERE zone_id = ?", array($zone_id))),
        $first(db_q("SELECT COUNT(DISTINCT x.product_id) FROM products_zones_xref x
            JOIN products p ON p.id = x.product_id AND p.shippable = 1
            WHERE x.zone_id = ?
            AND NOT EXISTS (SELECT 1 FROM products_zones_xref o WHERE o.product_id = x.product_id AND o.zone_id != ?)", array($zone_id, $zone_id))),
        $first(db_q("SELECT COUNT(DISTINCT x.shipping_method_id) FROM shipping_methods_zones_xref x
            WHERE x.zone_id = ?
            AND NOT EXISTS (SELECT 1 FROM shipping_methods_zones_xref o WHERE o.shipping_method_id = x.shipping_method_id AND o.zone_id != ?)", array($zone_id, $zone_id))),
    );
}

/**
 * The Delete confirmation of Edit Zone as lang() arguments: the products
 * and shipping methods losing the zone, then those left with no zone.
 *
 * @param int $products
 * @param int $methods
 * @param int $stranded_products shippable products with no other zone
 * @param int $stranded_methods  methods with no other zone
 * @return array list of lang() arguments, one per sentence
 */
function pg_zone_form_delete_confirm($products, $methods, $stranded_products, $stranded_methods)
{
    $products = (int) $products;
    $methods = (int) $methods;
    $stranded_products = (int) $stranded_products;
    $stranded_methods = (int) $stranded_methods;

    if ($products > 0 && $methods > 0) {
        $sentences = array(array(
            'string' => 'WARNING: This zone will be permanently deleted and removed from {var:1} product{suffix:1} and {var:2} shipping method{suffix:2}.',
            'vars' => array($products, $methods),
            'suffix' => array($products == 1 ? '' : 's', $methods == 1 ? '' : 's'),
        ));
    } elseif ($products > 0) {
        $sentences = array(array(
            'string' => 'WARNING: This zone will be permanently deleted and removed from {var:1} product{suffix:1}.',
            'vars' => array($products),
            'suffix' => array($products == 1 ? '' : 's'),
        ));
    } elseif ($methods > 0) {
        $sentences = array(array(
            'string' => 'WARNING: This zone will be permanently deleted and removed from {var:1} shipping method{suffix:1}.',
            'vars' => array($methods),
            'suffix' => array($methods == 1 ? '' : 's'),
        ));
    } else {
        return array(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('zone'))));
    }

    if ($stranded_products > 0 && $stranded_methods > 0) {
        $sentences[] = array(
            'string' => '{var:1} product{suffix:1} will have no other zone and cannot be shipped, and {var:2} shipping method{suffix:2} will have no other zone and will never be offered.',
            'vars' => array($stranded_products, $stranded_methods),
            'suffix' => array($stranded_products == 1 ? '' : 's', $stranded_methods == 1 ? '' : 's'),
        );
    } elseif ($stranded_products > 0) {
        $sentences[] = array(
            'string' => '{var:1} product{suffix:1} will have no other zone and cannot be shipped.',
            'vars' => array($stranded_products),
            'suffix' => array($stranded_products == 1 ? '' : 's'),
        );
    } elseif ($stranded_methods > 0) {
        $sentences[] = array(
            'string' => '{var:1} shipping method{suffix:1} will have no other zone and will never be offered.',
            'vars' => array($stranded_methods),
            'suffix' => array($stranded_methods == 1 ? '' : 's'),
        );
    }

    return $sentences;
}

/**
 * What deleting a state or a country does to the shipping zones and tax
 * zones: how many list it (a country also through its states, which go
 * with it), and how many of those keep no other state or country. A zone
 * with no member matches no address, so its methods are never offered; a
 * tax zone with no member charges no tax.
 *
 * @param string $kind 'state' or 'country'
 * @param int    $id   the state's or the country's id
 * @return array list of (zones, tax zones, emptied zones, emptied tax zones)
 */
function pg_zone_form_member_delete_counts($kind, $id)
{
    // A state delete takes that state's rows; a country delete takes the
    // country's rows and the rows of its states. Ids start at 1, so 0
    // matches nothing.
    $state_id = $kind == 'country' ? 0 : (int) $id;
    $country_id = $kind == 'country' ? (int) $id : 0;
    $counts = array();

    foreach (array(
        array('zones_states_xref', 'zones_countries_xref', 'zone_id'),
        array('tax_zones_states_xref', 'tax_zones_countries_xref', 'tax_zone_id'),
    ) as $tables) {
        list($states, $countries, $zone) = $tables;
        $result = db_q("SELECT COUNT(*) FROM (SELECT s.$zone AS zone FROM $states s
                WHERE s.state_id = ? OR s.state_id IN (SELECT id FROM states WHERE country_id = ?)
            UNION SELECT c.$zone AS zone FROM $countries c WHERE c.country_id = ?) linked", array($state_id, $country_id, $country_id));
        $row = $result ? mysqli_fetch_row($result) : null;
        $counts[] = $row ? (int) $row[0] : 0;

        $result = db_q("SELECT COUNT(*) FROM (SELECT s.$zone AS zone FROM $states s
                WHERE s.state_id = ? OR s.state_id IN (SELECT id FROM states WHERE country_id = ?)
            UNION SELECT c.$zone AS zone FROM $countries c WHERE c.country_id = ?) linked
            WHERE NOT EXISTS (SELECT 1 FROM $states s WHERE s.$zone = linked.zone
                AND s.state_id != ? AND s.state_id NOT IN (SELECT id FROM states WHERE country_id = ?))
            AND NOT EXISTS (SELECT 1 FROM $countries c WHERE c.$zone = linked.zone AND c.country_id != ?)", array($state_id, $country_id, $country_id, $state_id, $country_id, $country_id));
        $row = $result ? mysqli_fetch_row($result) : null;
        $counts[] = $row ? (int) $row[0] : 0;
    }

    return array($counts[0], $counts[2], $counts[1], $counts[3]);
}

/**
 * The sentences a state or country Delete confirmation adds as lang()
 * arguments: the shipping zones and tax zones it leaves, then those left
 * with no state or country. Nothing when it is in no zone.
 *
 * @param int $zones
 * @param int $tax_zones
 * @param int $emptied_zones     shipping zones left with no member
 * @param int $emptied_tax_zones tax zones left with no member
 * @return array list of lang() arguments, one per sentence
 */
function pg_zone_form_member_delete_sentences($zones, $tax_zones, $emptied_zones, $emptied_tax_zones)
{
    $zones = (int) $zones;
    $tax_zones = (int) $tax_zones;
    $emptied_zones = (int) $emptied_zones;
    $emptied_tax_zones = (int) $emptied_tax_zones;
    $sentences = array();

    if ($zones > 0 && $tax_zones > 0) {
        $sentences[] = array(
            'string' => 'It will also be removed from {var:1} shipping zone{suffix:1} and {var:2} tax zone{suffix:2}.',
            'vars' => array($zones, $tax_zones),
            'suffix' => array($zones == 1 ? '' : 's', $tax_zones == 1 ? '' : 's'),
        );
    } elseif ($zones > 0) {
        $sentences[] = array(
            'string' => 'It will also be removed from {var:1} shipping zone{suffix:1}.',
            'vars' => array($zones),
            'suffix' => array($zones == 1 ? '' : 's'),
        );
    } elseif ($tax_zones > 0) {
        $sentences[] = array(
            'string' => 'It will also be removed from {var:1} tax zone{suffix:1}.',
            'vars' => array($tax_zones),
            'suffix' => array($tax_zones == 1 ? '' : 's'),
        );
    }

    if ($emptied_zones > 0 && $emptied_tax_zones > 0) {
        $sentences[] = array(
            'string' => '{var:1} shipping zone{suffix:1} will have no state or country left and will match no address, and {var:2} tax zone{suffix:2} will have no state or country left and will charge no tax.',
            'vars' => array($emptied_zones, $emptied_tax_zones),
            'suffix' => array($emptied_zones == 1 ? '' : 's', $emptied_tax_zones == 1 ? '' : 's'),
        );
    } elseif ($emptied_zones > 0) {
        $sentences[] = array(
            'string' => '{var:1} shipping zone{suffix:1} will have no state or country left and will match no address.',
            'vars' => array($emptied_zones),
            'suffix' => array($emptied_zones == 1 ? '' : 's'),
        );
    } elseif ($emptied_tax_zones > 0) {
        $sentences[] = array(
            'string' => '{var:1} tax zone{suffix:1} will have no state or country left and will charge no tax.',
            'vars' => array($emptied_tax_zones),
            'suffix' => array($emptied_tax_zones == 1 ? '' : 's'),
        );
    }

    return $sentences;
}
