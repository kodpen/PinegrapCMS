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
$user = validate_user();

validate_ecommerce_report_access();

include_once('liveform.class.php');
$liveform = new liveform('view_shipping_report');

// Store all values collected in request to session.
foreach ($_REQUEST as $key => $value) {
    // If the value is a string then add it to the session.  We have to do this
    // check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings.
    if (is_string($value) == TRUE) {
        $value = trim($value);

        // The period is read as a pair below, never one date at a time.
        if (($key == 'start_date') || ($key == 'end_date')) {
            continue;
        }

        // A status the screen cannot use (hand-typed ?status=all) counts as
        // not given: stored, it left the quantity unset on every later visit.
        if (($key == 'status') && (in_array($value, array('any', 'unshipped', 'shipped'), true) == false)) {
            continue;
        }

        $_SESSION['software']['ecommerce']['view_shipping_report'][$key] = $value;
    }
}

if (isset($_SESSION['software']['ecommerce']['view_shipping_report']['status']) == false) {
    $_SESSION['software']['ecommerce']['view_shipping_report']['status'] = 'unshipped';
}

// An end before the start or an impossible date (hand-typed or shared
// address) is not taken, so no inverted period is drawn or kept.
list($start_date, $end_date) = pg_shipping_report_period(
    (isset($_REQUEST['start_date']) && is_string($_REQUEST['start_date'])) ? trim($_REQUEST['start_date']) : null,
    (isset($_REQUEST['end_date']) && is_string($_REQUEST['end_date'])) ? trim($_REQUEST['end_date']) : null,
    $_SESSION['software']['ecommerce']['view_shipping_report']['start_date'] ?? null,
    $_SESSION['software']['ecommerce']['view_shipping_report']['end_date'] ?? null,
    date('Y-m-d')
);

$_SESSION['software']['ecommerce']['view_shipping_report']['start_date'] = $start_date;
$_SESSION['software']['ecommerce']['view_shipping_report']['end_date'] = $end_date;

// The period in the panel language and the site's date order ('31 Ekim
// 2026 – 13 Kasım 2026'), like the list screens' headings.
$output_date_range = h(pg_list_period_heading(pg_list_period_range_from_dates($start_date, $end_date)));

$number_of_days_in_date_range = round((strtotime($end_date) - strtotime($start_date)) / 86400);

$output_previous_start_date = date('Y-m-d', strtotime('-' . ($number_of_days_in_date_range + 1) . ' day', strtotime($start_date)));
$output_previous_end_date = date('Y-m-d', strtotime('-' . ($number_of_days_in_date_range + 1) . ' day', strtotime($end_date)));

$output_next_start_date = date('Y-m-d', strtotime('+' . ($number_of_days_in_date_range + 1) . ' day', strtotime($start_date)));
$output_next_end_date = date('Y-m-d', strtotime('+' . ($number_of_days_in_date_range + 1) . ' day', strtotime($end_date)));

// Create array to store all dates in the date range.
$dates = array();

// The first date is the start date.
$date = $start_date;

while ($date <= $end_date) {
    $dates[] = $date;

    // Prepare next date.
    $date = date('Y-m-d', strtotime('+1 day', strtotime($date)));
}

// The status choices (links: the screen keeps the status in the session)
// and the SQL filter, so that we only get order items for that status.
$output_status_items = '';

foreach (array('any' => lang('Any'), 'unshipped' => lang('Unshipped'), 'shipped' => lang('Shipped')) as $status_value => $status_label) {
    $status_current = (($_SESSION['software']['ecommerce']['view_shipping_report']['status'] ?? '') === $status_value);
    $output_status_items .= '<a class="pg-seg-item' . ($status_current ? ' is-active" aria-current="true"' : '"') . ' href="view_shipping_report.php?status=' . $status_value . '">' . h($status_label) . '</a>';
}

$sql_status_filter = "";

switch (($_SESSION['software']['ecommerce']['view_shipping_report']['status'] ?? '')) {
    case 'unshipped':
        $sql_status_filter = "AND (order_items.quantity > order_items.shipped_quantity)";
        break;
    
    case 'shipped':
        $sql_status_filter = "AND (order_items.shipped_quantity > 0)";
        break;
}

