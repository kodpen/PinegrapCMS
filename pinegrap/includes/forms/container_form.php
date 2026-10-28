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
 * The options card: name, enabled, dimensions (inches or cm), weight
 * (pounds or kg) and cost. Only the inch and pound fields are posted; the
 * metric fields are converted into them by pg_container_form_script().
 *
 * @param liveform $liveform
 * @return string  HTML
 */
function pg_container_form_card($liveform)
{
    return '
                        <div class="row">
                            <div class="col-12 mb-12">
                                <div class="card my-7 h-100">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . lang('Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="name" class="form-label">' . lang('Name') . '</label>
                                                <input type="text" name="name" id="name" maxlength="100" value="' . h($liveform->get_field_value('name')) . '" class="form-control' . ($liveform->check_field_error('name') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3 d-flex align-items-end">
                                                <div class="form-field">
                                                    <input type="checkbox" name="enabled" id="enabled" value="1" class="check"' . ($liveform->get_field_value('enabled') ? ' checked' : '') . ' />
                                                    <label for="enabled" class="">' . lang('Enabled') . '</label>
                                                </div>
                                            </div>
                                            <div class="col-12 my-3">
                                                <label class="form-label">' . lang('Dimensions') . '</label>
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
                                                <div id="length_inches" class="input-group" style="max-width:400px;">
                                                    <span class="input-group-text">L</span>
                                                    <input type="number" step="any" id="length" name="length" value="' . h($liveform->get_field_value('length')) . '" class="form-control' . ($liveform->check_field_error('length') ? ' is-invalid' : '') . '" placeholder="' . lang('Length') . '" />
                                                    <span class="input-group-text">W</span>
                                                    <input type="number" step="any" id="width" name="width" value="' . h($liveform->get_field_value('width')) . '" class="form-control' . ($liveform->check_field_error('width') ? ' is-invalid' : '') . '" placeholder="' . lang('Width') . '" />
                                                    <span class="input-group-text">H</span>
                                                    <input type="number" step="any" id="height" name="height" value="' . h($liveform->get_field_value('height')) . '" class="form-control' . ($liveform->check_field_error('height') ? ' is-invalid' : '') . '" placeholder="' . lang('Height') . '" />
                                                    <span class="input-group-text">' . lang('in') . '</span>
                                                </div>
                                                <div id="length_cm" class="input-group d-none" style="max-width:400px;">
                                                    <span class="input-group-text">L</span>
                                                    <input type="number" step="any" id="lengthcm" name="lengthcm" class="form-control" placeholder="' . lang('Length') . '" />
                                                    <span class="input-group-text">W</span>
                                                    <input type="number" step="any" id="widthcm" name="widthcm" class="form-control" placeholder="' . lang('Width') . '" />
                                                    <span class="input-group-text">H</span>
                                                    <input type="number" step="any" id="heightcm" name="heightcm" class="form-control" placeholder="' . lang('Height') . '" />
                                                    <span class="input-group-text">' . lang('cm') . '</span>
                                                </div>
                                            </div>
                                            <div class="col-12 md:col-6 my-3">
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
                                                    <input type="number" step="any" id="weight" name="weight" value="' . h($liveform->get_field_value('weight')) . '" class="form-ghost' . ($liveform->check_field_error('weight') ? ' is-invalid' : '') . '" />
                                                </label>
                                                <label id="weight_kg" class="form-control form-adorn form-adorn-end d-none" style="max-width:200px;">
                                                    <span class="form-adorn-text" aria-hidden="true">' . lang('kg') . '</span>
                                                    <input type="number" step="any" id="weightkg" name="weightkg" class="form-ghost" aria-labelledby="weight_label" />
                                                </label>
                                            </div>
                                            <div class="col-12 md:col-6 lg:col-4 my-3">
                                                <label for="cost" class="form-label">' . lang('Cost') . '</label>
                                                <label class="form-control form-adorn" style="max-width:200px;">
                                                    <span class="form-adorn-text" aria-hidden="true">' . BASE_CURRENCY_SYMBOL . '</span>
                                                    <input type="number" step="any" id="cost" name="cost" value="' . h($liveform->get_field_value('cost')) . '" class="form-ghost' . ($liveform->check_field_error('cost') ? ' is-invalid' : '') . '" />
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
';
}

/**
 * The script that switches the units and keeps the metric fields and the
 * posted inch and pound fields in step; printed after the form.
 *
 * @return string  HTML
 */
function pg_container_form_script()
{
    return '
                    <script>
                    $(function() {
                        $("input[name=length_type]").on("change", function() {
                            var isInches = $("#length_type_inches").is(":checked");
                            $("#length_inches").toggleClass("d-none", !isInches);
                            $("#length_cm").toggleClass("d-none", isInches);
                        });
                        $("#length").on("input", function() { $("#lengthcm").val($(this).val() * 2.54); });
                        $("#width").on("input", function() { $("#widthcm").val($(this).val() * 2.54); });
                        $("#height").on("input", function() { $("#heightcm").val($(this).val() * 2.54); });
                        $("#lengthcm").on("input", function() { $("#length").val($(this).val() * 0.3937007874); });
                        $("#widthcm").on("input", function() { $("#width").val($(this).val() * 0.3937007874); });
                        $("#heightcm").on("input", function() { $("#height").val($(this).val() * 0.3937007874); });
                        $("input[name=weight_type]").on("change", function() {
                            var isLbs = $("#weight_type_pounds").is(":checked");
                            $("#weight_pound").toggleClass("d-none", !isLbs);
                            $("#weight_kg").toggleClass("d-none", isLbs);
                        });
                        $("#weight").on("input", function() { $("#weightkg").val($(this).val() * 0.45359237); });
                        $("#weightkg").on("input", function() { $("#weight").val($(this).val() * 2.20462262); });
                    });
                    </script>
';
}

/**
 * Check a posted container: name, length, width and height are required,
 * the dimensions must not be zero and the name must not be used by another
 * container. Errors are marked on the liveform.
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

    if ($liveform->check_field_error('name') == false) {
        $result = db_q("SELECT COUNT(*) AS count FROM containers WHERE (name = ?) AND (id != ?)", array((string) $liveform->get_field_value('name'), (string) $container_id));
        $row = $result ? mysqli_fetch_assoc($result) : null;

        if ($row && ($row['count'] != 0)) {
            $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
        }
    }

    foreach (array(
        'length' => lang('Sorry, the length must be greater than zero.'),
        'width' => lang('Sorry, the width must be greater than zero.'),
        'height' => lang('Sorry, the height must be greater than zero.')) as $field => $message) {
        if (($liveform->check_field_error($field) == false) && ($liveform->get_field_value($field) == 0)) {
            $liveform->mark_error($field, $message);
        }
    }
}
