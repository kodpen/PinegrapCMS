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

validate_token_field();

include_once('liveform.class.php');
$liveform = new liveform('region_content');

$liveform->add_fields_to_session();

$page_id = $liveform->get_field_value('page_id');

// get page properties from the database
$query =
    "SELECT
        page_name,
        page_folder,
        seo_analysis_current
    FROM page
    WHERE page_id = '" . escape($page_id) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);
$page_name = $row['page_name'];
$folder_id = $row['page_folder'];
$seo_analysis_current = $row['seo_analysis_current'];

$sql_seo_analysis_current = "";

// Set wherever the markup of THIS page changed, which is not the same question
// $sql_seo_analysis_current answers: that one is only filled in when the score
// was current beforehand, so a page that was already stale would never be
// re-analyzed on save. Page regions and the system header and footer all
// qualify - each belongs to exactly one page. Common regions do not: they are
// shared, and their pages are queued instead.
$seo_page_markup_changed = FALSE;

// replace path references with a placeholder, so the path is not stored in the database
$liveform->assign_field_value('region_content', prepare_rich_text_editor_content_for_input($liveform->get_field_value('region_content')));

switch ($liveform->get_field_value('region_type')) {
    // if type is pregion
    case 'pregion':
        // if region already exists then update the region
        if ($liveform->get_field_value('region_id')) {
            // get region information
            // we get page information again that we have already gotten above, in case the user is trying to hack and send a region id that is not part of the page id that they passed
            $query =
                "SELECT
                    pregion.pregion_content,
                    pregion.pregion_page as page_id,
                    page.page_name as page_name,
                    page.page_folder as folder_id,
                    page.seo_analysis_current
                FROM pregion
                LEFT JOIN page ON pregion.pregion_page = page.page_id
                WHERE pregion.pregion_id = '" . escape($liveform->get_field_value('region_id')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            
            $page_region_content = $row['pregion_content'];
            $page_id = $row['page_id'];
            $page_name = $row['page_name'];
            $folder_id = $row['folder_id'];
            $seo_analysis_current = $row['seo_analysis_current'];
            
            // validate user's access to page region
            if (check_edit_access($folder_id) == false) {
                log_activity(lang('access denied to edit region because user does not have access to modify folder that page is in'), $_SESSION['sessionusername']);
                output_error('Access denied.');
            }

            // update region content
            $query =
                "UPDATE pregion
                SET 
                    pregion_content = '" . escape($liveform->get_field_value('region_content')) . "', 
                    pregion_user = '" . $user['id'] . "', 
                    pregion_timestamp = UNIX_TIMESTAMP()
                WHERE pregion_id = '" . escape($liveform->get_field_value('region_id')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if the seo analysis is current and the page region content has changed, then prepare to remove current status
            if (($seo_analysis_current == 1) && ($page_region_content != $liveform->get_field_value('region_content'))) {
                $sql_seo_analysis_current = "seo_analysis_current = '0',";
            }

            if ($page_region_content != $liveform->get_field_value('region_content')) {
                $seo_page_markup_changed = TRUE;
            }
            
        // else region does not exist so create a new one
        } else {
            // validate that user has access to create page region for the page
            if (check_edit_access($folder_id) == false) {
                log_activity(lang('access denied to edit region because user does not have access to modify folder that page is in'), $_SESSION['sessionusername']);
                output_error('Access denied.');
            }

            $order = $liveform->get_field_value('region_order');
            $collection = $liveform->get_field_value('collection');

            // If this region's order is greater than 1, then check if we need to create
            // placeholder regions for earlier page regions.  This is necessary,
            // so that the content will appear in the correct page region location,
            // when the page is refreshed (i.e. not moved up to the top region).
            if ($order > 1) {
                // Loop through each earlier page region.
                for ($region_order = 1; $region_order < $order; $region_order++) {
                    // If a page region does not yet exist for this earlier region,
                    // then create a blank page region for it.
                    if (!db_value(
                        "SELECT pregion_id
                        FROM pregion
                        WHERE
                            (pregion_page = '" . e($page_id) . "')
                            AND (pregion_order = '$region_order')
                            AND (collection = '" . e($collection) . "')")
                    ) {
                        db(
                            "INSERT INTO pregion (
                                pregion_name,
                                pregion_page,
                                pregion_order,
                                collection,
                                pregion_user,
                                pregion_timestamp)
                            VALUES (
                                '" . e(time() . '_' . $region_order) . "',
                                '" . e($page_id) . "',
                                '" . e($region_order) . "',
                                '" . e($collection) . "',
                                '" . $user['id'] . "',
                                UNIX_TIMESTAMP())");
                    }
                }
            }
            
            // create region name
            $region_name = time() . '_' . $order;
            
            $query =
                "INSERT INTO pregion (
                    pregion_name,
                    pregion_content,
                    pregion_page,
                    pregion_order,
                    collection,
                    pregion_user,
                    pregion_timestamp)
                VALUES (
                    '" . e($region_name) . "',
                    '" . e($liveform->get_field_value('region_content')) . "',
                    '" . e($page_id) . "',
                    '" . e($order) . "',
                    '" . e($collection) . "',
                    '" . $user['id'] . "',
                    UNIX_TIMESTAMP())";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // if the seo analysis is current and the new page region has content, then prepare to remove current status
            if (($seo_analysis_current == 1) && ($liveform->get_field_value('region_content') != '')) {
                $sql_seo_analysis_current = "seo_analysis_current = '0',";
            }

            if ($liveform->get_field_value('region_content') != '') {
                $seo_page_markup_changed = TRUE;
            }
        }
        break;
    
    // if type is cregion
    case 'cregion':
        $cregion_id = (int)$liveform->get_field_value('region_id');
        $cregion_row = db_item("SELECT cregion_name, cregion_designer_type FROM cregion WHERE cregion_id = '" . $cregion_id . "'");

        if (!is_array($cregion_row)) {
            output_error(lang('Access denied.'));
        }

        // A designer region is design, not content. Every screen that opens one
        // (edit_designer_region.php, edit_common_region.php) requires the
        // designer role, and this endpoint has to apply the same gate: the
        // request does not have to come from one of those screens.
        if (($cregion_row['cregion_designer_type'] == 'yes') && ($user['role'] > 1)) {
            log_activity(lang(array('string' => 'access denied because user does not have access to edit designer region ({var:1})', 'vars' => array($cregion_row['cregion_name']))), $_SESSION['sessionusername']);
            output_error(lang('Access denied.'));
        }

        // if user has a user role and if they do not have access to this common region, then user does not have access to edit region, so output error
        if (($user['role'] == 3) && (in_array($cregion_id, get_items_user_can_edit('common_regions', $user['id'])) == FALSE)) {
            log_activity(lang(array('string' => 'access denied because user does not have access to edit common region ({var:1})', 'vars' => array($cregion_row['cregion_name']))), $_SESSION['sessionusername']);
            output_error('Access denied.');
        }
        
        // update region content
        $query =
            "UPDATE cregion
            SET 
                cregion_content = '" . escape($liveform->get_field_value('region_content')) . "', 
                cregion_user = '" . $user['id'] . "', 
                cregion_timestamp = UNIX_TIMESTAMP()
            WHERE cregion_id = '" . $cregion_id . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // A common region is shared: this one edit changes the markup of every
        // page whose style or page region pulls it in, and on most sites that
        // is where the header and the footer live. Those pages are queued
        // rather than analyzed - rendering all of them inside a save request is
        // not a trade this can make - so the nightly pass takes them that
        // night instead of whenever the periodic full refresh next comes round.
        $cregion_name = $cregion_row['cregion_name'];

        if ($cregion_name !== NULL) {
            require_once(dirname(__FILE__) . '/seo.php');
            require_once(dirname(__FILE__) . '/seo_structure.php');

            pg_seo_queue_common_region($cregion_name);
        }

        break;

    // if type is system_region_header then update it
    case 'system_region_header':
        // get system region header information
        $query =
            "SELECT
                page_id,            
                page_name,
                page_folder as folder_id,
                system_region_header
            FROM page
            WHERE page_id = '" . escape($liveform->get_field_value('region_id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $page_id = $row['page_id'];
        $page_name = $row['page_name'];
        $folder_id = $row['folder_id'];
        
        // validate user's access to system region header
        if (check_edit_access($folder_id) == false) {
            log_activity(lang(array('string' => 'access denied to edit system region header ({var:1}) because user does not have access to modify folder that page is in', 'vars' => array($page_name))), $_SESSION['sessionusername']);
            output_error('Access denied.');
        }

        // update the info for the system region header and its page
        $query =
            "UPDATE page
            SET 
                system_region_header = '" . escape($liveform->get_field_value('region_content')) . "',
                page_user = '" . $user['id'] . "',
                page_timestamp = UNIX_TIMESTAMP()
            WHERE page_id = '" . escape($liveform->get_field_value('region_id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // Stored on the page row, so this is one page's markup like a page
        // region is - not a shared region despite the name.
        if ($row['system_region_header'] != $liveform->get_field_value('region_content')) {
            $seo_page_markup_changed = TRUE;
        }
        
        break;

    // if type is system_region_footer then update it
    case 'system_region_footer':
        // get system region footer information
        $query =
            "SELECT
                page_id,            
                page_name,
                page_folder as folder_id,
                system_region_footer
            FROM page
            WHERE page_id = '" . escape($liveform->get_field_value('region_id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $page_id = $row['page_id'];
        $page_name = $row['page_name'];
        $folder_id = $row['folder_id'];
        
        // validate user's access to system region footer
        if (check_edit_access($folder_id) == false) {
            log_activity(lang(array('string' => 'access denied to edit system region footer ({var:1}) because user does not have access to modify folder that page is in', 'vars' => array($page_name))), $_SESSION['sessionusername']);
            output_error('Access denied.');
        }

        // update the info for the system region footer and its page
        $query =
            "UPDATE page
            SET 
                system_region_footer = '" . escape($liveform->get_field_value('region_content')) . "',
                page_user = '" . $user['id'] . "',
                page_timestamp = UNIX_TIMESTAMP()
            WHERE page_id = '" . escape($liveform->get_field_value('region_id')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // Stored on the page row, so this is one page's markup like a page
        // region is - not a shared region despite the name.
        if ($row['system_region_footer'] != $liveform->get_field_value('region_content')) {
            $seo_page_markup_changed = TRUE;
        }
        
        break;
}

