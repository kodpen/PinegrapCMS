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
validate_contacts_access($user);

// if user does not have access to add contact group, output error
if ($user['role'] == 3) {
    log_activity(lang('access denied to add contact group'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_group_form.php');
$liveform = new liveform('add_contact_group');

if (!$_POST) {

    
    print
    pg_page_shell(
        array(
            'title'=> lang('Create Contact Group'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Create Contact Group'),
            'heading_description' => lang('Create a new contact group to collect and organize contacts.'),
            'cancel'=>array('enable'=>'true','url'=>'view_contact_groups.php'),
        
            'breadcrumb' => array(array('label' => lang('All Contact Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contact_groups.php'), array('label' => lang('Create Contact Group'))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                
                <form name="form" action="add_contact_group.php" method="post" >
                    ' . get_token_field() . '
                    ' . pg_contact_group_form_card($liveform, true) . '

                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    pg_contact_group_form_validate_name($liveform, null);
    
    // if there is an error, forward user back to add contact group screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_contact_group.php');
        exit();
    }
    
    // create contact group
    $query =
        "INSERT INTO contact_groups (
            name,
            email_subscription,
            email_subscription_type,
            description,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            UNIX_TIMESTAMP(),
            ?,
            UNIX_TIMESTAMP())";
    $result = db_q($query, array_merge(pg_contact_group_form_values($liveform), array(
        (string) $user['id'],
        (string) $user['id'],
    ))) or output_error('Query failed.');

    
    log_activity(lang(array('string'=>'contact group (name: {var:1}) was created','vars'=>$liveform->get_field_value('name') )), $_SESSION['sessionusername']);

    $liveform->remove_form();

    $liveform_view_contact_groups = new liveform('view_contact_groups');
    $liveform_view_contact_groups->add_notice(lang('The contact group has been created.'));
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_contact_groups.php');
  
    
}
?>