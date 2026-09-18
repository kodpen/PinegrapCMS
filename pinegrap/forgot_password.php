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

// If the user has not submitted the form, then show form.
if (!$_POST) {
    echo get_forgot_password_screen();
    exit();
}

validate_token_field();

$page_name = db_value(
    "SELECT page_name
    FROM page
    WHERE page_type = 'forgot password'
    LIMIT 1");

if ($page_name != '') {
    $url = PATH . encode_url_path($page_name);
} else {
    $url = PATH . SOFTWARE_DIRECTORY . '/forgot_password.php';
}

$form = new liveform('forgot_password');

$form->add_fields_to_session();

$email = $form->get_field_value('email');
$screen = $form->get_field_value('screen');
$send_to = $form->get_field_value('send_to');

// A designed page (the forgot_password widget) names itself in return_to;
// every answer below - the error, the hint screen, the confirmation - goes
// back to that page instead of the legacy screen. The widget reads the same
// liveform fields (screen, email) and notices this script leaves behind.
$return_to = $form->get_field_value('return_to');
if (is_scalar($return_to) && (string) $return_to !== '') {
    $url = pg_safe_redirect_path((string) $return_to, $url);
}

$form->validate_required_field('email', lang(array('string'=>'{var:1} is required.','vars'=>lang('Email') )) );

// The reset link can only point at a page of type 'set password': that page
// is the sole renderer of the set-password form. set_password.php itself only
// handles that form's POST and starts with the CSRF check, so a link to it
// would greet the visitor with a "session expired" error instead of the form.
// Without such a page there is nowhere to send the visitor, so stop here,
// before the account lookup and the token write, and do not mail a dead link.
// Stopping before the lookup keeps the answer identical for every address.
$set_password_url = get_page_type_url('set password');

if ($set_password_url === false) {
    log_activity('Forgot Password: no set password page');
    $form->mark_error('email', lang('Sorry, password reset is not available on this website right now.'));

    go($url);
}

// Rate limit before the account lookup and, above all, before the mail send
// below. That send holds this request's database connection for as long as the
// mail server takes to answer, which is how a bot loop on this endpoint filled
// max_user_connections and took a live site down on 2026-08-31.
pg_password_reset_guard($email);

// If there is not an error then get user info.
if (!$form->check_form_errors()) {
    $user = db_item(
        "SELECT
            user_id AS id,
            user_username AS username,
            user_password_hint AS password_hint
        FROM user
        WHERE user_email = '" . e($email) . "'");

    // No account for this address: answer exactly as if there were one.
    //
    // Naming the miss turned this form into a lookup service. Anyone could
    // submit addresses and read back which ones this site holds - a list worth
    // money to whoever sends the phishing that follows, and a plausible motive
    // for the flood this endpoint took on 2026-08-31.
    //
    // The confirmation below is deliberately worded as a condition ("if this
    // address is registered") rather than a claim, so it is true either way
    // and no one is told their mail is on its way when it is not.
    if (empty($user['id'])) {
        $form->remove();
        $form->assign_field_value('screen', 'confirm');
        $form->add_notice(lang('If this email address is registered, then we have sent password reset instructions to it. Please also check your spam folder.'));

        go($url);
    }
}

// If there is an error, forward user back to previous screen.
if ($form->check_form_errors()) {
    go($url);
}

// If password hint is enabled and the user has a password hint,
// and the user has not already told us that the password hint did not help,
// then show the password hint to the user.
if (
    PASSWORD_HINT
    && ($user['password_hint'] != '')
    && ($screen != 'password_hint')
) {
    $form->assign_field_value('screen', 'password_hint');

    go($url);
}

// Create a function to get token for email link, because, if the token that we generate is already
// in use, then we will need to use recursion to generate a different token.

function get_token() {

    // Create a random token with lower and uppercase characters and numbers.  We use a length of 16
    // because it will result in a strong token, but it is also short enough so that the link in the
    // email will not break.

    $token['token'] = get_random_string(array(
        'type' => 'letters_and_numbers',
        'length' => 16));

    // Get the hash of the token, because that is how we store it in db.  We store a hash of the
    // token because the token is basically a password that is stored in the db.  If there is a
    // vulnerability that allows someone read access to db, this will prevent attacker from getting
    // token.  Hashing the token is not as important as hashing a password, because the token is
    // not a user's personal password that might be used on multiple sites and token is also
    // time-limited, however we might as well hash it.  We are using sha256 instead of bcrypt,
    // because bcrypt would require us to include user id in set password link in email, which
    // would complicate URL and make it longer, which might result in email client breaking link.

    $token['hash'] = hash('sha256', $token['token']);
    
    // If the token already exists, then use recursion to generate new token.
    if (db("SELECT user_id FROM user WHERE token = '" . $token['hash'] . "' LIMIT 1")) {
        return get_token();
        
    // Otherwise the token is not already in use, so return token.
    } else {
        return $token;
    }
}

$token = get_token();

// Insert token into database
db(
    "UPDATE user 
    SET 
        token = '" . $token['hash'] . "',
        token_timestamp = UNIX_TIMESTAMP()
    WHERE user_id = '" . $user['id'] . "'");

// Send reset password email to user. We use a short query string parameter ("k") for the token, to
// prevent the link from from being too long and breaking in email clients.  We use "k" instead of
// "t" for the token, because "t" is already used for tracking codes.
//
// The link is built from the configured hostname, never from the request's
// Host header: this endpoint takes anonymous posts, so a spoofed header would
// otherwise mail the victim a reset link that hands the token to another host.

email(array(
    'to' => $email,
    'from_name' => ORGANIZATION_NAME,
    'from_email_address' => EMAIL_ADDRESS,
    'subject' => lang('Reset Password'),
    'body' =>
        lang('We received a request to reset your password. You can reset your password by clicking the link below.') . "\n" .
        "\n" .
        URL_SCHEME . HOSTNAME_SETTING . $set_password_url . '?k=' . $token['token'] . "\n" .
        "\n" .
        lang('If you did not make this request, then you may safely ignore this email, and your password will remain the same.') ));

log_activity(lang('User requested reset password email.'), $user['username']);

$form->remove();

$form->assign_field_value('screen', 'confirm');

// Word for word the message an unknown address gets. Unifying the outcome is
// the whole point: two confirmations that read differently are still an
// answer to "does this address have an account here", just a politer one.
$form->add_notice(lang('If this email address is registered, then we have sent password reset instructions to it. Please also check your spam folder.'));

go($url);