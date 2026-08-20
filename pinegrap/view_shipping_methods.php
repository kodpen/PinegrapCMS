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

    case lang('Message'):
        $sort_column = 'description';
        break;

    case ('Code'):
        $sort_column = 'code';
        break;

    case lang('Service'):
        $sort_column = 'service';
        break;

    case lang('Real-Time Rate'):
        $sort_column = 'realtime_rate';
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

    case lang('Handling'):
        $sort_column = 'handle_days';
        break;

    case lang('Transit'):
        $sort_column = 'base_transit_days';
        break;

    case ('Street'):
        $sort_column = 'street_address';
        break;

    case lang('PO Box'):
        $sort_column = 'po_box';
        break;

    case lang('Protected'):
        $sort_column = 'protected';
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
        ORDER BY $sort_column $asc_desc";

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
        $output_base_rate .= BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate / 100) . ' (@' . BASE_CURRENCY_SYMBOL . '0.00)';

        if ($base_rate_2_subtotal) {
            $output_base_rate .= '<br>' . BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate_2 / 100) . ' (@' . BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate_2_subtotal / 100) . ')';
        }

        if ($base_rate_3_subtotal) {
            $output_base_rate .= '<br>' . BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate_3 / 100) . ' (@' . BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate_3_subtotal / 100) . ')';
        }

        if ($base_rate_4_subtotal) {
            $output_base_rate .= '<br>' . BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate_4 / 100) . ' (@' . BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate_4_subtotal / 100) . ')';
        }

    // Otherwise variable base rate is disabled, so just show the base rate.
    } else {
        $output_base_rate = BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $base_rate / 100);
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
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td style="max-width:100px" class="text-nowrap text-truncate align-middle chart_label ' . $status_color_class . '">' . h($name) . '</td>
            <td style="max-width:200px" class="text-nowrap text-truncate align-middle">' . h($description) . '</td>
            <td class="align-middle"><span class="badge text-dark fw-light">' . h($code) . '</span></td>
            <td class="align-middle">' . h(get_shipping_service_name($service)) . '</td>
            <td class="align-middle text-center">' . $realtime_rate . '</td>
            <td class="align-middle text-end">' . $output_base_rate . '</td>
            <td class="align-middle text-end">' . BASE_CURRENCY_SYMBOL . $primary_weight_rate . '</td>
            <td class="align-middle text-end">' . BASE_CURRENCY_SYMBOL . $secondary_weight_rate . '</td>
            <td class="align-middle text-end">' . BASE_CURRENCY_SYMBOL . $item_rate . '</td>
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
        'heading'=>lang('All Shipping Methods')
    ]) . '
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All shipping options and fees, based on the carrier, product, destination, and arrival date.') . '" title="' . lang('All Shipping Methods') . '">' . lang('All Shipping Methods') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_shipping_method.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . asc_or_desc(lang('Name'),'view_shipping_methods') . '</th>
                                <th>' . asc_or_desc(lang('Message'),'view_shipping_methods') . '</th>
                                <th>' . asc_or_desc(lang('Code'),'view_shipping_methods') . '</th>
                                <th>' . asc_or_desc(lang('Service'),'view_shipping_methods') . '</th>
                                <th class="text-center">' . asc_or_desc(lang('Real-Time Rate'),'view_shipping_methods') . '</th>
                                <th class="text-end">' . asc_or_desc(lang('Base Rate'),'view_shipping_methods') . '</th>
                                <th class="text-end">' . asc_or_desc(lang('Primary W. Rate'),'view_shipping_methods') . '</th>
                                <th class="text-end">' . asc_or_desc(lang('Secondary W. Rate'),'view_shipping_methods') . '</th>
                                <th class="text-end">' . asc_or_desc(lang('Item Rate'),'view_shipping_methods') . '</th>
                                <th class="text-end">' . asc_or_desc(lang('Handling'),'view_shipping_methods') . '</th>
                                <th class="text-center">' . asc_or_desc(lang('Transit'),'view_shipping_methods') . '</th>
                                <th class="text-center">' . asc_or_desc(lang('Street'),'view_shipping_methods') . '</th>
                                <th class="text-center">' . asc_or_desc(lang('PO Box'),'view_shipping_methods') . '</th>
                                <th>' . lang('Shipping Zone') . '</th>
                                <th class="text-center">' . asc_or_desc(lang('Protected'),'view_shipping_methods') . '</th>
                                <th>' . asc_or_desc(lang('Last Modified'),'view_shipping_methods') . '</th>
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