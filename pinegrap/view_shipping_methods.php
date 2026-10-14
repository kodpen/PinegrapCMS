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
    'message'               => lang('Message'),
    'code'                  => lang('Code'),
    'service'               => lang('Service'),
    'realtime_rate'         => lang('Real-Time Rate'),
    'base_rate'             => lang('Base Rate'),
    'primary_weight_rate'   => lang('Primary W. Rate'),
    'secondary_weight_rate' => lang('Secondary W. Rate'),
    'item_rate'             => lang('Item Rate'),
    'handling'              => lang('Handling'),
    'transit'               => lang('Transit'),
    'street'                => lang('Street'),
    'po_box'                => lang('PO Box'),
    'protected'             => lang('Protected'),
    'last_modified'         => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_shipping_methods', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'                  => 'shipping_methods.name',
    'message'               => 'shipping_methods.description',
    'code'                  => 'shipping_methods.code',
    'service'               => 'shipping_methods.service',
    'realtime_rate'         => 'shipping_methods.realtime_rate',
    'base_rate'             => 'shipping_methods.base_rate',
    'primary_weight_rate'   => 'shipping_methods.primary_weight_rate',
    'secondary_weight_rate' => 'shipping_methods.secondary_weight_rate',
    'item_rate'             => 'shipping_methods.item_rate',
    'handling'              => 'shipping_methods.handle_days',
    'transit'               => 'shipping_methods.base_transit_days',
    'street'                => 'shipping_methods.street_address',
    'po_box'                => 'shipping_methods.po_box',
    'protected'             => 'shipping_methods.protected',
    'last_modified'         => 'shipping_methods.timestamp',
));

$query = "SELECT
            shipping_methods.id,
            shipping_methods.name,
            shipping_methods.description,
            shipping_methods.code,
            shipping_methods.status,
            shipping_methods.start_time,
            shipping_methods.end_time,
            shipping_methods.service,
            shipping_methods.realtime_rate,
            shipping_methods.base_rate,
            shipping_methods.variable_base_rate,
            shipping_methods.base_rate_2,
            shipping_methods.base_rate_2_subtotal,
            shipping_methods.base_rate_3,
            shipping_methods.base_rate_3_subtotal,
            shipping_methods.base_rate_4,
            shipping_methods.base_rate_4_subtotal,
            shipping_methods.primary_weight_rate,
            shipping_methods.secondary_weight_rate,
            shipping_methods.item_rate,
            shipping_methods.handle_days,
            shipping_methods.base_transit_days,
            shipping_methods.street_address,
            shipping_methods.po_box,
            shipping_methods.protected,
            user.user_username as user,
            shipping_methods.timestamp
        FROM shipping_methods
        LEFT JOIN user ON shipping_methods.user = user.user_id
        ORDER BY $order_by";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$number_of_results = 0;

