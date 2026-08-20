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



switch (isset($_GET['sort']) ? $_GET['sort'] : '') {
    case lang('Name'):
        $sort_column = 'name';
        break;

    case lang('Code'):
        $sort_column = 'code';
        break;

    case lang('Last Modified'):
        $sort_column = 'timestamp';
        break;
    default:
        $sort_column = 'timestamp';
}

if (isset($_GET['sort']) && $_GET['sort']) {
    $asc_desc = isset($_GET['order']) ? $_GET['order'] : '';
} else {
    $asc_desc = 'asc';
}

if (($sort_column == 'sort_order') && (empty($_GET['order']))) {
    $asc_desc = 'asc';
} else {
    $asc_desc = 'desc';
}

$query = "SELECT
            referral_sources.id as id,
            referral_sources.name as name,
            referral_sources.code as code,
            user.user_username as user,
            referral_sources.timestamp as timestamp
        FROM referral_sources
        LEFT JOIN user ON referral_sources.user = user.user_id
        ORDER BY $sort_column $asc_desc";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
while ($row = mysqli_fetch_array($result)) {
    $id = $row['id'];
    $name = $row['name'];
    $code = $row['code'];
    $username = $row['user'];
    $timestamp = $row['timestamp'];
    
    $output_link_url = 'edit_referral_source.php?id=' . $id;
    
    if($username != ''){
        $output_last_modifier_user = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($username) ) ) );
    }else{
        $output_last_modifier_user = '';
    }

    $output_rows .=
        '<tr>
		    <td class="align-middle text-start action-buttons">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
            </td>
            <td class="align-middle chart_label position-relative">' . h($name) . '</td>
            <td class="align-middle position-relative">' . h($code) . '</td>
            <td class="align-middle" >' . get_relative_time(array('timestamp' => $timestamp)) . $output_last_modifier_user . ' </td>
        </tr>';
}

print 
    pg_page_shell([
        'title'=> lang('All Referral Sources'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('All Referral Sources'),
        'cancel'=>array('enable'=>'true','url'=>'welcome.php')
    ])  . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . lang('All possible answers to the checkout question: "How did you hear about us?"') . '" title="' . lang('All Referral Sources') . '">' . lang('All Referral Sources') . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_referral_source.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' .asc_or_desc(lang('Name'),'view_referral_sources'). '</th>
                                    <th>' .asc_or_desc(lang('Code'),'view_referral_sources'). '</th>
                                    <th>' .asc_or_desc(lang('Last Modified'),'view_referral_sources'). '</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $output_rows . '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>' .
    output_footer();
?>