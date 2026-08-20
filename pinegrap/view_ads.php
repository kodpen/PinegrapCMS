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

// if user has a user role and if they do not have access to edit any ad regions, output error
if (($user['role'] == 3) && (count(get_items_user_can_edit('ad_regions', $user['id'])) == 0)) {
    log_activity(lang('access denied because user does not have access to ads'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('view_ads');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ads']['view_ads'][$key] = trim($value);
    }
}

// if ad region is not set yet, set default to [All]
if (isset($_SESSION['software']['ads']['view_ads']['ad_region_id']) == false) {
    $_SESSION['software']['ads']['view_ads']['ad_region_id'] = '[All]';
}

// If a screen was passed and it is a positive integer, then use it.
// These checks are necessary in order to avoid SQL errors below for a bogus screen value.
if (
    isset($_REQUEST['screen'])
    and $_REQUEST['screen']
    and is_numeric($_REQUEST['screen'])
    and $_REQUEST['screen'] > 0
    and $_REQUEST['screen'] == round($_REQUEST['screen'])
) {
    $screen = (int) $_REQUEST['screen'];

// Otherwise, use the default, which is the first screen.
} else {
    $screen = 1;
}

$sql_join = '';
$where = '';

// if user is a user role, then prepare sql join and where
if ($user['role'] == 3) {
    $sql_join = 'LEFT JOIN users_ad_regions_xref ON ad_regions.id = users_ad_regions_xref.ad_region_id';
    $where = "WHERE users_ad_regions_xref.user_id = '" . escape($user['id']) . "'";
}

// get all ad regions in order to prepare options for ad region pick list
$query = 
    "SELECT
        ad_regions.id,
        ad_regions.name
    FROM ad_regions
    $sql_join
    $where
    ORDER BY name ASC";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$ad_regions = array();

// loop through all ad regions in order to add ad regions to array
while ($row = mysqli_fetch_assoc($result)){
    $ad_regions[] = $row;
}

$output_ad_region_options = '';

// loop through all ad regions in order to prepare options for ad region pick list
foreach ($ad_regions as $ad_region) {
    // if this ad region is equal to the selected ad region
    if ($ad_region['id'] == ($_SESSION['software']['ads']['view_ads']['ad_region_id'] ?? '')) {
        $selected = ' selected="selected"';
    } else {
        $selected = '';
    }
    
    // get the number of ads that are assigned to this ad region
    $query = "SELECT COUNT(*) FROM ads WHERE ad_region_id = '" . $ad_region['id'] . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $number_of_ads = $row[0];
    
    $output_ad_region_options .= '<option value="' . $ad_region['id'] . '"' . $selected . '>' . h($ad_region['name']) . ' (' . number_format($number_of_ads) . ')</option>';
}

// if the user clicked on the clear button, then clear the search
if (isset($_GET['clear']) == true) {
    $_SESSION['software']['ads']['view_ads']['query'] = '';
}


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ads']['view_ads']['sort']) == false) {
    $_SESSION['software']['ads']['view_ads']['sort'] = '';
}

switch (($_SESSION['software']['ads']['view_ads']['sort'] ?? '')) {
    case 'Name':
        $sort_column = 'ads.name';
        break;
        
    case 'Ad Region':
        $sort_column = 'ad_regions.name';
        break;
        
    case 'Display Type':
        $sort_column = 'ad_regions.display_type';
        break;

    case 'Created':
        $sort_column = 'ads.created_timestamp';
        break;

    case 'Last Modified':
        $sort_column = 'ads.last_modified_timestamp';
        break;

    default:
        $sort_column = 'ads.last_modified_timestamp';
        $_SESSION['software']['ads']['view_ads']['sort'] = 'Last Modified';
        $_SESSION['software']['ads']['view_ads']['order'] = 'desc';
        break;
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ads']['view_ads']['order']) == false) {
    $_SESSION['software']['ads']['view_ads']['order'] = 'asc';
}

$all_ads = 0;

$sql_join = '';
$where = '';

// if user is a user role, then prepare sql join and where
if ($user['role'] == 3) {
    $sql_join = 
        'LEFT JOIN ad_regions ON ads.ad_region_id = ad_regions.id
        LEFT JOIN users_ad_regions_xref ON ad_regions.id = users_ad_regions_xref.ad_region_id';
    
    $where = "WHERE users_ad_regions_xref.user_id = '" . escape($user['id']) . "'";
}

// get the total number of ads
$query = 
    "SELECT
        COUNT(*)
    FROM ads
    $sql_join
    $where";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_ads = $row[0];

$sql_join = '';
$where = "";

// if user is a user role, then prepare sql join and where
if ($user['role'] == 3) {
    $sql_join = 'LEFT JOIN users_ad_regions_xref ON ad_regions.id = users_ad_regions_xref.ad_region_id';
    
    // if this is the first where clause, then add where first
    if ($where == '') {
        $where .= "WHERE ";
        
    // else this is not the first where clause, so add and
    } else {
        $where .= "AND ";
    }
    
    $where .= "users_ad_regions_xref.user_id = '" . escape($user['id']) . "'";
}

