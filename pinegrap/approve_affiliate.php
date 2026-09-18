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

$contact_id = (int) ($_REQUEST['id'] ?? 0);

// try to find contact for supplied id
$query = "SELECT id, email_address, affiliate_code FROM contacts WHERE id = '" . $contact_id . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// if contact could not be found output error
if (mysqli_num_rows($result) == 0) {
    output_error(lang('The contact for the affiliate could not be found.'));
}

$row = mysqli_fetch_assoc($result);

$email_address = $row['email_address'];
$existing_affiliate_code = $row['affiliate_code'];

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
        <main class="container mb-5" style="min-height:calc(100vh - 175px)" id="content">
            <div class="row">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <p>' . lang(array('string' => 'Approve {var:1} as an affiliate? The affiliate code is created if there is none yet, and a welcome e-mail is sent to the contact.', 'vars' => '<strong>' . h($email_address) . '</strong>')) . '</p>
                            <form method="post" action="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/approve_affiliate.php" class="disable_shortcut">
                                ' . get_token_field() . '
                                <input type="hidden" name="id" value="' . $contact_id . '">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>' . lang('Approve Affiliate') . '</button>
                                <a href="' . $contact_url . '" class="btn btn-outline-secondary">' . lang('Cancel') . '</a>
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

// if there is an existing affiliate code
if ($existing_affiliate_code) {
    $affiliate_code = $existing_affiliate_code;
    
// else there is not an existing affiliate code, so generate code
} else {
    $affiliate_code = generate_affiliate_code();
}

// update contact to be approved and update affiliate code
$query = "UPDATE contacts
         SET
            affiliate_approved = '1',
            affiliate_code = '" . escape($affiliate_code) . "',
            user = '" . $user['id'] . "',
            timestamp = UNIX_TIMESTAMP()
         WHERE id = '" . $contact_id . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');

// if there is a group offer, then determine if we need to add a key code for group offer for this affiliate
if (AFFILIATE_GROUP_OFFER_ID != 0) {
    // check if offer exists and get offer code
    $query = "SELECT code FROM offers WHERE id = '" . AFFILIATE_GROUP_OFFER_ID . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    
    // if an offer was found, then continue to check if a key code should be added for group offer
    if (mysqli_num_rows($result) > 0) {
        $offer = mysqli_fetch_assoc($result);
        
        // check if a key code already exists for this group offer and affiliate
        $query =
            "SELECT id
            FROM key_codes
            WHERE
                (code = '" . escape($affiliate_code) . "')
                AND (offer_code = '" . escape($offer['code']) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if a key code does not already exist for this group offer and affiliate, then create key code
        if (mysqli_num_rows($result) == 0) {
            $query =
                "INSERT INTO key_codes (
                    code,
                    offer_code,
                    enabled,
                    user,
                    timestamp)
                VALUES (
                    '" . escape($affiliate_code) . "',
                    '" . escape($offer['code']) . "',
                    '1',
                    '" . $user['id'] . "',
                    UNIX_TIMESTAMP())";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }
}

// store values in session, so they are accessible on affiliate welcome screen
$_SESSION['software']['affiliate_welcome']['affiliate_code'] = $affiliate_code;

email(array(
    'to' => $email_address,
    'bcc' => AFFILIATE_EMAIL_ADDRESS,
    'from_name' => ORGANIZATION_NAME,
    'from_email_address' => EMAIL_ADDRESS,
    'subject' => lang('Welcome to the Affiliate Program'),
    'format' => 'html',
    'body' => get_affiliate_welcome_screen()));

// add notice to view contact screen
include_once('liveform.class.php');
$liveform = new liveform('view_contact');
$liveform->add_notice(lang('The affiliate has been approved.'));

header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_contact.php?id=' . $contact_id);
?>
