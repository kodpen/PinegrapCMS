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
$liveform = new liveform('edit_featured_and_new_items');

// if the form has not been submitted, then prepare to output form
if (!$_POST) {
    // get items for table
    $items = get_items_that_can_be_featured_and_new();

    // Fill the boxes from the database unless the form came back with an
    // error: after a save they show what was stored, not what was typed.
    $prefill = !$liveform->check_form_errors();
    
    $output_item_rows = '';
    
    // loop through all items in order to output rows
    foreach ($items as $item) {

        // If this item is enabled, then use green color for name and short description.
        if ($item['enabled'] == 1) {
            $status_class = 'status_enabled';
            $output_status = '';
        
        // Otherwise this item is disabled, so use red color for name and short description,
        // and say it in words for those who cannot see the color.
        } else {
            $status_class = 'status_disabled';
            $output_status = ' <span class="visually-hidden">' . h(lang('Disabled')) . '</span>';
        }

        // if this item is a product group, then prepare data in a certain way
        if ($item['type'] == 'product group') {
            $item_label = $item['name'];
            $output_name = '<strong>' . h($item['name']) . '</strong>';
            
            // if this item is not the root product group, then prepare field names
            if ($item['parent_product_group_id'] != 0) {
                $featured_sort_order_field_name = 'featured_sort_order_product_group_' . $item['id'];
                $new_date_field_name = 'new_date_product_group_' . $item['id'];
            }
            
        // else this item is a product, so prepare data in a different way
        } else {
            $item_label = $item['name'] . ' - ' . $item['short_description'];
            $output_name = h($item['name']) . ' - ' . h($item['short_description']);
            $featured_sort_order_field_name = 'featured_sort_order_product_group_' . $item['parent_product_group_id'] . '_product_' . $item['id'];
            $new_date_field_name = 'new_date_product_group_' . $item['parent_product_group_id'] . '_product_' . $item['id'];
        }        
        
        $output_padding = '';
        
        // if this is not the root level, then output padding
        if ($item['level'] != 0) {
            $output_padding = ' style="padding-left: ' . $item['level'] * 2 . 'em"';
        }

        // if this is not the root product group and we should prefill the fields, then do that
        if (($item['parent_product_group_id'] != 0) && ($prefill == true)) {
            $liveform->assign_field_value($featured_sort_order_field_name, ($item['featured'] == 1) ? $item['featured_sort_order'] : '');
            $liveform->assign_field_value($new_date_field_name, ($item['new_date'] != '0000-00-00') ? prepare_form_data_for_output($item['new_date'], 'date') : '');
        }
        
        // assume that we should not output the fields until we find out otherwise
        $output_featured_sort_order_field = '';
        $output_new_date_field = '';
        
        // if this item is not the root product group, then output fields
        if ($item['parent_product_group_id'] != 0) {
            $output_featured_sort_order_field = $liveform->output_field(array('type'=>'text', 'class'=>'form-control text-end', 'name'=>$featured_sort_order_field_name, 'aria-label' => lang(array('string' => 'Featured Order of {var:1}', 'vars' => $item_label)), 'size'=>'5', 'style'=>'width:70px;', 'maxlength'=>'5', 'inputmode'=>'numeric','data-inputmask-alias'=>'integer','data-inputmask-placeholder'=>'0', ));
            $output_new_date_field =
                $liveform->output_field(array('type'=>'text', 'id' => $new_date_field_name, 'name'=>$new_date_field_name, 'aria-label' => lang(array('string' => 'New on Date of {var:1}', 'vars' => $item_label)), 'size'=>'10', 'maxlength'=>'10','class'=>'form-control w-auto', 'data-pg-date' => '')) . pg_fields_script();
        }
        
        $output_item_rows .=
            '<tr>
                <td' . $output_padding . ' class="align-middle text-break ' . $status_class . '">' . $output_name . $output_status . '</td>
                <td class="align-middle">' . $output_featured_sort_order_field . '</td>
                <td class="align-middle">' . $output_new_date_field . '</td>
            </tr>';
    }

    // The status column: what the stored rows hold, read before any change.
    $counts = pg_featured_new_summary_counts($items);
    $summary_rows = array(
        lang('Product Groups') => pg_format_number($counts['product_groups'], 0),
        lang('Products') => pg_format_number($counts['products'], 0),
        lang('Featured') => pg_format_number($counts['featured'], 0),
        lang('New on Date') => pg_format_number($counts['new'], 0),
        lang('Disabled') => pg_format_number($counts['disabled'], 0),
    );
    $output_summary_rows = '';
    $number = 0;
    foreach ($summary_rows as $label => $value) {
        $number++;
        $output_summary_rows .= '
                                            <div class="d-flex justify-content-between gap-3 py-2' . (($number < count($summary_rows)) ? ' border-bottom' : '') . '">
                                                <dt class="fw-normal">' . h($label) . '</dt>
                                                <dd class="mb-0 fw-bold text-end">' . $value . '</dd>
                                            </div>';
    }
    $output_summary = '
                                <div class="card mb-7">
                                    <h2 class="card-header bg-reset border-0 pg-title-section">
                                        ' . lang('Summary') . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-0">' . $output_summary_rows . '
                                        </dl>
                                        <p class="form-text mb-0 mt-3">' . lang('An item with a Featured Order appears among the featured items; one with a New on Date appears among the new items from that date on. Disabled items are shown in red.') . '</p>
                                    </div>
                                </div>';

    // output product group tree screen; the rows are a plain table, not a
    // paged list: paging, search and column visibility take the rows and columns
    // they hide out of the form, and the save clears every item whose boxes were not posted
    echo
    pg_page_shell([
        'title'=> lang('Edit Featured & New Items'),
        'extra classes'=>'products pg-measure-edit',
        'icon'=>'store',
        'heading'=>lang('Edit Featured & New Items'),
        'heading_description' => lang('Update the featured and new product groups and products that should appear on catalog pages.'),
        'cancel' => array('enable' => 'true', 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_groups.php'),
        'breadcrumb' => array(
            array('label' => lang('Product Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_product_groups.php'),
            array('label' => lang('Edit Featured & New Items')),
        ),
    ]) . '<main id="content" class="container-fluid">' . get_date_picker_format() . '
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                <form name="form"  action="edit_featured_and_new_items.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Items') . '
                                </h2>
                                <div class="card-body p-0 position-relative">
                                    <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>' . lang('Item') . '</th>
                                                <th>' . lang('Featured Order') . '</th>
                                                <th>' . lang('New on Date') . '</th>
                                            </tr>
                                        </thead>
                                        <tbody>' . $output_item_rows . '</tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">' . $output_summary . '
                            </div>
                        </div>
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">
                                        <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>
                    <input type="hidden" name="max_input_vars_test" value="true">
                </form>
</main>' .
    output_footer();
        
    $liveform->remove_form('edit_featured_and_new_items');
    
// else the form has been submitted, so process form
} else {
    validate_token_field();
    
    // If the max_input_vars_test hidden field is not in the post data then that means the post data was truncated,
    // so output error. This can happen because of max_input_vars (i.e. php.ini
    // setting added in PHP v5.3.9 and often backported to earlier versions).
    // This can happen when there is a large number of products (e.g. 1,000+).
    // The default value for max_input_vars is 1,000.
    if (isset($_POST['max_input_vars_test']) == FALSE) {
        output_error(lang('Sorry, the server did not accept the form that you submitted. We recommend that you ask the server administrator to check the max_input_vars PHP setting in the php.ini file.  We recommend that it be set to a number that is at least double the number of Products that the site will contain.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }
    
    $liveform->add_fields_to_session();
    
    // get all items that can be featured and new
    $items = get_items_that_can_be_featured_and_new();
    
    // loop through all items in order to validate new date field for each item
    foreach ($items as $item) {
        // if this item is not the root product group, then continue
        if ($item['parent_product_group_id'] != 0) {
            // if this item is a product group, then prepare new date field name in a certain way
            if ($item['type'] == 'product group') {
                $new_date_field_name = 'new_date_product_group_' . $item['id'];
                
            // else this item is a product, so prepare new date field name in a different way
            } else {
                $new_date_field_name = 'new_date_product_group_' . $item['parent_product_group_id'] . '_product_' . $item['id'];
            }
            
            // if this item is a product group, then prepare name for error message in a certain way
            if ($item['type'] == 'product group') {
                $output_name = h($item['name']);
                $featured_sort_order_field_name = 'featured_sort_order_product_group_' . $item['id'];

            // else this item is a product, so prepare name for error message in a different way
            } else {
                $output_name = h($item['name']) . ' - ' . h($item['short_description']);
                $featured_sort_order_field_name = 'featured_sort_order_product_group_' . $item['parent_product_group_id'] . '_product_' . $item['id'];
            }

            // The order is a whole number from 0 to 65535 or nothing.
            if (pg_featured_sort_order_value($liveform->get_field_value($featured_sort_order_field_name)) === false) {
                $liveform->mark_error($featured_sort_order_field_name, lang(array('string' => 'Please enter a whole number from 0 to 65535 as the featured order of {var:1}.', 'vars' => $output_name)));
            }

            // if a new date was entered for this item and the date is invalid, prepare error
            if (($liveform->get_field_value($new_date_field_name) != '') && (validate_date($liveform->get_field_value($new_date_field_name)) == false)) {
                $liveform->mark_error($new_date_field_name, lang(array('string'=>'Please enter a valid new date for {var:1}.','vars'=>$output_name)));
            }
        }
    }
    
    // if there is an error, forward user back to the form
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_featured_and_new_items.php');
        exit();
    }
    
    // loop through all items in order to update featured and new data for items
    foreach ($items as $item) {
        // if this item is not the root product group, then continue
        if ($item['parent_product_group_id'] != 0) {
            // if this item is a product group, then prepare field names in a certain way
            if ($item['type'] == 'product group') {
                $featured_sort_order_field_name = 'featured_sort_order_product_group_' . $item['id'];
                $new_date_field_name = 'new_date_product_group_' . $item['id'];
                
            // else this item is a product, so prepare field names in a different way
            } else {
                $featured_sort_order_field_name = 'featured_sort_order_product_group_' . $item['parent_product_group_id'] . '_product_' . $item['id'];
                $new_date_field_name = 'new_date_product_group_' . $item['parent_product_group_id'] . '_product_' . $item['id'];
            }
            
            // if the data for the item has changed (see details below),
            // if the item was not featured and is now featured,
            // or if the item was featured and is now not featured,
            // or if the item is now featured and the featured sort order is different,
            // or if the item did not have a new date and it has a new date now,
            // or if the item did have a new date and it does not have a new date now,
            // or if the item now has a new date and the new date is different,
            // then update the item
            $posted_sort_order = pg_featured_sort_order_value($liveform->get_field_value($featured_sort_order_field_name));

            if (
                (($item['featured'] == 0) && ($posted_sort_order !== ''))
                || (($item['featured'] == 1) && ($posted_sort_order === ''))
                || (($posted_sort_order !== '') && ((int) $item['featured_sort_order'] !== $posted_sort_order))
                || (($item['new_date'] == '0000-00-00') && ($liveform->get_field_value($new_date_field_name) != ''))
                || (($item['new_date'] != '0000-00-00') && ($liveform->get_field_value($new_date_field_name) == ''))
                || (($liveform->get_field_value($new_date_field_name) != '') && ($item['new_date'] != prepare_form_data_for_input($liveform->get_field_value($new_date_field_name), 'date')))
            ) {
                // if this item is a product group, then prepare sql in a certain way
                if ($item['type'] == 'product group') {
                    $table_name = 'product_groups';
                    $sql_where = "id = ?";
                    $where_values = array((string) $item['id']);
                    
                // else this item is a product, so prepare sql in a different way
                } else {
                    $table_name = 'products_groups_xref';
                    $sql_where = "(product = ?) AND (product_group = ?)";
                    $where_values = array((string) $item['id'], (string) $item['parent_product_group_id']);
                }
                
                // assume that the item should not be featured until we find out otherwise
                $featured = '0';
                $featured_sort_order = '0';
                
                // if a featured sort order was entered for this item, then prepare to turn featured on
                if ($posted_sort_order !== '') {
                    $featured = 1;
                    $featured_sort_order = $posted_sort_order;
                }
                
                // update featured and new data for item
                $query =
                    "UPDATE $table_name
                    SET
                        featured = ?,
                        featured_sort_order = ?,
                        new_date = ?
                    WHERE $sql_where";
                $result = db_q($query, array_merge(array((string) $featured, (string) $featured_sort_order, (string) prepare_form_data_for_input($liveform->get_field_value($new_date_field_name), 'date')), $where_values)) or output_error('Query failed.');
            }
        }
    }
    
    log_activity(lang('featured and new items were modified'), $_SESSION['sessionusername']);
    
    $confirmation_message = lang('The featured and new items have been saved.');
    $liveform->add_notice($confirmation_message);

    // forward user to send to
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_featured_and_new_items.php');
}

function get_items_that_can_be_featured_and_new($parent_product_group_id = 0, $level = 0)
{
    $items = array();
    
    // get product groups under this parent product group
    $query =
        "SELECT
            id,
            name,
            enabled,
            parent_id,
            sort_order,
            display_type,
            featured,
            featured_sort_order,
            new_date
        FROM product_groups
        WHERE parent_id = ?" . pg_catalog_bin_live_sql('product_groups');
    $result = db_q($query, array((string) $parent_product_group_id)) or output_error('Query failed.');
    
    while ($row = mysqli_fetch_assoc($result)) {
        // add product_group to items array
        $items[] = 
            array (
                'type' => 'product group',
                'id' => $row['id'],
                'name' => $row['name'],
                'enabled' => $row['enabled'],
                'parent_product_group_id' => $row['parent_id'],
                'sort_order' => $row['sort_order'],
                'display_type' => $row['display_type'],
                'featured' => $row['featured'],
                'featured_sort_order' => $row['featured_sort_order'],
                'new_date' => $row['new_date'],
                'level' => $level
            );
    }
    
    // get products under this parent id
    $query =
        "SELECT
            products.id,
            products.name,
            products.enabled,
            products.short_description,
            products_groups_xref.product_group,
            products_groups_xref.sort_order,
            products_groups_xref.featured,
            products_groups_xref.featured_sort_order,
            products_groups_xref.new_date
        FROM products_groups_xref
        LEFT JOIN products ON products_groups_xref.product = products.id
        WHERE products_groups_xref.product_group = ?" . pg_catalog_bin_live_sql('products') . "
        ORDER BY name";
    $result = db_q($query, array((string) $parent_product_group_id)) or output_error('Query failed.');
    
    while ($row = mysqli_fetch_assoc($result)) {
        // add product to items array
        $items[] = 
            array (
                'type' => 'product',
                'id' => $row['id'],
                'name' => $row['name'],
                'enabled' => $row['enabled'],
                'short_description' => $row['short_description'],
                'parent_product_group_id' => $row['product_group'],
                'sort_order' => $row['sort_order'],
                'featured' => $row['featured'],
                'featured_sort_order' => $row['featured_sort_order'],
                'new_date' => $row['new_date'],
                'level' => $level
            );
    }
    
    // sort the items by sort order and then by name
    $items = pg_list_sort_by_order_and_name($items);
    
    $items_for_returning = array();
    
    // loop through the items in order to return them
    foreach ($items as $item) {
        $items_for_returning[] = $item;
        
        // if this item is a product group and the product group is a browse product group, then get items under this product group
        if (($item['type'] == 'product group') && ($item['display_type'] == 'browse')) {
            $items_for_returning = array_merge($items_for_returning, get_items_that_can_be_featured_and_new($item['id'], $level + 1));
        }
    }
    
    return $items_for_returning;
}
?>