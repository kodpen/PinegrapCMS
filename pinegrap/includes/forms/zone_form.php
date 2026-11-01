<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The shipping zone form, shared by the create and the edit screens
 * (add_zone.php, edit_zone.php): the four cards (name, fees, countries,
 * states), the country and state lists with the zone's own picks selected
 * and listed first, and the reading of a posted rate. The screens keep
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
 * @return array  'countries' and 'states' (HTML)
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
    );
}

/**
 * The form's four cards, from the opening row to its closing tag; the
 * screens print the form tag, the buttons and the edit screen's id around it.
 *
 * @param array  $values          'name', 'base_rate', 'primary_weight_rate',
 *                                'secondary_weight_rate', 'item_rate' (plain text)
 * @param string $country_options pg_zone_form_choices()['countries']
 * @param string $state_options   pg_zone_form_choices()['states']
 * @param array  $errors          Field => message; 'name' is printed under the name field
 * @return string  HTML
 */
function pg_zone_form_cards($values, $country_options, $state_options, $errors = array())
{
    $name_error = isset($errors['name']) ? (string) $errors['name'] : '';
    $name_class = ($name_error !== '') ? ' is-invalid' : '';
    $name_aria = ($name_error !== '') ? ' aria-invalid="true" aria-describedby="name-error"' : '';
    $name_feedback = ($name_error !== '') ? '
                                            <div id="name-error" class="invalid-feedback d-block">' . h($name_error) . '</div>' : '';

    return '
                    <div class="row">
                        <div class="col-12 md:col-6 mb-12">
                            <div class="card my-7 h-100">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Zone Name') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Shipping Zone Name') . '</label>
                                            <input value="' . h($values['name']) . '" type="text" name="name" placeholder="' . lang('Zone Name') . '" id="name" maxlength="50" class="form-control' . $name_class . '" required' . $name_aria . ' />' . $name_feedback . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-6 mb-12">
                            <div class="card my-7 h-100">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shipping Zone Fees') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="base_rate" class="form-label">' . lang('Base Rate') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values['base_rate']) . '" type="text" name="base_rate" id="base_rate" class="form-ghost" maxlength="12" inputmode="decimal"' . pg_money_mask_html() . '  style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="primary_weight_rate" class="form-label">' . lang('Primary Weight Rate') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values['primary_weight_rate']) . '" type="text" name="primary_weight_rate" id="primary_weight_rate" class="form-ghost" maxlength="12" inputmode="decimal"' . pg_money_mask_html() . '  style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="secondary_weight_rate" class="form-label">' . lang('Secondary Weight Rate') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values['secondary_weight_rate']) . '" type="text" name="secondary_weight_rate" id="secondary_weight_rate" class="form-ghost" maxlength="12" inputmode="decimal"' . pg_money_mask_html() . '  style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="item_rate" class="form-label">' . lang('Item Rate') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values['item_rate']) . '" type="text" name="item_rate" id="item_rate" class="form-ghost" maxlength="12" inputmode="decimal"' . pg_money_mask_html() . '  style="text-align: right;" />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-6 mb-12">
                            <div class="card my-7 h-100">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shipping Zone Countries') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <label for="allowed_countries" class="form-label">' . lang('Allowed Countries') . '</label>
                                            <select style="width:100%" data-pg-combobox class="form-control" data-placeholder="' . lang('Click to select country(s)') . '" id="allowed_countries" name="allowed_countries[]" multiple="multiple">' . $country_options . '</select>' . pg_fields_script() . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 md:col-6 mb-12">
                            <div class="card my-7 h-100">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Shipping Zone States or Provinces') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <label for="allowed_states" class="form-label">' . lang('Allowed States') . '</label>
                                            <select style="width:100%" data-pg-combobox class="form-control" data-placeholder="' . lang('Click to select state(s)') . '" id="allowed_states" name="allowed_states[]" multiple="multiple">' . $state_options . '</select>' . pg_fields_script() . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
';
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
