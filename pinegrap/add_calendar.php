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
validate_calendars_access($user);

// if user does not have access to add calendar, output error
if ($user['role'] == 3) {
    log_activity(lang('access denied to add calendar'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/calendar_form.php');
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
            'heading_description' => lang('Create a new calendar to be displayed on any calendar pages.'),
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
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="add_calendar.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_calendar_form_card($liveform) . '
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

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    pg_calendar_form_validate_name($liveform, null);
    
    // if there is an error, forward user back to add calendar screen
    if ($liveform->check_form_errors() == true) {
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
            ?,
            ?,
            UNIX_TIMESTAMP(),
            ?,
            UNIX_TIMESTAMP())";
    $result = db_q($query, array((string) $liveform->get_field_value('name'), (string) $user['id'], (string) $user['id'])) or output_error('Query failed.');

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('calendar'),$liveform->get_field_value('name') ))), $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_calendars = new liveform('view_calendars');
    $liveform_view_calendars->add_notice(lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Calendar') )));
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_calendars.php');
    
    
}