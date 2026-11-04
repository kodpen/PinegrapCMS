<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The verified shipping address form, shared by the create and the edit
 * screens (add_verified_shipping_address.php,
 * edit_verified_shipping_address.php): the form column with the address
 * card, the sticky status column with the Summary, the sticky save bar, the required
 * fields, the column values a save writes and the name the activity log
 * shows. The screens keep what differs: the edit screen loads the record,
 * offers delete and sends the id back.
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
 * The state pick list options with the current state selected.
 *
 * @param array $states  Label => state id, as get_state_options() returns them (labels already escaped)
 * @param mixed $current_state_id
 * @return string
 */
function pg_verified_shipping_address_form_state_options($states, $current_state_id)
{
    $output = '';

    foreach ($states as $label => $value) {
        $selected = ($current_state_id !== '' && $current_state_id == $value) ? ' selected' : '';
        $output .= '<option value="' . h($value) . '"' . $selected . '>' . $label . '</option>';
    }

    return $output;
}

/**
 * The ids of the states that belong to a countries row standing for Turkey:
 * the address has no country field, its state says which country it is in.
 *
 * @return array list of states.id as strings
 */
function pg_verified_shipping_address_form_tr_state_ids()
{
    $ids = array();

    foreach ((array) db_items("SELECT states.id, countries.code, countries.name FROM states LEFT JOIN countries ON countries.id = states.country_id") as $row) {
        if (pg_ship_to_district_country_is_tr((string) $row['code'], (string) $row['name'])) {
            $ids[] = (string) $row['id'];
        }
    }

    return $ids;
}

/**
 * The form column (the Verified Shipping Address card: company, address 1
 * and 2, city, state and zip code) and the sticky status column (a Summary
 * that prints the address as it reads, following the fields, then the
 * screen's own rows: the creation and the last change on edit); the screens
 * wrap it in pg_verified_shipping_address_form_layout(). For a Turkish state
 * the city and state labels say İlçe and İl, and follow the state pick list
 * (pg_address_labels_html(), pg_ship_to_district_script()).
 *
 * @param liveform $liveform
 * @param string   $status_rows  More Summary rows (pg_verified_shipping_address_form_status_row()), HTML
 * @return string
 */
function pg_verified_shipping_address_form_card($liveform, $status_rows = '')
{
    $states = get_state_options();
    $output_state_options = pg_verified_shipping_address_form_state_options($states, $liveform->get_field_value('state_id'));
    $tr_state_ids = pg_verified_shipping_address_form_tr_state_ids();
    $state_id = (string) $liveform->get_field_value('state_id');
    $city_label = pg_address_labels_html(lang('City'), 'city', 'state_id', $state_id, $tr_state_ids);
    $state_label = pg_address_labels_html(lang('State'), 'state', 'state_id', $state_id, $tr_state_ids);

    // The state as the pick list shows it (its labels are already escaped).
    $state_text = '';
    foreach ((array) $states as $label => $value) {
        if (($state_id !== '') && ($state_id == $value)) {
            $state_text = (string) $label;
        }
    }

    $preview = pg_verified_shipping_address_form_preview(array(
        'company' => (string) $liveform->get_field_value('company'),
        'address_1' => (string) $liveform->get_field_value('address_1'),
        'address_2' => (string) $liveform->get_field_value('address_2'),
        'city' => (string) $liveform->get_field_value('city'),
        'zip_code' => (string) $liveform->get_field_value('zip_code'),
    ), $state_text);

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Verified Shipping Address') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 my-3">
                                            <label for="company" class="form-label">' . lang('Company') . '</label>
                                            <input type="text" name="company" id="company" maxlength="50" value="' . h($liveform->get_field_value('company')) . '" class="form-control' . ($liveform->check_field_error('company') ? ' is-invalid' : '') . '" />
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="address_1" class="form-label">' . lang('Address 1') . '</label>
                                            <input type="text" name="address_1" id="address_1" maxlength="50" required value="' . h($liveform->get_field_value('address_1')) . '" class="form-control' . ($liveform->check_field_error('address_1') ? ' is-invalid' : '') . '" />
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="address_2" class="form-label">' . lang('Address 2') . '</label>
                                            <input type="text" name="address_2" id="address_2" maxlength="50" value="' . h($liveform->get_field_value('address_2')) . '" class="form-control' . ($liveform->check_field_error('address_2') ? ' is-invalid' : '') . '" />
                                        </div>
                                        <div class="col-12 md:col-5 my-3">
                                            <label for="city" class="form-label"' . $city_label['attrs'] . '>' . $city_label['text'] . '</label>
                                            <input type="text" name="city" id="city" maxlength="50" required value="' . h($liveform->get_field_value('city')) . '" class="form-control' . ($liveform->check_field_error('city') ? ' is-invalid' : '') . '"' . pg_places_input_attrs('TR', 'state_id') . ' />
                                        </div>
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="state_id" class="form-label"' . $state_label['attrs'] . '>' . $state_label['text'] . '</label>
                                            <select style="width:100%" data-pg-combobox class="form-control' . ($liveform->check_field_error('state_id') ? ' is-invalid' : '') . '" data-placeholder="' . lang('Click to select state') . '" id="state_id" name="state_id" required>' . $output_state_options . '</select>' . pg_fields_script() . pg_ship_to_district_script() . '
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="zip_code" class="form-label">' . lang('Zip Code') . '</label>
                                            <input type="text" name="zip_code" id="zip_code" maxlength="50" required value="' . h($liveform->get_field_value('zip_code')) . '" class="form-control' . ($liveform->check_field_error('zip_code') ? ' is-invalid' : '') . '" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <address id="verified_address_summary" class="mb-0 text-break">' . $preview . '</address>' . ($status_rows !== '' ? '
                                        <dl class="mb-0 mt-3 border-top">' . $status_rows . '
                                        </dl>' : '') . '
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // The Summary follows the fields as they change; jQuery hears a
                            // pick of the state combobox whatever engine draws it.
                            (function () {
                                var summary = document.getElementById("verified_address_summary");
                                var text = function (id) { return document.getElementById(id).value.trim(); };
                                var update = function () {
                                    var state = document.getElementById("state_id");
                                    var option = state.options[state.selectedIndex];
                                    var state_text = (option && option.value !== "") ? option.text.trim() : "";
                                    var last = [text("city"), [state_text, text("zip_code")].filter(Boolean).join(" ")].filter(Boolean).join(", ");
                                    summary.textContent = "";
                                    [text("company"), text("address_1"), text("address_2"), last].filter(Boolean).forEach(function (line) {
                                        var div = document.createElement("div");
                                        div.textContent = line;
                                        summary.appendChild(div);
                                    });
                                };
                                ["company", "address_1", "address_2", "city", "zip_code"].forEach(function (id) {
                                    document.getElementById(id).addEventListener("input", update);
                                });
                                jQuery("#state_id").on("change", update);
                            })();
                        </script>';
}

