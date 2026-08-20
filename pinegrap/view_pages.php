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

$liveform = new liveform('view_pages');
$user = validate_user();
validate_area_access($user, 'user');

// Manual SEO score refresh from the button bar. A GET link with a token, like
// the other state-changing links on this screen. When nothing is stale the
// whole site is queued first (a refresh means "recompute everything"); when
// stale rows already exist the run continues them instead, so repeated clicks
// converge on a large site rather than starting over each time. The time
// budget keeps a single click under the PHP execution limit.
//
// Queuing marks rows stale but does not clear seo_checked_at, so the scores
// stay on screen while the backlog drains rather than blanking site-wide.
if (isset($_GET['recalculate_seo']) && (USER_ROLE < 3)) {
    validate_token_field();

    if (!pg_seo_schema_ready()) {
        $liveform->add_notice(lang('Please run the software upgrade to enable SEO scores.'));

        go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
    }

    if (!db_value("SELECT COUNT(*) FROM page WHERE seo_analysis_current = 0")) {
        db("UPDATE page SET seo_analysis_current = 0");
    }

    $seo_run = pg_seo_recalculate('page', null, 20);

    if ($seo_run['remaining'] > 0) {
        $liveform->add_notice(lang(array(
            'string' => 'SEO scores were updated for {var:1} page{suffix:1}. {var:2} page{suffix:2} still need to be calculated - click the button again to continue.',
            'vars' => array($seo_run['processed'], $seo_run['remaining']),
            'suffix' => array((($seo_run['processed'] == 1) ? '' : 's'), (($seo_run['remaining'] == 1) ? '' : 's')))));
    } else {
        $liveform->add_notice(lang(array(
            'string' => 'SEO scores were updated for {var:1} page{suffix:1}.',
            'vars' => $seo_run['processed'],
            'suffix' => (($seo_run['processed'] == 1) ? '' : 's'))));
    }

    log_activity(lang('recalculated SEO scores'), $_SESSION['sessionusername']);

    go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
}

