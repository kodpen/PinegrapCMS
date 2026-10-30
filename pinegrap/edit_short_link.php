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
validate_area_access($user, 'manager');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM short_links WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');
$liveform = new liveform('edit_short_link');

// Get short link data that we will use later.
$query =
    "SELECT 
        short_links.id,
        short_links.name,
        short_links.destination_type,
        short_links.page_id,
        short_links.product_group_id,
        short_links.product_id,
        short_links.url,
        short_links.file_id,
        short_links.tracking_code,
        short_links.created_user_id,
        " . (pg_short_link_redirects_ready() ? "short_links.redirect_code, short_links.is_pattern," : "") . "
        page.page_folder AS folder_id,
        page.page_type
    FROM short_links
    LEFT JOIN page ON short_links.page_id = page.page_id
    WHERE short_links.id = ?";
$result = db_q($query, array((string) $_REQUEST['id'])) or output_error('Query failed.');
$short_link = mysqli_fetch_assoc($result);

// If the short link does not exist, then output error.
if ($short_link === null) {
    output_error(lang('Invalid request.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// The Recycle Bin and the short link helpers live with the File Manager.
require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// A link in the Recycle Bin is restored or deleted for good there, not changed
// here: the External API answers the same. The list no longer shows it, so
// this is an old tab or a typed address.
if (pg_short_link_recycle_ready() && ((int) db_value("SELECT recycled FROM short_links WHERE id = '" . $id . "'") === 1)) {
    output_error(lang('This short link is in the recycle bin. Restore it in the file manager before changing it.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 409);
}

// A link with a token for its address has no name for this screen to edit:
// the File Manager shows it for what it is. A guest's way into the workspace
// is the workspace's own.
if ((string) $short_link['name'] === '') {
    if ((string) $short_link['destination_type'] === 'workspace_guest') {
        output_error(lang('Invalid request.'));
    }

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_folders.php?view=short_links&edit=' . (int) $short_link['id']);
    exit();
}

$short_link_file_id = $short_link['file_id'];

// If the form was not just submitted then output form.
if (!$_POST) {
    // If the form has not been submitted at all yet, pre-populate fields with data.
    if ($liveform->field_in_session('id') == FALSE) {
        $liveform->assign_field_value('name', $short_link['name']);
        $liveform->assign_field_value('destination_type', $short_link['destination_type']);

        // Set the page field differently based on the destination type.
        switch ($liveform->get_field_value('destination_type')) {
            case 'page':
                $liveform->assign_field_value('page_id', $short_link['page_id']);
                break;
            
            case 'product_group':
                // If the page is a catalog page, then set page for that field.
                if ($short_link['page_type'] == 'catalog') {
                    $liveform->assign_field_value('catalog_page_id', $short_link['page_id']);

                // Otherwise the page is a catalog detail page, so set page for that field.
                } else {
                    $liveform->assign_field_value('catalog_detail_page_id', $short_link['page_id']);
                }

                break;

            case 'product':
                $liveform->assign_field_value('catalog_detail_page_id', $short_link['page_id']);
                break;

            case 'url':
                $liveform->assign_field_value('url', $short_link['url']);
                break;

            case 'file':
                $liveform->assign_field_value('file', $short_link['file_id']);
                break;
        }

        $liveform->assign_field_value('product_group_id', $short_link['product_group_id']);
        $liveform->assign_field_value('product_id', $short_link['product_id']);
        $liveform->assign_field_value('tracking_code', $short_link['tracking_code']);

        if (isset($short_link['redirect_code'])) {
            $liveform->assign_field_value('redirect_code', (string) (int) $short_link['redirect_code']);
        }
    }

    // A name with a * stands for many addresses: there is no one address to
    // visit or to print.
    $short_link_is_pattern = pg_short_link_is_pattern($short_link['name']);

    // The saved address as a QR code, to print or put on a poster. It leaves
    // the request, so it is built on the configured host (HOSTNAME_SETTING),
    // not on the Host header. A name may hold letters outside ASCII, so it is
    // percent-encoded.
    $short_link_qr_url = URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path($short_link['name']);
    $short_link_qr_options = array('label' => lang('QR code for the short link'), 'class' => 'd-block mx-auto');
    $short_link_qr = $short_link_is_pattern ? '' : pg_qr_svg($short_link_qr_url, 160, $short_link_qr_options);
    $output_short_link_qr_card = '';

    if ($short_link_qr !== '') {
        $short_link_qr_file = pg_ascii_file_name((string) $short_link['name']);

        $output_short_link_qr_card =
            '<div class="col-12 md:col-6 xl:col-4">
                <div class="card my-7">
                    <div class="card-header bg-reset border-0 d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('QR Code')) . '</span>
                        <a class="btn btn-sm btn-outline theme-secondary" download="' . h((($short_link_qr_file !== '') ? $short_link_qr_file : 'short-link') . '-qr.svg') . '" href="' . h(pg_qr_svg_data_uri($short_link_qr_url, 0, $short_link_qr_options)) . '"><i class="bi bi-download me-1" aria-hidden="true"></i>' . h(lang('Download SVG')) . '</a>
                    </div>
                    <div class="card-body text-center">
                        <div class="d-inline-block border rounded-4 p-3 bg-white">' . $short_link_qr . '</div>
                        <p class="small fg-secondary mt-3 mb-0 text-break">' . h($short_link_qr_url) . '</p>
                    </div>
                </div>
            </div>';
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Short Link'),
            'extra classes'=>'page',
            'icon'=>'page', 
            'heading'=>lang('Edit Short Link'),
            'heading_description' => lang('Update this shortcut alias for a Page, Product Group, Product, or URL.'),
            'cancel'=>array('enable'=>'true','url'=>'view_short_links.php')
        ,
            'breadcrumb' => array(array('label' => lang('Short Links'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_short_links.php'), array('label' => lang('Edit Short Link'))),
        )
    )  . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-3  flex-wrap">
                    <div class="col-12 sm:col-12 text-center md:text-start">

                        <nav id="button_bar" class="navigation " aria-label="' . h(lang('Button Bar')) . '">
                            <div class=" btn-group btn-group-sm flex-wrap">
                                ' . ($short_link_is_pattern ? '' : '<a class="btn btn-link pg-link-secondary py-0 mb-3 "  href="' . OUTPUT_PATH . h(encode_url_path($short_link['name'])) . '" target="_blank"><span class="material-icons me-1">link</span>' . lang('Visit') . '</a>') . '
                            </div>
                        </nav>
                    </div>
                </div>
                <form name="form" action="edit_short_link.php" method="post">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$_GET['id'])) . '
                    ' . pg_short_link_form_cards($liveform, array(
                        'extra_rows' => pg_short_link_form_redirect_code_row($liveform),
                        'after_main' => $output_short_link_qr_card,
                        'keep' => array(
                            'page_id' => $short_link['page_id'],
                            'product_group_id' => $liveform->get_field_value('product_group_id'),
                            'product_id' => $liveform->get_field_value('product_id'),
                            'file_id' => $short_link['file_id']))) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . (pg_short_link_recycle_ready() ? lang('This short link will be moved to the Recycle Bin.') : lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('short link'))))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

    $liveform->remove_form();

// Otherwise the form was just submitted, so process it.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    // A field posted as a list (product_id[]=x) counts as not given: the
    // escaping and the checks below stopped the request on it with a 500.
    pg_liveform_single_values($liveform, array('name', 'destination_type', 'page_id', 'catalog_page_id', 'catalog_detail_page_id', 'product_group_id', 'product_id', 'url', 'file', 'tracking_code', 'redirect_code'));

    $handle = @fopen(HTACCESS_FILE_PATH, 'a');

    // If the rewrite file does not exist or can not be written to, then add error and send user back to previous screen.
    if ($handle == FALSE) {
        $liveform->mark_error('', lang(array('string'=>'{var:1} is not writeable so your request could not be completed.','vars'=>HTACCESS_FILE_NAME)) );
        header('Location: ' . URL_SCHEME . HOSTNAME . $_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
        exit();

    // Otherwise the rewrite file is writeable, so close handle.
    } else {
        @fclose($handle);
    }

    // If the user selected to delete this short link, then delete it.
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // With the bin installed the link goes there, the way the File
        // Manager deletes it; before the upgrade it is deleted outright.
        if (pg_short_link_recycle_ready()) {
            pg_short_link_to_bin($short_link['id'], $user);

            log_activity(lang(array('string' => 'The short link, {var:1}, was moved to the Recycle Bin.', 'vars' => h($short_link['name']))), $_SESSION['sessionusername']);

            $delete_notice = lang('The short link was moved to the Recycle Bin.');
        } else {
            $query = "DELETE FROM short_links WHERE id = ?";
            $result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');

            log_activity( lang(array('string'=>'short link ({var:1}) was deleted','vars'=>$short_link['name'])), $_SESSION['sessionusername']);

            $delete_notice = lang('The short link has been deleted.');
        }
        
        $liveform->remove_form();

        $liveform_view_short_links = new liveform('view_short_links');
        $liveform_view_short_links->add_notice($delete_notice);
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_short_links.php');

    // Otherwise the user selected to save the short link, so save it.
    } else {
        $name = pg_short_link_form_validate_name($liveform, $liveform->get_field_value('id'));

        // The page, group, product or file the link already points at may
        // stay while it is in the Recycle Bin (its lists keep it, marked).
        $page_id = pg_short_link_form_validate_destination($liveform, $short_link);

        // How it answers, checked with the name and the destination it goes
        // with, the way the add screen checks it.
        $short_link_answer = pg_short_link_form_answer($liveform, $short_link['id'], $name, isset($short_link['redirect_code']) ? (int) $short_link['redirect_code'] : 0);

        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors() == TRUE) {
            header('Location: ' . URL_SCHEME . HOSTNAME . $_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
            exit();
        }

        $short_link_sql_answer = '';
        $short_link_answer_values = array();

        foreach ($short_link_answer['columns'] as $answer_column => $answer_value) {
            $short_link_sql_answer .= $answer_column . " = ?,
                ";
            $short_link_answer_values[] = (string) (int) $answer_value;
        }

        // Only the destination's own column holds its target; the others
        // are cleared.
        $destination = array('product_group_id' => '', 'product_id' => '', 'url' => '', 'file_id' => '');
        $destination_column = pg_short_link_form_destination_column($liveform->get_field_value('destination_type'));

        if ($destination_column !== null) {
            $destination[$destination_column[0]] = $liveform->get_field_value($destination_column[1]);
        }

        // Update short link.
        $query =
            "UPDATE short_links
            SET
                name = ?,
                destination_type = ?,
                page_id = ?,
                product_group_id = ?,
                product_id = ?,
                url = ?,
                tracking_code = ?,
                file_id = ?,
                " . $short_link_sql_answer . "last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(
            array((string) $name, (string) $liveform->get_field_value('destination_type'), (string) $page_id, (string) $destination['product_group_id'], (string) $destination['product_id'], (string) $destination['url'], (string) $liveform->get_field_value('tracking_code'), (string) $destination['file_id']),
            $short_link_answer_values,
            array((string) USER_ID, (string) $liveform->get_field_value('id'))
        )) or output_error('Query failed.');
        

        log_activity(lang(array('string'=>'short link ({var:1}) was modified','vars'=>$name)), $_SESSION['sessionusername']);


        $liveform->remove_form();
        $liveform_view_short_links = new liveform('view_short_links');
        $liveform_view_short_links->add_notice(lang('The short link has been saved.'));

        // Forward user to view short links screen.
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_short_links.php');
    }
    
    
    exit();
}
?>