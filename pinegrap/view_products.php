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
require_once(dirname(__FILE__) . '/seo.php');

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
$seo_weak_products_filter_selected = '';
$seo_struct_error_products_filter_selected = '';
$output_shipping_headers = '';
$output_recurring_option_headers = '';
$output_recurring_set_schedule_header = '';
$output_start_page_header = '';
$output_private_folder_access_headers = '';
$output_add_membership_header = '';
$output_all_product_actions_headers = '';
$output_out_of_stock_timestamp_header = '';
$output_recurring_header = '';
$output_inventory_headers = '';
$output_tax_header = '';
$output_product_form_header = '';
$output_all_products_headers = '';
$output_price_header = '';
$liveform = new liveform('view_products');
$user = validate_user();
validate_ecommerce_access($user);

include_once('product_builder.php');

// Two views of the same catalog: every product, or the variant sets that group
// them. Remembered per session like the filter below it, so returning from an
// edit lands on the list the operator was working in rather than resetting to
// the other one.
//
// Handled before anything else on this screen runs: the product query, the
// filters and the export all belong to the product view and none of it is
// needed to draw the other one.
if (isset($_GET['mode'])) {
    $_SESSION['software']['view_products']['mode'] = (($_GET['mode'] ?? '') === 'variant_sets') ? 'variant_sets' : 'products';
}

if (isset($_SESSION['software']['view_products']['mode'])
    && (($_SESSION['software']['view_products']['mode'] ?? '') === 'variant_sets')) {
    pg_pb_variant_sets_screen();
    exit();
}
//   $liveform->mark_error("error yitle","error descrition");
//   $liveform->add_warning("warning title");
//   $liveform->add_notice("notice title");


// If there is a filter then store it in a session
if (isset($_GET['filter']) && $_GET['filter']) {
    $_SESSION['software']['view_products']['filter'] = $_GET['filter'];
}

// if filter session is blank then set it to the default all products
if ((isset($_SESSION['software']['view_products']['filter']) == false) || (($_SESSION['software']['view_products']['filter'] ?? '') == '')) {
    $_SESSION['software']['view_products']['filter'] = 'all_products';
}

$filter = ($_SESSION['software']['view_products']['filter'] ?? '');

// The full product list's sort columns, also used when an SEO filter
// falls back to it below.
$sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'recurring_payment', 'seo', 'impact', 'inventory_quantity', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4');
$sort_default = 'last_modified';