// if user has not choosen [All] filter for ad region pick list, then prepare where clause
if (($_SESSION['software']['ads']['view_ads']['ad_region_id'] ?? '') != '[All]') {
    // if this is the first where clause, then add where first
    if ($where == '') {
        $where .= "WHERE ";
        
    // else this is not the first where clause, so add and
    } else {
        $where .= "AND ";
    }
    
    $where .= "(ads.ad_region_id = '" . escape(($_SESSION['software']['ads']['view_ads']['ad_region_id'] ?? '')) . "') ";
}

// get all ads
$query =
    "SELECT
        ads.id,
        ads.name as ad_name,
        ad_regions.name as ad_region_name,
        ad_regions.display_type as ad_region_display_type,
        ads.label as label,
        ads.sort_order as sort_order,
        created_user.user_username as created_username,
        ads.created_timestamp,
        last_modified_user.user_username as last_modified_username,
        ads.last_modified_timestamp
    FROM ads
    LEFT JOIN user AS created_user ON ads.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON ads.last_modified_user_id = last_modified_user.user_id
    LEFT JOIN ad_regions ON ads.ad_region_id = ad_regions.id
    $sql_join
    $where
    ORDER BY $sort_column " . escape(($_SESSION['software']['ads']['view_ads']['order'] ?? ''));

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$ads = array();

while ($row = mysqli_fetch_assoc($result)) {
    $ads[] = $row;
}

$output_rows = '';

// if there is at least one result to display
if ($ads) {
   
    foreach ($ads as $ad) {




        $created_username = '';
        
        if ($ad['created_username'] != '') {
            $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($ad['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($product_attribute['last_modified_username'] != '') {
            $last_modified_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($ad['last_modified_username']) ) ) );
        }

        // if the ad region display type is static then prepare output value
        if ($ad['ad_region_display_type'] == 'static') {
            $output_ad_region_display_type = lang('Static');
            
        // else the ad region display type is dynamic, so prepare output value
        } else {
            $output_ad_region_display_type = lang('Dynamic');
        }
        
        $output_label = '';
        $output_sort_order = '';
        
        // if the ad region display type is dynamic, then prepare to output label and sort order
        if ($ad['ad_region_display_type'] == 'dynamic') {
            $output_label = h($ad['label']);
            
            // if the sort order is not equal to 0, then set value
            if ($ad['sort_order'] != 0) {
                $output_sort_order = number_format($ad['sort_order']);
            }
        }
        
        $output_link_url = 'edit_ad.php?id=' . $ad['id'];

        $output_rows .=
            '<tr>
                <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                </td>
                <td class="chart_label">' . h($ad['ad_name']) . '</td>
                <td class="chart_label">' . h($ad['ad_region_name']) . '</td>
                <td class="align-middle text-center">' . $output_ad_region_display_type . '</td>
                <td >' . $output_label . '</td>
                <td class="align-middle text-center">' . $output_sort_order . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $ad['created_timestamp'])) . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $ad['last_modified_timestamp'])) . $last_modified_username . '</td>
            </tr>';
    }
}

print
    pg_page_shell([
        'title'=> lang('All My Ads'),
        'extra classes'=>'ads',
        'icon'=>'ads',
        'heading'=>lang('All My Ads')
    ]) . '
    <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All shared content that can be rotated on one or more pages that I can edit.') . '" title="' . lang('All My Ads') . '">' . lang('All My Ads') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_ad.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        </nav>
                    </div>
                    <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="search_form" action="view_ads.php" method="get" class="search_form col-auto">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="ad_region_id" name="ad_region_id" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')"><option value="[All]">[' . lang('All') . '] (' . number_format($all_ads) . ')</option>' . $output_ad_region_options . '</select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table" style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                    <th>' . get_column_heading(lang('Name') , ($_SESSION['software']['ads']['view_ads']['sort'] ?? ''), ($_SESSION['software']['ads']['view_ads']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Ad Region'),  ($_SESSION['software']['ads']['view_ads']['sort'] ?? ''), ($_SESSION['software']['ads']['view_ads']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Display Type') , ($_SESSION['software']['ads']['view_ads']['sort'] ?? ''), ($_SESSION['software']['ads']['view_ads']['order'] ?? '')) . '</th>
                                    <th class="text-center">' . lang('Label') . '</th>
                                    <th class="text-center">' . lang('Sort Order') . '</th>
                                    <th>' . get_column_heading(lang('Created') , ($_SESSION['software']['ads']['view_ads']['sort'] ?? ''), ($_SESSION['software']['ads']['view_ads']['order'] ?? '')) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified') , ($_SESSION['software']['ads']['view_ads']['sort'] ?? ''), ($_SESSION['software']['ads']['view_ads']['order'] ?? '')) . '</th>
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

$liveform->remove_form();
?>