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

switch (isset($_GET['sort']) ? $_GET['sort'] : '') {
    case lang('Name'):
        $sort_column = 'name';
        break;

    case  lang('Code'):
        $sort_column = 'code';
        break;

    case  lang('Country'):
        $sort_column = 'country_name';
        break;

    case  lang('Last Modified'):
        $sort_column = 'timestamp';
        break;
    default:
        $sort_column = 'name';
}

if (isset($_GET['sort']) && $_GET['sort']) {
    $asc_desc = sql_order_direction(isset($_GET['order']) ? $_GET['order'] : '');
} else {
    $asc_desc = 'asc';
}

if (($sort_column == 'name') && (empty($_GET['order']))) {
    $asc_desc = 'asc';
}

$query = "SELECT
            states.id,
            states.name,
            states.code,
            countries.name as country_name,
            user.user_username as user,
            states.timestamp
        FROM states
        LEFT JOIN countries ON states.country_id = countries.id
        LEFT JOIN user ON states.user = user.user_id
        ORDER BY $sort_column $asc_desc";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_array($result)) {

    // reset for every row, otherwise a row without a user repeats the previous row's user
    $last_modified_username = '';

    $id = $row['id'];
    $name = h($row['name']);
    $code = h($row['code']);
    $country_name = $row['country_name'];
    $username = $row['user'];
    $timestamp = $row['timestamp'];

    $output_link_url = 'edit_state.php?id=' . $id;
    
    $number_of_results++;

     // if the last modified username was found, then prepare to output it
     if ($username) {
        $last_modified_username = $username;
    } 

    $output_rows .= '
        <tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle chart_label">' . $name . '</td>
            <td class="align-middle">' . $code . '</td>
            <td class="align-middle">' . $country_name . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . h($last_modified_username) . '</td>
        </tr>';
}

print
pg_page_shell([
        'title'=> lang('All States'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All States'),
        'heading_description' => lang('All states/provinces that are valid for billing address and shipping address selection.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_state.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' .asc_or_desc(lang('Name'),'view_states'). '</th>
                                    <th>' .asc_or_desc(lang('Code'),'view_states'). '</th>
                                    <th>' .asc_or_desc(lang('Country'),'view_states'). '</th>
                                    <th>' .asc_or_desc(lang('Last Modified'),'view_states'). '</th>
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