// Set where statement and page headers for the filter
switch ($filter) {
    case 'all_product_actions':
        // The columns this filter can be sorted by; pg_list_state() below
        // falls back to the default for any other.
        $sort_codes = array('id', 'short_description', 'enabled', 'price', 'set_start_page', 'grant_private_access', 'membership_renewal', 'order_receipt_message', 'order_receipt_bcc', 'email_page', 'email_page_bcc', 'contact_group', 'seo', 'impact', 'inventory_quantity', 'last_modified');
        
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
        $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'weight', 'pwp', 'swp', 'dim', 'cont_req', 'prep', 'free_ship', 'extra_ship', 'seo', 'impact', 'inventory_quantity', 'last_modified');
        
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
        $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'start', 'number_of_payments', 'payment_period', 'seo', 'impact', 'inventory_quantity', 'last_modified');
        
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
        $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'recurring_payment', 'start', 'number_of_payments', 'payment_period', 'allow_to_schedule', 'seo', 'impact', 'inventory_quantity', 'last_modified');
        
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
        $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'set_start_page', 'grant_private_access', 'seo', 'impact', 'inventory_quantity', 'last_modified');
        
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
        $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'recurring_payment', 'set_start_page', 'membership_renewal', 'seo', 'impact', 'inventory_quantity', 'last_modified');
        
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

        // The two SEO filters carry the same columns as the full product
        // list and add a condition, so every column gate below that names
        // all_products names them too. Anything else would leave the header
        // row and the body row disagreeing about how many cells there are.
        case 'seo_weak_products':

            // The columns this filter reads only exist after the
            // upgrade. $filter comes from the query string and is
            // stored in the session, so an unguarded value here
            // would kill the screen on every later visit too.
            if (!pg_seo_schema_ready()) {
                $filter = 'all_products';
                $_SESSION['software']['view_products']['filter'] = $filter;
                $heading = lang(array('string'=>'All Products') );
                $subheading = lang(array('string'=>'Merchandise, downloads, donations, recurring fees, memberships, and simple payments.') );
                break;
            }

            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'recurring_payment', 'seo', 'impact', 'inventory_quantity', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4');

            // If where is blank
            if ($where == '') {
                $where .= "WHERE ";

            // else where is not blank, so add and
            } else {
                $where .= "AND ";
            }

            // Uncalculated rows are excluded: their stored 0 means unknown.
            $where .= "(products.seo_score < 55) AND (products.seo_analysis_current = 1)";

            // Change the heading and subheading.
            $heading = lang(array('string'=>'Products With a Weak SEO Score') );
            $subheading = lang(array('string'=>'Products scoring under 55 out of 100 on title, description, keywords and content.') );

            // select the filter option
            $seo_weak_products_filter_selected = ' selected="selected"';
            break;

        case 'seo_struct_error_products':

            // The columns this filter reads only exist after the
            // upgrade. $filter comes from the query string and is
            // stored in the session, so an unguarded value here
            // would kill the screen on every later visit too.
            if (!pg_seo_structure_schema_ready()) {
                $filter = 'all_products';
                $_SESSION['software']['view_products']['filter'] = $filter;
                $heading = lang(array('string'=>'All Products') );
                $subheading = lang(array('string'=>'Merchandise, downloads, donations, recurring fees, memberships, and simple payments.') );
                break;
            }

            // The columns this filter can be sorted by; pg_list_state() below
            // falls back to the default for any other.
            $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'recurring_payment', 'seo', 'impact', 'inventory_quantity', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4');

            // If where is blank
            if ($where == '') {
                $where .= "WHERE ";

            // else where is not blank, so add and
            } else {
                $where .= "AND ";
            }

            // Bit 32768 marks at least one error-level structure finding.
            $where .= "((products.seo_flags & 32768) != 0)";

            // Change the heading and subheading.
            $heading = lang(array('string'=>'Products With HTML Structure Errors') );
            $subheading = lang(array('string'=>'Products whose description markup has a serious fault, such as invalid nesting or images with no alt text.') );

            // select the filter option
            $seo_struct_error_products_filter_selected = ' selected="selected"';
            break;
    case 'all_products':
    default:
        
        // The columns this filter can be sorted by; pg_list_state() below
        // falls back to the default for any other.
        $sort_codes = array('id', 'short_description', 'enabled', 'price', 'taxable', 'product_form', 'selection_type', 'default_quantity', 'shippable', 'recurring_payment', 'seo', 'impact', 'inventory_quantity', 'last_modified', 'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4');
        
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
    'seo'                   => lang('SEO'),
    'impact'                => lang('Impact'),
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

// A custom field with no label has no column, so it cannot be sorted by.
$sort_columns = array();

foreach ($sort_codes as $code) {
    if ($sort_labels[$code] != '') {
        $sort_columns[$code] = $sort_labels[$code];
    }
}

$list_state = pg_list_state('view_products', $sort_columns, array('sort' => $sort_default, 'order' => 'desc'));

$sort = $list_state['sort'];
$asc_desc = $list_state['order'];
$screen = $list_state['screen'];

// duplicate_products.php still keeps the sort as a heading under the old key,
// and picks up the one chosen here from it.
$_SESSION['software']['ecommerce']['view_products']['sort'] = $sort_columns[$sort];
$_SESSION['software']['ecommerce']['view_products']['order'] = $asc_desc;

$sort_column = array(
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
    'seo'                   => 'products.seo_score',
    'impact'                => 'seo_impact',
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
)[$sort];


