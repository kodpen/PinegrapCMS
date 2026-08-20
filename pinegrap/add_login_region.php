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
validate_area_access($user, 'designer');

include_once('liveform.class.php');
$liveform = new liveform('add_login_region');

// if add login region screen has not been submitted already
if (!$_POST) {
    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Login Region'))),
        'extra classes'=>'page',
        'icon'=>'page',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Login Region'))),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Login Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_login_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Login Region'))))),
        ]) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create the messages displayed in the login region before and after users log in.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Login Region'))) . '">[' . lang(array('string'=>'new {var:1} name','vars'=>lang('login region'))) . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_login_region.php" method="post">
                    ' . get_codemirror_includes() . '
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 my-2">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Login Region'))) . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<login>') . '</div>
                                                ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100')) . '
                                                <div class="input-group-text">' . h('</login>') . '</div>
                                            </div>
                                        </div>
                                        <h5 class="mt-5">' . lang('Not Logged In') . '</h5>
                                        <div class="col-12 my-2">
                                            <label for="name" class="form-label">' . lang('Header') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'not_logged_in_header', 'id'=>'not_logged_in_header', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . get_codemirror_javascript(array('id' => 'not_logged_in_header', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'login_form', 'id'=>'login_form', 'value'=>'1', 'class'=>'form-check-input', 'checked'=>'checked')) . '
                                                <label class="form-check-label" for="login_form">' . lang('Show Login Form') . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="name" class="form-label">' . lang('Footer') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'not_logged_in_footer', 'id'=>'not_logged_in_footer', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . get_codemirror_javascript(array('id' => 'not_logged_in_footer', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <h5 class="mt-5">' . lang('Logged In') . '</h5>
                                        <div class="col-12 my-2">
                                            <label for="name" class="form-label">' . lang('Header') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'logged_in_header', 'id'=>'logged_in_header', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . get_codemirror_javascript(array('id' => 'logged_in_header', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                        <div class="col-12 my-2">
                                            <label for="name" class="form-label">' . lang('Footer') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'logged_in_footer', 'id'=>'logged_in_footer', 'style'=>'width: 600px; height: 200px')) . '
                                            ' . get_codemirror_javascript(array('id' => 'logged_in_footer', 'code_type' => 'mixed', 'height' => '200px')) . '
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
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
    
    // validate the required fields
    $liveform->validate_required_field('name', lang('Name is required.'));
    
    // if there is an error, forward user back to form
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_login_region.php');
        exit();
    }
    
    // check to see if name is already in use
    $query =
        "SELECT id
        FROM login_regions
        WHERE (name = '" . escape($liveform->get_field_value('name')) . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if name is already in use, prepare error and forward user back to form
    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        
        // forward user back to form
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
            ('" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('not_logged_in_header')) . "',
            '" . escape($liveform->get_field_value('login_form')) . "',
            '" . escape($liveform->get_field_value('not_logged_in_footer')) . "',
            '" . escape($liveform->get_field_value('logged_in_header')) . "',
            '" . escape($liveform->get_field_value('logged_in_footer')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('login region'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);
    $liveform->remove_form();
    $liveform_view_regions = new liveform('view_regions');
    $liveform_view_regions->add_notice(lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Login Region') )));
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_login_regions');
}
?>