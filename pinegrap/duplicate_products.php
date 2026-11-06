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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

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

// " AND (products.recycled = '0')", or '' before the upgrade that adds the bin.
$live_products_sql = pg_catalog_bin_ready() ? " AND (products.recycled = '0')" : '';
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

    // The page asked for, a whole number of 1 or more (pg_list_page_number()).
    $screen = pg_list_page_number($_REQUEST['screen'] ?? '');

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
            $heading = lang('Actions After an Order');
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
                $subheading = lang('Products that are out of stock, including those sold out by customer orders.');

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

    // A product in the Recycle Bin is neither listed nor counted, as on the
    // Products screen: copying one would put a deleted product back on sale.
    if ($live_products_sql !== '') {
        $where .= (($where == '') ? 'WHERE ' : ' AND ') . "(products.recycled = '0')";
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


    // The product export is the Products screen's (export_products_f.php),
    // so an old link to an export here gets that one.
    if (($_GET['submit_data'] ?? '') == 'Export Products') {
        go(PATH . SOFTWARE_DIRECTORY . '/view_products.php?submit_data=Export+Products');
    } else {
        // get total number of results for all screens, so that we can output links to different screens
        $query = "SELECT count(id) " .
                 "FROM products WHERE 1 = 1" . $live_products_sql;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $number_of_results = $row[0];
        $all_products = $number_of_results;

    

        /* build product filter options */

        // set all products option
        $output_filter_options = '<option value="all_products"' . $all_products_filter_selected . '>' . lang(array('string'=>'All Products') ) . ' (' . pg_format_number($all_products, 0) . ')</option>';

        // set all product actions option
        $output_filter_options .= '<option value="all_product_actions"' . $all_product_actions_filter_selected . '>' . lang('Actions After an Order') . ' (' . pg_format_number($all_products, 0) . ')</option>';

        // get the amount of shippable products
        $query = "SELECT count(id) FROM products WHERE shippable = '1'" . $live_products_sql;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set shippable product option
        $output_filter_options .= '<option value="shippable_products"' . $shippable_product_filter_selected . '>' . lang(array('string'=>'Shippable Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of recurring products
        $query = "SELECT count(id) FROM products WHERE recurring = '1'" . $live_products_sql;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set recurring product option
        $output_filter_options .= '<option value="recurring_products"' . $recurring_product_filter_selected . '>' . lang(array('string'=>'Recurring Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of donation products
        $query = "SELECT count(id) FROM products WHERE selection_type = 'donation'" . $live_products_sql;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set donation product option
        $output_filter_options .= '<option value="donation_products"' . $donation_product_filter_selected . '>' . lang(array('string'=>'Donation Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of grant access products
        $query = "SELECT count(id) FROM products WHERE grant_private_access = '1' AND (private_folder != '0' OR send_to_page != '0')" . $live_products_sql;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set grant access product option
        $output_filter_options .= '<option value="grant_access_products"' . $grant_access_product_filter_selected . '>' . lang(array('string'=>'Grant Access Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of membership products
        $query = "SELECT count(id) FROM products WHERE membership_renewal != '0'" . $live_products_sql;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set membership product option
        $output_filter_options .= '<option value="membership_products"' . $membership_product_filter_selected . '>' . lang(array('string'=>'Membership Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        // get the amount of out of stock products
        $query = "SELECT count(id) FROM products WHERE out_of_stock = '1'" . $live_products_sql;
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        // set out of stock product option
        $output_filter_options .= '<option value="out_of_stock_products"' . $out_of_stock_products_filter_selected . '>' . lang(array('string'=>'All Out of Stock Products') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';
    


        $filter_specific_columns .= '';
        $filter_specific_join .= '';

        /* Build filter specific sql joins and columns */
        if (($filter == 'all_products') || ($filter == 'out_of_stock_products')) {
            $filter_specific_columns .=
                "products.shippable,
                products.selection_type as selection_type,
                products.default_quantity as default_quantity,
                products.custom_field_1,
                products.custom_field_2,
                products.custom_field_3,
                products.custom_field_4,";
        }

        if (($filter == 'grant_access_products') || ($filter == 'membership_products') || ($filter == 'all_product_actions')) {
            $filter_specific_columns .= "products.grant_private_access as grant_private_access,";
        }

        if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {
            $filter_specific_columns .= "products.membership_renewal as membership_renewal,";
        }

        if (($filter == 'all_products') || ($filter == 'donation_products') || ($filter == 'membership_products')) {
            $filter_specific_columns .= "products.recurring as recurring,";
        }

        if ($filter == 'all_product_actions') {
            $filter_specific_columns .=
                "email_page.page_name as email_page,
                contact_groups.name as contact_group_name,
                products.order_receipt_message as order_receipt_message,
                products.order_receipt_bcc_email_address as order_receipt_bcc_email_address,
                products.email_bcc as email_bcc,";

            $filter_specific_join .= "LEFT JOIN page AS email_page ON email_page.page_id = products.email_page ";
        }

        if ($filter == 'shippable_products') {
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
        }

        if (($filter == 'recurring_products') || ($filter == 'donation_products')) {
            $filter_specific_columns .=
                "products.start as recurring_start,
                products.number_of_payments as number_of_payments,
                products.payment_period as payment_period,
                products.recurring_schedule_editable_by_customer as recurring_schedule_editable_by_customer,";
        }

        if (($filter == 'grant_access_products') || ($filter == 'membership_products') || ($filter == 'all_product_actions')) {
            $filter_specific_columns .= "page.page_name as start_page,";
            $filter_specific_join .= "LEFT JOIN page ON page.page_id = products.send_to_page ";
        }

        if (($filter == 'grant_access_products') || ($filter == 'all_product_actions')) {
            $filter_specific_columns .= "folder.folder_name as private_folder,";
            $filter_specific_join .= "LEFT JOIN folder ON folder.folder_id = products.private_folder ";
        }

        // The columns this filter shows, in the order they are shown. A key
        // is the column's sort code: one $sort_columns lacks (the zones, a
        // custom field without a label) is not sortable.
        $columns = array(
            'image' => array('heading' => lang('Image'), 'kind' => 'image'),
            'id' => array('heading' => lang('ID')),
            'short_description' => array('heading' => lang('Short Description')),
            'enabled' => array('heading' => lang('Enabled'), 'kind' => 'check'),
            'price' => array('heading' => $sort_labels['price'], 'kind' => 'money'),
        );

        if ($filter != 'out_of_stock_products') {
            $columns['inventory_quantity'] = array('heading' => lang('Inventory Quantity'), 'kind' => 'number');
        }

        if ((ECOMMERCE_TAX == true) && ($filter != 'all_product_actions')) {
            $columns['taxable'] = array('heading' => lang('Taxable'), 'kind' => 'check');
        }

        if ($filter != 'all_product_actions') {
            $columns['product_form'] = array('heading' => lang('Product Form'));
        }

        if (($filter == 'all_products') || ($filter == 'out_of_stock_products')) {
            $columns['selection_type'] = array('heading' => lang('Selection Type'));
            $columns['default_quantity'] = array('heading' => lang('Default Quantity'), 'kind' => 'number');
            $columns['shippable'] = array('heading' => lang('Shippable'), 'kind' => 'check');

            foreach (array(1, 2, 3, 4) as $number) {
                if ($sort_labels['custom_field_' . $number] != '') {
                    $columns['custom_field_' . $number] = array('heading' => $sort_labels['custom_field_' . $number]);
                }
            }
        }

        if ($filter == 'shippable_products') {
            $columns['weight'] = array('heading' => lang('Weight'), 'kind' => 'number');
            $columns['pwp'] = array('heading' => lang('PWP'), 'kind' => 'number');
            $columns['swp'] = array('heading' => lang('SWP'), 'kind' => 'number');
            $columns['dim'] = array('heading' => lang('Dim'));
            $columns['cont_req'] = array('heading' => lang('Cont Req'), 'kind' => 'check');
            $columns['prep'] = array('heading' => lang('Prep'), 'kind' => 'number');
            $columns['free_ship'] = array('heading' => lang('Free Ship'), 'kind' => 'check');
            $columns['extra_ship'] = array('heading' => lang('Extra Ship'), 'kind' => 'money');
            $columns['allowed_zones'] = array('heading' => lang('Allowed Zones'));
            $columns['disallowed_zones'] = array('heading' => lang('Disallowed Zones'));
        }

        if (($filter == 'all_products') || ($filter == 'donation_products') || ($filter == 'membership_products')) {
            $columns['recurring_payment'] = array('heading' => lang('Recurring Payment'), 'kind' => 'check');
        }

        if (($filter == 'recurring_products') || ($filter == 'donation_products')) {
            $columns['start'] = array('heading' => lang('Start'));
            $columns['number_of_payments'] = array('heading' => lang('Number of Payments'));
            $columns['payment_period'] = array('heading' => lang('Payment Period'));
        }

        if ($filter == 'donation_products') {
            $columns['allow_to_schedule'] = array('heading' => lang('Allow to Schedule'), 'kind' => 'check');
        }

        if (($filter == 'grant_access_products') || ($filter == 'membership_products') || ($filter == 'all_product_actions')) {
            $columns['set_start_page'] = array('heading' => lang('Set Start Page'));
        }

        if (($filter == 'grant_access_products') || ($filter == 'all_product_actions')) {
            $columns['grant_private_access'] = array('heading' => lang('Grant Private Access'));
        }

        if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {
            $columns['membership_renewal'] = array('heading' => lang('Membership Renewal'));
        }

        if ($filter == 'all_product_actions') {
            $columns['order_receipt_message'] = array('heading' => lang('Order Receipt Message'), 'kind' => 'check');
            $columns['order_receipt_bcc'] = array('heading' => lang('Order Receipt BCC'));
            $columns['email_page'] = array('heading' => lang('E-mail Page'));
            $columns['email_page_bcc'] = array('heading' => lang('E-mail Page BCC'));
            $columns['contact_group'] = array('heading' => lang('Contact Group'));
        }

        $columns['last_modified'] = array('heading' => lang('Last Modified'), 'kind' => 'date');

        if ($filter == 'out_of_stock_products') {
            $columns['out_of_stock_date'] = array('heading' => lang('Out of Stock Date'), 'kind' => 'date');
        }

        foreach ($columns as $key => $spec) {
            if (!isset($sort_columns[$key])) {
                $columns[$key]['sortable'] = false;
            }
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

        // Every product of the filter is printed: the list table driver sorts
        // and searches them in the browser (browser mode), keeps a heading's
        // sort, and posts the selection as products[].
        $list_rows = array();
        $selection_types = array(
            'checkbox' => lang('Checkbox'),
            'quantity' => lang('Quantity'),
            'donation' => lang('Donation'),
            'autoselect' => lang('Auto-Select'),
        );

        while ($row = mysqli_fetch_assoc($result)) {
            $product_id = $row['id'];

            // An enabled product's name and short description are green, a
            // disabled one's red.
            $color_class = ($row['enabled'] == 1) ? 'fg-success' : 'fg-danger';

            // Untracked inventory shows a dash; a tracked one is green from
            // 10 up, amber below and red at 0.
            if ($row['inventory'] == 1) {
                $quantity = (int) $row['inventory_quantity'];
                $inventory_class = ($quantity == 0) ? 'fg-danger' : (($quantity >= 10) ? 'fg-success' : 'fg-warning');
                $inventory_cell = pg_list_cell((string) $quantity, 'align-middle h5 fw-bolder ' . $inventory_class, array('data-pg-sort-value' => $quantity));
            } else {
                $inventory_cell = pg_list_cell('<span title="' . h(lang('Inventory tracking is not active for this product/service')) . '">-</span>', 'align-middle h5 fw-bolder fg-success', array('data-pg-sort-value' => -1));
            }

            $cells = array(
                'image' => pg_list_cell(pg_product_image_cell_content('product-' . (int) $product_id, $row['image_name'], $row['name'], $row['short_description']), 'align-middle text-start'),
                'id' => pg_list_cell(h($row['name']), 'align-middle ' . $color_class),
                'short_description' => pg_list_cell(h($row['short_description']), 'align-middle ' . $color_class),
                'enabled' => pg_list_table_check_cell($row['enabled'] == 1),
                'price' => pg_list_cell(prepare_amount($row['price'] / 100), 'align-middle text-end', array('data-pg-sort-value' => (int) $row['price'])),
                'inventory_quantity' => $inventory_cell,
                'taxable' => pg_list_table_check_cell($row['taxable'] == 1),
                'product_form' => pg_list_cell(h($row['form_name'])),
                'selection_type' => pg_list_cell($selection_types[$row['selection_type'] ?? ''] ?? ''),
                'default_quantity' => pg_list_cell(h($row['default_quantity'] ?? '')),
                'shippable' => pg_list_table_check_cell(($row['shippable'] ?? '') == 1),
                'custom_field_1' => pg_list_cell(h($row['custom_field_1'] ?? '')),
                'custom_field_2' => pg_list_cell(h($row['custom_field_2'] ?? '')),
                'custom_field_3' => pg_list_cell(h($row['custom_field_3'] ?? '')),
                'custom_field_4' => pg_list_cell(h($row['custom_field_4'] ?? '')),
                'recurring_payment' => pg_list_table_check_cell(($row['recurring'] ?? '') == 1),
                'allow_to_schedule' => pg_list_table_check_cell(($row['recurring_schedule_editable_by_customer'] ?? '') == 1),
                // A start page and a private folder count only while the
                // product grants private access.
                'set_start_page' => pg_list_cell((($row['grant_private_access'] ?? '') == '1') ? h($row['start_page'] ?? '') : ''),
                'grant_private_access' => pg_list_cell((($row['grant_private_access'] ?? '') == '1') ? h($row['private_folder'] ?? '') : ''),
                'order_receipt_message' => pg_list_table_check_cell(($row['order_receipt_message'] ?? '') != ''),
                'order_receipt_bcc' => pg_list_cell(h($row['order_receipt_bcc_email_address'] ?? '')),
                'email_page' => pg_list_cell(h($row['email_page'] ?? '')),
                'email_page_bcc' => pg_list_cell(h($row['email_bcc'] ?? '')),
                'contact_group' => pg_list_cell(h($row['contact_group_name'] ?? '')),
                'last_modified' => pg_list_table_when_cell($row['timestamp'], (string) ($row['user'] ?? '')),
                'out_of_stock_date' => pg_list_cell(get_relative_time(array('timestamp' => $row['out_of_stock_timestamp'])), 'align-middle', array('data-pg-sort-value' => (int) $row['out_of_stock_timestamp'])),
            );

            if (($filter == 'recurring_products') || ($filter == 'donation_products')) {
                $recurring_start = (int) ($row['recurring_start'] ?? 0);
                $number_of_payments = (int) ($row['number_of_payments'] ?? 0);

                $cells['start'] = pg_list_cell(h(($recurring_start != 0) ? $recurring_start . (($recurring_start == 1) ? ' Day' : ' Days') : 'Immediately'), 'align-middle', array('data-pg-sort-value' => $recurring_start));
                $cells['number_of_payments'] = pg_list_cell(h(($number_of_payments != 0) ? (string) $number_of_payments : 'Unlimited'), 'align-middle text-center', array('data-pg-sort-value' => $number_of_payments));
                $cells['payment_period'] = pg_list_cell(h($row['payment_period'] ?? ''));
            }

            if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {
                $membership_renewal = (int) ($row['membership_renewal'] ?? 0);

                $cells['membership_renewal'] = pg_list_cell(h(($membership_renewal != 0) ? $membership_renewal . (($membership_renewal == 1) ? ' Day' : ' Days') : ''), 'align-middle', array('data-pg-sort-value' => $membership_renewal));
            }

            if ($filter == 'shippable_products') {
                $dimensions = '';

                if (($row['length'] > 0) or ($row['width'] > 0) or ($row['height'] > 0)) {
                    $dimensions = ($row['length']+0) . '&Prime; x ' . ($row['width']+0) . '&Prime; x ' . ($row['height']+0) . '&Prime;';
                }

                $output_allowed_zones = '';
                $output_disallowed_zones = '';
                $disallowed_zones_sql_where = '';
                $disallowed_zones_values = array();

                // Get allowed zones
                $query2 = "SELECT 
                             zones.id,
                             zones.name 
                          FROM zones 
                          LEFT JOIN products_zones_xref ON products_zones_xref.zone_id = zones.id 
                          WHERE products_zones_xref.product_id = ?
                          ORDER BY zones.name ASC";
                $result2 = db_q($query2, array((string) $product_id)) or output_error('Query failed.');

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
                    $disallowed_zones_sql_where .= "id != ?";
                    $disallowed_zones_values[] = (string) $row2['id'];
                }

                // Get disallowed zones
                $query2 = "SELECT name FROM zones
                          $disallowed_zones_sql_where
                          ORDER BY zones.name ASC";
                $result2 = db_q($query2, $disallowed_zones_values) or output_error('Query failed.');

                // loop through and prepare disallowed zones
                while($row2 = mysqli_fetch_assoc($result2)){

                    if ($output_disallowed_zones) {
                        $output_disallowed_zones .= ',<br />';
                    }

                    // output disallowed zones
                    $output_disallowed_zones .= h($row2['name']);
                }

                $cells['weight'] = pg_list_cell(($row['weight'] > 0) ? ($row['weight']+0) . ' lb' : '', 'align-middle text-center', array('data-pg-sort-value' => $row['weight']+0));
                $cells['pwp'] = pg_list_cell(h($row['primary_weight_points']), 'align-middle text-center');
                $cells['swp'] = pg_list_cell(h($row['secondary_weight_points']), 'align-middle text-center');
                $cells['dim'] = pg_list_cell($dimensions, 'align-middle', array('data-pg-sort-value' => $row['length']+0));
                $cells['cont_req'] = pg_list_table_check_cell((bool) $row['container_required']);
                $cells['prep'] = pg_list_cell(h($row['preparation_time']), 'align-middle text-center');
                $cells['free_ship'] = pg_list_table_check_cell($row['free_shipping'] == 1);
                $cells['extra_ship'] = pg_list_cell(pg_format_money($row['extra_shipping_cost'] / 100, BASE_CURRENCY_SYMBOL), 'align-middle text-end', array('data-pg-sort-value' => (int) $row['extra_shipping_cost']));
                $cells['allowed_zones'] = pg_list_cell($output_allowed_zones);
                $cells['disallowed_zones'] = pg_list_cell($output_disallowed_zones);
            }

            $list_rows[] = array('id' => (int) $product_id, 'cells' => array_intersect_key($cells, $columns));
        }

        $list = pg_list_table_printed(array(
            'list' => 'duplicate_products',
            'columns' => $columns,
            'levels' => $sort_levels,
            'rows' => $list_rows,
            'ids_field' => 'products[]',
        ));

        // The site's own attribute options, one group per attribute: the
        // chosen option replaces the copied product's option of that
        // attribute. No field at all on a site without attribute options.
        $output_option_field = '';
        $attribute_options = db_items(
            "SELECT
                product_attribute_options.id,
                product_attribute_options.label,
                product_attribute_options.product_attribute_id,
                product_attributes.name AS attribute_name,
                product_attributes.label AS attribute_label
            FROM product_attribute_options
            INNER JOIN product_attributes ON product_attributes.id = product_attribute_options.product_attribute_id
            ORDER BY product_attributes.name, product_attributes.id, product_attribute_options.sort_order, product_attribute_options.id");

        if ($attribute_options) {
            $output_option_groups = '';
            $attribute_id = null;

            foreach ($attribute_options as $attribute_option) {
                if ($attribute_option['product_attribute_id'] !== $attribute_id) {
                    $output_option_groups .= (($attribute_id !== null) ? '</optgroup>' : '')
                        . '<optgroup label="' . h(($attribute_option['attribute_label'] !== '') ? $attribute_option['attribute_label'] : $attribute_option['attribute_name']) . '">';
                    $attribute_id = $attribute_option['product_attribute_id'];
                }

                $output_option_groups .= '<option value="' . (int) $attribute_option['id'] . '">' . h($attribute_option['label']) . '</option>';
            }

            $output_option_field = '
                                    <div class="col">
                                        <label class="form-label" for="option_id">' . h(lang('Product Attribute')) . '</label>
                                        <select class="form-control" id="option_id" name="option_id">
                                            <option value="">' . h(lang('Do not change')) . '</option>
                                            ' . $output_option_groups . '</optgroup>
                                        </select>
                                    </div>';
        }

        $output = '
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->get_warnings() . '
                    ' . $liveform->output_notices() . '
                    ' . pg_screen_toolbar(array(
                        'search' => array(pg_list_table_search_box('duplicate_products'), pg_list_sort_menu('duplicate_products.php', $sort_columns, $sort_levels, $sort_default)),
                    )) . '
                    <form name="form" action="duplicate_products.php" method="post">
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
                        <div class="row g-5 align-items-end mb-12">
                            <div class="col-12">
                                <p class="mb-0">' . h(lang('The new letter replaces the 1 character removed from the ID and Short Description fields.')) . '</p>
                            </div>
                            <div class="col-auto">
                                <label class="form-label" for="lettertodelete">' . h(lang('Number of Letters to Remove')) . '</label>
                                <input type="number" class="form-control" id="lettertodelete" name="lettertodelete" value="1" min="1" aria-describedby="lettertodelete_help" />
                                <span class="form-text" id="lettertodelete_help">' . h(lang('Add +1 for each space as well')) . '</span>
                            </div>
                            <div class="col">
                                <label class="form-label" for="newletter">' . h(lang('Word or Letter to Add')) . '</label>
                                <input type="text" class="form-control" id="newletter" name="newletter" />
                            </div>
                            ' . $output_option_field . '
                        </div>
                        ' . pg_list_table_html($list['source'], $list['page'], $heading, '<button type="submit" value="Duplicate Selected" class="btn btn-sm btn-solid theme-secondary"><i class="bi bi-copy me-1" aria-hidden="true"></i>' . h(lang('Duplicate Selected')) . '</button>') . '
                    </form>
                </div>
            </div>
        ';

        print pg_page_shell([
        'title'=> $heading,
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>$heading,
        'heading_description' => ($subheading ?? '')
    ]) . '<main id="content" class="container-fluid">' . $output . _pg_lightbox_assets_once() . '</main>' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . output_footer();
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
        $result = db_q("SELECT * FROM products WHERE id = ?", array((string) $id)) or output_error(lang('Query failed'));
        $row                   = mysqli_fetch_assoc($result);
        $original_product_name = $row['name'];
        // The original's code with its last letter swapped, made unique as it
        // is: cutting the "[1]" off the original's unique name gave a code a
        // second run, or another original ending the same, already held.
        $product_name          = get_unique_name(array(
            'name' => pg_duplicate_products_base_name($row['name'], $newletter),
            'type' => 'product'
        ));
        // An emptied field or a value that is not a count of at least one falls
        // back to the field's default; negating a non-numeric string below
        // would end the request with a TypeError on PHP 8.
        if (!is_numeric($lettertodelete) || ($lettertodelete < 1)) {
            $lettertodelete = 1;
        }

        $lettertodelete = (int) $lettertodelete;

        // Letters, not bytes: a Turkish description keeps whole letters.
        $short_description = pg_duplicate_products_description($row['short_description'], $lettertodelete, $newletter);
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

        // products.hs_code and origin_country (2026.11.0) the same way.
        $customs = pg_product_customs_copy_sql($row);

        // products.ai_fields, vat_exemption_code and the bundle columns the
        // same way (pg_product_copy_extra_sql(), shared with
        // duplicate_product()): the copy keeps the marks of the texts it
        // writes unchanged; the short description, whose last letters are
        // replaced, keeps its mark only if it is still the same.
        $extra = pg_product_copy_extra_sql($row, pg_ai_fields_copy('product', $row, array(
            'short_description' => $short_description,
            'full_description'  => $row['full_description'],
            'title'             => $row['title'],
            'meta_description'  => $row['meta_description'],
        )));
        $is_bundle = !empty($row['bundle']);

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
    
                " . $customs['columns'] . "
    
                " . $extra['columns'] . "
    
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

                " . $customs['values'] . "
    
                " . $extra['values'] . "
    
                '" . USER_ID . "',
    
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error(lang('Query failed'));
        $new_product_id = mysqli_insert_id(db::$con);
        // get allowed zones for product that we are duplicating
        $query          = "SELECT zone_id FROM products_zones_xref WHERE product_id = ?";
        $result = db_q($query, array((string) $id)) or output_error(lang('Query failed.'));
        while ($row = mysqli_fetch_assoc($result)) {
            // insert row for allowed zone for new product
            $query = "INSERT INTO products_zones_xref (product_id, zone_id) VALUES (?, ?)";
            $result_2 = db_q($query, array((int) $new_product_id, (int) $row['zone_id'])) or output_error(lang('Query failed.'));
        }
        // get form fields for this product and duplicate them
        // form_type pinned: template rows (product_id 0 since 2026.4) must not
        // be copied onto a duplicate, and a duplicate is a standalone product
        // rather than a variant generated from somebody's template.
        $query = "SELECT * FROM form_fields WHERE (product_id = ?) AND (form_type = 'product')";
        $result = db_q($query, array((string) $id)) or output_error(lang('Query failed.'));
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
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    ?,
    
                    UNIX_TIMESTAMP())";
            $result_2 = db_q($query, array((string) $row['form_type'], (string) $new_product_id, (string) $row['name'], (string) $row['label'], (string) $row['type'], (string) $row['sort_order'], (string) $row['required'], (string) $row['information'], (string) $row['default_value'], (string) $row['use_folder_name_for_default_value'], (string) $row['size'], (string) $row['maxlength'], (string) $row['wysiwyg'], (string) $row['rows'], (string) $row['cols'], (string) $row['multiple'], (string) $row['spacing_above'], (string) $row['spacing_below'], (string) USER_ID)) or output_error(lang('Query failed.'));
            $new_form_field_id = mysqli_insert_id(db::$con);
            // get form field options
            $query             = "SELECT * FROM form_field_options WHERE form_field_id = ?";
            $result2 = db_q($query, array((string) $row['id'])) or output_error(lang('Query failed.'));
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
    
                            ?,
    
                            ?,
    
                            ?,
    
                            ?,
    
                            ?,
    
                            ?)";
                $result2 = db_q($query, array((string) $new_product_id, (string) $new_form_field_id, (string) $form_field_option['label'], (string) $form_field_option['value'], (string) $form_field_option['default_selected'], (string) $form_field_option['sort_order'])) or output_error(lang('Query failed.'));
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
    
        // The gallery pictures beside the cover, in the order they were
        // written: the table has no order column and is read back in that order.
        $images = db_values("SELECT file_name FROM products_images_xref WHERE product = '" . (int) $id . "' AND file_name != ''");

        foreach ($images as $image) {
            db_q("INSERT INTO products_images_xref (product, file_name) VALUES (?, ?)", array((int) $new_product_id, (string) $image));
        }

        // The copy starts a price history of its own at the original's price.
        pg_price_history_record($new_product_id, 'panel');

        // A bundle copy takes the same contents, then its stock and price are
        // derived from them like any bundle's instead of kept from the copied row.
        if ($is_bundle && pg_bundles_ready()) {
            pg_bundle_items_save($new_product_id, pg_bundle_items($id));
            pg_bundle_refresh($new_product_id);
        }

        pg_audit_record('create', 'products', (int) $new_product_id);
    
        return $new_product_id;
    }

    // The ticked products; a submit with none ticked goes back with an error
    // instead of reporting a copy that was never made.
    $products = (isset($_POST['products']) && is_array($_POST['products'])) ? $_POST['products'] : array();

    if (!$products) {
        $liveform->mark_error('products', lang('Please select at least one product.'));
        go(PATH . SOFTWARE_DIRECTORY . '/duplicate_products.php');
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

    $number_of_copies = 0;
    $number_of_binned_products = 0;

    foreach ($products as $product) {
        // Only an existing product is copied. One in the Recycle Bin is not:
        // the list does not show it, but a selection made before it was
        // binned in another tab still posts it, and its copy would be live.
        if (!is_scalar($product) || ((int) $product <= 0) || !db_value("SELECT id FROM products WHERE id = '" . (int) $product . "'")) {
            continue;
        }

        if (pg_catalog_product_binned((int) $product)) {
            $number_of_binned_products++;
            continue;
        }

        duplicate_products((int) $product, $_POST['lettertodelete'] ?? '', $_POST['newletter'] ?? '', $option);
        $number_of_copies++;
    }

    if ($number_of_copies > 0) {
        $liveform->add_notice(lang(array('string' => '{var:1} product(s) were duplicated.', 'vars' => array($number_of_copies))));
    }

    if ($number_of_binned_products > 0) {
        $liveform->mark_error('products', lang(array('string' => '{var:1} product(s) were not duplicated because they are in the Recycle Bin.', 'vars' => array($number_of_binned_products))));
    }

    // Nothing copied: back to the selection, where the error shows.
    if ($number_of_copies == 0) {
        if ($number_of_binned_products == 0) {
            $liveform->mark_error('products', lang('Please select at least one product.'));
        }

        go(PATH . SOFTWARE_DIRECTORY . '/duplicate_products.php');
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_products.php');
}