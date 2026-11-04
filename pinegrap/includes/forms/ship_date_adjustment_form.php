<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The ship date adjustment form, shared by the create and the edit screens
 * (add_ship_date_adjustment.php, edit_ship_date_adjustment.php): the
 * form and status columns, the sticky save bar, the checks of a posted adjustment and the signed number of
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
 * The form column (Options card: zip code prefix, shipping method and
 * adjustment) and the sticky status column (a Summary of the prefix, the
 * method and the adjustment, following the fields as they change), in the
 * edit screen layout of product_builder.php. Below lg the status column
 * follows the form in source order. The screens print the form tag, the
 * buttons (pg_ship_date_adjustment_form_layout()) and the edit screen's id
 * around it.
 *
 * @param liveform $liveform
 * @param string $status_rows  extra Summary rows
 *                             (pg_ship_date_adjustment_form_status_row()),
 *                             the edit screen's last change
 * @return string
 */
function pg_ship_date_adjustment_form_card($liveform, $status_rows = '')
{
    // Get shipping methods for pick list.
    $shipping_methods = db_items(
        "SELECT id, name, code
        FROM shipping_methods
        ORDER BY name ASC, code ASC");

    $output_shipping_method_options = pg_ship_date_adjustment_form_shipping_method_options($shipping_methods, $liveform->get_field_value('shipping_method_id'));
    $output_adjustment_type_options = pg_ship_date_adjustment_form_adjustment_type_options($liveform->get_field_value('adjustment_type'));

    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-4 my-3">
                                            <label for="zip_code_prefix" class="form-label">' . lang('Zip Code Prefix') . ' <span class="fg-secondary small">' . lang('(first 3 numbers)') . '</span></label>
                                            <input type="text" name="zip_code_prefix" id="zip_code_prefix" maxlength="3" required value="' . h($liveform->get_field_value('zip_code_prefix')) . '" class="form-control' . ($liveform->check_field_error('zip_code_prefix') ? ' is-invalid' : '') . '" />
                                        </div>
                                        <div class="col-12 md:col-8 my-3">
                                            <label for="shipping_method_id" class="form-label">' . lang('Shipping Method') . '</label>
                                            <select name="shipping_method_id" id="shipping_method_id" required class="form-control' . ($liveform->check_field_error('shipping_method_id') ? ' is-invalid' : '') . '">' . $output_shipping_method_options . '</select>
                                        </div>
                                        <div class="col-12 md:col-auto my-3">
                                            <div class="form-label" id="adjustment_label">' . lang('Adjustment') . '</div>
                                            <div class="input-group w-auto" role="group" aria-labelledby="adjustment_label">
                                                <input style="width:100px;max-width:100%;" type="text" name="adjustment_days" id="adjustment_days" required inputmode="numeric" pattern="[0-9]*" aria-labelledby="adjustment_label adjustment_days_unit" maxlength="3" value="' . h($liveform->get_field_value('adjustment_days')) . '" class="form-control' . ($liveform->check_field_error('adjustment_days') ? ' is-invalid' : '') . '"/>
                                                <span class="input-group-text" id="adjustment_days_unit">' . lang('days') . '</span>
                                                <span class="visually-hidden" id="adjustment_type_label">' . lang('Direction') . '</span>
                                                <select style="width:100px;max-width:100%;" name="adjustment_type" id="adjustment_type" aria-labelledby="adjustment_label adjustment_type_label" class="form-control' . ($liveform->check_field_error('adjustment_type') ? ' is-invalid' : '') . '">' . $output_adjustment_type_options . '</select>
                                            </div>
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
                                        <dl class="mb-0" id="ship_date_adjustment_summary" data-days="' . h(lang('days')) . '">'
        . pg_ship_date_adjustment_form_status_row(lang('Zip Code Prefix'), h($liveform->get_field_value('zip_code_prefix')), false, 'ship_date_adjustment_summary_prefix')
        . pg_ship_date_adjustment_form_status_row(lang('Shipping Method'), '', false, 'ship_date_adjustment_summary_method')
        . pg_ship_date_adjustment_form_status_row(lang('Adjustment'), '', $status_rows === '', 'ship_date_adjustment_summary_adjustment')
        . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <script>
                    (function () {
                        // The Summary follows the fields as they change.
                        var summary = document.getElementById("ship_date_adjustment_summary");
                        var prefix = document.getElementById("zip_code_prefix");
                        var method = document.getElementById("shipping_method_id");
                        var days = document.getElementById("adjustment_days");
                        var type = document.getElementById("adjustment_type");

                        if (!summary || !prefix || !method || !days || !type) {
                            return;
                        }

                        function selectedText(select) {
                            var option = select.options[select.selectedIndex];
                            return (option && option.value !== "") ? option.text : "";
                        }

                        function update() {
                            var typed = days.value.trim();
                            document.getElementById("ship_date_adjustment_summary_prefix").textContent = prefix.value.trim();
                            document.getElementById("ship_date_adjustment_summary_method").textContent = selectedText(method);
                            document.getElementById("ship_date_adjustment_summary_adjustment").textContent = (typed === "") ? "" : typed + " " + summary.getAttribute("data-days") + " " + selectedText(type);
                        }

                        prefix.addEventListener("input", update);
                        method.addEventListener("change", update);
                        days.addEventListener("input", update);
                        type.addEventListener("change", update);
                        update();
                    })();
                    </script>';
}

/**
 * One Summary row of the status column.
 *
 * @param string $label The label (plain text)
 * @param string $value The value (HTML, escaped by the caller)
 * @param bool   $last  The last row draws no bottom border
 * @param string $id    The value's id, for a row the script updates
 * @return string  HTML
 */
function pg_ship_date_adjustment_form_status_row($label, $value, $last = true, $id = '')
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break"' . ($id !== '' ? ' id="' . h($id) . '"' : '') . '>' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the columns (pg_ship_date_adjustment_form_card()) and the
 * sticky save bar with the given buttons.
 *
 * @param string $cards   pg_ship_date_adjustment_form_card()
 * @param string $buttons The buttons inside the bar's group
 * @return string  HTML
 */
function pg_ship_date_adjustment_form_layout($cards, $buttons)
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
 * Checks a posted adjustment and marks its errors: the required fields, a
 * three character zip code prefix, a shipping method that exists, a prefix
 * and shipping method no other adjustment uses, earlier or later, and a
 * whole number of days from 1.
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
        if (pg_ship_date_adjustment_form_taken($liveform->get_field_value('zip_code_prefix'), $liveform->get_field_value('shipping_method_id'), $id)) {
            $liveform->mark_error('zip_code_prefix', lang('Sorry, there is already a ship date adjustment for the zip code prefix and shipping method you selected.'));
            $liveform->mark_error('shipping_method_id');
        }
    }

    // A hand-made post can name a method that does not exist; the list
    // showed such an adjustment with an empty method.
    if ($liveform->check_field_error('shipping_method_id') == false) {
        if (!pg_ship_date_adjustment_form_method_exists($liveform->get_field_value('shipping_method_id'))) {
            $liveform->mark_error('shipping_method_id', lang('Shipping Method is required.'));
        }
    }

    if (
        ($liveform->check_field_error('adjustment_type') == false)
        && !pg_ship_date_adjustment_form_type_valid($liveform->get_field_value('adjustment_type'))
    ) {
        $liveform->mark_error('adjustment_type', lang('"earlier" or "later" is required.'));
    }

    if (
        ($liveform->check_field_error('adjustment_days') == false)
        && !pg_ship_date_adjustment_form_days_valid($liveform->get_field_value('adjustment_days'))
    ) {
        $liveform->mark_error('adjustment_days', lang('Please enter a valid number of days.'));
    }
}

