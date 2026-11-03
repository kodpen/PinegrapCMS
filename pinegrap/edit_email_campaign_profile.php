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
validate_email_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM email_campaign_profiles WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');
$liveform = new liveform('edit_email_campaign_profile');

// Get profile data that we will use later.
$email_campaign_profile = db_item(
    "SELECT
        id,
        name,
        enabled,
        action,
        action_item_id,
        subject,
        format,
        body,
        page_id,
        from_name,
        from_email_address,
        reply_email_address,
        bcc_email_address,
        schedule_time,
        schedule_length,
        schedule_unit,
        schedule_period,
        schedule_base,
        purpose,
        created_user_id
    FROM email_campaign_profiles
    WHERE id = '" . escape($_REQUEST['id']) . "'");

// If the user does not have access to this profile, then output error.
if (
    (USER_ROLE == 3)
    && (USER_ID != $email_campaign_profile['created_user_id'])
) {
    log_activity(lang('access denied to edit campaign profile because user does not have access to it'), $_SESSION['sessionusername']);
    output_error(lang('Access denied') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
}

// If the form was not just submitted, then show form.
if (!$_POST) {
    // If the form has not been submitted at all yet, pre-populate fields with data.
    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('name', $email_campaign_profile['name']);
        $liveform->assign_field_value('enabled', $email_campaign_profile['enabled']);
        $liveform->assign_field_value('action', $email_campaign_profile['action']);

        // The record the action is tied to goes into that action's picker.
        $action_item_field = pg_email_campaign_profile_action_item_field($liveform->get_field_value('action'));

        if ($action_item_field != '') {
            $liveform->assign_field_value($action_item_field, $email_campaign_profile['action_item_id']);
        }

        $liveform->assign_field_value('subject', $email_campaign_profile['subject']);
        $liveform->assign_field_value('format', $email_campaign_profile['format']);
        $liveform->assign_field_value('body', $email_campaign_profile['body']);
        $liveform->assign_field_value('page_id', $email_campaign_profile['page_id']);
        $liveform->assign_field_value('bcc_email_address', $email_campaign_profile['bcc_email_address']);
        $liveform->assign_field_value('from_name', $email_campaign_profile['from_name']);
        $liveform->assign_field_value('from_email_address', $email_campaign_profile['from_email_address']);
        $liveform->assign_field_value('reply_email_address', $email_campaign_profile['reply_email_address']);

        // If the schedule time is not "00:00:00" then populate field.
        if ($email_campaign_profile['schedule_time'] != '00:00:00') {
            $liveform->assign_field_value('schedule_time', prepare_form_data_for_output($email_campaign_profile['schedule_time'], 'time'));
        }

        $liveform->assign_field_value('schedule_length', $email_campaign_profile['schedule_length']);
        $liveform->assign_field_value('schedule_unit', $email_campaign_profile['schedule_unit']);
        $liveform->assign_field_value('schedule_period', $email_campaign_profile['schedule_period']);
        $liveform->assign_field_value('schedule_base', $email_campaign_profile['schedule_base']);

        $liveform->set('purpose', $email_campaign_profile['purpose']);
    }

    // A stored trigger item whose row is gone gets a notice above its picker.
    $action_item_gone = pg_email_campaign_profile_action_item_gone(
        $email_campaign_profile['action'],
        $email_campaign_profile['action_item_id'],
        pg_email_campaign_profile_action_item_row($email_campaign_profile['action'], $email_campaign_profile['action_item_id'])
    );

    // An HTML profile whose stored body page row is gone gets a notice above
    // the Page picker.
    $page_gone = ($email_campaign_profile['format'] == 'html')
        && ((int) $email_campaign_profile['page_id'] > 0)
        && (db_value("SELECT page_id FROM page WHERE page_id = '" . (int) $email_campaign_profile['page_id'] . "'") === null);

    $delete_confirmation = pg_email_campaign_profile_delete_confirmation(
        pg_email_campaign_profile_follower_count($id, pg_email_campaign_profile_follow_map())
    );

    echo
    pg_page_shell(
        array(
            'title'=> lang('Edit Campaign Profile'),
            'extra classes'=>'campaign',
            'icon'=>'campaign', 
            'heading'=> lang('Edit Campaign Profile'),
            'heading_description' => lang('Modify the Campaign that is created automatically when a certain action is completed (e.g. Visitor reserves Calendar Event).'),
            'cancel'=>array('enable'=>'true','url'=>'view_email_campaign_profiles.php')
        ,
            'breadcrumb' => array(array('label' => lang('My Campaign Profiles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php'), array('label' => lang('Edit Campaign Profile'))),
        )
    )    . '
<main id="content" class="container-fluid">
    ' . pg_screen_toolbar(array(
        'secondary' => array(
            '<a class="btn btn-sm btn-text theme-secondary" href="duplicate_email_campaign_profile.php?id=' . h($_GET['id']) . get_token_query_string_field() . '"><i class="bi bi-copy me-1" aria-hidden="true"></i>' . lang('Duplicate') . '</a>',
        ),
    )) . '
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <form action="edit_email_campaign_profile.php" method="post">
        ' . get_token_field() . '
        ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$_GET['id'])) . '
        <div class="row">
            ' . pg_email_campaign_profile_form_cards($liveform, $user, $liveform->get_field_value('product_id'), array(
                'custom_form_page_id' => ($email_campaign_profile['action'] == 'custom_form_submitted') ? (int) $email_campaign_profile['action_item_id'] : 0,
                'page_id' => (int) $email_campaign_profile['page_id'],
                'email_campaign_profile_id' => ($email_campaign_profile['action'] == 'email_campaign_sent') ? (int) $email_campaign_profile['action_item_id'] : 0,
                'action_item_gone' => $action_item_gone ? $email_campaign_profile['action'] : '',
                'page_gone' => $page_gone,
            ), $id) . '
            <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                            <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . h($delete_confirmation) . '"><span class="material-icons me-3" aria-hidden="true">delete</span><span class="pg-btn-label">' . lang(array('string'=>'Delete') ) . '</span></button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </form>
