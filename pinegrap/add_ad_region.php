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
validate_area_access($user, 'designer');

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_region_form.php');
$liveform = new liveform('add_ad_region');

// if the form has not been submitted
if (!$_POST) {
    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Ad Region'))),
        'extra classes'=>'design pg-measure-edit',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Ad Region'))),
        'heading_description' => lang('Create an ad region to display rotating ad content.'),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php?filter=all_ad_regions')
    ,
            'breadcrumb' => array(array('label' => lang('All Ad Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Ad Region'))))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_ad_region.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_ad_region_form_layout(
                        pg_ad_region_form_card($liveform, $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100', 'required'=>'required')), $liveform->prefill())
                        . pg_ad_region_form_status($liveform, $liveform->prefill()),
                        '
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
                    ) . '
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    pg_ad_region_form_validate_name($liveform, null);
    pg_ad_region_form_validate_behavior($liveform);
    
    // if there is an error, forward user back to add ad region screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_ad_region.php');
        exit();
    }
    
    // create ad region
    pg_ad_region_save(0, (string) $liveform->get_field_value('name'), pg_ad_region_form_values($liveform), $user['id']) or output_error('Query failed.');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('ad region'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_regions = new liveform('view_regions');
    $liveform_view_regions->add_notice(lang('The ad region has been created.'));
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_ad_regions');
    
   
}
?>