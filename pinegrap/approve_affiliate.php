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

$contact_id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;

// try to find contact for supplied id
$query = "SELECT
        contacts.id, contacts.email_address, contacts.affiliate_approved, contacts.affiliate_code, contacts.affiliate_name,
        contacts.first_name, contacts.last_name, contacts.company, contacts.website, contacts.business_phone,
        contacts.mobile_phone, contacts.business_city, contacts.business_country, contacts.timestamp,
        user.user_username
    FROM contacts
    LEFT JOIN user ON contacts.user = user.user_id
    WHERE contacts.id = ?";
$result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');

// if contact could not be found output error
if (mysqli_num_rows($result) == 0) {
    output_error(lang('The contact for the affiliate could not be found.'));
}

$row = mysqli_fetch_assoc($result);

// A role-3 user reaches this screen with access to one contact group; the
// contact must be in one of that user's groups, as on the edit contact screen.
if (validate_contact_access($user, $contact_id) == false) {
    log_activity(lang('access denied to approve affiliate because user does not have access to a contact group that the contact is in'), $_SESSION['sessionusername']);
    output_error(lang('Access denied.'));
}

$email_address = $row['email_address'];
$existing_affiliate_code = $row['affiliate_code'];
$already_approved = ($row['affiliate_approved'] == 1);

// The approval link arrives in an e-mail, so it cannot carry the session
// token. The first visit only shows the contact and asks for confirmation;
// the approval itself is a POST that carries the token, so a link on a
// foreign page cannot approve an affiliate on the administrator's behalf.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    require_once(PG_FUNCTIONS_DIR . '/includes/forms/approve_affiliate_form.php');

    $contact_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id;

    $last_modified = ((int) $row['timestamp'] > 0)
        ? get_relative_time(array('timestamp' => (int) $row['timestamp'])) . ((string) $row['user_username'] !== '' ? ' ' . h($row['user_username']) : '')
        : '&mdash;';

    // The request e-mail can be opened more than once: an approved affiliate
    // is only reported, so a second approval sends no second welcome e-mail.
    if ($already_approved) {
        $buttons = '
                                        <a href="' . h($contact_url) . '" class="btn my-1 btn-solid theme-primary"><span class="material-icons me-3" aria-hidden="true">person</span><span class="pg-btn-label">' . lang('Go to Contact') . '</span></a>';
    } else {
        $buttons = '
                                        <button type="submit" id="submit_approve" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">how_to_reg</span><span class="pg-btn-label">' . lang('Approve Affiliate') . '</span></button>';
    }

    $content = pg_approve_affiliate_form_layout(
        pg_approve_affiliate_form_card($row, $already_approved)
        . pg_approve_affiliate_form_status($row, $already_approved, $last_modified, $contact_url),
        $buttons
    );

    // Only a pending request posts: the approval carries the session token.
    if (!$already_approved) {
        $content = '
                <form method="post" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/approve_affiliate.php" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . $contact_id . '">' . $content . '
                </form>';
    }

    echo
        pg_page_shell(
            array(
                'title' => lang('Approve Affiliate'),
                'extra classes' => 'contact',
                'icon' => 'contact',
                'heading' => lang('Approve Affiliate'),
                'heading_description' => lang('Confirm the approval of the affiliate request that reached you by e-mail.'),
                'cancel' => array('enable' => 'true', 'url' => $contact_url),
                'breadcrumb' => array(
                    array('label' => lang('Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'),
                    array('label' => lang('Approve Affiliate')),
                ),
            )
        ) . '
        <main id="content" class="container-fluid">' . $content . '
        </main>
    ' . output_footer();
    exit();
}

validate_token_field();

include_once('liveform.class.php');

// The approval the external API shares (includes/fn/contacts.php): the
// code, the mark, the key code for the group offer, the welcome e-mail and
// the event. A repeated approval (the request e-mail opened twice, two tabs)
// changes nothing and sends no second welcome e-mail.
$approval = pg_affiliate_approve($contact_id, $user['id']);

if ($approval === false) {
    output_error('Query failed.');
}

if ($approval === null) {
    output_error(lang('The contact for the affiliate could not be found.'));
}

// Queued for Edit Contact, the screen the approval leads to, which prints
// and removes it.
$liveform = new liveform('edit_contact');

if ($approval === 'already_approved') {
    $liveform->add_notice(lang('The contact is already an approved affiliate.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id);
    exit();
}

$liveform->add_notice(lang('The affiliate has been approved.'));

header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id);
?>
