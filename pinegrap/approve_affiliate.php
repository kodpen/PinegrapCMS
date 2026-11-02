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
$query = "SELECT id, email_address FROM contacts WHERE id = ?";
$result = db_q($query, array((string) $contact_id)) or output_error('Query failed.');

// if contact could not be found output error
if (mysqli_num_rows($result) == 0) {
    output_error(lang('The contact for the affiliate could not be found.'));
}

$row = mysqli_fetch_assoc($result);

$email_address = $row['email_address'];

// The approval link arrives in an e-mail, so it cannot carry the session
// token. The first visit only shows the contact and asks for confirmation;
// the approval itself is a POST that carries the token, so a link on a
// foreign page cannot approve an affiliate on the administrator's behalf.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $contact_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id;

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
                            <p>' . lang(array('string' => 'Approve {var:1} as an affiliate? The affiliate code is created if there is none yet, and a welcome e-mail is sent to the contact.', 'vars' => '<strong>' . h($email_address) . '</strong>')) . '</p>
                            <form method="post" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/approve_affiliate.php" class="disable_shortcut">
                                ' . get_token_field() . '
                                <input type="hidden" name="id" value="' . $contact_id . '">
                                <button type="submit" class="btn btn-solid theme-primary"><i class="bi bi-check-lg me-1"></i>' . lang('Approve Affiliate') . '</button>
                                <a href="' . $contact_url . '" class="btn btn-outline theme-secondary">' . lang('Cancel') . '</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    ' . output_footer();
    exit();
}

validate_token_field();

// The approval the external API shares (includes/fn/contacts.php): the
// code, the mark, the key code for the group offer, the welcome e-mail and
// the event.
if (pg_affiliate_approve($contact_id, $user['id']) === false) {
    output_error('Query failed.');
}

// add notice to view contact screen
include_once('liveform.class.php');
$liveform = new liveform('view_contact');
$liveform->add_notice(lang('The affiliate has been approved.'));

header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id);
?>
