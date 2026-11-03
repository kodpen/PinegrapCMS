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
$query = "SELECT id, email_address, affiliate_approved, affiliate_code FROM contacts WHERE id = ?";
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
    $contact_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id;

    // The request e-mail can be opened more than once: an approved affiliate
    // is only reported, so a second approval sends no second welcome e-mail.
    if ($already_approved) {
        $output_body = '
                            <p>' . lang(array('string' => '{var:1} is already an approved affiliate. Affiliate code: {var:2}', 'vars' => array('<strong>' . h($email_address) . '</strong>', '<strong>' . h($existing_affiliate_code) . '</strong>'))) . '</p>
                            <a href="' . $contact_url . '" class="btn btn-solid theme-primary">' . lang('Go to Contact') . '</a>';
    } else {
        $output_body = '
                            <p>' . lang(array('string' => 'Approve {var:1} as an affiliate? The affiliate code is created if there is none yet, and a welcome e-mail is sent to the contact.', 'vars' => '<strong>' . h($email_address) . '</strong>')) . '</p>
                            <form method="post" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/approve_affiliate.php" class="disable_shortcut">
                                ' . get_token_field() . '
                                <input type="hidden" name="id" value="' . $contact_id . '">
                                <button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-check-lg me-1"></i>' . lang('Approve Affiliate') . '</button>
                                <a href="' . $contact_url . '" class="btn btn-outline theme-secondary">' . lang('Cancel') . '</a>
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
        <main class="container mb-12" style="min-height:calc(100vh - 175px)" id="content">
            <div class="row">
                <div class="col-12 md:col-8 lg:col-6">
                    <div class="card">
                        <div class="card-body">
                            ' . $output_body . '
                        </div>
                    </div>
                </div>
            </div>
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

$liveform = new liveform('view_contact');

if ($approval === 'already_approved') {
    $liveform->add_notice(lang('The contact is already an approved affiliate.'));
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id);
    exit();
}

$liveform->add_notice(lang('The affiliate has been approved.'));

header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id);
?>
