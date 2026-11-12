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

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/contact_form.php');

if (!$_POST) {
    $contact_groups = pg_contact_form_groups($user);

    print
    pg_page_shell(
        array(
            'title'=> lang('Create Contact'),
            'extra classes'=>'contact pg-measure-edit',
            'icon'=>'contact',
            'heading'=>lang('Create Contact'),
            'heading_description' => lang('Create a new contact, subscriber, unregistered member, or unapproved affiliate, and add them to any of my contact groups.'),
            'cancel' => array('enable' => 'true', 'url' => pg_send_to_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php')),
            'breadcrumb' => array(
                array('label' => lang('Contacts'), 'url' => pg_send_to_list_url(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php')),
                array('label' => lang('Create Contact')),
            ),
        )
    ) . '
<main id="content" class="container-fluid">
                <form name="form" action="add_contact.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                    ' . pg_contact_form_layout(
                        pg_contact_form_name_card(array(), true) . '
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('New Contact\'s Subscriber Information') . '
                                </h2>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="email_address" class="form-label">' . lang('Email') . '</label>
                                            <input type="text" name="email_address" autocomplete="off" id="email_address" class="form-control text-end" maxlength="100" inputmode="email" data-inputmask-alias="email" />
                                        </div>
                                        <div class="col-12 my-1">
                                            <div class="form-field">
                                                <div class="switch"><input class="" type="checkbox" id="opt_in" name="opt_in" value="1" checked="checked"></div>
                                                <label class="" for="opt_in">' . lang('Opt-In Status') . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ' . pg_contact_form_detail_cards(array(), true, '', pg_contact_form_group_cells($contact_groups, array())),
                        pg_contact_form_picture_card(true, ''),
                        '
                                        <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
                    ) . '
                </form>

    <script>
        // The photo x removes its item from an inline handler. This listener
        // runs first, in the capture phase, and moves focus to Change Image,
        // so focus does not fall to the page top with the removed button.
        (function () {
            var photos = document.getElementById("software_image_picker_container");
            if (photos) {
                photos.addEventListener("click", function (event) {
                    if (event.target.closest(".item .card-header button[onclick]")) {
                        photos.closest(".card").querySelector(".card-footer button").focus();
                    }
                }, true);
            }
        }());
    </script>
</main>' .
        output_footer();

} else {

    validate_token_field();

    $_POST['email_address'] = pg_contact_form_email($_POST['email_address'] ?? '');

    // The code is saved even while the affiliate program is off ('').
    $affiliate_code = (AFFILIATE_PROGRAM == true) ? pg_contact_form_affiliate_code($_POST, 0) : '';

    // get all contact groups, so that contact can be added to selected contact groups
    $contact_groups = pg_contact_form_all_groups();

    pg_contact_form_check($user, $contact_groups, $_POST);

    $columns = pg_contact_form_columns($_POST, $user['id']) + pg_contact_form_affiliate_columns($_POST, $affiliate_code);

    db_q(
        "INSERT INTO contacts (" . implode(', ', array_keys($columns)) . ", timestamp)
        VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", UNIX_TIMESTAMP())",
        array_values($columns)) or output_error('Query failed');

    $contact_id = mysqli_insert_id(db::$con);

    pg_audit_record('create', 'contacts', $contact_id);

    // A contact that did not exist a moment ago. The panel's own screen is as
    // much a source of new customers as the checkout is, so an integration
    // watching the address book hears about this one too.
    pg_announce_contact_created($contact_id);

    // The other contacts with this address take its opt-in status; the new
    // contact's create line is already written.
    pg_contact_form_sync_opt_in($contact_id, $_POST['email_address'], $columns['opt_in']);

    pg_contact_form_save_groups($user, $contact_id, $contact_groups, $_POST);

    // An approved affiliate gets a key code for the affiliate group offer.
    if ((AFFILIATE_PROGRAM == TRUE) && (($_POST['affiliate_approved'] ?? '') == 1)) {
        pg_contact_form_affiliate_key_code($affiliate_code, $user['id']);
    }

    log_activity(lang(array('string' => '{var:1} ({var:2}) was created', 'vars' => array(lang('contact'), $_POST['first_name'] . ' ' . $_POST['last_name']))), $_SESSION['sessionusername']);

    $liveform_view_contacts = new liveform('view_contacts');
    $liveform_view_contacts->add_notice(lang('The contact has been created.'));

    pg_contact_form_redirect();
}
?>