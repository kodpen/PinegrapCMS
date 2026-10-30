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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/login_region_form.php');
$liveform = new liveform('add_login_region');

// if add login region screen has not been submitted already
if (!$_POST) {
    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Login Region'))),
        'extra classes'=>'page',
        'icon'=>'page',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Login Region'))),
        'heading_description' => lang('Create the messages displayed in the login region before and after users log in.'),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php?filter=all_login_regions')
    ,
            'breadcrumb' => array(array('label' => lang('All Login Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_login_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Login Region'))))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_login_region.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_login_region_form_card($liveform, $liveform->prefill()) . '
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

// else, the form was submitted so create the login region
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    pg_login_region_form_validate_name($liveform, null);
    
    // if there is an error, forward user back to form
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_login_region.php');
        exit();
    }
    
    // insert row into login_regions table
    $query = 
        "INSERT INTO login_regions
            (name, 
            not_logged_in_header,
            login_form,
            not_logged_in_footer,
            logged_in_header,
            logged_in_footer,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES
            (?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            UNIX_TIMESTAMP(),
            ?,
            UNIX_TIMESTAMP())";
    $result = db_q($query, array_merge(pg_login_region_form_values($liveform), array(
        (string) $user['id'],
        (string) $user['id'],
    ))) or output_error('Query failed');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('login region'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);
    $liveform->remove_form();
    $liveform_view_regions = new liveform('view_regions');
    $liveform_view_regions->add_notice(lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Login Region') )));
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_login_regions');
}
?>