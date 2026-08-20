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

    case lang('Type'):
        $sort_column = 'type';
        break;

    case lang('Last Modified'):
        $sort_column = 'timestamp';
        break;

    default:
        $sort_column = 'timestamp';
        $asc_desc = 'DESC';
}

if (isset($_GET['sort']) && $_GET['sort']) {
    $asc_desc = isset($_GET['order']) ? $_GET['order'] : '';
}

$query = "SELECT
            offer_actions.id,
            offer_actions.name,
            offer_actions.type,
            offer_actions.discount_order_amount,
            offer_actions.discount_order_percentage,
            offer_actions.discount_product_product_id,
            offer_actions.discount_product_amount,
            offer_actions.discount_product_percentage,
            offer_actions.add_product_product_id,
            offer_actions.add_product_quantity,
            offer_actions.add_product_discount_amount,
            offer_actions.add_product_discount_percentage,
            offer_actions.discount_shipping_percentage,
            user.user_username as user,
            offer_actions.timestamp as timestamp
        FROM offer_actions
        LEFT JOIN user ON offer_actions.user = user.user_id
        ORDER BY $sort_column $asc_desc";

$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

$number_of_results = 0;

while ($row = mysqli_fetch_array($result)) {
    $id = $row['id'];
    $name = $row['name'];
    $type = $row['type'];
    $discount_order_amount = $row['discount_order_amount'];
    $discount_order_percentage = $row['discount_order_percentage'];
    $discount_product_product_id = $row['discount_product_product_id'];
    $discount_product_amount = $row['discount_product_amount'];
    $discount_product_percentage = $row['discount_product_percentage'];
    $add_product_product_id = $row['add_product_product_id'];
    $add_product_quantity = $row['add_product_quantity'];
    $add_product_discount_amount = $row['add_product_discount_amount'];
    $add_product_discount_percentage = $row['add_product_discount_percentage'];
    $discount_shipping_percentage = $row['discount_shipping_percentage'];
    $username = $row['user'];
    $timestamp = $row['timestamp'];

    switch ($type) {
        case 'discount order':
            $discount_amount = $discount_order_amount;
            $discount_percentage = $discount_order_percentage;
            $product_name = '';
            $quantity = '';
            break;

        case 'discount product':
            $discount_amount = $discount_product_amount;
            $discount_percentage = $discount_product_percentage;

            // get product name
            $query = "SELECT name FROM products WHERE id = '$discount_product_product_id'";
            $result_2 = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row_2 = mysqli_fetch_assoc($result_2);
            $product_name = $row_2['name'];

            $quantity = '';
            break;

        case 'add product':
            $discount_amount = $add_product_discount_amount;
            $discount_percentage = $add_product_discount_percentage;

            // get product name
            $query = "SELECT name FROM products WHERE id = '$add_product_product_id'";
            $result_2 = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row_2 = mysqli_fetch_assoc($result_2);
            $product_name = $row_2['name'];

            $quantity = $add_product_quantity;
            break;
            
        case 'discount shipping':
            $discount_amount = '';
            $discount_percentage = $discount_shipping_percentage;
            $product_name = '';
            $quantity = '';
            break;
    }

    // if discount amount is set, then use amount for discount
    if ($discount_amount) {
        $discount = BASE_CURRENCY_SYMBOL . sprintf("%01.2lf", $discount_amount / 100);

    // else discount amount is not set, so use percentage for discount
    } elseif ($discount_percentage) {
        $discount = $discount_percentage . '%';

    // else set discount to emtpy
    } else {
        $discount = '';
    }

    $type = lang(ucwords($type));

    $number_of_results++;

    $last_modified_username = '';
            
    if ($username) {
        $last_modified_username = lang(array('string'=>'by {var:1}','vars'=>array( h($username) ) ) );
    }

    $output_link_url = 'edit_offer_action.php?id=' . $id;
    $output_rows .=
        '<tr>
        	<td class="align-middle text-start">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " data-loading-content=" " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
            </td>
            <td class="chart_label align-middle">' . h($name) . '</td>
            <td class="align-middle"><span class="badge bg-secondary fw-light">' . $type . '</span></td>
            <td class="align-middle pe-2 text-end">' . $discount . '</td>
            <td class="align-middle">' . $product_name . '</td>
            <td class="align-middle text-center">' . $quantity . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $timestamp)) . ' ' . $last_modified_username . '</td>
        </tr>';
}

print
pg_page_shell([
        'title'=> lang('All Offer Actions'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Offer Actions')
    ]) . '
    <div class="row">
        <div class="col-12">
            <div class="row mb-2  flex-wrap">
                <div class="col-12 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . lang('All actions available to any offer.') . '" title="' . lang('All Offer Actions') . '">' . lang('All Offer Actions') . '</h2>
                    <nav id="button_bar" class="navigation " aria-label="Button Bar">
                        <a class="btn btn-sm btn-primary m-1 " href="add_offer_action.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                    </nav>
                </div>
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <table class="chart table-hover table " style="width:100%;display:none">
                        <thead>
                            <tr>
                                <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                <th>' . asc_or_desc(lang('Name'),'view_offer_actions') . '</th>
                                <th>' . asc_or_desc(lang('Type'),'view_offer_actions') . '</th>
                                <th class="text-end pe-2">' . lang('Discount') . '</th>
                                <th>' . lang('Product') . '</th>
                                <th class="text-center">' . lang('Quantity') . '</th>
                                <th>' . asc_or_desc(lang('Last Modified'),'view_offer_actions') . '</th>
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