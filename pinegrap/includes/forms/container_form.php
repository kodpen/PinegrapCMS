<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The container form, shared by the create and the edit screens
 * (add_container.php, edit_container.php): the options card, its unit
 * switch script and the check of a posted container. The screens keep
 * what differs: the edit screen loads the record, fills the fields, posts
 * its id and offers delete; each writes its own row.
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
 * The form column (Options card: name and cost; Package card: the
 * dimensions in inches or cm and the weight in pounds or kg) and the
 * sticky status column (Status card: the Enabled switch; Summary card: the dimensions,
 * weight and cost in the shown units, filled and kept current by
 * pg_container_form_script(), then $status_rows), in the edit screen
 * layout of product_builder.php. Below lg the status column follows the
 * form in source order. Only the inch and pound fields are posted; the
 * metric fields are converted into them by pg_container_form_script().
 *
 * @param liveform $liveform
 * @param string   $status_rows  More Summary rows (pg_container_form_status_row()), e.g. Last Modified on edit
 * @return string  HTML
 */
function pg_container_form_cards($liveform, $status_rows = '')
{
    return '
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            <input type="text" name="name" id="name" maxlength="100" required value="' . h($liveform->get_field_value('name')) . '" class="form-control' . ($liveform->check_field_error('name') ? ' is-invalid' : '') . '" />
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="cost" class="form-label">' . lang('Cost') . '</label>
                                            <label class="form-control form-adorn" style="max-width:200px;">
                                                <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                <input type="number" step="any" min="0" max="' . pg_container_cost_max() . '" id="cost" name="cost" value="' . h($liveform->get_field_value('cost')) . '" class="form-ghost' . ($liveform->check_field_error('cost') ? ' is-invalid' : '') . '" />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Package') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <fieldset class="col-12 mt-3 mb-0">
                                            <legend class="form-label fs-md">' . lang('Dimensions') . '</legend>
                                            <div class="mb-3">
                                                <div class="form-field d-inline-grid me-5">
                                                    <input type="radio" class="radio" id="length_type_inches" name="length_type" value="inches" checked />
                                                    <label class="" for="length_type_inches">' . lang('Inches') . '</label>
                                                </div>
                                                <div class="form-field d-inline-grid me-5">
                                                    <input type="radio" class="radio" id="length_type_cm" name="length_type" value="cm" />
                                                    <label class="" for="length_type_cm">' . lang('Cm') . '</label>
                                                </div>
                                            </div>
                                            <div id="length_inches" class="row g-3" style="max-width:600px;">
                                                <div class="col-12 sm:col-4">
                                                    <label for="length" class="form-label">' . lang('Length') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                    <label class="form-control form-adorn form-adorn-end">
                                                        <span class="form-adorn-text" aria-hidden="true">' . lang('in') . '</span>
                                                        <input type="number" step="any" min="0.0001" max="' . pg_container_dimension_max() . '" id="length" name="length" value="' . h($liveform->get_field_value('length')) . '" class="form-ghost' . ($liveform->check_field_error('length') ? ' is-invalid' : '') . '" />
                                                    </label>
                                                </div>
                                                <div class="col-12 sm:col-4">
                                                    <label for="width" class="form-label">' . lang('Width') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                    <label class="form-control form-adorn form-adorn-end">
                                                        <span class="form-adorn-text" aria-hidden="true">' . lang('in') . '</span>
                                                        <input type="number" step="any" min="0.0001" max="' . pg_container_dimension_max() . '" id="width" name="width" value="' . h($liveform->get_field_value('width')) . '" class="form-ghost' . ($liveform->check_field_error('width') ? ' is-invalid' : '') . '" />
                                                    </label>
                                                </div>
                                                <div class="col-12 sm:col-4">
                                                    <label for="height" class="form-label">' . lang('Height') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                    <label class="form-control form-adorn form-adorn-end">
                                                        <span class="form-adorn-text" aria-hidden="true">' . lang('in') . '</span>
                                                        <input type="number" step="any" min="0.0001" max="' . pg_container_dimension_max() . '" id="height" name="height" value="' . h($liveform->get_field_value('height')) . '" class="form-ghost' . ($liveform->check_field_error('height') ? ' is-invalid' : '') . '" />
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="length_cm" class="row g-3 d-none" style="max-width:600px;">
                                                <div class="col-12 sm:col-4">
                                                    <label for="lengthcm" class="form-label">' . lang('Length') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                    <label class="form-control form-adorn form-adorn-end">
                                                        <span class="form-adorn-text" aria-hidden="true">' . lang('cm') . '</span>
                                                        <input type="number" step="any" min="0.01" max="253999999.9997" id="lengthcm" name="lengthcm" class="form-ghost" />
                                                    </label>
                                                </div>
                                                <div class="col-12 sm:col-4">
                                                    <label for="widthcm" class="form-label">' . lang('Width') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                    <label class="form-control form-adorn form-adorn-end">
                                                        <span class="form-adorn-text" aria-hidden="true">' . lang('cm') . '</span>
                                                        <input type="number" step="any" min="0.01" max="253999999.9997" id="widthcm" name="widthcm" class="form-ghost" />
                                                    </label>
                                                </div>
                                                <div class="col-12 sm:col-4">
                                                    <label for="heightcm" class="form-label">' . lang('Height') . '<span class="pg-required-marker fg-danger ms-1" aria-hidden="true">*</span></label>
                                                    <label class="form-control form-adorn form-adorn-end">
                                                        <span class="form-adorn-text" aria-hidden="true">' . lang('cm') . '</span>
                                                        <input type="number" step="any" min="0.01" max="253999999.9997" id="heightcm" name="heightcm" class="form-ghost" />
                                                    </label>
                                                </div>
                                            </div>
                                        </fieldset>
                                        <div class="col-12 my-3">
                                            <label for="weight" id="weight_label" class="form-label">' . lang('Weight') . '</label>
                                            <div class="mb-3">
                                                <div class="form-field d-inline-grid me-5">
                                                    <input type="radio" class="radio" id="weight_type_pounds" name="weight_type" value="pounds" checked />
                                                    <label class="" for="weight_type_pounds">' . lang('Pounds') . '</label>
                                                </div>
                                                <div class="form-field d-inline-grid me-5">
                                                    <input type="radio" class="radio" id="weight_type_kg" name="weight_type" value="kg" />
                                                    <label class="" for="weight_type_kg">' . lang('Kg') . '</label>
                                                </div>
                                            </div>
                                            <label id="weight_pound" class="form-control form-adorn form-adorn-end" style="max-width:200px;">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('lbs') . '</span>
                                                <input type="number" step="any" min="0" max="' . pg_container_dimension_max() . '" id="weight" name="weight" value="' . h($liveform->get_field_value('weight')) . '" class="form-ghost' . ($liveform->check_field_error('weight') ? ' is-invalid' : '') . '" />
                                            </label>
                                            <label id="weight_kg" class="form-control form-adorn form-adorn-end d-none" style="max-width:200px;">
                                                <span class="form-adorn-text" aria-hidden="true">' . lang('kg') . '</span>
                                                <input type="number" step="any" min="0" max="45359236.9999" id="weightkg" name="weightkg" class="form-ghost" aria-labelledby="weight_label" />
                                            </label>
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
                                        ' . lang('Status') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <div class="form-field">
                                            <div class="switch"><input type="checkbox" name="enabled" id="enabled" value="1" class=""' . ($liveform->get_field_value('enabled') ? ' checked' : '') . ' /></div>
                                            <label for="enabled" class="">' . lang('Enabled') . '</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">
                                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Dimensions') . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="container_summary_dimensions" data-in="' . h(lang('in')) . '" data-cm="' . h(lang('cm')) . '"></dd>
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <dt class="fw-normal">' . lang('Weight') . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="container_summary_weight" data-lbs="' . h(lang('lbs')) . '" data-kg="' . h(lang('kg')) . '"></dd>
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 py-2">
                                                <dt class="fw-normal">' . lang('Cost') . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break" id="container_summary_cost" data-symbol="' . h(html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '"></dd>
                                            </div>
                                            ' . $status_rows . '
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
';
}

/**
 * One more Summary row of the status column. The rows before it carry
 * the dividing line, so a row starts with a top border.
 *
 * @param string $label  Plain text
 * @param string $value  HTML
 * @return string  HTML
 */
function pg_container_form_status_row($label, $value)
{
    return '
                                            <div class="d-flex justify-content-between gap-3 py-2 border-top">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $value . '</dd>
                                            </div>';
}

/**
 * The screen row: the columns (pg_container_form_cards()) and the sticky
 * save bar with the given buttons.
 *
 * @param string $cards    pg_container_form_cards()
 * @param string $buttons  The buttons inside the bar's group
 * @return string  HTML
 */
function pg_container_form_layout($cards, $buttons)
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
 * The script that switches the units, keeps the metric fields and the
 * posted inch and pound fields in step and fills the Summary of
 * pg_container_form_cards(); printed after the form.
 *
 * @return string  HTML
 */
function pg_container_form_script()
{
    return '
                    <script>
                    $(function() {
                        // Convert a typed figure and round it: cm to two places,
                        // inches, pounds and kg to four (the columns are
                        // decimal(12,4)); a blank or unreadable source leaves the
                        // other box blank.
                        function convert(value, factor, places) {
                            var n = parseFloat(String(value).replace(",", "."));
                            if (value === "" || isNaN(n)) return "";
                            return String(Number((n * factor).toFixed(places)));
                        }
                        var pairs = [["length", "lengthcm"], ["width", "widthcm"], ["height", "heightcm"]];
                        $.each(pairs, function(i, pair) {
                            var $in = $("#" + pair[0]), $cm = $("#" + pair[1]);
                            $cm.val(convert($in.val(), 2.54, 2));
                            $in.on("input", function() { $cm.val(convert($in.val(), 2.54, 2)); });
                            $cm.on("input", function() { $in.val(convert($cm.val(), 0.3937007874, 4)); });
                        });
                        // Only the boxes of the shown unit are required: a required
                        // box hidden with d-none would block the save without a
                        // visible message.
                        function lengthUnit() {
                            var isInches = $("#length_type_inches").is(":checked");
                            $("#length_inches").toggleClass("d-none", !isInches);
                            $("#length_cm").toggleClass("d-none", isInches);
                            $("#length, #width, #height").prop("required", isInches);
                            $("#lengthcm, #widthcm, #heightcm").prop("required", !isInches);
                        }
                        $("input[name=length_type]").on("change", lengthUnit);
                        lengthUnit();
                        $("#weightkg").val(convert($("#weight").val(), 0.45359237, 4));
                        $("#weight").on("input", function() { $("#weightkg").val(convert($(this).val(), 0.45359237, 4)); });
                        $("#weightkg").on("input", function() { $("#weight").val(convert($(this).val(), 2.20462262, 4)); });
                        $("input[name=weight_type]").on("change", function() {
                            var isLbs = $("#weight_type_pounds").is(":checked");
                            $("#weight_pound").toggleClass("d-none", !isLbs);
                            $("#weight_kg").toggleClass("d-none", isLbs);
                        });
                        // The Summary shows the figures in the shown units and
                        // follows every box and unit switch; bound after the
                        // converters above, so the other unit is already filled.
                        function summary() {
                            var isInches = $("#length_type_inches").is(":checked");
                            var ids = isInches ? ["length", "width", "height"] : ["lengthcm", "widthcm", "heightcm"];
                            var $dims = $("#container_summary_dimensions");
                            var figures = $.map(ids, function(id) { return $.trim($("#" + id).val()); });
                            $dims.text($.grep(figures, function(f) { return f === ""; }).length ? "" : figures.join(" × ") + " " + $dims.attr(isInches ? "data-in" : "data-cm"));
                            var isLbs = $("#weight_type_pounds").is(":checked");
                            var $weight = $("#container_summary_weight");
                            var weight = $.trim($(isLbs ? "#weight" : "#weightkg").val());
                            $weight.text(weight === "" ? "" : weight + " " + $weight.attr(isLbs ? "data-lbs" : "data-kg"));
                            var $cost = $("#container_summary_cost");
                            var cost = $.trim($("#cost").val());
                            $cost.text(cost === "" ? "" : $cost.attr("data-symbol") + cost);
                        }
                        $("#length, #width, #height, #lengthcm, #widthcm, #heightcm, #weight, #weightkg, #cost").on("input", summary);
                        $("input[name=length_type], input[name=weight_type]").on("change", summary);
                        summary();
                    });
                    </script>
';
}

/**
 * Check a posted container: name, length, width and height are required,
 * the name fits its column and is not used by another container, the
 * dimensions are greater than zero, and every figure fits its column (the
 * site runs a non-strict sql_mode, which would store a negative or
 * non-numeric figure as 0 and an oversized one as the column's limit). A
 * blank weight or cost is allowed. Errors are marked on the liveform.
 *
 * @param liveform $liveform
 * @param int      $container_id The container being edited, 0 for a new one
 * @return void
 */
function pg_container_form_validate($liveform, $container_id = 0)
{
    $liveform->validate_required_field('name', lang('Name is required.'));
    $liveform->validate_required_field('length', lang('Length is required.'));
    $liveform->validate_required_field('width', lang('Width is required.'));
    $liveform->validate_required_field('height', lang('Height is required.'));

    if (($liveform->check_field_error('name') == false) && (mb_strlen((string) $liveform->get_field_value('name'), 'UTF-8') > 100)) {
        $liveform->mark_error('name', lang('Sorry, the name must not be longer than 100 characters.'));
    }

    if ($liveform->check_field_error('name') == false) {
        $result = db_q("SELECT COUNT(*) AS count FROM containers WHERE (name = ?) AND (id != ?)", array((string) $liveform->get_field_value('name'), (string) $container_id));
        $row = $result ? mysqli_fetch_assoc($result) : null;

        if ($row && ($row['count'] != 0)) {
            $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }
    }

    $dimension_max = pg_container_format_measure(pg_container_dimension_max());

    $checks = array(
        'length' => array(true, pg_container_dimension_max(), lang('Sorry, the length must be greater than zero.'), lang(array('string' => 'Sorry, the length must not be greater than {var:1}.', 'vars' => array($dimension_max)))),
        'width' => array(true, pg_container_dimension_max(), lang('Sorry, the width must be greater than zero.'), lang(array('string' => 'Sorry, the width must not be greater than {var:1}.', 'vars' => array($dimension_max)))),
        'height' => array(true, pg_container_dimension_max(), lang('Sorry, the height must be greater than zero.'), lang(array('string' => 'Sorry, the height must not be greater than {var:1}.', 'vars' => array($dimension_max)))),
        'weight' => array(false, pg_container_dimension_max(), lang('Sorry, the weight must be a number of zero or more.'), lang(array('string' => 'Sorry, the weight must not be greater than {var:1}.', 'vars' => array($dimension_max)))),
        'cost' => array(false, pg_container_cost_max(), lang('Sorry, the cost must be an amount of zero or more.'), lang(array('string' => 'Sorry, the cost must not be greater than {var:1}.', 'vars' => array(pg_format_money(pg_container_cost_max(), BASE_CURRENCY_SYMBOL))))));

    foreach ($checks as $field => $check) {
        if ($liveform->check_field_error($field)) {
            continue;
        }

        $value = $liveform->get_field_value($field);

        // A blank weight or cost is stored as 0, "none".
        if (!$check[0] && is_scalar($value) && (trim((string) $value) === '')) {
            continue;
        }

        $problem = pg_container_number_check($value, $check[1], $check[0]);

        if ($problem === 'number') {
            $liveform->mark_error($field, $check[2]);
        } elseif ($problem === 'max') {
            $liveform->mark_error($field, $check[3]);
        }
    }
}

/**
 * The largest length, width, height or weight: decimal(12,4) unsigned.
 *
 * @return float
 */
function pg_container_dimension_max()
{
    return 99999999.9999;
}

/**
 * The largest cost: containers.cost is int(10) unsigned in cents.
 *
 * @return float
 */
function pg_container_cost_max()
{
    return 42949672.95;
}

/**
 * Whether a posted figure fits its column: '' when it does, 'number' when it
 * is not a number, is negative or (for a dimension) not above zero at the
 * column's four places, 'max' when it is above $max. A decimal comma is read
 * like a point.
 *
 * @param mixed $value
 * @param float $max
 * @param bool  $positive True when zero is refused too
 * @return string
 */
function pg_container_number_check($value, $max, $positive)
{
    if (!is_scalar($value)) {
        return 'number';
    }

    $text = trim((string) $value);

    if ((strpos($text, ',') !== false) && (strpos($text, '.') === false) && (substr_count($text, ',') === 1)) {
        $text = str_replace(',', '.', $text);
    }

    if (!is_numeric($text)) {
        return 'number';
    }

    $number = round((float) $text, 4);

    if (($number < 0) || ($positive && ($number <= 0))) {
        return 'number';
    }

    if ($number > round((float) $max, 4)) {
        return 'max';
    }

    return '';
}

/**
 * Whether a posted length, width or height is a number greater than zero.
 * The columns are unsigned decimals and the site runs a non-strict sql_mode,
 * so a negative or non-numeric value would be stored as 0. A "== 0" test let
 * those through on PHP 8, where "abc" == 0 and "-5" == 0 are both false.
 *
 * @param mixed $value
 * @return bool
 */
function pg_container_dimension_positive($value)
{
    return pg_container_number_check($value, INF, true) === '';
}

/**
 * A stored length, width or weight for a list: the panel's separators, up to
 * the column's four places, trailing zeros dropped (9.5000 is 9.5, 12.0000
 * is 12).
 *
 * @param mixed $value
 * @return string
 */
function pg_container_format_measure($value)
{
    $s = pg_number_separators();
    $text = pg_format_number($value, 4);

    if (strpos($text, $s['decimal']) !== false) {
        $text = rtrim(rtrim($text, '0'), $s['decimal']);
    }

    return $text;
}

/**
 * A checked length, width, height or weight as the figure to store: a
 * decimal comma read as a point, rounded to the column's four places, blank
 * as 0.
 *
 * @param mixed $value
 * @return string
 */
function pg_container_figure($value)
{
    $text = is_scalar($value) ? trim((string) $value) : '';

    if ((strpos($text, ',') !== false) && (strpos($text, '.') === false)) {
        $text = str_replace(',', '.', $text);
    }

    return is_numeric($text) ? number_format(round((float) $text, 4), 4, '.', '') : '0';
}

/**
 * Write a checked container (pg_container_form_validate()) over the stored
 * one: the figures at the columns' four places, the cost in minor units,
 * and who changed it when. Used by the edit screen and the list's quick
 * edit (includes/fn/container_list_edit.php).
 *
 * @param liveform $liveform
 * @param int      $container_id
 * @param int      $user_id
 * @return bool    whether the query ran
 */
function pg_container_form_save($liveform, $container_id, $user_id)
{
    $cost = (int) round(pg_parse_amount($liveform->get_field_value('cost')) * 100);

    return (bool) db_q(
        "UPDATE containers
        SET
            name = ?,
            enabled = ?,
            length = ?,
            width = ?,
            height = ?,
            weight = ?,
            cost = ?,
            last_modified_user_id = ?,
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = ?",
        array(
            (string) $liveform->get_field_value('name'),
            $liveform->get_field_value('enabled') ? '1' : '0',
            pg_container_figure($liveform->get_field_value('length')),
            pg_container_figure($liveform->get_field_value('width')),
            pg_container_figure($liveform->get_field_value('height')),
            pg_container_figure($liveform->get_field_value('weight')),
            (string) $cost,
            (string) (int) $user_id,
            (string) (int) $container_id,
        ));
}
