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
include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');
$liveform = new liveform('view_pages');
$user = validate_user();
validate_area_access($user, 'user');

// If a theme is being previewed then get the activated themes.
// We will use further below.
if (!empty($_SESSION['software']['preview_theme_id'])) {
    $activated_desktop_theme_id = db_value("SELECT id FROM files WHERE activated_desktop_theme = '1'");
    $activated_mobile_theme_id = db_value("SELECT id FROM files WHERE activated_mobile_theme = '1'");
}

// Modify Selected asks for its changes in a dialog of the Pages list
// (pg_page_bulk_edit_dialog()); this file only takes the post.
if (!$_POST) {
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_pages.php');

// else the form has been submitted, so process it
} else {
    validate_token_field();

    // The pages list sends its selection as the list table driver keeps it:
    // the pages picked, or the whole view by its condition less the
    // exceptions. It is read back with the list's own query and the user's
    // rights (pg_page_list_selected_ids()).
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_list.php');
    $page_ids = pg_page_list_selected_ids($user, $_POST['selection'] ?? '', $_POST['list_params'] ?? '');

    if ($page_ids === false) {
        output_error('Query failed.');
    }

    if ($page_ids === null) {
        output_error(lang('Too many pages are selected. Select fewer pages and try again.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
    }

    // if at least one page was selected
    if ($page_ids) {
        $number_of_pages = 0;
        
        switch ($_POST['action'] ?? '') {
            // if pages are being edited, proceed
            case 'edit':
                // if a folder was selected to move the page(s) to
                // and if user does not have access to the folder that he/she is trying to move pages to, output error
                $move_to_folder = (int) ($_POST['move_to_folder'] ?? 0);
                $move_to_folder_name = '';

                if ($move_to_folder > 0) {
                    if (check_edit_access($move_to_folder) == false) {
                        output_error(lang('You do not have access to move pages to the folder that you selected') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
                    }

                    // Only a folder the move picker offers: one that exists,
                    // outside the Recycle Bin and the Workspace.
                    $folder_tree = pg_folder_access_tree();

                    if (!pg_page_bulk_folder_ok($move_to_folder, $folder_tree['rows'], pg_workspace_folder_id(), $folder_tree['bin'])) {
                        output_error(lang('Pages cannot be moved to the folder that you selected.') . ' <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
                    }

                    $result = db_q("SELECT folder_name FROM folder WHERE folder_id = ?", array($move_to_folder)) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);
                    $move_to_folder_name = $row ? (string) $row['folder_name'] : '';
                }

                // While a theme other than the activated one is previewed, the styles are set for the preview.
                $previewing = (
                    (!empty($_SESSION['software']['preview_theme_id']))
                    && ($_SESSION['software']['preview_theme_id'] != $activated_desktop_theme_id)
                    && ($_SESSION['software']['preview_theme_id'] != $activated_mobile_theme_id)
                );

                // Only staff set a style, as on the page screen; a role 3 user is
                // not offered the two pick lists and a posted style is ignored.
                $staff = ($user['role'] < 3);
                $edit_page_style = $staff ? (string) ($_POST['edit_page_style'] ?? '') : '';
                $edit_mobile_style_id = $staff ? (string) ($_POST['edit_mobile_style_id'] ?? '') : '';

                $set = array(
                    'folder' => $move_to_folder,
                    'search' => $_POST['edit_site_search'] ?? '',
                    'sitemap' => $_POST['edit_sitemap'] ?? '',
                    'style' => $edit_page_style,
                    'mobile_style' => $edit_mobile_style_id,
                );

                // What was written, counted from the decisions: the notice,
                // the log and the audit name nothing a page did not get.
                $tally = pg_page_bulk_tally_start();

                // loop through each page and process actions
                foreach ($page_ids as $page_id) {
                    $page = pg_page_bulk_fetch($page_id);

                    if (!$page) {
                        continue;
                    }

                    // if user has access to page then proceed
                    if (check_edit_access($page['page_folder']) == true) {
                        $decision = pg_page_bulk_decide($page, $set, $user['role']);
                        $tally = pg_page_bulk_tally($tally, $page, $set, $decision);

                        // Nothing to write for this page: no audit line either.
                        if ((count($decision['columns']) == 0) && (count($decision['styles']) == 0)) {
                            continue;
                        }

                        $audit_before = pg_audit_fetch('page', $page['page_id']);

                        if (!pg_page_bulk_apply($page, $decision, $user['id'], $previewing ? (int) $_SESSION['software']['preview_theme_id'] : 0)) {
                            output_error('Query failed.');
                        }

                        pg_audit_record('update', 'page', $page['page_id'], $audit_before);

                    // else output error
                    } else {
                        output_error(lang('You do not have access to edit the pages that you selected') . '. <a href="javascript:history.go(-1);">' . lang('Go back') . '</a>.');
                    }
                }

                // Each change is a verb-free phrase, named only when at least
                // one page got it; they are joined with ', ' inside one
                // sentence key.
                $changes = array();

                if ($tally['folder'] > 0) {
                    $changes[] = lang(array('string' => 'moved to the folder {var:1}', 'vars' => array($move_to_folder_name)));
                }

                $style_label = function ($style_id) use ($previewing) {
                    if ((string) $style_id === '0') {
                        return $previewing ? '[' . lang('Activated') . ']' : lang('Default') . ' (' . lang('inherit') . ')';
                    }

                    $result = db_q("SELECT style_name FROM style WHERE style_id = ?", array((int) $style_id)) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);

                    return $row ? (string) $row['style_name'] : '';
                };

                if ($tally['style_desktop'] > 0) {
                    $style = $style_label($edit_page_style);
                    $changes[] = $previewing
                        ? lang(array('string' => 'preview page style set to {var:1}', 'vars' => array($style)))
                        : lang(array('string' => 'page style set to {var:1}', 'vars' => array($style)));
                }

                if ($tally['style_mobile'] > 0) {
                    $mobile_style = $style_label($edit_mobile_style_id);
                    $changes[] = $previewing
                        ? lang(array('string' => 'mobile preview page style set to {var:1}', 'vars' => array($mobile_style)))
                        : lang(array('string' => 'mobile page style set to {var:1}', 'vars' => array($mobile_style)));
                }

                if ($tally['search_on'] > 0) {
                    $changes[] = lang('site search turned on');
                }

                if ($tally['search_off'] > 0) {
                    $changes[] = lang('site search turned off');
                }

                if ($tally['sitemap_on'] > 0) {
                    $changes[] = lang('added to sitemap.xml');
                }

                if ($tally['sitemap_off'] > 0) {
                    $changes[] = lang('removed from sitemap.xml');
                }

                $number_of_pages = $tally['pages'];
                $message = '';

                if (($number_of_pages > 0) && $changes) {
                    if ($number_of_pages == 1) {
                        $message = lang(array('string' => '{var:1} page was changed: {var:2}.', 'vars' => array($number_of_pages, implode(', ', $changes))));
                    } else {
                        $message = lang(array('string' => '{var:1} pages were changed: {var:2}.', 'vars' => array($number_of_pages, implode(', ', $changes))));
                    }

                    log_activity($message, $_SESSION['sessionusername']);
                }

                // Said rather than left to be noticed: a change that did
                // nothing on part of the selection would look like a bug.
                $left_alone = array();

                if ($tally['style_skipped'] > 0) {
                    $left_alone[] = lang(array(
                        'string' => 'The style was left alone on {var:1} of them, because a page the Page Design Studio opens carries its own layout.',
                        'vars' => $tally['style_skipped']));
                }

                // The two reasons the list's quick edit gives
                // (pg_page_quick_decide()).
                foreach (array(
                    'sitemap_noindex' => lang('A page closed to search engines is not listed in the site map. Open it to search engines on the page screen first.'),
                    'sitemap_type' => lang('Pages of this type are not listed in the site map.'),
                ) as $key => $reason) {
                    if ($tally[$key] > 0) {
                        $left_alone[] = lang(array('string' => 'sitemap.xml was left alone on {var:1} of the selected pages.', 'vars' => $tally[$key])) . ' ' . $reason;
                    }
                }

                if (($message === '') && !$left_alone) {
                    $message = lang('No change detected.');
                }

                $message = trim($message . ' ' . implode(' ', $left_alone));
                $liveform->add_notice(h($message));

                break;

            // if pages are being deleted
            case 'delete':
                // if the user has a user role and the user does not have access to delete pages, then output error
                if (($user['role'] == '3') && ($user['delete_pages'] == FALSE)) {
                    log_activity(lang('access denied because user does not have access to delete pages'), $_SESSION['sessionusername']);
                    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
                }
                
                $access_error_exists = false;
                $submitted_forms_error_exists = false;

                // With the recycle bin installed a page deleted here goes there,
                // the way the file manager deletes it, and keeps everything it
                // had until the bin is emptied. One already in the bin is
                // deleted for good below, as the bin's own delete does.
                require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
                $recycle_pages = pg_recycle_ready();
                $number_of_binned_pages = 0;
                $binned_home_page = false;
                $bin_errors = array();
                // Pages deleted since the list was drawn (another tab, another
                // manager): skipped, never counted as deleted.
                $number_of_missing_pages = 0;
                
                foreach ($page_ids as $page_id) {
                    // get page properties
                    $query =
                        "SELECT
                            page_id,
                            page_folder,
                            page_type,
                            page_name,
                            page_search,
                            page_search_keywords
                        FROM page
                        WHERE page_id = ?";
                    $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                    $row = mysqli_fetch_assoc($result);

                    if (!$row) {
                        $number_of_missing_pages++;
                        continue;
                    }

                    $page_id = $row['page_id'];
                    $page_folder = $row['page_folder'];
                    $page_type = $row['page_type'];
                    $page_name = $row['page_name'];
                    $original_page_search = $row['page_search'];
                    $original_search_keywords = $row['page_search_keywords'];

                    // if user has access to page, remember that
                    if (check_edit_access($page_folder) == true) {
                        $access_granted = true;
                    } else {
                        $access_granted = false;
                        $access_error_exists = true;
                    }

                    $audit_before = pg_audit_fetch('page', $page_id);

                    if (($access_granted == true) && ($recycle_pages == true)) {
                        $recycled = pg_recycle_page($page_id, $user);

                        if ($recycled['status'] == 'binned') {
                            $number_of_binned_pages++;

                            if ($recycled['home']) {
                                $binned_home_page = true;
                            }

                            continue;
                        }

                        if ($recycled['status'] == 'error') {
                            $bin_errors[] = $recycled['message'];
                            continue;
                        }
                    }

                    // if this page is a custom form, we need to check if there are submitted forms for this page,
                    // because software will not allow this page to be deleted if there are submitted forms for this page
                    if ($page_type == 'custom form') {
                        $query = "SELECT id FROM forms WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');

                        // if there are submitted forms for this page, remember that
                        if (mysqli_num_rows($result) > 0) {
                            $submitted_forms = true;
                            $submitted_forms_error_exists = true;
                        } else {
                            $submitted_forms = false;
                        }
                    } else {
                        $submitted_forms = false;
                    }
                    
                    // if user has access to this page and there are no submitted forms, then delete page
                    if (($access_granted == true) && ($submitted_forms == false)) {
                        $query = "DELETE FROM page WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');

                        pg_audit_record('delete', 'page', $page_id, $audit_before);
                        // A page deleted from the bin leaves no restore record behind.
                        if (function_exists('pg_recycle_forget')) {
                            pg_recycle_forget('page', $page_id);
                        }

                        // Stored SEO findings for this page. The single-page
                        // delete in edit_page.php clears these; this screen
                        // deletes pages too and was never given the same
                        // cleanup. The seo_link rows matter in both directions:
                        // a link from a deleted page still counts as an
                        // incoming link when the graph decides whether another
                        // page is an orphan, so leaving them hides real ones.
                        if (pg_schema_has('seo_issue')) {
                            db("DELETE FROM seo_issue WHERE (entity_type = 'page') AND (entity_id = '" . (int) $page_id . "')");
                        }

                        if (pg_schema_has('seo_link')) {
                            db("DELETE FROM seo_link WHERE (from_type = 'page') AND (from_id = '" . (int) $page_id . "')");
                            db("DELETE FROM seo_link WHERE (to_type = 'page') AND (to_id = '" . (int) $page_id . "')");
                        }

                        // call the function that updates the tag cloud table
                        update_tag_cloud_keywords_for_page($page_id, 0, '', $original_page_search, $original_search_keywords);
                        
                        // if this is a search results page, then call the function responsible for removing the keywords for this page from the tag cloud
                        if ($page_type == 'search results') {
                            delete_tag_cloud_keywords_for_search_results_page($page_id);
                        }
                        
                        // delete regions
                        $query = "DELETE FROM pregion WHERE pregion_page = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        
                        // delete form fields for page
                        $query = "DELETE FROM form_fields WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        
                        // delete form field options for page
                        $query = "DELETE FROM form_field_options WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');

                        // Delete target options for page.
                        db("DELETE FROM target_options WHERE page_id = '" . escape($page_id) . "'");
                        
                        // if this page is a form list view, delete filters and form_view_directories_form_list_views_xref records
                        if ($page_type == 'form list view') {
                            $query = "DELETE FROM form_list_view_filters WHERE page_id = ?";
                            $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');

                            $query = "DELETE FROM form_list_view_browse_fields WHERE page_id = ?";
                            $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                            
                            $query = "DELETE FROM form_view_directories_form_list_views_xref WHERE form_list_view_page_id = ?";
                            $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        }
                        
                        // if this page is a form view directory, delete form_view_directories_form_list_views_xref records
                        if ($page_type == 'form view directory') {
                            $query = "DELETE FROM form_view_directories_form_list_views_xref WHERE form_view_directory_page_id = ?";
                            $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        }
                        
                        // delete views for this page that the form view directory feature uses
                        pg_sfv_delete_views('page_id', $page_id);
                        
                        // delete calendar_views_calendars_xref records
                        $query = "DELETE FROM calendar_views_calendars_xref WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        
                        // delete calendar_event_views_calendars_xref records
                        $query = "DELETE FROM calendar_event_views_calendars_xref WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        
                        // if page type has a table for properties, delete page type record of properties
                        if (check_for_page_type_properties($page_type) == true) {
                            $page_type_table_name = str_replace(' ', '_', $page_type) . '_pages';
                            
                            $query = "DELETE FROM $page_type_table_name WHERE page_id = '" . escape($page_id) . "'";
                            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                        }
                        
                        // get comment attachments for this page, so they can be deleted
                        $query = 
                            "SELECT
                                comments.id as comment_id,
                                files.id,
                                files.name
                            FROM comments
                            LEFT JOIN files ON comments.file_id = files.id
                            WHERE
                                (comments.page_id = ?)
                                AND (files.id IS NOT NULL)";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        
                        $attachments = array();
                        
                        // loop through the attachments in order to add them to array
                        while ($row = mysqli_fetch_assoc($result)) {
                            $attachments[] = $row;
                        }
                        
                        // loop through the attachments so they can be deleted
                        foreach ($attachments as $attachment) {
                            // check if the file attachment is used by another comment (multiple comments can share the same file attachment when pages are duplicated)
                            $query = "SELECT id FROM comments WHERE (file_id = ?) AND (id != ?)";
                            $result = db_q($query, array((string) $attachment['id'], (string) $attachment['comment_id'])) or output_error('Query failed.');
                            
                            // if the file attachment is not used by another comment, then delete the file
                            if (mysqli_num_rows($result) == 0) {
                                // delete file from database
                                $query = "DELETE FROM files WHERE id = ?";
                                $result = db_q($query, array((string) $attachment['id'])) or output_error('Query failed.');
                                
                                // delete file on file system
                                @unlink(FILE_DIRECTORY_PATH . '/' . $attachment['name']);
                                
                                // log that the file was deleted
                                log_activity(lang(array('string' => 'file attachment ({var:1}) for a comment was deleted because the page ({var:2}) was deleted', 'vars' => array($attachment['name'], $page_name))), $_SESSION['sessionusername']);
                            }
                        }
                        
                        // delete comments for page
                        $query = "DELETE FROM comments WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        
                        // delete submitted_form_info for page
                        $query = "DELETE FROM submitted_form_info WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');

                        // delete allow new comments data for page
                        $query = "DELETE FROM allow_new_comments_for_items WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        
                        // delete watchers for this page
                        $query = "DELETE FROM watchers WHERE page_id = ?";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');

                        // Check if this page has short links, in order to determine if we need to delete them and update rewrite file.
                        $query =
                            "SELECT COUNT(*)
                            FROM short_links
                            WHERE
                                (destination_type = 'page')
                                AND (page_id = ?)";
                        $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        $row = mysqli_fetch_row($result);

                        // If a short link exists, then delete short links for this page
                        // and remember that we need to update short links in rewrite file later.
                        if ($row[0] != 0) {
                            $query =
                                "DELETE FROM short_links
                                WHERE
                                    (destination_type = 'page')
                                    AND (page_id = ?)";
                            $result = db_q($query, array((string) $page_id)) or output_error('Query failed.');
                        }

                        db("DELETE FROM preview_styles WHERE page_id = '" . escape($page_id) . "'");
                        
                        // If a layout file exists, then delete it.
                        if (file_exists(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php')) {
                            unlink(LAYOUT_DIRECTORY_PATH . '/' . $page_id . '.php');
                        }

                        $number_of_pages++;
                    }
                }

                if ($number_of_binned_pages > 0) {
                    if ($number_of_binned_pages == 1) {
                        $binned_message = lang(array('string' => '{var:1} page was moved to the Recycle Bin.', 'vars' => $number_of_binned_pages));
                    } else {
                        $binned_message = lang(array('string' => '{var:1} pages were moved to the Recycle Bin.', 'vars' => $number_of_binned_pages));
                    }

                    log_activity($binned_message, $_SESSION['sessionusername']);
                    $liveform->add_notice($binned_message
                        . ($binned_home_page ? ' ' . lang('The home page was among them: the site has no home page until another page is marked as home.') : ''));
                }

                if ($number_of_missing_pages > 0) {
                    $liveform->add_notice(($number_of_missing_pages == 1)
                        ? lang('One of the selected pages no longer exists, so it was skipped.')
                        : lang(array('string' => '{var:1} of the selected pages no longer exist, so they were skipped.', 'vars' => $number_of_missing_pages)));
                }

                if (count($bin_errors) > 0) {
                    $liveform->mark_error('bin_error', h(implode(' ', array_unique($bin_errors))));
                }

                // if more than 0 pages were deleted, then log activity
                if ($number_of_pages > 0) {
                    if ($number_of_pages == 1) {
                        $deleted_message = lang(array('string' => '{var:1} page was deleted.', 'vars' => $number_of_pages));
                    } else {
                        $deleted_message = lang(array('string' => '{var:1} pages were deleted.', 'vars' => $number_of_pages));
                    }

                    log_activity($deleted_message, $_SESSION['sessionusername']);
                    $liveform->add_notice($deleted_message);
                }
                
                if ($access_error_exists == true) {
                    $liveform->mark_error('access_error', lang('At least one page could not be deleted, because you do not have access to the page(s).'));
                }
                
                if ($submitted_forms_error_exists == true) {
                    $liveform->mark_error('submitted_forms_error', lang('At least one page could not be deleted, because there are submitted forms for the page(s).  All submitted forms for the page(s) must be deleted before the page(s) are allowed to be deleted.'));
                }
                
                break;
        }
    }
    
    // If there is a send to value then send user back to that screen
    if ((isset($_POST['send_to']) == TRUE) && ($_POST['send_to'] != '')) {
        header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
        
    // else send user to the default view
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_pages.php');
    }
}
?>