// if this region is not a system region header or system region footer,
// then update the page properties.  We have already updated the page for the
// system region header and footer.
//
// The page region branch has already checked edit access on the page's folder.
// The common region branch has not: its page id is only the page the editor
// was open on, supplied by the request, so the page is stamped there only when
// this user may edit it. The region itself is saved either way.
if (
    ($liveform->get_field_value('region_type') != 'system_region_header')
    && ($liveform->get_field_value('region_type') != 'system_region_footer')
    && (($liveform->get_field_value('region_type') != 'cregion') || (check_edit_access($folder_id) == true))
) {
    $query =
        "UPDATE page
        SET
            $sql_seo_analysis_current
            page_user = '" . $user['id'] . "',
            page_timestamp = UNIX_TIMESTAMP()
        WHERE page_id = '" . escape($page_id) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
}

// The markup of this page just changed, which is exactly what the HTML half of
// the score reads.
//
// The flag is set by the three branches whose content belongs to exactly one
// page - a page region, a system header, a system footer - and only when the
// value actually differs from what was stored, so an operator who opens a
// region and saves it unchanged does not pay for a render. The common region
// branch deliberately does not set it: that content is shared, and its pages
// are queued there instead. Rendering all of them inside a save request is not
// a trade this can make.
//
// Analyzed here rather than left for tonight because the editor is about to
// look at the ring in the toolbar, and a structure score describing the
// previous version of the page is worse than none.
if ($seo_page_markup_changed) {
    require_once(dirname(__FILE__) . '/seo.php');
    require_once(dirname(__FILE__) . '/seo_structure.php');

    if (pg_seo_analyze_record('page', $page_id)) {
        pg_seo_recalculate('page', array((int) $page_id));
    }
}

// log activity
log_activity(lang(array('string' => '{var:1} ({var:2}) was modified', 'vars' => array(lang('page'), $page_name))), $_SESSION['sessionusername']);

if ($liveform->get_field_value('inline') != 'true') {
    // forward user to the last page they were on
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')));
}

$liveform->remove_form();
?>