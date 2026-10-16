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
validate_ecommerce_access($user);

include_once('liveform.class.php');
$liveform = new liveform('view_product_attributes');

// The columns this list sorts by, in the order the table shows them.
// pg_list_state() keeps the choice in the session as a code, so it survives
// a language change, and falls back to the default for anything else.
$sort_columns = array(
    'name'          => lang('Name'),
    'label'         => lang('Label & Options'),
    'created'       => lang('Created'),
    'last_modified' => lang('Last Modified'),
);

$sort_default = array('sort' => 'last_modified', 'order' => 'desc');

$list_state = pg_list_state('view_product_attributes', $sort_columns, $sort_default);
$sort_levels = $list_state['sorts'];

// What each sort code orders by. Only codes pg_list_state() let through
// reach pg_list_order_by(), and it writes nothing but these.
$order_by = pg_list_order_by($sort_levels, array(
    'name'          => 'product_attributes.name',
    'label'         => 'product_attributes.label',
    'created'       => 'product_attributes.created_timestamp',
    'last_modified' => 'product_attributes.last_modified_timestamp',
));

$all_product_attributes = 0;

// get the total number of product attributes
$query = "SELECT COUNT(*) FROM product_attributes";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$all_product_attributes = $row[0];



// Get all product attributes.
$query =
    "SELECT
        product_attributes.id,
        product_attributes.name,
        product_attributes.label,
        created_user.user_username AS created_username,
        product_attributes.created_timestamp,
        last_modified_user.user_username AS last_modified_username,
        product_attributes.last_modified_timestamp
    FROM product_attributes
    LEFT JOIN user AS created_user ON product_attributes.created_user_id = created_user.user_id
    LEFT JOIN user AS last_modified_user ON product_attributes.last_modified_user_id = last_modified_user.user_id
    ORDER BY $order_by";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$product_attributes = mysqli_fetch_items($result);

$output_rows = '';

// if there is at least one result to display
if ($product_attributes) {
    foreach ($product_attributes as $product_attribute) {
        $options = db_items(
            "SELECT
                id,
                label,
                no_value
            FROM product_attribute_options
            WHERE product_attribute_id = '" . $product_attribute['id'] . "'
            ORDER BY sort_order");

       
        $output_options = '';
        foreach ($options as $option) {
            $output_no_value = '';

            if ($option['no_value']) {
                $output_no_value = lang(' [\'No Thanks\' Option]');
            }

            $output_options .= '<li><span class="dropdown-item-text badge text-dark fw-light py-2" >' . h($option['label']) . $output_no_value . '</span></li>';
        }

       

        $created_username = '';
        
        if ($product_attribute['created_username'] != '') {
            $created_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($product_attribute['created_username']) ) ) );
        }
        
        $last_modified_username = '';
        
        if ($product_attribute['last_modified_username'] != '') {
            $last_modified_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($product_attribute['last_modified_username']) ) ) );
        }
        if($product_attribute['label'] != ''){
            $output_label = h($product_attribute['label']);
        }else{
            $output_label = '<div class="text-danger">[' . lang('No Label') . ']</div>';
        }



        $output_rows .=
            '<tr>
                <td></td>
                <td class="align-middle text-start">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'edit_product_attribute.php?id=' . $product_attribute['id'] . '\'"><i class="bi bi-pencil"></i></button>
                    <!--<button type="button" class="m-1 btn-data-control btn btn-outline-danger border-2 " title="' . lang('Delete') . '" ><i class="material-icons">delete</i></button>-->
                </td>
                <td class="chart_label align-middle">' . h($product_attribute['name']) . '</td>
                <td class="align-middle ">
                    <div class="row row-cols-2 align-middle">
                        <div class="col align-self-center">' . $output_label . '</div>
                        <div class="dropdown col">
                            <button type="button" class="m-1 btn btn-sm p-1 no-popover dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false">' . lang('Options') . '</button>
                            <ul class="dropdown-menu">
                                ' . $output_options . '
                            </ul>
                        </div>
                    </div>
                </td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $product_attribute['created_timestamp'])) . $created_username . '</td>
                <td class="align-middle">' . get_relative_time(array('timestamp' => $product_attribute['last_modified_timestamp'])) . $last_modified_username . '</td>
            </tr>';
    }
}

echo
    pg_page_shell([
        'title'=> lang('All Product Attributes'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Product Attributes'),
        'heading_description' => lang('All attributes that can be assigned to products.'),
    ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 text-center text-md-start">
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_product_attribute.php"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <div class="pg-toolbar-grow"></div>
                            ' . pg_list_sort_menu('view_product_attributes.php', $sort_columns, $sort_levels, $sort_default) . '
                        </nav>
                    </div>
                </div>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis"></th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' . get_column_heading(lang('Name'), $sort_levels, '', '', 'name') . '</th>
                                    <th>' . get_column_heading(lang('Label & Options'), $sort_levels, '', '', 'label') . '</th>
                                    <th>' . get_column_heading(lang('Created'), $sort_levels, '', '', 'created') . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
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