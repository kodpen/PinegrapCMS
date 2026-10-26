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
// This feature can take a long time to run for a large site,
// so increase the allowed execution time for the PHP script.
ini_set('max_execution_time', '9999');

// This script is responsible for looping through necessary pages and files and storing the URL
// and plain text content for each item in the database for full-text searching.

// Set constant to take note that this script is updating the search index,
// so that in other code in other files it knows to do special things.
// For example, we don't want the initialize_user() function to see remember me
// data in a cookie for the visitor that is running this script and try to add info to the session,
// because we don't want the rest of this script to run as any specific user.
define('UPDATE_SEARCH_INDEX', true);

include('init.php');

require_once(dirname(__FILE__) . '/get_page_content.php');

// init.php only defines this when there is an HTTP host. Run from the command
// line it is missing, and a custom form page reads it for its send_to field:
// in PHP 8 that is a thrown Error, so the first form page in the index ended
// the whole run. The field is a hidden input, which never reaches the indexed
// text. Same fallback as seo_analyze_job.php.
if (!defined('REQUEST_URL')) {
    define('REQUEST_URL', defined('PATH') ? PATH : '/');
}

// If this script is being run from a web browser (i.e. not a cron job),
// then record login info from session for authentication later, and then unset session,
// so that the rest of the script does not run as this user, so that content for pages and etc.
// that will be stored in search index is generic and not specific to this user.
// Also, verify that user has access to run this.
if (isset($_SERVER['HTTP_HOST']) == true) {
    $username = $_SESSION['sessionusername'] ?? '';
    $userid = $_SESSION['sessionuserid'] ?? '';

    // This command allows us to purely unset the session variables,
    // without destroying the actual saved session data.  This is important,
    // because when a user is done running this script, we don't want them
    // to be logged out of the system.
    unset($_SESSION);

    // Try to find a user with the same login info and has a role of manager or above.
    // The session id is trusted; just confirm it is a manager-or-above account.
    $user_id = db_value(
        "SELECT user_id FROM user
        WHERE (user_id = '" . (int) $userid . "') AND (user_role < '3')");

    // If a user was not found, then output error.
    if ($user_id == '') {
        log_activity(lang('access denied to update search index'), $username);
        output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }
}

// The general job files whatever a script it dispatched printed in the site
// log, as "printed a message while running with the general job", because
// that is how a job reports its own missing settings. This progress page is
// not a message, and it was filed every day. It is discarded at the end of a
// dispatched run instead; an error screen printed on the way still ends the
// run inside this buffer and reaches the site log as before.
if (defined('PG_CRON_DISPATCH')) {
    ob_start();
}

echo
output_header_secure(array(
    'title' => lang('Update Search Index'),
    'icon' => 'page'
)) . '
    <script type="text/javascript">
        $(document).ready(function() {
            $("html, body").animate({ scrollTop: $(document).height()  });
            $("#start").hide();
            $("#finish").show();
        });
    </script>

    <nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
        <div class="container-fluid gap-3">
            <span id="start" class="navbar-text d-flex align-items-center gap-3 small">
                <span class="spinner-border spinner-border-sm fg-success flex-shrink-0" role="status"></span>
                ' . lang('Spidering all Pages for text and files and updating the Search Index. Please leave this window open until it is complete') . '...
            </span>
            
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="Close">
                        <span class="bi bi-x-lg"></span>
                    </button>
                </li>
            </ul>
        </div>
    </nav>
    <main id="content" class="container py-3">
        <ul class="list-group list-group-flush">';
// Store the current timestamp.  We will use this is order to know which search items are old and can be deleted.
$timestamp = time();

// Create array that we will use to store every item name that is linked in the HTML for all pages
// that are included in the search index.  This will allow us to determine which files should be included in index.
$linked_item_names = array();

// Create an array that will hold all added calendar event view URLs so that we don't add duplicates.
// Different calendar views might link to the same calendar event view, so that is why this is necessary.
$calendar_event_view_urls = array();

// Create an array that will hold all added form item view URLs so that we don't add duplicates.
// Different form list views might link to the same form item view, so that is why this is necessary.
$form_item_view_urls = array();

// The pages built in the visual editor that carry record lists, collected in
// the page loop: array('page' => page row, 'lists' => list widget configs).
$designer_hosts = array();

// if calendars enabled than output search properties else do not include them because error while update search index.
$sql_where = "";
if(defined('CALENDARS') && CALENDARS == 1){
    $sql_where .= "OR (page.page_type = 'calendar view')";
}
// if ecommerce enabled than output search properties else do not include them because error while update search index.
if(defined('ECOMMERCE') && ECOMMERCE == 1){
    $sql_where .= "OR (page.page_type = 'catalog')
            OR (page.page_type = 'express order')
            OR (page.page_type = 'order form')
            OR (page.page_type = 'shopping cart')";
}

// Get pages that should be included in search index.
// We removed email preferences page types because they caused an error
// and we did not want to spend time to resolve error.

$pages = db_items(
    "SELECT
        page.page_id AS id,
        page.page_name AS name,
        page.page_folder AS folder_id,
        page.page_title AS title,
        page.page_meta_description AS meta_description,
        page.page_search_keywords AS search_keywords,
        page.page_type AS type
    FROM page
    LEFT JOIN folder ON page.page_folder = folder.folder_id
    WHERE
        (page.page_search = '1')
        AND (folder.folder_archived = '0')
        AND
        (
            (page.page_type = 'standard')
            OR (page.page_type = 'change password')
            OR (page.page_type = 'folder view')
            OR (page.page_type = 'forgot password')
            OR (page.page_type = 'login')
            OR (page.page_type = 'logout')
            OR (page.page_type = 'photo gallery')
            OR (page.page_type = 'search results')
            OR (page.page_type = 'registration entrance')
            OR (page.page_type = 'membership entrance')
            OR (page.page_type = 'my account')
            OR (page.page_type = 'my account profile')
            OR (page.page_type = 'update address book')
            OR (page.page_type = 'custom form')
            OR (page.page_type = 'form list view')
            OR (page.page_type = 'form view directory')
            $sql_where
            OR (page.page_type = 'affiliate sign up form')
        )");


$count_pages = 1;

// Loop through pages in order to store content in database for searching.
foreach ($pages as $page) {

    $url = encode_url_path($page['name']);

    // A page built in the visual editor carries its record lists and record
    // views as system widgets. Its lists are kept for the record items
    // written after this loop. A record page drawn without ?r= shows its
    // not-found state, so it is not an item itself; its records are.
    if ($page['type'] == 'standard') {
        $designer_lists = array();
        $designer_record_page = false;

        foreach (pg_sw_page_widgets($page['id']) as $widget) {
            $widget_type = isset($widget['cfg']['regionType']) ? (string) $widget['cfg']['regionType'] : '';

            if (in_array($widget_type, pg_sw_form_list_types(), true)) {
                $designer_lists[] = $widget['cfg'];
            } elseif ($widget_type === 'form_item_view') {
                $designer_record_page = true;
            }
        }

        if ($designer_lists) {
            $designer_hosts[] = array('page' => $page, 'lists' => $designer_lists);
        }

        if ($designer_record_page) {
            continue;
        }
    }

    // Get HTML version of page.
    $content = get_page_content($page['id'], $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false);

    // Updated linked item names so that later we know which files to include in index.
    $linked_item_names = update_linked_item_names($content, $linked_item_names);

    // Start the promoted keywords off with the content that the editor might have
    // entered into the "Promote on Keyword" page property.
    $promoted_keywords = $page['search_keywords'];

    // Check for more keywords that might appear inside HTML tags with
    // "promote-on-keyword" class.
    $more_promoted_keywords = get_promoted_keywords($content);

    // If more keywords were found, then add them to the promoted keywords.
    if ($more_promoted_keywords != '') {

        // If there are already keywords from the page property,
        // then add a comma and space for separation.
        if ($promoted_keywords != '') {
            $promoted_keywords .= ', ';
        }

        $promoted_keywords .= $more_promoted_keywords;
    }

    // Get plain text version of page.
    $content = convert_html_to_text($content);

    // If the page title is blank, then use the name for the title.
    if ($page['title'] == '') {
        $title = $page['name'];

    // Otherwise use the title.
    } else {
        $title = $page['title'];
    }

    // Add title, meta description, and the promote on keyword terms to content
    // so that there is more data included in index to get better results
    // and so that we can simply search the one content index instead of
    // searching the title, description, and content indexes.
    $content = $title . ' ' . $page['meta_description'] . ' ' . $page['search_keywords'] . ' ' . $content;

    // The topics given to the page's search sections are searched with its
    // text; nothing on the page shows them.
    if (function_exists('pg_search_section_topics') && function_exists('pg_page_tree_json')) {
        $section_topics = pg_search_section_topics(json_decode(pg_page_tree_json($page['id']), true));
        if ($section_topics !== '') $content .= ' ' . $section_topics;
    }

    // Check if a search item already exists for this URL.
    $number_of_items = db_value("SELECT COUNT(*) FROM search_items WHERE url = '" . escape($url) . "'");

    // If a search item already exists, then update it.
    if ($number_of_items == 1) {
        db(
            "UPDATE search_items
            SET
                url = '" . escape($url) . "',
                content = '" . escape($content) . "',
                title = '" . escape($title) . "',
                description = '" . escape($page['meta_description']) . "',
                keywords = '" . e($promoted_keywords) . "',
                page_id = '" . $page['id'] . "',
                folder_id = '" . $page['folder_id'] . "',
                timestamp = '" . $timestamp . "'
            WHERE url = '" . escape($url) . "'");

    // Otherwise a search item does not already exist, so insert it.
    } else {
        db(
            "INSERT INTO search_items (
                url,
                content,
                title,
                description,
                keywords,
                page_id,
                folder_id,
                timestamp)
            VALUES (
                '" . escape($url) . "',
                '" . escape($content) . "',
                '" . escape($title) . "',
                '" . escape($page['meta_description']) . "',
                '" . e($promoted_keywords) . "',
                '" . $page['id'] . "',
                '" . $page['folder_id'] . "',
                '" . $timestamp . "')");
    }

    echo
        '<li class="list-group-item px-3 py-3">
            <div class="d-flex align-items-start gap-3">
                <small class="fg-secondary flex-shrink-0 text-end mt-1" style="min-width:22px;font-size:10px">' . $count_pages++ . '</small>
                <div class="flex-fill overflow-hidden">
                    <a href="' . h(URL_SCHEME . HOSTNAME . PATH . $url) . '" target="_blank" class="fw-semibold text-truncate d-block" style="font-size:13px">' . h($title) . '</a>
                    <div class="fg-secondary text-truncate" style="font-size:11px">' . h($url) . ($page['meta_description'] ? ' &middot; ' . h($page['meta_description']) : '') . '</div>
                </div>
                <span class="badge text-bg-light border flex-shrink-0" style="font-size:9px">' . lang('Page') . '</span>
            </div>
        </li>';

    // Do additional things depending on the type of page.
    switch ($page['type']) {
        case 'calendar view':
            
            // Get calendars that are allowed for this calendar view page.
            $calendar_ids = db_values("SELECT calendar_id FROM calendar_views_calendars_xref WHERE page_id = '" . $page['id'] . "'");
            
            // If there is at least one calendar allowed for this calendar view page, then continue.
            if (count($calendar_ids) > 0) {
                // Get calendar event view page info for this calendar view page
                $query =
                    "SELECT
                        calendar_event_view_page.page_id AS calendar_event_view_page_id,
                        calendar_event_view_page.page_folder AS calendar_event_view_folder_id,
                        calendar_event_view_page.page_name AS calendar_event_view_page_name,
                        calendar_event_view_page.page_search AS calendar_event_view_search,
                        calendar_event_view_page.page_title AS calendar_event_view_title,
                        calendar_event_view_page.page_meta_description AS calendar_event_view_meta_description,
                        calendar_event_view_page.page_search_keywords AS calendar_event_view_search_keywords,
                        calendar_event_view_folder.folder_archived AS calendar_event_view_archived
                    FROM calendar_view_pages
                    LEFT JOIN page AS calendar_event_view_page ON calendar_view_pages.calendar_event_view_page_id = calendar_event_view_page.page_id
                    LEFT JOIN folder AS calendar_event_view_folder ON calendar_event_view_page.page_folder = calendar_event_view_folder.folder_id
                    WHERE calendar_view_pages.page_id = '" . $page['id'] . "'";
                $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                $row = mysqli_fetch_assoc($result);
                
                $calendar_event_view_page_id = $row['calendar_event_view_page_id'];
                $calendar_event_view_folder_id = $row['calendar_event_view_folder_id'];
                $calendar_event_view_page_name = $row['calendar_event_view_page_name'];
                $calendar_event_view_search = $row['calendar_event_view_search'];
                $calendar_event_view_title = $row['calendar_event_view_title'];
                $calendar_event_view_meta_description = $row['calendar_event_view_meta_description'];
                $calendar_event_view_search_keywords = $row['calendar_event_view_search_keywords'];
                $calendar_event_view_archived = $row['calendar_event_view_archived'];

                // If the calendar event view, that this calendar view links to,
                // exists and is included in search and is not archived,
                // then continue to get search items for it.
                if (
                    ($calendar_event_view_page_id != '')
                    && ($calendar_event_view_search == 1)
                    && ($calendar_event_view_archived == 0)
                ) {
                    $sql_calendar_condition = "";
                    
                    // Loop through the calendar ID's in order to prepare SQL condition.
                    foreach ($calendar_ids as $calendar_id) {
                        // If the SQL condition is not blank, then add OR separator.
                        if ($sql_calendar_condition != '') {
                            $sql_calendar_condition .= " OR ";
                        }
                        
                        $sql_calendar_condition .= "(calendar_events_calendars_xref.calendar_id = '" . $calendar_id . "')";
                    }
                    
                    // Get all calendar events for this calendar view page.
                    $calendar_events = db_items(
                        "SELECT
                            DISTINCT(calendar_events.id),
                            calendar_events.name,
                            calendar_events.short_description
                        FROM calendar_events_calendars_xref
                        LEFT JOIN calendar_events ON calendar_events_calendars_xref.calendar_event_id = calendar_events.id
                        WHERE
                            (calendar_events.published = '1')
                            AND ($sql_calendar_condition)");
                    
                    // If there is at least one calendar event, then prepare search items.
                    if (count($calendar_events) > 0) {

                        $path = encode_url_path($calendar_event_view_page_name);
                        
                        // Loop through the calendar events in order to add search items to the database.
                        foreach ($calendar_events as $calendar_event) {
                            $url = $path . '?id=' . $calendar_event['id'];

                            // If this URL has not already been added, then add search item.
                            // Multiple calendar views can link to the same calendar event on the same calendar event view,
                            // so that is why this check is necessary.
                            if (in_array($url, $calendar_event_view_urls) == false) {
                                // Get HTML version of page.
                                $content = get_page_content($calendar_event_view_page_id, $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, array('calendar_event_id' => $calendar_event['id']));

                                // Updated linked item names so that later we know which files to include in index.
                                $linked_item_names = update_linked_item_names($content, $linked_item_names);

                                // Start the promoted keywords off with the content that the editor might have
                                // entered into the "Promote on Keyword" page property.
                                $promoted_keywords = $calendar_event_view_search_keywords;

                                // Check for more keywords that might appear inside HTML tags with
                                // "promote-on-keyword" class.
                                $more_promoted_keywords = get_promoted_keywords($content);

                                // If more keywords were found, then add them to the promoted keywords.
                                if ($more_promoted_keywords != '') {

                                    // If there are already keywords from the page property,
                                    // then add a comma and space for separation.
                                    if ($promoted_keywords != '') {
                                        $promoted_keywords .= ', ';
                                    }
                                    
                                    $promoted_keywords .= $more_promoted_keywords;

                                }

                                // Get plain text version of page.
                                $content = convert_html_to_text($content);

                                // Add title, meta description, calendar event short description, and the
                                // promote on keyword terms to content
                                // so that there is more data included in index to get better results
                                // and so that we can simply search the one content index instead of
                                // searching the title, description, and content indexes.
                                // We don't have to include the calendar name because that should already exist in the content.
                                $content = $calendar_event_view_title . ' ' . $calendar_event_view_meta_description . ' ' . $calendar_event['short_description'] . ' ' . $calendar_event_view_search_keywords . ' ' . $content;

                                // Check if a search item already exists for this URL.
                                $number_of_items = db_value("SELECT COUNT(*) FROM search_items WHERE url = '" . escape($url) . "'");

                                // If a search item already exists, then update it.
                                if ($number_of_items == 1) {
                                    db(
                                        "UPDATE search_items
                                        SET
                                            url = '" . escape($url) . "',
                                            content = '" . escape($content) . "',
                                            title = '" . escape($calendar_event['name']) . "',
                                            description = '" . escape($calendar_event['short_description']) . "',
                                            keywords = '" . e($promoted_keywords) . "',
                                            page_id = '" . $page['id'] . "',
                                            folder_id = '$calendar_event_view_folder_id',
                                            timestamp = '" . $timestamp . "'
                                        WHERE url = '" . escape($url) . "'");

                                // Otherwise a search item does not already exist, so insert it.
                                } else {
                                    db(
                                        "INSERT INTO search_items (
                                            url,
                                            content,
                                            title,
                                            description,
                                            keywords,
                                            page_id,
                                            folder_id,
                                            timestamp)
                                        VALUES (
                                            '" . escape($url) . "',
                                            '" . escape($content) . "',
                                            '" . escape($calendar_event['name']) . "',
                                            '" . escape($calendar_event['short_description']) . "',
                                            '" . e($promoted_keywords) . "',
                                            '" . $page['id'] . "',
                                            '$calendar_event_view_folder_id',
                                            '" . $timestamp . "')");
                                }

                                // Remember that we have added this URL.
                                $calendar_event_view_urls[] = $url;

                                
                                echo
                                    '<li class="list-group-item px-3 py-3">
                                        <div class="d-flex align-items-start gap-3">
                                            <small class="fg-secondary flex-shrink-0 text-end mt-1" style="min-width:22px;font-size:10px">' . $count_pages++ . '</small>
                                            <div class="flex-fill overflow-hidden">
                                                <a href="' . h(URL_SCHEME . HOSTNAME . PATH . $url) . '" target="_blank" class="fw-semibold text-truncate d-block" style="font-size:13px">' . h($calendar_event['name']) . '</a>
                                                <div class="fg-secondary text-truncate" style="font-size:11px">' . h($url) . ($calendar_event['short_description'] ? ' &middot; ' . h($calendar_event['short_description']) : '') . '</div>
                                            </div>
                                            <span class="badge text-bg-light border flex-shrink-0" style="font-size:9px">' . lang('Event') . '</span>
                                        </div>
                                    </li>';
                            }
                        }
                    }
                }
            }

            break;

        // If this is a form list view them store a search item for every
        // submitted form on a form item view page for this form list view.
        case 'form list view':
            // Get info about custom form and form item view for this page.
            $query =
                "SELECT
                    form_list_view_pages.custom_form_page_id,
                    form_list_view_pages.viewer_filter,
                    form_item_view_page.page_id AS form_item_view_page_id,
                    form_item_view_page.page_folder AS form_item_view_folder_id,
                    form_item_view_page.page_name AS form_item_view_page_name,
                    form_item_view_page.page_search AS form_item_view_search,
                    form_item_view_page.page_title AS form_item_view_title,
                    form_item_view_page.page_meta_description AS form_item_view_meta_description,
                    form_item_view_page.page_search_keywords AS form_item_view_search_keywords,
                    form_item_view_folder.folder_archived AS form_item_view_archived,
                    form_item_view_page_properties.submitter_security AS form_item_view_submitter_security
                FROM form_list_view_pages
                LEFT JOIN page AS form_item_view_page ON form_list_view_pages.form_item_view_page_id = form_item_view_page.page_id
                LEFT JOIN form_item_view_pages AS form_item_view_page_properties ON
                    (form_item_view_page.page_id = form_item_view_page_properties.page_id)
                    AND (form_item_view_page_properties.collection = 'a')
                LEFT JOIN folder AS form_item_view_folder ON form_item_view_page.page_folder = form_item_view_folder.folder_id
                WHERE
                    (form_list_view_pages.page_id = '" . $page['id'] . "')
                    AND (form_list_view_pages.collection = 'a')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            
            $custom_form_page_id = $row['custom_form_page_id'];
            $viewer_filter = $row['viewer_filter'];
            $form_item_view_page_id = $row['form_item_view_page_id'];
            $form_item_view_folder_id = $row['form_item_view_folder_id'];
            $form_item_view_page_name = $row['form_item_view_page_name'];
            $form_item_view_search = $row['form_item_view_search'];
            $form_item_view_title = $row['form_item_view_title'];
            $form_item_view_meta_description = $row['form_item_view_meta_description'];
            $form_item_view_search_keywords = $row['form_item_view_search_keywords'];
            $form_item_view_archived = $row['form_item_view_archived'];
            $form_item_view_submitter_security = $row['form_item_view_submitter_security'];

            // If viewer filter is disabled for form list view, and the form item view,
            // that this form list view links to, exists, and is included in search,
            // and is not archived, and where submitter security is disabled
            // then continue to get search items for it.
            if (
                ($viewer_filter == 0)
                && ($form_item_view_page_id != '')
                && ($form_item_view_search == 1)
                && ($form_item_view_archived == 0)
                && ($form_item_view_submitter_security == 0)
            ) {
                $standard_fields = get_standard_fields_for_view();

                $custom_fields = db_items(
                    "SELECT
                        id,
                        name,
                        type,
                        multiple,
                        wysiwyg,
                        size,
                        maxlength
                    FROM form_fields
                    WHERE page_id = '$custom_form_page_id'");

                // Prepare to get description for each submitted form.  We only look for one description field,
                // so that is why we prepare sub-query for it, whereas for title, we look for multiple title fields,
                // so for title we hold off until later to get that info.

                $sql_select_description = "";

                // Get info about description field, if one exists, so we know how to select it.
                $description_field = db_item(
                    "SELECT
                        id,
                        wysiwyg
                    FROM form_fields
                    WHERE
                        (page_id = '$custom_form_page_id')
                        AND (rss_field = 'description')");

                // If there is a description field, then select it.
                // db_item() returns null when the form has none.
                if (($description_field['id'] ?? '') != '') {
                    $sql_select_description = "(SELECT form_data.data FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '" . $description_field['id'] . "') LIMIT 1) AS description,";
                }

                // Assume that we don't need to join various tables until we find out otherwise.
                $submitter_join_required = FALSE;
                $last_modifier_join_required = FALSE;
                $submitted_form_info_join_required = FALSE;
                $newest_comment_join_required = FALSE;

                $sql_where = "";

                // We order by form_field_id ASC in order to get filters for standard
                // fields first, in order to improve performance.
                $filters = db_items(
                    "SELECT
                        form_field_id,
                        standard_field,
                        operator,
                        value,
                        dynamic_value,
                        dynamic_value_attribute
                    FROM form_list_view_filters
                    WHERE page_id = '" . $page['id'] . "'
                    ORDER BY form_field_id ASC");

                // Loop through all filters in order to prepare SQL.
                foreach ($filters as $filter) {
                    // get operand 1
                    $operand_1 = '';
                    
                    // if a standard field was selected for filter
                    if ($filter['standard_field'] != '') {
                        // determine if a join is required based on the field
                        switch ($filter['standard_field']) {
                            case 'submitter':
                                $submitter_join_required = TRUE;
                                break;

                            case 'last_modifier':
                                $last_modifier_join_required = TRUE;
                                break;

                            case 'number_of_views':
                            case 'number_of_comments':
                            case 'comment_attachments':
                                $submitted_form_info_join_required = TRUE;
                                break;

                            case 'newest_comment_name':
                            case 'newest_comment':
                            case 'newest_comment_date_and_time':
                            case 'newest_comment_id':
                            case 'newest_activity_date_and_time':
                                $submitted_form_info_join_required = TRUE;
                                $newest_comment_join_required = TRUE;
                                break;
                        }

                        // get operand 1 which is sql name by looping through standard fields
                        foreach ($standard_fields as $standard_field) {
                            // if this is the standard field that was selected for this filter, then set operand 1 and break out of loop
                            if ($standard_field['value'] == $filter['standard_field']) {
                                $operand_1 = $standard_field['sql_name'];
                                break;
                            }
                        }
                        
                    // else a custom field was selected for the filter
                    } else {
                        // Assume that the custom field for this filter is not valid, until we find out otherwise.
                        // A custom field for a filter is valid if the custom field exists on the custom form for this form list view.
                        $custom_field_is_valid = FALSE;
                        
                        // get field type and determine if field is valid by looping through custom fields
                        foreach ($custom_fields as $custom_field) {
                            // if this is the custom field that was selected for this filter,
                            // then we know the custom field is valid, so break out of loop
                            if ($custom_field['id'] == $filter['form_field_id']) {
                                $field_type = $custom_field['type'];
                                $field_multiple = $custom_field['multiple'];
                                $custom_field_is_valid = TRUE;
                                break;
                            }
                        }
                        
                        // If the custom field for this filter is not valid, then do not prepare SQL filter and skip to the next filter.
                        // We have to do this in order to prevent a database error.
                        if ($custom_field_is_valid == FALSE) {
                            continue;
                        }

                        // If this custom field is a pick list and allow multiple selection is enabled,
                        // or if this custom field is a check box, then prepare operand in a certain way,
                        // because there might be multiple values.
                        if (
                            (
                                ($field_type == 'pick list')
                                && ($field_multiple == 1)
                            )
                            || ($field_type == 'check box')
                        ) {
                            // If the operator is "is equal to" or "is not equal to" then prepare special filter in a way
                            // so that it looks at individual values instead of a string of grouped values.
                            if (
                                ($filter['operator'] == 'is equal to')
                                || ($filter['operator'] == 'is not equal to')
                            ) {
                                $operand_1 = "(SELECT COUNT(*) FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '" . $filter['form_field_id'] . "') AND (form_data.data = '" . escape($filter['value']) . "'))";

                                // If the operator is "is equal to", then change operator to "is greater than",
                                // because of the special comparison we are using.
                                if ($filter['operator'] == 'is equal to') {
                                    $filter['operator'] = 'is greater than';

                                // Otherwise the operator is "is not equal to", so change operator to "is equal to",
                                // because of the special comparison we are using.
                                } else {
                                    $filter['operator'] = 'is equal to';
                                }

                                // Change the filter value to 0 in order for our special comparison to work.
                                $filter['value'] = '0';

                            // Otherwise the operator is a different operator, so prepare operand 1,
                            // so that multiple values are grouped into one concatenated string, separated by commas.
                            } else {
                                $operand_1 = "(SELECT GROUP_CONCAT(form_data.data ORDER BY form_data.id ASC SEPARATOR ', ') FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '" . $filter['form_field_id'] . "'))";
                            }

                        // else if this custom field is a file upload field, then prepare operand so it contains file name
                        } else if ($field_type == 'file upload') {
                            $operand_1 = "(SELECT files.name FROM form_data LEFT JOIN files ON form_data.file_id = files.id WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '" . $filter['form_field_id'] . "') LIMIT 1)";

                        // else this custom field has any other type, so prepare operand in the default way
                        } else {
                            $operand_1 = "(SELECT form_data.data FROM form_data WHERE (form_data.form_id = forms.id) AND (form_data.form_field_id = '" . $filter['form_field_id'] . "') LIMIT 1)";
                        }
                    }
                    
                    // if a basic value was entered, use that value
                    if ($filter['value'] != '') {
                        $operand_2 = $filter['value'];
                        
                    // else a dynamic value was entered, so use dynamic value
                    } else {
                        $operand_2 = get_dynamic_value($filter['dynamic_value'], $filter['dynamic_value_attribute']);
                    }
                    
                    $sql_where .= " AND (" . prepare_sql_operation($filter['operator'], $operand_1, $operand_2) . ")";
                }

                $sql_join = "";

                // if a submitter join is required, then add join
                if ($submitter_join_required == TRUE) {
                    $sql_join .= "LEFT JOIN user AS submitter ON forms.user_id = submitter.user_id\n";
                }

                // if a last modifier join is required, then add join
                if ($last_modifier_join_required == TRUE) {
                    $sql_join .= "LEFT JOIN user AS last_modifier ON forms.last_modified_user_id = last_modifier.user_id\n";
                }

                // if a submitted form comment info join is required, then add join
                if ($submitted_form_info_join_required == TRUE) {
                    $sql_join .= "LEFT JOIN submitted_form_info ON ((forms.id = submitted_form_info.submitted_form_id) AND (submitted_form_info.page_id = '" . $form_item_view_page_id . "'))\n";
                }

                // if a newest comment join is required, then add join
                if ($newest_comment_join_required == TRUE) {
                    $sql_join .= "LEFT JOIN comments AS newest_comment ON submitted_form_info.newest_comment_id = newest_comment.id\n";
                }

                // Get submitted forms.
                $submitted_forms = db_items(
                    "SELECT
                        forms.id,
                        $sql_select_description
                        forms.reference_code,
                        forms.address_name
                    FROM forms
                    $sql_join
                    WHERE
                        (forms.page_id = '$custom_form_page_id')
                        $sql_where");

                // If there is at least one submitted form, then prepare search items for them.
                if (count($submitted_forms) > 0) {
                    $pretty_urls = check_if_pretty_urls_are_enabled($custom_form_page_id);

                    // If pretty URLs are enabled, then prepare pretty path.
                    if ($pretty_urls == true) {
                        $pretty_path = encode_url_path($page['name']) . '/';
                    }

                    // We still need to prepare ugly path even if pretty URLs are enabled,
                    // in case a submitted form does not have an address name.

                    $ugly_path = encode_url_path($form_item_view_page_name);
                    
                    // Loop through the submitted forms in order to add search items to the database.
                    foreach ($submitted_forms as $submitted_form) {
                        // If pretty URLs are enabled for the custom form, and this submitted form has
                        // an address name, then prepare pretty URL.
                        if (($pretty_urls == true) && ($submitted_form['address_name'] != '')) {
                            $url = $pretty_path . $submitted_form['address_name'];

                        // Otherwise, prepare ugly URL.
                        } else {
                            $url = $ugly_path . '?r=' . $submitted_form['reference_code'];
                        }

                        // If this URL has not already been added, then add search item.
                        // Multiple form list views can link to the same submitted form on the same form item view,
                        // so that is why this check is necessary.
                        if (in_array($url, $form_item_view_urls) == false) {
                            // Get HTML version of page.
                            $content = get_page_content($form_item_view_page_id, $system_content = '', $extra_system_content = '', $mode = 'preview', $email = false, array('form_id' => $submitted_form['id']));

                            // Updated linked item names so that later we know which files to include in index.
                            $linked_item_names = update_linked_item_names($content, $linked_item_names);

                            // Start the promoted keywords off with the content that the editor might have
                            // entered into the "Promote on Keyword" page property.
                            $promoted_keywords = $form_item_view_search_keywords;

                            // Check for more keywords that might appear inside HTML tags with
                            // "promote-on-keyword" class.
                            $more_promoted_keywords = get_promoted_keywords($content);

                            // If more keywords were found, then add them to the promoted keywords.
                            if ($more_promoted_keywords != '') {

                                // If there are already keywords from the page property,
                                // then add a comma and space for separation.
                                if ($promoted_keywords != '') {
                                    $promoted_keywords .= ', ';
                                }
                                
                                $promoted_keywords .= $more_promoted_keywords;

                            }

                            // Get plain text version of page.
                            $content = convert_html_to_text($content);

                            // Get title values for this submitted form.  The title field is the one where the RSS title
                            // setting is enabled for the field.  There can be multiple title fields (e.g. first name, last name).
                            $titles = db_values(
                                "SELECT form_data.data
                                FROM form_data
                                LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
                                WHERE
                                    (form_data.form_id = '" . $submitted_form['id'] . "')
                                    AND (form_fields.rss_field = 'title')
                                ORDER BY form_fields.sort_order ASC");

                            $combined_title = '';

                            // Loop through the title values, in order to prepare the combined title.
                            foreach ($titles as $title) {
                                // If the title is not blank, then add it to combined title.
                                if ($title != '') {
                                    // If this is not the first title then add a space for separation.
                                    if ($combined_title != '') {
                                        $combined_title .= ' ';
                                    }

                                    $combined_title .= $title;
                                }
                            }

                            // Prepare the description.  We only use one description field, unlike title.

                            // Not selected at all when the form has no description field.
                            $description = trim($submitted_form['description'] ?? '');

                            // If there is a description, then prepare description for storage.
                            if ($description != '') {
                                // If the description field from the custom form is a rich-text editor field,
                                // then convert description to plain text.
                                if ($description_field['wysiwyg']) {
                                    $description = trim(convert_html_to_text($description));
                                }

                                // If the description is greater than 255 characters then shorten it.
                                if (mb_strlen($description) > 255) {
                                    $description = mb_substr($description, 0, 255) . '...';
                                }
                            }

                            // Add page title, submitted form combined title, meta description,
                            // submitted form description, and the promote on keyword terms to content
                            // so that there is more data included in index to get better results
                            // and so that we can simply search the one content index instead of
                            // searching the title, description, and content indexes.
                            // We include the submitte form combined title and description, because
                            // those do not necessarily already appear in the content.
                            $content = $form_item_view_title . ' ' . $combined_title . ' ' . $form_item_view_meta_description . ' ' . $description . ' ' . $form_item_view_search_keywords . ' ' . $content;

                            // Check if a search item already exists for this URL.
                            $number_of_items = db_value("SELECT COUNT(*) FROM search_items WHERE url = '" . escape($url) . "'");

                            // If a search item already exists, then update it.
                            if ($number_of_items == 1) {
                                db(
                                    "UPDATE search_items
                                    SET
                                        url = '" . escape($url) . "',
                                        content = '" . escape($content) . "',
                                        title = '" . escape($combined_title) . "',
                                        description = '" . escape($description) . "',
                                        keywords = '" . e($promoted_keywords) . "',
                                        page_id = '" . $page['id'] . "',
                                        folder_id = '$form_item_view_folder_id',
                                        timestamp = '" . $timestamp . "'
                                    WHERE url = '" . escape($url) . "'");

                            // Otherwise a search item does not already exist, so insert it.
                            } else {
                                db(
                                    "INSERT INTO search_items (
                                        url,
                                        content,
                                        title,
                                        description,
                                        keywords,
                                        page_id,
                                        folder_id,
                                        timestamp)
                                    VALUES (
                                        '" . escape($url) . "',
                                        '" . escape($content) . "',
                                        '" . escape($combined_title) . "',
                                        '" . escape($description) . "',
                                        '" . e($promoted_keywords) . "',
                                        '" . $page['id'] . "',
                                        '$form_item_view_folder_id',
                                        '" . $timestamp . "')");
                            }

                            // Remember that we have added this URL.
                            $form_item_view_urls[] = $url;

                            echo
                                '<li class="list-group-item px-3 py-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <small class="fg-secondary flex-shrink-0 text-end mt-1" style="min-width:22px;font-size:10px">' . $count_pages++ . '</small>
                                        <div class="flex-fill overflow-hidden">
                                            <a href="' . h(URL_SCHEME . HOSTNAME . PATH . $url) . '" target="_blank" class="fw-semibold text-truncate d-block" style="font-size:13px">' . ($combined_title ? h($combined_title) : h($url)) . '</a>
                                            <div class="fg-secondary text-truncate" style="font-size:11px">' . h($url) . ($description ? ' &middot; ' . h($description) : '') . '</div>
                                        </div>
                                        <span class="badge text-bg-light border flex-shrink-0" style="font-size:9px">' . lang('Form') . '</span>
                                    </div>
                                </li>';
                        }
                    }
                }
            }

            break;
    }
}

// The records of the lists on pages built in the visual editor, one item per
// record at its address on the list's detail page, as the sitemap lists
// them (get_sitemap_info.php): the list's filters and the record view's
// (which fail closed), complete records only, both widgets public and
// neither filtering by the viewer. The detail page must be a standard page
// in site search itself. Each record is drawn with ?r= for its widget and
// form_id for its head; one that fails to draw, or that the page would not
// show, is left out (and its old item goes with the sweep below) instead of
// ending the run. Records come before files so the files they link are
// indexed.
$designer_record_urls = array();
$designer_detail_pages = array();
$designer_failures = 0;
$recycle_bin_folder_ids = pg_recycle_bin_folder_ids();

foreach ($designer_hosts as $designer_host) {
    $host_page = $designer_host['page'];

    foreach ($designer_host['lists'] as $list_cfg) {
        $form_page_id = pg_sw_form_widget_form_page_id($list_cfg);
        $detail_page_id = isset($list_cfg['detail_page_id']) ? (int) $list_cfg['detail_page_id'] : 0;

        if (($form_page_id <= 0) || ($detail_page_id <= 0)) {
            continue;
        }

        if (!array_key_exists($detail_page_id, $designer_detail_pages)) {
            $detail_page = db_item(
                "SELECT
                    page.page_name,
                    page.page_title,
                    page.page_folder,
                    page.page_search_keywords
                FROM page
                INNER JOIN folder ON page.page_folder = folder.folder_id
                WHERE
                    (page.page_id = '" . $detail_page_id . "')
                    AND (page.page_type = 'standard')
                    AND (page.page_search = '1')
                    AND (folder.folder_archived = '0')");

            if (!is_array($detail_page) || in_array((int) $detail_page['page_folder'], $recycle_bin_folder_ids)) {
                $detail_page = null;
            }

            $designer_detail_pages[$detail_page_id] = $detail_page;
        }

        $detail_page = $designer_detail_pages[$detail_page_id];

        if ($detail_page === null) {
            continue;
        }

        // The record view on the detail page that shows the list's form.
        $record_view = null;

        foreach (pg_sw_page_widgets($detail_page_id) as $widget) {
            if (
                isset($widget['cfg']['regionType'])
                && ($widget['cfg']['regionType'] === 'form_item_view')
                && (pg_sw_form_widget_form_page_id($widget['cfg']) === $form_page_id)
            ) {
                $record_view = $widget;
                break;
            }
        }

        if (!$record_view || !pg_sw_search_index_eligible($list_cfg, $record_view['cfg'])) {
            continue;
        }

        $folder_id = pg_sw_search_index_folder(
            $host_page['folder_id'],
            pg_sw_search_index_folder_open(get_access_control_type($host_page['folder_id'])),
            $detail_page['page_folder'],
            pg_sw_search_index_folder_open(get_access_control_type($detail_page['page_folder'])));

        $saved_error_throws = pg_error_throws();
        pg_error_throws(true);

        try {
            $filter_sql = pg_sw_list_record_filter_clause($form_page_id, $list_cfg, $record_view['cfg'], $detail_page_id);

            $records = db_items(
                "SELECT forms.id, forms.reference_code, forms.address_name
                FROM forms
                " . $filter_sql['join'] . "
                WHERE
                    (forms.page_id = '" . (int) $form_page_id . "')
                    AND (forms.complete = 1)
                    AND (forms.reference_code != '')
                    " . $filter_sql['where'] . "
                ORDER BY forms.id ASC");
        } catch (Throwable $throwable) {
            $records = array();
            $designer_failures++;
        } finally {
            pg_error_throws($saved_error_throws);
        }

        $pretty = pg_sw_form_pretty_urls($form_page_id);

        foreach ((array) $records as $record) {
            $url = pg_sw_search_index_record_url($detail_page['page_name'], $record['reference_code'], $record['address_name'], $pretty);

            if ($url === '') {
                continue;
            }

            // Several lists can reach the same record. It is drawn once; a
            // list on an open page (or on a page in the record page's own
            // folder) widens the folder an earlier list on a closed page
            // filed it under to the record page's.
            if (array_key_exists($url, $designer_record_urls)) {
                if (
                    ($designer_record_urls[$url] !== null)
                    && ($designer_record_urls[$url] !== $folder_id)
                    && ($folder_id === (int) $detail_page['page_folder'])
                ) {
                    db("UPDATE search_items SET folder_id = '" . $folder_id . "' WHERE url = '" . e($url) . "'");
                    $designer_record_urls[$url] = $folder_id;
                }

                continue;
            }

            $designer_record_urls[$url] = null;

            $had_reference_code = array_key_exists('r', $_GET);
            $saved_reference_code = $had_reference_code ? $_GET['r'] : null;
            $saved_error_throws = pg_error_throws();
            $saved_ob_level = ob_get_level();

            $_GET['r'] = (string) $record['reference_code'];
            pg_error_throws(true);

            try {
                // The record page would not show it (gone since the query,
                // or held back by the page's own record view).
                $record_context = pg_sw_record_comment_context($detail_page_id, (int) $record['id']);

                if (!is_array($record_context) || empty($record_context['visible'])) {
                    continue;
                }

                $content = get_page_content($detail_page_id, '', '', 'preview', false, array('form_id' => (int) $record['id']));

                if (((string) $content === '') || pg_sw_item_missing()) {
                    continue;
                }

                $record_rows = db_items(
                    "SELECT form_fields.name, form_fields.rss_field, form_data.data, form_data.type
                    FROM form_data
                    LEFT JOIN form_fields ON form_data.form_field_id = form_fields.id
                    WHERE form_data.form_id = '" . (int) $record['id'] . "'
                    ORDER BY form_fields.sort_order ASC, form_data.id ASC");

                $record_texts = pg_sw_search_index_record_texts($record_view['cfg'], $record_rows, $record['reference_code']);

                $title = $record_texts['title'];

                if ($title === '') {
                    $title = ((string) $detail_page['page_title'] !== '') ? (string) $detail_page['page_title'] : (string) $detail_page['page_name'];
                }

                $linked_item_names = update_linked_item_names($content, $linked_item_names);

                $promoted_keywords = (string) $detail_page['page_search_keywords'];
                $more_promoted_keywords = get_promoted_keywords($content);

                if ($more_promoted_keywords != '') {
                    if ($promoted_keywords != '') {
                        $promoted_keywords .= ', ';
                    }

                    $promoted_keywords .= $more_promoted_keywords;
                }

                // The record's own title and description, which the drawn
                // page need not print, then its text. The detail page's
                // title and meta description are left out: every record of
                // the page would match their words.
                $content = $title . ' ' . $record_texts['description'] . ' ' . $detail_page['page_search_keywords'] . ' ' . convert_html_to_text($content);

                pg_sw_search_index_save_item(array(
                    'url' => $url,
                    'content' => $content,
                    'title' => $title,
                    'description' => $record_texts['description'],
                    'keywords' => $promoted_keywords,
                    'page_id' => $detail_page_id,
                    'folder_id' => $folder_id
                ), $timestamp);

                $designer_record_urls[$url] = $folder_id;
            } catch (Throwable $throwable) {
                $designer_failures++;
                continue;
            } finally {
                // A render that throws skips the ob_get_clean() of a PHP
                // region or dynamic region it was evaluating; its buffer
                // would swallow the rest of the run's output.
                while (ob_get_level() > $saved_ob_level) {
                    ob_end_clean();
                }

                pg_error_throws($saved_error_throws);

                if ($had_reference_code) {
                    $_GET['r'] = $saved_reference_code;
                } else {
                    unset($_GET['r']);
                }
            }

            echo
                '<li class="list-group-item px-3 py-3">
                    <div class="d-flex align-items-start gap-3">
                        <small class="fg-secondary flex-shrink-0 text-end mt-1" style="min-width:22px;font-size:10px">' . $count_pages++ . '</small>
                        <div class="flex-fill overflow-hidden">
                            <a href="' . h(URL_SCHEME . HOSTNAME . PATH . $url) . '" target="_blank" class="fw-semibold text-truncate d-block" style="font-size:13px">' . h($title) . '</a>
                            <div class="fg-secondary text-truncate" style="font-size:11px">' . h($url) . ($record_texts['description'] !== '' ? ' &middot; ' . h($record_texts['description']) : '') . '</div>
                        </div>
                        <span class="badge text-bg-light border flex-shrink-0" style="font-size:9px">' . lang('Form') . '</span>
                    </div>
                </li>';
        }
    }
}

if ($designer_failures > 0) {
    error_log('Pinegrap: the search index left out ' . $designer_failures . ' record list(s) or record page(s) that failed to load.');
}

// Get files that should be included in search index.
$files = db_items(
    "SELECT
        files.name,
        files.folder AS folder_id,
        files.description,
        files.type
    FROM files
    LEFT JOIN folder ON files.folder = folder.folder_id
    WHERE
        (files.design = '0')
        AND (folder.folder_archived = '0')");

// Loop through files in order to store content in database for searching.
foreach ($files as $file) {
    // If this file was linked in a page, then continue to include it in the index.
    if (in_array(mb_strtolower($file['name']), $linked_item_names) == true) {
        $url = encode_url_path($file['name']);

        $content = '';

        // Get the content in different ways depending on the type of file.
        // We only get content for certain file types.
        switch (mb_strtolower($file['type'])) {
            case 'doc':
                $content = convert_doc_to_text(FILE_DIRECTORY_PATH . '/' . $file['name']);
                break;

            case 'docx':
                $content = convert_docx_to_text(FILE_DIRECTORY_PATH . '/' . $file['name']);
                break;

            case 'pdf':
                // If the path to pdftotext has not been determined yet, then try to find it.
                if (isset($pdftotext_path) == false)  {
                    $pdftotext_path = '';

                    // If the server is running Windows, then try to find pdftotext in a certain way.
                    if (mb_strtoupper(mb_substr(PHP_OS, 0, 3)) == 'WIN') {
                        // If the path constant is defined then use that (but add quotes around path,
                        // because path might contain spaces and Windows requires that).
                        if ((defined('PDFTOTEXT_PATH') == true) && (PDFTOTEXT_PATH != '')) {
                            $pdftotext_path = '"' . PDFTOTEXT_PATH . '"';
                        }
                        
                    // Otherwise the server is running Unix, so try to find pdftotext in a different way.
                    } else {
                        // If the path constant is defined, then use that.
                        if ((defined('PDFTOTEXT_PATH') == true) && (PDFTOTEXT_PATH != '')) {
                            $pdftotext_path = PDFTOTEXT_PATH;

                        // Otherwise, try to figure out the path.
                        // For this area, we first tried using code similar to what we do for wkhtmltopdf,
                        // however pdftotext would not return anything when testing the location,
                        // so we had to use this "which" solution instead.
                        } else {
                            $pdftotext_path = trim(shell_exec('which pdftotext'));
                        }
                    }
                }

                // If a pdftotext path has been found, then convert PDF to text.
                if ($pdftotext_path != '') {
                    $content = shell_exec($pdftotext_path . ' ' . escapeshellarg(FILE_DIRECTORY_PATH . '/' . $file['name']) . ' -');
                }
                break;

            case 'ppt':
                $content = convert_ppt_to_text(FILE_DIRECTORY_PATH . '/' . $file['name']);
                break;

            case 'pptx':
                $content = convert_pptx_to_text(FILE_DIRECTORY_PATH . '/' . $file['name']);
                break;

            case 'txt':
                $content = @file_get_contents(FILE_DIRECTORY_PATH . '/' . $file['name']);
                break;

            case 'xls':
                $content = convert_xls_to_text(FILE_DIRECTORY_PATH . '/' . $file['name']);
                break;

            case 'xlsx':
                $content = convert_xlsx_to_text(FILE_DIRECTORY_PATH . '/' . $file['name']);
                break;
        }

        $description = trim($file['description']);

        // If the file description in the database is blank, then add the content from the searched file.
        if ($description == '') {
            $description = trim($content);
        }

        // If the description is greater than 255 characters then shorten it.
        if (mb_strlen($description) > 255) {
            $description = mb_substr($description, 0, 255) . '...';
        }

        // Add file name and description to content
        // so that there is more data included in index to get better results
        // and so that we can simply search the one content index instead of
        // searching the title, description, and content indexes.
        $content = $file['name'] . ' ' . $file['description'] . ' ' . $content;

        // Check if a search item already exists for this URL.
        $number_of_items = db_value("SELECT COUNT(*) FROM search_items WHERE url = '" . escape($url) . "'");

        // If a search item already exists, then update it.
        if ($number_of_items == 1) {
            db(
                "UPDATE search_items
                SET
                    url = '" . escape($url) . "',
                    content = '" . escape($content) . "',
                    title = '" . escape($file['name']) . "',
                    description = '" . escape($description) . "',
                    folder_id = '" . $file['folder_id'] . "',
                    timestamp = '" . $timestamp . "'
                WHERE url = '" . escape($url) . "'");

        // Otherwise a search item does not already exist, so insert it.
        } else {
            db(
                "INSERT INTO search_items (
                    url,
                    content,
                    title,
                    description,
                    folder_id,
                    timestamp)
                VALUES (
                    '" . escape($url) . "',
                    '" . escape($content) . "',
                    '" . escape($file['name']) . "',
                    '" . escape($description) . "',
                    '" . $file['folder_id'] . "',
                    '" . $timestamp . "')");
        }

        echo
            '<li class="list-group-item px-3 py-3">
                <div class="d-flex align-items-start gap-3">
                    <small class="fg-secondary flex-shrink-0 text-end mt-1" style="min-width:22px;font-size:10px">' . $count_pages++ . '</small>
                    <div class="flex-fill overflow-hidden">
                        <a href="' . h(URL_SCHEME . HOSTNAME . PATH . $url) . '" target="_blank" class="fw-semibold text-truncate d-block" style="font-size:13px">' . h($file['name']) . '</a>
                        <div class="fg-secondary text-truncate" style="font-size:11px">' . h($url) . ($description ? ' &middot; ' . h($description) : '') . '</div>
                    </div>
                    <span class="badge text-bg-light border flex-shrink-0" style="font-size:9px">' . lang('File') . '</span>
                </div>
            </li>';
    }
}

// Delete old items that no longer exist.
db("DELETE FROM search_items WHERE timestamp != '" . $timestamp . "'");

// Ping IndexNow if the sitemap has changed and at least 12 hours have passed since the last ping.
// This runs here so the CURL call stays off the interactive page load path.
if ($timestamp >= (LAST_SITEMAP_CHECK_TIMESTAMP + 43200)) {
    update_sitemap_and_ping();
}

// Scheduled-task health: record that this job finished. See pg_cron_ran().
pg_cron_ran('update_search_index');

echo '
    </ul>

    <div id="finish" class="text-center py-7" style="display:none">
        <i class="bi bi-check-circle-fill fg-success d-block mb-3" style="font-size:2.5rem"></i>
        
        <div class="fw-semibold mb-5">' . lang('The Search Index has been updated successfully!') . '</div>
        <button type="button" class="btn btn-sm btn-outline theme-secondary" onclick="window.close()">
            <i class="bi bi-x-lg me-1"></i>' . lang('Close') . '
        </button>
    </div>
</main>' . output_footer_secure();

if (defined('PG_CRON_DISPATCH')) {
    ob_end_clean();
}

// Create a function that will check content for linked item names,
// so we can determine if files should be included in search results.
// Ideally, we would like this function to just store file names,
// however we don't have a way of determining that from the href,
// so this will store all linked items (e.g. pages, other websites, and etc.)
// but that should not cause any problems.
function update_linked_item_names($content, $linked_item_names)
{
    // Get all linked item names.
    preg_match_all('/<\s*a\s+[^>]*href\s*=\s*["\'](.*?)["\']/is', $content, $matches);

    // Loop through the matches in order to determine if match should be added to array.
    foreach ($matches[1] as $match) {
        $href = trim($match);

        // If there is a slash in the href,
        // then get item name by looking at content after last slash.
        if (mb_strpos($href, '/') !== false) {
            $position_of_last_slash = mb_strrpos($href, '/');

            $item_name = mb_substr($href, $position_of_last_slash + 1);

        // Otherwise, the item name is the whole href.
        } else {
            $item_name = $href;
        }

        // Get item name by unescaping and converting to lower case,
        // so future checks can be case-insensitive.
        $item_name = mb_strtolower(rawurldecode(unhtmlspecialchars(trim($item_name))));

        // If this item name has not already been added to the array, then add it.
        if (in_array($item_name, $linked_item_names) == false) {
            $linked_item_names[] = $item_name;
        }
    }

    return $linked_item_names;
}

/*****************************************************************
This approach uses detection of NUL (chr(00)) and end line (chr(13))
to decide where the text is:
- divide the file contents up by chr(13)
- reject any slices containing a NUL
- stitch the rest together again
- clean up with a regular expression
*****************************************************************/

function convert_doc_to_text($file_path) 
{
    $fileHandle = fopen($file_path, "r");
    $line = @fread($fileHandle, filesize($file_path));   
    $lines = explode(chr(0x0D),$line);
    $outtext = "";
    foreach($lines as $thisline)
      {
        $pos = strpos($thisline, chr(0x00));
        if (($pos !== FALSE)||(strlen($thisline)==0))
          {
          } else {
            $outtext .= $thisline." ";
          }
      }
     $outtext = preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/","",$outtext);
    return $outtext;
}

function convert_docx_to_text($file_path)
{
    // Only the part with the text is read: its declared size is measured
    // against an office part's ceiling before anything is inflated, and it
    // is read no further than that size. The rest of the package (a video,
    // say) is not looked at.
    $zip = class_exists('ZipArchive') ? new ZipArchive() : null;

    if (($zip === null) || (@$zip->open($file_path) !== true)) {
        return '';
    }

    $error = '';
    $content = pg_zip_entry_contents($zip, 'word/document.xml', PG_OFFICE_MAX_PART, $error);
    $zip->close();

    // No such part, or one that is too large or damaged: no text.
    if ($content === null) {
        return '';
    }

    // Replace tags with a space.
    $content = preg_replace('/<[^>]*>/', ' ', $content);

    // Remove multiple spaces.
    $content = preg_replace('/ {2,}/', ' ', $content);

    // Remove spaces at the beginning and end.
    $content = trim($content);

    // Unescape characters that were escaped in the XML (e.g. convert "&lt;" to "<").
    $content = unhtmlspecialchars($content);

    return $content;
}

function convert_ppt_to_text($file_path)
{
    // This approach uses detection of the string "chr(0f).Hex_value.chr(0x00).chr(0x00).chr(0x00)" to find text strings, which are then terminated by another NUL chr(0x00). [1] Get text between delimiters [2] 
    $fileHandle = fopen($file_path, "r");
    $line = @fread($fileHandle, filesize($file_path));
    $lines = explode(chr(0x0f),$line);
    $outtext = '';

    foreach($lines as $thisline) {
        if (strpos($thisline, chr(0x00).chr(0x00).chr(0x00)) == 1) {
            $text_line = substr($thisline, 4);
            $end_pos   = strpos($text_line, chr(0x00));
            $text_line = substr($text_line, 0, $end_pos);
            $text_line = preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/","",$text_line);
            if (strlen($text_line) > 1) {
                $outtext.= substr($text_line, 0, $end_pos)."\n";
            }
        }
    }

    return $outtext;
}

function convert_pptx_to_text($file_path)
{
    // Read like a docx (convert_docx_to_text()).
    $zip = class_exists('ZipArchive') ? new ZipArchive() : null;

    if (($zip === null) || (@$zip->open($file_path) !== true)) {
        return '';
    }

    $error = '';
    $content = '';

    // The slides together are held to what one part may be.
    $room = PG_OFFICE_MAX_PART;

    // Create a loop in order to get content for every slide.
    for ($slide_number = 1; true; $slide_number++) {
        $slide = pg_zip_entry_contents($zip, 'ppt/slides/slide' . $slide_number . '.xml', $room, $error);

        // No such slide is the end of the slides; one past the room left, or
        // a damaged one, ends the text there.
        if ($slide === null) {
            break;
        }

        $room -= strlen($slide);
        $content .= $slide;
    }

    $zip->close();

    // Replace tags with a space.
    $content = preg_replace('/<[^>]*>/', ' ', $content);

    // Remove multiple spaces.
    $content = preg_replace('/ {2,}/', ' ', $content);

    // Remove spaces at the beginning and end.
    $content = trim($content);

    // Unescape characters that were escaped in the XML (e.g. convert "&lt;" to "<").
    $content = unhtmlspecialchars($content);

    return $content;
}

function convert_xls_to_text($file_path)
{
    // The older binary format, read by excel_reader2.
    require_once('excel_reader2.php');

    $data = new Spreadsheet_Excel_Reader($file_path);

    // If the file is invalid or there are no sheets, then return empty string.
    if (count($data->sheets) == 0) {
        return '';
    }

    $content = '';

    // Loop through all sheets, in order to get content.
    foreach ($data->sheets as $sheet) {
        // If there is at least one cell, then loop through all rows in order to get content for each cell.
        if (isset($sheet['cells']) == true) {
            foreach ($sheet['cells'] as $row) {
                // Loop through each cell in this row, in order to get content.
                foreach ($row as $cell) {
                    $content .= ' ' . $cell;
                }
            }
        }
    }

    // Remove multiple spaces.
    $content = preg_replace('/ {2,}/', ' ', $content);

    // Remove spaces at the beginning and end.
    $content = trim($content);

    return $content;
}

function convert_xlsx_to_text($file_path)
{
    // Read like a docx (convert_docx_to_text()).
    $zip = class_exists('ZipArchive') ? new ZipArchive() : null;

    if (($zip === null) || (@$zip->open($file_path) !== true)) {
        return '';
    }

    // The shared strings hold the workbook's text.
    $error = '';
    $content = pg_zip_entry_contents($zip, 'xl/sharedStrings.xml', PG_OFFICE_MAX_PART, $error);
    $zip->close();

    if ($content === null) {
        return '';
    }

    // Replace tags with a space.
    $content = preg_replace('/<[^>]*>/', ' ', $content);

    // Remove multiple spaces.
    $content = preg_replace('/ {2,}/', ' ', $content);

    // Remove spaces at the beginning and end.
    $content = trim($content);

    // Unescape characters that were escaped in the XML (e.g. convert "&lt;" to "<").
    $content = unhtmlspecialchars($content);

    return $content;
}

// This function looks for HTML tags with a specific class ("promote-on-keyword"),
// and gets the content/keywords that appear inside the HTML tag.

function get_promoted_keywords($content) {

    // Get all the HTML tags that have a "promoted" class.
    preg_match_all('/class\s*=\s*["\']([^"\']*\s+)*promote-on-keyword(\s+[^"\']*)*["\'].*?>(.*?)<\//is', $content, $matches, PREG_SET_ORDER);

    $promoted_keywords = '';

    foreach ($matches as $match) {

        $keywords = trim($match[3]);

        // If there are no keywords inside this HTML tag, then skip this match,
        // and go to the next one.
        if ($keywords == '') {
            continue;
        }

        $keywords = convert_html_to_text($keywords);

        // If keywords have already been collected from previous matches,
        // then add a comma and space for separation.
        if ($promoted_keywords != '') {
            $promoted_keywords .= ', ';
        }

        $promoted_keywords .= $keywords;

    }

    return $promoted_keywords;

}