/**
 * Whether a posted number of days is a whole number from 1 to 999 (the box
 * holds three characters). is_numeric() let 1.5 through, which the
 * smallint column rounded to 2, and 1e2, stored as 100.
 *
 * @param mixed $days
 * @return bool
 */
function pg_ship_date_adjustment_form_days_valid($days)
{
    if (!is_scalar($days)) {
        return false;
    }

    $days = trim((string) $days);

    return ($days !== '') && ctype_digit($days) && (strlen($days) <= 3) && ((int) $days >= 1);
}

/**
 * Whether a posted direction is one the form offers: any other value was
 * saved as later.
 *
 * @param mixed $type
 * @return bool
 */
function pg_ship_date_adjustment_form_type_valid($type)
{
    return in_array($type, array('earlier', 'later'), true);
}

/**
 * Whether another adjustment has the zip code prefix and shipping method:
 * checkout applies one adjustment per pair.
 *
 * @param mixed $zip_code_prefix
 * @param mixed $shipping_method_id
 * @param string|int|null $id  the adjustment being edited, null on create
 * @return bool
 */
function pg_ship_date_adjustment_form_taken($zip_code_prefix, $shipping_method_id, $id)
{
    $query =
        "SELECT COUNT(*) AS count
        FROM ship_date_adjustments
        WHERE
            (zip_code_prefix = ?)
            AND (shipping_method_id = ?)";
    $values = array((string) $zip_code_prefix, (string) $shipping_method_id);
    if ($id !== null) {
        $query .= " AND (id != ?)";
        $values[] = (string) $id;
    }
    $result = db_q($query, $values) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    return ((int) $row['count'] > 0);
}

/**
 * Whether the shipping method exists: a hand-made post can name one that
 * does not, and the list showed such an adjustment with an empty method.
 *
 * @param mixed $shipping_method_id
 * @return bool
 */
function pg_ship_date_adjustment_form_method_exists($shipping_method_id)
{
    $result = db_q("SELECT COUNT(*) FROM shipping_methods WHERE id = ?", array((int) $shipping_method_id)) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    return ((int) $row[0] > 0);
}