// if user requested to export products, then export them
if (($_GET['submit_data'] ?? '') == 'Export Products') {

    // The whole of this used to be written out here: ninety columns of
    // headings, then the same ninety again as values, and the two had to be
    // kept in step by hand. It now lives in export_products_f.php, which is
    // also what the File Manager and the spreadsheet writer call -- so a
    // column added once is added everywhere, and a workbook and a CSV of the
    // same products cannot disagree.
    require_once(dirname(__FILE__) . '/export_products_f.php');

    $export_table = pg_products_export_table(
        $where,
        (($sort_column == 'seo_impact') ? 'products.timestamp' : $sort_column) . ' ' . $asc_desc);

    pg_products_export_csv($export_table);

    // if at least 1 product was exported, then log activity
    if (count($export_table['rows']) > 0) {
        // if only 1 product was exported, then prepare message phrasing in a certain way
        if (count($export_table['rows']) == 1) {
            $plural_suffix = '';
            $was_or_were = 'was';

        // else more than 1 product was exported, so prepare message phrasing in a different way
        } else {
            $plural_suffix = 's';
            $was_or_were = 'were';
        }

        // add log message about products being exported
        log_activity(count($export_table['rows']) . ' product' . $plural_suffix . ' ' . $was_or_were . ' exported', $_SESSION['sessionusername']);
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

    // SEO filters. Only offered once the score columns exist, and the
    // structure one only once the analysis half that fills its bit does.
    if (pg_seo_schema_ready()) {

        $result = mysqli_query(db::$con, "SELECT count(id) FROM products WHERE (seo_score < 55) AND (seo_analysis_current = 1)") or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        $output_filter_options .= '<option value="seo_weak_products"' . $seo_weak_products_filter_selected . '>' . lang(array('string'=>'Products With a Weak SEO Score') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';

        if (pg_seo_structure_schema_ready()) {

            $result = mysqli_query(db::$con, "SELECT count(id) FROM products WHERE (seo_flags & 32768) != 0") or output_error('Query failed.');
            $row = mysqli_fetch_row($result);

            $output_filter_options .= '<option value="seo_struct_error_products"' . $seo_struct_error_products_filter_selected . '>' . lang(array('string'=>'Products With HTML Structure Errors') ) . ' (' . pg_format_number($row[0], 0) . ')</option>';
        }
    }
   


    $filter_specific_columns .= '';
    $filter_specific_join .= '';

    /* Build filter specific table headers, sql joins and columns */
    if ((ECOMMERCE_TAX == true) && ($filter != 'all_product_actions')) {
        $output_tax_header =
            '<th>' . get_column_heading(lang(array('string'=>'Taxable') ), $sort, $asc_desc, '', 'taxable') . '</th>';
    }

    if (($filter == 'all_products') || ($filter == 'out_of_stock_products') || ($filter == 'seo_weak_products') || ($filter == 'seo_struct_error_products')) {
        
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
            $output_custom_field_1_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL, $sort, $asc_desc, '', 'custom_field_1') . '</th>';
        }

        $output_custom_field_2_heading = '';

        // If the second custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
            $output_custom_field_2_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL, $sort, $asc_desc, '', 'custom_field_2') . '</th>';
        }

        $output_custom_field_3_heading = '';

        // If the third custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
            $output_custom_field_3_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL, $sort, $asc_desc, '', 'custom_field_3') . '</th>';
        }

        $output_custom_field_4_heading = '';

        // If the fourth custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
            $output_custom_field_4_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL, $sort, $asc_desc, '', 'custom_field_4') . '</th>';
        }
        
        $output_all_products_headers = 
            '<th>' . get_column_heading(lang(array('string'=>'Selection Type') ), $sort, $asc_desc, '', 'selection_type') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Default Quantity') ), $sort, $asc_desc, '', 'default_quantity') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Shippable') ), $sort, $asc_desc, '', 'shippable') . '</th>
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
        ($filter == 'membership_products') ||
        ($filter == 'seo_weak_products') ||
        ($filter == 'seo_struct_error_products')) {
        
        // Set filter specific sql columns
        $filter_specific_columns .= 
        "products.recurring as recurring,";
        
        $output_recurring_header = '<th>' . get_column_heading(lang(array('string'=>'Recurring Payment') ), $sort, $asc_desc, '', 'recurring_payment') . '</th>';   
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
            '<th>' . get_column_heading(lang(array('string'=>'Order Receipt Message') ), $sort, $asc_desc, '', 'order_receipt_message') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Order Receipt BCC') ), $sort, $asc_desc, '', 'order_receipt_bcc') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'E-mail Page') ), $sort, $asc_desc, '', 'email_page') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'E-mail Page BCC') ), $sort, $asc_desc, '', 'email_page_bcc') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Contact Group') ), $sort, $asc_desc, '', 'contact_group') . '</th>';
        
    // else output product form header
    } else {
        $output_product_form_header = '<th>' . get_column_heading(lang(array('string'=>'Product Form') ), $sort, $asc_desc, '', 'product_form') . '</th>';
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
            '<th>' . get_column_heading(lang(array('string'=>'Weight') ), $sort, $asc_desc, '', 'weight') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'PWP') ), $sort, $asc_desc, '', 'pwp') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'SWP') ), $sort, $asc_desc, '', 'swp') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Dim') ), $sort, $asc_desc, '', 'dim') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Cont Req') ), $sort, $asc_desc, '', 'cont_req') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Prep') ), $sort, $asc_desc, '', 'prep') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Free Ship') ), $sort, $asc_desc, '', 'free_ship') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Extra Ship') ), $sort, $asc_desc, '', 'extra_ship') . '</th>
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
            '<th>' . get_column_heading(lang(array('string'=>'Start') ), $sort, $asc_desc, '', 'start') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Number of Payments') ), $sort, $asc_desc, '', 'number_of_payments') . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Payment Period') ), $sort, $asc_desc, '', 'payment_period') . '</th>';
    }

    if ($filter == 'donation_products') {
        
        // Output dontaine price header
        $output_price_header = '<th>' . get_column_heading(lang(array('string'=>'Default Amount (Price)') ), $sort, $asc_desc, '', 'price') . '</th>';
        
        $output_recurring_set_schedule_header = '<th>' . get_column_heading(lang(array('string'=>'Allow to Schedule') ), $sort, $asc_desc, '', 'allow_to_schedule') . '</th>';

    // else output the default price header
    } else {
        $output_price_header = '<th>' . get_column_heading(lang(array('string'=>'Price') ), $sort, $asc_desc, '', 'price') . '</th>';
    }

    if (($filter == 'grant_access_products') || 
        ($filter == 'membership_products') ||
        ($filter == 'all_product_actions')) {

        // Set filter specific sql columns
        $filter_specific_columns .= "page.page_name as start_page,";
        
        // Set filter specific sql joins
        $filter_specific_join .= "LEFT JOIN page ON page.page_id = products.send_to_page ";

        $output_start_page_header = '<th>' . get_column_heading(lang(array('string'=>'Set Start Page') ), $sort, $asc_desc, '', 'set_start_page') . '</th>';
    }

    if (($filter == 'grant_access_products') || ($filter == 'all_product_actions')) {
        
        // Set filter specific sql columns
        $filter_specific_columns .= "folder.folder_name as private_folder,";

        // Set filter specific sql joins
        $filter_specific_join .= "LEFT JOIN folder ON folder.folder_id = products.private_folder ";
        
        $output_private_folder_access_headers = '<th>' . get_column_heading(lang(array('string'=>'Grant Private Access') ), $sort, $asc_desc, '', 'grant_private_access') . '</th>';
    }

    if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {
        
        $output_add_membership_header = '<th>' . get_column_heading(lang(array('string'=>'Membership Renewal') ), $sort, $asc_desc, '', 'membership_renewal') . '</th>';
    }
    if ($filter == 'out_of_stock_products') {
        
        $output_out_of_stock_timestamp_header = '<th>' . get_column_heading(lang(array('string'=>'Out of Stock Date') ), $sort, $asc_desc, '', 'out_of_stock_date') . '</th>';
    }
    // Refresh stale SEO scores before the list is fetched - newest records
    // first, since those are the likeliest to be on the first screen - capped
    // per request so a large catalog cannot stall the page; leftovers are
    // covered by the nightly job and the refresh button on the Pages screen.
    if (pg_seo_schema_ready()) {
        $stale_product_ids = array();
        $stale_result = mysqli_query(db::$con, "SELECT id FROM products WHERE seo_analysis_current = 0 ORDER BY timestamp DESC LIMIT 200") or output_error('Query failed.');

        while ($stale_row = mysqli_fetch_assoc($stale_result)) {
            $stale_product_ids[] = $stale_row['id'];
        }

        if ($stale_product_ids) {
            // Budgeted for the same reason as the pages screen: this is
            // synchronous work inside the visitor's request.
            pg_seo_recalculate('product', $stale_product_ids, 3);
        }
    }

    /* get results for just this screen*/
    $barcode_select = (defined('BARCODE_ENABLED') && BARCODE_ENABLED) ? 'IFNULL(pb.bc_count, 0) as barcode_count,' : '';
    $barcode_join   = (defined('BARCODE_ENABLED') && BARCODE_ENABLED) ? 'LEFT JOIN (SELECT product_id, COUNT(*) as bc_count FROM product_barcodes GROUP BY product_id) pb ON pb.product_id = products.id' : '';
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
                " . (pg_seo_schema_ready() ? "products.seo_flags, products.seo_checked_at," : "'0' AS seo_flags, '0' AS seo_checked_at,") . "
                products.seo_analysis_current,
                " . pg_seo_impact_select('products.seo_score', 'products.seo_checked_at') . ",
                $filter_specific_columns
                $barcode_select
                user.user_username as user,
                products.out_of_stock as out_of_stock,
                products.out_of_stock_timestamp as out_of_stock_timestamp,
                products.timestamp as timestamp
             FROM products
             LEFT JOIN user ON products.user = user.user_id
             " . pg_seo_traffic_join('product', 'products.id') . "
             $sql_join
             $filter_specific_join
             $barcode_join
             $where
             ORDER BY $sort_column $asc_desc";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // Determine whether to show product images based on config setting (default: show).
    $show_image = (bool) ECOMMERCE_SHOW_PRODUCT_IMAGES;
    $output_image_header = $show_image ? '<th>' . lang('Image') . '</th>' : '';

    $barcode_product_map = array(); // product_id => [sku, short_description, price, image]

    while ($row = mysqli_fetch_assoc($result)) {

        // These cells are only filled in for some of the filters, so they have to be reset for
        // every row.  Otherwise a row without them would repeat the previous row's cells.
        $output_all_product_actions_columns = '';
        $output_shipping_columns = '';
        $output_recurring_option_columns = '';
        $output_recurring_set_schedule_column = '';
        $output_start_page_column = '';
        $output_private_folder_access_columns = '';
        $output_add_membership_column = '';
        $output_out_of_stock_timestamp_column = '';
        $output_tax_column = '';
        $output_recurring_column = '';
        $output_inventory_columns = '';
        $output_product_form_column = '';
        $output_all_products_columns = '';
        $output_image_column = '';

        $product_id = $row['id'];
        $name = h($row['name']);
        $enabled = $row['enabled'];
        $short_description = $row['short_description'];
        $price = $row['price'] / 100;
        $form_name = $row['form_name'];
        $seo_score = $row['seo_score'];
        $selection_type = isset($row['selection_type']) ? $row['selection_type'] : '';
        $default_quantity = isset($row['default_quantity']) ? $row['default_quantity'] : '';
        $required_product = isset($row['required_product_name']) ? $row['required_product_name'] : '';
        $shippable = isset($row['shippable']) ? $row['shippable'] : '';
        $commissionable = isset($row['commissionable']) ? $row['commissionable'] : '';
        $recurring = isset($row['recurring']) ? $row['recurring'] : '';
        $recurring_start = isset($row['recurring_start']) ? $row['recurring_start'] : '';
        $number_of_payments = isset($row['number_of_payments']) ? $row['number_of_payments'] : '';
        $payment_period = isset($row['payment_period']) ? $row['payment_period'] : '';
        $recurring_schedule_editable_by_customer = isset($row['recurring_schedule_editable_by_customer']) ? $row['recurring_schedule_editable_by_customer'] : '';
        $start_page = isset($row['start_page']) ? $row['start_page'] : '';
        $grant_private_access = isset($row['grant_private_access']) ? $row['grant_private_access'] : '';
        $private_folder = isset($row['private_folder']) ? $row['private_folder'] : '';
        $membership_renewal = isset($row['membership_renewal']) ? $row['membership_renewal'] : '';
        $order_receipt_message = isset($row['order_receipt_message']) ? $row['order_receipt_message'] : '';
        $order_receipt_bcc_email_address = isset($row['order_receipt_bcc_email_address']) ? $row['order_receipt_bcc_email_address'] : '';
        $email_page = isset($row['email_page']) ? $row['email_page'] : '';
        $email_bcc = isset($row['email_bcc']) ? $row['email_bcc'] : '';
        $contact_group_name = isset($row['contact_group_name']) ? $row['contact_group_name'] : '';
        $custom_field_1 = isset($row['custom_field_1']) ? $row['custom_field_1'] : '';
        $custom_field_2 = isset($row['custom_field_2']) ? $row['custom_field_2'] : '';
        $custom_field_3 = isset($row['custom_field_3']) ? $row['custom_field_3'] : '';
        $custom_field_4 = isset($row['custom_field_4']) ? $row['custom_field_4'] : '';
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
		
        $output_image_column = '';
        if ($show_image) {
            if (!$image_name) {
                $output_image_column = '<td class="align-middle text-start"><svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img" ><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . lang(array('string'=>'No Image') ) . '</text></svg></td>';
            } else {
                $output_image_column = '<td class="align-middle text-start"><img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' .  PATH . $image_name . '" /></td>';
            }
        }
        
        // output filter specific table cells
        if (($filter == 'all_products') || ($filter == 'out_of_stock_products') || ($filter == 'seo_weak_products') || ($filter == 'seo_struct_error_products')) {
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
            ($filter == 'membership_products') ||
            ($filter == 'seo_weak_products') ||
            ($filter == 'seo_struct_error_products')) {
            
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
                $weight = ($row['weight']+0) . ' ' . lang('lb');
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
                    $output_recurring_start .= ' ' . lang('Day');
                } else {
                    $output_recurring_start .= ' ' . lang('Days');
                }
                
            // else output default
            } else {
                $output_recurring_start = lang('Immediately');
            }
            
            // if number of payments is not 0 then output the number of payments
            if ($number_of_payments != '0') {
                $output_number_of_payments = $number_of_payments;
                
            // else output default
            } else {
                $output_number_of_payments = lang('Unlimited');
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
                    $output_membership_renewal .= ' ' . lang('Day');
                } else {
                    $output_membership_renewal .= ' ' . lang('Days');
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

            $output_inventory_headers ='<th>' . get_column_heading(lang(array('string'=>'Inventory Quantity') ), $sort, $asc_desc, '', 'inventory_quantity') . '</th>';
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
        
        if (defined('BARCODE_ENABLED') && BARCODE_ENABLED && !empty($row['barcode_count'])) {
            $barcode_product_map[(int)$row['id']] = array(
                'sku'              => $row['name'],
                'short_description'=> $row['short_description'],
                'price'            => number_format($row['price'] / 100, 2, '.', ''),
                'productImageSrc'  => $row['image_name'] ? OUTPUT_PATH . $row['image_name'] : ''
            );
        }

        $output_rows .=
        '<tr>
            <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="products[]" value="' . $row['id'] . '" class="checkbox" /></td>
            <td class="align-middle text-start action-buttons">
                <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-pencil"></i></button>
                ' . (defined('BARCODE_ENABLED') && BARCODE_ENABLED && !empty($row['barcode_count']) ? '<button type="button" class="m-1 btn-data-control btn btn-outline-secondary border-2" data-loading-content=" " title="' . lang('Print Barcode') . '" onclick="pgPrintProductBarcode(' . (int)$row['id'] . ')"><i class="bi bi-printer"></i></button>' : '') . '
            </td>
            ' . $output_image_column . '
            <td class="align-middle chart_label ' . $output_name_and_short_description_color_class . '">' . $name . '<a href="javascript:void(0)" class="pg-seo-open d-block text-decoration-none" title="' . lang('SEO Detail') . '" data-seo-url="get_seo_analysis.php?type=product&amp;id=' . (int) $row['id'] . get_token_query_string_field() . '">' . pg_seo_render_bar($row) . '</a></td>
            <td class=" align-middle ' . $output_name_and_short_description_color_class . '">' . h($short_description) . '</td>
            <td class="align-middle text-center">' . $output_enabled_check_mark . '</td>
            <td class="align-middle text-end">' . prepare_amount($price) . '</td>
            ' . $output_inventory_columns . '
            ' . $output_tax_column . '
            ' . $output_product_form_column . '
            ' . $output_all_products_columns . ' 
            ' . $output_shipping_columns . '
            ' . $output_recurring_column . '
            ' . $output_recurring_option_columns . '
            ' . $output_recurring_set_schedule_column . '
            ' . $output_start_page_column . '
            ' . $output_private_folder_access_columns . '
            ' . $output_add_membership_column . '
            ' . $output_all_product_actions_columns . '
            <td class="align-middle text-center">' . pg_seo_render_impact($row) . '</td>
            <td class="align-middle">' . get_relative_time(array('timestamp' => $row['timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($row['user'] ?? '') ) ) ) . '</td>
            ' . $output_out_of_stock_timestamp_column . '
        </tr>';
    }

    $output = '
    <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <nav id="button_bar" class="pg-toolbar navigation" aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_product.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <a class="btn btn-sm btn-outline-secondary m-1" href="view_products.php?mode=variant_sets" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-grid me-2"></span>' . lang('Variant Sets') . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link link-secondary py-0 m-1" href="import_products.php"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                    <button type="submit" name="submit_data" value="Export Products" class="btn btn-link link-secondary py-0 m-1"><span class="material-icons me-1">file_download</span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                            </form>
                            <div class="pg-toolbar-grow"></div>
                            <form id="search_form" action="view_products.php" method="get" class="search_form">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="filter_select" name="filter" class="form-select" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . $output_filter_options . '</select>
                                </div>
                            </form>
                </nav>
                <div class="card my-4">
                    <div class="card-body p-0 position-relative">
                        <form name="form"  action="edit_products.php" method="post"> 
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
                                        <th class="noVis">' . lang(array('string'=>'Action') ) . '</th>
                                        ' . $output_image_header . ' 
                                        <th>' . get_column_heading(lang(array('string'=>'ID') ), $sort, $asc_desc, '', 'id') . '</th>
                                        <th>' . get_column_heading(lang(array('string'=>'Short Description') ), $sort, $asc_desc, '', 'short_description') . '</th>
                                        <th>' . get_column_heading(lang(array('string'=>'Enabled') ), $sort, $asc_desc, '', 'enabled') . '</th> 
                                        ' . $output_price_header . ' 
                                        ' . $output_inventory_headers . '
                                        ' . $output_tax_header . '  
                                        ' . $output_product_form_header . ' 
                                        ' . $output_all_products_headers . ' 
                                        ' . $output_shipping_headers . ' 
                                        ' . $output_recurring_header . ' 
                                        ' . $output_recurring_option_headers . ' 
                                        ' . $output_recurring_set_schedule_header . ' 
                                        ' . $output_start_page_header . ' 
                                        ' . $output_private_folder_access_headers . ' 
                                        ' . $output_add_membership_header . ' 
                                        ' . $output_all_product_actions_headers . ' 
                                        <th style="text-align: center;" title="' . h(lang('How much a weak score costs, weighed against how many people see the page')) . '">' . get_column_heading(lang(array('string'=>'Impact') ), $sort, $asc_desc, '', 'impact') . '</th>
                                        <th>' . get_column_heading(lang(array('string'=>'Last Modified') ), $sort, $asc_desc, '', 'last_modified') . '</th> 
                                        ' . $output_out_of_stock_timestamp_header . ' 
                                    </tr>
                                </thead>
                                <tbody>' . $output_rows . '</tbody>
                            </table>

                            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                                <div class="container">
                                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                                        <button type="button" value="Modify Selected" class=" btn mb-1 mt-1 btn-primary disabled" onclick="window.open(\'edit_products.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\'); edit_chart_content(\'edit\',\'product\')"><span class="material-icons me-2">edit</span>' . lang(array('string'=>'Modify Selected') ) . '</button>
                                        <button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('products')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>
                                    </div>
                                </div>
                            </nav>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        ' . pg_seo_render_detail_offcanvas() . '
    ';

    $barcode_print_js = '';
    if (defined('BARCODE_ENABLED') && BARCODE_ENABLED) {
        $barcode_print_js = '
        <script>
        window._pgViewBarcodeOpts = {
            apiUrl:        ' . json_encode(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/api.php') . ',
            token:         ' . json_encode($_SESSION['software']['token']) . ',
            labelTemplate: ' . json_encode(BARCODE_LABEL_TEMPLATE ?: null) . ',
            productMap:    ' . json_encode($barcode_product_map, JSON_HEX_TAG | JSON_HEX_AMP) . '
        };
        </script>';
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Products'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>($heading ?? lang('Products')),
            'heading_description' => ($subheading ?? lang('Your catalogue, stock and prices')),
            'head' => (defined('BARCODE_ENABLED') && BARCODE_ENABLED ?
                '<script src="assets/lib/JsBarcode/JsBarcode.all.min.js"></script>' : '')
        )
    ) . '<main id="content" class="container-fluid">' . $output . $barcode_print_js . '</main>' . output_footer();
    $liveform->remove_form('view_products');
}