</main>' .
    output_footer();
    
    $liveform->remove_form();

// Otherwise the form was just submitted, so process form.
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();

    // If the user selected to delete this profile, then delete it.
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        db_q("DELETE FROM email_campaign_profiles WHERE id = ?", array($id)) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'campaign profile ({var:1}) was deleted','vars'=>$email_campaign_profile['name'])), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_email_campaign_profiles = new liveform('view_email_campaign_profiles');
        $liveform_view_email_campaign_profiles->add_notice(lang('The campaign profile has been deleted.'));
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php');
        
    // Otherwise the user selected to save the profile, so save it.
    } else {
        pg_email_campaign_profile_form_validate($liveform, $id);

        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . $_SERVER['PHP_SELF'] . '?id=' . $liveform->get_field_value('id'));
            exit();
        }

        $columns = pg_email_campaign_profile_form_columns($liveform);

        $columns['last_modified_user_id'] = (int) USER_ID;

        $assignments = array();

        foreach (array_keys($columns) as $column) {
            $assignments[] = $column . ' = ?';
        }

        $parameters = array_values($columns);
        $parameters[] = $id;

        // Update profile.
        db_q(
            "UPDATE email_campaign_profiles
            SET " . implode(', ', $assignments) . ", last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?",
            $parameters) or output_error('Query failed.');

        log_activity(lang(array('string'=>'campaign profile ({var:1}) was modified','vars'=>$liveform->get_field_value('name'))), $_SESSION['sessionusername']);
        
        $liveform->remove_form();
        $liveform_view_email_campaign_profiles = new liveform('view_email_campaign_profiles');
        $liveform_view_email_campaign_profiles->add_notice(lang('The campaign profile has been saved.'));

        // Forward user to view email campaign profiles screen.
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_email_campaign_profiles.php');
    }
}