while ($row = mysqli_fetch_array($result)) {
    $id = $row['id'];
    $name = $row['name'];
    $description = $row['description'];
    $code = $row['code'];
    $status = $row['status'];
    $start_time = $row['start_time'];
    $end_time = $row['end_time'];
    $service = $row['service'];
    $base_rate = $row['base_rate'];
    $variable_base_rate = $row['variable_base_rate'];
    $base_rate_2 = $row['base_rate_2'];
    $base_rate_2_subtotal = $row['base_rate_2_subtotal'];
    $base_rate_3 = $row['base_rate_3'];
    $base_rate_3_subtotal = $row['base_rate_3_subtotal'];
    $base_rate_4 = $row['base_rate_4'];
    $base_rate_4_subtotal = $row['base_rate_4_subtotal'];
    $primary_weight_rate = sprintf("%01.2lf", $row['primary_weight_rate'] / 100);
    $secondary_weight_rate = sprintf("%01.2lf", $row['secondary_weight_rate'] / 100);
    $item_rate = sprintf("%01.2lf", $row['item_rate'] / 100);
    $handle_days = $row['handle_days'];
    $base_transit_days = $row['base_transit_days'];
    $username = $row['user'];
    $timestamp = $row['timestamp'];
    
    $current_time = date('Y-m-d H:i:s');
    
    // if the shipping method is active, use green status color
    if (($status == 'enabled') && ($start_time <= $current_time) && ($end_time >= $current_time)) {
        $status_color_class = ' text-success';
    
    // else shipping method is inactive, so use red status color
    } else {
        $status_color_class = ' text-danger';
    }

    $realtime_rate = '';

    if ($row['realtime_rate']) {
        $realtime_rate = '<span class="material-icons">task_alt</span>';
    }

    $output_base_rate = '';

    // If variable base rate is enabled, then show all base rates.
    if ($variable_base_rate) {
        $output_base_rate .= pg_format_money($base_rate / 100, BASE_CURRENCY_SYMBOL) . ' (@' . pg_format_money(0, BASE_CURRENCY_SYMBOL) . ')';

        if ($base_rate_2_subtotal) {
            $output_base_rate .= '<br>' . pg_format_money($base_rate_2 / 100, BASE_CURRENCY_SYMBOL) . ' (@' . pg_format_money($base_rate_2_subtotal / 100, BASE_CURRENCY_SYMBOL) . ')';
        }

        if ($base_rate_3_subtotal) {
            $output_base_rate .= '<br>' . pg_format_money($base_rate_3 / 100, BASE_CURRENCY_SYMBOL) . ' (@' . pg_format_money($base_rate_3_subtotal / 100, BASE_CURRENCY_SYMBOL) . ')';
        }

        if ($base_rate_4_subtotal) {
            $output_base_rate .= '<br>' . pg_format_money($base_rate_4 / 100, BASE_CURRENCY_SYMBOL) . ' (@' . pg_format_money($base_rate_4_subtotal / 100, BASE_CURRENCY_SYMBOL) . ')';
        }

    // Otherwise variable base rate is disabled, so just show the base rate.
    } else {
        $output_base_rate = pg_format_money($base_rate / 100, BASE_CURRENCY_SYMBOL);
    }

    // For handle days, show blank instead of zero.
    if (!$handle_days) {
        $handle_days = '';
    }

    if ($row['street_address']) {
        $street_address = '<span class="material-icons">task_alt</span>';
    } else {
        $street_address = '';
    }

    if ($row['po_box']) {
        $po_box = '<span class="material-icons">task_alt</span>';
    } else {
        $po_box = '';
    }

    $zones = db_values(
        "SELECT zones.name
        FROM shipping_methods_zones_xref
        LEFT JOIN zones ON shipping_methods_zones_xref.zone_id = zones.id
        WHERE shipping_methods_zones_xref.shipping_method_id = '" . e($id) . "'
        ORDER BY zones.name");

    $output_zones = '';

    foreach ($zones as $zone) {

        // If this is not the first zone then output a comma and a break tag.
        if ($output_zones) {
            $output_zones .= ',<br>';
        }
        
        $output_zones .= h($zone);
    }

    if ($row['protected']) {
        $protected = '<span class="material-icons">task_alt</span>';
    } else {
        $protected = '';
    }

    $output_link_url ='edit_shipping_method.php?id=' . $id;
    
    $number_of_results++;
    
            
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
            <td style="max-width:100px" class="text-nowrap text-truncate align-middle chart_label ' . $status_color_class . '">' . h($name) . '</td>
            <td style="max-width:200px" class="text-nowrap text-truncate align-middle">' . h($description) . '</td>
            <td class="align-middle"><span class="badge text-dark fw-light">' . h($code) . '</span></td>
            <td class="align-middle">' . h(get_shipping_service_name($service)) . '</td>
            <td class="align-middle text-center">' . $realtime_rate . '</td>
            <td class="align-middle text-end">' . $output_base_rate . '</td>
            <td class="align-middle text-end">' . pg_format_money($primary_weight_rate, BASE_CURRENCY_SYMBOL) . '</td>
            <td class="align-middle text-end">' . pg_format_money($secondary_weight_rate, BASE_CURRENCY_SYMBOL) . '</td>
            <td class="align-middle text-end">' . pg_format_money($item_rate, BASE_CURRENCY_SYMBOL) . '</td>
            <td class="align-middle text-end">' . h($handle_days) . '</td>
            <td class="align-middle text-center">' . $base_transit_days . '</td>
            <td class="align-middle text-center">' . $street_address . '</td>
            <td class="align-middle text-center">' . $po_box . '</td>
            <td class="align-middle">' . $output_zones . '</td>
            <td class="align-middle text-center">' . $protected . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . $last_modified_username . '</td>
        </tr>';
}

echo
pg_page_shell([
        'title'=> lang('All Shipping Methods'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Shipping Methods'),
        'heading_description' => lang('All shipping options and fees, based on the carrier, product, destination, and arrival date.'),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_shipping_method.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                        <div class="pg-toolbar-grow"></div>
                        ' . pg_list_sort_menu('view_shipping_methods.php', $sort_columns, $sort_levels, $sort_default) . '
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                <th>' . get_column_heading(lang('Message'), $sort_levels, '', '', 'message') . '</th>
                                <th>' . get_column_heading(lang('Code'), $sort_levels, '', '', 'code') . '</th>
                                <th>' . get_column_heading(lang('Service'), $sort_levels, '', '', 'service') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Real-Time Rate'), $sort_levels, '', '', 'realtime_rate') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Base Rate'), $sort_levels, '', '', 'base_rate') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Primary W. Rate'), $sort_levels, '', '', 'primary_weight_rate') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Secondary W. Rate'), $sort_levels, '', '', 'secondary_weight_rate') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Item Rate'), $sort_levels, '', '', 'item_rate') . '</th>
                                <th class="text-end">' . get_column_heading(lang('Handling'), $sort_levels, '', '', 'handling') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Transit'), $sort_levels, '', '', 'transit') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Street'), $sort_levels, '', '', 'street') . '</th>
                                <th class="text-center">' . get_column_heading(lang('PO Box'), $sort_levels, '', '', 'po_box') . '</th>
                                <th>' . lang('Shipping Zone') . '</th>
                                <th class="text-center">' . get_column_heading(lang('Protected'), $sort_levels, '', '', 'protected') . '</th>
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