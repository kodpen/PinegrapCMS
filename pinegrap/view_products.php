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

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['ecommerce']['view_products']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['ecommerce']['view_products']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // Whitelisted rather than stored raw: this value is interpolated into
    // ORDER BY further down, and it is kept in the session, so an injected
    // value would persist across requests.
    $_SESSION['software']['ecommerce']['view_products']['order'] = (strtolower($_REQUEST['order']) === 'desc') ? 'desc' : 'asc';
}

// If the sort is not set, then set to default.
if (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') == '') {
    $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
    $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
}

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

// Set where statement and page headers for the filter
switch ($filter) {
    case 'all_product_actions':
        // if the sort session does not apply to this screen then reset it to the default
        if((($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Set Start Page') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Grant Private Access') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Membership Renewal') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Order Receipt Message') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Order Receipt BCC') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'E-mail Page') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'E-mail Page BCC') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Contact Group') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))) {
            
            $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
            $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
        }
        
        $sql_join = "LEFT JOIN contact_groups ON contact_groups.id = products.contact_group_id ";
        
        // Change the heading and subheading.
        $heading = lang(array('string'=>'All Product Actions') );
        $subheading = lang(array('string'=>'Actions triggered by each product when it is ordered.') );
        
        // select the filter option
        $all_product_actions_filter_selected = ' selected="selected"';
        break;
        
    case 'shippable_products':
        // if the sort session does not apply to this screen then reset it to the default
        if((($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Weight') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'PWP') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SWP') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Dim') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Cont Req') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Prep') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Free Ship') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Extra Ship') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))) {
            
            $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
            $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
        }
        
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
        // if the sort session does not apply to this screen then reset it to the default
        if((($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Start') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Number of Payments') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Payment Period') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))) {
            
            $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
            $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
        }
        
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
        // if the sort session does not apply to this screen then reset it to the default
        if((($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Default Amount (Price)') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Recurring Payment') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Start') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Number of Payments') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Payment Period') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Allow to Schedule') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))) {
            
            $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
            $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
        }
        
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
        // if the sort session does not apply to this screen then reset it to the default
        if((($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Set Start Page') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Grant Private Access') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))) {
            
            $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
            $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
        }
        
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
        // if the sort session does not apply to this screen then reset it to the default
        if((($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Recurring Payment') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Set Start Page') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Membership Renewal') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))) {
            
            $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
            $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
        }
        
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
          
                
            // if the sort session does not apply to this screen then reset it to the default
            if(
                (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Selection Type') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Default Quantity') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Shippable') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Out of Stock Date') ))
            ) {
                $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Out of Stock Date') );
                $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
            }
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

            // The sort session survives a filter change, so a column that
            // only exists for another filter has to be cleared here. Without
            // it, arriving from a screen sorted by an alias this query never
            // selects builds an ORDER BY on an unknown column - a fatal that
            // repeats on every later visit, because both the filter and the
            // sort are stored in the session.
            if (
                (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Selection Type') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Default Quantity') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Shippable') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Recurring Payment') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL)
            ) {
                $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
                $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
            }

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

            // The sort session survives a filter change, so a column that
            // only exists for another filter has to be cleared here. Without
            // it, arriving from a screen sorted by an alias this query never
            // selects builds an ORDER BY on an unknown column - a fatal that
            // repeats on every later visit, because both the filter and the
            // sort are stored in the session.
            if (
                (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Selection Type') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Default Quantity') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Shippable') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Recurring Payment') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL)
                && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL)
            ) {
                $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
                $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
            }

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
        
        // if the sort session does not apply to this screen then reset it to the default
        if(
            (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'ID') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Short Description') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Enabled') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Price') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Taxable') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Product Form') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Selection Type') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Default Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Shippable') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Recurring Payment') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'SEO') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Impact') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Inventory Quantity') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != lang(array('string'=>'Last Modified') ))
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL)
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL)
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL)
            && (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '') != ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL)
        ) {
            $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
            $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
        }
        
        // Change the heading and subheading.
        $heading = lang(array('string'=>'All Products') );
        $subheading = lang(array('string'=>'Merchandise, downloads, donations, recurring fees, memberships, and simple payments.') );
        
        // select the filter option
        $all_products_filter_selected = ' selected="selected"';
        break;
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_products']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_products']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_products']['sort'] ?? '')) {
    case lang(array('string'=>'ID') ):
        $sort_column = 'products.name';
        break;

    case lang(array('string'=>'Enabled') ):
        $sort_column = 'products.enabled';
        break;

    case lang(array('string'=>'Short Description') ):
        $sort_column = 'products.short_description';
        break;
    case lang(array('string'=>'Price') ):
    case lang(array('string'=>'Default Amount (Price)') ):
        $sort_column = 'products.price';
        break;
    case lang(array('string'=>'Taxable') ):
        $sort_column = 'products.taxable';
        break;
    case lang(array('string'=>'Product Form') ):
        $sort_column = 'products.form_name';
        break;
    case lang(array('string'=>'Selection Type') ):
        $sort_column = 'products.selection_type';
        break;
    case lang(array('string'=>'Default Quantity') ):
        $sort_column = 'products.default_quantity';
        break;
    case lang(array('string'=>'Shippable') ):
        $sort_column = 'products.shippable';
        break;
    case lang(array('string'=>'Weight') ):
        $sort_column = 'products.weight';
        break;
    case lang(array('string'=>'PWP') ):
        $sort_column = 'products.primary_weight_points';
        break;
    case lang(array('string'=>'SWP') ):
        $sort_column = 'products.secondary_weight_points';
        break;
    case lang(array('string'=>'Dim') ):
        $sort_column = 'products.length';
        break;
    case lang(array('string'=>'Cont Req') ):
        $sort_column = 'products.container_required';
        break;
    case lang(array('string'=>'Prep') ):
        $sort_column = 'products.preparation_time';
        break;
    case lang(array('string'=>'Free Ship') ):
        $sort_column = 'products.free_shipping';
        break;
    case lang(array('string'=>'Extra Ship') ):
        $sort_column = 'products.extra_shipping_cost';
        break;
    case lang(array('string'=>'Recurring Payment') ):
        $sort_column = 'products.recurring';
        break;
    case lang(array('string'=>'SEO') ):
        $sort_column = 'products.seo_score';
        break;
    case lang(array('string'=>'Impact') ):
        $sort_column = 'seo_impact';
        break;
    case lang(array('string'=>'Allow to Schedule') ):
        $sort_column = 'products.recurring_schedule_editable_by_customer';
        break;
    case lang(array('string'=>'Order Receipt Message') ):
        $sort_column = 'products.order_receipt_message';
        break;
    case lang(array('string'=>'Order Receipt BCC') ):
        $sort_column = 'products.order_receipt_bcc_email_address';
        break;
    case lang(array('string'=>'E-mail Page') ):
        $sort_column = 'products.email_page';
        break;
    case lang(array('string'=>'E-mail Page BCC') ):
        $sort_column = 'products.email_bcc';
        break;
    case lang(array('string'=>'Contact Group') ):
        $sort_column = 'contact_group_name';
        break;
    case lang(array('string'=>'Start') ):
        $sort_column = 'products.start';
        break;
    case lang(array('string'=>'Number of Payments') ):
        $sort_column = 'products.number_of_payments';
        break;
    case lang(array('string'=>'Payment Period') ):
        $sort_column = 'products.payment_period';
        break;
    case lang(array('string'=>'Set Start Page') ):
        $sort_column = 'start_page';
        break;
    case lang(array('string'=>'Grant Private Access') ):
        $sort_column = 'private_folder';
        break;
    case lang(array('string'=>'Membership Renewal') ):
        $sort_column = 'products.membership_renewal';
        break;

    case ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL:
        $sort_column = 'products.custom_field_1';
        break;

    case ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL:
        $sort_column = 'products.custom_field_2';
        break;

    case ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL:
        $sort_column = 'products.custom_field_3';
        break;

    case ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL:
        $sort_column = 'products.custom_field_4';
        break;
    case lang(array('string'=>'Inventory Quantity') ):
            $sort_column = 'products.inventory_quantity';
            break;
    case lang(array('string'=>'Last Modified') ):
        $sort_column = 'products.timestamp';
        break;
    case lang(array('string'=>'Out of Stock Date') ):
        $sort_column = 'products.out_of_stock_timestamp';
        break;
    default:
        $sort_column = 'products.timestamp';
        $_SESSION['software']['ecommerce']['view_products']['sort'] = lang(array('string'=>'Last Modified') );
}

