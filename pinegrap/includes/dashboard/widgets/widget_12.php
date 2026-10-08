<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 12 - Out of Stock Products.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_12($request, $user)
{
    $output_rows = '';
    // if e-commerce is enabled and the user has access to manage e-commerce
    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
        $out_of_stock_products = array();
        $query = "SELECT
                    products.id as id,
                    products.name as name,
                    products.enabled,
	                			products.image_name  as image_name,
                    products.inventory as inventory,
                    products.inventory_quantity as inventory_quantity,
                    products.short_description as short_description,
                    products.price as price,
                    products.taxable as taxable,
                    products.form_name as form_name,
                    products.seo_score as seo_score,
                    user.user_username as user,
                    products.out_of_stock_timestamp as timestamp
                FROM products
                LEFT JOIN user ON products.user = user.user_id
                WHERE out_of_stock = '1'
                ORDER BY timestamp DESC
                LIMIT 20";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $out_of_stock_products[] = $row;
        }

        if (!empty($out_of_stock_products)) {
            // loop through the orders, in order to output rows
            foreach ($out_of_stock_products as $out_of_stock_product) {
                $output_link_url = 'edit_product.php?id=' . $out_of_stock_product['id'];

                $has_image = !empty($out_of_stock_product['image_name']);
                // A product photo fills the tile; without one the
                // tile falls back to the card accent and the icon.
                $output_rows .= pg_widget_row(array(
                    'href'  => $output_link_url,
                    'badge' => $has_image
                        ? '<img src="' . PATH . h($out_of_stock_product['image_name']) . '" alt="">'
                        : '<i class="bi bi-exclamation-diamond"></i>',
                    'name'  => h($out_of_stock_product['name']),
                    'aside' => prepare_amount($out_of_stock_product['price'] / 100),
                    'meta'  => h($out_of_stock_product['short_description'])
                        . ' &middot; ' . get_relative_time(array('timestamp' => $out_of_stock_product['timestamp'])),
                ));
            }

        } else {
            $output_rows = pg_widget_empty('bi-check2-circle', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Out of Stock Product'))), 'good');
        }


        $output_data = '
            <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
                <div class="pg-list">' . $output_rows . '</div>
            </div>';

        //return success json output
        $response = array(
            'status' => 'success',
            'message' => 'Action Success',
            'data' => $output_data,
        );
        return $response;

    } else {
        $response = array(
            'status' => 'error',
            'message' => 'Access denied'
        );
        return $response;
    }
}
