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
$liveform = new liveform('edit_ship_date_adjustment');

$ship_date_adjustment = db_item(
    "SELECT
        zip_code_prefix,
        shipping_method_id,
        adjustment
    FROM ship_date_adjustments
    WHERE id = '" . escape($_REQUEST['id']) . "'");

// If the form has not been submitted, then output it.
if (!$_POST) {

    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('zip_code_prefix', $ship_date_adjustment['zip_code_prefix']);
        $liveform->assign_field_value('shipping_method_id', $ship_date_adjustment['shipping_method_id']);

        if ($ship_date_adjustment['adjustment'] < 0) {
            $liveform->assign_field_value('adjustment_days', -$ship_date_adjustment['adjustment']);
            $liveform->assign_field_value('adjustment_type', 'earlier');
        } else {
            $liveform->assign_field_value('adjustment_days', $ship_date_adjustment['adjustment']);
            $liveform->assign_field_value('adjustment_type', 'later');
        }
    }

    // Get shipping methods for pick list.
    $shipping_methods = db_items(
        "SELECT id, name, code
        FROM shipping_methods
        ORDER BY name ASC, code ASC");

    $output_shipping_method_options = '<option value=""></option>';
    foreach ($shipping_methods as $shipping_method) {
        $label = h($shipping_method['name']);
        if ($shipping_method['code'] != '') {
            $label .= ' (' . h($shipping_method['code']) . ')';
        }
        $selected = ($liveform->get_field_value('shipping_method_id') == $shipping_method['id']) ? ' selected' : '';
        $output_shipping_method_options .= '<option value="' . h($shipping_method['id']) . '"' . $selected . '>' . $label . '</option>';
    }

    $adjustment_type_value = $liveform->get_field_value('adjustment_type') ?: 'later';
    $output_adjustment_type_options =
        '<option value="earlier"' . ($adjustment_type_value == 'earlier' ? ' selected' : '') . '>' . lang('earlier') . '</option>' .
        '<option value="later"' . ($adjustment_type_value == 'later' ? ' selected' : '') . '>' . lang('later') . '</option>';

    echo
        pg_page_shell([
            'title'=> lang('Edit Ship Date Adjustment'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Edit Ship Date Adjustment'),
            'cancel'=>array('enable'=>'true','url'=>'view_ship_date_adjustments.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Ship Date Adjustments'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ship_date_adjustments.php'), array('label' => lang('Edit Ship Date Adjustment'))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break" data-bs-content="' . lang('Update this adjustment for a specific zip code prefix and shipping method.') . '" title="' . lang('Edit Ship Date Adjustment') . '">' . h($ship_date_adjustment['zip_code_prefix']) . '</h2>
                        </div>
                    </div>
                    <form action="edit_ship_date_adjustment.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                        <div class="row">
                            <div class="col-12 mb-5">
                                <div class="card my-4 h-100">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="zip_code_prefix" class="form-label">' . lang('Zip Code Prefix') . ' <span class="text-muted small">' . lang('(first 3 numbers)') . '</span></label>
                                                <input type="text" name="zip_code_prefix" id="zip_code_prefix" maxlength="3" value="' . h($liveform->get_field_value('zip_code_prefix')) . '" class="form-control' . ($liveform->check_field_error('zip_code_prefix') ? ' is-invalid' : '') . '" />
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-8 my-2">
                                                <label for="shipping_method_id" class="form-label">' . lang('Shipping Method') . '</label>
                                                <select name="shipping_method_id" id="shipping_method_id" class="form-select' . ($liveform->check_field_error('shipping_method_id') ? ' is-invalid' : '') . '">' . $output_shipping_method_options . '</select>
                                            </div>
                                            <div class="col-12 col-md-auto my-2">
                                                <label class="form-label">' . lang('Adjustment') . '</label>
                                                <div class="input-group">
                                                    <input  style="width:100px;max-width:100%;" type="text" name="adjustment_days" id="adjustment_days" maxlength="3" value="' . h($liveform->get_field_value('adjustment_days')) . '" class="form-control' . ($liveform->check_field_error('adjustment_days') ? ' is-invalid' : '') . '"/>
                                                    <span class="input-group-text">' . lang('days') . '</span>
                                                    <select style="width:100px;max-width:100%;" name="adjustment_type" id="adjustment_type" class="form-select' . ($liveform->check_field_error('adjustment_type') ? ' is-invalid' : '') . '">' . $output_adjustment_type_options . '</select>
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
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang('Saving') . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-danger" data-loading-content="' . lang('Deleting') . '" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('ship date adjustment')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text">' . lang('Delete') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </main>' .
        output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted, so process it.
} else {
    validate_token_field();

    $liveform->add_fields_to_session();

    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        db("DELETE FROM ship_date_adjustments WHERE id = '" . escape($liveform->get_field_value('id')) . "'");

        log_activity(lang(array('string' => 'ship date adjustment ({var:1}) was deleted', 'vars' => array($ship_date_adjustment['zip_code_prefix']))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view = new liveform('view_ship_date_adjustments');
        $liveform_view->add_notice(lang('The ship date adjustment has been deleted.'));

        

        go(PATH . SOFTWARE_DIRECTORY . '/view_ship_date_adjustments.php');

    } else {
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
            &&
            (
                db_value(
                    "SELECT COUNT(*)
                    FROM ship_date_adjustments
                    WHERE
                        (zip_code_prefix = '" . escape($liveform->get_field_value('zip_code_prefix')) . "')
                        AND (shipping_method_id = '" . escape($liveform->get_field_value('shipping_method_id')) . "')
                        AND (id != '" . escape($liveform->get_field_value('id')) . "')")
                > 0
            )
        ) {
            $liveform->mark_error('zip_code_prefix', lang('Sorry, there is already a ship date adjustment for the zip code prefix and shipping method you selected.'));
            $liveform->mark_error('shipping_method_id');
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

        if ($liveform->check_form_errors() == true) {
            go($_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        }

        if ($liveform->get_field_value('adjustment_type') == 'earlier') {
            $adjustment = -$liveform->get_field_value('adjustment_days');
        } else {
            $adjustment = $liveform->get_field_value('adjustment_days');
        }

        db(
            "UPDATE ship_date_adjustments
            SET
                zip_code_prefix = '" . escape($liveform->get_field_value('zip_code_prefix')) . "',
                shipping_method_id = '" . escape($liveform->get_field_value('shipping_method_id')) . "',
                adjustment = '" . escape($adjustment) . "',
                last_modified_user_id = '" . USER_ID . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($liveform->get_field_value('id')) . "'");

        log_activity(lang(array('string' => 'ship date adjustment ({var:1}) was modified', 'vars' => array($ship_date_adjustment['zip_code_prefix']))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view = new liveform('view_ship_date_adjustments');
        $liveform_view->add_notice(lang('The ship date adjustment has been saved.'));

        

        go(PATH . SOFTWARE_DIRECTORY . '/view_ship_date_adjustments.php');
    }
}
