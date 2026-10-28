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
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM ship_date_adjustments WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');
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

        foreach (pg_ship_date_adjustment_form_fields($ship_date_adjustment['adjustment']) as $name => $value) {
            $liveform->assign_field_value($name, $value);
        }
    }

    echo
        pg_page_shell([
            'title'=> lang('Edit Ship Date Adjustment'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Edit Ship Date Adjustment'),
            'heading_description' => lang('Update this adjustment for a specific zip code prefix and shipping method.'),
            'cancel'=>array('enable'=>'true','url'=>'view_ship_date_adjustments.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Ship Date Adjustments'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ship_date_adjustments.php'), array('label' => lang('Edit Ship Date Adjustment'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    
                    <form action="edit_ship_date_adjustment.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                        ' . pg_ship_date_adjustment_form_card($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('ship date adjustment')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>
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
        pg_ship_date_adjustment_form_check($liveform, (string) $liveform->get_field_value('id'));

        if ($liveform->check_form_errors() == true) {
            go($_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        }

        $adjustment = pg_ship_date_adjustment_form_adjustment($liveform->get_field_value('adjustment_days'), $liveform->get_field_value('adjustment_type'));

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
