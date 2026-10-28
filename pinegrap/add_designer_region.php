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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/designer_region_form.php');

if (!isset($_POST['name'])) {
    print 
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Designer Region'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Designer Region'))),
        'heading_description' => lang('Create a designer region of shared content that can be added to any page style and updated during page editing by any Site designer.'),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Designer Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_designer_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Designer Region'))))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="add_designer_region.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_designer_region_form_card() . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
} else {
    validate_token_field();
    
    include_once('liveform.class.php');
    
    list($name, $content) = pg_designer_region_form_values($_POST);

    $query = "INSERT INTO cregion (cregion_name, cregion_content, cregion_designer_type, cregion_user, cregion_timestamp) "
            ."VALUES (?, ?, 'yes', ?, UNIX_TIMESTAMP())";
    // insert row into region table
    $result = db_q($query, array($name, $content, (int) $user['id'])) or output_error('Query failed');
    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('designer region'), $name))), $_SESSION['sessionusername']);
    $notice = lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Designer Region') ));
    
    $liveform_view_styles = new liveform('view_regions');
    $liveform_view_styles->add_notice($notice);
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_designer_regions');
}
?>