if (!empty($_SESSION['software']['ecommerce']['view_products']['order'])) {
    $asc_desc = ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '');
} elseif ($sort_column == 'products.timestamp') {
    $asc_desc = 'desc';
    $_SESSION['software']['ecommerce']['view_products']['order'] = 'desc';
} else {
    $asc_desc = 'asc';
    $_SESSION['software']['ecommerce']['view_products']['order'] = 'asc';
}


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
        ORDER BY ' . (($sort_column == 'seo_impact') ? 'products.timestamp' : $sort_column) . ' ' . $asc_desc;
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
    $output_filter_options = '<option value="all_products"' . $all_products_filter_selected . '>' . lang(array('string'=>'All Products') ) . ' (' . number_format($all_products) . ')</option>';

    // set all product actions option
    $output_filter_options .= '<option value="all_product_actions"' . $all_product_actions_filter_selected . '>' . lang(array('string'=>'All Product Actions') ) . ' (' . number_format($all_products) . ')</option>';

    // get the amount of shippable products
    $query = "SELECT count(id) FROM products WHERE shippable = '1'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);

    // set shippable product option
    $output_filter_options .= '<option value="shippable_products"' . $shippable_product_filter_selected . '>' . lang(array('string'=>'Shippable Products') ) . ' (' . number_format($row[0]) . ')</option>';

    // get the amount of recurring products
    $query = "SELECT count(id) FROM products WHERE recurring = '1'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);

    // set recurring product option
    $output_filter_options .= '<option value="recurring_products"' . $recurring_product_filter_selected . '>' . lang(array('string'=>'Recurring Products') ) . ' (' . number_format($row[0]) . ')</option>';

    // get the amount of donation products
    $query = "SELECT count(id) FROM products WHERE selection_type = 'donation'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);

    // set donation product option
    $output_filter_options .= '<option value="donation_products"' . $donation_product_filter_selected . '>' . lang(array('string'=>'Donation Products') ) . ' (' . number_format($row[0]) . ')</option>';

    // get the amount of grant access products
    $query = "SELECT count(id) FROM products WHERE grant_private_access = '1' AND (private_folder != '0' OR send_to_page != '0')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);

    // set grant access product option
    $output_filter_options .= '<option value="grant_access_products"' . $grant_access_product_filter_selected . '>' . lang(array('string'=>'Grant Access Products') ) . ' (' . number_format($row[0]) . ')</option>';

    // get the amount of membership products
    $query = "SELECT count(id) FROM products WHERE membership_renewal != '0'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);

    // set membership product option
    $output_filter_options .= '<option value="membership_products"' . $membership_product_filter_selected . '>' . lang(array('string'=>'Membership Products') ) . ' (' . number_format($row[0]) . ')</option>';
    
    // get the amount of out of stock products
    $query = "SELECT count(id) FROM products WHERE out_of_stock = '1'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    
    // set out of stock product option
    $output_filter_options .= '<option value="out_of_stock_products"' . $out_of_stock_products_filter_selected . '>' . lang(array('string'=>'All Out of Stock Products') ) . ' (' . number_format($row[0]) . ')</option>';

    // SEO filters. Only offered once the score columns exist, and the
    // structure one only once the analysis half that fills its bit does.
    if (pg_seo_schema_ready()) {

        $result = mysqli_query(db::$con, "SELECT count(id) FROM products WHERE (seo_score < 55) AND (seo_analysis_current = 1)") or output_error('Query failed.');
        $row = mysqli_fetch_row($result);

        $output_filter_options .= '<option value="seo_weak_products"' . $seo_weak_products_filter_selected . '>' . lang(array('string'=>'Products With a Weak SEO Score') ) . ' (' . number_format($row[0]) . ')</option>';

        if (pg_seo_structure_schema_ready()) {

            $result = mysqli_query(db::$con, "SELECT count(id) FROM products WHERE (seo_flags & 32768) != 0") or output_error('Query failed.');
            $row = mysqli_fetch_row($result);

            $output_filter_options .= '<option value="seo_struct_error_products"' . $seo_struct_error_products_filter_selected . '>' . lang(array('string'=>'Products With HTML Structure Errors') ) . ' (' . number_format($row[0]) . ')</option>';
        }
    }
   


    $filter_specific_columns .= '';
    $filter_specific_join .= '';

    /* Build filter specific table headers, sql joins and columns */
    if ((ECOMMERCE_TAX == true) && ($filter != 'all_product_actions')) {
        $output_tax_header =
            '<th>' . get_column_heading(lang(array('string'=>'Taxable') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
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
            $output_custom_field_1_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_1_LABEL, ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
        }

        $output_custom_field_2_heading = '';

        // If the second custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL != '') {
            $output_custom_field_2_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_2_LABEL, ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
        }

        $output_custom_field_3_heading = '';

        // If the third custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL != '') {
            $output_custom_field_3_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_3_LABEL, ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
        }

        $output_custom_field_4_heading = '';

        // If the fourth custom product field is active, then output heading for it.
        if (ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL != '') {
            $output_custom_field_4_heading .= '<th>' . get_column_heading(ECOMMERCE_CUSTOM_PRODUCT_FIELD_4_LABEL, ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
        }
        
        $output_all_products_headers = 
            '<th>' . get_column_heading(lang(array('string'=>'Selection Type') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Default Quantity') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Shippable') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
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
        
        $output_recurring_header = '<th>' . get_column_heading(lang(array('string'=>'Recurring Payment') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';   
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
            '<th>' . get_column_heading(lang(array('string'=>'Order Receipt Message') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Order Receipt BCC') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'E-mail Page') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'E-mail Page BCC') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Contact Group') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
        
    // else output product form header
    } else {
        $output_product_form_header = '<th>' . get_column_heading(lang(array('string'=>'Product Form') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
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
            '<th>' . get_column_heading(lang(array('string'=>'Weight') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'PWP') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'SWP') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Dim') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Cont Req') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Prep') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Free Ship') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Extra Ship') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
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
            '<th>' . get_column_heading(lang(array('string'=>'Start') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Number of Payments') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
            <th>' . get_column_heading(lang(array('string'=>'Payment Period') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
    }

    if ($filter == 'donation_products') {
        
        // Output dontaine price header
        $output_price_header = '<th>' . get_column_heading(lang(array('string'=>'Default Amount (Price)') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
        
        $output_recurring_set_schedule_header = '<th>' . get_column_heading(lang(array('string'=>'Allow to Schedule') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';

    // else output the default price header
    } else {
        $output_price_header = '<th>' . get_column_heading(lang(array('string'=>'Price') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
    }

    if (($filter == 'grant_access_products') || 
        ($filter == 'membership_products') ||
        ($filter == 'all_product_actions')) {

        // Set filter specific sql columns
        $filter_specific_columns .= "page.page_name as start_page,";
        
        // Set filter specific sql joins
        $filter_specific_join .= "LEFT JOIN page ON page.page_id = products.send_to_page ";

        $output_start_page_header = '<th>' . get_column_heading(lang(array('string'=>'Set Start Page') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
    }

    if (($filter == 'grant_access_products') || ($filter == 'all_product_actions')) {
        
        // Set filter specific sql columns
        $filter_specific_columns .= "folder.folder_name as private_folder,";

        // Set filter specific sql joins
        $filter_specific_join .= "LEFT JOIN folder ON folder.folder_id = products.private_folder ";
        
        $output_private_folder_access_headers = '<th>' . get_column_heading(lang(array('string'=>'Grant Private Access') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
    }

    if (($filter == 'membership_products') || ($filter == 'all_product_actions')) {
        
        $output_add_membership_header = '<th>' . get_column_heading(lang(array('string'=>'Membership Renewal') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
    }
    if ($filter == 'out_of_stock_products') {
        
        $output_out_of_stock_timestamp_header = '<th>' . get_column_heading(lang(array('string'=>'Out of Stock Date') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
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

        $product_id = $row['id'];
        $name = h($row['name']);
        $enabled = $row['enabled'];
        $short_description = $row['short_description'];
        $price = $row['price'] / 100;
        $form_name = $row['form_name'];
        $seo_score = $row['seo_score'];
        $selection_type = $row['selection_type'];
        $default_quantity = $row['default_quantity'];
        $required_product = isset($row['required_product_name']) ? $row['required_product_name'] : '';
        $shippable = $row['shippable'];
        $commissionable = isset($row['commissionable']) ? $row['commissionable'] : '';
        $recurring = $row['recurring'];
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
        $custom_field_1 = $row['custom_field_1'];
        $custom_field_2 = $row['custom_field_2'];
        $custom_field_3 = $row['custom_field_3'];
        $custom_field_4 = $row['custom_field_4'];
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
            
            $extra_shipping_cost = BASE_CURRENCY_SYMBOL . number_format($row['extra_shipping_cost'] / 100, 2, '.', ',');
                
            
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

            $output_inventory_headers ='<th>' . get_column_heading(lang(array('string'=>'Inventory Quantity') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>';
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
            <td class=" align-middle ' . $output_name_and_short_description_color_class . '">' . $short_description . '</td>
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
            <td class="align-middle">' . get_relative_time(array('timestamp' => $row['timestamp'])) . ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($row['user']) ) ) ) . '</td>
            ' . $output_out_of_stock_timestamp_column . '
        </tr>';
    }

    $output = '
    <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
               
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                        <h2 class="d-inline-block " data-bs-content="' . $subheading . '" title="' . $heading . '">' . $heading . '</h2>
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <a class="btn btn-sm btn-primary m-1 " href="add_product.php" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-plus-circle me-2"></span>' . lang(array('string'=>'Create') ) . '</a>
                            <a class="btn btn-sm btn-outline-secondary m-1" href="view_products.php?mode=variant_sets" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="bi bi-grid me-2"></span>' . lang('Variant Sets') . '</a>
                            <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                                <div class=" btn-group btn-group-sm flex-wrap">
                                    <a class="btn btn-link link-secondary py-0 m-1" href="import_products.php"><span class="bi bi-box-arrow-in-right me-1"></span>' . lang(array('string'=>'Import') ) . '</a>
                                    <button type="submit" name="submit_data" value="Export Products" class="btn btn-link link-secondary py-0 m-1"><span class="material-icons me-1">file_download</span>' . lang(array('string'=>'Export') ) . '</button>
                                </div>
                            </form>
                        </nav>
                    </div>
                    <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
                        <div class="row justify-content-center justify-content-md-end">
                            <form id="search_form" action="view_products.php" method="get" class="search_form col-auto">
                                <div class="input-group input-group-sm">
                                    <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                    <select id="filter_select" name="filter" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . $output_filter_options . '</select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
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
                                        <th>' . get_column_heading(lang(array('string'=>'ID') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang(array('string'=>'Short Description') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang(array('string'=>'Enabled') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th> 
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
                                        <th style="text-align: center;" title="' . h(lang('How much a weak score costs, weighed against how many people see the page')) . '">' . get_column_heading(lang(array('string'=>'Impact') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th>
                                        <th>' . get_column_heading(lang(array('string'=>'Last Modified') ), ($_SESSION['software']['ecommerce']['view_products']['sort'] ?? ''), ($_SESSION['software']['ecommerce']['view_products']['order'] ?? '')) . '</th> 
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
    </main>';

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
            'heading'=>lang('Products'),
            'head' => (defined('BARCODE_ENABLED') && BARCODE_ENABLED ?
                '<script src="assets/jsbarcode/JsBarcode.all.min.js"></script>' : '')
        )
    ) . $output . $barcode_print_js . output_footer();
    $liveform->remove_form('view_products');
}