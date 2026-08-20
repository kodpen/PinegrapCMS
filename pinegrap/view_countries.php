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

    case lang('Code'):
        $sort_column = 'code';
        break;

    case lang('Zip Code Required'):
        $sort_column = 'zip_code_required';
        break;

    case lang('Transit Adjustment'):
        $sort_column = 'transit_adjustment_days';
        break;

    case lang('Default'):
        $sort_column = 'default_selected';
        break;

    case lang('Last Modified'):
        $sort_column = 'timestamp';
        break;
    default:
        $sort_column = 'name';
}

if (isset($_GET['sort']) && $_GET['sort']) {
    $asc_desc = isset($_GET['order']) ? $_GET['order'] : '';
} else {
    $asc_desc = 'asc';
}

if (($sort_column == 'name') && (empty($_GET['order']))) {
    $asc_desc = 'asc';
}

if (($sort_column == 'timestamp') && (empty($_GET['order']))) {
    $asc_desc = 'desc';
}

$query = "SELECT
            countries.id,
            countries.name,
            countries.code,
            countries.zip_code_required,
            countries.transit_adjustment_days,
            countries.default_selected,
            user.user_username as user,
            countries.timestamp
        FROM countries
        LEFT JOIN user ON countries.user = user.user_id
        ORDER BY $sort_column $asc_desc";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_array($result)) {
    $id = $row['id'];
    $name = h($row['name']);
    $code = h($row['code']);
    $zip_code_required = $row['zip_code_required'];
    $transit_adjustment_days = $row['transit_adjustment_days'];
    $default_selected = $row['default_selected'];
    $username = $row['user'];
    $timestamp = $row['timestamp'];

    $zip_code_required_check_mark = '';

    if ($zip_code_required) {
        $zip_code_required_check_mark = '<span class="material-icons">task_alt</span>';
    }

    if ($row['default_selected']) {
        $default_selected = '<span class="material-icons">task_alt</span>';
    } else {
        $default_selected = '';
    }
    $output_link_url = 'edit_country.php?id=' . $id;
    
    $number_of_results++;
    
    $output_rows .= '
        <tr>
            <td class="align-middle text-start action-buttons">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="align-middle chart_label">' . $name . '</td>
            <td class="align-middle">' . $code . '</td>
            <td style="text-align: center">' . $zip_code_required_check_mark . '</td>
            <td style="text-align: right;">' . $transit_adjustment_days . '</td>
            <td style="text-align: center">' . $default_selected . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($username) ) ) ) . '</td>
        </tr>';
}

echo
    pg_page_shell([
        'title'=> lang('All Countries'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Countries')
    ]) . '
    <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All countries that are valid for billing address and shipping address selection.') . '" title="' . lang('All Countries') . '">' . lang('All Countries') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_country.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' .asc_or_desc(lang('Name'),'view_countries'). '</th>
                                    <th>' .asc_or_desc(lang('Code'),'view_countries'). '</th>
                                    <th style="text-align: center">' . asc_or_desc(lang('Zip Code Required'),'view_countries') . '</th>
                                    <th style="text-align: right;">' .asc_or_desc(lang('Transit Adjustment'),'view_countries'). '</th>
                                    <th style="text-align: center">' .asc_or_desc(lang('Default'),'view_countries'). '</th>
                                    <th>' .asc_or_desc(lang('Last Modified'),'view_countries'). '</th>
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