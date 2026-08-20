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
$user = validate_user();
validate_area_access($user, 'designer');

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
       WHERE style_id = '" . escape($_REQUEST['id']) . "'";
    $result = mysqli_query(db::$con, $query);
    $row = mysqli_fetch_array($result);
    $style_id = $row['style_id'];
    $style_name = $row['style_name'];
    $style_timestamp = $row['style_timestamp'];
    $style_code = $row['style_code'];
    $social_networking_position = $row['social_networking_position'];
    $collection = $row['collection'];
    $layout_type = $row['layout_type'];
    
    // Faz 3c: same-host whitelisted cancel URL (avoids open-redirect via ?send_to=)
    $output_cancel_button_url = pg_safe_back_url(
        isset($_GET['send_to']) ? ($_GET['send_to'] ?? '') : '',
        OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'
    );

    $output_header = pg_page_shell( array('cancel'=>array('enable'=>'true','url'=>$output_cancel_button_url) ,
            'breadcrumb' => array(array('label' => lang('All Page Styles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'), array('label' => lang('Edit Custom Page Style'))),
        ) );
    
    $output_social_networking_position = '';

    // If social networking is enabled, then output position pick list.
    if (SOCIAL_NETWORKING == TRUE) {
        $output_top_left_selected = '';
        $output_top_right_selected = '';
        $output_bottom_left_selected = '';
        $output_bottom_right_selected = '';
        $output_disabled_selected = '';

        switch ($social_networking_position) {
            case 'top_left':
                $output_top_left_selected = ' selected="selected"';
                break;

            case 'top_right':
                $output_top_right_selected = ' selected="selected"';
                break;

            case 'bottom_left':
                $output_bottom_left_selected = ' selected="selected"';
                break;

            case 'bottom_right':
                $output_bottom_right_selected = ' selected="selected"';
                break;

            case 'disabled':
                $output_disabled_selected = ' selected="selected"';
                break;
        }

        $output_social_networking_position =
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <label for="social_networking_position" class="form-label">' . lang('Social Networking Position') . '</label>
                <select id="social_networking_position" name="social_networking_position" class="form-select">
                    <option value="top_left"' . $output_top_left_selected . '>' . lang('Top Left') . '</option>
                    <option value="top_right"' . $output_top_right_selected . '>' . lang('Top Right') . '</option>
                    <option value="bottom_left"' . $output_bottom_left_selected . '>' . lang('Bottom Left') . '</option>
                    <option value="bottom_right"' . $output_bottom_right_selected . '>' . lang('Bottom Right') . '</option>
                    <option value="disabled"' . $output_disabled_selected . '>' . lang('Disabled') . '</option>
                </select>
            </div>';
    }

    if ($collection == 'a') {
        $output_collection_a_checked = ' checked="checked"';
        $output_collection_b_checked = '';
    } else {
        $output_collection_a_checked = '';
        $output_collection_b_checked = ' checked="checked"';
    }

    $output_layout_type_system_selected = '';
    $output_layout_type_custom_selected = '';

    if ($layout_type == 'system') {
        $output_layout_type_system_selected = ' selected="selected"';
    } else if ($layout_type == 'custom') {
        $output_layout_type_custom_selected = ' selected="selected"';
    }
    
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
            (folder_style = '" . $style_id . "')
            OR (mobile_style_id = '" . $style_id . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $folders_using_style = $row[0];
    
    // Find if style is being used by a page
    $query =
        "SELECT
            COUNT(page_id)
        FROM page
        WHERE
            (page_style = '" . $style_id . "')
            OR (mobile_style_id = '" . $style_id . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $pages_using_style = $row[0];

    // if style is being used by either a folder or a page
    if (($folders_using_style > 0) || ($pages_using_style > 0)) {
        // output delete button with alert
        $output_delete_button = '<button type="button" value="Delete" class="btn my-1 btn-danger "  data-confirm-content="' . lang('You may not delete this page style because it is being used by at least one folder or page.') . '" title="' . lang('You may not delete this page style because it is being used by at least one folder or page.') . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
       
    } else {
        // output regular delete button
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-danger " data-loading-content="' . lang(array('string'=>'Deleting') ) . '" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('page style')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text" >' . lang(array('string'=>'Delete') ) . '</span></button>';
    }

    print
    $output_header . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Update this HTML template that is applied to all associated pages.') . '" title="' . lang('Edit Custom Page Style') . '">[' . h($style_name) . ']</h2>
                        <p>' . lang('Last Modified') . ': ' . get_relative_time(array('timestamp' => $style_timestamp)) . ' ' . h($user_username) . '</p>
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link link-secondary py-0 mb-2 " data-loading-content="' . lang('Duplicating') . '" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_style.php?id=' . h(escape_javascript(urlencode($_REQUEST['id']))) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_custom_style.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="'. h($_REQUEST['id']) .'">
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Page Style Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-8 col-lg-6 my-2">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            <input value="' . h($style_name) . '" name="name" id="name" type="text" placeholder="' . lang('new page style') . '" class="form-control add-header-content-updater" maxlength="100" required />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label class="form-label">' . lang('HTML Page with embedded Tags') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($style_code) . '</textarea>
                                                ' . get_codemirror_includes() . '
                                                ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Additional Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-2">
                                            <h5>'. lang('Collection') . '</h5>
                                        </div>
                                        <div class="col-12 mb-2">
                                            <div class="form-check form-check-inline">
                                                <input type="radio" name="collection" id="collection_a" value="a"' . $output_collection_a_checked . ' class="form-check-input" />
                                                <label class="form-check-label" for="collection_a">' . lang('Collection') . ' A</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input type="radio" name="collection" id="collection_b" value="b"' . $output_collection_b_checked . ' class="form-check-input" />
                                                <label class="form-check-label" for="collection_b">' . lang('Collection') . ' B</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="layout_type" class="form-label">' . lang('Override Layout Type') . '</label>
                                            <select id="layout_type" name="layout_type" class="form-select">
                                                <option value=""></option>
                                                <option value="system"' . $output_layout_type_system_selected . '>' . lang('System') . '</option>
                                                <option value="custom"' . $output_layout_type_custom_selected . '>' . lang('Custom') . '</option>
                                            </select>
                                        </div>
                                        ' . $output_social_networking_position . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save_and_return" name="submit_save_and_return" value="Save & Return" class="btn my-1  btn-primary" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">arrow_backsave</span><span class="btn-text">' . lang(array('string'=>'Save & Return') ) . '</span></button>
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang(array('string'=>'Saving') ) . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang(array('string'=>'Save') ) . '</span></button>
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
                (folder_style = '" . escape($_POST['id'] ?? '') . "')
                OR (mobile_style_id = '" . escape($_POST['id'] ?? '') . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $folders_using_style = $row[0];
        
        // Find if style is being used by a page
        $query =
            "SELECT
                COUNT(page_id)
            FROM page
            WHERE
                (page_style = '" . escape($_POST['id'] ?? '') . "')
                OR (mobile_style_id = '" . escape($_POST['id'] ?? '') . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $pages_using_style = $row[0];

        // if style is being used by either a folder or a page
        if (($folders_using_style > 0) || ($pages_using_style > 0)) {
            
            // output error
            output_error(lang('You may not delete this page style because it is being used by at least one folder or page.'));
        
        // else delete the style
        } else {
            
            $result=mysqli_query(db::$con, "DELETE FROM style WHERE style_id = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');

            db("DELETE FROM preview_styles WHERE style_id = '" . escape($_POST['id'] ?? '') . "'");
            
            log_activity(lang(array('string'=>'style ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
            $notice = lang('The style was deleted successfully.');
        }
    
    // Else update the style.
    } else {
        $name = trim($_POST['name']);

        $sql_social_networking_position = "";

        // If social networking is enabled, then update position value.
        if (SOCIAL_NETWORKING == TRUE) {
            $sql_social_networking_position = "social_networking_position = '" . escape($_POST['social_networking_position'] ?? '') . "',";
        }

        // update style
        $result = mysqli_query(db::$con, "UPDATE style SET style_name = '" . escape($name) . "', style_code = '" . escape($_POST['code'] ?? '') . "', " . $sql_social_networking_position . "collection = '" . escape($_POST['collection'] ?? '') . "', layout_type = '" . e($_POST['layout_type'] ?? '') . "', style_timestamp = UNIX_TIMESTAMP(), style_user = '" . $user['id'] . "' WHERE style_id = '" . escape($_POST['id'] ?? '') . "'") or output_error('Query failed');
        log_activity(lang(array('string'=>'style ({var:1}) was modified','vars'=>$name)), $_SESSION['sessionusername']);
        $notice = lang('The style was edited successfully.');
    }

    // If user clicked save button, then forward user back to this screen again.
    if (($_POST['submit_save'] ?? '') == 'Save') {
        $send_to = '' ;

        if ($_POST['send_to'] != '') {
            $send_to = '&send_to=' . urlencode($_POST['send_to']);
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_custom_style.php?id=' . urlencode($_POST['id']) . $send_to);
        exit();

    // Otherwise the user clicked one of the other buttons (e.g. Save & Return or Delete),
    // so determine where the user should be sent.
    } else {
        // If there is a send to set, then forward user to send to.
        if ($_POST['send_to'] != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . $_POST['send_to']);
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