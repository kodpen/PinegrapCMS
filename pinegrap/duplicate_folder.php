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

validate_area_access($user, 'user');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}



$form = new liveform('duplicate_folder');

$folder = db_item(
    "SELECT
        folder_id AS id,
        folder_name AS name,
        folder_parent AS parent
    FROM folder
    WHERE folder_id = '" . e($_REQUEST['id']) . "'");

if (!$folder) {
    output_error(lang('Sorry, the folder could not be found.'));
}
// If the user does not have edit access to the parent folder or does not have access to
// create/duplicate pages, then log and show error.
if (!check_edit_access($folder['parent']) or (USER_ROLE == 3 and !$user['create_pages'])) {
    log_activity(lang(array('string'=>'Access denied to duplicate folder ({var:1}) because user does not have edit access to parent folder.','vars'=>array($folder['name']) )) );
    output_error(lang('Access denied.'));
}

// Opened from the File Manager's folder menu: Cancel, the breadcrumb and the
// finished duplicate go back to where it was opened, else to the folder the
// duplicate is made in.
$back_url = pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_folders.php?folder=' . (int) $folder['parent']);

// If the form has not been submitted, then output it.
if (!$_POST) {

    // If the form has not been submitted yet then autofill fields.
    if (!$form->field_in_session('find_and_replace_check')) {

        if (($_SESSION['software']['duplicate_folder']['find_replace'] ?? '') === '1') {
            $form->set('find_and_replace_check', '1');
        }

        $find_replace_keywords = ($_SESSION['software']['duplicate_folder']['find_replace_keywords'] ?? '');

        if ($find_replace_keywords) {
            foreach ($find_replace_keywords as $key => $item) {
                $form->set('find_' . ($key + 1), $item['find']);
                $form->set('replace_' . ($key + 1), $item['replace']);
            }
        }
    }

    // The Summary: the parent the copy is made in and the pages directly
    // inside the folder, the ones the copy takes along.
    $parent_name = (string) db_value("SELECT folder_name FROM folder WHERE folder_id = '" . (int) $folder['parent'] . "'");
    $page_count = (int) db_value("SELECT COUNT(*) FROM page WHERE page_folder = '" . (int) $folder['id'] . "'");

    echo pg_page_shell([
        'title'=> lang('Duplicate Folder'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Duplicate Folder'),
        'cancel'=>array('enable'=>'true','url'=>$back_url),
        'breadcrumb' => array(
            array('label' => lang('File Manager'), 'url' => $back_url),
            array('label' => lang('Duplicate Folder') . ' [' . $folder['name'] . ']'),
        ),
    ]);

    $content = render(array(
        'template' => 'duplicate_folder.php',
        'form' => $form,
        'folder' => $folder,
        'parent_name' => $parent_name,
        'page_count' => $page_count,
        'back_url' => $back_url));

    echo $form->prepare($content);

    echo output_footer();
    
    $form->remove();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();
    
    $form->add_fields_to_session();

    require_once(dirname(__FILE__) . '/duplicate_folder_f.php');

    // The switch posts find_and_replace_check; its rows count only while it is on.
    $find_replace_on = ((string) $form->get('find_and_replace_check') === '1');

    // Remember if find and replace feature was used, so we can auto-open if user uses duplicate
    // folder feature again in this session.
    $_SESSION['software']['duplicate_folder']['find_replace'] = $find_replace_on ? '1' : '';

    $find_replace_fields = array('find_and_replace_check' => $find_replace_on ? '1' : '');
    for ($number = 1; $number <= 10; $number++) {
        $find_replace_fields['find_' . $number] = $form->get('find_' . $number);
        $find_replace_fields['replace_' . $number] = $form->get('replace_' . $number);
    }

    $find_replace_keywords = pg_duplicate_folder_keywords($find_replace_fields);

    if ($find_replace_on) {
        // Store find and replace keywords in session, so we can autofill the same values if the user
        // duplicates a folder again in this session.
        $_SESSION['software']['duplicate_folder']['find_replace_keywords'] = $find_replace_keywords;
    }

    $response = duplicate_folder(array(
        'folder' => $folder,
        'find_replace_keywords' => $find_replace_keywords));

    if ($response['status'] == 'error') {
        output_error(h($response['message']));
    }

    $new_folder = $response['folder'];

    // Back where it was opened, else the duplicate itself.
    $location = pg_safe_redirect_path(pg_send_to_url(''), PATH . SOFTWARE_DIRECTORY . '/view_folders.php?folder=' . (int) $new_folder['id']);

    // The notice goes to the screen the redirect opens.
    $notice_form = pg_redirect_notice_form($location);
    if ($notice_form !== '') {
        $liveform_target = new liveform($notice_form);
        $liveform_target->add_notice(lang(array(
            'string' => 'The folder {var:1} has been created with a copy of {var:2} page(s). Files and sub-folders were not copied.',
            'vars' => array(h($new_folder['name']), (int) ($response['pages_copied'] ?? 0)))));
    }

    $form->remove();

    go($location);
}