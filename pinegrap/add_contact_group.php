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
validate_contacts_access($user);

// if user does not have access to add contact group, output error
if ($user['role'] == 3) {
    log_activity(lang('access denied to add contact group'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

include_once('liveform.class.php');
$liveform = new liveform('add_contact_group');

if (!$_POST) {

    
    print
    pg_page_shell(
        array(
            'title'=> lang('Create Contact Group'),
            'extra classes'=>'contact',
            'icon'=>'contact', 
            'heading'=>lang('Create Contact Group'),
            'cancel'=>array('enable'=>'true','url'=>'view_contact_groups.php'),
        
            'breadcrumb' => array(array('label' => lang('All Contact Groups'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contact_groups.php'), array('label' => lang('Create Contact Group'))),
        )
    ) . '
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '

                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new contact group to collect and organize contacts.') . '" title="' . lang('Create Contact Group') . '">[' . lang('Contact Group Name') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_contact_group.php" method="post" >
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="name" class="form-label">' . lang('Contact Group Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'placeholder'=>lang('Please enter a name'), 'maxlength'=>'255', 'class'=>'form-control add-header-content-updater', 'required'=>'required')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>

                                        <div class="col-12 my-2">
                                            <div class="form-check form-switch">
                                                ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'email_subscription', 'id'=>'email_subscription', 'value'=>'1', 'class'=>'form-check-input collapse-switcher', 'data-bs-target'=>'#email_subscription_type_row')) . '
                                                <label class="form-check-label" for="email_subscription">' . lang('Enable E-mail Subscription') . '</label>
                                                <div class="form-text">' . lang('Allow Campaigns to send to this Contact Group') . '</div>
                                            </div>
                                        </div>
                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="email_subscription_type_row">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                            <div class="popover-body">
                                                <div class="row">
                                                 
                                                    <div class="col-12 my-3">
                                                        <label class="form-label">' . lang('E-mail Subscription Type') . '</label>
                                                        <div class="form-check">
                                                        ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'open', 'value'=>'open', 'checked'=>'checked', 'class'=>'form-check-input')) . '
                                                            <label class="form-check-label" for="open">' . lang('Open') . '</label>
                                                        </div>
                                                        <div class="form-check">
                                                            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'closed', 'value'=>'closed', 'class'=>'form-check-input')) . '
                                                            <label class="form-check-label" for="closed">' . lang('Closed') . '</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 my-1">
                                                        <label for="description" class="form-label">' . lang('Description') . '</label>
                                                        ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'description', 'id'=>'description', 'class'=>'form-control', 'rows'=>'5', 'cols'=>'50')) . '
                                                        <div class="form-text text-end">' . lang('Description/Subscription Message on My Account Pages') . '</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
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
    
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>lang('Name'))) );
    
    // if there is an error, forward user back to add contact group screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/add_contact_group.php');
        exit();
    }
    
    // check to see if name is already in use by a different contact group
    $query =
        "SELECT id
        FROM contact_groups
        WHERE (name = '" . escape($liveform->get_field_value('name')) . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if name is already in use by a different contact group, prepare error and forward user back to screen
    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        
        // forward user to add contact group screen
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
            '" . escape($liveform->get_field_value('name')) . "',
            '" . escape($liveform->get_field_value('email_subscription')) . "',
            '" . escape($liveform->get_field_value('email_subscription_type')) . "',
            '" . escape($liveform->get_field_value('description')) . "',
            '" . $user['id'] . "',
            UNIX_TIMESTAMP(),
            '" . $user['id'] . "',
            UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    
    log_activity(lang(array('string'=>'contact group (name: {var:1}) was created','vars'=>$liveform->get_field_value('name') )), $_SESSION['sessionusername']);

    $liveform->remove_form();

    $liveform_view_contact_groups = new liveform('view_contact_groups');
    $liveform_view_contact_groups->add_notice(lang('The contact group has been created.'));
    
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_contact_groups.php');
  
    
}
?>