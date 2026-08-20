<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('add_container');

// If the form has not been submitted, then output it.
if (!$_POST) {

    // Pre-populate enabled as checked by default on first load.
    if ($liveform->field_in_session('name') == false) {
        $liveform->assign_field_value('enabled', 1);
    }

    echo
        pg_page_shell([
            'title'=> lang('Create Container'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Create Container'),
            'cancel'=>array('enable'=>'true','url'=>'view_containers.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Containers'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_containers.php'), array('label' => lang('Create Container'))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new shipping container (e.g. box) that products are packaged in.') . '" title="' . lang('Create Container') . '">[' . lang('Container') . ']</h2>
                        </div>
                    </div>
                    <form method="post">
                        ' . get_token_field() . '
                        <div class="row">
                            <div class="col-12 mb-5">
                                <div class="card my-4 h-100">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="name" class="form-label">' . lang('Name') . '</label>
                                                <input type="text" name="name" id="name" maxlength="100" value="' . h($liveform->get_field_value('name')) . '" class="form-control' . ($liveform->check_field_error('name') ? ' is-invalid' : '') . ' add-header-content-updater" />
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2 d-flex align-items-end">
                                                <div class="form-check">
                                                    <input type="checkbox" name="enabled" id="enabled" value="1" class="form-check-input"' . ($liveform->get_field_value('enabled') ? ' checked' : '') . ' />
                                                    <label for="enabled" class="form-check-label">' . lang('Enabled') . '</label>
                                                </div>
                                            </div>
                                            <div class="col-12 my-2">
                                                <label class="form-label">' . lang('Dimensions') . '</label>
                                                <div class="mb-2">
                                                    <div class="form-check form-check-inline">
                                                        <input type="radio" class="form-check-input" id="length_type_inches" name="length_type" value="inches" checked />
                                                        <label class="form-check-label" for="length_type_inches">' . lang('Inches') . '</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input type="radio" class="form-check-input" id="length_type_cm" name="length_type" value="cm" />
                                                        <label class="form-check-label" for="length_type_cm">' . lang('Cm') . '</label>
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
                                            <div class="col-12 col-md-6 my-2">
                                                <label class="form-label">' . lang('Weight') . '</label>
                                                <div class="mb-2">
                                                    <div class="form-check form-check-inline">
                                                        <input type="radio" class="form-check-input" id="weight_type_pounds" name="weight_type" value="pounds" checked />
                                                        <label class="form-check-label" for="weight_type_pounds">' . lang('Pounds') . '</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input type="radio" class="form-check-input" id="weight_type_kg" name="weight_type" value="kg" />
                                                        <label class="form-check-label" for="weight_type_kg">' . lang('Kg') . '</label>
                                                    </div>
                                                </div>
                                                <div id="weight_pound" class="input-group" style="max-width:200px;">
                                                    <input type="number" step="any" id="weight" name="weight" value="' . h($liveform->get_field_value('weight')) . '" class="form-control' . ($liveform->check_field_error('weight') ? ' is-invalid' : '') . '" />
                                                    <span class="input-group-text">' . lang('lbs') . '</span>
                                                </div>
                                                <div id="weight_kg" class="input-group d-none" style="max-width:200px;">
                                                    <input type="number" step="any" id="weightkg" name="weightkg" class="form-control" />
                                                    <span class="input-group-text">' . lang('kg') . '</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="cost" class="form-label">' . lang('Cost') . '</label>
                                                <div class="input-group" style="max-width:200px;">
                                                    <span class="input-group-text">' . BASE_CURRENCY_SYMBOL . '</span>
                                                    <input type="number" step="any" id="cost" name="cost" value="' . h($liveform->get_field_value('cost')) . '" class="form-control' . ($liveform->check_field_error('cost') ? ' is-invalid' : '') . '" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang('Creating') . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang('Create') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
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
                </div>
            </div>
        </main>' .
        output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    $liveform->validate_required_field('name', lang('Name is required.'));
    $liveform->validate_required_field('length', lang('Length is required.'));
    $liveform->validate_required_field('width', lang('Width is required.'));
    $liveform->validate_required_field('height', lang('Height is required.'));

    if (
        ($liveform->check_field_error('name') == false)
        && (db_value("SELECT COUNT(*) FROM containers WHERE name = '" . escape($liveform->get_field_value('name')) . "'") != 0)
    ) {
        $liveform->mark_error('name', lang('Sorry, the name that you entered is already in use, so please enter a different name.'));
    }

    if (($liveform->check_field_error('length') == false) && ($liveform->get_field_value('length') == 0)) {
        $liveform->mark_error('length', lang('Sorry, the length must be greater than zero.'));
    }

    if (($liveform->check_field_error('width') == false) && ($liveform->get_field_value('width') == 0)) {
        $liveform->mark_error('width', lang('Sorry, the width must be greater than zero.'));
    }

    if (($liveform->check_field_error('height') == false) && ($liveform->get_field_value('height') == 0)) {
        $liveform->mark_error('height', lang('Sorry, the height must be greater than zero.'));
    }

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_container.php');
    }

    // Convert cost to cents.
    $cost = (float) $liveform->get_field_value('cost') * 100;

    db(
        "INSERT INTO containers (
            name,
            enabled,
            length,
            width,
            height,
            weight,
            cost,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('enabled')) . "',
            '" . escape($liveform->get_field_value('length')) . "',
            '" . escape($liveform->get_field_value('width')) . "',
            '" . escape($liveform->get_field_value('height')) . "',
            '" . escape($liveform->get_field_value('weight')) . "',
            '" . escape($cost) . "',
            '" . USER_ID . "',
            UNIX_TIMESTAMP(),
            '" . USER_ID . "',
            UNIX_TIMESTAMP())");

    log_activity(lang(array('string' => 'container ({var:1}) was created', 'vars' => array($liveform->get_field_value('name')))), $_SESSION['sessionusername']);

    $liveform->remove_form();
    $liveform_view = new liveform('view_containers');
    $liveform_view->add_notice(lang('The container has been created.'));

    go(PATH . SOFTWARE_DIRECTORY . '/view_containers.php');
}
