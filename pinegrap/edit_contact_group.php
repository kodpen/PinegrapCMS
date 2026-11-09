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

// The rule editor's pickers (pg_audience_editor.js) search here and answer
// in JSON, a refusal too. Rules are a Manager's or above.
if (($_GET['request'] ?? '') === 'pick') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');

    if (!USER_LOGGED_IN || !defined('USER_ROLE') || (USER_ROLE >= 3)) {
        http_response_code(403);
        echo json_encode(array('results' => array()));
        exit();
    }

    $results = pg_audience_picker_search(is_string($_GET['kind'] ?? null) ? $_GET['kind'] : '', $_GET['q'] ?? '');

    echo json_encode(array('results' => $results), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

$user = validate_user();
validate_contacts_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page. The id is the one the handlers below act on (POST on a
// submit, GET on the form), never $_REQUEST, which a cookie of the same name
// can feed past the access check.
$requested_contact_group_id = $_POST ? ($_POST['id'] ?? '') : ($_GET['id'] ?? '');
$id = is_scalar($requested_contact_group_id) ? (int) $requested_contact_group_id : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM contact_groups WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// if user does not have access to edit contact group, output error
if (validate_contact_group_access($user, $id) == false) {
    log_activity(lang('access denied to edit contact group'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');
$liveform = new liveform('edit_contact_group', $id);

// The rule state of the group, once the upgrade has added the columns.
$audience_ready = pg_audience_ready();
$may_edit_rules = $audience_ready && ($user['role'] < 3);
$group_rules = array('id' => $id, 'kind' => 'static', 'rules' => '', 'computed_at' => 0, 'compute_cursor' => 0);

if ($audience_ready) {
    $group_rules = db_item("SELECT id, kind, rules, computed_at, compute_cursor FROM contact_groups WHERE id = '" . $id . "'") ?: $group_rules;
}

// get number of contacts in this contact group; the members a group filled by
// rules got from its rules do not hold its delete back, they are derived
$query = "SELECT COUNT(contact_id) FROM contacts_contact_groups_xref WHERE contact_group_id = ?" . (($group_rules['kind'] === 'dynamic') ? " AND source <> 'rule'" : '');
$result = db_q($query, array((string) $id)) or output_error('Query failed.');
$row = mysqli_fetch_row($result);
$number_of_contacts = $row[0];

if (!$_POST) {
    // if edit contact group screen has not been submitted already, pre-populate fields with data
    if (isset($_SESSION['software']['liveforms']['edit_contact_group'][$id]) == false) {
        // get contact group data
        $query =
            "SELECT
                name,
                email_subscription,
                email_subscription_type,
                description
            FROM contact_groups
            WHERE id = ?";
        $result = db_q($query, array((string) $id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('email_subscription', $row['email_subscription']);
        $liveform->assign_field_value('email_subscription_type', $row['email_subscription_type']);
        $liveform->assign_field_value('description', $row['description']);
        $liveform->assign_field_value('kind', $group_rules['kind']);
        $liveform->assign_field_value('rules', (string) $group_rules['rules']);
    }
    
    // Every member, the rule-added ones included, for the Summary.
    $member_count = (int) db_value("SELECT COUNT(*) FROM contacts_contact_groups_xref WHERE contact_group_id = '" . (int) $id . "'");

    // A static group's hand, form and product rows, reviewed when it is
    // switched to rules.
    $switch_review = ($may_edit_rules && ($group_rules['kind'] !== 'dynamic')) ? pg_audience_switch_counts($id) : null;

    // if there are no contacts in this contact group, allow delete
    if ($number_of_contacts == 0) {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('contact group')))) . '"><span class="material-icons me-3" aria-hidden="true">delete</span><span class="pg-btn-label">' . lang(array('string'=>'Delete') ) . '</span></button>';
        
    // else there is at least one contact in this contact group, so disable delete button
    } else {
        $output_delete_button = '<span class="d-inline-block" title="' . h(lang('Please delete or remove all contacts from this contact group before deleting this contact group.')) . '"><button type="button" class="btn my-1 btn-solid theme-danger" disabled aria-describedby="delete_blocked_reason"><span class="material-icons me-3" aria-hidden="true">delete</span><span class="pg-btn-label">' . lang(array('string'=>'Delete') ) . '</span></button></span><span id="delete_blocked_reason" class="visually-hidden">' . h(lang('Please delete or remove all contacts from this contact group before deleting this contact group.')) . '</span>';
    }

    print
    pg_page_shell(
        array(
            'title'=> lang('Edit Contact Group'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Edit Contact Group'),
            'heading_description' => lang('Update this contact group and its subscription features.'),
            'cancel'=>array('enable'=>'true','url'=>'view_contact_groups.php'),
        
            'breadcrumb' => array(array('label' => lang('All Contact Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contact_groups.php'), array('label' => lang('Edit Contact Group'))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                
                <form name="form" action="edit_contact_group.php" method="post" >
                    ' . get_token_field() . '
                    ' . pg_contact_group_form_layout(
                        pg_contact_group_form_cards($liveform, array(
                            'required' => true,
                            'cards' => ($audience_ready ? pg_contact_group_rules_card($liveform, $group_rules, $may_edit_rules, $switch_review) : ''),
                            'status' => pg_contact_group_form_members_status($member_count, $audience_ready ? (string) $group_rules['kind'] : null),
                        )),
                        '
                                        <button type="submit" id="create_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                        ' . $output_delete_button) . '
                    <input type="hidden" name="id" value="' . $id . '">
                </form>
            </div>
        </div>
    
</main>' .
    ($may_edit_rules ? pg_contact_group_rules_scripts() : '') .
    output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    // if contact group was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // if there are no contacts in this contact group, proceed with deleting contact group
        if ($number_of_contacts == 0) {
            // get contact group name for log
            $query = "SELECT name FROM contact_groups WHERE id = ?";
            $result = db_q($query, array((string) $id)) or output_error('Query failed.');
            $row = mysqli_fetch_assoc($result);
            $contact_group_name = $row['name'];
            
            // delete contact group
            $query = "DELETE FROM contact_groups WHERE id = ?";
            $result = db_q($query, array((string) $id)) or output_error('Query failed.');
            
            // delete contact group references in contacts_contact_groups_xref
            $query = "DELETE FROM contacts_contact_groups_xref WHERE contact_group_id = ?";
            $result = db_q($query, array((string) $id)) or output_error('Query failed.');
            
            // delete contact group references in users_contact_groups_xref
            $query = "DELETE FROM users_contact_groups_xref WHERE contact_group_id = ?";
            $result = db_q($query, array((string) $id)) or output_error('Query failed.');
            
            // delete contact group references in opt_in
            $query = "DELETE FROM opt_in WHERE contact_group_id = ?";
            $result = db_q($query, array((string) $id)) or output_error('Query failed.');
            
            // delete contact group references in contact_groups_email_campaigns_xref
            $query = "DELETE FROM contact_groups_email_campaigns_xref WHERE contact_group_id = ?";
            $result = db_q($query, array((string) $id)) or output_error('Query failed.');

            // Its SMS campaign rows, once the upgrade has created the table.
            if (pg_schema_has('contact_groups_sms_campaigns_xref')) {
                db_q("DELETE FROM contact_groups_sms_campaigns_xref WHERE contact_group_id = ?", array((string) $id)) or output_error('Query failed.');
            }

            // A recompute left half way keeps its state in a file.
            if ($audience_ready) {
                pg_audience_state_write($id, null);
            }

            log_activity(lang(array('string'=>'contact group ({var:1}) was deleted','vars'=>$contact_group_name)), $_SESSION['sessionusername']);
            $liveform->remove_form();
            $liveform_view_contact_groups = new liveform('view_contact_groups');
            $liveform_view_contact_groups->add_notice(lang('The contact group has been deleted.'));
            
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_contact_groups.php');
            
            
        
        // else there is at least one contact in this contact group, so prepare error
        } else {
            $liveform->add_fields_to_session();
            
            $liveform->mark_error('', lang('Please delete or remove all contacts from this contact group before deleting this contact group.'));
            
            // forward user to edit contact group screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact_group.php?id=' . $id);
        }
        
    // else contact group was not selected for delete
    } else {
        $liveform->add_fields_to_session();
        pg_contact_group_form_validate_name($liveform, (string) $id);
        $posted_rules = $may_edit_rules ? pg_audience_editor_posted($_POST) : null;

        if ($posted_rules && ($posted_rules['kind'] === 'dynamic') && ($posted_rules['count'] === 0)) {
            $liveform->mark_error('kind', lang('Add at least one condition, or turn off Fill this group by rules.'));
        }
        
        // if there is an error, forward user back to edit contact group screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact_group.php?id=' . $id);
            exit();
        }
        
        // update contact group
        $query =
            "UPDATE contact_groups
            SET
                name = ?,
                email_subscription = ?,
                email_subscription_type = ?,
                description = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array_merge(pg_contact_group_form_values($liveform), array((string) $user['id'], (string) $id))) or output_error('Query failed.');

        // A group that becomes filled by rules, or whose rules change, is due
        // at the next general job tick (computed_at 0); its hand rows stay.
        if ($posted_rules) {
            $due = (($posted_rules['kind'] === 'dynamic') && (($group_rules['kind'] !== 'dynamic') || pg_audience_rules_changed($group_rules['rules'], $posted_rules['rules'])));

            db_q('UPDATE contact_groups SET kind = ?, rules = ?' . ($due ? ', computed_at = 0' : '') . ' WHERE id = ?', array($posted_rules['kind'], $posted_rules['rules'], $id)) or output_error('Query failed.');

            // A pass that stopped part-way would go on from its cursor with
            // the old rules' ids: a change of rules or of kind starts the
            // next pass from the first contact with an empty state.
            if ($due || ($posted_rules['kind'] !== $group_rules['kind'])) {
                pg_audience_pass_restart($id) or output_error('Query failed.');
            }

            // The switch review's choice for the rows the group had while
            // static runs once the switch is saved; keep touches nothing.
            if (($posted_rules['kind'] === 'dynamic') && ($group_rules['kind'] !== 'dynamic')) {
                pg_audience_switch_apply($id, pg_audience_switch_action($_POST)) or output_error('Query failed.');
            }
        }
        log_activity(lang(array('string'=>'contact group (name: {var:1}) was modified','vars'=>$liveform->get_field_value('name') )), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_contact_groups = new liveform('view_contact_groups');
        $liveform_view_contact_groups->add_notice(lang('The contact group has been saved.'));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_contact_groups.php');
        
        
    }
}
?>