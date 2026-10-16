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
    'name'                  => lang('Name'),
    'base_rate'             => lang('Base Rate'),
    'primary_weight_rate'   => lang('Primary W. Rate'),
    'secondary_weight_rate' => lang('Secondary W. Rate'),
    'item_rate'             => lang('Item Rate'),
    'last_modified'         => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_zones', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'                  => 'zones.name',
    'base_rate'             => 'zones.base_rate',
    'primary_weight_rate'   => 'zones.primary_weight_rate',
    'secondary_weight_rate' => 'zones.secondary_weight_rate',
    'item_rate'             => 'zones.item_rate',
    'last_modified'         => 'zones.timestamp',
));

$query = "SELECT
            zones.id,
            zones.name,
            zones.base_rate,
            zones.primary_weight_rate,
            zones.secondary_weight_rate,
            zones.item_rate,
            user.user_username as user,
            zones.timestamp
        FROM zones
        LEFT JOIN user ON zones.user = user.user_id
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

while ($row = mysqli_fetch_array($result)) {
    $id = $row['id'];
    $name = h($row['name']);
    $base_rate = $row['base_rate'] / 100;
    $primary_weight_rate = $row['primary_weight_rate'] / 100;
    $secondary_weight_rate = $row['secondary_weight_rate'] / 100;
    $item_rate = $row['item_rate'] / 100;
    $timestamp = $row['timestamp'];

    $output_link_url ='edit_zone.php?id=' . $id;

        
    $last_modified_username = '';
        
    if ($row['user']) {
        $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($row['user']) ) ) );
    }
    $output_rows .=
        '<tr>
            <td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="align-middle chart_label">' . $name . '</td>
            <td class="align-middle text-end">' . prepare_amount($base_rate) . '</td>
            <td class="align-middle text-end">' . prepare_amount($primary_weight_rate) . '</td>
            <td class="align-middle text-end">' . prepare_amount($secondary_weight_rate) . '</td>
            <td class="align-middle text-end">' . prepare_amount($item_rate) . '</td>
            <td>' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . $last_modified_username . '</td>
        </tr>';
}

print 
pg_page_shell([
        'title'=> lang('All Shipping Zones'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Shipping Zones'),
        'heading_description' => lang('All geographic areas where your organization can ship products.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_zone.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_zones.php', $sort_columns, $sort_levels, $sort_default) . '
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
                                <th style="text-align: right">' . get_column_heading(lang('Base Rate'), $sort_levels, '', '', 'base_rate') . '</th>
                                <th style="text-align: right">' . get_column_heading(lang('Primary W. Rate'), $sort_levels, '', '', 'primary_weight_rate') . '</th>
                                <th style="text-align: right">' . get_column_heading(lang('Secondary W. Rate'), $sort_levels, '', '', 'secondary_weight_rate') . '</th>
                                <th style="text-align: right">' . get_column_heading(lang('Item Rate'), $sort_levels, '', '', 'item_rate') . '</th>
                                <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
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