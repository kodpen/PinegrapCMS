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

$liveform = new liveform('calendars');

if (isset($_GET['calendar_id']) == true) {
    $_SESSION['software']['calendars']['calendar_id'] = $_GET['calendar_id'];
}

if (isset($_GET['view']) == true) {
    $_SESSION['software']['calendars']['view'] = $_GET['view'];
}

if (isset($_GET['status']) == true) {
    $_SESSION['software']['calendars']['status'] = $_GET['status'];
}

if (isset($_GET['date']) == true) {
    $_SESSION['software']['calendars']['date'] = $_GET['date'];
}

// get all calendars for calendar pick list
$query =
    "SELECT
       id,
       name
    FROM calendars
    ORDER BY name";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$calendars = array();

// loop through all calendars in order to prepare calendar pick list
while ($row = mysqli_fetch_assoc($result)) {
    // if user has access to calendar, then include this calendar
    if (validate_calendar_access($row['id']) == true) {
        $calendars[] = $row;
        
        // Get current calendar name
        if ($row['id'] == ($_SESSION['software']['calendars']['calendar_id'] ?? '')) {
            $calendar_name = $row['name'];
        }
    }
}
$output_edit_calendar_button = '';
$pg_breadcrumb_items = array(
    array('label' => lang('Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendars.php'),
);
if($calendar_name == ''){
    $calendar_name = lang('All Calendars');
    $pg_breadcrumb_items[] = array('label' => lang('All Calendars'));
}else{
    $pg_breadcrumb_items[] = array('label' => lang('View Calendars'));
    $output_edit_calendar_button ='<a class="btn btn-sm btn-primary m-1 " href="edit_calendar.php?id=' . h(($_SESSION['software']['calendars']['calendar_id'] ?? '')) . '" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">edit</span>' . lang(array('string'=>'Edit Calendar Properties') ) . '</a>';
}
echo


pg_page_shell(
    array(
        'title'=> lang(array('string'=>'View {var:1}','vars'=>lang('Calendars'))),
        'extra classes'=>'calendar',
        'icon'=>'calendar', 
        'heading'=> lang(array('string'=>'View {var:1}','vars'=>lang('Calendars'))),
        'cancel'=>array(
            'enable'=>'true',
            'title'=>lang('Return to Calendars'),
            'url'=>'view_calendars.php'
        ),
        'breadcrumb' => $pg_breadcrumb_items,
    )
) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
<h2 class="d-inline-block " data-bs-content="' . lang('View and update this calendar.') . '" title="' . lang('View Calendars') . '">[' . h($calendar_name) . ']</h2>
                    ' . $output_subheading . '
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_calendar_event.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create Calendar Event') ) . '</a>
                        ' . $output_edit_calendar_button . '
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                ' . get_calendar(($_SESSION['software']['calendars']['calendar_id'] ?? ''), $calendars, ($_SESSION['software']['calendars']['view'] ?? ''), ($_SESSION['software']['calendars']['status'] ?? ''), $user, ($_SESSION['software']['calendars']['date'] ?? ''), PATH . SOFTWARE_DIRECTORY . '/edit_calendar_event.php') . '
            </div>
        </div>
    </div>
</main>' . output_footer();

$liveform->remove_form();