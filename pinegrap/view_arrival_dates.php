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

// only ever appended to further below, so it has to start out empty
$output_rows = '';
$user = validate_user();
validate_ecommerce_access($user);

$number_of_results = 0;

switch (isset($_GET['sort']) ? $_GET['sort'] : '') {
    case lang('Name'):
        $sort_column = 'name';
        break;

    case lang('Arrival Date'):
        $sort_column = 'arrival_date';
        break;

    case lang('Status'):
        $sort_column = 'status';
        break;

    case lang('Start Date'):
        $sort_column = 'start_date';
        break;

    case lang('End Date'):
        $sort_column = 'end_date';
        break;

    case lang('Last Modified'):
        $sort_column = 'timestamp';
        break;
    default:
        $sort_column = 'timestamp';
        $asc_desc = 'DESC';
}

if (isset($_GET['sort']) && $_GET['sort']) {
    $asc_desc = isset($_GET['order']) ? $_GET['order'] : '';
} else {
    $asc_desc = 'asc';
}

if (($sort_column == 'timestamp') && (empty($_GET['order']))) {
    $asc_desc = 'desc';
}

// Get the current date so that later we can figure out if arrival dates are active.
$current_date = date('Y-m-d');

$query = "SELECT
            arrival_dates.id as id,
            arrival_dates.name as name,
            arrival_dates.arrival_date as arrival_date,
            arrival_dates.status as status,
            arrival_dates.start_date as start_date,
            arrival_dates.end_date as end_date,
            user.user_username as user,
            arrival_dates.timestamp as timestamp
        FROM arrival_dates
        LEFT JOIN user ON arrival_dates.user = user.user_id
        ORDER BY $sort_column $asc_desc";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_array($result)) {

    $id = $row['id'];
    $name = $row['name'];
    $arrival_date = prepare_form_data_for_output($row['arrival_date'], 'date');
    $status = $row['status'];
    $start_date = prepare_form_data_for_output($row['start_date'], 'date');
    $end_date = prepare_form_data_for_output($row['end_date'], 'date');
    $username = $row['user'];
    $timestamp = $row['timestamp'];
    
    $output_link_url = 'edit_arrival_date.php?id=' . $id;

    // If this arrival date is active, then use class that shows green color.
    if (
        ($row['status'] == 'enabled')
        && ($row['start_date'] <= $current_date)
        && ($row['end_date'] >= $current_date)
    ) {
        $output_status_class = 'status_enabled';
    
    // Otherwise this arrival date is not active, so use class that shows red color.
    } else {
        $output_status_class = 'status_disabled';
    }
     
    $number_of_results++;
    $status_class = '';
    if($status == 'enabled'){
        $status_class = 'bg-success';
    } else{
        $status_class = 'bg-danger';
    }
    $output_username = '';
    if($username){
        $output_username = lang(array('string'=>'by {var:1}','vars'=>h($username) ));
    }
    
    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="chart_label ' . $output_status_class . '" nowrap>' . $name . '</td>
            <td class="align-center" nowrap>' . $arrival_date . '</td>
            <td class="align-center"><span class="badge text-light fw-light ' . $status_class . '">' . lang(ucwords($status)) . '</span></td>
            <td class="align-center" nowrap>' . $start_date . '</td>
            <td class="align-center" nowrap>' . $end_date . '</td>
            <td class="align-center">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . $output_username . '</td>
        </tr>';
}

print 
pg_page_shell([
        'title'=> lang('All Shipping Arrival Dates'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Shipping Arrival Dates')
    ]) . '
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All arrival dates for determining shipping methods for all deliveries.') . '" title="' . lang('All Shipping Arrival Dates') . '">' . lang('All Shipping Arrival Dates') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_arrival_date.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th nowrap>' .asc_or_desc(lang('Name'),'view_arrival_dates'). '</td>
                                <th nowrap>' .asc_or_desc(lang('Arrival Date'),'view_arrival_dates'). '</td>
                                <th nowrap>' .asc_or_desc(lang('Status'),'view_arrival_dates'). '</td>
                                <th nowrap>' .asc_or_desc(lang('Start Date'),'view_arrival_dates'). '</td>
                                <th nowrap>' .asc_or_desc(lang('End Date'),'view_arrival_dates'). '</td>
                                <th nowrap>' .asc_or_desc(lang('Last Modified'),'view_arrival_dates'). '</td>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();
?>