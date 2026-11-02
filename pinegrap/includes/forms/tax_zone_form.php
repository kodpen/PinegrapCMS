<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The tax zone form, shared by the create and the edit screens
 * (add_tax_zone.php, edit_tax_zone.php): the three cards (options,
 * countries, states), the country and state lists with the zone's own picks
 * selected, the posted values and the blank name check of a refused save,
 * and the writing of the posted country and state links. The screens keep
 * what differs: the edit screen loads the zone, posts its id, offers delete
 * and clears the old links first; each writes its own row.
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
 * The options of a multiple select in the order given, the chosen ones
 * selected.
 *
 * @param array $items        Rows with 'id' and 'label' (plain text)
 * @param array $selected_ids The ids to select
 * @return string  HTML
 */
function pg_tax_zone_form_option_list($items, $selected_ids)
{
    $selected_ids = array_map('strval', $selected_ids);
    $output = '';

    foreach ($items as $item) {
        $selected = in_array((string) $item['id'], $selected_ids, true) ? ' selected="selected"' : '';
        $output .= '<option value="' . h($item['id']) . '"' . $selected . '>' . h($item['label']) . '</option>';
    }

    return $output;
}

/**
 * The country or state ids of a posted multiple select: the non-empty
 * values as text. Anything but a list of values gives none.
 *
 * @param mixed $posted
 * @return array
 */
function pg_tax_zone_form_link_ids($posted)
{
    $ids = array();

    if (!is_array($posted)) {
        return $ids;
    }

    foreach ($posted as $id) {
        if (is_scalar($id) && $id) {
            $ids[] = (string) $id;
        }
    }

    return $ids;
}

/**
 * The posted form as the screens redraw it: the name trimmed, the rate as
 * typed (parse_tax_rate() reads it on the write) and the chosen country and
 * state ids. A missing or non-scalar value is empty.
 *
 * @param array $post The posted form ($_POST)
 * @return array  'name', 'tax_rate', 'allowed_countries', 'allowed_states'
 */
function pg_tax_zone_form_post_values($post)
{
    $values = array();

    foreach (array('name', 'tax_rate') as $key) {
        $values[$key] = (isset($post[$key]) && is_scalar($post[$key])) ? (string) $post[$key] : '';
    }

    $values['name'] = trim($values['name']);
    $values['allowed_countries'] = pg_tax_zone_form_link_ids($post['allowed_countries'] ?? null);
    $values['allowed_states'] = pg_tax_zone_form_link_ids($post['allowed_states'] ?? null);

    return $values;
}

/**
 * The message for a blank tax zone name, or '': a zone with no name is an
 * empty row in All Tax Zones.
 *
 * @param string $name
 * @return string
 */
function pg_tax_zone_form_name_error($name)
{
    return (trim((string) $name) === '') ? lang('Enter a name.') : '';
}

/**
 * The state items of the form from rows in state name order with 'id',
 * 'name', 'country_name' and 'country_code' (null for a state without a
 * country): "Country: State" with the country named in the panel's
 * language (pg_geo_country_label()), ordered by that name, the given state
 * order kept within each country.
 *
 * @param array $rows
 * @return array  Rows with 'id', 'country' and 'label' (plain text)
 */
function pg_tax_zone_form_state_items($rows)
{
    $states = array();

    foreach ($rows as $row) {
        $country_label = isset($row['country_name']) ? pg_geo_country_label($row['country_name'], (string) ($row['country_code'] ?? '')) : '';
        $states[] = array('id' => $row['id'], 'country' => $country_label, 'label' => $country_label . ': ' . $row['name']);
    }

    return pg_geo_sort_by_label($states, 'country');
}

/**
 * The country and state options of the form, a tax zone's own links
 * selected, or the given ids when a refused post is drawn again.
 *
 * @param int        $tax_zone_id The zone being edited, 0 for a new one
 * @param array|null $selected    'allowed_countries' and 'allowed_states'
 *                                ids to select instead of the zone's links
 * @return array  'countries' and 'states' (HTML)
 */
