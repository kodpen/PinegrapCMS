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
validate_calendars_access($user);

// if user does not have access to add calendar, output error
if ($user['role'] == 3) {
    log_activity(lang('access denied to add calendar'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

$liveform = new liveform('add_calendar');

// if the form has not been submitted
if (!$_POST) {
    echo 
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Calendar'))),
            'extra classes'=>'calendar',
            'icon'=>'calendar',
            'heading'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Calendar'))),
            'cancel'=>array(
                'enable'=>'true',
                'title'=>lang('Return to Calendars'),
                'url'=>'view_calendars.php'
            ),
            'breadcrumb' => array(
                array('label' => lang('Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendars.php'),
                array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Calendar')))),
            )
        )
    )    . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
                        <h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new calendar to be displayed on any calendar pages.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Calendar'))) . '">[' . lang(array('string'=>'new {var:1} name','vars'=>lang('calendar'))) . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_calendar.php" method="post">
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
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Calendar'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control add-header-content-updater', 'maxlength'=>'100', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
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

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    $liveform->validate_required_field('name', lang('Name is required.'));
    
    // if there is an error, forward user back to add calendar screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_calendar.php');
        exit();
    }
    
    // check to see if name is already in use by a different calendar
    $query =
        "SELECT id
        FROM calendars
        WHERE (name = '" . escape($liveform->get_field_value('name')) . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if name is already in use by a different calendar, prepare error and forward user back to screen
    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        
        // forward user to add calendar screen
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_calendar.php');
        exit();
    }
    
    // create calendar
    $query =
        "INSERT INTO calendars (
            name,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            '" . escape($liveform->get_field_value('name')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('calendar'),$liveform->get_field_value('name') ))), $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_calendars = new liveform('view_calendars');
    $liveform_view_calendars->add_notice(lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Calendar') )));
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_calendars.php');
    
    
}