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

// if user has a user role and if they do not have access to edit any ad regions, output error
if (($user['role'] == 3) && (count(get_items_user_can_edit('ad_regions', $user['id'])) == 0)) {
    log_activity(lang('access denied because user does not have access to ads'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');
$liveform = new liveform('add_ad');

// if the form has not been submitted
if (!$_POST) {
    print
        pg_page_shell([
            'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Ad'))),
            'extra classes'=>'ads',
            'icon'=>'ads',
            'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Ad'))),
            'heading_description' => lang('Create a new ad and assign it to any existing ad region.'),
            'cancel'=>array('enable'=>'true','url'=>'view_ads.php')
        ,
            'breadcrumb' => array(array('label' => lang('All My Ads'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php?filter=all_ad_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Ad'))))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . get_wysiwyg_editor_code(array('content_textarea', 'caption')) . '
                    ' . $liveform->output_errors() . '
                    ' . $liveform->output_notices() . '
                    
                    <form name="form" action="add_ad.php" method="post">
                        ' . get_token_field() . '
                        ' . pg_ad_form_cards($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
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
    
    $liveform->remove_form();

} else {
    validate_token_field();

    $liveform->add_fields_to_session();
    
    pg_ad_form_validate($liveform, $user, null);
    
    // if there is an error, forward user back to add ad screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_ad.php');
        exit();
    }
    
    // create ad
    $query =
        "INSERT ads (
            name,
            content,
            caption,
            ad_region_id,
            label,
            sort_order,
            created_user_id, 
            created_timestamp,
            last_modified_user_id, 
            last_modified_timestamp)
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            UNIX_TIMESTAMP(),
            ?,
            UNIX_TIMESTAMP())";
    $result = db_q($query, array_merge(pg_ad_form_values($liveform), array(
        (string) $user['id'],
        (string) $user['id'],
    ))) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'ad ({var:1}) was created','vars'=>$liveform->get_field_value('name') )), $_SESSION['sessionusername']);



    $liveform->remove_form();
    $liveform_view_ads = new liveform('view_ads');
    $liveform_view_ads->add_notice(lang('The ad has been created.'));

    // forward user to view ads screen
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_ads.php');
}
?>