function pg_tax_zone_form_choices($tax_zone_id = 0, $selected = null)
{
    $countries = array();
    $states = array();
    $zone_country_ids = array();
    $zone_state_ids = array();

    foreach (db_items("SELECT id, name, code FROM countries") as $row) {
        $countries[] = array('id' => $row['id'], 'label' => pg_geo_country_label($row['name'], $row['code']));
    }

    // Named and ordered in the panel's language (pg_geo_country_label()).
    $countries = pg_geo_sort_by_label($countries);

    $states = pg_tax_zone_form_state_items(db_items(
        "SELECT states.id, states.name, countries.name AS country_name, countries.code AS country_code
        FROM states
        LEFT JOIN countries ON countries.id = states.country_id
        ORDER BY states.name"));

    if ($selected !== null) {
        $zone_country_ids = (array) ($selected['allowed_countries'] ?? array());
        $zone_state_ids = (array) ($selected['allowed_states'] ?? array());
    } elseif ($tax_zone_id > 0) {
        $result = db_q("SELECT country_id FROM tax_zones_countries_xref WHERE tax_zone_id = ?", array((int) $tax_zone_id)) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $zone_country_ids[] = $row['country_id'];
        }

        $result = db_q("SELECT state_id FROM tax_zones_states_xref WHERE tax_zone_id = ?", array((int) $tax_zone_id)) or output_error('Query failed.');

        while ($row = mysqli_fetch_assoc($result)) {
            $zone_state_ids[] = $row['state_id'];
        }
    }

    return array(
        'countries' => pg_tax_zone_form_option_list($countries, $zone_country_ids),
        'states' => pg_tax_zone_form_option_list($states, $zone_state_ids),
    );
}

/**
 * Write the posted country and state links of a tax zone. The edit screen
 * deletes the old links first.
 *
 * @param int   $tax_zone_id
 * @param array $post The posted form ($_POST)
 * @return void
 */
function pg_tax_zone_form_save_links($tax_zone_id, $post)
{
    foreach (pg_tax_zone_form_link_ids($post['allowed_countries'] ?? null) as $country_id) {
        $query = "INSERT INTO tax_zones_countries_xref (tax_zone_id, country_id) VALUES (?, ?)";
        db_q($query, array((int) $tax_zone_id, (string) $country_id)) or output_error('Query failed.');
    }

    foreach (pg_tax_zone_form_link_ids($post['allowed_states'] ?? null) as $state_id) {
        $query = "INSERT INTO tax_zones_states_xref (tax_zone_id, state_id) VALUES (?, ?)";
        db_q($query, array((int) $tax_zone_id, (string) $state_id)) or output_error('Query failed.');
    }
}

/**
 * The form's three cards, from the opening row to its closing tag; the
 * screens print the form tag, the buttons and the edit screen's id around it.
 *
 * @param array|null $values          'name', 'tax_rate' (as stored, or as
 *                                    typed on a refused post); null on create
 * @param string     $country_options pg_tax_zone_form_choices()['countries']
 * @param string     $state_options   pg_tax_zone_form_choices()['states']
 * @param array      $errors          Field => message; 'name' is printed under the name field
 * @return string  HTML
 */
function pg_tax_zone_form_cards($values, $country_options, $state_options, $errors = array())
{
    $name = ($values === null) ? '' : (string) ($values['name'] ?? '');

    // 8.250 as stored reads as 8.25 and "8,25" as typed as 8.25; zero stays
    // 0, not an empty box; an unreadable rate leaves the box empty.
    $tax_rate = ($values === null) ? NULL : parse_tax_rate($values['tax_rate'] ?? '');
    $tax_rate_attribute = ($tax_rate === NULL) ? '' : ' value="' . h(format_tax_rate($tax_rate)) . '"';

    $name_error = isset($errors['name']) ? (string) $errors['name'] : '';
    $name_class = ($name_error !== '') ? ' is-invalid' : '';
    $name_aria = ($name_error !== '') ? ' aria-invalid="true" aria-describedby="name-error"' : '';
    $name_feedback = ($name_error !== '') ? '
                                                <div id="name-error" class="invalid-feedback d-block">' . h($name_error) . '</div>' : '';

    return '
                        <div class="row">
                            <div class="col-12 mb-12">
                                <div class="card my-7 h-100">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Options') . '
                                    </h2>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="name" class="form-label">' . lang('Tax Zone Name') . '</label>
                                                <input type="text" name="name" id="name" maxlength="50" value="' . h($name) . '" placeholder="' . h(lang('Tax Zone Name')) . '" class="form-control' . $name_class . '" required' . $name_aria . ' />' . $name_feedback . '
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="tax_rate" class="form-label">' . lang('Tax Rate') . ' (%)</label>
                                                <input type="number" min="0" max="100" step="0.001" name="tax_rate" id="tax_rate" required' . $tax_rate_attribute . ' class="form-control" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 md:col-6 mb-12">
                                <div class="card my-7 h-100">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Tax Zone Countries') . '
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
                                        ' . lang('Tax Zone States or Provinces') . '
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