$order_items = db_items(
    "SELECT
        order_items.quantity,
        order_items.shipped_quantity,
        products.id AS product_id,
        products.name,
        products.short_description,
        ship_tos.ship_date
    FROM order_items
    LEFT JOIN orders ON order_items.order_id = orders.id
    LEFT JOIN products ON order_items.product_id = products.id
    LEFT JOIN ship_tos ON order_items.ship_to_id = ship_tos.id
    WHERE
        (order_items.ship_to_id != '0')
        $sql_status_filter
        AND (orders.status IN ('complete', 'exported'))
        AND (products.id IS NOT NULL)
        AND (ship_tos.ship_date != '0000-00-00')
    ORDER BY products.name ASC");

// Create array for product data in report.
$products = array();

// If there is at least one order item, then continue to prepare data for report.
if (count($order_items) > 0) {
    // Create an array that will be used to store total data for last row in table.
    $totals = array();
    $totals['before'] = 0;

    // Loop through the dates in the date range, in order to initialize values for each date.
    foreach ($dates as $date) {
        $totals[$date] = 0;
    }

    $totals['after'] = 0;
    $totals['total'] = 0;

    // Loop through the order items in order to prepare data for report.
    foreach ($order_items as $order_item) {
        // If this is the first order item for this product, then initialize array for product.
        if (isset($products[$order_item['product_id']]) == false) {
            $products[$order_item['product_id']] = array();
            $products[$order_item['product_id']]['total'] = 0;
            $products[$order_item['product_id']]['name'] = $order_item['name'];
            $products[$order_item['product_id']]['id'] = $order_item['product_id'];
            $products[$order_item['product_id']]['short_description'] = $order_item['short_description'];
            $products[$order_item['product_id']]['before'] = 0;

            // Loop through the dates in the date range, in order to initialize values for each date.
            foreach ($dates as $date) {
                $products[$order_item['product_id']][$date] = 0;
            }

            $products[$order_item['product_id']]['after'] = 0;
        }

        // Get the quantity differently based on the status.
        switch (($_SESSION['software']['ecommerce']['view_shipping_report']['status'] ?? '')) {
            case 'any':
                // If the ordered quantity is greater than the shipped quantity,
                // then use ordered quantity for the quantity.
                if ($order_item['quantity'] > $order_item['shipped_quantity']) {
                    $quantity = $order_item['quantity'];

                // Otherwise use the shipped quantity.
                } else {
                    $quantity = $order_item['shipped_quantity'];
                }

                break;

            case 'unshipped':
                $quantity = $order_item['quantity'] - $order_item['shipped_quantity'];
                break;
            
            case 'shipped':
                $quantity = $order_item['shipped_quantity'];
                break;
        }

        // If the ship date is in the before category, then add quantity to that category.
        if ($order_item['ship_date'] < $start_date) {
            $products[$order_item['product_id']]['before'] += $quantity;
            $totals['before'] += $quantity;

        // Otherwise if the ship date is in the date range, then increment value for the ship date.
        } else if (($order_item['ship_date'] >= $start_date) && ($order_item['ship_date'] <= $end_date)) {
            $products[$order_item['product_id']][$order_item['ship_date']] += $quantity;
            $totals[$order_item['ship_date']] += $quantity;

        // Otherwise if the ship date is in the after category, then add quantity to that category.
        } else if ($order_item['ship_date'] > $end_date) {
            $products[$order_item['product_id']]['after'] += $quantity;
            $totals['after'] += $quantity;
        }

        // Add quantity to total for this product.
        $products[$order_item['product_id']]['total'] += $quantity;

        $totals['total'] += $quantity;
    }
}

// If there is at least data for one product, then output report.
if (count($products) > 0) {
    $output_view_orders_shipping_status = '';

    // If the status is unshipped, then update shipping status on view orders screen,
    // for all links to view orders screen.
    if (($_SESSION['software']['ecommerce']['view_shipping_report']['status'] ?? '') == 'unshipped') {
        $output_view_orders_shipping_status = '&amp;shipping_status=unshipped';
    }

    // If the date format is month and then day, then use that format.
    if (DATE_FORMAT == 'month_day') {
        $month_and_day_format = 'M j';

    // Otherwise the date format is day and then month, so use that format.
    } else {
        $month_and_day_format = 'j M';
    }

    // view_orders.php and edit_product.php ask for commerce, not for commerce
    // reports: a user who may only read the report gets the headings and
    // product names without links rather than links to "Access denied".
    $can_open_commerce = (defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE);

    $output_orders_heading = function ($url, $label, $link_class = '', $text_class = '') use ($can_open_commerce) {
        if (!$can_open_commerce) {
            return ($text_class !== '') ? '<span class="' . $text_class . '">' . $label . '</span>' : $label;
        }

        return '<a' . (($link_class !== '') ? ' class="' . $link_class . '"' : '') . ' href="' . $url . '">' . $label . '</a>';
    };

    $output_date_heading_cells = '';

    // Loop through the dates in order to get the date headings.
    foreach ($dates as $date) {
        $output_date_heading_cells .= '<th class="text-end">' . $output_orders_heading('view_orders.php?reset=true&amp;status=complete_or_exported&amp;advanced_filters=true' . $output_view_orders_shipping_status . '&amp;date_type=ship_date&amp;start_month=' . date('m', strtotime($date)) . '&amp;start_day=' . date('d', strtotime($date)) . '&amp;start_year=' . date('Y', strtotime($date)) . '&amp;stop_month=' . date('m', strtotime($date)) . '&amp;stop_day=' . date('d', strtotime($date)) . '&amp;stop_year=' . date('Y', strtotime($date)) . '', lang(date('D', strtotime($date))) . '<br />' . date('j', strtotime($date)) . ' ' . lang(date('M', strtotime($date)))) . '</th>';
    }

    rsort($products);

    $output_product_rows = '';

    // Loop through the products, in order to output product rows.
    foreach ($products as $product) {
        $output_description = '';
        
        // If there is a name, then add it to the description.
        if ($product['name'] != '') {
            $output_description .= h($product['name']);
        }
        
        // If there is a short description, then add it to the description.
        if ($product['short_description'] != '') {
            // If the description is not blank, then add separator.
            if ($output_description != '') {
                $output_description .= ' - ';
            }
            
            $output_description .= h($product['short_description']);
        }

        $output_before = '';

        // If the before value is not 0, then prepare to output value.
        if ($product['before'] > 0) {
            $output_before = pg_format_number($product['before'], 0);
        }

        $output_date_cells = '';

        // Loop through the dates in order to get the date cells for this product.
        foreach ($dates as $date) {
            $output_date_value = '';

            // If the value is not 0, then prepare to output value.
            if ($product[$date] > 0) {
                $output_date_value = pg_format_number($product[$date], 0);
            }

            $output_date_cells .= '<td class="text-end">' . $output_date_value . '</td>';
        }

        $output_after = '';

        // If the after value is not 0, then prepare to output value.
        if ($product['after'] > 0) {
            $output_after = pg_format_number($product['after'], 0);
        }

        $output_total = '';

        // If the total value is not 0, then prepare to output value.
        if ($product['total'] > 0) {
            $output_total = pg_format_number($product['total'], 0);
        }

        $output_product_rows .=
            '<tr>
                <th scope="row" class="chart_label fw-normal">' . ($can_open_commerce ? '<a href="' . h('edit_product.php?id=' . (int) $product['id']) . '">' . $output_description . '</a>' : $output_description) . '</th>
                <td class="text-end">' . $output_before . '</td>
                ' . $output_date_cells . '
                <td class="text-end">' . $output_after . '</td>
                <td class="text-end">' . $output_total . '</td>
            </tr>';
    }

    $output_total_before = '';

    // If the before value is not 0, then prepare to output value.
    if ($totals['before'] > 0) {
        $output_total_before = pg_format_number($totals['before'], 0);
    }

    $output_total_date_cells = '';

    // Loop through the dates in order to get the date cells for the total row.
    foreach ($dates as $date) {
        $output_total_date_value = '';

        // If the value is not 0, then prepare to output value.
        if ($totals[$date] > 0) {
            $output_total_date_value = pg_format_number($totals[$date], 0);
        }

        $output_total_date_cells .= '<td class="text-end">' . $output_total_date_value . '</td>';
    }

    $output_total_after = '';

    // If the after value is not 0, then prepare to output value.
    if ($totals['after'] > 0) {
        $output_total_after = pg_format_number($totals['after'], 0);
    }

    $output_grand_total = '';

    // If the grand total value is not 0, then prepare to output value.
    if ($totals['total'] > 0) {
        $output_grand_total = pg_format_number($totals['total'], 0);
    }

    // A plain report table, not a list: its columns are the period's days,
    // its headings open the orders of a day and its foot is the Grand Total.
    $output_report =
        '<table class="shipping_report pg-report-table">
            <thead>
                <tr>
                    <th>' . lang('Product') . '</th>
                    <th class="text-end">' . $output_orders_heading('view_orders.php?reset=true&amp;status=complete_or_exported&amp;advanced_filters=true' . $output_view_orders_shipping_status . '&amp;date_type=ship_date&amp;start_month=01&amp;start_day=01&amp;start_year=2000&amp;stop_month=' . date('m', strtotime('-1 day', strtotime($start_date))) . '&amp;stop_day=' . date('d', strtotime('-1 day', strtotime($start_date))) . '&amp;stop_year=' . date('Y', strtotime('-1 day', strtotime($start_date))) . '', lang('Before')) . '</th>
                    ' . $output_date_heading_cells . '
                    <th class="text-end">' . $output_orders_heading('view_orders.php?reset=true&amp;status=complete_or_exported&amp;advanced_filters=true' . $output_view_orders_shipping_status . '&amp;date_type=ship_date&amp;start_month=' . date('m', strtotime('+1 day', strtotime($end_date))) . '&amp;start_day=' . date('d', strtotime('+1 day', strtotime($end_date))) . '&amp;start_year=' . date('Y', strtotime('+1 day', strtotime($end_date))) . '&amp;stop_month=12&amp;stop_day=31&amp;stop_year=2030', lang('After')) . '</th>
                    <th class="text-end">' . $output_orders_heading('view_orders.php?reset=true&amp;status=complete_or_exported&amp;advanced_filters=true' . $output_view_orders_shipping_status . '&amp;date_type=ship_date&amp;start_month=01&amp;start_day=01&amp;start_year=2000&amp;stop_month=12&amp;stop_day=31&amp;stop_year=2030', lang('Total')) . '</th>
                </tr>
            </thead>
            <tbody>' . $output_product_rows . '</tbody>
            <tfoot>
                <tr class="pg-report-total">
                    <th scope="row">' . lang('Grand Total') . '</th>
                    <td class="text-end">' . $output_total_before . '</td>
                    ' . $output_total_date_cells . '
                    <td class="text-end">' . $output_total_after . '</td>
                    <td class="text-end">' . $output_grand_total . '</td>
                </tr>
            </tfoot>
        </table>';

// Otherwise there is not any data for any products, so output notice.
} else {
    $output_report = '<div class="alert theme-info m-5">' . lang('Sorry, there does not appear to be any shipping info for that date range.  Shipping info will appear for this report once shippable Products are purchased.') . '</div>';
}

// The figures and the chart over the table (includes/fn/report_view.php),
// read from the Grand Total row and the day columns the table prints.
$report_view_totals = isset($totals) ? $totals : array();
$report_view_names = array();

foreach ($dates as $date) {
    $report_view_names[] = lang(date('D', strtotime($date))) . ' ' . date('j', strtotime($date)) . ' ' . lang(date('M', strtotime($date)));
}

$report_view = array(
    'figures' => pg_report_view_shipping_figures($report_view_totals, $dates, count($products)),
    'chart' => pg_report_view_shipping_chart($report_view_totals, $dates, $report_view_names),
    'strings' => pg_report_view_strings(),
);

print
    pg_page_shell(
        array(
            'title'=> lang('Shipping Report'),
            'extra classes'=>'store pg-measure-report',
            'icon'=>'store', 
            'heading'=> lang('Shipping Report'),
            'heading_description' => lang('View the number of Products that need to be shipped or have been shipped on different days.'),
            'cancel'=>false
        )
    ) . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        // A related screen, not this one's own action: slot 2.
        'secondary' => USER_MANAGE_ECOMMERCE ? '<a class="pg-button pg-button-quiet" href="view_ship_date_adjustments.php"><i class="bi bi-wrench-adjustable-circle" aria-hidden="true"></i>' . h(lang('Ship Date Adjustments')) . '</a>' : '',
        // What the table shows: the status and the period, at the bar's end.
        'search'    => '<div class="pg-seg" role="group" aria-label="' . h(lang('Status')) . '">' . $output_status_items . '</div>
            <div class="pg-seg" role="group" aria-label="' . h(lang('Period')) . '">
                <a class="pg-seg-item pg-report-step" href="' . h('view_shipping_report.php?start_date=' . $output_previous_start_date . '&end_date=' . $output_previous_end_date) . '" title="' . h(lang('Previous')) . '" aria-label="' . h(lang('Previous')) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                <a class="pg-seg-item pg-report-step" href="' . h('view_shipping_report.php?start_date=' . $output_next_start_date . '&end_date=' . $output_next_end_date) . '" title="' . h(lang('Next')) . '" aria-label="' . h(lang('Next')) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            </div>
            <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . $output_date_range . '</span>',
    ), array('class' => 'pg-report-tools')) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <section class="pg-report-view" id="pg_report_view" aria-label="' . h(lang('Summary')) . '">
                <div class="pg-report-view-figures"></div>
                <div class="pg-report-view-chart"></div>
                <script type="application/json" id="pg_report_view_data">' . (string) json_encode($report_view, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>
            </section>
            ' . pg_js_bundle_tags('assets/js/pg_report_view.min.js') . '
            <section class="pg-card pg-report-frame" aria-labelledby="pg_report_table_title">
                <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_report_table_title">' . h(lang('Products')) . '</h3></div>
                <div class="pg-report-frame-body">
                    ' . $output_report . '
                </div>
            </section>
        </div>
    </div>
</main>
' .
output_footer();
?>