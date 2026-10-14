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



// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'code'          => lang('Code'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_referral_sources', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'name',
    'code'          => 'code',
    'last_modified' => 'timestamp',
));

$query = "SELECT
            referral_sources.id as id,
            referral_sources.name as name,
            referral_sources.code as code,
            user.user_username as user,
            referral_sources.timestamp as timestamp
        FROM referral_sources
        LEFT JOIN user ON referral_sources.user = user.user_id
        ORDER BY $order_by";

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
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
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
        'heading_description' => lang('All possible answers to the checkout question: "How did you hear about us?"'),
        'cancel'=>array('enable'=>'true','url'=>'welcome.php')
    ])  . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_referral_source.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_referral_sources.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                    <th>' . get_column_heading(lang('Code'), $sort_levels, '', '', 'code') . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
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