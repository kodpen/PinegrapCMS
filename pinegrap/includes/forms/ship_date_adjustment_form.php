<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ship date adjustment form, shared by the create and the edit screens
 * (add_ship_date_adjustment.php, edit_ship_date_adjustment.php): the
 * options card, the checks of a posted adjustment and the signed number of
 * days it is saved with. The screens keep what differs: the edit screen
 * loads the record, offers delete and updates the row; the create screen
 * inserts it.
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
 * The options of the shipping method pick list: an empty first option,
 * then each method by name, its code in brackets when it has one.
 *
 * @param array $shipping_methods  rows with id, name and code
 * @param mixed $selected  the chosen shipping method id
 * @return string
 */
function pg_ship_date_adjustment_form_shipping_method_options($shipping_methods, $selected)
{
    $output = '<option value=""></option>';

    foreach ($shipping_methods as $shipping_method) {
        $label = h($shipping_method['name']);

        if ($shipping_method['code'] != '') {
            $label .= ' (' . h($shipping_method['code']) . ')';
        }

        $output .= '<option value="' . h($shipping_method['id']) . '"' . (($selected == $shipping_method['id']) ? ' selected' : '') . '>' . $label . '</option>';
    }

    return $output;
}

/**
 * The earlier / later options; later is chosen when nothing is.
 *
 * @param string $value
 * @return string
 */
function pg_ship_date_adjustment_form_adjustment_type_options($value)
{
    $value = $value ?: 'later';

    return
        '<option value="earlier"' . ($value == 'earlier' ? ' selected' : '') . '>' . lang('earlier') . '</option>' .
        '<option value="later"' . ($value == 'later' ? ' selected' : '') . '>' . lang('later') . '</option>';
}

/**
 * The stored adjustment of posted days and direction: negative for earlier.
 *
 * @param mixed $days
 * @param string $type  'earlier' or 'later'
 * @return mixed
 */
function pg_ship_date_adjustment_form_adjustment($days, $type)
{
    if ($type == 'earlier') {
        return -$days;
    }

    return $days;
}

/**
 * The form fields of a stored adjustment: the number of days and the
 * direction (a negative adjustment is earlier).
 *
 * @param mixed $adjustment
 * @return array  array('adjustment_days' => …, 'adjustment_type' => …)
 */
function pg_ship_date_adjustment_form_fields($adjustment)
{
    if ($adjustment < 0) {
        return array('adjustment_days' => -$adjustment, 'adjustment_type' => 'earlier');
    }

    return array('adjustment_days' => $adjustment, 'adjustment_type' => 'later');
}

/**
 * The Options card: zip code prefix, shipping method and adjustment.
 *
 * @param liveform $liveform
 * @return string
 */
function pg_ship_date_adjustment_form_card($liveform)
{
    // Get shipping methods for pick list.
    $shipping_methods = db_items(
        "SELECT id, name, code
        FROM shipping_methods
        ORDER BY name ASC, code ASC");

    $output_shipping_method_options = pg_ship_date_adjustment_form_shipping_method_options($shipping_methods, $liveform->get_field_value('shipping_method_id'));
    $output_adjustment_type_options = pg_ship_date_adjustment_form_adjustment_type_options($liveform->get_field_value('adjustment_type'));

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
                                                <label for="zip_code_prefix" class="form-label">' . lang('Zip Code Prefix') . ' <span class="fg-secondary small">' . lang('(first 3 numbers)') . '</span></label>
                                                <input type="text" name="zip_code_prefix" id="zip_code_prefix" maxlength="3" required value="' . h($liveform->get_field_value('zip_code_prefix')) . '" class="form-control' . ($liveform->check_field_error('zip_code_prefix') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-8 my-3">
                                                <label for="shipping_method_id" class="form-label">' . lang('Shipping Method') . '</label>
                                                <select name="shipping_method_id" id="shipping_method_id" required class="form-control' . ($liveform->check_field_error('shipping_method_id') ? ' is-invalid' : '') . '">' . $output_shipping_method_options . '</select>
                                            </div>
                                            <div class="col-12 md:col-auto my-3">
                                                <div class="form-label" id="adjustment_label">' . lang('Adjustment') . '</div>
                                                <div class="input-group w-auto" role="group" aria-labelledby="adjustment_label">
                                                    <input style="width:100px;max-width:100%;" type="text" name="adjustment_days" id="adjustment_days" required aria-labelledby="adjustment_label adjustment_days_unit" maxlength="3" value="' . h($liveform->get_field_value('adjustment_days')) . '" class="form-control' . ($liveform->check_field_error('adjustment_days') ? ' is-invalid' : '') . '"/>
                                                    <span class="input-group-text" id="adjustment_days_unit">' . lang('days') . '</span>
                                                    <span class="visually-hidden" id="adjustment_type_label">' . lang('Direction') . '</span>
                                                    <select style="width:100px;max-width:100%;" name="adjustment_type" id="adjustment_type" aria-labelledby="adjustment_label adjustment_type_label" class="form-control' . ($liveform->check_field_error('adjustment_type') ? ' is-invalid' : '') . '">' . $output_adjustment_type_options . '</select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>';
}

/**
 * Checks a posted adjustment and marks its errors: the required fields, a
 * three character zip code prefix, a prefix and shipping method no other
 * adjustment uses, and a positive number of days.
 *
 * @param liveform $liveform
 * @param string|null $id  the adjustment being edited, null on create
 * @return void
 */
function pg_ship_date_adjustment_form_check($liveform, $id)
{
    $liveform->validate_required_field('zip_code_prefix', lang('Zip Code Prefix is required.'));
    $liveform->validate_required_field('shipping_method_id', lang('Shipping Method is required.'));
    $liveform->validate_required_field('adjustment_days', lang('The number of days is required.'));
    $liveform->validate_required_field('adjustment_type', lang('"earlier" or "later" is required.'));

    if (
        ($liveform->check_field_error('zip_code_prefix') == false)
        && (mb_strlen($liveform->get_field_value('zip_code_prefix')) != 3)
    ) {
        $liveform->mark_error('zip_code_prefix', lang('Sorry, the zip code prefix must contain 3 characters.'));
    }

    if (
        ($liveform->check_field_error('zip_code_prefix') == false)
        && ($liveform->check_field_error('shipping_method_id') == false)
    ) {
        $query =
            "SELECT COUNT(*) AS count
            FROM ship_date_adjustments
            WHERE
                (zip_code_prefix = ?)
                AND (shipping_method_id = ?)";
        $values = array((string) $liveform->get_field_value('zip_code_prefix'), (string) $liveform->get_field_value('shipping_method_id'));

        if ($id !== null) {
            $query .= " AND (id != ?)";
            $values[] = (string) $id;
        }

        $result = db_q($query, $values) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        if ((int) $row['count'] > 0) {
            $liveform->mark_error('zip_code_prefix', lang('Sorry, there is already a ship date adjustment for the zip code prefix and shipping method you selected.'));
            $liveform->mark_error('shipping_method_id');
        }
    }

    if (
        ($liveform->check_field_error('adjustment_days') == false)
        &&
        (
            (is_numeric($liveform->get_field_value('adjustment_days')) == false)
            || ($liveform->get_field_value('adjustment_days') <= 0)
        )
    ) {
        $liveform->mark_error('adjustment_days', lang('Please enter a valid number of days.'));
    }
}
