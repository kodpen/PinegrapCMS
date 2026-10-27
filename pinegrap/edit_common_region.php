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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM cregion WHERE cregion_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
$liveform = new liveform('edit_common_region');

// A region made a shared component is drawn by the component; its content
// here is no longer read, and the type stays common.
require_once(PG_FUNCTIONS_DIR . '/includes/designer_access.php');
$component_id = pg_common_region_components(array($id))[$id];

if (!isset($_POST['name'])) {
    $query = "SELECT cregion_name, cregion_content, cregion_designer_type "
            ."FROM cregion "
            ."WHERE cregion_id = '" . escape($_GET['id']) . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed');
    $row = mysqli_fetch_array($result);
    $cregion_name = $row['cregion_name'];
    $cregion_content = $row['cregion_content'];
    $cregion_designer_type = $row['cregion_designer_type'];

    if ($component_id) {
        $component_name = (string) db_value("SELECT name FROM shared_components WHERE id = '" . (int) $component_id . "'");
        $output_component =
            '<div class="alert theme-primary my-7">
                <p>' . h(lang(array('string' => 'This common region is now the shared component "{var:1}". Its content is edited in the visual designer, by managers and by the users granted the component; the content kept below is no longer shown on the site.', 'vars' => $component_name))) . '</p>
                <a class="btn btn-sm btn-solid theme-primary" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_system_style.php?component=' . (int) $component_id . '&amp;send_to=' . h(urlencode(get_request_uri())) . '"><span class="material-icons me-1">edit</span>' . h(lang('Edit in the visual designer')) . '</a>
            </div>';
    } elseif (($user['role'] <= 1) && pg_shared_component_content_ready()) {
        $output_component =
            '<form class="alert theme-warning d-flex gap-3 align-items-start my-7" action="edit_common_region.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $id . '">
                <div class="flex-grow-1">
                    <p>' . h(lang('Make this region a shared component to edit its content in the visual designer. Its tag then draws the component on every page; managers and the users granted this region edit the component, and front-end inline editing of the region ends. Deleting the component brings back the content kept here.')) . '</p>
                    <button type="submit" name="submit_convert" value="Convert" class="btn btn-sm btn-solid theme-primary" data-confirm-content="' . h(lang('Make this common region a shared component? Every page will draw the component from now on.')) . '"><span class="material-icons me-1">widgets</span>' . h(lang('Make shared component')) . '</button>
                </div>
            </form>';
    } else {
        $output_component = '';
    }

    if ($component_id) {
        $output_content = '';
    } else {
        $output_content =
            '<h5 class="mt-12">' . lang('Shared Content to appear on associated Pages') . '</h5>
            <div class="col-12 my-3">
                <label for="name" class="form-label">' . lang('Content') . '</label>
                <textarea id="content_textarea" name="content" style="width: 100%" rows="30" wrap="off">' . h(prepare_rich_text_editor_content_for_output($cregion_content)) . '</textarea>
            </div>';
    }

    $output = '';
    $output .= 
    pg_page_shell([
        'title'=> lang('Edit Common Region'),
        'extra classes'=>'page',
        'icon'=>'page',
        'heading'=>lang('Edit Common Region'),
        'heading_description' => lang('Update this common region of shared content. (A rename will require its tag to be updated in any page styles.)'),
        'cancel'=>array('enable'=>'true','url'=>'view_regions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Common Regions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_common_regions'), array('label' => lang('Edit Common Region'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <p>' . lang('Page Style Body Tag') . ': <strong>' . h('<cregion>' . $cregion_name . '</cregion>') . '</strong></p>
                        
                        <nav id="button_bar" class="navigation " aria-label="Button Bar">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                <a class="btn btn-link pg-link-secondary py-0 mb-3 " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_common_region.php?id=' . h(escape_javascript(urlencode($_REQUEST['id']))) . get_token_query_string_field() . '"><span class="material-icons me-1">control_point_duplicate</span>' . lang('Duplicate') . '</a>
                            </div>
                        </nav>
                    </div>
                </div>
                ' . $output_component . '
                <form name="form" action="edit_common_region.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Common Region Name') . '</label>
                                            <div class="input-group">
                                                <div class="input-group-text">' . h('<cregion>') . '</div>
                                                <input value="' . h($cregion_name) . '" name="name" id="name" type="text" class="form-control" maxlength="100" />
                                                <div class="input-group-text">' . h('</cregion>') . '</div>
                                            </div>
                                        </div>
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang('Region Type') . '</label>
                                            <select name="type" id="type" class="form-control"' . ($component_id ? ' disabled' : '') . '><option value="common" selected="selected">' . lang('Common Region') . '</option><option value="designer">' . lang('Designer Region') . '</option></select>
                                            ' . ($component_id ? '<input type="hidden" name="type" value="common">' : '<div class="form-text text-end">' . lang('You can change Region Type') . '</div>') . '
                                        </div>
                                        ' . $output_content . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('common region')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    if (!$component_id) {
        // Get wysiwyg editor code
        $output_wysiwyg_editor_code = get_wysiwyg_editor_code(array('content_textarea'), $activate_editors = true);

        // Puts the wysiwyg code in the header.
        $output = preg_replace('/(<\/head>)/i', $output_wysiwyg_editor_code . '$1', $output);
    }
    
    print $output;

    $liveform->remove_form();

} else {
    validate_token_field();
    
    // Make the region a shared component, then come back to this screen.
    if (($_POST['submit_convert'] ?? '') == 'Convert') {
        if (($user['role'] > 1) || !pg_shared_component_content_ready()) {
            output_error(lang('Access denied.'));
        }

        require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');
        $warnings = array();
        $made = pg_common_region_component($id, $user, $warnings);

        if (!is_array($made)) {
            $liveform->add_error(lang('The region could not be made a shared component.'));
        } else {
            $liveform->add_notice(h(lang(array('string' => 'The common region is now the shared component "{var:1}".', 'vars' => $made['name']))));

            foreach ($warnings as $warning) {
                $liveform->add_warning(h($warning));
            }
        }

        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_common_region.php?id=' . $id);
        exit();
    }

    // if region was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        $query = "DELETE FROM cregion "
                ."WHERE cregion_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        
        // delete users_common_regions_xref records
        $query = "DELETE FROM users_common_regions_xref WHERE common_region_id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('common region'), $_POST['name']) )), $_SESSION['sessionusername']);
        $notice = lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Common Region') ));
    } else {
        $_POST['name'] = trim($_POST['name']);

        if ($_POST['type'] == 'common') {
            $cregion_designer_type = 'no';
        } else {
            $cregion_designer_type = 'yes';
        }

        // A converted region keeps its content and its type: the component
        // draws it.
        if ($component_id) {
            $cregion_designer_type = 'no';
            $sql_content = "";
        } else {
            $sql_content = "cregion_content = '" . escape(prepare_rich_text_editor_content_for_input($_POST['content'] ?? '')) . "', ";
        }

        $query = "UPDATE cregion "
                ."SET cregion_name = '" . escape($_POST['name'] ?? '') . "', "
                    .$sql_content
                    ."cregion_designer_type = '$cregion_designer_type', "
                    ."cregion_user = '" . $user['id'] . "', "
                    ."cregion_timestamp = UNIX_TIMESTAMP() "
                ."WHERE cregion_id = '" . escape($_POST['id'] ?? '') . "'";
        // update region
        $result = mysqli_query(db::$con, $query) or output_error('Query failed');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('common region'), $_POST['name']) )), $_SESSION['sessionusername']);
        $notice = lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Common Region') ));

    }
    
    $liveform_view_styles = new liveform('view_regions');
    $liveform_view_styles->add_notice($notice);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_regions.php?filter=all_' . $_POST['type'] . '_regions');
}