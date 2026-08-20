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

    case lang('Base Rate'):
        $sort_column = 'base_rate';
        break;

    case lang('Primary W. Rate'):
        $sort_column = 'primary_weight_rate';
        break;

    case lang('Secondary W. Rate'):
        $sort_column = 'secondary_weight_rate';
        break;

    case lang('Item Rate'):
        $sort_column = 'item_rate';
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

if (($sort_column == 'timestamp') && (empty($_GET['order']))) {
    $asc_desc = 'desc';
}

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
        ORDER BY $sort_column $asc_desc";

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
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
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
        'heading'=>lang('All Shipping Zones')
    ]) . '
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All geographic areas where your organization can ship products.') . '" title="' . lang('All Shipping Zones') . '">' . lang('All Shipping Zones') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_zone.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                <th>' .asc_or_desc(lang('Name'),'view_zones'). '</td>
                                <th style="text-align: right">' .asc_or_desc(lang('Base Rate'),'view_zones'). '</td>
                                <th style="text-align: right">' .asc_or_desc(lang('Primary W. Rate'),'view_zones'). '</td>
                                <th style="text-align: right">' .asc_or_desc(lang('Secondary W. Rate'),'view_zones'). '</td>
                                <th style="text-align: right">' .asc_or_desc(lang('Item Rate'),'view_zones'). '</td>
                                <th>' .asc_or_desc(lang('Last Modified'),'view_zones'). '</td>
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