/**
 * The address as the Summary prints it: one line each for the company and
 * the two address lines, then "city, state zip"; empty parts are left out.
 *
 * @param array  $address     'company', 'address_1', 'address_2', 'city', 'zip_code' (plain text)
 * @param string $state_html  The state's name, already HTML
 * @return string  HTML
 */
function pg_verified_shipping_address_form_preview($address, $state_html)
{
    $lines = array();

    foreach (array('company', 'address_1', 'address_2') as $key) {
        $value = trim((string) ($address[$key] ?? ''));
        if ($value !== '') {
            $lines[] = h($value);
        }
    }

    $place = array();
    $city = trim((string) ($address['city'] ?? ''));
    if ($city !== '') {
        $place[] = h($city);
    }

    $state_zip = trim(trim((string) $state_html) . ' ' . h(trim((string) ($address['zip_code'] ?? ''))));
    if ($state_zip !== '') {
        $place[] = $state_zip;
    }

    if ($place) {
        $lines[] = implode(', ', $place);
    }

    $output = '';
    foreach ($lines as $line) {
        $output .= '<div>' . $line . '</div>';
    }

    return $output;
}

/**
 * One Summary row of the status column: a label and its value.
 *
 * @param string $label  The label, plain text
 * @param string $value  The value, already HTML
 * @param bool   $last   False draws the line under the row
 * @return string
 */
function pg_verified_shipping_address_form_status_row($label, $value, $last = true)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the columns (pg_verified_shipping_address_form_card())
 * and the sticky save bar with the given buttons.
 *
 * @param string $cards    pg_verified_shipping_address_form_card()
 * @param string $buttons  The buttons inside the bar's group
 * @return string
 */
function pg_verified_shipping_address_form_layout($cards, $buttons)
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
 * Marks the required fields left empty: address 1, city, state, zip code.
 *
 * @param liveform $liveform
 * @return void
 */
function pg_verified_shipping_address_form_validate($liveform)
{
    $liveform->validate_required_field('address_1', lang('Address 1 is required.'));
    $liveform->validate_required_field('city', lang('City is required.'));
    $liveform->validate_required_field('state_id', lang('State is required.'));
    $liveform->validate_required_field('zip_code', lang('Zip Code is required.'));
}

/**
 * The posted address as column => value, in INSERT/UPDATE order.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_verified_shipping_address_form_values($liveform)
{
    $values = array();

    foreach (array('company', 'address_1', 'address_2', 'city', 'state_id', 'zip_code') as $column) {
        $values[$column] = (string) $liveform->get_field_value($column);
    }

    return $values;
}

/**
 * The name the activity log shows: the company, or address 1 without one.
 *
 * @param string $company
 * @param string $address_1
 * @return string
 */
function pg_verified_shipping_address_form_name($company, $address_1)
{
    return $company != '' ? (string) $company : (string) $address_1;
}
