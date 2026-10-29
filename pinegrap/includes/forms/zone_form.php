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
 * The country and state options of the form, a zone's own links selected.
 *
 * @param int $zone_id The zone being edited, 0 for a new one
 * @return array  'countries' and 'states' (HTML)
 */
function pg_zone_form_choices($zone_id = 0)
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

    $result = mysqli_query(db::$con,
        "SELECT states.id, states.name, countries.name as country_name
        FROM states
        LEFT JOIN countries ON countries.id = states.country_id
        ORDER BY countries.name, states.name") or output_error('Query failed.');

    while ($row = mysqli_fetch_assoc($result)) {
        $states[] = array('id' => $row['id'], 'label' => $row['country_name'] . ': ' . $row['name']);
    }

    if ($zone_id > 0) {
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
 * @return string  HTML
 */
function pg_zone_form_cards($values, $country_options, $state_options)
{
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
                                            <input value="' . h($values['name']) . '" type="text" name="name" placeholder="' . lang('Zone Name') . '" id="name" maxlength="50" class="form-control" />
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
                                                <input value="' . h($values['base_rate']) . '" type="text" name="base_rate" id="base_rate" class="form-ghost" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="primary_weight_rate" class="form-label">' . lang('Primary Weight Rate') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values['primary_weight_rate']) . '" type="text" name="primary_weight_rate" id="primary_weight_rate" class="form-ghost" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="secondary_weight_rate" class="form-label">' . lang('Secondary Weight Rate') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values['secondary_weight_rate']) . '" type="text" name="secondary_weight_rate" id="secondary_weight_rate" class="form-ghost" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
                                            </label>
                                        </div>
                                        <div class="col-12 lg:col-6 my-3">
                                            <label for="item_rate" class="form-label">' . lang('Item Rate') . '</label>
                                            <label class="form-control form-adorn form-adorn-end">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input value="' . h($values['item_rate']) . '" type="text" name="item_rate" id="item_rate" class="form-ghost" maxlength="12" inputmode="numeric"' . pg_money_mask_html() . '  style="text-align: right;" />
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
