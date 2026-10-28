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

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ship_date_adjustment_form.php');
$liveform = new liveform('add_ship_date_adjustment');

// If the form has not been submitted, then output it.
if (!$_POST) {

    echo
        pg_page_shell([
            'title'=> lang('Create Ship Date Adjustment'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Create Ship Date Adjustment'),
            'heading_description' => lang('Create a new adjustment for a specific zip code prefix and shipping method.'),
            'cancel'=>array('enable'=>'true','url'=>'view_ship_date_adjustments.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Ship Date Adjustments'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ship_date_adjustments.php'), array('label' => lang('Create Ship Date Adjustment'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    
                    <form method="post">
                        ' . get_token_field() . '
                        ' . pg_ship_date_adjustment_form_card($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang('Create') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();

    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {
    validate_token_field();

    $liveform->add_fields_to_session();

    pg_ship_date_adjustment_form_check($liveform, null);

    if ($liveform->check_form_errors() == true) {
        go(PATH . SOFTWARE_DIRECTORY . '/add_ship_date_adjustment.php');
    }

    $adjustment = pg_ship_date_adjustment_form_adjustment($liveform->get_field_value('adjustment_days'), $liveform->get_field_value('adjustment_type'));

    db(
        "INSERT INTO ship_date_adjustments (
            zip_code_prefix,
            shipping_method_id,
            adjustment,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('zip_code_prefix')) . "',
            '" . escape($liveform->get_field_value('shipping_method_id')) . "',
            '" . escape($adjustment) . "',
            '" . USER_ID . "',
            UNIX_TIMESTAMP(),
            '" . USER_ID . "',
            UNIX_TIMESTAMP())");

    log_activity(lang(array('string' => 'ship date adjustment ({var:1}) was created', 'vars' => array($liveform->get_field_value('zip_code_prefix')))), $_SESSION['sessionusername']);
    $liveform->remove_form();
    $liveform_view = new liveform('view_ship_date_adjustments');
    $liveform_view->add_notice(lang('The ship date adjustment has been created.'));

    

    go(PATH . SOFTWARE_DIRECTORY . '/view_ship_date_adjustments.php');
}
