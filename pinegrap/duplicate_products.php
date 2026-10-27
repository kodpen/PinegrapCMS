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

// These are only ever appended to, or are set only for some of the filters below, so they have
// to start out empty.
$where = '';
$sql_join = '';
$filter_specific_columns = '';
$filter_specific_join = '';
$all_products_filter_selected = '';
$all_product_actions_filter_selected = '';
$shippable_product_filter_selected = '';
$recurring_product_filter_selected = '';
$donation_product_filter_selected = '';
$grant_access_product_filter_selected = '';
$membership_product_filter_selected = '';
$out_of_stock_products_filter_selected = '';
$liveform = new liveform('view_products');
$user = validate_user();
validate_ecommerce_access($user);
//   $liveform->mark_error("error yitle","error descrition");
//   $liveform->add_warning("warning title");
//   $liveform->add_notice("notice title");

if (!$_POST) {
    // If there is a filter then store it in a session
    if ($_GET['filter'] ?? '') {
        $_SESSION['software']['view_products']['filter'] = $_GET['filter'];
    }

    // if filter session is blank then set it to the default all products
    if (($_SESSION['software']['view_products']['filter'] ?? '') == '') {
        $_SESSION['software']['view_products']['filter'] = 'all_products';
    }

    $filter = ($_SESSION['software']['view_products']['filter'] ?? '');

    // If a screen was passed and it is a positive integer, then use it.
    // These checks are necessary in order to avoid SQL errors below for a bogus screen value.
    if (
        isset($_REQUEST['screen'])
        and $_REQUEST['screen']
        and is_numeric($_REQUEST['screen'])
        and $_REQUEST['screen'] > 0
        and $_REQUEST['screen'] == round($_REQUEST['screen'])
    ) {
        $screen = (int) $_REQUEST['screen'];

    // Otherwise, use the default, which is the first screen.
    } else {
        $screen = 1;
    }

    // The full product list's sort columns.
    $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'recurring_payment', 'inventory_quantity', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4');
    $sort_default = 'last_modified';

    // Set where statement and page headers for the filter
    switch ($filter) {
        case 'all_product_actions':
            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'set_start_page', 'grant_private_access', 'membership_renewal', 'order_receipt_message', 'order_receipt_bcc', 'email_page', 'email_page_bcc', 'contact_group', 'inventory_quantity', 'last_modified');

            $sql_join = "LEFT JOIN contact_groups ON contact_groups.id = products.contact_group_id ";

            // Change the heading and subheading.
            $heading = lang(array('string'=>'All Product Actions') );
            $subheading = lang(array('string'=>'Actions triggered by each product when it is ordered.') );

            // select the filter option
            $all_product_actions_filter_selected = ' selected="selected"';
            break;

        case 'shippable_products':
            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'weight', 'pwp', 'swp', 'dim', 'cont_req', 'prep', 'free_ship', 'extra_ship', 'inventory_quantity', 'last_modified');

            // If where is blank
            if ($where == '') {
                $where .= "WHERE ";
            
            // else where is not blank, so add and
            } else {
                $where .= "AND ";
            }

            // set where statement
            $where .= "shippable = '1'";

            // Change the heading and subheading.
            $heading =  lang(array('string'=>'Shippable Products') );
            $subheading =  lang(array('string'=>'All products that can be shipped to a recipient.') );

            // select the filter option
            $shippable_product_filter_selected = ' selected="selected"';
            break;
        case 'recurring_products':
            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'start', 'number_of_payments', 'payment_period', 'inventory_quantity', 'last_modified');

            // If where is blank
            if ($where == '') {
                $where .= "WHERE ";
            
            // else where is not blank, so add and
            } else {
                $where .= "AND ";
            }

            // set where statement
            $where .= "recurring = '1'";

            // Change the heading and subheading.
            $heading = lang(array('string'=>'Recurring Products') );
            $subheading = lang(array('string'=>'All products that require a recurring payment.') );

            // select the filter option
            $recurring_product_filter_selected = ' selected="selected"';
            break;
        case 'donation_products':
            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'recurring_payment', 'start', 'number_of_payments', 'payment_period', 'allow_to_schedule', 'inventory_quantity', 'last_modified');

            // If where is blank
            if ($where == '') {
                $where .= "WHERE ";
            
            // else where is not blank, so add and
            } else {
                $where .= "AND ";
            }

            // set where statement
            $where .= "selection_type = 'donation'";

            // Change the heading and subheading.
            $heading = lang(array('string'=>'Donation Products') );
            $subheading = lang(array('string'=>'All products that allow donors to enter their own amount and optionally set their donation schedule.') );

            // select the filter option
            $donation_product_filter_selected = ' selected="selected"';
            break;
        case 'grant_access_products':
            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'set_start_page', 'grant_private_access', 'inventory_quantity', 'last_modified');

            // If where is blank
            if ($where == '') {
                $where .= "WHERE ";
            
            // else where is not blank, so add and
            } else {
                $where .= "AND ";
            }

            // set where statement
            $where .= "grant_private_access = '1' AND (private_folder != '0' OR send_to_page != '0')";

            // Change the heading and subheading.
            $heading = lang(array('string'=>'Grant Access Products') );
            $subheading = lang(array('string'=>'All products that grant access a private folder\'s pages and files.') );

            // select the filter option
            $grant_access_product_filter_selected = ' selected="selected"';
            break;
        case 'membership_products':
            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'recurring_payment', 'set_start_page', 'membership_renewal', 'inventory_quantity', 'last_modified');

            // If where is blank
            if ($where == '') {
                $where .= "WHERE ";
            
            // else where is not blank, so add and
            } else {
                $where .= "AND ";
            }

            // set where statement
            $where .= "membership_renewal != '0'";

            // Change the heading and subheading.
            $heading = lang(array('string'=>'Membership Products') );
            $subheading = lang(array('string'=>'All products that grant access to all member folders, and set or extend their membership days.') );

            // select the filter option
            $membership_product_filter_selected = ' selected="selected"';
            break;

            case 'out_of_stock_products':
            

                // The columns this filter can be sorted by; pg_list_state() below
                // falls back to the default for any other.
                $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4', 'out_of_stock_date');
                $sort_default = 'out_of_stock_date';
                // If where is blank
                if ($where == '') {
                    $where .= "WHERE ";
                
                // else where is not blank, so add and
                } else {
                    $where .= "AND ";
                }
                // set where statement
                $where .= "out_of_stock = '1'";
                // Change the heading and subheading.
                $heading = lang(array('string'=>'All Out of Stock Products') );
                $subheading = lang(array('string'=>'All out of stock products, Products purchased by customers and out of stock.') );

                // select the filter option
                $out_of_stock_products_filter_selected = ' selected="selected"';
                break;
        case 'all_products':
        default:

            // Change the heading and subheading.
            $heading = lang(array('string'=>'All Products') );
            $subheading = lang(array('string'=>'Merchandise, downloads, donations, recurring fees, memberships, and simple payments.') );

            // select the filter option
            $all_products_filter_selected = ' selected="selected"';
            break;
    }

    // Headings of the columns the list can be sorted by, keyed by the code the
    // sort is kept as.
    $sort_labels = array(
        'id'                    => lang('ID'),
        'short_description'     => lang('Short Description'),
        'enabled'               => lang('Enabled'),
        'price'                 => ($filter == 'donation_products') ? lang('Default Amount (Price)') : lang('Price'),
        'taxable'               => lang('Taxable'),
        'product_form'          => lang('Product Form'),
        'selection_type'        => lang('Selection Type'),
        'default_quantity'      => lang('Default Quantity'),
        'shippable'             => lang('Shippable'),
        'weight'                => lang('Weight'),
        'pwp'                   => lang('PWP'),
        'swp'                   => lang('SWP'),
        'dim'                   => lang('Dim'),
        'cont_req'              => lang('Cont Req'),
        'prep'                  => lang('Prep'),
        'free_ship'             => lang('Free Ship'),
        'extra_ship'            => lang('Extra Ship'),
        'recurring_payment'     => lang('Recurring Payment'),
        'allow_to_schedule'     => lang('Allow to Schedule'),
        'order_receipt_message' => lang('Order Receipt Message'),
        'order_receipt_bcc'     => lang('Order Receipt BCC'),
        'email_page'            => lang('E-mail Page'),
        'email_page_bcc'        => lang('E-mail Page BCC'),
        'contact_group'         => lang('Contact Group'),
        'start'                 => lang('Start'),
        'number_of_payments'    => lang('Number of Payments'),
        'payment_period'        => lang('Payment Period'),
        'set_start_page'        => lang('Set Start Page'),
        'grant_private_access'  => lang('Grant Private Access'),
        'membership_renewal'    => lang('Membership Renewal'),
        'custom_field_1'        => ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL,
        'custom_field_2'        => ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL,
        'custom_field_3'        => ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL,
        'custom_field_4'        => ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL,
        'inventory_quantity'    => lang('Inventory Quantity'),
        'last_modified'         => lang('Last Modified'),
        'out_of_stock_date'     => lang('Out of Stock Date'),
    );

    // A custom field with no label has no column, and neither has Taxable
    // while tax is off, so they cannot be sorted by.
    $sort_columns = array();

    foreach ($sort_codes as $code) {
        if (($sort_labels[$code] != '') && (($code != 'taxable') || (ECOMMERCE_TAX == true))) {
            $sort_columns[$code] = $sort_labels[$code];
        }
    }

    $sort_default = array('sort' => $sort_default, 'order' => 'desc');

    $list_state = pg_list_state('duplicate_products', $sort_columns, $sort_default);
    $sort_levels = $list_state['sorts'];

    // What each sort code orders by. Only codes pg_list_state() let through
    // reach pg_list_order_by(), and it writes nothing but these.
    $order_by = pg_list_order_by($sort_levels, array(
        'id'                    => 'products.name',
        'short_description'     => 'products.short_description',
        'enabled'               => 'products.enabled',
        'price'                 => 'products.price',
        'taxable'               => 'products.taxable',
        'product_form'          => 'products.form_name',
        'selection_type'        => 'products.selection_type',
        'default_quantity'      => 'products.default_quantity',
        'shippable'             => 'products.shippable',
        'weight'                => 'products.weight',
        'pwp'                   => 'products.primary_weight_points',
        'swp'                   => 'products.secondary_weight_points',
        'dim'                   => 'products.length',
        'cont_req'              => 'products.container_required',
        'prep'                  => 'products.preparation_time',
        'free_ship'             => 'products.free_shipping',
        'extra_ship'            => 'products.extra_shipping_cost',
        'recurring_payment'     => 'products.recurring',
        'allow_to_schedule'     => 'products.recurring_schedule_editable_by_customer',
        'order_receipt_message' => 'products.order_receipt_message',
        'order_receipt_bcc'     => 'products.order_receipt_bcc_email_address',
        'email_page'            => 'products.email_page',
        'email_page_bcc'        => 'products.email_bcc',
        'contact_group'         => 'contact_group_name',
        'start'                 => 'products.start',
        'number_of_payments'    => 'products.number_of_payments',
        'payment_period'        => 'products.payment_period',
        'set_start_page'        => 'start_page',
        'grant_private_access'  => 'private_folder',
        'membership_renewal'    => 'products.membership_renewal',
        'custom_field_1'        => 'products.custom_field_1',
        'custom_field_2'        => 'products.custom_field_2',
        'custom_field_3'        => 'products.custom_field_3',
        'custom_field_4'        => 'products.custom_field_4',
        'inventory_quantity'    => 'products.inventory_quantity',
        'last_modified'         => 'products.timestamp',
        'out_of_stock_date'     => 'products.out_of_stock_timestamp',
    ));


    // if user requested to export products, then export them
    if (($_GET['submit_data'] ?? '') == 'Export Products') {
        // force download dialog
        header("Content-type: text/csv; charset=utf-8");
        header("Content-disposition: attachment; filename=products.csv");

        $output_custom_field_1_heading = '';

        // If the first custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
            $output_custom_field_1_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL) . '",';
        }

        $output_custom_field_2_heading = '';

        // If the second custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
            $output_custom_field_2_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL) . '",';
        }

        $output_custom_field_3_heading = '';

        // If the third custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
            $output_custom_field_3_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL) . '",';
        }

        $output_custom_field_4_heading = '';

        // If the fourth custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
            $output_custom_field_4_heading = '"' . escape_csv(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL) . '",';
        }

        // Get all of the submit form fields, so we can figure out all of the necessary columns.
        $submit_form_fields = db_items(
            "SELECT
                product_submit_form_fields.product_id,
                product_submit_form_fields.action,
                product_submit_form_fields.value,
                form_fields.name
            FROM product_submit_form_fields
            LEFT JOIN form_fields ON product_submit_form_fields.form_field_id = form_fields.id
            ORDER BY
                product_submit_form_fields.product_id,
                product_submit_form_fields.action,
                product_submit_form_fields.id");

        $submit_form_create_fields = array();
        $submit_form_update_fields = array();
        $product_submit_form_fields = array();

        foreach ($submit_form_fields as $submit_form_field) {
            switch ($submit_form_field['action']) {
                case 'create':
                    if (in_array($submit_form_field['name'], $submit_form_create_fields) == false) {
                        $submit_form_create_fields[] = $submit_form_field['name'];
                    }

                    break;
                
                case 'update':
                    if (in_array($submit_form_field['name'], $submit_form_update_fields) == false) {
                        $submit_form_update_fields[] = $submit_form_field['name'];
                    }

                    break;
            }

            $product_submit_form_fields[$submit_form_field['product_id']][$submit_form_field['action']][$submit_form_field['name']] = $submit_form_field['value'];
        }

        // output column headings for CSV data
        echo
            '"name",' .
            '"enabled",' .
            '"short_description",' .
            '"full_description",' .
            '"details",' .
            '"code",' .
            '"keywords",' .
            '"image_name",' .
            '"price",' .
            '"taxable",' .
            '"tax_rate",' .
            '"selection_type",' .
            '"default_quantity",' .
            '"address_name",' .
            '"title",' .
            '"meta_description",' .
            '"meta_keywords",' .
            '"inventory",' .
            '"inventory_quantity",' .
            '"backorder",' .
            '"out_of_stock_message",' .
            '"required_product_id",' .
            '"form",' .
            '"form_name",' .
            '"form_label_column_width",' .
            '"form_quantity_type",' .
            '"shippable",' .
            '"weight",' .
            '"primary_weight_points",' .
            '"secondary_weight_points",' .
            '"length",' .
            '"width",' .
            '"height",' .
            '"container_required",' .
            '"preparation_time",' .
            '"free_shipping",' .
            '"extra_shipping_cost",' .
            '"commissionable",' .
            '"commission_rate_limit",' .
            '"order_receipt_message",' .
            '"order_receipt_bcc_email_address",' .
            '"email_page_id",' .
            '"email_bcc_email_address",' .
            '"recurring",' .
            '"recurring_schedule_editable_by_customer",' .
            '"recurring_days_before_start",' .
            '"recurring_number_of_payments",' .
            '"recurring_payment_period",' .
            '"recurring_profile_disabled_perform_actions",' .
            '"recurring_profile_disabled_expire_membership",' .
            '"recurring_profile_disabled_revoke_private_access",' .
            '"recurring_profile_disabled_email",' .
            '"recurring_profile_disabled_email_subject",' .
            '"recurring_profile_disabled_email_page_id",' .
            '"recurring_sage_group_id",' .
            '"contact_group_id",' .
            '"membership_renewal",' .
            '"grant_private_access",' .
            '"private_folder_id",' .
            '"private_days",' .
            '"start_page_id",' .
            '"reward_points",' .
            '"gift_card",' .
            '"gift_card_email_subject",' .
            '"gift_card_email_format",' .
            '"gift_card_email_body",' .
            '"gift_card_email_page_id",' .
            '"submit_form",' .
            '"submit_form_custom_form_page_id",' .
            '"submit_form_create",' .
            '"submit_form_update",' .
            '"submit_form_update_where_field",' .
            '"submit_form_update_where_value",' .
            '"submit_form_quantity_type",' .
            '"add_comment",' .
            '"add_comment_page_id",' .
            '"add_comment_message",' .
            '"add_comment_name",' .
            '"add_comment_only_for_submit_form_update",' .
            $output_custom_field_1_heading .
            $output_custom_field_2_heading .
            $output_custom_field_3_heading .
            $output_custom_field_4_heading .
            '"notes",' .
            '"google_product_category",' .
            '"gtin",' .
            '"brand",' .
            '"mpn"';

        foreach ($submit_form_create_fields as $field) {
            echo ',"sfc_' . escape_csv($field) . '"';
        }

        foreach ($submit_form_update_fields as $field) {
            echo ',"sfu_' . escape_csv($field) . '"';
        }

        echo "\n";

        // get all products in order to export them
        $query =
            'SELECT
                id,
                name,
                enabled,
                short_description,
                full_description,
                details,
                code,
                keywords,
                image_name,
                price,
                taxable,

                tax_rate,
                selection_type,
                default_quantity,
                address_name,
                title,
                meta_description,
                meta_keywords,
                inventory,
                inventory_quantity,
                backorder,
                out_of_stock_message,
                required_product,
                form,
                form_name,
                form_label_column_width,
                form_quantity_type,
                shippable,
                weight,
                primary_weight_points,
                secondary_weight_points,
                length,
                width,
                height,
                container_required,
                preparation_time,
                free_shipping,
                extra_shipping_cost,
                commissionable,
                commission_rate_limit,
                order_receipt_message,
                order_receipt_bcc_email_address,
                email_page,
                email_bcc,
                recurring,
                recurring_schedule_editable_by_customer,
                start,
                number_of_payments,
                payment_period,
                recurring_profile_disabled_perform_actions,
                recurring_profile_disabled_expire_membership,
                recurring_profile_disabled_revoke_private_access,
                recurring_profile_disabled_email,
                recurring_profile_disabled_email_subject,
                recurring_profile_disabled_email_page_id,
                sage_group_id,
                contact_group_id,
                membership_renewal,
                grant_private_access,
                private_folder,
                private_days,
                send_to_page,
                reward_points,
                gift_card,
                gift_card_email_subject,
                gift_card_email_format,
                gift_card_email_body,
                gift_card_email_page_id,
                submit_form,
                submit_form_custom_form_page_id,
                submit_form_create,
                submit_form_update,
                submit_form_update_where_field,
                submit_form_update_where_value,
                submit_form_quantity_type,
                add_comment,
                add_comment_page_id,
                add_comment_message,
                add_comment_name,
                add_comment_only_for_submit_form_update,
                custom_field_1,
                custom_field_2,
                custom_field_3,
                custom_field_4,
                notes,
                google_product_category,
                gtin,
                brand,
                mpn
            FROM products
            ' . $where . '
            ORDER BY ' . $order_by;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $products = mysqli_fetch_items($result);

        // loop through the products in order to output CSV data
        foreach ($products as $product) {
            $output_custom_field_1 = '';

            // If the first custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                $output_custom_field_1 = '"' . escape_csv($product['custom_field_1']) . '",';
            }

            $output_custom_field_2 = '';

            // If the second custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                $output_custom_field_2 = '"' . escape_csv($product['custom_field_2']) . '",';
            }

            $output_custom_field_3 = '';

            // If the third custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                $output_custom_field_3 = '"' . escape_csv($product['custom_field_3']) . '",';
            }

            $output_custom_field_4 = '';

            // If the fourth custom product field is active, then output value for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                $output_custom_field_4 = '"' . escape_csv($product['custom_field_4']) . '",';
            }

            echo
                '"' . escape_csv($product['name']) . '",' .
                '"' . $product['enabled'] . '",' .
                '"' . escape_csv($product['short_description']) . '",' .
                '"' . escape_csv($product['full_description']) . '",' .
                '"' . escape_csv($product['details']) . '",' .
                '"' . escape_csv($product['code']) . '",' .
                '"' . escape_csv($product['keywords']) . '",' .
                '"' . escape_csv($product['image_name']) . '",' .
                '"' . sprintf('%01.2lf', $product['price'] / 100) . '",' .
                '"' . $product['taxable'] . '",' .
                '"' . $product['tax_rate'] . '",' .
                '"' . $product['selection_type'] . '",' .
                '"' . $product['default_quantity'] . '",' .
                '"' . escape_csv($product['address_name']) . '",' .
                '"' . escape_csv($product['title']) . '",' .
                '"' . escape_csv($product['meta_description']) . '",' .
                '"' . escape_csv($product['meta_keywords']) . '",' .
                '"' . $product['inventory'] . '",' .
                '"' . $product['inventory_quantity'] . '",' .
                '"' . $product['backorder'] . '",' .
                '"' . escape_csv($product['out_of_stock_message']) . '",' .
                '"' . $product['required_product'] . '",' .
                '"' . $product['form'] . '",' .
                '"' . escape_csv($product['form_name']) . '",' .
                '"' . escape_csv($product['form_label_column_width']) . '",' .
                '"' . $product['form_quantity_type'] . '",' .
                '"' . $product['shippable'] . '",' .
                '"' . $product['weight'] . '",' .
                '"' . $product['primary_weight_points'] . '",' .
                '"' . $product['secondary_weight_points'] . '",' .
                '"' . $product['length'] . '",' .
                '"' . $product['width'] . '",' .
                '"' . $product['height'] . '",' .
                '"' . $product['container_required'] . '",' .
                '"' . $product['preparation_time'] . '",' .
                '"' . $product['free_shipping'] . '",' .
                '"' . sprintf('%01.2lf', $product['extra_shipping_cost'] / 100) . '",' .
                '"' . $product['commissionable'] . '",' .
                '"' . $product['commission_rate_limit'] . '",' .
                '"' . escape_csv($product['order_receipt_message']) . '",' .
                '"' . escape_csv($product['order_receipt_bcc_email_address']) . '",' .
                '"' . $product['email_page'] . '",' .
                '"' . escape_csv($product['email_bcc']) . '",' .
                '"' . $product['recurring'] . '",' .
                '"' . $product['recurring_schedule_editable_by_customer'] . '",' .
                '"' . $product['start'] . '",' .
                '"' . $product['number_of_payments'] . '",' .
                '"' . $product['payment_period'] . '",' .
                '"' . $product['recurring_profile_disabled_perform_actions'] . '",' .
                '"' . $product['recurring_profile_disabled_expire_membership'] . '",' .
                '"' . $product['recurring_profile_disabled_revoke_private_access'] . '",' .
                '"' . $product['recurring_profile_disabled_email'] . '",' .
                '"' . escape_csv($product['recurring_profile_disabled_email_subject']) . '",' .
                '"' . $product['recurring_profile_disabled_email_page_id'] . '",' .
                '"' . $product['sage_group_id'] . '",' .
                '"' . $product['contact_group_id'] . '",' .
                '"' . $product['membership_renewal'] . '",' .
                '"' . $product['grant_private_access'] . '",' .
                '"' . $product['private_folder'] . '",' .
                '"' . $product['private_days'] . '",' .
                '"' . $product['send_to_page'] . '",' .
                '"' . $product['reward_points'] . '",' .
                '"' . $product['gift_card'] . '",' .
                '"' . escape_csv($product['gift_card_email_subject']) . '",' .
                '"' . $product['gift_card_email_format'] . '",' .
                '"' . escape_csv($product['gift_card_email_body']) . '",' .
                '"' . $product['gift_card_email_page_id'] . '",' .
                '"' . $product['submit_form'] . '",' .
                '"' . $product['submit_form_custom_form_page_id'] . '",' .
                '"' . $product['submit_form_create'] . '",' .
                '"' . $product['submit_form_update'] . '",' .
                '"' . $product['submit_form_update_where_field'] . '",' .
                '"' . $product['submit_form_update_where_value'] . '",' .
                '"' . $product['submit_form_quantity_type'] . '",' .
                '"' . $product['add_comment'] . '",' .
                '"' . $product['add_comment_page_id'] . '",' .
                '"' . escape_csv($product['add_comment_message']) . '",' .
                '"' . escape_csv($product['add_comment_name']) . '",' .
                '"' . $product['add_comment_only_for_submit_form_update'] . '",' .
                $output_custom_field_1 .
                $output_custom_field_2 .
                $output_custom_field_3 .
                $output_custom_field_4 .
                '"' . escape_csv($product['notes']) . '",' .
                '"' . escape_csv($product['google_product_category']) . '",' .
                '"' . escape_csv($product['gtin']) . '",' .
                '"' . escape_csv($product['brand']) . '",' .
                '"' . escape_csv($product['mpn']) . '"';

            foreach ($submit_form_create_fields as $field) {
                echo ',"' . escape_csv($product_submit_form_fields[$product['id']]['create'][$field]) . '"';
            }

            foreach ($submit_form_update_fields as $field) {
                echo ',"' . escape_csv($product_submit_form_fields[$product['id']]['update'][$field]) . '"';
            }

            echo "\n";
        }

        // if at least 1 product was exported, then log activity
        if (count($products) > 0) {
            // if only 1 product was exported, then prepare message phrasing in a certain way
            if (count($products) == 1) {
                $plural_suffix = '';
                $was_or_were = 'was';

            // else more than 1 product was exported, so prepare message phrasing in a different way
            } else {
                $plural_suffix = 's';
                $was_or_were = 'were';
            }

            // add log message about products being exported
            log_activity(count($products) . ' product' . $plural_suffix . ' ' . $was_or_were . ' exported', $_SESSION['sessionusername']);
        }

    // else the user did not select to export products, so just list products
    } else {
        // get total number of results for all screens, so that we can output links to different screens
        $query = "SELECT count(id) " .
                 "FROM products";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_results = $row[0];
        $all_products = $number_of_results;

    

        /* build product filter options */

        // set all products option
        $output_filter_options = '<option value="all_products"' . $all_products_filter_selected . '>' . lang(array('string'=>'All Products') ) . ' (' . pg_format_number($all_products, 0) . ')</option>';

        // set all product actions option
        $output_filter_options .= '<option value="all_product_actions"' . $all_product_actions_filter_selected . '>' . lang(array('string'=>'All Product Actions') ) . ' (' . pg_format_number($all_products, 0) . ')</option>';

        // get the amount of shippable products
        $query = "SELECT count(id) FROM products WHERE shippable = '1'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set shippable product option
        $output_filter_options .= '<option value="shippable_products"' . $shippable_product_filter_selected . '>' . lang(array('string'=>'Shippable Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of recurring products
        $query = "SELECT count(id) FROM products WHERE recurring = '1'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set recurring product option
        $output_filter_options .= '<option value="recurring_products"' . $recurring_product_filter_selected . '>' . lang(array('string'=>'Recurring Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of donation products
        $query = "SELECT count(id) FROM products WHERE selection_type = 'donation'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set donation product option
        $output_filter_options .= '<option value="donation_products"' . $donation_product_filter_selected . '>' . lang(array('string'=>'Donation Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of grant access products
        $query = "SELECT count(id) FROM products WHERE grant_private_access = '1' AND (private_folder != '0' OR send_to_page != '0')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set grant access product option
        $output_filter_options .= '<option value="grant_access_products"' . $grant_access_product_filter_selected . '>' . lang(array('string'=>'Grant Access Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of membership products
        $query = "SELECT count(id) FROM products WHERE membership_renewal != '0'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set membership product option
        $output_filter_options .= '<option value="membership_products"' . $membership_product_filter_selected . '>' . lang(array('string'=>'Membership Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of out of stock products
        $query = "SELECT count(id) FROM products WHERE out_of_stock = '1'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set out of stock product option
        $output_filter_options .= '<option value="out_of_stock_products"' . $out_of_stock_products_filter_selected . '>' . lang(array('string'=>'All Out of Stock Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';
    


        $filter_specific_columns .= '';
        $filter_specific_join .= '';

        /* Build filter specific table headers, sql joins and columns */
        if ((ECOMMERCE_TAX == true) && ($filter != 'all_product_actions')) {
            $output_tax_header =
                '<th>' . get_column_heading(lang('Taxable'), $sort_levels, '', '', 'taxable') . '</th>';
        }

        if (($filter == 'all_products') || ($filter == 'out_of_stock_products')) {

            // Set filter specific sql columns
            $filter_specific_columns .= 
                "products.shippable,
                products.selection_type as selection_type,
                products.default_quantity as default_quantity,
                products.custom_field_1,
                products.custom_field_2,
                products.custom_field_3,
                products.custom_field_4,";

            $output_custom_field_1_heading = '';

            // If the first custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                $output_custom_field_1_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL, $sort_levels, '', '', 'custom_field_1') . '</th>';
            }

            $output_custom_field_2_heading = '';

            // If the second custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                $output_custom_field_2_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL, $sort_levels, '', '', 'custom_field_2') . '</th>';
            }

            $output_custom_field_3_heading = '';

            // If the third custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                $output_custom_field_3_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL, $sort_levels, '', '', 'custom_field_3') . '</th>';
            }

            $output_custom_field_4_heading = '';

            // If the fourth custom product field is active, then output heading for it.
            if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                $output_custom_field_4_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL, $sort_levels, '', '', 'custom_field_4') . '</th>';
            }

            $output_all_products_headers = 
                '<th>' . get_column_heading(lang('Selection Type'), $sort_levels, '', '', 'selection_type') . '</th>
                <th>' . get_column_heading(lang('Default Quantity'), $sort_levels, '', '', 'default_quantity') . '</th>
                <th>' . get_column_heading(lang('Shippable'), $sort_levels, '', '', 'shippable') . '</th>
                ' . $output_custom_field_1_heading . '
                ' . $output_custom_field_2_heading . '
                ' . $output_custom_field_3_heading . '
                ' . $output_custom_field_4_heading;
        }

        if (
            ($filter == 'grant_access_products')
            || ($filter == 'membership_products')
            || ($filter == 'all_product_actions')
        ) {
            // Set filter specific sql columns
            $filter_specific_columns .= "products.grant_private_access as grant_private_access,";
        }

        if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {
            // Set filter specific sql columns
            $filter_specific_columns .= "products.membership_renewal as membership_renewal,";
        }

        if (($filter == 'all_products') ||
            ($filter == 'donation_products') ||
            ($filter == 'membership_products')) {
            
            // Set filter specific sql columns
            $filter_specific_columns .= 
            "products.recurring as recurring,";
            
            $output_recurring_header = '<th>' . get_column_heading(lang('Recurring Payment'), $sort_levels, '', '', 'recurring_payment') . '</th>';
        }

        if ($filter == 'all_product_actions') {

            // Set filter specific sql columns
            $filter_specific_columns .= 
                "email_page.page_name as email_page,
                contact_groups.name as contact_group_name,
                products.order_receipt_message as order_receipt_message,
                products.order_receipt_bcc_email_address as order_receipt_bcc_email_address,
                products.email_bcc as email_bcc,";

            // Set filter specific sql joins
            $filter_specific_join .= "LEFT JOIN page AS email_page ON email_page.page_id = products.email_page ";

            $output_all_product_actions_headers = 
                '<th>' . get_column_heading(lang('Order Receipt Message'), $sort_levels, '', '', 'order_receipt_message') . '</th>
                <th>' . get_column_heading(lang('Order Receipt BCC'), $sort_levels, '', '', 'order_receipt_bcc') . '</th>
                <th>' . get_column_heading(lang('E-mail Page'), $sort_levels, '', '', 'email_page') . '</th>
                <th>' . get_column_heading(lang('E-mail Page BCC'), $sort_levels, '', '', 'email_page_bcc') . '</th>
                <th>' . get_column_heading(lang('Contact Group'), $sort_levels, '', '', 'contact_group') . '</th>';

        // else output product form header
        } else {
            $output_product_form_header = '<th>' . get_column_heading(lang('Product Form'), $sort_levels, '', '', 'product_form') . '</th>';
        }

        if ($filter == 'shippable_products') {
            // Set filter specific sql columns
            $filter_specific_columns .= 
                "products.shippable,
                products.weight,
                products.primary_weight_points,
                products.secondary_weight_points,
                products.length,
                products.width,
                products.height,
                products.container_required,
                products.preparation_time,
                products.free_shipping,
                products.extra_shipping_cost,";

            $output_shipping_headers =
                '<th>' . get_column_heading(lang('Weight'), $sort_levels, '', '', 'weight') . '</th>
                <th>' . get_column_heading(lang('PWP'), $sort_levels, '', '', 'pwp') . '</th>
                <th>' . get_column_heading(lang('SWP'), $sort_levels, '', '', 'swp') . '</th>
                <th>' . get_column_heading(lang('Dim'), $sort_levels, '', '', 'dim') . '</th>
                <th>' . get_column_heading(lang('Cont Req'), $sort_levels, '', '', 'cont_req') . '</th>
                <th>' . get_column_heading(lang('Prep'), $sort_levels, '', '', 'prep') . '</th>
                <th>' . get_column_heading(lang('Free Ship'), $sort_levels, '', '', 'free_ship') . '</th>
                <th>' . get_column_heading(lang('Extra Ship'), $sort_levels, '', '', 'extra_ship') . '</th>
                <th>' . lang(array('string'=>'Allowed Zones') ) . '</th>
                <th>' . lang(array('string'=>'Disallowed Zones') ) . '</th>';
        }

        if (($filter == 'recurring_products') || ($filter == 'donation_products')) {

            // Set filter specific sql columns
            $filter_specific_columns .= 
                "products.start as recurring_start,
                products.number_of_payments as number_of_payments,
                products.payment_period as payment_period,
                products.recurring_schedule_editable_by_customer as recurring_schedule_editable_by_customer,";

            $output_recurring_option_headers = 
                '<th>' . get_column_heading(lang('Start'), $sort_levels, '', '', 'start') . '</th>
                <th>' . get_column_heading(lang('Number of Payments'), $sort_levels, '', '', 'number_of_payments') . '</th>
                <th>' . get_column_heading(lang('Payment Period'), $sort_levels, '', '', 'payment_period') . '</th>';
        }

        if ($filter == 'donation_products') {

            // Output dontaine price header
            $output_price_header = '<th>' . get_column_heading(lang('Default Amount (Price)'), $sort_levels, '', '', 'price') . '</th>';

            $output_recurring_set_schedule_header = '<th>' . get_column_heading(lang('Allow to Schedule'), $sort_levels, '', '', 'allow_to_schedule') . '</th>';

        // else output the default price header
        } else {
            $output_price_header = '<th>' . get_column_heading(lang('Price'), $sort_levels, '', '', 'price') . '</th>';
        }

        if (($filter == 'grant_access_products') || 
            ($filter == 'membership_products') ||
            ($filter == 'all_product_actions')) {

            // Set filter specific sql columns
            $filter_specific_columns .= "page.page_name as start_page,";
            
            // Set filter specific sql joins
            $filter_specific_join .= "LEFT JOIN page ON page.page_id = products.send_to_page ";

            $output_start_page_header = '<th>' . get_column_heading(lang('Set Start Page'), $sort_levels, '', '', 'set_start_page') . '</th>';
        }

        if (($filter == 'grant_access_products') || ($filter == 'all_product_actions')) {

            // Set filter specific sql columns
            $filter_specific_columns .= "folder.folder_name as private_folder,";

            // Set filter specific sql joins
            $filter_specific_join .= "LEFT JOIN folder ON folder.folder_id = products.private_folder ";

            $output_private_folder_access_headers = '<th>' . get_column_heading(lang('Grant Private Access'), $sort_levels, '', '', 'grant_private_access') . '</th>';
        }

        if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {

            $output_add_membership_header = '<th>' . get_column_heading(lang('Membership Renewal'), $sort_levels, '', '', 'membership_renewal') . '</th>';
        }
        if ($filter == 'out_of_stock_products') {

            $output_out_of_stock_timestamp_header = '<th>' . get_column_heading(lang('Out of Stock Date'), $sort_levels, '', '', 'out_of_stock_date') . '</th>';
        }
        /* get results for just this screen*/
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
                    $filter_specific_columns
                    user.user_username as user,
                    products.out_of_stock as out_of_stock,
                    products.out_of_stock_timestamp as out_of_stock_timestamp,
                    products.timestamp as timestamp
                 FROM products
                 LEFT JOIN user ON products.user = user.user_id
                 $sql_join
                 $filter_specific_join
                 $where
                 ORDER BY $order_by";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        while ($row = mysqli_fetch_assoc($result)) {
            $product_id = $row['id'];
            $name = h($row['name']);
            $enabled = $row['enabled'];
            $short_description = $row['short_description'];
            $price = $row['price'] / 100;
            $form_name = $row['form_name'];
            $seo_score = $row['seo_score'];
            // Only the filters that show these columns select them.
            $selection_type = $row['selection_type'] ?? '';
            $default_quantity = $row['default_quantity'] ?? '';
            $shippable = $row['shippable'] ?? '';
            $recurring = $row['recurring'] ?? '';
            $recurring_start = $row['recurring_start'] ?? '';
            $number_of_payments = $row['number_of_payments'] ?? '';
            $payment_period = $row['payment_period'] ?? '';
            $recurring_schedule_editable_by_customer = $row['recurring_schedule_editable_by_customer'] ?? '';
            $start_page = $row['start_page'] ?? '';
            $grant_private_access = $row['grant_private_access'] ?? '';
            $private_folder = $row['private_folder'] ?? '';
            $membership_renewal = $row['membership_renewal'] ?? '';
            $order_receipt_message = $row['order_receipt_message'] ?? '';
            $order_receipt_bcc_email_address = $row['order_receipt_bcc_email_address'] ?? '';
            $email_page = $row['email_page'] ?? '';
            $email_bcc = $row['email_bcc'] ?? '';
            $contact_group_name = $row['contact_group_name'] ?? '';
            $custom_field_1 = $row['custom_field_1'] ?? '';
            $custom_field_2 = $row['custom_field_2'] ?? '';
            $custom_field_3 = $row['custom_field_3'] ?? '';
            $custom_field_4 = $row['custom_field_4'] ?? '';
            $inventory = $row['inventory'];
    		$inventory_quantity = $row['inventory_quantity'];
    		$image_name = $row['image_name'];
            // set checkmark image for columns to use
            $output_checkmark = '<span class="material-icons">task_alt</span>';

            // set link url
            $output_link_url = 'edit_product.php?id=' . $row['id'];

            $output_name_and_short_description_color_class = '';
            $output_enabled_check_mark = '';

            // If this product is enabled, then use green color class for name and short description,
            // and output check mark for enabled column.
            if ($enabled == 1) {
                $output_name_and_short_description_color_class = 'text-success';
                $output_enabled_check_mark = $output_checkmark;
            
            // Otherwise this product is disabled, so use red color class for name and short description,
            // and do not output check mark for enabled column.
            } else {
                $output_name_and_short_description_color_class = 'text-danger';
            }

            // if tax is on, prepare tax data
            if ((ECOMMERCE_TAX == true) && ($filter != 'all_product_actions')) {
                $taxable = $row['taxable'];

                if ($taxable == 1) {
                    $taxable = $output_checkmark;
                } else {
                    $taxable = '';
                }

                // output column
                $output_tax_column = '<td class="align-middle text-center">' . $taxable . '</td>';
            }
        
    		$output_image_header ='';
            $output_image_column ='';
    		//default show image
    		$show_image = true;
    		if($show_image == true){
    			$output_image_header ='<th>' . lang(array('string'=>'Image') ) . '</th>';
    			$output_image_column = pg_product_image_cell('product-' . (int) $row['id'], $image_name, $row['name'], $short_description);
    		}

            // output filter specific table cells
            if (($filter == 'all_products') || ($filter == 'out_of_stock_products')) {
                $output_selection_type = '';

                switch ($selection_type) {
                    case 'checkbox':
                        $output_selection_type = lang(array('string'=>'Checkbox') );
                        break;

                    case 'quantity':
                        $output_selection_type = lang(array('string'=>'Quantity') );
                        break;

                    case 'donation':
                        $output_selection_type = lang(array('string'=>'Donation') );
                        break;

                    case 'autoselect':
                        $output_selection_type = lang(array('string'=>'Auto-Select') );
                        break;
                }

                // if shippable is on then output checkmark
                if ($shippable == 1) {
                    $output_shippable = $output_checkmark;
                } else {
                    $output_shippable = '';
                }

                $output_custom_field_1_cell = '';

                // If the first custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL != '') {
                    $output_custom_field_1_cell .= '<td class="align-middle">' . h($custom_field_1) . '</td>';
                }

                $output_custom_field_2_cell = '';

                // If the second custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
                    $output_custom_field_2_cell .= '<td class="align-middle">' . h($custom_field_2) . '</td>';
                }

                $output_custom_field_3_cell = '';

                // If the third custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
                    $output_custom_field_3_cell .= '<td class="align-middle">' . h($custom_field_3) . '</td>';
                }

                $output_custom_field_4_cell = '';

                // If the fourth custom product field is active, then output cell for it.
                if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
                    $output_custom_field_4_cell .= '<td class="align-middle">' . h($custom_field_4) . '</td>';
                }

                // output columns
                $output_all_products_columns = 
                    '<td class="align-middle">' . $output_selection_type . '</td>
                    <td class="align-middle text-center">' . $default_quantity . '</td>
                    <td class="align-middle text-center">' . $output_shippable . '</td>
                    ' . $output_custom_field_1_cell . '
                    ' . $output_custom_field_2_cell . '
                    ' . $output_custom_field_3_cell . '
                    ' . $output_custom_field_4_cell;
            }

            // output filter specific table cells
            if (($filter == 'all_products') ||
                ($filter == 'donation_products') ||
                ($filter == 'membership_products')) {
                
                // if recurring is on then output checkmark
                if ($recurring == 1) {
                    $output_recurring = $output_checkmark;
                } else {
                    $output_recurring = '';
                }

                // output column
                $output_recurring_column = '<td class="align-middle text-center">' . $output_recurring . '</td>';
            }

            // output filter specific table cells
            if ($filter == 'all_product_actions') {
                // if there is an order receipt message then output checkmark
                if ($order_receipt_message != '') {
                    $output_order_receipt_message = $output_checkmark;
                } else {
                    $output_order_receipt_message = '';
                }

                // output columns
                $output_all_product_actions_columns = 
                    '<td class="align-middle text-center">' . $output_order_receipt_message . '</td>
                    <td class="align-middle">' . h($order_receipt_bcc_email_address) . '</td>
                    <td class="align-middle">' . h($email_page) . '</td>
                    <td class="align-middle">' . h($email_bcc) . '</td>
                    <td class="align-middle">' . h($contact_group_name) . '</td>';
            
            // else output product form column
            } else {
                $output_product_form_column = '<td class="align-middle">' . h($form_name) . '</td>';
            }

            // output filter specific table cells
            if ($filter == 'shippable_products') {

                $weight = '';

                if ($row['weight'] > 0) {
                    $weight = ($row['weight']+0) . ' lb';
                }

                $primary_weight_points = $row['primary_weight_points'];
                $secondary_weight_points = $row['secondary_weight_points'];

                $dimensions = '';

                if (($row['length'] > 0) or ($row['width'] > 0) or ($row['height'] > 0)) {
                    $dimensions = ($row['length']+0) . '&Prime; x ' . ($row['width']+0) . '&Prime; x ' . ($row['height']+0) . '&Prime;';
                }

                $container_required = '';

                if ($row['container_required']) {
                    $container_required = '<span class="material-icons">task_alt</span>';
                }

                $preparation_time = $row['preparation_time'];
                $free_shipping = $row['free_shipping'];

                if ($free_shipping == 1) {
                    $free_shipping = '<span class="material-icons">task_alt</span>';
                } else {
                    $free_shipping = '';
                }

                $extra_shipping_cost = pg_format_money($row['extra_shipping_cost'] / 100, BASE_CURRENCY_SYMBOL);


                $output_allowed_zones = '';
                $output_disallowed_zones = '';
                $disallowed_zones_sql_where = '';

                // Get allowed zones
                $query2 = "SELECT 
                             zones.id,
                             zones.name 
                          FROM zones 
                          LEFT JOIN products_zones_xref ON products_zones_xref.zone_id = zones.id 
                          WHERE products_zones_xref.product_id = '" . escape($product_id) . "'
                          ORDER BY zones.name ASC";
                $result2 = mysqli_query(db::$con, $query2) or output_error('Query failed.');

                // loop through and prepare allowed zones
                while($row2 = mysqli_fetch_assoc($result2)){

                    // if there are already allowed zones then output a comma and a break tag
                    if ($output_allowed_zones) {
                        $output_allowed_zones .= ',<br />';
                    }

                    // output allowed zone
                    $output_allowed_zones .= h($row2['name']);

                    // If disallowed zones where is blank
                    if ($disallowed_zones_sql_where == '') {
                        $disallowed_zones_sql_where .= 'WHERE ';

                    // else where is not blank, so add and
                    } else {
                        $disallowed_zones_sql_where .= 'AND ';
                    }

                    // add zone id to where statement to exclude this zone from disallowed zones
                    $disallowed_zones_sql_where .= "id != '" . escape($row2['id']) . "'";
                }

                // Get disallowed zones
                $query2 = "SELECT name FROM zones
                          $disallowed_zones_sql_where
                          ORDER BY zones.name ASC";
                $result2 = mysqli_query(db::$con, $query2) or output_error('Query failed.');

                // loop through and prepare disallowed zones
                while($row2 = mysqli_fetch_assoc($result2)){

                    if ($output_disallowed_zones) {
                        $output_disallowed_zones .= ',<br />';
                    }

                    // output disallowed zones
                    $output_disallowed_zones .= h($row2['name']);
                }

                // output columns
                $output_shipping_columns =
                    '<td class="align-middle text-center">' . $weight . '</td>
                    <td class="align-middle text-center">' . $primary_weight_points . '</td>
                    <td class="align-middle text-center">' . $secondary_weight_points . '</td>
                    <td class="align-middle">' . $dimensions . '</td>
                    <td class="align-middle text-center">' . $container_required . '</td>
                    <td class="align-middle text-center">' . $preparation_time . '</td>
                    <td class="align-middle text-center">' . $free_shipping . '</td>
                    <td class="align-middle text-end">' . $extra_shipping_cost . '</td>
                    <td class="align-middle">' . $output_allowed_zones . '</td>
                    <td class="align-middle">' . $output_disallowed_zones . '</td>';
            }

            // output filter specific table cells
            if (($filter == 'recurring_products') || ($filter == 'donation_products')) {
                // If there is a start date
                if ($recurring_start != '0') {
                    // output amount of days
                    $output_recurring_start = $recurring_start;

                    // If the start is set to one day then output day, else output days.
                    if ($recurring_start == '1') {
                        $output_recurring_start .= ' Day';
                    } else {
                        $output_recurring_start .= ' Days';
                    }

                // else output default
                } else {
                    $output_recurring_start = 'Immediately';
                }

                // if number of payments is not 0 then output the number of payments
                if ($number_of_payments != '0') {
                    $output_number_of_payments = $number_of_payments;

                // else output default
                } else {
                    $output_number_of_payments = 'Unlimited';
                }

                // output columns
                $output_recurring_option_columns = 
                    '<td class="align-middle">' . h($output_recurring_start) . '</td>
                    <td class="align-middle text-center">' . h($output_number_of_payments) . '</td>
                    <td class="align-middle">' . h($payment_period) . '</td>';
            }

            // output filter specific table cells
            if ($filter == 'donation_products') {
                // if customer is able to set the schedule is on then output checkmark
                if ($recurring_schedule_editable_by_customer == 1) {
                    $output_recurring_schedule_editable_by_customer = $output_checkmark;
                } else {
                    $output_recurring_schedule_editable_by_customer = '';
                }

                $output_recurring_set_schedule_column = '<td class="align-middle text-center">' . $output_recurring_schedule_editable_by_customer . '</td>';
            }

            // output filter specific table cells
            if (($filter == 'grant_access_products') || 
                ($filter == 'membership_products') ||
                ($filter == 'all_product_actions')) {
                
                // if grant private access is on then output start page
                if ($grant_private_access == '1') {
                    $output_start_page = $start_page;

                // else output nothing
                } else {
                    $output_start_page = '';
                }

                // output column
                $output_start_page_column = '<td class="align-middle">' . h($output_start_page) . '</td>';
            }

            // output filter specific table cells
            if (($filter == 'grant_access_products') || ($filter == 'all_product_actions')) {
                // if grant private access is on then output private folder
                if ($grant_private_access == '1') {
                    $output_grant_private_folder_access = $private_folder;

                // else output nothing
                } else {
                    $output_grant_private_folder_access = '';
                }

                // output columns
                $output_private_folder_access_columns = '<td class="align-middle ">' . h($output_grant_private_folder_access) . '</td>';
            }

            // output filter specific table cells
            if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {
                // if there is a membership renewal value then output it
                if ($membership_renewal != '0') {
                    $output_membership_renewal = $membership_renewal;

                    // If the membership renewal field is set to one day then output day, else output days.
                    if ($membership_renewal == '1') {
                        $output_membership_renewal .= ' Day';
                    } else {
                        $output_membership_renewal .= ' Days';
                    }

                // else do not output anything
                } else {
                    $output_membership_renewal = '';
                }

                // output column
                $output_add_membership_column = '<td class="align-middle">' . h($output_membership_renewal) . '</td>';
            }     

            // output filter specific table cells
            if ($filter == 'out_of_stock_products') {
            
                $output_out_of_stock_timestamp_column = '<td class="align-middle">' . get_relative_time(array('timestamp' => $row['out_of_stock_timestamp'])) . '</td>';
            }else{

                $output_inventory_headers ='<th>' . get_column_heading(lang('Inventory Quantity'), $sort_levels, '', '', 'inventory_quantity') . '</th>';
                $output_inventory_columns ='<td class="align-middle text-center text-success h5 fw-bolder" title="' . lang('Inventory tracking is not active for this product/service') . '">-</td>';
                if ($inventory == 1){
                    if ($inventory_quantity >= 10){
                        $output_inventory_columns ='<td class="align-middle text-center text-success h5 fw-bolder">'.$inventory_quantity.'</td>';
                    }else{
                        $output_inventory_columns ='<td class="align-middle text-center text-warning h5 fw-bolder">'.$inventory_quantity.'</td>';
                    }
                    if ($inventory_quantity == 0){
                        $output_inventory_columns ='<td class="align-middle text-center text-danger h5 fw-bolder">'.$inventory_quantity.'</td>';
                    }
                }

            }

            $output_rows .=
            '<tr>
                <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="products[]" value="' . $row['id'] . '" class="checkbox" /></td>
        
                ' . $output_image_column . '
                <td class="align-middle chart_label ' . $output_name_and_short_description_color_class . '">' . $name . '</td>
                <td class=" align-middle ' . $output_name_and_short_description_color_class . '">' . $short_description . '</td>
                <td class="align-middle text-center">' . $output_enabled_check_mark . '</td>
                <td class="align-middle text-end">' . prepare_amount($price) . '</td>
                ' . ($output_inventory_columns ?? '') . '
                ' . ($output_tax_column ?? '') . '
                ' . ($output_product_form_column ?? '') . '
                ' . ($output_all_products_columns ?? '') . ' 
                ' . ($output_shipping_columns ?? '') . '
                ' . ($output_recurring_column ?? '') . '
                ' . ($output_recurring_option_columns ?? '') . '
                ' . ($output_recurring_set_schedule_column ?? '') . '
                ' . ($output_start_page_column ?? '') . '
                ' . ($output_private_folder_access_columns ?? '') . '
                ' . ($output_add_membership_column ?? '') . '
                ' . ($output_all_product_actions_columns ?? '') . '
                <td class="align-middle">' . get_relative_time(array('timestamp' => $row['timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($row['user']) ) ) ) . '</td>
                ' . ($output_out_of_stock_timestamp_column ?? '') . '
            </tr>';
        }

        $output = '
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->get_warnings() . '
                    ' . $liveform->output_notices() . '
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 text-center text-md-start">
                            <nav id="button_bar" class="navigation " aria-label="Button Bar">
                                <div class="pg-toolbar-grow"></div>
                                ' . pg_list_sort_menu('duplicate_products.php', $sort_columns, $sort_levels, $sort_default) . '
                            </nav>
                        </div>
                    </div>
                    <div class="card my-4">
                        <div class="card-body p-0 position-relative">
                            <form name="form"  action="duplicate_products.php" method="post"> 
                                ' . get_token_field() . '
                                <input type="hidden" name="action">
                                <input type="hidden" name="edit_enabled">
                                <input type="hidden" name="edit_allowed_zones">
                                <input type="hidden" name="edit_disallowed_zones">
                                <input type="hidden" name="edit_change_price_method">
                                <input type="hidden" name="edit_price_value">
                                <input type="hidden" name="edit_inventory">
                                <input type="hidden" name="edit_inventory_quantity_process">
                                <input type="hidden" name="edit_tax_rate_method">
                                <input type="hidden" name="edit_tax_rate_value">
                                <input type="hidden" name="edit_inventory_quantity">
                                <table class="chart table-hover table" style="width:100%;display:none">
                                    <thead>
                                        <tr>
                                            <th class="noVis">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                                </div>
                                            </th>

                                            ' . $output_image_header . ' 
                                            <th>' . get_column_heading(lang('ID'), $sort_levels, '', '', 'id') . '</th>
                                            <th>' . get_column_heading(lang('Short Description'), $sort_levels, '', '', 'short_description') . '</th>
                                            <th>' . get_column_heading(lang('Enabled'), $sort_levels, '', '', 'enabled') . '</th>
                                            ' . ($output_price_header ?? '') . ' 
                                            ' . ($output_inventory_headers ?? '') . '
                                            ' . ($output_tax_header ?? '') . '  
                                            ' . ($output_product_form_header ?? '') . ' 
                                            ' . ($output_all_products_headers ?? '') . ' 
                                            ' . ($output_shipping_headers ?? '') . ' 
                                            ' . ($output_recurring_header ?? '') . ' 
                                            ' . ($output_recurring_option_headers ?? '') . ' 
                                            ' . ($output_recurring_set_schedule_header ?? '') . ' 
                                            ' . ($output_start_page_header ?? '') . ' 
                                            ' . ($output_private_folder_access_headers ?? '') . ' 
                                            ' . ($output_add_membership_header ?? '') . ' 
                                            ' . ($output_all_product_actions_headers ?? '') . ' 
                                            <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', '', 'last_modified') . '</th>
                                            ' . ($output_out_of_stock_timestamp_header ?? '') . ' 
                                        </tr>
                                    </thead>
                                    <tbody>' . $output_rows . '</tbody>
                                </table>
                                
                              
                                <div class="row g-2 p-3 mb-4">
                                    <div class="col-12">
                                    <p>' . h(lang('The new letter replaces the 1 character removed from the ID and Short Description fields.')) . '</p>
                                    </div>
                                    <div class="col-auto">
                                        <label for="lettertodelete">' . h(lang('Number of Letters to Remove')) . '</label>
                                        <input type="number" class="form-control" id="lettertodelete" name="lettertodelete" value="1" min="1" />
                                        <span class="small">' . h(lang('Add +1 for each space as well')) . '</span>
                                    </div>
                                    <div class="col">
                                        <label for="newletter">' . h(lang('Word or Letter to Add')) . '</label>
                                        <input type="text" class="form-control" id="newletter" name="newletter" />
                                    </div>
                                    <div class="col">
                                    <label for="option_id">Assortment</label>
                                        <select class="form-control" id="option_id" name="option_id">
                                        <option value="">-Do not Change-</option>
                                        <option value="21" >Assortment A</option>
                                        <option value="134" >Assortment B</option>
                                        <option value="23" >Assortment C</option>
                                        <option value="22" >Assortment D</option>
                                        <option value="130" >Assortment E</option>
                                        <option value="131" >Assortment F</option>
                                        <option value="132" >Assortment G</option>
                                        <option value="133" >Assortment H</option>
                                        <option value="93" >Assortment X</option>
                                        <option value="135" >Assortment Y</option>
                                    </select></div>
                                    <div class="col"><button type="submit" value="Duplicate Selected" class=" mt-4 btn btn-secondary"><span class="material-icons me-2">edit</span>' . h(lang('Duplicate Selected')) . '</button></div>
                                </div>
                                
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        ';

        print pg_page_shell([
        'title'=> $heading,
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>$heading,
        'heading_description' => ($subheading ?? '')
    ]) . '<main id="content" class="container-fluid">' . $output . _pg_lightbox_assets_once() . '</main>' . output_footer();
        $liveform->remove_form('view_products');
    }
}else{

    // The form above carries the token; a POST from anywhere else is refused
    // before a single product is copied.
    validate_token_field();

    // $option is the assortment option row (id, product_attribute_id) checked
    // below before anything is copied, or an empty array for "Do not Change".
    function duplicate_products($id,$lettertodelete,$newletter,$option) {
        // get original product's info
        $result = mysqli_query(db::$con, "SELECT * FROM products WHERE id = '" . escape($id) . "'") or output_error(lang('Query failed'));
        $row                   = mysqli_fetch_assoc($result);
        $original_product_name = $row['name'];
        $product_name          = get_unique_name(array(
            'name' => $row['name'],
            'type' => 'product'
        ));
        // An emptied field or a value that is not a count of at least one falls
        // back to the field's default; negating a non-numeric string below
        // would end the request with a TypeError on PHP 8.
        if (!is_numeric($lettertodelete) || ($lettertodelete < 1)) {
            $lettertodelete = 1;
        }

        $lettertodelete = (int) $lettertodelete;

        $product_name = substr($product_name, 0, -4) . $newletter;
        $short_description = substr($row['short_description'], 0, -$lettertodelete) . $newletter;
        $row['address_name'] = '';
        // if the address name is NOT blank then use that value for the address name
        if ($row['address_name'] != '') {
            $address_name = $row['address_name'];
            // else if the short description is NOT blank then use that value
        } elseif ($row['short_description'] != '') {
            $address_name = $row['short_description'];
            // else if the name is NOT blank then use that value
        } elseif ($row['name'] != '') {
            $address_name = $row['name'];
            // else use id
        } else {
            $address_name = $row['id'];
        }
        // prepare the address name for the database
        $address_name = prepare_catalog_item_address_name($address_name);

        // products.compare_at_price (2026.11.0) is copied where the table has
        // it, which the SELECT * above shows: the copy starts with the
        // original's "was" price as it starts with its price.
        $compare_at_column = array_key_exists('compare_at_price', $row) ? 'compare_at_price,' : '';
        $compare_at_value  = array_key_exists('compare_at_price', $row) ? pg_compare_at_price_sql($row['compare_at_price']) . ',' : '';

        // products.ai_fields (2026.11.0) likewise: the copy keeps the marks of
        // the texts it writes unchanged; the short description, whose last
        // letters are replaced, keeps its mark only if it is still the same.
        $ai_fields = pg_ai_fields_copy('product', $row, array(
            'short_description' => $short_description,
            'full_description'  => $row['full_description'],
            'title'             => $row['title'],
            'meta_description'  => $row['meta_description'],
        ));
        $ai_fields_column = ($ai_fields !== false) ? 'ai_fields,' : '';
        $ai_fields_value  = ($ai_fields !== false) ? (($ai_fields === null) ? 'NULL' : "'" . e($ai_fields) . "'") . ',' : '';

        // insert row into product table
        $query        = "INSERT INTO products (
    
                name,
    
                enabled,
    
                short_description,
    
                full_description,
    
                details,
    
                code,
    
                keywords,
    
                image_name,
    
                price,
    
                taxable,

                tax_rate,
    
                contact_group_id,
    
                order_receipt_bcc_email_address,
    
                email_page,
    
                email_bcc,
    
                order_receipt_message,
    
                required_product,
    
                selection_type,
    
                default_quantity,
    
                minimum_quantity,
    
                maximum_quantity,
    
                address_name,
    
                title,
    
                meta_description,
    
                meta_keywords,
    
                inventory,
    
                inventory_quantity,
    
                backorder,
    
                out_of_stock_message,
    
                shippable,
    
                weight,
    
                primary_weight_points,
    
                secondary_weight_points,
    
                length,
    
                width,
    
                height,
    
                container_required,
    
                preparation_time,
    
                free_shipping,
    
                extra_shipping_cost,
    
                commissionable,
    
                commission_rate_limit,
    
                recurring,
    
                recurring_schedule_editable_by_customer,
    
                start,
    
                number_of_payments,
    
                payment_period,
    
                recurring_profile_disabled_perform_actions,
    
                recurring_profile_disabled_expire_membership,
    
                recurring_profile_disabled_revoke_private_access,
    
                recurring_profile_disabled_email,
    
                recurring_profile_disabled_email_subject,
    
                recurring_profile_disabled_email_page_id,
    
                sage_group_id,
    
                membership_renewal,
    
                grant_private_access,
    
                private_folder,
    
                private_days,
    
                send_to_page,
    
                reward_points,
    
                gift_card,
    
                gift_card_email_subject,
    
                gift_card_email_format,
    
                gift_card_email_body,
    
                gift_card_email_page_id,
    
                submit_form,
    
                submit_form_custom_form_page_id,
    
                submit_form_create,
    
                submit_form_update,
    
                submit_form_update_where_field,
    
                submit_form_update_where_value,
    
                submit_form_quantity_type,
    
                add_comment,
    
                add_comment_page_id,
    
                add_comment_message,
    
                add_comment_name,
    
                add_comment_only_for_submit_form_update,
    
                form,
    
                form_name,
    
                form_label_column_width,
    
                form_quantity_type,
    
                custom_field_1,
    
                custom_field_2,
    
                custom_field_3,
    
                custom_field_4,
    
                notes,
    
                google_product_category,
    
                gtin,
    
                brand,
    
                mpn,
    
                " . $compare_at_column . "
    
                " . $ai_fields_column . "
    
                user,
    
                timestamp)
    
            VALUES (
    
                '" . escape($product_name) . "',
    
                '" . escape($row['enabled']) . "',
    
                '" . escape($short_description) . "',
    
                '" . escape($row['full_description']) . "',
    
                '" . escape($row['details']) . "',
    
                '" . escape($row['code']) . "',
    
                '" . escape($row['keywords']) . "',
    
                '" . escape($row['image_name']) . "',
    
                '" . escape($row['price']) . "',
    
                '" . escape($row['taxable']) . "',

                " . (($row['tax_rate'] === null) ? 'NULL' : "'" . escape($row['tax_rate']) . "'") . ",
    
                '" . escape($row['contact_group_id']) . "',
    
                '" . escape($row['order_receipt_bcc_email_address']) . "',
    
                '" . escape($row['email_page']) . "',
    
                '" . escape($row['email_bcc']) . "',
    
                '" . escape($row['order_receipt_message']) . "',
    
                '" . escape($row['required_product']) . "',
    
                '" . escape($row['selection_type']) . "',
    
                '" . escape($row['default_quantity']) . "',
    
                '" . escape($row['minimum_quantity']) . "',
    
                '" . escape($row['maximum_quantity']) . "',
    
                '" . escape($address_name) . "',
    
                '" . escape($row['title']) . "',
    
                '" . escape($row['meta_description']) . "',
    
                '" . escape($row['meta_keywords']) . "',
    
                '" . escape($row['inventory']) . "',
    
                '" . escape($row['inventory_quantity']) . "',
    
                '" . escape($row['backorder']) . "',
    
                '" . escape($row['out_of_stock_message']) . "',
    
                '" . escape($row['shippable']) . "',
    
                '" . escape($row['weight']) . "',
    
                '" . escape($row['primary_weight_points']) . "',
    
                '" . escape($row['secondary_weight_points']) . "',
    
                '" . e($row['length']) . "',
    
                '" . e($row['width']) . "',
    
                '" . e($row['height']) . "',
    
                '" . e($row['container_required']) . "',
    
                '" . escape($row['preparation_time']) . "',
    
                '" . escape($row['free_shipping']) . "',
    
                '" . escape($row['extra_shipping_cost']) . "',
    
                '" . escape($row['commissionable']) . "',
    
                '" . escape($row['commission_rate_limit']) . "',
    
                '" . escape($row['recurring']) . "',
    
                '" . escape($row['recurring_schedule_editable_by_customer']) . "',
    
                '" . escape($row['start']) . "',
    
                '" . escape($row['number_of_payments']) . "',
    
                '" . escape($row['payment_period']) . "',
    
                '" . escape($row['recurring_profile_disabled_perform_actions']) . "',
    
                '" . escape($row['recurring_profile_disabled_expire_membership']) . "',
    
                '" . escape($row['recurring_profile_disabled_revoke_private_access']) . "',
    
                '" . escape($row['recurring_profile_disabled_email']) . "',
    
                '" . escape($row['recurring_profile_disabled_email_subject']) . "',
    
                '" . escape($row['recurring_profile_disabled_email_page_id']) . "',
    
                '" . escape($row['sage_group_id']) . "',
    
                '" . escape($row['membership_renewal']) . "',
    
                '" . escape($row['grant_private_access']) . "',
    
                '" . escape($row['private_folder']) . "',
    
                '" . escape($row['private_days']) . "',
    
                '" . escape($row['send_to_page']) . "',
    
                '" . escape($row['reward_points']) . "',
    
                '" . escape($row['gift_card']) . "',
    
                '" . escape($row['gift_card_email_subject']) . "',
    
                '" . escape($row['gift_card_email_format']) . "',
    
                '" . escape($row['gift_card_email_body']) . "',
    
                '" . escape($row['gift_card_email_page_id']) . "',
    
                '" . escape($row['submit_form']) . "',
    
                '" . escape($row['submit_form_custom_form_page_id']) . "',
    
                '" . escape($row['submit_form_create']) . "',
    
                '" . escape($row['submit_form_update']) . "',
    
                '" . e($row['submit_form_update_where_field']) . "',
    
                '" . e($row['submit_form_update_where_value']) . "',
    
                '" . e($row['submit_form_quantity_type']) . "',
    
                '" . escape($row['add_comment']) . "',
    
                '" . escape($row['add_comment_page_id']) . "',
    
                '" . escape($row['add_comment_message']) . "',
    
                '" . escape($row['add_comment_name']) . "',
    
                '" . escape($row['add_comment_only_for_submit_form_update']) . "',
    
                '" . escape($row['form']) . "',
    
                '" . escape($row['form_name']) . "',
    
                '" . escape($row['form_label_column_width']) . "',
    
                '" . escape($row['form_quantity_type']) . "',
    
                '" . escape($row['custom_field_1']) . "',
    
                '" . escape($row['custom_field_2']) . "',
    
                '" . escape($row['custom_field_3']) . "',
    
                '" . escape($row['custom_field_4']) . "',
    
                '" . escape($row['notes']) . "', 
    
                '" . escape($row['google_product_category']) . "',
    
                '" . escape($row['gtin']) . "',
    
                '" . escape($row['brand']) . "', 
    
                '" . escape($row['mpn']) . "',
    
                " . $compare_at_value . "
    
                " . $ai_fields_value . "
    
                '" . USER_ID . "',
    
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
        $new_product_id = mysqli_insert_id(db::$con);
        // get allowed zones for product that we are duplicating
        $query          = "SELECT zone_id FROM products_zones_xref WHERE product_id = '" . escape($id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        while ($row = mysqli_fetch_assoc($result)) {
            // insert row for allowed zone for new product
            $query = "INSERT INTO products_zones_xref (product_id, zone_id) VALUES ($new_product_id, " . $row['zone_id'] . ")";
            $result_2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        }
        // get form fields for this product and duplicate them
        // form_type pinned: template rows (product_id 0 since 2026.4) must not
        // be copied onto a duplicate, and a duplicate is a standalone product
        // rather than a variant generated from somebody's template.
        $query = "SELECT * FROM form_fields WHERE (product_id = '" . escape($id) . "') AND (form_type = 'product')";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
        while ($row = mysqli_fetch_assoc($result)) {
            // insert row for field for new product
            $query = "INSERT INTO form_fields (
    
                    form_type,
    
                    product_id,
    
                    name,
    
                    label,
    
                    type,
    
                    sort_order,
    
                    required,
    
                    information,
    
                    default_value,
    
                    use_folder_name_for_default_value,
    
                    size,
    
                    maxlength,
    
                    wysiwyg,
    
                    `rows`, # Backticks for reserved word.
    
                    cols,
    
                    multiple,
    
                    spacing_above,
    
                    spacing_below,
    
                    user,
    
                    timestamp)
    
                VALUES (
    
                    '" . e($row['form_type']) . "',
    
                    '" . $new_product_id . "',
    
                    '" . escape($row['name']) . "',
    
                    '" . escape($row['label']) . "',
    
                    '" . escape($row['type']) . "',
    
                    '" . escape($row['sort_order']) . "',
    
                    '" . escape($row['required']) . "',
    
                    '" . escape($row['information']) . "',
    
                    '" . escape($row['default_value']) . "',
    
                    '" . escape($row['use_folder_name_for_default_value']) . "',
    
                    '" . escape($row['size']) . "',
    
                    '" . escape($row['maxlength']) . "',
    
                    '" . escape($row['wysiwyg']) . "',
    
                    '" . escape($row['rows']) . "',
    
                    '" . escape($row['cols']) . "',
    
                    '" . escape($row['multiple']) . "',
    
                    '" . escape($row['spacing_above']) . "',
    
                    '" . escape($row['spacing_below']) . "',
    
                    '" . USER_ID . "',
    
                    UNIX_TIMESTAMP())";
            $result_2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $new_form_field_id = mysqli_insert_id(db::$con);
            // get form field options
            $query             = "SELECT * FROM form_field_options WHERE form_field_id = '" . $row['id'] . "'";
            $result2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            $form_field_options = array();
            while ($row = mysqli_fetch_assoc($result2)) {
                $form_field_options[] = $row;
            }
            foreach ($form_field_options as $form_field_option) {
                // create form field option
                $query = "INSERT INTO form_field_options (
    
                            product_id,
    
                            form_field_id,
    
                            label,
    
                            value,
    
                            default_selected,
    
                            sort_order)
    
                         VALUES (
    
                            '" . $new_product_id . "',
    
                            '" . $new_form_field_id . "',
    
                            '" . escape($form_field_option['label']) . "',
    
                            '" . escape($form_field_option['value']) . "',
    
                            '" . $form_field_option['default_selected'] . "',
    
                            '" . $form_field_option['sort_order'] . "')";
                $result2 = mysqli_query(db::$con, $query) or output_error(lang('Query failed.'));
            }
        }
        // Get submit form fields, in order to duplicate them.
        $submit_form_fields = db_items("SELECT
    
                action,
    
                form_field_id,
    
                value
    
            FROM product_submit_form_fields
    
            WHERE product_id = '" . escape($id) . "'");
        foreach ($submit_form_fields as $submit_form_field) {
            db("INSERT INTO product_submit_form_fields (
    
                    product_id,
    
                    action,
    
                    form_field_id,
    
                    value)
    
                VALUES (
    
                    '$new_product_id',
    
                    '" . $submit_form_field['action'] . "',
    
                    '" . $submit_form_field['form_field_id'] . "',
    
                    '" . escape($submit_form_field['value']) . "')");
        }
        // Get product attributes in order to duplicate them.
        $attributes = db_items("SELECT
    
                attribute_id,
    
                option_id,
    
                sort_order
    
            FROM products_attributes_xref
    
            WHERE product_id = '" . e($id) . "'");

        foreach ($attributes as $attribute) {
            // The chosen assortment replaces the option of the attribute it
            // belongs to; every other attribute keeps the original's option.
            $attribute_option_id = $attribute['option_id'];

            if ($option && ($attribute['attribute_id'] == $option['product_attribute_id'])) {
                $attribute_option_id = $option['id'];
            }

            db("INSERT INTO products_attributes_xref (
    
                    product_id,
    
                    attribute_id,
    
                    option_id,
    
                    sort_order)
    
                VALUES (
    
                    '$new_product_id',
    
                    '" . (int) $attribute['attribute_id'] . "',
    
                    '" . (int) $attribute_option_id . "',
    
                    '" . (int) $attribute['sort_order'] . "')");
        }
    
        // Get product groups in order to duplicate them.
        $products_groups = db_items("SELECT product,product_group,sort_order,featured,featured_sort_order,new_date FROM products_groups_xref WHERE product = '" . e($id) . "'");
    
        foreach ($products_groups as $products_group) {
    
            $group_sort_order = '0';
            $group_featured  = '0';
            $group_featured_sort_order  = '0';
            $group_new_date  = '0000-00-00';
            //if there is old sort_order we prepare add to products_groups_xref.
            if($products_group['sort_order']){
                $group_sort_order = $products_group['sort_order'];
            }
            //if there is old featured we prepare add to products_groups_xref.
            if($products_group['featured']){
                $group_featured = $products_group['featured'];
            }
            //if there is old featured_sort_order we prepare add to products_groups_xref.
            if($products_group['featured_sort_order']){
                $group_featured_sort_order = $products_group['featured_sort_order'];
            }
            //if there is old new_date we prepare add to products_groups_xref.
            if($products_group['new_date']){
                $group_new_date = $products_group['new_date'];
            }
            
            db("INSERT INTO products_groups_xref (
                product,
                sort_order,
                featured,
                featured_sort_order,
                new_date,
                product_group)
    
                VALUES (
                    '$new_product_id',
                    '" . $group_sort_order . "',
                    '" . $group_featured . "',
                    '" . $group_featured_sort_order . "',
                    '" . $group_new_date . "',
                    '" . $products_group['product_group'] . "')");
        }
    
        // The copy starts a price history of its own at the original's price.
        pg_price_history_record($new_product_id, 'panel');

        pg_audit_record('create', 'products', (int) $new_product_id);
    
        return $new_product_id;
    }
  
    // The assortment is looked up once, before a single product is copied, so
    // only the id of an option that exists reaches the attribute INSERT; any
    // other value sends the user back with nothing written.
    $option = array();
    $option_id = $_POST['option_id'] ?? '';

    if ($option_id !== '') {
        if (is_string($option_id) && ctype_digit($option_id)) {
            $option = db_item(
                "SELECT id, product_attribute_id
                FROM product_attribute_options
                WHERE id = '" . e($option_id) . "'");
        }

        if (!$option) {
            $liveform->mark_error('option_id', lang('The selected assortment option could not be found, so no products were duplicated.'));
            go(PATH . SOFTWARE_DIRECTORY . '/duplicate_products.php');
        }
    }

    $products = $_POST['products'];
    $new_product_id = '';
    foreach($products as $product){
        $new_product_id = duplicate_products($product, $_POST['lettertodelete'] ?? '', $_POST['newletter'] ?? '', $option);
    }
   
    include_once('liveform.class.php');
    $liveform_edit_product = new liveform('view_products');
    $liveform_edit_product->add_notice(lang(array('string'=>'{var:1} has been duplicated.','vars'=>lang('products') )) );
    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}