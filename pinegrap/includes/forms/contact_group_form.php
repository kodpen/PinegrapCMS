<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The contact group form, shared by the create and the edit screens
 * (add_contact_group.php, edit_contact_group.php): the Main Informations
 * card, the name check and the values a save writes. The screens keep what
 * differs: the edit screen loads the record, checks the user's access to
 * the group and offers delete while the group holds no contact.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The Main Informations card: name, e-mail subscription with its type and
 * the description shown on My Account pages.
 *
 * @param liveform $liveform
 * @param bool $required  Mark the name field required in the browser (create)
 * @return string
 */
function pg_contact_group_form_card($liveform, $required = false)
{
    $required = $required ? array('required' => 'required') : array();

    return '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Main Informations') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 lg:col-4 my-3">
                                            <label for="name" class="form-label">' . lang('Contact Group Name') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'placeholder'=>lang('Please enter a name'), 'maxlength'=>'255', 'class'=>'form-control') + $required) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>

                                        <div class="col-12 my-3">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'email_subscription', 'id'=>'email_subscription', 'value'=>'1', 'class'=>'collapse-switcher no-bs', 'data-bs-target'=>'#email_subscription_type_row')) . '</div>
                                                <label class="" for="email_subscription">' . lang('Enable E-mail Subscription') . '</label>
                                                <div class="form-text">' . lang('Allow Campaigns to send to this Contact Group') . '</div>
                                            </div>
                                        </div>
                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="email_subscription_type_row">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                            <div class="popover-body">
                                                <div class="row">
                                                 
                                                    <div class="col-12 my-5">
                                                        <label class="form-label">' . lang('E-mail Subscription Type') . '</label>
                                                        <div class="form-field">
                                                        ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'open', 'value'=>'open', 'checked'=>'checked', 'class'=>'radio')) . '
                                                            <label class="" for="open">' . lang('Open') . '</label>
                                                        </div>
                                                        <div class="form-field">
                                                            ' . $liveform->output_field(array('type'=>'radio', 'name'=>'email_subscription_type', 'id'=>'closed', 'value'=>'closed', 'class'=>'radio')) . '
                                                            <label class="" for="closed">' . lang('Closed') . '</label>
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
                    </div>';
}

/**
 * Checks the posted name: required, not used by another contact group.
 * Errors are marked on the form.
 *
 * @param liveform $liveform
 * @param string|null $id  Edited group's id, null on create
 */
function pg_contact_group_form_validate_name($liveform, $id)
{
    $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>lang('Name'))));

    if ($liveform->check_field_error('name') == true) {
        return;
    }

    if ($id === null) {
        $result = db_q("SELECT id FROM contact_groups WHERE (name = ?)", array((string) $liveform->get_field_value('name'))) or output_error('Query failed.');
    } else {
        $result = db_q("SELECT id FROM contact_groups WHERE (name = ?) AND (id != ?)", array((string) $liveform->get_field_value('name'), (string) $id)) or output_error('Query failed.');
    }

    if (mysqli_num_rows($result) > 0) {
        $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
    }
}

/**
 * The four values a save writes, in INSERT/UPDATE order: name, e-mail
 * subscription, subscription type, description.
 *
 * @param liveform $liveform
 * @return array
 */
function pg_contact_group_form_values($liveform)
{
    return array(
        (string) $liveform->get_field_value('name'),
        (string) $liveform->get_field_value('email_subscription'),
        (string) $liveform->get_field_value('email_subscription_type'),
        (string) $liveform->get_field_value('description'),
    );
}
