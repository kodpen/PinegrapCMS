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
validate_area_access($user, 'designer');

require_once(PG_FUNCTIONS_DIR . '/includes/forms/custom_style_form.php');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM style WHERE style_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// If the form was not just submitted, then continue to output the page.
if (!$_POST) {
    // Get style information.
    $query = 
        "SELECT
           style.style_id,
           style.style_name,
           style.style_code,
           style.social_networking_position,
           style.collection,
           style.layout_type,
           style.style_timestamp,
           user.user_username
       FROM style
       LEFT JOIN user ON style.style_user = user.user_id
       WHERE style_id = ?";
    $result = db_q($query, array((string) $_REQUEST['id'])) or output_error(lang('Query failed.'));
    $row = mysqli_fetch_array($result);

    // The id comes from the request and can point at a style that was
    // deleted meanwhile; without this the screen renders an empty editor.
    if (!$row) {
        output_error(lang('Sorry, the item could not be found.'), 404);
    }

    $style_id = $row['style_id'];
    $style_name = $row['style_name'];
    $style_timestamp = $row['style_timestamp'];
    $style = $row;
    
    // Faz 3c: same-host whitelisted cancel URL (avoids open-redirect via ?send_to=)
    $output_cancel_button_url = pg_safe_back_url(
        isset($_GET['send_to']) ? ($_GET['send_to'] ?? '') : '',
        OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'
    );

    $output_header = pg_page_shell( array('cancel'=>array('enable'=>'true','url'=>$output_cancel_button_url) ,
            'breadcrumb' => array(array('label' => lang('All Page Styles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'), array('label' => lang('Edit Custom Page Style'))),
        ) ) . '<main id="content" class="container-fluid">';
    
    if (isset($row['user_username']) == TRUE) {
        $user_username = $row['user_username'];
    } else {
        $user_username = '[' . lang('Unknown') . ']';
    }

    
    // Find if style is being used by a folder
    $query =
        "SELECT
            COUNT(folder_id)
        FROM folder
        WHERE
            (folder_style = ?)
            OR (mobile_style_id = ?)";
    $result = db_q($query, array((string) $style_id, (string) $style_id)) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $folders_using_style = $row[0];
    
    // Find if style is being used by a page
    $query =
        "SELECT
            COUNT(page_id)
        FROM page
        WHERE
            (page_style = ?)
            OR (mobile_style_id = ?)";
    $result = db_q($query, array((string) $style_id, (string) $style_id)) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $pages_using_style = $row[0];

    // if style is being used by either a folder or a page
    if (($folders_using_style > 0) || ($pages_using_style > 0)) {
        // output delete button with alert
        $output_delete_button = '<button type="button" value="Delete" class="btn my-1 btn-solid theme-danger "  data-confirm-content="' . lang('You may not delete this page style because it is being used by at least one folder or page.') . '" title="' . lang('You may not delete this page style because it is being used by at least one folder or page.') . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';
       
    } else {
        // output regular delete button
        // Once the bin holds styles, a deleted style goes there.
        require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
        $delete_confirmation = pg_recycle_style_ready()
            ? lang('This page style will be moved to the Recycle Bin.')
            : lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('page style'))));
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . $delete_confirmation . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }

    print
    $output_header . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update this HTML template that is applied to all associated pages.') . '" title="' . lang('Edit Custom Page Style') . '">[' . h($style_name) . ']</h2>
                        <p>' . lang('Last Modified') . ': ' . get_relative_time(array('timestamp' => $style_timestamp)) . ' ' . h($user_username) . '</p>
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_style.php?id=' . h(escape_javascript(urlencode($_REQUEST['id']))) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_custom_style.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="'. h($_REQUEST['id']) .'">
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    ' . pg_custom_style_form_cards($style, SOCIAL_NETWORKING == TRUE) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save_and_return" name="submit_save_and_return" value="Save & Return" class="btn my-1  btn-solid theme-primary"><span class="material-icons me-3">arrow_backsave</span><span class="pg-btn-label">' . lang(array('string'=>'Save & Return') ) . '</span></button>
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . $output_delete_button . '
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
        output_footer();
}
else
{
    validate_token_field();
    
    // Start liveform
    include_once('liveform.class.php');
    
    // if style was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        
        // Find if style is being used by a folder
        $query =
            "SELECT
                COUNT(folder_id)
            FROM folder
            WHERE
                (folder_style = ?)
                OR (mobile_style_id = ?)";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''), (string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $folders_using_style = $row[0];
        
        // Find if style is being used by a page
        $query =
            "SELECT
                COUNT(page_id)
            FROM page
            WHERE
                (page_style = ?)
                OR (mobile_style_id = ?)";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''), (string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $pages_using_style = $row[0];

        // if style is being used by either a folder or a page
        if (($folders_using_style > 0) || ($pages_using_style > 0)) {
            
            // output error
            output_error(lang('You may not delete this page style because it is being used by at least one folder or page.'));
        
        // else delete the style: to the Recycle Bin once it holds styles,
        // where it can be restored from the file manager
        } else {
            require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

            if (pg_recycle_style_ready()) {
                $recycled = pg_recycle_style((int) ($_POST['id'] ?? 0), $user);

                if ($recycled['status'] != 'binned') {
                    output_error(h($recycled['message']));
                }

                $notice = lang('The style was moved to the Recycle Bin.');

            } else {
                $result=db_q("DELETE FROM style WHERE style_id = ?", array((string) ($_POST['id'] ?? ''))) or output_error('Query failed');

                db("DELETE FROM preview_styles WHERE style_id = '" . escape($_POST['id'] ?? '') . "'");

                log_activity(lang(array('string'=>'style ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
                $notice = lang('The style was deleted successfully.');
            }
        }
    
    // Else update the style.
    } else {
        $values = pg_custom_style_form_values($_POST, SOCIAL_NETWORKING == TRUE);
        $name = $values['style_name'];

        // update style
        $result = db_q(
            "UPDATE style SET " . implode(' = ?, ', array_keys($values)) . " = ?, style_timestamp = UNIX_TIMESTAMP(), style_user = ? WHERE style_id = ?",
            array_merge(array_values($values), array((string) $user['id'], (string) ($_POST['id'] ?? '')))
        ) or output_error('Query failed');
        log_activity(lang(array('string'=>'style ({var:1}) was modified','vars'=>$name)), $_SESSION['sessionusername']);
        $notice = lang('The style was edited successfully.');
    }

    // If user clicked save button, then forward user back to this screen again.
    if (($_POST['submit_save'] ?? '') == 'Save') {
        $send_to = '' ;

        if (($_POST['send_to'] ?? '') != '') {
            $send_to = '&send_to=' . urlencode($_POST['send_to']);
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_custom_style.php?id=' . urlencode($_POST['id']) . $send_to);
        exit();

    // Otherwise the user clicked one of the other buttons (e.g. Save & Return or Delete),
    // so determine where the user should be sent.
    } else {
        // If there is a send to set, then forward user to send to.
        if (($_POST['send_to'] ?? '') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path(($_POST['send_to'] ?? '')));
            exit();
            
        // Otherwise there is not a send to set, so send user to view styles screen.
        } else {
            $liveform_view_styles = new liveform('view_styles');
            $liveform_view_styles->add_notice($notice);
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_styles.php');
            exit();
        }
    }
}
?>