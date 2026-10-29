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

// only ever appended to further below, so it has to start out empty
$output_rows = '';
$user = validate_user();
validate_ecommerce_access($user);

$number_of_results = 0;

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'arrival_date'  => lang('Arrival Date'),
    'status'        => lang('Status'),
    'start_date'    => lang('Start Date'),
    'end_date'      => lang('End Date'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_arrival_dates', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'arrival_dates.name',
    'arrival_date'  => 'arrival_dates.arrival_date',
    'status'        => 'arrival_dates.status',
    'start_date'    => 'arrival_dates.start_date',
    'end_date'      => 'arrival_dates.end_date',
    'last_modified' => 'arrival_dates.timestamp',
));

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
        ORDER BY $order_by";

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
    
    $output_link_url = 'edit_arrival_date.php?id=' . (int) $id;

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
                <button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline theme-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="chart_label ' . $output_status_class . '" nowrap>' . h($name) . '</td>
            <td class="align-center" nowrap>' . $arrival_date . '</td>
            <td class="align-center"><span class="badge fg-white fw-light ' . $status_class . '">' . lang(ucwords($status)) . '</span></td>
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
        'heading'=>lang('All Shipping Arrival Dates'),
        'heading_description' => lang('All arrival dates for determining shipping methods for all deliveries.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="row mb-3  flex-wrap">
                <div class="col-12 text-center md:text-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                        <a class="btn btn-sm btn-solid theme-primary m-1 " href="add_arrival_date.php"><span class="bi bi-plus-circle me-3"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_arrival_dates.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table" style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th nowrap>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                <th nowrap>' . get_column_heading(lang('Arrival Date'), $sort_levels, '', '', 'arrival_date') . '</th>
                                <th nowrap>' . get_column_heading(lang('Status'), $sort_levels, '', '', 'status') . '</th>
                                <th nowrap>' . get_column_heading(lang('Start Date'), $sort_levels, '', '', 'start_date') . '</th>
                                <th nowrap>' . get_column_heading(lang('End Date'), $sort_levels, '', '', 'end_date') . '</th>
                                <th nowrap>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
' .
output_footer();
?>