// The HTML structure pass, on demand instead of waiting for tonight. It runs
// exactly what the nightly job runs - pages, products, product groups, and the
// link graph once the queue empties - with a much smaller time budget, because
// this one is inside a browser request and a page render is a full request's
// worth of work.
//
// The queue is the state, so a click that runs out of budget is not a failed
// run: it leaves the rest queued and the next click, or the job tonight,
// continues from there.
//
// Worth knowing: rendered here the pages are rendered inside a signed-in
// administrator's session, which the nightly job does not have. Content behind
// a membership gate is therefore visible to this pass and not to the job. The
// job re-analyzes on its own schedule, so the difference does not persist.
if (isset($_GET['analyze_seo']) && (USER_ROLE < 3)) {
    validate_token_field();

    require_once(dirname(__FILE__) . '/seo_structure.php');

    if (!pg_seo_structure_schema_ready()) {
        $liveform->add_notice(lang('Please run the software upgrade to enable SEO scores.'));

        go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
    }

    if (!class_exists('DOMDocument')) {
        $liveform->add_notice(lang('The PHP DOM extension is not available, so the HTML structure cannot be analyzed.'));

        go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
    }

    // Deliberately shorter than the job's budget. A browser request that keeps
    // an administrator waiting a full minute reads as a hung screen, and there
    // is nothing lost by stopping early.
    $analyze_budget = defined('SEO_ANALYZE_BUTTON_BUDGET') ? (int) SEO_ANALYZE_BUTTON_BUDGET : 20;

    $analyze_run = pg_seo_analyze_batch($analyze_budget);

    if ($analyze_run['remaining'] > 0) {
        $liveform->add_notice(lang(array(
            'string' => 'The HTML structure of {var:1} record{suffix:1} was analyzed. {var:2} record{suffix:2} still need to be analyzed - click the button again to continue.',
            'vars' => array($analyze_run['analyzed'], $analyze_run['remaining']),
            'suffix' => array((($analyze_run['analyzed'] == 1) ? '' : 's'), (($analyze_run['remaining'] == 1) ? '' : 's')))));
    } elseif ($analyze_run['analyzed'] > 0) {
        $liveform->add_notice(lang(array(
            'string' => 'The HTML structure of {var:1} record{suffix:1} was analyzed.',
            'vars' => $analyze_run['analyzed'],
            'suffix' => (($analyze_run['analyzed'] == 1) ? '' : 's'))));
    } else {
        // Nothing analyzed and nothing left: the queue was already empty. This
        // is what the operator sees on every click after the first full pass,
        // so it says so rather than reporting a run of zero records.
        $liveform->add_notice(lang('The HTML structure of every record is already up to date.'));
    }

    log_activity(lang('analyzed HTML structure'), $_SESSION['sessionusername']);

    go(PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
}

$output_clear_button = '';

// If there is a filter set.
if (isset($_GET['filter']) == true) {
    // Send the filter to the search form.
    $filter = $_GET['filter'];
} else {
    $filter = 'default';
}

$filter_for_links = '&filter=' . $filter;
$output_filter_for_links = h($filter_for_links);

// build filters array
$filters_in_array = 
    array(
        'all_my_pages'=>lang('All My Pages'),
        'all_my_archived_pages'=>lang('All My Archived Pages'),
        'my_home_pages'=>lang('My Home Pages'),
        'my_searchable_pages'=>lang('My Searchable Pages'),
        'my_unsearchable_pages'=>lang('My Unsearchable Pages'),
        'my_sitemap_pages'=>lang('My Site Map Pages'),
        'my_rss_enabled_pages'=>lang('My RSS Enabled Pages'),
        'my_standard_pages'=>lang('My Standard Pages'),
        'my_photo_gallery_pages'=>lang('My Photo Gallery Pages'),
        'my_calendar_pages'=>lang('My Calendar Pages'),
        'my_custom_form_pages'=>lang('My Custom Form Pages'),
        'my_form_view_pages'=>lang('My Form View Pages'),
        'my_commerce_pages'=>lang('My Commerce Pages'),
        'my_account_pages'=>lang('My Account Pages'),
        'my_login_pages'=>lang('My Login Pages'),
        'my_affiliate_pages'=>lang('My Affiliate Pages'),
        'my_miscellaneous_pages'=>lang('My Miscellaneous Pages'),
        'my_public_pages'=>lang('My Public Access Pages'),
        'my_guest_pages'=>lang('My Guest Access Pages'),
        'my_registration_pages'=>lang('My Registration Access Pages'),
        'my_membership_pages'=>lang('My Member Access Pages'),
        'my_private_pages'=>lang('My Private Access Pages')
    );

// The SEO filters query columns that only exist after the upgrade has run;
// until then they are left out of the list entirely. $filter comes straight
// from the query string, so it is validated against this list further down -
// otherwise a bookmarked or hand-typed SEO filter would build a WHERE clause
// naming a column that is not there and kill the screen.
if (pg_seo_schema_ready()) {
    $filters_in_array['seo_critical'] = lang('SEO Score Critical (0-29)');
    $filters_in_array['seo_weak'] = lang('SEO Score Weak (30-54)');
    $filters_in_array['seo_sitemap_no_title'] = lang('In Site Map, Missing Title');
    $filters_in_array['seo_sitemap_no_description'] = lang('In Site Map, Missing Description');
    $filters_in_array['seo_duplicate'] = lang('Duplicate Title or Description');
    $filters_in_array['seo_search_weak_keywords'] = lang('In Site Search, Weak Keywords');
    $filters_in_array['seo_not_calculated'] = lang('SEO Score Not Calculated');
}

// The structure filters read bits that only the HTML analysis pass fills in,
// so they stay out of the list until that half is installed.
if (pg_seo_structure_schema_ready()) {
    $filters_in_array['seo_no_h1'] = lang('Missing H1 Heading');
    $filters_in_array['seo_struct_error'] = lang('HTML Structure Errors');
    $filters_in_array['seo_img_no_alt'] = lang('Images Without Alt Text');
}

// The link filters read seo_flags bits the graph pass fills in, which needs
// the 2026.4.13 columns.
if (pg_seo_structure_schema_ready() && db_item("SHOW TABLES LIKE 'seo_link'")) {
    $filters_in_array['seo_broken_links'] = lang('Pages With Broken Internal Links');
    $filters_in_array['seo_orphan'] = lang('Orphan Pages');
}

// An SEO filter that is not in the list - because the upgrade has not run,
// or because the value was typed - falls back to the default view rather
// than building a WHERE clause on a column that may not exist.
if ((strpos($filter, 'seo_') === 0) && !isset($filters_in_array[$filter])) {
    $filter = 'default';
    $filter_for_links = '&filter=' . $filter;
    $output_filter_for_links = h($filter_for_links);
}

// if sort was set, update session
if (isset($_REQUEST['sort'])) {
    // store sort in session
    $_SESSION['software']['pages']['sort'] = $_REQUEST['sort'];

    // clear order
    $_SESSION['software']['pages']['order'] = '';
}

// if order was set, update session
if (isset($_REQUEST['order'])) {
    // Whitelisted rather than stored raw: this value is interpolated into
    // ORDER BY further down, and it is kept in the session, so an injected
    // value would persist across requests.
    $_SESSION['software']['pages']['order'] = (strtolower($_REQUEST['order']) === 'desc') ? 'desc' : 'asc';
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

// If the sort session is set to Form Name or Form Enabled and the view filter is not set to custom form set the sort session to default
if (((isset($_SESSION['software']['pages']) && ($_SESSION['software']['pages']['sort'] ?? '') == lang('Form Name') ) || (isset($_SESSION['software']['pages']) && ($_SESSION['software']['pages']['sort'] ?? '') == lang('Form Enabled') )) && ($filter != 'my_custom_form_pages')) {
    $_SESSION['software']['pages']['sort'] = '';
}

// If a theme is being previewed then get the activated theme.
// We will use in several places below.
if (isset($_SESSION['software']['preview_theme_id']) && $_SESSION['software']['preview_theme_id']) {
    $activated_theme_id = db_value("SELECT id FROM files WHERE activated_" . $_SESSION['software']['device_type'] . "_theme = '1'");
}

// If the sort is set to Desktop Page Style or Mobile Page Style,
// and the user is previewing a theme that is not the activated theme,
// then reset the sort to the default, because we can't easily sort for preview styles.
if (
    (
        (isset($_SESSION['software']['pages']) && ($_SESSION['software']['pages']['sort'] ?? '') == 'Desktop Page Style')
        || (isset($_SESSION['software']['pages']) && ($_SESSION['software']['pages']['sort'] ?? '') == 'Mobile Page Style')
    )
    && (isset($_SESSION['software']['preview_theme_id']))
    && ($_SESSION['software']['preview_theme_id'])
    && ($_SESSION['software']['preview_theme_id'] != $activated_theme_id)
) {
    unset($_SESSION['software']['pages']['sort']);
    unset($_SESSION['software']['pages']['order']);
}

$style_join_field = "";
$style_column_heading_label = '';
$output_device_type_toggle = '';

// do some different things based on the device type
switch ($_SESSION['software']['device_type']) {
    case 'desktop':
    default:
        // set style join field to desktop style field
        $style_join_field = "page.page_style";

        // set the style column heading
        $style_column_heading_label = lang('Desktop Page Style');

        // output toggle to show mobile
        $output_device_type_toggle = '<a class="btn btn-sm btn-secondary p-1 material-icons" href="update_device_type.php?device_type=mobile&amp;send_to=' . h(urlencode(get_request_uri())) . get_token_query_string_field() . '" title="' . lang('Show mobile Page Styles') . '">phone_iphone</a>';

        // if the sort is mobile page style, then set it to desktop page style,
        // so that the column will still be sorted correctly
        if (isset($_SESSION['software']['pages']) && ($_SESSION['software']['pages']['sort'] ?? '') == 'Mobile Page Style') {
            $_SESSION['software']['pages']['sort'] = 'Desktop Page Style';
        }

        break;
    
    case 'mobile':
        // set style join field to mobile style field
        $style_join_field = "page.mobile_style_id";

        // set the style column heading
        $style_column_heading_label = lang('Mobile Page Style');

        // output toggle to show desktop
        $output_device_type_toggle = '<a class="btn btn-sm btn-secondary p-1 material-icons" href="update_device_type.php?device_type=desktop&amp;send_to=' . h(urlencode(get_request_uri())) . get_token_query_string_field() . '" title="' . lang('Show desktop Page Styles') . '">computer</a>';

        // if the sort is desktop page style, then set it to mobile page style,
        // so that the column will still be sorted correctly
        if (($_SESSION['software']['pages']['sort'] ?? '') == 'Desktop Page Style') {
            $_SESSION['software']['pages']['sort'] = 'Mobile Page Style';
        }

        break;
}
if(isset($_SESSION['software']['pages'])){
    // if the sort is not set yet, then default it to empty so that the switch below falls
    // through to its default case
    if (isset($_SESSION['software']['pages']['sort']) == false) {
        $_SESSION['software']['pages']['sort'] = '';
    }

    switch (($_SESSION['software']['pages']['sort'] ?? '')) {
        case lang('Name'):
            $sort_column = 'page_name';
            break;
    
        case lang('Form Name'):
            $sort_column = 'form_name';
            break;
    
        case lang('Form Enabled'):
            $sort_column = 'enabled';
            break;
    
        case lang('Folder'):
            $sort_column = 'folder_name';
            break;
    
        case lang('Desktop Page Style'):
        case lang('Mobile Page Style'):
            $sort_column = 'style_name';
            break;
    
        case lang('Searchable'):
            $sort_column = 'page_search';
            break;
    
        case lang('Comments'):
            $sort_column = 'comments';
            break;
    
        case lang('Page Type'):
            $sort_column = 'page_type';
            break;
            
        case lang('SEO'):
            $sort_column = 'seo_score';
            break;

        case lang('Impact'):
            $sort_column = 'seo_impact';
            break;
    
        case lang('Site Map'):
            $sort_column = 'sitemap';
            break;
    
        case lang('Last Modified'):
            $sort_column = 'page_timestamp';
            break;
    
        default:
            $sort_column = 'page_timestamp';
            $_SESSION['software']['pages']['sort'] = lang('Last Modified');
            break;
    }
}else{
    $sort_column = 'page_timestamp';
    $_SESSION['software']['pages']['sort'] = lang('Last Modified');
}


if (isset($_SESSION['software']['pages']['order']) && ($_SESSION['software']['pages']['order'] ?? '')) {
    $asc_desc = ($_SESSION['software']['pages']['order'] ?? '');
} elseif ($sort_column == 'page_timestamp') {
    $asc_desc = 'desc';
    $_SESSION['software']['pages']['order'] = 'desc';
} else {
    $asc_desc = 'asc';
    $_SESSION['software']['pages']['order'] = 'asc';
}

$folders_that_user_has_access_to = array();

// if user is a basic user, then get folders that user has access to
if ($user['role'] == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
}


$output_button_bar = '';

// if the user is at least a manager or has create pages turned on, then output the create page button and button bar
if (($user['role'] < '3') || ($user['create_pages'] == TRUE)) {
    $output_button_bar .=
        '<nav id="button_bar" class="navigation " aria-label="Button Bar">
        <a class="btn btn-sm btn-primary m-1" href="add_page.php" data-loading-content="' . lang(array('string'=>'Loading')) . '"><span class="bi bi-plus-circle me-2"></span>' . lang('Create') . '</a>
        <a class="btn btn-sm btn-outline-secondary m-1" href="add_system_style.php?from=pages" data-loading-content="' . lang(array('string'=>'Loading')) . '"><span class="bi bi-plus-circle me-2"></span>' . lang('Create') . ' (Visual Page Editor)</a>
        ';

    // If advanced search is enabled and the user is a manager or above then output "Update Search Index" button.
    if ((SEARCH_TYPE == 'advanced') && (USER_ROLE != 3)) {
        $output_button_bar .= '
        <div class=" btn-group btn-group-sm flex-wrap">
            <button type="button" class="btn btn-link link-secondary py-0 m-1" onclick="window.open(\'update_search_index.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\');"><span class="material-icons me-1">manage_search</span>' . lang(array('string'=>'Update Search Index') ) . '</button>
        </div>';
    }

    // SEO score refresh sits with the other maintenance buttons. Managers and
    // above, same threshold the scores themselves matter to.
    if (USER_ROLE < 3) {
        $output_button_bar .= '
        <div class=" btn-group btn-group-sm flex-wrap">
            <a class="btn btn-link link-secondary py-0 m-1" href="view_pages.php?recalculate_seo=1' . get_token_query_string_field() . '" data-loading-content="' . lang(array('string'=>'Loading')) . '"><span class="bi bi-speedometer2 me-1"></span>' . lang('Refresh SEO Scores') . '</a>
            <a class="btn btn-link link-secondary py-0 m-1" href="view_pages.php?analyze_seo=1' . get_token_query_string_field() . '" title="' . lang('Renders each page and examines the resulting HTML. Covers products and product groups too. Slower than a score refresh, and it may take several clicks on a large site.') . '" data-loading-content="' . lang(array('string'=>'Loading')) . '"><span class="bi bi-code-slash me-1"></span>' . lang('Analyze HTML Structure') . '</a>
        </div>';
    }

    $output_button_bar .= '</nav>';
}

// Set the name heading to the default.
// This may be changed by different filters
$output_name_heading = lang('Name');
$page_access_control_filter = '';
$output_form_name_heading  = '';
$output_form_enabled_heading = '';
$where = '';
$join_table = '';
$group_column_by = '';
$select_column = '';
// Switch between the subnav filters
switch ($filter) {
    case 'all_my_archived_pages':
        // Change the heading and subheading.
        $heading = lang('All My Archived Pages');
        $subheading = lang('All archived pages that I can edit & duplicate.');
        break;
    
    case 'my_home_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_home = "yes")';

        // Change the heading and subheading.
        $heading = lang('My Home Pages');
        $subheading = lang('These pages are rotated as the website\'s home page.');
        break;

    case 'my_searchable_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_search = "1")';
        // Change the heading and subheading.
        $heading = lang('My Searchable Pages');
        $subheading = lang('Depending on the page\'s access control, these pages can be found using the built-in site search feature.');
        break;

    case 'my_unsearchable_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_search = "0")';
        // Change the heading and subheading.
        $heading = lang('My Unsearchable Pages');
        $subheading = lang('These pages cannot be found using the built-in site search.');
        break;
        
    case 'my_sitemap_pages':
        // set page access control filter to public so only public pages show up in the results
        $page_access_control_filter = 'public';
        
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }
        
        // Set the query filter
        $where .= ' (page.sitemap = "1")';
        
        // Change the heading and subheading.
        $heading = lang('My Site Map Pages');
        $subheading = lang('These are Public Pages that appear in the sitemap.xml file so search engines can find them.');
        break;
        
    case 'my_rss_enabled_pages':
        // set page acces control filter to public so only public pages show up in the results
        $page_access_control_filter = 'public';
    
        // Join tables to query
        $join_table = 
            "LEFT JOIN form_list_view_pages ON
                (form_list_view_pages.page_id = page.page_id)
                AND (form_list_view_pages.collection = 'a')
             LEFT JOIN form_fields ON form_fields.page_id = form_list_view_pages.custom_form_page_id";
        
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }
        
        // Set the query filter
        $where .= '(((page.page_type = "form list view") AND (form_fields.rss_field != "")) OR (page.page_type = "calendar view") OR (page.page_type = "catalog") OR (page.page_type = "catalog detail") OR (page.page_type = "order form"))';
        
        // Group the pages based on their id
        $group_column_by = 'GROUP BY (page.page_id)';
            
        // Change the heading and subheading.
        $heading = lang('My RSS Enabled Pages');
        $subheading = lang('These are Public Pages that are able to broadcast RSS feeds.');
        break;

    case 'my_standard_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "standard")';

        // Change the heading and subheading.
        $heading = lang('My Standard Pages');
        $subheading = lang('These pages only contain content and do not contain any built-in interactive features.');
        break;

    case 'my_photo_gallery_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "photo gallery")';

        // Change the heading and subheading.
        $heading = lang('My Photo Gallery Pages');
        $subheading = lang('These pages display a photo gallery slideshow of all photos located in the same folder.');
        break;

    case 'my_calendar_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "calendar event view" OR page_type = "calendar view")';

        // Change the heading and subheading.
        $heading = lang('My Calendar Pages');
        $subheading = lang('These pages overlay one or more calendars, calendar details, and published events.');
        break;
    case 'my_custom_form_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        $select_column = ',
            custom_form_pages.form_name,
            custom_form_pages.enabled as enabled';

        $join_table = 'LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id';

        // Set the query filter.
        $where .= ' (page_type = "custom form" OR page_type = "custom form" OR page_type = "custom form confirmation")';

        // Output additional table headings
        $output_form_name_heading = '<th>' . get_column_heading(lang('Form Name'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>';
        $output_form_enabled_heading = '<th>' . get_column_heading(lang('Form Enabled'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>';

        // Change the heading.
        $heading = lang('My Custom Form Pages');
        $subheading = lang('These pages either gather data through customizable forms or display submitted form confirmations.');
        break;
    case 'my_form_view_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }
        // Set the query filter.
        $where .= ' ((page_type = "form list view") OR (page_type = "form item view") OR (page_type = "form view directory"))';
        // Change the heading.
        $heading = lang('My Form View Pages');
        // Change the subheading.
        $subheading = lang('These pages display submitted form data in customizable views.');
        break;
    case 'my_commerce_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "order form" OR page_type = "view order" OR page_type = "catalog" OR page_type = "catalog detail" OR page_type = "express order" OR page_type = "shopping cart" OR page_type = "shipping address and arrival" OR page_type = "shipping method" OR page_type = "billing information" OR page_type = "order preview" OR page_type = "order receipt" OR page_type = "update address book")';

        // Change the heading and subheading.
        $heading = lang('My Commerce Pages');
        $subheading = lang('These pages provide the built-in ecommerce features.');
        break;

    case 'my_account_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "my account" OR page_type = "my account profile" OR page_type = "email preferences" OR page_type = "view order" OR page_type = "update address book" OR page_type = "change password")';

        // Change the heading and subheading.
        $heading = lang('My Account Pages');
        $subheading = lang('These pages provide the built-in account self-management for all site users.');
        break;

    case 'my_login_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "registration entrance" OR page_type = "registration confirmation" OR page_type = "membership entrance" OR page_type = "membership confirmation" OR page_type = "forgot password" OR page_type = "login" OR page_type = "logout" OR page_type = "login" OR page_type = "set password")';

        // Change the heading and subheading.
        $heading = lang('My Login Pages');
        $subheading = lang('These pages provide the built-in user account creation and login capabilities for all site users.');
        break;

    case 'my_affiliate_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "affiliate sign up form" OR page_type = "affiliate sign up confirmation" OR page_type = "affiliate welcome")';

        // Change the heading and subheading.
        $heading = lang('My Affiliate Pages');
        $subheading = lang('These pages provide the built-in affiliate sign up features.');
        break;

    case 'my_miscellaneous_pages':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page_type = "email a friend" OR page_type = "error" OR page_type = "folder view" OR page_type = "search results")';

        // Change the heading and subheading.
        $heading = lang('My Miscellaneous Pages');
        $subheading = lang('These pages provide responses to other site-wide built-in features.');
        break;

    case 'my_public_pages':
        // Set the page access control filter
        $page_access_control_filter = 'public';

        // Change the heading and subheading.
        $heading = lang('My Public Access Pages');
        $subheading = lang('These pages are visible by any website visitor.');
        break;

    case 'my_guest_pages':
        // Set the page access control filter
        $page_access_control_filter = 'guest';

        // Change the heading and subheading.
        $heading = lang('My Guest Access Pages');
        $subheading = lang('These pages are visible to any website visitor after they are offered the option to login or register.');
        break;

    case 'my_registration_pages':
        // Set the page access control filter
        $page_access_control_filter = 'registration';

        // Change the heading and subheading.
        $heading = lang('My Registration Access Pages');
        $subheading = lang('These pages are visible to any website visitor, but only after they login or register.');
        break;

    case 'my_membership_pages':
        // Set the page access control filter
        $page_access_control_filter = 'membership';

        // Change the heading and subheading.
        $heading = lang('My Member Access Pages');
        $subheading = lang('These pages are visible to any website user, but only after they have either registered with a valid member id, purchased a membership product, completed a membership trial custom form, or logged in as an unexpired member.');
        break;

    case 'my_private_pages':
        // Set the page access control filter
        $page_access_control_filter = 'private';

        // Change the heading and subheading.
        $heading = lang('My Private Access Pages');
        $subheading = lang('These pages are visible only to website users who have been granted view access to the parent folder, or who have purchased a product that grants private access to the parent folder.');
        break;

    case 'seo_critical':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Uncalculated rows are excluded on purpose:
        // their stored 0 is "unknown", not a real score.
        $where .= ' (page.seo_score < 30) AND (page.seo_analysis_current = 1)';

        // Change the heading and subheading.
        $heading = lang('SEO Score Critical (0-29)');
        $subheading = lang('These pages have the most severe SEO problems and should be reviewed first.');
        break;

    case 'seo_weak':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page.seo_score BETWEEN 30 AND 54) AND (page.seo_analysis_current = 1)';

        // Change the heading and subheading.
        $heading = lang('SEO Score Weak (30-54)');
        $subheading = lang('These pages have significant SEO problems.');
        break;

    case 'seo_sitemap_no_title':
        // Only public pages can really be in the sitemap.
        $page_access_control_filter = 'public';

        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Bit 1 of seo_flags marks a missing title.
        $where .= ' (page.sitemap = "1") AND ((page.seo_flags & 1) != 0)';

        // Change the heading and subheading.
        $heading = lang('In Site Map, Missing Title');
        $subheading = lang('These pages are listed in sitemap.xml but have no web browser title, so search engines have to guess one.');
        break;

    case 'seo_sitemap_no_description':
        // Only public pages can really be in the sitemap.
        $page_access_control_filter = 'public';

        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Bit 32 of seo_flags marks a missing description.
        $where .= ' (page.sitemap = "1") AND ((page.seo_flags & 32) != 0)';

        // Change the heading and subheading.
        $heading = lang('In Site Map, Missing Description');
        $subheading = lang('These pages are listed in sitemap.xml but have no web browser description, so search engines compose their own snippet.');
        break;

    case 'seo_duplicate':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Bits 8 and 256 mark duplicated titles and
        // descriptions.
        $where .= ' ((page.seo_flags & 264) != 0)';

        // Change the heading and subheading.
        $heading = lang('Duplicate Title or Description');
        $subheading = lang('These pages share a web browser title or description with another page, which makes them compete with each other in search results.');
        break;

    case 'seo_search_weak_keywords':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Bits 512 and 1024 mark missing and thin
        // keywords on pages that are included in the site search.
        $where .= ' (page.page_search = "1") AND ((page.seo_flags & 1536) != 0)';

        // Change the heading and subheading.
        $heading = lang('In Site Search, Weak Keywords');
        $subheading = lang('These pages are included in the built-in site search but have missing or too few keywords, so visitors have a hard time finding them.');
        break;

    case 'seo_no_h1':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Bit 131072 marks a page whose rendered HTML
        // has no H1 heading.
        $where .= ' ((page.seo_flags & 131072) != 0)';

        // Change the heading and subheading.
        $heading = lang('Missing H1 Heading');
        $subheading = lang('The rendered HTML of these pages has no H1 heading, so nothing states what the page is about.');
        break;

    case 'seo_struct_error':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Bit 32768 marks at least one error-level
        // finding from the HTML structure analysis.
        $where .= ' ((page.seo_flags & 32768) != 0)';

        // Change the heading and subheading.
        $heading = lang('HTML Structure Errors');
        $subheading = lang('The markup of these pages has at least one serious fault, such as invalid nesting or images with no alt text.');
        break;

    case 'seo_img_no_alt':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter. Bit 524288 marks images with no alt text.
        $where .= ' ((page.seo_flags & 524288) != 0)';

        // Change the heading and subheading.
        $heading = lang('Images Without Alt Text');
        $subheading = lang('These pages contain images with no alt text, which search engines and screen readers cannot interpret.');
        break;

    case 'seo_broken_links':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Bit 2097152 marks at least one link that resolves to nothing.
        $where .= ' ((page.seo_flags & 2097152) != 0)';

        // Change the heading and subheading.
        $heading = lang('Pages With Broken Internal Links');
        $subheading = lang('These pages link to an address that no longer resolves to a page, file or product.');
        break;

    case 'seo_orphan':
        // Only public pages can really be in the sitemap.
        $page_access_control_filter = 'public';

        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Bit 4194304 marks a page in the sitemap that nothing links to.
        $where .= ' ((page.seo_flags & 4194304) != 0)';

        // Change the heading and subheading.
        $heading = lang('Orphan Pages');
        $subheading = lang('These pages are listed in sitemap.xml but no other page links to them, so a visitor can only arrive by search or by typing the address.');
        break;

    case 'seo_not_calculated':
        // If where is blank
        if ($where == '') {
            $where .= 'WHERE ';

        // else where is not blank, so add and
        } else {
            $where .= 'AND ';
        }

        // Set the query filter.
        $where .= ' (page.seo_checked_at = 0)';

        // Change the heading and subheading.
        $heading = lang('SEO Score Not Calculated');
        $subheading = lang('The SEO score of these pages is stale or has never been calculated. Scores are refreshed automatically while browsing, by the nightly job, or with the Refresh SEO Scores button.');
        break;

    default:
        // Change the heading and subheading.
        $heading = lang('All My Pages');
        $subheading = lang('All pages that I can edit &amp; duplicate.');
        break;
}

// If the user is previewing a theme that is not the activated theme,
// then output unsortable column heading for style column.
if (isset($_SESSION['software']['preview_theme_id']) && $_SESSION['software']['preview_theme_id'] && $_SESSION['software']['preview_theme_id'] != $activated_theme_id ) {
    $output_style_column_heading = $style_column_heading_label;

// Otherwise output sortable column.
} else {
    $output_style_column_heading = get_column_heading($style_column_heading_label, ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links);
}

// If where is blank
if ($where == '') {
    $where .= 'WHERE ';

// else where is not blank, so add and
} else {
    $where .= 'AND ';
}

// if the filter is not all my archived pages, then add the sql where statement to prevent getting archived pages
// NULL check covers pages with no parent folder (page_folder = 0) — LEFT JOIN produces NULL
// for folder columns in that case, and NULL = "0" evaluates to false in SQL.
if ($filter != 'all_my_archived_pages') {
    $where .= '(folder.folder_archived = "0" OR folder.folder_archived IS NULL)';

// else this is the all my archived pages filter, so only get archived pages
} else {
    $where .= '(folder.folder_archived = "1")';
}

$all_pages = 0;
$my_pages = 0;

// Get file's id and folder number from files.
//
// Deliberately not ordered. This pass only counts, so the order it reads rows
// in cannot matter - and sharing the display query's ORDER BY meant every sort
// column had to exist in this select list too, which is a coupling that breaks
// the screen outright the first time the two lists drift apart.
$query =
    "SELECT
       page.page_id,
       page.page_folder
    FROM page
    LEFT JOIN folder ON page.page_folder = folder.folder_id
    LEFT JOIN style ON $style_join_field = style.style_id
    LEFT JOIN user ON page.page_user = user.user_id
    " . $join_table . "
    $where
    " . $group_column_by;
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// Loop through the results
while ($row = mysqli_fetch_assoc($result)) {

    // If the folder access control filter is set.
    if ($page_access_control_filter) {

        // Compare the filter to the folder's access control type and set the row if they match.
        if (get_access_control_type($row['page_folder']) == $page_access_control_filter) {

            // Add one to all files.
            $all_pages++;

            // if user has access to file then add one to my files.
            if (check_folder_access_in_array($row['page_folder'], $folders_that_user_has_access_to) == true) {
                $my_pages++;
            }
       }

    // If the filder access control filter is not set.
    } else {
        // Add one to all files.
        $all_pages++;

        // if user has access to file then add one to my files.
        if (check_folder_access_in_array($row['page_folder'], $folders_that_user_has_access_to) == true) {
            $my_pages++;
        }
    }
}




// get all pages
$query =
    "SELECT
       page.page_id,
       page.page_name,
       page.page_folder,
       page.page_style,
       page.layout_type,
       folder.folder_name,
       folder.folder_access_control_type,
       style.style_name,
       style.style_type,
       page.page_search,
       page.page_home,
       page.page_type,
       page.comments,
       page.seo_score,
       " . (pg_seo_schema_ready() ? "page.seo_flags, page.seo_checked_at," : "'0' AS seo_flags, '0' AS seo_checked_at,") . "
       page.seo_analysis_current,
       page.sitemap,
       user.user_username,
       " . pg_seo_impact_select('page.seo_score', 'page.seo_checked_at') . ",
       page.page_timestamp" . $select_column . "
    FROM page
    LEFT JOIN folder ON page.page_folder = folder.folder_id
    LEFT JOIN style ON $style_join_field = style.style_id
    LEFT JOIN user ON page.page_user = user.user_id
    " . pg_seo_traffic_join('page', 'page.page_id') . "
    " . $join_table . "
    $where
    " . $group_column_by . "
    ORDER BY $sort_column $asc_desc";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// Declared before the loop that fills it: a filter matching nothing - now
// easy to reach, since a healthy site has no critical SEO scores - otherwise
// leaves it undefined and every later foreach over it emits a warning.
$pages = array();

while ($row = mysqli_fetch_assoc($result)) {
    // if user has access to page then add page to pages array
    if (check_folder_access_in_array($row['page_folder'], $folders_that_user_has_access_to) == true) {

        // If there is a folder access control filter.
        if ($page_access_control_filter) {

            // Compare the filter to the pages folder's access control type and set the row if they match.
            if (get_access_control_type($row['page_folder']) == $page_access_control_filter) {
                $pages[] = $row;
            }

        // Else If the folder access control filter is not set.
        } else {
            // Set the row.
            $pages[] = $row;
        }
    }
}
// Compute scores for stale rows that are about to be displayed, capped so a
// large installation cannot stall this screen on its first visit; everything
// past the cap is covered by the nightly job or the refresh button.
$stale_page_ids = array();

foreach ($pages as $stale_check_row) {
    if ($stale_check_row['seo_analysis_current'] != '1') {
        $stale_page_ids[] = $stale_check_row['page_id'];

        if (count($stale_page_ids) >= 200) {
            break;
        }
    }
}

// A time budget even on the lazy path: this runs inside the page request,
// and on a large backlog 200 evaluations plus 200 updates is not something a
// visitor should wait through. Whatever is left stays queued for the next
// load or the nightly job.
if ($stale_page_ids && pg_seo_schema_ready()) {

    $seo_lazy_run = pg_seo_recalculate('page', $stale_page_ids, 3);

    foreach ($pages as $stale_key => $stale_check_row) {
        if (isset($seo_lazy_run['items'][$stale_check_row['page_id']])) {
            $pages[$stale_key]['seo_score'] = $seo_lazy_run['items'][$stale_check_row['page_id']]['score'];
            $pages[$stale_key]['seo_flags'] = $seo_lazy_run['items'][$stale_check_row['page_id']]['flags'];
            $pages[$stale_key]['seo_analysis_current'] = '1';

            // The row was read before it had ever been scored, so it still
            // carries the timestamp that means "never". Every renderer below
            // asks that column first, so without this the same row prints a
            // score and a badge reading "not calculated yet".
            $pages[$stale_key]['seo_checked_at'] = time();

            // Impact came out of the query, computed from the score this row
            // had a moment ago. Leaving it would print the new score and the
            // old score's impact side by side.
            $pages[$stale_key]['seo_impact'] = pg_seo_impact_value(
                $pages[$stale_key]['seo_score'],
                $pages[$stale_key]['seo_views'] ?? 0);
        }
    }
}

$output_form_name_row = '';
$output_form_enabled_row = '';
$output_rows = '';
// if there is at least one result to display
if ($pages) {
    foreach ($pages as $page) {
        $query_string_from = '';

        // if page type is a certain page type, then prepare from
        switch ($page['page_type']) {
            case 'view order':
            case 'custom form':
            case 'custom form confirmation':
            case 'calendar event view':
            case 'catalog detail':
            case 'shipping address and arrival':
            case 'shipping method':
            case 'logout':
                $query_string_from = '?from=control_panel';
                break;
        }

        $output_link_url = h(escape_javascript(PATH)) . h(escape_javascript(encode_url_path($page['page_name']))) . $query_string_from;

        $output_style_name = '';

        // If the user is previewing a theme, and it is not the activated theme,
        // then get preview style if one exists.
        if (isset($_SESSION['software']['preview_theme_id']) && $_SESSION['software']['preview_theme_id'] && $_SESSION['software']['preview_theme_id'] != $activated_theme_id ) {
            $style_name = db_value(
                "SELECT style.style_name
                FROM preview_styles
                LEFT JOIN style ON preview_styles.style_id = style.style_id
                WHERE
                    (preview_styles.page_id = '" . $page['page_id'] . "')
                    AND (preview_styles.theme_id = '" . escape($_SESSION['software']['preview_theme_id']) . "')
                    AND (device_type = '" . escape($_SESSION['software']['device_type']) . "')");

            // If a preview style was found, then prepare to output style name.
            if ($style_name != '') {
                $output_style_name = '[P] ' . h($style_name);
            }
        }

        // If a style has not been found yet, then that means user is not previewing themes
        // or there is no preview style, so just output activated style.
        if ($output_style_name == '') {
            $output_no_mobile_style_warning_class = '';

            // if the page has a style specifically defined for it, then prepare to output style name
            if ($page['style_name'] != '') {
                $output_style_name = h($page['style_name']);

            // else the page does not have a style specifically defined for it, so get inherited style name
            } else {
                // get inherited style id
                $style_id = get_style($page['page_folder'], $_SESSION['software']['device_type']);

                // if the device type is set to mobile and a mobile style id could not be found, then get desktop style id
                // because we fallback to a desktop style when a mobile style cannot be found for a page
                if (
                    ($_SESSION['software']['device_type'] == 'mobile')
                    && ($style_id == 0)
                ) {
                    $style_id = get_style($page['page_folder'], 'desktop');

                    $output_no_mobile_style_warning_class = ' text-danger';
                }

                // if a style was found, then get style name
                if ($style_id != 0) {
                    // get inherited style name
                    $query = "SELECT style_name FROM style WHERE style_id = '$style_id'";
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);

                    $output_style_name = 'Default: ' . h($row['style_name']);
                }
            }

            // If a theme is being previewed, then add an "[A]" prefix before style name,
            // to show that it is the activated style.
            if (isset($_SESSION['software']['preview_theme_id']) && $_SESSION['software']['preview_theme_id']) {
                $output_style_name = '[A] ' . $output_style_name;
            }
        }

        $output_search_check_mark = '';

        // if page is searchable, then prepare to output check mark image
        if ($page['page_search'] == 1) {
            $output_search_check_mark = '<span class="material-icons">task_alt</span>';
        }

        $output_home_icon = '';
        $output_comments_check_mark = '';

        // if page is a home page, then prepare to output home icon
        if ($page['page_home'] == 'yes') {
            $output_home_icon = '<span class="material-icons">home</span>';
        }
        
        // if page has comments enabled, then prepare to output check mark image
        if ($page['comments'] == '1') {
            $output_comments_check_mark = '<span class="material-icons">task_alt</span>';
        }
        
        $output_sitemap_check_mark = '';
        
        // if page has sitemap enabled, then prepare to output check mark image
        if ($page['sitemap'] == '1') {
            $output_sitemap_check_mark = '<span class="material-icons">task_alt</span>';
        }

        // If custom form filter is on.
        if ($filter == 'my_custom_form_pages') {

            // if page is a home page, then prepare to output check mark image
            if ($page['enabled'] == 1) {
                $output_form_enabled_mark = '<span class="material-icons">task_alt</span>';
            }

            // output form rows
            $output_form_name_row = '<td class="align-middle">' . h($page['form_name']) . '</td>';
            $output_form_enabled_row = '<td class="align-middle text-center">' . $output_form_enabled_mark . '</td>';
        }

        $output_edit_url = 'edit_page.php?id=' . $page['page_id'];
        
        if($page['user_username'] != ''){
            $output_last_modifier_user = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($page['user_username']) ) ) );
        }else{
            $output_last_modifier_user = '';
        }
        
        $output_rows .=
            '<tr>
                <td class="select-all align-middle text-start"><input class="form-check-input " type="checkbox" name="pages[]" value="' . $page['page_id'] . '" class="checkbox" /></td>
			    <td class="align-middle text-start action-buttons">
                    <button type="button" class="m-1 btn-data-control btn btn-outline-primary border-2 " data-loading-content=" " title="' . lang('Edit') . '" onclick="window.location.href=\'' . $output_edit_url . '\'"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="m-1 btn-data-control btn btn-outline-secondary border-2 " data-loading-content=" " title="' . lang('Preview') . '" onclick="window.location.href=\'' . $output_link_url . '\'"><i class="bi bi-link"></i></button>
                </td>
                <td class="align-middle chart_label position-relative">' . $output_home_icon . h($page['page_name']) . '<a href="javascript:void(0)" class="pg-seo-open d-block text-decoration-none" title="' . lang('SEO Detail') . '" data-seo-url="get_seo_analysis.php?type=page&amp;id=' . $page['page_id'] . get_token_query_string_field() . '">' . pg_seo_render_bar($page) . '</a></td>
                ' . $output_form_name_row . '
                ' . $output_form_enabled_row . '
                <td class="align-middle text-start ' . $page['folder_access_control_type'] . '" title="' . h($page['folder_name']) . '"><span class="d-block overflow-hidden text-truncate" style="width: 100px;max-width:100%;"><span class="material-icons d-inline">folder</span><span class="badge fw-light text-reset d-inline">' . h($page['folder_name']) . '</span></span></td>
                <td class="align-middle ' . $output_no_mobile_style_warning_class . '">' . $output_style_name . '</td>
                <td class="align-middle">' . h(get_page_type_name($page['page_type'])) . '</td>
                <td class="align-middle text-center">' . $output_search_check_mark . '</td>
                <td class="align-middle text-center">' . $output_comments_check_mark . '</td>
                <td class="align-middle text-center">' . $output_sitemap_check_mark . '</td>
                <td class="align-middle text-center"><a href="javascript:void(0)" class="pg-seo-open text-decoration-none" title="' . lang('SEO Detail') . '" data-seo-url="get_seo_analysis.php?type=page&amp;id=' . $page['page_id'] . get_token_query_string_field() . '">' . pg_seo_render_badge($page) . '</a></td>
                <td class="align-middle text-center">' . pg_seo_render_impact($page) . '</td>
                <td class="align-middle" >' . get_relative_time(array('timestamp' => $page['page_timestamp'])) . $output_last_modifier_user . ' </td>
            </tr>';
    }
}

