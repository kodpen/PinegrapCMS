<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The verified shipping address form, shared by the create and the edit
 * screens (add_verified_shipping_address.php,
 * edit_verified_shipping_address.php): the address card, the required
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
 * The Verified Shipping Address card: company, address 1 and 2, city,
 * state and zip code.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_verified_shipping_address_form_card($liveform)
{
    $output_state_options = pg_verified_shipping_address_form_state_options(get_state_options(), $liveform->get_field_value('state_id'));

    return '
                        <div class="row">
                            <div class="col-12 md:col-8 mb-12">
                                <div class="card my-7 h-100">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Verified Shipping Address') . '
                                    </h2>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 my-3">
                                                <label for="company" class="form-label">' . lang('Company') . '</label>
                                                <input type="text" name="company" id="company" maxlength="50" value="' . h($liveform->get_field_value('company')) . '" class="form-control' . ($liveform->check_field_error('company') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 my-3">
                                                <label for="address_1" class="form-label">' . lang('Address 1') . '</label>
                                                <input type="text" name="address_1" id="address_1" maxlength="50" value="' . h($liveform->get_field_value('address_1')) . '" class="form-control' . ($liveform->check_field_error('address_1') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 my-3">
                                                <label for="address_2" class="form-label">' . lang('Address 2') . '</label>
                                                <input type="text" name="address_2" id="address_2" maxlength="50" value="' . h($liveform->get_field_value('address_2')) . '" class="form-control' . ($liveform->check_field_error('address_2') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 lg:col-6 my-3">
                                                <label for="city" class="form-label">' . lang('City') . '</label>
                                                <input type="text" name="city" id="city" maxlength="50" value="' . h($liveform->get_field_value('city')) . '" class="form-control' . ($liveform->check_field_error('city') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 lg:col-4 my-3">
                                                <label for="state_id" class="form-label">' . lang('State') . '</label>
                                                <select style="width:100%" data-pg-combobox class="form-control' . ($liveform->check_field_error('state_id') ? ' is-invalid' : '') . '" data-placeholder="' . lang('Click to select state') . '" id="state_id" name="state_id">' . $output_state_options . '</select>' . pg_fields_script() . '
                                            </div>
                                            <div class="col-12 lg:col-2 my-3">
                                                <label for="zip_code" class="form-label">' . lang('Zip Code') . '</label>
                                                <input type="text" name="zip_code" id="zip_code" maxlength="50" value="' . h($liveform->get_field_value('zip_code')) . '" class="form-control' . ($liveform->check_field_error('zip_code') ? ' is-invalid' : '') . '" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
