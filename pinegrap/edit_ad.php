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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM ads WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if user has a user role and if they do not have access to edit any ad regions, output error
if (($user['role'] == 3) && (count(get_items_user_can_edit('ad_regions', $user['id'])) == 0)) {
    log_activity(lang('access denied because user does not have access to ads'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

// if the user has a user role, then determine if user has access to the ad region that this ad is in
if ($user['role'] == 3) {
    // get this ad's name and ad region id
    $query = "SELECT name, ad_region_id FROM ads WHERE id = ?";
    $result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $name = $row['name'];
    $ad_region_id = $row['ad_region_id'];
    
    // if the user does not have access to the ad region that this ad is in, then log activity and output error
    if (in_array($ad_region_id, get_items_user_can_edit('ad_regions', $user['id'])) == FALSE) {
        log_activity(lang(array('string'=>'access denied because user does not have access to edit ad ({var:1})','vars'=>$name )), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
    }
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/ad_form.php');
$liveform = new liveform('edit_ad');

// if the form has not just been submitted
if (!$_POST) {
    // get ad data
    $query =
        "SELECT 
            ads.name,
            ads.content,
            ads.caption,
            ads.ad_region_id,
            ads.label,
            ads.sort_order,
            ad_regions.name as ad_region_name
        FROM ads
        LEFT JOIN ad_regions ON ads.ad_region_id = ad_regions.id
        WHERE ads.id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    
    $name = $row['name'];
    $ad_region_name = $row['ad_region_name'];
    
    // if the form has not been submitted yet, pre-populate fields with data
    if ($liveform->field_in_session('id') == false) {
        $content = $row['content'];
        $caption = $row['caption'];
        $ad_region_id = $row['ad_region_id'];
        $label = $row['label'];
        $sort_order = $row['sort_order'];
        
        // if the sort order is 0, then set it to empty string
        if ($sort_order == 0) {
            $sort_order = '';
        }
        
        // Assign values to fields
        $liveform->assign_field_value('name', $name);
        $liveform->assign_field_value('content', prepare_rich_text_editor_content_for_output($content));
        $liveform->assign_field_value('caption', prepare_rich_text_editor_content_for_output($caption));
        $liveform->assign_field_value('ad_region_id', $ad_region_id);
        $liveform->assign_field_value('label', $label);
        $liveform->assign_field_value('sort_order', $sort_order);
    }
    
    print
        pg_page_shell([
            'title'=> lang('Edit Ad'),
            'extra classes'=>'ads',
            'icon'=>'ads',
            'heading'=>lang('Edit Ad'),
            'heading_description' => lang('Update this ad and assign it to any existing ad region.'),
            'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php')),
            'breadcrumb' => array(array('label' => lang('All My Ads'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_ads.php?filter=all_ad_regions'), array('label' => lang('Edit Ad'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . get_wysiwyg_editor_code(array('content_textarea', 'caption')) . '
                    ' . $liveform->output_errors() . '
                    ' . $liveform->output_notices() . '
                    
                    <form name="form" action="edit_ad.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                        <input type="hidden" name="send_to" value="' . h(($_GET['send_to'] ?? '')) . '" />
                        ' . pg_ad_form_cards($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class=" btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('ad')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // if the user selected to delete the ad
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // delete ad
        $query = "DELETE FROM ads WHERE id = ?";
        $result = db_q($query, array((string) ($liveform->get_field_value('id')))) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('ad'), $liveform->get_field_value('name') ) )), $_SESSION['sessionusername']);

        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')));
            
        // else there is not a send to, so prepare notice and send user to view ads screen
        } else {
            
            $liveform->remove_form();
            $liveform_view_ads = new liveform('view_ads');
            $liveform_view_ads->add_notice( lang(array('string'=>'{var:1} was deleted successfully','vars'=>lang('Ad') )) );
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_ads.php');
        }
        
        
        exit();
        
    // else the user selected to save the ad region
    } else {
    
        pg_ad_form_validate($liveform, $user, (string) $liveform->get_field_value('id'));
        
        // if there is an error, forward user back to edit ad screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_ad.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
            exit();
        }
        
        // update ad
        $query =
            "UPDATE ads
            SET
                name = ?,
                content = ?,
                caption = ?,
                ad_region_id = ?,
                label = ?,
                sort_order = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(pg_ad_form_values($liveform), array((string) $user['id'], (string) ($_POST['id'] ?? '')))) or output_error('Query failed.');

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('ad'), $liveform->get_field_value('name') ) )), $_SESSION['sessionusername']);
        // if there is a send to, then forward user to send to
        if ($liveform->get_field_value('send_to') != '') {
            header('Location: ' . URL_SCHEME . HOSTNAME . pg_safe_redirect_path($liveform->get_field_value('send_to')));
            
        // else there is not a send to, so prepare notice and send user to view ads screen
        } else {
            
            $liveform->remove_form();
            $liveform_view_ads = new liveform('view_ads');
            $liveform_view_ads->add_notice(lang(array('string'=>'{var:1} was edited successfully','vars'=>lang('Ad') )));
            
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_ads.php');
        }
        
        
        exit();
    }
}
?>