// Site-wide SEO summary for the strip under the heading. One aggregate query,
// no join: the folder conditions are already folded into the flag bits when
// the scores are computed. The counts link to the matching list filters.
//
// Not shown to basic users. Their list below is filtered to the folders they
// can reach, so a site-wide total would both disagree with what they see and
// tell them about pages they have no access to - and the refresh button that
// acts on it is hidden from them anyway.
$seo_summary = ((USER_ROLE >= 3) || !pg_seo_schema_ready()) ? null : db_item(
    "SELECT
        COALESCE(SUM(sitemap = 1), 0) AS in_sitemap,
        COALESCE(SUM((sitemap = 1) AND ((seo_flags & 1) != 0)), 0) AS sitemap_no_title,
        COALESCE(SUM((sitemap = 1) AND ((seo_flags & 32) != 0)), 0) AS sitemap_no_description,
        COALESCE(SUM(page_search = 1), 0) AS in_search,
        COALESCE(SUM((page_search = 1) AND ((seo_flags & 1536) != 0)), 0) AS search_weak_keywords,
        COALESCE(SUM(seo_checked_at = 0), 0) AS not_calculated,
        COALESCE(SUM((seo_flags & 131072) != 0), 0) AS no_h1,
        COALESCE(SUM((seo_flags & 524288) != 0), 0) AS img_no_alt
    FROM page");

$output_seo_summary = '';

if ($seo_summary && (($seo_summary['in_sitemap'] > 0) || ($seo_summary['in_search'] > 0) || ($seo_summary['not_calculated'] > 0))) {
    $seo_summary_parts = array();

    if ($seo_summary['in_sitemap'] > 0) {
        $seo_summary_part =
            '<a class="link-secondary" href="view_pages.php?filter=my_sitemap_pages">'
            . lang(array('string' => '{var:1} page{suffix:1} in the site map', 'vars' => (int) $seo_summary['in_sitemap'], 'suffix' => (($seo_summary['in_sitemap'] == 1) ? '' : 's')))
            . '</a>';

        if ($seo_summary['sitemap_no_title'] > 0) {
            $seo_summary_part .= ' · <a class="link-danger" href="view_pages.php?filter=seo_sitemap_no_title">'
                . lang(array('string' => '{var:1} missing a title', 'vars' => (int) $seo_summary['sitemap_no_title']))
                . '</a>';
        }

        if ($seo_summary['sitemap_no_description'] > 0) {
            $seo_summary_part .= ' · <a class="link-danger" href="view_pages.php?filter=seo_sitemap_no_description">'
                . lang(array('string' => '{var:1} missing a description', 'vars' => (int) $seo_summary['sitemap_no_description']))
                . '</a>';
        }

        $seo_summary_parts[] = $seo_summary_part;
    }

    if ($seo_summary['in_search'] > 0) {
        $seo_summary_part =
            '<a class="link-secondary" href="view_pages.php?filter=my_searchable_pages">'
            . lang(array('string' => '{var:1} page{suffix:1} in the site search', 'vars' => (int) $seo_summary['in_search'], 'suffix' => (($seo_summary['in_search'] == 1) ? '' : 's')))
            . '</a>';

        if ($seo_summary['search_weak_keywords'] > 0) {
            $seo_summary_part .= ' · <a class="link-danger" href="view_pages.php?filter=seo_search_weak_keywords">'
                . lang(array('string' => '{var:1} with weak or missing keywords', 'vars' => (int) $seo_summary['search_weak_keywords']))
                . '</a>';
        }

        $seo_summary_parts[] = $seo_summary_part;
    }

    // Structure counts only appear once the analysis half has produced them;
    // a row of zeroes would read as "no problems" on an installation where
    // the pass has simply never run.
    if (($seo_summary['no_h1'] > 0) || ($seo_summary['img_no_alt'] > 0)) {
        $seo_summary_part = lang('HTML') . ': ';
        $seo_structure_bits = array();

        if ($seo_summary['no_h1'] > 0) {
            $seo_structure_bits[] = '<a class="link-danger" href="view_pages.php?filter=seo_no_h1">'
                . lang(array('string' => '{var:1} without an H1 heading', 'vars' => (int) $seo_summary['no_h1']))
                . '</a>';
        }

        if ($seo_summary['img_no_alt'] > 0) {
            $seo_structure_bits[] = '<a class="link-danger" href="view_pages.php?filter=seo_img_no_alt">'
                . lang(array('string' => '{var:1} with images missing alt text', 'vars' => (int) $seo_summary['img_no_alt']))
                . '</a>';
        }

        $seo_summary_parts[] = $seo_summary_part . implode(' · ', $seo_structure_bits);
    }

    if ($seo_summary['not_calculated'] > 0) {
        $seo_summary_parts[] =
            '<a class="link-secondary" href="view_pages.php?filter=seo_not_calculated">'
            . lang(array('string' => '{var:1} not calculated yet', 'vars' => (int) $seo_summary['not_calculated']))
            . '</a>';
    }

    $output_seo_summary =
        '<div class="col-12 mb-2 small text-body-secondary">
            <span class="bi bi-graph-up-arrow me-1"></span>' . implode(' &nbsp;|&nbsp; ', $seo_summary_parts) . '
        </div>';
}

$output_delete_selected_button = '';

// if the user is at least a manager or has access to delete pages, then output the delete selected button
if (($user['role'] < '3') || ($user['delete_pages'] == TRUE)) {
    $output_delete_selected_button = '<button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-danger disabled" data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('pages')))) . '"><span class="material-icons me-2">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>';
}

echo
pg_page_shell(
    array(
        'title'=> lang('Pages'),
        'extra classes'=>'page',
        'icon'=>'page', 
        'heading'=>lang('Pages'),
        
    )
) . '
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            <div class="row mb-2  flex-wrap">
                <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
                    <h2 class="d-inline-block " data-bs-content="' . $subheading . '" title="' . $heading . '">' . $heading . '</h2>
                    ' . $output_button_bar . '
                </div>
                <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
                    <div class="row justify-content-center justify-content-md-end">
                        <form id="search_form" action="view_pages.php" method="get" class="search_form col-auto">
                            <input type="hidden" name="filter" value="' . h($filter) . '" />
                            <div class="input-group input-group-sm">
                                <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Content that viewed') . '" for="filter_select">visibility</label>
                                <select id="filter_select" name="filter" class="form-select mt-1 mb-1" title="' . lang('Content that viewed') . '" onchange="submit_form(\'search_form\')">' . get_filter_options($filters_in_array, $filter) . '</select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="row">
                ' . $output_seo_summary . '
            </div>
            <div class="card my-4">
                <div class="card-body p-0 position-relative">
                    <form name="form"  action="edit_pages.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="action" />
                        <input type="hidden" name="move_to_folder" />
                        <input type="hidden" name="edit_page_style" />
                        <input type="hidden" name="edit_mobile_style_id" />
                        <input type="hidden" name="edit_site_search" />
                        <input type="hidden" name="edit_sitemap" />
                        <input type="hidden" name="send_to" value="' . h(get_request_uri()) . '" />
                        <table class="chart table-hover table " style="width:100%;display:none">
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all">
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' . get_column_heading($output_name_heading, ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    ' . $output_form_name_heading . '
                                    ' . $output_form_enabled_heading . '
                                    <th>' . get_column_heading(lang('Folder'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    <th>' . $output_style_column_heading . '' . $output_device_type_toggle . '</th>
                                    <th>' . get_column_heading(lang('Page Type'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    <th  style="text-align: center;">' . get_column_heading(lang('Searchable'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    <th style="text-align: center;">' . get_column_heading(lang('Comments'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    <th style="text-align: center;">' . get_column_heading(lang('Site Map'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    <th style="text-align: center;">' . get_column_heading(lang('SEO'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    <th style="text-align: center;" title="' . h(lang('How much a weak score costs, weighed against how many people see the page')) . '">' . get_column_heading(lang('Impact'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['pages']['sort'] ?? ''), ($_SESSION['software']['pages']['order'] ?? ''), $output_filter_for_links) . '</th>
                                </tr>
                            </thead>
                            <tbody>
                                ' . $output_rows . '
                            </tbody>
                        </table>

                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">                                   
                                    <button type="button" value="Modify Selected" class=" btn mb-1 mt-1 btn-primary disabled" onclick="window.open(\'edit_pages.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\'); edit_chart_content(\'edit\',\'product\')"><span class="material-icons me-2">edit</span>' . lang(array('string'=>'Modify Selected') ) . '</button>
                                    ' . $output_delete_selected_button . '
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </div>
    ' . pg_seo_render_detail_offcanvas() . '
</main>' .
output_footer();

$liveform->remove_form('view_pages');